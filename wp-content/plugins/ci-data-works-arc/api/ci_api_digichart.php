<?php

include_once('ci_api_base_sockethl7.php');

class CI_Api_DigiChart extends CI_Api_Base_SocketHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/digichart_orders/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/digichart_results/';
	public $api_id 			= -48;
	public $log_file_slug 	= 'digichart';
	public $api_key			= 'api_digichart';


	public function __construct()
	{
		//override
		$this->enable_send_results = true;


		$this->lab_msh3 = 'igniteLIS';
		$this->lab_msh4 = 'igniteLIS';
		$this->lab_msh5 = 'DigiChart';
		$this->lab_msh6 = 'OBGYNMONT';
		$this->msh5_by_reflab = true;
		$this->all_btypes = array('pcr');


		$this->options = get_option('tr_digichart_st', array());

		parent::__construct($this->options);
	}
}
