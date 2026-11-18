<?php

class CI_Fax_RingCentral {

    const RC_SERVER_PRODUCTION = 'https://platform.ringcentral.com';
    const RC_SERVER_SANDBOX    = 'https://platform.devtest.ringcentral.com';
    const RC_SERVER_URL_PART   = '/restapi/v1.0';

    protected $appKey          = '';
    protected $appSecret       = '';
    protected $serverUrl       = '';
    protected $username        = '';
    protected $extension       = '';
    protected $accessToken     = '';
    protected $refreshToken    = '';
    protected $authData        = array();
    var $headers = array();

    var $use_current_blog = false;
    var $options = array();
    var $res = '';
    var $last_phone = '';
    var $error_message = '';

    function __construct() {

        $this->get_options();
        $serverUrl = isset($this->options['use_sandbox']) && $this->options['use_sandbox']? 'sandbox':'production';

        $this->appKey       = isset($this->options['client_id'])? $this->options['client_id'] : '';
        $this->appSecret    = isset($this->options['client_secret'])? $this->options['client_secret'] : '';
        $this->accessToken  = isset($this->options['access_token'])? $this->options['access_token'] : '';
        $this->refreshToken = isset($this->options['refresh_token'])? $this->options['refresh_token'] : '';

        if (strtolower($serverUrl)=='production') {
            $this->serverUrl = self::RC_SERVER_PRODUCTION;
        } else if (strtolower($serverUrl)=='sandbox') {
            $this->serverUrl = self::RC_SERVER_SANDBOX;
        } else {
            $this->serverUrl = $serverUrl;
        }
    }

    public static function getObject()
    {
        global $trci_ringcentral_obj;
        if(!is_object($trci_ringcentral_obj)){
            $trci_ringcentral_obj = new CI_Fax_RingCentral();
        }

        return $trci_ringcentral_obj;
    }

    public function authorize($username='', $extension='', $password='') {
        $this->username  = $username;
        $this->extension = $extension;
        $requestData = array(
            'grant_type' => 'password',
            'username'   => $username,
            'extension'  => $extension,
            'password'   => $password
        );
        return $this->authCall($requestData);
    }


    protected function authCall($requestData = array()) {
        $url = $this->tokenUrl();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $this->getApiKey(),
            'Content-Type: ' . 'application/x-www-form-urlencoded;charset=UTF-8'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        curl_close($ch);
        $responseData = json_decode($responseBody,true);

        $this->authData = $responseData;
        $this->inflateAuthData($responseData);
        return $responseData;
    }

    protected function inflateAuthData($authData = array()) {
        if (array_key_exists('access_token', $authData)) {
            $this->accessToken = $this->authData['access_token'];
        }
        if (array_key_exists('refresh_token', $authData)) {
            $this->refreshToken = $this->authData['refresh_token'];
        }
    }

    protected function getApiKey() {
        $apiKey = base64_encode($this->appKey . ':' . $this->appSecret);
        $apiKey = preg_replace('/[\s\t\r\n]/','',$apiKey);

        return $apiKey;
    }

    protected function tokenUrl() {
        $url = $this->serverUrl . '/restapi/oauth/token';
        return $url;
    }

    protected function prepareBody($contentType,$body) {
        if ($contentType == 'application/json') {
            if (is_array($body)) {
                $body = json_encode($body);
            }
        } elseif ($contentType =='application/x-www-form-urlencoded') {
            if (is_array($body)) {
                $body = http_build_query($body);
            }
        }
        return $body;
    }

    public function get($url, $params) {
        return $this->apiCall('GET', $url, $params, 0);
    }

    public function post($url, $params) {
        return $this->apiCall('POST', $url, $params, 0);
    }

    public function put($url, $params) {
        return $this->apiCall('PUT', $url, $params, 0);
    }

    public function delete($url, $params) {
        return $this->apiCall('DELETE', $url, $params, 0);
    }

