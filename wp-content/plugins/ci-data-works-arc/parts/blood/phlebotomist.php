<?php
global $wpdb, $current_role, $today, $collector;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$settings               = get_option('ci_req_settings_blood', array());

$today          = date_i18n('Y-m-d');
$user_id        = get_current_user_id();
$collector      = array();
$collectors     = array();
$editable_code  = tr_current_role_in(array('administrator', 'AdminCIDW'));

if ($current_role == 'collector') {
    $col_ID = get_user_meta($user_id, '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID = {$col_ID}", ARRAY_A);
}

$tabs = array(
    'phlebotomist' => 'Phlebotomist Log',
    'standing_orders' => 'Standing Orders',
);

if (tr_get_option('ci_req_settings_blood', 'Phlebotomist_showallblood') || tr_get_option('ci_req_settings_blood', 'show_all_standing_order')) {
    $tabs['standing_orders'] = 'Orders';
}

if (function_exists('WC')) {
    $tabs['testing_payments'] = 'Testing Payments';
}

$user_can_stadingtabs = current_user_can('ci_standing_orders');
if (get_user_meta($user_id, '_phlebotomist', true)) {
    $user_can_stadingtabs = true;
}
if (!$settings['add_standing_order'] || !$user_can_stadingtabs) {
    unset($tabs['standing_orders']);
}

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (count($tabs) > 1 && !isset($_REQUEST['trajax'])) {
?>
    <h2 class="nav-tab-wrapper">
        <?php
        foreach ($tabs as $k => $txt) {
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 'from' => '', 'to' => ''), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
    <?php
}

if ($tab == 'testing_payments') {
    return include(trpdd_get_file_path('blood', 'testing_payments'));
}
if ($tab == 'standing_orders') {
    return include(trpdd_get_file_path('blood', 'standing_orders'));
}


if (!function_exists('phlebotomist_list_bydate')) {

    function phlebotomist_list_bydate($date)
    {
        global $wpdb, $collector, $current_role;

        ob_start();

        $where = " 1";
        if ($current_role == 'collector') {
            $where = "  and  collector_ID = {$collector['col_ID']} ";
        }
        if (!empty($date)) {
            $where .= " and service_date = '{$date}' ";
        }
        $row_per_page = 20;
        $total = $wpdb->get_var("select count(*) from ci_phlebotomist_logs where {$where} ");
        $pagination = new Tr_Pagination_class($row_per_page);
        $pagination->setTotal($total);
        $offset = $pagination->offset();

        $sql = "select * from ci_phlebotomist_logs 
	            where {$where}
	             order by service_date desc
	            limit {$offset},{$row_per_page}";
        $list = $wpdb->get_results($sql);

    ?>
        <div class="tablenav top">
            <?php echo $pagination->pagination() ?>
        </div>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl visitrow_tbl">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Service Date</th>
                    <th>Start Service Time</th>
                    <th>End Service Time</th>
                    <?php if (!tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) { ?>
                        <th>Start Mileage</th>
                        <th>End Mileage</th>
                        <th>Number of stops</th>
                        <th>Round Trip</th>
                        <th>Total Patients/ Draws</th>
                    <?php } else { ?>
                        <th>Total Patients/Check-In</th>
                        <th>Total Patients/ Draws</th>
                    <?php } ?>
                    <?php
                    if ($current_role != 'collector') {
                        echo '<th>Phlebotomist</th>';
                    }
                    ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($list) == 0) {
                    echo '<tr><td colspan=7>Not found</td></tr>';
                } else {
                    foreach ($list as $i => $row) {
                ?>
                        <tr data-id="<?php echo $row->ID ?>" data-value="<?php echo esc_attr(json_encode($row)) ?>">
                            <td><?php echo $i + 1 ?></td>
                            <td><?php echo $row->service_date ?></td>
                            <td><?php echo $row->start_service_time ?></td>
                            <td><?php echo $row->end_service_time ?></td>
                            <?php if (!tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) { ?>
                                <td><?php echo $row->start_mileage . ' / ' . $row->start_mileage_2 ?></td>
                                <td><?php echo $row->end_mileage . ' / ' . $row->end_mileage_2 ?></td>
                                <td><?php echo $row->number_of_stops . ' / ' . $row->number_of_stops_2 ?></td>
                                <td><?php echo $row->round_trip . ' / ' . $row->round_trip_2 ?></td>
                                <td class="center"><?php echo $row->total_patients_draws . ' / ' . $row->total_patients_draws_2 ?></td>
                            <?php } else {
                                $docfrom = $row->service_date . ' ' . (!empty($row->start_service_time) ? $row->start_service_time : '00:00:00');
                                $docto = $row->service_date . ' ' . (!empty($row->end_service_time) ? $row->end_service_time : '23:59:59');
                                $row->checkin = $wpdb->get_var("select count(*) from ci_batches_blood ba
                                                where doc >='{$docfrom}' and doc<='{$docto}' and ba.batch_status = 'so_assigned' ");
                                $row->draws = $wpdb->get_var("select count(*) from ci_batches_blood ba
                                                where doc >='{$docfrom}' and doc<='{$docto}' and ba.batch_status = 'so_collected' ");

                            ?>
                                <td class="center"><?php echo $row->checkin ?></td>
                                <td class="center"><?php echo $row->draws ?></td>
                            <?php } ?>
                            <?php
                            if ($current_role != 'collector') {
                                $row->collector_name = $wpdb->get_var("select concat(col_first,' ',col_last) from ci_collector where col_ID = {$row->collector_ID}");
                            ?>
                                <td><?php echo $row->collector_name ?></td>
                            <?php
                            }
                            ?>
                            <td>
                                <a class="editrow" href="#">Edit</a> -
                                <a class="deleterow delete" href="#">Delete</a>
                            </td>
                        </tr>
                <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <div class="tablenav bottom">
            <?php echo $pagination->pagination() ?>
        </div>
<?php
        return ob_get_clean();
    }
}


if (isset($_REQUEST['trajax'])) {
    if ($_REQUEST['trajax'] == 'check_service_date') {
        $date = $_REQUEST['date'];
        $return = array('status' => 'ok', 'html' => '');
        $return['html'] = phlebotomist_list_bydate($date);
        wp_send_json($return);
        exit;
    }

    if ($_REQUEST['trajax'] == 'delete_service') {
        $return = array('status' => 'ok', 'html' => '');
        $wpdb->delete('ci_phlebotomist_logs', array('ID' => $_REQUEST['id']));
        wp_send_json($return);
        exit;
    }
}

if (!empty($_POST['data'])) {
    $data = tr_pdd_stripslashes_deep($_POST['data']);

    if (empty($data['collector_ID'])) {
        $data['collector_ID'] = $collector['col_ID'];
    }
    if ($data['collector_ID'] > 0 && isset($_POST['col_phlebotomist_code']) && $editable_code) {
        $wpdb->update(
            'ci_collector',
            array('col_phlebotomist_code' => $_POST['col_phlebotomist_code']),
            array('col_ID' => $data['collector_ID'])
        );
    }

    if ($data['ID'] > 0) {
        $wpdb->update('ci_phlebotomist_logs', $data, array('ID' => $data['ID']));
    } else {
        $data['created'] = date_i18n('Y-m-d H:i:s');
        $data['created_by'] = $user_id;
        $wpdb->insert('ci_phlebotomist_logs', $data);
    }
    $today = $data['service_date'];
    Tr_Session_Class::add('Saved Successfully!');
}




$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
if ($id > 0) {
    $where = '1';
    $where .= " and collector_ID = {$collector['col_ID']} ";
    $where .= " and ID = {$id} ";
    $sql = "select * from ci_phlebotomist_logs where {$where}";

    $data = $wpdb->get_row($sql, ARRAY_A);
} else {
    $data = array(
        'service_date' => isset($_REQUEST['date']) ? $_REQUEST['date'] : '',
        'collector_ID' => isset($collector['col_ID']) ? $collector['col_ID'] : 0,
        'category'      => 'M',
        'category_2'      => 'NM',
    );
}

$collectors = $wpdb->get_results("select col.* from ci_collector col
	                left join {$wpdb->usermeta} m ON m.meta_key='_collector_id' 
	                                            and m.meta_value = col.col_ID
	               where col_phlebotomist_code!='' or m.user_id >0
	                order by col_first asc", ARRAY_A);
$categories = array('M', 'NM');

$current_lab = CI_Base_Lab::get_current_lab();
$lab_name = $current_lab['lab_name'];

$data['identifier'] = !empty($settings['phle_indentifier']) ? $settings['phle_indentifier'] : $lab_name;
$data['company'] = !empty($settings['phle_company']) ? $settings['phle_company'] : '';
?>
<div class="wrap">
    <h2 id="phlebotop">Phlebotomist Log</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" id="form_phle" class="need_validate">
        <div class="frow">
            <div class="col4">
                <label>Log Identifier</label>
                <input type="text" readonly value="<?php echo $data['identifier'] ?>" />
            </div>
            <div class="col4">
                <label>Company</label>
                <input type="text" readonly value="<?php echo $data['company'] ?>" />
            </div>
            <div class="col4">
                <label>Phlebotomist Code</label>
                <input type="text" <?php echo ($editable_code) ? 'name="col_phlebotomist_code"' : 'readonly' ?> class="col_phlebotomist_code" value="<?php echo isset($collector['col_phlebotomist_code']) ? $collector['col_phlebotomist_code'] : '' ?>" />
            </div>
            <?php
            if ($current_role != 'collector') {
            ?>
                <div class="col4">
                    <label>Phlebotomist</label>
                    <select name="data[collector_ID]" required>
                        <option value="">Select </option>
                        <?php
                        foreach ($collectors as $col) {
                            if (trim($col['col_first'] . ' ' . $col['col_last']) == '') {
                                continue;
                            }
                            $selected = $col['col_ID'] == $data['collector_ID'] ? 'selected' : '';
                            echo '<option value="' . $col['col_ID'] . '" data-code="' . $col['col_phlebotomist_code'] . '" ' . $selected . '>' . $col['col_first'] . ' ' . $col['col_last'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
            <?php
            }
            ?>
        </div>

        <div class="frow">
            <div class="col4">
                <label>Service Date</label>
                <input type="text" class="hasdate" name="data[service_date]" value="<?php echo isset($data['service_date']) ? $data['service_date'] : '' ?>" required />
            </div>
            <div class="col4">
                <label>Start Service Time</label>
                <input type="text" class="hastime" name="data[start_service_time]" value="<?php echo isset($data['start_service_time']) ? $data['start_service_time'] : '' ?>" />
            </div>
            <div class="col4">
                <label>End Service Time </label>
                <input type="text" class="hastime" name="data[end_service_time]" value="<?php echo isset($data['end_service_time']) ? $data['end_service_time'] : '' ?>" />
            </div>
        </div>
        <?php if (!tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) { ?>
            <h4>Medicare Patients</h4>

            <div class="frow">
                <div class="col4">
                    <label>Start Mileage</label>
                    <input type="text" name="data[start_mileage]" value="<?php echo isset($data['start_mileage']) ? $data['start_mileage'] : '0' ?>" />
                </div>
                <div class="col4">
                    <label>End Mileage</label>
                    <input type="text" name="data[end_mileage]" value="<?php echo isset($data['end_mileage']) ? $data['end_mileage'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Total Patients/ Draws</label>
                    <input type="text" name="data[total_patients_draws]" value="<?php echo isset($data['total_patients_draws']) ? $data['total_patients_draws'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Medicare or Non-Medicare</label>
                    <select name="data[category]">
                        <option value="">Select</option>
                        <?php
                        foreach ($categories as $category) {
                            $selected = isset($data['category']) && $data['category'] == $category ? 'selected' : '';
                            echo '<option value="' . $category . '" ' . $selected . '>' . $category . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Number of stops</label>
                    <input type="text" name="data[number_of_stops]" value="<?php echo isset($data['number_of_stops']) ? $data['number_of_stops'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Round Trip</label>
                    <input type="text" name="data[round_trip]" value="<?php echo isset($data['round_trip']) ? $data['round_trip'] : '1' ?>" />
                </div>

            </div>
            <h4>Non-Medicare Patients</h4>
            <div class="frow">
                <div class="col4">
                    <label>Start Mileage</label>
                    <input type="text" name="data[start_mileage_2]" value="<?php echo isset($data['start_mileage_2']) ? $data['start_mileage_2'] : '0' ?>" />
                </div>
                <div class="col4">
                    <label>End Mileage</label>
                    <input type="text" name="data[end_mileage_2]" value="<?php echo isset($data['end_mileage_2']) ? $data['end_mileage_2'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Total Patients/ Draws</label>
                    <input type="text" name="data[total_patients_draws_2]" value="<?php echo isset($data['total_patients_draws_2']) ? $data['total_patients_draws_2'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Medicare or Non-Medicare</label>
                    <select name="data[category_2]">
                        <option value="">Select</option>
                        <?php
                        foreach ($categories as $category) {
                            $selected = isset($data['category_2']) && $data['category_2'] == $category ? 'selected' : '';
                            echo '<option value="' . $category . '" ' . $selected . '>' . $category . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Number of stops</label>
                    <input type="text" name="data[number_of_stops_2]" value="<?php echo isset($data['number_of_stops_2']) ? $data['number_of_stops_2'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Round Trip</label>
                    <input type="text" name="data[round_trip_2]" value="<?php echo isset($data['round_trip_2']) ? $data['round_trip_2'] : '1' ?>" />
                </div>

            </div>

        <?php } ?>




        <div class="frow submit">
            <input type="submit" class="button-primary" value="<?php echo isset($data['ID']) && $data['ID'] > 0 ? 'Save' : 'Add' ?>" />
            <input type="button" class="button btnclear" value="Clear" />

        </div>
        <input type="hidden" name="data[ID]" value="<?php echo isset($data['ID']) ? $data['ID'] : '' ?>" />

    </form>

    <br>
    <div class="frow">
        <label>Date:</label>
        <input type="search" class="hasdate" id="service_date_filter" data-write="1" value="<?php echo isset($_REQUEST['date']) ? $_REQUEST['date'] : '' ?>" />
    </div>
    <div id="listdiv">
        <?php
        //list
        echo  phlebotomist_list_bydate(!empty($_REQUEST['date']) ? $_REQUEST['date'] : $data['service_date']);
        ?>
    </div>

</div>

<script>
    jQuery(function($) {
        $('body').on('change', '#form_phle [name="data[collector_ID]"]', function() {
                //location.href = '<?php Tr_Base_Class_V6::link(array('col_id' => '')) ?>&col_id='+$(this).val();
                var $code = $('option:selected', $(this)).data('code');
                $('.col_phlebotomist_code').val($code);
            })
            .on('click', 'input.btnclear', function() {
                $('#form_phle [name*="data["]').each(function() {
                    var $name = $(this).attr('name');
                    if ($name.indexOf('service_date') == -1) {
                        $(this).val('');
                    }
                })
                $('#form_phle [type="submit"]').val('Add').removeAttr('disabled');
            })
            .on('click', '.pagination-links a', function() {
                //$('#listdiv table tbody').hide();
                $('#listdiv table tbody').html('<tr><td style="padding:30px" colspan=11><div class="loading center">loading...</div></td></tr>');
                $.ajax({
                    url: $(this).attr('href'),
                    type: 'post',
                    dataType: 'json',
                    data: {
                        trajax: 'check_service_date'
                    },
                    success: function(rs) {
                        $('#listdiv').html(rs.html);
                    },
                    error: function() {
                        $('#service_date_filter').change();
                    }
                })
                return false;
            })
            .on('change', '#service_date_filter', function() {
                var $date = $(this).val();
                var nextURL = '<?php echo Tr_Base_Class_V6::link(array('date' => '', 'paged' => '')) ?>&date=' + $date;
                history.pushState({}, null, nextURL);
                $('#listdiv').html('<div class="loading">loading...</div>');
                $.ajax({
                    url: nextURL,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        trajax: 'check_service_date'
                    },
                    success: function(rs) {
                        $('#listdiv').html(rs.html);
                    },
                    error: function() {
                        $('#service_date_filter').change();
                    }
                })
            })
            .on('click', 'a.editrow', function() {
                var $tr = $(this).parents('tr');
                var $data = $tr.data('value');
                $('#form_phle [type="submit"]').val('Save').removeAttr('disabled');
                var $id = $tr.data('id');
                $('[name="data[ID]"]').val($id);
                for (var k in $data) {
                    $('[name="data[' + k + ']"]').val($data[k]);
                }
                $('#form_phle [name="data[collector_ID]"]').change();

                $('html, body').animate({
                    scrollTop: $("#phlebotop").offset().top
                }, 700);

                return false;
            })
            .on('click', 'a.deleterow', function() {
                var $tr = $(this).parents('tr');
                var $id = $tr.data('id');

                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    data: {
                        trajax: 'delete_service',
                        'id': $id
                    },
                    success: function(rs) {
                        $('input.btnclear').click();
                        $tr.remove();
                    },
                    error: function() {
                        alert('Error. Please try again!');
                    }
                })
            })
    })
</script>