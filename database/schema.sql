-- Property Custodian Management System
-- Supabase PostgreSQL public schema (schema only)
-- Contains no table rows, user passwords, or connection credentials.

BEGIN;

SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET search_path = public, pg_catalog;

-- Sequences
CREATE SEQUENCE public."asset_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."assets_id_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."audit_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."audits_id_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."inventory_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."inventory_id_seq1"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."maintenance_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."maintenance_id_seq1"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."procurement_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."procurement_id_seq1"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."property_events_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."security_events_id_seq"
    AS bigint
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 9223372036854775807
    CACHE 1
    NO CYCLE;

CREATE SEQUENCE public."users_id_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    MINVALUE 1
    MAXVALUE 2147483647
    CACHE 1
    NO CYCLE;

-- Tables
CREATE TABLE public."assets" (
    "id" integer DEFAULT nextval('assets_id_seq'::regclass) NOT NULL,
    "asset_id" character varying(20) NOT NULL,
    "asset_name" character varying(150) NOT NULL,
    "category" character varying(100) NOT NULL,
    "brand" character varying(100),
    "model" character varying(100),
    "serial_number" character varying(100),
    "acquisition_date" date,
    "purchase_cost" numeric(12,2),
    "supplier" character varying(150),
    "location" character varying(150),
    "remarks" text,
    "inventory_id" integer NOT NULL,
    "status" character varying(30) DEFAULT 'Available'::character varying NOT NULL,
    "custodian" character varying(150),
    "employee_id" character varying(50),
    "department" character varying(150),
    "date_assigned" date,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."audits" (
    "id" integer DEFAULT nextval('audits_id_seq'::regclass) NOT NULL,
    "audit_id" character varying(20) NOT NULL,
    "asset_id" integer NOT NULL,
    "asset_name_snap" character varying(150),
    "category_snap" character varying(100),
    "custodian_snap" character varying(150),
    "auditor" character varying(150) NOT NULL,
    "audit_date" date,
    "result" character varying(30) NOT NULL,
    "remarks" text,
    "status" character varying(20) DEFAULT 'Scheduled'::character varying NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."inventory" (
    "id" integer DEFAULT nextval('inventory_id_seq1'::regclass) NOT NULL,
    "inventory_id" character varying(20) NOT NULL,
    "asset_name" character varying(150) NOT NULL,
    "category" character varying(100) NOT NULL,
    "quantity" integer DEFAULT 0 NOT NULL,
    "condition" character varying(50) DEFAULT 'Good'::character varying NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."login_attempts" (
    "attempt_key" character varying(80) NOT NULL,
    "failure_count" integer DEFAULT 1 NOT NULL,
    "window_started_at" timestamp with time zone DEFAULT now() NOT NULL,
    "locked_until" timestamp with time zone,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."maintenance" (
    "id" integer DEFAULT nextval('maintenance_id_seq1'::regclass) NOT NULL,
    "maintenance_id" character varying(20) NOT NULL,
    "asset_id" integer NOT NULL,
    "asset_name_snap" character varying(150),
    "category_snap" character varying(100),
    "custodian_snap" character varying(150),
    "maintenance_type" character varying(50) NOT NULL,
    "scheduled_date" date,
    "status" character varying(20) DEFAULT 'Scheduled'::character varying NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."procurement" (
    "id" integer DEFAULT nextval('procurement_id_seq1'::regclass) NOT NULL,
    "procurement_id" character varying(20) NOT NULL,
    "item_name" character varying(150) NOT NULL,
    "category" character varying(100) NOT NULL,
    "quantity" integer NOT NULL,
    "supplier" character varying(150) NOT NULL,
    "requested_by" character varying(150),
    "request_date" date,
    "status" character varying(20) DEFAULT 'Pending'::character varying NOT NULL,
    "approved_by" character varying(150),
    "approval_date" date,
    "remarks" text,
    "delivered_quantity" integer DEFAULT 0 NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "updated_at" timestamp with time zone DEFAULT now() NOT NULL,
    "delivery_date" date
);

CREATE TABLE public."property_events" (
    "id" bigint DEFAULT nextval('property_events_id_seq'::regclass) NOT NULL,
    "module" character varying(30) NOT NULL,
    "event_type" character varying(60) NOT NULL,
    "business_id" character varying(20) NOT NULL,
    "related_business_id" character varying(20),
    "record_name_snap" character varying(150),
    "category_snap" character varying(100),
    "event_date" date NOT NULL,
    "quantity_delta" integer,
    "from_status" character varying(50),
    "to_status" character varying(50),
    "outcome" character varying(100),
    "performed_by" character varying(50),
    "description" text,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE public."users" (
    "id" integer DEFAULT nextval('users_id_seq'::regclass) NOT NULL,
    "employee_id" character varying(50) NOT NULL,
    "full_name" character varying(150) NOT NULL,
    "password_hash" character varying(255) NOT NULL,
    "role" character varying(50) DEFAULT 'Administrator'::character varying NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL,
    "is_active" boolean DEFAULT true NOT NULL,
    "session_version" integer DEFAULT 1 NOT NULL,
    "mfa_enabled" boolean DEFAULT false NOT NULL,
    "mfa_secret_enc" text,
    "mfa_enrolled_at" timestamp with time zone,
    "mfa_last_used_step" bigint
);

CREATE TABLE public."security_events" (
    "id" bigint DEFAULT nextval('security_events_id_seq'::regclass) NOT NULL,
    "event_type" character varying(50) NOT NULL,
    "actor_user_id" integer,
    "target_user_id" integer NOT NULL,
    "description" text,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL
);

-- Sequence ownership
ALTER SEQUENCE public."assets_id_seq" OWNED BY public."assets"."id";
ALTER SEQUENCE public."audits_id_seq" OWNED BY public."audits"."id";
ALTER SEQUENCE public."inventory_id_seq1" OWNED BY public."inventory"."id";
ALTER SEQUENCE public."maintenance_id_seq1" OWNED BY public."maintenance"."id";
ALTER SEQUENCE public."procurement_id_seq1" OWNED BY public."procurement"."id";
ALTER SEQUENCE public."property_events_id_seq" OWNED BY public."property_events"."id";
ALTER SEQUENCE public."security_events_id_seq" OWNED BY public."security_events"."id";
ALTER SEQUENCE public."users_id_seq" OWNED BY public."users"."id";

-- Primary keys
ALTER TABLE ONLY public."assets" ADD CONSTRAINT "assets_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."audits" ADD CONSTRAINT "audits_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."inventory" ADD CONSTRAINT "inventory_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."login_attempts" ADD CONSTRAINT "login_attempts_pkey" PRIMARY KEY (attempt_key);
ALTER TABLE ONLY public."maintenance" ADD CONSTRAINT "maintenance_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."procurement" ADD CONSTRAINT "procurement_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."property_events" ADD CONSTRAINT "property_events_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."security_events" ADD CONSTRAINT "security_events_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."users" ADD CONSTRAINT "users_pkey" PRIMARY KEY (id);

-- Unique constraints
ALTER TABLE ONLY public."assets" ADD CONSTRAINT "assets_asset_id_key" UNIQUE (asset_id);
ALTER TABLE ONLY public."audits" ADD CONSTRAINT "audits_audit_id_key" UNIQUE (audit_id);
ALTER TABLE ONLY public."inventory" ADD CONSTRAINT "inventory_inventory_id_key" UNIQUE (inventory_id);
ALTER TABLE ONLY public."maintenance" ADD CONSTRAINT "maintenance_maintenance_id_key" UNIQUE (maintenance_id);
ALTER TABLE ONLY public."procurement" ADD CONSTRAINT "procurement_procurement_id_key" UNIQUE (procurement_id);
ALTER TABLE ONLY public."users" ADD CONSTRAINT "users_employee_id_key" UNIQUE (employee_id);

-- Check constraints
ALTER TABLE ONLY public."assets"
    ADD CONSTRAINT "assets_status_check"
    CHECK (status::text = ANY (ARRAY['Available'::character varying, 'Assigned'::character varying, 'Under Maintenance'::character varying, 'Lost'::character varying]::text[]));

ALTER TABLE ONLY public."audits"
    ADD CONSTRAINT "audits_status_check"
    CHECK (status::text = ANY (ARRAY['Scheduled'::character varying, 'Ongoing'::character varying, 'Completed'::character varying]::text[]));

ALTER TABLE ONLY public."inventory"
    ADD CONSTRAINT "inventory_quantity_check"
    CHECK (quantity >= 0);

ALTER TABLE ONLY public."login_attempts"
    ADD CONSTRAINT "login_attempts_failure_count_check"
    CHECK (failure_count > 0);

ALTER TABLE ONLY public."maintenance"
    ADD CONSTRAINT "maintenance_maintenance_type_check"
    CHECK (maintenance_type::text = ANY (ARRAY['Preventive'::character varying, 'Corrective'::character varying, 'Inspection'::character varying]::text[]));

ALTER TABLE ONLY public."maintenance"
    ADD CONSTRAINT "maintenance_status_check"
    CHECK (status::text = ANY (ARRAY['Scheduled'::character varying, 'In Progress'::character varying, 'Completed'::character varying]::text[]));

ALTER TABLE ONLY public."procurement"
    ADD CONSTRAINT "procurement_delivered_quantity_check"
    CHECK (delivered_quantity >= 0);

ALTER TABLE ONLY public."procurement"
    ADD CONSTRAINT "procurement_quantity_check"
    CHECK (quantity > 0);

ALTER TABLE ONLY public."procurement"
    ADD CONSTRAINT "procurement_status_check"
    CHECK (status::text = ANY (ARRAY['Pending'::character varying, 'Approved'::character varying, 'Rejected'::character varying, 'Delivered'::character varying]::text[]));

ALTER TABLE ONLY public."property_events"
    ADD CONSTRAINT "property_events_module_check"
    CHECK (module::text = ANY (ARRAY['Procurement'::character varying, 'Inventory'::character varying, 'Asset Registry'::character varying, 'Maintenance'::character varying, 'Audit'::character varying]::text[]));

ALTER TABLE ONLY public."security_events"
    ADD CONSTRAINT "security_events_type_check"
    CHECK (event_type::text = ANY (ARRAY['MFA_ENROLLED'::character varying, 'MFA_DISABLED'::character varying, 'MFA_RESET_BY_ADMIN'::character varying, 'MFA_CHALLENGE_BLOCKED'::character varying]::text[]));

ALTER TABLE ONLY public."users"
    ADD CONSTRAINT "users_mfa_state_check"
    CHECK (
        (
            mfa_enabled = false
            AND mfa_enrolled_at IS NULL
            AND mfa_last_used_step IS NULL
        )
        OR
        (
            mfa_enabled = true
            AND mfa_secret_enc IS NOT NULL
            AND mfa_enrolled_at IS NOT NULL
            AND mfa_last_used_step IS NOT NULL
        )
    );

ALTER TABLE ONLY public."users"
    ADD CONSTRAINT "users_role_check"
    CHECK (role::text = ANY (ARRAY['Administrator'::character varying, 'Property Custodian'::character varying]::text[]));

ALTER TABLE ONLY public."users"
    ADD CONSTRAINT "users_session_version_check"
    CHECK (session_version > 0);

-- Foreign keys
ALTER TABLE ONLY public."assets"
    ADD CONSTRAINT "assets_inventory_id_fkey"
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public."audits"
    ADD CONSTRAINT "audits_asset_id_fkey"
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public."maintenance"
    ADD CONSTRAINT "maintenance_asset_id_fkey"
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public."security_events"
    ADD CONSTRAINT "security_events_actor_user_fk"
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE ONLY public."security_events"
    ADD CONSTRAINT "security_events_target_user_fk"
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT;

-- Additional indexes
CREATE INDEX idx_assets_inventory_id ON public.assets USING btree (inventory_id);
CREATE INDEX idx_assets_status ON public.assets USING btree (status);
CREATE INDEX idx_audits_asset_id ON public.audits USING btree (asset_id);
CREATE INDEX idx_audits_result ON public.audits USING btree (result);
CREATE UNIQUE INDEX inventory_name_category_uidx ON public.inventory USING btree (lower((asset_name)::text), lower((category)::text));
CREATE INDEX idx_maintenance_asset_id ON public.maintenance USING btree (asset_id);
CREATE INDEX idx_maintenance_status ON public.maintenance USING btree (status);
CREATE INDEX login_attempts_updated_at_idx ON public.login_attempts USING btree (updated_at);
CREATE INDEX idx_procurement_status ON public.procurement USING btree (status);
CREATE INDEX property_events_business_id_idx ON public.property_events USING btree (business_id);
CREATE INDEX property_events_event_date_idx ON public.property_events USING btree (event_date);
CREATE INDEX property_events_module_date_idx ON public.property_events USING btree (module, event_date);
CREATE INDEX property_events_related_business_id_idx ON public.property_events USING btree (related_business_id);
CREATE INDEX security_events_actor_user_idx ON public.security_events USING btree (actor_user_id);
CREATE INDEX security_events_created_at_idx ON public.security_events USING btree (created_at DESC);
CREATE INDEX security_events_target_user_idx ON public.security_events USING btree (target_user_id);
CREATE UNIQUE INDEX users_employee_id_lower_key ON public.users USING btree (lower((employee_id)::text));
CREATE INDEX users_role_active_idx ON public.users USING btree (role, is_active);

-- Server-side application role and Data API boundary
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_roles WHERE rolname = 'pcms_app'
    ) THEN
        CREATE ROLE pcms_app
            LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE
            NOINHERIT NOBYPASSRLS;
    END IF;
END
$$;

-- Configure the pcms_app password separately through an approved secret channel.
-- No database password belongs in this schema file or in Git.

GRANT CONNECT ON DATABASE postgres TO pcms_app;
GRANT USAGE ON SCHEMA public TO pcms_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO pcms_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO pcms_app;

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
ALTER TABLE public.security_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;

CREATE POLICY pcms_app_all_access ON public.assets FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.audits FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.inventory FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.login_attempts FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.maintenance FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.procurement FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.property_events FOR ALL TO pcms_app USING (true) WITH CHECK (true);
CREATE POLICY security_events_pcms_app_select ON public.security_events FOR SELECT TO pcms_app USING (true);
CREATE POLICY security_events_pcms_app_insert ON public.security_events FOR INSERT TO pcms_app WITH CHECK (true);
CREATE POLICY pcms_app_all_access ON public.users FOR ALL TO pcms_app USING (true) WITH CHECK (true);

-- Security events are append-only for the application role.
REVOKE ALL ON TABLE public.security_events FROM PUBLIC, anon, authenticated;
REVOKE ALL ON SEQUENCE public.security_events_id_seq FROM PUBLIC, anon, authenticated;
GRANT SELECT, INSERT ON TABLE public.security_events TO pcms_app;
GRANT USAGE, SELECT ON SEQUENCE public.security_events_id_seq TO pcms_app;
REVOKE UPDATE, DELETE, TRUNCATE ON TABLE public.security_events FROM pcms_app;

COMMIT;
