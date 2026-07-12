<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();
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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['links_name'] = strip_tags(trim($_POST['links_name'] ?? ''));
    $values['links_url'] = trim($_POST['links_url'] ?? '');
    $values['links_author'] = strip_tags(trim($_POST['links_author'] ?? ''));
    $values['links_email'] = trim($_POST['links_email'] ?? '');
    $values['links_desc'] = strip_tags(trim($_POST['links_desc'] ?? ''));
    $values['links_cats'] = array_map('intval', $_POST['links_cats'] ?? []);

    if ($is_edit) {
        $check_stmt = mysqli_prepare($myConnection, "SELECT id FROM t_links WHERE id = ? AND submitted_by = ? AND links_deleted_at IS NULL");
        mysqli_stmt_bind_param($check_stmt, 'ii', $id, $_SESSION['user_id']);
        mysqli_stmt_execute($check_stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
            header('Location: my_links.php');
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }

    if ($values['links_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['links_url'] === '') {
        $errors[] = 'URL is required.';
    } elseif (!filter_var($values['links_url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($values['links_url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'URL is not a well-formed URL.';
    }
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $exclude_link_id = $is_edit ? $id : null;
        if (find_exact_duplicate_link_url($myConnection, $values['links_url'], $exclude_link_id) !== null) {
            $errors[] = 'This URL already exist';
        } elseif (!is_link_url_alive($values['links_url'])) {
            $errors[] = 'Link is not valid';
        }
    }

    if (empty($errors)) {
        $category_ids = implode(',', array_unique($values['links_cats']));
        $target_id = $is_edit ? $id : null;
        $action = $is_edit ? 'edit' : 'new';

        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_submissions (type, action, target_id, submitted_by, links_name, links_url, links_author, links_email, links_desc, category_ids, status)
             VALUES ('link', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'siissssss',
            $action, $target_id, $_SESSION['user_id'],
            $values['links_name'], $values['links_url'], $values['links_author'], $values['links_email'], $values['links_desc'],
            $category_ids
        );
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($success) {
            $_SESSION['flash_message'] = $is_edit ? 'Link edit submitted for review.' : 'Link submitted for review.';
            header('Location: my_submissions.php');
            exit;
        }

        $errors[] = 'Submission failed, please try again.';
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND submitted_by = ? AND links_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: my_links.php');
        exit;
    }

    $values['links_name'] = $row['links_name'];
    $values['links_url'] = $row['links_url'];
    $values['links_author'] = $row['links_author'];
    $values['links_email'] = $row['links_email'];
    $values['links_desc'] = $row['links_desc'];
    $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, 'i', $id);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $values['links_cats'][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
}

$category_tree = get_category_tree($myConnection);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Link' : 'Submit Link'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
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
function updateLinkPreview() {
    var nameField = document.getElementById('links_name');
    var urlField = document.getElementById('links_url');
    var authorField = document.getElementById('links_author');
    var descField = document.getElementById('links_desc');

    var name = nameField.value.replace(/^\s+|\s+$/g, '');
    var url = urlField.value.replace(/^\s+|\s+$/g, '');
    var author = authorField.value.replace(/^\s+|\s+$/g, '');
    var desc = descField.value.replace(/^\s+|\s+$/g, '');

    document.getElementById('preview_name').textContent = name === '' ? '(link name)' : name;
    document.getElementById('preview_author').textContent = author === '' ? '(author)' : author;
    document.getElementById('preview_desc').textContent = desc === '' ? '(description)' : desc;

    var urlNote = document.getElementById('preview_url_note');
    urlNote.textContent = url === '' ? '' : 'Links to: ' + url;
}

var urlCheckSeq = 0;
var lastCheckedUrl = null;

function requestUrlStatus(url, seq, statusEl) {
    function applyResult(status) {
        if (seq !== urlCheckSeq) {
            return;
        }
        if (status === 'up') {
            statusEl.textContent = String.fromCharCode(0x2713);
            statusEl.style.color = '#008000';
        } else if (status === 'down') {
            statusEl.textContent = String.fromCharCode(0x2717);
            statusEl.style.color = '#c70000';
        } else {
            statusEl.textContent = '';
        }
    }

    if (window.fetch) {
        fetch('link_url_check.php?url=' + encodeURIComponent(url), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) { applyResult(data.status); })
            .catch(function () { applyResult('down'); });
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'link_url_check.php?url=' + encodeURIComponent(url), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    applyResult(JSON.parse(xhr.responseText).status);
                } catch (e) {
                    applyResult('down');
                }
            } else {
                applyResult('down');
            }
        }
    };
    xhr.send();
}

