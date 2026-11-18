<?php
class CI_Box_Content_Api
{
    var $client_id;
    var $client_secret;
    var $api_url = 'https://api.box.com';
    var $upload_url = 'https://upload.box.com/api';
    var $api_version= '2.0';
    var $token;
    var $refresh_token;
    var $token_type='bearer';
    var $debug = false;

    public function __construct($client_id,$client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    function setToken($token,$refresh_token='',$token_type='bearer')
    {
        $this->token = $token;
        if(!empty($token_type))
        {
            $this->token_type = $token_type;
        }
        if(!empty($refresh_token))
        {
            $this->refresh_token = $refresh_token;
        }
    }

    /**
     * Parses the response in a more friendly format.
     *
     * @param $ch
     * @param string $response
     * @return object
     */
    private function parseResponse($ch, $response = '') {
        $headers = $this->parseHeaders($ch);
        if ($decoded = json_decode($response,true)) {
            $body = $decoded;
        }
        else {
            $body = $response;
        }
        return (object) array('headers' => $headers, 'response' => $body);
    }

    /**
     * Parse header information into something usable.
     * Currently, we should only have to look at the status code.
     *
     * @param $ch
     * @return stdClass
     */
    private function parseHeaders($ch) {
        $headers = new stdClass();
        $headers->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $headers;
    }

    private function httpRequest($curl_params = array(),$test=false) {

        $ch = curl_init();

        // Return the result of the curl_exec().
        $curl_params[CURLOPT_RETURNTRANSFER] = TRUE;
        $curl_params[CURLOPT_FOLLOWLOCATION] = TRUE;

        // Need to set the authorization header.
        if(!empty($this->token))
        {
            $curl_params[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $this->token;
        }

        // Set other CURL_OPT params.
        foreach ($curl_params as $curl_opt => $val) {
            @curl_setopt($ch, $curl_opt, $val);
        }

        // Get the response.
        $response = curl_exec($ch);

        // Ensure our request didn't have errors.
        if ($error = curl_error($ch)) {
            throw new Exception($error);
        }

        // Close and return the curl response.
        $result = $this->parseResponse($ch, $response);

        curl_close($ch);


        if($test)
        {
            return $result;
        }

        switch($result->headers->code)
        {

            case 204:
                $this->exception('no_content');
                break;
            case 400:
                $rs = $this->refresh();
                if($rs)
                {
                    return $this->httpRequest($curl_params,true);
                }
                $this->exception('bad_request');
                break;
            case 401:
                $rs = $this->refresh();
                if($rs)
                {
                    return $this->httpRequest($curl_params,true);
                }
                $this->exception('unauthorized');
                break;
            case 403:
                $this->exception('forbidden');
                break;
            case 404:
                $this->exception('not_found');
                break;
            case 405:
                $this->exception('method_not_allowed');
                break;
            case 412:
                $this->exception('precondition_failed');
                break;
            case 429:
                $this->exception('too_many_requests');
                break;
            case 500:
                $this->exception('internal_server_error');
                break;
            case 503:
                $this->exception('unavailable');
                break;
        }

        return $result;
    }

    public function get_authorize_url($redirect_url='')
    {
        if(empty($redirect_url))
        {
            $blogurl = str_replace('http:','https:',get_bloginfo('home'));
            $redirect_url = $blogurl.$_SERVER['REQUEST_URI'];
        }
        $redirect_url = urlencode($redirect_url);
        return 'https://app.box.com/api/oauth2/authorize?response_type=code&client_id='.$this->client_id.'&redirect_uri='.$redirect_url.'&state=security_token%3DKnhMJatFipTAnM0nHlZA';
    }

    public function get_folder_info($folder_id,$key=''){
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders/'.$folder_id;


        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=409)
        {
            $this->log('get_folder_info: '.$folder_id.$result->headers->code);
            $this->log($result);
            $this->exception('folder info: '.$folder_id.$result->headers->code);
            return false;
        }
        return $result;
    }

    public function get_folder_items($folder_id)
    {
        $items = array();
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders/'.$folder_id.'/items';


        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=409)
        {
            $this->log('get_folder_info: '.$folder_id.$result->headers->code);
            $this->log($result);
            $this->exception('folder info: '.$folder_id.$result->headers->code);

        }
        return $result;
    }

