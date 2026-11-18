<?php
global $wpdb,$current_role;
include (TRCPDD_PATH.'api/ci_api_medhiwa.php');

$log_dir = WP_CONTENT_DIR.'/log/medhiwa/';
$log_url = get_bloginfo('url').'/wp-content/log/medhiwa/';
if(!file_exists($log_dir))
{
	wp_mkdir_p($log_dir);
}




if(isset($_POST['options'])){
	$options = $_POST['options'];
	if(empty($options['cron']))
	{
		$options['cron'] = 10;
	}
    $old_options = get_option('tr_tr_medhiwa_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_medhiwa_st', $bkdata);
	update_option('tr_medhiwa_st',$options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_medhiwa_st',array());
	//setup cron


	if(isset($options['enable']) && $options['enable']){

		$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$time_mid_night = strtotime(date('Y-m-d',time() + $offset)) -  $offset + 1000;
		if($time_mid_night<time())
		{
			$time_mid_night+=86400;
		}

		$next_cron = wp_next_scheduled('cron_api_medhiwa');
		if(!$next_cron || $next_cron > $time_mid_night)
		{
			wp_clear_scheduled_hook('cron_api_medhiwa');
			wp_schedule_event($time_mid_night,'daily','cron_api_medhiwa');
			Tr_Session_Class::add('Added Schedule');
		}

	}else{
		wp_clear_scheduled_hook('cron_api_medhiwa');
	}
}
$options = get_option('tr_medhiwa_st',array());


try{
	$client = new CI_Api_Medhiwa($options);

}catch(Exception $e)
{
	Tr_Session_Class::add_error($e->getMessage());
}



if($act=='test_sync')
{
	try{
		$client->debug = true;
		$client->sync_reports(1);
		echo str_replace("\n",'<br>',$client->log);

		echo "<br><br>done";
	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}

else if($act=='log')
{
	$joins = '';
	$fields = '';
	$where = " o.mo_file_id='{$client->api_id}' ";

	if(!empty($_REQUEST['s'])){
		$wherechild = array();
		foreach(tr_pdd_get_batch_types() as $btype)
		{
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$sql_c = $wpdb->prepare(" select mid from {$batch_tbl} where assen like %s or patient_name like %s ",
				$_REQUEST['s'].'%','%'.$_REQUEST['s'].'%');
			$mids = $wpdb->get_col($sql_c);
			if(count($mids)>0)
			{
				$mids = implode(',',$mids);
				$wherechild[] = " ( mo_btype='{$btype}' and mo_mid in ({$mids}) ) ";
			}
		}

		if(count($wherechild)>0)
		{
			$wherechild = implode(' or ',$wherechild);
			$where.= " and ".$wherechild;
		}else{
			$where.= " and mo_ID = -1 ";
		}

	}

	$row_per_page = 200;

	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c 
	                                from ci_sync_api_orders o
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select o.*
                            from ci_sync_api_orders o
                            where {$where} 
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");

	ob_start();

	?>
	<div>
		<input type="search" id="assen_search" value="<?php echo $_REQUEST['s']?>" placeholder="Accession or patient name">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s'=>''))?>">Search</a>
	</div>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
		<tr>
			<th style="width:60px">#</th>
			<th style="width:50px">type</th>
			<th>Accession</th>
			<th>Patient</th>
			<th>Clinic</th>
			<th>Doctor</th>
			<th>Sent PDF</th>
			<th>File</th>
		</tr>
		</thead>
		<tbody>
		<?php
		$codes = array();
		foreach($rows as $row)
		{
			$batch_tbl = tr_pdd_get_table_by_btype($row->mo_btype);
			$data = $wpdb->get_row("select * from {$batch_tbl} ba
		                    where ba.mid = {$row->mo_mid}");
			?>
			<tr class="childp" data-id="<?php echo $row->id?>">
				<td><?php echo $data->mid?></td>
				<td><?php echo $row->mo_btype?></td>
				<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->mo_btype.'_'.$data->mid?>&"><?php echo $data->assen?></a></td>
				<td><?php echo $data->patient_name?></td>
				<td><?php echo $data->requesting_practice?></td>
				<td><?php echo $data->requesting_physician?></td>
				<td><?php echo $row->mo_peg_ID?></td>
				<td title="<?php echo $row->mo_test_ID?>">
					<a target="_blank" href="<?php echo '?page=welcome&trajax=1&fot=medhiwa&viewfile='.$row->mo_control_ID?>"><?php echo $row->mo_control_ID?></a>
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<?php

	if(isset($_GET['get_codes'])){
		echo implode(', ',$codes);
	}
	$content = ob_get_clean();
	if(isset($_GET['trajax'])){
		$content = str_replace('class="','class="show_popup ',$content);
		$content = str_replace('class=\'','class=\'show_popup ',$content);
	}
	echo $content;
	exit;
}



?>

<div class="wrap">
	<h2>Medhiwa Settings</h2>
	<?php Tr_Session_Class::show()?>

	<form method="post">
		<div class="frow">
			<div class="col2">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable'])? $options['enable'] : '')?>/>
				</div>


				<div class="row">
					<label>Host</label>
					<input type="text" name="options[url]" value="<?php echo isset($options['url'])? $options['url'] : ''?>" required/>
				</div>
				<div class="row">
					<label>Key</label>
					<input type="text" name="options[key]" value="<?php echo isset($options['key'])? $options['key'] : ''?>" required/>
				</div>

			</div>

			<div class="col2">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes'])? $options['notes'] : ''?></textarea>
			</div>
		</div>


		<div class="row">
			<input type="submit" value="Save" class="button-primary"/> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'test_sync'))?>">Test Sync</a> &nbsp;&nbsp;

			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'log'));if(isset($_GET['debug']))echo '&debug=1'?>&width=1200">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'1','act'=>'viewlog'));?>">Log File</a>
		</div>

		<?php

		echo 'Now: '.date_i18n('Y-m-d H:i:s').'<br>';


		$next_cron      = wp_next_scheduled('cron_api_medhiwa');
		$current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;


		$last_cron = file_exists(WP_CONTENT_DIR.'/log/log_api_medhiwa.txt') ? filemtime(WP_CONTENT_DIR.'/log/log_api_medhiwa.txt') : 0;

		if($last_cron)
		{
			echo 'Last Cron: '.date('Y-m-d H:i:s',$last_cron+$current_offset).'<br>';
		}

		if($next_cron)
		{
			echo 'Next Cron: '.date('Y-m-d H:i:s',$next_cron+$current_offset).'<br>';
		}
		?>
	</form>
