<?php
global $wpdb, $current_role, $show_menus_options, $ci_active_menu, $current_lab_id;

$settings 	= tr_billing_get_settings();
$user_id    = get_current_user_id();
$fromdate   = isset($_REQUEST['fromdate']) ? $_REQUEST['fromdate'] : '';
$todate     = isset($_REQUEST['todate']) ? $_REQUEST['todate'] : '';
$paytype    = isset($_REQUEST['paytype']) ? $_REQUEST['paytype'] : '';
$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;

if (isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'], 'export') !== false) {
	$act = 'export';
}

$orderby = ' order by re.created_date desc, ba.mid desc';
$where_cli = "";


if ($current_role == 'BillbyClinic') {
	$clinics_ids = get_user_meta($user_id, '_bill_cli_IDs', true);
	if (!is_array($clinics_ids)) {
		$clinics_ids = array(0);
	}
	$clinics_ids = implode(',', $clinics_ids);
	$where_cli .= " and cli.cID in ({$clinics_ids}) ";
} else if ($current_role == 'office_manager') {

	$clinics_ids  = $wpdb->get_col("select cID from ci_clinic where cli_om_user_ID={$user_id} ");
	$clinics_ids  = implode(',', $clinics_ids);
	$where_cli .= " and cli.cID in ({$clinics_ids}) ";
} else if ($current_role == 'Billing' && !$settings['disable_billbyclinic']) {
	$excludecids = get_option('ci_bill_cli_IDs', array());

	if (is_array($excludecids) && count($excludecids) > 0) {
		$excludecids = implode(',', $excludecids);
		$where_cli .= " and cli.cID not in ({$excludecids}) ";
	}
}


if ($cli_ID > 0) {
	$where_cli .= " and cli.cID = {$cli_ID} ";
}

$where = " and ba.doc >= '{$fromdate}' and ba.doc <= '{$todate} 23:59:59' ";

$where .= " and re.estatus in ('C','F') ";


if (defined('TRPDD_UPSAVED') && TRPDD_UPSAVED) {
	$where .= " and ba.is_saved = 0 ";
}



if (!empty($_REQUEST['lab_reflab'])) {
	list($lab_id, $ref_lab_id) = explode('_', $_REQUEST['lab_reflab']);
	if ($lab_id > 0) {
		$where .= " and ba.lab_ID = {$lab_id} ";
	}

	if ($ref_lab_id > 0) {
		$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
	} else {
		$where .= " and ba.ref_lab_ID = 0 ";
	}
}


if ($settings['exclude_ref_lab']) {
	$where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id = '{$current_lab_id}' ) ";
} else if (isset($settings['include_ref_labs']) && !empty($settings['include_ref_labs'])) {
	$where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id in ( {$settings['include_ref_labs']} ) ) ";
}


$user_ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
if ($user_ref_lab_id > 0) {
	$where .= " and ba.ref_lab_id = {$user_ref_lab_id}  ";
}

if (!empty($paytype)) {
	if ($paytype !== 'no') {
		$where_cli .= " and cli_pay_type = '{$paytype}'  ";
	} else {
		$where_cli .= " and cli_pay_type = 'insu' ";
	}
}



