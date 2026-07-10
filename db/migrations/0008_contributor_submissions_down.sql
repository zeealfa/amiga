-- 0008_contributor_submissions_down.sql
ALTER TABLE t_users
  MODIFY COLUMN status ENUM('active','removed') NOT NULL DEFAULT 'active';

ALTER TABLE t_news
  DROP FOREIGN KEY fk_news_submitted_by,
  DROP COLUMN submitted_by;

ALTER TABLE t_links
  DROP FOREIGN KEY fk_links_submitted_by,
  DROP COLUMN submitted_by;

DROP TABLE t_submissions;
