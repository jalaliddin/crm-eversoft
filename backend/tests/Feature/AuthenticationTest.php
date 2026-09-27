<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_can_login_and_receives_token_profile_and_permissions(): void
    {
        [$organization, $owner] = $this->createTenant('Alpha');

        $response = $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $owner->email)
            ->assertJsonPath('data.organization.id', $organization->id);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertContains('customers.view', $response->json('data.permissions'));
    }

    public function test_invalid_credentials_are_rejected_with_validation_envelope(): void
    {
        [, $owner] = $this->createTenant();

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_bearer_token_authenticates_me_and_logout_revokes_it(): void
    {
        [, $owner] = $this->createTenant();
        $token = $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.id', $owner->id);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $owner->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_inactive_user_and_suspended_organization_cannot_login(): void
    {
        [$organization, $owner] = $this->createTenant();
        $inactive = $this->userWithRole($organization, 'employee', ['status' => 'inactive']);

        $this->postJson('/api/v1/auth/login', ['email' => $inactive->email, 'password' => 'password'])->assertStatus(422);

        $organization->update(['status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertStatus(422);
    }

    public function test_suspended_organization_blocks_existing_tokens(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertOk();

        $organization->update(['status' => 'suspended']);
        $this->actingAsUser($owner->fresh())->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_change_password_requires_current_password(): void
    {
        [, $owner] = $this->createTenant();

        $this->actingAsUser($owner)->putJson('/api/v1/profile/password', [
            'current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertStatus(422);

        $this->actingAsUser($owner)->putJson('/api/v1/profile/password', [
            'current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-1', $owner->fresh()->password));
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Notification::fake();
        [, $owner] = $this->createTenant();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $owner->email])->assertOk();
        // Unknown addresses get the same answer (no enumeration).
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($owner, ResetPasswordNotification::class, function ($notification) use (&$token, $owner) {
            $url = $notification->toMail($owner)->actionUrl;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $token = $query['token'];

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => $owner->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'brand-new-pass'])->assertOk();
    }

    public function test_login_is_rate_limited(): void
    {
        [, $owner] = $this->createTenant();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'bad']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_login_is_audited_without_secrets(): void
    {
        [, $owner] = $this->createTenant();
        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertOk();

        $log = AuditLog::withoutGlobalScopes()->where('event', 'auth.login')->where('user_id', $owner->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('password', json_encode($log->new_values));
    }

    public function test_sessions_can_be_listed_and_revoked(): void
    {
        [, $owner] = $this->createTenant();
        $owner->createToken('other device');
        $token = $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/profile/sessions')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->deleteJson('/api/v1/profile/sessions')->assertOk();
        $this->assertSame(1, User::find($owner->id)->tokens()->count());
    }
}
