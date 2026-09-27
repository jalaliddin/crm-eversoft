# Eversoft CRM — Claude Code Master Prompt

> Project: **crm.eversoft.uz**
> Product: **Multi-Tenant SaaS CRM + HR**
> Current scope: **CRM Core + HR/Attendance**
> Future scope: Finance, Booking/Reservation, IP Telephony

---

## 1. ROLE

Act as a **Senior Software Architect, Senior Laravel Developer, Senior React/TypeScript Developer, Database Architect, DevOps Engineer and UI/UX Engineer**.

Build a real production-ready SaaS product, not a demo.

Before writing code:

1. Inspect the existing repository completely.
2. Identify existing Laravel/React/Docker code.
3. Reuse good existing code where possible.
4. Create an architecture plan.
5. Implement features in logical phases.
6. After each phase run tests/build/lint and fix errors.
7. Never leave fake implementations, broken TODOs or placeholder business logic.

Do not ask unnecessary technical questions. Make reasonable engineering decisions yourself. Ask only when a genuinely important business rule cannot safely be inferred.

---

# 2. PRODUCT VISION

Build a **multi-tenant CRM SaaS platform** under:

```text
crm.eversoft.uz
```

Each tenant is an independent organization/company.

Core structure:

```text
Platform
 ├── Tenant A
 │    ├── Users
 │    ├── Employees
 │    ├── Customers
 │    ├── Leads
 │    ├── Deals
 │    ├── Activities
 │    └── Attendance
 │
 ├── Tenant B
 │    ├── Users
 │    ├── Employees
 │    ├── Customers
 │    ├── Leads
 │    ├── Deals
 │    ├── Activities
 │    └── Attendance
 │
 └── Platform Admin
```

**Strict tenant isolation is mandatory.**

A user from Tenant A must never be able to access Tenant B data through API, frontend, URL manipulation, IDs, filters, exports or background jobs.

---

# 3. PRODUCT MODULES

## Current release

### CRM

- Dashboard
- Customers
- Leads
- Deals / Opportunities
- Pipelines
- Activities
- Tasks
- Notes
- Calls
- Meetings
- Customer timeline
- CRM search
- Tags
- Custom fields architecture
- Attachments/documents
- Reports

### HR

- Employees
- Departments
- Positions
- Work schedules
- Attendance
- Attendance monitor
- Attendance calendar
- Late arrival
- Early departure
- Absence
- Worked hours
- Overtime
- Manual attendance correction
- Attendance audit
- Penalty rules
- Payroll calculation
- Daily/monthly/hourly payment calculation
- HR reports
- Hikvision biometric integration

## Future modules

Do not implement these now, but design the architecture so they can be added without rewriting the core:

```text
Finance
 ├── Income
 ├── Expenses
 ├── Accounts
 ├── Transactions
 └── Reports

Booking
 ├── Resources
 ├── Restaurant tables
 ├── Hotel rooms
 ├── Meeting rooms
 ├── Availability
 └── Reservations

Telephony
 ├── SIP
 ├── Asterisk
 ├── FreePBX
 ├── Grandstream UCM
 ├── Incoming calls
 ├── Click-to-call
 └── Call history
```

---

# 4. TECHNOLOGY STACK

## Backend

```text
Laravel 12
PHP 8.3+
MySQL 8+
Laravel Sanctum
Redis
Laravel Queue
Laravel Scheduler
REST API
```

## Frontend

```text
React
TypeScript
Mantine UI
React Router
TanStack Query
React Hook Form
Zod
Axios
Day.js
Recharts
```

Use TypeScript strict mode.

## Infrastructure

```text
Docker
Docker Compose
Nginx Reverse Proxy
MySQL
Redis
PHP-FPM
Node/Vite
Queue Worker
Scheduler
```

---

# 5. REPOSITORY STRUCTURE

Use a clean monorepo:

```text
eversoft-crm/
├── backend/
├── frontend/
├── docker/
├── nginx/
├── docs/
├── docker-compose.yml
├── .env.example
├── README.md
└── .gitignore
```

