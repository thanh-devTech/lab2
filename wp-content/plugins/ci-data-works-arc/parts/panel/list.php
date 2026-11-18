<?php
global $wpdb, $ci_all_btypes, $is_ci_admin;

include(TRCPDD_PATH . 'inc/define_instruments.php');

$act = isset($act) ? $act : '';
$tab = isset($tab) ? $tab : '';
$can_delete = isset($can_delete) ? (bool) $can_delete : false;
if (!isset($tes_type)) {
	$tes_type = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? $_REQUEST['type'] : $tab;
}
if (empty($tes_type)) {
	$tes_type = '';
}

$result_settings   = get_option('ci_result_settings_' . $tes_type, array());


$group_selected = isset($_GET['gr']) ? $_GET['gr'] : '';
$show_cutoffs = isset($_GET['showcutoffs']) ? $_GET['showcutoffs'] : '';


$group_types_str = "'{$tes_type}'";
if (in_array($tes_type, array('blood', 'pcr')) && in_array('req', $ci_all_btypes)) {
	$group_types_str .= ",'req'";
}

$today = date_i18n('Y-m-d');
$current_user = wp_get_current_user();
$is_admin = $current_user->user_login == 'Administrat0r';

if ($act == 'reposition' && $group_selected > 0) {
	$groups_id = $wpdb->get_col("select tg_ID from ci_test_groups 
		where tg_ID = {$group_selected} 
		or tg_parent = {$group_selected} ");
	$groups_id_str = implode(',', $groups_id);
	$testsids = $wpdb->get_col("select te_ID from ci_test_panels where te_group_id in ({$groups_id_str}) order by te_name asc");
	foreach ($testsids as $i => $tid) {
		$wpdb->update('ci_test_panels', array('te_position' => $i + 1), array('te_ID' => $tid));
	}
	wp_redirect(Tr_Base_Class_V6::link(array('act' => ''), false));
	exit;
} else if ($act == 'pdelete' && $can_delete) {
	$te_id = $_REQUEST['idd'];
	$panel = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM ci_test_panels WHERE te_ID = %d",
		$te_id
	), 'ARRAY_A');

	if ($panel) {
		$changed_arr = array_merge(['id' => $te_id, 'name' => $panel['te_name']], $panel);
	} else {
		$changed_arr = ['id' => $te_id, 'name' => $panel['te_name']];
	}
	$wpdb->delete('ci_test_panels', array('te_ID' => $te_id));
	$wpdb->delete('ci_test_panels_cutoffs', array('cu_te_ID' => $te_id));
	trpdd_log_user(get_current_user_id(), 'delete_panel_' . $panel['te_type'], $changed_arr, $te_id, '', '', '', $te_id);
	Tr_Session_Class::add('Deleted test #' . $te_id);
} else if ($act == 'plate_wells') {
	echo '<form method="post">';
	echo '<textarea name="listabrevpwell" style="width:100%;height:200px"></textarea>';
	echo 'Num per well: <input type="number" value="1" name="numperwell"/>';
	echo '<input type="submit" class="button"/>';
	echo '</form>';
	exit;
} else if ($act == 'update_colors') {
	include(TRCPDD_PATH . 'inc/functions_fix.php');
	$rs = trpdd_sync_abx_test_colors(true);

	echo 'Updated colors for ' . count($rs) . ' tests.';
	exit;
} else if (isset($_POST['listabrevpwell'])) {

	$platemap = array();
	$numperwell = $_POST['numperwell'] > 0 ? $_POST['numperwell'] : 1;
	$cwell = 1;
	$csubwell = 1;
	foreach (explode("\n", $_POST['listabrevpwell']) as $t) {
		$t = strtoupper(trim($t));
		if (!empty($t)) {
			$platemap[$t] = 'well_' . $cwell . ($numperwell > 1 ? '_' . $csubwell : '');

			if ($csubwell == $numperwell) {
				$cwell++;
				$csubwell = 0;
			}
			$csubwell++;
		}
	}
}

$is_group_gramparent = false;
$gram_neg_group_id = 0;
$gram_pos_group_id = 0;

$where          = " te_type='{$tes_type}' ";
$orderby        = 'te.te_in_req desc, te.te_position asc, te.te_name asc';

if ($group_selected > 0) {
	$where .= " and (
		                te_group_id = {$group_selected}
		                or te_group_id in (select tg_ID from ci_test_groups where tg_parent = {$group_selected} )
		            ) ";

	if (isset($_GET['reset_position']) && isset($_GET['abc'])) {
		$orderby = 'te.te_name asc';
	} else {
		$orderby        = 'te.te_in_req desc,tg.tg_position asc,tg.tg_ID asc,te.te_in_req desc, te.te_position asc, te.te_name asc';
	}

	if (isset($_GET['movegramposneg'])) {

		$gram_neg_group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
		where  tg_parent = {$group_selected} and tg_name like '%gram%neg%' ");

		$gram_pos_group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
		where  tg_parent = {$group_selected} and tg_name like '%gram%pos%' ");

		$is_group_gramparent = $gram_neg_group_id + $gram_pos_group_id > 0;
	}
} else if ($group_selected == -1) {
} else if ($group_selected == -2) {
	$where .= " and te_group_id in 
		        (
		            select tg_ID from ci_test_groups 
		                where ((tg_show_req = 0 or tg_show_req is null) and tg_parent = 0)
		                or tg_parent in (select tg_ID from ci_test_groups where (tg_show_req = 0 or tg_show_req is null) )
		        ) ";
	$orderby = 'te.te_in_req desc, te.te_name asc';
} else {
	$where .= " and te_group_id in 
		        (
		            select tg_ID from ci_test_groups 
		                where tg_show_req = 1
		                or tg_parent in (select tg_ID from ci_test_groups where tg_show_req = 1)
		        ) ";
	$orderby = 'te.te_in_req desc, te.te_name asc';
}

