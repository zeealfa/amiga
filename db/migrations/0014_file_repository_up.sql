-- 0014_file_repository_up.sql
-- File Repository: UP
-- Adds t_files for the admin-managed file repository. Purely additive.
-- `active` mirrors t_news.news_active (soft unpublish, not delete) so a
-- file's download_count history survives being hidden. `stored_filename`
-- is a server-generated random on-disk name (never derived from user
-- input) to prevent path traversal / double-extension tricks;
-- `original_filename` is only ever used for display and the
-- Content-Disposition header at download time.

CREATE TABLE `t_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` varchar(500) NOT NULL,
  `stored_filename` varchar(64) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `file_ext` varchar(10) NOT NULL,
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
