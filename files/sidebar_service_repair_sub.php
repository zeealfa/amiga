
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$repair_rows = get_repair_vendors($myConnection);
	$hr="<hr>";

		foreach ($repair_rows as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['repair_url'].">".$line1['repair_name']."</a> </li>";
			}
?>

</ul>
