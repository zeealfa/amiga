-- Phase 01: DB Schema Cleanup — DOWN
-- Exact reverse of 0001_phase01_schema_cleanup_up.sql

-- Same sql_mode scoping as the up-migration — see that file for why. DROP
-- COLUMN on t_links re-validates the same pre-existing links_date_added
-- default and fails without this override.
SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION';

DROP INDEX cat_main_title_idx ON t_cat_main;

DROP INDEX cat_sub_title_short_idx ON t_cat_sub;
DROP INDEX cat_sub_ref_main_id_idx ON t_cat_sub;

DROP INDEX news_date_idx ON t_news;
DROP INDEX news_active_idx ON t_news;

DROP INDEX links_date_added_idx ON t_links;
DROP INDEX links_date_verified_idx ON t_links;
DROP INDEX links_name_idx ON t_links;
DROP INDEX links_cat_5_idx ON t_links;
DROP INDEX links_cat_4_idx ON t_links;
DROP INDEX links_cat_3_idx ON t_links;
DROP INDEX links_cat_2_idx ON t_links;
DROP INDEX links_cat_1_idx ON t_links;
DROP INDEX links_dead_idx ON t_links;

ALTER TABLE t_cat_sub  DROP COLUMN cat_sub_active;
ALTER TABLE t_cat_main DROP COLUMN cat_main_active;

ALTER TABLE t_links DROP COLUMN links_verified;

ALTER TABLE t_vendor      DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_top10       DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_repair      DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_mags_print  DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_mags_online DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_cfund       DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_cal         DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_cat_spec    DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_cat_sub     DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_cat_main    DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_news        DROP COLUMN updated_at, DROP COLUMN created_at;
ALTER TABLE t_links       DROP COLUMN updated_at, DROP COLUMN created_at;
