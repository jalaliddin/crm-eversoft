<?php

declare(strict_types=1);

namespace App\Models\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\SalaryType;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\Penalty;
use App\Models\User;
use Database\Factories\Hr\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToOrganization, HasFactory, HasNotesAndDocuments, SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_number', 'first_name', 'last_name', 'middle_name', 'phone', 'email', 'birth_date',
        'gender', 'address', 'department_id', 'position_id', 'manager_id', 'employment_type', 'hire_date',
        'termination_date', 'salary_type', 'salary_amount', 'status', 'photo_path', 'notes', 'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'gender' => Gender::class,
            'employment_type' => EmploymentType::class,
            'salary_type' => SalaryType::class,
            'salary_amount' => 'decimal:2',
            'status' => EmployeeStatus::class,
            'custom_fields' => 'array',
        ];
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->last_name.' '.$this->first_name.' '.($this->middle_name ?? '')));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(fn (Builder $q) => $q->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('employee_number', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('email', 'like', $like));
    }

    /** Employees whose attendance is tracked (not terminated). */
    public function scopeTracked(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [EmployeeStatus::Active->value, EmployeeStatus::OnLeave->value]);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class)->orderByDesc('effective_from');
    }

    public function deviceIdentifiers(): HasMany
    {
        return $this->hasMany(EmployeeDeviceIdentifier::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }
}
