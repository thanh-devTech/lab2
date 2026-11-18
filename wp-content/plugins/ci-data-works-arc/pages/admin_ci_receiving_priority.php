<?php
global $wpdb;
$current_time = time()+(get_option( 'gmt_offset' ) * HOUR_IN_SECONDS);

wp_enqueue_script( 'jquery-ui', '', array( 'jquery' ) );
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );

$date       = isset($_REQUEST['date'])? $_REQUEST['date'] : '';
$assen      = isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
$results    = array();

if(empty($date))
{
	$date = date_i18n('Y-m-d');
}

if(!empty($date) || !empty($assen))
{

	$sql = "select re.*,lab.lab_dba_init as lab_name
	from ci_receiving re
	JOIN ci_labs as lab On lab.lab_ID = re.re_lab_ID
	where re.re_type = 0 ";

	if(!empty($date))
	{
		$sql.= " and re.re_today='{$date}'  ";
	}

	if(!empty($assen))
	{
		$re_ids = $wpdb->get_col("select re_ID from
		(
		select re_ID from ci_receiving_batches rb
		join ci_batches ba ON ba.mid = rb.mid
		where assen like '{$assen}%' and rb.btype='tox'
		
		UNION ALL
		
		select re_ID from ci_receiving_batches rb
		join ci_batches_blood ba ON ba.mid = rb.mid
		where assen like '{$assen}%' and rb.btype='blood'
		
		UNION ALL
		
		select re_ID from ci_receiving_batches rb
		join ci_batches_dna ba ON ba.mid = rb.mid
		where assen like '{$assen}%' and rb.btype='dna'
		
		UNION ALL
		
		select re_ID from ci_receiving_batches rb
		join ci_batches_ngs ba ON ba.mid = rb.mid
		where assen like '{$assen}%' and rb.btype='ngs'
		
		UNION ALL
		
		select re_ID from ci_receiving_batches rb
		join ci_batches_pcr ba ON ba.mid = rb.mid
		where assen like '{$assen}%' and rb.btype='pcr'
		
		) as allre limit 10
		");
		$re_ids = implode(',',$re_ids);
		$sql.= " and re.re_ID in ({$re_ids}) ";

	}

	if(CI_Base_Lab::has_user_labs())
	{
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$sql.= " and re_lab_ID in ( {$lab_str} ) ";
	}
	

	if(isset($_GET['debug'])){
		echo $sql;
	}
	$results = $wpdb->get_results($sql, ARRAY_A);

	$tmp_results = $results;
	$results = array();
	foreach($tmp_results as $r)
	{
		$receiving_time = strtotime($r['re_created']);
		$end_time = $receiving_time + ($r['re_timing'] * 3600);
		$color = '';
		if ($r['re_priority'] == 'high') {
			$color = 'red';

		} else if ($r['re_priority'] == 'med') {
			$color = 'orange';
			//check hours
			if ($r['re_timing'] == '24') {
				if ($end_time - $current_time < (12 * 3600)) {
					$color = 'red';
				}

			} else {
				if ($end_time - $current_time < (24 * 3600)) {
					$color = 'red';
				}
			}
		} else if ($r['re_priority'] == 'low') {
			$color = 'green';
			//check hours
			if ($end_time - $current_time < ($r['re_timing'] / 4 * 3600)) {

				$color = 'red';
			} else if ($end_time - $current_time < ($r['re_timing'] / 2 * 3600)) {
				$color = 'orange';
			}

		}

		$key_time = ($current_time+30*86400) - $end_time;

		$r['color'] = $color;
		switch ($color)
		{
			case 'red':
				$key_time*=10;
				break;
			case 'orange':
				$key_time*=5;
				break;
		}

		while(isset($results[$key_time]))
		{
			$key_time++;
		}

		$results[$key_time] = $r;
	}

	krsort($results);
}

if(isset($_REQUEST['trajax']) && $_REQUEST['trajax']=='export'){

	$filename = 'receiving_'.$date.'.csv';
	ob_clean();
	header("Content-disposition: attachment; filename={$filename}");
	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');
	$headers = array(
		'Tracking Number Barcode',
		'Lab',
		'Priority',
		'Timing',
		'Practice',
		'Collector',
		'Qty Urine',
		'Qty Oral',
		'Qty JVHL',
		'Qty JVHL-O',
		'Qty Blood',
		'Qty PGx',
		'Qty rPCR',
		'Qty NF-PCR',
		'Qty PCR'
	);
	fputcsv($output,$headers);

	foreach ($results as $row)
	{
		$datarow = array(
			$row['re_tracking_number_barcode'],
			$row['lab_name'],
			$priorities[$row['re_priority']],
			$timings[$row['re_timing']],
			$row['re_practice'],
			$row['re_collector'],
			$row['re_qty_urine'],
			$row['re_qty_oral'],
			$row['re_qty_jvhlo'],
			$row['re_qty_jvhl'],
			$row['re_qty_blood'],
			$row['re_qty_dna'],
			$row['re_qty_rpcr'],
			$row['re_qty_nfpcr'],
			$row['re_qty_pcr'],
		);
		fputcsv($output,$datarow);
	}
	@fclose($output);
	exit;
}
?>
<br>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
    <label>Date:</label>
    <input type="text" class="hasdate" name="date" value="<?php echo $date?>"/>
    <label>Accession</label>
    <input type="text" name="assen" value="<?php echo $assen?>" placeholder="Search by Accession"/>
    <input type="submit" class="button bsty6" value="Search"/>
    <input type="button" class="button exportbtn" value="Export"/>
</form>
<br>
<div class="wraptbl">
    <table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
        <thead class="sticky">
        <tr>
            <th style="width:30px"></th>
            <th>Tracking Number Barcode</th>
            <th style="width:40px">Lab</th>
            <th style="width:50px">Priority</th>
            <th style="width:50px">Timing</th>
            <th>Practice</th>
            <th style="width:100px">Collector</th>
            <th class="smaller" style="width:45px">Qty<br>Urine</th>
            <th class="smaller" style="width:45px">Qty<br>Oral</th>
            <th class="smaller" style="width:45px">Qty<br>JVHL</th>
            <th class="smaller" style="width:45px">Qty<br>JVHL-O</th>
            <th class="smaller" style="width:45px">Qty<br>Blood</th>
            <th class="smaller" style="width:45px">Qty<br>PGx</th>
            <th class="smaller" style="width:45px">Qty<br>rPCR</th>
            <th class="smaller" style="width:45px">Qty<br>NF-PCR</th>
            <th class="smaller" style="width:45px">Qty<br>PCR</th>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach($results as $i => $row){
			?>
            <tr class="parent_c color_<?php echo $row['color']?>" data-p="batch_<?php echo $i?>">
                <td title="<?php echo $i?>"><i></i></td>
                <td><?php echo $row['re_tracking_number_barcode']?></td>
                <td><?php echo $row['lab_name']?></td>
                <td><?php echo $priorities[$row['re_priority']]?></td>
                <td><?php echo $timings[$row['re_timing']]?></td>
                <td><?php echo $row['re_practice']?></td>
                <td><?php echo $row['re_collector']?></td>
                <td><?php echo $row['re_qty_urine']?></td>
                <td><?php echo $row['re_qty_oral']?></td>
                <td><?php echo $row['re_qty_jvhlo']?></td>
                <td><?php echo $row['re_qty_jvhl']?></td>
                <td><?php echo $row['re_qty_blood']?></td>
                <td><?php echo $row['re_qty_dna']?></td>
                <td><?php echo $row['re_qty_rpcr']?></td>
                <td><?php echo $row['re_qty_nfpcr']?></td>
                <td><?php echo $row['re_qty_pcr']?></td>
            </tr>
            <tr  class="child  p_batch_<?php echo $i?>" data-id="<?php echo $i?>" data-reid="<?php echo $row['re_ID']?>">
                <td class="nopadding" colspan="16">
                    <table  class="posts report_tbl">
                        <thead>
                        <tr>
                            <th style="width:20px">#</th>
                            <th style="width:70px">Position</th>
                            <th style="width:100px">Barcode</th>
                            <th style="width:80px">DOC</th>
                            <th style="width:22%">Patient Name</th>
                            <th style="width:21%">Office</th>
                            <th style="width:21%">Doctor</th>
                            <th>Batch#</th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						$sql = "
                            select * from (
                                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.batch_id,
                                    rb.btype 
                                    from ci_batches ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='tox'
                                        where rb.re_ID = '{$row['re_ID']}' ) 
                                        
                                    UNION ALL
                                    
                                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.batch_id,
                                    rb.btype 
                                    from ci_batches_blood ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='blood'
                                        where rb.re_ID = '{$row['re_ID']}' )
                                        
                                        UNION ALL
                                    
                                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.batch_id,
                                    rb.btype 
                                    from ci_batches_dna ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='dna'
                                        where rb.re_ID = '{$row['re_ID']}' )
                                        
                                         UNION ALL
                                    
                                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.batch_id,
                                    rb.btype 
                                    from ci_batches_pcr ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='pcr'
                                        where rb.re_ID = '{$row['re_ID']}' )
                                        
                                ) as ball
                                order by ball.position desc";

						$batches = $wpdb->get_results($sql);
						foreach($batches as $batch)
						{
							$class = '';
							if(!empty($assen) && stripos($batch->assen,$assen)!==false)
							{
								$class = 'found_assen';
							}
							$batch->mid = $batch->btype.'_'.$batch->mid;
							?>
                            <tr class="childp assen_<?php echo $batch->assen .' '.$class?>">
                                <td><?php echo $ii?></td>
                                <td><?php echo $batch->position?></td>
                                <td><?php echo $batch->assen?></td>
                                <td><?php echo $batch->doc?></td>
                                <td><?php echo $batch->patient_name?></td>
                                <td><?php echo $batch->requesting_practice?></td>
                                <td><?php echo $batch->requesting_physician?></td>
                                <td>
									<?php if($batch->batch_id>0 && !isset($_GET['debug'])):?>
										<?php echo $batch->batch_id?>
									<?php else:?>
                                        <a class="button add_to_batch" data-id="<?php echo $batch->mid?>">Add to Batch</a>
									<?php endif;?>
                                </td>
                            </tr>
							<?php
						}
						?>
                        </tbody>

                    </table>
                </td>
            </tr>
			<?php
		}
		?>
        </tbody>
    </table>
</div>

<style>
    .report_tbl tbody tr.found_assen.childp td{background:#ffffe0 !important;}
    tr.color_red td:first-child{
        background:red !important;
    }
    tr.color_orange td:first-child{
        background:orange !important;
    }
    tr.color_green td:first-child{
        background:green !important;
    }
</style>
<script>
    jQuery(function ($) {
        $('a.add_to_batch').click(function(){
            var $pr = $(this).parent();
            var $child_c = $pr.parents('tr.child');
            var $reid = $child_c.data('reid');
            $.ajax({
                data:{trajax:1,act:'add_to_batch',reid:$reid,mid:$(this).data('id')},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    $pr.html(rs.batch_id);
                }
            })
        })

        $('body').on('click','tr.parent_c',function(e){
            if(e.target.tagName!='TD' && e.target.tagName!='TR' && e.target.tagName!='I')return;
            $('tr.child').hide();
            if($(this).data('showed')!='1')
            {
                $('tr.p_'+$(this).data('p')).show();
                $('tr.parent_c').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('.exportbtn').click(function () {
            location.href='<?php Tr_Base_Class_V6::link(array('trajax'=>'export'))?>&date='+$('form [name="date"]').val();
        })
    })
</script>