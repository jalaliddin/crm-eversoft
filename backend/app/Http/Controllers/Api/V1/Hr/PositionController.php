<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\RecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Models\Hr\Position;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Position::query()
            ->with('department:id,name')
            ->withCount('employees')
            ->when($request->query('search'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name');

        if ($request->boolean('all')) {
            return $this->ok(PositionResource::collection($query->get()));
        }

        return $this->paginated($query->paginate($this->perPage($request, 50)), PositionResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->created(PositionResource::make(Position::query()->create($this->validated($request))->load('department')));
    }

    public function show(Position $position): JsonResponse
    {
        return $this->ok(PositionResource::make($position->load('department')->loadCount('employees')));
    }

    public function update(Request $request, Position $position): JsonResponse
    {
        $position->update($this->validated($request, $position));

        return $this->ok(PositionResource::make($position->load('department')), 'Position updated.');
    }

    public function destroy(Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            throw new BusinessRuleException('Reassign employees holding this position before deleting it.');
        }
        $position->delete();

        return $this->ok(null, 'Position deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', TenantRule::unique('positions', 'code', $position?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', TenantRule::exists('departments')],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
        ]);
    }
}
