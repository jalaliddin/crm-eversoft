<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Enums\LateMode;
use App\Enums\OrganizationStatus;
use App\Enums\ScheduleType;
use App\Enums\StageType;
use App\Enums\UserStatus;
use App\Models\Crm\Pipeline;
use App\Models\Hr\WorkSchedule;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\OrganizationSettings;
use App\Support\Permissions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a ready-to-use tenant: organization, system roles, owner, default sales pipeline
 * and a default work schedule. Values are starting points that the tenant edits afterwards.
 */
class OrganizationProvisioner
{
    public const DEFAULT_STAGES = [
        ['key' => 'new', 'name' => 'New', 'color' => 'gray', 'probability' => 10, 'type' => 'open'],
        ['key' => 'qualification', 'name' => 'Qualification', 'color' => 'blue', 'probability' => 25, 'type' => 'open'],
        ['key' => 'proposal', 'name' => 'Proposal', 'color' => 'violet', 'probability' => 50, 'type' => 'open'],
        ['key' => 'negotiation', 'name' => 'Negotiation', 'color' => 'orange', 'probability' => 75, 'type' => 'open'],
        ['key' => 'won', 'name' => 'Won', 'color' => 'green', 'probability' => 100, 'type' => 'won'],
        ['key' => 'lost', 'name' => 'Lost', 'color' => 'red', 'probability' => 0, 'type' => 'lost'],
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, slug?: ?string, domain?: ?string, timezone?: ?string, currency?: ?string, locale?: ?string, status?: ?string}  $data
     * @param  array{name: string, email: string, password: string}|null  $owner
     * @return array{organization: Organization, owner: ?User}
     */
    public function provision(array $data, ?array $owner = null): array
    {
        return DB::transaction(function () use ($data, $owner) {
            $organization = Organization::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'domain' => $data['domain'] ?? null,
                'timezone' => $data['timezone'] ?? config('app.default_tenant_timezone'),
                'currency' => $data['currency'] ?? 'UZS',
                'locale' => $data['locale'] ?? 'en',
                'status' => $data['status'] ?? OrganizationStatus::Active->value,
                'settings' => OrganizationSettings::defaults(),
            ]);

            return $this->tenant->run($organization, function () use ($organization, $owner) {
                $roles = $this->createRoles($organization);
                $this->createDefaultPipeline();
                $this->createDefaultSchedule($organization);

                $ownerUser = null;
                if ($owner !== null) {
                    $ownerUser = new User([
                        'name' => $owner['name'],
                        'email' => strtolower($owner['email']),
                        'password' => $owner['password'],
                        'status' => UserStatus::Active,
                    ]);
                    $ownerUser->organization_id = $organization->id;
                    $ownerUser->save();
                    $ownerUser->roles()->attach($roles['owner']->id);
                }

                $this->audit->log('organization.created', $organization, [], ['name' => $organization->name, 'slug' => $organization->slug], $organization->id);

                return ['organization' => $organization, 'owner' => $ownerUser];
            });
        });
    }

    /** @return array<string, Role> */
    public function createRoles(Organization $organization): array
    {
        $names = Permissions::defaultRoleNames();
        $roles = [];

        foreach (Permissions::defaultRoles() as $slug => $permissions) {
            // Tenant context is active here, so organization_id is filled by BelongsToOrganization.
            $role = Role::query()->create([
                'slug' => $slug,
                'name' => $names[$slug],
                'is_system' => true,
            ]);
            $role->syncPermissions($permissions);
            $roles[$slug] = $role;
        }

        return $roles;
    }

    public function createDefaultPipeline(): Pipeline
    {
        $pipeline = Pipeline::query()->create(['name' => 'Sales pipeline', 'is_default' => true, 'sort_order' => 0]);

        foreach (self::DEFAULT_STAGES as $index => $stage) {
            $pipeline->stages()->create([...$stage, 'type' => StageType::from($stage['type']), 'sort_order' => $index]);
        }

        return $pipeline;
    }

    public function createDefaultSchedule(Organization $organization): WorkSchedule
    {
        $schedule = WorkSchedule::query()->create([
            'name' => 'Standard (Mon–Fri 08:30–17:30)',
            'type' => ScheduleType::Fixed,
            'grace_minutes' => (int) $organization->setting('attendance.default_grace_minutes', 10),
            'early_leave_grace_minutes' => 0,
            'late_mode' => LateMode::AfterGrace,
            'overtime_enabled' => true,
            'overtime_threshold_minutes' => 15,
            'is_default' => true,
            'status' => 'active',
        ]);

        foreach (range(1, 7) as $day) {
            $working = $day <= 5;
            $schedule->days()->create([
                'day_of_week' => $day,
                'is_working_day' => $working,
                'start_time' => $working ? '08:30' : null,
                'end_time' => $working ? '17:30' : null,
                'break_start' => $working ? '13:00' : null,
                'break_end' => $working ? '14:00' : null,
            ]);
        }

        return $schedule;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $i = 1;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
