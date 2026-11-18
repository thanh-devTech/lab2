<?php
global $blog_id, $wpdb;
$wpdb->suppress_errors(true);

$settings       = tr_billing_get_settings();


require_once(ABSPATH . 'wp-admin/includes/upgrade.php');



$sql = "CREATE TABLE `ci_bill_drug_classes` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_drug_classes_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_bill_drug_classes_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


//ci_bill_bc_medicaid

$sql = "CREATE TABLE `ci_bill_bc_medicaid` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_bc_medicaid_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_medicaid_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

//ci_bill_bc_commercial_kit

$sql = "CREATE TABLE `ci_bill_bc_commercial_kit` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_bc_commercial_kit_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_commercial_kit_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

//ci_bill_bc_medicare

$sql = "CREATE TABLE `ci_bill_bc_medicare` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_medicare_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_bill_bc_medicare_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_codes_medicare` (
  `cm_ID` int(10) NOT NULL AUTO_INCREMENT,
  `cm_code` varchar(50) NOT NULL,
  `cm_range_bot` int(11),
  `cm_range_top` int(11),
  `cm_name` varchar(200),
  `cm_amount` int(11) not null,
  `cm_created` datetime,
  `cm_created_by` int(11),
  PRIMARY KEY (`cm_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_bc_cashpay` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_cashpay_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_bill_bc_cashpay_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_codes_cashpay` (
  `cm_ID` int(10) NOT NULL AUTO_INCREMENT,
  `cm_code` varchar(50) NOT NULL,
  `cm_range_bot` int(11),
  `cm_range_top` int(11),
  `cm_name` varchar(200),
  `cm_amount` int(11) not null,
  `cm_created` datetime,
  `cm_created_by` int(11),
  PRIMARY KEY (`cm_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_client_bill` (
  `dc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `dc_class` varchar(200) NOT NULL,
  `dc_code` varchar(50) NOT NULL,
  `dc_exclude_code` varchar(225) NOT NULL,
  `dc_dax_code` varchar(50),
  `dc_labref_info` text,
  `dc_amount` varchar(15) not null,
  `dc_pos_amount` varchar(15) not null,
  `dc_minimum_match` tinyint(2) default 1,
  `dc_maximum_match` tinyint(2),
  `dc_type` varchar(6) default 'tox',
  `dc_start_date` date,
  `dc_end_date` date,
  `dc_detect_u0005` tinyint(1) default 0,
  `dc_swabbed_code` varchar(15),
  `dc_swabbed_code2` varchar(15),
  `dc_created` datetime,
  `dc_created_by` int(11),
  `dc_panel_ID` int(11) default 0,
  `dc_panel_search` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `dc_group_class` TEXT NULL,
  PRIMARY KEY (`dc_ID`),
  KEY `dc_code` (`dc_code`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


$sql = "CREATE TABLE `ci_bill_bc_client_bill_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  `ct_test` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);

$sql = "CREATE TABLE `ci_bill_bc_client_bill_pos_test` (
  `ct_dc_ID` int(10) NOT NULL,
  `ct_test_ID` int(10) NOT NULL,
  UNIQUE KEY `ct_dc_testID` (`ct_dc_ID`,`ct_test_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_bill_codes_client_bill` (
  `cm_ID` int(10) NOT NULL AUTO_INCREMENT,
  `cm_code` varchar(50) NOT NULL,
  `cm_range_bot` int(11),
  `cm_range_top` int(11),
  `cm_name` varchar(200),
  `cm_amount` int(11) not null,
  `cm_created` datetime,
  `cm_created_by` int(11),
  PRIMARY KEY (`cm_ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_batch_billings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient` varchar(100) NOT NULL,
  `claim_no` varchar(30) NOT NULL,
  `assen` varchar(15) NOT NULL,
  `servdate` date DEFAULT NULL,
  `date` date DEFAULT NULL,
  `allowed_amount` varchar(25) NOT NULL,
  `insurance_payment` varchar(25) NOT NULL ,
  `claim_date` date default null,
  `billed_amount` varchar(20) not null,
  `received_amount` varchar(20) not null,
  `balance` varchar(20) not null,
  `bill_status` varchar(20) not null,
  `patient_balance` varchar(20) not null,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `assen` (`assen`)
) ENGINE=InnoDB";

$rs = dbDelta($sql);





$sql = "CREATE TABLE `ci_autoimportqc` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `filename` varchar(200),
               `ctype` varchar(10) default 'qc',
               `total_rows` int(11),
               `per_run` int(11),
               `imported` int(11),
               `start_time` datetime,
               `end_time` datetime,
               `data` text,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_billing_ins_upload_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mid` bigint(15) NOT NULL,
  `btype` varchar(10) NOT NULL,
  `created` datetime,
  `createdby` int(10),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB";

$rs = dbDelta($sql);

//check ci_test_panels table
$test_panel_exists = $wpdb->get_var("select count(*) as count from 'ci_bill_drug_classes");
if ($test_panel_exists == 0) {
  // dbDelta(file_get_contents(TRCDBI_PATH . 'inc/default.sql'));
}

$wpdb->query("alter table ci_bill_drug_classes drop column dc_exclude_class");
$wpdb->query("alter table ci_bill_bc_medicaid drop column dc_exclude_class");
$wpdb->query("alter table ci_bill_bc_medicare drop column dc_exclude_class");
$wpdb->query("alter table ci_bill_bc_cashpay drop column dc_exclude_class");
$wpdb->query("alter table ci_bill_bc_client_bill drop column dc_exclude_class");


$wpdb->query("alter table ci_bill_drug_classes_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_medicaid_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_medicare_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_cashpay_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_client_bill_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_medicaid_pos_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_cashpay_pos_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_client_bill_pos_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_bc_medicare_pos_test drop key ct_dc_ID");
$wpdb->query("alter table ci_bill_drug_classes_pos_test drop key ct_dc_ID");

$wpdb->query("alter table ci_batch_billings drop key claim_no");


$wpdb->query("alter table ci_test_groups add column `tg_notestcheckbox` tinyint(1) default 0");

$wpdb->query("alter table ci_doc add column `doc_pecos` tinyint(1) default 0");
$wpdb->query("alter table ci_doc add column `doc_provider_code` varchar(20)");

$wpdb->query("alter table ci_clinic add column `cli_pay_type` varchar(10) default '{$settings['default_pay_type']}'");
$wpdb->query("alter table ci_clinic add column `cli_facility_code` varchar(20) default ''");
$wpdb->query("alter table ci_clinic add column `cli_swab_code` varchar(20) default ''");


$wpdb->query("update ci_bill_drug_classes set dc_type='tox' where dc_type='T'");

$wpdb->query('alter table ci_batch_billings drop column payment_paid');

$wpdb->query('alter table ci_bill_drug_classes drop column dc_tests');
$wpdb->query('alter table ci_bill_bc_medicaid drop column dc_tests');
$wpdb->query('alter table ci_bill_bc_medicare drop column dc_tests');

$wpdb->query("alter table ci_insurances ADD COLUMN IF NOT EXISTS `ins_code` VARCHAR(50) default ''");


$remove_cols = array('claim_date', 'payment_paid', 'billed_amount', 'insurance_payment', 'balance', 'received_amount', 'bill_status');
$btypes = tr_pdd_get_batch_types();

unset($btypes[17]);


$types = array(
  0 => 'tox',
  1 => 'dna',
  2 => 'blood',
  3 => 'allergy',
  5 => 'rpp_gi',
  6 => 'cgx',
  7 => 'wom',
  14 => 'ngs',
  16 => 'pcr'
);

$btypes = array_merge($btypes, $types);
foreach ($btypes as $btype) {
  $btbl = tr_pdd_get_table_by_btype($btype);
  foreach ($remove_cols as $col) {
    $wpdb->query("alter table {$btbl} drop column {$col}");
  }
  $wpdb->query("Alter table {$btbl} add key `batch_created`(`batch_created`);");
}

Tr_Base_Class_V4::create_role('BillbyClinic', 'BillbyClinic', array(
  'billing_admin'
));


Tr_Base_Class_V4::create_role('BillbyRefLab', 'BillbyRefLab', array(
  'billing_admin'
));
trcdbi_update_exclude_billingcid();


update_option('TRCDBI_V', TRCDBI_V);
