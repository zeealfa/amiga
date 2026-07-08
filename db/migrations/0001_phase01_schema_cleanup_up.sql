-- Phase 01: DB Schema Cleanup — UP
-- Adds created_at/updated_at to all 12 tables, links_verified to t_links,
-- is_active-equivalent flags to t_cat_main/t_cat_sub, and indexes on columns
-- used in WHERE/ORDER BY clauses (see docs/audit/DB_TABLES.md for evidence).
-- Purely additive — no renames, no drops, no destructive ALTERs.

-- Scoped to this session only: t_links.links_date_added has a pre-existing
-- DEFAULT '0000-00-00' (see docs/audit/DB_TABLES.md). Any ALTER TABLE on
-- t_links re-validates all column defaults under the active sql_mode, and
-- this server's default sql_mode includes NO_ZERO_DATE, which rejects that
-- pre-existing default even though this migration never touches that column.
-- Dropping NO_ZERO_DATE/NO_ZERO_IN_DATE here does not change any data or any
-- column's actual default value — confirmed with a throwaway probe column
-- before this migration was run for real.
SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION';

-- 1. Timestamps on every table
ALTER TABLE t_links      ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_news       ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_cat_main    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_cat_sub     ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_cat_spec    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_cal         ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_cfund       ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_mags_online ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_mags_print  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_repair      ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_top10       ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE t_vendor      ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. links_verified boolean (links_dead already exists — roadmap's is_dead requirement is already satisfied)
ALTER TABLE t_links ADD COLUMN links_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER links_date_verified;

-- 3. is_active-equivalent flags on category tables (naming follows existing <table_short>_<field> convention)
ALTER TABLE t_cat_main ADD COLUMN cat_main_active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE t_cat_sub  ADD COLUMN cat_sub_active  TINYINT(1) NOT NULL DEFAULT 1;

-- 4. Indexes on columns actually used in WHERE/ORDER BY (evidence: table_result_cat.php,
--    content_news.php, content_search_proc.php, sidebar_categories_sub_01/02.php)
ALTER TABLE t_links ADD INDEX links_dead_idx (links_dead);
ALTER TABLE t_links ADD INDEX links_cat_1_idx (links_cat_1);
ALTER TABLE t_links ADD INDEX links_cat_2_idx (links_cat_2);
ALTER TABLE t_links ADD INDEX links_cat_3_idx (links_cat_3);
ALTER TABLE t_links ADD INDEX links_cat_4_idx (links_cat_4);
ALTER TABLE t_links ADD INDEX links_cat_5_idx (links_cat_5);
ALTER TABLE t_links ADD INDEX links_name_idx (links_name);
ALTER TABLE t_links ADD INDEX links_date_verified_idx (links_date_verified);
ALTER TABLE t_links ADD INDEX links_date_added_idx (links_date_added);

ALTER TABLE t_news ADD INDEX news_active_idx (news_active);
ALTER TABLE t_news ADD INDEX news_date_idx (news_date);

ALTER TABLE t_cat_sub ADD INDEX cat_sub_ref_main_id_idx (cat_sub_ref_main_id);
ALTER TABLE t_cat_sub ADD INDEX cat_sub_title_short_idx (cat_sub_title_short);

ALTER TABLE t_cat_main ADD INDEX cat_main_title_idx (cat_main_title);

-- NOT indexed, deliberately: links_desc/links_url/links_name/links_author used only in
-- LIKE '%...%' searches (content_search_proc.php) — leading-wildcard LIKE cannot use a
-- standard B-tree index, so an index here would add write overhead with zero read benefit.
