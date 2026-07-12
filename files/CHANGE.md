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

---

## 2026-07-09 (link categories rebuild)

Replaced the site's old "5 fixed category slots per link" storage with a
proper flexible system, so a link's category tags are stored as a real
list instead of being crammed into five always-there-even-if-empty
columns. Nothing changes for how admins use the category picker when
adding or editing a link — same up-to-5 limit, same checkbox list — this
was purely a behind-the-scenes storage improvement.

One real improvement did come out of it: category pages now show links
filed under any of that category's sub-categories too, not just links
filed under the exact category being viewed. Previously, visiting a
parent category and expecting to see everything "under" it would miss
links that were only tagged with one of its children.

Also removed a small piece of leftover debug text that was quietly
showing raw internal category numbers next to every link on category
pages — visitors were never meant to see that.

The old five-slot columns are left in place, untouched, to be cleaned up
later alongside the other deferred cleanup items — nothing currently uses
them anymore.

---

## 2026-07-09 (link URL status check)

Added a small live indicator on the admin "Add/Edit Link" screen: next to
the web address field, a green checkmark or red cross now shows whether
that address currently loads, checked automatically as soon as the page
opens (when editing an existing link) and again a moment after typing a
new or changed address. This is just a quick visual hint for the admin
while filling out the form — it doesn't change or save anything on its
own; the "Dead" checkbox still has to be set and saved manually as
before.

---

## 2026-07-09 (test site deploy)

Put the recent batch of work — link management, the new nested category
system, the category-to-link storage rebuild, and the link web address
checker — onto the test working site (testamigasource.com), so it can be
tried out somewhere real before going to the actual public site. The
matching database changes were applied first through the test site's own
database tool, then every updated file was uploaded. Two old files that
nothing uses anymore (leftover pieces of the old two-level category
system) were removed from the test site to match. Every uploaded file was
then checked directly against the copy kept here to confirm the upload
matches exactly, with nothing missing or corrupted in transit.

One hiccup along the way: an earlier attempt to upload everything in one
automated batch appeared to freeze partway through (one file in
particular showed no sign of finishing after a long wait), so that
attempt was stopped rather than left to sit. The files were then uploaded
by hand instead, which completed without the same issue, and every file
was re-checked afterward to make sure nothing was left out or only
partly uploaded because of the earlier interruption.

---

## 2026-07-09 (link quick-actions)

Added one-click buttons on the admin "Manage Links" screen: an admin can
now mark a link dead (or clear that mark) and mark a link verified (or
clear that mark) directly from the list, without opening the full
edit-and-preview form. A new "Archive.org" link next to each entry opens
that link's Wayback Machine history in a new tab. A confirmation message
now also appears at the top of the list after deleting, restoring, or
using either new button — previously the page silently dropped that
confirmation after deleting or restoring a link.

---

## 2026-07-09 (links check all)

Added a "Check All" button to the admin "Manage Links" screen. Clicking
it checks every link on the current page to see if it's still reachable
right now, showing a green checkmark or a red cross next to each link's
web address as the results come in. This is a live, on-demand look only
— it doesn't change any link's saved dead/verified status; marking a
link dead is still a separate, deliberate action using the existing
"Mark Dead" button.

---

## 2026-07-09 (check all speed)

Changed "Check All" to fire every link's check at the same time instead
of checking them four at a time, so results for a full page of links
come back faster.

---

## 2026-07-09 (clickable links)

On the admin "Manage Links" screen, a link's web address is now
clickable and opens the actual site in a new browser tab, instead of
just being plain text an admin had to copy and paste elsewhere to
visit.

---

## 2026-07-09 (news admin)

