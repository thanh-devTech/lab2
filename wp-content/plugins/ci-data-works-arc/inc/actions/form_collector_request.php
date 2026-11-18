<?php
global $wpdb;

$_POST = tr_pdd_stripslashes_deep($_POST);

$col = $_POST['col'];
$cols=$_POST['cols'];
$rep = $_POST['rep'];
$cli = $_POST['cli'];

$col['col_email'] = trim($col['col_email']);
$rep['rep_email'] = trim($rep['rep_email']);

//var_dump($col);exit;
$clinics = is_array($col['cli_ID'])? $col['cli_ID'] : array();
$reps = is_array($col['rep_ID'])? $col['rep_ID'] : array();

$col_id = @$col['col_ID'];
unset($col['col_ID']);
unset($col['cli_ID']);
if($reps[0]>0)
{
    $col['rep_ID'] = $reps[0];
}



//check rep empty field
$rep_data = $wpdb->get_row("select * from ci_lab_reps where rep_ID = '{$col['rep_ID']}' ");
$update_rep = array();

if(empty($rep_data->rep_email) && !empty($rep['rep_email'])){
    $update_rep['rep_email'] = $rep['rep_email'];
}
if(empty($rep_data->rep_phone) && !empty($rep['rep_phone'])){
    $update_rep['rep_phone'] = $rep['rep_phone'];
}

if(count($update_rep)>0)
{
    $wpdb->update('ci_lab_reps',$update_rep,array('rep_ID'=>$rep_data->rep_ID));
}


