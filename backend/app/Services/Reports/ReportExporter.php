<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Exports\ArrayExport;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders report rows as CSV / XLSX / PDF. Rows are produced by tenant-scoped services,
 * so exports can never contain another tenant's data. Every export is audited.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    /**
     * @param  array{columns: array<string, string>, rows: list<array<string, mixed>>}  $report
     */
    public function export(string $format, string $name, string $title, array $report): Response
    {
        $timezone = $this->tenant->timezone();
        $rows = array_map(fn (array $row) => array_map(fn ($key) => $this->format($row[$key] ?? null, $key, $timezone), array_keys($report['columns'])), $report['rows']);
        $headings = array_values($report['columns']);
        $filename = sprintf('%s-%s.%s', $name, now($timezone)->format('Ymd-His'), $format);

        $this->audit->log('report.exported', null, [], ['report' => $name, 'format' => $format, 'rows' => count($rows)]);

        return match ($format) {
            'xlsx' => Excel::download(new ArrayExport($headings, $rows, $title), $filename),
            'pdf' => Pdf::loadView('reports.table', [
                'title' => $title,
                'organization' => $this->tenant->require()->name,
                'generatedAt' => now($timezone)->format('Y-m-d H:i'),
                'headings' => $headings,
                'rows' => $rows,
            ])->setPaper('a4', count($headings) > 8 ? 'landscape' : 'portrait')->download($filename),
            default => $this->csv($filename, $headings, $rows),
        };
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    private function csv(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding.
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                // Neutralize spreadsheet formula injection.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function format(mixed $value, string $key, string $timezone): mixed
    {
        if (is_string($value) && in_array($key, ['check_in', 'check_out'], true) && $value !== '') {
            return CarbonImmutable::parse($value)->setTimezone($timezone)->format('H:i');
        }
        if ($key === 'status' && is_string($value)) {
            return ucfirst(str_replace('_', ' ', $value));
        }

        return $value;
    }
}
