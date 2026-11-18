<?php
global $wpdb,$current_role;

$log_dir = WP_CONTENT_DIR.'/log/billing_sftp7/';
if(!file_exists($log_dir))
{
	wp_mkdir_p($log_dir);
}
include (TRCDBI_PATH.'apis/ci_api_billing_sftp_hl7.php');


if(isset($_POST['options'])){

	update_option('tr_billing_hl7_sftp',$_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_billing_hl7_sftp',array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_billing_sftp_hl7');

	if($options['enable']){
		wp_schedule_event(time()+1,'10min','cron_api_billing_sftp_hl7');
	}
}
$options = get_option('tr_billing_hl7_sftp',array());




try{
	$client = new CI_Api_Billing_Sftp_Hl7($options['lab_id'],$options['cli_id'],$options['doc_id']);

}catch(Exception $e)
{
	Tr_Session_Class::add_error($e->getMessage());
}

if($act=='test_connect')
{
	try{

		$path = isset($_REQUEST['path'])? $_REQUEST['path']:'/';
		$client->connect($options['host']);
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';


		$files = $client->scan_dir($path);
		if($path!='/'){
			$path.= '/';
		}else{
			//$rs = $client->mkdir('/results');
			//var_dump($rs);
		}

		echo '<br>List files:<br>';
		foreach($files as $file)
		{
			if(strpos($file,'.')>0)
			{
				if(stripos($path,'archive')!==false)
				{
					//$client->delete($path.$file);
				}
				echo '<li><a href="'.Tr_Base_Class_V6::link(array('act'=>'readfile','path'=> $path.$file),false).'" class="show_popup">'.$file.'</a></li>';
			}else{
				echo '<li><a href="'.Tr_Base_Class_V6::link(array('act'=>'test_connect','path'=> $path.$file),false).'" class="show_popup">'.$file.'</a></li>';
			}

		}

	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}
else if($act=='readfile')
{
	try{
		$client->connect($options['host']);
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';

		$tmp_path = tempnam('/tmp','test');
		$client->receiveFile($_REQUEST['path'], $tmp_path);
		echo 'reading file '.$_REQUEST['path'].'<br><br>';
		echo '<textarea style="width:100%;height:400px">';
		echo file_get_contents($tmp_path);
		echo '</textarea>';
		unlink($tmp_path);

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
		$client->debug = true;
		$client->connect($options['host']);
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';
		$client->sync_reports($options['results_folder']);
		echo str_replace("\n",'<br>',$client->log);
		$files = $client->scan_dir($options['results_folder']);
		echo '<br>List files in Results folder:<br>';
		foreach($files as $file)
		{
			echo '<li>'.$file.'</li>';
		}



	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}
else if($act=='test_upload')
{
	try{
		$file_path = $options['results_folder'].'/test.txt';
		$client->debug = true;
		$client->connect($options['host']);
		$client->login($options['username'], $options['password']);
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp','test');
		file_put_contents($tmp_path,'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($options['results_folder']);
		echo '<br>List files in Results folder:<br>';
		foreach($files as $file)
		{
			echo '<li>'.$file.'</li>';
		}
		echo 'Reading file:<br><br>';
		$client->receiveFile($file_path, $tmp_path);
		echo file_get_contents($tmp_path);
		echo '<br>';
		echo '<br>Deleting file:'.$file_path;
		$client->delete($file_path);
		echo '=>deleted';


		echo str_replace("\n",'<br>',$client->log);


	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}
else if($act=='demo_msg'){
	$client->debug = true;
	$files = $client->sync_reports(false,1);

	foreach($files as $i => $file)
	{
		$msg = file_get_contents($file);
		?>
        <form method="post" class="sendmsg_form" style="border:1px solid #ddd;padding:10px 5px;margin-bottom:15px">
            <div>Message #<?php echo $i+1?></div>
            <div class="frow row">
                <textarea name="msg" style="width:100%;height:150px;white-space: nowrap"><?php echo $msg?></textarea>
            </div>
            <div class="frow row">
                <input type="submit" class="button" style="margin-right:30px" name="submit" value="Send"/>

                <input type="hidden" name="act" value="send_test_msg"/>
            </div>
        </form>
        <br>
		<?php
	}
	exit;
}
else if($act=='send_test_msg')
{
	$return = array('status'=>'ok','msg'=>'Sent successfully!');
	try{

		$msg = stripslashes( $_POST['msg'] );
		$client->debug = true;
		$client->connect($options['host']);
		$client->login($options['username'], $options['password']);

		$tmp_path = tempnam('/tmp','demotmp.hl7');
		file_put_contents($tmp_path,$msg);
		if($client->uploadFile($tmp_path, $options['results_folder'].'/filedemo.hl7')){

		}else{
			$return['status'] = 'Error';
		}

	}catch (Exception $e)
	{

	}
	if(isset($_REQUEST['trajax'])){
		wp_send_json($return);
	}

}

?>

<div class="wrap">
    <h2>Genius Settings</h2>
	<?php Tr_Session_Class::show()?>

    <form method="post">
        <div class="row">
            <label for="for_enable">Enable </label>
            <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable'])?>/>
        </div>

        <div class="row">
            <label>Host</label>
            <input type="text" name="options[host]" value="<?php echo $options['host']?>" required/>
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
            <label>Results Folder</label>
            <input type="text" name="options[results_folder]" value="<?php echo $options['results_folder']?>" required/>
        </div>

        <div class="row">
            <input type="submit" value="Save" class="button-primary"/> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_connect'))?>">Test Connect</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_upload'))?>">Upload a test file</a>
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'demo_msg'))?>">Demo Msg</a>
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
        }).on('submit','form.sendmsg_form',function () {
            var $form = $(this);
            if($('.sending',$form).length>0)return false;
            $('.success',$form).remove();

            $form.append('<div class="sending">Sending...</div>');
            $.ajax({
                type:'post',
                dataType:'json',
                data:$(this).serialize()+'&trajax',
                success:function (json) {
                    $('.sending',$form).remove();
                    if(json.status=='ok'){
                        $form.append('<div class="success">'+json.msg+'</div>');

                    }
                    else if(json.msg)
                    {
                        alert(json.msg);
                    }
                    else{
                        alert('Error. Please connect Internet!');
                    }
                },
                error:function () {
                    $('.sending',$form).remove();
                    alert('Error. Please connect Internet!');
                }
            });
            return false;
        })
    })
</script>