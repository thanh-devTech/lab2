<?php

global $wpdb, $blog_id;


$btype      = 'dna';

$_POST      = tr_pdd_stripslashes_deep($_POST);


if (trpdd_check_form_unique_id()) {
    $_POST = array();
    return;
}

$batch      = $_POST['bat'];
$pa         = isset($_POST['pa']) ? $_POST['pa'] : array();
$ins1       = isset($_POST['ins1']) ? $_POST['ins1'] : array();
$ins2       = isset($_POST['ins2']) ? $_POST['ins2'] : array();
$col        = isset($_POST['col']) ? $_POST['col'] : array();
$lab        = isset($_POST['lab']) && is_array($_POST['lab']) ? $_POST['lab'] : array();

if (isset($batch['lab_id']) && $batch['lab_id'] > 0) {
    $lab['lab_ID'] = $batch['lab_id'];
}


$is_saved_form = isset($_POST['only_save_form']);
$redirect_url = false;
$need_print_assen = isset($_REQUEST['save_print']);

if ($is_saved_form) {
    $batch['is_saved'] = 1;
} else {
    $batch['is_saved'] = 0;
}


$table_batch        = 'ci_batches_dna';
$patient_signature  = $_POST['patient_signature'];
$provider_signature = $_POST['provider_signature'];

if (isset($batch['assen'])) {
    $batch['assen'] = trim($batch['assen']);
}

if (empty($batch['assen']) && isset($_REQUEST['auto_generated'])) {
    $batch['assen'] = tr_get_new_assen('dna', false, '', $lab);
}

if (isset($batch['doc']) && (empty($batch['doc']) || $batch['doc'] == 'auto')) {
    $batch['doc'] = date_i18n('Y-m-d H:i:s');
}

$mid = isset($batch['mid']) ? $batch['mid'] : 0;
$bat = null;

if (!isset($batch['mid']) || $batch['mid'] == 0) {
    if (!empty($batch['assen'])) {
        $b_old = $wpdb->get_row("select * from ci_batches_dna where assen = '{$batch['assen']}' and batch_status!='completed'");
        if ($b_old) {
            $batch['mid'] = $b_old->mid;
            $mid = $batch['mid'];
            $bat = $b_old;
            if (empty($batch['batch_status'])) {
                unset($batch['batch_status']);
            }
            unset($batch['batch_id']);
        }
    }
} else {
    //if update
    $mid = $batch['mid'];
    $bat = $wpdb->get_row("select * from {$table_batch} where mid = {$mid}");
    if ($pa['pa_ID'] == 0) {
        $pa['pa_ID'] = $bat->patient_id;
    }
    if (empty($batch['lab_id'])) {
        $batch['lab_id']  = $bat->lab_id;
    }

    if ($bat && (!isset($batch['assen']) || empty($batch['assen']))) {
        $batch['assen'] = $bat->assen;
    }
    if ($bat->batch_status != $batch['batch_status'] && isset($batch['batch_status'])) {
        $is_rejected = tr_pdd_get_batch_statuses($batch['batch_status'], true, true, 'dna');
        if ($batch['batch_status'] == 'archived' || $is_rejected)
            $batch['archived_date'] = date_i18n('Y-m-d H:i:s');
        if ($bat->batch_status != 'hold' && $batch['batch_status']  == 'hold') {
            $wpdb->replace($table_batch . '_meta', [
                'mid' => $mid,
                'meta_key' => 'hold_batch_status',
                'meta_value' => $bat->batch_status
            ]);
        }
        if ($bat->batch_status == 'hold' && $batch['batch_status']  != 'hold') {
            $wpdb->delete($table_batch . '_meta', ['mid' => $mid, 'meta_key' => 'hold_batch_status']);
        }
    }
}

if (isset($pa['pa_dob'])) {
    $pa['pa_dob'] = tr_format_date_default($pa['pa_dob'], 'Y-m-d');
}


