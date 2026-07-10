<table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="3">
							<tr>
								<td align="left" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>">
										<b><?php
										echo $row['news_date'];?>
										</b><br>
									</font>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="4">
							<tr>
								<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><br>
										<b><u>Today's Highlights</u></b><br>

									<?php
										echo $row['news_story'];
									?>
									<br>
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
<br>
