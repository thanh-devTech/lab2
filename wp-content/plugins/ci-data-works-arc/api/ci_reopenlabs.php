<?php
require_once  'restful_api.php';

add_filter( 'https_ssl_verify', '__return_false' );


class CI_ReopenLabs_Api extends restful_api {

	public $api_id = - 17;
	public $key_data = null;

	function __construct( $pre_endpoint ) {
		//$this->hide_info = true;
		parent::__construct( $pre_endpoint );
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

		$this->key_data = $wpdb->get_row( "select * from ci_wsdl_keys 
							where wsdl_key = '{$this->request_api_key}'
							and wsdl_type='ropcr' " );
		if ( ! $this->key_data ) {
			$this->response( 405, array( 'status' => 'error', 'message' => 'wrong key' ) );
			exit;
		}

		return true;
	}

	protected function get_all_tests()
	{
		global $wpdb;
		$all_tests = array();
		$rs = $wpdb->get_results("select te_ID,te_name from ci_test_panels where te_type = 'pcr' ");
		foreach($rs as $r)
		{
			$all_tests[$r->te_ID] = $r->te_name;
		}

		$rs = $wpdb->get_results("select tg_ID,tg_name from ci_test_groups where tg_type = 'pcr' ");
		foreach($rs as $r)
		{
			$all_tests['GROUP_'.$r->tg_ID] = $r->tg_name;
		}
		return $all_tests;
	}

	protected function get_ip()
	{
		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

			$ips = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim($ips[0]);
			if(!empty($ip))return $ip;
		}

		if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
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
					'wl_ip' => $this->get_ip()
				)
			);
		}
		return $rs;
	}



	/**
	 * GET orders
	 *
	 *
	 * - Requests:
	 *      + limit: default 10
	 *
	 * - return: json orders
	 */
	public function request_get_orders() {
		global $wpdb;

		$return = array( 'status' => 'ok', 'number' => 0, 'total'=>0, 'orders' => array() );

		$limit      = isset( $this->params['limit'] ) && $this->params['limit'] > 0 ? $this->params['limit'] : 10;
		if($limit>100)
		{
			$limit = 100;
		}
		$start_date = $this->key_data->wsdl_start;
		$cli_IDs = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$this->key_data->wsdl_ID}");


		$all_tests = $this->get_all_tests();

		$where = "";
		if ( ! empty( $start_date ) ) {
			$where .= " and ba.doc >= '{$start_date}' ";
		}
		if(count($cli_IDs)==1 && $cli_IDs[0]==-1)
		{
			$cli_IDs = array();
		}

		if(count($cli_IDs)>0)
		{
			$cli_IDs_str = implode(',',$cli_IDs);
			$where.= " and ba.cli_ID in ({$cli_IDs_str}) ";
		}

		if($this->key_data->wsdl_ref_lab_ID >0)
		{
			$where.= " and ba.ref_lab_ID  =  {$this->key_data->wsdl_ref_lab_ID} ";
		}

		$sql = "select ba.mid,
                    ba.assen,
                    ba.type_of_specimen,
                    ba.doc,
                    ba.requesting_practice,
                    ba.panel_tests_requested,
                    ba.requesting_physician,
                    pa.pa_first,
                    pa.pa_last,
                    pa.pa_gender,
                    pa.pa_dob,
                    pa.pa_address,
                    pa.pa_city,
                    pa.pa_state,
                    pa.pa_zip,
                    lab.lab_dba,
                    lab.lab_billing_account,
                    doc.doc_first,
                    doc.doc_last,
                    doc.doc_npi,
                    cli.cli_experity_id
                    
            from ci_batches_pcr ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = {$this->api_id}
                                        and api.mo_btype = 'pcr'
             where api.mo_ID is null {$where}
             order by mid asc
			 limit {$limit}";

		$sql_total = "select count(*)
            from ci_batches_pcr ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = {$this->api_id}
                                        and api.mo_btype = 'pcr'
             where api.mo_ID is null {$where}";
		$total = $wpdb->get_var($sql_total);
		$return['total'] = $total;

		$rs = $wpdb->get_results( $sql );

		$return['number'] = count( $rs );
		foreach ( $rs as $r ) {
			$patientdata        = array(
				'first_name' => $r->pa_first,
				'last_name'  => $r->pa_last,
				'gender'        => $r->pa_gender,
				'dob'        => $r->pa_dob,
				'address'    => $r->pa_address,
				'city'       => $r->pa_city,
				'state'      => $r->pa_state,
				'zip'        => $r->pa_zip,
			);
			$tests              = array();
			foreach(explode(',',$r->panel_tests_requested) as $t) {
				if ( isset( $all_tests[ $t ] ) ) {
					$tests[] = $all_tests[ $t ];
				}
			}

			$rowdata            = array(
				'system' => 'igniteLIS',
				'sending_software' => 'igniteLIS',
				'billing_account' => $r->lab_billing_account,
				'location' => $r->lab_dba,
				'accession' => $r->assen,
				'experity_id' => $r->cli_experity_id,
				'type_of_specimen' => $r->type_of_specimen,
				'doc'       => $r->doc,
				'clinic'    => $r->requesting_practice,
				'doctor_first_name'    => $r->doc_first,
				'doctor_last_name'    => $r->doc_last,
				'doctor_npi'    => $r->doc_npi,
				'patient'   => $patientdata,
				'tests'     => $tests
			);
			$return['orders'][] = $rowdata;
		}

		$this->log('get_orders',$this->key_data->wsdl_ID,date_i18n('Y-m-d H:i:s'),$return['status'],json_encode($return));

		$this->response( 200, $return );
	}


	/**
	 * PUT list orders were imported
	 *
	 * - Request json:
	 * {"status":"done","accessions":["accession1", "accession2"]}
	 *
	 * - return: json status
	 *
	 */
	public function request_orders_imported()
	{
		global $wpdb;

		$return = array( 'status' => 'ok','accessions' => array() );

		$accessions = is_array($this->params['accessions'])? $this->params['accessions'] : array();
		$accessions_str = implode("','", $accessions);
		$bats = $wpdb->get_results("select mid,assen from ci_batches_pcr 
								where assen in ('{$accessions_str}')");
		foreach($bats as $bat)
		{

			$exid = $wpdb->get_var("select mo_ID from ci_sync_api_orders api
									where api.mo_mid = {$bat->mid} 
									and api.mo_file_id = {$this->api_id}
									and api.mo_btype = 'pcr'");

			if(!$exid)
			{
				$wpdb->insert('ci_sync_api_orders',array(
					'mo_mid' => $bat->mid,
					'mo_file_id'  => $this->api_id,
					'mo_btype' => 'pcr',
					'mo_test_ID' => date_i18n('Y-m-d H:i:s'),
				));
			}
			$return['accessions'][] = $bat->assen;
		}

		$logarr = array(
			'request' => $this->params,
			'return' => $return
		);

		$this->log('orders_imported',$this->key_data->wsdl_ID,date_i18n('Y-m-d H:i:s'),$return['status'],json_encode($logarr));

		$this->response( 200, $return );
	}


	/**
	 * Put result
	 *
	 * - Requests json:
	 *
	 * {"results":[{"accession":"Accession here","experity_id":"experityID","dob":"1950-02-29","patient_name":"John,Patient","testname":"test name","result":"POS/NEG/INV/INC/..."}]}
	 * POS: Positive
	 * NEG: Negative
	 * INV: Invalid
	 * INC: Inconclusive
	 * - return: json status
	 *
	 */
	public function request_put_results()
	{
		global $wpdb;

		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';
		$result_tbl = 'ci_results_pcr';

		$return         = array('status'=>'success');

		$results          = $this->params['results'];


		foreach($results as $row)
		{
			$assen = $row['accession'];
			$testname = $row['testname'];
			if($testname=='COVID19 Panel'){
				$testname = 'SARS-CoV-2 PCR Nucleic Acid';
			}
			if(empty($assen) || empty($testname))
			{
				continue;
			}
			$result = $row['result'];
			if(stripos($result,'pos')!==false){
				$result = 'POS';
			}else if(stripos($result,'neg')!==false){
				$result = 'NEG';
			}else if(stripos($result,'inv')!==false) {
				$result = 'INV';
			}else if(stripos($result,'inc')!==false){
				$result = 'INC';
			}

			$mid = $wpdb->get_var( "select mid from {$batch_tbl} where assen = '{$assen}'" );
			if ( $mid > 0 ) {
				$found = true;

				$report_id = $wpdb->get_var( "select id from {$report_tbl} where patient_id='{$assen}' " );
				if ( $report_id == 0 ) {
					$wpdb->insert( $report_tbl, array(
						'patient_id'   => $assen,
						'estatus'      => 'C',
						'created_date' => date_i18n( 'Y-m-d' ),
						'approve_date' => date_i18n( 'Y-m-d H:i:s' ),
						'created'      => date_i18n( 'Y-m-d H:i:s' ),
					) );
					$report_id = $wpdb->insert_id;
				}

				if ( $report_id > 0 ) {
					$result_id = $wpdb->get_var( "select id from {$result_tbl} where report_id = {$report_id}
													and drug = '{$testname}' " );
					if ( $result_id > 0 ) {
						$wpdb->update( $result_tbl, array( 'pos-neg' => $result ), array( 'id' => $result_id ) );
					} else {
						$wpdb->insert( $result_tbl, array(
							'report_id' => $report_id,
							'drug'      => $testname,
							'pos-neg'   => $result
						) );
					}
				}
			}
		}

		$this->log('put_results',$this->key_data->wsdl_ID,date_i18n('Y-m-d H:i:s'),$return['status'],json_encode($results));

		$this->response(200,$return);
	}


}