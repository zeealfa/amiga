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

$errors = [];
$values = [
    'username' => '',
    'email' => '',
];
$registered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $values['username']);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That username is already taken.';
        }
        mysqli_stmt_close($stmt);
    }
    if (empty($errors)) {
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $values['email']);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That email address is already registered.';
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'user', 'pending')"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $values['username'], $values['email'], $hash);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($success) {
            $registered = true;
        } else {
            $errors[] = 'Registration failed, please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Register</title>
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
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>REGISTER</b></font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">

<?php if ($registered): ?>
										<p><b>Thanks for registering!</b><br>
										Your account is pending admin approval. You'll be able to log in once an admin approves it.</p>
										<p><a href="login.php">Back to Login</a></p>
<?php else: ?>
<?php if (!empty($errors)): ?>
										<p class="txt-2-black" style="color:#c70000;"><b>Please fix the following:</b>
										<ul>
<?php foreach ($errors as $error): ?>
											<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
										</ul></p>
<?php endif; ?>
										<form method="post" action="register.php">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>Username:</b></td>
												<td><input type="text" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Email:</b></td>
												<td><input type="text" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Password:</b></td>
												<td><input type="password" name="password" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Confirm Password:</b></td>
												<td><input type="password" name="confirm_password" style="width:180px;"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Register" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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
