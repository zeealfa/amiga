-- 0008_contributor_submissions_up.sql
CREATE TABLE t_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type ENUM('link','news') NOT NULL,
  action ENUM('new','edit') NOT NULL,
  target_id INT NULL,
  submitted_by INT UNSIGNED NOT NULL,
  links_name VARCHAR(255) NULL,
  links_url VARCHAR(150) NULL,
  links_author VARCHAR(255) NULL,
  links_email VARCHAR(255) NULL,
  links_desc TEXT NULL,
  category_ids VARCHAR(50) NULL,
  news_date DATE NULL,
  news_story MEDIUMTEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_submitted_by (submitted_by),
  CONSTRAINT fk_submissions_user FOREIGN KEY (submitted_by) REFERENCES t_users(id),
  CONSTRAINT fk_submissions_reviewer FOREIGN KEY (reviewed_by) REFERENCES t_users(id)
);

ALTER TABLE t_links
  ADD COLUMN submitted_by INT UNSIGNED NULL AFTER links_recommended,
  ADD CONSTRAINT fk_links_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_news
  ADD COLUMN submitted_by INT UNSIGNED NULL AFTER news_deleted_at,
  ADD CONSTRAINT fk_news_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_users
  MODIFY COLUMN status ENUM('active','removed','pending') NOT NULL DEFAULT 'active';
