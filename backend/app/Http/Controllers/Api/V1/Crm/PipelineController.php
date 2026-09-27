<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Enums\StageType;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PipelineResource;
use App\Models\Crm\Deal;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Tenant-specific pipelines. Stages are replaced as a list; stages holding deals cannot be removed. */
class PipelineController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(PipelineResource::collection(Pipeline::query()->with('stages')->withCount('deals')->orderByDesc('is_default')->orderBy('sort_order')->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $pipeline = DB::transaction(fn () => $this->save(new Pipeline, $this->validated($request)));

        return $this->created(PipelineResource::make($pipeline->load('stages')));
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $pipeline = DB::transaction(fn () => $this->save($pipeline, $this->validated($request)));

        return $this->ok(PipelineResource::make($pipeline->load('stages')), 'Pipeline saved.');
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        if ($pipeline->deals()->withTrashed()->exists()) {
            throw new BusinessRuleException('This pipeline has deals. Move them to another pipeline first.');
        }
        if (Pipeline::query()->count() === 1) {
            throw new BusinessRuleException('At least one pipeline is required.');
        }

        $pipeline->delete();

        return $this->ok(null, 'Pipeline deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'stages' => ['required', 'array', 'min:2', 'max:30'],
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.name' => ['required', 'string', 'max:100'],
            'stages.*.color' => ['nullable', 'string', 'max:20'],
            'stages.*.probability' => ['required', 'integer', 'between:0,100'],
            'stages.*.type' => ['required', Rule::enum(StageType::class)],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function save(Pipeline $pipeline, array $data): Pipeline
    {
        $types = array_column($data['stages'], 'type');
        if (! in_array('open', $types, true) || ! in_array('won', $types, true) || ! in_array('lost', $types, true)) {
            throw new BusinessRuleException('A pipeline needs at least one open, one won and one lost stage.', ['stages' => ['Missing open/won/lost stage.']]);
        }

        $pipeline->fill(['name' => $data['name'], 'is_default' => (bool) ($data['is_default'] ?? $pipeline->is_default)])->save();
        if ($pipeline->is_default) {
            Pipeline::query()->whereKeyNot($pipeline->id)->update(['is_default' => false]);
        }

        $keep = [];
        foreach ($data['stages'] as $index => $stage) {
            $model = ! empty($stage['id']) ? PipelineStage::query()->where('pipeline_id', $pipeline->id)->find($stage['id']) : null;
            $model ??= new PipelineStage(['pipeline_id' => $pipeline->id, 'key' => $this->uniqueKey($pipeline, $stage['name'])]);
            $model->fill([
                'name' => $stage['name'],
                'color' => $stage['color'] ?? 'blue',
                'probability' => $stage['probability'],
                'type' => $stage['type'],
                'sort_order' => $index,
            ])->save();
            $keep[] = $model->id;
        }

        $removed = PipelineStage::query()->where('pipeline_id', $pipeline->id)->whereNotIn('id', $keep);
        if (Deal::withTrashed()->whereIn('pipeline_stage_id', (clone $removed)->pluck('id'))->exists()) {
            throw new BusinessRuleException('A removed stage still has deals. Move them first.', ['stages' => ['Stage in use.']]);
        }
        $removed->delete();

        return $pipeline;
    }

    private function uniqueKey(Pipeline $pipeline, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'stage';
        $key = $base;
        $i = 1;
        while (PipelineStage::query()->where('pipeline_id', $pipeline->id)->where('key', $key)->exists()) {
            $key = $base.'_'.++$i;
        }

        return $key;
    }
}
