<table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>
						<table width="100%"  cellspacing="0" cellpadding="3">
							<tr>
								<td align="left" valign="top" class="bg-darkred" bgcolor="<?php echo bg_hex('darkred'); ?>">
									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">
										<b>Search</b><br>
									</font>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="4">
							<tr>
								<td align="center" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">


									<form action="/entry_search.php" method="post">
										<input type="text" name="search" size=25 maxlength=125>
										<p>
										<font class="txt-0-black" face="Verdana, sans-serif" size="0" color="<?php echo txt_hex('black'); ?>">
											<a href="/entry_advanced_search.php">Advanced Search</a><br><br>
										<input type="submit">
									</font></form>	<br>


								</td>		
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>	
	</tr>
</table>
<br> 