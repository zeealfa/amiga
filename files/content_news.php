
<table width=100% align=center cellpadding=0 >

 	<tr>

		<td> 



<center><br> 

<table cellpadding="1" cellspacing="0" width="70%"  bgcolor="#637B94">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" bgcolor="#FFFFFF">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="15">
							<tr>
								<td align="center" valign="top" bgcolor="#FF2626">
									<font face="Verdana, sans-serif" size=6	color=#ffffff>
										<b>LATEST NEWS</b><br>
									<font face="Verdana, sans-serif" size=5	color=#ffffff>
										<b>Celebrating our 23rd year</b><br>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="0">
							<tr>
								<td align="left" valign="top" bgcolor="#ffffff">
								</td>
							</tr>
						</table>
						
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

</center><br>

<center>
<table width="30%" cellpadding="1" cellspacing="0" width="70%"  bgcolor="#637B94">
	<tr>
		<td>
			<table cellpadding="1"  cellspacing="1" bgcolor="#FFFFFF">
				<center>
				<font face="Verdana, sans-serif" size=3	color=#000000>
				<b>TEMP LINK COUNT</b><br>
				<font face="Verdana, sans-serif" size=2	color=#000000>
				<?php
				$result_count = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_records FROM t_links "
				);
				$total_records = mysqli_fetch_array($result_count);
				$total_records = $total_records['total_records'];
				echo "total records:".$total_records."<br>";

				$result_count2 = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_verified FROM t_links where (links_date_verified>'2021-12-01')"
				);
				$total_verified = mysqli_fetch_array($result_count2);
				$total_verified = $total_verified['total_verified'];
				echo "verified:".$total_verified."<br>";
				
				$total_left=$total_records-$total_verified;
				echo "# remaining:".$total_left."<br>";

				$result_count3 = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_new FROM t_links where (links_date_added>'2021-12-01')"
				);
				$total_new = mysqli_fetch_array($result_count3);
				$total_new = $total_new['total_new'];
				echo "new links:".$total_new."<br>";
				?>
				</center>
			</table>
		</td>
	</tr>
</table>

<!-------- Get the current page number of pagination (if nothing is set, it is page number 1) ------------>
<?php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = $_GET['page_no'];
    } else {
        $page_no = 1;
        }
?>

<!-------- Set total records per page number for pagination ------------>
<?php
$total_records_per_page = 5;
?>

<!-------- Set offset and page calculation for pagination ------------>
<?php
$offset = ($page_no-1) * $total_records_per_page;
$previous_page = $page_no - 1;
$next_page = $page_no + 1;
$adjacents = "2";
?>

<!-------- Calculate total pages for pagination ------------>
<?php
$result_count = mysqli_query(
$myConnection,
"SELECT COUNT(*) As total_records FROM t_news where news_active='1'"
);
$total_records = mysqli_fetch_array($result_count);
$total_records = $total_records['total_records'];
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
?>


<!-------- Pagination menu top ------------>
<center>
<p>
<font face="Verdana, sans-serif" size=2>
Page <?php echo $page_no." of ".$total_no_of_pages; ?>

<?php if($page_no > 1){
echo " | <a href='?page_no=1'>First Page</a>";
} ?> 
    
<?php if($page_no > 1){
echo " | <a href='?page_no=$previous_page'>Previous Page</a>";
} 

if ($total_no_of_pages <= 10){  	 
	for ($counter = 1; $counter <= $total_no_of_pages; $counter++){
	if ($counter == $page_no) {
	echo " | <a><b>$counter</b></a>";	
	        }else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
                }
        }
}

elseif ($total_no_of_pages > 10){

if($page_no <= 4) {			
 for ($counter = 1; $counter < 8; $counter++){		 
	if ($counter == $page_no) {
	   echo " | <a>$counter</a>";	
		}else{
           echo " | <a href='?page_no=$counter'>$counter</a>";
                }
}
echo " | <a>...</a>";
echo " | <a href='?page_no=$second_last'>$second_last</a>";
echo " | <a href='?page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

elseif($page_no > 4 && $page_no < $total_no_of_pages - 4) {		 
echo " | <a href='?page_no=1'>1</a>";
echo " | <a href='?page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $page_no - $adjacents;
     $counter <= $page_no + $adjacents;
     $counter++
     ) {		
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
          }                  
       }
echo " | <a>...</a>";
echo " | <a href='?page_no=$second_last'>$second_last</a>";
echo " | <a href='?page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

else {
echo " | <a href='?page_no=1'>1</a>";
echo " | <a href='?page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $total_no_of_pages - 6;
     $counter <= $total_no_of_pages;
     $counter++
     ) {
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
	}                   
     }
}

}

?>

    
<?php if($page_no < $total_no_of_pages) {
echo " | <a href='?page_no=$next_page'>Next</a>";
} ?>


<?php if($page_no < $total_no_of_pages){
echo " | <a href='?page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
} ?>

</font>
</p>
</br>
</center>


 <?php

		$result = mysqli_query(
		$myConnection,
		"SELECT * FROM t_news where news_active='1' ORDER BY news_date DESC LIMIT $offset, $total_records_per_page"
		);
		while($row = mysqli_fetch_array($result)){	
		
?>



<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->

<!-------------------------------------------------------------------------------------------------->

<?php
	include 'table_content_news_sub.php';
?>	

<?php 						

		}

?>

<!-------- Pagination bottom top ------------>
<center>
<p>
<font face="Verdana, sans-serif" size=2>
Page <?php echo $page_no." of ".$total_no_of_pages; ?>

<?php if($page_no > 1){
echo " | <a href='?page_no=1'>First Page</a>";
} ?> 
    
<?php if($page_no > 1){
echo " | <a href='?page_no=$previous_page'>Previous Page</a>";
} 

if ($total_no_of_pages <= 10){  	 
	for ($counter = 1; $counter <= $total_no_of_pages; $counter++){
	if ($counter == $page_no) {
	echo " | <a><b>$counter</b></a>";	
	        }else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
                }
        }
}

elseif ($total_no_of_pages > 10){

if($page_no <= 4) {			
 for ($counter = 1; $counter < 8; $counter++){		 
	if ($counter == $page_no) {
	   echo " | <a>$counter</a>";	
		}else{
           echo " | <a href='?page_no=$counter'>$counter</a>";
                }
}
echo " | <a>...</a>";
echo " | <a href='?page_no=$second_last'>$second_last</a>";
echo " | <a href='?page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

elseif($page_no > 4 && $page_no < $total_no_of_pages - 4) {		 
echo " | <a href='?page_no=1'>1</a>";
echo " | <a href='?page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $page_no - $adjacents;
     $counter <= $page_no + $adjacents;
     $counter++
     ) {		
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
          }                  
       }
echo " | <a>...</a>";
echo " | <a href='?page_no=$second_last'>$second_last</a>";
echo " | <a href='?page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

else {
echo " | <a href='?page_no=1'>1</a>";
echo " | <a href='?page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $total_no_of_pages - 6;
     $counter <= $total_no_of_pages;
     $counter++
     ) {
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?page_no=$counter'>$counter</a>";
	}                   
     }
}

}

?>

    
<?php if($page_no < $total_no_of_pages) {
echo " | <a href='?page_no=$next_page'>Next</a>";
} ?>


<?php if($page_no < $total_no_of_pages){
echo " | <a href='?page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
} ?>

</font>
</p>
</br></br>
</center>		

