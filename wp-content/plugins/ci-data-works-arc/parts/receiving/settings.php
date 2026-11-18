<?php
global $wpdb;

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_receiving_position') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];

    $wpdb->delete('ci_receiving_positions', array('ID' => $id));

    trpdd_log_user(get_current_user_id(), 'rmrepos', $id);

    ob_clean();
    wp_send_json($return);
    exit;
}

wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

$btypes = tr_pdd_get_batch_types();
$btypes_str = implode("','", $btypes);
if (isset($_POST['save'])) {
    $items = array();
    foreach ($_POST['settings'] as $k => $data) {
        if (stripos($k, 'new_') !== false) {

            $data['created'] = date_i18n('Y-m-d H:i:s');
            $data['created_by'] = get_current_user_id();
            $wpdb->insert('ci_receiving_positions', $data);
        } else {
            $wpdb->update('ci_receiving_positions', $data, array('ID' => $k));
        }
    }

    trpdd_log_form('recei_pos', $_POST['settings']);

    trpdd_log_user(get_current_user_id(), 'saverepos', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$items = $wpdb->get_results("select * from ci_receiving_positions where btype in ('{$btypes_str}') order by sort_order, ID asc", ARRAY_A);
$specimens = $wpdb->get_results("select * from ci_test_specimens group by ts_type, ts_name order by ts_name asc");
$ref_labs = $wpdb->get_results("select * from ci_labs where lab_is_ref = 1  order by lab_dba_init asc");

?>
<div class="wrap">
    <h2>Receiving Settings</h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col7">
                    <h4>Name</h4>
                </div>
                <div class="col7">
                    <h4>Type</h4>
                </div>
                <div class="col7">
                    <h4>Subtype</h4>
                </div>
                <div class="col7">
                    <h4>Specimen</h4>
                </div>
                <div class="col7">
                    <h4>Start Position</h4>
                </div>
                <div class="col7">
                    <h4>Ref Lab</h4>
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
        <div class="col7">
            <input type="text" class="bname" />
        </div>
        <div class="col7">
            <select class="btype">
                <?php
                foreach ($btypes as $btype) {
                    echo '<option value="' . $btype . '">' . tr_pdd_get_typename($btype) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col7">
            <input type="text" class="subtype" />
        </div>
        <div class="col7">
            <select class="specimen">
                <?php
                echo '<option value="" data-type="pcr">None</option>';
                foreach ($specimens as $specimen) {
                    if ($specimen->ts_type == 'pcr') continue;
                    echo '<option value="' . $specimen->ts_name . '" data-type="' . $specimen->ts_type . '">' . $specimen->ts_name . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col7">
            <input type="text" class="position" />
            <input type="hidden" class="sort_order" />
        </div>
        <div class="col7">
            <select class="ref_lab">
                <option value="">Select</option>
                <?php
                foreach ($ref_labs as $ref_lab) {
                    echo '<option value="' . $ref_lab->lab_ID . '">' . $ref_lab->lab_dba_init . '</option>';
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
        var $keynow = 0;

        function add_item($data) {
            var $default_html = $($('#default_item').html());
            var $key = $data['ID'];
            if (!$key) {
                $key = 'new_' + $keynow;
                $keynow++;
            }
            $default_html.data('id', $key);
            $('.bname', $default_html).attr('name', 'settings[' + $key + '][bname]').val($data['bname']);
            $('.btype', $default_html).attr('name', 'settings[' + $key + '][btype]').val($data['btype']);
            $('.subtype', $default_html).attr('name', 'settings[' + $key + '][subtype]').val($data['subtype']);
            $('.specimen', $default_html).attr('name', 'settings[' + $key + '][specimen]').val($data['specimen']);
            $('.position', $default_html).attr('name', 'settings[' + $key + '][position]').val($data['position']);
            $('.ref_lab', $default_html).attr('name', 'settings[' + $key + '][ref_lab]').val($data['ref_lab']);
            $('.sort_order', $default_html).attr('name', 'settings[' + $key + '][sort_order]').val($data['sort_order']);
            $('#type_items').append($default_html);
            $('.btype', $default_html).change();
        }

        function init_items() {
            var $type_items = $('#type_items');
            var $items = $type_items.data('items');
            for (var i in $items) {
                add_item($items[i]);
            }
        }
        $('body').on('click', 'a.add_item', function() {
                add_item({
                    btype: 'tox'
                });
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
                            trajax: 'remove_receiving_position',
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
            .on('change', 'select.btype', function() {
                var $btype = $(this).val();
                var $pr = $(this).parents('.item');
                var $specimen = $('select.specimen', $pr);
                $('option', $specimen).each(function() {
                    if ($(this).data('type') == $btype) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                })
            })

        init_items();
        $('#type_items').sortable({
            stop: function(event, ui) {
                var $pos = 0;
                $('#type_items input.sort_order').each(function() {
                    $pos++;
                    $(this).val($pos);

                })
            }
        });
    })
</script>