# Change Log

This file keeps a plain-language record of what changed with each update to the site, for anyone to read — no code or technical detail required.

---

## 2026-07-08

Set up the project notes for the upcoming work: a written plan (in three versions — one detailed, one simple, one styled to match the site's retro look) covering the phases needed to add an admin area that more than one contributor can use. Also wrote down the ground rules for this project: any new features must still work on very old web browsers, the site stays hosted on GoDaddy, all real code changes happen inside the site's `files` folder, and this log will be updated every time work is saved going forward.

No changes were made to the live site itself yet — this was planning and setup only.

---

## 2026-07-08 (audit)

Finished the first working phase: a full read-through of the existing site's code
and database. Wrote down exactly what every file does and how the pages connect
to each other.

Found one real bug: the admin "add news" button currently saves to a database
table that doesn't exist, so any new post silently disappears — the person
adding it sees no error and has no way to know it didn't save. Also found three
spots (the category page, the search box, and search results) that accept text
from visitors without checking it first, which is a security risk worth closing
before the admin area opens up to more contributors. Admin pages currently have
no login screen at all — anyone who finds the web address can get in.

Also noticed two unused/orphaned things: an old duplicate copy of the news page
that nothing links to anymore, and a "category" feature in the database that
was set up but never finished being built into any page.

Drafted, for your review only (nothing has been changed yet): a plan for
consistent, less confusing file naming, and a cleaner folder layout for the
admin area once it's rebuilt.

---

## 2026-07-08 (naming cleanup)

Applied the first piece of the file-naming cleanup you approved: the two
public pages that had a confusing, near-duplicate name to the admin area's
pages were renamed to something clearer. Nothing about how these pages work
or look changed — only their internal file names, and the couple of spots on
the site that pointed to those names were updated to match. Checked the live
site afterward to confirm the category page and the search box still work.

The rest of the naming cleanup (a matching prefix for admin pages) is being
held until the admin area itself is rebuilt, since renaming it twice would be
wasted effort.

---

## 2026-07-08 (database groundwork)

Added groundwork to the database so future features have something solid to
build on, without changing how anything currently looks or works. Every table
now automatically records when a row was created and last changed. The two
category tables gained an on/off switch for hiding a category without deleting
it. The links table gained a spot to mark a link as "verified" going forward.
Also added several behind-the-scenes lookups (indexes) so category and search
pages can find matching links faster as the link list grows.

Took a full backup immediately before making any of these changes, and wrote
every change as a paired "apply" and "undo" script, tested both directions,
before leaving the database in its new state. Checked the news page, the
category page, and the search box afterward to confirm everything still works
exactly as before.

Two things were deliberately left for later, to avoid building them twice: a
way to mark submitted links/news as pending/approved/rejected, and any
moderation controls — both will be designed together with the new admin
console rather than bolted on now.

---

## 2026-07-08 (live deploy)

Put both of the above changes — the clearer page names and the database
groundwork — onto the actual working site, not just a local test copy.
Backed up the live database first, applied the same tested changes, and then
checked the homepage, a category page, and the search box directly on the
live site to confirm everything still works correctly. Also removed the two
old, now-unused page files so they don't cause confusion for future work.

---

## 2026-07-08 (code cleanup)

Closed the security gap flagged in the audit: the category page, the search
box, and the search results page no longer accept raw, unchecked text from
visitors — all three now check and safely handle what's typed before it ever
reaches the database.

Pulled all the site's scattered colors and text styling into one shared
style sheet, so a color or font size only needs to be changed in one place
going forward instead of hunting through dozens of pages. Also gathered the
handful of magic numbers (like how many links or news items show per page)
into one settings file instead of leaving them buried in the page code.

Removed a leftover duplicate copy of the news page that nothing on the site
was using, and took out a broken link on the link-listing page that pointed
to a long-dead outside tool.

Checked the whole site afterward — homepage, category pages, search, and the
admin prototype pages — to confirm every page still loads and works exactly
as before, and re-ran the security checks from the audit to confirm the gap
is actually closed, not just visually unchanged.

---

## 2026-07-08 (admin login)

Built the real login system the audit flagged as missing: the admin area no
longer lets anyone in just by knowing the web address. There's now a proper
login page, a logout link, and a shared account (username `scottp`) with a
password only that person has. Five wrong password attempts in a row locks
the account for 15 minutes, so it can't be guessed by brute force. A
dashboard page and an account page (where the password can be changed) were
also added as the landing spot after logging in.

---

## 2026-07-08 (header login link)

Added a Login link to the top of every page on the main site. If nobody is
logged in, it just says "Login." Once someone does log in, it changes to
show their username along with Dashboard and Logout links instead, so
there's always a clear, one-click way in and out of the admin area from any
page. Matching changes went into the admin area's own header too: its logo
now links back to the main site (previously it went nowhere useful), and a
"Back to Site" link was added next to the existing "Log Out" link.

---

## 2026-07-08 (header tidy-up)

Cleaned up the main site's header: the scrolling tagline ("Since 2001... "),
which used to slide across the screen, is now still text that stays in
place. It and the new Login/Dashboard/Logout links (added above) now sit on
the same line — tagline on the left, account links on the right — instead of
stacking on two separate lines.

---

## 2026-07-08 (color names)

Renamed the color classes in the shared style sheet from hex codes (like
`bg-ff9900`) to plain English names (like `bg-orange`), and updated every
page that used them. Nothing about how the site looks changed — this only
makes the styling easier to read and update in the future.

Put all of the above — the login system, the header links, the header
tidy-up, and the color renaming — onto the live working site (not the real
public site) and confirmed every uploaded file matches exactly what was
tested locally before calling it done.

---

## 2026-07-09 (admin link management)

Gave admins a real screen for managing the site's link directory, instead of
needing to edit the database directly. Admins can now browse every link in
one sortable, filterable, paginated list (search by name/URL/author,
narrow down by category or status), add a brand-new link, edit an existing
one, and remove a link — with every add/edit going through a preview screen
first so the admin can see exactly how the link will look before it's
saved. Removing a link doesn't erase it outright: it's hidden from the
normal list but can still be found and brought back later via a "Show
Deleted" option, so a mistaken removal isn't permanent.

While reviewing this new screen before calling it finished, found and
closed two security gaps it introduced: the link web address field would
have accepted a disguised, harmful web address as if it were a normal one,
and the link name/author/description fields would have accepted hidden
code instead of just plain text. Both are now blocked before anything gets
saved. Also tightened the "restore a removed link" button so it needs a
confirmed click rather than acting the instant the link is visited.

---

## 2026-07-09 (category structure rebuild)

Rebuilt how the site's categories are stored so they can now be nested as
deep as needed, instead of being stuck at exactly two levels (a main
category and one sub-category under it). Admins get a new screen to add,
edit, remove, and reorder categories at any depth, with simple up/down
buttons to control the order they appear in (kept deliberately simple, no
drag-and-drop, so it keeps working in very old browsers).

While rebuilding this, found and fixed two data problems that were already
quietly present: one category's web address didn't match the ID used to
file links under it (so visiting it from the sidebar could show the wrong
page), and two whole categories — used by 40 existing links — had become
invisible everywhere because of a broken internal reference. Both are now
fixed and those categories are visible and working again. A handful of
links (25) also had a leftover placeholder category value from years ago,
which has been cleared out.

The old two-table category storage is left in place, untouched, to be
cleaned up later — nothing currently uses it anymore.
