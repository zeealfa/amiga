<?php
	$search_news_excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($row['news_story'])));
	if ($search_news_excerpt === '') {
		$search_news_excerpt = '(empty)';
	} elseif (mb_strlen($search_news_excerpt) > 200) {
		$search_news_excerpt = mb_substr($search_news_excerpt, 0, 200) . '...';
	}
?>
<table cellpadding="1" cellspacing="0" width="90%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="4" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
						<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
							<b><?php echo htmlspecialchars($row['news_date']); ?></b>
<?php if (!empty($row['submitter_username'])): ?>
							&nbsp;&mdash;&nbsp;submitted by <?php echo htmlspecialchars($row['submitter_username']); ?>
<?php endif; ?>
							<br>
							<?php echo htmlspecialchars($search_news_excerpt); ?>
						</font>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<br>
