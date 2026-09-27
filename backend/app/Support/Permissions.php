<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Code-defined permission catalog. Roles are stored per tenant and reference these keys.
 * Adding a module (Finance, Booking, Telephony) means adding a group here.
 */
final class Permissions
{
    /** @var array<string, array<string, string>> group => [permission => label] */
    public const CATALOG = [
        'dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'crm' => [
            'crm.view_all' => 'See records assigned to other users',
            'customers.view' => 'View customers',
            'customers.create' => 'Create customers',
            'customers.update' => 'Update customers',
            'customers.delete' => 'Delete customers',
            'customers.export' => 'Export customers',
            'leads.view' => 'View leads',
            'leads.create' => 'Create leads',
            'leads.update' => 'Update leads',
            'leads.delete' => 'Delete leads',
            'leads.assign' => 'Assign leads',
            'deals.view' => 'View deals',
            'deals.create' => 'Create deals',
            'deals.update' => 'Update deals',
            'deals.delete' => 'Delete deals',
            'activities.view' => 'View activities',
            'activities.create' => 'Create activities',
            'activities.update' => 'Update activities',
            'activities.delete' => 'Delete activities',
            'pipelines.manage' => 'Manage pipelines',
            'reports.crm' => 'CRM reports',
        ],
        'hr' => [
            'employees.view' => 'View employees',
            'employees.create' => 'Create employees',
            'employees.update' => 'Update employees',
            'employees.delete' => 'Delete employees',
            'departments.view' => 'View departments',
            'departments.manage' => 'Manage departments',
            'positions.view' => 'View positions',
            'positions.manage' => 'Manage positions',
            'schedules.view' => 'View work schedules',
            'schedules.manage' => 'Manage work schedules',
            'reports.hr' => 'HR reports',
        ],
        'attendance' => [
            'attendance.view' => 'View attendance of all employees',
            'attendance.view_own' => 'View own attendance',
            'attendance.manage' => 'Create attendance records',
            'attendance.correct' => 'Correct attendance records',
            'attendance.export' => 'Export attendance',
            'devices.view' => 'View attendance devices',
            'devices.manage' => 'Manage attendance devices',
            'devices.sync' => 'Synchronize attendance devices',
        ],
        'payroll' => [
            'penalties.view' => 'View penalties',
            'penalties.manage' => 'Manage penalties and rules',
            'penalties.approve' => 'Approve penalties',
            'payroll.view' => 'View payroll',
            'payroll.calculate' => 'Calculate payroll',
            'payroll.approve' => 'Approve payroll',
        ],
        'administration' => [
            'users.view' => 'View users',
            'users.manage' => 'Manage users',
            'roles.manage' => 'Manage roles and permissions',
            'settings.view' => 'View organization settings',
            'settings.manage' => 'Manage organization settings',
            'audit.view' => 'View audit logs',
            'system.health' => 'View system health',
        ],
    ];

    /** Platform-level abilities (not assignable to tenant roles). */
    public const PLATFORM = [
        'platform_super_admin' => ['platform.organizations.view', 'platform.organizations.manage', 'platform.users.manage', 'platform.health'],
        'platform_support' => ['platform.organizations.view', 'platform.health'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_map(array_keys(...), array_values(self::CATALOG)));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /** @return array<string, list<string>> slug => permissions */
    public static function defaultRoles(): array
    {
        $crmFull = [
            'crm.view_all', 'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.export',
            'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.assign',
            'deals.view', 'deals.create', 'deals.update', 'deals.delete',
            'activities.view', 'activities.create', 'activities.update', 'activities.delete',
            'pipelines.manage', 'reports.crm',
        ];

        return [
            'owner' => self::all(),
            'admin' => self::all(),
            'hr_manager' => [
                'dashboard.view', 'users.view',
                'employees.view', 'employees.create', 'employees.update', 'employees.delete',
                'departments.view', 'departments.manage', 'positions.view', 'positions.manage',
                'schedules.view', 'schedules.manage', 'reports.hr',
                'attendance.view', 'attendance.view_own', 'attendance.manage', 'attendance.correct', 'attendance.export',
                'devices.view', 'devices.sync',
                'penalties.view', 'penalties.manage', 'penalties.approve',
                'payroll.view', 'payroll.calculate',
            ],
            'manager' => [
                'dashboard.view', 'crm.view_all', 'customers.view', 'leads.view', 'deals.view', 'activities.view',
                'activities.create', 'activities.update', 'reports.crm',
                'employees.view', 'departments.view', 'positions.view', 'schedules.view',
                'attendance.view', 'attendance.view_own', 'reports.hr', 'users.view',
            ],
            'sales_manager' => [...$crmFull, 'dashboard.view', 'users.view', 'attendance.view_own'],
            'sales_agent' => [
                'dashboard.view', 'customers.view', 'customers.create', 'customers.update',
                'leads.view', 'leads.create', 'leads.update', 'deals.view', 'deals.create', 'deals.update',
                'activities.view', 'activities.create', 'activities.update', 'activities.delete', 'attendance.view_own',
            ],
            'employee' => ['dashboard.view', 'activities.view', 'activities.create', 'activities.update', 'attendance.view_own'],
        ];
    }

    /** @return array<string, string> */
    public static function defaultRoleNames(): array
    {
        return [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'hr_manager' => 'HR Manager',
            'manager' => 'Manager',
            'sales_manager' => 'Sales Manager',
            'sales_agent' => 'Sales Agent',
            'employee' => 'Employee',
        ];
    }
}
