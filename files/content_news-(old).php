
<table width=100% align=center cellpadding=0 >

 	<tr>

		<td> 



<center><br> 

<table cellpadding="1" cellspacing="0" width="70%"  bgcolor="#637B94">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" bgcolor="#FFFFFF">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="15">
							<tr>
								<td align="center" valign="top" bgcolor="#FF2626">
									<font face="Verdana, sans-serif" size=6	color=#ffffff>
										<b>LATEST NEWS</b><br>
									<font face="Verdana, sans-serif" size=5	color=#ffffff>
										<b>Celebrating our 23th year</b><br>
								</td>
							</tr>
						</table>
						<center><br>
							<font face="Verdana, sans-serif" size=3	color=#000000>
								Previous Years<br>
							<font face="Verdana, sans-serif" size=2	color=#ffffff>
								<a href="action_history.php?yr=22">2022</a>
								<a href="content_news.php?yr=21">2021</a>
								<a href="content_news.php?yr=20">2020</a>
								<a href="content_news.php?yr=19">2019</a>
								<a href="content_news.php?yr=18">2018</a>
								<a href="content_news.php?yr=17">2017</a>
								<a href="content_news.php?yr=16">2016</a>
								<a href="content_news.php?yr=15">2015</a>
								<a href="content_news.php?yr=14">older</a>								

						<table width="100%"  cellspacing="0" cellpadding="0">
							<tr>
								<td align="left" valign="top" bgcolor="#ffffff">
								</td>
							</tr>
						</table>
						
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

</center>
<br>

<center>
<table width="30%" cellpadding="1" cellspacing="0" width="70%"  bgcolor="#637B94">
	<tr>
		<td>
			<table cellpadding="1"  cellspacing="1" bgcolor="#FFFFFF">
				<center>
				<font face="Verdana, sans-serif" size=3	color=#000000>
				<b>TEMP LINK COUNT</b><br>
				<font face="Verdana, sans-serif" size=2	color=#000000>

					<?php
						$result_count = mysqli_query(
						$myConnection,
							"SELECT COUNT(*) As total_records FROM t_links "
							);
								$total_records = mysqli_fetch_array($result_count);
								$total_records = $total_records['total_records'];
									echo "total records:".$total_records."<br>";
								$result_count2 = mysqli_query(
						
						$myConnection,
							"SELECT COUNT(*) As total_verified FROM t_links where (links_date_verified>'2021-12-01')"
								);
								$total_verified = mysqli_fetch_array($result_count2);
								$total_verified = $total_verified['total_verified'];
									echo "verified:".$total_verified."<br>";
							
							$total_left=$total_records-$total_verified;
									echo "# remaining:".$total_left."<br>";
								$result_count3 = mysqli_query(
								
						$myConnection,
							"SELECT COUNT(*) As total_new FROM t_links where (links_date_added>'2021-12-01')"
								);
								$total_new = mysqli_fetch_array($result_count3);
								$total_new = $total_new['total_new'];
									echo "new links:".$total_new."<br>";
					?>
					
				</center>
			</table>
		</td>
	</tr>
</table>

<br>
<font face="Verdana, sans-serif" size=6	color=#000000>
<br><center>17 Sep 23 - Site under construction!!  <br><br>I'm working on it!! ;-)</center><br>
<font face="Verdana, sans-serif" size=3	color=#000000>

 <?php

	echo "<br><br>";
	$yr3=$_GET['yr'];
		//echo "cn yr3:".$yr3."<br>";
		//echo "cn sd:".$sd."<br>";
		//echo "cn ed:".$ed."<br>";	

	if ($yr3 == '10') {

		//echo "this is 2023"."<br>";
		//$sd='2023-01-01';
		//$ed='2023-12-31';	
		
	}


	include 'table_content_news_sub.php';
				
?>



<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->



</font>
</p>
</br></br>
</center>		

