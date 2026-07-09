# Links "Check All" (live up/down status) — Design

**Date:** 2026-07-09
**Status:** Approved for planning
**Related work:** Follows the link quick-actions feature (`2026-07-09-link-quick-actions-design.md`), and reuses the existing single-URL checker built for `files/admin/link_form.php`.

## Problem

`files/admin/links.php`'s Status column shows the DB-stored flags
(`active`, `dead`, `verified`, `recommended`, `DELETED`) but never confirms
whether a link is actually reachable right now. An admin currently has to
open each link individually (or use the per-URL check already built into
`link_form.php`) to find out if it's really live. There's no way to sweep
the visible list and see, at a glance, which links are currently broken.

A working single-URL checker already exists:
`files/admin/link_url_check.php` is a JSON endpoint that HEAD-probes (with
a GET fallback on ambiguous status codes) a given URL via curl and returns
`{"status": "up"}` or `{"status": "down"}`. `link_form.php` already calls
it via `fetch()` with an `XMLHttpRequest` fallback, rendering a green
`✓` or red `✗` next to the URL field. This feature extends that same
proven pattern from "one URL on the edit form" to "every URL on the
current links-list page."

## Scope

- A **"Check All"** button added to `links.php`'s existing filter-bar row
  (alongside Search/Status/Category/Show deleted/Apply/+Add Link).
- Clicking it checks every **non-deleted** link currently rendered on the
  page (i.e. the current page of results, respecting whatever
  search/status/category/show-deleted filters are already applied — no
  fetching of other pages).
- Soft-deleted rows (`links_deleted_at !== null`, which already only show
  "Restore") are excluded from the sweep entirely.
- Requests are throttled to **4 concurrent** checks at a time via a
  client-side worker-pool queue, to avoid firing up to 25 simultaneous
  outbound curl calls from shared GoDaddy hosting.
- Each row shows its own spinner-to-result transition as its individual
  check resolves — results appear incrementally, not all at once at the
  end.
- Results are **transient/display-only**. Nothing is written to the
  database. A "down" result does not touch `links_dead` — marking a link
  dead remains a deliberate admin action via the existing (currently
  hidden) "Mark Dead" quick-action button.
- The indicator is placed **in the URL column**, immediately after the
  URL text — not in the Status column, which continues to show only the
  DB-stored flags unchanged.

**Out of scope:** checking links across all pages/filters in one sweep
(flagged as a possible future backlog item, not part of this work),
auto-updating `links_dead` from check results, checking on page load
without a button click, any change to `link_url_check.php` itself.

## Mechanism

### `link_url_check.php`

Unchanged. Already handles auth (session-based admin check), CSRF-ish
protection (requires the `X-Requested-With: XMLHttpRequest` header, which
a cross-site request can't set), URL validation, and the HEAD/GET probe
logic. This feature only adds a new caller.

### `links.php` changes

**Filter-bar button** — added as one more `<td>` in the existing
single-row filter table (see the `links.php` "no wrapped line" fix from
earlier this session):

```php
<td style="white-space:nowrap;"><button type="button" id="check_all_links_btn">Check All</button></td>
```

**Row wiring** — each non-deleted row's `<tr>` gains `data-link-id`, and
the URL cell gains an empty status span:

```php
<tr data-link-id="<?php echo (int) $link['id']; ?>">
    ...
    <td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span><span class="txt-1" data-url-status></span></td>
```

Deleted rows keep their existing markup unchanged — no `data-link-id`, no
status span — so they're structurally excluded from the sweep, not just
filtered out at runtime.

**Client-side script** — a new inline `<script>` block in `links.php`
(same convention as the existing inline script in `link_form.php`: no
external JS file, no build step, no framework):

- On `DOMContentLoaded`, wire a click handler on `#check_all_links_btn`.
- On click: disable the button and set its label to "Checking...".
  Collect all `tr[data-link-id]` elements into a queue. Start 4 "workers"
  (simple recursive function: pop the next row off the queue, run its
  check, and on completion immediately pop and run the next one, until
  the queue is empty). Track outstanding count; once every row has
  settled, re-enable the button and restore its "Check All" label.
- Per-row check: set that row's `[data-url-status]` span to a `"..."`
  placeholder (matching `link_form.php`'s existing loading-state text),
  then call `link_url_check.php?url=<url>` using the same
  `fetch()`-with-`XMLHttpRequest`-fallback function already proven in
  `link_form.php` (ported into this file, not shared via an external
  file, consistent with this project having no shared JS module system).
  On `"up"`, set the span to `✓` (`#008000`). On `"down"` or any error
  (network failure, non-2xx, bad JSON), set it to `✗` (`#c70000`) — same
  "unknown treated as down" behavior `link_form.php`'s `.catch()` already
  uses.

## Data / schema

No schema changes. No new persisted data — every result is discarded
when the page is reloaded or re-filtered.

## Testing plan

- `php -l` on `links.php`.
- Authenticated curl fetch of `links.php`, checking the rendered HTML for:
  - The `Check All` button present in the filter bar.
  - `data-link-id` attributes present on non-deleted rows, absent on
    deleted rows.
  - `data-url-status` spans present on non-deleted rows.
- **Known verification gap:** the actual spinner→result behavior only
  happens through real browser JS execution and a real click, which
  can't be exercised by an automated curl-only check. This will be
  called out explicitly rather than asserted as "working" — a manual
  browser click-through (open `links.php`, click "Check All", confirm
  spinners appear and flip to ✓/✗, confirm the button re-enables when
  done) is needed to actually confirm the feature works end-to-end.
