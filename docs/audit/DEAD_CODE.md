# Dead Code & Orphaned Files

| File | Status | Evidence |
|---|---|---|
| files/content_news-(old).php | Orphaned — 145 lines, nothing includes it | `grep -rn "content_news-(old)" files` returns nothing |
| files/sidebar_tabor.php | Orphaned — include is commented out | mod_sidebar_chooser.php: `//include 'sidebar_tabor.php'; // not yet dynamically generated` |

## Also found in the database (not code, but same "unused" category)

| Item | Status | Evidence |
|---|---|---|
| Table `t_cat_spec` | Exists in DB, referenced by zero PHP files | `grep -rn "cat_spec" files` returns nothing; see DB_TABLES.md |
| `t_links.links_cat_6` through `links_cat_10` | Columns exist, never read/written by any current query | Only `links_cat_1`..`links_cat_5` appear in table_result_cat.php |

## Other things worth flagging, not orphaned but noise

Every file in `files/` (except a handful) has no trailing newline and most logic
is written as one long single-line block rather than across multiple lines —
makes diffs and code review harder. Example: `login_db.php`, `sidebar_calendar.php`,
`content_search_proc.php` are each a single physical line. Reformatting is in
scope for Phase 02, not this audit.

## Recommendation

Delete `content_news-(old).php` and `sidebar_tabor.php` in Phase 02 once the client
confirms neither is needed for reference. Confirm with client whether `t_cat_spec`
and the unused `t_links` category slots are intentionally unfinished or safe to
drop/ignore. Nothing deleted during Phase 00 — audit only.
