<?php

namespace Database\Seeders;

use App\Enums\DeviceEventType;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\EmployeeDeviceIdentifier;
use App\Models\Crm\Activity;
use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeWorkSchedule;
use App\Models\Hr\Holiday;
use App\Models\Hr\Position;
use App\Models\Hr\WorkSchedule;
use App\Models\Organization;
use App\Models\Payroll\Penalty;
use App\Models\Payroll\PenaltyRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\Devices\DeviceEvent;
use App\Services\Attendance\RawEventIngestor;
use App\Services\Attendance\RawEventProcessor;
use App\Services\Crm\TimelineRecorder;
use App\Services\Organization\OrganizationProvisioner;
use App\Services\Payroll\PayrollService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoTenantSeeder extends Seeder
{
    public function run(string $name, string $slug, string $prefix, string $emailDomain, int $size): void
    {
        mt_srand(crc32($slug));
        fake()->seed(crc32($slug));

        $result = app(OrganizationProvisioner::class)->provision(
            ['name' => $name, 'slug' => $slug, 'timezone' => 'Asia/Tashkent', 'currency' => 'UZS'],
            ['name' => $prefix.' Owner', 'email' => 'owner@'.$emailDomain, 'password' => 'password'],
        );
        /** @var Organization $organization */
        $organization = $result['organization'];

        app(TenantContext::class)->run($organization, function () use ($organization, $prefix, $emailDomain, $size, $result): void {
            Auth::setUser($result['owner']);
            $users = $this->users($prefix, $emailDomain);
            $users['owner'] = $result['owner'];

            [$departments, $positions] = $this->structure($prefix);
            $schedules = $this->schedules();
            $this->holidays();
            $this->penaltyRules();
            $employees = $this->employees($prefix, $size, $departments, $positions, $schedules, $users);
            $this->crm($prefix, $users);
            $this->attendance($organization, $employees);
            $this->payroll($users['hr_manager']);
        });

        Auth::forgetUser();
    }

    /** @return array<string, User> */
    private function users(string $prefix, string $domain): array
    {
        $roles = Role::query()->pluck('id', 'slug');
        $definitions = [
            'admin' => ['admin', $prefix.' Admin'],
            'hr_manager' => ['hr', $prefix.' HR Manager'],
            'sales_manager' => ['sales', $prefix.' Sales Manager'],
            'sales_agent' => ['agent', $prefix.' Sales Agent'],
            'manager' => ['manager', $prefix.' Manager'],
            'employee' => ['employee', $prefix.' Employee'],
        ];

        $users = [];
        foreach ($definitions as $role => [$local, $userName]) {
            $user = new User(['name' => $userName, 'email' => $local.'@'.$domain, 'password' => 'password', 'status' => 'active']);
            $user->organization_id = app(TenantContext::class)->id();
            $user->save();
            $user->roles()->attach($roles[$role]);
            $users[$role] = $user;
        }

        return $users;
    }

    /** @return array{0: array<string, Department>, 1: array<string, Position>} */
    private function structure(string $prefix): array
    {
        $departments = [];
        foreach (['MGT' => 'Management', 'SAL' => 'Sales', 'HR' => 'Human Resources', 'IT' => 'IT', 'OPS' => 'Operations'] as $code => $label) {
            $departments[$code] = Department::query()->create(['name' => $prefix.' '.$label, 'code' => $code, 'status' => 'active']);
        }
        $departments['SAL']->update(['parent_id' => $departments['MGT']->id]);

        $positions = [];
        foreach ([
            'CEO' => ['Chief Executive Officer', 'MGT'], 'SM' => ['Sales Manager', 'SAL'], 'SA' => ['Sales Agent', 'SAL'],
            'HRM' => ['HR Manager', 'HR'], 'DEV' => ['Software Engineer', 'IT'], 'OPS' => ['Operator', 'OPS'], 'DRV' => ['Driver', 'OPS'],
        ] as $code => [$label, $dept]) {
            $positions[$code] = Position::query()->create(['name' => $label, 'code' => $code, 'department_id' => $departments[$dept]->id, 'status' => 'active']);
        }

        return [$departments, $positions];
    }

    /** @return array<string, WorkSchedule> */
    private function schedules(): array
    {
        $standard = WorkSchedule::query()->where('is_default', true)->firstOrFail();

        $sixDay = WorkSchedule::query()->create([
            'name' => 'Six-day (Mon–Sat 09:00–18:00)', 'type' => 'fixed', 'grace_minutes' => 5, 'late_mode' => 'after_grace',
            'overtime_enabled' => true, 'overtime_threshold_minutes' => 30, 'status' => 'active',
        ]);
        foreach (range(1, 7) as $d) {
            $sixDay->days()->create([
                'day_of_week' => $d, 'is_working_day' => $d <= 6,
                'start_time' => $d <= 6 ? '09:00' : null, 'end_time' => $d <= 6 ? '18:00' : null,
                'break_start' => $d <= 6 ? '13:00' : null, 'break_end' => $d <= 6 ? '14:00' : null,
            ]);
        }

        $flexible = WorkSchedule::query()->create([
            'name' => 'Flexible 8h', 'type' => 'flexible', 'required_daily_minutes' => 480, 'grace_minutes' => 0,
            'overtime_enabled' => false, 'status' => 'active',
        ]);
        foreach (range(1, 7) as $d) {
            $flexible->days()->create(['day_of_week' => $d, 'is_working_day' => $d <= 5, 'break_minutes' => $d <= 5 ? 60 : null]);
        }

        return ['standard' => $standard, 'six_day' => $sixDay, 'flexible' => $flexible];
    }

    private function holidays(): void
    {
        $year = now()->year;
        foreach (['01-01' => 'New Year', '03-08' => "International Women's Day", '03-21' => 'Navruz', '05-09' => 'Day of Remembrance',
            '09-01' => 'Independence Day', '10-01' => "Teachers' Day", '12-08' => 'Constitution Day'] as $md => $label) {
            Holiday::query()->create(['date' => $year.'-'.$md, 'name' => $label]);
        }
    }

    private function penaltyRules(): void
    {
        foreach ([
            ['name' => 'Late 15+ min', 'type' => 'late', 'min_minutes' => 15, 'max_minutes' => 59, 'calculation' => 'fixed', 'amount' => 20000],
            ['name' => 'Late 60+ min', 'type' => 'late', 'min_minutes' => 60, 'max_minutes' => null, 'calculation' => 'fixed', 'amount' => 50000],
            ['name' => 'Early leave 30+ min', 'type' => 'early_leave', 'min_minutes' => 30, 'max_minutes' => null, 'calculation' => 'fixed', 'amount' => 30000],
            ['name' => 'Absence without reason', 'type' => 'absence', 'min_minutes' => 0, 'max_minutes' => null, 'calculation' => 'daily_salary', 'amount' => 0],
        ] as $rule) {
            PenaltyRule::query()->create([...$rule, 'is_active' => true]);
        }
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, Position>  $positions
     * @param  array<string, WorkSchedule>  $schedules
     * @param  array<string, User>  $users
     * @return list<Employee>
     */
    private function employees(string $prefix, int $size, array $departments, array $positions, array $schedules, array $users): array
    {
        $plan = [['CEO', 'MGT'], ['HRM', 'HR'], ['SM', 'SAL'], ['SA', 'SAL'], ['SA', 'SAL'], ['DEV', 'IT'], ['DEV', 'IT'], ['OPS', 'OPS'], ['DRV', 'OPS']];
        $linkedUsers = [2 => 'sales_manager', 3 => 'sales_agent', 1 => 'hr_manager', 7 => 'employee'];
        $employees = [];

        for ($i = 0; $i < $size; $i++) {
            [$pos, $dept] = $plan[$i % count($plan)];
            $salaryType = match (true) {
                $i % 7 === 5 => 'hourly',
                $i % 5 === 4 => 'daily',
                default => 'monthly',
            };

            $employee = Employee::query()->create([
                'employee_number' => sprintf('%04d', $i + 1),
                'first_name' => fake()->firstName(),
                'last_name' => $i === 0 ? $prefix.'ov' : fake()->lastName(),
                'phone' => '+998'.fake()->numerify('9########'),
                'email' => fake()->safeEmail(),
                'gender' => fake()->randomElement(['male', 'female']),
                'birth_date' => now()->subYears(fake()->numberBetween(22, 55))->format('Y-m-d'),
                'department_id' => $departments[$dept]->id,
                'position_id' => $positions[$pos]->id,
                'employment_type' => $i % 11 === 10 ? 'part_time' : 'full_time',
                'hire_date' => now()->subMonths(fake()->numberBetween(3, 36))->startOfMonth()->format('Y-m-d'),
                'salary_type' => $salaryType,
                'salary_amount' => match ($salaryType) {
                    'hourly' => 45000, 'daily' => 300000, default => fake()->randomElement([5, 6, 7, 8, 10, 12, 15]) * 1000000
                },
                'status' => $i === $size - 1 ? 'on_leave' : 'active',
                'user_id' => isset($linkedUsers[$i]) ? $users[$linkedUsers[$i]]->id : null,
            ]);

            $schedule = match (true) {
                $dept === 'OPS' => $schedules['six_day'],
                $pos === 'DEV' && $i % 2 === 0 => $schedules['flexible'],
                default => $schedules['standard'],
            };
            EmployeeWorkSchedule::query()->create([
                'employee_id' => $employee->id, 'work_schedule_id' => $schedule->id, 'effective_from' => $employee->hire_date->format('Y-m-d'),
            ]);
            // Identifier on the device ("employeeNo") deliberately differs from the database id.
            EmployeeDeviceIdentifier::query()->create(['employee_id' => $employee->id, 'identifier' => (string) (1000 + $i + 1), 'status' => 'active']);

            $employees[] = $employee;
        }

        $departments['MGT']->update(['manager_id' => $employees[0]->id]);
        $departments['HR']->update(['manager_id' => $employees[1]->id]);
        $departments['SAL']->update(['manager_id' => $employees[2]->id]);

        return $employees;
    }

    /** @param array<string, User> $users */
    private function crm(string $prefix, array $users): void
    {
        $timeline = app(TimelineRecorder::class);
        $sales = [$users['sales_manager'], $users['sales_agent']];

        $customers = [];
        $customers[] = Customer::query()->create([
            'type' => 'company', 'company_name' => $prefix.' Customer A — Tashkent Textile', 'first_name' => 'Aziz', 'last_name' => 'Karimov',
            'email' => 'info@'.strtolower($prefix).'-textile.test', 'phone' => '+998901112233', 'city' => 'Tashkent', 'country' => 'Uzbekistan',
            'source' => 'referral', 'status' => 'active', 'owner_id' => $users['sales_manager']->id, 'created_by' => $users['sales_manager']->id,
        ]);
        for ($i = 0; $i < 30; $i++) {
            $customers[] = Customer::factory()->create([
                'company_name' => fn (array $a) => $a['type'] === 'company' ? $prefix.' '.fake()->company() : null,
                'owner_id' => fake()->randomElement($sales)->id,
                'created_by' => fake()->randomElement($sales)->id,
                'created_at' => now()->subDays(fake()->numberBetween(0, 180)),
            ]);
        }
        foreach ($customers as $customer) {
            $timeline->record('customer.created', $customer, 'Customer created: '.$customer->display_name);
        }
        $customers[0]->contacts()->create(['first_name' => 'Aziz', 'last_name' => 'Karimov', 'position' => 'Director', 'phone' => '+998901112233', 'is_primary' => true]);

        $leads = [];
        for ($i = 0; $i < 25; $i++) {
            $agent = fake()->randomElement($sales);
            $leads[] = $lead = Lead::factory()->create([
                'name' => $prefix.' lead '.fake()->name(),
                'assigned_to' => $agent->id,
                'created_by' => $agent->id,
                'customer_id' => fake()->boolean(30) ? fake()->randomElement($customers)->id : null,
                'created_at' => now()->subDays(fake()->numberBetween(0, 90)),
            ]);
            $timeline->record('lead.created', $lead, 'Lead created: '.$lead->name);
        }

        $pipeline = Pipeline::query()->with('stages')->where('is_default', true)->firstOrFail();
        for ($i = 0; $i < 22; $i++) {
            $stage = $pipeline->stages[$i % $pipeline->stages->count()];
            $agent = fake()->randomElement($sales);
            $created = now()->subDays(fake()->numberBetween(1, 120));
            $deal = Deal::factory()->create([
                'title' => $prefix.' deal: '.fake()->bs(),
                'customer_id' => fake()->randomElement($customers)->id,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'status' => $stage->type->value,
                'probability' => $stage->probability,
                'assigned_to' => $agent->id,
                'created_by' => $agent->id,
                'created_at' => $created,
                'won_at' => $stage->type->value === 'won' ? $created->copy()->addDays(fake()->numberBetween(1, 20)) : null,
                'lost_at' => $stage->type->value === 'lost' ? $created->copy()->addDays(fake()->numberBetween(1, 20)) : null,
            ]);
            $timeline->record('deal.created', $deal, 'Deal created: '.$deal->title, ['stage' => $stage->name]);
        }

        $types = ['task', 'call', 'meeting', 'email', 'task'];
        for ($i = 0; $i < 35; $i++) {
            $customer = fake()->randomElement($customers);
            $agent = fake()->randomElement($sales);
            $due = now()->addHours(fake()->numberBetween(-24 * 10, 24 * 10))->startOfHour();
            $completed = $due->isPast() && fake()->boolean(60);
            $activity = Activity::query()->create([
                'title' => fake()->randomElement(['Follow up', 'Send proposal to', 'Call', 'Meeting with', 'Demo for']).' '.$customer->display_name,
                'type' => $types[$i % count($types)],
                'due_at' => $due,
                'status' => $completed ? 'completed' : 'pending',
                'completed_at' => $completed ? $due : null,
                'priority' => fake()->randomElement(['low', 'normal', 'normal', 'high', 'urgent']),
                'assigned_to' => $agent->id,
                'customer_id' => $customer->id,
                'created_by' => $agent->id,
            ]);
            $timeline->record('activity.created', $activity, ucfirst($activity->type->value).' created: '.$activity->title);
        }
    }

    /**
     * 45 days of punches pushed through the real pipeline: fake device → raw events → processor → close day.
     *
     * @param  list<Employee>  $employees
     */
    private function attendance(Organization $organization, array $employees): void
    {
        $device = AttendanceDevice::query()->create([
            'name' => 'Demo terminal (simulated)', 'vendor' => 'fake', 'model' => 'Simulator', 'type' => 'face_terminal',
            'connection_mode' => 'direct', 'status' => 'active',
        ]);
        AttendanceDevice::query()->create([
            'name' => 'Main entrance DS-K1A340FWX', 'vendor' => 'hikvision', 'model' => 'DS-K1A340FWX', 'type' => 'face_terminal',
            'connection_mode' => 'direct', 'ip_address' => '192.168.1.64', 'port' => 80, 'protocol' => 'http', 'username' => 'admin',
            'encrypted_password' => 'change-me', 'status' => 'inactive',
            'settings' => ['timezone' => 'Asia/Tashkent'],
        ]);

        $processor = app(AttendanceProcessor::class);
        $tz = $organization->timezone;
        $today = CarbonImmutable::now($tz)->startOfDay();
        $events = [];

        foreach (CarbonPeriod::create($today->subDays(45), $today) as $date) {
            $date = CarbonImmutable::parse($date->format('Y-m-d'), $tz);
            foreach ($employees as $index => $employee) {
                if ($employee->status->value === 'on_leave' || $employee->hire_date->format('Y-m-d') > $date->format('Y-m-d')) {
                    continue;
                }
                $day = $processor->resolver()->daySchedule($employee, $date->format('Y-m-d'));
                if (! $day->expectsWork() || mt_rand(1, 100) <= 4) {
                    continue; // day off, or absent (~4%)
                }

                $start = $day->start ?? $date->setTime(9, 0);
                $end = $day->end ?? $date->setTime(18, 0);
                $roll = mt_rand(1, 100);
                $in = $start->addMinutes(match (true) {
                    $roll <= 8 => mt_rand(16, 75),   // clearly late
                    $roll <= 22 => mt_rand(6, 14),   // within / just over grace
                    default => -mt_rand(0, 25),      // on time
                })->addSeconds(mt_rand(0, 59));
                $isToday = $date->isSameDay($today);
                $out = $end->addMinutes(mt_rand(1, 100) <= 7 ? -mt_rand(20, 90) : mt_rand(-3, 50))->addSeconds(mt_rand(0, 59));

                $identifier = (string) (1000 + $index + 1);
                $events[] = new DeviceEvent($in->utc(), $identifier, type: DeviceEventType::CheckIn, externalId: 'seed-'.$employee->id.'-'.$date->format('Ymd').'-in');
                // Occasional double scan (deduplicated by the punch window).
                if (mt_rand(1, 100) <= 5) {
                    $events[] = new DeviceEvent($in->addSeconds(20)->utc(), $identifier, type: DeviceEventType::CheckIn, externalId: 'seed-'.$employee->id.'-'.$date->format('Ymd').'-dup');
                }
                if (! $isToday && mt_rand(1, 100) > 3) {
                    $events[] = new DeviceEvent($out->utc(), $identifier, type: DeviceEventType::CheckOut, externalId: 'seed-'.$employee->id.'-'.$date->format('Ymd').'-out');
                } elseif ($isToday && CarbonImmutable::now($tz)->greaterThan($out)) {
                    $events[] = new DeviceEvent($out->utc(), $identifier, type: DeviceEventType::CheckOut, externalId: 'seed-'.$employee->id.'-'.$date->format('Ymd').'-out');
                }
            }
        }

        // One punch from an identifier that no employee has: shows up as "unmatched".
        $events[] = new DeviceEvent($today->setTime(8, 40)->utc(), '9999', type: DeviceEventType::CheckIn, externalId: 'seed-unknown-1');
        // Only feed events that already happened.
        $events = array_filter($events, fn (DeviceEvent $e) => $e->time->lessThanOrEqualTo(CarbonImmutable::now()));

        app(RawEventIngestor::class)->store($device, $events);
        app(RawEventProcessor::class)->process($organization->id, $device->id);
        $device->forceFill(['last_sync_at' => now(), 'last_sync_status' => 'ok', 'last_event_at' => now()])->save();

        foreach (CarbonPeriod::create($today->subDays(45), $today->subDay()) as $date) {
            $processor->closeDay($organization, CarbonImmutable::parse($date->format('Y-m-d')));
        }
    }

    private function payroll(User $hr): void
    {
        $previous = now()->subMonthNoOverflow();
        // Penalties of closed months are reviewed; the current month stays pending for the demo approval flow.
        Penalty::query()->where('status', 'pending')->where('date', '<', now()->startOfMonth()->format('Y-m-d'))
            ->update(['status' => 'approved', 'approved_by' => $hr->id, 'approved_at' => now()]);
        $service = app(PayrollService::class);
        $period = $service->open($previous->year, $previous->month, $hr);
        $service->calculate($period, $hr);
    }
}
