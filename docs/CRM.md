# CRM module

## Entities

| Entity | Notes |
|---|---|
| Customers | Person or company. Has contacts (people at a company), tags, custom fields, notes, documents, timeline. Soft-deleted. |
| Leads | Statuses `new → contacted → qualified → proposal → negotiation → converted / lost`. Kanban board. Assignment requires `leads.assign` when assigning to someone else, and the assignee is notified. |
| Deals | Belong to a pipeline and a stage. `status` (open/won/lost) is derived from the stage *type*, so tenants can rename stages freely. `won_at`/`lost_at` are set automatically. Losing asks for a reason. |
| Pipelines | Tenant-specific, ordered stages. Each needs at least one open, one won and one lost stage. A stage with deals cannot be removed. One pipeline is the default. |
| Activities | `task, call, meeting, email, note`; priority and status; due time. Linked to a customer, lead and/or deal; an activity on a deal or lead inherits its customer. Due reminders are sent 15 minutes before. |
| Notes, documents | Polymorphic, on customers, leads, deals and employees. Files are validated by extension *and* sniffed MIME type (20 MB max) and stored outside the web root under `tenants/{id}/…`. They download only through the API after an authorization check. |
| Tags | Per tenant, colored. |
| Custom fields | Definitions per entity (`customer, lead, deal, employee`) with type `text, textarea, number, date, boolean, select`. Values live in a JSON column and are validated against the definitions. Unknown keys are dropped. |

## Lead conversion

`POST /leads/{id}/convert` creates a customer from the lead, or reuses the linked or chosen customer.
It can also create a deal in the default or a chosen pipeline, carrying over the value and owner, and
it marks the lead `converted`. Moving a lead to *Converted* on the board opens the convert dialog
instead of changing the status directly.

## Timeline

`TimelineRecorder` writes one event and links it to every entity it concerns, so a deal stage change
shows on the deal, its lead and its customer. Recorded events:

- `customer.created`, `customer.updated`
- `lead.created`, `lead.status_changed`
- `deal.created`, `deal.stage_changed`
- `activity.created`, `activity.completed`
- `note.created`, `document.uploaded`

## Visibility

Users without `crm.view_all` see only leads, deals and activities assigned to them or created by
them. This is enforced in queries (`visibleTo`) and in policies (show, update, delete).

## Dashboard

`GET /dashboard?from&to` returns only the sections the user may see:

- **Cards:** customers, new leads, open/won/lost deals with values, open and overdue tasks, due today.
- **Series:** customer growth (cumulative), lead funnel, deal pipeline by stage, won value per
  day/month.
- **HR block:** included for users with `attendance.view`.

Presets are today, this week, this month, this quarter, or a custom range, bucketed in the tenant
timezone.

## Search

`GET /search?q=` (Ctrl/⌘+K in the UI) searches customers, leads, deals and employees, respecting
permissions and visibility.
