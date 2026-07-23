-- 0016_page_todos_up.sql
-- Page Todo Checklist: UP
-- Adds t_page_todos, a dev-only, no-login checklist attached to individual
-- pages (public content views + admin pages) so the client can leave
-- specific requests in context. page_key is the filename that identifies
-- the page (e.g. "content_news.php", "links.php"). Purely additive.

CREATE TABLE `t_page_todos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(100) NOT NULL,
  `item_text` text NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_key` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
