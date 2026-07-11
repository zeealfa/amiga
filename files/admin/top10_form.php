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
    'is_divider' => false,
    'top10_name' => '',
    'top10_url' => '',
    'top10_order' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['is_divider'] = isset($_POST['is_divider']);
    $values['top10_name'] = strip_tags(trim($_POST['top10_name'] ?? ''));
    $values['top10_url'] = strip_tags(trim($_POST['top10_url'] ?? ''));
    $values['top10_order'] = trim($_POST['top10_order'] ?? '');

    if (!$values['is_divider'] && $values['top10_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if (!$values['is_divider'] && $values['top10_url'] === '') {
        $errors[] = 'URL is required.';
    }
    if ($values['top10_order'] === '' || !ctype_digit($values['top10_order'])) {
        $errors[] = 'Order must be a whole number.';
    }

    if (empty($errors)) {
        $save_name = $values['is_divider'] ? '<hr>' : $values['top10_name'];
        $save_url = $values['is_divider'] ? '' : $values['top10_url'];
        $save_order = (int) $values['top10_order'];

        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_top10 SET top10_name=?, top10_url=?, top10_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'ssii', $save_name, $save_url, $save_order, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $entry_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_top10 (top10_name, top10_url, top10_order) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $save_name, $save_url, $save_order);
            mysqli_stmt_execute($stmt);
            $entry_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'top10', $entry_id, $is_edit ? 'edit' : 'add', $save_name, $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'Entry updated' : 'Entry added';
        header('Location: top10.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_top10 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: top10.php');
        exit;
    }

    $values['is_divider'] = $row['top10_name'] === '<hr>';
    $values['top10_name'] = $values['is_divider'] ? '' : $row['top10_name'];
    $values['top10_url'] = $row['top10_url'];
    $values['top10_order'] = $row['top10_order'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Top 10 Entry' : 'Add Top 10 Entry'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
<tr>
	<td width="20%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="77%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT TOP 10 ENTRY' : 'ADD TOP 10 ENTRY'; ?></b></font>
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
							<form method="post" action="top10_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Divider line:</b></td>
										<td><input type="checkbox" name="is_divider" value="1" <?php echo $values['is_divider'] ? 'checked' : ''; ?>> (a horizontal line separator, no name/URL needed)</td>
									</tr>
									<tr>
										<td align="right"><b>Name:</b></td>
										<td><input type="text" name="top10_name" value="<?php echo htmlspecialchars($values['top10_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="top10_url" value="<?php echo htmlspecialchars($values['top10_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Order:</b></td>
										<td><input type="text" name="top10_order" value="<?php echo htmlspecialchars((string) $values['top10_order']); ?>" size="5"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="top10.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
