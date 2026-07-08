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
        "SELECT id, username, password_hash, role, failed_login_attempts, locked_until
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

    return ['success' => true, 'error' => null];
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin()
{
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}
