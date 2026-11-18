<?php
global $wpdb, $current_role;

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] === 'force_send') {
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	try {
		$date =  isset($_REQUEST['date']) ? $_REQUEST['date'] : date_i18n('Y-m-d');
		$assen =  isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
		$client = new CI_Lab_Client_Api(true);
		$rs = $client->force_send_report(['date' => $date, 'assen' => $assen]);
		echo json_encode($rs);
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => $e->getMessage()
		]);
	}
	exit;
}

$schedule_report = new CI_Report_Schedule('state_report', 'State Report');
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
if (!isset($is_v2_3)) {
	$is_v2_3 = false;
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

$default_to_date = date_i18n('Y-m-d');
$default_from_date = date_i18n('Y-m-d', strtotime('-1 day'));
$from_date = !empty($_GET['from']) ? $_GET['from'] : $default_from_date;
$to_date = !empty($_GET['to']) ? $_GET['to'] : $default_to_date;
$today_date = date_i18n('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from_date)) {
	$from_date = $default_from_date;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to_date)) {
	$to_date = $default_to_date;
}
if ($from_date > $today_date) {
	$from_date = $today_date;
}
if ($to_date > $today_date) {
	$to_date = $today_date;
}
if ($from_date > $to_date) {
	$to_date = $from_date;
}
$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$rtype = isset($_REQUEST['rtype']) ? $_REQUEST['rtype'] : 'pcr';
$date_mode = isset($_REQUEST['date_mode']) ? trim((string) $_REQUEST['date_mode']) : 'approved';
$state_filter = isset($_REQUEST['state']) ? strtoupper(trim((string) $_REQUEST['state'])) : '';
$email_to = isset($_REQUEST['email_to']) ? trim((string) $_REQUEST['email_to']) : '';
$schedule_report->ajax();

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$show_pending = false;
$tests_str = '';
$configure_report_url = 'admin.php?page=ci_lab_main&act=state_lab';

$options = get_option('ci_send_hub_tests', array());
$obj_option = array_column($options, null, 'tid');
$test_panel_map = array();
$group_name_map = array();
$group_parent_map = array();
$top_panel_name_map = array();
$safe_rtype = esc_sql($rtype);
$test_groups = $wpdb->get_results("select tg_ID, tg_name, tg_parent, tg_show_req from ci_test_groups where tg_type = '{$safe_rtype}'");
foreach ($test_groups as $tg_row) {
	$tg_id = (int) $tg_row->tg_ID;
	$tg_parent = (int) $tg_row->tg_parent;
	$group_name_map[$tg_id] = $tg_row->tg_name;
	$group_parent_map[$tg_id] = $tg_parent;
	if ($tg_parent === 0 && (int) $tg_row->tg_show_req === 1) {
		$top_panel_name_map[$tg_id] = trim(str_replace('(new)', '', (string) $tg_row->tg_name));
	}
}
$resolve_top_group_id = function ($group_id) use (&$group_parent_map) {
	$group_id = (int) $group_id;
	if ($group_id <= 0) {
		return 0;
	}
	$safety = 0;
	while (isset($group_parent_map[$group_id]) && (int) $group_parent_map[$group_id] > 0 && $safety < 20) {
		$group_id = (int) $group_parent_map[$group_id];
		$safety++;
	}
	return $group_id;
};
$test_panel_rows = $wpdb->get_results("select te_ID, te_group_id from ci_test_panels where te_type = '{$safe_rtype}'");
foreach ($test_panel_rows as $tp_row) {
	$group_id = (int) $tp_row->te_group_id;
	$top_group_id = $resolve_top_group_id($group_id);
	$panel_name = isset($top_panel_name_map[$top_group_id]) ? $top_panel_name_map[$top_group_id] : '';
	$test_panel_map[(int) $tp_row->te_ID] = $panel_name;
}

$btypes = array_column($options, 'btype');
$btypes = array_unique($btypes);
$all_states = tr_get_states();
$states_filter_options = array();
$configured_state_codes = array();

foreach ($options as $option_item) {
	$state_code = strtoupper(trim((string) ($option_item['state'] ?? '')));
	if ($state_code === '') {
		continue;
	}

	$configured_state_codes[$state_code] = $state_code;
	$states_filter_options[$state_code] = isset($all_states[$state_code]) ? $all_states[$state_code] : $state_code;
}

ksort($states_filter_options);

if (!empty($state_filter) && $state_filter !== 'ALL' && !isset($states_filter_options[$state_filter])) {
	$state_filter = '';
}

$list_pos_neg_default = array(
	'POS' => 'Detected',
	'NEG' => 'Negative',
	'POL' => 'Detected Low',
	'POM' => 'Detected Medium',
	'POH' => 'Detected High',
	'PVL' => 'Detected Very Low',
	'PVH' => 'Detected Very High',
);

$rejected_status = tr_pdd_where_batch_rejected(true, false, 'pcr');
$races = array(
	'1002-5' => 'American Indian or Alaska Native',
	'2028-9' => 'Asian',
	'2054-5' => 'Black / African American',
	'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
	'2106-3' => 'White',
	'2131-1' => 'Other'
);
$Ethnicitis = array(
	'H' => 'Hispanic or Latino',
	'N' => 'Not Hispanic or Latino',
	'U' => 'Unknown'
);

$use_racedesc = tr_get_option('ci_pdf_data', 'use_racedesc');

$doc_ID = 0;
$user_id = get_current_user_id();
$show_all = false;
$clinics = array();

switch ($current_role) {
	case 'lab_rep':
	case 'lab_rep_w2':

		$rep_id = get_user_meta($user_id, '_rep_id', true);
		$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                        where cli.cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");


		break;
	case 'doctor':
		$doc_ID = get_user_meta($user_id, '_doc_id', true);

		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
		                                join ci_clinic_doc cd ON cli.cID = cd.cli_ID
                                    where cli.cli_status = 1 and cd.doc_ID = {$doc_ID}
                                     order by cli.cli_clinic asc");
		break;

	case 'collector':
		$col_id = get_user_meta($user_id, '_collector_id', true);
		$cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
		$cids = implode(',', $cids);
		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");
		break;

	case 'clinic':
		$cli_ID = get_user_meta($user_id, '_clinic_id', true);
		break;

	case 'office_manager':
		$cid = get_user_meta($user_id, '_clinic_id', true);
		$where_cli = "";
		if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
			$cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
			$cids = implode(',', $cids);
			$where_cli .= " and cli.cID in ({$cids}) ";
		}

		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");

		break;


	case 'multi_site_manager':
		$where_cli = '';
		if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
			$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
			if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
				$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
			}
			$ms_cli_ids_str = implode(',', $ms_cli_ids);
			$where_cli .= " and cli.cID in ({$ms_cli_ids_str}) ";
		}


		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");
		break;

	default:
		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
		$show_all = true;
		break;
}



$pos_neg_detected = ['POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'];

$child_drugs = array();
$child_drugs_str = '';

$default_arr = array();

$title_export = '';
$results = array();
$report = array(
	'all_item' => array()
);
$total_rs = 0;
$detected_count = 0;
$unique_state_count = 0;

if (isset($_REQUEST['bydoc']) && $_REQUEST['bydoc']) {
	$date_mode = 'doc';
} else if (isset($_REQUEST['bybat']) && $_REQUEST['bybat']) {
	$date_mode = 'batch';
}
if (!in_array($date_mode, array('approved', 'doc', 'batch'), true)) {
	$date_mode = 'approved';
}

$trajax_action = isset($_REQUEST['trajax']) ? trim((string) $_REQUEST['trajax']) : '';
$should_load_report_data = in_array($act, array('export', 'email_csv'), true)
	|| $trajax_action === 'loadData'
	|| isset($_GET['debug']);

