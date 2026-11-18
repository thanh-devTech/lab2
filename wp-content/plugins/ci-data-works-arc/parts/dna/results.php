<?php
global $wpdb, $current_role;

$btype 		= 'dna';
$user_id 	= get_current_user_id();


include_once(TRCPDD_PATH . 'pages/formdata.php');
$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

if (empty($tabs)) {
	$tabs = array(
		'list' => 'PGx Results',
		//  'pending' => 'Pending List',
		'upload' => 'Upload Results'
	);
	if ($disable_upload_results) unset($tabs['upload']);

	if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'lab_director', 'labmanager'))) {
		$tabs['efax_setup'] = 'eFax Setup';
	}
	$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

	if (!isset($_REQUEST['trajax'])) {
?>
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'tab'   => $k,
					'act'   => '',
					'id'    => '',
					'mid'   => '',
					'paged' => '',
					's'     => ''
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>
<?php
	}
}

if ($tab == 'pending') {
	include(trpdd_get_file_path('dna', 'results_pending'));
} else if ($tab == 'upload' && !$disable_upload_results) {
	include(trpdd_get_file_path('dna', 'results_up'));
} else if ($tab == 'efax_setup') {
	include(trpdd_get_file_path('settings', 'efax_setup'));
} else {
	include(trpdd_get_file_path('dna', 'results_list'));
}
