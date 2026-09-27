<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PenaltyCalculation;
use App\Enums\PenaltyStatus;
use App\Enums\PenaltyType;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Payroll\Penalty;
use App\Models\Payroll\PenaltyRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Hr\WorkingTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Applies tenant-configured penalty rules to attendance records.
 *
 * For each incident type the most specific matching rule (highest min_minutes) wins, so tiered
 * rules like "late > 15 min → X" and "late > 60 min → Y" never stack. Automatic penalties are
 * idempotent per (attendance record, type); approved/rejected ones are never changed automatically.
 */
class PenaltyService
{
    /** @var array<int, Collection<int, PenaltyRule>> */
    private array $rules = [];

    public function __construct(
        private readonly WorkingTimeService $workingTime,
        private readonly AuditLogger $audit,
    ) {}

    public function syncForRecord(AttendanceRecord $record): void
    {
        $employee = $record->employee;
        $date = CarbonImmutable::parse($record->date->format('Y-m-d'));

        $incidents = [
            PenaltyType::Late->value => $record->late_minutes,
            PenaltyType::EarlyLeave->value => $record->early_leave_minutes,
            PenaltyType::Absence->value => $record->status === AttendanceStatus::Absent ? 1 : 0,
        ];

        $existing = Penalty::withoutGlobalScopes()
            ->where('attendance_record_id', $record->id)
            ->where('is_automatic', true)
            ->get()
            ->keyBy(fn (Penalty $p) => $p->type->value);

        foreach ($incidents as $type => $minutes) {
            $rule = $minutes > 0 ? $this->matchingRule($record->organization_id, PenaltyType::from($type), $type === 'absence' ? 0 : $minutes) : null;
            /** @var Penalty|null $current */
            $current = $existing->get($type);

            if ($rule === null) {
                if ($current !== null && $current->status === PenaltyStatus::Pending) {
                    $current->delete();
                }

                continue;
            }

            $amount = $this->amountFor($rule, $employee, $date, $type === 'absence' ? 0 : $minutes);
            $reason = $this->reasonFor($rule, $type, $minutes);

            if ($current !== null) {
                if ($current->status === PenaltyStatus::Pending) {
                    $current->update(['penalty_rule_id' => $rule->id, 'amount' => $amount, 'percentage' => $rule->percentage, 'reason' => $reason]);
                }

                continue;
            }

            $autoApprove = (bool) $employee->organization->setting('penalties.auto_approve', false);

            $penalty = new Penalty([
                'employee_id' => $record->employee_id,
                'attendance_record_id' => $record->id,
                'penalty_rule_id' => $rule->id,
                'date' => $date->format('Y-m-d'),
                'type' => $type,
                'amount' => $amount,
                'percentage' => $rule->percentage,
                'reason' => $reason,
                'status' => $autoApprove ? PenaltyStatus::Approved : PenaltyStatus::Pending,
                'is_automatic' => true,
                'applied_at' => now(),
                'approved_at' => $autoApprove ? now() : null,
            ]);
            // Tenant key comes from the record, never from input (not mass assignable).
            $penalty->organization_id = $record->organization_id;
            $penalty->save();
        }
    }

    public function matchingRule(int $organizationId, PenaltyType $type, int $minutes): ?PenaltyRule
    {
        $this->rules[$organizationId] ??= PenaltyRule::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderByDesc('min_minutes')
            ->get();

        return $this->rules[$organizationId]
            ->first(fn (PenaltyRule $rule) => $rule->type === $type && $rule->matches($minutes));
    }

    public function amountFor(PenaltyRule $rule, Employee $employee, CarbonImmutable $date, int $minutes): float
    {
        $amount = match ($rule->calculation) {
            PenaltyCalculation::Fixed => (float) $rule->amount,
            PenaltyCalculation::PerMinute => (float) $rule->amount * $minutes,
            PenaltyCalculation::PercentOfDaily => $this->workingTime->dailyRate($employee, $date) * ((float) $rule->percentage / 100),
            PenaltyCalculation::DailySalary => $this->workingTime->dailyRate($employee, $date),
        };

        return round($amount, 2);
    }

    /** @param array<string, mixed> $data */
    public function createManual(Employee $employee, array $data, User $user): Penalty
    {
        $penalty = Penalty::query()->create([
            'employee_id' => $employee->id,
            'attendance_record_id' => $data['attendance_record_id'] ?? null,
            'date' => $data['date'],
            'type' => $data['type'] ?? PenaltyType::Manual->value,
            'amount' => $data['amount'],
            'percentage' => $data['percentage'] ?? null,
            'reason' => $data['reason'],
            'status' => PenaltyStatus::Pending,
            'is_automatic' => false,
            'applied_by' => $user->id,
            'applied_at' => now(),
        ]);

        return $penalty;
    }

    public function approve(Penalty $penalty, User $user): Penalty
    {
        $this->assertPending($penalty);
        $penalty->update(['status' => PenaltyStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->audit->log('penalty.approved', $penalty, ['status' => 'pending'], ['status' => 'approved', 'amount' => $penalty->amount]);

        return $penalty;
    }

    public function reject(Penalty $penalty, User $user): Penalty
    {
        $this->assertPending($penalty);
        $penalty->update(['status' => PenaltyStatus::Rejected, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->audit->log('penalty.rejected', $penalty, ['status' => 'pending'], ['status' => 'rejected']);

        return $penalty;
    }

    public function cancel(Penalty $penalty, User $user): Penalty
    {
        if ($penalty->status === PenaltyStatus::Cancelled) {
            return $penalty;
        }
        $old = $penalty->status->value;
        $penalty->update(['status' => PenaltyStatus::Cancelled, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->audit->log('penalty.cancelled', $penalty, ['status' => $old], ['status' => 'cancelled']);

        return $penalty;
    }

    private function assertPending(Penalty $penalty): void
    {
        if ($penalty->status !== PenaltyStatus::Pending) {
            throw new BusinessRuleException('Only pending penalties can be approved or rejected.');
        }
    }

    private function reasonFor(PenaltyRule $rule, string $type, int $minutes): string
    {
        return match ($type) {
            'late' => sprintf('%s: late by %d min', $rule->name, $minutes),
            'early_leave' => sprintf('%s: left %d min early', $rule->name, $minutes),
            default => $rule->name.': absence',
        };
    }
}
