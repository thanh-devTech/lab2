<?php
global $wpdb;
include_once(TRCPDD_PATH . 'pages/formdata.php');

$tr_no_delete   = isset($tr_no_delete) ? $tr_no_delete : false;
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$settings       = [];
if (function_exists('tr_billing_get_settings')) $settings =  tr_billing_get_settings();
$enable_comm_kit = $settings['enable_commercial_kit'] == 1;

if (isset($_POST['save_payer'])) {
    $data = $_POST['payer'];

    if ($data['payer_ID'] > 0) {
        $old = $wpdb->get_row("select * from ci_payer_list where payer_ID = {$data['payer_ID']}", ARRAY_A);

        $wpdb->update('ci_payer_list', $data, array('payer_ID' => $data['payer_ID']));

        if ($old['payer_mnemonic'] != $data['payer_mnemonic'] || $old['payer_fin_mnemonic'] != $data['payer_fin_mnemonic']) {
            //need update ci_batches
            $wpdb->query("update ci_batches set hl7sent = 0 where insurance = {$data['payer_name']} and batch_id BETWEEN 20151111 AND curdate()");
        }


        Tr_Session_Class::add('Updated Payer');

        trpdd_log_user(get_current_user_id(), 'update_payer', tr_pdd_build_diff($old, $data), '', '', '', '', $data['payer_ID']);
    } else {
        unset($data['payer_ID']);
        $wpdb->insert('ci_payer_list', $data);
        $payer_ID = $wpdb->insert_id;
        Tr_Session_Class::add('Added Payer');

        trpdd_log_user(get_current_user_id(), 'insert_payer', '#' . $payer_ID, '', '', '', '', $payer_ID);
    }
} else if ($act == 'delete') {
    trpdd_log_user(get_current_user_id(), 'delete_payer', '#' . $_REQUEST['id'], '', '', '', '', $_REQUEST['id']);
    $wpdb->delete('ci_payer_list', array('payer_ID' => $_REQUEST['id']));
    Tr_Session_Class::add('Deleted Payer');
}



