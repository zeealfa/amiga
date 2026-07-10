-- 0008_contributor_submissions_down.sql
-- Contributor Submissions: DOWN
-- NOT purely additive to reverse: MODIFYs t_users.status enum back
-- (drops 'pending') in addition to dropping t_submissions and the
-- submitted_by columns.

-- t_links/t_news never had a real FOREIGN KEY (see 0008 UP comment --
-- MyISAM can't enforce one), just a plain named index, so the down
-- migration drops that index rather than a constraint.
ALTER TABLE t_users
  MODIFY COLUMN status ENUM('active','removed') NOT NULL DEFAULT 'active';

ALTER TABLE t_news
  DROP INDEX idx_news_submitted_by,
  DROP COLUMN submitted_by;

ALTER TABLE t_links
  DROP INDEX idx_links_submitted_by,
  DROP COLUMN submitted_by;

DROP TABLE t_submissions;
