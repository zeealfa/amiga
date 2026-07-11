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
    'cfund_name' => '',
    'cfund_url' => '',
    'cfund_date_start' => '',
    'cfund_date_end' => '',
    'cfund_active' => true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['cfund_name'] = strip_tags(trim($_POST['cfund_name'] ?? ''));
    $values['cfund_url'] = strip_tags(trim($_POST['cfund_url'] ?? ''));
    $values['cfund_date_start'] = trim($_POST['cfund_date_start'] ?? '');
    $values['cfund_date_end'] = trim($_POST['cfund_date_end'] ?? '');
    $values['cfund_active'] = isset($_POST['cfund_active']);

    if ($values['cfund_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['cfund_date_start'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['cfund_date_start'])) {
        $errors[] = 'Start date is required (YYYY-MM-DD).';
    }
    if ($values['cfund_date_end'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['cfund_date_end'])) {
        $errors[] = 'End date is required (YYYY-MM-DD).';
    }
    if (empty($errors) && $values['cfund_date_end'] < $values['cfund_date_start']) {
        $errors[] = 'End date cannot be before start date.';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_cfund SET cfund_name=?, cfund_url=?, cfund_date_start=?, cfund_date_end=?, cfund_active=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssssii',
                $values['cfund_name'], $values['cfund_url'], $values['cfund_date_start'],
                $values['cfund_date_end'], $values['cfund_active'], $id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $campaign_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_cfund (cfund_name, cfund_url, cfund_date_start, cfund_date_end, cfund_active, cfund_v_sub) VALUES (?, ?, ?, ?, ?, 0)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssssi',
                $values['cfund_name'], $values['cfund_url'], $values['cfund_date_start'],
                $values['cfund_date_end'], $values['cfund_active']
            );
            mysqli_stmt_execute($stmt);
            $campaign_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'crowdfunding', $campaign_id, $is_edit ? 'edit' : 'add', $values['cfund_name'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'Campaign updated' : 'Campaign added';
        header('Location: crowdfunding.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_cfund WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: crowdfunding.php');
        exit;
    }

    $values['cfund_name'] = $row['cfund_name'];
    $values['cfund_url'] = $row['cfund_url'];
    $values['cfund_date_start'] = $row['cfund_date_start'];
    $values['cfund_date_end'] = $row['cfund_date_end'];
    $values['cfund_active'] = (bool) $row['cfund_active'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Crowdfunding Campaign' : 'Add Crowdfunding Campaign'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT CROWDFUNDING CAMPAIGN' : 'ADD CROWDFUNDING CAMPAIGN'; ?></b></font>
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
							<form method="post" action="crowdfunding_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Name:</b></td>
										<td><input type="text" name="cfund_name" value="<?php echo htmlspecialchars($values['cfund_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="cfund_url" value="<?php echo htmlspecialchars($values['cfund_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Start Date:</b></td>
										<td><input type="date" name="cfund_date_start" value="<?php echo htmlspecialchars($values['cfund_date_start']); ?>"></td>
									</tr>
									<tr>
										<td align="right"><b>End Date:</b></td>
										<td><input type="date" name="cfund_date_end" value="<?php echo htmlspecialchars($values['cfund_date_end']); ?>"></td>
									</tr>
									<tr>
										<td align="right"><b>Status:</b></td>
										<td><label><input type="checkbox" name="cfund_active" <?php echo $values['cfund_active'] ? 'checked' : ''; ?>> Active (shown in public sidebar)</label></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="crowdfunding.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
