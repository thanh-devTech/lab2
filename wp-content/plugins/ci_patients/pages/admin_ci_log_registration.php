<?php
global $wpdb;
$s = isset($_GET['s'])? $_GET['s']:'';
$where = "";
if(!empty($s)){
    $where.= " and ast.meta_value like '%{$s}%' ";
}

$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var(
            "select 
                    count(*) from {$wpdb->postmeta} rt
                    join {$wpdb->postmeta} ast ON ast.post_id = rt.post_id and ast.meta_key='_accessions'
                    where rt.meta_key='_restration_covid_testing' 
                    {$where}"
));
$sql = "
	select 
	 rt.*,
	 ast.meta_value as accessions,
	 pa.pa_ID,
	 pa.pa_first,
	 pa.pa_last,
	 pa.pa_phone,
	 p.post_date as created
	 from {$wpdb->postmeta} rt
	 join {$wpdb->postmeta} ast ON ast.post_id = rt.post_id and ast.meta_key='_accessions'
	 join {$wpdb->postmeta} pid ON pid.post_id = rt.post_id and pid.meta_key='_patient_id'
	 join {$wpdb->posts} p ON p.ID = rt.post_id
	 left join ci_patients pa ON pa.pa_ID = pid.meta_value
	 where rt.meta_key='_restration_covid_testing'
	 {$where}
	order by rt.post_id desc
	limit {$offset},{$row_per_page}";
$rows = $wpdb->get_results($sql);

?>

<div class="wrap">
	<h2>Log</h2>
	<?php Tr_Session_Class::show()?>

	<div class="tablenav">
        <form method="get" class="left">
            <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
            <label>Search</label>
            <input type="search" name="s" value="<?php echo $_GET['s']?>"/>
            <input type="submit" class="button" value="Search"/>
        </form>
        <?php if(isset($pagination))echo $pagination->pagination(array('m')) ?>
    </div>

	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
			<thead>
			<tr>
				<th style="width:140px">Date/Time</th>
				<th  style="width:200px">Patient Name</th>
				<th  style="width: 100px">Phone Number</th>
				<th  style="width: 100px">Accession</th>
				<th>Text</th>
			</tr>
			</thead>

			<tbody>
			<?php
			foreach($rows as $row)
			{
				$text = '';
				$tests = array();
				$json = maybe_unserialize($row->meta_value);

				foreach($json['tests'] as $t)
                {
                    $tests[] = $t;
                }

				foreach($json['bat'] as $k => $vl)
				{
					$text.= $k.': '.$vl.' | ';
				}

				foreach($json['pa'] as $k => $vl)
				{
					$text.= $k.': '.$vl.' | ';
				}
				foreach($json['ins1'] as $k => $vl)
				{
					$text.= $k.': '.$vl.' | ';
				}

				if(empty($row->pa_ID) && isset($_GET['fix_patient']))
                {
                    $wpdb->insert('ci_patients',$json['pa']);
                    $pid = $wpdb->insert_id;
                    if($pid>0)
                    {
                        update_post_meta($row->post_id,'_patient_id',$pid);
                        $wpdb->query("update ci_batches_pcr set patient_id = {$pid} where assen= '{$row->accessions}' and patient_id = 0");
                    }
                }
				?>
				<tr class="childp">
					<td><?php
                        echo $row->created;

						echo '<br><br>';
						echo implode('<br>',$tests);
						?></td>
					<td><?php
                        echo $row->pa_last.', '.$row->pa_first;
					?></td>
					<td><?php echo $row->pa_phone?></td>
					<td><?php echo $row->accessions?></td>
					<td><?php echo $text?></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</div>
	<div class="tablenav"><?php if(isset($pagination))echo $pagination->pagination(array('m')) ?></div>
</div>
