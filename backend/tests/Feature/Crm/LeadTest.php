<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Lead;
use App\Models\Crm\TimelineEvent;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadTest extends TestCase
{
    public function test_create_update_and_status_change_is_on_timeline(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);

        $id = $this->postJson('/api/v1/leads', ['name' => 'Jasur', 'expected_value' => 15000000, 'source' => 'website'])
            ->assertCreated()->assertJsonPath('data.status', 'new')->json('data.id');

        $this->putJson("/api/v1/leads/{$id}", ['status' => 'qualified'])->assertOk()->assertJsonPath('data.status', 'qualified');

        $event = TimelineEvent::withoutGlobalScopes()->where('lead_id', $id)->where('event', 'lead.status_changed')->first();
        $this->assertEquals(['from' => 'new', 'to' => 'qualified'], $event->properties);
    }

    public function test_kanban_board_and_move(): void
    {
        [$organization, $owner] = $this->createTenant();
        $lead = $this->inTenant($organization, fn () => Lead::factory()->create(['status' => 'new']));
        $this->actingAsUser($owner);

        $board = $this->getJson('/api/v1/leads-board')->assertOk()->json('data');
        $this->assertSame(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'converted', 'lost'], array_column($board, 'status'));

        $this->postJson("/api/v1/leads/{$lead->id}/move", ['status' => 'proposal', 'position' => 0])->assertOk()->assertJsonPath('data.status', 'proposal');
        $this->postJson("/api/v1/leads/{$lead->id}/move", ['status' => 'converted'])->assertStatus(422);
    }

    public function test_conversion_creates_customer_and_deal(): void
    {
        [$organization, $owner] = $this->createTenant();
        $lead = $this->inTenant($organization, fn () => Lead::factory()->create(['name' => 'Olim Karimov', 'company_name' => 'Karimov & Co', 'expected_value' => 9000000, 'status' => 'negotiation']));
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/leads/{$lead->id}/convert", ['create_deal' => true])->assertOk();

        $response->assertJsonPath('data.lead.status', 'converted')
            ->assertJsonPath('data.customer.company_name', 'Karimov & Co')
            ->assertJsonPath('data.deal.value', 9000000)
            ->assertJsonPath('data.deal.lead_id', $lead->id);

        $this->postJson("/api/v1/leads/{$lead->id}/convert", [])->assertStatus(422);
    }

    public function test_assignment_notifies_assignee_and_requires_permission(): void
    {
        Notification::fake();
        [$organization, $owner] = $this->createTenant();
        $agent = $this->userWithRole($organization, 'sales_agent');
        $colleague = $this->userWithRole($organization, 'sales_agent');

        $this->actingAsUser($owner)->postJson('/api/v1/leads', ['name' => 'Lead', 'assigned_to' => $agent->id])->assertCreated();
        Notification::assertSentTo($agent, LeadAssignedNotification::class);

        // Agents (no leads.assign) cannot assign to someone else.
        $this->actingAsUser($agent)->postJson('/api/v1/leads', ['name' => 'Lead 2', 'assigned_to' => $colleague->id])->assertForbidden();
        $this->actingAsUser($agent)->postJson('/api/v1/leads', ['name' => 'Lead 3', 'assigned_to' => $agent->id])->assertCreated();
    }
}
