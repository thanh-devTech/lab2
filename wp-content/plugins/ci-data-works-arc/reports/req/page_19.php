<div class="break page12 headerresult staticpage">
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
		<div class="content_title" style="margin-bottom:10px">Part 4: Hemoglobin A1C - <span>Understanding Your Average Blood Sugar Levels</span></div>
		<div class="content_title2" style="margin-bottom:5px">Description</div>
		Hemoglobin A1c (HbA1c) is a measurement of the amount of glucose bound to the heme found in red blood
		cells. Unlike a single blood glucose test, which can vary significantly throughout the day based on factors like
		recent meals or physical activity, an HbA1c test measures your average blood sugar levels over the past 2-3
		months. This makes it an important tool for the screening, diagnosis, and management of pre-diabetes and
		diabetes.
		<br>
		<br>
		Blood glucose, commonly referred to as blood sugar, is the main sugar found in your bloodstream. It comes
		from the food you eat and supplies energy to all cells in the body. However, it needs to stay within safe levels
		to reduce the risk of diabetes and associated disorders, such as heart disease. Insulin, a hormone produced
		by the pancreas, helps store glucose in the liver, muscles, and fat, controls the amount of glucose in your
		blood, and regulates how your body uses carbohydrates, fats, and proteins. When you don't have enough
		insulin or it isn't working properly, your body cannot effectively process blood sugar, resulting in high blood
		sugar levels and, ultimately, diabetes. If high blood sugar levels are left untreated, they can lead to chronic
		health conditions and damage to many bodily systems.
		<br>
		<div class="content_title2" style="margin-bottom:0">Results</div>
		<ul>
			<li><b>Your HbA1c Level:</b> <?php echo $blood_HbA['flag'] ?></li>
			<li><b>Comparative Analysis:</b>
				<?php
				if ($blood_HbA['pos-neg'] == 'NEG') {
					echo 'Your result is within the expected range for individuals of your age and health status.';
				} else if ($blood_HbA['pos-neg'] == 'POH') {
					echo 'Your result is Increased for individuals of your age and health status.';
				} else {
					echo 'Your result is High for individuals of your age and health status.';
				}
				?></li>
		</ul>

		<?php include($files_dir . 'chart_hba.php') ?>
	</div>
	<?php include($files_dir . 'footer.php') ?>
</div>