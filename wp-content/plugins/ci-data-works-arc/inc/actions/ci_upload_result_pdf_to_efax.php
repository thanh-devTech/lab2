<?php
global $wpdb;

include_once(TRCPDD_PATH . 'api/ci_main_fax.php');

$fax            = new CI_Main_Fax();

$batch_id       = ($_REQUEST['batch_id']);
$lab_id         = intval(@$_REQUEST['lab_id']);
$result_dir     = WP_CONTENT_DIR . '/results/';
$type_result    = 'rs_efax';
$is_debug       = isset($_REQUEST['rsdb']);
$ids            = isset($_REQUEST['ids']) ? $_REQUEST['ids'] : '';
$mids           = isset($_REQUEST['mids']) ? $_REQUEST['mids'] : '';
$fax_ids        = isset($_REQUEST['fax_ids']) ? $_REQUEST['fax_ids'] : '';
$btype          = trpdd_get_current_btype();
$batch_tbl      = tr_pdd_get_table_by_btype($btype);
$report_tbl     = tr_pdd_get_table_report_by_btype($btype);

if ($_REQUEST['batch_id'] === 'id') {
    $batch_id = 'id';
}
if (!empty($btype) && $btype != 'tox') {
    $type_result .= $btype;
}

$limit_date = date('Y-m-d', strtotime('-10days'));
$settings = get_option('ci_pdf_data');
if (empty($settings['to_email']) && function_exists('get_blog_option')) {
    $settings = get_blog_option(1, 'ci_pdf_data');
}

if ($is_debug) {
    add_action('wp_mail_failed', 'tr_wp_mail_failed');
    function tr_wp_mail_failed($msg)
    {
        var_dump($msg);
        exit;
    }
}

$fax_ids_str = is_array($fax_ids) ? implode(',', $fax_ids) : '';
if (empty($fax_ids_str)) {
    $fax_ids_str = '-1';
}
$where_lab = " ";
$where_fax = " and id in ({$fax_ids_str})  ";
//$where_fax.= " and created > '{$limit_date}' ";
$where_first = '';
if ($lab_id > 0) {
    $lab = $wpdb->get_row("select lab_dba_init,lab_dba from ci_labs where lab_ID = {$lab_id} ");
    $where_lab = " and (lab_ID={$lab_id})  ";
    $where_fax .= " and (lab_ID={$lab_id})  ";
    $where_first .= " and ba.lab_id = {$lab_id}  ";
}

if (!empty($mids)) {
    $mids = explode(',', $mids);
    $mids = array_filter($mids);
    $mids = implode(',', $mids);
    $where_first .= " and ba.mid in ({$mids})";
} else if (!empty($ids)) {
    $ids = explode(',', $ids);
    $ids = array_filter($ids);
    $ids = implode(',', $ids);
    $where_first .= " and re.id in ({$ids})";
} else {
    $where_first .= " and ba.batch_id = '{$batch_id}' ";
}

//end setup
$pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1  {$where_fax}");

