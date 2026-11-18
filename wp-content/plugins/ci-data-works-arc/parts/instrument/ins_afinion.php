<?php
global $logpath, $wpdb;
//document: https://www.dropbox.com/s/3pgztr3egkmfr7o/N12027%20Gallery-afinion%20LIS%20Interface%207.0C.pdf?dl=0

include(TRCPDD_PATH . 'inc/functions_instrument.php');

const API_ID = -35;

$admin_view             = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);

$options    = get_option('tr_ins_afinion_sts', array());

$need_start = false;
if (empty($options['port'])) {
    $options['port'] = 8647;
}
$offtime    = get_option('gmt_offset') * HOUR_IN_SECONDS;
$ip         = trpdd_get_ip();
$port       = $options['port'];
$path       = TRCPDD_PATH . 'run';
$path       = str_replace('//', '/', $path);
//$logpath = $path.'/log';
$logpath    = WP_CONTENT_DIR . '/log/afinion';

$logurl     = Tr_Base_Class_V6::link(array('trajax' => 'viewlog'), false);
if (!file_exists($logpath)) {
    wp_mkdir_p($logpath);
}
$outfile    = $logpath . '/out_' . $port . '.txt';
$error_file = $logpath . '/error_' . $port . '.txt';
$pid_file   = $logpath . '/pid_' . $port . '.txt';
$stop_file  = $logpath . '/stop_' . $port . '.txt';


$log        = trpdd_get_logfiles($logpath, $port);


