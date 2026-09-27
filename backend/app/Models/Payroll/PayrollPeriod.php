<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = [
        'year', 'month', 'start_date', 'end_date', 'status', 'opened_at', 'calculated_at',
        'approved_at', 'approved_by', 'closed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollPeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'opened_at' => 'datetime',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [PayrollPeriodStatus::Approved, PayrollPeriodStatus::Closed], true);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
