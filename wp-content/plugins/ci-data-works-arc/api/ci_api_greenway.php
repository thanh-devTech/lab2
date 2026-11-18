<?php

include_once('ci_api_base_sftphl7.php');

class CI_Api_Greenway extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/greenway/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/greenway_results/';
	public $api_id 			= -50;
	public $log_file_slug 	= 'greenway';
	public $api_key			= 'api_greenway';


	public function __construct()
	{
		//override
		$this->enable_send_orders = false;
		$this->enable_receive_results = false;


		$this->enable_receive_orders = true;
		$this->enable_send_results = true;


		$this->dont_send_pdf_no_pos = false;


		$this->clinic_fieldname = '';
		$this->lab_msh5 = 'GreenWay';
		//$this->msh5_by_reflab = true;
		$this->all_btypes = array('pcr');


		$this->options = get_option('tr_greenway_st', array());

		parent::__construct($this->options);
	}
}
