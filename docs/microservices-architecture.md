# PCMS Microservices Architecture

## Purpose

PCMS is migrating from a modular monolith to independently deployable service
boundaries because the project documentation requires a microservices
architecture. The migration uses the Strangler pattern: the existing interface
continues to operate while one business domain at a time moves behind a
versioned internal API.

Microservices do not automatically improve performance. Pagination, bounded
queries, reduced database round trips, short transactions, and representative
load testing remain separate engineering requirements.

## Target services

| Service | Intended ownership | Status |
| --- | --- | --- |
| Procurement | Procurement requests and delivery workflow | Extracted in V1 |
| Property Core | Inventory, Assets, assignment, return, lifecycle | V2 Gate 4 complete; Gate 5 UI cutover next |
| Operations | Maintenance and Audits | Planned |
| Intelligence | Dashboard, Reports, AI Insights, What-If | Planned |
| Integration | Other school systems and announcements | Planned |
| Identity & Access | Users, roles, authentication, MFA, sessions, security events | Planned last |

Inventory and Asset Registry must remain together in Property Core. Registering
an Asset locks one Inventory row, creates the Asset, and decrements stock in one
transaction. Eligible deletion restores one unit. Splitting those tables would
replace a reliable local transaction with a distributed transaction.

## Procurement V1 request flow

```text
Signed-in browser
    -> existing authenticated PCMS Procurement route
    -> CSRF and role/session validation
    -> internal ProcurementServiceClient
    -> Authorization: Bearer <server-only service token>
    -> Procurement Service /api/v1
    -> pcms_procurement_service database role
    -> Supabase PostgreSQL through the Session Pooler
```

Browser users never receive the internal service token or Procurement database
credentials. Their password, TOTP code, and MFA encryption key are never service
credentials. Mutations enter through existing PCMS routes, so browser CSRF
protection remains separate from service-to-service authentication.

The gateway supplies the authenticated employee ID as `X-PCMS-Actor`. The
service accepts it only after bearer authentication and records it in dated
property events.

## API conventions

The service is versioned under `/api/v1`. Responses use one envelope:

```json
{
  "success": true,
  "data": {},
  "error": null
}
```

Errors return `success: false`, `data: null`, a stable error code, a safe
message, and an appropriate HTTP status. SQL messages and stack traces are not
returned.

Endpoints:

- `GET /health`
- `GET /api/v1/procurements`
- `GET /api/v1/procurements/summary`
- `GET /api/v1/procurements/{business-id}`
- `POST /api/v1/procurements/next-id`
- `POST /api/v1/procurements`
- `POST /api/v1/procurements/{business-id}/update`
- `POST /api/v1/procurements/{business-id}/delete`

The list endpoint defaults to 25 records, caps page size at 50, applies search
and filters in SQL, and returns matching totals. V1 intentionally uses
`LIMIT/OFFSET`; keyset pagination can be considered only if later measurements
show deep-page cost.

## Data ownership and transactions

After database cutover, the main `pcms_app` role cannot access the Procurement
table. The Procurement service has only the privileges needed for:

- Procurement CRUD and its business-ID sequences;
- Inventory SELECT/INSERT/UPDATE for atomic delivery adjustments;
- append-only Procurement/Inventory `property_events` inserts.

It has no access to users, MFA data, security events, Assets, Maintenance, or
Audits. Delivery still locks the Procurement and logical Inventory rows and
commits Procurement, Inventory, and event changes together. No HTTP call occurs
inside the database transaction.

The owner-run SQL is `database/microservices_v1_procurement.sql`. Phase 1
provisions the role and policies. The database owner sets the role password
through a restricted channel. Phase 2 revokes direct Procurement access from
`pcms_app` only after the service and gateway configuration have been verified.

## Local operation

Add the gateway values from `.env.example` to the ignored root `.env`. Copy
`services/procurement/.env.example` to the separately ignored
`services/procurement/.env` and add only the Procurement database connection
and matching service token there. This prevents the service process from
loading the main PCMS database password or MFA encryption key. Use a separate
random service bearer token of at least 32 characters. The token and database
password must never be printed, logged, or committed.

Run the service during development with:

```powershell
php -S 127.0.0.1:8101 services/procurement/router.php
```

The existing PCMS frontend remains at port 8000. Normal users do not browse to
port 8101. PHP's built-in server is suitable for functional development only;
concurrency claims require Apache or another production-like multi-worker
environment.

## Failure isolation

The gateway uses short connection and request timeouts and performs no infinite
retry. If Procurement is unavailable, Procurement pages display a useful
temporary-unavailability message. Dashboard and Reports mark Procurement data
unavailable instead of raising a raw fatal error. Other PCMS modules continue
to use their existing paths.

