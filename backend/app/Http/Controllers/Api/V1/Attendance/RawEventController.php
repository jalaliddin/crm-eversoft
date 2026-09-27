<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Enums\RawEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RawEventResource;
use App\Models\Attendance\AttendanceRawEvent;
use App\Services\Attendance\RawEventProcessor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin screen for raw punches, especially unmatched ones (unknown device identifiers). */
class RawEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(RawEventStatus::class)],
            'device_id' => ['nullable', 'integer'],
            'identifier' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AttendanceRawEvent::query()
            ->with(['device:id,name', 'employee:id,first_name,last_name,middle_name'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('device_id', $v))
            ->when($filters['identifier'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('employee_identifier', $v)->orWhere('card_number', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('event_time', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('event_time', '<=', $v.' 23:59:59'))
            ->orderByDesc('event_time')
            ->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request, 50)), RawEventResource::class);
    }

    /** Unmatched identifiers grouped, to map them to employees in one go. */
    public function unmatchedIdentifiers(): JsonResponse
    {
        return $this->ok(AttendanceRawEvent::query()
            ->where('processing_status', RawEventStatus::Unmatched->value)
            ->selectRaw('device_id, employee_identifier, card_number, count(*) as events, max(event_time) as last_seen')
            ->groupBy('device_id', 'employee_identifier', 'card_number')
            ->orderByDesc('events')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'device_id' => $row->device_id,
                'employee_identifier' => $row->employee_identifier,
                'card_number' => $row->card_number,
                'events' => (int) $row->events,
                'last_seen' => $row->last_seen,
            ]));
    }

    public function reprocess(Request $request, RawEventProcessor $processor, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate(['statuses' => ['nullable', 'array'], 'statuses.*' => [Rule::in(['pending', 'unmatched', 'failed'])]]);
        $result = $processor->process($tenant->require()->id, null, $data['statuses'] ?? ['pending', 'unmatched', 'failed']);

        return $this->ok($result, sprintf('%d events processed, %d still unmatched.', $result['processed'], $result['unmatched']));
    }
}
