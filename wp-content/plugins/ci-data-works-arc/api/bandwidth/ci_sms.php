<?php
require_once ('vendor/autoload.php');

class CI_Sms{
	protected $client;
	protected  $settings;
	protected $app_tag = '';

	public function __construct($settings) {
		$this->settings = $settings;

		try {
			$config = new BandwidthLib\Configuration(
				array(
					'messagingBasicAuthUserName' => $settings['username'],
					'messagingBasicAuthPassword' => $settings['password'],
				)
			);

			$this->client = new BandwidthLib\BandwidthClient( $config );
		}catch (Exception $e){

		}
	}

	public function send_sms($phone,$msg,$media='',$targetname='',$sendtype='cl',$assen='')
	{
		global $wpdb;

		if(isset($_GET['tr_debug2']) ){
			wp_mail('pntrinh29@gmail.com','send sms',$msg.' phone: '.$phone);
			return true;
		}

		$phone = trim($phone);
		$phone = str_replace(array('-',' ','(',')','.'),'',$phone);
		if(strpos($phone,'+1')!==0)
		{
			$phone = '+1'.$phone;
		}


		$messagingClient = $this->client->getMessaging()->getClient();

		$body = new BandwidthLib\Messaging\Models\MessageRequest();
		$body->from = $this->settings['send_number'];
		$body->to = array($phone);
		$body->text = $msg;
		$body->tag = $this->app_tag;
		if(!empty($media))
		{
			$body->media = $media;
		}
		$body->applicationId = $this->settings['app_id'];
		$messagingAccountId= $this->settings['account_id'];
		try {
			$result = $messagingClient->createMessage( $messagingAccountId, $body );

			//adding count
			$logdata = array(
				'phone' => $phone,
				'patient_name' => $targetname,
				'assen' => $assen,
				'sendtype' => $sendtype,
				'message' => $msg,
				'created' => date_i18n('Y-m-d H:i:s')
			);
			$wpdb->insert('tr_log_sms',$logdata);
			if(!empty($wpdb->last_error) && isset($_GET['debug']))
			{
				echo $wpdb->last_error;
			}
			return true;
		}catch (Exception $e)
		{
			$logdata = array(
				'phone' => $phone,
				'patient_name' => $targetname,
				'assen' => $assen,
				'sendtype' => 'cf',
				'message' => $msg,
				'created' => date_i18n('Y-m-d H:i:s')
			);
			$wpdb->insert('tr_log_sms',$logdata);
			$this->last_error = $e->getMessage();
		}
		return false;
	}


}