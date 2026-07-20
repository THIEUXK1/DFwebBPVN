-- Reconciliation checks after import
SELECT 'legacy_dye' metric, count(*) value FROM legacy_df_scale."tblRECORD"
UNION ALL SELECT 'target_dye',count(*) FROM app.scale_measurements WHERE material_type='DYE'
UNION ALL SELECT 'legacy_chemical',count(*) FROM legacy_df_scale."tblRECORD_chem"
UNION ALL SELECT 'target_chemical',count(*) FROM app.scale_measurements WHERE material_type='CHEMICAL';

SELECT legacy_source, count(*) total, count(DISTINCT legacy_id) unique_ids FROM app.scale_measurements GROUP BY legacy_source;
SELECT source_table,queue_state,count(*) FROM app.machine_dispatches GROUP BY source_table,queue_state ORDER BY source_table;
SELECT count(*) rows_without_batch FROM app.scale_measurements WHERE nullif(trim(legacy_batch_id),'') IS NULL;
SELECT legacy_source,legacy_id,count(*) FROM app.scale_measurements GROUP BY legacy_source,legacy_id HAVING count(*)>1;