if (is_array(@$lab) && !empty($lab['lab_dba'])) {
    if (@$lab['lab_ID'] > 0) {
        $batch['lab_id'] = $lab['lab_ID'];
        $batch['lab'] = $lab['lab_dba'];
    } else {
        $lab_id = $wpdb->get_var($wpdb->prepare("select lab_ID from ci_labs where lab_dba=%s", $lab['lab_dba']));
        if ($lab_id > 0) {
            $batch['lab_id'] = $lab_id;
        }
    }
} else if (empty($batch['lab_id'])) {
    $batch['lab_id'] = CI_Base_Lab::get_current_lab_id();
}


//check new clinic
if (!empty($batch['requesting_practice']) && $batch['cli_ID'] == 0) {

    //check exists
    $batch['requesting_practice'] = trim(ucwords($batch['requesting_practice']));
    $cli_id = $wpdb->get_var("select cID from ci_clinic where cli_clinic='{$batch['requesting_practice']}' and `cli_lab-ID` = '{$batch['lab_id']}'");
    if ($cli_id > 0) {
        $batch['cli_ID'] = $cli_id;
    } else {
        $arr = array(
            'cli_clinic' => $batch['requesting_practice'],
            'cli_lab-ID' => $batch['lab_id']
        );
        //var_dump($arr);
        if (!isset($arr['cli_sdate'])) {
            $arr['cli_sdate'] = Tr_Base_Class_V6::date('Y-m-d');
        }
        $wpdb->insert('ci_clinic', $arr);
        $cli_id = $wpdb->insert_id;
        $batch['cli_ID'] = $cli_id;
        tr_check_and_create_clinic_user($cli_id, true);
    }

    //var_dump($wpdb->last_error);exit;
}

$cli = $wpdb->get_row("select cli_clinic from ci_clinic where cID = {$batch['cli_ID']}");
$batch['requesting_practice'] = ucwords($cli->cli_clinic);

if (@$batch['lab_id'] == 0) {
    $batch['lab_id'] = tr_get_lab_id_by_clinic($batch['cli_ID']);
}
$batch['lab'] = tr_get_lab_short($batch['lab_id']);

if (isset($pa['pa_dob'])) {
    $batch['dob'] = $pa['pa_dob'];
}

if (isset($batch['position']) && !empty($batch['position'])) {
    $batch['position'] = intval($batch['position']);
}


//lab ref
$ref_lab = isset($_REQUEST['ref_lab']) ? $_REQUEST['ref_lab'] : array();
if (isset($ref_lab['lab_dba_init']) && !empty($ref_lab['lab_dba_init'])) {
    $batch['ref_lab_ID'] = $wpdb->get_var("select lab_ID from ci_labs where lab_ID='{$ref_lab['lab_ID']}' and lab_dba_init='{$ref_lab['lab_dba_init']}'");
    if ($batch['ref_lab_ID'] == 0) {
        $batch['ref_lab_ID'] = $wpdb->get_var("select lab_ID from ci_labs where lab_dba_init='{$ref_lab['lab_dba_init']}' or lab_dba_init = '{$ref_lab['lab_dba_init']}'");
    }
} else if ($blog_id > 1 && CI_Base_Lab::get_current_lab_id() != $batch['lab_id']) {
    $batch['ref_lab_ID'] = CI_Base_Lab::get_current_lab_id();
}


if (isset($batch['ref_lab_ID']) && $batch['ref_lab_ID'] == $batch['lab_id']) {
    $batch['ref_lab_ID'] = 0;
}

$batch['assen'] = CI_Base_Lab::replace_assen_by_reflab($batch['assen'], $bat, $batch, $btype);


//check $batch['assen']
$has_duplicate_assen = false;
if ($mid == 0) {
    $batch['batch_type'] = @$batch['batch_short'] == '1' ? 'M' : 'E';
    if (!empty($batch['batch_id'])) {
        $batch['spec_received'] = Tr_Base_Class_V6::date('Y-m-d H:i:s');
    }
    $batch['author_ID'] = get_current_user_id();

    $exist_assen = $wpdb->get_var("select assen from {$table_batch} where  assen like '{$batch['assen']}-%' or assen='{$batch['assen']}' order by assen desc");
    if ($exist_assen) {
        $assen_num = $wpdb->get_var("select cast(SUBSTRING_INDEX(assen,'-',-1) as  DECIMAL) as num from {$table_batch} where  assen like '{$batch['assen']}-%'  order by num desc");

        if ($assen_num > 0) {
            $assen_num = $assen_num + 1;
            $assen_num = $assen_num <= 9 ? '0' . $assen_num : $assen_num;
            $batch['assen'] = $batch['assen'] . '-' . $assen_num;
        } else {
            $batch['assen'] = $batch['assen'] . '-01';
        }
        $has_duplicate_assen = true;
    }
}


