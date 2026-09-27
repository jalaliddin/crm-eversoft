<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Crm\Customer;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Organization\OrganizationProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/** Platform administration of tenants. Platform users never enter tenant endpoints. */
class PlatformOrganizationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = Organization::query()
            ->withCount('users')
            ->when($request->query('search'), fn ($q, $v) => $q->where(fn ($s) => $s->where('name', 'like', "%{$v}%")->orWhere('slug', 'like', "%{$v}%")))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name');

        return $this->paginated($query->paginate($this->perPage($request)), OrganizationResource::class);
    }

    public function store(Request $request, OrganizationProvisioner $provisioner): JsonResponse
    {
        $this->authorize('platform.organizations.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', 'unique:organizations,slug'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:organizations,domain'],
            'timezone' => ['nullable', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'owner.name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner.password' => ['required', 'string', Password::defaults()],
        ]);

        $result = $provisioner->provision($data, $data['owner']);

        return $this->created(OrganizationResource::make($result['organization']->loadCount('users')), 'Organization created.');
    }

    public function show(Organization $organization): JsonResponse
    {
        return $this->ok([
            'organization' => OrganizationResource::make($organization->loadCount('users'))->resolve(),
            'owners' => User::query()->inTenant($organization->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
                ->get(['id', 'name', 'email', 'last_login_at']),
            'stats' => [
                'employees' => Employee::withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
                'customers' => Customer::withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
                'devices' => AttendanceDevice::withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
            ],
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('platform.organizations.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::unique('organizations', 'slug')->ignore($organization->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('organizations', 'domain')->ignore($organization->id)],
            'timezone' => ['sometimes', 'timezone:all'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::enum(OrganizationStatus::class)],
        ]);

        $old = $organization->only(array_keys($data));
        $organization->update($data);
        $this->audit->log('platform.organization_updated', $organization, $old, $data, $organization->id);

        if (($data['status'] ?? null) === OrganizationStatus::Suspended->value) {
            // Suspension signs everyone out immediately.
            PersonalAccessToken::query()
                ->where('tokenable_type', 'user')
                ->whereIn('tokenable_id', User::query()->inTenant($organization->id)->pluck('id'))
                ->delete();
        }

        return $this->ok(OrganizationResource::make($organization->loadCount('users')), 'Organization updated.');
    }

    public function stats(): JsonResponse
    {
        return $this->ok([
            'organizations' => Organization::query()->count(),
            'active_organizations' => Organization::query()->where('status', OrganizationStatus::Active->value)->count(),
            'users' => User::query()->whereNotNull('organization_id')->count(),
            'employees' => Employee::withoutGlobalScopes()->count(),
        ]);
    }
}
