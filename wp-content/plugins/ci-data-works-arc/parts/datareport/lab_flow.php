<?php
global $wpdb;

$date_from = isset($_REQUEST['date_from'])? $_REQUEST['date_from']: '';
$date_to   = isset($_REQUEST['date_to'])? $_REQUEST['date_to'] :'';

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );


$results = array();
if($date_from && $date_to)
{
	$where = " where ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
	$where.= " and ba.cli_ID > 0 ";

	$batch_statuses = tr_pdd_get_batch_statuses(false,true);
	$rejected_status = array();
	foreach ($batch_statuses as $k => $vl) {
		$rejected_status[] = $k;
	}
	$rejected_status = implode("','", $rejected_status);



	$rows = array();
	foreach(tr_pdd_get_batch_types() as $btype)
	{
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$sql = "
			select count(*) as count_req,
				sum(IF(ba.doc>'2000-01-01',1,0)) as count_doc,
				sum(IF(ba.spec_received>'2000-01-01',1,0)) as count_rec,
				sum(IF(re.estatus='C',1,0)) as count_rs,
				SUM(IF(batch_status in ('{$rejected_status}'), 1, 0)) as count_rejected
            from {$batch_tbl} ba 
            LEFT JOIN {$report_tbl} re ON ba.assen = re.patient_id
             {$where} ";

		$row = $wpdb->get_row($sql);
		if($row && $row->count_req>0)
		{
			$rows[$btype] = array(
				'count_req' => $row->count_req,
				'count_doc'   => $row->count_doc,
				'count_rec'    => $row->count_rec,
				'count_rs'    => $row->count_rs,
				'count_rejected'    => $row->count_rejected,
			);
		}

	}

}


?>
<div class="wrap">
	<h2>Lab Flow</h2>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab']: ''?>"/>
		<label>From: </label>
		<input type="text" name="date_from" id="from" value="<?php echo $date_from?>"/>
		<label>To: </label>
		<input type="text" name="date_to" id="to" value="<?php echo $date_to?>"/>

		<input type="submit" value="Run Report" class="button bsty6"/>
	</form>
	<br>

	<table class="report_tbl">
		<thead>
		<tr>
			<th class="center" style="width:80px">Type</th>
			<th class="center">Requisitions</th>
			<th class="center">Collected</th>
			<th class="center">Received</th>
			<th class="center">Resulted</th>
			<th class="center">Rejected</th>
		</tr>
		</thead>
		<tbody>
		<?php
		if(isset($rows) && count($rows)>0) {
			$total = array();
			foreach ( $rows as $btype=> $row ) {
				$total['count_req']+= $row['count_req'];
				$total['count_doc']+= $row['count_doc'];
				$total['count_rec']+= $row['count_rec'];
				$total['count_rs']+= $row['count_rs'];
				$total['count_rejected']+= $row['count_rejected'];
				?>
				<tr class="childp">
					<td><?php echo ucfirst($btype)?></td>
					<td class="center"><?php echo $row['count_req'] ?></td>
					<td class="center"><?php echo $row['count_doc'] ?></td>
					<td class="center"><?php echo $row['count_rec'] ?></td>
					<td class="center"><?php echo $row['count_rs'] ?></td>
					<td class="center"><?php echo $row['count_rejected'] ?></td>
				</tr>
				<?php
			}

			?>
			<tr>
				<td>Total</td>
				<td class="center"><?php echo $total['count_req'] ?></td>
				<td class="center"><?php echo $total['count_doc'] ?></td>
				<td class="center"><?php echo $total['count_rec'] ?></td>
				<td class="center"><?php echo $total['count_rs'] ?></td>
				<td class="center"><?php echo $total['count_rejected'] ?></td>
			</tr>
			<?php
		}
		?>


		</tbody>
	</table>

</div>


<script>
    jQuery(function ($) {
        $("#from").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function (selectedDate) {
                $("#to").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#to").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function (selectedDate) {
                $("#from").datepicker("option", "maxDate", selectedDate);
            }
        });
    })
</script>