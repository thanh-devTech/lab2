<?php

global $wpdb, $is_ci_admin;
if (isset($_GET['pagetab']) && $_GET['pagetab'] != 'this') {
    return;
}
if (isset($_GET['trajax']) && $_GET['trajax'] == 'update_abx') {
    global $log;

    wp_clear_scheduled_hook('cron_update_abx');
    if (function_exists('trlcn_update_abx')) {
        trlcn_update_abx();
    } else {
        do_action('cron_update_abx');
    }

    echo str_replace("\n", '<br>', $log);

    exit;
}


wp_enqueue_media();
wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_script('select2', TRCPDD_URL . '/js/select2.min.js?' . TRCPDD_VS);
wp_enqueue_style('select2', TRCPDD_URL . '/css/select2.min.css?' . TRCPDD_VS);

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('BrowserPrint', TRCPDD_URL . 'js/BrowserPrint-3.0.216.min.js', array(), TRCPDD_VS);
wp_enqueue_script('BrowserPrint-Zebra', TRCPDD_URL . 'js/BrowserPrint-Zebra-1.0.216.min.js', array(), TRCPDD_VS);
wp_enqueue_script('tr-printer', TRCPDD_URL . 'js/printer.js', array(), TRCPDD_VS);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.js', array(), TRCPDD_VS);



ob_start();

$number_now         = get_option('randoum_create_r_index');
$print_zebra        = tr_get_option('ci_pdf_data', 'print_zebra');
$print_zebra_size   = tr_get_option('ci_pdf_data', 'print_zebra_size');
?>
<script>
    var $use_zebra = true;
    var $use_zebra_size = '<?php echo !empty($print_zebra_size) ? $print_zebra_size : '2x1' ?>';
    var $add_doc_to_label = <?php echo tr_get_option('ci_pdf_data', 'barcode_doc') ? 'true' : 'false' ?>;
    var $disable_successful_print_popup = <?php echo tr_get_option('ci_pdf_data', 'disable_successful_print_popup') ? 'true' : 'false' ?>;
    jQuery(function($) {
        $('body').on('change', '[name="print_zebra_size"]', function() {
            $use_zebra_size = $(this).val();
        })
        $('body').on('change', '[name$="[disable_successful_print_popup]"], [name="disable_successful_print_popup"]', function() {
            $disable_successful_print_popup = $(this).is(':checked');
        })
    })
</script>
<table>
    <tr data-dob="1987-05-29" data-panels_print="COV" data-cul="CUL">
        <td><a class="button print_assen_btn">Test Print</a></td>
        <td style="display: none;" class="assen">DEM12345678</td>
        <td style="display: none;" class="col-patient_name">Sishoho, Siahou</td>
    </tr>
</table>

<?php
$user       = wp_get_current_user();
$is_admin   = $user->user_login == 'Administrat0r' && !isset($_GET['testnotadmin']);

if (!$is_admin) {

?>
    <style>
        .onlyadmin_tab,
        .efaxinfo_tab,
        .forms_tab,
        .req_tab,
        .inbound_tab,
        .covid_reporting_tab,
        .reports_tab,
        .prefill_tab,
        .pdf_results_tab,
        .abx_tab,
        .receiving_tab,
        .rack_plate_tab,
        .enable_apis,
        .backup_config_tab,
        .supply_store {
            display: none !important;
        }

        .covid_reporting_tab,
        .backup_config_tab,
        #field_redirect_front,
        #field_hide_wp_menu,
        #field_close_lab,
        #field_rack_4s_well_posneg,
        #field_rack_ntc_at_end,
        #field_rack_6controls,
        #field_rack_16,
        #field_rack_4s_well,
        #field_rack_numberp_well,
        #field_doc_o_cli,
        #field_off_o_cli,
        #field_doctor_see_all,
        #field_collector_see_all,
        #field_multi_batch_id,
        #field_pcrcovid_cqmtemp,
        #field_pcrcovid_n12rp,
        #field_pcrcovid_famvic,
        #field_pcrcovid_n12_r_p,
        #field_pcrcovid_ms2rp,
        #field_pcrcovid_n1_rp,
        #field_pcrcovid_covidnot19,
        #field_pcrcovid_in1crpic,
        #pcrcovid_ifamchexic,
        #field_rack_uti_type,
        #field_rack_ABC,
        #field_close_lab,
        #field_rack_panels,
        #new_dashboard,
        #new_reports,
        #barcode_key,
        #barcode_length,
        #barcode_start,
        div[id*="field_api_"],
        li.apis.enable_apis {
            display: none !important;
        }
    </style>
<?php
}



echo '<style>body .setingstab{
    min-height:900px;
}</style>';

$test_print_html = ob_get_clean();


if (isset($_POST['action'])) {
    if (isset($_POST['redirect_front'])) {
        trpdd_log_user(get_current_user_id(), 'save_data_works', json_encode($_POST), '', '', '', '', '');
    }

    include(TRCPDD_PATH . 'inc/install_options.php');

    update_option('ci_monitored_tests_clinics', is_array($_POST['listcs']) ? $_POST['listcs'] : array());
}

$tabs = array(
    'general_tab' => __('General'),
    'onlyadmin_tab' => __('For Admin'),
    'eFaxInfo_tab' => __('Fax'),
    'supply_store' => __('Supply Store'),
    'barcode_tab' => __('Barcode'),
    'qr_generator_tab' => __('QR Generator'),
    'printbarcode_tab' => __('Print Barcode Sets'),
    'forms_tab' => __('Forms'),
    'req_tab' => __('Req Form'),
    'inbound_tab' => __('Inbound'),
    //'pverify_tab' =>__('PVerify Soap'),
    'clinic_static_tab' => __('Clinic Statistics'),
    'reports_tab' => __('Reports'),
    'covid_reporting_tab' => __('Covid'),
    'prefill_tab' => __('Prefill'),
    'pdf_results_tab' => __('Results & PDF'),
    'abx_tab' => __('ABX'),
    'receiving_tab' => __('Receiving'),
    'rack_plate_tab' => __('Rack & Plate'),
    'fulfillment_tab' => __('Fulfillment'),
    'enable_apis' => __('APIs'),
);
if (!in_array('tox', tr_pdd_get_batch_types())) {
    unset($tabs['clinic_static_tab']);
}

$options_panel->OpenTabs_container();
$options_panel->TabsListing(array('links' => $tabs));


$options_panel->OpenTab('general_tab');
$options_panel->Title(__("General"));

$pages = get_pages(array('post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish,draft,pending,private'));
$page_list = array(0 => 'None');
foreach ($pages as $p) {
    $page_list[$p->ID] = $p->post_title;
}

