import { lazy } from 'react'
import { Navigate } from 'react-router'
import { useAuth } from '@/features/auth/useAuth'

// Route-level code splitting: each section loads on first visit.
export const ForgotPasswordPage = lazy(() => import('@/features/auth/ForgotPasswordPage'))
export const ResetPasswordPage = lazy(() => import('@/features/auth/ResetPasswordPage'))
export const ProfilePage = lazy(() => import('@/features/auth/ProfilePage'))
export const DashboardPage = lazy(() => import('@/features/dashboard/DashboardPage'))
export const CustomersPage = lazy(() => import('@/features/crm/customers/CustomersPage'))
export const CustomerDetailPage = lazy(() => import('@/features/crm/customers/CustomerDetailPage'))
export const LeadsPage = lazy(() => import('@/features/crm/leads/LeadsPage'))
export const LeadDetailPage = lazy(() => import('@/features/crm/leads/LeadDetailPage'))
export const DealsPage = lazy(() => import('@/features/crm/deals/DealsPage'))
export const DealDetailPage = lazy(() => import('@/features/crm/deals/DealDetailPage'))
export const ActivitiesPage = lazy(() => import('@/features/crm/activities/ActivitiesPage'))
export const HrDashboardPage = lazy(() => import('@/features/hr/HrDashboardPage'))
export const EmployeesPage = lazy(() => import('@/features/hr/employees/EmployeesPage'))
export const EmployeeProfilePage = lazy(() => import('@/features/hr/employees/EmployeeProfilePage'))
export const DepartmentsPage = lazy(() => import('@/features/hr/structure/DepartmentsPage'))
export const PositionsPage = lazy(() => import('@/features/hr/structure/PositionsPage'))
export const HolidaysPage = lazy(() => import('@/features/hr/structure/HolidaysPage'))
export const SchedulesPage = lazy(() => import('@/features/hr/schedules/SchedulesPage'))
export const AttendancePage = lazy(() => import('@/features/hr/attendance/AttendancePage'))
export const MonitorPage = lazy(() => import('@/features/hr/attendance/MonitorPage'))
export const CalendarPage = lazy(() => import('@/features/hr/attendance/CalendarPage'))
export const MyAttendancePage = lazy(() => import('@/features/hr/attendance/MyAttendancePage'))
export const PenaltiesPage = lazy(() => import('@/features/hr/payroll/PenaltiesPage'))
export const PayrollPage = lazy(() => import('@/features/hr/payroll/PayrollPage'))
export const PayrollPeriodPage = lazy(() => import('@/features/hr/payroll/PayrollPeriodPage'))
export const ReportsPage = lazy(() => import('@/features/hr/reports/ReportsPage'))
export const OrganizationSettingsPage = lazy(() => import('@/features/settings/OrganizationSettingsPage'))
export const UsersPage = lazy(() => import('@/features/settings/UsersPage'))
export const RolesPage = lazy(() => import('@/features/settings/RolesPage'))
export const DevicesPage = lazy(() => import('@/features/settings/DevicesPage'))
export const PipelinesPage = lazy(() => import('@/features/settings/PipelinesPage'))
export const FieldsPage = lazy(() => import('@/features/settings/FieldsPage'))
export const NotificationSettingsPage = lazy(() => import('@/features/settings/NotificationSettingsPage'))
export const AuditLogPage = lazy(() => import('@/features/settings/AuditLogPage'))
export const SystemHealthPage = lazy(() => import('@/features/settings/SystemHealthPage'))
export const PlatformOrganizationsPage = lazy(() => import('@/features/platform/PlatformOrganizationsPage'))
export const ComingSoonPage = lazy(() => import('@/features/misc/ComingSoonPage'))
export const NotFoundPage = lazy(() => import('@/features/misc/NotFoundPage'))

/** "/" goes wherever the user can actually work. */
export function Home() {
  const { me, can } = useAuth()
  if (me?.is_platform_user) return <Navigate to="/platform" replace />
  if (can('dashboard.view')) return <Navigate to="/dashboard" replace />
  return <Navigate to="/me/attendance" replace />
}

