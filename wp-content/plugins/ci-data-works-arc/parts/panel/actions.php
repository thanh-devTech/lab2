<?php
global $wpdb;




if (!function_exists('tr_change_abrev')) {
	function tr_change_abrev($old, $new)
	{
		global $wpdb;
		$groupid = $old['te_group_id'] > 0 ? $old['te_group_id'] : $new['te_group_id'];
		$btype = $old['te_type'] ? $old['te_type'] : $new['te_type'];
		$old_abrev = $old['te_abrev'];
		$new_abrev = $new['te_abrev'];

		if ($btype == 'blood') {
			$rs = $wpdb->get_results("select te_ID, te_calculation from ci_test_panels 
			where te_type = '{$btype}'
			and te_group_id = '{$groupid}' 
			and te_calculation!=''
			and te_calculation like '%[{$old_abrev}]%'
			");
			foreach ($rs as $r) {
				$calc = $r->te_calculation;
				$calc = str_replace('[' . $old_abrev . ']', '[' . $new_abrev . ']', $calc);

				if (!empty($calc)) {
					$wpdb->update('ci_test_panels', array('te_calculation' => $calc), array('te_ID' => $r->te_ID));
				}
			}
		}
	}
}

$act        = isset($_GET['act']) ? $_GET['act'] : '';


if ($act == 'export' && isset($_GET['trajax'])) {
	$type = $_REQUEST['type'];
	$result_settings = get_option('ci_result_settings_' . $type, array());
	ob_clean();
	$groups = $wpdb->get_results("select * from ci_test_groups 
	where tg_type='{$type}' 
	and tg_show_req = 1
	and tg_parent = 0
	order by tg_position asc ", ARRAY_A);

	$tests = $wpdb->get_results("select te.* , 
	g.tg_name,
	(select tg_name from ci_test_groups gp where gp.tg_ID = g.tg_parent) as group_parent
	from ci_test_panels te
	JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
	where te_type= '{$type}' 
	and te_in_req = 1 
	and te_hide_pdf = 0
	and (
		g.tg_show_req = 1
		or (
			g.tg_parent in (select tg_ID from ci_test_groups where tg_show_req = 1)
		)
	)
	order by group_parent asc, g.tg_name asc
	", ARRAY_A);

	$testbygroups = array();
	foreach ($tests as $test) {
		$test['main_group'] = $test['tg_name'];
		$test['subgroup'] = '';
		if (!empty($test['group_parent'])) {
			$test['subgroup'] = $test['tg_name'];
			$test['main_group'] = $test['group_parent'];
		}
		$row = array(
			$test['te_name'],
			$test['te_loinc_code'],
			'',
			$test['te_abrev'],
			$test['main_group'],
			$test['subgroup'],
		);
		$testbygroups[$test['te_group_id']][] = $row;
	}

	$lab_id = CI_Base_Lab::get_current_lab_id();
	$labinit = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$lab_id}");
	$filename = strtoupper($type) . '_Panels_' . $labinit . '_' . date_i18n('mdy') . '.csv';
	header('Content-disposition: attachment; filename=' . $filename);
	header('Content-type: application/csv');

	$f = fopen('php://output', 'w');

	$headers = array('Test Name', 'LOINC', 'Order Code', 'Abreviation', 'Group', 'Sub Group');
	fputcsv($f, $headers);

	foreach ($groups as $group) {
		$child_groups = $wpdb->get_results("select * from ci_test_groups where tg_type='{$type}' 
		and tg_parent = {$group['tg_ID']} 
		order by tg_position asc", ARRAY_A);

		fputcsv($f, array(
			$group['tg_name'],
			$group['tg_short_name'],
			$group['tg_cpt_code'],
		));

		if (isset($testbygroups[$group['tg_ID']]) && count($testbygroups[$group['tg_ID']]) > 0) {
			foreach ($testbygroups[$group['tg_ID']] as $row) {
				fputcsv($f, $row);
			}
		}

		if (count($child_groups) > 0) {
			foreach ($child_groups as $childgroup) {

				fputcsv($f, array(
					$childgroup['tg_name'],
					$childgroup['tg_short_name'],
				));
				if (isset($testbygroups[$childgroup['tg_ID']]) && count($testbygroups[$childgroup['tg_ID']]) > 0) {
					foreach ($testbygroups[$childgroup['tg_ID']] as $row) {
						fputcsv($f, $row);
					}
				}
			}
		}
	}

	exit;
} else if ($act == 'export_json' && isset($_GET['trajax'])) {
	$type = $_REQUEST['type'];
	$result_settings = get_option('ci_result_settings_' . $type, array());
	ob_clean();
	$groups = $wpdb->get_results("select * from ci_test_groups where tg_type='{$type}' order by tg_parent asc", ARRAY_A);
	$tests = $wpdb->get_results("select * from ci_test_panels where te_type= '{$type}' ", ARRAY_A);
	$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_btype= '{$type}' ", ARRAY_A);
	$specimens = $wpdb->get_results("select * from ci_test_specimens where ts_type= '{$type}' ", ARRAY_A);


	$json = array(
		'groups' => $groups,
		'tests' => $tests,
		'cutoffs' => $cutoffs,
		'specimens' => $specimens,
		'from' => 'arc',
	);
	header('Content-disposition: attachment; filename=' . $type . '.json');
	header('Content-type: application/json');
	wp_send_json($json);
	exit;
} else if ($act == 'backup_json' && isset($_GET['trajax'])) {
	$type = $_REQUEST['type'];
	$result_settings = get_option('ci_result_settings_' . $type, array());
	ob_clean();
	$groups = $wpdb->get_results("select * from ci_test_groups where tg_type='{$type}' order by tg_parent asc", ARRAY_A);
	$tests = $wpdb->get_results("select * from ci_test_panels where te_type= '{$type}' ", ARRAY_A);
	$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_btype= '{$type}' ", ARRAY_A);
	$specimens = $wpdb->get_results("select * from ci_test_specimens where ts_type= '{$type}' ", ARRAY_A);

	$json = array(
		'groups' => $groups,
		'tests' => $tests,
		'cutoffs' => $cutoffs,
		'specimens' => $specimens,
		'from' => 'arc',
	);
	$bk_folder = WP_CONTENT_DIR . '/backups/panels/' . $type;
	if (!file_exists($bk_folder)) {
		wp_mkdir_p($bk_folder);
	}

	$filename =  $type . '_' . date_i18n('Y-m-d_H-i-s') . '.json';
	$filepath = $bk_folder . '/' . $filename;
	$json_data = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	if (file_put_contents($filepath, $json_data)) {
		echo "Backup created successfully!";
	} else {
		echo "Failed to create backup file";
	}
	exit;
} else if ($act == 'export_bacteria') {
	$groupk         = isset($_REQUEST['groupk']) ? $_REQUEST['groupk'] : 'uti';
	$bacteria_tbl   = trpdd_get_bacteria_table($groupk);
	$filename       = $bacteria_tbl . ($groupk == 'uti' ? '_uti' : '');

	header('Content-disposition: attachment; filename=' . $filename . '.csv');
	header('Content-type: text/csv');
	$f = fopen('php://output', 'w');

	$rows = $wpdb->get_results("select * from {$bacteria_tbl} order by ID asc", ARRAY_A);
	$show_header = false;
	foreach ($rows as $row) {
		if (!$show_header) {
			$header = array_keys($row);
			fputcsv($f, $header);
			$show_header = true;
		}
		fputcsv($f, $row);
	}


	exit;
} else if ($act == 'export_abx') {
	$groupk         = isset($_REQUEST['groupk']) ? $_REQUEST['groupk'] : 'uti';

	$abx_tbl        = trpdd_get_abx_table($groupk);

	$filename       = $abx_tbl . ($groupk == 'uti' ? '_uti' : '');

	header('Content-disposition: attachment; filename=' . $filename . '.csv');
	header('Content-type: text/csv');
	$f = fopen('php://output', 'w');

	$rows = $wpdb->get_results("select * from {$abx_tbl} order by ID asc", ARRAY_A);
	$show_header = false;
	foreach ($rows as $row) {
		if (!$show_header) {
			$header = array_keys($row);
			fputcsv($f, $header);
			$show_header = true;
		}
		fputcsv($f, $row);
	}


	exit;
} else if ($act == 'cutoff_export') {
	$gid = isset($_REQUEST['gid']) ? intval($_REQUEST['gid']) : 0;
	$today = date_i18n('Y-m-d');
	$type = $_REQUEST['type'];
	$outfile = CI_Base_Lab::get_current_lab_init() . '_cutoffs_' . $type . '_' . $today . '.csv';
	$result_settings = get_option('ci_result_settings_' . $type, array());

	header('Content-disposition: attachment; filename=' . $outfile);
	header('Content-type: text/csv');

	$f = fopen('php://output', 'w');
	$header = array('Panel', 'Test Name');
	if ($type == 'blood') {
		$header[] = 'Critical Low';
		$header[] = 'Low';
		$header[] = 'Medium';
		$header[] = 'High';
		$header[] = 'Critical High';
		$header[] = 'Instrument';
		$header[] = 'Gender';
		$header[] = 'Age';
	} else if ($type == 'pcr') {
		if ($result_settings['enable_verylh']) {
			$header[] = 'Very Low';
		} else {
			$header[] = 'Repeat';
			$header[] = 'Indeterminate';
		}

		$header[] = 'Low';
		$header[] = 'Medium';
		$header[] = 'High';
		if ($result_settings['enable_verylh']) {
			$header[] = 'Very High';
		}
		$header[] = 'Instrument';
	} else {
		$header[] = 'Cutoff';
		$header[] = 'Upper Limit';
		$header[] = 'Low';
		$header[] = 'High';
		$header[] = 'Instrument';
	}
	$header[] = 'Start Date';
	$header[] = 'End Date';
	fputcsv($f, $header);

	$where = '';
	if ($gid > 0) {
		$where .= " and (g.tg_ID = {$gid} or g.tg_parent = {$gid}) ";
	}

	$tests = $wpdb->get_results("
	select te.*, g.tg_name, tgp.tg_name AS group_parent
	from ci_test_panels te
	JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
	LEFT JOIN ci_test_groups tgp ON tgp.tg_ID = g.tg_parent
	where te_type= '{$type}'
	and te_in_req = 1
	and (te_end='' or te_end is null or te_end < '{$today}')
	AND (g.tg_show_req = 1 OR tgp.tg_show_req = 1)
	{$where}
	order by group_parent asc, g.tg_name asc, te.te_name asc
	", ARRAY_A);



	foreach ($tests as $t) {
		$cuts = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_te_ID = {$t['te_ID']}", ARRAY_A);

		$panel_name = $t['group_parent'] ?? $t['tg_name'];
		foreach ($cuts as $cut) {
			$row = array($panel_name, $t['te_name']);
			if ($type == 'blood') {
				$row[] = $cut['cu_critical_low'];
				$row[] = $cut['cu_cutoff'];
				$row[] = $cut['cu_medium'];
				$row[] = $cut['cu_upper_limit'];
				$row[] = $cut['cu_critical_high'];
				$row[] = $cut['cu_ms'];
				$row[] = $cut['cu_gender'];
				$row[] = $cut['cu_age'];
			} else if ($type == 'pcr') {
				if ($result_settings['enable_verylh']) {
					$row[] = $cut['cu_critical_low'];
				} else {
					$row[] = $cut['cu_critical_low'];
					$row[] = $cut['cu_critical_high'];
				}

				$row[] = $cut['cu_cutoff'];
				$row[] = $cut['cu_medium'];
				$row[] = $cut['cu_upper_limit']; //high
				if ($result_settings['enable_verylh']) {
					$row[] = $cut['cu_critical_high']; //very high
				}
				$row[] = $cut['cu_ms'];
			} else {
				$row[] = $cut['cu_cutoff'];
				$row[] = $cut['cu_upper_limit'];
				$row[] = $cut['cu_lqc'];
				$row[] = $cut['cu_hqc'];
				$row[] = $cut['cu_ms'];
			}
			$row[] = $cut['cu_start'];
			$row[] = $cut['cu_end'];
			fputcsv($f, $row);
		}
	}
	exit;
} else if ($act == 'export_template') {
	$today = date_i18n('Y-m-d');
	$type = $_REQUEST['type'];
	$outfile = CI_Base_Lab::get_current_lab_init() . '_template_' . $type . '_' . $today . '.csv';
	$result_settings = get_option('ci_result_settings_' . $type, array());

	header('Content-disposition: attachment; filename=' . $outfile);
	header('Content-type: text/csv');

	$f = fopen('php://output', 'w');

	// Header row
	$header = array('Panel', 'Test Name', 'Abrev', 'Abrev 2', 'Snomed Code', 'Loinc Code', 'Label Barcode', 'Units');

	if ($type == 'blood') {
		$header[] = 'Critical Low';
		$header[] = 'Low';
		$header[] = 'Medium';
		$header[] = 'High';
		$header[] = 'Critical High';
		$header[] = 'Instrument';
		$header[] = 'Gender';
		$header[] = 'Age';
	} else if ($type == 'pcr') {
		if ($result_settings['enable_verylh']) {
			$header[] = 'Very Low';
		} else {
			$header[] = 'Repeat';
			$header[] = 'Indeterminate';
		}
		$header[] = 'Low';
		$header[] = 'Medium';
		$header[] = 'High';
		if ($result_settings['enable_verylh']) {
			$header[] = 'Very High';
		}
		$header[] = 'Instrument';
	} else {
		$header[] = 'Cutoff';
		$header[] = 'Upper Limit';
		$header[] = 'Low';
		$header[] = 'High';
		$header[] = 'Instrument';
	}
	$header[] = 'Start Date';
	$header[] = 'End Date';
	fputcsv($f, $header);

	// Data: only Panel + Test Name + extra fields, cutoff fields left empty
	$tests = $wpdb->get_results("
        SELECT te.*, g.tg_name, tgp.tg_name AS group_parent
        FROM ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        LEFT JOIN ci_test_groups tgp ON tgp.tg_ID = g.tg_parent
        WHERE te_type = '{$type}'
        AND te_in_req = 1
        AND (te_end='' OR te_end IS NULL OR te_end < '{$today}')
        AND (g.tg_show_req = 1 OR tgp.tg_show_req = 1)
        ORDER BY group_parent ASC, g.tg_name ASC, te.te_name ASC
    ", ARRAY_A);

	foreach ($tests as $t) {
		$panel_name = $t['group_parent'] ?? $t['tg_name'];
		$row = array(
			$panel_name,
			$t['te_name'],
			$t['te_abrev'],
			$t['te_abrev2'],
			$t['te_snomed_code'],
			$t['te_loinc_code'],
			$t['te_label_barcode'],
			$t['te_units'],
		);

		// Empty cutoff columns
		if ($type == 'blood') {
			$row = array_merge($row, array('', '', '', '', '', '', '', ''));
		} else if ($type == 'pcr') {
			if ($result_settings['enable_verylh']) {
				$row = array_merge($row, array('', '', '', '', '', ''));
			} else {
				$row = array_merge($row, array('', '', '', '', '', ''));
			}
		} else {
			$row = array_merge($row, array('', '', '', '', ''));
		}
		$row[] = ''; // Start Date
		$row[] = ''; // End Date

		fputcsv($f, $row);
	}
	exit;
} else if ($act == 'compendium_export') {
	$today = date_i18n('Y-m-d');
	$type = $_REQUEST['type'];
	$outfile = 'Compendium_' . CI_Base_Lab::get_current_lab_init() . '_' . $today . '.csv';
	header('Content-disposition: attachment; filename=' . $outfile);
	header('Content-type: text/csv');

	$f = fopen('php://output', 'w');
	fputcsv($f, array('Test Name', 'Test Code', 'Specimen Types', 'Specimen Codes', 'CPT Codes'));

	$results = $wpdb->get_results("
		SELECT 
			g.tg_ID, 
			g.tg_name, 
			g.tg_cpt_code, 
			ts.ts_name, 
			ts.ts_code 
		FROM ci_test_groups g 
		LEFT JOIN ci_test_specimens ts ON g.tg_ID = ts.ts_group_id 
		WHERE g.tg_type = '{$type}' 
			AND g.tg_show_req = 1 
			AND g.tg_parent = 0 
		ORDER BY g.tg_name ASC
	", ARRAY_A);

	$groups = array();
	foreach ($results as $row) {
		$tg_id = $row['tg_ID'];

		if (!isset($groups[$tg_id])) {
			$groups[$tg_id] = array(
				'tg_ID' => $row['tg_ID'],
				'tg_name' => $row['tg_name'],
				'tg_cpt_code' => $row['tg_cpt_code'],
				'specimen_names' => array(),
				'specimen_codes' => array()
			);
		}
		if (!empty($row['ts_name']) && trim($row['ts_name']) !== '') {
			$groups[$tg_id]['specimen_names'][] = $row['ts_name'];
		}

		if (!empty($row['ts_code']) && trim($row['ts_code']) !== '') {
			$groups[$tg_id]['specimen_codes'][] = $row['ts_code'];
		}
	}

	$groups = array_values($groups);

	foreach ($groups as $group) {
		$specimen_types = implode(', ', $group['specimen_names']);
		$specimen_codes_str = implode(', ', $group['specimen_codes']);

		fputcsv($f, array(
			$group['tg_name'],
			$group['tg_cpt_code'],
			$specimen_types,
			$specimen_codes_str,
			''
		));
	}

	if (defined('TRPDD_ARK') && $type === 'tox') {
		fputcsv($f, array(
			'DRUG SCREEN, URINE',
			'69739-1',
			'',
			'',
			'',
		));

		fputcsv($f, array(
			'DRUG SCREEN with Reflex Confirmation, URINE',
			'87428-9',
			'',
			'',
			'',
		));
	}

	fclose($f);
	exit;
} else if (isset($_REQUEST['save_test'])) {
	ob_clean();
	$te_id 	= $_REQUEST['id'];
	$col 	= $_REQUEST['col'];
	$vl 	= is_array($_REQUEST['val']) ? $_REQUEST['val'] : trim($_REQUEST['val']);
	$return = array('status' => 'ok');
	$old_val = '';
	$new_val = $vl;
	$old_panel = $wpdb->get_row("select * from ci_test_panels where te_ID = {$te_id} ", ARRAY_A);
	$panel_name = $old_panel['te_name'];

	if (stripos($col, 'meta_') === 0) {
		$col = str_replace('meta_', '', $col);
		$vl = is_array($vl) ? implode(',', $vl) : $vl;
		$old_meta_value = $wpdb->get_var("select meta_value from ci_test_meta 
                    where test_id = {$te_id}
                    and meta_key='{$col}' ");

		$existm = $wpdb->get_var("select id from ci_test_meta 
                    where test_id = {$te_id}
                    and meta_key='{$col}' ");
		$metaarr = array(
			'test_id' => $te_id,
			'meta_key' => $col,
			'meta_value' => $vl
		);
		if ($existm > 0) {
			$wpdb->update('ci_test_meta', $metaarr, array('id' => $existm));
		} else {
			$wpdb->insert('ci_test_meta', $metaarr);
		}
		$old_val = $old_meta_value;
		if (function_exists('tr_client_send_hub') && ($col === 'bac_tests' || $col === 'mkr_tests')) {
			$testobj = $wpdb->get_row("select * from ci_test_panels where te_ID = {$te_id}");

			$old_options_name = '';
			$new_options_name = '';
			if ($col === 'bac_tests') {
				$table = 'ci_abx_bacterias';
				$column = 'bacteria_name';
			} else {
				$table = 'ci_abx_resistance_markers';
				$column = 'resistance_marker';
			}
			if (!empty($old_meta_value)) {
				$old_ids = explode(',', $old_meta_value);
				$old_ids = array_map('intval', $old_ids);
				$old_ids_str = implode(',', $old_ids);

				$old_names = $wpdb->get_col(
					"SELECT {$column} FROM {$table} WHERE ID IN ({$old_ids_str}) ORDER BY {$column} ASC"
				);
				$old_options_name = implode(', ', $old_names);
			}

			if (!empty($vl)) {
				$new_ids = explode(',', $vl);
				$new_ids = array_map('intval', $new_ids);
				$new_ids_str = implode(',', $new_ids);

				$new_names = $wpdb->get_col(
					"SELECT {$column} FROM {$table} WHERE ID IN ({$new_ids_str}) ORDER BY {$column} ASC"
				);
				$new_options_name = implode(', ', $new_names);
			}
			$old_val = $old_options_name;
			$new_val = $new_options_name;

			$change_data = [
				$panel_name => [
					'oldValue' => $old_options_name,
					'newValue' => $new_options_name,
				]
			];
			$user = wp_get_current_user();
			tr_client_send_hub(array(
				'action'    => 'abx_change',
				'log_type' => 'test_map',
				'change_data' => json_encode($change_data),
				'by_email' => $user->user_email,
				'id' => $te_id
			));

			if (tr_get_option('ci_pdf_data', 'new_abx')) {
				//check abx type
				$groupp = $wpdb->get_row("select * from ci_test_groups 
                where tg_parent = 0 and tg_other_options like '%new_abx\":\"1%'
                and ( tg_ID = {$testobj->te_group_id} or tg_ID IN (select tg_parent from ci_test_groups where tg_ID = {$testobj->te_group_id})) ");
				if ($groupp) {
					//clear abxtbl cache
					$wpdb->query("update {$wpdb->options} set option_value = '0'  where option_name like '%ci_abx_results_scores_%' ");
				}
			}
		}
		update_option('ci_abx_last_change', time());
	} else if (stripos($col, 'cu_') === 0) {
		//update cutoffs
		list($col, $ms) = explode('__', $col);
		$allowed_cols = array('cu_critical_low', 'cu_cutoff', 'cu_medium', 'cu_upper_limit', 'cu_critical_high');
		if (!in_array($col, $allowed_cols, true)) {
			echo '<div class="msg">Invalid cutoff column.</div>';
			exit;
		}
		$vl = trpdd_replace_symbol_db($vl);
		$te_type = $wpdb->get_var($wpdb->prepare("select te_type from ci_test_panels where te_ID = %d limit 1", $te_id));
		$te_type = !empty($te_type) ? $te_type : 'pcr';

		$old_cutoff = $wpdb->get_row($wpdb->prepare(
			"select * from ci_test_panels_cutoffs 
			where cu_te_ID = %d 
			and cu_ms = %s
			and cu_btype = %s
			order by created desc, cu_start desc
			limit 1",
			$te_id,
			$ms,
			$te_type
		), ARRAY_A);

		if ($old_cutoff) {
			$old_val = $old_cutoff[$col];
			$rs = $wpdb->query($wpdb->prepare(
				"update ci_test_panels_cutoffs 
				set {$col} = %s
				where cu_te_ID = %d
				and cu_ms = %s
				and cu_btype = %s
				order by created desc, cu_start desc
				limit 1",
				$vl,
				$te_id,
				$ms,
				$te_type
			));
		} else {
			$s = $wpdb->insert('ci_test_panels_cutoffs', array(
				'cu_te_ID' => $te_id,
				'cu_ms' => $ms,
				'cu_btype' => $te_type,
				$col => $vl,
			));
			$rs = $s;
		}
	} else {
		$old_val = isset($old_panel[$col]) ? $old_panel[$col] : '';
		$rs = $wpdb->update('ci_test_panels', array($col => $vl), array('te_ID' => $te_id));
	}
	$changed_arr = [
		'id' => $te_id,
		'name' => $panel_name,
		$col => [$old_val, $new_val]
	];
	trpdd_log_user(get_current_user_id(), 'update_panel_' . $old_panel['te_type'], $changed_arr, $te_id, '', '', '', $te_id);

	if (isset($rs) && $rs === false) {
		echo '<div class="msg">Error please try againt!</div>';
	} else {
		echo '<div class="ok">updated success</div>';

		if ($vl != $old_panel[$col]) {
			if ($col == 'te_abrev' && function_exists('tr_change_abrev')) {
				tr_change_abrev($old_panel, array('te_abrev' => $vl));
			}
		}
	}

	exit;
} else if (isset($_POST['trajax']) && $_POST['trajax'] === 'delete_bk') {
	$type = sanitize_text_field($_POST['type']);
	$file = sanitize_file_name($_POST['file']);
	$bk_path = WP_CONTENT_DIR . "/backups/panels/{$type}/{$file}";

	if (!file_exists($bk_path)) {
		wp_send_json(['success' => false, 'message' => 'Backup file not found']);
	}

	if (unlink($bk_path)) {
		wp_send_json(['success' => true, 'message' => 'Backup file deleted successfully']);
	} else {
		wp_send_json(['success' => false, 'message' => 'Failed to delete backup file']);
	}
} else if ($act == 'backups') {
	$type = $_REQUEST['type'];

	$bk_folder = WP_CONTENT_DIR . '/backups/panels/' . $type;

	$files = [];
	if (file_exists($bk_folder)) {
		$file_paths = glob($bk_folder . '/*.json');
		usort($file_paths, function ($a, $b) {
			return filemtime($b) - filemtime($a);
		});

		foreach ($file_paths as $file) {
			$files[] = [
				'name' => basename($file),
				'size' => filesize($file),
				'date' => date("Y-m-d H:i:s", filemtime($file)),
				'url'  => str_replace(WP_CONTENT_DIR, content_url(), $file)
			];
		}
	}

	ob_start();
?>
	<div style="padding:10px;">
		<h3>Backup files (<?php echo $type; ?>)</h3>

		<?php if (!empty($files)) : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th>File name</th>
						<th style="width: 100px">Size</th>
						<th>Date</th>
						<th style="width: 120px">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($files as $f) : ?>
						<tr>
							<td><?php echo esc_html($f['name']); ?></td>
							<td><?php echo size_format($f['size']); ?></td>
							<td><?php echo esc_html($f['date']); ?></td>
							<td>
								<a href="<?php echo esc_url($f['url']); ?>" target="_blank" download>Download</a>
								<a href="#" class="delete-btn" style="margin-left: 10px;"
									data-file="<?php echo esc_attr($f['name']); ?>"
									data-type="<?php echo esc_attr($type); ?>">
									Delete
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em>No backup files found for this type.</em></p>
		<?php endif; ?>
	</div>
	<script>
		jQuery(function($) {
			$('.delete-btn').on('click', function() {
				if (!confirm('Are you sure you want to delete this backup file?')) return;

				const $row = $(this).closest('tr');
				const file = $(this).data('file');
				const type = $(this).data('type');
				$.ajax({
					type: 'POST',
					data: {
						trajax: 'delete_bk',
						file: file,
						type: type
					},
					success: function(res) {
						alert(res.message);
						if (res.success) {
							$row.remove();
						}
					},
					error: function() {
						alert('Error delete file');
					},
				});
			});
		});
	</script>
<?php
	$content = ob_get_clean();

	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace("class='", "class='show_popup ", $content);
	}

	echo $content;
	exit;
}
