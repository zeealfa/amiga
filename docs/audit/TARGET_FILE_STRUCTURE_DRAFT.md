# Target File Structure — Draft

Constraint: stays vanilla PHP, single CSS file, no framework, no build step
(per client's hard requirement) — this is a proposed reorganization of the
existing `files/` tree, not a rewrite.

```
files/
  index.php                  (unchanged — public entry point)
  action_categories.php, action_search.php   (renamed per NAMING_CONVENTIONS_DRAFT.md)
  config/
    db.php                   (NEW — single shared credential source, replaces the
                               duplicated inline credentials in login_db.php and ata/conn.php)
  login_db.php                (unchanged behavior, but includes config/db.php instead
                               of hardcoding credentials)
  sec_*.php, mod_*.php, sidebar_*.php, content_*.php, table_*.php   (unchanged locations)
  admin/                      (NEW — replaces files/ata/, name change signals "this is
                               the real admin module now, not a prototype")
    auth.php                  (NEW — Phase 03: session-based login gate, required by
                               every file in admin/)
    conn.php                  (includes config/db.php, same as login_db.php)
    index.php                 (dashboard — evolves from ata/index.php)
    news/
      list.php, add.php, edit.php, delete.php   (renamed from flat add.php/edit.php/etc,
                               grouped by entity so a second entity — links, categories —
                               doesn't collide on generic verb names)
    links/                    (NEW in Phase 04 — currently only ata/a_links_check*.php exist)
    categories/               (evolves from ata/a_category.php)
```

This groups admin pages by entity (Phase 04's stated goal: "never open phpMyAdmin
for routine admin tasks again" implies more entities than just news are coming),
without introducing routing/framework machinery — still plain directories and
plain `include`s.

**Needs client confirmation before Phase 01/03 start moving files.**
