# File Repository — Design Spec

## Summary

Add an admin-managed file repository so visitors can download files hosted on the
site. Files are uploaded internally (actual browser multipart upload into a
`storage/` folder — no external URL option). Each file has a title and a required
short description, both of which are searchable via the existing quick-search and
advanced-search infrastructure. Downloads are tracked with a public counter. The
public listing page is flat (no categories), styled like `content_news.php`. A new
"FILES" link is added to the sidebar's QUICK LINKS block.

Creation/editing is **admin-only** — there is no contributor submission workflow for
files (unlike links/news, which use `t_submissions`).

## Out of scope

- Contributor/user submission of files (`t_submissions` workflow) — admin-only.
- External URL-hosted files — removed per explicit user correction; all files must
  be uploaded and stored internally.
- Categorization of files — flat list only.

## Database

New table `t_files`:

```sql
CREATE TABLE t_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description VARCHAR(500) NOT NULL,
  stored_filename VARCHAR(64) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_size INT NOT NULL,
  file_ext VARCHAR(10) NOT NULL,
  download_count INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
);
```

- `stored_filename` is the server-generated random on-disk name
  (`bin2hex(random_bytes(16)) . '.' . $ext`) — never derived from user input. This
  closes path-traversal and double-extension tricks.
- `original_filename` is the name shown to users at download time
  (`Content-Disposition` header), preserved from the uploaded file's name.
- `active` mirrors `t_news.news_active` — an inactive file is hidden from the
  public listing, search results, and direct download (id lookup returns 404),
  but is not deleted from disk/DB. This lets admins unpublish without losing the
  upload or its download-count history.

## Storage & security

- `files/storage/` holds all uploaded file bodies under their random
  `stored_filename`.
- `files/storage/.htaccess` → `Deny from all`. The folder is never directly
  browsable or linkable; all downloads must go through `file_download.php`.
- Extension whitelist (allow-list, not block-list). Proposed initial list:
  `zip, lha, lzx, adf, dms, hdf, exe, txt, pdf, doc, docx, jpg, jpeg, png, gif`.
  Enforced server-side on upload (in both add and edit-with-replacement flows),
  matched case-insensitively against the extension of the original filename —
  rejected extensions produce a validation error, not a silent rename.
- 25MB max upload size, enforced both via a form `MAX_FILE_SIZE` hint and
  server-side against `$_FILES['upload']['size']` — the server-side check is
  authoritative since the client-side hint is not trustworthy.
- MIME-sniffing is explicitly NOT used as a security boundary (many legitimate
  Amiga archive formats — `.adf`/`.lha`/`.dms`/`.lzx` — sniff as
  `application/octet-stream`, indistinguishable from an executable). The
  whitelist + randomized filename + storage lockdown are the real defenses.

## Admin UI (mirrors the existing `mags_online` CRUD pattern)

- `admin/files.php` — list page. Table of files with title, size, download_count,
  active/inactive status, Edit/Delete links, "+ Add" button, flash message
  display. Gated by `require_admin()`.
- `admin/file_form.php` — add/edit form, `enctype="multipart/form-data"`.
  - Fields: title (required), description (required, also used for search),
    active checkbox (default checked), file upload.
  - On add: file upload is required.
  - On edit: file upload is optional. If a new file is provided, it replaces the
    stored file — the old file is deleted from `storage/` and
    `stored_filename`/`original_filename`/`file_size`/`file_ext` are updated. If
    left blank, the existing file is kept and only metadata (title, description,
    active) is updated.
  - Validation errors collected into `$errors[]` and redisplayed, following the
    existing pattern in `mags_online_form.php`.
  - On success: prepared INSERT/UPDATE, `log_audit($myConnection, 'file', $id,
    $is_edit ? 'edit' : 'add', $values['title'], $_SESSION['user_id'])`, flash
    message, redirect to `admin/files.php`.
- `admin/file_delete.php` — GET shows a confirmation page ("Are you sure?"), POST
  with `confirm_delete=1` executes: delete the DB row, delete the file from
  `storage/`, `log_audit()`, flash message, redirect. Mirrors
  `mags_online_delete.php`.
- `admin/_nav.php` gets a new `Files` link in the admin-role nav block.

## Public pages

- `sidebar_categories.php` — QUICK LINKS block gets a new line:
  `<a href="entry_files.php">FILES</a>`, alongside the existing NEW SITES / TOP
  RATED / ARCHIVED SITES / DEAD SITES links.
- `entry_files.php` — thin routing file, sets `$_SESSION['content_type'] =
  'files'` and includes `page_builder.php`, following the pattern of
  `entry_new_sites.php` etc.
- `sec_body.php` — gets a new branch:
  `else if($_SESSION["content_type"]=='files'){ include 'content_files.php'; }`
- `content_files.php` — public listing page styled like `content_news.php`:
  header block, paginated flat list (new `FILES_PER_PAGE` constant), each row
  showing title, description, file size (human-readable), download count, and a
  download link pointing at `file_download.php?id=N`. Only rows with `active=1`
  are shown. Uses a `get_files_page($myConnection, $offset,
  $total_records_per_page)` fetch helper and a `table_content_files.php` row
  partial, matching the `table_content_news_sub.php` pattern.
- `file_download.php` — accepts `?id=N`. Validates the id, looks up the row,
  404s if missing or `active=0`. On success: increments `download_count` via a
  prepared `UPDATE`, then streams the file from `storage/` with headers
  `Content-Disposition: attachment; filename="<original_filename>"` and an
  appropriate `Content-Type` (safe generic fallback
  `application/octet-stream` is fine given the MIME-sniffing caveat above).

## Search integration

- `content_search_proc.php`: add a 10th entry to the `$simple_sections` config
  array for `Files`, matching the search term against `title` and
  `description`, filtered to `active=1`. Uses the existing generic
  `table_search_simple_row.php` row partial (`name_field` → title,
  `url_field` → `file_download.php?id=N`, `extra_label`/`extra_field` → file
  size or download count).
- `content_advanced_search_proc.php`: same section added to the advanced-search
  filter/checkbox set, consistent with the other 9 sections' existing pattern
  (section checkbox + date-range filter on `created_at`).

## Documentation

`CHANGE.md` must be updated when this feature ships, describing: the new file
repository feature (admin-managed uploads, `storage/` folder, download counter,
search integration, sidebar FILES link) — per explicit user instruction to
include this level of detail in the changelog entry, not just a one-line note.

## Testing/verification approach

No test framework exists in this repo (per `CLAUDE.md`). Verification will be via:
- `php -l` lint on every new/modified file.
- `php -r` throwaway scripts against a local dev DB to verify the `t_files`
  schema and CRUD queries.
- `curl` with cookie jars against the local dev site to exercise: admin
  add-with-upload, admin edit-with-replacement, admin edit-metadata-only, admin
  delete, public listing pagination, download-count increment, quick search
  hit, advanced search hit, and an inactive file being correctly hidden from
  both listing and search.
- Manual confirmation that `storage/.htaccess` actually blocks direct access
  (`curl` a known stored filename directly and confirm403/404, not 200).
