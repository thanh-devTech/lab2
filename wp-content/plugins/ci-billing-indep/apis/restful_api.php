<?php
/*
 * Version 2.3
 */
header('Expires: Tue, 20 Oct 1981 05:00:02 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
setcookie('no-cache',1);

class restful_api {

	public $hide_info = false;
	/**
	 * Property: $method
	 * Method được gọi, GET POST PUT hoặc DELETE
	 */
	protected $method = '';
	/**
	 * Property: $endpoint
	 * Endpoint của api
	 */
	protected $endpoint = '';
	/**
	 * Property: $params
	 * Các tham số khác sau endpoint, ví dụ /<endpoint>/<param1>/<param2>
	 */
	protected $params = array();
	/**
	 * Property: $file
	 * Lưu trữ file của PUT request
	 */
	protected $file = null;

	protected $request_public_key ='';

	protected $request_api_key = '';

	protected $pre_endpoint;

	/**
	 * Function: __construct
	 * Just a constructor
	 */
	public function __construct($pre_endpoint=''){
		$this->pre_endpoint = $pre_endpoint;

		$this->_input();
		$this->_process_api();
	}

	/**
	 * Allow CORS
	 * Thực hiện lấy các thông tin của request: endpoint, params và method
	 */
	private function _input(){
		header("Access-Control-Allow-Orgin: *");
		header("Access-Control-Allow-Methods: *");

		$this->request_public_key = isset($_SERVER['HTTP_PUB_KEY'])? $_SERVER['HTTP_PUB_KEY']:'';
		$this->request_api_key = $_SERVER['HTTP_API_KEY'];
		if(empty($this->request_api_key))
		{
			$this->request_api_key = $_REQUEST['key'];
		}
		if(empty($this->request_api_key))
		{
			$this->request_api_key = str_ireplace(array('Basic ','Bearer '),'',$_SERVER['HTTP_AUTHORIZATION']);
		}

		if(empty($_SERVER['PATH_INFO']))
		{
			$_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];
		}
		$query = '';
		$pathurl = trim($_SERVER['PATH_INFO'],'/');
		if(strpos($pathurl,'?')>0)
		{
			list($pathurl,$query) = explode('?',$pathurl,2);
		}

		$this->params   = explode('/', $pathurl);

		$this->endpoint = '';

		foreach($this->params as $i => $param)
		{
			if($param=='restapi'){
				unset($this->params[$i]);
				continue;
			}else if($param=='api'){
				unset($this->params[$i]);
				continue;
			}else if(!empty($this->pre_endpoint) && $param === $this->pre_endpoint)
			{
				unset($this->params[$i]);
				continue;
			}
			else if(empty($this->endpoint))
			{
				unset($this->params[$i]);
				$this->endpoint = 'request_'.$param;
			}
		}

		if(!empty($query))
		{
			parse_str($query, $output);
			$this->params = array_merge($this->params, $output);
		}

		// Lấy method của request
		$method         = $_SERVER['REQUEST_METHOD'];
		$allow_method   = array('GET', 'POST', 'PUT', 'DELETE');

		if (in_array($method, $allow_method)){
			$this->method = $method;
		}

		// Nhân thêm dữ liệu tương ứng theo từng loại method
		switch ($this->method) {
			case 'POST':
				if(is_array($_POST) && count($_POST)==1 ){
					foreach($_POST as $kp =>$vl)
					{
						if(empty($vl))
						{
							$_POST = array();
						}
					}
				}
				if(empty($_POST))
				{
					$input = file_get_contents("php://input");
					if(!empty($input))
					{
						$_POST = json_decode($input,true);
					}
				}
				if(!is_array($_POST))
				{
					$_POST = array();
				}
				$this->params = array_merge($this->params,$_POST);
				break;

			case 'GET':
				// Không cần nhận, bởi params đã được lấy từ url
				break;

			case 'PUT':
				$this->file    = file_get_contents("php://input");
				if(!empty($this->file))
				{
					$this->params = json_decode($this->file,true);
				}
				break;

			case 'DELETE':
				// Không cần nhận, bởi params đã được lấy từ url
				break;

			default:
				$this->response(500, "Invalid Method");
				break;
		}
	}

	/**
	 * Thực hiện xử lý request
	 */
	private function _process_api(){
		$this->check_key();

		if (method_exists($this, $this->endpoint) && is_callable(array($this,$this->endpoint))){
			try{
				$this->{$this->endpoint}();
			}catch (Exception $e){
				$this->response(500, "Unknown endpoint");
				exit;
			}
		}
		else {
			$this->response(500, "Unknown endpoint");
		}
	}

	protected function check_key()
	{
		return true;
	}



	/**
	 * Trả dữ liệu về client
	 * @param: $status_code: mã http trả về
	 * @param: $data: dữ liệu trả về
	 */
	protected function response($status_code, $data = NULL){
		ob_clean();
		header($this->_build_http_header_string($status_code));
		header("Content-Type: application/json");
		echo json_encode($data);
		die();
	}

	/**
	 * Tạo chuỗi http header
	 * @param: $status_code: mã http
	 * @return: Chuỗi http header, ví dụ: HTTP/1.1 404 Not Found
	 */
	private function _build_http_header_string($status_code){
		$status = array(
			200 => 'OK',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			500 => 'Internal Server Error'
		);
		return "HTTP/1.1 " . $status_code . " " . $status[$status_code];
	}

	function request_info()
	{
		if($this->hide_info)
		{
			exit;
		}
		$class = new ReflectionClass(get_class($this));
		$methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
		$key = !empty($this->request_api_key)? $this->request_api_key : 'key here';
		if($key=='viewinfo'){
			$key = 'key here';
		}
		echo '<p>send "api-key:'.$key.'" in header</p>';
		echo '<h3>Methods</h3>';
		foreach($methods as $method)
		{
			if($method->class=='restful_api' || $method->name=='__construct' || stripos($method->name,'request_')!==0){
				continue;
			}
			$methodinfo = new ReflectionMethod($method->class, $method->name);
			$doc = $methodinfo->getDocComment();
			$doc = str_replace(array("/**\n",'/**',"*\t","*/",'* ',"*\n"),'',$doc);
			$doc = str_replace("\n","<br>\n",$doc);
			$doc = str_replace(" ",'&nbsp;',$doc);

			echo ' <div style="font-weight:bold;font-size:14px"> /'.$this->pre_endpoint.'/'.str_replace('request_','',$method->name).'</div>';
			echo '<div style="padding-bottom:20px;padding-left:20px">';
			echo $doc;
			echo '</div>';

		}
		exit;
	}

}