if ($act == 'export') {
    ob_clean();
    $file_name = 'payer_list.csv';
    header("Content-disposition: attachment; filename={$file_name}");
    header("Content-Type: text/csv");

    $rs = $wpdb->get_results("select * from ci_payer_list order by payer_name asc");

    $pay_types = array(
        'payer_medicaid' => 'medicaid',
        'payer_commercial_kit' => 'comm_kit',
        'payer_commercial' => 'commercial',
        'payer_unknown' => 'unknown',
        'payer_medicare' => 'medicare',
        'payer_other' => 'other',
        'payer_workmen' => 'workmen',
    );
    if (!$enable_comm_kit) {
        unset($pay_types['payer_commercial_kit']);
    }
    $headers = array('name', 'mnemonic', 'fin mnemonic', 'phone', 'address', 'city', 'state', 'zip', 'insurance type');

    $f = fopen('php://output', 'w');
    fputcsv($f, $headers);
    foreach ($rs as $r) {
        $types = array();
        foreach ($pay_types as $dk => $tk) {
            if ($r->{$dk}) {
                $types[] = $tk;
            }
        }
        $types = implode(',', $types);

        $row = array(
            $r->payer_name,
            $r->payer_mnemonic,
            $r->payer_fin_mnemonic,
            $r->payer_phone,
            $r->payer_address,
            $r->payer_city,
            $r->payer_state,
            $r->payer_zip,
            $types
        );
        fputcsv($f, $row);
    }
    exit;
} else if ($act == 'import') {
    if (!empty($_FILES['file']['tmp_name'])) {
        $data = trpdd_get_data($_FILES['file']['tmp_name'], null, array(array('name', 'ins_name')), true);

        if (isset($_REQUEST['removeall'])) {
            $wpdb->query("delete from ci_payer_list");
        }
        foreach ($data['data'] as $row) {
            $insert = array(
                'payer_name' => trpdd_get_string_exist($row, array('name', 'ins_name')),
                'payer_mnemonic' => trpdd_get_string_exist($row, array('mnemonic', 'key_code')),
                'payer_fin_mnemonic' => trpdd_get_string_exist($row, array('fin mnemonic', 'keyid', 'code')),
                'payer_phone' => trpdd_get_string_exist($row, array('phone')),
                'payer_address' => trpdd_get_string_exist($row, array('address', 'ins_addr1', 'address 1')),
                'payer_city' => trpdd_get_string_exist($row, array('city', 'ins_city')),
                'payer_state' => trpdd_get_string_exist($row, array('state', 'ins_state')),
                'payer_zip' => trpdd_get_string_exist($row, array('zip', 'ins_zip')),
            );

            $insert['payer_address'] = trim($insert['payer_address'], '-');
            if (empty($insert['payer_city']) && empty($insert['payer_state'])) {
                $address_arr = tr_parse_address($insert['payer_address']);
                $insert['payer_address'] = $address_arr['address'];
                $insert['payer_city'] = $address_arr['city'];
                $insert['payer_state'] = $address_arr['state'];
                $insert['payer_zip'] = $address_arr['zip'];
            }

            $class_code = trpdd_get_string_exist($row, array('payerclasscode'));
            $final_class_code = trpdd_get_string_exist($row, array('financialclasscode', 'financialreportingclass'));
            if (!empty($class_code)) {
                $insert['payer_class_code'] = $class_code;
            }
            if (!empty($final_class_code)) {
                $insert['payer_financial_class_code'] = $final_class_code;
            }

            if (stripos($row['insurance type'], 'medicaid') !== false) {
                $insert['payer_medicaid'] = 1;
            }
            if (stripos($row['insurance type'], 'commercial') !== false) {
                $insert['payer_commercial'] = 1;
            }
            if (stripos($row['insurance type'], 'comm_kit') !== false && $enable_comm_kit) {
                $insert['payer_commercial_kit'] = 1;
            }
            if (stripos($row['insurance type'], 'unknown') !== false) {
                $insert['payer_unknown'] = 1;
            }
            if (stripos($row['insurance type'], 'medicare') !== false) {
                $insert['payer_medicare'] = 1;
            }
            if (stripos($row['insurance type'], 'other') !== false) {
                $insert['payer_other'] = 1;
            }
            if (stripos($row['insurance type'], 'workmen') !== false) {
                $insert['payer_workmen'] = 1;
            }


            $insert['payer_name'] = trim($insert['payer_name']);
            $exist = $wpdb->get_var($wpdb->prepare("select payer_ID from ci_payer_list where payer_name = %s", $insert['payer_name']));
            foreach ($insert as $k => $vl) {
                if (empty($vl)) {
                    unset($insert[$k]);
                }
            }
            if ($exist > 0) {
                $wpdb->update('ci_payer_list', $insert, array('payer_ID' => $exist));
            } else {
                $wpdb->insert('ci_payer_list', $insert);
            }
        }
        echo 'count: ' . count($data['data']);
    }
?>
    <div class="wrap">
        <br>
        <h2>Import
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Payer</a>
        </h2>
        <br>
        <div class="wrapbox" style="margin:30px auto;width:300px">
            <form method="post" enctype="multipart/form-data">
                <div class="frow">
                    <label>File:</label>
                    <input type="file" name="file" />
                </div>
                <div class="frow">
                    <label><input type="checkbox" name="removeall" value="1"> Remove All before import</label>
                </div>
                <div class="frow">
                    <input type="submit" class="button" value="Import" />
                </div>
            </form>
        </div>
    </div>
<?php
} else if ($act == 'add' || $act == 'edit') {

    if ($act == 'edit') {
        $id = $_REQUEST['id'];
        $payer = $wpdb->get_row("select * from ci_payer_list where payer_ID = {$id}", ARRAY_A);
    }

?>
    <div class="wrap">
        <h2>Payer <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Payer</a></h2>
        <?php Tr_Session_Class::show() ?>
        <form method="post" class="need_validate">
            <div class="frow">
                <div class="col2">
                    <div class="row">
                        <label>Name</label>
                        <input type="text" name="payer[payer_name]" value="<?php echo @$payer['payer_name'] ?>" maxlength="150" required />
                        <input type="hidden" name="payer[payer_ID]" value="<?php echo @$payer['payer_ID'] ?>" />
                    </div>
                    <div class="row">
                        <label>Mnemonic</label>
                        <input type="text" name="payer[payer_mnemonic]" value="<?php echo @$payer['payer_mnemonic'] ?>" maxlength="30" />
                    </div>
                    <div class="row">
                        <label>Fin Mnemonic</label>
                        <input type="text" name="payer[payer_fin_mnemonic]" value="<?php echo @$payer['payer_fin_mnemonic'] ?>" maxlength="30" />
                    </div>
                    <div class="row">
                        <label>Phone</label>
                        <input type="text" name="payer[payer_phone]" value="<?php echo @$payer['payer_phone'] ?>" maxlength="25" />
                    </div>
                    <div class="row">
                        <label>Pverify ID</label>
                        <input type="text" name="payer[payer_pverify_ID]" value="<?php echo @$payer['payer_pverify_ID'] ?>" maxlength="25" />
                    </div>
                </div>
                <div class="col2">
                    <div class="row">
                        <label>Address</label>
                        <input type="text" name="payer[payer_address]" value="<?php echo @$payer['payer_address'] ?>" maxlength="250" />
                    </div>
                    <div class="row">
                        <label>City</label>
                        <input type="text" name="payer[payer_city]" value="<?php echo @$payer['payer_city'] ?>" maxlength="150" />
                    </div>
                    <div class="row">
                        <label>State</label>
                        <?php tr_select_option('payer[payer_state]', tr_get_states(), @$payer['payer_state']) ?>
                    </div>
                    <div class="row">
                        <label>Zip</label>
                        <input type="text" name="payer[payer_zip]" value="<?php echo @$payer['payer_zip'] ?>" maxlength="15" />
                    </div>
                    <div class="row">
                        <label>Search Name</label>
                        <input type="text" name="payer[payer_name_search]" value="<?php echo @$payer['payer_name_search'] ?>" maxlength="150" />
                    </div>


                </div>
            </div>

            <div class="row">
                <?php
                $mixes = array(
                    'payer_commercial' => 'Commercial',
                    'payer_commercial_kit' => 'Commercial Kit',
                    'payer_medicaid' => 'Medicaid',
                    'payer_unknown' => 'Unknown',
                    'payer_medicare' => 'Medicare',
                    'payer_workmen' => 'Workmen Comp',
                    'payer_other' => 'Other',
                );
                foreach ($mixes as $k => $txt) {
                    if (!$enable_comm_kit && $k === 'payer_commercial_kit') continue;
                ?>
                    <input type="hidden" name="payer[<?php echo $k ?>]" value="0" />
                    <label><input type="checkbox" name="payer[<?php echo $k ?>]" value="1" <?php checked($payer[$k]) ?> />
                        <?php echo $txt ?> </label>
                <?php
                }
                ?>
            </div>
            <br><br>

            <div class="row">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_payer" value="1" />
            </div>
        </form>
    </div>
<?php

} else {

?>
    <div class="wrap">
        <h2>Payer List <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2 bsty1">Add New</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'id' => '')) ?>" class="add-new-h2 bsty2">Import</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'export', 'trajax' => '1')) ?>" class="add-new-h2 bsty3">Export</a>
        </h2>
        <?php Tr_Session_Class::show() ?>
        <?php

        $where = ' where 1 = 1 ';
        if (!empty($_REQUEST['search'])) {
            $s = $_REQUEST['search'];
            $where .= " and (payer_name like '%{$s}%' or payer_fin_mnemonic='{$s}')";
        }
        $row_per_page = 100;
        $pagination = new Tr_Pagination_class($row_per_page);
        $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_payer_list {$where}"));
        $offset = $pagination->offset();
        $results = $wpdb->get_results("select * from ci_payer_list {$where} limit {$offset},{$row_per_page}");
        ?>

        <div class="tablenav">
            <div style="float:left">
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
                    <?php if (isset($_GET['pagetab'])) { ?>
                        <input type="hidden" name="pagetab" value="<?php echo $_GET['pagetab'] ?>" />
                    <?php } ?>
                    Search <input type="text" name="search" value="<?php echo isset($_REQUEST['search']) ? $_REQUEST['search'] : '' ?>" />
                    <input type="submit" class="button bsty6" value="Search" />
                </form>
            </div>
            <?php if ($pagination) echo $pagination->pagination() ?>
        </div>


        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Name</th>
                        <th>Mnemonic</th>
                        <th>Fin Mnemonic</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><?php echo $row->payer_ID ?></td>
                            <td><?php echo $row->payer_name ?></td>
                            <td><?php echo $row->payer_mnemonic ?></td>
                            <td><?php echo $row->payer_fin_mnemonic ?></td>
                            <td>
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->payer_ID)) ?>">Edit</a>
                                <?php if (!$tr_no_delete) : ?>
                                    -
                                    <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->payer_ID)) ?>">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
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