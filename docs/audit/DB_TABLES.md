# DB Table Inventory — Real Schema

Pulled directly from the local `asdb` database (127.0.0.1, per `files/login_db.php`),
which is a clone of the live GoDaddy database, via `DESCRIBE` on each table on 2026-07-08.
A full dump is saved at `docs/audit/db_dump_2026-07-08.sql` (gitignored — contains real data).

12 tables exist in the database. `t_news_sub`, which `files/ata/add.php` inserts into,
**does not exist** — confirmed via `SHOW TABLES LIKE 't_news_sub'` (zero rows). See
FINDINGS.md for the bug this causes.

## t_links (1,524 rows)

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| links_name | varchar(255) | YES | | NULL |
| links_url | varchar(150) | YES | | NULL |
| links_author | varchar(255) | YES | | NULL |
| links_email | varchar(255) | YES | | NULL |
| links_desc | text | YES | | NULL |
| links_cat_1 .. links_cat_10 | int(3) | YES | | 0 |
| links_date_added | date | YES | | 0000-00-00 |
| links_dead | tinyint(1) | YES | | 0 |
| links_archived_url | varchar(150) | YES | | NULL |
| links_archived_date | date | YES | | 0000-00-00 |
| links_date_verified | date | NO | | 0000-00-00 |
| links_misc | varchar(255) | YES | | NULL |
| links_v_sub | int(1) | NO | | 0 |
| links_active | tinyint(1) | NO | | 1 |
| links_recommended | tinyint(1) | NO | | 0 |

Note: schema has 10 category slots (`links_cat_1`..`links_cat_10`); code only ever
reads/writes `links_cat_1` through `links_cat_5` (see table_result_cat.php) — the
other 5 slots exist in the DB but are unused by any current code path.

## t_news (113 rows)

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| news_date | date | NO | | NULL |
| news_story | mediumtext | YES | | NULL |
| news_v_sub | tinyint(4) | NO | | 0 |
| news_active | tinyint(1) | NO | | 1 |

`files/ata/add.php` writes to `t_news_sub`, a table that does not exist in this
database — see FINDINGS.md.

## t_cat_main

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | tinyint(11) | NO | UNI | NULL |
| cat_main_id | int(11) | NO | PRI | 0 |
| cat_main_title | varchar(50) | YES | | NULL |

## t_cat_sub

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(3) | NO | PRI (auto_increment) | |
| cat_sub_id | int(11) | NO | MUL | NULL |
| cat_sub_ref_main_id | int(3) | NO | | NULL |
| cat_sub_title | varchar(255) | YES | | NULL |
| cat_sub_desc | varchar(255) | YES | | NULL |
| cat_sub_title_short | varchar(50) | YES | | NULL |

## t_cat_spec — exists in DB, unused by any code

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(3) | NO | PRI (auto_increment) | |
| cat_spec_id | int(3) | NO | | NULL |
| cat_spec_sub_ref_sub_id | int(3) | NO | | NULL |
| cat_spec_title | varchar(255) | NO | | NULL |
| cat_spec_desc | varchar(255) | NO | | NULL |
| cat_spec_title_short | varchar(50) | NO | | NULL |

Looks like a planned third category tier (main → sub → spec) that was never wired
into any PHP file — `grep -rn "cat_spec" files` returns nothing. Confirm with client
whether this is intentionally unfinished or safe to drop.

## t_cal

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI | NULL |
| cal_name | text | NO | | NULL |
| cal_url | text | NO | | NULL |
| cal_date_start | date | NO | | NULL |
| cal_date_end | date | NO | | NULL |
| cal_location | text | NO | | NULL |
| cal_v_sub | tinyint(1) | NO | | NULL |

## t_cfund

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| cfund_name | text | NO | | NULL |
| cfund_url | text | NO | | NULL |
| cfund_date_start | date | NO | | NULL |
| cfund_date_end | date | NO | | NULL |
| cfund_active | tinyint(1) | NO | | NULL |
| cfund_v_sub | tinyint(1) | NO | | NULL |

## t_mags_online

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(1) | NO | PRI | NULL |
| online_name | text | NO | | NULL |
| online_url | text | NO | | NULL |
| online_issue | int(3) | NO | | NULL |

## t_mags_print

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(1) | NO | PRI | NULL |
| print_name | text | NO | | NULL |
| print_url | text | NO | | NULL |
| print_issue | int(3) | NO | | NULL |

## t_repair

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| repair_name | text | NO | | NULL |
| repair_url | text | NO | | NULL |
| repair_country | text | NO | | NULL |

## t_top10

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| top10_name | text | NO | | NULL |
| top10_url | text | NO | | NULL |
| top10_order | int(11) | NO | | NULL |

## t_vendor

| Column | Type | Null | Key | Default |
|---|---|---|---|---|
| id | int(11) | NO | PRI (auto_increment) | |
| vendor_name | text | NO | | NULL |
| vendor_url | text | NO | | NULL |

## Indexes observed

Beyond primary keys, only `t_cat_sub.cat_sub_id` has a non-primary key (`MUL`).
No other table has a secondary index — every other lookup (`t_links` by category,
`t_news` by active flag, etc.) does a full table scan. Not urgent at current row
counts (1,524 max), but worth flagging for Phase 01 if the admin module adds
filtering/search UI that runs these queries more often.
