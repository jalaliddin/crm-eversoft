<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Enums\RecordStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = ['department_id', 'name', 'code', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
