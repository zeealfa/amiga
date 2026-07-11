<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$entity_type = $_GET['entity_type'] ?? 'all';
$allowed_entity_types = ['link', 'news', 'category', 'calendar', 'crowdfunding'];
if (!in_array($entity_type, $allowed_entity_types, true)) {
    $entity_type = 'all';
}

$action = $_GET['action'] ?? 'all';
$allowed_actions = ['add', 'edit', 'delete', 'restore'];
if (!in_array($action, $allowed_actions, true)) {
    $action = 'all';
}

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = AUDIT_LOG_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;

$where = ['1=1'];
$types = '';
$params = [];

if ($entity_type !== 'all') {
    $where[] = 'a.entity_type = ?';
    $types .= 's';
    $params[] = $entity_type;
}

if ($action !== 'all') {
    $where[] = 'a.action = ?';
    $types .= 's';
    $params[] = $action;
}

$where_sql = implode(' AND ', $where);

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS total_records FROM t_audit_log a WHERE $where_sql");
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

$stmt = mysqli_prepare(
    $myConnection,
    "SELECT a.*, u.username FROM t_audit_log a LEFT JOIN t_users u ON u.id = a.user_id
     WHERE $where_sql ORDER BY a.created_at DESC LIMIT ?, ?"
);
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$entries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $entries[] = $row;
}
mysqli_stmt_close($stmt);

$url_prefix = 'entity_type=' . urlencode($entity_type) . '&action=' . urlencode($action) . '&';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Audit Log</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="90%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>AUDIT LOG</b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<form method="get" action="audit_log.php">
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
								<td style="white-space:nowrap; padding-right:10px;">Entity:
								<select name="entity_type">
									<option value="all" <?php echo $entity_type === 'all' ? 'selected' : ''; ?>>All</option>
									<option value="link" <?php echo $entity_type === 'link' ? 'selected' : ''; ?>>Link</option>
									<option value="news" <?php echo $entity_type === 'news' ? 'selected' : ''; ?>>News</option>
									<option value="category" <?php echo $entity_type === 'category' ? 'selected' : ''; ?>>Category</option>
									<option value="calendar" <?php echo $entity_type === 'calendar' ? 'selected' : ''; ?>>Calendar</option>
									<option value="crowdfunding" <?php echo $entity_type === 'crowdfunding' ? 'selected' : ''; ?>>Crowdfunding</option>
								</select>
								</td>
								<td style="white-space:nowrap; padding-right:10px;">Action:
								<select name="action">
									<option value="all" <?php echo $action === 'all' ? 'selected' : ''; ?>>All</option>
									<option value="add" <?php echo $action === 'add' ? 'selected' : ''; ?>>Add</option>
									<option value="edit" <?php echo $action === 'edit' ? 'selected' : ''; ?>>Edit</option>
									<option value="delete" <?php echo $action === 'delete' ? 'selected' : ''; ?>>Delete</option>
									<option value="restore" <?php echo $action === 'restore' ? 'selected' : ''; ?>>Restore</option>
								</select>
								</td>
								<td style="white-space:nowrap;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
								</tr></table>
							</form>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td style="width:16%;"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Date/Time</b></font></td>
									<td style="width:12%;"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actor</b></font></td>
									<td style="width:10%;"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Entity</b></font></td>
									<td style="width:10%;"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Action</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Label</b></font></td>
								</tr>
<?php if (empty($entries)): ?>
								<tr><td colspan="5"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">No audit log entries found.</font></td></tr>
<?php endif; ?>
<?php foreach ($entries as $entry): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($entry['created_at']))); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($entry['username'] ?? ('#' . $entry['user_id'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($entry['entity_type'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($entry['action'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($entry['label']); ?></font></td>
								</tr>
<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" align="center" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></font>
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
