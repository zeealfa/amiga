<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'delete';

if ($id <= 0) {
    header('Location: news.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, news_date, news_deleted_at FROM t_news WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$news = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$news) {
    header('Location: news.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore' && isset($_POST['confirm_restore'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_deleted_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'News post restored';
    header('Location: news.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'News post deleted';
    header('Location: news.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete News Post</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="60%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="25%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="72%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $action === 'restore' ? 'RESTORE NEWS POST' : 'DELETE NEWS POST'; ?></b></span>
						</td>
					</tr>
<?php if ($action === 'restore'): ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to restore the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
							</span>
							<br><br>
							<center>
								<form method="post" action="news_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $news['id']; ?>">
									<input type="hidden" name="action" value="restore">
									<input type="hidden" name="confirm_restore" value="1">
									<input type="submit" value="Confirm Restore" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php else: ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
								This can be undone later via Show Deleted &rarr; Restore.
							</span>
							<br><br>
							<center>
								<form method="post" action="news_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $news['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
