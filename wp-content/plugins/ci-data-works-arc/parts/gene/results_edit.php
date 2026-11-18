<?php
global $wpdb, $report, $ci_cutoffs, $current_role;
$btype 			= 'gene';
$id             = $_GET['id'];
$user_id        = get_current_user_id();
$checked_types  = array();



$can_edit       = true;
$can_delete     = true;
$can_fax        = true;
$use_style      = 1;
$can_approve    = true;
$is_qc_form     = isset($_GET['qc']);
$has_pos        = null;
$has_manual_antibiotic = false;

$result_ids     = array();


$all_drugs              = array();
$in_panel_tests_requested = array();
$all_test_groups        = array();
$group_ids              = array();

$req_settings   = get_option('ci_req_settings_gene', array());

if ($current_role == 'results') {
	if (tr_get_option('ci_pdf_data', 'results_oview')) {
		$can_edit     = false;
		$can_delete   = false;
		$can_fax      = false;
	}
}

include_once(TRCPDD_PATH . 'pages/formdata.php');


$report = tr_pdd_get_report_by_id($id, $btype);

if (!$report['id'] && isset($_GET['createnew'])) {
	$mid = $_GET['createnew'];

	$bat = $wpdb->get_row("select * from ci_batches_gene where mid = {$mid} ");

	if ($bat) {
		$id = $wpdb->get_var("select id from ci_reports_gene where patient_id='{$bat->assen}'");
		if ($id == 0) {
			if (empty($bat->batch_id)) {
				$bat->batch_id = tr_pdd_get_current_batch_id();
				$wpdb->update('ci_batches_gene', array('batch_id' => $bat->batch_id), array('mid' => $mid));
			}

			$data = array(
				'batch_id' => $bat->batch_id,
				'patient_id' => $bat->assen,
				'created_date' => date_i18n('Y-m-d'),
				'created' => date_i18n('Y-m-d H:i:s'),
			);
			$wpdb->insert('ci_reports_gene', $data);
			$id = $wpdb->insert_id;
		}

		$report = tr_pdd_get_report_by_id($id, 'gene');
		echo '<meta http-equiv="refresh" content="1;url=' .
			Tr_Base_Class_V6::link(array('id' => $id, 'add_alltests' => true), false)
			. '" />';
		exit;
	}
}
if (!$report['id']) {
	echo '<h2>No Results Found!</h2>';
	return;
}

if ($report['cli_result_style_gene'] > 1) {
	$use_style = $report['cli_result_style_gene'];
}


foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$gid = str_ireplace('group_', '', $t);
		$group_ids[] = $gid;

		$group_childs = $wpdb->get_col("select tg_ID from ci_test_groups g 
			where g.tg_parent in (select tg_ID from ci_test_groups g2 where g2.tg_ID in ({$gid}) )");

		if (count($group_childs) > 0) {
			$group_ids = array_merge($group_ids, $group_childs);
		}
	}
}

$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}
$query_control_by = '';
if (isset($req_settings['enabel_test_control_by']) && $req_settings['enabel_test_control_by']) {
	$query_control_by = " or (te_control_bys!='' and te_control_bys is not null) ";
}

$groups_ids_selected = $group_ids;
$group_ids_str = implode(',', $group_ids);
if (empty($group_ids_str)) {
	$group_ids_str = '0';
}
$where_group = '';
$more_query_test = '';
if (count($group_ids) > 0) {
	$where_group .= " or ((tg_only_group = 1 or tg_notestcheckbox= 1 ) and tg_ID in ({$group_ids_str}) )";
	$more_query_test .= " or (tp.te_nocheckbox=1 and tp.te_group_id in ({$group_ids_str}))";
}

$drugkeys       = array('te_abrev2', 'te_abrev', 'te_name');
$all_res        = array();
$all_results_q  = array();
$date_test      = date('Y-m-d', strtotime($report['created']));
$where_testdate = " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
$where_testdate .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";


foreach ($drugkeys as $drugkey) {
	$sql = "SELECT *
        FROM ci_results_gene rs 
        join ci_test_panels tp ON tp.te_type='gene' and  tp.{$drugkey} = rs.drug and rs.drug!=''
        WHERE report_id='" . $id . "' 
        and (tp.te_ID in ({$test_ids}) 
            or tp.te_group_id in (select tg_ID from ci_test_groups where tg_aw_results = 1 {$where_group})
             {$more_query_test}
            )
        {$where_testdate}
        ";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	foreach ($all_results_q as $arr) {
		if (isset($all_res[$arr['te_ID']])) {
			continue;
		}
		$found_test_ids[] = $arr['te_ID'];

		$arr['dbdrug'] = $arr['drug'];
		$arr['drug'] = strtoupper($arr['te_name']);
		$all_res[$arr['te_ID']] = $arr;
		if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
			$in_panel_tests_requested[] = $arr['te_ID'];
		}

		if (!in_array($arr['te_group_id'], $group_ids)) {
			$group_ids[] = $arr['te_group_id'];
		}
	}
}

