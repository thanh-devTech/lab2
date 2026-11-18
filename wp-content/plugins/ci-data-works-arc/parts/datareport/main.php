<?php
global $wpdb, $current_role, $blog_id;
$user_id = get_current_user_id();

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);



$date_from      = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to        = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id         = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$by             = isset($_REQUEST['by']) ? $_REQUEST['by'] : '';
$all_rows       = array();


$users_show_patient = tr_get_option('ci_pdf_data', 'report_show_patients');
$show_patient_name  = in_array($user_id, (array) $users_show_patient);
$show_status_col = tr_get_option('ci_pdf_data', 'report_show_status_col');
$not_use_search_by  = false;
$hide_columnsend    =  tr_get_option('ci_pdf_data', 'salerp_hidecs');
$hide_rejected      =  tr_get_option('ci_pdf_data', 'salerp_hiderj');
$hide_amountcols      =  tr_get_option('ci_pdf_data', 'report_hide_bp_amount');
$batch_statuses = tr_pdd_get_batch_statuses();
$hide_commission    = false;
$has_result         = false;

$tabs = array(
	'report_sale' => 'Sales Report',
);

if (tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p', 'labmanager'))) {

	$tabs['report_date'] = 'Collection Date Report';
	$tabs['report_info'] = 'Billing Info Report';
}

if (tr_current_role_in(array('AdminCIDW', 'administrator', 'administrator_p'))) {

	$tabs['form_fill'] = 'Form Fill Report';
}

$tabs['calendar']   =  'Specimen Calendar';
$btypes             = tr_pdd_get_batch_types();
$tab                = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

$rep_ids = array();
if ($current_role == 'sales_accounting') {
	$by = 'rep';
	$not_use_search_by = true;
}

