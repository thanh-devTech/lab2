<div class="break page14 headerresult staticpage">
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
		<div class="content_title" style="margin-bottom:10px">Part 2: Insulin - <span>Understanding Your Insulin Result</span></div>
		<div class="content_title2" style="margin-bottom:5px">Description</div>
		Insulin is a hormone produced in the pancreas that helps regulate blood glucose levels by allowing cells to
		absorb glucose from the bloodstream for energy or storage. Proper insulin function is essential for
		maintaining normal blood sugar levels. In people with diabetes, insulin can be less effective (insulin
		resistance) or not produced in sufficient quantities (insulin deficiency), making it difficult to manage blood
		sugar levels. Consistently high or low insulin levels can indicate serious medical conditions.
		<br>
		<br>
		<br><br>
		
		<div class="content_title2" style="margin-bottom:0">Results</div>
		<ul>
			<li><b>Your Insulin Level:</b> <?php echo $blood_Insulin['flag'] ?></li>
			<li><b>Comparative Analysis:</b> 
		<?php
		if($blood_Insulin['pos-neg']=='NEG'){
			echo 'Your result is within the expected range for individuals of your age and health status. ';
		}else{
			echo 'Your result is High for individuals of your age and health status. ';
		}
		echo 'This reference range is based on a fasting state during collection. If the sample was collected in a non-fasting state, this should be considered when interpreting the clinical significance.';
		?></li>
		</ul>
		<br><br><br><br><br>
		<?php include($files_dir . 'chart_insulin.php') ?>
	</div>
	<?php include($files_dir . 'footer.php') ?>
</div>