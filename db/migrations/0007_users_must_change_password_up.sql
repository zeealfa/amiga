-- Phase 03: User Accounts & Roles — UP
-- Adds must_change_password to t_users. Purely additive.

ALTER TABLE t_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
