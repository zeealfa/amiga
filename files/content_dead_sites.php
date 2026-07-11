<table width=100% align=center cellpadding=0>
	<tr>
		<td>
			<title>AmigaSource.com - DEAD SITES</title>

			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
				<tr>
					<td>
						<table width="100%" cellpadding="1" cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
										<b>DEAD SITES</b>
									</font>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<br>

<?php
require_once __DIR__ . '/includes/functions.php';

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = LINKS_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;
$adjacents = 2;

$where = "links_dead=1";

$result_count = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_links WHERE $where");
$total_records = mysqli_fetch_array($result_count)['total_records'];
$total_no_of_pages = max(1, (int) ceil($total_records / $total_records_per_page));
$second_last = max(1, $total_no_of_pages - 1);
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
</center>

<?php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE $where ORDER BY id DESC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, 'ii', $offset, $total_records_per_page);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($line2 = mysqli_fetch_array($result)) {
	include 'table_link.php';
}
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
</center>

			<font class="txt-3" face="Verdana, sans-serif" size="3">
			<b>
			<center>
<?php echo "<p>Total number of web sites found: $total_records</p>"; ?>
			</center>
			</b>
			</font>
		</td>
	</tr>
</table>
