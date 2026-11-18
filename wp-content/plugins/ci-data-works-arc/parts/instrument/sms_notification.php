<?php

if (isset($_POST['data'])) {
    $config = $_POST['data'];

    update_option('ci_sms_notification_st', $config);

    //wp_clear_scheduled_hook('cron_sms_notify');

    Tr_Session_Class::add('Saved Successfully!');
}

$config = get_option('ci_sms_notification_st', array());

if (empty($config)) {
    $config = array(
        'clinic_msg' => 'The Results are available now'
    );
}


if ($act == 'test_sms') {
    include(TRCPDD_PATH . 'api/bandwidth/ci_sms.php');

    if (empty($config['username'])) {
        echo 'Please put username';
    } else {
        $sms = new CI_Sms($config);
        $rs = $sms->send_sms('+13176230565', 'test message');
        var_dump($rs);
    }
    exit;
} else if ($act == 'test_cron') {
    $_GET['testcron'] = 1;
    do_action('cron_sms_notify');
    exit;
} else if ($act == 'copyst') {
    $patient_blog_id = defined('CI_PATIENT_BLOG_ID') && CI_PATIENT_BLOG_ID > 0 ? CI_PATIENT_BLOG_ID : 2;
    $opts = get_blog_option($patient_blog_id, 'otpa_bandwidth_sms_gateway_settings');

    $config['username'] = $opts['user_name'];
    $config['password'] = $opts['user_pass'];
    $config['app_id'] = $opts['application_id'];
    $config['account_id'] = $opts['account_id'];
    $config['send_number'] = $opts['send_number'];
    update_option('ci_sms_notification_st', $config);

    wp_redirect(Tr_Base_Class_V6::link(array('trajax' => ''), false));
    exit;
} else if ($act == 'list_clinic') {

    $list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_sms_number as unsigned) desc");
?>
    <table id="clinictbl" class="posts wp-list-table widefat fixed hover_tbl">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Clinic</th>
                <th style="width:180px">Phone Number</th>
            </tr>
        </thead>
        <?php
        foreach ($list as $row) {
        ?>
            <tr class="datarow" data-id="<?php echo $row->cID ?>">
                <td><?php echo $row->cID ?></td>
                <td><?php echo $row->cli_clinic ?></td>
                <td><input type="text" class="editfield" data-k="cli_sms_number" value="<?php echo $row->cli_sms_number ?>" /></td>
            </tr>
        <?php
        }
        ?>
    </table>
