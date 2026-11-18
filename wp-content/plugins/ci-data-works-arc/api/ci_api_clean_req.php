<?php
class CI_Api_Clean_Req
{
	public $options = array();
	public $log = '';
	public function __construct()
	{
		$this->options['enable'] = true;
	}

	public function cron()
	{
		//check auto reject by insurance
		if (tr_get_option('ci_pdf_data', 'req_auto_rej_ins')) {
			$this->auto_rejected_by_ins();
		}
	}

	function auto_rejected_by_ins()
	{
		global $wpdb;
		$labs = $wpdb->get_results("select * from ci_labs where lab_status in (1,3)");
		foreach ($labs as $lab) {
			$lab->insurances  = !empty($lab->lab_auto_rej_insurance) ? json_decode($lab->lab_auto_rej_insurance, true) : array();
			if (!empty($lab->insurances)) {
				$this->auto_rejected_by_ins_lab($lab);
			}
		}
	}

	function auto_rejected_by_ins_lab($lab)
	{
		global $wpdb;

		global $wpdb;
		echo 'run Cron auto reject ' . $lab->lab_name . '<br>';
		$insurance_str = implode("','", $lab->insurances);
		if (empty($insurance_str)) {
			return false;
		}

		$date_from = date_i18n('Y-m-d', time() - 86400);
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);


			$sql = "
			select mid, doc from {$batch_tbl} ba
			where ba.doc >='{$date_from}' 
			and ba.lab_ID = {$lab->lab_ID}
			and ba.insurance in ('{$insurance_str}')
			and (ba.batch_id >0 )
			and ba.batch_status != 'rejected' 
			";
			$rs = $wpdb->get_results($sql);

			echo 'count: ' . count($rs);
			$this->log .= $wpdb->last_error;

			foreach ($rs as $r) {
				$wpdb->update($batch_tbl, array('batch_status' => 'rejected'), array('mid' => $r->mid));
			}
		}
	}
}
