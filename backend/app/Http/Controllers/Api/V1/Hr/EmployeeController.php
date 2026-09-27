<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeRequest;
use App\Http\Resources\DeviceIdentifierResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\PayrollRecordResource;
use App\Http\Resources\PenaltyResource;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Hr\Employee;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\Penalty;
use App\Services\Hr\EmployeeService;
use App\Services\Reports\ReportExporter;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): JsonResponse|Response
    {
        $this->authorize('viewAny', Employee::class);
        [$column, $direction] = $this->sort($request, ['last_name', 'employee_number', 'hire_date', 'created_at', 'status'], 'last_name');

        $query = Employee::query()
            ->with(['department:id,name', 'position:id,name', 'scheduleAssignments.schedule:id,name'])
            ->search($request->query('search'))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('position_id'), fn ($q, $v) => $q->where('position_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', (array) $v))
            ->when($request->query('employment_type'), fn ($q, $v) => $q->where('employment_type', $v))
            ->when($request->query('salary_type'), fn ($q, $v) => $q->where('salary_type', $v))
            ->orderBy($column, $direction)
            ->orderBy('first_name');

        if ($format = $request->query('export')) {
            abort_unless(in_array($format, ReportExporter::FORMATS, true), 422, 'Unsupported export format.');

            return app(ReportExporter::class)->export($format, 'employees', 'Employees', [
                'columns' => ['employee_number' => 'No.', 'full_name' => 'Name', 'department' => 'Department', 'position' => 'Position', 'phone' => 'Phone', 'email' => 'Email', 'hire_date' => 'Hire date', 'status' => 'Status'],
                'rows' => $query->limit(50000)->get()->map(fn (Employee $e) => [
                    'employee_number' => $e->employee_number, 'full_name' => $e->full_name, 'department' => $e->department?->name,
                    'position' => $e->position?->name, 'phone' => $e->phone, 'email' => $e->email,
                    'hire_date' => $e->hire_date?->format('Y-m-d'), 'status' => $e->status->value,
                ])->all(),
            ]);
        }

        if ($request->boolean('all')) {
            return $this->ok($query->limit(2000)->get()->map(fn (Employee $e) => ['id' => $e->id, 'full_name' => $e->full_name, 'employee_number' => $e->employee_number, 'department_id' => $e->department_id]));
        }

        return $this->paginated($query->paginate($this->perPage($request)), EmployeeResource::class);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);
        $employee = $this->employees->create($request->validated());

        return $this->created(EmployeeResource::make($this->withRelations($employee)));
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->ok(EmployeeResource::make($this->withRelations($employee)));
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validated();
        if (! $request->user()->hasPermission('payroll.view')) {
            // Users who cannot see compensation cannot change it either.
            unset($data['salary_amount'], $data['salary_type']);
        }
        $employee = $this->employees->update($employee, $data);

        return $this->ok(EmployeeResource::make($this->withRelations($employee)), 'Employee updated.');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $employee->delete();

        return $this->ok(null, 'Employee deleted.');
    }

    public function photo(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        return $this->ok(EmployeeResource::make($this->withRelations($this->employees->storePhoto($employee, $request->file('photo')))), 'Photo updated.');
    }

    public function assignSchedule(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validate([
            'work_schedule_id' => ['required', 'integer', TenantRule::exists('work_schedules')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $this->employees->assignSchedule($employee, (int) $data['work_schedule_id'], $data['effective_from'], $data['effective_to'] ?? null);

        return $this->ok(EmployeeResource::make($this->withRelations($employee)), 'Schedule assigned.');
    }

    public function identifiers(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->ok(DeviceIdentifierResource::collection($employee->deviceIdentifiers()->with('device:id,name')->get()));
    }

    public function storeIdentifier(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('devices.manage');
        $identifier = $this->employees->saveIdentifier($employee, $this->identifierRules($request));

        return $this->created(DeviceIdentifierResource::make($identifier->load('device:id,name')));
    }

    public function updateIdentifier(Request $request, Employee $employee, EmployeeDeviceIdentifier $identifier): JsonResponse
    {
        $this->authorize('devices.manage');
        abort_unless($identifier->employee_id === $employee->id, 404);
        $identifier = $this->employees->saveIdentifier($employee, $this->identifierRules($request), $identifier);

        return $this->ok(DeviceIdentifierResource::make($identifier->load('device:id,name')), 'Identifier updated.');
    }

    public function destroyIdentifier(Employee $employee, EmployeeDeviceIdentifier $identifier): JsonResponse
    {
        $this->authorize('devices.manage');
        abort_unless($identifier->employee_id === $employee->id, 404);
        $identifier->delete();

        return $this->ok(null, 'Identifier removed.');
    }

    public function penalties(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('penalties.view');

        return $this->paginated(
            Penalty::query()->with('rule:id,name')->where('employee_id', $employee->id)->orderByDesc('date')->paginate($this->perPage($request)),
            PenaltyResource::class,
        );
    }

    public function payroll(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('payroll.view');

        return $this->ok(PayrollRecordResource::collection(
            PayrollRecord::query()->with('period')->where('employee_id', $employee->id)->orderByDesc('id')->limit(24)->get(),
        ));
    }

    /** @return array<string, mixed> */
    private function identifierRules(Request $request): array
    {
        return $request->validate([
            'identifier' => ['required', 'string', 'max:64'],
            'device_id' => ['nullable', 'integer', TenantRule::exists('attendance_devices')],
            'card_number' => ['nullable', 'string', 'max:64'],
            'biometric_identifier' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function withRelations(Employee $employee): Employee
    {
        return $employee->load([
            'department:id,name', 'position:id,name', 'manager:id,first_name,last_name,middle_name', 'user:id,name,email',
            'scheduleAssignments.schedule:id,name', 'deviceIdentifiers.device:id,name',
        ]);
    }
}
