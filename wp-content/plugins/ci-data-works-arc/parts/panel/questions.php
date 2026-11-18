<?php
global $wpdb;


$tes_type   = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? $_REQUEST['type'] : $tab;

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'save_question') {
    ob_clean();
    $id = $_REQUEST['id'];
    $col = $_REQUEST['col'];
    $vl = trim($_REQUEST['val']);
    $return = array('status' => 'ok');

    $rs = $wpdb->update('ci_test_aoe', array($col => $vl), array('ta_ID' => $id));

    if ($rs === false) {
        echo '<div class="msg">Error please try againt!</div>';
    } else {
        echo '<div class="ok">updated success</div>';
    }

    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'save_question_group') {
    ob_clean();
    $id = $_REQUEST['id'];
    $col = $_REQUEST['col'];
    $vl = trim($_REQUEST['val']);
    $return = array('status' => 'ok');

    $rs = $wpdb->update('ci_test_aoe_headers', array($col => $vl), array('tag_ID' => $id));

    if ($rs === false) {
        echo '<div class="msg">Error please try againt!</div>';
    } else {
        echo '<div class="ok">updated success</div>';
    }

    exit;
} else if ($act == 'export_questions') {
    $type = $_REQUEST['type'];
    ob_clean();
    $headers = $wpdb->get_results("select * from ci_test_aoe_headers where tag_btype='{$type}'", ARRAY_A);
    $questions = $wpdb->get_results("select * from ci_test_aoe where ta_btype= '{$type}' ", ARRAY_A);


    $json = array(
        'headers' => $headers,
        'questions' => $questions,
    );
    header('Content-disposition: attachment; filename=questions_' . $type . '.json');
    header('Content-type: application/json');
    wp_send_json($json);
    exit;
}



