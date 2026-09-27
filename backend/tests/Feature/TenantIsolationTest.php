<?php

namespace Tests\Feature;

use App\Exceptions\TenantMismatchException;
use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Models\User;
use Tests\TestCase;

/** Tenant A must never see, change or reference Tenant B data — through any channel. */
class TenantIsolationTest extends TestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->createTenant('Tenant A');
        $this->b = $this->createTenant('Tenant B');
    }

    public function test_list_endpoints_only_return_own_tenant_records(): void
    {
        [$orgA, $ownerA] = $this->a;
        [$orgB] = $this->b;
        $this->inTenant($orgA, fn () => Customer::factory()->create(['company_name' => 'Customer A', 'type' => 'company']));
        $this->inTenant($orgB, fn () => Customer::factory()->create(['company_name' => 'Customer B', 'type' => 'company']));

        $response = $this->actingAsUser($ownerA)->getJson('/api/v1/customers?per_page=100')->assertOk();
        $names = collect($response->json('data'))->pluck('company_name');

        $this->assertContains('Customer A', $names);
        $this->assertNotContains('Customer B', $names);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_direct_id_access_to_other_tenant_returns_404(): void
    {
        [, $ownerA] = $this->a;
        [$orgB] = $this->b;
        $customerB = $this->inTenant($orgB, fn () => Customer::factory()->create());
        $leadB = $this->inTenant($orgB, fn () => Lead::factory()->create());
        $employeeB = $this->employee($orgB);

        $this->actingAsUser($ownerA);
        $this->getJson("/api/v1/customers/{$customerB->id}")->assertNotFound();
        $this->putJson("/api/v1/customers/{$customerB->id}", ['first_name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/customers/{$customerB->id}")->assertNotFound();
        $this->getJson("/api/v1/leads/{$leadB->id}")->assertNotFound();
        $this->getJson("/api/v1/employees/{$employeeB->id}")->assertNotFound();
        $this->getJson("/api/v1/customers/{$customerB->id}/timeline")->assertNotFound();
        $this->getJson("/api/v1/customers/{$customerB->id}/notes")->assertNotFound();

        $this->assertNotSame('Hacked', $customerB->fresh()->first_name);
        $this->assertNull(Customer::withoutGlobalScopes()->find($customerB->id)->deleted_at);
    }

    public function test_client_supplied_organization_id_is_ignored(): void
    {
        [$orgA, $ownerA] = $this->a;
        [$orgB] = $this->b;

        $id = $this->actingAsUser($ownerA)->postJson('/api/v1/customers', [
            'type' => 'individual', 'first_name' => 'Sneaky', 'organization_id' => $orgB->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($orgA->id, Customer::withoutGlobalScopes()->find($id)->organization_id);
    }

    public function test_foreign_keys_to_other_tenant_are_rejected(): void
    {
        [, $ownerA] = $this->a;
        [$orgB, $ownerB] = $this->b;
        $customerB = $this->inTenant($orgB, fn () => Customer::factory()->create());

        $this->actingAsUser($ownerA)->postJson('/api/v1/leads', [
            'name' => 'Lead', 'customer_id' => $customerB->id, 'assigned_to' => $ownerB->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'assigned_to']);

        $this->actingAsUser($ownerA)->postJson('/api/v1/deals', ['title' => 'Deal', 'customer_id' => $customerB->id])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }

    public function test_users_endpoint_is_tenant_scoped(): void
    {
        [, $ownerA] = $this->a;
        [, $ownerB] = $this->b;

        $emails = collect($this->actingAsUser($ownerA)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('email');
        $this->assertContains($ownerA->email, $emails);
        $this->assertNotContains($ownerB->email, $emails);

        $this->getJson("/api/v1/users/{$ownerB->id}")->assertNotFound();
        $this->putJson("/api/v1/users/{$ownerB->id}", ['name' => 'X'])->assertNotFound();
    }

    public function test_search_and_dashboard_do_not_leak(): void
    {
        [, $ownerA] = $this->a;
        [$orgB] = $this->b;
        $this->inTenant($orgB, fn () => Customer::factory()->create(['first_name' => 'Zebedee', 'last_name' => 'Uniquename']));

        $this->actingAsUser($ownerA)->getJson('/api/v1/search?q=Uniquename')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.crm.cards.customers', 0);
    }

    public function test_exports_only_contain_own_tenant(): void
    {
        [$orgA, $ownerA] = $this->a;
        [$orgB] = $this->b;
        $this->inTenant($orgA, fn () => Customer::factory()->create(['type' => 'individual', 'first_name' => 'Alphaname']));
        $this->inTenant($orgB, fn () => Customer::factory()->create(['type' => 'individual', 'first_name' => 'Betaname']));

        $csv = $this->actingAsUser($ownerA)->get('/api/v1/customers?export=csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('Alphaname', $csv);
        $this->assertStringNotContainsString('Betaname', $csv);
    }

    public function test_model_layer_rejects_cross_tenant_writes(): void
    {
        [$orgA] = $this->a;
        [$orgB] = $this->b;

        // organization_id is not mass assignable at all...
        $this->assertNotContains('organization_id', (new Customer)->getFillable());

        // ...and even when set explicitly, the model refuses to write into another tenant.
        $this->expectException(TenantMismatchException::class);
        $this->inTenant($orgA, function () use ($orgB): void {
            $customer = new Customer(['type' => 'individual', 'first_name' => 'X']);
            $customer->organization_id = $orgB->id;
            $customer->save();
        });
    }

    public function test_organization_id_is_immutable(): void
    {
        [$orgA] = $this->a;
        [$orgB] = $this->b;
        $customer = $this->inTenant($orgA, fn () => Customer::factory()->create());

        $this->expectException(TenantMismatchException::class);
        $customer->organization_id = $orgB->id;
        $customer->save();
    }

    public function test_tenant_host_must_match_user_organization(): void
    {
        [$orgA, $ownerA] = $this->a;
        [$orgB] = $this->b;
        config(['tenancy.base_domain' => 'crm.eversoft.uz']);

        $this->actingAsUser($ownerA)->getJson("http://{$orgA->slug}.crm.eversoft.uz/api/v1/customers")->assertOk();
        $this->actingAsUser($ownerA)->getJson("http://{$orgB->slug}.crm.eversoft.uz/api/v1/customers")->assertForbidden();
    }

    public function test_platform_admin_is_separated_from_tenant_endpoints(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['platform_role' => 'platform_super_admin'])->save();
        [, $ownerA] = $this->a;

        $this->actingAsUser($admin)->getJson('/api/v1/customers')->assertForbidden();
        $this->actingAsUser($admin)->getJson('/api/v1/platform/organizations')->assertOk()->assertJsonPath('meta.total', 2);

        $this->actingAsUser($ownerA)->getJson('/api/v1/platform/organizations')->assertForbidden();
    }

    public function test_deal_board_and_employee_list_are_scoped(): void
    {
        [, $ownerA] = $this->a;
        [$orgB] = $this->b;
        $this->employee($orgB, ['first_name' => 'OtherTenantPerson']);
        $this->inTenant($orgB, function () {
            $pipeline = Pipeline::query()->with('stages')->first();
            Deal::factory()->create(['title' => 'B deal', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $pipeline->stages->first()->id]);
        });

        $names = collect($this->actingAsUser($ownerA)->getJson('/api/v1/employees')->json('data'))->pluck('first_name');
        $this->assertNotContains('OtherTenantPerson', $names);

        $board = $this->getJson('/api/v1/deals-board')->assertOk()->json('data.columns');
        $this->assertSame(0, collect($board)->sum('count'));
    }
}