if ($act == 'load_detail' || $act == 'export') {
	$cid  = $_REQUEST['cid'];

	if ($cid === 'no') {
		$where .= " and ba.cli_ID in (select cID from ci_clinic cli where 1 {$where_cli} ) ";
	} else if ($act == 'export') {
		$where .= " and ba.cli_ID in (select cID from ci_clinic cli where 1 {$where_cli} ) ";
	} else {
		$where .= " and ba.cli_ID = {$cid} ";
	}

	if (!empty($paytype)) {
		if ($paytype == 'no' || $cid == 'no') {
			$where .= " and ba.insurance = 'No Insurance - Cash' ";
		} else if ($paytype == 'insu') {
			$where .= " and ba.insurance != 'No Insurance - Cash' ";
		}
	}


	$sql_child = array();

	$has_more_type = false;
	$rpp_gi = false;
	if (function_exists('tr_pdd_get_batch_types')) {
		$where_join = " bb.assen = ba.assen ";
		if (tr_billing_get_settings('enable_claim_no')) {
			$where_join = " (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen) ";
		}

		foreach (trbilling_req_types() as $t) {

			if ($t == 'study') continue;

			if (
				$show_menus_options['all']['form_' . $t]
				|| $ci_active_menu['form_' . $t]
				|| in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
			) {
				$batch_tbl = tr_pdd_get_table_by_btype($t);
				$report_tbl = tr_pdd_get_table_report_by_btype($t);
				$has_more_type = true;

				$sql_child[] = "(select 
                        ba.mid,
                        ba.assen,
                        ba.patient_name,
                        ba.requesting_practice,
                        ba.requesting_physician,
                        ba.claim_id,
                        ba.result_pdf,
                        ba.insurance,
                        ba.doc,
                        ba.priority_hours,
                        
                        re.id,
                        IF(created_date is not null,created_date, DATE_FORMAT(ba.batch_created,'%Y-%m-%d')) AS result_date ,
                        'C' as estatus,
                        
                        cli.cli_pay_type,
                        
                        doc.doc_ID,
                        doc.doc_npi,
                        doc.doc_pecos,
                        pa.pa_address,
                        pa.pa_city,
                        pa.pa_state,
                        pa.pa_zip,
                        
                        bb.claim_no,
                        bb.claim_date,
                        bb.allowed_amount,
                        bb.bill_status,
                        bb.received_amount,
                        bb.billed_amount,
                        
                        '{$t}' as btype
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    left join ci_batch_billings bb ON {$where_join}
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
                    where 1=1 {$where} 
                    group by ba.mid 
                    order by ba.cli_ID asc, ba.mid desc)";
			}
		}
	}




	$sql_child = implode("\n UNION ALL \n", $sql_child);

	$sql = "select * from ( {$sql_child} ) as rs  ";

	if ($act == 'export') {
		$sql .= " order by rs.result_date asc, rs.requesting_practice asc, rs.cli_pay_type asc, rs.insurance asc";
	}

	if (isset($_GET['debug'])) {
		var_dump($sql);
		echo '<br><br>';
		var_dump($wpdb->last_error);
		echo '<br>';
	}

	$rows = $wpdb->get_results($sql);

	if ($act == 'export') {
		$paytypename = 'All';
		if ($paytype == 'cash') {
			$paytypename = 'CASH';
		} else if ($paytype == 'insu') {
			$paytypename = 'INS';
		} else if ($paytype == 'no') {
			$paytypename = 'NO-INS';
		}
		$fdname = date('dmY', strtotime($fromdate));
		$tdname = date('dmY', strtotime($todate));
		$filename = $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';

		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
		$headers = array(
			'Accession',
			'Clinic',
			'Patient Name',
			'Address',
			'City',
			'State',
			'Zip',
			'Claim ID',
			'Billed Amount',
			'Received Amount',
			'Balance',
			'Date Billed',
			'Date of service',
			'Pay Type',
			'Priority'
		);
		fputcsv($output, $headers);
		foreach ($rows as $r) {
			$rowpaytype = 'Insurance';
			if (stripos($r->insurance, 'No Insurance - Cash') !== false) {
				$rowpaytype = 'No-Insurance';
			} else if ($r->cli_pay_type == 'cash') {
				$rowpaytype = 'Cash';
			}
			$priority_name = ($r->priority_hours > 0) ? $r->priority_hours . 'hr' : '';
			if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
				$arrpriorities = $r->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
				$priority_name = isset($arrpriorities[$r->priority_hours]) ? $arrpriorities[$r->priority_hours] : '';
			}
			$row = array(
				$r->assen,
				$r->requesting_practice,
				$r->patient_name,
				$r->pa_address,
				$r->pa_city,
				$r->pa_state,
				$r->pa_zip,
				$r->claim_id,
				$r->billed_amount,
				$r->received_amount,
				$r->balance,
				tr_pdd_display_date($r->claim_date),
				tr_pdd_display_date($r->result_date),
				$rowpaytype,
				$priority_name
			);

			fputcsv($output, $row);
		}
		@fclose($output);
		if (function_exists('trpdd_log_user')) {
			$data_log = [
				'type' => 'Billing Reports',
				'file_name' => $filename,
				'total_records' => count($rows),
				'from_date' => $fromdate,
				'to_date' => $todate,
			];
			trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
		}
		exit;
	}

