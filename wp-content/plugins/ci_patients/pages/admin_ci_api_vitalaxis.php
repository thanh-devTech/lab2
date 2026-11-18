<?php
global $wpdb;

include (TRCPPR_PATH.'api/ci_api_vital_axis.php');

if(isset($_POST['data'])){
	$config = $_POST['data'];
	if(empty($config['cron']))
	{
		$config['cron'] = 5;
	}

	update_option('ci_vital_axis_api_st',$config);

	wp_clear_scheduled_hook('cron_api_vital_axis');
	if($options['active']){
		wp_schedule_event(time()+1,$config['cron'].'min','cron_api_vital_axis');
    }


	Tr_Session_Class::add('Saved Successfully!');
}

$config = get_option('ci_vital_axis_api_st',array());
if(empty($config['cron']))
{
	$config['cron'] = 5;
}

if(empty($config))
{
	$config = get_blog_option(1,'ci_vital_axis_api_st');
	if(!empty($config))
	{
		update_option('ci_vital_axis_api_st',$config);
	}
}

$api = new Ci_Api_VitalAxis($config);
if(isset($_GET['trajax']) && $_GET['trajax']=='send_live'){
    
	$api->sync_reqs(1);

	exit;
}
else if(isset($_GET['trajax']) && $_GET['trajax']=='send_fake'){

		$api = new Ci_Api_VitalAxis($config);
	    $i = 0;
        foreach($config['products'] as $arr) {
	        $i++;


	        list($btype,$panelid) = explode('|',$arr['panelid']);

	        $patient_types = implode( "','", $arr['forms'] );


	        $req = $wpdb->get_row("select 
	                            pa.*,
                                cli.*,
                                doc.*,
                                ins.ins_relation,
                                ins.ins_dob,
                                ins.ins_last,
                                ins.ins_first,
                                ins.ins_company,
                                ins.ins_address,
                                ins.ins_city,
                                ins.ins_state,
                                ins.ins_zip,
                                ins.ins_authorization_id,
                                ins.ins_group_id
                                from ci_patients pa
	                            join ci_insurances ins ON ins.pa_ID = pa.pa_ID
	                            join ci_clinic cli ON cli.cID = 1
	                            join ci_doc doc ON doc.doc_ID = 1
	                            where pa.pa_ID = 1 limit 1", ARRAY_A);

	        if(empty($req))
            {
                continue;
            }
	        $req['assen'] = 'BIO'.time();
	        $req['mid'] = -1 * $i;
	        $req['pa_race'] = '1002-5';
	        $req['pa_ethnicity'] = 'N';
	        $req['type_of_specimen'] = '';

	        $orders = array();
	        $orders[] = $req;


	        $rs = $api->sendtoapi($orders,$arr,$btype);
	        var_dump($rs);
        }


	exit;
}


if(empty($config['products']))
{
	$config['products'] = array(array());
}
$panels = $wpdb->get_results("select * from ci_test_groups where tg_show_req=1 
                order by tg_type asc");

$pa_options = get_option('trpatient_settings',array());
$forms = array(
	'main'=>'Main',
	'conci'=> 'Concierge',
	'canad'=> 'Canada',
	'meije'=> 'Meijer',
);

if(!isset($pa_options['enable_concierge']) || !$pa_options['enable_concierge']){
	unset($forms['conci']);
}
if(!isset($pa_options['enable_meijer']) || !$pa_options['enable_meijer']){
	unset($forms['meije']);
}
if(!isset($pa_options['enable_canada']) || !$pa_options['enable_canada']){
	unset($forms['canad']);
}

if(isset($pa_options['formtypes']) && is_array($pa_options['formtypes'])){
	foreach($pa_options['formtypes'] as $arr)
	{
		$arr['value'] = substr($arr['value'],0,5);
		$forms[$arr['value']] = $arr['title'];
	}
}

$labs = $wpdb->get_results("select lab_ID, lab_name from ci_labs where lab_is_ref = 0 order by lab_ID asc");

?>
<div class="wrap" xmlns="http://www.w3.org/1999/html">
    <h2>Vital Axis Settings</h2>
	<?php Tr_Session_Class::show()?>
    <br>
    <form method="post">
        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <label>Application ID</label>
                    <input type="text" name="data[app_id]" value="<?php echo $config['app_id']?>" required/>
                </div>
                <div class="frow">
                    <label>Destination ID</label>
                    <input type="text" name="data[dest_id]" value="<?php echo $config['dest_id']?>" required/>
                </div>
                <div class="frow">
                    <label>Location GUID</label>
                    <input type="text" name="data[guid]" value="<?php echo $config['guid']?>" required/>
                </div>

            </div>
            <div class="col2">
                <div class="frow">
                    <label>Active <input type="checkbox" name="data[active]" value="1"<?php checked($config['active'])?>/></label>
                    <div style="height:38px">&nbsp;</div>
                </div>
                <div class="frow">
                    <label>User Name</label>
                    <input type="text" name="data[user_name]" value="<?php echo $config['user_name']?>" required/>
                </div>
                <div class="frow">
                    <label>Password</label>
                    <input type="text" name="data[pass]" value="<?php echo $config['pass']?>" required/>
                </div>

            </div>
        </div>

        <div class="products_area">
            <table id="productsadding" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl"">
            <thead>
            <tr>
                <th style="width:30% !important;">Test</th>
                <th style="width:20% !important;">Form</th>
                <th>Slug</th>
	            <?php
                if(count($labs)>1){?>
                    <th>Lab</th>
	            <?php } ?>
                <th style="width:80px !important;">Action</th>
            </tr>
            </thead>
            <tbody>
			<?php
			$i = 0;
			$max_number = 0;
			foreach($config['products'] as $arr)
			{
			    if(!is_array($arr['labs']))
                {
	                $arr['labs'] = array();
                }
				$i++;
				?>
                <tr class="productrow row_<?php echo $i ?>">
                    <td>
                        <select name="data[products][<?php echo $i ?>][panelid]" required>
                            <option value="">Select</option>
							<?php
							foreach($panels as $panel)
							{
							    $pkey = $panel->tg_type.'|'.$panel->tg_ID;
								$prname = strtoupper($panel->tg_type).'- '.$panel->tg_name;
								$selected = $pkey== $arr['panelid']? 'selected':'';
								echo '<option value="'.$pkey.'" '.$selected.'>'.$prname.'</option>';
							}
							?>
                        </select>
                    </td>
                    <td>
                        <select name="data[products][<?php echo $i ?>][forms][]" multiple class="select2" required>
							<?php
							foreach($forms as $fkey => $ftxt)
							{
								$selected = in_array($fkey,$arr['forms'])? 'selected':'';
								echo '<option value="'.$fkey.'" '.$selected.'>'.$ftxt.'</option>';
							}
							?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="data[products][<?php echo $i?>][slug]" value="<?php echo $arr['slug']?>" required/>
                    </td>
	                <?php if(count($labs)>1){?>
                        <td>
                            <select name="data[products][<?php echo $i ?>][labs][]" multiple class="select2" required>
				                <?php
				                foreach($labs as $lab)
				                {
					                $selected = in_array($lab->lab_ID,$arr['labs'])? 'selected':'';
					                echo '<option value="'.$lab->lab_ID.'" '.$selected.'>'.$lab->lab_name.'</option>';
				                }
				                ?>
                            </select>
                        </td>
	                <?php }?>
                    <td>
                        <a class="button removerowproduct">-</a>
                    </td>
                </tr>
				<?php
			}
			if($i> $max_number)
			{
				$max_number = $i;
			}
			?>
            </tbody>
            </table>
            <div class="frow" style="text-align: right">
                <a class="button addnewrowproduct">+</a>
            </div>
        </div>

        <div class="frow">
            <br><br>
            <input type="submit" class="button-primary" value="Save">
			<?php if(isset($_GET['debug'])){?>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'send_live'))?>">send live</a>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'send_fake'))?>">send Fake</a>
			<?php }?>
            
            <?php
            if($config['active']){
	            $today = date_i18n('Y-m-d');
	            $total = 0;
	            echo '<br>';
	            foreach($config['products'] as $arr) {
	                if(!is_array($arr['labs']))
                    {
	                    $arr['labs'] = array();
                    }
		            list( $btype, $panelid ) = explode( '|', $arr['panelid'] );
		            $batch_tbl = tr_pdd_get_table_by_btype($btype);
		            $patient_types = implode("','",$arr['forms']);
		            if(empty($patient_types))
		            {
			            continue;
		            }

		            $sql = "select count(*) as count_reqs
                            from {$batch_tbl} ba
                            join ci_sync_api_orders mo ON mo.mo_mid = ba.mid 
                                            and mo.mo_btype ='{$btype}'
                                            and mo.mo_file_id='{$api->api_id}'
                             where ba.patient_portal_type in ('{$patient_types}')
                             and FIND_IN_SET('GROUP_{$panelid}', ba.panel_tests_requested)
                            ";
		            if(count($arr['labs'])>0)
                    {
	                    $sql.= " and ba.lab_id in (". implode(',',$arr['labs']).") ";
                    }
                    if(isset($_GET['debug'])){
		                var_dump($sql);
                    }

		            $count = $wpdb->get_var( $sql );



		            echo $btype.' '.$arr['slug'].': ' . $count . '<br>';
		            $total+=$count;

	            }
	            echo 'Total Sent: ' . $total . '<br>';
	           
	            echo 'Now: '.date_i18n('Y-m-d H:i:s').'<br>';

	            $next_cron = wp_next_scheduled('cron_api_vital_axis');
	            $current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;


	            $last_cron = @filemtime(WP_CONTENT_DIR.'/log/log_api_vital_axis.txt');
	            if($last_cron >  $next_cron - 60*$config['cron']){
		            $last_cron = $next_cron - 60*$config['cron'];
	            }
	            if($last_cron)
	            {
		            echo 'Last Cron: '.date('Y-m-d H:i:s',$last_cron+$current_offset).'<br>';
	            }

	            if($next_cron)
	            {
		            echo 'Next Cron: '.date('Y-m-d H:i:s',$next_cron+$current_offset).'<br>';
	            }else if($config['active']){
		            if(empty($config['cron']))
		            {
			            $config['cron'] = 5;
		            }
		            wp_clear_scheduled_hook('cron_api_vital_axis');
		            wp_schedule_event(time()+1,$config['cron'].'min','cron_api_vital_axis');
	            }
            }
            ?>

        </div>
    </form>
