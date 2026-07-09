-- Phase 03d: Admin Link CRUD — DOWN
-- Exact reverse of 0003_phase03d_links_soft_delete_up.sql

ALTER TABLE t_links
  DROP INDEX idx_links_deleted_at,
  DROP COLUMN links_deleted_at;
