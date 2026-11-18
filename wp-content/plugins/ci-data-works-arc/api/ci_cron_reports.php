<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
define('DO_EMAIL_SCHEDULE_REPORTS', true);

class ci_cron_reports
{
	public $options = array();
	public $log = '';
	public function __construct()
	{
		$this->options['enable'] = tr_get_option('ci_pdf_data', 'report_schedule');
	}

	public function cron($parms = array())
	{

		if (empty($this->options['enable'])) return;
		$user_id = $parms[0];
		$schedule_id = $parms[1];
		$this->log .= "\nRun Cron Report ... Send mail user id:" . $user_id . " id_schedule:" . $schedule_id . "\n";
		$this->send_reports($user_id, $schedule_id);
	}

	function ci_is_weekly_today($schedule)
	{
		if (!isset($schedule['sel_day_of_week'])) return false;
		return intval($schedule['sel_day_of_week']) === intval(current_time('w'));
	}

	function ci_is_monthly_today($schedule)
	{
		if (!isset($schedule['sel_day_of_month'])) return false;
		return intval($schedule['sel_day_of_month']) === intval(current_time('j'));
	}

	public function send_reports($user_id, $schedule_id)
	{
		$schedules = get_option('ci_user_schedules', []);

		// ==== CHECK EXIST ====

		if (
			empty($schedules[$user_id]) ||
			empty($schedules[$user_id][$schedule_id])
		) {
			$this->log .= "\nSchedule not found!\n";
			return;
		}

		$sc = $schedules[$user_id][$schedule_id];

		//$today = current_time('Y-m-d');
		// ==== COMMON CHECK ====
		if ($sc['is_pause'] == 1) {
			$this->log .= "\nSchedule is paused!\n";
			return;
		}
		//if (!empty($sc['last_run']) && $sc['last_run'] === $today) return;

		$this->log .= "\nSend mail to:" . $sc['email'];
		//$sc['last_run'] = $today;
		//update_option('ci_user_schedules', $sc);

		$this->ci_create_report_file($sc);

		$this->log .= sprintf(
			"Sent report to %s (uid:%d, sid:%s)\n",
			$sc['email'],
			$user_id,
			$schedule_id
		);
	}

	function ci_create_report_file($sc)
	{
		$dir = WP_CONTENT_DIR . '/cache/abx';
		if (!file_exists($dir)) wp_mkdir_p($dir);

		$pdf_out_file = '';
		$csv_out_file = '';

		$filename_pdf = 'report_' . date_i18n('Ymd_His') . '.pdf';
		$filepath_psd = $dir . '/' . $filename_pdf;
		$filename_csv = 'report_' . date_i18n('Ymd_His') . '.csv';
		$filepath_csv = $dir . '/' . $filename_csv;
		$attachments = [];

		if ($sc['is_pdf'] == 1) {
			$sc['filepath_save_pdf'] = $filepath_psd;
			$attachments['pdf'] = $filepath_psd;
		}

		if ($sc['is_csv'] == 1) {
			$sc['filepath_save_csv'] = $filepath_csv;
			$attachments['csv'] = $filepath_csv;
		}

		$trajax = 'report_all_panels';
		if ($sc['tab'] != 'all') $trajax = 'report_tabs';

		$_REQUEST = array_merge($_REQUEST, [
			'page'        => 'ci_reportabx_main',
			'tab'         => $sc['tab'],
			'trajax'      => $trajax,
			'export_type' => 'pdf',
			'export_func' => 'all',
			'month_range' => isset($sc['month_range']) ? $sc['month_range'] : 'last_0_month',
			'from'        => date_i18n('Y-m-01'),
			'to'          => date_i18n('Y-m-t'),
			'cid'         => $sc['cid'] ?? '',
			'gid'		  => $sc['gid'] ?? '',
			'view_by'     => $sc['view_by'] ?? ''
		]);
		if (!empty($sc['filepath_save_pdf'])) {
			$_REQUEST['filepath_save_pdf'] = $sc['filepath_save_pdf'];
		}

		if (!empty($sc['filepath_save_csv'])) {
			$_REQUEST['filepath_save_csv'] = $sc['filepath_save_csv'];
		}


		$this->log .= "\nCreate file success!";
		if ($sc['type_report'] == 'delay') {
			include(TRCPDD_PATH . 'parts/reports/sample_delay_trends.php');
		} else if ($sc['type_report'] == 'current_delays') {
			include(TRCPDD_PATH . 'parts/reports/pending_and_outstanding.php');
		} else if ($sc['type_report'] == 'test_volumes') {
			include(TRCPDD_PATH . 'parts/reports/test_volumes.php');
		} else if ($sc['type_report'] == 'tat_report') {
			include(TRCPDD_PATH . 'parts/reports/tat_report.php');
		} else if ($sc['type_report'] == 'quality_metrics') {
			include(TRCPDD_PATH . 'parts/reports/quality_metrics.php');
		} else if ($sc['type_report'] == 'providers') {
			include(TRCPDD_PATH . 'parts/reports/providers.php');
		} else if ($sc['type_report'] == 'collectors') {
			include(TRCPDD_PATH . 'parts/reports/collectors.php');
		} else if ($sc['type_report'] == 'processed_samples') {
			include(TRCPDD_PATH . 'parts/reports/processed_samples.php');
		} else if ($sc['type_report'] == 'prevalence_rates') {
			include(TRCPDD_PATH . 'parts/reports/prevalence_rates.php');
		} else if ($sc['type_report'] == 'positive_negative') {
			include(TRCPDD_PATH . 'parts/reports/positive_negative_report.php');
		} else if ($sc['type_report'] == 'audit') {
			include(TRCPDD_PATH . 'parts/reports/audit.php');
		} else if ($sc['type_report'] == 'blood_result') {
			include(TRCPDD_PATH . 'parts/reports/blood_result.php');
		} else if ($sc['type_report'] == 'clinic_panel_stats') {
			include(TRCPDD_PATH . 'parts/reports/clinic_panel_stats.php');
		} else if ($sc['type_report'] == 'insurance_verification') {
			include(TRCPDD_PATH . 'parts/reports/insurance_verification.php');
		} else if ($sc['type_report'] == 'rack') {
			include(TRCPDD_PATH . 'parts/reports/sapphire.php');
		} else {
			include(TRCPDD_PATH . 'parts/reportabx/main.php');
		}

		if (!empty($attachments)) {
			$new_attachments = [];
			foreach ($attachments as $filetype => $file) {
				if (!file_exists($file)) {
					$this->log .= 'File not found: ' . $file;
					return;
				}
				if ($filetype == 'pdf') {
					$filetype = !empty($pdf_out_file) ? $pdf_out_file : basename($file);
				} else if ($filetype == 'csv') {
					$filetype =  !empty($csv_out_file) ? $csv_out_file : basename($file);
				}
				$new_attachments[$filetype] = $file;
			}

			$labarr = CI_Base_Lab::get_current_lab();
			$labinit = $labarr['lab_dba_init'] . ' - ' . $labarr['lab_name'];
			$domain = str_replace(array('www.', 'lab.'), '', $_SERVER['SERVER_NAME']);

			$headers = array('Content-Type: text/html; charset=UTF-8');
			$headers[] = 'From: ' . $labinit . ' <no-reply@' . $domain . '>';
			wp_mail($sc['email'], $schedule_report->get_title(), $schedule_report->get_mail_body(), $headers, $new_attachments);
			$this->log .= "Send Mail success\n";
			foreach ($attachments as $file) {
				if (file_exists($file)) unlink($file);
			}
		}
	}
}
