<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollPeriod;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Reports\ReportExporter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** HR reports. ?export=csv|xlsx|pdf returns a file; otherwise JSON {columns, rows}. */
class ReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly ReportExporter $exporter,
        private readonly TenantContext $tenant,
    ) {}

    public function dailyAttendance(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'export' => ['nullable', Rule::in(ReportExporter::FORMATS)],
        ]);
        $date = $data['date'] ?? CarbonImmutable::now($this->tenant->timezone())->format('Y-m-d');

        return $this->respond($data, $this->reports->daily($date, $data), 'attendance-daily-'.$date, 'Daily attendance · '.$date);
    }

    public function monthlyAttendance(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'export' => ['nullable', Rule::in(ReportExporter::FORMATS)],
        ]);
        $month = CarbonImmutable::parse(($data['month'] ?? CarbonImmutable::now($this->tenant->timezone())->format('Y-m')).'-01');

        return $this->respond($data, $this->reports->monthly($month->year, $month->month, $data), 'attendance-monthly-'.$month->format('Y-m'), 'Monthly attendance · '.$month->format('F Y'));
    }

    public function payroll(Request $request, PayrollPeriod $payroll): JsonResponse|Response
    {
        $data = $request->validate(['export' => ['nullable', Rule::in(ReportExporter::FORMATS)]]);
        $label = sprintf('%04d-%02d', $payroll->year, $payroll->month);

        return $this->respond($data, $this->reports->payroll($payroll), 'payroll-'.$label, 'Payroll · '.$label);
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data, array $report, string $name, string $title): JsonResponse|Response
    {
        if (! empty($data['export'])) {
            $this->authorize('attendance.export');

            return $this->exporter->export($data['export'], $name, $title, $report);
        }

        return $this->ok($report);
    }
}
