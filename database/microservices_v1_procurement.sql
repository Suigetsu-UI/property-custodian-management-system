-- PCMS Microservices V1: Procurement Service ownership
-- Definitions only. Run each phase as the authorized Supabase database owner.
-- Never place the role password or service bearer token in this file.

-- -------------------------------------------------------------------------
-- PHASE 1: Provision the least-privilege role and RLS policies.
-- -------------------------------------------------------------------------
BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'pcms_procurement_service'
    ) THEN
        CREATE ROLE pcms_procurement_service
            NOLOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOINHERIT
            NOBYPASSRLS;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE postgres TO pcms_procurement_service;
GRANT USAGE ON SCHEMA public TO pcms_procurement_service;

GRANT SELECT, INSERT, UPDATE, DELETE
    ON TABLE public.procurement
    TO pcms_procurement_service;

GRANT SELECT, INSERT, UPDATE
    ON TABLE public.inventory
    TO pcms_procurement_service;

GRANT INSERT
    ON TABLE public.property_events
    TO pcms_procurement_service;

GRANT USAGE, SELECT ON SEQUENCE
    public.procurement_id_seq,
    public.procurement_id_seq1,
    public.inventory_id_seq,
    public.inventory_id_seq1,
    public.property_events_id_seq
    TO pcms_procurement_service;

ALTER TABLE public.procurement ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inventory ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.property_events ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS procurement_service_all
    ON public.procurement;

DROP POLICY IF EXISTS procurement_service_select
    ON public.procurement;
CREATE POLICY procurement_service_select
    ON public.procurement
    FOR SELECT
    TO pcms_procurement_service
    USING (true);

DROP POLICY IF EXISTS procurement_service_insert
    ON public.procurement;
CREATE POLICY procurement_service_insert
    ON public.procurement
    FOR INSERT
    TO pcms_procurement_service
    WITH CHECK (true);

DROP POLICY IF EXISTS procurement_service_update
    ON public.procurement;
CREATE POLICY procurement_service_update
    ON public.procurement
    FOR UPDATE
    TO pcms_procurement_service
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS procurement_service_delete
    ON public.procurement;
CREATE POLICY procurement_service_delete
    ON public.procurement
    FOR DELETE
    TO pcms_procurement_service
    USING (true);

DROP POLICY IF EXISTS procurement_service_inventory_select
    ON public.inventory;
CREATE POLICY procurement_service_inventory_select
    ON public.inventory
    FOR SELECT
    TO pcms_procurement_service
    USING (true);

DROP POLICY IF EXISTS procurement_service_inventory_insert
    ON public.inventory;
CREATE POLICY procurement_service_inventory_insert
    ON public.inventory
    FOR INSERT
    TO pcms_procurement_service
    WITH CHECK (true);

DROP POLICY IF EXISTS procurement_service_inventory_update
    ON public.inventory;
CREATE POLICY procurement_service_inventory_update
    ON public.inventory
    FOR UPDATE
    TO pcms_procurement_service
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS procurement_service_events_insert
    ON public.property_events;
CREATE POLICY procurement_service_events_insert
    ON public.property_events
    FOR INSERT
    TO pcms_procurement_service
    WITH CHECK (
        module IN ('Procurement', 'Inventory')
    );

REVOKE ALL ON TABLE public.users, public.security_events,
    public.assets, public.maintenance, public.audits
    FROM pcms_procurement_service;

COMMIT;

-- After Phase 1, the database owner must set an institution-managed password
-- through a restricted channel and enable LOGIN. Do not save that statement
-- with its real password in Git. Configure the matching PROCUREMENT_DB_*
-- values and PROCUREMENT_SERVICE_TOKEN in the ignored .env file.

-- -------------------------------------------------------------------------
-- PHASE 2: Exclusive data-access cutover.
-- Run only after the service can authenticate, /health succeeds, the list
-- endpoint succeeds, and the main PCMS .env points to the service.
-- -------------------------------------------------------------------------
BEGIN;

REVOKE SELECT, INSERT, UPDATE, DELETE
    ON TABLE public.procurement
    FROM pcms_app;

REVOKE USAGE, SELECT ON SEQUENCE
    public.procurement_id_seq,
    public.procurement_id_seq1
    FROM pcms_app;

DROP POLICY IF EXISTS pcms_app_all_access
    ON public.procurement;

-- Phase 2 removes only the main application's policy. The four
-- procurement_service_* policies remain the service's exclusive RLS path.

COMMIT;
