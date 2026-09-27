<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payroll\PayrollPeriod;
use Illuminate\Http\Request;

/** @mixin PayrollPeriod */
class PayrollPeriodResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'start_date' => $this->day($this->start_date),
            'end_date' => $this->day($this->end_date),
            'status' => $this->status?->value,
            'opened_at' => $this->iso($this->opened_at),
            'calculated_at' => $this->iso($this->calculated_at),
            'approved_at' => $this->iso($this->approved_at),
            'approved_by' => $this->userRef($this->whenLoaded('approver', fn () => $this->approver, null)),
            'closed_at' => $this->iso($this->closed_at),
            'records_count' => $this->whenCounted('records'),
            'totals' => $this->when(isset($this->net_total), fn () => [
                'gross' => (float) $this->gross_total,
                'penalties' => (float) $this->penalty_total,
                'bonuses' => (float) $this->bonus_total,
                'net' => (float) $this->net_total,
            ]),
        ];
    }
}
