<?php
global $wpdb;

$act = isset($act) ? $act : '';
$hide_low_high = false;
$panel_types = array(
    0 => 'Urine Validity',
    1 => 'Screening',
    2 => 'VDX Synthetic Urine'
);

include(TRCPDD_PATH . 'inc/define_instruments.php');

$panel_cols = $wpdb->get_col("show COLUMNS from `ci_urine_validity`");

wp_enqueue_style('admin_table', TRCPDD_URL . 'css/admin_table.min.css', array(), TRCPDD_VS);
$api_methasoft = tr_get_option('ci_pdf_data', 'api_methasoft');
$instruments = array();
if (tr_get_option('ci_pdf_data', 'api_indiko')) {
    $instruments['u_abreviation_indiko'] = 'Indiko+';
}
if (tr_get_option('ci_pdf_data', 'api_au680')) {
    $instruments['u_abreviation_au680'] = 'AU680';
}
if (tr_get_option('ci_pdf_data', 'api_au400')) {
    $instruments['u_abreviation_au400'] = 'AU400';
}
if (tr_get_option('ci_pdf_data', 'api_dxc700')) {
    $instruments['u_abreviation_dxc700'] = 'DxC700';
}

if (tr_get_option('ci_pdf_data', 'ins_diatron_pictus500')) {
    $instruments['u_abreviation_pic500'] = 'Pictus 500';
}
if (tr_get_option('ci_pdf_data', 'ins_diatron_pictus700')) {
    $instruments['u_abreviation_pic700'] = 'Pictus 700';
}

if (tr_get_option('ci_pdf_data', 'api_immtox250')) {
    $instruments['u_abreviation_immtox250'] = 'Immtox250';
}
if (tr_get_option('ci_pdf_data', 'api_mc6410')) {
    $instruments['u_abreviation_mc6410'] = 'MC6410';
}
if (tr_get_option('ci_pdf_data', 'api_instrument_architect')) {
    $instruments['u_abreviation_architect'] = 'Architect';
}
if (tr_get_option('ci_pdf_data', 'api_immtox250')) {
    $instruments['u_abreviation_immtox250'] = 'Immtox250';
}

foreach ($define_instruments as $ins => $iarr) {
    if (tr_get_option('ci_pdf_data', $ins) && isset($iarr['add_fields']) && is_array($iarr['add_fields'])) {
        foreach ($iarr['add_fields'] as $tf) {
            if (strpos($tf, 'u_') === 0) {
                $instruments[$tf] = $iarr['name'];
            }
        }
    }
}

foreach ($instruments as $col => $name) {
    if (!in_array($col, $panel_cols)) {
        $wpdb->query("ALTER TABLE `ci_urine_validity` ADD {$col} text;");
    }
}

if (!in_array('u_related_medications', $panel_cols)) {
    $wpdb->query("ALTER TABLE `ci_urine_validity` ADD u_related_medications text;");
    $panel_cols[] = 'u_related_medications';
}


