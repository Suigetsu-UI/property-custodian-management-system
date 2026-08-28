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
| Property Core | Inventory, Assets, assignment, return, lifecycle | Planned |
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

## Future extraction order

The recommended order is Property Core, Operations, Intelligence, Integration,
then Identity & Access. Identity remains last because authentication, TOTP MFA,
session revocation, and security auditing already protect every current module;
moving them first would add risk before the service infrastructure is proven.

This architecture provides controlled data ownership, failure isolation,
versioned contracts, and potential independent scaling. It does not claim zero
lag, unlimited users, or guaranteed performance.
