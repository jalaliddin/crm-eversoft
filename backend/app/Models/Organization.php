<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Support\OrganizationSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'domain', 'logo_path', 'timezone', 'currency', 'locale', 'status', 'settings'];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [OrganizationStatus::Active, OrganizationStatus::Trial], true);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->resolvedSettings(), $key, $default);
    }

    /** @return array<string, mixed> */
    public function resolvedSettings(): array
    {
        return OrganizationSettings::merge($this->settings);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
