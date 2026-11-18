<?php
global $wpdb, $current_lab_id, $current_role, $blog_id, $ci_active_menu, $show_menus_options;

$user_id        = get_current_user_id();
$user           = wp_get_current_user();
$use_select2    = true;
$settings       = tr_billing_get_settings();

//check db version
$db_version = get_option('TRCDBI_V');
if (TRCDBI_V != $db_version) {
    trcdbi_plugin_activate();
}

$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';

$types = array(
    0 => 'tox',
);
if (function_exists('tr_pdd_get_batch_types')) {
    $types = trbilling_req_types();
    unset($types[17]); //study
}

if (!in_array($btype, $types)) {
    $btype = array_key_first($types);
}



$bill_statuses = array(
    '' => 'Select',
    'Need Billing' => 'Need Billing',
    'Billed' => 'Billed',
    'Rebilled' => 'Rebilled',
    'Need Info' => 'Need Info',
    'Partial Pay' => 'Partial Pay',
    'Paid' => 'Paid',
    'Do Not Bill' => 'Do Not Bill',
    'Do not Bill-Verified' => 'Do not Bill-Verified'
);


$tabs = array(
    '' => 'Specimen Billing',
    'import' => 'Import',
    'reports' => 'Reports',
    'insurance_reports' => 'Insurance Reports',
    'ins_upload' => 'Insurance Upload',
    'pay_type' => 'Pay Type',
    'doctor' => 'Doctor',
    'bc' => 'Billing Codes',
    'apis' => 'APIs',
    'settings' => 'Settings',
);


if ($current_role == 'BillbyClinic') {
    $tabs = array(
        '' => 'Specimen Billing',
        'reports' => 'Reports',
        'insurance_reports' => 'Insurance Reports',
    );
}


if (!$settings['enable_pay_type']) {
    unset($tabs['pay_type']);
    unset($tabs['reports']);
}


if (!$settings['enable_doctortb']) {
    unset($tabs['doctor']);
}

if (!$settings['enable_ins_upload']) {
    unset($tabs['ins_upload']);
}

if (!$settings['enable_ins_report']) {
    unset($tabs['insurance_reports']);
}
if (!$settings['enable_api_sftp'] || !in_array($current_role, array('administrator', 'Billing', 'BillbyClinic'))) {
    unset($tabs['apis']);
}

if (!in_array($current_role, array('administrator', 'administrator_p', 'AdminCIDW', 'lab_tech', 'Billing', 'BillbyClinic'))) {
    unset($tabs['bc']);
}


if (!in_array($current_role, array('administrator'))) {
    unset($tabs['settings']);
}


$conditions_file        = TRCPDD_PATH . 'pages/admin_ci_conditions.php';
$max_udc_approve_file   = TRCPDD_PATH . 'pages/admin_ci_max_udc_approve.php';

if (file_exists($conditions_file)) {
    $tabs['exceptions'] = 'Exceptions';
}

