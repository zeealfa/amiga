<?php
	$search_row_name = $row[$section['name_field']];
	$search_row_url = $row[$section['url_field']];
?>
<table cellpadding="1" cellspacing="0" width="90%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="4" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
						<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
							<a target="_blank" href="<?php echo htmlspecialchars($search_row_url); ?>"><b><?php echo htmlspecialchars($search_row_name); ?></b></a>
<?php if ($section['extra_label'] !== null && trim((string) $row[$section['extra_field']]) !== ''): ?>
							&nbsp;&mdash;&nbsp;<b><?php echo htmlspecialchars($section['extra_label']); ?>:</b> <?php echo htmlspecialchars($row[$section['extra_field']]); ?>
<?php endif; ?>
						</font>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<br>
