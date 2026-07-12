<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = null;
$success = false;
$token_valid = $token !== '' && verify_reset_token($myConnection, $token) !== null;

if (!$token_valid) {
    $error = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $result = complete_password_reset($myConnection, $token, $new_password, $confirm_password);
    if ($result['success']) {
        $success = true;
        $token_valid = false;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Reset Password</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="#">
				<img src="../web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>
			</tr>
			<tr>
				<td align="right" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="16" cellspacing="8">
					<font class="txt-5" face="Verdana, sans-serif" size="5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</font>
				</td>
			</tr>
		</td>
	</tr>
</table>

<br><br>

<center>
<table cellpadding="1" cellspacing="0" width="360" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%" cellspacing="0" cellpadding="12">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>RESET PASSWORD</b></font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">

<?php if ($success): ?>
										<p>Your password has been reset. You can now log in with your new password.</p>
										<p><a href="login.php">Go to Login</a></p>
<?php elseif (!$token_valid): ?>
										<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
										<p><a href="forgot_password.php">Request a new reset link</a></p>
<?php else: ?>
<?php if ($error): ?>
										<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>
										<form method="post" action="password_reset.php">
										<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>New Password:</b></td>
												<td><input type="password" name="new_password" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Confirm Password:</b></td>
												<td><input type="password" name="confirm_password" style="width:180px;"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Reset Password" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
												</td>
											</tr>
										</table>
										</form>
<?php endif; ?>

									</font>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</center>

<br><br>

</body>
</html>
