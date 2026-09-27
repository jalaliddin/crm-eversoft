<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Models\Hr\Employee;
use App\Notifications\ResetPasswordNotification;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Users are not globally scoped: login must find a user before the tenant is known.
 * Tenant-facing queries on users must go through User::inTenant() / explicit organization filters.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar_path', 'locale', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var list<string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'platform_role' => PlatformRole::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function scopeInTenant($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function isPlatformUser(): bool
    {
        return $this->organization_id === null && $this->platform_role !== null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isOwner(): bool
    {
        return in_array('owner', $this->roles->pluck('slug')->all(), true);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->isPlatformUser()) {
            return $this->permissionCache = Permissions::PLATFORM[$this->platform_role->value] ?? [];
        }

        return $this->permissionCache = DB::table('permission_role')
            ->join('role_user', 'role_user.role_id', '=', 'permission_role.role_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->where('roles.organization_id', $this->organization_id)
            ->distinct()
            ->pluck('permission_role.permission')
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
