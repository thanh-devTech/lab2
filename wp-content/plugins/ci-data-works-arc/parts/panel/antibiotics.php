<?php
global $wpdb;
if (empty($tes_type)) {
    return;
}

$mic_type = tr_get_option('ci_result_settings_pcr', 'e_mic2', 0);

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];

    $wpdb->delete('ci_test_antibiotics', array('ID' => $id));

    trpdd_log_user(get_current_user_id(), 'rmanti', $id, '', '', '', '', $id);

    ob_clean();
    wp_send_json($return);
    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'export') {
    $rs = $wpdb->get_col("select ta_name from ci_test_antibiotics");
    echo implode("\n", $rs);
    exit;
}

wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

$panels = $wpdb->get_results("select tg_ID, tg_name, tg_cpt_code from ci_test_groups where tg_parent = 0 and tg_show_req = 1 order by tg_name asc");
$panel_names_by_id = array();
$panel_ids_by_name = array();
foreach ($panels as $panel) {
    $panel_names_by_id[$panel->tg_ID] = $panel->tg_name;
    $panel_ids_by_name[strtolower(trim($panel->tg_name))] = $panel->tg_ID;
    //get sub panels
    $panel->sub_panels = $wpdb->get_results("select tg_ID, tg_name,tg_cpt_code from ci_test_groups where tg_parent = {$panel->tg_ID} order by tg_name asc");
    foreach ($panel->sub_panels as $sub_panel) {
        $panel_names_by_id[$sub_panel->tg_ID] = $sub_panel->tg_name . ' (' . $panel->tg_cpt_code . ')';
        $panel_ids_by_name[strtolower(trim($panel_names_by_id[$sub_panel->tg_ID]))] = $sub_panel->tg_ID;
    }
}

