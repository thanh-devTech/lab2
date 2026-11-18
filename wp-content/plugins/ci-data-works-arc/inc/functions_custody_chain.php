<?php
global $define_instruments;

if (!function_exists('get_user_by')) {
	include_once(ABSPATH . '/wp-includes/pluggable.php');
}
include_once(TRCPDD_PATH . 'inc/define_instruments.php');

function filter_logs($array)
{
	global $is_ci_admin;
	global $is_ci_current_user;
	$rs = [];
	foreach ($array as $item) {
		$is_ci_user = false;
		$is_admin = false;
		$user_	= get_user_by('id', $item->user_id);
		$is_ci_user = get_user_meta($item->user_id, '_is_ci_user', true);
		$is_admin = in_array('administrator', (array) $user_->roles);
		$show = !$is_ci_user;
		if ($is_admin) {
			$show = false;
		}
		if ($is_ci_admin || $is_ci_current_user) {
			$show = true;
		}
		if ($show) {
			$rs[] = $item;
		}
	}
	return $rs;
}
function trcustody_chain_detail($bat, $btype, $onlyarr = false)
{
	global $is_ci_admin;
	global $is_ci_current_user;
	global $wpdb, $sign_dir;
	global $define_instruments;

	$rack_ABC = tr_get_option('ci_pdf_data', 'rack_ABC');

	$logs = array('receiving' => '', 'rack' => '', 'plate' => '', 'req' => '', 'results' => '');

	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$reportdb = $wpdb->get_row("select * from {$report_tbl} where patient_id = '{$bat->assen}' ");

	if ($btype == 'study') {
		$received = $wpdb->get_row("select 
		rb.re_ID,
		rb.created,
		rb.created_by
		from ci_receiving_study_batches rb 
		where rb.ID = {$bat->receivingID} ");
	} else {
		$received   = $wpdb->get_row("select 
		rb.re_ID,
		rb.created,
		rb.created_by
		from ci_receiving_batches rb 
		where rb.mid = {$bat->mid} and rb.btype='{$btype}' ");
	}


	$received_datetime = isset($received->created) ? $received->created : '';
	$user_received = null;
	$show = false;

	$log_receiving = array();
	if ($received) {
		$author2 = $bat->author_ID;

		if (empty($received_datetime)) {

			$createdtime = date('H:i:s', strtotime($bat->batch_created) - 300);

			if ($btype == 'study') {
				$received2 = $wpdb->get_row("select ri.re_today,
									ri.re_author_id
									from ci_receiving_study ri 
							where ri.re_ID = {$received->re_ID} ");
			} else {
				$received2 = $wpdb->get_row("select ri.re_today,
									ri.re_author_id
									from ci_receiving ri 
							where ri.re_ID = {$received->re_ID} ");
			}
			$received_datetime = $received2->re_today . ' ' . $createdtime;
			$author2 = $received2->re_author_id;
		}
		$user_received_id = $received->created_by > 0 ? $received->created_by : $author2;
		$received_date    = tr_pdd_display_date($received_datetime);
		$received_time    = tr_pdd_display_date($received_datetime, 'h:i:s A');
		$user_received    = get_user_by('id', $user_received_id);


		$display_name = trim($user_received->first_name . ' ' . $user_received->last_name);
		if (empty($display_name)) {
			$display_name = $user_received->display_name;
		}
		$receivingrow = array();
		if ($onlyarr) {
			$receivingrow = array(
				'action' => 'Received',
				'datetime' => $received_date . ' ' . $received_time,
				'by' =>  $display_name,
				'by_email' =>  $user_received->user_email,
			);
		} else {
			$is_ci_user = false;
			$is_admin = false;
			$is_ci_user = get_user_meta($user_received_id, '_is_ci_user', true);
			$is_admin = in_array('administrator', (array) $user_received->roles);
			$show = !$is_ci_user;
			if ($is_admin) {
				$show = false;
			}
			if ($is_ci_admin || $is_ci_current_user) {
				$show = true;
			}
			if ($show) {
				$receivingrow = '<div class="dt">Received by ';
				$receivingrow .= '<a href="mailto:' . $user_received->user_email . '" title="' . $user_received->user_email . '">' . $display_name . '</a>';
				$receivingrow .= ' at ' . $received_date . ' ' . $received_time;
				if (!empty($user_received->_signature)) {
					if (file_exists($sign_dir['path'] . '/' . $user_received->_signature) && filesize($sign_dir['path'] . '/' . $user_received->_signature) > 0) {
						$receivingrow .= ' <span class="signatureimg"><img class="sign_img" src="' . $sign_dir['url'] . '/' . $user_received->_signature . '?' . time() . '" style="max-height:26px"/></span>';
					}
				}
				$receivingrow .= '</div>';
			}
		}
		$log_receiving[] = $receivingrow;
	} else if (!$onlyarr) {
		$log_receiving[] = '<div class="dt">Not Received</div>';
	}


	if (empty($bat->batch_datetime)) {
		if (!empty($received_datetime)) {
			$bat->batch_datetime = date('Y-m-d H:i:s', strtotime($received_datetime) + 1000);
		} else if (isset($bat->batch_created)) {
			$bat->batch_datetime = date('Y-m-d H:i:s', strtotime($bat->batch_created) + 1000);
		}
	}



	$receiving_logs = $wpdb->get_results("select lu.id,
	                                                lu.user_action,
	                                                lu.data,
	                                                lu.created,
													lu.user_id
	                                        from ci_log_users lu 
	                                    where mid = {$bat->mid} and btype='{$btype}'
	                                    and user_action in('receiving_change_pos','receiving_add_batch','move_batch')
	                                     order by created asc");

	$has_add_batch = false;
	$filter_receiving_logs = filter_logs($receiving_logs);
	foreach ($filter_receiving_logs as $reql) {
		$req_date 		= tr_pdd_display_date($reql->created);
		$req_time 		= tr_pdd_display_date($reql->created, 'h:i:s A');
		$act 			= $reql->user_action;

		if ($act == 'receiving_change_pos') {
			$act = 'Changed position: ';
		} else if ($act == 'receiving_add_batch') {
			$act = 'Added to batch# ' . (empty($reql->data) ? $bat->batch_id : $reql->data);
			$reql->data = '';
			$has_add_batch = true;
		} else if ($act == 'move_batch') {
			$act = 'Moved to Batch# ' .  (empty($reql->data) ? $bat->batch_id : $reql->data);
			$reql->data = '';
		}

		$display_name 	= '';
		$email 			= '';
		$signature 		= '';

		if ($reql->user_id > 0) {
			$user_received	= get_user_by('id', $reql->user_id);
			$email 			= $user_received->user_email;
			$signature 		= $user_received->_signature;
			$display_name 	= trim($user_received->first_name . ' ' . $user_received->last_name);
			if (empty($display_name)) {
				$display_name = $user_received->display_name;
			}
		} else {
			$display_name = 'System';
		}

		if ($onlyarr) {
			$receivingrow = array(
				'action' => $act . ' ' . $reql->data,
				'datetime' => $req_date . ' ' . $req_time,
				'by' =>  $display_name,
				'by_email' =>  $email,
			);
		} else {
			$receivingrow = '<div class="dt">' . $act . ' ' . $reql->data . '  by ';
			$receivingrow .= '<a href="mailto:' . $email . '" title="' . $email . '">' . $display_name . '</a>';
			$receivingrow .= ' at ' . $req_date . ' ' . $req_time;

			if (!empty($signature)) {
				if (file_exists($sign_dir['path'] . '/' . $signature) && filesize($sign_dir['path'] . '/' . $signature) > 0) {
					$receivingrow .= ' <span class="signatureimg"><img class="sign_img" src="' . $sign_dir['url'] . '/' . $signature . '?' . time() . '" style="max-height:24px"/></span>';
				}
			}
			$receivingrow .= '</div>';
		}

		$log_receiving[] = $receivingrow;
	}


	if (!$has_add_batch && !empty($bat->batch_id) && !empty($bat->batch_datetime)) {
		$bat_time = tr_pdd_display_date($bat->batch_datetime, 'Y-m-d h:i:s A');
		$addedbby = $user_received ? $user_received->user_email : '';
		if (empty($addedbby)) {
			$addedbby = 'Auto';
		}
		if ($onlyarr) {
			$log_receiving[] = array(
				'action' => 'Added to Batch #' . $bat->batch_id,
				'datetime' => $bat_time,
				'by' => $addedbby,
				'by_email' => '',
			);
		} else {
			if ($show) {
				$log_receiving[] = '<div class="dt">Added to batch# ' . $bat->batch_id . '. by ' . $addedbby . ' at ' . $bat_time . '</div>';
			}
		}
	}


	if ($onlyarr) {
		$logs['receiving'] = $log_receiving;
	} else {
		$logs['receiving'] = implode(' ', $log_receiving);
	}


	$rs_acts = array(
		'update',
		'edit',
		'create',
		'insert',
		'save',
		'save_req',
		'delete_req',
		'save_req_cd',
		'apiserver_i',
		'apiserver_u',
		'restore_req',
	);
	$acts = array();
	foreach ($rs_acts as $act) {
		$acts[] = $act;
		$acts[] = $act . '_short';
	}
	$acts[] = 'move_ref';
	$acts[] = 'remove_ref';
	$acts[] = 'archived';
	$acts[] = 'rejected';
	$acts[] = 'canceled';
	$acts[] = 'change_status';
	$acts = implode("','", $acts);



	$req_logs = array();
	$batcreated = false;

	//check api for orders
	$logapi = $wpdb->get_row("select mo.*,
                                af.m_type
                            from ci_sync_api_orders mo
                            left join ci_sync_api_files af ON af.m_ID = mo.mo_file_id
                            where mo.mo_mid = {$bat->mid} 
                            and mo.mo_btype = '{$btype}'
                            and mo.mo_file_id > -13
							order by mo_control_ID desc
							");
	if ($logapi) {
		if (empty($bat->batch_created) || $bat->batch_created == '0000-00-00 00:00:00') {
			$bat->batch_created = $bat->doc;
		}
		if (empty($bat->batch_created)) {
			$other_info = json_decode($logapi->other_info, true);
			if (!isset($other_info['dt'])) {
				$bat->batch_created = $other_info['dt'];
			}
		}
		$req_date = tr_pdd_display_date($bat->batch_created);
		$req_time = tr_pdd_display_date($bat->batch_created, 'h:i:s A');
		$timecreate = strtotime($bat->batch_created);
		while (isset($req_logs[$timecreate])) {
			$timecreate++;
		}

		$api_type = 'API';
		if ($logapi->mo_file_id == -2) {
			$api_type = 'Elink';
			$batcreated = true;
		} else if ($logapi->mo_file_id == -3) {
			$api_type = 'CTEH';
			$batcreated = true;
		} else if ($logapi->mo_file_id == -4) {
			$api_type = 'Athena API';
			$batcreated = true;
		} else if ($logapi->mo_file_id == -27) {
			$api_type = ' Indiko+ Requested Tests';
		} else if ($logapi->m_type == 'hl7_ellkay') {
			$api_type = 'Ellkay';
			$batcreated = true;
		} else if ($logapi->m_type == 'hl7_athena') {
			$api_type = 'Athena sFtp';
			$batcreated = true;
		} else if (!empty($logapi->m_type)) {
			$api_type = strtoupper($logapi->m_type);
			$batcreated = true;
		}

		if ($onlyarr) {
			$req_logs[$timecreate] = array(
				'datetime' => $req_date . ' ' . $req_time,
				'by' => $api_type,
				'by_email' => '',
			);
		} else {
			$req_logs[$timecreate] = '<div class="dt">' .  $req_date . ' by ' . $api_type . ' at ' . $req_time . '</div>';
		}
	}

	//check by patient portal
	if (isset($bat->billing_notes) && stripos($bat->billing_notes, 'Billed by Order') !== false) {
		$batcreated = true;
		$req_date = tr_pdd_display_date($bat->batch_created);
		$req_time = tr_pdd_display_date($bat->batch_created, 'h:i:s A');
		$reql     = get_user_by('id', $bat->author_ID);

		$timecreate = strtotime($bat->batch_created);
		while (isset($req_logs[$timecreate])) {
			$timecreate++;
		}

		if ($onlyarr) {
			$req_logs[$timecreate] = array(
				'datetime' => $req_date . ' ' . $req_time,
				'by' => $reql->display_name,
				'by_email' => $reql->user_email,
			);
		} else {
			$req_logs[$timecreate] = '<div class="dt">Patient Portal ' . $req_date . ' by ' . $reql->user_email . ' at ' . $req_time . '</div>';
		}
	}

	$has_last_data = '';
	$req_log = $wpdb->get_results("select lu.id,
				lu.user_action,
				lu.created,
				lu.data,
				lu.user_id
		from ci_log_users lu 
	where ( (mid = {$bat->mid} and btype='{$btype}') or data = '{$bat->assen}')
	and user_action in ('{$acts}')
	order by created asc");

	$filter_req_log = filter_logs($req_log);

	foreach ($filter_req_log as $reql) {
		$addingtext = '';
		$req_date = tr_pdd_display_date($reql->created);
		$req_time = tr_pdd_display_date($reql->created, 'h:i:s A');
		$timecreate = strtotime($reql->created);
		while (isset($req_logs[$timecreate])) {
			$timecreate++;
		}

		$req_form = $batcreated ? 'Req Form modified' : 'Req Form created';


		$display_name 	= '';
		$email 			= '';
		$signature 		= '';

		if ($reql->user_id > 0) {
			$user_req	= get_user_by('id', $reql->user_id);
			$email 			= $user_req->user_email;
			$signature 		= $user_req->_signature;
			$display_name 	= trim($user_req->first_name . ' ' . $user_req->last_name);
			if (empty($display_name)) {
				$display_name = $user_req->display_name;
			}
		}

		if ($reql->user_action == 'rejected') {
			$req_form = 'Rejected ';
			$status = tr_get_option('ci_pdf_data', 'rejectbydocdates_status');
			if (!empty($status)) {
				$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true);
				if (isset($rejected_statuses[$status])) {
					$req_form = $rejected_statuses[$status];
				}
			}
			$display_name = 'No Batch ID';
		} else if ($reql->user_action == 'delete_req') {
			$req_form = 'Deleted ';

			if ($bat->is_saved == 2 && stripos($reql->data, 'restored') === false) {
				$addingtext .= ' <a href="' . Tr_Base_Class_V6::link(array('trajax' => 'restore_bat', 'id' => $bat->mid, 'logid' => $reql->id, 'btype' => $btype), false) . '"> Restore</a> ';
			}
		} else if ($reql->user_action == 'restore_req') {
			$req_form = 'Restored ';
		} else if ($reql->user_action == 'change_status') {
			$req_form = 'Changed Status ';
			$changed = json_decode($reql->data, true);
			if (is_array($changed) && isset($changed['batch_status'])) {
				$req_form .= ' from ' . $changed['batch_status'][0] . ' to ' . $changed['batch_status'][1];
			}
		} else if ($reql->user_action == 'canceled') {
			$req_form = 'Canceled ';
			$display_name = 'Auto';
			$display_name = '';
		} else if ($reql->user_action == 'archived') {
			$req_form = 'Archived ';
		} else if ($reql->user_action == 'move_ref') {
			$reflab = CI_Base_Lab::getLabByID($reql->data);
			$reflabname = '';
			if ($reflab) {
				$reflabname = $reflab->lab_dba_init;
			}
			//check sent api
			$sentapi = $wpdb->get_var("select count(*) from ci_wsdl_batch wb
						JOIN ci_wsdl_keys wk ON wk.wsdl_ID=wb.b_wsdl_ID and wk.wsdl_type = 'req_{$btype}' 
						where b_mid={$bat->mid} and btype = '{$btype}' and b_status =1");

			$req_form = ($sentapi ? 'Sent to Reflab: ' : 'Moved to Reflab: ') . $reflabname . ' at ';
		} else if ($reql->user_action == 'apiserver_i' || $reql->user_action == 'apiserver_u') {
			$display_name = 'Server API';
			$req_form = 'Created ';
			if ($batcreated) {
				continue;
			}
			$batcreated = true;
		} else if ($btype != 'tox') {
			if (!$batcreated) {
				$req_form = 'Req Form created';
				$batcreated = true;
			}
			$batcreated = true;
		} else if (strpos($reql->user_action, 'short') || ($bat->batch_type == 'M' && !$batcreated)) {
			$req_form   = !$batcreated ? 'Short Form created' : 'Short Form modified';
			$batcreated = true;
		}

		if ($req_form == 'Req Form created') {
			$batcreated = true;
		}

		//find assen in data
		$found_assen = '';
		$dataarr = json_decode($reql->data, true);
		if (is_array($dataarr)) {
			if (isset($dataarr['assen'])) {
				$found_assen = trim($dataarr['assen']);
			}
			if (isset($dataarr['ostatus']) && isset($dataarr['nstatus'])) {
				$addingtext .= ' modified Status: ' . tr_pdd_get_batch_statuses($dataarr['ostatus'], false, false, $btype) .
					' to ' . tr_pdd_get_batch_statuses($dataarr['nstatus'], false, false, $btype) . ' ';
			}
		} else if (stripos($reql->data, 'assen:') !== false) {
			list($notext, $found_assen) = explode('assen: ', $reql->data);
			$found_assen = trim($found_assen);
		} else {
			$found_assen = trim($reql->data);
			if (strlen($found_assen) != strlen($bat->assen)) {
				$found_assen = '';
			}
		}

		//find change
		$has_change_log = $wpdb->get_var("select id from ci_log_forms where user_log_id = {$reql->id}");
		if ($has_change_log) {
			if ($has_last_data) {
				$title = $req_date . ' by ' . $display_name . ' at ' . $req_time;
				$addingtext .= ' ( <a class="thickbox" title="' . $title . '" href="' . Tr_Base_Class_V6::link(array('trajax' => 'load_last_change', 'id' => $reql->id, 'lastid' => $has_last_data), false) . '"> view changes</a> ) ';
			}
			$has_last_data = $reql->id;
		} else if ($req_form === 'Req Form modified') {
			$dataarr = json_decode($reql->data, true);
			if (is_array($dataarr) && count($dataarr) > 0) {
				$title = $req_date . ' by ' . $display_name . ' at ' . $req_time;
				$addingtext .= ' ( <a class="thickbox" title="' . $title . '" href="' . Tr_Base_Class_V6::link(array('trajax' => 'load_last_change_rs', 'id' => $reql->id), false) . '"> view </a> ) ';
			}
		}

		if (!empty($found_assen) && isset($bat->assen) && strtoupper($found_assen) != strtoupper($bat->assen)) {
			$addingtext .= ' (' . $found_assen . ')';
		}

		if (!empty($signature)) {
			if (file_exists($sign_dir['path'] . '/' . $signature) && filesize($sign_dir['path'] . '/' . $signature) > 0) {
				$addingtext .= ' <span class="signatureimg"><img class="sign_img" src="' . $sign_dir['url'] . '/' . $signature . '?' . time() . '" style="max-height:24px"/></span>';
			}
		}



		if ($onlyarr) {
			$req_logs[$timecreate] = array(
				'datetime' => $req_date . ' ' . $req_time,
				'by' => $display_name,
				'by_email' => $email,
			);
		} else {
			$req_logs[$timecreate] = '<div class="dt" data-act=' . $reql->user_action . '>' . $req_form . ' ' . $req_date . ' by ';
			$req_logs[$timecreate] .= '<a href="mailto:' . $email . '" title="' . $email . '">' . $display_name . '</a>';
			$req_logs[$timecreate] .= ' at ' . $req_time . $addingtext . '</div>';
		}
	}

	if (count($req_log) == 0 && !$batcreated) {
		$req_date = tr_pdd_display_date($bat->batch_created, 'm/d/Y');
		$req_time = tr_pdd_display_date($bat->batch_created, 'h:i:s A');
		$reql     = get_user_by('id', $bat->author_ID);
		$req_form = !$batcreated ? (($bat->batch_type == 'M') ? 'Short ' : '') . 'Form created' : 'Req Form modified';

		if ($bat->cli_ID > 0 || $btype == 'study') {
			$timecreate = strtotime($bat->batch_created);
			while (isset($req_logs[$timecreate])) {
				$timecreate++;
			}

			if ($onlyarr) {
				$req_logs[$timecreate] = array(
					'datetime' => $req_date . ' ' . $req_time,
					'by' => $reql->display_name,
					'by_email' => $reql->user_email,
				);
			} else {
				$req_logs[$timecreate] = '<div class="dt" title="by No log">' . $req_form . ' ' . $req_date . ' by ' . $reql->user_email . ' at ' . $req_time . '</div>';
			}
		}
	}


	ksort($req_logs);

	if ($onlyarr) {
		$logs['req'] = $req_logs;
	} else {
		$logs['req'] = implode(' ', $req_logs);
	}




	//check rack
	$racks = $wpdb->get_results("select ra.created,
                                    r.re_number,
                                    r.re_subtitle,
									u.ID as user_id,
                                    u.user_email
                            from ci_rack_batches ra
                            join ci_racks r ON r.re_ID = ra.re_ID
                            left join {$wpdb->users} u ON ra.created_by = u.ID
                            where ra.mid = {$bat->mid} and ra.btype='{$btype}'");

	$sql_plate = "select ra.created,u.ID as user_id,
                                    u.user_email,
                                    ucom.user_email as com_user_email,
                                    pc.created as com_created,
                                    ra.position as plate_position,
                                    pl.pl_today
                            from ci_plates_batches ra
                            left join {$wpdb->users} u ON ra.created_by = u.ID
                            join ci_plates pl ON pl.pl_ID = ra.pl_ID
                            left join ci_plates_combine pc ON pc.ID = pl.pl_combine_ID
                            left join {$wpdb->users} ucom ON pc.created_by = ucom.ID
                            where ra.mid = {$bat->mid} and ra.btype='{$btype}'
                            group by ra.ID";

	$plates = $wpdb->get_results($sql_plate);

	$racklogs = array();
	if (count($racks) > 0) {
		$filter_racks = filter_logs($racks);
		foreach ($filter_racks as $rack) {
			$rack_date = tr_pdd_display_date($rack->created, 'm/d/Y h:i:s A');
			if ($onlyarr) {
				$racklogs[] = array(
					'number' => $rack->re_number,
					'title' => $rack->re_subtitle,
					'date' => $rack_date,
					'by' => $rack->user_email,
				);
			} else {
				$racklogs[] = '<div class="dt">Rack #' . $rack->re_number . ': ' . $rack->re_subtitle . '</div>';
				$racklogs[] = '<div class="dt">Added by ' . $rack->user_email . ' at ' . $rack_date . '</div>';
			}
		}
	}

	if ($onlyarr) {
		$logs['rack'] = $racklogs;
	} else {
		$logs['rack'] = implode(' ', $racklogs);
	}


	$plates_log = array();
	if (count($plates) > 0) {
		$filter_plates = filter_logs($plates);
		foreach ($filter_plates as $plate) {
			if ($rack_ABC) {
				$num12 = floor(($plate->plate_position - 1) / 8) + 1;
				$welld = chr(floor(($plate->plate_position - 1) % 8) + 65) . $num12;
			} else {
				$num12 = ($plate->plate_position - 1) % 12 + 1;
				$welld = chr(floor(($plate->plate_position - 1) / 12) + 65) . $num12;
			}

			$plate_date = tr_pdd_display_date($plate->created, 'm/d/Y h:i:s A');

			if ($onlyarr) {
				$plates_log =  array(
					'position' => $welld,
					'date' => $plate_date,
					'by' => $plate->user_email,
				);
			} else {
				$plates_log[] = '<div class="dt">Position: ' . $welld . '</div>';
				$plates_log[] = '<div class="dt" title=' . $plate->pl_today . '>Added by ' . $plate->user_email . ' at ' . $plate_date . '</div>';
			}


			if (!empty($plate->com_user_email)) {
				$plate_date_com = tr_pdd_display_date($plate->com_created, 'm/d/Y h:i:s A');
				$plates_log[] = '<div class="dt">Combined by ' . $plate->com_user_email . ' at ' . $plate_date_com . '</div>';
			}
		}
	}

	if ($onlyarr) {
		$logs['plate'] = $plates_log;
	} else {
		$logs['plate'] = implode(' ', $plates_log);
	}

	$rs_acts = array(
		'editreverse',
		'editapprove',
		'edit',
		'delete',
		'uploadedpdf',
		'uploaded',
		'approve',
		'approve_auto',
		'reverse',
		'removehold',
		'hold',
		'au680',
		'alinity',
		'uploaded_screeing'
	);
	$acts = array();
	foreach ($rs_acts as $act) {
		$acts[] = $act . '_rs';
		$acts[] = $act . '_rs_cd';
		if ($act == 'fax') {
			$acts[] = $act;
		}
	}
	$acts = implode("','", $acts);

	$sql_results = "select lu.id,
                            lu.user_id,
                            lu.user_action,
                            lu.data,
                            lu.created
                    from ci_log_users lu 
                where mid = {$bat->mid} and btype = '{$btype}'
                and user_action in ('{$acts}') 
                 order by created asc, user_action desc";
	$result_log = $wpdb->get_results($sql_results);

	$approved      = false;
	$uploaded_data = false;
	$log_result    = array();

	if (isset($_GET['debug'])) {
		echo '<br><br>';
		var_dump($sql_results);
		echo '<br><br>';
		var_dump($result_log);
		echo '<br><br>';
	}
	$exist_log = array();
	$filter_result_logs = filter_logs($result_log);
	foreach ($filter_result_logs as $reql) {


		$data_arr = !empty($reql->data) ? json_decode($reql->data, true) : array();


		$existkey = $reql->created . $reql->user_action;
		if (isset($exist_log[$existkey])) {
			continue;
		}
		$exist_log[$existkey] = 1;
		$addingtext = '';
		$last_change_title = 'view changes';
		$r_date   = tr_pdd_display_date($reql->created);
		$r_time   = tr_pdd_display_date($reql->created, 'h:i:s A');
		$r_action = $reql->user_action;
		$user_reql = new stdClass();
		if ($reql->user_id > 0) {
			$user_reql    = get_user_by('id', $reql->user_id);
		}

		if ($r_action == 'architect_rs' || $reql->user_id == -5) {
			$r_action = 'Data from Instrument Architect received at ';
		} else if ($reql->user_id == -4) {
			$r_action = 'Data from instrument Alinity i received at ';
		} else if ($reql->user_id == -3) {
			$r_action = 'Screening Data from instrument AU680 received at ';
		} else if ($reql->user_id == -2) {
			$r_action = 'Specimen Validity Test Data from instrument 6410 received at ';
		} else if (stripos($reql->user_action, 'editreverse') !== false) {
			$r_action = 'Modified & Reversed';
		} else if (stripos($reql->user_action, 'editapprove') !== false) {
			$r_action = 'Modified & Approved';
			$approved = true;
		} else if (stripos($reql->user_action, 'edit') !== false) {
			$r_action = 'Modified';
		} else if (stripos($reql->user_action, 'uploadedpdf') !== false) {
			$r_action = 'Uploaded Ref Result ';
		} else if (stripos($reql->user_action, 'uploaded') !== false) {
			$r_action      = 'Data Uploaded';
			$uploaded_data = true;
			$file_csv = $data_arr['file'] ?? '';
			if (empty($file_csv)) {
				$file_csv = $reportdb->upload_files ?? '';
			}
			if (!empty($file_csv)) {
				$file_csv = '/wp-content/log/results' . ($btype == 'tox' ? '' : '_' . $btype) . '/' . $file_csv;
				$addingtext .= ' ( <a target=_blank  href="' . $file_csv . '"> file</a> ) ';
			}
		} else if (stripos($reql->user_action, 'approve') !== false) {
			$r_action = 'Approved';
			$approved = true;
		} else if (stripos($reql->user_action, 'reverse') !== false) {
			$r_action = 'Reversed';
		} else if (stripos($reql->user_action, 'removehold') !== false) {
			$r_action = 'Remove Hold';
		} else if (stripos($reql->user_action, 'hold') !== false) {
			$r_action = 'On Hold';
			$last_change_title = 'reason';
		} else if (stripos($reql->user_action, 'delete') !== false) {
			$r_action = 'Deleted';
		} else if (stripos($reql->user_action, 'uploaded_screeing') !== false) {
			$r_action = 'Uploaded Screening';
		} else if (stripos($reql->user_action, 'uploaded_screeing') !== false) {
			$r_action = 'Faxed';
		} else if ($reql->user_action == 'fax') {
			$r_action = 'Faxed';
		}

		//find assen in data
		$found_assen = '';

		if (is_array($data_arr) && count($data_arr) > 0) {
			$addingtext .= ' ( <a class="thickbox" title="' . $bat->assen . ' | ' . $user_reql->user_email . '" href="' . Tr_Base_Class_V6::link(array('trajax' => 'load_last_change_rs', 'id' => $reql->id), false) . '"> ' . $last_change_title . ' </a> ) ';
		} else if (stripos($reql->data, 'assen:') !== false) {
			list($notext, $found_assen) = explode('assen: ', $reql->data);
			if (strpos($found_assen, ',') > 0) {
				list($found_assen) = explode(',', $found_assen);
			}
			$found_assen = trim($found_assen);
		} else {
			$found_assen = trim($reql->data);
			if (strlen($found_assen) != strlen($bat->assen)) {
				$found_assen = '';
			}
		}

		if (!empty($found_assen) && strtoupper($found_assen) != strtoupper($bat->assen)) {
			$addingtext .= ' (' . $found_assen . ')';
		}
		//var_dump($reql);echo '<br><br>';
		$uemail = $user_reql->user_email;
		if ($reql->user_id > 0) {
			$display_name = trim($user_reql->first_name . ' ' . $user_reql->last_name);
			if (empty($display_name)) {
				$display_name = $user_reql->user_email;
			}
			$uemail =  $onlyarr ? $user_reql->user_email  : ' by <a href="mailto:' . $user_reql->user_email . '" title="' . $user_reql->user_email . '">' . $display_name . '</a>';
		} else if (stripos($reql->user_action, 'auto') > 0) {
			$uemail = '  Auto by System ';
		}

		$timecreate = strtotime($reql->created);
		while (isset($log_result[$timecreate])) {
			$timecreate++;
		}

		if (!empty($user_reql->_signature)) {
			if (file_exists($sign_dir['path'] . '/' . $user_reql->_signature) && filesize($sign_dir['path'] . '/' . $user_reql->_signature) > 0) {
				$addingtext .= ' <span class="signatureimg"><img class="sign_img" src="' . $sign_dir['url'] . '/' . $user_reql->_signature . '?' . time() . '" style="max-height:24px"/></span>';
			}
		}

		if ($onlyarr) {
			$log_result[$timecreate] = array(
				'action' => $r_action,
				'datetime' => $r_date . ' ' . $r_time,
				'by' =>  $uemail,
			);
		} else {
			$log_result[$timecreate] = '<div class="dt" data-id="' . $reql->id . '">' . $r_action . ' ' . $r_date . ' ' . $uemail . ' at ' . $r_time . $addingtext . '</div>';
		}
	}



	if (!isset($bat->assen) || empty($bat->assen)) {
	} else if (!$approved || !$uploaded_data) {



		if ($reportdb && $reportdb->upload_files && !$uploaded_data && $reportdb->estatus !== '' && !empty($reportdb->created)) {
			$a_date = tr_pdd_display_date($reportdb->created);
			$a_time = tr_pdd_display_date($reportdb->created, 'h:i:s A');
			$a_user = '@';
			if ($reportdb->author_id > 0) {
				$r_user = get_user_by('id', $reportdb->author_id);
				$a_user = $r_user->user_email;
			}
			$log_result[strtotime($reportdb->created)] = '<div class="dt">Data Uploaded ' . $a_date . ' by ' . $a_user . ' at ' . $a_time . '</div>';
		}

		if ($reportdb && $reportdb->approve_date && !$approved) {
			$a_date = tr_pdd_display_date($reportdb->approve_date);
			$a_time = tr_pdd_display_date($reportdb->approve_date, 'h:i:s A');
			$a_user = '@';


			if (!empty($bat->batch_status) && tr_pdd_get_batch_statuses($bat->batch_status, true, true, $btype)) {
				$a_user = 'System';
			} else if ($reportdb->author_id > 0) {
				$r_user = get_user_by('id', $reportdb->author_id);
				$a_user = $r_user->user_email;
			}
			$timeapproved = strtotime($reportdb->approve_date);

			if ($timeapproved > 1000) {
				while (isset($log_result[$timeapproved])) {
					$timeapproved++;
				}

				if ($onlyarr) {
					$log_result[$timeapproved] = array(
						'action' => 'Approved',
						'datetime' => $a_date . ' ' . $a_time,
						'by' =>  $a_user,
					);
				} else {
					$log_result[$timeapproved] = '<div class="dt">Approved ' . $a_date . ' by ' . $a_user . ' at ' . $a_time . '</div>';
				}
			}
		}
	}

	//check api for results
	$apinames = array(
		'-18' => 'Telcor',
	);
	$ins_names = array();

	$ins_ids = array(-18);
	foreach ($define_instruments as $mkey => $minfo) {
		if ($minfo['id'] < 0 && tr_get_option('ci_pdf_data', $mkey)) {
			$ins_ids[] = $minfo['id'];
			$ins_names[$minfo['id']] = $minfo['name'];
		}
	}

	$rids_str = implode(',', $ins_ids);

	$logapi_rs = $wpdb->get_results("select mo.*
                            from ci_sync_api_orders mo
                            where mo.mo_mid = {$bat->mid} 
                            and mo.mo_btype = '{$btype}'
                            and mo.mo_file_id in ({$rids_str})
                            order by mo_ID asc");

	foreach ($logapi_rs as $lapi) {
		$title 		= '';
		$is_ins 	= isset($ins_names[$lapi->mo_file_id]);
		$apiname 	= $is_ins ? $ins_names[$lapi->mo_file_id] : $apinames[$lapi->mo_file_id] ?? 'API';
		$datetime 	= $is_ins ? ($lapi->mo_set_ID) : ($lapi->mo_test_ID);
		if (empty($datetime)) {
			continue;
		}
		$timeapproved = strtotime($datetime);
		$a_date 	= tr_pdd_display_date($datetime);
		$a_time 	= tr_pdd_display_date($datetime, 'h:i:s A');
		$action 	= $is_ins ? 'Received Results' : 'API Uploaded';
		$addingtext = '';



		if ($onlyarr) {
			$log_result[$timeapproved] = array(
				'action' => $action,
				'datetime' => $a_date . ' ' . $a_time,
				'by' => $apiname
			);
		} else {
			$addingtext .= ' ( <a class="thickbox" title="' . $title . '" href="' . Tr_Base_Class_V6::link(array('trajax' => 'load_last_change_rs', 'moid' => $lapi->mo_ID, 'lastid' => $has_last_data), false) . '"> view changes</a> ) ';
			$log_result[$timeapproved] = '<div class="dt">' . $action . ' ' . $a_date . ' to ' . $apiname . ' at ' . $a_time . $addingtext . '</div>';
		}
	}

	//check received result by web api
	$rsw = $wpdb->get_results("select wl_at,wl_ID ,wsdl_ref_lab_ID
	from ci_wsdl_log wl
	JOIN ci_wsdl_keys wk ON wk.wsdl_ID = wl.wl_ID and wk.wsdl_ref_lab_ID > 0 and wsdl_url = ''
	where wl.wl_key = '{$bat->assen}' 
	and wl.wl_type = 'put_result_{$btype}' ");

	foreach ($rsw as $lapi) {
		$timeapproved = strtotime($lapi->wl_at);
		$a_date = tr_pdd_display_date($lapi->wl_at);
		$a_time = tr_pdd_display_date($lapi->wl_at, 'h:i:s A');
		$action = 'Received Data';
		$apiname = 'RefLab';
		$addingtext = '';

		if ($onlyarr) {
			$log_result[$timeapproved] = array(
				'action' => $action,
				'datetime' => $a_date . ' ' . $a_time,
				'by' => $apiname
			);
		} else {
			//$addingtext .= ' ( <a class="thickbox" title="' . $title . '" href="' . Tr_Base_Class_V6::link(array('trajax' => 'load_last_change_rs', 'id' => $reql->id, 'lastid' => $has_last_data), false) . '"> view changes</a> ) ';
			$log_result[$timeapproved] = '<div class="dt"> ' . $action . ' from ' . $apiname . ' at ' . $a_date . ' ' . $a_time . $addingtext . '</div>';
		}
	}

	ksort($log_result);

	if ($onlyarr) {
		$logs['results'] = $log_result;
	} else {
		$logs['results'] = implode(' ', $log_result);
	}



	//check State upload
	$logs['state_upload'] = '';
	if (isset($bat->assen) && !empty($bat->assen)) {
		$state_upload = $wpdb->get_var("select date_report from ci_hub_statistics where assen = '{$bat->assen}'");
		if ($state_upload) {
			$a_date = tr_pdd_display_date($state_upload);
			$a_time = tr_pdd_display_date($state_upload, 'h:i:s A');

			$logs['state_upload'] = '<div class="dt">Sent to ' . $bat->pa_state . ' ' . $a_date . ' at ' . $a_time . '</div>';
		}
	}


	return $logs;
}
