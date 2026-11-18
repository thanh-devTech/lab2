<?php
$define_instruments = array(
	//AU680 blood & tox
	'api_au680' => array(
		'id' => -20,
		'name' => 'AU680',
		'desc' => '(astm) Tox & Blood',
		'file' => 'au680.php',
		'dir' => 'au680',
		'add_fields' => array('te_au680'),
		'btype' => array('tox', 'blood'),
		'opt' => 'tr_ins_au680_sts'
	),
	//AU680 only Tox
	'api_au680_blood' => array(
		'id' => -20,
		'name' => 'AU680 Blood',
		'desc' => '(astm) Blood',
		'file' => 'au680_blood.php',
		'dir' => 'au680_blood',
		'add_fields' => array('te_au680'),
		'btype' => 'blood',
		'opt' => 'tr_ins_au680_blood_sts'
	),
	'api_instrument_architect' => array(
		'id' => -5,
		'name' => 'Architect',
		'desc' => 'hl7',
		'file' => 'architect.php',
		'dir' => 'architect',
		'add_fields' => array('u_abreviation_architect'),
		'btype' => 'urine',
		'opt' => 'tr_ins_architect_sts'
	),
	//Phoresis
	'api_phoresis' => array(
		'id' => -16,
		'name' => 'Phoresis',
		'desc' => 'hl7',
		'file' => 'phoresis.php',
		'dir' => 'phoresis',
		'add_fields' => array('te_phoresis'),
		'btype' => 'blood',
		'opt' => 'tr_ins_phoresis_sts'
	),
	//Sysmex530
	'api_sysmexn530' => array(
		'id' => -26,
		'name' => 'Sysmex XN 530',
		'desc' => 'hl7',
		'file' => 'sysmexn530.php',
		'dir' => 'sysmexn530',
		'add_fields' => array('te_sysmexn530'),
		'btype' => array('blood', 'study'),
		'opt' => 'tr_ins_sysmexn530_sts'
	),

	'ins_sysmexn430' => array(
		'id' => -5,
		'name' => 'Sysmex XN 430',
		'desc' => 'hl7',
		'file' => 'sysmexn430.php',
		'dir' => 'sysmexn430',
		'add_fields' => array('te_sysmexn430'),
		'btype' => 'blood',
		'opt' => 'tr_ins_sysmexn430_sts'
	),
	'api_access2' => array(
		'id' => -25,
		'name' => 'AccessII',
		'desc' => 'hl7',
		'file' => 'access2.php',
		'dir' => 'access2',
		'add_fields' => array('te_access2_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_access2_sts'
	),
	//dxc700
	'api_dxc700' => array(
		'id' => -28,
		'name' => 'DxC 700',
		'desc' => 'hl7',
		'file' => 'dxc700.php',
		'dir' => 'dxc700',
		'add_fields' => array('te_dxc700', 'u_abreviation_dxc700'),
		'btype' => array('blood', 'urine'),
		'opt' => 'tr_ins_dxc700_sts'
	),
	'api_indiko' => array(
		'id' => -27,
		'name' => 'Indiko+',
		'desc' => 'hl7',
		'file' => 'indiko.php',
		'dir' => 'indiko',
		'opt' => 'tr_ins_indiko_sts'
	),
	'api_celldynruby' => array(
		'id' => -29,
		'name' => 'Cell-Dyn Ruby',
		'desc' => 'hl7',
		'file' => 'celldynruby.php',
		'dir' => 'celldynruby',
		'add_fields' => array('te_celldynruby_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_celldynruby_sts'
	),

	'ins_solana' => array(
		'id' => -33,
		'name' => 'Solana',
		'desc' => 'hl7',
		'file' => 'solana.php',
		'dir' => 'solana',
		'add_fields' => array('te_solana_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_solana_sts'
	),
	'ins_afinion' => array(
		'id' => -35,
		'name' => 'Afinion',
		'desc' => 'hl7',
		'file' => 'afinion.php',
		'dir' => 'afinion',
		'add_fields' => array('te_afinion'),
		'btype' => 'blood',
		'opt' => 'tr_ins_afinion_sts'
	),

	'ins_biomic' => array(
		'id' => -33,
		'name' => 'BIOMIC',
		'desc' => 'hl7',
		'file' => 'biomic.php',
		'dir' => 'biomic',
		'add_fields' => array('te_biomic_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_biomic_sts'
	),
	'ins_diatron_pictus500' => array(
		'id' => -37,
		'name' => 'Diatron Pictus 500',
		'desc' => 'hl7',
		'file' => 'diatron_pictus500.php',
		'dir' => 'diatron_pictus500',
		'add_fields' => array('u_abreviation_pic500'),
		'btype' => 'urine',
		'opt' => 'tr_ins_diatron_pictus500_sts'
	),
	'ins_diatron_pictus700' => array(
		'id' => -38,
		'name' => 'Diatron Pictus 700',
		'desc' => 'hl7',
		'file' => 'diatron_pictus700.php',
		'dir' => 'diatron_pictus700',
		'add_fields' => array('u_abreviation_pic700'),
		'btype' => 'urine',
		'opt' => 'tr_ins_diatron_pictus700_sts'
	),
	'ins_liaison' => array(
		'id' => -33,
		'name' => 'DiaSorin LIAISON XL',
		'desc' => 'hl7',
		'file' => 'liaison.php',
		'dir' => 'liaison',
		'add_fields' => array('te_liaison_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_liaison_sts'
	),
	'ins_thunderbolt' => array(
		'id' => -33,
		'name' => 'THUNDERBOLT',
		'desc' => 'hl7',
		'file' => 'thunderbolt.php',
		'dir' => 'thunderbolt',
		'add_fields' => array('te_thunderbolt_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_thunderbolt_sts'
	),
	'api_dxh560' => array(
		'id' => -28,
		'name' => 'DXH 520(560)',
		'desc' => 'hl7',
		'file' => 'dxh560.php',
		'dir' => 'dxh560',
		'add_fields' => array('te_dxh560_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_dxh560_sts'
	),
	'ins_au400' => array(
		'id' => -43,
		'name' => 'AU400',
		'desc' => 'astm',
		'file' => 'au400.php',
		'dir' => 'au400',
		'add_fields' => array('te_au400', 'u_abreviation_au400'),
		'btype' => array('blood', 'urine'),
		'opt' => 'tr_ins_au400_sts'
	),
	'ins_vitros' => array(
		'id' => -44,
		'name' => 'Vitros',
		'desc' => 'hl7',
		'file' => 'vitros.php',
		'dir' => 'vitros',
		'add_fields' => array('te_vitros_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_vitros_sts'
	),
	'ins_clinitek' => array(
		'id' => -45,
		'name' => 'Clinitek',
		'desc' => 'hl7',
		'file' => 'clinitek.php',
		'dir' => 'clinitek',
		'add_fields' => array('te_clinitek'),
		'btype' => 'blood',
		'opt' => 'tr_ins_clinitek_sts'
	),
	'ins_dca_advantage' => array(
		'id' => -45,
		'name' => 'DCA Advantage',
		'desc' => 'hl7',
		'file' => 'dca_advantage.php',
		'dir' => 'dca_advantage',
		'add_fields' => array('te_dca_advantage'),
		'btype' => 'blood',
		'opt' => 'tr_ins_dca_advantage_sts'
	),
	'ins_dxc500au' => array(
		'id' => -51,
		'name' => 'DxC500AU',
		'desc' => 'hl7',
		'file' => 'dxc500au.php',
		'dir' => 'dxc500au',
		'add_fields' => array('te_dxc500au'),
		'btype' => 'blood',
		'opt' => 'tr_ins_dxc500au_sts'
	),

	'ins_roche_e411' => array(
		'id' => -52,
		'name' => 'Roche E411',
		'desc' => 'hl7',
		'file' => 'roche_e411.php',
		'dir' => 'roche_e411',
		'add_fields' => array('te_roche_e411'),
		'btype' => 'blood',
		'opt' => 'tr_ins_roche_e411_sts'
	),
	'ins_roche_c311' => array(
		'id' => -53,
		'name' => 'Roche c311',
		'desc' => 'hl7',
		'file' => 'roche_c311.php',
		'dir' => 'roche_c311',
		'add_fields' => array('te_roche_c311'),
		'btype' => 'blood',
		'opt' => 'tr_ins_roche_c311_sts'
	),
	'ins_horiba' => array(
		'id' => -54,
		'name' => 'Horiba',
		'desc' => 'hl7',
		'file' => 'horiba.php',
		'dir' => 'horiba',
		'add_fields' => array('te_horiba'),
		'btype' => 'blood',
		'opt' => 'tr_ins_horiba_sts'
	),
	'ins_pentra_xl80' => array(
		'id' => -94,
		'name' => 'Pentra XL80',
		'desc' => 'astm',
		'file' => 'pentra_xl80.php',
		'dir' => 'pentra_xl80',
		'add_fields' => array('te_pentra_xl80_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_pentra_xl80_sts'
	),
	'ins_vitros_4600' => array(
		'id' => -92,
		'name' => 'Vitros 4600',
		'desc' => 'astm',
		'file' => 'vitros_4600.php',
		'dir' => 'vitros_4600',
		'add_fields' => array('te_vitros_4600_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_vitros_4600_sts'
	),
	'ins_immulite_2000xpi' => array(
		'id' => -93,
		'name' => 'Immulite 2000XPi',
		'desc' => 'astm',
		'file' => 'immulite_2000xpi.php',
		'dir' => 'immulite_2000xpi',
		'add_fields' => array('te_immulite_2000xpi_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_immulite_2000xpi_sts'
	),
	'ins_dz_lite_c270' => array(
		'id' => -56,
		'name' => 'DZ-Lite c270',
		'desc' => 'hl7',
		'file' => array(
			'order_port' => 'dz_lite_c270.php',
			'result_port' => 'dz_lite_c270.php',
			'patient_port' => 'dz_lite_c270.php',
			'result_inq_port' => 'dz_lite_c270.php',
			'order_inq_port' => 'dz_lite_c270.php',
		),
		'dir' => 'dz_lite_c270',
		'add_fields' => array('te_dz_lite_c270'),
		'btype' => 'blood',
		'opt' => 'tr_ins_dz_lite_c270_sts'
	),

	'ins_gene' => array(
		'id' => -60,
		'name' => 'Gene',
		'desc' => 'hl7',
		'file' => 'gene.php',
		'dir' => 'gene',
		//'add_fields' => array('te_gene_code'),
		'btype' => 'gene',
		'opt' => 'tr_ins_gene_sts'
	),
	'ins_abbott_id_now' => array(
		'id' => -66,
		'name' => 'Abbott ID NOW',
		'desc' => 'hl7',
		'file' => 'abbott_id_now.php',
		'dir' => 'abbott_id_now',
		'add_fields' => array('te_abbott_id_now'),
		'btype' => 'blood',
		'opt' => 'tr_ins_abbott_id_now'
	),

	'ins_hemox' => array(
		'name' => 'Hemox',
		'desc' => 'study',
		'file' => 'ins_hemox.php',
		'dir' => 'hemox',
		'btype' => 'study',
		'opt' => 'tr_ins_hemox_st'
	),

	'ins_lorcca' => array(
		'name' => 'LORCCA',
		'desc' => 'study',
		'file' => 'ins_lorcca.php',
		'dir' => 'lorcca',
		'btype' => 'study',
		'opt' => 'tr_ins_lorcca_st'
	),

	'ins_dsa' => array(
		'name' => 'DSA',
		'desc' => 'study',
		'file' => 'ins_dsa.php',
		'dir' => 'dsa',
		'btype' => 'study',
		'opt' => 'tr_ins_dsa_st'
	),

	'ins_hplc' => array(
		'name' => 'HPLC',
		'desc' => 'study',
		'file' => 'ins_hplc.php',
		'dir' => 'hplc',
		'btype' => 'study',
		'opt' => 'tr_ins_hplc_st'
	),
	'ins_immtox_120' => array(
		'id' => -81,
		'name' => 'Immtox 120',
		'desc' => 'hl7',
		'file' => array(
			'order_port' => 'immtox_120.php',
			'result_port' => 'immtox_120.php',
			'patient_port' => 'immtox_120.php',
			'result_inq_port' => 'immtox_120.php',
			'order_inq_port' => 'immtox_120.php',
		),
		'dir' => 'immtox_120',
		'add_fields' => array('u_immtox_120_code'),
		'btype' => 'blood',
		'opt' => 'tr_ins_immtox_120_sts'
	),
	'ins_exc200' => array(
		'id' => -83,
		'name' => 'EXC200',
		'desc' => 'hl7',
		'file' => 'exc200.php',
		'dir' => 'exc200',
		'add_fields' => array('te_exc200'),
		'btype' => 'blood',
		'opt' => 'tr_ins_exc200_sts'
	),
	'ins_biomic_pcr' => array(
		'id' => -91,
		'name' => 'BIOMIC',
		'desc' => 'PCR - short-hl7',
		'file' => 'biomic_pcr.php',
		'dir' => 'biomic_pcr',
		'btype' => 'pcr',
		'opt' => 'tr_ins_biomic_pcr_sts'
	),

);
