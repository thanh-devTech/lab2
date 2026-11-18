<?php
global $wpdb;

function update_test_field($test_ids, $field = '', $wpdb)
{
    $wpdb->update(
        'ci_test_panels',
        [$field => 0],
        ['te_type' => 'tox']
    );
    $wpdb->query("
            UPDATE ci_test_panels
            SET $field = 1
            WHERE te_type = 'tox'
            AND te_ID IN ($test_ids)
        ");
}

$_POST = tr_pdd_stripslashes_deep($_POST);

$basic         = isset($_POST['te_basic']) ? $_POST['te_basic'] : '';
$standard       = isset($_POST['te_standard']) ? $_POST['te_standard'] : '';
$high_rick      = isset($_POST['te_highrisk']) ? $_POST['te_highrisk'] : array();
$comprehensive    = isset($_POST['te_comprehensive']) ? $_POST['te_comprehensive'] : '';

if (!empty($basic)) {
    update_test_field($basic, 'te_basic', $wpdb);
}
if (!empty($standard)) {
    update_test_field($standard, 'te_standard', $wpdb);
}
if (!empty($high_rick)) {
    update_test_field($high_rick, 'te_highrisk', $wpdb);
}
if (!empty($comprehensive)) {
    update_test_field($comprehensive, 'te_comprehensive', $wpdb);
}

Tr_Session_Class::add('Saved profile');
