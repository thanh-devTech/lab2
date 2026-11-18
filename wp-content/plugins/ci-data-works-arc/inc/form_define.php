<?php

function tr_pdd_get_batch_types()
{
	$all_types = array(
		0  => 'tox',
		1  => 'dna',
		2  => 'blood',
		3  => 'allergy',
		5  => 'rpp_gi',
		7  => 'wom',
		14 => 'ngs',
		16 => 'pcr',
		17 => 'study',
		18 => 'gene',
		19 => 'req',
	);
	$types = array(
		0 => 'tox',
		2 => 'blood',
		16 => 'pcr',
	);

	if (defined('CI_ALL_BATCH_TYPES')) {
		$types = CI_ALL_BATCH_TYPES;
	}

	$saved = get_option('ci_btypes_order', null);
	if (function_exists('get_blog_option') && defined('BLOG_ID_CURRENT_SITE') && BLOG_ID_CURRENT_SITE > 0) {
		$saved  = get_blog_option(BLOG_ID_CURRENT_SITE, 'ci_btypes_order', null);
	}

	if ($saved && !empty($saved['enabled'])) {

		$types2 = array();
		foreach ($saved['order'] as $btype) {
			if (in_array($btype, $saved['enabled'])) {
				$num = array_search($btype, $all_types);
				if ($num !== false) {
					$types2[$num] = $btype;
				}
			}
		}
		if (!empty($types2)) {
			$types = $types2;
		}
	}
	return $types;
}

function tr_pdd_get_typename($type)
{
	$types = array(
		'tox' => 'Tox',
		'dna' => 'PGx',
		'blood' => 'Blood',
		'allergy' => 'Allergy',
		'rpp_gi' => 'RPP & GI',
		'wom' => 'WOH',
		'ngs' => 'NGS',
		'pcr' => 'PCR',
		'study' => 'Study',
		'gene' => 'Genetics',
		'req' => 'Requisition',
	);
	if (!isset($types[$type])) {
		$types[$type] = strtoupper($type);
	}
	return $types[$type];
}

function tr_pdd_get_typecolor($type)
{
	$colors = array(
		'tox'     => '#155dfc',
		'dna'     => '#3498db',
		'blood'   => '#e7000b',
		'allergy' => '#f54a00',
		'rpp_gi'  => '#1abc9c',
		'wom'     => '#d35400',
		'ngs'     => '#8e44ad',
		'pcr'     => '#00a63e',
		'study'   => '#009689',
		'gene'    => '#e60076',
		'req'     => '#7f8c8d',
	);

	if (!isset($colors[$type])) {
		return '#333';
	}

	return $colors[$type];
}

function tr_pdd_get_current_medications($btype = 'tox')
{
	global $wpdb;
	$sql = "select me_name AS te_name 
		from ci_medications 
		WHERE me_show_req=1 and me_btype = '{$btype}' order by me_name";
	$current_medications = $wpdb->get_col($sql);

	if (empty($current_medications)) {
		$sql = "select me_name AS te_name 
		from ci_medications 
		WHERE me_show_req=1 and me_btype = 'tox' order by me_name";
		$current_medications = $wpdb->get_col($sql);
	}

	return $current_medications;
}

function tr_pdd_get_custom_profiles()
{
	$custom_profiles = array(
		'' => 'Please select',
		//'Use Custom Profile' => 'Use Custom Profile',
		'Do Not Use Custom Profile' => 'Do Not Use Custom Profile',
		'Custom High Risk' => 'High Risk',
		'Custom Mid Risk' => 'Moderate Risk',
		'Custom Low Risk' => 'Low Risk',

		/*
				'Highrisk' => 'Template - High Risk',
				'Standard' => 'Template - Moderate Risk',
				'Basic' => 'Template - Low Risk',*/
		'Comprehensive' => 'Comprehensive'

	);
	return $custom_profiles;
}

function tr_pdd_get_medications()
{
	$Medications = array(
		'Amphetamine',
		'Barbiturate',
		'Benzodiazapine',
		'Buprenorphine',
		'Cocaine',
		'Methadone',
		'Methamphetamine',
		'MDMA',
		'Opiates',
		'Oxycodone',
		'PCP',
		'TCA',
		'THC',
		'Fentanyl',
		'6-MAM',
		'Carisoprodol',
		'EDDP',
		'ETG',
		'Ketamine',
		'Mempridine',
		'Cannabinoids',
		'Tramadol',
		'Zolpidem'
	);
	sort($Medications);
	return $Medications;
}

function tr_pdd_get_panel_tests_requesteds()
{
	$panel_tests_requesteds = array(
		'Amphetamines' => 'Amphetamines',
		'Barbiturates' => 'Barbiturates',
		'Benzodiazepines' => 'Benzodiazepines',
		'Methylone,Mephedrone,MDPV' => 'Bath Salts',
		'Buprenorphine' => 'Buprenorphine',
		'Carisprodol' => 'Carisprodol',
		'Cathinones (Bath Salts)' => 'Cathinones (Bath Salts)',
		'Cocaine Metabolite' => 'Cocaine Metabolite',
		'Ecstacy' => 'Ecstacy',
		'Ethanol (EtOH)' => 'Ethanol (EtOH)',
		'Ethanol Biomarkers (EtG/EtS)' => 'Ethanol Biomarkers (EtG/EtS)',
		'Fentanyl' => 'Fentanyl',
		'Gabapentin' => 'Gabapentin',
		'6-MAM' => 'Heroin',
		'Ketamine' => 'Ketamine',
		'MDMA' => 'MDMA',

		'Meperidine' => 'Meperidine',
		'Methadone' => 'Methadone',
		'Methamphetamine d/l' => 'Methamphetamine d/l',
		'Methylphenidate' => 'Methylphenidate',
		'Opiates' => 'Opiates',
		'Opiate Metabolites' => 'Opiate Metabolites',
		'Phencyclidine' => 'Phencyclidine',
		'Pregabalin' => 'Pregabalin',
		'JWH-018N-PentanoicAcid,JWH-018N-PentanoicAcid,JWH-073-4OHbutyl,JWH-073-4OHbutyl,JWH-073-N-Butanoic acid/JWH-018-5OHpentyl,JWH-073-N-Butanoic acid/JWH-018-5OHpentyl,JWH-018-4OHpentyl' => 'Synthetic Cannabinoids',
		'Tapentadol' => 'Tapentadol',
		'THC' => 'THC',
		'Tramadol' => 'Tramadol',
		'Tricyclic Antidepressents' => 'Tricyclic Antidepressents',
		'Triazolam' => 'Triazolam',
		'Zolpidem' => 'Zolpidem',
	);
	return $panel_tests_requesteds;
}