    public function copy_folder($folder_s_id,$folder_d_id)
    {
        $post = array(
            'parent' => array('id'=>$folder_d_id)
        );
        $post = json_encode($post);
        $curl_params[CURLOPT_POSTFIELDS] = $post;
        $curl_params[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $curl_params[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($post);

        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders/'.$folder_s_id.'/copy';


        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=409)
        {
            //$this->exception('copy folder: '.$folder_s_id.' to '.$folder_d_id.$result->headers->code);
        }
        return $result;
    }

    public function authorize($code,$refresh_token='')
    {
        if($this->debug)
        {
            echo '<br>try authorize<br>';
        }
        $this->token = '';
        $post = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
        );
        if(!empty($code))
        {
            $post['code'] = $code;
        }
        if(!empty($refresh_token))
        {
            $post['refresh_token'] = $refresh_token;
            $post['grant_type'] = 'refresh_token';
        }
        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_URL] = $this->api_url . '/oauth2/token';
        $curl_params[CURLOPT_POSTFIELDS] = $post;


        $result = $this->httpRequest($curl_params, true);

        if($this->debug)
        {
            echo "<br>post:";
            var_dump($post);
            echo '<br>authorize result: <br>';
            var_dump($result);
            var_dump($result->headers->code);
            echo '<br>';
        }

