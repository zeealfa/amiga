<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$print_pubs = get_print_publications($myConnection);
	$hr="<hr>";

		foreach ($print_pubs as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['print_url'].">".$line1['print_name']."</a> (".$line1['print_issue'].")</li>";
			}
?>

</ul>





