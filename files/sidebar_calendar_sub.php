
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_cal ORDER BY cal_date_start ASC";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
$is_there_any_events='0';  //0= no current/future events.  1= yes current/future event
$today = date("Y-m-d");

	$line1=mysqli_fetch_array($query1);

		do {
			// variables for specific parts of cal_date_start/end.  this allows them to be *easily* used within the (3) if statements below  			
			$short_date_start_d=date("d", strtotime($line1['cal_date_start']));
			$short_date_start_m=date("m", strtotime($line1['cal_date_start']));
			$short_date_start_dm=date("d M", strtotime($line1['cal_date_start']));
			$short_date_end_dmy=date("d M y", strtotime($line1['cal_date_end']));
			$short_date_end_m=date("m", strtotime($line1['cal_date_end']));
			$short_date_end_d=date("d", strtotime($line1['cal_date_end']));
			
			if ($line1['cal_date_end']>=$today) {
 
				$is_there_any_events='1'; // set to 1 because there is at least one current/active event(s)
				
				// current/future event - same day - same month		
				if ($short_date_start_d == $short_date_end_d and $short_date_start_m == $short_date_end_m )
					{
					echo "<li> ".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";	
				}					
				
				// current/future event - multi day - same month	
				if ($short_date_start_m == $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
				
					echo "<li> ".$short_date_start_d."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				} 

				// current/future event - multi day - diff month				
				if ($short_date_start_m <> $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
					
					echo "<li> ".$short_date_start_dm."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				}
				
			} 
			
		}
				while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC));

			if ($is_there_any_events=='0') { //if it was never set to 1 above then there are no future events in the table
				
				echo "<li> None at this time <br>";
			}
				
?>

</ul>
