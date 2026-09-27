<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\WorkSchedule;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    public function test_create_employee_with_schedule_and_identifier(): void
    {
        [$organization, $owner] = $this->createTenant();
        $schedule = $this->inTenant($organization, fn () => WorkSchedule::query()->first());
        $this->actingAsUser($owner);

        $departmentId = $this->postJson('/api/v1/departments', ['name' => 'Sales', 'code' => 'SAL'])->assertCreated()->json('data.id');
        $positionId = $this->postJson('/api/v1/positions', ['name' => 'Agent', 'department_id' => $departmentId])->assertCreated()->json('data.id');

        $employee = $this->postJson('/api/v1/employees', [
            'first_name' => 'Madina', 'last_name' => 'Yusupova', 'department_id' => $departmentId, 'position_id' => $positionId,
            'hire_date' => '2026-01-10', 'salary_type' => 'monthly', 'salary_amount' => 8000000,
            'work_schedule_id' => $schedule->id, 'device_identifier' => '1001',
        ])->assertCreated()
            ->assertJsonPath('data.employee_number', '0001')
            ->assertJsonPath('data.department.name', 'Sales')
            ->assertJsonPath('data.work_schedule.id', $schedule->id)
            ->assertJsonPath('data.device_identifiers.0.identifier', '1001')
            ->json('data');

        $this->putJson("/api/v1/employees/{$employee['id']}", ['status' => 'terminated'])->assertOk()->assertJsonPath('data.status', 'terminated');
        $this->assertNotNull($this->getJson("/api/v1/employees/{$employee['id']}")->json('data.termination_date'));
    }

    public function test_employee_number_unique_per_tenant_only(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $this->employee($orgB, ['employee_number' => 'X-1']);

        $this->actingAsUser($ownerA)->postJson('/api/v1/employees', ['first_name' => 'A', 'last_name' => 'B', 'employee_number' => 'X-1'])->assertCreated();
        $this->postJson('/api/v1/employees', ['first_name' => 'C', 'last_name' => 'D', 'employee_number' => 'X-1'])->assertStatus(422);
    }

    public function test_device_identifier_cannot_be_reused_by_two_employees(): void
    {
        [$organization, $owner] = $this->createTenant();
        $first = $this->employee($organization);
        $second = $this->employee($organization);
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/employees/{$first->id}/identifiers", ['identifier' => '77'])->assertCreated();
        $this->postJson("/api/v1/employees/{$second->id}/identifiers", ['identifier' => '77'])->assertStatus(422);
    }

    public function test_department_cycles_are_rejected(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);
        $parent = $this->postJson('/api/v1/departments', ['name' => 'Parent'])->json('data.id');
        $child = $this->postJson('/api/v1/departments', ['name' => 'Child', 'parent_id' => $parent])->json('data.id');

        $this->putJson("/api/v1/departments/{$parent}", ['name' => 'Parent', 'parent_id' => $child])->assertStatus(422);
    }

    public function test_work_schedule_crud(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);
        $days = collect(range(1, 7))->map(fn ($d) => [
            'day_of_week' => $d, 'is_working_day' => $d <= 6,
            'start_time' => $d <= 6 ? '22:00' : null, 'end_time' => $d <= 6 ? '06:00' : null,
        ])->all();

        $this->postJson('/api/v1/work-schedules', ['name' => 'Night shift', 'type' => 'shift', 'grace_minutes' => 5, 'days' => $days])
            ->assertCreated()->assertJsonPath('data.days.0.start_time', '22:00');

        $this->postJson('/api/v1/work-schedules', ['name' => 'Broken', 'type' => 'fixed', 'days' => [['day_of_week' => 1, 'is_working_day' => true]]])
            ->assertStatus(422);
    }
}