//check manual group
$has_test_ids = array_keys($all_res);
$has_test_ids = implode(',', $has_test_ids);
if (empty($has_test_ids)) $has_test_ids = '0';

$adding_groups = $wpdb->get_col("select tg_ID from ci_test_groups where tg_parent in ({$group_ids_str}) 
and tg_manual_edit = 1");
$group_ids_str_adding = $group_ids_str;
if (count($adding_groups) > 0) {
	$group_ids_str_adding = implode(',', array_merge($group_ids, $adding_groups));
}

$sql = "SELECT *
        FROM ci_test_panels tp 
        left join ci_results_gene rs ON  tp.te_name = rs.drug and report_id='" . $id . "'
        WHERE  tp.te_type='gene' and rs.id is null
        and tp.te_ID not in ({$has_test_ids}) 
		and (tp.te_group_id in (select tg_ID from ci_test_groups where tg_manual_edit=1 and tg_ID in ({$group_ids_str_adding}) ) {$query_control_by})
        and (
            tp.te_group_id in (select tg_ID from ci_test_groups where (tg_only_group = 1 or tg_notestcheckbox= 1) and tg_ID in ({$group_ids_str_adding}) ) 
            or tp.te_ID in ({$test_ids})
        )
        {$where_testdate}
        ";

$all_results_q = $wpdb->get_results($sql, ARRAY_A);
foreach ($all_results_q as $arr) {
	if (isset($all_res[$arr['te_ID']])) {
		continue;
	}
	$arr['dbdrug'] = $arr['drug'];
	$arr['drug'] = strtoupper($arr['te_name']);
	if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
		$in_panel_tests_requested[] = $arr['te_ID'];
	}
	$all_res[$arr['te_ID']] = $arr;

	if (!in_array($arr['te_group_id'], $group_ids)) {
		$group_ids[] = $arr['te_group_id'];
	}
}

if (isset($_GET['debug1'])) {
	var_dump($sql);
}

if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<br><br>';
	echo '<br><br>';
	var_dump($report);
	echo '<br><br>';
	var_dump($all_results_q);
	echo '<br><br><br>';
}
foreach ($in_panel_tests_requested as $t) {
	$t = strtoupper($t);
	$miss_test[$t] = $t;
}

$drug_groups = tr_pdd_get_drug_groups('gene', $group_ids, true);


if (isset($_GET['createddate'])) {
	$report['created_date'] = $_GET['createddate'];
}


$inew = 0;
foreach ($drug_groups as $groupid => $groupdata) {

	$group_types = array();
	if (!isset($groupdata['types']) || !is_array($groupdata['types'])) {
		$groupdata['types'] = array();
	}
	if (count($groupdata['types']) > 0) {
		foreach ($checked_types as $checked_type) {
			if (isset($groupdata['types'][$checked_type])) {
				$group_types[] = $groupdata['types'][$checked_type];
			}
		}
	}
	$groupdata['sample_type'] = implode(', ', $group_types);

	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

	if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
		$has_manual_antibiotic = true;
	}
	$groupdata['tests'] = array();

	foreach ($te_IDs as $te_ID) {
		if (!isset($all_res[$te_ID])) {
			continue;
		}
		$arr = $all_res[$te_ID];

		$arr['report_id'] = $id;
		$arr['manual_antibiotic'] = $groupdata['manual_antibiotic'];
		if (!empty($arr)) {
			if (!empty($arr['te_name'])) {
				$arr['drug'] = $arr['te_name'];
			}
			$arr['showed'] = true;
			$arr = tr_pdd_parse_gene_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);


			$all_res[$te_ID] = $arr;



			if (empty($arr['id'])) {
				$arr['id'] = 'new' . $inew++;
			} else {
				$result_ids[] = $arr['id'];
			}

			if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'))) {
				$has_pos = 1;
			} else if ($arr['pos-neg'] == 'NEG' && is_null($has_pos)) {
				$has_pos = 0;
			}


			$groupdata['tests'][$arr['drug']] = $arr;
		}

		if (in_array($arr['te_ID'], $in_panel_tests_requested)) {
			$all_test_groups[] = $arr['drug'];
		}
	}



	$drug_groups[$groupid] = $groupdata;

	if (count($groupdata['tests']) > 0 && in_array($groupdata['id'], $groups_ids_selected)) {
		$all_test_groups[] = $groupid;
	}
}

$update = array();
$update['has_pos'] = $has_pos;
$update['has_pos_modified'] = time();
$wpdb->update('ci_reports_gene', $update, array('id' => $id));

