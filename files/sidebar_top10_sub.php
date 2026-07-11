
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$top10_rows = get_top10_entries($myConnection);
	$hr="<hr>";

		foreach ($top10_rows as $line1) {
			if ($line1['top10_name']==$hr) {
				echo "<hr>";
			} else
				echo "<li> <a target=\"_blank\" href=".$line1['top10_url'].">".$line1['top10_name']."</a> </li>";
			}
?>

</ul>