if (isset($_POST['import_settings'])) {
    if (isset($_FILES['settings_import_file']) && !empty($_FILES['settings_import_file']['tmp_name'])) {
        $file = fopen($_FILES['settings_import_file']['tmp_name'], 'r');
        $headers = $file ? fgetcsv($file) : false;
        $header_map = array();
        if (is_array($headers)) {
            foreach ($headers as $i => $header) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
                $header_map[strtolower(trim($header))] = $i;
            }
        }

        if (!empty($header_map) && isset($header_map['drug'])) {
            $count_insert = 0;
            $count_update = 0;
            $ids = array();
            while (($data = fgetcsv($file)) !== false) {
                $ta_name = isset($data[$header_map['drug']]) ? trim($data[$header_map['drug']]) : '';
                if (empty($ta_name)) {
                    continue;
                }
                $group_names = isset($header_map['group']) && isset($data[$header_map['group']]) ? trim($data[$header_map['group']]) : '';
                $group_ids = array();
                if (!empty($group_names)) {
                    $names = preg_split('/\s*\|\s*/', $group_names);
                    foreach ($names as $name) {
                        $name_key = strtolower(trim($name));
                        if (isset($panel_ids_by_name[$name_key])) {
                            $group_ids[$panel_ids_by_name[$name_key]] = $panel_ids_by_name[$name_key];
                        }
                    }
                }
                $mode = isset($header_map['modes']) && isset($data[$header_map['modes']]) ? trim($data[$header_map['modes']]) : '';
                if (stripos($mode, ' - ') !== false) {
                    $mode = trim(substr($mode, 0, stripos($mode, ' - ')));
                }
                $row = array(
                    'ID' => isset($header_map['id']) && isset($data[$header_map['id']]) ? (int)$data[$header_map['id']] : 0,
                    'ta_name' => $ta_name,
                    'ta_abrev' => isset($header_map['abrev']) && isset($data[$header_map['abrev']]) ? trim($data[$header_map['abrev']]) : '',
                    'ta_group' => $mode,
                    'ta_groups' => implode(',', $group_ids),
                    'iorder' => isset($header_map['order']) && isset($data[$header_map['order']]) ? (int)$data[$header_map['order']] : 0,
                );
                if (isset($header_map['start date']) && isset($data[$header_map['start date']])) {
                    $row['ta_start_date'] = trim($data[$header_map['start date']]);
                }
                if (isset($header_map['end date']) && isset($data[$header_map['end date']])) {
                    $row['ta_end_date'] = trim($data[$header_map['end date']]);
                }
                $existing_id = $wpdb->get_var($wpdb->prepare("select ID from ci_test_antibiotics where ta_name = %s and ta_groups = %s limit 1", $ta_name, implode(',', $group_ids)));
                if ($existing_id) {
                    unset($row['ID']);
                    if ($wpdb->update('ci_test_antibiotics', $row, array('ID' => $existing_id)) !== false) {
                        $count_update++;
                        $ids[] = $existing_id;
                    }
                    continue;
                }
                if (empty($row['ID']) || $wpdb->get_var($wpdb->prepare("select ID from ci_test_antibiotics where ID = %d", $row['ID']))) {
                    unset($row['ID']);
                }
                $row['created'] = date_i18n('Y-m-d H:i:s');
                $row['created_by'] = get_current_user_id();
                if ($wpdb->insert('ci_test_antibiotics', $row) !== false) {
                    $count_insert++;
                    $ids[] = $wpdb->insert_id;
                }
            }

            if (count($ids) > 0) {
                $wpdb->query("DELETE FROM ci_test_antibiotics WHERE ID NOT IN (" . implode(',', array_map('intval', $ids)) . ")");
            }
            if ($file) {
                fclose($file);
            }
            trpdd_log_user(0, 'import_antibiotics', array('inserted' => $count_insert, 'updated' => $count_update));
            Tr_Session_Class::add('Inserted: ' . $count_insert);
            Tr_Session_Class::add('Updated: ' . $count_update);
        } else {
            if ($file) {
                fclose($file);
            }
            Tr_Session_Class::add('Invalid import file.');
        }
    } else {
        Tr_Session_Class::add('Please select an import file.');
    }
}

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
        $ta_name = trim($row[0]);

        if (empty($ta_name) || strtoupper($ta_name) == 'DRUGS') continue;
        $count++;


        $exist = $wpdb->get_var("select ID from ci_test_antibiotics 
						where `ta_name`='{$ta_name}' ");
        if (!$exist) {
            $wpdb->insert('ci_test_antibiotics', array(
                'ta_name' => $ta_name,
                'created' => date_i18n('Y-m-d H:i:s'),
                'created_by' => get_current_user_id()
            ));
        } else {
            $wpdb->update('ci_test_antibiotics', array('ta_name' => $ta_name), array('ID' => $exist));
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
        $data['iorder'] = $iorder;
        $data['ta_name'] = trim($data['ta_name']);
        $data['ta_name'] = ucwords(strtolower($data['ta_name']));
        if (isset($data['position'])) {
            $data['iorder'] = count($_POST['settings']) + 10 - $data['position'];

            unset($data['position']);
        }

        if (!is_array($data['ta_groups'])) {
            $data['ta_groups'] = array();
        }
        $data['ta_groups'] = implode(',', $data['ta_groups']);



        if (stripos($k, 'new_') !== false) {
            $data['created'] = date_i18n('Y-m-d H:i:s');
            $data['created_by'] = get_current_user_id();
            $wpdb->insert('ci_test_antibiotics', $data);
        } else {
            $wpdb->update('ci_test_antibiotics', $data, array('ID' => $k));
        }
    }

    trpdd_log_form('diag', $_POST['settings']);

    trpdd_log_user(get_current_user_id(), 'savediag', '', '', '', '', '', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$orderby = " ta_group desc, iorder desc ";
if ($mic_type == 2) {
    $orderby = "  iorder desc, ta_name asc";
}
$items = $wpdb->get_results("select *
                from ci_test_antibiotics 
				order by {$orderby}");
$export_items = array();
$mode_labels = array(
    'PO' => 'PO - by mouth',
    'IM' => 'IM - Intramuscular',
    'IV' => 'IV - Intravenous',
);
foreach ($items as $item) {
    $group_names = array();
    $group_ids = !empty($item->ta_groups) ? explode(',', $item->ta_groups) : array();
    foreach ($group_ids as $group_id) {
        $group_id = trim($group_id);
        if (isset($panel_names_by_id[$group_id])) {
            $group_names[] = $panel_names_by_id[$group_id];
        }
    }
    $export_items[] = array(
        'ID' => $item->ID,
        'Drug' => $item->ta_name,
        'Abrev' => $item->ta_abrev,
        'Group' => implode(' | ', $group_names),
        'Modes' => isset($mode_labels[$item->ta_group]) ? $mode_labels[$item->ta_group] : $item->ta_group,
        'Start Date' => $item->ta_start_date,
        'End Date' => $item->ta_end_date,
        'Order' => $item->iorder,
    );
}
?>
<div class="wrap">
    <h2>Antibiotics
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
        <a href="#" class="add-new-h2 trpdd-panel-import-export">Import/Export</a>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <div id="trpdd_panel_import_export_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
        <div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
            <h3 style="margin-top:0;">Import/Export Antibiotics</h3>
            <form method="post" enctype="multipart/form-data">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="file" name="settings_import_file" accept="text/csv,.csv" />
                    <input type="submit" name="import_settings" class="button" value="Import" />
                    <a href="#" class="button button-primary trpdd-export-settings">Export</a>
                    <a href="#" class="button trpdd-close-panel-import-export">Close</a>
                </div>
            </form>
        </div>
    </div>
    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col5">
                    <h4>Drug</h4>
                </div>
                <div class="col5">
                    <h4>Abrev</h4>
                </div>
                <?php if (in_array($mic_type, array(1, 2))) { ?>
                    <div class="col5">
                        <h4>Group</h4>
                    </div>
                    <div class="col5">
                        <h4>Modes</h4>
                    </div>
                <?php } ?>
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
        <div class="col5">
            <input type="text" class="ta_name" />
        </div>
        <div class="col5">
            <input type="text" class="ta_abrev" />
        </div>
        <?php if (in_array($mic_type, array(1, 2))) { ?>
            <div class="col5">
                <select class="ta_groups" multiple>
                    <?php
                    foreach ($panels as $panel) {

                        echo '<option value="' . $panel->tg_ID . '">' . $panel->tg_name . '</option>';
                        if (isset($panel->sub_panels) && is_array($panel->sub_panels)) {
                            foreach ($panel->sub_panels as $sub_panel) {
                                echo '<option value="' . $sub_panel->tg_ID . '">&nbsp;&nbsp;- ' . $sub_panel->tg_name . '(' . $panel->tg_cpt_code . ')</option>';
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col5">
                <?php
                if ($mic_type == 2) {
                ?>
                    <select class="ta_group">
                        <option value="">Select</option>
                        <option value="PO">PO - by mouth</option>
                        <option value="IM">IM - Intramuscular</option>
                        <option value="IV">IV - Intravenous</option>
                    </select>
                <?php } else { ?>
                    <input type="text" class="ta_group" />
                <?php } ?>
                <div style="margin-top:5px;text-align:right">
                    <input type="text" class="ta_start_date" style="width:100px;height:20px;min-height:20px" placeholder="Start Date" />
                    <input type="text" class="ta_end_date" style="width:100px;height:20px;min-height:20px" placeholder="End Date" />
                </div>

            </div>
        <?php } ?>
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

    #type_items select {
        width: 100%;
    }

    #type_items .select2 {
        width: 100% !important;
    }
</style>
<script>
    jQuery(function($) {
        var trpddSettingsExport = <?php echo wp_json_encode($export_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var trpddSettingsExportFilename = <?php echo wp_json_encode('pcr-antibiotics-' . date_i18n('Ymd-His') . '.csv') ?>;
        var $trow = 0;

        function add_item($data, $next) {
            var $default_html = $($('#default_item').html());
            var $tid = $data['ID'];
            $trow++;
            if (!$tid) {
                $tid = 'new_' + $trow;
            }
            $default_html.data('id', $tid);
            $('.ta_name', $default_html).attr('name', 'settings[' + $tid + '][ta_name]').val($data['ta_name']);
            $('.ta_abrev', $default_html).attr('name', 'settings[' + $tid + '][ta_abrev]').val($data['ta_abrev']);
            $('.ta_group', $default_html).attr('name', 'settings[' + $tid + '][ta_group]').val($data['ta_group']);
            if ($('.ta_start_date', $default_html).length > 0) {
                if ($data['ta_start_date'] == '0000-00-00') $data['ta_start_date'] = '';
                if ($data['ta_end_date'] == '0000-00-00') $data['ta_end_date'] = '';
                $('.ta_start_date', $default_html).attr('name', 'settings[' + $tid + '][ta_start_date]').val($data['ta_start_date']);
                $('.ta_end_date', $default_html).attr('name', 'settings[' + $tid + '][ta_end_date]').val($data['ta_end_date']);
            }


            $('.position', $default_html).attr('name', 'settings[' + $tid + '][position]').val($trow).data('oldpos', $trow);
            $('.ta_groups', $default_html).attr('name', 'settings[' + $tid + '][ta_groups][]');
            if ($data['ta_groups']) {
                var $groups = $data['ta_groups'].split(',');
                console.log($groups);
                for (i in $groups) {
                    $('.ta_groups option[value="' + $groups[i] + '"]', $default_html).prop('selected', true);
                }
            }

            $('select', $default_html).select2();
            $('#type_items').append($default_html);
            if ($('.ta_start_date', $default_html).length > 0) {
                $('.ta_start_date', $default_html).datepicker({
                    dateFormat: 'yy-mm-dd'
                });
                $('.ta_end_date', $default_html).datepicker({
                    dateFormat: 'yy-mm-dd'
                });
            }
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
        }).on('click', '.trpdd-panel-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_panel_import_export_modal').show();
        }).on('click', '.trpdd-close-panel-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_panel_import_export_modal').hide();
        }).on('click', '#trpdd_panel_import_export_modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        }).on('click', '.trpdd-export-settings', function(e) {
            e.preventDefault();
            var headers = ['ID', 'Drug', 'Abrev', 'Group', 'Modes', 'Start Date', 'End Date', 'Order'];
            var csvEscape = function(value) {
                value = value == null ? '' : String(value);
                return '"' + value.replace(/"/g, '""') + '"';
            };
            var csv = headers.map(csvEscape).join(',') + "\n";
            for (var i in trpddSettingsExport) {
                csv += headers.map(function(header) {
                    return csvEscape(trpddSettingsExport[i][header]);
                }).join(',') + "\n";
            }
            var blob = new Blob([csv], {
                type: 'text/csv'
            });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = trpddSettingsExportFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
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