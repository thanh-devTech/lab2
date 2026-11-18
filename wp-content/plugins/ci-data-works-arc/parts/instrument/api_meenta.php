<?php
global $wpdb,$current_role;

include (TRCPDD_PATH.'api/ci_api_meenta.php');

if(isset($_POST['options'])){

    $options=$_POST['options'];
    $old_options = get_option('tr_meenta_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_meenta_st', $bkdata);
	update_option('tr_meenta_st',$_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_meenta_st',array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_meenta');

	if($options['enable']){
		wp_schedule_event(time()+1,'hourly','cron_api_meenta');
	}
}
$options = get_option('tr_meenta_st',array());


if($act=='test_connect')
{
	try{
		$client = new CI_Api_Meenta($options['lab_id'],$options['cli_id'],$options['root_folder']);

		$client->connect();
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';

		$files = $client->scan_dir('/orders');
		echo '<br>List files:<br>';
		foreach($files as $file)
		{
			echo '<li>'.$file.'</li>';
		}

	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	exit;
}else if($act=='test_sync')
{
	try{
		$client = new CI_Api_Meenta($options['lab_id'],$options['cli_id'],$options['root_folder']);
		$client->debug = true;
		//$client->is_test = true;

		$client->connect();
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';
		$client->sync_reqs();
		echo str_replace("\n",'<br>',$client->log);


	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	exit;
}else if($act=='test_upload')
{
	try{
		$client = new CI_Api_Meenta($options['lab_id'],$options['cli_id'],$options['root_folder']);
		$client->debug = true;
		//$client->is_test = true;

		$client->connect();
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';
		$client->sync_reports();
		echo str_replace("\n",'<br>',$client->log);


	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	exit;
}

$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
?>

	<div class="wrap">
		<h2>Meenta Settings</h2>
		<?php Tr_Session_Class::show()?>

		<form method="post">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable'])?>/>
			</div>
			<div class="row">
				<label>Lab</label>
				<select name="options[lab_id]" required>
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
                <select name="options[cli_id]" required>
                    <option value="">Select</option>
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
				<label>Username</label>
				<input type="text" name="options[username]" value="<?php echo $options['username']?>" required/>
			</div>
			<div class="row">
				<label>Password</label>
				<input type="password" name="options[password]" value="<?php echo $options['password']?>" required/>
			</div>
			<div class="row">
				<label>Root Folder</label>
				<input type="text" name="options[root_folder]" value="<?php echo $options['root_folder']?>" required/>
			</div>

			<div class="row">
				<input type="submit" value="Save" class="button-primary"/> &nbsp;&nbsp;
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_connect'))?>&width=1000">Test Connect</a> &nbsp;&nbsp;
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_sync'))?>">Test Sync Orders</a> &nbsp;&nbsp;
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_upload'))?>">Test Upload Result</a>
			</div>
		</form>
	</div>
