<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: users.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, username FROM t_users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: users.php');
    exit;
}

$stmt = mysqli_prepare(
    $myConnection,
    "SELECT * FROM t_submissions WHERE submitted_by = ? ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$submissions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $submissions[] = $row;
}
mysqli_stmt_close($stmt);

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
<title>AmigaSource.com - Submissions by <?php echo htmlspecialchars($user['username']); ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>SUBMISSIONS BY <?php echo htmlspecialchars(strtoupper($user['username'])); ?></b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;">
							<font class="txt-1" face="Verdana, sans-serif" size="1"><a href="users.php">&laquo; Back to Manage Users</a></font>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Type</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Action</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Item</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Submitted</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Reviewed</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($submissions)): ?>
								<tr><td colspan="7"><font class="txt-1" face="Verdana, sans-serif" size="1">No submissions from this user.</font></td></tr>
<?php endif; ?>
<?php foreach ($submissions as $submission): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars(ucfirst($submission['type'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['action'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo submission_title($submission); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['status'])); ?><?php if ($submission['status'] === 'rejected' && $submission['reject_reason'] !== null && $submission['reject_reason'] !== ''): ?> &mdash; <?php echo htmlspecialchars($submission['reject_reason']); ?><?php endif; ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($submission['created_at']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo $submission['reviewed_at'] !== null ? htmlspecialchars($submission['reviewed_at']) : '&mdash;'; ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php if ($submission['status'] === 'pending'): ?><a href="submission_review.php?id=<?php echo (int) $submission['id']; ?>">Review</a><?php else: ?>&mdash;<?php endif; ?></font></td>
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
