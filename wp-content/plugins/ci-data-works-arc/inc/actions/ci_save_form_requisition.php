<?php

global $wpdb, $blog_id;


$_POST = tr_pdd_stripslashes_deep($_POST);

if (trpdd_check_form_unique_id()) {
    $_POST = array();
    return;
}

$btype      = 'tox';
$changes    = array();
$batch      = $_POST['bat'];
$batmeta    = isset($_POST['batmeta']) ? $_POST['batmeta'] : array();
$pa         = isset($_POST['pa']) ? $_POST['pa'] : array();
$ins1       = isset($_POST['ins1']) ? $_POST['ins1'] : array();
$ins2       = isset($_POST['ins2']) ? $_POST['ins2'] : array();
$col        = isset($_POST['col']) ? $_POST['col'] : array();
$lab        = isset($_POST['lab']) && is_array($_POST['lab']) ? $_POST['lab'] : array();

if (isset($batch['lab_id']) && $batch['lab_id'] > 0) {
    $lab['lab_ID'] = $batch['lab_id'];
}


$is_saved_form  = isset($_POST['only_save_form']);
$redirect_url   = false;
$need_print_assen = isset($_REQUEST['save_print']);



if ($is_saved_form) {
    $batch['is_saved'] = 1;
} else {
    $batch['is_saved'] = 0;
}

$table_batch = 'ci_batches';
$table_report = 'ci_reports';

$is_prefill_covid        = isset($_REQUEST['page']) && $_REQUEST['page'] == 'pcr_prefill_covid';
if (!$is_prefill_covid) {
    $is_prefill_covid        = isset($_POST['batch_tbl']) && $_POST['batch_tbl'] == 'ci_saved_batches';
}

if ($is_prefill_covid) {
    $table_batch = 'ci_saved_batches';
}


$specimen_tbl = 'ci_result_test_speciments';

$patient_signature = $_POST['patient_signature'];
$provider_signature = $_POST['provider_signature'];

if (isset($batch['assen'])) {
    $batch['assen'] = trim($batch['assen']);
}


if (isset($batch['doc']) && (empty($batch['doc']) || $batch['doc'] == 'auto')) {
    $batch['doc'] = date_i18n('Y-m-d H:i:s');
}

$mid = isset($batch['mid']) ? $batch['mid'] : 0;
$bat = null;
$bat_snapshot = array();


