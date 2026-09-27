<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Enums\PenaltyStatus;
use App\Enums\PenaltyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenaltyResource;
use App\Models\Hr\Employee;
use App\Models\Payroll\Penalty;
use App\Services\Payroll\PenaltyService;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenaltyController extends Controller
{
    public function __construct(private readonly PenaltyService $penalties) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PenaltyStatus::class)],
            'type' => ['nullable', Rule::enum(PenaltyType::class)],
            'employee_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Penalty::query()
            ->with(['employee:id,first_name,last_name,middle_name,employee_number', 'rule:id,name', 'appliedBy:id,name', 'approvedBy:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('date', '<=', $v))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $totals = (clone $query)->reorder()->selectRaw('status, count(*) c, coalesce(sum(amount),0) total')->groupBy('status')->get()
            ->mapWithKeys(fn ($r) => [$r->status->value ?? $r->status => ['count' => (int) $r->c, 'amount' => (float) $r->total]]);

        return $this->paginated($query->paginate($this->perPage($request)), PenaltyResource::class, ['totals' => $totals]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'attendance_record_id' => ['nullable', 'integer', TenantRule::exists('attendance_records', softDeletes: false)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $penalty = $this->penalties->createManual(Employee::query()->findOrFail($data['employee_id']), $data, $request->user());

        return $this->created(PenaltyResource::make($penalty->load(['employee', 'appliedBy:id,name'])));
    }

    public function approve(Request $request, Penalty $penalty): JsonResponse
    {
        return $this->ok(PenaltyResource::make($this->penalties->approve($penalty, $request->user())->load('approvedBy:id,name')), 'Penalty approved.');
    }

    public function reject(Request $request, Penalty $penalty): JsonResponse
    {
        return $this->ok(PenaltyResource::make($this->penalties->reject($penalty, $request->user())), 'Penalty rejected.');
    }

    public function cancel(Request $request, Penalty $penalty): JsonResponse
    {
        return $this->ok(PenaltyResource::make($this->penalties->cancel($penalty, $request->user())), 'Penalty cancelled.');
    }

    /** Approve many pending penalties at once. */
    public function bulkApprove(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $count = 0;
        Penalty::query()->whereIn('id', $data['ids'])->where('status', PenaltyStatus::Pending->value)->get()
            ->each(function (Penalty $penalty) use ($request, &$count): void {
                $this->penalties->approve($penalty, $request->user());
                $count++;
            });

        return $this->ok(['approved' => $count], "{$count} penalties approved.");
    }
}
