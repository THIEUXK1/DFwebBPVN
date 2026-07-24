BEGIN;
CREATE TABLE IF NOT EXISTS legacy_df_scale.tbl_status (
  "ID" TEXT,
  "machine" TEXT,
  "chem" TEXT,
  "chem_name" TEXT,
  "status" TEXT
);
TRUNCATE legacy_df_scale.tbl_status;
INSERT INTO legacy_df_scale.tbl_status VALUES ('1010834838', 'VD016', '4', 'AC77', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('693761887', 'VD017', '4', 'AC77', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1439684020', 'VD015', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1199028059', 'VD016', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1854340350', 'VD017', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-2122755237', 'VD018', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('318204160', 'VD015', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-573344511', 'VD016', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-192252114', 'VD017', '6', 'AC20+0553', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-924170473', 'VD018', '6', 'AC20+0553', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1607280908', 'VD001', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('821100317', 'VD002', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('366011930', 'VD003', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('150195859', 'VD004', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1540136488', 'VD005', '5', 'AC68', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1156596473', 'VD002', '4', 'AC123', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1683522618', 'VD001', '4', 'AC123', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1764009423', 'VD003', '4', 'AC123', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('287907684', 'VD004', '4', 'AC123', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-613524331', 'VD005', '4', 'AC123', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1095292570', 'VD006', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('253498643', 'VD006', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('144623192', 'VD007', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('136047993', 'VD007', '6', 'AC123+AC122', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1166242886', 'VD008', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-2045619121', 'VD008', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-776483868', 'VD009', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-2088915179', 'VD009', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('614769842', 'VD010', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1888952501', 'VD010', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('164405808', 'VD011', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('783262321', 'VD012', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('672207838', 'VD013', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-2074480121', 'VD013', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1775024870', 'VD012', '5', 'VN62+0554', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1775024962', 'VD011', '6', 'AC77+AC78', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('1775639274', 'VD012', '4', 'AC77', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-1622083805', 'VD013', '4', 'AC77', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('419750696', 'VD014', '4', 'AC77', '0');
INSERT INTO legacy_df_scale.tbl_status VALUES ('-873341431', 'VD015', '4', 'AC77', '0');
COMMIT;