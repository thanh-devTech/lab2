<?php
global $wpdb;
global $access_btypes;
global $ci_all_btypes;


$receiving_keys         = tr_get_option('ci_pdf_data', 'receiving_keys');
$print_zebra            = tr_get_option('ci_pdf_data', 'print_zebra');
$print_zebra_size       = tr_get_option('ci_pdf_data', 'print_zebra_size');
$print_2_labels         = tr_get_option('ci_pdf_data', 'receiving_print2');
$receiving_show_panel   = true;
$receiving_time_bg      = tr_get_option('ci_pdf_data', 'receiving_time_bg');
$receiving_priority_bg  = tr_get_option('ci_pdf_data', 'receiving_priority_bg');
$show_stat              = tr_get_option('ci_pdf_data', 'enable_stat');
$show_mani              = tr_get_option('ci_pdf_data', 'receiving_mani');
$datetime_now 			= date_i18n('Y-m-d H:i:s');
$user_id 				= get_current_user_id();
$hournow 				= date_i18n('H');
$today 					= date_i18n('Y-m-d');


$base_positions     	= trpdd_get_receiving_base_positions();
$pcr_settings       	= get_option('ci_req_settings_pcr', array());

$start_positions    	= array();
$specimen_types_all 	= array();
$show_tracking_code 	= true;
$show_doctor			= tr_get_option('ci_pdf_data', 'receiving_hdoctor', false) == false;
$show_insurance			= tr_get_option('ci_pdf_data', 'receiving_sinsu', false);

$count_clinics			= $wpdb->get_var("select count(*) from ci_clinic where cli_status = 1");
$default_clinic			= '';
if ($count_clinics == 1) {
	$default_clinic = $wpdb->get_var("select cli_clinic from ci_clinic where cli_status = 1");
}


if (in_array('req', $ci_all_btypes)) {
	$show_tracking_code = false;
	$show_doctor		= false;
}

if (defined('TRPDD_HOUR_CHANGE_BATCH') && TRPDD_HOUR_CHANGE_BATCH > 0) {
	if (TRPDD_HOUR_CHANGE_BATCH > 13 && $hournow >= TRPDD_HOUR_CHANGE_BATCH) {
		//$today = date('Y-m-d', strtotime($today) + 86400);
	} else if (TRPDD_HOUR_CHANGE_BATCH < 7 && $hournow < TRPDD_HOUR_CHANGE_BATCH) {
		$today = date('Y-m-d', strtotime($today) - 86400);
	}
}

if (isset($_REQUEST['today'])) {
	$today = $_REQUEST['today'];
}

$batch_id           = tr_pdd_get_current_batch_id($today);



$key_types          = array();
foreach ($base_positions as $k => $vl) {
	$key_types[] = $k;
}

//check ci_receiving table fields
$fields = $wpdb->get_col("show columns from ci_receiving");
foreach ($key_types as $k) {
	$qty_field = 're_qty_' . $k;
	if (!in_array($qty_field, $fields)) {
		$wpdb->query("alter table ci_receiving add column {$qty_field} int(6) default 0");
	}
	$prev_field = 're_prev_pos_' . $k;
	if (!in_array($qty_field, $fields)) {
		$wpdb->query("alter table ci_receiving add column {$prev_field} int(4) default 0");
	}
}




