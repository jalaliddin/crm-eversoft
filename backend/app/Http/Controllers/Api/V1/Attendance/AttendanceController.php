<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Services\Attendance\AttendanceCalendarService;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\AttendanceMonitorService;
use App\Services\Attendance\AttendanceProcessor;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownOnly = ! $user->hasPermission('attendance.view');
        abort_if($ownOnly && ! $user->hasPermission('attendance.view_own'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'source' => ['nullable', 'string'],
            'device_id' => ['nullable', 'integer'],
            'manual' => ['nullable', 'boolean'],
        ]);

        $query = AttendanceRecord::query()
            ->with(['employee.department:id,name', 'device:id,name', 'editor:id,name'])
            ->when($ownOnly, fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0))
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->where('date', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('date', '<=', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('device_id', $v))
            ->when(isset($filters['manual']), fn ($q) => $q->where('is_manual', (bool) $filters['manual']))
            ->orderByDesc('date')
            ->orderBy('employee_id');

        return $this->paginated($query->paginate($this->perPage($request, 50)), AttendanceRecordResource::class);
    }

    public function show(AttendanceRecord $attendance): JsonResponse
    {
        $this->authorize('view', $attendance);

        return $this->ok(AttendanceRecordResource::make($attendance->load(['employee.department:id,name', 'device:id,name', 'editor:id,name', 'schedule:id,name', 'auditLogs.user:id,name'])));
    }

    public function store(Request $request, AttendanceCorrectionService $corrections): JsonResponse
    {
        $this->authorize('create', AttendanceRecord::class);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now($this->tenant->timezone())->addDay()->format('Y-m-d')],
            ...$this->correctionRules(),
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $record = $corrections->create($employee, $data, $request->user());

        return $this->created(AttendanceRecordResource::make($record->load(['employee', 'editor:id,name'])), 'Attendance created.');
    }

    public function update(Request $request, AttendanceRecord $attendance, AttendanceCorrectionService $corrections): JsonResponse
    {
        $this->authorize('update', $attendance);
        $data = $request->validate($this->correctionRules());
        $record = $corrections->update($attendance, $data, $request->user());

        return $this->ok(AttendanceRecordResource::make($record->load(['employee', 'editor:id,name', 'auditLogs.user:id,name'])), 'Attendance corrected.');
    }

    public function destroy(Request $request, AttendanceRecord $attendance, AttendanceCorrectionService $corrections): JsonResponse
    {
        $this->authorize('delete', $attendance);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $corrections->delete($attendance, $data['reason'], $request->user());

        return $this->ok(null, 'Attendance record deleted.');
    }

    /** HR dashboard: cards, trends, department breakdown and live table. */
    public function dashboard(Request $request, AttendanceMonitorService $monitor): JsonResponse
    {
        $this->authorize('attendance.view');
        $today = CarbonImmutable::now($this->tenant->timezone());
        $date = $request->query('date', $today->format('Y-m-d'));

        return $this->ok([
            'date' => $date,
            'summary' => $monitor->summary($date),
            'trend' => $monitor->trend($today->subDays(29)->format('Y-m-d'), $today->format('Y-m-d')),
            'by_department' => $monitor->byDepartment($date),
            'live' => $monitor->rows($date, ['status' => 'present'], 10)->items(),
        ]);
    }

    public function monitor(Request $request, AttendanceMonitorService $monitor): JsonResponse
    {
        $this->authorize('attendance.view');
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:present,late,absent,working,left,incomplete,on_leave,early_leave'],
            'device_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string'],
        ]);
        $date = $filters['date'] ?? CarbonImmutable::now($this->tenant->timezone())->format('Y-m-d');
        $rows = $monitor->rows($date, $filters, $this->perPage($request, 50), (int) $request->query('page', 1));

        return response()->json([
            'success' => true,
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'date' => $date,
                'summary' => $monitor->summary($date),
                'refresh_seconds' => (int) $this->tenant->require()->setting('attendance.monitor_refresh_seconds', 30),
                'generated_at' => now()->toIso8601ZuluString(),
            ],
        ]);
    }

    public function calendar(Request $request, AttendanceCalendarService $calendar): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $month = CarbonImmutable::parse(($data['month'] ?? CarbonImmutable::now($this->tenant->timezone())->format('Y-m')).'-01');
        $user = $request->user();

        if (! empty($data['employee_id']) || ! $user->hasPermission('attendance.view')) {
            $employee = ! empty($data['employee_id']) ? Employee::query()->findOrFail($data['employee_id']) : $user->employee;
            abort_if($employee === null, 404, 'No employee profile is linked to your account.');
            abort_unless($user->hasPermission('attendance.view') || ($user->hasPermission('attendance.view_own') && $employee->user_id === $user->id), 403);

            return $this->ok([
                'month' => $month->format('Y-m'),
                'employee' => ['id' => $employee->id, 'full_name' => $employee->full_name],
                'days' => $calendar->forEmployee($employee, $month->year, $month->month),
            ]);
        }

        $matrix = $calendar->matrix($month->year, $month->month, $data, $this->perPage($request, 25));

        return response()->json([
            'success' => true,
            'data' => $matrix->items(),
            'meta' => [
                'current_page' => $matrix->currentPage(),
                'last_page' => $matrix->lastPage(),
                'per_page' => $matrix->perPage(),
                'total' => $matrix->total(),
                'month' => $month->format('Y-m'),
                'codes' => AttendanceCalendarService::CODES,
            ],
        ]);
    }

    /** Re-apply schedules/rules to a date range (after schedule or rule changes). */
    public function recalculate(Request $request, AttendanceProcessor $processor): JsonResponse
    {
        $this->authorize('attendance.correct');
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee_ids' => ['nullable', 'array', 'max:5000'],
            'employee_ids.*' => ['integer', TenantRule::exists('employees')],
        ]);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 62, 422, 'Recalculate at most two months at a time.');

        $count = 0;
        AttendanceRecord::query()
            ->with('employee.organization')
            ->whereBetween('date', [$data['from'], $data['to']])
            ->when($data['employee_ids'] ?? null, fn ($q, $ids) => $q->whereIn('employee_id', $ids))
            ->chunkById(500, function ($records) use ($processor, &$count): void {
                foreach ($records as $record) {
                    $processor->recalculate($record);
                    $count++;
                }
            });

        return $this->ok(['recalculated' => $count], "{$count} records recalculated.");
    }

    /** Days an employee is expected to work in a range (used by the manual entry form). */
    public function expected(Request $request, AttendanceProcessor $processor): JsonResponse
    {
        $this->authorize('attendance.view');
        $data = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $day = $processor->resolver()->daySchedule($employee, $data['date']);

        return $this->ok([
            'date' => $data['date'],
            'timezone' => $day->timezone,
            'is_working_day' => $day->isWorkingDay,
            'holiday' => $day->holidayName,
            'start' => $day->start?->format('H:i'),
            'end' => $day->end?->format('H:i'),
            'grace_minutes' => $day->graceMinutes,
            'required_minutes' => $day->requiredMinutes,
        ]);
    }

    /** @return array<string, mixed> */
    private function correctionRules(): array
    {
        return [
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
