
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
										<b>LATEST NEWS</b><br>
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>">
										<b>Celebrating our 23rd year</b><br>
									</font></font>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="0">
							<tr>
								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
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

<center>
<table width="70%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
						<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"><b>LINK STATS</b></font>
					</td>
				</tr>
				<tr>
					<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
						<table width="100%" cellpadding="6" cellspacing="8">
							<tr>
							<?php
							require_once __DIR__ . '/includes/functions.php';
							$link_stats = get_link_stats($myConnection);
							$total_records = $link_stats['total'];
							$total_verified = $link_stats['verified'];
							$total_left = $total_records - $total_verified;
							$total_new = $link_stats['new'];

							$link_metric_boxes = [
								['Total Links',        $total_records, 'blue'],
								['Verified',           $total_verified, 'green'],
								['Remaining',          $total_left,    'orange'],
								['New (Last 7 Days)',  $total_new,     'purple'],
							];
							foreach ($link_metric_boxes as $box):
								[$label, $value, $color] = $box;
							?>
								<td width="25%" align="center" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<table width="100%" cellpadding="6" cellspacing="0">
										<tr>
											<td align="center" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
												<font class="txt-4" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex($color); ?>"><b><?php echo $value; ?></b></font><br>
												<font class="txt-1" face="Verdana, sans-serif" size="1" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($label); ?></font>
											</td>
										</tr>
									</table>
								</td>
							<?php endforeach; ?>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-------- Get the current page number of pagination (if nothing is set, it is page number 1) ------------>
<?php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
?>

<!-------- Set total records per page number for pagination ------------>
<?php
$total_records_per_page = NEWS_PER_PAGE;
?>

<!-------- Set offset and page calculation for pagination ------------>
<?php
$offset = ($page_no-1) * $total_records_per_page;
$previous_page = $page_no - 1;
$next_page = $page_no + 1;
$adjacents = "2";
?>

<!-------- Calculate total pages for pagination ------------>
<?php
$total_records = get_news_total_count($myConnection);
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>


<!-------- Pagination menu top ------------>
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

		$news_rows = get_news_page($myConnection, $offset, $total_records_per_page);
		foreach ($news_rows as $row) {

?>



<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->

<?php
	include 'table_content_news_sub.php';
?>	

<?php 						

		}

?>

<!-------- Pagination bottom top ------------>
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

