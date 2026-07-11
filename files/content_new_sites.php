<table width=100% align=center cellpadding=0>
	<tr>
		<td>
<?php
require_once __DIR__ . '/includes/functions.php';

$stmt = mysqli_prepare(
	$myConnection,
	"SELECT * FROM t_links WHERE (links_active=1 or links_archived_url<>'') and links_date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY links_date_added DESC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$heading = 'NEW SITES (last 7 days)';
if (empty($rows)) {
	$heading = 'NEW SITES (last 10 added)';
	$stmt = mysqli_prepare(
		$myConnection,
		"SELECT * FROM t_links WHERE (links_active=1 or links_archived_url<>'') ORDER BY links_date_added DESC LIMIT 10"
	);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
	mysqli_stmt_close($stmt);
}
?>
			<title>AmigaSource.com - <?php echo $heading; ?></title>

			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
				<tr>
					<td>
						<table width="100%" cellpadding="1" cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
										<b><?php echo $heading; ?></b>
									</font>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<br>

<?php foreach ($rows as $line2) {
	include 'table_link.php';
} ?>

			<font class="txt-3" face="Verdana, sans-serif" size="3">
			<b>
			<center>
<?php echo "<p>Total number of web sites found: " . count($rows) . "</p>"; ?>
			</center>
			</b>
			</font>
		</td>
	</tr>
</table>
