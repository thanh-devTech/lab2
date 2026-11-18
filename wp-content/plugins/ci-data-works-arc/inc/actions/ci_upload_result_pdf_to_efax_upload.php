<?php
global $wpdb;



include(TRCPDD_PATH.'api/ci_main_fax.php');

$fax            = new CI_Main_Fax();

$result_dir     = tr_get_result_upload_dir();

$batch_id       = intval($_REQUEST['batch_id']);
$lab_id         = intval(@$_REQUEST['lab_id']);
$type           = $_REQUEST['type']? $_REQUEST['type']:'dna';
$type_result    = 'fax'.$type;

$is_debug       = isset($_REQUEST['rsdb']);
$ids            = isset($_REQUEST['ids'])? $_REQUEST['ids']:'';
$mids           = isset($_REQUEST['mids'])? $_REQUEST['mids']:'';

$table          = tr_pdd_get_table_by_btype($type);
$report_tbl     = tr_pdd_get_table_report_by_btype($type);

$settings       = get_option('ci_pdf_data');
if(empty($settings['to_email']) && function_exists('get_blog_option'))
{
    $settings = get_blog_option(1,'ci_pdf_data');
}

if($is_debug)
{
    add_action('wp_mail_failed','tr_wp_mail_failed');
    function tr_wp_mail_failed($msg)
    {
        echo $msg;exit;
    }
}

$where = " and batch_id = '{$batch_id}' ";
$where2= " and ba.batch_id = '{$batch_id}' and ba.result_pdf != '' and ba.result_pdf is not null ";

if($lab_id>0)
{
    $lab = $wpdb->get_row("select lab_dba_init,lab_dba from ci_labs where lab_ID = {$lab_id} ");
    $where = " and lab_ID = {$lab_id} ";
    $where2 .= " and ba.lab_id = {$lab_id} ";
}

if(!empty($mids))
{
    $mids = explode(',',$mids);
    $mids = array_filter($mids);
    $mids = implode(',',$mids);
    $where_lab2.= " and ba.mid in ({$mids})";
}
else if(!empty($ids))
{
    $ids = explode(',',$ids);
    $ids = array_filter($ids);
    $ids = implode(',',$ids);
    $where2.= " and ba.mid in ({$ids})";
}




//end setup
$pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1 {$where}");

