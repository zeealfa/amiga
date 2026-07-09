<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'links_name' => '',
    'links_url' => '',
    'links_author' => '',
    'links_email' => '',
    'links_desc' => '',
    'links_cats' => [],
    'links_date_added' => date('Y-m-d'),
    'links_active' => true,
    'links_dead' => false,
    'links_verified' => false,
    'links_recommended' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['links_name'] = trim($_POST['links_name'] ?? '');
    $values['links_url'] = trim($_POST['links_url'] ?? '');
    $values['links_author'] = trim($_POST['links_author'] ?? '');
    $values['links_email'] = trim($_POST['links_email'] ?? '');
    $values['links_desc'] = trim($_POST['links_desc'] ?? '');
    $values['links_cats'] = array_map('intval', $_POST['links_cats'] ?? []);
    $values['links_date_added'] = trim($_POST['links_date_added'] ?? date('Y-m-d'));
    $values['links_active'] = isset($_POST['links_active']);
    $values['links_dead'] = isset($_POST['links_dead']);
    $values['links_verified'] = isset($_POST['links_verified']);
    $values['links_recommended'] = isset($_POST['links_recommended']);

    if ($values['links_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['links_url'] === '') {
        $errors[] = 'URL is required.';
    } elseif (!filter_var($values['links_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL is not a well-formed URL.';
    }
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $_SESSION['link_preview_data'] = array_merge($values, ['id' => $id]);
        header('Location: link_preview.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: links.php');
        exit;
    }

    $values['links_name'] = $row['links_name'];
    $values['links_url'] = $row['links_url'];
    $values['links_author'] = $row['links_author'];
    $values['links_email'] = $row['links_email'];
    $values['links_desc'] = $row['links_desc'];
    $values['links_cats'] = array_values(array_filter([
        $row['links_cat_1'], $row['links_cat_2'], $row['links_cat_3'], $row['links_cat_4'], $row['links_cat_5'],
    ]));
    $values['links_date_added'] = $row['links_date_added'];
    $values['links_active'] = (bool) $row['links_active'];
    $values['links_dead'] = (bool) $row['links_dead'];
    $values['links_verified'] = (bool) $row['links_verified'];
    $values['links_recommended'] = (bool) $row['links_recommended'];
}

$category_tree = get_category_tree($myConnection);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Link' : 'Add Link'; ?></title>
<link rel="stylesheet" href="../style.css">
<script>
function enforceCategoryLimit() {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    var checked = document.querySelectorAll('input[name="links_cats[]"]:checked').length;
    boxes.forEach(function (box) {
        if (!box.checked) {
            box.disabled = checked >= 5;
        }
    });
}
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    boxes.forEach(function (box) {
        box.addEventListener('change', enforceCategoryLimit);
    });
    enforceCategoryLimit();
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
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT LINK' : 'ADD LINK'; ?></b></span>
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
							<form method="post" action="link_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="20%"><b>Name:</b></td>
										<td><input type="text" name="links_name" value="<?php echo htmlspecialchars($values['links_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Author:</b></td>
										<td><input type="text" name="links_author" value="<?php echo htmlspecialchars($values['links_author']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Email:</b></td>
										<td><input type="text" name="links_email" value="<?php echo htmlspecialchars($values['links_email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="links_desc" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['links_desc']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Categories (up to 5):</b></td>
										<td>
<?php foreach ($category_tree as $main): ?>
											<div><b><?php echo htmlspecialchars($main['title']); ?></b></div>
<?php foreach ($main['subs'] as $sub_id => $sub_title): ?>
											<label><input type="checkbox" name="links_cats[]" value="<?php echo (int) $sub_id; ?>" <?php echo in_array($sub_id, $values['links_cats']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($sub_title); ?></label><br>
<?php endforeach; ?>
<?php endforeach; ?>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Date Added:</b></td>
										<td><input type="date" name="links_date_added" value="<?php echo htmlspecialchars($values['links_date_added']); ?>"></td>
									</tr>
									<tr>
										<td align="right"><b>Status:</b></td>
										<td>
											<label><input type="checkbox" name="links_active" <?php echo $values['links_active'] ? 'checked' : ''; ?>> Active</label>
											<label><input type="checkbox" name="links_dead" <?php echo $values['links_dead'] ? 'checked' : ''; ?>> Dead</label>
											<label><input type="checkbox" name="links_verified" <?php echo $values['links_verified'] ? 'checked' : ''; ?>> Verified</label>
											<label><input type="checkbox" name="links_recommended" <?php echo $values['links_recommended'] ? 'checked' : ''; ?>> Recommended</label>
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
