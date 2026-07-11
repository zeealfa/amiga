<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

function fetch_categories_flat($myConnection)
{
    $result = mysqli_query(
        $myConnection,
        "SELECT id, parent_id, title, sort_order, active FROM t_categories ORDER BY parent_id, sort_order"
    );
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function build_admin_tree($rows)
{
    $by_parent = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $build = function ($parent_key) use (&$build, $by_parent) {
        $nodes = [];
        $siblings = $by_parent[$parent_key] ?? [];
        foreach ($siblings as $index => $row) {
            $nodes[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'active' => (bool) $row['active'],
                'is_first' => $index === 0,
                'is_last' => $index === count($siblings) - 1,
                'children' => $build((int) $row['id']),
            ];
        }
        return $nodes;
    };

    return $build(0);
}

function render_admin_tree_rows($nodes, $depth)
{
    foreach ($nodes as $node) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        echo '<tr><td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="' . txt_hex('black') . '">' . $indent . htmlspecialchars($node['title']);
        if (!$node['active']) {
            echo ' <i>(inactive)</i>';
        }
        echo '</font></td><td>';
        if (!$node['is_first']) {
            echo '<form method="post" action="category_move.php" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $node['id'] . '">'
                . '<input type="hidden" name="dir" value="up">'
                . '<input type="hidden" name="confirm_move" value="1">'
                . '<input type="submit" value="Up">'
                . '</form> ';
        }
        if (!$node['is_last']) {
            echo '<form method="post" action="category_move.php" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $node['id'] . '">'
                . '<input type="hidden" name="dir" value="down">'
                . '<input type="hidden" name="confirm_move" value="1">'
                . '<input type="submit" value="Down">'
                . '</form>';
        }
        echo '</td><td>'
            . '<a href="category_form.php?parent_id=' . $node['id'] . '">Add Subcategory</a> | '
            . '<a href="category_form.php?id=' . $node['id'] . '">Edit</a> | '
            . '<a href="category_delete.php?id=' . $node['id'] . '">Delete</a>'
            . '</td></tr>';
        if (!empty($node['children'])) {
            render_admin_tree_rows($node['children'], $depth + 1);
        }
    }
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$tree = build_admin_tree(fetch_categories_flat($myConnection));
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Categories</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE CATEGORIES</b></font>
						</td>
					</tr>
<?php if ($flash): ?>
					<tr>
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;">
							<a href="category_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Root Category</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Title</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Reorder</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php render_admin_tree_rows($tree, 0); ?>
							</table>
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