function tr_pdd_get_profiles_tests_requesteds($type = 'tox', $in_req = false, $onlytest = false, $date = null, $include = array())
{
	global $wpdb;
	if ($date == 'auto' || $date < '2000-01-01') {
		$date = date_i18n('Y-m-d');
	}
	if ($onlytest) {
		$where = " where te.te_type='{$type}' ";
	} else {
		$where = " where (te.te_type='{$type}' or (gr.tg_type='{$type}' and gr.tg_show_req=1 and te.te_ID is null))";
	}

	$wherejoin = '';
	if ($in_req) {
		if (is_array($include) && count($include) > 0) {
			$include = implode(',', $include);
			$wherejoin .= " and (te_in_req=1 or te_ID in ({$include}))";
		} else {
			$wherejoin .= " and te_in_req=1 ";
		}
	}

	if ($date) {
		$date = date('Y-m-d', strtotime($date));
		$wherejoin .= " and (te_start <= '{$date}' or te_start is null or te_start = '' or te_start = '0000-00-00') ";
		$wherejoin .= " and (te_end > '{$date}' or te_end is null or te_end = '' or te_end = '0000-00-00' )";

		$where .= " and (tg_date_start <= '{$date}' or tg_date_start is null or tg_date_start = ''  or tg_date_start = '0000-00-00')";
		$where .= " and (tg_date_end >= '{$date}' or tg_date_end is null or tg_date_end = ''  or tg_date_end = '0000-00-00' )";
	}

	$sql = "select te.*,
					gr.tg_ID,
					gr.tg_parent,
					gr.tg_show_req,
					gr.tg_only_group,
					gr.tg_name as main_group,
                    if(gr.tg_show_req=1,gr.tg_name,'Other') as tg_name,
                    gr.tg_position as position,
                    group_concat(cut.cu_type_of_specimen) as spec_types
                from ci_test_groups gr
                left join ci_test_panels te ON gr.tg_ID = te.te_group_id {$wherejoin}
                left join ci_test_panels_cutoffs cut ON cut.cu_te_ID = te.te_ID
                {$where} 
                group by te.te_ID
                order by gr.tg_position asc, gr.tg_ID asc, te.te_position asc";
	$results = $wpdb->get_results($sql);
	$profiles_tests_requesteds = array();

	if (isset($_GET['debug2'])) {
		var_dump($sql);
		echo '<br>';
	}
	$max_pos = 0;
	foreach ($results as $r) {
		$r->class = ' ';
		if ($r->te_basic) {
			$r->class .= 'te_basic ';
		}
		if ($r->te_standard) {
			$r->class .= 'te_standard ';
		}
		if ($r->te_highrisk) {
			$r->class .= 'te_highrisk ';
		}
		if ($r->te_comprehensive) {
			$r->class .= 'te_comprehensive ';
		}
		$r->key = preg_replace('/\(.*\).*/', '', $r->te_name ? $r->te_name : '');
		$r->key = trim($r->key);
		if ($r->position > 1000) {
			$r->position = $max_pos + 1;
		}
		if ($r->position > $max_pos) {
			$max_pos = $r->position;
		}
		$group_id = $r->tg_ID;
		if ($r->tg_parent > 0 && !$r->tg_show_req) {
			$group_id = $r->tg_parent;
		}
		$profiles_tests_requesteds[$group_id][] = $r;
	}
	return $profiles_tests_requesteds;
}

function tr_pdd_get_psych_requesteds()
{
	$psych_requesteds = array(
		'Codeine Fentanyl Propoxyphene',
		'6-MAM',
		'Fluoxetine',
		'Methylphenidate',
		'Alprazolam Hydrocodone Morphine',
		'Amitriptyline Naloxone',
		'Amphetamine THC',
		'Imipramine THC-COOH',
		'JWH-018-5OH-Pentyl',
		'JWH-073-4OH-Butyl',
		'Bupropion JWH-073-N-Butanoic Acid',
		'Ketamine',
		'Lorazepam',
		'MDA',
		'MDEA',
		'MDMA',
		'MDPV',
		'Meperidine',
		'Meprobamate',
		'Methadone PCP',
		'EDDP Methamphetamine Phentermine',
		'Methamphetamine d/l',
		'Analyte Name Analyte Name Analyte Name Analyte Name',
		'Methcathinone',
		'Flunitrazepam Methylone Quetiapine',
		'7-Amino Clonazepam Methylone Ritalinic Acid',
		'7-hydroxy quetiapine Flurazepam Selegiline',
		'Alpha-hydroxymidazolam Gabapentin Mirtazapine Sertraline',
		'Tapentadol',
		'Hydromorphone Temazepam',
		'Hydroxyalprazolam Norbuprenorphine',
		'Aripiprazole Norcodeine',
		'Benzoylecgonine JW-1018N-Pentanoic Acid Nordiazepam Topiramate',
		'Buprenorphine Norfentanyl Tramadol',
		'Buprenorphine glucuronide Norhydrocodone Trazodone',
		'Normedperidine Triazolam',
		'Carisoprodol Noroxycodone Venlafaxine',
		'Citalopram Noroxymorphone Vilazodone',
		'Clonazepam Nortriptyline Zaleplon',
		'Cotinine Metabolite Olanzapine Zolpidem',
		'Cyclobenzaprine Oxazepam Zolpidem Metabolite',
		'Desipramine Oxcarbazepine Zopiclone',
		'Dihydrocodeine Oxycodone',
		'Divalproex Mephedrone Oxymorphone',
		'Doxepin Paroxetine',
		'Duloxetine',
		'Escitaopram Pregabalin'
	);
	return $psych_requesteds;
}


