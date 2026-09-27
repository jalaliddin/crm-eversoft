<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Enums\PenaltyCalculation;
use App\Enums\PenaltyType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenaltyRule extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $table = 'attendance_penalty_rules';

    protected $fillable = ['name', 'type', 'min_minutes', 'max_minutes', 'calculation', 'amount', 'percentage', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => PenaltyType::class,
            'calculation' => PenaltyCalculation::class,
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function matches(int $minutes): bool
    {
        return $minutes >= $this->min_minutes && ($this->max_minutes === null || $minutes <= $this->max_minutes);
    }
}
