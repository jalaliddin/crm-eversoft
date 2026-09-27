<?php

use App\Http\Controllers\Api\V1\Attendance\AttendanceController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceDeviceController;
use App\Http\Controllers\Api\V1\Attendance\DeviceBridgeController;
use App\Http\Controllers\Api\V1\Attendance\RawEventController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Crm\ActivityController;
use App\Http\Controllers\Api\V1\Crm\CustomerContactController;
use App\Http\Controllers\Api\V1\Crm\CustomerController;
use App\Http\Controllers\Api\V1\Crm\DealController;
use App\Http\Controllers\Api\V1\Crm\DocumentController;
use App\Http\Controllers\Api\V1\Crm\LeadController;
use App\Http\Controllers\Api\V1\Crm\NoteController;
use App\Http\Controllers\Api\V1\Crm\PipelineController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Hr\DepartmentController;
use App\Http\Controllers\Api\V1\Hr\EmployeeController;
use App\Http\Controllers\Api\V1\Hr\HolidayController;
use App\Http\Controllers\Api\V1\Hr\PositionController;
use App\Http\Controllers\Api\V1\Hr\WorkScheduleController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Payroll\PayrollController;
use App\Http\Controllers\Api\V1\Payroll\PenaltyController;
use App\Http\Controllers\Api\V1\Payroll\PenaltyRuleController;
use App\Http\Controllers\Api\V1\Platform\PlatformOrganizationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\Settings\AuditLogController;
use App\Http\Controllers\Api\V1\Settings\CustomFieldController;
use App\Http\Controllers\Api\V1\Settings\OrganizationController;
use App\Http\Controllers\Api\V1\Settings\RoleController;
use App\Http\Controllers\Api\V1\Settings\TagController;
use App\Http\Controllers\Api\V1\Settings\UserController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use Illuminate\Support\Facades\Route;

/*
| Every tenant endpoint passes: auth:sanctum → tenant (organization from the user) → can:<permission>
| → FormRequest validation → policy / tenant-scoped query.
*/

