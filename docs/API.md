# REST API

Base URL: `/api/v1`. All requests and responses use JSON (`Accept: application/json` is forced on API
routes).

## Authentication

```http
POST /api/v1/auth/login
{ "email": "owner@alpha.test", "password": "password", "remember": false, "device_name": "Chrome on Windows" }
```

The response includes a Sanctum bearer token plus the user, organization and permissions. Send the
token as `Authorization: Bearer <token>` on every other request.

- **Lifetime:** 12 h (`SANCTUM_SESSION_HOURS`), or 30 days with `remember: true`.
- **Sessions:** each login is a session the user can list and revoke under `/profile/sessions`.
- **Password changes** revoke the user's other sessions.
- **Password resets** revoke all of the user's sessions.

## Response envelope

```json
{ "success": true, "data": { }, "message": null }
```

Paginated lists:

```json
{ "success": true, "data": [ ], "meta": { "current_page": 1, "last_page": 10, "per_page": 25, "total": 250 } }
```

Errors:

```json
{ "success": false, "message": "Validation failed.", "errors": { "email": ["The email field is required."] } }
```

| Status | Meaning |
|---|---|
| 401 | Missing, invalid or expired token |
| 403 | Missing permission, suspended organization, tenant host mismatch, or a platform user calling a tenant endpoint |
| 404 | Not found **or belongs to another organization** (the two are indistinguishable by design) |
| 422 | Validation failed, or a business rule was broken (`message` explains which) |
| 429 | Rate limited |

## Conventions

- **Lists:** `?page=`, `?per_page=` (at most 100), `?search=`, and `?sort=field` / `?sort=-field`
  (whitelisted per endpoint), plus endpoint-specific filters. Exportable lists accept
  `?export=csv|xlsx|pdf`.
- **Instants** are UTC ISO-8601 (`2026-09-21T03:45:10Z`); **business dates** are `YYYY-MM-DD`.
  Attendance inputs without an offset are read in the organization or schedule timezone.
- **Ids from the client** are validated against the caller's organization, and ids from another
  tenant fail validation. `organization_id` in a body is ignored.
- **Every tenant endpoint** runs, in order: authentication → tenant resolution → permission
  (`can:` middleware or policy) → validation.
- **Rate limits:**
  - login: 5 per minute per email+IP, 30 per minute per IP
  - password reset: 3 per minute
  - API: 240 per minute per user
  - uploads: 30 per minute
  - device ingest: 600 per minute per IP

## Device bridge ingest

```http
POST /api/v1/device-bridge/events
Authorization: Bearer hbr_…          (device bridge token, not a user token)
{ "events": [ { "time": "2026-09-21T08:31:05+05:00", "employee_identifier": "1001", "card_number": null,
                "event_type": "check_in", "external_id": "5001", "payload": { } } ] }
```

This accepts up to 1000 events per call and is idempotent (deduplicated by device + external id). It
returns `received, stored, duplicates, processed, unmatched, records_updated`.

## Terminal event push

```http
POST /api/v1/hikvision/push/{token}        (token = hpt_…, issued per device, shown once)
Content-Type: multipart/form-data | application/json | application/xml
```

This is the listening-host URL a terminal is configured with. The terminal cannot send a bearer
token, so the path token identifies the device, and through it the tenant. The endpoint accepts an
ISAPI `EventNotificationAlert` (JSON or XML, multipart or raw body), including heartbeats. It always
answers 200 once the request is recorded in the device's push log. It answers 404 for an unknown
token, 403 for an inactive device, and 500 if processing failed, so the terminal can retry;
duplicates are ignored.

## Endpoints

"Required" is enforced by route middleware. Endpoints that only require a signed-in user are still
checked in the controller or policy: for example `customers.view` / `.create` / `.update` / `.delete`, own-record
visibility for leads, deals and activities, and `attendance.view` vs `attendance.view_own`.

### Health

| Method | Path | Required |
|---|---|---|
| GET | `/ping` | public |

### Authentication

| Method | Path | Required |
|---|---|---|
| POST | `/auth/forgot-password` | public |
| POST | `/auth/login` | public |
| POST | `/auth/logout` | signed-in user |
| GET | `/auth/me` | signed-in user |
| POST | `/auth/reset-password` | public |

### Profile & sessions

| Method | Path | Required |
|---|---|---|
| PUT | `/profile` | signed-in user |
| DELETE | `/profile/avatar` | signed-in user |
| POST | `/profile/avatar` | signed-in user |
| PUT | `/profile/password` | signed-in user |
| DELETE | `/profile/sessions` | signed-in user |
| GET | `/profile/sessions` | signed-in user |
| DELETE | `/profile/sessions/{id}` | signed-in user |

### Notifications

| Method | Path | Required |
|---|---|---|
| GET | `/notifications` | signed-in user |
| POST | `/notifications/read-all` | signed-in user |
| GET | `/notifications/unread-count` | signed-in user |
| POST | `/notifications/{id}/read` | signed-in user |

### Dashboard, search, lookups

