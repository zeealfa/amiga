<?php
// Run locally once via XAMPP to generate the bootstrap admin's password + hash.
// Never deploy this file's output (the plaintext password) anywhere — it is
// printed to the terminal once and then must be hand-delivered to the user.

$plaintext = bin2hex(random_bytes(9)); // 18-char random password
$hash = password_hash($plaintext, PASSWORD_BCRYPT);

echo "Plaintext password (give this to the user, do not save it anywhere): $plaintext\n";
echo "\nSeed SQL (safe to run/commit — contains only the hash, not the plaintext):\n\n";

$escapedHash = addslashes($hash);
echo "INSERT INTO t_users (username, email, password_hash, role, status) VALUES ('scottp', 'scottp@amigasource.com', '$escapedHash', 'admin', 'active');\n";
