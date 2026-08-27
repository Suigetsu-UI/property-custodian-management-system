BEGIN;

-- =========================================================
-- Security V2: TOTP MFA storage
-- =========================================================

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS mfa_secret_enc TEXT,
    ADD COLUMN IF NOT EXISTS mfa_enrolled_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS mfa_last_used_step BIGINT;

-- Allow these three valid states:
--
-- 1. Disabled, no enrollment pending:
--      secret = NULL
--      enrolled_at = NULL
--      last_used_step = NULL
--
-- 2. Disabled, enrollment pending:
--      secret = encrypted value
--      enrolled_at = NULL
--      last_used_step = NULL
--
-- 3. Enabled:
--      secret = encrypted value
--      enrolled_at = timestamp
--      last_used_step = verified timestep

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'users_mfa_state_check'
          AND conrelid = 'public.users'::regclass
    ) THEN
        ALTER TABLE public.users
            ADD CONSTRAINT users_mfa_state_check
            CHECK (
                (
                    mfa_enabled = FALSE
                    AND mfa_enrolled_at IS NULL
                    AND mfa_last_used_step IS NULL
                )
                OR
                (
                    mfa_enabled = TRUE
                    AND mfa_secret_enc IS NOT NULL
                    AND mfa_enrolled_at IS NOT NULL
                    AND mfa_last_used_step IS NOT NULL
                )
            );
    END IF;
END
$$;

-- =========================================================
-- Append-only security audit events
-- =========================================================

CREATE TABLE IF NOT EXISTS public.security_events (
    id BIGSERIAL PRIMARY KEY,

    event_type VARCHAR(50) NOT NULL,

    actor_user_id INTEGER,
    target_user_id INTEGER NOT NULL,

    description TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT security_events_actor_user_fk
        FOREIGN KEY (actor_user_id)
        REFERENCES public.users(id)
        ON DELETE SET NULL,

    CONSTRAINT security_events_target_user_fk
        FOREIGN KEY (target_user_id)
        REFERENCES public.users(id)
        ON DELETE RESTRICT,

    CONSTRAINT security_events_type_check
        CHECK (
            event_type IN (
                'MFA_ENROLLED',
                'MFA_DISABLED',
                'MFA_RESET_BY_ADMIN',
                'MFA_CHALLENGE_BLOCKED'
            )
        )
);

CREATE INDEX IF NOT EXISTS security_events_target_user_idx
    ON public.security_events(target_user_id);

CREATE INDEX IF NOT EXISTS security_events_actor_user_idx
    ON public.security_events(actor_user_id);

CREATE INDEX IF NOT EXISTS security_events_created_at_idx
    ON public.security_events(created_at DESC);

-- =========================================================
-- RLS
-- =========================================================

ALTER TABLE public.security_events ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS security_events_pcms_app_select
    ON public.security_events;

CREATE POLICY security_events_pcms_app_select
    ON public.security_events
    FOR SELECT
    TO pcms_app
    USING (true);

DROP POLICY IF EXISTS security_events_pcms_app_insert
    ON public.security_events;

CREATE POLICY security_events_pcms_app_insert
    ON public.security_events
    FOR INSERT
    TO pcms_app
    WITH CHECK (true);

-- =========================================================
-- Explicit privilege model
-- =========================================================

REVOKE ALL
    ON TABLE public.security_events
    FROM PUBLIC;

REVOKE ALL
    ON TABLE public.security_events
    FROM anon, authenticated;

REVOKE ALL
    ON SEQUENCE public.security_events_id_seq
    FROM PUBLIC;

REVOKE ALL
    ON SEQUENCE public.security_events_id_seq
    FROM anon, authenticated;

GRANT SELECT, INSERT
    ON TABLE public.security_events
    TO pcms_app;

GRANT USAGE, SELECT
    ON SEQUENCE public.security_events_id_seq
    TO pcms_app;

-- Append-only enforcement for the application role.
REVOKE UPDATE, DELETE, TRUNCATE
    ON TABLE public.security_events
    FROM pcms_app;

COMMIT;