## Property Core V2 Gate 1

Property Core is scaffolded as a separate plain-PHP service on development port
`8102`. Gate 1 establishes only the versioned HTTP contract, isolated service
configuration, bearer-token validation, safe JSON envelopes, health endpoint,
gateway client, and permanent contract tests. It does not change Supabase,
production data, RLS, database grants, or existing Inventory and Asset Registry
runtime paths.

The service owns one future transactional boundary:

```text
Property Core
    -> Inventory
    -> Asset Registry
        -> registration
        -> assignment
        -> return
        -> eligible deletion/restoration
```

Inventory and Assets remain together because registration must lock Inventory,
create exactly one Asset, and decrement quantity by exactly one in a local
transaction. Eligible deletion uses the consistent lock order Inventory first,
then Asset, and restores exactly one unit. No HTTP call will occur inside either
database transaction.

The Gate 1 API contract includes:

- `GET /health`
- `GET /api/v1/inventory`
- `GET /api/v1/inventory/search`
- `GET /api/v1/inventory/summary`
- `GET /api/v1/inventory/options`
- `GET /api/v1/inventory/{business-id}`
- `POST /api/v1/inventory/next-id`
- `POST /api/v1/inventory`
- `POST /api/v1/inventory/{business-id}/update`
- `POST /api/v1/inventory/{business-id}/delete`
- `GET /api/v1/assets`
- `GET /api/v1/assets/search`
- `GET /api/v1/assets/summary`
- `GET /api/v1/assets/options`
- `GET /api/v1/assets/filters`
- `GET /api/v1/assets/suggestions`
- `GET /api/v1/assets/{business-id}`
- `POST /api/v1/assets/next-id`
- `POST /api/v1/assets/register`
- `POST /api/v1/assets/{business-id}/update`
- `POST /api/v1/assets/{business-id}/assign`
- `POST /api/v1/assets/{business-id}/return`
- `POST /api/v1/assets/{business-id}/delete`

At Gate 1, the health endpoint reported `data_access: pending-provisioning`.
Authenticated
data routes intentionally fail closed until the owner-run Gate 2 migration and
dedicated `pcms_property_core_service` role exist. The service reads only its
separately ignored `services/property_core/.env`; it does not load the main PCMS
database password, MFA encryption key, or Procurement credentials.

The root application is prepared to call Property Core through
`PropertyCoreServiceClient` with bounded connection/request timeouts, no
redirect following, one separate server-only token, and the authenticated PCMS
employee ID as `X-PCMS-Actor`. Existing browser routes and modules are not yet
connected; authentication, authorization, and CSRF remain at the gateway when
Gate 5 performs that cutover.

Gate 2 will add the owner-run database role/RLS/index migration without revoking
current access. Procurement will retain only its existing narrow Inventory
permissions for atomic delivery adjustments. Asset access will be reduced in
stages because Operations and Intelligence still have documented direct Asset
dependencies until V3 and V4.

## Property Core V2 Gate 2

The additive migration `microservices_v2_property_core_phase1` is recorded in
Supabase from `database/microservices_v2_property_core.sql`. It creates
`pcms_property_core_service` as `NOLOGIN`, `NOINHERIT`, non-superuser, without
database creation, role creation, replication, or RLS-bypass privileges. No
password or service token is stored in SQL.

The role has only these explicit object privileges:

- Inventory and Assets: `SELECT`, `INSERT`, `UPDATE`, and `DELETE`;
- Maintenance and Audits: `SELECT` only, to enforce Asset lifecycle
  eligibility while those domains remain owned by the Operations code;
- Property Events: `INSERT` only, restricted by RLS to the `Inventory` and
  `Asset Registry` modules;
- `USAGE` only on the two Inventory sequences, two Asset sequences, and the
  Property Events sequence.

Inventory and Assets each have separate Property Core policies for SELECT,
INSERT, UPDATE, and DELETE. Maintenance and Audits have separate read-only
policies. Property Events has one append-only policy with a module constraint.
RLS remains enabled throughout, and `anon` and `authenticated` retain no direct
application-table privileges.

The approved query indexes are intentionally limited to:

- a GIN trigram expression index for the four existing Inventory search fields;
- a partial Inventory options index for rows where `quantity > 0`;
- a GIN trigram expression index for all approved Asset search fields,
  including Custodian.

`pg_trgm` is installed in the `extensions` schema using Supabase's default
version. Current tiny-table unused-index notices are informational and are not
evidence for removing indexes intended for the bounded V2 query contracts.

Gate 2 is pre-cutover. `pcms_app` still has its existing Inventory and Asset
access, and `pcms_procurement_service` still has Inventory SELECT/INSERT/UPDATE
for atomic delivery adjustments. The Property Core role remains `NOLOGIN`; no
dedicated password/token exists and the service remains disconnected until the
separately gated credential and read-only verification work in Gate 3.

