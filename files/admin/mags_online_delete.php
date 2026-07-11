<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header('Location: mags_online.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, online_name FROM t_mags_online WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$mag = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$mag) {
    header('Location: mags_online.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_mags_online WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_audit($myConnection, 'mags_online', $id, 'delete', $mag['online_name'], $_SESSION['user_id']);
    $_SESSION['flash_message'] = 'Publication deleted';
    header('Location: mags_online.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete Online Publication</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="60%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="25%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="72%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>DELETE ONLINE PUBLICATION</b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to delete <b><?php echo htmlspecialchars($mag['online_name']); ?></b>?
							</font>
							<br><br>
							<center>
								<form method="post" action="mags_online_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $mag['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="mags_online.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
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
