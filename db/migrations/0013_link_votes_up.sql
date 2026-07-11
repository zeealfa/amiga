-- 0013_link_votes_up.sql
-- Link Votes: UP
-- Purely additive: creates t_link_votes only. Records one vote per
-- (link_id, voter_ip) pair for unauthenticated visitor "thumbs up" voting
-- on public links. UNIQUE(link_id, voter_ip) is the dedup mechanism --
-- a repeat click from the same IP on the same link is rejected at the
-- DB level (via INSERT IGNORE in application code) instead of requiring
-- a read-then-write check.

CREATE TABLE `t_link_votes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `link_id` int(10) unsigned NOT NULL,
  `voter_ip` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_voter_idx` (`link_id`, `voter_ip`),
  KEY `link_id_idx` (`link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