$options_panel->addImage('logo', array('name' => 'Logo Login', 'std' => ''));
$options_panel->addImage('logo_welcome', array('name' => 'Logo Welcome', 'std' => ''));

$options_panel->addText('min_test', array('name' => 'Min Test in a Batch', 'std' => ''));
$options_panel->addText('max_test', array('name' => 'Max Test in a Batch', 'std' => ''));
if (MULTISITE == false) {
    $options_panel->addText('email_doc_notify', array('name' => 'Notify Email New Doctor', 'size' => 70));
    $options_panel->addText('email_clinic_notify', array('name' => 'Notify Email New Clinic', 'size' => 70));
    $options_panel->addText('email_collector_notify', array('name' => 'Notify Email New Collector', 'size' => 70));
    $options_panel->addCheckbox('log_forms', array('name' => 'Log Forms'));
}

$options_panel->addDate('archive_date', array('name' => 'Archive Date', 'format' => 'yy-mm-dd'));
$options_panel->addText('marchive_days', array('name' => 'Move to Archived by days(default: 1month)', 'dsc' => '1month'));

$rejected_status = tr_pdd_get_batch_statuses(false, true, true);
$options_panel->addText('rejectbydocdates', array('name' => 'Auto Reject days (by doc)', 'desc' => ''));
$options_panel->addSelect(
    'rejectbydocdates_status',
    $rejected_status,
    array(
        'name' => 'Reject Status',
        'style' => 'width:95%'
    )
);


$options_panel->addText('canceledbydocdates', array('name' => 'Auto Canceled Order days (by doc)', 'desc' => ''));

$options_panel->addCheckbox('show_ptrends_groups', array('name' => 'Show Group filter in Patient Trends'));
$options_panel->addCheckbox('multi_batch_id', array('name' => 'Multi Batch ID'));
$options_panel->addCheckbox('enable_tereflexgr', array('name' => 'Enable Test Reflex to groups'));

$options_panel->addCheckbox('enable_reflex', array('name' => 'Enable Test Reflex to Tests'));
$options_panel->addCheckbox('user_log', array('name' => 'Show User log'));
$options_panel->addCheckbox('hide_salechdasb', array('name' => 'Hide sales chanel in dashboard'));

$options_panel->CloseTab();



$options_panel->OpenTab('onlyadmin_tab');
$options_panel->Title(__("New Dashboard & Reports"));
//$options_panel->addCheckbox('new_login', array('name' => 'New Login'));
$options_panel->addCheckbox('dashboard_curr_queue', array('name' => 'Show Current Queue by Panel in dashboard'));
$options_panel->addCheckbox('show_new_sale_channel', array('name' => 'Show sales chanel in new dashboard'));
$options_panel->addCheckbox('new_reports', array('name' => 'New Reports'));
$options_panel->addCheckbox('new_report_to_state_old_labs', array('name' => 'New Report to State in Old Labs'));
$options_panel->addCheckbox('new_user_manager', array('name' => 'New User Manager'));

$options_panel->Title(__("<br><br>ABX Dashboard"));
$options_panel->addCheckbox('show_clinical_filter', array('name' => 'Show clinical filter'));
$options_panel->addCheckbox('abx_db_base_batch_id', array('name' => 'Query by Base Batch ID'));
$options_panel->addCheckbox('abx_db_positive_by_all_pathogens', array('name' => 'Positive by all Pathogens'));
$options_panel->addCheckbox('abx_genotypic_antibiogram', array('name' => 'Show Genotypic Antibiogram'));

$options_panel->Title(__("<br><br>Features"));
$options_panel->addCheckbox('report_schedule', array('name' => 'Email Report Schedule'));
$options_panel->addCheckbox('report_dispatch_schedule', array('name' => 'Set Up Dispatch'));

$options_panel->Title(__("<br><br>Reditect & WP Menus"));
$options_panel->addCheckbox('redirect_front', array('name' => 'Redirect Front to admin'));
$options_panel->addCheckbox('hide_wp_menu', array('name' => 'Remove WP Menus'));

$options_panel->Title(__("<br><br>Others"));
$options_panel->addCheckbox('fix_panel_colors', array('name' => 'Fix Panel Colors'));
$options_panel->addCheckbox('enable_new_supply_store', array('name' => 'Enable new Supply Store'));

$options_panel->addCheckbox('close_lab', array('name' => 'Close Lab'));
$options_panel->addCheckbox('disable_upload_results', array('name' => 'Disable Upload Results'));
$options_panel->CloseTab();




$options_panel->OpenTab('eFaxInfo_tab');
$options_panel->Title(__("Fax"));

$optionst =  array(
    '' => 'After Approved',
);
for ($i = 1; $i <= 24; $i++) {
    $optionst[$i] = $i . ':00';
}
$options_panel->addSelect('fax_time', $optionst, array('name' => 'Auto Fax Time', 'std' => ''));

foreach (tr_pdd_get_batch_types() as $btype) {
    $options_panel->addCheckbox('auto_fax_approve_' . $btype, array('name' => 'Auto Fax when Approved ' . strtoupper($btype)));
}

$options_panel->addSelect(
    'fax_api',
    array(
        '' => 'None',
        'CI_Fax_RingCentral' => 'Ring Central',
        'CI_Fax_Faxage' => 'Faxage',
    ),
    array('name' => 'Fax API', 'std' => 'CI_Fax_RingCentral')
);

$options_panel->CloseTab();





$options_panel->OpenTab('supply_store');
$options_panel->Title(__("Supply Store"));
$options_panel->addTextarea('store_html', array('name' => 'Html', 'std' => ''));
$options_panel->CloseTab();




$options_panel->OpenTab('qr_generator_tab');
$options_panel->Title(__("QR Generator"));
$options_panel->addCustom('qr_generator', array('name' => ''));
$options_panel->CloseTab();




$options_panel->OpenTab('barcode_tab');
$options_panel->Title(__("Barcode Settings"));
$options_panel->addText('barcode_key', array('name' => 'First Key', 'std' => 'ILS', 'desc' => ''));
$options_panel->addText('barcode_length', array('name' => 'Number Length', 'std' => '7', 'desc' => ''));
$options_panel->addText('barcode_start', array('name' => 'Start Number', 'std' => '0000001', 'desc' => 'Number now: ' . $number_now));

$options_panel->addCheckbox('barcode_doc', array('name' => 'Add DOC to label'));
$options_panel->addCheckbox('mp_lb', array('name' => 'Multi Panel in one label'));
$options_panel->addCheckbox('disable_successful_print_popup', array('name' => 'Disable successful print popup'));

