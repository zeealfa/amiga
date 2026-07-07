    <?php
    	$id=$_GET['id'];
    	include('conn.php');
    	mysqli_query($myConnection,"delete from t_news where id='$id'");
    	header('location:index.php');
    ?>