| Method | Path | Required |
|---|---|---|
| GET | `/dashboard` | `dashboard.view` |
| GET | `/meta/options` | signed-in user |
| GET | `/meta/users` | signed-in user |
| GET | `/search` | signed-in user |

### CRM

| Method | Path | Required |
|---|---|---|
| GET | `/activities` | signed-in user |
| POST | `/activities` | signed-in user |
| DELETE | `/activities/{activity}` | signed-in user |
| GET | `/activities/{activity}` | signed-in user |
| PUT | `/activities/{activity}` | signed-in user |
| POST | `/activities/{activity}/complete` | signed-in user |
| GET | `/customers` | signed-in user |
| POST | `/customers` | signed-in user |
| DELETE | `/customers/{customer}` | signed-in user |
| GET | `/customers/{customer}` | signed-in user |
| PUT | `/customers/{customer}` | signed-in user |
| GET | `/customers/{customer}/contacts` | signed-in user |
| POST | `/customers/{customer}/contacts` | signed-in user |
| DELETE | `/customers/{customer}/contacts/{contact}` | signed-in user |
| PUT | `/customers/{customer}/contacts/{contact}` | signed-in user |
| GET | `/customers/{customer}/timeline` | signed-in user |
| GET | `/deals` | signed-in user |
| POST | `/deals` | signed-in user |
| GET | `/deals-board` | signed-in user |
| DELETE | `/deals/{deal}` | signed-in user |
| GET | `/deals/{deal}` | signed-in user |
| PUT | `/deals/{deal}` | signed-in user |
| POST | `/deals/{deal}/move` | signed-in user |
| GET | `/deals/{deal}/timeline` | signed-in user |
| GET | `/leads` | signed-in user |
| POST | `/leads` | signed-in user |
| GET | `/leads-board` | signed-in user |
| DELETE | `/leads/{lead}` | signed-in user |
| GET | `/leads/{lead}` | signed-in user |
| PUT | `/leads/{lead}` | signed-in user |
| POST | `/leads/{lead}/convert` | signed-in user |
| POST | `/leads/{lead}/move` | signed-in user |
| GET | `/leads/{lead}/timeline` | signed-in user |
| GET | `/pipelines` | `deals.view` |
| POST | `/pipelines` | `pipelines.manage` |
| DELETE | `/pipelines/{pipeline}` | `pipelines.manage` |
| PUT | `/pipelines/{pipeline}` | `pipelines.manage` |

### Notes & documents

| Method | Path | Required |
|---|---|---|
| DELETE | `/documents/{document}` | signed-in user |
| GET | `/documents/{document}/download` | signed-in user |
| DELETE | `/notes/{note}` | signed-in user |
| PUT | `/notes/{note}` | signed-in user |
| GET | `/{type}/{id}/documents` | signed-in user |
| POST | `/{type}/{id}/documents` | signed-in user |
| GET | `/{type}/{id}/notes` | signed-in user |
| POST | `/{type}/{id}/notes` | signed-in user |

### Tags & custom fields

| Method | Path | Required |
|---|---|---|
| GET | `/custom-fields` | signed-in user |
| POST | `/custom-fields` | `settings.manage` |
| DELETE | `/custom-fields/{customField}` | `settings.manage` |
| PUT | `/custom-fields/{customField}` | `settings.manage` |
| GET | `/tags` | signed-in user |
| POST | `/tags` | `settings.manage` |
| DELETE | `/tags/{tag}` | `settings.manage` |
| PUT | `/tags/{tag}` | `settings.manage` |

### HR

| Method | Path | Required |
|---|---|---|
| GET | `/departments` | `departments.view` |
| POST | `/departments` | `departments.manage` |
| DELETE | `/departments/{department}` | `departments.manage` |
| GET | `/departments/{department}` | `departments.view` |
| PUT | `/departments/{department}` | `departments.manage` |
| GET | `/employees` | signed-in user |
| POST | `/employees` | signed-in user |
| DELETE | `/employees/{employee}` | signed-in user |
| GET | `/employees/{employee}` | signed-in user |
| PUT | `/employees/{employee}` | signed-in user |
| GET | `/employees/{employee}/identifiers` | signed-in user |
| POST | `/employees/{employee}/identifiers` | signed-in user |
| DELETE | `/employees/{employee}/identifiers/{identifier}` | signed-in user |
| PUT | `/employees/{employee}/identifiers/{identifier}` | signed-in user |
| GET | `/employees/{employee}/payroll` | signed-in user |
| GET | `/employees/{employee}/penalties` | signed-in user |
| POST | `/employees/{employee}/photo` | signed-in user |
| POST | `/employees/{employee}/schedule` | signed-in user |
| GET | `/holidays` | `schedules.view` |
| POST | `/holidays` | `schedules.manage` |
| DELETE | `/holidays/{holiday}` | `schedules.manage` |
| PUT | `/holidays/{holiday}` | `schedules.manage` |
| GET | `/positions` | `positions.view` |
| POST | `/positions` | `positions.manage` |
| DELETE | `/positions/{position}` | `positions.manage` |
| GET | `/positions/{position}` | `positions.view` |
| PUT | `/positions/{position}` | `positions.manage` |
| GET | `/work-schedules` | `schedules.view` |
| POST | `/work-schedules` | `schedules.manage` |
| DELETE | `/work-schedules/{workSchedule}` | `schedules.manage` |
| GET | `/work-schedules/{workSchedule}` | `schedules.view` |
| PUT | `/work-schedules/{workSchedule}` | `schedules.manage` |

