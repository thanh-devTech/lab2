<?php

class CI_Api_Coriell 
{

	var $product_url = 'https://gateway.coriell-services.com';
	var $dev_url 	= 'https://gateway-dev.coriell-services.com';

	public $log 	= '';
	public $is_Test = false;
	public $debug 	= false;

	var $api_id 	= -36;
	var $options = array();

	public function __construct() {
		$this->get_options();
		$this->is_Test = !isset($this->options['is_product']) || !$this->options['is_product'];
	}

	public function get_options()
	{
		$this->options = get_option('tr_hl7_api_coriell_st');
	}

	public function set_option($k , $vl){
		$this->options[$k] = $vl;
		update_option('tr_hl7_api_coriell_st',$this->options);
	}

	public function cron(){
		
	}


	protected function apiCall( $urlpath, $args=array()) {
		$urlpath = str_replace(' ','%20',$urlpath);
		
		$url = $this->is_Test? $this->dev_url : $this->product_url;
		$url .= $urlpath;
		
		if(!isset($args['headers']))
		{
			$args['headers'] = array();
		}
		if(!empty($this->options['access_token'])){
			$args['headers']['Authorization'] = 'Bearer '.$this->options['access_token'];
		}
		
		
		//$url = 'https://api.webhue.net/raw.php';

		$rs = wp_remote_post($url,$args);
		var_dump($rs['body']);
		if(!is_wp_error($rs))
		{
			$rs = json_decode($rs['body'],true);
		}
		
		return $rs;
	}


	public function auth()
	{
		$args = array(
			"grant_type" => "password",
			 "username" => $this->options['user'], 
			 "password" => $this->options['password'], 
			 "client_id" => ""
		);
		$rs = $this->apiCall('/auth/token/wrap', array(

			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body' => json_encode($args),
			'data_format' => 'body',
		));
		if(isset($rs['access_token'])){
			$this->set_option('access_token',$rs['access_token']);
		}
		var_dump($rs);
	}

	public function drugs($term)
	{
		$rs = $this->apiCall('/drugs/search?q='.$term);
		
		var_dump($rs);
	}

	public function pgxinfo()
	{
		$rs = $this->apiCall('/drugs/pgxinfo');
		
		var_dump($rs);
	}

	public function pgx_upload($report)
	{
		$args = array(
			"sample" => array(
				'sampleid' => $report['assen'],
				'patient' => array(
					'sn' => $report['pa_first'],
					'givenName' => $report['pa_last'],
					'sex' => $report['pa_gender']=='F'?'FEMALE':'MALE',
					'dob' => $report['pa_dob'],
					'regimen' => array(),
				),
				'metadata' => array(
					'labTechnology' => 'PGx120',
					'datapolicy' => 'PGx120',
					'clinicianname' => isset($report['requesting_practice'])? $report['requesting_practice'] : $report['clinic'],
					'practicename' => isset($report['requesting_physician'])? $report['requesting_physician'] :$report['doctor']
				),
				'diplotypes' => array(),
				'geneCnvs' => array()
			)
		);
		$rs = $this->apiCall('/sample/pgx/upload', array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body' => json_encode($args),
			'data_format' => 'body',
		));
		
		var_dump($rs);
	}

}