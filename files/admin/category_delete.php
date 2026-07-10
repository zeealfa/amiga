<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, title FROM t_categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$category) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS child_count FROM t_categories WHERE parent_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$child_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['child_count'];
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $child_count == 0) {
    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'Category deleted';
    header('Location: categories.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete Category</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="60%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="25%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="72%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>DELETE CATEGORY</b></span>
						</td>
					</tr>
<?php if ($child_count > 0): ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Cannot delete <b><?php echo htmlspecialchars($category['title']); ?></b>: remove or move its <?php echo (int) $child_count; ?> subcategories first.
							</span>
							<br><br>
							<center>
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Back</a>
							</center>
						</td>
					</tr>
<?php else: ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete <b><?php echo htmlspecialchars($category['title']); ?></b>?
							</span>
							<br><br>
							<center>
								<form method="post" action="category_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php endif; ?>
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
