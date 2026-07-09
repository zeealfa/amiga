-- ============================================================================
-- Pending migrations for testamigasource.com — combined for phpMyAdmin
-- ============================================================================
-- Generated 2026-07-09. Paste this whole file into phpMyAdmin's SQL tab on
-- testamigasource.com's database and run it once, top to bottom.
--
-- ASSUMPTION: 0001_phase01_schema_cleanup and 0002_phase03a_users_table are
-- ALREADY applied on this database (confirmed live per CHANGE.md's
-- 2026-07-08 "live deploy" / "color names" entries — created_at/updated_at
-- columns, links_verified, t_users, etc. should already exist). If you are
-- not sure, check first: this script will error out loudly (rather than
-- silently corrupt data) if it hits a column/table that already exists, so
-- it is safe to try — just stop and roll back the failed statement if that
-- happens, do not continue past an unexpected error.
--
-- Source files (kept as the source of truth — this file is a deployment
-- copy, not a new migration):
--   db/migrations/0003_phase03d_links_soft_delete_up.sql
--   db/migrations/0004_category_hierarchy_up.sql
--   db/migrations/0005_link_categories_up.sql
--
-- REQUIRES MariaDB 10.2+ / MySQL 8.0+ (window function ROW_NUMBER() OVER
-- is used in the category-hierarchy section below). If phpMyAdmin reports a
-- syntax error on ROW_NUMBER(), the server is older than that and this
-- script cannot run as-is — stop and report back rather than improvising a
-- workaround, since the row numbering here determines each category's
-- display sort order.
-- ============================================================================


-- ============================================================================
-- SECTION 1 of 3 — Phase 03d: links soft delete (source: 0003_..._up.sql)
-- Adds links_deleted_at to t_links for soft delete. Purely additive.
-- ============================================================================

ALTER TABLE t_links
  ADD COLUMN links_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER links_active,
  ADD INDEX idx_links_deleted_at (links_deleted_at);


-- ============================================================================
-- SECTION 2 of 3 — Category hierarchy (source: 0004_category_hierarchy_up.sql)
-- Replaces t_cat_main/t_cat_sub with a single arbitrary-depth t_categories
-- table. t_cat_main and t_cat_sub are left in place, untouched, unused
-- (deferred cleanup, same pattern as links_cat_6..10).
-- ============================================================================

CREATE TABLE t_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    title_short VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_parent_id (parent_id),
    CONSTRAINT fk_t_categories_parent FOREIGN KEY (parent_id) REFERENCES t_categories(id)
);

-- Reserve a high starting id for root categories so their fresh
-- auto-increment ids cannot collide with any preserved cat_sub_id (max 53).
-- Without this, root categories would get ids 1..17, which overlap with
-- cat_sub_id values in that same range (confirmed by a failed dry run).
ALTER TABLE t_categories AUTO_INCREMENT = 1000;

-- Step A: former main categories become root rows with fresh auto-increment
-- ids (old cat_main_id values overlap with cat_sub_id values, so they can't
-- be reused directly).
INSERT INTO t_categories (parent_id, title, title_short, description, sort_order, active)
SELECT NULL, cat_main_title, cat_main_title, NULL,
       ROW_NUMBER() OVER (ORDER BY cat_main_title ASC) - 1,
       cat_main_active
FROM t_cat_main;

-- Step B: former sub categories with a valid parent keep cat_sub_id as
-- their new id (this is what makes links_cat_1..5 need no remapping).
--
-- Exception: t_cat_sub.cat_sub_id is not actually unique -- id=41
-- "AROS/MORPH/AMITHLON/ETC..." and id=42 "INTERVIEWS" both have
-- cat_sub_id=41 (confirmed live: `SELECT cat_sub_id, id FROM t_cat_sub
-- WHERE id IN (41,42)` returns cat_sub_id=41 for both). Content review of
-- every link tagged 41 (12 links: general software/emulator sites) and
-- every link tagged 42 (43 links: overwhelmingly MorphOS/Pegasos/Amithlon
-- content, only one incidentally titled "Interview...") showed neither
-- group is meaningfully interview content -- "INTERVIEWS" was never
-- actually used for its labeled purpose. So id=41 keeps cat_sub_id 41
-- (excluded from this bulk insert, handled below), and id=42 "INTERVIEWS"
-- is excluded here and inserted separately with a fresh id, since reusing
-- cat_sub_id 41 for it would collide with id=41's row.
INSERT INTO t_categories (id, parent_id, title, title_short, description, sort_order, active)
SELECT s.cat_sub_id, m.id, s.cat_sub_title, s.cat_sub_title_short, s.cat_sub_desc,
       ROW_NUMBER() OVER (PARTITION BY s.cat_sub_ref_main_id ORDER BY s.cat_sub_title_short ASC) - 1,
       s.cat_sub_active
FROM t_cat_sub s
JOIN t_cat_main old_m ON old_m.cat_main_id = s.cat_sub_ref_main_id
JOIN t_categories m ON m.title = old_m.cat_main_title AND m.parent_id IS NULL
WHERE s.cat_sub_ref_main_id NOT IN (0, 2)
  AND s.id <> 42;

