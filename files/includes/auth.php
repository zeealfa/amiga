<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Attempts to log the given identifier/password in against t_users.
// Returns an array: ['success' => bool, 'error' => string|null].
// On success, sets $_SESSION['user_id'] and $_SESSION['role'] and
// regenerates the session id.
function attempt_login($myConnection, $identifier, $password)
{
    $generic_error = 'Invalid username/email or password';

    $stmt = mysqli_prepare(
        $myConnection,
        "SELECT id, username, password_hash, role, failed_login_attempts, locked_until, must_change_password
         FROM t_users
         WHERE (username = ? OR email = ?) AND status = 'active'"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        return ['success' => false, 'error' => $generic_error];
    }

    if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        return [
            'success' => false,
            'error' => 'Account temporarily locked due to too many failed attempts. Try again in a few minutes.',
        ];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int) $user['failed_login_attempts'] + 1;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_users SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?"
            );
            $lockout_minutes = LOGIN_LOCKOUT_MINUTES;
            mysqli_stmt_bind_param($stmt, 'iii', $attempts, $lockout_minutes, $user['id']);
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_users SET failed_login_attempts = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ii', $attempts, $user['id']);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return ['success' => false, 'error' => $generic_error];
    }

    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

    return ['success' => true, 'error' => null];
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function change_password($myConnection, $user_id, $current_password, $new_password, $confirm_password)
{
    $stmt = mysqli_prepare($myConnection, "SELECT password_hash FROM t_users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($current_password, $row['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    if (strlen($new_password) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    if ($new_password !== $confirm_password) {
        return ['success' => false, 'error' => 'New password and confirmation do not match.'];
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET password_hash = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_hash, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'error' => null];
}

function require_admin()
{
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

// Generates a reset token for the given email (if a matching active account
// exists) and emails it. Always returns success — the caller shows the same
// generic message either way, so this can't be used to enumerate accounts.
function request_password_reset($myConnection, $email)
{
    $stmt = mysqli_prepare($myConnection, "SELECT id, username FROM t_users WHERE email = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_users SET reset_token_hash = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?"
        );
        $minutes = PASSWORD_RESET_TOKEN_MINUTES;
        mysqli_stmt_bind_param($stmt, 'sii', $token_hash, $minutes, $user['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        require_once __DIR__ . '/mailer.php';
        $reset_url = 'https://' . ($_SERVER['SERVER_NAME'] ?? 'testamigasource.com') . '/admin/password_reset.php?token=' . $token;
        $subject = 'AmigaSource: password reset request';
        $body = "Hi {$user['username']},\n\n"
            . "A password reset was requested for your AmigaSource account. "
            . "If this was you, click the link below to set a new password. "
            . "This link expires in {$minutes} minutes.\n\n"
            . $reset_url . "\n\n"
            . "If you didn't request this, you can safely ignore this email — "
            . "your password will not be changed.\n";

        $mail_result = smtp_send_mail($email, $subject, $body);
        if (!$mail_result['success']) {
            error_log('request_password_reset mail failed: ' . $mail_result['error']);
        }
    }

    return ['success' => true, 'error' => null];
}

// Looks up an unexpired reset token and returns the matching user row, or
// null if the token is invalid/expired.
function verify_reset_token($myConnection, $token)
{
    $token_hash = hash('sha256', $token);

    $stmt = mysqli_prepare(
        $myConnection,
        "SELECT id, username FROM t_users
         WHERE reset_token_hash = ? AND reset_token_expires > NOW() AND status = 'active'"
    );
    mysqli_stmt_bind_param($stmt, 's', $token_hash);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $user ?: null;
}

function complete_password_reset($myConnection, $token, $new_password, $confirm_password)
{
    $user = verify_reset_token($myConnection, $token);
    if (!$user) {
        return ['success' => false, 'error' => 'This reset link is invalid or has expired.'];
    }

    if (strlen($new_password) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    if ($new_password !== $confirm_password) {
        return ['success' => false, 'error' => 'New password and confirmation do not match.'];
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_users SET password_hash = ?, reset_token_hash = NULL, reset_token_expires = NULL,
         failed_login_attempts = 0, locked_until = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $new_hash, $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'error' => null];
}
