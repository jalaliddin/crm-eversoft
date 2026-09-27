<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['slug', 'name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return DB::table('permission_role')->where('role_id', $this->id)->orderBy('permission')->pluck('permission')->all();
    }

    /** @param list<string> $permissions */
    public function syncPermissions(array $permissions): void
    {
        DB::transaction(function () use ($permissions): void {
            DB::table('permission_role')->where('role_id', $this->id)->delete();
            DB::table('permission_role')->insert(array_map(
                fn (string $permission) => ['role_id' => $this->id, 'permission' => $permission],
                array_values(array_unique($permissions)),
            ));
        });
    }
}
