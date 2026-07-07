
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_vendor order by vendor_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
				echo "<li> <a target=\"_blank\" href=".$line1['vendor_url'].">".$line1['vendor_name']."</a> </li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