?>
	<table class="report_tbl">
		<thead>
			<tr class="parent_c">
				<th style="width:120px">Accession</th>
				<th>Patient Name</th>
				<th>Adress</th>
				<th>City</th>
				<th>State</th>
				<th>Zip</th>
				<th style="width: 9%;">Claim ID</th>
				<th style="width: 9%;">Claim Date</th>
				<th style="width: 10%;">Billed Amount</th>
				<th style="width: 11%;">Received Amount</th>
				<th style="width: 9%;">Balance</th>
				<th>Date Billed</th>
				<th>Priority</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
			?>
				<tr class="childp">
					<td><?php echo $row->assen ?></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->pa_address ?></td>
					<td><?php echo $row->pa_city ?></td>
					<td><?php echo $row->pa_state ?></td>
					<td><?php echo $row->pa_zip ?></td>
					<td><?php echo $row->claim_id ?></td>
					<td><?php echo $row->claim_date ?></td>
					<td><?php if ($row->billed_amount > 0) echo number_format($row->billed_amount, 2) ?></td>
					<td><?php if ($row->received_amount > 0) echo number_format($row->received_amount, 2) ?></td>
					<td><?php if ($row->balance != 0) echo number_format($row->balance, 2) ?></td>
					<td><?php echo tr_pdd_display_date($row->claim_date) ?></td>
					<td><?php
						if ($row->priority_hours > 0) {
							if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
								$arrpriorities = $row->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
								$priority_name = isset($arrpriorities[$row->priority_hours]) ? $arrpriorities[$row->priority_hours] : '';

								echo $priority_name;
							} else {
								echo $row->priority_hours . 'hr(s)';
							}
						}
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
<?php
	exit;
}

$rows = array();
if (!empty($fromdate) && !empty($todate)) {


	$sql = "select cli.cli_clinic as name,
                cli.cli_pay_type,
                cli.cID
                from ci_clinic cli
                where cli.cli_status = 1 {$where_cli}
                order by cli.cli_clinic asc
                ";

	$rows = $wpdb->get_results($sql, ARRAY_A);

	if (isset($_REQUEST['debug'])) {
		echo $sql;
		echo '<br><br>';
	}
}


