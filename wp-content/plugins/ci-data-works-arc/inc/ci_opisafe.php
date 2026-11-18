<?php
class CI_Opisafe{

    var $serverUrl = 'https://api.opisafe.com/';

    var $key;
    var $secret;
    var $lab_id;
    var $last_query;
    var $last_body;
    var $debug;

    public function __construct($key,$secret,$lab_id)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->lab_id = $lab_id;
    }

    public function ping()
    {
        return $this->get('/ping');
    }

    public function getlab(){
        return $this->get('/labs/'.$this->lab_id);
    }

    public function patients()
    {
        return $this->get('/labs/'.$this->lab_id.'/patients');
    }

    public function providers()
    {
        return $this->get('/labs/'.$this->lab_id.'/providers');
    }

    public function get_patient($patient_id)
    {
        return $this->get('/labs/'.$this->lab_id.'/patients/'.$patient_id);
    }

    public function search_patients($npi,$patient_id,$dob='',$last_name='')
    {
        $params = array();
        $params['npi'] = $npi;
        if(!empty($patient_id))
        {
            $params['patient_id'] = $patient_id;
        }
        $params['dob'] = $dob;
        $params['last_name'] = $last_name;
        return $this->get('/labs/'.$this->lab_id.'/patient-search',$params);
    }

    public function put_report($patient_id,$data,$file,$report_date)
    {
        $params = array();
        $params['type'] = 'file';
        $params['file'] = '@'.realpath($file).';filename='.basename($file);
        $params['hl7_file'] = '';
        $params['report_date'] = $report_date;
        $params['data_format'] = 'custom';
        $params['data'] = json_encode($data);

        return $this->post('/labs/'.$this->lab_id.'/patients/'.$patient_id.'/reports',$params);
    }

    public function put_report_providers($npi,$dob,$last_name,$first_name,$ssn,$data,$file,$report_date)
    {
        if (function_exists('curl_file_create')) { // php 5.5+
            $cFile = curl_file_create($file);
        } else { //
            $cFile = '@' . realpath($file).';filename='.basename($file);
        }

        $params = array();
        $params['type'] = 'file';
        $params['file'] = $cFile;
        $params['hl7_file'] = '';
        $params['report_date'] = $report_date;
        $params['dob'] = $dob;
        $params['last_name'] = $last_name;
        $params['first_name'] = $first_name;
        $params['ssn'] = $ssn;
        $params['data_format'] = 'custom';
        $params['data'] = json_encode($data);



        return $this->post('/labs/'.$this->lab_id.'/providers/'.$npi.'/reports',$params);
    }


    public function get($url,$params=array()) {
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

    function get_sig($endpoint)
    {
        return hash_hmac('sha256',$this->key.$endpoint,$this->secret);
    }

    protected function apiCall($verb='', $endpoint, $params, $try=0) {
        $url = $this->serverUrl.$endpoint;
       // $url = 'http://ngoctrinh.net/t.php';
        if(!empty($params) && $verb=='GET'){
            $url  = add_query_arg($params,$url);
        }
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        $this->headers = array(
            'api-key: ' . $this->key,
            'api-sig: ' . $this->get_sig($endpoint)
            );

        $this->last_query['params'] = $params;
        $this->last_query['endpoint'] = $endpoint;

        if (strtoupper($verb)=='POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers );


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->last_body = $responseBody;
        $responseData = @json_decode($responseBody, true);
        if($this->debug)
        {
            $this->debug($endpoint,$params,$responseBody,$responseData);
        }
        return $responseData;
    }

    public function debug($endpoint,$params,$responseBody,$responseData)
    {
        $log = 'Endpoint: '.$endpoint."<br>\n";
        $log .= 'Params: '.json_encode($params)."<br>\n";
        $log .= 'responseBody: '.$responseBody."<br>\n";
        $log .= 'responseData: '.json_encode($responseData)."<br>\n";
        echo $log;
    }

}