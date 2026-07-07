
<table bgcolor="#ffffff" width="100%" align="center" cellpadding="0" cellspacing="0">
 	<tr>
		<center>
			<td width="70%"> 				
				<table bgcolor="#ffffff" width="100%" align="center" cellpadding="6">					
					<tr>	
						<!----width of sidebar---->
						<td width="21%" valign="top" bgcolor="#bbbbbb">
								<?php include 'mod_sidebar_chooser.php'; ?>						
						</td>
		</center>
		<center>
			<td valign="top" bgcolor="#6699CC">
				<!----width of main content---->
				<table bgcolor="#6699CC" width="80%" align="center" cellpadding="0">
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