if (!tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p', 'labmanager'))) {
	//find rep in group
	$users_reps = tr_dpp_get_users_in_group($user_id);
	foreach ($users_reps as $uid) {
		$rid = get_user_meta($uid, '_rep_id', true);
		if ($rid > 0) {
			$rep_ids[] = $rid;
		}
	}
	if ($current_role == 'distributor') {
		$rep_ids2 = $wpdb->get_col("select rep.rep_ID from ci_lab_reps AS rep 
                                    JOIN ci_distributors dis ON dis.dis_ID = rep.rep_distributor_ID
                                    WHERE dis.dis_user_ID = {$user_id}");
		if (count($rep_ids2) > 0) {
			$rep_ids = array_merge($rep_ids, $rep_ids2);
		}
	}
} else {
	$hide_columnsend = false;
}

if (isset($_GET['hide_columnsend'])) {
	$hide_columnsend = true;
}

if ($hide_columnsend) {
	$hide_commission = true;
}

if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
	$hide_commission = $wpdb->get_var("select rep.rep_hide_commission from ci_lab_reps rep
                                    join {$wpdb->usermeta} um ON um.meta_key='_rep_id' and um.meta_value=rep.rep_ID
                                    where um.user_id = {$user_id}");
}

$lab_id = CI_Base_Lab::get_current_lab_id();



$by_paid_date = isset($_REQUEST['by_paid_date']);
if (isset($_REQUEST['submit'])) {
	$rows = array();

	if (empty($date_from)) $date_from = date_i18n('Y-m-d');
	if (empty($date_to)) $date_to = date_i18n('Y-m-d');
	$from_time = strtotime($date_from);
	$to_time = strtotime($date_to);


	$lab_tox_cost = $wpdb->get_var("select lab_tox_cost from ci_labs where lab_ID = {$lab_id} ");

	$where = ' and ba.cli_ID > 0 ';
	if ($lab_id > 0 && !defined('TRPDD_SAME_LAB')) {
		//$where .= " and LB.lab_ID ='{$lab_id}' ";
		$where .= " and (LB.lab_ID ='{$lab_id}' or ba.lab_id = {$lab_id} or ba.ref_lab_ID = {$lab_id} )";
	}
	//$where .= " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
	if ($tab == 'report_date') {
		$where .= " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
	}
	//else if($by_paid_date)
	//{
	//$where .= " and bb.date >= '{$date_from}' and bb.date <= '{$date_to} 23:59:59' ";
	//}
	else {
		$where .= " and ((ba.spec_received >= '{$date_from}' and ba.spec_received <= '{$date_to} 23:59:59' ) 
        or (ba.spec_received is null and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59'))";
	}

	if ($hide_rejected) {
		$where .= ' and ' . tr_pdd_where_batch_rejected(false, true);
	}


	//where by user role
	if (count($rep_ids) > 0) {
		$rep_ids = implode(',', $rep_ids);
		$where .= " and CLI.`cli_rep-ID` in ({$rep_ids}) ";
	}

	$conditions = trpdd_get_batch_conditions();

	$where      .=  $conditions['where'];
	if ($conditions['check_author']) {
		$where      .= $return['where_author'];
	}

	if (isset($_REQUEST['debug'])) {
		var_dump($rep_ids);
		echo '<br>';
		echo $where;
	}

	$select_fields = "ba.mid,
                    ba.doc, 
                    ba.assen,
                    ba.insurance as pinsname, 
                    ba.requesting_practice, 
                    ba.requesting_physician,
                    bb.received_amount AS collected,
                    bb.claim_date AS billed_date,
                    bb.date AS collected_date,
                    LR.rep_comission,
                    CLI.cli_clinic,
                    ba.patient_name,
                    ba.batch_status,
                    docs.doc_first,
                    docs.doc_last,
                    ba.dob,
					CONCAT(col.col_first,' ',col.col_last) as collector,
                    LR.rep_first,
                    LR.rep_last,
                    if(pl.payer_commercial='1','C',
                    if(pl.payer_medicaid='1','A',
                    if(pl.payer_medicare='1','M',
                    if(pl.payer_workmen='1','W',
                    if(pl.payer_other='1','O',''))))) as ins_mix
                    ";

	$joins = "  left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left JOIN `ci_labs` LB ON ba.lab =  LB.lab_dba_init or ba.lab_id = LB.lab_ID or ba.ref_lab_ID = LB.lab_ID
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
                left join `ci_doc` as docs ON docs.doc_ID = ba.doc_ID
                left join `ci_collector` as col ON col.col_ID = ba.col_ID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID` 
                left JOIN ci_payer_list pl ON pl.payer_name = ba.insurance
                ";



	$order_by = ' pinsname asc';
	$batch_tbl = 'ci_batches';

	if ($by == 'cli') {
		$search = isset($_REQUEST['clinic']) ? $_REQUEST['clinic'] : '';
		if (!empty($search)) {
			$where .= " and ba.requesting_practice like '%{$search}%'";
		}
		$order_by = "ba.requesting_practice";
	} else if ($by == 'doc') {
		$search = isset($_REQUEST['doctor']) ? $_REQUEST['doctor'] : '';

		$select_fields .= ", doc.doc_npi ";
		$joins .= " left join ci_doc doc ON doc.doc_ID = ba.doc_id ";
		if (!empty($search)) {
			$where .= $wpdb->prepare(" and ba.requesting_physician like %s", '%' . $search . '%');
		}
		$order_by = "ba.requesting_physician";
	} else if ($by == 'rep') {
		$search = isset($_REQUEST['rep']) ? $_REQUEST['rep'] : '';
		if (!empty($search)) {
			$where .= " and (LR.rep_first like '%{$search}%' or LR.rep_last like '%{$search}%') ";
		}
		$select_fields .= ",concat(LR.rep_first,' ',LR.rep_last) as rep";
		$order_by = "rep";
	} else if ($by == 'distributor') {
		$search = isset($_REQUEST['dis']) ? $_REQUEST['dis'] : '';
		if (!empty($search)) {
			$where .= " and (DIS.dis_name like '%{$search}%' ) ";
		}
		$joins .= " left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID or DIS.dis_ID = LR.rep_distributor_ID ";
		$select_fields .= ",DIS.dis_name,DIS.dis_comission";
		$order_by = "dis_name";
	}

	if ($tab == 'report_info') {
		$select_fields .= ",concat(LR.rep_last,', ',LR.rep_first) as rep,ba.billing_notes";
		$where .= " and (bill_status='Need Info')";

		$order_by = "rep,cli_clinic ASC ";
	} else if ($tab == 'report_date') {

		$select_fields .= ",dis_name as distributor";
		if (strpos($joins, 'ci_distributors') === false) {
			$joins .= " left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID or DIS.dis_ID = LR.rep_distributor_ID ";
		}

		$order_by = "ba.doc DESC";
	}

	$all_rows = array();
	if ($tab == 'report_sale') {

		foreach ($btypes as $k => $btype) {
			$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);
			$result_tbl = tr_pdd_get_table_result_by_btype($btype);

			$select_fields_by_type = $select_fields;
			$joins_by_type = $joins;

			if (tr_get_option('ci_pdf_data', 'report_scompleted')) {
				$joins_by_type .= " JOIN {$report_tbl} re2 ON re2.estatus='C' and ba.assen = re2.patient_id ";
			}

			$query = " select {$select_fields} ,
				'{$btype}' as btype
                from {$batch_tbl} ba 
                {$joins_by_type}
                where 1 {$where} 
                group by ba.mid 
                order by {$order_by}";
			$all_rows[$btype] = $wpdb->get_results($query);

			if (isset($_GET['debug'])) {
				echo "<br>";
				echo $query;
				echo "<br>";
				echo "<br>";
				echo $wpdb->last_error;
				echo "<br>";
			}
		}
	} else {

		$child = array();
		foreach ($btypes as $k => $btype) {
			$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);
			$result_tbl = tr_pdd_get_table_result_by_btype($btype);

			$select_fields_by_type = $select_fields;
			$joins_by_type = $joins;

			if ($tab == 'report_date') {
				$select_fields_by_type .= ",re.id as has_results, rsp.id as has_pending";
				$joins_by_type .= " left JOIN {$report_tbl} re ON re.estatus='C' and ba.assen = re.patient_id 
                					LEFT JOIN {$result_tbl} rsp ON rsp.report_id = re.id 
                    				and rsp.`pos-neg`= 'PEN' ";
			}


			$child[] = " ( select {$select_fields_by_type} ,
				'{$btype}' as btype
                from {$batch_tbl} ba 
                {$joins_by_type}
                where 1 {$where} 
                group by ba.mid 
                order by {$order_by} )";
		}
		$child = implode(' UNION ALL ', $child);
		$query = "select * from ({$child} ) rs";
		$rows = $wpdb->get_results($query);
	}


	$count_rows = count($rows);
}

if (!isset($_REQUEST['trajax'])) {
?>

	<h2 class="nav-tab-wrapper">
		<?php

		foreach ($tabs as $k => $txt) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'batch_id' => '', 'dis' => '', 'rep' => '', 'doctor' => '', 'date_from' => '', 'date_to' => '', 'submit' => '', 'clinic' => '', 'by_paid_date' => '', 'view' => ''), false);
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
	</h2>
	<br>
<?php
}

if ($tab == 'calendar') {
	include(dirname(__FILE__) . '/calendar.php');
} else if ($tab == 'form_fill') {
	include(dirname(__FILE__) . '/form_fill.php');
} else {
?>

	<div class="wrap_report">
		<?php
		if ($tab == 'report_date') {
			include dirname(__FILE__) . "/date.php";
		} elseif ($tab == 'report_info') {
			include dirname(__FILE__) . "/info.php";
		} else {
		?>
			<div>
				<form method="get" class="form_sale_report">
					<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
					<input type="hidden" name="tab" value="<?php echo $tab; ?>" />
					<span class="onelinemobile">
						<label>From: </label>
						<input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" autocomplete="off" />
					</span>
					<span class="onelinemobile">
						<label>To: </label>
						<input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" autocomplete="off" />
					</span>
					<?php
					global $current_role, $blog_id;
					if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms')) && ($blog_id == 1)) {
						$labs = $wpdb->get_results("select lab_ID,lab_name from ci_labs where (lab_is_ref=0 or lab_is_ref=2) ");

						echo '<label>Lab: </label><select name="lab_id" style="margin-top:-3px">';
						foreach ($labs as $lab) {
							$selected = ($lab->lab_ID == $lab_id) ? 'selected' : '';
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
						}
						echo '</select>';
					} else {
					?>
						<input type="hidden" name="lab_id" value="<?php echo CI_Base_Lab::get_current_lab_id() ?>">
					<?php
					}
					?>
					<input type="submit" name="submit" id="export" onclick="form_sale_run_action();return false;" value="Run Report" class="button bsty6" />
					<input type="submit" id="submit_form" style="width:1px;height: 1px;padding: 0px;position: absolute;top:-1000px;">
					<input type="submit" name="export" value="Export" onclick="form_sale_report_action();return false;" class="button" />
					<?php if (!isset($not_use_search_by) || !$not_use_search_by) : ?>
						<div class="row">
							<?php
							$bys = array(
								'ins' => 'Insurance Mix',
								'cli' => 'Clinic',
								'doc' => 'Doctor'
							);
							if (!isset($rep_ids) || !is_array($rep_ids)) {
								$rep_ids = array();
							}
							if (tr_current_role_in(array('lab', 'administrator', 'distributor', 'AdminCIDW', 'administrator_p')) || count($rep_ids) > 0) {
								$bys['rep'] = 'Rep';
							}
							if (tr_current_role_in(array('lab', 'administrator', 'distributor', 'AdminCIDW', 'administrator_p'))) {
								$bys['distributor'] = 'Distributor';
							}

							if (tr_current_role_in(array('lab_rep', 'lab_rep_w2'))) {
								unset($bys['ins']);
								if (empty($by) || $by == 'ins') {
									$by = 'cli';
								}
							}

							if (empty($by)) {
								$by = is_array($bys) ? key($bys) : '';
							}

							foreach ($bys as $vl => $txt) {
							?>
								<label><input type="radio" class="report_by" name="by" value="<?php echo $vl ?>" <?php checked($vl, $by) ?>>
									by <?php echo $txt ?></label>
							<?php
							}
							?>
							<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'lab', 'lab_rep', 'lab_rep_w2'))) : ?>
								<label><input type="checkbox" name="by_paid_date" value="1" <?php checked($by_paid_date) ?> /> Paid Date </label>
							<?php endif; ?>
						</div>

						<div class="row search_by">
							<div id="search_by_cli" style="display: none;">
								<label>Search by Clinic</label>
								<input type="text" name="clinic" value="<?php echo isset($_REQUEST['clinic']) ? $_REQUEST['clinic'] : '' ?>" />
							</div>
							<div id="search_by_doc" style="display: none;">
								<label>Search by Doctor</label>
								<input type="text" name="doctor" value="<?php echo isset($_REQUEST['doctor']) ? $_REQUEST['doctor'] : '' ?>" />
							</div>
							<div id="search_by_rep" style="display: none;">
								<label>Search by Rep</label>
								<input type="text" name="rep" value="<?php echo isset($_REQUEST['rep']) ? $_REQUEST['rep'] : '' ?>" />
							</div>
							<div id="search_by_distributor" style="display: none;">
								<label>Search by Distributor</label>
								<input type="text" name="dis" value="<?php echo isset($_REQUEST['dis']) ? $_REQUEST['dis'] : '' ?>" />
							</div>
						</div>
					<?php endif; ?>
				</form>
				<script>
					function form_sale_report_action() {
						jQuery('#export').append('<input type="hidden" name="tr_action" class="tr_action" value="ci_export_sale_report">');
						jQuery('#submit_form').click();
					}

					function form_sale_run_action() {
						jQuery('.tr_action').remove();
						jQuery('#export').click();
					}
				</script>
			</div>
			<br><br>

			<?php Tr_Session_Class::show() ?>

			<h2 class="nav-tab-wrapper" id="btype_tabs">
				<?php

				foreach ($btypes as $k => $btype) {
					$txt = tr_pdd_get_typename($btype);
					echo '<a class="nav-tab" href="#tab_' . $btype . '">' . $txt . '</a>';
				}
				?>
			</h2>
			<?php
			echo '<div class="wrap_tabs">';
			foreach ($btypes as $k => $btype) {

				$has_result = false;
				$rows = is_array($all_rows) && isset($all_rows[$btype]) ? $all_rows[$btype] : array();
				if (!is_array($rows)) $rows = array();
				$count_rows = count($rows);
				echo '<div class="tab" id="tab_' . $btype . '">';

				if ($by == 'cli') {
			?>
					<div class="wraptbl">
						<table class="report_tbl" data-count="<?php echo @$count_rows ?>">
							<?php if (isset($_REQUEST['submit'])) { ?>
								<thead class="sticky">
									<tr>
										<th style="width:20px"></th>
										<th style="width:72px">Clinic</th>
										<th style="width:11%">Doctor</th>
										<th style="width:11%">Collector</th>
										<th style="width:15%">Insurance</th>
										<th style="width:10%">Accession</th>
										<?php if ($show_status_col) { ?>
											<th style="width:10%">Status</th>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<th style="width:10%">Patient</th>
										<?php } ?>
										<th style="width:94px">Spec/B/R</th>
										<th style="width:80px">Doc</th>
										<?php if (!$hide_columnsend) { ?>
											<th style="width:80px">Billed Date</th>
											<?php if (!$hide_amountcols) {
												echo '<th style="width:80px">Claim Paid Amount</th>';
											}
											?>

											<th style="width:80px">Claim Paid</th>
											<?php if (!$hide_amountcols) {
												echo '<th>Commission</th>';
											}
											?>

										<?php } ?>
									</tr>
								</thead>
							<?php } ?>
							<tbody>
								<?php
								if (count($rows) > 0) {
									$has_result = true;
								}

								$parents 		= array();
								$html 			= '';
								$current_code 	= null;
								$collected 		= 0;
								$commission 	= 0;
								$count_child 	= 0;
								$count_paid 	= 0;
								$count_bill 	= 0;
								$parent_id 		= 1;
								$column_count 	= 12; //changed
								if ($show_patient_name) {
									$column_count++;
								}
								if ($show_status_col) {
									$column_count++;
								}
								if ($hide_columnsend) {
									$column_count -= 4;
								} else if ($hide_amountcols) {
									$column_count -= 2;
								}
								foreach ($rows as $i => $row) {
									if (!in_array($row->requesting_practice, $parents)) {
										$parents[] = $row->requesting_practice;
										ob_start();
									}
									if (empty($row->collected)) $row->collected = 0;

									$row->commission = trpdd_cal_commission($row->collected, $row->rep_comission, $row->ins_mix, $row->btype);

									if (isset($_GET['te']) && $row->btype == 'blood') {
										var_dump($row);
									}
								?>
									<tr class="childp">
										<td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:72px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:11%"' ?>><?php echo $row->requesting_physician ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:11%"' ?>><?php echo $row->collector ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:15%"' ?>><?php echo $row->pinsname ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->assen ?></td>
										<?php if ($show_status_col) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $batch_statuses[$row->batch_status] ?></td>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->patient_name ?></td>
										<?php } ?>
										<td <?php if ($count_child == 0) echo 'style="width:94px"' ?>><span><?php echo 1 ?></span>
										</td>
										<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php echo date('Y-m-d', strtotime($row->doc)) ?></td>
										<?php if (!$hide_columnsend) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php echo $row->billed_date ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>>
													<?php
													if (tr_current_role_in(array('lab_rep', 'lab_rep_w2', 'distributor')) && $row->collected > 0) {
														if ($row->collected >= $lab_tox_cost) {
															echo '<span class="dashicons dashicons-yes"></span>';
														} else {
															echo '<span class="dashicons dashicons-yes orange"></span>';
														}
													} else {
														echo number_format($row->collected, 2);
													}
													?>
												</td>
											<?php } ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php if (intval($row->collected) != 0) {
																												echo $row->collected_date;
																											} ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td><span><?php echo number_format($row->commission, 2, '.', '') ?></span></td>
											<?php } ?>
										<?php } ?>
									</tr>

									<?php
									$collected += $row->collected;
									$commission += $row->commission;
									$count_child++;
									if ($by_paid_date) {
										$col_time = strtotime($row->collected_date);

										if ($col_time >= $from_time && $col_time <= $to_time) {
											$count_paid += ($row->collected > 0) ? 1 : 0;
										}
									} else {
										$count_paid += ($row->collected > 0) ? 1 : 0;
									}

									$count_bill += !empty($row->billed_date) ? 1 : 0;
									$has_result = true;
									$next_row = $rows[$i + 1];


									if (($next_row && !in_array($next_row->requesting_practice, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
										$html = ob_get_clean();
										if (empty($row->requesting_practice)) {
											$row->requesting_practice = 'Unknown';
										}
										$colspan = 5;
										if ($show_patient_name) {
											$colspan++;
										}
										if ($show_status_col) {
											$colspan++;
										}
									?>
										<tr class="parent" data-p="<?php echo $parent_id ?>">
											<td><i></i></td>
											<td colspan="<?php echo $colspan ?>"><?php echo $row->requesting_practice ?></td>
											<td>
												<span><?php echo '<span>' . $count_child . '</span> / <span class="blue">' . $count_bill . '</span> / <span class="lgreen">' . $count_paid . '</span>' ?></span>
											</td>
											<td></td>
											<?php if (!$hide_columnsend) { ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>
													<td>
														<?php
														if (tr_current_role_in(array('lab', 'administrator', 'administrator_p')) || $collected == 0) {
															echo '<span>' . number_format($collected, 2, '.', '') . '</span>';
														} else {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
														?>
													</td>
												<?php } ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>

													<td><span><?php echo number_format($commission, 2, '.', '') ?></span></td>
												<?php } ?>
											<?php } ?>
										</tr>
										<tr class="child p_<?php echo $parent_id ?>">
											<td colspan="<?php echo $column_count ?>" class="nopadding">
												<table style="width: 100%;" class="report_tbl">
													<?php echo $html ?>
												</table>
											</td>
										</tr>
								<?php
										$parent_id++;
										$collected = 0;
										$commission = 0;
										$count_child = 0;
										$count_paid = 0;
										$count_bill = 0;
									}
								}

								?>
							</tbody>
						</table>
					</div>
					<div>Total: <?php echo $count_rows ?></div>

				<?php
				} else if ($by == 'doc') {
				?>
					<div class="wraptbl">
						<table class="report_tbl" data-count="<?php echo @$count_rows ?>">
							<?php if (isset($_REQUEST['submit'])) : ?>
								<thead class="sticky">
									<tr>
										<th style="width:20px"></th>
										<th style="width:6%">Doctor</th>
										<th style="width:11%">Collector</th>
										<th style="width:15%">Clinic</th>
										<th style="width:9%">Insurance</th>
										<th style="width:9%">NPI/Rep</th>
										<th style="width:72px">Accession</th>
										<?php if ($show_status_col) { ?>
											<th style="width:10%">Status</th>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<th style="width:10%">Patient</th>
										<?php } ?>
										<th style="width:65px">Spec/B/R</th>
										<th style="width:76px">Doc</th>
										<?php if (!$hide_columnsend) { ?>
											<th style="width:76px">Billed Date</th>
											<?php if (!$hide_amountcols) { ?>
												<th style="width:80px">Claim Paid Amount</th>
											<?php } ?>
											<th style="width:80px">Claim Paid</th>
											<?php if (!$hide_amountcols) { ?>
												<th>Commission</th>
											<?php } ?>
										<?php } ?>
									</tr>
								</thead>
							<?php endif; ?>
							<tbody>
								<?php
								if (count($rows) > 0) {
									$has_result = true;
								}

								$parents = array();
								$html = '';
								$current_code = null;
								$collected = 0;
								$commission = 0;
								$count_child = 0;
								$count_paid = 0;
								$count_bill = 0;
								$parent_id = 1;
								$column_count = 13; //changed
								if ($show_patient_name) {
									$column_count++;
								}
								if ($show_status_col) {
									$column_count++;
								}

								if ($hide_columnsend) {
									$column_count -= 4;
								} else if ($hide_amountcols) {
									$column_count -= 2;
								}

								foreach ($rows as $i => $row) {
									if (!in_array($row->requesting_physician, $parents)) {
										$parents[] = $row->requesting_physician;
										ob_start();
									}

									if (empty($row->collected)) $row->collected = 0;


									$row->commission = trpdd_cal_commission($row->collected, $row->rep_comission, $row->ins_mix, $row->btype);
								?>
									<tr class="childp">
										<td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:6%"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:15%"' ?>><?php echo $row->requesting_practice ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:15%"' ?>><?php echo $row->collector ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:9%"' ?>><?php echo $row->pinsname ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:9%"' ?>><?php echo $row->rep_first . ' ' . $row->rep_last ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:72px"' ?>><?php echo $row->assen ?></td>
										<?php if ($show_status_col) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $batch_statuses[$row->batch_status] ?></td>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->patient_name ?></td>
										<?php } ?>
										<td <?php if ($count_child == 0) echo 'style="width:65px"' ?>><span><?php echo 1 ?></span>
										</td>
										<td <?php if ($count_child == 0) echo 'style="width:76px"' ?>><?php echo date('Y-m-d', strtotime($row->doc)) ?></td>
										<?php if (!$hide_columnsend) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:76px"' ?>><?php echo $row->billed_date ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>>
													<?php
													if (tr_current_role_in(array('lab_rep', 'lab_rep_w2', 'distributor')) && $row->collected > 0) {
														if ($row->collected >= $lab_tox_cost) {
															echo '<span><span class="checkmark"></span></span>';
														} else {
															echo '<span><span class="checkmark_orange"></span></span>';
														}
													} else {
														echo number_format($row->collected, 2, '.', '');
													}
													?>
												</td>
											<?php } ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php if (intval($row->collected) != 0) {
																												echo $row->collected_date;
																											} ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td>
													<?php if ($hide_commission) {
														if ($row->commission > 0) {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
													} else {
														echo '<span>' . number_format($row->commission, 2, '.', '') . '</span>';
													}
													?>
												</td>
											<?php } ?>
										<?php } ?>
									</tr>

									<?php
									$collected += $row->collected;
									$commission += $row->commission;
									$count_child++;
									if ($by_paid_date) {
										$col_time = strtotime($row->collected_date);
										if ($col_time >= $from_time && $col_time <= $to_time) {
											$count_paid += ($row->collected > 0) ? 1 : 0;
										}
									} else {
										$count_paid += ($row->collected > 0) ? 1 : 0;
									}
									$count_bill += !empty($row->billed_date) ? 1 : 0;

									$has_result = true;
									$next_row = $rows[$i + 1];


									if (($next_row && !in_array($next_row->requesting_physician, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
										$html = ob_get_clean();
										if (empty($row->requesting_physician)) {
											$row->requesting_physician = 'Unknown';
										}
										$colspan = 3;
										if ($show_patient_name) {
											$colspan++;
										}
										if ($show_status_col) {
											$colspan++;
										}
									?>
										<tr class="parent" data-p="<?php echo $parent_id ?>">
											<td><i></i></td>
											<td colspan="3">
												<?php echo $row->requesting_physician ?>
											</td>
											<td colspan="<?php echo $colspan ?>">
												<?php echo $row->doc_npi ?>
											</td>
											<td>
												<span><?php echo '<span>' . $count_child . '</span> / <span class="blue">' . $count_bill . '</span> / <span class="lgreen">' . $count_paid . '</span>' ?></span>
											</td>
											<td></td>
											<?php if (!$hide_columnsend) { ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>
													<td>
														<?php
														if (tr_current_role_in(array('lab', 'administrator', 'administrator_p')) || $collected == 0) {
															echo '<span>' . number_format($collected, 2, '.', '') . '</span>';
														} else {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
														?>
													</td>
												<?php } ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>

													<td>
														<?php if ($hide_commission) {
															if ($commission > 0) {
																echo '<span class="dashicons dashicons-yes"></span>';
															}
														} else {
															echo '<span>' . number_format($commission, 2) . '</span>';
														}
														?>
													</td>
												<?php } ?>
											<?php } ?>
										</tr>
										<tr class="child p_<?php echo $parent_id ?>">
											<td colspan="<?php echo $column_count ?>" class="nopadding">
												<table style="width: 100%;" class="report_tbl">
													<?php echo $html ?>
												</table>
											</td>
										</tr>
								<?php
										$parent_id++;
										$collected = 0;
										$commission = 0;
										$count_child = 0;
										$count_paid = 0;
										$count_bill = 0;
									}
								}

								?>
							</tbody>
						</table>
					</div>
					<div>Total: <?php echo $count_rows ?></div>

				<?php } else if ($by == 'rep') { ?>
					<div class="wraptbl">
						<table class="report_tbl" data-count="<?php echo $count_rows ?>">
							<?php if (isset($_REQUEST['submit'])) : ?>
								<thead class="sticky">
									<tr>
										<th style="width:20px"></th>
										<th style="width: 60px">Rep</th>
										<th style="width: 10%">Clinic</th>
										<th style="width: 10%">Doctor</th>
										<th style="width:11%">Collector</th>
										<th style="width:10%">Insurance</th>
										<th style="width:9%">Accession</th>
										<?php if ($show_status_col) { ?>
											<th style="width:8%">Status</th>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<th style="width:8%">Patient</th>
										<?php } ?>
										<th style="width:94px">Spec/B/R</th>
										<th style="width:80px">Doc</th>
										<?php if (!$hide_columnsend) { ?>
											<th style="width:80px">Billed Date</th>
											<?php if (!$hide_amountcols) { ?>
												<th style="width:80px">Claim Paid Amount</th>
											<?php } ?>
											<th style="width:80px">Claim Paid</th>
											<?php if (!$hide_amountcols) { ?>
												<th>Commission</th>
											<?php } ?>
										<?php } ?>
									</tr>
								</thead>
							<?php endif; ?>
							<tbody>
								<?php
								if (count($rows) > 0) {
									$has_result = true;
								}

								$parents = array();
								$html = '';
								$current_code = null;
								$collected = 0;
								$commission = 0;
								$count_child = 0;
								$count_paid = 0;
								$count_bill = 0;
								$parent_id = 1;

								$column_count = 13; //changed
								if ($show_patient_name) {
									$column_count++;
								}
								if ($show_status_col) {
									$column_count++;
								}
								if ($hide_columnsend) {
									$column_count -= 4;
								} else if ($hide_amountcols) {
									$column_count -= 2;
								}
								foreach ($rows as $i => $row) {
									if (!in_array($row->rep, $parents)) {
										$parents[] = $row->rep;
										ob_start();
									}

									if (empty($row->collected)) $row->collected = 0;


									$row->commission = trpdd_cal_commission($row->collected, $row->rep_comission, $row->ins_mix, $row->btype);

								?>
									<tr class="childp">
										<td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:60px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->requesting_practice ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->requesting_physician ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->collector ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->pinsname ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:9%"' ?>><?php echo $row->assen ?></td>
										<?php if ($show_status_col) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:8%"' ?>><?php echo $batch_statuses[$row->batch_status] ?></td>
										<?php } ?> <?php if ($show_patient_name) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:8%"' ?>><?php echo $row->patient_name ?></td>
										<?php } ?>
										<td <?php if ($count_child == 0) echo 'style="width:94px"' ?>><span><?php echo 1 ?></span>
										</td>
										<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php echo date('Y-m-d', strtotime($row->doc)) ?></td>
										<?php if (!$hide_columnsend) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php echo $row->billed_date ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>>
													<?php
													if (tr_current_role_in(array('lab_rep', 'lab_rep_w2', 'distributor')) && $row->collected > 0) {
														if ($row->collected >= $lab_tox_cost) {
															echo '<span><span class="checkmark"></span></span>';
														} else {
															echo '<span><span class="checkmark_orange"></span></span>';
														}
													} else {
														echo number_format($row->collected, 2, '.', '');
													}
													?>
												</td>
											<?php } ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php if (intval($row->collected) != 0) {
																												echo $row->collected_date;
																											} ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td>
													<?php if ($hide_commission) {
														if ($row->commission > 0) {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
													} else {
														echo '<span>' . number_format($row->commission, 2, '.', '') . '</span>';
													}
													?>
												</td>
											<?php } ?>
										<?php } ?>
									</tr>

									<?php
									$collected += $row->collected;
									$commission += $row->commission;
									$count_child++;
									if ($by_paid_date) {
										$col_time = strtotime($row->collected_date);
										if ($col_time >= $from_time && $col_time <= $to_time) {
											$count_paid += ($row->collected > 0) ? 1 : 0;
										}
									} else {
										$count_paid += ($row->collected > 0) ? 1 : 0;
									}
									$count_bill += !empty($row->billed_date) ? 1 : 0;
									$has_result = true;
									$next_row = $rows[$i + 1];


									if (($next_row && !in_array($next_row->rep, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
										$html = ob_get_clean();
										$row->rep = trim($row->rep);
										if (empty($row->rep)) {
											$row->rep = 'Unknown';
										}
										$colspan = 5;
										if ($show_patient_name) {
											$colspan++;
										}
										if ($show_status_col) {
											$colspan++;
										}
									?>
										<tr class="parent" data-p="<?php echo $parent_id ?>">
											<td><i></i></td>
											<td colspan="<?php echo $colspan ?>"><?php echo $row->rep ?></td>
											<td>
												<span><?php echo '<span>' . $count_child . '</span> / <span class="blue">' . $count_bill . '</span> / <span class="lgreen">' . $count_paid . '</span>' ?></span>
											</td>
											<td></td>
											<?php if (!$hide_columnsend) { ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>
													<td>
														<?php
														if (tr_current_role_in(array('lab', 'administrator', 'administrator_p')) || $collected == 0) {
															echo '<span>' . number_format($collected, 2, '.', '') . '</span>';
														} else {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
														?>
													</td>
												<?php } ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>

													<td>
														<?php if ($hide_commission) {
															if ($commission > 0) {
																echo '<span class="dashicons dashicons-yes"></span>';
															}
														} else {
															echo '<span>' . number_format($commission, 2, '.', '') . '</span>';
														}
														?>
													</td>
												<?php } ?>
											<?php } ?>
										</tr>
										<tr class="child p_<?php echo $parent_id ?>">
											<td colspan="<?php echo $column_count ?>" class="nopadding">
												<table style="width: 100%;" class="report_tbl">
													<?php echo $html ?>
												</table>
											</td>
										</tr>
								<?php
										//echo $html;
										$parent_id++;
										$collected = 0;
										$commission = 0;
										$count_child = 0;
										$count_paid = 0;
										$count_bill = 0;
									}
								}

								?>
							</tbody>
						</table>
					</div>
					<div>Total: <?php echo $count_rows ?></div>

				<?php } else if ($by == 'distributor') { ?>
					<div class="wraptbl">
						<table class="report_tbl" data-count="<?php echo $count_rows ?>">
							<?php if (isset($_REQUEST['submit'])) : ?>
								<thead class="sticky">
									<tr>
										<th style="width:20px"></th>
										<th style="width: 72px">Distributor</th>
										<th style="width: 10%">Clinic</th>
										<th style="width: 10%">Doctor</th>
										<th style="width:11%">Collector</th>
										<th style="width:12%">Insurance</th>
										<th style="width:10%">Accession</th>
										<?php if ($show_status_col) { ?>
											<th style="width:8%">Status</th>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<th style="width:8%">Patient</th>
										<?php } ?>
										<th style="width:94px">Spec/B/R</th>
										<?php if (!$hide_columnsend) { ?>
											<th style="width:80px">Billed Date</th>
											<?php if (!$hide_amountcols) { ?>
												<th style="width:70px">Claim Paid Amount</th>
											<?php } ?>
											<th style="width:80px">Claim Paid</th>
											<?php if (!$hide_amountcols) { ?>
												<th>Commission</th>
											<?php } ?>
										<?php } ?>
									</tr>
								</thead>
							<?php endif; ?>
							<tbody>
								<?php
								if (count($rows) > 0) {
									$has_result = true;
								}

								$parents = array();
								$html = '';
								$current_code = null;
								$collected = 0;
								$commission = 0;
								$count_child = 0;
								$count_paid = 0;
								$count_bill = 0;
								$parent_id = 1;
								$column_count = 12; //changed
								if ($show_patient_name) {
									$column_count++;
								}
								if ($show_status_col) {
									$column_count++;
								}
								if ($hide_columnsend) {
									$column_count -= 4;
								} else if ($hide_amountcols) {
									$column_count -= 2;
								}
								foreach ($rows as $i => $row) {
									if (!in_array($row->dis_name, $parents)) {
										$parents[] = $row->dis_name;
										ob_start();
									}

									if (empty($row->collected)) $row->collected = 0;

									$row->commission = trpdd_cal_commission($row->collected, $row->dis_comission, $row->ins_mix);


								?>
									<tr class="childp">
										<td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:72px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->requesting_practice ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->requesting_physician ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->collector ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:12%"' ?>><?php echo $row->pinsname ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->assen ?></td>
										<?php if ($show_status_col) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:8%"' ?>><?php echo $batch_statuses[$row->batch_status] ?></td>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:8%"' ?>><?php echo $row->patient_name ?></td>
										<?php } ?>
										<td <?php if ($count_child == 0) echo 'style="width:94px"' ?>><span><?php echo 1 ?></span>
										</td>
										<?php if (!$hide_columnsend) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php echo $row->billed_date ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td <?php if ($count_child == 0) echo 'style="width:70px"' ?>>
													<?php
													if (tr_current_role_in(array('lab_rep', 'lab_rep_w2', 'distributor')) && $row->collected > 0) {
														if ($row->collected >= $lab_tox_cost) {
															echo '<span><span class="checkmark"></span></span>';
														} else {
															echo '<span><span class="checkmark_orange"></span></span>';
														}
													} else {
														echo number_format($row->collected, 2, '.', '');
													}
													?>
												</td>
											<?php } ?>
											<td <?php if ($count_child == 0) echo 'style="width:80px"' ?>><?php if (intval($row->collected) != 0) {
																												echo $row->collected_date;
																											} ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td>
													<?php if ($hide_commission) {
														if ($row->commission > 0) {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
													} else {
														echo '<span>' . number_format($row->commission, 2, '.', '') . '</span>';
													}
													?>
												</td>
											<?php } ?>
										<?php } ?>
									</tr>

									<?php
									$collected += $row->collected;
									$commission += $row->commission;
									$count_child++;
									if ($by_paid_date) {
										$col_time = strtotime($row->collected_date);
										if ($col_time >= $from_time && $col_time <= $to_time) {
											$count_paid += ($row->collected > 0) ? 1 : 0;
										}
									} else {
										$count_paid += ($row->collected > 0) ? 1 : 0;
									}
									$count_bill += !empty($row->billed_date) ? 1 : 0;
									$has_result = true;
									$next_row = $rows[$i + 1];


									if (($next_row && !in_array($next_row->dis_name, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
										$html = ob_get_clean();
										$row->dis_name = trim($row->dis_name);
										if (empty($row->dis_name)) {
											$row->dis_name = 'Unknown';
										}
										$colspan = 6;
										if ($show_patient_name) {
											$colspan++;
										}
										if ($show_status_col) {
											$colspan++;
										}
									?>
										<tr class="parent" data-p="<?php echo $parent_id ?>">
											<td><i></i></td>
											<td colspan="<?php echo $colspan ?>"><?php echo $row->dis_name ?></td>
											<td>
												<span><?php echo '<span>' . $count_child . '</span> / <span class="blue">' . $count_bill . '</span> / <span class="lgreen">' . $count_paid . '</span>' ?></span>
											</td>

											<?php if (!$hide_columnsend) { ?>
												<td></td>
												<td>
													<?php
													if (tr_current_role_in(array('lab', 'administrator', 'administrator_p')) || $collected == 0) {
														echo '<span>' . number_format($collected, 2) . '</span>';
													} else {
														echo '<span class="dashicons dashicons-yes"></span>';
													}
													?>
												</td>
												<?php if (!$hide_amountcols) { ?>
													<td></td>
													<td>
														<?php if ($hide_commission) {
															if ($commission > 0) {
																echo '<span class="dashicons dashicons-yes"></span>';
															}
														} else {
															echo '<span>' . number_format($commission, 2) . '</span>';
														}
														?>
													</td>
												<?php } ?>
											<?php } ?>
										</tr>
										<tr class="child p_<?php echo $parent_id ?>">
											<td colspan="<?php echo $column_count ?>" class="nopadding">
												<table style="width: 100%;" class="report_tbl">
													<?php echo $html ?>
												</table>
											</td>
										</tr>
								<?php
										//echo $html;
										$parent_id++;
										$collected = 0;
										$commission = 0;
										$count_child = 0;
										$count_paid = 0;
										$count_bill = 0;
									}
								}

								?>
							</tbody>
						</table>
					</div>
					<div>Total: <?php echo $count_rows ?></div>

				<?php } else {
				?>
					<div class="wraptbl">
						<table class="report_tbl" data-count="<?php echo $count_rows ?>">
							<?php if (isset($_REQUEST['submit'])) : ?>
								<thead class="sticky">
									<tr>
										<th style="width:20px"></th>
										<th style="width:10%">Insurance</th>
										<th style="width:10%">Accession</th>
										<?php if ($show_status_col) { ?>
											<th style="width:15%">Status</th>
										<?php } ?>
										<?php if ($show_patient_name) { ?>
											<th style="width:15%">Patient</th>
										<?php } ?>
										<th style="width:100px">Spec/B/R</th>
										<?php if (!$hide_columnsend) { ?>
											<th style="width:150px">Billed Date</th>
											<?php if (!$hide_amountcols) { ?>
												<th style="width:150px">Claim Paid Amount</th>
											<?php } ?>
											<th style="width:150px;">Claim Paid</th>
											<?php if (!$hide_amountcols) { ?>
												<th>Commission</th>
											<?php } ?>
										<?php } ?>
									</tr>
								</thead>
							<?php endif; ?>
							<tbody>
								<?php
								$parents = array();
								$html = '';
								$current_code = null;
								$collected = 0;
								$commission = 0;
								$count_child = 0;
								$count_paid = 0;
								$count_bill = 0;
								$parent_id = 1;

								$column_count = 8;
								if ($show_patient_name) {
									$column_count++;
								}
								if ($show_status_col) {
									$column_count++;
								}
								if ($hide_columnsend) {
									$column_count -= 4;
								} else if ($hide_amountcols) {
									$column_count -= 2;
								}
								// var_dump($rows);exit;
								if (!is_array($rows)) $rows = array();
								foreach ($rows as $i => $row) {
									if (!in_array($row->pinsname, $parents)) {
										$parents[] = $row->pinsname;
										ob_start();
									}
									if (empty($row->collected)) $row->collected = 0;


									$row->commission = trpdd_cal_commission($row->collected, $row->rep_comission, $row->ins_mix, $row->btype);
								?>
									<tr class="childp">
										<td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->pinsname ?></td>
										<td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->assen ?></td>
										<?php if ($show_status_col) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:15%"' ?>><?php echo $batch_statuses[$row->batch_status] ?></td>
											<?php } ?><?php if ($show_patient_name) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:15%"' ?>><?php echo $row->patient_name ?></td>
										<?php } ?>
										<td <?php if ($count_child == 0) echo 'style="width:100px"' ?>><span><?php echo 1 ?></span>
										</td>
										<?php if (!$hide_columnsend) { ?>
											<td <?php if ($count_child == 0) echo 'style="width:150px"' ?>><?php echo $row->billed_date ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td <?php if ($count_child == 0) echo 'style="width:150px"' ?>>
													<?php
													if (tr_current_role_in(array('lab_rep', 'lab_rep_w2', 'distributor')) && $row->collected > 0) {
														if ($row->collected >= $lab_tox_cost) {
															echo '<span><span class="checkmark"></span></span>';
														} else {
															echo '<span><span class="checkmark_orange"></span></span>';
														}
													} else {
														echo number_format($row->collected, 2);
													}
													?>
												</td>
											<?php } ?>
											<td <?php if ($count_child == 0) echo 'style="width:150px"' ?>><?php if (intval($row->collected) != 0) {
																												echo $row->collected_date;
																											} ?></td>
											<?php if (!$hide_amountcols) { ?>
												<td>
													<?php if ($hide_commission) {
														if ($row->commission > 0) {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
													} else {
														echo '<span>' . number_format($row->commission, 2) . '</span>';
													}
													?>
												</td>
											<?php } ?>
										<?php } ?>
									</tr>

									<?php
									$collected += $row->collected;
									$commission += $row->commission;
									$count_child++;
									if ($by_paid_date) {
										$col_time = strtotime($row->collected_date);
										if ($col_time >= $from_time && $col_time <= $to_time) {
											$count_paid += ($row->collected > 0) ? 1 : 0;
										}
									} else {
										$count_paid += ($row->collected > 0) ? 1 : 0;
									}
									$count_bill += !empty($row->billed_date) ? 1 : 0;
									$has_result = true;
									$next_row = isset($rows[$i + 1]) ? $rows[$i + 1] : '';


									if (($next_row && !in_array($next_row->pinsname, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
										$html = ob_get_clean();
										if (empty($row->pinsname)) {
											$row->pinsname = 'Unknown';
										}
										$colspan = 2;
										if ($show_patient_name) {
											$colspan++;
										}
										if ($show_status_col) {
											$colspan++;
										}
									?>
										<tr class="parent" data-p="<?php echo $parent_id ?>">
											<td><i></i></td>
											<td colspan="<?php echo $colspan ?>"><?php echo $row->pinsname ?></td>
											<td>
												<span><?php echo '<span>' . $count_child . '</span> / <span class="blue">' . $count_bill . '</span> / <span class="lgreen">' . $count_paid . '</span>' ?></span>
											</td>
											<?php if (!$hide_columnsend) { ?>

												<td></td>
												<?php if (!$hide_amountcols) { ?>
													<td>
														<?php
														if (tr_current_role_in(array('lab', 'administrator', 'administrator_p')) || $collected == 0) {
															echo '<span>' . number_format($collected, 2) . '</span>';
														} else {
															echo '<span class="dashicons dashicons-yes"></span>';
														}
														?>
													</td>
												<?php } ?>
												<td></td>
												<?php if (!$hide_amountcols) { ?>

													<td>
														<?php if ($hide_commission) {
															if ($commission > 0) {
																echo '<span class="dashicons dashicons-yes"></span>';
															}
														} else {
															echo '<span>' . number_format($commission, 2) . '</span>';
														}
														?>
													</td>
												<?php } ?>
											<?php } ?>
										</tr>
										<tr class="child p_<?php echo $parent_id ?>">
											<td colspan="<?php echo $column_count ?>" class="nopadding">
												<table style="width: 100%;" class="report_tbl">
													<?php echo $html ?>
												</table>
											</td>
										</tr>
								<?php
										$parent_id++;
										$collected = 0;
										$commission = 0;
										$count_child = 0;
										$count_paid = 0;
										$count_bill = 0;
									}
								}


								?>

							</tbody>
						</table>
					</div>
					<div>Total: <?php echo $count_rows ?></div>
				<?php }

				?>
				<?php if (isset($_REQUEST['submit']) && !$has_result) : ?>
					<div class="updated">
						<p>No Result Found</p>
					</div>
				<?php endif; ?>
		<?php
				echo '</div>';
			}
			echo '</div>';
		}
		?>


	</div>
	<style>
		.report_tbl .dashicons-yes {
			color: #00a700 !important;
			font-size: 24px;
			padding: 4px 7px !important;
		}

		.report_tbl .dashicons-yes.orange {
			color: orange !important;
		}

		span.blue {
			color: #5ca6ff;
		}

		span.lgreen {
			color: lightgreen;
		}

		.report_tbl tr.parent td>span,
		.tls>span {
			white-space: nowrap
		}

		.wrap_tabs .tab {
			display: none;
		}
	</style>
	<script>
		jQuery(function($) {
			$('tr.parent').click(function() {
				$('tr.child').hide();
				if ($(this).data('showed') != '1') {
					$('tr.p_' + $(this).data('p')).show();
					$('tr.parent').data('showed', '0');
					$(this).data('showed', '1');
					$(this).addClass('active');
					$('body,html').scrollTop($(this).offset().top - 90);
				} else {
					$('tr.p_' + $(this).data('p')).hide();
					$(this).data('showed', '0');
					$(this).removeClass('active');
				}

			});
			$("#from").datepicker({
				changeMonth: true,
				changeYear: true,
				numberOfMonths: 1,
				dateFormat: 'yy-mm-dd',
				onClose: function(selectedDate) {
					$("#to").datepicker("option", "minDate", selectedDate);
				}
			});
			$("#to").datepicker({
				changeMonth: true,
				changeYear: true,
				numberOfMonths: 1,
				dateFormat: 'yy-mm-dd',
				onClose: function(selectedDate) {
					$("#from").datepicker("option", "maxDate", selectedDate);
				}
			});
			$('input.report_by').change(function() {
				if ($(this).is(':checked') == false) return;
				$('.search_by div').hide();
				$('#search_by_' + $(this).val()).show();
			}).change();
			$('#btype_tabs a').click(function() {
				$('#btype_tabs a').removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');
				var $d = $(this).attr('href');
				$('.wrap_tabs .tab').hide();
				$($d).show();
				return false;
			})
			$('#btype_tabs a:first-child').click();
		})
	</script>
<?php
}
