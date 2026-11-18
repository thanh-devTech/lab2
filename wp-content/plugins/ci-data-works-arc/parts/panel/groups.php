<?php
global $wpdb;
global $is_ci_admin;
global $current_role;

wp_enqueue_media();
wp_enqueue_script('wp-color-picker');
wp_enqueue_style('wp-color-picker');
add_thickbox();

$upload_dir = tr_wp_upload_dir();
$logo_dir   = $upload_dir['basedir'] . '/logos';
$logo_url   = $upload_dir['baseurl'] . '/logos';
$pdf_dir    = $upload_dir['basedir'] . '/pdf/panel';
$pdf_url    = $upload_dir['baseurl'] . '/pdf/panel';

if (!file_exists($pdf_dir)) {
	wp_mkdir_p($pdf_dir);
}

$user       = wp_get_current_user();


$tes_type   = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? $_REQUEST['type'] : $tab;

$result_settings = get_option('ci_result_settings_' . $tes_type, array());

$fix_panel_colors = tr_get_option('ci_pdf_data', 'fix_panel_colors');
$show_panel_type = $tes_type == 'pcr' && $fix_panel_colors;

$panel_types 		= CI_Base_Panel::get_panel_types();
$panel_types_colors =  CI_Base_Panel::get_panel_types('color');