foreach (tr_pdd_get_batch_types() as $btype) {
    $options_panel->addText($btype . '_print_times', array('name' => strtoupper($btype) . ' print times', 'std' => 1));
}
$options_panel->addText('quintake_print_times', array('name' => 'Quick Intake print times', 'std' => 1));

$options_panel->addCheckbox('print_zebra', array('name' => 'Use Zebra printer', 'target' => 'print_zebra_size'));
$options_panel->addSelect(
    'print_zebra_size',
    array(
        '2x1' => '2x1 inch',
        '3x1.5' => '3x1.5 inch',
        '3x2'  => '3x2 inch',
        '4x2'  => '4x2 inch',
    ),
    array('name' => 'Zebra Label Size', 'max' => 10, 'std' => '2x1', 'desc' => 'Default:2x1 ' . $test_print_html)
);


$options_panel->Title(__("<br><br>Barcode Normal"));
$options_panel->addText('barcode_width', array('name' => 'Size Width', 'std' => '200', 'desc' => 'Pixel'));
$options_panel->addText('barcode_height', array('name' => 'Size Height', 'std' => '96', 'desc' => 'Pixcel'));

$options_panel->Title(__("<br><br>Barcode Short Form"));
$options_panel->addText('barcode_width_short', array('name' => 'Size Width', 'std' => '200', 'desc' => 'Pixel'));
$options_panel->addText('barcode_height_short', array('name' => 'Size Height', 'std' => '96', 'desc' => 'Pixcel'));

$options_panel->Title("<br><br>Receiving Form");
$options_panel->addText('barcode_width_rec', array('name' => 'Size Width', 'std' => '200', 'desc' => 'Pixel'));
$options_panel->addText('barcode_height_rec', array('name' => 'Size Height', 'std' => '120', 'desc' => 'Pixcel'));
$options_panel->addCheckbox('barcode_vertical_rec', array('name' => 'Show Vertical'));
$options_panel->CloseTab();




$options_panel->OpenTab('printbarcode_tab');
$options_panel->Title(__("Print Barcode Sets"));
ob_start();
?>
<div class="row"><label>Start Barcode</label><span class="barkey"></span><input type="number" id="startbarcode" /></div>
<div class="row"><label>End Barcode</label><span class="barkey"></span><input type="number" id="endbarcode" /></div>
<div class="row"><label>QTY</label><input type="number" id="qtybc" value="1" style="margin-left:18px" /></div>
<br>
<div>
    <a class="button print_assen_btn_all">Print</a>
    <a class="button stopbtn" style="display: none;" onclick="location.reload()">Stop</a>
    <br>
    <span id="printing"></span>
</div>
<style>
    form .row label {
        line-height: 28px;
    }
</style>
<script>
    jQuery(function($) {
        $('#barcode_key, #barcode_length').change(function() {
            var $length = $('#barcode_length').val();
            $('#startbarcode').val('6' + ('0'.repeat($length - 1)));
            $('#endbarcode').val('6' + ('0'.repeat($length - 5)) + '1000');
            $('.barkey').html($('#barcode_key').val());
        }).change();
        $('a.print_assen_btn_all').click(function() {
            var $this = $(this);
            if (typeof window.tr_hold_print_button === 'function') {
                if (!window.tr_hold_print_button($this, 'Printing...')) return false;
            } else if ($this.html() == 'Printing...') {
                return false;
            }
            var $bkey = $('#barcode_key').val();
            var $start = parseInt($('#startbarcode').val());
            var $end = parseInt($('#endbarcode').val());
            var $qty = parseInt($('#qtybc').val());
            if ($end >= $start) {
                var data = [];
                for (i = $start; i <= $end; i++) {
                    for (j = 1; j <= $qty; j++) {
                        var $barcode = $bkey + i;
                        var $row = {
                            barcode: $barcode,
                            patient_name: "",
                            position: "",
                            panels: "",
                            cul: ""
                        };
                        data.push($row);
                    }
                }
                if (typeof window.tr_hold_print_button !== 'function') {
                    $this.html('Printing...');
                }
                $('.stopbtn').show();
                $.printLabels(data, 0, function() {
                    if (typeof window.tr_release_print_button === 'function') {
                        window.tr_release_print_button($this);
                    } else {
                        $this.html('Print');
                    }
                    $('.stopbtn').hide();
                }, function(row) {
                    $('#printing').html(row.barcode);
                });
            }
        })
    })
</script>
<?php
$html = ob_get_clean();
$options_panel->addParagraph($html);
$options_panel->CloseTab();



$options_panel->OpenTab('forms_tab');
$options_panel->Title(__("Forms"));
$options_panel->addCheckbox('show_abx_clinic', array('name' => 'Show ABX Report in Clinic settings'));
$options_panel->addCheckbox('show_sms_clinic', array('name' => 'Show send sms Report in Clinic settings'));
$options_panel->addCheckbox('show_cbaa_clinic', array('name' => 'Show Auto Assen in Clinic settings'));
$options_panel->addCheckbox('show_note_clinic', array('name' => 'Show Notes field in Clinic settings'));
$options_panel->addCheckbox('df_doctor_clinic', array('name' => 'Enable default doctor'));
$options_panel->addCheckbox('doc_o_cli', array('name' => 'Doctors appear all clinics'));
$options_panel->addCheckbox('doctor_see_all', array('name' => 'Doctors Can see all reqs & Results'));
//$options_panel->addCheckbox('doc_global', array('name' => 'Enable Global Doctors'));
$options_panel->addCheckbox('e_import_doc', array('name' => 'Enable Import Doctors'));

$options_panel->addCheckbox('off_o_cli', array('name' => 'Office Manager appear all clinics'));
$options_panel->addCheckbox('off_see_all', array('name' => 'Office Manager Can see all reqs & Results (all type)'));
$options_panel->addCheckbox('forms_droffic', array('name' => 'Disable Required Office managers'));
$options_panel->addCheckbox('e_import_om', array('name' => 'Enable Import Office Manager'));
$options_panel->addCheckbox('collector_see_all', array('name' => 'Collectors Can see all reqs & Results'));
$options_panel->addCheckbox('coloshisreqs', array('name' => 'Collector Only show his reqs'));
$options_panel->addCheckbox('msm_in_setupf', array('name' => 'Show "Multi-Site Manager" in setup from'));
$options_panel->addCheckbox('msm_can_show_all', array('name' => '"Multi-Site Manager" can show all clinics'));
$options_panel->addCheckbox('show_group_clinics_to_all_members', array('name' => 'Show group clinics to all members'));
$options_panel->CloseTab();




