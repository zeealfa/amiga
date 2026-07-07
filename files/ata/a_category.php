<?php	
include 'conn.php';

$result = mysqli_query(
    $myConnection,
    "SELECT * FROM t_cat_main ORDER BY cat_main_id"
    );
	
while($line2 = mysqli_fetch_array($result)){

    echo $line2['cat_main_id'].":  ". $line2['cat_main_title']."<br>";
}
//-----------------------------
echo "<br><br>";

$result = mysqli_query(
    $myConnection,
    "SELECT * FROM t_cat_sub ORDER BY cat_sub_id"
    );
	
while($line2 = mysqli_fetch_array($result)){

    echo $line2['cat_sub_id'].":  ". $line2['cat_sub_title']."<br>";
}





?>