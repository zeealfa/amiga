<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['news_preview_data'])) {
    header('Location: news_form.php');
    exit;
}

$data = $_SESSION['news_preview_data'];
$is_edit = !empty($data['id']);

// Re-validate server-side — never trust that news_form.php's validation
// was not bypassed by a direct POST to this page.
$errors = [];
if ($data['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['news_date'])) {
    $errors[] = 'Date is required and must be a valid date.';
}
if (trim(strip_tags($data['news_story'])) === '') {
    $errors[] = 'Story is required.';
}

if (!empty($errors)) {
    unset($_SESSION['news_preview_data']);
    header('Location: news_form.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    if ($is_edit) {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_news SET news_date=?, news_story=?, news_active=? WHERE id=?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssii',
            $data['news_date'], $data['news_story'], $data['news_active'], $data['id']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $flash = 'News post updated';
        log_audit($myConnection, 'news', (int) $data['id'], 'edit', $data['news_date'], $_SESSION['user_id']);
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_news (news_date, news_story, news_active) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $data['news_date'], $data['news_story'], $data['news_active']
        );
        mysqli_stmt_execute($stmt);
        $news_id = mysqli_insert_id($myConnection);
        mysqli_stmt_close($stmt);
        $flash = 'News post added';
        log_audit($myConnection, 'news', $news_id, 'add', $data['news_date'], $_SESSION['user_id']);
    }

    unset($_SESSION['news_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: news.php');
    exit;
}

// Build a mysqli_fetch_array-shaped row so table_content_news_sub.php (the
// exact public rendering include) can render the not-yet-saved data unmodified.
$row = [
    'news_date' => $data['news_date'],
    'news_story' => $data['news_story'],
];
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Preview News Post</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW NEWS POST</b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<?php include __DIR__ . '/../table_content_news_sub.php'; ?>
						</td>
					</tr>
					<tr>
						<td align="center" style="padding:12px;">
							<form method="post" action="news_form.php" style="display:inline;">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $data['id']; ?>">
<?php endif; ?>
								<input type="hidden" name="news_date" value="<?php echo htmlspecialchars($data['news_date']); ?>">
								<input type="hidden" name="news_story" value="<?php echo htmlspecialchars($data['news_story']); ?>">
<?php if ($data['news_active']): ?><input type="hidden" name="news_active" value="on"><?php endif; ?>
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="news_preview.php" style="display:inline;">
								<input type="hidden" name="confirm_save" value="1">
								<input type="submit" value="Save" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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
