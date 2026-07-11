-- 0010_calendar_id_autoincrement_down.sql
-- Calendar id autoincrement: DOWN

ALTER TABLE t_cal
  MODIFY COLUMN id INT(11) NOT NULL;
