<?php
require_once  'restful_api.php';

add_filter( 'https_ssl_verify', '__return_false' );


class Resful_Provista_api extends restful_api {

	public $api_id = - 23;
	public $key_data = array();

	function __construct( $pre_endpoint ) {
		//$this->hide_info = true;
		if(!empty($pre_endpoint))
		{
			parent::__construct( $pre_endpoint );
		}
	}

	protected function formatdate( $date ) {
		if ( strpos( $date, '/' ) > 0 ) {
			$date = date( 'Y-m-d', strtotime( $date ) );
		}

		return $date;
	}

	protected function check_key() {
		global $wpdb;

		if ( $this->endpoint == 'request_info' && $this->request_api_key == 'viewinfo' ) {
			return true;
		}

		$options = get_option('tr_api_provista_st',array());
		$key = base64_encode($options['username'].':'.$options['password']);
		if($key!=$this->request_api_key)
		{
			$this->response( 405, array( 'Status' => 'error', 'StatusCode' => 500 ) );
			exit;
		}

		return true;
	}

	protected function log($type,$key_id,$assen,$status,$xml)
	{
		global $wpdb;
		$at = date_i18n('Y-m-d H:i:s');

		$count = $wpdb->get_var("select wl_count from ci_wsdl_log where wl_ID={$key_id} 
						and wl_type='{$type}' 
						and wl_key='{$assen}' ");
		if($count > 0)
		{
			$rs = $wpdb->update('ci_wsdl_log',
				array(
					'wl_count'=>$count+1,
					'wl_data' => $xml,
					'wl_at' => $at,
					'wl_status'=>$status,
				),
				array('wl_ID'=>$key_id,'wl_type'=>$type,'wl_key'=>$assen));
		}else{
			$rs = $wpdb->insert('ci_wsdl_log',array(
					'wl_ID'=>$key_id,
					'wl_type'=>$type,
					'wl_key'=>$assen,
					'wl_count'=>1,
					'wl_status'=>$status,
					'wl_data' => $xml,
					'wl_at'=>$at,
					//'wl_ip' => $this->get_ip()
				)
			);
		}
		return $rs;
	}


	/**
	 * InsertResult
	 *
	 * - Request Json:
	 * {
	"externalOrderId": 211,
	"externalPatientId": 3923,
	"AccessionNo": "TestSpeciman21213",
	"OrderId": "VED-0002466",
	"Status": "Validated",
	"StatusChangeDateTime": "2021-07-02T21:58:53.307",
	"PatientFirstName": "Test",
	"PatientLastName": "Patient",
	"PatientAddress1": "Test Address 1",
	"PatientAddress2": "Test Address 2",
	"PatientCity": "Test City",
	"PatientState": "AL",
	"PatientZip": "12345",
	"PatientEmail": "Hassan@test.com",
	"PatientPhone": "2323232323",
	"PatientDob": "1997-10-10",
	"ResultValue": "Detected",
	"Type": "Amended Result"
	}
	 *
	 * - return: json
	 */
	public function request_InsertResult() {
		global $wpdb;

		$return = array( 'Status' => 'success','StatusCode'=>200);


		$assen      = $this->params['AccessionNo'];
		$result_vl  = $this->params['ResultValue'];

		$btype      = 'pcr';
		$batch_tbl  = tr_pdd_get_table_by_btype($btype);

		$report = $wpdb->get_row("select 
						ba.mid,
						ba.batch_id,
						ba.doc,
						re.id,
						re.estatus,
						re.approve_date,
						api.mo_ID
					from {$batch_tbl} ba
					left JOIN ci_reports_pcr re ON re.patient_id = ba.assen
					left JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = {$this->api_id}
                                        and api.mo_btype = '{$btype}'
					where assen = '{$assen}' ", ARRAY_A);

		if($report)
		{
			$report_data = array(
				'patient_id'=>$assen,
				'upload_files' => 'provista',
			);

			if($report['estatus']!='C'){
				$report_data['estatus'] = 'C';
				$report_data['approve_date'] = date_i18n('Y-m-d H:i:s');

				if(empty($report['batch_id'])){
					$batch_id = date('Ymd',strtotime($report['doc']));
					$wpdb->update($batch_tbl,array('batch_id'=>$batch_id),array('mid'=>$report['mid']));
				}
			}

			if($report['id']>0)
			{
				$report_data['covid_pos'] = null;
				$wpdb->update('ci_reports_pcr',$report_data,array('id'=>$report['id']));
			}else{
				$report_data['created'] = date_i18n('Y-m-d H:i:s');
				$report_data['created_date'] = date_i18n('Y-m-d');

				$wpdb->insert('ci_reports_pcr',$report_data);
				$report['id'] = $wpdb->insert_id;

			}

			if($report['id']>0)
			{
				$covid_drug = $wpdb->get_var("select te_name from ci_test_panels where te_type='pcr'
									and te_abrev = 'nCoV-2019' or te_name='SARS-CoV-2 PCR Nucleic Acid' ");

				$rsid = $wpdb->get_var("select id from ci_results_pcr 
								where report_id = {$report['id']}
								and drug = '{$covid_drug}' ");

				$pos_neg = '';
				if(stripos($result_vl,'Not Detected')!==false)
				{
					$pos_neg = 'NEG';
				}
				else if(stripos($result_vl,'Detected')!==false)
				{
					$pos_neg = 'POS';
				}
				else if(stripos($result_vl,'Inconclusive')!==false)
				{
					$pos_neg = 'INC';
				}else if(stripos($result_vl,'Invalid')!==false)
				{
					$pos_neg = 'INV';
				}
				//$return['pos_neg']= $pos_neg;

				if($rsid>0)
				{
					$wpdb->update('ci_results_pcr',array(
						'pos-neg' => $pos_neg,
					),array('id' => $rsid));
				}else{
					$wpdb->insert('ci_results_pcr',array(
						'drug' => $covid_drug,
						'report_id' => $report['id'],
						'pos-neg' => $pos_neg
					));
				}
			}else{
				$return['Status']='error';
				$return['StatusCode']=500;
				$return['Message']='';
			}


			//log
			if($report['mo_ID']>0)
			{
				$wpdb->update('ci_sync_api_orders',array(
					array('mo_peg_ID' => date_i18n('Y-m-d H:i:s'))
				),array('mo_ID'=>$report['mo_ID']));

			}else{
				$logarr = array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $report['mid'],
					'mo_btype'     => 'pcr',
					'mo_peg_ID' => date_i18n('Y-m-d H:i:s')
				);
				$wpdb->insert('ci_sync_api_orders',$logarr);
			}


		}else{
			$return['Status']='error';
			$return['StatusCode']=500;
			$return['Message']='AccessionNo Not found';
		}



		$this->response( 200, $return );
	}



}