if (isset($_GET['trajax']) && $_GET['trajax'] == 'viewlog') {
    if (empty($_GET['file'])) {
        $_GET['file'] = '';
        foreach ($log['files'] as $file) {
            $_GET['file'] = $file;
            break;
        }
    }
    if (isset($_GET['raw'])) {
        $_GET['file'] = str_replace('log_', 'log_raw', $_GET['file']);
    }
    $logfile = $logpath . '/' . $_GET['file'];
    if (isset($_REQUEST['delete'])) {
        unlink($logfile);
        echo 'Deleted';
        exit;
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
        jQuery(function($) {
            $("html, body").animate({
                scrollTop: $(document).height()
            }, 300);
        })
    </script>

    </body>

    </html>
<?php
    exit;
} else if ($act == 'load_log_test_server') {
    $return = array('content' => '', 'time' => 0);
    $filepath = $logpath . '/log_' . $port . '_test.txt';
    if (isset($_REQUEST['clear']) && $_REQUEST['clear']) {
        file_put_contents($filepath, '');
    }
    $return['content'] = file_get_contents($filepath);
    $return['time'] = filesize($filepath);
    wp_send_json($return);
    exit;
} else if ($act == 'test') {

?>
    <div class="frow">
        <a class="button ws_send" data-msg="MSH|^~\&|Afinion 2 Analyzer||EPR|KH-1|20100610131643||ORU^R01|1048|P|2.4|||AL|NE||8859/1 PID|1|||
PV1|1||||||||||||||||||43|
OBR|1||3|CRP|||||||N||||ORH||||||||^10124809||F| OBX|1|ST|CRP||16|mg/L|||||F|||||||AF0000030|20100608142352|">Result</a>
        <a class="button ws_send" data-msg="<ACK>">ACK</a>
    </div>
    <div class="frow">
        <textarea id="text_send" style="width:70%;height:70px"></textarea>
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
        testclient.setType('MSH');
    </script>
<?php
    exit;
} else if ($act == 'install') {

    include('install_instrument.php');

    exit;
} else if ($act == 'list') {

    $btype      = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
    $joins      = '';
    $fields     = '';
    $where      = " ";
    if (!empty($_REQUEST['s'])) {
        $where .= " and ba.assen = '{$_REQUEST['s']}' ";
    }
    $table_batch = tr_pdd_get_table_by_btype($btype);
    $report_table = tr_pdd_get_table_report_by_btype($btype);

    $row_per_page = 200;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(distinct(ba.mid)) as c 
	                                from {$table_batch} ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                        and o.mo_file_id in(" . API_ID . ") and o.mo_btype='{$btype}'
	                                where 1 {$where}"));
    $offset = $pagination->offset();

    $rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.doc,
                                ba.oldreq,
                                re.id,
                                re.estatus,
                                re.instrument,
                                re.created_date,
                                pa.pa_first,
                                pa.pa_last,
                                o.mo_control_ID as sent_order,
                                o.mo_set_ID as received_rs
                            from {$table_batch} ba
                            left join {$report_table} re ON re.patient_id = ba.assen
                            left join ci_patients pa ON pa.pa_ID = ba.patient_id
                            join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                        and o.mo_file_id in(" . API_ID . ") and o.mo_btype='{$btype}'
                            where 1 {$where}
                            group by ba.mid
                            order by o.mo_control_ID desc
                            limit {$offset},{$row_per_page}");
    echo ($wpdb->last_error);
?>
    <div>
        <input type="search" id="assen_search">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:20px"></th>
                <th>Accession</th>
                <th>Patient</th>
                <th style="width:90px">Status</th>
                <th style="width:140px;">Requested Order</th>
                <th>Received Result</th>
            </tr>
        </thead>
        <tbody>
            <?php

            foreach ($rows as $i => $row) {
            ?>
                <tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
                    <td><?php echo $i + 1 ?></td>
                    <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
                    <td><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
                    <td><a href="?page=<?php echo $btype ?>_results&act=edit&id=<?php echo $row->id; ?>" target="_blank">
                            <?php echo !empty($row->estatus) ? $row->estatus : '.' ?>
                        </a>
                    </td>
                    <td><?php echo $row->sent_order; ?></td>
                    <td><?php echo $row->received_rs ?></td>

                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>

    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <?php
    $content = ob_get_clean();
    if (isset($_GET['trajax'])) {
        $content = str_replace('class="', 'class="show_popup ', $content);
        $content = str_replace('class=\'', 'class=\'show_popup ', $content);
    }
    echo $content;
    exit;
} else if ($act == 'toxcode') {
    $rows = $wpdb->get_results("select * from ci_urine_validity 
                            where u_abreviation_afinion is not null  
                            and u_abreviation_afinion!=''
                             and u_status = 1
                             order by u_showinreq asc, CAST(u_abreviation_afinion as unsigned) asc");
    ?>

    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <td>#</td>
                <td>Test Name</td>
                <td>Group</td>
                <td>Code</td>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($rows as $row) {
            ?>
                <tr>
                    <td><?php echo $row->u_ID ?></td>
                    <td><?php echo $row->u_name ?></td>
                    <td><?php echo $row->u_showinreq ? 'Screening' : 'Urine Validity' ?></td>
                    <td><?php echo $row->u_abreviation_afinion ?></td>
                </tr>
            <?php
            }
            if (count($rows) == 0) {
                echo 'not found';
            }
            ?>
        </tbody>
    </table>


<?php
    exit;
}




if (isset($_POST['start_stop'])  || isset($_POST['restart'])) {
    trpdd_update_config_file('afinion', $options);
    //stop that port first
    trpdd_stop_port($port, $pid_file, $stop_file);
    sleep(2);
    $started = trpdd_port_opened($port);

    if (!$started && stripos($_POST['start_stop'], 'start') !== false  || isset($_POST['restart'])) {
        unlink($pid_file);
        $command = "nohup zts-php {$path}/afinion.php port {$port} {$logpath} > {$outfile} 2> {$error_file} < /dev/null &";


        $rs = exec($command, $output);

        if (isset($_GET['debug2'])) {
            var_dump($command);
            echo '<br><br>';
            var_dump($output);
            var_dump($rs);
        }

        $need_start = true;
        sleep(2);
        $started = trpdd_port_opened($port);
        if ($started) {
            $options['started'] = true;
            update_option('tr_ins_afinion_sts', $options);
        }
    }

    //if stop
    if (stripos($_POST['start_stop'], 'stop') !== false) {
        $options['auto_start'] = false;
        $options['started'] = false;
        update_option('tr_ins_afinion_sts', $options);
    }
} else if (isset($_POST['options'])) {
    $options = $_POST['options'];
    update_option('tr_ins_afinion_sts', $options);
    Tr_Session_Class::add("Settings Saved");
}

$started = trpdd_port_opened($port);

if ($started) {
    $options['started'] = true;
    $old_options = get_option('tr_ins_afinion_sts', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_ins_afinion_sts', $bkdata);
    update_option('tr_ins_afinion_sts', $options);
}

if ($need_start && $started) {
    $options['auto_start'] = true;
    update_option('tr_ins_afinion_sts', $options);
    Tr_Session_Class::add("Started at port {$port} ");
}

if (!$options['auto_start']) $options['auto_start'] = true;



wp_enqueue_script('instrument', TRCPDD_URL . 'js/instrument.js', array(), time());
wp_add_inline_script('instrument', 'var $ws_host = "' . $_SERVER['SERVER_ADDR'] . '",$ws_port=' . $port . ';', 'before');

$df_configs = array();
?>
<div class="wrap">
    <?php Tr_Session_Class::show() ?>

    <div style="border:1px dashed #ddd;width:600px;margin:30px auto;padding:30px;">
        <form method="post">
            <?php if (!$started) { ?>
                <div class="frow">
                    <div class="col2">
                        <div class="row">
                            <label>Port: </label>
                            <input type="number" min="100" max="9999" name="options[port]" value="<?php echo $options['port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px" />
                        </div>
                        <?php
                        $showd_col2 = false;
                        $countrowonecol = floor(count($df_configs) / 2);
                        $numrownow = 0;
                        foreach ($df_configs as $cfk => $cfv) {
                            $secondvl = '';
                            if (is_array($cfv)) {
                                $secondvl = $cfv[1];
                                $cfv = $cfv[0];
                            }
                            if (!isset($options[$cfk])) {
                                $options[$cfk] = $cfv;
                            }
                            $numrownow++;
                            if (!$showd_col2 && $numrownow >= $countrowonecol) {
                                $showd_col2 = true;
                                echo '</div><div class="col2">';
                            }
                        ?>
                            <div class="row">
                                <label><?php echo $cfk ?>: </label>
                                <?php if (is_bool($cfv)) {
                                ?>
                                    <input type="checkbox" name="options[<?php echo $cfk ?>]" <?php checked($options[$cfk]) ?> value="1" />
                                    <small>default: <?php echo $cfv ? 1 : 0 ?></small>
                                <?php
                                } else { ?>
                                    <input type="number" name="options[<?php echo $cfk ?>]" value="<?php echo $options[$cfk] ?>" style="width:90px" />
                                    <small>default: <?php echo $cfv . (!empty($secondvl) ? '. or ' . $secondvl : '') ?></small>
                                <?php } ?>
                            </div>
                        <?php
                        }
                        ?>

                        <div class="row">
                            <label>Auto Start: </label>
                            <input type="checkbox" name="options[auto_start]" value="1" <?php checked($options['auto_start']) ?> />
                        </div>
                    </div>
                </div>
                <div class="frow">
                    <input type="submit" class="button-primary" value="Save" />
                </div>
            <?php
            } else {

                echo '<div>connected: ' . trpdd_count_connected('afinion.php', $port) . '</div>';
                if ($log['lasttime'] > 0) {
                    echo '<div>Last Msg: ' . date('Y-m-d H:i:s', $log['lasttime'] + $offtime) . '</div>';
                    echo '<div><label style="padding-right:29px">Now</label>: ' . date_i18n('Y-m-d H:i:s') . ' </div>';
                }
            }
            ?>



            <div style="padding:15px 0">
                Server IP: <?php echo $_SERVER['SERVER_ADDR'] ?><br>
                Status: <?php echo $started ? 'Started at port ' . $options['port'] : 'stopped' ?>
            </div>


            <input type="submit" class="button" name="start_stop" value="<?php echo $started ? 'Stop' : 'Start' ?>" />

            <?php if ($started) { ?>
                <input type="submit" class="button" name="restart" value="Restart" />
                <?php
                if (isset($_GET['debug']) || $admin_view) {
                ?>
                    <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test'));
                                                        if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200&height=600">Test</a> &nbsp;&nbsp;
                <?php
                }
            }
            if (isset($_GET['debug']) || $admin_view) {
                ?>
                <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install'));
                                                    if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Install</a> &nbsp;&nbsp;
            <?php
            } ?>


            <div style="margin-top:25px">
                Log file:
                <select id="listlog">
                    <?php
                    foreach ($log['files'] as $f) {
                        $filesize = filesize($logpath . '/' . $f);

                        if ($filesize == 0 || isset($_REQUEST['clear_log'])) {
                            unlink($logpath . '/' . $f);
                            continue;
                        } else if (($filesize < 30 || stripos($f, 'test') > 0) && !isset($_GET['debug'])) {
                            continue;
                        }
                        echo '<option value="' . $f . '">' . $f . '</option>';
                    }
                    ?>
                </select>

                <a class="button viewlog">View Log</a>
                <a class="button viewlograw">View raw</a>
                <br>
                <br>

                <a class="button" href="<?php echo $logurl . '&file=out_' . $port . '.txt' ?>" target="logc">Log connected</a>
                <a class="button" href="<?php echo $logurl . '&file=error_' . $port . '.txt' ?>" target="logc">Log Errors</a>
                <br>
                <br>

                <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'list', 'btype' => 'tox'));
                                                    if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000" title="Tox List">Tox Log</a> &nbsp;&nbsp;
                <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'toxcode'));
                                                    if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000" title="Tox Codes">Tox Codes</a> &nbsp;&nbsp;


            </div>

        </form>
    </div>
</div>
<script>
    jQuery(function($) {

        $('body').on('click', '[name="start_stop"]', function() {
                var act = $(this).val();
                if (act == 'Stop' && confirm("Do you want " + act + '?') == false) {
                    return false;
                }
            })
            .on('click', 'a.show_popup', function() {
                if ($(this).hasClass('seach_assen')) return;
                var $url = $(this).attr('href');
                //tb_remove();
                setTimeout(function() {
                    tb_show('', $url);
                }, 300);
                return false;
            })
            .on('click', 'a.seach_assen', function() {
                var $s = $('#assen_search').val();
                var $url = $(this).attr('href');
                setTimeout(function() {
                    tb_show('', $url + '&s=' + $s);
                }, 300);
                return false;
            })
        $('a.viewlog').click(function() {
            var url = '<?php echo $logurl . '&file=' ?>' + $('#listlog').val();
            window.open(url, 'viewlog');
        });
        $('a.viewlograw').click(function() {
            var url = '<?php echo $logurl . '&raw=1&file=' ?>' + $('#listlog').val();
            window.open(url, 'viewlograw');
        });
    })
</script>