function tr_pdd_get_panel_groups($key = false, $type = 'tox')
{
	global $wpdb;
	$panel_groups = array();

	$where = '';
	if ($key > 0) {
		$where .= " and tg_ID = {$key} ";
	}
	$groups = $wpdb->get_results("select * from ci_test_groups where tg_type='{$type}' {$where} order by tg_name asc");

	foreach ($groups as $group) {
		if (isset($group->{'tg_cli' . '_ID'})) {
			$group->tg_show_cli_IDs = $group->{'tg_cli' . '_ID'};
		}
		$panel_groups[$group->tg_ID] = array(
			'id' => $group->tg_ID,
			'label' => $group->tg_name,
			'label_name' => $group->tg_label_name,
			'byone' => $group->tg_show_one,
			'show' => $group->tg_show_req,
			'bgcolor' => $group->tg_bgcolor,
			'only_group' => $group->tg_only_group,
			'edit_results' => $group->tg_manual_edit,
			'notestcheckbox' => $group->tg_notestcheckbox,
			'parent' => $group->tg_parent,
			'relate_to' => $group->tg_relate_to,
			'is_covid' => $group->tg_is_covid == 1,
			'auto_position' => $group->tg_auto_position,
			'auto_batchid' => $group->tg_auto_batchid,
			'lab_IDs' => $group->tg_lab_ID,
			'hide_lab_IDs' => $group->tg_hide_lab_ID,
			'cli_IDs' => $group->tg_show_cli_IDs,
			'not_cli_IDs' => $group->tg_notshow_cli_IDs,
			'ref_lab_ID' => $group->tg_ref_lab_ID,
			'show_for_ref_lab_ID' => $group->tg_show_for_ref_lab_ID,
			'notshow_for_ref_lab_ID' => $group->tg_notshow_for_ref_lab_ID,
			'limit_days' => $group->tg_limit_days,
			'other_options' => !empty($group->tg_other_options) ? json_decode($group->tg_other_options, true) : array(),
		);

		if ($key > 0) {
			return $panel_groups[$key];
		}
	}
	return $panel_groups;
}

