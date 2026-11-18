<?php

function trpdd_cal_ncovid19($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $tr_covid_test_data;

	if (!isset($arr['drug']) || empty($arr['drug'])) {
		if (empty($tr_covid_test_data)) {
			$tr_covid_test_data = $wpdb->get_row(
				"select * 
                    from ci_test_panels 
                    where (te_abrev='nCoV-2019' or te_name='nCoV-2019' ) 
                    and te_type='pcr'",
				ARRAY_A
			);
			if ($tr_covid_test_data) {
				$tr_covid_test_data['drug'] = strtoupper($tr_covid_test_data['te_name']);
			}
		}
		$arr = $tr_covid_test_data;
	}
	$arr['is_covid'] = true;

	//check tests in results table
	$all_covid_tests        = trpdd_get_covid_tests('*', true);
	$type_by_covid_tests    = array();
	$all_tests              = array();
	foreach ($all_covid_tests as $tt => $ts) {
		foreach ($ts as $t) {
			$t = strtoupper($t);
			$all_tests[] = $t;
			$type_by_covid_tests[$t] = $tt;
		}
	}

	if (!isset($arr['list']) || empty($arr['list'])) {
		$all_tests = trpdd_get_covid_tests('*');
		$tests_str = implode("','", $all_tests);
		$rs = $wpdb->get_results("select * from ci_results_pcr 
                    where report_id = {$report_id} 
                        and drug in ('{$tests_str}')", ARRAY_A);

		$arr['list'] = array();
		foreach ($rs as $r) {
			$drug = strtoupper($r['drug']);
			$arr['list'][$drug] = $r;
		}
		if (isset($_REQUEST['debug11'])) {
			var_dump($arr['list']);
		}
	}

	if (empty($arr['list'])) {
		$arr['only_detected'] = true;
		if (!isset($arr['pos-neg'])) {
			$te_name = isset($arr['te_name']) ? $arr['te_name'] : 'SARS-CoV-2 PCR Nucleic Acid';
			$arr['pos-neg'] = $wpdb->get_var("select `pos-neg` from ci_results_pcr
			where report_id = {$report_id}
			and drug = '{$te_name}' ");
		}else{
			$arr = tr_pdd_parse_pcr_test($arr,$instrument, $arr['te_group_id'], $date);
		}
		//check rerun
		if (tr_get_option('ci_result_settings_pcr', 'enable_solana')) {
			$ctrl_pos = $wpdb->get_var("select `pos-neg` from ci_results_pcr where report_id = {$report_id}
		and drug = 'CTRL_POSITIVE' ");
			if ($ctrl_pos == 'NEG') {
				$arr['pos-neg'] = 'RER';
			}
		}
		return trpdd_filter_covid_pos_neg($arr);
	}

	if ((isset($arr['list']['E/N']) || isset($arr['list']['IC'])) && tr_get_option('ci_pdf_data', 'pcrcovid_famvic')) {
		$arr = trpdd_cal_ncovid19_fam_vic($arr, $report_id, $instrument, $date);
	} else if (isset($arr['list']['N1']) && isset($arr['list']['N2']) && tr_get_option('ci_pdf_data', 'pcrcovid_n12_r_p')) {
		$arr = trpdd_cal_ncovid19_n1n2_rp($arr, $report_id, $instrument, $date);
	} else if (isset($arr['list']['N1']) && isset($arr['list']['RP']) && tr_get_option('ci_pdf_data', 'pcrcovid_n1_rp')) {
		$arr = trpdd_cal_ncovid19_n1rp($arr, $report_id, $instrument, $date);
	} else if (isset($arr['list']['N1']) && isset($arr['list']['N2'])) {
		$arr = trpdd_cal_ncovid19_n1n2($arr, $report_id, $instrument, $date);
	} else if (tr_get_option('ci_pdf_data', 'pcrcovid_ms2rpsgen') || (isset($arr['list']['ORF1AB']) && isset($arr['list']['N GENE']) && isset($arr['list']['MS2']))) {
		if (tr_get_option('ci_pdf_data', 'pcrcovid_ms2rpsgen')) {
			$arr = trpdd_cal_ncovid19_ms2_sgen($arr, $report_id, $instrument, $date);
		} else {
			$arr = trpdd_cal_ncovid19_ms2($arr, $report_id, $instrument, $date);
		}
	} else if (tr_get_option('ci_pdf_data', 'pcrcovid_ifamchexic')) {
		$arr = trpdd_cal_covid_fam_hex($arr, $report_id, $instrument, $date);
	} else {
		$arr = trpdd_cal_ncovid19_ic($arr, $report_id, $instrument, $date);
	}

	if ($arr['pos-neg'] == 'NEG' && empty($arr['comments'])) {
		$arr['comments'] = $arr['te_comments_neg'];
	} else if ($arr['pos-neg'] == 'POS' && empty($arr['comments'])) {
		$arr['comments'] = $arr['te_comments'];
	}

	if ($arr['pos-neg'] == 'POS') {
		$arr['notes'] = $arr['te_pdf_note_pos'];
		$arr['pdf2file'] = $arr['te_pdf_file_pos'];
	} else if ($arr['pos-neg'] == 'NEG') {
		$arr['notes'] = $arr['te_pdf_note_neg'];
		$arr['pdf2file'] = $arr['te_pdf_file_neg'];
	}

	$arr['cal_posneg'] = $arr['pos-neg'];

	if (tr_get_option('ci_pdf_data', 'pcr_cv_file')) {
		if (!isset($arr['oldposneg'])) {
			$arr['oldposneg'] = $wpdb->get_var("select `pos-neg` from ci_results_pcr where drug = '{$arr['drug']}' and report_id = {$report_id}");
		}

		if (!empty($arr['oldposneg'])) {
			$arr['pos-neg'] = $arr['oldposneg'];
		}
	}

	//check rerun
	if (tr_get_option('ci_result_settings_pcr', 'enable_solana')) {
		$ctrl_pos = $wpdb->get_var("select `pos-neg` from ci_results_pcr where report_id = {$report_id}
		and drug = 'CTRL_POSITIVE' ");
		if ($ctrl_pos == 'NEG') {
			$arr['pos-neg'] = 'RER';
		}
	}

	if ($arr['pos-neg'] != 'PEN' && !empty($arr['drug'])) {

		$exist = $wpdb->get_row("select * from ci_results_pcr where report_id = {$report_id} and drug = '{$arr['drug']}'");
		if (!$exist) {
			$wpdb->insert('ci_results_pcr', array('drug' => $arr['drug'], 'pos-neg' => $arr['pos-neg'], 'report_id' => $report_id));
		} else if ($arr['pos-neg'] != $exist->{'pos-neg'}) {
			$wpdb->update('ci_results_pcr', array('pos-neg' => $arr['pos-neg']), array('id' => $exist->id));
		}
	}


	return trpdd_filter_covid_pos_neg($arr);
}

function trpdd_filter_covid_pos_neg($arr)
{
	$pos_neg_vls = array(
		'' => 'Select',
		'POS' => 'Positive',
		'NEG' => 'Negative',
		'RER' => 'ReRun',
		'INC' => 'Inconclusive',
		'INV' => 'Invalid'
	);

	if ($arr['te_is_detected'] == '2') {
		if (isset($arr['verylh'])) {
			$pos_neg_vls['PVL'] = 'Very Low';
		}
		$pos_neg_vls['POL'] = 'Low';
		$pos_neg_vls['NEG'] = 'Normal';
		$pos_neg_vls['POH'] = 'High';
		if (isset($arr['verylh'])) {
			$pos_neg_vls['PVH'] = 'Very High';
		}
		unset($pos_neg_vls['POS']);
		if ($arr['pos-neg'] == 'POS') {
			$arr['pos-neg'] = 'POH';
		}
	} else if ($arr['te_is_detected'] == '1') {

		$pos_neg_vls['POS'] = 'Detected';
		$pos_neg_vls['NEG'] = 'Not Detected';
		if (!$arr['only_detected']) {
			if (isset($arr['verylh'])) {
				$pos_neg_vls['PVL'] = 'Detected-Very Low';
			}
			$pos_neg_vls['POL'] = 'Detected-Low';
			$pos_neg_vls['POM'] = tr_get_option('ci_result_settings_pcr', 'pos_moderate', false) ? 'Detected-Moderate' : 'Detected-Medium';
			$pos_neg_vls['POH'] = 'Detected-High';
			if (isset($arr['verylh'])) {
				$pos_neg_vls['PVH'] = 'Detected-Very High';
			}
			unset($pos_neg_vls['POS']);
			if ($arr['pos-neg'] == 'POS') {
				$arr['pos-neg'] = 'POL';
			}
		}
	}
	if ($arr['te_inconclusive_ID'] === '-1') {
		$pos_neg_vls['POS'] = 'SUFFICIENT';
		$pos_neg_vls['NEG'] = 'INSUFFICIENT';
	}
	if (stripos($arr['te_name'], 'MonkeyPox') !== false) {
		$pos_neg_vls['EQU'] = 'Equivocal';
		$pos_neg_vls['INC'] = 'Inconclusive';
	}

	$arr['pos_neg_list'] 	= $pos_neg_vls;
	$arr['flag'] 			= !empty($arr['pos-neg']) && isset($pos_neg_vls[$arr['pos-neg']]) ? $pos_neg_vls[$arr['pos-neg']] : '';

	return $arr;
}

function trpdd_cal_covid_fam_hex($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $covid_19_fam_hex_cutoff;

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}

	if (empty($covid_19_fam_hex_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";
		$covid_19_fam_hex_cutoff = $wpdb->get_var($sql_cut);
	}

	$covid_testnames = trpdd_get_covid_tests('IC');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['FAM']['conc'], 'nan') !== false) {
		$arr['list']['FAM']['conc'] = 45;
	}
	if (stripos($arr['list']['HEX']['conc'], 'nan') !== false) {
		$arr['list']['HEX']['conc'] = 45;
	}


	$arr['list']['FAM']['pos-neg'] = tr_pdd_pass_condition($arr['list']['FAM']['conc'], $covid_19_fam_hex_cutoff) ? 'POS' : 'NEG';
	$arr['list']['HEX']['pos-neg'] = tr_pdd_pass_condition($arr['list']['HEX']['conc'], $covid_19_fam_hex_cutoff) ? 'POS' : 'NEG';

	if ($arr['list']['FAM']['pos-neg'] == 'POS' && $arr['list']['HEX']['pos-neg'] == 'POS') {
		$arr['pos-neg'] = 'POS';
	} else {
		$arr['pos-neg'] = 'NEG';
	}

	$arr['only_detected'] = true;
	return $arr;
}

function trpdd_cal_ncovid19_fam_vic($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $covid_19_fam_vic_cutoff;

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}

	if (empty($covid_19_fam_vic_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
				and (cu_ms='{$instrument}' or cu_ms='famvic') 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";
		$covid_19_fam_vic_cutoff = $wpdb->get_var($sql_cut);
	}

	$covid_testnames = trpdd_get_covid_tests('FAMVIC');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['E/N']['conc'], 'nan') !== false || stripos($arr['list']['E/N']['conc'], 'no ct') !== false) {
		$arr['list']['E/N']['conc'] = 45;
	}
	if (stripos($arr['list']['IC']['conc'], 'nan') !== false || stripos($arr['list']['IC']['conc'], 'no ct') !== false) {
		$arr['list']['IC']['conc'] = 45;
	}


	$arr['list']['E/N']['pos-neg'] = tr_pdd_pass_condition($arr['list']['E/N']['conc'], $covid_19_fam_vic_cutoff) ? 'POS' : 'NEG';
	$arr['list']['IC']['pos-neg'] = tr_pdd_pass_condition($arr['list']['IC']['conc'], $covid_19_fam_vic_cutoff) ? 'POS' : 'NEG';

	if ($arr['list']['E/N']['pos-neg'] == 'POS' && $arr['list']['IC']['pos-neg'] == 'POS') {
		$arr['pos-neg'] = 'POS';
	} else {
		$arr['pos-neg'] = 'NEG';
	}

	$arr['only_detected'] = true;
	return $arr;
}

