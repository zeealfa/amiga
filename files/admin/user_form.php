<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'username' => '',
    'email' => '',
    'role' => 'user',
    'status' => 'active',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $values['role'] = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $values['status'] = $is_edit && ($_POST['status'] ?? 'active') === 'removed' ? 'removed' : 'active';
    $values['password'] = $_POST['password'] ?? '';

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($values['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (!$is_edit && $values['password'] === '') {
        $errors[] = 'Password is required for a new user.';
    }
    if ($values['password'] !== '' && strlen($values['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($values['username'] !== '') {
        $exclude_id = $is_edit ? $id : 0;
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE username = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, 'si', $values['username'], $exclude_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That username is already taken.';
        }
        mysqli_stmt_close($stmt);
    }
    if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $exclude_id = $is_edit ? $id : 0;
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE email = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, 'si', $values['email'], $exclude_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That email is already registered.';
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($values['password'] !== '') {
                $new_hash = password_hash($values['password'], PASSWORD_BCRYPT);
                $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET username = ?, email = ?, role = ?, status = ?, password_hash = ?, must_change_password = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'sssssi', $values['username'], $values['email'], $values['role'], $values['status'], $new_hash, $id);
            } else {
                $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $values['username'], $values['email'], $values['role'], $values['status'], $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'User updated.';
        } else {
            $new_hash = password_hash($values['password'], PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($myConnection, "INSERT INTO t_users (username, email, password_hash, role, status, must_change_password) VALUES (?, ?, ?, ?, 'active', 1)");
            mysqli_stmt_bind_param($stmt, 'ssss', $values['username'], $values['email'], $new_hash, $values['role']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'User created.';
        }
        header('Location: users.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: users.php');
        exit;
    }

    $values['username'] = $row['username'];
    $values['email'] = $row['email'];
    $values['role'] = $row['role'];
    $values['status'] = $row['status'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit User' : 'Add User'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="70%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT USER' : 'ADD USER'; ?></b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
<?php if (!empty($errors)): ?>
							<div class="txt-2-black" style="color:#c70000;">
								<b>Please fix the following:</b>
								<ul>
<?php foreach ($errors as $error): ?>
									<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
								</ul>
							</div>
<?php endif; ?>
							<form method="post" action="user_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Username:</b></td>
										<td><input type="text" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Email:</b></td>
										<td><input type="text" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Role:</b></td>
										<td>
											<select name="role">
												<option value="user" <?php echo $values['role'] === 'user' ? 'selected' : ''; ?>>User</option>
												<option value="admin" <?php echo $values['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
											</select>
										</td>
									</tr>
<?php if ($is_edit): ?>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Status:</b></td>
										<td>
											<select name="status">
												<option value="active" <?php echo $values['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
												<option value="removed" <?php echo $values['status'] === 'removed' ? 'selected' : ''; ?>>Removed</option>
											</select>
										</td>
									</tr>
<?php endif; ?>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b><?php echo $is_edit ? 'Reset Password:' : 'Password:'; ?></b></td>
										<td><input type="password" name="password" style="width:180px;"> <?php if ($is_edit): ?><font class="txt-1" face="Verdana, sans-serif" size="1">(leave blank to keep current password)</font><?php endif; ?></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="<?php echo $is_edit ? 'Save' : 'Create User'; ?>" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
										</td>
									</tr>
								</table>
							</form>
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

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
