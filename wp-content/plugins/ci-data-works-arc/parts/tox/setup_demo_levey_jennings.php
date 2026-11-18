<?php
global $wpdb;

if (isset($_POST['setupdata'])) {
	echo '<h3>Setting up Levey-Jennings QC Data...</h3>';
	$lab_report = isset($_POST['lab']) ? sanitize_text_field($_POST['lab']) : '';
	$instrument = isset($_POST['instrument']) ? sanitize_text_field($_POST['instrument']) : '';
	$lot        = isset($_POST['lot']) ? sanitize_text_field($_POST['lot']) : '';
	$month      = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('Y-m');

	$table_report = 'ci_reports';
	$table_result = 'ci_results';

	$date_from = date('Y-m-01', strtotime($month));
	$date_to   = date('Y-m-t', strtotime($month));

	$qctypes = array('LQC', 'MQC', 'HQC');

	$drugs = array();
	$lowhmed = array();
	$tests_rs = $wpdb->get_results("select * from ci_theoreticals order by qc_drug ASC ");

	foreach ($tests_rs as $t) {
		$drugs[$t->qc_drug] = $t->qc_drug;
		if (!isset($lowhmed[$t->qc_drug])) {
			$lowhmed[$t->qc_drug] = array(
				'low' => 0,
				'high' => 0
			);
		}
		$lowhmed[$t->qc_drug][$t->qc_type] = $t->qc_value;
	}


	for ($date = strtotime($date_from); $date <= strtotime($date_to); $date = strtotime('+1 day', $date)) {
		$current_date = date('Y-m-d', $date);
		// Your code here for each date
		$batch_id = date('Ymd', $date);
		echo '<strong>Processing date: ' . $current_date . '</strong><br>';

		foreach ($qctypes as $qctype) {
			$report_id = $wpdb->get_var("select id from {$table_report} 
			where sublab = '{$lab_report}' 
			AND instrument = '{$instrument}' 
			AND patient_name = '{$qctype}'
			AND acquisition_date = '{$current_date}' ");

			if ($report_id == 0) {
				$wpdb->insert($table_report, array(
					'sublab'           => $lab_report,
					'instrument'      => $instrument,
					'acquisition_date' => $current_date,
					'patient_name'    => $qctype,
					'patient_id'    => $qctype . '_' . $instrument,
					'created_date'       => $current_date,
					'batch_id' => $batch_id
				));
				$report_id = $wpdb->insert_id;
				echo 'Inserted report for ' . $qctype . ' on ' . $current_date . '<br>';
			}

			if ($report_id == 0) {
				continue;
			}

			foreach ($drugs as $drug) {
				if (empty($drug)) continue;

				$rsid = $wpdb->get_var("select id from {$table_result} 
				where report_id = {$report_id} 
				AND drug = '{$drug}' ");

				$low = $lowhmed[$drug]['low'];
				$high = $lowhmed[$drug]['high'];
				$med = ($low * 15 + $high) / 16;

				if ($qctype == 'LQC') {
					$outrand = $low / 6 * 1000;
					$conc = $low + rand(0 - $outrand, $outrand) / 1000;
				} elseif ($qctype == 'MQC') {
					$outrand = $med / 6 * 1000;
					$conc = $med + rand(0 - $outrand, $outrand) / 1000;
				} else {
					$outrand = $high / 6 * 1000;
					$conc = $high + rand(0 - $outrand, $outrand) / 1000;
				}


				if ($rsid == 0) {

					$wpdb->insert($table_result, array(
						'report_id' => $report_id,
						'drug'      => $drug,
						'conc'       => $conc,
						'conc_number'       => $conc,
					));
				} else {
					$wpdb->update($table_result, array(
						'conc'       => $conc,
						'conc_number'       => $conc,
					), array('id' => $rsid));
				}
			}
		}
	}
}
?>
<form method="post" style="margin:10px 5px;">

	<?php if (count($labs_all) > 1) : ?>
		<label style="float:left">Lab: </label>
		<select style="float:left;margin-right:10px;" name="lab" id="select_lab">
			<option value="">Select Lab</option>
			<?php
			foreach ($labs_all as $l) {
				$ins = $wpdb->get_col("select distinct(re.instrument) from ci_reports re where re.sublab = '{$l->lab_dba_init}' AND re.instrument!='' {$where_qc} ORDER BY re.instrument ASC ");
				$ins = implode(',', $ins);
				$selected = $l->lab_dba_init == $lab_report ? 'selected="selected"' : '';
				echo '<option value="' . $l->lab_dba_init . '" ' . $selected . ' data-in="' . $ins . '">' . $l->lab_dba_init . '</option>';
			}
			?>
		</select>
	<?php endif; ?>



	<input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
	<div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>" data-href="<?php Tr_Base_Class_V6::link(array('month' => '')) ?>"></div>


	<div style="float: left">
		<select name="instrument" id="instrument" required>
			<option value="">Select Machine</option>
			<?php

			//$instruments['MS-4500'] = 'MS-4500';
			foreach ($instruments as $in) {
				$selected = $in == $instrument ? 'selected' : '';
				echo '<option value="' . $in . '" ' . $selected . '>' . $in . '</option>';
			}
			?>
		</select>

	</div>
	<?php if (count($lots) > 0) { ?>
		<div style="float: left">
			<select name="lot">
				<option value="">All Lot</option>
				<?php foreach ($lots as $in) {
					if (empty($in) || stripos($in, 'qc') !== false) continue;
					$selected = $in == $lot ? 'selected' : '';
					echo '<option value="' . $in . '" ' . $selected . '>' . $in . '</option>';
				}
				?>
			</select>
		</div>
	<?php } ?>


	<input type="submit" name="setupdata" class="button" value="Setup" />


	<div class="clear"></div>
</form>
<br><br><br>