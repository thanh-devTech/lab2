<?php
global $wpdb;
$log_dir = WP_CONTENT_DIR.'/log/api_cteh/';
include(TRCPDD_PATH.'api/ci_api_cteh.php');
$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'3' => 'Inconclusive',
	'4' => 'Inconclusive',
);


if(isset($_POST['options'])){
	$options = $_POST['options'];
	$options['active'] = !empty($options['active']) ? 1 : 0;
    $old_options = get_option('_tr_cteh_settings', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_cteh_settings', $bkdata);
	update_option('_tr_cteh_settings',$options);

	Tr_Session_Class::add("Saved Settings Successfully");

	wp_clear_scheduled_hook('cron_api_cteh');
	if($options['active']){
		wp_schedule_event(time()+1,'10min','cron_api_cteh');
	}
}

$options = array_merge(array(
	'active' => 0,
	'key' => '',
	'lab_id' => 0,
	'cli_id' => '',
	'doc_id' => '',
), get_option('_tr_cteh_settings',array()));
$cteh = new CI_Api_CTEH($options['key']);
$cteh->is_test = !$options['active'];
if($act=='test'){
	$rs = $cteh->describe();

	var_dump($rs);
	exit;
}
else if($act=='sync'){
	$rs = $cteh->import_reqs(false,'');
	echo 'count: '.$rs;
	exit;
}
else if($act=='restatus'){
    $rows = $wpdb->get_results("select * from ci_sync_api_orders where mo_file_id= '{$cteh->api_id}'");
    foreach($rows as $r)
    {
	    $rs = $cteh->update_item_status($r->mo_test_ID,'null');
	    var_dump($rs);break;
    }
}
else if($act=='list'){
    $rs = $cteh->manifest_items('');
    var_dump($rs);exit;
}
else if($act=='sync_reports'){
	$rs = $cteh->sync_reports();
	var_dump($rs);
	exit;
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
?>

<div class="wrap">
	<h2>CTEH Settings</h2>

	<?php Tr_Session_Class::show()?>

	<form method="post">
		<div class="row ">
			<label>Active</label>
			<input type="checkbox" name="options[active]" value="1" <?php echo checked($options['active'])?>"/>
		</div>
		<div class="row ">
			<label>API Key</label>
			<input type="text" name="options[key]" value="<?php echo $options['key']?>" maxlength="50" required style="width:50%"/>
		</div>

        <div class="row">
            <label>Lab</label>
            <select name="options[lab_id]" required class="njs_select2">
				<?php
				foreach($labs as $lab)
				{
					$selected = $lab->lab_ID == $options['lab_id']?'selected':'';
					echo '<option value="'.$lab->lab_ID.'" '.$selected.'>'.$lab->lab_dba_init.'</option>';
				}
				?>
            </select>
        </div>
        <div class="row">
            <label>Clinic</label>
            <select name="options[cli_id]" required class="njs_select2">
                <option value="">In file</option>
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
            <select name="options[doc_id]" data-value="<?php echo $options['doc_id']?>" required></select>
        </div>

		<div class="frow">
			<input type="submit" class="button-primary" value="Submit">
			<?php
			if(!empty($options['key'])){
				?>
				<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act'=>'test','trajax'=>1))?>">Describe</a>
				<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act'=>'list','trajax'=>1))?>&width=1000">List</a>
				<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act'=>'sync','trajax'=>1))?>&width=1000">Sync Reqs</a>
				<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act'=>'sync_reports','trajax'=>1))?>&width=1000">Sync Reports</a>
				<?php
			}
			?>
			<?php
			$count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status=1,1,0)) as count_sent
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen and re.estatus='C'
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID
                            where fi.m_type='cteh'");
			echo '<br>';
			echo 'Reqs: '.$count->count_reqs.'<br>';
			echo 'Results: '.$count->count_results.'<br>';
			echo 'Sent: '.$count->count_sent.'<br>';
			echo 'Now: '.date_i18n('Y-m-d H:i:s').'<br>';

			$next_cron = wp_next_scheduled('cron_api_cteh');
			$last_cron = file_exists(WP_CONTENT_DIR.'/log/log_api_cteh.txt') ? filemtime(WP_CONTENT_DIR.'/log/log_api_cteh.txt') : 0;
			if($last_cron)
			{
				echo 'Last Cron: '.get_date_from_gmt(date('Y-m-d H:i:s',$last_cron), 'Y-m-d H:i:s').'<br>';
			}
			if($next_cron)
			{
				echo 'Next Cron: '.get_date_from_gmt(date('Y-m-d H:i:s',$next_cron), 'Y-m-d H:i:s').'<br>';
			}
			?>
		</div>
	</form>
</div>

<script>
    var $doctors = <?php echo json_encode($doctors)?>;
    var $selected_doc = <?php echo intval($options['doc_id'])?>;
    jQuery(function ($) {
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


        }).change();
        $('body').on('click','a.show_popup',function () {
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },500);
            return false;
        }).on('click','a.resend',function () {
            if($(this).hasClass('sending'))return false;
            $(this).addClass('sending');
            var $this = $(this);
            var $pr = $(this).parents('tr');
            var $id = $pr.data('id');
            $.ajax({
                data:{trajax:1,act:'send_result',id:$id},
                type:'post',
                dataType:'json',
                success:function ($rs) {
                    $this.removeClass('sending');
                    if($rs.status=='ok'){
                        alert('Sent successfully');
                    }else{
                        alert($rs.msg);
                    }
                }
            });
            return false;
        })
    })
</script>
