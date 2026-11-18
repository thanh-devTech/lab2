<?php
global $wpdb,$current_role;


$user_id                = get_current_user_id();
$incomplete_statuses    = tr_pdd_where_batch_incomplete(true);


wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );

$where      = '';

$rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'gene');

$where.= " and (".tr_pdd_where_batch_incomplete()." 
                or re.rerun_count > 0
                or (ba.culture=1 and re.final_results >1)  
               )";


if($current_role=='ref_lab_tech'){
	$ref_lab_id = get_user_meta($user_id,'_ref_lab_id',true);
	$where .= " and ba.ref_lab_ID = '{$ref_lab_id}' ";
}
else if(!tr_current_role_in(array('administrator','administrator_p','AdminCIDW','lab','labb','lab_tech','lab_sec','labsecman','leadlabsecman','labmanager','scientist','Billing'))){
	$where.= " and ba.author_ID = {$user_id} ";
}


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ba.lab_id in ( {$lab_str} ) ";
}

$sql_total = "select count(distinct(DATE_FORMAT(ba.spec_received,'%Y-%m-%d'))) as c 
                from ci_batches_gene ba 
                left join ci_reports_gene re ON ba.assen = re.patient_id
                where ba.batch_id > 0 {$where}";

if(class_exists('Tr_Cache_Query'))
{
	$cache_time = 86400;
	$cache = new Tr_Cache_Query();
	$total = $cache->get_var($sql_total,0,0,$cache_time);

}else{
	$total = $wpdb->get_var($sql_total);
}

$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$pagination->setTotal($total);
$offset     = $pagination->offset();

$order      = isset($_REQUEST['order']) && $_REQUEST['order']=='asc'?'asc':'desc';
$order_by   = !empty($_REQUEST['orderby'])? 'ba.'.$_REQUEST['orderby']:'ba.spec_received';
$order_day  = 'desc';

if($order_by == 'ba.spec_received'){
	$order_day = $order;
}
$sql = "select distinct(DATE_FORMAT(ba.spec_received,'%Y-%m-%d')) as spec_received
        from ci_batches_gene ba
        left join ci_reports_gene re ON ba.assen = re.patient_id
        where 1 {$where}
        order by ba.spec_received {$order_day}
        limit {$offset},{$row_per_page}";

$spec_receiveds = $wpdb->get_col($sql);
$spec_receiveds = implode("','",$spec_receiveds);
$where .= " and DATE_FORMAT(ba.spec_received,'%Y-%m-%d') in ('$spec_receiveds') ";

$sql = "select ba.*, 
            re.id,
            re.final_results,
            re.rerun_count,
            re.upload_files,
            re.estatus
        from ci_batches_gene ba 
        left join ci_reports_gene re ON ba.assen = re.patient_id
            where 1 {$where}
            order by spec_received {$order_day},{$order_by} {$order}";
$results = $wpdb->get_results($sql);

$dates = array();
foreach($results as $row)
{
	$row->spec_received = tr_pdd_display_date($row->spec_received,'Y-m-d');
	$dates[$row->spec_received][] = $row;
}

if(isset($_GET['debug'])){
	var_dump($sql);
	var_dump($results);
	var_dump($dates);
}
?>

