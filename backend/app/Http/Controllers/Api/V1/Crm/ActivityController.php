<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Crm\Activity;
use App\Services\Crm\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activities) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);
        [$column, $direction] = $this->sort($request, ['due_at', 'created_at', 'priority', 'status', 'title'], 'due_at');
        $user = $request->user();

        $query = Activity::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'customer', 'lead:id,name', 'deal:id,title'])
            ->when($request->query('search'), fn ($q, $v) => $q->where('title', 'like', '%'.addcslashes($v, '%_\\').'%'))
            ->when($request->query('type'), fn ($q, $v) => $q->whereIn('type', (array) $v))
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', (array) $v))
            ->when($request->query('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->query('assigned_to'), fn ($q, $v) => $v === 'me' ? $q->where('assigned_to', $user->id) : $q->where('assigned_to', $v))
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->query('lead_id'), fn ($q, $v) => $q->where('lead_id', $v))
            ->when($request->query('deal_id'), fn ($q, $v) => $q->where('deal_id', $v))
            ->when($request->query('due_from'), fn ($q, $v) => $q->where('due_at', '>=', $v))
            ->when($request->query('due_to'), fn ($q, $v) => $q->where('due_at', '<=', $v))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', [ActivityStatus::Pending->value, ActivityStatus::InProgress->value])->where('due_at', '<', now()))
            ->orderByRaw('due_at is null')
            ->orderBy($column, $direction)
            ->orderBy('id', 'desc');

        return $this->paginated($query->paginate($this->perPage($request)), ActivityResource::class);
    }

    public function store(ActivityRequest $request): JsonResponse
    {
        $this->authorize('create', Activity::class);
        $activity = $this->activities->create($request->validated(), $request->user());

        return $this->created(ActivityResource::make($activity->load(['assignee:id,name', 'customer', 'lead:id,name', 'deal:id,title'])));
    }

    public function show(Activity $activity): JsonResponse
    {
        $this->authorize('view', $activity);

        return $this->ok(ActivityResource::make($activity->load(['assignee:id,name', 'customer', 'lead:id,name', 'deal:id,title', 'creator:id,name'])));
    }

    public function update(ActivityRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $activity = $this->activities->update($activity, $request->validated(), $request->user());

        return $this->ok(ActivityResource::make($activity->load(['assignee:id,name', 'customer', 'lead:id,name', 'deal:id,title'])), 'Activity updated.');
    }

    public function complete(Request $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $activity = $this->activities->complete($activity, $request->user());

        return $this->ok(ActivityResource::make($activity->load(['assignee:id,name'])), 'Marked as completed.');
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);
        $activity->delete();

        return $this->ok(null, 'Activity deleted.');
    }
}
