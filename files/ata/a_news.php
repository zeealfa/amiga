<!DOCTYPE html>
<html>
    <head>
    <title>Edit NEWS Page</title>
    </head>
			<center>
    <body>
	<h1> HOLY CRAP... It WORKS!!! (asdb) <br>
		 click on "edit" to make changes<br>
	<h4>
    	<br>
			<div>
		<form method="POST" action="add.php">
			<label>date:</label><input type="text" name="news_date">
			<label>story:</label><input type="text" name="news_story">
			<label>active:</label><input type="text" name="news_active">
			<input type="submit" name="add">
		</form><br><br>	</div>

    	<div>
    		<table border="1" width="80%">
			    <colgroup>
					<col span="1" style="width: 15%;">
					<col span="1" style="width: 55%;">
					<col span="1" style="width: 10%;">
					<col span="1" style="width: 10%;">
					<col span="1" style="width: 10%;">
					
    </colgroup>
    			<thead>
    				<th>Date</th>
    				<th>Post</th>
					<th>Record ID</th>
    				<th>Live?</th>
    				<th>Action</th>
    			</thead>
    			<tbody>
    				<?php
    					include('conn.php');
    					$query=mysqli_query($myConnection,"select * from t_news order by id desc" );
						while($row=mysqli_fetch_array($query)){
    						?>
    						<tr>

								<td><?php echo $row['news_date']; ?></td>
    							<td><?php echo $row['news_story']; ?></td>
								<td><?php echo $row['id']; ?></td>
    							<td><?php echo $row['news_active']; ?></td>
    							<td>
    								<a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
    								<a href="delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure to delete the entry no. <?php echo $row['id']; ?> ?')">Delete</a>
    							</td>
    						</tr>
    						<?php
    					}
    				?>
    			</tbody>
    		</table>
    	</div>
		</center>
    </body>
</html>