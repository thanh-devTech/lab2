<?php
global $wpdb, $current_role;

$btype  = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'blood';
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$is_export = false;
$group_ids = array();
if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'change_standing_status') {

    $mid     = $_REQUEST['id'];

    $status = $_REQUEST['status'];
    $return = array('status' => 'ok', 'msg' => 'Changed Status Successfully');
    if ($status == 'assigned') {
        $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
        if (empty($col_id)) {
            $user = wp_get_current_user();
            $wpdb->insert('ci_collector', array(
                'col_first' => $user->first_name,
                'col_last' => $user->last_name,
                'col_email' => $user->user_email,
            ));
            $col_id = $wpdb->insert_id;
            update_user_meta(get_current_user_id(), '_collector_id', $col_id);
        }
        $wpdb->update($batch_tbl, array(
            'col_ID' => $col_id,
        ), array('mid' => $mid));
    } else {
        $wpdb->update($batch_tbl, array(
            'batch_status' => $status
        ), array('mid' => $mid));
    }
    //trpdd_update_batch_meta($mid, $btype, 'status', $status);

    wp_send_json($return);
} else if (isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'], 'export') !== false) {
    $is_export = true;
}

$enable_patient_room = tr_get_option('ci_pdf_data', 'enable_patient_room', false);

$showallreqs = false;
$enable_stat = false;
$btypes = array();

if (current_user_can('ci_standing_orders')) {
    $btypes[] = 'blood';
    $enable_stat = tr_get_option('ci_pdf_data', 'enable_stat');
}
if (current_user_can('ci_standing_orders_pcr')) {
    $btypes[] = 'pcr';
}
if (empty($btypes)) {
    $btypes[] = 'blood';
}

$btypes_str = implode("','", $btypes);

wp_enqueue_script('data-admin-tbl', TRCPDD_URL . 'js/admin_table.min.js', array(), TRCPDD_VS);




