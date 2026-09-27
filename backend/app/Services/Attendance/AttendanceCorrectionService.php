<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manual attendance entry and correction by HR. Every change stores who/when/why and old/new values.
 * Times without an explicit UTC offset are interpreted in the schedule/tenant timezone.
 */
class AttendanceCorrectionService
{
    public function __construct(
        private readonly AttendanceProcessor $processor,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array{date: string, check_in?: ?string, check_out?: ?string, status?: ?string, notes?: ?string, reason: string} $data */
    public function create(Employee $employee, array $data, User $user): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $data, $user) {
            $exists = AttendanceRecord::query()->where('employee_id', $employee->id)->where('date', $data['date'])->lockForUpdate()->exists();
            if ($exists) {
                throw new BusinessRuleException('An attendance record already exists for this date. Edit it instead.', ['date' => ['Record already exists.']]);
            }

            $record = new AttendanceRecord(['employee_id' => $employee->id, 'date' => $data['date'], 'status' => AttendanceStatus::Absent]);
            $record->setRelation('employee', $employee);
            $this->apply($record, $data, $user, AttendanceSource::Manual);

            $this->log($record, 'created', [], $this->snapshot($record), $data['reason'], $user);

            return $record;
        });
    }

    /** @param array{check_in?: ?string, check_out?: ?string, status?: ?string, notes?: ?string, reason: string} $data */
    public function update(AttendanceRecord $record, array $data, User $user): AttendanceRecord
    {
        return DB::transaction(function () use ($record, $data, $user) {
            $record = AttendanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $old = $this->snapshot($record);

            $this->apply($record, $data, $user, $record->source);
            $new = $this->snapshot($record);

            $changedOld = array_diff_assoc(array_map('strval', $old), array_map('strval', $new));
            $this->log($record, 'updated', array_intersect_key($old, $changedOld), array_intersect_key($new, $changedOld), $data['reason'], $user);

            return $record;
        });
    }

    public function delete(AttendanceRecord $record, string $reason, User $user): void
    {
        DB::transaction(function () use ($record, $reason, $user): void {
            $this->log($record, 'deleted', $this->snapshot($record), [], $reason, $user);
            $record->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function apply(AttendanceRecord $record, array $data, User $user, AttendanceSource $source): void
    {
        $day = $this->processor->resolver()->daySchedule($record->employee, $record->date->format('Y-m-d'));

        if (array_key_exists('check_in', $data)) {
            $record->actual_check_in = $this->parseTime($data['check_in'], $day->timezone);
        }
        if (array_key_exists('check_out', $data)) {
            $record->actual_check_out = $this->parseTime($data['check_out'], $day->timezone);
        }
        if ($record->actual_check_in && $record->actual_check_out && $record->actual_check_out->lessThanOrEqualTo($record->actual_check_in)) {
            throw new BusinessRuleException('Check-out must be after check-in.', ['check_out' => ['Check-out must be after check-in.']]);
        }
        if (array_key_exists('notes', $data)) {
            $record->notes = $data['notes'];
        }

        $record->fill([
            'source' => $source,
            'is_manual' => true,
            'edited_by' => $user->id,
            'edited_at' => now(),
            'edit_reason' => $data['reason'],
        ]);

        $forced = ! empty($data['status']) ? AttendanceStatus::from($data['status']) : null;
        $this->processor->recalculate($record, null, $forced);
    }

    private function parseTime(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $timezone)->utc();
    }

    /** @return array<string, mixed> */
    private function snapshot(AttendanceRecord $record): array
    {
        $values = [];
        foreach (AttendanceRecord::AUDITED as $key) {
            $value = $record->getAttribute($key);
            $values[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function log(AttendanceRecord $record, string $action, array $old, array $new, string $reason, User $user): void
    {
        AttendanceAuditLog::query()->create([
            'attendance_record_id' => $action === 'deleted' ? null : $record->id,
            'employee_id' => $record->employee_id,
            'date' => $record->date->format('Y-m-d'),
            'action' => $action,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'reason' => $reason,
            'user_id' => $user->id,
        ]);

        $this->audit->log('attendance.corrected', $record, $old, [...$new, 'action' => $action, 'reason' => $reason]);
    }
}
