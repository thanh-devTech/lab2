<?php
global $wpdb;
global $tr_lat_lng_error;

wp_enqueue_script('wp-color-picker');
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('jquery-ui-sortable');
wp_enqueue_script('jquery-ui-autocomplete');



$formname = '';
$setting_key = $tab == 'concierge' ? 'trpatient_concierge_settings' : 'trpatient_product_settings';
if ($tab == 'meijer') {
    $setting_key = 'trpatient_meijer_settings';
    $formname = $tab;
}
if ($tab == 'canada') {
    $setting_key = 'trpatient_canada_settings';
    $formname = $tab;
}
if (stripos($tab, 'cform_') !== false) {
    $setting_key = 'trpatient_' . $tab . '_settings';
    $formname = str_replace('cform_', '', $tab);
}

$form_options = get_option($setting_key, array());

if (isset($_GET['trajax']) && $_GET['trajax'] == 'export_questions') {
    $json = array(
        'pa_questions' => $form_options['questions'],
    );
    header('Content-disposition: attachment; filename=pa_questions.json');
    header('Content-type: application/json');
    wp_send_json($json);
    exit;
}

$location_keys = array(
    'loc_S' => 'S', //school
    'loc_W' => 'W', //
    'loc_P' => 'P',
    'loc_H' => 'H'
);

$btypes     = array();
$all_btypes = tr_pdd_get_batch_types();


