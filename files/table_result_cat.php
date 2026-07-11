

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
$total_records_per_page = LINKS_PER_PAGE;
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
require_once __DIR__ . '/includes/functions.php';
$descendant_ids = get_category_descendant_ids($myConnection, $cat_id);
$id_placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
$id_types = str_repeat('i', count($descendant_ids));

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(DISTINCT l.id) As total_records FROM t_links l JOIN t_link_categories lc ON lc.link_id = l.id WHERE (l.links_active=1 or l.links_archived_url<>'') and lc.category_id IN ($id_placeholders)");
mysqli_stmt_bind_param($stmt_count, $id_types, ...$descendant_ids);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_array($result_count);
$total_records = $total_records['total_records'];
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, "cat_id=$cat_id&");
?>


<!-------- Pagination menu top ------------>
<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
</br>
</center>


<!-------- Show defined number of results ------------>
<?php
$list_types = $id_types . 'ii';
$list_params = array_merge($descendant_ids, [$offset, $total_records_per_page]);
$stmt = mysqli_prepare($myConnection, "SELECT DISTINCT l.* FROM t_links l JOIN t_link_categories lc ON lc.link_id = l.id WHERE (l.links_active=1 or l.links_archived_url<>'') and lc.category_id IN ($id_placeholders) ORDER BY l.links_name ASC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
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
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
</br></br>
</center>


</center>
