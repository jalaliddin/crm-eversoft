<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Enums\DealStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\TimelineEventResource;
use App\Models\Crm\Customer;
use App\Models\Crm\TimelineEvent;
use App\Services\Crm\CustomerService;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): JsonResponse|Response
    {
        $this->authorize('viewAny', Customer::class);
        [$column, $direction] = $this->sort($request, ['created_at', 'updated_at', 'last_name', 'company_name', 'status', 'city']);

        $query = Customer::query()
            ->with(['tags', 'owner:id,name'])
            ->withCount(['leads', 'deals'])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', (array) $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->query('city'), fn ($q, $v) => $q->where('city', $v))
            ->when($request->query('owner_id'), fn ($q, $v) => $q->where('owner_id', $v))
            ->when($request->query('tag_id'), fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->whereKey($v)))
            ->when($request->query('created_from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('created_to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->orderBy($column, $direction)
            ->orderBy('id', 'desc');

        if ($format = $request->query('export')) {
            abort_unless(in_array($format, ReportExporter::FORMATS, true), 422, 'Unsupported export format.');
            $this->authorize('customers.export');

            return app(ReportExporter::class)->export($format, 'customers', 'Customers', [
                'columns' => ['display_name' => 'Name', 'type' => 'Type', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'source' => 'Source', 'status' => 'Status', 'created_at' => 'Created'],
                'rows' => $query->limit(50000)->get()->map(fn (Customer $c) => [
                    'display_name' => $c->display_name, 'type' => $c->type->value, 'email' => $c->email, 'phone' => $c->phone,
                    'city' => $c->city, 'source' => $c->source, 'status' => $c->status->value, 'created_at' => $c->created_at->format('Y-m-d'),
                ])->all(),
            ]);
        }

        return $this->paginated($query->paginate($this->perPage($request)), CustomerResource::class);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $customer = $this->customers->create($request->validated(), $request->user());

        return $this->created(CustomerResource::make($customer->load(['tags', 'owner:id,name'])));
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $customer->load(['tags', 'owner:id,name', 'creator:id,name'])->loadCount(['leads', 'deals']);
        $customer->open_deals_value = $customer->deals()->where('status', DealStatus::Open->value)->sum('value');

        return $this->ok(CustomerResource::make($customer));
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        $customer = $this->customers->update($customer, $request->validated(), $request->user());

        return $this->ok(CustomerResource::make($customer->load(['tags', 'owner:id,name'])), 'Customer updated.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return $this->ok(null, 'Customer deleted.');
    }

    public function timeline(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->paginated(
            TimelineEvent::query()->with('causer:id,name')->where('customer_id', $customer->id)->orderByDesc('occurred_at')->orderByDesc('id')->paginate($this->perPage($request, 30)),
            TimelineEventResource::class,
        );
    }
}