if (in_array('pcr', $all_btypes)) {

    $covid_group_id = $wpdb->get_var("select tg_ID 
from ci_test_groups where tg_type='pcr' 
and tg_show_req=1 and tg_is_covid = 1");

    $panels = $wpdb->get_results("select * from ci_test_groups where tg_type='pcr' and tg_show_req=1");
    foreach ($panels as $panel) {
        $btypes['pcr-G_' . $panel->tg_ID] = 'PCR - ' . $panel->tg_name;
        if (stripos($panel->tg_name, 'antigen') === false) {
            $btypes['pcr-G_' . $panel->tg_ID . '-Antigen'] = 'PCR - ' . $panel->tg_name . ' & Antigen';
        }

        if (stripos($panel->tg_name, 'flu') !== false && $covid_group_id > 0) {
            $btypes['pcr-G_' . $panel->tg_ID . '-' . $covid_group_id] = 'PCR - ' . $panel->tg_name . ' & Covid';
        }
    }

    $btypes['pcr-G_0'] = 'PCR - No Test';
    $btypes['pcr-G_orderable'] = 'PCR - Orderable Test';
    $btypes['pcr'] = 'PCR - SARS-CoV-2(old)';
    $btypes['pcr-UTI'] = 'PCR - UTI(old)';
    $btypes['pcr-Wound'] = 'PCR - Wound(old)';
    $btypes['pcr-Antigen'] = 'PCR - Antigen(old)';
    $btypes['pcr_antigen'] = 'PCR & Antigen(old)';
    $btypes['pcr-Repiratory'] = 'PCR - Repiratory(old)';
}


if (in_array('blood', $all_btypes)) {

    $panels = $wpdb->get_results("select * from ci_test_groups 
    where tg_type='blood' 
    and tg_show_req=1
    order by tg_name asc");
    foreach ($panels as $panel) {
        $btypes['blood-G_' . $panel->tg_ID] = 'Blood - ' . $panel->tg_name;

        if ($form_options['list_b_test'] && !$panel->tg_only_group && !$panel->tg_notestcheckbox) {
            $tests = $wpdb->get_results("select te_ID, te_name 
        from ci_test_panels 
        where te_group_id = {$panel->tg_ID}");
            foreach ($tests as $t) {
                $btypes['blood-GT_' . $t->te_ID] = 'Blood - ' . $panel->tg_name . ' : ' . $t->te_name;
            }
        }
    }

    $btypes['blood-G_0'] = 'Blood - No Test';
    $btypes['blood-G_orderable'] = 'Blood - Orderable Test';
    $btypes['blood-G_all'] = 'Blood - All Panels';
    $btypes['blood'] = 'Blood -  SARS-CoV-2 Antibody(old)';
}

if (in_array('tox', $all_btypes)) {
    $panels = $wpdb->get_results("select * from ci_test_groups where tg_type='tox' and tg_show_req=1");
    foreach ($panels as $panel) {
        $btypes['tox-G_' . $panel->tg_ID] = 'Tox - ' . $panel->tg_name;
    }

    $panels = $wpdb->get_results("select * from ci_test_groups where tg_type in ('scr')");
    foreach ($panels as $panel) {
        $btypes['tox-G_' . $panel->tg_ID] = 'Tox - ' . $panel->tg_name;
    }
}

foreach ($all_btypes as $bt) {
    if (!in_array($bt, array('blood', 'tox', 'pcr'))) {
        $panels = $wpdb->get_results("select * from ci_test_groups 
        where tg_type='{$bt}' 
        and tg_show_req=1
        order by tg_name asc");
        foreach ($panels as $panel) {
            $btypes[$bt.'-G_' . $panel->tg_ID] = tr_pdd_get_typename($bt).' - ' . $panel->tg_name;
        }
    }
}

$btypes['combo'] = 'Combo - All selected';


if (isset($_GET['fix_order'])) {
    $order_id = $_GET['fix_order'];
    trcpa_add_covid_order($order_id);
}

if (isset($_GET['fix_billing'])) {
    include(TRCPPR_PATH . 'inc/crons/sync_billing.php');
    echo 'billing';
    exit;
}

if (isset($_POST['pro_options']) || isset($_POST['admin_action'])) {
    $subtab = isset($_REQUEST['stab']) ? $_REQUEST['stab'] : '';
    $form_options = get_option($setting_key, array());

    if (!is_array($_POST['pro_options'])) {
        $_POST['pro_options'] = array();
    }

    $newoptions = tr_pdd_stripslashes_deep($_POST['pro_options']);

    if (!empty($subtab) && $subtab != 'product') {
        unset($newoptions['products']);
    }

    if ($newoptions['refcli_ID'] > 0) {
        foreach (tr_pdd_get_batch_types() as $bt) {
            $batch_tbl = tr_pdd_get_table_by_btype($bt);
            $wpdb->query("alter table {$batch_tbl} add column ref_cli_ID int(11)");
        }
    }

    if (isset($newoptions['show_patient_county_pregnant']) && $newoptions['show_patient_county_pregnant']) {
        $wpdb->suppress_errors(true);
        $wpdb->query("alter table ci_patients add column pa_pt_county text");
        $wpdb->query("alter table ci_patients add column pa_employed_in_healthcar varchar(3) default ''");
        $wpdb->query("alter table ci_patients add column pa_symptomatic varchar(3) default ''");
        $wpdb->query("alter table ci_patients add column pa_date_of_symptom date default ''");
        $wpdb->query("alter table ci_patients add column pa_hospitalized_in_icu varchar(3) default ''");
        $wpdb->query("alter table ci_patients add column pa_resident_in_congregate_care varchar(3) default ''");
        $wpdb->query("alter table ci_patients add column pa_pregnant varchar(3) default ''");

        $wpdb->query("alter table ci_patients_history add column pa_pt_county text");
        $wpdb->query("alter table ci_patients_history add column pa_employed_in_healthcar varchar(3) default ''");
        $wpdb->query("alter table ci_patients_history add column pa_symptomatic varchar(3) default ''");
        $wpdb->query("alter table ci_patients_history add column pa_date_of_symptom date default ''");
        $wpdb->query("alter table ci_patients_history add column pa_hospitalized_in_icu varchar(3) default ''");
        $wpdb->query("alter table ci_patients_history add column pa_resident_in_congregate_care varchar(3) default ''");
        $wpdb->query("alter table ci_patients_history add column pa_pregnant varchar(3) default ''");
    }


    if ($subtab == 'no-insurance' && empty($newoptions['products_noinsurance'])) {
        $newoptions['products_noinsurance'] = array();
    }

    if (isset($_POST['has_list_type']) && !empty($_POST['has_list_type']) && isset($form_options[$_POST['has_list_type']])) {
        $form_options[$_POST['has_list_type']] = array();
    }

    if (($newoptions['enable_location']
            || (!isset($newoptions['enable_location']) && $form_options['enable_location']))
        && isset($newoptions['locations'])
    ) {
        $old_latlng = array();
        foreach ($form_options['locations'] as $k => $location) {
            if (!empty($location['lat']) && !empty($location['lng'])) {
                $old_latlng[$location['address']] = array($location['lat'], $location['lng'], $location['state']);
            }
        }
        foreach ($newoptions['locations'] as $k => $location) {
            if ((empty($location['name']) || $location['name'] == 'auto') && $location['cli_ID'] > 0) {
                $clinic = $wpdb->get_row("select * from ci_clinic where cID = {$location['cli_ID']}");
                $location['name'] = $clinic->cli_clinic;
                $location['address'] = $clinic->cli_street . ', ' . $clinic->cli_city . ', ' . $clinic->cli_state . ' ' . $clinic->cli_zip;
            } else if ($location['address'] == 'auto' && $location['cli_ID'] > 0) {
                $clinic = $wpdb->get_row("select * from ci_clinic where cID = {$location['cli_ID']}");
                $location['name'] = $clinic->cli_clinic;
                $location['address'] = $clinic->cli_street . ', ' . $clinic->cli_city . ', ' . $clinic->cli_state . ' ' . $clinic->cli_zip;
            }
            if (isset($old_latlng[$location['address']])) {
                $latlng = $old_latlng[$location['address']];
                $location['lat'] = $latlng[0];
                $location['lng'] = $latlng[1];
                $location['state'] = $latlng[2];
            } else {
                $latlng = trpatient_get_Lat_LNG($location['address']);
                if ($latlng) {
                    $location['lat'] = $latlng['lat'];
                    $location['lng'] = $latlng['lng'];
                }
            }
            if (empty($location['state'])) {
                preg_match("/[,\s]+([a-zA-Z]{2})\s/", $location['address'], $matches);

                $location['state'] = isset($matches[1]) ? $matches[1] : $location['state'];
            }
            if (isset($location['desc'])) {
                $location['desc'] = stripslashes($location['desc']);
            }
            if (isset($location['name'])) {
                $location['name'] = stripslashes($location['name']);
            }
            $newoptions['locations'][$k] = $location;
        }
    }

    if (isset($_POST['has_test_tabs'])) {

        $newoptions['test_tabs'] = is_array($_POST['test_tabs']) ? $_POST['test_tabs'] : array();
        foreach ($newoptions['test_tabs'] as $i => $arr) {
            if (empty($arr['value'])) {
                unset($newoptions['test_tabs'][$i]);
            } else {
                $newoptions['test_tabs'][$i]['value'] = trim(str_replace(' ', '', $arr['value']));
            }
        }
    }

    //auto products
    if (isset($newoptions['products'])) {
        foreach ($newoptions['products'] as $k => $pro) {
            if ($pro['ID'] == 'auto') {
                $postarr = array(
                    'post_title' => $btypes[$pro['type']],
                    'post_type' => 'product',
                    'post_status' => 'publish',
                );
                $prid = wp_insert_post($postarr);
                $newoptions['products'][$k]['ID'] = $prid;
            }
        }
    }

    $form_options = array_merge($form_options, $newoptions);

    if (isset($form_options['locations'])) {

        foreach ($form_options['locations'] as $k => $arr) {
            if (empty($arr['name']) && empty($arr['cli_ID'])) {
                unset($form_options['locations'][$k]);
            } else if (empty($arr['key'])) {
                $form_options['locations'][$k]['key'] = sanitize_title($arr['name']);
            }
        }
    }


    if ($form_options['show_patient_passport']) {
        $wpdb->query("alter table ci_patients add column pa_passport varchar(100);");
    }

    if ($form_options['show_quesion_interested']) {
        $wpdb->query("alter table ci_patients add column `pa_interested_research` varchar(1) default ''");
        $wpdb->query("alter table ci_patients_history add column `pa_interested_research` varchar(1) default ''");
    }
    if ($form_options['show_patient_sport']) {
        $wpdb->query("alter table ci_patients add column `pa_sport` text");
        $wpdb->query("alter table ci_patients_history add column `pa_sport` text");
    }

    if (isset($_POST['logo'])) {
        $form_options['logo'] = $_POST['logo'];
    }


    update_option($setting_key, $form_options);

    trpdd_log_form('patient_product');
    Tr_Session_Class::add('Saved Successfully!');
}




$form_options = get_option($setting_key, array());


$sub_tabs = array(
    'main' => 'Main',
    'style' => 'Style',
    'text' => 'Text',
    'questions' => 'Questions',
);


if (isset($form_options['enable_groups']) && $form_options['enable_groups']) {
    $sub_tabs['listgroups'] = 'Groups';
    $sub_tabs['limitscheduling'] = 'Limit';
} else if (isset($form_options['enable_sctest']) && $form_options['enable_sctest']) {
    $sub_tabs['limitscheduling'] = 'Limit';
}

if (isset($form_options['enable_symptoms']) && $form_options['enable_symptoms']) {
    $sub_tabs['symptoms'] = 'Symptoms';
}

if (isset($form_options['enable_discounttest']) && $form_options['enable_discounttest']) {
    $sub_tabs['discount'] = 'Discount';
}

if ($tab != 'register_page') {
    $sub_tabs['notify'] = 'Notify';
}

$sub_tabs['product'] = 'Pay Products';
$sub_tabs['insurance'] = 'Insurance Products';
$sub_tabs['no-insurance'] = 'No-Insurance Products';
$sub_tabs['addon'] = 'Add-on Products';
$sub_tabs['location_opt'] = 'Location Options';
$sub_tabs['locations'] = 'Locations';

$subtab = isset($_REQUEST['stab']) ? $_REQUEST['stab'] : key($sub_tabs);
if (!isset($sub_tabs[$subtab])) {
    $subtab = key($sub_tabs);
}




$clinics = $doctors = array('' => 'Select');
// get clinic
$clinics_qr = $wpdb->get_results("select cID, cli_clinic from ci_clinic 
	                        where cli_status = 1  
	                        order by cli_clinic asc");


foreach ($clinics_qr as $value) {
    $clinics[$value->cID] = $value->cli_clinic;
}

// get docters
$doctors_qr = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        doc.doc_npi,
	                                        cli.cID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            join ci_clinic cli ON cli.cID = cd.cli_ID
	                            order by doc.doc_last asc, doc.doc_first asc", ARRAY_A);

foreach ($doctors_qr as $value) {
    $doctors[$value['doc_ID']] = $value['doc_last'] . ', ' . $value['doc_first'] . ' (' . $value['doc_npi'] . ')';
}

$pcrTypesRaw = $wpdb->get_results("select ts_name from ci_test_specimens where ts_type= 'pcr'", ARRAY_A);

$pcrTypes = array();
foreach ($pcrTypesRaw as $value) {
    $pcrTypes[$value['ts_name']] = $value['ts_name'];
}

$products = query_posts(array('post_type' => 'product', 'posts_per_page' => -1));
$product_options = array('' => 'Select');
foreach ($products as $product) {
    $category = '';
    $terms = get_the_terms($product, 'product_cat');
    foreach ($terms as $term) {
        if ($term->name == 'Uncategorized') {
            $category = 'Base';
        } else {
            $category = $term->name;
            break;
        }
    }
    $title = $product->post_title . ' (' . $product->_regular_price . '$)';
    if (!empty($category)) {
        if (!isset($product_options[$category])) {
            $product_options[$category] = array('group_name' => $category);
        }
        $product_options[$category][$product->ID] = $title;
    } else {
        $product_options[$product->ID] = $title;
    }
}

if (isset($_GET['debug'])) {
    $product_options['auto'] = 'Auto Create';
}

$max_number = 0;

if (empty($form_options['type_of_specimen']) && empty($form_options['products'])) {
    $form_options['type_of_specimen'] = 'Nasopharyngeal Swab';
    $df_patient_settings = get_option('trpatient_product_settings');
    $df_patient_settings['locations'] = array();
    if ($tab == 'concierge') {
        update_option('trpatient_concierge_settings', $df_patient_settings);
    } else if ($tab == 'meijer') {
        update_option('trpatient_meijer_settings', $df_patient_settings);
    } else if ($tab == 'canada') {
        update_option('trpatient_canada_settings', $df_patient_settings);
    } else if (stripos($tab, 'cform_') !== false) {
        update_option($setting_key, $df_patient_settings);
    }
}

if ($tab == 'concierge' && isset($_REQUEST['removelocs'])) {
    $form_options['locations'] = array();
    update_option('trpatient_concierge_settings', $form_options);
} else if ($tab == 'canada' && isset($_REQUEST['removelocs'])) {
    $form_options['locations'] = array();
    update_option('trpatient_canada_settings', $form_options);
}

if (isset($_GET['restore'])) {
    $id = $_GET['restore'];

    $data = $wpdb->get_var("select data from ci_log_forms where id = {$id} ");
    $data = json_decode($data, true);
    $data = $data['pro_options'];
    if (isset($data['products']) && count($data['products']) > 0) {
        $form_options['products'] = $data['products'];
    }
    var_dump($data);
}

if (!is_array($form_options['test_tabs'])) $form_options['test_tabs'] = array();
?>
<div class="wrap">
    <h2 class="nav-tab-wrapper tabs2">
        <?php
        foreach ($sub_tabs as $k => $txt) {
            $class = $subtab == $k ? 'nav-tab-active' : '';
            $link = Tr_Base_Class_V4::link(array('stab' => $k, 'act' => '', 'id' => '', 'batch_id' => '', 'view' => '', 's' => '', 'search' => '', 'paged' => '', 'from' => '', 'to' => '', 'summary' => '', 'orderby' => '', 'order' => '', 'assen' => '', 'status' => ''), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
</div>
<?php
?>
<div class="wrap">
    <?php Tr_Session_Class::show() ?>

    <?php
    $this->form(array('validate' => false, 'data' => $form_options), 'pro_options');


    if ($subtab == 'main') {

        $questions_rs  = $wpdb->get_results("select * from ci_test_aoe_headers where tag_btype='pcr' order by tag_ID asc");
        $questions = array('' => 'Select');
        foreach ($questions_rs as $r) {
            $questions[$r->tag_ID] = $r->tag_header;
        }

        echo '<div class="frow">';
        echo '<div class="col2">';
        echo '<div class="block">Shortcode: [ci_registration_tests ' . (!empty($formname) ? 'form="' . $formname . '"' : '') . ']</div>';

        $pa_options['client_pageids'] = is_array($pa_options['client_pageids']) ? $pa_options['client_pageids'] : array();
        $postid = isset($pa_options['client_pageids'][$formname]) ? $pa_options['client_pageids'][$formname] : 0;
        if ($postid > 0) {
            echo '<div class=" block"><a href="' . get_permalink($postid) . '">' . $formname . ' Page</a></div>';
            echo '</div>';
            echo '<div class="col2">';
            include(TRCPPR_PATH . 'pages/metabox_generateqr.php');
            echo '<style>.pageqr{max-width:70px !important}</style>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="frow"><h4>&nbsp;</h4></div>';


        $this->input('logo', array('type' => 'image', 'label' => 'Logo', 'id' => 'pro_options[logo]'));

        $this->input('flatfee', array('type' => 'select', 'options' => $product_options, 'label' => 'Flat Fee'));
        $this->input('cli_ID', array('type' => 'select', 'options' => $clinics, 'label' => 'Clinic'));

        $this->input('doc_ID', array('type' => 'select', 'options' => $doctors, 'label' => 'Doctor'));
        $this->input('type_of_specimen', array('type' => 'text', 'label' => 'Default Specimen Type for PCR'));
        $this->input('enable_scheduling', array('type' => 'checkbox', 'label' => 'Enable Scheduling', 'labelcb' => true));
        $this->input('enable_doc_picker', array('type' => 'checkbox', 'label' => 'Enable DOC datepicker', 'labelcb' => true));
        $this->input('disable_payment', array('type' => 'checkbox', 'label' => 'Disable Payment', 'labelcb' => true));


        $this->input('show_patient_fvaccine', array('type' => 'checkbox', 'label' => 'Enable Patient "Are you fully Vaccinated?"', 'labelcb' => true));
        $this->input('show_patient_passport', array('type' => 'checkbox', 'label' => 'Enable Patient Passport', 'labelcb' => true));
        $this->input('required_patient_passport', array('type' => 'checkbox', 'label' => 'Enable Patient Passport Required', 'labelcb' => true));
        $this->input('show_patient_sport', array('type' => 'checkbox', 'label' => 'Enable Patient Sports', 'labelcb' => true));
        $this->input('phone_not_required', array('type' => 'checkbox', 'label' => 'Phone Not required', 'labelcb' => true));
        $this->input('show_patient_race', array('type' => 'checkbox', 'label' => 'Enable Patient Race', 'labelcb' => true));
        $this->input('show_patient_eth', array('type' => 'checkbox', 'label' => 'Enable Patient Ethnicity', 'labelcb' => true));
        $this->input('show_marital_status', array('type' => 'checkbox', 'label' => 'Enable Patient Marital Status', 'labelcb' => true));
        $this->input('show_patient_sexgen', array('type' => 'checkbox', 'label' => 'Enable Patient "Sexual Orientation, Gender Identity"', 'labelcb' => true));
        $this->input('show_patient_county_pregnant', array('type' => 'checkbox', 'label' => 'Enable Patient County...Pregnant', 'labelcb' => true));

        $this->input('required_patient_mid', array('type' => 'checkbox', 'label' => 'Enable Required Middle Initial', 'labelcb' => true));
        $this->input('show_patient_multi', array('type' => 'checkbox', 'label' => 'Enable Multi Patient', 'labelcb' => true));

        $this->input('hide_patient_passport', array('type' => 'checkbox', 'label' => 'Disable Patient SSN', 'labelcb' => true));
        $this->input('allow_empty_ssn', array('type' => 'checkbox', 'label' => 'Allow empty SSN', 'labelcb' => true));
        $this->input('show_quesion_interested', array('type' => 'checkbox', 'label' => 'Enable Are you interested in being contacted regarding a COVID research study?', 'labelcb' => true));
        $this->input('invertinsucash', array('type' => 'checkbox', 'label' => 'Invert Column Insurance & Cash', 'labelcb' => true));
        $this->input('invertinsunoinsu', array('type' => 'checkbox', 'label' => 'Invert Rows Insurance & No-Insurance', 'labelcb' => true));
        $this->input('allow_select_pay_ins', array('type' => 'checkbox', 'label' => 'Allow Select Insurance & Pay same time', 'labelcb' => true));
        $this->input('ins_dropdown', array('type' => 'checkbox', 'label' => 'Display Insurance Company as dropdown', 'labelcb' => true));
        $this->input('only_one_per_group', array('type' => 'checkbox', 'label' => 'Only select One type Per group', 'labelcb' => true));

        $this->input('enable_insurance_photo', array('type' => 'checkbox', 'label' => 'Enable Insurance Photo ID', 'labelcb' => true));
        $this->input('enable_pay_ins_photo', array('type' => 'checkbox', 'label' => 'Enable Pay Photo ID', 'labelcb' => true));
        $this->input('ins_group_required', array('type' => 'checkbox', 'label' => 'Insurance  Group# Required', 'labelcb' => true));
        $this->input('add_or_cc_ins', array('type' => 'checkbox', 'label' => 'Add "Or" beween CC & Insurance in Mobile', 'labelcb' => true));
        $this->input('show_reason_testing', array('type' => 'checkbox', 'label' => 'Show "Reason for Testing" tab', 'labelcb' => true));
        $this->input('reason_testing', array('type' => 'text', 'label' => 'Reason for Testing text', 'labelcb' => true));
        $this->input('show_medical_history', array('type' => 'checkbox', 'label' => 'Show "Medical History" tab', 'labelcb' => true));
        $this->input('disable_select_tests', array('type' => 'checkbox', 'label' => 'Disable Select Tests', 'labelcb' => true));

        if (isset($_GET['debug'])) {
            echo '<tr><td colspan=2" style="text-align:center">Debug only for admin</td></tr>';
            $this->input('refcli_ID', array('type' => 'select', 'options' => $clinics, 'label' => 'Ref Clinic'));
            $this->input('enable_groups', array('type' => 'checkbox', 'label' => 'Enable Group', 'labelcb' => true));
            $this->input('enable_sctest', array('type' => 'checkbox', 'label' => 'Enable Scheduling by test', 'labelcb' => true));
            $this->input('enable_discounttest', array('type' => 'checkbox', 'label' => 'Enable Discount by test', 'labelcb' => true));
            $this->input('enable_symptoms', array('type' => 'checkbox', 'label' => 'Enable Symptoms', 'labelcb' => true));
            $this->input('list_b_test', array('type' => 'checkbox', 'label' => 'Enable List Test in type dropdown', 'labelcb' => true));
            $this->input('test_tabs', array('type' => 'box', 'label' => 'Adding Tabs'));
            echo '<input type="hidden" name="has_test_tabs" value="1"/>';
        }
    } else if ($subtab == 'style') {

        if (isset($form_options['test_tabs']) && count($form_options['test_tabs']) > 0) {
            $this->input('acc_bg_color_main', array('label' => (!empty($form_options['selectteststab']) ? $form_options['selectteststab'] : 'first tab') . ' Background'));
            foreach ($form_options['test_tabs'] as $tabarr) {
                $this->input('acc_bg_color_' . $tabarr['value'], array('label' => $tabarr['title'] . ' Background'));
            }
        }
        $this->input('acc_bg_color', array('label' => 'Accordion Background'));
        $this->input('acc_bg_hover_color', array('label' => 'Accordion Hover Background'));
        $this->input('acc_text_color', array('label' => 'Accordion Title Color'));
        $this->input('acc_text_hover_color', array('label' => 'Accordion Title Hover Color'));
        $this->input('acc_content_color', array('label' => 'Accordion Content Color'));
    } else if ($subtab == 'text') {
        $this->input('selectteststab', array('type' => 'text', 'label' => '"Select Tests" tab text'));
        $this->input('insurance_text', array('type' => 'text', 'label' => 'Insurance Free Text in checkout'));
        $this->input('insu_textac', array('type' => 'textarea', 'label' => 'Insurance Text Above cards buttons'));
        $this->input('insu_cbtsyellow', array('type' => 'checkbox', 'label' => 'Cards buttons in Yellow', 'labelcb' => true));
        $this->input('success_msg', array('type' => 'text', 'label' => 'Success Message'));
        $this->input('purchase_more_text', array('type' => 'text', 'label' => 'Purchase More Text'));

        $this->input('text_st', array('type' => 'textarea', 'label' => 'Text Below "Select Tests"'));
        $this->input('text_loc', array('type' => 'textarea', 'label' => 'Text Below "Testing Location"'));
        $this->input('text_apatient', array('type' => 'textarea', 'label' => 'Text Above "Patient"'));
        $this->input('text_bpatient', array('type' => 'textarea', 'label' => 'Text Below "Patient"'));
        $this->input('text_btime', array('type' => 'textarea', 'label' => 'Text Above "Appointment Time"'));
        $this->input('text_bltime', array('type' => 'textarea', 'label' => 'Text Below "Appointment Time"'));
        $this->input('text_paym', array('type' => 'textarea', 'label' => 'Text Below "Payment"'));
    } else if ($subtab == 'listgroups') {
        $groupkey = 'listgroups';
    ?>

        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Groups</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_group_list" value="<?php echo $groupkey ?>" />
                <table id="productsadding" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:10% !important;">Group Slug</th>
                            <th style="width:45% !important;">Group Text</th>
                            <th style="width:33% !important;">Main Tests</th>
                            <th style="width:18% !important;">Option Tests</th>
                            <th style="width:80px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        if (empty($form_options[$groupkey])) {
                            $form_options[$groupkey] = array(array());
                        }

                        foreach ($form_options[$groupkey] as $pr) {
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <input type="text" name="pro_options[<?php echo $groupkey ?>][<?php echo $i ?>][key]" value="<?php echo $pr['key'] ?>" />
                                </td>
                                <td>
                                    <textarea class="hoversize" name="pro_options[<?php echo $groupkey ?>][<?php echo $i ?>][text]"><?php echo $pr['text'] ?></textarea>
                                </td>
                                <td>
                                    <select name="pro_options[<?php echo $groupkey ?>][<?php echo $i ?>][tests][]" class="select2" multiple>
                                        <?php
                                        foreach ($form_options['products'] as $te) {
                                            $prname = '';
                                            foreach ($product_options as $pid => $product_option) {
                                                if (is_array($product_option)) {
                                                    foreach ($product_option as $pid2 => $pname2) {
                                                        if ($te['ID'] == $pid2) {
                                                            $prname = $pname2;
                                                            break;
                                                        }
                                                    }
                                                } else if ($te['ID'] == $pid) {
                                                    $prname = $product_option;
                                                    break;
                                                }
                                            }
                                            $selected = in_array($te['ID'], $pr['tests']) ? 'selected' : '';
                                            echo '<option value="' . $te['ID'] . '" ' . $selected . '>' . $prname . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="pro_options[<?php echo $groupkey ?>][<?php echo $i ?>][testsopt][]" class="select2" multiple>
                                        <?php
                                        foreach ($form_options['products'] as $te) {
                                            $prname = '';
                                            foreach ($product_options as $pid => $product_option) {
                                                if (is_array($product_option)) {
                                                    foreach ($product_option as $pid2 => $pname2) {
                                                        if ($te['ID'] == $pid2) {
                                                            $prname = $pname2;
                                                            break;
                                                        }
                                                    }
                                                } else if ($te['ID'] == $pid) {
                                                    $prname = $product_option;
                                                    break;
                                                }
                                            }
                                            $selected = in_array($te['ID'], $pr['testsopt']) ? 'selected' : '';
                                            echo '<option value="' . $te['ID'] . '" ' . $selected . '>' . $prname . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>

                                            <div class="frow">
                                                <label>Description</label>
                                                <textarea name="pro_options[<?php echo $groupkey ?>][<?php echo $i ?>][desc]" style="height:400px"><?php echo $pr['desc'] ?></textarea>
                                            </div>
                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>

    <?php

        $this->input('group_passportimg', array('type' => 'checkbox', 'label' => 'Enable Upload Passport Image', 'labelcb' => true));
    } else if ($subtab == 'limitscheduling') {
        $schedulingkey = 'limitscheduling';
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Limit Scheduling</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_group_list" value="<?php echo $schedulingkey ?>" />
                <table id="productsadding" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:10% !important;">Scheduling Key</th>
                            <th style="width:45% !important;">Week dates</th>
                            <th style="width:33% !important;">Limit from #1(days)</th>
                            <th style="width:80px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        if (empty($form_options[$schedulingkey])) {
                            $form_options[$schedulingkey] = array(array());
                        }

                        foreach ($form_options[$schedulingkey] as $pr) {
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][key]" value="<?php echo $pr['key'] ?>" />
                                </td>
                                <td>
                                    <select name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][dweeks][]" multiple class="select2">
                                        <?php
                                        $weekdates = array(1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun');
                                        foreach ($weekdates as $dk => $dname) {
                                            $selected = in_array($dk, $pr['dweeks']) ? 'selected' : '';
                                            echo '<option value="' . $dk . '" ' . $selected . '>' . $dname . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][lm1]" value="<?php echo $pr['lm1'] ?>" />
                                </td>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>

                                            <div class="frow">
                                                <label>Adding product:</label>
                                                <select name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][prad]">
                                                    <?php
                                                    foreach ($product_options as $pid => $pname) {

                                                        if (is_array($pname)) {
                                                            echo '<optgroup label="' . $pname['group_name'] . '">';
                                                            foreach ($pname as $ic => $vl) {
                                                                if ($ic == 'group_name') continue;
                                                                $selected = $ic == $pr['prad'] ? 'selected' : '';
                                                                echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                            }
                                                            echo '</optgroup>';
                                                        } else {
                                                            $selected = $pid == $pr['prad'] ? 'selected' : '';
                                                            echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="frow">
                                                <label>Adding Product Limit days:</label>
                                                <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][pradlm]" value="<?php echo $pr['pradlm'] ?>" />
                                            </div>
                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>
    <?php
    } else if ($subtab == 'discount') {
        $schedulingkey = 'discount';
        $pids = array();
        foreach ($form_options['products'] as $pr) {
            $pids[] = $pr['ID'];
        }
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Discount</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_group_list" value="<?php echo $schedulingkey ?>" />
                <table id="productsadding" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:40% !important;">Products</th>
                            <th style="width:25% !important;">Title</th>
                            <th style="width:10% !important;">Amount</th>
                            <th style="width:80px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        if (empty($form_options[$schedulingkey])) {
                            $form_options[$schedulingkey] = array(array());
                        }

                        foreach ($form_options[$schedulingkey] as $pr) {
                            if (!is_array($pr['products'])) {
                                $pr['products'] = array();
                            }
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <select name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][products][]" multiple class="select2">
                                        <?php
                                        foreach ($product_options as $pid => $pname) {

                                            if (is_array($pname)) {
                                                ob_start();
                                                $hasitems = false;
                                                foreach ($pname as $ic => $vl) {
                                                    if ($ic == 'group_name' || !in_array($ic, $pids)) continue;
                                                    $selected = in_array($ic, $pr['products']) ? 'selected' : '';
                                                    echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                    $hasitems = true;
                                                }
                                                $contentsub = ob_get_clean();
                                                if ($hasitems) {
                                                    echo '<optgroup label="' . $pname['group_name'] . '">';
                                                    echo $contentsub;
                                                    echo '</optgroup>';
                                                }
                                            } else if (in_array($ic, $pids)) {
                                                $selected = in_array($pid, $pr['products']) ? 'selected' : '';
                                                echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][title]" value="<?php echo trim($pr['title']) ?>" />
                                </td>
                                <td>
                                    <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][amount]" value="<?php echo $pr['amount'] ?>" />
                                </td>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>

                                            <div class="wrapmoreform">
                                                <div class="row">
                                                    <label>Text Below</label>
                                                    <textarea name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][desc]"><?php echo $pr['desc'] ?></textarea>
                                                </div>
                                            </div>

                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>
    <?php
    } else if ($subtab == 'symptoms') {

        $schedulingkey = 'symptomslist';
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Symtoms</h3>
            </th>
        </tr>
        <?php
        $this->input('en_symptoms', array('type' => 'checkbox', 'label' => 'Enable', 'labelcb' => true));
        $this->input('symptoms_allc', array('type' => 'text', 'label' => 'Diagnostic Adding while All Checked'));
        $this->input('symptoms_noc', array('type' => 'text', 'label' => 'Diagnostic No checked'));
        ?>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_group_list" value="<?php echo $schedulingkey ?>" />
                <table id="symtoms" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:50% !important;">Symtoms</th>
                            <th style="width:40% !important;">diagnostic Code</th>
                            <th style="width:10% !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        if (empty($form_options[$schedulingkey])) {
                            $form_options[$schedulingkey] = array(array());
                        }

                        foreach ($form_options[$schedulingkey] as $pr) {

                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <input type="text" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][title]" value="<?php echo trim($pr['title']) ?>" />
                                </td>
                                <td>
                                    <input type="text" class="diagnostic_code" name="pro_options[<?php echo $schedulingkey ?>][<?php echo $i ?>][code]" value="<?php echo $pr['code'] ?>" />
                                </td>
                                <td class="bactions">
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>

    <?php
    } else if ($subtab == 'notify') {
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Override Main Notify</h3>
            </th>
        </tr>
    <?php
        $this->input('new_order_email_msg', array(
            'type' => 'textarea',
            'label' => 'New Order Email Message',
            'desc' => 'Shortcodes: <span>{accession}</span>  &nbsp;&nbsp;
                                                      {patient name} &nbsp;&nbsp;&nbsp;
                                                      {patient_dob} &nbsp;&nbsp;&nbsp;
                                                      {patient_email} &nbsp;&nbsp;&nbsp;
                                                      {datetime} => M d @h:iA  &nbsp;&nbsp;&nbsp;
                                                      {datetime2} => m/d/y H:i 
                                                      <br><span>{location}</span>&nbsp;&nbsp;
                                                      <span>{location_address}</span>  &nbsp;&nbsp;
                                                      <span>{description}</span>&nbsp;&nbsp;
                                                      {lab name} &nbsp;&nbsp;
                                                      {payment_status} &nbsp;&nbsp;
                                                      '
        ));
    } else if ($subtab == 'questions') {
        $types = array(
            'select' => 'Dropdown',
            'mselect' => 'Dropdown Multi',
            'text' => 'Text',
            'textu' => 'Text with Unit',
            'texta' => 'TextArea',
            'date' => 'Date',
            'radio' => 'Radio',
            'checkbox' => 'Checkbox',
            'mcheckbox' => 'Multi Checkbox',
            'mtext' => 'Multi Text box',
        );

        $this->input('enable_questions', array('type' => 'checkbox', 'label' => 'Enable Questions', 'labelcb' => true));
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Questions</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_list_type" value="questions" />
                <table id="products" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:90px!important">Key</th>
                            <th style=" width:40%!important">Question</th>
                            <th style="width:100px!important">Type</th>
                            <th style="width:60px!important">Columns</th>
                            <th>Options</th>
                            <th style="width:50px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;

                        if (empty($form_options['questions'])) {
                            $form_options['questions'] = array(array());
                        }
                        foreach ($form_options['questions'] as $pr) {
                            $i++;
                            if (empty($pr['key'])) {
                                $pr['key']  =  substr(preg_replace("/[^0-9a-z]/", '', strtolower($pr['question'])), 0, 30);
                            } else {
                                $pr['key'] = substr($pr['key'], 0, 30);
                            }
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <input type="text" name="pro_options[questions][<?php echo $i ?>][key]" value="<?php echo $pr['key'] ?>" maxlength="30" style="width:90px" />
                                </td>
                                <td>
                                    <input type="text" name="pro_options[questions][<?php echo $i ?>][question]" value="<?php echo $pr['question'] ?>" required />
                                </td>
                                <td>
                                    <select name="pro_options[questions][<?php echo $i ?>][type]" required>
                                        <?php

                                        foreach ($types as $k => $txt) {
                                            $selected = $k == $pr['type'] ? 'selected' : '';

                                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="pro_options[questions][<?php echo $i ?>][cols]" value="<?php echo $pr['cols'] ?>" style="width:60px" />
                                </td>
                                <td>
                                    <textarea class="hoversize" name="pro_options[questions][<?php echo $i ?>][options]"><?php echo $pr['options'] ?></textarea>
                                </td>
                                <td class="bactions">

                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                    <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'export_questions')) ?>">Export</a>
                </div>
            </td>
        </tr>

    <?php
    } else if ($subtab == 'product') {
        $labs = $wpdb->get_results("select lab_ID, lab_name from ci_labs where lab_is_ref = 0 order by lab_ID asc");

        if (count($form_options['test_tabs']) > 0 && isset($_GET['do_sort'])) {
            $tabs_list = array();

            foreach ($form_options['products'] as $i => $pr) {
                if (!isset($tabs_list[$pr['tabkey']])) {
                    $tabs_list[$pr['tabkey']] = array();
                }

                $tabs_list[$pr['tabkey']][] = $pr;
            }

            $form_options['products'] = array();
            foreach ($tabs_list as $t => $prs) {
                $form_options['products'] = array_merge($form_options['products'], $prs);
            }
        }
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Pay Products</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_list_type" value="products" />
                <table id="products" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:45%">Product Name</th>
                            <th style="width:100px">Type</th>
                            <th>Header</th>
                            <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                <th>Location</th>
                            <?php } ?>
                            <?php if (count($form_options['test_tabs']) > 0) { ?>
                                <th>Tab</th>
                            <?php } ?>
                            <th style="width:50px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        if (empty($form_options['products'])) {
                            $form_options['products'] = array(array());
                        }
                        foreach ($form_options['products'] as $pr) {
                            if (!is_array($pr['locs'])) {
                                $pr['locs'] = array();
                            }
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <select name="pro_options[products][<?php echo $i ?>][ID]" required>
                                        <?php
                                        foreach ($product_options as $pid => $pname) {

                                            if (is_array($pname)) {
                                                echo '<optgroup label="' . $pname['group_name'] . '">';
                                                foreach ($pname as $ic => $vl) {
                                                    if ($ic == 'group_name') continue;
                                                    $selected = $ic == $pr['ID'] ? 'selected' : '';
                                                    echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                }
                                                echo '</optgroup>';
                                            } else {
                                                $selected = $pid == $pr['ID'] ? 'selected' : '';
                                                echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="pro_options[products][<?php echo $i ?>][type]" required>
                                        <?php

                                        foreach ($btypes as $btype => $txt) {
                                            $selected = $btype == $pr['type'] ? 'selected' : '';
                                            if (!$selected && stripos($txt, '(old)') > 0) {
                                                continue;
                                            }
                                            echo '<option value="' . $btype . '" ' . $selected . '>' . $txt . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <textarea class="hoversize" name="pro_options[products][<?php echo $i ?>][header]"><?php echo $pr['header'] ?></textarea>
                                </td>
                                <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                    <td>
                                        <select name="pro_options[products][<?php echo $i ?>][locs][]" class="select2" multiple>
                                            <?php
                                            foreach ($form_options['locations'] as $location) {
                                                $selected = in_array($location['key'], $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $location['key'] . '" ' . $selected . '>' . $location['name'] . '</option>';
                                            }

                                            foreach ($location_keys as $lk => $lv) {
                                                $selected = in_array($lk, $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $lk . '" ' . $selected . '>' . $lv . ' Group</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                <?php } ?>
                                <?php if (count($form_options['test_tabs']) > 0) {
                                ?>
                                    <td>
                                        <select name="pro_options[products][<?php echo $i ?>][tabkey]">
                                            <?php
                                            echo '<option value="">' . (!empty($form_options['selectteststab']) ? $form_options['selectteststab'] : 'first tab') . '</option>';
                                            foreach ($form_options['test_tabs'] as $tabarr) {
                                                $tabarr['value'] = strtolower($tabarr['value']);
                                                $selected = $tabarr['value'] == $pr['tabkey'] ? 'selected' : '';
                                                echo '<option value="' . $tabarr['value'] . '" ' . $selected . '>' . $tabarr['title'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                <?php
                                }
                                ?>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>

                                            <div class="wrapmoreform">
                                                <div class="row">
                                                    <label>Clinic: </label>
                                                    <select class="location_clinic" name="pro_options[products][<?php echo $i ?>][cli_ID]">
                                                        <?php
                                                        foreach ($clinics as $cid => $cliname) {
                                                            $selected = $cid == $pr['cli_ID'] ? 'selected' : '';
                                                            echo '<option value="' . $cid . '" ' . $selected . '>' . $cliname . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="row">
                                                    <label>Doctor</label>
                                                    <select class="location_doctor" name="pro_options[products][<?php echo $i ?>][doc_ID]" data-value="<?php echo $pr['doc_ID'] ?>"></select>
                                                </div>
                                                <?php
                                                if (count($labs) > 1) {
                                                    echo '<div class="row">';
                                                    echo '<label>Lab: </label>';
                                                    echo '<select name="pro_options[products][' . $i . '][labid]">';
                                                    echo '<option value="">Default</option>';
                                                    foreach ($labs as $lab) {
                                                        $selected = $lab->lab_ID == $pr['labid'] ? 'selected' : '';
                                                        echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
                                                    }
                                                    echo '</select>';
                                                    echo '</div>';

                                                    if ($form_options['enable_discounttest']) {
                                                ?>
                                                        <div>
                                                            <label><input type="checkbox" <?php checked($pr['onlydiscountlab']) ?> value="1" name="pro_options[products][<?php echo $i ?>][onlydiscountlab]" />
                                                                Only Apply Lab for discount</label>
                                                        </div>
                                                    <?php
                                                    }
                                                }

                                                if ($form_options['enable_scheduling']) {
                                                    if ($form_options['enable_groups'] || $form_options['enable_sctest']) {
                                                    ?>
                                                        <div class="row">
                                                            <label>Scheduling key</label>
                                                            <input type="number" value="<?php echo $pr['escheduling'] ?>" name="pro_options[products][<?php echo $i ?>][escheduling]" />
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <div class="row">
                                                        <label>Limit Time From</label>
                                                        <input name="pro_options[products][<?php echo $i ?>][timefrom]" value="<?php echo $pr['timefrom'] ?>" placeholder="HH:ii" />
                                                    </div>
                                                    <div class="row">
                                                        <label>Limit Time To</label>
                                                        <input name="pro_options[products][<?php echo $i ?>][timeto]" value="<?php echo $pr['timeto'] ?>" placeholder="HH:ii" />
                                                    </div>
                                                    <div class="row">
                                                        <label>Text Limit</label>
                                                        <textarea name="pro_options[products][<?php echo $i ?>][textlimit]"><?php echo $pr['textlimit'] ?></textarea>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                                <div class="row">
                                                    <label>"key" use in shortcode</label>
                                                    <input type="text" value="<?php echo $pr['sckey'] ?>" name="pro_options[products][<?php echo $i ?>][sckey]" />
                                                </div>
                                                <div class="row">
                                                    <label>Duration</label>
                                                    <input type="text" value="<?php echo $pr['duration'] ?>" name="pro_options[products][<?php echo $i ?>][duration]" />
                                                </div>
                                                <div class="row">
                                                    <label>Badge</label>
                                                    <input type="text" value="<?php echo $pr['badge'] ?>" name="pro_options[products][<?php echo $i ?>][badge]" />
                                                </div>
                                                <div class="row">
                                                    <label>Type of Specimen</label>
                                                    <input type="text" value="<?php echo $pr['type_specimen'] ?>" name="pro_options[products][<?php echo $i ?>][type_specimen]" />
                                                </div>
                                                <div class="row">
                                                    <label>Radio group key</label>
                                                    <input type="text" value="<?php echo $pr['radiogroup'] ?>" name="pro_options[products][<?php echo $i ?>][radiogroup]" />
                                                </div>
                                                <div class="row">
                                                    <label>Text Below</label>
                                                    <textarea name="pro_options[products][<?php echo $i ?>][tbelow]"><?php echo $pr['tbelow'] ?></textarea>
                                                </div>

                                                <div>
                                                    <label><input type="checkbox" <?php checked($pr['company']) ?> value="1" name="pro_options[products][<?php echo $i ?>][company]" />
                                                        enable Company/Group field</label>
                                                </div>
                                            </div>

                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>
        <script>
            var $doctors = <?php echo json_encode($doctors_qr) ?>;
            jQuery(function($) {
                $.fn.build_doctors = function($cid) {
                    console.log(':' + $cid);
                    var $html = '<option value="">Select</option>';
                    var $docid = $(this).data('value');
                    for (var i in $doctors) {
                        var $doc = $doctors[i];

                        if ($doc.cID != $cid) continue;
                        var $docname = $doc.doc_last + ', ' + $doc.doc_first + ' ( ' + $doc.doc_npi + ' )';
                        var $selected = $docid == $doc.doc_ID ? 'selected' : '';
                        $html += '<option value="' + $doc.doc_ID + '" ' + $selected + '>' + $docname + '</option>';
                    }
                    $(this).html($html);
                }
                $('body').on('change', '.location_clinic', function() {
                    var $tr = $(this).parents('.moreinfo_content');
                    var $se_doctor = $('.location_doctor', $tr);
                    $se_doctor.data('value', '');
                    $se_doctor.build_doctors($(this).val());
                });
                $('.moreinfo_content').each(function() {
                    var $cid = $('select.location_clinic', $(this)).val();
                    $('.location_doctor', $(this)).build_doctors($cid);
                })

            })
        </script>
    <?php
    } else if ($subtab == 'insurance') {
        $this->input('enable_insurance', array('type' => 'checkbox', 'label' => 'Enable Insurance', 'labelcb' => true));
        $this->input('enable_insurance_ssn', array('type' => 'checkbox', 'label' => 'SSN Required', 'labelcb' => true));
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Products Insurance</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_list_type" value="products_insurance" />
                <table id="productsinsurance" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:45%">Product Name</th>
                            <th style="width:100px">Type</th>
                            <th>Header</th>
                            <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                <th>Location</th>
                            <?php } ?>
                            <th style="width:50px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        if (empty($form_options['products_insurance'])) {
                            $form_options['products_insurance'] = array(array());
                        }
                        foreach ($form_options['products_insurance'] as $pr) {
                            if (!is_array($pr['locs'])) {
                                $pr['locs'] = array();
                            }

                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <select name="pro_options[products_insurance][<?php echo $i ?>][ID]">
                                        <?php
                                        foreach ($product_options as $pid => $pname) {

                                            if (is_array($pname)) {
                                                echo '<optgroup label="' . $pname['group_name'] . '">';
                                                foreach ($pname as $ic => $vl) {
                                                    if ($ic == 'group_name') continue;
                                                    $selected = $ic == $pr['ID'] ? 'selected' : '';
                                                    echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                }
                                                echo '</optgroup>';
                                            } else {
                                                $selected = $pid == $pr['ID'] ? 'selected' : '';
                                                echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="pro_options[products_insurance][<?php echo $i ?>][type]" required>
                                        <?php

                                        foreach ($btypes as $btype => $txt) {
                                            $selected = $btype == $pr['type'] ? 'selected' : '';
                                            if (!$selected && stripos($txt, '(old)') > 0) {
                                                continue;
                                            }
                                            echo '<option value="' . $btype . '" ' . $selected . '>' . $txt . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <textarea class="hoversize" name="pro_options[products_insurance][<?php echo $i ?>][header]"><?php echo $pr['header'] ?></textarea>
                                </td>
                                <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                    <td>
                                        <select name="pro_options[products_insurance][<?php echo $i ?>][locs][]" class="select2" multiple>
                                            <?php
                                            foreach ($form_options['locations'] as $location) {
                                                $selected = in_array($location['key'], $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $location['key'] . '" ' . $selected . '>' . $location['name'] . '</option>';
                                            }
                                            foreach ($location_keys as $lk => $lv) {
                                                $selected = in_array($lk, $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $lk . '" ' . $selected . '>' . $lv . ' Group</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                <?php } ?>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>
                                            <?php
                                            if ($form_options['enable_scheduling']) {
                                            ?>
                                                <div class="row">
                                                    <label>Limit Time From</label>
                                                    <input name="pro_options[products_insurance][<?php echo $i ?>][timefrom]" value="<?php echo $pr['timefrom'] ?>" placeholder="HH:ii" />
                                                </div>
                                                <div class="row">
                                                    <label>Limit Time To</label>
                                                    <input name="pro_options[products_insurance][<?php echo $i ?>][timeto]" value="<?php echo $pr['timeto'] ?>" placeholder="HH:ii" />
                                                </div>
                                                <div class="row">
                                                    <label>Text Limit</label>
                                                    <textarea name="pro_options[products_insurance][<?php echo $i ?>][textlimit]"><?php echo $pr['textlimit'] ?></textarea>
                                                </div>
                                            <?php
                                            }
                                            ?>
                                            <div class="row">
                                                <label>Only Show By slug "key" in shortcode</label>
                                                <input type="text" value="<?php echo $pr['sckey'] ?>" name="pro_options[products_insurance][<?php echo $i ?>][sckey]" />
                                            </div>
                                            <div class="row">
                                                <label>Type of Specimen</label>
                                                <input type="text" value="<?php echo $pr['type_specimen'] ?>" name="pro_options[products_insurance][<?php echo $i ?>][type_specimen]" />
                                            </div>
                                            <div class="row">
                                                <label>Radio group key</label>
                                                <input type="text" value="<?php echo $pr['radiogroup'] ?>" name="pro_options[products_insurance][<?php echo $i ?>][radiogroup]" />
                                            </div>
                                            <div class="row">
                                                <label>Text Below</label>
                                                <textarea name="pro_options[products_insurance][<?php echo $i ?>][tbelow]"><?php echo $pr['tbelow'] ?></textarea>
                                            </div>

                                            <div>
                                                <label><input type="checkbox" <?php checked($pr['company']) ?> value="1" name="pro_options[products_insurance][<?php echo $i ?>][company]" />
                                                    enable Company/Group field</label>
                                            </div>

                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>

    <?php
    } else if ($subtab == 'no-insurance') {
        $this->input('enable_no_insurance', array('type' => 'checkbox', 'label' => 'Enable No-Insurance', 'labelcb' => true));
        $this->input('no_insurance_below', array('type' => 'checkbox', 'label' => 'Show No-Insurance Below', 'labelcb' => true));
        $this->input('enable_no_insurance_ssn', array('type' => 'checkbox', 'label' => 'SSN Required', 'labelcb' => true));
        $this->input('enable_no_ins_upid', array('type' => 'checkbox', 'label' => 'Show Upload ID', 'labelcb' => true));
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Products No-Insurance</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_list_type" value="products_noinsurance" />
                <table id="productsnoinsurance" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:45%">Product Name</th>
                            <th style="width:100px">Type</th>
                            <th>Header</th>
                            <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                <th>Location</th>
                            <?php } ?>
                            <th style="width:50px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        if (empty($form_options['products_noinsurance'])) {
                            $form_options['products_noinsurance'] = array(array());
                        }
                        foreach ($form_options['products_noinsurance'] as $pr) {
                            if (!is_array($pr['locs'])) {
                                $pr['locs'] = array();
                            }
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <select name="pro_options[products_noinsurance][<?php echo $i ?>][ID]">
                                        <?php
                                        foreach ($product_options as $pid => $pname) {

                                            if (is_array($pname)) {
                                                echo '<optgroup label="' . $pname['group_name'] . '">';
                                                foreach ($pname as $ic => $vl) {
                                                    if ($ic == 'group_name') continue;
                                                    $selected = $ic == $pr['ID'] ? 'selected' : '';
                                                    echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                }
                                                echo '</optgroup>';
                                            } else {
                                                $selected = $pid == $pr['ID'] ? 'selected' : '';
                                                echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="pro_options[products_noinsurance][<?php echo $i ?>][type]" required>
                                        <?php

                                        foreach ($btypes as $btype => $txt) {
                                            $selected = $btype == $pr['type'] ? 'selected' : '';
                                            if (!$selected && stripos($txt, '(old)') > 0) {
                                                continue;
                                            }
                                            echo '<option value="' . $btype . '" ' . $selected . '>' . $txt . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <textarea class="hoversize" name="pro_options[products_noinsurance][<?php echo $i ?>][header]"><?php echo $pr['header'] ?></textarea>
                                </td>
                                <?php if ($form_options['enable_s_location'] || $form_options['enable_location']) { ?>
                                    <td>
                                        <select name="pro_options[products_noinsurance][<?php echo $i ?>][locs][]" class="select2" multiple>
                                            <?php
                                            foreach ($form_options['locations'] as $location) {
                                                $selected = in_array($location['key'], $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $location['key'] . '" ' . $selected . '>' . $location['name'] . '</option>';
                                            }
                                            foreach ($location_keys as $lk => $lv) {
                                                $selected = in_array($lk, $pr['locs']) ? 'selected' : '';
                                                echo '<option value="' . $lk . '" ' . $selected . '>' . $lv . ' Group</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                <?php } ?>
                                <td class="bactions">
                                    <div class="moreinfo">
                                        <a class="button moreinfo_btn">more</a>
                                        <div class="moreinfo_content" style="display: none;">
                                            <a class="closebtn">x</a>
                                            <?php
                                            if ($form_options['enable_scheduling']) {
                                            ?>
                                                <div class="row">
                                                    <label>Limit Time From</label>
                                                    <input name="pro_options[products_noinsurance][<?php echo $i ?>][timefrom]" value="<?php echo $pr['timefrom'] ?>" placeholder="HH:ii" />
                                                </div>
                                                <div class="row">
                                                    <label>Limit Time To</label>
                                                    <input name="pro_options[products_noinsurance][<?php echo $i ?>][timeto]" value="<?php echo $pr['timeto'] ?>" placeholder="HH:ii" />
                                                </div>
                                                <div class="row">
                                                    <label>Text Limit</label>
                                                    <textarea name="pro_options[products_noinsurance][<?php echo $i ?>][textlimit]"><?php echo $pr['textlimit'] ?></textarea>
                                                </div>
                                            <?php
                                            }
                                            ?>
                                            <div class="row">
                                                <label>Only Show By slug "key" in shortcode</label>
                                                <input type="text" value="<?php echo $pr['sckey'] ?>" name="pro_options[products_noinsurance][<?php echo $i ?>][sckey]" />
                                            </div>

                                            <div class="row">
                                                <label>Type of Specimen</label>
                                                <input type="text" value="<?php echo $pr['type_specimen'] ?>" name="pro_options[products_noinsurance][<?php echo $i ?>][type_specimen]" />
                                            </div>
                                            <div class="row">
                                                <label>Radio group key</label>
                                                <input type="text" value="<?php echo $pr['radiogroup'] ?>" name="pro_options[products_noinsurance][<?php echo $i ?>][radiogroup]" />
                                            </div>

                                            <div class="row">
                                                <label>Text Below</label>
                                                <textarea name="pro_options[products_noinsurance][<?php echo $i ?>][tbelow]"><?php echo $pr['tbelow'] ?></textarea>
                                            </div>


                                            <div>
                                                <label><input type="checkbox" <?php checked($pr['company']) ?> value="1" name="pro_options[products_noinsurance][<?php echo $i ?>][company]" />
                                                    enable Company/Group field</label>
                                            </div>
                                            <br>
                                            <div class="center"><a class="button donebtn">Done</a></div>
                                        </div>
                                    </div>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>

    <?php
        $this->input('noinsu_text', array('label' => 'Text Below', 'type' => 'textarea'));
    } else if ($subtab == 'addon') {
    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Products - Add-on's</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2" class="products_area">
                <input type="hidden" name="has_list_type" value="products_adding" />
                <table id="productsadding" class=" wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th style=" width:45%">Product Name</th>
                            <th colspan="2">Header</th>
                            <th style="width:50px !important;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        if (empty($form_options['products_adding'])) {
                            $form_options['products_adding'] = array(array());
                        }
                        foreach ($form_options['products_adding'] as $pr) {
                            $i++;
                        ?>
                            <tr class="productrow row_<?php echo $i ?>">
                                <td>
                                    <select name="pro_options[products_adding][<?php echo $i ?>][ID]">
                                        <?php
                                        foreach ($product_options as $pid => $pname) {

                                            if (is_array($pname)) {
                                                echo '<optgroup label="' . $pname['group_name'] . '">';
                                                foreach ($pname as $ic => $vl) {
                                                    if ($ic == 'group_name') continue;
                                                    $selected = $ic == $pr['ID'] ? 'selected' : '';
                                                    echo '<option value="', $ic, '"', $selected, '>', $vl, '</option>';
                                                }
                                                echo '</optgroup>';
                                            } else {
                                                $selected = $pid == $pr['ID'] ? 'selected' : '';
                                                echo '<option value="', $pid, '"', $selected, '>', $pname, '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td colspan="2">
                                    <textarea class="hoversize" name="pro_options[products_adding][<?php echo $i ?>][header]"><?php echo $pr['header'] ?></textarea>
                                </td>
                                <td>
                                    <a class="button removerowproduct">-</a>
                                </td>
                            </tr>
                        <?php
                        }
                        if ($i > $max_number) {
                            $max_number = $i;
                        }
                        ?>
                    </tbody>
                </table>
                <div class="frow" style="text-align: right">
                    <a class="button addnewrowproduct">+</a>
                </div>
            </td>
        </tr>
    <?php
    } else if ($subtab == 'location_opt') {
        $this->input('enable_location', array('type' => 'checkbox', 'label' => 'Enable Location', 'labelcb' => true));
        $this->input('enable_skip_location', array('type' => 'checkbox', 'label' => 'Enable Skip Location', 'labelcb' => true));
        $this->input('enable_location_fields', array('type' => 'checkbox', 'label' => 'Enable Location Fields (addres,city,state,zip) in order form', 'labelcb' => true));
        $this->input('enable_location_map', array('type' => 'checkbox', 'label' => 'Enable Map Location', 'labelcb' => true));
        $this->input('enable_s_location', array('type' => 'checkbox', 'label' => 'Enable URL Location', 'labelcb' => true));
        $this->input('location_first', array('type' => 'checkbox', 'label' => 'Show Location First', 'labelcb' => true));
        $this->input('location_afpa', array('type' => 'checkbox', 'label' => 'Show Location After Patient', 'labelcb' => true));
        $this->input('enable_pbyloc', array('type' => 'checkbox', 'label' => 'Enable Products by location', 'labelcb' => true));
        $this->input('only_show_one_in_loc', array('type' => 'checkbox', 'label' => 'Only show Current Location in Location page', 'labelcb' => true));
        $this->input('no_location_text', array('type' => 'textarea', 'label' => 'Text If not Location In State'));
        $this->input('location_text', array('type' => 'textarea', 'label' => 'Text in Location'));
        $this->input('location_text_selected', array('type' => 'textarea', 'label' => 'Text after selected Location: [phone] [address]'));
        $this->input('location_text_color', array('label' => 'Text Color'));
    } else if ($subtab == 'locations') {

        if (isset($_GET['restore']) && empty($_POST)) {
            $lastchange = $wpdb->get_var("select data from `ci_log_forms` where form='patient_product' order by id desc ");

            $lastchange = json_decode($lastchange, true);
            $form_options['locations'] = $lastchange['pro_options']['locations'];
        } else if (isset($_GET['setupclinic']) && isset($_GET['df'])) {
            $cli = $wpdb->get_row("select * from ci_clinic where cID = {$_REQUEST['df']}", ARRAY_A);
            if ($cli) {
                foreach ($form_options['locations'] as $i => $location) {
                    if (empty($location['cli_ID'])) {

                        $exist = $wpdb->get_var("select cID from ci_clinic where cli_clinic = '{$location['name']}'");
                        if ($exist) continue;

                        $clidata = $cli;
                        unset($clidata['cID']);

                        list($address, $city, $statez) = explode(',', $location['address']);
                        $statez = trim($statez);
                        list($state, $zip) = explode(' ', $statez);
                        $clidata['cli_clinic'] = $location['name'];
                        $clidata['cli_street'] = trim($address);
                        $clidata['cli_city'] = trim($city);
                        $clidata['cli_state'] = strtoupper(trim($state));
                        $clidata['cli_zip'] = trim($zip);


                        $wpdb->insert('ci_clinic', $clidata);
                        $cid = $wpdb->insert_id;

                        if ($cid == 0) {
                            var_dump($clidata);
                            var_dump($wpdb->last_error);
                            echo '<br><br><br>';
                        }


                        if ($cid > 0) {
                            $form_options['locations'][$i]['cli_ID'] = $cid;

                            $offices = $wpdb->get_results("select * from ci_clinic_manager 
                                                where ma_cli_ID = {$_REQUEST['df']}", ARRAY_A);
                            foreach ($offices as $r) {
                                unset($r['ma_ID']);
                                $r['ma_cli_ID'] = $cid;
                                $wpdb->insert('ci_clinic_manager', $r);
                            }

                            $docs = $wpdb->get_results("select * from ci_clinic_doc 
                                                where cli_ID = {$_REQUEST['df']}", ARRAY_A);
                            foreach ($docs as $r) {
                                $r['cli_ID'] = $cid;
                                $wpdb->insert('ci_clinic_doc', $r);
                            }
                        }
                    }
                }


                $clinics = $doctors = array('' => 'Select');
                // get clinic
                $clinics_qr = $wpdb->get_results("select cID, cli_clinic from ci_clinic 
	                        where cli_status = 1  
	                        order by cli_clinic asc");


                foreach ($clinics_qr as $value) {
                    $clinics[$value->cID] = $value->cli_clinic;
                }
            }
        } else if (empty($form_options['locations']) && isset($_GET['autoclinic'])) {
            $clis = $wpdb->get_results("select * from ci_clinic where cli_status=1", ARRAY_A);
            foreach ($clis as $i => $cli) {
                $form_options['locations'][] = array(
                    'key' => $i,
                    'name' => $cli['cli_clinic'],
                    'cli_ID' => $cli['cID'],
                );
            }
        }

    ?>
        <tr>
            <th colspan="2" style="text-align: center">
                <h3>Locations</h3>
            </th>
        </tr>
        <tr>
            <td colspan="2">
                <input type="hidden" name="has_list_type" value="locations" />
                <table id="locations" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl" ">
                <thead>
                <tr>
                    <th style=" width:60px !important;">Key</th>
                    <?php if ($form_options['enable_s_location']) { ?>
                        <th style="width:80px !important;">URL</th>
                    <?php } ?>
                    <th>Location Name</th>
                    <?php if ($form_options['enable_location_map']) { ?>
                        <th>Address</th>
                    <?php } ?>
                    <th>Phone</th>
                    <th>Description</th>
                    <th>Clinic</th>
                    <th>Doctor</th>
                    <th style="width:50px !important;">Action</th>
        </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            if (empty($form_options['locations'])) {
                $form_options['locations'] = array(array());
            }
            global $adding_locations_import;
            if (is_array($adding_locations_import) && count($adding_locations_import) > 0) {
                foreach ($form_options['locations'] as $i2 => $lc) {
                    foreach ($adding_locations_import as $j2 => $lca) {
                        if ($lca['name'] == $lc['name']) {
                            $form_options['locations'][$i2]['address'] = $lca['address'];
                            $form_options['locations'][$i2]['phone'] = $lca['phone'];
                            $form_options['locations'][$i2]['desc'] = $lca['desc'];
                            unset($adding_locations_import[$j2]);
                            break;
                        }
                    }
                }
                $form_options['locations'] = array_merge($form_options['locations'], $adding_locations_import);
            }

            if (isset($_GET['sortl'])) {
                usort($form_options['locations'], function ($a, $b) {
                    if (substr($a['key'], 0, 1) == substr($b['key'], 0, 1)) {
                        return intval(substr($a['key'], 1)) > intval(substr($b['key'], 1));
                    }
                    return strcmp($a['key'], $b['key']);
                });
            }

            foreach ($form_options['locations'] as $pr) {
                $i++;
            ?>
                <tr class="locationrow row_<?php echo $i ?>">
                    <td>
                        <input type="text" name="pro_options[locations][<?php echo $i ?>][key]" value="<?php echo $pr['key'] ?>" />
                    </td>
                    <?php if ($form_options['enable_s_location']) { ?>
                        <td>
                            <input type="text" name="pro_options[locations][<?php echo $i ?>][url]" value="<?php echo $pr['url'] ?>" />
                        </td>
                    <?php } ?>
                    <td>
                        <input type="text" name="pro_options[locations][<?php echo $i ?>][name]" value="<?php echo $pr['name'] ?>" />
                    </td>
                    <?php if ($form_options['enable_location_map']) { ?>
                        <td>
                            <input type="text" name="pro_options[locations][<?php echo $i ?>][address]" value="<?php echo $pr['address'] ?>" />
                        </td>
                    <?php } ?>
                    <td>
                        <input type="text" name="pro_options[locations][<?php echo $i ?>][phone]" value="<?php echo $pr['phone'] ?>" />
                    </td>
                    <td>
                        <input type="text" name="pro_options[locations][<?php echo $i ?>][desc]" value="<?php echo $pr['desc'] ?>" />
                    </td>
                    <td>
                        <select class="location_clinic" name="pro_options[locations][<?php echo $i ?>][cli_ID]">
                            <?php
                            foreach ($clinics as $cid => $cliname) {
                                $selected = $cid == $pr['cli_ID'] ? 'selected' : '';
                                echo '<option value="' . $cid . '" ' . $selected . '>' . $cliname . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <select class="location_doctor" name="pro_options[locations][<?php echo $i ?>][doc_ID]" data-value="<?php echo $pr['doc_ID'] ?>"></select>
                    </td>
                    <td class="bactions">
                        <div class="moreinfo">
                            <a class="button moreinfo_btn">more</a>
                            <div class="moreinfo_content" style="display: none;">
                                <a class="closebtn">x</a>
                                <?php if ($form_options['enable_pbyloc'] && function_exists('wc_get_product')) { ?>
                                    <div>
                                        <label>Products: </label><br>
                                        <select style="width:100%" name="pro_options[locations][<?php echo $i ?>][pids][]" class="select2" multiple>
                                            <?php

                                            if (!isset($pr['pids']) || !is_array($pr['pids'])) {
                                                $pr['pids']  = array();
                                            }
                                            if (is_array($form_options['products'])) {
                                                foreach ($form_options['products'] as $parr) {
                                                    $product = wc_get_product($parr['ID']);
                                                    $price_txt = '';
                                                    $optxt = '';
                                                    if ($product) {
                                                        $price_txt = $product->get_price_html();
                                                        $optxt = 'Pay Product - ' . $product->get_name() . ' (' . $price_txt . ')';
                                                    }

                                                    $optkey = $parr['type'] . '|' . $parr['ID'] . '|pay';
                                                    $selected = in_array($optkey, $pr['pids']) ? 'selected' : '';
                                                    echo '<option value="' . $optkey . '" ' . $selected . '>' . $optxt . '</option>';
                                                }
                                            }


                                            if (is_array($form_options['products_insurance'])) {
                                                foreach ($form_options['products_insurance'] as $parr) {
                                                    $product = wc_get_product($parr['ID']);
                                                    $price_txt = '';
                                                    $optxt = '';
                                                    if ($product) {
                                                        $price_txt = $product->get_price_html();
                                                        $optxt = 'Insurance - ' . $product->get_name() . ' (' . $price_txt . ')';
                                                    }

                                                    $optkey = $parr['type'] . '|' . $parr['ID'] . '|ins';
                                                    $selected = in_array($optkey, $pr['pids']) ? 'selected' : '';
                                                    echo '<option value="' . $optkey . '" ' . $selected . '>' . $optxt . '</option>';
                                                }
                                            }

                                            if (is_array($form_options['products_noinsurance'])) {
                                                foreach ($form_options['products_noinsurance'] as $parr) {
                                                    $product = wc_get_product($parr['ID']);
                                                    $price_txt = '';
                                                    $optxt = '';
                                                    if ($product) {
                                                        $price_txt = $product->get_price_html();
                                                        $optxt = 'No-Insurance - ' . $product->get_name() . ' (' . $price_txt . ')';
                                                    }

                                                    $optkey = $parr['type'] . '|' . $parr['ID'] . '|noins';
                                                    $selected = in_array($optkey, $pr['pids']) ? 'selected' : '';
                                                    echo '<option value="' . $optkey . '" ' . $selected . '>' . $optxt . '</option>';
                                                }
                                            }

                                            ?>
                                        </select>
                                    </div>

                                <?php } ?>
                                <div>
                                    <label><input type="checkbox" <?php checked(isset($pr['hidemain']) && $pr['hidemain']) ?> value="1" name="pro_options[locations][<?php echo $i ?>][hidemain]" />
                                        Hide in Main Patient Portal </label>
                                </div>
                                <div>
                                    <label><input type="checkbox" <?php checked(isset($pr['hidemap']) && $pr['hidemap']) ?> value="1" name="pro_options[locations][<?php echo $i ?>][hidemap]" />
                                        Hide map </label>
                                </div>
                                <br>
                                <div class="center"><a class="button donebtn">Done</a></div>
                            </div>
                        </div>
                        <a class="button removerowlocation">-</a>
                    </td>
                </tr>
            <?php
            }
            if ($i > $max_number) {
                $max_number = $i;
            }
            ?>
        </tbody>
        </table>
        <div class="frow" style="text-align: right">
            <a class="button addnewrowlocation">+</a>
        </div>
        <p>
            Notes: key start W or S will hide in main page
        </p>
        <div>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import_locations', 'trajax' => '1')) ?>" class="thickbox" title="Import Locations">Import</a>
        </div>
        <script>
            var $next_id = <?php echo $max_number + 20 ?>;
            var $doctors = <?php echo json_encode($doctors_qr) ?>;
            jQuery(function($) {
                $.fn.build_doctors = function($cid) {
                    console.log(':' + $cid);
                    var $html = '<option value="">Select</option>';
                    var $docid = $(this).data('value');
                    for (var i in $doctors) {
                        var $doc = $doctors[i];

                        if ($doc.cID != $cid) continue;
                        var $docname = $doc.doc_last + ', ' + $doc.doc_first + ' ( ' + $doc.doc_npi + ' )';
                        var $selected = $docid == $doc.doc_ID ? 'selected' : '';
                        $html += '<option value="' + $doc.doc_ID + '" ' + $selected + '>' + $docname + '</option>';
                    }
                    $(this).html($html);
                }
                $('body').on('click', '.addnewrowlocation', function() {
                    $next_id++;
                    var $defaultrow = '<tr class="locationrow">' + $('table#locations tr.row_1').html() + '</tr>';
                    $defaultrow = $defaultrow.replace(/\[\d\]/g, '[' + $next_id + ']');
                    var $new = $($defaultrow);
                    $('input,select,textarea', $new).val('').attr('value', '');
                    $('select', $new).removeClass('select2-hidden-accessible');
                    $('select option', $new).prop('selected', false);
                    $('.select2.select2-container', $new).remove();
                    $('.location_doctor', $new).build_doctors('');
                    try {
                        $('select.select2', $new).each(function() {
                            $(this).select2();
                        })
                    } catch (e) {}
                    $('table#locations tbody').append($new);

                }).on('click', '.removerowlocation', function() {
                    if (confirm('Do you want delete row?')) {
                        var $tr = $(this).parents('tr.locationrow');
                        $tr.remove();
                    }
                    return false;
                }).on('change', '.location_clinic', function() {
                    var $tr = $(this).parents('tr.locationrow');
                    var $se_doctor = $('.location_doctor', $tr);
                    $se_doctor.data('value', '');
                    $se_doctor.build_doctors($(this).val());
                })

                $('tr.locationrow').each(function() {
                    var $cid = $('select.location_clinic', $(this)).val();
                    $('.location_doctor', $(this)).build_doctors($cid);
                })
            })
        </script>
        </td>
        </tr>
    <?php
    }


    $this->endform();
    ?>
</div>
<style>
    .select2-container.select2-container--open {
        z-index: 9999999;
    }

    table.custom-meta-tbl tr th {
        width: 30% !important;
        padding-left: 2px;
        padding-right: 2px;
    }

    form .custom-meta-tbl tr td {
        padding-left: 2px;
        padding-right: 2px;
        position: relative;
    }

    td select {
        width: 100%;
    }

    table th h3 {
        margin: 0;
    }

    body form table.wp-list-table th {
        text-align: center;
    }

    table.blue_tbl td {
        vertical-align: middle;
    }

    .blue_tbl td textarea {
        width: 100%;
        height: 56px;
    }

    .blue_tbl td textarea.hoversize:focus {
        position: absolute;
        width: 380px;
        height: 200px;
        z-index: 999;
        top: 0;
    }

    .blue_tbl tr.productrow:focus-within {
        background: #ff8;
    }

    td.bactions {
        padding: 2px;
    }

    td.bactions .button {
        display: block;
        height: 24px;
        line-height: 24px;
        margin: 2px;
        padding: 0;
        text-align: center;
        min-height: 24px;
        font-weight: normal;
    }

    .moreinfo_content {
        display: none;
        position: relative;
    }

    .moreinfo .closebtn {
        position: absolute;
        right: 10px;
        top: 3px;
        font-size: 22px;
        cursor: pointer;
    }

    .moreinfo.active:before {
        content: "";
        position: fixed;
        z-index: 99999;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        background: rgba(0, 0, 0, 0.3);
    }

    .moreinfo.active .moreinfo_content {
        display: block !important;
        position: fixed;
        top: 60px;
        left: 50%;
        margin-left: -240px;
        width: 480px;
        background: #fff;
        border: 15px solid rgba(0, 0, 0, 0.5);
        -webkit-border-radius: 15px;
        -moz-border-radius: 15px;
        border-radius: 15px;
        min-height: 200px;
        padding: 20px;
        z-index: 100000;
    }

    .moreinfo_content .wrapmoreform {

        max-height: 500px;
        overflow: auto;
    }

    h2.tabs2 .nav-tab {
        text-transform: none;
    }

    <?php if ($form_options['enable_location_map']) { ?>.locationrow td input[type="text"]:focus {
        position: absolute;
        width: 300px;
        z-index: 999;
    }

    <?php } ?>
</style>
<script src="<?php echo TRCPDD_URL . '/js/select2.min.js?' . TRCPDD_VS ?>"></script>
<link href="<?php echo TRCPDD_URL . '/css/select2.min.css?' . TRCPDD_VS ?>" type='text/css' media='all' rel='stylesheet' />
<script>
    var $next_id = <?php echo $max_number + 20 ?>;
    jQuery(function($) {
        $('body').on('click', '.addnewrowproduct', function() {
            $next_id++;
            var $area = $(this).parents('.products_area');
            var $defaultrow = '<tr class="productrow">' + $('table tr.row_1', $area).html() + '</tr>';
            $defaultrow = $defaultrow.replace(/\[\d\]/g, '[' + $next_id + ']');
            var $new = $($defaultrow);
            $('input,select,textarea', $new).val('');
            $('select', $new).removeClass('select2-hidden-accessible');
            $('span.select2', $new).remove();
            $('table tbody', $area).append($new);
            $('select.select2', $new).select2();
            if ($('.diagnostic_code', $new).length > 0) {
                $('.diagnostic_code', $new).autocomplete({
                    source: '?tr_act=autocomplete&type=diagnostic_code',
                    minLength: 1
                });
            }


        }).on('click', '.removerowproduct', function() {
            if (confirm('Do you want delete row?')) {
                var $tr = $(this).parents('tr.productrow');
                $tr.remove();
            }
            return false;
        }).on('click', '.moreinfo .moreinfo_btn', function() {
            var $pr = $(this).parents('.moreinfo');
            $pr.addClass('active');
        }).on('click', '.moreinfo .closebtn, .moreinfo .donebtn', function() {
            var $pr = $(this).parents('.moreinfo');
            $pr.removeClass('active');
        })

        $("#acc_text_color,#acc_content_color,#acc_bg_hover_color,#acc_text_hover_color, #location_text_color").wpColorPicker();
        $('input[id*="acc_bg_color"]').wpColorPicker();

        $('#products tbody, #productsinsurance tbody, #locations tbody, #symtoms tbody').sortable();
        $('.select2').select2();

        $('.diagnostic_code').each(function() {
            $(this).autocomplete({
                source: '?tr_act=autocomplete&type=diagnostic_code',
                minLength: 1
            });
        })

    })
</script>