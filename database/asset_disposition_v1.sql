-- Asset Disposition V1
-- Records externally authorized sale/bidding decisions without granting
-- institutional approval inside Smart AssetTrack.

BEGIN;

ALTER TABLE public.assets
    DROP CONSTRAINT IF EXISTS assets_status_check;

ALTER TABLE public.assets
    ADD CONSTRAINT assets_status_check
    CHECK (
        status IN (
            'Available',
            'Assigned',
            'Under Maintenance',
            'Lost',
            'Sold'
        )
    );

CREATE SEQUENCE IF NOT EXISTS public.asset_disposition_business_id_seq
    AS bigint START WITH 1 INCREMENT BY 1 MINVALUE 1 CACHE 1;

CREATE TABLE IF NOT EXISTS public.asset_dispositions (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    disposition_id character varying(20) NOT NULL UNIQUE,
    asset_id integer NOT NULL,

    requested_by character varying(50) NOT NULL,
    requested_at timestamp with time zone NOT NULL DEFAULT now(),
    reason text NOT NULL,
    proposed_method character varying(20) NOT NULL,
    request_remarks text,

    asset_age_months_snapshot integer,
    useful_life_months_snapshot integer,
    lifecycle_snapshot character varying(50) NOT NULL,
    latest_audit_id_snapshot character varying(20),
    latest_audit_result_snapshot character varying(30),
    latest_audit_date_snapshot date,

    status character varying(40) NOT NULL
        DEFAULT 'Pending Institutional Approval',

    institutional_approval_reference character varying(150),
    institutional_approver_name character varying(150),
    institutional_approver_position character varying(150),
    institutional_approval_date date,
    approval_remarks text,
    approval_recorded_by character varying(50),
    approval_recorded_at timestamp with time zone,

    completed_by character varying(50),
    completed_at timestamp with time zone,
    completion_reference character varying(150),
    completion_remarks text,

    status_note text,
    status_changed_by character varying(50) NOT NULL,
    status_changed_at timestamp with time zone NOT NULL DEFAULT now(),
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone NOT NULL DEFAULT now(),

    CONSTRAINT asset_dispositions_asset_fkey
        FOREIGN KEY (asset_id) REFERENCES public.assets(id)
        ON DELETE RESTRICT,
    CONSTRAINT asset_dispositions_business_id_check
        CHECK (disposition_id ~ '^DSP-[0-9]{6,}$'),
    CONSTRAINT asset_dispositions_method_check
        CHECK (proposed_method IN ('Sale', 'Bidding')),
    CONSTRAINT asset_dispositions_status_check
        CHECK (
            status IN (
                'Pending Institutional Approval',
                'Approved for Sale/Bidding',
                'Sold',
                'Rejected',
                'Cancelled'
            )
        ),
    CONSTRAINT asset_dispositions_reason_check
        CHECK (length(trim(reason)) BETWEEN 3 AND 2000),
    CONSTRAINT asset_dispositions_request_remarks_check
        CHECK (request_remarks IS NULL OR length(request_remarks) <= 2000),
    CONSTRAINT asset_dispositions_age_snapshot_check
        CHECK (asset_age_months_snapshot IS NULL OR asset_age_months_snapshot >= 0),
    CONSTRAINT asset_dispositions_life_snapshot_check
        CHECK (useful_life_months_snapshot IS NULL OR useful_life_months_snapshot > 0),
    CONSTRAINT asset_dispositions_approval_remarks_check
        CHECK (approval_remarks IS NULL OR length(approval_remarks) <= 2000),
    CONSTRAINT asset_dispositions_completion_remarks_check
        CHECK (completion_remarks IS NULL OR length(completion_remarks) <= 2000),
    CONSTRAINT asset_dispositions_status_note_check
        CHECK (status_note IS NULL OR length(status_note) <= 2000),
    CONSTRAINT asset_dispositions_approval_state_check
        CHECK (
            status NOT IN ('Approved for Sale/Bidding', 'Sold') OR (
                institutional_approval_reference IS NOT NULL AND
                institutional_approver_name IS NOT NULL AND
                institutional_approver_position IS NOT NULL AND
                institutional_approval_date IS NOT NULL AND
                approval_recorded_by IS NOT NULL AND
                approval_recorded_at IS NOT NULL
            )
        ),
    CONSTRAINT asset_dispositions_completion_state_check
        CHECK (
            status <> 'Sold' OR (
                completed_by IS NOT NULL AND
                completed_at IS NOT NULL AND
                completion_reference IS NOT NULL
            )
        )
);

CREATE INDEX IF NOT EXISTS asset_dispositions_asset_id_idx
    ON public.asset_dispositions (asset_id);

CREATE INDEX IF NOT EXISTS asset_dispositions_status_created_idx
    ON public.asset_dispositions (status, created_at DESC);

CREATE UNIQUE INDEX IF NOT EXISTS asset_dispositions_one_open_per_asset_uidx
    ON public.asset_dispositions (asset_id)
    WHERE status IN (
        'Pending Institutional Approval',
        'Approved for Sale/Bidding'
    );

CREATE INDEX IF NOT EXISTS assets_status_idx
    ON public.assets (status);

CREATE OR REPLACE FUNCTION public.prevent_sold_asset_changes()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = ''
AS $$
BEGIN
    IF OLD.status = 'Sold' THEN
        RAISE EXCEPTION 'A Sold Asset is a terminal historical record.'
            USING ERRCODE = '23514';
    END IF;

    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;

DROP TRIGGER IF EXISTS assets_sold_terminal_guard ON public.assets;
CREATE TRIGGER assets_sold_terminal_guard
    BEFORE UPDATE OR DELETE ON public.assets
    FOR EACH ROW
    EXECUTE FUNCTION public.prevent_sold_asset_changes();

ALTER TABLE public.asset_dispositions ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.asset_dispositions FROM PUBLIC, anon, authenticated;
REVOKE ALL ON public.asset_dispositions FROM pcms_app;
REVOKE ALL ON SEQUENCE public.asset_dispositions_id_seq
    FROM PUBLIC, anon, authenticated;
REVOKE ALL ON SEQUENCE public.asset_disposition_business_id_seq
    FROM PUBLIC, anon, authenticated;
REVOKE ALL ON SEQUENCE
    public.asset_dispositions_id_seq,
    public.asset_disposition_business_id_seq
    FROM pcms_app;

REVOKE ALL ON public.asset_dispositions FROM pcms_property_core_service;
GRANT SELECT, INSERT, UPDATE ON public.asset_dispositions
    TO pcms_property_core_service;
GRANT USAGE, SELECT ON SEQUENCE
    public.asset_dispositions_id_seq,
    public.asset_disposition_business_id_seq
    TO pcms_property_core_service;

DROP POLICY IF EXISTS property_core_dispositions_select
    ON public.asset_dispositions;
CREATE POLICY property_core_dispositions_select
    ON public.asset_dispositions
    FOR SELECT TO pcms_property_core_service
    USING (true);

DROP POLICY IF EXISTS property_core_dispositions_insert
    ON public.asset_dispositions;
CREATE POLICY property_core_dispositions_insert
    ON public.asset_dispositions
    FOR INSERT TO pcms_property_core_service
    WITH CHECK (true);

DROP POLICY IF EXISTS property_core_dispositions_update
    ON public.asset_dispositions;
CREATE POLICY property_core_dispositions_update
    ON public.asset_dispositions
    FOR UPDATE TO pcms_property_core_service
    USING (true)
    WITH CHECK (true);

COMMIT;