</div>
<style>
	.editfield{
		max-width:95%;
	}
</style>

<script>
    var $doctors = <?php echo json_encode(isset($doctors)? $doctors : array())?>;
    var $selected_doc = <?php echo intval(isset($options['doc_id'])? $options['doc_id'] : 0)?>;
    jQuery(function ($) {
        function save_ids_data($this)
        {
            var $pr = $this.parent();
            var $tr = $pr.parent();
            var $vl = $this.val();
            var $col = $this.data('k');
            var $id = $tr.data('id');

            $pr.append('<div class="sloading"></div>');
            $.ajax({
                data:{act:'save_ids',trajax:1,col:$col,id:$id,val:$vl},
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

        $('[name="options[cli_id]"]').change(function () {
            var $cli_id = $(this).val();
            var $html = '<option value="">Select Doctor</option>';
            for(i in $doctors)
            {
                var $doctor = $doctors[i];
                if($doctor.cli_ID ==$cli_id)
                {
                    var $selected = $doctor.doc_ID==$selected_doc? 'selected':'';
                    var $docnpi = $doctor.doc_npi? '('+$doctor.doc_npi+')':'';
                    $html+='<option value="'+$doctor.doc_ID+'" '+$selected+'>'+$doctor.doc_first+' '+$doctor.doc_last+$docnpi+'</option>';
                }
            }
            $('[name="options[doc_id]"]').html($html);


        }).change();
        $('body').on('click','a.show_popup',function () {
            if($(this).hasClass('seach_assen'))return;
            var $url = $(this).attr('href');
            //tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },300);
            return false;
        }).on('click','a.resend',function () {
            if(!confirm('Do you want send?'))return false;
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

                        alert($rs.msg);
                    }else{
                        alert($rs.msg);
                    }
                }
            });
            return false;
        }).on('click','a.seach_assen',function () {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function () {
                tb_show('',$url+'&s='+$s);
            },500);
            return false;
        }).on('change','#ma_ids_cb',function () {
            if($(this).is(':checked')){
                $('#clinic_div').hide();
                $('#doctor_div').hide();
            }else{
                $('#clinic_div').show();
                $('#doctor_div').show();
            }
        }).on('keypress','#idstbl input.editfield',function (e) {
            var code = e.keyCode || e.which;
            if(code==13)
            {
                save_ids_data($(this));
                return false;
            }
        }).on('change','#idstbl input.editfield',function () {
            save_ids_data($(this));
        }).on('change', '.duplicate_ids', function() {
				var $has_duplicate = false;
				var $ice_value = $(this).val();
				var $cid = $(this).data('id');
				$('.duplicate_ids').each(function() {
					var $cid2 = $(this).data('id');
					if ($cid != $cid2 && $(this).val() == $ice_value) {
						$has_duplicate = true;
					}
				})
				if ($has_duplicate) {
					alert('Please input a unique code!');
					$(this).val('');
					$(this).focus();
				}
			})

        $('#ma_ids_cb').change();
    })
</script>

