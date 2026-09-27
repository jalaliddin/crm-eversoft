<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** Enum options and lightweight lookups used by forms and filters. */
class MetaController extends Controller
{
    public function options(): JsonResponse
    {
        return $this->ok([
            'customer_types' => Enums\CustomerType::options(),
            'customer_statuses' => Enums\CustomerStatus::options(),
            'lead_statuses' => Enums\LeadStatus::options(),
            'deal_statuses' => Enums\DealStatus::options(),
            'activity_types' => Enums\ActivityType::options(),
            'activity_statuses' => Enums\ActivityStatus::options(),
            'activity_priorities' => Enums\ActivityPriority::options(),
            'employment_types' => Enums\EmploymentType::options(),
            'salary_types' => Enums\SalaryType::options(),
            'employee_statuses' => Enums\EmployeeStatus::options(),
            'genders' => Enums\Gender::options(),
            'schedule_types' => Enums\ScheduleType::options(),
            'late_modes' => Enums\LateMode::options(),
            'attendance_statuses' => Enums\AttendanceStatus::options(),
            'attendance_sources' => Enums\AttendanceSource::options(),
            'device_vendors' => Enums\DeviceVendor::options(),
            'device_types' => Enums\DeviceType::options(),
            'device_connection_modes' => Enums\DeviceConnectionMode::options(),
            'penalty_types' => Enums\PenaltyType::options(),
            'penalty_calculations' => Enums\PenaltyCalculation::options(),
            'penalty_statuses' => Enums\PenaltyStatus::options(),
            'payroll_period_statuses' => Enums\PayrollPeriodStatus::options(),
            'payroll_record_statuses' => Enums\PayrollRecordStatus::options(),
            'custom_field_types' => Enums\CustomFieldType::options(),
            'custom_field_entities' => Enums\CustomFieldEntity::options(),
            'record_statuses' => Enums\RecordStatus::options(),
        ]);
    }

    /** Active users of the tenant, for assignment pickers. */
    public function users(TenantContext $tenant): JsonResponse
    {
        return $this->ok(User::query()->inTenant($tenant->require()->id)
            ->where('status', Enums\UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_path'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'avatar_url' => $u->avatarUrl()]));
    }
}
