-- Phase 03: User Accounts & Roles — DOWN
-- Reverts 0007_users_must_change_password_up.sql

ALTER TABLE t_users
  DROP COLUMN must_change_password;
