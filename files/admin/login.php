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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = attempt_login($myConnection, $identifier, $password);
    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Login</title>
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
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>LOGIN</b></font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">

<?php if ($error): ?>
										<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

										<form method="post" action="login.php">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>Username or Email:</b></td>
												<td><input type="text" name="identifier" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Password:</b></td>
												<td><input type="password" name="password" style="width:180px;"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Log In" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
												</td>
											</tr>
										</table>
										</form>

									</font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="8">
							<tr>
								<td align="center" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
									<font class="txt-1" face="Verdana, sans-serif" size="1">
										One login for everyone — admins and users sign in here.<br>
										What you see next depends on your account's role.<br>
										Don't have an account? <a href="register.php">Register here</a>.
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
