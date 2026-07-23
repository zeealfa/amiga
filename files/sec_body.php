
<?php
	require_once __DIR__ . '/includes/page_todo.php';
	$page_key = null;
?>
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
								<?php if ($_SESSION["content_type"]=='news'){ $page_key = 'content_news.php'; include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ $page_key = 'content_categories.php'; include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ $page_key = 'content_search.php'; include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ $page_key = 'content_new_sites.php'; include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ $page_key = 'content_archived_sites.php'; include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ $page_key = 'content_dead_sites.php'; include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ $page_key = 'content_top_rated.php'; include 'content_top_rated.php'; }
										else if($_SESSION["content_type"]=='advanced_search'){ $page_key = 'content_advanced_search.php'; include 'content_advanced_search.php'; }
										else if($_SESSION["content_type"]=='files'){ $page_key = 'content_files.php'; include 'content_files.php'; }

								?>
								<?php if ($page_key !== null) { render_page_todo($myConnection, $page_key); } ?>
						</td>
					</tr>
				</table>
		</td>
	</tr>
</table>
