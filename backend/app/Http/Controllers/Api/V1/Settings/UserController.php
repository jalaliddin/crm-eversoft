<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        [$column, $direction] = $this->sort($request, ['name', 'email', 'created_at', 'last_login_at'], 'name');

        $query = $this->tenantUsers()
            ->with(['roles', 'employee:id,user_id'])
            ->when($request->query('search'), fn ($q, $v) => $q->where(fn ($s) => $s->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('role_id'), fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->whereKey($v)))
            ->orderBy($column, $direction);

        return $this->paginated($query->paginate($this->perPage($request)), UserResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->users->create($request->validate($this->rules()), $request->user());

        return $this->created(UserResource::make($user->load('roles')));
    }

    public function show(int $user): JsonResponse
    {
        return $this->ok(UserResource::make($this->find($user)->load(['roles', 'employee'])));
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $model = $this->find($user);
        $updated = $this->users->update($model, $request->validate($this->rules($model)), $request->user());

        return $this->ok(UserResource::make($updated->load('roles')), 'User updated.');
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $this->users->delete($this->find($user), $request->user());

        return $this->ok(null, 'User deleted.');
    }

    /** Users are not globally scoped (login needs them before a tenant exists), so scope explicitly. */
    private function tenantUsers()
    {
        return User::query()->inTenant($this->tenant->require()->id);
    }

    private function find(int $id): User
    {
        return $this->tenantUsers()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function rules(?User $user = null): array
    {
        return [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults()],
            'locale' => ['nullable', 'in:en,ru,uz'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'role_ids' => [$user ? 'sometimes' : 'required', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }
}