if ($tes_type == 'pcr' && $group_selected > 0 && !empty($show_cutoffs)) {
	$group_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT tg_ID FROM ci_test_groups WHERE tg_ID = %d OR tg_parent = %d",
		$group_selected,
		$group_selected
	));

	if (!empty($group_ids)) {
		$group_placeholders = implode(',', array_fill(0, count($group_ids), '%d'));
		$wpdb->query($wpdb->prepare(
			"UPDATE ci_test_panels_cutoffs cu
			JOIN ci_test_panels te ON te.te_ID = cu.cu_te_ID
			SET cu.cu_btype = %s
			WHERE te.te_type = %s
			AND te.te_group_id IN ({$group_placeholders})
			AND (cu.cu_btype IS NULL OR cu.cu_btype = '' OR cu.cu_btype != %s)",
			array_merge(array($tes_type, $tes_type), $group_ids, array($tes_type))
		));
	}
}

$joins = '';
$morefields = '';


$abx_tests 		= array(
	'bac' => array(),
	'bac_active' => array(),
	'marker' => array(),
	'marker_active' => array(),
);

$abx_type		= '';
$bac_options 	= array();
$mkr_options    = array();

if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'new_abx') && isset($_GET['gr']) && $_GET['gr'] > 0) {


	$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$_GET['gr']} 
					and tg_other_options like '%new_abx\":\"1%' ");


	if ($group) {

		$other_options = json_decode($group->tg_other_options, true);

		$abx_type = 'uti';
		if (!empty($other_options['abx_type'])) {
			$abx_type = $other_options['abx_type'];
		} else if (stripos($group->tg_name, 'wound') !== false) {
			$abx_type = 'wound';
		} else if (stripos($group->tg_name, 'gi') !== false) {
			$abx_type = 'gi';
		} else if (stripos($group->tg_name, 'Respiratory') !== false) {

			$abx_type = 'r';
		}
	} else {
		$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$_GET['gr']} 
					and tg_parent = 0 and tg_antibiotic = 1 ");
		if ($group) {
			$abx_type = 'maker';
		}
	}

	$abx_bac_mk_active_ids = array('bac' => array(), 'marker' => array());

	if (!empty($abx_type)) {

		$abx_bac_mk_key	= '_abx_' . $group->tg_ID . '_bac_mk_active_ids';
		$abx_bac_mk_active_ids = get_option($abx_bac_mk_key, array());

		$rs = $wpdb->get_results("select * from ci_abx_bacterias");
		foreach ($rs as $r) {
			$abx_tests['bac'][strtolower($r->bacteria_name)] = $r->ID;
			$abx_tests['bac_active'][$r->ID] = isset($r->{$abx_type}) ? $r->{$abx_type} : 0;
		}


		$rs = $wpdb->get_results("select * from ci_abx_resistance_markers");
		foreach ($rs as $r) {
			$abx_tests['marker'][strtolower($r->resistance_marker)] = $r->ID;
			$abx_tests['marker_active'][$r->ID] = isset($r->{$abx_type}) ? $r->{$abx_type} : 0;
		}

		if (isset($abx_bac_mk_active_ids['bac']) && is_array($abx_bac_mk_active_ids['bac'])) {
			foreach ($abx_bac_mk_active_ids['bac'] as $bacid) {
				$abx_tests['bac_active'][$bacid] = 1;
			}
		}

		if (isset($abx_bac_mk_active_ids['bac']) && is_array($abx_bac_mk_active_ids['marker'])) {
			foreach ($abx_bac_mk_active_ids['marker'] as $mkid) {
				$abx_tests['marker_active'][$mkid] = 1;
			}
		}
		$abx_bac_mk_active_ids = array('bac' => array(), 'marker' => array());

		if (empty($abx_tests['bac']) || empty($abx_tests['marker'])) {
			Tr_Session_Class::add_error('ABX not install correct! Try refresh now.');
			//add cron cron_update_abx
			do_action('cron_update_abx');
			$abx_type = '';
		}

		$joins .= " left join ci_test_meta m1 ON m1.test_id = te.te_ID and m1.meta_key='bac_tests' ";
		$joins .= " left join ci_test_meta m2 ON m2.test_id = te.te_ID and m2.meta_key='mkr_tests' ";

		$morefields .= ', m1.meta_value as bac_tests';
		$morefields .= ', m2.meta_value as mkr_tests';

		//update type = 0
		//$wpdb->query("update ci_abx_resistance_markers set {$abx_type} = 0 ");
		//$wpdb->query("update ci_abx_bacterias set {$abx_type} = 0 ");

		$bac_options = $wpdb->get_results(
			"select ID, 
			bacteria_name as name 
			from ci_abx_bacterias 
			order by bacteria_name asc",
			ARRAY_A
		);

		$mkr_options = $wpdb->get_results(
			"select ID, 
			resistance_marker as name 
			from ci_abx_resistance_markers 
			order by resistance_marker asc",
			ARRAY_A
		);
	}
}


