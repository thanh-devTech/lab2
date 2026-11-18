<?php
global $wpdb, $blog_id;

if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
  return;
}

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$wpdb->suppress_errors(true);

$btypes     = tr_pdd_get_batch_types();

$old_setup  = get_option('ci_old_setup_lab_install', array());


//create tables
$batch_id_type = 'int(10)';
if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
  $batch_id_type = 'varchar(11)';
}

$sql_batches_fields =
  "`mid` bigint(15) NOT NULL AUTO_INCREMENT,
  `batch_id` {$batch_id_type} NOT NULL,
  `assen` varchar(26) DEFAULT NULL COMMENT 'ASSEN',
  `position` int(11) DEFAULT NULL,
  `batch_type` varchar(1) DEFAULT 'M',
  `batch_status` varchar(25) DEFAULT '',
  `batch_short` int(1) DEFAULT '0',
  `short_completed` tinyint(1) DEFAULT '0',
  `ref_lab_ID` int(11) NOT NULL,
  `cli_ID` int(11) NOT NULL,
  `doc_ID` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `col_ID` int(11) NOT NULL,
  `author_ID` int(11) NOT NULL,
  `doc` datetime DEFAULT NULL COMMENT 'DOC',
  `lab` varchar(15) DEFAULT NULL COMMENT 'LAB',
  `patient_id` int(11) NOT NULL,
  `patient_name` varchar(150) DEFAULT NULL COMMENT 'PATIENT NAME',
  `dob` date DEFAULT NULL COMMENT 'DOB',
  `requesting_practice` varchar(200) DEFAULT NULL COMMENT 'REQUESTING PRACTICE',
  `requesting_physician` varchar(200) DEFAULT NULL COMMENT 'REQUESTING PHYSICIAN',
  `physician_phone` varchar(25) NOT NULL,
  `spec_received` datetime DEFAULT NULL COMMENT 'SPEC RECEIVED',
  `comments` text COMMENT 'COMMENTS',
  `panel_tests_requested` text COMMENT 'Panel /Tests requested',
  `current_medication` text,
  `insurance` varchar(150) NOT NULL COMMENT 'INSURANCE',
  `insurance1` int(11) DEFAULT NULL COMMENT 'INSURANCE1',
  `insurance2` int(11) DEFAULT NULL COMMENT 'INSURANCE2',
  `billing` date DEFAULT NULL COMMENT 'Billing',
  `custom_profile` varchar(50) DEFAULT NULL COMMENT 'CUSTOM PROFILE',
  `notes` text,
  `internal_notes` text,
  `billing_notes` text,
  `dagnostic_codes1` text,
  `dagnostic_codes2` text,
  `type_of_specimen` text,
  `temperature` varchar(10) DEFAULT '',
  `actual_temperature` varchar(10) NOT NULL,
  `subscriber_last` text,
  `subscriber_first` text,
  `date_of_injury` date DEFAULT NULL,
  `address_of_claim` varchar(50) NOT NULL,
  `subscriber_zip` varchar(16) NOT NULL,
  `subscriber_insurance` text,
  `subscriber_wc` text,
  `adjuster_name` text,
  `adjuster_phone` varchar(15) NOT NULL,
  `claim_number` varchar(15) NOT NULL,
  `attorney_name` text,
  `attorney_phone` varchar(15) NOT NULL,
  `claim_under_litigation` enum('0','1') DEFAULT '0',
  `poc_perform` enum('0','1') DEFAULT '0',
  `current_medication_other` text,
  `medication` text,
  `ins_comp` varchar(5) DEFAULT 'ins',
  `oldreq` varchar(50) DEFAULT NULL,
  `is_dna` int(2) DEFAULT '0',
  `ins_card_front` text,
  `ins_card_back` text,
  `patient_id_front` text,
  `patient_id_back` text,
  `patient_signature` text,
  `provider_signature` text,
  `batch_modified` datetime NOT NULL,
  `batch_created` datetime NOT NULL,
  `issue_photo` text,
  `claim_id` varchar(50) default '',
  `ms` varchar(5) not null,
  `fasting` tinyint(1) default 0,
  `Partial_Pay` tinyint(1) default 0,
  `result_pdf` text,
  `original_req` text,
  `sent_wsdl` tinyint(1) default 0,
  `box_uploaded` tinyint(1) default 0,
  `saved_status` tinyint(1) default 0,
  `second_logo` text,
  `ref_from_lab_ID` int(11) default 0,
  `sync_ref` int(11),
  `sync_type` varchar(1) default '',
  `reason_for_testing` varchar(25),
  `seal_instact` enum('0','1') DEFAULT '0',
  `received_by` text,
  `receiver_signature` text,
  `tracking_number` varchar(30),
  `current_date` date,
  `doc_urine` datetime,
  `pverify_status` tinyint(1) default 0,
  `quest_barcode` varchar(35),
  `initials1` varchar(10) DEFAULT NULL COMMENT 'use by receiving',
  `short_form_by` int(11),
  `short_form_time` datetime,
  `req_form_by` int(11),
  `req_form_time` datetime,
  `has_vdx` tinyint(1) default 0,
  `culture` tinyint(1) default 0,
  `orderable_test` tinyint(1) default 0,
  `covid_question_id` int(11) default 0,
  `swabbed` tinyint(1) default 0,
  `swabbed2` tinyint(1) default 0,
  `qc` tinyint(1) default 0,
  `is_employee` tinyint(1) default 0,
  `incomplete_patient` tinyint(1) default 0,
  `priority_hours` int(3) default 0,
  `p_pain_score` char(2) default '',
  `p_fatigue_score` char(2) default '',
  `p_condition` varchar(40) default '',
  `p_hemoglobin` varchar(40) default '',
  `notes_report` text,
  `p_pregnant` char(1) default '',
  `p_fin` varchar(50),
  `p_ehr` varchar(5),
  `attach_face_sheet` text,
  `attach_demographics` text,
  `stat` tinyint(1) default 0,
  `is_saved` tinyint(1) default 0,
  `patient_portal_type` varchar(5) default '',
  `archived_date` datetime,
  `paid` tinyint(2) default 0,";


$sql_batches_keys =
  "PRIMARY KEY (`mid`),
  UNIQUE KEY `assen` (`assen`),
  KEY `patient_name` (`patient_name`),
  KEY `patient_id` (`patient_id`),
  KEY `doc` (`doc`),
  KEY `spec_received` (`spec_received`),
  KEY `requesting_practice` (`requesting_practice`),
  KEY `lab_id` (`lab_id`),
  KEY `ref_lab_ID` (`ref_lab_ID`),
  KEY `claim_id` (`claim_id`),
  KEY `type_of_specimen` (`type_of_specimen`(50)),
  KEY `doc_ID` (`doc_ID`),
  KEY `cli_ID` (`cli_ID`),
  KEY `insurance1` (`insurance1`),
  KEY `batch_status` (`batch_status`),
  KEY `position` (`position`),
  KEY `is_saved` (`is_saved`),
  KEY `batch_id` (`batch_id`)";


foreach ($btypes as $btype) {
  $batch_tbl  = tr_pdd_get_table_by_btype($btype);
  $report_tbl = tr_pdd_get_table_report_by_btype($btype);


  $fields = $sql_batches_fields;
  $keys   = $sql_batches_keys;

  if ($btype == 'study') {
    $fields .= "\n" . '`project_ID` int(11),' . "\n";
    $fields .= '`receivingID` int(11),' . "\n";
    $fields .= '`conditions` text,' . "\n";
    $fields .= '`timeframe` text,' . "\n";
    $fields .= '`phase` text,';
  } else if ($btype == 'tox') {
    $fields .= "\n" . 'only_screening tinyint(1) default 0,' . "\n";
    $fields .= "\n" . 'has_screening tinyint(1) default 0,' . "\n";
    $keys .= ',' . "\n" . 'KEY `only_screening` (`only_screening`)';
    $keys .= ',' . "\n" . 'KEY `has_screening` (`has_screening`)';
  } else if ($btype == 'req') {
    $fields .= "\n";
    $fields .= '`collection_agency_name` text,' . "\n";
    $fields .= '`collection_agency_address` text,' . "\n";
    $fields .= '`collection_agency_city` text,' . "\n";
    $fields .= '`collection_agency_state` text,' . "\n";
    $fields .= '`collection_agency_zip` text,' . "\n";
    $fields .= '`collection_agency_phone` text,' . "\n";
    $fields .= '`collection_datetime` datetime,' . "\n";
    $fields .= '`shipment_picked_up_datetime` datetime,';
  }

  $sql = "CREATE TABLE `{$batch_tbl}` (
    {$fields}
    {$keys}
    ) ENGINE=InnoDB ";

  $rs = dbDelta($sql);
  if (isset($_GET['ci_setup'])) {
    echo $batch_tbl;
    echo '<br>';
    echo $sql;
    echo '<br>';
    var_dump($rs);
    var_dump($wpdb->last_error);
    echo '<br><br>';
  }



  $sql = "CREATE TABLE `{$batch_tbl}_meta` (
    `mid` bigint(15) not null,
    `meta_key` varchar(50) NOT NULL,
    `meta_value` text,
    KEY `id_key` (`mid`,`meta_key`,`meta_value`(50))
    ) ENGINE=InnoDB";
  dbDelta($sql);
}


