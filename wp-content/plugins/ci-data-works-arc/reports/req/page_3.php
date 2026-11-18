<div class="break page7 staticpage">
	<div class="pageheader smallheader">
		<div class="logo">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo2'] ?>" style="width:46px" />
			<span><?php echo $settings['pdf_title'] ?></span>
		</div>
		<div class="title">
			<div class="t1">RESULTS SUMMARY PAGE</div>
			<div class="t2">Understand your results</div>
		</div>
	</div>
	<div class="pagecontent">
		<?php
		$is_sum_page = true;
		include($files_dir . 'chart_beta_score.php');
		include($files_dir . 'chart_insulin.php');
		include($files_dir . 'chart_c_peptide.php');
		include($files_dir . 'chart_hba.php');

		$is_sum_page = false;
		?>

	</div>
	<?php 
	$nopagenum = true;
	include($files_dir . 'footer.php') ?>
</div>