<?php	
$mc2=intval($_SESSION['mc']);
$cat_page_url="action_categories.php?cat_id=";

$sqlcommand="SELECT * from t_cat_sub where cat_sub_ref_main_id=$mc2 order by cat_sub_title_short";
$query2=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
$line2=mysqli_fetch_array($query2);	

	do { 
		echo"&nbsp;&nbsp;&nbsp;<a href=$cat_page_url".$line2['id'].">".$line2['cat_sub_title_short']."</a><br>";

		}
	while ($line2=mysqli_fetch_array($query2))
	
?>