$sql = "CREATE TABLE `ci_test_aoe_headers` (
  `tag_ID` int(11) NOT NULL AUTO_INCREMENT,
  `tag_header` varchar(250) NOT NULL CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `tag_btype` varchar(5) default 'tox',
  `tag_order` int(11) default 0,
  `tag_status` tinyint(1) default 1,
  `tag_created` datetime,
  `tag_created_by` int(11),
  PRIMARY KEY (`tag_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);





$sql = "CREATE TABLE `ci_test_aoe` (
  `ta_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ta_slug` varchar(100),
  `ta_order` int(11) default 0,
  `ta_question` varchar(255) NOT NULL CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `ta_question_type` varchar(10) NOT NULL,
  `ta_answers` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `ta_btype` varchar(5) default 'tox',
  `ta_group_id` int(11) default 0,
  `ta_group_ids` varchar(200) default '',
  `ta_test_id` int(11) default 0,
  `ta_header_id` int(11) default 0,
  `ta_cols` int(3) default 0,
  `ta_start_end_date` tinyint(1) default 0,
  `ta_required` tinyint(1) default 0,
  `ta_created` datetime,
  `ta_created_by` int(11),
  PRIMARY KEY (`ta_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
dbDelta($sql);

$sql = "CREATE TABLE `ci_test_aoe_answers` (
  `an_ID` bigint(15) NOT NULL AUTO_INCREMENT,
  `an_question_id` int(11) NOT NULL,
  `an_mid` int(11) not null,
  `an_answer` text,
  PRIMARY KEY (`an_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
dbDelta($sql);



$sql = "CREATE TABLE `ci_saved_batches` (
  {$sql_batches_fields}
  `only_screening` tinyint(1) default 0,
  `deleted` tinyint(1) default '0',
  {$sql_batches_keys}
) ENGINE=InnoDB ";

$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_batch_billings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient` varchar(50) NOT NULL,
  `claim_no` int(11) NOT NULL,
  `claim_date` date DEFAULT NULL,
  `servdate` date DEFAULT NULL,
  `date` date DEFAULT NULL,
  `insurance_payment` varchar(25) NOT NULL ,
 
  PRIMARY KEY (`id`)
) ENGINE=InnoDB";


$rs = dbDelta($sql);
if (isset($_GET['ci_setup'])) {
  echo "<br>";
  var_dump($rs);
  echo "<br>";
}

$sql = "CREATE TABLE `ci_labs` (
  `lab_ID` int(10) NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(75) NOT NULL,
  `lab_dba` varchar(75) NOT NULL,
  `lab_dba_init` varchar(15) NOT NULL,
  `lab_logo` text,
  `lab_results_logo` text,
  `lab_address` text,
  `lab_city` varchar(100) NOT NULL,
  `lab_state` varchar(10) NOT NULL,
  `lab_zip` varchar(12) NOT NULL,
  `lab_phone` text,
  `lab_fax` text,
  `lab_email` text,
  `lab_site` int(10) DEFAULT NULL,
  `lab_npi` varchar(30) NOT NULL,
  `lab_tax_id` varchar(30) NOT NULL,
  `lab_clia` varchar(30) NOT NULL,
  `lab_status` tinyint(1) default 1,
  `lab_additional` text,
  `lab_is_ref` int(1) default 0,
  `lab_specimen_fee` text,
  `lab_email_notify` text not null,
  `lab_day_sent` date not null,
  `lab_day_notify` datetime not null,
  `lab_results_footer` text not null,
  `ref_lab_additional` text,
  `lab_ref_specimen_fee` text,
  `lab_ref_test_type` text,
  `lab_dna_fee` text,
  `lab_not_specimen` int(1) default 0,
  `freshbook_client_id` int(11) not null,
  `lab_tox_cost` text,
  `lab_blood_cost` text,
  `lab_dna_cost` text,
  `lab_rpp_gi_cost` text,
  `lab_allergy_cost` text,
  `lab_second_logo` text,
  `lab_no_address_rs` int(1) default 0,
  `lab_ref_only_logo` int(1) default 0,
  `lab_ref_only_address` int(1) default 0,
  `lab_conditions` text,
  `ref_lab_change_assen` tinyint(1) default 0,
  `lab_insurance` text,
  `lab_states` text,
  `lab_exclusions` text,
  `lab_medical_director` text,
  `lab_director_signature` text,
  `lab_director` text,
  `lab_cap` text,
  `lab_second_line_hpdf` text,
  `lab_ref_assen_init` tinyint(1) default 0,
  `lab_billing_account` text,
  `lab_color` varchar(8) default '',
  `lab_cola` text,
  `lab_pcr_panels` text,
  `lab_send_billing` tinyint(1) default 1,
  `lab_auto_rej_insurance` text,
  `lab_start_date` date default NULL,
  PRIMARY KEY (`lab_ID`),
  KEY `lab_name` (`lab_name`),
  KEY `lab_dba` (`lab_dba`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);
if (isset($_GET['ci_setup'])) {
  echo "<br>";
  var_dump($rs);
  echo "<br>";
}

$sql = "CREATE TABLE `ci_lab_ref_fees` (
    `ref_lab_ID` int(10) NOT NULL,
    `lab_ID` int(11) not null,
    `lab_specimen_fee` varchar(100) not null,
      UNIQUE KEY `lab_ID` (`ref_lab_ID`,`lab_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql_btype = '';
foreach ($btypes as $btype) {
  $sql_btype .= " `rep_{$btype}` tinyint(1) default 0,\n";
}

$sql = "CREATE TABLE `ci_lab_reps` (
  `rep_ID` int(10) NOT NULL AUTO_INCREMENT,
  `lab_ID` int(10) NOT NULL,
  `rep_first` varchar(100) NOT NULL,
  `rep_last` varchar(100) NOT NULL,
  `rep_middle` varchar(5) NOT NULL,
  `rep_address` varchar(150) NOT NULL,
  `rep_city` varchar(100) NOT NULL,
  `rep_state` varchar(50) NOT NULL,
  `rep_zip` varchar(12) NOT NULL,
  `rep_phone` varchar(18) NOT NULL,
  `rep_fax` varchar(18) NOT NULL,
  `rep_cell` varchar(18) NOT NULL,
  `rep_website` varchar(255) NOT NULL,
  `rep_company_type` varchar(10) not null,
  `rep_state_inc` varchar(10) not null,
  `rep_state_inc_ein` varchar(150) not null,
  `rep_contact_name` varchar(100) not null,
  `rep_contact_phone` varchar(18) not null,
  `rep_contact_fax` varchar(18) not null,
  `rep_email` varchar(250) NOT NULL,
  `rep_comission` text,
  `rep_sdate` varchar(15) NOT NULL,
  `rep_edate` varchar(15) NOT NULL,
  `rep_princial` text not null,
  `rep_payment_type` varchar(10) not null,
  `rep_company_type_other` varchar(25) not null,
  `rep_payment_bank_name` text,
  `rep_payment_aba` text,
  `rep_payment_account_number` text,
  `rep_payment_account_name` text,
  `rep_distributor_ID` int(11) not null,
  `rep_marketing_group_ID` int(11) not null,
  `rep_inactive` int(11) DEFAULT '0',
  `rep_hide_commission` int(1) default 0,
  {$sql_btype}
  PRIMARY KEY (`rep_ID`),
  KEY `rep_sdate` (`rep_sdate`),
  KEY `rep_edate` (`rep_edate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_insurancemix` (
  `ins_ID` int(10) NOT NULL AUTO_INCREMENT,
  `lab_ID` int(10) NOT NULL,
  `ins_name` varchar(200) NOT NULL,
  `ins_code` varchar(10) NOT NULL,
  `ins_in_net` enum('0','1') NOT NULL,
  `ins_out_net` enum('0','1') NOT NULL,
  `ins_unknown` enum('0','1') NOT NULL,
  `ins_auto_wc` enum('0','1') NOT NULL,
  `ins_hmo` enum('0','1') NOT NULL,
  `ins_referenced` enum('0','1') NOT NULL,
  PRIMARY KEY (`ins_ID`),
  UNIQUE KEY `ins_code` (`ins_code`,`ins_name`,`lab_ID`),
  KEY `lab_ID` (`lab_ID`,`ins_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_collector` (
  `col_ID` int(10) NOT NULL AUTO_INCREMENT,
  `doc_ID` int(11) NOT NULL,
  `lab_ID` int(11) NOT NULL,
  `rep_ID` int(11) NOT NULL,
  `col_first` varchar(100) NOT NULL,
  `col_last` varchar(100) NOT NULL,
  `col_middle` varchar(5) NOT NULL,
  `col_street` varchar(150) NOT NULL,
  `col_city` varchar(100) NOT NULL,
  `col_state` varchar(5) NOT NULL,
  `col_zip` varchar(12) NOT NULL,
  `col_phone` varchar(20) NOT NULL,
  `col_email` varchar(150) NOT NULL,
  `col_mon` varchar(15) NOT NULL,
  `col_tue` varchar(15) NOT NULL,
  `col_wed` varchar(15) NOT NULL,
  `col_thu` varchar(15) NOT NULL,
  `col_fri` varchar(15) NOT NULL,
  `col_sat` varchar(15) NOT NULL,
  `col_sun` varchar(15) NOT NULL,
  `col_sdate` date NOT NULL,
  `col_rdate` date NOT NULL,
  `col_notes` varchar(250) NOT NULL,
  `col_poc-cups` enum('0','1') NOT NULL,
  `col_fax-result` enum('0','1') NOT NULL,
  `col_analyser` enum('0','1') NOT NULL,
  `col_portal` enum('0','1') NOT NULL,
  `col_dna` enum('0','1') NOT NULL,
  `col_allergy` enum('0','1') not null,
  `col_tox` enum('0','1') NOT NULL,
  `col_blood` enum('0','1') NOT NULL,
  `col_physicians` text,
  `col_office_phone` varchar(20) NOT NULL,
  `col_fax` varchar(20) NOT NULL,
  `col_cdate` date NOT NULL,
  `col_location` varchar(150) NOT NULL,
  `col_session` varchar(50) not null,
  `col_test_results` enum('0','1') not null,
  `col_ref_tox` enum('0','1') not null,
  `col_rpp_gi` enum('0','1') not null,
  `col_wom` enum('0','1') not null,
  `col_import_ID` int(11),
  `col_signature_date` date,
  `col_urine_tox` int(1) default 0,
  `col_blood_service` int(1) default 0,
  `col_supply_orders` int(1) default 0,
  `col_status` int(1) default 1,
  `col_date_of_training` date,
  `col_training_location` varchar(255),
  `col_trainer` varchar(150),
  `col_employee` tinyint(1) default 0,
  `col_phlebotomist_code` text,
  PRIMARY KEY (`col_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1";
$rs = dbDelta($sql);


$fax_types = '';
foreach ($btypes as $btype) {
  if ($type != 'tox') {
    $fax_types .= " `cli_fax_{$btype}` enum('0','1') NOT NULL,\n";
  }
}

$sql = "CREATE TABLE `ci_clinic` (
  `cID` int(10) NOT NULL AUTO_INCREMENT,
  `cli_lab-ID` int(10) NOT NULL,
  `cli_rep-ID` int(10) NOT NULL,
  `cli_clinic` varchar(150) NOT NULL,
  `cli_street` text,
  `cli_city` varchar(100) NOT NULL,
  `cli_state` varchar(2) NOT NULL,
  `cli_zip` varchar(12) NOT NULL,
  `cli_om-phone` varchar(18) NOT NULL,
  `cli_om-fax` text NOT NULL,
  `cli_om-email` text,
  `cli_mon` text,
  `cli_tue` text,
  `cli_wed` text,
  `cli_thu` text,
  `cli_fri` text,
  `cli_sat` text,
  `cli_sun` text,
  `cli_sdate` date NOT NULL,
  `cli_adate` date NOT NULL,
  `cli_auto` int(3) NOT NULL,
  `cli_in-net` int(3) NOT NULL,
  `cli_in-net-list` text,
  `cli_out-net` int(3) NOT NULL,
  `cli_out-net-list` text,
  `cli_urine` int(10) NOT NULL,
  `cli_oral` int(10) NOT NULL,
  `cli_poc-test` enum('0','1') NOT NULL,
  `cli_off-ana` enum('0','1') NOT NULL,
  `cli_ncollector` enum('0','1') NOT NULL,
  `cli_faxres` enum('0','1') NOT NULL,
  `cli_portal` enum('0','1') NOT NULL,
  `cli_emr-ehr` enum('0','1') NOT NULL,
  `cli_other` enum('0','1') NOT NULL,
  `cli_portal_email` text,
  `cli_current_laboratory` text,
  `cli_payor-mix` text,
  `clinic_name` text,
  `cli_group_ID` int(11) not null,
  `cli_marketing_group_ID` int(11) not null,
  `cli_distributor_ID` int(11) not null,
  `cli_status` int(1) DEFAULT 1,
  `cli_import_ID` int(1) default 0,
  `cli_result_style` int(1) default 0,
  `cli_result_style_pcr` int(1) default 0,
  `cli_result_style_blood` int(1) default 0,
  `cli_tox` tinyint(1) default 0,
  `cli_blood` tinyint(1) default 0,
  `cli_dna` tinyint(1) default 0,
  `cli_allergy` tinyint(1) default 0,
  `cli_wom` tinyint(1) default 0,
  `cli_pcr` tinyint(1) default 0,
  `cli_req` tinyint(1) default 0,
  `cli_gene` tinyint(1) default 0,
  `cli_study` tinyint(1) default 0,
  `cli_ngs` tinyint(1) default 0,
  `cli_vurine_top` tinyint(1) default 0,
  `cli_no_history` tinyint(1) default 0,
  `cli_account_manager` text,
  `cli_courier_type` text,
  `cli_courier_type2` text,
  `cli_print_2labels` tinyint(1) default 0,
  `cli_auto_assen` tinyint(1) default 0,
  `cli_ref_lab_ID` int(5) default 0,
  `cli_send_result_sms` tinyint(1) default 0,
  `cli_nowc_sms` tinyint(1) default 0,
  `cli_show_submit_print_bc` tinyint(1) default 1,
  `cli_show_abx_report` tinyint(1) default 0,
  `cli_swab_code` text,
  `cli_elink_id` varchar(10) default '',
  `cli_elink_id2` varchar(10) default '',
  `cli_experity_id` text default '',
  `paperwork` text,
  `cli_pcr_uri` int(11),
  `cli_pcr_uti` int(11),
  `cli_pcr_wound` int(11),
  `cli_pcr_covid` int(11),
  `cli_pcr_prefillcovid` tinyint(1) default 0,
  `cli_only_one` tinyint(1) default 0,
  `cli_notes` text,
  `cli_athena_departmentid` text,
  `cli_athena_departmentid2` text,
  `cli_solv_hash` text,
  `cli_show_reason_testing` tinyint(1) default 0,
  `cli_covid_export_v` tinyint(1) default 0,
  `cli_default_doc_ID` int(11) default 0,
  `cli_sms_number` text,
  `cli_Elink_lab_ID` int(10),
  `cli_Telcor_ID` int(10),
  `cli_report_by_collector` tinyint(1) default 0,
  `cli_only_result` tinyint(1) default 0,
  `cli_phlebotomist` tinyint(1) default 0,
  `cli_other_options` text,
  `cli_import_id` text,
  `cli_tox_df_profile` text,
  `cli_screening` tinyint(1) default 0,
  `cli_only_screening` tinyint(1) default 0,
  {$fax_types}
  PRIMARY KEY (`cID`),
  KEY `cli_clinic` (`cli_clinic`),
  KEY `cli_lab-ID` (`cli_lab-ID`),
  KEY `cli_rep-ID` (`cli_rep-ID`),
  UNIQUE KEY `cli_clinic_u`(`cli_lab-ID`,`cli_rep-ID`,`cli_clinic`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
$rs = dbDelta($sql);
if (isset($_GET['ci_setup'])) {
  var_dump($rs);
}



$sql = "CREATE TABLE `ci_clinic_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cID` int(11) not null,
  `meta_key` varchar(255) NOT NULL,
  `meta_value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_key` (`cID`,`meta_key`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_doc` (
  `doc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `cli_ID` int(10) NOT NULL,
  `doc_first` varchar(100) NOT NULL,
  `doc_last` varchar(100) NOT NULL,
  `doc_middle` varchar(5) NOT NULL,
  `doc_address` varchar(150) NOT NULL,
  `doc_city` varchar(100) NOT NULL,
  `doc_state` varchar(2) NOT NULL,
  `doc_zip` varchar(12) NOT NULL,
  `doc_phone` varchar(18) NOT NULL,
  `doc_cell` varchar(18) NOT NULL,
  `doc_email` varchar(150) NOT NULL,
  `doc_specialty` varchar(30) NOT NULL,
  `doc_npi` varchar(100) NOT NULL,
  `doc_sdate` date NOT NULL,
  `doc_edate` date NOT NULL,
  `doc_mnemonic` varchar(30) not null,
  `doc_import_ID` int(1) default 0,
  `doc_created` datetime,
  `doc_created_by` int(11),
  `doc_last_order` datetime,
  `doc_signature` varchar(100),
  `doc_provider_code` varchar(20) default '',
  `doc_athena_username` text,
  `group_ID` int(11) default 0,
  PRIMARY KEY (`doc_ID`),
  KEY `rep_sdate` (`doc_sdate`),
  KEY `rep_edate` (`doc_edate`),
  KEY `cli_ID` (`cli_ID`),
  KEY `doc_npi`(`doc_npi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);




$sql = "CREATE TABLE `ci_doc_groups` (
  `group_ID` int(10) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100),
  `cli_IDs` text,
  `istatus` tinyint(1) default 1,
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`group_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_clinic_doc` (
  `doc_ID` int(10) NOT NULL,
  `cli_ID` int(10) NOT NULL,
  `date_order` datetime,
  KEY `doc_ID` (`doc_ID`),
  KEY `cli_ID` (`cli_ID`),
  UNIQUE KEY `cli_doc_ID` (`cli_ID`,`doc_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_clinic_collector` (
  `col_ID` int(10) NOT NULL,
  `cli_ID` int(10) NOT NULL,
  UNIQUE KEY `cli_col_ID` (`cli_ID`,`col_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_clinic_manager` (
  `ma_ID` int(10) NOT NULL AUTO_INCREMENT,
  `ma_cli_ID` int(10) NOT NULL,
  `ma_manager` varchar(100) NOT NULL,
  `ma_email` varchar(100) NOT NULL,
  `ma_user_ID` int(11),
  PRIMARY KEY (`ma_ID`),
  UNIQUE KEY `cli_ma_ID` (`ma_cli_ID`,`ma_email`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_rep_collector` (
  `rep_ID` int(10) NOT NULL,
  `col_ID` int(10) NOT NULL,
  UNIQUE KEY `rep_col_ID` (`rep_ID`,`col_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_ar_aging` (
  `aging_ID` int(11) NOT NULL AUTO_INCREMENT,
  `location` varchar(2) DEFAULT NULL,
  `doctor` int(1) DEFAULT NULL,
  `inscode` varchar(6) DEFAULT NULL,
  `insname` varchar(85) DEFAULT NULL,
  `cbalance00` decimal(10,2) DEFAULT NULL,
  `cbalance30` decimal(10,2) DEFAULT NULL,
  `cbalance60` decimal(10,2) DEFAULT NULL,
  `cbalance90` decimal(10,2) DEFAULT NULL,
  `cbalance120` decimal(10,2) DEFAULT NULL,
  `ibalance00` decimal(10,2) DEFAULT NULL,
  `ibalance30` decimal(10,2) DEFAULT NULL,
  `ibalance60` decimal(10,2) DEFAULT NULL,
  `ibalance90` decimal(10,2) DEFAULT NULL,
  `ibalance120` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`aging_ID`),
  KEY `inscode` (`inscode`),
  KEY `insname` (`insname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);



$sql_patient = "CREATE TABLE `ci_patients` (
 `pa_ID` int(11) NOT NULL AUTO_INCREMENT,
  `pa_first` varchar(100) DEFAULT NULL,
  `pa_last` varchar(100) DEFAULT NULL,
  `pa_middle` varchar(20) DEFAULT NULL,
  `pa_dob` date default null,
  `pa_phone` varchar(18) DEFAULT NULL,
  `pa_gender` varchar (1) DEFAULT '',
  `pa_address` varchar(150) NOT NULL,
  `pa_unit` text,
  `pa_room` text,
  `pa_bed` text,
  `pa_city` varchar(100) NOT NULL,
  `pa_state` varchar(4) NOT NULL,
  `pa_zip` varchar(12) NOT NULL,
  `pa_ssn` varchar(20) not null,
  `pa_apt` varchar(20) not null,
  `pa_opi_id` varchar(100) not null,
  `pa_opi_npi` varchar(100) not null,
  `pa_opi_key` varchar(100) not null,
  `pa_mrn` varchar(20),
  `pa_height_ft` varchar(10),
  `pa_height_in` varchar(10),
  `pa_weight` varchar(15),
  `pa_waist` varchar(15),
  `pa_blood_pressure` varchar(15),
  `pa_patient_id` varchar(20),
  `pa_clinic_ID` int(11),
  `pa_race` varchar(10),
  `pa_ethnicity` varchar(10),
  `pa_code` varchar(20),
  `pa_email` varchar(200),
  `pa_created` datetime,
  `pa_modified` datetime,
  `pa_created_by` int(11),
  `pa_symptomatic` char(1),
  `pa_interested_research` varchar(1) default '',
  `pa_bill_notes` text,
  `pa_notes` text,
  `pa_import_ID` int(11),
  `pa_patient_type` varchar(3),
  `pa_fully_vaccinated` varchar(3) default '',
  `pa_phlebotomist_class` text,
  `pa_genotypes` text,
  `pa_hb_genotype` text,
  `pa_marital_status` text,
  `pa_height` text,
  `pa_weight` text,
  `pa_sleep` text,
  `pa_exercise` text,
  `pa_location` text,
  `pa_passport` text,
  `pa_passport_front` text,
  `pa_driver_license_id` text,
  `pa_driver_license_front` text,
  `pa_driver_license_back` text,
  `pa_state_id` text,
  `pa_state_front` text,
  `pa_state_back` text,
  PRIMARY KEY (`pa_ID`),
  KEY `pa_name2` (`pa_first`,`pa_last`,`pa_dob`),
  KEY `pa_phone` (`pa_phone`),
  KEY `pa_dob` (`pa_dob`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_patient);

$wpdb->query("alter table drop key `pa_name`");

$sql_patient_history = str_replace('ci_patients', 'ci_patients_history', $sql_patient);
$sql_patient_history = str_replace('pa_ID', 'ID', $sql_patient_history);
$sql_patient_history = str_replace('AUTO_INCREMENT,', "AUTO_INCREMENT,\n`pa_ID` int(11),", $sql_patient_history);
$rs = dbDelta($sql_patient_history);



$sql = "CREATE TABLE `ci_patients_payers_udc` (
 `pa_ID` int(11) NOT NULL ,
  `payer_ID` int(11) NOT NULL,
  `approved_year` int(4) NOT NULL,
  `approved_by` int(11),
  `created` datetime,
  UNIQUE KEY `pa_payer` (`pa_ID`,`payer_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_medical_numbers` (
 `med_ID` int(11) NOT NULL AUTO_INCREMENT,
  `med_pa_ID` int(11) NOT NULL,
  `med_cli_ID` int(11) not NULL,
  `med_number` varchar(15) not null,
  KEY med_pa_ID (`med_pa_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql_insurance = "CREATE TABLE `ci_insurances` (
 `ins_ID` int(11) NOT NULL AUTO_INCREMENT,
  `pa_ID` int(11) not null,
  `lab_ID` int(11) not null,
  `ins_first` varchar(100) DEFAULT NULL,
  `ins_last` varchar(100) DEFAULT NULL,
  `ins_relation` varchar(8) DEFAULT NULL,
  `ins_company` varchar(100) DEFAULT NULL,
  `ins_payer_ID` int(11) not null,
  `ins_address` text,
  `ins_city` text,
  `ins_state` varchar(2) NOT NULL,
  `ins_zip` varchar(12) NOT NULL,
  `ins_phone` varchar(25) not null,
  `ins_email` text,
  `ins_dob` date not null,
  `ins_member_id` varchar(24) not null,
  `ins_group_id` varchar(20) not null,
  `ins_authorization` enum('0','1') not null,
  `ins_authorization_id` varchar(15) not null,
  `ins_authorization_date` date default null,
  `ins_mnemonic` varchar(30) not null,
  `ins_nursing_employee` tinyint(1) default 0,
  `ref_Lab_ID` int(11) not null,
  `ref_Lab_Name` text,
  `ref_Address` text,
  `ref_City` text,
  `ref_State` text,
  `ref_Zip` text,
  `ins_cares_act` tinyint(1) default 0,
  `ins_created` datetime,
  `ins_modified` datetime,
  `ins_created_by` int(11),
  `ins_is_second` tinyint(1) default 0,
  `ins_fin_mnemonic` text,
  `ins_card_front` text,
  `ins_card_back` text,
  `ins_code` VARCHAR(50) NULL DEFAULT,
  PRIMARY KEY (`ins_ID`),
  KEY `pa_ID` (`pa_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql_insurance);


$sql_insurance_history = str_replace('ci_insurances', 'ci_insurances_history', $sql_insurance);
$sql_insurance_history = str_replace('ins_ID', 'ID', $sql_insurance_history);
$sql_insurance_history = str_replace('AUTO_INCREMENT,', "AUTO_INCREMENT,\n`ins_ID` int(11),", $sql_insurance_history);
$rs = dbDelta($sql_insurance_history);




$sql = "CREATE TABLE `ci_ins_equiv` (
 `equiv_ID` int(11) NOT NULL AUTO_INCREMENT,
  `equiv_mnemonic` varchar(30) DEFAULT NULL,
  `equiv_name` varchar(100) DEFAULT NULL,
  `equiv_financial_class` varchar(100) DEFAULT NULL,
  `equiv_in_network` int(1) default null,
  `equiv_jvhl` int(1) NOT NULL,
  `equiv_jvhl_code` varchar(50) NOT NULL,
  `equiv_clms_ID` varchar(15) NOT NULL,
  PRIMARY KEY (`equiv_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_messages` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(150) NOT NULL,
  `msg` text,
  `author` int(11) NOT NULL,
  `created_date` datetime,
  `from_date` date,
  `to_date` date,
  `background` varchar(8),
  `msg_type` varchar(1) default 'n',
  `read_roles` varchar(255) default '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);

$wpdb->query("alter table ci_messages drop column sendtype");

$sql = "CREATE TABLE `ci_message_users` (
  `msg_ID` int(11) NOT NULL,
  `user_id` int(11),
  `readed` int(1) default 0,
  UNIQUE KEY `msg_user` (`msg_ID`,`user_id`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);
$wpdb->query("alter table ci_message_users drop column sender");
$wpdb->query("alter table ci_message_users drop column istatus");

$sql = "CREATE TABLE `ci_priors` (
`pri_ID` int(11) NOT NULL AUTO_INCREMENT,
`pri_mid` int(11) not null,
`pri_assen` varchar(16) not null,
`pri_plan_name` varchar(100) DEFAULT NULL,
`pri_plan_fax` varchar(15) DEFAULT NULL,
`pri_plan_email` varchar(150) DEFAULT NULL,
`pri_date` date not null,
`pri_ins_id` varchar(16) NOT NULL,
`pri_cpt` text,
`pri_diagnosis` text,
`pri_comments` text,
PRIMARY KEY (`pri_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_custom_providers` (
 `cus_ID` int(11) NOT NULL AUTO_INCREMENT,
 `cli_ID` int(11) not null,
 `doc_ID` int(11) not null,
 `cus_manager` varchar(100) not null,
 `cus_office_man` varchar(100) not null,
 `cus_phone` varchar(20) not null,
  `cus_email` varchar(150) DEFAULT NULL,
  `cus_enzyme` int(1),
  `cus_point_of_care` int(1),
  `cus_testing_profile` varchar(150) DEFAULT NULL,
  `cus_confirm_positive` enum('0','1') DEFAULT '0',
  `cus_individuals` text,
  `cus_med_risk` text,
  `cus_high_risk` text,
  `cus_comprehensive` text,
  `cus_last` varchar(100) DEFAULT NULL,
  `cus_first` varchar(100) DEFAULT NULL,
  `cus_specialty` varchar(100) DEFAULT NULL,
  `cus_provider_date` date not null,
  `cus_injections` varchar(100) not null,
  `cus_include_signature` enum('0','1') DEFAULT '0',
  `cus_tox` int(1) DEFAULT 0,
  `cus_dna` int(1) DEFAULT 0,
  `cus_blood` int(1) DEFAULT 0,
  `cus_allergy` int(1) DEFAULT 0,
  `cus_ref_tox` int(1) DEFAULT 0,
  `cus_signature` varchar(300) not null,
  `cus_signature_upload` int(1) default 0,
  `cus_result_style` int(1) default 1,
  `cus_ref_account` int(1) default 0,
  `cus_type` varchar(10) default 'tox',
  `cus_bwc` int(1) default 0,
  `cus_df_profile` text,
  PRIMARY KEY (`cus_ID`),
  KEY `doc_ID` (`doc_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_payer_list` (
`payer_ID` int(11) NOT NULL AUTO_INCREMENT,
`payer_name` varchar(150) not null,
`payer_name_search` varchar(150) not null,
`payer_mnemonic` varchar(30) not null,
`payer_fin_mnemonic` varchar(30) not null,
`payer_address` varchar(250) not null,
`payer_city` varchar(100) not null,
`payer_state` varchar(10) not null,
`payer_zip` varchar(20) not null,
`payer_phone` varchar(25) not null,
`payer_class_code` varchar(25) not null,
`payer_financial_class_code` varchar(25) not null,
`payer_commercial` enum('0','1') NOT NULL,
`payer_medicaid` enum('0','1') NOT NULL,
`payer_unknown` enum('0','1') NOT NULL,
`payer_medicare` enum('0','1') NOT NULL,
`payer_workmen` enum('0','1') NOT NULL,
`payer_other` enum('0','1') NOT NULL,
`payer_commercial_kit` enum('0','1') NOT NULL DEFAULT '0',
`payer_pverify_ID` varchar(10) not null,
`payer_pverify_insert` int(1) DEFAULT  0,
`payer_udc_limit` int(10) DEFAULT  0, 
`payer_udc_limit` int(10) DEFAULT  0,
`payer_udc_patient_limit` int(10) DEFAULT  0, 
PRIMARY KEY (`payer_ID`),
UNIQUE KEY `payer_name` (`payer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_receiving` (
 `re_ID` int(11) NOT NULL AUTO_INCREMENT,
 `re_today` date,
 `re_tracking_number_barcode` varchar(50) not null,
 `re_lab_ID` int(11) not null,
 `re_practice` text,
 `re_collector` text,
 `re_qty_urine` int(6) default 0,
 `re_qty_oral` int(6) default 0,
 `re_qty_jvhl` int(6) default 0,
 `re_qty_jvhlo` int(6) default 0,
 `re_qty_blood` int(6) default 0,
 `re_qty_dna` int(6) default 0,
 `re_qty_pcr` int(6) default 0,
 `re_qty_rpcr` int(6) default 0,
 `re_qty_nfpcr` int(6) default 0,
 `re_qty_swab` int(6) default 0,
 `re_qty_stool` int(6) default 0,
 `re_damaged` int(11) default 0,
 `re_author_id` int(11) not null,
 `re_priority` varchar(10) not null,
 `re_timing` varchar(10) not null,
 `re_type` tinyint(1) default 0,
 `re_ref_lab_ID` int(11) default 0,
 `re_prev_pos_urine` int(4) default 0,
 `re_prev_pos_oral` int(4) default 0,
 `re_prev_pos_jvhl` int(4) default 0,
 `re_prev_pos_jvhlo` int(4) default 0,
 `re_prev_pos_blood` int(4) default 0,
 `re_prev_pos_dna` int(4) default 0,
 `re_prev_pos_pcr` int(4) default 0,
 `re_prev_pos_rpcr` int(4) default 0,
 `re_prev_pos_nfpcr` int(4) default 0,
 `re_batch_id` varchar(12) not null,
 `re_created` datetime,
 `re_manifest` tinyint(1) default 0,
  PRIMARY KEY (`re_ID`)
) ENGINE=InnoDB ";
$rs = dbDelta($sql);

$wpdb->query("alter table ci_receiving_batches drop key re_batch");

$sql = "CREATE TABLE `ci_receiving_batches` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `re_ID` int(11) NOT NULL,
 `mid` int(11) not null,
 `btype` varchar(10) default 'tox',
 `subtype` varchar(1) default '',
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `re_batch_type` (`re_ID`,`mid`,`btype`,`subtype`),
  KEY `mid_btype` (`mid`,`btype`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



if (in_array('study', $btypes)) {


  $sql = "CREATE TABLE `ci_study` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(200),
  `istatus` tinyint(1) default 1,
  `max_slides` int(10) default 0,
  `frozen_blood` int(10) default 0,
  `freezer_code` varchar(20) default '',
  `template` text,
  `csvbycondition` tinyint(1) default 0,
  `created` datetime,
  `created_by` int(10) default 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1";
  $rs = dbDelta($sql);



  $sql = "CREATE TABLE `ci_receiving_study` (
  `re_ID` int(11) NOT NULL AUTO_INCREMENT,
  `re_today` date,
  `re_tracking_number_barcode` varchar(50) not null,
  `re_lab_ID` int(11) not null,
  `re_practice` text,
  `re_collector` text,
  `re_qty` int(6) default 0,
  `re_author_id` int(11) not null,
  `re_priority` varchar(10) not null,
  `re_timing` varchar(10) not null,
  `re_created` datetime,
  `re_type` tinyint(1) default 0,
   PRIMARY KEY (`re_ID`)
 ) ENGINE=InnoDB ";
  $rs = dbDelta($sql);



  $sql = "CREATE TABLE `ci_receiving_study_batches` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `re_ID` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `code` varchar(14) NOT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `irb` text DEFAULT NULL,
  `patientid` varchar(30),
  `inclusion` text DEFAULT NULL,
  `siteid` text DEFAULT NULL,
  `study_assigned` text DEFAULT NULL,
  `doc` datetime DEFAULT NULL,
  `number_assen` varchar(14) NOT NULL,
  `condition` text DEFAULT NULL,
  `timepoint` text DEFAULT NULL,
  `sampleid` text DEFAULT NULL,
  `edta` text DEFAULT NULL,
  `edta_value` text DEFAULT NULL,
  `edta_id` text DEFAULT NULL,
  `citrate` text DEFAULT NULL,
  `citrate_value` text DEFAULT NULL,
  `citrate_id` text DEFAULT NULL,
  `sst_tube` text DEFAULT NULL,
  `sst_tube_value` text DEFAULT NULL,
  `sst_tube_id` text DEFAULT NULL,
  `freezer_code` text DEFAULT NULL,
  `slides` text DEFAULT NULL,
   PRIMARY KEY (`ID`),
   UNIQUE KEY `re_batch` (`re_ID`,`code`),
   KEY `patientid` (`patientid`)
 ) ENGINE=InnoDB";
  $rs = dbDelta($sql);
  if (isset($_GET['ci_setup'])) {
    var_dump($rs);
  }


  $sql = "CREATE TABLE `ci_study_sites` (
  `ID` int(9) NOT NULL AUTO_INCREMENT,
 `key` varchar(20),
 `desc` text,
 `iorder` int(11) default 0,
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
  $rs = dbDelta($sql);


  $sql = "CREATE TABLE `ci_study_irbs` (
    `ID` int(9) NOT NULL AUTO_INCREMENT,
   `key` varchar(20),
   `desc` text,
   `iorder` int(11) default 0,
   `created` datetime,
   `created_by` int(11),
    PRIMARY KEY (`ID`)
  ) ENGINE=InnoDB";
  $rs = dbDelta($sql);


  $sql = "CREATE TABLE `ci_study_inclusion` (
      `ID` int(9) NOT NULL AUTO_INCREMENT,
     `key` varchar(20),
     `desc` text,
     `iorder` int(11) default 0,
     `created` datetime,
     `created_by` int(11),
      PRIMARY KEY (`ID`)
    ) ENGINE=InnoDB";
  $rs = dbDelta($sql);

  $sql = "CREATE TABLE `ci_study_exclusion` (
    `ID` int(9) NOT NULL AUTO_INCREMENT,
   `key` varchar(20),
   `desc` text,
   `iorder` int(11) default 0,
   `created` datetime,
   `created_by` int(11),
    PRIMARY KEY (`ID`)
  ) ENGINE=InnoDB";
  $rs = dbDelta($sql);

  $sql = "CREATE TABLE `ci_study_tests` (
  `ID` int(9) NOT NULL AUTO_INCREMENT,
 `abrev` varchar(50),
 `desc` text,
 `units` text,
 `minimumvolumewholebloodul` text,
 `anti-coagulant` text,
 `reported_values` text,
 `iorder` int(11) default 0,
 `sysmex_xn_530` tinyint(1) default 0,
 `phoresis` tinyint(1) default 0,
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
  $rs = dbDelta($sql);




  $sql = "CREATE TABLE `ci_reports_study` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11),
  `patient_id` varchar(30) DEFAULT NULL,
  `estatus` varchar(1),
  `author_id` int(11),
  `created_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(15) DEFAULT NULL,
  `show_comment1` tinyint(1) NOT NULL,
  `show_comment2` tinyint(1) NOT NULL,
  `box_uploaded` tinyint(1) DEFAULT '0',
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `final_results` tinyint(1) default 0,
  `verified` tinyint(1) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `rerun_count` tinyint(1) default 0,
  `rerun_modified` int(11) default 0,
  `approved_by_ID` int(11) default 0,
  `is_pending` tinyint(1) default 1,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results_study` (
  `id` bigint(16) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `drug` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `conc` text,
  `pos-neg` varchar(3),
  `comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `has_order` tinyint(1) default 1,
  `instrument` varchar(15) DEFAULT NULL,
  `group_id` int(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id_drug`(`report_id`,`drug`),
  KEY `has_order` (`has_order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);
}


$sql = "CREATE TABLE `ci_receiving_positions` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `bname` varchar(35),
 `btype` varchar(10) default 'tox',
 `subtype` varchar(35),
 `specimen` varchar(100),
 `position` int(6) default 0,
 `ref_lab` int(6) default 0,
 `sort_order` int(6) default 0,
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `bntype` (`btype`,`subtype`,`specimen`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_compendium` (
 `co_ID` int(11) NOT NULL AUTO_INCREMENT,
 `co_bill_code` varchar(15) not null,
 `co_order_code` varchar(15) not null,
 `co_order_name`  varchar(100) not null,
 `co_order_able` enum('N','Y'),
 `co_loinc_code` varchar(15) not null,
 `co_unit_measure` varchar(10) not null,
 `co_cpt_code` varchar(15) not null,
 `co_specimen_type` varchar(50) not null,
 `co_utilization_rate` varchar(15) not null,
 `co_method_name` varchar(50) not null,
 `co_instrument_name` varchar(50) not null,
 `co_panel` varchar(255) not null,
  PRIMARY KEY (`co_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);

$group_types = '';
foreach ($btypes as $btype) {
  $group_types .= " `gr_{$btype}` tinyint(1) default 0,\n";
}


$sql = "CREATE TABLE `ci_marketing_groups` (
`gr_ID` int(11) NOT NULL AUTO_INCREMENT,
`gr_name`  varchar(150) not null,
`gr_admins` varchar(300) not null,
`gr_members` varchar(500) not null,
`gr_site_id` int(5) not null,
`gr_address` varchar(150) NOT NULL,
`gr_city` varchar(100) NOT NULL,
`gr_state` varchar(50) NOT NULL,
`gr_zip` varchar(12) NOT NULL,
`gr_phone` varchar(18) NOT NULL,
`gr_fax` varchar(18) NOT NULL,
`gr_cell` varchar(18) NOT NULL,
`gr_website` varchar(255) NOT NULL,
`gr_company_type` varchar(10) not null,
`gr_state_inc` varchar(10) not null,
`gr_state_inc_ein` varchar(150) not null,
`gr_contact_name` varchar(100) not null,
`gr_contact_phone` varchar(18) not null,
`gr_contact_fax` varchar(18) not null,
`gr_email` varchar(250) NOT NULL,
`gr_comission` varchar(50) NOT NULL,
`gr_sdate` varchar(15) NOT NULL,
`gr_edate` varchar(15) NOT NULL,
`gr_princial` text not null,
`gr_payment_type` varchar(10) not null,
`gr_company_type_other` varchar(25) not null,
`gr_payment_bank_name` text,
`gr_payment_aba` text,
`gr_payment_account_number` text,
`gr_payment_account_name` text,
`gr_distributor_ID` int(11) not null,
`gr_inactive` int(1) DEFAULT '0',
`gr_tox_phase` int(1) DEFAULT '0',
{$group_types}
PRIMARY KEY (`gr_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);




$sql = "CREATE TABLE `ci_ap_clms` (
 `ap_ID` int(11) NOT NULL AUTO_INCREMENT,
 `ap_date` date,
 `ap_Lab_ID`  int(11) not null,
 `ap_Service_fees` int(11) not null,
 `ap_Equipment_Lease` int(11) not null,
 `ap_Rent` int(11) not null,
 `ap_BOP_Insurance` int(11) not null,
 `ap_Credentialing` int(11) not null,
 `ap_Employee_Leasing` int(11) not null,
 `ap_Lab_Director` int(11) not null,
 `ap_Printing` int(11) not null,
 `ap_Shipping` int(11) not null,
 `ap_Accreditation` int(11) not null,

  PRIMARY KEY (`ap_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_levey_jennings` (
 `lev_ID` int(11) NOT NULL AUTO_INCREMENT,
 `lev_date` date,
 `lev_Lab_ID`  int(11) not null,

  PRIMARY KEY (`lev_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

//-------------------------------------------------------

$sql = "CREATE TABLE `ci_log_forms` (
	`id` bigint(15) NOT NULL AUTO_INCREMENT,
  `form` varchar(50) DEFAULT NULL,
  `data` MEDIUMTEXT,
  `author_id` int(11) not null,
  `target_id` int(11),
  `blog_id` int(11),
  `created_date` datetime,
  `user_log_id` bigint(15),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_log_users` (
  `id` bigint(15) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `lab_id` int(11),
  `user_action` varchar(50),
  `data` text,
  `ip` varchar(40),
  `mid` bigint(15),
  `btype` varchar(6),
  `assen` varchar(255),
  `created` datetime,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `mid_btype` (`mid`,`btype`),
  KEY `user_action` (`user_action`),
  KEY `created` (`created`)
) ENGINE=InnoDB";
dbDelta($sql);


//for results-------------------------------------------------------

$sql = "CREATE TABLE `ci_drug_analyte_pairs` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `drug_1` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `drug_2` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `drug_3` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `drug_4` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `new_comment` text,
  `analyte_replace` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
dbDelta($sql);

$sql = "CREATE TABLE `ci_drug_logic` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `medication` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `concent` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `anticipated` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `detected` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `detected_oral` varchar(256) default null,
  `cutoff` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8_unicode_ci,
  `from_sheet` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medication` (`medication`(255)),
  KEY `anticipated` (`anticipated`(255)),
  KEY `concent` (`concent`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
dbDelta($sql);



$sql = "CREATE TABLE `ci_excel` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `filename` text,
  `row` int(10) DEFAULT NULL,
  `col` varchar(256) DEFAULT NULL,
  `val` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

dbDelta($sql);

$class_fields = '';
foreach (tr_pdd_get_report_medis() as $num) {
  $class_fields .= "has_{$num} tinyint(1) , \n";
  $class_fields .= "has_{$num}_pos tinyint(1) , \n";
}

if (in_array('tox', $btypes)) {

  $sql = "CREATE TABLE `ci_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `info` text,
  `batch_id` int(11),
  `patient_name` varchar(150),
  `patient_id` varchar(30) DEFAULT NULL,
  `acquisition_date` date DEFAULT '0000-00-00',
  `physician` text,
  `sublab` varchar(30) DEFAULT NULL,
  `reflab` varchar(15) DEFAULT NULL,
  `sample_type` varchar(20) DEFAULT NULL,
  `validity` tinyint(1) DEFAULT '0',
  `estatus` varchar(1),
  `author_id` int(11),
  `created_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(100) DEFAULT NULL,
  `expiration_date` varchar(20),
  `show_comment1` tinyint(1) DEFAULT 0,
  `show_comment2` tinyint(1) DEFAULT 0,
  `box_uploaded` tinyint(1) DEFAULT '0',
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT 0,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `final_results` tinyint(1) default 0,
  `count_drugs` int(2) default 0,
  `has_reflex` tinyint(1) default null,
  `covid_pos` tinyint(1) default null,
  `show3pathogen_note` tinyint(1) default 0,
  `shownogrowth_note` tinyint(1) default 0,
  `approved_by_ID` int(11) default 0,
    {$class_fields}
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results` (
  `id` bigint(16) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `drug` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `conc` varchar(15),
  `pos-neg` varchar(3),
  `cutoff` varchar(15),
  `poct` varchar(3),
  `ia` varchar(3),
  `crea_adj` varchar(25),
  `comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `has_order` tinyint(1) default 1,
  `group_id` int(5) DEFAULT NULL,
  `instrument` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id_drug`(`report_id`,`drug`),
  KEY `has_order` (`has_order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);




  $sql = "CREATE TABLE `ci_result_metha` (
  `report_id` int(11) DEFAULT NULL,
  `d` varchar(15),
  `l` varchar(15),
  `comments` text,
  UNIQUE KEY `report_id`(`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);
}

if (in_array('blood', $btypes)) {

  $sql = "CREATE TABLE `ci_reports_blood` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `info` text,
  `batch_id` int(11) DEFAULT 0,
  `patient_name` varchar(150) NOT NULL,
  `patient_id` varchar(30) DEFAULT NULL,
  `acquisition_date` date DEFAULT '0000-00-00',
  `physician` text,
  `sublab` varchar(30) DEFAULT NULL,
  `reflab` varchar(15) DEFAULT NULL,
  `sample_type` varchar(20) DEFAULT NULL,
  `validity` tinyint(1) DEFAULT '0',
  `estatus` varchar(1),
  `author_id` int(11) NOT NULL,
  `created_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(15) DEFAULT NULL,
  `expiration_date` varchar(20) DEFAULT '',
  `show_comment1` tinyint(1) DEFAULT 0,
  `show_comment2` tinyint(1) DEFAULT 0,
  `box_uploaded` tinyint(1) DEFAULT 0,
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `final_results` tinyint(1) default 0,
  `verified` tinyint(1) default 0,
  `count_drugs` int(2) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `covid_pos` tinyint(1) default null,
  `show3pathogen_note` tinyint(1) default 0,
  `shownogrowth_note` tinyint(1) default 0,
  `is_pending` tinyint(1) default 1,
  `approved_by_ID` int(11) default 0,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results_blood` (
  `id` bigint(16) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `drug` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `conc` varchar(15),
  `pos-neg` varchar(3),
  `comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `has_order` tinyint(1) default 1,
  `group_id` int(5) DEFAULT NULL,
  `instrument` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id_drug`(`report_id`,`drug`),
  KEY `has_order` (`has_order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);
}

if (in_array('pcr', $btypes)) {

  $sql = "CREATE TABLE `ci_reports_pcr` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11),
  `patient_id` varchar(30) DEFAULT NULL,
  `sublab` varchar(30) DEFAULT NULL,
  `validity` tinyint(1) DEFAULT '0',
  `estatus` varchar(1),
  `author_id` int(11),
  `created_date` date DEFAULT NULL,
  `acquisition_date` date DEFAULT '0000-00-00',
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(15) DEFAULT NULL,
  `expiration_date` varchar(20) DEFAULT '',
  `show_comment1` tinyint(1) DEFAULT 0,
  `show_comment2` tinyint(1) DEFAULT 0,
  `box_uploaded` tinyint(1) DEFAULT '0',
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `final_results` tinyint(1) default 0,
  `verified` tinyint(1) default 0,
  `missingcovid` tinyint(1) default 0,
  `count_drugs` int(2) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `covid_pos` tinyint(1) default null,
  `rerun_count` tinyint(1) default 0,
  `rerun_modified` int(11) default 0,
  `covid_inc_completed` tinyint(1) default 0,
  `covid_re_prabrp` tinyint(1) default null,
  `has_uti` tinyint(1) default null,
  `show3pathogen_note` tinyint(1) default 0,
  `shownogrowth_note` tinyint(1) default 0,
  `approved_by_ID` int(11) default 0,
  `is_pending` tinyint(1) default 1,
  `norerun_role` tinyint(1) default 0,
  `clinical_pharmacy_guidance` text,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `estatus` (`estatus`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  KEY `has_uti` (`has_uti`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results_pcr` (
`id` bigint(16) NOT NULL AUTO_INCREMENT,
`report_id` int(11) DEFAULT NULL,
`drug` varchar(86) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
`conc` varchar(15),
`pos-neg` varchar(3),
`comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
`has_order` tinyint(1) default 1,
`instrument` varchar(15) DEFAULT NULL,
`group_id` int(5) DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `report_id_drug`(`report_id`,`drug`),
KEY `has_order` (`has_order`),
KEY `pos-neg` (`pos-neg`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  $rs = dbDelta($sql);
  if (isset($_GET['ci_setup'])) {
    echo '<br>ci_results_pcr:<br';
    var_dump($rs);
  }




  $sql = "CREATE TABLE `ci_pcr_qc` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `PatientID` varchar(15),
    `assen` varchar(15),
    `SampleType` varchar(1),
    `Location` varchar(5),
    `Priority` varchar(3),
    `Instrument` varchar(30),
    `DateTime` date,
    `created` datetime,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `assen_date` (`assen`,`DateTime`)
    ) ENGINE=InnoDB";
  dbDelta($sql);


  $sql = "CREATE TABLE `ci_pcr_qc_tests` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`QC_ID` int(11),
`TestName` varchar(100),
`TestResult` varchar(20),
`TestResult_number` varchar(20),
`Units` varchar(6),
`comments` text,
PRIMARY KEY (`ID`),
UNIQUE KEY `ID_Test2` (`QC_ID`,`TestName`)
) ENGINE=InnoDB";
  dbDelta($sql);
}

if (in_array('gene', $btypes)) {


  $sql = "CREATE TABLE `ci_reports_gene` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11),
  `patient_id` varchar(30) DEFAULT NULL,
  `estatus` varchar(1),
  `author_id` int(11),
  `created_date` date DEFAULT NULL,
  `acquisition_date` date DEFAULT '0000-00-00',
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(15) DEFAULT NULL,
  `show_comment1` tinyint(1) NOT NULL,
  `show_comment2` tinyint(1) NOT NULL,
  `box_uploaded` tinyint(1) DEFAULT '0',
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `final_results` tinyint(1) default 0,
  `verified` tinyint(1) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `rerun_count` tinyint(1) default 0,
  `rerun_modified` int(11) default 0,
  `is_pending` tinyint(1) default 1,
  `approved_by_ID` int(11) default 0,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results_gene` (
  `id` bigint(16) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `drug` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `conc` varchar(15),
  `pos-neg` varchar(3),
  `comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `has_order` tinyint(1) default 1,
  `instrument` varchar(15) DEFAULT NULL,
  `group_id` int(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id_drug`(`report_id`,`drug`),
  KEY `has_order` (`has_order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);
}



if (in_array('req', $btypes)) {


  $sql = "CREATE TABLE `ci_reports_req` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11),
  `patient_id` varchar(30) DEFAULT NULL,
  `estatus` varchar(1),
  `author_id` int(11),
  `created_date` date DEFAULT NULL,
  `acquisition_date` date DEFAULT '0000-00-00',
  `lab_id` int(11) DEFAULT NULL,
  `upload_files` text,
  `import_type` varchar(3) DEFAULT '',
  `instrument` varchar(15) DEFAULT NULL,
  `show_comment1` tinyint(1) NOT NULL,
  `show_comment2` tinyint(1) NOT NULL,
  `box_uploaded` tinyint(1) DEFAULT '0',
  `faxed` tinyint(1) DEFAULT '0',
  `re_import_ID` int(11) DEFAULT NULL,
  `approve_date` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` int(11) DEFAULT NULL,
  `sent_wsdl` tinyint(1) DEFAULT '0',
  `sent_wsdl_results_data` tinyint(1) DEFAULT '0',
  `users_printed` text,
  `opi_id` text,
  `sync_ref` int(11),
  `old_barcode` varchar(25),
  `report_ref` int(11) default 0,
  `final_results` tinyint(1) default 0,
  `verified` tinyint(1) default 0,
  `has_pos` tinyint(1) default null,
  `has_pos_modified` int(11) DEFAULT NULL,
  `covid_pos` tinyint(1) default null,
  `rerun_count` tinyint(1) default 0,
  `rerun_modified` int(11) default 0,
  `is_pending` tinyint(1) default 1,
  `approved_by_ID` int(11) default 0,
  `other_data` text,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_date` (`created_date`),
  KEY `approve_date` (`approve_date`),
  KEY `upload_files` (`upload_files`(3)),
  KEY `has_pos` (`has_pos`),
  UNIQUE KEY `patient_batch` (`batch_id`,`patient_id`,`lab_id`)
) ENGINE=InnoDB";

  dbDelta($sql);



  $sql = "CREATE TABLE `ci_results_req` (
  `id` bigint(16) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `drug` varchar(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `conc` varchar(15),
  `conc_number` varchar(15),
  `pos-neg` varchar(3),
  `comments` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `has_order` tinyint(1) default 1,
  `instrument` varchar(15) DEFAULT NULL,
  `group_id` int(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id_drug`(`report_id`,`drug`),
  KEY `has_order` (`has_order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
  dbDelta($sql);

  $sql = "CREATE TABLE `ci_req_qc` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `lab_id` int(11) default 0,
    `assen` varchar(15),
    `instrument` int(3),
    `DateTime` datetime,
    `created` datetime,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `assen_date` (`assen`,`DateTime`)
    ) ENGINE=InnoDB";
  dbDelta($sql);


  $sql = "CREATE TABLE `ci_req_qc_tests` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`QC_ID` int(11),
`TestName` varchar(100),
`TestResult` varchar(20),
`TestResult_number` varchar(20),
`Units` varchar(6),
`comments` text,
PRIMARY KEY (`ID`),
UNIQUE KEY `ID_Test2` (`QC_ID`,`TestName`)
) ENGINE=InnoDB";
  dbDelta($sql);

  $sql = "CREATE TABLE `ci_req_qx200` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`plate_ID` int(11),
`desc1` varchar(13),
`desc2` varchar(8),
`well` varchar(6),
`target` int(1),
`pctype` varchar(2) default 'PC',
`conc` varchar(15),
`accepted_droplets` varchar(15),
`positives` varchar(15),
`negatives` varchar(15),
`pass_qc2` int(1),
`pass_qc2_hand` int(1) default 0,
`pass_qc3` int(1) default 0,
`pass_qc3_hand` int(1) default 0,
`avg` varchar(10),
`stdev` varchar(5),
`comments_qc2` text,
`comments_qc3` text,
PRIMARY KEY (`ID`),
UNIQUE KEY `plate_IDs` (`plate_ID`,`desc1`,`desc2`,`target`,`well`)
) ENGINE=InnoDB";
  dbDelta($sql);

  $sql = "CREATE TABLE `ci_req_qc_historical` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`plate_ID` int(11),
`desc1` varchar(13),
`desc2` varchar(8),
`well` varchar(6),
`target` int(1),
`conc` varchar(15),
`accepted_droplets` varchar(15),
`positives` varchar(15),
`negatives` varchar(15),
`avg` varchar(10),
`stdev` varchar(5),
`status` int(1),
PRIMARY KEY (`ID`),
UNIQUE KEY `plate_IDs` (`plate_ID`,`desc1`,`desc2`,`target`,`well`)
) ENGINE=InnoDB";
  dbDelta($sql);

  $wpdb->query("alter table ci_req_qx200 drop key plate_IDd");

  $wpdb->query("alter table ci_test_panels add column te_optimal_value text");
  $wpdb->query("alter table ci_test_panels add column te_ref_max text");
}

$sql = "CREATE TABLE `ci_result_test_speciments` (
  `mid` bigint(14) DEFAULT NULL,
  `testid` int(3),
  `conc` varchar(10),
  `outcome` varchar(4),
  `comments` text,
  `checked` tinyint(1) default 0,
  `api_type` int(3) default 0,
  UNIQUE KEY `mid_drug`(`mid`,`testid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
dbDelta($sql);

$sql = "CREATE TABLE `ci_result_test_speciments_saved` (
  `mid` bigint(14) DEFAULT NULL,
  `testid` int(3),
  `conc` varchar(10),
  `outcome` varchar(4),
  `comments` text,
  `checked` tinyint(1) default 0,
  `api_type` int(3) default 0,
  UNIQUE KEY `mid_drug`(`mid`,`testid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
dbDelta($sql);





$sql = "CREATE TABLE `ci_box_upload` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `mid` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `status` tinyint(1) DEFAULT '0',
  `uptime` int(11) NOT NULL,
  `account_id` varchar(200) NOT NULL,
  `file_id` varchar(100) NOT NULL,
  `etag` varchar(100) NOT NULL,
  `sender` varchar(200) NOT NULL,
  `lab_ID` INT (11),
  `batch_id` varchar(12),
  `log` text,
  `btype` varchar(10),
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`id`),
  UNIQUE KEY `path_mid` (`path`,`mid`,`type`,`account_id`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_box_folder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `folder_id` varchar(15) NOT NULL,
  `parent_id` varchar(15) NOT NULL,
  `account_id` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`,`account_id`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_test_panels` (
  `te_ID` int(11) NOT NULL AUTO_INCREMENT,
  `te_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `te_abrev` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `te_abrev2` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `te_loinc_code` text,
  `te_label_barcode` varchar(10),
  `te_basic` int(1) not null,
  `te_standard` int(1) not null,
  `te_comprehensive` int(1) not null,
  `te_highrisk` int(1) not null,
  `te_group_id` int(6) not null,
  `te_type` varchar(8) not null default 'tox',
  `te_units` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `te_range` text,
  `te_reactive_result` tinyint(1) default 0,
  `te_cutoff` varchar(10) not null,
  `te_upper_limit` varchar(10) not null,
  `te_critical_low` varchar(10) not null,
  `te_critical_high` varchar(10) not null,
  `te_in_req` int(1) default 0,
  `te_comparison_symbol` text,
  `te_desc` text,
  `te_comments` text,
  `te_comments_neg` text,
  `te_comments_ind` text,
  `te_pdf_note_pos` text,
  `te_pdf_note_neg` text,
  `te_pdf_file_pos` text,
  `te_pdf_file_neg` text,
  `te_reportable_note` text,
  `te_start` date,
  `te_end` date,
  `te_calculation` text,
  `te_relations` text,
  `te_is_detected` tinyint(1) default 0,
  `te_is_hlm_detected` tinyint(1) default 0,
  `te_antibiotic` tinyint(1) default 0,
  `te_nocheckbox` tinyint(1) default 0,
  `te_hide_pdf` tinyint(1) default 0,
  `te_inconclusive_ID` int(10) default 0,
  `te_control_by_ID` int(10) default 0,
  `te_position` int(10) default 0,
  `te_mf_ranges` tinyint(1) default 0,
  `te_has_oral` tinyint(1) default 0,
  `te_conc_decimal` tinyint(1) default -1,
  `te_reflex_tests` text,
  `te_reflex_clinics` text,
  `te_type_id` int(5) default 0,
  `te_is_control` tinyint(1) default 0,
  `te_font_style` tinyint(1) default 0,
  `te_result_values` text,
  `te_well_numbers` text,
  `te_ref_lab_ID` int(5) default 0,
  `te_not_ref_lab_ID` int(5) default 0,
  `te_snomed_code` text,
  `te_color` text,
  `te_pdf_show_ct` tinyint(1) default 0,
  `te_groups_pathogen` text,
  PRIMARY KEY (`te_ID`),
  UNIQUE KEY `te_name_group_type` (`te_name`,`te_group_id`,`te_type`),
  KEY te_start (`te_start`),
  KEY te_end (`te_end`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_test_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` int(11) not null,
  `meta_key` varchar(255) NOT NULL,
  `meta_value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_key` (`test_id`,`meta_key`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_test_antibiotics` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ta_name` varchar(150),
  `ta_abrev` varchar(100),
  `ta_group` varchar(10),
  `ta_groups` varchar(50),
  `ta_start_date` date,
  `ta_end_date` date,
  `iorder` int(11),
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ta_name_groups` (`ta_name`,`ta_groups`)
) ENGINE=InnoDB";
dbDelta($sql);

$wpdb->query("alter table ci_test_antibiotics DROP INDEX ta_name");

$sql = "CREATE TABLE `ci_results_antibiotics` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ta_ID` int(11) not null,
  `te_ID` int(11) not null,
  `report_id` int(11) not null,
  `btype` varchar(5) default 'pcr',
  `result` varchar(20) default '',
  `mic_value` varchar(20) default '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_results_labresults` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `te_ID` int(11) not null,
  `report_id` int(11) not null,
  `result` varchar(20) default '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_test_specimens` (
  `ts_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ts_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `ts_code` varchar(30) default '',
  `ts_position` int(10) default 0,
  `ts_controlreruns` text,
  `ts_group_id` int(4) not null,
  `ts_type` varchar(8) not null default 'tox',
  PRIMARY KEY (`ts_ID`),
  UNIQUE KEY `name_group_type` (`ts_name`,`ts_group_id`,`ts_type`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_test_panels_cutoffs` (
  `cu_te_ID` int(11) NOT NULL,
  `cu_cutoff` varchar(20) not null,
  `cu_medium` varchar(20) not null,
  `cu_upper_limit` varchar(20),
  `cu_critical_low` varchar(20),
  `cu_critical_high` varchar(20),
  `cu_invalid` varchar(20) default '',
  `cu_lqc` varchar(20) default '',
  `cu_hqc` varchar(20) default '',
  `cu_ll` varchar(20) default '',
  `cu_ul` varchar(20) default '',
  `cu_amp_score` varchar(20) default '',
  `cu_cq` varchar(20) default '',
  `cu_ms` text not null default '',
  `cu_status` int(1) default 1,
  `cu_gender` varchar(1) default '',
  `cu_age` varchar(20) default '',
  `cu_type_of_specimen` varchar(12),
  `cu_btype` varchar(10) default 'tox',
  `cu_critical_low_concen` text,
  `cu_cutoff_concen` text,
  `cu_medium_concen` text,
  `cu_upper_limit_concen` text,
  `cu_critical_high_concen` text,
  `cu_start` date,
  `cu_end` date,
  `created` datetime,
  `created_by` int(11),
  KEY `cu_te_ID` (`cu_te_ID`),
  KEY `cu_btype` (`cu_btype`),
  KEY `cu_start_end` (`cu_start`,`cu_end`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

if (isset($_GET['ci_setup'])) {
  echo "<br><br>";
  var_dump($rs);
  echo "<br><br>";
}


$sql = "CREATE TABLE `ci_instruments` (
  `instrument` varchar(20) not null,
  `btype` varchar(10) default 'tox',
  UNIQUE KEY `instrument_btype` (`instrument`,`btype`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_test_groups` (
  `tg_ID` int(11) NOT NULL AUTO_INCREMENT,
  `tg_name` varchar(255) NOT NULL,
  `tg_short_name` varchar(50) NOT NULL,
  `tg_label_name` varchar(5) NOT NULL,
  `tg_show_req` int(1) default 1,
  `tg_position` int(11) default 0,
  `tg_show_one` int(1) default 0,
  `tg_bgcolor` text, 
  `tg_popup_approve` text,
  `tg_popup_verified` text,
  `tg_type` varchar(8) not null default 'tox',
  `tg_comments` text  CHARACTER SET utf8 COLLATE utf8_unicode_ci ,
  `tg_comments_pos` text,
  `tg_comments_neg` text,
  `tg_aw_results` tinyint(1) default 0,
  `tg_only_group` tinyint(1) default 0,
  `tg_manual_edit` tinyint(1) default 0,
  `tg_hide_pending` tinyint(1) default 0,
  `tg_notestcheckbox` tinyint(1) default 0,
  `tg_is_covid` tinyint(1) default 0,
  `tg_blood_style` tinyint(1) default 0,
  `tg_parent` int(11) default 0,
  `tg_relate_to` varchar(50) default '',
  `tg_antibiotic` tinyint(1) default 0,
  `tg_auto_batchid` tinyint(1) default 0,
  `tg_auto_approve_neg` tinyint(1) default 0,
  `tg_auto_approve_pos_neg` tinyint(1) default 0,
  `tg_auto_position` tinyint(1) default 0,
  `tg_manual_antibiotic` tinyint(1) default 0,
  `tg_hide_pdf_ct` tinyint(1) default 0,
  `tg_show_controls_pe` tinyint(1) default 0,
  `tg_remove_controls` tinyint(1) default 0,
  `tg_pos_2_top` tinyint(1) default 0,
  `tg_hide_phone_address` tinyint(1) default 0,
  `tg_comment_next_row` tinyint(1) default 0,
  `tg_hide_pdf_units` tinyint(1) default 0,
  `tg_logo` text,
  `tg_pdf_title` text,
  `tg_show_cli_IDs` text,
  `tg_notshow_cli_IDs` text,
  `tg_date_start` date,
  `tg_date_end` date,
  `tg_limit_days` int(3),
  `tg_plate_style` int(3),
  `tg_plate_style2` int(3),
  `tg_group_code` varchar(20),
  `tg_other_options` text,
  `tg_reflex_group_ID` varchar(50) default '',
  `tg_reflex_hide4nopos` tinyint(1) default 0,
  `tg_lab_ID` varchar(50) default '',
  `tg_hide_lab_ID` varchar(50) default '',
  `tg_ref_lab_ID` int(5) default 0,
  `tg_show_for_ref_lab_ID` varchar(50) default '',
  `tg_notshow_for_ref_lab_ID` varchar(50) default '',
  `tg_cpt_code` text,
  `tg_panel_type` varchar(10) not null default '',
  PRIMARY KEY (`tg_ID`),
  UNIQUE KEY `tg_name2` (`tg_name`,`tg_type`,`tg_parent`)
) ENGINE=InnoDB";
dbDelta($sql);

$wpdb->query("ALTER TABLE `ci_test_groups`
  MODIFY `tg_lab_ID` varchar(50) DEFAULT '',
  MODIFY `tg_hide_lab_ID` varchar(50) DEFAULT '',
  MODIFY `tg_show_for_ref_lab_ID` varchar(50) DEFAULT '',
  MODIFY `tg_notshow_for_ref_lab_ID` varchar(50) DEFAULT ''");


$sql = "CREATE TABLE `ci_medications` (
  `me_ID` int(11) NOT NULL AUTO_INCREMENT,
  `me_name` varchar(100) NOT NULL,
  `me_code` varchar(100) NOT NULL,
  `me_show_req` int(1) not null,
  `me_btype` varchar(5) default 'tox',
  `me_group_id` int(6),
  PRIMARY KEY (`me_ID`),
  UNIQUE KEY `me_name_type` (`me_name`,`me_btype`)
) ENGINE=InnoDB";
dbDelta($sql);


$wpdb->query('alter table ci_medications drop KEY me_name');


$sql = "CREATE TABLE `ci_medication_groups` (
  `mg_ID` int(11) NOT NULL AUTO_INCREMENT,
  `mg_name` varchar(100) NOT NULL,
  `mg_btype` varchar(5) default 'tox',
  PRIMARY KEY (`mg_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_distributors` (
  `dis_ID` int(11) NOT NULL AUTO_INCREMENT,
  `dis_name` varchar(255) NOT NULL,
  `dis_user_ID` int(11) not null,
  `dis_sale_groups` varchar(500) not null,
  `dis_reps` varchar(500) not null,
  `dis_address` varchar(150) NOT NULL,
  `dis_city` varchar(100) NOT NULL,
  `dis_state` varchar(50) NOT NULL,
  `dis_zip` varchar(12) NOT NULL,
  `dis_phone` varchar(18) NOT NULL,
  `dis_fax` varchar(18) NOT NULL,
  `dis_cell` varchar(18) NOT NULL,
  `dis_website` varchar(255) NOT NULL,
  `dis_company_type` varchar(10) not null,
  `dis_state_inc` varchar(10) not null,
  `dis_state_inc_ein` varchar(150) not null,
  `dis_contact_name` varchar(100) not null,
  `dis_contact_phone` varchar(18) not null,
  `dis_contact_fax` varchar(18) not null,
  `dis_email` varchar(250) NOT NULL,
  `dis_comission` varchar(50) NOT NULL,
  `dis_sdate` varchar(15) NOT NULL,
  `dis_edate` varchar(15) NOT NULL,
  `dis_tox` int(1) default 0,
  `dis_blood` int(1) default 0,
  `dis_dna` int(1) default 0,
  `dis_allergy` int(1) default 0,
  `dis_rpp_gi` int(1) default 0,
  `dis_pcr` int(1) default 0,
  `dis_study` int(1) default 0,
  `dis_princial` text not null,
  `dis_payment_type` varchar(10) not null,
  `dis_company_type_other` varchar(25) not null,
  `dis_payment_bank_name` varchar(200) not null,
  `dis_payment_aba` varchar(200) not null,
  `dis_payment_account_number` varchar(200) not null,
  `dis_payment_account_name` varchar(200) not null,
  `dis_activer` int(1) DEFAULT '0',
  PRIMARY KEY (`dis_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_clinician_groups` (
  `clig_ID` int(11) NOT NULL AUTO_INCREMENT,
  `clig_name` varchar(255) NOT NULL,
  `clig_members` varchar(500) not null,
  `clig_site_id` int(11) not null,
  `clig_address` varchar(150) NOT NULL,
  `clig_city` varchar(100) NOT NULL,
  `clig_state` varchar(50) NOT NULL,
  `clig_zip` varchar(12) NOT NULL,
  `clig_phone` varchar(18) NOT NULL,
  `clig_fax` varchar(18) NOT NULL,
  `clig_cell` varchar(18) NOT NULL,
  `clig_website` varchar(255) NOT NULL,
  `clig_company_type` varchar(10) not null,
  `clig_state_inc` varchar(10) not null,
  `clig_state_inc_ein` varchar(150) not null,
  `clig_contact_name` varchar(100) not null,
  `clig_contact_phone` varchar(18) not null,
  `clig_contact_fax` varchar(18) not null,
  `clig_email` varchar(250) NOT NULL,
  `clig_comission` varchar(50) NOT NULL,
  `clig_sdate` varchar(15) NOT NULL,
  `clig_edate` varchar(15) NOT NULL,
  `clig_tox` int(1) default 0,
  `clig_blood` int(1) default 0,
  `clig_dna` int(1) default 0,
  `clig_princial` text not null,
  `clig_payment_type` varchar(10) not null,
  `clig_company_type_other` varchar(250) not null,
  `clig_payment_bank_name` varchar(200) not null,
  `clig_payment_aba` varchar(200) not null,
  `clig_payment_account_number` varchar(200) not null,
  `clig_payment_account_name` varchar(200) not null,
  `clig_admins` int(11) not null,
  `clig_inactive` int(1) DEFAULT '0',
  `clig_rep_ID` int(11) not null,
  PRIMARY KEY (`clig_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_results_start` (
  `rs_ID` int(11) NOT NULL AUTO_INCREMENT,
  `rs_test` varchar(100) NOT NULL,
  `rs_instrument` varchar(100) not null,
  `rs_lot` varchar(50) not null,
  `rs_high` varchar(20) not null,
  `rs_mid` varchar(20) not null,
  `rs_low` varchar(20) not null,
  `rs_expiration_date` varchar(20) not null,
  PRIMARY KEY (`rs_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_batches_uploaded` (
  `bu_ID` int(11) NOT NULL AUTO_INCREMENT,
  `bu_batch_id` varchar(12) NOT NULL,
  `bu_lab_id` int(11) not null,
  `bu_lab_site_id` int(11) not null,
  PRIMARY KEY (`bu_ID`)
) ENGINE=InnoDB";
dbDelta($sql);



$sql = "CREATE TABLE `ci_batches_status` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `s_key` varchar(20) NOT NULL,
  `s_text` text,
  `s_type` varchar(2),
  `btype` varchar(5) default 'tox',
  `iorder` int(11) default 0,
  `istatus` tinyint(1) default 1,
  `show_in_req_form` tinyint(1) default 1,
  `show_in_inbound` tinyint(1) default 1,
  `show_in_result_form` tinyint(1) default 1,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `s_key_btype` (`s_key`,`btype`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_reports_files` (
  `rf_ID` int(11) NOT NULL AUTO_INCREMENT,
  `rf_batch_id` varchar(12) NOT NULL,
  `rf_lab_site_id` int(11) default 0,
  `rf_file` varchar(300),
  `rf_count` int(11),
  `rf_btype` varchar(7) default 'tox',
  `rf_covid_type` varchar(7) default 'IC',
  `rf_assen_list` text,
  `rf_meta_data` LONGTEXT,
  `rf_upload_date` date,
  `rf_created` datetime,
  `rf_number_of_plates` tinyint(1) default null,
  `rf_imported` tinyint(1) default 0,
  PRIMARY KEY (`rf_ID`),
  KEY rf_assen_list (rf_assen_list (255)),
  KEY rf_upload_date (`rf_upload_date`),
  KEY rf_covid_type (`rf_covid_type`),
  KEY rf_btype (`rf_btype`)
) ENGINE=InnoDB";
dbDelta($sql);



$sql = "CREATE TABLE `ci_tecan` (
  `te_ID` int(10) NOT NULL AUTO_INCREMENT,
  `te_position` int(11),
  `te_assen` varchar(20) not null,
  `te_info` text,
  `te_batch_id` varchar(20) not null,
  `te_estatus` varchar(1) default '',
  `te_time` varchar(10) not null,
  `te_letter` varchar(1),
  `te_number` int(5),
  `te_filename` varchar(100) not null,
  `te_created` datetime not null,
  `te_uploads` text,
  `te_alcohol` int(1) default 0,
  PRIMARY KEY (`te_ID`),
  KEY te_created(te_created),
  UNIQUE KEY `te_assen` (`te_assen`)
) ENGINE=InnoDB";

dbDelta($sql);


$sql = "CREATE TABLE `ci_batches_notes` (
  `bn_ID` int(10) NOT NULL AUTO_INCREMENT,
  `bn_bid` varchar (20),
  `bn_core_bid` int(11),
  `bn_number` varchar (50),
  `bn_number_auto` int(10), 
  `bn_notes` text,
  `bn_created` datetime not null,
  `bn_user` int(10),
  `bn_status_changed` datetime not null,
  PRIMARY KEY (`bn_ID`),
  UNIQUE KEY `bn_bid` (`bn_bid`)
) ENGINE=InnoDB";

dbDelta($sql);


$sql = "CREATE TABLE `ci_customer_orders` (
  `co_ID` int(11) NOT NULL AUTO_INCREMENT,
  `co_txn_id` varchar(64),
  `ship_date` date,
  `ups` varchar(30),
  `order_notes` text,
  `ship_notes` text,
  `created` datetime,
  `author_ID` int(11),
  `doc_ID` int(11),
  `cli_ID` int(11),
  `ship_weight` varchar(11) default 0,
  `ship_length` varchar(11),
  `ship_width` varchar(11),
  `ship_height` varchar(11),
  `ship_type` varchar(25),
  `ship_cost` varchar(25),
  `rates` text,
  `ups_cod` varchar(25),
  `ups_selected_service` varchar(50),
  `shipments_details` text,
  `label_details` text,
  UNIQUE KEY `co_txn_id` (`co_txn_id`),
  KEY  `doc_ID` (`doc_ID`,`cli_ID`),
  PRIMARY KEY `co_ID` (`co_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);
if (isset($_GET['ci_setup'])) {
  echo 'ci_customer_orders<br>';
  var_dump($rs);
  echo '<br><br>';
}



$sql = "CREATE TABLE `ci_igniting_qss` (
  `ig_ID` int(10) NOT NULL AUTO_INCREMENT,
  `cli_ID` int(11),
  `doc_ID` int(11),
  `patient_name` varchar(150),
  `doc` datetime not null,
  `file_name` varchar(250),
  `file_path` varchar(250),
  `request_pdf` varchar(250),
  `box_uploaded` int(1) default 0,
  `is_dna` INT (1) DEFAULT 0,
  `created` datetime,
  `old_file_name` varchar(250),
  `user_id` int(11),
  `assen` varchar(25),
  `subfolder` varchar(50),
  PRIMARY KEY (`ig_ID`)
) ENGINE=InnoDB";

dbDelta($sql);


$wpdb->query('DROP TABLE IF EXISTS ci_tox_hos');
$wpdb->query('DROP TABLE IF EXISTS ci_blood_hos');
$wpdb->query('DROP TABLE IF EXISTS ci_allergy_hos');
$wpdb->query('DROP TABLE IF EXISTS ci_dna_hos');
/*
$sql = "CREATE TABLE `ci_tox_hos` (
  `th_ID` int(10) NOT NULL AUTO_INCREMENT,
  `clinic` varchar(250),
  `doctor` varchar(250),
  `patient_name` varchar(150),
  `doc` datetime not null,
  `subfolder` varchar(50),
  `file_name` varchar(250),
  `created` datetime,
  `user_id` int(11),
  `assen` varchar(25),
  `dl_ID` int(11) default 0,
  PRIMARY KEY (`th_ID`)
) ENGINE=InnoDB";

dbDelta($sql);


$sql = "CREATE TABLE `ci_blood_hos` (
  `th_ID` int(10) NOT NULL AUTO_INCREMENT,
  `clinic` varchar(250),
  `doctor` varchar(250),
  `patient_name` varchar(150),
  `doc` datetime not null,
  `subfolder` varchar(50),
  `file_name` varchar(250),
  `created` datetime,
  `user_id` int(11),
  `assen` varchar(25),
  `dl_ID` int(11) default 0,
  PRIMARY KEY (`th_ID`)
) ENGINE=InnoDB";

dbDelta($sql);


$sql = "CREATE TABLE `ci_allergy_hos` (
  `th_ID` int(10) NOT NULL AUTO_INCREMENT,
  `clinic` varchar(250),
  `doctor` varchar(250),
  `patient_name` varchar(150),
  `doc` datetime not null,
  `subfolder` varchar(50),
  `file_name` varchar(250),
  `created` datetime,
  `user_id` int(11),
  `assen` varchar(25),
  `dl_ID` int(11) default 0,
  PRIMARY KEY (`th_ID`)
) ENGINE=InnoDB";

dbDelta($sql);


$sql = "CREATE TABLE `ci_dna_hos` (
  `th_ID` int(10) NOT NULL AUTO_INCREMENT,
  `clinic` varchar(250),
  `doctor` varchar(250),
  `patient_name` varchar(150),
  `doc` datetime not null,
  `subfolder` varchar(50),
  `file_name` varchar(250),
  `created` datetime,
  `user_id` int(11),
  `assen` varchar(25),
  `dl_ID` int(11) default 0,
  PRIMARY KEY (`th_ID`)
) ENGINE=InnoDB";

dbDelta($sql);
*/



$sql = "CREATE TABLE `ci_wsdl_keys` (
  `wsdl_ID` int(11) NOT NULL AUTO_INCREMENT,
  `wsdl_url` varchar(300) NOT NULL default '',
  `wsdl_key` varchar(200) NOT NULL,
  `wsdl_user_group_ID` int(11) default 0,
  `wsdl_lab_ID` int(11) default 0,
  `wsdl_ref_lab_ID` int(11) default 0,
  `wsdl_type` varchar(30) default '',
  `wsdl_api_type` int(2) default 0,
  `wsdl_desc` text,
  `wsdl_current_ID` bigint(15) default 0,
  `wsdl_active` TINYINT(1) default 0,
  `wsdl_fax_result` TINYINT(1) default 0,
  `wsdl_sync_clinic` TINYINT(1) default 0,
  `wsdl_sync_confirmation` TINYINT(1) default 0,
  `wsdl_clinicname` text,
  `wsdl_doctorname` text,
  `wsdl_start` date,
  `wsdl_group_IDs` text,
  `wsdl_send_instrument` text,
  `wsdl_df_cli_ID` int(11) default 0,
  `wsdl_df_doc_ID` int(11) default 0,
  `wsdl_dont_approve` TINYINT(1) default 0,
  `wsdl_fix_ref_test` TINYINT(1) default 0,
  `wsdl_dont_send_bid` TINYINT(1) default 0,
  `wsdl_dont_reflex` TINYINT(1) default 0,
  `wsdl_dont_send_screening` TINYINT(1) default 0,
  PRIMARY KEY (`wsdl_ID`)
) ENGINE=InnoDB";
dbDelta($sql);

$wpdb->query('alter table ci_wsdl_keys drop column wsdl_btypes');
$wpdb->query('alter table ci_wsdl_keys drop column wsdl_isjson');



$sql = "CREATE TABLE `ci_wsdl_log` (
  `wl_ID` int(11) NOT NULL ,
  `wl_type` varchar(25) NOT NULL,
  `wl_key` varchar(60) NOT NULL,
  `wl_status` varchar(20),
  `wl_at` datetime,
  `wl_count` int(2) default 0,
  `wl_data` text,
  `wl_datain` text,
  `wl_dataout` text,
  `wl_ip` varchar(25),
  KEY `wl_ID` (`wl_ID`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_wsdl_batch` (
  `b_mid` bigint(16) NOT NULL,
  `btype` varchar(5) default 'tox',
  `b_wsdl_ID` int(11) not null,
  `b_remote_ID` int(11) not null,
  `b_status` tinyint(1) default 0,
  `b_exported` tinyint(1) default 0,
  `bdate` datetime,
  UNIQUE KEY `b_mid` (`b_mid`,`btype`,`b_wsdl_ID`)
) ENGINE=InnoDB";
dbDelta($sql);



$sql = "CREATE TABLE `ci_wsdl_key_clinics` (
  `wsdl_ID` int(11) NOT NULL,
  `cli_ID` int(11) not null,
  UNIQUE KEY `wsdl_ID` (`wsdl_ID`,`cli_ID`)
) ENGINE=InnoDB";
dbDelta($sql);



$sql = "CREATE TABLE `ci_opisafe_keys` (
  `opi_ID` int(11) NOT NULL AUTO_INCREMENT,
  `opi_key` varchar(100) NOT NULL,
  `opi_secret` varchar(100) NOT NULL,
  `opi_labkey` varchar(100) NOT NULL,
  `opi_user_group_ID` int(11) default 0,
  `opi_lab_ID` int(11) default 0,
  `opi_desc` text,
  PRIMARY KEY (`opi_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_opisafe_key_relationships` (
  `opi_ID` int(11) NOT NULL,
  `target_ID` int(11) not null,
  `cType` enum('C','D') default 'C',
  UNIQUE KEY `opi_ID` (`opi_ID`,`target_ID`,`cType`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_user_groups` (
 `gr_ID` int(11) NOT NULL AUTO_INCREMENT,
 `gr_name`  varchar(150) not null,
 `gr_site_id` int(5) not null,
  PRIMARY KEY (`gr_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = dbDelta($sql);

/*
$sql = "CREATE TABLE `ci_hos_download` (
 `dl_ID` int(11) NOT NULL AUTO_INCREMENT,
 `dl_type`  varchar(20) not null,
 `dl_created` datetime,
 `dl_user_ids` text,
 `dl_count` int(11) default 0,
 `dl_from` int(11) default 0,
  PRIMARY KEY (`dl_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);
*/

$sql = "CREATE TABLE `ci_fix_duplicate` (
 `ID` int(11),
 `import_ID` int(11),
 `ctype` varchar(20),
 `created` datetime,
  UNIQUE KEY `del_ID` (`ID`,`import_ID`,`ctype`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);




$sql = "CREATE TABLE `ci_conditions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
               `ctype` varchar(10),
               `target_id` int(11),
               `bill_status` varchar(50),
               `dont_collect` int(1) default 0,
               `dont_receive` int(1) default 0,
               `is_active` int(1) default 1,
               `code` varchar(10),
               `created` datetime,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB";

$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_urine_validity` (
`u_ID` int(11) NOT NULL AUTO_INCREMENT,
`u_name` varchar(200),
`u_low` varchar(20),
`u_high` varchar(20),
`u_range` text,
`u_comments` text,
`u_status` tinyint(1) default 1,
`u_neg_pos` tinyint(1) default 0,
`u_showinreq` tinyint(1) default 0,
`u_hide_value` tinyint(1) default 0,
`u_default_blank` tinyint(1) default 0,
`u_not_auto_checked` tinyint(1) default 0,
`u_reflex` tinyint(1) default 0,
`u_reflex_tests` text,
`u_reflex_clinics` text,
`u_show_for_ref_lab_ID` int(5) default 0,
`u_notshow_for_ref_lab_ID` int(5) default 0,
`u_abreviation` varchar(50),
`u_abreviation_mc6410` varchar(30),
`u_abreviation_indiko` varchar(30),
`u_abreviation_au680` varchar(30),
`u_abreviation_immtox250` varchar(30),
`u_abreviation_architect` varchar(30),
`u_abreviation_indiko` varchar(30),
`u_loinc_code` varchar(30),
`u_position` int(10) default 0,
`u_start` date,
`u_end` date,
`u_reflex_pos_not_medication` tinyint(1) default 1,
`u_reflex_neg_and_medication` tinyint(1) default 0,
`u_related_medications` text,
PRIMARY KEY (`u_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

$rs = dbDelta($sql);
if (isset($_GET['ci_setup'])) {
  echo "<br>";
  echo 'ci_urine_validity';
  var_dump($rs);
  echo "<br>";
}


$sql = "CREATE TABLE `ci_collector_groups` (
  `cg_ID` int(9) NOT NULL AUTO_INCREMENT,
  `cg_name` varchar(100) NOT NULL,
  `cg_col_ID` int(9) not null,
  `cg_parent` int(9) not null,
  PRIMARY KEY (`cg_ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_user_group_members` (
 `gm_gr_ID` int(11) NOT NULL,
 `gm_user_ID` int(11) not null,
 `gm_type` enum('A','M'),
  UNIQUE KEY `member` (`gm_gr_ID`,`gm_user_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_dagnostic_codes` (
  `ID` int(9) NOT NULL AUTO_INCREMENT,
 `key` varchar(20),
 `desc` text,
 `type` varchar(8),
 `panelIDs` text,
 `iorder` int(11) default 0,
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `key_type` (`key`,`type`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);




$sql = "CREATE TABLE `ci_exported_ids` (
              `id` bigint(15) NOT NULL,
              `type` varchar(15),
              `client_id` varchar(10)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_consumable_inv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `co_name` varchar(150),
  `co_size` int(11),
  `co_order_date` date,
  `co_vendor_id` int(11),
  `co_duration` int(11),
  `co_qty` int(11),
  `co_reorder_at` int(11),
  `co_reorder_qty` int(11),
  `co_email` varchar(200),
  `co_sent_notify` tinyint(1) default 0,
  `co_notes` text,
  `co_btype` varchar(10),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ve_name` varchar(150),
  `ve_salesperson` varchar(150),
  `ve_site` varchar(150),
  `ve_email` varchar(150),
  `ve_phone` varchar(30),
  `ve_notes` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_theoreticals` (
`ID` int(9) NOT NULL AUTO_INCREMENT,
`qc_type` varchar(10),
`qc_drug` varchar(45),
`qc_value` varchar(10),
`qc_value2` varchar(10),
`qc_specimen` varchar(5),
PRIMARY KEY (`ID`),
UNIQUE KEY `key_type` (`qc_type`,`qc_drug`,`qc_specimen`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_theoretical_instruments` (
 `theo_ID` int(9),
 `instrument` varchar(80),
 `qc_value` varchar(10),
 `qc_value2` varchar(10),
 `start_date` date,
 `end_date` date,
  UNIQUE KEY `theo_ID` (`theo_ID`,`instrument`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_covid_questions` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pa_ID` int(11),
  `race` varchar(1),
  `ethnicity` varchar(3),
  `first_covid_test` varchar(1),
  `employed_in_heal` varchar(1),
  `cdc` varchar(1),
  `cdc_date` date,
  `hospitalized` varchar(1),
  `icu` varchar(1),
  `pregnant` varchar(1),
  `resident` varchar(1),
  `created` datetime,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_racks` (
 `re_ID` int(11) NOT NULL AUTO_INCREMENT,
 `re_today` date,
 `re_number` int(2) default 1,
 `re_rack_type` varchar(10) default '',
 `re_subtitle` varchar(50) not null,
 `re_barcode` varchar(20) not null,
 `re_author_id` int(11) not null,
 `re_priority` varchar(10) not null,
 `re_rtype` tinyint(1) default 0,
 `re_kind` tinyint(1) default 0,
 `re_panel_id` int(5) default 0,
 `re_panel_style` int(1) default 1,
 `re_is_uti_panel` int(3) default 0,
 `re_relate_id` int(11) default 0,
 `re_rack_subtype` varchar(3) default '',
 `re_created` datetime,
 `re_completed` datetime,
  PRIMARY KEY (`re_ID`),
  KEY `re_barcode` (`re_barcode`)
) ENGINE=InnoDB ";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_rack_batches` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `re_ID` int(11) NOT NULL,
 `mid` int(11) not null,
 `iparent` int(11) default 0,
 `is_racks4` tinyint(1) default 0,
 `btype` varchar(10) default '',
 `subtype` varchar(10) default '',
 `is_rerun` tinyint(1) default 0,
 `reason_rerun` text,
 `other_data` text,
 `created` datetime,
 `created_by` int(11),
  PRIMARY KEY (`ID`),
  UNIQUE KEY `re_batch3` (`re_ID`,`mid`,`btype`,`subtype`,`is_rerun`,`iparent`),
  KEY `re_batchtype` (`re_ID`,`mid`,`btype`),
  KEY `mid_btype` (`mid`,`btype`),
  KEY `subtype` (`subtype`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

//25/3/2024
//UNIQUE KEY `re_batch` (`re_ID`,`mid`,`btype`),
$wpdb->query('alter table ci_rack_batches drop key re_batch');
$wpdb->query('alter table ci_rack_batches drop key re_batch2');



$sql = "CREATE TABLE `ci_plates` (
 `pl_ID` int(11) NOT NULL AUTO_INCREMENT,
 `pl_today` date,
 `pl_rack_ID` int(11),
 `pl_combine_ID` int(11) default 0,
 `pl_number` int(2) not null,
 `pl_ntc_position` int(3) default 0,
 `pl_ntc2_position` int(3) default 96,
 `pl_ntc3_position` int(3) default 0,
 `pl_ntc4_position` int(3) default 0,
 `pl_blank1_position` int(3) default 0,
 `pl_blank2_position` int(3) default 0,
 `pl_ntc_combine_position` int(3) default 0,
 `pl_ntc2_combine_position` int(3) default 0,
 `pl_ntc3_combine_position` int(3) default 0,
 `pl_ntc4_combine_position` int(3) default 0,
 `pl_blank1_combine_position` int(3) default 0,
 `pl_blank2_combine_position` int(3) default 0,
 `pl_ntc_text` varchar(30),
 `pl_ntc2_text` varchar(30),
 `pl_ntc3_text` varchar(30),
 `pl_ntc4_text` varchar(30),
 `pl_blank1_text` varchar(30),
 `pl_blank2_text` varchar(30),
 `pl_lock` tinyint(1) default 0,
 `created` datetime,
 `created_by` int(11) not null,
 `other_data` text,
  PRIMARY KEY (`pl_ID`),
  KEY `pl_combine_ID` (`pl_combine_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_plates_combine` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `pl_today` date not null,
 `pl_number` int(2) not null,
 `pl_lock` tinyint(1) default 0,
 `pl_type` int(3) default 0,
 `combinesort` tinyint(1) default 1,
 `combine96` tinyint(1) default 0,
 `created` datetime,
 `created_by` int(11) not null,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_plates_batches` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `pl_ID` int(11) NOT NULL,
 `mid` int(11) not null,
 `position` int(3) default 0,
 `combine_position` int(3) default 0,
 `btype` varchar(10) default 'tox',
 `subtype` varchar(10) default '',
 `is_rerun` tinyint(1) default 0,
 `is_racks4` tinyint(1) default 0,
 `iparent` int(11) default 0,
 `created` datetime,
 `created_by` int(11),
 `rb_ID` int(11) default 0,
  UNIQUE KEY `pl_batch3` (`pl_ID`,`mid`,`btype`,`subtype`,`is_rerun`,`iparent`),
  KEY `btype`(`btype`),
  KEY `mid`(`mid`),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

//25/3/2024
$wpdb->query("alter table ci_plates_batches drop KEY pl_batch");
$wpdb->query("alter table ci_plates_batches drop KEY pl_batch2");



$sql = "CREATE TABLE `ci_batch_ids` (
  `batch_id` varchar(12) NOT NULL,
  `cli_ID` int(11) NOT NULL,
  `doc_ID` int(11) NOT NULL,
  KEY `batch_id`(`batch_id`,`cli_ID`,`doc_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_sync_api_files` (
  `m_ID` int(11) NOT NULL AUTO_INCREMENT,
  `m_file` varchar(100),
  `m_type` varchar(10) default 'meenta',
  `m_time` int(11) NOT NULL,
  PRIMARY KEY (`m_ID`),
  UNIQUE KEY `m_file` (`m_file`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_sync_api_orders` (
  `mo_ID` bigint(15) NOT NULL AUTO_INCREMENT,
  `mo_file_id` int(11),
  `mo_mid` bigint(15) NOT NULL,
  `mo_btype` varchar(8) default 'pcr',
  `mo_status` tinyint(1) default 0,
  `mo_sent` tinyint(1) default 0,
  `mo_test_ID` varchar(100),
  `mo_set_ID` varchar(30),
  `mo_control_ID` varchar(30),
  `mo_patient_ID` varchar(30),
  `mo_peg_ID` text,
  `mo_sent_dt` datetime,
  `other_info` MEDIUMTEXT,
  `mo_runcount` int(3) default 0,
  PRIMARY KEY (`mo_ID`),
  KEY `mo_test_ID` (`mo_test_ID`),
  UNIQUE KEY `file_mid4` (`mo_mid`,`mo_file_id`,`mo_btype`,`mo_status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);

$indexes = $wpdb->get_results("SHOW INDEX FROM ci_sync_api_orders");

foreach ($indexes as $idx) {
  if ($idx->Column_name === 'mo_peg_ID') {
    $wpdb->query("ALTER TABLE ci_sync_api_orders DROP INDEX {$idx->Key_name}");
  }
}

$wpdb->query("
    ALTER TABLE ci_sync_api_orders
    MODIFY COLUMN mo_peg_ID TEXT
");


$sql = "CREATE TABLE `ci_log_hubs` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `log_month` int(2),
  `log_year` int(4),
  `log_count` int(11) default 0,
  `log_state` varchar(2) not null,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `umys` (`log_month`,`log_year`,`log_state`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);



$sql_notify = "CREATE TABLE `ci_sent_notify_clinic` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `cli_ID` int(11),
 `btype` varchar(10) default 'tox',
 `sent_key` varchar(50),
 `created` datetime,
  PRIMARY KEY (`ID`),
  KEY `cli_ID_btype_sent_key`(`cli_ID`,`btype`,`sent_key`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_notify);


$sql = "CREATE TABLE `ci_antibiotics` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100),
  `Description` text,
  `QRCode` varchar(40),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_analytical_errors` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200),
  `assen` varchar(35),
  `edate` date,
  `desc` text,
  `created` datetime,
  `created_by` int(11),
  `atype` varchar(5) default '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$rs = dbDelta($sql);




$sql = "CREATE TABLE `ci_test_abxmix` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `drug` varchar(100) DEFAULT NULL,
  `AmpC` varchar(10) DEFAULT '',
  `KPC` varchar(10) DEFAULT '',
  `NDM` varchar(10) DEFAULT '',
  `OXA-48` varchar(10) DEFAULT '',
  `VIM IMP-7` varchar(10) DEFAULT '',
  `CTX-M GROUP 1` varchar(10) DEFAULT '',
  `CTX-M GROUP 2` varchar(10) DEFAULT '',
  `SHV` varchar(10) DEFAULT '',
  `TEM` varchar(10) DEFAULT '',
  `ermA` varchar(10) DEFAULT '',
  `ermB` varchar(10) DEFAULT '',
  `mefA` varchar(10) DEFAULT '',
  `femA` varchar(10) DEFAULT '',
  `mecA` varchar(10) DEFAULT '',
  `QnrA` varchar(10) DEFAULT '',
  `QnrB` varchar(10) DEFAULT '',
  `vanA` varchar(10) DEFAULT '',
  `vanB` varchar(10) DEFAULT '',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `drug` (`drug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = $wpdb->query($sql);


$sql = "CREATE TABLE `ci_abx` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Marker` varchar(100) DEFAULT NULL,
  `Description` varchar(450) NOT NULL,
  `Resistant` varchar(450) DEFAULT NULL,
  `Sensitivity` varchar(560) DEFAULT NULL,
  `HarmfulAntibiotics` varchar(40) DEFAULT NULL,
  `BadAntibiotics` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = $wpdb->query($sql);


$sql = "CREATE TABLE `ci_bacteria` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Base Antibiotics` varchar(450) NOT NULL,
  `ampC` varchar(450) NOT NULL,
  `KPC` varchar(450) NOT NULL,
  `Oxa-48` varchar(450) NOT NULL,
  `VIM` varchar(450) NOT NULL,
  `NDM` varchar(450) NOT NULL,
  `IMP-7` varchar(450) NOT NULL,
  `TEM` varchar(450) NOT NULL,
  `CTX-M Group 1` varchar(450) NOT NULL,
  `CTX-M Group 2` varchar(450) NOT NULL,
  `SHV` varchar(450) NOT NULL,
  `ErmA` varchar(450) NOT NULL,
  `ErmB` varchar(450) NOT NULL,
  `mefA` varchar(450) NOT NULL,
  `mecA` varchar(450) NOT NULL,
  `femA` varchar(450) NOT NULL,
  `vanA1` varchar(450) NOT NULL,
  `vanA2` varchar(450) NOT NULL,
  `vanB` varchar(450) NOT NULL,
  `QnrA` varchar(450) NOT NULL,
  `QnrB` varchar(450) NOT NULL,
  `Gram` tinyint(1) DEFAULT NULL,
  `MainAntibiotic` int(11) DEFAULT NULL,
  `SecondaryAntibiotics` int(11) DEFAULT NULL,
  `QRCode` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
$rs = $wpdb->query($sql);




$sql = "CREATE TABLE `ci_pdf_footer` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `btype` varchar(6) default 'tox',
  `lab_ID` int(11) default 0,
  `group_ID` int(11) default 0,
  `text` text,
  `header` text,
  `start_date` date,
  `end_date` date,
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_pdf_notes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `btype` varchar(6) default 'tox',
  `lab_ID` int(11) default 0,
  `group_ID` int(11) default 0,
  `parent_ID` int(11) default 0,
  `text` text,
  `pdf_file` text,
  `start_date` date,
  `end_date` date,
  `exclude_panels` text,
  `is_abx` tinyint(1) default 0,
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_lab_field_history` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `lab_ID` int(10) NOT NULL,
  `field_key` varchar(50) NOT NULL,
  `field_value` text,
  `from_date` date,
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`ID`),
  KEY `lab_ID` (`lab_ID`),
  KEY `field_key` (`field_key`),
  KEY `from_date` (`from_date`)
) ENGINE=InnoDB";
dbDelta($sql);



$sql = "CREATE TABLE `ci_phlebotomist_logs` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `service_date` date not null,
 `collector_ID` int(10) not null,
 `start_service_time` varchar(5),
 `end_service_time` varchar(5),
 `start_mileage` varchar(15),
 `end_mileage` varchar(15),
 `number_of_stops` int(10),
 `round_trip` varchar(20),
 `total_patients_draws` int(10),
 `category` varchar(2),
 `start_mileage_2` varchar(15),
 `end_mileage_2` varchar(15),
 `category_2` varchar(2),
 `total_patients_draws_2` int(10),
 `number_of_stops_2` int(10),
 `round_trip_2` varchar(20),
 `created` datetime,
 `created_by` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `service_date`(`service_date`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_lab_operation` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `lab_ID` int(10) NOT NULL,
 `lab_mon` tinyint(1) default 1,
 `lab_tue` tinyint(1) default 1,
 `lab_wed` tinyint(1) default 1,
 `lab_thu` tinyint(1) default 1,
 `lab_fri` tinyint(1) default 1,
 `lab_sat` tinyint(1) default 1,
 `lab_sun` tinyint(1) default 1,
 `target_tat` tinyint(3) default 36,
 `from_date` date,
 `to_date` date,
 `created` date,
  PRIMARY KEY (`ID`),
  KEY `lab_ID`(`lab_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



if (tr_get_option('ci_pdf_data', 'plate_coating')) {

  $sql = "CREATE TABLE `ci_plate_coating` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `service_date` date not null,
  `barcode` varchar(20),
  `letters` varchar(6),
  `type` varchar(3),
  `other_data` text,
  `created` datetime,
  `created_by` int(11) NOT NULL,
   PRIMARY KEY (`ID`),
   KEY `service_date`(`service_date`),
   KEY `barcode`(`barcode`)
 ) ENGINE=InnoDB";
  $rs = dbDelta($sql);
}
if (tr_get_option('ci_pdf_data', 'ins_iflow')) {

  $sql = "CREATE TABLE `ci_iflow_logs` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `message` text not null,
  `data` text,
  `type` varchar(20) default 'info',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (`ID`),
   KEY `type`(`type`),
   KEY `created_at`(`created_at`)
 ) ENGINE=InnoDB";
  $rs = dbDelta($sql);
}
$sql = "CREATE TABLE `ci_clinic_support` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT 0,
  `account_name` varchar(191) NOT NULL,
  `account_email` varchar(191) NOT NULL,
  `clinic_name` varchar(191) NOT NULL,
  `phone` varchar(50) DEFAULT '',
  `inquiry_type` varchar(100) NOT NULL,
  `priority_type` varchar(50) NOT NULL,
  `comment` text NOT NULL,
  `mail_sent` tinyint(1) DEFAULT 0,
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `supply_items` text,
  PRIMARY KEY (`ID`),
  KEY `account_email` (`account_email`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE IF NOT EXISTS ci_approve_logs (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) NOT NULL,
  `btype` varchar(10) NOT NULL,
  `final_status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `report_id` (`report_id`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$columns = array(
  'facility_name text NOT NULL',
  'facility_address text NOT NULL',
  'lab_facility text NOT NULL',
  'doc_ID int(11)',
  'cli_ID int(11) ',
  'ship_qty int(11) default 0',
  'lab_ID int(11)',
  'import_ID int(11) default 0',
  'viewed int(1) default 0 ',
  '`datetime` datetime'
);

foreach ($columns as $cl) {
  $wpdb->query("alter table wp_wp_eStore_customer_tbl add COLUMN {$cl}");
}

$wpdb->query("alter table wp_wp_eStore_customer_tbl add Key `doc_ID`(`doc_ID`)");
$wpdb->query("alter table wp_wp_eStore_customer_tbl add Key `cli_ID`(`cli_ID`)");
$wpdb->query("alter table wp_wp_eStore_customer_tbl add Key `txn_id`(`txn_id`)");

$wpdb->query("drop table if exists ci_test_groups_rel");
$wpdb->query("drop table if exists ci_autoimportqc");
$wpdb->query("drop table if exists ci_meenta_files");
$wpdb->query("drop table if exists ci_meenta_orders");




foreach (trpdd_get_install_folders() as $folder) {
  $dir = $folder['path'];
  if (!file_exists($dir)) {
    $rs = wp_mkdir_p($dir);
    if ($rs == false) {
      mkdir($dir, 0755, true);
    }
  }
}


update_option('TRCPDD_VS', TRCPDD_VS, false);

if ($blog_id < 2) {
  $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
  $time_mid_night = strtotime(date('Y-m-d', time() + $offset)) + 86400 -  $offset - 60;


  $cron_next_time = 0;

  wp_clear_scheduled_hook('cron_check_covid_status');


  if (! wp_next_scheduled('ci_cron_clean_results')) {
    wp_clear_scheduled_hook('ci_cron_clean_results');
    wp_schedule_event(time() + 30, '10min', 'ci_cron_clean_results');
  }



  wp_clear_scheduled_hook('upgrader_scheduled_cleanup');

  if (! wp_next_scheduled('ci_today_batch_cron')) {
    wp_clear_scheduled_hook('ci_today_batch_cron');
    wp_schedule_event($time_mid_night, 'daily', 'ci_today_batch_cron');
  }


  if (! wp_next_scheduled('ci_auto_upload_results')) {
    wp_clear_scheduled_hook('ci_auto_upload_results');
    wp_schedule_event($time_mid_night + 200, 'daily', 'ci_auto_upload_results');
  }


  if (! wp_next_scheduled('ci_fix_db')) {
    wp_clear_scheduled_hook('ci_fix_db');
    wp_schedule_event($time_mid_night, 'daily', 'ci_fix_db');
  }

  //5:30pm
  if (! wp_next_scheduled('cron_receiving_batch_id')) {
    wp_clear_scheduled_hook('cron_receiving_batch_id');
    if (defined('TRPDD_HOUR_CHANGE_BATCH')) {
      wp_schedule_event(strtotime(date_i18n('Y-m-d') . ' ' . TRPDD_HOUR_CHANGE_BATCH . ':30:00') - $offset, 'daily', 'cron_receiving_batch_id');
    }
  }


  //-----
  if (! wp_next_scheduled('ci_check_pdf_card_cron')) {
    wp_clear_scheduled_hook('ci_check_pdf_card_cron');
    wp_schedule_event(time() + $cron_next_time, 'hourly', 'ci_check_pdf_card_cron');
  }
  $cron_next_time += 60;


  if (! wp_next_scheduled('ci_check_upload_apis')) {
    wp_clear_scheduled_hook('ci_check_upload_apis');
    wp_schedule_event(time() + $cron_next_time, 'hourly', 'ci_check_upload_apis');
  }
  $cron_next_time += 60;



  wp_clear_scheduled_hook('ci_cron_sync_wsdl_client');


  if (! wp_next_scheduled('ci_cron_sync_wsdl_client_req')) {
    wp_clear_scheduled_hook('ci_cron_sync_wsdl_client_req');
    wp_schedule_event(time() + $cron_next_time, '10min', 'ci_cron_sync_wsdl_client_req');
  }
  $cron_next_time += 60;


  if (! wp_next_scheduled('ci_cron_sync_wsdl_get_ref_lab_reqs')) {
    wp_clear_scheduled_hook('ci_cron_sync_wsdl_get_ref_lab_reqs');
    wp_schedule_event(time() + $cron_next_time, 'hourly', 'ci_cron_sync_wsdl_get_ref_lab_reqs');
  }
  $cron_next_time += 60;

  if (! wp_next_scheduled('ci_cron_sync_wsdl_client_result')) {
    wp_clear_scheduled_hook('ci_cron_sync_wsdl_client_result');
    wp_schedule_event(time() + $cron_next_time, 'hourly', 'ci_cron_sync_wsdl_client_result');
  }
  $cron_next_time += 60;


  wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Clean_Req'));
  wp_schedule_event(time() + $cron_next_time, 'hourly', 'cron_all_api', array('CI_Api_Clean_Req'));
  $cron_next_time += 60;



  wp_clear_scheduled_hook('cron_all_api', array('CI_Api_User'));

  wp_clear_scheduled_hook('ci_auto_fax_results');
  wp_schedule_event(time() + $cron_next_time, 'hourly', 'ci_auto_fax_results');
  $cron_next_time += 60;

  wp_clear_scheduled_hook('ci_cron_2mins');
  wp_schedule_event(time() + $cron_next_time, '2min', 'ci_cron_2mins');
  $cron_next_time += 60;



  wp_clear_scheduled_hook('cron_all_api', array('CI_Check_Panel', 'panel'));
  wp_schedule_event($time_mid_night, 'daily', 'cron_all_api', array('CI_Check_Panel', 'panel'));

  if (!function_exists('WC')) {
    wp_clear_scheduled_hook('woocommerce_cleanup_logs');
    wp_clear_scheduled_hook('woocommerce_scheduled_sales');
    wp_clear_scheduled_hook('woocommerce_tracker_send_event');
    wp_clear_scheduled_hook('woocommerce_cleanup_personal_data');
    wp_clear_scheduled_hook('woocommerce_cleanup_sessions');
  }



  if (defined('DEMO_SITE') && DEMO_SITE) {
    wp_clear_scheduled_hook('cron_demo_auto');
    wp_schedule_event(time() + 3600, 'daily', 'cron_demo_auto');

    include('actions/sync_demo_api_xml.php');
  }
}
$vs_db = get_option('TRCPDD_VS', TRCPDD_VS);

$wpdb->query("ALTER TABLE ci_theoreticals CHANGE qc_type qc_type varchar(10)");

if (version_compare($vs_db, '2.4.7.62', '<')) {

  //remove columns
  $columns_remove = array(
    'order_req',
    'collector_signature',
    'released_to',
    'insurance_plan',
    'pb_logo'
  );

  foreach ($columns_remove as $col) {
    foreach ($btypes as $btype) {
      $batch_tbl = tr_pdd_get_table_by_btype($btype);
      $wpdb->query("alter table {$batch_tbl} drop column {$col} ");
    }

    $wpdb->query("alter table ci_saved_batches drop column {$col} ");
  }

  $wpdb->query("alter table ci_saved_batches drop KEY oldreq ");

  $report_remove = array('patient_num', 'patient_dob', 'medications', 'received_in_lab');
  foreach ($report_remove as $col) {
    foreach ($btypes as $btype) {
      $batch_tbl = tr_pdd_get_table_report_by_btype($btype);
      $wpdb->query("alter table {$batch_tbl} drop column {$col} ");
    }
  }

  $wpdb->query("alter table ci_doc drop key `doctor`");
  $wpdb->query("alter table ci_labs drop column `lab_dba_logo`");
  $wpdb->query("alter table ci_labs drop column `lab_logos`");
  $wpdb->query("alter table ci_labs drop column `lab_logoid`");
  $wpdb->query("alter table ci_labs drop column `lab_wsdl_server`");
  $wpdb->query("alter table ci_labs drop column `lab_results_active_date`");
  $wpdb->query("alter table ci_labs drop column `lab_results_footer_new`");
  $wpdb->query("alter table ci_labs drop column `lab_results_footer_pcr`");
  $wpdb->query("alter table ci_labs drop column `lab_results_footer_blood`");
  $wpdb->query("alter table ci_labs drop column `lab_results_footer_pcr_new_date`");
  $wpdb->query("alter table ci_labs drop column `lab_results_footer_pcr_new`");


  $wpdb->query("alter table ci_test_panels drop column `te_pos_by_IDs`");
  $wpdb->query("alter table ci_test_groups drop key `tg_name`");

  $wpdb->query("alter table ci_test_groups drop column `tg_column`");


  $wpdb->query("alter table ci_reports_pcr drop column `covid_needcheckstatus`");

  $wpdb->query("alter table ci_clinic drop column `cli_omanager`");
  $wpdb->query("alter table ci_clinic drop column `cli_PPO-in`");
  $wpdb->query("alter table ci_clinic drop column `cli_PPO-out`");
  $wpdb->query("alter table ci_clinic drop column `cli_cash`");
  $wpdb->query("alter table ci_clinic drop column `cli_bcbs`");
  $wpdb->query("alter table ci_clinic drop column `cli_pmedicare`");
  $wpdb->query("alter table ci_clinic drop column `cli_medicaid`");
  $wpdb->query("alter table ci_clinic drop column `cli_enzyme`");
  $wpdb->query("alter table ci_clinic drop column `cli_testing_profile`");
  $wpdb->query("alter table ci_clinic drop column `cli_confirm_positive`");
  $wpdb->query("alter table ci_clinic drop column `cli_individuals`");
  $wpdb->query("alter table ci_clinic drop column `cli_provider_name`");
  $wpdb->query("alter table ci_clinic drop column `cli_specialty`");
  $wpdb->query("alter table ci_clinic drop column `cli_provider_date`");
  $wpdb->query("alter table ci_clinic drop column `cli_om_user_ID`");
  $wpdb->query("alter table ci_clinic drop column `cli_ref_account`");
  $wpdb->query("alter table ci_clinic drop column `cli_service_id`");
  $wpdb->query("alter table ci_clinic drop column `cli_rpp_gi`");
  $wpdb->query("alter table ci_clinic drop column `cli_last_order`");

  $wpdb->query("drop table ci_ref_labs");

  $wpdb->query("ALTER TABLE ci_antibiotics CONVERT TO CHARACTER SET utf8");
  $wpdb->query("ALTER TABLE ci_abx CONVERT TO CHARACTER SET utf8");
  $wpdb->query("ALTER TABLE ci_abx_wound CONVERT TO CHARACTER SET utf8");
  $wpdb->query("ALTER TABLE ci_bacteria CONVERT TO CHARACTER SET utf8");
  $wpdb->query("ALTER TABLE ci_bacteria_wound CONVERT TO CHARACTER SET utf8");
}





foreach ($btypes as $btype) {

  $batch_tbl = tr_pdd_get_table_by_btype($btype);
  $report_tbl = tr_pdd_get_table_report_by_btype($btype);
  $wpdb->query("alter table {$report_tbl} change show_comment1 show_comment1 tinyint(1) default 0");
  $wpdb->query("alter table {$report_tbl} change show_comment2 show_comment2 tinyint(1) default 0");
  $wpdb->query("alter table {$report_tbl} change opi_id opi_id text");

  $wpdb->query("alter table {$report_tbl} drop column `final_count`");
  $wpdb->query("alter table {$report_tbl} drop column `preliminary_count`");
  $wpdb->query("alter table {$report_tbl} drop column `amended_count`");

  $wpdb->query("alter table {$batch_tbl} drop KEY oldreq ");

  $items = $wpdb->get_var("select count(*) from ci_dagnostic_codes 
								where `type` = '{$btype}' ");


  if (!$items) {
    $dagnostic_codes = tr_pdd_get_dagnostic_codes($btype);
    foreach ($dagnostic_codes as $key => $desc) {
      if (empty($key)) continue;

      $wpdb->insert('ci_dagnostic_codes', array(
        'key' => $key,
        'desc' => $desc,
        'type' => $btype,
        'created' => date_i18n('Y-m-d H:i:s'),
        'created_by' => get_current_user_id()
      ));
    }
  }
}


$exist_table = $wpdb->get_var("select sendtype from tr_log_sms limit 1");
if (!empty($wpdb->last_error)) {
  $sql = "CREATE TABLE tr_log_sms (
				phone varchar(20),
				patient_name varchar(100),
				patient_id int(11),
				assen varchar(100),
				sendtype varchar(3),
				message text,
				created datetime,
				KEY `created` (`created`),
				KEY `phone` (`phone`),
				KEY `assen` (`assen`),
				KEY `sendtype` (`sendtype`),
				KEY `patient_id` (`patient_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1";
  dbDelta($sql);
}


$covid_old_title = tr_get_option('ci_pdf_data', 'pcr_pdf_title_covid');

if (!empty($covid_old_title)) {
  $covid_group_id = $wpdb->get_var("select tg_ID 
							from ci_test_groups 
							where tg_type='pcr' and tg_is_covid = 1");
  $wpdb->update(
    'ci_test_groups',
    array('tg_pdf_title' => $covid_old_title),
    array('tg_ID' => $covid_group_id)
  );

  $options = get_option('ci_pdf_data');
  unset($options['pcr_pdf_title_covid']);

  update_option('ci_pdf_data', $options);
}

if (!$old_setup['update_pcr_style']) {
  $wpdb->update('ci_test_groups', array('tg_blood_style' => ''), array('tg_blood_style' => '1'));
  $wpdb->update('ci_clinic', array('cli_result_style' => ''), array('cli_result_style' => '1'));
  $wpdb->update('ci_clinic', array('cli_result_style_pcr' => ''), array('cli_result_style_pcr' => '1'));
  $wpdb->update('ci_clinic', array('cli_result_style_blood' => ''), array('cli_result_style_blood' => '1'));

  $old_setup['update_pcr_style'] = 1;
  update_option('ci_old_setup_lab_install', $old_setup);
}


//remove tables

$types = array('cgx', 'dna', 'ngs', 'rpp_gi', 'wom', 'allergy');
foreach ($types as $t) {
  if (!in_array($t, $btypes)) {
    $wpdb->query("drop table ci_batches_{$t}");
    $wpdb->query("drop table ci_batches_{$t}_meta");
  }
}


//check saved reqs
$wpdb->query("alter table ci_saved_batches drop column billed_amount");
$wpdb->query("alter table ci_saved_batches drop column received_amount");
$wpdb->query("alter table ci_saved_batches drop column bill_status");
$wpdb->query("alter table ci_saved_batches drop column balance");
$wpdb->query("alter table ci_saved_batches drop column claim_date");
$wpdb->query("alter table ci_saved_batches drop column clinic_history");
$wpdb->query("alter table ci_saved_batches drop column barcode");
$wpdb->query("alter table ci_saved_batches drop column id");
$wpdb->query("alter table ci_saved_batches drop column comments_validity");
$wpdb->query("alter table ci_saved_batches drop column testing_method");
$wpdb->query("alter table ci_saved_batches drop column reference");
$wpdb->query("alter table ci_saved_batches drop column comments1");
$wpdb->query("alter table ci_saved_batches drop column collected");
$wpdb->query("alter table ci_saved_batches drop column ins");
$wpdb->query("alter table ci_saved_batches drop column ins1");
$wpdb->query("alter table ci_saved_batches drop column adjusted");
$wpdb->query("alter table ci_saved_batches drop column withheld");
$wpdb->query("alter table ci_saved_batches drop column spec_received1");
$wpdb->query("alter table ci_saved_batches drop column added_logo_pdf");
$wpdb->query("alter table ci_saved_batches drop column faxed");
$wpdb->query("alter table ci_saved_batches drop column import_ID");
$wpdb->query("alter table ci_saved_batches drop column paper_pdf");
$wpdb->query("alter table ci_saved_batches drop column initials");
$wpdb->query("alter table ci_saved_batches drop column batch_no");

$rs = $wpdb->get_results("select * from ci_saved_batches where deleted = 0 and batch_type!='I'", ARRAY_A);

foreach ($rs as $r) {

  if (isset($btypes[$r['is_dna']])) {
    $btype = $btypes[$r['is_dna']];
    var_dump($btype);
    $mid = $r['mid'];
    unset($r['mid']);
    unset($r['is_dna']);
    unset($r['only_screening']);
    $r['is_saved'] = 1;
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    if ($wpdb->insert($batch_tbl, $r) !== false || stripos($wpdb->last_error, 'Duplicate') !== false) {
      $wpdb->query('delete from ci_saved_batches where mid = ' . $mid);
    } else {
      echo $wpdb->last_error;
    }
  }
}



$wpdb->query("delete from `ci_results` where drug = '' ");
$wpdb->query("alter table ci_results drop key `drug_report`");
$wpdb->query("alter table ci_results drop column `place`");
$wpdb->query("alter table ci_results drop column `detected`");
$wpdb->query("alter table ci_results drop column `in_med`");


//move well
$wpdb->query("update ci_test_panels te_well_numbers = te_abrev2 where te_abrev2 like '%well_%' ");
$wpdb->query("update ci_test_panels te_abrev2 = '' where te_abrev2 like '%well_%' ");

//fix M
$wpdb->query("update ci_reports_pcr set estatus = '' where estatus = 'M' and approve_date = '' ");

//fix internal_notes
foreach ($btypes as $btype) {
  $batch_tbl = tr_pdd_get_table_by_btype($btype);
  $report_tbl = tr_pdd_get_table_report_by_btype($btype);
  $result_tbl = tr_pdd_get_table_result_by_btype($btype);

  $existf = $wpdb->get_var("select re_notes from {$report_tbl} where re_notes !='' limit 1");
  if ($existf) {
    $rs = $wpdb->get_results("select patient_id, re_notes from {$report_tbl} where re_notes !='' ");
    foreach ($rs as $r) {
      if (empty($r->re_notes)) {
        continue;
      }
      if (in_array($btype, array('tox', 'study', 'pcr', 'gene'))) {
        $wpdb->update($batch_tbl, array('internal_notes' => $r->re_notes), array('assen' => $r->patient_id));
      } else {
        $wpdb->update($batch_tbl, array('notes_report' => $r->re_notes), array('assen' => $r->patient_id));
      }
    }
  }
  $wpdb->query("alter table {$report_tbl} drop column re_notes");

  //add batch-status : plate
  $countst = $wpdb->get_var("select count(*) from ci_batches_status where btype = '{$btype}' and s_text!='Plated' ");

  if ($countst > 0) {
    $has_plate = $wpdb->get_var("select count(*) from ci_batches_status where s_text = 'Plated' ");
    if (!$has_plate) {
      $wpdb->insert('ci_batches_status', array(
        'iorder' => 5,
        's_key' => 'plated',
        's_text' => 'Plated',
        'btype' => $btype,
        'istatus' => 1,
      ));
    }
  } else {
    $wpdb->delete('ci_batches_status', array('btype' => $btype, 's_text' => 'Plated'));
  }

  //check empty status
  $countst = $wpdb->get_var("select count(*) from ci_batches_status where btype = '{$btype}' and s_type not in ('so') and s_key!='plated' ");
  if ($countst == 0) {
    $only_btype = $btype;
    include(trpdd_get_file_path('scripts', 'setup_statuses'));
  }
}

$wpdb->query("update ci_wsdl_keys set wsdl_url = replace(wsdl_url,'xmlrpc.php','wsdl') ");

$wpdb->query("update ci_test_panels_cutoffs set cu_amp_score = cu_critical_high where cu_amp_score = '' or cu_amp_score is null ");
$wpdb->query("update ci_test_panels_cutoffs set cu_cq = cu_hqc where cu_cq = '' or cu_cq is null ");

//fix lab start date

$start_date = date('Y-m-d', strtotime('-1 months'));
$labs = $wpdb->get_results("select * from ci_labs where lab_start_date = '' or lab_start_date is null or lab_start_date > '{$start_date}' ");
foreach ($labs as $lab) {
  $start_date = date('Y-m-d', strtotime('-1 months'));
  foreach ($btypes as $btype) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $first_batch_date = $wpdb->get_var("select min(`batch_created`) from {$batch_tbl} where lab_ID = {$lab->lab_ID} and batch_created>'2000-01-01' ");

    if (!empty($first_batch_date)) {
      $batch_date = date_i18n('Y-m-d', strtotime($first_batch_date) - 86400);
      if (empty($start_date) || strtotime($batch_date) < strtotime($start_date)) {
        $start_date = $batch_date;
      }
    }
  }

  if (!empty($start_date)) {
    $wpdb->update(
      'ci_labs',
      array('lab_start_date' => $start_date),
      array('lab_ID' => $lab->lab_ID)
    );
  }
}

if (version_compare($vs_db, '5.9.230', '<')) {
  include_once(TRCPDD_PATH . 'parts/scripts/update_batch_archived_date.php');
}

//add status for standing orders
include_once(TRCPDD_PATH . 'parts/scripts/add_status_standing_orders.php');

$source_favicon = TRCPDD_PATH . 'images/favicon.ico';
$destination_favicon = ABSPATH . 'favicon.ico';

if (!file_exists($destination_favicon) || filemtime($source_favicon) > filemtime($destination_favicon)) {
  copy($source_favicon, $destination_favicon);
}
