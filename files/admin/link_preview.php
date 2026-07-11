<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['link_preview_data'])) {
    header('Location: link_form.php');
    exit;
}

$data = $_SESSION['link_preview_data'];
$is_edit = !empty($data['id']);

// Re-validate server-side — never trust that link_form.php's validation
// was not bypassed by a direct POST to this page.
$errors = [];
if (trim($data['links_name']) === '') {
    $errors[] = 'Name is required.';
}
if (trim($data['links_url']) === '') {
    $errors[] = 'URL is required.';
} elseif (!filter_var($data['links_url'], FILTER_VALIDATE_URL)) {
    $errors[] = 'URL is not a well-formed URL.';
}
if ($data['links_email'] !== '' && !filter_var($data['links_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is not a well-formed email address.';
}
if (count($data['links_cats']) > 5) {
    $errors[] = 'You may select at most 5 categories.';
}

if (!empty($errors)) {
    unset($_SESSION['link_preview_data']);
    header('Location: link_form.php');
    exit;
}

$duplicates = find_similar_link_urls($myConnection, $data['links_url'], $is_edit ? (int) $data['id'] : null);
$save_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $exclude_link_id = $is_edit ? (int) $data['id'] : null;
    if (find_exact_duplicate_link_url($myConnection, $data['links_url'], $exclude_link_id) !== null) {
        $save_errors[] = 'This URL already exist';
    } elseif (!is_link_url_alive($data['links_url'])) {
        $save_errors[] = 'Link is not valid';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save']) && empty($save_errors)) {
    $cats = array_pad($data['links_cats'], 5, 0);

    if ($is_edit) {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_links SET links_name=?, links_url=?, links_author=?, links_email=?, links_desc=?,
             links_cat_1=?, links_cat_2=?, links_cat_3=?, links_cat_4=?, links_cat_5=?,
             links_date_added=?, links_active=?, links_dead=?, links_verified=?, links_recommended=?
             WHERE id=?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssiiiiisiiiii',
            $data['links_name'], $data['links_url'], $data['links_author'], $data['links_email'], $data['links_desc'],
            $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
            $data['links_date_added'], $data['links_active'], $data['links_dead'], $data['links_verified'], $data['links_recommended'],
            $data['id']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $link_id = (int) $data['id'];
        $flash = 'Link updated';
        log_audit($myConnection, 'link', $link_id, 'edit', $data['links_name'], $_SESSION['user_id']);
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_links
             (links_name, links_url, links_author, links_email, links_desc,
              links_cat_1, links_cat_2, links_cat_3, links_cat_4, links_cat_5,
              links_date_added, links_active, links_dead, links_verified, links_recommended)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssiiiiisiiii',
            $data['links_name'], $data['links_url'], $data['links_author'], $data['links_email'], $data['links_desc'],
            $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
            $data['links_date_added'], $data['links_active'], $data['links_dead'], $data['links_verified'], $data['links_recommended']
        );
        mysqli_stmt_execute($stmt);
        $link_id = mysqli_insert_id($myConnection);
        mysqli_stmt_close($stmt);
        $flash = 'Link added';
        log_audit($myConnection, 'link', $link_id, 'add', $data['links_name'], $_SESSION['user_id']);
    }

    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_link_categories WHERE link_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $link_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($data['links_cats'])) {
        $stmt = mysqli_prepare($myConnection, "INSERT INTO t_link_categories (link_id, category_id) VALUES (?, ?)");
        foreach (array_unique($data['links_cats']) as $category_id) {
            mysqli_stmt_bind_param($stmt, 'ii', $link_id, $category_id);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    unset($_SESSION['link_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: links.php');
    exit;
}

// Build a mysqli_fetch_array-shaped row so table_link.php (the exact public
// rendering include) can render the not-yet-saved data unmodified.
$line2 = [
    'id' => $is_edit ? $data['id'] : 0,
    'links_name' => $data['links_name'],
    'links_url' => $data['links_url'],
    'links_author' => $data['links_author'],
    'links_email' => $data['links_email'],
    'links_desc' => $data['links_desc'],
    'links_cat_1' => $data['links_cats'][0] ?? 0,
    'links_cat_2' => $data['links_cats'][1] ?? 0,
    'links_cat_3' => $data['links_cats'][2] ?? 0,
    'links_cat_4' => $data['links_cats'][3] ?? 0,
    'links_cat_5' => $data['links_cats'][4] ?? 0,
    'links_cat_6' => 0, 'links_cat_7' => 0, 'links_cat_8' => 0, 'links_cat_9' => 0, 'links_cat_10' => 0,
    'links_date_added' => $data['links_date_added'],
    'links_dead' => $data['links_dead'] ? 1 : 0,
    'links_archived_url' => '',
    'links_archived_date' => '0000-00-00',
    'links_date_verified' => '0000-00-00',
    'links_verified' => $data['links_verified'] ? 1 : 0,
    'links_active' => $data['links_active'] ? 1 : 0,
    'links_recommended' => $data['links_recommended'] ? 1 : 0,
];
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Preview Link</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW LINK</b></font>
						</td>
					</tr>
<?php if (!empty($save_errors)): ?>
						<tr>
							<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
								<div class="txt-2-black" style="color:#c70000;">
									<b>Cannot save:</b>
									<ul>
<?php foreach ($save_errors as $error): ?>
										<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
									</ul>
								</div>
							</td>
						</tr>
<?php endif; ?>
<?php if (!empty($duplicates)): ?>
					<tr>
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<b>Possible duplicate URL found:</b>
								<ul>
<?php foreach ($duplicates as $dup): ?>
									<li><?php echo htmlspecialchars($dup['links_name']); ?> (<a href="link_form.php?id=<?php echo (int) $dup['id']; ?>" target="_blank"><?php echo htmlspecialchars($dup['links_url']); ?></a>)</li>
<?php endforeach; ?>
								</ul>
								You can still save this link if it is not actually a duplicate.
							</font>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<?php include __DIR__ . '/../table_link.php'; ?>
						</td>
					</tr>
					<tr>
						<td align="center" style="padding:12px;">
							<form method="post" action="link_form.php" style="display:inline;">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $data['id']; ?>">
<?php endif; ?>
								<input type="hidden" name="links_name" value="<?php echo htmlspecialchars($data['links_name']); ?>">
								<input type="hidden" name="links_url" value="<?php echo htmlspecialchars($data['links_url']); ?>">
								<input type="hidden" name="links_author" value="<?php echo htmlspecialchars($data['links_author']); ?>">
								<input type="hidden" name="links_email" value="<?php echo htmlspecialchars($data['links_email']); ?>">
								<input type="hidden" name="links_desc" value="<?php echo htmlspecialchars($data['links_desc']); ?>">
<?php foreach ($data['links_cats'] as $cat_id): ?>
								<input type="hidden" name="links_cats[]" value="<?php echo (int) $cat_id; ?>">
<?php endforeach; ?>
								<input type="hidden" name="links_date_added" value="<?php echo htmlspecialchars($data['links_date_added']); ?>">
<?php if ($data['links_active']): ?><input type="hidden" name="links_active" value="on"><?php endif; ?>
<?php if ($data['links_dead']): ?><input type="hidden" name="links_dead" value="on"><?php endif; ?>
<?php if ($data['links_verified']): ?><input type="hidden" name="links_verified" value="on"><?php endif; ?>
<?php if ($data['links_recommended']): ?><input type="hidden" name="links_recommended" value="on"><?php endif; ?>
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="link_preview.php" style="display:inline;">
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