if ($from_date && $to_date && $should_load_report_data) {
	$where = '';

	if ((empty($cli_ID) || $cli_ID === 'all') && !$show_all) {
		$cids = array();
		foreach ($clinics as $clinic) {
			$cids[] = $clinic->cID;
		}
		$cids = implode(',', $cids);
		$where .= " and ba.cli_ID in ({$cids}) ";
	} else if ($cli_ID != 'all' && $cli_ID > 0) {
		$where .= " and ba.cli_ID = {$cli_ID} ";
		$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_ID}");
		$title_export = $clinic->cli_clinic;
	}

	if ($doc_ID > 0) {
		$where .= " and ba.doc_ID = {$doc_ID} ";
	}
	$from_b = str_replace('-', '', $from_date);
	$to_b = str_replace('-', '', $to_date);

	if ($date_mode === 'batch') {
		$fromb = str_replace('-', '', $from_date);
		$tob = str_replace('-', '', $to_date);
		$where .= "and ba.batch_id >= {$fromb} and ba.batch_id <= {$tob} ";
	} else if ($date_mode === 'doc') {
		$where .= "and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date} 23:59:59' ";
	} else {
		$where .= "and re.approve_date >= '{$from_date}' and re.approve_date <= '{$to_date} 23:59:59' ";
	}

	if (!empty($configured_state_codes)) {
		$state_placeholders = implode(',', array_fill(0, count($configured_state_codes), '%s'));
		$where .= $wpdb->prepare(
			" and pa.pa_state is not null and TRIM(pa.pa_state) <> '' and UPPER(pa.pa_state) IN ({$state_placeholders}) ",
			array_values($configured_state_codes)
		);
	} else {
		$where .= " and 1 = 2 ";
	}

	if (!empty($state_filter) && $state_filter !== 'ALL') {
		$where .= $wpdb->prepare(" and UPPER(pa.pa_state) = %s ", $state_filter);
	}

	$option_by_testid = [];
	$option_by_testid_state = [];
	foreach ($options as $opt) {
		$tid = isset($opt['tid']) ? (int) $opt['tid'] : 0;
		$opt_state = strtoupper(trim((string) ($opt['state'] ?? '')));
		if ($tid > 0 && $opt_state !== '') {
			$opt['state'] = $opt_state;
			$option_by_testid[$tid][] = $opt;
			if (!isset($option_by_testid_state[$tid][$opt_state])) {
				$option_by_testid_state[$tid][$opt_state] = [];
			}
			$option_by_testid_state[$tid][$opt_state][] = $opt;
		}
	}
	// Lấy danh sách tid trong report to state
	$option_tids = array_keys($option_by_testid);
	$find_in_set_conditions = [];

	foreach ($option_tids as $tid) {
		$find_in_set_conditions[] = "find_in_set('{$tid}', ba.panel_tests_requested)";
	}

	if (!empty($find_in_set_conditions)) {
		$where .= " AND (" . implode(' OR ', $find_in_set_conditions) . ")";
	}

	$more_fields = '';
	$joins = '';
	if ($is_v2_3) {
		$more_fields .= ',ba.type_of_specimen,
            doc.doc_first,
            doc.doc_last,
            doc.doc_npi,
            doc.doc_phone';
		$joins .= 'join ci_doc doc ON doc.doc_ID = ba.doc_ID';
	}

	$sql = "select 
		        ba.patient_name, 
	            ba.doc,
	            ba.spec_received,
	            ba.requesting_practice,
	            ba.requesting_physician,
	            ba.assen,
	            ba.oldreq,
	            ba.batch_status,
	            ba.p_condition,
	            ba.panel_tests_requested,
	            
	            pa.*,
                
                cli.cli_clinic,
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`,
                
                lab.lab_dba_init,
                lab.lab_clia,
                lab.lab_name,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                
                re.id,
	
                re.created_date,
                re.approve_date,
                re.instrument
                {$more_fields}
                from ci_batches_pcr ba
                join ci_clinic cli On cli.cID = ba.cli_ID
                left join ci_labs lab ON lab.lab_ID = ba.lab_ID
                left join ci_patients pa ON ba.patient_id = pa.pa_ID
                join ci_reports_pcr re ON re.patient_id = ba.assen
                {$joins}
                where re.estatus = 'C'
                    {$where}
                order by ba.patient_name asc";
	$rows = $wpdb->get_results($sql);


	$count_status = array();

	if (isset($_GET['debug'])) {
		echo '<br>count: ' . count($rows) . '<br>';
	}

	foreach ($rows as $row) {

		if (isset($rejected_status[$row->batch_status])) {
			continue;
		}

		$row->age = trdd_dob2ages($row->pa_dob);
		$row_state_code = strtoupper(trim((string) $row->pa_state));
		if ($row_state_code === '' || !isset($configured_state_codes[$row_state_code])) {
			continue;
		}

		// lấy danh sách tid trong panel
		$test_ids = array_filter(array_map('intval', explode(',', $row->panel_tests_requested)));

		// lấy tid tồn tại trong options
		$existing_tids = array_intersect($test_ids, array_keys($option_by_testid));
		if (empty($existing_tids))
			continue;


		$row->existing_tids = $existing_tids;

		// lấy GROUP_*
		$groups = [];
		foreach (explode(',', $row->panel_tests_requested) as $p) {
			if (stripos($p, 'GROUP_') !== false) {
				$groups[] = (int) str_replace('GROUP_', '', $p);
			}
		}

		$listTest = tr_pdd_get_results_by_testsid(
			$row->id,
			$rtype,
			$test_ids,
			$groups,
			$row->instrument,
			true
		);


		$matchedTests = 0;
		$t_names = [];
		$r_result = [];
		$r_pn = ['all_item' => []];
		$row_countable_te_ids = [];
		$list_test_pos_neg = [];
		foreach ($listTest as $t_ID => $pn) {
			$list_test_pos_neg[$t_ID] = $pn['pos-neg'];
		}

		foreach ($listTest as $pn) {
			$te_ID = $pn['te_ID'];
			$pos_neg_val = $pn['pos-neg'];
			$list_pos_neg = $pn['pos_neg_list'] ?? [];
			if (empty($list_pos_neg)) {
				$list_pos_neg = $list_pos_neg_default;
			}

			if (!isset($option_by_testid_state[$te_ID][$row_state_code])) {
				continue;
			}

			$test_matches_state_config = false;
			foreach ($option_by_testid_state[$te_ID][$row_state_code] as $opt_cur) {
				$is_matched = true;

				if (!empty($opt_cur['cond_only_tids']) && is_array($opt_cur['cond_only_tids'])) {
					$intersect = array_intersect($opt_cur['cond_only_tids'], $test_ids);
					if (empty($intersect)) {
						$is_matched = false;
					}
					if ($is_matched) {
						$cond_only_pn = isset($opt_cur['cond_only_pos_neg']) ? $opt_cur['cond_only_pos_neg'] : 0;
						if ($cond_only_pn > 0) {
							$has_valid_pos_neg = false;

							foreach ($opt_cur['cond_only_tids'] as $check_tid) {
								$check_pos_neg = isset($list_test_pos_neg[$check_tid]) ? $list_test_pos_neg[$check_tid] : '';
								if ($cond_only_pn == 1) {
									if (in_array($check_pos_neg, $pos_neg_detected)) {
										$has_valid_pos_neg = true;
										break;
									}
								} elseif ($cond_only_pn == 2) {
									if ($check_pos_neg === 'NEG') {
										$has_valid_pos_neg = true;
										break;
									}
								}
							}
							if (!$has_valid_pos_neg) {
								$is_matched = false;
							}
						}
					}
				}

				if ($is_matched && !in_array($te_ID, $existing_tids)) {
					$is_matched = false;
				}

				if ($is_matched) {
					$cond = isset($opt_cur['cond_pos_neg']) ? $opt_cur['cond_pos_neg'] : 0;
					if ($cond > 0) {
						if ($cond == 1 && !in_array($pos_neg_val, $pos_neg_detected)) {
							$is_matched = false;
						} elseif ($cond == 2 && $pos_neg_val !== 'NEG') {
							$is_matched = false;
						}
					}
				}

				if ($is_matched) {
					$test_matches_state_config = true;
					break;
				}
			}

			if (!$test_matches_state_config) {
				continue;
			}

			$matchedTests++;

			// P/N text
			$t_names[] = $pn['te_name'];
			$r_result[] = isset($list_pos_neg[$pos_neg_val]) ? $list_pos_neg[$pos_neg_val] : $pos_neg_val;

			if (!isset($report['all_item'][$te_ID])) {
				$report['all_item'][$te_ID] = [
					'id' => $te_ID,
					'name' => $pn['te_name'],
					'panel' => isset($test_panel_map[$te_ID]) ? $test_panel_map[$te_ID] : '',
					'p' => 0,
					'n' => 0,
					'sent' => 0,
				];
			}

			if (in_array($pos_neg_val, $pos_neg_detected)) {
				$report['all_item'][$te_ID]['p']++;
				$row_countable_te_ids[] = $te_ID;
			} elseif ($pos_neg_val === 'NEG') {
				$report['all_item'][$te_ID]['n']++;
				$row_countable_te_ids[] = $te_ID;
			}
		}

		if ($matchedTests > 0 && !empty($t_names)) {
			$row->te_names = $t_names;
			$row->te_result = $r_result;
			$row->te_ids = array_values(array_unique($row_countable_te_ids));
			$row->test_result = implode(' | ', $r_result);
			$results[] = $row;
		}
	}
	$total_rs = count($results);
	$detected_count = 0;
	$unique_state_codes = array();
	foreach ($results as $result_row) {
		foreach ($result_row->te_result as $res_val) {
			if (stripos($res_val, 'Detected') !== false) {
				$detected_count++;
			}
		}
		$st_code = strtoupper(trim((string) $result_row->pa_state));
		if (!empty($st_code)) {
			$unique_state_codes[$st_code] = true;
		}
	}
	$unique_state_count = count($unique_state_codes);

	$state_upload_map = array();
	$assens = array();
	foreach ($results as $result_row) {
		if (!empty($result_row->assen)) {
			$assens[] = $result_row->assen;
		}
	}
	$assens = array_values(array_unique($assens));
	if (!empty($assens)) {
		$holders = implode(',', array_fill(0, count($assens), '%s'));
		$has_hub_statistics = (bool) $wpdb->get_var("SHOW TABLES LIKE 'ci_hub_statistics'");
		if ($has_hub_statistics) {
			$sql_state_upload = $wpdb->prepare(
				"select assen, max(date_report) as date_report from ci_hub_statistics where assen in ({$holders}) group by assen",
				$assens
			);
			$state_upload_rows = $wpdb->get_results($sql_state_upload);
			foreach ($state_upload_rows as $state_upload_row) {
				$state_upload_map[$state_upload_row->assen] = $state_upload_row->date_report;
			}
		}
	}
	foreach ($results as $result_row) {
		$result_row->state_upload = '-';
		$has_state_upload = false;
		if (!empty($result_row->assen) && isset($state_upload_map[$result_row->assen]) && !empty($state_upload_map[$result_row->assen])) {
			$state_upload_at = $state_upload_map[$result_row->assen];
			$state_code = strtoupper(trim((string) $result_row->pa_state));
			if ($state_code === '') {
				$state_code = 'STATE';
			}
			$result_row->state_upload = 'Sent to ' . $state_code . ' ' . tr_pdd_display_date($state_upload_at) . ' at ' . tr_pdd_display_date($state_upload_at, 'h:i:s A');
			$has_state_upload = true;
		}

		if ($has_state_upload && !empty($result_row->te_ids) && is_array($result_row->te_ids)) {
			foreach ($result_row->te_ids as $te_id_row) {
				$te_id_row = (int) $te_id_row;
				if ($te_id_row > 0 && isset($report['all_item'][$te_id_row])) {
					$report['all_item'][$te_id_row]['sent'] = (int) ($report['all_item'][$te_id_row]['sent'] ?? 0) + 1;
				}
			}
		}
	}
} else {
	$total_rs = 0;
	$results = array();
	$detected_count = 0;
	$unique_state_count = 0;
}

if (!function_exists('tr_state_report_csv_file_name')) {
	function tr_state_report_csv_file_name($from_date, $to_date, $state_filter = '')
	{
		$file_name = generate_report_filename('RPT', 'StateReport', $from_date, $to_date, 'csv');
		if (!empty($state_filter) && strtoupper($state_filter) !== 'ALL') {
			$state_slug = preg_replace('/[^A-Z0-9]+/', '', strtoupper($state_filter));
			$file_name = str_replace('.csv', '-' . $state_slug . '.csv', $file_name);
		}
		return $file_name;
	}
}

if (!function_exists('tr_state_report_csv_headers')) {
	function tr_state_report_csv_headers($show_pid_crp)
	{
		return array(
			'Accession',
			'First Name',
			'Last Name',
			'Birthday',
			'Age',
			'Sex',
			'Address',
			'City',
			'Zip',
			'State',
			'Phone',
			'Doctor',
			'DOC',
			'Result Date',
			'Test',
			'Result',
			'State Upload',
		);
	}
}

if (!function_exists('tr_state_report_write_csv_rows')) {
	function tr_state_report_write_csv_rows($f, $results, $rejected_status, $use_racedesc, $races, $Ethnicitis, $show_pid_crp)
	{
		foreach ($results as $row) {
			if (isset($rejected_status[$row->batch_status])) {
				continue;
			}

			$phone = str_replace(array('(', ')', '.', '-', ' '), '', $row->pa_phone);
			$phone = substr($phone, -10);
			if (strlen($phone) < 10 || $phone == 0) {
				$phone = '';
			}

			$data = array(
				$row->assen,
				$row->pa_first,
				$row->pa_last,
				tr_pdd_display_date($row->pa_dob, 'Y-m-d'),
				$row->age,
				$row->pa_gender,
				$row->pa_address,
				$row->pa_city,
				$row->pa_zip,
				$row->pa_state,
				$phone,
				$row->requesting_physician,
				tr_pdd_display_date($row->doc, 'Y-m-d'),
				tr_pdd_display_date($row->approve_date, 'Y-m-d'),
				implode(', ', $row->te_names),
				implode(', ', $row->te_result),
				isset($row->state_upload) && $row->state_upload !== '' ? $row->state_upload : '-',
			);
			fputcsv($f, $data);
		}
	}
}

if ($act == 'export') {
	ob_clean();
	$file_name = tr_state_report_csv_file_name($from_date, $to_date, $state_filter);
	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	if ($filepath_save_csv != '') {
		$f = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename($file_name);
	} else {
		$f = fopen('php://output', 'w');
	}
	$show_pid_crp = tr_get_option('ci_pdf_data', 'show_pid_crp');
	fputcsv($f, tr_state_report_csv_headers($show_pid_crp));
	tr_state_report_write_csv_rows($f, $results, $rejected_status, $use_racedesc, $races, $Ethnicitis, $show_pid_crp);
	@fclose($f);
	if ($filepath_save_csv == '') {
		$data_log = array(
			'type' => 'State Report',
			'file_name' => $file_name,
			'total_records' => count($results),
			'from_date' => $from_date,
			'to_date' => $to_date,
		);
		trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
		exit;
	}
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
}

if ($act == 'email_csv') {
	if (empty($email_to) || !is_email($email_to)) {
		Tr_Session_Class::add_error('Please enter a valid email address.');
	} else if (empty($results)) {
		Tr_Session_Class::add_error('No data available for the selected filters.');
	} else {
		$tmp_file = wp_tempnam('state-report');
		if ($tmp_file) {
			$mail_file_name = tr_state_report_csv_file_name($from_date, $to_date, $state_filter);
			$mail_base_name = preg_replace('/\.csv$/i', '', $mail_file_name);
			$csv_file = dirname($tmp_file) . '/' . $mail_file_name;
			if (file_exists($csv_file)) {
				@unlink($csv_file);
			}
			rename($tmp_file, $csv_file);
			$f = fopen($csv_file, 'w');
			$show_pid_crp = tr_get_option('ci_pdf_data', 'show_pid_crp');
			fputcsv($f, tr_state_report_csv_headers($show_pid_crp));
			tr_state_report_write_csv_rows($f, $results, $rejected_status, $use_racedesc, $races, $Ethnicitis, $show_pid_crp);
			fclose($f);

			$subject = $mail_base_name;
			$message = 'Attached is your State Report CSV export.';
			$sent = wp_mail($email_to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'), array($csv_file));
			@unlink($csv_file);

			if ($sent) {
				Tr_Session_Class::add('State report CSV was sent to ' . esc_html($email_to));
			} else {
				Tr_Session_Class::add_error('Could not send email. Please check SMTP settings.');
			}
		} else {
			Tr_Session_Class::add_error('Could not create temporary CSV file.');
		}
	}
}

if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<hr/>Options:';
	var_dump($obj_option);
	echo '<hr/>Report:';
	var_dump($report);
	echo '<hr/>Row 1:';
	var_dump($results[0]);
}

?>

<?php
$summary_rows = array_values(array_filter($report['all_item'], function ($item) {
	return (($item['p'] ?? 0) + ($item['n'] ?? 0)) > 0;
}));

usort($summary_rows, function ($a, $b) {
	$positive_compare = ($b['p'] ?? 0) <=> ($a['p'] ?? 0);
	if ($positive_compare !== 0) {
		return $positive_compare;
	}

	$negative_compare = ($b['n'] ?? 0) <=> ($a['n'] ?? 0);
	if ($negative_compare !== 0) {
		return $negative_compare;
	}

	return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});

$summary_total_positive = 0;
$summary_total_negative = 0;
$summary_total_sent = 0;
foreach ($summary_rows as $summary_item) {
	$summary_total_positive += (int) ($summary_item['p'] ?? 0);
	$summary_total_negative += (int) ($summary_item['n'] ?? 0);
	$summary_total_sent += (int) ($summary_item['sent'] ?? 0);
}

if (!function_exists('tr_state_render_upload_bullet_html')) {
	function tr_state_render_upload_bullet_html($sent, $total)
	{
		$sent = (int) $sent;
		$total = (int) $total;
		if ($total < 0) {
			$total = 0;
		}
		if ($sent < 0) {
			$sent = 0;
		}
		$sent = min($sent, $total);
		$is_all_sent = ($total > 0 && $sent >= $total);
		$badge_class = $is_all_sent
			? 'bg-green-50 border-green-200 text-green-700'
			: 'bg-red-50 border-red-200 text-red-700';

		return '<span class="inline-flex items-center justify-center min-w-[64px] px-3 py-1 rounded-md border text-xs font-medium ' . esc_attr($badge_class) . '">' . $sent . ' (' . $total . ')</span>';
	}
}

if (!function_exists('tr_state_report_render_summary_body')) {
	function tr_state_report_render_summary_body($summary_rows, $summary_total_positive, $summary_total_negative, $summary_total_sent)
	{
		$summary_percent_text = function ($positive, $negative) {
			$total = (int) $positive + (int) $negative;
			if ($total <= 0) {
				return '0%';
			}
			$percent = round(((int) $positive / $total) * 100, 1);
			$formatted = ((float) $percent === (float) (int) $percent) ? (string) (int) $percent : number_format($percent, 1);
			return $formatted . '%';
		};

		ob_start();
		if (!empty($summary_rows)) {
			foreach ($summary_rows as $summary_index => $summary_item) {
				$summary_row_class = ($summary_index % 2 === 0) ? 'bg-white' : 'bg-gray-50';
?>
				<tr class="<?php echo esc_attr($summary_row_class); ?>">
					<td class="px-3 py-1.5 text-gray-900"><?php echo esc_html($summary_item['name']); ?></td>
					<td class="px-3 py-1.5 text-gray-700"><?php echo esc_html($summary_item['panel'] ?? ''); ?></td>
					<td class="px-3 py-1.5 text-right text-gray-900"><?php echo (int) $summary_item['p']; ?></td>
					<td class="px-3 py-1.5 text-right text-gray-900"><?php echo (int) $summary_item['n']; ?></td>
					<td class="px-3 py-1.5 text-right text-gray-900"><?php echo esc_html($summary_percent_text($summary_item['p'], $summary_item['n'])); ?></td>
					<td class="px-3 py-1.5 text-left"><?php echo tr_state_render_upload_bullet_html((int) ($summary_item['sent'] ?? 0), (int) (($summary_item['p'] ?? 0) + ($summary_item['n'] ?? 0))); ?></td>
				</tr>
			<?php
			}
			?>
			<tr class="bg-gray-200 border-t-2 border-gray-300">
				<td class="px-3 py-2 font-medium text-gray-900">Total</td>
				<td class="px-3 py-2 text-gray-900"></td>
				<td class="px-3 py-2 text-right font-medium text-gray-900"><?php echo (int) $summary_total_positive; ?></td>
				<td class="px-3 py-2 text-right font-medium text-gray-900"><?php echo (int) $summary_total_negative; ?></td>
				<td class="px-3 py-2 text-right font-medium text-gray-900"><?php echo esc_html($summary_percent_text($summary_total_positive, $summary_total_negative)); ?></td>
				<td class="px-3 py-2 text-left"><?php echo tr_state_render_upload_bullet_html((int) $summary_total_sent, (int) ($summary_total_positive + $summary_total_negative)); ?></td>
			</tr>
		<?php
		} else {
		?>
			<tr class="bg-white">
				<td colspan="6" class="px-3 py-4 text-center text-gray-500">No summary data available for the selected filters.</td>
			</tr>
			<?php
		}
		return ob_get_clean();
	}
}

if (!function_exists('tr_state_report_render_data_body')) {
	function tr_state_report_render_data_body($results)
	{
		ob_start();
		if (count($results) > 0) {
			$row_index = 0;
			foreach ($results as $row) {
				$row_class = ($row_index % 2 === 1) ? 'bg-gray-50' : 'bg-white';
				$row_index++;
			?>
				<tr class="<?php echo esc_attr($row_class); ?>">
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html($row->assen) ?></td>
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html(tr_pdd_capitalized($row->pa_first)) ?></td>
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html(tr_pdd_capitalized($row->pa_last)) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html(tr_pdd_display_date($row->pa_dob, 'Y-m-d')) ?></td>
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html($row->age) ?></td>
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html($row->pa_gender) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html($row->pa_address) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html($row->pa_city) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html($row->pa_zip) ?></td>
					<td class="px-2 py-2 text-gray-900"><?php echo esc_html($row->pa_state) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html($row->pa_phone) ?></td>
					<td class="px-2 py-2 text-gray-700" title="<?php echo esc_attr($row->requesting_practice) ?>"><?php echo esc_html($row->requesting_physician) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html(tr_pdd_display_date($row->doc, 'Y-m-d')) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html(tr_pdd_display_date($row->approve_date, 'Y-m-d')) ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html(implode(', ', $row->te_names)); ?></td>
					<td class="px-2 py-2 text-gray-900 font-medium"><?php echo esc_html(implode(', ', $row->te_result)); ?></td>
					<td class="px-2 py-2 text-gray-700"><?php echo esc_html($row->state_upload ?? '-'); ?></td>
				</tr>
			<?php
			}
		} else {
			?>
			<tr class="bg-white">
				<td colspan="17" class="px-2 py-3 text-gray-500">No Results Found.</td>
			</tr>
<?php
		}
		return ob_get_clean();
	}
}

if (!function_exists('tr_state_sort_header_cell')) {
	function tr_state_sort_header_cell($key, $label)
	{
		$sort_meta = array(
			'assen' => array('col' => 0, 'type' => 'text'),
			'age' => array('col' => 4, 'type' => 'number'),
			'pa_gender' => array('col' => 5, 'type' => 'text'),
			'pa_state' => array('col' => 9, 'type' => 'text'),
			'doc' => array('col' => 12, 'type' => 'date'),
			'approve_date' => array('col' => 13, 'type' => 'date'),
			'result' => array('col' => 14, 'type' => 'text'),
		);
		$meta = isset($sort_meta[$key]) ? $sort_meta[$key] : array('col' => 0, 'type' => 'text');
		echo '<th class="px-2 py-3 text-left font-semibold cursor-pointer hover:bg-[#C43600] transition-colors js-state-sort" data-sort-key="' . esc_attr($key) . '" data-sort-col="' . (int) $meta['col'] . '" data-sort-type="' . esc_attr($meta['type']) . '">';
		echo '<div class="flex items-center gap-1">' . esc_html($label) . '<span class="state-sort-indicator text-[11px] leading-none opacity-80">↕</span></div>';
		echo '</th>';
	}
}

if (!function_exists('tr_state_summary_percent')) {
	function tr_state_summary_percent($positive, $negative)
	{
		$total = (int) $positive + (int) $negative;
		if ($total <= 0) {
			return '0%';
		}

		$percent = round(((int) $positive / $total) * 100, 1);
		$formatted = ((float) $percent === (float) (int) $percent)
			? (string) (int) $percent
			: number_format($percent, 1);

		return $formatted . '%';
	}
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] === 'loadData') {
	$summary_json_rows = array();
	foreach ($summary_rows as $summary_item) {
		$p_val = (int) ($summary_item['p'] ?? 0);
		$n_val = (int) ($summary_item['n'] ?? 0);
		$total_val = $p_val + $n_val;
		$sent_val = min((int) ($summary_item['sent'] ?? 0), $total_val);
		$summary_json_rows[] = array(
			'name' => (string) ($summary_item['name'] ?? ''),
			'panel' => (string) ($summary_item['panel'] ?? ''),
			'p' => $p_val,
			'n' => $n_val,
			'percent' => tr_state_summary_percent($p_val, $n_val),
			'sent' => $sent_val,
			'total' => $total_val,
		);
	}

	$data_json_rows = array();
	foreach ($results as $row) {
		$data_json_rows[] = array(
			'assen' => (string) ($row->assen ?? ''),
			'first_name' => (string) tr_pdd_capitalized($row->pa_first ?? ''),
			'last_name' => (string) tr_pdd_capitalized($row->pa_last ?? ''),
			'dob' => (string) tr_pdd_display_date($row->pa_dob ?? '', 'Y-m-d'),
			'age' => (string) ($row->age ?? ''),
			'sex' => (string) ($row->pa_gender ?? ''),
			'address' => (string) ($row->pa_address ?? ''),
			'city' => (string) ($row->pa_city ?? ''),
			'zip' => (string) ($row->pa_zip ?? ''),
			'state' => (string) ($row->pa_state ?? ''),
			'phone' => (string) ($row->pa_phone ?? ''),
			'doctor' => (string) ($row->requesting_physician ?? ''),
			'doctor_title' => (string) ($row->requesting_practice ?? ''),
			'doc_date' => (string) tr_pdd_display_date($row->doc ?? '', 'Y-m-d'),
			'approved_date' => (string) tr_pdd_display_date($row->approve_date ?? '', 'Y-m-d'),
			'test' => implode(', ', $row->te_names ?? array()),
			'result' => implode(', ', $row->te_result ?? array()),
			'state_upload' => (string) ($row->state_upload ?? '-'),
		);
	}

	wp_send_json(array(
		'success' => true,
		'summary' => array(
			'rows' => $summary_json_rows,
			'total_positive' => (int) $summary_total_positive,
			'total_negative' => (int) $summary_total_negative,
			'total_percent' => tr_state_summary_percent($summary_total_positive, $summary_total_negative),
			'total_sent' => (int) min((int) $summary_total_sent, (int) ($summary_total_positive + $summary_total_negative)),
			'total_tests' => (int) ($summary_total_positive + $summary_total_negative),
		),
		'data_rows' => $data_json_rows,
		'total_results' => (int) $total_rs,
	));
}

?>
<style>
	.select2-container--default .select2-selection--multiple .select2-selection__choice {
		background-color: white;
		color: #6b7280;
		border-color: #0000001f;
	}

	.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
		color: #d83d00;
	}

	body .select2-container .select2-selection--single,
	body .select2-container--default .select2-selection--single .select2-selection__arrow {
		border: none;
	}

	body .select2-container--default .select2-selection--single .select2-selection__rendered {
		background-color: #f9f9f9;
		border-radius: 10px;
		color: #4a5565;
		padding-right: 30px
	}

	.select2-container--default .select2-results__option[role=group] {
		font-size: 12px;
	}

	.select2-dropdown {
		border: 1px solid #e5e7eb !important;
		border-radius: 10px !important;
		padding: 3px;
		box-shadow: 0px 1px 7px 0px #7878784a;
	}

	body .select2-container--default .select2-results__option--highlighted[aria-selected] {
		background: #ffece9;
		color: #d83d00;
		border-radius: 6px;
	}

	.select2-results__options::-webkit-scrollbar {
		width: 6px;
	}

	.select2-results__options::-webkit-scrollbar-thumb {
		background: #cbd5e1;
		border-radius: 8px;
	}

	.select2-results__options::-webkit-scrollbar-track {
		background: transparent;
	}

	.select2-results__option--highlighted:not(.select2-results__option--selected) {
		background-color: #ffece9 !important;
		color: #d83d00 !important;
	}

	.select2-container--default .select2-selection--single {
		background-color: transparent !important;
	}

	.select2-container--default .select2-selection--single .select2-selection__arrow b {
		background-repeat: no-repeat;
		background-size: 100% 100%;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
		height: 16px;
		left: 50%;
		margin-left: -14px;
		margin-top: -8px;
		position: absolute;
		top: 50%;
		width: 16px;
		border: none;
	}

	/* #sel_day style matches export button */
	.sel-day-select2 .select2-selection--single {
		height: 33px !important;
		border: 1px solid #d1d5db !important;
		border-radius: 6px !important;
		background: #ffffff !important;
		box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
		position: relative;
	}

	.sel-day-select2 .select2-selection__rendered {
		line-height: 30px !important;
		font-size: 14px !important;
		color: #111827 !important;
		padding-left: 34px !important;
		padding-right: 28px !important;
		background: transparent !important;
		border-radius: 6px !important;
	}

	.sel-day-select2 .select2-selection--single::before {
		content: '';
		position: absolute;
		left: 11px;
		top: 50%;
		width: 14px;
		height: 14px;
		transform: translateY(-50%);
		opacity: 0.75;
		pointer-events: none;
		background-repeat: no-repeat;
		background-size: 14px 14px;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 2v4'/%3E%3Cpath d='M16 2v4'/%3E%3Crect width='18' height='18' x='3' y='4' rx='2'/%3E%3Cpath d='M3 10h18'/%3E%3C/svg%3E");
	}

	.sel-day-select2 .select2-selection__arrow {
		height: 30px !important;
		right: 8px !important;
		width: 18px !important;
	}

	.sel-day-select2 .select2-selection__arrow b {
		border: none !important;
		width: 14px !important;
		height: 14px !important;
		margin: -7px 0 0 -7px !important;
		left: 50% !important;
		top: 50% !important;
		opacity: 0.7;
		background-repeat: no-repeat !important;
		background-size: 14px 14px !important;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
	}

	.sel-day-select2.select2-container--focus .select2-selection--single {
		border-color: #9ca3af !important;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
	}

	.select2-container--default .select2-results__option[aria-selected=true] {
		border-radius: 6px;
	}

	.select2-container--default .select2-search--dropdown .select2-search__field {
		padding-top: 2px;
		padding-bottom: 2px;
	}

	.border-gray-300 {
		border-color: #d1d5dc;
	}

	.border-t-2 {
		border-top-style: solid;
		border-top-width: 2px;
	}
</style>
<div class="wrap flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
	<div class="space-y-6">
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">State Reporting</h1>
				<p class="text-muted-foreground mt-1">Generate reporting data for state health agencies using customizable filters. Reportable conditions are configured separately.</p>
			</div>
			<div class="flex items-center gap-2 sm:gap-3 flex-wrap">
				<button onclick="window.location='<?php echo esc_js($configure_report_url); ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
					Configure Reportable Conditions
				</button>
			</div>
		</div>

		<?php Tr_Session_Class::show(); ?>
		<div>
			<div class="flex flex-col gap-4">
				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border shadow-card">
					<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1 px-6 pt-4 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-4 pb-1">
						<div class="flex items-center justify-between">
							<div class="flex items-center gap-1.5">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4 text-primary">
									<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
								</svg>
								<h4 data-slot="card-title" class="text-base" style="margin: 0; line-height: 1.25;font-weight: 400;">Filters</h4>
							</div>

							<div class="flex items-center gap-3">
								<div style="position:relative;display:inline-block;">
									<button id="export_report" ; data-slot="dropdown-menu-trigger"
										class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
										type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
										data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
											viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
											<path d="M12 15V3"></path>
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
											<path d="m7 10 5 5 5-5"></path>
										</svg>Export Report<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
											viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
											<path d="m6 9 6 6 6-6"></path>
										</svg>
									</button>
									<div id="menu_export_report" class="menu-export" data-radix-popper-content-wrapper=""
										dir="ltr"
										style="display: none;position: absolute;left: 14px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
										<div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
											data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
											aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
											class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
											tabindex="-1" data-orientation="vertical"
											style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
											<div export_func="export_report" export_type="csv" role="menuitem"
												data-slot="dropdown-menu-item" data-variant="default"
												class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
												tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-spreadsheet h-4 w-4 mr-2">
													<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
													<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
													<path d="M8 13h2"></path>
													<path d="M14 13h2"></path>
													<path d="M8 17h2"></path>
													<path d="M14 17h2"></path>
												</svg>
												Download CSV
											</div>
										</div>
									</div>
								</div>

								<?php $schedule_report->btn_schedule(); ?>
							</div>
						</div>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-5 pt-0 pb-3">
						<form method="get" id="formsearch" class="flex flex-wrap items-center gap-3">
							<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']) ?>" />
							<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? esc_attr($_GET['tab']) : '' ?>" />
							<input type="hidden" name="from" value="<?php echo esc_attr($from_date) ?>" />
							<input type="hidden" name="to" value="<?php echo esc_attr($to_date) ?>" />
							<div class="flex items-center gap-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar h-4 w-4 text-gray-500 flex-shrink-0">
									<path d="M8 2v4"></path>
									<path d="M16 2v4"></path>
									<rect width="18" height="18" x="3" y="4" rx="2"></rect>
									<path d="M3 10h18"></path>
								</svg>
								<button id="btn_day_customer_state_report" data-slot="popover-trigger" class="cursor-pointer inline-flex items-center whitespace-nowrap transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-foreground hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-9 px-3 text-sm justify-start text-left font-normal w-[160px] border-0 bg-gray-50 hover:bg-gray-100" type="button"><span class="txt_show_customer_day"><?php echo esc_html(date('M d', strtotime($from_date)) . ' - ' . date('M d', strtotime($to_date))) ?></span></button>
							</div>

							<div class="flex items-center gap-2 w-[140px]">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-4 w-4 text-gray-500 flex-shrink-0">
									<path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
									<path d="M6.453 15h11.094"></path>
									<path d="M8.5 2h7"></path>
								</svg>

								<select name="rtype" class="select2 njs_select2" style="width: 140px;">
									<?php foreach ($btypes as $bt) {
										$selected = $bt == $rtype ? 'selected' : '';
										$bt_label = strtoupper($bt) === 'PCR' ? 'PCR' : $bt;
										echo '<option value="' . esc_attr($bt) . '" ' . $selected . '>' . esc_html($bt_label) . '</option>';
									} ?>
								</select>
							</div>

							<div class="flex items-center gap-2 w-[180px]">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-4 w-4 text-gray-500 flex-shrink-0">
									<circle cx="12" cy="12" r="10"></circle>
									<polyline points="12 6 12 12 16 14"></polyline>
								</svg>
								<select name="date_mode" class="select2 njs_select2" style="width: 180px;">
									<option value="approved" <?php selected($date_mode, 'approved') ?>>Approved Date</option>
									<option value="doc" <?php selected($date_mode, 'doc') ?>>DOC</option>
								</select>
							</div>

							<div class="flex items-center gap-2 w-[160px]">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin h-4 w-4 text-gray-500 flex-shrink-0">
									<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
									<circle cx="12" cy="10" r="3"></circle>
								</svg>
								<select name="state" class="select2 njs_select2" style="width: 160px;">
									<option value="ALL">All States</option>
									<?php foreach ($states_filter_options as $state_code => $state_name) {
										$selected = strtoupper($state_code) == strtoupper($state_filter) ? 'selected' : '';
										echo '<option value="' . esc_attr($state_code) . '" ' . $selected . '>' . esc_html($state_code . ' - ' . $state_name) . '</option>';
									} ?>
								</select>
							</div>

							<?php if (isset($clinics)) { ?>
								<div class="flex items-center gap-2 w-[160px]">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-4 w-4 text-gray-500 flex-shrink-0">
										<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
										<path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
										<path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
										<path d="M10 6h4"></path>
										<path d="M10 10h4"></path>
										<path d="M10 14h4"></path>
										<path d="M10 18h4"></path>
									</svg>
									<select name="cli_ID" class="select2 njs_select2" style="width: 180px;">
										<option value="all">All Clinics</option>
										<?php foreach ($clinics as $cli) {
											$selected = $cli->cID == $cli_ID ? 'selected' : '';
											echo '<option value="' . esc_attr($cli->cID) . '" ' . $selected . '>' . esc_html($cli->cli_clinic) . '</option>';
										} ?>
									</select>
								</div>
							<?php } ?>


							<label class="inline-flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="summary" value="1" <?php checked(isset($_GET['summary']) ? $_GET['summary'] : '') ?> />Summary</label>

						</form>
					</div>
				</div>

				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border shadow-card relative hidden" id="state-summary-card">
					<div class="report-box-loading" id="state-summary-loading">
						<div class="flex items-center gap-2">
							<span class="loader-dot"></span><span class="loader-dot"></span><span class="loader-dot"></span>
						</div>
					</div>
					<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1 px-6 pt-4 pb-0">
						<h4 data-slot="card-title" class="text-base" style="margin: 0; line-height: 1.25;font-weight: 400;margin-top: 8px;">Reportable Tests Summary</h4>
					</div>
					<div data-slot="card-content" class="[&amp;:last-child]:pb-5 p-3 pt-2">
						<div class="overflow-x-auto">
							<table class="w-full text-xs">
								<thead id="state-summary-head" <?php if (empty($summary_rows)) { ?>style="display:none" <?php } ?>>
									<tr class="bg-gray-100 border-b border-gray-200">
										<th class="px-3 py-2 text-left font-medium text-gray-700">Test</th>
										<th class="px-3 py-2 text-left font-medium text-gray-700">Panel</th>
										<th class="px-3 py-2 text-right font-medium text-gray-700">Positive</th>
										<th class="px-3 py-2 text-right font-medium text-gray-700">Negative</th>
										<th class="px-3 py-2 text-right font-medium text-gray-700">% Positive</th>
										<th class="px-3 py-2 text-left font-medium text-gray-700">State Upload</th>
									</tr>
								</thead>
								<tbody id="state-summary-body"><?php echo tr_state_report_render_summary_body($summary_rows, $summary_total_positive, $summary_total_negative, $summary_total_sent); ?></tbody>
							</table>
						</div>
					</div>
				</div>

				<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border shadow-card relative" id="state-report-card">
					<div class="report-box-loading" id="state-report-loading">
						<div class="flex items-center gap-2">
							<span class="loader-dot"></span><span class="loader-dot"></span><span class="loader-dot"></span>
						</div>
					</div>
					<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1 px-6 pt-4 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-4 pb-1">
						<h4 data-slot="card-title" class="text-base" style="margin: 0; line-height: 1.25;font-weight: 400;margin-top: 8px;">Reportable Tests</h4>
					</div>
					<div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0 pt-0">
						<div class="overflow-x-auto">
							<table class="w-full text-xs state-report-table">
								<thead>
									<tr class="text-white border-b border-gray-200" style="background-color: rgb(216, 61, 0);">
										<?php tr_state_sort_header_cell('assen', 'Accession'); ?>
										<th class="px-2 py-3 text-left font-semibold">First Name</th>
										<th class="px-2 py-3 text-left font-semibold">Last Name</th>
										<th class="px-2 py-3 text-left font-semibold">DOB</th>
										<?php tr_state_sort_header_cell('age', 'Age'); ?>
										<?php tr_state_sort_header_cell('pa_gender', 'Sex'); ?>
										<th class="px-2 py-3 text-left font-semibold">Address</th>
										<th class="px-2 py-3 text-left font-semibold">City</th>
										<th class="px-2 py-3 text-left font-semibold">Zip</th>
										<?php tr_state_sort_header_cell('pa_state', 'State'); ?>
										<th class="px-2 py-3 text-left font-semibold">Phone</th>
										<th class="px-2 py-3 text-left font-semibold">Doctor</th>
										<?php tr_state_sort_header_cell('doc', 'DOC'); ?>
										<?php tr_state_sort_header_cell('approve_date', 'Approved Date'); ?>
										<?php tr_state_sort_header_cell('result', 'Test'); ?>
										<th class="px-2 py-3 text-left font-semibold">Result</th>
										<th class="px-2 py-3 text-left font-semibold">State Upload</th>
									</tr>
								</thead>
								<tbody id="state-report-body"><?php echo tr_state_report_render_data_body($results); ?></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
	.state-report-table tbody tr:hover {
		background-color: #f8fafc;
	}

	.report-box-loading {
		position: absolute;
		inset: 0;
		background: rgba(255, 255, 255, 0.75);
		display: none;
		align-items: center;
		justify-content: center;
		z-index: 5;
	}

	.report-box-loading .loader-dot {
		width: 10px;
		height: 10px;
		border-radius: 999px;
		background: #d83d00;
		animation: reportLoadingPulse 0.9s infinite ease-in-out;
	}

	.report-box-loading .loader-dot:nth-child(2) {
		animation-delay: 0.15s;
	}

	.report-box-loading .loader-dot:nth-child(3) {
		animation-delay: 0.3s;
	}

	@keyframes reportLoadingPulse {

		0%,
		80%,
		100% {
			opacity: 0.35;
			transform: translateY(0);
		}

		40% {
			opacity: 1;
			transform: translateY(-3px);
		}
	}
</style>
<?php $schedule_report->style(); ?>

<script>
	var url_main_page = 'ci_reports_state_report';
	jQuery(function($) {
		const todayDate = moment('<?php echo esc_js($today_date) ?>', 'YYYY-MM-DD');
		let autoSubmitTimer = null;
		let isLoadingData = false;
		let activeLoadRequest = null;
		let loadRequestId = 0;
		let currentSortKey = '';
		let currentSortDir = 'asc';

		function clearTransientActions() {
			$('#formsearch input[name="trajax"]').remove();
			$('#formsearch input[name="act"]').remove();
		}

		function formatDateRangeLabel(from, to) {
			return moment(from, 'YYYY-MM-DD').format('MMM DD') + ' - ' + moment(to, 'YYYY-MM-DD').format('MMM DD');
		}

		function updateDateLabel(from, to) {
			$('#btn_day_customer_state_report .txt_show_customer_day').text(formatDateRangeLabel(from, to));
		}

		function updateUrlParams() {
			const params = new URLSearchParams(window.location.search);
			$('#formsearch').serializeArray().forEach(function(item) {
				if (item.value === '') {
					params.delete(item.name);
				} else {
					params.set(item.name, item.value);
				}
			});
			params.delete('trajax');
			params.delete('act');
			params.delete('orderby');
			params.delete('order');
			window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
		}

		function toggleReportBoxesLoading(loading) {
			if (loading) {
				$('#state-summary-loading').css('display', 'flex');
				$('#state-report-loading').css('display', 'flex');
			} else {
				$('#state-summary-loading').hide();
				$('#state-report-loading').hide();
			}
		}

		function updateSortIndicators() {
			$('.js-state-sort .state-sort-indicator').text('↕');
			if (!currentSortKey) {
				return;
			}
			const $active = $('.js-state-sort[data-sort-key="' + currentSortKey + '"] .state-sort-indicator');
			$active.text(currentSortDir === 'asc' ? '↑' : '↓');
		}

		function escapeHtml(value) {
			return $('<div/>').text(value == null ? '' : String(value)).html();
		}

		function renderSummaryTable(summary) {
			const rows = Array.isArray(summary && summary.rows) ? summary.rows : [];
			if (!rows.length) {
				$('#state-summary-head').hide();
				$('#state-summary-body').html('<tr class="bg-white"><td colspan="6" class="px-3 py-4 text-center text-gray-500">No summary data available for the selected filters.</td></tr>');
				return;
			}
			$('#state-summary-head').show();
			let html = '';
			const renderUploadBadge = function(sent, total) {
				const totalNum = Math.max(0, parseInt(total || 0, 10) || 0);
				const sentNumRaw = Math.max(0, parseInt(sent || 0, 10) || 0);
				const sentNum = Math.min(sentNumRaw, totalNum);
				const allSent = totalNum > 0 && sentNum >= totalNum;
				const badgeClass = allSent ?
					'bg-green-50 border-green-200 text-green-700' :
					'bg-red-50 border-red-200 text-red-700';
				return '<span class="inline-flex items-center justify-center min-w-[64px] px-3 py-1 rounded-md border text-xs font-medium ' + badgeClass + '">' + sentNum + ' (' + totalNum + ')</span>';
			};
			rows.forEach(function(row, index) {
				const rowClass = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
				const rowTotal = parseInt(row.total || ((parseInt(row.p || 0, 10) || 0) + (parseInt(row.n || 0, 10) || 0)), 10) || 0;
				const rowSent = parseInt(row.sent || 0, 10) || 0;
				html += '<tr class="' + rowClass + '">';
				html += '<td class="px-3 py-1.5 text-gray-900">' + escapeHtml(row.name) + '</td>';
				html += '<td class="px-3 py-1.5 text-gray-700">' + escapeHtml(row.panel) + '</td>';
				html += '<td class="px-3 py-1.5 text-right text-gray-900">' + escapeHtml(row.p) + '</td>';
				html += '<td class="px-3 py-1.5 text-right text-gray-900">' + escapeHtml(row.n) + '</td>';
				html += '<td class="px-3 py-1.5 text-right text-gray-900">' + escapeHtml(row.percent) + '</td>';
				html += '<td class="px-3 py-1.5 text-left">' + renderUploadBadge(rowSent, rowTotal) + '</td>';
				html += '</tr>';
			});
			html += '<tr class="bg-gray-200 border-t-2 border-gray-300">';
			html += '<td class="px-3 py-2 font-medium text-gray-900">Total</td>';
			html += '<td class="px-3 py-2 text-gray-900"></td>';
			html += '<td class="px-3 py-2 text-right font-medium text-gray-900">' + escapeHtml(summary.total_positive || 0) + '</td>';
			html += '<td class="px-3 py-2 text-right font-medium text-gray-900">' + escapeHtml(summary.total_negative || 0) + '</td>';
			html += '<td class="px-3 py-2 text-right font-medium text-gray-900">' + escapeHtml(summary.total_percent || '0%') + '</td>';
			html += '<td class="px-3 py-2 text-left">' + renderUploadBadge(summary.total_sent || 0, summary.total_tests || 0) + '</td>';
			html += '</tr>';
			$('#state-summary-body').html(html);
		}

		function renderDataTable(rows) {
			if (!Array.isArray(rows) || !rows.length) {
				$('#state-report-body').html('<tr class="bg-white"><td colspan="17" class="px-2 py-3 text-gray-500">No Results Found.</td></tr>');
				return;
			}
			let html = '';
			rows.forEach(function(row, index) {
				const rowClass = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
				html += '<tr class="' + rowClass + '">';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.assen) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.first_name) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.last_name) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.dob) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.age) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.sex) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.address) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.city) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.zip) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900">' + escapeHtml(row.state) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.phone) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700" title="' + escapeHtml(row.doctor_title) + '">' + escapeHtml(row.doctor) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.doc_date) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.approved_date) + '</td>';
				html += '<td class="px-2 py-2 text-gray-700">' + escapeHtml(row.test) + '</td>';
				html += '<td class="px-2 py-2 text-gray-900 font-medium">' + escapeHtml(row.result) + '</td>';
				const state_upload = row.state_upload === '-' ?
					`<button data-date="${row.approved_date}" data-assen="${row.assen}"
						class="force_send cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all outline-none border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-6 rounded-md gap-1.5 px-3"
					>Force Send</button>` : escapeHtml(row.state_upload);

				html += '<td class="px-2 py-2 text-gray-700">' + state_upload + '</td>';
				html += '</tr>';
			});
			$('#state-report-body').html(html);
		}

		function normalizeLoadResponse(response) {
			let payload = response;

			if (typeof payload === 'string') {
				const trimmed = payload.trim();
				if (!trimmed) {
					return null;
				}
				try {
					payload = JSON.parse(trimmed);
				} catch (e) {
					const firstBrace = trimmed.indexOf('{');
					const lastBrace = trimmed.lastIndexOf('}');
					if (firstBrace >= 0 && lastBrace > firstBrace) {
						try {
							payload = JSON.parse(trimmed.substring(firstBrace, lastBrace + 1));
						} catch (e2) {
							return null;
						}
					} else {
						return null;
					}
				}
			}

			if (payload && typeof payload === 'object' && payload.reports && typeof payload.reports === 'object') {
				payload = payload.reports;
			}

			if (!payload || typeof payload !== 'object') {
				return null;
			}

			return payload;
		}

		function parseSortValue(text, type) {
			const raw = String(text || '').trim();
			if (type === 'number') {
				const numberText = raw.replace(/[^0-9.\-]/g, '');
				const num = parseFloat(numberText);
				return isNaN(num) ? Number.NEGATIVE_INFINITY : num;
			}
			if (type === 'date') {
				const m = moment(raw, 'YYYY-MM-DD', true);
				if (m.isValid()) {
					return m.valueOf();
				}
				const ts = Date.parse(raw);
				return isNaN(ts) ? 0 : ts;
			}
			return raw.toLowerCase();
		}

		function applyZebraRows() {
			$('#state-report-body tr').removeClass('bg-white bg-gray-50');
			$('#state-report-body tr').each(function(index) {
				$(this).addClass(index % 2 === 0 ? 'bg-white' : 'bg-gray-50');
			});
		}

		function sortStateReportTable(key, dir) {
			const $header = $('.js-state-sort[data-sort-key="' + key + '"]');
			if ($header.length === 0) {
				return;
			}
			const colIndex = parseInt($header.data('sort-col'), 10);
			const sortType = String($header.data('sort-type') || 'text');
			const $rows = $('#state-report-body tr').filter(function() {
				return $(this).find('td').length > colIndex;
			});
			if ($rows.length <= 1) {
				updateSortIndicators();
				return;
			}
			const rows = $rows.get();
			rows.sort(function(a, b) {
				const aText = $(a).children('td').eq(colIndex).text();
				const bText = $(b).children('td').eq(colIndex).text();
				const va = parseSortValue(aText, sortType);
				const vb = parseSortValue(bText, sortType);
				if (va < vb) {
					return dir === 'asc' ? -1 : 1;
				}
				if (va > vb) {
					return dir === 'asc' ? 1 : -1;
				}
				return 0;
			});
			$('#state-report-body').append(rows);
			applyZebraRows();
			updateSortIndicators();
		}

		function runLoadData(delayMs) {
			clearTimeout(autoSubmitTimer);
			autoSubmitTimer = setTimeout(function() {
				clearTransientActions();
				if (activeLoadRequest && typeof activeLoadRequest.abort === 'function') {
					activeLoadRequest.abort();
				}
				loadRequestId += 1;
				const requestId = loadRequestId;
				isLoadingData = true;
				toggleReportBoxesLoading(true);
				const requestData = $('#formsearch').serializeArray();
				requestData.push({
					name: 'trajax',
					value: 'loadData'
				});
				activeLoadRequest = $.get('admin.php', requestData)
					.done(function(response) {
						if (requestId !== loadRequestId) {
							return;
						}
						const normalizedResponse = normalizeLoadResponse(response);
						if (!normalizedResponse || !normalizedResponse.success) {
							console.log('State report response is invalid', response);
							return;
						}
						renderSummaryTable(normalizedResponse.summary || {});
						renderDataTable(normalizedResponse.data_rows || []);
						if (currentSortKey) {
							sortStateReportTable(currentSortKey, currentSortDir);
						} else {
							applyZebraRows();
						}
						updateUrlParams();
					})
					.fail(function(xhr, status) {
						if (status === 'abort') {
							return;
						}
						console.log('State report loadData failed');
					})
					.always(function() {
						if (requestId !== loadRequestId) {
							return;
						}
						activeLoadRequest = null;
						isLoadingData = false;
						toggleReportBoxesLoading(false);
					});
			}, delayMs || 0);
		}

		$('#btn_day_customer_state_report').daterangepicker({
			startDate: moment($('#formsearch [name="from"]').val(), 'YYYY-MM-DD'),
			endDate: moment($('#formsearch [name="to"]').val(), 'YYYY-MM-DD'),
			autoUpdateInput: false,
			opens: 'right',
			maxDate: todayDate,
			locale: {
				cancelLabel: 'Cancel',
				applyLabel: 'Apply',
				format: 'YYYY-MM-DD'
			}
		}, function(start, end) {
			if (start.isAfter(todayDate, 'day')) {
				start = todayDate.clone();
			}
			if (end.isAfter(todayDate, 'day')) {
				end = todayDate.clone();
			}
			$('#formsearch [name="from"]').val(start.format('YYYY-MM-DD'));
			$('#formsearch [name="to"]').val(end.format('YYYY-MM-DD'));
			updateDateLabel(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
			runLoadData();
		});

		$('#btn_day_customer_state_report').on('show.daterangepicker', function(ev, picker) {
			setTimeout(function() {
				picker.container.off('mousedown.statePreview').on('mousedown.statePreview', 'td.available', function() {
					const dataTitle = $(this).data('title');
					const match = String(dataTitle || '').match(/r(\d+)c(\d+)/);
					if (!match) {
						return;
					}
					const row = parseInt(match[1], 10);
					const col = parseInt(match[2], 10);
					let previewDate = null;
					if (picker.leftCalendar && picker.leftCalendar.calendar && picker.leftCalendar.calendar[row] && picker.leftCalendar.calendar[row][col]) {
						previewDate = picker.leftCalendar.calendar[row][col];
					} else if (picker.rightCalendar && picker.rightCalendar.calendar && picker.rightCalendar.calendar[row] && picker.rightCalendar.calendar[row][col]) {
						previewDate = picker.rightCalendar.calendar[row][col];
					}
					if (!previewDate || !previewDate.isValid() || previewDate.isAfter(todayDate, 'day')) {
						return;
					}
					const previewValue = previewDate.format('YYYY-MM-DD');
					$('#formsearch [name="from"]').val(previewValue);
					$('#formsearch [name="to"]').val(previewValue);
					updateDateLabel(previewValue, previewValue);
				});
			}, 0);
		});

		$(".btn-export").on("click", function(e) {
			e.stopPropagation();
			var id_btn = $(this).attr("id");
			$("#menu_" + id_btn).toggle();
		});

		$(document).on("click", function(e) {
			if (!$(e.target).closest(".custom-select").length) {
				$(".custom-select .options").hide();
			}

			if (!$(e.target).closest(".menu-export").length) {
				$(".menu-export").hide();
			}
		});

		$(".export_item").on('click', function() {
			$('#menu_export_report').hide();
			clearTransientActions();
			$('#formsearch').append('<input type="hidden" name="trajax" value="1"/>');
			$('#formsearch').append('<input type="hidden" name="act" value="export"/>');
			$('#formsearch').submit();
		});

		$('input[type="submit"]').click(function() {
			clearTransientActions();
		});

		const $rtypeSelect = $('#formsearch select[name="rtype"]');
		const $dateModeSelect = $('#formsearch select[name="date_mode"]');
		$('.select2').not($rtypeSelect).not($dateModeSelect).select2();
		$rtypeSelect.add($dateModeSelect).select2({
			minimumResultsForSearch: Infinity
		});
		$('#formsearch select[name="rtype"], #formsearch select[name="date_mode"], #formsearch select[name="state"], #formsearch select[name="cli_ID"]').on('change', function() {
			runLoadData();
		});

		$('#formsearch').on('submit', function(e) {
			if ($('#formsearch input[name="act"][value="export"]').length) {
				return true;
			}
			e.preventDefault();
			runLoadData(0);
			return false;
		});

		$(document).on('click', '.js-state-sort', function() {
			if (isLoadingData) {
				return;
			}
			const clickedKey = String($(this).data('sort-key') || '');
			if (!clickedKey) {
				return;
			}
			if (currentSortKey === clickedKey) {
				currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
			} else {
				currentSortKey = clickedKey;
				currentSortDir = 'asc';
			}
			sortStateReportTable(currentSortKey, currentSortDir);
		}).on('click', '.force_send', function() {
			const btn = $(this);
			if (!btn.data('assen')) {
				alert('Missing assen!');
				return;
			}
			btn.text('Sending...');
			$.get(`admin.php?page=ci_reports_state_report&trajax=force_send&date=${btn.data('date')}&assen=${btn.data('assen')}`, function(response) {
				try {
					var res = JSON.parse(response);
				} catch (err) {
					console.error('Parse error:', err);
				}
			}).always(function() {
				btn.text('Force send');
			});
		}).on('click', 'input[name="summary"]', function() {
			$('#state-summary-card').toggleClass('hidden', !this.checked);
		});


		const urlParams = new URLSearchParams(window.location.search);
		const urlSortKey = String(urlParams.get('orderby') || '');
		const urlSortDir = String(urlParams.get('order') || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
		if ($('.js-state-sort[data-sort-key="' + urlSortKey + '"]').length > 0) {
			currentSortKey = urlSortKey;
			currentSortDir = urlSortDir;
		}
		updateSortIndicators();
		runLoadData(0);
	});
</script>
<?php $schedule_report->js(); ?>