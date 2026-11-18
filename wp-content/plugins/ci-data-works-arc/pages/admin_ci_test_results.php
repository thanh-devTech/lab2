<?php
global $wpdb, $current_role, $ci_all_btypes;
global $access_btypes;
global $ci_all_btypes;


$show_filter_approve_date = tr_get_option('ci_pdf_data', 'teresult_approvefilter');
$is_test_results    = true;
$user               = wp_get_current_user();
$user_id            = get_current_user_id();
$today              = date_i18n('Y-m-d');
$fromdate           = isset($_GET['fromdate']) ? $_GET['fromdate'] : '';
$todate             = isset($_GET['todate']) ? $_GET['todate'] : '';
$is_robots          = isset($_GET['trajax']) && $_GET['trajax'] == 'robots';
$where_limit_smart  = '';
$where              = '';
$has_filter         = false;
$show_clinic        = $show_doctor = false;

$tabs = array();

if (empty($access_btypes)) {
    $access_btypes = $ci_all_btypes;
}

foreach ($access_btypes as $btype) {
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);
    $countrs = $wpdb->get_var("select count(*) from {$report_tbl}  ");
    if ($countrs > 0) {
        $tabs[$btype] = tr_pdd_get_typename($btype) . ' Results';
    }
}


if (tr_current_role_in(array('office_manager')) || $current_role == 'clinic') {

    foreach ($tabs as $k => $txt) {
        $show = get_user_meta(get_current_user_id(), '_' . $k, true);

        if ($show == false) {
            unset($tabs[$k]);
        }
    }
} else if (tr_current_role_in(array('multi_site_manager'))) {
} else if ($current_role == 'doctor' || $current_role == 'collector') {
    foreach ($tabs as $k => $txt) {
        $cap = 'form_' . $k;
        if ($k == 'tox') {
            $cap = 'form_requisition';
        } else if ($k == 'dna') {
            $cap = 'form_lab_test_req';
        }


        if (!user_can($user_id, $cap)) {
            unset($tabs[$k]);
        }
    }
    //$tabs['specimen_issues'] = 'Specimen Issues';
}

$tab = isset($_REQUEST['tab']) && !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (!isset($_REQUEST['trajax'])) {

    include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
    <h2 class="nav-tab-wrapper">
        <?php
        foreach ($tabs as $k => $txt) {
            if ($k === 'rpp_gi') continue;
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link  = Tr_Base_Class_V6::link(array(
                'tab'             => $k,
                'act'             => '',
                'id'              => '',
                'mid'             => '',
                'paged'           => '',
                's'               => '',
                'clinic'          => '',
                'doctor'          => '',
                'new_results'     => '',
                'revised_results' => '',
                'spc'             => '',
                'lorh'            => ''
            ), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
<?php
}




if ($tab == 'specimen_issues') {
    return include_once(trpdd_get_file_path('inbound', 'specimen_issues'));
} else if ($tab != 'tox') {
    $btype = $tab;
    return include(trpdd_get_file_path($tab, $is_robots ? 'results_for_robots' : 'results'));
} else {
    $btype = 'tox';
    return include(trpdd_get_file_path('tox', $is_robots ? 'results_for_robots' : 'test_results'));
}
