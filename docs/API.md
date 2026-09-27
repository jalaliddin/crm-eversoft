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

## Endpoints

"Route permission" is enforced by middleware. Endpoints marked "—" are still checked in the
controller or policy: for example `customers.view` / `.create` / `.update` / `.delete`, own-record
visibility for leads, deals and activities, and `attendance.view` vs `attendance.view_own`.

### Health

| Method | Path | Route permission |
|---|---|---|
| GET | `/ping` | — |

### Authentication

| Method | Path | Route permission |
|---|---|---|
| POST | `/auth/forgot-password` | — |
| POST | `/auth/login` | — |
| POST | `/auth/logout` | — |
| GET | `/auth/me` | — |
| POST | `/auth/reset-password` | — |

### Profile & sessions

| Method | Path | Route permission |
|---|---|---|
| PUT | `/profile` | — |
| DELETE | `/profile/avatar` | — |
| POST | `/profile/avatar` | — |
| PUT | `/profile/password` | — |
| DELETE | `/profile/sessions` | — |
| GET | `/profile/sessions` | — |
| DELETE | `/profile/sessions/{id}` | — |

### Notifications

| Method | Path | Route permission |
|---|---|---|
| GET | `/notifications` | — |
| POST | `/notifications/read-all` | — |
| GET | `/notifications/unread-count` | — |
| POST | `/notifications/{id}/read` | — |

### Dashboard, search, lookups

| Method | Path | Route permission |
|---|---|---|
| GET | `/dashboard` | — |
| GET | `/meta/options` | — |
| GET | `/meta/users` | — |
| GET | `/search` | — |

### CRM

| Method | Path | Route permission |
|---|---|---|
| GET | `/activities` | — |
| POST | `/activities` | — |
| DELETE | `/activities/{activity}` | — |
| GET | `/activities/{activity}` | — |
| PUT | `/activities/{activity}` | — |
| POST | `/activities/{activity}/complete` | — |
| GET | `/customers` | — |
| POST | `/customers` | — |
| DELETE | `/customers/{customer}` | — |
| GET | `/customers/{customer}` | — |
| PUT | `/customers/{customer}` | — |
| GET | `/customers/{customer}/contacts` | — |
| POST | `/customers/{customer}/contacts` | — |
| DELETE | `/customers/{customer}/contacts/{contact}` | — |
| PUT | `/customers/{customer}/contacts/{contact}` | — |
| GET | `/customers/{customer}/timeline` | — |
| GET | `/deals` | — |
| POST | `/deals` | — |
| GET | `/deals-board` | — |
| DELETE | `/deals/{deal}` | — |
| GET | `/deals/{deal}` | — |
| PUT | `/deals/{deal}` | — |
| POST | `/deals/{deal}/move` | — |
| GET | `/deals/{deal}/timeline` | — |
| GET | `/leads` | — |
| POST | `/leads` | — |
| GET | `/leads-board` | — |
| DELETE | `/leads/{lead}` | — |
| GET | `/leads/{lead}` | — |
| PUT | `/leads/{lead}` | — |
| POST | `/leads/{lead}/convert` | — |
| POST | `/leads/{lead}/move` | — |
| GET | `/leads/{lead}/timeline` | — |
| GET | `/pipelines` | — |
| POST | `/pipelines` | — |
| DELETE | `/pipelines/{pipeline}` | — |
| PUT | `/pipelines/{pipeline}` | — |

### Notes & documents

| Method | Path | Route permission |
|---|---|---|
| DELETE | `/documents/{document}` | — |
| GET | `/documents/{document}/download` | — |
| DELETE | `/notes/{note}` | — |
| PUT | `/notes/{note}` | — |
| GET | `/{type}/{id}/documents` | — |
| POST | `/{type}/{id}/documents` | — |
| GET | `/{type}/{id}/notes` | — |
| POST | `/{type}/{id}/notes` | — |

### Tags & custom fields

| Method | Path | Route permission |
|---|---|---|
| GET | `/custom-fields` | — |
| POST | `/custom-fields` | — |
| DELETE | `/custom-fields/{customField}` | — |
| PUT | `/custom-fields/{customField}` | — |
| GET | `/tags` | — |
| POST | `/tags` | — |
| DELETE | `/tags/{tag}` | — |
| PUT | `/tags/{tag}` | — |

### HR

