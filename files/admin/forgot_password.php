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

$error = null;
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        request_password_reset($myConnection, $email);
        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Forgot Password</title>
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
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>FORGOT PASSWORD</b></font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">

<?php if ($submitted): ?>
										<p>If that email address matches an account, a password reset link has been sent. The link expires in <?php echo (int) PASSWORD_RESET_TOKEN_MINUTES; ?> minutes.</p>
										<p><a href="login.php">Back to Login</a></p>
<?php else: ?>
<?php if ($error): ?>
										<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>
										<p>Enter the email address associated with your account and we'll send you a link to reset your password.</p>
										<form method="post" action="forgot_password.php">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>Email:</b></td>
												<td><input type="email" name="email" style="width:200px;" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Send Reset Link" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
												</td>
											</tr>
										</table>
										</form>
<?php endif; ?>

									</font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="8">
							<tr>
								<td align="center" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
									<font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="login.php">Back to Login</a>
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