Route::prefix('v1')->group(function (): void {
    Route::get('ping', [SystemHealthController::class, 'ping']);

    // Public authentication
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    });

    // On-site bridge agents (device token, no user session)
    Route::post('device-bridge/events', [DeviceBridgeController::class, 'events'])->middleware('throttle:device-ingest');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::prefix('profile')->group(function (): void {
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('avatar', [ProfileController::class, 'avatar'])->middleware('throttle:uploads');
            Route::delete('avatar', [ProfileController::class, 'deleteAvatar']);
            Route::put('password', [ProfileController::class, 'password']);
            Route::get('sessions', [ProfileController::class, 'sessions']);
            Route::delete('sessions', [ProfileController::class, 'revokeOtherSessions']);
            Route::delete('sessions/{id}', [ProfileController::class, 'revokeSession'])->whereNumber('id');
        });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

        // ───────────── Platform administration ─────────────
        Route::prefix('platform')->middleware('platform')->group(function (): void {
            Route::get('stats', [PlatformOrganizationController::class, 'stats']);
            Route::get('health', SystemHealthController::class)->middleware('can:platform.health');
            Route::get('organizations', [PlatformOrganizationController::class, 'index'])->middleware('can:platform.organizations.view');
            Route::post('organizations', [PlatformOrganizationController::class, 'store']);
            Route::get('organizations/{organization}', [PlatformOrganizationController::class, 'show'])->middleware('can:platform.organizations.view');
            Route::put('organizations/{organization}', [PlatformOrganizationController::class, 'update']);
        });

        // ───────────── Tenant ─────────────
        Route::middleware('tenant')->group(function (): void {
            Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view');
            Route::get('search', SearchController::class);
            Route::get('meta/options', [MetaController::class, 'options']);
            Route::get('meta/users', [MetaController::class, 'users']);

            // CRM
            Route::get('customers', [CustomerController::class, 'index']);
            Route::post('customers', [CustomerController::class, 'store']);
            Route::get('customers/{customer}', [CustomerController::class, 'show']);
            Route::put('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
            Route::get('customers/{customer}/timeline', [CustomerController::class, 'timeline']);
            Route::get('customers/{customer}/contacts', [CustomerContactController::class, 'index']);
            Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store']);
            Route::put('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update']);
            Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy']);

            Route::get('leads-board', [LeadController::class, 'board']);
            Route::get('leads', [LeadController::class, 'index']);
            Route::post('leads', [LeadController::class, 'store']);
            Route::get('leads/{lead}', [LeadController::class, 'show']);
            Route::put('leads/{lead}', [LeadController::class, 'update']);
            Route::delete('leads/{lead}', [LeadController::class, 'destroy']);
            Route::post('leads/{lead}/move', [LeadController::class, 'move']);
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);
            Route::get('leads/{lead}/timeline', [LeadController::class, 'timeline']);

            Route::get('deals-board', [DealController::class, 'board']);
            Route::get('deals', [DealController::class, 'index']);
            Route::post('deals', [DealController::class, 'store']);
            Route::get('deals/{deal}', [DealController::class, 'show']);
            Route::put('deals/{deal}', [DealController::class, 'update']);
            Route::delete('deals/{deal}', [DealController::class, 'destroy']);
            Route::post('deals/{deal}/move', [DealController::class, 'move']);
            Route::get('deals/{deal}/timeline', [DealController::class, 'timeline']);

            Route::get('pipelines', [PipelineController::class, 'index'])->middleware('can:deals.view');
            Route::middleware('can:pipelines.manage')->group(function (): void {
                Route::post('pipelines', [PipelineController::class, 'store']);
                Route::put('pipelines/{pipeline}', [PipelineController::class, 'update']);
                Route::delete('pipelines/{pipeline}', [PipelineController::class, 'destroy']);
            });

            Route::get('activities', [ActivityController::class, 'index']);
            Route::post('activities', [ActivityController::class, 'store']);
            Route::get('activities/{activity}', [ActivityController::class, 'show']);
            Route::put('activities/{activity}', [ActivityController::class, 'update']);
            Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);
            Route::post('activities/{activity}/complete', [ActivityController::class, 'complete']);

            Route::get('{type}/{id}/notes', [NoteController::class, 'index'])->whereIn('type', ['customers', 'leads', 'deals', 'employees'])->whereNumber('id');
            Route::post('{type}/{id}/notes', [NoteController::class, 'store'])->whereIn('type', ['customers', 'leads', 'deals', 'employees'])->whereNumber('id');
            Route::put('notes/{note}', [NoteController::class, 'update']);
            Route::delete('notes/{note}', [NoteController::class, 'destroy']);

            Route::get('{type}/{id}/documents', [DocumentController::class, 'index'])->whereIn('type', ['customers', 'leads', 'deals', 'employees'])->whereNumber('id');
            Route::post('{type}/{id}/documents', [DocumentController::class, 'store'])->whereIn('type', ['customers', 'leads', 'deals', 'employees'])->whereNumber('id')->middleware('throttle:uploads');
            Route::get('documents/{document}/download', [DocumentController::class, 'download']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

            Route::get('tags', [TagController::class, 'index']);
            Route::middleware('can:settings.manage')->group(function (): void {
                Route::post('tags', [TagController::class, 'store']);
                Route::put('tags/{tag}', [TagController::class, 'update']);
                Route::delete('tags/{tag}', [TagController::class, 'destroy']);
                Route::post('custom-fields', [CustomFieldController::class, 'store']);
                Route::put('custom-fields/{customField}', [CustomFieldController::class, 'update']);
                Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy']);
            });
            Route::get('custom-fields', [CustomFieldController::class, 'index']);

            // HR
            Route::get('departments', [DepartmentController::class, 'index'])->middleware('can:departments.view');
            Route::get('departments/{department}', [DepartmentController::class, 'show'])->middleware('can:departments.view');
            Route::middleware('can:departments.manage')->group(function (): void {
                Route::post('departments', [DepartmentController::class, 'store']);
                Route::put('departments/{department}', [DepartmentController::class, 'update']);
                Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
            });

            Route::get('positions', [PositionController::class, 'index'])->middleware('can:positions.view');
            Route::get('positions/{position}', [PositionController::class, 'show'])->middleware('can:positions.view');
            Route::middleware('can:positions.manage')->group(function (): void {
                Route::post('positions', [PositionController::class, 'store']);
                Route::put('positions/{position}', [PositionController::class, 'update']);
                Route::delete('positions/{position}', [PositionController::class, 'destroy']);
            });

            Route::get('employees', [EmployeeController::class, 'index']);
            Route::post('employees', [EmployeeController::class, 'store']);
            Route::get('employees/{employee}', [EmployeeController::class, 'show']);
            Route::put('employees/{employee}', [EmployeeController::class, 'update']);
            Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
            Route::post('employees/{employee}/photo', [EmployeeController::class, 'photo'])->middleware('throttle:uploads');
            Route::post('employees/{employee}/schedule', [EmployeeController::class, 'assignSchedule']);
            Route::get('employees/{employee}/penalties', [EmployeeController::class, 'penalties']);
            Route::get('employees/{employee}/payroll', [EmployeeController::class, 'payroll']);
            Route::get('employees/{employee}/identifiers', [EmployeeController::class, 'identifiers']);
            Route::post('employees/{employee}/identifiers', [EmployeeController::class, 'storeIdentifier']);
            Route::put('employees/{employee}/identifiers/{identifier}', [EmployeeController::class, 'updateIdentifier']);
            Route::delete('employees/{employee}/identifiers/{identifier}', [EmployeeController::class, 'destroyIdentifier']);

            Route::get('work-schedules', [WorkScheduleController::class, 'index'])->middleware('can:schedules.view');
            Route::get('work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])->middleware('can:schedules.view');
            Route::middleware('can:schedules.manage')->group(function (): void {
                Route::post('work-schedules', [WorkScheduleController::class, 'store']);
                Route::put('work-schedules/{workSchedule}', [WorkScheduleController::class, 'update']);
                Route::delete('work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy']);
            });

            Route::get('holidays', [HolidayController::class, 'index'])->middleware('can:schedules.view');
            Route::middleware('can:schedules.manage')->group(function (): void {
                Route::post('holidays', [HolidayController::class, 'store']);
                Route::put('holidays/{holiday}', [HolidayController::class, 'update']);
                Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy']);
            });

            // Attendance
            Route::get('attendance', [AttendanceController::class, 'index']);
            Route::post('attendance', [AttendanceController::class, 'store']);
            Route::get('attendance/dashboard', [AttendanceController::class, 'dashboard']);
            Route::get('attendance/monitor', [AttendanceController::class, 'monitor']);
            Route::get('attendance/calendar', [AttendanceController::class, 'calendar']);
            Route::get('attendance/expected', [AttendanceController::class, 'expected']);
            Route::post('attendance/recalculate', [AttendanceController::class, 'recalculate']);
            Route::get('attendance/{attendance}', [AttendanceController::class, 'show'])->whereNumber('attendance');
            Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->whereNumber('attendance');
            Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])->whereNumber('attendance');

            Route::get('attendance-devices', [AttendanceDeviceController::class, 'index'])->middleware('can:devices.view');
            Route::get('attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'show'])->middleware('can:devices.view');
            Route::post('attendance-devices/{attendanceDevice}/test', [AttendanceDeviceController::class, 'test'])->middleware('can:devices.sync');
            Route::post('attendance-devices/{attendanceDevice}/sync', [AttendanceDeviceController::class, 'sync'])->middleware('can:devices.sync');
            Route::middleware('can:devices.manage')->group(function (): void {
                Route::post('attendance-devices', [AttendanceDeviceController::class, 'store']);
                Route::put('attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'update']);
                Route::delete('attendance-devices/{attendanceDevice}', [AttendanceDeviceController::class, 'destroy']);
                Route::post('attendance-devices/{attendanceDevice}/push-employees', [AttendanceDeviceController::class, 'pushEmployees']);
                Route::post('attendance-devices/{attendanceDevice}/bridge-token', [AttendanceDeviceController::class, 'bridgeToken']);
            });
            Route::get('attendance-raw-events', [RawEventController::class, 'index'])->middleware('can:devices.view');
            Route::get('attendance-raw-events/unmatched', [RawEventController::class, 'unmatchedIdentifiers'])->middleware('can:devices.view');
            Route::post('attendance-raw-events/reprocess', [RawEventController::class, 'reprocess'])->middleware('can:devices.sync');

            // Penalties & payroll
            Route::get('penalty-rules', [PenaltyRuleController::class, 'index'])->middleware('can:penalties.view');
            Route::middleware('can:penalties.manage')->group(function (): void {
                Route::post('penalty-rules', [PenaltyRuleController::class, 'store']);
                Route::put('penalty-rules/{penaltyRule}', [PenaltyRuleController::class, 'update']);
                Route::delete('penalty-rules/{penaltyRule}', [PenaltyRuleController::class, 'destroy']);
                Route::post('penalties', [PenaltyController::class, 'store']);
                Route::post('penalties/{penalty}/cancel', [PenaltyController::class, 'cancel']);
            });
            Route::get('penalties', [PenaltyController::class, 'index'])->middleware('can:penalties.view');
            Route::middleware('can:penalties.approve')->group(function (): void {
                Route::post('penalties/bulk-approve', [PenaltyController::class, 'bulkApprove']);
                Route::post('penalties/{penalty}/approve', [PenaltyController::class, 'approve']);
                Route::post('penalties/{penalty}/reject', [PenaltyController::class, 'reject']);
            });

            Route::middleware('can:payroll.view')->group(function (): void {
                Route::get('payroll', [PayrollController::class, 'index']);
                Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->whereNumber('payroll');
                Route::get('payroll/{payroll}/records', [PayrollController::class, 'records'])->whereNumber('payroll');
            });
            Route::middleware('can:payroll.calculate')->group(function (): void {
                Route::post('payroll', [PayrollController::class, 'store']);
                Route::post('payroll/calculate', [PayrollController::class, 'calculate']);
                Route::put('payroll-records/{payrollRecord}', [PayrollController::class, 'updateRecord']);
                Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])->whereNumber('payroll');
            });
            Route::middleware('can:payroll.approve')->group(function (): void {
                Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve']);
                Route::post('payroll/{payroll}/pay', [PayrollController::class, 'pay']);
                Route::post('payroll/{payroll}/reopen', [PayrollController::class, 'reopen']);
            });

            // Reports
            Route::get('reports/attendance/daily', [ReportController::class, 'dailyAttendance'])->middleware('can:reports.hr');
            Route::get('reports/attendance/monthly', [ReportController::class, 'monthlyAttendance'])->middleware('can:reports.hr');
            Route::get('reports/payroll/{payroll}', [ReportController::class, 'payroll'])->middleware('can:payroll.view');

            // Administration
            Route::get('users', [UserController::class, 'index'])->middleware('can:users.view');
            Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:users.view')->whereNumber('user');
            Route::middleware('can:users.manage')->group(function (): void {
                Route::post('users', [UserController::class, 'store']);
                Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user');
                Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user');
            });

            Route::get('permissions', [RoleController::class, 'permissions'])->middleware('can:users.view');
            Route::get('roles', [RoleController::class, 'index'])->middleware('can:users.view');
            Route::middleware('can:roles.manage')->group(function (): void {
                Route::post('roles', [RoleController::class, 'store']);
                Route::put('roles/{role}', [RoleController::class, 'update']);
                Route::delete('roles/{role}', [RoleController::class, 'destroy']);
            });

            Route::get('settings/organization', [OrganizationController::class, 'show']);
            Route::middleware('can:settings.manage')->group(function (): void {
                Route::put('settings/organization', [OrganizationController::class, 'update']);
                Route::post('settings/organization/logo', [OrganizationController::class, 'logo'])->middleware('throttle:uploads');
            });

            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view');
            Route::get('system/health', SystemHealthController::class)->middleware('can:system.health');
        });
    });
});
