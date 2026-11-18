<?php
global $wpdb, $is_ci_admin;
$pcr_settings = get_option('ci_result_settings_pcr', array());
$current_user = wp_get_current_user();

if (isset($_FILES['jsonfile'])) {

	$onlycutoff = isset($_REQUEST['onlycutoff']) && $_REQUEST['onlycutoff'];
	$onlytests 	= isset($_REQUEST['onlytests']) && $_REQUEST['onlytests'];

	if (stripos($_FILES['jsonfile']['name'], '.csv') > 0) {
		$data 		= array();
		$f 			= fopen($_FILES['jsonfile']['tmp_name'], 'r');
		$separater 	= ',';
		$datafile           = file_get_contents($_FILES['jsonfile']['tmp_name']);
		$count_semicolon    = substr_count($datafile, ';');
		$count_comma        = substr_count($datafile, ',');
		$datafile           = '';
		if ($count_semicolon > $count_comma) {
			$separater = ';';
		}
		while ($row = fgetcsv($f, null, $separater)) {
			$data[] = $row;
		}

		$_SESSION['import_panels_data'] = $data;
		$args = array('step' => '2');
		$args['onlycutoff'] = $onlycutoff;
		$args['onlytests'] = $onlytests;
		wp_redirect(Tr_Base_Class_V6::link($args, false));
		exit;
	}

	$data = file_get_contents($_FILES['jsonfile']['tmp_name']);
	$json = json_decode($data, true);

	$test_cols 		= $wpdb->get_col("show COLUMNS from `ci_test_panels`");
	$group_cols 	= $wpdb->get_col("show COLUMNS from `ci_test_groups`");
	$cutoff_cols 	= $wpdb->get_col("show COLUMNS from `ci_test_panels_cutoffs`");
	$specimen_cols 	= $wpdb->get_col("show COLUMNS from `ci_test_specimens`");

	$group_importIds = array();
	$filefrom 		= isset($json['from']) ? $json['from'] : '';

	foreach ($json['groups'] as $group) {
		$group_parent = 0;
		if ($group['tg_parent'] > 0) {
			$group_parent = $group_importIds[$group['tg_parent']];
			if ($group_parent == 0) {
				Tr_Session_Class::add_error('Empty group parent');
				Tr_Session_Class::show();
				exit;
			}
		}

		$group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
                                    where tg_type='{$tes_type}' 
                                    and tg_parent = '{$group_parent}'
                                    and tg_name='{$group['tg_name']}'");
		if ($group_id == 0) {

			$group_data = $group;
			unset($group_data['tg_ID']);
			$group_data['tg_type'] = $tes_type;
			$group_data['tg_parent'] = $group_parent;

			foreach ($group_data as $k => $vl) {
				if (!in_array($k, $group_cols)) {
					unset($group_data[$k]);
				}
			}

			$rs = $wpdb->insert('ci_test_groups', $group_data);
			echo $wpdb->last_error;
			$group_id = $wpdb->insert_id;
		}

		$group_importIds[$group['tg_ID']] = $group_id;
	}


	$tests_importIds = array();
	foreach ($json['tests'] as $test) {
		$metas = isset($test['metas']) ? $test['metas'] : array();

		$group_id = $group_importIds[$test['te_group_id']];
		$test_id = $wpdb->get_var("select te_ID from ci_test_panels 
                                                where te_type='{$tes_type}' 
                                                and te_name = '{$test['te_name']}' 
                                                and te_group_id = '{$group_id}' ");
		$test_data = $test;
		foreach ($test_data as $tk => $tv) {
			if (!in_array($tk, $test_cols)) {
				unset($test_data[$tk]);
			}
		}

		unset($test_data['te_centaur_code']);
		unset($test_data['te_mf_ranges']);
		unset($test_data['te_is_hlm_detected']);
		unset($test_data['te_ID']);
		unset($test_data['import_ID']);
		unset($test['metas']);

		$test_data['te_type'] = $tes_type;
		$test_data['te_group_id'] = intval($group_id);

		if ($filefrom == 'ark') {
			if ($test_data['te_is_detected'] == 4) {
				$test_data['te_is_detected'] = 5;
			} else if ($test_data['te_is_detected'] == 3 && $tes_type == 'blood') {
				$test_data['te_is_detected'] = 1;
			} else if ($test_data['te_is_detected'] == 2 && $tes_type == 'blood') {
				$test_data['te_is_detected'] = 3;
			} else if ($test_data['te_is_detected'] == 1 && $tes_type == 'blood') {
				$test_data['te_is_detected'] = 2;
			}
		}

		if ($test_id == 0) {
			$wpdb->insert('ci_test_panels', $test_data);
			echo $wpdb->last_error;
			$test_id = $wpdb->insert_id;
		} else if (!$onlycutoff) {
			$wpdb->update('ci_test_panels', $test_data, array('te_ID' => $test_id));
		}

		$tests_importIds[$test['te_ID']] = $test_id;

		//import meta
		if (!$onlycutoff) {
			foreach ($metas as $mtk => $mtvl) {
				$exitmetaid = $wpdb->get_var("select id 
				from ci_test_meta 
				where test_id = {$test_id}
				and meta_key = '{$mtk}' ");
				$metaarr = array(
					'test_id' => $test_id,
					'meta_key' => $mtk,
					'meta_value' => $mtvl
				);
				if ($exitmetaid > 0) {
					$wpdb->update('ci_test_meta', $metaarr, array('id' => $exitmetaid));
				} else {
					$wpdb->insert('ci_test_meta', $metaarr);
				}
			}
		}
	}

	foreach ($json['cutoffs'] as $cutoff) {
		$test_id = $tests_importIds[$cutoff['cu_te_ID']];
		$cut_db = $wpdb->get_var("select * from ci_test_panels_cutoffs 
                                            where cu_te_ID = {$test_id} 
                                            and cu_btype = '{$tes_type}'
                                            and cu_ms = '{$cutoff['cu_ms']}' ");


		$cutoff['cu_btype'] = $tes_type;
		$cutoff['cu_te_ID'] = $test_id;

		foreach ($cutoff as $tk => $tvl) {
			if (!in_array($tk, $cutoff_cols)) {
				unset($cutoff[$tk]);
			} else if (is_null($tvl)) {
				unset($cutoff[$tk]);
			}
		}
		if (!$cut_db) {
			$wpdb->insert('ci_test_panels_cutoffs', $cutoff);
		} else {
			$wpdb->update('ci_test_panels_cutoffs', $cutoff, array('cu_te_ID' => $test_id, 'cu_btype' => $tes_type, 'cu_ms' => $cutoff['cu_ms']));
		}

		if (!empty($cutoff['cu_ms'])) {
			$ms = explode(',', $cutoff['cu_ms']);
			foreach ($ms as $m) {
				if (!empty($m) && $m != '*') {
					$wpdb->query("insert IGNORE into ci_instruments (instrument,btype)
                                    values ('{$m}','{$tes_type}') ");
				}
			}
		}
	}

	if (!$onlycutoff) {
		foreach ($json['specimens'] as $specimen) {
			$group_id = $group_importIds[$specimen['ts_group_id']];
			$test_id = $wpdb->get_var("select ts_ID from ci_test_specimens where ts_type	='{$tes_type}' 
													and ts_name = '{$specimen['ts_name']}' 
													and ts_group_id = '{$group_id}' ");
			$test_data = $specimen;
			unset($test_data['ts_ID']);
			foreach ($test_data as $tk => $tvl) {
				if (!in_array($tk, $specimen_cols)) {
					unset($test_data[$tk]);
				}
			}
			$test_data['ts_type'] = $tes_type;
			$test_data['ts_group_id'] = intval($group_id);

			if ($test_id == 0) {
				$wpdb->insert('ci_test_specimens', $test_data);
				echo $wpdb->last_error;
				$test_id = $wpdb->insert_id;
			} else {
				$wpdb->update('ci_test_specimens', $test_data, array('ts_ID' => $test_id));
			}
		}
	}


	echo 'Imported successfully!';
} else if (isset($_POST['do_import2'])) {
	$group_id   = isset($_REQUEST['group_id']) ? $_REQUEST['group_id'] : 0;
	$text       = isset($_POST['text']) ? $_POST['text'] : '';
	$ms         = isset($_POST['ms']) && !empty($_POST['ms']) ? $_POST['ms'] : '*';
	$msarr		= strpos($ms, ',') > 0 ? explode(',', $ms) : array($ms);
	$data		= $_SESSION['import_panels_data'];
	$maps		= $_POST['maps'];
	$_SESSION['ci_last_import_maps'] = $maps;
	$insertfirst = isset($_POST['insertfirst']);

	if (count($data) == 1 && $data[0][0] == 'all') {
		$row = $data[0];
		$data = array();
		$insertfirst = true;
		$sql1 = "select te_name from ci_test_panels 
		where te_type = '{$tes_type}'
		 ";
		if ($group_id > 0) {
			$sql1 .= " and te_group_id = {$group_id} ";
		}
		$rs = $wpdb->get_col($sql1);
		foreach ($rs as $r) {
			$newrow = $row;
			$newrow[0] = $r;
			$data[] = $newrow;
		}
	}


	if (count($maps) > 0) {

		$group = null;
		if ($group_id > 0) {
			$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$group_id} ");
		}

		$groupsbynames = array();
		$position = 0;
		$count_test = 0;
		foreach ($data as $ri => $line) {
			if (!$insertfirst && $ri == 0) {
				continue;
			}
			$count_test++;

			$position += 2;

			$row_group_id = $group_id;

			$rowdata = array();
			$group_name = '';
			$subgroup_name = '';
			foreach ($maps as $i => $mkey) {

				if (!empty($mkey) && isset($line[$i]) && $line[$i] !== '') {

					$colvl = trim($line[$i]);
					if ($mkey == 'group') {
						$group_name = $colvl;

						continue;
					} else if ($mkey == 'subgroup') {
						$subgroup_name = $colvl;
						$gramname = str_replace(array('-', '  '), array(' '), $subgroup_name);
						if (stripos($gramname, 'gram positive') !== false) {
							$subgroup_name = 'Gram Positive';
						} else if (stripos($gramname, 'gram Negative') !== false) {
							$subgroup_name = 'Gram Negative';
						}

						continue;
					}
					$rowdata[$mkey] = $colvl;
				}
			}

			if (!empty($group_name)) {
				if (!isset($groupsbynames[strtolower($group_name)])) {
					$gid = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type = '{$tes_type}' 
					and tg_name = '{$group_name}' and tg_parent = 0");
					if ($gid > 0) {
						$groupsbynames[strtolower($group_name)] = $gid;
					} else if (!isset($_POST['dontnewtest'])) {
						$wpdb->insert('ci_test_groups', array(
							'tg_type' => $tes_type,
							'tg_name' => $group_name,
						));
						$groupsbynames[strtolower($group_name)] = $wpdb->insert_id;
					}
				}
				$row_group_id = $groupsbynames[strtolower($group_name)];
			}

			if (!empty($subgroup_name)) {
				if (!isset($groupsbynames[strtolower($subgroup_name) . '_sub'])) {
					$gid = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type = '{$tes_type}' 
					and tg_name = '{$subgroup_name}' and tg_parent = {$row_group_id} ");
					if ($gid > 0) {
						$groupsbynames[strtolower($subgroup_name) . '_sub'] = $gid;
					} else if (!isset($_POST['dontnewtest'])) {
						$wpdb->insert('ci_test_groups', array(
							'tg_type' => $tes_type,
							'tg_name' => $subgroup_name,
							'tg_parent' => $row_group_id,
							'tg_show_req' => 0,
						));
						$groupsbynames[strtolower($subgroup_name) . '_sub'] = $wpdb->insert_id;
					}
				}
				$row_group_id = $groupsbynames[strtolower($subgroup_name) . '_sub'];
			}


			$testname   = isset($rowdata['te_name']) ? $rowdata['te_name'] : '';
			$abrev      = isset($rowdata['te_abrev']) ? $rowdata['te_abrev'] : '';

			if (!isset($rowdata['te_name']) && isset($rowdata['te_abrev']) && !empty($rowdata['te_abrev'])) {
				$testabrev = $rowdata['te_abrev'];
				$sql_test = "select te_name from ci_test_panels 
				where te_type='{$tes_type}' 
				and te_abrev = '{$testabrev}'  ";
				if ($row_group_id > 0) {
					$sql_test .= " and  (
					te_group_id = {$row_group_id}  
					or te_group_id in (select tg_ID from ci_test_groups where tg_parent = {$row_group_id})
				)";
				}

				$testname = $wpdb->get_var($sql_test);

				if (!$testname && $group->tg_parent > 0) {
					$testname = $wpdb->get_var("select te_name from ci_test_panels 
	                        where te_type='{$tes_type}' 
	                        and te_abrev = '{$testabrev}' 
	                        and te_group_id = {$group->tg_parent} ");
				}
			}

			if (empty($testname)) continue;

			$test_data = array(
				'te_name' => $testname,
				'te_type' => $tes_type,
				'te_group_id' => $row_group_id,
				'te_in_req' => '1',
				'te_is_detected' => '1',
				'te_position' => $position
			);

			if (stripos($subgroup_name, 'control') !== false) {
				$test_data['te_in_req'] = 0;
				$test_data['te_is_control'] = 1;
			}

			if (!empty($abrev)) {
				$test_data['te_abrev'] = $abrev;
			}

			$cutoff = array();

			$low        = isset($arr[2]) && !empty($arr[2]) ? $arr[2] : $_POST['low'];
			$med        = isset($arr[3]) && !empty($arr[3]) ? $arr[3] : $_POST['medium'];
			$high       = isset($arr[4]) && !empty($arr[4]) ? $arr[4] : $_POST['high'];

			if (isset($_POST['low']) && !empty($_POST['low'])) {
				$cutoff['cu_cutoff'] = $_POST['low'];
			}

			if (isset($_POST['medium']) && !empty($_POST['medium'])) {
				$cutoff['cu_medium'] = $_POST['medium'];
			}

			if (isset($_POST['high']) && !empty($_POST['high'])) {
				$cutoff['cu_upper_limit'] = $_POST['high'];
			}



			foreach ($rowdata as $k => $vl) {
				if (substr($k, 0, 3) == 'te_') {
					$test_data[$k] = $vl;
				} else if (substr($k, 0, 3) == 'cu_') {
					$vl = str_replace(',', '.', $vl);
					$vl = trpdd_replace_symbol_db($vl);

					if (strpos($vl, '>') === false && strpos($vl, '<') === false && strpos($vl, '=') === false) {

						if ($pcr_settings['enable_rs_ct_amp_cq'] && in_array($k, array('cu_critical_high', 'cu_hqc'))) {
							if ($k == 'cu_critical_high') {
								//amp
								$vl = stripos($vl, '>') === false && stripos($v, '-') === false ? '>=' . $vl : $vl;
							} else if ($k == 'cu_hqc') {
								//cq
								$vl = stripos($vl, '>') === false && stripos($v, '-') === false ? '>=' . $vl : $vl;
							}
						} elseif (in_array($k, array('cu_upper_limit', 'cu_cutoff', 'cu_medium', 'cu_critical_high', 'cu_critical_low'))) {
							//ct | high , medium, low
							if (stripos($vl, '-') > 0) {
								list($num1, $num2) = explode('-', $vl);
								if ($num1 > $num2) {
									$none2 = $num2;
									$num2 = $num1;
									$num1 = $none2;
								}

								if ($k == 'cu_upper_limit' && stripos($num2, '<') === false) {
									$num2 = '<' . $num2;
								} else if ($k == 'cu_medium' && stripos($num2, '<') === false) {
									$num2 = '<' . $num2;
								} else if ($k == 'cu_cutoff' && stripos($num2, '<') === false) {
									//$num2 = '<' . $num2;
								}
								$vl = $num1 . '-' . $num2;
							} else {
								$vl = stripos($vl, '<') === false ? '<=' . $vl : $vl;
							}
						}
					}

					$cutoff[$k] = trpdd_replace_symbol_db($vl);
				}
			}



			$low    = str_replace(',', '.', $low);
			$med    = str_replace(',', '.', $med);
			$high   = str_replace(',', '.', $high);


			$sql_test = "select te_ID from ci_test_panels 
			where te_type='{$tes_type}' 
			and te_name = '{$testname}'  ";
			if ($row_group_id > 0) {
				$sql_test .= " and  (
					te_group_id = {$row_group_id}  
					or te_group_id in (select tg_ID from ci_test_groups where tg_parent = {$row_group_id})
				)";
			}

			$test_id = $wpdb->get_var($sql_test);

			if (!$test_id && $group->tg_parent > 0) {
				$test_id = $wpdb->get_var("select te_ID from ci_test_panels 
	                        where te_type='{$tes_type}' 
	                        and te_name = '{$testname}' 
	                        and te_group_id = {$group->tg_parent} ");
			}

			if (($row_group_id == 0 || isset($_POST['dontnewtest'])) && $test_id == 0) {
				Tr_Session_Class::add_error("{$group_name} : {$testname}  :not found");
				continue;
			}

			if (!$test_id && !isset($_POST['dontnewtest'])) {

				$wpdb->insert('ci_test_panels', $test_data);
				$test_id = $wpdb->insert_id;
			} else if ($test_id > 0) {
				$update_data = $test_data;
				unset($update_data['te_name']);
				unset($update_data['te_type']);
				unset($update_data['te_group_id']);
				unset($update_data['te_in_req']);
				unset($update_data['te_is_detected']);
				unset($update_data['te_position']);

				$wpdb->update(
					'ci_test_panels',
					$update_data,
					array('te_ID' => $test_id)
				);
			}

			if ($test_id > 0 && count($cutoff) > 0) {

				$ms_arr2 = $msarr;
				if (!empty($cutoff['cu_ms'])) {
					$ms_arr2 = array($cutoff['cu_ms']);
				}

				foreach ($ms_arr2 as $ms) {

					$cutoff['cu_te_ID'] = $test_id;
					$cutoff['cu_ms'] = $ms;
					$cutoff['cu_btype'] = $tes_type;

					$where_cut = " and cu_ms = '{$cutoff['cu_ms']}' ";
					if (empty($ms) || $ms == '*') {
						$where_cut = " and (cu_ms = '{$cutoff['cu_ms']}' or cu_ms is null or cu_ms = '' )";
					}


					$cut_db = $wpdb->get_row("select * from ci_test_panels_cutoffs 
					where cu_te_ID = {$test_id} 
					and cu_btype = '{$tes_type}'
					{$where_cut}
					", ARRAY_A);

					if (!$cut_db || empty($cut_db['cu_te_ID'])) {
						$wpdb->insert('ci_test_panels_cutoffs', $cutoff);
					} else {
						if (!isset($_POST['replace'])) {
							foreach ($cutoff as $k => $vl) {
								if (!empty($cut_db[$k])) {
									unset($cutoff[$k]);
								}
							}
						}
						if (!empty($cutoff)) {
							$wpdb->update('ci_test_panels_cutoffs', $cutoff, array('cu_te_ID' => $test_id, 'cu_btype' => $tes_type, 'cu_ms' => $cut_db['cu_ms']));
						}
					}
				}
			}
		}

		Tr_Session_Class::add('Successfully!');
		Tr_Session_Class::add('Count Tests: ' . $count_test);
		wp_redirect(Tr_Base_Class_V6::link(array('step' => ''), false));
		exit;
	}
} else if (!empty($_POST['text']) || !empty($_FILES['csv']['tmp_name'])) {
	$text       = $_POST['text'];
	$data 		= array();
	if (!empty($text)) {
		$text = str_replace("\r", "\n", $text);
		$text = str_replace("\n\n", "\n", $text);
		$lines = explode("\n", $text);
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line)) continue;

			$line   = str_replace("\t-\t", "-", $line);
			$data[] = explode("\t", $line);
		}
	} else {
		$f = fopen($_FILES['csv']['tmp_name'], 'r');

		$datafile           = file_get_contents($_FILES['csv']['tmp_name']);
		$count_semicolon    = substr_count($datafile, ';');
		$count_comma        = substr_count($datafile, ',');
		$datafile           = '';
		if ($count_semicolon > $count_comma) {
			$separater = ';';
		}
		while ($row = fgetcsv($f, null, $separater)) {
			$data[] = $row;
		}
	}

	$_SESSION['import_panels_data'] = $data;

	wp_redirect(Tr_Base_Class_V6::link(array('step' => 2), false));
	exit;
}


?>
<div class="wrap">
	<h2 class="center">Import & Export <?php echo strtoupper($tes_type) ?></h2>
	<?php Tr_Session_Class::show() ?>
	<br>
	<div class="center">
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'cutoff_export', 'type' => $tes_type, 'trajax' => 1)) ?>" class="add-new-h2">Cutoffs Export</a>

		<a href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'export', 'trajax' => 1)) ?>" class="add-new-h2">Export</a>
		<a href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'export_template', 'trajax' => 1)) ?>" class="add-new-h2">Export template</a>

		<?php
		if ($is_ci_admin) { ?>
			<a title="Only show for admin" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'compendium_export', 'trajax' => 1)) ?>" class="add-new-h2">Compendium Export</a>
			<a title="Only show for admin" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'export_json', 'trajax' => 1)) ?>" class="add-new-h2">Export Json</a>
			<a title="Only show for admin" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'backup_json', 'trajax' => 1)) ?>" class="add-new-h2">Backup Json</a>
			<a title="Only show for admin" class="thickbox add-new-h2" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'backups', 'trajax' => 1, 'width' => '1200')) ?>">Backups</a>

		<?php } ?>
	</div>
	<br><br>
	<?php
	if (isset($_GET['step']) && $_GET['step'] == 2) {

		$data = $_SESSION['import_panels_data'];
		$columns = $data[0];
		$last_maps = array();
		if (isset($_SESSION['ci_last_import_maps']) && count($columns) == count($_SESSION['ci_last_import_maps'])) {
			$last_maps = $_SESSION['ci_last_import_maps'];
		}


		$maps = array(
			'te_name'           => 'Name',
			'te_abrev'          => 'Abrev',
			'te_abrev2'         => 'Abrev 2',
			'te_snomed_code'    => 'Snomed Code',
			'te_loinc_code'     => 'Loinc Code',
			'te_label_barcode'  => 'Label Barcode',
			'te_units'          => 'Units',
			'te_range'          => 'Range',
			'te_conc_decimal'   => 'Decimal',
			'cu_critical_high'  => 'Very High/Amp Score',
			'cu_upper_limit'    => 'Ct / High',
			'cu_medium'         => 'Medium',
			'cu_cutoff'         => 'Low',
			'cu_critical_low'   => 'Very Low',
			'cu_invalid'        => 'Invalid',
			'cu_hqc'            => 'Cq',
			'cu_ms'             => 'Instrument',
			'cu_start'          => 'Start Date',
			'cu_end'            => 'End Date',
			'group'             => 'Group',
			'subgroup'          => 'Sub Group',
		);

		$found_header = false;

	?>
		<form method="POST">

			<div class="frow">
				<div class="col2">
					<table style="width:100%;" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
						<thead>
							<tr>
								<th>First Row <label id="insertfirstrow"><input type="checkbox" name="insertfirst" value="1"> Insert first row</label></th>
								<th>Map</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($columns as $i => $col) {
								$col = trim($col);
								$col_low = strtolower($col);
								$colkey = '';
								if ($col_low == 'test' || $col_low == 'name' || $col_low == 'test name' || $col_low == 'all') {
									$colkey = 'te_name';
								} else if (stripos($col, 'abrev') === 0) {
									$colkey = 'te_abrev';
								} else if (stripos($col, 'abrev2') === 0) {
									$colkey = 'te_abrev2';
								} else if (stripos($col, 'test code') === 0 || $col_low == 'loinc') {
									$colkey = 'te_loinc_code';
								} else if ($col_low == 'group' || $col_low == 'panel') {
									$colkey = 'group';
								} else if ($col_low == 'sub group') {
									$colkey = 'subgroup';
								} else if ($col_low == 'very low') {
									$colkey = 'cu_critical_low';
								} else if ($col_low == 'low') {
									$colkey = 'cu_cutoff';
								} else if ($col_low == 'medium') {
									$colkey = 'cu_medium';
								} else if ($col_low == 'high') {
									$colkey = 'cu_upper_limit';
								} else if ($col_low == 'very high') {
									$colkey = 'cu_critical_high';
								} else if (stripos($col, 'abrev2') === 0 || strtolower($col) == 'abrev 2') {
									$colkey = 'te_abrev2';
								} else if (stripos($col, 'snomed') !== false) {
									$colkey = 'te_snomed_code';
								} else if (stripos($col, 'test code') === 0 || $col_low == 'loinc' || stripos($col, 'loinc') !== false) {
									$colkey = 'te_loinc_code';
								} else if (stripos($col, 'barcode') !== false || stripos($col, 'label') !== false) {
									$colkey = 'te_label_barcode';
								} else if ($col_low == 'units' || $col_low == 'unit') {
									$colkey = 'te_units';
								} else if ($col_low == 'instrument') {
									$colkey = 'cu_ms';
								} else if ($col_low == 'start date') {
									$colkey = 'cu_start';
								} else if ($col_low == 'end date') {
									$colkey = 'cu_end';
								}

								if (empty($colkey) && isset($last_maps[$i])) {
									$colkey = $last_maps[$i];
								} else if (!empty($colkey)) {
									$found_header = true;
								}
							?>
								<tr>
									<td><?php echo $col ?></td>
									<td>
										<select name="maps[<?php echo $i ?>]">
											<?php
											echo '<option value="">select</option>';
											foreach ($maps as $k => $txt) {
												$selected = $k == $colkey ? 'selected' : '';
												echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
											}
											?>
										</select>
									</td>
								</tr>
							<?php
							}
							?>
						</tbody>

					</table>

				</div>
				<div class="col2">
					<div class="frow">
						<label>Group:</label>
						<select name="group_id">
							<option value="">In File</option>
							<?php
							$groups = $wpdb->get_results("select *
							from ci_test_groups t
							where tg_type='{$tes_type}' and tg_parent = 0 and tg_show_req = 1
							order by tg_show_req desc, tg_name asc");

							foreach ($groups as $group) {
								$childs = $wpdb->get_results(
									"select *,
								(select count(*) from ci_test_panels where te_group_id = tg_ID) as count_tests
							from ci_test_groups where tg_parent = {$group->tg_ID} order by tg_position asc"
								);

								$selected = isset($panel['te_group_id']) && $group->tg_ID == $panel['te_group_id'] ? 'selected="selected"' : '';
								echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';

								foreach ($childs as $child) {
									$selected = isset($panel['te_group_id']) && $child->tg_ID == $panel['te_group_id'] ? 'selected="selected"' : '';
									echo '<option value="' . $child->tg_ID . '" ' . $selected . '>- &nbsp;&nbsp;' . $child->tg_name . '</option>';
								}
							}
							?>
						</select>
					</div>
					<div class="frow">
						<div class="col4">
							<label>MS</label>
							<input type="text" name="ms" value="<?php echo isset($_POST['ms']) ? $_POST['ms'] : '' ?>" />
						</div>
						<div class="col4">
							<label>Default Low</label>
							<input type="text" name="low" value="<?php echo isset($_POST['low']) ? $_POST['low'] : '' ?>" />
						</div>
						<div class="col4">
							<label>Default Medium</label>
							<input type="text" name="medium" value="<?php echo isset($_POST['medium']) ? $_POST['medium'] : '' ?>" />
						</div>
						<div class="col4">
							<label>Default High</label>
							<input type="text" name="high" value="<?php echo isset($_POST['high']) ? $_POST['high'] : '' ?>" />
						</div>

					</div>

					<div class="frow">
						<label><input type="checkbox" name="replace" value="1" checked /> Replace Cutoffs</label>
					</div>

					<div class="frow">
						<label><input type="checkbox" name="dontnewtest" value="1" /> Dont add new test</label>
					</div>


				</div>
			</div>
			<div class="frow">
				<h3>Other Rows</h3>
				<table style="width:100%;" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
					<?php

					foreach ($data as $i => $row) {
						if ($i > 3) break;
						echo '<tr>';
						foreach ($row as $col) {
							echo '<td>' . $col . '</td>';
						}
						echo '</tr>';
					}
					?>
				</table>
			</div>
			<div class="frow">
				<input type="submit" class="button" name="Import" />
			</div>
			<input type="hidden" name="do_import2" value="2" />
			<input type="hidden" name="trajax" value="1" />
		</form>

		<script>
			<?php if ($found_header) echo 'jQuery("#insertfirstrow").hide();' ?>
		</script>
	<?php
	} else {
		$_SESSION['import_panels_data'] = array();
	?>

		<div class="frow">

			<form method="post" enctype="multipart/form-data" style="margin:20px auto;width:400px;border:1px dashed #ddd;text-align: center;padding:20px;">
				<label>File: </label><input type="file" name="jsonfile" />
				<input type="submit" class="button" value="Import" />
				<br>
				<br>

				<label><input type="checkbox" name="onlycutoff" value="1" /> Only Update Cutoffs</label>
			</form>

			<?php
			if ($is_ci_admin && isset($_GET['admin'])) {
			?>
				<div style="margin:20px auto;width:400px;border:1px dashed #ddd;text-align: center;padding:20px;">
					<h3>Only for admin</h3>
					<form method="post" enctype="multipart/form-data">
						<br>

						<div class="frow">
							<label>Text split by tab</label>
							<textarea name="text" placeholder="Test Name {tab} Abrev {tab} low {tab} med {tab} high"><?php echo isset($_POST['text']) ? $_POST['text'] : '' ?></textarea>
						</div>
						<div class="frow">
							<label>Or Csv file:</label>
							<input type="file" name="csv" />
						</div>

						<div class="frow">
							<input type="submit" class="button" value="Import" />
							<input type="hidden" name="trajax" value="1" />
						</div>
					</form>
				</div>
			<?php
			}
			?>
		</div>
	<?php } ?>



</div>