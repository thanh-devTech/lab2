<?php
global $wpdb,$current_role;

include_once(TRCPDD_PATH.'pages/formdata.php');

$user_id        = get_current_user_id();
$show_detail    = isset($_REQUEST['detail'])? $_REQUEST['detail'] : '';
$from_date      = isset($_REQUEST['from'])? $_REQUEST['from'] : '';
$to_date        = isset($_REQUEST['to'])? $_REQUEST['to'] : '';
$from_time      = '';
$to_time        = '';
$where          = '';


if(empty($from_date)){
	$from_date = date_i18n('Y-m-d');
}
if(empty($to_date)){
	$to_date = date_i18n('Y-m-d');
}


$all_tests  = array();
$rs         = $wpdb->get_results("select te_ID, te_name,te_group_id from ci_test_panels where te_type='gene'");
foreach($rs as $r)
{
    $all_tests[$r->te_ID] = array(
            'name' => $r->te_name,
            'group_id' => $r->te_group_id,
    );
}


$groups     = $wpdb->get_results("select * from ci_test_groups where tg_type = 'gene' 
                                    and tg_show_req=1 order by tg_name", ARRAY_A);
$all_groups = array();
foreach($groups as $group)
{
    $all_groups[$group['tg_ID']] = $group['tg_name'];
}

@list($from_batch_id, $from_time)    = explode(' ',$from_date);
@list($to_batch_id, $to_time)        = explode(' ',$to_date);
$from_batch_id                      = str_replace('-','',$from_batch_id);
$to_batch_id                        = str_replace('-','',$to_batch_id);


$where.= " and ba.batch_id>='{$from_batch_id}' ";
$where.= " and ba.batch_id<='{$to_batch_id}' ";

if( in_array($current_role , array( 'ref_lab_tech','reflab_manager')) ){
	$ref_lab_id = get_user_meta($user_id,'_ref_lab_id',true);
	$where .= " and ba.ref_lab_ID = '{$ref_lab_id}' ";
}

if(!empty($from_time) && $from_time!='00:00:00')
{
    $where.= " and DATE_FORMAT(ba.spec_received,'%H:%i:%s') >= '{$from_time}' ";
}

if(!empty($to_time) && $to_time!='00:00:00')
{
	$where.= " and DATE_FORMAT(ba.spec_received,'%H:%i:%s') <= '{$to_time}' ";
}

if(isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'],'export')!==false)
{
	$sql = "select ba.position,
        ba.assen,
        ba.patient_name,
        ba.dob,
        ba.doc,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.physician_phone,
        ba.batch_status,
        ba.spec_received,
        ba.lab,
        ba.panel_tests_requested as tests,
        ba.notes,
        ba.culture,
        ins.ins_address, 
        ins.ins_member_id, 
        ins.ins_member_id, 
        ins.ins_group_id, 
        ins.ins_city, 
        ins.ins_state, 
        ins.ins_zip,
        u.user_login
        
        from ci_batches_gene ba 
        left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
        Left JOIN {$wpdb->users} u ON u.ID = ba.author_ID and ba.batch_short=1
        where 1 {$where}
        order by ba.position asc";
	$samples = $wpdb->get_results($sql);

	$filename = 'gene_batch_stats.csv';

	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"$filename\";" );
	header("Content-Transfer-Encoding: binary");

    $f = fopen('php://output','w');
	$keys = array(
		'Position',
		'Patient Name',
		'Accession',
		'DOB',
		'Clinic',
		'Doctor Name',
		'Doctor Phone',
		'Collection Date',
		'Received',
		'Lab',
		'Req Panels',
        'Insurance',
        'Insurance Address',
        'Insurance City',
        'Insurance State',
        'Insurance Zip',
        'Insurance Member ID',
        'Insurance Group ID',
        'Diagnostic codes',
        'Username',
        'Notes'
	);

	fputcsv($f, $keys);

	foreach($samples as $row)
    {
	    $tests = array();
	    $row->tests = strtoupper($row->tests);
	    $rs_tests = explode(',',$row->tests);
	    foreach($rs_tests as $t)
	    {
		    if(stripos($t,'group')===false)
		    {
			    if($t>0 && isset($all_tests[$t]))
			    {
				    $t = $all_tests[$t];
				    if(!in_array('GROUP_'.$t['group_id'], $rs_tests))
				    {
					    $tests[] = $t['name'];
				    }
			    }

		    }else{
			    $group_id = str_ireplace('group_','',$t);
			    if(isset($all_groups[$group_id])){
				    $tests[] = $all_groups[$group_id];
			    }
		    }
	    }

	    if($row->culture)
        {
	        $tests[] = 'Culture';
        }
	    $tests = implode(', ',$tests);
	    $row_data = array(
		    $row->position,
		    $row->patient_name,
		    $row->assen,
		    $row->dob,
		    $row->requesting_practice,
		    $row->requesting_physician,
		    $row->physician_phone,
		    $row->doc,
		    $row->spec_received,
		    $row->lab,
		    $tests,
		    $row->insurance,
		    $row->ins_address,
		    $row->ins_city,
		    $row->ins_state,
		    $row->ins_zip,
		    $row->ins_member_id,
		    $row->ins_group_id,
		    $row->dagnostic_codes1,
		    $row->user_login,
		    $row->notes,
	    );

	    fputcsv($f, $row_data);
    }
    fclose($f);


    exit;
}


$sql = "select ba.position,
        ba.assen,
        ba.patient_name,
        ba.dob,
        ba.doc,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.physician_phone,
        ba.batch_status,
        ba.spec_received,
        ba.lab,
        ba.panel_tests_requested as tests
        from ci_batches_gene ba 
        where 1 {$where}
        order by ba.position asc";
$samples = $wpdb->get_results($sql);

$rejected = 0;
if(!$show_detail)
{
	$rejected = $wpdb->get_var("select count(*) from ci_batches_gene ba where  (ba.batch_status like 're\_%' or ba.batch_status='rejected') {$where}");
}

if(isset($_GET['debug'])){
    var_dump($sql);
}
?>

<div class="wrap">
    <form method="get" style="margin: 15px auto">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>

        <span id="summaryfromdate">
            From Date: <input type="text" class="hasdatetime" name="from" value="<?php echo @$from_date?>"/>
        </span>
        <label id="summarytodate">To Date:</label>
        <input type="text" class="hasdatetime today" name="to" value="<?php echo @$to_date?>"/>

        <label>
            <input type="checkbox" name="detail" value="1" <?php if($show_detail)echo 'checked'?>/> Detail
        </label>
        <input type="submit" class="button bsty6" value="Search"/>
        <a class="button buttonprint bsty3">Print Barcodes</a>
        <input type="submit" class="button" name="trajax" value="QC Export"/>
    </form>

    <div class="wraptbl">
		<?php if($show_detail)
		{
            ?>
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
                <thead>
                <tr>
                    <th style="width:120px">Panel</th>
					<th style="width:30%">Test Name</th>
					<th style="width:20%">Positive</th>
					<th style="width:20%">Negative</th>
					<th>Percentage Positive</th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach($groups as $group)
                {
	                $sql = "select te_ID,te_name from ci_test_panels where te_group_id = {$group['tg_ID']} order by te_name asc";
	                $tests = $wpdb->get_results($sql, ARRAY_A);

                    ?>
                    <tr class="parent" data-id="<?php echo $group['tg_ID']?>">
                        <td colspan="5"><?php echo $group['tg_name']?></td>
                    </tr>
                    <tr class="child" data-id="<?php echo $group['tg_ID']?>">
                        <td colspan="5">
                            <table class="report_tbl child_list">
		                        <?php
                                $count = 0;
		                        foreach($tests as $test)
		                        {
		                            $sql = "select count(DISTINCT ba.mid) from ci_batches_gene ba 
                                            join ci_reports_gene re ON re.patient_id = ba.assen
                                            join ci_results_gene rs ON rs.report_id = re.id  
                                            and rs.`pos-neg` in ('POS','POL','POM','POH','PVH','PVL') 
                                            where 1 {$where} 
                                            and (rs.drug='{$test['te_name']}' or rs.drug='{$group['tg_ID']}|{$test['te_name']}') ";

			                        $test['pos'] = intval($wpdb->get_var($sql));

			                        $sql = "select count(DISTINCT ba.mid) from ci_batches_gene ba 
                                            join ci_reports_gene re ON re.patient_id = ba.assen
                                            join ci_results_gene rs ON rs.report_id = re.id  and rs.`pos-neg` = 'NEG'
                                            where 1 {$where} 
                                             and (rs.drug='{$test['te_name']}' or rs.drug='{$group['tg_ID']}|{$test['te_name']}')";

			                        $test['neg'] = intval($wpdb->get_var($sql));

			                        if($test['pos']+$test['neg']==0)continue;

			                        $test['percent'] = number_format($test['pos'] / ($test['pos'] + $test['neg']) * 100,2);
			                        ?>
                                    <tr class="childp">
                                        <td style="width:120px"></td>
                                        <td style="width:30%"><?php echo $test['te_name']?></td>
                                        <td style="width:20%"><?php echo $test['pos']?></td>
                                        <td style="width:20%"><?php echo $test['neg']?></td>
                                        <td><?php echo $test['percent']?> %</td>
                                    </tr>
			                        <?php
                                    $count++;
		                        }
		                        if($count==0)
                                {
                                    ?>
                                    <tr class="childp">
                                        <td colspan="5" align="center">No Results Found.</td>
                                    </tr>
                                <?php
                                }
		                        ?>
                            </table>
                        </td>
                    </tr>
                <?php
                }
                ?>
                </tbody>
            </table>
            <script>
                jQuery(function ($) {
                    $('tr.parent').click(function(e){
                        if(e.target.tagName!='TD' && e.target.tagName!='TR' && e.target.tagName!='I')return;
                        $('tr.child').hide();
                        var $pr = $('tr.child[data-id="'+$(this).data('id')+'"]');
                        if($(this).data('showed')!='1')
                        {
                            $pr.show();
                            $('tr.parent').data('showed','0');
                            $(this).data('showed','1');
                            $(this).addClass('active');
                            var $obj = $(this);
                            $('body,html').scrollTop($obj.offset().top-50);

                        }else{
                            $pr.hide();
                            $(this).data('showed','0');
                            $(this).removeClass('active');
                        }
                        return false;
                    });
                })
            </script>
            <?php
		}else{
			?>
            <table class="wp-list-table fixed widefat striped pages blue_tbl hover_tbl">
                <thead>
                <tr>
                    <th>Samples</th>
					<?php
					foreach($groups as $group)
					{
						echo '<th>'.$group['tg_name'].'</th>';
					}
					?>
                    <th>Rejected</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php echo count($samples)?></td>
					<?php
					foreach($groups as $group)
					{
						$where2 = '';
						$tests_ids = $wpdb->get_col("select te_ID from ci_test_panels where te_group_id = {$group['tg_ID']}");
						foreach($tests_ids as $tid)
						{
							$where2.= " or FIND_IN_SET('{$tid}', panel_tests_requested ) ";
						}
						$sql = "select count(*) from ci_batches_gene ba 
                            join ci_reports_gene re ON ba.assen = re.patient_id and re.estatus = 'C'
					        where ( FIND_IN_SET('GROUP_{$group['tg_ID']}',panel_tests_requested) {$where2}) 
					        and (ba.batch_status not like 're\_%' and ba.batch_status!='rejected')
					        {$where} ";
						$count = $wpdb->get_var($sql);
						if(isset($_GET['debug'])){
							echo '<br><br><br>';
							echo $group['tg_name'];
							echo '<br><br><br>';
							var_dump($sql);
						}
						echo '<td>'.$count.'</td>';
					}
					?>
                    <td><?php echo $rejected?></td>
                </tr>
                </tbody>
            </table>
			<?php
		}
		?>
    </div>

</div>



<script>
    jQuery(function ($) {
        var $tiv = null;
        var $countrow = <?php echo count($samples)?>;
        var $timeout_print = 2000 +(45 * $countrow);
        if($timeout_print<4000)$timeout_print = 4000;
        console.log('need: '+$timeout_print);
        setTimeout(function () {
            var $html = $('#printarea').html();
            document.getElementById('printframe').contentWindow.document.write($html);
        },1000);
        $tiv = setInterval(function () {
            $timeout_print-=1000;
            console.log('need: '+$timeout_print);
            if($timeout_print<2000)
            {
                clearInterval($tiv);
                $tiv = null;
            }
        },1000);
        $('a.buttonprint').click(function () {
            var $this = $(this);
            $this.data('oldvl',$this.html());
            if($this.html()=='Getting content...')return false;
            $this.html('Getting content...');
            setTimeout(function () {
                window.frames["printframe"].focus();
                window.frames["printframe"].print();
                $this.html($this.data('oldvl'));
                $timeout_print = 1000;
            },$timeout_print);
            if($tiv!=null)
            {
                clearInterval($tiv);
                $tiv = null;
            }
            return false;
        })
        $('[name="from"]').change(function () {
           //$('[name="to"]').val($(this).val());
        });
        $('[name="from"],[name="to"]').change(function () {
            $('.buttonprint').hide();
        });
    })
</script>

<iframe src="about:blank" id="printframe" name="printframe" style="position:absolute;left:-99999px;"></iframe>

<script type="text/html" id="printarea" style="display: none;">

    <style media="print">
        @page {
            size: portrait;
            margin-top:0;
            margin-bottom:0;
        }
        body,html{
            margin-bottom:0;
        }
        .printtbl th{
            text-align: left;
            border-bottom:1px solid #ddd;
        }
        .printtbl td{
            border-bottom:1px solid #f9f9f9;
        }
    </style>
    <div class="print_wrap">
        <h5 style="text-align: center"><?php echo tr_pdd_display_date($from_date).' - '.tr_pdd_display_date($to_date)?></h5>
        <table style="width:100%" class="printtbl">
            <thead>
            <tr>
                <th>Position</th>
                <th>Patient Name</th>
                <th>Panels</th>
                <th style="width:30px"></th>
                <th>Barcode</th>
                <th>Accession</th>
            </tr>
            </thead>
            <tbody>
			<?php
			foreach($samples as $row)
			{
				$arr = array(
					'text' => $row->assen,
					'type' => 'code128b',
					'transparency' => 1,
					'rotation' => 0
				);
				$url_img = apply_filters('barcode',$arr);
				$tests = array();
				$row->tests = strtoupper($row->tests);
				$rs_tests = explode(',',$row->tests);
				foreach($rs_tests as $t)
                {
	                if(stripos($t,'group')===false)
	                {
		                if($t>0 && isset($all_tests[$t]))
		                {
		                    $t = $all_tests[$t];
		                    if(!in_array('GROUP_'.$t['group_id'], $rs_tests))
                            {
	                            $tests[] = $t['name'];
                            }
		                }

	                }else{
		                $group_id = str_ireplace('group_','',$t);
		                if(isset($all_groups[$group_id])){
		                    $tests[] = $all_groups[$group_id];
                        }
	                }
                }
				$tests = implode(', ',$tests);
				$mstatus = '';
				if($row->culture){
					$mstatus = 'C';
				}

				?>
                <tr>
                    <td><?php echo $row->position?></td>
                    <td><?php echo tr_pdd_capitalized($row->patient_name)?></td>
                    <td><?php echo $tests?></td>
                    <td style="text-align: center"><?php echo $mstatus?></td>
                    <td><img id="image_code1" style="width:100px;" src="<?php echo $url_img?>" /></td>
                    <td><?php echo $row->assen?></td>
                </tr>
				<?php
			}
			?>
            </tbody>
        </table>
    </div>

</script>