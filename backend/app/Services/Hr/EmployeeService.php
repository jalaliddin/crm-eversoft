<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\CustomFieldEntity;
use App\Enums\EmployeeStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkSchedule;
use App\Services\CustomFields\CustomFieldValidator;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(private readonly CustomFieldValidator $customFields) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $this->assertUserFree($data['user_id'] ?? null);

            $employee = Employee::query()->create([
                ...Arr::except($data, ['work_schedule_id', 'custom_fields', 'device_identifier']),
                'employee_number' => $data['employee_number'] ?? $this->nextEmployeeNumber(),
                'custom_fields' => $this->customFields->validate(CustomFieldEntity::Employee, $data['custom_fields'] ?? null),
            ]);

            if (! empty($data['work_schedule_id'])) {
                $this->assignSchedule($employee, (int) $data['work_schedule_id'], $data['hire_date'] ?? now()->format('Y-m-d'));
            }

            if (! empty($data['device_identifier'])) {
                $this->saveIdentifier($employee, ['identifier' => $data['device_identifier'], 'device_id' => null]);
            }

            return $employee;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            if (array_key_exists('user_id', $data) && $data['user_id'] !== $employee->user_id) {
                $this->assertUserFree($data['user_id'], $employee->id);
            }

            $attributes = Arr::except($data, ['work_schedule_id', 'custom_fields', 'device_identifier']);
            if (array_key_exists('custom_fields', $data)) {
                $attributes['custom_fields'] = [
                    ...(array) $employee->custom_fields,
                    ...$this->customFields->validate(CustomFieldEntity::Employee, $data['custom_fields'], partial: true),
                ];
            }
            if (($attributes['status'] ?? null) === EmployeeStatus::Terminated->value && empty($attributes['termination_date']) && ! $employee->termination_date) {
                $attributes['termination_date'] = now()->format('Y-m-d');
            }

            $employee->update($attributes);

            if (! empty($data['work_schedule_id'])) {
                $current = $employee->scheduleAssignments()->whereNull('effective_to')->first();
                if ($current?->work_schedule_id !== (int) $data['work_schedule_id']) {
                    $this->assignSchedule($employee, (int) $data['work_schedule_id'], now()->format('Y-m-d'));
                }
            }

            return $employee;
        });
    }

    /** Close the current assignment and start a new one from $from. */
    public function assignSchedule(Employee $employee, int $scheduleId, string $from, ?string $to = null): EmployeeWorkSchedule
    {
        return DB::transaction(function () use ($employee, $scheduleId, $from, $to) {
            // Assignments starting on/after the new start date are superseded.
            EmployeeWorkSchedule::query()->where('employee_id', $employee->id)->where('effective_from', '>=', $from)->delete();

            EmployeeWorkSchedule::query()
                ->where('employee_id', $employee->id)
                ->where('effective_from', '<', $from)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->format('Y-m-d')]);

            return EmployeeWorkSchedule::query()->create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $scheduleId,
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
        });
    }

    /** @param array{identifier: string, device_id?: ?int, card_number?: ?string, biometric_identifier?: ?string, status?: ?string} $data */
    public function saveIdentifier(Employee $employee, array $data, ?EmployeeDeviceIdentifier $existing = null): EmployeeDeviceIdentifier
    {
        $deviceId = $data['device_id'] ?? null;

        $conflict = EmployeeDeviceIdentifier::query()
            ->where('identifier', $data['identifier'])
            ->where(fn ($q) => $deviceId ? $q->where('device_id', $deviceId)->orWhereNull('device_id') : $q->whereRaw('1 = 1'))
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->where('employee_id', '!=', $employee->id)
            ->exists();

        if ($conflict) {
            throw new BusinessRuleException('This device identifier is already assigned to another employee.', ['identifier' => ['Identifier already in use.']]);
        }

        $identifier = $existing ?? new EmployeeDeviceIdentifier(['employee_id' => $employee->id]);
        $identifier->fill([
            'device_id' => $deviceId,
            'identifier' => $data['identifier'],
            'card_number' => $data['card_number'] ?? null,
            'biometric_identifier' => $data['biometric_identifier'] ?? null,
            'status' => $data['status'] ?? 'active',
        ])->save();

        return $identifier;
    }

    public function storePhoto(Employee $employee, UploadedFile $file): Employee
    {
        $old = $employee->photo_path;
        $path = $file->storeAs(
            sprintf('tenants/%d/employees', $employee->organization_id),
            Str::uuid()->toString().'.'.($file->guessExtension() ?: 'jpg'),
            ['disk' => 'public'],
        );
        $employee->update(['photo_path' => $path]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $employee;
    }

    public function nextEmployeeNumber(): string
    {
        $numbers = Employee::withTrashed()->pluck('employee_number')
            ->map(fn ($n) => (int) preg_replace('/\D/', '', (string) $n));

        return str_pad((string) (($numbers->max() ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function assertUserFree(?int $userId, ?int $exceptEmployeeId = null): void
    {
        if ($userId === null) {
            return;
        }

        $taken = Employee::query()->where('user_id', $userId)->when($exceptEmployeeId, fn ($q) => $q->whereKeyNot($exceptEmployeeId))->exists();
        if ($taken) {
            throw new BusinessRuleException('This user is already linked to another employee.', ['user_id' => ['User already linked.']]);
        }
    }
}
