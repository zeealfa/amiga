
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_cfund where cfund_active=1 order by cfund_date_end";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
$today = time();
	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";
		do {
		$to = strtotime($line1['cfund_date_end']);	$diff = $to - $today;	
			if ($line1['cfund_name']==$hr) {
				//echo "<li> <a href=".$line1['url'].">".$line1['name']."</a>";
			} else
		$rd=round($diff / 86400)+1;		
		//2 different formats to choose from			
		echo "<li> <a target=\"_blank\" href=".$line1['cfund_url'].">".$line1['cfund_name']."</a> (days left: <b>".$rd."</b>)<br><br>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
