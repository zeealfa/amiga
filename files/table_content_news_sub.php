<table cellpadding="1" cellspacing="0" width="100%"  bgcolor="#637B94">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" bgcolor="#FFFFFF">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="3">
							<tr>
								<td align="left" valign="top" bgcolor="#FF2626">
									<font face="Verdana, sans-serif" size=2	color=#ffffff>
										<b><?php
										echo $row['news_date'];?>
										</b><br>
									</font>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="4">
							<tr>
								<td align="left" valign="top" bgcolor="#fafafa">
									<font face="Verdana, sans-serif" size=2	color=#000000><br>
										<b><u>Today's Highlights</u></b><br>

									<?php
										echo $row['news_story'];
									?>
									<br>		
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
