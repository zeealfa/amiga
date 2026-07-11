<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$online_pubs = get_online_publications($myConnection);
	$hr="<hr>";

		foreach ($online_pubs as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['online_url'].">".$line1['online_name']."</a> (".$line1['online_issue'].")</li>";
			}
?>

</ul>