//check patient
$patient = false;
if (isset($pa['pa_ssn'])) {
    $pa['pa_ssn'] = trim($pa['pa_ssn']);
}


if (isset($pa['med_number'])) {
    $pa['pa_mrn'] = $pa['med_number'];
    unset($pa['med_number']);
}

if (@$pa['pa_ID'] > 0) {
    $patient = tr_pdd_get_patient_duplicate($pa, $bat);
} else {
    //find patient exist
    $sql = $wpdb->prepare("select * from ci_patients WHERE pa_first=%s AND pa_last=%s AND pa_dob =%s ", $pa['pa_first'], $pa['pa_last'], $pa['pa_dob']);
    $patient = $wpdb->get_row($sql);
}
unset($pa['pa_ID']);
if ($batch['cli_ID'] > 0) {
    $pa['pa_clinic_ID'] = $batch['cli_ID'];
}

//fix phone
if (isset($pa['pa_phone'])) {
    $pa['pa_phone'] = str_replace(array(' ', '(', ')', '-', '.'), '', $pa['pa_phone']);
}


if (!@$patient) {
    $wpdb->insert('ci_patients', $pa);
    $patient_id = $wpdb->insert_id;

    $batch['patient_name'] = trim($pa['pa_last'] . ', ' . $pa['pa_first']);
} else {
    $patient_id = $patient->pa_ID;
    trpdd_update_patient($pa, $patient_id);
    $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id}");
    $batch['patient_name'] = trim($patient->pa_last . ', ' . $patient->pa_first);
}

$batch['patient_id'] = $patient_id;
//end patient





//insurance
if (isset($ins1['ins_company'])) {
    $ins1['ins_company'] = tr_pdd_capitalized($ins1['ins_company']);
}
//check if change company
if (isset($ins1['ins_ID']) && $ins1['ins_ID'] > 0) {
    $oldins = $wpdb->get_row("select * from ci_insurances where ins_ID = {$ins1['ins_ID']}", ARRAY_A);
    if ($ins1['ins_company'] != $oldins['ins_company']) {
        $ins1['ins_ID'] = 0;
    }
}
if (isset($ins1['ins_ID']) && $ins1['ins_ID'] > 0) {
    trpdd_update_insurance($ins1, $ins1['ins_ID']);
    $batch['insurance1'] = $ins1['ins_ID'];
    $batch['insurance'] = $ins1['ins_company'];
} else if (!empty($ins1['ins_first']) || !empty($ins1['ins_last']) || !empty($ins1['ins_company'])) {
    $ins1['pa_ID'] = $patient_id;
    $ins1['lab_ID'] = $batch['lab_id'];
    $wpdb->insert('ci_insurances', $ins1);
    $insurance_id = $wpdb->insert_id;
    $batch['insurance1'] = $insurance_id;
    $batch['insurance'] = $ins1['ins_company'];
}

if (isset($_REQUEST['no_insurance']) && !empty($_REQUEST['no_insurance'])) {
    $batch['insurance'] = $_REQUEST['no_insurance'];
}

if (isset($ins2['ins_ID']) && $ins2['ins_ID'] > 0) {
    $batch['insurance2'] = $ins2['ins_ID'];
    $wpdb->update('ci_insurances', $ins2, array('ins_ID' => $ins2['ins_ID']));
} else if (!empty($ins2['ins_first']) || !empty($ins2['ins_last']) || !empty($ins2['ins_company'])) {
    $ins2['pa_ID'] = $patient_id;
    $ins2['lab_ID'] = $batch['lab_id'];
    $ins2['ins_is_second'] = 1;

    $rs = $wpdb->insert('ci_insurances', $ins2);
    $insurance2_id = $wpdb->insert_id;
    $batch['insurance2'] = $insurance2_id;
}
//end insurance

