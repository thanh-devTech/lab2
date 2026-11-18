<?php
global $wpdb;


$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid                    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$asset_url 	    		= isset($_GET['html']) ? TRCPDD_URL : TRCPDD_PATH;

$sum                    = true; //now live sum
$has_report             = false;
$has_style2             = false;
$missing_tests			= array();
$upload_dir             = tr_wp_upload_dir();
$logo_dir               = $upload_dir['basedir'] . '/logos/';
$logo_url               = $upload_dir['baseurl'] . '/logos/';


$settings               = get_option('ci_result_settings_req', array());
$notes                  = get_option('ci_notes_result_req', array());

$style_num              = tr_get_option('ci_pdf_data', 'req_pdf_style', 4);
$req_pdf_show_conc    = tr_get_option('ci_pdf_data', 'req_pdf_show_conc');
$pdf_title 			    = tr_get_option('ci_pdf_data', 'req_pdf_title');
$showdatetime           = tr_get_option('ci_pdf_data', 'results_dtime');

$result_dir     		= tr_get_result_upload_dir();
if (isset($_GET['html'])) {
	$result_dir['path'] = $result_dir['url'];
}
$files_dir 				= TRCPDD_PATH . 'reports/req/';

if (empty($pdf_title)) {
	$pdf_title = 'Test Lab Report';
}
$all_genders = array(
	'M' => 'Male',
	'F' => 'Female',
	'O' => 'Other'
);

$testname = 'Diabetes Risk Test';
$samplesource = 'Blood';



if (empty($report['mid']) && $mid > 0) {
	$report['mid'] = $mid;
}
$report = tr_pdd_get_report_by_id($id, 'req', $report['mid']);
$has_report = true;


if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {

	include('results_req_normal.php');
	return;
}
$imagepage1 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/bgpage1.jpg'));
$imageheaderpage2 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage2.jpg'));
$imageheaderpage3 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage3.jpg'));
$imageheaderpage5 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage5.jpg'));
$headerpageresult = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpageresult.jpg'));
$imageheaderpage16 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage16.jpg'));
$imageheaderpage17 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage17.jpg'));
$imageheaderpage18 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage18.jpg'));
$imageheaderpage19 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/headerpage19.jpg'));
$backgroundpage21 = 'data:image/jpg;base64,' . base64_encode(file_get_contents(TRCPDD_PATH . 'reports/images/page21.jpg'));


//get blood values
$pcr_beta_score = array();
$blood_HbA = array();
$blood_C_Peptide = array();
$blood_Insulin = array();

$in_panel_tests_requested = array();
$group_ids = array();
foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$group_ids[] = str_ireplace('group_', '', $t);
	}
}


if (empty($test_ids)) {
	$test_ids = '-1';
}


$where_group     = '';
$group_ids_str     = implode(',', $group_ids);
if (count($group_ids) > 0) {
	$where_group .= " and tg_ID in ({$group_ids_str}) ";
} else {
	$where_group .= ' and 1=2';
}

$sql = "SELECT *
        FROM ci_test_panels tp
        left join ci_results_req rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug) 
                                        and rs.report_id= {$id}
        WHERE  tp.te_type in ('blood','pcr')
        and( 
            (
                (
                    rs.id >0
                    OR
                    (
                        rs.id is null 
                        AND 
                        tp.te_group_id in (select tg_ID from ci_test_groups 
                                            where tg_type = 'req' 
                                            and  tg_manual_edit=1 {$where_group} )
                     )
                ) 
                AND 
                (   
                    tp.te_ID in ({$test_ids}) 
                    OR tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'req' 
                                                and (tg_only_group = 1 or tg_notestcheckbox=1 or tg_manual_edit = 1) {$where_group})
                 ) 
            ) 
            OR
            (
                tp.te_calculation!='' 
                and tp.te_calculation is not null
                and  tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'req' 
                                             {$where_group})
            )
        )
        ";

$date_test = date('Y-m-d', strtotime($report['created']));
$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

$all_results_q = $wpdb->get_results($sql, ARRAY_A);

$all_res = array();
foreach ($all_results_q as $arr) {
	if (empty($arr['drug'])) {
		$arr['drug'] = $arr['te_name'];
	}
	$arr['drug'] = strtoupper($arr['drug']);

	$all_res[$arr['te_ID']] = $arr;
}


