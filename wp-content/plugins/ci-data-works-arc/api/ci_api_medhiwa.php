<?php
class CI_Api_Medhiwa
{
	var $headers = array();
	var $is_test = false;
	public $log = '';
	public $options = array();
	var $api_id = -19;

	public function __construct($options) {
		$this->options = $options;
	}


	protected function send_result($params=array()) {
		$ch = curl_init($this->options['url']);
		$this->headers = array('Authorization: Basic ' . $this->options['key']);
		$this->headers[] = 'Content-Type: application/json';
		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers );


		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseBody = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if(isset($_GET['debug2'])){
			var_dump($url);
			var_dump($responseBody);
		}

		$responseData = json_decode($responseBody, true);
		return $responseData;
	}


	function sync_reports($limit = 10)
	{
		global $wpdb;
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$sql = "select re.id,
					re.estatus,
					re.modified,
					ba.mid,
					ba.assen,
					ba.oldreq,
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID
					
				FROM {$batch_tbl} ba
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				LEFT JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
							 and mo.mo_file_id = '{$this->api_id}'
				where re.estatus = 'C'
				and (mo.mo_ID is null or mo.mo_status = 0)
				limit {$limit}
				";
		$rs = $wpdb->get_results($sql, ARRAY_A);


		$this->log.= "count: ".count($rs)."\n";

		$mo_ids = array();

		foreach($rs as $report)
		{
			$file_path = trpdd_export_results_pdf($report['id'],'',false,'pcr');

			if(!file_exists($file_path))
			{
				continue;
			}


			$data = array(
				'accessionNumber' => $report['oldreq'],
				'RecordNo' => $report['mo_set_ID'],
				'status' => 'Results Finalized',
				'reports' => array(
					'resultFileName' => basename($file),
					'resultFileContentType' => 'application/pdf',
					'resultFileContentStream' => base64_encode(file_get_contents($file_path))
				)
			);
			var_dump($data);
			$rs = $this->send_result($data);
			var_dump($rs);

			if($rs && $rs['status']=='200')
			{
				$mo_ids[] = $report['mo_ID'];
			}
		}


		if(count($mo_ids)>0)
		{
			$datetime = date_i18n('Y-m-d H:i:s');
			$mo_id_str = implode(',',$mo_ids);
			$sql = "update ci_sync_api_orders 
								set mo_status = 1,
								mo_peg_ID = '{$datetime}'
								where mo_ID in ({$mo_id_str})";
			$wpdb->query($sql);

		}
		return count($mo_ids);
	}

}