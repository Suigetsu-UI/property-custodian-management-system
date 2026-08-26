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
    "is_active" boolean DEFAULT true NOT NULL
);

-- Sequence ownership
ALTER SEQUENCE public."assets_id_seq" OWNED BY public."assets"."id";
ALTER SEQUENCE public."audits_id_seq" OWNED BY public."audits"."id";
ALTER SEQUENCE public."inventory_id_seq1" OWNED BY public."inventory"."id";
ALTER SEQUENCE public."maintenance_id_seq1" OWNED BY public."maintenance"."id";
ALTER SEQUENCE public."procurement_id_seq1" OWNED BY public."procurement"."id";
ALTER SEQUENCE public."property_events_id_seq" OWNED BY public."property_events"."id";
ALTER SEQUENCE public."users_id_seq" OWNED BY public."users"."id";

-- Primary keys
ALTER TABLE ONLY public."assets" ADD CONSTRAINT "assets_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."audits" ADD CONSTRAINT "audits_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."inventory" ADD CONSTRAINT "inventory_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."maintenance" ADD CONSTRAINT "maintenance_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."procurement" ADD CONSTRAINT "procurement_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY public."property_events" ADD CONSTRAINT "property_events_pkey" PRIMARY KEY (id);
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

ALTER TABLE ONLY public."users"
    ADD CONSTRAINT "users_role_check"
    CHECK (role::text = ANY (ARRAY['Administrator'::character varying, 'Property Custodian'::character varying]::text[]));

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

-- Additional indexes
CREATE INDEX idx_assets_inventory_id ON public.assets USING btree (inventory_id);
CREATE INDEX idx_assets_status ON public.assets USING btree (status);
CREATE INDEX idx_audits_asset_id ON public.audits USING btree (asset_id);
CREATE INDEX idx_audits_result ON public.audits USING btree (result);
CREATE UNIQUE INDEX inventory_name_category_uidx ON public.inventory USING btree (lower((asset_name)::text), lower((category)::text));
CREATE INDEX idx_maintenance_asset_id ON public.maintenance USING btree (asset_id);
CREATE INDEX idx_maintenance_status ON public.maintenance USING btree (status);
CREATE INDEX idx_procurement_status ON public.procurement USING btree (status);
CREATE INDEX property_events_business_id_idx ON public.property_events USING btree (business_id);
CREATE INDEX property_events_event_date_idx ON public.property_events USING btree (event_date);
CREATE INDEX property_events_module_date_idx ON public.property_events USING btree (module, event_date);
CREATE INDEX property_events_related_business_id_idx ON public.property_events USING btree (related_business_id);
CREATE UNIQUE INDEX users_employee_id_lower_key ON public.users USING btree (lower((employee_id)::text));
CREATE INDEX users_role_active_idx ON public.users USING btree (role, is_active);

COMMIT;
