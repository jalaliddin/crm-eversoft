<?php

declare(strict_types=1);

namespace App\Models\Payroll;

use App\Enums\PayrollRecordStatus;
use App\Enums\SalaryType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'payroll_period_id', 'employee_id', 'salary_type', 'rate', 'base_salary', 'scheduled_days', 'scheduled_minutes',
        'worked_days', 'worked_minutes', 'overtime_minutes', 'late_minutes', 'early_leave_minutes', 'absence_days',
        'overtime_amount', 'gross_amount', 'penalty_amount', 'bonus_amount', 'net_amount', 'currency', 'status',
        'details', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'salary_type' => SalaryType::class,
            'status' => PayrollRecordStatus::class,
            'rate' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'details' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
