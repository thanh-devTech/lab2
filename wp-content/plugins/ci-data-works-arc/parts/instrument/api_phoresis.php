<?php
global $logpath,$wpdb;

include(TRCPDD_PATH.'inc/functions_instrument.php');

$admin_view             = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);
$options = get_option('tr_ins_phoresis_sts',array());

$need_start = false;
if(empty($options['port']))
{
	$options['port'] = 8681;
}
$offtime    = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$ip         = trpdd_get_ip();
$port       = $options['port'];
$path       = TRCPDD_PATH.'run';
$path       = str_replace('//','/',$path);
//$logpath = $path.'/loghl7';
$logpath    = WP_CONTENT_DIR.'/log/phoresis';
$logurl     = Tr_Base_Class_V6::link(array('trajax'=>'viewlog'),false);
if(!file_exists($logpath))
{
	wp_mkdir_p($logpath);
}
$outfile    = $logpath.'/out_'.$port.'.txt';
$error_file = $logpath.'/error_'.$port.'.txt';
$pid_file   = $logpath.'/pid_'.$port.'.txt';
$stop_file  = $logpath.'/stop_'.$port.'.txt';


$log 		= trpdd_get_logfiles($logpath,$port);


if(isset($_GET['trajax']) && $_GET['trajax']=='viewlog'){
	if(empty($_GET['file']))
	{
		$_GET['file'] = '';
		foreach($log['files'] as $file)
		{
			$_GET['file'] = $file;
			break;
		}
	}
	if(isset($_GET['raw'])){
		$_GET['file'] = str_replace('log_','log_raw',$_GET['file']);
	}
	$logfile = $logpath .'/'.$_GET['file'];
	if(isset($_REQUEST['delete'])){
	    unlink($logfile);echo 'Deleted';exit;
    }
	echo '<html><body>';
	echo '<pre>';
	$string = file_get_contents($logfile);
    $string = preg_replace('/[\x14-\x1F\x7F-\xFF]/', '', $string);
	echo htmlentities($string);
	echo '</pre>';
	echo '<div id="endofile"></div>';
	?>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script>
        jQuery(function ($) {
            $("html, body").animate({ scrollTop: $(document).height() }, 300);
        })
	</script>

	</body>
	</html>
	<?php
	exit;
}
else if($act=='load_log_test_server'){
	$return = array('content' => '','time'=>0);
	$filepath = $logpath.'/log_'.$port.'_test.txt';
	if(isset($_REQUEST['clear']) && $_REQUEST['clear']){
		file_put_contents($filepath,'');
	}
	$return['content'] = file_get_contents($filepath);
	$return['time'] = filesize($filepath);
	wp_send_json($return);
	exit;
}
else if($act=='install'){

	?>
	<textarea style="width:100%;height:500px;">
	wget https://github.com/pmmp/pthreads/archive/refs/tags/4.0.0.zip
	unzip 4.0.0.zip
	cd pthrea*
	zts-phpize
	./configure --with-php-config=/usr/bin/zts-php-config
	make
	make install
	echo "extension=pthreads.so" >> /etc/php-zts.d/pthreads.ini
	
	iptables -A INPUT -m state --state NEW -p tcp --dport <?php echo $port?> -j ACCEPT
	service iptables save
	</textarea>
	<?php
	
		exit;
}

