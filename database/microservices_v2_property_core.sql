-- PCMS Microservices V2: Property Core Service ownership
-- Definitions only. Run each phase as the authorized Supabase database owner.
-- Never place the database-role password or service bearer token in this file.

-- -------------------------------------------------------------------------
-- PHASE 1 / GATE 2: Additive role, RLS, and approved indexes.
-- This phase preserves every existing pcms_app and Procurement Service grant.
-- -------------------------------------------------------------------------
BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'pcms_property_core_service'
    ) THEN
        CREATE ROLE pcms_property_core_service
            NOLOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOINHERIT
            NOREPLICATION
            NOBYPASSRLS;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE postgres TO pcms_property_core_service;
GRANT USAGE ON SCHEMA public TO pcms_property_core_service;

-- Start from no explicit application-object privileges, then add only the
-- operations required by the frozen Property Core lifecycle.
REVOKE ALL PRIVILEGES
    ON ALL TABLES IN SCHEMA public
    FROM pcms_property_core_service;

REVOKE ALL PRIVILEGES
    ON ALL SEQUENCES IN SCHEMA public
    FROM pcms_property_core_service;

GRANT SELECT, INSERT, UPDATE, DELETE
    ON TABLE public.inventory, public.assets
    TO pcms_property_core_service;

-- Assignment, return, and eligible deletion must inspect these domains, but
-- Operations ownership remains with pcms_app until Microservices V3.
GRANT SELECT
    ON TABLE public.maintenance, public.audits
    TO pcms_property_core_service;

-- Property Core records only Inventory and Asset Registry lifecycle events.
GRANT INSERT
    ON TABLE public.property_events
    TO pcms_property_core_service;

-- nextval() and serial defaults require USAGE. SELECT is intentionally not
-- granted because Property Core does not inspect sequence state.
GRANT USAGE ON SEQUENCE
    public.inventory_id_seq,
    public.inventory_id_seq1,
    public.asset_id_seq,
    public.assets_id_seq,
    public.property_events_id_seq
    TO pcms_property_core_service;

ALTER TABLE public.inventory ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.assets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.maintenance ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audits ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.property_events ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS property_core_inventory_select
    ON public.inventory;
CREATE POLICY property_core_inventory_select
    ON public.inventory
    FOR SELECT
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_inventory_insert
    ON public.inventory;
CREATE POLICY property_core_inventory_insert
    ON public.inventory
    FOR INSERT
    TO pcms_property_core_service
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_inventory_update
    ON public.inventory;
CREATE POLICY property_core_inventory_update
    ON public.inventory
    FOR UPDATE
    TO pcms_property_core_service
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_inventory_delete
    ON public.inventory;
CREATE POLICY property_core_inventory_delete
    ON public.inventory
    FOR DELETE
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_assets_select
    ON public.assets;
CREATE POLICY property_core_assets_select
    ON public.assets
    FOR SELECT
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_assets_insert
    ON public.assets;
CREATE POLICY property_core_assets_insert
    ON public.assets
    FOR INSERT
    TO pcms_property_core_service
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_assets_update
    ON public.assets;
CREATE POLICY property_core_assets_update
    ON public.assets
    FOR UPDATE
    TO pcms_property_core_service
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_assets_delete
    ON public.assets;
CREATE POLICY property_core_assets_delete
    ON public.assets
    FOR DELETE
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_maintenance_select
    ON public.maintenance;
CREATE POLICY property_core_maintenance_select
    ON public.maintenance
    FOR SELECT
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_audits_select
    ON public.audits;
CREATE POLICY property_core_audits_select
    ON public.audits
    FOR SELECT
    TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_events_insert
    ON public.property_events;
CREATE POLICY property_core_events_insert
    ON public.property_events
    FOR INSERT
    TO pcms_property_core_service
    WITH CHECK (
        module IN ('Inventory', 'Asset Registry')
    );

-- Supabase provides pg_trgm for indexed case-insensitive substring search.
-- No VERSION clause is used because Supabase installs the current default.
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA extensions;

-- Supports Inventory substring search across the existing visible fields:
-- Inventory ID, Asset Name, Category, and Condition.
CREATE INDEX IF NOT EXISTS inventory_property_core_search_trgm_idx
    ON public.inventory
    USING gin (
        (
            lower(
                coalesce(inventory_id, '') || ' ' ||
                coalesce(asset_name, '') || ' ' ||
                coalesce(category, '') || ' ' ||
                coalesce(condition, '')
            )
        ) extensions.gin_trgm_ops
    );

COMMENT ON INDEX public.inventory_property_core_search_trgm_idx IS
    'Supports Property Core case-insensitive Inventory substring search.';

-- Supports bounded registration options that always require quantity > 0.
CREATE INDEX IF NOT EXISTS inventory_property_core_available_options_idx
    ON public.inventory (
        lower(asset_name),
        lower(category),
        id
    )
    WHERE quantity > 0;

COMMENT ON INDEX public.inventory_property_core_available_options_idx IS
    'Supports bounded Asset-registration Inventory options with quantity > 0.';

-- Preserves Asset Registry substring search, including Custodian, while also
-- covering every currently approved textual search field.
CREATE INDEX IF NOT EXISTS assets_property_core_search_trgm_idx
    ON public.assets
    USING gin (
        (
            lower(
                coalesce(asset_id, '') || ' ' ||
                coalesce(asset_name, '') || ' ' ||
                coalesce(brand, '') || ' ' ||
                coalesce(model, '') || ' ' ||
                coalesce(serial_number, '') || ' ' ||
                coalesce(custodian, '') || ' ' ||
                coalesce(employee_id, '') || ' ' ||
                coalesce(department, '') || ' ' ||
                coalesce(supplier, '')
            )
        ) extensions.gin_trgm_ops
    );

COMMENT ON INDEX public.assets_property_core_search_trgm_idx IS
    'Supports Property Core case-insensitive Asset substring and Custodian search.';

COMMIT;

-- -------------------------------------------------------------------------
-- LATER CUTOVER PHASE -- DOCUMENTATION ONLY; DO NOT EXECUTE DURING GATE 2.
--
-- Inventory cutover, after Gates 3-7 pass:
--   * revoke pcms_app Inventory CRUD and Inventory sequence privileges;
--   * remove pcms_app_all_access from Inventory;
--   * retain Procurement Service SELECT/INSERT/UPDATE for atomic delivery.
--
-- Partial Asset cutover, after Gates 3-7 pass:
--   * revoke pcms_app Asset INSERT/DELETE and Asset sequence privileges;
--   * replace pcms_app_all_access with temporary SELECT and UPDATE policies;
--   * retain those two operations only for documented Operations/AI bridges;
--   * remove UPDATE in V3 and final SELECT in V4.
--
-- Exact executable cutover SQL will be added only after the preceding gates
-- verify service credentials, lifecycle behavior, UI integration, and outage
-- isolation. Keeping it out of Gate 2 prevents accidental early revocation.
-- -------------------------------------------------------------------------
