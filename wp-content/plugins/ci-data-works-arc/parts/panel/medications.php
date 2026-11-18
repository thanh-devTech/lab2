<?php
global $wpdb;

$tes_type  = !empty($tab) ? $tab : 'tox';
if ($tes_type == 'dru') $tes_type = 'tox';


if ($act == 'export_medications') {
    $lab_name = CI_Base_Lab::get_current_lab_name();
    $exported_at = date_i18n('Y-m-d H:i:s');
    $filename = sanitize_file_name('medications_' . CI_Base_Lab::get_current_lab_init() . '_' . date_i18n('Y-m-d_H-i-s') . '.json');
    header('Content-disposition: attachment; filename=' . $filename);
    header('Content-type: application/json');
    $json = array();
    $json['lab_name'] = $lab_name;
    $json['exported_at'] = $exported_at;
    $json['medications'] = $wpdb->get_results("select * from ci_medications where me_btype = '{$tes_type}'", ARRAY_A);
    $json['drug_logic'] = $wpdb->get_results("select * from ci_drug_logic ", ARRAY_A);
    echo json_encode($json);
    exit;
} else if ($act == 'import_medications') {
    if (isset($_FILES['jsonfile'])) {
        $data = file_get_contents($_FILES['jsonfile']['tmp_name']);
        $json = json_decode($data, true);


        $tests_importIds = array();
        foreach ($json['medications'] as $test) {
            $existid = $wpdb->get_var("select me_ID from ci_medications 
			                                where  me_name = '{$test['me_name']}' and me_btype = '{$tes_type}'");
            $test_data = $test;
            unset($test_data['me_ID']);

            if ($existid == 0) {
                $wpdb->insert('ci_medications', $test_data);
                echo $wpdb->last_error;
                $existid = $wpdb->insert_id;
            } else {
                $wpdb->update('ci_medications', $test_data, array('me_ID' => $existid));
            }

            $tests_importIds[$test['me_ID']] = $existid;
        }

        foreach ($json['drug_logic'] as $test) {
            $existid = $wpdb->get_var("select me_name from ci_drug_logic 
			                                where  medication = '{$test['medication']}' 
			                                 and anticipated = '{$test['anticipated']}' ");
            $test_data = $test;
            unset($test_data['id']);

            if ($existid == 0) {
                $wpdb->insert('ci_drug_logic', $test_data);
                echo $wpdb->last_error;
                $existid = $wpdb->insert_id;
            } else {
                $wpdb->update('ci_drug_logic', $test_data, array('id' => $existid));
            }

            $tests_importIds[$test['id']] = $existid;
        }



        echo 'Imported successfully!';
    }
?>
    <div class="wrap">
        <form method="post" enctype="multipart/form-data" style="margin:50px;text-align: center">
            <input type="file" name="jsonfile" />
            <input type="submit" class="button" value="Import" />
        </form>
    </div>
<?php
} else if ($act == 'edit' || $act == 'add') {

    if (isset($_POST['drug'])) {
        $drug = $_POST['drug'];
        $me_id = $drug['me_ID'];

        $drug['me_show_req'] = isset($drug['me_show_req']) ? 1 : 0;
        $drug['me_btype'] = $tes_type;

        $medication = $drug['me_name'];

        if (!empty($drug['new_group'])) {
            $g_exists = $wpdb->get_var("select mg_ID from ci_medication_groups where mg_name = '{$drug['new_group']}'");
            if ($g_exists > 0) {
                $drug['me_group_id'] = $g_exists;
            } else {
                $wpdb->insert('ci_medication_groups', array('mg_name' => $drug['new_group'], 'mg_btype' => $tes_type));
                $drug['me_group_id'] = $wpdb->insert_id;
            }
        }
        unset($drug['new_group']);


        if (isset($_POST['drug_logic'])) {
            foreach ($_POST['drug_logic'] as $i => $dl) {
                $dl['medication'] = $medication;
                $dl['anticipated'] = strtoupper($dl['anticipated']);

                if (empty($dl['anticipated'])) {
                    continue;
                }
                if (empty($dl['detected']) && empty($dl['detected_oral']) && empty($dl['comment'])) {

                    // $wpdb->delete('ci_drug_logic', array('medication' => $medication, 'anticipated' => $dl['anticipated']));
                    // continue;
                }

                //check >
                $d_g = $dl['anticipated'] . ' > Cutoff';
                $exits = $wpdb->get_var("select id from ci_drug_logic WHERE medication='{$medication}' AND anticipated ='{$dl['anticipated']}' AND concent = '{$d_g}' ");
                if ($exits) {
                    $wpdb->update('ci_drug_logic', $dl, array('id' => $exits));
                } else {
                    $new = $dl;
                    $new['concent'] = $d_g;
                    $wpdb->insert('ci_drug_logic', $new);
                }

                //check <
                $d_g = $dl['anticipated'] . ' < Cutoff';
                $exits = $wpdb->get_var("select id from ci_drug_logic WHERE medication='{$medication}' AND anticipated ='{$dl['anticipated']}' AND concent = '{$d_g}' ");
                if ($exits) {
                    $wpdb->update('ci_drug_logic', $dl, array('id' => $exits));
                } else {
                    $new = $dl;
                    $new['concent'] = $d_g;
                    $wpdb->insert('ci_drug_logic', $new);
                }
            }
        }

        if ($me_id > 0) {
            $rs = $wpdb->update('ci_medications', $drug, array('me_ID' => $me_id));
            if ($rs === false) {
                Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
            } else {
                Tr_Session_Class::add('Updated Drug Successfully!');
            }
        } else {
            if (!empty($drug['me_names'])) {
                $names = explode("\n", $drug['me_names']);

                unset($drug['me_names']);
                foreach ($names as $name) {
                    $name = trim($name);
                    if (empty($name)) continue;
                    $drug['me_name'] = $name;
                    $wpdb->insert('ci_medications', $drug);
                }
            } else {
                $wpdb->insert('ci_medications', $drug);
            }

            $me_id = $wpdb->insert_id;
            if ($me_id > 0) {
                Tr_Session_Class::add('Created New drug Successfully!');
                $drug = array();
            } else {
                Tr_Session_Class::add_error('Error.' . $wpdb->last_error);
            }
            $_POST = array();
        }
    }

    if ($_REQUEST['id'] > 0) {
        $id = $_REQUEST['id'];
        $drug = $wpdb->get_row("select * from ci_medications where me_ID = {$id}", ARRAY_A);
    }

    $drug_logics = $wpdb->get_results("
            select dl.*,cu.cu_cutoff from ci_drug_logic dl 
            JOIN ci_test_panels te ON te.te_name = dl.anticipated
            JOIN ci_test_panels_cutoffs cu ON cu.cu_te_ID = te.te_ID
                WHERE dl.medication = '{$drug['me_name']}' 
                GROUP BY dl.medication,dl.anticipated", ARRAY_A);
    if (isset($_GET['debug'])) {
        var_dump($drug_logics);
    }

    $groups = $wpdb->get_results("select * from ci_medication_groups where mg_btype='{$tes_type}'", ARRAY_A);

    $tests = $wpdb->get_results("select te.te_name,cu.cu_cutoff 
          from ci_test_panels  te
          JOIN ci_test_panels_cutoffs cu ON cu.cu_te_ID = te.te_ID
          GROUP BY te.te_name ORDER BY te.te_name");
?>
    <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> drug <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Drugs</a>
        <?php if ($act == 'edit'): ?><a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a><?php endif; ?>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <label>Medicine Name</label>
                <?php if ($_REQUEST['debug'] && $act == 'add') { ?>
                    <textarea name="drug[me_names]" required style="height: 200px;"><?php echo $drug['me_names'] ?></textarea>
                    <i>Add multi drugs by multi line</i>
                <?php } else { ?>
                    <input type="text" name="drug[me_name]" value="<?php echo $drug['me_name'] ?>" required />
                <?php } ?>
            </div>
            <div class="col2">
                <label style="display:block">&nbsp;</label>
                <label>
                    <input type="checkbox" name="drug[me_show_req]" value="1" <?php checked($drug['me_show_req']) ?> />
                    Show in Forms?
                </label>
            </div>
        </div>


        <?php if ($tes_type == 'tox') { ?>
            <div class="frow blabel">
                <div class="col6">
                    <h4>Test</h4>
                </div>
                <div class="col6">
                    <h4>Detection Window - Urine</h4>
                </div>
                <div class="col6">
                    <h4>Detection Window - Oral </h4>
                </div>
                <div class="col3">
                    <h4>Comment</h4>
                </div>
            </div>
            <div class="frow" id="doctors_dv" style="list-style: none">
                <?php
                $max_j = count($drug_logics) > 0 ? count($drug_logics) : 1;
                for ($j = 0; $j < $max_j; $j++): ?>
                    <li class="frow blabel pdoctorrow <?php if ($j == 0) echo 'default' ?>">
                        <div class="col6">
                            <select class="anticipated js_select2" name="drug_logic[<?php echo $j ?>][anticipated]">
                                <option value="">Select Test</option>
                                <?php
                                foreach ($tests as $t) {
                                    $t->te_name = strtoupper($t->te_name);
                                    $selected = $t->te_name == $drug_logics[$j]['anticipated'] ? 'selected' : '';
                                    echo '<option value="' . $t->te_name . '" ' . $selected . ' data-cut="' . $t->cu_cutoff . '">' . $t->te_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col6">
                            <input type="text" name="drug_logic[<?php echo $j ?>][detected]" value="<?php echo $drug_logics[$j]['detected'] ?>" />
                        </div>
                        <div class="col6">
                            <input type="text" name="drug_logic[<?php echo $j ?>][detected_oral]" value="<?php echo $drug_logics[$j]['detected_oral'] ?>" />
                        </div>
                        <div class="col3">
                            <textarea name="drug_logic[<?php echo $j ?>][comment]"><?php echo $drug_logics[$j]['comment'] ?></textarea>
                        </div>
                        <div class="col6">
                            <a class="button rm_doctor_row"> - </a>
                        </div>
                    </li>
                <?php endfor; ?>
            </div>
            <div class="frow" style="text-align: right;margin-right:2%">
                <a class="button add_doctor_row">+</a>
            </div>
        <?php
        } else {
        ?>
            <div class="frow">
                <div class="col2">
                    <label>Code</label>
                    <input type="text" name="drug[me_code]" value="<?php echo $drug['me_code'] ?>" />
                </div>
            </div>
            <div class="frow">
                <div class="col2">
                    <label>Group</label>
                    <select name="drug[me_group_id]">
                        <option value="">Select Group</option>
                        <?php
                        foreach ($groups as $group) {
                            $selected = $group['mg_ID'] == $drug['me_group_id'] ? 'selected="selected"' : '';
                            echo '<option value="' . $group['mg_ID'] . '" ' . $selected . '>' . $group['mg_name'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col2">
                    <label>or New Group</label>
                    <input type="text" name="drug[new_group]" value="<?php echo $drug['new_group'] ?>" />
                </div>
            </div>
        <?php
        }
        ?>

        <div class="frow">
            <input type="submit" class="button-primary" value="Submit" />
            <input type="hidden" name="drug[me_ID]" value="<?php echo @$drug['me_ID'] ?>" />
        </div>
    </form>
    <script>
        jQuery(function($) {
            $('body').on('change', '.anticipated', function() {
                $(this).parents('li').find('.dlcutoff').val($(this).find('option[value="' + $(this).val() + '"]').data('cut'));
            })
            $('[name="drug[new_group]"]').change(function() {
                if ($(this).val() != '') {
                    $('[name="drug[me_group_id]"]').removeAttr('required');
                } else {
                    $('[name="drug[me_group_id]"]').attr('required', 'required');
                }
            })
        })
    </script>

<?php
} else {

    if (isset($_POST['save_inline_medications'])) {
        check_admin_referer('save_inline_medications');
        $inline_medications = isset($_POST['inline_medications']) && is_array($_POST['inline_medications']) ? $_POST['inline_medications'] : array();
        foreach ($inline_medications as $me_ID => $medication) {
            $wpdb->update(
                'ci_medications',
                array(
                    'me_code' => isset($medication['me_code']) ? sanitize_text_field($medication['me_code']) : '',
                    'me_show_req' => isset($medication['me_show_req']) ? 1 : 0,
                ),
                array(
                    'me_ID' => (int)$me_ID,
                    'me_btype' => $tes_type,
                )
            );
        }
        Tr_Session_Class::add('Updated Medications Successfully!');
    }

    if ($act == 'delete') {
        $me_ID = $_REQUEST['id'];
        $wpdb->delete('ci_medications', array('me_ID' => $me_ID));
        Tr_Session_Class::add('Deleted Drug #' . $me_ID);
    }

    $mes = $wpdb->get_results("select * from ci_medications where me_btype = '{$tes_type}' ORDER BY me_name asc");
    if (isset($_GET['debug'])) {
        var_dump($wpdb->last_query);
    }
?>
    <h2>Medications
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'export_medications', 'id' => '')) ?>" class="add-new-h2">Export</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import_medications', 'id' => '')) ?>" class="add-new-h2">Import</a>
        <span style="font-size:13px;font-weight:normal;margin-left:8px;"><?php echo count($mes) ?> items</span>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <?php wp_nonce_field('save_inline_medications') ?>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl medications-table">
            <thead>
                <tr>
                    <th class="medication-sortable" style="width:50px"># <span class="sort-icon">&#8597;</span></th>
                    <th class="medication-sortable">Name <span class="sort-icon">&#8597;</span></th>
                    <th class="medication-sortable">Code <span class="sort-icon">&#8597;</span></th>
                    <th class="medication-sortable">Show in Form <span class="sort-icon">&#8597;</span></th>
                    <th class="medication-sortable">Actions <span class="sort-icon">&#8597;</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mes as $i => $me):

                ?>
                    <tr>
                        <td><?php echo (int)$me->me_ID ?></td>
                        <td><?php echo esc_html($me->me_name) ?></td>
                        <td><input type="text" name="inline_medications[<?php echo (int)$me->me_ID ?>][me_code]" value="<?php echo esc_attr($me->me_code) ?>" /></td>
                        <td>
                            <input type="checkbox" name="inline_medications[<?php echo (int)$me->me_ID ?>][me_show_req]" value="1" <?php checked($me->me_show_req) ?> />
                        </td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $me->me_ID)) ?>">Edit</a> -
                            <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $me->me_ID)) ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><input type="submit" name="save_inline_medications" class="button-primary" value="Save Changes" /></p>
    </form>
    <script>
        jQuery(function($) {
            $('a.delete').click(function() {
                if (confirm('Do you want delete?') == false) return false;
            })
            $('.medication-sortable').click(function() {
                var $header = $(this);
                var $table = $header.closest('table');
                var column = $header.index();
                var ascending = !$header.data('ascending');
                var rows = $table.find('tbody tr').get();

                rows.sort(function(a, b) {
                    var $a = $(a).children('td').eq(column);
                    var $b = $(b).children('td').eq(column);
                    var aValue = $a.find('input[type="checkbox"]').length ? ($a.find('input[type="checkbox"]').is(':checked') ? '1' : '0') : ($a.find('input[type="text"]').val() || $a.text()).trim();
                    var bValue = $b.find('input[type="checkbox"]').length ? ($b.find('input[type="checkbox"]').is(':checked') ? '1' : '0') : ($b.find('input[type="text"]').val() || $b.text()).trim();

                    if ($.isNumeric(aValue) && $.isNumeric(bValue)) {
                        return ascending ? aValue - bValue : bValue - aValue;
                    }
                    return ascending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                });

                $.each(rows, function(index, row) {
                    $table.children('tbody').append(row);
                });
                $table.find('.sort-icon').html('&#8597;');
                $table.find('.medication-sortable').removeData('ascending');
                $header.data('ascending', ascending).find('.sort-icon').html(ascending ? '&#8593;' : '&#8595;');
            })
        })
    </script>
<?php
}
