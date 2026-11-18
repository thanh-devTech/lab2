<?php
global $wpdb, $current_role;

$trajax = isset($_POST['trajax']) ? $_POST['trajax'] : '';

if ($trajax == 'add_cart') {

    wc_load_cart();
    WC()->cart->empty_cart();

    global $wpdb;

    $price_product  = isset($_POST['price']) ? explode(',', sanitize_text_field($_POST['price'])) : [];
    $p_names        = isset($_POST['p_names']) ? explode(',', sanitize_text_field($_POST['p_names'])) : [];
    $p_ids          = isset($_POST['p_ids']) ? explode(',', sanitize_text_field($_POST['p_ids'])) : [];

    $assen          = isset($_POST['assen']) ? $_POST['assen'] : '';
    $patient_name   = isset($_POST['patient_name']) ? $_POST['patient_name'] : '';
    $btype          = isset($_POST['btype']) ? $_POST['btype'] : '';

    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $paid = $wpdb->get_var("select paid from {$batch_tbl} where assen = '{$assen}' ");
    if ($paid == -1 || $paid == 1) {
        wp_send_json_error([
            'message' => 'Paid',
        ]);
    }

    $added_products = [];

    foreach ($p_ids as $index => $p_id) {
        $p_id   = intval($p_id);
        $p_name = trim($p_names[$index]);
        $p_price = floatval($price_product[$index]);

        if ($p_price == 0) continue;

        $existing_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value = %d LIMIT 1",
            '_group_p',
            $p_id
        ));

        if (!$existing_product_id) {
            $post_data = [
                'post_title'   => $p_name,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'product',
            ];
            $existing_product_id = wp_insert_post($post_data);

            if ($existing_product_id) {
                update_post_meta($existing_product_id, '_price', $p_price);
                update_post_meta($existing_product_id, '_regular_price', $p_price);
                update_post_meta($existing_product_id, '_group_p', $p_id);
                update_post_meta($existing_product_id, '_group_p_name_backup', $p_name);
                update_post_meta($existing_product_id, '_group_p_price_backup', $p_price);
                update_post_meta($existing_product_id, '_stock_status', 'instock');
                update_post_meta($existing_product_id, '_manage_stock', 'no');
                wp_set_object_terms($existing_product_id, 'simple', 'product_type');
            }
        } else {
            $current_title = get_the_title($existing_product_id);
            if ($current_title !== $p_name && !empty($p_name)) {
                wp_update_post([
                    'ID'         => $existing_product_id,
                    'post_title' => $p_name,
                ]);
                update_post_meta($existing_product_id, '_group_p_name_backup', $p_name);
            }

            $current_price = get_post_meta($existing_product_id, '_price', true);
            if ($current_price != $p_price) {
                update_post_meta($existing_product_id, '_price', $p_price);
                update_post_meta($existing_product_id, '_regular_price', $p_price);
                update_post_meta($existing_product_id, '_group_p_price_backup', $p_price);
            }
        }

        if ($existing_product_id) {


            if (WC()->customer) {

                $billing_fields = [
                    'first_name',
                    'last_name',
                    'company',
                    'address_1',
                    'address_2',
                    'city',
                    'postcode',
                    'country',
                    'state',
                    'email',
                    'phone'
                ];

                foreach ($billing_fields as $field) {
                    WC()->customer->{"set_billing_{$field}"}('');
                }


                $patient_name = trim($patient_name);
                $parts = preg_split('/\s+/u', $patient_name);

                if (count($parts) === 1) {
                    $first_name = $parts[0];
                    $last_name  = '';
                } else {
                    $first_name = array_pop($parts);
                    $last_name  = implode(' ', $parts);
                    $last_name = str_replace(',', '', $last_name);
                }

                WC()->customer->set_billing_first_name($first_name);
                WC()->customer->set_billing_last_name($last_name);
                WC()->customer->set_billing_email('guest@example.com');
                WC()->customer->set_billing_country('US');

                WC()->customer->save();
            }

            WC()->session->set('assen', $assen);
            WC()->session->set('patient_name', $patient_name);
            WC()->session->set('btype', $btype);
            WC()->cart->add_to_cart($existing_product_id);
            $added_products[] = $existing_product_id;
        }
    }

    if (!empty($added_products)) {
        wp_send_json_success([
            'message' => 'Added to cart',
            'added_products' => $added_products
        ]);
    }

    wp_send_json_error(['message' => 'No products added']);
    exit;
}


