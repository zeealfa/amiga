<!-- Header starts here -->
<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="index.php">
				<img src="/web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>		
			</tr>
			<tr>
				<td align="left" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="16">
					<font class="txt-5" face="Verdana, sans-serif" size="5"><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></font>
				</td>
				<td align="right" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="4">
					<font class="txt-3" face="Verdana, sans-serif" size="3">
<?php if (isset($_SESSION['user_id'])): ?>
						<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
<?php else: ?>
						<b><a href="admin/login.php">Login</a></b>
<?php endif; ?>
					</font>
				</td>
			</tr>
		</td>
	</tr>
</table>
<!-- Header ends here -->