<div class="wrap2">
	<h2>Incomplete Orders</h2>

	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>

    <div class="wraptbl">
	<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
		<thead>
		<tr>
			<th style="width:30px">#</th>
			<th style="width:90px">Batch #</th>
			<?php tr_tbl_sort('spec_received','Received Date','','width:130px')?>
			<?php tr_tbl_sort('assen','Accession','','width:10%')?>
			<?php tr_tbl_sort('requesting_practice','Clinic','','width:16%')?>
			<?php tr_tbl_sort('batch_status','Reason','','width:20%')?>
			<th style="width:25%">Note</th>
			<th>Photo</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach($dates as $date => $results){

		    ob_start();
		    $count_row = array(
		            'rerun' => 0,
                '2rerun' => 0,
                'rejected' => 0,
                'approved' => 0
            );
		    $batch_id = '';
			foreach ($results as $i=> $row)
			{

			    if(empty($batch_id))
                {
	                $batch_id = $row->batch_id;
                }
				$mstatus =  '';

				if($row->culture && $row->final_results>1){
					$mstatus = '<ogr>Culture results needed</ogr>';
				}

				$countby = '';
				if($row->rerun_count>0)
				{
					if($row->rerun_count==1 && strpos($row->upload_files,',')>0)
					{
						$row->rerun_count++;
						$wpdb->update('ci_reports_gene',array('rerun_count'=>$row->rerun_count),array('id'=>$row->id));
					}
					$mstatus = '<black>Rerun</black>';
					$countby = 'rerun';
					if($row->rerun_count>1)
					{
						$mstatus.= ' / <green>Rerun</green>';
						$countby = '2rerun';
					}
					if(isset($rejected_status[$row->batch_status])){
						$mstatus.= ' / <red>Rejected</red>';
						$countby = 'rejected';
					}
					if($row->estatus=='C'){
						$mstatus.= ' / Approved';
                        if($countby!='rejected'){
                            $countby = 'approved';
                        }

					}
				}else if(isset($incomplete_statuses[$row->batch_status]) )
				{
					$mstatus = '<span class="brejected">'.$incomplete_statuses[$row->batch_status].'</span>';
					$countby = 'rejected';
				}

				if(!isset($count_row[$countby]))
                {
	                $count_row[$countby] = 0;
                }
				$count_row[$countby]++;
				?>
                <tr class="childp">
                    <td style="width:30px;"><?php echo $i+1?></td>
                    <td style="width:90px"></td>
                    <td style="width:130px"><?php echo $row->spec_received?></td>
                    <td style="width:10%"><?php echo $row->assen?></td>
                    <td style="width:16%"><?php echo $row->requesting_practice?></td>
                    <td style="width:20%"><?php echo $mstatus?></td>
                    <td style="width:25%"><?php echo $row->billing_notes?></td>
                    <td>
						<?php if(!empty($row->issue_photo)):?>
                            <a class="thickbox" href="<?php echo $dir['url'].'/'.$row->issue_photo?>">View</a>
						<?php endif;?>
                    </td>
                </tr>
			<?php
			}
		    $child_content = ob_get_clean();
		    ?>
            <tr class="parent" data-p="batch_<?php echo $date?>">
                <td><i></i></td>
                <td><?php echo $batch_id?></td>
                <td colspan="3"><?php echo $date?></td>
                <td colspan="2">
                    <span>
                        <span class="rerun" title="Rerun"><?php echo $count_row['rerun']?></span> /
                        <span class="rerun2" title="Second Rerun"><?php echo $count_row['2rerun']?></span> /
                        <span class="brejected" title="Rejected"><?php echo $count_row['rejected']?></span> /
                        <span class="approved" title="Approved"><?php echo $count_row['approved']?></span>
                    </span>
                </td>
                <td><?php echo count($results)?> items</td>
            </tr>
			<tr class="child p_batch_<?php echo $date?>">
				<td colspan="8" class="nopadding">
					<table class="report_tbl">
						<?php echo $child_content?>
					</table>
				</td>
			</tr>
		<?php }?>
		</tbody>
	</table>
    </div>

</div>
<style>
    .report_tbl span.rerun{
        color:black;
    }
    .report_tbl span.rerun2{
        color:#39c539;
    }
    .report_tbl span.approved{
        color:white;
    }
    .report_tbl span.brejected{
        color:red;
    }
    .report_tbl span.missing{
        color:orange;
    }
</style>
<script>
    jQuery(function($){
        $('tr.parent').click(function(){
            $('tr.child').hide();
            if($(this).data('showed')!='1')
            {
                $('tr.p_'+$(this).data('p')).show();
                $('tr.parent').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                setCookie('active_icom',$(this).data('p'));
                $('body,html').scrollTop($(this).offset().top-80);
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });
    })
</script>