<table width=100% align=center cellpadding=0 > 	
	<tr>
		<td> 
			<?php	
			$cat_id = $_GET['cat_id'];	$sqlcommand="SELECT * FROM t_cat_sub where cat_sub_id=$cat_id";
			$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
			$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);

				do{
					$ph=$line1['cat_sub_title'];
					$pd=$line1['cat_sub_desc'];
			?>
			
			<?php 
				echo "<title>AmigaSource.com - ".$ph."</title>";
			?>
			<br>
			
			<table align=center cellpadding="1" cellspacing="0" width="50%"  bgcolor="#637B94">	
				<tr>
				<td>

					<table width="100%" cellpadding="1"  cellspacing="0" bgcolor="#FFFFFF">			
						<tr>
							<td>
								
								<table width="100%"  cellspacing="0" cellpadding="12">	
									<tr>
										<td align="center" valign="top" bgcolor="#FF2626">
											<font face="Verdana, sans-serif" size=6 color=#ffffff>		
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
										<td align="left" valign="top" bgcolor="#F4F4F4">
											<font face="Verdana, sans-serif" size=4	color=#000000>	
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
				while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
			?>	
		
			<?php
				include ("table_result_cat.php");
			?>

			<font face="Verdana, sans-serif" size=3>
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