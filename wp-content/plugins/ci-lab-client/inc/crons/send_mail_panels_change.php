<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
add_action('init', function () {
    if (!wp_next_scheduled('ci_daily_test_log_email')) {
        $timestamp = strtotime(
            date_i18n('Y-m-d') . ' 23:50:00'
        );
        wp_schedule_event(
            $timestamp,
            'daily',
            'ci_daily_test_log_email'
        );
    }
});


add_action('ci_daily_test_log_email', 'ci_send_daily_test_log_email');

function ci_send_daily_test_log_email()
{
    global $wpdb;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE 'ci_log_users'");
    if (!$table_exists) {
        return;
    }

    $today_from = date_i18n('Y-m-d') . ' 00:00:00';
    $today_to   = date_i18n('Y-m-d') . ' 23:59:59';

    $logs = $wpdb->get_results("
        SELECT l.*, u.user_email
        FROM ci_log_users l
        JOIN {$wpdb->users} u ON u.ID = l.user_id
        WHERE l.user_action LIKE '%_test_%' or l.user_action LIKE '%_panel_%'
          AND l.created >= '{$today_from}'
          AND l.created <= '{$today_to}'
        ORDER BY l.created DESC
    ");

    if (empty($logs)) {
        return;
    }

    $html  = '<!DOCTYPE html><html><head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; }
        h2 { color: #0073aa; }
        .log-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .log-table th { background: #0073aa; color: #fff; padding: 8px 10px; text-align: left; font-size: 12px; }
        .log-table td { border: 1px solid #ddd; padding: 7px 10px; vertical-align: top; font-size: 12px; }
        .log-table tr:nth-child(even) td { background: #f9f9f9; }
        .change-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px; }
        .change-table th { border: 1px solid #ddd; padding: 3px 6px; background: #f5f5f5; color:#000; text-align: left; }
        .change-table td { border: 1px solid #ddd; padding: 3px 6px; }
        .cutoff-table { width: 100%; border-collapse: collapse; }
        .cutoff-table th { border: 1px solid #ddd; padding: 3px 6px; background: #f9f9f9; color:#000; font-size: 11px; white-space: nowrap; }
        .cutoff-table td { border: 1px solid #ddd; padding: 3px 6px; font-size: 11px; white-space: nowrap; }
        a { color: #0073aa; text-decoration: none; }
    </style>';
    $html .= '</head><body>';
    $html .= '<h2>Test Panel Change Logs — ' . date_i18n('Y-m-d') . '</h2>';
    $html .= '<p>Total changes: <strong>' . count($logs) . '</strong></p>';

    $html .= '<table class="log-table">';
    $html .= '<thead><tr>';
    $html .= '<th style="width:140px">Date</th>';
    $html .= '<th style="width:160px">Action</th>';
    $html .= '<th>Data</th>';
    $html .= '<th style="width:200px">User Email</th>';
    $html .= '<th style="width:120px">IP</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($logs as $log) {
        $html .= '<tr>';
        $html .= '<td>' . esc_html($log->created) . '</td>';
        $html .= '<td>' . esc_html($log->user_action) . '</td>';
        $html .= '<td>' . ci_render_log_data_email($log->data, $log->user_action) . '</td>';
        $html .= '<td>' . esc_html($log->user_email) . '</td>';
        $html .= '<td>' . esc_html($log->ip) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</body></html>';


    $args = array(
        'sent_to_admin' => 1,
        'subject'     => '[Lab] Daily Test Panel Logs — ' . date('Y-m-d'),
        'message'     => $html,
        'headers'     => array('Content-Type: text/html; charset=UTF-8'),
        'attachments' => array(),
    );
    include_once(TRLCN_PATH . '/api/ci_lab_client.php');
    $client = new CI_Lab_Client_Api(true);

    $client->send_mail_to_hub(['data' => $args]);
}

function ci_render_log_data_email($json_data, $user_action = '')
{
    $data = json_decode($json_data, true);

    if (!$data || !is_array($data)) {
        return esc_html($json_data);
    }

    $id   = isset($data['id'])   ? $data['id']   : '';
    $name = isset($data['name']) ? $data['name'] : '';

    $changes = $data;
    unset($changes['id']);
    unset($changes['name']);
    unset($changes['log_type']);

    $html = '';

    if ($id && $name) {
        $base_url = admin_url('admin.php?page=ci_pdf_data&pagetab=ci_test_panels&act=edit&id=' . $id);
        $html    .= '<a href="' . $base_url . '" target="_blank"><strong>' . esc_html($name) . '</strong></a>';
    }

    if (!empty($changes)) {
        $html .= '<table class="change-table" style="margin-top: 6px;">';
        $html .= '<thead><tr>';
        $html .= '<th>Field</th><th>Old</th><th>New</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($changes as $field => $values) {
            if ($field === 'cutoffs') {
                $old_cutoffs = isset($values[0]) && is_array($values[0]) ? $values[0] : array();
                $new_cutoffs = isset($values[1]) && is_array($values[1]) ? $values[1] : array();

                $html .= '<tr>';
                $html .= '<td><strong>cutoffs</strong></td>';
                $html .= '<td>' . ci_render_cutoffs_table_email($old_cutoffs) . '</td>';
                $html .= '<td>' . ci_render_cutoffs_table_email($new_cutoffs) . '</td>';
                $html .= '</tr>';
                continue;
            }

            if (is_array($values)) {
                $old_val = isset($values[0]) ? ci_format_value_email($values[0]) : '';
                $new_val = isset($values[1]) ? ci_format_value_email($values[1]) : '';
            } else {
                $old_val = ci_format_value_email($values);
                $new_val = '';
            }

            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($field) . '</strong></td>';
            $html .= '<td>' . $old_val . '</td>';
            $html .= '<td>' . $new_val . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
    }

    return $html;
}

function ci_render_cutoffs_table_email($cutoffs)
{
    if (empty($cutoffs) || !is_array($cutoffs)) {
        return '<em style="color:#999;">—</em>';
    }

    $field_labels = array(
        'cu_ms'            => 'Instrument',
        'cu_cutoff'        => 'Cutoff',
        'cu_upper_limit'   => 'Upper Limit',
        'cu_medium'        => 'Medium',
        'cu_critical_low'  => 'Critical Low',
        'cu_critical_high' => 'Critical High',
        'cu_age'           => 'Age',
    );

    $fields = array();
    foreach ($cutoffs as $cut) {
        if (is_array($cut)) {
            foreach (array_keys($cut) as $k) {
                if (!in_array($k, $fields) && isset($field_labels[$k])) {
                    $fields[] = $k;
                }
            }
        }
    }

    if (empty($fields)) {
        return '<em style="color:#999;">—</em>';
    }

    $html  = '<table class="cutoff-table">';
    $html .= '<thead><tr>';
    foreach ($fields as $f) {
        $html .= '<th>' . esc_html($field_labels[$f]) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($cutoffs as $cut) {
        if (!is_array($cut)) continue;
        $html .= '<tr>';
        foreach ($fields as $f) {
            $val   = isset($cut[$f]) ? $cut[$f] : '';
            $html .= '<td>' . esc_html($val) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}

function ci_format_value_email($value)
{
    if (is_array($value)) {
        return esc_html(implode(', ', $value));
    } elseif (is_bool($value)) {
        return $value
            ? '<span style="color:green;">Yes</span>'
            : '<span style="color:red;">No</span>';
    } elseif (is_null($value)) {
        return '<em style="color:#999;">null</em>';
    } else {
        return esc_html($value);
    }
}
