<?php
global $wpdb;

if (empty($tes_type)) {
    return;
}

$dagnostic_code_bpanel = tr_get_option('ci_req_settings_' . $tes_type, 'dagnostic_code_bpanel', false);

$panels = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups 
where tg_type = '{$tes_type}' 
and tg_parent = 0
order by tg_position asc ");
$panels_by_ids = array();
foreach ($panels as $panel) {
    $panels_by_ids[$panel->tg_ID] = $panel;
}


if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];

    $wpdb->delete('ci_dagnostic_codes', array('ID' => $id));

    trpdd_log_user(get_current_user_id(), 'rmdiag', $id, '', '', '', '', $id);

    ob_clean();
    wp_send_json($return);
    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'export') {
    tr_pdd_header('csv', 'diagnostic_codes_' . $tes_type . '.csv');
    $f = fopen('php://output', 'w');



    $headers = array('Key', 'Description', 'Panels');
    fputcsv($f, $headers);
    $rows = $wpdb->get_results("select * from ci_dagnostic_codes where type='{$tes_type}' order by iorder desc ");
    foreach ($rows as $row) {
        $row->panels = array();
        foreach (explode(',', $row->panelIDs) as $panelID) {
            if (isset($panels_by_ids[$panelID])) {
                $row->panels[] = $panels_by_ids[$panelID]->tg_name;
            }
        }
        $row->panels = implode("\n", $row->panels);

        fputcsv($f, array(
            $row->key,
            $row->desc,
            $row->panels
        ));
    }
    exit;
}




wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

