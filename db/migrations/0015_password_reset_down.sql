-- Forgot Password — DOWN
-- Reverts 0015_password_reset_up.sql

ALTER TABLE t_users
  DROP COLUMN reset_token_hash,
  DROP COLUMN reset_token_expires;
