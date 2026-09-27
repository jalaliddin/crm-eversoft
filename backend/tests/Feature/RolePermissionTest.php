<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Crm\Lead;
use App\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    public function test_every_organization_gets_the_system_roles(): void
    {
        [$organization] = $this->createTenant();

        $slugs = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('slug')->all();
        foreach (['owner', 'admin', 'hr_manager', 'manager', 'employee', 'sales_manager', 'sales_agent'] as $slug) {
            $this->assertContains($slug, $slugs);
        }
    }

    public function test_missing_permission_returns_403(): void
    {
        [$organization] = $this->createTenant();
        $employee = $this->userWithRole($organization, 'employee');

        $this->actingAsUser($employee)->getJson('/api/v1/customers')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/api/v1/payroll')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/attendance-devices', ['name' => 'X', 'vendor' => 'fake'])->assertForbidden();
    }

    public function test_granular_permissions_are_enforced_per_action(): void
    {
        [$organization] = $this->createTenant();
        $viewer = $this->userWithPermissions($organization, ['customers.view']);

        $this->actingAsUser($viewer)->getJson('/api/v1/customers')->assertOk();
        $this->postJson('/api/v1/customers', ['type' => 'individual', 'first_name' => 'A'])->assertForbidden();
    }

    public function test_hr_manager_cannot_access_crm_and_sales_cannot_access_payroll(): void
    {
        [$organization] = $this->createTenant();
        $hr = $this->userWithRole($organization, 'hr_manager');
        $sales = $this->userWithRole($organization, 'sales_manager');

        $this->actingAsUser($hr)->getJson('/api/v1/customers')->assertForbidden();
        $this->actingAsUser($hr)->getJson('/api/v1/employees')->assertOk();
        $this->actingAsUser($sales)->getJson('/api/v1/payroll')->assertForbidden();
        $this->actingAsUser($sales)->getJson('/api/v1/customers')->assertOk();
    }

    public function test_sales_agent_only_sees_own_leads(): void
    {
        [$organization, $owner] = $this->createTenant();
        $agent = $this->userWithRole($organization, 'sales_agent');
        $other = $this->userWithRole($organization, 'sales_agent');

        $mine = $this->inTenant($organization, fn () => Lead::factory()->create(['assigned_to' => $agent->id]));
        $theirs = $this->inTenant($organization, fn () => Lead::factory()->create(['assigned_to' => $other->id]));

        $ids = collect($this->actingAsUser($agent)->getJson('/api/v1/leads')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->getJson("/api/v1/leads/{$theirs->id}")->assertForbidden();

        // Managers with crm.view_all see both.
        $this->assertSame(2, $this->actingAsUser($owner)->getJson('/api/v1/leads')->json('meta.total'));
    }

    public function test_roles_can_be_created_and_permission_changes_are_audited(): void
    {
        [$organization, $owner] = $this->createTenant();

        $roleId = $this->actingAsUser($owner)->postJson('/api/v1/roles', [
            'name' => 'Auditor', 'permissions' => ['audit.view', 'customers.view'],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/roles/{$roleId}", ['name' => 'Auditor', 'permissions' => ['audit.view']])->assertOk()
            ->assertJsonPath('data.permissions', ['audit.view']);

        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('event', 'role.permissions_changed')->where('auditable_id', $roleId)->exists());

        $this->postJson('/api/v1/roles', ['name' => 'Bad', 'permissions' => ['does.not.exist']])->assertStatus(422);
    }

    public function test_owner_role_cannot_be_edited_and_last_owner_cannot_be_demoted(): void
    {
        [$organization, $owner] = $this->createTenant();
        $ownerRole = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->where('slug', 'owner')->first();
        $employeeRole = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->where('slug', 'employee')->first();

        $this->actingAsUser($owner)->putJson("/api/v1/roles/{$ownerRole->id}", ['name' => 'X', 'permissions' => []])->assertStatus(422);

        // Admins cannot touch owner accounts at all.
        $admin = $this->userWithRole($organization, 'admin');
        $this->actingAsUser($admin)->putJson("/api/v1/users/{$owner->id}", ['role_ids' => [$employeeRole->id]])->assertForbidden();

        // The sole owner cannot demote themselves either: an organization always keeps an owner.
        $this->actingAsUser($owner)->putJson("/api/v1/users/{$owner->id}", ['role_ids' => [$employeeRole->id]])->assertStatus(422);
    }

    public function test_users_cannot_be_given_roles_from_another_tenant(): void
    {
        [$orgA, $ownerA] = $this->createTenant('A');
        [$orgB] = $this->createTenant('B');
        $foreignRole = Role::withoutGlobalScopes()->where('organization_id', $orgB->id)->where('slug', 'admin')->first();

        $this->actingAsUser($ownerA)->postJson('/api/v1/users', [
            'name' => 'New', 'email' => 'new@example.test', 'password' => 'password123', 'role_ids' => [$foreignRole->id],
        ])->assertStatus(422);
    }

    public function test_salary_is_hidden_without_payroll_permission(): void
    {
        [$organization, $owner] = $this->createTenant();
        $employee = $this->employee($organization, ['salary_amount' => 7000000]);
        $manager = $this->userWithRole($organization, 'manager');

        $this->actingAsUser($owner)->getJson("/api/v1/employees/{$employee->id}")->assertJsonPath('data.salary_amount', 7000000);
        $this->actingAsUser($manager)->getJson("/api/v1/employees/{$employee->id}")->assertJsonPath('data.salary_amount', null);
    }
}
