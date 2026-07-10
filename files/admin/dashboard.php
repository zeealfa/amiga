<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';

$pending_count = 0;
if ($_SESSION['role'] === 'admin') {
    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE status = 'pending'");
    $pending_count = (int) mysqli_fetch_assoc($result)['c'];
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
<table width="70%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">You are logged in. Full dashboard content ships in Phase 03b.</font>
<?php if ($_SESSION['role'] === 'admin'): ?>
							<br><br>
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<a href="submissions.php"><b><?php echo $pending_count; ?> pending submission<?php echo $pending_count === 1 ? '' : 's'; ?></b></a>
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
