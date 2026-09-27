<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Customer;
use App\Models\Crm\TimelineEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_crud_lifecycle_with_timeline(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->actingAsUser($owner);

        $id = $this->postJson('/api/v1/customers', [
            'type' => 'company', 'company_name' => 'Silk Road LLC', 'email' => 'info@silk.test', 'phone' => '+998901234567', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.display_name', 'Silk Road LLC')->json('data.id');

        $this->getJson("/api/v1/customers/{$id}")->assertOk()->assertJsonPath('data.email', 'info@silk.test');
        $this->putJson("/api/v1/customers/{$id}", ['city' => 'Samarkand'])->assertOk()->assertJsonPath('data.city', 'Samarkand');

        $events = TimelineEvent::withoutGlobalScopes()->where('customer_id', $id)->pluck('event')->all();
        $this->assertContains('customer.created', $events);
        $this->assertContains('customer.updated', $events);
        $this->getJson("/api/v1/customers/{$id}/timeline")->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/customers/{$id}")->assertOk();
        $this->getJson("/api/v1/customers/{$id}")->assertNotFound();
        $this->assertSoftDeleted('customers', ['id' => $id]);
    }

    public function test_validation_errors(): void
    {
        [, $owner] = $this->createTenant();

        $this->actingAsUser($owner)->postJson('/api/v1/customers', ['type' => 'company', 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_name', 'email']);
    }

    public function test_search_filter_sort_and_pagination(): void
    {
        [$organization, $owner] = $this->createTenant();
        $this->inTenant($organization, function () {
            Customer::factory()->count(30)->create(['status' => 'active']);
            Customer::factory()->create(['first_name' => 'Findme', 'status' => 'blocked']);
        });
        $this->actingAsUser($owner);

        $this->getJson('/api/v1/customers?per_page=10')->assertOk()->assertJsonPath('meta.total', 31)->assertJsonPath('meta.last_page', 4)->assertJsonCount(10, 'data');
        $this->getJson('/api/v1/customers?search=Findme')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/customers?status=blocked')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/customers?sort=-created_at')->assertOk();
    }

    public function test_tags_notes_contacts_and_documents(): void
    {
        Storage::fake('local');
        [$organization, $owner] = $this->createTenant();
        $customer = $this->inTenant($organization, fn () => Customer::factory()->create());
        $this->actingAsUser($owner);

        $tagId = $this->postJson('/api/v1/tags', ['name' => 'VIP', 'color' => 'yellow'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/customers/{$customer->id}", ['tag_ids' => [$tagId]])->assertOk()->assertJsonPath('data.tags.0.name', 'VIP');

        $this->postJson("/api/v1/customers/{$customer->id}/notes", ['body' => 'Called, interested.'])->assertCreated();
        $this->getJson("/api/v1/customers/{$customer->id}/notes")->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/customers/{$customer->id}/contacts", ['first_name' => 'Dilnoza', 'is_primary' => true])->assertCreated();

        $documentId = $this->post("/api/v1/customers/{$customer->id}/documents", [
            'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->get("/api/v1/documents/{$documentId}/download")->assertOk();
        $this->assertTrue(TimelineEvent::withoutGlobalScopes()->where('customer_id', $customer->id)->where('event', 'document.uploaded')->exists());

        // Executables are rejected.
        $this->post("/api/v1/customers/{$customer->id}/documents", [
            'file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_custom_fields_are_validated_against_definitions(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/custom-fields', [
            'entity_type' => 'customer', 'key' => 'industry', 'label' => 'Industry', 'field_type' => 'select',
            'options' => [['value' => 'retail', 'label' => 'Retail'], ['value' => 'textile', 'label' => 'Textile']],
        ])->assertCreated();

        $this->postJson('/api/v1/customers', ['type' => 'individual', 'first_name' => 'A', 'custom_fields' => ['industry' => 'space']])->assertStatus(422);

        $this->postJson('/api/v1/customers', ['type' => 'individual', 'first_name' => 'A', 'custom_fields' => ['industry' => 'textile', 'unknown' => 'x']])
            ->assertCreated()
            ->assertJsonPath('data.custom_fields.industry', 'textile')
            ->assertJsonMissingPath('data.custom_fields.unknown');
    }
}
