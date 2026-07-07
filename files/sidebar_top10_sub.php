
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_top10 order by top10_order";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
			if ($line1['top10_name']==$hr) {
				echo "<hr>";
			} else
				echo "<li> <a target=\"_blank\" href=".$line1['top10_url'].">".$line1['top10_name']."</a> </li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
