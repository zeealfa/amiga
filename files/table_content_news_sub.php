<table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="3">
							<tr>
								<td align="left" valign="top" class="bg-red">
									<span class="txt-2-white">
										<b><?php
										echo $row['news_date'];?>
										</b><br>
									</span>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="4">
							<tr>
								<td align="left" valign="top" class="bg-offwhite">
									<span class="txt-2-black"><br>
										<b><u>Today's Highlights</u></b><br>

									<?php
										echo $row['news_story'];
									?>
									<br>
									</span>
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
