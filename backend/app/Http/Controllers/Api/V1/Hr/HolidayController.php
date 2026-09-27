<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\HolidayResource;
use App\Models\Hr\Holiday;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);

        return $this->ok(HolidayResource::collection(Holiday::query()->whereYear('date', $year)->orderBy('date')->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', TenantRule::unique('holidays', 'date', softDeletes: false)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return $this->created(HolidayResource::make(Holiday::query()->create($data)));
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $holiday->update($request->validate([
            'date' => ['required', 'date', TenantRule::unique('holidays', 'date', $holiday->id, softDeletes: false)],
            'name' => ['required', 'string', 'max:255'],
        ]));

        return $this->ok(HolidayResource::make($holiday), 'Holiday updated.');
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return $this->ok(null, 'Holiday deleted.');
    }
}
