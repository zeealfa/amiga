-- 0012_mags_online_id_autoincrement_down.sql
-- Online publications id autoincrement: DOWN

ALTER TABLE t_mags_online
  MODIFY COLUMN id INT(1) NOT NULL;
