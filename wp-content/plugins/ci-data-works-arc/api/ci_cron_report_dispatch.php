<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
class ci_cron_report_dispatch
{
    public $options = array();
    public $log = '';
    protected $option_key = 'ci_user_dispatch_schedules';

    public function __construct()
    {
        $this->options['enable'] = tr_get_option('ci_pdf_data', 'report_dispatch_schedule');
    }

    protected function get_wp_timezone()
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $tz_string = get_option('timezone_string');
        if (!empty($tz_string)) {
            return new DateTimeZone($tz_string);
        }

        $offset = (float) get_option('gmt_offset');
        $hours = (int) $offset;
        $minutes = (int) round(abs($offset - $hours) * 60);
        $sign = $offset < 0 ? '-' : '+';

        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
    }

    protected function is_within_dispatch_window($schedule)
    {
        $timezone = $this->get_wp_timezone();
        $now = new DateTime('now', $timezone);
        $today = $now->format('Y-m-d');

        $start = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $schedule['start_time'], $timezone);
        $end = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $schedule['end_time'], $timezone);

        if (!$start || !$end || $end <= $start) {
            return false;
        }

        return $now >= $start && $now <= $end;
    }

    public function cron($parms = array())
    {
        if (empty($this->options['enable'])) {
            return;
        }

        $user_id = $parms[0] ?? 0;
        $schedule_id = $parms[1] ?? '';

        if (empty($user_id) || empty($schedule_id)) {
            return;
        }

        $this->log .= "\nRun Dispatch Cron ... Send mail user id:" . $user_id . " id_schedule:" . $schedule_id . "\n";
        $this->send_dispatch($user_id, $schedule_id);
    }

    protected function send_dispatch($user_id, $schedule_id)
    {
        $schedules = get_option($this->option_key, []);

        if (empty($schedules[$user_id][$schedule_id])) {
            $this->log .= "\nDispatch schedule not found!\n";
            return;
        }

        $sc = $schedules[$user_id][$schedule_id];

        if (!empty($sc['is_pause'])) {
            $this->log .= "\nDispatch schedule is paused!\n";
            return;
        }

        if (!$this->is_within_dispatch_window($sc)) {
            $this->log .= "\nOutside dispatch window!\n";
            return;
        }

        $this->create_report_file($sc);
    }

    protected function build_dispatch_summary_rows($rows)
    {
        $summary = array();

        foreach ($rows as $row) {
            $clinic_name = !empty($row['cli_clinic']) ? $row['cli_clinic'] : 'Unknown Clinic';

            if (!isset($summary[$clinic_name])) {
                $summary[$clinic_name] = array(
                    'clinic' => $clinic_name,
                    'not_received_samples' => 0,
                    'oldest_hours' => 0,
                );
            }

            $summary[$clinic_name]['not_received_samples']++;
            $summary[$clinic_name]['oldest_hours'] = max($summary[$clinic_name]['oldest_hours'], (int) ($row['hours_since_doc'] ?? 0));
        }

        ksort($summary, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($summary as &$item) {
            $item['oldest_sample_age'] = $item['oldest_hours'] . 'h';
            unset($item['oldest_hours']);
        }
        unset($item);

        return array_values($summary);
    }

    protected function create_report_file($sc)
    {
        $report_type = $sc['report_type'] ?? 'not_received';
        $report_label = $sc['report_label'] ?? 'Not Received';
        $dir = WP_CONTENT_DIR . '/cache/abx';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $filename_csv = 'dispatch_' . sanitize_key($report_type) . '_' . date_i18n('Ymd_His') . '.csv';
        $filepath_csv = $dir . '/' . $filename_csv;
        $attachments = array('csv' => $filepath_csv);
        $csv_out_file = '';
        $filtered_rows = array();

        switch ($report_type) {
            case 'not_received':
                $_REQUEST = array_merge($_REQUEST, array(
                    'page' => 'ci_reports_not_received',
                    'trajax' => 'export_report',
                    'export_type' => 'csv',
                    'export_func' => 'dispatch',
                    'filepath_save_csv' => $filepath_csv,
                    'dispatch_only' => 1,
                    'from' => '',
                    'cli_ID' => 'all',
                    's' => '',
                ));
                include(TRCPDD_PATH . 'parts/reports/not_received.php');
                break;
            default:
                $this->log .= "Unsupported dispatch report type: " . $report_type . "\n";
                return;
        }

        if (!file_exists($filepath_csv)) {
            $this->log .= "Dispatch file not found: " . $filepath_csv . "\n";
            return;
        }

        $dispatch_schedule = new CI_Report_Dispatch_Schedule($report_type, $report_label);
        $summary_rows = $this->build_dispatch_summary_rows($filtered_rows);
        $attachment_name = !empty($csv_out_file) ? $csv_out_file : basename($filepath_csv);
        $new_attachments = array(
            $attachment_name => $filepath_csv,
        );

        $labarr = CI_Base_Lab::get_current_lab();
        $labinit = $labarr['lab_dba_init'] . ' - ' . $labarr['lab_name'];
        $domain = str_replace(array('www.', 'lab.'), '', $_SERVER['SERVER_NAME']);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = 'From: ' . $labinit . ' <no-reply@' . $domain . '>';

        wp_mail(
            $sc['email'],
            $dispatch_schedule->get_title(),
            $dispatch_schedule->get_mail_body($summary_rows),
            $headers,
            $new_attachments
        );

        $this->log .= "Dispatch mail sent\n";

        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
