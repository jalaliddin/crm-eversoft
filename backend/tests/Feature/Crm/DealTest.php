<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Pipeline;
use App\Models\Crm\TimelineEvent;
use Tests\TestCase;

class DealTest extends TestCase
{
    public function test_create_in_default_pipeline_and_move_through_stages(): void
    {
        [$organization, $owner] = $this->createTenant();
        $pipeline = $this->inTenant($organization, fn () => Pipeline::query()->with('stages')->first());
        $won = $pipeline->stages->firstWhere('type.value', 'won');
        $this->actingAsUser($owner);

        $deal = $this->postJson('/api/v1/deals', ['title' => 'Uniforms', 'value' => 45000000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.currency', 'UZS')
            ->assertJsonPath('data.stage.key', 'new')
            ->json('data');

        $this->postJson("/api/v1/deals/{$deal['id']}/move", ['pipeline_stage_id' => $won->id])
            ->assertOk()->assertJsonPath('data.status', 'won')->assertJsonPath('data.probability', 100);

        $this->assertNotNull($this->getJson("/api/v1/deals/{$deal['id']}")->json('data.won_at'));
        $this->assertTrue(TimelineEvent::withoutGlobalScopes()->where('deal_id', $deal['id'])->where('event', 'deal.stage_changed')->exists());
    }

    public function test_board_groups_by_stage_and_reports_totals(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);
        $this->postJson('/api/v1/deals', ['title' => 'A', 'value' => 100])->assertCreated();
        $this->postJson('/api/v1/deals', ['title' => 'B', 'value' => 250])->assertCreated();

        $columns = $this->getJson('/api/v1/deals-board')->assertOk()->json('data.columns');
        $this->assertCount(6, $columns);
        $this->assertSame(2, $columns[0]['count']);
        $this->assertEquals(350, $columns[0]['total_value']);
    }

    public function test_tenant_specific_pipeline_stages(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);

        $pipeline = $this->postJson('/api/v1/pipelines', [
            'name' => 'Wholesale',
            'stages' => [
                ['name' => 'Inquiry', 'probability' => 10, 'type' => 'open'],
                ['name' => 'Sample sent', 'probability' => 40, 'type' => 'open'],
                ['name' => 'Ordered', 'probability' => 100, 'type' => 'won'],
                ['name' => 'Declined', 'probability' => 0, 'type' => 'lost'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(['Inquiry', 'Sample sent', 'Ordered', 'Declined'], array_column($pipeline['stages'], 'name'));

        $this->postJson('/api/v1/deals', ['title' => 'Bulk', 'pipeline_id' => $pipeline['id']])->assertCreated()->assertJsonPath('data.stage.name', 'Inquiry');

        // A stage from another pipeline is rejected.
        $defaultStage = $this->getJson('/api/v1/pipelines')->json('data.0.stages.0.id');
        $this->postJson('/api/v1/deals', ['title' => 'Bad', 'pipeline_id' => $pipeline['id'], 'pipeline_stage_id' => $defaultStage])->assertStatus(422);

        // Pipelines need open, won and lost stages.
        $this->postJson('/api/v1/pipelines', ['name' => 'Bad', 'stages' => [['name' => 'x', 'probability' => 1, 'type' => 'open'], ['name' => 'y', 'probability' => 1, 'type' => 'open']]])->assertStatus(422);
    }

    public function test_activities_on_a_deal_appear_on_customer_timeline(): void
    {
        [, $owner] = $this->createTenant();
        $this->actingAsUser($owner);
        $customerId = $this->postJson('/api/v1/customers', ['type' => 'individual', 'first_name' => 'Nodir'])->json('data.id');
        $dealId = $this->postJson('/api/v1/deals', ['title' => 'Deal', 'customer_id' => $customerId])->json('data.id');

        $activityId = $this->postJson('/api/v1/activities', ['title' => 'Call Nodir', 'type' => 'call', 'deal_id' => $dealId])
            ->assertCreated()->assertJsonPath('data.customer_id', $customerId)->json('data.id');
        $this->postJson("/api/v1/activities/{$activityId}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        $events = collect($this->getJson("/api/v1/customers/{$customerId}/timeline")->json('data'))->pluck('event');
        $this->assertContains('activity.created', $events);
        $this->assertContains('activity.completed', $events);
        $this->assertContains('deal.created', $events);
    }
}
