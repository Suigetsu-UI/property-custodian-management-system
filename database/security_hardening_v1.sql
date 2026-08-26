BEGIN;

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS session_version INTEGER NOT NULL DEFAULT 1;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'users_session_version_check'
          AND conrelid = 'public.users'::regclass
    ) THEN
        ALTER TABLE public.users
            ADD CONSTRAINT users_session_version_check
            CHECK (session_version > 0);
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.login_attempts (
    attempt_key VARCHAR(80) PRIMARY KEY,
    failure_count INTEGER NOT NULL DEFAULT 1
        CHECK (failure_count > 0),
    window_started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    locked_until TIMESTAMPTZ,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

DROP INDEX IF EXISTS public.login_attempts_locked_until_idx;

CREATE INDEX IF NOT EXISTS login_attempts_updated_at_idx
    ON public.login_attempts (updated_at);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_roles WHERE rolname = 'pcms_app'
    ) THEN
        CREATE ROLE pcms_app
            NOLOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOINHERIT
            NOBYPASSRLS;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE postgres TO pcms_app;
GRANT USAGE ON SCHEMA public TO pcms_app;
GRANT SELECT, INSERT, UPDATE, DELETE
    ON ALL TABLES IN SCHEMA public TO pcms_app;
GRANT USAGE, SELECT
    ON ALL SEQUENCES IN SCHEMA public TO pcms_app;

REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    REVOKE ALL ON TABLES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    REVOKE ALL ON SEQUENCES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO pcms_app;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO pcms_app;

ALTER TABLE public.assets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audits ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inventory ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.login_attempts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.maintenance ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.procurement ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.property_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS pcms_app_all_access ON public.assets;
DROP POLICY IF EXISTS pcms_app_all_access ON public.audits;
DROP POLICY IF EXISTS pcms_app_all_access ON public.inventory;
DROP POLICY IF EXISTS pcms_app_all_access ON public.login_attempts;
DROP POLICY IF EXISTS pcms_app_all_access ON public.maintenance;
DROP POLICY IF EXISTS pcms_app_all_access ON public.procurement;
DROP POLICY IF EXISTS pcms_app_all_access ON public.property_events;
DROP POLICY IF EXISTS pcms_app_all_access ON public.users;

CREATE POLICY pcms_app_all_access ON public.assets
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.audits
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.inventory
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.login_attempts
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.maintenance
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.procurement
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.property_events
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.users
    FOR ALL TO pcms_app USING (true) WITH CHECK (true);

COMMIT;
