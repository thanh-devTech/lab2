<div class="break page8 headerresult staticpage">
	<div class="pageheader smallheader">
		<div class="logo">
			<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo2'] ?>" style="width:46px" />
			<span><?php echo $settings['pdf_title'] ?></span>
		</div>
		<div class="title">
			<div class="t1">RESULTS</div>
			<div class="t2">Understand your results</div>
		</div>
	</div>

	<div class="pagecontent">
		<div class="content_title">Part 1: Beta Score - <span>Understanding Beta Cell Health</span></div>
		<div class="content_title2">Description</div>

		Beta cells are specialized cells in the pancreas responsible for producing and releasing insulin, a
		hormone that regulates blood glucose levels. The death of beta cells, also known as beta cell apoptosis,
		can significantly impact insulin production. Monitoring beta cell death is crucial because a high rate of
		beta cell loss can lead to reduced insulin levels, resulting in elevated blood glucose levels and an
		increased risk of developing diabetes.
		<br><br>
		<br><br>
		<div class="content_title2">Results</div>
		<ul>
			<li>
				<b>Your Beta Score Level:</b> <?php echo $pcr_beta_score['flag'] ?>
			</li>
			<li>
				<b>Comparative Analysis:</b>
				<?php
				if ($pcr_beta_score['pos-neg'] == 'NEG') {
					echo 'Your result is within the expected range for individuals of your age and health status. ';
				} else if ($pcr_beta_score['pos-neg'] == 'INC') {
					echo 'The specimen failed to meet our laboratory’s quality acceptance criteria and therefore could not be resulted. We recommend specimen recollection and repeat testing. Customer service will contact you.';
				} else {
					echo 'Your result is Elevated for individuals of your age and health status. ';
				}

				?>
			</li>
		</ul>
		<br><br><br>
		<?php include($files_dir . 'chart_beta_score.php') ?>
		<br><br>
		<p class="fontsmall1 center">
			The reference range provided in this report has been established based on our initial population studies. Please note that this range is subject to change as more comprehensive data becomes available, which might refine our understanding of normal and abnormal values. Should future updates to the reference range result in significant clinical impact, an addendum will be issued to reflect these changes
		</p>
	</div>
	<?php include($files_dir . 'footer.php') ?>
</div>