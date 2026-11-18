<?php
if ($act == 'clear_invalid_tests' && isset($_GET['debug'])) {
    $checked_rows = 0;
    $removed_tests = 0;
    $rows_clear = $wpdb->get_results("select dc_ID, dc_type from {$bill_tbl}");

    foreach ($rows_clear as $row_clear) {
        $checked_rows++;
        $tests_clear = $wpdb->get_results($wpdb->prepare(
            "select ct_dc_ID, ct_test_ID, ct_test from {$bill_test_tbl} where ct_dc_ID = %d",
            $row_clear->dc_ID
        ));

        foreach ($tests_clear as $test_clear) {
            $valid_test = false;
            $test_id = (int) $test_clear->ct_test_ID;

            if ($test_id == -1 && $row_clear->dc_type == 'tox' && strtoupper(trim($test_clear->ct_test)) == 'REF-PDF') {
                $valid_test = true;
            } else if ($test_id > 0 && in_array($row_clear->dc_type, array('urine', 'screen'))) {
                $show_in_req = $row_clear->dc_type == 'screen' ? 1 : 0;
                $valid_test = (int) $wpdb->get_var($wpdb->prepare(
                    "select count(*) from ci_urine_validity where u_ID = %d and u_showinreq = %d",
                    $test_id,
                    $show_in_req
                )) > 0;
            } else if ($test_id > 0) {
                $valid_test = (int) $wpdb->get_var($wpdb->prepare(
                    "select count(*)
                    from ci_test_panels t
                    join ci_test_groups g ON g.tg_ID = t.te_group_id
                    left join ci_test_groups gp ON gp.tg_ID = g.tg_parent
                    where t.te_ID = %d
                    and t.te_type = %s
                    and t.te_in_req = 1
                    and t.te_is_control = 0
                    and (g.tg_show_req = 1 or gp.tg_show_req = 1)",
                    $test_id,
                    $row_clear->dc_type
                )) > 0;
            }

            if (!$valid_test) {
                $wpdb->delete($bill_test_tbl, array(
                    'ct_dc_ID' => $test_clear->ct_dc_ID,
                    'ct_test_ID' => $test_clear->ct_test_ID,
                    'ct_test' => $test_clear->ct_test,
                ));
                $removed_tests++;
            }
        }
    }

    Tr_Session_Class::add('Checked ' . $checked_rows . ' billing code rows. Removed ' . $removed_tests . ' invalid tests.');
    $act = '';
}
?>
