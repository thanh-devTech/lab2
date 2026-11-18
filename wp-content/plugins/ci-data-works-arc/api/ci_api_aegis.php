<?php

include_once('ci_api_base_sftphl7.php');

class CI_Api_AEGIS extends CI_Api_Base_sFtpHl7
{

	public $order_log_dir 	= WP_CONTENT_DIR . '/log/aegis/';
	public $results_in_folder = WP_CONTENT_DIR . '/log/aegis/';
	public $results_log_dir = WP_CONTENT_DIR . '/log/aegis_results/';
	public $api_id 			= -61;
	public $log_file_slug 	= 'aegis';
	public $api_key			= 'api_aegis';


	public function __construct()
	{
		//override
		$this->enable_send_orders = false;
		$this->enable_receive_results = true;
		$this->enable_revese_result = true;

		$this->send_all_results = true;
		$this->send_result_ct = true;
		$this->send_result_range = true;
		//$this->folder_by_panel = true;
		$this->folder_by_btype = true;
		$this->send_when_meta = '_inc_pathogogy';
		$this->send_result_not_final = true;




		$this->enable_receive_orders = false;
		$this->enable_send_results = true;

		$this->sent_field = 'mo_sent';


		$this->clinic_fieldname = '';
		$this->lab_msh5 = 'Aegis';
		$this->lab_msh6 = '';
		//$this->msh5_by_reflab = true;
		$this->all_btypes = array('pcr', 'gene');

		$this->pdf_format['OBX.3.1'] = 'assen';
		$this->pdf_format['OBX.3.2'] = 'Sample Results Document';
		$this->pdf_format['OBX.5.1'] = 'application/pdf';
		$this->pdf_format['OBX.5.2'] = 'Base64';
		$this->pdf_format['OBX.5.3'] = 'content';


		$this->options = get_option('tr_aegis_st', array());



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
			'onlysendpos' => array('type' => 'checkbox', 'label' => 'Only Send POS'),
			'override_pdf' => array('type' => 'checkbox', 'label' => 'Override PDF'),
			'send_req_pdf' => array('type' => 'checkbox', 'label' => 'Send Req PDF'),
			'cron' => array('type' => 'select', 'options' => array(30 => 'each 30mins', 10 => 'each 10mins', 5 => 'each 5mins', 2 => 'each 2mins'), 'label' => 'Schedule'),
		);

		foreach ($this->all_btypes as $btype) {
			$args['type_folder_' . $btype] = array(
				'type' => 'text',
				'label' => $btype
			);
		}

		/*
		$panels = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups 
		where tg_type='gene'
		and tg_show_req = 1");
		foreach ($panels as $p) {
			$args['panel_folder_' . $p->tg_ID] = array(
				'type' => 'text',
				'label' => $p->tg_name
			);
		}*/


		return $args;
	}

	public function settingActions()
	{
		return array(
			'test_connect' => array('label' => 'Test Connect'),
			'test_send_reports' => array('label' => 'Test Send Reports'),
			'test_receive_reports' => array('label' => 'Test Receive Reports'),
			'test_upload' => array('label' => 'Test Upload'),
		);
	}

	public function setting_count()
	{
		global $wpdb;
		echo '<table style="max-width:600px" class="wp-list-table widefat striped posts   ">';
		echo '<tr><th>Type</th><th>Reqs</th><th>Results</th><th>Sent</th><th>Skipped</th><th>Resend</th><th>Received</th><th></th></tr>';
		foreach ($this->all_btypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$joins = '';
			if (!empty($this->send_when_meta)) {
				$joins .= " JOIN {$batch_tbl}_meta mw ON mw.mid = ba.mid and mw.meta_key='{$this->send_when_meta}' ";
			}


			$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.estatus is not null,1,0)) as count_results,
                            SUM(IF(o.mo_sent>0,1,0)) as count_sent,
                            SUM(IF(o.mo_status>0,1,0)) as count_received,
                            SUM(IF(o.mo_sent=2,1,0)) as count_resend,
                            SUM(IF(o.mo_sent=-1,1,0)) as count_skipped
                            from {$batch_tbl} ba
							{$joins}
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.approve_date >='2000-01-01'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' and o.mo_file_id = {$this->api_id}
                            where 1 ";
			if (!empty($this->options['start_date'])) {
				$sql_count .= " and re.approve_date >= '{$this->options['start_date']}' ";
			}



			if (isset($_GET['debug'])) {
				echo $sql_count;
			}
			$count = $wpdb->get_row($sql_count);

			if ($count->count_sent > $count->count_results) {
				$count->count_results = $count->count_sent;
			}
			echo '<tr><th>' . $btype . '</th><td>' . $count->count_reqs . '</td>';
			echo '<td>' . $count->count_results . '</td>';
			echo '<td>' . $count->count_sent . '</td>';
			echo '<td>' . $count->count_skipped . '</td>';
			echo '<td>' . $count->count_resend . '</td>';
			echo '<td>' . $count->count_received . '</td>';
			echo '<td><a title="' . strtoupper($btype) . '" class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
			echo '</tr>';
		}
		echo '</table>';
	}
}