function get_abx_group_change_data($group, $oldgroupArr = null)
{
	global $wpdb;
	$result = [];
	$checked_fields = [
		'new_abx',
		'new_abx_title',
		'new_abx_date',
		'abx_type',
		'quantitative',
		'perquantitative',
		'abx_formula',
		'abx_load_high',
		'abx_y_intercept',
		'abx_slope',
		'abx_load_cutoff',
		'abx_delta',
		'abx_ct1',
		'abx_ct2',
		'abx_load1',
		'abx_load2',
	];
	$oldJson = [];

	if ($oldgroupArr && isset($oldgroupArr['tg_other_options'])) {
		$oldJson = json_decode($oldgroupArr['tg_other_options'], true) ?: [];
	}
	$newJson = json_decode($group['tg_other_options'], true);

	foreach ($newJson as $jsonKey => $jsonVal) {
		if (!in_array($jsonKey, $checked_fields, true)) {
			continue;
		}
		$oldJsonVal = $oldJson[$jsonKey] ?? '';
		if (in_array($jsonKey, ['perquantitative', 'quantitative'])) {
			$oldJsonVal = (empty($oldJsonVal) && $oldJsonVal !== 0 && $oldJsonVal !== '0') ? 0 : (int)$oldJsonVal;
			$jsonVal = (empty($jsonVal) && $jsonVal !== 0 && $jsonVal !== '0') ? 0 : (int)$jsonVal;
			if ($jsonKey === 'quantitative') {
				$oldJsonVal = $oldJsonVal ? 'Quantitative' : 'Semiquantitative';
				$jsonVal = $jsonVal ? 'Quantitative' : 'Semiquantitative';
			}
		}

		if ($oldJsonVal !== $jsonVal) {
			$result[$jsonKey] = [
				'oldValue' => $oldJsonVal,
				'newValue' => $jsonVal,
			];
		}
	}
	if (count($result) > 0) {
		$result['tg_name'] = [
			'oldValue' => $oldgroupArr ? $oldgroupArr['tg_name'] : '',
			'newValue' => $group['tg_name'],
		];
	}

	//check unmapped
	$tests = $wpdb->get_results("select te_ID, te_name, tm.meta_value
		from ci_test_panels te
		JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
		left JOIN ci_test_meta tm ON te.te_ID = tm.test_id AND (tm.meta_key = 'mkr_tests' OR tm.meta_key = 'bac_tests')
					and (tm.meta_value is not null and tm.meta_value != '' ) 
		where (tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']} )
		and te.te_in_req = 1 
		group by te.te_ID ", ARRAY_A);
	if (count($tests) > 0) {
		$result['unmapped_tests'] = array();
		foreach ($tests as $test) {
			if (empty($test['meta_value'])) {
				$result['unmapped_tests'][] = array(
					'test_id' => $test['te_ID'],
					'test_name' => $test['te_name'],
				);
			}
		}
		if (empty($result['unmapped_tests'])) {
			unset($result['unmapped_tests']);
		}
	}

	return $result;
}

if (isset($_POST['group'])) {


	$redirect_url 		= '';
	$enabled_new_abx 	= false;
	$group              = tr_pdd_stripslashes_deep($_POST['group']);
	$group['tg_name']   = trim($group['tg_name']);
	$group['tg_relate_to'] = isset($group['tg_relate_to']) && is_array($group['tg_relate_to']) ? implode(',', $group['tg_relate_to']) : '';
	$normalize_multi_ids = function ($value) {
		if (!is_array($value)) {
			$value = empty($value) ? array() : explode(',', (string) $value);
		}

		$ids = array();
		foreach ($value as $id) {
			$id = trim((string) $id);
			if ($id !== '' && $id !== '0' && ctype_digit($id)) {
				$ids[] = $id;
			}
		}

		return implode(',', array_values(array_unique($ids)));
	};

	if (!isset($group['tg_show_cli_IDs']) || !is_array($group['tg_show_cli_IDs'])) {
		$group['tg_show_cli_IDs'] = array();
	}
	if (!isset($group['tg_notshow_cli_IDs']) || !is_array($group['tg_notshow_cli_IDs'])) {
		$group['tg_notshow_cli_IDs'] = array();
	}
	$group['tg_show_cli_IDs'] = implode(',', $group['tg_show_cli_IDs']);
	$group['tg_notshow_cli_IDs'] = implode(',', $group['tg_notshow_cli_IDs']);
	$group['tg_lab_ID'] = $normalize_multi_ids(isset($group['tg_lab_ID']) ? $group['tg_lab_ID'] : array());
	$group['tg_hide_lab_ID'] = $normalize_multi_ids(isset($group['tg_hide_lab_ID']) ? $group['tg_hide_lab_ID'] : array());
	$group['tg_show_for_ref_lab_ID'] = $normalize_multi_ids(isset($group['tg_show_for_ref_lab_ID']) ? $group['tg_show_for_ref_lab_ID'] : array());
	$group['tg_notshow_for_ref_lab_ID'] = $normalize_multi_ids(isset($group['tg_notshow_for_ref_lab_ID']) ? $group['tg_notshow_for_ref_lab_ID'] : array());
	$group['tg_reflex_group_ID'] = implode(',', isset($group['tg_reflex_group_ID']) ? $group['tg_reflex_group_ID'] : array());

	if (isset($_POST['guide_pdf_remove']) && $_POST['guide_pdf_remove'] == '1') {
		if (isset($group['tg_other_options']['guide_pdf']) && !empty($group['tg_other_options']['guide_pdf'])) {
			$old_filename = basename($group['tg_other_options']['guide_pdf']);
			$old_file = $pdf_dir . '/' . $old_filename;
			if (file_exists($old_file)) {
				unlink($old_file);
			}
			$group['tg_other_options']['guide_pdf'] = '';
		}
	}
	if (
		isset($_FILES['group']['tmp_name']['tg_other_options']['guide_pdf'])
		&& !empty($_FILES['group']['tmp_name']['tg_other_options']['guide_pdf'])
	) {

		$file_name = $_FILES['group']['name']['tg_other_options']['guide_pdf'];
		$file_tmp = $_FILES['group']['tmp_name']['tg_other_options']['guide_pdf'];
		$file_error = $_FILES['group']['error']['tg_other_options']['guide_pdf'];

		if ($file_error === 0) {
			$info = pathinfo($file_name);
			$ext = isset($info['extension']) ? strtolower($info['extension']) : '';

			if ($ext === 'pdf') {
				$original_filename = sanitize_file_name($info['filename']);
				$filename = $original_filename . '.pdf';
				$filepath = $pdf_dir . '/' . $filename;

				if (file_exists($filepath)) {
					if (!empty($group['tg_ID']) && $group['tg_ID'] > 0) {
						$filename = $original_filename . '_' . $group['tg_ID'] . '.pdf';
						$filepath = $pdf_dir . '/' . $filename;
					}

					$counter = 1;
					while (file_exists($filepath)) {
						if (!empty($group['tg_ID']) && $group['tg_ID'] > 0) {
							$filename = $original_filename . '_' . $group['tg_ID'] . '_' . $counter . '.pdf';
						} else {
							$filename = $original_filename . '_' . $counter . '.pdf';
						}
						$filepath = $pdf_dir . '/' . $filename;
						$counter++;
					}
				}

				if ($group['tg_ID'] > 0) {
					$oldgroup = $wpdb->get_row("select tg_other_options from ci_test_groups where tg_ID = {$group['tg_ID']}");
					if ($oldgroup) {
						$old_options = json_decode($oldgroup->tg_other_options, true);
						if (is_array($old_options) && !empty($old_options['guide_pdf'])) {
							$old_filename = basename($old_options['guide_pdf']);
							$old_file = $pdf_dir . '/' . $old_filename;
							if (file_exists($old_file)) {
								unlink($old_file);
								error_log("Deleted old guide PDF: " . $old_file);
							}
						}
					}
				}

				if (move_uploaded_file($file_tmp, $filepath)) {
					$group['tg_other_options']['guide_pdf'] = $pdf_url . '/' . $filename;
					error_log("Guide PDF uploaded successfully. URL: " . $group['tg_other_options']['guide_pdf']);
				} else {
					error_log("Failed to move uploaded file to: " . $filepath);
				}
			}
		}
	}


	if (isset($group['tg_other_options'])) {

		if (isset($group['tg_other_options']['is_rpp']) && $group['tg_other_options']['is_rpp']) {
			$wpdb->query("alter table ci_results_pcr add column amp_score varchar(15)");
			$wpdb->query("alter table ci_results_pcr add column cq varchar(15)");
		}

		if (isset($group['tg_other_options']['percent_positive']) && $group['tg_other_options']['percent_positive']) {
			$wpdb->query("alter table ci_results_pcr add column percent_positive varchar(15)");
		}

		if (isset($group['tg_other_options']['new_abx']) && $group['tg_other_options']['new_abx']) {
			$enabled_new_abx = tr_get_option('ci_pdf_data', 'new_abx');
		}

		if (isset($group['tg_other_options']['rerun_test_id'])) {
			if (!is_array($group['tg_other_options']['rerun_test_id'])) {
				$group['tg_other_options']['rerun_test_id'] = array();
			}

			$group['tg_other_options']['rerun_test_id'] = implode(',', $group['tg_other_options']['rerun_test_id']);
		}
		if (isset($group['tg_other_options']['not_show_for_cids'])) {
			if (!is_array($group['tg_other_options']['not_show_for_cids'])) {
				$group['tg_other_options']['not_show_for_cids'] = array();
			}

			$group['tg_other_options']['not_show_for_cids'] = implode(',', $group['tg_other_options']['not_show_for_cids']);
		}
		if (isset($group['tg_other_options']['rerun_test_id_or'])) {
			if (!is_array($group['tg_other_options']['rerun_test_id_or'])) {
				$group['tg_other_options']['rerun_test_id_or'] = array();
			}

			$group['tg_other_options']['rerun_test_id_or'] = implode(',', $group['tg_other_options']['rerun_test_id_or']);
		}
		if (isset($group['tg_other_options']['rerun_specimen'])) {
			if (!is_array($group['tg_other_options']['rerun_specimen'])) {
				$group['tg_other_options']['rerun_specimen'] = array();
			}

			$group['tg_other_options']['rerun_specimen'] = implode(',', $group['tg_other_options']['rerun_specimen']);
		}
		if (isset($group['tg_other_options']['filters'])) {
			if (!is_array($group['tg_other_options']['filters'])) {
				$group['tg_other_options']['filters'] = array();
			}

			$group['tg_other_options']['filters'] = implode(',', $group['tg_other_options']['filters']);
		}

		$group['tg_other_options'] = json_encode($group['tg_other_options']);
	}


	if (is_array($_FILES) && count($_FILES) > 0) {
		foreach ($_FILES as $k => $file) {
			if (empty($file['tmp_name'])) continue;

			$filename   = $k . $file['name'];
			$filepath   = $logo_dir . '/' . $filename;
			$info       = pathinfo($filename);
			$ext        = isset($info['extension']) ? $info['extension'] : '';

			if (in_array($ext, array('jpg', 'jpeg', 'png'))) {
				if (file_exists($filepath)) {
					unlink($filepath);
				}
				if (move_uploaded_file($file['tmp_name'], $filepath)) {
					$group[$k] = $filename;
				}
			}
		}
	}
	$change_data = [];
	if ($group['tg_ID'] > 0) {
		$oldgroup = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$group['tg_ID']}");
		$oldgroupArr = $wpdb->get_row("SELECT * FROM ci_test_groups WHERE tg_ID = {$group['tg_ID']}", ARRAY_A);
		if (!$group['tg_show_req'] && $oldgroup->tg_show_req && $group['tg_parent'] == 0) {
			$group['tg_date_end'] = date_i18n('Y-m-d');
		} else if ($group['tg_show_req'] && !$oldgroup->tg_show_req && $group['tg_parent'] == 0) {
			$group['tg_date_end'] = '';
		}

		unset($group['tg_type']);
		$rs = $wpdb->update('ci_test_groups', $group, array('tg_ID' => $group['tg_ID']));
		if ($rs === false) {
			Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
		} else {
			Tr_Session_Class::add('Updated Group Successfully!');
		}

		if ($enabled_new_abx) {
			$change_data = get_abx_group_change_data($group, $oldgroupArr);
		}
		$changed_arr = array('id' => $group['tg_ID'], 'name' => $group['tg_name'], 'log_type' => 'groups');
		foreach ($group as $k => $vl) {
			$oldgroupArr[$k] = str_replace('0000-00-00', '', $oldgroupArr[$k]);
			if ($k == 'tg_other_options') {
				$old_options = !empty($oldgroupArr[$k]) ? json_decode($oldgroupArr[$k], true) : array();
				$new_options = !empty($vl) ? json_decode($vl, true) : array();
				if (!is_array($old_options)) $old_options = array();
				if (!is_array($new_options)) $new_options = array();
				$all_keys = array_unique(array_merge(array_keys($old_options), array_keys($new_options)));
				foreach ($all_keys as $option_key) {
					$old_val = isset($old_options[$option_key]) ? $old_options[$option_key] : '';
					$new_val = isset($new_options[$option_key]) ? $new_options[$option_key] : '';
					$old_val = str_replace('0000-00-00', '', $old_val);

					if ($old_val === null) $old_val = '';
					if ($new_val === null) $new_val = '';

					if ($old_val != $new_val && !($old_val == '0' && $new_val === '')) {
						$changed_arr[$option_key] = array($old_val, $new_val);
					}
				}
				continue;
			}
			if ($vl != $oldgroupArr[$k]  && !($oldgroupArr[$k] == '0' && $vl === '')) {
				$changed_arr[$k] = array($oldgroupArr[$k], $vl);
			}
		}
		trpdd_log_user(get_current_user_id(), 'update_panel_' . $tes_type, $changed_arr, $group['tg_ID'], '', '', '', $group['tg_ID']);
	} else {
		$rs = $wpdb->insert('ci_test_groups', $group);
		$group['tg_ID'] = $wpdb->insert_id;
		if ($rs === false) {
			Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
		} else {
			$editlink  = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group['tg_ID']), false);
			Tr_Session_Class::add('Added Group "' . $group['tg_name'] . '" Successfully! <a href="' . $editlink . '">Edit</a>');
		}

		if ($enabled_new_abx) {
			$change_data = get_abx_group_change_data($group);
		}
		$changed_arr = array('id' => $group['tg_ID'], 'name' => $group['tg_name'], 'log_type' => 'groups');
		trpdd_log_user(get_current_user_id(), 'insert_panel_' . $tes_type, $changed_arr, $group['tg_ID'], '', '', '', $group['tg_ID']);
	}
	if ($enabled_new_abx && !empty($change_data)) {
		if (function_exists('tr_client_send_hub')) {
			$rs = tr_client_send_hub(array(
				'action'     => 'abx_change',
				'log_type'   => 'group',
				'change_data' => json_encode($change_data),
				'by_email'   => $user->user_email,
				'id'  => $group['tg_ID']
			));
			if (isset($_GET['debug'])) {
				var_dump($rs);
			}
		}

		//reset abx cache
		if ($group['tg_parent'] == 0) {
			$wpdb->query("update {$wpdb->options} set option_value = '0'  where option_name like '%ci_abx_results_scores_%' ");
		}
	}


	if (
		in_array($tes_type, array('pcr', 'blood'))
		&& (
			(isset($changed_arr['tg_notestcheckbox']) && $changed_arr['tg_notestcheckbox'][0] == 1 && $changed_arr['tg_notestcheckbox'][1] == 0)
			|| (isset($changed_arr['tg_only_group']) && $changed_arr['tg_only_group'][0] == 0 && $changed_arr['tg_only_group'][1] == 1)
		)
	) {
		//call cron to fix tests

		if (!wp_next_scheduled('cron_all_api', array('CI_Fix_Panel_Groups', 'panel', $group['tg_ID']))) {
			wp_schedule_single_event(time() + 60, 'cron_all_api', array('CI_Fix_Panel_Groups', 'panel', $group['tg_ID']));
		}
	}


	trpdd_log_form('savetg_' . $tes_type, $group);

	if (!empty($redirect_url)) {
		echo '<meta http-equiv="refresh" content="0; url=' . $redirect_url . '">';
	}
}

