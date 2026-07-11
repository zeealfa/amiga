# Public Search — All-Columns + Rate Limit — Design

**Goal:** Extend the public site search (`entry_search.php` → `content_search_proc.php`, reached via the `sidebar_search.php` search box) from a links-only search into a search across all public content types, grouped by section, paginated per section, and rate-limited to one new search per 15 seconds per session.

## Background

Today `content_search_proc.php` runs a single LIKE query against `t_links` (`links_name`, `links_url`, `links_author`, `links_desc`) and renders results through `table_result_search.php` → `table_link.php`. It has no rate limiting and only covers links.

## Scope — content types searched

Each type is its own query block, rendered as its own section (only shown if it has matches):

| Section | Table | Columns searched (LIKE) | Visibility filter |
|---|---|---|---|
| Links | `t_links` | `links_name`, `links_url`, `links_author`, `links_desc` | `links_deleted_at IS NULL` (existing) |
| News | `t_news` JOIN `t_users` ON `t_news.submitted_by = t_users.id` | `news_story`, `username` | `news_active = 1 AND news_deleted_at IS NULL` |
| Calendar | `t_cal` | `cal_name`, `cal_url`, `cal_location` | none (no active/deleted column on this table) |
| Crowdfunding | `t_cfund` | `cfund_name`, `cfund_url` | `cfund_active = 1` (matches `get_active_crowdfunding()` visibility) |
| Online Publications | `t_mags_online` | `online_name`, `online_url` | none |
| Print Publications | `t_mags_print` | `print_name`, `print_url` | none |
| Repair/Service | `t_repair` | `repair_name`, `repair_url`, `repair_country` | none |
| Shops/Vendors | `t_vendor` | `vendor_name`, `vendor_url` | none |
| Top 10 | `t_top10` | `top10_name`, `top10_url` | none |

Categories are explicitly **out of scope** — the categories sidebar is a navigation taxonomy, not a list of individually visitable content entries.

All 8 queries use `mysqli_prepare` + `mysqli_stmt_bind_param` (parameterized, matching the existing pattern in this file — no string interpolation).

## Existing quirks preserved

`content_search_proc.php`'s current pre-checks run first, unchanged, before anything below:

- `strlen($search) < 3` → "To short... Try again" message, no queries run.
- Exact-match `"amiga"`/`"amig"`/`"ami"` → search term blanked, "To vague... Try and narrow the search" message, no queries run.

Neither of these counts as a "search" for rate-limiting purposes (see below) — only a term that actually reaches the query stage consumes the 15-second window.

## Rate limiting

- Key: PHP session (`$_SESSION['last_search_time']`), consistent with how the rest of the codebase tracks per-visitor state (no new storage).
- Only checked when `$_SERVER['REQUEST_METHOD'] === 'POST'` — i.e. only when the visitor submits the search box with a new term. Pagination link clicks (GET requests, see below) never check or reset this timer.
- On a POST: if `time() - ($_SESSION['last_search_time'] ?? 0) < 15`, skip all query blocks and render `"Please wait N more seconds before searching again"` (N = `15 - (time() - last_search_time)`), reusing the submitted term as the page heading. Otherwise run the searches and set `$_SESSION['last_search_time'] = time()`.

## Pagination

- New constant `SEARCH_RESULTS_PER_PAGE = 10` in `includes/config.php`.
- Each of the 8 sections paginates independently via its own query-string param: `page_links`, `page_news`, `page_cal`, `page_cfund`, `page_online`, `page_print`, `page_repair`, `page_vendor`, `page_top10` (default 1).
- Each section's `COUNT(*)` + `LIMIT ?, ?` pair mirrors the existing `admin/news.php`/`admin/links.php` pattern, and reuses `render_pagination_menu()` for the Prev/Next/page-number links, styled to match the public site's table markup (not the admin skin).
- Pagination links are plain `<a href="entry_search.php?search=...&page_links=2">` GET links — no JS/AJAX, per the IBrowse constraint.

## GET/POST handling

- `entry_search.php` currently only reads `$_POST['search']`. It's updated to read `$_POST['search'] ?? $_GET['search'] ?? ''`, so pagination GET links (which carry `search` in the query string) continue showing the same result set without resubmitting the form.
- The rate-limit check (above) is gated on `REQUEST_METHOD === 'POST'`, so this GET fallback path never triggers or resets it.

## Out of scope

- No change to the categories sidebar/tree.
- No per-section result cap beyond standard pagination (i.e., no truncation — all matches are reachable via paging).
- No JS-based live countdown for the rate-limit wait message — it's static text from the render that already happened; a follow-up submit after the wait recalculates fresh.
- No change to `table_link.php`/`table_result_search.php`'s existing links-only rendering — the Links section keeps using it as-is; the other 7 sections get their own minimal row markup matching their existing admin-list column sets.