if(isset($_REQUEST['upload'])){
    $wpdb->query("SET SESSION group_concat_max_len = 1000000;");

    $total_up = $wpdb->get_var("select count(*) from ci_box_upload where type='{$type_result}' AND status!=4 {$where}");
    $uploads = $wpdb->get_results("select id,mid,path,etag,sender
                                from ci_box_upload where type='{$type_result}'
                                and status !=1 and status!=4
                                and batch_id = '{$batch_id}'
                                {$where}
                                limit 1");

    $response = array('status'=>'done','total'=>$total_up,'pos_up'=>$pos_up);
    if($total_up==0 || $total_up <= $pos_up)
    {
        wp_send_json($response);
    }


    foreach($uploads as $upload) {
        $rs = false;
        $files = array();
        $file = $result_dir['path'] . $upload->path;
        if (!file_exists($file) ) {
            break;
        }
        $files[basename($file)] = $file;

        //var_dump($files);
        $number = $upload->etag;
        $number = str_replace(array('-','(',')',' '),'',$number);


        $error = '';
        $error_log = '';
        $needbreak = false;

	    if($fax->is_expired())
	    {
		    $fax->refreshToken();
		    if($fax->is_expired())
		    {
			    $needbreak = true;
			    $response['status']='error';
			    break;
		    }
	    }

	    if($is_debug)
	    {
		    $response['use'] = 'debug';
		    $rs = $fax->send_debug($number,$files,$upload->sender);
	    }
	    else{
		    $response['use'] = 'api';
		    $rs = $fax->send($number,$files,$upload->sender);
	    }


	    if(!empty($fax->last_phone))
	    {
		    $number = $fax->last_phone;
	    }

	    $response['to'] = $number;
	    $response['rs'] = $rs;
	    $error = $fax->error_message;

	    if(stripos($error,'Token not found')!==false)
	    {
		    $needbreak = true;
	    }
	    else if($error == 'Request rate exceeded'){
		    sleep(40);
	    }else{
		    @sleep(5);
	    }
	    $error_log = $fax->error_message;

        $response['phone'] = 'number: '.$number . '('.$upload->sender.') ';
        $response['phone'].= ' => ' . (($rs)? 'sent ('.count($files).')':'<red>error: </red>'.$error);


        if($rs)
        {
            $response['status'] = 'ok';
            $wpdb->update('ci_box_upload',array('status'=>1,'uptime'=>time()),array('id'=>$upload->id));


        }else if($response['status']=='done'){
            $response['status'] = 'ok';
            $wpdb->update('ci_box_upload',array('status'=>4,'uptime'=>time(),'log'=>$error_log),array('id'=>$upload->id));
        }else{
            $wpdb->update('ci_box_upload',array('status'=>4,'uptime'=>time(),'log'=>$error_log),array('id'=>$upload->id));
        }

        if($needbreak)
        {
            break;
        }

    }
    $pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1 {$where}");
    $response['pos_up']  = $pos_up;

    wp_send_json($response);
    exit;

}


$can_fax = true;

if($fax->is_expired()){
	$rsrr = $fax->refreshToken();
	$need_break = true;
	if(is_array($rsrr) && !empty($rsrr['access_token']))
	{
		$need_break =  false;
	}else if($rsrr===true)
	{
		$need_break = false;
	}

	if($need_break)
	{
		$can_fax = false;
	}
}


if($can_fax) {
////get files
    if (!isset($_SESSION['last_time_id'])) {
        $_SESSION['last_time_id'] = array('k' => '', 'keys' => array());
    }
    $sql = "select
     ba.*,
     cli.`cli_om-fax` as fax,
     cli.cli_clinic,
     lab.lab_dba_init AS sublab,
     bu.id as box_id,
     bu.status as box_status,
     bu.uptime,
     bu.path
    from {$table} ba 
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
    Left JOIn ci_box_upload bu ON bu.mid = ba.mid and type='{$type_result}'
    where 1 {$where2} group by ba.mid order by ba.doc asc limit 100";

    $is_new = true;


    if ($is_new) {
        //try remove from cache
        $wpdb->query("delete from ci_box_upload where type='{$type_result}' AND status !=4 {$where}");

    }



    $results = $wpdb->get_results($sql, ARRAY_A);


    $sent_before = '';
    foreach ($results as $re) {
        if ($re['box_id'] == 0) {
            $rs = $wpdb->insert('ci_box_upload', array(
                    'path' => $re['result_pdf'],
                    'type' => $type_result,
                    'mid' => $re['mid'],
                    'account_id' => $re['cli_ID'],
                    'etag' => $re['fax'],
                    'lab_ID' => $re['lab_id'],
                    'sender' => $re['cli_clinic'],
                    'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                    'batch_id' => $re['batch_id'],
                )
            );
            if (!$rs) {
                echo $wpdb->last_error;
            }

        } else {
            if (($re['box_status'] == 5) && $re['box_status'] > 0) {
                $wpdb->update('ci_box_upload', array('status' => '2'), array('id' => $re['box_id']));
            } else if ($re['box_status'] == 1) {
                //list sent before
                $sent_before .= '<li>number: ' . $re['etag'] . '(' . $re['sender'] . ') ';
                $sent_before .= ' => sent before</li>';
            }

        }
    }
}
?>

<html>
<?php
do_action( 'admin_print_scripts' );
?>
<script type="text/javascript">
    addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
        pagenow = '<?php echo $current_screen->id; ?>',
        typenow = '<?php echo $current_screen->post_type; ?>',
        adminpage = '<?php echo $admin_body_class; ?>',
        thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
        decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
        isRtl = <?php echo (int) is_rtl(); ?>;
</script>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<body>
<?php
if($can_fax) {
    ?>
    Total: <?php echo count($results) ?>
    <br>
    Current: <span class="current_upload_pdf">0</span>
    <div id="upload_status" title="<?php echo $file_other ?>">Waiting...</div>
    <ol id="sent_list"><?php echo $sent_before ?></ol>

    <?php
}else{
    echo 'RingCentral token is expired. 
    Please <a target=_blank href="admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=ringcentral">ReAuthorize</a>';
    exit;
}

if(empty($settings['to_email']))
{
    echo 'Please setup email gateway!';
    exit;
}
?>
<script>
    jQuery(function($){
        function tr_upload_to_box()
        {
            $('#upload_status').html('Sending...');
            $.ajax({
                url:ajaxurl+'?<?php if($is_debug)echo 'rsdb=1' ?>',
                data:{action:'ajax',tr_action:'<?php echo $_REQUEST['tr_action']?>',type:'<?php echo $type?>',upload:true,batch_id:<?php echo $batch_id?>,lab_id:<?php echo $lab_id?>},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    if(rs.status=='error'){
                        alert(rs.message);
                    }
                    else if(rs.status=='done'){
                        $('#upload_status').html('Done');
                    }else{
                        $('.current_upload_pdf').html(rs.pos_up);
                        $('#sent_list').append('<li>'+rs.phone+'</li>');
                        setTimeout(function(){
                            tr_upload_to_box();
                        },9600);
                    }
                },
                error:function()
                {
                    $('#upload_status').html('Please wait... Retrying upload.');
                    setTimeout(function(){
                        tr_upload_to_box();
                    },19600);
                }
            });
        }
        tr_upload_to_box();
    })
</script>
</body>
</html>