function tr_pdd_get_dagnostic_codes($btype = 'tox', $groupid = 0)
{
	global $wpdb;
	$where = " `type` = '{$btype}' ";
	if ($groupid > 0) {
		$where .= " AND FIND_IN_SET('{$groupid}',panelIDs)";
	}
	$lists = $wpdb->get_results("select * from ci_dagnostic_codes where {$where} order by iorder desc");

	$dagnostic_codes = array();
	foreach ($lists as $list) {
		$dagnostic_codes[$list->key] = $list->desc;
	}

	if ($groupid > 0) {
		return $dagnostic_codes;
	} else if (count($dagnostic_codes) > 0) {
		return array('' => 'Select') + $dagnostic_codes;
	}
	if ($btype == 'allergy') {
		$dagnostic_codes = array(
			'' => 'Please Select',
			'Z91.010' => 'Z91.010 - Allergy to peanuts',
			'L27,2' => 'L27,2 - Dermatitis due to ingested foods',
			'K29.70' => 'K29.70 - Gastritis, unspecified',
			'K59.00' => 'K59.00 - Constipation, unspecified',
			'R14.3' => 'R14.3 - Flatulence',
			'K90.89' => 'K90.89 - Other intestinal malabsorption',
			'L70.9' => 'L70.9 - Acne, unspecified',
			'L50.0' => 'L50.0 - Allergic Urticaria',
			'Z91.018' => 'Z91.018 - Allergy to other foods',
			'L27.9' => 'L27.9 - Dermatitis due to unspecified substance taken internally',
			'Z91.012' => 'Z91.012 - Allergy to eggs',
			'R19.7' => 'R19.7 - Diarrhea, unspecified',
			'J01.80' => 'J01.80 - Other acute sinusitis',
			'J45.32' => 'J45.32 - Moderate persistent asthma w/status asthmaticus',
			'J45.990' => 'J45.990 - Exercise-induced brochospasm',
			'J20.9' => 'J20.9 - Acute bronchitis',
			'J45.991' => 'J45.991 - Cough variant asthma',
			'J31.0' => 'J31.0 - Chronic rhinitis',
			'J30.1' => 'J30.1 - Allergic rhinitis due to pollen',
			'J01.40' => 'J01.40 - Acute pansinusitis, NOW',
			'R53.82' => 'R53.82 - Chronic fatgue, NOS',
			'J30.2' => 'J30.2 - Other seasonal allergic rhinitis',
			'J30.5' => 'J30.5 - Allergica rhinitis due to food',
			'J45.50' => 'J45.50 - Severe persistent asthma, uncomplicated',
		);
	} else if ($btype == 'rpp_gi') {
		$dagnostic_codes = array(
			'' => 'Please Select',
			'RESPIRATORY PANEL' => array(
				'B97.89' => 'B97.89 Oth viral agents as the cause of diseases classd elswhr',
				'G93.3' => 'G93.3 Postviral fatigue syndrome',
				'J01.90' => 'J01.90 Acute sinusitis, unspecified',
				'J02.9' => 'J02.9 Acute pharyngitis, unspecified',
				'J06.9' => 'J06.9 Acute upper respiratory infection, unspecified',
				'J20.9' => 'J20.9 Acute bronchitis, unspecified',

				'J30.1' => 'J30.1 Allergic rhinitis due to pollen',
				'J44.1' => 'J44.1 Chronic obstructive pulmonary disease w (acute) exacerbation',
				'M60.9' => 'M60.9 Myositis, unspecified',
				'M79.1' => 'M79.1 Myalgia',
				'J10.1' => 'J10.1 Flu due to other indent influenza virus w oth resp manifest',
				'J11.00' => 'J11.00 Flu due to unidentified flu virus w unsp type of pneumonia',
				'J11.1' => 'J11.1 Flu due to unidentified influenza virus w oth resp manifest',
				'J12.9' => 'J12.9 Viral pneumonia, unspecified',
				'J18.9' => 'J18.9 Pneumonia, unspecified organism',
				'R05' => 'R05 Cough',
				'R50.9' => 'R50.9 Fever, unspecified',
				'R53.1' => 'R53.1 Weakness',
				'R53.81' => 'R53.81 Other malaise',
				'R53.83' => 'R53.83 Other fatigue',

			),
			'GASTROINTESTINAL PANEL' => array(

				'A00.0' => 'A00.0 Cholera due to Vibrio cholera 01, biovar cholerae',
				'A00.1' => 'A00.1 Cholera due to Vibrio cholera 01, biovar eltor',
				'A05.3' => 'A05.3 Foodborne Vibrio parahaemolyticus intoxication',
				'A05.5' => 'A05.5 Foodborne Vibrio vulnificus intoxication',
				'A04.9' => 'A04.9 Due to bacteria',
				'A04.5' => 'A04.5 Campylobacter enteritis',
				'A07.2' => 'A07.2 Cryptosporidium',
				'A04.0' => 'A04.0 Enteropathogenic Escherichia coli infection',
				'A04.1' => 'A04.1 Enterotoxogenic Escherichia coli infection (ETEC LT/ST)',
				'A04.2' => 'A04.2 Enteroinvasive Escherichia coli infection',
				'A04.3' => 'A04.3 Enterohemorrhagic Escherichia coli infection',
				'A04.7' => 'A04.7 Enterocolitis due to Clostridium dicle',
				'A08.8' => 'A08.8 Other specified intestinal infection',
				'A04.8' => 'A04.8 Other specified bacterial intestinal infections',
				'A08.39' => 'A08.39 Enterovirus, other viral enteritis',
				'A08.4' => 'A08.4 Viral intestinal infection, unspecified',
				'A08.2' => 'A08.2 Adenovirus, adenoviral enteritis',
				'A08.0' => 'A08.0 Rotavirus, Rotaviral enteritis',
				'A02.0' => 'A02.0 Salmonella enteritis',
				'A02.9' => 'A02.9 Salmonella infection, unspecified',
				'A03.0' => 'A03.0 Shigellosis due to shigella dysentriae',
				'A03.9' => 'A03.9 Shigellosis, unspecified',
				'A03.1' => 'A03.1 Shigellosis due to shigella flexneri',
				'A03.2' => 'A03.2 Shigellosis due to shigella boydii',
				'A03.3' => 'A03.3 Shigellosis due to shigella sonnei',
				'A08.11' => 'A08.11 Acute gastroenteropathy due to Norwalk agent',
				'A07.1' => 'A07.1 Giardiasis [labliasis]',
				'A07.9' => 'A07.9 Protozoal intestinal disease, unspecified; Protozoal diarrhea, Protozoal dysentriae',

				'A09' => 'A09 Infectious gastroenteritis and colitis, unspecified',
				'K52.2' => 'K52.2 Food hypersensitivity',
				'K59.1' => 'K59.1 Functional diarrhea',
				'K58.0' => 'K58.0 Irritable bowel syndrome with diarrhea',
				'K52.9' => 'K52.9 Gastroenteritis',
				'B82.9' => 'B82.9 Intestinal parasitism, unspecified',
				'B96.21' => 'B96.21 Shiga toxin-producing Excherichia coli [E. coli] (STEC) 0157 as the cause of diseases classified elsewhere',
				'B96.22' => 'B96.22 Other specified Shiga toxin-producing Escherichia coli [E. coli] (STEC) as the cause of diseases classified elsewhere',
				'B96.23' => 'B96.23 Unspecified Shiga toxin-producing Escherichia coli [E. coli] (STEC) as the cause of diseases classified elsewhere',
				'K50.018' => 'K50.018 Crohn’s disease of small intestine with other complication',
				'K50.019' => 'K50.019 Crohn’s disease of small intestine with unspecified complications',
				'K50.118' => 'K50.118 Crohn’s disease of large intestine with other complication',
				'K50.119' => 'K50.119 Crohn’s disease of large intestine with unspecified complications',
				'K50.818' => 'K50.818 Crohn’s disease of both small and large intestine with other complication',
				'K50.819' => 'K50.819 Crohn’s disease of both small and large intestine with unspecified complications',
				'K50.918' => 'K50.918 Crohn’s disease, unspecified, with other complication',
				'K50.919' => 'K50.919 Crohn’s disease, unspecified, with unspecified complications',
				'K51.819' => 'K51.819 Other ulcerative colitis with unspecified complications',
				'K51.818' => 'K51.818 Other ulcerative colitis with other complication',
				'K51.918' => 'K51.918 Ulcerative colitis, unspecified with other complication',
				'K51.919' => 'K51.919 Ulcerative colitis, unspecified with unspecified complications',
				'R19.7' => 'R19.7 Diarrhea, unspecified',
				'R19.4' => 'R19.4 Change in bowel habit',
			)
		);
	} else if ($btype == 'pcr') {
		$dagnostic_codes = array(
			'' => 'Please Select',
			'Z20.828' => 'Z20.828 - Contact with and (suspected) exposure to viral communicable diseases.',
			'Z20.822' => 'Z20.822 contact with and (suspected) exposure to COVID-19',
			'Z11.52' => 'Z11.52 - encounter for screening for COVID-19',
			'Z86.16' => 'Z86.16 - personal history of COVID-19',
			'R09.81' => 'R09.81 Congestion',
			'R05' => 'R05 Cough',
			'Z11.59' => 'Z11.59 Encounter for screening for other viral diseases.',
			'J02.9' => 'J02.9 Pharyngitis',
			'R50.9' => 'R50.9 Fever',
			'R35.0' => 'R35.0 Frequency of Micturition',
			'Z22.39' => 'Z22.39 Carrier of other specified bacterial disease',
			'N39.0' => 'N39.0 Urinary Tract Infection, site not specified',
			'R30.0' => 'R30.0 Dysuria',
			'L08.9' => 'L08.9 Local infection of the skin and subcutaneous tissue, unspecified',
			'Z22.322' => 'Z22.322 Carrier or suspected carrier of MRSA',
			'N76.0' => 'N76.0 Acute vaginitis',
			'Z16.19' => 'Z16.19 Resistance to other specified Beta Lactam antibiotics',
			'Z11.3' => 'Z11.3 Encounter for screening for infections with a predominantly sexual mode of transmission',
			'Z01.419' => 'Z01.419 Encounter for gynecological exam (routine)',
			'N89.8' => 'N89.8 Other specified noninflammatory disorders of vagina',
		);
	} else if ($btype == 'blood') {

		$dagnostic_codes = array(
			'' => 'Please Select',
			'R10.9' => 'R10.9 ABDOMINAL PAIN',
			'R79.9' => 'R79.9 ABNORMAL BLOOD CHEM',
			'R79.01' => 'R79.01 ANTICOAGULANT',
			'148.91' => '148.91 ATRIALFIBRILLATION',
			'J40' => 'J40 BRONCHITIS',
			'C80.1' => 'C80.1 CANCER',
			'149.9' => '149.9 CARDIAC DYSHYTHMIAS',
			'R07.9' => 'R07.9 CHEST PAIN',
			'N18.9' => 'N18.9 CKD (mid)',
			'D68.9' => 'D68.9 COAG. DEFECT',
			'C18.9' => 'C18.9 COLON CA',
			'F32.9' => 'F32.9 DEPRESSION',
			'E10.9' => 'E10.9 DIABETES (NIDDM: E11.9)',
			'E87.8' => 'E87.8 ELECTROLYTE IMBALANCE',
			'R42'   => 'R42 DIZZINESS',
			'R53.83' => 'R53.83 FATIGUE',
			'R50.81' => 'R50.81 FEVER',
			'K52.9' => 'K52.9 GASTROENTERITIS',
			'M10.40' => 'M10.40 GOUT',
			'150.9' => '150.9 HEART FAILURE CONGUES HEPATIC DISORDER',
			'K75.9' => 'K75.9 HEPATITIS'
		);
	} else {
		$dagnostic_codes = array(
			'' => 'Please Select',
			'Z51.81'  => 'Z51.81 - Encounter for drug level monitoring',
			'Z79.891' => 'Z79.891 - Long term (current) use of opiate analgesic',
			'Z79.899' => 'Z79.899 - Other long term (current) drug therapy',
			'F11.20' => 'F11.20 -  OPIOID DEPENDENCE, Uncomplicated',
			'F19.20' => 'F19.20 - Other psychoactive substance dependence, uncomplicated',
			'F18.10' => 'F18.10 - Inhalant abuse, uncomplicated',
			'F18120' => 'F18120 - Inhalant abuse with intoxication, uncomplicated',
			'F1890' => 'F1890 - Inhalant use, unspecified, uncomplicated',
			'F550' => 'F550 - Abuse of antacids',
			'F551' => 'F551 - Abuse of herbal or folk remedies',
			'F552' => 'F552 - Abuse of laxatives',
			'F553' => 'F553 - Abuse of steroids or hormones',
			'F554' => 'F554 - Abuse of vitamins',
			'F558' => 'F558 - Abuse of other non-psychoactive substances',
			'M545' => 'M545 -Low Back Pain',
			'M546' => 'M546 - Pain in Thoracic Spine',
			'G894' => 'G894 - Chronic Pain Syndrome',
			'M25.50' => 'M25.50',
			'M96.1' => 'M96.1',
			'M51.36' => 'M51.36',
			'M05.40' => 'M05.40',
			'M54.18' => 'M54.18',
			'M54.17' => 'M54.17',
			'M50.30' => 'M50.30',
			'M51.26' => 'M51.26',
			'M51.16' => 'M51.16',
			'M47.816' => 'M47.816',
			'G89.29' => 'G89.29',
			'M54.9' => 'M54.9',
			'G89.0' => 'G89.0',
			'G89.4' => 'G89.4',
			'M54.2' => 'M54.2',
			'F.10.20' => 'F.10.20',
			'F.90.9' => 'F.90.9',
			'F.13.239'  => 'F.13.239',
			'F.19.20' => 'F.19.20',
			'F33.3' => 'F33.3'
		);
	}

	return $dagnostic_codes;
}

