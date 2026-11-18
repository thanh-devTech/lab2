<?php
global $wpdb, $current_role;

include(TRCPDD_PATH . 'api/ci_api_genxys.php');

$log_dir            = WP_CONTENT_DIR . '/log/ci_api_genxys/';
$log_file           = WP_CONTENT_DIR . '/log/ci_api_genxys.txt';

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


if (isset($_POST['options'])) {
    $options = $_POST['options'];
    if (empty($options['cron'])) {
        $options['cron'] = 2;
    }
    $old_options = get_option('tr_hl7_api_genxys_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_hl7_api_genxys_st', $bkdata);
    
    update_option('tr_hl7_api_genxys_st', $options);
    Tr_Session_Class::add('Saved Settings Successfully');
    $options = get_option('tr_hl7_api_genxys_st', array());
    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Genxys'));

    if ($options['enable']) {
        wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Genxys'));
    }
}
$options = get_option('tr_hl7_api_genxys_st', array());


if (!isset($options['cron']) || empty($options['cron'])) {
    $options['cron'] = 2;
}

try {
    $client = new CI_Api_Genxys();
    $client->debug = true;
} catch (Exception $e) {
    Tr_Session_Class::add_error($e->getMessage());
}

if (!function_exists('ci_api_genxys_get_logged_instrument_files')) {
    function ci_api_genxys_get_logged_instrument_files($file_path)
    {
        if (empty($file_path) || !file_exists($file_path)) {
            return array();
        }

        $content = file_get_contents($file_path);
        if ($content === false || $content === '') {
            return array();
        }

        $logs = json_decode($content, true);
        if (!is_array($logs)) {
            return array();
        }

        foreach (array_reverse($logs) as $log_entry) {
            if (!empty($log_entry['message']['instrumentFiles']) && is_array($log_entry['message']['instrumentFiles'])) {
                return $log_entry['message']['instrumentFiles'];
            }
        }

        return array();
    }
}

