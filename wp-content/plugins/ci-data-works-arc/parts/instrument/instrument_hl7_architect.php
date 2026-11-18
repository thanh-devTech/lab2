<?php

global $logpath;
include(TRCPDD_PATH . 'inc/functions_instrument.php');

$path = TRCPDD_PATH . 'run';
$path = str_replace('//', '/', $path);
//$logpath = $path.'/architect';

$logpath = WP_CONTENT_DIR . '/log/architect';
$logurl = get_bloginfo('url') . '/wp-content/log/architect';
if (!file_exists($logpath)) {
    wp_mkdir_p($logpath);
}
$offtime = get_option('gmt_offset') * HOUR_IN_SECONDS;



$options = get_option('tr_ins_architect_sts', array());
$need_start = false;
if (empty($options['port'])) {
    $options['port'] = 8689;
}

$outfile = $logpath . '/out_' . $options['port'] . '.txt';
$error_file = $logpath . '/error_' . $options['port'] . '.txt';
$pid_file = $logpath . '/pid_' . $options['port'] . '.txt';
$stop_file = $logpath . '/stop_' . $options['port'] . '.txt';

$log = trpdd_get_logfiles($logpath, $options['port']);

if (isset($_POST['start_stop'])) {
    //stop that port first
    trpdd_stop_port($options['port'], $pid_file, $stop_file);

    $started = trpdd_port_opened($options['port']);

    if (!$started && stripos($_POST['start_stop'], 'start') !== false) {
        unlink($pid_file);
        //start result port
        $command = "nohup zts-php {$path}/architect.php port {$options['port']} {$logpath} > {$outfile} 2> {$error_file} < /dev/null &";
        $rs = exec($command, $output);

        $need_start = true;
        sleep(2);
        $started = trpdd_port_opened($options['port']);
        if ($started) {
            $options['started'] = true;
            update_option('tr_ins_architect_sts', $options);
        }
    }

    //if stop
    if (stripos($_POST['start_stop'], 'stop') !== false) {
        $options['auto_start'] = false;
        $options['started'] = false;
        update_option('tr_ins_architect_sts', $options);
    }
} else if (isset($_POST['options'])) {
    $options = $_POST['options'];
    $old_options = get_option('tr_ins_architect_sts', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_ins_architect_sts', $bkdata);
    update_option('tr_ins_architect_sts', $options);
    Tr_Session_Class::add("Settings Saved");
}

$started = trpdd_port_opened($options['port']);

if ($started) {
    $options['started'] = true;
    update_option('tr_ins_architect_sts', $options);
}

if ($need_start && $started) {
    $options['auto_start'] = true;
    update_option('tr_ins_architect_sts', $options);
    Tr_Session_Class::add("Started at port {$options['port']} ");
}

if (!$options['auto_start']) $options['auto_start'] = true;


?>
<div class="wrap">
    <?php Tr_Session_Class::show() ?>

    <table style="border:none;margin:0 auto;">
        <tr>
            <td style="width:50%">
                <div style="border:1px dashed #ddd;width:400px;margin:30px auto;padding:30px;">

                    <form method="post">
                        <?php if (!$started) { ?>
                            <div class="frow">
                                <label class="inline">Port: </label>
                                <input type="number" min="100" max="9999" name="options[port]" value="<?php echo $options['port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px" />
                            </div>

                            <div class="frow">
                                <label class="inline">Auto Start: </label>
                                <input type="checkbox" name="options[auto_start]" value="1" <?php checked($options['auto_start']) ?> />
                            </div>
                            <div class="frow">
                                <input type="submit" class="button-primary" value="Save" />

                            </div>
                        <?php
                        } else {

                            echo '<div>Result connected: ' . trpdd_count_connected('architect.php', $options['port']) . '</div>';
                            if ($log['lasttime'] > 0) {
                                echo '<div>Last Msg: ' . date('Y-m-d H:i:s', $log['lasttime'] + $offtime) . '</div>';
                                echo '<div>Now: ' . date_i18n('Y-m-d H:i:s') . ' </div>';
                            }
                        }
                        ?>

                        <div style="padding:15px 0">
                            Status: <?php echo $started ? 'Started at port ' . $options['port'] : 'stopped' ?>
                        </div>

                        <input type="submit" class="button" name="start_stop" value="<?php echo $started ? 'Stop' : 'Start' ?>" />
                        <div style="margin-top:25px">
                            Log file:
                            <select id="listlog" class="njs_select2">
                                <?php
                                foreach ($log['files'] as $f) {
                                    echo '<option value="' . $f . '">' . $f . '</option>';
                                }
                                ?>
                            </select><a class="button viewlog">View Log</a>
                            <br>
                            <a class="button" href="<?php echo $logurl . '/out_' . $options['port'] . '.txt' ?>" target="logc">Log connected</a>
                            <a class="button" href="<?php echo $logurl . '/error_' . $options['port'] . '.txt' ?>" target="logc">Log Errors</a>
                        </div>

                    </form>
                </div>
            </td>

        </tr>
    </table>



</div>
<script>
    jQuery(function($) {
        $('[name="start_stop"]').click(function() {
            var act = $(this).val();
            if (confirm("Do you want " + act + '?') == false) {
                return false;
            }
        })
        $('[name="start_stop_req"]').click(function() {
            var act = $(this).val();
            if (confirm("Do you want " + act + '?') == false) {
                return false;
            }
        })
        $('a.viewlog').click(function() {
            var url = '<?php echo $logurl . '/' ?>' + $('#listlog').val();
            window.open(url, 'viewlog');
        });
    })
</script>