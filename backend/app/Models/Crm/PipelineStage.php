<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\StageType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStage extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['pipeline_id', 'key', 'name', 'color', 'probability', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => StageType::class, 'probability' => 'integer'];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