Backend:

```text
backend/app/
├── Actions/
├── DTOs/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Policies/
├── Services/
├── Support/
└── ...
```

Frontend:

```text
frontend/src/
├── app/
├── components/
├── features/
│   ├── auth/
│   ├── dashboard/
│   ├── crm/
│   ├── hr/
│   └── settings/
├── layouts/
├── hooks/
├── lib/
├── routes/
├── services/
├── types/
└── ...
```

Prefer feature-based architecture.

---

# 6. MULTI-TENANCY — CRITICAL

Implement proper application-level multi-tenancy.

Use a central `organizations` / `tenants` table.

Suggested:

```text
organizations
├── id
├── name
├── slug
├── domain
├── logo
├── timezone
├── currency
├── status
├── settings
└── timestamps
```

Every tenant-owned table must contain:

```text
organization_id
```

Examples:

```text
users
employees
departments
positions
customers
leads
deals
activities
attendance
attendance_devices
penalties
payroll_periods
payroll_records
documents
```

Implement a tenant context/resolver.

Example concept:

```text
Request
   ↓
Authenticate User
   ↓
Resolve Organization
   ↓
Set Tenant Context
   ↓
All Queries Scoped to Tenant
```

Use:

- middleware
- policies
- model scopes / global scopes where appropriate
- service-level validation
- database foreign keys

Do not rely only on frontend filtering.

Every API endpoint must enforce tenant isolation.

---

# 7. TENANT RESOLUTION

Support a structure that can later use:

```text
crm.eversoft.uz
```

with tenant identification by:

```text
subdomain
custom domain
authenticated user's organization
```

For initial implementation, authenticated user's `organization_id` may be the primary resolver, while the architecture remains ready for:

```text
tenant1.crm.eversoft.uz
tenant2.crm.eversoft.uz
```

Never trust a client-provided `organization_id` without validating authorization.

---

# 8. PLATFORM ADMIN VS TENANT ADMIN

Create platform-level roles separately from tenant-level roles.

## Platform roles

```text
platform_super_admin
platform_support
```

Platform admins can manage tenants according to permission.

## Tenant roles

```text
owner
admin
hr_manager
manager
employee
sales_manager
sales_agent
```

Permissions must be granular.

Examples:

```text
customers.view
customers.create
customers.update
customers.delete

leads.view
leads.create
leads.update
leads.delete

deals.view
deals.create
deals.update
deals.delete

employees.view
employees.create
employees.update
employees.delete

attendance.view
attendance.manage
attendance.correct
attendance.export

payroll.view
payroll.calculate
payroll.approve

devices.view
devices.manage
devices.sync
```

Use Laravel Policies/Gates and role/permission management.

---

# 9. DATABASE

