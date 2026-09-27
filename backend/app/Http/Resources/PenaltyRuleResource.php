<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payroll\PenaltyRule;
use Illuminate\Http\Request;

/** @mixin PenaltyRule */
class PenaltyRuleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'min_minutes' => $this->min_minutes,
            'max_minutes' => $this->max_minutes,
            'calculation' => $this->calculation?->value,
            'amount' => (float) $this->amount,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
            'is_active' => $this->is_active,
        ];
    }
}
