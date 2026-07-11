
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$vendor_rows = get_shop_vendors($myConnection);
	$hr="<hr>";

		foreach ($vendor_rows as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['vendor_url'].">".$line1['vendor_name']."</a> </li>";
			}
?>

</ul>
