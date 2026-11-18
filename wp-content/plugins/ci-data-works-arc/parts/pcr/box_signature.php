<?php
$mid  = isset($mid) && $mid > 0 ? $mid : (isset($bat['mid']) ? $bat['mid'] : 0);


if ($settings['req_inc_pathogogy']) {
	$meta_inc = trpdd_get_batch_meta($mid, $btype, '_inc_pathogogy');
	echo '<div class="frow">';
	echo '<h4>PCR Clinical Interpretation and Review</h4>';
	echo '<label><input type="checkbox" name="batmeta[_inc_pathogogy]" value="1" ' . (checked($meta_inc, 1, false)) . '/>  <span class="bold red">Request Aegis Pathology Consult</span></label>';
	echo '</div>';
	echo '<div class="frow">If selected, I acknowledge that I will receive a pathology consult and interpretive report from Aegis Health Services on the lab results for the patient’s treatment and care, due to the complexity of the patient’s medical condition. I certify that I have discussed these services with the patient, informed them that they will be provided by a consultative provider, and noted in the medical record.</div>';
}



$patientcol = 'col2';
$providercol = 'col2';
if (isset($settings['abovep_32']) && $settings['abovep_32']) {
	$patientcol = 'col23';
	$providercol = 'col13';
}
$patient_s_title = !empty($settings['abovep_title']) ? $settings['abovep_title'] : 'CONSENT FOR TESTING';
$patient_s_text = !empty($settings['abovep_text']) ? $settings['abovep_text'] : 'The information I have provided on this form is accurate. 
            I authorize ' . $current_lab['lab_dba_init'] . ' Diagnostics Laboratories to release the results of this test to my treating physician or facility. 
            I hereby authorize my insurance or other payment to ' . $current_lab['lab_dba_init'] . ' Diagnostics Laboratories for services
                    I receive. I am aware that ' . $current_lab['lab_dba_init'] . ' Diagnostics Laboratories may be an
                    out of network provider with my insurer. I am aware that I am responsible for
                    all co-pays and deductibles not covered by insurance or other payers.';

$provider_s_title = !empty($settings['abovepro_title']) ? $settings['abovepro_title'] : 'MEDICAL NECESSITY';
$provider_s_text = !empty($settings['abovepro_text']) ? $settings['abovepro_text'] : 'As part of my antibiotic stewardship policy, 
            I find it medically necessary to rapidly determine and differentiate a viral and/or bacterial infection in order to treat with or without appropriate antibiotics. 
            Having the most accurate and timely data available to me directly guides my treatment and patient management. 
            Empiric treatment and management leads to inappropriate and unnecessary antibiotic use (50% according to the CDC) and delayed diagnosis which can lead to severe consequences.';
?>
<div class="frow">
	<div class="<?php echo $patientcol ?>">
		<h4><?php echo $patient_s_title ?></h4>
		<div style="margin-right: 15px">
			<?php echo $patient_s_text ?>
		</div>
	</div>
	<div class="<?php echo $providercol ?>">
		<h4><?php echo $provider_s_title ?></h4>
		<?php echo $provider_s_text ?>
	</div>
</div>
<div class="frow blabel">
	<div class="<?php echo $patientcol ?>">
		<br>
		<label>Patient Signature:</label>
		<?php
		$fname = !empty($bat['patient_signature']) ? $bat['patient_signature'] : '';
		$patient_signature_img = '';
		if ($fname == 'onfile') {
			$patient_signature_img = TRCPDD_URL . 'images/OnFile.png';
			$patient_signature_img = '<img class="sign_img" src="' . $patient_signature_img . '" style="width:300px"/>';
		} else if (!empty($fname) && file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
			$patient_signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $fname . '?' . time() . '" style="width:300px"/>';
		}

		if ($act == 'view' || $act == 'print' || (isset($billing_page) && $billing_page)) {
			echo '<div class="signatureimgbox">';
			echo $patient_signature_img;
			echo '</div>';
		} else {
		?>
			<div class="card_file">
				<?php
				echo '<div class="signaturepad" style="' . (empty($patient_signature_img) ? '' : 'display:none') . '">';
				echo tr_signature(array('name' => 'patient_signature', 'required' => false, 'clear' => false));
				echo '</div>';
				?>
				<a class="button selectfile" href="#">Choose Signature Image</a>
				<a class="button-primary remove" rel="patient_signature">Clear</a>
				<br>
				<span class="view"><?php echo $patient_signature_img ?></span>
				<input type="file" name="patient_signature" accept="image/*" />
				<input type="hidden" name="img_patient_signature" value="<?php echo $fname ?>" />
				<?php if ($bat['patient_signature'] != 'onfile') { ?>
					<div>
						<label class="inline">
							<input type="checkbox" name="onfile" value="1" <?php checked($settings['auto_on_patient_signature_file']) ?> /> On-File
						</label>
					</div>
				<?php } ?>
			</div>
		<?php
		}
		if (tr_get_option('ci_pdf_data', 'show_submitdate') && !empty($patient_signature_img)) {
			echo date('m/d/Y H:i:s', strtotime($bat['batch_modified']));
		}
		?>
	</div>
	<div class="<?php echo $providercol ?>" id="provider_signdiv">
		<br>
		<label>Provider Signature:</label>
		<?php
		$fname = !empty($bat['provider_signature']) ? $bat['provider_signature'] : '';
		if ((empty($fname) || stripos($fname, 'doc_') === 0) && !empty($current_doctor_sign)) {
			$fname = $current_doctor_sign;
		} else if (empty($fname) && $mid == 0 && $current_role == 'doctor') {
			$fname = trpdd_get_user_signature();
		}
		$provider_signature_img = '';
		if (!empty($fname) && file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
			$provider_signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $fname . '?' . time() . '" style="width:300px"/>';
		}
		if ($act == 'view' || $act == 'print' || (isset($billing_page) && $billing_page)) {
			echo '<div class="signatureimgbox">';
			echo $provider_signature_img;
			echo '</div>';
		} else {
		?>
			<div class="card_file">
				<?php
				echo '<div class="signaturepad" style="' . (empty($provider_signature_img) ? '' : 'display:none') . '">';
				echo tr_signature(array('name' => 'provider_signature', 'required' => false, 'clear' => false));
				echo '</div>';
				?>
				<a class="button selectfile" href="#">Choose Signature Image</a>
				<a class="button-primary remove" rel="provider_signature">Clear</a>
				<br>
				<span class="view signimg"><?php echo $provider_signature_img ?></span>
				<input type="file" name="provider_signature" accept="image/*" />
				<input type="hidden" name="img_provider_signature" value="<?php echo $fname ?>" />
			</div>
		<?php
		}
		?>
	</div>
</div>