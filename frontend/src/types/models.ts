export interface Ref {
  id: number
  name: string
}

export interface Tag {
  id: number
  name: string
  color: string
}

export interface OrganizationSettings {
  date_format: string
  time_format: string
  work_week: number[]
  attendance: {
    default_grace_minutes: number
    duplicate_punch_window_seconds: number
    auto_mark_absent: boolean
    monitor_refresh_seconds: number
  }
  payroll: {
    monthly_mode: 'fixed' | 'prorated'
    overtime_pay_enabled: boolean
    overtime_rate_multiplier: number
    count_incomplete_as_worked: boolean
  }
  penalties: { auto_approve: boolean }
  notifications: Record<string, string[]>
}

export interface Organization {
  id: number
  name: string
  slug: string
  domain: string | null
  logo_url: string | null
  timezone: string
  currency: string
  locale: string
  status: 'active' | 'trial' | 'suspended'
  settings: OrganizationSettings
  users_count?: number
  created_at: string
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar_url: string | null
  locale: string | null
  status: 'active' | 'inactive'
  platform_role: string | null
  roles?: { id: number; slug: string; name: string }[]
  employee_id?: number | null
  last_login_at: string | null
  created_at: string
}

export interface Me {
  user: User
  organization: Organization | null
  permissions: string[]
  is_platform_user: boolean
}

export interface Role {
  id: number
  slug: string
  name: string
  description: string | null
  is_system: boolean
  permissions: string[]
  users_count?: number
}

export interface Customer {
  id: number
  type: 'individual' | 'company'
  display_name: string
  first_name: string | null
  last_name: string | null
  company_name: string | null
  email: string | null
  phone: string | null
  secondary_phone: string | null
  website: string | null
  address: string | null
  city: string | null
  country: string | null
  source: string | null
  status: 'active' | 'inactive' | 'blocked'
  notes: string | null
  custom_fields: Record<string, unknown>
  owner: Ref | null
  owner_id: number | null
  tags?: Tag[]
  leads_count?: number
  deals_count?: number
  open_deals_value?: number
  created_by?: Ref | null
  created_at: string
  updated_at: string
}

export interface CustomerContact {
  id: number
  customer_id: number
  first_name: string
  last_name: string | null
  position: string | null
  email: string | null
  phone: string | null
  is_primary: boolean
}

export type LeadStatus = 'new' | 'contacted' | 'qualified' | 'proposal' | 'negotiation' | 'converted' | 'lost'

export interface Lead {
  id: number
  name: string
  company_name: string | null
  phone: string | null
  email: string | null
  source: string | null
  status: LeadStatus
  expected_value: number | null
  description: string | null
  lost_reason: string | null
  position: number
  custom_fields: Record<string, unknown>
  customer_id: number | null
  customer?: { id: number; display_name: string } | null
  assigned_to: number | null
  assignee: Ref | null
  tags?: Tag[]
  converted_at: string | null
  created_at: string
  updated_at: string
}

export interface PipelineStage {
  id: number
  key: string
  name: string
  color: string
  probability: number
  type: 'open' | 'won' | 'lost'
  sort_order: number
}

export interface Pipeline {
  id: number
  name: string
  is_default: boolean
  sort_order: number
  stages: PipelineStage[]
  deals_count?: number
}

export interface Deal {
  id: number
  title: string
  value: number
  currency: string
  status: 'open' | 'won' | 'lost'
  probability: number
  expected_close_date: string | null
  description: string | null
  lost_reason: string | null
  position: number
  custom_fields: Record<string, unknown>
  pipeline_id: number
  pipeline_stage_id: number
  stage?: { id: number; name: string; key: string; color: string; type: string }
  customer_id: number | null
  customer?: { id: number; display_name: string } | null
  lead_id: number | null
  assigned_to: number | null
  assignee: Ref | null
  tags?: Tag[]
  won_at: string | null
  lost_at: string | null
  created_at: string
  updated_at: string
}

export interface Activity {
  id: number
  title: string
  type: 'task' | 'call' | 'meeting' | 'email' | 'note'
  description: string | null
  due_at: string | null
  completed_at: string | null
  duration_minutes: number | null
  priority: 'low' | 'normal' | 'high' | 'urgent'
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
  is_overdue: boolean
  assigned_to: number | null
  assignee: Ref | null
  customer_id: number | null
  customer?: { id: number; display_name: string } | null
  lead_id: number | null
  lead?: { id: number; name: string } | null
  deal_id: number | null
  deal?: { id: number; title: string } | null
  created_by?: Ref | null
  created_at: string
}

