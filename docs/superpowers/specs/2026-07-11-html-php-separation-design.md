# HTML/PHP Separation (Phase 02 remaining milestone) — Design

**Roadmap milestone:** "Separate HTML output from PHP logic on every existing page" (`roadmap.html`, Phase 02, currently unchecked).

## Goal

Move DB query logic (`mysqli_query`/`mysqli_prepare` calls currently interleaved with HTML `<?php ?>` blocks) out of 9 public-facing page files and into named functions in `includes/functions.php`, so each page file does "fetch data, then render markup" instead of mixing SQL and HTML output line-by-line. This is a pure refactor — no behavior change, no new features, no visual change (verified below).

## Non-goals

- No change to rendered HTML output (byte-identical before/after, verified via `curl` diff per file).
- No change to database schema, queries' actual SQL, or query results.
- No extraction of date-formatting/branching logic (e.g. `sidebar_calendar_sub.php`'s multi-day-event format selection) into functions — only the DB fetch itself is extracted. Keeping formatting logic in the page file avoids scope creep beyond what the roadmap milestone asks for.
- No IBrowse-specific testing needed — IBrowse only ever sees the final rendered HTML, which byte-identical diffing already proves is unchanged. (User confirmed 2026-07-11: "curl diff is fine, no need for AmiKit testing.")
- No changes to `sidebar_tabor.php` or any file without an embedded `mysqli_query`/`mysqli_prepare` call — out of scope, since there's no logic/HTML mixing to separate.
- **Correction (post-approval):** the original scope-confirmation message to the user undercounted this list as "8 files" and omitted `sidebar_top10_sub.php`, even though it matched the same `mysqli_query`-in-`do/while` pattern and was in the original grep match set. The user's approval ("should be all") is treated as covering it; it has been added to scope below and in the implementation plan.

## In-scope files and their new functions

All new functions are added to `files/includes/functions.php`, following the existing naming convention (verb_noun, e.g. `render_pagination_menu`, `get_category_tree`).

| File | Current query | New function | Returns |
|---|---|---|---|
| `content_news.php` | 4 queries: total link count, verified count, new-link count, paginated news rows (prepared stmt) | `get_link_stats($myConnection)` → array; `get_news_page($myConnection, $offset, $limit)` → array of rows; `get_news_total_count($myConnection)` → int | see signatures below |
| `content_categories.php` | conditional first-category lookup + category-by-id (prepared stmt) | `get_default_category_id($myConnection)` → int\|null; `get_category_rows($myConnection, $cat_id)` → array of rows | see signatures below |
| `sidebar_calendar_sub.php` | `SELECT * FROM t_cal ORDER BY cal_date_start ASC` | `get_calendar_events($myConnection)` → array of rows | array |
| `sidebar_crowdfunding_sub.php` | `SELECT * FROM t_cfund WHERE cfund_active=1 ORDER BY cfund_date_end` | `get_active_crowdfunding($myConnection)` → array of rows | array |
| `sidebar_publications_sub_online.php` | `SELECT * FROM t_mags_online ORDER BY online_name` | `get_online_publications($myConnection)` → array of rows | array |
| `sidebar_publications_sub_print.php` | `SELECT * FROM t_mags_print ORDER BY print_name` | `get_print_publications($myConnection)` → array of rows | array |
| `sidebar_service_repair_sub.php` | `SELECT * FROM t_repair ORDER BY repair_name` | `get_repair_vendors($myConnection)` → array of rows | array |
| `sidebar_shops_vendors_sub.php` | `SELECT * FROM t_vendor ORDER BY vendor_name` | `get_shop_vendors($myConnection)` → array of rows | array |
| `sidebar_top10_sub.php` | `SELECT * FROM t_top10 ORDER BY top10_order` | `get_top10_entries($myConnection)` → array of rows | array |

Function bodies do only: build SQL, execute (`mysqli_query` or `mysqli_prepare`/bind/execute matching what the file already uses), fetch all rows into a plain PHP array via `mysqli_fetch_all($result, MYSQLI_ASSOC)`, return the array (or scalar for count-style queries). No `echo`, no HTML, no date formatting inside the function.

### Detailed signatures

```php
// content_news.php
function get_link_stats($myConnection): array
// returns ['total' => int, 'verified' => int, 'new' => int]
// (mirrors the 3 existing COUNT(*) queries; 'left' is still computed
// in the page as total - verified, same as today)

function get_news_total_count($myConnection): int
// SELECT COUNT(*) FROM t_news WHERE news_active='1' AND news_deleted_at IS NULL

function get_news_page($myConnection, int $offset, int $limit): array
// prepared stmt, same SQL as today, returns array of assoc rows
```

```php
// content_categories.php
function get_default_category_id($myConnection): ?int
// SELECT id FROM t_categories ORDER BY id ASC LIMIT 1 — returns null if no rows

function get_category_rows($myConnection, int $cat_id): array
// prepared stmt "SELECT * FROM t_categories WHERE id=?", returns array of assoc rows
// (page keeps its existing do/while loop, now iterating this array instead of
// the mysqli result handle — the loop count-driven-by-fetch behavior is preserved
// since t_categories.id is a primary key and this always returns 0 or 1 row today,
// same as the current code path)
```

```php
// sidebar_*_sub.php (7 files, same shape each)
function get_calendar_events($myConnection): array
function get_active_crowdfunding($myConnection): array
function get_online_publications($myConnection): array
function get_print_publications($myConnection): array
function get_repair_vendors($myConnection): array
function get_shop_vendors($myConnection): array
function get_top10_entries($myConnection): array
// each: mysqli_query() the existing unmodified SQL string, then
// mysqli_fetch_all($result, MYSQLI_ASSOC), return the array
```

## Page-file changes

Each page file:
1. Gets `require_once __DIR__ . '/includes/functions.php';` added near the top if not already present (only `content_news.php` currently has this line; the other 7 files need it added).
2. Replaces its `mysqli_query`/`mysqli_prepare`/fetch block with a single call to the new function, storing the result in the same-shaped variable the rest of the file already expects (e.g. `$rows = get_calendar_events($myConnection);`).
3. Replaces `do { ... } while ($line1 = mysqli_fetch_array(...))` loops with `foreach ($rows as $line1) { ... }` — this changes the loop construct but not its behavior (same iteration order, since MySQL result order is unchanged and `mysqli_fetch_all` preserves row order). The `do/while` → `foreach` swap is necessary because the page no longer holds a live mysqli result handle to fetch from.
4. All existing HTML/echo/formatting logic below the query block is otherwise untouched, character-for-character.

## Risk review

**Highest risk: the `do/while` → `foreach` loop-construct change altering iteration behavior in an edge case.** The original `do/while` pattern in these files calls `mysqli_fetch_array()` once before the loop body, meaning on a genuinely empty result set (`$line1 = false`), the loop body still runs once with `$line1 = false`, which would emit PHP warnings/notices on array access (e.g. `$line1['top10_name']` on `false`). A `foreach` over an empty array skips the body entirely — **this is a behavior difference on empty tables.**
- **Mitigation:** Before refactoring each file, check (via a quick DB query) whether its source table can realistically be empty. If a table already has rows today (all 7 do, per the live DB), the curl-diff step will not surface this edge case — flagging it here so it's a known, accepted risk rather than a silent gap. If any table is empty at diff-time, that file's diff will not be byte-identical for the empty-vs-one-broken-iteration case, which will surface immediately as a failed diff and require a closer look (e.g. wrapping the foreach body's `$line1` array access defensively) before proceeding — not deferred silently.

**Second risk: byte-identical diffing requires stable inputs.** `content_news.php` and `content_categories.php` depend on `$_GET['page_no']` / `$_GET['cat_id']` and on live DB row counts that could change between the "before" and "after" curl calls (e.g. someone editing a link in the admin panel mid-refactor). **Mitigation:** capture "before" and "after" curl output back-to-back for each file (immediately before and after that file's own edit, not batched across all 9 files), and use a fixed query string (e.g. `?page_no=1`, `?cat_id=<known-id>`) so the same code path is exercised both times.

**Third risk: functions.php naming collisions.** Two publications functions (`get_online_publications`/`get_print_publications`) and others must not collide with existing function names. **Mitigation:** cross-checked against the current 5 functions in `includes/functions.php` (`render_pagination_menu`, `find_similar_link_urls`, `get_category_tree`, `get_category_descendant_ids`, `render_cat_checkboxes`) — no name collisions in this plan.

## Verification (per file, in order — no batching)

1. Capture "before": `curl` the file's live output (or invoke via PHP CLI with the same `$_GET`/session context) with a fixed query string, save to a temp file.
2. Apply the refactor to that one file (+ add its function(s) to `includes/functions.php`).
3. `php -l` on both the page file and `includes/functions.php`.
4. Capture "after": same `curl` call, save to a second temp file.
5. `diff` before/after — must be empty (byte-identical). If not empty, stop and fix before moving to the next file.
6. Re-run the tag-balance grep count (`<table`/`</table>`, `<tr`/`</tr>`, `<td`/`</td>`) on the changed file and confirm it matches the pre-refactor baseline captured during design (all 9 files currently balanced — see below).
7. Commit that one file's change (+ its function additions) before starting the next file.

**Pre-refactor tag-balance baseline (already captured, all balanced):**
```
content_categories.php: table 5/5 tr 5/5 td 5/5
content_news.php: table 7/7 tr 6/6 td 6/6
sidebar_calendar_sub.php: table 0/0 tr 0/0 td 0/0
sidebar_crowdfunding_sub.php: table 0/0 tr 0/0 td 0/0
sidebar_publications_sub_online.php: table 0/0 tr 0/0 td 0/0
sidebar_publications_sub_print.php: table 0/0 tr 0/0 td 0/0
sidebar_service_repair_sub.php: table 0/0 tr 0/0 td 0/0
sidebar_shops_vendors_sub.php: table 0/0 tr 0/0 td 0/0
sidebar_top10_sub.php: table 0/0 tr 0/0 td 0/0
```

## Roadmap update

Once all 9 files are refactored and verified, mark the "Separate HTML output from PHP logic on every existing page" milestone in `roadmap.html` (Phase 02) as done — this closes out Phase 02 entirely (all other Phase 02 items are already done or N/A), so Phase 02's badge should also move from PARTIAL to DONE at that point.
