# Phase 00 — Codebase Audit & Standards Setup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce the concrete audit artifacts (`docs/audit/*.md`) that `roadmap.html` Phase 00 calls for, so Phases 01+ start from documented facts about `files/` instead of assumptions.

**Architecture:** Each task inspects a specific slice of `files/` using `grep`/`find` against the real code, writes one Markdown artifact under `docs/audit/`, and commits it. Two tasks (DB dump, local dev mirror) cannot be completed from this machine — they require GoDaddy cPanel access — and are marked blocked rather than faked.

**Tech Stack:** Plain grep/find over the existing PHP codebase; no new tooling introduced (per the vanilla-PHP project constraint).

---

## Known constraints going in

- No test suite exists and none is planned for this phase — Phase 00 is documentation, not code change. "Verification" below means "run this command, confirm the output," not unit tests.
- The live DB (`asdb`) is only reachable from the GoDaddy cPanel host per `files/login_db.php` (`$host = "localhost"` resolves to the shared MySQL host *on that server*, not on this dev machine). Two tasks below are blocked on you providing either phpMyAdmin export access or a DB dump file.

---

### Task 1: File & include map

**Files:**
- Create: `docs/audit/FILE_MAP.md`

- [ ] **Step 1: Confirm the include chain is fully traced**

Already traced by reading every file in `files/`. The chain is:

```
index.php
  → login_db.php        (opens $myConnection, sets $_SESSION['content_type']='news')
  → page_builder.php
      → sec_header.php   → mod_header.php
      → sec_body.php
          → mod_sidebar_chooser.php → sidebar_search.php, sidebar_add_link.php,
              sidebar_calendar.php → sidebar_calendar_sub.php,
              sidebar_crowdfunding.php → sidebar_crowdfunding_sub.php,
              sidebar_categories.php → sidebar_categories_sub_01.php, sidebar_categories_sub_02.php,
              sidebar_top10.php → sidebar_top10_sub.php,
              sidebar_shops_vendors.php → sidebar_shops_vendors_sub.php,
              sidebar_service_repair.php → sidebar_service_repair_sub.php,
              sidebar_publications.php → sidebar_publications_sub_online.php, sidebar_publications_sub_print.php
              (sidebar_tabor.php exists but its include is commented out — see Task 3)
          → content_news.php   [if content_type == 'news', the default]
          → content_categories.php  [if content_type == 'categories']
          → content_search.php     [if content_type == 'search']
      → sec_footer.php   → mod_footer.php

Entry points that set content_type before including page_builder.php:
  action_categories.php  → sets 'categories', includes login_db.php + page_builder.php
  action_search.php      → sets 'search', includes login_db.php + page_builder.php
  content_search_proc.php → invoked as a POST target; runs its own SQL directly (not through content_*.php)

table_link.php, table_result_cat.php, table_result_search.php, table_content_news_sub.php,
table_print_pub.php are row-renderer partials, included in a loop from content_*.php /
sidebar_*.php while iterating a mysqli result set — not standalone pages.

files/ata/ is a separate, self-contained admin prototype with its own connection
(ata/conn.php) — it does not share state with the public site beyond the same DB:
  ata/index.php  → static links to a_news.php, a_category.php, a_links_check.php
  ata/a_news.php → lists t_news, links to edit.php / delete.php, form posts to add.php
  ata/add.php, ata/edit.php, ata/update.php, ata/delete.php → CRUD actions (no auth gate)
  ata/a_category.php → read-only view of t_cat_main / t_cat_sub
  ata/a_links_check.php, ata/a_links_check_02.php → read-only view of t_links
```

- [ ] **Step 2: Write `docs/audit/FILE_MAP.md`**

