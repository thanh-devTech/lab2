<?php

global $wpdb;
if (empty($tes_type)) {
    return;
}
if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];

    $wpdb->delete('ci_study_exclusion', array('ID' => $id));

    trpdd_log_user(get_current_user_id(), 'rmdiag', $id);

    ob_clean();
    wp_send_json($return);
    exit;
}


wp_enqueue_script('jquery-ui-sortable');

include_once(TRCPDD_PATH . 'pages/formdata.php');

if (isset($_POST['save'])) {
    $_POST = tr_pdd_stripslashes_deep($_POST);
    $items = array();
    $iorder = count($_POST['settings']) + 10;
    foreach ($_POST['settings'] as $k => $data) {
        $iorder--;
        $data['iorder'] = $iorder;
       
        $data['desc'] = trim($data['desc']);

        $data['desc'] = str_replace('\\\'', "'", $data['desc']);
        if (isset($data['position'])) {
            $data['iorder'] = count($_POST['settings']) + 10 - $data['position'];

            unset($data['position']);
        }



        if (stripos($k, 'new_') !== false) {
            $data['created'] = date_i18n('Y-m-d H:i:s');
            $data['created_by'] = get_current_user_id();
            $wpdb->insert('ci_study_exclusion', $data);
        } else {
            $wpdb->update('ci_study_exclusion', $data, array('ID' => $k));
        }
    }

    trpdd_log_form('diag', $_POST['settings']);

    trpdd_log_user(get_current_user_id(), 'savediag', '');
    Tr_Session_Class::add('Saved Successfully!');
}

$items = $wpdb->get_results("select ID, `desc` from ci_study_exclusion 
				order by iorder desc");



?>
<div class="wrap">
    <h2>Exclusion
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => '', 'id' => '')) ?>" class="add-new-h2">All Tests</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'questions', 'type' => $tes_type)) ?>" class="add-new-h2">Questions</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'sitenames', 'type' => $tes_type)) ?>" class="add-new-h2">Site Names</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'irbs', 'type' => $tes_type)) ?>" class="add-new-h2">IRBs</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'inclusion', 'type' => $tes_type)) ?>" class="add-new-h2">Inclusion</a>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">

        <div class="group_type">
            <div class="frow">
                <div class="col2">
                    <h4>Name</h4>
                </div>
                <div class="col7">
                    <h4>Position</h4>
                </div>
                <div class="col7">
                    <h4>Action</h4>
                </div>
            </div>
            <div id="type_items" data-items="<?php echo esc_attr(json_encode($items)) ?>"></div>
            <a class="button add_item">+</a>
        </div>
        <div class="frow actions" id="save_buttons" style="padding-top:20px;display:none">
            <input type="submit" name="save" value="Save" class="button-primary" />
        </div>
    </form>
</div>

<div style="display: none" id="default_item">
    <div class="item frow">

        <div class="col2">
            <input type="text" class="desc" required />
        </div>
        <div class="col7">
            <input type="number" class="position" />
        </div>
        <div class="col7">
            <a class="button remove_item">-</a>
            <span class="dashicons dashicons-menu"></span>
        </div>
    </div>
</div>

<form method="post" enctype="multipart/form-data" style="display:none">
    <label>Import file: </label>
    <input type="file" name="importfile" />
    <label><input type="checkbox" name="colnum" value="1" /> Use Column Number </label>
    <input type="submit" class="button" value="Import" />
</form>
<style>
    .ui-sortable-handle {
        cursor: move;
    }
</style>
<script>
    jQuery(function($) {
        var $keynow = 0;

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

</div>