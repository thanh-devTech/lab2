<?php

if (strpos($_SERVER['REQUEST_URI'], '/restapi/') !== false) {
	include(TRCDBI_PATH . 'apis/ci_billing_api.php');
	$api = new CI_Data_restapi('restapi');
	exit;
}

add_action('wsdl_apis_info', function () {
	include(TRCDBI_PATH . 'parts/actions/wsdl_apis_info.php');
});
add_action('ci_approve_rs', function ($report = array(), $btype = 'pcr') {
	$wsdl_apis_info = get_option('tr_wsdl_apis_info_sts', array());
	if (isset($wsdl_apis_info['billing_webhook']) && !empty($wsdl_apis_info['billing_webhook'])) {
		wp_schedule_single_event(time() + 60, 'ci_cron_send_webhook', array($report['id'], $btype));
	}
}, 10, 2);

add_action('ci_cron_send_webhook', function ($id = 0, $btype = '') {
	include(TRCDBI_PATH . 'crons/send_webhook.php');
}, 10, 2);

add_action('ci_export_data_cleanup_file', function ($token = '', $user_id = 0) {
	$state = get_transient('ci_export_data_' . $token);
	if (is_array($state) && (int) $state['user_id'] === (int) $user_id) {
		if (!empty($state['file']) && file_exists($state['file'])) {
			@unlink($state['file']);
		}
		if (function_exists('trpdd_log_user')) {
			trpdd_log_user($user_id, 'data_export', array(
				'status' => 'canceled',
				'records_exported' => isset($state['processed']) ? (int) $state['processed'] : 0,
				'request_id' => isset($state['request_id']) ? $state['request_id'] : '',
				'reason' => 'Export file expired before download',
			));
		}
		delete_transient('ci_export_data_' . $token);
		delete_user_meta($user_id, '_ci_export_data_token', $token);
	}
}, 10, 2);

