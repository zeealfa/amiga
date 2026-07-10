<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_hash, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['must_change_password'] = false;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Change Your Password</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<br>

<center>
<table width="50%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>YOU MUST CHANGE YOUR PASSWORD</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:12px;">
							<span class="txt-2-black">
								<p>An administrator set a password for your account. Please choose a new password before continuing.</p>

<?php if ($error): ?>
								<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

								<form method="post" action="force_password_change.php">
								<table cellpadding="4" cellspacing="0">
									<tr>
										<td align="right"><b>New Password:</b></td>
										<td><input type="password" name="new_password" style="width:180px;"></td>
									</tr>
									<tr>
										<td align="right"><b>Confirm New Password:</b></td>
										<td><input type="password" name="confirm_password" style="width:180px;"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Change Password" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
										</td>
									</tr>
								</table>
								</form>
							</span>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

</body>
</html>
