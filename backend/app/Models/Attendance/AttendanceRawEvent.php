<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Enums\DeviceEventType;
use App\Enums\RawEventStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'device_id', 'external_event_id', 'employee_identifier', 'card_number', 'employee_id', 'event_type',
        'event_time', 'payload', 'processed_at', 'processing_status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => DeviceEventType::class,
            'processing_status' => RawEventStatus::class,
            'event_time' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
