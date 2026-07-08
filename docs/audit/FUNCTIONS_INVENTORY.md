# Function Inventory

Searched every `.php` file under `files/` for `function` declarations:
`grep -rn "function\s*[A-Za-z_]" files --include=*.php`

**Result: zero user-defined functions exist anywhere in the codebase.**

Every page is flat procedural PHP — the same DB-query-and-loop pattern
(`mysqli_query` → `mysqli_fetch_array` → inline HTML) is copy-pasted across
roughly 15 `sidebar_*.php` / `table_*.php` files instead of being extracted
into a shared helper. Expected to be addressed in Phase 02 (Code Cleanup &
Refactoring), not fixed as part of this audit.