//update col
if ($col['col_ID'] > 0) {
    $update_col = array();
    $collector = $wpdb->get_row("select * from ci_collector where col_ID = {$col['col_ID']}", ARRAY_A);
    $collector['col_phone'] = trim($collector['col_phone']);
    if (empty($collector['col_phone'])) {
        $update_col['col_phone'] = $col['col_phone'];
    }
    if (empty($collector['col_first'])) {
        $update_col['col_first'] = $col['col_name'];
    }
    if (count($update_col) > 0) {
        $wpdb->update('ci_collector', $update_col, array('col_ID' => $col['col_ID']));
    }
} else if (!empty($col['col_name'])) {
    $data = array(
        'col_first' => $col['col_name'],
        'col_phone' => $col['col_phone'],
        'cli_ID'    => $batch['cli_ID']
    );
    $wpdb->insert('ci_collector', $data);
    $col['col_ID'] = $wpdb->insert_id;
}
if ($col['col_ID'] > 0) {
    $batch['col_ID'] = $col['col_ID'];
}

//end col


if (isset($_POST['has_edit_medication'])) {

    $batch['medication'] = maybe_serialize($batch['medication']);


    if (!is_array($batch['current_medication'])) {
        $batch['current_medication'] = array();
    }
    foreach ((array)$batch['current_medication_other'] as $vl) {
        $vl = trim($vl);
        if (empty($vl)) continue;
        $batch['current_medication'][] = $vl;
    }
    unset($batch['current_medication_other']);

    $batch['current_medication'] = implode(',', $batch['current_medication']);
    if (isset($_REQUEST['current_medication_none']) && $_REQUEST['current_medication_none'] == '1') {
        $batch['current_medication'] = 'None Listed';
    }
}
//check dagnostic_codes
if (isset($batch['dagnostic_codes'])) {
    $batch['dagnostic_codes1'] = implode(',', (array) $batch['dagnostic_codes']);
    unset($batch['dagnostic_codes']);
}

if (isset($batch['dagnostic_codes2']) && is_array($batch['dagnostic_codes2'])) {
    $batch['dagnostic_codes2'] = implode(',', (array) $batch['dagnostic_codes2']);
}

if (isset($_REQUEST['has_panel_requests'])) {
    $testing_ordered = array();
    if (!is_array($_REQUEST['testing_ordered'])) {
        $_REQUEST['testing_ordered'] = array();
    }
    foreach ($_REQUEST['testing_ordered'] as $panel) {
        $tests = array();
        if (is_array($_REQUEST['testing_ordered_name'][$panel])) {
            foreach ($_REQUEST['testing_ordered_name'][$panel] as $j => $nvl) {
                $nvl = trim($nvl);
                if (!empty($nvl)) {
                    $tests[] = array(
                        'type' => @$_REQUEST['testing_ordered_add'][$panel][$j],
                        'name' => $nvl
                    );
                }
            }
        }
        $testing_ordered[$panel] = $tests;
    }
    $batch['panel_tests_requested'] = json_encode($testing_ordered);
}





