-- Phase 03: News Admin — DOWN
-- Reverts 0006_news_soft_delete_up.sql

ALTER TABLE t_news
  DROP INDEX idx_news_deleted_at,
  DROP COLUMN news_deleted_at;
