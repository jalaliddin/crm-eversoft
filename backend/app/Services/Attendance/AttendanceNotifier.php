<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Organization;
use App\Notifications\AbsenceDigestNotification;
use App\Notifications\EarlyLeaveNotification;
use App\Notifications\EmployeeLateNotification;
use App\Services\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\Notification;

class AttendanceNotifier
{
    public function __construct(private readonly NotificationRecipients $recipients) {}

    /** Notify HR once when a record first becomes late / early leave. */
    public function statusChanged(AttendanceRecord $record, ?AttendanceStatus $previous): void
    {
        if ($record->status === $previous) {
            return;
        }

        $payload = [
            'employee' => $record->employee->full_name,
            'employee_id' => $record->employee_id,
            'date' => $record->date->format('Y-m-d'),
            'link' => '/hr/employees/'.$record->employee_id.'?tab=attendance',
        ];

        $notification = match ($record->status) {
            AttendanceStatus::Late => new EmployeeLateNotification($record->organization_id, [...$payload, 'minutes' => $record->late_minutes]),
            AttendanceStatus::EarlyLeave => new EarlyLeaveNotification($record->organization_id, [...$payload, 'minutes' => $record->early_leave_minutes]),
            default => null,
        };

        if ($notification !== null) {
            Notification::send($this->recipients->withPermission($record->organization_id, 'attendance.view'), $notification);
        }
    }

    /** @param list<string> $names */
    public function absenceDigest(Organization $organization, string $date, int $count, array $names): void
    {
        if ($count === 0) {
            return;
        }

        Notification::send(
            $this->recipients->withPermission($organization->id, 'attendance.view'),
            new AbsenceDigestNotification($organization->id, [
                'date' => $date,
                'count' => $count,
                'names' => array_slice($names, 0, 20),
                'link' => '/hr/attendance?date='.$date.'&status=absent',
            ]),
        );
    }
}