if (isset($_FILES['importfile']) && !empty($_FILES['importfile']['tmp_name'])) {
    $required_fields = array(array('code', 'icd-10 code', 'key'), 'description');
    if (isset($_REQUEST['colnum'])) {
        $required_fields = array();
    }
    if (isset($_REQUEST['removeold'])) {
        $wpdb->delete('ci_dagnostic_codes', array('type' => $tes_type));
    }

    $info = pathinfo($_FILES['importfile']['name']);
    $ext = isset($info['extension']) ? $info['extension'] : 'csv';
    $data = trpdd_get_data($_FILES['importfile']['tmp_name'], $ext, $required_fields, true, count($required_fields) == 0);

    $count = 0;

    foreach ($data['data'] as $row) {

        if (isset($_REQUEST['colnum'])) {
            $key = trim($row[0]);
            $desc = trim($row[1]);
            $panel = trim(isset($row[2]) ? $row[2] : '');
        } else {
            $key = trpdd_get_string_exist($row, array('code', 'icd-10 code', 'key'));
            $desc = trpdd_get_string_exist($row, array('description', 'desc'));
            $panel = trpdd_get_string_exist($row, array('panel', 'panel name'));
        }

        if (empty($key) || empty($desc)) continue;
        $count++;
        if (stripos($desc, $key) === false) {
            $desc = $key . ' ' . $desc;
        }

        $data_row = array(
            'desc' => $desc,
        );


        $existrow = $wpdb->get_row("select * from ci_dagnostic_codes 
						where `type`='{$tes_type}' and `key` = '{$key}'");

        if ($dagnostic_code_bpanel) {
            $data_row['panelIDs'] = !empty($existrow->panelIDs) ? explode(',', $existrow->panelIDs) : array();

            if (!empty($panel)) {
                $panels = explode("\n", $panel);
                foreach ($panels as $panel) {
                    $panel = trim($panel);
                    if (empty($panel)) continue;
                    $panelobj = $wpdb->get_row($wpdb->prepare("select tg_ID from ci_test_groups 
                        where tg_type = %s and tg_name = %s 
                        limit 1", $tes_type, $panel));
                    if ($panelobj) {
                        $data_row['panelIDs'][] = $panelobj->tg_ID;
                    }
                }
            }

            $data_row['panelIDs'] = implode(',', array_unique($data_row['panelIDs']));
        }
        if (!$existrow) {
            $data_row['key'] = $key;
            $data_row['type'] = $tes_type;
            $data_row['created'] = date_i18n('Y-m-d H:i:s');
            $data_row['created_by'] = get_current_user_id();

            $wpdb->insert('ci_dagnostic_codes', $data_row);
        } else {
            $wpdb->update('ci_dagnostic_codes', $data_row,  array('ID' => $existrow->ID));
        }
    }
    if ($count > 0) {
        Tr_Session_Class::add("import success: " . $count);
    } else {
        Tr_Session_Class::add_error("cant import. Missing column code,description");
    }
}
if (isset($_POST['save'])) {
    $_POST = tr_pdd_stripslashes_deep($_POST);
    $items = array();
    $iorder = count($_POST['settings']) + 10;
    foreach ($_POST['settings'] as $k => $data) {
        $iorder--;
        $data['iorder'] = $iorder;
        $data['key'] = trim($data['key']);
        $data['desc'] = trim($data['desc']);
        $data['type'] = $tes_type;
        if ($dagnostic_code_bpanel) {
            $data['panelIDs'] = isset($data['panelIDs']) ? implode(',', $data['panelIDs']) : '';
        }


        $data['desc'] = str_replace('\\\'', "'", $data['desc']);
        if (isset($data['position'])) {
            $data['iorder'] = count($_POST['settings']) + 10 - $data['position'];

            unset($data['position']);
        }



        if (stripos($k, 'new_') !== false) {
            $data['created'] = date_i18n('Y-m-d H:i:s');
            $data['created_by'] = get_current_user_id();
            $wpdb->insert('ci_dagnostic_codes', $data);
        } else {
            $wpdb->update('ci_dagnostic_codes', $data, array('ID' => $k));
        }
    }

    trpdd_log_form('diag', $_POST['settings']);

    trpdd_log_user(get_current_user_id(), 'savediag', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$where = " `type` = '{$tes_type}'  ";

$row_per_page = 200;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) 
from ci_dagnostic_codes 
where {$where}"));
$offset = $pagination->offset();

$items = $wpdb->get_results("select *
                from ci_dagnostic_codes 
				where {$where}
				order by iorder desc
                limit {$offset},{$row_per_page}");



if (empty($items)) {
    $dagnostic_codes = tr_pdd_get_dagnostic_codes($tes_type);
    $limitcount = 0;
    foreach ($dagnostic_codes as $key => $desc) {
        if (empty($key)) continue;
        $limitcount++;
        if ($limitcount > 100) break;

        $wpdb->insert('ci_dagnostic_codes', array(
            'key' => $key,
            'desc' => $desc,
            'type' => $tes_type,
            'created' => date_i18n('Y-m-d H:i:s'),
            'created_by' => get_current_user_id()
        ));
    }
    $items = $wpdb->get_results("select ID, `key`, `desc` from ci_dagnostic_codes 
				where `type` = '{$tes_type}' 
				order by iorder desc");
}





?>
<div class="wrap">
    <h2>Diagnostic Codes
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <div class="tablenav">

        <?php if ($pagination) echo $pagination->pagination() ?>
    </div>
    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col8">
                    <h4>Key</h4>
                </div>
                <div class="col2">
                    <h4>Description</h4>
                </div>
                <?php if ($dagnostic_code_bpanel) { ?>
                    <div class="col6">
                        <h4>Panels</h4>
                    </div>
                <?php } ?>
                <div class="col10">
                    <h4>Position</h4>
                </div>
                <div class="col9">
                    <h4>Action</h4>
                </div>
            </div>
            <div id="type_items"></div>
            <a class="button add_item">+</a>
        </div>
        <div class="frow actions" id="save_buttons" style="padding-top:20px;display:none">
            <input type="submit" name="save" value="Save" class="button-primary" />
        </div>
    </form>
</div>

<div class="tablenav">
    <?php if ($pagination) echo $pagination->pagination() ?>
</div>

<div style="display: none" id="default_item">
    <div class="item frow">
        <div class="col8">
            <input type="text" class="key" />
        </div>
        <div class="col2">
            <input type="text" class="desc" />
        </div>
        <?php if ($dagnostic_code_bpanel) { ?>
            <div class="col6">
                <select class="panelIDs" multiple></select>
            </div>
        <?php } ?>
        <div class="col10">
            <input type="number" class="position center" />
        </div>
        <div class="col9">
            <a class="button remove_item">-</a>
            <span class="dashicons dashicons-menu"></span>
        </div>
    </div>
</div>

<br>
<br>
<br>
<br>
<br>
<form method="post" enctype="multipart/form-data">
    <label>Import file: </label>
    <input type="file" name="importfile" />
    <br>
    <br>
    <label><input type="checkbox" name="colnum" value="1" /> Use Column Number (1:key, 2: description<?php if ($dagnostic_code_bpanel) echo ',3: panel' ?>)</label>
    <br><br>
    <label><input type="checkbox" name="removeold" value="1" /> Remove Old before import</label>
    <br><br>
    <input type="submit" class="button-primary" value="Import" />
    <a class="button" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'export')) ?>">Export</a>
</form>
<style>
    .ui-sortable-handle {
        cursor: move;
    }
</style>
<script>
    jQuery(function($) {
        var $keynow = 0;
        var $panels = <?php echo json_encode($panels) ?>;
        var $items = <?php echo json_encode(is_array($items) ? $items : array()) ?>

        function add_item($data, $next) {
            var $default_html = $($('#default_item').html());
            var $key = $data['ID'];
            $keynow++;
            if (!$key) {
                $key = 'new_' + $keynow;
            }
            $default_html.data('id', $key);


            $('.key', $default_html).attr('name', 'settings[' + $key + '][key]').val($data['key']);
            $('.desc', $default_html).attr('name', 'settings[' + $key + '][desc]').val($data['desc']);
            $('.position', $default_html).attr('name', 'settings[' + $key + '][position]').val($keynow).data('oldpos', $keynow);
            $('#type_items').append($default_html);

            try {
                //add panel
                var $pnobj = $('.panelIDs', $default_html);
                if ($pnobj) {
                    $pnobj.attr('name', 'settings[' + $key + '][panelIDs][]');
                    $panelsvl = $data['panelIDs'] ? $data['panelIDs'].split(',') : [];
                    for (i in $panels) {
                        var $selected = $panelsvl.includes($panels[i].tg_ID) ? 'selected' : '';
                        $pnobj.append('<option value="' + $panels[i].tg_ID + '" ' + $selected + '>' + $panels[i].tg_name + '</option>');
                    }
                    $pnobj.select2();
                }

            } catch (e) {
                console.log(e)
            }

        }

        function init_items() {
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
        })

        init_items();
        $('#save_buttons').show();
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