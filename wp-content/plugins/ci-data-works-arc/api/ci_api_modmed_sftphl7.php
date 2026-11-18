<?php

include_once('ci_api_base_sftphl7.php');


class CI_Api_Modmed_Sftphl7 extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/modmed/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/modmed_results/';
	public $api_id 			= -80;
	public $log_file_slug 	= 'modmed';
	public $api_key			= 'api_modmed';


	public function __construct()
	{
		//override
		$this->enable_send_orders = false;
		$this->enable_receive_results = false;


		$this->enable_receive_orders = true;
		$this->enable_send_results = true;

		$this->send_all_results = false;
		$this->send_result_ct = false;
		//$this->send_result_range = true;
		//$this->folder_by_panel = false;
		//	$this->folder_by_btype = false;
		//	$this->send_when_meta = '';



		$this->sent_field = 'mo_sent';


		$this->clinic_fieldname = 'cli_modmed_code';
		$this->lab_msh5 = 'ModMed';
		$this->lab_msh6 = '';
		//$this->msh5_by_reflab = true;
		$this->all_btypes = array('pcr');

		$this->pdf_format['OBX.5.1'] = 'IGNITELIS';
		$this->pdf_format['OBX.5.2'] = 'Image';
		$this->pdf_format['OBX.5.3'] = 'PDF';


		$this->obx_loinc_456 = true;


		$this->options = get_option('tr_modmed_st', array());

		parent::__construct($this->options);
	}

	public function settingFields()
	{
		global $wpdb;
		$args =  array(
			'host' => array('type' => 'text', 'label' => 'Host', 'required' => true),
			'port' => array('type' => 'text', 'label' => 'Port', 'required' => true),
			'username' => array('type' => 'text', 'label' => 'Username', 'required' => true),
			'password' => array('type' => 'text', 'label' => 'Password', 'required' => true),
			'app_sender_name' => array('type' => 'text', 'label' => 'Sender Name'),
			'app_sender_code' => array('type' => 'text', 'label' => 'Sender Code'),
			'app_receiver_name' => array('type' => 'text', 'label' => 'Receiver Name'),
			'app_receiver_code' => array('type' => 'text', 'label' => 'Receiver Code'),
			'start_date' => array('type' => 'date', 'label' => 'Start Date'),
			'out_folder' => array('type' => 'text', 'label' => 'Out Folder', 'required' => true),
			'in_folder' => array('type' => 'text', 'label' => 'In Folder', 'required' => true),
			'cron' => array('type' => 'select', 'options' => array(30 => 'each 30mins', 10 => 'each 10mins', 5 => 'each 5mins', 2 => 'each 2mins'), 'label' => 'Schedule'),
		);




		return $args;
	}

	public function settingActions()
	{
		$arr = array(
			'test_connect' => array('label' => 'Test Connect'),
			'test_sync_orders' => array('label' => 'Test get Orders'),
			'test_send_reports' => array('label' => 'Test send Reports'),
			'test_upload' => array('label' => 'Test Upload'),
			'list_ids' => array('label' => 'Clinic IDs'),
			'log' => array('label' => 'Log'),
		);
		if (isset($_GET['debug'])) {
			$arr['install'] = array('label' => 'Install');
			$arr['put_demo'] = array('label' => 'Put demo');
		}
		return $arr;
	}

	public function setting_count()
	{
		global $wpdb;
		echo '<table style="min-width:300px">';
		echo '<tr><th>Type</th><th>Reqs</th><th>Results</th><th>Sent</th><th>Received</th><th></th></tr>';
		foreach ($this->all_btypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$joins = '';
			if (!empty($this->send_when_meta)) {
				$joins .= " JOIN {$batch_tbl}_meta mw ON mw.mid = ba.mid and mw.meta_key='{$this->send_when_meta}' ";
			}


			$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_sent in (1,2),1,0)) as count_sent,
                            SUM(IF(o.mo_status in (2),1,0)) as count_resend
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID and fi.m_type='{$this->log_file_slug}'
                            where ba.doc >= '{$this->options['start_date']}' ";


			if (isset($_GET['debug'])) {
				echo $sql_count;
			}
			$count = $wpdb->get_row($sql_count);

			if ($count->count_sent > $count->count_results) {
				$count->count_results = $count->count_sent;
			}
			echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
			echo '<td class="center">' . $count->count_results . '</td>';
			echo '<td class="center">' . $count->count_sent . '</td>';
			echo '<td class="center">' . $count->count_reqs . '</td>';
			echo '<td><a title="' . strtoupper($btype) . '" class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
			echo '</tr>';
		}
		echo '</table>';
	}
}
