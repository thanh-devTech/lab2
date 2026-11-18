<?php
global $wpdb, $current_role;
global $access_btypes, $sign_dir;

include_once(TRCPDD_PATH . 'inc/functions_custody_chain.php');

$sign_dir = tr_get_signature_dir();
$_GET['assen'] = isset($_GET['assen']) ? trim($_GET['assen']) : '';
$user_id    = get_current_user_id();
$user       = wp_get_current_user();

$where_clinic   = '';
$where_ba       = '';

if ($current_role == 'office_manager') {
	if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
		$cid     = get_user_meta(get_current_user_id(), '_clinic_id', true);
		$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$cli_ids = implode(',', $cli_ids);

		$where_clinic .= " and cID in ( {$cli_ids} )";
		$where_ba .= " and cli_ID in ({$cli_ids}) ";
	}
} else if ($current_role == 'administrator') {
	if ($user->user_login == 'Administrat0r' && isset($_GET['reset'])) {
		$wpdb->delete('ci_log_users', array('user_id' => $user->ID));
	}
}

add_thickbox();
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.2');
wp_enqueue_script('jquery-ui-datepicker');



function trcustody_chain_compare2($newdata, $lastdata)
{
	global $has_batch_changed;
	$key2s = array_keys(array_merge($newdata, $lastdata));
	foreach ($key2s as $k) {
		if (in_array($k, array('mid', 'pa_ID', 'ins_ID', 'save_print', 'form_unique_id', 'ref', 'auto_generated', 'old_status'))) {
			continue;
		}
		$vl = $newdata[$k];
		$lastvl = $lastdata[$k];
		if ((is_array($vl) || is_array($lastvl))
			&& !in_array($k, array('dagnostic_codes', 'panel_tests_requested', 'type_of_specimen'))
		) {
			trcustody_chain_compare2($vl, $lastvl);
		} else {
			if (is_array($vl) || is_array($lastvl)) {
				$vl = implode(', ', $vl);
				$lastvl = implode(', ', $lastvl);
			}

			if ($lastvl == '0000-00-00') $lastvl = '';
			if ($vl == '0000-00-00') $vl = '';

			if ($lastvl == '0') $lastvl = '';
			if ($vl == '0') $vl = '';

			if (strtolower($vl) == strtolower($lastvl)) continue;
			$has_batch_changed = true;
			$field = $k;
			$field = str_replace('pa_', 'Patient ', $field);
			$field = str_replace('ins_', 'Insurance ', $field);
?>
			<tr>
				<td><?php echo $field ?></td>
				<td><?php echo $lastvl ?></td>
				<td><?php echo $vl ?></td>
			</tr>
	<?php
		}
	}
}

$log = '';
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';


