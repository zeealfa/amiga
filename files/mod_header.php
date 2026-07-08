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
				<td align="right" class="bg-ff9900" cellpadding="16" cellspacing="8">
					<span class="txt-5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</span>
				</td>
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="4" cellspacing="0">
					<span class="txt-3">
<?php if (isset($_SESSION['user_id'])): ?>
						<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
<?php else: ?>
						<b><a href="admin/login.php">Login</a></b>
<?php endif; ?>
					</span>
				</td>
			</tr>
		</td>
	</tr>
</table>
<!-- Header ends here -->
