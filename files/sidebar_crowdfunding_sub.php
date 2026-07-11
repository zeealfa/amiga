
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$cfund_rows = get_active_crowdfunding($myConnection);
$today = time();
	$hr="<hr>";
		foreach ($cfund_rows as $line1) {
		$to = strtotime($line1['cfund_date_end']);	$diff = $to - $today;
			if ($line1['cfund_name']==$hr) {
				//echo "<li> <a href=".$line1['url'].">".$line1['name']."</a>";
			} else
		$rd=round($diff / 86400)+1;
		//2 different formats to choose from
		echo "<li> <a target=\"_blank\" href=".$line1['cfund_url'].">".$line1['cfund_name']."</a> (days left: <b>".$rd."</b>)<br><br>";
			}
?>

</ul>