Development credentials:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=eversoft_crm
DB_USERNAME=root
DB_PASSWORD=root
```

These are development defaults only.

Production must use a dedicated DB user and strong secret.

Use:

- foreign keys
- indexes
- unique constraints
- transactions
- soft deletes where appropriate

Large attendance tables must be indexed correctly.

---

# 10. AUTHENTICATION

Use Laravel Sanctum.

Implement:

```text
Login
Logout
Current user
Change password
Forgot password
Reset password
Profile
Avatar
Session handling
```

Frontend protected routing:

```text
/login
/dashboard
```

Unauthenticated users must be redirected to login.

---

# 11. CRM DASHBOARD

Dashboard must be tenant-specific.

Cards:

```text
Customers
New Leads
Open Deals
Won Deals
Lost Deals
Tasks
Today's Activities
```

HR cards:

```text
Employees
Present Today
Late Today
Absent Today
Currently Working
```

Charts:

```text
Customer Growth
Lead Funnel
Deal Pipeline
Sales/Deal Value
Attendance Trend
```

Date filters:

```text
Today
This Week
This Month
This Quarter
Custom Range
```

---

# 12. CUSTOMERS

Table:

```text
customers
```

Fields:

```text
id
organization_id
type
first_name
last_name
company_name
email
phone
secondary_phone
website
address
city
country
source
status
notes
created_by
updated_by
timestamps
```

Types:

```text
individual
company
```

Statuses:

```text
active
inactive
blocked
```

Features:

- CRUD
- search
- filters
- sorting
- pagination
- tags
- notes
- attachments
- timeline
- linked leads
- linked deals
- activities

Customer detail:

```text
Overview
Contacts
Leads
Deals
Activities
Notes
Documents
Timeline
```

---

# 13. LEADS

Fields:

```text
id
organization_id
customer_id
name
phone
email
source
status
assigned_to
expected_value
description
created_by
timestamps
```

Statuses:

```text
new
contacted
qualified
proposal
negotiation
converted
lost
```

Implement:

- CRUD
- Kanban
- filters
- assignment
- conversion to customer/deal
- timeline

---

# 14. DEALS / OPPORTUNITIES

Fields:

```text
id
organization_id
customer_id
lead_id
title
value
currency
stage
probability
expected_close_date
assigned_to
description
created_by
timestamps
```

Stages:

```text
new
qualification
proposal
negotiation
won
lost
```

Implement Kanban pipeline.

Do not hard-code pipeline stages permanently. Prepare for tenant-specific pipelines.

---

# 15. ACTIVITIES

Types:

```text
task
call
meeting
email
note
```

Fields:

```text
id
organization_id
title
type
description
due_at
completed_at
assigned_to
customer_id
lead_id
deal_id
priority
status
created_by
timestamps
```

Statuses:

```text
pending
in_progress
completed
cancelled
```

---

# 16. CRM TIMELINE

Create reusable timeline system.

Events:

```text
customer.created
customer.updated
lead.created
lead.status_changed
deal.created
deal.stage_changed
activity.created
activity.completed
note.created
document.uploaded
```

Timeline must be visible from customer/lead/deal pages.

---

# 17. HR MODULE

Navigation:

```text
HR
├── Dashboard
├── Employees
├── Departments
├── Positions
├── Work Schedules
├── Attendance
├── Attendance Monitor
├── Penalties
├── Payroll
└── Reports
```

---

# 18. EMPLOYEES

Fields:

```text
id
organization_id
user_id
employee_number
first_name
last_name
middle_name
phone
email
birth_date
gender
address
department_id
position_id
manager_id
employment_type
hire_date
termination_date
salary_type
salary_amount
status
photo
notes
timestamps
```

Employment types:

```text
full_time
part_time
contract
temporary
```

Salary types:

```text
monthly
daily
hourly
```

Statuses:

```text
active
inactive
terminated
on_leave
```

Employee profile:

```text
Personal Information
Employment Information
Department
Position
Work Schedule
Attendance
Payroll
Penalties
Documents
Activity History
```

Authentication credentials belong to `users`, not employees.

---

# 19. DEPARTMENTS

Fields:

```text
id
organization_id
name
code
description
manager_id
status
timestamps
```

Support hierarchy if practical:

```text
parent_id
```

---

# 20. POSITIONS

Fields:

```text
id
organization_id
name
code
description
department_id
status
timestamps
```

---

# 21. WORK SCHEDULES

Support:

- Monday-Friday
- Monday-Saturday
- custom working days
- shifts
- flexible schedules

Example:

```text
08:30 - 17:30
Break: 13:00 - 14:00
Grace: 10 minutes
```

Models:

```text
work_schedules
work_schedule_days
employee_work_schedules
```

Schedule configuration:

```text
timezone
grace_minutes
required_daily_minutes
overtime_enabled
```

Do not hard-code working hours.

---

# 22. ATTENDANCE

Attendance table:

```text
attendance_records
```

Fields:

```text
id
organization_id
employee_id
date
scheduled_start
scheduled_end
actual_check_in
actual_check_out
worked_minutes
late_minutes
early_leave_minutes
overtime_minutes
status
source
device_id
raw_event_id
notes
created_at
updated_at
```

Statuses:

```text
present
late
absent
early_leave
on_leave
holiday
weekend
incomplete
```

Sources:

```text
manual
biometric
web
mobile
api
```

---

# 23. ATTENDANCE CALCULATION

All business calculations happen on the backend.

Example:

```text
Scheduled: 08:30
Grace: 10 min
Actual: 08:35
Late: 0