### Attendance

| Method | Path | Required |
|---|---|---|
| GET | `/attendance` | signed-in user |
| POST | `/attendance` | signed-in user |
| GET | `/attendance/calendar` | signed-in user |
| GET | `/attendance/dashboard` | signed-in user |
| GET | `/attendance/expected` | signed-in user |
| GET | `/attendance/monitor` | signed-in user |
| POST | `/attendance/recalculate` | signed-in user |
| DELETE | `/attendance/{attendance}` | signed-in user |
| GET | `/attendance/{attendance}` | signed-in user |
| PUT | `/attendance/{attendance}` | signed-in user |

### Attendance devices

| Method | Path | Required |
|---|---|---|
| GET | `/attendance-devices` | `devices.view` |
| POST | `/attendance-devices` | `devices.manage` |
| GET | `/attendance-devices/push-logs` | `devices.view` |
| DELETE | `/attendance-devices/{attendanceDevice}` | `devices.manage` |
| GET | `/attendance-devices/{attendanceDevice}` | `devices.view` |
| PUT | `/attendance-devices/{attendanceDevice}` | `devices.manage` |
| POST | `/attendance-devices/{attendanceDevice}/bridge-token` | `devices.manage` |
| POST | `/attendance-devices/{attendanceDevice}/push-employees` | `devices.manage` |
| POST | `/attendance-devices/{attendanceDevice}/sync` | `devices.sync` |
| POST | `/attendance-devices/{attendanceDevice}/test` | `devices.sync` |
| GET | `/attendance-raw-events` | `devices.view` |
| POST | `/attendance-raw-events/reprocess` | `devices.sync` |
| GET | `/attendance-raw-events/unmatched` | `devices.view` |
| POST | `/device-bridge/events` | public |
| POST/PUT | `/hikvision/push/{token}` | public |

### Penalties & payroll

| Method | Path | Required |
|---|---|---|
| GET | `/payroll` | `payroll.view` |
| POST | `/payroll` | `payroll.calculate` |
| PUT | `/payroll-records/{payrollRecord}` | `payroll.calculate` |
| POST | `/payroll/calculate` | `payroll.calculate` |
| DELETE | `/payroll/{payroll}` | `payroll.calculate` |
| GET | `/payroll/{payroll}` | `payroll.view` |
| POST | `/payroll/{payroll}/approve` | `payroll.approve` |
| POST | `/payroll/{payroll}/pay` | `payroll.approve` |
| GET | `/payroll/{payroll}/records` | `payroll.view` |
| POST | `/payroll/{payroll}/reopen` | `payroll.approve` |
| GET | `/penalties` | `penalties.view` |
| POST | `/penalties` | `penalties.manage` |
| POST | `/penalties/bulk-approve` | `penalties.approve` |
| POST | `/penalties/{penalty}/approve` | `penalties.approve` |
| POST | `/penalties/{penalty}/cancel` | `penalties.manage` |
| POST | `/penalties/{penalty}/reject` | `penalties.approve` |
| GET | `/penalty-rules` | `penalties.view` |
| POST | `/penalty-rules` | `penalties.manage` |
| DELETE | `/penalty-rules/{penaltyRule}` | `penalties.manage` |
| PUT | `/penalty-rules/{penaltyRule}` | `penalties.manage` |

### Reports

| Method | Path | Required |
|---|---|---|
| GET | `/reports/attendance/daily` | `reports.hr` |
| GET | `/reports/attendance/monthly` | `reports.hr` |
| GET | `/reports/payroll/{payroll}` | `payroll.view` |

### Administration

| Method | Path | Required |
|---|---|---|
| GET | `/audit-logs` | `audit.view` |
| GET | `/permissions` | `users.view` |
| GET | `/roles` | `users.view` |
| POST | `/roles` | `roles.manage` |
| DELETE | `/roles/{role}` | `roles.manage` |
| PUT | `/roles/{role}` | `roles.manage` |
| GET | `/settings/organization` | signed-in user |
| PUT | `/settings/organization` | `settings.manage` |
| POST | `/settings/organization/logo` | `settings.manage` |
| GET | `/system/health` | `system.health` |
| GET | `/users` | `users.view` |
| POST | `/users` | `users.manage` |
| DELETE | `/users/{user}` | `users.manage` |
| GET | `/users/{user}` | `users.view` |
| PUT | `/users/{user}` | `users.manage` |

### Platform (platform admins only)

| Method | Path | Required |
|---|---|---|
| GET | `/platform/health` | `platform.health` |
| GET | `/platform/organizations` | `platform.organizations.view` |
| POST | `/platform/organizations` | signed-in user |
| GET | `/platform/organizations/{organization}` | `platform.organizations.view` |
| PUT | `/platform/organizations/{organization}` | signed-in user |
| GET | `/platform/stats` | signed-in user |
