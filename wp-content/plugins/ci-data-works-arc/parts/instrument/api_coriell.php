<?php
global $wpdb,$current_role;

include (TRCPDD_PATH.'api/ci_api_coriell.php');

$log_dir            = WP_CONTENT_DIR.'/log/api_coriell/';
$log_file           = WP_CONTENT_DIR.'/log/log_api_coriell.txt';

wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );




$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'4' => 'Inconclusive'
);


if(isset($_POST['options'])){
	$options = $_POST['options'];
	if(empty($options['cron']))
	{
		$options['cron'] = 2;
	}
    
  
    $old_options = get_option('tr_hl7_api_coriell_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_hl7_api_coriell_st', $bkdata);

	update_option('tr_hl7_api_coriell_st',$options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_hl7_api_coriell_st',array());
	//setup cron
	wp_clear_scheduled_hook('cron_all_api',array('CI_Api_Coriell'));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Coriell'));
	}
}
$options = get_option('tr_hl7_api_coriell_st',array());


if(!isset($options['cron']) || empty($options['cron']))
{
	$options['cron'] = 2;
}

try{
	$client = new CI_Api_Coriell();
	$client->debug = true;

}catch(Exception $e)
{
	Tr_Session_Class::add_error($e->getMessage());
}

if($act=='auth')
{
	$rs = $client->auth();
	var_dump($rs);
	exit;
}
else if($act=='upload')
{
	try{
        $sql = "select ba.*,
        pa.* 
        from ci_batches_dna ba
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        where batch_id > 0 
        order by mid asc
        limit 1";
        $report = $wpdb->get_row($sql, ARRAY_A);
        echo $wpdb->last_error;
        if($report)
        {
            $rs = $client->pgx_upload($report);
            var_dump($rs);
        }else{
            echo 'not found';
        }
		

	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}
else if($act=='log'){

	$joins = '';
	$fields = '';
	$where = " mo_file_id='{$client->api_id}' ";
	if(isset($_GET['debug'])){
		$joins.= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins.= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields.= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if(!empty($_REQUEST['s'])){
		$where.= " and ba.assen = '{$_REQUEST['s']}' ";
	}
	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen, 
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.dagnostic_codes1,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_ID,
                                o.mo_status,
                                o.mo_set_ID
                                {$fields}
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            {$joins}
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");

	ob_start();

	?>
    <div>
        <input type="search" id="assen_search">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s'=>''))?>">Search</a>
    </div>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
        <tr>
            <th style="width:60px">#</th>
            <th>Accession</th>
            <th>Patient</th>
            <th>Clinic</th>
            <th>Doctor</th>
            <th style="width:60px">Resulted</th>
            <th style="width:70px">Status</th>
            <th style="width:50px;">Sent</th>
            <th>Date/time</th>
        </tr>
        </thead>
        <tbody>
		<?php
		$update = array();
		if(isset($_REQUEST['debug'])) {
			if ( $options['cli_id'] >0) {
				$clinic_name = $wpdb->get_var( "select cli_clinic from ci_clinic where cID = '{$options['cli_id']}' " );
				$update['cli_ID'] = $options['cli_id'];
				$update['requesting_practice'] = $clinic_name;
			}
			if ( $options['doc_id'] >0) {
				$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$options['doc_id']}");
				$default_doctor_name = $doctor->doc_last.', '.$doctor->doc_first;
				$update['doc_ID'] = $options['doc_id'];
				$update['requesting_physician'] = $default_doctor_name;
			}
		}
		foreach($rows as $i => $row)
		{
			?>
            <tr class="childp" data-id="<?php echo $row->id?>" data-mid="<?php echo $row->mid?>">
                <td><?php echo $row->mid?></td>
                <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid?>&short=1"><?php echo $row->assen?></a></td>
                <td><?php echo $row->patient_name?></td>
                <td><?php echo $row->requesting_practice?></td>
                <td><?php echo $row->requesting_physician?></td>
                <td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;if(isset($_GET['debug']))echo '&createnew='.$row->mid?>" target="_blank">
						<?php echo $row->estatus=='C'?'Yes':(isset($_GET['debug'])? '.':'')?>
                    </a>
                </td>
                <td>
					<?php
					if(!is_null($row->covid_pos))
					{
						echo $covid_status[$row->covid_pos];
					}
					?>
                </td>
                <td>
                    <?php echo $row->mo_status==1?'Yes':''?>

                    <?php if($row->mo_status==1)
                    {
                        ?>
                    <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'send_result','id'=>$row->id,'mid'=>$row->mid,'moid'=>$row->mo_ID))?>&width=1000" class="show_popup">Resend</a>
                    <?php
                    }else if($row->estatus=='C'){
	                    ?>
                        <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'send_result','id'=>$row->id,'mid'=>$row->mid,'moid'=>$row->mo_ID))?>&width=1000" class="show_popup">send</a>
	                    <?php
                    }
                    ?>
                </td>
                <td>
                    <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'get_result','id'=>$row->mo_ID))?>&width=1000" class="show_popup"><?php echo date('Y-m-d H:i:s',strtotime($row->mo_set_ID))?></a>
                </td>
            </tr>
			<?php
		}
		?>
        </tbody>
    </table>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<?php
	$content = ob_get_clean();
	if(isset($_GET['trajax'])){
		$content = str_replace('class="','class="show_popup ',$content);
		$content = str_replace('class=\'','class=\'show_popup ',$content);
	}
	echo $content;
	exit;
}
else if(!empty($act) && method_exists($client,$act))
{
	$rs = $client->{$act}($_GET['arg1']);
	var_dump($rs);exit;
}