        if($result->headers->code==200){
            if($this->debug)
            {
                echo '<br>access_token: ';
                var_dump($result->response['access_token']);
            }
            $this->token = $result->response['access_token'];
            $this->refresh_token = $result->response['refresh_token'];
        }
        return $result;
    }

    public function check_folder_name($name,$parient)
    {
        $folder_id = 0;
        $rs = $this->get_folder_items($parient);
        if(is_array($rs->response['entries'])){
            foreach($rs->response['entries'] as $r)
            {
                if($r['type']=='folder' && $r['name']==$name)
                {
                    $folder_id = $r['id'];
                    break;
                }
            }
        }

        return $folder_id;
    }

    public function create_folder($name,$parent=0)
    {
        $folder_id = $this->check_folder_name($name,$parent);
        if($folder_id>0)
        {
            return $folder_id;
        }



        $post = array(
            'name' => $name,
            'parent' => array('id'=>$parent)
        );
        $post = json_encode($post);

        $curl_params = array();
        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders';
        $curl_params[CURLOPT_POSTFIELDS] = $post;

        $rs = $this->httpRequest($curl_params);

        $folder_id = '';

        if(isset($rs->response['id']))
        {
            $folder_id = $rs->response['id'];
        }
        else if(isset($rs->response['context_info']['conflicts']['0']['id']))
        {
            $folder_id = $rs->response['context_info']['conflicts']['0']['id'];
        }

        if(!$folder_id)
        {
            $this->log('create_folder: '.$name.' parent#'.$parent);
            $this->log($rs);
           // $this->exception('create_folder: '.$name.' parent#'.$parent);
            return false;
        }
        return $folder_id;
    }

    public function delete_folder($folder_id)
    {
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders/'.$folder_id.'?recursive=true';

        $result = $this->httpRequest($curl_params);
        return $result;
    }

    public function collaborations_folder($folder_id)
    {
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/folders/'.$folder_id.'/collaborations';

        $result = $this->httpRequest($curl_params);
        return $result;
    }

    public function upload_file($file,$name='',$parent=0){
        if(empty($name))
        {
            $name = basename($file);
        }

        $attributes = array(
            'name' => $name,
            'parent' => array('id'=>$parent)
        );
        $post = array(
            'attributes' => json_encode($attributes),
            'file'=>'@'.realpath($file)
        );

        $curl_params = array();

        if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
            $post['file'] = new CURLFile($file);
            $curl_params[CURLOPT_SAFE_UPLOAD] =  true;
        }else{
            $curl_params[CURLOPT_SAFE_UPLOAD] =  false;
        }


        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_URL] = $this->upload_url.'/'.$this->api_version.'/files/content';
        //$curl_params[CURLOPT_URL] = 'http://ngoctrinh.net/t.php';
        $curl_params[CURLOPT_POSTFIELDS] = $post;

        $result = $this->httpRequest($curl_params);

        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=409)
        {
            $this->log('upload_file: '.$file.' '.$result->headers->code);
            $this->log($result);
            $this->log($post);
            $this->exception('upload_file:'.json_encode($result),'upload_file_'.$result->headers->code);
            return false;
        }
        else if($result->headers->code==409)
        {
            $this->log('409: '.$file);
            $this->log($result);
        }
        return $result;
    }

    public function update_file($file,$file_id,$etag='')
    {
        if(empty($file_id))
        {
            $this->exception('File ID empty');
        }
        $post = array(
            'file'=>'@'.$file
        );
        $curl_params = array();

        if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
            $post['file'] = new CURLFile($file);
            $curl_params[CURLOPT_SAFE_UPLOAD] =  true;
        }else{
            $curl_params[CURLOPT_SAFE_UPLOAD] =  false;
        }


        $curl_params = array();
        $curl_params[CURLOPT_SAFE_UPLOAD] =  false;
        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_HTTPHEADER][] = "If-Match: ".$etag;
        $curl_params[CURLOPT_URL] = $this->upload_url . '/'. $this->api_version . '/files/'.$file_id.'/content';
        $curl_params[CURLOPT_POSTFIELDS] = $post;

        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=404)
        {
            $this->log($result);
            $this->exception('update_file');
        }
        return $result;
    }

    public function copy_file($file_s_id,$folder_id)
    {
        $post = array(
            'parent' => array('id'=>$folder_id)
        );
        $post = json_encode($post);
        $curl_params[CURLOPT_POSTFIELDS] = $post;
        $curl_params[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $curl_params[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($post);

        $curl_params[CURLOPT_CUSTOMREQUEST] = 'POST';
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/files/'.$file_s_id.'/copy';

        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=409)
        {
            $this->log($result);
            $this->exception('copy file: '.$file_s_id.' to '.$folder_id.$result->headers->code);
        }

        return $result;
    }

    public function delete_file($file_id,$etag='')
    {
        $curl_params[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        $curl_params[CURLOPT_HTTPHEADER][] = "If-Match: ".$etag;
        $curl_params[CURLOPT_URL] = $this->api_url . '/'. $this->api_version . '/files/'.$file_id;

        $result = $this->httpRequest($curl_params);
        if($result->headers->code!=200 && $result->headers->code!=201 && $result->headers->code!=204)
        {
            $this->log($result);
            $this->exception('delete fail: '.$file_id.$result->headers->code);
        }
        else if($result->headers->code==404)
        {
            $this->log('404: '.$file_id);
        }
        return $result;
    }

    public function get_user($user_id = 'me')
    {
        $curl_params[CURLOPT_URL] = $this->api_url. '/'.$this->api_version.'/users/'.$user_id;

        $result = $this->httpRequest($curl_params);
        return $result;
    }

    public function refresh()
    {
        if($this->debug)
        {
            echo '<br>try refresh<br>';
        }
        if(!empty($this->refresh_token))
        {

            $result = $this->authorize('',$this->refresh_token);

            if($result->headers->code==200){
                return true;
            }
        }
        return false;
    }

    function log($log)
    {
        if($this->debug)
        {
            var_dump($log);
        }
        return $log;
    }

    function exception($txt)
    {
        $this->log($txt);
        throw new Exception($txt);
    }
}