<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$result = mysqli_prepare($myConnection, "SELECT * FROM t_submissions WHERE submitted_by = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($result, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($result);
$submissions = [];
$rows = mysqli_stmt_get_result($result);
while ($row = mysqli_fetch_assoc($rows)) {
    $submissions[] = $row;
}
mysqli_stmt_close($result);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

function submission_title($submission)
{
    if ($submission['type'] === 'link') {
        return htmlspecialchars($submission['links_name']);
    }
    return htmlspecialchars($submission['news_date']) . ' &mdash; ' . htmlspecialchars(mb_substr(strip_tags($submission['news_story']), 0, 60)) . '&hellip;';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My Submissions</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MY SUBMISSIONS</b></font>
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
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Type</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Action</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Item</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Submitted</b></font></td>
								</tr>
<?php if (empty($submissions)): ?>
								<tr><td colspan="5"><font class="txt-1" face="Verdana, sans-serif" size="1">You haven't submitted anything yet.</font></td></tr>
<?php endif; ?>
<?php foreach ($submissions as $submission): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars(ucfirst($submission['type'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['action'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo submission_title($submission); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['status'])); ?>
<?php if ($submission['status'] === 'rejected' && $submission['reject_reason']): ?>
										<br><span style="color:#c70000;"><?php echo htmlspecialchars($submission['reject_reason']); ?></span>
<?php endif; ?>
									</font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($submission['created_at']); ?></font></td>
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
