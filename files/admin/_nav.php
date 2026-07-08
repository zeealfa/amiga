<table width="100%" cellpadding="8" cellspacing="0">
	<tr><td class="bg-637b94"><span class="txt-3-fff"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2"><b>&raquo; Dashboard</b></span></td></tr>
<?php if ($_SESSION['role'] === 'admin'): ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Users</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; News</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Links</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Categories</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; <a href="profile.php">My Profile</a></span></td></tr>
<?php else: ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; My Submissions</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; <a href="profile.php">My Profile</a></span></td></tr>
<?php endif; ?>
</table>