$btype  = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'blood';
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$is_export = false;
$group_ids = array();


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
$where      = '1 ' . $conditions['where'];

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
        b2.dc_amount AS d_amount,
        ba.batch_status as status,
        ba.paid
        {$fieldsadding}
        from {$batch_tbl} ba
        {$join_mt}
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        JOIN ci_clinic cli ON cli.cID = ba.cli_ID

        LEFT JOIN ci_bill_bc_cashpay_test t2 
        ON FIND_IN_SET(CONCAT(t2.ct_test_ID,''), ba.panel_tests_requested)
        LEFT JOIN ci_bill_bc_cashpay b2 
        ON b2.dc_ID = t2.ct_dc_ID

        where {$where} {$where2}
        AND ba.insurance= 'No Insurance - Cash'
        AND b2.dc_amount IS NOT NULL
        AND b2.dc_amount > 0
        
        GROUP by ba.mid
    )";
}

if (count($btypes) === 1) {
    $sql_base = reset($child_query);
} else {
    $sql_base = implode(" UNION ALL ", $child_query);
}

$sql = "SELECT * FROM ({$sql_base}) as ba ORDER BY {$orderby}";

$sql_count = "SELECT COUNT(*) FROM ({$sql_base}) as ba ORDER BY {$orderby}";

$row_per_page = 10;
$pagination     = new Tr_Pagination_class2($row_per_page);
$offset         = $pagination->offset();
$total_rows   = (int) $wpdb->get_var($sql_count);
$pagination->setTotal($total_rows);

$sql .= " limit {$offset},{$row_per_page} ";

$rows = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
    var_dump($sql);
}