Actual: 08:45
Late: 15 min
```

Early leave:

```text
Scheduled end: 17:30
Actual end: 17:20
Early leave: 10 min
```

Worked time must account for breaks.

Example:

```text
08:42 → 17:20
Break = 60 min
Worked = 7h38m
```

Support overnight shifts architecturally even if not enabled initially.

All calculations must use:

```text
Asia/Tashkent
```

or the tenant's configured timezone.

---

# 24. ATTENDANCE MONITOR

Real-time-style dashboard:

```text
Present
Late
Absent
Currently Working
Left
Incomplete
```

Table:

```text
Employee
Department
Schedule
Check-in
Check-out
Worked
Late
Early Leave
Status
Source
```

Filters:

```text
Date
Department
Employee
Status
Device
Source
```

Initial auto-refresh can use polling.

Design service/API so WebSockets can be added later.

---

# 25. ATTENDANCE CALENDAR

Monthly calendar.

Indicators:

```text
P  Present
L  Late
A  Absent
EL Early Leave
H  Holiday
W  Weekend
```

Clicking a day opens attendance details.

---

# 26. HIKVISION DS-K1A340FWX

Primary biometric device:

```text
Hikvision DS-K1A340FWX
```

Vendor-specific code must be isolated.

Create:

```php
interface AttendanceDeviceInterface
{
    public function testConnection(): DeviceConnectionResult;

    public function getEvents(
        Carbon $from,
        Carbon $to
    ): iterable;

    public function syncEmployees(): void;

    public function pushEmployee(Employee $employee): void;

    public function removeEmployee(Employee $employee): void;
}
```

Models:

```text
attendance_devices
employee_device_identifiers
attendance_raw_events
```

Device fields:

```text
id
organization_id
name
vendor
model
type
ip_address
port
username
encrypted_password
protocol
status
last_sync_at
last_error
settings
timestamps
```

Never store plaintext device passwords.

Use Laravel encryption / encrypted casts.

---

# 27. HIKVISION SERVICE ARCHITECTURE

Never call Hikvision API directly from controllers.

Use:

```text
Controller
    ↓
AttendanceDeviceManager
    ↓
AttendanceSyncService
    ↓
HikvisionAttendanceDevice
    ↓
HikvisionClient
    ↓
Device
```

Suggested:

```text
app/Services/Attendance/
├── AttendanceDeviceManager.php
├── AttendanceSyncService.php
└── Devices/
    ├── AttendanceDeviceInterface.php
    ├── Fake/
    └── Hikvision/
        ├── HikvisionClient.php
        ├── HikvisionAttendanceDevice.php
        └── HikvisionEventMapper.php
```

Must support:

- timeout
- retry
- connection test
- error handling
- logging
- sync status
- mock adapter for tests

Do not invent undocumented Hikvision endpoints.

Before implementing the actual protocol, verify what DS-K1A340FWX and its firmware support. If a real device is unavailable, implement the adapter architecture and mock integration without pretending the physical device was tested.

---

# 28. RAW ATTENDANCE EVENTS

Create:

```text
attendance_raw_events
```

Fields:

```text
id
organization_id
device_id
external_event_id
employee_identifier
event_type
event_time
payload
processed_at
processing_status
error_message
timestamps
```

Preserve raw events for:

- audit
- debugging
- reprocessing
- duplicate prevention

Unique constraint:

```text
device_id + external_event_id
```

If no external event ID exists, create a deterministic event hash.

---

# 29. EMPLOYEE DEVICE IDENTIFIER

Create:

```text
employee_device_identifiers
```

Fields:

```text
id
organization_id
employee_id
device_id
identifier
card_number
biometric_identifier
status
timestamps
```

Never assume device identifier equals employee database ID.

---

# 30. ATTENDANCE SYNC

Create scheduled command:

```bash
php artisan attendance:sync
```

Run via Laravel Scheduler.

Flow:

```text
Hikvision
    ↓
