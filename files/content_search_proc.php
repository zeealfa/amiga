<?php
require_once __DIR__ . '/includes/functions.php';

$search_1 = $_POST['search'] ?? ($_GET['search'] ?? '');
$search_2 = $search_1;

$header_1 = "<center>Search Results for: <br> <b> <font size=6>";
$header_2 = "<font size=2> </b> <br>";
$respon_1 = "<br> To short...  Try again";
$respon_2 = "<br> To vague...  Try and narrow the search";
$respon_3 = "";

if ($search_1 === "amiga" || $search_1 === "amig" || $search_1 === "ami") {
    $search_2 = "";
}
?>
<br><table align=center cellpadding=2 cellspacing=0 border=1 width=50%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<br>
			<?php
				echo $header_1, htmlspecialchars($search_1), $header_2;
				if ($search_1 === "" || strlen($search_1) < 3) {
					echo $respon_1;
				} elseif (in_array($search_1, ["amiga", "amig", "ami"], true)) {
					echo $respon_2;
				} else {
					echo $respon_3;
				}
			?>
		</font>
	</td>
</table>
<br>
<?php
if ($search_2 !== "" && strlen($search_2) > 2) {
    if (!isset($_SESSION)) {
        session_start();
    }

    // $search_f is normally already set by entry_search.php (the only entry
    // point into this file) to the raw submitted term, and table_link.php
    // uses it to highlight matches in Links results. This fallback only
    // matters if content_search_proc.php is ever reached another way.
    $search_f = $search_f ?? $search_2;

    // Rate limiting only applies to a genuine new search submission (POST).
    // Pagination links below are plain GET requests and must never be
    // blocked or reset this timer -- they're just paging through results
    // the visitor already legitimately searched for.
    $is_new_search = $_SERVER['REQUEST_METHOD'] === 'POST';
    $wait_seconds = $is_new_search
        ? search_seconds_until_next_allowed($_SESSION['last_search_time'] ?? null, time())
        : 0;

    if ($wait_seconds > 0) {
?>
<center><font face="Verdana, sans-serif" size="4">Please wait <?php echo $wait_seconds; ?> more second<?php echo $wait_seconds === 1 ? '' : 's'; ?> before searching again.</font></center>
<?php
    } else {
        if ($is_new_search) {
            $_SESSION['last_search_time'] = time();
        }

        $like = '%' . $search_2 . '%';
        $search_url_prefix = 'search=' . urlencode($search_2) . '&';
        $any_results = false;

        // ---- Links ----
        $page_links = isset($_GET['page_links']) && $_GET['page_links'] !== '' ? max(1, intval($_GET['page_links'])) : 1;
        $links_result = fetch_paginated_search_results(
            $myConnection,
            '*',
            't_links',
            'links_deleted_at IS NULL AND (links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)',
            'ssss',
            [$like, $like, $like, $like],
            'links_name',
            $page_links,
            SEARCH_RESULTS_PER_PAGE
        );
        if ($links_result['total'] > 0) {
            $any_results = true;
            $links_pagination_html = render_pagination_menu($page_links, $links_result['total_pages'], $links_result['total_pages'] - 1, 2, $search_url_prefix, 'page_links');
?>
<center><font face="Verdana, sans-serif" size="4"><b>Links</b> (<?php echo $links_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_links . ' of ' . $links_result['total_pages']; ?><?php echo $links_pagination_html; ?></font></center>
<?php
            foreach ($links_result['rows'] as $line2) {
                include 'table_result_search.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_links . ' of ' . $links_result['total_pages']; ?><?php echo $links_pagination_html; ?></font></center>
<br>
<?php
        }

        // ---- News ----
        $page_news = isset($_GET['page_news']) && $_GET['page_news'] !== '' ? max(1, intval($_GET['page_news'])) : 1;
        $news_result = fetch_paginated_search_results(
            $myConnection,
            'n.*, u.username AS submitter_username',
            't_news n LEFT JOIN t_users u ON u.id = n.submitted_by',
            "n.news_active = 1 AND n.news_deleted_at IS NULL AND (n.news_story LIKE ? OR COALESCE(u.username, '') LIKE ?)",
            'ss',
            [$like, $like],
            'n.news_date DESC',
            $page_news,
            SEARCH_RESULTS_PER_PAGE
        );
        if ($news_result['total'] > 0) {
            $any_results = true;
            $news_pagination_html = render_pagination_menu($page_news, $news_result['total_pages'], $news_result['total_pages'] - 1, 2, $search_url_prefix, 'page_news');
?>
<center><font face="Verdana, sans-serif" size="4"><b>News</b> (<?php echo $news_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_news . ' of ' . $news_result['total_pages']; ?><?php echo $news_pagination_html; ?></font></center>
<?php
            foreach ($news_result['rows'] as $row) {
                include 'table_search_news_row.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_news . ' of ' . $news_result['total_pages']; ?><?php echo $news_pagination_html; ?></font></center>
<br>
<?php
        }

        // ---- Simple sections: Calendar, Crowdfunding, Publications, Repair, Vendors, Top 10 ----
        $simple_sections = [
            'cal' => [
                'heading' => 'Calendar Events',
                'from' => 't_cal',
                'where' => '(cal_name LIKE ? OR cal_url LIKE ? OR cal_location LIKE ?)',
                'types' => 'sss',
                'like_count' => 3,
                'order_by' => 'cal_name ASC',
                'name_field' => 'cal_name',
                'url_field' => 'cal_url',
                'extra_label' => 'Location',
                'extra_field' => 'cal_location',
            ],
            'cfund' => [
                'heading' => 'Crowdfunding',
                'from' => 't_cfund',
                'where' => 'cfund_active = 1 AND (cfund_name LIKE ? OR cfund_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'cfund_name ASC',
                'name_field' => 'cfund_name',
                'url_field' => 'cfund_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'online' => [
                'heading' => 'Online Publications',
                'from' => 't_mags_online',
                'where' => '(online_name LIKE ? OR online_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'online_name ASC',
                'name_field' => 'online_name',
                'url_field' => 'online_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'print' => [
                'heading' => 'Print Publications',
                'from' => 't_mags_print',
                'where' => '(print_name LIKE ? OR print_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'print_name ASC',
                'name_field' => 'print_name',
                'url_field' => 'print_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'repair' => [
                'heading' => 'Repair & Service',
                'from' => 't_repair',
                'where' => '(repair_name LIKE ? OR repair_url LIKE ? OR repair_country LIKE ?)',
                'types' => 'sss',
                'like_count' => 3,
                'order_by' => 'repair_name ASC',
                'name_field' => 'repair_name',
                'url_field' => 'repair_url',
                'extra_label' => 'Country',
                'extra_field' => 'repair_country',
            ],
            'vendor' => [
                'heading' => 'Shops & Vendors',
                'from' => 't_vendor',
                'where' => '(vendor_name LIKE ? OR vendor_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'vendor_name ASC',
                'name_field' => 'vendor_name',
                'url_field' => 'vendor_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'top10' => [
                'heading' => 'Top 10',
                'from' => 't_top10',
                'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'top10_name ASC',
                'name_field' => 'top10_name',
                'url_field' => 'top10_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
        ];

        foreach ($simple_sections as $section_key => $section) {
            $page_param = 'page_' . $section_key;
            $page_no = isset($_GET[$page_param]) && $_GET[$page_param] !== '' ? max(1, intval($_GET[$page_param])) : 1;
            $section_result = fetch_paginated_search_results(
                $myConnection,
                '*',
                $section['from'],
                $section['where'],
                $section['types'],
                array_fill(0, $section['like_count'], $like),
                $section['order_by'],
                $page_no,
                SEARCH_RESULTS_PER_PAGE
            );
            if ($section_result['total'] === 0) {
                continue;
            }
            $any_results = true;
            $section_pagination_html = render_pagination_menu($page_no, $section_result['total_pages'], $section_result['total_pages'] - 1, 2, $search_url_prefix, $page_param);
?>
<center><font face="Verdana, sans-serif" size="4"><b><?php echo htmlspecialchars($section['heading']); ?></b> (<?php echo $section_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_no . ' of ' . $section_result['total_pages']; ?><?php echo $section_pagination_html; ?></font></center>
<?php
            foreach ($section_result['rows'] as $row) {
                include 'table_search_simple_row.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_no . ' of ' . $section_result['total_pages']; ?><?php echo $section_pagination_html; ?></font></center>
<br>
<?php
        }

        if (!$any_results) {
?>
<center><font face="Verdana, sans-serif" size="4">Nothing found. Please try again!</font></center>
<?php
        }
    }
} else {
?>
<center><font face="verdana, sans-serif" size="4"><b>Please enter a valid search.</b></font></center>
<?php
}
?>