    protected function apiCall($verb='', $url, $params, $try=0) {
        $ch = curl_init($this->inflateUrl($url));
        $this->headers = array('Authorization: Bearer ' . $this->accessToken);

        if(isset($params['files'])){
            $this->curl_custom_postfields($ch, $params['json'], $params['files']);
        }
        else if (strtoupper($verb)=='POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getBodyForParams($params));
            $this->headers[] = 'Content-Type: ' . $this->getContentTypeForParams($params);
        }else{
            $this->headers[] = 'Content-Type: ' . $this->getContentTypeForParams($params);
        }


        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers );


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);


        if ($try==0 && preg_match('/^4[0-9][0-9]$/', $httpCode)	) {
            $this->refreshToken();
            if(!$this->is_expired())
            {
                return $this->apiCall($verb, $url, $params, $try+1);
            }

        }

        $responseData = json_decode($responseBody, true);
        return $responseData;
    }

    function curl_custom_postfields(&$ch, array $json = array(), array $files = array()) {

        // invalid characters for "name" and "filename"
        static $disallow = array("\0", "\"", "\r", "\n");

        $body[] = implode("\r\n", array(
            "Content-Disposition: form-data; name=\"json\"",
            "Content-Type: application/json",
            "Content-Transfer-Encoding: 8bit",
            "",
            json_encode($json),
        ));

        // build file parameters
        foreach ($files as $k => $v) {
            switch (true) {
                case false === $v = realpath(filter_var($v)):
                case !is_file($v):
                case !is_readable($v):
                    continue 2;
            }
            $data = file_get_contents($v);
            $v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));
            $k = str_replace($disallow, "_", $k);
            $v = str_replace($disallow, "_", $v);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
                "Content-Type: application/octet-stream",
                "Content-Transfer-Encoding: binary",
                "",
                $data,
            ));
        }

        // generate safe boundary
        do {
            $boundary = "--" . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        $this->headers = array_merge($this->headers,array(
            "Expect: 100-continue",
            "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
        ));


        // set options
        return @curl_setopt_array($ch, array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => implode("\r\n", $body),
        ));
    }

    protected function getContentTypeForParams($params) {
        $type = '';
        if (array_key_exists('json', $params)) {
            $type=  'application/json';
            if(isset($params['files'])){
                $type = 'multipart/form-data';
            }
        }
        return $type;
    }

    protected function getBodyForParams($params) {
        if (array_key_exists('json', $params)) {
            $json = $this->prepareBody('application/json', $params['json']);
            if(isset($params['files'])){
                $temp = tempnam("/tmp", "json");
                file_put_contents($temp,$json);
                $json = array(
                    'json' => '@'.$temp.';type=application/json'
                );
                foreach($params['files'] as $i =>$file)
                {
                    $json[$i]='@'.$file;
                }

            }

            return $json;
        }
        return '';
    }

    protected function inflateUrl($urlIn = '') {
        $urlOut = '';
        if (strlen($urlIn)==0) {
            $urlOut = $urlIn;
        } elseif (preg_match('/^https?:\/\//',$urlIn)) {
            $urlOut = $urlIn;
        } elseif (preg_match('/^(\/)?restapi/',$urlIn, $m)) {
            if (length($m)>0 && $m[0] == '/') {
                $urlOut = $this->serverUrl . $urlIn;
            } else {
                $urlOut = $this->serverUrl . '/' . $urlIn;
            }
        } else {
            if (preg_match('/^\//',$urlIn)) {
                $urlOut = $this->serverUrl . self::RC_SERVER_URL_PART . $urlIn;
            } else {
                $urlOut = $this->serverUrl . self::RC_SERVER_URL_PART . '/' . $urlIn;
            }
        }
        return $urlOut;
    }

    public function get_options()
    {
        if(function_exists('get_blog_option') && !$this->use_current_blog){
            $this->options = get_blog_option(1,'ci_ringcentral_st',array());
        }else{
            $this->options = get_option('ci_ringcentral_st');
            if((empty($this->options['client_id']) || empty($this->options['access_token'])) && function_exists('get_blog_option'))
            {
                $this->options = get_blog_option(1,'ci_ringcentral_st',array());
            }
        }
        if(!isset($this->options['time']))
        {
	        $this->options['time'] = 0;
        }
        if(!isset($this->options['expires_in']))
        {
	        $this->options['expires_in'] = 0;
        }
    }

    public function update_options($options=null)
    {
        if($options!=null)
        {
            $this->options = wp_parse_args($options,$this->options);
        }
        if(function_exists('get_blog_option') && !$this->use_current_blog){
            update_blog_option(1,'ci_ringcentral_st',$this->options);
        }else{
            update_option('ci_ringcentral_st',$this->options);
        }
    }

    function get_redirect_uri()
    {
        $blogurl = str_replace('http:','https:',get_bloginfo('home'));
        return $blogurl.'';//.$_SERVER['REQUEST_URI'];
    }

    function get_authorize_url($redirect_url='')
    {
        if(empty($redirect_url))
        {
            $redirect_url = $this->get_redirect_uri();
        }
        $redirect_url = urlencode($redirect_url);

        return $this->serverUrl.'/restapi/oauth/authorize?response_type=code&client_id='.$this->appKey.'&redirect_uri='.$redirect_url.'';
    }

    function authorize_code($code)
    {
        $requestData = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->get_redirect_uri()
        );

        $ch = curl_init($this->tokenUrl());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $this->getApiKey(),
            'Content-Type: ' . 'application/x-www-form-urlencoded;charset=UTF-8'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $responseData = json_decode($responseBody,true);

        $this->update_tk($responseData);
        if(is_array($responseData) && isset($responseData['access_token']))
        {
            return true;
        }

        return $responseData['message'];

    }

    public function is_expired()
    {
        $expiry = $this->options['time'] + $this->options['expires_in'];

        if(time() > $expiry - 200)
        {
            return true;
        }
        return false;
    }

    public function can_refresh($delay=2000)
    {
        if($delay<60)$delay = 2000;

        if($this->options['time'] > time() - $delay)
        {
            return false;
        }
        $filelog = WP_CONTENT_DIR.'/cache/logrefreshring.txt';
        $lasttime = file_exists($filelog)? filemtime($filelog) :0;
        if($lasttime > time() - $delay)
        {
            return false;
        }

        return true;
    }

    public function refreshToken($delay=2000) {

        if(!$this->can_refresh($delay))
        {
            return -1;
        }


        if(function_exists('trpdd_log_form')){
            trpdd_log_form('ringrf',date_i18n('Y-m-d H:i:s'));
        }
        $requestData = array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken
        );

        $rs =  $this->authCall($requestData);
        $this->update_tk($rs);
        return $rs;
    }

    function update_tk($responseData)
    {
        if(is_array($responseData) && !empty($responseData['access_token']))
        {
            if(defined('WP_DO_CRON') && WP_DO_CRON)
            {
                $this->options['last_cron'] = date_i18n('Y-m-d H:i:s');
            }
            
            $this->options['last_refreshed'] = date_i18n('Y-m-d H:i:s');
            $this->options['access_token'] = $responseData['access_token'];
            $this->accessToken = $responseData['access_token'];


            $this->options['token_type'] = $responseData['token_type'];
            if(!empty($responseData['refresh_token']))
            {
                $this->options['refresh_token'] = $responseData['refresh_token'];
                $this->options['expires_in'] = $responseData['expires_in'];
                $this->options['refresh_token_expires_in'] = $responseData['refresh_token_expires_in'];

                $this->refreshToken = $responseData['refresh_token'];
            }else{
                $this->options['norefresh_token'] = time();
            }

            if(!empty($responseData['owner_id']))
            {
                $this->options['owner_id'] = $responseData['owner_id'];
            }

            $this->options['endpoint_id'] = $responseData['endpoint_id'];
            $this->options['scope'] = $responseData['scope'];
            $this->options['time'] = time();


            $this->update_options();
        }else if(!empty($responseData['error'])){
            //$this->options['access_token'] = '';
            //$this->options['refresh_token'] = '';
            //$this->update_options();
        }
    }

    function get_user($user_id = '~')
    {
        $rs = $this->get('account/'.$user_id,'');
        return $rs;
    }

    function fax($phone,$files,$text='',$sendTime='')
    {
        if(!empty($this->options['sandbox_to']))
        {
            $phone = $this->options['sandbox_to'];
        }
        $this->last_phone = $phone;

        $params = array(
            'json'     => array(
                'to'   => array( array('phoneNumber' => $phone) ),
                'faxResolution' => 'High',
                'coverPageText' => $text
            ),
            'files' => $files
        );
        if(!empty($sendTime))
        {
            $params['json']['sendTime'] = $sendTime;
        }
     
        $rs = $this->post('account/~/extension/~/fax', $params);
        $this->res = $rs;
	    if($this->res['errorCode']=='TokenInvalid')
	    {
	    	$this->error_message = 'TokenInvalid';

	    	return false;
	    }
        if(is_array($rs) && isset($rs['id'])){
            return true;
        }
        return false;
    }
}