<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'title', 'type', 'description', 'due_at', 'completed_at', 'duration_minutes', 'assigned_to',
        'customer_id', 'lead_id', 'deal_id', 'priority', 'status', 'reminded_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'priority' => ActivityPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('crm.view_all')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ActivityStatus::Pending, ActivityStatus::InProgress], true);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
