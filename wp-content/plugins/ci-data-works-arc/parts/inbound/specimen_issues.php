<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();

$subtab = '';

$sub_tabs = array(
	'pre' => 'Pre-Analytical Errors',
	'post' => 'Post-Analytical Errors',
	'analytical_errors' => 'Analytical Errors'
);



$all_tests = array();
$all_groups = array();
$reflex_ids = array();


$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels");
foreach ($ts as $t) {
	$all_tests[$t->te_ID] = $t;
}
$ts = $wpdb->get_results("select * from ci_test_groups");
foreach ($ts as $t) {
	$all_groups[$t->tg_ID] = $t;
	foreach (explode(',', $t->tg_reflex_group_ID) as $rlid) {
		if ($rlid > 0) {
			$reflex_ids[$rlid] = 'GROUP_' . $rlid;
		}
	}
}



$subtab = isset($_REQUEST['subtab']) ? $_REQUEST['subtab'] : key($sub_tabs);

if (!isset($sub_tabs[$subtab])) {
	$subtab = key($sub_tabs);
}

if (count($sub_tabs) > 0 && !isset($_REQUEST['trajax'])) {
?>
	<h2 class="nav-tab-wrapper">
		<?php

		foreach ($sub_tabs as $k => $txt) {
			$class = $subtab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link(array(
				'subtab'      => $k,
				'act'      => '',
				'id'       => '',
				'batch_id' => '',
				'view'     => '',
				's'        => '',
				'search'   => '',
				'paged'    => '',
				'from'     => '',
				'to'       => '',
				'summary'  => '',
				'orderby'  => '',
				'order'    => '',
				'assen'    => '',
				'status'   => '',
				'city'     => '',
				'short'     => '',
				'cid'     => '',
			), false);
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
	</h2>
	<br>
	<?php
}

if ($subtab == 'post') {
	include(trpdd_get_file_path('inbound', 'post_analytical'));
	return;
} else if ($subtab == 'analytical_errors') {
	include(trpdd_get_file_path('inbound', 'analytical_errors'));
	return;
}





if (isset($_REQUEST['trajax'])) {
	if ($_REQUEST['trajax'] == 'add_form_issue') {
		$data = array();
		$data['edate'] = date_i18n('Y-m-d');
	?>
		<form method="post" id="add_form">
			<div class="frow">
				<label>Title</label>
				<input type="text" name="data[title]" value="<?php echo $data['title'] ?>" required />
			</div>
			<div class="frow">
				<label>Accession</label>
				<input type="text" name="data[assen]" value="<?php echo $data['assen'] ?>" required />
			</div>
			<div class="frow">
				<label>Date</label>
				<input type="text" class="hasdate" name="data[edate]" value="<?php echo $data['edate'] ?>" required />
			</div>
			<div class="frow">
				<label>Description of Error</label>
				<textarea name="data[desc]" style="height:50px" required><?php echo $data['desc'] ?></textarea>
			</div>

			<div class="frow center">
				<input type="submit" class="button-primary" value="Add" />
				<input type="button" class="button" onclick="tb_remove()" value="Close" />
				<input type="hidden" name="trajax" value="do_add_new_issue" />
				<input type="hidden" name="data[ID]" value="<?php echo $data['ID'] ?>" />
			</div>
		</form>
		<script>
			jQuery(function($) {
				$('.hasdate').each($.tr_setup_datef);
				$('form#add_form').submit(function() {
					var $form = $(this);
					$.ajax({
						data: $form.serialize(),
						type: 'post',
						dataType: 'json',
						success: function(json) {
							if (json.success) {
								location.reload();
							} else {
								alert('Error. Please try again!');
							}
						},
						error: function() {
							alert('Error. Please try again!');
						}
					});
					return false;
				})
			})
		</script>
<?php
		exit;
	} else if ($_REQUEST['trajax'] == 'do_add_new_issue') {
		$return = array('success' => 1);

		$data = $_POST['data'];
		$data['atype'] = 'pre';
		if ($data['ID'] > 0) {
			$wpdb->update('ci_analytical_errors', $data, array('ID' => $data['ID']));
		} else {
			$data['created'] = date_i18n('Y-m-d H:i:s');
			$data['created_by'] = get_current_user_id();

			$wpdb->insert('ci_analytical_errors', $data);
		}

		wp_send_json($return);
	}
}


$from_date 	= isset($_REQUEST['from']) ? $_REQUEST['from'] : '';
$to_date 	= isset($_REQUEST['to']) ? $_REQUEST['to'] : date_i18n('Y-m-d');
$search 	= isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
$panel_id 	= isset($_REQUEST['panel']) ? $_REQUEST['panel'] : '';


if (empty($from_date)) {
	$from_date = date_i18n('Y-m-d', strtotime('-30days'));
}
if ($to_date < $from_date) {
	$to_date = date_i18n('Y-m-d');
}

$limit_date  = date('Y-m-d', strtotime('-300days'));
$batch_statuses = tr_pdd_get_batch_statuses(false, false, false, 'all');





$conditions = trpdd_get_batch_conditions(0, isset($btype) ? $btype : '');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = '1 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where      .= $return['where_author'];
}

