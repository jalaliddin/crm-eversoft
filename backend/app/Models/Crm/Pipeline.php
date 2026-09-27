<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort_order');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
