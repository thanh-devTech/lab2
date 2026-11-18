<?php

include_once(TRCPDD_PATH . 'api/ci_hl7_parser.php');
include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'inc/define_instruments.php');
include_once(TRCPDD_PATH . 'pages/formdata.php');

$tabs = array(
	'api_cteh' => 'CTEH',
	'api_ellkay' => 'Ellkay',
	'api_athena' => 'Athena sFtp',
	'api_athena_sftp2' => 'Athena sFtp2',
	'api_athena_api' => 'Athena API',
	'api_solv' => 'Solv',
	'api_billing_sftp' => 'Telcor',
	'api_medhiwa' => 'Medhiwa',
	'api_lifetime' => 'Lifetime',
	'api_evident' => 'Evident',
	'api_provista' => 'Provista/TruMed',
	'api_md_connection' => 'MD Connection',
	'api_sftp_emr' => 'eClinicalWorks',
	'api_coriell' => 'Coriell',
	'api_sftp_pfizer' => 'Pfizer/GBH1',
	'api_sftp_seacoast' => 'Seacoast',
	'api_sftp_allscripts' => 'Allscripts'
);


foreach ($define_apis as $k => $arr) {
	if (tr_get_option('ci_pdf_data', $k)) {
		$tabs[$k] = $arr['name'];
	}
}

foreach ($tabs as $stabk => $subarr) {
	if (
		!in_array($stabk, array('server', 'client', 'api_ringcentral', 'api_faxage'))
		&& !tr_get_option('ci_pdf_data', $stabk)
	) {
		unset($tabs[$stabk]);
	}
}

$tab    = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($_REQUEST['trajax'])) {
?>
	<div class="wrap">
		<h2 class="nav-tab-wrapper">
			<?php
			foreach ($tabs as $k => $arr) {
				$tabname = is_array($arr) ? $arr['name'] : $arr;
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'subtab'   => $k,
					'act'   => '',
					'id'    => '',
					'date'  => '',
					'paged' => '',
					'mt'    => ''
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $tabname . '</a>';
			}
			?>
		</h2>
	</div>

<?php
}
$tabfile = '';
if (!empty($tab)) {
	if (isset($tabs[$tab]['file']) && file_exists($tabs[$tab]['file'])) {
		$tabfile = $tabs[$tab]['file'];
	} else {
		$tabfile = trpdd_get_file_path('instrument', $tab);
	}
}

if (!empty($tabfile)) {
	include($tabfile);
}
