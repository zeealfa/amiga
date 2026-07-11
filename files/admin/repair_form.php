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
    'repair_name' => '',
    'repair_url' => '',
    'repair_country' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['repair_name'] = strip_tags(trim($_POST['repair_name'] ?? ''));
    $values['repair_url'] = strip_tags(trim($_POST['repair_url'] ?? ''));
    $values['repair_country'] = strip_tags(trim($_POST['repair_country'] ?? ''));

    if ($values['repair_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['repair_url'] === '') {
        $errors[] = 'URL is required.';
    }
    if ($values['repair_country'] === '') {
        $errors[] = 'Country is required.';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_repair SET repair_name=?, repair_url=?, repair_country=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssi',
                $values['repair_name'], $values['repair_url'], $values['repair_country'], $id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $repair_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_repair (repair_name, repair_url, repair_country) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sss',
                $values['repair_name'], $values['repair_url'], $values['repair_country']
            );
            mysqli_stmt_execute($stmt);
            $repair_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'repair', $repair_id, $is_edit ? 'edit' : 'add', $values['repair_name'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'Entry updated' : 'Entry added';
        header('Location: repair.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_repair WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: repair.php');
        exit;
    }

    $values['repair_name'] = $row['repair_name'];
    $values['repair_url'] = $row['repair_url'];
    $values['repair_country'] = $row['repair_country'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Repair Entry' : 'Add Repair Entry'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT REPAIR ENTRY' : 'ADD REPAIR ENTRY'; ?></b></font>
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
							<form method="post" action="repair_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Name:</b></td>
										<td><input type="text" name="repair_name" value="<?php echo htmlspecialchars($values['repair_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Country:</b></td>
										<td><input type="text" name="repair_country" value="<?php echo htmlspecialchars($values['repair_country']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="repair_url" value="<?php echo htmlspecialchars($values['repair_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="repair.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
