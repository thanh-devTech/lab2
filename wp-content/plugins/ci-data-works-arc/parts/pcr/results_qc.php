<?php
include_once(TRCPDD_PATH . 'pages/formdata.php');

$sub_tabs = array();
$instruments = get_option('trinstruments_pcr', array());
foreach ($instruments as $ins) {
    $sub_tabs['ins_' . $ins] = $ins;
}
$sub_tabs['qc_setup'] = 'QC Setup';

$subtab = !empty($_REQUEST['subtab']) ? $_REQUEST['subtab'] : key($sub_tabs);
if (!isset($_REQUEST['trajax'])) {
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($sub_tabs as $k => $txt) {
        $class = $subtab == $k ? 'nav-tab-active' : '';
        $link = Tr_Base_Class_V4::link(array('subtab' => $k, 'act' => '', 'id' => '', 'mid' => '', 'paged' => '', 's' => ''), false);
        echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
    }
    echo '</h2>';
}

if (stripos($subtab, 'ins_') !== false) {
    $instrument = str_ireplace('ins_', '', $subtab);
    include(trpdd_get_file_path('pcr', 'results_qc_ins'));
} else {
    include(trpdd_get_file_path('pcr', 'results_qc_setup'));
}
