<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Enums\LeadStatus;
use App\Models\Crm\Activity;
use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Models\User;
use App\Services\Attendance\AttendanceMonitorService;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Tenant dashboard: only sections the user is permitted to see are computed. */
class CrmDashboardService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AttendanceMonitorService $monitor,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, string $from, string $to): array
    {
        $tz = $this->tenant->timezone();
        [$fromUtc, $toUtc] = TenantTime::utcRange($from, $to, $tz);
        $bucket = $this->bucket($from, $to);

        $result = ['range' => ['from' => $from, 'to' => $to, 'bucket' => $bucket]];

        if ($user->hasPermission('customers.view')) {
            $result['crm'] = $this->crm($user, $fromUtc, $toUtc, $tz, $bucket, $from, $to);
        }

        if ($user->hasPermission('attendance.view')) {
            $result['hr'] = $this->hr($from, $to);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function crm(User $user, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, string $tz, string $bucket, string $from, string $to): array
    {
        $leads = fn () => Lead::query()->visibleTo($user);
        $deals = fn () => Deal::query()->visibleTo($user);
        $activities = fn () => Activity::query()->visibleTo($user);
        $todayLocal = CarbonImmutable::now($tz);

        $openDeals = $deals()->where('status', DealStatus::Open->value)->selectRaw('count(*) c, coalesce(sum(value),0) v')->first();
        $won = $deals()->where('status', DealStatus::Won->value)->whereBetween('won_at', [$fromUtc, $toUtc])->selectRaw('count(*) c, coalesce(sum(value),0) v')->first();
        $lost = $deals()->where('status', DealStatus::Lost->value)->whereBetween('lost_at', [$fromUtc, $toUtc])->count();

        $cards = [
            'customers' => Customer::query()->count(),
            'new_customers' => Customer::query()->whereBetween('created_at', [$fromUtc, $toUtc])->count(),
            'new_leads' => $leads()->whereBetween('created_at', [$fromUtc, $toUtc])->count(),
            'open_deals' => (int) $openDeals->c,
            'open_deals_value' => (float) $openDeals->v,
            'won_deals' => (int) $won->c,
            'won_deals_value' => (float) $won->v,
            'lost_deals' => $lost,
            'open_tasks' => $activities()->where('type', ActivityType::Task->value)
                ->whereIn('status', [ActivityStatus::Pending->value, ActivityStatus::InProgress->value])->count(),
            'overdue_tasks' => $activities()->whereIn('status', [ActivityStatus::Pending->value, ActivityStatus::InProgress->value])
                ->where('due_at', '<', now())->count(),
            'today_activities' => $activities()->whereBetween('due_at', [$todayLocal->startOfDay()->utc(), $todayLocal->endOfDay()->utc()])->count(),
        ];

        $funnel = collect(LeadStatus::cases())->map(fn (LeadStatus $s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'count' => 0,
        ])->keyBy('status');
        $leads()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')
            ->each(function ($count, $status) use ($funnel): void {
                if ($funnel->has($status)) {
                    $funnel[$status] = [...$funnel[$status], 'count' => (int) $count];
                }
            });

        $pipeline = Pipeline::query()->orderByDesc('is_default')->orderBy('id')->with('stages')->first();
        $stageStats = $pipeline
            ? $deals()->where('pipeline_id', $pipeline->id)->selectRaw('pipeline_stage_id, count(*) c, coalesce(sum(value),0) v')
                ->groupBy('pipeline_stage_id')->get()->keyBy('pipeline_stage_id')
            : collect();

        return [
            'cards' => $cards,
            'customer_growth' => $this->series(Customer::query(), 'created_at', $tz, $bucket, $from, $to, 'count(*)', Customer::query()->where('created_at', '<', $fromUtc)->count()),
            'lead_funnel' => $funnel->values(),
            'deal_pipeline' => $pipeline ? $pipeline->stages->map(fn ($stage) => [
                'stage_id' => $stage->id,
                'stage' => $stage->name,
                'color' => $stage->color,
                'type' => $stage->type->value,
                'count' => (int) ($stageStats[$stage->id]->c ?? 0),
                'value' => (float) ($stageStats[$stage->id]->v ?? 0),
            ])->values() : [],
            'deal_value' => $this->series($deals()->where('status', DealStatus::Won->value), 'won_at', $tz, $bucket, $from, $to, 'coalesce(sum(value),0)'),
            'currency' => $this->tenant->require()->currency,
        ];
    }

    /** @return array<string, mixed> */
    private function hr(string $from, string $to): array
    {
        return [
            'today' => $this->monitor->summary(CarbonImmutable::now($this->tenant->timezone())->format('Y-m-d')),
            'attendance_trend' => $this->monitor->trend($from, $to),
        ];
    }

    /**
     * Time series bucketed in tenant local time. When $cumulativeBase is given, values are running totals.
     *
     * @return list<array{date: string, value: float}>
     */
    private function series($query, string $column, string $tz, string $bucket, string $from, string $to, string $aggregate, ?int $cumulativeBase = null): array
    {
        [$fromUtc, $toUtc] = TenantTime::utcRange($from, $to, $tz);
        $local = TenantTime::localDateSql($column, $tz);
        $key = $bucket === 'month' ? "DATE_FORMAT({$local}, '%Y-%m-01')" : $local;

        $rows = $query->whereBetween($column, [$fromUtc, $toUtc])
            ->selectRaw("{$key} as bucket, {$aggregate} as value")
            ->groupBy(DB::raw('bucket'))
            ->pluck('value', 'bucket');

        $points = [];
        $running = (float) ($cumulativeBase ?? 0);
        $period = CarbonPeriod::create($from, $bucket === 'month' ? '1 month' : '1 day', $to);
        foreach ($period as $date) {
            $label = $bucket === 'month' ? $date->format('Y-m-01') : $date->format('Y-m-d');
            $value = (float) ($rows[$label] ?? 0);
            $running += $value;
            $points[] = ['date' => $label, 'value' => $cumulativeBase !== null ? $running : $value];
        }

        return $points;
    }

    private function bucket(string $from, string $to): string
    {
        return CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 62 ? 'month' : 'day';
    }
}
