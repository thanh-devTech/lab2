<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();


$batch_id = $_REQUEST['batch_id'];

$where = " and batch_id !='0' and batch_id !='' ";
if (!empty($batch_id)) {
	$where .= " and batch_id = '{$batch_id}' ";
}

if (tr_current_role_in(array('office_manager', 'clinic'))) {

	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where .= " and ba.cli_ID = {$current_clinic} ";
	} else {
		$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} ");
		$cli_ids = implode(',', $cli_ids);

		$where .= " and ba.cli_ID in ( {$cli_ids} )";
	}
} else if (tr_current_role_in(array('multi_site_manager'))) {

	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where .= " and ba.cli_ID = {$current_clinic} ";
	} else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
		//can see all clinics
	} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
		$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
		if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
			$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
			update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
		}
		$ms_cli_ids_str = implode(',', $ms_cli_ids);
		$where .= " and ba.cli_ID in ( {$ms_cli_ids_str} )";
	}
} else if ($current_role == 'doctor') {
	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where .= " and ba.cli_ID = {$current_clinic} ";
	}
	$doctor_id = get_user_meta($user_id, '_doc_id', true);
	$where .= " and ba.doc_ID = {$doctor_id} ";
} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
	$show_doctor = true;
	$rep_id = get_user_meta($user_id, '_rep_id', true);
	$clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` = {$rep_id}");
	$clinics = implode(',', $clinics);
	$where .= " and ba.cli_ID in  ({$clinics}) ";
} else if ($current_role == 'distributor') {
	$dis_ID = $wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
	$reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ");


	$reps = implode(',', $reps);
	$clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
	$clinics = implode(',', $clinics);
	$where .= " and ba.cli_ID in ({$clinics}) ";
} else if ($current_role == 'collector') {
	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where .= " and ba.cli_ID = {$current_clinic} ";
	}
	$col_id = get_user_meta($user_id, '_collector_id', true);
	$clinics = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
	$clinics = implode(',', $clinics);
	$where .= " and ba.cli_ID in ({$clinics}) ";
}


$total_child = array();
$count_child = array();
$sql_child = array();
foreach (tr_pdd_get_batch_types() as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$total_child[] = " (SELECT distinct(batch_id) as batch_id FROM {$batch_tbl} ba where 1 {$where} )";
	$count_child[] = " (SELECT batch_id, count(*) as count FROM {$batch_tbl} ba where 1 {$where} group by batch_id)";
	$sql_child[] = " (
						SELECT ba.assen, ba.patient_name, pa.pa_mrn from {$batch_tbl} ba
						LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
						WHERE 1 {$where}
						) ";
}
$total_child = implode(' UNION ', $total_child);
$count_child = implode(' UNION ', $count_child);
$sql_child = implode(' UNION ', $sql_child);

if (isset($_REQUEST['trajax'])) {

	if ($_REQUEST['trajax'] == 'load_child') {

		$sql = "SELECT * from ({$sql_child}) as rs";
		$results = $wpdb->get_results($sql);
		//var_dump($sql);
?>
		<table class="report_tbl child_list">
			<tbody>
				<?php
				foreach ($results as $row) {
				?>
					<tr class="childp">
						<td style="width:10px"></td>
						<td style="width:80px"></td>
						<td style="width:15%"><?php echo $row->assen ?></td>
						<td style="width:30%"><?php echo $row->pa_mrn ?></td>
						<td><?php echo $row->patient_name ?></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
<?php
	}
	exit;
}


$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();

$sql_total = "select count(distinct(batch_id)) as c from ({$total_child}) rs";

$total = $wpdb->get_var($sql_total);

$pagination->setTotal($total);


$sql = "SELECT 
		batch_id, 
		SUM(count) as count 
		 FROM ({$count_child}) rs
		 GROUP BY batch_id
		ORDER BY batch_id desc
		LIMIT {$offset}, {$row_per_page} ";

$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);

?>
<div class="wrap">
	<h2>Received List</h2>

	<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
			<thead class="sticky">
				<tr>
					<th style="width:20px">#</th>
					<th style="width: 80px;">Batch ID</th>
					<th style="width: 15%;">Accession#</th>
					<th style="width:30%">MRN</th>
					<th>Patient Name</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($results as $row) {
				?>
					<tr class="parent" data-p="<?php echo $row->batch_id ?>">
						<td><i></i></td>
						<td colspan="3"><?php echo $row->batch_id ?></a></td>
						<td class="right"><?php echo $row->count ?></td>
					</tr>
					<tr class="child child_<?php echo $row->batch_id ?>">
						<td colspan="5" class="nopadding"></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</div>
	<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
</div>

<script>
	jQuery(function($) {
		function trcpp_load_results($batch_id, $obj) {
			$obj.html('<div class="loading center"></div>');
			$.ajax({
				data: {
					trajax: 'load_child',
					batch_id: $batch_id
				},
				type: 'post',
				success: function(rs) {
					var $html = $(rs);
					$obj.html($html);
				},
				error: function(c) {
					trcpp_load_results($batch_id, $obj);
				}
			})
		}
		$('tr.parent').click(function(e) {
			if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
			$('tr.child').hide();
			var $batch_id = $(this).data('p');
			if ($(this).data('showed') != '1') {
				var $pr = $('tr.child_' + $batch_id);
				$pr.show();
				$('tr.parent').data('showed', '0');
				$(this).data('showed', '1');
				$(this).addClass('active');
				$('body,html').scrollTop($(this).offset().top - 50);
				var $tdchild = $('>td', $pr);
				if ($tdchild.html() == '') {
					trcpp_load_results($batch_id, $tdchild);
				}

			} else {
				$('tr.child_' + $batch_id).hide();
				$(this).data('showed', '0');
				$(this).removeClass('active');
			}
			return false;
		});
	})
</script>