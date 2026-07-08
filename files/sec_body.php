
<table class="bg-fff" width="100%" align="center" cellpadding="0" cellspacing="0">
 	<tr>
		<center>
			<td width="70%">
				<table class="bg-fff" width="100%" align="center" cellpadding="6">
					<tr>
						<!----width of sidebar---->
						<td width="21%" valign="top" class="bg-bbbbbb">
								<?php include 'mod_sidebar_chooser.php'; ?>
						</td>
		</center>
		<center>
			<td valign="top" class="bg-fff">
				<!----width of main content---->
				<table class="bg-fff" width="80%" align="center" cellpadding="0">
					<tr>
						<td>
								<?php if ($_SESSION["content_type"]=='news'){ include 'content_news.php'; } 
										else if($_SESSION["content_type"]=='categories'){ include 'content_categories.php'; } 
										else if($_SESSION["content_type"]=='search'){ include 'content_search.php'; } 

								?>
						</td>
					</tr>
				</table>		
		</center>		
	</tr>
</table>