//check new doc
if (!empty($batch['requesting_physician']) && $batch['doc_ID'] == 0) {
    //check exists
    $batch['requesting_physician'] = trim($batch['requesting_physician']);
    $doc_specialty = '';
    if (strpos($batch['requesting_physician'], ',') > 0) {
        list($doc_last, $doc_first) = explode(',', $batch['requesting_physician'], 2);

        //check specialty
        $doc_first = trim($doc_first);
        $doc_last = trim($doc_last);
        if (strpos($doc_first, ' ') > 0) {
            list($doc_first, $doc_specialty) = explode(' ', $doc_first, 2);
        }
    } else {
        list($doc_first, $doc_last) = explode(' ', $batch['requesting_physician'], 2);
        $doc_first = trim($doc_first);
        $doc_last = trim($doc_last);
        if (strpos($doc_last, ' ') > 0) {
            list($doc_last, $doc_specialty) = explode(' ', $doc_last, 2);
        }
    }


    $doc_id = $wpdb->get_var("select doc.doc_ID from ci_doc doc
                                join ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                where doc.doc_first='{$doc_first}' and doc.doc_last='{$doc_last}'
                                and doc.doc_specialty='{$doc_specialty}'
                                and cd.cli_ID = '{$batch['cli_ID']}'");

    if ($doc_id > 0) {
        $batch['doc_ID'] = $doc_id;
    } else {
        $doc = array(
            'doc_first' => $doc_first,
            'doc_last' => $doc_last,
            'doc_specialty' => $doc_specialty,
            'cli_ID' => $batch['cli_ID']
        );
        $doc = tr_data_update_doc($doc);
        $batch['doc_ID'] = $doc['doc_ID'];
    }
}
if ($batch['doc_ID'] > 0) {
    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$batch['doc_ID']}");
    if (!isset($batch['requesting_physician']) || empty($batch['requesting_physician'])) {
        $batch['requesting_physician'] = trim($doc->doc_first . ' ' . $doc->doc_last);
    }
    $batch['requesting_physician'] = ucwords($batch['requesting_physician']);

    $batch['physician_phone'] = $doc->doc_phone;
}



$signature_id = $batch['assen'];
if (empty($signature_id)) {
    $signature_id = 'batch_' . time();
}
//patient signature
$patient_sign = tr_save_signature($signature_id . '_patient', $patient_signature, $_FILES['patient_signature']);

if (isset($_REQUEST['onfile'])) {
    $batch['patient_signature'] = 'onfile';
} else if ($patient_sign) {
    $batch['patient_signature'] = $patient_sign;
} else if (!empty($_REQUEST['img_patient_signature'])) {
    $batch['patient_signature'] = $_REQUEST['img_patient_signature'];
}

//provider signature

$provider_sign = tr_save_signature($signature_id . '_provider', $provider_signature, $_FILES['provider_signature']);
if ($provider_sign) {
    $batch['provider_signature'] = $provider_sign;
} else if (!empty($_REQUEST['img_provider_signature'])) {
    $batch['provider_signature'] = $_REQUEST['img_provider_signature'];
}


//save card files
$ins_front = tr_save_ins_card($signature_id . '_ins', $_FILES['ins_card_front']);
if ($ins_front) {
    $batch['ins_card_front'] = $ins_front;
} else if (!empty($_REQUEST['img_ins_card_front'])) {
    $batch['ins_card_front'] = $_REQUEST['img_ins_card_front'];
}

$ins_back = tr_save_ins_card($signature_id . '_ins_back', $_FILES['ins_card_back']);
if ($ins_back) {
    $batch['ins_card_back'] = $ins_back;
} else if (!empty($_REQUEST['img_ins_card_front'])) {
    $batch['ins_card_back'] = $_REQUEST['img_ins_card_back'];
}

$id_front = tr_save_ins_card($signature_id . '_id', $_FILES['patient_id_front']);
if ($id_front) {
    $batch['patient_id_front'] = $id_front;
} else if (!empty($_REQUEST['img_ins_card_front'])) {
    $batch['patient_id_front'] = $_REQUEST['img_patient_id_front'];
}

$id_back = tr_save_ins_card($signature_id . '_id_back', $_FILES['patient_id_back']);
if ($id_back) {
    $batch['patient_id_back'] = $id_back;
} else if (!empty($_REQUEST['img_ins_card_front'])) {
    $batch['patient_id_back'] = $_REQUEST['img_patient_id_back'];
}

//save issue_photo
if (isset($_FILES['issue_photo'])) {
    $id_issue = tr_save_issue_photo($signature_id . '_issue', $_FILES['issue_photo']);
    if ($id_issue) {
        $batch['issue_photo'] = $id_issue;
    } else if (!empty($_REQUEST['issue_photo'])) {
        $batch['issue_photo'] = $_REQUEST['issue_photo'];
    }
}


//save result pdf
if (isset($batch['result_pdf'])) {
    $result_pdf = tr_save_result_pdfs_upload(
        $signature_id,
        $batch['result_pdf'],
        isset($_FILES['result_pdfs']) ? $_FILES['result_pdfs'] : null,
        isset($_FILES['result_pdf']) ? $_FILES['result_pdf'] : null
    );

    if ($result_pdf) {
        $batch['result_pdf'] = $result_pdf;
    }
}

