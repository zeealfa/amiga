# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Two things live side by side here:

1. **`files/`** — the actual production PHP codebase for amigasource.com (a public Amiga hobbyist site: news, links, calendar, crowdfunding, top 10, shops/vendors, publications). This is the client's real, unmodernized "old school" site.
2. **Planning documents** at the repo root (`roadmap.html`, `proposal.html`, `proposal-amiga.html`) — standalone HTML deliverables (not app code) describing a phased plan to add a multi-contributor admin module on top of the existing site. Screenshots of the live site's current pages are in `screenshots/`.

There is no build system, package manager, or test suite. This is intentional — see hard constraints below.

## Hard technical constraints (client-mandated)

- **Vanilla PHP + MySQL (mysqli) + HTML only.** No frameworks, no Composer, no npm, no JS libraries, no build step.
- **Single-page-app style delivery**: pages are assembled via PHP `include`, not routed through a front controller.
- Keep new work consistent with this "old school" style — do not introduce modern tooling as part of any phase without an explicit go-ahead, since the roadmap documents commit to this constraint.

## Architecture of `files/` (public site)

Page assembly is a straight include chain, driven by a `$_SESSION['content_type']` switch — there is no router:

```
index.php
  → login_db.php          (opens the mysqli connection as $myConnection, sets $_SESSION['content_type'])
  → page_builder.php       (outer table layout)
      → sec_header.php     (currently empty placeholder)
      → sec_body.php       (main table: sidebar + content columns)
          → mod_sidebar_chooser.php  → sidebar_*.php (calendar, crowdfunding, categories, top10, shops/vendors, service/repair, publications, search, add-link)
          → content_{news,categories,search}.php   (chosen via $_SESSION['content_type'])
      → sec_footer.php     (currently empty placeholder)
```

- `$myConnection` (a `mysqli` resource) is a global created once in `login_db.php` and used directly by every included file that needs the DB — there is no DB abstraction layer.
- Layout is nested HTML `<table>` markup with inline `bgcolor`/`font face` attributes (pre-CSS-era style). `mod_header.php` and sidebar modules follow the same pattern.
- `content_search_proc.php` builds SQL by directly interpolating `$_POST['search']` into the query string (`t_links` table) — no parameterization. Treat any new query code as needing prepared statements even though the existing code does not use them; do not copy this pattern forward.
- DB credentials are hardcoded inline in `login_db.php` (and duplicated in `files/ata/conn.php`) rather than in a config file.

## `files/ata/` — early admin prototype

A minimal, unauthenticated admin area already exists and is the seed for the planned admin module:
- `index.php` — a static links dashboard (Admin Options / User Options tables); most links are still `---` placeholders (Calendar, Crowd Funding, Top 10, Shops & Vendors, Print & Publications, Tabor, and all "User Options" are not wired up yet).
- `a_news.php`, `add.php`, `edit.php`, `update.php`, `delete.php` — CRUD for `t_news_sub` (news items with `news_date`, `news_story`, `news_active`).
- `a_category.php`, `a_links_check.php`, `a_links_check_02.php` — read-only/check views for categories and links.
- `conn.php` — its own separate mysqli connection (same DB, duplicated credentials from `login_db.php` — not shared/reused).
- **No authentication currently gates this directory.** Adding auth and multi-contributor support here is the core of the planned work described in the roadmap documents.

## The planning documents

These are static, self-contained HTML files (inline `<style>`, no external CSS/JS) — not part of the runtime app. They exist to describe the same phased plan to different audiences:

- `roadmap.html` — internal/technical version: phases 00–07, complexity stars, risk levels, task-type tags (DB/UI/AUTH/LOGIC/QA), milestone checklists, a CSS-Gantt timeline table, a codebase-onboarding checklist, and a naming-convention table. Duration estimates here are labeled in **hours** (not days) per an explicit client instruction — do not "fix" this back to days, and do not recompute the numeric ranges when editing wording nearby.
- `proposal.html` — client-facing version of the same plan: plain-language phase summaries plus a CSS-only (`position:absolute` bars) Gantt chart.
- `proposal-amiga.html` — a visually distinct copy of `proposal.html` styled to look like a hand-built, retro AmigaOS Workbench–themed page (raised/sunken borders, classic title bar, Verdana/Courier New fonts). Its Gantt chart is a genuine HTML `<table>` with colored `<td>` cells (deliberately not CSS-positioned) to match the "old school hand-coded" aesthetic. Phase content must stay word-for-word consistent with `proposal.html` when either is edited.

When editing any of the three planning documents, changes to phase content, durations, or scope should generally be mirrored across all three (technical wording differs, but the underlying phases/durations should not silently diverge) unless the user asks for a change to only one.

## Running/previewing

There's no dev server config in this repo. `files/` is a plain PHP+MySQL app meant to run under a standard Apache+PHP+MySQL stack (e.g. XAMPP, given the `htdocs` path) with a `mysqli` database named `asdb` (or `asbd`/`tmainasdb` per commented-out alternates in `login_db.php`). The root-level `.html` planning documents can be opened directly in a browser with no server needed.