$specimens = $wpdb->get_results("select ts.ts_type,
                                        ts.ts_name,
										tg.tg_ID
                                        from ci_test_specimens ts
                                        left join ci_test_groups tg ON tg.tg_ID = ts.ts_group_id
                                                                and tg.tg_type = ts.ts_type
                                        where tg.tg_show_req = 1 or tg.tg_ID is null
										order by ts_group_id, ts_ID asc ");
foreach ($specimens as $r) {
	$specimen_types_all[$r->ts_type][$r->ts_name] = $r->ts_name;
	$specimen_types_all[$r->ts_type . '_' . $r->tg_ID][$r->ts_name] = $r->ts_name;
}

if (isset($_GET['debug2'])) {
	var_dump($specimen_types_all);
}



$sql_labs = "select * from ci_labs where 1 ";

$sql_receiving = "select * from ci_receiving where re_today>='{$today}' and re_type = 0";
if (isset($_REQUEST['today'])) {
	$sql_receiving = "select * from ci_receiving where re_today='{$today}' and re_type = 0";
}

if (CI_Base_Lab::has_user_labs()) {
	$lab_str = CI_Base_Lab::get_user_lab_ids_str();

	$sql_labs .= "and lab_ID in ( {$lab_str} ) ";
	$sql_receiving .= " and re_lab_ID in ( {$lab_str} ) ";
} else {
	$sql_labs .= " and lab_status = 1 ";
}

$sql_labs .= " order by lab_is_ref asc ";
$sql_receiving .= " order by re_ID asc";

$results = $wpdb->get_results($sql_receiving, ARRAY_A);


$labs = $wpdb->get_results($sql_labs, ARRAY_A);

if (count($results) == 0) {
	$results[] = array(
		're_today' => $today,
		're_lab_ID' => CI_Base_Lab::get_current_lab_id(),
		're_timing' => '72',
		're_priorty' => 'low',
		're_collector' => 'N/A',
		're_qty_urine' => '0',
		're_qty_oral' => '0',
		're_qty_jvhl' => '0',
		're_qty_blood' => '0',
		're_qty_dna' => '0',
		're_qty_jvhlo' => '0',
		're_qty_rpcr' => '0',
		're_qty_nfpcr' => '0',
		're_qty_pcr' => '0',
	);
}

$ts = $wpdb->get_results("select tg_ID, 
								tg_parent,
                                tg_name, 
                                tg_label_name,
								tg_show_req,
                                tg_notestcheckbox 
                                from ci_test_groups");
$all_groups = array();
foreach ($ts as $t) {
	$all_groups[$t->tg_ID] = $t;
}

$all_tests = array();
$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels");
foreach ($ts as $t) {
	$all_tests[$t->te_ID] = $t;
}

if (isset($_GET['debug'])) {
	var_dump($sql_receiving);
}

$table_num_cols = count($base_positions) + 8;
if (!$show_tracking_code) {
	$table_num_cols--;
}

?>

<div class="wrap">
	<?php Tr_Session_Class::show() ?>


	<div id="loading_form">
		<div class="loading"></div>
	</div>

	<div id="form_receiving" data-print2="<?php echo $print_2_labels ? 1 : 0 ?>" style="display: none;">
		<div style="float:right;padding-bottom:10px;padding-top:10px">
			<span style="line-height: 32px;margin-right:15px">Current Batch: <span id="current_batch"><?php echo $batch_id ?></span></span>
			<?php if (tr_get_option('ci_pdf_data', 'multi_batch_id')) { ?>
				<a class="button add_batch_no" style="margin-right:20px">New Batch</a>
			<?php } ?>
			<a class="button add_all_current_batch bsty6" data-bid="<?php echo $batch_id ?>">Add All to Current Batch</a>
		</div>

		<div class="wraptbl">
			<table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
				<thead class="sticky">
					<tr>
						<th style="width:18px"></th>
						<th style="width: 90px;">Today</th>
						<?php if ($show_tracking_code) { ?>
							<th class="smaller">Tracking Number Barcode</th>
						<?php } ?>
						<th style="width:55px">Lab</th>
						<th style="width:60px">Priority</th>
						<th style="width:60px">Timing</th>
						<th>Practice</th>
						<th>Collector</th>
						<?php
						if ($show_mani) {
							echo '<th style="width:50px">Manifest</th>';
						}
						foreach ($base_positions as $k => $arr) {
							$name = empty($arr['subtype']) ? $arr['name'] : strtoupper($arr['subtype']);
							echo '<th class="smaller" style="width:45px">Qty<br>' . $name . '</th>';
						}
						?>

					</tr>
				</thead>
				<tbody>
					<?php
					$i = 0;

					foreach ($results as $row) {
						$re_ID = isset($row['re_ID']) ? $row['re_ID'] : 0;
						$start_positions[$re_ID] = array();
						$re_start_pos_str = '';
						$positionkey = '';

						foreach ($base_positions as $k => $arr) {
							$start_positions[$re_ID][$k] = (!isset($row['re_prev_pos_' . $k]) || $row['re_prev_pos_' . $k] == 0) ? $arr['position'] : $row['re_prev_pos_' . $k];
						}


						if (isset($_GET['debug2'])) {
							foreach ($start_positions[$re_ID] as $k => $stp) {
								$re_start_pos_str .= " data-{$k}=\"{$stp}\" ";
							}
						}


						$child_sql = array();
						foreach (tr_pdd_get_batch_types() as $btype) {
							$batch_tbl = tr_pdd_get_table_by_btype($btype);

							$child_sql[] = "(select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.panel_tests_requested,ba.culture,ba.dob,ba.priority_hours, ba.insurance,
                                    rb.btype,rb.created,rb.subtype
                                    from {$batch_tbl} ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                        where rb.re_ID = '{$re_ID}' ) ";
						}

						$child_sql = implode(' UNION ALL ', $child_sql);

						$sql = "select * from ({$child_sql}) as ball
                                order by ball.position desc";

						if (isset($_GET['debug'])) {
							echo $sql;
							echo '<br><br><br>';
						}
						$batches = $wpdb->get_results($sql);

						$row['re_practice'] = str_replace('\\', '', isset($row['re_practice']) ? $row['re_practice'] : '');

						if (empty($row['re_practice'])) {
							$row['re_practice'] = $default_clinic;
						}
					?>
						<tr class="parent_c smaller <?php if ($i == 0) echo 'first' ?>" data-p="batch_<?php echo $i ?>">
							<td><i></i></td>
							<td><input type="text" data-fname="re_today" class="date" value="<?php echo $row['re_today'] ?>" readonly />
								<input type="hidden" data-fname="re_ID" value="<?php echo isset($row['re_ID']) ? $row['re_ID'] : '' ?>" />
							</td>
							<?php if ($show_tracking_code) { ?>
								<td><input type="text" data-fname="re_tracking_number_barcode" value="<?php echo isset($row['re_tracking_number_barcode']) ? $row['re_tracking_number_barcode'] : '' ?>" required /> </td>
							<?php } ?>
							<td>
								<select data-fname="re_lab_ID" required style="width:60px">
									<option value="">Select Lab</option>
									<?php foreach ($labs as $lab) {
										$selected = $lab['lab_ID'] == @$row['re_lab_ID'] || count($labs) == 1 ? 'selected' : '';
										echo '<option value="' . $lab['lab_ID'] . '" ' . $selected . '>' . $lab['lab_dba_init'] . '</option>';
									} ?>
								</select>
							</td>
							<td>
								<?php tr_select_option('rec[' . $i . '][re_priority]', $priorities, isset($row['re_priority']) ? $row['re_priority'] : '', array('attr' => 'data-fname="re_priority" ')); ?>
							</td>
							<td>
								<?php tr_select_option('rec[' . $i . '][re_timing]', $timings, isset($row['re_timing']) ? $row['re_timing'] : '', array('attr' => 'data-fname="re_timing" ')); ?>
							</td>
							<td><input type="text" data-fname="re_practice" value="<?php echo stripslashes($row['re_practice']) ?>" required /> </td>
							<td><input type="text" data-fname="re_collector" value="<?php echo stripslashes($row['re_collector']) ?>" required /> </td>
							<?php
							if ($show_mani) {
								echo '<td class="center"><input type="checkbox" data-fname="re_manifest" value="1" ' . checked(isset($row['re_manifest']) ? $row['re_manifest'] : false, true, false) . ' /> </td>';
							}
							foreach ($base_positions as $k => $arr) {
								$qty_key = 're_qty_' . $k;
								$attr = '';
								if ($k == 'pcr33') {
									$attr = 'required';
								} else if (!isset($_REQUEST['debug2'])) {
									$attr = 'readonly';
								}
								if ($row[$qty_key] > 0) {
									$positionkey = $k;
								} else if (isset($row['re_ID']) && $row['re_ID'] > 0) {
									//check why
									$row[$qty_key] = $wpdb->get_var("select count(*) from ci_receiving_batches rb
								where re_ID = {$row['re_ID']} and btype = '{$k}'");
									if ($row[$qty_key] > 0) {
										$wpdb->update('ci_receiving', array($qty_key => $row[$qty_key]), array('re_ID' => $row['re_ID']));
									}
								}
							?>
								<td><input type="number" class="hidearrow" min="0" data-fname="<?php echo $qty_key ?>" value="<?php echo ($row[$qty_key]) ?>" <?php echo $attr ?> /> </td>
							<?php
							}
							?>
						</tr>
						<tr class="child <?php if ($i == 0) echo 'first_child' ?> p_batch_<?php echo $i ?>" data-id="<?php echo $i ?>">
							<td colspan="<?php echo $table_num_cols ?>">
								<select class="btype" style="width:80px">
									<?php
									foreach ($base_positions as $k => $arr) {
										$selected = $k == $positionkey ? 'selected' : '';
										echo '<option value="' . $k . '" ' . $selected . '>' . $arr['name'] . '</option>';
									}
									?>
								</select>
								<input type="text" placeholder="Barcode" class="barcode_auto" oninput="this.value = this.value.toUpperCase()" style="width:300px">

								<small>Press Enter to add</small>

								<a class="button printall_btn bsty1" title="Exclude JVHL" style="margin-left:15px">Print All</a>
								<a class="button printall_btn reprint bsty2" title="Reprint exclude Oral & JVHL" style="margin-left:15px">Reprint</a>
								<div class="warning_duplicate"></div>

								<table class="posts report_tbl batch_tbl subretbl" <?php echo $re_start_pos_str ?>>
									<thead>
										<tr>
											<th style="width:20px">#</th>
											<th style="width:70px">Position</th>
											<th style="width:100px">Barcode</th>
											<th style="width:<?php echo $show_doctor || $show_insurance ? '80px' : '150px' ?>">DOC</th>
											<th>Patient Name</th>
											<?php
											if (tr_get_option('ci_pdf_data', 'receiving_sdob')) {
												echo '<th style="width:100px">DOB</th>';
											}
											?>
											<th>Office</th>
											<?php if ($show_doctor) { ?>
												<th>Doctor</th>
											<?php }
											if ($show_insurance) {
												echo '<th>Insurance</th>';
											}
											?>
											<?php if ($receiving_show_panel) { ?>
												<th>Panel</th>
											<?php } ?>
											<th style="width:18%">Specimen Type</th>
											<?php if ($receiving_priority_bg) { ?>
												<th title="Priority" style="width:5px"></th>
											<?php } ?>
											<th></th>
											<th style="width:60px">Print Barcode</th>
											<th style="width:30px"></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$total_b = count($batches);
										foreach ($batches as $ii => $batch) {
											$show_specimen_type = true;
											$specimen_types 	= isset($specimen_types_all[$batch->btype]) ? $specimen_types_all[$batch->btype] : array();
											$specimen_selecteds = !empty($batch->type_of_specimen) ? explode(',', $batch->type_of_specimen) : array();

											$key_pos = $batch->btype;
											if (!empty($batch->initials1)) {
												$key_pos = strtolower($batch->initials1);
											} else if ($batch->btype == 'tox') {
												$key_pos = strtolower($batch->type_of_specimen);
											}

											if ($batch->btype == 'pcr' && (empty($batch->initials1) || $batch->initials1 == 'pcr')) {
												$show_specimen_type = false;
											}

											$batch->oldmid = $batch->mid;
											if ($batch->btype != 'tox') {
												$batch->mid = $batch->btype . '_' . $batch->mid . '_' . $batch->subtype;
											}


											$post_number = intval($batch->position);

											if ($post_number > 0 && $post_number < $start_positions[$re_ID][$key_pos]) {
												$start_positions[$re_ID][$key_pos] = $post_number;
											}

											$batch->alcohol = stripos($batch->panel_tests_requested, 'etg,') !== false && stripos($batch->panel_tests_requested, ',ets') !== false;
											if (!empty($batch->dob)) {
												$batch->dob = tr_pdd_display_date($batch->dob);
											}
											$panels_print 	= array();
											$panels_ordered = array();
											$group_ids		= array();
											$panels_specimens = array();
											$has_change_spec = false;
											if (in_array($batch->btype, array('pcr', 'blood'))) {

												foreach (explode(',', $batch->panel_tests_requested) as $t) {
													if (stripos($t, 'GROUP_') !== false) {
														$group_id = str_replace('GROUP_', '', $t);
														$group_ids[] = $group_id;
														if (isset($all_groups[$group_id])) {
															$panels_ordered[] = $all_groups[$group_id]->tg_name;

															if (!empty($all_groups[$group_id]->tg_label_name)) {
																$panels_print[] = $all_groups[$group_id]->tg_label_name;
															} else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
																foreach ($all_tests as $test) {
																	if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
																		$panels_print[] = $test->te_label_barcode;
																	}
																}
															}
														}

														if ($batch->btype == 'pcr') {
															if (isset($specimen_types_all[$batch->btype . '_' . $group_id])) {
																foreach ($specimen_types_all[$batch->btype . '_' . $group_id] as $spec) {
																	if (!empty($spec)) {
																		$panels_specimens[$spec] = $spec;
																	}
																}


																if (count($specimen_types_all[$batch->btype . '_' . $group_id]) == 1) {
																	foreach ($specimen_types_all[$batch->btype . '_' . $group_id] as $spec) {
																		if (!empty($spec) && !in_array($spec, $specimen_selecteds)) {
																			$specimen_selecteds[] = $spec;
																			$has_change_spec = true;
																		}
																	}
																}
															}
														}
													} else if ($t > 0 && isset($all_tests[$t])) {
														if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
															$panels_print[] = $all_tests[$t]->te_label_barcode;
														}
													}
												}


												if ($batch->btype == 'pcr' && !empty($group_ids)) {
													$specimen_types = $panels_specimens;
													if ($has_change_spec) {
														$specimen_selecteds_str = implode(',', $specimen_selecteds);
														$batch_tbl = tr_pdd_get_table_by_btype($batch->btype);
														$wpdb->update($batch_tbl, array('type_of_specimen' => $specimen_selecteds_str), array('mid' => $batch->oldmid));
													}
												}
											}



											$panels_print_str = implode('|', $panels_print);
											if ($batch->btype == 'blood') {
												$panels_print_str = '';
											}
											$hourcolor = '';
											if ($receiving_time_bg) {
												$hour = date('H', strtotime($batch->doc));
												if ($hour >= 8 && $hour <= 12) {
													$hourcolor = 'bg_red';
												} else if ($hour > 12 && $hour <= 16) {
													$hourcolor = 'bg_orange';
												} else if ($hour > 16 && $hour <= 20) {
													$hourcolor = 'bg_blue';
												}
											}
											$assen_display = $batch->assen;
											if (!empty($batch->subtype)) {
												$assen_display = substr($batch->assen, 0, 2) . $batch->subtype . substr($batch->assen, 3);
											}
										?>
											<tr class="childp assen_<?php echo $batch->assen ?>" data-key_pos="<?php echo $key_pos ?>" data-cul="<?php echo $batch->culture ?>" data-panels_print="<?php echo $panels_print_str ?>" data-dob="<?php echo $batch->dob ?>" data-spec_type="<?php echo $batch->type_of_specimen ?>" data-btype="<?php echo $batch->btype ?>" data-pos="<?php echo $batch->position ?>">
												<td title="<?php echo 'created:', $batch->created ?>"><?php echo $total_b - $ii ?><input type="hidden" class="mid" value="<?php echo $batch->mid ?>" /></td>
												<td class="col-position"><input type="text" value="<?php echo $batch->position ?>" /></td>
												<td class="assen"><?php echo $assen_display ?></td>
												<td class="<?php echo $hourcolor ?> doc col-doc" title="Background by DOC">
													<?php
													if (!$show_doctor) {
														echo '<input type="text" class="hasdatetime" value="' . $batch->doc . '"/>';
													} else {
														echo tr_pdd_display_date($batch->doc, 'Y-m-d H:i');
													}

													?>
												</td>
												<td class="patient_name"><?php echo $batch->patient_name ?></td>
												<?php
												if (tr_get_option('ci_pdf_data', 'receiving_sdob')) {
													echo '<td class="dob">' . $batch->dob . '</td>';
												}
												?>
												<td><?php echo $batch->requesting_practice ?></td>
												<?php
												if ($show_doctor) {
												?>
													<td><?php echo $batch->requesting_physician ?></td>
												<?php
												}
												if ($show_insurance) {
													echo '<td>' . $batch->insurance . '</td>';
												}
												?>
												<?php if ($receiving_show_panel) { ?>
													<td><?php echo implode(',<br>', $panels_ordered) ?></td>
												<?php } ?>
												<td class="spec_type">
													<?php
													$specimen_cbtype = 'radio';
													if ($batch->btype == 'pcr') {
														$specimen_cbtype = 'checkbox';
													} else if ($btype == 'blood') { // && tr_get_option('ci_req_settings_blood', 'specimen_checkbox')
														$specimen_cbtype = 'checkbox';
													}
													foreach ($specimen_types as $k => $vl) {
													?>
														<div><label><input type="<?php echo $specimen_cbtype ?>" class="spec_type" name="spec_type[<?php echo $batch->mid ?>]" value="<?php echo $k ?>" <?php checked(in_array($k, $specimen_selecteds)) ?> /> <?php echo $vl ?> </label></div>
													<?php
													}
													if (!empty($batch->initials1)) {
														echo ' <span class="small">' . $batch->initials1 . '</span>';
													}
													?>
												</td>
												<?php if ($receiving_priority_bg) {
													$background_priority = '';
													if ($batch->priority_hours == '-1') {
														$background_priority = 'bg_green';
													} else if ($batch->priority_hours == '-2' || $batch->stat == 1) {
														$background_priority = 'bg_red';
													}
												?>
													<td type="Priority" class="<?php echo $background_priority ?>"></td>
												<?php } ?>
												<td>
													<?php if ($batch->btype == 'tox') {
													?>
														<label><input type="checkbox" class="alcohol" value="1" <?php checked($batch->alcohol) ?> />
															Alcohol</label>
													<?php
													} else if ($batch->btype == 'pcr' && isset($pcr_settings['enabel_cultureid']) && $pcr_settings['enabel_cultureid']) {
													?>
														<label><input type="checkbox" class="culture" value="1" <?php checked($batch->culture) ?> />
															Culture</label>
													<?php
													} ?>
												</td>
												<td><a class="print_barcode button" href="#">Print</a></td>
												<td><a class="button remove_item">-</a> </td>
											</tr>
										<?php
										}
										?>
									</tbody>
								</table>
							</td>
						</tr>
					<?php
						$i++;
					}
					?>
				</tbody>
			</table>
		</div>

		<br>
		<a class="button add_new_row">+</a>
		<?php /*
    <input type="submit" class="button-primary" id="submit_btn" value="Submit"/>
        */ ?>
		<a class="button show_hide_all right">Show All</a>
	</div>

