<?php
global $wpdb, $coating_types;

$today          = date_i18n('Y-m-d');
$selected_date  = isset($_GET['date']) ? $_GET['date'] : $today;
$act            = isset($_REQUEST['trajax']) && empty($act) ? $_REQUEST['trajax'] : $act;
$options        = array();

$req_settings   = get_option('ci_req_settings_blood');
$df_coating_types = array(
    'VP' => 'VP',
    'VPV' => 'VP Validation',
    'P' => 'PSel', //487
    'V' => 'VCam', //48
    'M' => 'Mixed',
    'PT' => 'PT',
    'VT' => 'VT',
);
if(!empty($req_settings['plate_coating_types'])){
    $coating_types = array();
    foreach($req_settings['plate_coating_types'] as $k)
    {
        $coating_types[$k] = $df_coating_types[$k];
    }
}else{
    $coating_types = $df_coating_types;
}

$options['type'] = $coating_types;
//exclude pending list: VPV, VT, PT
//max length type :3

function tr_coating_show_row($row)
{
    global $coating_types;
    ob_start();
?>
    <tr data-id="<?php echo $row->ID ?>">
        <td><?php echo $row->service_date ?></td>
        <td class="editable" data-required=true data-k="barcode"><?php echo $row->barcode ?></td>
        <td class="editable" data-required=true data-k="type" data-type="select" data-value="<?php echo $row->type ?>"><?php echo $coating_types[$row->type] ?></td>
        <td class="editable" data-k="letters" data-value="<?php echo $row->letters ?>"><?php echo $row->letters ?></td>
        <td><a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'consumables', 'cid' => $row->ID, 'width' => 1000)) ?>">Consumables</a></td>
        <td>
            <a class="pdelete" href="#" data-id="<?php echo $row->ID ?>">delete</a>
        </td>
    </tr>
<?php
    return ob_get_clean();
}

