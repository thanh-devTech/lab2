<?php
global $wpdb, $current_role;


wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$use_upload = isset($use_upload) ? $use_upload : false;

$table_report = 'ci_reports_gene';
$table_results = 'ci_results_gene';

if ($act == 'verify_approve') {
    $id = $_REQUEST['id'];
    $report = $wpdb->get_row("select * from {$table_report} where id={$id}", ARRAY_A);
    if ($report['verified'] != 1) {
        $wpdb->update($table_report, array('verified' => 1), array('id' => $id));

        Tr_Session_Class::add("Approved Result #" . $id);
    }
    if (isset($_REQUEST['trajax'])) {
        Tr_Session_Class::show();
        echo 'ok';
        exit;
    }
} else if ($act == 'verify_reverse') {
    $id = $_REQUEST['id'];
    $report = $wpdb->get_row("select * from {$table_report} where id={$id}", ARRAY_A);
    if ($report['verified'] == 1) {
        $wpdb->update($table_report, array('verified' => 0), array('id' => $id));
        Tr_Session_Class::add("Reversed Result #" . $id);
    }
    if (isset($_REQUEST['trajax'])) {
        Tr_Session_Class::show();
        echo 'ok';
        exit;
    }
}

$user_id = get_current_user_id();
$box_options = get_option('ci_box_settings', array());

$where = '';
$orderby = ' order by ba.batch_id desc,re.lab_id asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = " left JOIN ci_reports_gene re ON ba.assen = re.patient_id ";
//page
$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and (re.lab_id in ( {$lab_str} ) or (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) ) ) ";
}



$where .= " and (re.id > 0 or ( re.id is null and " . tr_pdd_where_batch_rejected() . ") or (ba.result_pdf !=''  and ba.result_pdf is not null))  ";


if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'clinic'))) {

    $cid = get_user_meta($user_id, '_clinic_id', true);
    $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
} else if (!tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'scientist', 'labmanager', 'lab_tech', 'lab_director'))) {
    //$where.= " and re.author_id = {$user_id} ";
}

