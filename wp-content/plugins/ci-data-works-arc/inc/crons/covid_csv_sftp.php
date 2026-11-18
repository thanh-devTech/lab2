<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$min_hour 	= 4;
$hour 		= date_i18n('H');
$date 		= date_i18n('Y-m-d');

if (isset($_GET['today'])) {
	$date = $_GET['today'];
	$hour = 24;
}

$show_pid_crp   = tr_get_option('ci_pdf_data', 'show_pid_crp');
$enable_export  = tr_get_option('ci_pdf_data', 'e_export_csvsftp');
$export_all  	= tr_get_option('ci_pdf_data', 'e_export_csvsftp_all');
$show_elink_id  = tr_get_option('ci_pdf_data', 'e_export_csvsftp_elink');
$dir            = tr_get_option('ci_pdf_data', 'export_csv_folder');
$dir			= trim($dir);
$pid_title      = tr_get_option('ci_pdf_data', 'title_pid_field');
$export_today_batch      = tr_get_option('ci_pdf_data', 'e_export_csvsftp_tdb');
$use_rejected_file = true;
if (!$enable_export || empty($dir)) {
	echo 'empty dir';
	return;
}
/*

file_put_contents( '/usr/local/deployments/imt/data/sftp_myhometownuc/results/test.csv', 'test');
echo 'put test file';

$files = scandir('/usr/local/deployments/imt/data/sftp_myhometownuc/results');
var_dump($files);
*/

if ($hour < 24 && $hour > $min_hour) {
	echo 'out hour';
	$mid_night = strtotime(date_i18n('Y-m-d')) + 86400;
	$next_cron = wp_next_scheduled('cron_api_covid_csv_sftp');
	if (!$next_cron) {
		wp_schedule_single_event($mid_night, 'cron_api_covid_csv_sftp');
	}

	return;
}




$rejected_status = tr_pdd_where_batch_rejected(true, false, 'pcr');
$pos_neg = array(
	'POS' => 'Positive',
	'NEG' => 'Negative',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'PRP' => 'Positive',
	'INV' => 'Invalid',
	'NGE' => 'Negative',
);
if ($hour <= $min_hour) {
	$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
	$date = date('Y-m-d', time() + $offset - 86400);
}

$where = '';
if (isset($_GET['end_date'])) {
	$date = $_GET['end_date'];
	$where .= " and " . tr_pdd_where_batch_rejected(false, false, 'pcr');;
} else {
	$where .= " and re.approve_date >= '{$date}' ";
}

$where .= " and re.approve_date <= '{$date} 23:59:59' ";
$group_covid = $wpdb->get_row("select * from ci_test_groups where tg_is_covid = 1 and tg_type='pcr' ");
if (!$group_covid) {
	$group_covid = $wpdb->get_row("select * from ci_test_groups where tg_name like '%ncov%' and tg_type='pcr' ");
}
$covid_group_id = $group_covid->tg_ID;
$default_arr = trpdd_get_pcr_covid_test();
$default_arr['drug'] = ($default_arr['te_name']);

$fields_more = '';
$join_more = '';
if ($show_elink_id || $use_rejected_file) {
	$fields_more .= ",ao.other_info";
	$join_more .= " left join ci_sync_api_orders ao ON ao.mo_mid = ba.mid
						and ao.mo_file_id = -2
						and ao.mo_btype = 'pcr' ";
}


if (!$export_all) {
	$where .= " and FIND_IN_SET('GROUP_{$covid_group_id}',ba.panel_tests_requested) ";
}

