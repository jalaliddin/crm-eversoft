<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\RawEventStatus;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Hr\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * pending raw events → resolve employee → recompute affected (employee, business date) records.
 * Unknown identifiers are kept as "unmatched" for the admin screen and can be reprocessed later.
 */
class RawEventProcessor
{
    public function __construct(
        private readonly EmployeeIdentifierResolver $identifiers,
        private readonly AttendanceProcessor $processor,
    ) {}

    /**
     * @param  list<string>  $statuses
     * @return array{processed: int, unmatched: int, failed: int, records: int}
     */
    public function process(int $organizationId, ?int $deviceId = null, array $statuses = ['pending']): array
    {
        $stats = ['processed' => 0, 'unmatched' => 0, 'failed' => 0, 'records' => 0];

        AttendanceRawEvent::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->whereIn('processing_status', $statuses)
            ->orderBy('id')
            ->chunkById(500, function ($events) use ($organizationId, &$stats): void {
                /** @var array<string, array{0: int, 1: string}> $affected */
                $affected = [];
                $employees = [];

                foreach ($events as $event) {
                    $employeeId = $this->identifiers->resolve($organizationId, $event->device_id, $event->employee_identifier, $event->card_number);

                    if ($employeeId === null) {
                        $event->forceFill([
                            'processing_status' => RawEventStatus::Unmatched,
                            'processed_at' => now(),
                            'error_message' => 'No employee mapped to this identifier.',
                        ])->save();
                        $stats['unmatched']++;

                        continue;
                    }

                    $employee = $employees[$employeeId] ??= Employee::withoutGlobalScopes()->with('organization')->find($employeeId);
                    if ($employee === null) {
                        $stats['unmatched']++;

                        continue;
                    }

                    $event->forceFill([
                        'employee_id' => $employeeId,
                        'processing_status' => RawEventStatus::Processed,
                        'processed_at' => now(),
                        'error_message' => null,
                    ])->save();
                    $stats['processed']++;

                    $date = $this->processor->resolver()->businessDateFor($employee, $event->event_time)->format('Y-m-d');
                    $affected[$employeeId.'|'.$date] = [$employeeId, $date];
                }

                foreach ($affected as [$employeeId, $date]) {
                    try {
                        $this->processor->recompute($employees[$employeeId], CarbonImmutable::parse($date));
                        $stats['records']++;
                    } catch (Throwable $e) {
                        $stats['failed']++;
                        Log::channel('attendance')->error('Attendance recompute failed', [
                            'organization_id' => $organizationId,
                            'employee_id' => $employeeId,
                            'date' => $date,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            });

        return $stats;
    }
}