$rs = $wpdb->get_results("select * from ci_test_panels where te_type in ('{$btypes_str}') ");
$all_tests = array();
foreach ($rs as $r) {
    $all_tests[$r->te_ID] = $r;
}
$rs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_type in ('{$btypes_str}')");
$all_groups = array();
foreach ($rs as $r) {
    $all_tests['GROUP_' . $r->tg_ID] = $r;
}
$clinic_ids = $wpdb->get_col("select cli_ID from ci_collector col 
join ci_clinic_collector cc ON cc.col_ID = col.col_ID
where col.col_phlebotomist_code!='' ");

$clinicids2 = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_phlebotomist = 1 ");

$clinic_ids_str = implode(',', $clinic_ids);
$clinic_ids2_str = implode(',', $clinicids2);



$conditions = trpdd_get_batch_conditions();

$from_date  = isset($_REQUEST['from']) ? $_REQUEST['from'] : date('Y-m-d', strtotime('-30 days'));
$to_date    = isset($_REQUEST['to']) ? $_REQUEST['to'] : '';
$orderby    = "ba.doc desc";
if ($enable_stat) {
    $orderby = ' stat desc, ba.doc desc';
}
$where      = ' ba.is_saved = 0 ' . $conditions['where'];

$where_date = '';

if (!empty($from_date)) {
    $where_date .= " and doc >= '{$from_date}' ";
}
if (!empty($to_date)) {
    $where_date .= " and doc <= '{$to_date} 23:59:59' ";
}
$where .= $where_date;

$all_status = trpdd_get_standing_order_statuses($btypes);
$all_status_str = implode("','", array_keys($all_status));
$where .= " and ba.batch_status  in ( null,'{$all_status_str}') ";
//$where .= " and ba.batch_status not in ( 'archived') ";


$row_per_page   = 20;
$pagination     = new Tr_Pagination_class2($row_per_page);
$offset         = $pagination->offset();
$total          = 0;

foreach ($btypes as $btype) {


    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $where2 = '';
    $byjoin = true;

    if (!empty($clinic_ids2_str)) {

        $showallreqs = true;
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " left JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";

        $where2 .= " and (me.mid > 0 or ba.cli_ID in ({$clinic_ids2_str}) )";
    } else if (tr_get_option('ci_req_settings_' . $btype, 'show_all_standing_order')) {
        $showallreqs = true;
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " left JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";
        $where2 .= " and (me2.mid is null ) ";

        if (!tr_current_role_in(array('collector', 'administrator', 'AdminCIDW'))) {
            $where2 .= " and (me.mid > 0 or ba.cli_ID in ({$clinic_ids_str}) )";
        }
    } else if (tr_get_option('ci_req_settings_' . $btype, 'Phlebotomist_showallblood')) {
        $showallreqs = true;
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " left JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";
        $where2 .= " and (me2.mid is null ) ";
    }


    if ($byjoin) {
        $join_mt = " JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
    }

    $sql_total        = "select count(*) from {$batch_tbl} ba
    {$join_mt}
    where {$where} {$where2}";
    $total += $wpdb->get_var($sql_total);
}
$pagination->setTotal($total);

$child_query = array();
foreach ($btypes as $btype) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $where2 = '';
    $byjoin = true;


    if (!empty($clinic_ids2_str)) {
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";
        $where2 .= " and (me.mid > 0 or ba.cli_ID in ({$clinic_ids2_str}) )";
    } else if (tr_get_option('ci_req_settings_' . $btype, 'show_all_standing_order')) {
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";

        $where2 .= " and (me2.mid is null ) ";

        if (!tr_current_role_in(array('collector', 'administrator', 'AdminCIDW'))) {
            $where2 .= " and (me.mid > 0 or ba.cli_ID in ({$clinic_ids_str}) )";
        }
    } else if (tr_get_option('ci_req_settings_' . $btype, 'Phlebotomist_showallblood')) {
        $byjoin = false;
        $join_mt = " left JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " left JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
        $join_mt .= " left JOIN {$batch_tbl}_meta me2 ON ba.mid = me2.meta_value and me2.meta_key = 'rl' ";
        $where2 .= " and (me2.mid is null ) ";
    }

    if ($byjoin) {
        $join_mt = " JOIN {$batch_tbl}_meta me ON ba.mid = me.mid and me.meta_key = 'rl' ";
        if (!empty($where_date)) {
            $join_mt = " JOIN {$batch_tbl}_meta me ON (ba.mid = me.mid or ba.mid = me.meta_value) and me.meta_key = 'rl' ";
        }
    }

    $fieldsadding = '';
    if ($enable_patient_room) {
        $fieldsadding .= ', pa.pa_room';
    }

    if ($enable_stat) {
        $fieldsadding .= $btype == 'blood' ? ', ba.stat ' : ", 0 as stat ";
    }

    if (tr_get_option('ci_pdf_data', 'req_patient_location')) {
        $fieldsadding .= ', pa.pa_location';
    }

    $child_query[] = "( 
        select ba.mid,
        ba.assen,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.patient_name,
        ba.type_of_specimen,
        ba.doc,
        ba.notes,
        ba.panel_tests_requested,
        ba.position,
        '{$btype}' as btype,
        pa.pa_address,
        pa.pa_dob,
        cli.cli_city,
        ba.batch_status as status
        {$fieldsadding}
        from {$batch_tbl} ba
        {$join_mt}
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        JOIN ci_clinic cli ON cli.cID = ba.cli_ID
        where {$where}  {$where2}
        GROUP by ba.mid
    )";
}
$child_query = implode(" UNION ALL ", $child_query);


$sql = "select * from ({$child_query}) as ba
order by {$orderby}
";
if (!$is_export) {
    $sql .= " limit {$offset},{$row_per_page} ";
}
$rows            = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
    var_dump($sql);
}

