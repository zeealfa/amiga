<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $result = change_password($myConnection, $_SESSION['user_id'], $current_password, $new_password, $confirm_password);
    if ($result['success']) {
        $success = true;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My Profile</title>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="70%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="22%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="75%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MY PROFILE</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:12px;">
							<span class="txt-2-black">

<?php if ($success): ?>
								<p class="txt-2-black" style="color:#229c22;"><b>Password updated.</b></p>
<?php endif; ?>
<?php if ($error): ?>
								<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

								<form method="post" action="profile.php">
								<table cellpadding="4" cellspacing="0">
									<tr>
										<td align="right"><b>Current Password:</b></td>
										<td><input type="password" name="current_password" style="width:180px;"></td>
									</tr>
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

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