if (!$is_ci_admin) {
	$abx_type = '';
}


$sql = "select te.*,
        (select count(*) from ci_test_panels_cutoffs cut where cut.cu_te_ID = te.te_ID) as count_ms,
		tg.tg_name,
		tg.tg_antibiotic,
		tg.tg_parent
		{$morefields}
        from ci_test_panels te
		JOIN ci_test_groups tg On tg.tg_ID = te.te_group_id
		{$joins}
        where {$where} 
		group by te.te_ID
        order by {$orderby}";
$tes = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
	echo $sql;
}
$max_length = 0;


$rsgroups = $wpdb->get_results("select *,
IF(t.tg_parent>0,(select t2.tg_name from ci_test_groups t2 where t2.tg_ID = t.tg_parent limit 1),'') as parent_name
from ci_test_groups t
where tg_type in ({$group_types_str})
order by tg_show_req desc, tg_position asc");


$group_types = array();
$groups_options = array(
	'' => array('name' => 'All Active Groups', 'childs' => array()),
	'-2' => array('name' => 'All Inactive Groups', 'childs' => array()),
	'-1' => array('name' => 'All Groups', 'childs' => array()),
);
foreach ($rsgroups as $group) {
	if ($group->tg_parent > 0) {
		$groups_options[$group->tg_parent]['childs'][$group->tg_ID] = $group->tg_name;
	} else {
		if (isset($groups_options[$group->tg_ID])) {
			$groups_options[$group->tg_ID]['name'] = $group->tg_name;
		} else {
			$groups_options[$group->tg_ID] = array(
				'name' => $group->tg_name,
				'childs' => array(),

			);
		}
	}

	if (!empty($group->parent_name)) {
		$group->tg_name .= ' ( ' . $group->parent_name . ' )';
	}


	$group_types[$group->tg_ID] = $group->tg_type;
}

if (isset($_GET['testnames'])) {
	$testsarr = array();
	foreach ($tes as $t) {
		$testsarr[] = $t->te_abrev;
	}
	$rs = tr_client_send_hub(array(
		'action' 	=> 'testnames',
		'abrevs' 	=> $testsarr,
		'btype'		=> $tes_type,
	));

	if (is_array($rs) && $rs['status'] == 'ok') {

		foreach ($tes as $i => $t) {
			if ($t->te_abrev == $t->te_name && isset($rs['tests'][$t->te_abrev])) {
				$tes[$i]->te_name = $rs['tests'][$t->te_abrev];
				$wpdb->update('ci_test_panels', array('te_name' => $tes[$i]->te_name), array('te_ID' => $t->te_ID));
			}
		}
	}
}

$speciments = $wpdb->get_results("select * from ci_test_specimens where ts_type = '{$tes_type}' order by ts_ID asc", 'ARRAY_A');
$unique_speciments = array();
$all_speciments = array();
foreach ($speciments as $sp) {
	if (!isset($unique_speciments[$sp['ts_name']])) {
		$unique_speciments[$sp['ts_name']] = $sp['ts_ID'];
	}
	$all_speciments[$sp['ts_ID']] = $sp['ts_name'];
}


?>
<div class="wrap">
	<h2 style="line-height: 40px;">
		<?php include(trpdd_get_file_path('panel', 'title_actions')) ?>
	</h2>
	<?php Tr_Session_Class::show() ?>

	<?php if (count($groups_options) > 0) { ?>
		<form method="get">
			<?php
			if (isset($_GET['page'])) {
				echo '<input type="hidden" name="page" value="' . $_GET['page'] . '"/>';
			}
			if (isset($_GET['pagetab'])) {
				echo '<input type="hidden" name="pagetab" value="' . $_GET['pagetab'] . '"/>';
			}
			if (isset($_GET['tab'])) {
				echo '<input type="hidden" name="tab" value="' . $_GET['tab'] . '"/>';
			}
			?>

			<select name="gr">
				<?php
				foreach ($groups_options as $groupid => $arr) {
					$selected = $groupid == $group_selected ? 'selected' : '';
					echo '<option value="' . $groupid . '" ' . $selected . '>' . $arr['name'] . '</option>';
					foreach ($arr['childs'] as $gcid => $cname) {
						$selected = $gcid == $group_selected ? 'selected' : '';
						echo '<option value="' . $gcid . '" ' . $selected . '> - &nbsp;&nbsp;' . $cname . '</option>';
					}
				}
				?>
			</select>
			<?php
			if ($tes_type == 'pcr' && $group_selected > 0) {
				$inss = $wpdb->get_col("select DISTINCT(cu.cu_ms) from ci_test_panels_cutoffs cu
				join ci_test_panels te on te.te_ID = cu.cu_te_ID
				join ci_test_groups tg on tg.tg_ID = te.te_group_id
				where (	te.te_group_id = {$group_selected}
						or te.te_group_id in (select tg_ID from ci_test_groups where tg_parent = {$group_selected} )
						)
					and cu_btype = '{$tes_type}' and cu_ms!='' ");

				echo '<select name="showcutoffs" onchange="this.form.submit()">';
				echo '<option value="">Select Instrument</option>';
				if (!in_array('*', $inss)) {
					$inss[] = '*';
				}
				foreach ($inss as $ins1) {
					$selected = $show_cutoffs == $ins1 ? 'selected' : '';
					echo '<option value="' . $ins1 . '" ' . $selected . '>' . $ins1 . '</option>';
				}
				echo '</select>';
			}
			?>
			<input type="submit" class="button" value="Filter" />
			<?php
			if ($group_selected > 0 && tr_current_role_in(array('administrator', 'AdminCIDW'))) {
				echo ' <a class="button" href="' . Tr_Base_Class_V6::link(array('act' => 'reposition'), false) . '">Alphabetize</a>';
				echo ' <a title="Fill a Text to list" class="button thickbox" href="' . Tr_Base_Class_V6::link(array('act' => 'plate_wells', 'trajax' => 1), false) . '">plate Wells</a>';
				if (!empty($abx_type)) {
					echo ' <a title="Update colors from Hub" class="button thickbox" href="' . Tr_Base_Class_V6::link(array('act' => 'update_colors', 'trajax' => 1), false) . '">Update colors</a>';
				}
			}
			?>
		</form>
	<?php } ?>

	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead class="sticky">
				<tr>
					<th style="width:20px">#</th>
					<th style="width:200px">Name</th>
					<?php if (!empty($abx_type)) echo '<th style="width:180px">ABX Map</th><th style="width:140px">ABX Name</th><th style="width:40px">Color</th>' ?>
					<th style="width:90px">Abreviation</th>
					<th style="width:90px">Abreviation2</th>
					<th style="width:60px">Well</th>
					<th style="width:90px">Loinc Code</th>
					<th style="width:90px">SNOMED</th>
					<?php
					$i_instrpos = 0;
					$showed_keys = array();
					foreach ($define_instruments as $ins => $iarr) {
						if (isset($iarr['add_fields']) && is_array($iarr['add_fields'])) {
							foreach ($iarr['add_fields'] as $tf) {
								$passbtype = is_array($iarr['btype']) ? in_array($tes_type, $iarr['btype']) : $tes_type == $iarr['btype'];
								if ($passbtype && tr_get_option('ci_pdf_data', $ins) && strpos($tf, 'te_') === 0 && !in_array($tf, $showed_keys)) {

									$showed_keys[] = $tf;
									$i_instrpos++;
									echo '<th style="width:100px">' . $iarr['name'] . '</th>';
								}
							}
						}
					}
					?>
					<th style="width:120px">Group</th>
					<?php
					if (in_array($tes_type, array('tox'))) { ?>
						<th style="width:40px">Low Risk</th>
						<th style="width:50px">Moderate Risk</th>
						<th style="width:40px">High Risk</th>
						<th style="width:100px">Comprehensive</th>
					<?php } ?>
					<?php if ($show_cutoffs && $tes_type == 'pcr') {
						if ($result_settings['enable_verylh']) {
							echo '<th style="width:90px">Very Low</th>';
						}
						echo '<th style="width:90px">Low</th>';
						echo '<th style="width:90px">Medium</th>';
						echo '<th style="width:90px">High</th>';
						if ($result_settings['enable_verylh']) {
							echo '<th style="width:90px">Very High</th>';
						}
					} ?>
					<th style="width:30px">Ins</th>
					<?php if ($group_selected > 0) { ?>
						<th style="width:50px">Position</th>
					<?php } ?>
				</tr>
			</thead>
			<tbody>
				<?php
				$last_pos 		= 0;
				$has_changeabx  = 0;
				$max_position   = 0;

				foreach ($tes as $i => $te) {

					$update_test = array();
					$tlength = strlen($te->te_name);
					if ($tlength > $max_length) {
						$max_length = $tlength;
					}
					if ($group_selected > 0 && ($te->te_position == 0 || isset($_REQUEST['reset_position']))) {
						$te->te_position = $last_pos + 1;
						$update_test['te_position'] = $te->te_position;
					}
					if (isset($_REQUEST['add_well'])) {
						$update_test['te_abrev2'] = 'well_' . $te->te_position;
					}
					if ($te->te_hide_pdf && $te->te_position < $max_position) {
						$update_test['te_position'] = 2 + $max_position++;
						$te->te_position = $update_test['te_position'];
					}
					$max_position = max($max_position, $te->te_position);
					$last_pos = $te->te_position;
					if (isset($_REQUEST['fixtestname'])) {
						if (stripos($te->te_name, "\t") > 0) {
							list($te->te_name, $te->te_abrev) = explode("\t", $te->te_name);
							$te->te_name = trim($te->te_name);
							$te->te_abrev = trim($te->te_abrev);
							$update_test['te_name'] = $te->te_name;
							$update_test['te_abrev'] = $te->te_abrev;
						}
					} else if (isset($_GET['removenamengoac'])) {
						if (strpos($te->te_name, '(') !== false) {
							$te->te_name = preg_replace("/\([^\)]*\)/", '', $te->te_name);
							$te->te_name = trim($te->te_name);
							if (!empty($te->te_name)) {
								$update_test['te_name'] = $te->te_name;
							}
						}
					} else if (isset($_GET['auto_abrev'])) {
						if (empty($te->te_abrev) && strpos($te->te_name, '(') !== false) {
							preg_match("/\(([^\)]*)\)/", $te->te_name, $matches);
							if (isset($matches[1])) {
								$te->te_abrev = trim($matches[1]);
								$update_test['te_abrev'] = $te->te_abrev;
							}
						}
					}

					if (isset($platemap) && count($platemap) > 0 && isset($platemap[$te->te_abrev])) {
						$update_test['te_abrev2'] = $te->te_abrev2 = $platemap[$te->te_abrev];
					} else if (isset($_GET['reset_well'])) {
						$update_test['te_abrev2'] = $te->te_abrev2 = '';
					}


					if ($tes_type == 'blood') {
						$new_type_id = 0;
						if (empty($te->te_type_id)) {
							$new_type_id = isset($unique_speciments['Serum']) ? $unique_speciments['Serum'] : 0;
							if ($new_type_id > 0) {
								$update_test['te_type_id'] = $new_type_id;
							}
						} else if ($te->te_type_id > 0 && isset($all_speciments[$te->te_type_id])) {
							$te->te_specimen_name = $all_speciments[$te->te_type_id];
							$new_type_id = isset($unique_speciments[$te->te_specimen_name]) ? $unique_speciments[$te->te_specimen_name] : 0;
							if ($new_type_id > 0 && $new_type_id != $te->te_type_id) {
								$update_test['te_type_id'] = $new_type_id;
							}
						}
					}

					if (count($update_test) > 0) {
						$wpdb->update('ci_test_panels', $update_test, array('te_ID' => $te->te_ID));
					}
					if ($te->te_end > 1000 && $te->te_end < $today) {
						$te->te_in_req = 0;
					}



					$class = '';
					$title = '';
					$foundabxtestids = array();
					if ($te->te_in_req == 0) {
						$class .= ' notfortoxreq ';
					} else if (!empty($abx_type)) {
						//need check tested variants
						$ismarker = $te->tg_antibiotic || stripos($te->tg_name, 'antibiotic') !== false ? 1 : 0;

						$bmkey 	= $ismarker ? 'marker' : 'bac';
						$testkey = $ismarker ? 'mkr_tests' : 'bac_tests';
						$bmtable = $ismarker ? 'ci_abx_resistance_markers' : 'ci_abx_bacterias';


						if (!empty($te->{$testkey})) {
							//search testid
							$foundabxtestids = explode(',', $te->{$testkey});
						} else if (isset($abx_tests[$bmkey][strtolower($te->te_name)])) {
							$foundabxtestids[] = $abx_tests[$bmkey][strtolower($te->te_name)];
						} else if (isset($abx_tests[$bmkey][strtolower($te->te_abrev)])) {
							$foundabxtestids[] = $abx_tests[$bmkey][strtolower($te->te_abrev)];
						}

						if (count($foundabxtestids) == 0) {
							$class .= ' notabxtest ';
							$title = 'Different ' . ($ismarker ? 'Marker' : 'Bacteria') . ' Name';
						} else {
							foreach ($foundabxtestids as $abxid) {
								if (isset($abx_tests[$bmkey . '_active'][$abxid])) {
									if (!$abx_tests[$bmkey . '_active'][$abxid]) {
										$has_changeabx++;
										$wpdb->update($bmtable, array($abx_type => 1), array('ID' => $abxid));
										echo $te->te_name;
										echo '<br>';
										echo $bmkey . '-' . $abxid;
										echo '<br>';
									}
									$abx_bac_mk_active_ids[$bmkey][] = $abxid;

									unset($abx_tests[$bmkey . '_active'][$abxid]);
								}
							}
							$title = count($foundabxtestids);
						}
					}

					if ($te->tg_parent > 0 && empty($group_selected)) {
						$te->tg_name .= ' (' . $groups_options[$te->tg_parent]['name'] . ')';
					}


					if (isset($_GET['add_cutoff'])) {

						$colkey = $_GET['key'];
						$colvl = $_GET['vl'];
						if (!empty($colkey) && !empty($colvl)) {
							if ($colkey == 'verylow') $colkey = 'cu_critical_low';
							else if ($colkey == 'low') $colkey = 'cu_cutoff';
							else if ($colkey == 'med') $colkey = 'cu_medium';
							else if ($colkey == 'high') $colkey = 'cu_upper_limit';
							else if ($colkey == 'veryhigh') $colkey = 'cu_critical_high';

							$exiscoff = $wpdb->get_var("select count(*) from ci_test_panels_cutoffs 
							where cu_te_ID = {$te->te_ID} and cu_ms='*' and cu_btype = '{$tes_type}' ");
							if ($exiscoff) {
								$wpdb->update('ci_test_panels_cutoffs', array(
									$colkey => $colvl
								), array(
									'cu_te_ID' => $te->te_ID,
									'cu_ms' => '*',
									'cu_btype' => $tes_type
								));
							} else {
								$wpdb->insert('ci_test_panels_cutoffs', array(
									'cu_te_ID' => $te->te_ID,
									'cu_ms' => '*',
									'cu_btype' => $tes_type,
									$colkey => $colvl
								));
							}
						}
					}

					if (isset($_GET['movegramposneg']) && $group_selected > 0 && $is_group_gramparent && $te->te_group_id == $group_selected) {
						$new_group_id = 0;
						if (stripos('Streptococcus pyogenes,Corynebacterium urealyticum,Enterococcus faecalis,Enterococcus faecium,Mycobacteroides abcessus,Staphylococcus aureus,Staphylococcus epidermidis,Staphylococcus saprophyticus,Streptococcus agalactiae,Streptococcus anginosus', $te->te_name) !== false) {
							$new_group_id = $gram_pos_group_id;
						} else if (stripos('Bacteroides sp.,Proteus vulgaris,Citrobacter braakii,Acinetobacter baumannii,Bacteroides fragilis,Citrobacter freundii/braakii,Citrobacter koseri,Enterobacter cloacae,Escherichia coli,Klebsiella aerogenes,Klebsiella oxytoca/michiganensis,Klebsiella pneumoniae,Morganella morganii,Mycoplasma hominis,Prevotella bivia,Proteus mirabilis,Pseudomonas aeruginosa,Serratia marcescens,Ureaplasma urealyticum', $te->te_name) !== false) {
							$new_group_id = $gram_neg_group_id;
						}

						if ($new_group_id > 0) {
							$wpdb->update('ci_test_panels', array('te_group_id' => $new_group_id), array('te_ID' => $te->te_ID));
						}
					}
				?>
					<tr class="<?php echo $class; ?>" data-id="<?php echo $te->te_ID ?>">
						<td class="testid" title="<?php echo $title ?>"><?php echo $te->te_ID ?></td>
						<td>
							<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $te->te_ID)) ?>"><?php echo $te->te_name ?></a>
							<div class="row-actions">
								<a href="<?php Tr_Base_Class_V6::link(array('act' => 'duplicate', 'id' => $te->te_ID)) ?>">Duplicate</a>
								<?php if ($can_delete) { ?>
									| <a class="pdelete" href="#" title="there are results with this test" data-id="<?php echo  $te->te_ID ?>">Delete</a>
								<?php } ?>
							</div>
						</td>
						<?php if (!empty($abx_type)) {
							$panelmeta = array();
							$panelmeta['abx_name'] = $wpdb->get_var("select meta_value from ci_test_meta where test_id = {$te->te_ID}  and meta_key='abx_name'");
							if ($ismarker) {
								$panelmeta['mkr_tests'] = $wpdb->get_var("select meta_value from ci_test_meta where test_id = {$te->te_ID}  and meta_key='mkr_tests'");
								$panelmeta['mkr_tests'] = !empty($panelmeta['mkr_tests']) ? explode(',', $panelmeta['mkr_tests']) : array();
								if (empty($panelmeta['mkr_tests']) && !empty($foundabxtestids)) {
									$panelmeta['mkr_tests'] = $foundabxtestids;
									trpdd_update_test_meta($te->te_ID, 'mkr_tests', implode(',', $foundabxtestids));
								}
							} else {
								$panelmeta['bac_tests'] = $wpdb->get_var("select meta_value from ci_test_meta where test_id = {$te->te_ID}  and meta_key='bac_tests'");
								$panelmeta['bac_tests'] = !empty($panelmeta['bac_tests']) ? explode(',', $panelmeta['bac_tests']) : array();
								if (empty($panelmeta['bac_tests']) && !empty($foundabxtestids)) {
									$panelmeta['bac_tests'] = $foundabxtestids;
									trpdd_update_test_meta($te->te_ID, 'bac_tests', implode(',', $foundabxtestids));
								}
							}
						?>

							<?php
							if ($ismarker) {
								//echo '<select class="js_select2 editfield" data-k="meta_mkr_tests" multiple>';

								echo '<td class="editable" data-k="meta_mkr_tests" data-multiple="1" data-type="select" data-source="abx_marker">';
								foreach ($mkr_options as $abxarr) {
									$selected = in_array($abxarr['ID'], $panelmeta['mkr_tests']) ? 'selected' : '';
									//echo '<option value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</option>';
									if ($selected) {
										echo '<li value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</li>';
									}
								}
								echo '</td>';
								//echo '</select>';
							} else {
								//echo '<select class="js_select2 editfield" data-k="meta_bac_tests" multiple>';
								echo '<td class="editable" data-k="meta_bac_tests" data-multiple="1" data-type="select" data-source="abx_bacteria">';
								foreach ($bac_options as $abxarr) {
									$selected = in_array($abxarr['ID'], $panelmeta['bac_tests']) ? 'selected' : '';
									//echo '<option value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</option>';
									if ($selected) {
										echo '<li value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</li>';
									}
								}
								//echo '</select>';
								echo '</td>';
							}
							?>


							<td class="editable" title="click to edit" data-k="meta_abx_name"><?php echo $panelmeta['abx_name'] ?></td>
							<td class="editable" title="click to edit" data-k="te_color" data-type="color" data-value="<?php echo $te->te_color ?>" style="background:<?php echo $te->te_color ?>;"></td>
						<?php } ?>
						<td class=" editable" title="click to edit" data-k="te_abrev"><?php echo $te->te_abrev ?></td>
						<td class="editable" title="click to edit" data-k="te_abrev2"><?php echo $te->te_abrev2 ?></td>
						<td class="editable" title="click to edit" data-k="te_well_numbers"><?php echo $te->te_well_numbers ?></td>
						<td class="editable" title="click to edit" data-k="te_loinc_code"><?php echo $te->te_loinc_code ?></td>
						<td class="editable" title="click to edit" data-k="te_snomed_code"><?php echo $te->te_snomed_code ?></td>
						<?php
						$i_instrpos = 0;
						$showed_keys = array();
						foreach ($define_instruments as $ins => $iarr) {
							if (isset($iarr['add_fields']) && is_array($iarr['add_fields'])) {
								foreach ($iarr['add_fields'] as $tf) {
									$passbtype = is_array($iarr['btype']) ? in_array($tes_type, $iarr['btype']) : $tes_type == $iarr['btype'];
									if ($passbtype && tr_get_option('ci_pdf_data', $ins) && strpos($tf, 'te_') === 0 && !in_array($tf, $showed_keys)) {

										$showed_keys[] = $tf;
										$i_instrpos++;
										echo '<td class="editable" title="click to edit" data-k="' . $tf . '">' . $te->{$tf} . '</td>';
									}
								}
							}
						}
						?>
						<td><a target="_blank" href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'groups', 'tab' => $group_types[$te->te_group_id], 'type' => $group_types[$te->te_group_id], 'act' => 'edit', 'id' => $te->te_group_id)) ?>"><?php echo $te->tg_name ?></a></td>
						<?php

						if (in_array($tes_type, array('tox'))) { ?>
							<td><?php echo ($te->te_basic) ? 'x' : ' ' ?></td>
							<td><?php echo ($te->te_standard) ? 'x' : ' ' ?></td>
							<td><?php echo ($te->te_highrisk) ? 'x' : ' ' ?></td>
							<td><?php echo ($te->te_comprehensive) ? 'x' : ' ' ?></td>
						<?php } ?>

						<?php if ($show_cutoffs && $tes_type == 'pcr') {
							$sql_cutoffs = "select *
							from ci_test_panels_cutoffs 
							where cu_te_ID = {$te->te_ID}
							and cu_ms = '{$show_cutoffs}'
							and cu_btype = '{$tes_type}'
							order by created desc, cu_start desc
							limit 1
							";
							$cut    = $wpdb->get_row(
								$sql_cutoffs,
								ARRAY_A
							);
							if ($result_settings['enable_verylh']) {
								echo '<td class="editable" data-k="cu_critical_low__' . $show_cutoffs . '">' . trpdd_show_symbol($cut['cu_critical_low']) . '</td>';
							}
							echo '<td class="editable" data-k="cu_cutoff__' . $show_cutoffs . '">' . trpdd_show_symbol($cut['cu_cutoff']) . '</td>';
							echo '<td class="editable" data-k="cu_medium__' . $show_cutoffs . '">' . trpdd_show_symbol($cut['cu_medium']) . '</td>';
							echo '<td class="editable" data-k="cu_upper_limit__' . $show_cutoffs . '">' . trpdd_show_symbol($cut['cu_upper_limit']) . '</td>';
							if ($result_settings['enable_verylh']) {
								echo '<td class="editable" data-k="cu_critical_high__' . $show_cutoffs . '">' . trpdd_show_symbol($cut['cu_critical_high']) . '</td>';
							}
							$te->count_ms = $show_cutoffs;
						} ?>
						<td><?php echo $te->count_ms ?></td>
						<?php if ($group_selected > 0) { ?>
							<td class="editable" title="click to edit" data-k="te_position"><?php echo $te->te_position ?></td>
						<?php } ?>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</div>

	<div class="total">
		Total: <?php echo count($tes); ?>
	</div>

	<?php


	if (!empty($abx_type)) {

		echo '<script>';
		echo '$mkr_options = ' . json_encode($mkr_options) . ';';
		echo '$bac_options = ' . json_encode($bac_options) . ';';
		echo '</script>';

		if (count($abx_bac_mk_active_ids['bac']) > 0) {
			foreach ($abx_tests['bac_active'] as $bid => $acti) {
				if ($acti) {
					$wpdb->update('ci_abx_bacterias', array($abx_type => 0), array('ID' => $bid));
					$has_changeabx++;
				}
			}
		}

		if (count($abx_bac_mk_active_ids['marker']) > 0) {
			foreach ($abx_tests['marker_active'] as $bid => $acti) {
				if ($acti) {
					$wpdb->update('ci_abx_resistance_markers', array($abx_type => 0), array('ID' => $bid));
					$has_changeabx++;
				}
			}
		}

		update_option($abx_bac_mk_key, $abx_bac_mk_active_ids);

		if ($has_changeabx > 0) {
			update_option('ci_abx_last_build_' . $abx_type, 0);
			if ($abx_type == 'uti') {
				update_option('ci_abx_last_build_cuti', 0);
			}
		}

		echo '<br>';
		echo 'abx type: ' . $abx_type . '<br>';
		echo 'found bacteria: ' . count($abx_bac_mk_active_ids['bac'])  . '<br>';
		echo 'found marker: ' . count($abx_bac_mk_active_ids['marker'])  . '<br>';
		echo 'updated: ' . $has_changeabx . '<br>';
	}


	?>
