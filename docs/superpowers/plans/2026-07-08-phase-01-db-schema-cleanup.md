# Phase 01: Database Schema Cleanup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the missing schema foundation (timestamps, moderation-ready boolean flags on categories, missing indexes) that Phase 00's audit found lacking, without breaking any existing public page, and with every change written as reversible SQL.

**Architecture:** Two hand-written, reversible `.sql` scripts (`up`/`down`) applied directly to the local `asdb` MySQL database via the XAMPP `mysql.exe` client — no migration framework, consistent with the project's no-build-step constraint. Every change is purely additive (`ADD COLUMN` / `ADD INDEX`); nothing is renamed, dropped, or altered destructively, because `SELECT *` + associative-array fetch is used everywhere in `files/` (confirmed via grep — zero positional-index array access), so new columns are automatically safe for every existing query.

**Tech Stack:** MySQL/MariaDB (via `/d/xampp/mysql/bin/mysql.exe`), plain `.sql` files, `curl` for regression checks against the local `amiga.test` vhost.

**Scope decisions locked in by the client before this plan was written:**
1. `t_cat_spec` (unused table, 1 row) and `links_cat_6`-`links_cat_10` (confirmed **live**, rendered on every link row via `table_link.php:212-216` — corrected from an earlier, wrong DEAD_CODE.md finding) are both left untouched. No drops in this phase.
2. The roadmap's "status enum for submissions" milestone is **deferred** — no submissions table exists yet; it will be designed alongside the rebuilt admin console, not now.
3. Moderation-style flags (`is_active`/`status`) on `t_links` and `t_news` are **deferred** to the admin console rebuild. This phase only adds `is_active`-equivalent flags to the two category tables, per the roadmap's original, narrower wording.
4. Column naming: no renames needed anywhere — `DESCRIBE` output for all 12 tables already follows the `<table_short>_<field>` convention confirmed in `NAMING_CONVENTIONS_DRAFT.md` item 4.
5. `t_cat_main` has a pre-existing oddity — both an `id` (tinyint(11), UNI) and a `cat_main_id` (int(11), PRI, default 0) column, and the app code (`sidebar_categories_sub_01.php`) uses `cat_main_id`, not `id`, as the real foreign-key-referenced value. This is **not touched** in this phase — fixing a live PK/FK mismatch is out of scope for an additive schema cleanup and belongs in a dedicated, separately-planned task if ever done.

---

### Task 1: Fresh full DB backup before any schema change

**Files:**
- Create: `docs/audit/db_dump_phase01_pre.sql` (gitignored — matches existing `docs/audit/*.sql` pattern)

- [ ] **Step 1: Take the dump**

Run:
```bash
"/d/xampp/mysql/bin/mysqldump.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb > "D:\xampp\htdocs\amiga\docs\audit\db_dump_phase01_pre.sql"
```

- [ ] **Step 2: Verify the dump is non-empty and contains all 12 tables**

Run:
```bash
grep -c "^CREATE TABLE" "D:/xampp/htdocs/amiga/docs/audit/db_dump_phase01_pre.sql"
```
Expected: `12`

- [ ] **Step 3: Confirm it's gitignored (don't skip this — a prior session already found a stray dump that wasn't)**

Run:
```bash
cd /d/xampp/htdocs/amiga && git check-ignore -v docs/audit/db_dump_phase01_pre.sql
```
Expected: prints a match against the `docs/audit/*.sql` line in `.gitignore`. If it prints nothing, STOP — do not proceed until the file is confirmed ignored.

---

### Task 2: Write the UP migration SQL

**Files:**
- Create: `db/migrations/0001_phase01_schema_cleanup_up.sql`

Placed under `db/migrations/` (new top-level folder, parallel to `docs/`) rather than inside `files/` — these are DB administration scripts, not site code that gets included/executed by PHP at runtime, so they don't fall under the "all real code changes happen inside `files/`" rule.

- [ ] **Step 1: Write the full up-migration**