if (isset($_REQUEST['save_urine_v'])) {
    ob_clean();
    $id     = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
    $col    = trim($_REQUEST['col']);
    $vl     = trim($_REQUEST['val']);
    $return = array('status' => 'ok');

    $rs = $wpdb->update('ci_urine_validity', array($col => $vl), array('u_ID' => $id));

    if ($rs === false) {
        echo '<div class="msg">Error please try againt!</div>';
    } else {
        echo '<div class="ok">updated success</div>';
    }

    exit;
} else if (empty($act)) {
    //fix clinic reflex
    $active_clinics = $wpdb->get_col("select cID from ci_clinic where cli_status = 1");
    if (count($active_clinics) > 0) {
        $rs = $wpdb->get_results("select u_ID, u_reflex_clinics from ci_urine_validity 
        where u_reflex_clinics !='' and u_reflex_clinics is not null");
        foreach ($rs as $r) {
            $clids = explode(',', $r->u_reflex_clinics);
            $has_change = false;
            foreach ($clids as $i => $cid) {
                if (!in_array($cid, $active_clinics)) {
                    unset($clids[$i]);
                    $has_change = true;
                }
            }
            if ($has_change) {
                $u_reflex_clinics = implode(',', $clids);
                $wpdb->update(
                    'ci_urine_validity',
                    array('u_reflex_clinics' => $u_reflex_clinics),
                    array('u_ID' => $r->u_ID)
                );
            }
        }
    }
} else if ($act == 'methasoftids') {
    //u_methasoft_ids
    $id = $_REQUEST['id'];
    $test = $wpdb->get_row("select * from ci_urine_validity where u_ID = {$id}", ARRAY_A);

    $codes = json_decode($test['u_methasoft_ids'], true);

    $clinics_rs = $wpdb->get_results("select cID, cli_clinic from ci_clinic order by cli_clinic asc");
    $clinics = array('' => 'select');
    foreach ($clinics_rs as $r) {
        $clinics[$r->cID] = $r->cli_clinic;
    }
    $data = array();
    foreach ($codes as $cid => $code) {
        $data[] = array('clinic' => $cid, 'code' => $code);
    }
    $tableconfig = array(
        'table' => '',
        //'limit' => 10,
        //'width' => '3000',
        //'stickyfc' => true,//sticky first column
        'url' => $_SERVER['REQUEST_URI'],
        'save_all' => true,
        'new_row' => true,
        'columns' => array(
            'clinic' => array('type' => 'select2', 'label' => 'Clinic', 'options' => $clinics, 'required' => true),
            'code' => array('type' => 'text', 'label' => 'Code')
        ),
        'data' => $data,
        // 'actions' => array('export' => 'Export')
    );



    if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'save_table') {
        $return = array('status' => 'ok');

        $data = is_array($_REQUEST['data']) ? $_REQUEST['data'] : json_decode(stripslashes($_REQUEST['data']), true);
        $ids = array();
        foreach ($data as $k => $vl) {
            if (!empty($vl['code']) && !empty($vl['clinic'])) {
                $ids[$vl['clinic']] = $vl['code'];
            }
        }

        $wpdb->update('ci_urine_validity', array(
            'u_methasoft_ids' => json_encode($ids)
        ), array('u_ID' => $id));

        wp_send_json($return);
    }
?>
    <div class="wrap">
        <h2><?php echo $test['u_name'] ?></h2>

        <div id="table_show"></div>
    </div>
    <script src="<?php echo TRCPDD_URL . 'js/admin_table.js?v=' . time() ?>"></script>
    <script>
        var $tableconfig = <?php echo json_encode($tableconfig) ?>;
        new Tr_Table('table_show', $tableconfig);
    </script>
<?php
    exit;
} else if ($act == 'importmethasoft') {
    if (isset($_POST['codes']) && $_POST['clinic'] > 0) {
        $cid = $_POST['clinic'];
        $codes = $_POST['codes'];
        $lines = explode("\r", $codes);
        foreach ($lines as $line) {
            $line = trim($line);
            $line = str_replace("\t", ' ', $line);
            list($code, $testname) = explode(' ', $line, 2);
            $code = trim($code);
            $testname = trim($testname);
            if (empty($code) || empty($testname)) {
                continue;
            }
            $test = $wpdb->get_row("select * from ci_urine_validity where u_name = '{$testname}' ", ARRAY_A);
            if ($test) {
                $codesold = json_decode($test['u_methasoft_ids'], true);
                unset($codesold['']);
                foreach ($codesold as $c => $arrc) {
                    if (is_array($arrc)) {
                        unset($codesold[$c]);
                    }
                }
                $codesold[$cid] = $code;
                $wpdb->update('ci_urine_validity', array('u_methasoft_ids' => json_encode($codesold)), array('u_ID' => $test['u_ID']));
                echo $testname . ' => ' . $code . '<br>';
            }
        }
        echo 'done';
    }
    $clinics_rs = $wpdb->get_results("select cID, cli_clinic from ci_clinic order by cli_clinic asc");
?>
    <form method="post" enctype="multipart/form-data">
        <div class="frow">
            <label>Clinic</label>
            <select name="clinic" required class="js_select2">
                <?php
                echo '<option value="">select</option>';
                foreach ($clinics_rs as $r) {
                    echo '<option value="' . $r->cID . '">' . $r->cli_clinic . '</option>';
                }
                ?>
            </select>

        </div>
        <div class="frow">
            <textarea name="codes"></textarea>
        </div>
        <div class="frow">
            <input type="submit" class="button" />
        </div>
    </form>
<?php
}

