-- Target normalized schema for the web application (PostgreSQL 15+)
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA IF NOT EXISTS app;

CREATE TABLE IF NOT EXISTS app.users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  username varchar(100) NOT NULL UNIQUE,
  display_name varchar(200),
  password_hash text NOT NULL,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS app.roles (id bigserial PRIMARY KEY, code varchar(50) UNIQUE NOT NULL, name varchar(150) NOT NULL);
CREATE TABLE IF NOT EXISTS app.user_roles (user_id uuid REFERENCES app.users(id) ON DELETE CASCADE, role_id bigint REFERENCES app.roles(id) ON DELETE CASCADE, PRIMARY KEY(user_id,role_id));

CREATE TABLE IF NOT EXISTS app.machines (
  id bigserial PRIMARY KEY, code varchar(100) NOT NULL UNIQUE, name varchar(200), is_active boolean NOT NULL DEFAULT true
);
CREATE TABLE IF NOT EXISTS app.tanks (
  id bigserial PRIMARY KEY, machine_id bigint REFERENCES app.machines(id), code varchar(100) NOT NULL, name varchar(200), UNIQUE(machine_id,code)
);
CREATE TABLE IF NOT EXISTS app.production_batches (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(), legacy_batch_id text, color text, product_code text,
  machine_id bigint REFERENCES app.machines(id), tank_id bigint REFERENCES app.tanks(id), level_code text,
  status varchar(30) NOT NULL DEFAULT 'NEW', created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(legacy_batch_id, product_code, machine_id)
);
CREATE TABLE IF NOT EXISTS app.machine_dispatches (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(), legacy_row_no bigint NOT NULL, legacy_id bigint, batch_id uuid REFERENCES app.production_batches(id),
  confirm_1 text, confirm_2 text, sending_value text, sent_value text,
  confirmed_at_1 timestamp, confirmed_at_2 timestamp, sent_at timestamp,
  is_sent boolean, scale_checked boolean, raw_qr_dye text, raw_qr_chemical text,
  queue_state varchar(30) NOT NULL, source_table varchar(100) NOT NULL,
  locked_by uuid REFERENCES app.users(id), locked_at timestamptz,
  idempotency_key varchar(255) UNIQUE,
  created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(source_table,legacy_row_no)
);
CREATE INDEX IF NOT EXISTS ix_dispatch_queue_state ON app.machine_dispatches(queue_state);
CREATE INDEX IF NOT EXISTS ix_dispatch_batch ON app.machine_dispatches(batch_id);

CREATE TABLE IF NOT EXISTS app.scale_measurements (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(), legacy_source varchar(30) NOT NULL, legacy_id bigint NOT NULL,
  legacy_batch_id text, color text, product_code text, machine_code text, level_code text, rack_code text,
  dye_code text, weight numeric(18,6), process_code text, measured_at timestamp, process_color text,
  warehouse_done boolean, warehouse_time timestamp,
  material_type varchar(20) NOT NULL CHECK(material_type IN ('DYE','CHEMICAL')),
  imported_at timestamptz NOT NULL DEFAULT now(), UNIQUE(legacy_source,legacy_id)
);
CREATE INDEX IF NOT EXISTS ix_scale_batch ON app.scale_measurements(legacy_batch_id);
CREATE INDEX IF NOT EXISTS ix_scale_time ON app.scale_measurements(measured_at);
CREATE INDEX IF NOT EXISTS ix_scale_material ON app.scale_measurements(material_type,dye_code);

CREATE TABLE IF NOT EXISTS app.audit_logs (
  id bigserial PRIMARY KEY, user_id uuid REFERENCES app.users(id), action varchar(100) NOT NULL,
  entity_type varchar(100) NOT NULL, entity_id text, before_data jsonb, after_data jsonb,
  created_at timestamptz NOT NULL DEFAULT now(), client_ip inet
);

CREATE TABLE IF NOT EXISTS app.machine_chemical_channels (
  id bigserial PRIMARY KEY,
  machine_id bigint REFERENCES app.machines(id) ON DELETE CASCADE,
  channel_number smallint NOT NULL,
  chemical_code varchar(100) NOT NULL,
  is_active boolean NOT NULL DEFAULT true,
  legacy_id bigint,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(machine_id, channel_number)
);