$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);

$specimens = $wpdb->get_col("select ts_name from ci_test_specimens where ts_type = 'pcr' ");
?>

<div class="wrap">
    <h2>Coriell Settings</h2>
	<?php Tr_Session_Class::show()?>

    <form method="post">
        <div class="row">
            <div style="width:50%;float:left">
                <div class="row">
                    <label for="for_enable">Enable Cron</label>
                    <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable'])? $options['enable'] : 0)?>/>
                </div>
                <div class="row">
                    <label for="is_product">Product </label>
                    <input type="checkbox" id="is_product" value="1" name="options[is_product]" <?php checked(isset($options['is_product'])? $options['is_product'] : 0)?>/>
                </div>
                <div class="row">
                    <label>Lab</label>
                    <select name="options[lab_id]" required class="njs_select2">
						<?php
						foreach($labs as $lab)
						{
							$selected = isset($options['lab_id']) && $lab->lab_ID == $options['lab_id']?'selected':'';
							echo '<option value="'.$lab->lab_ID.'" '.$selected.'>'.$lab->lab_dba_init.'</option>';
						}
						?>
                    </select>
                </div>
                <div class="row">
                    <label>Clinic</label>
                    <select name="options[cli_id]" required class="njs_select2">
                        <option value="">Select</option>
						<?php
						foreach($clinics as $clinic)
						{
							$selected = isset($options['cli_id']) && $clinic->cID == $options['cli_id']?'selected':'';
							echo '<option value="'.$clinic->cID.'" '.$selected.'>'.$clinic->cli_clinic.'</option>';
						}
						?>
                    </select>
                </div>
                <div class="row">
                    <label>Doctor</label>
                    <select name="options[doc_id]" class="njs_select2"></select>
                </div>
                
                <div class="row">
                    <label>Username</label>
                    <input type="text" name="options[user]" value="<?php echo isset($options['user'])? $options['user'] : ''?>" required/>
                </div>
                <div class="row">
                    <label>Password</label>
                    <input type="text" name="options[password]" value="<?php echo isset($options['password'])? $options['password'] : ''?>" required/>
                </div>

                

                <div class="row">
                    <label>Schedule</label>
                    <select name="options[cron]">
			            <?php
			            foreach(array(1,2,5,10,30) as $m)
			            {
				            $selected = $m == $options['cron']? 'selected':'';
				            echo '<option value="'.$m.'" '.$selected.'>each '.$m.' min(s)</option>';
			            }
			            ?>
                    </select>
                </div>


            </div>
            <div style="width:49%;float:right">
                <div class="frow">
                    <label>Notes</label>
                    <textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes'])? esc_html($options['notes']) : ''?></textarea>
                </div>
            </div>
        </div>


        <div class="frow row">
            <input type="submit" value="Save" class="button-primary"/> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'auth'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000">Auth</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'upload'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000">Test Upload</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'log'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000">Log</a> &nbsp;&nbsp;
            <a class="button" target="_blank" href="<?php echo '/wp-content/log/log_api_coriell.txt'?>">Log File</a> &nbsp;&nbsp;
            <?php 
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid 
                            where o.mo_file_id = '{$client->api_id}' ");
			echo '<br>';
			echo 'Reqs: '.$count->count_reqs.'<br>';
			echo 'Results: '.$count->count_results.'<br>';
			echo 'Sent: '.$count->count_sent.'<br>';
			echo 'Now: '.date_i18n('Y-m-d H:i:s').'<br>';

			$next_cron = wp_next_scheduled('cron_api_hl7_api_coriell');
			$current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;

			$last_cron = file_exists($log_file)? filemtime($log_file) : 0;
            
			if($last_cron >0)
			{
				echo 'Last Cron: '.date('Y-m-d H:i:s',$last_cron+$current_offset).'<br>';
			}
			if($next_cron)
			{
				echo 'Next Cron: '.date('Y-m-d H:i:s',$next_cron+$current_offset).'<br>';
			}
			?>
        </div>
    </form>