function trpdd_cal_ncovid19_ic($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $covid_19_cutoff;

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}
	$ckey = $instrument . '-' . $date;
	if (!isset($covid_19_cutoff[$ckey]) || empty($covid_19_cutoff[$ckey])) {
		//Cut Covid-19
		$covid19_cut = $wpdb->get_row(
			"select 
            pc.* 
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='{$instrument}' or cu_ms='all') 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 "
		);

		$cut_up_compare = '<';
		$cut_low_compare = '<';
		$cut_up = 0;
		$cut_low = 0;
		if (!is_null($covid19_cut)) {
			$cut_up_compare = strpos($covid19_cut->cu_upper_limit, '<=') !== false ? '<=' : '<';
			$cut_low_compare = strpos($covid19_cut->cu_cutoff, '<=') !== false ? '<=' : '<';

			$cut_up = str_replace(array('<=', '>=', '<', '>'), '', $covid19_cut->cu_upper_limit);
			$cut_low = str_replace(array('<=', '>=', '<', '>'), '', $covid19_cut->cu_cutoff);
		}


		$cut_up = floatval($cut_up);
		$cut_low = floatval($cut_low);
		if ($cut_up == 0 || $cut_up > 100) {
			$cut_up = 34;
			$cut_up_compare = '<=';
			$cut_low_compare = '<';
		}

		//cut IC
		$cutic = $wpdb->get_row(
			"select 
            pc.* 
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='IC') 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 "
		);

		$cut_ic_up = $cutic ? trim($cutic->cu_upper_limit) : '';
		$cut_ic_low = $cutic ? trim($cutic->cu_cutoff) : '';


		$covid_19_cutoff[$ckey] = array(
			'high' => $cut_up,
			'high_compare' => $cut_up_compare,
			'low' => $cut_low,
			'low_compare' => $cut_low_compare,
			'invalid' => !is_null($covid19_cut) ? $covid19_cut->cu_invalid : '',
			'ic_high' => $cut_ic_up,
			'ic_low' => $cut_ic_low,
		);
	}

	$covid_testnames = trpdd_get_covid_tests('IC');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	if (isset($_GET['debug11'])) {
		echo '<br>';
		var_dump($covid_testnames);
	}

	foreach ($arr['list'] as $dr => $tpl) {
		$dr2 = strtoupper($dr);
		if (!in_array($dr, $covid_testnames) && !in_array($dr2, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	$arr['invalid'] = $covid_19_cutoff[$ckey]['invalid'];
	$arr['high'] = $covid_19_cutoff[$ckey]['high'];
	$arr['high_compare'] = $covid_19_cutoff[$ckey]['high_compare'];
	$arr['low'] = $covid_19_cutoff[$ckey]['low'];
	$arr['low_compare'] = $covid_19_cutoff[$ckey]['low_compare'];

	$arr['ic_high'] = $covid_19_cutoff[$ckey]['ic_high'];
	$arr['ic_low'] = $covid_19_cutoff[$ckey]['ic_low'];

	$arr['ckey'] = $ckey;

	$covid19 = $arr['list'][strtoupper($covid_testnames[0])]['conc'];

	$ic = $arr['list'][strtoupper($covid_testnames[1])]['conc'];

	if (strtolower($covid19) === 'undetermined') {
		$covid19 = $arr['high'] + 5;
	}
	if (strtolower($ic) === 'undetermined') {
		$ic = $arr['high'] + 5;
	}

	$covid19nan = $covid19 === '' || stripos($covid19, 'nan') !== false;
	$covid19_pos = false;

	if (
		!$covid19nan &&
		(($arr['high_compare'] == '<=' && $covid19 <= $arr['high'])
			|| ($arr['high_compare'] == '<' && $covid19 < $arr['high']))
	) {
		$covid19_pos = true;
	}

	if (
		$arr['low'] > 0
		&& (($arr['low_compare'] == '<=' && $covid19 <= $arr['low'])
			|| ($arr['low_compare'] == '<' && $covid19 < $arr['low']))
	) {
		$covid19_pos = false;
	}



	$ic_pos = $ic !== '' && trim($ic) != 'NaN';
	if (!empty($arr['ic_high'])) {
		$ic_pos = tr_pdd_pass_condition($ic, $arr['ic_high']);
		if ($ic_pos) {
			$ic_pos = tr_pdd_pass_condition($ic, $arr['ic_low']);
		}
	} else if ($arr['high'] == 60) {
		$ic_pos = $ic > 0 && $ic <= 60;
	}


	$arr['COVID-19']    = $covid19_pos ? 'POS' : 'NEG';
	$arr['IC']          = $ic_pos ? 'POS' : 'NEG';
	$arr['cutoff']      = $arr['high'];
	$is_invalid         = !empty($arr['invalid']) ? tr_pdd_pass_condition($covid19, $arr['invalid']) : false;

	$arr['list'][strtoupper($covid_testnames[1])]['pos-neg'] = $arr[strtoupper($covid_testnames[1])];
	$arr['list'][strtoupper($covid_testnames[0])]['pos-neg'] = $arr[strtoupper($covid_testnames[0])];

	if (isset($_GET['dd2'])) {
		var_dump($arr);
	}

	if (is_null($covid19) || is_null($ic)) {
		if (!isset($arr['pos-neg'])) {
			$arr['pos-neg'] = $wpdb->get_var("select `pos-neg` from ci_results_pcr where drug = '{$arr['drug']}' and report_id = {$report_id}");
			$arr['oldposneg'] = $arr['pos-neg'];
		}
		if (empty($arr['pos-neg'])) {
			$arr['pos-neg'] = 'PEN';
		}
	} else if ($covid_testnames[0] === 'CQ') {
		$pass_mtemp = $ic > 76.5 && $ic < 77.5;

		if ($covid19_pos && $pass_mtemp) {
			$arr['pos-neg'] = 'POS';
		} else {
			$arr['pos-neg'] = 'NEG';
		}
	} else {
		if ($is_invalid || (!empty($arr['ic_high']) && !$ic_pos)) {
			$arr['pos-neg'] = 'RER';
		} else if ($covid19_pos) {
			$arr['pos-neg'] = 'POS';
		} else if ($ic_pos && !$covid19_pos) {
			$arr['pos-neg'] = 'NEG';
		} else {
			$arr['pos-neg'] = 'RER';
		}
	}
	$arr['only_detected'] = true;
	return $arr;
}

function trpdd_cal_ncovid19_n1n2($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $n1n2_cutoff, $n1n2_compare;

	if (empty($n1n2_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='N1N2' or cu_ms = '{$instrument}') 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";
		$n1n2_cutoff = $wpdb->get_var($sql_cut);
		$n1n2_compare = strpos($n1n2_cutoff, '<=') !== false ? '<=' : '<';
		$n1n2_cutoff = str_replace(array('<=', '>=', '<', '>'), '', $n1n2_cutoff);

		$n1n2_cutoff = floatval($n1n2_cutoff);
		if ($n1n2_cutoff == 0 || $n1n2_cutoff > 100) {
			$n1n2_cutoff = 34;
			$n1n2_compare = '<=';
		}
	}
	if (isset($_GET['n1n2c'])) {

		var_dump($n1n2_compare);
		var_dump($n1n2_cutoff);
	}

	$covid_testnames = trpdd_get_covid_tests('N1N2');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['N1']['conc'], 'nan') !== false) {
		$arr['list']['N1']['conc'] = 45;
	}
	if (stripos($arr['list']['N2']['conc'], 'nan') !== false) {
		$arr['list']['N2']['conc'] = 45;
	}


	if ($n1n2_compare == '<') {
		$n1 = $arr['list']['N1']['conc'] > 0 && $arr['list']['N1']['conc'] < $n1n2_cutoff;
		$n2 = $arr['list']['N2']['conc'] > 0 && $arr['list']['N2']['conc'] < $n1n2_cutoff;
	} else {
		$n1 = $arr['list']['N1']['conc'] > 0 && $arr['list']['N1']['conc'] <= $n1n2_cutoff;
		$n2 = $arr['list']['N2']['conc'] > 0 && $arr['list']['N2']['conc'] <= $n1n2_cutoff;
	}

	$RNAseP = $arr['list']['RNASEP']['conc'] > 0;

	if (!$RNAseP) {
		$arr['pos-neg'] = 'INV';
	} else if ($n1 || $n2) {
		$arr['pos-neg'] = 'POS';
	} else if (!$n1 && !$n2) {
		$arr['pos-neg'] = 'NEG';
	}

	$arr['only_detected'] = true;
	return $arr;
}


