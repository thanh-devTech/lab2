<?php
global $wpdb, $current_lab_id;

$tabs = array(
	'export' => 'Export',
	'abx' => 'ABX Export',
	'abx_report' => 'ABX Report',
	'abx_test' => 'ABX Test',
	'tools' => 'Tools',
);

$tab        = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($_REQUEST['trajax'])) {
	echo '<div class="wrap">';
	echo '<h2 class="nav-tab-wrapper dontprint">';
	foreach ($tabs as $k => $ttt) {
		$class = $tab ==  $k ? 'nav-tab-active' : '';
		$link  = Tr_Base_Class_V6::link(array(
			'tab'         => $k,
			'cid'         => '',
			'm'         => '',
			'fdate'         => '',
			'rptype'         => '',
			'act' => '',
		), false);
		echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $ttt . '</a>';
	}
	echo '</h2>';
	echo '<div class="clear"></div>';
	echo '</div>';
	echo '<br>';
}

if ($tab == 'abx') {
	include('abx_avg_bv_load.php');
} else if ($tab == 'abx_test') {
	include('abx_test.php');
} else if ($tab == 'abx_report') {
	include('abx_report.php');
} else if ($tab == 'tools') {
	include('admin_tools.php');
} else {
	include('admin_tools_export.php');
}