export interface TimelineEvent {
  id: number
  event: string
  description: string
  properties: Record<string, unknown>
  subject_type: string | null
  subject_id: number | null
  causer: Ref | null
  occurred_at: string
}

export interface Note {
  id: number
  body: string
  author: Ref | null
  created_by: number | null
  created_at: string
  updated_at: string
}

export interface DocumentFile {
  id: number
  original_name: string
  mime_type: string
  size: number
  uploader: Ref | null
  download_url: string
  created_at: string
}

export interface CustomFieldDefinition {
  id: number
  entity_type: 'customer' | 'lead' | 'deal' | 'employee'
  key: string
  label: string
  field_type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select'
  options: { value: string; label: string }[]
  is_required: boolean
  sort_order: number
}

export interface Department {
  id: number
  name: string
  code: string | null
  description: string | null
  status: 'active' | 'inactive'
  parent_id: number | null
  parent?: { id: number; name: string } | null
  manager_id: number | null
  manager?: { id: number; full_name: string } | null
  employees_count?: number
}

export interface Position {
  id: number
  name: string
  code: string | null
  description: string | null
  status: 'active' | 'inactive'
  department_id: number | null
  department?: { id: number; name: string } | null
  employees_count?: number
}

export interface DeviceIdentifier {
  id: number
  employee_id: number
  employee?: { id: number; full_name: string } | null
  device_id: number | null
  device?: { id: number; name: string } | null
  identifier: string
  card_number: string | null
  biometric_identifier: string | null
  status: 'active' | 'inactive'
}

export interface Employee {
  id: number
  employee_number: string
  full_name: string
  first_name: string
  last_name: string
  middle_name: string | null
  phone: string | null
  email: string | null
  birth_date: string | null
  gender: 'male' | 'female' | null
  address: string | null
  department_id: number | null
  department?: { id: number; name: string } | null
  position_id: number | null
  position?: { id: number; name: string } | null
  manager_id: number | null
  manager?: { id: number; full_name: string } | null
  user_id: number | null
  user?: { id: number; name: string; email: string } | null
  employment_type: 'full_time' | 'part_time' | 'contract' | 'temporary'
  hire_date: string | null
  termination_date: string | null
  salary_type: 'monthly' | 'daily' | 'hourly'
  salary_amount: number | null
  status: 'active' | 'inactive' | 'terminated' | 'on_leave'
  photo_url: string | null
  notes: string | null
  custom_fields: Record<string, unknown>
  work_schedule?: { id: number; name: string; effective_from: string }
  schedule_assignments?: { id: number; work_schedule_id: number; name: string; effective_from: string; effective_to: string | null }[]
  device_identifiers?: DeviceIdentifier[]
  created_at: string
}

export interface ScheduleDay {
  day_of_week: number
  is_working_day: boolean
  start_time: string | null
  end_time: string | null
  break_start: string | null
  break_end: string | null
  break_minutes: number | null
}

export interface WorkSchedule {
  id: number
  name: string
  type: 'fixed' | 'shift' | 'flexible'
  timezone: string | null
  grace_minutes: number
  early_leave_grace_minutes: number
  late_mode: 'after_grace' | 'from_start'
  required_daily_minutes: number | null
  overtime_enabled: boolean
  overtime_threshold_minutes: number
  is_default: boolean
  status: 'active' | 'inactive'
  days: ScheduleDay[]
  employees_count?: number
}

export interface Holiday {
  id: number
  date: string
  name: string
}

export type AttendanceStatus = 'present' | 'late' | 'absent' | 'early_leave' | 'on_leave' | 'holiday' | 'weekend' | 'incomplete'

export interface AttendanceAudit {
  id: number
  action: string
  old_values: Record<string, unknown>
  new_values: Record<string, unknown>
  reason: string | null
  user: Ref | null
  created_at: string
}

export interface AttendanceRecord {
  id: number
  employee_id: number
  employee?: { id: number; full_name: string; employee_number: string; department: string | null }
  date: string
  schedule?: Ref | null
  scheduled_start: string | null
  scheduled_end: string | null
  check_in: string | null
  check_out: string | null
  worked_minutes: number
  late_minutes: number
  early_leave_minutes: number
  overtime_minutes: number
  status: AttendanceStatus
  source: string
  device?: Ref | null
  notes: string | null
  is_manual: boolean
  edited_by: Ref | null
  edited_at: string | null
  edit_reason: string | null
  audit_logs?: AttendanceAudit[]
}