$options_panel->OpenTab('req_tab');
$options_panel->Title(__("Req Form"));
$options_panel->addCheckbox('enable_duplicate_req', array('name' => 'Enable Duplicate'));
$options_panel->addCheckbox('req_always_editable', array('name' => 'Req always editable'));
$options_panel->addCheckbox('collectorcanuprs', array('name' => 'Allow Collector Upload result in req'));
$options_panel->addCheckbox('req_multi_add', array('name' => 'Enable Add Multi Req Type'));
$options_panel->addCheckbox('req_show_hold', array('name' => 'Enable Hold in Req list'));
$options_panel->addCheckbox('show_submitdate', array('name' => 'Show Submit Date below signature'));
$options_panel->addCheckbox('req_ddelete', array('name' => 'Disable delete'));
$options_panel->addCheckbox('req_auto_rej_ins', array('name' => 'Enable Auto Rejected by Insurance'));
$options_panel->addCheckbox('show_priority_comreqs', array('name' => 'Show Priority Dropdown'));
$options_panel->addCheckbox('show_reflab_comreqs', array('name' => 'Show Ref Lab Dropdown'));

$options_panel->addCheckbox('show_bnt_file_pa_ins', array('name' => 'Show 2 buttons Attach Face Sheet    and     Attach Demographics'));
$options_panel->addCheckbox('allow_type_col', array('name' => 'Allow Type Collector in req forms'));
$options_panel->addCheckbox('discreatedcol', array('name' => 'Dislay User Created req in empty Collector field'));
$options_panel->addCheckbox('req_canadian_provices', array('name' => 'Enable Canadian Provices'));
$options_panel->addCheckbox('req_show_npi', array('name' => 'Show Doctor NPI'));

$options_panel->addCheckbox('disable_rlbcl', array('name' => 'Disable Ref lab by clinic'));

$options_panel->addCheckbox('auto_pa_address', array('name' => 'Auto Patient Address'));
$options_panel->addCheckbox('req_patient_location', array('name' => 'Add Patient Location Field'));
$options_panel->addCheckbox('disable_c_patient', array('name' => 'Disable Change Patient Name in Saved'));
$options_panel->addCheckbox('enable_patient_room', array('name' => 'Enable "Room" in Patient'));
$options_panel->addCheckbox('enable_patient_unit_room_bed', array('name' => 'Enable Patient Unit / Room / Bed'));
$options_panel->addCheckbox('enable_ttmileage', array('name' => 'Enable Total Mileage field'));
$options_panel->addCheckbox('disable_c_patientdob', array('name' => 'Disable Change DoB in Saved'));
$options_panel->addCheckbox('e_hand_editdob', array('name' => 'Allow edit DOB by hand'));
$options_panel->addCheckbox('pa_email_required', array('name' => 'Patient Email Required'));
$options_panel->addCheckbox('pa_phone_notrequired', array('name' => 'Disable Patient Phone Required'));
$options_panel->addCheckbox('pa_address_notrequired', array('name' => 'Disable Patient Address Required'));
$options_panel->addCheckbox('pa_mid_required', array('name' => 'Patient Middle Initial Required'));
$options_panel->addCheckbox('enable_patient_score', array('name' => 'Enable Patient Score'));
$options_panel->addCheckbox('enable_patient_notes', array('name' => 'Enable Patient Notes'));
$options_panel->addCheckbox('enable_patientug', array('name' => 'Enable Patient Unknown gender'));
$options_panel->addCheckbox('enable_patientgdts', array('name' => 'Enable Patient "Decline To Specify" gender'));
$options_panel->addCheckbox('enable_patientgo', array('name' => 'Enable Patient "Other" gender'));
$options_panel->addCheckbox('hide_physicalnotes', array('name' => 'Hide Physician Notes'));
$options_panel->addCheckbox('cht_physicalnotes', array('name' => 'change Physician Notes to Chief Complaint'));
$options_panel->addCheckbox('show_symtomatic', array('name' => 'Show Patient Symtomatic?'));
$options_panel->addCheckbox('show_patient_passport', array('name' => 'Show Patient Passport'));
$options_panel->addCheckbox('show_patient_r3snh', array('name' => 'Show Patient Radio "symptomatic, nursing home, and healthcare worker"'));
$options_panel->addCheckbox('show_patient_county_pregnant', array('name' => 'Enable Patient County...Pregnant'));
$options_panel->addCheckbox('show_patient_sogi', array('name' => 'Enable Patient Sexual Orientation , Gender Identity'));
$options_panel->addCheckbox('show_basic_demographics', array('name' => 'Show Basic Demographics'));
$options_panel->addCheckbox('disable_prev_orders', array('name' => 'Disalbe Fill Prev Orders'));
$options_panel->addCheckbox('auto_fill_dx_code', array('name' => 'Enable Auto fill diagnosis code'));

$options_panel->addCheckbox('show_pid_field', array('name' => 'Show PID(Ref Accession) Field'));
$options_panel->addCheckbox('show_pid_crp', array('name' => 'Show PID in covid reporting export'));


$options_panel->addText('title_pid_field', array('name' => 'PID(Ref Accession) Title', 'std' => 'PID'));

$options_panel->addCheckbox('oldreq_required', array('name' => 'PID(Ref Accession) Required'));

$options_panel->addCheckbox('mrn_required', array('name' => 'MRN Required'));
$options_panel->addCheckbox('limit_doc', array('name' => 'Limit DOC in calendar'));

$options_panel->addCheckbox('current_doc', array('name' => 'Auto fill DOC'));
$options_panel->addCheckbox('aoffice_delete', array('name' => 'Allow Office Manager delete Reqs'));
$options_panel->addCheckbox('show_pregnant', array('name' => 'Show Pregnant in Req form'));
$options_panel->addCheckbox('show_fin', array('name' => 'Show FIN in Req form'));
$options_panel->addCheckbox('show_ehr', array('name' => 'Show EHR in Req form'));

$options_panel->addCheckbox('disable_ssn', array('name' => 'Disalbe SSN'));
$options_panel->addCheckbox('required_ssn', array('name' => 'SSN Required'));
$options_panel->addCheckbox('ecancelorder', array('name' => 'Show Cancel Order'));
$options_panel->addCheckbox('new_batch_status', array('name' => 'Enable new batch status'));

$options_panel->addText('ssn_limit', array('name' => 'SSN Limit', 'size' => '120', 'desc' => ''));

$options_panel->Title(__("<br><br>Insurance"));
$options_panel->addCheckbox('hide_insurance', array('name' => 'Hide Insurance'));
$options_panel->addCheckbox('dropdown_insurance', array('name' => 'Show Insurance as Dropdown'));
$options_panel->addCheckbox('unrequired_insu', array('name' => 'Remove Required for Insurance'));
$options_panel->addCheckbox('unrequired_ainsu', array('name' => 'Remove Required Address for Insurance'));
$options_panel->addCheckbox('insurance_partab', array('name' => 'Enable Insurance Part A,B'));



