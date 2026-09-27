# Architecture

## Overview

```text
Browser (React SPA)
   │  HTTPS
   ▼
Nginx ── /            → built SPA (static, SPA fallback)
      └─ /api, /up    → PHP-FPM (Laravel)          ← internal network only
                              │
              ┌───────────────┼──────────────────┐
              ▼               ▼                  ▼
            MySQL           Redis          Queue worker / Scheduler
                        (cache, queue,     (same Laravel image)
                         sessions)
                                                 │ ISAPI (digest auth)
                                                 ▼
                                   Hikvision terminals (direct mode)

On-site network ── hik-bridge agent ──HTTPS──▶ /api/v1/device-bridge/events (bridge mode)
```

## Backend layout (`backend/app`)

| Folder | Responsibility |
|---|---|
| `Models/{Crm,Hr,Attendance,Payroll}` | Eloquent models per module. Tenant-owned models use `BelongsToOrganization`. |
| `Models/Concerns` | `BelongsToOrganization` (tenant scope + write guard), `Auditable`, `HasTags`, `HasNotesAndDocuments` |
| `Http/Controllers/Api/V1/*` | Thin controllers: authorize → validate → call a service → return a Resource |
| `Http/Requests` | FormRequests for the main entities; tenant-aware rules via `Support/Validation/TenantRule` |
| `Http/Resources` | API output shape (UTC ISO timestamps, `Y-m-d` business dates) |
| `Http/Middleware` | `ResolveTenant`, `EnsurePlatformUser`, `SecurityHeaders`, `ForceJsonResponse` |
| `Policies` | Permission + same-tenant + record visibility (CRM "own records") |
| `Services/Crm` | Customers, leads (conversion), deals (stage moves), activities, timeline |
| `Services/Hr` | Employees, work schedules, `ScheduleResolver`, `WorkingTimeService` |
| `Services/Attendance` | Calculator (pure), processor, monitor/calendar/report read models, device manager, sync, ingestion |
| `Services/Attendance/Devices` | `AttendanceDeviceInterface`, `Hikvision/*`, `Fake/*` |
| `Services/Payroll` | Penalty rules engine, payroll calculator and period workflow |
| `Services/{Audit,Files,Reports,Search,System,Users,Organization,Notifications}` | Cross-cutting services |
| `Support` | `Permissions` catalog, `OrganizationSettings`, tenancy helpers, API response trait |
| `Enums` | Backed string enums for every status and type |
| `Console/Commands` | `attendance:sync`, `attendance:close-day`, `attendance:process-pending`, `activities:remind-due` |
| `Jobs` | `SyncAttendanceDeviceJob` (unique per device), `ProcessRawEventsJob` |
| `Notifications` | Tenant notifications. Channels come from organization settings. |

Rules the code follows:

- Business logic lives in services. Controllers never call device APIs, never compute attendance or
  pay, and never touch `organization_id`.
- All attendance and payroll math runs on the backend. The frontend only renders it.
- Configurable values (schedules, grace, penalty amounts, payroll mode) live in the database or in
  organization settings, never in code.

## Request flow (tenant endpoint)

```text
Request → auth:sanctum → ResolveTenant (organization from the user, host must match)
        → can:<permission> → FormRequest (TenantRule for every foreign id)
        → route-model binding (runs *after* ResolveTenant, so it uses the tenant scope)
        → Policy (permission + same tenant + visibility) → Service → Resource → JSON envelope
```

`ResolveTenant` is placed before `SubstituteBindings` in the middleware priority list. That is what
makes a foreign id return 404 rather than a record.

## Frontend layout (`frontend/src`)

| Folder | Responsibility |
|---|---|
| `app/` | Providers and theme tokens (`theme.ts`, `global.css`) |
| `routes/` | Router with lazily loaded pages and permission guards |
| `layouts/` | App shell, sidebar (permission-filtered), global search (Ctrl/⌘+K), user menu |
| `components/` | `DataTable`, `PageHeader`, `SearchInput`, `FilterDrawer`, `confirmAction`, `EmptyState`, `ErrorState`, `LoadingState`, `StatCard`, `StatusBadge`, `FormField`, `EntityDrawer`, `EntityModal`, `Timeline`, `KanbanBoard`, `ShiftBar`, charts |
| `features/*` | Feature modules: `auth`, `dashboard`, `crm`, `hr`, `settings`, `platform`, `notifications` |
| `hooks/` | URL-synced list state, lookups, mutation helper |
| `lib/` | API client (normalized errors), formatting (tenant timezone), i18n (en/ru/uz) |

Server state lives in TanStack Query. List filters live in the URL, so a filtered view can be shared
as a link.

## Future modules

New modules plug in without touching the core:

1. Add a permission group to `Support/Permissions::CATALOG` and grant it to roles.
2. Add tenant-owned models using `BelongsToOrganization`.
3. Add services and controllers under a module namespace, and routes inside the `tenant` group.
4. Add a feature folder and nav entries in the frontend. The sidebar already shows Finance, Booking
   and Telephony as "Soon".

Specifics:

- **Finance:** payroll records already hold net amounts per period, so a finance module can read
  approved periods through a service without payroll depending on finance.
- **Telephony:** add a `TelephonyProviderInterface` with Asterisk / FreePBX / Grandstream drivers, the
  same pattern as `AttendanceDeviceManager`. Calls attach to customers through the timeline
  (`TimelineRecorder::record('call.logged', …)`).
- **Booking:** resources and reservations are new tenant-owned tables. Availability reuses the
  schedule and holiday concepts.

Coupling is deliberately loose. HR does not depend on CRM. Hikvision code does not know about
attendance rules. Payroll does not depend on finance.
