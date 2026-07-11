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
    'cal_name' => '',
    'cal_url' => '',
    'cal_date_start' => '',
    'cal_date_end' => '',
    'cal_location' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['cal_name'] = strip_tags(trim($_POST['cal_name'] ?? ''));
    $values['cal_url'] = strip_tags(trim($_POST['cal_url'] ?? ''));
    $values['cal_date_start'] = trim($_POST['cal_date_start'] ?? '');
    $values['cal_date_end'] = trim($_POST['cal_date_end'] ?? '');
    $values['cal_location'] = strip_tags(trim($_POST['cal_location'] ?? ''));

    if ($values['cal_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['cal_date_start'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['cal_date_start'])) {
        $errors[] = 'Start date is required (YYYY-MM-DD).';
    }
    if ($values['cal_date_end'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['cal_date_end'])) {
        $errors[] = 'End date is required (YYYY-MM-DD).';
    }
    if (empty($errors) && $values['cal_date_end'] < $values['cal_date_start']) {
        $errors[] = 'End date cannot be before start date.';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_cal SET cal_name=?, cal_url=?, cal_date_start=?, cal_date_end=?, cal_location=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssi',
                $values['cal_name'], $values['cal_url'], $values['cal_date_start'],
                $values['cal_date_end'], $values['cal_location'], $id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $event_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_cal (cal_name, cal_url, cal_date_start, cal_date_end, cal_location, cal_v_sub) VALUES (?, ?, ?, ?, ?, 0)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssss',
                $values['cal_name'], $values['cal_url'], $values['cal_date_start'],
                $values['cal_date_end'], $values['cal_location']
            );
            mysqli_stmt_execute($stmt);
            $event_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'calendar', $event_id, $is_edit ? 'edit' : 'add', $values['cal_name'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'Event updated' : 'Event added';
        header('Location: calendar.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_cal WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: calendar.php');
        exit;
    }

    $values['cal_name'] = $row['cal_name'];
    $values['cal_url'] = $row['cal_url'];
    $values['cal_date_start'] = $row['cal_date_start'];
    $values['cal_date_end'] = $row['cal_date_end'];
    $values['cal_location'] = $row['cal_location'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Calendar Event' : 'Add Calendar Event'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT CALENDAR EVENT' : 'ADD CALENDAR EVENT'; ?></b></font>
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
							<form method="post" action="calendar_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Name:</b></td>
										<td><input type="text" name="cal_name" value="<?php echo htmlspecialchars($values['cal_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="cal_url" value="<?php echo htmlspecialchars($values['cal_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Location:</b></td>
										<td><input type="text" name="cal_location" value="<?php echo htmlspecialchars($values['cal_location']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Start Date:</b></td>
										<td><input type="date" name="cal_date_start" value="<?php echo htmlspecialchars($values['cal_date_start']); ?>"></td>
									</tr>
									<tr>
										<td align="right"><b>End Date:</b></td>
										<td><input type="date" name="cal_date_end" value="<?php echo htmlspecialchars($values['cal_date_end']); ?>"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="calendar.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