$options_panel->Title(__("<br><br>TOX"));
$options_panel->addCheckbox('tox_emrs_shortf', array('name' => 'TOX Manual Result in Shortform'));

$options_panel->Title(__("<br><br>PCR"));
$options_panel->addCheckbox('pcr_patient_fvaccine', array('name' => 'Show in patient "Are you fully Vaccinated?"'));
$options_panel->addCheckbox('pcr_diagnostic_required', array('name' => 'PCR Diagnostic code Required'));
$options_panel->addCheckbox('pcr_crs_shortf', array('name' => 'PCR Covid Result in Shortform'));
$options_panel->addCheckbox('pcr_emrs_shortf', array('name' => 'PCR Manual Result in Shortform'));

$options_panel->addCheckbox('pcr_card_images', array('name' => 'Enable Insurance & ID Images'));
$options_panel->addCheckbox('req_cares_act', array('name' => 'Enable CARES Act'));
$options_panel->addCheckbox('pcr_reasont', array('name' => 'Enable Reason for Testing'));


$options_panel->Title(__("<br><br>Blood"));
$options_panel->addCheckbox('blood_dc_required', array('name' => 'Enable diagnosis code Required'));
$options_panel->addCheckbox('blood_card_images', array('name' => 'Enable Insurance & ID Images'));
$options_panel->addCheckbox('blood_hide_worker', array('name' => 'Blood Hide WORKER\'S COMP'));
$options_panel->addCheckbox('blood_medication', array('name' => 'Enable Medication in Blood'));
$options_panel->addCheckbox('hide_fasting', array('name' => 'Blood Hide Fasting'));
$options_panel->addCheckbox('req_blood_cares_act', array('name' => 'Enable CARES Act'));
$options_panel->addCheckbox('enable_stat', array('name' => 'Enable STAT in Blood'));

$options_panel->addText('form_pgx_subject', array('name' => 'PGX subject', 'size' => '120', 'desc' => ''));
$options_panel->addText('form_pgx_mail', array('name' => 'PGX email to', 'size' => '120', 'desc' => ''));
$options_panel->addText('form_blood_subject', array('name' => 'Blood Supplies Subject', 'size' => '120', 'desc' => ''));
$options_panel->addText('form_blood_mail', array('name' => 'Blood Supplies email to', 'size' => '120', 'desc' => ''));
$options_panel->CloseTab();




$options_panel->OpenTab('inbound_tab');
$options_panel->Title(__("Inbound"));
$options_panel->addCheckbox('rejected_issue', array('name' => 'Show Rejected in Specimen issues'));
$options_panel->addCheckbox('inb_lab_ref', array('name' => 'Show Filter by lab/ref lab'));
$options_panel->addCheckbox('mv_bref_top', array('name' => 'Revert Columns Ref Lab and Status to front'));
$options_panel->addCheckbox('inb_rv_mp', array('name' => 'Revert Columns Panels and Medication'));
$options_panel->addCheckbox('inb_paid', array('name' => 'Show Paid column'));
$options_panel->addCheckbox('inb_oldreq', array('name' => 'Show Ref Accession'));

$options_panel->addCheckbox('inb_ssn', array('name' => 'Show SSN column'));
$options_panel->addCheckbox('inb_insmid', array('name' => 'Show Insurance Member ID column'));
$options_panel->addCheckbox('inb_insgid', array('name' => 'Show Insurance Group column'));
$options_panel->addCheckbox('inb_ccbdob', array('name' => 'Move "Clinic City" before "DOB" column'));
$options_panel->addCheckbox('hide_ib_medication', array('name' => 'Hide Medication column'));
$options_panel->addCheckbox('hide_ib_notes', array('name' => 'Hide Notes column'));
$options_panel->addCheckbox('inb_exmf', array('name' => 'Enable export Export MF & Export FA(Blood)'));
$options_panel->addCheckbox('inb_showmf_fa', array('name' => 'Show checkbox MF & FA(Blood)'));
$options_panel->addCheckbox('inb_hqcinb', array('name' => 'Hide QC in Inbound tab'));
$options_panel->addCheckbox('inb_exbat', array('name' => 'Enable export Batches'));
$options_panel->addCheckbox('inb_egiabx', array('name' => 'Enable GI ABX Today Batches'));
$options_panel->addCheckbox('inb_tbpphone', array('name' => 'Show Patient Phone today batches'));
$options_panel->addCheckbox('inb_show_doc_npi', array('name' => 'Show Doctor NPI today batches'));
$options_panel->addCheckbox('inb_show_pcode', array('name' => 'Show Panel Code and LOINC instead of name'));
$options_panel->addCheckbox('inb_exbatrefed', array('name' => 'Show export Reflab transfered'));
$options_panel->addCheckbox('inb_exsamebill', array('name' => 'Export same Insurance Report Billing format'));
$options_panel->addCheckbox('inb_tdb_rsd', array('name' => 'Show Result Date/time in today batch'));
$options_panel->addCheckbox('inb_exibo', array('name' => 'Show Export in Inbound'));
$options_panel->addCheckbox('show_pa_mrn_todayb', array('name' => 'Show Patient ID(patient MRN)'));


