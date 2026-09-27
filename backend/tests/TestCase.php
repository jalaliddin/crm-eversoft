<?php

namespace Tests;

use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Organization\OrganizationProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wipes the schema: refuse to run against anything but a *_test database.
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($database, '_test')) {
            $this->fail("Refusing to run tests against database [{$database}]. Tests must use a *_test database.");
        }
    }

    /** @return array{0: Organization, 1: User} organization and its owner */
    protected function createTenant(string $name = 'Acme', array $attributes = []): array
    {
        $result = app(OrganizationProvisioner::class)->provision(
            ['name' => $name, ...$attributes],
            ['name' => $name.' Owner', 'email' => strtolower(preg_replace('/\W+/', '', $name)).'-owner-'.uniqid().'@example.test', 'password' => 'password'],
        );

        return [$result['organization'], $result['owner']];
    }

    protected function userWithRole(Organization $organization, string $roleSlug, array $attributes = []): User
    {
        $user = User::factory()->make($attributes);
        $user->organization_id = $organization->id;
        $user->save();

        $role = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);

        return $user;
    }

    /** @param list<string> $permissions */
    protected function userWithPermissions(Organization $organization, array $permissions): User
    {
        $role = $this->inTenant($organization, fn () => tap(Role::query()->create([
            'slug' => 'custom_'.uniqid(),
            'name' => 'Custom',
        ]))->syncPermissions($permissions));

        $user = User::factory()->make();
        $user->organization_id = $organization->id;
        $user->save();
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inTenant(Organization $organization, callable $callback): mixed
    {
        return app(TenantContext::class)->run($organization, fn () => $callback());
    }

    protected function employee(Organization $organization, array $attributes = []): Employee
    {
        return $this->inTenant($organization, fn () => Employee::factory()->create($attributes));
    }
}