if ($act == 'auth') {
    $rs = $client->auth();
    var_dump($rs);
    exit;
} else if ($act == 'send_orders') {
    try {
        $rs = $client->send_orders();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} else if ($act == 'get_results') {
    try {
        $rs = $client->get_result_file();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} else if ($act == 'resend') {
    try {
        $assen = $_REQUEST['assen'];
        $rs = $client->send_orders($assen);
        var_dump($rs);
    } catch (Exception $e) {
        var_dump($e);
    }
    exit;
} else if ($act == 'download_again') {
    try {
        $assen = $_REQUEST['assen'];
        $rs = $client->get_result_file($assen, 'gene');
        wp_send_json([
            "success" => true,
            "data" => $rs
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            "error" => $e->getMessage()
        ]);
    }
} else if ($act == 'log') {

    $btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'gene';
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $where = " o.mo_file_id='{$client->api_id}'  ";

    if (!empty($_REQUEST['s'])) {
        $where .= $wpdb->prepare(
            " and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s or o.mo_patient_ID= %s) ",
            $_REQUEST['s'] . '%',
            '%' . $_REQUEST['s'] . '%',
            $_REQUEST['s'],
            $_REQUEST['s']
        );
    }

    if ($client->options['ref_lab_id'] > 0) {
        $where .= " and ba.ref_lab_ID = {$client->options['ref_lab_id']} ";
    }



    $row_per_page = 200;
    if (isset($_GET['get_codes'])) {
        $row_per_page = 9999999999;
    }
    $pagination = new Tr_Pagination_class2($row_per_page);

    $rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'gene');


    $pagination->setTotal($wpdb->get_var("select count(*) as c from {$batch_tbl} ba
									left join {$report_tbl} re ON re.patient_id = ba.assen
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' 
	                                where {$where} "));

    $offset = $pagination->offset();



    $rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.spec_received,
                                ba.batch_created,
                                re.id,
                                re.estatus,
                                o.mo_ID,
                                o.mo_status,
                                o.mo_patient_ID,
								o.mo_sent_dt
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}' 
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
    echo $wpdb->last_error;
?>
    <div>
        <input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession or patient name">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                <th style="width:100px">Accession</th>
                <th>PID</th>
                <th>Patient</th>
                <th>Clinic</th>
                <th>Doctor</th>
                <th style="width:60px">Resulted</th>
                <th style="width:70px">Status</th>
                <th style="width:50px;">Sent</th>
                <th>DOC</th>
                <th style="width:100px;"></th>
                <th style="width:96px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($rows as $row) {
            ?>
                <tr class="childp" data-id="<?php echo $row->id ?>">
                    <td><?php echo $row->mid ?></td>
                    <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>"><?php echo $row->assen ?></a></td>
                    <td><?php echo $row->mo_patient_ID ?></td>
                    <td><?php echo $row->patient_name ?></td>
                    <td><?php echo $row->requesting_practice ?></td>
                    <td><?php echo $row->requesting_physician ?></td>
                    <td><a href="?page=<?php echo 'ci_results_' . $btype ?>&act=edit&id=<?php echo $row->id;
                                                                                        if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
                            <?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
                        </a>
                    </td>
                    <td>

                    </td>
                    <td><?php echo $row->mo_sent_dt ?></td>
                    <td><?php echo $row->doc ?></td>
                    <td>
                        <?php
                        echo '<a style="display: block" target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'resend', 'assen' => $row->assen), false) . '">Resend</a>';
                        echo '<a style="display: block" class="action_" href="" data-assen="' . $row->assen . '" data-action="download_again">Download again</a>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $base_dir = WP_CONTENT_DIR . '/log/ci_api_genxys/';
                        $base_url = content_url('/log/ci_api_genxys/');

                        $order_file = sanitize_file_name('order_' . $row->assen . '.json');
                        $download_file = sanitize_file_name('download_' . $row->assen . '.json');

                        $order_path = $base_dir . $order_file;
                        $download_path = $base_dir . $download_file;
                        $instrument_files = ci_api_genxys_get_logged_instrument_files($order_path);

                        if (file_exists($order_path)) {
                            echo '<a target="_blank" href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'file' => $order_file), false)  . '">View order</a>';
                        }

                        if (!empty($instrument_files)) {
                            if (file_exists($order_path)) echo ' | ';
                            echo '<a class="view-files-popup" data-assen="' . esc_attr($row->assen) . '" href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewfiles', 'assen' => $row->assen), false)  . '">View File</a>';
                        }

                        if (file_exists($download_path)) {
                            if (file_exists($order_path) || !empty($instrument_files)) echo ' | ';
                            echo '<a target="_blank" href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'file' => $download_file), false)  . '">View download</a>';
                        }
                        ?>
                    </td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>

    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <div id="genxys-view-files-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;">
        <div style="position:absolute;top:4%;left:50%;transform:translateX(-50%);width:92%;max-width:1200px;height:90%;background:#fff;border-radius:6px;box-shadow:0 12px 30px rgba(0,0,0,.25);overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #ddd;background:#f6f7f7;">
                <strong id="genxys-view-files-title">Sent files</strong>
                <a href="#" id="genxys-view-files-close" class="button">Close</a>
            </div>
            <div id="genxys-view-files-body" style="height:calc(100% - 56px);overflow:auto;padding:16px;"></div>
        </div>
    </div>
    <script>
        jQuery(function($) {
            function closeGenxysFilesModal() {
                $('#genxys-view-files-modal').hide();
                $('#genxys-view-files-body').html('');
            }

            $('body').on('click', '.view-files-popup', function(e) {
                e.preventDefault();

                var url = $(this).attr('href');
                var assen = $(this).data('assen') || '';

                $('#genxys-view-files-title').text('Sent files: ' + assen);
                $('#genxys-view-files-body').html('<div style="padding:20px;text-align:center;">Loading...</div>');
                $('#genxys-view-files-modal').show();

                $.get(url, function(html) {
                    $('#genxys-view-files-body').html(html);
                }).fail(function() {
                    $('#genxys-view-files-body').html('<div style="padding:20px;color:#b32d2e;">Cannot load sent files.</div>');
                });
            });

            $('body').on('click', '#genxys-view-files-close', function(e) {
                e.preventDefault();
                closeGenxysFilesModal();
            });

            $('body').on('click', '#genxys-view-files-modal', function(e) {
                if (e.target.id === 'genxys-view-files-modal') {
                    closeGenxysFilesModal();
                }
            });
        });
    </script>
<?php
    $content = ob_get_clean();
    if (isset($_GET['trajax'])) {
        $content = str_replace('class="', 'class="show_popup ', $content);
        $content = str_replace('class=\'', 'class=\'show_popup ', $content);
    }
    echo $content;
    exit;
} else if (!empty($act) && method_exists($client, $act)) {
    $rs = $client->{$act}($_GET['arg1']);
    var_dump($rs);
    exit;
} else if ($act == 'viewfiles') {

    $assen = sanitize_text_field($_REQUEST['assen'] ?? '');
    $base_dir = WP_CONTENT_DIR . '/log/ci_api_genxys/';
    $order_file = sanitize_file_name('order_' . $assen . '.json');
    $order_path = $base_dir . $order_file;
    $instrument_files = ci_api_genxys_get_logged_instrument_files($order_path);

    if (empty($assen)) {
        echo 'Missing accession!';
        exit;
    }

    if (empty($instrument_files)) {
        echo 'No sent files found for ' . esc_html($assen) . '!';
        exit;
    }

    echo '<div class="wrap">';
    echo '<h2>Sent files: ' . esc_html($assen) . '</h2>';

    foreach ($instrument_files as $ext => $base64code) {
        $decoded = base64_decode($base64code, true);
        $label = strtolower($ext) . '.' . strtolower($ext);

        echo '<div style="margin-bottom:20px">';
        echo '<div><strong>' . esc_html($label) . '</strong>';
        if ($decoded !== false) {
            echo ' (' . esc_html(size_format(strlen($decoded))) . ')';
        }
        echo '</div>';

        if ($decoded === false) {
            echo '<p>Unable to decode file content.</p>';
        } else {
            echo '<textarea readonly style="width:100%;min-height:240px;font-family:monospace">' . esc_textarea($decoded) . '</textarea>';
        }

        echo '</div>';
    }

    echo '</div>';
    exit;
} else if ($act == 'viewrshl7') {

    $file_name = $_REQUEST['file'] ?? '';

    $base_dir = WP_CONTENT_DIR . '/log/ci_api_genxys/';
    $safe_path = ($base_dir . $file_name);


    if ($safe_path && file_exists($safe_path)) {
        header('Content-Type: application/json');
        echo file_get_contents($safe_path);
    } else {
        var_dump($safe_path);
        echo 'File not found or invalid!';
    }

    exit;
}