if ($act == 'add_coating') {
    $barcode    = $_POST['barcode'];
    $type       = $_POST['type'];
    $date       = isset($_POST['date']) && !empty($_POST['date']) ? $_POST['date'] : $today;
    $letters    = isset($_POST['letters']) ? $_POST['letters'] : '';
    $return     = array('status' => 'exist');

    if (!empty($barcode) && !empty($type)) {
        //check exist
        $data = array(
            'barcode'   => $barcode,
            'type'      => $type,
            'letters'      => $letters,
            'service_date' => $date,
            'created'   => date_i18n('Y-m-d H:i:s'),
            'created_by' => get_current_user_id(),
        );
        $exist = $wpdb->get_var("select count(*) from ci_plate_coating where barcode = '{$barcode}' ");
        if (!$exist) {
            $return['status'] = 'ok';
            $wpdb->insert('ci_plate_coating', $data);
            $row_id = $wpdb->insert_id;
            $row = $wpdb->get_row("select * from ci_plate_coating where ID = {$row_id} ");
            $return['html'] = tr_coating_show_row($row);
        }
    }
    if (isset($_POST['trajax'])) {

        wp_send_json($return);
        exit;
    } else {
        Tr_Session_Class::add('Added Successfully!');
    }
} else if ($act == 'pdelete') {
    $id = $_REQUEST['idd'];
    $wpdb->delete('ci_plate_coating', array('ID' => $id));
    Tr_Session_Class::add("Deleted successfully!");
} else if ($act == 'consumables') {
    $coating_id = $_REQUEST['cid'];
    include(trpdd_get_file_path('plate', 'consumables'));
    exit;
} else if ($act == 'settings') {
    $year = date_i18n('y');
    $cache_key         = 'ci_plate_coating_numbers_' . $year;

    if (isset($_POST['numbers'])) {
        update_option($cache_key, $_POST['numbers']);
        Tr_Session_Class::add('Saved');
    } else {
        $cache_numbers = get_option($cache_key, array());

        echo '<form method="post">';
        echo '<div class="frow">';
        $pos = 0;
        foreach ($coating_types as $k => $txt) {

            if (in_array($k, array('VPV'))) continue;

            $number = $cache_numbers[$k] > 0 ? $cache_numbers[$k] : 0;
            if ($number == 0) {
                //search
                $pretitle = $coating->type;
                if ($coating->type == 'vcam') {
                    $pretitle = 'V';
                } else if ($coating->type == 'psel') {
                    $pretitle = 'P';
                } else if ($coating->type == 'mixed') {
                    $pretitle = 'M';
                } else if ($coating->type == 'VPV') {
                    $pretitle = 'VP';
                }
                $pretitle     = strtoupper($pretitle);
                $arrnum = $wpdb->get_row("
                        select cast(replace(replace(re_subtitle,'-{$year}',''),'{$pretitle}','') as UNSIGNED) as number,
                            re_subtitle
                        from ci_racks 
                        where re_subtitle REGEXP '{$pretitle}[0-9]+' or re_subtitle REGEXP '{$pretitle}[0-9]+-[0-9]+' 
                        order by number desc", ARRAY_A);

                $number     = intval($arrnum['number']);
                $yearoldarr = explode('-', $arrnum['re_subtitle']);
                $yearold    = isset($yearoldarr[1]) && $yearoldarr[1] > 0 ? $yearoldarr[1] : $year;
                if ($yearold != $year) {
                    $number = 0;
                }
            }
            if ($pos % 2 == 0) echo '</div><div class="frow">';
            echo '<div class="col2">';
            echo '<label>' . $txt . '(' . $k . ')</label>';
            echo '<input type="number" name="numbers[' . $k . ']" value="' . $number . '">';
            echo '</div>';
            $pos++;
        }

        echo '</div>';
        echo '<input type="submit" class="button" />';
        echo '<input type="hidden" name="act" value="settings"/>';
        echo '</form>';
        exit;
    }
} else if ($act == 'ci_save_table_cell') {
    $return = array('status' => 'ok');
    $field  = $_POST['field'];
    $id     = $_POST['id'];
    $value  = $_POST['value'];
    $old_barcode = '';

    if($field=='barcode'){
        $old_barcode = $wpdb->get_var(
            "select barcode 
            from ci_plate_coating
            where ID = {$id} "
        );
        //check exist
        $exist = $wpdb->get_var("select count(*) from ci_plate_coating 
        where barcode = '{$value}' and ID != {$id} ");
        if($exist)
        {
            $return['status'] = 'exist';
            $return['message'] = 'The Barcode is exist!';
            wp_send_json($return);
        }
    }
    $wpdb->update(
        'ci_plate_coating',
        array(
            $field => $value,
        ),
        array(
            'ID' => $id,
        )
    );
    if($field=='barcode' && !empty($old_barcode)){
        //check and update to rack
        $wpdb->query("update ci_racks 
        set re_barcode = '{$value}'
        where re_barcode = '{$old_barcode}' limit 1 ");
    }
    wp_send_json($return);
}

$sql = "select * from ci_plate_coating 
where service_date = '{$selected_date}' ";

$rows = $wpdb->get_results($sql);

include_once(TRCPDD_PATH . 'pages/formdata.php');

wp_enqueue_script('data-admin-tbl', TRCPDD_URL . 'js/admin_table_edit.js', array(), TRCPDD_VS . time());
?>
<div class="wrap">
    <h2>Plate Coating
        <?php
        if (isset($_GET['debug'])) {
            echo '<a href="' . Tr_Base_Class_V6::link(array('trajax' => 'settings'), false) . '" class="thickbox add-new-h2">settings</a>';
        }
        ?>
    </h2>

    <?php Tr_Session_Class::show() ?>

    <div class="tablenav">
        <form method="get" class="left">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="search" name="date" class="hasdate" value="<?php echo $selected_date ?>" />
            <input type="submit" class="button" value="Filter" />
        </form>
        <form method="post" id="search_form" class="right">
            <input type="hidden" name="date" value="<?php echo $selected_date ?>" />
            <label>Type</label>
            <select name="type">
                <?php
                foreach ($coating_types as $k => $txt) {
                    echo '<option value="' . $k . '">' . $txt . '</option>';
                }
                ?>
            </select>
            <span id="letters_div" style="display:none">
                <label>Letters</label>
                <input type="search" id="letters" name="letters" maxlength="6" />
            </span>
            <label>Barcode:</label>
            <input type="search" id="barcode" name="barcode" maxlength="20" />
            <input type="submit" class="button" value="Add" />
        </form>
    </div>


    <div class="wrap_tbl">
        <table id="main_table_list" class="wp-list-table widefat striped posts blue_tbl hover_tbl">
            <thead class="sticky">
                <tr>
                    <th style="width:200px">Date</th>
                    <th style="width:200px">Barcode</th>
                    <th style="width:20%">Type</th>
                    <th style="width:20%">Letters</th>
                    <th>Consumables</th>
                    <th style="width:80px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $row) {
                    echo tr_coating_show_row($row);
                }
                ?>
            </tbody>
        </table>
        <?php if (count($rows) == 0) {
            echo '<p class="center notfound">Not found</p>';
        }
        ?>
    </div>
</div>

<script>
    var $options = <?php echo json_encode($options)?>;
    jQuery(function($) {
        $('body').on('submit', 'form#search_form', function(e) {
            var $form = $(this);
            var $inputbc = $('[name="barcode"]', $form);
            var $inputletters = $('[name="letters"]', $form);
            var $inputdate = $('[name="date"]', $form);
            var $date = $inputdate.val();
            var $barcode = $inputbc.val();
            var $letters = $inputletters.val();
            var $type = $('[name="type"]', $form).val();
            var $tbl = $('#main_table_list');
            $inputbc.val('');
            $inputletters.val('');
            if ($barcode.length == 0) return false;

            $.ajax({
                type: 'post',
                dataType: 'json',
                data: {
                    trajax: 1,
                    act: 'add_coating',
                    date: $date,
                    barcode: $barcode,
                    letters: $letters,
                    type: $type,
                },
                success: function($json) {
                    if ($json.status == 'ok') {
                        $tbl.append($json.html);
                        $('.notfound').remove();
                    } else if ($json.status == 'exist') {
                        alert('Barcode exist!');
                        $inputletters.val($letters);
                    }
                },
                error: function() {
                    $inputbc.val($barcode);
                    $inputletters.val($letters);
                    alert('Error Please try again');
                }
            });
            return false;
        }).on('change', 'select[name="type"]', function() {
            if ($(this).val() == 'M') {
                $('#letters_div').show();
            } else {
                $('#letters_div').hide();
            }
        }).on('keyup', 'input[name="letters"]', function() {
            $(this).val($(this).val().toUpperCase());
        }).on('keydown', 'input[name="letters"]', function(event) {
            return /[VP]/i.test(event.key);
        })
        $('select[name="type"]').change();
    });
</script>