if (isset($_REQUEST['upload'])) {

    $groupby = 'group by id';
    if ($fax->api_slug == 'CI_Fax_RingCentral') {
        $groupby = 'group by etag,btype';
    }
    $wpdb->query("SET SESSION group_concat_max_len = 1000000;");

    $total_up = $wpdb->get_var("select count(*) from ci_box_upload where type='{$type_result}' AND status!=4 {$where_fax}");
    $uploads = $wpdb->get_results("select 
                                group_concat(id) as ids,
                                group_concat(mid) as mids,
                                group_concat(path) as paths,
                                etag,sender
                                from ci_box_upload where type='{$type_result}'
                                and status !=1 and status!=4
                                {$where_fax}
                                {$groupby}
                                limit 1");

    $response = array('status' => 'done', 'total' => $total_up, 'pos_up' => $pos_up);
    if ($total_up == 0 || $total_up <= $pos_up) {
        wp_send_json($response);
    }
    $time_edit_html = filectime(TRCPDD_PATH . 'reports/results_tox.php');


    foreach ($uploads as $upload) {
        $rs = false;
        $files = array();
        $ids = explode(',', $upload->ids);
        $mids = explode(',', $upload->mids);
        $paths = explode(',', $upload->paths);

        foreach ($paths as $i => $path) {
            $mid = $mids[$i];
            $file = $result_dir . $path;
            $filetime = filectime($file);
            if (stripos($file, 'uploads/') === false) {
                $file = trpdd_export_results_pdf($mid, 'mid', false, $btype);
                if (! $file) {

                    $response['emptyfile'][] = $file;
                    continue;
                }
            }

            if (file_exists($file)) {
                $files[basename($file)] = $file;
            } else {

                $response['filenotexists'][] = $file;
            }
        }


        if (count($files) == 0) {
            continue;
        }

        //var_dump($files);
        $number = trim($upload->etag);
        $number = str_replace(array('-', '(', ')', ' '), '', $number);


        $error = '';
        $error_log = '';
        $needbreak = false;

        if ($fax->is_expired()) {
            $fax->refreshToken();
            if ($fax->is_expired()) {
                $needbreak = true;
                $response['status'] = 'error';
                $response['reason'] = 'expried';
                break;
            }
        }

        if ($is_debug) {
            $response['use'] = 'debug';
            $rs = $fax->send_debug($number, $files, $upload->sender);
        } else {
            $response['use'] = 'api';
            $rs = $fax->send($number, $files, $upload->sender);
        }


        if (!empty($fax->last_phone)) {
            $number = $fax->last_phone;
        }

        $response['to'] = $number;
        $response['rs'] = $rs;
        $error = $fax->error_message;

        if (stripos($error, 'Token not found') !== false) {
            $needbreak = true;
        } else if ($error == 'Request rate exceeded') {
            sleep(40);
        } else {
            @sleep(5);
        }
        $error_log = $fax->error_message;

        $response['phone'] = 'number: ' . $number . '(' . $upload->sender . ') ';
        $response['phone'] .= ' => ' . (($rs) ? 'sent (' . count($files) . ')' : '<red>error: </red>' . $error);


        if ($rs) {
            $response['status'] = 'ok';
            $wpdb->query("update ci_box_upload set status = 1,uptime='" . time() . "' where id in ({$upload->ids})");
            $wpdb->query("update {$report_tbl} set faxed = 1 
                                where patient_id in (select assen from {$batch_tbl} where mid in ({$upload->mids}))");

            //add log
            foreach ($mids as $mid) {
                trpdd_log_user(get_current_user_id(), 'fax', $upload->etag, $mid, $btype, '', '', '');
            }
        } else if ($response['status'] == 'done') {
            $response['status'] = 'ok';
            $wpdb->query("update ci_box_upload set status = 4,log='" . $error_log . "' where id in ({$upload->ids})");
        } else {
            $wpdb->query("update ci_box_upload set status = 4,log='" . $error_log . "' where id in ({$upload->ids})");
        }

        if ($needbreak) {
            break;
        }
    }
    $pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload 
                                where type='{$type_result}' 
                                and status = 1 
                                {$where_fax}");
    $response['pos_up']  = $pos_up;
    $response['end'] = 1;

    wp_send_json($response);
    exit;
}




////get files
if (!isset($_SESSION['last_time_id'])) {
    $_SESSION['last_time_id'] = array('k' => '', 'keys' => array());
}
$sql = "select
     re.id,
     re.upload_files,
     ba.mid,
     ba.cli_ID,
     ba.result_pdf,
     ba.lab_id,
     cli.`cli_om-fax` as fax,
     cli.cli_clinic,
     re.modified,
     bu.id as box_id,
     bu.status as box_status,
     bu.created_by,
     bu.uptime,
     bu.path,
     bu.sender,
     bu.etag
    from {$batch_tbl} ba
    left JOIN {$report_tbl} re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
    Left JOIn ci_box_upload bu ON bu.mid = ba.mid and bu.type='{$type_result}' and bu.status !=4
    where ba.batch_id = '{$batch_id}' {$where_first} 
    and (re.estatus='C' or (ba.result_pdf!='' and ba.result_pdf is not null ))
    group by ba.mid 
    order by ba.doc asc";


$is_new = false;

if ($ids) {
    $is_new = true;
} else {
    $pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload 
                    where type='{$type_result}' 
                    and status =0 
                    and batch_id = '{$batch_id}' 
                    {$where_lab}");
    if ($pos_up == 0) {
        $is_new = true;
    }
}
if ($is_new) {
    //try remove from cache
    $wpdb->query("delete from ci_box_upload 
                    where type='{$type_result}' 
                    and batch_id = '{$batch_id}' 
                    AND status !=4 
                    {$where_lab}");
}

$can_fax = true;


if ($fax->is_expired()) {
    $rsrr = $fax->refreshToken();
    $need_break = true;
    if (is_array($rsrr) && !empty($rsrr['access_token'])) {
        $need_break =  false;
    } else if ($rsrr === true) {
        $need_break = false;
    }

    if ($need_break) {
        $can_fax = false;
    }
}

$fax_ids = array();
if ($can_fax) {


    $results = $wpdb->get_results($sql, ARRAY_A);
    $sent_before = '';
    foreach ($results as $re) {
        if ($re['box_id'] == 0) {

            if (!empty($re['result_pdf']) && empty($re['upload_files'])) {
                $file = $re['result_pdf'];
                $path = 'uploads';
                $path_db = $path . '/' . $file;
            } else {
                $report = tr_pdd_get_report_by_id(0, $btype, $re['mid']);
                if (!$report) continue;

                list($path, $file) = trpdd_get_path_result_pdf($report, $btype);
                $path_db = $path . '/' . $file;

                if (!file_exists($result_dir . $path)) {
                    wp_mkdir_p($result_dir . $path);
                }
            }

            $wpdb->insert(
                'ci_box_upload',
                array(
                    'path' => $path_db,
                    'type' => $type_result,
                    'mid' => $re['mid'],
                    'account_id' => $re['cli_ID'],
                    'etag' => trim($re['fax']),
                    'lab_ID' => $re['lab_id'],
                    'batch_id' => $batch_id,
                    'btype' => $btype,
                    'sender' => $re['cli_clinic'],
                    'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                    'created_by' => get_current_user_id(),
                )
            );
            $re['box_id'] = $wpdb->insert_id;

            if (stripos($file, 'other') === 0) {
                $file_other = $file . $re['id'];
            }
        } else {

            $file = $result_dir . $re['path'];

            if ((filectime($file) >= $re['uptime'] || $re['created_by'] == -1) && $re['box_status'] > 0) {
                $wpdb->update('ci_box_upload', array('status' => '0'), array('id' => $re['box_id']));
            } else if ($re['box_status'] == 1) {
                //list sent before
                $wpdb->update('ci_box_upload', array('status' => '0'), array('id' => $re['box_id']));
                //$sent_before .= '<li>number: ' . $re['etag'] . '(' . $re['sender'] . ') ';
                //$sent_before .= ' => sent before</li>';
            }
        }
        $fax_ids[] = $re['box_id'];
    }
}
?>

<html>
<?php
do_action('admin_print_scripts');
?>
<script type="text/javascript">
    addLoadEvent = function(func) {
        if (typeof jQuery != "undefined") jQuery(document).ready(func);
        else if (typeof wpOnload != 'function') {
            wpOnload = func;
        } else {
            var oldonload = wpOnload;
            wpOnload = function() {
                oldonload();
                func();
            }
        }
    };
    var ajaxurl = '<?php echo admin_url('admin-ajax.php', 'relative'); ?>',
        pagenow = '<?php echo $current_screen->id; ?>',
        typenow = '<?php echo $current_screen->post_type; ?>',
        adminpage = '<?php echo $admin_body_class; ?>',
        thousandsSeparator = '<?php echo addslashes($wp_locale->number_format['thousands_sep']); ?>',
        decimalPoint = '<?php echo addslashes($wp_locale->number_format['decimal_point']); ?>',
        isRtl = <?php echo (int) is_rtl(); ?>;
</script>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<body>
    <?php if ($can_fax) { ?>
        Total: <?php echo count($results) ?>
        <br>
        Current: <span class="current_upload_pdf">0</span>
        <div id="upload_status" title="<?php echo $file_other ?>">Waiting...</div>
        <ol id="sent_list"><?php echo $sent_before ?></ol>
        <div style="display: none;"><?php echo $sql ?></div>
    <?php
    } else {
        echo 'RingCentral token is expired. 
    Please <a target=_blank href="admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=ringcentral">ReAuthorize</a>';
        exit;
    }

    ?>
    <script>
        jQuery(function($) {
            var $fax_ids = <?php echo json_encode($fax_ids) ?>;

            function tr_upload_to_box() {
                $('#upload_status').html('Sending...');
                $.ajax({
                    url: ajaxurl + '?<?php if ($is_debug) echo 'rsdb=1' ?>',
                    data: {
                        action: 'ajax',
                        tr_action: '<?php echo $_REQUEST['tr_action'] ?>',
                        upload: true,
                        batch_id: '<?php echo $batch_id ?>',
                        lab_id: '<?php echo $lab_id ?>',
                        type: '<?php echo $btype ?>',
                        fax_ids: $fax_ids
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        if (rs.status == 'error') {
                            alert(rs.message);
                        } else if (rs.status == 'done') {
                            $('#upload_status').html('Done');
                        } else {
                            $('.current_upload_pdf').html(rs.pos_up);
                            $('#sent_list').append('<li>' + rs.phone + '</li>');
                            setTimeout(function() {
                                tr_upload_to_box();
                            }, 9600);
                        }
                    },
                    error: function() {
                        $('#upload_status').html('Please wait... Retrying upload.');
                        setTimeout(function() {
                            tr_upload_to_box();
                        }, 19600);
                    }
                });
            }
            tr_upload_to_box();
        })
    </script>
</body>

</html>