if ($panel_id > 0) {
	$where .= " and find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested ) ";
}

$where_aerrors = " atype = 'pre'";
//where missing
$rejected_issue = tr_get_option('ci_pdf_data', 'rejected_issue');
$rejected_issue = true;
if ($rejected_issue) {

	$batch_statuses_arr = tr_pdd_where_batch_incomplete(true, 'all');
	//unset($batch_statuses_arr['re_srrir']);
} else {
	$batch_statuses_arr = array(
		'incomplete'    => 'Incomplete',
		'in_spe_nambir' => 'Incomplete:  Missing Specimen Identification: Name and Birthdate',
		'in_spe_pa_nam' => 'Incomplete:  Missing Specimen Identification: Missing Patient Name',
		'in_spe_bir' => 'Incomplete:  Missing Specimen Identification: Missing Birthdate',
		'in_spe_bar' => 'Incomplete:  Missing Specimen Identification: Missing Barcode',
		'in_req' => 'Incomplete:  Missing Req Form or Req Form Empty',
		'in_doc' => 'Incomplete:  Missing Doctor Name',
		'in_ins' => 'Incomplete:  Missing Patient Insurance Information',
		'in_bir' => 'Incomplete:  Missing Birthdate',
		'in_pa_sig' => 'Incomplete:  Missing Patient Signature',
	);
}

$statuses = array();
foreach ($batch_statuses_arr as $k => $vl) {
	$statuses[] = $k;
}
$statuses = implode("','", $statuses);

$where .= " and  ba.batch_status in ('{$statuses}') ";


if (!empty($from_date)) {
	$where .= " and ba.doc >= '{$from_date}' ";
	$where_aerrors .= " and ae.edate >= '{$from_date}' ";
} else {
	$where .= " and ba.doc > '{$limit_date}' ";
	$where_aerrors .= " and ae.edate >= '{$limit_date}' ";
}
if (!empty($to_date)) {
	$where .= " and ba.doc <= '{$to_date} 23:59:59' ";
	$where_aerrors .= " and ae.edate <= '{$to_date} 23:59:59' ";
}

if (!empty($search)) {
	$where .= " and (ba.assen = '{$search}' or ba.patient_name like '%{{$search}}%') ";
}



$order      = isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
$order_by   = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby']) ? 'ba.' . $_REQUEST['orderby'] : 'ba.spec_received';
$child_queries = array();
$total      = 0;