if ($act == 'do_import_urine') {
    if (isset($_FILES['jsonfile'])) {
        $data = file_get_contents($_FILES['jsonfile']['tmp_name']);
        $json = json_decode($data, true);

        $test_cols = $wpdb->get_col("show COLUMNS from `ci_urine_validity`");

        foreach ($json['urines'] as $row) {
            $test_data = $row;
            foreach ($test_data as $tk => $tv) {
                if (!in_array($tk, $test_cols)) {
                    unset($test_data[$tk]);
                }
            }
            unset($test_data['u_ID']);
            $existid = $wpdb->get_var("select u_ID from ci_urine_validity
            where u_name = '{$test_data['u_name']}' ");
            if ($existid > 0) {
                $wpdb->update('ci_urine_validity', $test_data, array('u_ID' => $existid));
            } else {
                $wpdb->insert('ci_urine_validity', $test_data);
            }
        }
        Tr_Session_Class::add("Imported Successfully!");
    }
    $act = '';
}

if ($act == 'export_urine') {
    $json = array();
    $rows = $wpdb->get_results("select * from ci_urine_validity", ARRAY_A);
    $json['urines'] = $rows;
    header('Content-disposition: attachment; filename=urine_chemistry.json');
    header('Content-type: application/json');
    wp_send_json($json);
} else if ($act == 'import_urine') {
?>
    <form method="post" enctype="multipart/form-data"
        style="margin:20px;border:1px dashed #ddd;text-align: center;padding:20px;">
        <label>Json file: </label><input type="file" name="jsonfile" />
        <input type="submit" class="button" value="Import" />
        <input type="hidden" name="act" value="do_import_urine" />
    </form>
<?php
    exit;
} else if (in_array($act, array('ugroups', 'relationship'))) {

    $tg_type = $act == 'relationship' ? 'urv' : 'scr';

    if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

        $return = array('status' => 'ok');
        $id = $_REQUEST['id'];

        $wpdb->delete('ci_test_groups', array('tg_ID' => $id, 'tg_type' => $tg_type));

        trpdd_log_user(get_current_user_id(), 'rm' . $tg_type, $id);

        ob_clean();
        wp_send_json($return);
        exit;
    } else if (isset($_POST['save'])) {
        $items = array();
        $iorder = count($_POST['settings']) + 10;
        foreach ($_POST['settings'] as $k => $data) {
            $iorder++;
            $data['tg_position'] = $iorder;
            $data['tg_name'] = trim($data['tg_name']);
            $data['tg_type'] = $tg_type;
            $data['tg_comments'] = implode(',', $data['tg_comments']);

            if (stripos($k, 'new_') !== false) {
                $wpdb->insert('ci_test_groups', $data);
            } else {
                $wpdb->update('ci_test_groups', $data, array('tg_ID' => $k));
            }
        }

        trpdd_log_form($tg_type, $_POST['settings']);

        trpdd_log_user(get_current_user_id(), 'save' . $tg_type, '');
        Tr_Session_Class::add('Saved Successfully!');
    }

    $items = $wpdb->get_results("select 
	                    tg_ID as ID, 
	                    tg_name as name,
	                    tg_comments as items 
	            from ci_test_groups 
	            where tg_type = '{$tg_type}'
				order by tg_position asc");
?>
    <div class="wrap">
        <h2><?php echo $tg_type == 'relationship' ? 'Relationship' : 'Screening Groups' ?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
        </h2>
        <?php Tr_Session_Class::show() ?>
        <form method="post">

            <div class="group_type">
                <div class="frow">
                    <div class="col3">
                        <h4>Name</h4>
                    </div>
                    <div class="col2">
                        <h4>Tests</h4>
                    </div>
                    <div class="col7">
                        <h4>Action</h4>
                    </div>
                </div>
                <div id="type_items" data-items="<?php echo esc_attr(json_encode($items)) ?>"></div>
                <a class="button add_item">+</a>
            </div>
            <div class="frow actions" style="padding-top:20px">
                <input type="submit" name="save" value="Save" class="button-primary" />
            </div>
        </form>
    </div>

    <div style="display: none" id="default_item">
        <div class="item frow">
            <div class="col3">
                <input type="text" class="ta_name" />
                <input type="hidden" class="ta_position" />
            </div>
            <div class="col2">
                <select class="ta_tests" multiple>
                    <?php
                    foreach ($panel_types as $panel_typeid => $panel_type_name) {
                        if ($act == 'ugroups' && $panel_typeid != 1) {
                            continue;
                        }
                        $us = $wpdb->get_results("select * from ci_urine_validity 
	                                where u_showinreq = {$panel_typeid} 
	                                and u_status = 1
	                                order by u_name asc ");
                        if (count($us) == 0) continue;
                        echo '<optgroup label="' . $panel_type_name . '">';
                        foreach ($us as $u) {
                            echo '<option value="' . $u->u_ID . '">' . $u->u_name . '</option>';
                        }
                        echo '</optgroup>';
                    }
                    ?>
                </select>
            </div>
            <div class="col7">
                <a class="button remove_item">-</a>
                <span class="dashicons dashicons-menu"></span>
            </div>
        </div>
    </div>

    <style>
        .ui-sortable-handle {
            cursor: move;
        }
    </style>
    <script>
        jQuery(function($) {
            var $trow = 0;

            function add_item($data, $next) {
                var $default_html = $($('#default_item').html());
                var $tid = $data['ID'];
                $trow++;
                if (!$tid) {
                    $tid = 'new_' + $trow;
                }
                var $values = $data['items'] ? $data['items'].split(',') : [];
                $default_html.data('id', $tid);
                $('.ta_name', $default_html).attr('name', 'settings[' + $tid + '][tg_name]').val($data['name']);
                $('.ta_tests', $default_html).attr('name', 'settings[' + $tid + '][tg_comments][]');
                $('.ta_tests option', $default_html).each(function() {
                    if ($values.includes($(this).attr('value'))) {
                        $(this).prop('selected', true);
                    }
                })
                $('#type_items').append($default_html);
                $('.ta_tests', $default_html).select2();
            }

            function init_items() {
                var $type_items = $('#type_items');
                var $items = $type_items.data('items');
                for (var i in $items) {
                    add_item($items[i]);
                }
            }
            $('body').on('click', 'a.add_item', function() {
                    add_item({});
                })
                .on('click', 'a.add_next', function() {
                    var $pr = $(this).parents('.item');
                    add_item({}, $pr);
                })
                .on('click', 'a.remove_item', function() {
                    if (confirm('Do you want remove?')) {
                        var $this = $(this);
                        var $pr = $(this).parents('.item');
                        var $id = $pr.data('id');
                        $this.html('Removing...');
                        $.ajax({
                            type: 'post',
                            dataType: 'json',
                            data: {
                                trajax: 'remove_row',
                                id: $id
                            },
                            success: function(json) {
                                if (json.status == 'ok') {
                                    $pr.remove();
                                } else {
                                    alert('Error. Please try again!');
                                    $this.html('-');
                                }
                            },
                            error: function() {
                                alert('Error. Please try again!');
                                $this.html('-');
                            }
                        })
                    }
                    return false;
                })
                .on('click', '.exportlist', function() {
                    $.ajax({
                        data: {
                            trajax: 'export'
                        },
                        type: 'post',
                        success: function(rs) {
                            $('#listimport').val(rs);
                        }
                    })
                    return false;
                })

            init_items();
            $('#type_items').sortable({
                stop: function(event, ui) {
                    var $pos = 0;
                    $('#type_items input.ta_position').each(function() {
                        $pos++;
                        $(this).val($pos);

                    })
                }
            });
        })
    </script>
<?php
} else if ($act == 'add' || $act == 'edit') {
    if (isset($_POST['panel'])) {
        $panel = tr_pdd_stripslashes_deep($_POST['panel']);

        $panel['u_reflex_tests'] = isset($panel['u_reflex_tests']) ? implode(',', $panel['u_reflex_tests']) : '';
        $panel['u_reflex_clinics'] = isset($panel['u_reflex_clinics']) ? implode(',', $panel['u_reflex_clinics']) : '';
        $panel['u_related_medications'] = isset($panel['u_related_medications']) ? implode(',', $panel['u_related_medications']) : '';
        // Auto-add any missing columns from the submitted form.
        $panel_cols = $wpdb->get_col("show COLUMNS from `ci_urine_validity`");
        foreach ($panel as $key => $val) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                continue;
            }
            if (!in_array($key, $panel_cols)) {
                $wpdb->query("ALTER TABLE `ci_urine_validity` ADD COLUMN `{$key}` text;");
            }
        }
        $panel_cols = $wpdb->get_col("show COLUMNS from `ci_urine_validity`");
        foreach ($panel as $key => $val) {
            if (!in_array($key, $panel_cols)) {
                unset($panel[$key]);
            }
        }
        $u_id = $panel['u_ID'];

        if ($u_id > 0) {
            $rs = $wpdb->update('ci_urine_validity', $panel, array('u_ID' => $u_id));
            if ($rs === false) {
                Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
            } else {
                Tr_Session_Class::add('Updated Successfully!');
            }
        } else {
            $wpdb->insert('ci_urine_validity', $panel);

            $u_id = $wpdb->insert_id;
            if ($u_id > 0) {
                Tr_Session_Class::add('Created New Successfully!');
                $redirect_list = true;
            } else {
                Tr_Session_Class::add_error('Error.' . $wpdb->last_error);
            }
            $panel = array();
        }

        trpdd_log_form('urine_validity', $panel);
        trpdd_log_user(get_current_user_id(), 'edit_urine_validity', 'Edit #' . $u_id, '', '', '', '', $u_id);
    }


    if ($_REQUEST['id'] > 0) {
        $id = $_REQUEST['id'];
        $panel = $wpdb->get_row("select * from ci_urine_validity where u_ID = {$id}", ARRAY_A);
    } else {
        $panel['u_start'] = date_i18n('Y-m-d');
        $panel['u_reflex_pos_not_medication'] = 1;
    }
    $ref_labs = CI_Base_Lab::get_ref_labs();

    if (isset($_GET['auto_reflex_medications']) && empty($panel['u_related_medications'])) {
        //auto put medications from tests to u_related_medications
        $related_medications = array();
        $test_ids = $wpdb->get_col("select te_ID from ci_test_panels where FIND_IN_SET(te_name, '{$panel['u_reflex_tests']}') > 0");
        $test_ids_str = implode(',', $test_ids);
        $drug_logics = $wpdb->get_col("
                        select dl.medication
                        from ci_drug_logic dl 
                        JOIN ci_test_panels te ON te.te_name = dl.anticipated
                        left join ci_medications me ON me.me_name = dl.medication
                            WHERE te.te_ID in ({$test_ids_str})
                            GROUP BY dl.medication,dl.anticipated");
        $drug_logics = array_unique(array_filter($drug_logics));
        if (count($drug_logics) > 0) {
            $wpdb->update('ci_urine_validity', array('u_related_medications' => implode(',', $drug_logics)), array('u_ID' => $id));
            $panel['u_related_medications'] = implode(',', $drug_logics);
            Tr_Session_Class::add('Related medications updated based on reflex tests!');
        }
    }
?>
    <div class="wrap">
        <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All</a>
            <?php if ($act == 'edit') : ?><a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a><?php endif; ?>
        </h2>

        <?php Tr_Session_Class::show() ?>
        <form method="post">
            <div class="frow">
                <div class="col2">
                    <label>Name</label>
                    <input type="text" name="panel[u_name]" value="<?php echo $panel['u_name'] ?>" required maxlength="200" />
                </div>
                <div class="col4">
                    <label>Abreviation</label>
                    <input type="text" name="panel[u_abreviation]" value="<?php echo $panel['u_abreviation'] ?>" />
                </div>
                <div class="col4">
                    <label>Loinc Code</label>
                    <input type="text" name="panel[u_loinc_code]" value="<?php echo $panel['u_loinc_code'] ?>" maxlength="30" />
                </div>
            </div>
            <div class="frow">
                <?php
                foreach ($instruments as $ik => $itt) {
                ?>
                    <div class="col4">
                        <label><?php echo $itt . ' code' ?></label>
                        <input type="text" name="panel[<?php echo $ik ?>]" value="<?php echo $panel[$ik] ?>" />
                    </div>
                <?php
                }
                ?>
            </div>
            <?php if (!$hide_low_high) { ?>
                <div class="frow">
                    <div class="col2">
                        <label>Low</label>
                        <input type="number" name="panel[u_low]" value="<?php echo $panel['u_low'] ?>" maxlength="15" step="0.0001" />
                    </div>
                    <div class="col2">
                        <label>High</label>
                        <input type="number" name="panel[u_high]" value="<?php echo $panel['u_high'] ?>" maxlength="15" step="0.0001" />
                    </div>
                </div>
            <?php } ?>

            <div class="frow">
                <div class="col2">
                    <label>Reference Range</label>
                    <input type="text" name="panel[u_range]" value="<?php echo $panel['u_range'] ?>" maxlength="200" />
                </div>
                <div class="col4">
                    <label>Status:</label><br>
                    <label><input type="radio" name="panel[u_status]" value="0" <?php checked($panel['u_status'], 0) ?>" /> Inactive</label>
                    <label><input type="radio" name="panel[u_status]" value="1" <?php checked($panel['u_status'], 1) ?>" /> Active</label>
                </div>

                <div class="col4">
                    <label>Position</label>
                    <input type="number" name="panel[u_position]" value="<?php echo $panel['u_position'] ?>" />
                </div>

            </div>

            <div class="frow">
                <div class="col2">
                    <label>Start Date</label>
                    <input type="text" name="panel[u_start]" value="<?php if ($panel['u_start'] > '1000-01-01') echo $panel['u_start'] ?>" class="hasdate" data-write="1">
                </div>
                <div class="col2">
                    <label>End Date</label>
                    <input type="text" name="panel[u_end]" value="<?php if ($panel['u_end'] > '1000-01-01') echo $panel['u_end'] ?>" class="hasdate" data-write="1">
                </div>

            </div>

            <div class="frow">
                <div class="col2">
                    <label>Only Show for Ref Lab</label>
                    <select name="panel[u_show_for_ref_lab_ID]" class="js_select2" style="max-width:100%">
                        <?php
                        echo '<option value="">Select</option>';
                        foreach ($ref_labs as $rlab) {
                            $selected = $rlab->lab_ID == $panel['u_show_for_ref_lab_ID'] ? 'selected' : '';
                            echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col2">
                    <label>Do Not Show for Ref Lab</label>
                    <select name="panel[u_notshow_for_ref_lab_ID]" class="js_select2" style="max-width:100%">
                        <?php
                        echo '<option value="">Select</option>';
                        foreach ($ref_labs as $rlab) {
                            $selected = $rlab->lab_ID == $panel['u_notshow_for_ref_lab_ID'] ? 'selected' : '';
                            echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="frow">
                <div>
                    <label>Comments</label>
                    <textarea name="panel[u_comments]"><?php echo $panel['u_comments'] ?></textarea>
                </div>

            </div>
            <div class="frow">
                <div class="col2">
                    <input type="hidden" name="panel[u_neg_pos]" value="0" />
                    <label style="margin-right:30px"><input type="checkbox" name="panel[u_neg_pos]" value="1" <?php checked($panel['u_neg_pos']) ?> />
                        Neg/POS</label>

                    <input type="hidden" name="panel[u_hide_value]" value="0" />
                    <label style="margin-right:30px"><input type="checkbox" name="panel[u_hide_value]" value="1" <?php checked($panel['u_hide_value']) ?> />
                        Hide Value</label>

                    <input type="hidden" name="panel[u_default_blank]" value="0" />
                    <label style="margin-right:30px"><input type="checkbox" name="panel[u_default_blank]" value="1" <?php checked($panel['u_default_blank']) ?> />
                        Default Blank</label>

                    <input type="hidden" name="panel[u_not_auto_checked]" value="0" />
                    <label style="margin-right:30px"><input type="checkbox" name="panel[u_not_auto_checked]" value="1" <?php checked($panel['u_not_auto_checked']) ?> />
                        Not Default</label>
                </div>

                <div class="col2">
                    <label>
                        <input type="radio" required name="panel[u_showinreq]" value="0" <?php checked($panel['u_showinreq'], '0') ?> /> Urine
                    </label>
                    <label>
                        <input type="radio" required name="panel[u_showinreq]" value="1" <?php checked($panel['u_showinreq'], '1') ?> /> Screening
                    </label>
                    <label>
                        <input type="radio" required name="panel[u_showinreq]" value="2" <?php checked($panel['u_showinreq'], '2') ?> /> VDX Synthetic Urine
                    </label>
                </div>

            </div>
            <div class="frow" style="margin-top:30px">
                <div class="col5">
                    <input type="hidden" name="panel[u_reflex]" value="0" title="<?php echo $panel['u_reflex_tests'] ?>" />
                    <label>
                        <input type="checkbox" id="u_reflex" name="panel[u_reflex]" value="1" <?php checked($panel['u_reflex']) ?> /> Reflex</label>
                </div>
                <div class="col3">
                    <input type="hidden" name="panel[u_reflex_pos_not_medication]" value="0" />
                    <label>
                        <input type="checkbox" id="u_reflex" name="panel[u_reflex_pos_not_medication]" value="1" <?php checked($panel['u_reflex_pos_not_medication']) ?> />
                        Reflex POS screening tests without related medications
                    </label>

                </div>
                <div class="col3">
                    <input type="hidden" name="panel[u_reflex_neg_and_medication]" value="0" />
                    <label>
                        <input type="checkbox" id="u_reflex" name="panel[u_reflex_neg_and_medication]" value="1" <?php checked($panel['u_reflex_neg_and_medication']) ?> />
                        Reflex any NEG screening tests if medication related
                    </label>

                </div>
            </div>

            <div class="frow">
                <div class="" id="reflex_div" <?php if (!$panel['u_reflex']) echo 'style="display:none"' ?>>
                    <label>Reflex Tests</label>
                    <select class="js_select2" name="panel[u_reflex_tests][]" multiple class="njs_select2">
                        <?php
                        $panel['u_reflex_tests'] = explode(',', $panel['u_reflex_tests']);

                        $groups = tr_pdd_get_profiles_tests_requesteds('tox', false);
                        foreach ($groups as $group_id => $tests) {
                            $group = tr_pdd_get_panel_groups($group_id);
                            echo '<optgroup label="' . $group['label'] . '">';
                            foreach ($tests as $t) {
                                $key = strtoupper($t->te_name);
                                $selected = in_array($key, $panel['u_reflex_tests']) ? 'selected' : '';
                                echo '<option value="' . $key . '" ' . $selected . '>' . $t->te_name . '</option>';
                            }
                            echo '</optgroup>';
                        }
                        ?>
                    </select>

                    <br>
                    <br>
                    <label>Reflex Clinics</label>
                    <select class="js_select2" name="panel[u_reflex_clinics][]" multiple class="njs_select2">
                        <?php
                        $clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where cli_status=1 order by cli_clinic");
                        $panel['u_reflex_clinics'] = explode(',', $panel['u_reflex_clinics']);
                        foreach ($clinics as $clinic) {
                            $selected = in_array($clinic->cID, $panel['u_reflex_clinics']) ? 'selected' : '';
                            echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                        }
                        ?>
                    </select>

                    <br>
                    <br>
                    <label>Related Medications</label>
                    <select class="js_select2" name="panel[u_related_medications][]" multiple class="njs_select2">
                        <?php
                        $panel['u_related_medications'] = explode(',', $panel['u_related_medications']);
                        $related_medications = $wpdb->get_col("select me_name from ci_medications order by me_name asc");
                        foreach ($related_medications as $medication) {
                            $selected = in_array($medication, $panel['u_related_medications']) ? 'selected' : '';
                            echo '<option value="' . esc_attr($medication) . '" ' . $selected . '>' . htmlspecialchars($medication) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit" />
                <input type="hidden" name="panel[u_ID]" value="<?php echo @$panel['u_ID'] ?>" />
            </div>
        </form>
    </div>
    <style>
        #reflex_div span.select2 {
            width: 100% !important
        }
    </style>
    <script>
        jQuery(function($) {
            $('#u_reflex').change(function() {
                if ($(this).is(':checked')) {
                    $('#reflex_div').show();
                } else {
                    $('#reflex_div').hide();
                }
            }).change();
            $('[name="panel[u_status]"]').change(function() {
                if ($(this).val() == '0' && $(this).is(":checked") && $('[name="panel[u_end]"]').val() == '') {
                    $('[name="panel[u_end]"]').val('<?php echo date_i18n('Y-m-d') ?>');
                }

            })
        })
    </script>
<?php
} else {
    if (isset($_POST['panel'])) {
        update_option('ci_urine_validity_settings', $_POST['panel']);
        Tr_Session_Class::add('Updated Successfully!');
    }

    if ($act == 'delete') {
        $u_id = $_REQUEST['id'];
        $wpdb->delete('ci_urine_validity', array('u_ID' => $u_id));
        Tr_Session_Class::add('Deleted test #' . $u_id);


        trpdd_log_user(get_current_user_id(), 'delete_urine_validity', 'Delete #' . $u_id, '', '', '', '', $u_id);
    }



    $panelst = get_option('ci_urine_validity_settings', array());


?>
    <div class="wrap">
        <h2>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'relationship', 'id' => '')) ?>" class="add-new-h2">Relationship</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'ugroups', 'id' => '')) ?>" class="add-new-h2">Screening Groups</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_urine', 'trajax' => '1')) ?>" class="add-new-h2">Export</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import_urine', 'trajax' => '1')) ?>" class="add-new-h2 thickbox">Import</a>
        </h2>

        <?php Tr_Session_Class::show() ?>

        <div class="wraptbl">
            <table id="tbl_tests" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:30px">#</th>
                        <th style="width:180px">Name</th>
                        <th>Abreviation</th>
                        <?php
                        foreach ($instruments as $tt) {
                            echo '<th>' . $tt . '</th>';
                        }
                        ?>
                        <th>Loinc Code</th>
                        <th style="width:60px">Low</th>
                        <th style="width:60px">High</th>
                        <th>Reference Range</th>
                        <th style="width:80px">Status</th>
                        <th style="width:70px">Position</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($panel_types as $panel_typeid => $panel_type_name) {
                        $us = $wpdb->get_results("select * from ci_urine_validity 
	                                where u_showinreq = {$panel_typeid} 
	                                order by u_status desc,u_position asc, u_ID asc ");
                        if (count($us) == 0) {
                            continue;
                        }
                    ?>
                        <tr class="headtt">
                            <th colspan="<?php echo count($instruments) + 10 ?>">
                                <h4><?php echo $panel_type_name ?></h4>
                            </th>
                        </tr>
                        <?php
                        foreach ($us as $i => $u) {
                        ?>
                            <tr data-id="<?php echo $u->u_ID ?>">
                                <td><?php echo $u->u_ID ?></td>
                                <td>
                                    <a href="<?php Tr_Base_Class_V6::link(array(
                                                    'act' => 'edit',
                                                    'id'  => $u->u_ID
                                                )) ?>"><?php echo $u->u_name ?></a>
                                </td>
                                <td class="editable" title="click to edit" data-k="u_abreviation"><?php echo $u->u_abreviation ?></td>
                                <?php
                                foreach ($instruments as $ik => $itt) {
                                    echo '<td class="editable" title="click to edit"
                                data-k="' . $ik . '">' . $u->{$ik} . '</td>';
                                }
                                ?>
                                <td class="editable" title="click to edit" data-k="u_loinc_code"><?php echo $u->u_loinc_code ?></td>
                                <td><?php echo $u->u_low ?></td>
                                <td><?php echo $u->u_high ?></td>
                                <td><?php echo $u->u_range ?></td>
                                <td class="status_col" data-status="<?php echo $u->u_status ?>"></td>
                                <td class="editable center" data-k="u_position"><?php echo $u->u_position ?></td>
                                <td>
                                    <a href="<?php Tr_Base_Class_V6::link(array(
                                                    'act' => 'edit',
                                                    'id'  => $u->u_ID
                                                )) ?>">Edit</a> -
                                    <?php
                                    if ($api_methasoft) {
                                        echo '<a class="thickbox" href="' . Tr_Base_Class_V4::link(array(
                                            'act' => 'methasoftids',
                                            'id'  => $u->u_ID,
                                            'trajax' => 1
                                        ), false) . '">Methasoft IDs</a> - ';
                                    }
                                    ?>
                                    <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $u->u_ID)) ?>">Delete</a>
                                </td>
                            </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
            </table>

        </div>


        <h2>Settings</h2>
        <form method="post">


            <?php
            if (defined('TRPDD_ARK')) {
            ?>
                <div class="frow">
                    <div class="col4">
                        <input type="hidden" name="panel[manually_screening]" value="0" />
                        <label> <input type="checkbox" name="panel[manually_screening]" value="1" <?php checked($panelst['manually_screening']) ?> /> Manually result Screening</label>


                    </div>
                    <div class="col4">
                        <input type="hidden" name="panel[paper_strip]" value="0" />
                        <label>
                            <input type="checkbox" name="panel[paper_strip]" value="1" <?php checked($panelst['paper_strip']) ?> /> Hide Value Urine Validity (PDF)</label>

                    </div>
                    <div class="col4">
                        <input type="hidden" name="panel[hidevlscre]" value="0" />
                        <label>
                            <input type="checkbox" name="panel[hidevlscre]" value="1" <?php checked($panelst['hidevlscre']) ?> /> Hide value in screening</label>

                    </div>
                    <div class="col4">
                        <input type="hidden" name="panel[vdx]" value="0" />
                        <label>
                            <input type="checkbox" name="panel[vdx]" value="1" <?php checked($panelst['vdx']) ?> /> VDX Synthetic Urine</label>
                    </div>

                </div>
                <div class="frow">
                    <div class="col4">
                        <input type="hidden" name="panel[screenanegreflex]" value="0" />
                        <label><input type="checkbox" name="panel[screenanegreflex]" value="1" <?php checked($panelst['screenanegreflex']) ?> /> Reflex Screening (all neg) to Comprehensive panel </label>


                    </div>
                    <idv class="col4">
                        <label>Screening Group Code</label>
                        <input type="text" name="panel[screeningcode]" value="<?php echo $panelst['screeningcode'] ?>" />
                    </idv>
                </div>
            <?php
            } else { ?>

                <div class="frow">
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[hide_value]" value="0" />
                            <input type="checkbox" name="panel[hide_value]" value="1" <?php checked(isset($panelst['hide_value']) ? $panelst['hide_value'] : '') ?> />
                            Hide Value Urine Validity (PDF)</label>
                    </div>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[hide_cutoff]" value="0" />
                            <input type="checkbox" name="panel[hide_cutoff]" value="1" <?php checked(isset($panelst['hide_cutoff']) ? $panelst['hide_cutoff'] : '') ?> />
                            Hide cutoff Urine Validity(PDF)</label>
                    </div>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[hide_tbl_border]" value="0" />
                            <input type="checkbox" name="panel[hide_tbl_border]" value="1" <?php checked(isset($panelst['hide_tbl_border']) ? $panelst['hide_tbl_border'] : '') ?> />
                            Hide table border(PDF)</label>
                    </div>


                </div>

                <br>

                <div class="frow">
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[hide_value_screening]" value="0" />
                            <input type="checkbox" name="panel[hide_value_screening]" value="1" <?php checked(isset($panelst['hide_value_screening']) ? $panelst['hide_value_screening'] : '') ?> />
                            Hide Value Screening(PDF)</label>
                    </div>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[hide_cutoff_screening]" value="0" />
                            <input type="checkbox" name="panel[hide_cutoff_screening]" value="1" <?php checked(isset($panelst['hide_cutoff_screening']) ? $panelst['hide_cutoff_screening'] : '') ?> />
                            Hide cutoff Screening(PDF)</label>
                    </div>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[manual_edit_screening]" value="0" />
                            <input type="checkbox" name="panel[manual_edit_screening]" value="1" <?php checked(isset($panelst['manual_edit_screening']) ? $panelst['manual_edit_screening'] : '') ?> />
                            Manual edit Screening</label>
                    </div>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="panel[vdx]" value="0" />
                            <input type="checkbox" name="panel[vdx]" value="1" <?php checked(isset($panelst['vdx']) ? $panelst['vdx'] : '') ?> />
                            VDX Synthetic Urine</label>
                    </div>

                </div>
            <?php } ?>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit" />
            </div>
        </form>

    </div>
    <script>
        jQuery(function($) {
            $('a.delete').click(function() {
                if (confirm('Do you want delete?') == false) return false;
            })
        })
    </script>