<?php
    exit;
} else if ($act == 'save_clinic') {
    $return = array('success' => 1, 'msg' => '');
    ob_clean();
    $id = $_REQUEST['id'];
    $col = trim($_REQUEST['col']);
    $vl = trim($_REQUEST['val']);

    $rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));

    if ($rs === false) {
        $return['success'] = 0;
        $return['msg'] = 'Error. Please try again';
    }
    wp_send_json($return);
} else if ($act == 'log_sms') {

    ob_start();
    $row_per_page = 50;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();
    $total = $wpdb->get_var("
                    select count(*) 
                    from ci_sent_notify_clinic ");

    $pagination->setTotal($total);

    $rows = $wpdb->get_results("select no.*,
                cli.cli_clinic,
                cli.cli_sms_number
                from ci_sent_notify_clinic no
               join ci_clinic cli ON cli.cID = no.cli_ID
               order by no.created desc
               limit {$offset},{$row_per_page}");
?>
    <div class="tablenav">
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>
    <table id="clinictbl" class="posts wp-list-table widefat fixed hover_tbl">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Clinic</th>
                <th style="width:120px">Phone Number</th>
                <th style="width:80px">Type</th>
                <th>Datetime</th>
            </tr>
        </thead>
        <?php
        foreach ($rows as $row) {
        ?>
            <tr>
                <td><?php echo $row->cli_ID ?></td>
                <td><?php echo $row->cli_clinic ?></td>
                <td><?php echo $row->cli_sms_number ?></td>
                <td><?php echo $row->btype ?></td>
                <td><?php echo $row->created ?></td>
            </tr>
        <?php
        }
        ?>
    </table>
    <div class="tablenav">
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>
<?php
    $content = ob_get_clean();
    if (isset($_GET['trajax'])) {
        $content = str_replace('class="', 'class="show_popup ', $content);
        $content = str_replace('class=\'', 'class=\'show_popup ', $content);
    }
    echo $content;
    exit;
}



if (!isset($config['btypes']) || !is_array($config['btypes'])) {
    $config['btypes'] = array();
}
?>
<div class="wrap">
    <h2>SMS Result Notification</h2>
    <?php Tr_Session_Class::show() ?>
    <br>
    <form method="post">
        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <input type="hidden" name="data[enable]" value="0" />
                    <label><input type="checkbox" name="data[enable]" value="1" <?php checked($config['enable']) ?> /> Enable</label>
                </div>
                <div class="frow">
                    <label>Result Message for Clinic</label>
                    <textarea name="data[clinic_msg]"><?php echo $config['clinic_msg'] ?></textarea>
                    <div>
                        Shortcode: [batch_type] [assens] [assen]
                    </div>
                </div>

                <?php
                foreach (tr_pdd_get_batch_types() as $btype) {
                    if (empty($config['btypes']) && $btype == 'pcr') {
                        $config['btypes'][] = 'pcr';
                    }
                ?>
                    <div class="frow">
                        <label>
                            <input type="checkbox" name="data[btypes][]" value="<?php echo $btype ?>" <?php checked(in_array($btype, $config['btypes'])) ?> />
                            <?php echo tr_pdd_get_typename($btype); ?>
                        </label>
                    </div>
                <?php
                }
                ?>

                <div class="frow">
                    <label>
                        <input type="checkbox" name="data[onlypos]" value="1" <?php checked(isset($config['onlypos'])? $config['onlypos'] : '') ?> />
                        Only Positive
                    </label>
                </div>

                <div class="frow">
                    <?php
                    $countc = $wpdb->get_var("select count(*) from ci_clinic where cli_sms_number!=''");
                    echo '<div>Clinics: ' . $countc . '</div>';

                    $countsms = $wpdb->get_var("select count(*) from ci_sent_notify_clinic");
                    echo '<div>Sms sent: ' . $countsms . '</div>';
                    ?>
                </div>
            </div>
            <div class="col2">
                <div class="frow">
                    <label>BANDWIDTH Settings</label>
                </div>
                <div class="frow">
                    <label>Username</label><br>
                    <input type="password" name="data[username]" value="<?php echo $config['username'] ?>" required />
                </div>
                <div class="frow">
                    <label>Password</label><br>
                    <input type="password" name="data[password]" value="<?php echo $config['password'] ?>" required />
                </div>
                <div class="frow">
                    <label>Application ID</label><br>
                    <input type="password" name="data[app_id]" value="<?php echo $config['app_id'] ?>" required />
                </div>
                <div class="frow">
                    <label>Account ID</label><br>
                    <input type="text" name="data[account_id]" value="<?php echo $config['account_id'] ?>" required style="width:200px" />
                </div>
                <div class="frow">
                    <label>Send Number</label><br>
                    <input type="text" name="data[send_number]" value="<?php echo $config['send_number'] ?>" required style="width:200px"/>
                </div>
            </div>
        </div>

        <div class="frow">
            <br><br>
            <input type="submit" class="button-primary" value="Save">
            <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_clinic', 'trajax' => 1)) ?>">List Clinic</a>
            <a class=" button" href="/wp-content/log/log_sms_clinic.txt" target="_blank">Log File</a>
            <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'log_sms', 'trajax' => 1, 'width' => '1000')) ?>">Log</a>
            <?php if (isset($_GET['debug'])) { ?>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'test_sms', 'trajax' => 1)) ?>">Test</a>
                <a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'test_cron', 'trajax' => 1)) ?>">Test Cron</a>
                <a class=" button" href="<?php Tr_Base_Class_V6::link(array('act' => 'copyst', 'trajax' => 1)) ?>">Copy settings</a>
            <?php } ?>

        </div>
    </form>
</div>



<script>
    jQuery(function($) {
        function save_clinic_data($this) {
            var $pr = $this.parent();
            var $tr = $pr.parent();
            var $vl = $this.val();
            var $col = $this.data('k');
            var $id = $tr.data('id');

            $pr.append('<div class="sloading"></div>');
            $.ajax({
                data: {
                    act: 'save_clinic',
                    trajax: 1,
                    col: $col,
                    id: $id,
                    val: $vl
                },
                type: 'post',
                dataType: 'json',
                success: function(rs) {
                    if (rs.success) {

                    } else {
                        alert(rs.msg);
                    }
                    $('.sloading', $pr).remove();
                }
            });
        }


        $('body').on('click', 'a.seach_assen', function() {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function() {
                tb_show('', $url + '&s=' + $s);
            }, 300);
            return false;
        }).on('click', 'a.show_popup', function() {
            if ($(this).hasClass('seach_assen')) return;
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function() {
                tb_show('', $url);
            }, 500);
            return false;
        }).on('keypress', '#clinictbl input.editfield', function(e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                save_clinic_data($(this));
                return false;
            }
        }).on('change', '#clinictbl input.editfield', function() {
            save_clinic_data($(this));
        })
    })
</script>