$drug_groups = tr_pdd_get_drug_groups('req', $group_ids, true);

$inew       		= 0;
$has_pos    		= '';
$has_normal_group 	= false;
foreach ($drug_groups as $groupid => $groupdata) {

	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

	$groupdata['tests'] = array();

	foreach ($te_IDs as $te_ID) {

		$arr = $all_res[$te_ID];

		if (in_array($groupdata['id'], $group_ids) && ($groupdata['only_group'] || $groupdata['notestcheckbox']) && !in_array($te_ID, $missing_tests)) {
			$missing_tests[] = $te_ID;
		}

		if (!empty($arr)) {

			$arr['showed'] = true;
			if (empty($arr['id'])) {
				$arr['id'] = 'new' . $inew++;
			}
			$arr = tr_pdd_parse_req_test($arr, $report['instrument'], $groupdata['id'], $report);

			$all_res[$te_ID] = $arr;

			if ($arr['te_abrev'] == 'HbA1c' || $arr['te_name'] == 'Hemoglobin A1c') {
				$blood_HbA = $arr;
			} else if ($arr['te_abrev'] == 'CPEP' || $arr['te_name'] == 'C-Peptide') {
				$blood_C_Peptide = $arr;
			} else if ($arr['te_abrev'] == 'INS' || $arr['te_name'] == 'Insulin') {
				$blood_Insulin = $arr;
			} else if ($arr['te_abrev'] == 'BETA' || $arr['te_name'] == 'Beta Score') {
				$pcr_beta_score = $arr;
			}


			$groupdata['tests'][$arr['drug']] = $arr;
		}
	}

	$drug_groups[$groupid] = $groupdata;

	if (isset($groupdata['other_options']['normal_pdf']) && $groupdata['other_options']['normal_pdf']) {
		$has_normal_group = true;
	}
	$report['group_id'] = $groupid;

	if (!empty($groupdata['pdf_title'])) {
		$pdf_title = $groupdata['pdf_title'];
	}
}

if ($has_normal_group) {
	include('results_req_normal.php');
	return;
}

$footer_text    = trpdd_get_footer_text($report, 'req');

if (isset($_GET['debug1'])) {
	var_dump($report);
	echo '<br><br>';
	var_dump($footer_text);
	exit;
}
?>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title><?php echo $report['assen'] ?></title>
	<link href="<?php echo $asset_url . 'css/results_req.css' ?>" rel='stylesheet' />
	<style>
		.page1 {
			background: url(<?php echo $imagepage1 ?>) no-repeat;
		}

		.page2 .pageheader {
			background: url(<?php echo $imageheaderpage2 ?>) no-repeat;
		}

		.page3 .pageheader,
		.page4 .pageheader {
			background: url(<?php echo $imageheaderpage3 ?>) no-repeat;
		}

		.page5 .pageheader,
		.page6 .pageheader,
		.page7 .pageheader {
			background: url(<?php echo $imageheaderpage5 ?>) no-repeat;
		}

		.headerresult .pageheader {
			background: url(<?php echo $headerpageresult ?>) no-repeat;
		}

		.page16 .pageheader {
			background: url(<?php echo $imageheaderpage16 ?>) no-repeat;
		}

		.page17 .pageheader {
			background: url(<?php echo $imageheaderpage17 ?>) no-repeat;
		}

		.page18 .pageheader {
			background: url(<?php echo $imageheaderpage18 ?>) no-repeat;
		}

		.page19 .pageheader {
			background: url(<?php echo $imageheaderpage19 ?>) no-repeat;
		}

		.page21 {
			background: url(<?php echo $backgroundpage21 ?>) no-repeat;
		}

		<?php
		if (isset($_GET['debugpage'])) echo '.staticpage.break{page-break-before:initial !imporant}';
		?>
	</style>
</head>

<body>

	<?php
	$pagenum = 1;
	$total_page = 27;
	for ($i = 2; $i <= $total_page; $i++) {
		if (isset($_GET['debugpage']) && $_GET['debugpage'] != $i) {
			continue;
		}
		$filepath = $files_dir . 'page_' . $i . '.php';
		if (file_exists($filepath)) {
			include($filepath);
		}
	}
	?>

</body>

</html>