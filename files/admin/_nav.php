<table width="100%" cellpadding="8" cellspacing="0">
	<tr><td class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>"><font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2"><b>&raquo; Dashboard</b></font></td></tr>
<?php if ($_SESSION['role'] === 'admin'): ?>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="submissions.php">Submissions</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="users.php">Users</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="news.php">News</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="links.php">Links</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="categories.php">Categories</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>
<?php else: ?>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_links.php">My Links</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_news.php">My News</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_submissions.php">My Submissions</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>
<?php endif; ?>
</table>
