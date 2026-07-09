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

define('LOGIN_MAX_ATTEMPTS', 5);      // Phase 03a: wrong-password count before lockout
define('LOGIN_LOCKOUT_MINUTES', 15);  // Phase 03a: lockout duration
