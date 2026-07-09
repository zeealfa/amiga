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
    $values['links_name'] = strip_tags(trim($_POST['links_name'] ?? ''));
    $values['links_url'] = trim($_POST['links_url'] ?? '');
    $values['links_author'] = strip_tags(trim($_POST['links_author'] ?? ''));
    $values['links_email'] = trim($_POST['links_email'] ?? '');
    $values['links_desc'] = strip_tags(trim($_POST['links_desc'] ?? ''));
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
    $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, 'i', $id);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    $values['links_cats'] = [];
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $values['links_cats'][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
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
var urlCheckTimer = null;
var urlCheckSeq = 0;

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
        fetch('link_url_check.php?url=' + encodeURIComponent(url))
            .then(function (response) { return response.json(); })
            .then(function (data) { applyResult(data.status); })
            .catch(function () { applyResult('down'); });
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'link_url_check.php?url=' + encodeURIComponent(url), true);
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
    urlField.addEventListener('input', function () {
        if (urlCheckTimer) {
            clearTimeout(urlCheckTimer);
        }
        urlCheckTimer = setTimeout(checkUrlStatus, 600);
    });

    if (urlField.value.replace(/^\s+|\s+$/g, '') !== '') {
        checkUrlStatus();
    }
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
										<td><input type="text" id="links_url" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:80%;"> <span id="url_status"></span></td>
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
<?php
function render_cat_checkboxes($nodes, $depth, $selected) {
    foreach ($nodes as $node) {
        $is_root = $depth === 0;
        if ($is_root) {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<span style="font-weight:bold;font-style:italic;">'
                . htmlspecialchars($node['title']) . '</span><br>';
        } else {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<label><input type="checkbox" name="links_cats[]" value="' . $node['id'] . '" '
                . (in_array($node['id'], $selected, true) ? 'checked' : '') . '> '
                . htmlspecialchars($node['title']) . '</label><br>';
        }
        if (!empty($node['children'])) {
            render_cat_checkboxes($node['children'], $depth + 1, $selected);
        }
    }
}
render_cat_checkboxes($category_tree, 0, $values['links_cats']);
?>
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
