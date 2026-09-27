<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\LeadStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Database\Factories\Crm\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasNotesAndDocuments, HasTags, SoftDeletes;

    protected $fillable = [
        'customer_id', 'name', 'company_name', 'phone', 'email', 'source', 'status', 'assigned_to',
        'expected_value', 'description', 'lost_reason', 'converted_at', 'custom_fields', 'position', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'expected_value' => 'decimal:2',
            'converted_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like));
    }

    /** Restrict to the user's own records unless they may see everything. */
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