<?php
}
?>


<style>
    .notfortoxreq {
        background: #ccc;
    }
</style>
<script>
    jQuery(function($) {
        var $statuses = {
            1: 'Active',
            0: 'Inactive'
        };
        $('#tbl_tests td.status_col').each(function() {
            var $html = '<select>';
            for (k in $statuses) {
                $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
            }
            $html += '</select>';
            $(this).html($html);
            if ($(this).data('status') == '0') {
                $(this).parents('tr').addClass('row_inactive');
            }
        })
        $('.status_col select').change(function() {
            var $this = $(this);
            var $pr = $(this).parent();
            $pr.find('.sloading').remove();
            $pr.append('<div class="sloading"></div>');
            var $id = $pr.parents('tr').data('id');
            var $vl = $(this).val();
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: {
                    tr_action: 'ci_change_urine_validity_status',
                    id: $id,
                    status: $vl
                },
                success: function(rs) {
                    $pr.find('.sloading').remove();
                    if (rs.status == 'ok') {
                        if ($vl == '0') {
                            $pr.parents('tr').addClass('row_inactive');
                        } else {
                            $pr.parents('tr').removeClass('row_inactive');
                        }
                    } else {
                        $this.change();
                    }
                },
                error: function() {
                    $this.change();
                }
            })
        });

        function save_data($this) {
            var $pr = $this.parent();
            var $tr = $pr.parent();
            var $vl = $this.val();
            var $col = $pr.data('k');
            var $id = $tr.data('id');

            $pr.append('<div class="sloading"></div>');
            $.ajax({
                data: {
                    save_urine_v: 1,
                    trajax: 1,
                    col: $col,
                    id: $id,
                    val: $vl
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    if (rs.indexOf("updated success") > -1) {
                        $pr.data('editing', false);
                        $pr.html($vl);
                    } else {
                        alert($html.find('.msg').html());
                    }
                    $('.sloading', $pr).remove();
                }
            });
        }

        $('td.editable').click(function() {
            var $this = $(this);
            if (!$this.data('editing')) {
                $this.data('value', $this.html());
                if ($this.hasClass('textarea')) {
                    $this.html('<textarea>' + $this.html() + '</textarea>');
                } else {
                    $this.html('<input type="text" value="' + $this.html() + '"/>');
                }

                $this.data('editing', true);
                $('input,textarea', $this).focus();
            }
            return false;
        });
        $('body').on('keypress', 'td.editable input', function(e) {
                var code = e.keyCode || e.which;
                if (code == 13) {
                    save_data($(this));

                    return false;
                }
            })
            .on('blur', 'td.editable textarea', function() {
                save_data($(this));
            })
            .on('focusout', 'td.editable input', function(e) {
                var $pr = $(this).parent();
                var $vl = $(this).val();
                var $oldvl = $pr.data('value');
                if ($vl != $oldvl) {
                    save_data($(this));
                } else {
                    $pr.data('editing', false);
                    $pr.html($vl);
                }
            })
    })
</script>