if($col_id>0)
{
    $wpdb->delete('ci_clinic_collector',array('col_ID'=>$col_id));
    $wpdb->delete('ci_rep_collector',array('col_ID'=>$col_id));

    $rs = $wpdb->update('ci_collector',$col,array('col_ID'=>$col_id));
    if($rs===false)
    {
        Tr_Session_Class::add_error('Error. Please contact Admin. '.$wpdb->last_error);
    }
    $col['col_ID'] = $col_id;
    tr_pdd_create_user_collector($col,true);

    foreach($clinics as $cli_ID)
    {
        $wpdb->insert('ci_clinic_collector',array('cli_ID'=>$cli_ID,'col_ID'=>$col_id));
    }

    foreach($reps as $rep_ID)
    {
        $wpdb->insert('ci_rep_collector',array('rep_ID'=>$rep_ID,'col_ID'=>$col_id));
    }
}
else{
    if ($col['lab_ID'] == 0) {
        $col['lab_ID'] = CI_Base_Lab::get_current_lab_id();
    }
    $default_col = $col;

    foreach($cols as $col)
    {
        $col = wp_parse_args($col,$default_col);
        $col['col_email'] = trim($col['col_email']);
        //check email exists
        $exists = $wpdb->get_var("select col_ID from ci_collector where col_email = '{$col['col_email']}'");
        if($exists)
        {
           // var_dump($col);
            Tr_Session_Class::add_error('Collector Email is exists: '.$col['col_email']);
            continue;
        }
        $rs = $wpdb->insert('ci_collector', $col);
        if($rs===false)
        {
            Tr_Session_Class::add_error('Error. Please contact Admin. '.$wpdb->last_error);
        }else{

            $col['col_ID'] = $col_id = $wpdb->insert_id;


            foreach($clinics as $cli_ID)
            {
                $wpdb->insert('ci_clinic_collector',array('cli_ID'=>$cli_ID,'col_ID'=>$col_id));
            }
            foreach($reps as $rep_ID)
            {
                $wpdb->insert('ci_rep_collector',array('rep_ID'=>$rep_ID,'col_ID'=>$col_id));
            }

            tr_pdd_create_user_collector($col,true);
            $is_insert = true;

            if ($is_insert) {
                //send mail to admin

                $lab = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$col['lab_ID']}");
                $rep_data = $wpdb->get_row("select * from ci_lab_reps where rep_ID = '{$col['rep_ID']}' ");
                $clinic = array();
                foreach ($clinics as $cli_ID) {
                    $clinic[] = $wpdb->get_var("select cli_clinic from ci_clinic where cID = {$cli_ID}");
                }
                $clinic = implode(', ', $clinic);

                $nl = "<br>";
                if (function_exists('get_blog_option')) {
                    $settings = get_blog_option(1, 'ci_data_work_net');
                }else{
                    $settings = get_option('ci_pdf_data');
                }
                $from_name = $lab;
                if (empty($from_name)) {
                    $from_name = get_option('blogname');
                    $from_name = wp_specialchars_decode($from_name, ENT_QUOTES);
                }

                $from_email = get_option('admin_email');


                $headers = 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
                $headers .= 'Reply-To: ' . $from_email . "\r\n";
                $headers .= "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

                if (!empty($settings['email_collector_notify'])) {
                    $to = $settings['email_collector_notify'];
                    $subject = 'New Collector added to '.get_bloginfo('name');

                    $message = 'New collector added "' . $col['col_first'] . ' ' . $col['col_last'] . '"' . $nl;
                    $message .= 'Lab: ' . $lab . $nl;

                    $message .= $nl . '<h4>Sales Representative</h4>';
                    $message .= 'Rep Name: ' . $rep_data->rep_first . ' ' . $rep_data->rep_last . $nl;
                    $message .= 'Email: ' . $rep_data->rep_email . $nl;
                    $message .= 'Phone: ' . $rep_data->rep_phone . $nl;
                    $message .= 'Date of Request: ' . $col['col_rdate'] . $nl;
                    $message .= 'Date training needs to be completed by: ' . $col['col_cdate'] . $nl;
                    $message .= 'Collector start date in the office: ' . $col['col_sdate'] . $nl;

                    $message .= $nl . '<h4>Collector</h4>';
                    $message .= 'Name: ' . $c['col_first'] . ' ' . $c['col_last'] . $nl;
                    $message .= 'Email: ' . $c['col_email'] . $nl;
                    $message .= 'Phone: ' . $c['col_phone'] . $nl;
                    $message .= 'Training Location: ' . $c['col_location'] . $nl;

                    $message .= $nl . '<h4>Available Training Sessions</h4>';
                    $message .= $col['col_session'] . $nl;

                    $message .= $nl . '<h4>Office Information</h4>';
                    $message .= 'Clinic: ' . $clinic . $nl;
                    $message .= 'Physicians: ' . $col['col_physicians'] . $nl;
                    $message .= 'Address: ' . $col['col_street'] . $nl;
                    $message .= 'Telephone Number: ' . $cli['cli_om-phone'] . $nl;
                    $message .= 'Fax Number: ' . $cli['cli_om-fax'] . $nl;


                    $message .= $nl . '<h4>Please check all that apply</h4>';
                    if ($col['col_poc-cups']) {
                        $message .= "POC Cups" . $nl;
                    }
                    if ($col['col_fax-result']) {
                        $message .= "Results Faxed" . $nl;
                    }
                    if ($col['col_analyser']) {
                        $message .= "Office Analyzer" . $nl;
                    }
                    if ($col['col_portal']) {
                        $message .= "Results via online portal" . $nl;
                    }
                    if ($col['col_dna']) {
                        $message .= "DNA" . $nl;
                    }

                    $message .= $nl . '<h4>Notes</h4>';
                    $message .= $col['col_notes'] . $nl;

                    $rs = wp_mail($to, $subject, $message, $headers);
                    if (!$rs) {
                        Tr_Session_Class::add_error('Error. while send email');
                    }
                }
            }

        }
    }
}




if($col_id > 0)
{
    Tr_Session_Class::add('Saved Collector form');
}
//echo $wpdb->last_query;exit;
trpdd_log_form('form_collector_request');
return true;