$wherepanels = implode("','", tr_pdd_get_batch_types());
$panels = $wpdb->get_results("select tg_ID, tg_name 
				from ci_test_groups where tg_parent = 0 and tg_show_req =1
				and tg_type in ('{$wherepanels}') order by tg_type asc, tg_position asc");

foreach (tr_pdd_get_batch_types() as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$total += $wpdb->get_var("select count(*) as c 
	                            from {$batch_tbl} ba 
	                            where {$where}");
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
                        '' as aetype,
                        '{$btype}' as btype
                        from {$batch_tbl} ba
                        where {$where} ";
}


$total += $wpdb->get_var("select count(*) as c 
	                            from ci_analytical_errors ae 
	                            where {$where_aerrors}");

$child_queries[] = "select '' as mid,
                        ae.assen,
                        '' as batch_status,
                        '' as batch_id,
                        ae.edate as spec_received,
                        '' as requesting_practice,
                        '' as panel_tests_requested,
                        ae.desc as billing_notes,
                        '' as issue_photo,
                        ae.created_by as author_ID,
                        'custom' as aetype,
                        '' as btype
                        From ci_analytical_errors ae
                        where {$where_aerrors} ";

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'Export') {

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=specimen_issues.csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	$f = fopen('php://output', 'w');
	$headers = array('Received Date', 'Accession', 'Clinic', 'Reason', 'Panels', 'Note', 'User');
	fputcsv($f, $headers);


	$child_queries = implode(" UNION ALL ", $child_queries);
	$sql = "select * from ({$child_queries}) ba
        order by {$order_by} {$order}
        ";
	$results = $wpdb->get_results($sql);
	foreach ($results as $row) {

		$panel = '';
		$reason = $batch_statuses[$row->batch_status];
		if ($row->aetype == 'custom') {
			$reason = $row->billing_notes;
			$row->billing_notes = '';

			$email = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$row->author_ID}");
		} else {
			$email = $wpdb->get_var("select u.user_email from {$wpdb->users} u
	         						join ci_log_users us ON us.user_id = u.ID
	         						where us.mid = '{$row->mid}' and btype='{$row->btype}' 
	         						and us.user_action = 'change_status'
	         						ORDER BY us.created DESC");
			if (empty($email)) {
				$email = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$row->author_ID}");
			}
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
			$row->spec_received,
			$row->assen,
			$row->requesting_practice,
			stripslashes($reason),
			$panel,
			stripslashes($row->billing_notes),
			$email
		);
		fputcsv($f, $rowdata);
	}

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


trpdd_log_query_error();


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
			<input type="hidden" name="subtab" value="<?php echo isset($_GET['subtab']) ? $_GET['subtab'] : '' ?>" />
			<label>From</label>
			<input type="text" class="hasdate" name="from" value="<?php echo @$from_date ?>" />
			<label>To</label>
			<input type="text" class="hasdate" name="to" value="<?php echo @$to_date ?>" />
			<label>Accession/Name</label>
			<input type="search" name="s" value="<?php echo $search ?>" />
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
			<a class="button thickbox" title="Add New" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'add_form_issue')) ?>">Add New</a>
		</form>
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>

	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
				<tr>
					<?php tr_tbl_sort('spec_received', 'Received Date') ?>
					<?php tr_tbl_sort('assen', 'Accession') ?>
					<?php tr_tbl_sort('requesting_practice', 'Clinic') ?>
					<?php tr_tbl_sort('batch_status', 'Reason') ?>
					<th>Panels</th>
					<th>Note</th>
					<th>Photo</th>
					<th>User</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($results as $i => $row) { ?>
					<?php
					$panel = '';
					$reason = $batch_statuses[$row->batch_status];
					if ($row->aetype == 'custom') {
						$reason = $row->billing_notes;
						$row->billing_notes = '';
						$email = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$row->author_ID}");
					} else {
						$email = $wpdb->get_var("select u.user_email from {$wpdb->users} u
	         						join ci_log_users us ON us.user_id = u.ID
	         						where us.mid = '{$row->mid}' and btype='{$row->btype}' 
	         						and us.user_action = 'change_status'
	         						ORDER BY us.created DESC");
						if (empty($email)) {
							$email = $wpdb->get_var("select user_email from {$wpdb->users} where ID = {$row->author_ID}");
						}
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
						if (isset($row->culture) && $row->culture) {
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
						<td><?php echo $row->spec_received ?></td>
						<td><?php echo $row->assen ?></td>
						<td><?php echo $row->requesting_practice ?></td>
						<td><?php echo $reason ?></td>
						<td><?php echo $panel ?></td>
						<td><?php echo $row->billing_notes ?></td>
						<td>
							<?php if (!empty($row->issue_photo)): ?>
								<a class="thickbox" href="<?php echo $dir['url'] . '/' . $row->issue_photo ?>">View</a>
							<?php endif; ?>
						</td>
						<td><?php echo $email; ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

</div>