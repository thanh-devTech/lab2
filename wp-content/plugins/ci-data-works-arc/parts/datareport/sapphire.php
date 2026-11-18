<?php
global $wpdb;
include_once(TRCPDD_PATH.'inc/functions_rack_plate.php');

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );
wp_enqueue_script( 'jquery-validate', TRCPDD_URL. 'admin/js/jquery.validate.min.js',array('jquery'),true );
wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );


$rack_selected = isset($_GET['rack'])? $_GET['rack']:1;
$date_selected = isset($_GET['date'])? $_GET['date']:'';
$results       = array();
$rack          = new stdClass();
if($rack_selected>0 && $date_selected!=''){
	$sql_rack = "select * from ci_racks where re_today='{$date_selected}' and re_number = '{$rack_selected}'";
	$rack   = $wpdb->get_row($sql_rack);
	$child_sql = array();
	foreach(tr_pdd_get_batch_types() as $btype)
	{
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$child_sql[] = "(select 
						ba.requesting_practice,
                        ba.assen,
                        ba.doc,
                        ba.patient_name,
                        ba.patient_id,
                        rb.*
                        from  ci_rack_batches rb 
                          JOIN {$batch_tbl} ba ON rb.mid=ba.mid 
                            where rb.re_ID = '{$rack->re_ID}' and rb.btype='{$btype}' ) ";
	}

	$child_sql = implode(' UNION ALL ',$child_sql);

	$sql = "select * from ({$child_sql}) as ball
			join ci_patients pa ON ball.patient_id = pa.pa_ID
                                order by ball.ID asc";
	$results = $wpdb->get_results($sql);

	if(count($results)==0)
	{
		Tr_Session_Class::reset();
		Tr_Session_Class::add_error("Not found!");
	}
}

if(isset($_REQUEST['trajax'])){

	if(count($results)>0)
	{
		$headers = array(
			'Well',
				'Accession',
				'Age',
				'Gender',
				'Race',
				'Ethnicity',
				'Zip Code',
				'DOC',
				'Clinic Name',
			);
		header('content-type:text/csv');
		header("Content-disposition: attachment; filename=sapphire.csv");
		$file = fopen('php://output','w');
		fputcsv($file,$headers);
		foreach($results as $j => $row)
		{
			$row->pa_age = trdd_dob2ages($row->pa_dob);
			$welld = tr_rack_well_by_position($j, $rack->re_is_uti_panel);
			$data = array(
				$welld,
				$row->assen,
				$row->pa_age,
				$row->pa_gender,
				$row->pa_race,
				$row->pa_ethnicity,
				$row->pa_zip,
				$row->doc,
				$row->requesting_practice
			);
			fputcsv($file,$data);
		}

	}else{
		wp_redirect(Tr_Base_Class_V6::link(array('trajax'=>''),false));
	}
	exit;
}
?>
<div class="wrap">
	<h2>Rack Report</h2>
	<?php Tr_Session_Class::show()?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
		<input type="search" name="date" class="hasdate" value="<?php echo $date_selected?>"/>
		<select name="rack">
			<?php
			for($i=1;$i<=10;$i++)
			{
				$selected = $rack_selected == $i ? 'selected':'';
				echo '<option value="'.$i.'" '.$selected.'>Rack #'.$i.'</option>';
			}
			?>
		</select>
		<input type="submit" class="button-primary" value="Search"/>
		<input type="submit" class="button" name="trajax" value="Export"/>
	</form>

	<?php
	if(!empty($results))
	{
		?>
		<table class="posts report_tbl batch_tbl subretbl">
			<thead>
			<tr>
				<th>Well</th>
				<th>Accession</th>
				<th>Age</th>
				<th>Gender</th>
				<th>Race</th>
				<th>Ethnicity</th>
				<th>Zip Code</th>
				<th>DOC</th>
				<th>Clinic Name</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach($results as $j=>$row)
			{
				$row->pa_age = trdd_dob2ages($row->pa_dob);
				$welld = tr_rack_well_by_position($j, $rack->re_is_uti_panel);
				?>
				<tr class="childp">
					<td><?php echo $welld?></td>
					<td><?php echo $row->assen?></td>
					<td><?php echo $row->pa_age?></td>
					<td><?php echo $row->pa_gender?></td>
					<td><?php echo $row->pa_race?></td>
					<td><?php echo $row->pa_ethnicity?></td>
					<td><?php echo $row->pa_zip?></td>
					<td><?php echo $row->doc?></td>
					<td><?php echo $row->requesting_practice?></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}
	?>


</div>