</div>


<style>
    #edittbl input.editfield{
        width:100%;
    }
</style>
<script>
    var $doctors = <?php echo json_encode($doctors)?>;
    var $selected_doc = <?php echo intval(isset($options['doc_id'])? $options['doc_id'] : 0)?>;
    jQuery(function ($) {

        function save_table_data($this)
        {
            var $pr = $this.parent();
            var $tr = $pr.parent();
            var $vl = $this.val();
            var $col = $this.data('k');
            var $id = $tr.data('id');

            $pr.append('<div class="sloading"></div>');
            $.ajax({
                data:{act:'save_table',trajax:1,col:$col,id:$id,val:$vl},
                type:'post',
                dataType:'json',
                success:function (rs) {
                    if(rs.success){

                    }else{
                        alert(rs.msg);
                    }
                    $('.sloading',$pr).remove();
                }
            });
        }
        $.setup_select2 = function () {
            $('.js_select2, .njs_select2').each(function(){
                if (!$(this).hasClass('select2-hidden-accessible')){
                    try {
                        $(this).select2({minimumResultsForSearch:8});
                        if ($(this).is(':required')) {
                            $(this).next('.select2').addClass('required');
                        }
                    }catch (e){
                        console.log(e);
                    }
                }
            })
        }
        $('[name="options[cli_id]"]').change(function () {
            var $cli_id = $(this).val();
            var $html = '<option value="">Select Doctor</option>';
            for(i in $doctors)
            {
                var $doctor = $doctors[i];
                if($doctor.cli_ID ==$cli_id)
                {
                    var $selected = $doctor.doc_ID==$selected_doc? 'selected':'';
                    $html+='<option value="'+$doctor.doc_ID+'" '+$selected+'>'+$doctor.doc_first+' '+$doctor.doc_last+'</option>';
                }
            }
            $('[name="options[doc_id]"]').html($html);
            $('[name="options[doc_id]"]').removeClass('select2-hidden-accessible');
            $.setup_select2();


        }).change();
        $('body').on('click','a.seach_assen',function () {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function () {
                tb_show('',$url+'&s='+$s);
            },300);
            return false;
        }).on('click','a.show_popup',function () {
            if($(this).hasClass('seach_assen'))return;
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },500);
            return false;
        }).on('keypress','#edittbl input.editfield',function (e) {
            var code = e.keyCode || e.which;
            if(code==13)
            {
                save_table_data($(this));
                return false;
            }
        }).on('change','#edittbl .editfield',function () {
            save_table_data($(this));
        })
    })
</script>
