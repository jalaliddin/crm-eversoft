# Attendance engine

## Pipeline

```text
Terminal punch
  → attendance_raw_events   (stored once per device + external id; the raw payload is kept)
  → resolve employee        (employee_device_identifiers: device-specific first, then org-wide; card number fallback)
  → business date           (overnight shifts: punches up to 6 h after the shift end belong to the start day)
  → recompute record        (first punch = check-in, last punch = check-out, from all punches of that day)
  → metrics                 (AttendanceCalculator — pure, unit-tested)
  → penalties               (PenaltyService — tenant rules)
  → notifications           (late / early leave; absence digest)
```

Processing is **idempotent**. A record is always recomputed from the complete set of punches for its
business date, so re-syncing, re-processing or receiving events late all converge to the same result.
Unknown identifiers are stored as `unmatched`. Admins map them under
**Settings → Attendance devices → Unmatched punches**, and reprocessing then applies them.

## Rules (per work schedule)

The reference day is scheduled 08:30–17:30 with a 13:00–14:00 break and 10 minutes of grace.

| Situation | Result |
|---|---|
| In 08:30 | late 0 |
| In 08:35 | late 0 (within grace) |
| In 08:45, mode `after_grace` (default) | late **5** (minutes beyond start + grace) |
| In 08:45, mode `from_start` | late **15** (once grace is exceeded, counted from the start) |
| Out 17:20 | early leave 10 |
| 08:42 → 17:20 | worked 7 h 38 m (break window overlap deducted) |
| Worked more than required, overtime on | overtime = worked − required, if ≥ threshold |
| Punches on a weekend or holiday | present, all worked time is overtime (if enabled) |
| Check-in only, day still running | shown as *working now*; status late or present |
| Check-in only, day closed | `incomplete` |
| No punches, working day, day closed | `absent` (if *auto mark absent* is on) |
| Employee status `on_leave` | `on_leave` (holidays and weekends take precedence) |

The two lateness modes exist because the specification contains both conventions; each schedule
chooses one. The minutes are truncated: 08:45:59 against 08:40 is 5 minutes.

**Schedule types:**

- `fixed`: hours per weekday.
- `shift`: same as fixed, but may cross midnight (the end time is earlier than the start).
- `flexible`: no fixed hours, only `required_daily_minutes`; early leave is the shortfall.

**Breaks:** a break window (`break_start`/`break_end`) deducts only the part that overlaps the worked
span. Plain `break_minutes` are deducted when the span is longer than twice the break.

**Duplicate scans:** punches within `duplicate_punch_window_seconds` (default 60) of the first punch
are not a check-out. Raw duplicates (same device + event id) are rejected by a unique index.

## Closing the day

`attendance:close-day` runs hourly and closes *yesterday* in each organization's own timezone. It
remembers which days are done and is safe to re-run (`--force` re-closes, `--date=` targets a
specific day). Closing:

- turns open check-ins into `incomplete`;
- creates `absent` / `on_leave` records for expected employees without punches;
- sends one absence digest to users with `attendance.view`.

Weekends and holidays are **not** stored per employee. The calendar and reports derive them from
schedules and `holidays`, which keeps the table proportional to real events.

## Manual correction

HR users with `attendance.correct` can edit check-in, check-out, status and notes, and HR users with
`attendance.manage` can create records for missed days. A **reason is mandatory**. Each change writes:

- `edited_by`, `edited_at` and `edit_reason` on the record;
- an `attendance_audit_logs` row with old and new values;
- a generic `audit_logs` entry (`attendance.corrected`).

After a manual correction, device punches no longer overwrite that record's times. Only its metrics
are recalculated. After changing a schedule or rule, use **Attendance → Recalculate period**
(`POST /attendance/recalculate`) to apply it to past days.

## Timezone strategy

| Kind of value | Stored as | Example |
|---|---|---|
| Instants (punches, check-in/out, scheduled start/end, created_at) | **UTC** `timestamp` | `2026-09-21 03:45:10` |
| Business dates (`attendance_records.date`, payroll periods, holidays) | local `date`, no timezone | `2026-09-21` |
| Schedule times | local wall-clock `time` | `08:30` |

- `config('app.timezone')` is always `UTC`, and the database connection runs in UTC. `APP_TIMEZONE`
  only sets the default timezone for **new** organizations (Asia/Tashkent).
- Calculations happen in the schedule timezone, or else the organization's timezone
  (`DaySchedule`). A schedule's local times are converted to instants for the business date before
  any comparison.
- Devices send ISO-8601 times with an offset. Times without one are read in the device's configured
  timezone (`settings.timezone`), falling back to the organization's.
- The API returns instants as UTC (`…Z`) and dates as `Y-m-d`. The frontend renders instants in the
  **organization** timezone, not the browser's, so HR staff abroad see the same times.
- Dashboards bucket by the tenant's current UTC offset in SQL (Asia/Tashkent has no DST).

## Scale

- Indexes: `attendance_records(employee_id,date)` unique, `(organization_id,date,status)`;
  raw events `(device_id,external_event_id)` unique, `(organization_id,processing_status)`,
  `(organization_id,employee_id,event_time)`.
- Ingestion uses batched `insertOrIgnore` of 500 rows. Processing and day closing use
  `chunkById(500)`. Schedules and assignments are preloaded per chunk.
- Device sync runs as one unique queued job per device. A cache lock prevents overlapping syncs of
  the same device.
- The monitor summary is cached briefly (20 s for today, 5 min for past days), and the table is
  paginated server-side.

## Monitor

`GET /attendance/monitor` is stateless and polled at the tenant's `monitor_refresh_seconds`. Adding
WebSockets later only means pushing an invalidation event; the endpoint and UI stay the same. Live
statuses are *working now*, *left*, *not arrived yet* (before start + grace), *absent* (after
start + grace), *weekend*, *holiday* and *on leave*.
