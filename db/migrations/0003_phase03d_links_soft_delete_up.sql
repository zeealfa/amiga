-- Phase 03d: Admin Link CRUD — UP
-- Adds links_deleted_at to t_links for soft delete. Purely additive.

ALTER TABLE t_links
  ADD COLUMN links_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER links_active,
  ADD INDEX idx_links_deleted_at (links_deleted_at);