else if($act=='list'){


	$joins = '';
	$fields = '';
	$where = " mo_file_id='-16'  and mo_btype='blood'";
	if(!empty($_REQUEST['s'])){
		$where.= " and ba.assen = '{$_REQUEST['s']}' ";
	}

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_blood ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.doc,
                                ba.oldreq,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                re.instrument,
                                re.created_date,
                                pa.pa_first,
                                pa.pa_last,
                                o.mo_status,
                                o.mo_test_ID,
                                o.mo_set_ID,
                                o.mo_control_ID,
                                o.mo_peg_ID
                            from ci_batches_blood ba
                            left join ci_reports_blood re ON re.patient_id = ba.assen
                            left join ci_patients pa ON pa.pa_ID = ba.patient_id
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	?>
	<div>
		<input type="search" id="assen_search">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s'=>''))?>">Search</a>
	</div>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
		<tr>
			<th style="width:20px"></th>
			<th>Accession</th>
			<th>Patient</th>
			<th style="width:90px">Status</th>
			<th style="width:140px;">Sent Order</th>
			<th>Received Result</th>
		</tr>
		</thead>
		<tbody>
		<?php

		foreach($rows as $i => $row)
		{
			?>
			<tr class="childp" data-id="<?php echo $row->id?>" data-mid="<?php echo $row->mid?>">
				<td><?php echo $i +1?></td>
				<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=blood_<?php echo $row->mid?>&short=1"><?php echo $row->assen?></a></td>
				<td><?php echo $row->pa_last.', '.$row->pa_first?></td>
				<td><a href="?page=blood_results&act=edit&id=<?php echo $row->id;?>" target="_blank">
						<?php echo $row->estatus?>
					</a>
				</td>
				<td><?php
					if($row->mo_status==1)
					{
						echo !empty($row->mo_control_ID)? date('Y-m-d H:i:s',strtotime($row->mo_control_ID)):'';
					}else{
						echo 'Error: '.$row->mo_peg_ID;
					}

					?></td>
				<td><?php echo !empty($row->mo_set_ID)? date('Y-m-d H:i:s',strtotime($row->mo_set_ID)):''?></td>

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
else if($act=='bloodcode'){
    $rows = $wpdb->get_results("select te.*,
                g.tg_name
                from ci_test_panels te
                JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
                    where te.te_type = 'blood'
                    and te.te_phoresis !='' 
                    order by CAST(te.te_phoresis as unsigned) asc");
    ?>

    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
        <tr>
            <th>#</th>
            <th>Test Name</th>
            <th>Group</th>
            <th>Code</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach($rows as $row)
        {
            ?>
            <tr>
                <td><?php echo $row->te_ID?></td>
                <td><?php echo $row->te_name?></td>
                <td><?php echo $row->tg_name?></td>
                <td><?php echo $row->te_phoresis?></td>
            </tr>
        <?php
        }
        if(count($rows)==0)
        {
            echo 'not found';
        }
        ?>
        </tbody>
    </table>

    <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'export_blood_code'))?>">Export</a>

    <form method="post" enctype="multipart/form-data">
        <br>
        <label>Import file:</label>
        <input type="file" name="jsonfile"/>
        <input type="submit" class="button" value="Import"/>
        <input type="hidden" name="act" value="import_blood_code"/>
    </form>

<?php
    exit;
}
else if($act=='export_blood_code'){
    
    $rs = $wpdb->get_results("select te_name,tg_name,te_phoresis 
    from ci_test_panels te
    join ci_test_groups tg ON te.te_group_id = tg.tg_ID
    where te_phoresis !='' and te_phoresis is not null
    and te_type = 'blood' ", ARRAY_A);

    tr_pdd_header('application/json','sysmexn530_blood_code.json');

    wp_send_json($rs);

    exit;
}else if($act=='import_blood_code'){
    if(!empty($_FILES['jsonfile']['tmp_name'])){
        $data = json_decode(file_get_contents($_FILES['jsonfile']['tmp_name']),true);
        Tr_Session_Class::add("Count: ".count($data));
        $count_imported = 0;
        foreach($data as $row)
        {
            $testname = $row['te_name'];
            $group    = $row['tg_name'];
            $code     = $row['te_phoresis'];

            $tid = $wpdb->get_var($wpdb->prepare("select te_ID from ci_test_panels te
            join ci_test_groups tg ON tg.tg_ID = te.te_group_id
            where te_name = %s
            and tg_name = %s 
            and tg_type = 'blood' ", $testname,$group));

            if($tid==0)
            {
                $tid = $wpdb->get_var($wpdb->prepare("select te_ID from ci_test_panels te
                where te_name = %s
                and te_type = 'blood' ", $testname));
            }

            if($tid>0)
            {
                $wpdb->update('ci_test_panels',
                array('te_phoresis' => $code),
                array('te_ID'=> $tid));
                $count_imported ++;
            }
        }
        Tr_Session_Class::add("Count Imported: ".$count_imported);

        Tr_Session_Class::add("Imported Successfully!");
    }
}
else if($act=='test'){

	$patientdata['pa_ID'] = 'DEMO00001';
	$patientdata['pa_first'] = 'Test';
	$patientdata['pa_last'] = 'Test';
	$patientdata['pa_dob'] = '1987-05-01';
	$patientdata['pa_gender'] = 'M';
	$age = trdd_dob2ages($patientdata['pa_dob']);
	$department = 'ABC';
	$doc = '2022-04-01';
	$Concentration = 'ee';
	$frees = array(1,2,3);
	$Ratio_1 = 1.1;
	$Ratio_2 = 12.1;

	$msg3 = '<STX>';
	$msg3.= 6;
	$msg3.= fillzero(1,4);
	$msg3.= fill_space($patientdata['pa_ID'],15);
	$msg3.= fill_space($patientdata['pa_first'].' '.$patientdata['pa_last'],30);
	$msg3.= format_date($patientdata['pa_dob'],'dmY');
	$msg3.= $sex=='M'?'M':'F';
	$msg3.= fillzero($age,3);
	$msg3.= fill_space($department,20);
	$msg3.= format_date($doc,'dmY');
	$msg3.= fill_space($Concentration,5);
	$msg3.= fill_space($frees[0],30);
	$msg3.= fill_space($frees[1],30);
	$msg3.= fill_space($frees[2],30);
	$msg3.= fill_space($frees[3],30);
	$msg3.= fill_space($frees[4],30);
	$msg3.= '<ETX>';


	$Fractions = array();
	$num_Fractions = count($Fractions);
	if($num_Fractions>10)$num_Fractions=10;

	//247bytes
	$msg5 = '<STX>';
	$msg5.= 6;
	$msg5.= fillzero($number,4);
	$msg5.= fill_space($patientdata['pa_ID'],15);
	$msg5.= fill_space($patientdata['pa_first'].' '.$patientdata['pa_last'],30);
	$msg5.= format_date($patientdata['pa_dob'],'dmY');
	$msg5.= $patientdata['pa_gender']=='M'?'M':'F';
	$msg5.= fillzero($age,3);
	$msg5.= fill_space($department,20);
	$msg5.= format_date($doc,'dmY');
	$msg5.= fill_space($Concentration,5);
	$msg5.= fill_space($unit_concentration,8);
	$msg5.= fill_space($frees[0],30);
	$msg5.= fill_space($frees[1],30);
	$msg5.= fill_space($frees[2],30);
	$msg5.= fill_space($frees[3],30);
	$msg5.= fill_space($frees[4],30);
	$msg5.= fill_space($Operator_ID,3);
	$msg5.= format_date($Date_of_Analysis,'dmY');
	$msg5.= fillzero($num_Fractions,2);
	$name_msg = '';
	$percent_msg = '';
	$conc_msg = '';
	for($i=0;$i<10;$i++)
	{
		$name_msg.= fill_space(isset($Fractions[$i])? $Fractions[$i]['name']:'',10);
		$percent_msg .= fill_space(isset($Fractions[$i])? $Fractions[$i]['percent']:'',5);
		$conc_msg .= fill_space(isset($Fractions[$i])? $Fractions[$i]['conc']:'',5);
	}
	$msg5.= $name_msg;
	$msg5.= $percent_msg;
	$msg5.= $conc_msg;

	$name_msg = '';
	$percent_msg = '';
	$conc_msg = '';
	for($i=0;$i<4;$i++)
	{
		$name_msg.= fill_space(isset($Peaks[$i])? $Peaks[$i]['name']:'',10);
		$percent_msg .= fill_space(isset($Peaks[$i])? $Peaks[$i]['percent']:'',5);
		$conc_msg .= fill_space(isset($Peaks[$i])? $Peaks[$i]['conc']:'',5);
	}
	$msg5.= $name_msg;
	$msg5.= $percent_msg;
	$msg5.= $conc_msg;

	$msg5.= $Pathological_Flag? 1:0;
	$msg5.= fill_space($Ratio_1,5);
	$msg5.= fill_space($Ratio_2,5);
	$msg5.= fill_space($Comment,230);
	$msg5.= $Reference_pattern_flag? 1:0;
	$msg5.= $QC_sample_flag? 1:0;

	$msg5.= '<ETX>';
  ?>
    <div class="frow">
        <a class="button ws_send" data-msg="<STX>600010002<ETX>">Send msg1</a>
        <a class="button ws_send" data-msg="<STX><ACK><ETX>">Send msg2</a>
        <a class="button ws_send" data-msg="<?php echo $msg3?>">Send msg3</a>
        <a class="button ws_send" data-msg="<STX><NAK><ETX>">Send msg4</a>
        <a class="button ws_send" data-msg="<?php echo $msg5?>">Send msg5</a>
        <a class="button ws_send" data-msg="<STX>6ABC            <ETX>">Send msg6</a>
        <a class="button ws_send" data-msg="<STX><EOT><ETX>">Send msg7</a>
    </div>
    <div class="frow">
        <input type="text" id="text_send" style="width:70%"/>
        <a class="button send_msgbtn">Send</a>
        <a class="button clear_logbtn">clear</a>
    </div>
    <div class="frow">
        <div class="col2">
            <textarea id="ws_receving" style="width:100%;height:300px" readonly></textarea>
        </div>
        <div class="col2">
            <textarea id="log_server" style="width:100%;height:300px" readonly></textarea>
        </div>
    </div>
    <script>
        testclient.connect();
        testclient.cronloadlog();
    </script>
    <?php
    exit;
}




if(isset($_POST['start_stop']) || isset($_POST['restart']))
{
	trpdd_update_config_file('phoresis');

	//stop that port first
	trpdd_stop_port($port, $pid_file, $stop_file);
	sleep(3);
	$started = trpdd_port_opened($port);


	if(!$started && stripos($_POST['start_stop'],'start')!==false || isset($_POST['restart']))
	{
		unlink($pid_file);
		$command = "nohup zts-php {$path}/phoresis.php port {$port} {$logpath} > {$outfile} 2> {$error_file} < /dev/null &";


		$rs = exec($command,$output);

		if(isset($_GET['debug'])){
			var_dump($command);echo '<br><br>';
			var_dump($output);
			var_dump($rs);
		}

		$need_start = true;
		sleep(2);
		$started = trpdd_port_opened($port);
		if($started)
		{
			$options['started'] = true;
			update_option('tr_ins_phoresis_sts',$options);
		}
	}

	//if stop
	if(stripos($_POST['start_stop'],'stop')!==false)
	{
		$options['auto_start'] = false;
		$options['started'] = false;
		update_option('tr_ins_phoresis_sts',$options);
	}

}
else if(isset($_POST['options'])){
	$options = $_POST['options'];
    $old_options = get_option('tr_ins_phoresis_sts', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_ins_phoresis_sts', $bkdata);
	update_option('tr_ins_phoresis_sts',$options);
	Tr_Session_Class::add("Settings Saved");
}

$started = trpdd_port_opened($port);

if($started)
{
	$options['started'] = true;
	update_option('tr_ins_phoresis_sts',$options);
}

if($need_start && $started)
{
	$options['auto_start'] = true;
	update_option('tr_ins_phoresis_sts',$options);
	Tr_Session_Class::add("Started at port {$port} ");
}

if(!$options['auto_start'])$options['auto_start']= true;



wp_enqueue_script('instrument',TRCPDD_URL.'js/instrument.js',array(),time());
wp_add_inline_script('instrument','var $ws_host = "'.$_SERVER['SERVER_ADDR'].'",$ws_port='.$port.';','before');

?>
<div class="wrap">
	<?php Tr_Session_Class::show()?>

	<div style="border:1px dashed #ddd;width:400px;margin:30px auto;padding:30px;">
		<form method="post">
			<?php if(!$started){?>
				<div class="frow">
					<label class="inline">Port: </label>
					<input type="number" min="100" max="9999" name="options[port]" value="<?php echo $options['port']?>" <?php if($started) echo 'readonly'?> style="width:90px"/>
				</div>
				<div class="frow">
					<label class="inline">Enable Study: </label>
					<input type="checkbox" name="options[study]" value="1" <?php checked($options['study'])?>/>
				</div>
				<div class="frow">
					<label class="inline">Auto Start: </label>
					<input type="checkbox" name="options[auto_start]" value="1" <?php checked($options['auto_start'])?>/>
				</div>
				<div class="frow">
					<input type="submit" class="button-primary" value="Save"/>

				</div>
				<?php
			}else{

				echo '<div>connected: '.trpdd_count_connected('phoresis.php',$port).'</div>';
				if($log['lasttime']>0)
				{
					echo '<div>Last Msg: '.date('Y-m-d H:i:s',$log['lasttime']+$offtime).'</div>';
					echo '<div><label style="padding-right:29px">Now</label>: '.date_i18n('Y-m-d H:i:s').' </div>';
				}
			}
			?>

			<div style="padding:15px 0">
				Server IP: <?php echo $_SERVER['SERVER_ADDR']?><br>
				Status: <?php echo $started? 'Started at port '.$options['port'] : 'stopped'?>
			</div>


			<input type="submit" class="button" name="start_stop" value="<?php echo $started? 'Stop':'Start'?>"/>
			<?php if($started){?>
				<input type="submit" class="button" name="restart" value="Restart"/>
                <?php 
				if(isset($_GET['debug']) || $admin_view){
					?>
					<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1200">Test</a> &nbsp;&nbsp;
					<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'install'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1200">Install</a> &nbsp;&nbsp;
					<?php
				}
			}?>

			<div style="margin-top:25px">
				Log file:
				<select id="listlog">
					<?php
					foreach($log['files'] as $f)
					{
					    $filesize = filesize($logpath.'/'.$f);

						if($filesize==0 || isset($_REQUEST['clear_log']))
						{
							unlink($logpath.'/'.$f);
							continue;
						}else if(($filesize<30 || stripos($f,'test')>0) && !isset($_GET['debug']))
						{
							continue;
						}
						echo '<option value="'.$f.'">'.$f.'</option>';
					}
					?>
				</select>
				
				<a class="button viewlog">View Log</a>
				<a class="button viewlograw">View raw</a>
				<br>
				<br>

				<a class="button" href="<?php echo $logurl.'&file=out_'.$port.'.txt'?>" target="logc">Log connected</a>
				<a class="button" href="<?php echo $logurl.'&file=error_'.$port.'.txt'?>" target="logc">Log Errors</a>
				<br>
				<br>

				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'list','btype'=>'blood'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000" title="Blood List">Blood Log</a> &nbsp;&nbsp;
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'bloodcode'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000" title="Blood Codes">Blood Codes</a> &nbsp;&nbsp;
                
			</div>

		</form>
	</div>
</div>


<?php include('form_white_ips.php')?>



<script>
    jQuery(function ($) {
        $('body').on('click','[name="start_stop"]',function () {
            var act = $(this).val();
            if(act=='Stop' && confirm("Do you want "+act+'?')==false)
            {
                return false;
            }
        })
		.on('click','a.show_popup',function () {
            if($(this).hasClass('seach_assen'))return;
            var $url = $(this).attr('href');
            //tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },300);
            return false;
        })
		.on('click','a.seach_assen',function () {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function () {
                tb_show('',$url+'&s='+$s);
            },300);
            return false;
        })
        $('a.viewlog').click(function () {
            var url = '<?php echo $logurl.'&file='?>'+$('#listlog').val();
            window.open(url, 'viewlog');
        });
        $('a.viewlograw').click(function () {
            var url = '<?php echo $logurl.'&raw=1&file='?>'+$('#listlog').val();
            window.open(url, 'viewlograw');
        });
    })
</script>