```markdown
# File Map — amigasource.com

Traced by reading every file in `files/` on 2026-07-08. See PHASE-00 plan for the
raw include-chain notes this was built from.

## Public site (entry: files/index.php)

| File | Role |
|---|---|
| index.php | Entry point. Starts session, sets content_type=news, includes login_db.php + page_builder.php |
| action_categories.php | Alt entry point: sets content_type=categories, re-runs the same page_builder chain |
| action_search.php | Alt entry point: sets content_type=search, re-runs the same page_builder chain |
| login_db.php | Opens the one shared `$myConnection` (mysqli) used by every include below |
| page_builder.php | Outer `<table>` layout, includes sec_header/sec_body/sec_footer |
| sec_header.php, sec_footer.php | One-line wrappers around mod_header.php / mod_footer.php |
| mod_header.php | Site banner markup (static) |
| mod_footer.php | Site footer markup |
| sec_body.php | Sidebar + main-content two-column layout; switches on `$_SESSION['content_type']` |
| mod_sidebar_chooser.php | Includes all sidebar_*.php modules in a fixed order |
| sidebar_search.php, sidebar_add_link.php | Static/no-DB sidebar boxes |
| sidebar_calendar.php (+ _sub) | Reads `t_cal` |
| sidebar_crowdfunding.php (+ _sub) | Reads `t_cfund` |
| sidebar_categories.php (+ _sub_01, _sub_02) | Reads `t_cat_main`, `t_cat_sub` |
| sidebar_top10.php (+ _sub) | Reads `t_top10` |
| sidebar_shops_vendors.php (+ _sub) | Reads `t_vendor` |
| sidebar_service_repair.php (+ _sub) | Reads `t_repair` |
| sidebar_publications.php (+ _sub_online, _sub_print) | Reads `t_mags_online`, `t_mags_print` |
| sidebar_tabor.php | Exists on disk, include is commented out in mod_sidebar_chooser.php — dead |
| content_news.php | Default main content: reads `t_links` (counts) and `t_news` (listing) |
| content_news-(old).php | Orphaned duplicate of content_news.php — nothing includes it |
| content_categories.php | Reads `t_cat_sub` by `$_GET['cat_id']` (unsanitized, see FINDINGS.md) |
| content_search.php | Renders the search form/results shell for content_search_proc.php |
| content_search_proc.php | POST handler: builds a `LIKE` query against `t_links` from `$_POST['search']` (unsanitized, see FINDINGS.md) |
| table_link.php | Row renderer for a single `t_links` result (links out to an external "Maestro" admin tool at testamigasource.com/ata/maestrotest/) |
| table_result_cat.php | Paginated `t_links` results filtered by category |
| table_result_search.php | Row renderer for search results |
| table_content_news_sub.php | Row renderer for a news item |
| table_print_pub.php | Row renderer for print publications |

## Admin prototype (files/ata/ — unauthenticated)

| File | Role |
|---|---|
| conn.php | Its own separate mysqli connection (duplicate credentials from login_db.php) |
| index.php | Static dashboard; most links are `---` placeholders (only News, Links, Categories are wired) |
| a_news.php | Lists `t_news`, links to edit/delete, form posts to add.php |
| add.php | **Inserts into `t_news_sub`, not `t_news`** — see FINDINGS.md, this looks like a bug |
| edit.php, update.php, delete.php | CRUD against `t_news` (id-keyed) |
| a_category.php | Read-only view of `t_cat_main` / `t_cat_sub` |
| a_links_check.php, a_links_check_02.php | Read-only view of `t_links` |

## External system referenced but not in this repo

`table_link.php` links to `http://testamigasource.com/ata/maestrotest/t_links.php` — a
third-party admin tool ("Maestro") the client currently uses for link management. This is
what Phase 03 ("Replace Maestro") in roadmap.html replaces.
```

- [ ] **Step 3: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/FILE_MAP.md
git commit -m "docs: add Phase 00 file map"
```

---

### Task 2: Function inventory

**Files:**
- Create: `docs/audit/FUNCTIONS_INVENTORY.md`

- [ ] **Step 1: Run the search**

Run: `grep -rn "function\s*[A-Za-z_]" D:/xampp/htdocs/amiga/files --include=*.php`
Expected: no output (already run during planning — zero matches).

- [ ] **Step 2: Write `docs/audit/FUNCTIONS_INVENTORY.md`**

```markdown
# Function Inventory

Searched every .php file under files/ for `function` declarations
(`grep -rn "function\s*[A-Za-z_]" files --include=*.php`).

**Result: zero user-defined functions exist anywhere in the codebase.**

Every page is flat procedural PHP — the same DB-query-and-loop pattern
(mysqli_query → mysqli_fetch_array → inline HTML) is copy-pasted across
roughly 15 sidebar_*.php / table_*.php files instead of being extracted
into a shared helper. This is expected to be tackled in Phase 02
(Code Cleanup & Refactoring), not fixed here.
```