Fetch events
    ↓
Store raw event
    ↓
Deduplicate
    ↓
Resolve employee
    ↓
Process attendance
    ↓
Calculate metrics
    ↓
Audit
```

Use queues for large workloads.

Commands/jobs must be idempotent.

If an employee is unknown, keep the raw event and show it in an admin screen.

---

# 31. MANUAL ATTENDANCE

HR/Admin can:

- create attendance
- edit check-in
- edit check-out
- correct status
- add reason

Every manual change requires:

```text
edited_by
edited_at
edit_reason
```

Create:

```text
attendance_audit_logs
```

Store old/new values.

---

# 32. PAYROLL CALCULATION

This is an HR payroll calculation module, not a complete accounting system.

Support:

### Monthly

```text
monthly salary
```

### Daily

```text
daily rate × worked days
```

### Hourly

```text
hourly rate × worked hours
```

Create:

```text
payroll_periods
payroll_records
```

Payroll period:

```text
id
organization_id
year
month
status
opened_at
closed_at
created_by
timestamps
```

Payroll record:

```text
employee_id
base_salary
worked_days
worked_minutes
overtime_minutes
late_minutes
early_leave_minutes
absence_days
gross_amount
penalty_amount
bonus_amount
net_amount
status
```

Statuses:

```text
draft
calculated
approved
paid
cancelled
```

Do not hard-code salary rules.

---

# 33. PENALTIES

Create configurable penalty rules.

Types:

```text
late
early_leave
absence
manual
```

Models:

```text
attendance_penalty_rules
penalties
```

Penalty:

```text
employee_id
attendance_id
type
amount
percentage
reason
status
applied_by
applied_at
```

Example configurable rules:

```text
Late > 15 minutes → fixed amount
Late > 60 minutes → different amount
Absence → daily salary deduction
```

Do not hard-code these values.

---

# 34. HR REPORTS

Reports:

### Daily Attendance

```text
Employee
Department
Check-in
Check-out
Worked
Late
Early Leave
Status
```

### Monthly Attendance

```text
Employee
Work Days
Present Days
Absent Days
Late Count
Late Minutes
Early Leave Count
Worked Hours
Overtime
```

### Payroll

```text
Employee
Base
Worked
Penalties
Bonuses
Net
```

Export:

```text
Excel
CSV
PDF if practical
```

Exports must also enforce tenant isolation and permissions.

---

# 35. HR DASHBOARD

Cards:

```text
Total Employees
Present Today
Late Today
Absent Today
Currently Working
```

Charts:

```text
Attendance Trend
Late Trend
Department Attendance
```

Live table:

```text
Employee | Department | Check-in | Status | Worked
```

---

# 36. SETTINGS

Tenant settings:

```text
Organization Name
Logo
Timezone
Currency
Date Format
Time Format
Work Week
Default Grace Period
Attendance Settings
Payroll Settings
Notification Settings
```

Each tenant has independent settings.

---

# 37. NOTIFICATIONS

Architecture for:

```text
database
email
```

Future:

```text
Telegram
SMS
WhatsApp
Push
```

Events:

```text
employee late
employee absent
early leave
payroll calculated
lead assigned
deal stage changed
task due
```

---

# 38. AUDIT LOG

Generic audit system.

Track:

```text
login
logout
create
update
delete
attendance correction
payroll approval
penalty approval
role changes
permission changes
device changes
```

Never log:

```text
passwords
tokens
device passwords
application secrets
```

---

# 39. API

Use:

```text
/api/v1
```

Authentication:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

CRM:

```text
GET/POST       /api/v1/customers
GET/PUT/DELETE /api/v1/customers/{id}

