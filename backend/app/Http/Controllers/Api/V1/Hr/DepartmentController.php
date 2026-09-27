<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\RecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Hr\Department;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query()
            ->with(['parent:id,name', 'manager:id,first_name,last_name,middle_name'])
            ->withCount('employees')
            ->when($request->query('search'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name');

        if ($request->boolean('all')) {
            return $this->ok(DepartmentResource::collection($query->get()));
        }

        return $this->paginated($query->paginate($this->perPage($request, 50)), DepartmentResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $department = Department::query()->create($this->validated($request));

        return $this->created(DepartmentResource::make($department->load(['parent', 'manager'])));
    }

    public function show(Department $department): JsonResponse
    {
        return $this->ok(DepartmentResource::make($department->load(['parent', 'manager'])->loadCount('employees')));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $this->validated($request, $department);
        if (isset($data['parent_id']) && $this->createsCycle($department, (int) $data['parent_id'])) {
            throw new BusinessRuleException('A department cannot be placed under itself or its sub-department.', ['parent_id' => ['Invalid parent.']]);
        }
        $department->update($data);

        return $this->ok(DepartmentResource::make($department->load(['parent', 'manager'])), 'Department updated.');
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            throw new BusinessRuleException('Move employees out of this department before deleting it.');
        }
        $department->children()->update(['parent_id' => $department->parent_id]);
        $department->delete();

        return $this->ok(null, 'Department deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', TenantRule::unique('departments', 'code', $department?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('departments'), Rule::notIn(array_filter([$department?->id]))],
            'manager_id' => ['nullable', 'integer', TenantRule::exists('employees')],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
        ]);
    }

    private function createsCycle(Department $department, int $parentId): bool
    {
        $seen = [];
        while ($parentId !== 0 && ! in_array($parentId, $seen, true)) {
            if ($parentId === $department->id) {
                return true;
            }
            $seen[] = $parentId;
            $parentId = (int) Department::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
