<table class="pagefooter">
	<tr>
		<td class="logo">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logocopy'] ?>" style="width:46px" />
		</td>
		<td class="footertitle">
			<div><?php echo $settings['pdf_title'] ?> Inc.</div>
			<div><a href="<?php echo 'https://' . $settings['pdf_site'] ?>"><?php echo $settings['pdf_site'] ?></a></div>
		</td>
		<td class="footerinfo">
			<?php echo $footer_text ?>
		</td>
		<td class="nav">
			<?php
			if (!isset($nopagenum) || !$nopagenum) {
				echo $pagenum++;
				echo ' of ' . $total_page;
			}
			$nopagenum = false;
			?>
		</td>
	</tr>
</table>