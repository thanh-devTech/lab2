<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
class CI_Cron_Auto_Reject
{
	var $options = array();
	var $log = '';
	public function __construct()
	{
		$this->options['rejecteddays'] = intval(tr_get_option('ci_pdf_data', 'rejectbydocdates', 0));
		$this->options['rejected_status'] = intval(tr_get_option('ci_pdf_data', 'rejectbydocdates_status', 'rejected'));
		$this->options['canceldays'] = intval(tr_get_option('ci_pdf_data', 'canceledbydocdates', 0));
		$this->options['enable'] = $this->options['rejecteddays'] > 0 || $this->options['canceldays'] > 0;
	}

	public function cron()
	{

		if ($this->options['rejecteddays'] > 0) {
			$this->auto_rejected();
		}

		if ($this->options['canceldays'] > 0) {
			$this->auto_canceled();
		}
	}

	function auto_canceled()
	{
		global $wpdb;
		echo 'run Cron auto Cancel<br>';
		$limitdates 	= $this->options['canceldays'];
		$timenow 		= strtotime(date_i18n('Y-m-d H:i:s'));
		$date_from 		= date('Y-m-d H:i:s', $timenow - 86400 * ($limitdates + 10));
		$date_to 		= date('Y-m-d H:i:s', $timenow - 86400 * $limitdates);

		echo 'datefrom:' . $date_from . '<br>';
		echo 'date_to:' . $date_to . '<br>';



		foreach (tr_pdd_get_batch_types() as $btype) {

			$canceled_status = $wpdb->get_var("select s_key from ci_batches_status 
			where s_type = 'ca' and istatus = 1 
			order by iorder asc limit 1");
			if (empty($canceled_status)) continue;

			$canceled_status = 'ca_' . $canceled_status;

			$rejected_status = tr_pdd_get_batch_statuses(false, true, true, $btype);
			$rejected_status[] = 'rejected';
			$rejected_status[] = $canceled_status;
			$rejected_status[] = 'archived';
			$rejected_status[] = 'received';
			$rejected_status_str = implode("','", $rejected_status);

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);


			$sql = "
			select mid, doc, assen from {$batch_tbl} ba
			Left JOIN {$report_tbl} re ON re.patient_id = ba.assen
			where ba.doc >'{$date_from}' and ba.doc <'{$date_to}' 
			and (ba.batch_id = 0 )
			and ba.batch_status not in ('{$rejected_status_str}')
			";
			$rs = $wpdb->get_results($sql);
			echo $sql;
			echo 'count: ' . count($rs);
			$this->log .= $wpdb->last_error;

			foreach ($rs as $r) {
				$wpdb->update($batch_tbl, array('batch_status' => $canceled_status), array('mid' => $r->mid));

				trpdd_log_user(-1, 'canceled_req', 'Changed to canceled', $r->mid, $btype, '', '', $r->assen);
			}
		}
	}

	function auto_rejected()
	{
		global $wpdb;
		echo 'run Cron auto reject<br>';
		$limitdates = $this->options['rejecteddays'];
		$rejected = !empty($this->options['rejected_status']) ? $this->options['rejected_status'] : 'rejected';
		$timenow = strtotime(date_i18n('Y-m-d H:i:s'));
		$date_from = date('Y-m-d H:i:s', $timenow - 86400 * ($limitdates + 10));
		$date_to = date('Y-m-d H:i:s', $timenow - 86400 * $limitdates);
		echo 'datefrom:' . $date_from . '<br>';
		echo 'date_to:' . $date_to . '<br>';



		foreach (tr_pdd_get_batch_types() as $btype) {

			$rejected_status = tr_pdd_get_batch_statuses(false, true, true, $btype);
			$rejected_status[] = 'rejected';
			$rejected_status[] = 'archived';
			$rejected_status[] = 'received';
			$rejected_status_str = implode("','", $rejected_status);

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);


			$sql = "
			select mid, doc, assen from {$batch_tbl} ba
			Left JOIN {$report_tbl} re ON re.patient_id = ba.assen
			where ba.doc >'{$date_from}' and ba.doc <'{$date_to}' 
			and (ba.batch_id = 0 or re.id is null)
			and ba.batch_status not in ('{$rejected_status_str}')
			";
			$rs = $wpdb->get_results($sql);
			echo $sql;
			echo 'count: ' . count($rs);
			$this->log .= $wpdb->last_error;

			foreach ($rs as $r) {
				$wpdb->update($batch_tbl, array('batch_status' => $rejected), array('mid' => $r->mid));

				trpdd_log_user(0, 'rejected_req', 'Changed to Rejected', $r->mid, $btype, '', '', $r->assen);
			}
		}
	}
}
