<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Hr\Employee;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRecord;
use App\Models\User;
use App\Notifications\PayrollCalculatedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Payroll period workflow: open → calculated (repeatable) → approved (locked) → closed; records → paid. */
class PayrollService
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly AuditLogger $audit,
        private readonly NotificationRecipients $recipients,
    ) {}

    public function open(int $year, int $month, User $user): PayrollPeriod
    {
        $exists = PayrollPeriod::query()->where('year', $year)->where('month', $month)->exists();
        if ($exists) {
            throw new BusinessRuleException('A payroll period for this month already exists.', ['month' => ['Period already exists.']]);
        }

        $start = CarbonImmutable::create($year, $month, 1);

        return PayrollPeriod::query()->create([
            'year' => $year,
            'month' => $month,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->endOfMonth()->format('Y-m-d'),
            'status' => PayrollPeriodStatus::Open,
            'opened_at' => now(),
            'created_by' => $user->id,
        ]);
    }

    /**
     * (Re)calculate every employee employed during the period. Manual bonuses are preserved.
     *
     * @param  list<int>|null  $employeeIds  limit to these employees
     */
    public function calculate(PayrollPeriod $period, ?User $user = null, ?array $employeeIds = null): PayrollPeriod
    {
        if ($period->isLocked()) {
            throw new BusinessRuleException('Approved or closed payroll periods cannot be recalculated.');
        }

        $organization = $period->organization;
        $start = CarbonImmutable::parse($period->start_date->format('Y-m-d'));
        $end = CarbonImmutable::parse($period->end_date->format('Y-m-d'));

        Employee::query()
            ->where('organization_id', $period->organization_id)
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $end->format('Y-m-d')))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $start->format('Y-m-d')))
            ->where(fn ($q) => $q->where('status', '!=', EmployeeStatus::Terminated->value)->orWhereNotNull('termination_date'))
            ->when($employeeIds, fn ($q) => $q->whereIn('id', $employeeIds))
            ->chunkById(200, function ($employees) use ($period, $organization, $start, $end): void {
                DB::transaction(function () use ($employees, $period, $organization, $start, $end): void {
                    $existing = PayrollRecord::query()
                        ->where('payroll_period_id', $period->id)
                        ->whereIn('employee_id', $employees->pluck('id'))
                        ->get()
                        ->keyBy('employee_id');

                    foreach ($employees as $employee) {
                        /** @var PayrollRecord|null $record */
                        $record = $existing->get($employee->id);
                        if ($record !== null && in_array($record->status, [PayrollRecordStatus::Paid, PayrollRecordStatus::Cancelled], true)) {
                            continue;
                        }

                        $attributes = $this->calculator->calculate($employee, $organization, $start, $end, (float) ($record?->bonus_amount ?? 0));

                        PayrollRecord::query()->updateOrCreate(
                            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                            [...$attributes, 'status' => PayrollRecordStatus::Calculated],
                        );
                    }
                });
            });

        $period->update(['status' => PayrollPeriodStatus::Calculated, 'calculated_at' => now()]);

        $totals = $period->records()->selectRaw('count(*) as c, coalesce(sum(net_amount), 0) as net')->first();
        Notification::send(
            $this->recipients->withPermission($period->organization_id, 'payroll.approve'),
            new PayrollCalculatedNotification($period->organization_id, [
                'period' => sprintf('%04d-%02d', $period->year, $period->month),
                'records' => (int) $totals->c,
                'net_total' => number_format((float) $totals->net, 2, '.', ' '),
                'currency' => $organization->currency,
                'link' => '/hr/payroll/'.$period->id,
            ]),
        );

        return $period->refresh();
    }

    public function updateBonus(PayrollRecord $record, float $bonus): PayrollRecord
    {
        if ($record->period->isLocked() || $record->status === PayrollRecordStatus::Paid) {
            throw new BusinessRuleException('This payroll record is locked.');
        }

        $record->bonus_amount = round($bonus, 2);
        $record->net_amount = round(max(0, (float) $record->gross_amount + $bonus - (float) $record->penalty_amount), 2);
        $record->save();

        return $record;
    }

    public function approve(PayrollPeriod $period, User $user): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::Calculated) {
            throw new BusinessRuleException('Only calculated payroll periods can be approved.');
        }

        DB::transaction(function () use ($period, $user): void {
            $period->records()->where('status', PayrollRecordStatus::Calculated->value)->update(['status' => PayrollRecordStatus::Approved->value]);
            $period->update(['status' => PayrollPeriodStatus::Approved, 'approved_at' => now(), 'approved_by' => $user->id]);
        });

        $this->audit->log('payroll.approved', $period, ['status' => 'calculated'], ['status' => 'approved']);

        return $period->refresh();
    }

    public function markPaid(PayrollPeriod $period, User $user): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::Approved) {
            throw new BusinessRuleException('Only approved payroll periods can be marked as paid.');
        }

        DB::transaction(function () use ($period): void {
            $period->records()->where('status', PayrollRecordStatus::Approved->value)->update(['status' => PayrollRecordStatus::Paid->value, 'paid_at' => now()]);
            $period->update(['status' => PayrollPeriodStatus::Closed, 'closed_at' => now()]);
        });

        $this->audit->log('payroll.paid', $period, ['status' => 'approved'], ['status' => 'closed']);

        return $period->refresh();
    }

    public function reopen(PayrollPeriod $period, User $user): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::Approved) {
            throw new BusinessRuleException('Only approved (unpaid) periods can be reopened.');
        }

        DB::transaction(function () use ($period): void {
            $period->records()->where('status', PayrollRecordStatus::Approved->value)->update(['status' => PayrollRecordStatus::Calculated->value]);
            $period->update(['status' => PayrollPeriodStatus::Calculated, 'approved_at' => null, 'approved_by' => null]);
        });

        $this->audit->log('payroll.reopened', $period, ['status' => 'approved'], ['status' => 'calculated']);

        return $period->refresh();
    }
}