$sql = "select ba.patient_name, 
	            ba.dob, 
	            ba.doc,
	            ba.spec_received,
	            ba.requesting_practice,
	            ba.requesting_physician,
	            ba.assen,
	            ba.oldreq,
	            ba.batch_status,
	            
	            pa.pa_first,
	            pa.pa_last,
	            pa.pa_mrn,
                pa.pa_address,
                pa.pa_city,
                pa.pa_state,
                pa.pa_zip,
                pa.pa_phone,
                pa.pa_gender as sex,
                pa.pa_race,
                pa.pa_ethnicity,
                re.id,
                re.missingcovid,
                re.created_date,
                re.approve_date,
                re.instrument,
                
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`,
                
                lab.lab_name,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip
                {$fields_more}
                from ci_batches_pcr ba
                join ci_clinic cli On cli.cID = ba.cli_ID
                left join ci_labs lab ON lab.lab_ID = ba.lab_ID
                left join ci_patients pa ON ba.patient_id = pa.pa_ID
                join ci_reports_pcr re ON re.patient_id = ba.assen
                {$join_more}
                where re.estatus = 'C'
                    {$where}
                order by ba.patient_name asc";
$rows = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
	var_dump($sql);
}

$results = array();
$results_rejected = array();
$count_status = array();
foreach ($rows as $row) {
	if (isset($rejected_status[$row->batch_status])) {
		$results_rejected[] = $row;
	} else {
		$row->age = trdd_dob2ages($row->dob);

		$arr = trpdd_cal_ncovid19($default_arr, $row->id, $row->instrument, $row->created_date);

		if ($arr['pos-neg'] == 'PRP') {
			$arr['pos-neg'] = 'POS';
		}
		if ($arr['pos-neg'] == 'NGE') {
			$arr['pos-neg'] = 'NEG';
		}
		$row->test_result = $arr['pos-neg'];

		if ($row->test_result == 'PEN') continue;


		$count_status[$row->test_result]++;


		$results[] = $row;
	}
}

if (isset($_GET['end_date'])) {
	$results = array();
}

if (count($results) > 0) {

	$file_name = $date . ".csv";
	$file_path = $dir . '/' . $file_name;

	echo 'file: ' . $file_path . '<br>';

	$f = fopen($file_path, 'w');
	if (!$f) {
		echo 'cant open file' . '<br>';
		return;
	}

	$headers = array(
		'Accession',
		'Patient First',
		'Patient Last',
		'Patient ID',
		'Birthday',
		'Age',
		'Sex',
		'Race',
		'Ethnicity',
		'Address',
		'City',
		'State',
		'Zip',
		'Phone',
		'Doctor Last Name',
		'Doctor First Name',
		'Clinic',
		'Clinic Address',
		'Clinic City',
		'Clinic State',
		'Clinic Zip',
		'Clinic Phone',
		'Lab Address',
		'Lab City',
		'Lab State',
		'Lab Zip',
		'DOC',
		'Collection Date Time',
		'Received Date',
		'Result Date',
		'Date Resulted'
	);
	if ($show_pid_crp) {
		if (empty($pid_title)) {
			$pid_title = 'PID';
		}
		$headers[] = $pid_title;
	}

	if ($show_elink_id) {
		$headers[] = 'eLink id';
	}
	$headers[] = 'Test Result';
	fputcsv($f, $headers);

	//put data

	$count_row = 0;
	foreach ($results as $row) {
		if (!isset($rejected_status[$row->batch_status])) {
			if (strpos($row->requesting_physician, ',') > 0) {
				list($doctor_last, $doctor_first) = explode(',', $row->requesting_physician);
			} else {
				list($doctor_first, $doctor_last) = explode(' ', $row->requesting_physician, 2);
			}
			$doctor_first = trim($doctor_first);
			$doctor_last  = trim($doctor_last);

			$clinic_phone = trpdd_format_phone_number($row->{'cli_om-phone'});

			$data = array(
				$row->assen,
				$row->pa_first,
				$row->pa_last,
				$row->pa_mrn,
				$row->dob,
				$row->age,
				$row->sex,
				$row->pa_race,
				$row->pa_ethnicity,
				$row->pa_address,
				$row->pa_city,
				$row->pa_state,
				$row->pa_zip,
				$row->pa_phone,
				$doctor_last,
				$doctor_first,
				$row->requesting_practice,
				$row->cli_street,
				$row->cli_city,
				$row->cli_state,
				$row->cli_zip,
				$clinic_phone,
				$row->lab_address,
				$row->lab_city,
				$row->lab_state,
				$row->lab_zip,
				tr_pdd_display_date($row->doc, 'Y-m-d'),
				tr_pdd_display_date($row->doc, 'YmdHis-0000'),
				tr_pdd_display_date($row->spec_received, 'YmdHis-0000'),
				tr_pdd_display_date($row->approve_date, 'Y-m-d'),
				tr_pdd_display_date($row->approve_date, 'YmdHis-0000'),

			);
			if ($show_pid_crp) {
				$data[] = $row->oldreq;
			}

			if ($show_elink_id) {
				$other_info = json_decode($row->other_info, true);
				$data[] = $other_info['MSH4'];
			}

			$data[] = $pos_neg[$row->test_result];
			fputcsv($f, $data);
		}

		$count_row++;
	}
	@fclose($f);

	echo 'count:' . $count_row;
}


//rejected file
if ($use_rejected_file) {
	$file_name_rejected = $date . '_rejected.csv';
	if (isset($_GET['end_date'])) {
		$file_name_rejected = 'Start_to_' . $date . '_rejected.csv';
	}
	$file_path_rejected = $dir . '/' . $file_name_rejected;
	$f_rejected = fopen($file_path_rejected, 'w');

	if ($f_rejected) {
		$headers_rejected = array(
			'Accession',
			'Patient ID',
			'Visit ID',
			'Elink',
			'Status',
			'Date_of_Collection'
		);
		fputcsv($f_rejected, $headers_rejected);


		foreach ($results_rejected as $row) {
			//put rejected
			if ($use_rejected_file) {
				$other_info = json_decode($row->other_info, true);

				$data_rejected = array(
					$row->assen,
					$row->pa_mrn,
					$row->oldreq,
					$other_info['MSH4'],
					$rejected_status[$row->batch_status],
					tr_pdd_display_date($row->doc, 'Y-m-d')
				);
				fputcsv($f_rejected, $data_rejected);
			}
		}
		@fclose($f_rejected);
	}
}



if ($export_today_batch) {

	$file_name = $date . '_doc.csv';
	$filepath = $dir . '/' . $file_name;

	$f = fopen($filepath, 'w');

	if ($f) {
		$all_status = tr_pdd_get_batch_statuses(false, false, false, 'all');


		$where = '';
		$where .= " and ba.doc >= '{$date}'  ";
		$where .= " and ba.doc <= '{$date} 23:59:59'  ";

		if (!$export_all) {
			$where .= " and FIND_IN_SET('GROUP_{$covid_group_id}',ba.panel_tests_requested) ";
		}

		$fields_more = '';
		$join_more = '';
		if ($show_elink_id) {
			$fields_more .= ",ao.other_info";
			$join_more .= " left join ci_sync_api_orders ao ON ao.mo_mid = ba.mid
							and ao.mo_file_id = -2
							and ao.mo_btype = 'pcr' ";
		}

		$sql = "select ba.patient_name, 
					ba.dob, 
					ba.doc,
					ba.spec_received,
					ba.requesting_practice,
					ba.requesting_physician,
					ba.assen,
					ba.oldreq,
					ba.batch_status,
					
					pa.pa_first,
					pa.pa_last,
					pa.pa_mrn,
					pa.pa_address,
					pa.pa_city,
					pa.pa_state,
					pa.pa_zip,
					pa.pa_phone,
					pa.pa_gender as sex,
					pa.pa_race,
					pa.pa_ethnicity,
					re.id,
					re.missingcovid,
					re.created_date,
					re.approve_date,
					re.instrument,
					
					cli.cli_street,
					cli.cli_city,
					cli.cli_state,
					cli.cli_zip,
					cli.`cli_om-phone`,
					
					lab.lab_name,
					lab.lab_address,
					lab.lab_city,
					lab.lab_state,
					lab.lab_zip
					{$fields_more}
					from ci_batches_pcr ba
					join ci_clinic cli On cli.cID = ba.cli_ID
					left join ci_labs lab ON lab.lab_ID = ba.lab_ID
					left join ci_patients pa ON ba.patient_id = pa.pa_ID
					left join ci_reports_pcr re ON re.patient_id = ba.assen
					{$join_more}
					where  1 {$where}
					order by ba.patient_name asc";
		$rows = $wpdb->get_results($sql);

		$i = 1;
		$headers = array(
			'Accession',
			'Patient First',
			'Patient Last',
			'Patient ID',
			'Birthday',
			'Age',
			'Sex',
			'Race',
			'Ethnicity',
			'Address',
			'City',
			'State',
			'Zip',
			'Phone',
			'Doctor Last Name',
			'Doctor First Name',
			'Clinic',
			'Clinic Address',
			'Clinic City',
			'Clinic State',
			'Clinic Zip',
			'Clinic Phone',
			'Lab Address',
			'Lab City',
			'Lab State',
			'Lab Zip',
			'DOC',
			'Collection Date Time',
			'Received Date',
			'Result Date',
			'Date Resulted'
		);
		if ($show_pid_crp) {
			if (empty($pid_title)) {
				$pid_title = 'PID';
			}
			$headers[] = $pid_title;
		}

		if ($show_elink_id) {
			$headers[] = 'eLink id';
		}
		$headers[] = 'Test Result';

		fputcsv($f, $headers);

		foreach ($rows as $row) {
			if (strpos($row->requesting_physician, ',') > 0) {
				list($doctor_last, $doctor_first) = explode(',', $row->requesting_physician);
			} else {
				list($doctor_first, $doctor_last) = explode(' ', $row->requesting_physician, 2);
			}
			$doctor_first = trim($doctor_first);
			$doctor_last  = trim($doctor_last);

			$row->age = trdd_dob2ages($row->dob);

			$clinic_phone = trpdd_format_phone_number($row->{'cli_om-phone'});

			$data = array(
				$row->assen,
				$row->pa_first,
				$row->pa_last,
				$row->pa_mrn,
				$row->dob,
				$row->age,
				$row->sex,
				$row->pa_race,
				$row->pa_ethnicity,
				$row->pa_address,
				$row->pa_city,
				$row->pa_state,
				$row->pa_zip,
				$row->pa_phone,
				$doctor_last,
				$doctor_first,
				$row->requesting_practice,
				$row->cli_street,
				$row->cli_city,
				$row->cli_state,
				$row->cli_zip,
				$clinic_phone,
				$row->lab_address,
				$row->lab_city,
				$row->lab_state,
				$row->lab_zip,
				tr_pdd_display_date($row->doc, 'Y-m-d'),
				tr_pdd_display_date($row->doc, 'YmdHis-0000'),
				tr_pdd_display_date($row->spec_received, 'YmdHis-0000'),
				tr_pdd_display_date($row->approve_date, 'Y-m-d'),
				tr_pdd_display_date($row->approve_date, 'YmdHis-0000'),

			);
			if (isset($show_pid_crp) && $show_pid_crp) {
				$data[] = $row->oldreq;
			}

			if (isset($show_elink_id) && $show_elink_id) {
				$other_info = !empty($row->other_info) ? json_decode($row->other_info, true) : array();
				$data[] = $other_info['MSH4'];
			}

			$data[] = $pos_neg[$row->test_result];
			$data[] = $panel;
			fputcsv($f, $data);
		}

		@fclose($f);
	}
}


if (tr_get_option('ci_pdf_data', 'inb_post_errors')) {
	//add post-analytical

	$where = '';
	$where .= " and lu.created >= '{$date}' ";
	$where .= " and lu.created <= '{$date} 23:59:59' ";
	$where_actions = "and lu.user_action in ('edit_rs_cd','edit_approve_rs_cd','save_req_cd')";

	$order = 'desc';
	$order_by = 'created';
	$child_queries = array();
	$total = 0;
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$child_queries[] = "select ba.mid,
                        ba.assen,
                        ba.batch_status,
                        ba.batch_id,
                        ba.spec_received,
                        ba.requesting_practice,
                        ba.billing_notes,
                        ba.issue_photo,
                        ba.author_ID,
                        lu.user_action,
                        lu.created,
                        lu.data,
                        u.user_email
                        from {$batch_tbl} ba
                        join ci_log_users lu ON ba.mid = lu.mid 
                                        and lu.btype = '{$btype}'
                                        {$where_actions}
                        join {$wpdb->users} u ON lu.user_id = u.ID
                        where 1 {$where} 
                        ";
	}


	$file_name = $date . '_post_analytical.csv';
	$f = fopen($dir . '/' . $file_name, 'w');
	if ($f) {
		$headers = array('Date', 'Accession', 'Clinic', 'Reason', 'Note', 'User');
		fputcsv($f, $headers);

		$child_queries = implode(" UNION ALL ", $child_queries);
		$sql = "select * from ({$child_queries}) ba
			order by {$order_by} {$order}
			";
		$results = $wpdb->get_results($sql);
		foreach ($results as $row) {
			$cdata = json_decode($row->data, true);
			$changes = array();
			foreach ($cdata as $k => $vl) {
				$changes[] = trpdd_get_name_by_key($k) . ': ' . $vl;
			}
			$rowdata = array(
				date('Y-m-d', strtotime($row->created)),
				$row->assen,
				$row->requesting_practice,
				'Modified ' . (stripos($row->user_action, '_rs') > 0 ? 'Result' : 'Req'),
				implode(', ', $changes),
				$row->user_email
			);
			fputcsv($f, $rowdata);
		}
		fclose($f);
	}






	//add analytical errors
	$file_name_analytical = $date . '_analytical_errors.csv';
	$file_path_analytical = $dir . '/' . $file_name_analytical;
	$f = fopen($file_path_analytical, 'w');
	if ($f) {
		$headers = array('Title', 'Date', 'Error Type', 'Description of Error', 'Accession', 'User');
		fputcsv($f, $headers);

		$where = '';
		$where .= " and ba.edate >= '{$date}'  ";
		$where .= " and ba.edate <= '{$date} 23:59:59'  ";

		$sql = "select ba.*,
				u.user_email
				from ci_analytical_errors ba
			left join {$wpdb->users} u ON u.ID = ba.created_by
			where 1 {$where}
			order by {$order_by} {$order}";
		$results = $wpdb->get_results($sql);
		foreach ($results as $row) {
			$rowdata = array(
				$row->title,
				$row->edate,
				'Analytical',
				$row->desc,
				$row->assen,
				$row->user_email
			);
			fputcsv($f, $rowdata);
		}
		fclose($f);
	}
}


//export plates number
$labs = $wpdb->get_results("select lab_ID,lab_dba_init from ci_labs where (lab_is_ref=0 or lab_is_ref=2) ");

$plates_file = $dir . '/' . $date . '_plates.csv';
$headers = array('Date', 'Number');
if (count($labs) > 1) {
	$headers[] = 'Lab';
}

$f = fopen($plates_file, 'w');
if ($f) {
	if (!empty($headers)) {
		fputcsv($f, $headers);
	}

	if (count($labs) > 1) {
		foreach ($labs as $lab) {
			$plates = $wpdb->get_var("select count(DISTINCT(pl.pl_ID))
										from ci_plates pl
										join ci_plates_batches pb ON pb.pl_ID = pl.pl_ID and pb.btype='pcr'
										join ci_batches_pcr ba ON ba.mid = pb.mid
										where pl_today = '{$date}' 
										and ba.lab_id = {$lab->lab_ID}");
			fputcsv($f, array($date, $plates, $lab->lab_dba_init));
		}
	} else {
		$plates = $wpdb->get_var("select count(*) from ci_plates where pl_today = '{$date}'");
		fputcsv($f, array($date, $plates));
	}

	fclose($f);
}


//export e_export_csvsftp_pro_sampl
if (tr_get_option('ci_pdf_data', 'e_export_csvsftp_pro_sampl')) {

	$btype_selected = 'pcr';
	$filepath = $dir . '/' . $date . '_processed_samples.csv';
	$labname = $wpdb->get_var("select lab_name from ci_labs order by lab_ID asc limit 1 ");


	$headers = array('Panel', 'Proccessed', $labname, 'Referenced', 'Rejected', 'Rerun Rate');
	tr_pdd_header('text/csv', $filename);
	$f = fopen($filepath, 'w');
	if ($f) {
		fputcsv($f, $headers);

		$clinics_rs 	= $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic ");
		foreach ($clinics_rs as $r) {
			$clinics[$r->cID] = $r;
		}



		$all_panels 	= array();
		$all_labs 		= array();

		$all_tests = array();
		if ($btype_selected == 'tox') {
			$rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype_selected}' ");
			foreach ($rs as $r) {
				$all_tests[strtoupper($r->te_name)] = $r->te_group_id;
			}
		}

		$rs = $wpdb->get_results("select tg_ID, tg_name
				from ci_test_groups 
				where tg_type = '{$btype_selected}' 
				order by tg_position asc");


		foreach ($rs as $r) {
			$all_panels[$r->tg_ID] = $r->tg_name;
		}

		$rs = $wpdb->get_results("select * from ci_labs");
		foreach ($rs as $r) {
			$all_labs[$r->lab_ID] = $r;
		}


		$reports    = array();
		$report_clids = array();

		$all_rejected_status = tr_pdd_where_batch_rejected(true);
		$where = ' is_saved = 0 ';
		$where .= " and re.approve_date >= '{$date}' ";
		$where .= " and re.approve_date <= '{$date} 23:59:59' ";
		$where .= " and re.estatus = 'C' ";



		$rerunfield = 're.covid_pos';
		$rerunfield .= ',re.rerun_count';

		$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);

		$sql_reports = "select 
					ba.panel_tests_requested,
					ba.cli_ID,
					ba.ref_lab_ID,
					ba.batch_status,
					re.id,
					re.estatus,
					{$rerunfield}
					from {$batch_tbl} ba
					join {$report_tbl} re ON re.patient_id = ba.assen
					where {$where}";
		$rows = $wpdb->get_results($sql_reports);


		if (isset($_GET['debug'])) {
			var_dump($sql_reports);
			var_dump($wpdb->last_error);
		}

		foreach ($rows as $row) {
			$groupsids = array();
			foreach (explode(',', $row->panel_tests_requested) as $t) {
				if (stripos($t, 'GROUP_') !== false) {
					$gid = str_replace('GROUP_', '', $t);
					if (!in_array($gid, $groupsids)) {
						$groupsids[] = $gid;
					}
				} else if ($btype_selected == 'tox' && isset($all_tests[strtoupper($t)])) {
					$gid = $all_tests[strtoupper($t)];

					if (!in_array($gid, $groupsids)) {
						$groupsids[] = $gid;
					}
				}
			}


			foreach ($groupsids as $panelid) {

				$gid = $panelid;
				$gid .= '_' . $row->cli_ID;
				$report_clids[$row->cli_ID] = $row->cli_ID;
				if (!isset($reports[$gid])) {
					$reports[$gid] = array(
						'panel' 	=> $all_panels[$panelid],
						'processed' => 0,
						'main' 		=> 0,
						'ref' 		=> 0,
						'rejected' 	=> 0,
						'rerun'		=> 0,
					);
				}


				if (isset($all_rejected_status[$row->batch_status])) {
					$reports[$gid]['rejected']++;
					continue;
				}


				$reports[$gid]['processed']++;

				if ($row->ref_lab_ID > 0) {
					$reports[$gid]['ref']++;
					$reports[$gid]['refs'][$row->ref_lab_ID]++;
				} else {
					$reports[$gid]['main']++;
				}

				if (isset($row->rerun_count) && $row->rerun_count > 0) {
					$reports[$gid]['rerun']++;
				} else if (isset($row->covid_pos) && $row->covid_pos == -2) {
					$reports[$gid]['rerun']++;
				}
			}
		}

		ksort($reports);

		foreach ($report_clids as $cid) {
			$cli = $clinics[$cid];
			fputcsv($f, array('', '', $cli->cli_clinic));
			foreach ($reports as $gid => $row) {

				unset($row['refs']);
				list($panelid, $rcliid) = explode('_', $gid);
				if ($rcliid == $cid) {
					fputcsv($f, $row);
				}
			}
			fputcsv($f, array(''));
		}
		fclose($f);
	}
}