</div>
<audio src="<?php echo TRCPDD_URL ?>audio/beep.mp3" id="beep_player" loop style="display:none"></audio>
<iframe id="reprint_frame" src="about:blank" style="display: none;"></iframe>

<?php if ($receiving_keys) { ?>
	<div id="dialog_warning" style="display: none;">
		The scanned in sample number is not starting with <?php echo implode(' or ', $receiving_keys) ?>
	</div>
<?php } ?>
<div id="dialog_warning_empty" style="display: none;"></div>
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
</style>

<script>
	var $hour_next_batch = <?php echo intval(TRPDD_HOUR_CHANGE_BATCH) ?>;
	var $new_id = <?php echo intval($i) ?>;
	var $start_positions = <?php echo json_encode($start_positions) ?>;
	var $receiving_keys = <?php echo json_encode($receiving_keys) ?>;
	var $use_zebra = <?php echo $print_zebra ? 'true' : 'false' ?>;
	var $use_zebra_size = '<?php echo !empty($print_zebra_size) ? $print_zebra_size : '2x1' ?>';
	var $add_doc_to_label = <?php echo tr_get_option('ci_pdf_data', 'barcode_doc') ? 'true' : 'false' ?>;
	var $disable_successful_print_popup = <?php echo tr_get_option('ci_pdf_data', 'disable_successful_print_popup') ? 'true' : 'false' ?>;
	var $df_barcode = '<?php echo tr_get_option('ci_pdf_data', 'receiving_dfbcode', '') ?>';
</script>
<?php if ($print_zebra) { ?>
	<script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-3.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
	<script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-Zebra-1.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
	<script src="<?php echo TRCPDD_URL . 'js/printer.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
<?php } ?>
<script src="<?php echo TRCPDD_URL . 'js/admin_receiving.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>