function trpdd_cal_ncovid19_n1n2_rp($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $n1n2_rp_cutoff, $n1n2_rp_compare;

	if (empty($n1n2_rp_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='N1N2RP' or cu_ms = '{$instrument}' ) 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";
		$n1n2_rp_cutoff = $wpdb->get_var($sql_cut);

		$n1n2_rp_compare = strpos($n1n2_rp_cutoff, '<=') !== false ? '<=' : '<';
		$n1n2_rp_cutoff = str_replace(array('<=', '>=', '<', '>'), '', $n1n2_rp_cutoff);

		$n1n2_rp_cutoff = floatval($n1n2_rp_cutoff);
		if ($n1n2_rp_cutoff == 0 || $n1n2_rp_cutoff > 100) {
			$n1n2_rp_cutoff = 34;
			$n1n2_rp_compare = '<=';
		}
	}
	if (isset($_GET['n1n2c'])) {

		var_dump($n1n2_rp_compare);
		var_dump($n1n2_rp_cutoff);
		var_dump($sql_cut);
	}

	$covid_testnames = trpdd_get_covid_tests('N1N2RP');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['N1']['conc'], 'nan') !== false || stripos($arr['list']['N1']['conc'], 'Undetermined') !== false) {
		$arr['list']['N1']['conc'] = 45;
	}
	if (stripos($arr['list']['N2']['conc'], 'nan') !== false || stripos($arr['list']['N2']['conc'], 'Undetermined') !== false) {
		$arr['list']['N2']['conc'] = 45;
	}
	if (stripos($arr['list']['RP']['conc'], 'nan') !== false || stripos($arr['list']['RP']['conc'], 'Undetermined') !== false) {
		$arr['list']['N2']['conc'] = 45;
	}

	$arr['cutoff']      = $n1n2_rp_cutoff;

	if ($n1n2_rp_compare == '<') {
		$n1 = $arr['list']['N1']['conc'] > 0 && $arr['list']['N1']['conc'] < $n1n2_rp_cutoff;
		$n2 = $arr['list']['N2']['conc'] > 0 && $arr['list']['N2']['conc'] < $n1n2_rp_cutoff;
	} else {
		$n1 = $arr['list']['N1']['conc'] > 0 && $arr['list']['N1']['conc'] <= $n1n2_rp_cutoff;
		$n2 = $arr['list']['N2']['conc'] > 0 && $arr['list']['N2']['conc'] <= $n1n2_rp_cutoff;
	}

	$RP = $arr['list']['RP']['conc'] > 0;

	$arr['list']['N1']['pos-neg'] = $n1 ? 'POS' : 'NEG';
	$arr['list']['N2']['pos-neg'] = $n2 ? 'POS' : 'NEG';
	$arr['list']['RP']['pos-neg'] = $RP ? 'POS' : 'NEG';

	if ($n1 && $n2) {
		$arr['pos-neg'] = 'POS';
	} else if ($n1 || $n2) {
		$arr['pos-neg'] = 'INV'; // 'INC';
	} else if (!$n1 && !$n2 && $RP) {
		$arr['pos-neg'] = 'NEG';
	} else if (!$RP) {
		$arr['pos-neg'] = 'INV';
	}

	$arr['only_detected'] = true;
	return $arr;
}

