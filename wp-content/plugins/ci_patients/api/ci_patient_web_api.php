<?php
require_once  'restful_api.php';

add_filter('https_ssl_verify', '__return_false');

class CI_Patient_Web_Api extends restful_api
{
    function __construct($pre_endpoint)
    {
        $this->hide_info = false;
        $this->enable_log = true;
        parent::__construct($pre_endpoint);
    }

    public function request_update_patient()
    {
        global $wpdb;

        $patient_id     = $this->params['patient_id'];
        $user_id        = $this->params['user_id'];

        $current_user_id = get_current_user_id();
        if (empty($user_id) || $current_user_id != $user_id) {
            $this->response(200, array(
                'status'  => 'error',
                'message' => 'Unauthorized access!'
            ));
            exit;
        }

        $return = array(
            'status'  => 'ok',
            'message' => 'Update success!'
        );

        if (trim($this->params['pa_first']) == "") {
            $return = array(
                'status'  => 'error',
                'message' => 'First Name Not Null'
            );
            $this->response(200, $return);
            exit;
        }

        if (trim($this->params['pa_last']) == "") {
            $return = array(
                'status'  => 'error',
                'message' => 'Last Name Not Null'
            );
            $this->response(200, $return);
            exit;
        }

        $patientkeys = array(
            'pa_first'  => 'first_name',
            'pa_last'   => 'last_name',
            'pa_email'  => 'email',
            'pa_phone'  => 'phone',
            'pa_dob'    => 'dob',
            'pa_mrn'    => 'MRN'
        );
        $patientdata = array();
        foreach ($patientkeys as $k => $v) {
            if (isset($this->params[$k]) && !empty($this->params[$k])) {

                $vl = trim($this->params[$k]);
                if ($k == 'pa_state') {
                    $vl = substr($vl, 0, 2);
                } else if ($k == 'pa_phone' && !empty($vl)) {
                    update_user_meta($user_id, 'otp_identifier', $vl);
                } else if ($k == 'pa_first' && !empty($vl)) {
                    update_user_meta($user_id, 'first_name', $vl);
                } else if ($k == 'pa_last' && !empty($vl)) {
                    update_user_meta($user_id, 'last_name', $vl);
                }

                $patientdata[$k] = $vl;

                $rs = $wpdb->update('ci_patients', array(
                    $k => $vl
                ), array('pa_ID' => $patient_id));


                if ($rs === false) {
                    $return['status'] = 'error';
                    $return['message'] = 'Update profile error';
                    $return['error_fields'][] = $k;
                }
            }
        }

        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $this->params['pa_first'],
            'last_name'    => $this->params['pa_last'],
            'display_name' => trim($this->params['pa_first'] . ' ' . $this->params['pa_last']),
        ]);

        $return['data'] = $patientdata;

        $wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));

        $this->response(200, $return);
    }

    public function request_update_state_sidebar()
    {
        $user_id       = $this->params['user_id'];
        $status        = $this->params['status'];

        $current_user_id = get_current_user_id();
        if (empty($user_id) || $current_user_id != $user_id) {
            $this->response(200, array(
                'status'  => 'error',
                'message' => 'Unauthorized access!'
            ));
            exit;
        }


        update_user_meta($user_id, 'state_sidebar', $status);
        $return = array(
            'status'  => 'ok',
            'message' => 'Update success!'
        );
        $this->response(200, $return);
    }

    public function request_add_patient_linked()
    {
        $user_id      = $this->params['user_id'];
        $first_name   = $this->params['first_name'];
        $last_name    = $this->params['last_name'];
        $birthday     = $this->params['birthday'];
        $patient_code = $this->params['patient_code'];

        $current_user_id = get_current_user_id();
        if (empty($user_id) || $current_user_id != $user_id) {
            $this->response(200, array(
                'status'  => 'error',
                'message' => 'Unauthorized access!'
            ));
            exit;
        }

        $return = array(
            'status'  => 'ok',
            'message' => 'Add patient linked success!'
        );

        $pa_options = get_option('trpatient_settings', array());
        if (!isset($pa_options['disable_18kid'])) {
            $pa_options['disable_18kid'] = false;
        }

        $kid = array();
        $kid['last_name'] = $last_name;
        $kid['first_name'] = $first_name;
        $age = date('Y') - date('Y', strtotime($birthday));
        if ($age <= 19 || $pa_options['disable_18kid']) {
            $patient_id = trpa_patient_id_from_code($patient_code, $first_name, $last_name, $birthday);
            if (empty($patient_id)) {
                $patient_id = trpa_patient_id_from_code($patient_code, $last_name, $first_name, $birthday);
                if ($patient_id > 0) {
                    $kid['last_name'] = $first_name;
                    $kid['first_name'] = $last_name;
                }
            }

            if ($patient_id > 0) {
                $kids = get_user_meta($user_id, '_patient_kids', true);
                if (!is_array($kids)) $kids = array();
                $kids[$patient_id] = $kid;
                update_user_meta($user_id, '_patient_kids', $kids);
                $return['message'] = '<div class="success">Added Successfully!</div>';
                $return['redirect'] = Tr_Base_Class_V6::link(array('tab' => 'patient_' . $patient_id, 'act' => '', 'trajax' => ''), false);
            } else {
                $return['status'] = 'error';
                $return['message'] = '<div class="error"><b>Error</b>: Please correct your name, dob or patient code!</div>';
            }
        } else {
            $return['status'] = 'error';
            $return['message'] = '<div class="error"><b>Error</b>: Only allow kid\'s Age under 18!</div>';
        }

        $this->response(200, $return);
    }

    public function request_removekid()
    {
        $user_id    = $this->params['user_id'];
        $kidid      = intval($this->params['kidid']);
        $kids = get_user_meta($user_id, '_patient_kids', true);

        $current_user_id = get_current_user_id();
        if (empty($user_id) || $current_user_id != $user_id) {
            $this->response(200, array(
                'status'  => 'error',
                'message' => 'Unauthorized access!'
            ));
            exit;
        }

        unset($kids[$kidid]);
        update_user_meta($user_id, '_patient_kids', $kids);

        $return = array(
            'status'  => 'ok',
            'message' => 'Remove Patient linked success!'
        );

        $this->response(200, $return);
    }
}
