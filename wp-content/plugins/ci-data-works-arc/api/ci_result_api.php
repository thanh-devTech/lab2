<?php
require_once  'restful_api.php';

add_filter( 'https_ssl_verify', '__return_false' );


class CI_Result_Api extends restful_api {

	function __construct($pre_endpoint){
		//$this->hide_info = true;
		parent::__construct($pre_endpoint);
	}

	protected function formatdate($date)
	{
		if(strpos($date,'/')>0)
		{
			$date = date('Y-m-d',strtotime($date));
		}
		return $date;
	}

	protected function check_key()
	{
		global $wpdb;
		$pass = $wpdb->get_var("select wsdl_ID from ci_wsdl_keys where wsdl_key = '{$this->request_api_key}'");
		if(!$pass)
		{
			$this->response(405,array('status'=>'error','message'=>'wrong key'));
			exit;
		}
	}

	/**
	 * Put result
	 * - Requests:
	 *          + accession: Accession number
	 *          + test: test name
	 *          + result: pos/neg
	 * - return: json
	 *
	 * ex: /resultapi/put_result?key=abc&accession=ABC0001&test=Covid-19 Ag&result=pos
	 */
	public function request_put_result()
	{
		global $wpdb;

		$return         = array('status'=>'fail');

		$assen          = $this->params['accession'];
		$testname          = $this->params['test'];
		$result          = strtoupper($this->params['result']);

		if(!empty($assen) && !empty($testname) && !empty($result))
		{
			$found = false;
			foreach(tr_pdd_get_batch_types() as $btype)
			{
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);
				$result_tbl = tr_pdd_get_table_result_by_btype($btype);

				$mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$assen}' and patient_id>0");
				if($mid>0)
				{
					$found = true;

					$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id='{$assen}' ");
					if($report_id==0)
					{
						$wpdb->insert($report_tbl,array(
							'patient_id' => $assen,
							'estatus'  => 'C',
							'created_date' => date_i18n('Y-m-d'),
							'approve_date' => date_i18n('Y-m-d H:i:s'),
							'created' => date_i18n('Y-m-d H:i:s'),
						));
						$report_id = $wpdb->insert_id;
					}

					if($report_id>0)
					{
						$result_id = $wpdb->get_var("select id from {$result_tbl} where report_id = {$report_id}
													and drug = '{$testname}' ");
						if($result_id>0)
						{
							$wpdb->update($result_tbl,array('pos-neg'=>$result),array('id'=>$result_id));
						}else{
							$wpdb->insert($result_tbl,array(
								'report_id' => $report_id,
								'drug'      => $testname,
								'pos-neg'   => $result
							));
						}
						$return['status'] = 'success';
						$return['message'] = 'Success';
					}

					break;
				}

				if(!$found){
					$return['message'] = 'Accession not found';
				}
			}

		}else{
			$return['status']  = 'error';
			$return['message']  = 'Please check fields';
		}

		$this->response(200,$return);
	}


}