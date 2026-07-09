-- Category Hierarchy: DOWN
-- Drops t_categories. t_cat_main/t_cat_sub were never modified so nothing
-- to restore there. The links_cat_1..5 UPDATEs from the up-migration
-- (clearing value 1, remapping 42->41) are NOT reversed: the original
-- values were confirmed junk/buggy data, not meaningful state, so this is
-- a documented one-way cleanup rather than a reversible schema change.

DROP TABLE t_categories;
