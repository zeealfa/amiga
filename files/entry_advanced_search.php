<?php
	if(!isset($_SESSION)){
		session_start();
	}
	$_SESSION["content_type"]='advanced_search';
	include_once __DIR__ . '/legacy_colors.php';
?>
<table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<?php
				include ("login_db.php");
				include ("page_builder.php");
			?>
		</font>
	</td>
</table>
<?php echo "<title>AmigaSource.com Advanced Search</title>"; ?>
<br>