- [ ] **Step 3: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/FUNCTIONS_INVENTORY.md
git commit -m "docs: add Phase 00 function inventory"
```

---

### Task 3: Dead code & orphaned files

**Files:**
- Create: `docs/audit/DEAD_CODE.md`

- [ ] **Step 1: Verify content_news-(old).php is unreferenced**

Run: `grep -rn "content_news-(old)" D:/xampp/htdocs/amiga/files`
Expected: no output (already confirmed).

- [ ] **Step 2: Verify sidebar_tabor.php is unreferenced**

Run: `grep -n "sidebar_tabor" D:/xampp/htdocs/amiga/files/mod_sidebar_chooser.php`
Expected: `//include 'sidebar_tabor.php';			// not yet dynamically generated` — a commented-out line, confirming the file is never included.

- [ ] **Step 3: Write `docs/audit/DEAD_CODE.md`**

```markdown
# Dead Code & Orphaned Files

| File | Status | Evidence |
|---|---|---|
| files/content_news-(old).php | Orphaned — 145 lines, nothing includes it | `grep -rn "content_news-(old)" files` returns nothing |
| files/sidebar_tabor.php | Orphaned — include is commented out | mod_sidebar_chooser.php:22 `//include 'sidebar_tabor.php'; // not yet dynamically generated` |

## Other things worth removing/simplifying, not orphaned but noise

- Every file in `files/` (except a handful) has no trailing newline and most
  logic is written as one long single-line block rather than across multiple
  lines — makes diffs and code review harder. Example: `login_db.php`,
  `sidebar_calendar.php`, `content_search_proc.php` are each a single physical line.
  Reformatting is in scope for Phase 02, not this audit.
- `files/error_log` and `files/ata/error_log` are runtime logs, already excluded
  from git via `.gitignore` — not code, just noting they exist and will keep growing
  on the live server.

## Recommendation

Delete `content_news-(old).php` and `sidebar_tabor.php` in Phase 02 once the client
confirms neither is needed for reference. Do not delete during Phase 00 — audit only.
```

- [ ] **Step 4: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/DEAD_CODE.md
git commit -m "docs: add Phase 00 dead code findings"
```

---

### Task 4: Findings — bugs and risk items

**Files:**
- Create: `docs/audit/FINDINGS.md`

- [ ] **Step 1: Confirm the t_news / t_news_sub mismatch**

Run: `grep -n "t_news" D:/xampp/htdocs/amiga/files/ata/add.php D:/xampp/htdocs/amiga/files/ata/a_news.php D:/xampp/htdocs/amiga/files/ata/edit.php D:/xampp/htdocs/amiga/files/ata/update.php D:/xampp/htdocs/amiga/files/ata/delete.php`
Expected output shows `add.php` targets `t_news_sub` while the other four target `t_news` — confirming the admin "Add News" form writes to a table nothing else reads from.

- [ ] **Step 2: Confirm the unsanitized SQL inputs**

Run: `grep -n '\$_GET\|\$_POST' D:/xampp/htdocs/amiga/files/content_categories.php D:/xampp/htdocs/amiga/files/content_search_proc.php D:/xampp/htdocs/amiga/files/table_result_cat.php`
Expected: `content_categories.php` interpolates `$_GET['cat_id']` directly into a `SELECT`; `content_search_proc.php` interpolates `$_POST['search']` into a `LIKE` clause; `table_result_cat.php` interpolates a `$cat_id` sourced the same way. None use `mysqli_real_escape_string`, prepared statements, or casting.

- [ ] **Step 3: Write `docs/audit/FINDINGS.md`**

