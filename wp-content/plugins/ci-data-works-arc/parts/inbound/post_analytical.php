<?php
global $wpdb, $current_role;

$user_id    = get_current_user_id();
$sub_tabs   = array();
$subtab     = '';

$from_date  = isset($_REQUEST['from']) ? $_REQUEST['from'] : '';
$to_date    = isset($_REQUEST['to']) ? $_REQUEST['to'] : date_i18n('Y-m-d');
$panel_id 	= isset($_REQUEST['panel']) ? $_REQUEST['panel'] : '';

$wherepanels = implode("','", tr_pdd_get_batch_types());
$panels = $wpdb->get_results("select tg_ID, tg_name 
				from ci_test_groups where tg_parent = 0 and tg_show_req =1
				and tg_type in ('{$wherepanels}') order by tg_type asc, tg_position asc");


if (empty($from_date)) {
	$from_date = date_i18n('Y-m-d', strtotime('-30days'));
}
if ($to_date < $from_date) {
	$to_date = date_i18n('Y-m-d');
}

if (empty($where)) {
	$where = '1';
}

if (!empty($from_date)) {
	$where .= " and lu.created >= '{$from_date}' ";
}

if (!empty($to_date)) {
	$where .= " and lu.created <= '{$to_date} 23:59:59' ";
}
if ($panel_id > 0) {
	$where .= " and find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested ) ";
}

if (CI_Base_Lab::has_user_labs()) {
	$lab_str = CI_Base_Lab::get_user_lab_ids_str();
	$where .= " and ba.lab_id in ( {$lab_str} ) ";
}

$where_actions = "and lu.user_action in ('edit_rs_cd','edit_approve_rs_cd','save_req_cd')";

$order      = isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
$order_by   = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'created';
$child_queries = array();
$total      = 0;

foreach (tr_pdd_get_batch_types() as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$total += $wpdb->get_var("select count(ba.mid) as c 
	                from {$batch_tbl} ba 
					 join ci_log_users lu ON ba.mid = lu.mid 
					                    and lu.btype = '{$btype}'
					                    {$where_actions}
					where  {$where}");

	$child_queries[] = "select ba.mid,
                        ba.assen,
                        ba.batch_status,
                        ba.batch_id,
                        ba.spec_received,
                        ba.requesting_practice,
                        ba.panel_tests_requested,
                        ba.billing_notes,
                        ba.issue_photo,
                        ba.author_ID,
                        lu.user_action,
                        lu.created,
                        lu.data,
                        u.user_email
                        from {$batch_tbl} ba
                        join ci_log_users lu ON ba.mid = lu.mid 
                                        and lu.btype = '{$btype}'
                                        {$where_actions}
                        join {$wpdb->users} u ON lu.user_id = u.ID
                        where {$where} 
                        
                        ";
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'Export') {

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=Post_Analytical_errors.csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	$f = fopen('php://output', 'w');
	$headers = array('Date', 'Accession', 'Clinic', 'Reason', 'Panels', 'Note', 'User');
	fputcsv($f, $headers);


	$child_queries = implode(" UNION ALL ", $child_queries);
	$sql = "select * from ({$child_queries}) ba
        order by {$order_by} {$order}
        ";
	$results = $wpdb->get_results($sql);
	foreach ($results as $row) {
		$panel = '';
		$cdata = json_decode($row->data, true);
		$changes = array();
		foreach ($cdata as $k => $vl) {
			$changes[] = trpdd_get_name_by_key($k) . ': ' . $vl;
		}

		if ($row->btype != 'tox') {
			$panel = array();
			$has_groups = false;
			foreach (explode(',', $row->panel_tests_requested) as $t) {
				if (stripos($t, 'group') === false) {
					if ($t > 0 && isset($all_tests[$t])) {
						if (!$has_groups) {
							$panel[] = $all_tests[$t]->te_name;
						}


						if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
							$panels_print[$t] = $all_tests[$t]->te_label_barcode;
						}
					}
				} else {
					$group_id = str_ireplace('group_', '', $t);

					if (isset($all_groups[$group_id])) {
						$has_groups = true;
						$panel[] = $all_groups[$group_id]->tg_name;

						if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
							$panels_print[$t] = $all_groups[$group_id]->tg_label_name;
						} else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
							foreach ($all_tests as $test) {
								if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
									$panels_print[$test->te_ID] = $test->te_label_barcode;
								}
							}
						}
					}
				}
			}
			if ($row->culture) {
				$panel[] = 'Culture';
			}
			$panel = implode(', ', $panel);
		} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
			$panel = $row->panel_tests_requested;
		} else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
			$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
		} else {
			$panel = $row->panel_tests_requested;
		}


		$rowdata = array(
			date('Y-m-d', strtotime($row->created)),
			$row->assen,
			$row->requesting_practice,
			'Modified ' . (stripos($row->user_action, '_rs') > 0 ? 'Result' : 'Req'),
			$panel,
			implode(', ', $changes),
			$row->user_email
		);
		fputcsv($f, $rowdata);
	}
	fclose($f);
	exit;
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($total);
$offset = $pagination->offset();


