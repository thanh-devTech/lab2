<?php
global $wpdb, $current_role;

include_once(TRCPDD_PATH . 'pages/formdata.php');


$btype          = 'pcr';
$patient_id     = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';


$user_id        = get_current_user_id();
$result_dir     = tr_get_result_upload_dir();
$results        = array();

if ($patient_id > 0 || !empty($_REQUEST['s']) || !empty($_REQUEST['ssn']) || !empty($_REQUEST['mrn']) || !empty($_REQUEST['dob'])) {

	$where = '';

	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and ba.lab_id in ( {$lab_str} ) ";
	}


	$user = wp_get_current_user();

	if (tr_current_role_in( array('office_manager', 'multi_site_manager', 'clinic'))) {

		$cid = get_user_meta($user_id, '_clinic_id', true);
		$clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$clinics = implode(',', $clinics);
		if (empty($clinics)) $clinics = '0';
		$where .= " and ba.cli_ID in ({$clinics}) ";
	} else if ($current_role == 'doctor') {
		$doctor_id = get_user_meta($user->ID, '_doc_id', true);
		$where .= " and ba.doc_ID = {$doctor_id} ";
	} else if ($current_role == 'distributor') {
		$dis_ID = $wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`= {$user_id} ");
		$reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ");

		$reps = implode(',', $reps);
		$clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
		$clinics = implode(',', $clinics);
		if (empty($clinics)) $clinics = '0';
		$where .= " and ba.cli_ID in ({$clinics}) ";
	}

	if ($patient_id > 0) {
		$where .= " and ba.patient_id = '{$patient_id}' ";
	} else if (!empty($_REQUEST['s'])) {

		if (strpos($_REQUEST['s'], ',') > 0) {
			list($firstname, $lastname) = explode(',', $_REQUEST['s']);
			$firstname = trim($firstname);
			$lastname = trim($lastname);
			$explodeString = "%{$firstname}%{$lastname}%";
			$where .= $wpdb->prepare(" and ba.patient_name like %s", $explodeString);
		} else {
			$where .= $wpdb->prepare(" and ba.patient_name like %s", '%' . $_REQUEST['s'] . '%');
		}
	}

	if (!empty($_REQUEST['dob'])) {
		$where .= $wpdb->prepare(" and ba.dob = %s", $_REQUEST['dob']);
	}
	if (!empty($_REQUEST['ssn'])) {
		$where .= $wpdb->prepare(" and pa.pa_ssn like %s", '%' . $_REQUEST['ssn'] . '%');
	}
	if (!empty($_REQUEST['mrn'])) {
		$where .= $wpdb->prepare(" and pa.pa_mrn like %s", '%' . $_REQUEST['mrn'] . '%');
	}


	$where .= " and (ba.result_pdf !='' or re.estatus= 'C')
		            and ba.batch_status != 'not_received' ";


	$row_per_page = 100;
	$pagination = new Tr_Pagination_class($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(ba.mid) as c from ci_batches_pcr ba
                                        left JOIN ci_reports_pcr re ON ba.assen = re.patient_id
                                        left join ci_patients pa ON ba.patient_id = pa.pa_ID
                                        where 1 {$where}"));
	$offset = $pagination->offset();



	$sql = "select ba.mid,
		            ba.doc,
		            ba.batch_type,
		            ba.assen,
		            ba.patient_name as batch_patient_name,
		            ba.dob,
		            ba.patient_id,
		            ba.result_pdf,
		            re.id,
		            re.estatus
		            
                from ci_batches_pcr ba 
                left JOIN ci_reports_pcr re ON ba.assen = re.patient_id
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id

                LEFT JOIN ci_patients pa ON ba.patient_id=pa.pa_ID
                where 1 {$where} 
                group by ba.mid 
                order by ba.mid desc, ba.doc desc  
                limit {$offset},{$row_per_page}";

	$results = $wpdb->get_results($sql);

	if (isset($_REQUEST['debug'])) {
		echo $sql, '<br>';
		echo $wpdb->last_error;
	}
	if (count($results) > 0) {
?>
		<div class="wrap">
			<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
			<div class="wraptbl">
				<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
					<thead>
						<tr>
							<th>DOC</th>
							<th>Req Type</th>
							<th>Accession</th>
							<th>Patient Name</th>
							<th>Patient ID</th>
							<th>DOB</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($results as $row) : ?>
							<tr>
								<td title="<?php echo $row->mid ?>"><?php echo $row->doc ?></td>
								<td><?php echo $row->batch_type ?></td>
								<td><?php echo $row->assen ?></td>
								<td><?php echo $row->batch_patient_name ?></td>
								<td><a href="<?php Tr_Base_Class_V6::link(array('pid' => $row->patient_id)) ?>"><?php echo $row->patient_id ?></a></td>
								<td><?php echo $row->dob ?></td>
								<td>
									<a class="thickbox" href="?tr_action=ci_view_req&type=pcr&id=<?php echo $row->mid ?>&width=1000&height=600">View</a> -
									<?php if ($row->id > 0) {
									?>
										<a href="<?php echo ($row->id > 0) ? '?tr_action=ci_results_pdf&btype=pcr&id=' . $row->id : '#' ?>">PDF</a>
									<?php
									} else if (!empty($row->result_pdf)) {
									?>
										<a href="<?php echo $result_dir['url'] . '/' . $row->result_pdf ?>" target="_blank">PDF</a>
									<?php
									}
									?>
								</td>
							</tr>
						<?php endforeach ?>
					</tbody>
				</table>
			</div>
			<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
		</div>
<?php

	} else {
		echo '<br><br><h3 class="center">Not found</h3>';
	}
}
