<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Users\UserService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): JsonResponse
    {
        return $this->ok(RoleResource::collection(Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get()));
    }

    public function permissions(): JsonResponse
    {
        return $this->ok(collect(Permissions::CATALOG)->map(fn ($items, $group) => [
            'group' => $group,
            'permissions' => collect($items)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ])->values());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->created(RoleResource::make($this->users->saveRole(null, $this->validated($request))));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        return $this->ok(RoleResource::make($this->users->saveRole($role, $this->validated($request))), 'Role saved.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->users->deleteRole($role);

        return $this->ok(null, 'Role deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
    }
}
