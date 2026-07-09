-- Link Categories Join Table: UP
-- Replaces the fixed links_cat_1..5 columns on t_links with a real
-- many-to-many join table. links_cat_1..10 are left in place, untouched,
-- unused (deferred cleanup, same pattern as t_cat_main/t_cat_sub after
-- the category-hierarchy migration).

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