GET/POST       /api/v1/leads
GET/PUT/DELETE /api/v1/leads/{id}

GET/POST       /api/v1/deals
GET/PUT/DELETE /api/v1/deals/{id}

GET/POST       /api/v1/activities
```

HR:

```text
GET/POST       /api/v1/employees
GET/PUT/DELETE /api/v1/employees/{id}

GET/POST       /api/v1/departments
GET/POST       /api/v1/positions

GET            /api/v1/attendance
GET            /api/v1/attendance/dashboard
GET            /api/v1/attendance/calendar

GET            /api/v1/attendance-devices
POST           /api/v1/attendance-devices/{id}/test
POST           /api/v1/attendance-devices/{id}/sync

GET            /api/v1/payroll
POST           /api/v1/payroll/calculate
```

Every endpoint must enforce:

1. authentication
2. tenant access
3. permission
4. validation

---

# 40. API RESPONSE FORMAT

Success:

```json
{
  "success": true,
  "data": {},
  "message": null
}
```

Pagination:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 250
  }
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

---

# 41. FRONTEND NAVIGATION

```text
Dashboard

CRM
  Customers
  Leads
  Deals
  Activities

HR
  Dashboard
  Employees
  Departments
  Positions
  Work Schedules
  Attendance
  Attendance Monitor
  Penalties
  Payroll
  Reports

Settings
  Organization
  Users
  Roles & Permissions
  Attendance Devices
  Notifications
  Audit Logs
  System Settings
```

Future:

```text
Finance
Booking
Telephony
```

---

# 42. MODERN UI/UX

Create a modern commercial SaaS interface.

Use Mantine.

Design requirements:

- clean sidebar
- responsive header
- breadcrumbs
- cards
- tabs
- drawers
- modals
- data tables
- charts
- kanban
- timeline
- badges
- avatars
- skeleton loaders
- empty states
- error states
- confirmation dialogs
- toast notifications

Avoid:

- old Bootstrap admin look
- excessive borders
- tiny text
- excessive colors
- unnecessary gradients
- clutter
- giant unusable tables

Support:

```text
Light theme
Dark theme
Responsive desktop/tablet/mobile
```

Use centralized theme tokens.

---

# 43. FRONTEND DATA ARCHITECTURE

Use TanStack Query for server state.

Use:

```text
React Hook Form
Zod
Axios
```

Create reusable:

```text
DataTable
PageHeader
SearchInput
FilterDrawer
ConfirmDialog
EmptyState
ErrorState
LoadingState
StatCard
StatusBadge
FormField
EntityDrawer
EntityModal
Timeline
```

Do not duplicate CRUD UI unnecessarily.

---

# 44. SECURITY

Implement:

- Sanctum
- RBAC
- Laravel Policies
- request validation
- rate limiting
- secure file uploads
- MIME validation
- file size limits
- encrypted credentials
- SQL injection protection
- XSS protection
- security headers
- tenant isolation
- audit logs

Never trust:

```text
organization_id from frontend
employee_id from frontend
user_id from frontend
```

Validate ownership/access server-side.

---

# 45. FILES

Create a document abstraction.

Support:

```text
local storage
S3-compatible storage later
```

Do not store large files directly in MySQL.

---

# 46. PERFORMANCE

Design for:

```text
10,000+ employees
millions of attendance events
large CRM datasets
```

Use:

- database indexes
- pagination
- eager loading
- chunking
- queues
- batch inserts
- caching
- transactions

Never load millions of records into memory.

Attendance processing must be idempotent.

---

# 47. DOCKER

Create:

```text
docker-compose.yml
```

Services:

```text
nginx
backend
frontend
mysql
redis
queue
scheduler
```

Development:

```bash
docker compose up -d --build
```

Useful commands:

```bash
docker compose logs -f
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan test
```

---

# 48. NGINX

Domain:

```text
crm.eversoft.uz
```

Routing:

```text
/       → React
/api    → Laravel
```

PHP-FPM must not be publicly exposed.

Configure:

- gzip
- static asset caching
- client max body size
- security headers
- proxy headers
- SSL-ready configuration

---

# 49. ENVIRONMENT

Create `.env.example`.

Example:

```env
APP_NAME="Eversoft CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