-- Step B2: "INTERVIEWS" (t_cat_sub.id=42), excluded above because its
-- cat_sub_id (41) collides with "AROS/MORPH/AMITHLON/ETC..." (id=41).
-- Gets a fresh id (1017 -- next free id after the 17 root categories
-- reserved at 1000..1016) instead of the colliding cat_sub_id. No links
-- reference it after Step E remaps all links_cat_x=42 to 41 below, so it
-- is preserved as an empty category rather than dropped.
INSERT INTO t_categories (id, parent_id, title, title_short, description, sort_order, active)
SELECT 1017, m.id, s.cat_sub_title, s.cat_sub_title_short, s.cat_sub_desc,
       (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM t_categories WHERE parent_id = m.id),
       s.cat_sub_active
FROM t_cat_sub s
JOIN t_cat_main old_m ON old_m.cat_main_id = s.cat_sub_ref_main_id
JOIN t_categories m ON m.title = old_m.cat_main_title AND m.parent_id IS NULL
WHERE s.id = 42;

-- Step C: the two orphaned sub categories (cat_sub_id 43 "Companies", ref 2;
-- cat_sub_id 44 "Directories & Links", ref 0 -- neither ref matches any
-- cat_main_id) are promoted to root-level categories rather than dropped,
-- since 40 live links reference them.
INSERT INTO t_categories (id, parent_id, title, title_short, description, sort_order, active)
SELECT s.cat_sub_id, NULL, s.cat_sub_title, s.cat_sub_title_short, s.cat_sub_desc,
       (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM t_categories WHERE parent_id IS NULL)
           + ROW_NUMBER() OVER (ORDER BY s.cat_sub_id) - 1,
       s.cat_sub_active
FROM t_cat_sub s
WHERE s.cat_sub_ref_main_id IN (0, 2);

-- Step D: clear junk padding value 1 (never a valid cat_sub_id) from
-- links_cat_1..5.
UPDATE t_links SET links_cat_1 = 0 WHERE links_cat_1 = 1;
UPDATE t_links SET links_cat_2 = 0 WHERE links_cat_2 = 1;
UPDATE t_links SET links_cat_3 = 0 WHERE links_cat_3 = 1;
UPDATE t_links SET links_cat_4 = 0 WHERE links_cat_4 = 1;
UPDATE t_links SET links_cat_5 = 0 WHERE links_cat_5 = 1;

-- Step E: remap value 42 to 41. Content review (see Step B comment) showed
-- all 43 links tagged 42 are AROS/MORPH/AMITHLON/ETC content (id 41), not
-- interview content, so this lands them on the correct category rather
-- than the now-empty "INTERVIEWS" (id 1017).
UPDATE t_links SET links_cat_1 = 41 WHERE links_cat_1 = 42;
UPDATE t_links SET links_cat_2 = 41 WHERE links_cat_2 = 42;
UPDATE t_links SET links_cat_3 = 41 WHERE links_cat_3 = 42;
UPDATE t_links SET links_cat_4 = 41 WHERE links_cat_4 = 42;
UPDATE t_links SET links_cat_5 = 41 WHERE links_cat_5 = 42;


-- ============================================================================
-- SECTION 3 of 3 — Link categories join table (source: 0005_link_categories_up.sql)
-- Replaces the fixed links_cat_1..5 columns on t_links with a real
-- many-to-many join table. links_cat_1..10 are left in place, untouched,
-- unused (deferred cleanup, same pattern as t_cat_main/t_cat_sub above).
-- ============================================================================

-- No FOREIGN KEY constraints: t_links is MyISAM, which cannot be the
-- target of an InnoDB foreign key (errno 150). Matches the existing
-- schema's style anyway -- links_cat_1..5 never had FK enforcement
-- either. Referential integrity to t_links/t_categories is enforced in
-- application code only.
CREATE TABLE t_link_categories (
    link_id INT NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (link_id, category_id)
);

-- Backfill from links_cat_1..5 (links_cat_6..10 are confirmed always 0,
-- per the category-hierarchy migration's findings -- nothing to backfill
-- from those). INSERT IGNORE makes this idempotent against any duplicate
-- category assignment across the five columns for the same link (the
-- composite primary key would otherwise error on a collision).
INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_1 FROM t_links WHERE links_cat_1 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_2 FROM t_links WHERE links_cat_2 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_3 FROM t_links WHERE links_cat_3 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_4 FROM t_links WHERE links_cat_4 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_5 FROM t_links WHERE links_cat_5 <> 0;

-- ============================================================================
-- End of script. After running, verify in phpMyAdmin's Browse tab:
--   - t_categories has rows (root categories should show ids 1000+)
--   - t_link_categories has rows (roughly one row per link per assigned
--     category -- expect several hundred to a couple thousand rows total)
--   - t_links.links_deleted_at column exists and is NULL for all rows
-- ============================================================================
