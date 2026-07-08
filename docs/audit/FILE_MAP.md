# File Map — amigasource.com

Traced by reading every file in `files/` on 2026-07-08.

## Public site (entry: files/index.php)

| File | Role |
|---|---|
| index.php | Entry point. Starts session, sets content_type=news, includes login_db.php + page_builder.php |
| entry_categories.php | Alt entry point: sets content_type=categories, re-runs the same page_builder chain (renamed from action_categories.php per NAMING_CONVENTIONS_DRAFT.md) |
| entry_search.php | Alt entry point: sets content_type=search, re-runs the same page_builder chain (renamed from action_search.php per NAMING_CONVENTIONS_DRAFT.md) |
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
| sidebar_tabor.php | Exists on disk, include is commented out in mod_sidebar_chooser.php — dead (see DEAD_CODE.md) |
| content_news.php | Default main content: reads `t_links` (counts) and `t_news` (listing) |
| content_news-(old).php | Orphaned duplicate of content_news.php — nothing includes it (see DEAD_CODE.md) |
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
| conn.php | Its own separate mysqli connection (duplicate credentials from login_db.php — see FINDINGS.md) |
| index.php | Static dashboard; most links are `---` placeholders (only News, Links, Categories are wired) |
| a_news.php | Lists `t_news`, links to edit/delete, form posts to add.php |
| add.php | Inserts into `t_news_sub` — a table that does not exist (confirmed bug, see FINDINGS.md) |
| edit.php, update.php, delete.php | CRUD against `t_news` (id-keyed) |
| a_category.php | Read-only view of `t_cat_main` / `t_cat_sub` |
| a_links_check.php, a_links_check_02.php | Read-only view of `t_links` |

## External system referenced but not in this repo

`table_link.php` links to `http://testamigasource.com/ata/maestrotest/t_links.php` — a
third-party admin tool ("Maestro") the client currently uses for link management. This is
what Phase 03 ("Replace Maestro") in roadmap.html replaces.
