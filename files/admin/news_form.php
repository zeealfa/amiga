<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'news_date' => date('Y-m-d'),
    'news_story' => '',
    'news_active' => true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['news_date'] = trim($_POST['news_date'] ?? date('Y-m-d'));
    $values['news_story'] = trim($_POST['news_story'] ?? '');
    $values['news_active'] = isset($_POST['news_active']);

    if ($values['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['news_date'])) {
        $errors[] = 'Date is required and must be a valid date.';
    }
    if (trim(strip_tags($values['news_story'])) === '') {
        $errors[] = 'Story is required.';
    }

    if (empty($errors)) {
        $_SESSION['news_preview_data'] = array_merge($values, ['id' => $id]);
        header('Location: news_preview.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: news.php');
        exit;
    }

    $values['news_date'] = $row['news_date'];
    $values['news_story'] = $row['news_story'];
    $values['news_active'] = (bool) $row['news_active'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit News Post' : 'Add News Post'; ?></title>
<style><?php include __DIR__ . '/../style.css'; ?></style>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.tinymce) {
        tinymce.init({
            selector: '#news_story',
            license_key: 'gpl',
            menubar: false,
            plugins: 'link lists table',
            toolbar: 'bold italic underline | bullist numlist | link table | removeformat'
        });
    }

    var form = document.getElementById('news_form');
    form.addEventListener('submit', function () {
        if (window.tinymce) {
            tinymce.triggerSave();
        }
    });
});
</script>
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="18%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'ADD NEWS POST'; ?></b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
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
							<form method="post" action="news_form.php" id="news_form">
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
										<td><textarea id="news_story" name="news_story" rows="12" style="width:100%;"><?php echo htmlspecialchars($values['news_story']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Status:</b></td>
										<td>
											<label><input type="checkbox" name="news_active" <?php echo $values['news_active'] ? 'checked' : ''; ?>> Published</label>
										</td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Preview" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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
