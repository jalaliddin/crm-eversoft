<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeviceIdentifier extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'device_id', 'identifier', 'card_number', 'biometric_identifier', 'status'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }
}