## Property Core V2 Gate 3

Gate 3 activates the dedicated `pcms_property_core_service` login through the
Supabase Session Pooler and runs the service on `127.0.0.1:8102`. The database
password and the internal bearer token are separate cryptographically random
credentials. The database password exists only in the ignored service
environment file; the main application receives only the service URL, matching
bearer token, and bounded timeout settings. Both environment files use
restricted local Windows ACLs.

The live read-only contract now provides:

- Inventory list/search with SQL-side category and condition filters;
- Asset list/search with SQL-side category, status, and location filters;
- Custodian, employee ID, department, supplier, and other approved Asset text
  fields in the combined search;
- 25-row default and 50-row maximum server pagination;
- Inventory and Asset details by business ID rather than internal row ID;
- bounded Inventory/Asset options, Asset filter values, and allowlisted
  brand/model/supplier suggestions;
- coarse Inventory and Asset summaries.

All queries use explicit projections and deterministic ordering. General list
and detail responses do not expose internal numeric IDs. The Asset options
contract temporarily returns `asset_row_id` only because Maintenance and Audit
still use the existing numeric foreign key; that transitional field can be
removed after the Operations extraction.

Health now reports `data_access: read-only`. Missing or incorrect bearer
credentials are rejected, searches longer than 200 characters are rejected,
and invalid Asset status or suggestion fields return stable client errors.
The database identity was operationally verified as non-superuser,
`NOINHERIT`, without role/database creation, replication, or RLS bypass.
Actual forbidden reads from Users and writes to Maintenance were rejected by
PostgreSQL, in addition to privilege metadata checks.

Gate 3 does not cut over the Inventory or Asset Registry browser pages. It also
does not generate business IDs or perform registration, update, assignment,
return, or deletion. Those authenticated routes fail closed with
`PROPERTY_CORE_READ_ONLY_GATE`. Direct `pcms_app` Inventory/Asset privileges
and Procurement's narrow Inventory adjustment privileges remain unchanged.
Lifecycle implementation and its transactional tests belong to Gate 4.

## Property Core V2 Gate 4

Gate 4 implements the Property Core lifecycle engine behind the explicit
`PROPERTY_CORE_ALLOW_WRITES` environment switch. The committed example and
the live production service both keep this value `false`; database credentials
alone never activate write routes. Health reports `read-only` unless an
isolated environment deliberately enables lifecycle writes.

Implemented transactions include:

- manual Inventory create, update, and eligible delete with the existing
  logical-item uniqueness, quantity, linked-Asset, and historical-event rules;
- Asset registration from an authoritative locked Inventory row, with one
  Asset insert, one-unit stock decrement guarded by `quantity > 0`, and the
  existing Asset Registry and Inventory events;
- Asset editing without changing its business ID or Inventory relationship;
- assignment and return with locked-state, Maintenance, Lost, and status
  revalidation while leaving Inventory unchanged;
- eligible Asset deletion with Maintenance/Audit history checks, two lifecycle
  events, one Asset deletion, and exactly one restored Inventory unit.

Every operation uses the shared transaction runner. Any throwable before the
final commit triggers rollback. No external call, recursive service request,
test delay, or runtime failure-injection parameter exists inside a transaction.
Duplicate business IDs remain protected by PostgreSQL unique constraints, and
registration revalidates locked quantity so concurrent requests cannot reduce
Inventory below zero.

Asset deletion intentionally improves the legacy lock order. It first reads the
relationship, then locks and revalidates in the canonical order:

```text
Inventory row
    -> Asset row
```

All Property Core operations that require both records must retain that order.
This prevents delete/register interactions from introducing opposite-order
deadlock risk.

Deterministic tests cover input normalization, assignment/return/deletion state
rules, transaction commit/rollback control, SQL transaction structure, event
placement, negative-stock guards, and canonical lock order. Live read-only
checks also confirm the intended CRUD metadata on Inventory/Assets,
Maintenance/Audit SELECT-only access, append-only Property Events, required
sequence usage, and the Event RLS module restriction.

### Gate 4B isolated operational verification

Gate 4B provisions the separate zero-cost Supabase project
`PCMS Gate 4B Test` (`tyxomlowtfjwoqofwhgl`). It contains the current schema,
Security V1/V2 definitions, Procurement ownership boundary, Property Core
Phase 1 role/RLS/indexes, test-only database credentials, and synthetic data
only. Its credentials are never shared with the production-connected service.
The committed `.env.test.example` documents the required variable names while
the real `.env.test` path is ignored.

