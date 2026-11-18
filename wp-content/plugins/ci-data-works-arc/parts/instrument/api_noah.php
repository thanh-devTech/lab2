<?php
global $wpdb,$current_role;

include (TRCPDD_PATH.'api/ci_api_noah.php');
$dir = dirname(ABSPATH).'/noah/';
$order_dir = $dir.'orders/';
$order_log_dir = $dir.'archive/';
$result_dir = $dir.'results/';

if(isset($_POST['options'])){

    $options=$_POST['options'];
    $old_options = get_option('tr_noah_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_noah_st', $bkdata);
	update_option('tr_noah_st',$_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_noah_st',array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_noah');

	if($options['enable']){
		wp_schedule_event(time()+1,'10min','cron_api_noah');
		wp_mkdir_p($dir);
		wp_mkdir_p($order_dir);
		wp_mkdir_p($order_log_dir);
		wp_mkdir_p($result_dir);
	}
}
$options = get_option('tr_noah_st',array());
$client = new CI_Api_Noah($options['lab_id'],$options['cli_id'],$options['doc_id']);
if($act=='test_sync')
{
	try{

		$client->debug = true;
		$client->sync_reqs($order_dir);
		echo str_replace("\n",'<br>',$client->log);


	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}

else if($act=='test_sync_report')
{
	try{
		//$result_dir = WP_CONTENT_DIR;
		$client->debug = true;
		$client->sync_reports($result_dir);
		echo str_replace("\n",'<br>',$client->log);

	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}

$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$docs = $wpdb->get_results("select * from ci_doc where doc_first!='' order by doc_first asc");
?>

<div class="wrap">
    <h2>NOAH Settings</h2>
	<?php Tr_Session_Class::show()?>

    <form method="post">
        <div class="row">
            <label for="for_enable">Enable </label>
            <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable'])?>/>
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
            <select name="options[cli_id]"  class="njs_select2">
                <option value="">In file</option>
				<?php
				foreach($clinics as $clinic)
				{
					$selected = $clinic->cID == $options['cli_id']?'selected':'';
					echo '<option value="'.$clinic->cID.'" '.$selected.'>'.$clinic->cli_clinic.'</option>';
				}
				?>
            </select>
        </div>
        <div class="row">
            <label>Doctor</label>
            <select name="options[doc_id]"  class="njs_select2">
                <option value="">In file</option>
				<?php
				foreach($docs as $doc)
				{
					$selected = $doc->doc_ID == $options['doc_id']?'selected':'';
					echo '<option value="'.$doc->doc_ID.'" '.$selected.'>'.$doc->doc_first.' '.$doc->doc_last.' - '.$doc->doc_npi.'</option>';
				}
				?>
            </select>
        </div>

        <div class="row">
            <input type="submit" value="Save" class="button-primary"/>
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_sync'))?>">Test Sync Orders</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_sync_report'))?>">Test Sync Reports</a> &nbsp;&nbsp;
        </div>
    </form>
</div>

<script>
    jQuery(function ($) {
        $('body').on('click','a.show_popup',function () {
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },500);
            return false;
        })
    })
</script>