function tr_pdd_get_type_of_specimens($btype = 'tox')
{
	global $wpdb;
	$type_of_specimens = array();
	if ($btype == 'wom') {
		$type_of_specimens = array(
			'eSwab' => 'eSwab'
		);
	} else if ($btype == 'tox') {
		$type_of_specimens = array(
			'Urine' => 'Urine',
			'Oral' => 'Oral',
			//'JVHL' => 'JVHL',
			'Other' => 'Other'
		);
	} else {
		$rs = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$btype}' order by ts_group_id, ts_ID asc");
		foreach ($rs as $r) {
			$type_of_specimens[$r->ts_name] = $r->ts_name;
		}
	}

	return $type_of_specimens;
}



function tr_pdd_core_wp_caps()
{
	$caps = array();
	$caps['read'] = array('core', 'general', 'profile read/edit');
	$caps['edit_files'] = array('core', 'deprecated');
	$caps['manage_options'] = array('core', 'general');
	$caps['moderate_comments'] = array('core', 'posts', 'general');
	$caps['manage_categories'] = array('core', 'posts', 'general');
	//$caps['manage_links'] = array('core', 'general');
	$caps['upload_files'] = array('core', 'general');
	$caps['import'] = array('core', 'general');
	$caps['export'] = array('core', 'general');
	$caps['unfiltered_html'] = array('core', 'general');
	$caps['unfiltered_upload'] = array('core', 'general');
	$caps['edit_dashboard'] = array('core', 'general');

	$caps['edit_posts'] = array('core', 'posts');
	$caps['edit_post'] = array('core', 'posts');
	$caps['create_posts'] = array('core', 'posts');
	$caps['edit_others_posts'] = array('core', 'posts');
	$caps['edit_published_posts'] = array('core', 'posts');
	$caps['publish_posts'] = array('core', 'posts');
	$caps['edit_pages'] = array('core', 'pages');
	$caps['edit_others_pages'] = array('core', 'pages');
	$caps['edit_published_pages'] = array('core', 'pages');
	$caps['publish_pages'] = array('core', 'pages');
	$caps['delete_pages'] = array('core', 'pages');
	$caps['delete_others_pages'] = array('core', 'pages');
	$caps['delete_published_pages'] = array('core', 'pages');
	$caps['delete_posts'] = array('core', 'posts');
	$caps['delete_others_posts'] = array('core', 'posts');
	$caps['delete_published_posts'] = array('core', 'posts');
	$caps['delete_private_posts'] = array('core', 'posts');
	$caps['edit_private_posts'] = array('core', 'posts');
	$caps['read_private_posts'] = array('core', 'posts');
	$caps['delete_private_pages'] = array('core', 'pages');
	$caps['edit_private_pages'] = array('core', 'pages');
	$caps['read_private_pages'] = array('core', 'pages');
	$caps['update_plugins'] = array('core', 'plugins');
	$caps['delete_plugins'] = array('core', 'plugins');
	$caps['install_plugins'] = array('core', 'plugins');
	$caps['activate_plugins'] = array('core', 'plugins');
	$caps['edit_plugins'] = array('core', 'plugins');


	$caps['switch_themes'] = array('core', 'themes');
	$caps['edit_themes'] = array('core', 'themes');
	$caps['update_themes'] = array('core', 'themes');
	$caps['install_themes'] = array('core', 'themes');
	$caps['update_core'] = array('core', 'general');
	$caps['edit_theme_options'] = array('core', 'themes');
	$caps['delete_themes'] = array('core', 'themes');



	$caps['wpseo_manage_options'] = array('core', 'other', 'Seo');
	$caps['gform_full_access'] = array('core', 'other', 'Gravity Forms');
	$caps['trcs_cache'] = array('core', 'other', 'Cache & Security');
	$caps['okta'] = array('core', 'other', 'Okata');
	$caps['do_not_allow'] = array('core', 'do_not_allow');



	$caps['edit_users'] = array('core', 'users');
	$caps['edit_user'] = array('core', 'users');
	$caps['list_users'] = array('core', 'users');
	$caps['remove_users'] = array('core', 'users');
	$caps['promote_users'] = array('core', 'users');
	$caps['delete_users'] = array('core', 'users');
	$caps['create_users'] = array('core', 'users');

	if (defined('WP_NETWORK_ADMIN')) {
		//$caps['create_sites'] = array('core', 'multisite', 'Network');
		//$caps['delete_sites'] = array('core', 'multisite', 'Network');
		//$caps['manage_network'] = array('core', 'multisite', 'Network');
		//$caps['manage_sites'] = array('core', 'multisite', 'Network');
		$caps['manage_network_users'] = array('core', 'multisite', 'Network / Create/Edit Users');
		$caps['manage_network_plugins'] = array('core', 'multisite', 'Network');
		$caps['manage_network_themes'] = array('core', 'multisite', 'Network');
		$caps['manage_network_options'] = array('core', 'multisite', 'Network');
		//$caps['upgrade_network'] = array('core', 'multisite', 'Network');
	}
	return $caps;
}

