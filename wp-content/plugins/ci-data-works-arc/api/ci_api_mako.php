<?php

include_once('ci_api_base_sftphl7.php');

class CI_Api_Mako extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/mako_orders/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/mako_results/';
	public $api_id 			= -42;
	public $log_file_slug 	= 'mako';
	public $api_key			= 'api_mako';


	public function __construct()
	{
		//override
		$this->enable_send_orders = true;
		$this->enable_receive_results = true;
		$this->enable_send_results = false;

		$this->test_code_order 	= 'te_mako_order';
		$this->test_code_result = 'te_mako_result';
		$this->group_code_order = 'tg_mako_order';
		$this->group_code_result = 'tg_mako_result';
		$this->clinic_fieldname 		= 'cli_mako_id';
		$this->lab_msh5 		= 'Mako';
		$this->msh5_by_reflab 	= true;
		$this->all_btypes 		= array('pcr');


		$this->options = get_option('tr_mako_st', array());

		parent::__construct($this->options);
	}
}