if ($act == 'deleteheader') {
    $id = $_POST['tag_ID'];
    $AOE = $wpdb->get_row("select * from ci_test_aoe_headers where tag_ID = {$id} ");
    $rs = $wpdb->delete('ci_test_aoe_headers', array('tag_ID' => $id));

    if ($rs === false) {
        Tr_Session_Class::add('Can\'t delete AOE');
    } else {

        Tr_Session_Class::add('Deleted AOE "' . $AOE->tag_header . '"');
    }
    $act = 'groups';
}
if (isset($_POST['AOE'])) {
    $AOE = tr_pdd_stripslashes_deep($_POST['AOE']);
    $AOE['ta_question'] = trim($AOE['ta_question']);
    $header_value = isset($AOE['ta_header_name_new']) && trim($AOE['ta_header_name_new']) !== ''
        ? $AOE['ta_header_name_new']
        : (isset($AOE['ta_header_id']) ? $AOE['ta_header_id'] : 0);

    if (is_array($header_value)) {
        $header_value = reset($header_value);
    }

    if (is_numeric($header_value)) {
        $AOE['ta_header_id'] = intval($header_value);
    } else {
        $header_name = trim(wp_unslash($header_value));
        if ($header_name === '') {
            $AOE['ta_header_id'] = 0;
        } else {
            $AOE['ta_header_id'] = intval($wpdb->get_var($wpdb->prepare(
                "select tag_ID
                from ci_test_aoe_headers
                where tag_btype = %s
                and tag_header = %s
                limit 1",
                $tes_type,
                $header_name
            )));

            if (empty($AOE['ta_header_id'])) {
                $next_order = intval($wpdb->get_var($wpdb->prepare(
                    "select max(tag_order)
                    from ci_test_aoe_headers
                    where tag_btype = %s",
                    $tes_type
                ))) + 1;

                $wpdb->insert('ci_test_aoe_headers', array(
                    'tag_header'     => $header_name,
                    'tag_btype'      => $tes_type,
                    'tag_order'      => $next_order,
                    'tag_status'     => 1,
                    'tag_created'    => date_i18n('Y-m-d H:i:s'),
                    'tag_created_by' => get_current_user_id(),
                ));

                $AOE['ta_header_id'] = intval($wpdb->insert_id);
            }
        }
    }

    unset($AOE['ta_header_name_new']);
    $AOE['ta_group_ids'] = isset($AOE['ta_group_ids']) ? implode(',', $AOE['ta_group_ids']) : '';

    if (empty($AOE['ta_header_id'])) {
        Tr_Session_Class::add_error('Section is required.');
    } else if ($AOE['ta_ID'] > 0) {
        $rs = $wpdb->update('ci_test_aoe', $AOE, array('ta_ID' => $AOE['ta_ID']));
        if ($rs === false) {
            Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
        } else {
            Tr_Session_Class::add('Updated AOE Successfully!');
        }
    } else {
        $AOE['ta_created'] = date_i18n('Y-m-d H:i:s');
        $AOE['ta_created_by'] = get_current_user_id();

        if (empty($AOE['ta_order'])) {
            $AOE['ta_order'] = $wpdb->get_var("select max(ta_order) 
			from ci_test_aoe where ta_header_id = {$AOE['ta_header_id']}") + 1;
        }

        $rs = $wpdb->insert('ci_test_aoe', $AOE);
        if ($rs === false) {
            Tr_Session_Class::add_error('Error. ' . $wpdb->last_error);
        } else {
            $id = $wpdb->insert_id;
            $edit_url = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id), false);
            Tr_Session_Class::add('Inserted AOE Successfully! <a href="' . $edit_url . '">Edit</a>');
        }
    }
}
if ($act == 'import') {
    if (!empty($_FILES['importfile']['name'])) {
        if (stripos($_FILES['importfile']['name'], '.json') > 0) {

            $data = file_get_contents($_FILES['importfile']['tmp_name']);
            $json = json_decode($data, true);

            $group_importIds = array();
            $tests_importIds = array();

            if (isset($json['headers'])) {
                foreach ($json['headers'] as $group) {

                    $group_id = $wpdb->get_var("select tag_ID from ci_test_aoe_headers 
                                        where tag_btype='{$tes_type}' 
                                        and tag_header='" . addslashes($group['tag_header']) . "'");
                    if ($group_id == 0) {

                        $group_data = array(
                            'tag_header'           => $group['tag_header'],
                            'tag_btype'           => $tes_type,
                            'tag_order'       => $group['tag_order'],
                            'tag_status'       => $group['tag_status'],
                            'tag_created'         => $group['tag_created'],
                            'tag_created_by'         => $group['tag_created_by'],
                        );
                        $rs         = $wpdb->insert('ci_test_aoe_headers', $group_data);
                        echo $wpdb->last_error;
                        $group_id = $wpdb->insert_id;
                    }

                    $group_importIds[$group['tag_ID']] = $group_id;
                }
            }



            if (isset($json['questions'])) {
                foreach ($json['questions'] as $test) {

                    $group_id  = $group_importIds[$test['ta_header_id']];
                    if (empty($group_id)) continue;


                    $test_id   = $wpdb->get_var("select ta_ID from ci_test_aoe 
                                                    where ta_btype='{$tes_type}' 
                                                    and ta_question = '" . addslashes($test['ta_question']) . "' 
                                                    and ta_header_id = '{$group_id}' ");
                    $test_data = $test;
                    unset($test_data['ta_ID']);
                    $test_data['ta_btype']     = $tes_type;
                    $test_data['ta_header_id'] = intval($group_id);
                    if ($test_id == 0) {
                        $wpdb->insert('ci_test_aoe', $test_data);
                        echo $wpdb->last_error;
                        $test_id = $wpdb->insert_id;
                    } else {
                        $wpdb->update('ci_test_aoe', $test_data, array('ta_ID' => $test_id));
                    }

                    $tests_importIds[$test['ta_ID']] = $test_id;
                }
            }

            if (isset($json['pa_questions'])) {
                $groupName = 'Patient Questions';
                $group_id = $wpdb->get_var("select tag_ID from ci_test_aoe_headers 
                where tag_btype='{$tes_type}' 
                and tag_header='" . addslashes($groupName) . "'");
                if ($group_id == 0) {

                    $group_data = array(
                        'tag_header'           => $groupName,
                        'tag_btype'           => $tes_type,
                    );
                    $rs         = $wpdb->insert('ci_test_aoe_headers', $group_data);
                    $group_id = $wpdb->insert_id;
                }
                if (empty($group_id)) {
                    echo 'empty group';
                    exit;
                }
                $ta_order = 0;
                foreach ($json['pa_questions'] as $test) {

                    $ta_order++;
                    $test_id   = $wpdb->get_var("select ta_ID from ci_test_aoe 
                                                    where ta_btype='{$tes_type}' 
                                                    and ta_slug = '" . addslashes($test['key']) . "' 
                                                    and ta_header_id = '{$group_id}' ");
                    $test_data = array(
                        'ta_slug' => $test['key'],
                        'ta_question' => $test['question'],
                        'ta_question_type' => $test['type'],
                        'ta_answers' => $test['options'],
                        'ta_cols' => $test['cols'],
                        'ta_btype' => $tes_type,
                        'ta_header_id' => $group_id,
                        'ta_order' => $ta_order,
                    );

                    if ($test_id == 0) {
                        $wpdb->insert('ci_test_aoe', $test_data);
                        echo $wpdb->last_error;
                        $test_id = $wpdb->insert_id;
                    } else {
                        $wpdb->update('ci_test_aoe', $test_data, array('ta_ID' => $test_id));
                    }

                    $tests_importIds[$test['ta_ID']] = $test_id;
                }
            }
        } else {
            $data = trpdd_get_data($_FILES['importfile']['tmp_name'], '', array('field name'), true);

            $last_group = '';
            foreach ($data['data'] as $row) {
                $group = $row['section header'];
                if (empty($group)) {
                    $group = $last_group;
                }
                $last_group  = $group;
                $key         = $row['field name'];
                $field_title = $row['field label'];

                if (empty($field_title)) {
                    $field_title = $key;
                }

                $key = substr(preg_replace("/[^0-9a-z]/", '', strtolower($key)), 0, 30);

                if (strtolower($key) == 'study') {
                    continue;
                }
                $options = $row['choices, calculations, or slider labels'];
                $type    = strtolower($row['field type']);

                if ($type == 'dropdown') {
                    $type = 'select';
                } else if ($type == 'multi select') {
                    $type = 'mselect';
                } else if ($type == 'radio') {
                } else {
                    $options = '';
                }


                $groupid = $wpdb->get_var("select tag_ID from ci_test_aoe_headers where tag_header ='{$group}'");
                if ($groupid == 0) {
                    $wpdb->insert('ci_test_aoe_headers', array(
                        'tag_header'     => $group,
                        'tag_btype'      => $tes_type,
                        'tag_created'    => date('Y-m-d H:i:s'),
                        'tag_created_by' => get_current_user_id(),
                    ));
                    $groupid = $wpdb->insert_id;
                }

                if ($groupid == 0) {
                    continue;
                }
                $tid    = $wpdb->get_var("select ta_ID from ci_test_aoe where ta_slug = '{$key}'");
                $data_q = array(
                    'ta_slug'          => $key,
                    'ta_question'      => $field_title,
                    'ta_question_type' => $type,
                    'ta_answers'       => $options,
                    'ta_btype'         => $tes_type,
                    'ta_header_id'     => $groupid,
                );
                if ($tid == 0) {
                    $data_q['ta_created']    = date('Y-m-d H:i:s');
                    $data_q['ta_created_by'] = get_current_user_id();
                    $wpdb->insert('ci_test_aoe', $data_q);
                } else {
                    $wpdb->update('ci_test_aoe', $data_q, array('ta_ID' => $tid));
                }
            }
        }
    }
?>
    <div style="text-align: center;width:200px;margin:20px auto">
        <form method="post" enctype="multipart/form-data">
            <label>cvs file: </label>
            <input type="file" class="button" name="importfile" required />
            <br>
            <input type="submit" class="button" value="Import" />
        </form>
    </div>
<?php
} else if ($act == 'addgroup' || $act == 'editgroup') {

    $id = $_REQUEST['id'];
    if ($id > 0) {
        $group = $wpdb->get_row("select * from ci_test_aoe_headers where tag_ID = {$id}", ARRAY_A);
    } else {
        $group = array(
            'tag_status' => '1'
        );
    }
?>
    <form method="post" enctype="multipart/form-data">


        <div class="frow">
            <label>Name</label>
            <input type="text" name="group[tag_header]" value="<?php echo $group['tag_header'] ?>" required />
        </div>

        <div class="frow">
            <label>Status</label>
            <select name="group[tag_status]">
                <?php
                foreach (array('1' => 'Active', '0' => 'Disabled') as $k => $txt) {
                    $selected = $group['tag_status'] == $k ? 'selected' : '';
                    echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                }
                ?>
            </select>
        </div>


        <div class="frow">
            <input type="submit" class="button-primary" value="Submit" />
            <input type="hidden" name="group[tag_ID]" value="<?php echo $group['tag_ID'] ?>" />
            <input type="hidden" name="act" value="groups" />
        </div>
    </form>
<?php
    exit;
} else if ($act == 'groups') {

    if (isset($_POST['group'])) {

        $grouparr = $_POST['group'];
        $grouparr['tag_btype'] = $tes_type;
        if (!empty($grouparr['tag_header'])) {
            if ($grouparr['tag_ID'] > 0) {
                $wpdb->update('ci_test_aoe_headers', $grouparr, array('tag_ID' => $grouparr['tag_ID']));
                Tr_Session_Class::add('Updated Group Successfully!');
            } else {
                $grouparr['tag_created'] = date_i18n('Y-m-d H:i:s');
                $grouparr['tag_created_by'] = get_current_user_id();
                $wpdb->insert('ci_test_aoe_headers', $grouparr);
                Tr_Session_Class::add('Added Group Successfully!');
            }
        }
    }



    $sql = "select *,
	            (select count(*) from ci_test_aoe ta where tag.tag_ID = ta.ta_header_id) as count_tests
            from ci_test_aoe_headers tag 
            where tag.tag_btype = '{$tes_type}'
            order by tag.tag_order asc, tag.tag_ID asc";

    $groups = $wpdb->get_results($sql);
?>
    <h2>Sections
        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'addgroup', 'id' => '')) ?>" class="add-new-h2 thickbox">Add New Section</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New Question</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Questions</a>
    </h2>

    <?php Tr_Session_Class::show() ?>



    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Name</th>
                    <th>Count</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($groups as $i => $group) {

                ?>
                    <tr data-id="<?php echo $group->tag_ID ?>">
                        <td><?php echo $group->tag_ID ?></td>
                        <td class="tag_header"><?php echo $group->tag_header ?></td>
                        <td><?php echo $group->count_tests ?></td>
                        <td class="editable" title="click to edit" data-k="tag_order"><?php echo $group->tag_order ?></td>
                        <td>
                            <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'editgroup', 'id' => $group->tag_ID)) ?>">Edit</a>
                            <?php if ($group->count_tests == 0) { ?>
                                - <a class="delete" href="#">Delete</a>
                            <?php } ?>

                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <style>
        tr.disabled td {
            color: #888 !important;
        }
    </style>
    <script>
        jQuery(function($) {
            $('td.editable').click(function() {
                var $this = $(this);
                if (!$this.data('editing')) {
                    $this.data('value', $this.html());
                    $this.html('<input type="text" value="' + $this.html() + '"/>');
                    $this.data('editing', true);
                    $('input', $this).focus();
                }
            })
            $('body').on('keyup', 'td.editable input', function(e) {
                var code = e.keyCode || e.which;
                var $this = $(this);
                var $pr = $(this).parent();
                var $tr = $pr.parent();

                if (code == 27) {
                    $pr.data('editing', false);
                    $pr.html($pr.data('value'));
                } else if (code == 13) {
                    var $vl = $this.val();
                    var $col = $pr.data('k');
                    var $id = $tr.data('id');

                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        data: {
                            trajax: 'save_question_group',
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
                    return false;
                }
            })
            $('a.delete').click(function() {
                var $pr = $(this).parents('tr');
                var $tgname = $('.tag_header', $pr).html();
                if (confirm('Do you want delete group "' + $tgname + '"?') == false) return false;
                var $tg_ID = $pr.data('id');
                var $f = $('<form method="post"><input type="hidden" name="act" value="deleteheader"/>' +
                    '<input type="hidden" name="tag_ID" value="' + $tg_ID + '"/></form> ');
                $('body').append($f);
                $f.submit();
                return false;
            })
        })
    </script>
<?php
} else if ($act == 'add' || $act == 'edit') {

    $id = $_GET['id'];

    if ($act == 'edit') {
        $AOE = $wpdb->get_row("select * from ci_test_aoe where ta_ID = {$id} ", ARRAY_A);
    } else {
        $AOE = array();
        $AOE['ta_order'] = $wpdb->get_var("select max(ta_order) 
			from ci_test_aoe ") + 1;
    }

    $AOE['ta_group_ids'] = isset($AOE['ta_group_ids']) && !empty($AOE['ta_group_ids']) ? explode(',', $AOE['ta_group_ids']) : array();
    if (empty($AOE['ta_group_ids']) && isset($AOE['ta_group_id'])) {
        $AOE['ta_group_ids'] = array($AOE['ta_group_id']);
    }

    $panels = $wpdb->get_results("select * 
				from ci_test_groups 
				where tg_type='{$tes_type}' and tg_parent = 0
				order by tg_name asc");
    $groups  = $wpdb->get_results("select * from ci_test_aoe_headers where tag_btype='{$tes_type}' order by tag_order asc, tag_ID asc");
?>

    <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Question <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Questions</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'groups', 'id' => '')) ?>" class="add-new-h2">Sections</a>
        <?php if ($act == 'edit') : ?><a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a><?php endif; ?>
    </h2>

    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <label>Key</label>
                    <input type="text" name="AOE[ta_slug]" value="<?php echo $AOE['ta_slug'] ?>" required <?php if (!empty($AOE['ta_slug'])) echo 'readonly' ?> />
                </div>
                <div class="frow">
                    <label>Question</label>
                    <input type="text" name="AOE[ta_question]" value="<?php echo $AOE['ta_question'] ?>" required />
                </div>
                <div class="frow">
                    <label>Type</label>
                    <select name="AOE[ta_question_type]" required>
                        <?php
                        $types = array(
                            'select' => 'Dropdown',
                            'mselect' => 'Dropdown Multi',
                            'text' => 'Text',
                            'textu' => 'Text with Unit',
                            'texta' => 'TextArea',
                            'date' => 'Date',
                            'calc' => 'Calculate',
                            'calc_age' => 'Calculate Age',
                            'cond2' => 'Condition2',
                            'cond4' => 'Condition4',
                            'cond8' => 'Condition8',
                            'radio' => 'Radio',
                            'checkbox' => 'Checkbox',
                            'mcheckbox' => 'Multi Checkbox',
                            'mtext' => 'Multi Text box',
                            'cb2' => 'when checked Yes, Add # and Date if possible',
                        );
                        foreach ($types as $k => $vl) {
                            $selected = $AOE['ta_question_type'] == $k ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="frow">
                    <label>Options</label>
                    <textarea name="AOE[ta_answers]" style="height: 160px;"><?php echo $AOE['ta_answers'] ?></textarea>
                    <div>One answer per row (key,value)</div>
                </div>


            </div>
            <div class="col2">
                <div class="frow">
                    <label>Group</label>
                    <select name="AOE[ta_group_ids][]" class="js_select2" multiple style="max-width:100%">
                        <option value="">Select</option>
                        <?php
                        foreach ($panels as $panel) {
                            $selected = in_array($panel->tg_ID, $AOE['ta_group_ids']) ? 'selected' : '';
                            echo '<option value="' . $panel->tg_ID . '" ' . $selected . '>' . $panel->tg_name . '</option>';
                        }
                        ?>
                    </select>

                </div>
                <div class="frow">
                    <label>Section</label>
                    <select name="AOE[ta_header_id]" class="js_select2_section" style="max-width:100%" required>
                        <option value="">Type or select a section</option>
                        <?php
                        foreach ($groups as $header) {
                            $selected = intval($header->tag_ID) === intval($AOE['ta_header_id']) ? 'selected' : '';
                            echo '<option value="' . esc_attr($header->tag_ID) . '" ' . $selected . '>' . $header->tag_header . '</option>';
                        }
                        ?>
                    </select>
                    <div>Type a new section name, or pick one from the existing list.</div>
                </div>

                <div class="frow">
                    <label>Order</label>
                    <input type="number" name="AOE[ta_order]" value="<?php echo $AOE['ta_order'] ?>" required />
                </div>
                <div class="frow">
                    <label>Cols</label>
                    <input type="number" name="AOE[ta_cols]" value="<?php echo $AOE['ta_cols'] ?>" />
                </div>

                <div class="frow">
                    <input type="hidden" name="AOE[ta_required]" value="0" />
                    <label>
                        <input type="checkbox" name="AOE[ta_required]" value="1" <?php checked($AOE['ta_required']) ?> />
                        Required
                    </label>
                </div>

                <div class="frow" id="ta_start_end_date">
                    <input type="hidden" name="AOE[ta_start_end_date]" value="0" />
                    <label>
                        <input type="checkbox" name="AOE[ta_start_end_date]" value="1" <?php checked($AOE['ta_start_end_date']) ?> />
                        Start&End Date by each option
                    </label>
                </div>
            </div>

        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit" />
            <input type="hidden" name="AOE[ta_ID]" value="<?php echo @$AOE['ta_ID'] ?>" />
            <input type="hidden" name="AOE[ta_btype]" value="<?php echo @$tes_type ?>" />
        </div>
    </form>

    <style>
        body .frow>textarea {
            height: 70px;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            if ($.fn.wpColorPicker) {
                $('.my-color-field').wpColorPicker();
            }
            var $section = $('.js_select2_section');
            if ($section.length && $.fn.select2) {
                $section.select2({
                    width: '100%',
                    tags: true,
                    placeholder: 'Type or select a section',
                    minimumResultsForSearch: 0,
                    createTag: function(params) {
                        var term = $.trim(params.term);
                        if (term === '') {
                            return null;
                        }
                        return {
                            id: term,
                            text: term,
                            newTag: true
                        };
                    },
                    insertTag: function(data, tag) {
                        data.unshift(tag);
                    }
                });
            }
            $('[name="AOE[ta_question_type]"]').change(function() {
                if ($(this).val() == 'mselect') {
                    $('#ta_start_end_date').show();
                } else {
                    $('#ta_start_end_date').hide();
                }
            }).change();
        });
    </script>
<?php
} else {
    if ($act == 'deleteaoe') {
        $id = $_POST['ta_ID'];
        $AOE = $wpdb->get_row("select * from ci_test_aoe where ta_ID = {$id} ");
        $rs = $wpdb->delete('ci_test_aoe', array('ta_ID' => $id));

        if ($rs === false) {
            Tr_Session_Class::add('Can\'t delete AOE');
        } else {

            Tr_Session_Class::add('Deleted AOE "' . $AOE->ta_question . '"');
        }
    } else if ($act == 'deleteall') {
        $wpdb->query("delete from ci_test_aoe where ta_btype='{$tes_type}' ");
    }

    $group_selected = isset($_GET['gr']) ? $_GET['gr'] : 0;
    $where = '';
    if ($group_selected > 0) {
        $where .= " and ta.ta_header_id = {$group_selected} ";
    }

    $sql = "select ta.*,
				tg.tg_name,
				tag.tag_header
            from ci_test_aoe ta 
            left join ci_test_groups tg ON tg.tg_ID = ta.ta_group_id
            left join ci_test_aoe_headers tag ON tag.tag_ID = ta.ta_header_id
            where ta_btype='{$tes_type}' 
            {$where}
            order by ta_order asc, ta_ID asc";

    $AOEs = $wpdb->get_results($sql);

    $groups  = $wpdb->get_results("select * from ci_test_aoe_headers where tag_btype='{$tes_type}' order by tag_order asc, tag_ID asc");
?>
    <h2>AOE Questions
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2 bsty4">Add New</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'groups', 'id' => '', 'gr' => '')) ?>" class="add-new-h2 bsty5">Sections</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_questions', 'type' => $tes_type, 'trajax' => '1')) ?>" class="add-new-h2">Export</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'id' => '')) ?>" class="add-new-h2">Import</a>
    </h2>

    <?php Tr_Session_Class::show() ?>


    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="pagetab" value="<?php echo $_GET['pagetab'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <input type="hidden" name="tabtype" value="<?php echo $_GET['tabtype'] ?>" />
        <input type="hidden" name="type" value="<?php echo $_GET['type'] ?>" />
        <select name="gr">
            <option value="">All Groups</option>
            <?php
            foreach ($groups as $h) {
                $selected = $h->tag_ID == $group_selected ? 'selected' : '';
                echo '<option value="' . $h->tag_ID . '" ' . $selected . '>' . $h->tag_header . '</option>';
            }
            ?>
        </select>
        <input type="submit" class="button" value="Filter" />
    </form>
    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Question</th>
                    <th>Group</th>
                    <th>Section</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($AOEs as $i => $AOE) {
                    if (empty($AOE->ta_slug)) {
                        $AOE->ta_slug = substr(strtolower(trim(preg_replace("/[^\w]+/", '', $AOE->ta_question))), 0, 30);
                        $wpdb->update('ci_test_aoe', array(
                            'ta_slug' => $AOE->ta_slug,
                        ), array('ta_ID' => $AOE->ta_ID));
                    }
                ?>
                    <tr data-id="<?php echo $AOE->ta_ID ?>">
                        <td><?php echo $AOE->ta_ID ?></td>
                        <td class="ta_question"><?php echo $AOE->ta_question ?></td>
                        <td><?php echo $AOE->tg_name ?></td>
                        <td><?php echo $AOE->tag_header ?></td>
                        <td class="editable" title="click to edit" data-k="ta_order"><?php echo $AOE->ta_order ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $AOE->ta_ID)) ?>">Edit</a>
                            - <a class="delete" href="#">Delete</a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <br>

    <style>
        .notfortoxreq {
            background: #ccc !important;
        }

        td.editable:hover {
            background: yellow;
        }
    </style>
    <script>
        jQuery(function($) {
            $('td.editable').click(function() {
                var $this = $(this);
                if (!$this.data('editing')) {
                    $this.data('value', $this.html());
                    $this.html('<input type="text" value="' + $this.html() + '"/>');
                    $this.data('editing', true);
                    $('input', $this).focus();
                }
            })
            $('body').on('keyup', 'td.editable input', function(e) {
                var code = e.keyCode || e.which;
                var $this = $(this);
                var $pr = $(this).parent();
                var $tr = $pr.parent();

                if (code == 27) {
                    $pr.data('editing', false);
                    $pr.html($pr.data('value'));
                } else if (code == 13) {
                    var $vl = $this.val();
                    var $col = $pr.data('k');
                    var $id = $tr.data('id');

                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        data: {
                            trajax: 'save_question',
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
                    return false;
                }
            })
            $('a.delete').click(function() {
                var $pr = $(this).parents('tr');
                var $tgname = $('.ta_question', $pr).html();
                if (confirm('Do you want delete AOE "' + $tgname + '"?') == false) return false;
                var $ta_ID = $pr.data('id');
                var $f = $('<form method="post"><input type="hidden" name="act" value="deleteaoe"/>' +
                    '<input type="hidden" name="ta_ID" value="' + $ta_ID + '"/></form> ');
                $('body').append($f);
                $f.submit();
                return false;
            })
        })
    </script>
<?php
}