// --- Map dc_amount ---
$dc_map = [];
$results = $wpdb->get_results("
    SELECT t.ct_test_ID, b.dc_amount, b.dc_panel_search, b.dc_class
    FROM ci_bill_bc_cashpay_test t
    INNER JOIN ci_bill_bc_cashpay b ON b.dc_ID = t.ct_dc_ID
    WHERE b.dc_amount IS NOT NULL AND b.dc_amount > 0
");

foreach ($results as $r) {
    $dc_map[$r->ct_test_ID] = [
        'amount'       => floatval($r->dc_amount),
        'panel_search' => intval($r->dc_panel_search),
        'class_name'   => trim($r->dc_class)
    ];
}

foreach ($rows as $row) {
    $testids = array_filter(array_map('trim', explode(',', $row->panel_tests_requested)));

    $row->panels = [];
    $row->panels_print = [];
    $row->panel_ids = [];
    $row->panel_amounts = [];
    $row->panel_price = [];

    $seen_class = [];
    $seen_class_calc = [];

    foreach ($testids as $testid) {
        if (!isset($dc_map[$testid])) continue;
        $map_item  = $dc_map[$testid];
        $dc_amount = $map_item['amount'];
        $panel_search = $map_item['panel_search'];
        $class_name = $map_item['class_name'];
        $testid = trim($testid);

        if ($panel_search == 1) {

            if (isset($seen_class[$class_name])) {
                continue;
            }
            $display_name = $class_name;
            $seen_class[$class_name] = true;

            if (!isset($seen_class_calc[$class_name])) {
                $calc_amount = $dc_amount;
                $seen_class_calc[$class_name] = true;
            } else {
                $calc_amount = 0;
            }
        } else {
            $display_name = isset($all_tests[$testid])
                ? $all_tests[$testid]->te_name
                : $testid;
            $calc_amount = $dc_amount;
        }

        $row->panels[]        = $display_name;
        $row->panel_ids[]     = $testid;
        $row->panel_amounts[] = $dc_amount;
        $row->panel_price[]   = $calc_amount;

        if (isset($all_tests[$testid]) && !empty($all_tests[$testid]->te_label_barcode)) {
            $row->panels_print[] = $all_tests[$testid]->te_label_barcode;
        }
    }

    $row->panels_str        = implode(', ', $row->panels);
    $row->panels_name_str   = implode(', ', $row->panels);
    $row->panels_print_str  = implode('|', $row->panels_print);
    $row->panels_id_str     = implode(',', $row->panel_ids);
    $row->panel_price_str   = implode(',', $row->panel_price);
    $row->panels_amount_str = implode(', ', $row->panel_amounts);

    $row->panels_amount_total = array_sum($row->panel_price);
}


$pagetitle = 'Testing Payments';
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
        </form>
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>
    <div class="wraptbl">

        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" style="//width:1800px">
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
                    <th>Type of Specimen</th>
                    <th>Panels/Tests</th>
                    <th>Total Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $row) {
                    $row->panels_str = trim(str_replace(',', "\n", $row->panels_str));
                ?>
                    <tr class="<?php echo $rowclass ?>" data-btype="<?php echo $row->btype ?>" data-grid="<?php echo $tbl_group_id ?>" data-mid="<?php echo $row->mid ?>" data-dob="<?php echo $row->pa_dob ?>" data-doc="<?php echo $row->doc ?>" data-position="<?php echo $row->position ?>" data-panels_print="<?php echo $row->panels_print_str ?>">
                        <td>

                        <td>
                            <?php echo $row->assen ?></a>
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
                        </td>
                        <td><?php echo $row->panels_amount_total; ?> $</td>
                        <td>
                            <?php if ($row->paid == 0) { ?>
                                <button class="button btn_add_cart"
                                    data-price="<?php echo $row->panel_price_str; ?>"
                                    data-names="<?php echo $row->panels_name_str; ?>"
                                    data-ids="<?php echo $row->panels_id_str; ?>"
                                    data-assen="<?php echo $row->assen; ?>"
                                    data-patient_name="<?php echo $row->patient_name; ?>"
                                    data-btype="<?php echo $row->btype; ?>">Pay Now</button>
                            <?php } ?>
                        </td>
                    </tr>
                <?php
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
        font-size: 14px;
    }

    td.panels div {
        max-height: 60px;
        overflow-y: auto;
    }

    tr.regular_row {
        background: #e3e6ec !important;
    }
</style>

<div id="ajax-result-checkout" style="display:none;">
    <div id="ajax-content-checkout" style="width:100%;height:100%">Loading...</div>
</div>

<script>
    jQuery(function($) {
        $('body').on('click', '.todaybtn', function() {
            var $today = $(this).data('today');
            $('[name="from"]').val($today);
            $('[name="to"]').val($today);
        });
    });

    jQuery(document).on('click', '.btn_add_cart', function(e) {

        e.preventDefault();
        var price_product = jQuery(this).data('price');
        var ids = jQuery(this).data('ids');
        var names = jQuery(this).data('names');
        var assen = jQuery(this).data('assen');
        var patient_name = jQuery(this).data('patient_name');
        var btype = jQuery(this).data('btype');
        var btn_pay = $(this);
        $(this).html("Loading...");

        jQuery.post("admin.php?page=blood_phlebotomist&tab=testing_payments", {
            trajax: 'add_cart',
            price: price_product,
            p_names: names,
            p_ids: ids,
            assen: assen,
            patient_name: patient_name,
            btype: btype
        }, function(res) {
            $(btn_pay).html("Pay Now");
            if (res.success) {

                tb_show("WooCommerce Checkout", "/checkout/?TB_iframe=true");

            } else {
                alert(res.data.message || "Add to cart failed!");
            }

        });

        return false;
    });
</script>