```sql
-- Phase 01: DB Schema Cleanup — UP
-- Adds created_at/updated_at to all 12 tables, links_verified to t_links,
-- is_active-equivalent flags to t_cat_main/t_cat_sub, and indexes on columns
-- used in WHERE/ORDER BY clauses (see docs/audit/DB_TABLES.md for evidence).
-- Purely additive — no renames, no drops, no destructive ALTERs.

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
```

- [ ] **Step 2: Save the file, then sanity-check statement count**

Run:
```bash
grep -c "^ALTER TABLE" "D:/xampp/htdocs/amiga/db/migrations/0001_phase01_schema_cleanup_up.sql"
```
Expected: `27` (12 timestamp ALTERs + 1 links_verified + 2 category active flags + 12 index ALTERs = 27)

---

### Task 3: Write the DOWN migration SQL (exact reverse of Task 2)

**Files:**
- Create: `db/migrations/0001_phase01_schema_cleanup_down.sql`

- [ ] **Step 1: Write the full down-migration**

```sql
-- Phase 01: DB Schema Cleanup — DOWN
-- Exact reverse of 0001_phase01_schema_cleanup_up.sql

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
```

- [ ] **Step 2: Cross-check every UP change has exactly one DOWN counterpart**

Run:
```bash
grep -c "^ALTER TABLE\|^DROP INDEX" "D:/xampp/htdocs/amiga/db/migrations/0001_phase01_schema_cleanup_down.sql"
```
Expected: `27` (must match Task 2 Step 2's count exactly)

---

### Task 4: Apply the UP migration to the local DB and verify

**Files:** none (DB-only step)

- [ ] **Step 1: Apply it**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb < "D:\xampp\htdocs\amiga\db\migrations\0001_phase01_schema_cleanup_up.sql"
```
Expected: no output, exit code 0 (MySQL CLI is silent on success for `ALTER TABLE`).

- [ ] **Step 2: Verify t_links got all new columns and indexes**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb -e "DESCRIBE t_links;" 2>&1
```
Expected: output includes rows for `links_verified`, `created_at`, `updated_at` that weren't there before.

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb -e "SHOW INDEX FROM t_links;" 2>&1
```
Expected: `Key_name` column lists `links_dead_idx`, `links_cat_1_idx` through `links_cat_5_idx`, `links_name_idx`, `links_date_verified_idx`, `links_date_added_idx`, alongside the pre-existing `PRIMARY`.

- [ ] **Step 3: Verify t_cat_main and t_cat_sub got their active flags**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb -e "DESCRIBE t_cat_main; DESCRIBE t_cat_sub;" 2>&1
```
Expected: `cat_main_active` present in the first result set, `cat_sub_active` in the second, both `tinyint(1)`, `NO` for Null, default `1`.

- [ ] **Step 4: Spot-check row data wasn't touched**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb -e "SELECT COUNT(*) FROM t_links; SELECT COUNT(*) FROM t_news;" 2>&1
```
Expected: `1524` and `113` — same row counts as recorded in `docs/audit/DB_TABLES.md` before this migration.

---

### Task 5: Regression-test the live public site after migration

**Files:** none (verification-only step)

- [ ] **Step 1: Homepage / news still renders**

Run:
```bash
curl -s -o /tmp/phase01_home.html -w "HTTP_STATUS:%{http_code}\n" http://amiga.test/index.php
grep -c "LATEST NEWS" /tmp/phase01_home.html
```
Expected: `HTTP_STATUS:200`, and the grep prints `1` or higher (page still contains real content, not a PHP error page).

- [ ] **Step 2: Category entry point still renders (uses the just-renamed entry_categories.php + newly-indexed t_cat_sub/t_links)**

Run:
```bash
curl -s -o /tmp/phase01_cat.html -w "HTTP_STATUS:%{http_code}\n" "http://amiga.test/entry_categories.php?cat_id=1"
grep -ic "fatal error\|warning" /tmp/phase01_cat.html
```
Expected: `HTTP_STATUS:200`, and the grep prints `0` (no PHP errors/warnings leaked into the HTML output).

- [ ] **Step 3: Search entry point still renders (uses newly-indexed t_links columns)**

Run:
```bash
curl -s -o /tmp/phase01_search.html -w "HTTP_STATUS:%{http_code}\n" -X POST -d "search=amiga" http://amiga.test/entry_search.php
grep -ic "fatal error\|warning" /tmp/phase01_search.html
```
Expected: `HTTP_STATUS:200`, grep prints `0`.

- [ ] **Step 4: If any check fails, STOP and roll back immediately**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb < "D:\xampp\htdocs\amiga\db\migrations\0001_phase01_schema_cleanup_down.sql"
```
Then re-run Task 4 Step 2 to confirm the rollback actually removed the new columns/indexes before investigating further. Do not proceed to Task 6 until all three regression checks in Steps 1-3 pass cleanly.

---

### Task 6: Prove the DOWN migration actually works (reversibility is a stated roadmap requirement, not just a filename)

**Files:** none (verification-only step)

- [ ] **Step 1: Roll back**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb < "D:\xampp\htdocs\amiga\db\migrations\0001_phase01_schema_cleanup_down.sql"
```

- [ ] **Step 2: Confirm every added column/index is gone**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb -e "DESCRIBE t_links;" 2>&1 | grep -c "created_at\|updated_at\|links_verified"
```
Expected: `0`

- [ ] **Step 3: Re-apply UP (leave the DB in the finished, migrated state)**

Run:
```bash
"/d/xampp/mysql/bin/mysql.exe" -h127.0.0.1 -uadmin -pMasukaja12 asdb < "D:\xampp\htdocs\amiga\db\migrations\0001_phase01_schema_cleanup_up.sql"
```

- [ ] **Step 4: Re-run Task 5's three curl checks one final time to confirm the site is healthy in its final state**

(Same three commands as Task 5 Steps 1-3.)

---

### Task 7: Update DB_TABLES.md to reflect the new columns and indexes

**Files:**
- Modify: `docs/audit/DB_TABLES.md`

- [ ] **Step 1: Add the new columns to the `t_links` table listing**

In the `## t_links` section, after the `links_recommended` row, add:
```
| links_verified | tinyint(1) | NO | | 0 |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP |
| updated_at | timestamp | NO | | CURRENT_TIMESTAMP |
```

- [ ] **Step 2: Add `created_at`/`updated_at` rows to every other table's listing in the same file** (`t_news`, `t_cat_main`, `t_cat_sub`, `t_cat_spec`, `t_cal`, `t_cfund`, `t_mags_online`, `t_mags_print`, `t_repair`, `t_top10`, `t_vendor`), plus `cat_main_active`/`cat_sub_active` to the `t_cat_main`/`t_cat_sub` sections.

- [ ] **Step 3: Update the "Indexes observed" section at the bottom**

Replace:
```
Beyond primary keys, only `t_cat_sub.cat_sub_id` has a non-primary key (`MUL`).
No other table has a secondary index — every other lookup (`t_links` by category,
`t_news` by active flag, etc.) does a full table scan. Not urgent at current row
counts (1,524 max), but worth flagging for Phase 01 if the admin module adds
filtering/search UI that runs these queries more often.
```
With:
```
As of Phase 01 (db/migrations/0001_phase01_schema_cleanup_up.sql), indexes exist on:
`t_links` (links_dead, links_cat_1-5, links_name, links_date_verified, links_date_added),
`t_news` (news_active, news_date), `t_cat_sub` (cat_sub_id — pre-existing, plus
cat_sub_ref_main_id and cat_sub_title_short), `t_cat_main` (cat_main_title).
Text-search columns (links_desc/url/name/author, used only in leading-wildcard
LIKE queries) are deliberately not indexed — a B-tree index can't serve those lookups.
```

---

### Task 8: Update CHANGE.md (plain language, no local-server details)

**Files:**
- Modify: `CHANGE.md`

- [ ] **Step 1: Append a new dated entry**

```markdown

---

## 2026-07-08 (database groundwork)

Added groundwork to the database so future features have something solid to
build on, without changing how anything currently looks or works. Every table
now automatically records when a row was created and last changed. The two
category tables gained an on/off switch for hiding a category without deleting
it. The links table gained a spot to mark a link as "verified" going forward.
Also added several behind-the-scenes lookups (indexes) so category and search
pages can find matching links faster as the link list grows.

Took a full backup immediately before making any of these changes, and wrote
every change as a paired "apply" and "undo" script, tested both directions,
before leaving the database in its new state. Checked the news page, the
category page, and the search box afterward to confirm everything still works
exactly as before.

Two things were deliberately left for later, to avoid building them twice: a
way to mark submitted links/news as pending/approved/rejected, and any
moderation controls — both will be designed together with the new admin
console rather than bolted on now.
```

- [ ] **Step 2: Proofread against the standing instruction** — re-read the new entry and confirm it contains zero mentions of `amiga.test`, vhosts, hosts file, Apache, or any local-machine-only detail. (It doesn't — it only describes DB structure changes in plain language.)

---

### Task 9: Commit

**Files:**
- Add: `db/migrations/0001_phase01_schema_cleanup_up.sql`, `db/migrations/0001_phase01_schema_cleanup_down.sql`, `docs/audit/DB_TABLES.md`, `docs/audit/DEAD_CODE.md` (already modified earlier this session with the links_cat_6-10 correction), `CHANGE.md`

- [ ] **Step 1: Confirm the gitignored dump isn't staged**

Run:
```bash
cd /d/xampp/htdocs/amiga && git status --short
```
Expected: `docs/audit/db_dump_phase01_pre.sql` does NOT appear (it's covered by the existing `docs/audit/*.sql` gitignore rule, confirmed in Task 1 Step 3).

- [ ] **Step 2: Stage and commit**

```bash
git add db/migrations/0001_phase01_schema_cleanup_up.sql db/migrations/0001_phase01_schema_cleanup_down.sql docs/audit/DB_TABLES.md docs/audit/DEAD_CODE.md CHANGE.md
git commit -m "Phase 01: additive DB schema cleanup (timestamps, category active flags, indexes)

Adds created_at/updated_at to all 12 tables, links_verified to t_links,
cat_main_active/cat_sub_active to the category tables, and indexes on
every column found (via grep) to be used in a WHERE/ORDER BY clause.
Purely additive — verified reversible (up/down/up tested) and verified
against the live local site with curl before and after.

Corrects an earlier DEAD_CODE.md finding: links_cat_6-10 were wrongly
marked unused — they render on every link row via table_link.php."
```

- [ ] **Step 3: Verify**

Run:
```bash
git status --short
git log --oneline -3
```
Expected: clean working tree (aside from the untracked stray screenshot already present before this session), and the new commit at HEAD.

---

## Risk Review (most to least risky)

1. **Schema changes on a database cloned from production** — mitigated by Task 1 (fresh dump before touching anything) and Task 6 (proving the down-migration actually works, not just trusting the SQL was written correctly, before considering the phase done).
2. **Silent breakage of a public page that wasn't curl-tested** — mitigated by Task 5's explicit fatal-error/warning grep on top of the HTTP status check (a 200 status alone wouldn't catch a PHP warning printed into the middle of the page).
3. **Index bloat on tables this small (max 1,524 rows) providing no real benefit while adding write overhead** — accepted risk, explicitly called out in the plan; the roadmap milestone explicitly asks for this, and the write volume on these tables is negligible (news/links are added rarely, not high-frequency).
4. **`db/migrations/` being a new top-level folder not mentioned in CLAUDE.md** — low risk, but Task 9's commit message and this plan both document why it lives there instead of `files/`, so the reasoning isn't lost.

---

Plan complete and saved to `docs/superpowers/plans/2026-07-08-phase-01-db-schema-cleanup.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
