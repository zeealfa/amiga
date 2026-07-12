-- Forgot Password — UP
-- Adds password reset token fields to t_users. Purely additive.

ALTER TABLE t_users
  ADD COLUMN reset_token_hash VARCHAR(64) NULL AFTER must_change_password,
  ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token_hash;