```markdown
# Findings — Phase 00 Audit

## Bug: "Add News" in the admin prototype writes to the wrong table

`files/ata/add.php` runs:
`insert into t_news_sub (news_date,news_story,news_active) values (...)`

But everything that *reads* news — `files/ata/a_news.php` (admin list),
`files/content_news.php` (public site) — and everything else that *edits/deletes*
news — `files/ata/edit.php`, `files/ata/update.php`, `files/ata/delete.php` —
all target `t_news`, not `t_news_sub`.

**Effect:** using the existing "Add" form in the admin prototype silently writes
records nobody ever sees again, in either the admin list or the live site.
**Action:** confirm with client whether `t_news_sub` is a leftover/staging table
or a straight typo, then fix in Phase 01 (DB) / Phase 03 (admin rebuild) —
not fixed as part of this audit.

## Risk: three places build SQL by string interpolation of user input

| File | Input | Query |
|---|---|---|
| content_categories.php:5 | `$_GET['cat_id']` | `SELECT * FROM t_cat_sub where cat_sub_id=$cat_id` |
| content_search_proc.php | `$_POST['search']` | `... links_desc LIKE '%$search_2%' OR ...` |
| table_result_cat.php:29,138 | `$cat_id` (same source as above) | `SELECT ... WHERE ... links_cat_1=$cat_id OR ...` |

None of these use prepared statements or input sanitization. `cat_id` looks
numeric-only in current usage, which limits (but doesn't eliminate) exploitability;
the search field is free text and is the higher-risk one. Flagged for Phase 02/03
remediation — no fix applied during audit.

## Risk: DB credentials are duplicated and hardcoded in two files

`files/login_db.php` and `files/ata/conn.php` each hardcode the same production
DB user/password inline (with commented-out alternates for a different host/db
name). No shared config file. Any credential rotation means editing two files by
hand and keeping them in sync manually.

## Note: no authentication on files/ata/

The entire admin prototype is reachable by anyone who knows/guesses the URL —
there is no login check anywhere in `files/ata/`. This is expected (Phase 03
adds auth) but is worth stating plainly: **do not link to `/ata/` from the
public site or search engines before Phase 03 ships.**
```

- [ ] **Step 4: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/FINDINGS.md
git commit -m "docs: add Phase 00 findings (bug + SQL injection risk + shared creds)"
```

---

### Task 5: DB table inventory (from code, not from schema)

**Files:**
- Create: `docs/audit/DB_TABLES.md`

- [ ] **Step 1: Re-run the table/column extraction**

Run: `grep -rhoE "t_[a-z_]+" D:/xampp/htdocs/amiga/files --include=*.php | sort -u`
Expected tables: `t_cal`, `t_cat_main`, `t_cat_sub`, `t_cfund`, `t_links`, `t_mags_online`, `t_mags_print`, `t_news`, `t_news_sub`, `t_repair`, `t_top10`, `t_vendor`.

- [ ] **Step 2: Write `docs/audit/DB_TABLES.md`**

```markdown
# DB Table Inventory (inferred from code — not a schema dump)

12 tables are referenced across `files/`. Columns listed below are only the ones
that appear in a SELECT/WHERE/INSERT/UPDATE in the code — **not** a full schema.
Types, nullability, defaults, and indexes are unknown until Task 6 (DB dump) is
unblocked.

| Table | Referenced from | Columns seen in code |
|---|---|---|
| t_links | content_news.php, table_result_cat.php, table_link.php, ata/a_links_check*.php | links_name, links_url, links_desc, links_author, links_date_verified, links_date_added, links_dead, links_archived_url, links_cat_1..links_cat_5 |
| t_news | content_news.php, ata/a_news.php, ata/edit.php, ata/update.php, ata/delete.php | id, news_date, news_story, news_active |
| t_news_sub | ata/add.php only | news_date, news_story, news_active (see FINDINGS.md — likely a bug target) |
| t_cat_main | ata/a_category.php | cat_main_id |
| t_cat_sub | ata/a_category.php, content_categories.php, sidebar_categories_sub_*.php | cat_sub_id, cat_sub_ref_main_id, cat_sub_title_short |
| t_cal | sidebar_calendar_sub.php | cal_date_start |
| t_cfund | sidebar_crowdfunding_sub.php | cfund_active, cfund_date_end |
| t_mags_online | sidebar_publications_sub_online.php | online_name |
| t_mags_print | sidebar_publications_sub_print.php | print_name |
| t_repair | sidebar_service_repair_sub.php | repair_name |
| t_vendor | sidebar_shops_vendors_sub.php | vendor_name |
| t_top10 | sidebar_top10_sub.php | top10_order, top10_name, top10_url |

