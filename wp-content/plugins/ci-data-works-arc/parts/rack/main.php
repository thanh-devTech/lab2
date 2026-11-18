<?php
global $wpdb;
global $rack_ABC;
global $second_ntc_pos, $first_ntc, $ntc3_pos, $ntc4_pos, $blank1_pos, $blank2_pos;
global $allow_rack4s, $number_p_well;
global $rack_uti_type;
global $rack_max6_fun;
global $rack_panels;
global $rack_16, $max_well;
global $ci_all_btypes;

include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

$datetime_now   = date_i18n('Y-m-d H:i:s');
$priorities     = array('low' => 'Low', 'med' => 'Med', 'high' => 'High');
$user_id        = get_current_user_id();

$newurl         = '';
$today          = date_i18n('Y-m-d');
if (isset($_REQUEST['today'])) {
	$today = $_REQUEST['today'];
}

if (isset($_GET['s'])) {
	$rs = $wpdb->get_results("select * from ci_racks where re_is_uti_panel = {$_GET['s']}");
	var_dump($rs);
}

if (isset($_REQUEST['date'])) {
	$today = $_REQUEST['date'];
} else {
	$newurl = Tr_Base_Class_V6::link(array('date' => $today), false);
}

$print_zebra        = tr_get_option('ci_pdf_data', 'print_zebra');
$print_zebra_size   = tr_get_option('ci_pdf_data', 'print_zebra_size');

$rack_ABC       = tr_get_option('ci_pdf_data', 'rack_ABC');
$rack_16        = tr_get_option('ci_pdf_data', 'rack_16');
$rack_6controls = tr_get_option('ci_pdf_data', 'rack_6controls');
$ntc_at_end     = tr_get_option('ci_pdf_data', 'rack_ntc_at_end');
$allow_rack4s   = tr_get_option('ci_pdf_data', 'rack_4s_well');
$number_p_well  = tr_get_option('ci_pdf_data', 'rack_numberp_well');
$allow_rack4s_posneg = tr_get_option('ci_pdf_data', 'rack_4s_well_posneg');
$rack_uti_type  = tr_get_option('ci_pdf_data', 'rack_uti_type');
$rack_max6_fun  = tr_get_option('ci_pdf_data', 'rack_max6_fun');
$plate_coating 	= tr_get_option('ci_pdf_data', 'plate_coating');
$rack_panels    = tr_get_option('ci_pdf_data', 'rack_panels', array());

if ($number_p_well <= 1) {
	$number_p_well = 4;
}

$rack_batch_parts = array();
if (!is_array($rack_panels)) {
	$rack_panels = array();
}
$has_btypes 	= array();
$group_style 	= array();

$where_style = " (
	(tg_show_req = 0 and tg_date_end >= '{$today}') 
	or 
	(tg_show_req = 1 and (tg_date_start < '{$today}' or tg_date_start=0 or tg_date_start is null) ) 
)";

$where_style .= " and tg_plate_style  > 0 ";

if (count($rack_panels) > 0) {
	$panels_ids_str = implode(',', $rack_panels);
	$rack_panels = array();
	$where_style = " tg_ID in ({$panels_ids_str}) ";
}


