<?php

declare(strict_types=1);

namespace App\Models\Crm;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Database\Factories\Crm\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasNotesAndDocuments, HasTags, SoftDeletes;

    protected $fillable = [
        'type', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'secondary_phone', 'website',
        'address', 'city', 'country', 'source', 'status', 'notes', 'custom_fields', 'owner_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'status' => CustomerStatus::class,
            'custom_fields' => 'array',
        ];
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $person = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

            return $this->type === CustomerType::Company
                ? ($this->company_name ?: $person)
                : ($person ?: (string) $this->company_name);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
