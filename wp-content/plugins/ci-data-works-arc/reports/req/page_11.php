<div class="break page9 headerresult staticpage">
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
		<div class="content_title">Part 1: BetaDx - <span>Understanding Beta Cell Health</span></div>
		<div class="content_title2">Interpretation</div>
		<div class="content_title2">What This Result Means in Terms of Diabetes Risk:</div>

		<div class="listcircle ccgreen">
			<div><b>Normal Beta Cell Death Level:</b> A normal level of beta cell death indicates that your pancreas is
				functioning well, producing sufficient insulin to regulate blood glucose levels effectively. Your risk of
				developing diabetes is low.</div>
		</div>
		<div class="listcircle ccyellow">
			<div><b>Elevated Beta Cell Death Level:</b> An elevated level of beta cell death suggests that your pancreatic beta cells are dying at a faster rate than normal, signaling active cellular injury or stress within the pancreas. This can lead to insufficient insulin production, causing higher blood glucose levels. This condition increases your risk of developing diabetes. Immediate lifestyle changes and medical intervention are recommended to manage this risk.</div>
		</div>
		<?php /*
		<div class="listcircle ccred">
			<div><b>High Beta Cell Death Level:</b> A high level of beta cell death indicates significant loss of insulin-
				producing cells. This condition can severely impair your body's ability to regulate blood glucose levels,
				significantly increasing your risk of developing diabetes and related complications. It is crucial to
				consult with a healthcare provider to explore treatment options and implement strategies to protect
				remaining beta cells and manage blood glucose levels effectively.</div>
		</div>*/?>
		<br>
		<div class="content_title2">Recommendations</div>
		<ul class="margin20">
			<li>
				<b>Lifestyle Changes:</b> Maintain a balanced diet of whole food sources, engage in regular physical activity,
				and avoid factors that can stress your beta cells, such as excessive sugar intake and sedentary
				lifestyle.
			</li>
			<li>
				<b>Further Testing:</b> Consider periodic monitoring of beta cell health and other metabolic markers to
				ensure continued health and early detection of potential issues.
			</li>
		</ul>

		<p class="fontsmall1">
			Please be advised that a normal result from this screening test does not definitively rule out the presence of the condition tested for. False negatives can occur due to various factors such as the timing of the test, the stage of the disease, or individual biological variability. It is advised to correlate this test result with other laboratory tests, clinical signs, and symptoms. Additional diagnostic tests may provide a clearer picture of the patient's health status. In case that the clinical presentation or the symptoms persist or worsen, repeating the test or obtaining further testing is recommended to either confirm or exclude the diagnosis. Always consult with your healthcare provider to interpret these results within the context of your overall health and medical history.
		</p>
	</div>
	<?php include($files_dir . 'footer.php') ?>
</div>