if (file_exists($max_udc_approve_file)) {
    $tabs['udc_approve'] = 'Max UDC';
}
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (!isset($_REQUEST['trajax'])) {
    include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
    <h2 class="nav-tab-wrapper">
        <?php

        foreach ($tabs as $k => $txt) {
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link  = Tr_Base_Class_V4::link(array(
                'tab'          => $k,
                'act'          => '',
                'id'           => '',
                'mid'          => '',
                'paged'        => '',
                's'            => '',
                'step'         => '',
                'insurance'    => '',
                'claim_number' => '',
                'rep'          => '',
                'bill_status'  => '',
                'btype'  => '',
                'paytype'  => '',
                'fromdate'  => '',
                'todate'  => '',
                'cli_ID'  => '',
                'lab_reflab'  => '',
                'billing'  => '',
            ), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
    <?php
}


switch ($tab) {
    case 'bc':
        $sub_tabs = array(
            'bc_commercial' => 'Commercial',
            'bc_medicare' => 'Medicare',
            'bc_commercial_kit' => 'Commercial Kit',
            'bc_medicaid' => 'Medicaid',
            'bc_cashpay' => 'Cash Pay',
            'bc_client_bill' => 'Client bill',
            'codes_medicare' => 'Medicare',
        );
        if (!$settings['enable_commercial_kit']) {
            unset($sub_tabs['bc_commercial_kit']);
        }

        if (!$settings['enable_medicare']) {
            unset($sub_tabs['bc_medicare']);
            unset($sub_tabs['bc_medicaid']);
        } else {
            unset($sub_tabs['codes_medicare']);
        }



        $sub_tab = isset($_REQUEST['stab']) ? $_REQUEST['stab'] : key($sub_tabs);
        if (!isset($sub_tabs[$sub_tab])) {
            $sub_tab = $sub_tab == 'codes_medicare' && isset($sub_tabs['bc_medicare']) ? 'bc_medicare' : key($sub_tabs);
            $_REQUEST['stab'] = $sub_tab;
            $_GET['stab'] = $sub_tab;
        }

        if (!isset($_REQUEST['trajax'])) {
    ?>
            <h2 class="nav-tab-wrapper">
                <?php

                foreach ($sub_tabs as $k => $txt) {
                    $class = $sub_tab == $k ? 'nav-tab-active' : '';
                    $link  = Tr_Base_Class_V4::link(array(
                        'stab'          => $k,
                        'act'          => '',
                        'id'           => '',
                        'btype'           => '',
                        'mid'          => '',
                        'paged'        => '',
                        's'            => '',
                        'step'         => '',
                        'insurance'    => '',
                        'claim_number' => '',
                        'rep'          => '',
                        'bill_status'  => '',
                        'paytype'  => '',
                        'fromdate'  => '',
                        'todate'  => '',
                        'cli_ID'  => '',
                        'billing'  => '',
                    ), false);
                    echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
                }
                ?>
            </h2>
<?php
        }
        switch ($sub_tab) {
            case 'bc_commercial':
                include('admin_ci_bc_commercial.php');
                break;
            case 'bc_medicare':
                include('admin_ci_bc_medicare.php');
                break;
            case 'bc_medicaid':
                include('admin_ci_bc_medicaid.php');
                break;
            case 'bc_cashpay':
                include('admin_ci_bc_cashpay.php');
                break;
            case 'bc_client_bill':
                include('admin_ci_bc_client_bill.php');
                break;
            case 'bc_commercial_kit':
                include('admin_ci_bc_commercial_kit.php');
                break;
            case 'codes_medicare':
                include('admin_ci_codes_medicare.php');
                break;
        }
        break;

    case 'import':
        include('admin_ci_import_form.php');
        break;

    case 'ins_upload':
        include('admin_ci_ins_upload.php');
        break;

    case 'exceptions':
        include($conditions_file);
        break;

    case 'udc_approve':
        include($max_udc_approve_file);
        break;

    case 'settings':
        include('admin_ci_billing_settings.php');
        break;

    case 'pay_type':
        include('admin_ci_pay_type.php');
        break;

    case 'doctor':
        include('admin_ci_doctor_tbl.php');
        break;

    case 'reports':
        include('admin_ci_reports.php');
        break;

    case 'insurance_reports':
        include('admin_ci_insurance_reports.php');
        break;

    case 'apis':
        include('admin_ci_apis.php');
        break;

    default:

        if ($act == 'edit' || $act == 'view') {
            $hide_tabs = $_REQUEST['not_show_tab'] = true;
            $file = TRCDBI_PATH . 'pages/admin_ci_billing_edit.php';
            if ($btype != 'tox') {
                $file = TRCDBI_PATH . 'pages/admin_ci_billing_edit_' . $btype . '.php';
            }

            if (!file_exists($file) && function_exists('tr_pdd_get_req_file')) {
                $billing_page = true;
                $file = tr_pdd_get_req_file($btype);
            } else if (!file_exists($file)) {
                $billing_page = true;
                $file = TRCPDD_PATH . 'pages/admin_form_' . $btype . '.php';
            }
            include_once($file);
        } else {
            include('admin_ci_billing_list.php');
        }
        break;
}