function tr_pdd_lis_caps()
{
	$btypes = tr_pdd_get_batch_types();
	$settings_blood               = get_option('ci_req_settings_blood', array());

	$caps = array();
	$caps['ci_dashboard'] = array('Dashboard', 'main' => true);
	$caps['ci_dashboard_topbox'] = array('Top boxes');
	$caps['ci_dashboard_current_queue_panel'] = array('Current Queue by Panel');
	$caps['ci_dashboard_daily_volume'] = array('Daily Volume');
	$caps['ci_dashboard_tat'] = array('TAT');
	$caps['ci_dashboard_test_type_distribution'] = array('Test Type & Positivity');
	$caps['ci_dashboard_system'] = array('System');

	$caps['ci_turnaround_time'] = array('Average Turnaround Time', 'main' => true);
	$caps['ci_abx_report'] = array('igniteABX', 'main' => true);
	$caps['ci_abx_resistance_data'] = array('Resistance Data');

	$caps['clinic_statistics'] = array('Clinic Statistics', 'main' => true);
	$caps['clinic_support'] = array('Clinic Support', 'main' => true);
	if (in_array('tox', $btypes)) {
		$caps['tox_report'] = array('Tox Report Stats');
	}

	$caps['covid_reporting'] = array('State Reporting');

	if (in_array('blood', $btypes) && tr_get_option('ci_result_settings_blood', 'result_import_mf')) {
		$caps['blood_mf_fa'] = array('MF/FA Reporting');
	}

	$caps['ci_data_reports'] = array('Reports', 'main' => true);
	$caps['ci_collectors_report'] = array('Collectors Report');
	$caps['ci_audit_report'] = array('Audit Report');
	$caps['ci_clinic_report'] = array('Test Volumes');
	$caps['ci_lost_providers'] = array('Lost Providers');
	$caps['ci_report_summary'] = array('PCR Summary Report');
	$caps['ci_lab_flow'] = array('Lab Flow');
	if (in_array('blood', $btypes) && tr_get_option('ci_pdf_data', 'show_result_report')) {
		$caps['ci_report_result'] = array('Blood Result Report');
	}
	if (tr_get_option('ci_pdf_data', 'report_e_plating')) {
		$caps['ci_datareport_plating'] = array('Plating Report');
	}


	$caps['ci_clinic_panel_stats'] = array('Clinic Panel Stats');
	$caps['ci_report_collectors'] = array('Collectors');
	$caps['ci_sapphire'] = array('Rack Report');
	$caps['ci_report_tox_pos_neg'] = array('pos/neg Report');
	$caps['ci_sample_manifest'] = array('Sample Manifest');
	$caps['ci_not_received'] = array('Not Received');
	$caps['ci_processed_samples'] = array('Processed Samples');
	if (tr_get_option('ci_pdf_data', 'report_e_ins_veri')) {
		$caps['ci_insurance_verification'] = array('Insurance Verification');
	}

	$caps['ci_reports_sample_delay_trends'] = array('Sample Delay Trends');
	$caps['ci_reports_prevalence_rates'] = array('Prevalence Rates');
	$caps['ci_reports_pending_and_outstanding'] = array('Pending & Outstanding');


	$caps['ci_data_forms'] = array('Forms', 'main' => true);
	$caps['custom_provider_tox'] = array('Custom Provider Tox');

	$caps['ci_current_clients'] = array('Current Clients');

	if (in_array('tox', $btypes)) {
		$caps['form_requisition'] = array('Toxicology Req', 'main' => true);
		$caps['ci_results_ref_tox_upload'] = array('Ref Tox Upload');
		$caps['ci_results_tox_ref'] = array('Tox Ref Results');
		$caps['ci_results'] = array('Tox Results');
	}

	if (in_array('dna', $btypes)) {
		$caps['form_lab_test_req'] = array('PGx Req', 'main' => true);
		$caps['ci_results_dna'] = array('PGx Results');
	}

	if (in_array('blood', $btypes)) {
		$caps['form_blood'] = array('Blood Req Form', 'main' => true);
		$caps['form_blood_edit'] = array('Edit Blood');
		$caps['ci_results_blood'] = array('Blood Results');
		$caps['ci_phlebotomist'] = array('Phlebotomist');
		if (isset($settings_blood['add_standing_order']) && $settings_blood['add_standing_order']) {
			$caps['ci_standing_orders'] = array('Blood Standing Orders');
		}
		if (isset($settings_blood['quick_intake']) && $settings_blood['quick_intake']) {
			$caps['ci_quick_intake'] = array('Quick Intake');
		}
	}

	if (in_array('pcr', $btypes)) {
		$caps['form_pcr'] = array('PCR Requisition Form', 'main' => true);
		$caps['ci_resutls_pcr'] = array('PCR Results');
		if (isset($settings_blood['add_standing_order']) && $settings_blood['add_standing_order']) {
			$caps['ci_standing_orders_pcr'] = array('PCR Standing Orders');
		}
	}

	if (in_array('gene', $btypes)) {
		$caps['form_gene'] = array('Genetics Requisition Form', 'main' => true);
		$caps['ci_results_gene'] = array('Genetics Results');
	}

	if (in_array('req', $btypes)) {
		$caps['form_req'] = array('Requisition Form', 'main' => true);
		$caps['ci_results_req'] = array('Results');
	}




	if (in_array('study', $btypes)) {
		$caps['form_study'] = array('Study Requisition Form', 'main' => true);
		$caps['ci_study_setup'] = array('Study Setup Form');
		$caps['ci_study_receiving'] = array('Study Receiving');
		$caps['ci_results_study'] = array('Study Results');
		$caps['ci_results_study_tmp'] = array('Study Results Template');
		$caps['ci_results_study_send'] = array('Study Send');
		$caps['ci_results_study_csv'] = array('Study Export');
	}

	//$caps['ci_resutls_pcr_verification'] = array('PCR Verification');
	if (defined('TRCDBI_FILE')) {
		$caps['ci_ins_mix'] = array('Insurance Mix', 'main' => true);
	}
	if (defined('WP_ESTORE_URL') || tr_get_option('ci_pdf_data', 'enable_new_supply_store')) {
		$caps['ci_data_store'] = array('Supply Store');
	}
	if (defined('WP_ESTORE_URL') || tr_get_option('ci_pdf_data', 'enable_new_supply_store')) {
		$caps['ci_estore_admin'] = array('Supply Store Admin');
	}
	$caps['ci_add_notices'] = array('Add Notices');
	//$caps['ci_ap_clms'] = array('A/P to igniteLIS');
	$caps['ci_manage_labs'] = array('Labs');
	$caps['ci_manage_reps'] = array('Marketing Representative');
	$caps['ci_manage_distributors'] = array('Distributors');
	if (tr_get_option('ci_pdf_data', 'enable_clinic_group')) {
		$caps['ci_manage_clinician_groups'] = array('Clinician Groups');
	}
	$caps['ci_patient_chart'] = array('Patient Chart');
	$caps['ci_patient_trends'] = array('Patient Trends');
	//$caps['ci_short_list'] = array('Short Reqs');
	$caps['ci_batches_view'] = array('Inbound');
	$caps['ci_receiving'] = array('Receiving');

	if (tr_get_option('ci_pdf_data', 'plate_coating')) {

		$caps['ci_plate_coating'] = array('Plate Coating');
	}
	//$caps['form_custom_profiles'] = array('Custom Profiles');
	$caps['ci_tecan_processing'] = array('In Process');

	$caps['ci_test_results'] = array('Test Results');
	if (tr_get_option('ci_pdf_data', 'ehealth_wellness')) {
		$caps['ci_health_wellness'] = array('Health Wellness');
	}

	$caps['ci_customers'] = array('Fulfillment');
	//$caps['ci_log_pverify'] = array('Log Pverify');
	$caps['ci_consumable_inv'] = array('Consumable Inv');
	//$caps['ci_testing_profiles'] = array('Testing Profiles');
	$caps['billing_admin'] = array('Specimen Billing');
	$caps['ci_custody_chain'] = array('Custody Chain');
	// $caps['ci_apis'] = array('APIs');
	$caps['ci_payer_list'] = array('Payer List');
	$caps['ci_pcr_prefillcovid'] = array('Req Prefill');
	$caps['ci_security_ban'] = array('Security Ban');
	$caps['ci_user_logs'] = array('User Logs');
	$caps['ci_edit_statuses'] = array('Edit Statuses');

	return $caps;
}


