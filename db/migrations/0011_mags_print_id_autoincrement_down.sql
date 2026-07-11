-- 0011_mags_print_id_autoincrement_down.sql
-- Print publications id autoincrement: DOWN

ALTER TABLE t_mags_print
  MODIFY COLUMN id INT(1) NOT NULL;
