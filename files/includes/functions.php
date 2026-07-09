<?php
function render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix = '')
{
    $previous_page = $page_no - 1;
    $next_page = $page_no + 1;
    $out = '';

    if ($page_no > 1) {
        $out .= " | <a href='?{$url_prefix}page_no=1'>First Page</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$previous_page'>Previous Page</a>";
    }

    if ($total_no_of_pages <= 10) {
        for ($counter = 1; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a><b>$counter</b></a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
    } elseif ($page_no <= 4) {
        for ($counter = 1; $counter < 8; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>$total_no_of_pages</a>";
    } elseif ($page_no > 4 && $page_no < $total_no_of_pages - 4) {
        $out .= " | <a href='?{$url_prefix}page_no=1'>1</a>";
        $out .= " | <a href='?{$url_prefix}page_no=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $page_no - $adjacents; $counter <= $page_no + $adjacents; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>$total_no_of_pages</a>";
    } else {
        $out .= " | <a href='?page_no=1'>1</a>";
        $out .= " | <a href='?page_no=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $total_no_of_pages - 6; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?page_no=$counter'>$counter</a>";
            }
        }
    }

    if ($page_no < $total_no_of_pages) {
        $out .= " | <a href='?{$url_prefix}page_no=$next_page'>Next</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
    }

    return $out;
}

// Returns rows from t_links whose links_url contains any whitespace-separated
// token from $url (case-insensitive), excluding soft-deleted rows.
// Ports the substring-match logic from files/ata/a_links_check_02.php into a
// reusable, prepared-statement-based function.
function find_similar_link_urls($myConnection, $url, $exclude_id = null)
{
    $needle = preg_replace("#^[^:/.]*[:/]+#i", "", $url);
    $tokens = array_filter(explode(' ', $needle), function ($t) {
        return trim($t) !== '';
    });

    if (empty($tokens)) {
        return [];
    }

    $sql = "SELECT id, links_name, links_url FROM t_links WHERE links_deleted_at IS NULL";
    $types = '';
    $params = [];

    if ($exclude_id !== null) {
        $sql .= " AND id <> ?";
        $types .= 'i';
        $params[] = $exclude_id;
    }

    $conditions = [];
    foreach ($tokens as $token) {
        $conditions[] = "links_url LIKE ?";
        $types .= 's';
        $params[] = '%' . $token . '%';
    }
    $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    $sql .= " ORDER BY links_name ASC";

    $stmt = mysqli_prepare($myConnection, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $matches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $matches[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $matches;
}

// Returns active categories as a nested array:
// [cat_main_id => ['title' => string, 'subs' => [cat_sub_id => sub_title, ...]], ...]
// Ordered by cat_main_title, then cat_sub_title within each group.
function get_category_tree($myConnection)
{
    $tree = [];

    $result = mysqli_query(
        $myConnection,
        "SELECT cat_main_id, cat_main_title FROM t_cat_main WHERE cat_main_active = 1 ORDER BY cat_main_title ASC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $tree[$row['cat_main_id']] = ['title' => $row['cat_main_title'], 'subs' => []];
    }

    $result = mysqli_query(
        $myConnection,
        "SELECT cat_sub_id, cat_sub_ref_main_id, cat_sub_title FROM t_cat_sub WHERE cat_sub_active = 1 ORDER BY cat_sub_title ASC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($tree[$row['cat_sub_ref_main_id']])) {
            $tree[$row['cat_sub_ref_main_id']]['subs'][$row['cat_sub_id']] = $row['cat_sub_title'];
        }
    }

    return $tree;
}