if (!isset($batch['mid']) || $batch['mid'] == 0) {

    //check for receiving auto create
    if (!empty($batch['assen']) && $table_batch == 'ci_batches') {
        $b_old = $wpdb->get_row("select * from ci_batches where assen = '{$batch['assen']}' and batch_status!='completed'");
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
    $bat_snapshot = $bat ? (array) $bat : array();
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
        $is_rejected = tr_pdd_get_batch_statuses($batch['batch_status'], true, true, 'tox');
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

if (!empty($pa['pa_first'])) {
    $pa['pa_first'] = str_replace('9275', '', $pa['pa_first']);
}
if (!empty($pa['pa_last'])) {
    $pa['pa_last'] = str_replace('9275', '', $pa['pa_last']);
}

if (is_array(@$lab) && !empty($lab['lab_dba_init'])) {
    if (@$lab['lab_ID'] > 0) {
        $batch['lab_id'] = $lab['lab_ID'];
        $batch['lab'] = $lab['lab_dba_init'];
    } else {
        $lab_id = $wpdb->get_var($wpdb->prepare("select lab_ID from ci_labs where lab_dba_init=%s", $lab['lab_dba_init']));
        if ($lab_id > 0) {
            $batch['lab_id'] = $lab_id;
            $lab['lab_ID'] = $batch['lab_id'];
        }
    }
}

if (empty($batch['lab_id']) && (!$bat || empty($bat->lab_id))) {
    if (@$batch['lab_id'] == 0 && $batch['cli_ID'] > 0) {
        $batch['lab_id'] = tr_get_lab_id_by_clinic($batch['cli_ID']);
        $lab['lab_ID'] = $batch['lab_id'];
    }

    if (empty($batch['lab_id'])) {
        $batch['lab_id'] = CI_Base_Lab::get_current_lab_id();
        $lab['lab_ID'] = $batch['lab_id'];
    }
}

if (empty($batch['assen']) && isset($_REQUEST['auto_generated'])) {
    $batch['assen'] = tr_get_new_assen('tox', false, '', $lab);
    $_POST['bat']['assenauto'] = $batch['assen'];
}


$batch['assen'] = CI_Base_Lab::replace_assen_by_lab($batch['assen'], $bat, $batch, $btype);

//check new clinic
if (isset($batch['requesting_practice']) && !empty($batch['requesting_practice']) && $batch['cli_ID'] == 0) {

    //check exists
    $batch['requesting_practice'] = trim(ucwords($batch['requesting_practice']));
    $cli_id = $wpdb->get_var("select cID from ci_clinic where cli_clinic='{$batch['requesting_practice']}' ");
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

if ($batch['cli_ID'] > 0) {
    $cli = $wpdb->get_row("select * from ci_clinic where cID = {$batch['cli_ID']}");
    $batch['requesting_practice'] = ucwords($cli->cli_clinic);


    if ($batch['ref_lab_ID'] == 0 && $cli->cli_ref_lab_ID > 0 && !tr_get_option('ci_pdf_data', 'disable_rlbcl', false)) {
        $batch['ref_lab_ID'] = $cli->cli_ref_lab_ID;
    }

    if (@$batch['lab_id'] == 0) {
        $batch['lab_id'] = tr_get_lab_id_by_clinic($batch['cli_ID']);
    }
}



if ($batch['lab_id'] > 0) {
    $batch['lab'] = tr_get_lab_short($batch['lab_id']);
}


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


$batch['assen'] = CI_Base_Lab::replace_assen_by_reflab($batch['assen'], $bat, $batch, 'tox');


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
} else if ($pa && !empty($pa['pa_dob'])) {
    //find patient exist
    $sql = $wpdb->prepare("select * from ci_patients WHERE pa_first=%s AND pa_last=%s AND pa_dob =%s ", $pa['pa_first'], $pa['pa_last'], $pa['pa_dob']);
    $patient = $wpdb->get_row($sql);
}
unset($pa['pa_ID']);
$patient_id = 0;

if ($batch['cli_ID'] > 0) {
    $pa['pa_clinic_ID'] = $batch['cli_ID'];
}

//fix phone
if (isset($pa['pa_phone'])) {
    $pa['pa_phone'] = str_replace(array(' ', '(', ')', '-', '.'), '', $pa['pa_phone']);
}


if (!@$patient) {
    if (count($pa) > 0) {
        $wpdb->insert('ci_patients', $pa);
        $patient_id = $wpdb->insert_id;
        $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id}");

        $batch['patient_name'] = trim($pa['pa_last'] . ', ' . $pa['pa_first']);
    }
} else {
    $patient_id = $patient->pa_ID;
    if (count($pa) > 0) {
        $changes = trpdd_update_patient($pa, $patient_id);
    }

    $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id}");
    $batch['patient_name'] = trim($patient->pa_last . ', ' . $patient->pa_first);
}
if ($patient_id > 0) {
    $batch['patient_id'] = $patient_id;
}

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
if (isset($_REQUEST['client_bill']) && !empty($_REQUEST['client_bill'])) {
    $batch['insurance'] = $_REQUEST['client_bill'];
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
    );
    $wpdb->insert('ci_collector', $data);
    $col['col_ID'] = $wpdb->insert_id;
    if ($col['col_ID'] > 0) {
        $wpdb->insert('ci_clinic_collector', array('col_ID' => $col['col_ID'], 'cli_ID' => $batch['cli_ID']));
    }
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

if (isset($_REQUEST['has_panel_requests']) || is_array($batch['panel_tests_requested'])) {
    if (isset($_REQUEST['testing_ordered'])) {
        //for test req form
        $testing_ordered = array();
        foreach ($_REQUEST['testing_ordered'] as $i => $vl) {
            if (is_array($_REQUEST['testing_ordered_name'][$i])) {
                $cvl = array();
                foreach ($_REQUEST['testing_ordered_name'][$i] as $j => $nvl) {
                    $cvl[] = @$_REQUEST['testing_ordered_add'][$i][$j] . '' . $nvl;
                }
                $cvl = ' ' . implode(' ', $cvl);
            } else {
                $cvl = '';
            }
            $testing_ordered[] = $vl . $cvl;
        }
        $batch['panel_tests_requested'] = implode(',', $testing_ordered);
    } else {
        $batch['panel_tests_requested'] = implode(',', (array)$batch['panel_tests_requested']);
    }

    $batch['panel_tests_requested'] = trpdd_panel_tests_requested($batch, $batch['doc_ID'], $batch['cli_ID']);
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
    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$batch['doc_ID']}", ARRAY_A);
    $batch['requesting_physician'] = tr_trim_name($doc['doc_last'] . ', ' . $doc['doc_first']);
    $batch['requesting_physician'] = ucwords($batch['requesting_physician']);

    $batch['physician_phone'] = $doc['doc_phone'];
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
        $batch['result_pdf'] = $result_pdf; //update approve_date
        $wpdb->update('ci_reports', array('approve_date' => date_i18n('Y-m-d H:i:s')), array('patient_id' => $batch['assen']));
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

    if ($rs !== false) {
        $changes = array_filter($batch, function ($v) {
            return isset($v);
        });
    }
} else {
    $batch['batch_modified'] = date_i18n('Y-m-d H:i:s');
    $rs = $wpdb->update($table_batch, $batch, array('mid' => $mid));
    if ($rs !== false) {
        $changes = array_merge($changes, tr_pdd_get_batch_changes($batch, $bat_snapshot));
    }
}