//save $original_req
if (isset($batch['original_req'])) {
    $original_req = tr_save_multi_result_upload(
        $signature_id . 'original',
        $batch['original_req'],
        isset($_FILES['original_reqs']) ? $_FILES['original_reqs'] : null,
        isset($_FILES['original_req']) ? $_FILES['original_req'] : null
    );
    if ($original_req) {
        $batch['original_req'] = $original_req;
    }
}



if ($batch['batch_short'] == '1' || $_GET['page'] == 'ci_batches_view') {
    //short form
    $batch['short_form_by'] = get_current_user_id();
    $batch['short_form_time'] = date_i18n('Y-m-d H:i:s');
} else {
    $batch['req_form_by'] = get_current_user_id();
    $batch['req_form_time'] = date_i18n('Y-m-d H:i:s');
}


//insert batch
if ($mid == 0) {
    $batch['batch_created'] = date_i18n('Y-m-d H:i:s');

    if (isset($_GET['debug'])) {
        var_dump($batch);
    }

    $rs = $wpdb->insert($table_batch, $batch);
    $mid = $wpdb->insert_id;
} else {
    $batch['batch_modified'] = date_i18n('Y-m-d H:i:s');
    $rs = $wpdb->update($table_batch, $batch, array('mid' => $mid));
}


if ($rs === false) {
    Tr_Session_Class::add_error('Error REQUISITION FORM. ' . $wpdb->last_error);
} else {
    global $tr_footer_js_script;
    if ($need_print_assen) {
        $patient_name = $batch['patient_name'];
        if (!empty($batch['dob'])) {
            $patient_name .= '  ' . tr_pdd_display_date($batch['dob']);
        }
        $doc = tr_pdd_display_date($batch['doc'], 'm/d/Y H:i');
        $position = $bat && $bat->position > 0 ? $bat->position : '';
        $tr_footer_js_script .= "\n" . 'setTimeout(function(){jQuery.tr_print_barcode("' . $batch['assen'] . '","' . $patient_name . '","' . $position . '","' . $doc . '")},1500);' . "\n";
    }
    if ($batch['mid'] > 0) {
        Tr_Session_Class::add('Updated REQUISITION FORM');

        if (!$is_saved_form && $_POST['saved_id'] > 0) {
            $redirect_url = Tr_Base_Class_V6::link(array('tab' => ''), false);
        }
    } else {
        $edit_link = 'admin.php?page=' . $_GET['page'] . '&act=edit&id=' . $mid;
        if (isset($_GET['tab'])) {
            $edit_link .= '&tab=' . $_GET['tab'];
        }
        if ($is_saved_form) {
            $edit_link = 'admin.php?page=' . $_GET['page'] . '&tab=saved_form&act=edit&id=' . $mid;
        }
        Tr_Session_Class::add('Saved REQUISITION FORM ' . $batch['assen'] . ' <a href="' . $edit_link . '">Edit</a>');
    }



    if ($has_duplicate_assen) {
        Tr_Session_Class::add('Warning duplicate Accession #' . $batch['assen'] . ' Added!');
    }

    if ($ins1['ins_authorization'] == '1' && $mid > 0) {
        $redirect_url = Tr_Base_Class_V6::link(array('tab' => 'prior_authorization', 'mid' => $mid), false);
    }

    $batch['is_saved_form'] = $is_saved_form;
    do_action('saved_req', 'dna', $mid, $batch, $pa);

    $nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
    if ($nextcron == 0 || $nextcron > time() + 300) {
        wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', array('dna'));
    }
}

$_POST['mid'] = $mid;

if ($bat) {
    $_POST['old_status'] = $bat->batch_status;
}
trpdd_log_form('save_dna');

if (isset($_POST['submit_next']) && isset($_POST['next_link'])) {
    $redirect_url = $_POST['next_link'];
}

if (!empty($redirect_url)) {
    wp_redirect($redirect_url);
}
