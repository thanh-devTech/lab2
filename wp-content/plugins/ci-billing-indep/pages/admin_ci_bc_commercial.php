<?php
global $wpdb;

include(TRCDBI_PATH . 'inc/functions_fix.php');
$settings       = tr_billing_get_settings();
$bill_tbl = 'ci_bill_drug_classes';
$bill_test_tbl = 'ci_bill_drug_classes_test';

if (in_array('tox', $types)) {
    $types[-1] = 'urine';
    $types[-2] = 'screen';
}


if (isset($_POST['save_action'])) {
    $_POST  =  tr_pdd_stripslashes_deep($_POST);
    $row    = $_POST['drug_class'];
    $row['dc_panel_search'] = !empty($row['dc_panel_search']) ? 1 : 0;
    $row['dc_is_group'] = !empty($row['dc_is_group']) ? 1 : 0;
    $row['dc_detect_u0005'] = 0;
    if (empty($row['dc_is_group'])) {
        $row['dc_group_class'] = '';
    }


    if (isset($_GET['debug'])) {
        var_dump($row);
        echo '<br><br>';
    }

    $row_id = $row['dc_ID'];
    if ($row_id > 0) {
        $rs = $wpdb->update('ci_bill_drug_classes', $row, array('dc_ID' => $row_id));
        if ($rs === false) {
            Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
        } else {
            Tr_Session_Class::add('Updated Successfully!');
        }
    } else {
        $row['dc_created'] = Tr_Base_Class_V4::date("Y-m-d H:i:s");
        $wpdb->insert('ci_bill_drug_classes', $row);
        $row_id = $wpdb->insert_id;
        if ($row_id > 0) {
            Tr_Session_Class::add('Created New  Successfully!');
        } else {
            Tr_Session_Class::add_error('Error.' . $wpdb->last_error);
        }
        $row = array();
    }

    if ($row_id > 0) {

        $wpdb->delete($bill_test_tbl, array('ct_dc_ID' => $row_id));

        if (isset($_GET['debug'])) {
            var_dump($_POST['dc_tests']);
            echo '<br><br>';
        }
        if (empty($row['dc_is_group'])) {

            foreach ($_POST['dc_tests'] as $testid) {
                $testid = trim($testid);
                if (empty($testid)) continue;

                if (in_array($row['dc_type'], array('urine', 'screen'))) {
                    $testname = $wpdb->get_var("select u_name  from ci_urine_validity where u_ID = {$testid} ");
                } else {
                    $testname = $wpdb->get_var("select te_name from ci_test_panels where te_ID = {$testid}");
                }
                $inserttestarr = array(
                    'ct_test' => $testname,
                    'ct_test_ID' => $testid,
                    'ct_dc_ID' => $row_id
                );

                $rst = $wpdb->insert($bill_test_tbl, $inserttestarr);

                if (isset($_GET['debug'])) {
                    var_dump($inserttestarr);
                    echo '<br><br>';
                    var_dump($rst);
                    echo $wpdb->last_error;
                    echo '<br><br>';
                }
            }
        }


        $wpdb->delete('ci_bill_drug_classes_pos_test', array('ct_dc_ID' => $row_id));
        if (isset($_POST['dc_pos_tests']) && is_array($_POST['dc_pos_tests'])) {
            foreach ($_POST['dc_pos_tests'] as $tid) {
                $tid = trim($tid);
                if (empty($tid)) continue;
                $wpdb->insert('ci_bill_drug_classes_pos_test', array(
                    'ct_test_ID' => $tid,
                    'ct_dc_ID'   => $row_id
                ));
            }
        }
    }
} else if (isset($_POST['do_import'])) {

    $file = $_FILES['fileimport'];

    if (stripos($file['name'], '.csv') > 0) {
        $rs = trpdd_get_data($file['tmp_name'], 'csv', null, true, false, true);

        $data = array();
        foreach ($rs['data'] as $row) {

            $data[] = array(
                'dc_class' => trpdd_get_string_exist($row, array('class')),
                'dc_code' => trpdd_get_string_exist($row, array('code')),
                'tests' => explode(',', trpdd_get_string_exist($row, array('tests'))),
                'dc_amount' => trpdd_get_string_exist($row, array('amount')),
                'dc_pos_amount' => trpdd_get_string_exist($row, array('billupto')),
                'dc_minimum_match' => trpdd_get_string_exist($row, array('minimummatch')),
                'dc_swabbed_code' => trpdd_get_string_exist($row, array('swabbedcode')),
                'dc_swabbed_code2' => trpdd_get_string_exist($row, array('collectioncode', 'swabbedcode2')),
                'dc_type' => trpdd_get_string_exist($row, array('type')),
            );
        }
    } else {
        $data = file_get_contents($file['tmp_name']);
        $data = json_decode($data, true);
    }


    if (!is_array($data) || count($data) == 0) {
        Tr_Session_Class::add_error('Import failed. No billing code records were found in the uploaded file.');
    } else {
        $count_row = 0;
        foreach ($data as $row) {
            $tests = isset($row['tests']) && is_array($row['tests']) ? $row['tests'] : array();
            $test_ids = isset($row['test_ids']) && is_array($row['test_ids']) ? $row['test_ids'] : array();
            unset($row['dc_ID']);
            unset($row['tests']);
            unset($row['test_ids']);
            foreach (array('dc_created', 'dc_created_by', 'dc_start_date', 'dc_end_date', 'dc_minimum_match', 'dc_maximum_match') as $nullable_key) {
                if (isset($row[$nullable_key]) && $row[$nullable_key] === '') {
                    $row[$nullable_key] = null;
                }
            }

            $row_id = $wpdb->get_var("select dc_ID from ci_bill_drug_classes where dc_class = '{$row['dc_class']}' ");
            if ($row_id > 0) {
                $rs = $wpdb->update('ci_bill_drug_classes', $row, array('dc_ID' => $row_id));
            } else {
                $rs = $wpdb->insert('ci_bill_drug_classes', $row);
                $row_id = $wpdb->insert_id;
            }

            if ($rs !== false && $row_id > 0) {
                $count_row++;
            }

            if ($rs !== false && $row_id > 0 && count($tests) > 0) {
                $wpdb->delete($bill_test_tbl, array('ct_dc_ID' => $row_id));
                foreach ($tests as $test_index => $test) {
                    $test = trim($test);
                    if (empty($test)) continue;
                    $wpdb->insert($bill_test_tbl, array(
                        'ct_test' => $test,
                        'ct_test_ID' => isset($test_ids[$test_index]) ? (int) $test_ids[$test_index] : 0,
                        'ct_dc_ID' => $row_id
                    ));
                }
            }
        }
        if ($count_row > 0) {
            Tr_Session_Class::add('Imported ' . $count_row . ' !');
        } else {
            Tr_Session_Class::add_error('Import failed. No billing code records were imported.');
        }
    }
}

