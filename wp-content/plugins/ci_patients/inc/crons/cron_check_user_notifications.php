<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

// Log file path
unlink(WP_CONTENT_DIR . '/debug-notification-approve.log');
$log_file = WP_CONTENT_DIR . '/debug-notification-approve.txt';
function write_log_file($file, $message)
{
    $time = date('Y-m-d H:i:s');
    // file_put_contents($file, "[$time] $message\n", FILE_APPEND);
}

// Get last processed log id
$last_id = (int) get_option('last_approve_log_id', 0);

$sql = $wpdb->prepare("
    SELECT l.id, l.mid, l.btype, l.user_id AS actor_id
    FROM ci_log_users l
    WHERE l.id > %d
      AND l.user_action LIKE %s
      AND l.created >= NOW() - INTERVAL 3 DAY
    ORDER BY l.id ASC
", $last_id, '%approve%');

$logs = $wpdb->get_results($sql);

write_log_file($log_file, "SQL: " . $wpdb->remove_placeholder_escape($sql));
write_log_file($log_file, "Found logs: " . count($logs));

if ($logs) {
    $max_id = $last_id;

    foreach ($logs as $log) {
        write_log_file($log_file, "Processing log_id={$log->id}, mid={$log->mid}, btype={$log->btype}");

        $btable = tr_pdd_get_table_by_btype($log->btype);

        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT u.ID
            FROM {$btable} b
            INNER JOIN {$wpdb->users} u ON u.patient_id = b.patient_id
            WHERE b.mid = %s
        ", $log->mid));

        write_log_file($log_file, "Mapped user_id: " . var_export($user_id, true));

        if ($user_id) {
            $result = $wpdb->insert(
                "{$wpdb->prefix}ci_user_notifications",
                [
                    'user_id' => $user_id,
                    'title'   => 'Result Approval',
                    'message' => 'Your OncoSure Tests results are now available. View your detailed report.',
                    'type'    => 'report',
                    'target'  => $log->btype . '_' . $log->mid,
                    'viewed'  => 0,
                    'created' => $log->created
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
            );

            write_log_file($log_file, "Insert result: " . ($result ? "OK" : "FAILED") . " | Last error: " . $wpdb->last_error);
        }

        if ($log->id > $max_id) {
            $max_id = $log->id;
        }
    }

    update_option('last_approve_log_id', $max_id);
    write_log_file($log_file, "Updated last_approve_log_id to {$max_id}");
}
