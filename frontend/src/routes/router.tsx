import { createBrowserRouter, Navigate } from 'react-router'
import { AppLayout } from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'
import { RedirectIfAuthenticated, RequireAuth, RequirePermission, RequirePlatform, RequireTenant } from '@/features/auth/guards'
import LoginPage from '@/features/auth/LoginPage'
import {
  ForgotPasswordPage, ResetPasswordPage, ProfilePage, DashboardPage, CustomersPage, CustomerDetailPage, LeadsPage, LeadDetailPage, DealsPage, DealDetailPage, ActivitiesPage, HrDashboardPage, EmployeesPage, EmployeeProfilePage, DepartmentsPage, PositionsPage, HolidaysPage, SchedulesPage, AttendancePage, MonitorPage, CalendarPage, MyAttendancePage, PenaltiesPage, PayrollPage, PayrollPeriodPage, ReportsPage, OrganizationSettingsPage, UsersPage, RolesPage, DevicesPage, PipelinesPage, FieldsPage, NotificationSettingsPage, AuditLogPage, SystemHealthPage, PlatformOrganizationsPage, ComingSoonPage, NotFoundPage, Home,
} from './pages'

export const router = createBrowserRouter([
  {
    element: <RedirectIfAuthenticated />,
    children: [{ element: <AuthLayout />, children: [{ path: '/login', element: <LoginPage /> }, { path: '/forgot-password', element: <ForgotPasswordPage /> }] }],
  },
  { element: <AuthLayout />, children: [{ path: '/reset-password', element: <ResetPasswordPage /> }] },
  {
    element: <RequireAuth />,
    children: [
      { path: '/', element: <Home /> },
      {
        element: <RequirePlatform />,
        children: [{
          element: <AppLayout platform />,
          children: [
            { path: '/platform', element: <PlatformOrganizationsPage /> },
            { path: '/platform/health', element: <RequirePermission permission={'platform.health'}><SystemHealthPage platform /></RequirePermission> },
            { path: '/platform/profile', element: <ProfilePage /> },
          ],
        }],
      },
      {
        element: <RequireTenant />,
        children: [{
          element: <AppLayout />,
          children: [
            { path: '/dashboard', element: <RequirePermission permission={'dashboard.view'}><DashboardPage /></RequirePermission> },
            { path: '/profile', element: <ProfilePage /> },
            { path: '/me/attendance', element: <RequirePermission permission={['attendance.view_own', 'attendance.view']}><MyAttendancePage /></RequirePermission> },

            { path: '/crm/customers', element: <RequirePermission permission={'customers.view'}><CustomersPage /></RequirePermission> },
            { path: '/crm/customers/:id', element: <RequirePermission permission={'customers.view'}><CustomerDetailPage /></RequirePermission> },
            { path: '/crm/leads', element: <RequirePermission permission={'leads.view'}><LeadsPage /></RequirePermission> },
            { path: '/crm/leads/:id', element: <RequirePermission permission={'leads.view'}><LeadDetailPage /></RequirePermission> },
            { path: '/crm/deals', element: <RequirePermission permission={'deals.view'}><DealsPage /></RequirePermission> },
            { path: '/crm/deals/:id', element: <RequirePermission permission={'deals.view'}><DealDetailPage /></RequirePermission> },
            { path: '/crm/activities', element: <RequirePermission permission={'activities.view'}><ActivitiesPage /></RequirePermission> },

            { path: '/hr', element: <RequirePermission permission={'attendance.view'}><HrDashboardPage /></RequirePermission> },
            { path: '/hr/employees', element: <RequirePermission permission={'employees.view'}><EmployeesPage /></RequirePermission> },
            { path: '/hr/employees/:id', element: <EmployeeProfilePage /> },
            { path: '/hr/departments', element: <RequirePermission permission={'departments.view'}><DepartmentsPage /></RequirePermission> },
            { path: '/hr/positions', element: <RequirePermission permission={'positions.view'}><PositionsPage /></RequirePermission> },
            { path: '/hr/schedules', element: <RequirePermission permission={'schedules.view'}><SchedulesPage /></RequirePermission> },
            { path: '/hr/holidays', element: <RequirePermission permission={'schedules.view'}><HolidaysPage /></RequirePermission> },
            { path: '/hr/attendance', element: <RequirePermission permission={'attendance.view'}><AttendancePage /></RequirePermission> },
            { path: '/hr/attendance/monitor', element: <RequirePermission permission={'attendance.view'}><MonitorPage /></RequirePermission> },
            { path: '/hr/attendance/calendar', element: <RequirePermission permission={'attendance.view'}><CalendarPage /></RequirePermission> },
            { path: '/hr/penalties', element: <RequirePermission permission={'penalties.view'}><PenaltiesPage /></RequirePermission> },
            { path: '/hr/payroll', element: <RequirePermission permission={'payroll.view'}><PayrollPage /></RequirePermission> },
            { path: '/hr/payroll/:id', element: <RequirePermission permission={'payroll.view'}><PayrollPeriodPage /></RequirePermission> },
            { path: '/hr/reports', element: <RequirePermission permission={'reports.hr'}><ReportsPage /></RequirePermission> },

            { path: '/settings', element: <Navigate to="/settings/organization" replace /> },
            { path: '/settings/organization', element: <RequirePermission permission={'settings.view'}><OrganizationSettingsPage /></RequirePermission> },
            { path: '/settings/users', element: <RequirePermission permission={'users.view'}><UsersPage /></RequirePermission> },
            { path: '/settings/roles', element: <RequirePermission permission={'users.view'}><RolesPage /></RequirePermission> },
            { path: '/settings/devices', element: <RequirePermission permission={'devices.view'}><DevicesPage /></RequirePermission> },
            { path: '/settings/pipelines', element: <RequirePermission permission={'pipelines.manage'}><PipelinesPage /></RequirePermission> },
            { path: '/settings/fields', element: <RequirePermission permission={'settings.manage'}><FieldsPage /></RequirePermission> },
            { path: '/settings/notifications', element: <RequirePermission permission={'settings.manage'}><NotificationSettingsPage /></RequirePermission> },
            { path: '/settings/audit', element: <RequirePermission permission={'audit.view'}><AuditLogPage /></RequirePermission> },
            { path: '/settings/system', element: <RequirePermission permission={'system.health'}><SystemHealthPage /></RequirePermission> },

            { path: '/finance', element: <ComingSoonPage module="finance" /> },
            { path: '/booking', element: <ComingSoonPage module="booking" /> },
            { path: '/telephony', element: <ComingSoonPage module="telephony" /> },
            { path: '*', element: <NotFoundPage /> },
          ],
        }],
      },
    ],
  },
])
