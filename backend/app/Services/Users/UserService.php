<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Permissions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): User
    {
        if ($actor !== null) {
            $this->assertCanGrant($data['role_ids'] ?? [], $actor);
        }

        return DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
            // Organization always comes from the tenant context, never from input.
            $user->organization_id = $this->tenant->require()->id;
            $user->save();

            $this->syncRoles($user, $data['role_ids'] ?? []);
            $this->audit->log('user.created', $user, [], ['name' => $user->name, 'email' => $user->email, 'role_ids' => $data['role_ids'] ?? []]);

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data, User $actor): User
    {
        $this->assertSameTenant($user);
        // Only owners may change an owner's account (email, password, status, roles): prevents admin takeover.
        if ($user->id !== $actor->id && $user->isOwner() && ! $actor->isOwner()) {
            throw new AuthorizationException('Only an owner can change another owner\'s account.');
        }
        if (array_key_exists('role_ids', $data)) {
            $this->assertCanGrant($data['role_ids'], $actor);
        }

        return DB::transaction(function () use ($user, $data, $actor) {
            if (isset($data['status']) && $data['status'] !== 'active' && $user->id === $actor->id) {
                throw new BusinessRuleException('You cannot deactivate your own account.');
            }

            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'phone', 'locale', 'status'])));
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            if (array_key_exists('role_ids', $data)) {
                $this->syncRoles($user, $data['role_ids']);
            }

            if (($data['status'] ?? 'active') !== 'active') {
                $user->tokens()->delete();
            }

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        $this->assertSameTenant($user);
        if ($user->isOwner() && ! $actor->isOwner()) {
            throw new AuthorizationException('Only an owner can delete another owner.');
        }

        if ($user->id === $actor->id) {
            throw new BusinessRuleException('You cannot delete your own account.');
        }
        $this->assertNotLastOwner($user, []);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->roles()->detach();
            $user->delete();
            $this->audit->log('user.deleted', $user, ['email' => $user->email], []);
        });
    }

    /** @param list<int> $roleIds */
    public function syncRoles(User $user, array $roleIds): void
    {
        $roles = Role::query()->whereIn('id', $roleIds)->pluck('id')->all();
        if (count($roles) !== count(array_unique($roleIds))) {
            throw new BusinessRuleException('One or more roles are invalid.', ['role_ids' => ['Invalid role.']]);
        }

        $this->assertNotLastOwner($user, $roles);

        $old = $user->roles()->pluck('roles.id')->all();
        $user->roles()->sync($roles);
        $user->flushPermissionCache();

        if (array_diff($old, $roles) || array_diff($roles, $old)) {
            $this->audit->log('user.roles_changed', $user, ['role_ids' => $old], ['role_ids' => $roles]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveRole(?Role $role, array $data): Role
    {
        $permissions = array_values(array_filter($data['permissions'] ?? [], Permissions::exists(...)));

        return DB::transaction(function () use ($role, $data, $permissions) {
            if ($role?->is_system && $role->slug === 'owner') {
                throw new BusinessRuleException('The owner role always has every permission and cannot be edited.');
            }

            $old = $role?->permissions() ?? [];
            $role ??= new Role(['slug' => Str::slug($data['name'], '_').'_'.substr(uniqid(), -4), 'is_system' => false]);
            $role->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();
            $role->syncPermissions($permissions);

            $this->audit->log('role.permissions_changed', $role, ['permissions' => $old], ['permissions' => $permissions]);

            return $role;
        });
    }

    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException('System roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            throw new BusinessRuleException('Remove this role from all users before deleting it.');
        }

        $role->delete();
        $this->audit->log('role.deleted', $role, ['name' => $role->name], []);
    }

    /** @param list<int|string> $roleIds */
    private function assertCanGrant(array $roleIds, User $actor): void
    {
        $ownerRoleId = Role::query()->where('slug', 'owner')->value('id');
        if ($ownerRoleId !== null && in_array($ownerRoleId, array_map('intval', $roleIds), true) && ! $actor->isOwner()) {
            throw new AuthorizationException('Only an owner can grant the owner role.');
        }
    }

    private function assertSameTenant(User $user): void
    {
        if ($user->organization_id !== $this->tenant->id()) {
            abort(404);
        }
    }

    /** @param list<int> $newRoleIds */
    private function assertNotLastOwner(User $user, array $newRoleIds): void
    {
        $ownerRole = Role::query()->where('slug', 'owner')->first();
        if ($ownerRole === null || ! $user->roles()->whereKey($ownerRole->id)->exists() || in_array($ownerRole->id, $newRoleIds, true)) {
            return;
        }

        $otherOwners = $ownerRole->users()->where('users.id', '!=', $user->id)->whereNull('users.deleted_at')->count();
        if ($otherOwners === 0) {
            throw new BusinessRuleException('The organization must keep at least one owner.');
        }
    }
}
