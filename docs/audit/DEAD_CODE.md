# Dead Code & Orphaned Files

| File | Status | Evidence |
|---|---|---|
| files/content_news-(old).php | **Deleted in Phase 02e** — was orphaned, 145 lines, nothing included it | `grep -rn "content_news-(old)" files` returned nothing before deletion |
| files/sidebar_tabor.php | Orphaned — include is commented out (not deleted, out of scope for Phase 02) | mod_sidebar_chooser.php: `//include 'sidebar_tabor.php'; // not yet dynamically generated` |

## Also found in the database (not code, but same "unused" category)

| Item | Status | Evidence |
|---|---|---|
| Table `t_cat_spec` | Exists in DB, referenced by zero PHP files, holds 1 row of real data | `grep -rn "cat_spec" files` returns nothing; see DB_TABLES.md |

**Correction (2026-07-08):** `t_links.links_cat_6` through `links_cat_10` were
originally listed here as unused. That was wrong — the earlier grep only checked
`table_result_cat.php` directly and missed that it `include`s `table_link.php`,
which echoes `links_cat_6` through `links_cat_10` for every row (`table_link.php:212-216`).
These columns render on both the category-results and search-results public pages
today. They are **not** dead code; only the *filter* logic (`table_result_cat.php:29,138`)
is limited to `links_cat_1`-`5` — the display logic uses all 10. See DB_TABLES.md.

## Other things worth flagging, not orphaned but noise

Every file in `files/` (except a handful) has no trailing newline and most logic
is written as one long single-line block rather than across multiple lines —
makes diffs and code review harder. Example: `sidebar_calendar.php`,
`content_search_proc.php` are each a single physical line (`login_db.php` was
reformatted to two lines in Phase 02a when it was reduced to a delegating
include). Further reformatting beyond what Phase 02 already touched is not in
scope.

## Recommendation

`content_news-(old).php` was deleted in Phase 02e. `sidebar_tabor.php` remains
on disk (out of scope for Phase 02) — confirm with client whether it's still
needed before deleting. Confirm with client whether `t_cat_spec`
and the unused `t_links` category slots are intentionally unfinished or safe to
drop/ignore.
