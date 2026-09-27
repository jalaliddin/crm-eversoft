<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollPeriodResource;
use App\Http\Resources\PayrollRecordResource;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRecord;
use App\Services\Payroll\PayrollService;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request): JsonResponse
    {
        $periods = PayrollPeriod::query()
            ->withCount('records')
            ->withSum('records as gross_total', 'gross_amount')
            ->withSum('records as penalty_total', 'penalty_amount')
            ->withSum('records as bonus_total', 'bonus_amount')
            ->withSum('records as net_total', 'net_amount')
            ->with('approver:id,name')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate($this->perPage($request, 24));

        return $this->paginated($periods, PayrollPeriodResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        return $this->created(PayrollPeriodResource::make($this->payroll->open((int) $data['year'], (int) $data['month'], $request->user())));
    }

    public function show(PayrollPeriod $payroll): JsonResponse
    {
        return $this->ok(PayrollPeriodResource::make($this->withTotals($payroll)));
    }

    public function records(Request $request, PayrollPeriod $payroll): JsonResponse
    {
        $query = PayrollRecord::query()
            ->where('payroll_period_id', $payroll->id)
            ->with(['employee' => fn ($q) => $q->withTrashed()->with('department:id,name')])
            ->when($request->query('search'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->search($v)))
            ->when($request->query('department_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->orderByDesc('net_amount');

        return $this->paginated($query->paginate($this->perPage($request, 50)), PayrollRecordResource::class);
    }

    /** POST /payroll/calculate { period_id } or { year, month } (opens the period if needed). */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => ['required_without_all:year,month', 'nullable', 'integer', TenantRule::exists('payroll_periods', softDeletes: false)],
            'year' => ['required_without:period_id', 'nullable', 'integer', 'between:2000,2100'],
            'month' => ['required_without:period_id', 'nullable', 'integer', 'between:1,12'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', TenantRule::exists('employees')],
        ]);

        $period = ! empty($data['period_id'])
            ? PayrollPeriod::query()->findOrFail($data['period_id'])
            : (PayrollPeriod::query()->where('year', $data['year'])->where('month', $data['month'])->first()
                ?? $this->payroll->open((int) $data['year'], (int) $data['month'], $request->user()));

        $period = $this->payroll->calculate($period, $request->user(), $data['employee_ids'] ?? null);

        return $this->ok(PayrollPeriodResource::make($this->withTotals($period)), 'Payroll calculated.');
    }

    public function approve(Request $request, PayrollPeriod $payroll): JsonResponse
    {
        return $this->ok(PayrollPeriodResource::make($this->withTotals($this->payroll->approve($payroll, $request->user()))), 'Payroll approved.');
    }

    public function pay(Request $request, PayrollPeriod $payroll): JsonResponse
    {
        return $this->ok(PayrollPeriodResource::make($this->withTotals($this->payroll->markPaid($payroll, $request->user()))), 'Payroll marked as paid.');
    }

    public function reopen(Request $request, PayrollPeriod $payroll): JsonResponse
    {
        return $this->ok(PayrollPeriodResource::make($this->withTotals($this->payroll->reopen($payroll, $request->user()))), 'Payroll reopened.');
    }

    public function updateRecord(Request $request, PayrollRecord $payrollRecord): JsonResponse
    {
        $data = $request->validate(['bonus_amount' => ['required', 'numeric', 'min:0', 'max:9999999999']]);

        return $this->ok(PayrollRecordResource::make($this->payroll->updateBonus($payrollRecord, (float) $data['bonus_amount'])->load('employee')), 'Bonus saved.');
    }

    public function destroy(PayrollPeriod $payroll): JsonResponse
    {
        abort_if($payroll->isLocked(), 422, 'Approved or closed periods cannot be deleted.');
        $payroll->delete();

        return $this->ok(null, 'Payroll period deleted.');
    }

    private function withTotals(PayrollPeriod $period): PayrollPeriod
    {
        return $period->loadCount('records')
            ->loadSum('records as gross_total', 'gross_amount')
            ->loadSum('records as penalty_total', 'penalty_amount')
            ->loadSum('records as bonus_total', 'bonus_amount')
            ->loadSum('records as net_total', 'net_amount')
            ->load('approver:id,name');
    }
}