| Method | Path | Route permission |
|---|---|---|
| GET | `/departments` | — |
| POST | `/departments` | — |
| DELETE | `/departments/{department}` | — |
| GET | `/departments/{department}` | — |
| PUT | `/departments/{department}` | — |
| GET | `/employees` | — |
| POST | `/employees` | — |
| DELETE | `/employees/{employee}` | — |
| GET | `/employees/{employee}` | — |
| PUT | `/employees/{employee}` | — |
| GET | `/employees/{employee}/identifiers` | — |
| POST | `/employees/{employee}/identifiers` | — |
| DELETE | `/employees/{employee}/identifiers/{identifier}` | — |
| PUT | `/employees/{employee}/identifiers/{identifier}` | — |
| GET | `/employees/{employee}/payroll` | — |
| GET | `/employees/{employee}/penalties` | — |
| POST | `/employees/{employee}/photo` | — |
| POST | `/employees/{employee}/schedule` | — |
| GET | `/holidays` | — |
| POST | `/holidays` | — |
| DELETE | `/holidays/{holiday}` | — |
| PUT | `/holidays/{holiday}` | — |
| GET | `/positions` | — |
| POST | `/positions` | — |
| DELETE | `/positions/{position}` | — |
| GET | `/positions/{position}` | — |
| PUT | `/positions/{position}` | — |
| GET | `/work-schedules` | — |
| POST | `/work-schedules` | — |
| DELETE | `/work-schedules/{workSchedule}` | — |
| GET | `/work-schedules/{workSchedule}` | — |
| PUT | `/work-schedules/{workSchedule}` | — |

### Attendance

| Method | Path | Route permission |
|---|---|---|
| GET | `/attendance` | — |
| POST | `/attendance` | — |
| GET | `/attendance/calendar` | — |
| GET | `/attendance/dashboard` | — |
| GET | `/attendance/expected` | — |
| GET | `/attendance/monitor` | — |
| POST | `/attendance/recalculate` | — |
| DELETE | `/attendance/{attendance}` | — |
| GET | `/attendance/{attendance}` | — |
| PUT | `/attendance/{attendance}` | — |

### Attendance devices

| Method | Path | Route permission |
|---|---|---|
| GET | `/attendance-devices` | — |
| POST | `/attendance-devices` | — |
| DELETE | `/attendance-devices/{attendanceDevice}` | — |
| GET | `/attendance-devices/{attendanceDevice}` | — |
| PUT | `/attendance-devices/{attendanceDevice}` | — |
| POST | `/attendance-devices/{attendanceDevice}/bridge-token` | — |
| POST | `/attendance-devices/{attendanceDevice}/push-employees` | — |
| POST | `/attendance-devices/{attendanceDevice}/sync` | — |
| POST | `/attendance-devices/{attendanceDevice}/test` | — |
| GET | `/attendance-raw-events` | — |
| POST | `/attendance-raw-events/reprocess` | — |
| GET | `/attendance-raw-events/unmatched` | — |
| POST | `/device-bridge/events` | — |

### Penalties & payroll

| Method | Path | Route permission |
|---|---|---|
| GET | `/payroll` | — |
| POST | `/payroll` | — |
| PUT | `/payroll-records/{payrollRecord}` | — |
| POST | `/payroll/calculate` | — |
| DELETE | `/payroll/{payroll}` | — |
| GET | `/payroll/{payroll}` | — |
| POST | `/payroll/{payroll}/approve` | — |
| POST | `/payroll/{payroll}/pay` | — |
| GET | `/payroll/{payroll}/records` | — |
| POST | `/payroll/{payroll}/reopen` | — |
| GET | `/penalties` | — |
| POST | `/penalties` | — |
| POST | `/penalties/bulk-approve` | — |
| POST | `/penalties/{penalty}/approve` | — |
| POST | `/penalties/{penalty}/cancel` | — |
| POST | `/penalties/{penalty}/reject` | — |
| GET | `/penalty-rules` | — |
| POST | `/penalty-rules` | — |
| DELETE | `/penalty-rules/{penaltyRule}` | — |
| PUT | `/penalty-rules/{penaltyRule}` | — |

### Reports

| Method | Path | Route permission |
|---|---|---|
| GET | `/reports/attendance/daily` | — |
| GET | `/reports/attendance/monthly` | — |
| GET | `/reports/payroll/{payroll}` | — |

### Administration

| Method | Path | Route permission |
|---|---|---|
| GET | `/audit-logs` | — |
| GET | `/permissions` | — |
| GET | `/roles` | — |
| POST | `/roles` | — |
| DELETE | `/roles/{role}` | — |
| PUT | `/roles/{role}` | — |
| GET | `/settings/organization` | — |
| PUT | `/settings/organization` | — |
| POST | `/settings/organization/logo` | — |
| GET | `/system/health` | — |
| GET | `/users` | — |
| POST | `/users` | — |
| DELETE | `/users/{user}` | — |
| GET | `/users/{user}` | — |
| PUT | `/users/{user}` | — |

### Platform (platform admins only)

| Method | Path | Route permission |
|---|---|---|
| GET | `/platform/health` | — |
| GET | `/platform/organizations` | — |
| POST | `/platform/organizations` | — |
| GET | `/platform/organizations/{organization}` | — |
| PUT | `/platform/organizations/{organization}` | — |
| GET | `/platform/stats` | — |
