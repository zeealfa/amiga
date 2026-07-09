# Link URL Status Check — Design

## Goal

On the admin "Add/Edit Link" form (`files/admin/link_form.php`), show a live status
indicator (green check / red cross) next to the URL field, telling the admin
whether the URL currently in the field is reachable — without saving anything
or leaving the page.

## Trigger rules

- **Edit mode, page load:** the URL field is pre-filled with the link's saved
  URL. A check fires automatically on load.
- **Any mode, URL field edited:** every time the URL input becomes dirty
  (an `input` event fires with a non-empty, changed value), a check re-fires
  after a 600ms debounce, so it doesn't fire on every keystroke.
- **Add mode, page load:** the URL field starts empty, so no check fires
  until the admin types something (covered by the rule above).
- **Empty or unchanged-to-empty field:** no request is fired; any existing
  status indicator is cleared.

## Broken/up classification

- HTTP response with a final status code in the 2xx or 3xx range (after
  following redirects) → **up**.
- Any of: 4xx/5xx status, connection refused, DNS failure, or a request that
  exceeds a 5-second timeout → **down**.

## Components

### 1. `files/admin/link_url_check.php` (new)

A small, session-gated (`require_admin()`, same pattern as every other file
in `files/admin/`) JSON endpoint.

- Input: `?url=` query param.
- Validates the URL with `filter_var(..., FILTER_VALIDATE_URL)` and requires
  an `http`/`https` scheme (same validation already used in
  `link_form.php`'s own URL field check) — malformed input returns
  `{"status":"invalid"}` immediately, no HTTP request attempted.
- Performs a `curl` request:
  - Method: `HEAD` first. Some servers reject `HEAD` (405/501/timeout on
    `HEAD` specifically); in that case, retry once with `GET`.
  - `CURLOPT_FOLLOWLOCATION` = true, `CURLOPT_MAXREDIRS` = 5.
  - `CURLOPT_TIMEOUT` = 5 (seconds), `CURLOPT_CONNECTTIMEOUT` = 5.
  - `CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST` left at curl's secure defaults —
    a link with a broken/self-signed cert is reasonably classified as
    "down" for this purpose, since a real visitor's browser would also
    reject it.
  - `CURLOPT_NOBODY` = true for the `HEAD` attempt only.
- Output: `Content-Type: application/json`, body `{"status":"up"}`,
  `{"status":"down"}`, or `{"status":"invalid"}`.
- No database access, no writes anywhere — purely a network probe.

### 2. `files/admin/link_form.php` (modified)

- Add a `<span id="url_status"></span>` immediately after the existing URL
  `<input name="links_url">` element.
- Add a small vanilla-JS block in the existing `<script>` tag (same file
  already has `enforceCategoryLimit()` in plain DOM JS — this follows that
  existing style, no new library):
  - `checkUrlStatus()`: reads the URL field's current value. If empty,
    clears `#url_status` and returns. Otherwise sets `#url_status` to a
    neutral "checking…" state, then calls
    `fetch('link_url_check.php?url=' + encodeURIComponent(value))`
    (or `XMLHttpRequest` if `fetch` isn't available, for older-browser
    safety — feature-detect and fall back), and on response sets
    `#url_status` text/color based on `status`:
    - `up` → green "✓" (`color:#008000`)
    - `down` → red "✗" (`color:#c70000`)
    - `invalid` → cleared (no icon; the form's own validation message on
      submit already covers malformed URLs)
  - Debounce: on the URL field's `input` event, clear any pending timer and
    set a new one for 600ms before calling `checkUrlStatus()`.
  - On `DOMContentLoaded`, if the URL field's initial value is non-empty
    (true for edit mode, and also true if a previous submit round-tripped
    back with `errors` and a non-empty value), call `checkUrlStatus()`
    once immediately (no debounce, since there's no keystroke to wait out).
  - A monotonically increasing request counter (or simply storing the
    request's own URL value and comparing it to the field's value when the
    response arrives) guards against a slow earlier response overwriting a
    newer one, in case the admin edits the field again before the first
    check returns.

## Explicitly out of scope

- No database write of any kind — the existing "Dead" checkbox is untouched
  and must still be set/cleared manually by the admin.
- No check on `links.php` (the list view) — this is scoped to the
  add/edit form only, per the original request.
- No retry/backoff beyond curl's own timeout — a single attempt (with the
  one HEAD→GET fallback) per triggered check.
- No change to `link_preview.php` or the save path — this is purely a
  client-facing convenience on the form page.

## Testing plan

- `php -l` both changed/new files.
- Manual verification via the existing session-file-injection curl
  technique (no browser available in this environment):
  - Call `link_url_check.php?url=<a known-up URL>` directly, confirm
    `{"status":"up"}`.
  - Call it with a URL that returns 404, confirm `{"status":"down"}`.
  - Call it with an unreachable host, confirm `{"status":"down"}` and that
    the response returns within ~5-6 seconds (timeout is respected, not
    hanging).
  - Call it with a malformed value (e.g. `not a url`), confirm
    `{"status":"invalid"}`.
  - Confirm `link_url_check.php` returns 302/redirect-to-login (via
    `require_admin()`) when called without a valid admin session.
- Since real in-browser JS execution (debounce timing, DOMContentLoaded
  firing, fetch/XHR fallback) can't be verified by curl, this will be
  called out explicitly as unverified-in-browser after implementation,
  per the project's evidence-before-assertion rule — code will be
  reviewed by inspection instead of asserted as "working in the browser."