**Gap:** this is a code-usage inventory, not the roadmap's required "columns,
types, nullability, indexes." That needs an actual `DESCRIBE <table>` or a
phpMyAdmin schema export from the live GoDaddy DB — see Task 6, which is
blocked pending access.
```

- [ ] **Step 3: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/DB_TABLES.md
git commit -m "docs: add Phase 00 DB table inventory (code-derived)"
```

---

### Task 6: Full DB dump before any changes — DONE

**Correction from initial plan draft:** `files/login_db.php` already points at
`127.0.0.1` with a working local account (`admin`) — this is a clone of the live
GoDaddy database, not something only reachable from the GoDaddy host. No client
action was needed after all.

- [x] **Step 1: Take the dump**

```bash
/d/xampp/mysql/bin/mysqldump.exe -h 127.0.0.1 -u admin -p'Masukaja12' asdb > D:/xampp/htdocs/amiga/docs/audit/db_dump_2026-07-08.sql
```
Result: 388-line dump written.

- [x] **Step 2: Gitignore it before it ever touches git**

Added `docs/audit/db_dump_*.sql` to `.gitignore` before the dump file was
created. Confirmed with `git check-ignore -v docs/audit/db_dump_2026-07-08.sql`
→ matched, and `git status --short` shows nothing for it.

- [x] **Step 3: Extract real schema, cross-check against code-derived inventory**

Ran `DESCRIBE` on all 12 tables directly (not parsed from the dump file, but
same source data). Result written to `docs/audit/DB_TABLES.md`, replacing the
earlier code-only-inferred version from Task 5. Found two discrepancies the
code-only pass couldn't catch:
  - `t_cat_spec` exists in the DB but is referenced by zero PHP files.
  - `t_links` has 10 category slots (`links_cat_1`..`10`); code only uses 5.
  - **`t_news_sub` (the table `ata/add.php` inserts into) does not exist at
    all** — confirms the Task 4 bug finding with certainty instead of suspicion.

- [ ] **Step 4: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/DB_TABLES.md .gitignore
git commit -m "docs: pull real DB schema, confirm t_news_sub does not exist"
```

---

### Task 7: Local dev environment mirroring production — ALREADY IN PLACE

**Correction from initial plan draft:** this was assumed to need setup from
scratch; it doesn't. `files/login_db.php` already runs against a local MySQL
(`127.0.0.1`, database `asdb`) that the user confirms is cloned from GoDaddy.
Verified directly:

```bash
/d/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u admin -p'Masukaja12' -e "SHOW TABLES FROM asdb;"
```
→ returned all 12 tables. `t_news` has 113 rows, `t_links` has 1,524 — real data,
not an empty schema-only clone.

- [x] **Step 1: Confirm XAMPP's Apache serves the site correctly — FOUND A BLOCKER**

Ran: `curl -s -o /dev/null -w "%{http_code}" http://localhost/amiga/files/index.php`
Result: `404`, even though `D:\xampp\htdocs\amiga\files\index.php` exists and
Apache is running (`Server: Apache/2.4.58` confirmed in response headers).

Root cause, found in `D:/xampp/apache/conf/extra/httpd-vhosts.conf`:
```
<VirtualHost *:80>
    DocumentRoot "D:/xampp/htdocs/fingerprint"
    ServerName localhost
</VirtualHost>
```
This vhost claims `ServerName localhost` for **all** port-80 traffic, so every
request to `http://localhost/...` — regardless of path — is served from the
`fingerprint` project's docroot, not the real `D:/xampp/htdocs`. This is why
`http://localhost/` 302-redirects to `devices.php` (a fingerprint-app route)
and `http://localhost/amiga/...` 404s: the `amiga` folder doesn't exist inside
`fingerprint`'s docroot.

**This isn't an amiga-project problem — it's a shared Apache config on this
machine affecting every htdocs project that doesn't have its own vhost.**
Not fixing this without asking, since editing `httpd-vhosts.conf` affects
other projects (`fingerprint`, `hassani_ai`, `sblport`) too. Two ways to
unblock browser testing for amiga specifically, both requiring your OK:
  1. Add a dedicated vhost (e.g. `ServerName amiga.test` →
     `DocumentRoot D:/xampp/htdocs/amiga/files`) plus a matching
     `C:\Windows\System32\drivers\etc\hosts` entry, same pattern already used
     for `hasani.test` and `jetty.test`.
  2. Temporarily comment out the `fingerprint` vhost's `ServerName localhost`
     line when testing amiga — not recommended, breaks fingerprint testing.

