<?php
//define in other plugins

//patient plugin
define('TRPDD_PATIENT_SITE', false);
define('TRLCN_PATH', false);
define('TRHUB_URL', '');
define('TRCDPDF_NEW', false);
define('DEMO_SITE', false);
define('CI_ALL_BATCH_TYPES', array(
    0 => 'tox',
    1 => 'dna',
    2 => 'blood',
    3 => 'allergy',
    5 => 'rpp_gi',
    7 => 'wom',
    14 => 'ngs',
    16 => 'pcr',
    17 => 'study',
    18 => 'gene',
));

//billing plugin
function tr_bill_get_billing_code() {}
/*
@return: {status:'ok'}
*/
function tr_client_send_hub($args)
{
    return array();
}

//cache
class Tr_Cache_Query extends wpdb
{
    public function __construct() {}
};
