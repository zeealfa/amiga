-- Phase 03: News Admin — UP
-- Adds news_deleted_at to t_news for soft delete. Purely additive.

ALTER TABLE t_news
  ADD COLUMN news_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER news_active,
  ADD INDEX idx_news_deleted_at (news_deleted_at);
