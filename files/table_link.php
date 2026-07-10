<!-------- results table output ------------>
<?php
	// variable used for the AO function
	$ao = "https://web.archive.org/web/2020*/";

?>
<center>
	<table cellpadding="1" cellspacing="0" width="95%"  class="bg-darkolive" bgcolor="<?php echo bg_hex('darkolive'); ?>">
		<tr><td>
			<table width="100%" cellpadding="0"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr><td>
					<table width="100%"  cellspacing="0" cellpadding="0">
							<tr>
									<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">

									<!----------results icon pic (row 1: !C-2A) --------->
										<table width=100%>
											<TR>
												<TD rowspan="2" width="5%" align="center">
												
													<!----- NEW ICON: if date later than 2015-01-25 ----->
														<?php
															if ($line2['links_date_added']>'2015-01-25') { 
														?>
															<img src="/web_images/static/sm_new.gif" width=26 height=26> 

													<!----- DEAD ICON: if there is NO url ----->								
														<?php
															} elseif ($line2['links_url'] and $line2['links_archived_url']==='') {
															
														?>
															<img src="/web_images/static/sm_dead.gif" width=26 height=26> 
													
													<!----- ARCHIVED ICON: if there is NO url ----->							
														<?php
														
															} elseif ($line2['links_archived_url']<>'') { 
															
														?>
															<img src="/web_images/static/sm_archived.jpg" width=26 height=26>

													<!----- BOING BALL: else display boing ball ----->
														<?php
															} else {				
														?>
															<img src="/web_images/static/sm_boing.jpg" width=26 height=26>
														<?php
															}
														?>
												</TD>

									<!----------results site name as a link output (row 1: !C-2B) --------->
												<TD colspan="2" width=100% class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">&nbsp;
													<a target=new href="
													<?php
														if ($line2['links_archived_url']<>null and $line2['links_active']="1") { ?>
															<font class="txt-1" face="Verdana, sans-serif" size="1">
															<a target=new href="<?php echo $line2['links_archived_url'] ?>">

													<?php
														} else { ?>
															<font class="txt-1" face="Verdana, sans-serif" size="1">
															<a target=new href="<?php echo $line2['links_url'] ;?>">

							<!----- use to add a link back to as
							<a target=new href=" <?php // echo $line2['links_url']."?utm-source=amigasource.com";?>"   -->

															<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"> <b>
													<?php
														}
													?>
															<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"> <b>
																<?php 
																	$str=$line2['links_name'];
																	if(!isset($search_f)){
																	echo $str;
																	} else {
																	$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
																	echo $str2;
															}
																?>
															</b></a>
												</TD>
											</TR>
										</table>
									<!----------results author & date added output (row 2: !C-2C) --------->
										 <table width=100%>
											<TR>
											<!----- if ARCHIVED then date archived and verified ----->					
												<?php
													if ($line2['links_archived_url']<>'') { 
												?>
														<TD width=40% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
														<font class="txt-2" face="Verdana, sans-serif" size="2"><b> Author:</>
														<a href="<?php echo $line2['links_email'];?>">
															<?php
																$str=$line2['links_author'];
																if(!isset($search_f)){
																echo $str;
																} else {
																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
																echo $str2;
																}
															?></a>
														</TD>
														<TD width=30% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
														<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Archived:</b>
														<?php echo $line2['links_archived_date'];?>
														</TD>
														<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
														<?php
															if ($line2['links_date_verified']>'2021-12-21') {
														?>
															<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
															<font class="txt-2" face="Verdana, sans-serif" size="2">
														<?php 	} else {
														?>
															<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
														<?php
														}
														?>
														<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
														<font class="txt-2" face="Verdana, sans-serif" size="2">
														</TD>
													<?php
														} else {
													?>	
													<!----- AUTHOR ----->	
													<TD width=70% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
														<font class="txt-2" face="Verdana, sans-serif" size="2"><b> Author:</>
														<a href="<?php echo $line2['links_email'];?>">
															<?php
																$str=$line2['links_author'];
																if(!isset($search_f)){
																echo $str;
																} else {
																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
																echo $str2;
																}
															?></a>
													</TD>
														<!----- just date verified ----->
															<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
															<?php
																if ($line2['links_date_verified']>'2021-12-21') {
															?>
																<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
																<font class="txt-2" face="Verdana, sans-serif" size="2">
															<?php 	} else {
															?>
																<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
															<?php
															}
															?>
															<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
															<font class="txt-2" face="Verdana, sans-serif" size="2">
															</TD>
														<?php	
														}
														?>
											</TR>
										</table>
									<!----------results description output (row 3): !c-2E)  ---------> 
										<table width=100%>
											<TR>
												<TD colspan="3" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">&nbsp;
													<font class="txt-2" face="Verdana, sans-serif" size="2">
														<?php
															$str=$line2['links_desc'];

															if ($line2['links_archived_url']<>'') {
															echo "<b> {{ARCHIVED}} </b>";
															}
																if(!isset($search_f)){
																echo $str;
																} else {
																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
																echo $str2;
																}
														 ?>
												</TD>
											</TR>
										</table>
									<!----------extras bar & archived stuff (row 4) --------->
										<table width=100%>
											<TR>
												<!----------results category numbers (row 4: !C-2g) --------->
												<TD  class="bg-lightgray" width=15% bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
													<font class="txt-2" face="Verdana, sans-serif" size="2">
													<?php
														echo "<a target=\"_blank\" href=$ao".$line2['links_url'].">archive.org</a>";
													?>
												</TD>
												<!----------results id #### (row 4: !C-2i) --------->
												<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
													<font class="txt-2" face="Verdana, sans-serif" size="2">
													<b> id: </b>
													<?php echo $line2['id'];?>
													<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
														&nbsp;<a href="admin/link_form.php?id=<?php echo (int) $line2['id']; ?>" target="_blank">Edit</a>
													<?php endif; ?>
												</TD>
											</TR>
										</table>
										
			</td></tr></table>
			</td></tr></table>
			</td></tr></table>
	<table>
		<tr>
			<font class="txt-1" face="Verdana, sans-serif" size="1">
			&nbsp;
		</tr>
	</table>
</center>