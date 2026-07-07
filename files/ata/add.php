    <?php
    	include('conn.php');
     
    	$news_date  =$_POST['news_date'];
    	$news_story =$_POST['news_story'];
		$news_active=$_POST['news_active'];
     
	 
	 
    	mysqli_query($myConnection,"insert into t_news_sub (news_date,news_story,news_active) values ('$news_date','$news_story','$news_active')");
    	header('location:index.php');
     
    ?>