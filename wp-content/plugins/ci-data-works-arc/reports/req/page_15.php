<div class="break page10 headerresult staticpage">
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
		<div class="content_title">Part 3: C-Peptide - <span>Understanding Insulin & Beta cell function</span></div>
		<div class="content_title2">Description</div>

		C-Peptide is useful in the evaluation of pancreatic beta cell function and measuring it can show whether you
		have diabetes or if you are metabolically healthy. C-peptide is a substance — a short chain of amino acids —
		released into the blood as an inactive byproduct of the formation of insulin by the pancreas. When insulin is
		released from the beta cells into the blood in response to increased levels of glucose, equal amounts of C-
		peptide are also released. Since C-peptide is produced at the same rate as insulin, it is useful as a marker of
		insulin production.
		<br>
		<br>
		<br>

		<div class="content_title2">Results</div>
		<ul>
			<li><b>Your C-peptide Level:</b> <?php echo $blood_C_Peptide['flag'] ?></li>
			<li><b>Comparative Analysis:</b>
				<?php

				if ($blood_C_Peptide['pos-neg'] == 'NEG') {
					echo 'Your result is within the expected range for individuals of your age and health status.';
				} else if ($blood_C_Peptide['pos-neg'] == 'POL') {
					echo 'Your result is Low for individuals of your age and health status.';
				} else {
					echo 'Your result is High for individuals of your age and health status. ';
				}
				echo ' This reference range is based on a fasting state during collection. If the sample was collected in a non-fasting state, this should be considered when interpreting the clinical significance.';
				?></li>
		</ul>
		<br><br>
		<?php include($files_dir . 'chart_c_peptide.php') ?>
	</div>
	<?php include($files_dir . 'footer.php') ?>
</div>