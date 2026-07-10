# Legacy HTML Rendering (IBrowse Compatibility) — Design

## Problem

The client uses **IBrowse** on AmigaOS (via AmiKit XE) to view the live site and
reported that tables and colors don't render correctly. Two root causes were
found, both verified against evidence, not assumption:

1. **No CSS support in IBrowse at all** — confirmed via IBrowse's own FAQ
   ("No CSS yet, and only limited DOM support"). This applies to external
   stylesheets, `<style>` blocks, and inline `style=""` attributes equally —
   all three are CSS. The site currently styles everything through CSS
   classes (`class="bg-white"`, `class="txt-2-black"`, etc.), a result of the
   Phase 02d refactor that replaced legacy inline `bgcolor=`/`<font>` markup
   with an external stylesheet. None of that CSS renders in IBrowse, so every
   element with only a CSS class and no fallback attribute shows unstyled
   (default background, default font).

2. **Two unclosed `<table>` elements** — found by counting `<table>`/`</table>`
   tags per included file. `sec_body.php` (sidebar wrapper table) and
   `content_news.php` (outer content wrapper table) were each missing one
   closing tag. `<table>`, unlike `<td>`/`<tr>`, does not have an optional end
   tag in the HTML spec — an unclosed table can cause a strict/non-fault-tolerant
   parser to fail to render structure past that point, which is a better match
   for "tables didn't show at all" than a pure styling issue. **Both fixed and
   verified already** (table count balanced 72/72 across the full rendered
   page, confirmed via curl + tag-depth trace).

## Reference

The live production site (`amigasource.com`) was fetched directly
(`view-source`) as a known-working reference, since the client's screenshots
show it rendering correctly in real IBrowse. Its markup uses:

- Legacy `bgcolor="#hex"` attributes directly on `<table>`/`<tr>`/`<td>` —
  no CSS classes at all.
- `<font face="Verdana, sans-serif" size="N" color="#hex">` wrapping every
  piece of styled text — `face` is repeated on every single `<font>` tag,
  not just declared once, because there is no CSS to inherit from.
- Its color values match this project's `style.css` almost exactly (e.g.
  `bgcolor=#637B94` ↔ `.bg-slateblue { #637b94 }`, `bgcolor=#c70000` ↔
  `.bg-darkred { #c70000 }`), because `style.css` was originally *extracted
  from* this exact inline markup during Phase 02d.
- Its `<font size="N">` values match this project's `.txt-N` class number
  suffixes exactly (e.g. `size="4"` ↔ `.txt-4`), confirming the Phase 02d
  naming convention preserved the original legacy size values.

The fix in this design should produce markup structurally equivalent to this
reference — same nesting style, same per-tag `bgcolor`/`font` attributes, not
just visually similar colors.

## Design

### Approach

Convert every `class="bg-*"` / `class="txt-*"` occurrence across the codebase
to carry an equivalent legacy attribute, while **keeping the existing class
attribute and `<style>` include** so modern browsers still get CSS (which
wins the cascade over presentational attributes, so there's no visual
conflict). This was chosen over two alternatives:

- **Runtime DOM post-processing** (buffer final HTML, use `DOMDocument` to
  inject attributes automatically) — rejected: risks mangling this
  codebase's already-loose HTML via libxml's normalization, and adds a
  "magic" transformation layer that contradicts the project's plain,
  hand-editable style (no frameworks, no build step, per `CLAUDE.md`).
- **Fully replacing CSS classes with legacy attributes** (drop `class=` and
  `<style>` entirely) — rejected: throws away the Phase 02d single-source-of-
  truth win for anyone reading the code, for no functional benefit (legacy
  attributes and CSS classes can coexist without conflict).

### Components

**`files/legacy_colors.php`** (new file) — a plain PHP array mirroring the
color/size definitions already in `style.css`, plus two helper functions:

