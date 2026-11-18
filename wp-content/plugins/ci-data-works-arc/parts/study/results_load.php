<?php
global $wpdb, $current_role;


$user_id	= get_current_user_id();
$studyid	= $_REQUEST['studyid'];
$search		= isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
$time_show_dl = strtotime('2016-05-16');
$today 		= Tr_Base_Class_V6::date('Y-m-d');
$admin_view = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);
$pending    = isset($_REQUEST['pending']);
$only_view  = false;
$can_delete	= true;
$can_edit	= true;
$rejected_status        = tr_pdd_get_batch_statuses(false, true, true, 'study');
$result_dir = tr_get_result_upload_dir();
$is_test_results    = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';

$where = " and ba.project_ID = '{$studyid}' ";
$where .= " and ba.batch_status != 'archived' ";

if (!empty($search)) {
	$where .= " and ba.assen like '{$search}%' ";
}

if ($pending) {
	$where .= " and (re.id is null or re.estatus !='C' or re.estatus is null )";
}

$orderby = ' order by ba.position asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = '';



$sql = "select
        re.id,
        re.estatus,
        re.created,
        re.report_ref,
        re.box_uploaded,
        re.approve_date,
        re.final_results,
        re.instrument,
        re.faxed,
        ba.doc,
        ba.mid,
        ba.batch_id,
        ba.assen,
        ba.patient_name,
        ba.panel_tests_requested,
        ba.type_of_specimen,
        ba.position,
        ba.batch_status,
		ba.phase,
		ba.timeframe,
		ba.conditions,
		ba.project_ID,
		reb.patientid,
		reb.sampleid,
		reb.study_assigned,
		reb.timepoint,
		reb.condition
        
    from ci_batches_study ba
	left join ci_receiving_study_batches reb ON reb.ID = ba.receivingID
    left JOIN ci_reports_study re ON ba.assen = re.patient_id
    where 1 {$where} group by ba.mid {$orderby}";





$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_GET['debug1'])) {
	echo '<div>';
	var_dump($wpdb->last_error);
	echo '<br><br>' . "\n\n";
	echo microtime(true) - $time_start;
	echo '<br><br>' . "\n\n";
	echo $sql;
	echo '<br><br>' . "\n\n";
	echo '</div>';
	exit;
}

echo '<table class="report_tbl child_list" data-total="' . $count_rows . '">';

$last_position = 0;
$count_rows = 0;
$approved_ids = array();

foreach ($results as $i => $row) {

	$update = array();
	$mstatus = '';
	$status_class = '';
	$row_class = '';

	$study_assigneds = explode(',', $row->study_assigned);
	$timepoints = explode(',', $row->timepoint);

	foreach ($study_assigneds as $i2 => $prid) {
		if ($prid == $row->project_ID) {
			$row->timepoint = $timepoints[$i2];
			break;
		}
	}


	if ($row->faxed) {
		$mstatus .= '<span class="ricon faxed" title="Faxed">F</span>';
	}

	if (isset($rejected_status[$row->batch_status])) {
		$mstatus .= '<span class="ricon rejected" title="Rejected">X</span>';
	}

	if (empty($row->doc) || $row->doc == '0000-00-00 00:00:00') {
		$row->doc = $row->collection_date;
	}

	//insert if no id
	if ($row->id == 0 && !empty($row->assen)) {
		$report_data = array(
			'patient_id' => $row->assen,
			'batch_id' => $row->batch_id,
			'created' => date_i18n('Y-m-d H:i:s'),
			'created_date' => $today,
		);
		if (isset($rejected_status[$row->batch_status])) {
			$report_data['estatus'] = 'C';
			$report_data['approve_date'] = date_i18n('Y-m-d H:i:s');
			$row->estatus = 'C';
		}
		$wpdb->insert('ci_reports_study', $report_data);
		$row->id = $wpdb->insert_id;
	} else if (isset($rejected_status[$row->batch_status]) && !in_array($row->estatus, array('C', 'M'))) {
		$update['estatus'] = 'C';
		$update['approve_date'] = date_i18n('Y-m-d H:i:s');
		$row->estatus = 'C';
	}


	if (count($update) > 0) {
		$wpdb->update('ci_reports_study', $update, array('id' => $row->id));
		$count_updated++;
	}

	if ($row->position == 0) {
		$last_position++;
	} else {
		$last_position = $row->position;
	}

	$text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
	$count_rows++;
	$row_class .= ' bs_' . $row->batch_status;
	if ($row->covid_pos) {
		$row_class .= ' has_pos';
	}
?>
	<tr class="childp  bs_<?php echo $row_class ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>">
		<td style="width:40px"></td>
		<td style="width: 40px;"><?php echo $row->position ?></td>
		<td style="width: 80px;"><a href="admin.php?page=ci_batches_view&act=edit&id=study_<?php echo $row->mid ?>&short=1" target="_blank"><?php echo $row->assen ?></a></td>
		<td style="width: 10%;"><?php echo $row->patientid ?></td>
		<td style="width: 30px"><?php echo $row->phase ?></td>
		<td style="width: 10%;"><?php echo $row->timeframe ?></td>
		<td style="width: 10%"><?php echo str_replace(';', '<br>', $row->conditions) ?></td>
		<td style="width: 80px;"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
		<td style="width: 80px;"><?php echo tr_pdd_get_result_status($row->final_results) ?></td>
		<?php if ($is_test_results) { ?>
			<td style="width: 10%"><?php echo $row->batch_status ?></td>
		<?php } ?>
		<td style="width: 100px" class="mstatus"><?php echo $mstatus ?></td>
		<td class="cactions aright">
			<?php if (!$only_view) {
				if ($can_edit) {

					$text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
			?>
					<span class="approvediv"><a class="approvebtn bsty4" data-onlyapprove="<?php echo $only_approve ? 1 : 0 ?>" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a></span>

					<span class="editlink" <?php if ($row->estatus == 'C' && !$admin_view) echo 'style="display:none"' ?>>
						<a class="bsty4" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
					</span>
				<?php
				}

				if ($can_delete && $row->estatus != 'C') {
				?>

					<a class="pdelete bsty6" href="#" data-id="<?php echo $row->id ?>">Delete</a>

				<?php
				}
				?>
			<?php } ?>
		</td>
	</tr>
<?php

}

if (count($results) == 0 || $count_rows == 0) {
	echo '<tr class="childp"><td colspan=5><p class="center">Not found</p></td></tr>';
}

echo '</table>';