$clinics = array();
if ($settings['enable_filter_clinic']) {
	$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
}
?>
<div class="wrap">
	<h2>Reports</h2>
	<?php Tr_Session_Class::show() ?>


	<div class="tablenav top">
		<form method="get" class="left alignleft">
			<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
			<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />

			<label>From Date</label>
			<input type="search" class="hasdate" name="fromdate" value="<?php echo isset($_GET['fromdate']) ? $_GET['fromdate'] : '' ?>" placeholder="From Date" required />
			<label>To Date</label>
			<input type="search" class="hasdate" name="todate" value="<?php echo isset($_GET['todate']) ? $_GET['todate'] : '' ?>" placeholder="To Date" required />
			<select name="paytype" class="fixsline">
				<?php
				echo '<option value="">All Pay Type</option>';
				foreach ($settings['paytypes'] + array('no' => 'No-Insurance') as $k => $txt) {
					if ($k == 'inscash') continue;
					$selected = $k == $paytype ? 'selected' : '';
					echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
				}

				?>
			</select>
			<?php
			if ($settings['enable_filter_clinic'] && isset($clinics)) {
			?>
				<select name="cli_ID" class="select2" data-placeholder="Select Clinic">
					<option value="">All Clinic</option>
					<?php
					foreach ($clinics as $cli) {
						$selected = $cli->cID == $cli_ID ? 'selected' : '';
						echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
					}
					?>
				</select>
			<?php
			}
			?>
			<input type="submit" class="button bsty6" value="Search" />
			<input type="submit" class="button" name="trajax" value="Export" />
		</form>
		<?php if (isset($pagination))
			echo $pagination->pagination() ?>
	</div>

	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
			<thead class="sticky">
				<tr>
					<th style="width:20px"></th>
					<th style="width: 50%">Payor</th>
					<th>Samples</th>
					<th>Normal Priority</th>
					<th>Med Priority</th>
					<th>High Priority</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$count_rows = 0;

				foreach ($rows as $row) {
					$where_row = $where;
					$where_row .= " and ba.cli_ID = {$row['cID']} ";

					if (!empty($paytype)) {
						if ($row['cID'] === 'no' || $paytype == 'no') {
							$where_row .= " and ba.insurance = 'No Insurance - Cash' ";
						} else if ($row['cli_pay_type'] == 'insu') {
							$where_row .= " and ba.insurance != 'No Insurance - Cash' ";
						}
					}

					$sqlc = array();
					foreach (trbilling_req_types() as $t) {

						if ($t == 'study') continue;

						if (
							$show_menus_options['all']['form_' . $t]
							|| $ci_active_menu['form_' . $t]
							|| in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
						) {
							$batch_tbl  = tr_pdd_get_table_by_btype($t);

							$report_tbl = tr_pdd_get_table_report_by_btype($t);

							$sqlc[]     = "select count(*) as bcount,
						            SUM(IF(rc.re_priority='high', 1, 0)) as count_high,
						            SUM(IF(rc.re_priority='med', 1, 0)) as count_med
						            from {$batch_tbl} ba
                                left join {$report_tbl} re ON  ba.assen = re.patient_id
                                  left join ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype='{$t}'
                                  left join ci_receiving rc On rc.re_ID = rb.re_ID
                                where 1 {$where_row}
                                ";
						}
					}
					$sqlc = implode(' UNION ALL ', $sqlc);
					$sql = "select sum(bcount) as bcount,
				            SUM(count_high) as count_high,
				            SUM(count_med) as count_med
				        from ({$sqlc}) as rs";
					if (isset($_GET['debug'])) {
						var_dump($sql);
						echo '<br><br>';
					}
					$counts = $wpdb->get_row($sql);
					$row['count'] = $counts->bcount;
					$row['count_high'] = $counts->count_high;
					$row['count_med'] = $counts->count_med;
					$row['count_low'] = $row['count'] - $row['count_high'] - $row['count_med'];

					if ($row['count'] == 0) {
						continue;
					}
				?>
					<tr class="parent" data-id="<?php echo $row['cID'] ?>">
						<td></td>
						<td><?php echo $row['name'] ?></td>
						<td><?php echo $row['count'] ?></td>
						<td><?php echo $row['count_low'] ?></td>
						<td><?php echo $row['count_high'] ?></td>
						<td><?php echo $row['count_med'] ?></td>
					</tr>
					<tr class="child p_<?php echo $row['cID'] ?>">
						<td colspan="6"></td>
					</tr>
				<?php
					$count_rows++;
				}
				if ($count_rows == 0) {
				?>
					<tr>
						<td colspan="6">Not found</td>
					</tr>
				<?php
				} else {
				}
				?>
			</tbody>
		</table>
	</div>
	<div class="tablenav bottom"><?php if (isset($pagination))
										echo $pagination->pagination() ?></div>
</div>



<script>
	jQuery(function($) {

		function trcpp_load_results(id) {
			var $obj = $('tr.child.p_' + id + ' > td');
			if ($obj.html() != '') {
				return;
			} else {
				$obj.html('<div class="loading center"></div>');
			}
			$.ajax({
				data: {
					trajax: 1,
					act: 'load_detail',
					cid: id
				},
				type: 'post',
				success: function($html) {
					$obj.html($html);
				}
			})
		}

		$('body').on('click', 'tr.parent', function(e) {
			if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
			$('tr.child').hide();
			if ($(this).data('showed') != '1') {
				var id = $(this).data('id');
				var $obj = $('tr.p_' + id);
				$obj.show();
				$('tr.parent').data('showed', '0');
				$(this).data('showed', '1');
				$(this).addClass('active');
				$('body,html').scrollTop($obj.offset().top - 80);
				trcpp_load_results(id);
			} else {
				$('tr.p_' + $(this).data('p')).hide();
				$(this).data('showed', '0');
				$(this).removeClass('active');
			}
			return false;
		});

	})
</script>