<?php
global $wpdb;

$summary = isset($_REQUEST['summary'])? $_REQUEST['summary']: '';
$date_from = isset($_REQUEST['date_from'])? $_REQUEST['date_from']: '';
$date_to   = isset($_REQUEST['date_to'])? $_REQUEST['date_to'] :'';

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );


$results = array();
if($date_from && $date_to)
{
	$where = " where ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
	$where.= " and cli.cli_report_by_collector = 1 and col.col_middle!='' ";

	$child_q = array();
	foreach(tr_pdd_get_batch_types() as $btype)
	{
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$child_q[] = "(SELECT
			cli.cID,
		    cli.cli_clinic,
		    col.col_ID,
			col.col_middle,
			DATE_FORMAT(ba.doc,'%Y-%m-%d') AS doc_date,
            count(*) as count1
           
            from {$batch_tbl} ba
            JOIN ci_collector col ON col.col_ID = ba.col_ID
            JOIN ci_clinic cli ON cli.cId = ba.cli_ID
             {$where}
            GROUP BY cli.cID, col.col_ID, doc_date)";
	}
	$child_q = implode(" UNION ALL ", $child_q);
	$sql = "
	    select *,
	        sum(count1) as count_all
            from ({$child_q}) ba
         group by cID, col_ID, doc_date
         order by doc_date asc
         ";
	$results = $wpdb->get_results($sql);

	$clinics = array();
	foreach($results as $row)
	{
		if(!isset($clinics[$row->cID])){
			$clinics[$row->cID]= array(
				'name' => $row->cli_clinic,
				'rows' => array()
			);
		}

		if(!isset($clinics[$row->cID]['rows'][$row->col_ID]))
		{
			$clinics[$row->cID]['rows'][$row->col_ID] = array(
				'name' => $row->col_middle,
				'count' => 0,
				'rows' => array()
			);
		}

		$clinics[$row->cID]['rows'][$row->col_ID]['rows'][] = $row;
		$clinics[$row->cID]['rows'][$row->col_ID]['count']+= $row->count_all;
	}

	if(isset($_GET['debug'])){
		var_dump($sql);
		echo '<br><br>';
		var_dump($results);
	}
}


?>
<div class="wrap">
	<h2>Collectors Report</h2>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab']:''?>"/>
		<label>From: </label>
		<input type="text" name="date_from" id="from" value="<?php echo $date_from?>"/>
		<label>To: </label>
		<input type="text" name="date_to" id="to" value="<?php echo $date_to?>"/>

		<label><input type="checkbox" name="summary" value="1" <?php checked($summary)?>/> Summary </label>

		<input type="submit" value="Run Report" class="button bsty6"/>
	</form>
	<br>

	<?php
	if(isset($clinics))
	{
		if(count($clinics)>0)
		{
			foreach($clinics as $cid => $arr)
			{
				?>
				<table class="clinic" style="width:250px;margin-bottom:30px">
					<tr>
						<th colspan="2" style="text-align: left"><?php echo $arr['name']?></th>
					</tr>
					<?php
					foreach($arr['rows'] as $colid => $col_arr)
					{
						echo '<tr><td>'.$col_arr['name'].'</td><td>'.$col_arr['count'].'</td></tr>';

						if(!$summary)
						{
							foreach($col_arr['rows'] as $row)
							{
								echo '<tr><td>'.$row->doc_date.'</td><td>'.$row->count_all.'</td></tr>';
							}
							echo '<tr><td colspan=2>&nbsp;</td></tr>';
						}

					}
					?>
				</table>
				<?php
			}
		}else{
			echo '<p class="center"> Not found</p>';
		}

	}
	?>



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