<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
include_once(TRCPDD_PATH . 'inc/functions_fix.php');

$covid_tests = trpdd_get_covid_tests('*');
$covid_tests_str = implode("','", $covid_tests);

$all_types = tr_pdd_get_batch_types();

$log_file = WP_CONTENT_DIR . '/log/cron_clean_results.txt';

$ftime = filemtime($log_file);
if ((time() - $ftime < 300 && !isset($_GET['testcron'])) || !function_exists('trpdd_clear_by_dir')) {
	echo 'exist';
	return;
}

$f_log = fopen($log_file, 'a+');
fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => start\n");


//check and update POS
if (in_array('tox', $all_types)) {
	$limit 		= 5000;
	$datestart 	= date('Y-m-d', time() - 86400 * 7);
	$orders 	= $wpdb->get_results("select * from ci_reports 
									where estatus='C' 
                                    and count_drugs=0 
                                    and created_date > '{$datestart}'
                                    order by id desc 
                                    limit 1000");

	foreach ($orders as $o) {
		$rs = tr_pdd_update_result_ordered($o->id);
		sleep(1);
	}
	if (count($orders) == 1000) {
		$nextcron = wp_next_scheduled('ci_cron_clean_results');
		if ($nextcron == 0 || $nextcron > time() + 360) {
			wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('check and update POS'));
		}
	}




	//update has pos
	$rs = $wpdb->get_results("select re.id,
		re.estatus,
		ba.mid,
		ba.panel_tests_requested
		from ci_reports re
		join ci_batches ba ON ba.assen = re.patient_id
		where re.has_pos is null 
		and ba.doc >'{$datestart}'
		order by re.id desc limit {$limit}");

	if (count($rs) == $limit) {
		$nextcron = wp_next_scheduled('ci_cron_clean_results');
		if ($nextcron == 0 || $nextcron > time() + 360) {
			wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('haspos'));
		}
	}
	foreach ($rs as $r) {
		$test_requested = explode(',', strtoupper($r->panel_tests_requested));

		$test_requested_str = implode("','", $test_requested);

		$has_pos = $wpdb->get_var("select count(*) from ci_results 
		where report_id = {$r->id}  and `pos-neg`= 'POS' 
		and drug in ('{$test_requested_str}') ");

		if ($r->estatus != 'C' && $has_pos == 0) {
			continue;
		}
		$has_pos = $has_pos > 0 ? 1 : 0;
		$update = array();
		$update['has_pos'] = $has_pos;
		$update['has_pos_modified'] = time();
		$wpdb->update('ci_reports', $update, array('id' => $r->id));
	}




	//check and update drug classes
	$limit = 1000;
	$codes = tr_pdd_get_report_medis();
	$rs = $wpdb->get_results("select re.*, 
		(select count(*) from ci_results rs where rs.report_id = re.id and rs.has_order=1) as count_rs 
		from ci_reports re
		where re.estatus='C' and re.has_80361 is null 
		order by re.id desc 
		limit {$limit} ");


	if (count($rs) == $limit) {
		$nextcron = wp_next_scheduled('ci_cron_clean_results');
		if ($nextcron == 0 || $nextcron > time() + 360) {
			wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('check and update drug classes'));
		}
	}
	//var_dump(count($rs));
	foreach ($rs as $r) {

		$update = array();
		$can_update = true;
		foreach ($codes as $code) {
			$sql = "select count(rs.id) as count, 
			SUM(IF(rs.`pos-neg`='POS',1,0)) as count_pos 
			from ci_results rs 
			JOIN ci_bill_drug_classes_test ct ON ct.ct_test = rs.drug
			JOIN ci_bill_drug_classes as dc ON ct.ct_dc_ID = dc.dc_ID 
			where rs.report_id = {$r->id} and rs.has_order=1
			and dc.dc_code = '{$code}' ";
			$count_rs = $wpdb->get_row($sql);

			if ($count_rs === false || !empty($wpdb->last_error)) {
				$can_update = false;
				var_dump($wpdb->last_error);
				exit;
			}
			$update['has_' . $code] = $count_rs->count > 0 ? 1 : 0;
			$update['has_' . $code . '_pos'] = $count_rs->count_pos > 0 ? 1 : 0;
		}
		if ($can_update) {
			$wpdb->update('ci_reports', $update, array('id' => $r->id));
			var_dump($update);
			var_dump($r->id);
			echo '<br><br>';
		}
	}
}





$not_rejected_statuses_str = tr_pdd_where_batch_rejected(false, true, 'pcr');

$limittime = time() - 3600 * 4; //4 hours
$limit_1year = date('Y-m-d', strtotime('-1 year'));

foreach ($all_types as $btype) {
	if (in_array($btype, array('study', 'tox'))) {
		continue;
	}

	echo '<br>update ' . $btype . ' has_pos<br>';

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$results_tbl = tr_pdd_get_table_result_by_btype($btype);
	$results_edit_file = TRCPDD_PATH . "parts/{$btype}/results_edit.php";

	if (
		!file_exists($results_edit_file)
		|| !$wpdb->get_var("show columns from {$report_tbl} like 'has_pos_modified'")
	) {
		continue;
	}

	//update pcr has_pos
	$limit_has_pos = 1000;
	$is_clear_results = true;


	$rsrows = $wpdb->get_results("select re.id, re.has_pos
					from {$report_tbl} re
					join {$batch_tbl} ba ON ba.assen = re.patient_id
					where (re.has_pos is null or re.has_pos_modified < re.modified or has_pos_modified is null)
					and {$not_rejected_statuses_str} and ba.batch_status not in ('archived')
					and (re.has_pos_modified < {$limittime} or re.has_pos_modified is null)
					and ba.doc > '{$limit_1year}'
					order by re.id desc
					limit {$limit_has_pos}
					");
	echo $wpdb->last_error;
	echo 'count:' . count($rsrows);
	echo '<br>';

	foreach ($rsrows as $resultrow) {
		$_GET['id'] = $id = $resultrow->id;
		$has_pos = null;
		$report = null;
		$count_rs = $wpdb->get_var("select count(*) from {$results_tbl} 
		where report_id = {$id} 
		and (`pos-neg`!= '' or conc >0)
		and has_order=1 ");
		if ($count_rs > 0) {

			ob_start();
			include($results_edit_file);
			$content = ob_get_clean();
		}


		$update_rp = array();
		$update_rp['has_pos_modified'] = time();
		$wpdb->update($report_tbl, $update_rp, array('id' => $id));
		if ($report) {
			echo $report['assen'] . ' =>' . $report['doc'] . ' => ';
		}


		echo is_null($resultrow->has_pos) ? 'null' : $resultrow->has_pos;
		echo ' -> ';
		echo ($has_pos);
		if ($has_pos != $resultrow->has_pos) {
			echo ' ====> updated to ' . $has_pos;
		}
		echo  '<br>';
	}


	if (count($rsrows) == $limit_has_pos) {
		$nextcron = wp_next_scheduled('ci_cron_clean_results');
		if ($nextcron == 0 || $nextcron > time() + 800) {
			wp_schedule_single_event(time() + 600, 'ci_cron_clean_results', array('update ' . $btype . ' has_pos'));
		}
	}
}



//update pcr

if (in_array('pcr', $all_types)) {

	//update count_drugs
	$pcr_limit_date = date('Y-m-d', strtotime('-7 days'));
	$rs = $wpdb->get_results("select id from ci_reports_pcr 
									where estatus = 'C' 
										and count_drugs=0 
										and created > '{$pcr_limit_date}'
									order by id desc 
									limit 1000");
	foreach ($rs as $r) {
		$re_id = $r->id;
		$wpdb->query("update ci_reports_pcr set 
                                count_drugs = (select count(*) from ci_results_pcr 
                                where report_id = ci_reports_pcr.id and has_order=1) 
                                where id = {$re_id} ");
	}

	if (count($rs) == 1000) {
		$nextcron = wp_next_scheduled('ci_cron_clean_results');
		if ($nextcron == 0 || $nextcron > time() + 360) {
			wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('pcr'));
		}
	}

	//update has_uti

	$main_groups = $wpdb->get_results("
	select tg_ID, tg_name, tg_other_options
	from ci_test_groups
	where tg_parent = 0
	and tg_type = 'pcr'
	and tg_other_options like '%new_abx\":\"1%'
	and tg_antibiotic = 0 
	order by tg_position asc
	");
	$groups = array();
	foreach ($main_groups as $mg) {
		$mg->other_options = json_decode($mg->tg_other_options, true);

		//only update when date not active
		if (isset($mg->other_options['new_abx_date']) && $mg->other_options['new_abx_date'] > date_i18n('Y-m-d')) {
			$groups[] = $mg;
		}
	}


	$limit 		= 1000;

	if (count($groups) > 0) {
		$where_grouups = array();
		foreach ($groups as $g) {

			echo '<br><br>Start to update has_uti for group: ' . $g->tg_name . '<br>';
			$gid = $g->tg_ID;
			$abx_type = isset($g->other_options['abx_type']) ? $g->other_options['abx_type'] : '';


			$abx_type_num       = 9; //1: uti; 2: wound: 3: gi , 4:rpp
			if ($abx_type == 'uti') {
				$abx_type_num = 1;
			} else if ($abx_type == 'wound') {
				$abx_type_num = 2;
			} else if ($abx_type == 'gi') {
				$abx_type_num = 3;
			} else if ($abx_type == 'r') {
				$abx_type_num = 4;
			} else if (in_array($abx_type, array('v', 'sm', 'sp', 'sr', 'sl', 'es'))) {
				$abx_type_num = 5;
			} else if ($abx_type == 'ei') {
				$abx_type_num = 6;
			} else if ($abx_type == 'sti') {
				$abx_type_num = 7;
			} else if ($abx_type == 'nail') {
				$abx_type_num = 8;
			}


			echo '<br>update pcr has_uti for group ' . $gid . ': ' . $g->tg_name . '<br>';

			$bacteria_test_IDs = $wpdb->get_col(
				"select te_ID 
				from ci_test_panels te
				join ci_test_groups g ON g.tg_ID = te.te_group_id
				left join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
				where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
				and (tg_parent = {$gid} or tg_ID = {$gid})
				and ((tg_name like '%Bacteria%' or tg_name like '%Pathogen%' or tg_name like '%PARASITE%' or tg_name like '%Gram%') or tm.test_id is not null )"
			);

			if (empty($bacteria_test_IDs)) {
				$bacteria_test_IDs =  $wpdb->get_col(
					"select te_ID 
				from ci_test_panels te
				join ci_test_groups g ON g.tg_ID = te.te_group_id
				left join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
				where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
				and tg_ID = {$gid} 
				"
				);
			}

			$bacteria_test_IDs_str = implode(',', $bacteria_test_IDs);
			echo 'bacteria_test_IDs_str: ' . $bacteria_test_IDs_str . '<br>';

			if (empty($bacteria_test_IDs_str)) {
				continue;
			}

			$orders 	= $wpdb->get_results("select re.id, re.has_uti,
										ba.assen, ba.doc,
										ba.panel_tests_requested
									from ci_reports_pcr re
									JOIN ci_batches_pcr ba ON ba.assen = re.patient_id
									where re.estatus='C' 
									and re.has_uti is null 
									and FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)
									and {$not_rejected_statuses_str} and ba.batch_status not in ('archived')
									and ba.doc > '{$limit_1year}'
									order by id desc 
									limit {$limit}");
			echo $wpdb->last_error;
			foreach ($orders as $o) {
				$has_uti = $wpdb->get_var("select count(*) from ci_results_pcr rs
				JOIN ci_test_panels te ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
				where rs.report_id = {$o->id}  
					and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL')
					and rs.has_order = 1
					and te.te_ID in ({$bacteria_test_IDs_str}) ");
				echo $wpdb->last_error;
				$has_uti = $has_uti > 0 ? $abx_type_num : 0;
				$update = array();
				$update['has_uti'] = $has_uti;
				$wpdb->update('ci_reports_pcr', $update, array('id' => $o->id));

				echo $o->assen . ' => ' . $o->doc . ' => old: ' . $o->has_uti . ' =>new: ' . $has_uti . '<br>';
			}
		}

		if (count($orders) == $limit) {
			$nextcron = wp_next_scheduled('ci_cron_clean_results');
			if ($nextcron == 0 || $nextcron > time() + 360) {
				wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('pcr has_uti'));
			}
		}
	}
}


//create history pdfs
foreach ($all_types as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$rs = $wpdb->get_results("select *
		from {$report_tbl} re
		join {$batch_tbl} ba ON ba.assen = re.patient_id
		where re.estatus='C' 
		and re.final_results > 0
		order by re.id desc 
		limit 500
		", ARRAY_A);
	foreach ($rs as $report) {
		$dirhistory = WP_CONTENT_DIR . '/results/html/' . strtolower($report['assen']);
		if (file_exists($dirhistory)) {
			continue;
		}

		trpdd_build_history_pdf($report, $btype);

		fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => create history for {$report['assen']} \n");
	}
}



$hour = date_i18n('H');
$las_clean_run = get_option('trpdd_last_cron_clean_run', 0);
if ($las_clean_run < time() - 86400) {
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/qrs/', 86400 * 7, time());

	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/elink/', 86400 * 120, time());
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/cache/reqs/', 86400 * 30, time());

	//trpdd_clear_by_dir(WP_CONTENT_DIR.'/log/results/',86400*600,time());
	//trpdd_clear_by_dir(WP_CONTENT_DIR.'/log/results_blood/',86400*600,time());
	//trpdd_clear_by_dir(WP_CONTENT_DIR.'/log/results_pcr/',86400*600,time());
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/tecan/', 86400 * 90, time());
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/prefillcovid/', 86400 * 90, time());

	trpdd_clear_by_dir(WP_CONTENT_DIR . '/cache/queries/', 86400 * 6, time());
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/results/', 86400 * 360, time(), false, '.pdf');
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/tmp/', 3600, time());

	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => clean folders ' . "\n");
	update_option('trpdd_last_cron_clean_run', time(), false);

	if (isset($_GET['testcron'])) {
		echo 'clean folders';
	}
}

//remove xygene




fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n\n\n");
fclose($f_log);

if (filesize($log_file) > 500000) {
	$olddata = substr(file_get_contents($log_file), 390000);
	file_put_contents($log_file, $olddata);
}
