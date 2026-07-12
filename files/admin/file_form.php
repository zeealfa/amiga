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
    'title' => '',
    'description' => '',
    'active' => 1,
];
$existing = null;

if ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$existing) {
        header('Location: files.php');
        exit;
    }

    $values['title'] = $existing['title'];
    $values['description'] = $existing['description'];
    $values['active'] = (int) $existing['active'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = strip_tags(trim($_POST['title'] ?? ''));
    $values['description'] = strip_tags(trim($_POST['description'] ?? ''));
    $values['active'] = isset($_POST['active']) ? 1 : 0;

    if ($values['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($values['description'] === '') {
        $errors[] = 'Description is required.';
    }

    $upload = $_FILES['upload'] ?? null;
    $upload_provided = $upload !== null && $upload['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$is_edit && !$upload_provided) {
        $errors[] = 'A file upload is required.';
    }

    $new_stored_filename = null;
    $new_original_filename = null;
    $new_file_size = null;
    $new_file_ext = null;

    if ($upload_provided) {
        $validation = validate_file_upload($upload, FILE_REPO_ALLOWED_EXTENSIONS, FILE_REPO_MAX_BYTES);
        if (!$validation['ok']) {
            $errors[] = $validation['error'];
        } else {
            $new_file_ext = $validation['ext'];
            $new_stored_filename = bin2hex(random_bytes(16)) . '.' . $new_file_ext;
            $new_original_filename = basename($upload['name']);
            $new_file_size = (int) $upload['size'];
        }
    }

    if (empty($errors) && $upload_provided) {
        $destination = FILE_REPO_STORAGE_DIR . '/' . $new_stored_filename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            $errors[] = 'Failed to save the uploaded file. Please try again.';
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($upload_provided) {
                $stmt = mysqli_prepare(
                    $myConnection,
                    "UPDATE t_files SET title=?, description=?, active=?, stored_filename=?, original_filename=?, file_size=?, file_ext=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssissisi',
                    $values['title'],
                    $values['description'],
                    $values['active'],
                    $new_stored_filename,
                    $new_original_filename,
                    $new_file_size,
                    $new_file_ext,
                    $id
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $old_path = FILE_REPO_STORAGE_DIR . '/' . $existing['stored_filename'];
                if (is_file($old_path)) {
                    unlink($old_path);
                }
            } else {
                $stmt = mysqli_prepare(
                    $myConnection,
                    "UPDATE t_files SET title=?, description=?, active=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssii', $values['title'], $values['description'], $values['active'], $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $file_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_files (title, description, stored_filename, original_filename, file_size, file_ext, active) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssssisi',
                $values['title'],
                $values['description'],
                $new_stored_filename,
                $new_original_filename,
                $new_file_size,
                $new_file_ext,
                $values['active']
            );
            mysqli_stmt_execute($stmt);
            $file_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'file', $file_id, $is_edit ? 'edit' : 'add', $values['title'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'File updated' : 'File added';
        header('Location: files.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit File' : 'Add File'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT FILE' : 'ADD FILE'; ?></b></font>
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
							<form method="post" action="file_form.php" enctype="multipart/form-data">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FILE_REPO_MAX_BYTES; ?>">
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Title:</b></td>
										<td><input type="text" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="description" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['description']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right"><b>File:</b></td>
										<td>
											<input type="file" name="upload">
<?php if ($is_edit && $existing): ?>
											<br><font class="txt-1" face="Verdana, sans-serif" size="1">Current file: <?php echo htmlspecialchars($existing['original_filename']); ?> (<?php echo format_file_size($existing['file_size']); ?>). Leave blank to keep it.</font>
<?php endif; ?>
											<br><font class="txt-1" face="Verdana, sans-serif" size="1">Max <?php echo format_file_size(FILE_REPO_MAX_BYTES); ?>. Allowed types: <?php echo htmlspecialchars(implode(', ', FILE_REPO_ALLOWED_EXTENSIONS)); ?></font>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Active:</b></td>
										<td><input type="checkbox" name="active" value="1"<?php echo $values['active'] ? ' checked' : ''; ?>></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="files.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