function tr_bill_get_next_link($report, $date)
{
	global $wpdb, $current_lab_id, $current_role;
	$has_id = false;
	$user_id = get_current_user_id();
	$old_id = $_REQUEST['old_id'] > 0 ? $_REQUEST['old_id'] : $report['mid'];
	$btype = !empty($report['btype']) ? $report['btype'] : 'tox';

	$end_date = date('Y-m-d', strtotime('+100days', strtotime($report['created_date'])));

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$where = " and re.estatus = 'C' ";
	$where .= " and bb.bill_status != 'Do Not Bill' ";
	$where .= " and bb.bill_status!='Billed' ";

	$orderby = ' order by re.created_date desc, ba.mid desc';
	$joins = '';
	if ($current_role == 'office_manager') {
		$clinics = $wpdb->get_col("select cID from ci_clinic where cli_om_user_ID={$user_id} ");
		$clinics = implode(',', $clinics);
		$where .= " and ba.cli_ID in ({$clinics}) ";
	} else if ($user_id > 0 && !in_array($current_role, array('administrator', 'scientist', 'labmanager', 'lab_tech'))) {
		$where .= " and re.author_id = {$user_id} ";
	}

	$where_join = " bb.assen = ba.assen ";
	if (tr_billing_get_settings('enable_claim_no')) {
		$where_join = " (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen) ";
	}

	$joins .= " JOIN {$batch_tbl} ba ON  ba.assen = re.patient_id and ba.assen !=''
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id  
                left join ci_batch_billings bb ON {$where_join} ";



	$id_next = 0;
	//find this patient next days
	$sql = $wpdb->prepare("select ba.mid,DATE_FORMAT(re.created_date, '%Y-%m-%d') as created_date
            from {$report_tbl} re {$joins}
            where re.created_date > '{$report['created_date']}' and re.created_date < '{$end_date}'
            and ba.patient_name = %s
            {$where}
            order by re.created_date asc limit 1", $report['patient_name']);
	$rs = $wpdb->get_row($sql);
	if ($rs) {
		$id_next = $rs->mid;
		$rs->type = 'same patient';
	}

	//check this patient other days
	if ($id_next == 0) {
		$sql = $wpdb->prepare("select ba.mid,DATE_FORMAT(re.created_date, '%Y-%m-%d') as created_date
            from {$report_tbl} re {$joins}
            where  ba.patient_name = %s and ba.mid != {$report['mid']}
            {$where}
            order by re.created_date asc limit 1", $report['patient_name']);
		$rs = $wpdb->get_row($sql);
		if ($rs) {
			$id_next = $rs->mid;
			$rs->type = 'same patient older';
		}

		//next patient in this date
		if ($id_next == 0) {
			//go to other patient
			$where .= " ";
			$sql = "select ba.mid from {$report_tbl} re {$joins}
                    where DATE_FORMAT(re.created_date, '%Y-%m-%d') = '{$date}'  {$where}
                    group by ba.mid {$orderby}";

			$ids = $wpdb->get_col($sql);
			$has_id = false;
			foreach ($ids as $idn) {
				if ($idn == $old_id) {
					$has_id = true;
				} else if ($has_id) {
					$id_next = $idn;
					break;
				}
			}
			$old_id = $id_next;
		}

		//get next day
		if ($id_next == 0) {
			$sql = "select ba.mid,DATE_FORMAT(re.created_date, '%Y-%m-%d') as created_date
                from {$report_tbl} re {$joins}
                where re.created_date > '{$report['created_date']}' and re.created_date < '{$end_date}' 
                {$where}
                order by re.created_date asc limit 1";


			$rs = $wpdb->get_row($sql);
			if ($rs) {
				$id_next = $rs->mid;
				$date = $rs->created_date;
				$rs->type = 'next day';
			}
			//get oldest
			if ($id_next == 0) {
				$sql = "select ba.mid,DATE_FORMAT(re.created_date, '%Y-%m-%d') as created_date
                from {$report_tbl} re {$joins}
                where 1=1 {$where}
                order by re.created_date asc limit 1";


				$rs = $wpdb->get_row($sql);
				if ($rs) {
					$id_next = $rs->mid;
					$date = $rs->created_date;
					$rs->type = 'oldest';
				}
			}
		} else if ($has_id) {
			if (!is_object($rs)) {
				$rs = new stdClass();
			}
			$rs->type = 'next on same day';
		}
	}

	$next = '';
	if ($id_next > 0) {
		$add = array(
			'id' => $id_next,
			'date' => $date,
			'old_id' => $old_id
		);

		$next = add_query_arg($add, 'admin.php?page=ci_billings&act=edit&');
	}

	return $next;
}

function tr_bill_detect_u0005($report_id, $bat)
{
	global $wpdb;
	$pass_u0005 = false;
	//check days
	if (!isset($bat['approve_date']) || empty($bat['approve_date'])) {
		$report_tbl = tr_pdd_get_table_report_by_btype($bat['btype']);
		$bat['approve_date'] = $wpdb->get_var("select approve_date from {$report_tbl} where id = {$report_id}");
	}
	if (!empty($bat['doc']) && !empty($bat['approve_date'])) {
		$doc_time = strtotime($bat['doc']);

		$approve_time = strtotime($bat['approve_date']);
		$secs = $approve_time - $doc_time;
		$hours = floor($secs / 3600);
		if ($hours > 0 && $hours < 48) {
			$pass_u0005 = true;
		}
	}
	return $pass_u0005;
}

function tr_bill_get_paytype_by_clinic($clinic_id)
{
	global $wpdb, $cache_paytype_by_clinic;
	$paytype = '';
	if ($clinic_id > 0) {
		if (isset($cache_paytype_by_clinic[$clinic_id])) {
			$paytype = $cache_paytype_by_clinic[$clinic_id];
		} else {
			$paytype = $wpdb->get_var("select cli_pay_type from ci_clinic where cID = {$clinic_id}");
			$cache_paytype_by_clinic[$clinic_id] = $paytype;
		}
	}
	return $paytype;
}

function tr_bill_get_billing_code($insurance, $report_id, $bat, $onlycode = false)
{
	global $wpdb, $ci_all_tests;
	global $ci_all_tests_group;
	global $swab_code_by_clinics;
	global $insurance_cache;
	global $ci_dax_codes;



	$clinic_paytype = '';
	$returnbytest = isset($bat['bytest']) && $bat['bytest'];
	if (strtolower($bat['insurance']) == 'client bill') {
		$insurance = 'client bill';
	}
	if (strtolower($bat['insurance']) == 'no insurance - cash') {
		$insurance = 'no insurance - cash';
	}

	$insurance      = trim($insurance);
	$insurance_key  = strtolower($insurance);
	$insurance_key  = str_replace(' ', '', $insurance_key);


	if (in_array($insurance_key, array('n/a', 'na', 'noinsurance-cash', 'clientbill'))) {
		$insurance = '';
	}

	$mid = isset($bat['mid']) ? $bat['mid'] : -1;
	if (empty($bat['btype'])) {
		$bat['btype'] = 'tox';
	}
	if (!is_array($ci_all_tests)) {
		$ci_all_tests = array();
	}
	if (!is_array($ci_all_tests_group)) {
		$ci_all_tests_group = array();
	}

	if (!is_array($insurance_cache)) {
		$insurance_cache = array();
	}



	if (!is_array($swab_code_by_clinics)) {
		$swab_code_by_clinics = array();
		$rs = $wpdb->get_results("select cID, cli_swab_code from ci_clinic where cli_swab_code!=''");
		foreach ($rs as $r) {
			$swab_code_by_clinics[$r->cID] = $r->cli_swab_code;
		}
	}
	$settings       = tr_billing_get_settings();

	if (!empty($settings['enable_pay_type'])) {
		$clinic_paytype = tr_bill_get_paytype_by_clinic($bat['cli_ID']);
	}

	$result_tbl     = tr_pdd_get_table_result_by_btype($bat['btype']);
	$all_codes      = array();
	$ci_dax_codes      = array();
	$testids_array  = array();
	$tests_array    = array();
	$has_billing_code = false;
	$collector_is_lab_employee = false;
	$collection_code_added = false;
	$batch_date     = $bat['doc'];
	$allowemptyinsurance = isset($settings['allow_empty_insurance']) ? $settings['allow_empty_insurance'] : false;

	if (!empty($bat['col_ID'])) {
		$collector_is_lab_employee = (bool) $wpdb->get_var($wpdb->prepare(
			"select col_employee from ci_collector where col_ID = %d",
			$bat['col_ID']
		));
	}

	if (empty($batch_date)) {
		$batch_date = $bat['approve_date'];
	}
	$batch_date = date('Y-m-d', strtotime($batch_date));

	if (
		isset($bat['cli_ID']) && $bat['cli_ID'] > 0 && isset($swab_code_by_clinics[$bat['cli_ID']])
		&& !empty($swab_code_by_clinics[$bat['cli_ID']])
	) {
		$all_codes[$swab_code_by_clinics[$bat['cli_ID']]] = $all_codes[$swab_code_by_clinics[$bat['cli_ID']]] + 1;
	}


	if (isset($settings['enable_cashpay']) && $settings['enable_cashpay']) {
		$allowemptyinsurance = false;
	} else {
		$settings['enable_cashpay'] = false;
	}

	if (!empty($insurance) || $allowemptyinsurance || $settings['enable_cashpay'] || $insurance_key == 'clientbill') {
		$dc_type = $bat['btype'];

		if ($bat['btype'] == 'tox') {
			$drug_pairs = tr_pdd_get_drug_pairs();

			$tests_string = isset($bat['panel_tests_requested_str']) ? $bat['panel_tests_requested_str'] : $bat['panel_tests_requested'];
			if (is_array($tests_string)) {
				$tests_string = implode(',', $tests_string);
			}
			$tests_array = tr_pdd_parse_panel_tests_requested($tests_string, $drug_pairs);
		} else {
			if (isset($ci_all_tests[$bat['btype']])) {
				$rstests = $ci_all_tests[$bat['btype']];
			} else {
				$rstests = $wpdb->get_results("select te_ID, te_name 
											from ci_test_panels 
											where te_type='{$bat['btype']}' 
											and te_in_req = 1 
											and te_is_control = 0	");
				$ci_all_tests[$bat['btype']] = $rstests;
			}

			$testids = is_array($bat['panel_tests_requested']) ? $bat['panel_tests_requested'] : explode(',', $bat['panel_tests_requested']);

			foreach ($rstests as $t) {
				if (in_array($t->te_ID, $testids)) {
					$tests_array[$t->te_ID] = strtoupper($t->te_name);
					$testids_array[$t->te_ID] = $t->te_ID;
				}
			}

			foreach ($testids as $t) {
				if (stripos($t, 'group_') !== false) {
					$groupid = str_ireplace('group_', '', $t);
					if (isset($ci_all_tests_group[$groupid])) {
						$tests_group = $ci_all_tests_group[$groupid];
					} else {
						$tests_group = $wpdb->get_results(
							"select te.te_name, te.te_ID 
							from ci_test_panels te
							join ci_test_groups tg ON tg.tg_ID = te.te_group_id
							left join ci_test_groups tgp ON tgp.tg_ID = tg.tg_parent
							where (
									tg.tg_ID = {$groupid} 
									and (tg.tg_is_covid = 1 
										or tg.tg_only_group=1 
										or tg.tg_notestcheckbox = 1
										or te.te_nocheckbox = 1)
								)
								or (
									tgp.tg_ID = {$groupid} 
										and (tgp.tg_is_covid = 1 
											or tgp.tg_only_group=1 
											or tgp.tg_notestcheckbox = 1
											or te.te_nocheckbox = 1)
								) "
						);
						$ci_all_tests_group[$groupid] = $tests_group;
					}


					if ($tests_group) {
						foreach ($tests_group as $tg) {
							$tests_array[$tg->te_ID] = strtoupper($tg->te_name);
							$testids_array[$tg->te_ID] = $tg->te_ID;
						}
					}
				}
			}
		}


		$tests_array            = array_map('trim', $tests_array);
		$tests_str              = implode("','", $tests_array);
		$tests_str              = str_replace('(', "\(", $tests_str);
		$tests_str              = str_replace(')', "\)", $tests_str);

		if (empty($tests_str)) {
			$tests_str = 'empty_test';
		}

		if (isset($_GET['debug'])) {
			echo '<br>Tests String: ' . $tests_str . '<br><br>';
		}

		$keys = array('commercial');
		if (!empty($settings['enable_medicare']) && (!empty($insurance) || $allowemptyinsurance)) {
			$keys[] = 'medicaid';
			$keys[] = 'medicare';
		}
		if (!empty($settings['enable_commercial_kit']) && !empty($insurance)) {
			$keys[] = 'commercial_kit';
		}

		$base_keys = $keys;

		if (isset($settings['enable_cashpay']) && $settings['enable_cashpay']) {
			if (empty($insurance)) {
				$keys = array();
			}
			$keys[] = 'cashpay';
		} else {
			$settings['enable_cashpay'] = false;
		}

		if (isset($settings['enable_client_bill']) && $settings['enable_client_bill'] && $insurance_key == 'clientbill') {
			$keys = array();
			$keys[] = 'client_bill';
		}

		if (isset($_GET['debugpaytype'])) {
			echo '<br><br>Clinic Paytype: ' . $clinic_paytype . '<br><br>';
		}

		if (!empty($clinic_paytype)) {
			if ($clinic_paytype == 'cash') {
				$keys = array('cashpay');
				$insurance = '';
			} else if ($clinic_paytype == 'clientb') {
				$keys = array('client_bill');
				$insurance_key = 'clientbill';
				$insurance = '';
			}
		}


		$showed_tests = array();

		if (!empty($insurance) && !isset($insurance_cache[$insurance_key])) {

			$insurance_cache[$insurance_key] = $wpdb->get_row(
				$wpdb->prepare("select * from ci_payer_list 
								where payer_name = %s ", $insurance),
				ARRAY_A
			);

			if ($insurance_cache[$insurance_key] == null) {
				$insurance_cache[$insurance_key] = array();
			}

			$enable_types = array();
			if ($insurance_cache[$insurance_key]) {
				$show_classes = true;
				foreach ($keys as $key) {
					if ($insurance_cache[$insurance_key]['payer_' . $key]) {
						$enable_types[] = $key;
					}
				}
			}
			if (count($enable_types) > 0) {
				foreach ($keys as $ik => $key) {
					if (!in_array($key, $enable_types)) {
						unset($keys[$ik]);
					}
				}
			}
		}

		$allkeys = array_merge($keys, $base_keys);
		$allkeys = array_unique($allkeys);
		foreach ($allkeys as $key) {

			$show_classes = false;
			$show_classes_by = '';
			if ($settings['enable_cashpay'] && empty($insurance)) {
				$show_classes = true;
				$show_classes_by = 'cashpay enabled & empty insurance';
			}

			if (!empty($insurance) || $allowemptyinsurance || $settings['enable_cashpay']) {


				if (
					isset($insurance_cache[$insurance_key]) && $insurance_cache[$insurance_key]
					&& isset($insurance_cache[$insurance_key]['payer_' . $key]) && $insurance_cache[$insurance_key]['payer_' . $key]
				) {
					$show_classes = true;
					$show_classes_by = $insurance_key;
				}

				if ($show_classes && $key == 'commercial' && empty($settings['enable_medicare'])) {
					if (!$insurance_cache[$insurance_key]['payer_medicaid'] && !$insurance_cache[$insurance_key]['payer_commercial']) {
						$show_classes = false;
					}
				}
			}

			if ($key == 'commercial' && empty($insurance) && $allowemptyinsurance) {
				$show_classes = true;
				$show_classes_by = 'commercial & allowemptyinsurance';
			}
			if ($key == 'client_bill' && $settings['enable_client_bill'] && empty($insurance)) {
				$show_classes = true;
				$show_classes_by = 'client bill enabled & empty insurance';
			}

			if (!$show_classes) {
				$inum = array_search($key, $keys);
				if ($inum !== false) {
					unset($keys[$inum]);
				}

				$inum = array_search($key, $base_keys);
				if ($inum !== false) {
					unset($base_keys[$inum]);
				}
			}
		}

		if (empty($keys)) {
			$keys = $base_keys;
			if (empty($keys)) {
				$keys[] = 'commercial';
				$keys[] = 'medicare';
			}

			if (isset($_GET['debugpaytype'])) {
				echo '<br><br>No insurance matched, using default keys: ' . implode(', ', $keys) . '<br><br>';
			}
		}

		foreach ($keys as $key) {
			$maintbl = 'ci_bill_drug_classes';

			if ($key == 'medicaid') $maintbl = 'ci_bill_bc_medicaid';
			else if ($key == 'medicare') $maintbl = 'ci_bill_bc_medicare';
			else if ($key == 'cashpay') $maintbl = 'ci_bill_bc_cashpay';
			else if ($key == 'client_bill') $maintbl = 'ci_bill_bc_client_bill';
			else if ($key == 'commercial_kit') $maintbl = 'ci_bill_bc_commercial_kit';

			$testtbl = $maintbl . '_test';

			$show_classes = true;


			if ($show_classes) {

				if (!isset($bat['result_pdf'])) {
					$batch_tbl = tr_pdd_get_table_by_btype($bat['btype']);
					$bat['result_pdf'] = $wpdb->get_var("select result_pdf from {$batch_tbl} where assen = '{$bat['assen']}' ");
				}
				$has_resultpdf = !empty($bat['result_pdf']) ? true : false;
				$where_has_dc = '';

				if (isset($_GET['debug'])) {
					echo '<br><br><h2>' . $bat['assen'] . '</h2>';
					echo '<br>maintbl: ' . $maintbl . ' => ' . $insurance . ' => ' . $show_classes_by . '<br><br>';
				}

				$wheredc = '';
				if (isset($_GET['debug']) && $key == 'client_bill') {
					echo '<br>Detecting DC for client bill with ref_lab_ID: ' . $bat['ref_lab_ID'] . '<br>';
				}
				if ($key == 'client_bill' && $bat['ref_lab_ID'] > 0) {

					$dcids = $wpdb->get_col("select dc_ID 
							from ci_bill_bc_client_bill
							 where dc_labref_info like '%\"{$bat['ref_lab_ID']}\":%' ");
					if (count($dcids) > 0) {
						$dcids_str = implode(',', $dcids);
						$wheredc = " and dc.dc_ID in ({$dcids_str}) ";
					}
				}

				if ($bat['btype'] == 'rpp_gi') {
					$test_rs = array();
					$report = $wpdb->get_row("select * from ci_reports where id = {$report_id} ", ARRAY_A);
					$info = json_decode($report['info'], true);
					$groups = $info['results'];
					foreach ($groups as $group) {
						foreach ($group['result'] as $result) {
							$test_rs[] = $result['resultTestName'];
						}
					}

					$test_rs = implode("','", $test_rs);

					$sql = "select dc.*,sum(if((ct.ct_test in ('{$test_rs}') and ct.ct_test in ('{$tests_str}')),1,0)) as has_dc
                            from {$maintbl} dc
                            JOIN {$testtbl} ct ON ct.ct_dc_ID = dc.dc_ID
                             where  dc.dc_type='{$dc_type}'
                             and (dc.dc_start_date is null or dc.dc_start_date = '0000-00-00' or dc.dc_start_date <= '{$batch_date}')
                             and (dc.dc_end_date is null or dc.dc_end_date = '0000-00-00' or dc.dc_end_date >= '{$batch_date}')
                            group by dc.dc_ID ";
				} else if ($bat['btype'] == 'tox') {

					if ($has_resultpdf) {
						$where_has_dc .= " or (ct.ct_test_ID = -1 and dc.dc_type='tox') ";
					}

					$sql = "
					select dc.*,
						sum(if(( dc.dc_type='tox' and rs.id>0 and ct.ct_test in ('{$tests_str}')) 
						or (dc.dc_type = 'urine' and rsp.conc is not null and rsp.conc!='' ) 
						or (dc.dc_type ='screen' and rsp.checked = 1 and rsp.conc is not null and rsp.conc!='') 
						{$where_has_dc}
						,1,0)) as has_dc,
						GROUP_CONCAT(ct.ct_test) as group_tests,
						GROUP_CONCAT( if(dc.dc_type='tox' and rs.id>0 and ct.ct_test in ('{$tests_str}') ,ct.ct_test,0)) as testids
					from {$maintbl} dc
					JOIN {$testtbl} ct ON ct.ct_dc_ID = dc.dc_ID
					left join ci_test_panels te ON te.te_type='tox' and ct.ct_test_ID = te.te_ID and dc.dc_type='tox'
					left JOIN ci_results rs ON rs.drug = ct.ct_test 
												and rs.report_id = '{$report_id}' 
												and dc.dc_type='tox'
					

					LEFT JOIN ci_result_test_speciments rsp ON  rsp.mid = '{$mid}'
													and rsp.testid = ct.ct_test_ID 
													and dc.dc_type in ('urine','screen')
					where  dc.dc_type in ('tox','urine','screen')
					and (dc.dc_start_date is null or dc.dc_start_date = '0000-00-00' or dc.dc_start_date <= '{$batch_date}')
					and (dc.dc_end_date is null or dc.dc_end_date = '0000-00-00' or dc.dc_end_date >= '{$batch_date}')
					{$wheredc}
					group by dc.dc_ID ";
				} else {
					$adding_has_dc = '';
					if ($bat['btype'] == 'pcr' && function_exists('trpdd_get_pcr_covid_test')) {
						$covid_test = trpdd_get_pcr_covid_test();

						if ($covid_test['te_ID'] > 0 && function_exists('trpdd_get_pcr_has_covid_rs')) {
							$has_covid_rs = trpdd_get_pcr_has_covid_rs($report_id, isset($bat['instrument']) ? $bat['instrument'] : '');
							if ($has_covid_rs) {
								$adding_has_dc = " or (te.te_ID = {$covid_test['te_ID']})";
							}
						}
					}
					$testids_str = implode(',', $testids_array);
					if (empty($testids_str)) {
						$testids_str = '-1';
					}

					//$wheretestname = ' and ct.ct_test = te.te_name ';

					$wheretestname = ' and ct.ct_test_ID = te.te_ID ';

					if ($has_resultpdf) {
						$adding_has_dc .= " or (ct.ct_test_ID = -1 and dc.dc_type='tox') ";
					}

					$sql = "select dc.*,
					        SUM(if((rs.id>0 and te.te_ID in ($testids_str) ) 
							{$adding_has_dc}
							,1,0)) as has_dc,
					        GROUP_CONCAT(ct.ct_test) as group_tests,
					        GROUP_CONCAT(if((rs.id>0 and te.te_ID in ($testids_str) ) {$adding_has_dc},te.te_ID,0)) as testids
                            from {$maintbl} dc
                            JOIN {$testtbl} ct ON ct.ct_dc_ID = dc.dc_ID
                            JOIN ci_test_panels te ON te.te_type='{$dc_type}' {$wheretestname}
                            left JOIN {$result_tbl} rs ON rs.report_id = '{$report_id}' 
									and rs.`pos-neg` !='' 
									and rs.`pos-neg` is not null
									
                                    and ( rs.drug = te.te_name 
                                        or rs.drug = te.te_abrev 
                                        or rs.drug = te.te_abrev2
                                        )
                             where  dc.dc_type='{$dc_type}'
                             and (dc.dc_start_date is null or dc.dc_start_date = '0000-00-00' or dc.dc_start_date <= '{$batch_date}')
                             and (dc.dc_end_date is null or dc.dc_end_date = '0000-00-00' or dc.dc_end_date >= '{$batch_date}')
							 {$wheredc}
                            group by dc.dc_ID 
                            order by has_dc desc";
				}


				$drug_classes = $wpdb->get_results($sql);
				echo $wpdb->last_error;


				if (isset($_GET['debug'])) {
					echo '<br>SQL: ' . $sql . '<br><br>';
					var_dump($drug_classes);
					echo '<br><br>';
				}
				$exclude_codes = array();
				$ref_lab_test_type = null;
				if (isset($bat['ref_lab_ID']) && $bat['ref_lab_ID']) {
					$ref_lab_test_type = $wpdb->get_var("select lab_ref_test_type from ci_labs where lab_ID = {$bat['ref_lab_ID']}");
				}

				foreach ($drug_classes as $d) {
					if ($d->has_dc && !empty($d->dc_exclude_code)) {
						$excludes = array_map('trim', explode(',', $d->dc_exclude_code));
						foreach ($excludes as $exc) {
							if (!empty($exc)) {
								$exclude_codes[] = $exc;
							}
						}
					}
					// ref lab test type
					if ($ref_lab_test_type) {
						$test_types = array_map('trim', explode(',', $ref_lab_test_type));
						if (in_array($d->dc_type, $test_types)) {
							$exclude_codes[] = $d->dc_code;
						}
					}
				}
				$exclude_codes = array_unique($exclude_codes);

				foreach ($drug_classes as $ii => $d) {
					if ($key == 'client_bill' && !empty($d->dc_labref_info)) {
						$dc_labref_info = json_decode($d->dc_labref_info, true);



						if (is_array($dc_labref_info) && isset($dc_labref_info[$bat['ref_lab_ID']])) {
							if (!empty($dc_labref_info[$bat['ref_lab_ID']]['code'])) {
								$d->dc_code = $dc_labref_info[$bat['ref_lab_ID']]['code'];
							}
							if (!empty($dc_labref_info[$bat['ref_lab_ID']]['amount'])) {
								$d->dc_amount = $dc_labref_info[$bat['ref_lab_ID']]['amount'];
							}
						}
					}

					if (isset($_GET['debug']) && $key == 'client_bill') {
						echo '<br>Checking dc_labref_info for dc_ID: ' . $d->dc_ID . ' with ref_lab_ID: ' . $bat['ref_lab_ID'] . '<br>';
						var_dump($dc_labref_info);
						echo '<br>';
						var_dump($d->dc_code);
						echo '<br><br>';
					}
					$drug_classes[$ii] = $d;
				}

				$code_listed = array();
				$code_tested = array();
				$group_records = $wpdb->get_results("
					SELECT * FROM {$maintbl}
					WHERE dc_is_group = 1
					AND dc_type = 'tox'
					AND (dc_start_date IS NULL OR dc_start_date = '0000-00-00' OR dc_start_date <= '{$batch_date}')
					AND (dc_end_date IS NULL OR dc_end_date = '0000-00-00' OR dc_end_date >= '{$batch_date}')
				");
				if ($onlycode) {

					foreach ($drug_classes as $ii => $d) {
						if (in_array($d->dc_code, $exclude_codes)) {
							if (isset($_GET['debug'])) {
								echo 'Skipping code ' . $d->dc_code . ' because it is excluded by another drug class.<br>';
							}
							continue;
						}
						$d->testids_arr = array();
						$found_test = false;
						foreach (explode(',', $d->testids) as $ttid) {
							if (!empty($ttid) && (!isset($code_tested[$ttid]) || !$code_tested[$ttid])) {
								$d->testids_arr[] = strtoupper($ttid); //uppercase for tox
								$code_tested[$ttid] = true;
								$found_test = true;
							}
						}
						if ($has_resultpdf && $d->has_dc) {
							$d->testids_arr[] = '-1'; //for result pdf without test match, use -1 as testid to show code
							$found_test = true;
						}
						if (!$found_test && !in_array($d->dc_type, array('urine', 'screen'))) {
							if (isset($_GET['debug'])) {
								echo 'Skipping code ' . $d->dc_code . ' because it does not have testids that match with panel tests.<br>';
							}
							continue;
						}
						$d->testids = count($d->testids_arr) > 0 ? implode(',', $d->testids_arr) : '';


						if ($d->has_dc) {
							if ($d->dc_minimum_match > 1 && $d->dc_minimum_match > $d->has_dc) {
								if (isset($_GET['debug'])) {
									echo 'Skipping code ' . $d->dc_code . ' because it requires minimum match of ' . $d->dc_minimum_match . ' but has_dc is ' . $d->has_dc . '<br>';
								}
								continue;
							}

							if ($d->has_dc > 1) {
								$tests_array = isset($d->group_tests) ? explode(',', $d->group_tests) : array();
								foreach ($tests_array as $ct_test) {
									$showed_tests[] = $ct_test;
								}
							} else if (is_array($showed_tests) && isset($d->group_tests) && in_array($d->group_tests, $showed_tests)) {
								if (isset($_GET['debug'])) {
									echo 'Skipping code ' . $d->dc_code . ' for test ' . $d->group_tests . ' because it has been showed by other code.<br>';
								}
								continue;
							}



							if ($returnbytest) {

								$x = $d->has_dc;
								if ($d->has_dc > $d->dc_pos_amount) {
									$x = $d->dc_pos_amount;
								}



								foreach ($d->testids_arr as $tid) {
									if (isset($code_tested[$tid]) && $code_tested[$tid]) {
										if (isset($_GET['debug'])) {
											echo 'Skipping code ' . $d->dc_code . ' for testid ' . $tid . ' because it has been tested by other code.<br>';
										}
										continue;
									}
									$all_codes[$tid] = array(
										'code' => $d->dc_code,
										'class' => $d->dc_class,
										'amount' => $d->dc_amount,
										'x' => $x
									);

									if ($d->dc_pos_amount == 1 && $d->dc_panel_search) {
										$all_codes[$tid]['code'] = '';
										$all_codes[$tid]['amount'] = '';
									}
								}


								if ($d->dc_panel_ID > 0) {


									$all_codes['G' . $d->dc_panel_ID] = array(
										'code' => $d->dc_code,
										'class' => $d->dc_class,
										'amount' => $d->dc_amount,
										'x' => $x
									);

									if ($d->dc_pos_amount == 1 && $d->dc_panel_search) {
										$all_codes['G' . $d->dc_panel_ID]['code'] = '';
										$all_codes['G' . $d->dc_panel_ID]['amount'] = '';
									}
								}

								if (($d->dc_pos_amount == 1 && $d->dc_panel_search) || ($has_resultpdf && $d->has_dc == 1)) {
									if (!isset($all_codes['classes'])) {
										$all_codes['classes'] = array();
									}

									$all_codes['classes'][$d->dc_code] = array(
										'code' => $d->dc_code,
										'class' => $d->dc_class,
										'amount' => $d->dc_amount,
										'x' => 1
									);
								}


								continue;
							} else {

								if (!isset($all_codes[$d->dc_code])) {
									$all_codes[$d->dc_code] = 0;
								}
								if (!empty($d->dc_dax_code) || !isset($ci_dax_codes[$d->dc_code])) {
									$ci_dax_codes[$d->dc_code] = $d->dc_dax_code;
								}
								$d->dc_pos_amount = intval($d->dc_pos_amount);

								if ($d->dc_pos_amount > 0 && isset($all_codes[$d->dc_code]) && ($all_codes[$d->dc_code] >= $d->dc_pos_amount || $d->has_dc >= $d->dc_pos_amount)) {
									$all_codes[$d->dc_code] = $d->dc_pos_amount;
									if (isset($_GET['debug'])) {
										echo 'dc_code: ' . $d->dc_code . '=>';
										echo 'has_dc: ' . $d->has_dc . '=>';
										echo 'dc_pos_amount: ' . $d->dc_pos_amount . '=>';
										echo 'dc_ID: ' . $d->dc_ID;
										echo '<br>';
									}
								} else {

									$x = $d->has_dc;
									if ($d->has_dc > $d->dc_pos_amount) {
										$x = $d->dc_pos_amount;
									}

									$all_codes[$d->dc_code] += $x;

									if (isset($_GET['debug'])) {
										echo 'dc_code: ' . $d->dc_code . '=>';
										echo 'has_dc: ' . $d->has_dc . '=>';
										echo '++=>';
										echo 'count: ' . $all_codes[$d->dc_code] . '=>';
										echo 'dc_ID: ' . $d->dc_ID;
										echo '<br>';
									}
								}
							}


							if ($d->dc_detect_u0005) {
								if (tr_bill_detect_u0005($report_id, $bat)) {
									if (!isset($all_codes['U0005'])) {
										$all_codes['U0005'] = 0;
									}
									$all_codes['U0005']++;
								}
							}

							if (isset($bat['swabbed']) && $bat['swabbed'] && !empty($d->dc_swabbed_code)) {
								if (!isset($all_codes[$d->dc_swabbed_code])) {
									$all_codes[$d->dc_swabbed_code] = 0;
								}
								$all_codes[$d->dc_swabbed_code]++;
							}
							if ($collector_is_lab_employee && !$collection_code_added && !empty($d->dc_swabbed_code2) && is_numeric(trim($d->dc_swabbed_code2))) {
								if (!isset($all_codes[$d->dc_swabbed_code2])) {
									$all_codes[$d->dc_swabbed_code2] = 0;
								}
								$all_codes[$d->dc_swabbed_code2]++;
								$collection_code_added = true;
							}
						}
					}
					// === GROUP LOGIC ===

					foreach ($group_records as $grp) {
						if (empty($grp->dc_group_class)) continue;

						$group_class_ids = array_map('intval', array_filter(array_map('trim', explode(',', $grp->dc_group_class))));

						$group_class_ids_str = implode(',', $group_class_ids);
						$group_class_rows = $wpdb->get_results("
							SELECT dc_ID, dc_class, dc_code FROM {$maintbl} 
							WHERE dc_ID IN ({$group_class_ids_str})
						");

						$matched_codes = array();
						foreach ($group_class_rows as $gcr) {
							if (isset($all_codes[$gcr->dc_code])) {
								$matched_codes[] = $gcr->dc_code;
							}
						}
						$matched_codes = array_unique($matched_codes);
						$count = count($matched_codes);

						$min = intval($grp->dc_minimum_match) ?: 1;
						$max = intval($grp->dc_maximum_match);
						$match = $count >= $min && ($max <= 0 || $count <= $max);

						if (isset($_GET['debug'])) {
							echo "Group {$grp->dc_code}: matched={$count}, min={$min}, max={$max}, match=" . ($match ? 'YES' : 'NO') . "<br>";
						}

						if ($match) {
							foreach ($matched_codes as $mc) {
								unset($all_codes[$mc]);
							}
							$all_codes[$grp->dc_code] = 1;

							if (isset($_GET['debug'])) {
								echo "Group {$grp->dc_code} applied. Removed: " . implode(',', $matched_codes) . "<br>";
							}
						}
					}
					// === GROUP LOGIC ===

					foreach ($group_records as $grp) {
						if (empty($grp->dc_group_class)) continue;

						$group_class_ids = array_map('intval', array_filter(array_map('trim', explode(',', $grp->dc_group_class))));

						$group_class_ids_str = implode(',', $group_class_ids);
						$group_class_rows = $wpdb->get_results("
							SELECT dc_ID, dc_class, dc_code FROM {$maintbl} 
							WHERE dc_ID IN ({$group_class_ids_str})
						");

						$matched_codes = array();
						foreach ($group_class_rows as $gcr) {
							if (isset($all_codes[$gcr->dc_code])) {
								$matched_codes[] = $gcr->dc_code;
							}
						}
						$matched_codes = array_unique($matched_codes);
						$count = count($matched_codes);

						$min = intval($grp->dc_minimum_match) ?: 1;
						$max = intval($grp->dc_maximum_match);
						$match = $count >= $min && ($max <= 0 || $count <= $max);

						if (isset($_GET['debug'])) {
							echo "Group {$grp->dc_code}: matched={$count}, min={$min}, max={$max}, match=" . ($match ? 'YES' : 'NO') . "<br>";
						}

						if ($match) {
							foreach ($matched_codes as $mc) {
								unset($all_codes[$mc]);
							}
							$all_codes[$grp->dc_code] = 1;

							if (isset($_GET['debug'])) {
								echo "Group {$grp->dc_code} applied. Removed: " . implode(',', $matched_codes) . "<br>";
							}
						}
					}
				} else {
					$valid_classes = array();
					foreach ($drug_classes as $ii => $d) {
						if (in_array($d->dc_code, $exclude_codes)) {
							if (isset($_GET['debug'])) echo 'Skipping code ' . $d->dc_code . ' because it is excluded by another drug class.<br>';
							continue;
						}
						if (!$d->has_dc) {
							if (isset($_GET['debug'])) echo 'Skipping code ' . $d->dc_code . ' because it has no match. <br>';
							continue;
						}
						if ($d->dc_minimum_match > 1 && $d->dc_minimum_match > $d->has_dc) {
							if (isset($_GET['debug'])) echo 'Skipping code ' . $d->dc_code . ' because it requires minimum match of ' . $d->dc_minimum_match . ' but has_dc is ' . $d->has_dc . '<br>';
							continue;
						}

						$d->testids_arr = array();
						$found_test = false;
						foreach (explode(',', $d->testids) as $ttid) {
							if ($ttid > 0 && (!isset($code_tested[$ttid]) || !$code_tested[$ttid])) {
								$d->testids_arr[] = $ttid;
								$code_tested[$ttid] = true;
								$found_test = true;
							}
						}
						if ($has_resultpdf && $d->has_dc > 0) {
							$d->testids_arr[] = '-1'; //for result pdf without test match, use -1 as testid to show code
							$found_test = true;
						}
						if (!$found_test && !in_array($d->dc_type, array('urine', 'screen'))) {
							if (isset($_GET['debug'])) echo 'Skipping code ' . $d->dc_code . ' because it has no test match and is not urine/screen type. <br>';
							continue;
						}

						$d->testids = count($d->testids_arr) > 0 ? implode(',', $d->testids_arr) : '';

						if ($d->has_dc > 1) {
							$tests_name_array = explode(',', $d->group_tests);
							foreach ($tests_name_array as $ct_test) {
								$showed_tests[] = $ct_test;
							}
						} else if (isset($d->group_tests) && in_array($d->group_tests, $showed_tests)) {
							if (isset($_GET['debug'])) echo 'Skipping code ' . $d->dc_code . ' for test ' . $d->group_tests . ' because it has been showed by other code.<br>';
							continue;
						}

						if (!in_array($d->dc_code, $code_listed)) {
							$code_listed[] = $d->dc_code;
						} else {
							$d->dc_code = '(' . $d->dc_code . ')';
						}

						if ($d->dc_detect_u0005) {
							if (tr_bill_detect_u0005($report_id, $bat)) {
								$d->dc_code .= ' U0005';
							}
						}
						if ($bat['swabbed'] && !empty($d->dc_swabbed_code)) {
							$d->dc_code .= ' ' . $d->dc_swabbed_code;
						}
						if ($collector_is_lab_employee && !$collection_code_added && !empty($d->dc_swabbed_code2) && is_numeric(trim($d->dc_swabbed_code2))) {
							$d->dc_code .= ' ' . $d->dc_swabbed_code2;
							$collection_code_added = true;
						}
						if (stripos($d->dc_code, 'x') === false && $d->has_dc > 1 && $d->dc_pos_amount > 0) {
							$x = $d->has_dc;
							if ($d->has_dc > $d->dc_pos_amount) {
								$x = $d->dc_pos_amount;
							}
							if ($x > 1) {
								$d->dc_code .= 'x' . $x;
							}
						}

						$valid_classes[] = $d;
					}

					$replaced_classes = array();
					$group_to_render  = array();

					foreach ($group_records as $grp) {
						if (empty($grp->dc_group_class)) continue;

						$group_class_ids = array_map('intval', array_filter(array_map('trim', explode(',', $grp->dc_group_class))));
						$group_class_ids_str = implode(',', $group_class_ids);
						$group_class_rows = $wpdb->get_results("
							SELECT dc_ID, dc_class, dc_code FROM {$maintbl} 
							WHERE dc_ID IN ({$group_class_ids_str})
						");

						$matched = array();
						foreach ($valid_classes as $d) {
							foreach ($group_class_rows as $gcr) {
								if ($d->dc_class === $gcr->dc_class) {
									$matched[] = $d->dc_class;
								}
							}
						}
						$matched = array_unique($matched);
						$count = count($matched);

						$min = intval($grp->dc_minimum_match) ?: 1;
						$max = intval($grp->dc_maximum_match);
						$match = $count >= $min && ($max <= 0 || $count <= $max);

						if (isset($_GET['debug'])) {
							echo "Group {$grp->dc_code}: matched={$count}, min={$min}, max={$max}, match=" . ($match ? 'YES' : 'NO') . "<br>";
						}

						if ($match) {
							foreach ($matched as $mc) {
								$replaced_classes[] = $mc;
							}
							$grp->_matched_count = $count;
							$group_to_render[] = $grp;

							if (isset($_GET['debug'])) {
								echo "Group {$grp->dc_code} applied. Replaced: " . implode(',', $matched) . "<br>";
							}
						}
					}
					$replaced_classes = array_unique($replaced_classes);

					// === Bước 3: Render ===
					ob_start();
					foreach ($valid_classes as $d) {
						// Ẩn class bị replace bởi group
						if (in_array($d->dc_class, $replaced_classes)) continue;

						$has_billing_code = true;
?>
						<div class="col2 drug_classes" data-rs="<?php echo $d->has_dc ?>" <?php if (!$d->has_dc) echo 'style="background:rgba(255,0,0,0.4)"' ?>>
							<div class="frow">
								<div class="col23">
									<?php
									echo '<b>' . $d->dc_class . '</b>';
									if (isset($_GET['debug'])) {
										echo '<div>counttests:' . $d->has_dc . '</div>';
										foreach ($d->testids_arr as $ttid) {
											echo '<div>' . $tests_array[$ttid] . '</div>';
										}
									}
									?>
								</div>
								<div class="col13">
									<?php echo $d->dc_code; ?>
								</div>
							</div>
						</div>
					<?php
					}

					// Render group codes
					foreach ($group_to_render as $grp) {
						$has_billing_code = true;
					?>
						<div class="col2 drug_classes" data-rs="<?php echo $grp->_matched_count ?>">
							<div class="frow">
								<div class="col23"><b><?php echo esc_html($grp->dc_class) ?></b></div>
								<div class="col13"><?php echo esc_html($grp->dc_code) ?></div>
							</div>
						</div>
					<?php
					}

					if (!empty($all_codes)) {
						foreach ($all_codes as $cc => $cc_count) {
							echo '<div class="frow">';
							echo '<div class="col13">Swap Code: ' . ($cc_count > 1 ? $cc_count . 'x' : '') . $cc . '</div>';
							echo '</div>';
						}
					}

					$child_dc = ob_get_clean();

					if (!empty($child_dc)) {
					?>
						<div class="frow" style="margin-bottom:20px;" data-count="<?php echo count($drug_classes) ?>">
							<?php echo $child_dc ?>
						</div>
				<?php
					}
				}
			}
		}

		if (!$onlycode && empty($settings['enable_medicare'])) {

			$show_medicare = false;

			if (!empty($insurance) || $allowemptyinsurance) {
				if (!isset($insurance_cache[$insurance_key])) {
					$insurance_cache[$insurance_key] = $wpdb->get_row(
						$wpdb->prepare("select * from ci_payer_list 
			                            where payer_name = %s ", $insurance),
						ARRAY_A
					);

					if ($insurance_cache[$insurance_key] == null) {
						$insurance_cache[$insurance_key] = array();
					}
				}

				if (
					$insurance_cache[$insurance_key]
					&& $insurance_cache[$insurance_key]['payer_medicare']
					&& $insurance_cache[$insurance_key]['payer_workmen']
				) {
					$show_medicare = true;
				}
			}



			if ($show_medicare) {
				?>
				<div class="frow " style="margin-bottom:20px">
					<?php
					$tests_array = array_map('trim', $tests_array);
					$tests_str = implode("','", $tests_array);
					$sql = "select * from (select count(distinct(dc.dc_code)) as c from ci_bill_drug_classes dc
                            JOIN ci_bill_drug_classes_test ct ON ct.ct_dc_ID = dc.dc_ID and ct.ct_test in ('{$tests_str}')
                            where dc.dc_type='{$dc_type}'
                            ) as rs
                            JOIN ci_bill_codes_medicare cm ON cm.cm_range_bot <= rs.c and cm.cm_range_top >= rs.c
                            
                            limit 1
                            ";

					$medicare = $wpdb->get_row($sql);
					if ($medicare) {
						$has_billing_code = true;
					?>
						<div class="col2">
							<div class="frow">
								<div class="col2" title="<?php echo $medicare->c ?>">
									<?php echo $medicare->cm_name ?>
								</div>
								<div class="col2">
									<?php echo $medicare->cm_code ?>
								</div>
							</div>
						</div>
					<?php
					}
					?>


				</div>

<?php
			}
		}
	}

	if ($onlycode) {
		if (isset($bat['withobj']) && $bat['withobj']) {
			return $all_codes;
		} else if (isset($bat['withtimes']) && $bat['withtimes']) {
			$arr = array();
			if (isset($_GET['debug'])) {
				var_dump($all_codes);
			}
			foreach ($all_codes as $cc => $cc_count) {
				$arr[] = ($cc_count > 1 ? $cc_count . 'x' : '') . $cc;
			}
			return implode(',', $arr);
		} else {
			return implode(',', array_keys($all_codes));
		}
	} else if (!$has_billing_code) {
		echo '<p>Not found Billing Code</p>';
	}
}

function tr_billing_get_cpt_code($bat)
{
	if (is_object($bat)) {
		$bat = (array) $bat;
	}

	return tr_bill_get_billing_code($bat['insurance'], $bat['id'], $bat, true);
}

function tr_billing_get_settings($key = false)
{
	static $settings;
	if (!is_array($settings)) {
		$settings = get_option('tr_billing_ind_st', array());
		if (empty($settings['default_pay_type'])) {
			$settings['default_pay_type'] = 'cash';
		}
		$settings['paytypes'] = array(
			'cash' => 'Cash',
			'insu' => 'Insurance',
			'clientb' => 'Client Bill'
		);
		if (isset($settings['disible_pt_ins_cash']) && $settings['disible_pt_ins_cash']) {
			unset($settings['paytypes']['clientb']);
		}

		if (isset($settings['patient_ins_cli_pay']) && $settings['patient_ins_cli_pay']) {
			$settings['paytypes'] = array(
				'cash' => 'Patient Pay',
				'insu' => 'Insurance Pay',
				'clipay' => 'Clinic Pay'
			);
		}
	}
	if ($key !== false) {
		return isset($settings[$key]) ? $settings[$key] : false;
	}
	return $settings;
}

function tr_billing_export_insurance_reports($date, $paytype, $return_file)
{
	$_REQUEST['act'] = 'export';
	$_REQUEST['fromdate'] = $_REQUEST['todate'] = $date;
	$_REQUEST['paytype'] = $paytype;
	$mids_exported = array();

	include(TRCDBI_PATH . 'pages/admin_ci_insurance_reports.php');
	return $mids_exported;
}


function tr_billing_display_date($date, $format = '')
{
	$settings       = tr_billing_get_settings();
	$has_hour = strpos($date, ':') !== false;
	if (!empty($format)) {
		$has_hour = strpos($format, 'H') !== false || strpos($format, 'h') !== false || strpos($format, 'i') !== false || strpos($format, 's') !== false;
	}

	if ($settings['enable_us_date_format']) {
		$format = 'm/d/Y';
		if ($has_hour) $format .= ' h:iA';
	}
	if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
		return '';
	}
	if (empty($format)) {
		$format = get_option('date_format');
		if ($has_hour) $format .= ' ' . get_option('time_format');
	}
	return date($format, strtotime($date));
}
