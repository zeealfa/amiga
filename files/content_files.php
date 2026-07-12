<table width=100% align=center cellpadding=0 >

 	<tr>

		<td>



<center><br>

<table cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="15">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
										<b>FILE REPOSITORY</b><br>
									</font>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

</center><br>

<?php
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
?>

<?php
$total_records_per_page = FILES_PER_PAGE;
?>

<?php
$offset = ($page_no-1) * $total_records_per_page;
$adjacents = "2";
?>

<?php
$total_records = get_files_total_count($myConnection);
$total_no_of_pages = max(1, ceil($total_records / $total_records_per_page));
$second_last = $total_no_of_pages - 1;
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
<br>
</center>

<?php
$file_rows = get_files_page($myConnection, $offset, $total_records_per_page);

if (empty($file_rows)) {
?>
<center><font class="txt-3" face="Verdana, sans-serif" size="3">No files available yet.</font></center>
<br>
<?php
}

foreach ($file_rows as $row) {
?>

<?php
	include 'table_content_files_sub.php';
?>

<?php
}
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
<br><br>
</center>

		</td>
	</tr>
</table>
