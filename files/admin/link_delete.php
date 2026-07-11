<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'delete';

if ($id <= 0) {
    header('Location: links.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, links_name, links_deleted_at FROM t_links WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$link) {
    header('Location: links.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore' && isset($_POST['confirm_restore'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_links SET links_deleted_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_audit($myConnection, 'link', $id, 'restore', $link['links_name'], $_SESSION['user_id']);
    $_SESSION['flash_message'] = 'Link restored';
    header('Location: links.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_links SET links_deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_audit($myConnection, 'link', $id, 'delete', $link['links_name'], $_SESSION['user_id']);
    $_SESSION['flash_message'] = 'Link deleted';
    header('Location: links.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete Link</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $action === 'restore' ? 'RESTORE LINK' : 'DELETE LINK'; ?></b></font>
						</td>
					</tr>
<?php if ($action === 'restore'): ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to restore <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
							</font>
							<br><br>
							<center>
								<form method="post" action="link_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
									<input type="hidden" name="action" value="restore">
									<input type="hidden" name="confirm_restore" value="1">
									<input type="submit" value="Confirm Restore" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="links.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php else: ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to delete <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
								This can be undone later via Show Deleted &rarr; Restore.
							</font>
							<br><br>
							<center>
								<form method="post" action="link_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="links.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php endif; ?>
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