The isolated database includes two explicitly marked `gate4b_test` triggers.
They are reachable only through session-local settings used by the operational
test and prove rollback after registration has inserted/decremented and after
deletion has removed an Asset but before stock restoration completes. They are
test infrastructure and must never be deployed to production.

The 31-assertion operational suite proves:

- real PostgreSQL Inventory create, update, and eligible delete;
- Asset registration decrements stock exactly once;
- two independent PHP processes racing for quantity one produce one success
  and one safe `INSUFFICIENT_INVENTORY_QUANTITY` rejection;
- the losing race leaves no Asset or event and Inventory never becomes
  negative;
- eligible Asset deletion restores exactly one unit, while a repeated request
  cannot restore or emit events again;
- assignment and return preserve Inventory quantity and apply the existing
  custody/status transitions;
- forced registration and deletion failures roll back every table change;
- Property Events accepts only Inventory and Asset Registry for the dedicated
  service identity and rejects Procurement, Maintenance, Audit, and Users;
- concurrent registration/deletion on the same Inventory row completes with
  the canonical Inventory-then-Asset lock order and no deadlock.

The suite removes all synthetic fixtures after every run. The isolated
security advisor reports no findings; its unused-index notices are expected
for a newly created, empty test database. Production remained read-only and
unchanged at 5 Inventory records / 10 units, 2 Assets, and 25 Property Events,
with no `INV-99%` or `AST-99%` fixtures. Gate 4 is therefore complete. Gate 5
UI cutover is now the next authorized implementation boundary; permission
revocation remains deferred until the later cutover gates pass.

## Property Core V2 Gate 5

Gate 5 moves the normal Inventory and Asset Registry browser workflows behind
the internal Property Core gateway. The browser still talks only to the PCMS
web application. The server-side gateway attaches the private bearer token and
the authenticated employee ID, then calls the service on `127.0.0.1:8102`.
No token, service database credential, or direct service URL is rendered into
page markup or JavaScript.

The cutover includes:

- SQL-side search and filter combinations with 25-row server pagination;
- result counts and filter-preserving Previous/Next/page links;
- list projections limited to table-display fields;
- Inventory and Asset details fetched by business ID only when a user opens a
  View, Edit, or Assign action;
- Inventory and Asset create/update/delete lifecycle requests sent only to the
  Property Core service, with existing authentication, POST, and CSRF checks
  retained at the web gateway;
- Asset registration using a debounced, cancellable Inventory search that
  requires two characters and returns at most 10 available choices;
- responsive, keyboard-focusable table scroll regions that keep narrow pages
  within the viewport while retaining every table column;
- brand, model, and supplier suggestions forwarded through the service;
- safe unavailability messages with no fallback to `pcms_app` SQL.

The live service is deliberately enabled for writes only after the read
cutover, permanent tests, and role/privilege checks pass. The committed example
continues to default `PROPERTY_CORE_ALLOW_WRITES=false`, so a new environment
cannot activate mutations accidentally. The production verification baseline
remains 5 Inventory records / 10 units, 2 Assets, and 25 Property Events, with
no isolated-test fixtures.

The signed-in browser regression verifies combined filters, business-ID rows,
on-demand Inventory and Asset detail modals, edit-form population, ID issuance,
the bounded registration selector, narrow-screen table access, clean browser
logs, service unavailability messaging, and automatic recovery after the
service restarts. No production business record is created, edited, assigned,
returned, or deleted during that regression.

Gate 5 does not revoke `pcms_app` privileges. Direct Asset and Inventory
dependencies outside the two owner modules remain temporarily documented:

- Maintenance and Audit forms, tables, and lifecycle handlers read or update
  Assets; these move with the Operations extraction in Microservices V3.
- Dashboard, Reports, and AI functions read Inventory and Assets; these move
  with the Intelligence extraction in Microservices V4.
- Procurement retains its dedicated narrow Inventory transaction access so a
  delivery and its stock adjustment remain atomic within that service.
- shared legacy event/asset helpers still contain Inventory compatibility
  paths used by domains that have not yet been extracted.

Therefore Gate 5 establishes the Property Core service as the exclusive path
for the normal Inventory and Asset Registry UI while preserving the temporary
cross-domain permissions required for later gates. Permission revocation and
removal of compatibility helpers are explicitly deferred.

## Future extraction order

The recommended order is Property Core, Operations, Intelligence, Integration,
then Identity & Access. Identity remains last because authentication, TOTP MFA,
session revocation, and security auditing already protect every current module;
moving them first would add risk before the service infrastructure is proven.

This architecture provides controlled data ownership, failure isolation,
versioned contracts, and potential independent scaling. It does not claim zero
lag, unlimited users, or guaranteed performance.