$child_queries = implode(" UNION ALL ", $child_queries);
$sql = "select * from ({$child_queries}) ba
        order by {$order_by} {$order}
        limit {$offset},{$row_per_page}";
$results = $wpdb->get_results($sql);
$dir = tr_get_issue_photo_dir();

if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<br><br>';
	var_dump($batch_statuses_arr);
}
?>

<div class="wrap">

	<div class="tablenav">
		<form method="get" class="left">
			<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
			<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
			<input type="hidden" name="subtab" value="<?php echo $_GET['subtab'] ?>" />
			<label>From</label>
			<input type="text" class="hasdate" name="from" value="<?php echo @$from_date ?>" required />
			<label>To</label>
			<input type="text" class="hasdate" name="to" value="<?php echo @$to_date ?>" required />
			<select name="panel">
				<?php
				echo '<option value="">All Panels</option>';
				foreach ($panels as $p) {
					$selected = (isset($_REQUEST['panel']) && $_REQUEST['panel'] == $p->tg_ID) ? 'selected' : '';
					echo '<option value="' . $p->tg_ID . '" ' . $selected . '>' . $p->tg_name . '</option>';
				}
				?>
			</select>

			<input type="submit" class="button-primary" value="Filter" />
			<input type="submit" class="button" name="trajax" value="Export" />
		</form>
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>

	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
				<tr>
					<?php tr_tbl_sort('created', 'Date', '', 'width:100px') ?>
					<?php tr_tbl_sort('assen', 'Accession', '', 'width:120px') ?>
					<?php tr_tbl_sort('requesting_practice', 'Clinic', '', 'width:150px') ?>
					<th style="width:100px">Reason</th>
					<th>Panels</th>
					<th>Note</th>
					<th>User</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($results as $i => $row) {
					$panel = '';
					$cdata = json_decode($row->data, true);
					$changes = array();
					foreach ($cdata as $k => $vl) {
						$changes[] = trpdd_get_name_by_key($k) . ': ' . $vl;
					}

					if ($row->btype != 'tox') {
						$panel = array();
						$has_groups = false;
						foreach (explode(',', $row->panel_tests_requested) as $t) {
							if (stripos($t, 'group') === false) {
								if ($t > 0 && isset($all_tests[$t])) {
									if (!$has_groups) {
										$panel[] = $all_tests[$t]->te_name;
									}


									if (isset($all_tests[$t]->te_label_barcode) && !empty($all_tests[$t]->te_label_barcode)) {
										$panels_print[$t] = $all_tests[$t]->te_label_barcode;
									}
								}
							} else {
								$group_id = str_ireplace('group_', '', $t);

								if (isset($all_groups[$group_id])) {
									$has_groups = true;
									$panel[] = $all_groups[$group_id]->tg_name;

									if (isset($all_groups[$group_id]->tg_label_name) && !empty($all_groups[$group_id]->tg_label_name)) {
										$panels_print[$t] = $all_groups[$group_id]->tg_label_name;
									} else if (isset($all_groups[$group_id]->tg_notestcheckbox) && $all_groups[$group_id]->tg_notestcheckbox) {
										foreach ($all_tests as $test) {
											if ($test->te_group_id == $group_id && !empty($test->te_label_barcode)) {
												$panels_print[$test->te_ID] = $test->te_label_barcode;
											}
										}
									}
								}
							}
						}
						if ($row->culture) {
							$panel[] = 'Culture';
						}
						$panel = implode(', ', $panel);
					} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
						$panel = $row->panel_tests_requested;
					} else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
						$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
					} else {
						$panel = $row->panel_tests_requested;
					}
				?>
					<tr>
						<td><?php echo date('Y-m-d', strtotime($row->created)) ?></td>
						<td><?php echo $row->assen ?></td>
						<td><?php echo $row->requesting_practice ?></td>
						<td><?php echo 'Modified ' . (stripos($row->user_action, '_rs') > 0 ? 'Result' : 'Req') ?></td>
						<td><?php echo $panel ?></td>
						<td><?php echo implode('<br>', $changes) ?></td>
						<td><?php echo $row->user_email; ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

</div>