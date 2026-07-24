-- Transform readable legacy data into normalized application tables
BEGIN;

-- Create helper function for machine code normalization
CREATE OR REPLACE FUNCTION app.normalize_machine_code(raw_code text)
RETURNS text AS $$
BEGIN
  IF raw_code IS NULL OR trim(raw_code) = '' OR trim(raw_code) = '\N' THEN
    RETURN NULL;
  END IF;
  raw_code := trim(raw_code);
  IF raw_code ~* '-VD[0-9]+-' THEN
    RETURN 'VD' || lpad(cast(substring(raw_code from '(?i)-VD([0-9]+)-') as integer)::text, 2, '0');
  ELSIF raw_code ~* 'VD[0-9]+' THEN
    RETURN 'VD' || lpad(cast(substring(raw_code from '(?i)VD([0-9]+)') as integer)::text, 2, '0');
  ELSIF regexp_replace(raw_code, '[^0-9]', '', 'g') ~ '^[0-9]+$' AND length(regexp_replace(raw_code, '[^0-9]', '', 'g')) <= 3 THEN
    RETURN 'VD' || lpad(cast(regexp_replace(raw_code, '[^0-9]', '', 'g') as integer)::text, 2, '0');
  ELSE
    RETURN NULL;
  END IF;
EXCEPTION WHEN OTHERS THEN
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Create helper function for safe numeric casting
CREATE OR REPLACE FUNCTION app.safe_cast_to_numeric(val text)
RETURNS numeric AS $$
DECLARE
  clean_val text;
BEGIN
  IF val IS NULL OR trim(val) = '' OR trim(val) = '\N' THEN
    RETURN NULL;
  END IF;
  clean_val := regexp_replace(trim(val), '[^0-9.\-]', '', 'g');
  RETURN clean_val::numeric;
EXCEPTION WHEN OTHERS THEN
  BEGIN
    clean_val := substring(regexp_replace(trim(val), '[^0-9.]', '', 'g') from '^[0-9]+(\.[0-9]+)?');
    IF clean_val IS NOT NULL AND clean_val != '' THEN
      RETURN clean_val::numeric;
    ELSE
      RETURN NULL;
    END IF;
  EXCEPTION WHEN OTHERS THEN
    RETURN NULL;
  END;
END;
$$ LANGUAGE plpgsql;

-- 1. Insert machines (normalized from tbl_status)
INSERT INTO app.machines(code, name)
SELECT DISTINCT 
  app.normalize_machine_code(machine) as code,
  'Máy nhuộm VD' || cast(regexp_replace(machine, '[^0-9]', '', 'g') as integer)::text as name
FROM legacy_df_scale.tbl_status
WHERE app.normalize_machine_code(machine) IS NOT NULL
ON CONFLICT(code) DO NOTHING;

-- 2. Insert tanks/racks (normalized from tblRECORD and tblRECORD_chem)
INSERT INTO app.tanks(machine_id, code, name)
SELECT DISTINCT
  m.id,
  trim(r.rack_code),
  'Bồn ' || trim(r.rack_code)
FROM (
  SELECT DISTINCT "MACHINE" as machine_code, "RACK" as rack_code FROM legacy_df_scale."tblRECORD"
  UNION ALL
  SELECT DISTINCT "MACHINE", "RACK" FROM legacy_df_scale."tblRECORD_chem"
) r
JOIN app.machines m ON m.code = app.normalize_machine_code(r.machine_code)
WHERE nullif(trim(r.rack_code), '') IS NOT NULL AND app.normalize_machine_code(r.machine_code) IS NOT NULL
ON CONFLICT(machine_id, code) DO NOTHING;

-- 3. Insert production batches (consolidated unique batches)
INSERT INTO app.production_batches(legacy_batch_id, color, product_code, machine_id, level_code, status)
SELECT DISTINCT
  nullif(trim(s.batch_id), '') as legacy_batch_id,
  max(trim(s.color)) as color,
  max(trim(s.product_code)) as product_code,
  m.id as machine_id,
  max(trim(s.level_code)) as level_code,
  'IMPORTED' as status