$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);

$specimens = $wpdb->get_col("select ts_name from ci_test_specimens where ts_type = 'pcr' ");
$key = $wpdb->get_row("select wsdl_key from ci_wsdl_keys where wsdl_type like 'json_gene'");
$site_url = get_site_url();
?>

<div class="wrap">
    <h2>Genxys Settings</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="row">
            <div style="width:50%;float:left">
                <div class="row">
                    <label for="for_enable">Enable Cron</label>
                    <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
                </div>
                <div class="row">
                    <label>Clinic ID</label>
                    <input type="text" name="options[clinic_id]" value="<?php echo isset($options['clinic_id']) ? $options['clinic_id'] : '' ?>" required />
                </div>

                <div class="row">
                    <label>Username</label>
                    <input type="text" name="options[user]" value="<?php echo isset($options['user']) ? $options['user'] : '' ?>" required />
                </div>
                <div class="row">
                    <label>Password</label>
                    <input type="text" name="options[password]" value="<?php echo isset($options['password']) ? $options['password'] : '' ?>" required />
                </div>

                <div class="row">
                    <label>Callback url</label>
                    <input type="text" value="<?php echo $site_url . "/callback/genxys?key=" . $key->wsdl_key ?? "" ?>" readonly />
                </div>

                <div class="row">
                    <label>Schedule</label>
                    <select name="options[cron]">
                        <?php
                        foreach (array(1, 2, 5, 10, 30) as $m) {
                            $selected = $m == $options['cron'] ? 'selected' : '';
                            echo '<option value="' . $m . '" ' . $selected . '>each ' . $m . ' min(s)</option>';
                        }
                        ?>
                    </select>
                </div>


            </div>
            <div style="width:49%;float:right">
                <div class="frow">
                    <label>Notes</label>
                    <textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes']) ? esc_html($options['notes']) : '' ?></textarea>
                </div>
            </div>
        </div>


        <div class="frow row">
            <input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'auth'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Auth</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'send_orders'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Test send orders</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'get_results'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Test download files</a> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;
            <a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_genxys.txt' ?>">Log File</a> &nbsp;&nbsp;
            <?php
            $count = $wpdb->get_row("select count(*) as count_reqs,
                            SUM(IF(o.mo_sent=1,1,0)) as count_sent,
                            SUM(IF(o.mo_status=1,1,0)) as count_received
                            from ci_batches_gene ba
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid 
                            where o.mo_file_id = '{$client->api_id}' ");
            echo '<br>';
            echo 'Reqs: ' . $count->count_reqs . '<br>';
            echo 'Received: ' . $count->count_received . '<br>';
            echo 'Sent: ' . $count->count_sent . '<br>';
            echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

            $next_cron = wp_next_scheduled('cron_api_hl7_api_genxys');
            $current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

            $last_cron = file_exists($log_file) ? filemtime($log_file) : 0;

            if ($last_cron > 0) {
                echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
            }
            if ($next_cron) {
                echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
            }
            ?>
        </div>
    </form>
</div>


<style>
    #edittbl input.editfield {
        width: 100%;
    }
</style>
<script>
    var $doctors = <?php echo json_encode($doctors) ?>;
    var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
    jQuery(function($) {

        function ajaxAction($this) {
            var $pr = $this.parent();
            var $assen = $this.data('assen');
            var $action = $this.data('action');

            $pr.append('<div class="sloading"></div>');
            console.log($assen, $action)
            $.ajax({
                data: {
                    act: $action,
                    trajax: 1,
                    assen: $assen,
                },
                type: 'post',
                dataType: 'json',
                success: function(rs) {
                    console.log(rs);
                    if (rs.success) {

                    } else {
                        alert(rs.msg);
                    }
                },
                error: function(error) {
                    console.log("error:", error)
                },
                complete: function() {
                    $('.sloading', $pr).remove();
                }
            });
        }
        $('body').on('click', '.action_', function(e) {
            e.preventDefault()
            ajaxAction($(this));
        })
    })
</script>
