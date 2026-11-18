<?php

include_once('ci_api_base_sftphl7.php');

class CI_Api_OpenText_PCC extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/opentextpcc/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/opentextpcc_results/';
	public $api_id 			= -18;
	public $log_file_slug 	= 'openpcc';


	public function __construct()
	{
		//override
		$this->enable_send_orders = false;
		$this->enable_receive_results = false;


		$this->enable_receive_orders = true;
		$this->enable_send_results = true;
		
		$this->send_all_panels = true;

		$this->result_mic = true;

		$this->lab_msh5 = 'PSC';
		$this->lab_msh6 = 'EMRLINK';
		$this->clinic_fieldname = 'cli_pcc_id';
		$this->clinic_send_result_field = 'cli_pcc_id_meta';
		$this->api_key = 'api_opentextpcc';
		$this->all_btypes = array('blood', 'pcr');



		$this->options = get_option('tr_opentext_pcc_st', array());

		parent::__construct($this->options);
	}
}