FROM (
  SELECT "batchID" as batch_id, "COLOR" as color, "CODE" as product_code, "MACHINE" as machine_code, "LEVEL" as level_code
  FROM legacy_df_scale."tblRECORD"
  
  UNION ALL
  
  SELECT "batchID", "COLOR", "CODE", "MACHINE", "LEVEL"
  FROM legacy_df_scale."tblRECORD_chem"
) s
JOIN app.machines m ON m.code = app.normalize_machine_code(s.machine_code)
WHERE nullif(trim(s.batch_id), '') IS NOT NULL AND app.normalize_machine_code(s.machine_code) IS NOT NULL
GROUP BY s.batch_id, m.id
ON CONFLICT(legacy_batch_id, product_code, machine_id) DO NOTHING;

-- 4. Insert scale measurements (1-to-1 raw records for DYE)
INSERT INTO app.scale_measurements(legacy_source, legacy_id, legacy_batch_id, color, product_code, machine_code, level_code, rack_code, dye_code, weight, process_code, measured_at, process_color, warehouse_done, warehouse_time, material_type)
SELECT 
  'tblRECORD', 
  cast("ID" as bigint), 
  nullif(trim("batchID"), ''),
  nullif(trim("COLOR"), ''),
  nullif(trim("CODE"), ''),
  app.normalize_machine_code("MACHINE"),
  nullif(trim("LEVEL"), ''),
  nullif(trim("RACK"), ''),
  nullif(trim("DYECODE"), ''),
  app.safe_cast_to_numeric("WEIGHT"),
  nullif(trim("PROCESS"), ''),
  CASE WHEN nullif(trim("TIME"), '') IS NOT NULL AND trim("TIME") != '0' AND trim("TIME") != '\N' THEN to_timestamp("TIME", 'YYYY-MM-DD HH24:MI:SS') ELSE NULL END,
  nullif(trim("processCOLOR"), ''),
  CASE WHEN nullif(trim("WH_DONE"), '') ILIKE 'true' THEN true ELSE false END,
  CASE WHEN nullif(trim("WH_TIME"), '') IS NOT NULL AND trim("WH_TIME") != '0' AND trim("WH_TIME") != '\N' THEN to_timestamp("WH_TIME", 'YYYY-MM-DD HH24:MI:SS') ELSE NULL END,
  'DYE'
FROM legacy_df_scale."tblRECORD"
ON CONFLICT(legacy_source, legacy_id) DO NOTHING;

-- 5. Insert scale measurements (1-to-1 raw records for CHEMICAL)
INSERT INTO app.scale_measurements(legacy_source, legacy_id, legacy_batch_id, color, product_code, machine_code, level_code, rack_code, dye_code, weight, process_code, measured_at, process_color, material_type)
SELECT 
  'tblRECORD_chem', 
  cast("ID" as bigint), 
  nullif(trim("batchID"), ''),
  nullif(trim("COLOR"), ''),
  nullif(trim("CODE"), ''),
  app.normalize_machine_code("MACHINE"),
  nullif(trim("LEVEL"), ''),
  nullif(trim("RACK"), ''),
  nullif(trim("DYECODE"), ''),
  app.safe_cast_to_numeric("WEIGHT"),
  nullif(trim("PROCESS"), ''),
  CASE WHEN nullif(trim("TIME"), '') IS NOT NULL AND trim("TIME") != '0' AND trim("TIME") != '\N' THEN to_timestamp("TIME", 'YYYY-MM-DD HH24:MI:SS') ELSE NULL END,
  nullif(trim("processCOLOR"), ''),
  'CHEMICAL'
FROM legacy_df_scale."tblRECORD_chem"
ON CONFLICT(legacy_source, legacy_id) DO NOTHING;

