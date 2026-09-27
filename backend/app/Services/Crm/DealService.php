<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\CustomFieldEntity;
use App\Enums\DealStatus;
use App\Enums\StageType;
use App\Exceptions\BusinessRuleException;
use App\Models\Crm\Deal;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\User;
use App\Notifications\DealStageChangedNotification;
use App\Services\CustomFields\CustomFieldValidator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DealService
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly CustomFieldValidator $customFields,
        private readonly TenantContext $tenant,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Deal
    {
        return DB::transaction(function () use ($data, $user) {
            $pipeline = ! empty($data['pipeline_id'])
                ? Pipeline::query()->findOrFail($data['pipeline_id'])
                : $this->defaultPipeline();

            $stage = ! empty($data['pipeline_stage_id'])
                ? $this->stageOf($pipeline, (int) $data['pipeline_stage_id'])
                : $pipeline->stages()->where('type', StageType::Open->value)->firstOrFail();

            $deal = Deal::query()->create([
                ...Arr::except($data, ['tag_ids', 'custom_fields', 'pipeline_id', 'pipeline_stage_id']),
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'currency' => $data['currency'] ?? $this->tenant->require()->currency,
                'probability' => $data['probability'] ?? $stage->probability,
                'custom_fields' => $this->customFields->validate(CustomFieldEntity::Deal, $data['custom_fields'] ?? null),
                'created_by' => $user->id,
                ...$this->statusAttributes($stage),
            ]);

            if (isset($data['tag_ids'])) {
                $deal->syncTags($data['tag_ids']);
            }

            $this->timeline->record('deal.created', $deal, 'Deal created: '.$deal->title, ['stage' => $stage->name]);

            return $deal;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Deal $deal, array $data, User $user): Deal
    {
        return DB::transaction(function () use ($deal, $data, $user) {
            $attributes = Arr::except($data, ['tag_ids', 'custom_fields', 'pipeline_id', 'pipeline_stage_id']);
            if (array_key_exists('custom_fields', $data)) {
                $attributes['custom_fields'] = [
                    ...(array) $deal->custom_fields,
                    ...$this->customFields->validate(CustomFieldEntity::Deal, $data['custom_fields'], partial: true),
                ];
            }
            $deal->update($attributes);

            if (isset($data['tag_ids'])) {
                $deal->syncTags($data['tag_ids']);
            }

            $pipelineChanged = isset($data['pipeline_id']) && (int) $data['pipeline_id'] !== $deal->pipeline_id;
            if (isset($data['pipeline_stage_id']) && ((int) $data['pipeline_stage_id'] !== $deal->pipeline_stage_id || $pipelineChanged)) {
                $pipeline = $pipelineChanged ? Pipeline::query()->findOrFail($data['pipeline_id']) : $deal->pipeline;
                $this->moveToStage($deal, $this->stageOf($pipeline, (int) $data['pipeline_stage_id']), $deal->position, $user);
            }

            return $deal;
        });
    }

    public function moveToStage(Deal $deal, PipelineStage $stage, int $position, User $user, ?string $lostReason = null): Deal
    {
        return DB::transaction(function () use ($deal, $stage, $position, $user, $lostReason) {
            $from = $deal->stage;

            if ($stage->pipeline_id !== $deal->pipeline_id) {
                $deal->pipeline_id = $stage->pipeline_id;
            }

            $deal->fill([
                'pipeline_stage_id' => $stage->id,
                'position' => $position,
                'probability' => $stage->probability,
                'lost_reason' => $stage->type === StageType::Lost ? ($lostReason ?? $deal->lost_reason) : null,
                ...$this->statusAttributes($stage, $deal),
            ])->save();
            $deal->setRelation('stage', $stage);

            if ($from?->id !== $stage->id) {
                $this->timeline->record('deal.stage_changed', $deal, sprintf('Deal moved: %s → %s', $from?->name ?? '—', $stage->name), [
                    'from' => $from?->key,
                    'to' => $stage->key,
                ]);

                if ($deal->assigned_to !== null && $deal->assigned_to !== $user->id) {
                    User::query()->inTenant($deal->organization_id)->find($deal->assigned_to)?->notify(
                        new DealStageChangedNotification($deal->organization_id, [
                            'deal' => $deal->title,
                            'deal_id' => $deal->id,
                            'from' => $from?->name ?? '—',
                            'to' => $stage->name,
                            'link' => '/crm/deals/'.$deal->id,
                        ]),
                    );
                }
            }

            return $deal;
        });
    }

    public function defaultPipeline(): Pipeline
    {
        return Pipeline::query()->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first()
            ?? throw new BusinessRuleException('No sales pipeline configured. Create one in Settings → Pipelines.');
    }

    private function stageOf(Pipeline $pipeline, int $stageId): PipelineStage
    {
        $stage = PipelineStage::query()->where('pipeline_id', $pipeline->id)->find($stageId);

        return $stage ?? throw new BusinessRuleException('Stage does not belong to the selected pipeline.', ['pipeline_stage_id' => ['Invalid stage for pipeline.']]);
    }

    /** @return array<string, mixed> */
    private function statusAttributes(PipelineStage $stage, ?Deal $deal = null): array
    {
        return match ($stage->type) {
            StageType::Won => ['status' => DealStatus::Won, 'won_at' => $deal?->won_at ?? now(), 'lost_at' => null],
            StageType::Lost => ['status' => DealStatus::Lost, 'lost_at' => $deal?->lost_at ?? now(), 'won_at' => null],
            StageType::Open => ['status' => DealStatus::Open, 'won_at' => null, 'lost_at' => null],
        };
    }
}
