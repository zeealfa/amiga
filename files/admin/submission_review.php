<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_submissions WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$submission) {
    header('Location: submissions.php');
    exit;
}

if ($submission['status'] !== 'pending') {
    $_SESSION['flash_message'] = 'This submission has already been reviewed.';
    header('Location: submissions.php');
    exit;
}

// Flat id => title map, used to render category names in the diff view.
$category_names = [];
$cat_result = mysqli_query($myConnection, "SELECT id, title FROM t_categories");
while ($cat_row = mysqli_fetch_assoc($cat_result)) {
    $category_names[(int) $cat_row['id']] = $cat_row['title'];
}

function category_names_from_csv($csv, $category_names)
{
    $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
    if (empty($ids)) {
        return '(none)';
    }
    $names = array_map(function ($catId) use ($category_names) {
        return $category_names[$catId] ?? "#$catId";
    }, $ids);
    return htmlspecialchars(implode(', ', $names));
}

$current = null;
if ($submission['action'] === 'edit') {
    if ($submission['type'] === 'link') {
        $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $submission['target_id']);
        mysqli_stmt_execute($stmt);
        $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($current) {
            $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
            mysqli_stmt_bind_param($cats_stmt, 'i', $submission['target_id']);
            mysqli_stmt_execute($cats_stmt);
            $cat_ids = [];
            $cats_result = mysqli_stmt_get_result($cats_stmt);
            while ($row = mysqli_fetch_assoc($cats_result)) {
                $cat_ids[] = (int) $row['category_id'];
            }
            mysqli_stmt_close($cats_stmt);
            $current['category_ids'] = implode(',', $cat_ids);
        }
    } else {
        $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $submission['target_id']);
        mysqli_stmt_execute($stmt);
        $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    if (!$current) {
        $reason = 'Automatically rejected: the original item no longer exists.';
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_submissions SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $reason, $_SESSION['user_id'], $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = 'Submission auto-rejected: the original item it edits no longer exists.';
        header('Location: submissions.php');
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $stmt = mysqli_prepare($myConnection, "SELECT status FROM t_submissions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $status_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$status_row || $status_row['status'] !== 'pending') {
        $_SESSION['flash_message'] = 'This submission has already been reviewed.';
        header('Location: submissions.php');
        exit;
    }

    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_submissions SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $_SESSION['user_id'], $id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_affected_rows($myConnection);
    mysqli_stmt_close($stmt);

    if ($affected === 0) {
        $_SESSION['flash_message'] = 'This submission has already been reviewed.';
        header('Location: submissions.php');
        exit;
    }

    if ($submission['type'] === 'link') {
        $cat_ids = array_values(array_filter(array_map('intval', explode(',', (string) $submission['category_ids']))));
        $cats = array_pad($cat_ids, 5, 0);

        if ($submission['action'] === 'new') {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_links
                 (links_name, links_url, links_author, links_email, links_desc,
                  links_cat_1, links_cat_2, links_cat_3, links_cat_4, links_cat_5,
                  links_date_added, links_active, links_dead, links_verified, links_recommended, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, 0, 0, 0, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssiiiiii',
                $submission['links_name'], $submission['links_url'], $submission['links_author'], $submission['links_email'], $submission['links_desc'],
                $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
                $submission['submitted_by']
            );
            mysqli_stmt_execute($stmt);
            $link_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_links SET links_name=?, links_url=?, links_author=?, links_email=?, links_desc=?,
                 links_cat_1=?, links_cat_2=?, links_cat_3=?, links_cat_4=?, links_cat_5=?
                 WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssiiiiii',
                $submission['links_name'], $submission['links_url'], $submission['links_author'], $submission['links_email'], $submission['links_desc'],
                $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
                $submission['target_id']
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $link_id = (int) $submission['target_id'];
        }

        $stmt = mysqli_prepare($myConnection, "DELETE FROM t_link_categories WHERE link_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $link_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!empty($cat_ids)) {
            $stmt = mysqli_prepare($myConnection, "INSERT INTO t_link_categories (link_id, category_id) VALUES (?, ?)");
            foreach (array_unique($cat_ids) as $category_id) {
                mysqli_stmt_bind_param($stmt, 'ii', $link_id, $category_id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // news_story is contributor-typed plain text (see Task 7 review), but
        // t_news.news_story is echoed RAW/unescaped on the public site
        // (table_content_news_sub.php). strip_tags() here prevents a
        // contributor from injecting markup/script via a plain textarea that
        // no other contributor-facing field allows.
        $news_story = strip_tags($submission['news_story']);

        if ($submission['action'] === 'new') {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_news (news_date, news_story, news_active, submitted_by) VALUES (?, ?, 1, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $submission['news_date'], $news_story, $submission['submitted_by']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_date=?, news_story=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssi', $submission['news_date'], $news_story, $submission['target_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $_SESSION['flash_message'] = 'Submission approved.';
    header('Location: submissions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $reject_reason = trim($_POST['reject_reason'] ?? '');

    if ($reject_reason === '') {
        $error = 'A reject reason is required.';
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_submissions SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $reject_reason, $_SESSION['user_id'], $id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_affected_rows($myConnection);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            $_SESSION['flash_message'] = 'This submission has already been reviewed.';
            header('Location: submissions.php');
            exit;
        }

        $_SESSION['flash_message'] = 'Submission rejected.';
        header('Location: submissions.php');
        exit;
    }
}

if ($submission['type'] === 'link') {
    $fields = [
        'Name' => 'links_name',
        'URL' => 'links_url',
        'Author' => 'links_author',
        'Email' => 'links_email',
        'Description' => 'links_desc',
    ];
} else {
    $fields = [
        'Date' => 'news_date',
        'Story' => 'news_story',
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Review Submission</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>REVIEW SUBMISSION</b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="submissions.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to Queue</a></font></td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
<?php if ($error): ?>
							<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>
							<p><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<b><?php echo htmlspecialchars(ucfirst($submission['type'])); ?> &mdash; <?php echo htmlspecialchars(ucfirst($submission['action'])); ?></b>
							</font></p>
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Field</b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Current</b></font></td>
<?php endif; ?>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Proposed</b></font></td>
								</tr>
<?php foreach ($fields as $label => $column): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><b><?php echo htmlspecialchars($label); ?></b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo nl2br(htmlspecialchars((string) $current[$column])); ?></font></td>
<?php endif; ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo nl2br(htmlspecialchars((string) $submission[$column])); ?></font></td>
								</tr>
<?php endforeach; ?>
<?php if ($submission['type'] === 'link'): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><b>Categories</b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo category_names_from_csv($current['category_ids'], $category_names); ?></font></td>
<?php endif; ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo category_names_from_csv($submission['category_ids'], $category_names); ?></font></td>
								</tr>
<?php endif; ?>
							</table>
							<br>
							<form method="post" action="submission_review.php" style="display:inline;">
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
								<input type="hidden" name="approve" value="1">
								<input type="submit" value="Approve" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="submission_review.php" style="display:inline;">
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
								<input type="hidden" name="reject" value="1">
								<font class="txt-1" face="Verdana, sans-serif" size="1"><b>Reject reason:</b></font>
								<input type="text" name="reject_reason" style="width:240px;">
								<input type="submit" value="Reject" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
							</form>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
