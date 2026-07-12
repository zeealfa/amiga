<?php
// Local dev (XAMPP)
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'admin');
define('DB_PASS', 'Masukaja12');
define('DB_NAME', 'asdb');

// GoDaddy production values — swap the block above for this one when deploying,
// same pattern login_db.php used with its commented-out alternates.
// define('DB_HOST', 'localhost');
// define('DB_USER', '<production-user>');
// define('DB_PASS', '<production-pass>');
// define('DB_NAME', 'asdb');

define('LINKS_PER_PAGE', 25);   // was hardcoded in table_result_cat.php:14
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
define('ADMIN_NEWS_PER_PAGE', 20);  // admin news list page size (files/admin/news.php)
define('AUDIT_LOG_PER_PAGE', 30);   // admin audit log page size (files/admin/audit_log.php)
define('SEARCH_RESULTS_PER_PAGE', 10);  // per-section page size on the public search page (files/content_search_proc.php)

define('FILES_PER_PAGE', 10);  // public file repository listing page size (files/content_files.php)
define('FILE_REPO_MAX_BYTES', 25 * 1024 * 1024);  // 25MB upload cap
define('FILE_REPO_ALLOWED_EXTENSIONS', ['zip', 'lha', 'lzx', 'adf', 'dms', 'hdf', 'exe', 'txt', 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif']);
define('FILE_REPO_STORAGE_DIR', __DIR__ . '/../storage');  // files/storage — locked down by its own .htaccess, never served directly

define('LOGIN_MAX_ATTEMPTS', 5);      // Phase 03a: wrong-password count before lockout
define('LOGIN_LOCKOUT_MINUTES', 15);  // Phase 03a: lockout duration