-- 6. Insert machine dispatches (unshifted tbl_Waiting)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(d."ID" as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."CONFIRM1"::text) as confirm_1,
  trim(d."CONFIRM2"::text) as confirm_2,
  trim(d."SENDING"::text) as sending_value,
  trim(d."SENT"::text) as sent_value,
  CASE WHEN nullif(trim(d."TIME1"), '') IS NOT NULL AND d."TIME1" != '0' AND d."TIME1" != '\N' THEN to_timestamp(d."TIME1", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1,
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2,
  CASE WHEN nullif(trim(d."TIME3"), '') IS NOT NULL AND d."TIME3" != '0' AND d."TIME3" != '\N' THEN to_timestamp(d."TIME3", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as sent_at,
  CASE WHEN d."ISSENT" = 'true' THEN true ELSE false END as is_sent,
  'WAITING' as queue_state,
  'tbl_Waiting' as source_table
FROM legacy_df_data."tbl_Waiting" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."MACHINE"::text)
LEFT JOIN app.production_batches b ON b.product_code = trim(d."CODE"::text) AND b.machine_id = m.id
WHERE app.normalize_machine_code(d."MACHINE"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;

-- 7. Insert machine dispatches (shifted tbl_ToSend2)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(d."ID" as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."MACHINE"::text) as confirm_1, -- MACHINE contains CONFIRM1
  trim(d."SENDING"::text) as confirm_2, -- SENDING contains CONFIRM2
  trim(d."SENT"::text) as sending_value, -- SENT contains SENDING
  trim(d."TIME1"::text) as sent_value, -- TIME1 contains SENT
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1, -- TIME2 contains TIME1
  CASE WHEN nullif(trim(d."TIME3"), '') IS NOT NULL AND d."TIME3" != '0' AND d."TIME3" != '\N' THEN to_timestamp(d."TIME3", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2, -- TIME3 contains TIME2
  NULL::timestamp as sent_at,
  CASE WHEN d."ISSENT" = 'true' THEN true ELSE false END as is_sent,
  'TO_SEND' as queue_state,
  'tbl_ToSend2' as source_table
FROM legacy_df_data."tbl_ToSend2" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."TANK"::text) -- TANK contains Machine
LEFT JOIN app.production_batches b ON b.product_code = trim(d."CONFIRM1"::text) AND b.machine_id = m.id -- CONFIRM1 contains Product Code
WHERE app.normalize_machine_code(d."TANK"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;

-- 8. Insert machine dispatches (shifted WAITING)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(nullif(trim(d."ID"), '') as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."CODE"::text) as confirm_1, -- CODE contains CONFIRM1
  trim(d."LEVEL"::text) as confirm_2, -- LEVEL contains CONFIRM2
  trim(d."CONFIRM2"::text) as sending_value, -- CONFIRM2 contains SENDING
  trim(d."SENDING"::text) as sent_value, -- SENDING contains SENT
  CASE WHEN nullif(trim(d."SENT"), '') IS NOT NULL AND d."SENT" != '0' AND d."SENT" != '\N' THEN to_timestamp(d."SENT", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1, -- SENT contains TIME1
  CASE WHEN nullif(trim(d."TIME1"), '') IS NOT NULL AND d."TIME1" != '0' AND d."TIME1" != '\N' THEN to_timestamp(d."TIME1", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2, -- TIME1 contains TIME2
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as sent_at, -- TIME2 contains TIME3
  CASE WHEN trim(d."TIME3") ILIKE 'true' OR trim(d."TIME3") = '1' THEN true ELSE false END as is_sent, -- TIME3 contains ISSENT
  'WAITING_LEGACY' as queue_state,
  'WAITING' as source_table
FROM legacy_df_data."WAITING" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."CONFIRM1"::text) -- CONFIRM1 contains Machine
LEFT JOIN app.production_batches b ON b.product_code = trim(d."COLOR"::text) AND b.machine_id = m.id -- COLOR contains Product Code
WHERE app.normalize_machine_code(d."CONFIRM1"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;

-- 9. Insert machine chemical channels configuration
INSERT INTO app.machine_chemical_channels(machine_id, channel_number, chemical_code, legacy_id)
SELECT 
  m.id, 
  cast(d.chem as smallint), 
  trim(d.chem_name), 
  cast(d."ID" as bigint)
FROM legacy_df_scale.tbl_status d
JOIN app.machines m ON m.code = app.normalize_machine_code(d.machine)
ON CONFLICT (machine_id, channel_number) DO UPDATE 
SET chemical_code = EXCLUDED.chemical_code, legacy_id = EXCLUDED.legacy_id;

-- Clean up helper functions
DROP FUNCTION app.normalize_machine_code(text);
DROP FUNCTION app.safe_cast_to_numeric(text);

COMMIT;

ANALYZE app.scale_measurements;
ANALYZE app.machine_dispatches;
ANALYZE app.machine_chemical_channels;
