<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\LeadRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DealResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\TimelineEventResource;
use App\Models\Crm\Lead;
use App\Models\Crm\TimelineEvent;
use App\Services\Crm\LeadService;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        [$column, $direction] = $this->sort($request, ['created_at', 'updated_at', 'name', 'status', 'expected_value']);

        $query = $this->filtered($request)->orderBy($column, $direction)->orderBy('id', 'desc');

        return $this->paginated($query->paginate($this->perPage($request)), LeadResource::class);
    }

    /** Kanban board: leads grouped by status (converted/lost columns limited to recent). */
    public function board(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        $leads = $this->filtered($request)->orderBy('position')->orderByDesc('id')->limit(1000)->get();

        return $this->ok(collect(LeadStatus::cases())->map(fn (LeadStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'leads' => LeadResource::collection($leads->where('status', $status)->values())->resolve(),
            'total_value' => (float) $leads->where('status', $status)->sum('expected_value'),
        ])->values());
    }

    public function store(LeadRequest $request): JsonResponse
    {
        $this->authorize('create', Lead::class);
        $this->assertCanAssign($request);
        $lead = $this->leads->create($request->validated(), $request->user());

        return $this->created(LeadResource::make($lead->load(['assignee:id,name', 'customer', 'tags'])));
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return $this->ok(LeadResource::make($lead->load(['assignee:id,name', 'customer', 'tags'])));
    }

    public function update(LeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);
        if ($request->has('assigned_to') && (int) $request->input('assigned_to') !== $lead->assigned_to) {
            $this->assertCanAssign($request);
        }
        $lead = $this->leads->update($lead, $request->validated(), $request->user());

        return $this->ok(LeadResource::make($lead->load(['assignee:id,name', 'customer', 'tags'])), 'Lead updated.');
    }

    public function move(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);
        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)->except([LeadStatus::Converted])],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $lead = $this->leads->move($lead, LeadStatus::from($data['status']), (int) ($data['position'] ?? 0), $request->user());

        return $this->ok(LeadResource::make($lead->load(['assignee:id,name'])));
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);
        $this->authorize('customers.create');
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers')],
            'create_deal' => ['sometimes', 'boolean'],
            'deal_title' => ['nullable', 'string', 'max:255'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'pipeline_id' => ['nullable', 'integer', TenantRule::exists('pipelines', softDeletes: false)],
        ]);
        if (! empty($data['create_deal'])) {
            $this->authorize('deals.create');
        }

        $result = $this->leads->convert($lead, $data, $request->user());

        return $this->ok([
            'lead' => LeadResource::make($result['lead'])->resolve(),
            'customer' => CustomerResource::make($result['customer'])->resolve(),
            'deal' => $result['deal'] ? DealResource::make($result['deal'])->resolve() : null,
        ], 'Lead converted.');
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);
        $lead->delete();

        return $this->ok(null, 'Lead deleted.');
    }

    public function timeline(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return $this->paginated(
            TimelineEvent::query()->with('causer:id,name')->where('lead_id', $lead->id)->orderByDesc('occurred_at')->orderByDesc('id')->paginate($this->perPage($request, 30)),
            TimelineEventResource::class,
        );
    }

    private function filtered(Request $request)
    {
        return Lead::query()
            ->visibleTo($request->user())
            ->with(['assignee:id,name', 'customer', 'tags'])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', (array) $v))
            ->when($request->query('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->query('assigned_to'), fn ($q, $v) => $v === 'me' ? $q->where('assigned_to', $request->user()->id) : $q->where('assigned_to', $v))
            ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->query('tag_id'), fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->whereKey($v)));
    }

    private function assertCanAssign(Request $request): void
    {
        $assignee = $request->input('assigned_to');
        if ($assignee !== null && (int) $assignee !== $request->user()->id) {
            $this->authorize('leads.assign');
        }
    }
}
