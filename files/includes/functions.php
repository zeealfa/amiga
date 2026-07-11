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

// Returns active categories as an arbitrary-depth nested tree:
// [ ['id' => int, 'title' => string, 'title_short' => string, 'children' => [...]], ... ]
// Root categories (parent_id IS NULL) are the top-level array entries, each
// recursively nesting its own children in sort_order.
function get_category_tree($myConnection)
{
    $result = mysqli_query(
        $myConnection,
        "SELECT id, parent_id, title, title_short FROM t_categories WHERE active = 1 ORDER BY parent_id, sort_order"
    );

    $by_parent = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $build = function ($parent_key) use (&$build, $by_parent) {
        $nodes = [];
        foreach ($by_parent[$parent_key] ?? [] as $row) {
            $nodes[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'title_short' => $row['title_short'],
                'children' => $build((int) $row['id']),
            ];
        }
        return $nodes;
    };

    return $build(0);
}

// Returns a flat array of ints: $cat_id itself plus every descendant
// category id, regardless of the active flag (a link tagged with an
// inactive category should still be findable if reached directly, same
// as content_categories.php not filtering the requested cat_id by
// active). Used to roll up a category page's link listing to include
// links tagged with any child/grandchild/etc. category.
function get_category_descendant_ids($myConnection, $cat_id)
{
    $result = mysqli_query($myConnection, "SELECT id, parent_id FROM t_categories");

    $by_parent = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = (int) $row['id'];
    }

    $ids = [(int) $cat_id];
    $collect = function ($parent_id) use (&$collect, &$ids, $by_parent) {
        foreach ($by_parent[$parent_id] ?? [] as $child_id) {
            $ids[] = $child_id;
            $collect($child_id);
        }
    };
    $collect((int) $cat_id);

    return $ids;
}

// Renders a nested <ul>-free checkbox tree (indentation via &nbsp;) for
// picking up to 5 categories on the link add/edit forms. Root categories
// are rendered as bold/italic non-interactive headings; only descendants
// get a checkbox. Shared by files/admin/link_form.php and
// files/admin/link_submit.php.
function render_cat_checkboxes($nodes, $depth, $selected)
{
    foreach ($nodes as $node) {
        $is_root = $depth === 0;
        if ($is_root) {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<span style="font-weight:bold;font-style:italic;">'
                . htmlspecialchars($node['title']) . '</span><br>';
        } else {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<label><input type="checkbox" name="links_cats[]" value="' . $node['id'] . '" '
                . (in_array($node['id'], $selected, true) ? 'checked' : '') . '> '
                . htmlspecialchars($node['title']) . '</label><br>';
        }
        if (!empty($node['children'])) {
            render_cat_checkboxes($node['children'], $depth + 1, $selected);
        }
    }
}

// Returns all t_cal rows ordered by start date. Pure data fetch --
// the date-range formatting/branching logic stays in sidebar_calendar_sub.php.
function get_calendar_events($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_cal ORDER BY cal_date_start ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns active t_cfund rows ordered by end date. Pure data fetch --
// the days-remaining calculation stays in sidebar_crowdfunding_sub.php.
function get_active_crowdfunding($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_cfund WHERE cfund_active=1 ORDER BY cfund_date_end");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns all t_mags_online rows ordered by name.
function get_online_publications($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_mags_online ORDER BY online_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns all t_mags_print rows ordered by name.
function get_print_publications($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_mags_print ORDER BY print_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns all t_repair rows ordered by name.
function get_repair_vendors($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_repair ORDER BY repair_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns all t_vendor rows ordered by name.
function get_shop_vendors($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_vendor ORDER BY vendor_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns all t_top10 rows ordered by top10_order.
function get_top10_entries($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_top10 ORDER BY top10_order");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns the id of the lowest-id category, used as content_categories.php's
// fallback when no cat_id is given in the URL. Returns null if t_categories is empty.
function get_default_category_id($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT id FROM t_categories ORDER BY id ASC LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    return $row ? (int) $row['id'] : null;
}

// Returns t_categories rows matching the given id (0 or 1 row, since id is
// the primary key -- content_categories.php's do/while loop historically
// handled this as a general result set, preserved here as an array).
function get_category_rows($myConnection, $cat_id)
{
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $cat_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Returns link stats shown on the public news page: total link count,
// count ever verified, and count added in the last 7 days.
function get_link_stats($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_links");
    $total = mysqli_fetch_array($result)['total_records'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_verified FROM t_links where links_date_verified != '0000-00-00'");
    $verified = mysqli_fetch_array($result)['total_verified'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_new FROM t_links where links_date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new = mysqli_fetch_array($result)['total_new'];

    return ['total' => $total, 'verified' => $verified, 'new' => $new];
}

// Returns the count of active categories, shown on the public news page.
function get_active_category_count($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_categories FROM t_categories where active=1");
    return mysqli_fetch_array($result)['total_categories'];
}

// Returns the count of active, non-deleted t_news rows (used for pagination).
function get_news_total_count($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_news where news_active='1' AND news_deleted_at IS NULL");
    return mysqli_fetch_array($result)['total_records'];
}

// Returns one page of active, non-deleted t_news rows, newest first.
function get_news_page($myConnection, $offset, $limit)
{
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' AND news_deleted_at IS NULL ORDER BY news_date DESC LIMIT ?, ?");
    mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Records one audit log entry. $label is a human-readable snapshot (e.g. a
// link name or news date) captured at the time of the action, so the log
// stays readable even after the entity is later renamed or deleted.
function log_audit($myConnection, $entity_type, $entity_id, $action, $label, $user_id)
{
    $stmt = mysqli_prepare(
        $myConnection,
        "INSERT INTO t_audit_log (entity_type, entity_id, action, label, user_id) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'sissi', $entity_type, $entity_id, $action, $label, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
