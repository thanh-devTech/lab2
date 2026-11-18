<?php
class CI_Json_Client
{

	public $url = '';

	public function __construct($url)
	{
		$this->url = $url;
	}

	public function test_key($key = '')
	{
		$obj = new stdClass();
		$obj->status = 'OK';
		return $obj;
	}

	public function put_req($json, $key)
	{
		$rs = wp_remote_request($this->url, array(
			'method' => 'PUT',
			'headers'   => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'api-key' => $key,
			),
			'body' => $json
		));

		if (is_wp_error($rs)) {
			$obj = new stdClass();
			$obj->status = 'ERROR';
			return $obj;
		}
		return json_decode($rs['body']);
	}

	public function add_clinic($key, $xml)
	{

	}

	public function get_req_by_assen()
	{

	}

	public function get_orders()
	{

	}

	public function put_result()
	{

	}

	public function get_pdf_results()
	{

	}

	public function get_results()
	{

	}

	public function get_results_data()
	{

	}

	public function list_clinics()
	{
		
	}
}