</div>


<style>
	.testid {
		font-size: 8px !important;
	}

	.notfortoxreq {
		background: #ccc !important;
	}

	.notabxtest .testid {
		background: red;
	}

	td.editable:hover {
		background: yellow;
	}

	td>li {
		margin-left: 15px;
	}
</style>

<script>
	jQuery(function($) {
		function getRandomColor() {
			var letters = '0123456789ABCDEF';
			var color = '#';
			for (var i = 0; i < 6; i++) {
				color += letters[Math.floor(Math.random() * 16)];
			}
			return color;
		}


		$('td.editable').click(function() {
			var $this = $(this);
			var $fieldtype = $this.data('type') ? $this.data('type') : 'text';
			var $vl = $this.data('value') || $this.html();
			console.log($vl);
			if (!$this.data('editing')) {
				if ($fieldtype == 'color' && ($vl === '' || $vl === 'transparent')) {
					//$vl = getRandomColor();
				}
				if ($fieldtype == 'select') {
					var $k = $this.data('k');
					var $multiple = $this.data('multiple');
					var $source = $this.data('source');
					var $value = [];
					$('li', $this).each(function() {
						$value.push($(this).attr('value'));
					});
					var $select = $('<select class="editfield"></select>');
					if ($multiple == 1) {
						$select.attr('multiple', 'multiple');
					}
					$select.attr('data-k', $k);
					//build options
					var options = [];
					if ($source == 'abx_marker') {
						options = $mkr_options;
					} else if ($source == 'abx_bacteria') {
						options = $bac_options;
					}
					for (var i in options) {
						var abxarr = options[i];
						var selected = $value.indexOf(abxarr.ID) >= 0 ? 'selected' : '';
						$select.append('<option value="' + abxarr.ID + '" ' + selected + '>' + abxarr.name + '</option>');
					}
					$this.data('value', $vl);
					$this.html($select);
					$this.data('editing', true);
					$('select', $this).select2({
						minimumResultsForSearch: 1
					}).select2('open').focus();

				} else {
					$this.data('value', $vl);
					$this.html('<input type="' + $fieldtype + '" value="' + $vl + '"/>');
					$this.data('editing', true);
					$('input', $this).focus().click();
				}

			}
		})


		$('body').on('keyup change', 'td.editable > input', function(e) {
				var code = e.keyCode || e.which;
				if (typeof code === 'undefined') code = 13;
				var $this = $(this);
				var $pr = $(this).parent();
				var $tr = $pr.parent();

				console.log(code);
				if (code == 27) {
					$pr.data('editing', false);
					$pr.html($pr.data('value'));
				} else if (code == 13) {
					var $vl = $this.val();
					var $col = $pr.data('k');
					var $id = $tr.data('id');
					if ($('.sloading', $pr).length > 0) return false;

					$pr.append('<div class="sloading"></div>');
					$.ajax({
						data: {
							save_test: 1,
							trajax: 1,
							col: $col,
							id: $id,
							val: $vl
						},
						type: 'post',
						success: function(rs) {
							var $html = $(rs);
							if (rs.indexOf("updated success") > -1) {
								$pr.data('editing', false);
								$pr.data('value', $vl);
								if ($pr.data('type') == 'color') {
									$pr.html('');
									$pr.css('background', $vl);
								} else {
									$pr.html($vl);
								}

							} else {
								alert($html.find('.msg').html());
							}
							$('.sloading', $pr).remove();
						}
					});
					return false;
				}
			})
			.on('change', 'select.editfield', function() {
				var $this = $(this);
				var $pr = $(this).parent();
				var $tr = $pr.parents('tr');
				var $col = $(this).data('k');
				var $vl = $(this).val();
				var $id = $tr.data('id');
				$pr.append('<div class="sloading"></div>');
				$.ajax({
					data: {
						save_test: 1,
						trajax: 1,
						col: $col,
						id: $id,
						val: $vl
					},
					type: 'post',
					success: function(rs) {
						var $html = $(rs);
						if (rs.indexOf("updated success") > -1) {
							$pr.append('<span class="alert">saved</span>');
							setTimeout(function() {
								$('.alert', $pr).remove();
							}, 3000);
						} else {
							alert($html.find('.msg').html());
						}
						$('.sloading', $pr).remove();
					}
				});
			}).on('focusout', 'td.editable > input', function() {
				var $vl = $(this).val();
				var $pr = $(this).parent();
				$pr.data('editing', false);
				setTimeout(function() {
					if ($pr.data('type') == 'color') {
						$pr.html('');
					} else {
						$pr.html($vl);
					}
				}, 200);
			})

		$('a.delete').click(function() {
			if (confirm('Do you want delete?') == false) return false;
		})
		$('#cat_filter').change(function() {
			$(this).parents('form').submit();
		})
	})
</script>