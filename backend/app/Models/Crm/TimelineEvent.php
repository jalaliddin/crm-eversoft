<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimelineEvent extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'event', 'subject_type', 'subject_id', 'customer_id', 'lead_id', 'deal_id',
        'description', 'properties', 'causer_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