function trpdd_cal_ncovid19_n1rp($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $n1rp_cutoff, $n1rp_compare;

	if (empty($n1rp_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='N1RP' ) 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";
		$n1rp_cutoff = $wpdb->get_var($sql_cut, ARRAY_A);
		$n1rp_compare = strpos($n1rp_cutoff, '<=') !== false ? '<=' : '<';
		$n1rp_cutoff = str_replace(array('<=', '>=', '<', '>'), '', $n1rp_cutoff);

		$n1rp_cutoff = floatval($n1rp_cutoff);
		if ($n1rp_cutoff == 0 || $n1rp_cutoff > 100) {
			$n1rp_cutoff = 37;
			$n1rp_compare = '<';
		}
	}
	if (isset($_GET['n1n2c'])) {

		var_dump($n1rp_compare);
		var_dump($n1rp_cutoff);
		var_dump($sql_cut);
	}

	$covid_testnames = trpdd_get_covid_tests('N1RP');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['N1']['conc'], 'undetermined') !== false) {
		$arr['list']['N1']['conc'] = 45;
	}

	if (stripos($arr['list']['N1']['conc'], 'nan') !== false) {
		$arr['list']['N1']['conc'] = 40;
	}

	if (stripos($arr['list']['RP']['conc'], 'undetermined') !== false) {
		$arr['list']['RP']['conc'] = 45;
	}
	if (stripos($arr['list']['RP']['conc'], 'nan') !== false) {
		$arr['list']['RP']['conc'] = 40;
	}


	if ($n1rp_compare == '<') {
		$n1 = $arr['list']['N1']['conc'] < $n1rp_cutoff;
		$RP = $arr['list']['RP']['conc'] < $n1rp_cutoff;
	} else {
		$n1 = $arr['list']['N1']['conc'] <= $n1rp_cutoff;
		$RP = $arr['list']['RP']['conc'] <= $n1rp_cutoff;
	}

	$arr['list']['N1']['pos-neg'] = $n1 ? 'POS' : 'NEG';
	$arr['list']['RP']['pos-neg'] = $RP ? 'POS' : 'NEG';

	$invalid = $arr['list']['N1']['conc'] >= 40 && $arr['list']['RP']['conc'] >= 40;

	if ($invalid) {
		$arr['pos-neg'] = 'RER';
	} else if ($n1) {
		$arr['pos-neg'] = 'POS';
	} else if (!$n1 && $RP) {
		$arr['pos-neg'] = 'NEG';
	} else {
		$arr['pos-neg'] = 'RER';
	}

	$arr['only_detected'] = true;
	return $arr;
}


