<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$result = mysqli_query($myConnection, "SELECT * FROM t_repair ORDER BY repair_name ASC");
$repairs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $repairs[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Service and Repair</title>
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE SERVICE AND REPAIR</b></font>
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
							<a href="repair_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Entry</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Name</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Country</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>URL</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($repairs)): ?>
								<tr><td colspan="4"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">No entries found.</font></td></tr>
<?php endif; ?>
<?php foreach ($repairs as $repair): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($repair['repair_name']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($repair['repair_country']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($repair['repair_url']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="repair_form.php?id=<?php echo (int) $repair['id']; ?>">Edit</a> |
										<a href="repair_delete.php?id=<?php echo (int) $repair['id']; ?>">Delete</a>
									</font></td>
								</tr>
<?php endforeach; ?>
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
