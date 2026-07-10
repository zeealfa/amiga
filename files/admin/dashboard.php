<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';

$pending_count = 0;
$metrics = [];

if ($_SESSION['role'] === 'admin') {
    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE status = 'pending'");
    $pending_count = (int) mysqli_fetch_assoc($result)['c'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_links WHERE links_deleted_at IS NULL AND links_active = 1");
    $metrics['Active Links'] = (int) mysqli_fetch_assoc($result)['c'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_news WHERE news_deleted_at IS NULL AND news_active = 1");
    $metrics['Active News Items'] = (int) mysqli_fetch_assoc($result)['c'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_categories WHERE active = 1");
    $metrics['Active Categories'] = (int) mysqli_fetch_assoc($result)['c'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_users WHERE status = 'active'");
    $metrics['Active Users'] = (int) mysqli_fetch_assoc($result)['c'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_links WHERE links_deleted_at IS NULL AND links_dead = 1");
    $metrics['Dead Links'] = (int) mysqli_fetch_assoc($result)['c'];
} else {
    $stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM t_links WHERE submitted_by = ? AND links_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $metrics['Your Links'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM t_news WHERE submitted_by = ? AND news_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $metrics['Your News Items'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE submitted_by = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $metrics['Pending Submissions'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE submitted_by = ? AND status = 'approved'");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $metrics['Approved Submissions'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE submitted_by = ? AND status = 'rejected'");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $metrics['Rejected Submissions'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Dashboard</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="22%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="75%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>WELCOME, <?php echo strtoupper(htmlspecialchars($_SESSION['username'])); ?></b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<table width="100%" cellpadding="6" cellspacing="8">
								<tr>
<?php $col = 0; foreach ($metrics as $label => $value): ?>
<?php if ($col > 0 && $col % 3 === 0): ?>
								</tr>
								<tr>
<?php endif; ?>
									<td width="33%" align="center" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
										<table width="100%" cellpadding="6" cellspacing="0">
											<tr>
												<td align="center" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
													<font class="txt-4" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('blue'); ?>"><b><?php echo $value; ?></b></font><br>
													<font class="txt-1" face="Verdana, sans-serif" size="1" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($label); ?></font>
												</td>
											</tr>
										</table>
									</td>
<?php $col++; endforeach; ?>
								</tr>
							</table>
<?php if ($_SESSION['role'] === 'admin'): ?>
							<br>
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<a href="submissions.php"><b><?php echo $pending_count; ?> pending submission<?php echo $pending_count === 1 ? '' : 's'; ?> awaiting review</b></a>
							</font>
<?php endif; ?>
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