if ($rs === false) {
    Tr_Session_Class::add_error('Error REQUISITION FORM. ' . $wpdb->last_error);
} else {
    global $tr_footer_js_script;

    //update meta
    if (isset($_POST['batmeta']) && is_array($_POST['batmeta'])) {
        foreach ($_POST['batmeta'] as $k => $vl) {
            trpdd_update_batch_meta($mid, 'tox', $k, $vl);
        }
    }

    $has_screening = false;

    if ($need_print_assen) {
        $patient_name = $batch['patient_name'];
        if (!empty($batch['dob'])) {
            $patient_name .= '  ' . tr_pdd_display_date($batch['dob']);
        }
        $position   = $bat && $bat->position > 0 ? $bat->position : '';
        $doc        = tr_pdd_display_date($batch['doc'], 'm/d/Y H:i');
        $count_print = tr_get_option('ci_pdf_data', 'tox_print_times', 1);
        if ($count_print <= 0) $count_print = 1;

        $timeprint = 0;
        for ($i = 1; $i <= $count_print; $i++) {
            $timeprint += 1500;
            $tr_footer_js_script .= "\n" . 'setTimeout(function(){jQuery.tr_print_barcode("' . $batch['assen'] . '","' . $patient_name . '","' . $position . '","' . $doc . '")},' . $timeprint . ');' . "\n";
        }
    }
    if ($batch['mid'] > 0) {
        Tr_Session_Class::add('Updated REQUISITION FORM');

        if (!$is_saved_form && $_POST['saved_id'] > 0) {
            $redirect_url = Tr_Base_Class_V6::link(array('tab' => ''), false);
        }

        if (isset($batch['batch_id']) && $bat && empty($bat->batch_id)) {
            //log new batch id
            trpdd_log_user(get_current_user_id(), 'move_batch', $batch['batch_id'], $batch['mid'], 'blood', '', '', $batch['assen']);
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

    //update meta
    foreach ($batmeta as $metak => $metavl) {
        $metaexit = $wpdb->get_var("select mid from ci_batches_meta where mid = {$mid}
        and meta_key = '{$metak}'");
        if ($metaexit) {
            $wpdb->update('ci_batches_meta', array('meta_value' => $metavl), array('mid' => $mid, 'meta_key' => $metak));
        } else {
            $wpdb->insert('ci_batches_meta', array(
                'mid' => $mid,
                'meta_key' => $metak,
                'meta_value' => $metavl,
            ));
        }
    }

    //check result specimens
    if (isset($_REQUEST['data_specimen'])) {
        foreach ($_REQUEST['data_specimen'] as $k => $vl) {
            $update = array(
                'mid' => $mid,
                'testid' => $k,
            );
            if (isset($vl['conc'])) {
                $update['conc'] = $vl['conc'];
            }
            if (isset($vl['outcome'])) {
                $update['outcome'] = $vl['outcome'];
            }
            if (isset($vl['comments'])) {
                $update['comments'] = $vl['comments'];
            }
            if (isset($vl['checked'])) {
                $update['checked'] = $vl['checked'];
            }

            $exists = $wpdb->get_var("select mid from {$specimen_tbl} where mid = {$mid} and testid={$k} ");
            if ($exists) {
                $wpdb->update($specimen_tbl, $update, array('mid' => $mid, 'testid' => $k));
            } else if ((isset($update['conc']) && $update['conc'] !== '') || $update['checked'] || !empty($update['comments'])) {
                $wpdb->insert($specimen_tbl, $update);
            }
        }
    }

    if (isset($_REQUEST['data_specimen_screening'])) {
        foreach ($_REQUEST['data_specimen_screening'] as $k => $vl) {
            $update = array(
                'mid' => $mid,
                'testid' => $k,
            );
            if (isset($vl['conc'])) {
                $update['conc'] = $vl['conc'];
            }
            if (isset($vl['outcome'])) {
                $update['outcome'] = $vl['outcome'];
            }
            if (isset($vl['comments'])) {
                $update['comments'] = $vl['comments'];
            }
            if (isset($vl['checked'])) {
                $update['checked'] = $vl['checked'];

                if ($update['checked']) {
                    $has_screening = true;
                }
            }





            $exists = $wpdb->get_var("select mid from {$specimen_tbl} where mid = {$mid} and testid={$k} ");
            if ($exists) {
                $wpdb->update($specimen_tbl, $update, array('mid' => $mid, 'testid' => $k));
            } else if ((isset($update['conc']) && $update['conc'] !== '') || $update['checked'] || !empty($update['comments'])) {
                $wpdb->insert($specimen_tbl, $update);
            }
        }
    }

    //check group
    if (!$is_saved_form) {
        $update_report = array();

        $group_ids = array();
        $test_ids = array();
        $report_db = array();

        $has_manual_group = false;
        $tg_auto_batchid = false;
        $tg_auto_approve_pos_neg = false;

        foreach (explode(',', $batch['panel_tests_requested']) as $t) {
            if (stripos($t, 'group') !== false) {
                $group_ids[] = str_ireplace('group_', '', $t);
            } else if ($t) {
                $t = $wpdb->get_var("select te_ID from ci_test_panels where te_name = '{$t}' and te_type='tox' ");
                if ($t > 0) {
                    $test_ids[] = $t;
                }
            }
        }

        if (count($group_ids) > 0 || count($test_ids) > 0) {
            $group_ids_str  = implode(',', $group_ids);
            $test_ids       = implode(',', $test_ids);
            $has_manual_group = false;
            if (!empty($group_ids_str)) {
                $has_manual_group = $wpdb->get_var("select count(*) from ci_test_groups 
								where tg_type = 'tox' and tg_ID in ({$group_ids_str})
										and tg_manual_edit = 1
										") > 0;

                $tg_auto_batchid = $wpdb->get_var("select count(*) from ci_test_groups 
								where tg_type = 'tox' and tg_ID in ({$group_ids_str})
										and tg_auto_batchid = 1
										") > 0;

                $tg_auto_approve_pos_neg = $wpdb->get_var("select count(*) from ci_test_groups 
								where tg_type = 'tox' and tg_ID in ({$group_ids_str})
										and tg_auto_approve_pos_neg = 1
										") == count($group_ids);
            }

            if (!$has_manual_group && !empty($test_ids)) {
                $has_manual_group = $wpdb->get_var("select count(*) from ci_test_groups 
								where tg_type = 'tox' 
								and tg_ID in (select te_group_id from ci_test_panels where te_ID in ({$test_ids}))
								and tg_manual_edit = 1
										") > 0;
            }

            //if rejected
            $batch_status = isset($batch['batch_status']) ? $batch['batch_status'] : '';
            $bid = isset($batch['batch_id']) ? $batch['batch_id'] : '';
            if (empty($batch_status) && $bat) {
                $batch_status = $bat->batch_status;
            }
            if (empty($bid) && $bat) {
                $bid = $bat->batch_id;
            }
            if ($bid > 0 && !empty($batch_status) && tr_pdd_get_batch_statuses($batch_status, true, true, 'tox')) {
                $report_db = $wpdb->get_row("select * from ci_reports where patient_id = '{$batch['assen']}' ", ARRAY_A);
                $has_manual_group = true;
                $update_report['estatus'] = 'C';
                $update_report['final_results'] = '1';
                if (!$report_db || empty($report_db['approve_date'])) {
                    $update_report['approve_date'] = date_i18n('Y-m-d H:i:s');
                }
            }

            if (!empty($batch['result_pdf'])) {
                $has_manual_group = true;
            }
        }


        if (!$has_manual_group && $has_screening && tr_get_option('ci_urine_validity_settings', 'manual_edit_screening')) {
            $has_manual_group = true;
        }



        if ($has_manual_group) {
            $report_db = $wpdb->get_row("select * from ci_reports where patient_id = '{$batch['assen']}' ", ARRAY_A);

            if (!$report_db) {
                $wpdb->insert(
                    'ci_reports',
                    array(
                        'patient_id' => $batch['assen'],
                        'author_id' => get_current_user_id(),
                        'created' => date_i18n('Y-m-d H:i:s'),
                        'created_date' => date_i18n('Y-m-d'),
                    )
                );
                $report_db = array('id' => $wpdb->insert_id);
            }
        }

        //add reports
        $has_rs = false;
        if (isset($_POST['results']) && $report_db && $report_db['id'] > 0) {
            $has_pos = false;
            $all_pos_neg = true;

            foreach ($_POST['results'] as $k => $arr) {
                if (empty($arr['conc']) && empty($arr['pos-neg'])) {

                    continue;
                }
                $groupid = 0;
                $data_rs = array(
                    'conc' => $arr['conc'],
                    'pos-neg' => $arr['pos-neg'],
                    'comments' => $arr['comments'],
                    'report_id' => $report_db['id'],
                    'has_order' => 1
                );
                if (strpos($k, '_') > 0) {
                    list($testid, $drug, $groupid, $rsid) = explode('_', $k);
                    $data_rs['drug'] = $drug;
                    $data_rs['group_id'] = $groupid;
                } else if ($k > 0) {
                    $rsid = $k;
                } else {
                    $data_rs['drug'] = $k;
                    $rsid = $wpdb->get_var("select id from ci_results 
								where drug = '{$k}' and report_id = '{$report_db['id']}'");
                }

                if (in_array($groupid, $group_ids) && !in_array($arr['pos-neg'], array('POS', 'NEG'))) {
                    $all_pos_neg = false;
                }
                if ($arr['pos-neg'] == 'POS') {
                    $has_pos = true;
                }
                if ($rsid) {
                    $wpdb->update('ci_results', $data_rs, array('id' => $rsid));
                } else {
                    $rrr = $wpdb->insert('ci_results', $data_rs);
                }
                $has_rs = true;
            }

            if ($has_rs && $all_pos_neg && $tg_auto_approve_pos_neg && $report_db['estatus'] != 'C') {
                $update_report['estatus'] = 'C';
                $update_report['approve_date'] = date_i18n('Y-m-d H:i:s');

                $wpdb->update('ci_batches', array('batch_status' => 'completed'), array('mid' => $mid));
                trpdd_log_user(0, 'approve_auto_rs', get_current_user_id(), $mid, 'tox', '', '', $batch['assen']);

                trpdd_add_cron_after_approve('tox');

                trpdd_trigger_approve_report($report_db, 'tox');
            }

            if ($has_pos) {
                $update_report['has_pos'] = 1;
                $update['has_pos_modified'] = time();
            }
        }

        if (count($update_report) > 0) {
            $wpdb->update('ci_reports', $update_report, array('patient_id' => $batch['assen']));
        }


        if ($tg_auto_batchid && $has_rs) {
            $current_batch_id = tr_pdd_get_current_batch_id();
            $bid = $wpdb->get_var("select batch_id from {$table_batch} where mid = {$mid}");
            if (empty($bid) || strtoupper($bid) == 'NA') {
                $update_b = array('batch_id' => $current_batch_id);

                $wpdb->update($table_batch, $update_b, array('mid' => $mid));
            }
        }

        $nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
        if ($nextcron == 0 || $nextcron > time() + 300) {
            wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', array('tox'));
        }
    }


    if ($has_duplicate_assen) {
        Tr_Session_Class::add('Warning duplicate Accession #' . $batch['assen'] . ' Added!');
    }

    if ($ins1['ins_authorization'] == '1' && $mid > 0) {
        // $redirect_url = Tr_Base_Class_V6::link(array('tab' => 'prior_authorization', 'mid' => $mid), false);
    }

    $batch['is_saved_form'] = $is_saved_form;
    do_action('saved_req', 'tox', $mid, $batch, $pa);

    $nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
    if ($nextcron == 0 || $nextcron > time() + 300) {
        wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', array('tox'));
    }

    if (!$is_saved_form) {
        $user_action = 'save_req';
        if (count($changes) > 0) {
            $user_action .= '_cd';
        }
        trpdd_log_user(get_current_user_id(), $user_action, json_encode($changes), $mid, 'tox', '', '', $batch['assen']);
    }
}

$_POST['mid'] = $mid;

if ($bat) {
    $_POST['old_status'] = $bat->batch_status;
}
trpdd_log_form('save_tox');


if (isset($_POST['submit_next']) && isset($_POST['next_link'])) {
    $redirect_url = $_POST['next_link'];
}

if (isset($_POST['lab_form']) && isset($_REQUEST['ref'])) {

    $redirect_url = $_REQUEST['ref'];
}

if (!empty($redirect_url)) {
    wp_redirect($redirect_url);
}
