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
    'title_short' => '',
    'description' => '',
    'parent_id' => isset($_GET['parent_id']) ? intval($_GET['parent_id']) : null,
    'active' => true,
];

function fetch_all_categories_for_dropdown($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT id, parent_id, title FROM t_categories ORDER BY parent_id, sort_order");
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Returns a flat, depth-ordered list of [id, title, depth] for building the
// parent dropdown, excluding $exclude_id and all of its descendants.
function build_dropdown_options($rows, $exclude_id = null)
{
    $by_parent = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $excluded = [];
    if ($exclude_id !== null) {
        $mark = function ($id) use (&$mark, &$excluded, $by_parent) {
            $excluded[$id] = true;
            foreach ($by_parent[$id] ?? [] as $child) {
                $mark((int) $child['id']);
            }
        };
        $mark($exclude_id);
    }

    $options = [];
    $walk = function ($parent_key, $depth) use (&$walk, &$options, $by_parent, $excluded) {
        foreach ($by_parent[$parent_key] ?? [] as $row) {
            $id = (int) $row['id'];
            if (isset($excluded[$id])) {
                continue;
            }
            $options[] = ['id' => $id, 'title' => $row['title'], 'depth' => $depth];
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);

    return $options;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = strip_tags(trim($_POST['title'] ?? ''));
    $values['title_short'] = strip_tags(trim($_POST['title_short'] ?? ''));
    $values['description'] = strip_tags(trim($_POST['description'] ?? ''));
    $values['parent_id'] = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
    $values['active'] = isset($_POST['active']);

    if ($values['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($values['title_short'] === '') {
        $errors[] = 'Short title is required.';
    }
    if ($is_edit && $values['parent_id'] === $id) {
        $errors[] = 'A category cannot be its own parent.';
    }
    if ($is_edit && $values['parent_id'] !== null && empty($errors)) {
        $descendant_rows = fetch_all_categories_for_dropdown($myConnection);
        $by_parent = [];
        foreach ($descendant_rows as $row) {
            $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $by_parent[$key][] = $row;
        }
        $descendants = [];
        $mark = function ($node_id) use (&$mark, &$descendants, $by_parent) {
            foreach ($by_parent[$node_id] ?? [] as $child) {
                $descendants[(int) $child['id']] = true;
                $mark((int) $child['id']);
            }
        };
        $mark($id);
        if (isset($descendants[$values['parent_id']])) {
            $errors[] = 'A category cannot be moved under one of its own descendants.';
        }
    }

    if (empty($errors)) {
        if ($values['parent_id'] !== null) {
            $stmt = mysqli_prepare($myConnection, "SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM t_categories WHERE parent_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $values['parent_id']);
        } else {
            $stmt = mysqli_prepare($myConnection, "SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM t_categories WHERE parent_id IS NULL");
        }
        mysqli_stmt_execute($stmt);
        $next_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['next_order'];
        mysqli_stmt_close($stmt);

        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_categories SET title=?, title_short=?, description=?, parent_id=?, active=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssiiii',
                $values['title'], $values['title_short'], $values['description'],
                $values['parent_id'], $values['active'], $next_order, $id
            );
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_categories (title, title_short, description, parent_id, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssiii',
                $values['title'], $values['title_short'], $values['description'],
                $values['parent_id'], $values['active'], $next_order
            );
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = $is_edit ? 'Category updated' : 'Category added';
        header('Location: categories.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: categories.php');
        exit;
    }

    $values['title'] = $row['title'];
    $values['title_short'] = $row['title_short'];
    $values['description'] = $row['description'] ?? '';
    $values['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    $values['active'] = (bool) $row['active'];
}

$dropdown_options = build_dropdown_options(fetch_all_categories_for_dropdown($myConnection), $is_edit ? $id : null);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Category' : 'Add Category'; ?></title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT CATEGORY' : 'ADD CATEGORY'; ?></b></font>
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
							<form method="post" action="category_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Title:</b></td>
										<td><input type="text" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Short Title:</b></td>
										<td><input type="text" name="title_short" value="<?php echo htmlspecialchars($values['title_short']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="description" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['description']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right"><b>Parent:</b></td>
										<td>
											<select name="parent_id">
												<option value="">&mdash; None (top level) &mdash;</option>
<?php foreach ($dropdown_options as $option): ?>
												<option value="<?php echo $option['id']; ?>" <?php echo $values['parent_id'] === $option['id'] ? 'selected' : ''; ?>>
													<?php echo str_repeat('&mdash;&nbsp;', $option['depth']) . htmlspecialchars($option['title']); ?>
												</option>
<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Status:</b></td>
										<td><label><input type="checkbox" name="active" <?php echo $values['active'] ? 'checked' : ''; ?>> Active</label></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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
