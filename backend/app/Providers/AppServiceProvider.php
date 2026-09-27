<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceRawEvent;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Crm\Activity;
use App\Models\Crm\Customer;
use App\Models\Crm\CustomerContact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use App\Models\CustomFieldDefinition;
use App\Models\Document;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkSchedule;
use App\Models\Hr\Holiday;
use App\Models\Hr\Position;
use App\Models\Hr\WorkSchedule;
use App\Models\Hr\WorkScheduleDay;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\Penalty;
use App\Models\Payroll\PenaltyRule;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\AttendanceRecordPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DealPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeadPolicy;
use App\Services\Attendance\AttendanceDeviceManager;
use App\Support\Permissions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Reset between requests / queued jobs so tenant state never leaks.
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(AttendanceDeviceManager::class);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
        // Catch fillable mistakes early outside production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'organization' => Organization::class,
            'user' => User::class,
            'role' => Role::class,
            'customer' => Customer::class,
            'lead' => Lead::class,
            'deal' => Deal::class,
            'activity' => Activity::class,
            'pipeline' => Pipeline::class,
            'employee' => Employee::class,
            'department' => Department::class,
            'position' => Position::class,
            'work_schedule' => WorkSchedule::class,
            'attendance_record' => AttendanceRecord::class,
            'attendance_device' => AttendanceDevice::class,
            'penalty' => Penalty::class,
            'penalty_rule' => PenaltyRule::class,
            'payroll_period' => PayrollPeriod::class,
            'payroll_record' => PayrollRecord::class,
            'note' => Note::class,
            'document' => Document::class,
            'tag' => Tag::class,
            'custom_field' => CustomFieldDefinition::class,
            'customer_contact' => CustomerContact::class,
            'pipeline_stage' => PipelineStage::class,
            'holiday' => Holiday::class,
            'employee_work_schedule' => EmployeeWorkSchedule::class,
            'work_schedule_day' => WorkScheduleDay::class,
            'employee_device_identifier' => EmployeeDeviceIdentifier::class,
            'attendance_raw_event' => AttendanceRawEvent::class,
        ]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceRecord::class, AttendanceRecordPolicy::class);

        // Permission keys ("customers.view", "platform.health") are checked against the user's roles.
        // Anything else falls through to policies.
        Gate::before(function (User $user, string $ability) {
            if (Permissions::exists($ability) || str_starts_with($ability, 'platform.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('device-ingest', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
