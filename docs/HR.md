# HR module

## Structure

- **Departments:** nested (`parent_id`, cycles rejected), optional head, unique code per tenant.
- **Positions:** optionally tied to a department.
- **Employees:** personal and employment data, manager, status (`active, inactive, terminated,
  on_leave`) and pay (`monthly, daily, hourly` + amount). Login credentials belong to `users`; an
  employee can be linked to one user, who then sees "My attendance". Salary is returned only to users
  with `payroll.view`, and only they can change it. Setting the status to *terminated* fills the
  termination date.
- **Profile tabs:** overview, work schedule history, attendance calendar, payroll, penalties, terminal
  IDs, documents, notes, activity history (audit log).

## Work schedules

Schedules define per weekday whether it is a working day, plus start, end and break (window or
minutes). Per schedule they also set:

- grace
- early-leave tolerance
- lateness mode
- required daily minutes
- overtime (enabled, threshold)
- timezone
- default flag

Employees receive a schedule through dated assignments (`employee_work_schedules`). Assigning a new
one closes the previous assignment the day before. Employees without an assignment use the default
schedule; if there is none, working days follow the organization's work week with no fixed hours.

Holidays are per tenant and never count as absences.

See [ATTENDANCE.md](ATTENDANCE.md) for the calculation rules.

## Penalties

Rules (`attendance_penalty_rules`) are configured per tenant:

- **type:** `late`, `early_leave`, `absence`
- **range:** `min_minutes`–`max_minutes`
- **calculation:**
  - `fixed`: amount
  - `per_minute`: amount × minutes
  - `percent_of_daily`: % of one day's pay
  - `daily_salary`: one day's pay

For each incident the **most specific** rule applies (the highest `min_minutes` that matches). Tiers
such as "late > 15 min" and "late > 60 min" therefore never stack.

- Automatic penalties are idempotent per attendance record and type. While they are *pending* they
  follow corrections: they update or disappear when the record changes. Approved and rejected ones
  are never touched automatically.
- Manual penalties can be added by HR.
- Approval is done by users with `penalties.approve` (individually or in bulk), or automatically if
  the tenant enables *auto approve*.
- A day's pay is computed as follows:
  - monthly: salary ÷ scheduled working days of that month
  - daily: the rate
  - hourly: rate × required hours of that day

## Payroll

Payroll is calculation only, not accounting. Each period is a calendar month. The workflow is:

```text
open → calculated (recalculate any number of times) → approved (locked; can be reopened) → closed (paid)
```

| Pay type | Base |
|---|---|
| Monthly, *fixed* mode (default) | salary × scheduled days while employed ÷ scheduled days in the month (so mid-month hires and leavers are prorated); absences are handled by penalty rules |
| Monthly, *prorated* mode | salary × worked days ÷ scheduled days |
| Daily | rate × worked days |
| Hourly | rate × worked hours |

- **Worked days:** present, late and early-leave days, plus incomplete days if
  `count_incomplete_as_worked` is on.
- **Overtime pay** (optional): hourly equivalent × overtime hours × multiplier. Hourly staff get only
  the premium part, because the hours are already paid.
- **Net** = max(0, base + overtime + bonus − approved penalties). Pending penalties are not deducted.
- **Bonuses** are entered per record and survive recalculation.
- **Rights:** calculating needs `payroll.calculate`, approving and paying need `payroll.approve`. HR
  managers can calculate but not approve by default.
- **Report:** payroll per period, exportable to Excel, CSV or PDF.

## Reports

| Report | Columns |
|---|---|
| Daily attendance | employee, department, check-in, check-out, worked, late, early leave, status |
| Monthly attendance | work days, present, absent, late count and minutes, early leave count, worked and overtime hours |
| Payroll | base, worked, overtime, penalties, bonuses, net |

Every report exports to **Excel, CSV and PDF**. CSV is UTF-8 with a BOM and protected against formula
injection. Exports need `attendance.export` and are audit-logged.