function trpdd_cal_ncovid19_ms2($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $covid19_ms2_cutoff, $covid19_ms2_compare;

	if (empty($covid19_ms2_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='MS2' or cu_ms='{$instrument}') 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";


		$covid19_ms2_cutoff = $wpdb->get_var($sql_cut);
		if (isset($_GET['debug_sql'])) {
			echo $sql_cut;
			echo '<br>';
			var_dump($covid19_ms2_cutoff);
			echo '<br>';
		}

		$covid19_ms2_compare = strpos($covid19_ms2_cutoff, '<=') !== false ? '<=' : '<';
		$covid19_ms2_cutoff = str_replace(array('<=', '>=', '<', '>'), '', $covid19_ms2_cutoff);

		$covid19_ms2_cutoff = floatval($covid19_ms2_cutoff);
		if ($covid19_ms2_cutoff == 0 || $covid19_ms2_cutoff > 40) {
			$covid19_ms2_cutoff = 33;
			$covid19_ms2_compare = '<';
		}
	}

	$covid_testnames = trpdd_get_covid_tests('MS2');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}

	if (stripos($arr['list']['ORF1AB']['conc'], 'nan') !== false) {
		$arr['list']['ORF1AB']['conc'] = 45;
	}
	if (stripos($arr['list']['N GENE']['conc'], 'nan') !== false) {
		$arr['list']['N GENE']['conc'] = 45;
	}
	if (stripos($arr['list']['MS2']['conc'], 'nan') !== false) {
		$arr['list']['MS2']['conc'] = 45;
	}

	if ($covid19_ms2_compare == '<') {
		$ORF1AB = $arr['list']['ORF1AB']['conc'] < $covid19_ms2_cutoff;
		$NGENE = $arr['list']['N GENE']['conc'] < $covid19_ms2_cutoff;
		$MS2 = $arr['list']['MS2']['conc'] < $covid19_ms2_cutoff;
	} else {
		$ORF1AB = $arr['list']['ORF1AB']['conc'] <= $covid19_ms2_cutoff;
		$NGENE = $arr['list']['N GENE']['conc'] <= $covid19_ms2_cutoff;
		$MS2 = $arr['list']['MS2']['conc'] <= $covid19_ms2_cutoff;
	}


	if (isset($_GET['debugm2'])) {
		var_dump($covid19_ms2_compare);
		echo '<br>';
		echo 'ORF1AB: ';
		var_dump($ORF1AB);
		echo '<br>';
		echo '$NGENE: ';
		var_dump($NGENE);
		echo '<br>';
		echo '$MS2: ';
		var_dump($MS2);
		echo '<br>';
	}

	if (!$ORF1AB && !$NGENE && !$MS2) {
		$arr['pos-neg'] = 'INV';
	} else if (!$ORF1AB && !$NGENE && $MS2) {
		$arr['pos-neg'] = 'NEG';
	} else if ($ORF1AB && $NGENE) {
		$arr['pos-neg'] = 'POS';
	} else if ($ORF1AB || $NGENE) {
		$arr['pos-neg'] = 'RER';
	}

	$arr['only_detected'] = true;
	return $arr;
}