function trpdd_get_receiving_base_positions()
{
	global $wpdb;
	$btypes = implode("','", tr_pdd_get_batch_types());
	$base_positions = array();
	$rs = $wpdb->get_results("select * from ci_receiving_positions where btype in ('{$btypes}') order by ID asc");

	foreach ($rs as $r) {
		$key = !empty($r->subtype) ? $r->subtype : $r->btype;
		$key = strtolower($key);
		$position = $r->position > 0 ? $r->position - 1 : 0;
		$base_positions[$key] = array(
			'position' => $position,
			'name' => $r->bname,
			'btype' => $r->btype,
			'specimen' => $r->specimen,
			'subtype' => $r->subtype
		);
	}


	return $base_positions;
}


function trpdd_get_covid_tests($instrument, $return_all = false)
{
	$covidkey = 'COVID-19';
	if (tr_get_option('ci_pdf_data', 'pcrcovid_covidnot19')) {
		$covidkey = 'COVID';
	}
	$df = array($covidkey, 'IC');
	if (tr_get_option('ci_pdf_data', 'pcrcovid_cqmtemp')) {
		$df = array('CQ', 'MTemp');
	} else if (tr_get_option('ci_pdf_data', 'pcrcovid_ifamchexic')) {
		$df = array('FAM', 'HEX');
	}

	$instrument = strtoupper($instrument);
	$covid_tests_all = array();
	$covid_tests_all['IC'] = $df;

	if (tr_get_option('ci_pdf_data', 'pcrcovid_famvic')) {
		unset($covid_tests_all['IC']);
		$covid_tests_all['FAMVIC'] = array('E/N', 'IC');
	}

	if (tr_get_option('ci_pdf_data', 'pcrcovid_n12rp')) {
		$covid_tests_all['N1N2'] = array('N1', 'N2', 'RNAseP');
	}

	if (tr_get_option('ci_pdf_data', 'pcrcovid_n1_rp')) {
		$covid_tests_all['N1RP'] = array('N1', 'RP');
	}
	if (tr_get_option('ci_pdf_data', 'pcrcovid_n12_r_p')) {
		$covid_tests_all['N1N2RP'] = array('N1', 'N2', 'RP');
	}

	if (tr_get_option('ci_pdf_data', 'pcrcovid_ms2rpsgen')) {
		$covid_tests_all['MS2'] = array('ORF1ab', 'N Gene', 'S Gene', 'MS2');
	} else if (tr_get_option('ci_pdf_data', 'pcrcovid_ms2rp')) {
		$covid_tests_all['MS2'] = array('ORF1ab', 'N Gene', 'MS2');
	}


	if ($return_all) {
		return $covid_tests_all;
	}

	if ($instrument == '*') {
		$arr = array();
		foreach ($covid_tests_all as $t => $tests) {
			$arr = array_merge($arr, $tests);
		}
		return $arr;
	}

	if (isset($covid_tests_all[$instrument])) {
		return $covid_tests_all[$instrument];
	}
	return $covid_tests_all['IC'];
}

//end file
