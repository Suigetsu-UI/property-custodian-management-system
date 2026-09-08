-- Asset Lifecycle V1
-- Local-first schema foundation for factual Asset age and Administrator-managed
-- useful-life policy. This migration contains no institutional useful-life
-- assumptions; categories remain unclassified until configured.

BEGIN;

CREATE TABLE IF NOT EXISTS public.asset_lifecycle_settings (
    id smallint PRIMARY KEY DEFAULT 1,
    aging_threshold_percent smallint NOT NULL DEFAULT 80,
    updated_by character varying(50),
    updated_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT asset_lifecycle_settings_singleton_check CHECK (id = 1),
    CONSTRAINT asset_lifecycle_settings_aging_threshold_check
        CHECK (aging_threshold_percent BETWEEN 1 AND 99)
);

INSERT INTO public.asset_lifecycle_settings (
    id,
    aging_threshold_percent
) VALUES (1, 80)
ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS public.asset_category_useful_life (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category character varying(100) NOT NULL,
    useful_life_months integer NOT NULL,
    remarks text,
    updated_by character varying(50),
    updated_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT asset_category_useful_life_months_check
        CHECK (useful_life_months BETWEEN 1 AND 1200),
    CONSTRAINT asset_category_useful_life_category_check
        CHECK (length(trim(category)) BETWEEN 1 AND 100),
    CONSTRAINT asset_category_useful_life_remarks_check
        CHECK (remarks IS NULL OR length(remarks) <= 1000)
);

CREATE UNIQUE INDEX IF NOT EXISTS asset_category_useful_life_category_uidx
    ON public.asset_category_useful_life (lower(category));

CREATE TABLE IF NOT EXISTS public.asset_lifecycle_config_events (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    setting_type character varying(30) NOT NULL,
    category character varying(100),
    old_value character varying(100),
    new_value character varying(100) NOT NULL,
    changed_by character varying(50) NOT NULL,
    description text,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT asset_lifecycle_config_events_type_check
        CHECK (setting_type IN ('AGING_THRESHOLD', 'CATEGORY_USEFUL_LIFE')),
    CONSTRAINT asset_lifecycle_config_events_actor_check
        CHECK (length(trim(changed_by)) BETWEEN 1 AND 50)
);

CREATE INDEX IF NOT EXISTS asset_lifecycle_config_events_created_at_idx
    ON public.asset_lifecycle_config_events (created_at DESC);

ALTER TABLE public.asset_lifecycle_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.asset_category_useful_life ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.asset_lifecycle_config_events ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.asset_lifecycle_settings FROM anon, authenticated;
REVOKE ALL ON public.asset_category_useful_life FROM anon, authenticated;
REVOKE ALL ON public.asset_lifecycle_config_events FROM anon, authenticated;

REVOKE ALL ON public.asset_lifecycle_settings
    FROM pcms_property_core_service;
REVOKE ALL ON public.asset_category_useful_life
    FROM pcms_property_core_service;
REVOKE ALL ON public.asset_lifecycle_config_events
    FROM pcms_property_core_service;

GRANT SELECT, UPDATE ON public.asset_lifecycle_settings
    TO pcms_property_core_service;
GRANT SELECT, INSERT, UPDATE ON public.asset_category_useful_life
    TO pcms_property_core_service;
GRANT SELECT, INSERT ON public.asset_lifecycle_config_events
    TO pcms_property_core_service;
GRANT USAGE, SELECT ON SEQUENCE
    public.asset_category_useful_life_id_seq,
    public.asset_lifecycle_config_events_id_seq
    TO pcms_property_core_service;

DROP POLICY IF EXISTS property_core_lifecycle_settings_select
    ON public.asset_lifecycle_settings;
CREATE POLICY property_core_lifecycle_settings_select
    ON public.asset_lifecycle_settings
    FOR SELECT TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_lifecycle_settings_update
    ON public.asset_lifecycle_settings;
CREATE POLICY property_core_lifecycle_settings_update
    ON public.asset_lifecycle_settings
    FOR UPDATE TO pcms_property_core_service
    USING (id = 1)
    WITH CHECK (id = 1);

DROP POLICY IF EXISTS property_core_useful_life_select
    ON public.asset_category_useful_life;
CREATE POLICY property_core_useful_life_select
    ON public.asset_category_useful_life
    FOR SELECT TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_useful_life_insert
    ON public.asset_category_useful_life;
CREATE POLICY property_core_useful_life_insert
    ON public.asset_category_useful_life
    FOR INSERT TO pcms_property_core_service
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_useful_life_update
    ON public.asset_category_useful_life;
CREATE POLICY property_core_useful_life_update
    ON public.asset_category_useful_life
    FOR UPDATE TO pcms_property_core_service
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_lifecycle_events_select
    ON public.asset_lifecycle_config_events;
CREATE POLICY property_core_lifecycle_events_select
    ON public.asset_lifecycle_config_events
    FOR SELECT TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_lifecycle_events_insert
    ON public.asset_lifecycle_config_events;
CREATE POLICY property_core_lifecycle_events_insert
    ON public.asset_lifecycle_config_events
    FOR INSERT TO pcms_property_core_service
    WITH CHECK (true);

COMMIT;
