<table width=100% align=center cellpadding=0 > 	
	<tr>
		<td> 
			<?php
			require_once __DIR__ . '/includes/functions.php';
			if (isset($_GET['cat_id'])) {
				$cat_id = intval($_GET['cat_id']);
			} else {
				$cat_id = get_default_category_id($myConnection) ?? 0;
			}
			$category_rows = get_category_rows($myConnection, $cat_id);

				foreach ($category_rows as $line1){
					$ph=$line1['title'];
					$pd=$line1['description'];
			?>
			
			<?php 
				echo "<title>AmigaSource.com - ".$ph."</title>";
			?>
			<br>
			
			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
				<tr>
				<td>

					<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
						<tr>
							<td>

								<table width="100%"  cellspacing="0" cellpadding="12">
									<tr>
										<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
											<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
												<b>
													<?php
														echo $ph;
													?>
												</b>
											</font>
										</td>
									</tr>
								</table>

								<table width="100%"  cellspacing="0" cellpadding="4">
									<tr>
										<td align="left" valign="top" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
											<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
												<center>
													<?php
														echo $pd;
													?>
												</center>
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

			<?php	}
			?>

			<?php
				include ("table_result_cat.php");
			?>

			<font class="txt-3" face="Verdana, sans-serif" size="3">
			<b>
			<center>

			<?php
				echo "<p>Total number of web sites found in this category: $total_records </p>";
			?>
			</center>
			</b>
			</font>
		</td>
	</tr>
</table>		