</div>

<style>
    td select{
        width:100%;
    }
</style>

<script src="<?php echo TRCPDD_URL.'/js/select2.min.js?'.TRCPDD_VS?>"></script>
<link  href="<?php echo TRCPDD_URL.'/css/select2.min.css?'.TRCPDD_VS?>" type='text/css' media='all' rel='stylesheet'/>
<script>
    jQuery(function ($) {
        var $next_id = <?php echo $max_number + 20?>;
        $('.select2').select2();
        $('body').on('click','.addnewrowproduct',function () {
            $next_id++;
            var $area = $(this).parents('.products_area');
            var $defaultrow = '<tr class="productrow">' + $('table tr.row_1',$area).html() + '</tr>';
            $defaultrow = $defaultrow.replace(/\[\d\]/g, '[' + $next_id + ']');
            var $new = $($defaultrow);
            $('input,select,textarea',$new).val('');
            $('select',$new).removeClass('select2-hidden-accessible');
            $('span.select2',$new).remove();
            $('table tbody',$area).append($new);
            $('select.select2',$new).select2();

        }).on('click', '.removerowproduct', function () {
            if (confirm('Do you want delete row?')) {
                var $tr = $(this).parents('tr.productrow');
                $tr.remove();
            }
            return false;
        })
    })
</script>