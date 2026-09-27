# Multi-tenancy

Application-level multi-tenancy runs on a single database. Every tenant-owned table has an
`organization_id` foreign key to `organizations`. A user of tenant A can never read, change,
reference or export tenant B data. This is enforced in several independent layers, so one mistake
does not become a leak.

## Resolution

```text
Request → authenticate (Sanctum token) → organization = user.organization_id → TenantContext
```

- **Primary resolver:** the authenticated user's `organization_id`. Clients never send an
  organization id, and any they do send is ignored (not mass-assignable).
- **Host check:** on `{slug}.crm.eversoft.uz` or on a custom `organizations.domain`, the host's
  organization must equal the user's organization, otherwise the request gets 403 and a warning in
  the `security` log. Configure this with `TENANT_BASE_DOMAIN` and `TENANT_CENTRAL_DOMAINS`.
- **Platform users** (`organization_id = NULL`, `platform_role` set) get 403 on every tenant endpoint
  and only use `/api/v1/platform/*`.
- Suspending an organization blocks new logins and existing tokens (`ResolveTenant` checks the
  status on every request), and revokes all of its tokens.

`TenantContext` is a *scoped* singleton, so it is reset between requests and queued jobs, and the
middleware clears it after each response. Console commands and jobs set it explicitly with
`TenantContext::run($organization, fn () => …)`.

## Enforcement layers

| Layer | What it does |
|---|---|
| Global scope (`OrganizationScope`) | Every query on a tenant-owned model adds `where organization_id = <tenant>` |
| Route-model binding | Runs after `ResolveTenant`, so `/customers/{id}` of another tenant returns **404** |
| Model guard (`BelongsToOrganization`) | On create, fills `organization_id` from the context and throws `TenantMismatchException` if it differs. On update, `organization_id` cannot change. |
| Mass assignment | `organization_id` is not fillable on any tenant model, `User` or `Role` |
| Validation | Every client-supplied id (`customer_id`, `assigned_to`, `employee_id`, `tag_ids.*`, `role_ids.*`…) uses `TenantRule::exists()`, which constrains by organization |
| Policies | Check `model.organization_id === user.organization_id` as defense in depth, plus permission and CRM record visibility |
| Users | `users` has no global scope (login needs it), so every tenant user query goes through `User::inTenant()` |
| Exports & reports | Built from tenant-scoped services. Every export is audited. |
| Devices & bridge | Raw events carry the device's organization. A bridge token identifies exactly one device, and so one tenant. |
| Jobs & commands | Load models without scopes, then run the work inside `TenantContext::run()` for that device's or organization's tenant |

## Roles

- **Platform:** `platform_super_admin` manages organizations; `platform_support` has read-only access
  and health.
- **Tenant:** `owner`, `admin`, `hr_manager`, `manager`, `sales_manager`, `sales_agent`, `employee`.
  These are created per organization and editable, except `owner`, which always has everything. The
  last owner cannot be demoted or deleted.
- **Permissions** are granular keys (`customers.view`, `attendance.correct`, `payroll.approve`…),
  defined in `app/Support/Permissions.php`. Users can hold several roles, and permissions add up.
- **CRM visibility:** without `crm.view_all`, a user sees only leads, deals and activities assigned
  to them or created by them.

## Tests

`tests/Feature/TenantIsolationTest.php` and `tests/Feature/Attendance/AttendanceTenantIsolationTest.php`
cover:

- list filtering
- direct id access (404) on show, update, delete, timeline and notes
- ignored client `organization_id`
- foreign keys pointing to another tenant (422)
- users endpoint scoping
- search, dashboard and export leaks
- model-level cross-tenant writes
- `organization_id` immutability
- host/subdomain mismatch (403)
- platform vs tenant separation
- the same device identifier in two tenants
- attendance, monitor, device and raw-event endpoints
- bridge tokens pushing only into their own tenant

## Adding a tenant-owned table

1. Add `$table->foreignId('organization_id')->constrained()->cascadeOnDelete();` and index it,
   together with the columns you filter on.
2. Add `use BelongsToOrganization;` to the model, and keep `organization_id` out of `$fillable`.
3. Validate every foreign id with `TenantRule::exists('table')`.
4. Add an isolation test.