$rs = $wpdb->get_results("select tg_ID, 
					tg_name, 
					tg_plate_style, 
					tg_plate_style2, 
					tg_type, 
					tg_other_options
                    from ci_test_groups 
                    where  {$where_style} 
                    order by tg_is_covid desc, tg_plate_style asc, tg_position asc");
foreach ($rs as $r) {
	if ($r->tg_plate_style > 0) {




		$other_opts = json_decode($r->tg_other_options, true);
		if (isset($other_opts['rack_name']) && !empty($other_opts['rack_name'])) {
			$r->tg_name = $other_opts['rack_name'];
		}

		$has_btypes[$r->tg_type] = $r->tg_type;

		$config_plate = trpdd_plate_map_config($r->tg_plate_style);
		if (is_array($config_plate)) {
			if (isset($config_plate['parts']) && count($config_plate['parts']) > 0) {
				$rack_batch_parts[$r->tg_ID] = array(
					'label' => 'Type',
					'type' => 'subtype',
					'options' => $config_plate['parts'],
				);
				if ($r->tg_plate_style2 > 0) {
					$rack_batch_parts[$r->tg_ID . '_2'] = array(
						'label' => 'Type',
						'type' => 'subtype',
						'options' => $config_plate['parts'],
					);
				}
			}
		}
		if ($r->tg_plate_style2 > 0) {
			$config_plate2 = trpdd_plate_map_config($r->tg_plate_style2);
			if (is_array($config_plate2)) {
				if (isset($config_plate2['parts']) && count($config_plate2['parts']) > 0) {
					$rack_batch_parts[$r->tg_ID . '_2'] = array(
						'label' => 'Type',
						'type' => 'subtype',
						'options' => $config_plate2['parts'],
					);
				}
			}
		}


		if ($r->tg_name == 'RPP') {
			$rack_panels[$r->tg_ID] = $r->tg_name;
		} else if (isset($group_style[$r->tg_plate_style])) {
			$rack_panels[$group_style[$r->tg_plate_style]] .= ' / ' . $r->tg_name;
			if (stripos($rack_panels[$group_style[$r->tg_plate_style]], 'uti') !== false && stripos($rack_panels[$group_style[$r->tg_plate_style]], 'wound') !== false) {
				$rack_panels[$group_style[$r->tg_plate_style]] = 'UTI / Wound';
			}
		} else {
			$rack_panels[$r->tg_ID] = $r->tg_name;
			$group_style[$r->tg_plate_style] = $r->tg_ID;
		}
		if ($r->tg_plate_style2 > 0) {
			$rack_panels[$r->tg_ID . '_2'] = $other_opts['rack_name2'];
			$group_style[$r->tg_plate_style2] = $r->tg_ID;
		}
	} else if (stripos($r->tg_name, 'Wound') !== false && stripos($r->tg_name, 'ABX') > 0) {
		if (in_array('UTI', $rack_panels)) {
			$utipid = array_search('UTI', $rack_panels);
			$rack_panels[$utipid] = 'UTI/Wound';
		} else {
			$rack_panels[$r->tg_ID] = 'Wound';
			$rack_panels[$r->tg_ID * -1] = 'ABX';
		}
	} else if (stripos($r->tg_name, 'UTI') !== false && stripos($r->tg_name, 'ABX') > 0) {
		if (in_array('Wound', $rack_panels)) {
			$utipid = array_search('Wound', $rack_panels);
			unset($rack_panels[$utipid]);
			unset($rack_panels[$utipid * -1]);

			$rack_panels[$r->tg_ID]       = 'UTI/Wound';
			$rack_panels[$r->tg_ID * -1] = 'ABX';
		} else {
			$rack_panels[$r->tg_ID]       = 'UTI';
			$rack_panels[$r->tg_ID * -1] = 'ABX';
		}
	} else {
		$rack_panels[$r->tg_ID] = $r->tg_name;
	}
}


if (tr_get_option('ci_pdf_data', 'rack_mixed384')) {
	$rack_panels[-300] = 'Mixed';
}

if (in_array('UTI', $rack_panels) && in_array('RPP', $rack_panels)) {
	$rack_panels[-400] = 'UTI/RPP - ABX';
}


$max_well       = 96;
$second_ntc_pos = $rack_ABC ? 2 : 13;
$first_ntc      = 1;
$ntc3_pos       = 0;
$ntc4_pos       = 0;
$blank1_pos     = 0;
$blank2_pos     = 0;

if ($rack_16) {
	$max_well = 16;
	$first_ntc = 16;
	$second_ntc_pos = 15;
} else if ($allow_rack4s_posneg) {
	$first_ntc = 95;
	$second_ntc_pos = 93;
	$ntc3_pos = 94;
	$ntc4_pos = 96;
} else if ($rack_6controls) {
	$rack_ABC = false;

	$blank1_pos = 91;
	$blank2_pos = 92;
	$second_ntc_pos = 93;
	$first_ntc = 95;
	$ntc3_pos = 94;
	$ntc4_pos = 96;
} else if ($ntc_at_end) {
	$first_ntc = 96;
	$second_ntc_pos = 95;
}



$specimen_types_all = array();
$base_positions     = trpdd_get_receiving_base_positions();
if (in_array('study', $ci_all_btypes)) {
	$base_positions['study'] = array(
		'name' => 'Study',
		'btype' => 'study',
	);
}

foreach ($base_positions as $k => $arr) {
	if (!in_array($arr['btype'], $ci_all_btypes)) {
		unset($base_positions[$k]);
	} else if (count($has_btypes) > 0 && !isset($has_btypes[$arr['btype']])) {
		unset($base_positions[$k]);
	}
}

if (!empty($act)) {
	$actionfile = trpdd_get_file_path('rack', 'action_' . $act);
	if (!empty($actionfile)) {
		include($actionfile);
	}
}



$where_rack 	= " re_today='{$today}'  ";
$adding_fields 	= '';
$results  		= array();
if ($plate_coating) {
	$expiry_date = date('Y-m-d', strtotime($today) - 86400 * 7);
	$where_rack .= " 
	OR (
		re_priority > '{$expiry_date}' 
		and re_is_uti_panel in (21,22) 
		)";

	$adding_fields .= ', (select count(*) from ci_rack_batches rb 
								where rb.re_ID = ra.re_ID) as count_b ';

	$sql_rack = "select * from 
		(select * {$adding_fields} 
			from ci_racks ra 
			where {$where_rack} 
		) as rs
		where 
			re_today='{$today}'
			OR
			(re_rack_subtype in ('M','V','P') and count_b<6)
			OR 
			(re_rack_subtype not in ('M','V','P') and count_b<3) ";

	$sql_rack .= " order by re_ID asc";

	$results = $wpdb->get_results($sql_rack, ARRAY_A);
} else {

	$sql_rack = "select * {$adding_fields} 
	from ci_racks ra 
	where {$where_rack}";
	$sql_rack .= " order by re_ID asc";

	$results = $wpdb->get_results($sql_rack, ARRAY_A);
}



