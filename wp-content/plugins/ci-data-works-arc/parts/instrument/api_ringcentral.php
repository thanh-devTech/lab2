<?php
global $wpdb;

include(TRCPDD_PATH . 'api/ci_fax_ringcentral.php');


$act        = isset($_GET['trajax']) ? $_GET['trajax'] : $act;
$timezone_offset     = get_option('gmt_offset') * HOUR_IN_SECONDS;

$ring       = CI_Fax_RingCentral::getObject();
$options    = $ring->options;

$otheroptions = get_option('ci_pdf_data', array());
$result_dir = WP_CONTENT_DIR . '/results/';

if (isset($_GET['code']) && !empty($_GET['code'])) {
    try {
        $rs = $ring->authorize_code($_GET['code']);
        if ($rs === true) {
            Tr_Session_Class::add('Authentication Successfully');
            Tr_Session_Class::add('You need set Auto Fax when Approved to Yes and save settings to enable auto fax feature');
        } else {
            Tr_Session_Class::add_error('Authentication Error: ' . (string)$rs);
        }
    } catch (Exception $e) {
        Tr_Session_Class::add_error('Error: ' . $rs['error_description']);
    }
}

if (isset($_POST['options'])) {

    $new = $_POST['options'];

    if ($options['client_id'] != $new['client_id']) {
        $new['access_token'] = '';
        $new['refresh_token'] = '';
    }
    $ring->update_options($new);

    $ring = CI_Fax_RingCentral::getObject();
    $options = $ring->options;

    Tr_Session_Class::add('Saved Settings Successfully');

    ///update otheroptions
    if (isset($_POST['otheroptions']) && is_array($_POST['otheroptions'])) {
        $otheroptions = get_option('ci_pdf_data', array());
        $otheroptions = array_merge($otheroptions, $_POST['otheroptions']);
        update_option('ci_pdf_data', $otheroptions);
    }
}



$authorized = false;
if (isset($options['access_token']) && $options['access_token']) {
    if ($options['time'] < time() - 86400) {
        try {
            $rs = $ring->refreshToken();

            if ($rs['access_token']) {
                $authorized = true;
            } else {

                if ($rs === null) {
                    Tr_Session_Class::add_error('Error: Curl has issue, try restart php');
                } else {
                    Tr_Session_Class::add_error('Error: ' . $rs['error_description']);
                }
                //$options['access_token'] = '';
                //$options['refresh_token'] = '';
                //$ring->update_options($options);
            }
        } catch (Exception $e) {
            Tr_Session_Class::add_error('Error: ' . $e->getMessage());
        }
    } else {
        $authorized = true;
    }
}



if ($act == 'log') {
    $where = " type like '%fax%'  ";

    if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
        $search     = $_REQUEST['s'];
        $mid        = 0;
        $btype      = '';
        foreach (tr_pdd_get_batch_types() as $btype) {
            $batch_tbl = tr_pdd_get_table_by_btype($btype);
            $mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$search}' ");
            if ($mid > 0) {
                break;
            }
        }
        $where .= " and mid = {$mid} and btype = '{$btype}' ";
    }
    $row_per_page = 200;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) from ci_box_upload where {$where}"));
    $offset = $pagination->offset();
    $sql = "select * from ci_box_upload 
	                where {$where} 
	                order by id desc
                    limit {$offset},{$row_per_page}";
    $rows = $wpdb->get_results($sql);
    ob_start();