if ($trajax == 'load_last_change') {

	global $has_batch_changed;
	$current_data   = $wpdb->get_var("select data from ci_log_forms where user_log_id = {$_REQUEST['id']}");
	$last_data      = $wpdb->get_var("select data from ci_log_forms where user_log_id = {$_REQUEST['lastid']}");

	$current_data   = json_decode($current_data, true);
	$last_data      = json_decode($last_data, true);

	?>
	<table class="wp-list-table widefat fixed striped table-view-list pages">
		<thead>
			<tr>
				<th>Field</th>
				<th>Old Data</th>
				<th>New Data</th>
			</tr>
		</thead>
		<tbody>
			<?php
			trcustody_chain_compare2($current_data, $last_data);
			if (!$has_batch_changed) {
				echo '<tr><td colspan=3>No change</td></tr>';
			}
			?>
		</tbody>
	</table>
	<?php
	exit;
} else if ($trajax == 'load_last_change_rs') {
	global $has_batch_changed, $current_btype;

	$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
	$moid = isset($_REQUEST['moid']) ? intval($_REQUEST['moid']) : 0;
	$uaction = null;

	if ($moid > 0) {
		$current_data = $wpdb->get_var("select other_info from ci_sync_api_orders where mo_ID = {$moid}");
	} else {
		$user_log = $wpdb->get_row("select data, user_action from ci_log_users where id = {$id}");
		if ($user_log) {
			$current_data = $user_log->data;
			$uaction = $user_log->user_action;
		}
	}


	$current_data = json_decode($current_data, true);
	if ($uaction && strpos($uaction, 'hold') !== false) {
	?>
		<table class="wp-list-table widefat fixed striped table-view-list pages">
			<thead>
				<tr>
					<th>Reason Title</th>
					<th>Reason</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo isset($current_data['reason_title']) ? $current_data['reason_title'] : '' ?></td>
					<td><?php echo isset($current_data['reason']) ? $current_data['reason'] : '' ?></td>
				</tr>
			</tbody>
		</table>
	<?php
	} else if (!empty($current_data)) {
	?>

		<table class="wp-list-table widefat fixed striped table-view-list pages">
			<thead>
				<tr>
					<th>Field</th>
					<th>Old Data</th>
					<th>New Data</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($current_data as $drug => $arr) {
					if (in_array($drug, array('file', 'assen'))) {
						continue;
					}
					if (!is_array($arr)) {
						$arr = explode('(to)', $arr);
					}

					if ($moid > 0) {
						if (count($arr) == 2 && $arr[1] == 0) {
							continue;
						} else if (count($arr) == 3 && $arr[2] == 0) {
							continue;
						}
						$arr['old'] = isset($arr['old']) ? $arr['old'] : ((isset($arr[1]) && isset($arr[2])) ? $arr[1] : '');
						$arr['new'] = isset($arr['new']) ? $arr['new'] : (isset($arr[0]) ? $arr[0] : '');
					}

					if (isset($arr['old']) && is_array($arr['old'])) {
						$old_values = $arr['old'];
						$arr['old'] = '';
						foreach ($old_values as $kold => $vold) {
							if ($kold == 'conc') $kold = 'CT';
							$arr['old'] .= count($old_values) == 1 ? $vold : '<div>' . $kold . ': ' . $vold . '</div>';
						}
					}
					if (isset($arr['new']) && is_array($arr['new'])) {
						$new_values = $arr['new'];
						$arr['new'] = '';
						foreach ($new_values as $knew => $vnew) {
							if ($knew == 'conc') $knew = 'CT';
							$arr['new'] .= count($new_values) == 1 ? $vnew : '<div>' . $knew . ': ' . $vnew . '</div>';
						}
					}

					$arr['old'] = $arr['old'] ?? ($arr[0] ?? '');
					$arr['new'] = $arr['new'] ?? ($arr[1] ?? '');
					if ($arr['old'] == $arr['new']) {
						continue;
					}
				?>
					<tr>
						<td><?php echo $drug ?></td>
						<td><?php echo $arr['old'] ?></td>
						<td><?php echo $arr['new'] ?></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	<?php
	}
	exit;
} else if ($trajax == 'restore') {
	$assen   = isset($_GET['assen']) ? $_GET['assen'] : '';
	$deleted = $wpdb->get_row("select * from ci_log_forms 
			            where form='delete_assen'
			            and data like '%{$assen}%' order by id desc limit 1 ");


	if ($deleted) {
		$data_arr   = json_decode($deleted->data, true);
		$btype      = isset($data_arr['btype']) ? $data_arr['btype'] : '';

		if (empty($btype) && !empty($data_arr['ref'])) {
			foreach (tr_pdd_get_batch_types() as $bt) {
				if (stripos($data_arr['ref'], $bt) > 0) {
					$btype = $bt;
					break;
				}
			}
		}



		if (!empty($btype)) {

			if (isset($data_arr['bat'])) {
				$data_arr = $data_arr['bat'];
			}

			unset($data_arr['current_lab_id']);
			unset($data_arr['lab_email']);
			unset($data_arr['ref']);

			$batch_tbl = tr_pdd_get_table_by_btype($btype);

			$rs = $wpdb->insert($batch_tbl, $data_arr);


			Tr_Session_Class::add("Restored: " . $assen);
		} else {
			Tr_Session_Class::add_error("Not found btype");
		}
	} else {
		Tr_Session_Class::add_error("Not found old data!");
	}
	wp_redirect(Tr_Base_Class_V6::link(array('trajax' => '', 't' => '', 'act' => ''), false));
} else if ($trajax == 'restore_bat') {
	$mid   = isset($_GET['id']) ? intval($_GET['id']) : 0;
	$logid = isset($_GET['logid']) ? intval($_GET['logid']) : 0;
	$btype    = isset($_GET['btype']) ? $_GET['btype'] : '';

	if ($mid > 0 && !empty($btype)) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$assen = $wpdb->get_var("select assen from {$batch_tbl} where mid = {$mid}");
		$wpdb->update($batch_tbl, array('is_saved' => 0), array('mid' => $mid));
		trpdd_log_user(get_current_user_id(), 'restore_req', '', $mid, $btype, '', '', $assen);
		if ($logid > 0) {
			$wpdb->update('ci_log_users', array('data' => 'restored'), array('id' => $logid));
		}
		Tr_Session_Class::add("Sample {$assen} restored.");
	} else {
		Tr_Session_Class::add_error("Invalid request.");
	}
	wp_redirect(Tr_Base_Class_V6::link(array('trajax' => '', 't' => '', 'act' => ''), false));
}






if (!empty($_GET['assen'])) {

	$bat = new stdClass();
	$assens = explode(',', $_GET['assen']);

	foreach ($assens as $assen) {
		$log .= '<div class="searchgroup">';

		$btype = '';
		foreach ($access_btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$adding_fields = '';
			if ($btype == 'study') {
				$adding_fields .= ', ba.receivingID';
			}
			$sql = "select 
			            ba.mid,
			            ba.batch_id,
			            ba.position,
			            ba.assen,
			            ba.doc,
			            ba.batch_created,
			            ba.batch_type,
			            ba.batch_created,
			            ba.batch_status,
			            ba.author_ID,
			            ba.cli_ID,
			            ba.billing_notes,
						ba.is_saved,
			            pa.pa_state
						{$adding_fields}
			        from {$batch_tbl} ba
			        LEFT join ci_patients pa ON pa.pa_ID = ba.patient_id
			        where (assen = '{$assen}' or oldreq='{$assen}') {$where_ba}";
			$bat = $wpdb->get_row($sql);
			if ($bat) {
				break;
			}
		}

		if (isset($_GET['debug'])) {
			echo '<br><br>';
			var_dump($btype);
			echo '<br><br>';
			var_dump($sql);
			echo '<br><br>';
			var_dump($bat);
			echo '<br><br>';
		}


		if ($bat) {

			$rslog = trcustody_chain_detail($bat, $btype);
			$btypename = tr_pdd_get_typename($btype);
			$log .= '<div class="single_log">';
			$log .= '<h2 class="assentt"><span class="assen" title="' . $bat->mid . '">' . $assen . '</span> <small>' . $btypename . '</small> <span class="bid">Batch# ' . $bat->batch_id . '</span></h2>';

			$log .= '<div class="tt" title="Position: ' . $bat->position . '"><i class="linkicon"></i>Receiving</div>';
			$log .= $rslog['receiving'];

			if (isset($rslog['rack'])) {
				$log .= '<div class="tt"><i class="linkicon"></i>Racking</div>';
				$log .= $rslog['rack'];
			}

			if (isset($rslog['plate'])) {
				$log .= '<div class="tt"><i class="linkicon"></i>Plating</div>';
				$log .= $rslog['plate'];
			}

			$log     .= '<div class="tt" title="DOC: ' . date('m/d/Y H:i:s', strtotime($bat->doc)) . '"><i class="linkicon"></i>Requisitions</div>';
			$log .= $rslog['req'];

			$log .= '<div class="tt"><i class="linkicon"></i>Results</div>';
			$log .= $rslog['results'];

			if (!empty($rslog['state_upload'])) {
				$log .= '<div class="tt"><i class="linkicon"></i>State Upload</div>';
				$log .= $rslog['state_upload'];
			}
			$log .= '</div>';
		} else {

			//search deleted
			$deleted = $wpdb->get_row("select * from ci_log_forms 
			            where form='delete_assen'
			            and data like '%{$assen}%' limit 1");

			if (!$deleted) {
				$row = $wpdb->get_row("select mid,btype from ci_log_users where data='{$assen}' limit 1");

				if ($row && $row->mid > 0) {
					$deleted = $wpdb->get_row("select * from ci_log_users 
						            where user_action='delete_rs'
						            and mid = {$row->mid} and btype='{$row->btype}' order by id desc limit 1 ");
					if ($deleted) {
						$deleted->data = '';
						$deleted->nolog = true;
						$deleted->created_date = $deleted->created;
					}
				}
			}

			if ($deleted) {
				$deleted_at = date('m/d/Y H:i:s', strtotime($deleted->created_date));
				$log .= '<div class="single_log">';
				$log .= '<div class="tt"><i class="linkicon"></i>Deleted at ' . $deleted_at . '</div>';
				if (isset($deleted->user_id)) {
					$del_user = get_user_by('ID', $deleted->user_id);
					if ($del_user) {
						$log .= '<div class="tt"><i class="linkicon"></i>Deleted by ' . $del_user->user_login . '</div>';
					}
				}
				if (isset($deleted->user_action) && $deleted->user_action == 'delete_rs') {
					$log .= '<div class="tt"><i class="linkicon"></i>In Result page</div>';
				}
				$log .= '<div class="dt">';
				if (!isset($deleted->data)) {
					$deleted->data = '';
				}
				$deleted->data_arr = json_decode($deleted->data, true);
				if (empty($deleted->data_arr)) {
					$deleted->data = str_replace('Lab: <br>', '', $deleted->data);
					$log .= $deleted->data;
				} else {
					$log .= '<a href="' . Tr_Base_Class_V6::link(array('trajax' => 'restore', 'assen' => $assen, 't' => time()), false) . '">Restore</a>';
				}


				$log .= '</div>';


				if (!isset($deleted->nolog)) {
					//find mid
					$midbt = $wpdb->get_row("select mid,btype from ci_log_users where data='{$assen}' limit 1");
					if (!$midbt) {
						$midbt = $wpdb->get_row("select mid,btype from ci_log_users where data like '%{$assen}%' limit 1");
					}
					if ($midbt) {
						$bat = new stdClass();
						$bat->mid = $midbt->mid;
						$bat->deleted = true;
						$btype = $midbt->btype;

						$rslog = trcustody_chain_detail($bat, $btype);

						if ($rslog['receiving']) {
							$log .= '<div class="tt" title="Position: ' . (isset($bat->position) ? $bat->position : '') . '"><i class="linkicon"></i>Receiving</div>';
							$log .= $rslog['receiving'];
						}

						if (isset($rslog['rack'])) {
							$log .= '<div class="tt"><i class="linkicon"></i>Racking</div>';
							$log .= $rslog['rack'];
						}

						if (isset($rslog['plate'])) {
							$log .= '<div class="tt"><i class="linkicon"></i>Plating</div>';
							$log .= $rslog['plate'];
						}

						if (!empty($rslog['req'])) {
							$log     .= '<div class="tt"><i class="linkicon"></i>Requisitions</div>';
							$log .= $rslog['req'];
						}

						if (!empty($rslog['results'])) {
							$log .= '<div class="tt"><i class="linkicon"></i>Results</div>';
							$log .= $rslog['results'];
						}

						if (!empty($rslog['state_upload'])) {
							$log .= '<div class="tt"><i class="linkicon"></i>State Upload</div>';
							$log .= $rslog['state_upload'];
						}
					}
				}


				$log .= '</div>';
			} else {
				$log .= 'Not found: ' . $assen;
			}
		}

		$log .= '</div>';
	}
} else if (!empty($_REQUEST['clinic']) && !empty($_REQUEST['from']) && !empty($_REQUEST['to'])) {
	$orderby = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'doc';
	$order = $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
	$sql_child = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$adding_fields = '';
		if ($btype == 'study') {
			$adding_fields .= ', ba.receivingID ';
		} else {
			$adding_fields .= ', 0 as receivingID ';
		}
		$sql_child[] = "select 
		                ba.mid,
		                ba.doc,
			            ba.batch_id,
			            ba.position,
			            ba.assen,
			            ba.batch_created,
			            ba.spec_received,
			            ba.batch_type,
			            ba.batch_status,
			            ba.author_ID,
			            ba.cli_ID,
			            ba.billing_notes,
						ba.is_saved,
			            re.created,
			            pa.pa_state,
			            '{$btype}' as btype
			            {$adding_fields}
			            from {$batch_tbl} ba
			            LEFT join ci_patients pa ON pa.pa_ID = ba.patient_id
			             left join {$report_tbl} re On re.patient_id = ba.assen
			            where ba.cli_ID = '{$_REQUEST['clinic']}' 
		                and ba.doc >= '{$_REQUEST['from']}' and ba.doc<='{$_REQUEST['to']} 23:59:59' 
		        ";
	}
	$sql_child = implode(' UNION ALL ', $sql_child);
	$sql = "SELECT * FROM ({$sql_child}) r ";
	$sql .= " order by {$orderby} {$order} ";
	$batches = $wpdb->get_results($sql);
	$total = count($batches);
	$row_per_page = 100;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$offset = $pagination->offset();
	$pagination->setTotal($total);
	ob_start();
	?>
	<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

	<table class="wp-list-table widefat striped posts blue_tbl">
		<thead class="sticky">
			<tr>
				<?php
				tr_tbl_sort('position', 'Position', '', 'width:80px');
				tr_tbl_sort('assen', 'Accession', '', 'width:130px');
				tr_tbl_sort('batch_id', 'Batch');
				tr_tbl_sort('spec_received', 'Receiving');
				tr_tbl_sort('doc', 'Requisition');
				tr_tbl_sort('created', 'Results');
				tr_tbl_sort('batch_status', 'Current Status', '', 'font-size:10px;line-height:22px;width:106px');
				?>
			</tr>
		</thead>
		<?php
		$count = 0;
		$i = -1;
		foreach ($batches as $bat) {
			$i++;
			if ($i < $offset) continue;

			$rslog = trcustody_chain_detail($bat, $bat->btype);
			echo  '<tr>';
			echo  '<td title="id:' . $bat->mid . ' | type: ' . $bat->btype . '">' . $bat->position . '</td>';
			echo  '<td>' . $bat->assen . '</td>';
			echo  '<td>' . $bat->batch_id . '</td>';
			echo  '<td>' . $rslog['receiving'] . '</td>';
			echo  '<td>' . $rslog['req'] . '</td>';
			echo  '<td>' . $rslog['results'] . '</td>';
			echo  '<td>' . tr_pdd_get_batch_statuses($bat->batch_status, false, false, $bat->btype) . '</td>';
			echo  '</tr>';
			$count++;
			if ($count >= $row_per_page) {
				break;
			}
		}

		if ($total == 0) {
			echo '<tr><td colspan=7>No Results Found!</td></tr>';
		}
		?>
	</table>
	<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
<?php
	$log = ob_get_clean();
}

$clinics = $wpdb->get_results("select cID, cli_clinic 
                            from ci_clinic 
                            where cli_status = 1 {$where_clinic}
                            order by cli_clinic asc");
?>

<div class="wrap">
	<h2>Custody Chain</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="get" style="margin:20px 0 0 0" id="fscom" class="main_form need_validate">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<div class="frow">
			<label>Accession</label>
			<input type="search" name="assen" value="<?php echo $_GET['assen'] ?>" style="width:648px" />
		</div>
		<div class="frow">
			<label>Clinic</label>
			<select name="clinic" style="width:400px">
				<option value="">Select Clinic</option>
				<?php
				foreach ($clinics as $clinic) {
					$selected = isset($_REQUEST['clinic']) && $clinic->cID == $_REQUEST['clinic'] ? 'selected' : '';
					echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
				}
				?>
			</select>
			<input type="search" name="from" autocomplete="off" id="from" value="<?php echo isset($_REQUEST['from']) ? $_REQUEST['from'] : '' ?>" placeholder="DOC From" style="width:120px" />
			<input type="search" name="to" autocomplete="off" id="to" value="<?php echo isset($_REQUEST['to']) ? $_REQUEST['to'] : '' ?>" placeholder="DOC To" style="width:120px" />
			<input type="submit" value="Search" class="button bsty6 samefheight" />
		</div>
	</form>

	<div class="displaylog" style="margin-top:15px">
		<?php
		echo $log;
		?>
	</div>
</div>

<style>
	#fscom label {
		display: inline-block;
		width: 68px;
		height: 18px;
	}

	.single_log .tt {
		font-weight: bold;
		list-style: square;
		margin-top: 30px;
		margin-left: 30px;
		font-size: 20px;
	}

	.single_log .tt i {
		margin-right: 10px;
	}

	table td .dt {
		padding: 0 2px;
		margin: 2px 0;
	}

	table td .dt:nth-child(2n+1) {
		background: #eee;
	}

	table td .dt:nth-child(2n+2) {
		background: #ddd;
	}

	table.blue_tbl td,
	table.blue_tbl th {
		font-size: 12px;
	}

	.single_log .dt {
		padding-left: 58px;
		padding-top: 10px;
	}

	h2.assentt {
		border-bottom: 1px solid lightblue;
		display: inline-block;
		margin-bottom: 0;
	}

	h2.assentt .assen {
		display: inline-block;
		margin-right: 250px;
	}

	.searchgroup {
		margin-bottom: 75px;
	}
</style>
<script>
	jQuery(function($) {
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
		$('[name="assen"]').change(function() {
			if ($(this).val() != '') {
				$('#from').val('');
				$('#to').val('');
				$('[name="clinic"]').val('');
			}
		})
		$('#from,#to,[name="clinic"]').change(function() {
			if ($(this).val() != '') {
				$('[name="assen"]').val('');
			}
		})
		$('[name="clinic"]').select2();
	})
</script>