export interface MonitorRow {
  employee: { id: number; full_name: string; employee_number: string; photo_url: string | null }
  department: string | null
  position: string | null
  schedule: { name: string | null; start: string | null; end: string | null; is_working_day: boolean; holiday: string | null }
  record_id: number | null
  check_in: string | null
  check_out: string | null
  worked_minutes: number
  late_minutes: number
  early_leave_minutes: number
  overtime_minutes: number
  status: AttendanceStatus | null
  live_status: string
  source: string | null
  device: string | null
  is_manual: boolean
}

export interface AttendanceSummary {
  total_employees: number
  present: number
  late: number
  absent: number
  working: number
  left: number
  incomplete: number
  on_leave: number
  early_leave: number
}

export interface CalendarDay {
  date: string
  status: AttendanceStatus | null
  code: string | null
  record_id?: number | null
  check_in?: string | null
  check_out?: string | null
  worked_minutes?: number
  late_minutes?: number
  early_leave_minutes?: number
  overtime_minutes?: number
}

export interface AttendanceDevice {
  id: number
  name: string
  vendor: 'hikvision' | 'fake'
  model: string | null
  serial_number: string | null
  type: string
  connection_mode: 'direct' | 'bridge' | 'push'
  ip_address: string | null
  port: number | null
  protocol: 'http' | 'https'
  username: string | null
  has_password: boolean
  has_bridge_token: boolean
  status: 'active' | 'inactive'
  last_sync_at: string | null
  last_event_at: string | null
  last_sync_status: string | null
  last_error: string | null
  settings: { timezone?: string }
  identifiers_count?: number
  unmatched_events_count?: number
}

export interface DevicePushLog {
  id: number
  device: Ref | null
  status: 'accepted' | 'heartbeat' | 'ignored' | 'unparsed' | 'rejected' | 'failed'
  event_type: string | null
  events_count: number
  stored_count: number
  summary: string | null
  content_type: string | null
  remote_ip: string | null
  body_excerpt: string | null
  error: string | null
  created_at: string
}

export interface RawEvent {
  id: number
  device: Ref | null
  external_event_id: string
  employee_identifier: string | null
  card_number: string | null
  employee: { id: number; full_name: string } | null
  event_type: string
  event_time: string
  processing_status: 'pending' | 'processed' | 'unmatched' | 'failed' | 'ignored'
  error_message: string | null
  name_on_device: string | null
  processed_at: string | null
}

export interface PenaltyRule {
  id: number
  name: string
  type: 'late' | 'early_leave' | 'absence'
  min_minutes: number
  max_minutes: number | null
  calculation: 'fixed' | 'per_minute' | 'percent_of_daily' | 'daily_salary'
  amount: number
  percentage: number | null
  is_active: boolean
}

export interface Penalty {
  id: number
  employee_id: number
  employee?: { id: number; full_name: string; employee_number: string } | null
  attendance_record_id: number | null
  rule?: Ref | null
  date: string
  type: 'late' | 'early_leave' | 'absence' | 'manual'
  amount: number
  percentage: number | null
  reason: string | null
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  is_automatic: boolean
  applied_by: Ref | null
  applied_at: string | null
  approved_by: Ref | null
  approved_at: string | null
}

export interface PayrollPeriod {
  id: number
  year: number
  month: number
  start_date: string
  end_date: string
  status: 'open' | 'calculated' | 'approved' | 'closed'
  calculated_at: string | null
  approved_at: string | null
  approved_by: Ref | null
  closed_at: string | null
  records_count?: number
  totals?: { gross: number; penalties: number; bonuses: number; net: number }
}

export interface PayrollRecord {
  id: number
  payroll_period_id: number
  employee_id: number
  employee?: { id: number; full_name: string; employee_number: string; department: string | null } | null
  salary_type: 'monthly' | 'daily' | 'hourly'
  rate: number
  base_salary: number
  scheduled_days: number
  scheduled_minutes: number
  worked_days: number
  worked_minutes: number
  overtime_minutes: number
  late_minutes: number
  early_leave_minutes: number
  absence_days: number
  overtime_amount: number
  gross_amount: number
  penalty_amount: number
  bonus_amount: number
  net_amount: number
  currency: string
  status: string
  details: Record<string, unknown>
  paid_at: string | null
}

export interface AuditLog {
  id: number
  event: string
  auditable_type: string | null
  auditable_id: number | null
  old_values: Record<string, unknown>
  new_values: Record<string, unknown>
  user: Ref | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface AppNotification {
  id: string
  event: string
  title: string
  body: string
  link: string | null
  read_at: string | null
  created_at: string
}

export type MetaOptions = Record<string, { value: string; label: string }[]>

export interface ReportTable {
  columns: Record<string, string>
  rows: Record<string, string | number | null>[]
}