if (!empty($_REQUEST['s']) || !empty($_REQUEST['drug'])) {



    if (stripos($_REQUEST['s'], 'medication:') !== false) {
        $pn = str_replace('medication:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($_REQUEST['s'], 'position:') !== false) {
        $pn = str_replace('position:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($_REQUEST['s'], 'panel:') !== false) {
        $pn = str_replace('panel:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $pn . '%');
    } else if ($_REQUEST['s'] == 'rejected') {
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    } else {
        if (strpos($_REQUEST['s'], ',') > 0) {
            list($firstname, $lastname) = explode(',', $_REQUEST['s']);
            $firstname = trim($firstname);
            $lastname = trim($lastname);
            $explodeString = "%{$firstname}%{$lastname}%";
            $where .= $wpdb->prepare(
                " 
            and (ba.patient_name like %s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
                $explodeString,
                $_REQUEST['s'] . '%',
                $_REQUEST['s'] . '%'
            );
        } else {
            $where .= $wpdb->prepare(
                " 
            and (ba.patient_name like %s 
            or ba.assen=%s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
                $_REQUEST['s'] . '%',
                $_REQUEST['s'],
                $_REQUEST['s'] . '%',
                $_REQUEST['s'] . '%'
            );
        }
    }
}


$sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches_gene ba
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) from ci_batches_gene ba
    {$joins}
        where 1 {$where}
        {$orderby}  limit {$offset},{$row_per_page}";

$time_start  = microtime(true);
$pagination->setTotal($wpdb->get_var($sql_total));
$q_total = microtime(true) - $time_start;

$time_start  = microtime(true);
$batch_ids = $wpdb->get_col($sql_limit);
$q_limit = microtime(true) - $time_start;

$batch_ids = implode(',', $batch_ids);
$where .= " and ba.batch_id in({$batch_ids}) ";


$sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        rl.lab_ID as ref_lab_ID,
        if(re.report_ref = 0, lab.lab_dba_init, rl.lab_dba_init) as lab_dba_init,
        re.report_ref,
        sum(if(re.estatus='C',1,0)) as count_result,
        sum(if(re.box_uploaded>0,1,0)) as count_uploaded
    from ci_batches_gene ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID and re.report_ref >0
    where 1 {$where} 
    group by ba.batch_id,lab.lab_dba_init, rl.lab_dba_init
    {$orderby} ";


$time_start  = microtime(true);
if (class_exists('Tr_Cache_Query2')) {
    $cache_time = 500;
    if (isset($_REQUEST['m'])) {
        $cache_time = 5000;
    } else if (isset($_GET['created'])) {
        $cache_time = 0;
    }

    $cache = new Tr_Cache_Query();
    $results = $cache->get_results($sql, OBJECT, $cache_time);
} else {
    $results = $wpdb->get_results($sql);
}
$count_rows = count($results);




if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo $sql_total, '<br>';
    echo 'qtotal: ' . $q_total, '<br>';
    echo $sql_limit, '<br>';
    echo 'qlimit: ' . $q_limit, '<br>';
    echo $sql, '<br>';
}
?>
<h2>Verification</h2>
<?php Tr_Session_Class::show() ?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Accession/Patient/Clinic/Doctor:</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
    <input type="submit" class="button bsty6" value="Search" />


</form>
<div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead>
            <tr>
                <th style="width:7%">#</th>
                <th style="width: 7%;">Batch</th>
                <th style="width: 54px;">Position</th>
                <th style="width: 6%;">Lab</th>
                <th style="width: 10%;">Patient Name</th>
                <th style="width: 10%;">Clinic</th>
                <th style="width: 9%;">Doctor</th>
                <th style="width: 12%;">Result Type</th>
                <th style="width: 80px;">Date</th>
                <th style="width:30px"></th>
                <th style="width:8%;">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            if (count($results) > 0) {
                foreach ($results as $i => $row) {

                    if (!is_array($batches[$row->batch_id])) {
                        $batches[$row->batch_id] = array();
                    }

                    $batches[$row->batch_id]['date'] = $row->created_date;

                    $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->count_result && $row->count_uploaded > 0;
                    $batches[$row->batch_id]['count_uploaded'] = $row->count_uploaded;
                    $batches[$row->batch_id]['count_result'] = $row->count_result;
                    if ($row->report_ref == 0) {
                        $batches[$row->batch_id]['labs'][$row->lab_ID] = $row;
                    } else {
                        $batches[$row->batch_id]['labs'][$row->lab_ID . $row->ref_lab_ID] = $row;
                    }
                }
            ?>

                <?php
                foreach ($batches as $batch_id => $d) {
                    $classes = '';
                    if ($d['date'] == '1970-01-01') {
                        $d['date'] = preg_replace('/([0-9]{4})([0-9]{2})([0-9]{2})/', '$1-$2-$3', $batch_id);
                    }
                ?>
                    <tr class="parent <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>" data-uped="<?php echo $d['count_uploaded'] ?>" data-count="<?php echo $d['count_result'] ?>">
                        <td><i></i></td>
                        <td><?php echo $batch_id ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><?php echo $d['date'] ?></td>
                        <td></td>
                        <td colspan="2">

                        </td>
                    </tr>
                    <tr class="child p_batch_<?php echo $batch_id ?>">
                        <td colspan="12" class="nopadding">
                            <table class="report_tbl">
                                <?php

                                foreach ($d['labs'] as $lab_key => $row) {
                                    $lab_id = $row->lab_ID;
                                ?>
                                    <tr class="parent_c" data-p="lab_<?php echo $lab_key . $batch_id ?>">
                                        <td style="width: 7%;"><i></i></td>
                                        <td style="width:7%"></td>
                                        <td style="width:54px"></td>
                                        <td style="width:6%"><?php echo $row->lab_dba_init ?></td>
                                        <td colspan="8" align="right">

                                        </td>
                                    </tr>
                                    <tr class="child_c p_lab_<?php echo $lab_key . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>" data-lab_id="<?php echo $lab_id ?>">
                                        <td colspan="12" class="nopadding"></td>
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
                    <td colspan="12">No Results Found.</td>
                </tr>
            <?php
            }



            ?>
        </tbody>
    </table>
</div>

<style>
    .report_tbl .dashicons-yes {
        color: #00a700 !important;
        font-size: 24px;
        padding: 4px 2px !important;
        float: right;
    }

    .report_tbl .dashicons-yes.orange {
        color: orange !important;
    }

    .dashicons.dashicons-warning {
        font-size: 18px;
        padding: 0 !important;
        line-height: 30px;
    }

    span.red {
        color: red;
    }

    span.yellow {
        color: #ffe74e;
    }

    .report_tbl tr.parent td>span,
    .tls>span {
        white-space: nowrap
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>
<script>
    PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
    jQuery(function($) {
        function trcpp_load_results($batch_id, $lab_id, $ref_lab_id) {
            var $obj = $('tr.p_lab_' + $lab_id + $ref_lab_id + $batch_id + ' td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_results_gene',
                    batch_id: $batch_id,
                    lab_id: $lab_id,
                    ref_lab_id: $ref_lab_id,
                    verify: 1
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('<input type="checkbox" value="' + $(this).html() + '"/>');
                    })
                },
                error: function(c) {
                    trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
                }
            })
        }

        $('tr.parent').click(function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                var $pr = $('tr.p_' + $(this).data('p'));
                $pr.show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $(this);
                $('body,html').scrollTop($obj.offset().top - 50);
                if ($('.parent_c', $pr).length == 1) {
                    $('.parent_c', $pr).click();
                }
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function() {
            $('tr.child_c').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                $ref_lab_id = $obj.data('ref_lab_id');
                trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });

        $('body').on('click', 'a.print_pdf', function() {
            var $url = '<?php echo admin_url('/') ?>' + $(this).attr('href');
            $(this).html('Loading');
            $.print_Pdf($url, $(this));
            return false;
        }).on('printed', 'a.print_pdf', function() {
            var $obj = $(this);
            $obj.parent().find('.dashicons-yes').remove();
            $obj.parent().append('<span class="dashicons dashicons-yes"></span>');
            $obj.html('Print');
        }).on('print_error', 'a.print_pdf', function() {
            $(this).html('Print');
        })
        $old_active = getCookie('active_rs');
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
                $ref_lab_id = $obj.data('ref_lab_id');
                setTimeout(function() {
                    trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
                }, 500);
            } catch (e) {}

        }

    })
</script>