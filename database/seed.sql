-- Phase 4 — Initial Administrator Seed

INSERT INTO users (
    employee_id,
    full_name,
    password_hash,
    role,
    is_active
)
VALUES (
    'admin',
    'System Administrator',
    '$2y$12$QyR3HWhRA.OxiIWZNGqc8ur3VV87SyuXPMgr8dbMDm2wwH7nYbT/m',
    'Administrator',
    TRUE
);
