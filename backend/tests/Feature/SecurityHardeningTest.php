<?php

namespace Tests\Feature;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Role;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_device_addresses_cannot_target_the_server_or_metadata(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);

        foreach (['127.0.0.1', 'localhost', '169.254.169.254', 'mysql', 'redis', '0.0.0.0'] as $host) {
            $this->postJson('/api/v1/attendance-devices', ['name' => 'X', 'vendor' => 'hikvision', 'ip_address' => $host])
                ->assertStatus(422)->assertJsonValidationErrors(['ip_address']);
        }

        $this->postJson('/api/v1/attendance-devices', ['name' => 'LAN terminal', 'vendor' => 'hikvision', 'ip_address' => '192.168.1.64'])->assertCreated();
    }

    public function test_private_networks_can_be_disabled(): void
    {
        config(['attendance.allow_private_networks' => false]);
        [, $owner] = $this->createTenant();

        $this->actingAsUser($owner)->postJson('/api/v1/attendance-devices', ['name' => 'X', 'vendor' => 'hikvision', 'ip_address' => '10.0.0.5'])
            ->assertStatus(422);
    }

    public function test_guard_is_enforced_at_request_time_too(): void
    {
        Http::fake();
        [$organization] = $this->createTenant();
        // Simulate a record that bypassed validation (e.g. edited in the database).
        $device = $this->inTenant($organization, fn () => AttendanceDevice::query()->create([
            'name' => 'Bad', 'vendor' => 'hikvision', 'ip_address' => '169.254.169.254', 'status' => 'active', 'connection_mode' => 'direct',
        ]));

        $result = app(AttendanceSyncService::class)->sync($device);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('rejected', $result['message']);
        Http::assertNothingSent();
    }

    public function test_admin_cannot_take_over_an_owner_account(): void
    {
        [$organization, $owner] = $this->createTenant();
        $admin = $this->userWithRole($organization, 'admin');
        $ownerRole = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->where('slug', 'owner')->first();

        $this->actingAsUser($admin);
        $this->putJson("/api/v1/users/{$owner->id}", ['password' => 'hijacked-password', 'email' => 'evil@example.test'])->assertForbidden();
        $this->deleteJson("/api/v1/users/{$owner->id}")->assertForbidden();
        $this->putJson("/api/v1/users/{$admin->id}", ['role_ids' => [$ownerRole->id]])->assertForbidden();
        $this->postJson('/api/v1/users', ['name' => 'N', 'email' => 'n@example.test', 'password' => 'password123', 'role_ids' => [$ownerRole->id]])->assertForbidden();

        // Owners can still manage everything, including other owners.
        $this->actingAsUser($owner)->postJson('/api/v1/users', ['name' => 'Co-owner', 'email' => 'co@example.test', 'password' => 'password123', 'role_ids' => [$ownerRole->id]])->assertCreated();
    }
}