?>
    <div>
        <input type="search" id="assen_search">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th style="width:120px">Accession</th>
                <th style="width:50px">Type</th>
                <th>Patient</th>
                <th>Clinic</th>
                <th>Doctor</th>
                <th>To</th>
                <th>PDF</th>
                <th style="width:60px">Status</th>
                <th>DOC</th>
                <th>Report Date</th>
                <th>Date/time</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($rows as $row) {
                if (empty($row->btype)) {
                    $row->btype = 'tox';
                }
                $batch_tbl = tr_pdd_get_table_by_btype($row->btype);
                $report_tbl = tr_pdd_get_table_report_by_btype($row->btype);
                $batch = $wpdb->get_row("select assen, 
		            ba.patient_name,
		            ba.requesting_practice,
		            ba.requesting_physician,
		            ba.doc,
                    re.id,
                    re.approve_date
		        from {$batch_tbl} ba 
                left join {$report_tbl} re ON re.patient_id = ba.assen
                where mid = {$row->mid}
                 ");

                if (!$batch) {
                    if (!empty($wpdb->last_error)) {
                        echo $wpdb->last_error;
                    } else {
                        $wpdb->delete('ci_box_upload', array('id' => $row->id));
                    }
                    continue;
                }
                $pdf_datetime = '';
                $filepath = $result_dir . $row->path;
                $fileurl = '/wp-content/results/' . $row->path;
                if (!file_exists($filepath)) {
                    $fileurl = '#';
                } else {
                    $pdf_datetime = date('Y-m-d H:i:s', filemtime($filepath) + $timezone_offset);
                }

                $rowclass = 'rowt ';
                if (in_array($row->status, array('4', '5'))) {
                    $rowclass .= ' sending ';
                }
            ?>
                <tr class="<?php echo $rowclass ?>">
                    <td><?php echo $row->mid ?></td>
                    <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->btype . '_' . $row->mid ?>&short=1"><?php echo $batch->assen ?></a></td>
                    <td><a target="_blank" href="?page=<?php echo $row->btype ?>_results&act=edit&id=<?php echo $batch->id ?>"><?php echo $row->btype ?></a></td>
                    <td><?php echo $batch->patient_name ?></td>
                    <td><?php echo $batch->requesting_practice ?></td>
                    <td><?php echo $batch->requesting_physician ?></td>
                    <td><?php echo $row->etag ?></td>
                    <td><a target="_blank" title="<?php echo $pdf_datetime ?>" href="<?php echo $fileurl ?>">pdf</a></td>
                    <td class="sendstatus" title="<?php echo $row->status ?>"><?php echo $row->status == 1 ? 'sent' : '' ?></td>
                    <td><?php echo $batch->doc ?></td>
                    <td><?php echo $batch->approve_date ?></td>
                    <td title="<?php echo $row->created_by ?>"><?php echo $row->created ?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>

    <?php
    $content = ob_get_clean();
    if (isset($_GET['trajax'])) {
        $content = str_replace('class="', 'class="show_popup ', $content);
        $content = str_replace('class=\'', 'class=\'show_popup ', $content);
    }
    echo $content;
    exit;
} else if ($act == 'refresh_token') {
    try {
        $rs = $ring->refreshToken(60);

        if ($rs == -1) {
            echo 'Delay. Please try after 60s';
        } else if ($rs['access_token']) {
            $authorized = true;

            Tr_Session_Class::add("It works");
        } else {

            Tr_Session_Class::add_error('Error: ' . $rs['error_description']);
            //$options['access_token'] = '';
            //$options['refresh_token'] = '';
            //$ring->update_options($options);
        }
    } catch (Exception $e) {
        Tr_Session_Class::add_error('Error: ' . $e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} else if ($act == 'test') {
    if (!empty($_POST['faxto'])) {
        $number = $_POST['faxto'];
        include_once(TRCPDD_PATH . 'api/ci_fax_ringcentral.php');
        $ring = CI_Fax_RingCentral::getObject();

        $files = array(
            'abc' => TRCPDD_PATH . 'show.pdf'
        );
        $sendTime = date('c', time() + 5 * 60);
        $can_send = true;
        if ($ring->is_expired()) {
            $ring->refreshToken();
            if ($ring->is_expired()) {
                $can_send = false;
            }
        }
        if ($can_send) {
            $rs = $ring->fax($number, $files, 'test', $sendTime);
        } else {
            echo '<h2>Token is expiried</h2>';
        }


        if (isset($rs['uri'])) {
    ?>
            <h2>Sent Successfully to <?php echo $rs['to'][0]['phoneNumber'] ?></h2>
    <?php
        }
        var_dump($ring->res);
    }
    ?>
    <form method="post">
        <label>Fax to: </label>
        <input type="text" value="<?php echo $options['sandbox_to'] ?>" name="faxto" />
        <input type="submit" class="button" value="Send" />
    </form>

<?php
    exit;
}




if (isset($_GET['debug'])) {
    var_dump($ring->options);
}

?>
<div class="wrap">

    <h2>RingCentral Connect</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <?php if ($authorized) {
                    $offset_time = $options['time'] + $timezone_offset;
                    $expiry_in = date('Y-m-d H:i:s', $options['expires_in'] + $offset_time);
                    $refresh_token_expires_in = date("Y-m-d H:i:s", $options['refresh_token_expires_in'] + $offset_time);
                ?>
                    <div class="row">
                        <h4 style="color:green">Authorized</h4>
                        <div class="frow">
                            <label>Expiry In: </label>
                            <?php echo $expiry_in ?>
                        </div>
                        <div class="frow">
                            <label>Refresh Expiry In: </label>
                            <?php echo $refresh_token_expires_in ?>
                        </div>
                        <?php if (!empty($options['last_refreshed'])) { ?>
                            <div class="frow">
                                <label>Last Refresh: </label>
                                <?php echo $options['last_refreshed'] ?>
                            </div>
                        <?php } ?>
                        <?php if (!empty($options['last_cron'])) { ?>
                            <div class="frow">
                                <label>Last Cron: </label>
                                <?php echo $options['last_cron'] ?>
                            </div>
                        <?php } ?>
                        <div class="frow">
                            <label>Current: </label>
                            <?php echo date_i18n("Y-m-d H:i:s"); ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="row">
                    <label>Client ID</label>
                    <input type="text" name="options[client_id]" value="<?php echo isset($options['client_id']) ? $options['client_id'] : '' ?>" />
                    <?php if (isset($options['client_id']) && !empty($options['client_id'])) : ?>
                        <a href="<?php echo $ring->get_authorize_url() ?>" class="button"><?php echo ($authorized) ? 'ReAuthorize' : 'Authorize' ?></a>
                    <?php endif; ?>
                </div>
                <div class="row">
                    <label>Client Secret</label>
                    <input type="text" name="options[client_secret]" value="<?php echo isset($options['client_secret']) ? $options['client_secret'] : '' ?>" />
                </div>
                <div class="row">
                    <label>Redirect Url:</label>
                    <input type="text" readonly value="<?php echo $ring->get_redirect_uri() ?>" />
                </div>
                <div class="row">
                    <label>Sandbox phone To:</label>
                    <input type="text" name="options[sandbox_to]" value="<?php echo isset($options['sandbox_to']) ? $options['sandbox_to'] : '' ?>" />
                </div>
                <div class="row">
                    <?php
                    foreach (tr_pdd_get_batch_types() as $btype) {
                        $fieldname = 'auto_fax_approve_' . $btype;
                    ?>

                        <input type="hidden" name="otheroptions[<?php echo $fieldname ?>]" value="0" />
                        <label style="float: none;width:100%;"><input type="checkbox" name="otheroptions[<?php echo $fieldname ?>]" value="1" <?php checked(isset($otheroptions[$fieldname]) ? $otheroptions[$fieldname] : '') ?> />
                            Auto Fax when Approved <?php echo strtoupper($btype) ?> </label>

                    <?php } ?>
                </div>
                <div class="row">
                    <input type="hidden" name="options[use_sandbox]" value="0" />
                    <label><input type="checkbox" name="options[use_sandbox]" value="1" <?php checked(isset($options['use_sandbox']) ? $options['use_sandbox'] : '') ?> /> Use Sandbox</label>
                </div>

                <div class="row">
                    <input type="submit" value="Save" class="button-primary" />
                    <?php if ($authorized) { ?>
                        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'log', 'width' => '1200')) ?>" class="thickbox button">Log</a>
                        <a href="/wp-content/log/auto_fax_results.txt" target="_blank" class=" button">Log file</a>
                        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'test', 'TB_iframe' => 'true')) ?>" class="thickbox button">Test</a>
                        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'refresh_token', 'TB_iframe' => 'true')) ?>" class="thickbox button">Refresh</a>
                    <?php } ?>
                </div>
            </div>
            <div class="col2">
                <label>Notes</label>
                <textarea name="options[notes]"><?php echo isset($options['notes']) ? $options['notes'] : '' ?></textarea>
            </div>
        </div>
    </form>
</div>

<style>
    tr.sending td.sendstatus {
        background-color: #deefe5;
    }
</style>
<script>
    jQuery(function($) {
        $('body').on('click', 'a.show_popup', function() {
            if ($(this).hasClass('seach_assen')) return;
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function() {
                tb_show('', $url);
            }, 500);
            return false;
        }).on('click', 'a.seach_assen', function() {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function() {
                tb_show('', $url + '&s=' + $s);
            }, 300);
            return false;
        })
    })
</script>