if ($act == 'export') {

    $rows = $wpdb->get_results("select * from ci_bill_drug_classes", ARRAY_A);

    $filename = 'billing_commercial.csv';
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/csv");

    $f = fopen('php://output', 'w');
    $headers = array(
        'Class',
        'Code',
        'Tests',
        'Amount',
        'Bill up to',
        'Minimum Match',
        'Swabbed Code',
        'Collection Code',
        'Type'
    );
    fputcsv($f, $headers);
    foreach ($rows as $i => $row) {
        $tests = $wpdb->get_col("select ct_test 
                from {$bill_test_tbl} 
	            where ct_dc_ID = {$row['dc_ID']}");

        $tests = implode(', ', $tests);
        $data = array(
            $row['dc_class'],
            $row['dc_code'],
            $tests,
            $row['dc_amount'],
            $row['dc_pos_amount'],
            $row['dc_minimum_match'],
            $row['dc_swabbed_code'],
            $row['dc_swabbed_code2'],
            $row['dc_type'],
        );
        fputcsv($f, $data);
    }
    exit;
} else if ($act == 'export_json') {

    $rows = $wpdb->get_results("select * from ci_bill_drug_classes", ARRAY_A);
    foreach ($rows as $i => $row) {
        $tests = $wpdb->get_col("select ct_test from {$bill_test_tbl} 
	            where ct_dc_ID = {$row['dc_ID']}");
        $test_ids = $wpdb->get_col("select ct_test_ID from {$bill_test_tbl} 
	            where ct_dc_ID = {$row['dc_ID']}");
        $rows[$i]['tests'] = $tests;
        $rows[$i]['test_ids'] = $test_ids;
    }
    $filename = 'billing_commercial.json';
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/json");
    echo json_encode($rows);
    exit;
} else if ($act == 'import') {
?>
    <form method="post" enctype="multipart/form-data">
        <div class="frow center" style="padding:40px">
            <h2 class="center">Import</h2>
            <br>
            <br>
            <label>File:</label>
            <input type="file" name="fileimport" />
            <br>
            <br>
            <input type="submit" class="button" name="do_import" value="Upload" />
        </div>
    </form>
<?php
    exit;
} else if ($act == 'edit' || $act == 'add') {

    wp_enqueue_script('jquery-ui-datepicker');


    $panels     = array();
    $all_classes = $wpdb->get_results("SELECT dc_ID, dc_class FROM ci_bill_drug_classes WHERE dc_is_group = 0 AND dc_class != '' AND dc_type = 'tox' ORDER BY dc_class ASC");

    $rs         = $wpdb->get_results("select g.* from ci_test_groups g 
    where g.tg_show_req = 1
    and exists (
        select 1 from ci_test_panels tp
        JOIN ci_test_groups tg ON tg.tg_ID = tp.te_group_id
        where tp.te_in_req = 1
        and tp.te_is_control = 0
        and (tg.tg_ID = g.tg_ID or tg.tg_parent = g.tg_ID)
    )
    ");
    foreach ($rs as $r) {
        if (!isset($panels[$r->tg_type])) {
            $panels[$r->tg_type] = array();
        }
        $panels[$r->tg_type][] = array(
            'label' => $r->tg_name,
            'id' => $r->tg_ID,
            'value' => $r->tg_name
        );
    }
    $all_tests = $wpdb->get_results("select 
                te_ID, 
                te_name, 
                te_type ,
                te_group_id,
                g.tg_parent as group_parent,
                g.tg_name as group_name,
                gp.tg_name as parent_group_name,
                if(g.tg_parent > 0, (select g2.tg_name from ci_test_groups g2 where g2.tg_ID = g.tg_parent limit 1),g.tg_name) as tg_name
                from ci_test_panels t
                JOIN ci_test_groups g ON g.tg_ID = t.te_group_id
                LEFT JOIN ci_test_groups gp ON gp.tg_ID = g.tg_parent
                where t.te_in_req = 1
                and t.te_is_control = 0
                and (g.tg_show_req = 1 or gp.tg_show_req = 1)
                order by tg_name, te_name asc");


    if (in_array('tox', $types)) {
        $urines = $wpdb->get_results("select 
        u_ID as te_ID,
        u_name as te_name,
        'urine' as te_type,
        0 as te_group_id,
        0 as group_parent
        from ci_urine_validity where u_showinreq = 0");
        $all_tests = array_merge($all_tests, $urines);

        $screenings = $wpdb->get_results("select 
        u_ID as te_ID,
        u_name as te_name,
        'screen' as te_type,
        0 as te_group_id,
        0 as group_parent
        from ci_urine_validity where u_showinreq = 1");
        $all_tests = array_merge($all_tests, $screenings);
    }

    if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {
        $id     = $_REQUEST['id'];
        $row    = $wpdb->get_row("select * from ci_bill_drug_classes where dc_ID = {$id}", ARRAY_A);

        $dc_pos_tests = $wpdb->get_col("select ct_test_ID from ci_bill_drug_classes_pos_test where ct_dc_ID = {$id}");

        $row['dc_pos_tests'] = implode(',', $dc_pos_tests);

        $tests_arr  = $wpdb->get_results("select * from {$bill_test_tbl} where ct_dc_ID = {$id}");

        $tests = array();

        foreach ($tests_arr as $tobj) {

            if ($tobj->ct_test_ID == 0 || isset($_GET['fix_test_group'])) {
                $tobj->ct_test_ID = trbill_find_id_by_name($tobj, $row, $tests);

                if ($tobj->ct_test_ID > 0) {
                    $wpdb->update($bill_test_tbl, array('ct_test_ID' => $tobj->ct_test_ID), array('ct_dc_ID' => $tobj->ct_dc_ID));
                }
            }


            if (!in_array($tobj->ct_test_ID, $tests)) {
                $tests[] = $tobj->ct_test_ID;
            }
        }

        $row['dc_tests'] = implode(',', $tests);
    } else {
        $row = array();
    }

    if (isset($_GET['debug'])) {
        var_dump($row);
    }

    $enable_bc_test_popup = !empty($settings['enable_bc_test_popup']);

?>
    <div class="wrap">
        <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> <a href="<?php Tr_Base_Class_V4::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All</a> </h2>

        <?php Tr_Session_Class::show() ?>

        <form method="post" class="need_validate">
            <div class="frow">
                <h4>Info</h4>
            </div>

            <div class="frow">
                <div class="col2">
                    <div><label>Type</label></div>
                    <select name="drug_class[dc_type]">
                        <?php
                        foreach ($types as $btype) {
                            $vl = tr_pdd_get_typename($btype);
                            if ($btype == 'urine') {
                                $vl = 'Urine Validity';
                            } else if ($btype == 'screen') {
                                $vl = 'Screeening';
                            }
                            $selected = isset($row['dc_type']) && $btype == $row['dc_type'] ? 'selected' : '';
                            echo '<option value="' . $btype . '" ' . $selected . '>' . $vl . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col4">
                    <label>Code</label>
                    <input type="text" name="drug_class[dc_code]" value="<?php echo isset($row['dc_code']) ? $row['dc_code'] : '' ?>" maxlength="50" required />
                </div>
                <div class="col4">
                    <label>Exclude Codes</label>
                    <input type="text" name="drug_class[dc_exclude_code]" value="<?php echo isset($row['dc_exclude_code']) ? $row['dc_exclude_code'] : '' ?>" maxlength="225" placeholder="codeA, codeB,..." />
                </div>
            </div>

            <div class="frow">

                <div class="col2">
                    <div style="display: flex; justify-content: space-between;">
                        <label>Class</label>
                        <div style="display: flex; justify-content: space-between;">
                            <input type="checkbox" name="drug_class[dc_panel_search]" class="autocompletepanel" <?php echo $row['dc_panel_search'] ? 'checked' : '' ?> /> Panel &nbsp;&nbsp;
                            <input type="checkbox" id="dc_is_group" name="drug_class[dc_is_group]" value="1"
                                <?php echo !empty($row['dc_is_group']) ? 'checked' : '' ?>
                                <?php echo (!isset($row['dc_type']) || (isset($row['dc_type']) && $row['dc_type'] !== 'tox')) ? 'style="display:none"' : '' ?> />
                            <label id="label_is_group"
                                <?php echo (!isset($row['dc_type']) || (isset($row['dc_type']) && $row['dc_type'] !== 'tox')) ? 'style="display:none"' : '' ?>>
                                Group &nbsp;&nbsp;
                            </label>
                        </div>
                    </div>
                    <input type="text" name="drug_class[dc_class]" value="<?php echo isset($row['dc_class']) ? $row['dc_class'] : '' ?>" maxlength="200" required />
                    <input type="hidden" name="drug_class[dc_panel_ID]" value="<?php echo $row['dc_panel_ID'] ?>" />
                </div>

                <div class="col2">
                    <label>DAX</label>
                    <input type="text" name="drug_class[dc_dax_code]" value="<?php echo isset($row['dc_dax_code']) ? $row['dc_dax_code'] : '' ?>" maxlength="50" />
                </div>
            </div>

            <div class="frow" id="row_tests_classes">
                <div class="col2">
                    <div id="col_tests">
                        <div><label>Tests<?php if ($enable_bc_test_popup) { ?> - <a href="#" class="bc-open-test-popup">select tests</a><?php } ?></label></div>
                        <div id="bc_test_picker_wrap" class="<?php echo $enable_bc_test_popup ? 'bc-test-popup-enabled' : '' ?>">
                            <select name="dc_tests[]" class="bjs_select2" data-val="<?php echo isset($row['dc_tests']) ? $row['dc_tests'] : '' ?>" multiple required>
                            </select>
                            <?php if ($enable_bc_test_popup) { ?>
                                <div class="bc-selected-tests-summary">No tests selected</div>
                            <?php } ?>
                        </div>
                    </div>
                    <div id="col_classes" style="display:none">
                        <div><label>Classes (for Group)</label></div>
                        <select name="dc_group_classes[]" id="dc_group_classes" class="bjs_select2" multiple>
                        </select>
                        <input type="hidden" id="dc_group_class_val"
                            name="drug_class[dc_group_class]"
                            value="<?php echo isset($row['dc_group_class']) ? esc_attr($row['dc_group_class']) : '' ?>" />
                    </div>
                </div>
                <div class="col2">
                    <label>Amount</label>
                    <input type="text" name="drug_class[dc_amount]" value="<?php echo isset($row['dc_amount']) ? $row['dc_amount'] : '' ?>" maxlength="11" />
                </div>
            </div>
            <div class="frow">
                <div class="col2">
                    <div><label>Only bill if positive</label></div>
                    <select name="dc_pos_tests[]" class="bjs_select2" data-val="<?php echo isset($row['dc_pos_tests']) ? $row['dc_pos_tests'] : '' ?>" multiple>
                    </select>
                </div>
                <div class="col2">
                    <label>Bill up to</label>
                    <input type="text" name="drug_class[dc_pos_amount]" value="<?php echo isset($row['dc_pos_amount']) ? $row['dc_pos_amount'] : '' ?>" maxlength="11" />
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Minimum Match</label>
                    <input type="number" name="drug_class[dc_minimum_match]" value="<?php echo isset($row['dc_minimum_match']) ? $row['dc_minimum_match'] : '' ?>" maxlength="2" />
                </div>
                <div class="col4">
                    <label>Maximum Match</label>
                    <input type="number" name="drug_class[dc_maximum_match]" value="<?php echo isset($row['dc_maximum_match']) ? $row['dc_maximum_match'] : '' ?>" maxlength="2" />
                </div>
                <div class="col4">
                    <label>Start Date</label>
                    <input type="text" class="has_date" name="drug_class[dc_start_date]" value="<?php echo isset($row['dc_start_date']) && $row['dc_start_date'] != '0000-00-00' ? $row['dc_start_date'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>End Date</label>
                    <input type="text" class="has_date" name="drug_class[dc_end_date]" value="<?php echo isset($row['dc_end_date']) && $row['dc_end_date'] != '0000-00-00' ? $row['dc_end_date'] : '' ?>" />
                </div>
            </div>
            <div class="frow">
                <div class="col2">
                    <label>Swabbed Code</label>
                    <input type="text" name="drug_class[dc_swabbed_code]" value="<?php echo isset($row['dc_swabbed_code']) ? $row['dc_swabbed_code'] : '' ?>" />
                </div>
                <div class="col2">
                    <label>Collection Code</label>
                    <input type="text" name="drug_class[dc_swabbed_code2]" value="<?php echo isset($row['dc_swabbed_code2']) ? $row['dc_swabbed_code2'] : '' ?>" />
                </div>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_action" value="1" />
                <input type="hidden" name="drug_class[dc_ID]" value="<?php echo isset($row['dc_ID']) ? $row['dc_ID'] : '' ?>" />
            </div>
        </form>
    </div>
    <style>
        .frow input,
        .frow select {
            max-width: none !important;
        }
    </style>

    <script>
        var $currentId = '<?php echo isset($row['dc_ID']) ? $row['dc_ID'] : '' ?>' + '';
        var $panels = <?php echo json_encode($panels) ?>;
        var $all_tests = <?php echo json_encode($all_tests) ?>;
        var $autopanelid = 0;

        var $all_classes = <?php echo json_encode($all_classes); ?>;
        var $group_classes_inited = false;
        jQuery(function($) {

            $('body').on('click change', '.autocompletepanel', function() {
                var $classf = $('[name="drug_class[dc_class]"]');
                var $btype = $('[name="drug_class[dc_type]"]').val();
                if ($(this).is(':checked')) {
                    $classf.autocomplete({
                        source: $panels[$btype],
                        minLength: 1,
                        select: function(event, ui) {
                            $autopanelid = ui.item.id;
                            $('[name="drug_class[dc_panel_ID]"]').val(ui.item.id);
                            $('[name="drug_class[dc_type]"]').change();
                        }
                    });
                } else {
                    $('[name="drug_class[dc_panel_ID]"]').val('');
                }
            })

            $('[name="drug_class[dc_type]"]').change(function() {
                var $testse = $('[name="dc_tests[]"]');
                var $values = $testse.data('val') + "";
                $values = $values.split(',');
                var $btype = $(this).val();
                var $html = '';
                var $have_value = [];
                for (i in $all_tests) {
                    var $t = $all_tests[i];
                    if ($t.te_type == $btype) {
                        if ($have_value.indexOf($t.te_ID) > -1) continue;
                        $have_value.push($t.te_ID);
                        var $k = ($t.te_ID);
                        var $selected = $values.includes($k) ? 'selected' : '';
                        if ($autopanelid > 0 && ($t.te_group_id == $autopanelid || $t.group_parent == $autopanelid)) {
                            $selected = 'selected';
                        }
                        var $tname = $t.te_name;
                        if ($t.tg_name) {
                            $tname += '  .(' + $t.tg_name + ')';
                        }
                        $html += '<option value="' + $t.te_ID + '" ' + $selected + '>' + $tname + '</option>';
                    }
                }
                $autopanelid = 0;
                $testse.html($html).select2();

                var $testpos = $('[name="dc_pos_tests[]"]');
                var $values = $testpos.data('val') + "";
                $values = $values.split(',');
                var $btype = $(this).val();
                var $html = '';
                var $have_value = [];
                for (i in $all_tests) {
                    var $t = $all_tests[i];
                    if ($t.te_type == $btype) {
                        if ($have_value.indexOf($t.te_ID) > -1) continue;
                        $have_value.push($t.te_ID);
                        var $selected = $values.includes($t.te_ID) ? 'selected' : '';
                        var $tname = $t.te_name;
                        if ($t.tg_name) {
                            $tname += '  .(' + $t.tg_name + ')';
                        }
                        $html += '<option value="' + $t.te_ID + '" ' + $selected + '>' + $tname + '</option>';
                    }
                }
                $testpos.html($html).select2();

                $('.autocompletepanel').change();

            }).change();

            $('.has_date').datepicker({
                dateFormat: 'yy-mm-dd'
            });

            function initGroupClasses() {
                var $sel = $('#dc_group_classes');
                var $savedVal = $('#dc_group_class_val').val();
                var $savedIds = $savedVal ? $savedVal.split(',').map(s => s.trim()).filter(Boolean) : [];

                var $html = '';
                for (var i in $all_classes) {
                    var cls = $all_classes[i];
                    if (/\d+[-–]\d+\s+drug\s+class/i.test(cls.dc_class)) continue;
                    if ($currentId && cls.dc_ID + '' === $currentId) continue;
                    var id = cls.dc_ID + '';
                    var selected = (!$group_classes_inited && $savedIds.length === 0) ?
                        'selected' :
                        ($savedIds.indexOf(id) > -1 ? 'selected' : '');
                    $html += '<option value="' + id + '" ' + selected + '>' + cls.dc_class + '</option>';
                }
                $sel.html($html).select2();

                syncGroupClass();
                $group_classes_inited = true;
            }

            function syncGroupClass() {
                var $selected = $('#dc_group_classes').val() || [];
                $('#dc_group_class_val').val($selected.join(','));
            }

            function toggleGroup() {
                var $btype = $('[name="drug_class[dc_type]"]').val();
                var $isGroup = $('#dc_is_group').is(':checked');
                if ($btype === 'tox') {
                    $('#dc_is_group, #label_is_group').show();
                } else {
                    $('#dc_is_group, #label_is_group').hide();
                    $('#dc_is_group').prop('checked', false);
                    $('input[name="drug_class[dc_is_group]"][type="hidden"]').val('0');
                }

                if ($isGroup && $btype === 'tox') {
                    $('#col_tests').hide();
                    $('#col_tests select').removeAttr('required');
                    $('[name="dc_tests[]"]').val([]).trigger('change');
                    $('#col_classes').show();
                    initGroupClasses();
                } else {
                    if ($btype !== 'tox') {
                        $('#dc_is_group').prop('checked', false);
                        $('input[name="drug_class[dc_is_group]"][type="hidden"]').val('0');
                    }
                    $('#col_tests').show();
                    $('#col_tests select').attr('required', 'required');
                    $('#col_classes').hide();
                }
            }

            $('#dc_group_classes').on('change', function() {
                syncGroupClass();
            });

            $('#dc_is_group').on('change', function() {
                toggleGroup();
            });

            $('[name="drug_class[dc_type]"]').on('change', function() {
                toggleGroup();
            });

            toggleGroup();
        })
    </script>

<?php
    include(TRCDBI_PATH . 'pages/part_bc_test_popup.php');
} else {
    include(TRCDBI_PATH . 'pages/part_bc_clear_invalid_tests.php');

    if ($act == 'pdelete') {
        $id = $_REQUEST['idd'];
        $wpdb->query("delete from `ci_bill_drug_classes` WHERE  `dc_ID`={$id} ");
        $wpdb->query("delete from {$bill_test_tbl} where ct_dc_ID = {$id} ");

        Tr_Session_Class::add('Deleted Successfully');
    }

    $selected_btype = isset($_GET['btype']) ? $_GET['btype'] : '';

    $order      = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
    $orderby    = "order by dc_is_group desc, dc_ID $order";
    $types_str  = implode("','", $types);

    $where      = '';

    if (!empty($selected_btype)) {
        $where .= " and dc_type = '{$selected_btype}' ";
    } else {
        $where = " and dc_type in ('{$types_str}') ";
    }


    $sql        = "select 
    m.*,
    (select group_concat(ct_test) from {$bill_test_tbl} t where t.ct_dc_ID = m.dc_ID) as dc_tests
    from ci_bill_drug_classes m
    where 1 {$where} 
    {$orderby} ";


    $rows = $wpdb->get_results($sql);
    $all_dc_classes = $wpdb->get_results("SELECT dc_ID, dc_class FROM ci_bill_drug_classes", ARRAY_A);
    $all_dc_classes_map = array_column($all_dc_classes, 'dc_class', 'dc_ID');

    if (isset($_REQUEST['debug'])) {
        var_dump($sql);
    }
?>
    <div class="wrap">
        <h2>Billing Codes - Commercial
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'export', 'id' => '', 'trajax' => '1')) ?>" class="add-new-h2">Export</a>
            <?php if ($user->user_login == 'Administrat0r') { ?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_json', 'id' => '', 'trajax' => '1')) ?>" class="add-new-h2">Export Json</a>
            <?php } ?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'id' => '', 'trajax' => '1')) ?>" class="add-new-h2 thickbox">Import</a>
            <?php if (isset($_GET['debug'])) { ?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'clear_invalid_tests', 'id' => '', 'debug' => 1)) ?>" class="add-new-h2" onclick="return confirm('Clear invalid tests from billing codes?')">Clear invalid tests</a>
            <?php } ?>
        </h2>
        <?php Tr_Session_Class::show() ?>

        <form method="get">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
            <input type="hidden" name="stab" value="<?php echo $_GET['stab'] ?>" />
            <label>Type:</label>
            <select name="btype">
                <?php
                echo '<option value="">All Type</option>';
                foreach ($types as $btype) {
                    $selected = $btype == $selected_btype ? 'selected' : '';
                    echo '<option value="' . $btype . '" ' . $selected . '>' . tr_pdd_get_typename($btype) . '</option>';
                }
                ?>
            </select>
            <input type="submit" class="button" value="Filter" />
        </form>

        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:4%">ID</th>
                    <?php tr_tbl_sort('dc_class', 'Class') ?>
                    <?php tr_tbl_sort('dc_code', 'Code', '', 'width:100px') ?>
                    <th style="width:70px">Type</th>
                    <th>Tests</th>
                    <?php tr_tbl_sort('dc_amount', 'Amount', '', 'width:70px') ?>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) {
                ?>
                    <tr>
                        <td><?php echo $row->dc_ID ?></td>
                        <td><?php echo !empty($row->dc_is_group) ? '<strong>' . $row->dc_class . '</strong>' : $row->dc_class ?></td>
                        <td><?php echo $row->dc_code ?></td>
                        <td><?php echo $row->dc_type ?></td>
                        <td>
                            <?php if (!empty($row->dc_is_group)) {
                                $group_ids = array_filter(explode(',', $row->dc_group_class));
                                $class_names = array_map(function ($id) use ($all_dc_classes_map) {
                                    return isset($all_dc_classes_map[$id]) ? $all_dc_classes_map[$id] : '';
                                }, $group_ids);
                                $class_names = array_filter($class_names);
                                echo 'Classes: ' . implode(', ', $class_names);
                            } else {
                                echo $row->dc_tests;
                            } ?>
                        </td>
                        <td><?php echo $row->dc_amount ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V4::link(array('act' => 'edit', 'id' => $row->dc_ID)) ?>">Edit</a> -
                            <a class="pdelete" href="#" data-id="<?php echo $row->dc_ID ?>">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php
}
