<?php
require_once __DIR__ . '/includes/functions.php';

function render_category_tree_links($nodes)
{
    $out = '';
    foreach ($nodes as $node) {
        $out .= '&nbsp;&nbsp;&nbsp;<a href="entry_categories.php?cat_id=' . (int) $node['id'] . '">'
            . htmlspecialchars($node['title_short']) . '</a><br>';
        if (!empty($node['children'])) {
            $out .= render_category_tree_links($node['children']);
        }
    }
    return $out;
}

$category_tree = get_category_tree($myConnection);
foreach ($category_tree as $root) {
    echo '<br><b><u>' . htmlspecialchars($root['title']) . '</u></b><br>';
    if (!empty($root['children'])) {
        echo render_category_tree_links($root['children']);
    } else {
        // Root category with no children (e.g. a promoted orphan) is itself
        // a clickable category page.
        echo '&nbsp;&nbsp;&nbsp;<a href="entry_categories.php?cat_id=' . (int) $root['id'] . '">'
            . htmlspecialchars($root['title_short']) . '</a><br>';
    }
}
?>