APP_TIMEZONE=Asia/Tashkent

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=eversoft_crm
DB_USERNAME=root
DB_PASSWORD=root

REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

VITE_API_URL=http://localhost/api/v1
```

Never commit `.env`.

---

# 50. SEED DATA

Create development seed data:

```text
1 platform super admin
2 demo organizations
organization admins
HR manager
sales manager
employees
departments
positions
customers
leads
deals
activities
work schedules
attendance
penalty rules
```

Ensure seed data clearly demonstrates tenant isolation.

For example:

```text
Tenant A Customer A
Tenant B Customer B
```

Tenant A API must never return Customer B.

---

# 51. TESTING

Backend:

```text
AuthenticationTest
TenantIsolationTest
RolePermissionTest
CustomerTest
LeadTest
DealTest
EmployeeTest
AttendanceCalculationTest
AttendanceTenantIsolationTest
HikvisionEventTest
DuplicateEventTest
PayrollCalculationTest
PenaltyTest
```

Critical attendance tests:

```text
08:30 scheduled / 08:30 actual = 0 late
08:30 scheduled / 08:35 actual = 0 late with 10 min grace
08:30 scheduled / 08:45 actual = 5 late
17:30 scheduled / 17:20 actual = 10 early leave
duplicate biometric event = one event
unknown employee = raw event retained
device unavailable = graceful failure
Tenant A cannot access Tenant B
```

Frontend:

```bash
npm run lint
npm run build
```

---

# 52. TIMEZONE

Default:

```text
Asia/Tashkent
```

Tenant timezone must be configurable.

All attendance calculations must use the tenant timezone.

Avoid mixing:

```text
UTC timestamps
server local time
tenant business time
```

Document the strategy.

---

# 53. LOGGING / OBSERVABILITY

Use structured logs.

Separate channels where useful:

```text
daily
attendance
hikvision
security
```

Admin health page:

```text
Application
Database
Redis
Queue
Scheduler
Attendance Devices
Last Sync
```

Future-ready for:

```text
Sentry
Prometheus
Grafana
```

---

# 54. DOCUMENTATION

Create:

```text
README.md
docs/ARCHITECTURE.md
docs/MULTI_TENANCY.md
docs/CRM.md
docs/HR.md
docs/ATTENDANCE.md
docs/HIKVISION.md
docs/API.md
docs/DEPLOYMENT.md
```

Document:

- installation
- Docker
- environment
- migrations
- seed
- development
- testing
- production deployment
- tenant architecture
- Hikvision setup
- troubleshooting

---

# 55. IMPLEMENTATION PHASES

Implement in this exact order.

## Phase 1 — Foundation

- inspect repository
- Laravel setup
- React setup
- TypeScript
- Mantine
- Docker
- MySQL
- Redis
- Nginx
- environment
- base architecture

## Phase 2 — Multi-Tenancy

- organizations
- tenant context
- tenant middleware
- tenant-aware models
- tenant-aware policies
- platform admin
- tenant admin
- isolation tests

## Phase 3 — Authentication / RBAC

- Sanctum
- login/logout
- users
- roles
- permissions
- protected routes

## Phase 4 — CRM Core

- dashboard
- customers
- leads
- deals
- pipeline
- activities
- notes
- timeline
- attachments

## Phase 5 — HR Foundation

- employees
- departments
- positions
- work schedules
- employee profiles

## Phase 6 — Attendance Engine

- attendance records
- calculations
- calendar
- monitor
- manual correction
- audit
- reports

## Phase 7 — Hikvision

- devices
- device abstraction
- Hikvision client
- raw events
- mapping
- synchronization
- duplicate prevention
- background jobs

## Phase 8 — Payroll / Penalties

- penalty rules
- payroll periods
- calculations
- approval
- reports

## Phase 9 — UI/UX / Polish

- responsive design
- dark/light mode
- localization
- loading states
- empty states
- errors
- accessibility
- performance

## Phase 10 — QA

- tests
- security review
- tenant isolation review
- Docker review
- API review
- production build
- documentation

---

# 56. DEVELOPMENT WORKFLOW

At the beginning:

```text
1. Inspect repository.
2. Inspect composer.json.
3. Inspect package.json.
4. Inspect Docker configuration.
5. Inspect existing migrations/models/routes.
6. Identify what already exists.
7. Create/update architecture documentation.
8. Implement Phase 1.
9. Test.
10. Continue to Phase 2.
```

After every significant feature:

```text
1. Implement.
2. Run migrations.
3. Run tests.
4. Run lint.
5. Run frontend build.
6. Fix errors.
7. Review tenant security.
8. Continue.
```

Do not move forward while critical errors remain.

---

# 57. CODE QUALITY

Follow:

```text
SOLID
DRY
KISS
YAGNI
Separation of Concerns
Clean Architecture principles where useful
```

Do not over-engineer.

Avoid:

```text
fat controllers
duplicate business logic
frontend-only validation
hard-coded tenant IDs
hard-coded salary rules
hard-coded work schedules
hard-coded penalty values
vendor API calls inside controllers
```

Business logic belongs in services/actions/domain-oriented classes.

---

# 58. FUTURE-PROOFING

The following must be designed as modules:

```text
CRM
HR
Finance
Booking
Telephony
```

Do not tightly couple HR to CRM.

Do not tightly couple Hikvision to attendance business logic.

Do not tightly couple payroll to general finance.

Use interfaces/services where external integrations are involved.

---

# 59. FINAL ACCEPTANCE CRITERIA

The product is ready for the first release only when:

### Multi-tenancy

- multiple organizations work
- tenant isolation is enforced
- tenant isolation is tested
- platform admin is separated from tenant users

### Authentication

- login works
- logout works
- Sanctum works
- RBAC works
- policies work

### CRM

- customers CRUD
- leads CRUD
- deals CRUD
- pipeline
- activities
- timeline
- dashboard
- search/filter/pagination

### HR

- employees
- departments
- positions
- work schedules
- attendance
- late calculation
- early leave calculation
- worked hours
- absence
- overtime
- manual correction
- audit
- reports

### Hikvision

- device configuration
- connection test
- sync architecture
- raw event storage
- employee mapping
- duplicate prevention
- error handling
- logs
- mock adapter
- documented real-device verification steps

### Payroll

- monthly salary
- daily salary
- hourly salary
- worked time
- penalties
- payroll periods
- payroll reports

### UI

- modern SaaS design
- responsive
- dark/light mode
- loading states
- empty states
- error states
- usable tables
- search/filter/pagination

### Quality

These must pass:

```bash
php artisan test
npm run lint
npm run build
```

---

# 60. IMPORTANT FINAL INSTRUCTION

Build this as a **real multi-tenant commercial SaaS CRM**, not as a tutorial.

The most important architectural requirements are:

1. **Strict multi-tenancy**
2. **Secure RBAC**
3. **Clean CRM architecture**
4. **Reliable HR attendance engine**
5. **Vendor-isolated Hikvision integration**
6. **Correct payroll calculations**
7. **Modern React + Mantine UX**
8. **Docker + Nginx production-ready infrastructure**
9. **Automated tests**
10. **Easy future expansion into Finance, Booking and IP Telephony**

Do not sacrifice tenant security for convenience.

Do not trust client-supplied organization IDs.

Do not invent Hikvision APIs.

Do not claim physical device integration has been tested unless it actually has been tested.

When a feature is complete, verify it end-to-end before moving to the next phase.

Start by inspecting the repository and then implement **Phase 1 — Foundation**.
