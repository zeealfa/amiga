    <?php

    	include('conn.php');

    	$id=$_GET['id'];

    	$query=mysqli_query($myConnection,"select * from t_news where id='$id'");

    	$row=mysqli_fetch_array($query);

	?>

<!DOCTYPE html>

<html>

    <head>

    <title>Basic MySQLi Commands</title>

    </head>

    <body>

    	<h2>Edit</h2>

		
		<form method="POST" action="update.php?id=<?php echo $id; ?>">

    		<label>date:</label>

			<input type="text" value="<?php echo $row['news_date']; ?>" name="news_date"> <br>
    		

			<label>story:</label>

			<textarea name="news_story" rows="30" cols="100" id="sub_text"><?php echo $row['news_story']; ?></textarea><br><br>
			

    		<label>active:</label><input type="text" value="<?php echo $row['news_active']; ?>" name="news_active"> 	
			

    		<input type="submit" name="submit">

    		<a href="index.php">Back</a>

    	</form>

		
	</body>

</html>