function trpdd_cal_ncovid19_ms2_sgen($arr, $report_id, $instrument = '', $date = '')
{
	global $wpdb, $covid19_ms2_cutoff, $covid19_ms2_compare;

	if (empty($covid19_ms2_cutoff)) {
		$sql_cut =
			"select 
            pc.cu_upper_limit
            from ci_test_panels_cutoffs pc
            join ci_test_panels te On te.te_ID = pc.cu_te_ID
            join ci_test_groups tg ON te.te_group_id = tg.tg_ID
                where tg.tg_is_covid = 1 and tg.tg_type='pcr'
                 and (cu_ms='MS2' ) 
                 and (cu_start is null or cu_start ='' or cu_start <= '{$date}' ) 
                 and (cu_end is null or cu_end = '' or cu_end > '{$date}')
                 limit 1 ";

		$covid19_ms2_cutoff = $wpdb->get_var($sql_cut, ARRAY_A);
		$covid19_ms2_compare = strpos($covid19_ms2_cutoff, '<=') !== false ? '<=' : '<';
		$covid19_ms2_cutoff = str_replace(array('<=', '>=', '<', '>'), '', $covid19_ms2_cutoff);

		$covid19_ms2_cutoff = floatval($covid19_ms2_cutoff);
		if ($covid19_ms2_cutoff == 0 || $covid19_ms2_cutoff > 40) {
			$covid19_ms2_cutoff = 37;
			$covid19_ms2_compare = '<';
		}
	}

	$covid_testnames = trpdd_get_covid_tests('MS2');
	$covid_testnames = array_map('strtoupper', $covid_testnames);
	foreach ($arr['list'] as $dr => $tpl) {
		if (!in_array($dr, $covid_testnames)) {
			unset($arr['list'][$dr]);
		}
	}
	$arr['only_detected'] = true;
	if (!isset($arr['list']['ORF1AB']) && !isset($arr['list']['N GENE']) && !isset($arr['list']['S GENE'])) {
		$arr['list'] = array(0);
		return $arr;
	}


	if (stripos($arr['list']['ORF1AB']['conc'], 'nan') !== false) {
		$arr['list']['ORF1AB']['conc'] = 45;
	}
	if (stripos($arr['list']['N GENE']['conc'], 'nan') !== false) {
		$arr['list']['N GENE']['conc'] = 45;
	}
	if (stripos($arr['list']['S GENE']['conc'], 'nan') !== false) {
		$arr['list']['S GENE']['conc'] = 45;
	}
	if (stripos($arr['list']['MS2']['conc'], 'nan') !== false) {
		$arr['list']['MS2']['conc'] = 45;
	}

	if ($covid19_ms2_compare == '<') {
		$ORF1AB = $arr['list']['ORF1AB']['conc'] < $covid19_ms2_cutoff ? 1 : 0;
		$NGENE = $arr['list']['N GENE']['conc'] < $covid19_ms2_cutoff ? 1 : 0;
		$SGENE = $arr['list']['S GENE']['conc'] < $covid19_ms2_cutoff ? 1 : 0;
		$MS2 = $arr['list']['MS2']['conc'] < $covid19_ms2_cutoff ? 1 : 0;
	} else {
		$ORF1AB = $arr['list']['ORF1AB']['conc'] <= $covid19_ms2_cutoff ? 1 : 0;
		$NGENE = $arr['list']['N GENE']['conc'] <= $covid19_ms2_cutoff ? 1 : 0;
		$SGENE = $arr['list']['S GENE']['conc'] <= $covid19_ms2_cutoff ? 1 : 0;
		$MS2 = $arr['list']['MS2']['conc'] <= $covid19_ms2_cutoff ? 1 : 0;
	}

	$control_is3740 = $arr['list']['ORF1AB']['conc'] >= $covid19_ms2_cutoff && $arr['list']['ORF1AB']['conc'] < 40;
	if (!$control_is3740) {
		$control_is3740 = $arr['list']['N GENE']['conc'] >= $covid19_ms2_cutoff && $arr['list']['N GENE']['conc'] < 40;
	}
	if (!$control_is3740) {
		$control_is3740 = $arr['list']['S GENE']['conc'] >= $covid19_ms2_cutoff && $arr['list']['S GENE']['conc'] < 40;
	}

	$control_out40 = $arr['list']['ORF1AB']['conc'] >= 40
		&& $arr['list']['N GENE']['conc'] >= 40
		&& $arr['list']['S GENE']['conc'] >= 40;


	if (isset($_GET['debugm2'])) {
		echo 'ORF1AB: ';
		var_dump($ORF1AB);
		echo '<br>';
		echo '$NGENE: ';
		var_dump($NGENE);
		echo '<br>';
		echo '$SGENE: ';
		var_dump($SGENE);
		echo '<br>';
		echo '$MS2: ';
		var_dump($MS2);
		echo '<br>';
		echo '$control_is3740: ';
		var_dump($control_is3740);
		echo '<br>';
	}

	if ($ORF1AB + $NGENE + $SGENE >= 2) {
		$arr['pos-neg'] = 'POS';
	} else if (($ORF1AB + $NGENE + $SGENE == 1 || $control_is3740) && $MS2) {
		$arr['pos-neg'] = 'INC';
	} else if ($control_out40 && $MS2) {
		$arr['pos-neg'] = 'NEG';
	} else if (!$ORF1AB && !$NGENE && !$SGENE && !$MS2) {
		$arr['pos-neg'] = 'INV';
	}


	return $arr;
}



