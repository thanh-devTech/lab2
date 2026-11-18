<?php

global $wpdb, $current_role;
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$user_id = get_current_user_id();
$by         = isset($_REQUEST['by']) ? $_REQUEST['by'] : 'ins';
if (empty($date_from)) $date_from = date_i18n('Y-m-d');
if (empty($date_to)) $date_to = date_i18n('Y-m-d');
$by_paid_date = isset($_REQUEST['by_paid_date']);


$users_show_patient = tr_get_option('ci_pdf_data', 'report_show_patients');
$show_patient_name = in_array($user_id, (array) $users_show_patient);
$rep_ids = array();
if (!tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p'))) {

	//find rep in group
	$users_reps = tr_dpp_get_users_in_group($user_id);
	foreach ($users_reps as $uid) {
		$rid = get_user_meta($uid, '_rep_id', true);
		if ($rid > 0) {
			$rep_ids[] = $rid;
		}
	}
	if ($current_role == 'distributor') {
		$rep_ids2 = $wpdb->get_col("select rep.rep_ID from ci_lab_reps AS rep
                                    JOIN ci_distributors dis ON dis.dis_ID = rep.rep_distributor_ID
                                    WHERE dis.dis_user_ID = {$user_id}");
		if (count($rep_ids2) > 0) {
			$rep_ids = array_merge($rep_ids, $rep_ids2);
		}
	}
}

$where = '';
if ($lab_id > 0) {
	//$where .= " and LB.lab_ID ='{$lab_id}' ";
	$where .= " and (LB.lab_ID ='{$lab_id}' or ba.lab_id = {$lab_id} or ba.ref_lab_ID = {$lab_id} )";
}
//$where .= " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to}' ";
if ($by_paid_date) {
	$where .= " and bb.date >= '{$date_from}' and bb.date <= '{$date_to} 23:56:56' ";
} else {
	$where .= " and ((ba.spec_received >= '{$date_from}' and ba.spec_received <= '{$date_to} 23:59:59' ) 
        or (ba.spec_received is null and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59'))";
}


//where by user role
if (count($rep_ids) > 0) {
	$rep_ids = implode(',', $rep_ids);
	$where .= " and CLI.`cli_rep-ID` in ({$rep_ids}) ";
} else {
	switch ($current_role) {
		case 'lab_rep':
		case 'lab_rep_w2':
			$rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
			$where .= " and CLI.`cli_rep-ID` = {$rep_id} ";
			break;
	}
}

if (isset($_REQUEST['debug'])) {
	var_dump($rep_ids);
	echo '<br>';
	echo $where;
	exit;
}

$select_fields = "ba.mid,
                    ba.doc,
                    ba.assen,
                    ba.patient_name,
                    ba.insurance as pinsname,
                    ba.requesting_practice,
                    ba.requesting_physician,
					CONCAT(col.col_first,' ',col.col_last) as collector,
                    bb.received_amount AS collected,
                    bb.claim_date AS billed_date,
                    bb.date AS collected_date,
                    LR.rep_comission,
                    if(pl.payer_commercial='1','C',
                    if(pl.payer_medicaid='1','A',
                    if(pl.payer_medicare='1','M',
                    if(pl.payer_workmen='1','W',
                    if(pl.payer_other='1','O',''))))) as ins_mix

                    ";

$joins = "  left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left JOIN `ci_labs` LB ON ba.lab =  LB.lab_dba_init or ba.lab_id = LB.lab_ID or ba.ref_lab_ID = LB.lab_ID
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
				left join `ci_collector` as col ON col.col_ID = ba.col_ID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID`
                left JOIN ci_payer_list pl ON pl.payer_name = ba.insurance
                ";


$order_by = "ba.insurance";
if ($show_patient_name) {
	$headers = array(
		'Type',
		'Insurance',
		'Clinic',
		'Doctor',
		'Collector',
		'Accession',
		'Service Date',
		'Patient Name',
		'Billed Date',
		'Claim Paid Amount',
		'Claim Paid Date',
		'Commission'
	);
} else {
	$headers = array(
		'Type',
		'Insurance',
		'Clinic',
		'Doctor',
		'Collector',
		'Accession',
		'Service Date',
		'Billed Date',
		'Claim Paid Amount',
		'Claim Paid Date',
		'Commission'
	);
}


if ($by == 'cli') {
	$search = isset($_REQUEST['clinic']) ? $_REQUEST['clinic'] : '';
	if (!empty($search)) {
		$where .= " and ba.requesting_practice like '%{$search}%'";
	}
	$order_by = "ba.requesting_practice";

	if ($show_patient_name) {
		$headers = array(
			'Type',
			'Clinic',
			'Doctor',
			'Collector',
			'Insurance',
			'Accession',
			'Service Date',
			'Patient Name',
			'Doc',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	} else {
		$headers = array(
			'Type',
			'Clinic',
			'Doctor',
			'Collector',
			'Insurance',
			'Accession',
			'Service Date',
			'Doc',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	}
} else if ($by == 'doc') {
	$search = isset($_REQUEST['doctor']) ? $_REQUEST['doctor'] : '';
	if (!empty($search)) {
		$where .= $wpdb->prepare(" and ba.requesting_physician like %s", '%' . $search . '%');
	}

	$select_fields .= ",concat(LR.rep_first,' ',LR.rep_last) as rep , doc.doc_npi";

	$joins .= " left join ci_doc doc ON doc.doc_ID = ba.doc_id ";
	$order_by = "ba.requesting_physician";


	if ($show_patient_name) {
		$headers = array(
			'Type',
			'Doctor',
			'Collector',
			'NPI',
			'Clinic',
			'Insurance',
			'Rep',
			'Accession',
			'Service Date',
			'Patient Name',
			'Doc',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	} else {
	}
} else if ($by == 'rep') {
	$search = isset($_REQUEST['rep']) ? $_REQUEST['rep'] : '';
	if (!empty($search)) {
		$where .= " and (LR.rep_first like '%{$search}%' or LR.rep_last like '%{$search}%') ";
	}
	$select_fields .= ",concat(LR.rep_first,' ',LR.rep_last) as rep";
	$order_by = "rep";


	if ($show_patient_name) {
		$headers = array(
			'Type',
			'Rep',
			'Doctor',
			'Collector',
			'Clinic',
			'Insurance',
			'Accession',
			'Service Date',
			'Patient Name',
			'Doc',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	} else {
		$headers = array(
			'Type',
			'Rep',
			'Doctor',
			'Collector',
			'Clinic',
			'Insurance',
			'Accession',
			'Service Date',
			'Doc',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	}
} else if ($by == 'distributor') {
	$search = isset($_REQUEST['dis']) ? $_REQUEST['dis'] : '';
	if (!empty($search)) {
		$where .= " and (DIS.dis_name like '%{$search}%' ) ";
	}
	$joins .= " left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID	 ";
	$select_fields .= ",DIS.dis_name,DIS.dis_comission";
	$order_by = "DIS.dis_name";


	if ($show_patient_name) {
		$headers = array(
			'Type',
			'Distributor',
			'Doctor',
			'Collector',
			'Clinic',
			'Insurance',
			'Accession',
			'Service Date',
			'Patient Name',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	} else {
		$headers = array(
			'Type',
			'Distributor',
			'Doctor',
			'Collector',
			'Clinic',
			'Insurance',
			'Accession',
			'Service Date',
			'Billed Date',
			'Claim Paid Amount',
			'Claim Paid Date',
			'Commission'
		);
	}
}





$file = tempnam("/tmp", "export");
$f = fopen($file, 'w');
$count = 0;


fputcsv($f, $headers);


$btypes = tr_pdd_get_batch_types();
foreach ($btypes as $k => $btype) {
	$table = tr_pdd_get_table_by_btype($btype);
	$typename = tr_pdd_get_typename($btype);

	$query = " select {$select_fields},
				'{$btype}' as btype
                from {$table} ba 
                {$joins}
                where 1 {$where} 
                group by ba.mid 
                order by {$order_by}";
	$rows = $wpdb->get_results($query);



	foreach ($rows as $r) {

		$r->commission = trpdd_cal_commission($r->collected, $r->rep_comission, $r->ins_mix, $r->btype);

		if (empty($r->collected)) {
			$r->collected_date = '';
			$r->collected = 0;
		}
		if (empty($r->pinsname)) {
			$r->pinsname = 'Unknown';
		}

		if ($by == 'cli') {
			if ($show_patient_name) {
				$row = array(
					$typename,
					$r->requesting_practice,
					$r->requesting_physician,
					$r->collector,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->patient_name,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			} else {
				$row = array(
					$typename,
					$r->requesting_practice,
					$r->requesting_physician,
					$r->collector,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			}
		} else if ($by == 'doc') {

			if ($show_patient_name) {
				$row = array(
					$typename,
					$r->requesting_physician,
					$r->collector,
					$r->doc_npi,
					$r->requesting_practice,
					$r->pinsname,
					$r->rep,
					$r->assen,
					$r->doc,
					$r->patient_name,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			} else {
				$row = array(
					$typename,
					$r->requesting_physician,
					$r->collector,
					$r->doc_npi,
					$r->requesting_practice,
					$r->pinsname,
					$r->rep,
					$r->assen,
					$r->doc,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			}
		} else if ($by == 'rep') {

			if ($show_patient_name) {
				$row = array(
					$typename,
					$r->rep,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->patient_name,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			} else {
				$row = array(
					$typename,
					$r->rep,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			}
		} else if ($by == 'distributor') {
			if (empty($r->dis_name)) {
				$r->dis_name = 'Unknown';
			}
			if ($show_patient_name) {
				$row = array(
					$typename,
					$r->dis_name,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->patient_name,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			} else {
				$row = array(
					$typename,
					$r->dis_name,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->pinsname,
					$r->assen,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			}
		} else {
			if ($show_patient_name) {
				$row = array(
					$typename,
					$r->pinsname,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->assen,
					$r->doc,
					$r->patient_name,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			} else {
				$row = array(
					$typename,
					$r->pinsname,
					$r->requesting_physician,
					$r->collector,
					$r->requesting_practice,
					$r->assen,
					$r->doc,
					$r->billed_date,
					number_format($r->collected, 2, '.', ''),
					$r->collected_date,
					number_format($r->commission, 2, '.', '')
				);
			}
		}

		fputcsv($f, $row);
		$count++;
	}
}



if ($count == 0) {
	Tr_Session_Class::add_error('Not found!');
	return false;
}

$filename = 'sale_Report_' . $date_from . '_' . $date_to . '.csv';


header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";");
header("Content-Transfer-Encoding: binary");

readfile($file);
@unlink($file);
exit;
