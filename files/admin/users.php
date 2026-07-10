<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$result = mysqli_query($myConnection, "SELECT * FROM t_users ORDER BY username ASC");
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Users</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE USERS</b></font>
						</td>
					</tr>
<?php if ($flash): ?>
					<tr>
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;" align="right">
							<a href="user_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add User</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Username</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Email</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Role</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php foreach ($users as $user): ?>
<?php $is_locked = $user['locked_until'] !== null && strtotime($user['locked_until']) > time(); ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($user['username']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($user['email']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($user['status'])); ?><?php if ($is_locked): ?> &mdash; Locked until <?php echo htmlspecialchars($user['locked_until']); ?><?php endif; ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="user_form.php?id=<?php echo (int) $user['id']; ?>">Edit</a> |
<?php if ($user['status'] === 'pending'): ?>
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="approve">
											<input type="submit" value="Approve" class="txt-1">
										</form>
<?php else: ?>
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="toggle_status">
											<input type="submit" value="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?>" class="txt-1">
										</form>
<?php endif; ?>
<?php if ($is_locked): ?>
										|
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="unlock">
											<input type="submit" value="Unlock" class="txt-1">
										</form>
<?php endif; ?>
									</font></td>
								</tr>
<?php endforeach; ?>
							</table>
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
