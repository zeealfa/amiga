# Findings — Phase 00 Audit

## Confirmed bug: "Add News" in the admin prototype is completely broken

`files/ata/add.php` runs:
```
mysqli_query($myConnection,"insert into t_news_sub (news_date,news_story,news_active) values (...)");
header('location:index.php');
```

Checked directly against the database (local clone of the live GoDaddy DB,
`SHOW TABLES LIKE 't_news_sub'`): **the table `t_news_sub` does not exist.**
Only `t_news` exists (113 rows), and that's what every other page uses —
`files/ata/a_news.php` (admin list), `files/content_news.php` (public site),
`files/ata/edit.php`, `files/ata/update.php`, `files/ata/delete.php`.

`add.php` has no error handling on that query (no `or die`, no check of the
return value), so when the insert fails against a non-existent table, mysqli
just returns `false` silently and the code redirects to `index.php` anyway —
**the admin sees no error and has no way of knowing the post was never saved.**

**Action:** confirm with client this was never noticed (likely, given the
silent failure) — fix by pointing `add.php` at `t_news` in Phase 01/03, not
fixed as part of this audit.

## Risk: three places build SQL by string interpolation of user input

| File | Input | Query |
|---|---|---|
| content_categories.php:5 | `$_GET['cat_id']` | `SELECT * FROM t_cat_sub where cat_sub_id=$cat_id` |
| content_search_proc.php | `$_POST['search']` | `... links_desc LIKE '%$search_2%' OR ...` |
| table_result_cat.php:29,138 | `$cat_id` (same source as above) | `SELECT ... WHERE ... links_cat_1=$cat_id OR ...` |

None use prepared statements or input sanitization. `cat_id` looks numeric-only
in current usage, which limits (but doesn't eliminate) exploitability; the
search field is free text and is the higher-risk one. Flagged for Phase 02/03
remediation — no fix applied during audit.

## Risk: DB credentials are duplicated and hardcoded in two files

`files/login_db.php` and `files/ata/conn.php` each hardcode DB credentials
inline (currently pointing at `127.0.0.1` / user `admin` — a local clone of
the live GoDaddy DB — with commented-out alternates for other hosts). No
shared config file. Any credential rotation means editing two files by hand
and keeping them in sync manually.

## Note: no authentication on files/ata/

The entire admin prototype is reachable by anyone who knows/guesses the URL —
there is no login check anywhere in `files/ata/`. Expected (Phase 03 adds
auth), but worth stating plainly: **do not link to `/ata/` from the public
site or search engines before Phase 03 ships.**

## Found while pulling the real schema: an unused third category tier

The database has a `t_cat_spec` table (main → sub → **spec**) that no PHP file
references at all (`grep -rn "cat_spec" files` — zero matches). Either an
abandoned feature or a planned one that never got built. Confirm with client
whether it's safe to ignore/drop, or whether it's meant to be part of the
category system Phase 02+ touches.

## Found while pulling the real schema: unused category slots on t_links

`t_links` has `links_cat_1` through `links_cat_10`, but every query in the
current code (`table_result_cat.php`) only ever reads `links_cat_1` through
`links_cat_5`. The other five columns exist and may hold data but nothing
in the current codebase surfaces them.

## No secondary indexes beyond t_cat_sub.cat_sub_id

Every other table relies on primary-key-only lookups; filtering (e.g. `t_links`
by category) does a full table scan. Not urgent at current row counts
(≤1,524 rows), but worth revisiting if the admin module adds heavier filtering.
