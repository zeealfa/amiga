
<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="6">
 	<tr>
		<!----width of sidebar---->
		<td width="17%" valign="top" class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
				<?php include 'mod_sidebar_chooser.php'; ?>
		</td>
		<td valign="top" align="center" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<!----width of main content---->
				<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="0">
					<tr>
						<td>
								<?php if ($_SESSION["content_type"]=='news'){ include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }

								?>
						</td>
					</tr>
				</table>
		</td>
	</tr>
</table>