Built a real news editor for admins, replacing an old, broken, unlocked
prototype that silently failed to save new posts (it saved to a table
the site doesn't actually use). Admins can now browse, search, and
page through news posts; add or edit a post using a proper formatting
toolbar (bold, lists, links, tables) instead of typing raw website
code by hand; preview exactly how a post will look on the homepage
before saving; publish or unpublish a post with one click; and delete
a post (recoverable later via "Show Deleted") instead of losing it
for good.

---

## 2026-07-09 (news admin — live deploy)

Put the new news editor onto the actual working site. The database
change (adding the ability to recover a deleted news post) was applied
to the live database, and the new admin pages were uploaded alongside
it. The old, broken, unlocked news editor was also removed from the
live site.

---

## 2026-07-10 (user accounts & roles)

Gave admins a screen for managing who can log into the admin area,
closing out the last item from the original admin-area project plan.
Admins can now add a new account (choosing a username, email, role,
and starting password), edit an existing account's details, and
deactivate or reactivate an account with one click — a deactivated
account can no longer log in, but nothing about it is deleted, so it
can be turned back on later.

Anyone logging in with a password an admin just set (whether it's a
brand-new account or a password reset on an existing one) is now
required to choose their own password immediately after logging in,
before they can see anything else in the admin area.

Admins can also manually clear a "locked out" state (from five wrong
password attempts in a row) straight from this same screen, instead
of needing to wait out the 15-minute lock.

---

## 2026-07-10 (user accounts & roles — live deploy)

Put the new user management screen onto the actual working site. The
database change (adding the forced-password-change flag) was applied
to the live database, and the new admin pages were uploaded alongside
it.

---

## 2026-07-10 (older browser support)

Updated the site's pages so they also display correctly in older,
simpler web browsers like IBrowse on classic AmigaOS machines, which
don't understand the modern styling the site normally relies on.
Old-style color and font markup was added alongside the existing
styling throughout the public site and the admin area, so visitors on
those older browsers now see the same colors, backgrounds, and text
sizing as everyone else instead of a plain, unstyled page. Two pages
that had a structural layout problem, which would have made this fix
incomplete, were also corrected along the way.

---

## 2026-07-10 (news page layout fix & link editor cleanup)

Fixed a layout mistake on the "Latest News" page where part of the
page's table structure was never properly closed, which could cause
the page to display incorrectly in older browsers. Also cleaned up a
small formatting mistake on the site's link search results (a bold
label wasn't closed correctly) and added a "Back to List" link to the
admin link editing screen, matching the one already on the news
editing screen.

---

## 2026-07-10 (contributor accounts & submission review)

Added the ability for people other than the site admin to contribute.
Visitors can now register their own account from the login page; new
accounts wait for an admin's approval before they can log in. Once
approved, a contributor can submit new links or news posts, or
propose an edit to one of their own existing entries, from their own
"My Links" / "My News" / "My Submissions" pages. Nothing a contributor
submits goes live automatically — every submission sits in a review
queue until an admin approves or rejects it, and rejected submissions
show the contributor the admin's reason.

---

## 2026-07-11 (search warning fix & page appearance match)

Fixed two harmless but visible warning messages that could appear on
the search page when it was opened without an actual search term.
They no longer show up.

Also brought the site's appearance closer to how the real public site
looks: restored the wider page layout, added proper spacing between
the sidebar and the main content area, and corrected the padding
inside the sidebar boxes (Calendar, Categories, Crowd Funding,
Service & Repair, Shops and Vendors, Tabor Links, Top 10) so their
colored borders and spacing match the real site exactly instead of
looking flat. The admin area's own pages were widened to match.

---

## 2026-07-11 (login page cleanup & dashboard metrics)

Simplified the admin login page so it only shows one short line about
registering an account, instead of three lines that repeated the same
idea.

Finished the admin dashboard, which previously just said "you are
logged in" with no real information. It now shows a set of at-a-glance
numbers: admins see site-wide totals (active links, active news posts,
active categories, active users, dead links, and pending submissions
awaiting review), while regular contributors see their own totals
(their links, their news posts, and how many of their submissions are
pending, approved, or rejected).

---

## 2026-07-11 (behind-the-scenes cleanup, no visible changes)

Reorganized how several pages fetch their information from the
database — news, categories, calendar, crowd funding, online and
print publications, service & repair listings, shops & vendors, and
the Top 10 list. Previously the "get the data" step and the "display
it on the page" step were tangled together in the same block of code;
they're now cleanly separated, which makes future changes safer and
easier. This does not change anything visitors see or how any page
looks or behaves — verified by comparing the exact page output before
and after each change.

---

## 2026-07-11 (public link stats made real)

The "TEMP LINK COUNT" box on the news page is no longer temporary or
misleading. "Verified" now correctly counts links that have actually
been checked, instead of an old, incorrect method that missed most of
them. "New links" now means links added in the last 7 days, instead of
counting everything added since December 2021. The box was also
renamed to "Site Stats" and given a fresh look with a colored header
and a colored number for each stat, to match the style used on the
admin dashboard. A count of active categories was added as a fifth
stat, so the box now covers total links, verified links, links added
this week, and categories.

---

## 2026-07-11 (news editor upgrade & link URL checker for contributors)

The contributor "Submit News Post" / "Edit News Post" form now has the
same rich text editor already used on the admin news form, so
contributors can format their stories (bold, lists, links, tables)
instead of typing plain text.

The "Submit Link" / "Edit Link" form now checks a submitted web
address as soon as you click out of the URL field, showing a checkmark
or a cross next to it — the same live link-checking already available
to admins, now also working for regular contributors.

---

## 2026-07-11 (bulk verify links on the admin links page)

The admin Links page's "Check All" button now feeds a new "Verify All"
button. "Verify All" stays greyed out until a Check All run finishes,
then clicking it saves the results permanently — links that checked
"up" are marked Verified, links that checked "down" are marked Dead —
instead of the checkmarks/crosses only being a temporary on-screen
display like before.

Note: "Verify All" only works in modern browsers (it needs built-in
JSON support), unlike the rest of the admin link-checking tools, which
also work in older browsers. This only affects the admin area — the
public site is unaffected.

---

## 2026-07-11 (link visibility now controlled by the "Active" checkbox)

Whether a link shows up on the public site is now controlled by its
"Active" checkbox in the admin link editor, instead of only by whether
the link was found to be broken. This means an admin can now hide a
link from public view without deleting it, even if the link itself
still works — something that wasn't possible before. Links kept for
the record with an archive.org copy still show up regardless of the
Active checkbox, same as before.

Also fixed a bug where a link's live-vs-archived display never
actually checked its Active status, so it always behaved the same way
no matter what was set.

"Verify All" (see above) now records that a link was checked and when,
for every link it checks — not just the ones that turned out to be
live — but it no longer touches the Active checkbox, since that's now
a manual editorial choice rather than an automatic one.

---

## 2026-07-11 (audit log)

Admins can now see a history of who added, edited, deleted, or
restored a link, news post, or category, and when. This shows up on
a new "Audit Log" page in the admin menu, with filters for entity
type and action.

Note: this needs its database table created on the live site before
it will work there — it's only been set up on the local dev database
so far.

Bulk-verifying links (the "Verify All" button) now also gets recorded
in the audit log, one entry per link checked.

---

## 2026-07-11 (fixed: Verify All button was completely broken)

Found and fixed a bug that made the "Verify All" button fail every
time it was used, ever since it was added — it was missing its
connection to the database, so clicking it would error out instead
of saving anything. This is now fixed and verified working.

---

## 2026-07-11 (Calendar and Crowdfunding now manageable from admin)

The Calendar and Crowdfunding boxes on the site's sidebar previously
had no way to be updated except by editing the database directly.
Admins can now add, edit, and delete both calendar events and
crowdfunding campaigns from new "Calendar" and "Crowdfunding" pages
in the admin menu, the same way links and news already work. These
changes are also recorded in the Audit Log.

Also removed the "Add A Link" box from the sidebar — it was a
placeholder that said "under construction" and never did anything.

---

## 2026-07-11 (calendar events now number themselves automatically)

Behind the scenes, new calendar events were being assigned their ID
number by the application rather than by the database itself — a
leftover inconsistency from before the admin Calendar page existed.
This is now fixed so the database numbers new events automatically,
the same way every other list on the site already works. No visible
change for admins.

---

## 2026-07-11 (Top 10, Shops & Vendors, Service & Repair, and Publications now manageable from admin)

The rest of the sidebar boxes — "Top 10+8", "Shops and Vendors",
"Service and Repair", and "Publications" (both Print and Online) —
previously had no way to be updated except by editing the database
directly. Admins can now add, edit, and delete entries for all five
from new pages in the admin menu, the same way links, news, calendar,
and crowdfunding already work. These changes are also recorded in the
Audit Log.

---

## 2026-07-11 (visitors can now recommend a link)

Added a "[+1 recommend]" link next to every listed link (on category
pages and search results), with a count showing how many visitors have
recommended it. This works for anyone browsing the site — no login
needed. Each visitor's computer can only recommend a given link once,
to keep the count honest; clicking it again from the same computer
doesn't add another vote. This does not fully stop a large-scale
automated attack (that would need protection at the hosting level,
outside what the website's own code can control), but it does stop
casual repeat-clicking from inflating the numbers.

---

## 2026-07-11 (Quick Links in Categories now work)

The Categories sidebar has had three "Quick Links" — NEW SITES, ARCHIVED
SITES, and DEAD SITES — that were marked "in prog" and didn't go
anywhere. They now work:

- **NEW SITES** shows links added in the last 7 days, newest first. If
  nothing was added in the last week, it shows the 10 most recently
  added links instead, so the page is never empty.
- **ARCHIVED SITES** shows every link that has an archived (Wayback
  Machine) copy on file, most recently archived first.
- **DEAD SITES** shows every link marked dead, newest listing first.

Both Archived and Dead Sites use the same page-by-page browsing as the
rest of the site so long lists don't load all at once.

---

## 2026-07-11 (new "Top Rated" Quick Link)

Added a fourth Quick Link, **TOP RATED**, right after NEW SITES in the
Categories sidebar. It lists links in order of how many times visitors
have recommended them (most recommended first), using the same
recommend counts added earlier this week. Uses the same page-by-page
browsing as the other Quick Links.

---

## 2026-07-11 (deleting a user account is now a proper soft delete)

The admin Manage Users page could already add users, but "removing"
one only had a plain Deactivate/Reactivate toggle with no confirmation
step and no record of who did it. That's now replaced with a Delete /
Restore flow matching the one already used for links:

- Clicking **Delete** asks for confirmation first, explaining that the
  account is blocked from logging in but its record is kept (so past
  submissions/reviews the person made stay attributed to them) and can
  be restored later.
- Clicking **Restore** on a deleted account brings it back the same
  way.
- Every delete/restore is written to the Audit Log.
- An admin can no longer delete their own account by mistake.

This has to be a soft delete — permanently erasing a user row would
either fail or silently break the history of anything they ever
submitted or approved, since those records point back to the user's
account.

---

## 2026-07-11 (see a user's full submission history)

The Manage Users list now shows each user's ID number, and clicking a
username opens a new page listing every link/news submission that
person has ever made — pending, approved, and rejected — with dates
and, for rejected ones, the reason given. The existing Submission
Review Queue only ever showed submissions still waiting for review;
this new page is the first place to see a contributor's full track
record.

---

## 2026-07-11 (Submission Review Queue columns are now sortable)

The pending Submission Review Queue's Type, Action, Submitted By, and
Submitted (date) column headers are now clickable, sorting the list
by that column — click again to flip between oldest/newest,
A-to-Z/Z-to-A, and so on. Defaults to oldest submission first, same as
before.

---

## 2026-07-11 (links now checked for duplicates and dead URLs before saving)

When a link is submitted by a contributor, or added/edited directly by an
admin, it now has to pass two checks before it can be saved: the URL cannot
already be in use by another link or by another pending submission (this
catches near-identical addresses too, like http vs https, with vs without
"www.", and with vs without a trailing slash), and the URL has to actually
respond when checked live. Either problem stops the save with a clear error
message explaining why.

---

## 2026-07-11 (site search now covers the whole site)

The search box now searches much more than just links. A search now also looks
through news articles (including who submitted them), calendar events,
crowdfunding campaigns, online and print publications, repair/service
listings, shops & vendors, and the Top 10 list — not just the links directory.
Each type of match is shown in its own labeled group, and if a group has a lot
of matches they're split across pages you can click through. Searching again
within 15 seconds of a previous search now shows a short "please wait"
message instead of running another search right away.

---

## 2026-07-11 (advanced search form)

The "Advanced Search (coming soon)" text in the sidebar is now a working
link. It opens a new Advanced Search page that searches the same content
(links, news, calendar, crowdfunding, publications, repair/service, shops
& vendors, and Top 10) as the regular search box, but adds two extra
filters: you can limit the search to just the sections you check, and you
can limit it to items added within a date range. Leaving all sections
unchecked searches everything, same as before; leaving the date fields
blank applies no date limit. The advanced form shares the same 15-second
"please wait" limit as the regular search box, so switching between the
two doesn't let you search faster.

---

## 2026-07-12 (file repository)

Visitors can now download files directly from the site. A new "File
Repository" page lists every file an admin has uploaded — title, a short
description, the file size, and how many times it's been downloaded — in
one simple, unsorted list, the same look and feel as the News page. A
"FILES" link was added to the sidebar's quick-links list so visitors can
find it.

Only admins can add files, from a new "Files" screen in the admin area.
Adding a file means actually uploading it through the browser (there's no
option to just link to a file hosted somewhere else) — the file is copied
onto the server and can be replaced or taken down (marked inactive)
later without losing its download count history. Uploads are capped at
25MB and only a specific list of file types is allowed (things like zip,
lha, lzx, adf, dms, hdf, exe, txt, pdf, doc/docx, and common image
formats) — anything else is rejected before it's saved. Every uploaded
file is renamed to a random, unguessable name when it's stored, and the
folder they're stored in is locked down so the only way to actually get a
file is by clicking its "Download" link on the site, which is also what
counts the download.

The title and description of each file are now included when you use the
site's search box or the Advanced Search page, so files show up alongside
links, news, and everything else already covered by search. Files that an
admin marks inactive are hidden from the listing page, from search
results, and can no longer be downloaded, but stay on record so their
past download counts aren't lost.

---

## 2026-07-12 (file repository — live deploy)

Put the new File Repository onto the actual working site. The database
change (adding the new table that tracks uploaded files) was applied to
the live database, and the new public and admin pages were uploaded
alongside it. Verified live: the File Repository page loads, an
uploaded file downloads correctly and its download count goes up, and
the folder files are stored in cannot be browsed to directly.

---

## 2026-07-12 (link submission preview)

Contributors submitting a link now see a live preview of how it will
look once approved and published, right on the submission form — no
extra click or page reload needed, it updates automatically as they
type the name, URL, author, and description. This was one of the
still-open items from the User Submission Portal phase.

---

## 2026-07-12 (admin review: no edit)

Confirmed and documented that admins reviewing a submission can only
approve or reject it as the contributor submitted it — there was never
a way for an admin to change the wording before approving, and that's
staying intentional rather than being built out. Admins wanting a
different result reject the submission with a reason instead.

---

## 2026-07-12 (access control QA pass)

Tested the site's login permissions from every angle on the live test
site: signed out, logged in as a regular contributor, and logged in as
an admin. Every admin-only screen correctly turns away anyone who
isn't an admin, while the screens meant for regular contributors (link
and news submission, "My Links"/"My News"/"My Submissions", profile)
work correctly for both contributors and admins. No issues found.

---

## 2026-07-12 (submission email notifications)

Submitting a link or news post for review now sends an email so it
doesn't sit unnoticed in the queue. The email goes to
links@testamigasource.com and includes a note that this inbox may not
be actively monitored, so it's easy to spot if it needs to be checked
regularly or forwarded elsewhere.

---

## 2026-07-12 (email delivery fix — sender/recipient mailbox)

Real-world testing found that sending the notification email from
links@testamigasource.com to that same address was silently swallowed
by the mail server — no error, no bounce, it just never arrived. A
test send to an outside address (Gmail) confirmed the mail server and
login worked fine; the problem was specifically sending an address to
itself. The notification is now sent from a separate mailbox
(nobody@testamigasource.com) to links@testamigasource.com to avoid
that same-address issue.

---

## 2026-07-12 (forgot password)

Added a "Forgot your password?" link on the login page. Contributors and
admins who can't remember their password can now request a reset email
containing a one-time link; the link is valid for 60 minutes and can
only be used once. Requesting a reset always shows the same message
regardless of whether the email address is registered, so the feature
can't be used to check who has an account.