if (count($result_ids) > 0) {
	$result_ids_str = implode(',', $result_ids);
	$wpdb->query("update ci_results_gene set has_order=0 
	                where report_id = {$id} 
	                and id not in ({$result_ids_str}) ");
}



if ($report['estatus'] == 'C') {
	$can_approve = false;
}

if (isset($_REQUEST['debug'])) {
	var_dump($report);
	echo '<br><br>';
	var_dump($report['panel_tests_requested']);
	echo '<br><br>';
	echo 'Tests:';
	echo '<br><br>';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:';
	echo '<br><br>';
	var_export($all_res);
	echo '<br><br>';
	var_dump($drug_groups);
}


$pos_neg = array(
	'' => 'Blank',
	'NEG' => 'Negative',
	'POS' => 'Positive',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'INV' => 'Invalid',
);

$count_test_show = 0;
$count_all_test = count($in_panel_tests_requested);
$miss_test = array();

$csv_files = explode(',', $report['upload_files']);

$csv_file = '/wp-content/log/results_gene/' . end($csv_files);

?>

<style>
	.rowline .frow {
		border-bottom: 1px solid #ddd;
		padding-bottom: 4px;
		padding-top: 4px;
		margin-bottom: 0;
	}

	.in_medications {
		background: #80d4ff
	}

	.in_medications input,
	.in_medications textarea {
		background: rgba(128, 212, 255, 0.9);
	}

	.bg_red,
	.bg_red input,
	.bg_red textarea {
		background: #ff8080;
	}
</style>

<div class="wrap">

	<h2>Edit <small style="float:right">Style: <?php echo $use_style ?></small></h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">

		<div class="frow">
			<h4>Patient Info</h4>
		</div>

		<div class="frow">
			<div class="col4">
				<label title="<?php echo $report['mid'] ?>">Patient Name</label>
				<input type="text" value="<?php echo tr_pdd_capitalized($report['patient_name']) ?>" readonly>
			</div>
			<div class="col4">
				<label>Physician <small style="float:right"><?php echo $report['doc_npi'] ?></small></label>
				<input type="text" value="<?php echo $report['physician'] ?>" readonly>
			</div>

			<div class="col4">
				<label>Patient ID</label>
				<input type="text" value="<?php echo $report['assen'] ?>" readonly />
			</div>
			<div class="col4">
				<label>Lab</label>
				<input type="text" value="<?php echo $report['sublab'] ?>" readonly>
			</div>
		</div>

		<div class="frow">
			<div class="col4">
				<label>Patient DOB</label>
				<input type="text" placeholder="YYYY-mm-dd" value="<?php echo $report['patient_dob'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Patient Gender</label>
				<input type="text" value="<?php echo $report['pa_gender'] ?>">
			</div>

			<div class="col4">
				<label>Acquisition Date</label>
				<input type="text" class="" name="info[acquisition_date]" placeholder="YYYY-mm-dd" value="<?php echo $report['acquisition_date'] ?>">
			</div>
			<div class="col4">
				<label>Sample Type</label>
				<input type="text" value="<?php echo $report['sample_type'] ?>" readonly>
			</div>
		</div>

		<div class="frow">
			<div class="col4">
				<label>Collection Date</label>
				<input type="text" value="<?php echo $report['collection_date'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Received in Lab</label>
				<input type="text" class="hasdatetime" name="updatebat[spec_received]" value="<?php echo $report['spec_received'] ?>" />
			</div>
			<div class="col4">
				<label>Report Date</label>
				<input type="text" class="hasdatetime" name="info[approve_date]" value="<?php echo $report['approve_date'] ?>" />
			</div>
			<div class="col4">
				<label>Instrument <a target="_blank" href="<?php echo $csv_file ?>">.</a></label>
				<input type="text" value="<?php echo $report['instrument'] ?>" name="info[instrument]">
			</div>
		</div>
		<div class="header_fix_top"></div>
		<div class="header_fix" style="display: none;">
			<span><tt>P:</tt> <?php echo $report['patient_name'] ?> </span>
			<span><tt>D:</tt> <?php echo $report['physician'] ?></span>
			<span><tt>A:</tt> <a class="show_test_panels" href="#"><?php echo $report['patient_id'] ?></a></span>
			<span><tt>L:</tt> <?php echo $report['sublab'] ?></span>

			<span><tt>C:</tt> <?php echo $report['collection_date'] ?></span>
			<span><tt>M:</tt> <?php echo $report['medications'] ?></span>
			<span><a target="_blank" href="?page=gene_req_form&act=edit&id=<?php echo $report['mid'] ?>">.</a></span>
		</div>
		<div class="list_test_panels" style="display: none;">
			<div><?php echo implode(', ', $all_test_groups); ?></div>
		</div>


		<?php
		if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
			$rejected = tr_pdd_get_batch_statuses($report['batch_status']);
		?>
			<h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
			<div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
			<?php
		} else {
			if ($has_manual_antibiotic) {

				$vlkeys = array(
					'' => '',
					'I' => 'I',
					'R' => 'R',
					'S' => 'S'
				);

				$anti_pathogen = array();
				foreach ($drug_groups as $groupid => $groupdata) {
					if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
						foreach ($groupdata['tests'] as $arr) {
							if (empty($arr['te_name']) || empty($arr['te_ID'])) continue;
							$anti_pathogen[$arr['te_name']] = $arr['te_ID'];
						}
					}
				}
				//more pathgeno
				$morepathogens = $wpdb->get_results("select te_ID,te_name from ci_test_panels where te_type='pats'");
				foreach ($morepathogens as $r) {
					$anti_pathogen[$r->te_name] = $r->te_ID;
				}
				ksort($anti_pathogen);

				$repdb = $wpdb->get_row("select * from ci_reports_gene where id = {$report['id']}");
				$show3pathogen_note = false;
				$shownogrowth_note = false;
				if ($repdb) {
					$show3pathogen_note = $repdb->show3pathogen_note;
					$shownogrowth_note = $repdb->shownogrowth_note;
				}

				$antibiotics = $wpdb->get_results("select ID,ta_name 
                                            from ci_test_antibiotics 
											where (ta_start_date='' or ta_start_date <= '{$report['created_date']}' )
											and (ta_end_date = '' or ta_end_date > '{$report['created_date']}' )
                                            order by ta_name asc");

				$mic_data = array();
				$mic_rs = $wpdb->get_results("select ra.*
                                                from ci_results_antibiotics ra
                                                where report_id = {$id} 
                                                and btype = 'gene'
                                                and ra.te_ID = -1");
				foreach ($mic_rs as $r) {
					$mic_data[$r->ta_ID] = $r->result;
				}

				$anti_rs = $wpdb->get_results("select ra.*,te.te_name
                                                from ci_results_antibiotics ra
                                                join ci_test_panels te ON te.te_ID = ra.te_ID
                                                where report_id = {$id}
                                                and btype = 'gene'
                                                order by te_name asc");

				$labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");


				$anti_data = array();
				foreach ($anti_rs as $r) {
					$kt = 'k' . $r->te_ID;
					if (!isset($anti_data[$kt])) {
						$anti_data[$kt] = array(
							'te_ID' => $r->te_ID,
							'name' => $r->te_name,
							'rs' => array()
						);
					}
					$anti_data[$kt]['rs'][$r->ta_ID] = $r->result;
				}

				foreach ($labresults as $r) {
					$kt = 'k' . $r->te_ID;
					if (!isset($anti_data[$kt])) {
						$anti_data[$kt] = array(
							'te_ID' => $r->te_ID,
							'name' => $r->te_name,
							'rs' => array()
						);
					}
					$anti_data[$kt]['labresult'] = $r->result;
				}

			?>
				<div class="frow">
					<h4>Antibiotics</h4>
				</div>

				<select id="list_pathogen"></select>

				<a class="button add_antibiotics">+</a>
				<label style="padding-left: 16px;">
					<input type="hidden" name="info[show3pathogen_note]" value="0" />
					<input type="checkbox" name="info[show3pathogen_note]" value="1" <?php checked($show3pathogen_note) ?> /> 3 or more pathogen note
				</label>
				<label style="padding-left: 16px;">
					<input type="hidden" name="info[shownogrowth_note]" value="0" />
					<input type="checkbox" name="info[shownogrowth_note]" value="1" <?php checked($shownogrowth_note) ?> /> No Growth
				</label>
				<div id="display_antibiotics" style="margin:10px 0;display:none"></div>
				<input type="hidden" name="has_antibiotics" value="1" />
				<script>
					var $vlkeys = <?php echo json_encode($vlkeys) ?>;
					var $anti_pathogen = <?php echo json_encode($anti_pathogen) ?>;
					var $antibiotics = <?php echo json_encode($antibiotics) ?>;
					var $anti_data = <?php echo json_encode($anti_data) ?>;
					var $mic_data = <?php echo json_encode($mic_data) ?>;
					var $symbols = ['>', '<', '>=', '<='];

					jQuery(function($) {
						function tr_fill_antibio_drugcol() {
							var $html = '<div class="wraptbl"><table class="tbl_results">';
							$html += '<thead class="sticky top2 style2"><tr class="group_header"><th class="drugs_col" style="width:20%">DRUGS</th>';
							$html += '<th style="width:20%">MIC Data</th>';
							$html += '</tr></thead>';
							$html += '<tr class="rowdata labvalue"><td class="drugs_col"><b>Lab Result</b></td>';
							$html += '<td></td>';
							$html += '</tr>';
							for (var j in $antibiotics) {
								var $ta_ID = $antibiotics[j].ID;
								var $ta_name = $antibiotics[j].ta_name;
								$html += '<tr class="rowdata" data-taid="' + $ta_ID + '"><td class="drugs_col">' + $ta_name + '</td>';
								var $nrow = 'antibioticsdata[-1][' + $ta_ID + ']';
								$html += '<td class="">';
								var $mic_vl = $mic_data[$ta_ID] || '';

								$html += '<select name="symbol_' + $nrow + '" style="width:46%;"><option value=""></option>';
								for (isb in $symbols) {
									var sb = $symbols[isb];
									var sbt = sb;
									if (sbt == '>=') {
										sbt = '&ge;';
									} else if (sbt == '<=') {
										sbt = '&le;';
									}
									var $selected = '';
									if ($mic_vl.indexOf(sb) == 0 && (sb.length == 2 || (sb.length == 1 && $mic_vl.indexOf('=') !== 1))) {
										$selected = 'selected';
										$mic_vl = $mic_vl.replace(sb, '');
									}


									$html += '<option value="' + sb + '" ' + $selected + '>' + sbt + '</option>';
								}
								$html += '</select>';
								$html += '<input type="text" name="' + $nrow + '" value="' + $mic_vl + '" style="width:49%"/></td>';
								$html += '</tr>';
							}
							$html += '</table></div>';
							$('#display_antibiotics').append($html);

						}

						function tr_fill_antibio_col($data) {
							var $header = '<th data-teid="' + $data.te_ID + '" class="te_head">' + $data['name'] + '<br><a class="removecol button">-</a></th>';
							$('#display_antibiotics tr.group_header').append($header);

							//add labresult
							var $lrs = '<td data-teid="' + $data.te_ID + '" class="te_row"><input type="text" name="labresult[' + $data.te_ID + ']" value="' + ($data.labresult || '') + '"/></td>';
							$('#display_antibiotics tr.rowdata.labvalue').append($lrs);

							for (var j in $antibiotics) {
								var $ta_ID = $antibiotics[j].ID;
								var $nrow = 'antibioticsdata[' + $data.te_ID + '][' + $ta_ID + ']';
								var $html = '<td data-teid="' + $data.te_ID + '" class="te_row"><select name="' + $nrow + '">';
								for (var k in $vlkeys) {
									var $dresult = $data.rs[$ta_ID] || '';
									var $selected = k == $dresult ? 'selected' : '';
									$html += '<option value="' + k + '" ' + $selected + '>' + $vlkeys[k] + '</option>';
								}
								$html += '</select></td>';
								$('#display_antibiotics tr.rowdata[data-taid="' + $ta_ID + '"]').append($html);
							}
							$('#display_antibiotics').show();
						}

						function tr_fill_pathogen_dd() {
							var $html = '<option value="">Select Pathogen</option>';
							for (var tname in $anti_pathogen) {
								$html += '<option value="' + $anti_pathogen[tname] + '">' + tname + '</option>';
							}
							$('#list_pathogen').html($html);
						}
						$('body').on('click', '.add_antibiotics', function() {
							var $vl = $('#list_pathogen').val();
							if ($vl == '') return false;
							var $opt = $('#list_pathogen option[value="' + $vl + '"]');
							var $tname = $opt.html();
							tr_fill_antibio_col({
								'name': $tname,
								'te_ID': $vl,
								'rs': []
							});
							$opt.remove();
							$('#list_pathogen').val('');
						}).on('click', '#display_antibiotics .removecol', function() {
							if (confirm("Do you want remove?") == false) return false;
							var teid = $(this).parent().data('teid');
							$(this).parent().remove();
							$('#display_antibiotics td.te_row[data-teid="' + teid + '"]').remove();
							if ($('#display_antibiotics tr.group_header th').length <= 1) {
								$('#display_antibiotics').hide();
							}
						});
						tr_fill_pathogen_dd();
						tr_fill_antibio_drugcol();
						for (var tkey in $anti_data) {
							var item = $anti_data[tkey];
							tr_fill_antibio_col(item);
							var $opt = $('#list_pathogen option[value="' + item.te_ID + '"]');
							$opt.remove();
						}
					})
				</script>
			<?php
			}
			?>


			<div class="frow">
				<h4>&nbsp;</h4>
			</div>


			<?php
			foreach ($drug_groups as $groupid => $groupdata) {
				ob_start();
				foreach ($groupdata['tests'] as $arr) {
					$count_test_show++;
					unset($miss_test[$arr['drug']]);

					$pos_neg_vls = $pos_neg;

					if ($arr['te_is_detected']) {
						$pos_neg_vls['POS'] = 'Detected';
						$pos_neg_vls['NEG'] = 'Not Detected';
						if (!$arr['only_detected']) {
							$pos_neg_vls['POL'] = 'Detected-Low';
							$pos_neg_vls['POM'] = 'Detected-Medium';
							$pos_neg_vls['POH'] = 'Detected-High';
							unset($pos_neg_vls['POS']);
							if ($arr['pos-neg'] == 'POS') {
								$arr['pos-neg'] = 'POL';
							}
						}
					}
					if ($arr['te_inconclusive_ID'] === '-1') {
						$pos_neg_vls['POS'] = 'SUFFICIENT';
						$pos_neg_vls['NEG'] = 'INSUFFICIENT';
					}
					if (stripos($arr['te_name'], 'MonkeyPox') !== false) {
						$pos_neg_vls['EQU'] = 'Equivocal';
						$pos_neg_vls['INC'] = 'Inconclusive';
					}
					$count_test_show++;
					unset($miss_test[$arr['drug']]);

					if (empty($arr['tt'])) {
						$arr['tt'] = $arr['te_abrev'];
					}

					$preview_arr2 = '';
					if (isset($arr['value2'])) {
						$preview_arr2 = 'ct: ' . $arr['value2']['conc'];
						$preview_arr2 .= '| amp: ' . $arr['value2']['amp_score'];
						$preview_arr2 .= '| cq: ' . $arr['value2']['cq'];
						$preview_arr2 .= '| result: ' . $arr['value2']['pos-neg'];
					}
			?>
					<tr>
						<td title="<?php echo isset($arr['tt']) ? $arr['tt'] : '' ?>">
							<input type="text" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][drug]"' ?> value="<?php echo $arr['drug'] ?>" readonly />
						</td>
						<td>
							<select class="pos_neg" <?php if (!$can_edit) echo 'disabled' ?> name="data_results[<?php echo $arr['id'] ?>][pos-neg]" <?php if (isset($arr['is_blue']) && $arr['is_blue'] && $arr['pos-neg'] == 'NEG')
																																						echo 'style="font-weight:600"' ?>>
								<?php
								foreach ($pos_neg_vls as $k => $vl) {
									if (
										$arr['detected_type'] && !in_array($k, array(
											'POS',
											'POL',
											'POH',
											'POM',
											'PVH',
											'PVL',
											'NEG',
											'',
											'PEN',
											'INV'
										))
									) {
										continue;
									}
									$selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
									echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
								}
								?>
							</select>
						</td>
						<?php
						if (tr_get_option('ci_result_settings_gene', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp'])) {
						?>
							<td>
								<input type="text" class="conc <?php echo ($arr['ct_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
							</td>
							<td>
								<input type="text" class="amp_score <?php echo ($arr['amp_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][amp_score]" value="<?php echo $arr['amp_score'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
							</td>
							<td>
								<input type="text" class="cq <?php echo ($arr['cq_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][cq]" value="<?php echo $arr['cq'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
							</td>
						<?php
						} else {
						?>
							<td>
								<input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
							</td>
						<?php
						}
						?>
						<td class="center" title="<?php echo $preview_arr2 ?>">
							<?php echo trim($arr['range']) ?>
						</td>

						<td data-did="<?php echo isset($arr['drug_comment_id']) ? $arr['drug_comment_id'] : '' ?>">
							<textarea name="data_results[<?php echo $arr['id'] ?>][comments]" data-posc="<?php echo isset($arr['posc']) ? $arr['posc'] : '' ?>" <?php if (!$can_edit) echo 'readonly' ?>><?php echo $arr['comments'] ?></textarea>
						</td>
					</tr>
					<?php

					if (isset($arr['list']) && count($arr['list']) > 0) {
						foreach ($arr['list'] as $arr2) {
					?>

							<tr>
								<td title="<?php echo isset($arr2['tt']) ? $arr2['tt'] : '' ?>">
									<input type="text" <?php if (stripos($arr2['id'], 'new') !== false) echo 'name="data_results[' . $arr2['id'] . '][drug]"' ?> value="<?php echo $arr2['drug'] ?>" readonly />
								</td>
								<td>
									<select class="pos_neg" <?php if (!$can_edit) echo 'disabled' ?> name="data_results[<?php echo $arr2['id'] ?>][pos-neg]" <?php if (isset($arr2['is_blue']) && $arr2['is_blue'] && $arr2['pos-neg'] == 'NEG')
																																									echo 'style="font-weight:600"' ?>>
										<?php
										foreach ($pos_neg_vls as $k => $vl) {
											if (
												$arr2['detected_type'] && !in_array($k, array(
													'POS',
													'POL',
													'POH',
													'POM',
													'NEG',
													'',
													'PEN',
													'INV'
												))
											) {
												continue;
											}
											$selected = $k == $arr2['pos-neg'] ? 'selected="selected"' : '';
											echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
										}
										?>
									</select>
								</td>
								<?php
								if (tr_get_option('ci_result_settings_gene', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp'])) {
								?>
									<td>
										<input type="text" class="conc <?php echo ($arr2['ct_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][conc]" value="<?php echo $arr2['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
									<td>
										<input type="text" class="amp_score <?php echo ($arr2['amp_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][amp_score]" value="<?php echo $arr2['amp_score'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
									<td>
										<input type="text" class="cq <?php echo ($arr2['cq_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][cq]" value="<?php echo $arr2['cq'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
								<?php
								} else {
								?>
									<td>
										<input type="text" class="conc" name="data_results[<?php echo $arr2['id'] ?>][conc]" value="<?php echo $arr2['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
								<?php
								}
								?>
								<td class="center">
									<?php echo trim($arr2['range']) ?>
								</td>

								<td data-did="<?php echo isset($arr2['drug_comment_id']) ? $arr2['drug_comment_id'] : '' ?>">
									<textarea name="data_results[<?php echo $arr2['id'] ?>][comments]" data-posc="<?php echo isset($arr2['posc']) ? $arr2['posc'] : '' ?>" <?php if (!$can_edit) echo 'readonly' ?>><?php echo $arr2['comments'] ?></textarea>
								</td>
							</tr>
					<?php
						}
					}
				}

				$drug_content = ob_get_clean();
				if (!empty($drug_content)) {

					?>
					<table style="width:100%" class="tbl_results">
						<tr class="group_header">
							<th style="width:20%"><strong><?php echo htmlspecialchars($groupdata['name']) ?></strong></th>
							<th style="width:20%"><strong>Result</strong></th>
							<?php
							if (tr_get_option('ci_result_settings_gene', 'enable_rs_ct_amp_cq') || (isset($groupdata['other_options']['is_rpp']) && $groupdata['other_options']['is_rpp'])) {
								echo '<th  style="width:10%"><strong>CT</strong></th>';
								echo '<th  style="width:10%"><strong>Amp Score</strong></th>';
								echo '<th  style="width:10%"><strong>Cq Confidence</strong></th>';
							} else {
								echo '<th  style="width:20%"><strong>Conc (ng/mL)</strong></th>';
							}
							?>
							<th style="width:15%"><strong>Range</strong></th>
							<th><strong>Comments</strong></th>
						</tr>
						<?php echo $drug_content; ?>
					</table>
		<?php
				}
			}
		}
		?>
		<div class="frow">
			<h4>Notes (Show on Report)</h4>
		</div>
		<div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
			<div class="">
				<textarea name="updatebat[notes_report]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['notes_report']) ?></textarea>
			</div>
		</div>

		<?php if ($is_qc_form) { ?>
			<div class="frow">
				<div class="col3">
					<label>Status</label>
					<?php
					$status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'gene'), $report['batch_status'], $report['mid'], $table_batch, 'gene');
					$disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('gene', 'show_in_result_form', $report['batch_status']);
					tr_select_option('updatebat[batch_status]', $status_options, @$report['batch_status'], ['disabled_options' => $disabled_keys]);
					?>
				</div>
			</div>
		<?php } ?>

		<div class="frow">
			<h4>Internal Notes</h4>
		</div>
		<div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
			<div class="">
				<textarea name="updatebat[internal_notes]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['internal_notes']) ?></textarea>
			</div>
		</div>
		<br>

		<div class="frow">
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="0" <?php checked($report['final_results'], '0') ?> <?php if (!$can_edit) echo 'disabled' ?> /> None </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="2" <?php checked($report['final_results'], '2') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Preliminary </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="1" <?php checked($report['final_results'], '1') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Final </label>
			<label><input type="radio" name="info[final_results]" value="3" <?php checked($report['final_results'], '3') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Amended Report </label>
		</div>
		<br><br>


		<div class="frow">
			<?php
			$sql = "select 
                    g.tg_ID,
                    g.tg_popup_approve
                from ci_test_groups g
                where g.tg_type = 'gene' ";

			$rs = $wpdb->get_results($sql);
			$pop_msg = '';
			foreach ($rs as $r) {
				if (strlen($r->tg_popup_approve) > 10 && in_array($r->tg_ID, $group_ids)) {
					$pop_msg = $r->tg_popup_approve;
				}
			}
			$next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], 'gene');
			if ($can_edit) {
			?>
				<input type="submit" name="submit" value="Submit" class="button-primary" />
			<?php
			}
			if ($next > 0) {
			?>
				<input type="submit" name="submit_next" value="Submit & Next" class="button">
			<?php
			}
			?>

			<input type="submit" <?php if (!$can_approve) echo 'style="display:none"' ?> name="approval" value="Approve" class="button approve_btn">

			<?php

			if ($next > 0) {
				$next_url = Tr_Base_Class_V6::link(array('id' => $next), false);
			?>
				<input type="hidden" name="next_link" value="<?php echo $next_url ?>" />

				<input type="submit" <?php if (!$can_approve) echo 'style="display:none"' ?> name="approval_next" value="Approve & Next" class="button approve_btn">

				<a href="<?php echo $next_url ?>" class="button">Next</a>
			<?php
			}
			?>

			<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $report['id'], 'btype' => 'gene', 'view' => 1)) ?>">PDF</a>

			<a class="button" href="<?php Tr_Base_Class_V6::link(array('checkmatch' => '1')) ?>">Check File</a>

			<input type="hidden" name="tr_action" value="ci_save_result_edit" />
			<input type="hidden" name="btype" value="gene" />
			<input type="hidden" id="pop_msg" value="<?php echo $pop_msg ?>" />
			<input type="hidden" name="id" value="<?php echo $report['id'] ?>" />

			<?php trpdd_form_unique_id() ?>
		</div>
	</form>
