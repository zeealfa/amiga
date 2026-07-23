# Page Todo Checklist — Design Spec

## Summary

Add a lightweight, no-login checklist widget attached to individual pages so the
client can leave specific, unambiguous "here's what I want on this page" requests
directly in context, instead of over email/chat where it's disconnected from the
page it refers to. Developer marks items done as they're implemented; the client
sees the check happen on the same page. A plain text field was deliberately
rejected in favor of discrete checklist items to avoid ambiguity about what
exactly is being requested.

This is a **development/test-only** tool, gated by a config constant so it can be
switched off (or the include removed) before the site goes live, and never reaches
real IBrowse/production visitors.

A new item being added also fires a best-effort Telegram notification so the
developer doesn't have to keep checking every page for new requests.

## Out of scope

- Login/auth on the widget itself — anyone viewing the page can add/check/delete
  (matches the "no login, dev-only" requirement).
- Sidebar modules (calendar, crowdfunding, categories, top10, shops, service &
  repair, publications) — these render as shared chrome on every page
  simultaneously, not as distinct pages, so they don't get their own checklist.
- Editing an item's text after it's added — only add / check-done / delete.
- Any test-suite coverage (none exists in this repo).

## Page scoping

One checklist per **PHP file** that represents a distinct page:

- Public: the 9 content views dispatched by `sec_body.php`'s `content_type`
  switch — `content_news.php`, `content_categories.php`, `content_search.php`,
  `content_new_sites.php`, `content_archived_sites.php`,
  `content_dead_sites.php`, `content_top_rated.php`,
  `content_advanced_search.php`, `content_files.php`.
- Admin: every script under `files/admin/` that renders a page, keyed by its own
  filename (e.g. `links.php`, `news.php`, `dashboard.php`).

`page_key` is just that filename (e.g. `content_news.php`, `links.php`) — no
manual key-assignment table to maintain.

## Database

New table `t_page_todos`:

```sql
CREATE TABLE t_page_todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(100) NOT NULL,
    item_text TEXT NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY idx_page_key (page_key)
);
```

- Done items are never deleted or hidden by this flag — they stay in the list,
  rendered struck-through, so the client can see the request was seen and
  completed. Deletion is a separate, explicit action (below).

## Components

### `includes/page_todo.php`

- `render_page_todo(string $page_key): void` — outputs the widget: a small
  old-school HTML table (matching `legacy_colors.php`'s `bg_hex()`/`txt_hex()`
  pattern, no CSS beyond what the rest of the site already uses), listing open
  items first, then done items struck through, followed by a one-field add form.
  Each open item has a "Done" link and a "Remove" link; each done item has just a
  "Remove" link. No JavaScript — plain links/forms only, so the widget renders
  correctly even under IBrowse if the client reviews the test site there.
- `handle_page_todo_action(): void` — called at the top of any file that includes
  `page_todo.php`, before any HTML output. Looks for `$_POST['todo_action']`
  (`add`) or `$_GET['todo_action']` (`done` / `delete`) plus `page_key` /
  `item_text` / `id` as appropriate. Performs the prepared-statement
  INSERT/UPDATE/DELETE, then issues a redirect back to the current URL (POST-
  redirect-GET), so a page refresh never re-submits an add and a done/delete link
  is a plain idempotent GET.
- Both functions no-op immediately (render nothing / process nothing) if
  `PAGE_TODO_ENABLED` is not `true`, so the whole feature disappears from every
  page by flipping one constant.

### `includes/telegram.php`

- `notify_telegram_new_todo(string $page_key, string $item_text): void` — fires a
  POST to `https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/sendMessage` with
  `chat_id = TELEGRAM_CHAT_ID` and a text body like `New request on links.php:
  <item text>`. Uses a short connect/read timeout (e.g. 5s) via a stream context
  so a Telegram outage can't hang the page. Any failure (non-200 response,
  timeout, DNS failure) is caught and passed to `error_log()`, never surfaced to
  the client or the requester.

### `admin/page_todos.php`

- Overview screen, admin-auth gated (`require_admin()`), listing every row from
  `t_page_todos` across the whole site, grouped by `page_key`, open items first
  within each group. Reuses the same done/delete GET links as the inline widget
  (both routes call the same `handle_page_todo_action()`).

## Wiring

- `includes/config.php` — new constants:
  - `PAGE_TODO_ENABLED` (`true` for now; flip to `false` before go-live)
  - `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` (copied from the credentials Zee
    pointed to; unrelated to this project's DB credentials, stored the same way
    other secrets already are in this file)
- `sec_body.php` — each branch of the existing `content_type` if/else gets a
  matching `$page_key` set alongside its `include`; a single
  `render_page_todo($page_key)` call is added after the switch, inside the
  content `<td>`.
- `admin/_footer.php` — single
  `render_page_todo(basename($_SERVER['SCRIPT_NAME']))` call, which covers every
  admin page automatically with no per-file edits.
- `admin/_nav.php` — new "Page Todos" link to `admin/page_todos.php`.

## Data flow

**Add:** client fills the one-field form in the widget → POST `todo_action=add` +
`page_key` (hidden field) + `item_text` → `handle_page_todo_action()` inserts the
row → calls `notify_telegram_new_todo()` best-effort → redirects back to the same
URL → normal GET render shows the new open item.

**Done / Delete:** plain GET link (`?todo_action=done&id=123` or
`?todo_action=delete&id=123`) on either the inline widget or the overview screen →
`handle_page_todo_action()` updates/deletes the row → redirects back.

## Error handling

- DB errors on insert/update/delete simply redirect back without changing
  rendered state — never a fatal error on the page.
- Telegram failures are logged via `error_log()` and otherwise invisible; the
  todo item is still saved regardless of notification outcome.
- Empty/whitespace-only `item_text` submissions are ignored (redirect back with
  no insert), mirroring how other admin forms in this codebase silently ignore
  no-op submissions rather than showing a validation error for something this
  low-stakes.

## Testing/verification approach

No test framework exists in this repo. Verification will be manual:

- `php -l` lint on every new/modified file.
- Add, check off, and delete an item on one public content page
  (`content_news.php`) and confirm the row appears/updates/disappears correctly
  in the DB and on screen.
- Same on one admin page (`admin/links.php`).
- Confirm a Telegram message actually arrives when an item is added (real API
  call, not mocked).
- Confirm `admin/page_todos.php` shows items grouped correctly across both a
  public and an admin page_key.
- Set `PAGE_TODO_ENABLED = false` and confirm the widget disappears from both a
  public page and an admin page with no PHP errors/warnings.