- [ ] **Step 2: Document the local environment in docs/audit/**

Add a short `docs/audit/LOCAL_ENV.md` noting: XAMPP path
(`D:\xampp\htdocs\amiga\files`), DB host/user (`127.0.0.1` / `admin`, password
held only in `files/login_db.php` — not duplicated into a new doc), and the
fact that this local DB is a clone (so it will drift from production over
time — re-clone before any Phase 01 schema work that needs current data).

---

### Task 8: Naming convention agreement (draft — needs client sign-off)

**Files:**
- Create: `docs/audit/NAMING_CONVENTIONS_DRAFT.md`

- [ ] **Step 1: Write the draft based on what's actually inconsistent today**

```markdown
# Naming Convention — Draft for Client Sign-Off

Current state (observed, not prescribed) has five different file-prefix
"namespaces" with no documented meaning:

- `sec_*` — layout section wrapper (2 files, each ~6 lines, just includes a `mod_*`)
- `mod_*` — actual layout module content
- `sidebar_*` — sidebar widgets, each paired with a `sidebar_*_sub.php` that
  does the actual DB query (the split isn't consistent: some widgets have a
  `_sub`, e.g. sidebar_calendar.php/_sub, others inline the query directly)
- `content_*` — main content area, switched on `$_SESSION['content_type']`
- `table_*` — row renderers, included in a loop from `content_*`/`sidebar_*`
- `action_*` — alternate entry points that just set `content_type` and rerun `page_builder.php`
- `a_*` (inside files/ata/) — admin pages, unrelated to the `action_*` prefix
  above despite the similar name — potential source of confusion

Proposed going forward (subject to client approval, applied in Phase 02 —
existing files are not renamed during Phase 00):

1. Keep `sec_*` / `mod_*` / `sidebar_*` / `content_*` / `table_*` as-is — they're
   established and renaming risks breaking includes for no functional gain.
2. Retire the `action_*` vs `a_*` prefix collision: rename the two `action_*.php`
   entry points to `entry_categories.php` / `entry_search.php` so `a_*` uniquely
   means "admin" going forward.
3. All new admin-module files (Phase 03+) use a single `admin_*` prefix instead
   of continuing the terse `a_*` / bare-verb (`add.php`, `edit.php`, `delete.php`)
   pattern in `files/ata/` — bare verbs collide across features once there's more
   than one entity type.
4. DB columns already consistently use `<table_short>_<field>` (e.g. `news_date`,
   `links_url`, `cat_sub_id`) — keep this pattern for any new tables/columns.

**Needs client confirmation before Phase 02 applies any renames.**
```

- [ ] **Step 2: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/NAMING_CONVENTIONS_DRAFT.md
git commit -m "docs: draft naming convention agreement for client sign-off"
```

---

### Task 9: Target file structure (draft)

**Files:**
- Create: `docs/audit/TARGET_FILE_STRUCTURE_DRAFT.md`

- [ ] **Step 1: Write the draft**

```markdown
# Target File Structure — Draft

Constraint: stays vanilla PHP, single CSS file, no framework, no build step
(per client's hard requirement) — this is a proposed reorganization of the
existing `files/` tree, not a rewrite.

files/
  index.php                  (unchanged — public entry point)
  action_categories.php, action_search.php   (renamed per NAMING_CONVENTIONS_DRAFT.md)
  config/
    db.php                   (NEW — single shared credential source, replaces the
                               duplicated inline credentials in login_db.php and ata/conn.php)
  login_db.php                (unchanged behavior, but includes config/db.php instead
                               of hardcoding credentials)
  sec_*.php, mod_*.php, sidebar_*.php, content_*.php, table_*.php   (unchanged locations)
  admin/                      (NEW — replaces files/ata/, name change signals "this is
                               the real admin module now, not a prototype")
    auth.php                  (NEW — Phase 03: session-based login gate, required by
                               every file in admin/)
    conn.php                  (includes config/db.php, same as login_db.php)
    index.php                 (dashboard — evolves from ata/index.php)
    news/
      list.php, add.php, edit.php, delete.php   (renamed from flat add.php/edit.php/etc,
                               grouped by entity so a second entity — links, categories —
                               doesn't collide on generic verb names)
    links/                    (NEW in Phase 04 — currently only ata/a_links_check*.php exist)
    categories/               (evolves from ata/a_category.php)

This groups admin pages by entity (Phase 04's stated goal: "never open phpMyAdmin
for routine admin tasks again" implies more entities than just news are coming),
without introducing routing/framework machinery — still plain directories and
plain `include`s.

**Needs client confirmation before Phase 01/03 start moving files.**
```

- [ ] **Step 2: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add docs/audit/TARGET_FILE_STRUCTURE_DRAFT.md
git commit -m "docs: draft target file structure for client sign-off"
```

---

### Task 10: Update CHANGE.md and wrap the phase

**Files:**
- Modify: `CHANGE.md`

- [ ] **Step 1: Append a plain-language entry**

Add to the top of the log (after the header, before the 2026-07-08 entry already there, or as a new dated entry if this lands on a different day):

```markdown
## 2026-07-08

Finished the first planning phase: a full read-through of the existing site's code.
Wrote down exactly what every file does, found one real bug (the admin "add news"
button currently saves to a table nothing else reads, so new posts silently vanish),
flagged three spots where the search and category pages accept text without checking
it first (a security risk), and noted that admin pages currently have no login
protection at all. Also drafted (for your review, not yet applied) a plan for
consistent file naming and a cleaner folder layout for the admin area. Still waiting
on a database export from you before the next step (getting an exact copy of the
database structure) can be finished.
```

- [ ] **Step 2: Commit**

```bash
cd D:\xampp\htdocs\amiga
git add CHANGE.md
git commit -m "docs: log Phase 00 audit completion in CHANGE.md"
```

- [ ] **Step 3: Push**

```bash
git push origin master
```

---

## Self-review

**Spec coverage** — mapped against roadmap.html Phase 00's 10 milestones:

| Milestone | Task |
|---|---|
| Read every PHP file top-to-bottom | Done during planning; captured in Task 1 |
| Map all DB tables: columns, types, nullability, indexes | Partially done (Task 5, code-derived only); full version blocked in Task 6 |
| List all existing functions and what each one does | Task 2 (finding: zero exist) |
| Draw the current file structure | Task 1 |
| Flag dead code, commented blocks, unused variables | Task 3 |
| Note every naming inconsistency | Task 8 |
| Set up local dev environment mirroring production | Task 7 (blocked on Task 6) |
| Take a full DB dump before any changes | Task 6 (blocked, needs your access) |
| Draft naming convention agreement | Task 8 |
| Draft target file structure | Task 9 |

No gaps — every milestone has a task. Two are honestly blocked rather than faked.

**Placeholder scan** — every task has literal file content to write, not "TBD."
The only intentionally-unfilled items are Task 6/7, and those are marked BLOCKED
with a concrete unblocking action, not silently skipped.

**Type/name consistency** — table and file names used in Tasks 1, 4, 5, 8, 9 all
match what was actually grepped in Tasks 1–5 (e.g., `t_news_sub` appears
consistently as the add.php bug target everywhere it's mentioned).

## Risk review (most to least risky)

1. **DB dump handling (Task 6).** Real production data must never hit the public
   GitHub repo. Mitigated by adding the gitignore rule *before* the dump file is
   ever placed in the repo directory (Task 6, Step 3 runs before Step 2's file
   is used).
2. **Credential exposure.** `login_db.php` with a live password is already
   committed to `zeealfa/amiga` (accepted earlier by the user). Task 9 proposes
   a `config/db.php` split for the future, but doesn't fix this now — flagging
   again here so it isn't forgotten once Phase 01/03 actually restructures files.
3. **Local/live drift.** Task 7's local override file (`login_db.local.php`)
   must never get committed or swapped into `index.php` permanently — mitigated
   by gitignoring it and calling out "local testing only" explicitly in the step.
4. **Scope creep.** Every finding task (3, 4, 8, 9) explicitly states "not fixed
   here" to keep Phase 00 pure audit — actual fixes belong to Phases 01–03 per
   the roadmap's own phase boundaries.

---

Plan complete and saved to `docs/superpowers/plans/2026-07-08-phase-00-codebase-audit.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