$rs = $wpdb->get_results("select lab_ID, lab_name 
           from ci_labs where (lab_is_ref = 0 or lab_is_ref = 2)
            ");
$labs = array();
foreach ($rs as $r) {
    $labs[$r->lab_ID] = $r->lab_name;
}
$options_panel->addSelect(
    'inb_disdeletelids',
    $labs,
    array(
        'name' => 'Disable Delete for labs:',
        'multiple' => true,
        'class' => 'js_select2',
        'style' => 'width:95%'
    )
);


$options_panel->CloseTab();




/*
$options_panel->OpenTab('pverify_tab');
$options_panel->Title(__("PVerify Soap"));
$options_panel->addText('Pverify_UserID',array('name'=>'UserID','desc'=>'','size'=>70));
$options_panel->addText('Pverify_Password',array('name'=>'Password','desc'=>'','size'=>70));
$options_panel->CloseTab();
*/



if (in_array('tox', tr_pdd_get_batch_types())) {
    $options_panel->OpenTab('clinic_static_tab');
    $options_panel->Title(__("Clinic Statistics"));
    $rs    = $wpdb->get_results("select te_ID,te_name from ci_test_panels where te_type='tox' ");
    $tests = array();
    foreach ($rs as $r) {
        $tests[$r->te_ID] = $r->te_name;
    }
    $options_panel->addSelect(
        'cli_static_tests',
        $tests,
        array(
            'name'     => 'Monitored Tests',
            'multiple' => true,
            'class'    => 'js_select2',
            'style'    => 'width:95%'
        )
    );

    $options_panel->addCustom('monitored_tests', array('name' => 'Monitored Tests by Clinic'));
    $options_panel->CloseTab();
}




$options_panel->OpenTab('reports_tab');
$options_panel->Title(__("Reports"));

$options_panel->addCheckbox('ehealth_wellness', array('name' => 'Enable Health and Wellness Review'));
$options_panel->addCheckbox('total_turnaroundave', array('name' => 'Show Total samples in Turnaround Average'));
$options_panel->addCheckbox('report_e_plating', array('name' => 'Enable Plating Report'));
$options_panel->addCheckbox('report_e_ins_veri', array('name' => 'Enable Insurance Verification  Report'));
$options_panel->addCheckbox('show_result_report', array('name' => 'Blood Show Result Report (VCAM/PSEL/MFI3,MFi10)'));


$options_panel->addCheckbox('salerp_hidecs', array('name' => 'Hide columns in sale report for salesman'));
$options_panel->addCheckbox('salerp_hiderj', array('name' => 'Hide Rejected in sale report'));
$options_panel->addCheckbox('pcr_importfluor', array('name' => 'Use fluor as Target Name when import'));
$options_panel->addCheckbox('pcr_importuti', array('name' => 'Use UTI template(Abrev in header)'));


$rs = $wpdb->get_results("select ID, user_login, user_email 
            from {$wpdb->users} u
            join {$wpdb->usermeta} m ON m.user_id = u.ID and m.meta_key='{$wpdb->prefix}capabilities'
            where m.meta_value not like '%patient%'
            ");
$users = array();
foreach ($rs as $r) {
    $users[$r->ID] = $r->user_login . ' (' . $r->user_email . ')';
}


$options_panel->addSelect(
    'report_show_patients',
    $users,
    array(
        'name' => 'Show Patient Name Column in Sale Report for:',
        'multiple' => true,
        'class' => 'js_select2',
        'style' => 'width:95%'
    )
);
$options_panel->addCheckbox('report_show_status_col', array('name' => 'Show Status column in sale report'));
$options_panel->addCheckbox('report_scompleted', array('name' => 'Only Show Completed in Sales Report'));

$options_panel->addCheckbox('report_hide_bp_amount', array('name' => 'Hide Billed amount/paid amount columns'));
$options_panel->addCheckbox('disable_dl', array('name' => 'Disable D/L'));
$options_panel->addCheckbox('disable_Alcohol', array('name' => 'Disable Alcohol'));
$options_panel->addCheckbox('disable_Oral', array('name' => 'Disable Oral'));
$options_panel->addCheckbox('teresult_approvefilter', array('name' => 'Show Filter approve date in Test Results'));
$options_panel->addCheckbox('report_show_athena_id', array('name' => 'Show Athena ID in sample manifest'));
$options_panel->addCheckbox('report_show_panel', array('name' => 'Show Panel in sample manifest'));
$options_panel->addCheckbox('sample_manifest_no_results_tab', array('name' => 'Show No Results tab in sample manifest'));
$options_panel->CloseTab();







$options_panel->OpenTab('covid_reporting_tab');
$options_panel->Title(__("Covid"));
$options_panel->addCheckbox('disable_covid_reporting', array('name' => 'Disable Covid Reporting'));
$options_panel->addCheckbox('pcrcovid_covidnot19', array('name' => 'Remove -19 IN COVID-19 '));
$options_panel->addCheckbox('pcrcovid_in1crpic', array('name' => 'Import N1 as Covid-19, RP as IC'));
$options_panel->addCheckbox('pcrcovid_ifamchexic', array('name' => 'Use FAM as Covid-19, HEX as IC'));

$options_panel->addCheckbox('pcrcovid_cqmtemp', array('name' => 'New Type (CQ & MTemp) '));
$options_panel->addCheckbox('pcrcovid_n12rp', array('name' => 'New Type (N1, N2, RNAsep) '));
$options_panel->addCheckbox('pcrcovid_famvic', array('name' => 'New Type (FAM & VIC) '));
$options_panel->addCheckbox('pcrcovid_n12_r_p', array('name' => 'New Type (N1, N2, RP) '));
$options_panel->addCheckbox('pcrcovid_ms2rp', array('name' => 'New Type (ORF1ab,N Gene & MS2) '));
$options_panel->addCheckbox('pcrcovid_ms2rpsgen', array('name' => 'New Type (ORF1ab,N Gene & S Gene & MS2) '));
$options_panel->addCheckbox('pcrcovid_n1_rp', array('name' => 'New Type (N1, RP) '));
$options_panel->addCheckbox('pcrcovid_rangecl', array('name' => 'Display Range column for covid '));



$options_panel->Title(__("<br><br><br>Covid Reporting"));

$options_panel->addCheckbox('show_racecols', array('name' => 'Show Race & Ethnicity columns '));
$options_panel->addCheckbox('cv_show_sexgen', array('name' => 'Show Sexual Orientation & Gender Identity columns '));
$options_panel->addCheckbox('use_racedesc', array('name' => 'Use Race description '));
$options_panel->addCheckbox('e_export_ma', array('name' => 'Enable Export MA'));
$options_panel->addCheckbox('e_export_2_3', array('name' => 'Enable Export New Template 2.3 for Admin'));
$options_panel->addCheckbox('pcr_dcvbrerun', array('name' => 'Disable Black Rerun (first rerun)'));



$options_panel->Title(__("<br><br>Export to sFtp"));
$options_panel->addCheckbox('e_export_csvsftp', array('name' => 'Enable Export CSV sftp'));
$options_panel->addCheckbox('e_export_csvsftp_all', array('name' => 'Enable Export All Orders (not just covid)'));
$options_panel->addCheckbox('e_export_csvsftp_elink', array('name' => 'Add Elink id to CSV sftp'));
$options_panel->addCheckbox('e_export_csvsftp_tdb', array('name' => 'Enable Export Today\'s Batch'));
$options_panel->addCheckbox('e_export_csvsftp_pro_sampl', array('name' => 'Enable Export Processed Samples'));
$options_panel->addText('export_csv_folder', array('name' => 'Export sftp Folder', 'size' => '120', 'std' => dirname(ABSPATH) . '/sftp/results/'));
$options_panel->addTextarea('export_csv_folder_notes', array('name' => 'Notes'));
$options_panel->CloseTab();


$options_panel->OpenTab('prefill_tab');
$options_panel->Title(__("Prefill"));
$options_panel->addCheckbox('prfc_bulk', array('name' => 'Bulk Req Prefill (all type)'));
$options_panel->addCheckbox('prfc_d_assen', array('name' => 'Remove Accession Required'));
$options_panel->addCheckbox('prfc_e_ssn', array('name' => 'Enable SSN'));
$options_panel->addCheckbox('prfc_e_onlyp', array('name' => 'Enable Only Import Patient'));
$options_panel->addCheckbox('prfc_e_stp', array('name' => 'Enable Select Test Panel'));
$options_panel->addCheckbox('prfc_e_reflab', array('name' => 'Enable Select Ref Lab'));
$options_panel->addText('prfc_dagnostic', array('name' => 'Dagnostic Code'));
$options_panel->CloseTab();


$options_panel->OpenTab('pdf_results_tab');
$options_panel->Title(__("Results & PDF"));
$options_panel->addColor('pdf_main_color', array('name' => 'PDF Main Color'));
$options_panel->addCheckbox('rs_show_finalbtn', array('name' => 'Show Final button in list table'));
$options_panel->addCheckbox('rs_show_viewbtn', array('name' => 'Show View button in list table'));
$options_panel->addCheckbox('rs_show_approvecountfinal', array('name' => 'Show Number approved by Final'));
$options_panel->addCheckbox('pdf_bold_assen', array('name' => 'increse font & bold Accession number'));
$options_panel->addCheckbox('results_oview', array('name' => 'Results Role Only View & Approve'));
$options_panel->addCheckbox('results_dtime', array('name' => 'Show Date/Time'));
$options_panel->addCheckbox('results_sp_adoctor', array('name' => 'Show SPECIALTY/CREDENTIAL after doctor name'));
$options_panel->addCheckbox('report_st_top', array('name' => 'Move Sample Type to top'));
$options_panel->addCheckbox('report_loc_source', array('name' => 'Chanege Location to Specimen Source'));
$options_panel->addCheckbox('report_hilaphone', array('name' => 'Hide Lab Phone'));
$options_panel->addCheckbox('report_green_neg', array('name' => 'Green color for Negative'));
$options_panel->addCheckbox('pdf_hide_phone', array('name' => 'Hide Patient Phone'));
$options_panel->addCheckbox('pdf_hide_address', array('name' => 'Hide Patient Address'));
$options_panel->addCheckbox('pdf_show_npi', array('name' => 'Show Doctor NPI'));
$options_panel->addCheckbox('pdf_approved_by', array('name' => 'Enable Approved by'));
$options_panel->addCheckbox('pdf_with_refnumber', array('name' => 'Add Ref Number to PDF filename'));
$options_panel->addCheckbox('pdf_show_clinic_phone', array('name' => 'Show Clinic Phone in PDF'));
$options_panel->addCheckbox('pdf_show_age', array('name' => 'Show Age after DOB in PDF'));

$options_panel->addCheckbox('pdf_browser', array('name' => 'Open PDF in Browser'));

$options_panel->addCheckbox('show_pa_mrnasid', array('name' => 'Show Patient MRN as Patient ID in PDF'));
$options_panel->addCheckbox('show_patient_refid', array('name' => 'Show Medical Record Number ( Patient ID) in PDF'));
$options_panel->addCheckbox('pdf_amended_results', array('name' => 'Put "Amended Results" above the panel title'));


$options_panel->addSelect('pdf_filename', array(
    '' => 'Default',
    'mrn-date-name-panel' => 'MRN-Date-Name-Panel.pdf',
    'mrn-date-name-panelcode' => 'MRN-Date-Name_Panelcode.pdf',
), array('name' => 'PDF Name Format'));



$options_panel->Title(__("<br><br><br>Tox Report"));
$options_panel->addSelect('tox_pdf_style', trpdd_list_report_styles('tox'), array('name' => 'Default Style', 'std' => 1));


$options_panel->Title(__("<br><br><br>PCR Report"));
$options_panel->addText('pcr_pdf_title', array('name' => 'Default PDF Title', 'size' => '120', 'desc' => ''));
$options_panel->addSelect('pcr_pdf_style', trpdd_list_report_styles('pcr'), array('name' => 'Default Style'));
$options_panel->addCheckbox('pcrpdf_show_diagnostic', array('name' => 'Show Diagnostic Code In PCR PDF'));
$options_panel->addCheckbox('pcrpdf_show_ref', array('name' => 'Show Ref Accession In PCR PDF'));
$options_panel->addCheckbox('pcr_pdf_hide_ct', array('name' => 'Hide PDF CT column(Cycle Threshold*) '));



$options_panel->addText('pcr_proceduralcode', array('name' => 'PCR Procedural codes In PDF', 'size' => '120', 'desc' => ''));
$options_panel->addText('pcr_proceduralcode_antigen', array('name' => 'PCR Procedural codes for Antigen', 'size' => '120', 'desc' => '0 for dont show'));
$options_panel->addText('pcr_proceduralcode_uti', array('name' => 'PCR Procedural codes for UTI', 'size' => '120', 'desc' => '0 for dont show'));

$options_panel->addCheckbox('uti_hide_phone_address', array('name' => 'Hide Patient Phone & Address for UTI'));
$options_panel->addCheckbox('uti_bold_text', array('name' => 'Use Bold Text for Positive UTI'));



$options_panel->addCheckbox('pcr_pdf_antigen', array('name' => 'Change PDF Title for Antigen panel', 'desc' => '', 'target' => 'pcr_pdf_antigentext'));
$options_panel->addText('pcr_pdf_antigentext', array('name' => 'PDF Title for Antigen', 'size' => '120', 'desc' => ''));
$options_panel->addCheckbox('pcr_pos_top', array('name' => 'Show Positive top for Results Role(PCR List table)'));
$options_panel->addCheckbox('pcr_notaqcnan', array('name' => 'Not allow approve both NaN or one empty in Data QC'));
$options_panel->addCheckbox('notshow17', array('name' => 'Disable Warning below 17'));
$options_panel->addCheckbox('pcr_qcshowplate', array('name' => 'Show Plate/Well column in Data QC'));
$options_panel->addCheckbox('pcr_cv_file', array('name' => 'Use Covid Result in file import'));

$options_panel->Title(__("<br><br><br>Blood Report"));
$options_panel->addText('blood_pdf_title', array('name' => 'Default PDF Title', 'size' => '120', 'desc' => ''));

$options_panel->addSelect('blood_pdf_style', trpdd_list_report_styles('blood'), array('name' => 'Default Style', 'std' => 4));

$options_panel->addCheckbox('blood_spcode', array('name' => 'Show Patient code in results'));
$options_panel->addCheckbox('show_result_mrn', array('name' => 'Show MRN In Result pdf'));
$options_panel->addCheckbox('bloodpdf_show_diagnostic', array('name' => 'Show Diagnostic Code In Blood PDF'));
$options_panel->addCheckbox('blood_pdf_show_conc', array('name' => 'Show Result value Column In PDF'));
$options_panel->addText('blood_proceduralcode', array('name' => 'Blood Procedural codes In PDF', 'size' => '120', 'desc' => ''));
$options_panel->CloseTab();



include(trpdd_get_file_path('reportabx', 'settings'));


$options_panel->OpenTab('receiving_tab');
$options_panel->Title(__("Receiving"));
$options_panel->addCheckbox('receiving_w3d', array('name' => 'Show Warning Sample is older than {number} days! ', 'target' => 'receiving_w3dnum'));
$options_panel->addText('receiving_w3dnum', array('name' => 'Number Days', 'std' => 3));
$options_panel->addCheckbox('receiving_noedit', array('name' => 'Turn Off Editable Receiving date '));
$options_panel->addCheckbox('receiving_offtime', array('name' => 'Turn Off Time'));
$options_panel->addCheckbox('receiving_sdob', array('name' => 'Show DOB after patient name '));
$options_panel->addCheckbox('receiving_hdoctor', array('name' => 'Hide Doctor Column '));
$options_panel->addCheckbox('receiving_sinsu', array('name' => 'Show Insurance Column '));
$options_panel->addCheckbox('receiving_print2', array('name' => 'Print 2 labels'));
$options_panel->addCheckbox('receiving_time_bg', array('name' => 'Show Background by DOC Time'));
$options_panel->addCheckbox('receiving_priority_bg', array('name' => 'Show Background by Priority'));
$options_panel->addCheckbox('receiving_stat_alert', array('name' => 'Show STAT Alert'));
$options_panel->addCheckbox('receiving_saved', array('name' => 'allow to save and received saved accession'));
$options_panel->addCheckbox('receiving_mani', array('name' => 'Show Manifest'));
$options_panel->addTExt('receiving_dfbcode', array('name' => 'Default barcode'));
$options_panel->addTexts('receiving_keys', array('name' => 'Check Start Keys'));
$options_panel->CloseTab();





$options_panel->OpenTab('rack_plate_tab');
$options_panel->Title(__("Rack & Plate"));
$options_panel->addCheckbox('plate_coating', array('name' => 'enable Plate Coating'));
$options_panel->addCheckbox('rack_ABC', array('name' => 'Rack A1,B1,C1'));
$options_panel->addCheckbox('rack_mixed384', array('name' => 'Rack Mixed(UTI,STI,RPP)384')); //ecohealthcenter

$options_panel->addCheckbox('rack_16', array('name' => 'Rack 16 well(NTC at end)'));

$options_panel->addCheckbox('rack_6controls', array('name' => 'Rack 6 Controls(H7-H12)'));
$options_panel->addCheckbox('rack_ntc_at_end', array('name' => 'NTC at end(H11-H12)'));
$options_panel->addCheckbox('rack_4s_well_posneg', array('name' => 'NTC 4(12E and 12F Negative Control - 12G and 12H Positive Control)'));
$options_panel->addCheckbox('rack_4s_well', array('name' => 'Allow Multi samples per Well', 'target' => 'rack_numberp_well'));
$options_panel->addText('rack_numberp_well', array('name' => 'Number samples per Well', 'std' => 4));
$options_panel->addCheckbox('rack_hide_well', array('name' => 'Hide Rack Well Designation column'));

$options_panel->addSelect(
    'rack_uti_type',
    array(
        '' => 'Default',
        '3' => '3 Samples',
        '14' => '14 Samples',
        '30' => '30 Samples'
    ),
    array(
        'name' => 'UTI & Wound Type',
        'style' => 'width:95%'
    )
);

$options_panel->addCheckbox('rack_max6_fun', array('name' => 'Max 6 Samples for Fungal'));
$panels = array();
$rs = $wpdb->get_results("select tg_ID, tg_name, tg_type 
                from ci_test_groups where tg_show_req = 1 
                and tg_type in ('pcr','blood')
                order by tg_type asc, tg_name asc");
foreach ($rs as $r) {
    $panels[$r->tg_ID] = $r->tg_type . ' : ' . $r->tg_name;
}
$options_panel->addSelect(
    'rack_panels',
    $panels,
    array(
        'name' => 'Show Panels dropdown in Rack',
        'multiple' => true,
        'class' => 'js_select2',
        'style' => 'width:95%'
    )
);

$options_panel->addSelect(
    'rack_exporttype',
    array(
        '' => 'Default',
        'rpp' => 'Studio Plex'
    ),
    array(
        'name' => 'Studio Export Type',
        'multiple' => false,
        'class' => 'js_select2',
        'style' => 'width:95%'
    )
);

$options_panel->addCheckbox('rack_warerun', array('name' => 'Warning when add Rerun'));
$options_panel->addCheckbox('rack_printht', array('name' => 'Add Hood & Tech. Initials... to print'));

$options_panel->addCheckbox('rack_plate_remove_combine', array('name' => 'Remove Combine buttons'));
$options_panel->addCheckbox('rack_plate_logtab', array('name' => 'Enable Plate Log tab'));
$options_panel->addCheckbox('rack_plate_sync', array('name' => 'Enable Sync Rack to Plate'));
$options_panel->addCheckbox('rack_plate_ddel', array('name' => 'Disable Delete Plate button'));


$options_panel->CloseTab();




$options_panel->OpenTab('fulfillment_tab');
$options_panel->Title(__("Fulfillment"));
$options_panel->addCheckbox('e_ful_panels', array('name' => 'Enable Fulfillment count by panels'));
$options_panel->CloseTab();



$options_panel->OpenTab('enable_apis');
$options_panel->Title(__("Instruments"));

require(TRCPDD_PATH . 'inc/define_instruments.php');
foreach ($define_instruments as $k => $arr) {
    $options_panel->addCheckbox($k, array('name' => 'Enable ' . $arr['name'] . ' (' . $arr['desc'] . ')'));
}

$options_panel->Title(__("<br><br>APIs"));

require_once(TRCPDD_PATH . 'inc/define_apis.php');
foreach ($define_apis as $k => $arr) {
    $options_panel->addCheckbox($k, array('name' => 'Enable ' . $arr['name']));
}

$options_panel->addCheckbox('sms_notification', array('name' => 'Enable SMS Notify'));
$options_panel->addCheckbox('api_all_tests_same_code', array('name' => 'report all test same code'));


$options_panel->Title(__("<br><br>Server Service"));
$options_panel->addCheckbox('sv_64combined', array('name' => 'Show base64 in result_combined'));
$options_panel->addCheckbox('e_ws_sample_status', array('name' => 'Enable Sample Status function'));
$options_panel->CloseTab();
