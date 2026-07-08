

<!-------- Get the current page number of pagination (if nothing is set, it is page number 1) ------------>
<?php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
$cat_id = intval($cat_id);
?>

<!-------- Set total records per page number for pagination ------------>
<?php
$total_records_per_page = 25;
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
$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) As total_records FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?)");
mysqli_stmt_bind_param($stmt_count, "iiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
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
echo " | <a href='?cat_id=$cat_id&page_no=1'>First Page</a>";
} ?> 
    
<?php if($page_no > 1){
echo " | <a href='?cat_id=$cat_id&page_no=$previous_page'>Previous Page</a>";
} 

if ($total_no_of_pages <= 10){  	 
	for ($counter = 1; $counter <= $total_no_of_pages; $counter++){
	if ($counter == $page_no) {
	echo " | <a><b>$counter</b></a>";	
	        }else{
        echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
                }
        }
}

elseif ($total_no_of_pages > 10){

if($page_no <= 4) {			
 for ($counter = 1; $counter < 8; $counter++){		 
	if ($counter == $page_no) {
	   echo " | <a>$counter</a>";	
		}else{
           echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
                }
}
echo " | <a>...</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$second_last'>$second_last</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

elseif($page_no > 4 && $page_no < $total_no_of_pages - 4) {		 
echo " | <a href='?cat_id=$cat_id&page_no=1'>1</a>";
echo " | <a href='?cat_id=$cat_id&page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $page_no - $adjacents;
     $counter <= $page_no + $adjacents;
     $counter++
     ) {		
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
          }                  
       }
echo " | <a>...</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$second_last'>$second_last</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>$total_no_of_pages</a>";
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
echo " | <a href='?cat_id=$cat_id&page_no=$next_page'>Next</a>";
} ?>


<?php if($page_no < $total_no_of_pages){
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
} ?>

</font>
</p>
</br>
</center>


<!-------- Show defined number of results ------------>
<?php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?) ORDER BY links_name ASC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, "iiiiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id, $offset, $total_records_per_page);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($line2 = mysqli_fetch_array($result)){
?>


<!-------- results table output ------------>


<?php

	include 'table_link.php';


			}


?>	


<!-------- Pagination menu bottom ------------>
<center>
<p>
<font face="Verdana, sans-serif" size=2>
Page <?php echo $page_no." of ".$total_no_of_pages; ?>

<?php if($page_no > 1){
echo " | <a href='?cat_id=$cat_id&page_no=1'>First Page</a>";
} ?> 
    
<?php if($page_no > 1){
echo " | <a href='?cat_id=$cat_id&page_no=$previous_page'>Previous Page</a>";
} 

if ($total_no_of_pages <= 10){  	 
	for ($counter = 1; $counter <= $total_no_of_pages; $counter++){
	if ($counter == $page_no) {
	echo " | <a><b>$counter</b></a>";	
	        }else{
        echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
                }
        }
}

elseif ($total_no_of_pages > 10){

if($page_no <= 4) {			
 for ($counter = 1; $counter < 8; $counter++){		 
	if ($counter == $page_no) {
	   echo " | <a>$counter</a>";	
		}else{
           echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
                }
}
echo " | <a>...</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$second_last'>$second_last</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>$total_no_of_pages</a>";
}

elseif($page_no > 4 && $page_no < $total_no_of_pages - 4) {		 
echo " | <a href='?cat_id=$cat_id&page_no=1'>1</a>";
echo " | <a href='?cat_id=$cat_id&page_no=2'>2</a>";
echo " | <a>...</a>";
for (
     $counter = $page_no - $adjacents;
     $counter <= $page_no + $adjacents;
     $counter++
     ) {		
     if ($counter == $page_no) {
	echo " | <a>$counter</a>";	
	}else{
        echo " | <a href='?cat_id=$cat_id&page_no=$counter'>$counter</a>";
          }                  
       }
echo " | <a>...</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$second_last'>$second_last</a>";
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>$total_no_of_pages</a>";
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
echo " | <a href='?cat_id=$cat_id&page_no=$next_page'>Next</a>";
} ?>


<?php if($page_no < $total_no_of_pages){
echo " | <a href='?cat_id=$cat_id&page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
} ?>

</font>
</p>
</br></br>
</center>


</center>