if ($act == 'delete_tests' && $is_ci_admin) {
	$id = $_REQUEST['id'];

	$grgroupsoup = $wpdb->get_row("select * from ci_test_panels where tg_ID = {$id} ");
	$wpdb->delete('ci_test_panels', array('te_group_id' => $id));
	$childs = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$id}");

	foreach ($childs as $child) {
		$wpdb->delete('ci_test_groups', array('tg_ID' => $child->tg_ID));
		$wpdb->delete('ci_test_panels', array('te_group_id' => $child->tg_ID));
	}
	Tr_Session_Class::add('Deleted Group "' . $group['tg_name'] . '"');
} else if ($act == 'plate_maps') {
	include(trpdd_get_file_path('plate', 'action_plate_maps'));
} else if ($act == 'deleteall' && $is_ci_admin) {
	$wpdb->delete('ci_test_panels', array('te_type' => $tes_type));
	$wpdb->delete('ci_test_meta', array('te_type' => $tes_type));
	$wpdb->delete('ci_test_panels_cutoffs', array('cu_btype' => $tes_type));
	$wpdb->delete('ci_test_groups', array('tg_type' => $tes_type));
	$wpdb->delete('ci_test_specimens', array('ts_type' => $tes_type));
	echo 'deleted all';
	exit;
} else if ($act == 'group_export') {

	$id = $_REQUEST['id'];
	if (!empty($tes_type)) {
		ob_clean();

		$group_name = $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$id}");
		$groups_ids = array();
		$groups = $wpdb->get_results("select * from ci_test_groups 
	                        where tg_type='{$tes_type}' and (tg_ID = {$id} or tg_parent = {$id}) 
	                        order by tg_parent asc", ARRAY_A);

		foreach ($groups as $group) {
			$groups_ids[] = $group['tg_ID'];
		}
		$groups_ids_str = implode(',', $groups_ids);
		$tests = $wpdb->get_results("select * from ci_test_panels 
	                                        where te_type= '{$tes_type}' 
	                                        and te_group_id in ({$groups_ids_str}) ", ARRAY_A);
		if (!empty($wpdb->last_error)) {
			var_dump($wpdb->last_error);
			exit;
		}

		$cu_te_IDs = array();
		foreach ($tests as $test) {
			$cu_te_IDs[] = $test['te_ID'];
		}
		$cu_te_IDs_str = implode(',', $cu_te_IDs);
		$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs 
	                                where cu_btype= '{$tes_type}' 
	                                and cu_te_ID in ({$cu_te_IDs_str})", ARRAY_A);

		$specimens = $wpdb->get_results("select * from ci_test_specimens 
	                            where ts_type= '{$tes_type}' and ts_group_id in ({$groups_ids_str})  ", ARRAY_A);


		$json = array(
			'groups' => $groups,
			'tests' => $tests,
			'cutoffs' => $cutoffs,
			'specimens' => $specimens,
			'from' => 'arc',
		);

		$filename = $tes_type . '_group_' . $id . '_' . str_replace(array(' ', '.', ',', ';'), '', $group_name) . '.json';

		header('Content-disposition: attachment; filename=' . $filename);
		header('Content-type: application/json');
		wp_send_json($json);
		exit;
	}
} else if ($act == 'bulk_edit_tests') {
	$cutoffs        = array();
	$tg_id = $_GET['id'];
	if (isset($_POST['cutoffs'])) {

		$return = array('status' => 'ok');
		$tg_id = $_POST['tg_id'];
		if (empty($tg_id)) exit;

		$testids = $wpdb->get_col("select te_ID from ci_test_panels 
			where te_type = '{$tes_type}' 
			and te_group_id in (select tg_ID from ci_test_groups where tg_ID = {$tg_id} or tg_parent = {$tg_id} ) ");

		if (isset($_POST['panel']) && is_array($_POST['panel'])) {
			foreach ($_POST['panel'] as $k => $vl) {
				if (strlen($vl) > 0) {
					foreach ($testids as $tid) {
						if (in_array($k, array('te_control_by_ID', 'te_inconclusive_ID')) && $tid == $vl) {
							continue;
						}
						$wpdb->update('ci_test_panels', array($k => $vl), array('te_ID' => $tid));
					}
				}
			}
		}

		foreach ($_POST['cutoffs'] as $cut) {

			$cut['cu_ms'] = str_replace(' ,', ',', $cut['cu_ms']);
			$cut['cu_ms'] = str_replace(', ', ',', $cut['cu_ms']);
			$cut['cu_ms'] = trim($cut['cu_ms']);

			if (isset($cut['cu_cutoff'])) {
				$cut['cu_cutoff'] = trpdd_replace_symbol_db($cut['cu_cutoff']);
			}
			if (isset($cut['cu_medium'])) {
				$cut['cu_medium'] = trpdd_replace_symbol_db($cut['cu_medium']);
			}
			if (isset($cut['cu_upper_limit'])) {
				$cut['cu_upper_limit'] = trpdd_replace_symbol_db($cut['cu_upper_limit']);
			}
			if (isset($cut['cu_invalid'])) {
				$cut['cu_invalid'] = trpdd_replace_symbol_db($cut['cu_invalid']);
			}
			if (isset($cut['cu_critical_low'])) {
				$cut['cu_critical_low'] = trpdd_replace_symbol_db($cut['cu_critical_low']);
			}
			if (isset($cut['cu_critical_high'])) {
				$cut['cu_critical_high'] = trpdd_replace_symbol_db($cut['cu_critical_high']);
			}
			if (isset($cut['cu_hqc'])) {
				$cut['cu_hqc'] = trpdd_replace_symbol_db($cut['cu_hqc']);
			}

			if (empty($cut['cu_ms']) && empty($cut['cu_cutoff']) && empty($cut['cu_upper_limit'])) {
				continue;
			}

			if (empty($cut['cu_ms'])) {
				$cut['cu_ms'] = '*';
			}

			if (isset($cut['cu_age'])) {
				$cut['cu_age'] = trim($cut['cu_age']);
				$cut['cu_age'] = str_replace('  ', ' ', $cut['cu_age']);
				$cut['cu_age'] = trpdd_replace_symbol_db($cut['cu_age']);
			}



			foreach ($testids as $te_id) {

				$cut['cu_te_ID'] = $te_id;
				$cut['cu_btype'] = $tes_type;
				$cut['created'] = date_i18n('Y-m-d H:i:s');
				$cut['created_by'] = get_current_user_id();

				$exist = $wpdb->get_var("select count(*) from ci_test_panels_cutoffs
				where cu_ms = '{$cut['cu_ms']}' 
				and cu_te_ID = '{$te_id}'
				and cu_age='{$cut['cu_age']}' 
				and cu_gender='{$cut['cu_gender']}' ");

				if ($cut['cu_upper_limit'] == 'delete') {
					$rs = $wpdb->delete('ci_test_panels_cutoffs', array(
						'cu_te_ID' => $te_id,
						'cu_ms' => $cut['cu_ms'],
						'cu_age' => $cut['cu_age'],
						'cu_gender' => $cut['cu_gender'],
					));
				} else if ($exist) {
					$rs = $wpdb->update('ci_test_panels_cutoffs', $cut, array(
						'cu_te_ID' => $te_id,
						'cu_ms' => $cut['cu_ms'],
						'cu_age' => $cut['cu_age'],
						'cu_gender' => $cut['cu_gender'],
					));
				} else {
					$rs = $wpdb->insert('ci_test_panels_cutoffs', $cut);
				}

				if ($rs === false) {
					var_dump($wpdb->last_error);
				}

				if (!empty($cut['cu_ms']) && $cut['cu_ms'] != '*' && $cut['cu_ms'] != 'all') {
					$exist = $wpdb->get_var("select count(*) from ci_instruments where instrument = '{$cut['cu_ms']}' ");
					if (!$exist) {
						$wpdb->insert('ci_instruments', array('instrument' => $cut['cu_ms']));
					}
				}
			}
		}
		wp_send_json($return);
	}


	$testsingroup = $wpdb->get_results("select te_ID, te_name from ci_test_panels
		                            where te_group_id = {$tg_id}
		                            ");
?>
	<form method="post" id="buildeditform">
		<?php include('cutoffs.php'); ?>
		<br>
		<div class="frow">
			<div class="col4">
				<label>Flag type:</label>
				<select name="panel[te_is_detected]">
					<?php
					echo '<option value="">Select Flag Type</option>';
					foreach (CI_Base_Panel::getPosNegType($tes_type) as $k => $txt) {
						echo '<option value="' . $k . '" >' . $txt . '</option>';
					}
					?>
				</select>
			</div>
			<div class="col4">
				<label title="Control Negative => Test Invalid">Test Control Invalid:</label>
				<select name="panel[te_control_by_ID]">
					<option value="">Select Test</option>
					<?php
					foreach ($testsingroup as $teg) {
						$selected = $teg->te_ID == $panel['te_control_by_ID'] ? 'selected' : '';
						echo '<option value="' . $teg->te_ID . '" ' . $selected . '>' . $teg->te_name . '</option>';
					}
					?>
				</select>
			</div>
			<div class="col4">
				<label>Test Control INCONCLUSIVE:</label>
				<select name="panel[te_inconclusive_ID]">
					<option value="">Select Test</option>
					<?php
					$testsingroup[] = (object)array('te_ID' => -1, 'te_name' => 'byself');
					foreach ($testsingroup as $teg) {
						$selected = $teg->te_ID == $panel['te_inconclusive_ID'] ? 'selected' : '';
						echo '<option value="' . $teg->te_ID . '" ' . $selected . '>' . $teg->te_name . '</option>';
					}
					?>
				</select>
			</div>
		</div>
		<br>
		<div class="frow"><input type="submit" class="button" value="Submit" /></div>
		<input type="hidden" name="act" value="<?php echo $act ?>" />
		<input type="hidden" name="trajax" value="<?php echo $act ?>" />
		<input type="hidden" name="tg_id" value="<?php echo $_GET['id'] ?>" />
	</form>
	<script>
		jQuery(function($) {
			$('#buildeditform').on('submit', function() {
				$.ajax({
					url: '<?php Tr_Base_Class_V6::link(array('tg_id' => $_GET['id'])) ?>',
					type: 'post',
					dataType: 'json',
					data: $(this).serialize(),
					success: function(json) {
						alert('Successfully');
					}
				});
				return false;
			})
		})
	</script>
<?php
	exit;
} else if ($act == 'viewdemopdf') {
	include(trpdd_get_file_path('panel', 'viewdemopdf'));
	exit;
} else if ($act == 'duplicate') {
	$id = $_REQUEST['id'];
	$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$id} ", ARRAY_A);
	$new_position = $wpdb->get_var("select max(tg_position) from ci_test_groups where tg_type = '{$tes_type}'");
	$group_maps = array();
	$group_reflex = array();

	$new_position = $new_position + 1;
	if ($group) {
		$old_group_id = $group['tg_ID'];
		unset($group['tg_ID']);

		$group['tg_name'] .= ' (Copy)';
		$group['tg_show_req'] = 0;
		$group['tg_position'] = $new_position++;


		$wpdb->insert('ci_test_groups', $group);
		$new_group_id = $wpdb->insert_id;

		if ($group['tg_reflex_group_ID']) {
			$group_reflex[$new_group_id] = $group['tg_reflex_group_ID'];
		}

		$group_maps[$old_group_id] = $new_group_id;

		//check sub groups
		$subs = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$id} order by tg_position asc", ARRAY_A);
		foreach ($subs as $sub) {
			$old_sub_id = $sub['tg_ID'];
			unset($sub['tg_ID']);
			//$sub['tg_name'] .= ' (Copy)';
			$sub['tg_parent'] = $new_group_id;
			$sub['tg_position'] = $new_position++;
			$wpdb->insert('ci_test_groups', $sub);
			$new_sub_id = $wpdb->insert_id;

			if ($sub['tg_reflex_group_ID']) {
				$group_reflex[$new_sub_id] = $sub['tg_reflex_group_ID'];
			}
			$group_maps[$old_sub_id] = $new_sub_id;

			$tests = $wpdb->get_results("select * from ci_test_panels where te_group_id = {$old_sub_id}", ARRAY_A);
			foreach ($tests as $test) {
				$insert = $test;
				unset($insert['te_ID']);
				$insert['te_group_id'] = $new_sub_id;
				$wpdb->insert('ci_test_panels', $insert);
				$new_test_id = $wpdb->insert_id;

				$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_btype= '{$tes_type}'
	                        and cu_te_ID = {$test['te_ID']}", ARRAY_A);
				foreach ($cutoffs as $cutoff) {
					$cutoff['cu_te_ID'] = $new_test_id;
					$wpdb->insert('ci_test_panels_cutoffs', $cutoff);
				}
			}
		}

		$tests = $wpdb->get_results("select * from ci_test_panels where te_group_id = {$id}", ARRAY_A);
		foreach ($tests as $test) {
			$insert = $test;
			unset($insert['te_ID']);
			$insert['te_group_id'] = $new_group_id;
			$wpdb->insert('ci_test_panels', $insert);
			$new_test_id = $wpdb->insert_id;

			$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_btype= '{$tes_type}'
	                        and cu_te_ID = {$test['te_ID']}", ARRAY_A);
			foreach ($cutoffs as $cutoff) {
				$cutoff['cu_te_ID'] = $new_test_id;
				$wpdb->insert('ci_test_panels_cutoffs', $cutoff);
			}
		}

		foreach ($group_reflex as $new_id => $reflex_group_id) {
			if (!empty($reflex_group_id)) {
				$new_reflex_group_ids = array();
				foreach (explode(',', $reflex_group_id) as $rgid) {
					if (isset($group_maps[$rgid])) {
						$new_reflex_group_ids[] = $group_maps[$rgid];
					}
				}
				$new_reflex_group_ids_str = implode(',', $new_reflex_group_ids);
				$wpdb->update('ci_test_groups', array('tg_reflex_group_ID' => $new_reflex_group_ids_str), array('tg_ID' => $new_id));
			}
		}

		Tr_Session_Class::add('Duplicated Group "' . $group['tg_name'] . '" Successfully!');
	}
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $new_group_id), false));
	exit;
} else if ($act == 'copytest') {
	if (isset($_POST['group_to']) && $_POST['group_to'] > 0) {
		$group_id = $_GET['id'];
		$tests = $wpdb->get_results("select * from ci_test_panels where te_group_id = {$group_id}", ARRAY_A);
		foreach ($tests as $test) {
			$insert = $test;
			unset($insert['te_ID']);
			$insert['te_group_id'] = $_POST['group_to'];

			$cutoffs = $wpdb->get_results("select * from ci_test_panels_cutoffs where cu_btype= '{$tes_type}'
	                        and cu_te_ID = {$test['te_ID']}", ARRAY_A);


			//exit;
			$wpdb->insert('ci_test_panels', $insert);
			$new_id = $wpdb->insert_id;
			foreach ($cutoffs as $cutoff) {
				$cutoff['cu_te_ID'] = $new_id;
				$wpdb->insert('ci_test_panels_cutoffs', $cutoff);
			}
		}
	}
?>
	<form method="post">
		<h2>Duplicate Tests</h2>
		<div class="frow">
			<label>From Group: </label>
			<?php echo $_GET['from'] ?>
		</div>
		<div class="frow">
			<label>Group to: </label>
			<select name="group_to" required="">
				<option value="">Select</option>
				<?php
				$rsgroups = $wpdb->get_results("select *,
				        IF(t.tg_parent>0,(select t2.tg_name from ci_test_groups t2 where t2.tg_ID = t.tg_parent limit 1),'') as parent_name
				        from ci_test_groups t
				        where tg_type='{$tes_type}' 
				        order by tg_name asc");

				foreach ($rsgroups as $group) {
					if (!empty($group->parent_name)) {
						$group->tg_name .= ' ( ' . $group->parent_name . ' )';
					}

					echo '<option value="' . $group->tg_ID . '">' . $group->tg_name . '</option>';
				}
				?>
			</select>
		</div>
		<div class="frow">
			<input type="submit" class="button" name="Copy">
		</div>
	</form>
<?php
} else if ($act == 'plate_layout') {
	if (!$is_ci_admin) {
		echo 'You do not have permission to access this page.';
		exit;
	}
	include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

	$plate_styles = trpdd_list_plate_styles($tes_type);
	$all_labs = CI_Base_Lab::get_all_labs();
	$labs = CI_Base_Lab::get_labs();
	$ref_labs = CI_Base_Lab::get_ref_labs();
	$lab_names = array();
	foreach ($all_labs as $lab) {
		$lab_label = $lab->lab_name;
		if (!empty($lab->lab_dba_init)) {
			$lab_label .= ' - ' . $lab->lab_dba_init;
		}
		$lab_names[$lab->lab_ID] = $lab_label;
	}
	$plate_layout_options = array(array('id' => '', 'text' => 'None'));
	foreach ($plate_styles as $plate_id => $plate_name) {
		$plate_layout_options[] = array('id' => (string) $plate_id, 'text' => $plate_name);
	}
	$lab_options = array();
	foreach ($labs as $lab) {
		$lab_label = $lab->lab_name;
		if (!empty($lab->lab_dba_init)) {
			$lab_label .= ' - ' . $lab->lab_dba_init;
		}
		$lab_options[] = array('id' => (string) $lab->lab_ID, 'text' => $lab_label);
	}
	$ref_lab_options = array(array('id' => '', 'text' => 'No change'));
	foreach ($ref_labs as $ref_lab) {
		$ref_lab_options[] = array('id' => (string) $ref_lab->lab_ID, 'text' => $ref_lab->lab_name);
	}

	if (isset($_POST['plate_layout']) && is_array($_POST['plate_layout'])) {
		$post_plate_layout = tr_pdd_stripslashes_deep($_POST['plate_layout']);
		foreach ($post_plate_layout as $group_id => $plate_data) {
			$group_id = (int) $group_id;
			if ($group_id <= 0 || !is_array($plate_data)) {
				continue;
			}

			$old_group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$group_id} and tg_type='{$tes_type}' and tg_parent = 0", ARRAY_A);
			if (empty($old_group)) {
				continue;
			}

			$show_lab_ids = isset($plate_data['tg_lab_ID']) ? $plate_data['tg_lab_ID'] : array();
			if (!is_array($show_lab_ids)) {
				$show_lab_ids = $show_lab_ids !== '' ? explode(',', (string) $show_lab_ids) : array();
			}
			$show_lab_ids = array_filter(array_map('intval', $show_lab_ids));
			$show_lab_ids = array_values(array_unique($show_lab_ids));

			$other_options = !empty($old_group['tg_other_options']) ? json_decode($old_group['tg_other_options'], true) : array();
			if (!is_array($other_options)) {
				$other_options = array();
			}
			$other_options['rack_name'] = isset($plate_data['rack_name']) ? trim($plate_data['rack_name']) : '';
			$other_options['rack_name2'] = isset($plate_data['rack_name2']) ? trim($plate_data['rack_name2']) : '';

			$update_data = array(
				'tg_plate_style' => isset($plate_data['tg_plate_style']) ? (int) $plate_data['tg_plate_style'] : 0,
				'tg_plate_style2' => isset($plate_data['tg_plate_style2']) ? (int) $plate_data['tg_plate_style2'] : 0,
				'tg_lab_ID' => implode(',', $show_lab_ids),
				'tg_ref_lab_ID' => isset($plate_data['tg_ref_lab_ID']) ? (int) $plate_data['tg_ref_lab_ID'] : 0,
				'tg_other_options' => json_encode($other_options),
			);

			$changed_arr = array('id' => $group_id, 'name' => $old_group['tg_name'], 'log_type' => 'groups');
			foreach ($update_data as $field => $new_value) {
				$old_value = isset($old_group[$field]) ? $old_group[$field] : '';
				if ($field == 'tg_other_options') {
					$old_options = !empty($old_value) ? json_decode($old_value, true) : array();
					if (!is_array($old_options)) {
						$old_options = array();
					}
					foreach (array('rack_name', 'rack_name2') as $option_key) {
						$old_option_value = isset($old_options[$option_key]) ? $old_options[$option_key] : '';
						$new_option_value = isset($other_options[$option_key]) ? $other_options[$option_key] : '';
						if ($old_option_value != $new_option_value) {
							$changed_arr[$option_key] = array($old_option_value, $new_option_value);
						}
					}
					continue;
				}
				if ($old_value != $new_value) {
					$changed_arr[$field] = array($old_value, $new_value);
				}
			}

			$rs = $wpdb->update('ci_test_groups', $update_data, array('tg_ID' => $group_id));
			if ($rs === false) {
				Tr_Session_Class::add_error('Error updating group #' . $group_id . '. ' . $wpdb->last_error);
			} else if (count($changed_arr) > 3) {
				trpdd_log_user(get_current_user_id(), 'update_panel_' . $tes_type, $changed_arr, $group_id, '', '', '', $group_id);
			}
		}
		Tr_Session_Class::add('Updated Plate Layout successfully.');
	}

	$groups = $wpdb->get_results("select *,
			(select count(*) from ci_test_panels where te_group_id = tg_ID) as count_tests
		from ci_test_groups
		where tg_type='{$tes_type}'
			and tg_parent = 0
			and tg_show_req = 1
		order by tg_plate_style asc, tg_position asc");

	$plate_usage = array();
	foreach ($groups as $group) {
		$group->other_options = !empty($group->tg_other_options) ? json_decode($group->tg_other_options, true) : array();
		if (!is_array($group->other_options)) {
			$group->other_options = array();
		}

		$show_lab_ids = !empty($group->tg_lab_ID) ? explode(',', $group->tg_lab_ID) : array();
		$hide_lab_ids = !empty($group->tg_hide_lab_ID) ? explode(',', $group->tg_hide_lab_ID) : array();
		$show_ref_lab_ids = !empty($group->tg_show_for_ref_lab_ID) ? explode(',', $group->tg_show_for_ref_lab_ID) : array();
		$hide_ref_lab_ids = !empty($group->tg_notshow_for_ref_lab_ID) ? explode(',', $group->tg_notshow_for_ref_lab_ID) : array();

		$show_labs = array();
		foreach ($show_lab_ids as $lab_id) {
			$lab_id = trim($lab_id);
			if ($lab_id !== '' && isset($lab_names[$lab_id])) {
				$show_labs[] = $lab_names[$lab_id];
			}
		}
		$hide_labs = array();
		foreach ($hide_lab_ids as $lab_id) {
			$lab_id = trim($lab_id);
			if ($lab_id !== '' && isset($lab_names[$lab_id])) {
				$hide_labs[] = $lab_names[$lab_id];
			}
		}
		$show_ref_labs = array();
		foreach ($show_ref_lab_ids as $lab_id) {
			$lab_id = trim($lab_id);
			if ($lab_id !== '' && isset($lab_names[$lab_id])) {
				$show_ref_labs[] = $lab_names[$lab_id];
			}
		}
		$hide_ref_labs = array();
		foreach ($hide_ref_lab_ids as $lab_id) {
			$lab_id = trim($lab_id);
			if ($lab_id !== '' && isset($lab_names[$lab_id])) {
				$hide_ref_labs[] = $lab_names[$lab_id];
			}
		}

		$lab_rules = array();
		if (!empty($show_labs)) {
			$lab_rules[] = 'Show: ' . implode(', ', $show_labs);
		}
		if (!empty($hide_labs)) {
			$lab_rules[] = 'Hide: ' . implode(', ', $hide_labs);
		}
		if (empty($lab_rules)) {
			$lab_rules[] = 'All labs';
		}

		$ref_lab_rules = array();
		if (!empty($group->tg_ref_lab_ID) && isset($lab_names[$group->tg_ref_lab_ID])) {
			$ref_lab_rules[] = 'Change to: ' . $lab_names[$group->tg_ref_lab_ID];
		}
		if (!empty($show_ref_labs)) {
			$ref_lab_rules[] = 'Only show: ' . implode(', ', $show_ref_labs);
		}
		if (!empty($hide_ref_labs)) {
			$ref_lab_rules[] = 'Hide: ' . implode(', ', $hide_ref_labs);
		}
		if (empty($ref_lab_rules)) {
			$ref_lab_rules[] = 'No ref lab rule';
		}

		$plate_slots = array(
			1 => array(
				'style' => (int) $group->tg_plate_style,
				'title' => isset($group->other_options['rack_name']) ? $group->other_options['rack_name'] : '',
			),
			2 => array(
				'style' => (int) $group->tg_plate_style2,
				'title' => isset($group->other_options['rack_name2']) ? $group->other_options['rack_name2'] : '',
			),
		);
		foreach ($plate_slots as $slot_num => $slot) {
			if ($slot['style'] <= 0) {
				continue;
			}
			if (!isset($plate_usage[$slot['style']])) {
				$plate_usage[$slot['style']] = array(
					'panels' => array(),
					'lab_rules' => array(),
					'ref_lab_rules' => array(),
					'config' => trpdd_plate_map_config($slot['style']),
				);
			}
			$panel_title = !empty($slot['title']) ? $slot['title'] : $group->tg_name;
			$plate_usage[$slot['style']]['panels'][] = $panel_title . ' (#' . $slot_num . ')';
			$plate_usage[$slot['style']]['lab_rules'] = array_merge($plate_usage[$slot['style']]['lab_rules'], $lab_rules);
			$plate_usage[$slot['style']]['ref_lab_rules'] = array_merge($plate_usage[$slot['style']]['ref_lab_rules'], $ref_lab_rules);
		}
	}
	ksort($plate_usage);
?>
	<div class="wrap ci-plate-layout-page">
		<h2>Plate Layout
			<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a>
			<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
			<a class="add-new-h2" target="_plates" href="<?php Tr_Base_Class_V6::link(array('act' => 'plate_maps', 'trajax' => 1, 'width' => '1000')) ?>">maps</a>
		</h2>

		<?php Tr_Session_Class::show() ?>

		<form method="post">
			<div class="wraptbl">
				<table class="wp-list-table widefat striped posts blue_tbl hover_tbl ci-plate-layout-table">
					<thead>
						<tr>
							<th class="ci-nowrap" style="width:50px">#</th>
							<th class="ci-plate-name">Name</th>
							<th class="ci-plate-wide">Plate Layout #1</th>
							<th style="width:180px">Title #1</th>
							<th class="ci-plate-wide">Plate Layout #2</th>
							<th style="width:180px">Title #2</th>
							<th class="ci-plate-rules">Lab</th>
							<th class="ci-plate-rules">Ref Lab</th>
							<th class="ci-nowrap" style="width:90px">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($groups as $group) {
							$layout1 = !empty($group->tg_plate_style) && isset($plate_styles[$group->tg_plate_style]) ? $plate_styles[$group->tg_plate_style] : '';
							$layout2 = !empty($group->tg_plate_style2) && isset($plate_styles[$group->tg_plate_style2]) ? $plate_styles[$group->tg_plate_style2] : '';
							if (empty($layout1) && !empty($group->tg_plate_style)) {
								$layout1 = '#' . $group->tg_plate_style;
							}
							if (empty($layout2) && !empty($group->tg_plate_style2)) {
								$layout2 = '#' . $group->tg_plate_style2;
							}
							$title1 = isset($group->other_options['rack_name']) ? $group->other_options['rack_name'] : '';
							$title2 = isset($group->other_options['rack_name2']) ? $group->other_options['rack_name2'] : '';
							$group_lab = array();
							if (!empty($group->tg_lab_ID)) {
								foreach (explode(',', $group->tg_lab_ID) as $lab_id) {
									$lab_id = trim($lab_id);
									if ($lab_id !== '' && isset($lab_names[$lab_id])) {
										$group_lab[] = $lab_names[$lab_id];
									}
								}
							}
							$group_ref_lab = !empty($group->tg_ref_lab_ID) && isset($lab_names[$group->tg_ref_lab_ID]) ? $lab_names[$group->tg_ref_lab_ID] : '';
							$selected_lab_ids = !empty($group->tg_lab_ID) ? explode(',', $group->tg_lab_ID) : array();
							$layout1_display = !empty($layout1) ? $layout1 : 'None';
							$layout2_display = !empty($layout2) ? $layout2 : 'None';
							$group_lab_display = !empty($group_lab) ? implode(', ', $group_lab) : 'All labs';
							$group_ref_lab_display = !empty($group_ref_lab) ? $group_ref_lab : 'No change';
						?>
							<tr data-id="<?php echo $group->tg_ID ?>" class="<?php if (!$group->tg_show_req) echo 'disabled'; ?>">
								<td style="border-left:4px solid <?php echo esc_attr($group->tg_bgcolor) ?>;"><?php echo $group->tg_ID ?></td>
								<td class="ci-plate-name">
									<a class="tg_name bold" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group->tg_ID)) ?>"><?php echo esc_html($group->tg_name) ?></a>
									<div class="row-actions">
										<a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=<?php echo esc_attr($tes_type) ?>&gr=<?php echo $group->tg_ID ?>"><?php echo (int) $group->count_tests ?> Tests</a>
									</div>
								</td>
								<td class="ci-lazy-select2-cell" data-source="plate">
									<input type="hidden" name="plate_layout[<?php echo $group->tg_ID ?>][tg_plate_style]" value="<?php echo esc_attr($group->tg_plate_style) ?>" />
									<span class="ci-lazy-select2-display" title="<?php echo esc_attr($layout1_display) ?>"><?php echo esc_html($layout1_display) ?></span>
								</td>
								<td><input type="text" name="plate_layout[<?php echo $group->tg_ID ?>][rack_name]" class="ci-plate-edit-control" value="<?php echo esc_attr($title1) ?>" /></td>
								<td class="ci-lazy-select2-cell" data-source="plate">
									<input type="hidden" name="plate_layout[<?php echo $group->tg_ID ?>][tg_plate_style2]" value="<?php echo esc_attr($group->tg_plate_style2) ?>" />
									<span class="ci-lazy-select2-display" title="<?php echo esc_attr($layout2_display) ?>"><?php echo esc_html($layout2_display) ?></span>
								</td>
								<td><input type="text" name="plate_layout[<?php echo $group->tg_ID ?>][rack_name2]" class="ci-plate-edit-control" value="<?php echo esc_attr($title2) ?>" /></td>
								<td class="ci-lazy-select2-cell" data-source="lab" data-multiple="1">
									<input type="hidden" name="plate_layout[<?php echo $group->tg_ID ?>][tg_lab_ID]" value="<?php echo esc_attr(implode(',', $selected_lab_ids)) ?>" />
									<span class="ci-lazy-select2-display" title="<?php echo esc_attr($group_lab_display) ?>"><?php echo esc_html($group_lab_display) ?></span>
								</td>
								<td class="ci-lazy-select2-cell" data-source="ref_lab">
									<input type="hidden" name="plate_layout[<?php echo $group->tg_ID ?>][tg_ref_lab_ID]" value="<?php echo esc_attr($group->tg_ref_lab_ID) ?>" />
									<span class="ci-lazy-select2-display" title="<?php echo esc_attr($group_ref_lab_display) ?>"><?php echo esc_html($group_ref_lab_display) ?></span>
								</td>
								<td><a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group->tg_ID)) ?>">Edit</a></td>
							</tr>
						<?php } ?>
						<?php if (empty($groups)) { ?>
							<tr>
								<td colspan="9">No active groups found.</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
			<?php if (!empty($groups)) { ?>
				<p style="margin-top:14px;">
					<input type="submit" class="button button-primary" value="Save Plate Layout">
				</p>
			<?php } ?>
		</form>
		<script>
			jQuery(function($) {
				var ciPlateLayoutOptions = {
					plate: <?php echo json_encode($plate_layout_options) ?>,
					lab: <?php echo json_encode($lab_options) ?>,
					ref_lab: <?php echo json_encode($ref_lab_options) ?>
				};

				function ciGetLazyText(options, values, emptyText) {
					var text = [];
					values = $.isArray(values) ? values : [values + ''];
					$.each(options, function(i, option) {
						if ($.inArray(option.id + '', values) !== -1 && option.text !== emptyText) {
							text.push(option.text);
						}
					});

					return text.length ? text.join(', ') : emptyText;
				}

				$('.ci-plate-layout-page').on('click', '.ci-lazy-select2-cell', function() {
					var $cell = $(this);
					if ($cell.find('select').length) {
						return;
					}

					var source = $cell.data('source');
					var options = ciPlateLayoutOptions[source] || [];
					var multiple = $cell.data('multiple') == 1;
					var $hidden = $cell.find('input[type="hidden"]');
					var currentValue = $hidden.val();
					var values = multiple && currentValue ? currentValue.split(',') : currentValue;
					var $select = $('<select class="ci-plate-edit-control"></select>');

					if (multiple) {
						$select.attr('multiple', 'multiple');
					}

					$.each(options, function(i, option) {
						var $option = $('<option></option>').attr('value', option.id).text(option.text);
						if (multiple && $.inArray(option.id + '', values) !== -1) {
							$option.prop('selected', true);
						} else if (!multiple && option.id + '' === currentValue + '') {
							$option.prop('selected', true);
						}
						$select.append($option);
					});

					$cell.find('.ci-lazy-select2-display').hide();
					$cell.append($select);
					$select.select2({
						width: '100%'
					}).on('change', function() {
						var selectedValue = $(this).val();
						var emptyText = source === 'lab' ? 'All labs' : (source === 'ref_lab' ? 'No change' : 'None');
						var displayText;

						if (multiple) {
							selectedValue = selectedValue || [];
							$hidden.val(selectedValue.join(','));
							displayText = ciGetLazyText(options, selectedValue, emptyText);
						} else {
							selectedValue = selectedValue || '';
							$hidden.val(selectedValue);
							displayText = ciGetLazyText(options, selectedValue, emptyText);
						}

						$cell.find('.ci-lazy-select2-display')
							.text(displayText)
							.attr('title', displayText);
					}).on('select2:close', function() {
						var $currentSelect = $(this);
						setTimeout(function() {
							$currentSelect.select2('destroy').remove();
							$cell.find('.ci-lazy-select2-display').show();
						}, 150);
					});

					$select.select2('open');
				});
			});
		</script>

		<br>
		<h2>Plate Layout Summary</h2>
		<div class="wraptbl">
			<table class="wp-list-table widefat striped posts blue_tbl hover_tbl ci-plate-layout-summary">
				<thead>
					<tr>
						<th class="ci-nowrap" style="width:50px">#</th>
						<th class="ci-plate-wide">Plate Layout</th>
						<th class="ci-plate-list">Panels</th>
						<th style="width:180px">Plate Lab</th>
						<th class="ci-nowrap" style="width:110px">Type/Rack</th>
						<th class="ci-nowrap" style="width:110px">Well/Patient</th>
						<th class="ci-nowrap" style="width:70px">Max</th>
						<th class="ci-plate-list">Controls / Parts</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($plate_usage as $plate_id => $usage) {
						$config = is_array($usage['config']) ? $usage['config'] : array();
						$parts = array();
						if (isset($config['parts']) && is_array($config['parts'])) {
							foreach ($config['parts'] as $part_key => $part_name) {
								if (is_array($part_name)) {
									$part_name = implode(' / ', $part_name);
								}
								$parts[] = $part_key . ': ' . $part_name;
							}
						}
						$controls = array();
						if (!empty($config['ntc'])) {
							$controls[] = (!empty($config['ntc_text']) ? $config['ntc_text'] : 'NTC') . ': ' . $config['ntc'];
						}
						if (!empty($config['pos'])) {
							$controls[] = (!empty($config['pos_text']) ? $config['pos_text'] : 'POS') . ': ' . $config['pos'];
						}
						if (!empty($config['nec'])) {
							$controls[] = 'NEC: ' . $config['nec'];
						}
						if (!empty($config['blank'])) {
							$controls[] = 'Blank: ' . $config['blank'];
						}
						if (!empty($parts)) {
							$controls[] = 'Parts: ' . implode(', ', $parts);
						}
						$type_rack = array();
						if (!empty($config['type'])) {
							$type_rack[] = 'Type ' . $config['type'];
						}
						if (!empty($config['rack'])) {
							$type_rack[] = 'Rack ' . $config['rack'];
						}
						$panel_lines = array_map('esc_html', array_unique($usage['panels']));
						$lab_rule_lines = array_map('esc_html', array_unique($usage['lab_rules']));
						$ref_lab_rule_lines = array_map('esc_html', array_unique($usage['ref_lab_rules']));
						$control_lines = array_map('esc_html', $controls);
					?>
						<tr>
							<td><?php echo (int) $plate_id ?></td>
							<td><?php echo isset($plate_styles[$plate_id]) ? esc_html($plate_styles[$plate_id]) : '#' . (int) $plate_id ?></td>
							<td><?php echo implode('<br>', $panel_lines) ?></td>
							<td><?php echo !empty($config['lab']) ? esc_html($config['lab']) : '-' ?></td>
							<td><?php echo !empty($type_rack) ? esc_html(implode(' / ', $type_rack)) : '-' ?></td>
							<td><?php echo !empty($config['wellperpatient']) ? esc_html($config['wellperpatient']) : '-' ?></td>
							<td><?php echo !empty($config['max']) ? esc_html($config['max']) : '-' ?></td>
							<td><?php echo !empty($control_lines) ? implode('<br>', $control_lines) : '-' ?></td>
						</tr>
					<?php } ?>
					<?php if (empty($plate_usage)) { ?>
						<tr>
							<td colspan="10">No plate layout usage found.</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php
} else if ($act == 'add' || $act == 'edit') {
	include(trpdd_get_file_path('panel', 'group_edit'));
} else {
	if (isset($_POST['position'])) {
		foreach ($_POST['position'] as $grid => $pos) {
			$show   = isset($_POST['show_req'][$grid]) ? $_POST['show_req'][$grid] : 0;

			$group  = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$grid}");

			$update_data = array(
				'tg_position' => $pos,
				'tg_show_req' => $show,
			);

			if ($is_ci_admin && isset($_POST['tg_cpt_code'][$grid])) {
				$update_data['tg_cpt_code'] = $_POST['tg_cpt_code'][$grid];
			}

			if ($is_ci_admin && isset($_POST['tg_panel_type'][$grid])) {
				$update_data['tg_panel_type'] = $_POST['tg_panel_type'][$grid];
			}

			if (!$show && $group->tg_show_req) {
				$update_data['tg_date_end'] = date_i18n('Y-m-d');
			} else if ($show && !$group->tg_show_req) {
				$update_data['tg_date_end'] = '';
			}
			$changed_arr = [
				'id'       => $grid,
				'name'     => $group->tg_name,
				'log_type' => 'groups',
			];
			$fields_to_check = [
				'tg_position' => [$group->tg_position, $pos],
				'tg_show_req' => [$group->tg_show_req, $show],
				'tg_cpt_code' => [$group->tg_cpt_code, $_POST['tg_cpt_code'][$grid] ?? $group->tg_cpt_code],
				'tg_panel_type' => [$group->tg_panel_type, $_POST['tg_panel_type'][$grid] ?? $group->tg_panel_type],
			];

			foreach ($fields_to_check as $key => [$old, $new]) {
				if ($old != $new) {
					$changed_arr[$key] = [$old, $new];
				}
			}

			if (count($changed_arr) > 3) {
				trpdd_log_user(get_current_user_id(), 'update_panel_' . $tes_type, $changed_arr, $grid, '', '', '', $grid);
			}
			$wpdb->update('ci_test_groups', $update_data, array('tg_ID' => $grid));
		}
	} else if ($act == 'deletegroup') {
		$id = $_REQUEST['tg_ID'];
		$group = $wpdb->get_row("select *,
                                (select count(*) from ci_test_panels where te_group_id = tg_ID) as count_tests
                                from ci_test_groups where tg_ID = {$id} ");

		if ($group->count_tests > 0 && !isset($_GET['debug'])) {
			Tr_Session_Class::add('Can\'t delete group');
		} else {
			$wpdb->delete('ci_test_groups', array('tg_ID' => $id));
			$wpdb->delete('ci_test_panels', array('te_group_id' => $id));
			$childs = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$id}");

			foreach ($childs as $child) {
				$wpdb->delete('ci_test_groups', array('tg_ID' => $child->tg_ID));
				$wpdb->delete('ci_test_panels', array('te_group_id' => $child->tg_ID));
			}
			Tr_Session_Class::add('Deleted Group "' . $group->tg_name . '"');
		}
	}

	$query_count = '(select count(*) from ci_test_panels where te_group_id = tg_ID) as count_tests';
	if ($tes_type == 'req') {
		$query_count = "
		(select count(*) from ci_test_panels where te_group_id = tg_ID and te_type='blood') as count_tests_blood,
		(select count(*) from ci_test_panels where te_group_id = tg_ID and te_type='pcr') as count_tests_pcr";
	}
	$sql = "select *,
	            {$query_count}
            from ci_test_groups tg 
            where tg_type='{$tes_type}' 
                and tg_parent=0 
            order by tg_show_req desc, tg_position asc";

	$groups = $wpdb->get_results($sql);

	//fix panel colors
	if ($act == 'auto_panel_colors') {
		if ($fix_panel_colors && $tes_type == 'pcr') {

			$group_type_colors	= array();
			foreach ($groups as $group) {

				$group->other_options = !empty($group->tg_other_options) ? json_decode($group->tg_other_options, true) : array();
				$group->abx_type = isset($group->other_options['abx_type']) ? $group->other_options['abx_type'] : '';

				if (empty($group->tg_panel_type) && !empty($group->abx_type)) {
					$group->tg_panel_type = $group->abx_type;
					if (in_array($group->tg_panel_type, array('sr', 'sm', 'sl', 'sp', 'es'))) {
						$group->tg_panel_type = 'sti';
					}
					$wpdb->update('ci_test_groups', array('tg_panel_type' => $group->tg_panel_type), array('tg_ID' => $group->tg_ID));
				}
				if (!empty($group->tg_panel_type)) {
					if (!isset($group_type_colors[$group->tg_panel_type])) {
						$group_type_colors[$group->tg_panel_type] = array();
					}
					$group_type_colors[$group->tg_panel_type][] = array(
						'id' => $group->tg_ID,
						'name' => $group->tg_name,
						'color' => $group->tg_bgcolor,
					);
				}
			}

			//fix colors
			if (!empty($group_type_colors)) {
				foreach ($group_type_colors as $panel_type => $listpns) {
					$main_color = isset($panel_types_colors[$panel_type]) ? $panel_types_colors[$panel_type] : '';
					if (empty($main_color)) {
						continue;
					}
					$old_main_color = $listpns[0]['color'];

					if ($main_color && $main_color != $old_main_color) {
						$wpdb->update('ci_test_groups', array('tg_bgcolor' => $main_color), array('tg_ID' => $listpns[0]['id']));
						Tr_Session_Class::add('Updated color of group "' . $listpns[0]['name'] . '" to ' . $main_color);
						//update color to $groups
						foreach ($groups as $group) {
							if ($group->tg_ID == $listpns[0]['id']) {
								$group->tg_bgcolor = $main_color;
								break;
							}
						}
					}

					if (count($listpns) > 1) {
						foreach ($listpns as $pnindex => $pnarr) {
							if ($pnindex > 0) {
								$group_color = CI_Base_Panel::generateColorVariationHSL($main_color, $pnindex, count($listpns));
								if ($group_color != $pnarr['color']) {
									$wpdb->update('ci_test_groups', array('tg_bgcolor' => $group_color), array('tg_ID' => $pnarr['id']));
									Tr_Session_Class::add('Updated color of group "' . $pnarr['name'] . '" to ' . $group_color);
									//update color to $groups
									foreach ($groups as $group) {
										if ($group->tg_ID == $pnarr['id']) {
											$group->tg_bgcolor = $group_color;
											break;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		$url = Tr_Base_Class_V6::link(array('act' => '', 'trajax' => 0), false);
		wp_redirect($url);
		exit;
	}

?>
	<div class="wrap">
		<h2>Groups
			<a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New Group</a>
			<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>

			<?php if ($is_ci_admin) { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => 'plate_layout', 'id' => '')) ?>" class="add-new-h2">Plate Layout</a>
				<?php if ($fix_panel_colors) { ?>
					<a href="<?php Tr_Base_Class_V6::link(array('act' => 'auto_panel_colors', 'trajax' => 1)) ?>" class="add-new-h2">Auto Change Colors by type</a>
				<?php } ?>
			<?php } ?>
		</h2>

		<?php Tr_Session_Class::show() ?>

		<form method="post">

			<div class="wraptbl">
				<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
					<thead>
						<tr>
							<th style="width:50px">#</th>
							<th style="width:60px">Name</th>

							<th>Child Group</th>
							<th style="width:100px">Position</th>
							<th>Code</th>
							<th style="width:120px">Test Count</th>
							<th style="width:50px">Show</th>
							<?php if ($show_panel_type) { ?>
								<th style="width:90px">Type</th>
							<?php } ?>
							<?php if ($tes_type == 'pcr' && tr_current_role_in(array('administrator', 'AdminCIDW')) && tr_get_option('ci_pdf_data', 'new_abx')) { ?>
								<th class="center" style="width:100px">ABX Status</th>
							<?php } ?>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$positionnow 		= 1;
						$abx_types_groups 	= array();

						foreach ($groups as $i => $group) {
							$group->changed = false;

							$group->other_options = !empty($group->tg_other_options) ? json_decode($group->tg_other_options, true) : array();
							$group->abx_type = isset($group->other_options['abx_type']) ? $group->other_options['abx_type'] : '';

							if (!empty($group->abx_type)) {
								if (isset($abx_types_groups[$group->abx_type])) {
									//second
									update_option('ci_abx_type_' . $group->tg_ID, $group->abx_type, false);
								}
								$abx_types_groups[$group->abx_type] = $group->tg_ID;
							}


							if (empty($group->tg_panel_type)) {
								$group->tg_panel_type = $group->abx_type;
								$wpdb->update('ci_test_groups', array('tg_panel_type' => $group->tg_panel_type), array('tg_ID' => $group->tg_ID));
							}

							if (in_array($group->tg_panel_type, array('sr', 'sm', 'sl', 'sp', 'es'))) {
								$group->tg_panel_type = 'sti';
								$wpdb->update('ci_test_groups', array('tg_panel_type' => $group->tg_panel_type), array('tg_ID' => $group->tg_ID));
							}

							$sql_child = "select *,
								{$query_count}
								from ci_test_groups where tg_parent = {$group->tg_ID} order by tg_position asc";

							$childs = $wpdb->get_results($sql_child);
							if ($tes_type == 'req') {
								$group->count_tests = $group->count_tests_blood + $group->count_tests_pcr;
							}
							$count_test_group = $group->count_tests;
							foreach ($childs as $child) {
								$count_test_group += $child->count_tests;
							}

							$classes = '';
							if (!$group->tg_show_req) {
								$classes .= ' disabled ';
							}
							if (isset($_GET['reorder'])) {
								$group->tg_position = $positionnow++;
							}
						?>
							<tr data-id="<?php echo $group->tg_ID ?>" class="<?php echo $classes ?>">
								<td style="border-left:4px solid <?php echo $group->tg_bgcolor ?>;"><?php echo $group->tg_ID ?></td>
								<td colspan="2"><a class="tg_name bold" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group->tg_ID)) ?>"><?php echo $group->tg_name ?></a></td>
								<td><input type="text" name="position[<?php echo $group->tg_ID ?>]" value="<?php echo $group->tg_position ?>" required /> </td>
								<td><input type="text" name="tg_cpt_code[<?php echo $group->tg_ID ?>]" value="<?php echo $group->tg_cpt_code ?>" <?php if (!$is_ci_admin) echo 'readonly' ?> /> </td>
								<td>
									<?php
									if ($tes_type == 'req') {
									?>
										Blood(<a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=blood&gr=<?php echo $group->tg_ID ?>"><?php echo $group->count_tests_blood ?></a>) -
										PCR( <a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=pcr&gr=<?php echo $group->tg_ID ?>"><?php echo $group->count_tests_pcr ?></a>)
									<?php
									} else {
									?>
										<a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=<?php echo $tab ?>&gr=<?php echo $group->tg_ID ?>"><?php echo $group->count_tests . '(' . $count_test_group . ')' ?></a>
									<?php
									}
									?>

								</td>
								<td><input type="checkbox" name="show_req[<?php echo $group->tg_ID ?>]" value="1" <?php checked($group->tg_show_req) ?> /></td>
								<?php if ($show_panel_type) { ?>
									<td>
										<select name="tg_panel_type[<?php echo $group->tg_ID ?>]" <?php if (!$is_ci_admin) echo 'readonly' ?>>
											<?php
											echo '<option value="">Select</option>';
											foreach ($panel_types as $value => $label) { ?>
												<option value="<?php echo $value ?>" <?php selected($group->tg_panel_type, $value) ?>><?php echo $label ?></option>
											<?php } ?>
										</select>
									</td>
								<?php } ?>
								<?php if ($tes_type == 'pcr' && tr_current_role_in(array('administrator', 'AdminCIDW')) && tr_get_option('ci_pdf_data', 'new_abx')) {
									$abx_class = '';
									$abx_title = '';
									if (isset($group->other_options['new_abx']) && $group->other_options['new_abx']) {
										$abx_class = 'abx_active';
										$abx_title = 'Actived';
										if (!empty($group->other_options['new_abx_date']) && strtotime($group->other_options['new_abx_date']) > time()) {
											$abx_class = 'abx_setup';
											$abx_title = 'Contact us to activate ABX';
										} else if ($group->changed) {
											$abx_class = 'abx_changed';
											$abx_title = 'Panel information changed. Contact us to review ABX';
										}
									}
									echo '<td class="center"><span class="abx_icon ' . $abx_class . '" title="' . $abx_title . '"></span></td>';
								} ?>
								<td>
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group->tg_ID)) ?>">Edit</a>
									<?php if (($group->count_tests == 0 && count($childs) == 0) || isset($_GET['debug'])) { ?>
										- <a class="delete" href="#">Delete</a>
									<?php } ?>
									- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'group_export', 'trajax' => 1, 'type'   => $tes_type, 'id' => $group->tg_ID)) ?>">Export</a>
									<?php if ($is_ci_admin || $current_role == 'AdminCIDW') {
										$from_group = $group->tg_name . (isset($group->parent_name) ? ' - ' . $group->parent_name : '');
									?>
										- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'cutoff_export', 'trajax' => 1, 'type'   => $tes_type, 'gid' => $group->tg_ID)) ?>">Export only Cutoffs</a>

										- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'copytest', 'id' => $group->tg_ID, 'from' => $from_group)) ?>">Copy Tests</a>
										- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'duplicate', 'id' => $group->tg_ID, 'from' => $from_group)) ?>">Duplicate</a>
										- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'viewdemopdf', 'id' => $group->tg_ID, 'trajax' => 1)) ?>" target="_blank">View Demo PDF</a>
									<?php
									}
									?>
								</td>
							</tr>
							<?php
							foreach ($childs as $child) {
								$child->changed = false;
								$child->other_options = !empty($child->tg_other_options) ? json_decode($child->tg_other_options, true) : array();
								$child->abx_type = isset($child->other_options['abx_type']) ? $child->other_options['abx_type'] : '';


								if (isset($_GET['reorder'])) {
									$child->tg_position = $positionnow++;
								}
							?>
								<tr data-id="<?php echo $child->tg_ID ?>" class="<?php echo $classes ?>">
									<td><?php echo $child->tg_ID ?></td>
									<td></td>
									<td><a class="tg_name" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $child->tg_ID)) ?>"><?php echo $child->tg_name ?></a></td>

									<td><input type="text" name="position[<?php echo $child->tg_ID ?>]" value="<?php echo $child->tg_position ?>" required /> </td>
									<td><input type="text" name="tg_cpt_code[<?php echo $child->tg_ID ?>]" value="<?php echo $child->tg_cpt_code ?>" <?php if (!$is_ci_admin) echo 'readonly' ?> /> </td>
									<td>
										<?php
										if ($tes_type == 'req') {
											$child->count_tests = $child->count_tests_blood + $child->count_tests_pcr;
										?>
											Blood(<a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=blood&gr=<?php echo $child->tg_ID ?>"><?php echo $child->count_tests_blood ?></a>) -
											PCR( <a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=pcr&gr=<?php echo $child->tg_ID ?>"><?php echo $child->count_tests_pcr ?></a>)
										<?php
										} else {
										?>
											<a href="?page=ci_pdf_data&pagetab=ci_test_panels&tab=<?php echo $tes_type ?>&gr=<?php echo $child->tg_ID ?>"><?php echo $child->count_tests ?></a>
										<?php
										}
										?>
									</td>
									<td></td>
									<?php if ($show_panel_type) { ?>
										<td></td>
									<?php } ?>
									<?php if ($tes_type == 'pcr' && tr_current_role_in(array('administrator', 'AdminCIDW'))  && tr_get_option('ci_pdf_data', 'new_abx')) {
										$child_abx_class = '';
										$abx_title = '';
										if (!empty($child->abx_type)) {
											$child_abx_class = 'abx_active';
											$abx_title = 'Actived';
											if (!empty($child->other_options['new_abx_date']) && strtotime($child->other_options['new_abx_date']) > time()) {
												$child_abx_class = 'abx_setup';
												$abx_title = 'Contact us to activate ABX';
											} else if ($child->changed) {
												$child_abx_class = 'abx_changed';
												$abx_title = 'Panel information changed. Contact us to review ABX';
											}
										}
										if (!empty($child_abx_class)) {
											echo '<td class="center"><span style="width:8px;height:8px" class="abx_icon ' . $child_abx_class . '" title="' . $abx_title . '"></span></td>';
										} else {
											echo '<td></td>';
										}
									} ?>
									<td>
										<input type="hidden" name="show_req[<?php echo $child->tg_ID ?>]" value="<?php echo $child->tg_show_req ?>" />

										<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $child->tg_ID)) ?>">Edit</a>
										<?php if ($child->count_tests == 0 || isset($_GET['debug'])) { ?>
											- <a class="delete" href="#">Delete</a>
										<?php } ?>
										- <a href="<?php Tr_Base_Class_V6::link(array('act' => 'group_export', 'trajax' => 1, 'type'   => $tes_type, 'id' => $child->tg_ID)) ?>">Export</a>
										<?php if (isset($_GET['debug'])) {
											$from_group = $child->tg_name . (isset($group->parent_name) ? ' - ' . $group->parent_name : '');
										?> -
											<a href="<?php Tr_Base_Class_V6::link(array('act' => 'copytest', 'id' => $child->tg_ID, 'from' => $from_group)) ?>">Copy Tests</a>
										<?php
										}
										?>
									</td>
								</tr>
						<?php
							}

							if (isset($_GET['reorder'])) {
								$positionnow += 5;
							}
						}


						?>
					</tbody>
				</table>
			</div>

			<input type="submit" class="button" value="Save Changes" />
			<br>
		</form>


	</div>

	<style>
		tr.disabled td {
			color: #888 !important;
		}

		.abx_icon {
			width: 12px;
			height: 12px;
			border-radius: 50%;
			display: inline-block;
			background: #b0afaf;
		}

		.abx_icon.abx_active {
			background: green;
		}

		.abx_icon.abx_setup {
			background: lightgreen;
		}

		.abx_icon.abx_changed {
			background: orange;
		}
	</style>
	<script>
		jQuery(function($) {
			$('a.delete').click(function() {
				var $pr = $(this).parents('tr');
				var $tgname = $('.tg_name', $pr).html();
				if (confirm('Do you want delete group "' + $tgname + '"?') == false) return false;
				var $tg_ID = $pr.data('id');
				var $f = $('<form method="post"><input type="hidden" name="act" value="deletegroup"/>' +
					'<input type="hidden" name="tg_ID" value="' + $tg_ID + '"/></form> ');
				$('body').append($f);
				$f.submit();
				return false;
			})
		})
	</script>
<?php
}
