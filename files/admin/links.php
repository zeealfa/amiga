<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$cat_id = isset($_GET['cat_id']) && $_GET['cat_id'] !== '' ? intval($_GET['cat_id']) : null;
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

$allowed_sorts = ['links_name' => 'links_name', 'links_date_added' => 'links_date_added'];
$sort = isset($_GET['sort']) && isset($allowed_sorts[$_GET['sort']]) ? $allowed_sorts[$_GET['sort']] : 'links_name';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC' ? 'DESC' : 'ASC';

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = LINKS_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;

$where = [$show_deleted ? '1=1' : 'links_deleted_at IS NULL'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status === 'active') {
    $where[] = 'links_active = 1';
} elseif ($status === 'dead') {
    $where[] = 'links_dead = 1';
} elseif ($status === 'verified') {
    $where[] = 'links_verified = 1';
} elseif ($status === 'recommended') {
    $where[] = 'links_recommended = 1';
}

if ($cat_id !== null) {
    $where[] = '(links_cat_1 = ? OR links_cat_2 = ? OR links_cat_3 = ? OR links_cat_4 = ? OR links_cat_5 = ?)';
    $types .= 'iiiii';
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
}

$where_sql = implode(' AND ', $where);

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS total_records FROM t_links WHERE $where_sql");
if ($types !== '') {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total_records'];
mysqli_stmt_close($stmt_count);

$total_no_of_pages = max(1, (int) ceil($total_records / $total_records_per_page));
$second_last = max(1, $total_no_of_pages - 1);
$adjacents = 2;

$list_types = $types . 'ii';
$list_params = array_merge($params, [$offset, $total_records_per_page]);

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE $where_sql ORDER BY $sort $dir LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$links = [];
while ($row = mysqli_fetch_assoc($result)) {
    $links[] = $row;
}
mysqli_stmt_close($stmt);

$category_tree = get_category_tree($myConnection);

function find_cat_title($nodes, $target_id)
{
    foreach ($nodes as $node) {
        if ($node['id'] === $target_id) {
            return $node['title'];
        }
        if (!empty($node['children'])) {
            $found = find_cat_title($node['children'], $target_id);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

$url_prefix = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0')
    . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);

function sort_link($column, $label, $current_sort, $current_dir, $base_qs)
{
    $new_dir = ($current_sort === $column && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $qs = $base_qs . '&sort=' . urlencode($column) . '&dir=' . urlencode($new_dir);
    $arrow = $current_sort === $column ? ($current_dir === 'ASC' ? ' &uarr;' : ' &darr;') : '';
    return '<a href="?' . $qs . '">' . htmlspecialchars($label) . $arrow . '</a>';
}

$base_qs = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0');
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Links</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="90%" align="center" cellpadding="0" cellspacing="0">
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
							<span class="txt-4-white"><b>MANAGE LINKS</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
							<form method="get" action="links.php">
								<span class="txt-2-black">
								Search: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width:180px;">
								Status:
								<select name="status">
									<option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
									<option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
									<option value="dead" <?php echo $status === 'dead' ? 'selected' : ''; ?>>Dead</option>
									<option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
									<option value="recommended" <?php echo $status === 'recommended' ? 'selected' : ''; ?>>Recommended</option>
								</select>
								Category:
								<select name="cat_id">
									<option value="">All</option>
									<?php
									function render_cat_filter_options($nodes, $depth, $cat_id) {
										foreach ($nodes as $node) {
											echo '<option value="' . $node['id'] . '" ' . ($cat_id === $node['id'] ? 'selected' : '') . '>'
												. str_repeat('&mdash;&nbsp;', $depth) . htmlspecialchars($node['title']) . '</option>';
											if (!empty($node['children'])) {
												render_cat_filter_options($node['children'], $depth + 1, $cat_id);
											}
										}
									}
									render_cat_filter_options($category_tree, 0, $cat_id);
									?>
								</select>
								<label><input type="checkbox" name="show_deleted" value="1" <?php echo $show_deleted ? 'checked' : ''; ?>> Show deleted</label>
								<input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;">
								<a href="link_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a>
								</span>
							</form>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b><?php echo sort_link('links_name', 'Name', $sort, $dir, $base_qs); ?></b></span></td>
									<td><span class="txt-2-black"><b>URL</b></span></td>
									<td><span class="txt-2-black"><b>Category</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b><?php echo sort_link('links_date_added', 'Added', $sort, $dir, $base_qs); ?></b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php if (empty($links)): ?>
								<tr><td colspan="6"><span class="txt-2-black">No links found.</span></td></tr>
<?php endif; ?>
<?php foreach ($links as $link): ?>
<?php
    $cat_ids = array_filter([$link['links_cat_1'], $link['links_cat_2'], $link['links_cat_3'], $link['links_cat_4'], $link['links_cat_5']]);
    $cat_label = '&mdash;';
    if (!empty($cat_ids)) {
        $first_title = find_cat_title($category_tree, (int) reset($cat_ids));
        if ($first_title !== null) {
            $cat_label = htmlspecialchars($first_title) . (count($cat_ids) > 1 ? ' +' . (count($cat_ids) - 1) . ' more' : '');
        }
    }
    $status_parts = [];
    if ($link['links_active']) { $status_parts[] = 'active'; }
    if ($link['links_dead']) { $status_parts[] = 'dead'; }
    if ($link['links_verified']) { $status_parts[] = 'verified'; }
    if ($link['links_recommended']) { $status_parts[] = 'recommended'; }
    if ($link['links_deleted_at'] !== null) { $status_parts[] = 'DELETED'; }
?>
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span></td>
									<td><span class="txt-1"><?php echo $cat_label; ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_date_added']); ?></span></td>
									<td><span class="txt-1">
<?php if ($link['links_deleted_at'] !== null): ?>
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>&action=restore">Restore</a>
<?php else: ?>
										<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a>
<?php endif; ?>
									</span></td>
								</tr>
<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" align="center" style="padding:8px;">
							<span class="txt-2-black">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></span>
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
