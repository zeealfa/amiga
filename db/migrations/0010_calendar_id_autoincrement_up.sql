-- 0010_calendar_id_autoincrement_up.sql
-- Calendar id autoincrement: UP
-- t_cal.id was NOT NULL but not AUTO_INCREMENT, forcing application code
-- to compute the next id manually (SELECT MAX(id)+1) before every insert.
-- Purely additive: only changes the column's AUTO_INCREMENT attribute.

ALTER TABLE t_cal
  MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT;
