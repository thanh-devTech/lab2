<div class=" page1 staticpage">
	<div class="whitebg">
		<div class="pagecontent">
			<div class="center" style="font-size:60px;line-height:60px;font-family:Arial">
				<img src="<?php echo $result_dir['path'] . '/' . $settings['pdf_logo'] ?>" style="width:60px" />
				<span style="display:inline-block"><?php echo $settings['pdf_title'] ?></span>
			</div>
			<div class="center bold" style="margin-top:70px;font-size:60px;">DIABETES RISK TEST<br>REPORT</div>

			<div class="whitebox ">
				<?php /*<div class=" center bold" style="margin-bottom:20px">Report ID: <?php echo $report['assen'] ?></div>*/ ?>
				<div class="wrow"><label>Customer Name: </label> <span><?php echo $report['pa_first'] . ' ' . $report['pa_last'] ?></span></div>
				<div class="wrow"><label>Customer ID: </label> <span><?php echo $report['pa_mrn'] ?></span></div>
				<div class="wrow"><label>DOB:</label> <span><?php echo date('m/d/Y', strtotime($report['pa_dob'])) ?></span></div>
				<div class="wrow"><label>Gender</label> <span><?php echo isset($all_genders[$report['pa_gender']]) ? $all_genders[$report['pa_gender']] : '' ?></span></div>
				<?php /*<div class="wrow"><label>Customer ID: </label> <span><?php echo $report['pa_code'] ?></span></div>*/ ?>
				<div class="wrow"><label>Test Name: </label> <span><?php echo $testname ?></span></div>
				<div class="wrow"><label>Test Components: </label>
					<span class="small">Beta Score, Serum<br>
						Insulin / C-peptide, Serum<br>
						HbA1C - Whole Blood</span>
				</div>
				<div class="wrow"><label>Collection: </label> <span><?php echo date('m/d/Y h:ia', strtotime($report['doc'])) ?></span></div>
				<div class="wrow"><label>Received: </label> <span><?php echo date('m/d/Y h:ia', strtotime($report['spec_received'])) ?></span></div>
				<div class="wrow"><label>Issued: </label> <span><?php echo !empty($report['approve_date']) ? date('m/d/Y h:ia', strtotime($report['approve_date'])) : '' ?></span></div>
				<div class="wrow"><label>Ordering Provider: </label> <span><?php echo str_replace(',', ' ', $report['physician']) ?></span></div>
				<div class="wrow"><label>Sample ID: </label> <span><?php echo $report['assen'] ?></span></div>
				<div class="wrow"><label>Clinical Notes: </label>
					<div style="text-align: justify;line-height:17px;font-size:15px"><?php echo $report['notes_report'] ?></div>
				</div>

			</div>


		</div>
	</div>

</div>
<?php
$nopagenum = true;
include($files_dir . 'footer.php') ?>