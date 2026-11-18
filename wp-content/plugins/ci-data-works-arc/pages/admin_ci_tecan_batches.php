<?php
global $wpdb, $current_role, $blog_id;
$user_id = get_current_user_id();

include_once(TRCPDD_PATH . 'pages/formdata.php');

$where = '';
$orderby = ' order by ba.batch_id desc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';

$joins = '';
//page
$row_per_page = 10;
$pagination = new Tr_Pagination_class($row_per_page);
$offset = $pagination->offset();


if (!empty($_REQUEST['s'])) {

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(" and (ba.patient_name like %s) ", $explodeString);
    } else {
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen = %s) ", '%' . $_REQUEST['s'] . '%', $_REQUEST['s']);
    }
}

if (tr_current_role_in(array('office_manager', 'clinic'))) {

    $cid = get_user_meta($user_id, '_clinic_id', true);
    $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
} else if (tr_current_role_in(array('multi_site_manager'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        //can see all clinics
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
            $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
        }
        $ms_cli_ids_str = implode(',', $ms_cli_ids);
        $where .= " and ba.cli_ID in ( {$ms_cli_ids_str} )";
    }
}


$joins .= " JOIN ci_batches ba ON ba.assen = te.te_assen
    JOIN ci_labs lab  ON lab.lab_ID = ba.lab_id
     LEFT JOIN ci_reports re ON  ba.assen = re.patient_id ";
$where .= " and ((re.estatus != 'c' and ba.batch_status='completed') or (ba.batch_status='cold_storage'))";

$sql_total = "select count(distinct(ba.batch_id)) as c from ci_tecan te
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) from ci_tecan te
    {$joins}
        where 1 {$where}
        order by ba.batch_id desc limit {$offset},{$row_per_page}";


$time_start  = microtime(true);
$pagination->setTotal($wpdb->get_var($sql_total));
$q_total = microtime(true) - $time_start;

$time_start  = microtime(true);
$batch_ids = $wpdb->get_col($sql_limit);
$q_limit = microtime(true) - $time_start;

$batch_ids = implode("','", $batch_ids);
$where .= " and ba.batch_id in('{$batch_ids}') ";


$sql = "select ba.batch_id,
        ba.doc,
        lab.lab_ID,
        lab.lab_dba_init,
        te.te_letter,
        te.te_number,
        te.te_created
    from ci_tecan te
    {$joins}
    where 1 {$where} group by ba.mid {$orderby}";


$time_start  = microtime(true);
$tecans = $wpdb->get_results($sql);

$count_rows = count($tecans);




if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo 'qtotal: ' . $q_total, '<br>';
    echo '$sql_limit: ' . $sql_limit, "<br><br>";
    echo 'qlimit: ' . $q_limit, '<br><br>';
    echo $sql, '<br><br>';
    $r = trpdd_get_tecan_letters();
    var_dump($r);
}
?>
<h2>Tecan Batches Queue</h2>
<?php Tr_Session_Class::show() ?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Patient/Accession:</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
    <input type="submit" class="button bsty6" value="Search" />


</form>

<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead>
            <tr>
                <th style="width:25px">#</th>
                <th style="width: 9%;">Batch</th>
                <th style="width: 5%;">Position</th>
                <th style="width: 6%;">Lab</th>
                <th style="width: 11%;">Patient Name</th>
                <th style="width: 14%;">Clinic</th>
                <th style="width: 11%;">Doctor</th>
                <th style="width: 10%;">Date</th>
                <th style="width:2%"></th>
                <th style="width:12%">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            $add_cnum = array();
            if (count($tecans) > 0) {
                foreach ($tecans as $i => $row) {
                    if (!is_array($batches[$row->batch_id])) {
                        $batches[$row->batch_id] = array();
                    }
                    //$batches[$row->batch_id]['date'] = $row->collection_date;
                    $batches[$row->batch_id]['date'] = date('Y-m-d', strtotime($row->te_created));
                    $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
                    $batches[$row->batch_id]['count']++;
                    if ($row->te_number > 0) {
                        $batches[$row->batch_id]['cnum'] = trpdd_format_tecan_letters($row->te_letter, $row->te_number);
                    }
                }
            ?>

                <?php
                foreach ($batches as $batch_id => $d) {
                    $cnum = $d['cnum'];
                ?>
                    <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                        <td><i></i></td>
                        <td title="<?php echo $batch_id ?>"><?php echo empty($cnum) ? $batch_id : $cnum ?></td>
                        <td></td>
                        <td><a class="show_batch_tecan_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id ?>"></a></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><?php echo $d['date'] ?></td>
                        <td></td>
                        <td></td>
                        <td>
                            <a class="print_barcode" data-id="<?php echo $bid ?>" data-cnum="<?php echo $cnum ?>">Print Barcode</a>
                        </td>
                    </tr>
                    <tr class="child p_batch_<?php echo $batch_id ?>">
                        <td colspan="11" class="nopadding">
                            <table class="report_tbl">
                                <?php
                                foreach ($d['labs'] as $lab_id => $lab_name) {
                                ?>
                                    <tr class="parent_c" data-p="lab_<?php echo $lab_id . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">
                                        <td style="width: 25px;"><i></i></td>
                                        <td style="width:9%"></td>
                                        <td style="width:5%"></td>
                                        <td><?php echo $lab_name ?></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td align="right"></td>
                                        <td></td>
                                    </tr>
                                    <tr class="child_c p_lab_<?php echo $lab_id . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">
                                        <td colspan="10" class="nopadding"></td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </table>
                        </td>
                    </tr>
                <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="11">Not found.</td>
                </tr>
            <?php
            }

            ?>
        </tbody>
    </table>
</div>

<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<script>
    jQuery(function($) {
        function trcpp_load_tecans($batch_id, $lab_id) {
            var $obj = $('tr.p_lab_' + $lab_id + $batch_id + ' td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_tecans',
                    batch_id: $batch_id,
                    lab_id: $lab_id
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('<input type="checkbox" value="' + $(this).html() + '"/>');
                    })
                }
            })
        }
        $('body').on('click', 'a.delete', function() {
            if (confirm('Do you want delete?') == false) {
                return false;
            }
        })
        $('a.show_batch_tecan_files').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_tecan_files&batch_id=' + $bid + '&width=800');
            return false;
        })

        $('tr.parent').click(function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                var $obj = $('tr.p_' + $(this).data('p'));
                $obj.show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_tecans', $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);
                if ($('tr.p_' + $(this).data('p') + ' tr.parent_c').length == 1) {
                    $('tr.p_' + $(this).data('p') + ' tr.parent_c').click();
                }
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child_c').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_tecans', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                trcpp_load_tecans($batch_id, $lab_id);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('body').on('click', 'a.print_barcode', function() {
            var $code = $(this).data('id');
            var $cnum = $(this).data('cnum');
            tb_show('Barcode ' + $code, '?tr_action=ci_print_barcode_number&type=short&code=' + $code + '&cnum=' + $cnum + '&width=400&height=400');
            return false;
        })
        $('.drugs').click(function() {
            $(this).parents('form').submit();
        })
        $old_active = getCookie('active_tecans');
        if ($old_active && $old_active != null) {
            var $obj = $('tr.p_' + $old_active);
            try {
                $obj.show();
                $('[data-p="' + $old_active + '"]').data('showed', '1');
                if ($old_active.indexOf('lab_') > -1) {
                    $obj.parents('.child').show();
                }
                $('body,html').scrollTop($obj.offset().top - 150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                setTimeout(function() {
                    trcpp_load_tecans($batch_id, $lab_id);
                }, 500);
            } catch (e) {}

        }



    })
</script>