```php
<?php
$LEGACY_BG_COLORS = [
    'white'       => '#ffffff',
    'red'         => '#ff2626',
    'whitesmoke'  => '#f4f4f4',
    'slateblue'   => '#637b94',
    'darkolive'   => '#575748',
    'lightgray'   => '#dddddd',
    'orange'      => '#ff9900',
    'gray'        => '#bbbbbb',
    'skyblue'     => '#6699cc',
    'darkred'     => '#c70000',
    'cyan'        => '#00ffff',
    'gold'        => '#f1c40f',
    'blue'        => '#006cd9',
    'purple'      => '#842dce',
    'teal'        => '#336666',
    'magenta'     => '#990099',
    'burntorange' => '#dc7633',
    'charcoal'    => '#333333',
    'green'       => '#229c22',
    'offwhite'    => '#fafafa',
    'pink'        => '#d61baf',
];

$LEGACY_TXT_COLORS = [
    'white' => '#ffffff',
    'black' => '#000000',
];

function bg_hex(string $name): string
{
    global $LEGACY_BG_COLORS;
    return $LEGACY_BG_COLORS[$name] ?? '#000000';
}

function txt_hex(string $name): string
{
    global $LEGACY_TXT_COLORS;
    return $LEGACY_TXT_COLORS[$name] ?? '#000000';
}
```

Values are transcribed once, directly from `style.css`, and checked against
the live reference site's actual colors during implementation (spot-check,
not exhaustive) to catch any transcription error immediately.

`legacy_colors.php` is included once per page (same place `style.css` is
included today — `page_builder.php` for the public site, each `admin/*.php`
file's `<head>` for admin pages).

### Conversion Pattern

For every element carrying a `bg-*` class:

```php
<!-- before -->
<td class="bg-white">

<!-- after -->
<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Applies directly to `<table>`, `<tr>`, `<td>` — all three accept `bgcolor`
natively, no structural change needed.

For every `<span class="txt-N-color">...</span>` (or bare `txt-N` with no
color, which inherits the surrounding text color — left as plain text with
just `size`, no `color` attribute, matching the reference site's own
`size="0"` example which omits `color` and inherits):

```php
<!-- before -->
<span class="txt-2-black"><b>Search</b></span>

<!-- after -->
<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Search</b></font>
```

`<span>` becomes `<font>` — a structural swap, not just an attribute
addition, since `<span>` has no legacy color/size attributes of its own.
`class` carries over unchanged so the `<style>` block still applies for
modern browsers.

### Scope

All ~40 files across the public site and `admin/`, per the existing
`class="bg-*"`/`class="txt-*"` inventory (~350 occurrences total):

- Public: `mod_header.php`, `mod_footer.php`, `sec_body.php`,
  `mod_sidebar_chooser.php`, `sidebar_*.php` (9 files), `content_*.php` (3
  files), `table_*.php` (3 files), `entry_search.php`.
- Admin: all `admin/*.php` files that currently include `style.css`
  (18 files, per the earlier inline-`<style>` conversion list).

### Testing

- `php -l` on every modified file (syntax check).
- `<table>`/`</table>` tag-count balance re-verified after all edits (same
  method used to catch the two unclosed-table bugs), since structural swaps
  (`<span>` → `<font>`) don't touch tables but any accidental edit could.
- Visual spot-check via curl diff: fetch each converted page before/after and
  confirm the `bgcolor=`/`font color=` values match the corresponding
  `style.css` hex value for every class touched.
- Final validation against the actual client hardware: once Icaros/IBrowse
  (or the client directly) can load the live-deployed pages, confirm colors
  and table structure render as expected. This is the only step that
  confirms the fix actually works in the target environment — the automated
  checks above only confirm the generated markup matches the known-working
  reference pattern.

## Out of Scope

- The admin news editor's TinyMCE integration (`admin/news_form.php`) —
  already confirmed to degrade gracefully to a plain `<textarea>` in
  browsers without modern JS support; no change needed.
- Building/completing the Icaros/VirtualBox local IBrowse test environment —
  useful for future bug reports but not required to ship this fix, since the
  live reference site (real, currently-working IBrowse target) already
  provides working ground truth for markup patterns.
- The news/link contributor submission workflow (separate, already-identified
  gap in the multi-contributor feature) — unrelated to this rendering fix.
