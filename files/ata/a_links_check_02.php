<link rel="stylesheet" href="/style.css">
<?php include __DIR__ . '/../legacy_colors.php'; ?>
<a href="http://testamigasource.com/ata/a_links_check.php">new search</a><br><br>

<?php	
include 'conn.php';

$input = $_POST["check_url"];

echo 'input: '.$input.'<br>';
$needle = preg_replace( "#^[^:/.]*[:/]+#i", "", $input );

$result = mysqli_query(
    $myConnection,
    "SELECT * FROM t_links ORDER BY links_name "
    );
while($line2 = mysqli_fetch_array($result)){
//--------------------------------------------------------
$haystack = $line2['links_url'];
//$needle   = $_POST["check_url"];
$needles  = explode(' ', $needle);
$partial  = false;

foreach($needles as $needle)
{
    if(stripos($haystack, $needle) !== false)
    {
        $partial = true;
        break;
    }
}

if($partial)
{
    echo '<b><font class="txt-6" face="Verdana, sans-serif" size="6"> **************right here:</b> id: '.$line2['id']."  ".$line2['links_url']."<font class=\"txt-3\" face=\"Verdana, sans-serif\" size=\"3\"><br>";
		//echo  "id".$line2['id']."  ".$line2['links_url']."<font size=3><br>";
}
else
{
    //echo 'not found: ',$partial;
}
//--------------------------------------------------------
	//echo $line2['links_url']."<br>";
}
?>