if (count($results) == 0) {
	$results[] = array(
		're_number' => '1',
		're_today' => $today,
		're_priorty' => 'low',
	);
}

if (isset($_GET['debug'])) {
	var_dump($sql_rack);
	echo '<br>';
	$count = $wpdb->get_var("select count(*) from ci_racks");
	echo 'count: ' . $count . '<br>';
	$lastdate = $wpdb->get_results("select * from ci_racks order by re_ID desc limit 50");
	foreach ($lastdate as $l) {
		echo '<div>';
		echo $l->re_today;
		echo '</div>';
	}
}

?>
<div class="wrap">
	<h2>Racks</h2>
	<?php Tr_Session_Class::show() ?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
		<label>Date:</label>
		<input type="search" class="hasdate submitchange" name="date" value="<?php echo $today ?>" />
		<input type="submit" class="button bsty6" value="Go" />
	</form>
	<br>

	<div id="loading_form">
		<div class="loading"></div>
	</div>

	<div id="form_rack" style="display: none;">

		<div class="wraptbl">
			<table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
				<thead class="sticky">
					<tr>
						<th style="width:18px"></th>
						<th style="width: 100px;">Date</th>
						<th>Rack</th>
						<?php if ($allow_rack4s) { ?>
							<th style="width:100px">Kind</th>
						<?php } ?>
						<?php if ($plate_coating) { ?>
							<th>Plate ID</th>
							<th>Barcode</th>
							<th>Coating Date</th>
						<?php } else { ?>
							<th>SubTitle</th>
							<th>Priority</th>
						<?php } ?>

						<?php
						if (count($rack_panels) > 0) {
						?>
							<th>Panel</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php
					$count_rack = 0;
					$table_cols = $allow_rack4s ? 6 : 5;
					if (count($rack_panels) > 0) {
						$table_cols += 1;
					}
					if ($plate_coating) {
						$table_cols++;
					}

					$default_subtitle = $plate_coating ? '' : '';
					$browid = 0;
					foreach ($results as $row) {
						$count_rack++;
						$browid++;
						$re_ID  = isset($row['re_ID']) ? $row['re_ID'] : 0;
						$rack   = isset($row['re_number']) ? $row['re_number'] : 0;
						if ((!isset($row['re_subtitle']) || empty($row['re_subtitle'])) && $plate_coating) {
							$row['re_subtitle'] = $default_subtitle;
						}

						$child_data = trrack_display_samples($re_ID);
					?>
						<tr class="parent_c smaller <?php if ($count_rack == 1) echo 'first' ?>" data-rack="<?php echo $rack ?>" data-type="<?php echo isset($row['re_is_uti_panel']) ? $row['re_is_uti_panel'] : '' ?>" data-p="batch_<?php echo $browid ?>" data-relate_id="<?php echo isset($row['re_relate_id']) ? $row['re_relate_id'] : '' ?>">
							<td><i></i></td>
							<td><input type="text" data-fname="re_today" class="date" value="<?php echo $row['re_today'] ?>" readonly />
								<input type="hidden" data-fname="re_ID" value="<?php echo isset($row['re_ID']) ? $row['re_ID'] : '' ?>" />
								<input type="hidden" data-fname="re_number" value="<?php echo $rack ?>" />
							</td>
							<td class="tdracknum">
								<b>Rack <span class="racknum"><?php echo $row['re_number'] ?></span></b>
								<?php
								if (isset($row['re_is_uti_panel']) && ($row['re_is_uti_panel'] == 21 || $row['re_is_uti_panel'] == 22)) {
									if (($child_data['count'] == 3 && !in_array($row['re_rack_subtype'], array('M', 'V', 'P')))
										|| ($child_data['count'] == 6) && in_array($row['re_rack_subtype'], array('M', 'V', 'P'))
									) {
										echo '<span class="right stopicon"></span>';
									}
								}
								?>
							</td>
							<?php if ($allow_rack4s) { ?>
								<td>
									<?php tr_select_option('rec[' . $rack . '][re_kind]', array(
										'0' => 'Regular',
										'1' => 'Pooled',
									), isset($row['re_kind']) ? $row['re_kind'] : '', array('attr' => 'data-fname="re_kind" style="width:100px"')); ?>
								</td>
							<?php } ?>
							<td>
								<input type="text" data-fname="re_subtitle" data-default="<?php echo $default_subtitle ?>" value="<?php echo isset($row['re_subtitle']) ? $row['re_subtitle'] : '' ?>" />
							</td>
							<?php if ($plate_coating) { ?>
								<td>
									<input type="search" data-fname="re_barcode" value="<?php echo isset($row['re_barcode']) ? $row['re_barcode'] : '' ?>" />
								</td>
								<td>
									<input type="search" require="true" class="hasdate" data-fname="re_priority" value="<?php echo isset($row['re_priority']) ? $row['re_priority'] : '' ?>" />
								</td>
							<?php } else { ?>
								<td>
									<?php tr_select_option('rec[' . $rack . '][re_priority]', $priorities, isset($row['re_priority']) ? $row['re_priority'] : '', array('attr' => 'data-fname="re_priority" style="width:70px"')); ?>
								</td>
							<?php } ?>


							<?php
							if (count($rack_panels) > 0) {
							?>
								<td title="<?php echo isset($row['re_is_uti_panel']) ? $row['re_is_uti_panel'] : '' ?>">
									<?php

									if (isset($row['re_panel_style']) && $row['re_panel_style'] > 1) {
										$row['re_panel_id'] .= '_' . $row['re_panel_style'];
									}
									tr_select_option(
										'rec[' . $rack . '][re_panel_id]',
										$rack_panels,
										isset($row['re_panel_id']) ? $row['re_panel_id'] : '',
										array('attr' => 'required data-fname="re_panel_id" style="width:100%"', 'addselect' => count($rack_panels) > 1)
									);
									?>
								</td>
							<?php
							}
							?>
						</tr>
						<tr class="child <?php if ($count_rack == 1) echo 'first_child' ?> p_batch_<?php echo $browid ?>" data-id="<?php echo $browid ?>">
							<td colspan="<?php echo $table_cols ?>">
								<select class="btype " style="width:80px;margin-top:-2px">
									<?php
									foreach ($base_positions as $k => $arr) {
										$selected = isset($row['re_panel_id']) && $k == $row['re_rack_type'] ? 'selected' : '';
										echo '<option value="' . $k . '" ' . $selected . '>' . $arr['name'] . '</option>';
									}
									?>
								</select>

								<input type="search" placeholder="Barcode" class="barcode_auto" style="width:250px;">

								<?php
								if (tr_get_option('ci_pdf_data', 'rack_mixed384')) {
									$panels = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups
									where tg_type='pcr' and tg_name in ('uti','sti','rpp') 
									order by tg_ID asc");
									echo '<select class="bpanelid" style="width:80px;margin-top:-2px;' . ($row['re_panel_id'] == -300 ? '' : 'display:none') . '">';
									foreach ($panels as $pn) {
										echo '<option value="' . $pn->tg_ID . '">' . $pn->tg_name . '</option>';
									}
									echo '</select>';
								}

								if ($plate_coating) {
									$mixedvps = array(
										'auto' => 'Auto',
										'V' => 'V',
										'P' => 'P'
									);
									$is_m = substr($row['re_subtitle'], 0, 1) == 'M';
									echo '<select class="mixedvp ' . ($is_m ? 'showformixed' : '') . '" style="width:80px;margin-top:-2px;' . ($is_m ? '' : 'display:none') . '">';
									foreach ($mixedvps as $k => $vl) {
										echo '<option value="' . $k . '">' . $vl . '</option>';
									}
									echo '</select>';
								}

								if (count($rack_batch_parts) > 0) {
									foreach ($rack_batch_parts as $optid => $optarr) {
										echo '<select class="rackparts parts_' . $optid . '" data-key="' . $optarr['type'] . '" style="width:80px;margin-top:-2px;' . ($row['re_panel_id'] == $optid ? '' : 'display:none') . '">';
										foreach ($optarr['options'] as $k => $vl) {
											echo '<option value="' . $k . '">' . $vl . '</option>';
										}
										echo '</select>';
									}
								}
								?>

								<span class="showloading"></span>
								<small>Press Enter to add</small>

								<a class="add_new_well button <?php echo !isset($row['re_kind']) || !$row['re_kind'] ? 'hide' : '' ?>" href="#">New Well</a>

								<span class="right">
									<?php
									if (isset($_GET['debug'])) { ?>
										<a class="button export_btn bsty1" style="margin-left:15px">Export</a>
									<?php } ?>
									<a class="button printall_btn bsty1" style="margin-left:15px">Print All</a>
									<a class="button unrackbtn bsty6" title="Unrack" style="margin-left:15px">Unrack</a>
									<a class="button removesamplebtn bsty5" title="Remove Selected Samples" style="margin-left:15px">Remove Samples</a>
									<a class="button addplatebtn bsty3" style="margin-left:15px">Add Plate</a>
								</span>

								<div class="warning_duplicate"></div>

								<table class="posts report_tbl batch_tbl subretbl">
									<thead>
										<tr>
											<th style="width:50px;"><input type="checkbox" class="select_all" /></th>
											<th style="width:20px">#</th>
											<?php if ($allow_rack4s) { ?>
												<th style="width:130px">Well Designation</th>
												<th style="width:100px">Barcode</th>
												<?php
												for ($iw = 2; $iw < $number_p_well; $iw++) {
												?>
													<th style="width:100px"></th>
												<?php
												}
												?>
												<th></th>
											<?php } else { ?>
												<th style="width:100px">Barcode</th>
												<th><?php if (!tr_get_option('ci_pdf_data', 'rack_hide_well')) echo 'Well Designation' ?></th>
											<?php } ?>
										</tr>
									</thead>
									<tbody>
										<?php echo isset($child_data['html']) ? $child_data['html'] : '' ?>
									</tbody>
								</table>
							</td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		</div>

		<br>
		<a class="button add_new_row">+</a>
		<a class="button show_hide_all right">Show All</a>
	</div>

</div>
<audio src="<?php echo TRCPDD_URL ?>audio/beep.mp3" id="beep_player" loop style="display:none"></audio>
<iframe id="reprint_frame" src="about:blank" style="display: none;"></iframe>

<style>
	.col-position input {
		width: 60px;
	}

	.show_all tr.child {
		display: table-row !important;
	}

	.spec_type label {
		white-space: nowrap;
		margin-right: 10px;
	}

	.spec_type label input[type=radio] {
		margin-right: 0;
	}

	th.smaller {
		font-size: 12px;
	}

	.smaller input[type="text"],
	.smaller input[type="number"],
	.smaller select {
		font-size: 12px;
	}

	.blue_tbl input[type="text"] {
		width: 100%;
	}

	.report_tbl tbody tr.assen_MOL00001001 td {
		background: #ffc522 !important;
	}

	.report_tbl tbody tr.assen_MOL00001002 td {
		background: #ff8c00 !important;
	}
</style>

<script>
	var $today = '<?php echo $today ?>';
	var $new_id = <?php echo intval($rack + 1) ?>;
	var $use_zebra = <?php echo $print_zebra ? 'true' : 'false' ?>;
	var $use_zebra_size = '<?php echo !empty($print_zebra_size) ? $print_zebra_size : '2x1' ?>';
	var $add_doc_to_label = <?php echo tr_get_option('ci_pdf_data', 'barcode_doc') ? 'true' : 'false' ?>;
	var $disable_successful_print_popup = <?php echo tr_get_option('ci_pdf_data', 'disable_successful_print_popup') ? 'true' : 'false' ?>;
	<?php if (!empty($newurl)) {
		echo 'window.history.pushState("", "", \'' . $newurl . '\');';
	} ?>
</script>
<?php if ($print_zebra) { ?>
	<script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-3.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
	<script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-Zebra-1.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
<?php } ?>
<script src="<?php echo TRCPDD_URL . 'js/printer.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
<script src="<?php echo TRCPDD_URL . 'js/admin_racks.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
