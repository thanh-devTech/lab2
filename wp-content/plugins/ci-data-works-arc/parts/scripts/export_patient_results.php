<?php

if (isset($_REQUEST['export_rs'])) {

	$btype = $_REQUEST['btype'] ? $_REQUEST['btype'] : 'tox';
	$cli_id = $_REQUEST['clinic_id'];
	$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cId = {$cli_id} ");

	$file_name = sanitize_key($clinic_name) . '_results.csv';
	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	$f = fopen('php://output', 'w');

	$headers = array(
		'Physician Name',
		'Patient MRN',
		'Patient Last Name',
		'Patient First Name',
		'Patient Birthdate',
		'Patient Sex',
		'Accession',
		'Sample Type',
		'Medications',
		'DOC',
		'Received Date/time',
		'Result Date/time',

		'Test Name',
		'Neg/Pos',
		'Detected (ng/mL)',
		'Cutoff (ng/mL)',
		'Comment'
	);
	fputcsv($f, $headers);

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);

	$results = $wpdb->get_results("
	    select ba.requesting_physician,
	    ba.assen,
	    ba.type_of_specimen,
	    ba.doc,
	    ba.current_medication,
	    ba.spec_received,
	    re.approve_date,
	    pa.pa_mrn,
	    pa.pa_last,
	    pa.pa_first,
	    pa.pa_dob,
	    pa.pa_gender,
	    
	    rs.drug,
	    rs.`pos-neg`,
	    rs.conc,
	    rs.cutoff,
	    rs.comments
	    
	    from {$batch_tbl} ba
	    join ci_patients pa On ba.patient_id = pa.pa_ID
	    join {$report_tbl} re ON re.patient_id = ba.assen
	    join {$result_tbl} rs ON rs.report_id = re.id
	    where ba.cli_ID = {$cli_id} 
	    order by ba.mid asc");


	foreach ($results as $r) {
		$row = array(
			$r->requesting_physician,
			$r->pa_mrn,
			$r->pa_last,
			$r->pa_first,
			$r->pa_dob,
			$r->pa_gender,
			$r->assen,
			$r->type_of_specimen,
			$r->current_medication,
			$r->doc,
			$r->spec_received,
			$r->approve_date,

			$r->drug,
			$r->{'pos-neg'},
			$r->conc,
			$r->cutoff,
			$r->comments,
		);

		fputcsv($f, $row);
	}

	exit;
}


?><div>
	<div>Export Patient Results</div>
	<form method="post">
		<label>Clinic</label>
		<select name="clinic_id" class="select2">
			<?php
			$clinics = $wpdb->get_results("select * from ci_clinic order by cli_clinic");
			foreach ($clinics as $clinic) {
				echo '<option value="' . $clinic->cID . '">' . $clinic->cli_clinic . '</option>';
			}
			?>
		</select>
		<select name="btype">
			<?php
			foreach (tr_pdd_get_batch_types() as $btype) {
				$name = tr_pdd_get_typename($btype);
				echo '<option value="' . $btype . '">' . $name . '</option>';
				if ($btype == 'tox') {
					echo '<option value="screening">Tox Screening</option>';
				}
			}

			?>
		</select>
		<input type="search" name="fromdate" value="<?php echo isset($_REQUEST['fromdate']) ? $_REQUEST['fromdate'] : '' ?>" class="hasdate" placeholder="From Date" />
		<input type="search" name="todate" value="<?php echo isset($_REQUEST['todate']) ? $_REQUEST['todate'] : '' ?>" class="hasdate" placeholder="To Date" />
		<input type="submit" name="export_rs" class="button" value="Export" />
		<input type="hidden" name="trajax" value="export_patient_results" />
	</form>
</div>