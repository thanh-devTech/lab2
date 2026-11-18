<?php


include_once('ci_api_base_sftphl7.php');

class CI_Api_Hl7_Ellkay extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/ellkay_sftp/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/hl7_ellkay/';
	public $api_id 			= -9;
	public $log_file_slug 	= 'ellkay';
	public $api_key 		= 'ellkay';


	public function __construct()
	{
		//override
		$this->enable_send_orders = false;
		$this->enable_receive_results = false;


		$this->enable_receive_orders = true;
		$this->enable_send_results = true;

		$this->send_all_panels = true;



		$this->lab_msh5 = 'ELLKAY';
		$this->lab_msh6 = 'ELLKAY';
		$this->clinic_fieldname = 'cli_ellkay_id';
		$this->clinic_send_result_field = 'cli_ellkay_send_result_meta';
		$this->all_btypes = array('pcr');



		$this->options = get_option('tr_hl7_ellkay_st', array());

		parent::__construct($this->options);
	}
}
