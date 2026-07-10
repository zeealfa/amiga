<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = ADMIN_NEWS_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;

$where = [$show_deleted ? '1=1' : 'news_deleted_at IS NULL'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = 'news_story LIKE ?';
    $types .= 's';
    $params[] = '%' . $search . '%';
}

$where_sql = implode(' AND ', $where);

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS total_records FROM t_news WHERE $where_sql");
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

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE $where_sql ORDER BY news_date DESC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$news_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $news_rows[] = $row;
}
mysqli_stmt_close($stmt);

$url_prefix = 'search=' . urlencode($search) . '&show_deleted=' . ($show_deleted ? '1' : '0') . '&';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);

$base_qs = 'search=' . urlencode($search) . '&show_deleted=' . ($show_deleted ? '1' : '0');
$full_qs = $base_qs . '&page_no=' . $page_no;
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

function news_story_excerpt($html)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if ($text === '') {
        return '(empty)';
    }
    return mb_strlen($text) > 120 ? mb_substr($text, 0, 120) . '...' : $text;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage News</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
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
							<span class="txt-4-white"><b>MANAGE NEWS</b></span>
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
							<form method="get" action="news.php">
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
								<td style="white-space:nowrap; padding-right:10px;">Search: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width:220px;"></td>
								<td style="white-space:nowrap; padding-right:10px;"><label><input type="checkbox" name="show_deleted" value="1" <?php echo $show_deleted ? 'checked' : ''; ?>> Show deleted</label></td>
								<td style="white-space:nowrap; padding-right:10px;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
								<td style="white-space:nowrap;"><a href="news_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add News</a></td>
								</tr></table>
							</form>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Date</b></span></td>
									<td><span class="txt-2-black"><b>Story</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php if (empty($news_rows)): ?>
								<tr><td colspan="4"><span class="txt-2-black">No news posts found.</span></td></tr>
<?php endif; ?>
<?php foreach ($news_rows as $item): ?>
<?php
    $status_parts = [];
    $status_parts[] = $item['news_active'] ? 'active' : 'unpublished';
    if ($item['news_deleted_at'] !== null) { $status_parts[] = 'DELETED'; }
?>
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($item['news_date']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(news_story_excerpt($item['news_story'])); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
									<td><span class="txt-1">
<?php if ($item['news_deleted_at'] !== null): ?>
										<a href="news_delete.php?id=<?php echo (int) $item['id']; ?>&action=restore">Restore</a>
<?php else: ?>
										<a href="news_form.php?id=<?php echo (int) $item['id']; ?>">Edit</a> |
										<a href="news_delete.php?id=<?php echo (int) $item['id']; ?>">Delete</a> |
										<form method="post" action="news_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
											<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
											<input type="submit" value="<?php echo $item['news_active'] ? 'Unpublish' : 'Publish'; ?>" class="txt-1">
										</form>
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