foreach ($rows as $prow) {
    $child_query = array();
    foreach ($btypes as $btype) {
        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $fieldsadding = '';
        if ($enable_patient_room) {
            $fieldsadding .= ', pa.pa_room';
        }

        $child_query[] = "( 
                            select ba.mid,
                            ba.assen,
                            ba.requesting_practice,
                            ba.requesting_physician,
                            ba.patient_name,
                            ba.type_of_specimen,
                            ba.doc,
                            ba.notes,
                            ba.panel_tests_requested,
                            ba.position,
                            '{$btype}' as btype,
                            pa.pa_address,
                            pa.pa_dob,
                            cli.cli_city,
                            ba.batch_status as status
                            {$fieldsadding}
                        from {$batch_tbl} ba
                        JOIN {$batch_tbl}_meta me ON ba.mid = me.meta_value and me.meta_key = 'rl'
                        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                        JOIN ci_clinic cli ON cli.cID = ba.cli_ID
                        where me.mid = {$prow->mid} 
                        )";
    }
    $child_query = implode(" UNION ALL ", $child_query);

    $sql_child = "select * from ({$child_query}) as ba order by doc asc";

    $prow->childs     = empty($where_date) ? $wpdb->get_results($sql_child) : array();

    array_unshift($prow->childs, $prow);

    foreach ($prow->childs as $row) {
        $testids        = explode(',', $row->panel_tests_requested);
        $row->panels    = array();
        $row->panels_print = array();
        $row->group_ids  = array();
        foreach ($testids as $testid) {
            $testid = trim($testid);
            if (stripos($testid, 'group_') !== false) {
                if (isset($all_tests[$testid])) {
                    $row->group_ids[] = str_ireplace('group_', '', $testid);
                    $row->panels[] = $all_tests[$testid]->tg_name;
                }

                if (isset($all_tests[$testid]->tg_label_name) && !empty($all_tests[$testid]->tg_label_name)) {
                    $row->panels_print[] = $all_tests[$testid]->tg_label_name;
                }
            } else {
                if (isset($all_tests[$testid]) && !in_array($all_tests[$testid]->te_group_id, $group_ids)) {
                    $row->panels[] = $all_tests[$testid]->te_name;
                }
                if (isset($all_tests[$testid]->te_label_barcode) && !empty($all_tests[$testid]->te_label_barcode)) {
                    $row->panels_print[] = $all_tests[$testid]->te_label_barcode;
                }
            }
        }
        $row->panels_str = implode(', ', $row->panels);

        $row->panels_print_str = implode('|', $row->panels_print);
    }
}


if ($is_export) {

    tr_pdd_header('csv', 'Orders.csv');
    $header = array(
        'Accession',
        'Patient Name',
        'DOB',
        'Location',
        'Facility',
        'Patient Address',
        'Provider',
        'Collection',
        'Type Of Specimen',
        'Panels',
        'Notes',
        'Status'
    );
    $f = fopen('php://output', 'w');
    fputcsv($f, $header);
    foreach ($rows as $prow) {
        foreach ($prow->childs as $row) {
            $status_text = isset($all_status[$row->status]) ? $all_status[$row->status] : $row->status;
            $rowdata = array(
                $row->assen,
                $row->patient_name,
                $row->pa_dob,
                $row->pa_location,
                $row->requesting_practice,
                $row->pa_address,
                $row->requesting_physician,
                $row->doc,
                $row->type_of_specimen,
                $row->panels_str,
                $status_text,
            );
            fputcsv($f, $rowdata);
        }
    }
    exit;
}


