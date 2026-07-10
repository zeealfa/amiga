<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'news_date' => date('Y-m-d'),
    'news_story' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['news_date'] = trim($_POST['news_date'] ?? date('Y-m-d'));
    $values['news_story'] = trim($_POST['news_story'] ?? '');

    if ($is_edit) {
        $check_stmt = mysqli_prepare($myConnection, "SELECT id FROM t_news WHERE id = ? AND submitted_by = ? AND news_deleted_at IS NULL");
        mysqli_stmt_bind_param($check_stmt, 'ii', $id, $_SESSION['user_id']);
        mysqli_stmt_execute($check_stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
            header('Location: my_news.php');
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }

    if ($values['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['news_date'])) {
        $errors[] = 'Date is required and must be a valid date.';
    }
    if (trim(strip_tags($values['news_story'])) === '') {
        $errors[] = 'Story is required.';
    }

    if (empty($errors)) {
        $target_id = $is_edit ? $id : null;
        $action = $is_edit ? 'edit' : 'new';

        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_submissions (type, action, target_id, submitted_by, news_date, news_story, status)
             VALUES ('news', ?, ?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'siiss',
            $action, $target_id, $_SESSION['user_id'],
            $values['news_date'], $values['news_story']
        );
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($success) {
            $_SESSION['flash_message'] = $is_edit ? 'News edit submitted for review.' : 'News post submitted for review.';
            header('Location: my_submissions.php');
            exit;
        }

        $errors[] = 'Submission failed, please try again.';
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND submitted_by = ? AND news_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: my_news.php');
        exit;
    }

    $values['news_date'] = $row['news_date'];
    $values['news_story'] = $row['news_story'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit News Post' : 'Submit News Post'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'SUBMIT NEWS POST'; ?></b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="my_news.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to My News</a></font></td>
								</tr>
							</table>
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
							<p><font class="txt-1" face="Verdana, sans-serif" size="1">Submissions are reviewed by an admin before they go live.</font></p>
							<form method="post" action="news_submit.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Date:</b></td>
										<td><input type="date" name="news_date" value="<?php echo htmlspecialchars($values['news_date']); ?>"></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Story:</b></td>
										<td><textarea name="news_story" rows="12" style="width:100%;"><?php echo htmlspecialchars($values['news_story']); ?></textarea></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Submit for Review" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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
