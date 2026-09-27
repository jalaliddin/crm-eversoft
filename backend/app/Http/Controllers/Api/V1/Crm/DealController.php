<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DealRequest;
use App\Http\Resources\DealResource;
use App\Http\Resources\PipelineResource;
use App\Http\Resources\TimelineEventResource;
use App\Models\Crm\Deal;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\Crm\TimelineEvent;
use App\Services\Crm\DealService;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function __construct(private readonly DealService $deals) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);
        [$column, $direction] = $this->sort($request, ['created_at', 'updated_at', 'title', 'value', 'expected_close_date', 'status']);

        return $this->paginated(
            $this->filtered($request)->orderBy($column, $direction)->orderBy('id', 'desc')->paginate($this->perPage($request)),
            DealResource::class,
        );
    }

    /** Kanban board for one pipeline: columns are that pipeline's stages. */
    public function board(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);
        $pipeline = $request->query('pipeline_id')
            ? Pipeline::query()->with('stages')->findOrFail($request->query('pipeline_id'))
            : $this->deals->defaultPipeline()->load('stages');

        $deals = $this->filtered($request)->where('pipeline_id', $pipeline->id)->orderBy('position')->orderByDesc('id')->limit(2000)->get();

        return $this->ok([
            'pipeline' => PipelineResource::make($pipeline)->resolve(),
            'columns' => $pipeline->stages->map(fn (PipelineStage $stage) => [
                'stage' => ['id' => $stage->id, 'name' => $stage->name, 'key' => $stage->key, 'color' => $stage->color, 'type' => $stage->type->value, 'probability' => $stage->probability],
                'deals' => DealResource::collection($deals->where('pipeline_stage_id', $stage->id)->values())->resolve(),
                'count' => $deals->where('pipeline_stage_id', $stage->id)->count(),
                'total_value' => (float) $deals->where('pipeline_stage_id', $stage->id)->sum('value'),
            ])->values(),
        ]);
    }

    public function store(DealRequest $request): JsonResponse
    {
        $this->authorize('create', Deal::class);
        $deal = $this->deals->create($request->validated(), $request->user());

        return $this->created(DealResource::make($deal->load(['stage', 'customer', 'assignee:id,name', 'tags'])));
    }

    public function show(Deal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        return $this->ok(DealResource::make($deal->load(['stage', 'customer', 'assignee:id,name', 'tags'])));
    }

    public function update(DealRequest $request, Deal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        $deal = $this->deals->update($deal, $request->validated(), $request->user());

        return $this->ok(DealResource::make($deal->load(['stage', 'customer', 'assignee:id,name', 'tags'])), 'Deal updated.');
    }

    public function move(Request $request, Deal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', TenantRule::exists('pipeline_stages', softDeletes: false)],
            'position' => ['sometimes', 'integer', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = PipelineStage::query()->findOrFail($data['pipeline_stage_id']);
        $deal = $this->deals->moveToStage($deal, $stage, (int) ($data['position'] ?? 0), $request->user(), $data['lost_reason'] ?? null);

        return $this->ok(DealResource::make($deal->load(['stage', 'customer', 'assignee:id,name'])));
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $this->authorize('delete', $deal);
        $deal->delete();

        return $this->ok(null, 'Deal deleted.');
    }

    public function timeline(Request $request, Deal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        return $this->paginated(
            TimelineEvent::query()->with('causer:id,name')->where('deal_id', $deal->id)->orderByDesc('occurred_at')->orderByDesc('id')->paginate($this->perPage($request, 30)),
            TimelineEventResource::class,
        );
    }

    private function filtered(Request $request)
    {
        return Deal::query()
            ->visibleTo($request->user())
            ->with(['stage', 'customer', 'assignee:id,name', 'tags'])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', (array) $v))
            ->when($request->query('pipeline_stage_id'), fn ($q, $v) => $q->where('pipeline_stage_id', $v))
            ->when($request->query('assigned_to'), fn ($q, $v) => $v === 'me' ? $q->where('assigned_to', $request->user()->id) : $q->where('assigned_to', $v))
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->query('lead_id'), fn ($q, $v) => $q->where('lead_id', $v))
            ->when($request->query('min_value'), fn ($q, $v) => $q->where('value', '>=', $v))
            ->when($request->query('max_value'), fn ($q, $v) => $q->where('value', '<=', $v))
            ->when($request->query('close_from'), fn ($q, $v) => $q->where('expected_close_date', '>=', $v))
            ->when($request->query('close_to'), fn ($q, $v) => $q->where('expected_close_date', '<=', $v));
    }
}