function checkUrlStatus() {
    var urlField = document.getElementById('links_url');
    var statusEl = document.getElementById('url_status');
    var value = urlField.value.replace(/^\s+|\s+$/g, '');

    urlCheckSeq += 1;
    var seq = urlCheckSeq;

    lastCheckedUrl = value;

    if (value === '') {
        statusEl.textContent = '';
        return;
    }

    statusEl.textContent = '...';
    statusEl.style.color = '#666666';
    requestUrlStatus(value, seq, statusEl);
}

document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    boxes.forEach(function (box) {
        box.addEventListener('change', enforceCategoryLimit);
    });
    enforceCategoryLimit();

    var urlField = document.getElementById('links_url');
    urlField.addEventListener('blur', function () {
        var value = urlField.value.replace(/^\s+|\s+$/g, '');
        if (value !== lastCheckedUrl) {
            checkUrlStatus();
        }
    });

    if (urlField.value.replace(/^\s+|\s+$/g, '') !== '') {
        checkUrlStatus();
    }

    ['links_name', 'links_url', 'links_author', 'links_desc'].forEach(function (fieldId) {
        document.getElementById(fieldId).addEventListener('input', updateLinkPreview);
    });
    updateLinkPreview();
});
</script>
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
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT LINK' : 'SUBMIT LINK'; ?></b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="my_links.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to My Links</a></font></td>
								</tr>
							</table>
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
							<p><font class="txt-1" face="Verdana, sans-serif" size="1">Submissions are reviewed by an admin before they go live.</font></p>
							<form method="post" action="link_submit.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Name:</b></td>
										<td><input type="text" name="links_name" value="<?php echo htmlspecialchars($values['links_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>URL:</b></td>
										<td><input type="text" id="links_url" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:80%;"> <span id="url_status"></span></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Author:</b></td>
										<td><input type="text" name="links_author" value="<?php echo htmlspecialchars($values['links_author']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Email:</b></td>
										<td><input type="text" name="links_email" value="<?php echo htmlspecialchars($values['links_email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Description:</b></td>
										<td><textarea name="links_desc" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['links_desc']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Categories (up to 5):</b></td>
										<td class="txt-1">
<?php render_cat_checkboxes($category_tree, 0, $values['links_cats']); ?>
										</td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Submit for Review" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
										</td>
									</tr>
								</table>
							</form>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br>
			<table width="100%" cellpadding="1" cellspacing="0" class="bg-darkolive" bgcolor="<?php echo bg_hex('darkolive'); ?>">
				<tr><td>
					<table width="100%" cellpadding="0" cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
						<tr>
							<td colspan="2"><font class="txt-1" face="Verdana, sans-serif" size="1"><b>Preview &mdash; how this will look live once approved:</b></font></td>
						</tr>
						<tr>
							<td colspan="2" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">&nbsp;
								<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"><b><span id="preview_name">(link name)</span></b></font>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
								<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Author:</b> <span id="preview_author">(author)</span></font>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">&nbsp;
								<font class="txt-2" face="Verdana, sans-serif" size="2"><span id="preview_desc">(description)</span></font>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">&nbsp;
								<font class="txt-1" face="Verdana, sans-serif" size="1"><span id="preview_url_note"></span></font>
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
