<?php
global $wpdb;

include(TRCPDD_PATH.'api/ci_api_provista.php');


$act = isset($_REQUEST['act'])? $_REQUEST['act'] : '';

$api_url = get_bloginfo('url').'/provista/InsertResult';

if(isset($_POST['options'])){
	$options = $_POST['options'];
	if(empty($options['cron']))
	{
		$options['cron'] = 2;
	}
    $old_options = get_option('tr_api_provista_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_api_provista_st', $bkdata);
	update_option('tr_api_provista_st',$options);


	Tr_Session_Class::add('Saved Settings Successfully');

	if($options['enable']){
		wp_clear_scheduled_hook('cron_api_provista');
		wp_schedule_event(time()+1,$options['cron'].'min','cron_api_provista');

		Tr_Session_Class::add('Added Schedule');

	}else{
		wp_clear_scheduled_hook('cron_api_provista');
    }

}
$options = get_option('tr_api_provista_st',array());


$api = new CI_Api_Provista($options);
$api->isTest = true;

if($act=='test_connect'){
	$api->test();
	exit;
}else if($act=='test_send_order'){
    if($api->authenticate())
    {
	    $rs = $api->sync_reqs(1);
	    var_dump($rs);
	    var_dump($api->log);
    }else{
        echo 'Cant get token';
    }
    exit;
}else if($act=='log_orders'){

	$covid_status = array(
		'0' => 'Negative',
		'1' => 'Positive',
		'2' => 'ReRun',
		'3' => 'Inconclusive',
		'4' => 'Inconclusive',
	);
	$joins = '';
	$fields = '';
	$where = " o.mo_file_id = '{$api->api_id}' ";

	if(!empty($_REQUEST['s'])){
		$where.= " and ba.assen like '{$_REQUEST['s']}%' ";
	}

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
	                                where {$where}"));
	$offset = $pagination->offset();
	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.paid,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.*
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	//var_dump($wpdb->last_error);
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
            <th style="width:60px">Status</th>
            <th>Sent Order</th>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach($rows as $row)
		{
			if($row->paid != $client->api_id)
			{
				$wpdb->update('ci_batches_pcr',array('paid'=>$client->api_id),array('mid'=>$row->mid));
			}
			?>
            <tr class="childp" data-id="<?php echo $row->id?>">
                <td><?php echo $row->mid?></td>
                <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid?>&short=1"><?php echo $row->assen?></a></td>
                <td><?php echo $row->patient_name?></td>
                <td><?php echo $row->requesting_practice?></td>
                <td><?php echo $row->requesting_physician?></td>
                <td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;if(isset($_GET['debug']))echo '&createnew='.$row->mid?>" target="_blank">
						<?php echo $row->estatus=='C'?'Yes':'.'?>
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
                <td><?php echo $row->mo_control_ID?></td>
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
else if($act=='log_results'){

	$covid_status = array(
	     '' => '',
		'0' => 'Negative',
		'1' => 'Positive',
		'2' => 'ReRun',
		'3' => 'Inconclusive',
		'4' => 'Inconclusive',
	);

	$joins = '';
	$fields = '';
	$where = " mo_file_id='{$api->api_id}' and o.mo_peg_ID > '2022-01-01' ";

	if(!empty($_REQUEST['s'])){
		$where.= " and ba.assen like '{$_REQUEST['s']}%' ";
	}

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_pcr ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='pcr' 
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.paid,
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.*
                            from ci_batches_pcr ba
                            left join ci_reports_pcr re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='pcr'
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	//var_dump($wpdb->last_error);
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
            <th style="width:60px">Status</th>
            <th>Date</th>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach($rows as $row)
		{
			if($row->paid != $client->api_id)
			{
				$wpdb->update('ci_batches_pcr',array('paid'=>$client->api_id),array('mid'=>$row->mid));
			}
			?>
            <tr class="childp" data-id="<?php echo $row->id?>">
                <td><?php echo $row->mid?></td>
                <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=pcr_<?php echo $row->mid?>&short=1"><?php echo $row->assen?></a></td>
                <td><?php echo $row->patient_name?></td>
                <td><?php echo $row->requesting_practice?></td>
                <td><?php echo $row->requesting_physician?></td>
                <td><a href="?page=pcr_results&act=edit&id=<?php echo $row->id;if(isset($_GET['debug']))echo '&createnew='.$row->mid?>" target="_blank">
						<?php echo $row->estatus=='C'?'Yes':'.'?>
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
                <td><?php echo $row->mo_peg_ID?></td>
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



$ref_labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2) order by lab_dba_init asc");
$clinics    = $wpdb->get_results("select * from ci_clinic where cli_clinic !='' order by cli_clinic");
if(!isset($options['cli_IDs']) || !is_array($options['cli_IDs']))
{
	$options['cli_IDs'] = array();
}
if(!isset($options['max_threads']))
{
	$options['max_threads'] = 1;
}
?>

<div class="wrap">
    <h2>Provista/TruMed (PCR)</h2>
	<?php Tr_Session_Class::show()?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <div class="row">
                    <label for="for_enable">Enable </label>
                    <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable'])?>/>
                </div>
                <div class="row">
                    <label>Ref Lab</label>
                    <select name="options[ref_lab_ID]">
                        <option value="">Select Ref Lab</option>
			            <?php
			            foreach($ref_labs as $l)
			            {
				            $selected = $l->lab_ID == $options['ref_lab_ID']? 'selected':'';
				            echo '<option value="'.$l->lab_ID.'" '.$selected.'>'.$l->lab_name.'</option>';
			            }
			            ?>
                    </select>
                </div>
                <div class="row">
                    <label>Or Clinics</label>
                    <select name="options[cli_IDs][]"  multiple style="height:200px" class="js_select2_tag">
			            <?php
			            foreach($clinics as $cli)
			            {
				            $selected = in_array($cli->cID,$options['cli_IDs'])? 'selected':'';
				            echo '<option value="'.$cli->cID.'" '.$selected.'>'.$cli->cli_clinic.'</option>';
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
                    <label>Facility</label>
                    <input type="text" name="options[facility]" value="<?php echo $options['facility']?>" required/>
                </div>
                <div class="row">
                    <label>Email Domain</label>
                    <input type="text" name="options[domain]" value="<?php echo $options['domain']?>" required/>
                </div>
                <div class="row">
                    <label>Start Date</label>
                    <input type="text" class="hasdate" name="options[start_date]" value="<?php echo $options['start_date']?>" required/>
                </div>
                <div class="row">
                    <label>Schedule</label>
                    <select name="options[cron]">
			            <?php
			            foreach(array(1,2,4,6,8,10,15,30) as $m)
			            {
				            $selected = $m == $options['cron']? 'selected':'';
				            echo '<option value="'.$m.'" '.$selected.'>each '.$m.' min(s)</option>';
			            }
			            ?>
                    </select>
                </div>
                <?php if(function_exists('pcntl_fork')){?>
                <div class="row">
                    <label>Threads</label>
                    <input type="number" name="options[max_threads]" value="<?php echo $options['max_threads']?>"  style="width:90px"/>
                </div>
                <?php }?>

            </div>
            <div class="col2">
                <div style="width:50%;float:right">
                    <div class="frow">
                        <label>Notes</label>
                        <textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes'])?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <input type="submit" value="Save" class="button-primary"/> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_connect'))?>">Get Token</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_send_order'))?>">Test Send Order</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'log_orders'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000">Log Orders</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'log_results'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1000">Log Results</a> &nbsp;&nbsp;
            <a class="button" target="_blank" href="<?php echo '/wp-content/log/log_api_provista.txt'?>">Log Cron</a>
        </div>

        <div class="frow">
            <label>URL</label>
            <input type="text" readonly value="<?php echo $api_url?>"/>
        </div>

        <div class="row">
	        <?php
	        $btype      = 'pcr';
	        $batch_tbl  = tr_pdd_get_table_by_btype($btype);
	        $where      = "";
	        $date_timenow = date_i18n('Y-m-d H:i:s');
	        $limit_up_date = date('Y-m-d', strtotime('+1day'));
	        $limit_received = date('Y-m-d',strtotime('-1day'));
	        if ( ! empty( $options['start_date'] ) ) {
		        $where .= " and (ba.batch_created >= '{$options['start_date']}' or api.mo_ID is not null)";
	        }
	        $where.= " and ba.doc <= '{$date_timenow}' ";
	        $where.= " and ba.cli_ID>0 and ba.doc_ID >0 and ba.patient_id > 0 ";
	        //$where.= " and ba.spec_received > '{$limit_received}' ";

	        $where_lc = array();
	        if($options['ref_lab_ID']>0)
            {
	            $where_lc[] = " ba.ref_lab_ID = {$options['ref_lab_ID']} ";
            }
            if(is_array($options['cli_IDs']) && count($options['cli_IDs'])>0)
            {
	            $cli_ids_str = implode(',',$options['cli_IDs']);
	            $where_lc[] = " ba.cli_ID in ({$cli_ids_str})";
            }
            if(count($where_lc)>0)
            {
                $where.= " and (".implode(' or ',$where_lc).")";
            }

	        $covid_group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
							where tg_type='{$btype}'
							and tg_is_covid = 1");

	        $where.= " and FIND_IN_SET('GROUP_{$covid_group_id}', panel_tests_requested ) ";


	        $sql = "select 
                        count(*) as count_reqs,
                        SUM(IF(api.mo_status=1,1,0)) as count_sent,
                        SUM(IF(api.mo_peg_ID > '2022-01-01',1,0)) as count_results
                        from ci_batches_pcr ba 
                        left join ci_sync_api_orders api ON api.mo_mid = ba.mid 
                                                and api.mo_btype='pcr'
                                                and api.mo_file_id = {$api->api_id}
                        where 1
                        {$where}";
	        $count = $wpdb->get_row($sql);

	        if(isset($_GET['debug'])){
	            echo $sql;echo '<br>';
            }

	        echo '<br>';
	        echo 'Reqs: '.$count->count_reqs.'<br>';
	        echo 'Sent: '.$count->count_sent.'<br>';
	        echo 'Left: '.($count->count_reqs - $count->count_sent).'<br>';
	        echo 'Results: '.$count->count_results.'<br>';

	        echo 'Now: '.date_i18n('Y-m-d H:i:s').'<br>';

	        $next_cron = wp_next_scheduled('cron_api_provista');
	        $current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;

	        $last_cron = @filemtime(WP_CONTENT_DIR.'/log/log_api_provista_lastcron.txt');
	        if($last_cron)
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

<script>
    jQuery(function ($) {
        $('.js_select2_tag').select2({
            tags:true,
            createTag: function () {
                return false;
            }
        });
        $('body').on('click','a.show_popup',function () {
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
    })
</script>