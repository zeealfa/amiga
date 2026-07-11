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
    'online_name' => '',
    'online_url' => '',
    'online_issue' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['online_name'] = strip_tags(trim($_POST['online_name'] ?? ''));
    $values['online_url'] = strip_tags(trim($_POST['online_url'] ?? ''));
    $values['online_issue'] = trim($_POST['online_issue'] ?? '');

    if ($values['online_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['online_url'] === '') {
        $errors[] = 'URL is required.';
    }
    if ($values['online_issue'] === '' || !ctype_digit($values['online_issue'])) {
        $errors[] = 'Issue # must be a whole number.';
    }

    if (empty($errors)) {
        $issue = (int) $values['online_issue'];
        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_mags_online SET online_name=?, online_url=?, online_issue=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'ssii', $values['online_name'], $values['online_url'], $issue, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $mag_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_mags_online (online_name, online_url, online_issue) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $values['online_name'], $values['online_url'], $issue);
            mysqli_stmt_execute($stmt);
            $mag_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'mags_online', $mag_id, $is_edit ? 'edit' : 'add', $values['online_name'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'Publication updated' : 'Publication added';
        header('Location: mags_online.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_mags_online WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: mags_online.php');
        exit;
    }

    $values['online_name'] = $row['online_name'];
    $values['online_url'] = $row['online_url'];
    $values['online_issue'] = $row['online_issue'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Online Publication' : 'Add Online Publication'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT ONLINE PUBLICATION' : 'ADD ONLINE PUBLICATION'; ?></b></font>
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
							<form method="post" action="mags_online_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Name:</b></td>
										<td><input type="text" name="online_name" value="<?php echo htmlspecialchars($values['online_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Issue #:</b></td>
										<td><input type="text" name="online_issue" value="<?php echo htmlspecialchars((string) $values['online_issue']); ?>" size="5"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="online_url" value="<?php echo htmlspecialchars($values['online_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="mags_online.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
