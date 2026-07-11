# Advanced Search Form — Design

**Goal:** Turn the sidebar's dormant "Advanced Search (coming soon)" text into a real link to a dedicated advanced search page, which searches the same 9 content types as the public quick search (`entry_search.php` → `content_search_proc.php`) but adds a section checklist filter and a date-added range filter.

## Background

`files/sidebar_search.php` line 26 currently renders plain, unlinked text: `Advanced Search (coming soon)`. The quick search feature (built in a prior spec, `2026-07-11-public-search-all-columns-design.md`) already searches Links, News, Calendar, Crowdfunding, Online Publications, Print Publications, Repair/Service, Shops & Vendors, and Top 10 — grouped by section, paginated per section, rate-limited to one new search per 15 seconds per session. This feature reuses that same backend infrastructure (`fetch_paginated_search_results()`, `render_pagination_menu()`, `search_seconds_until_next_allowed()`, the row-template partials) rather than duplicating it.

## Routing

Following the site's existing `$_SESSION['content_type']` include-chain pattern (no router/front controller):

- **`files/entry_advanced_search.php`** (new) — entry point. Sets `$_SESSION['content_type'] = 'advanced_search'`, includes `login_db.php` then `page_builder.php`. Mirrors `entry_search.php`'s structure.
- **`files/sec_body.php`** (modified) — gains a new branch in its `content_type` if/else chain: `else if ($_SESSION["content_type"]=='advanced_search'){ include 'content_advanced_search.php'; }`.
- **`files/content_advanced_search.php`** (new) — thin wrapper, mirrors `content_search.php`: `<?php $_SESSION["content_type"]='advanced_search'; include("content_advanced_search_proc.php"); ?>`.
- **`files/content_advanced_search_proc.php`** (new) — the real logic: renders the filter form, and if a valid search was submitted, runs the filtered search and renders results below the form on the same page (not a separate results page).
- **`files/sidebar_search.php`** (modified) — line 26's `Advanced Search (coming soon)` becomes `<a href="/entry_advanced_search.php">Advanced Search</a>`.

## Form fields

Rendered by `content_advanced_search_proc.php`, submitting to itself (`entry_advanced_search.php`) via POST:

- **Search term** — text input, `name="search"`. Required, minimum 3 characters (same rule as quick search). The exact-match `"amiga"`/`"amig"`/`"ami"` "too vague" short-circuit from quick search is preserved here too.
- **Section filters** — 9 checkboxes, `name="sections[]"`, one per content type (Links, News, Calendar, Crowdfunding, Online Publications, Print Publications, Repair/Service, Shops & Vendors, Top 10). **None checked = search all 9 sections** (same as today's unfiltered behavior) — this is not an error state.
- **Date range** — two plain text inputs, `name="date_from"` and `name="date_to"`, expected format `YYYY-MM-DD`. Both optional. No HTML5 `<input type="date">` (IBrowse has no support for it) — plain text, validated server-side.

## Filtering logic

Reuses `fetch_paginated_search_results()` and the existing per-section query configs from `content_search_proc.php` (the `$simple_sections` array plus the Links and News blocks) completely unchanged — no modification to that shared helper function.

- **Section selection:** if `sections[]` is non-empty, only the checked sections' query blocks run; unchecked sections are skipped entirely (same code path as "zero matches," just pre-filtered rather than queried). If `sections[]` is empty, all 9 sections run, identical to quick search today.
- **Date range:** every one of the 9 tables has a `created_at` timestamp column (confirmed via live `SHOW COLUMNS`, 2026-07-11) — this feature filters on `created_at` uniformly across all sections, not each table's own semantically different date field (e.g. not `cal_date_start`, which is an event date rather than an added date). When a date range is present, each active section's `WHERE` clause gets an additional `DATE(created_at) BETWEEN ? AND ?` condition, with the two bound values appended to that section's `$params`/`$types`.

## Rate limiting

Identical mechanism to quick search, and the **same session key** — `$_SESSION['last_search_time']` and `search_seconds_until_next_allowed()` (15-second window). This is shared across both the sidebar quick-search box and the advanced search form, so a visitor cannot bypass the limit by alternating between the two entry points. Only checked on POST; pagination link clicks (GET) never check or reset it — identical to quick search's existing rule.

## Pagination

Same per-section pagination as quick search (`SEARCH_RESULTS_PER_PAGE = 10`, `render_pagination_menu()`, one page param per section: `page_links`, `page_news`, `page_cal`, etc.), but pagination links must now carry the full filter state, not just the search term:

```
entry_advanced_search.php?search=...&sections=links,news&date_from=2026-01-01&date_to=2026-06-30&page_links=2
```

`sections` is encoded as a single comma-joined query param (parsed back into an array on GET) rather than repeated `sections[]=` params, to keep `render_pagination_menu()`'s existing `$url_prefix` string-concatenation approach working unchanged. `content_advanced_search_proc.php` reads `$_POST['sections'] ?? explode(',', $_GET['sections'] ?? '')` (and similarly for `search`/`date_from`/`date_to`) so GET pagination requests redisplay the same filtered result set without resubmitting the form — mirroring quick search's existing GET/POST split in `entry_search.php`.

## Validation and error handling

Checked in this order; the first failure stops the search (no query runs) and redisplays the form with the visitor's submitted values pre-filled so they don't have to retype everything:

1. **Search term too short** (`< 3` chars, or blank) → `"To short... Try again"` (same message/wording as quick search).
2. **Search term is an exact vague match** (`"amiga"`/`"amig"`/`"ami"`) → `"To vague... Try and narrow the search"` (same as quick search).
3. **Malformed date** — either field doesn't match `YYYY-MM-DD` (validated via `DateTime::createFromFormat('Y-m-d', ...)` and checking round-trip equality, not just a regex), or `date_from` is later than `date_to` → a new validation error, e.g. `"Please enter a valid date range."`
4. **Rate limit** — same "Please wait N more seconds..." message as quick search, checked only after the above validations pass (so a mistyped date doesn't consume the 15-second window — consistent with quick search's existing rule that only a genuine, valid search attempt counts).

If all validations pass and the visitor isn't rate-limited, the filtered search runs and results render below the form, grouped by section exactly like quick search (same "Nothing found. Please try again!" fallback if zero sections have matches).

## Reused, unmodified from the quick search feature

- `fetch_paginated_search_results()`, `render_pagination_menu()`, `search_seconds_until_next_allowed()` (`files/includes/functions.php`)
- `SEARCH_RESULTS_PER_PAGE` constant (`files/includes/config.php`)
- `table_result_search.php` / `table_link.php` (Links rendering), `table_search_news_row.php` (News rendering), `table_search_simple_row.php` (the other 7 sections' rendering)
- The 9-table/column scope and visibility filters (`links_deleted_at IS NULL`, `news_active = 1 AND news_deleted_at IS NULL`, `cfund_active = 1`, etc.) defined in the original quick-search spec

## Out of scope

- No changes to the quick search sidebar box or `entry_search.php`/`content_search_proc.php` beyond the one-line link change in `sidebar_search.php`.
- No new content types beyond the existing 9 — Categories remains out of scope, same as quick search.
- No date filtering on any column other than `created_at` (e.g. not calendar event dates, not crowdfunding campaign dates).
- No saved/bookmarked filter presets — filter state only persists via the URL query string during pagination, not across separate visits.