</div>

<?php include(trpdd_get_file_path('global', 'report_history')); ?>
<?php

if (isset($_GET['debug2'])) {
	echo '$count_test_show:' . $count_test_show . '<br><br>';
	echo '$in_panel_tests_requested:' . '<br>';
	var_dump($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:<br>';
	var_dump($all_res);
	echo '<br><br>';
}
?>

<div id="popup_selecter" style="display: none;"></div>
<style>
	input.conc,
	input.cutoff {
		width: 80px;
	}

	tr.group_header {
		background: #ddd;
		height: 60px;
	}
</style>
<script>
	var $need_final = <?php echo ($current_role == 'results') ? 'true' : 'false' ?>;
	var $rejecteds = <?php echo json_encode(tr_pdd_where_batch_rejected(true, false, 'gene')) ?>;
	jQuery(function($) {
		$('body').on('change', '.specimen_outcome', function() {
			var $this = $(this);
			var $cm = $this.parents('.specimen_row').find('.specimen_comments');
			if ($this.val() == 'ABNORMAL') {
				var $vl = $(this).data('comments');
				if ($vl.indexOf('||') > -1) {
					var $html = '<select id="select_comments" style="width:100%"><option value="">Choose a Comment</option>';
					var $vls = $vl.split('||');
					for (i in $vls) {
						$html += '<option value="' + $vls[i] + '">' + $vls[i] + '</option>';
					}
					$html += '</select>';
					$('#popup_selecter').html($html);
					tb_remove();
					tb_show("Choose a Comment", "#TB_inline?height=50&amp;width=400&amp;inlineId=popup_selecter");
					$('#select_comments').change(function() {
						if ($(this).val() != '') {
							$cm.val($(this).val());
							tb_remove();
						}
					})
				} else {
					$cm.val($vl);
				}

			} else {
				$cm.val('');
			}
		});
		$('body').on('change', '.speciment_value', function() {
			var $min = parseFloat($(this).data('min'));
			var $max = parseFloat($(this).data('max'));
			var $vl = parseFloat($(this).val());
			var $status = 'NORMAL';

			if (isNaN($vl)) {
				$status = '';
			} else if ($(this).data('min') == 'neg') {
				$status = ($vl >= $max) ? 'POS' : 'NEG';
			} else if ($(this).val() == '') {
				$status = '';
			} else if ($vl < $min || $vl > $max) {
				$status = 'ABNORMAL';
			}
			var $pr = $(this).parents('.specimen_row');
			$('.specimen_outcome', $pr).val($status);

		})
		$('body').on('change', 'select.pos_neg', function() {
			var $pr = $(this).parents('.crow');
			if (!$pr.hasClass('in_medications')) {
				if ($(this).val() == 'NEG') {
					var $cutoff = $pr.find('input.cutoff').val();
					$pr.data('ocomment', $pr.find('textarea').val());
					$pr.data('conc', $pr.find('input.conc').val());
					$pr.find('textarea').val('');
					$pr.find('input.conc').val('<' + $cutoff);
				} else if ($(this).val() == 'POS' && $pr.data('ocomment')) {
					$pr.find('textarea').val($pr.data('ocomment'));
					if ($pr.data('conc')) {
						$pr.find('input.conc').val($pr.data('conc'));
					}
				} else if ($(this).val() == 'POS') {
					$pr.find('input.conc').val('');
					$pr.find('textarea').val($pr.find('textarea').data('posc'));
				}
			}
		});
		$('body').on('change', '[name="methamphetamine[d]"],[name="methamphetamine[l]"]', function() {
			$cm = $('[name="methamphetamine[comments]"]');
			if ($('[name="methamphetamine[d]"]').val() > 0 || $('[name="methamphetamine[l]"]').val() > 0) {
				$cm.val($cm.data('cm'));
			} else {
				$cm.val('');
			}
		}).on('change', '[name="updatebat[batch_status]"]', function() {
			var $vl = $(this).val();
			if ($rejecteds[$vl]) {
				$('.approve_btn').show();
			} else {
				$('.approve_btn').hide();
			}
		})
		$('.approve_btn').click(function() {
			var $pop_msg = $('#pop_msg').val();
			if ($pop_msg.length > 10 && confirm($pop_msg) == false) {
				return false;
			}
			if ($('[name="info[final_results]"]:checked').val() != '1' && $need_final) {
				alert('Status must be Final!');
				return false;
			}
		})
		$('a.show_test_panels').click(function() {
			$('.list_test_panels').toggle();
			return false;
		})
		$(window).scroll(function() {
			var $t = $(this).scrollTop();

			$f = $('.header_fix_top').offset().top;
			if ($t > $f - 20) {
				$('.header_fix').show();
			} else {
				$('.header_fix').hide();
				$('.list_test_panels').hide();
			}

		})
	})
</script>