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
    $where[] = 'id IN (SELECT link_id FROM t_link_categories WHERE category_id = ?)';
    $types .= 'i';
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

// Batch-fetch every displayed link's category ids in one query, keyed by
// link_id, to avoid an N+1 query per row in the table below.
$link_cat_ids = [];
if (!empty($links)) {
    $link_ids = array_map(fn($l) => (int) $l['id'], $links);
    $placeholders = implode(',', array_fill(0, count($link_ids), '?'));
    $cats_stmt = mysqli_prepare($myConnection, "SELECT link_id, category_id FROM t_link_categories WHERE link_id IN ($placeholders) ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, str_repeat('i', count($link_ids)), ...$link_ids);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $link_cat_ids[(int) $cat_row['link_id']][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
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
$full_qs = $base_qs . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)
    . '&page_no=' . $page_no;
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
// Quick-action buttons (Mark Dead/Verified, Archive.org) are built but hidden for now.
// Flip to true to show them again; link_quick_action.php still works either way.
$show_quick_actions = false;
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
<?php if ($flash): ?>
					<tr>
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
							<form method="get" action="links.php">
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
								<td style="white-space:nowrap; padding-right:10px;">Search: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width:180px;"></td>
								<td style="white-space:nowrap; padding-right:10px;">Status:
								<select name="status">
									<option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
									<option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
									<option value="dead" <?php echo $status === 'dead' ? 'selected' : ''; ?>>Dead</option>
									<option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
									<option value="recommended" <?php echo $status === 'recommended' ? 'selected' : ''; ?>>Recommended</option>
								</select>
								</td>
								<td style="white-space:nowrap; padding-right:10px;">Category:
								<select name="cat_id">
									<option value="">All</option>
									<?php
									function render_cat_filter_options($nodes, $depth, $cat_id) {
										foreach ($nodes as $node) {
											$is_root = $depth === 0;
											echo '<option value="' . $node['id'] . '" '
												. ($cat_id === $node['id'] ? 'selected ' : '')
												. ($is_root ? 'disabled style="font-weight:bold;font-style:italic;"' : '')
												. '>'
												. str_repeat('&mdash;&nbsp;', $depth) . htmlspecialchars($node['title']) . '</option>';
											if (!empty($node['children'])) {
												render_cat_filter_options($node['children'], $depth + 1, $cat_id);
											}
										}
									}
									render_cat_filter_options($category_tree, 0, $cat_id);
									?>
								</select>
								</td>
								<td style="white-space:nowrap; padding-right:10px;"><label><input type="checkbox" name="show_deleted" value="1" <?php echo $show_deleted ? 'checked' : ''; ?>> Show deleted</label></td>
								<td style="white-space:nowrap; padding-right:10px;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
								<td style="white-space:nowrap;"><a href="link_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a></td>
								</tr></table>
							</form>
							<button type="button" id="check_all_links_btn" class="txt-1">Check All</button>
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
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php if (empty($links)): ?>
								<tr><td colspan="5"><span class="txt-2-black">No links found.</span></td></tr>
<?php endif; ?>
<?php foreach ($links as $link): ?>
<?php
    $cat_ids = $link_cat_ids[(int) $link['id']] ?? [];
    $cat_label = '&mdash;';
    if (!empty($cat_ids)) {
        $first_title = find_cat_title($category_tree, $cat_ids[0]);
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
								<tr<?php echo $link['links_deleted_at'] === null ? ' data-link-id="' . (int) $link['id'] . '"' : ''; ?>>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span><?php if ($link['links_deleted_at'] === null): ?><span class="txt-1" data-url-status></span><?php endif; ?></td>
									<td><span class="txt-1"><?php echo $cat_label; ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
									<td><span class="txt-1">
<?php if ($link['links_deleted_at'] !== null): ?>
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>&action=restore">Restore</a>
<?php else: ?>
										<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a>
<?php if ($show_quick_actions): ?>
										|
										<form method="post" action="link_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
											<input type="hidden" name="field" value="dead">
											<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
											<input type="submit" value="<?php echo $link['links_dead'] ? 'Mark Not Dead' : 'Mark Dead'; ?>" class="txt-1">
										</form> |
										<form method="post" action="link_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
											<input type="hidden" name="field" value="verified">
											<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
											<input type="submit" value="<?php echo $link['links_verified'] ? 'Unverify' : 'Mark Verified'; ?>" class="txt-1">
										</form> |
										<a href="https://web.archive.org/web/*/<?php echo urlencode($link['links_url']); ?>" target="_blank">Archive.org</a>
<?php endif; ?>
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
