<?php

    	include('conn.php');

    	$id=$_GET['id'];


	    $news_date=$_POST['news_date'];

    	$news_story=$_POST['news_story'];

		$news_active=$_POST['news_active'];

     

    	mysqli_query($myConnection,"update t_news set news_date='$news_date', news_story='$news_story', news_active='$news_active' where id='$id'");

    	header('location:index.php');

?>