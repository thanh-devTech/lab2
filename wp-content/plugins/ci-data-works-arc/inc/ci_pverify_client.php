<?php
class CI_PVerify_Client extends CI_Data_Work_Xml{

    var $wsdl ='https://www.pverify.net/EBSOAP/soapservice.asmx?WSDL';
    var $wsdl_test='https://www.pverify.net/SoapSamples/SoapSamples.asmx?WSDL';
    var $UserID = '';
    var $Password = '';

    var $client;
    var $debug=false;
    var $settings;
    var $rsuid = false;
    var $rs = false;

    public function CI_PVerify_Client($debug=false,$blog_id=0)
    {
        if($blog_id>0 && function_exists('get_blog_option'))
        {
            $this->settings = get_blog_option($blog_id,'ci_pdf_data');
        }
	    else{
            $this->settings = get_option('ci_pdf_data');
        }

        if(empty($this->settings['Pverify_UserID']) && function_exists('get_blog_option'))
        {
            $this->settings = get_blog_option(1,'ci_data_work_net');
        }

        $this->UserID = $this->settings['Pverify_UserID'];
        $this->Password = $this->settings['Pverify_Password'];

        $wsdl = ($debug)? $this->wsdl_test : $this->wsdl;
        $this->client = new SoapClient( $wsdl ,
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'exceptions' => 1,
                'trace' => 1,
                'soap_version' => SOAP_1_2,
                'cache_wsdl' => WSDL_CACHE_NONE
                )
        );

    }

    public function VerifyEligibility($args)
    {
        if(empty($this->UserID) || empty($this->Password)){
            return false;
        }
        $default = array(
            'Version' => '3.0.1',
            'UserID' => $this->UserID,
            'Password' => $this->Password,

            'PayerID' => '',
            'ProviderNPI' => '',
            'ProviderLastName' => '',
            'ProviderFirstName' => '',
            'ProviderPIN' => '',
            'ProviderTaxanomy' => '',
            'VerificationType' => '',
            'SubLastName' => '',
            'SubFirstName' => '',
            'SubMemberID' => '',
            'SubSSN' => '',
            'SubscriberGroupNo' => '',
            'DependentLastName' => '',
            'DependentFirstName' => '',
            'DependentRelationship' => '',
            'PatientGender' => '',
            'PatientDOB' => '',
            'IssueDate' => '',
            'DOSFrom' => '',
            'DOSTo' => '',
            'BatchName' => '',
            'ServiceCodes' => '',
            'Specialty' => '',
            'Location' => '',
            'ApptDate' => '',
            'ApptTime' => '',
            'Notes' => '',
        );
        $args = wp_parse_args($args,$default);
        try{
            $rs = $this->client->VerifyEligibility($args );
        }
        catch(Exception $e)
        {
            if(isset($_REQUEST['debug2'])){
                var_dump($e);
            }
        }
        return $rs;
    }

    public function GetEligibilitySummaryFULL($uid,$ServiceName='')
    {
        if(empty($this->UserID) || empty($this->Password)){
            return false;
        }

        $args = array(
            'Version' => '3.0.1',
            'UserID' => $this->UserID,
            'Password' => $this->Password,
            'UniqueID' => $uid,
            'ServiceName' => $ServiceName,
        );
        try{
            $rs = $this->client->GetEligibilitySummaryFULL($args );
        }
        catch(Exception $e)
        {
            if(isset($_REQUEST['debug2'])){
                var_dump($e);
            }
        }
        return $rs;
    }

    public function GetDemography($uid)
    {
        if(empty($this->UserID) || empty($this->Password)){
            return false;
        }

	    $args = array(
            'Version' => '3.0.1',
            'UserID' => $this->UserID,
            'Password' => $this->Password,
            'UniqueID' => $uid,
        );
        try{
            $rs = $this->client->GetDemography($args );
        }
        catch(Exception $e)
        {
            if(isset($_REQUEST['debug2'])){
                var_dump($e);
            }
        }
        return $rs;
    }

    public function GetDemographics($uid)
    {
        if(empty($this->UserID) || empty($this->Password)){
            return false;
        }

	    $args = array(
            'Version' => '3.0.1',
            'UserID' => $this->UserID,
            'Password' => $this->Password,
            'UniqueID' => $uid,
        );
        try{
            $rs = $this->client->GetDemographics($args );
        }
        catch(Exception $e)
        {
            if(isset($_REQUEST['debug2'])){
                var_dump($e);
            }
        }
        return $rs;
    }

    public function getPayerList($type)
    {
        try{
            $rs = $this->client->getPayerList($type );
        }
        catch(Exception $e)
        {

        }
        $this->add_payer_ids($rs);
        return $rs;
    }

    public function add_payer_ids($rs)
    {
        global $wpdb;

        if(isset($_GET['show'])){
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private",false);
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"list.csv\";" );
            header("Content-Transfer-Encoding: binary");

        }

        foreach($rs->getPayerListResult->Payers->Payer as $payer)
        {
            $payername = $payer->PayerName;
            $PayerID = $payer->PayerID;

            if(isset($_GET['show'])){
                echo '"'.$payername.'"';
                echo ',';
                echo $PayerID;
                echo "\n";
                continue;
            }
            if(!isset($_GET['insert'])){
                continue;
            }

            $pa_id = $wpdb->get_var("select payer_ID from ci_payer_list WHERE payer_name like '{$payername}%'");
            if($pa_id==0)
            {
                $arr = explode(' ',$payername);
                $payername2 = $arr[1].' '.$arr[0];
                var_dump($payername2);
                $pa_id = $wpdb->get_var("select payer_ID from ci_payer_list WHERE payer_name like '{$payername2}%'");
            }
            if($pa_id>0)
            {
                $wpdb->update('ci_payer_list',array('payer_pverify_ID'=>$PayerID),array('payer_ID'=>$pa_id));
            }else{
                $wpdb->insert('ci_payer_list',array('payer_pverify_ID'=>$PayerID,'payer_name'=>$payername,'payer_pverify_insert'=>1));
            }


        }
        if(isset($_GET['show'])){
            exit;
        }
    }

    public function verify_patient($args,$servce = 'Diagnostic Medical')
    {
        $rs = $this->VerifyEligibility($args);
        if($rs===null)
        {
            @sleep(1);
            $rs = $this->VerifyEligibility($args);
        }
        $this->rsuid = $rs;
        if($rs && isset($rs->VerifyEligibilityResult->UniqueID))
        {
            $uid = $rs->VerifyEligibilityResult->UniqueID;
            if($rs->VerifyEligibilityResult->StatusCode!=11 && $rs->VerifyEligibilityResult->StatusCode!=10)
            {
                $uid = 0;
                $this->rs = false;
            }
            if(!empty($uid))
            {
                @sleep(1);
                $rs = $this->GetEligibilitySummaryFULL($uid,$servce);
                if($rs===null)
                {
                    @sleep(1);
                    $rs = $this->GetEligibilitySummaryFULL($uid,$servce);
                }
                $this->rs = $rs;
                if($rs){

                    if(isset($rs->GetEligibilitySummaryFULLResult->Demographics->Subscriber))
                    {
                        return $rs->GetEligibilitySummaryFULLResult->Demographics->Subscriber;
                    }
                    else if( isset($rs->GetEligibilitySummaryFULLResult->Demographics->Dependent) )
                    {
                        return $rs->GetEligibilitySummaryFULLResult->Demographics->Dependent;
                    }
                }
            }else{
                $this->rs = $rs->VerifyEligibilityResult->Description;
            }
        }
        return false;
    }


}