$pagetitle = $showallreqs ? 'Regular Orders and Standing Orders' : 'Standing Orders';
if (tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) {
    $pagetitle = 'Simply Orders';
}
?>
<div class="wrap">
    <h2><?php echo $pagetitle ?></h2>
    <div class="tablenav top">
        <form method="get" class="left">
            <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
            <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
            <label>From Date:</label>
            <input type="search" class="hasdate" name="from" value="<?php echo $from_date ?>" />
            <label>To:</label>
            <input type="search" class="hasdate" name="to" value="<?php echo $to_date ?>" />
            <input type="submit" class="button-primary" value="Search" />
            <input type="submit" class="button todaybtn" data-today="<?php echo date_i18n('Y-m-d') ?>" value="Today" />
            <input type="submit" class="button" name="trajax" value="Export" />
        </form>
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>
    <div class="wraptbl">

        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" style="width:1600px">
            <thead class="sticky">
                <tr>
                    <th style="width:20px"></th>
                    <th style="width:8%">Accession</th>
                    <th style="min-width:150px">Patient Name</th>
                    <th class="center">DOB</th>
                    <?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
                        <th>Location</th>
                    <?php } ?>
                    <th>Facility</th>
                    <?php if (!tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) { ?>
                        <th style="min-width:150px">Patient Address</th>
                        <th>Provider</th>
                    <?php } ?>
                    <th style="width:90px">Collection</th>
                    <th style="width:100px">Type of Specimen</th>
                    <th>Panels</th>
                    <th>Notes</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $prow) {
                    $tbl_group_id = $prow->mid;

                    foreach ($prow->childs as $ci => $row) {

                        $row->panels_str = trim(str_replace(',', "\n", $row->panels_str));

                        $rowclass = $ci == 0 ? 'parent' : 'child';

                        if (count($prow->childs) <= 1) $rowclass .= ' regular_row';
                ?>
                        <tr class="<?php echo $rowclass ?>" data-btype="<?php echo $row->btype ?>" data-grid="<?php echo $tbl_group_id ?>" data-mid="<?php echo $row->mid ?>" data-dob="<?php echo $row->pa_dob ?>" data-doc="<?php echo $row->doc ?>" data-position="<?php echo $row->position ?>" data-panels_print="<?php echo $row->panels_print_str ?>">
                            <td><?php
                                echo count($prow->childs) > 1 ? '<i></i>' : '';
                                if ($enable_stat && $row->stat) {
                                    echo '<red>STAT</red>';
                                }
                                ?></td>
                            <td>
                                <a class="assen" target="_blank" href="<?php Tr_Base_Class_V6::link(array('page' => $row->btype . '_req_form', 'act' => 'edit', 'id' => $row->mid)) ?>"><?php echo $row->assen ?></a>
                                <div class="row-actions">
                                    <?php echo '<a class="print_assen_btn" href="#">Print label</a>' ?>
                                </div>
                            </td>
                            <td>
                                <span class="col-patient_name"><?php echo $row->patient_name ?></span>
                            </td>
                            <td class="r-date center">
                                <?php echo $row->pa_dob ?>
                            </td>
                            <?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
                                <td><?php echo $row->pa_location ?></td>
                            <?php } ?>
                            <td>
                                <?php echo $row->requesting_practice ?>
                                <div><?php echo $row->cli_city ?></div>
                            </td>
                            <?php if (!tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) { ?>
                                <td>
                                    <?php echo $row->pa_address ?>
                                    <?php if ($enable_patient_room) {
                                        echo !empty($row->pa_room) ? '<div>Room#: ' . $row->pa_room . '</div>' : '';
                                    } ?>
                                </td>
                                <td><?php echo $row->requesting_physician ?></td>
                            <?php } ?>
                            <td class="r-date"><?php echo $row->doc ?></td>
                            <td><?php echo $row->type_of_specimen ?></td>
                            <td class="panels">
                                <div><?php echo nl2br($row->panels_str) ?></div>
                            </td>
                            <td><?php echo nl2br($row->notes) ?></td>
                            <td class="batch_status" data-status="<?php echo $row->status ?>"></td>
                        </tr>
                <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <div class="tablenav bottom">
            <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
        </div>
    </div>
</div>
<style>
    table tr.child {
        display: none;
    }

    td a.assen {
        display: block;
        margin-top: 22px;
    }

    td.panels,
    td.panels div {
        font-size: 9px;
        line-height: 10px;
    }

    td.panels div {
        max-height: 60px;
        overflow-y: auto;
    }

    tr.regular_row {
        background: #e3e6ec !important;
    }
</style>
<script>
    jQuery(function($) {
        var $statuses = <?php echo json_encode($all_status) ?>;
        $('.batch_status').each(function() {
            var $html = '<select style="width:100%;width:150px">';
            for (k in $statuses) {
                $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
            }
            $html += '</select>';
            $(this).html($html);
        });
        $('.batch_status select').change(function() {

            var $this = $(this);
            var $tr = $this.parents('tr');
            var $vl = $(this).val();
            var $id = $tr.data('mid');
            var $btype = $tr.data('btype');
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: {
                    trajax: 'change_standing_status',
                    id: $id,
                    btype: $btype,
                    status: $vl
                },
                success: function(rs) {
                    $pr.find('.sloading').remove();
                    if (rs.status == 'ok') {} else {
                        $this.change();
                    }
                },
                error: function() {
                    $this.change();
                }
            })
        });
        $('body').on('click', '.todaybtn', function() {
            var $today = $(this).data('today');
            $('[name="from"]').val($today);
            $('[name="to"]').val($today);
        })
    })
</script>