import {
  IconAddressBook, IconBriefcase, IconBuildingCommunity, IconCalendarStats, IconCalendarTime, IconCash, IconChartBar,
  IconClipboardList, IconClockHour4, IconCoin, IconDeviceDesktopAnalytics, IconFingerprint, IconHeartRateMonitor,
  IconHierarchy2, IconHistory, IconLayoutDashboard, IconPhoneCall, IconReceipt2, IconSettings, IconShieldLock,
  IconTags, IconTargetArrow, IconTimeline, IconUserCheck, IconUsers, IconUsersGroup, IconBeach, IconBell,
  IconBuildingStore, IconCalendarEvent,
  type Icon,
} from '@tabler/icons-react'

export interface NavItem {
  labelKey: string
  to: string
  icon: Icon
  permission?: string | string[]
  soon?: boolean
}

export interface NavSection {
  labelKey: string
  items: NavItem[]
}

export const tenantNavigation: NavSection[] = [
  {
    labelKey: '',
    items: [
      { labelKey: 'nav.dashboard', to: '/dashboard', icon: IconLayoutDashboard, permission: 'dashboard.view' },
      { labelKey: 'nav.myAttendance', to: '/me/attendance', icon: IconUserCheck },
    ],
  },
  {
    labelKey: 'nav.crm',
    items: [
      { labelKey: 'nav.customers', to: '/crm/customers', icon: IconAddressBook, permission: 'customers.view' },
      { labelKey: 'nav.leads', to: '/crm/leads', icon: IconTargetArrow, permission: 'leads.view' },
      { labelKey: 'nav.deals', to: '/crm/deals', icon: IconBriefcase, permission: 'deals.view' },
      { labelKey: 'nav.activities', to: '/crm/activities', icon: IconClipboardList, permission: 'activities.view' },
    ],
  },
  {
    labelKey: 'nav.hr',
    items: [
      { labelKey: 'nav.hrDashboard', to: '/hr', icon: IconDeviceDesktopAnalytics, permission: 'attendance.view' },
      { labelKey: 'nav.employees', to: '/hr/employees', icon: IconUsers, permission: 'employees.view' },
      { labelKey: 'nav.departments', to: '/hr/departments', icon: IconHierarchy2, permission: 'departments.view' },
      { labelKey: 'nav.positions', to: '/hr/positions', icon: IconUsersGroup, permission: 'positions.view' },
      { labelKey: 'nav.schedules', to: '/hr/schedules', icon: IconCalendarTime, permission: 'schedules.view' },
      { labelKey: 'nav.holidays', to: '/hr/holidays', icon: IconBeach, permission: 'schedules.view' },
      { labelKey: 'nav.attendance', to: '/hr/attendance', icon: IconClockHour4, permission: 'attendance.view' },
      { labelKey: 'nav.monitor', to: '/hr/attendance/monitor', icon: IconHeartRateMonitor, permission: 'attendance.view' },
      { labelKey: 'nav.calendar', to: '/hr/attendance/calendar', icon: IconCalendarStats, permission: 'attendance.view' },
      { labelKey: 'nav.penalties', to: '/hr/penalties', icon: IconReceipt2, permission: 'penalties.view' },
      { labelKey: 'nav.payroll', to: '/hr/payroll', icon: IconCash, permission: 'payroll.view' },
      { labelKey: 'nav.reports', to: '/hr/reports', icon: IconChartBar, permission: 'reports.hr' },
    ],
  },
  {
    labelKey: 'nav.settings',
    items: [
      { labelKey: 'nav.organization', to: '/settings/organization', icon: IconSettings, permission: 'settings.view' },
      { labelKey: 'nav.users', to: '/settings/users', icon: IconUsers, permission: 'users.view' },
      { labelKey: 'nav.roles', to: '/settings/roles', icon: IconShieldLock, permission: 'users.view' },
      { labelKey: 'nav.devices', to: '/settings/devices', icon: IconFingerprint, permission: 'devices.view' },
      { labelKey: 'nav.pipelines', to: '/settings/pipelines', icon: IconTimeline, permission: 'pipelines.manage' },
      { labelKey: 'nav.fields', to: '/settings/fields', icon: IconTags, permission: 'settings.manage' },
      { labelKey: 'nav.notifications', to: '/settings/notifications', icon: IconBell, permission: 'settings.manage' },
      { labelKey: 'nav.audit', to: '/settings/audit', icon: IconHistory, permission: 'audit.view' },
      { labelKey: 'nav.system', to: '/settings/system', icon: IconHeartRateMonitor, permission: 'system.health' },
    ],
  },
  {
    labelKey: 'nav.soon',
    items: [
      { labelKey: 'nav.finance', to: '/finance', icon: IconCoin, soon: true },
      { labelKey: 'nav.booking', to: '/booking', icon: IconCalendarEvent, soon: true },
      { labelKey: 'nav.telephony', to: '/telephony', icon: IconPhoneCall, soon: true },
    ],
  },
]

export const platformNavigation: NavSection[] = [
  {
    labelKey: 'nav.platform',
    items: [
      { labelKey: 'nav.organizations', to: '/platform', icon: IconBuildingCommunity },
      { labelKey: 'nav.system', to: '/platform/health', icon: IconHeartRateMonitor, permission: 'platform.health' },
    ],
  },
]

export { IconBuildingStore }
