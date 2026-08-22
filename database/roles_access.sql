BEGIN;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.users'::regclass
          AND conname = 'users_role_check'
    ) THEN
        ALTER TABLE users
        ADD CONSTRAINT users_role_check
        CHECK (role IN ('Administrator', 'Property Custodian'));
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS users_role_active_idx
ON users (role, is_active);

CREATE UNIQUE INDEX IF NOT EXISTS users_employee_id_lower_key
ON users (lower(employee_id));

COMMIT;
