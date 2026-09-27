<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\DealStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Database\Factories\Crm\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasNotesAndDocuments, HasTags, SoftDeletes;

    protected $fillable = [
        'customer_id', 'lead_id', 'pipeline_id', 'pipeline_stage_id', 'title', 'value', 'currency', 'status',
        'probability', 'expected_close_date', 'assigned_to', 'description', 'lost_reason', 'won_at', 'lost_at',
        'custom_fields', 'position', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    protected static function newFactory(): DealFactory
    {
        return DealFactory::new();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where('title', 'like', $like);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('crm.view_all')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