function trpdd_get_pcr_covid_test()
{
	global $wpdb;
	static $covid_test;
	if (empty($covid_test)) {
		$covid_test = $wpdb->get_row("select te.* from ci_test_panels te
			            where (te.te_name='nCoV-2019' 
			                    or te.te_name='SARS-CoV-2 PCR Nucleic Acid' 
			                    or te.te_abrev='nCoV-2019')
			             and te_type='pcr' ", ARRAY_A);
	}

	return $covid_test;
}



function trpdd_get_pcr_has_covid_rs($report_id, $instrument = '*')
{
	global $wpdb;
	$covid_tests = trpdd_get_covid_tests($instrument);
	$covid_tests_str = implode("','", $covid_tests);
	$sql = "select count(*) from ci_results_pcr 
	                    where report_id = {$report_id} 
                        and drug in ('{$covid_tests_str}')";
	$count = $wpdb->get_var($sql);
	if (isset($_GET['debug2'])) {
		echo 'trpdd_get_pcr_has_covid_rs:';
		var_dump($sql);
		echo '<br><br>';
		var_dump($count);
		echo '<br><br>';
	}
	return ($count >= count($covid_tests));
}

function trpdd_pcr_parse_rack4($row, $results)
{
	global $wpdb, $exclude_assens;


	$plate = $wpdb->get_row(
		"select 
            pl.pl_number, 
            pl.pl_today, 
            pb.position,
            pb.ID,
            pb.iparent
            from ci_plates_batches pb
            JOIN ci_plates pl ON pl.pl_ID = pb.pl_ID
            where mid = {$row->mid} 
                    and btype ='pcr'
                    and is_racks4 = 1"
	);

	if ($plate) {
		$iparentid = $plate->iparent > 0 ? $plate->iparent : $plate->ID;
		$list_assens = $wpdb->get_col("select assen from ci_batches_pcr ba
                                        join ci_plates_batches pb ON pb.mid = ba.mid
                                        where pb.btype='pcr' 
                                            and (iparent = {$iparentid} or ID = {$iparentid})");


		$list_all_assens = $list_assens;
		$not_exists = array();

		if ($row->has_pos || $row->covid_pos) {
			//check other exist
			foreach ($results as $r1) {
				if ($row->assen != $r1->assen && in_array($r1->assen, $list_assens)) {
					if (!empty($r1->upload_files)) {
						$sk = array_search($r1->assen, $list_assens);
						unset($list_assens[$sk]);
					}
				}
			}
			if (stripos($row->upload_files, ',') > 0) {
				$list_assens = array();
			}
			//check again
			if (count($list_assens) > 0) {
				$ass_str = implode("','", $list_assens);
				$exists = $wpdb->get_results("select patient_id,upload_files 
												from ci_reports_pcr 
												where patient_id in ('{$ass_str}')
												and patient_id !='{$row->assen}'
												and upload_files!=''
												 and upload_files is not null");
				foreach ($exists as $r1) {
					$sk = array_search($r1->patient_id, $list_assens);
					unset($list_assens[$sk]);

					if ($row->upload_files != $r1->upload_files) {
						$list_assens = array();
						break;
					}
				}
			}

			foreach ($list_assens as $as1) {
				if ($as1 == $row->assen) {
					continue;
				}
				$exclude_assens[] = $as1;
			}



			if (count($list_assens) > 1) {
				$row->assen_title = implode(', ', $list_all_assens);
				$plate->well = trpdd_pos_to_well($plate->position);

				$row->req_link = '#';
				$row->covid_pos = 2;
				$row->no_action = true;
				$row->classes .= ' disabled ';

				$row->assen = $plate->well . '-' . $plate->pl_number . '#' . $plate->pl_today;

				$ass_str = implode("','", $list_assens);
				$wpdb->query("update ci_reports_pcr set covid_pos=2 where patient_id in ('{$ass_str}')");
			} else {
				//need add other
				foreach ($list_all_assens as $a1) {
					if ($a1 != $row->assen) {
						//$not_exists[$a1] = $a1;
					}
				}
			}
		} else {
			//find and add 4 rows
			foreach ($list_assens as $a1) {
				if ($a1 != $row->assen) {
					$not_exists[$a1] = $a1;
				}
			}
		}

		if (count($not_exists) > 0) {
			foreach ($results as $r1) {
				if (in_array($r1->assen, $not_exists)) {
					unset($not_exists[$r1->assen]);
				}
			}

			//check again
			if (count($not_exists) > 0) {
				$ass_str = implode("','", $not_exists);
				$exists = $wpdb->get_results("select patient_id,upload_files 
												from ci_reports_pcr 
												where patient_id in ('{$ass_str}')");
				foreach ($exists as $e1) {
					unset($not_exists[$e1->patient_id]);
				}
			}

			if (count($not_exists) > 0) {
				$rs = $wpdb->get_results("select * from ci_results_pcr where report_id = {$row->id}");
				foreach ($not_exists as $a1) {
					$report_data = array(
						'patient_id'    => $a1,
						'batch_id'      => $row->batch_id,
						'created'       => date_i18n('Y-m-d H:i:s'),
						'created_date'  => date_i18n('Y-m-d'),
						'sublab'        => $row->lab_dba_init,
						'lab_id'        => $row->lab_ID
					);
					$wpdb->insert('ci_reports_pcr', $report_data);
					$report_id = $wpdb->insert_id;
					if ($report_id > 0) {
						foreach ($rs as $r) {
							$wpdb->insert('ci_results_pcr', array(
								'report_id' => $report_id,
								'drug'      => $r->drug,
								'conc'      => $r->conc,
								'pos-neg'   => $r->{'pos-neg'},
								'has_order' => $r->has_order,
								'instrument' => $r->instrument,
								'group_id'  => $r->group_id,
							));
						}
						$row->need_reload = true;
					}
				}
			}
		}
	}
	return $row;
}



//end file