<?php
class CI_Data_OpiSafe extends CI_Opisafe
{
    public function send_report2($re_id=0)
    {
        global $wpdb;

        $result_dir = WP_CONTENT_DIR.'/results/';

        $report = tr_pdd_get_report_by_id($re_id);
        list($path,$file) = trpdd_get_path_result_pdf($report);
        $file_path = $result_dir.$path.'/'.$file;
        if (!file_exists($file_path))
        {
            trpdd_export_results_pdf($re_id);
        }

        $pa_ID = $report['pa_ID'];
        $doc_ID = $report['doc_ID'];

        $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pa_ID} ", ARRAY_A);
        $doctor = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_ID} ", ARRAY_A);
        $npi = $doctor['doc_npi'];
        $dob = date('m/d/Y',strtotime($patient['pa_dob']));
        $pa = null;
        
        if(empty($patient['pa_opi_id'])){


            if(!empty($npi))
            {
                $rs = $this->search_patients($npi,null,$dob,$patient['pa_last']);
                if(isset($rs['patient'])){
                    $pa = $rs['patient'];
                }
            }

            if(!$pa){
                $rs = $this->patients();
                if(!isset($rs['patients']))
                {
                    return 'no_patients';
                }
                foreach($rs['patients'] as $p)
                {
                    if($dob==$p['dob'] && strtolower($patient['pa_last']) == strtolower($p['last_name']) && strtolower($patient['pa_first']) == strtolower($p['first_name'])){

                        $pa = $p;

                        break;
                    }
                }
            }

            if($pa)
            {
                $patient['pa_opi_id'] = $pa['id'];
                $wpdb->update('ci_patients',array(
                    'pa_opi_id'=>$pa['id'],
                    'pa_opi_npi'=>$pa['npi'],
                    'pa_opi_key'=>$pa['patient_id']
                ),
                    array('pa_ID'=>$pa_ID));
            }
        }


        if(!empty($patient['pa_opi_id']))
        {
            $report_created = $report['created_date'];
            $keys = array(
                'patient_name',
                'requesting_physician',
                'physician_phone',
                'requesting_practice',
                'assen',
                'lab',
                'patient_dob',
                'acquisition_date',
                'sample_type',
                'doc',
                'spec_received',
                'medications',
                'panel_tests_requested',
                'dagnostic_codes1'

            );
            $data = array();
            foreach($keys as $k)
            {
                $data[$k] = $report[$k];
            }


            $rs = $this->put_report($patient['pa_opi_id'],$data,$file_path,$report_created);

            if($rs && !empty($rs['id'])){
                $wpdb->update('ci_reports',array('opi_id'=>$rs['id']),array('id'=>$re_id));
                return $rs['id'];
            }
        }
        return null;

    }

    public function send_report($re_id=0)
    {
        global $wpdb;

        $return = array('status'=>'','id'=>'');

        $result_dir = WP_CONTENT_DIR.'/results/';

        $report = tr_pdd_get_report_by_id($re_id);
        list($path,$file) = trpdd_get_path_result_pdf($report);
        $file_path = $result_dir.$path.'/'.$file;
        if (!file_exists($file_path))
        {
            trpdd_export_results_pdf($re_id);
        }
        if (!file_exists($file_path)){
            $return['status'] = 'no_file';
            return $return;
        }


        $pa_ID = $report['pa_ID'];
        $doc_ID = $report['doc_ID'];

        $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pa_ID} ", ARRAY_A);
        $doctor = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_ID} ", ARRAY_A);
        $npi = $doctor['doc_npi'];
        $dob = date('m/d/Y',strtotime($patient['pa_dob']));



        if(!empty($npi)){

            $report_created = $report['created_date'];
            $keys = array(
                'patient_name',
                'requesting_physician',
                'physician_phone',
                'requesting_practice',
                'assen',
                'lab',
                'patient_dob',
                'acquisition_date',
                'sample_type',
                'doc',
                'spec_received',
                'medications',
                'panel_tests_requested',
                'dagnostic_codes1'

            );
            $data = array();
            foreach($keys as $k)
            {
                $data[$k] = $report[$k];
            }


            $rs = $this->put_report_providers($npi,$report['patient_dob'],$patient['pa_last'],$patient['pa_first'],$patient['pa_ssn'],$data,$file_path,$report_created);
            $return['rs'] = $rs;
            if($rs && !empty($rs['id'])){
                $wpdb->update('ci_reports',array('opi_id'=>$rs['id']),array('id'=>$re_id));
                $return['id'] = $rs['id'];
                $return['status'] = 'ok';
                return $return;
            }

        }else{

            $return['status'] = 'no_npi';
            return $return;
        }

        return $return;

    }
}