<?php
global $wpdb;
if (empty($tes_type)) {
    return;
}
$te_type = 'wsso';

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];

    $wpdb->delete('ci_test_panels', array('te_ID' => $id, 'te_type' => $te_type));

    trpdd_log_user(get_current_user_id(), 'rmpats', $id);

    ob_clean();
    wp_send_json($return);
    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'export') {
    $rs = $wpdb->get_col("select te_name from ci_test_panels 
						where  te_type='{$te_type}' ");
    echo implode("\n", $rs);
    exit;
}

wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

if (isset($_POST['importanti'])) {


    if (!empty($_POST['rows'])) {
        $rows = array();
        $lines = explode("\n", $_POST['rows']);
        foreach ($lines as $l) {
            $l = trim($l);
            if (empty($l)) continue;
            $rows[] = array($l);
        }
        $data = array('data' => $rows);
    } else {
        $data = trpdd_get_data($_FILES['importfile']['tmp_name'], null, array(), true, true);
    }

    $count = 0;
    foreach ($data['data'] as $row) {
        $te_name = trim($row[0]);

        if (empty($te_name) || strtoupper($te_name) == 'DRUGS') continue;
        $count++;


        $exist = $wpdb->get_var("select te_ID from ci_test_panels 
						where `te_name`='{$te_name}' and te_type='{$te_type}' ");
        if (!$exist) {
            $wpdb->insert('ci_test_panels', array(
                'te_name' => $te_name,
                'te_type' => $te_type,
                'te_start' => date_i18n('Y-m-d H:i:s'),

            ));
        } else {
            $wpdb->update('ci_test_panels', array('te_name' => $te_name), array('te_ID' => $exist));
        }
    }
    if ($count > 0) {
        Tr_Session_Class::add("import success");
    } else {
        Tr_Session_Class::add_error("cant import. Missing column drugs");
    }
}
if (isset($_POST['save'])) {
    $items = array();
    $iorder = count($_POST['settings']) + 10;
    foreach ($_POST['settings'] as $k => $data) {
        $iorder--;
        $data['te_position'] = $iorder;
        $data['te_name'] = trim($data['te_name']);
        //$data['te_name'] = ucwords(strtolower($data['te_name']));
        $data['te_type'] = $te_type;

        if (isset($data['position'])) {
            $data['te_position'] = count($_POST['settings']) + 10 - $data['position'];

            unset($data['position']);
        }



        if (stripos($k, 'new_') !== false) {
            $data['te_start'] = date_i18n('Y-m-d H:i:s');

            $wpdb->insert('ci_test_panels', $data);
        } else {
            $wpdb->update('ci_test_panels', $data, array('te_ID' => $k));
        }
    }

    update_option('ci_wound_required_panels', $_POST['required_panels']);

    trpdd_log_form('diag', $_POST['settings']);

    trpdd_log_user(get_current_user_id(), 'savediag', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$items = $wpdb->get_results("select te_ID, te_name 
            from ci_test_panels 
                where te_type='{$te_type}'
				order by te_position desc, te_name asc");

$required_panels = get_option('ci_wound_required_panels', array());
$panels = $wpdb->get_results("select tg_ID, tg_name 
            from ci_test_groups
                where tg_type!='{$te_type}'
                and tg_parent = 0
                and tg_show_req = 1
                order by tg_position desc, tg_name asc", ARRAY_A);
$title = 'Specimen Location/Source';

?>
<div class="wrap">
    <h2><?php echo $title ?>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col2">
                    <h4>Source</h4>
                </div>
                <div class="col7">
                    <h4>Action</h4>
                </div>
            </div>
            <div id="type_items" data-items="<?php echo esc_attr(json_encode($items)) ?>"></div>
            <a class="button add_item">+</a>
        </div>

        <div class="frow" style="padding-top:20px;width:50%">
            <label>Panels Required:</label>
            <select name="required_panels[]" class="js_select2" multiple>
                <?php
                foreach ($panels as $panel) {
                    $selected = in_array($panel['tg_ID'], $required_panels) ? 'selected' : '';
                    echo '<option value="' . $panel['tg_ID'] . '" ' . $selected . '>' . $panel['tg_name'] . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="frow actions" style="padding-top:20px">
            <input type="submit" name="save" value="Save" class="button-primary" />
        </div>
    </form>
</div>

<div style="display: none" id="default_item">
    <div class="item frow">
        <div class="col2">
            <input type="text" class="te_name" />
        </div>
        <div class="col7">
            <a class="button remove_item">-</a>
            <span class="dashicons dashicons-menu"></span>
        </div>
    </div>
</div>

<form method="post" enctype="multipart/form-data">
    <label>Import file: </label>
    <input type="file" name="importfile" />
    <br>
    or
    <br>
    <textarea name="rows" id="listimport" style="width:300px"></textarea>
    <br>
    <input type="submit" class="button" value="Import" />
    <a class="button exportlist">Export</a>
    <input type="hidden" name="importanti" value="1" />
</form>
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
            var $tid = $data['te_ID'];
            $trow++;
            if (!$tid) {
                $tid = 'new_' + $trow;
            }
            $default_html.data('id', $tid);
            $('.te_name', $default_html).attr('name', 'settings[' + $tid + '][te_name]').val($data['te_name']);
            $('.position', $default_html).attr('name', 'settings[' + $tid + '][position]').val($trow).data('oldpos', $trow);
            $('#type_items').append($default_html);
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
        }).on('click', 'a.add_next', function() {
            var $pr = $(this).parents('.item');
            add_item({}, $pr);
        }).on('click', 'a.remove_item', function() {
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
        }).on('change', '#type_items input.position', function() {
            var $this = $(this);
            var $cur = $(this).parents('div.item');
            var $vl = parseInt($(this).val());
            //find new pos

            for (var $newpos = $vl; $newpos > 0; $newpos--) {
                var $found = false;
                $('#type_items input.position').each(function() {
                    if ($(this).attr('name') != $this.attr('name')) {
                        var $cpos = parseInt($(this).val());
                        if ($cpos == $newpos) {
                            $found = $(this);
                        }
                    }

                })
                if ($found !== false) {
                    var $it = $found.parents('div.item');
                    if ($newpos >= $vl) {
                        $cur.insertBefore($it);
                    } else {
                        $cur.insertAfter($it);
                    }

                    break;
                }
            }
            var $pos = 0;
            $('#type_items input.position').each(function() {
                $pos++;
                $(this).val($pos);

            })

        }).on('focus', '#type_items input.position', function() {
            $(this).select();
        }).on('click', '.exportlist', function() {
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
                $('#type_items input.position').each(function() {
                    $pos++;
                    $(this).val($pos);

                })
            }
        });
    })
</script>