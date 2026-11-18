<?php

global $wpdb;
global $current_role;


$user_id    = get_current_user_id();
$cls_id     = array();
$is_prefill_covid = true;

$btype		= isset($btype) && !empty($btype) ? $btype : 'pcr';
$btype_code = array_search($btype, tr_pdd_get_batch_types());
$table_batch = tr_pdd_get_table_by_btype($btype);
$report_table = tr_pdd_get_table_report_by_btype($btype);


$conditions = trpdd_get_batch_conditions(0, $btype);
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = '1 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where      .= $return['where_author'];
}


$where .= " and batch_type = 'I' and deleted = 0 ";
if ($is_bulk) {
	$where .= " and ba.is_dna = {$btype_code} ";
}

if (!empty($_REQUEST['s'])) {
	$search = stripslashes($_REQUEST['s']);
	if (strpos($_REQUEST['s'], ',') > 0) {
		list($firstname, $lastname) = explode(',', $_REQUEST['s']);
		$firstname = trim($firstname);
		$lastname = trim($lastname);
		$explodeString = "%{$firstname}%{$lastname}%";
		$where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
	} else {
		$where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen like %s)", '%' . $search . '%', $search . '%');
	}
}


if ($act == 'delete' && $_GET['id'] > 0) {
	$wpdb->update('ci_saved_batches', array('deleted' => '1'), array('mid' => $_GET['id']));

	echo 'Deleted Successfully!';
	Tr_Session_Class::add('Deleted Successfully!');
	$url = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);
	echo '<meta http-equiv="refresh" content="1;url=' . $url . '" />';
	exit;
} else if ($act == 'submit') {
	$id = $_GET['id'];
	$batch_id = tr_pdd_get_current_batch_id();
	//check exists
	$bat_saved = $wpdb->get_row("select 
                mid,
                assen,
                doc,
                batch_type,
                lab_id,
                ref_lab_ID,
                patient_id,
                patient_name,
                insurance1,
                insurance2,
                insurance,
                panel_tests_requested,
                type_of_specimen,
                author_ID,
                oldreq,
                dob,
                cli_ID,
                requesting_practice,
                doc_ID,
                requesting_physician,
                batch_created,
                priority_hours,
                notes,
                patient_signature,
                dagnostic_codes1,
                notes_report
                
            from ci_saved_batches 
            where mid = {$id} ", ARRAY_A);

	$bat = $wpdb->get_row("select * from {$table_batch} where assen = '{$bat_saved['assen']}'", ARRAY_A);

	$notes_report = $bat_saved['notes_report'];
	unset($bat_saved['mid']);
	unset($bat_saved['notes_report']);

	$has_batch_id = false;

	if ($bat) {
		$mid = $bat['mid'];

		$wpdb->update($table_batch, $bat_saved, array('mid' => $mid));

		$has_batch_id = $bat->batch_id > 0;
	} else {
		$wpdb->insert($table_batch, $bat_saved);
		$mid = $wpdb->insert_id;
	}


	if ($btype == 'tox') {
		//add screening
		$specimen_tbl = 'ci_result_test_speciments';
		$screenings = tr_pdd_get_result_validity(1, $bat_saved['batch_created'], $id, true);
		foreach ($screenings as $arr) {
			$tid = $arr['u_ID'];
			if (!$arr['checked']) continue;
			$update = array(
				'mid' => $mid,
				'testid' => $tid,
				'checked' => 1
			);
			$exists = $wpdb->get_var("select mid from {$specimen_tbl} where mid = {$mid} and testid={$tid} ");
			if ($exists) {
				$wpdb->update($specimen_tbl, $update, array('mid' => $mid, 'testid' => $tid));
			} else {
				$wpdb->insert($specimen_tbl, $update);
			}
		}
	}

	if (!empty($notes_report)) {
		list($groupid, $testdrug, $resultpn) = explode('|', $notes_report);

		if (stripos($resultpn, 'pos') !== false) {
			$resultpn = 'POS';
		} else if (stripos($resultpn, 'neg') !== false) {
			$resultpn = 'NEG';
		} else {
			$resultpn = '';
		}


		if ($groupid > 0 && !empty($testdrug) && !empty($resultpn)) {
			$report = $wpdb->get_row("select * from {$report_table} where patient_id = '{$bat_saved['assen']}'", ARRAY_A);
			if (!$report) {
				$report_data = array(
					'patient_id' => $bat_saved['assen'],
					'created' => date_i18n('Y-m-d H:i:s'),
					'created_date' => date_i18n('Y-m-d'),
					'estatus' => 'C',
					'final_results' => '1',
					'approve_date' => date_i18n('Y-m-d H:i:s')
				);

				$wpdb->insert($report_table, $report_data);

				$report = array('id' => $wpdb->insert_id);
			} else if ($report['estatus'] != 'C') {
				$wpdb->update($report_table, array(
					'estatus' => 'C',
					'approve_date' => date_i18n('Y-m-d H:i:s')
				), array('id' => $report['id']));
			}

			if ($report['id'] > 0) {
				$rsid = $wpdb->get_var("select id from ci_results_pcr 
                                            where report_id = {$report['id']}
                                            and drug = '{$testdrug}'");
				$rs_data = array(
					'report_id' => $report['id'],
					'drug' => $testdrug,
					'conc' => '',
					'pos-neg' => $resultpn,
					'group_id' => $groupid,
				);
				if ($rsid > 0) {
					$wpdb->update('ci_results_pcr', $rs_data, array('id' => $rsid));
				} else {
					$wpdb->insert('ci_results_pcr', $rs_data);
				}

				//auto batch_id
				if (!$has_batch_id) {
					$wpdb->update($table_batch, array(
						'batch_id' => $batch_id
					), array('mid' => $mid));
				}
			}
		}
	}


	$wpdb->update('ci_saved_batches', array('deleted' => '1'), array('mid' => $id));
	echo 'Approved Successfully!';
	Tr_Session_Class::add('Approved Successfully!');
	$url = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);
	echo '<meta http-equiv="refresh" content="1;url=' . $url . '" />';
	exit;
} else if ($act == 'submitall') {
	$mids = trim($_POST['ids'], ' ,');

	$batch_id = tr_pdd_get_current_batch_id();

	$sql = "
            select 
                mid,
                assen,
                doc,
                batch_type,
                lab_id,
                ref_lab_ID,
                patient_id,
                patient_name,
                insurance1,
                insurance2,
                insurance,
                panel_tests_requested,
                type_of_specimen,
                author_ID,
                oldreq,
                dob,
                cli_ID,
                requesting_practice,
                doc_ID,
                requesting_physician,
                batch_created,
                priority_hours,
                notes,
                patient_signature,
                dagnostic_codes1,
                notes_report
                
            from ci_saved_batches ba
            where {$where}
            ";

	if (!empty($mids)) {
		$sql .= " and mid in ({$mids}) ";
	}
	$rs = $wpdb->get_results($sql, ARRAY_A);
	foreach ($rs as $bat_saved) {
		$id = $bat_saved['mid'];
		$notes_report = $bat_saved['notes_report'];

		unset($bat_saved['mid']);
		unset($bat_saved['notes_report']);

		$bat_saved['paid'] = -12;
		$has_batch_id = false;


		$bat = $wpdb->get_row("select * from {$table_batch} where assen = '{$bat_saved['assen']}'", ARRAY_A);

		if ($bat) {
			$mid = $bat['mid'];

			$wpdb->update($table_batch, $bat_saved, array('mid' => $mid));

			$has_batch_id = $bat->batch_id > 0;
		} else {
			$wpdb->insert($table_batch, $bat_saved);
			$mid = $wpdb->insert_id;
		}

		if ($btype == 'tox') {
			//add screening
			$specimen_tbl = 'ci_result_test_speciments';
			$screenings = tr_pdd_get_result_validity(1, $bat_saved['batch_created'], $id, true);
			foreach ($screenings as $arr) {
				$tid = $arr['u_ID'];
				if (!$arr['checked']) continue;
				$update = array(
					'mid' => $mid,
					'testid' => $tid,
					'checked' => 1
				);
				$exists = $wpdb->get_var("select mid from {$specimen_tbl} where mid = {$mid} and testid={$tid} ");
				if ($exists) {
					$wpdb->update($specimen_tbl, $update, array('mid' => $mid, 'testid' => $tid));
				} else {
					$wpdb->insert($specimen_tbl, $update);
				}
			}
		}

		if (!empty($notes_report)) {
			list($groupid, $testdrug, $resultpn) = explode('|', $notes_report);
			if (stripos($resultpn, 'pos') !== false) {
				$resultpn = 'POS';
			} else if (stripos($resultpn, 'neg') !== false) {
				$resultpn = 'NEG';
			} else {
				$resultpn = '';
			}

			if ($groupid > 0 && !empty($testdrug) && !empty($resultpn)) {
				$report = $wpdb->get_row("select * from {$report_table} where patient_id = '{$bat_saved['assen']}'", ARRAY_A);
				if (!$report) {
					$report_data = array(
						'patient_id' => $bat_saved['assen'],
						'created' => date_i18n('Y-m-d H:i:s'),
						'created_date' => date_i18n('Y-m-d'),
						'estatus' => 'C',
						'final_results' => '1',
						'approve_date' => date_i18n('Y-m-d H:i:s')
					);

					$wpdb->insert($report_table, $report_data);
					$report = array('id' => $wpdb->insert_id);
				} else if ($report['estatus'] != 'C') {
					$wpdb->update($report_table, array(
						'estatus' => 'C',
						'approve_date' => date_i18n('Y-m-d H:i:s')
					), array('id' => $report['id']));
				}

				if ($report['id'] > 0) {
					$rsid = $wpdb->get_var("select id from ci_results_pcr 
                                            where report_id = {$report['id']}
                                            and drug = '{$testdrug}'");
					$rs_data = array(
						'report_id' => $report['id'],
						'drug' => $testdrug,
						'conc' => '',
						'pos-neg' => $resultpn,
						'group_id' => $groupid,
					);
					if ($rsid > 0) {
						$wpdb->update('ci_results_pcr', $rs_data, array('id' => $rsid));
					} else {
						$wpdb->insert('ci_results_pcr', $rs_data);
					}

					//auto batch_id
					if (!$has_batch_id) {
						$wpdb->update($table_batch, array(
							'batch_id' => tr_pdd_get_current_batch_id(),
						), array('mid' => $mid));
					}
				}
			}
		}

		$wpdb->update('ci_saved_batches', array('deleted' => '1'), array('mid' => $id));
	}


	Tr_Session_Class::add('Approved Successfully!');
} else if ($act == 'deleteall') {

	$mids = trim($_POST['ids'], ' ,');


	$sql = "update ci_saved_batches ba set deleted = 1 where {$where}";
	if (!empty($mids)) {
		$sql .= "  and mid in ({$mids}) ";
	}
	$wpdb->query($sql);
	Tr_Session_Class::add("Deleted Successfully!");
} else if ($act == 'export_lastassen') {

	$file_name = 'last_accessions.csv';

	$sql = "select
        ba.assen,
        ba.mid,
        ba.doc,
        ba.requesting_physician,
        ba.patient_name,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic,
        pa.pa_dob as dob
        from ci_saved_batches ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        join ci_patients pa ON pa.pa_ID = ba.patient_id
        where {$where} order by doc desc";

	if (isset($_REQUEST['debug'])) {
		echo $sql;
	}
	$results = $wpdb->get_results($sql);


	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	$f = fopen('php://output', 'w');

	fputcsv($f, $headers);

	foreach ($results as $row) {
	}
	exit;
}



$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c 
        from ci_saved_batches ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.assen,
        ba.oldreq,
        ba.mid,
        ba.doc,
        ba.requesting_physician,
        ba.patient_name,
        if(ins.ins_company is not null, ins.ins_company,ba.insurance) as ins_company,
        cli.cli_clinic,
        pa.pa_dob as dob
        from ci_saved_batches ba
        left join ci_insurances ins ON ins.ins_ID = ba.insurance1
        left join ci_clinic cli ON cli.cID = ba.cli_ID
        left join ci_patients pa ON pa.pa_ID = ba.patient_id
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
	echo $sql;
}
$results = $wpdb->get_results($sql);


include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
<h2>Req List <?php echo tr_pdd_get_typename($btype) ?></h2>
<?php Tr_Session_Class::show() ?>



<div class="tablenav">

	<form method="get" style="float:left;margin-right: 10px;">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
		<label>Name/Accession:</label>
		<input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? stripslashes($_REQUEST['s']) : '' ?>" />
		<input type="submit" class="button bsty6" value="Search" />
	</form>

	<a href="#" class="button left " style="margin-right:15px;" id="print_barcodes_btn">Print Barcodes</a>
	<span class="print_assen_btn"></span>
	<?php if ($pagination) echo $pagination->pagination() ?>
</div>
<div class="wraptbl">
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:20px"><input type="checkbox" data-td="col-mid" class="checkall" /></th>
				<th style="width: 50px">#</th>
				<th>Accession Number</th>
				<th>Ref Number</th>
				<th>PRACTICE</th>
				<th>ORDERING PHYSICIAN</th>
				<th>Patient</th>
				<th>DOB</th>
				<th>INSURANCE</th>
				<th style="width:75px">Date</th>
				<th style="width:185px"><a class="button submitall bsty5" href="#">Approve All</a>
					&nbsp;<a class="button deleteall bsty6" href="#">Delete All</a>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php

			$i = ($pagination->get_paged() - 1) * $row_per_page + 1;
			foreach ($results as $row) : ?>
				<tr data-panels_print="">
					<td class="col-mid"><input type="checkbox" value="<?php echo $row->mid ?>" /></td>
					<td><?php echo $i++ ?></td>
					<td class="assen"><?php echo $row->assen ?></td>
					<td class="oldreq"><?php echo $row->oldreq ?></td>
					<td><?php echo $row->cli_clinic ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td class="col-patient_name"><?php echo $row->patient_name ?></td>
					<td class="col-dob"><?php echo $row->dob ?></td>
					<td><?php echo $row->ins_company ?></td>
					<td><?php echo $row->doc ?></td>
					<td class="cactions">
						<a href="<?php Tr_Base_Class_V6::link(array('act' => 'submit', 'id' => $row->mid)) ?>">Approve</a>
						<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => 'saved_form', 'btype' => $btype)) ?>">Edit</a>
						<a class="delete bsty6" data-assen="<?php echo $row->assen ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->mid)) ?>">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

