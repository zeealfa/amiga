-- 0012_mags_online_id_autoincrement_up.sql
-- Online publications id autoincrement: UP
-- t_mags_online.id was NOT NULL but not AUTO_INCREMENT, forcing application code
-- to compute the next id manually (SELECT MAX(id)+1) before every insert.
-- Purely additive: only changes the column's AUTO_INCREMENT attribute.

ALTER TABLE t_mags_online
  MODIFY COLUMN id INT(1) NOT NULL AUTO_INCREMENT;
