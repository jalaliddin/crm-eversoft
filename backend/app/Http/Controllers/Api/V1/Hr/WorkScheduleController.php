<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\WorkScheduleRequest;
use App\Http\Resources\WorkScheduleResource;
use App\Models\Hr\WorkSchedule;
use App\Services\Hr\WorkScheduleService;
use Illuminate\Http\JsonResponse;

class WorkScheduleController extends Controller
{
    public function __construct(private readonly WorkScheduleService $schedules) {}

    public function index(): JsonResponse
    {
        $schedules = WorkSchedule::query()
            ->with('days')
            ->withCount(['assignments as employees_count' => fn ($q) => $q->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>=', now()->format('Y-m-d')))])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->ok(WorkScheduleResource::collection($schedules));
    }

    public function store(WorkScheduleRequest $request): JsonResponse
    {
        return $this->created(WorkScheduleResource::make($this->schedules->save(null, $request->validated())));
    }

    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        return $this->ok(WorkScheduleResource::make($workSchedule->load('days')));
    }

    public function update(WorkScheduleRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        return $this->ok(WorkScheduleResource::make($this->schedules->save($workSchedule, $request->validated())), 'Schedule saved. Use "Recalculate" in Attendance to apply it to past days.');
    }

    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        $this->schedules->delete($workSchedule);

        return $this->ok(null, 'Schedule deleted.');
    }
}