<form method="post" id="submitform" style="display: none;">
	<input type="hidden" id="submitform_ids" name="ids" value="" />
	<input type="hidden" id="submitform_act" name="act" value="" />
</form>
<script>
	jQuery(function($) {
		$('body').on('click', 'td.col-mid input[type="checkbox"],.checkall', function() {
			setTimeout(function() {
				var $len = $('td.col-mid input[type="checkbox"]:checked').length;
				if ($len > 0) {
					$('.deleteall').html('Delete Selected');
					$('.submitall').html('Approve Selected');
				} else {
					$('.deleteall').html('Delete All');
					$('.submitall').html('Approve All');
				}
			}, 500);
		}).on('click', '.submitall', function() {
			var $len = $('td.col-mid input[type="checkbox"]:checked').length;
			if (confirm('Are you sure you want to approve ' + ($len > 0 ? '' : 'all') + '?') == false) return false;
			$ids = '';
			$('td.col-mid input[type="checkbox"]:checked').each(function() {
				$ids += $(this).val() + ',';
			})
			$('#submitform_ids').val($ids);
			$('#submitform_act').val('submitall');
			$('#submitform').submit();

			return false;
		}).on('click', '.deleteall', function() {
			var $len = $('td.col-mid input[type="checkbox"]:checked').length;
			if (confirm('Are you sure you want to delete ' + ($len > 0 ? '' : 'all') + '?') == false) return false;
			$ids = '';
			$('td.col-mid input[type="checkbox"]:checked').each(function() {
				$ids += $(this).val() + ',';
			})
			$('#submitform_ids').val($ids);
			$('#submitform_act').val('deleteall');
			$('#submitform').submit();

			return false;
		}).on('click', 'a.delete', function() {
			if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
		})
	})
</script>