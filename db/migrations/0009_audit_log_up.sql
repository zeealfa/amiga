-- 0009_audit_log_up.sql
-- Audit Log: UP
-- Purely additive: creates t_audit_log only.

CREATE TABLE `t_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(20) NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `action` varchar(20) NOT NULL,
  `label` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entity_type_idx` (`entity_type`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
