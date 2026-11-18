<?php
global $wpdb, $current_role;

if ($act == 'data_qc_plate384') {
    include(trpdd_get_file_path('gene', 'data_qc_plate384'));
    exit;
} else if ($act == 'data_qc_plate98') {
    include(trpdd_get_file_path('gene', 'data_qc_plate98'));
    exit;
}


add_thickbox();
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

wp_enqueue_script('admin_result', TRCPDD_URL . 'js/admin_result.min.js', array(), TRCPDD_VS);
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

$is_test_results    = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';
$user_id            = get_current_user_id();
$box_options        = get_option('ci_box_settings', array());
$gene_spcode       = tr_get_option('ci_pdf_data', 'gene_spcode');
$can_fax            = true;
$can_delete         = true;
$use_upload         = isset($use_upload) ? $use_upload : false;
$can_upload_box     = !empty($box_options['access_token']);

$can_view_csv       = false;
$has_filter         = true;
$where              = ' and ba.batch_id > 0 and ba.is_saved = 0';
$where .= " and ba.batch_status !='archived' ";
$orderby            = ' order by ba.batch_id desc,re.lab_id asc';
$order              = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins              = " left JOIN ci_reports_gene re ON ba.assen = re.patient_id ";



if ($_GET['page'] == 'ci_test_results') {
    $use_upload = false;
}



//page
$row_per_page       = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();



if ($_GET['page'] == 'ci_test_results') {
    $where .= " and re.id > 0   ";
} else {
    $where .= " and (re.id > 0 or ( re.id is null and " . tr_pdd_where_batch_rejected() . ") or (ba.result_pdf !=''  and ba.result_pdf is not null))  ";
}

$conditions = trpdd_get_batch_conditions(0, 'pcr');
$where      .=  $conditions['where'];

if (isset($conditions['check_author']) && !empty($conditions['check_author'])) {
    $where      .= $return['where_author'];
}
$edit_time      = $conditions['edit_time'];
$can_edit       = $conditions['can_edit_result'];
$can_delete     = $conditions['can_delete_result'];
$can_fax        = $conditions['can_fax'];
$can_view_csv   = $conditions['can_view_csv'];
$can_hold       = $conditions['can_hold_result'];

if (!tr_current_role_in(array('administrator', 'lab', 'lab_rep')) && current_user_can('only_rs_final')) {
    $where .= " and re.final_results = 1 ";
}



$search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';
$s_drug = isset($_REQUEST['drug']) ? $_REQUEST['drug'] : '';
if (!empty($search) || !empty($s_drug)) {

    if (stripos($search, 'position:') !== false) {
        $pn = str_replace('position:', '', $search);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($search, 'panel:') !== false) {
        $pn = str_replace('panel:', '', $search);
        $group_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_name like '{$pn}%' ");
        $wheregroups = array();
        foreach ($group_ids as $gid) {
            $wheregroups[] = "  find_in_set('GROUP_{$gid}', ba.panel_tests_requested  ) ";
        }
        $wheregroups = implode(' or ', $wheregroups);
        $where .= " and ({$wheregroups})";
    } else if (stripos($search, 'reflab:') !== false) {
        $reflabname = str_replace('reflab:', '', $search);
        $ref_lab_id = CI_Base_Lab::getLabIDByKey($reflabname);
        $where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
    } else if ($search == 'rejected') {
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    } else {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
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
                $search_like,
                $search_like
            );
        } else {
            $where .= $wpdb->prepare(
                " 
            and (ba.patient_name like %s 
            or ba.assen like %s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
                $search_like,
                $search_like,
                $search_like,
                $search_like
            );
        }
    }
    $has_filter = true;
}


$sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches_gene ba
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) from ci_batches_gene ba
    {$joins}
        where 1 {$where}
        {$orderby}  limit {$offset},{$row_per_page}";


if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
    $cache_time = 16400;
    $cache = new Tr_Cache_Query();
    $total = $cache->get_var($sql_total, 0, 0, $cache_time);
} else {
    $total = $wpdb->get_var($sql_total);
}


$time_start  = microtime(true);
$pagination->setTotal($total);
$q_total = microtime(true) - $time_start;

$time_start  = microtime(true);
$batch_ids = $wpdb->get_col($sql_limit);
$q_limit = microtime(true) - $time_start;

$batch_ids = implode("','", $batch_ids);
if (empty($batch_ids)) {
    $batch_ids = '-1';
}
$where .= " and ba.batch_id in ('{$batch_ids}') ";


$sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        rl.lab_ID as ref_lab_ID,
        if(re.report_ref = 0, lab.lab_dba_init, rl.lab_dba_init) as lab_dba_init,
        re.report_ref,
        sum(if(re.estatus='C',1,0)) as count_result,
        sum(if(re.box_uploaded>0,1,0)) as count_uploaded,
        count(*) as total_items,
        sum(ba.qc) as total_qc
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
    echo microtime(true) - $time_start, '<br><br>';
    echo $sql_total, '<br><br>';
    echo 'qtotal: ' . $q_total, '<br><br>';
    echo $sql_limit, '<br><br>';
    echo 'qlimit: ' . $q_limit, '<br><br>';
    echo $sql, '<br><br>';
}
?>
<h2>Results</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" class="left">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <?php if (isset($_GET['view'])) { ?>
            <input type="hidden" name="view" value="1" />
        <?php } ?>
        <label>Accession/Patient/Clinic/Doctor:</label>
        <input type="text" name="s" value="<?php echo $search ?>" style="width:250px" />
        <input type="submit" class="button bsty6 bsty6" value="Search" />

    </form>
    <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead class="sticky">
            <tr>
                <th style="width:40px">#</th>
                <th style="width: 8%;">Batch</th>
                <?php if (!$is_test_results) { ?>
                    <th style="width: 10%;">Panels</th>
                    <th style="width: 54px;">Position</th>
                    <th style="width: 40px">Lab</th>
                <?php } ?>
                <th style="width: 13%;">Patient Name</th>
                <th style="width: 13%;">Clinic</th>
                <th style="width: 12%;">Doctor</th>
                <?php if ($gene_spcode) { ?>
                    <th style="width: 8%">Patient Code</th>
                <?php } ?>
                <th style="width: 80px;">DOC</th>
                <th style="width: 8%;">Result Type</th>
                <th style="width:100px;text-align: center">Status</th>
                <th style="text-align: center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            if (count($results) > 0) {
                foreach ($results as $i => $row) {

                    if (!isset($batches[$row->batch_id]) || !is_array($batches[$row->batch_id])) {
                        $batches[$row->batch_id] = array(
                            'date' => $row->created_date,
                            'qc' => 0,
                            'total' => 0
                        );
                    }

                    $batches[$row->batch_id]['date'] = $row->created_date;
                    $batches[$row->batch_id]['qc'] += $row->total_qc;
                    $batches[$row->batch_id]['total'] += $row->total_items;

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
                    $qc_color = 'yellow';
                    if ($d['qc'] == $d['total']) {
                        $qc_color = 'blue';
                    }
                ?>
                    <tr class="parent parentacts <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-uped="<?php echo $d['count_uploaded'] ?>" data-count="<?php echo $d['count_result'] ?>">
                        <td><i></i></td>
                        <td colspan="3"><?php echo $batch_id ?></td>
                        <?php if (!$is_test_results) { ?>
                            <td><?php if ($can_view_csv) { ?>
                                    <a class="show_batch_result_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id ?>"></a>
                                <?php } ?>
                            </td>
                            <td></td>
                        <?php } ?>
                        <td><span class="dashicons dashicons-yes <?php echo $qc_color ?>"></span></td>
                        <td><?php echo $d['qc'] . '/' . $d['total'] ?></td>
                        <td align="right" colspan="4" class="bactions">
                            <?php
                            if (count($d['labs']) == 1) {
                                echo '<div class="batch_actions">';
                                if ($use_upload) {
                            ?>
                                    <a class="btn_sell_all bsty1" sel="true">Select all</a>
                                    <a class="approve_selected bsty2">Approve All</a>
                                    <?php if ($can_fax) { ?>
                                        <a class="fax_pdf bsty3" data-batch_id="<?php echo $batch_id ?>">Fax PDF</a>
                                    <?php } ?>
                                    <a class="hold_btn bsty4" data-batch_id="<?php echo $batch_id ?>">Hold</a>
                                    <a class="removehold_btn bsty5" data-batch_id="<?php echo $batch_id ?>">Remove Hold</a>
                                    <?php if ($can_upload_box) { ?>
                                        <a class="uploadpdf bsty1" data-batch_id="<?php echo $batch_id ?>">Upload PDF</a>
                                    <?php }
                                }
                                if (!$is_test_results) {
                                    if ($can_delete) {
                                    ?>
                                        <a class="delete_selected bsty6" data-batch_id="<?php echo $batch_id ?>">Delete</a>
                                    <?php
                                    }
                                } else {
                                    ?>
                                    <a class="printbatch bsty1">Print All</a>
                                    <a class="downloadbatcheachfile bsty7">Download Ind</a>
                                    <a class="downloadbatch bsty4">Download All</a>
                                <?php
                                }
                                echo '</div>';
                            }

                            if ($use_upload && $d['uploaded']) { ?>
                                <span class="checkmark checked"></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php
                    if (count($d['labs']) > 1) {
                    ?>
                        <tr class="child p_batch_<?php echo $batch_id ?>">
                            <td colspan="<?php echo $is_test_results ? 10 : 12 ?>" class="nopadding">
                                <table class="report_tbl">
                                    <?php

                                    foreach ($d['labs'] as $lab_key => $row) {
                                        $lab_id = $row->lab_ID;
                                    ?>
                                        <tr class="parent_c parentacts" data-p="lab_<?php echo $lab_key . $batch_id ?>" data-lab_id="<?php echo $lab_key ?>" data-batch_id="<?php echo $batch_id ?>">
                                            <td style="width: 40px;"><i></i></td>
                                            <td style="width:7%"></td>
                                            <td style="width:7%"></td>
                                            <td style="width:54px"></td>
                                            <td style="width:6%"><?php echo $row->lab_dba_init ?></td>
                                            <td colspan="7" align="right" class="bactions">
                                                <div class="batch_actions">
                                                    <?php if ($use_upload) { ?>
                                                        <a class="btn_sell_all bsty1" sel="true">Select all</a>
                                                        <a class="approve_selected bsty2">Approve All</a>
                                                        <?php if ($can_fax) { ?>
                                                            <a class="fax_pdf bsty3" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Fax PDF</a>
                                                        <?php } ?>
                                                        <a class="hold_btn bsty4" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Hold</a>

                                                        <a class="removehold_btn bsty5" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Remove Hold</a>

                                                        <?php if ($can_upload_box) { ?>
                                                            <a class="uploadpdf bsty1" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Upload PDF</a>
                                                        <?php } ?>
                                                    <?php } ?>
                                                    <?php if (!$is_test_results) {
                                                        if ($can_delete) {
                                                    ?>
                                                            <a class="delete_selected bsty6" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Delete</a>
                                                        <?php
                                                        }
                                                    } else {
                                                        ?>
                                                        <a class="printbatch bsty1">Print All</a>
                                                        <a class="downloadbatcheachfile bsty7">Download Ind</a>
                                                        <a class="downloadbatch bsty4">Download All</a>
                                                    <?php
                                                    } ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="child_c p_lab_<?php echo $lab_key . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>" data-lab_id="<?php echo $lab_id ?>">
                                            <td colspan="11" class="nopadding"></td>
                                        </tr>
                                    <?php
                                    }

                                    ?>
                                </table>
                            </td>
                        </tr>
                    <?php
                    } else {
                    ?>
                        <tr class="child_c cbatch p_batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>">
                            <td colspan="<?php echo $is_test_results ? 10 : 12 ?>" class="nopadding"></td>
                        </tr>
                <?php
                    }
                }
            } else {
                ?>
                <tr>
                    <td colspan="<?php echo $is_test_results ? 10 : 12 ?>">No Results Found.</td>
                </tr>
            <?php
            }



            ?>
        </tbody>
    </table>
</div>
<style>
    td.panels {
        font-size: 10px;
        line-height: 12px;
        vertical-align: top;
    }
</style>

<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>
<script>
    PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
    jQuery(function($) {
        var $final_results = {
            0: 'None',
            2: 'Preliminary',
            1: 'Final',
            3: 'Amended Report'
        };

        function trcpp_load_results($obj, $trpr, $batch_id, $lab_id, $ref_lab_id) {
            if ($obj.html() != '') {
                $('a.printbatch', $trpr).show();
                $('a.downloadbatch', $trpr).show();
                $('a.downloadbatcheachfile', $trpr).show();
                $('.batch_actions', $trpr).show();
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_results_gene',
                    batch_id: $batch_id,
                    lab_id: $lab_id,
                    ref_lab_id: $ref_lab_id
                },
                type: 'post',
                success: function(rs) {
                    if (rs.indexOf('need_reload') > -1) {
                        $obj.html('');
                        trcpp_load_results($obj, $trpr, $batch_id, $lab_id, $ref_lab_id);
                    } else {
                        var $html = $(rs);
                        $obj.html($html);
                        $('.cid', $html).each(function() {
                            if ($(this).parent().hasClass('disabled')) {
                                return;
                            }
                            $(this).prepend('<input type="checkbox" value="' + $(this).data('id') + '"/>');
                        })
                        $('a.printbatch', $trpr).show();
                        $('a.downloadbatch', $trpr).show();
                        $('a.downloadbatcheachfile', $trpr).show();
                        $('.batch_actions', $trpr).show();
                    }

                    var $scroll_top = $obj.offset().top - 140;
                    if ($('#header_bar').length) {
                        $scroll_top -= $('#header_bar').outerHeight();
                    }
                    $('body,html').scrollTop($scroll_top);
                },
                error: function(c) {
                    trcpp_load_results($obj, $trpr, $batch_id, $lab_id, $ref_lab_id);
                }
            })
        }
        $('body').on('click', 'a.delete', function() {
            if (confirm('Do you want delete?') == false) {
                return false;
            }
        })
        $('a.approve_selected').click(function() {
            var $pr = $(this).parents('.parentacts');
            var $tbl = $('tr.p_' + $pr.data('p'));

            var $batch_id = $pr.data('batch_id');
            var $labid = $pr.data('lab_id');
            var $ref_lab_id = $pr.data('ref_lab_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })
            if ($ids == '') {
                if (!confirm('Do you want Approve all?')) {
                    return false;
                }
            } else if (!confirm('Do you want Approve Items Selected?')) {
                return false;
            }

            $.ajax({
                type: 'post',
                data: {
                    trajax: 2,
                    act: 'approve_selected',
                    batch_id: $batch_id,
                    lab_id: $labid,
                    ref_lab_id: $ref_lab_id,
                    ids: $ids
                },
                dataType: 'json',
                success: function(rs) {
                    if (rs.status == 'ok') {
                        var $ipcheck = $ids == '' ? 'input[type="checkbox"]' : 'input:checked';
                        $($ipcheck, $tbl).each(function() {
                            var $id = $(this).val();
                            var $tr = $(this).parents('tr.childp');
                            $('.approvebtn', $tr).html('Reverse');

                            $('.editlink', $tr).hide();
                            $('.pdflink', $tr).show();
                            $('td.statuscol', $tr).html('C').addClass('result_C').removeClass('result_M');
                            $(this).removeAttr('checked');

                            try {
                                if (rs.adding_html[$id]) {
                                    for (ii in rs.adding_html[$id]) {
                                        $('.' + ii + ' .adding_html', $tr).remove();
                                        $('.' + ii, $tr).append('<span class="adding_html">' + rs.adding_html[$id][ii] + '<span>');
                                    }
                                }
                            } catch (e) {

                            }
                        })
                        if (rs.msg) {
                            alert(rs.msg);
                        }

                    } else {
                        alert('Error. Please try again!');
                    }
                },
                error: function() {
                    alert('Error. Please try again!');
                }
            })

            return false;
        })

        $('a.btn_sell_all').click(function() {
            var sleat = $(this).attr("sel");
            var $pid = $(this).parents('tr').data('p');

            if (sleat == "true") {
                $(".p_" + $pid + " input:checkbox").prop('checked', true);
                $(this).attr("sel", false);
                $(this).html("Unselect All")
            } else {
                $(".p_" + $pid + " input:checkbox").prop('checked', false);
                $(this).attr("sel", true);
                $(this).html("Select All")
            }
            return false;
        })


        $('a.delete_selected').click(function() {
            if (!confirm('Do You want delete selected?')) return false;
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            var $childpr = $('.p_lab_' + $labid + $ref_lab_id + $bid);
            $('input:checked', $childpr).each(function() {
                $ids += $(this).val() + ',';
            });
            if ($ids === '') {
                alert('Please select rows to delete!');
                return false;
            }
            $.ajax({
                data: {
                    trajax: '1',
                    act: 'delete_selected',
                    ids: $ids
                },
                type: 'post',
                dataType: 'json',
                success: function(json) {
                    if (json.status === 'ok') {
                        for (var i in json.ids) {
                            $('.childp[data-id="' + json.ids[i] + '"]', $childpr).remove();
                        }
                    } else {
                        alert('Error. Please try again!');
                    }
                },
                error: function() {
                    alert('Error. Please try again!');
                }
            })
            return false;
        });


        $('a.show_batch_result_miss').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=gene&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_back', function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=gene&width=800');
            return false;
        }).on('click', 'a.show_batch_result_miss_upload', function() {
            var $bid = $(this).data('batch_id');
            var $lab_id = $(this).data('lab_id');
            tb_show('miss upload', '?tr_action=ci_show_batch_result_miss_upload&batch_id=' + $bid + '&lab_id=' + $lab_id + '&type=gene&width=800');
            return false;
        }).on('click', 'a.show_batch_result_files', function() {
            var $bid = $(this).data('batch_id');
            tb_show('Uploaded Files in Batch ' + $bid, '?tr_action=ci_show_batch_result_files&batch_id=' + $bid + '&type=gene&width=800');
            return false;
        }).on('click', 'a.print_pdf', function() {
            var $url = '<?php echo admin_url('/') ?>' + $(this).attr('href');
            $(this).html('Loading');
            $.print_Pdf($url, $(this));
            return false;
        }).on('printed', 'a.print_pdf', function() {
            var $obj = $(this);
            $obj.parent().find('.dashicons-yes').remove();
            $obj.parent().append('<span class="dashicons dashicons-yes"></span>');
            $obj.html('Printed');
        }).on('print_error', 'a.print_pdf,a.printbatch', function() {
            $(this).html('Print');
        }).on('download_error', 'a.downloadbatch', function() {
            $(this).html('Download');
        }).on('click', 'a.printbatch', function(e) {
            e.preventDefault();
            var $urls = [];
            var $trpr = $(this).parents('tr.parent_c');
            if ($trpr.length == 0) {
                $trpr = $(this).parents('tr.parent');
            }
            var $trchild = $('tr.p_' + $trpr.data('p'));
            $('a.print_pdf', $trchild).each(function() {
                $urls.push($(this).attr('href'));
            })
            if ($urls.length == 0) {
                alert('Not found');
                return false;
            }
            $.print_Pdfs($urls, $(this));
            return false;
        }).on('printed', 'a.printbatch', function() {
            var $obj = $(this);
            $obj.html('Printed');
        }).on('change', '.final_results select', function() {
            var $this = $(this);
            var $td = $(this).parent();
            var $tr = $td.parents('tr');
            var $val = $(this).val();
            var $id = $('.cid input', $tr).val();
            $tr.find('.sloading').remove();
            $td.append('<span class="sloading"></span>');
            $.ajax({
                type: 'post',
                dataType: 'json',
                data: {
                    trajax: 1,
                    act: 'change_final_results',
                    id: $id,
                    val: $val
                },
                success: function(rs) {
                    $tr.find('.sloading').remove();
                    if (rs.status == 'ok') {
                        if (rs.action == 'remove_hold') {
                            $tr.removeClass('bs_hold');
                            var $approvebtn = $('a.approvebtn', $tr);
                            if ($approvebtn.html() == 'Approve') {
                                $approvebtn.click();
                            }
                        }
                    } else {
                        $this.change();
                    }
                },
                error: function() {
                    $this.change();
                }
            })
        }).on('click', 'a.downloadbatch', function(e) {
            var $ids = [];
            var $trpr = $(this).parents('tr.parent_c');
            if ($trpr.length == 0) {
                $trpr = $(this).parents('tr.parent');
            }
            var $trchild = $('tr.p_' + $trpr.data('p'));
            $('tr.childp', $trchild).each(function() {
                $ids.push($(this).data('id'));
            })
            if ($ids.length == 0) {
                alert('Not found');
                return false;
            }
            $ids = $ids.join(',');
            location.href = '?tr_action=ci_download_pdfs&btype=gene&ids=' + $ids;
            return false;
        }).on('click', 'a.downloadbatcheachfile', function(e) {
            var $ids = [];
            var $trpr = $(this).parents('tr.parent_c');
            if ($trpr.length == 0) {
                $trpr = $(this).parents('tr.parent');
            }
            var $trchild = $('tr.p_' + $trpr.data('p'));
            $('tr.childp', $trchild).each(function() {
                var $cbk = $('input[type="checkbox"]:checked', $(this));
                if ($cbk.length > 0) {
                    $ids.push($(this).data('id'));
                }
            })
            if ($ids.length == 0) {
                alert('Not found');
                return false;
            }
            $ids = $ids.join(',');
            location.href = '?tr_action=ci_download_pdfs&btype=gene&ids=' + $ids;
            return false;
        }).on('click', 'a.plate_heatmap', function() {
            tb_show('Plate Heatmap', '#TB_inline');
            var $tr = $(this).parents('tr.parent');
            var $batch_id = $tr.data('batch_id');

            $('#TB_ajaxContent').html('<div class="loading center"></div>');
            $.ajax({
                data: {
                    trajax: 1,
                    act: 'data_qc_plate384',
                    batch_id: $batch_id
                },
                type: 'post',
                success: function(rs) {
                    $('#TB_ajaxContent').html(rs);

                },
                error: function() {

                }
            })
            return false;
        }).on('change', 'select.test_filter', function() {
            var $pr = $(this).parents('.data_qc_plate');
            var $vl = $(this).val();
            $('.platetblsmall td', $pr).each(function() {
                var $colors = $(this).data('colors');
                if ($colors && $colors[$vl]) {
                    $(this).attr('style', 'background:' + $colors[$vl] + '!important');
                }

            })
        })


        $('tr.parent').click(function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;

            var $pr = $('tr.p_' + $(this).data('p'));
            $('tr.parent.active').removeClass('active');

            if ($pr.hasClass('cbatch')) {
                $('tr.child_c').hide();
                $('tr.parent a.printbatch').hide();
                $('tr.parent a.downloadbatch').hide();
                $('tr.parent a.downloadbatcheachfile').hide();
                $('tr.parent .batch_actions').hide();
            } else {
                $('tr.child').hide();
            }

            if ($(this).data('showed') != '1') {
                $pr.show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                var $obj = $(this);
                if ($('.parent_c', $pr).length == 1 && $('.parent_c', $pr).data('showed') != '1') {
                    $('.parent_c', $pr).click();
                }

                if ($pr.hasClass('cbatch')) {
                    var $batch_id = $pr.data('batch_id');
                    trcpp_load_results($('>td', $pr), $(this), $batch_id, 0, 0);
                }
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function(e) {
            if (e.target.tagName == 'A') return;
            $('tr.child_c').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                var $obj = $('tr.p_' + $(this).data('p'));
                var $scroll_top = $obj.offset().top - 150;
                if ($('#header_bar').length) {
                    $scroll_top -= $('#header_bar').outerHeight();
                }
                $('body,html').scrollTop($scroll_top);
                var $batch_id = $obj.data('batch_id');
                var $lab_id = $obj.data('lab_id');
                var $ref_lab_id = $obj.data('ref_lab_id');

                trcpp_load_results($('>td', $obj), $(this), $batch_id, $lab_id, $ref_lab_id);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');

                $('a.printbatch', $(this)).hide();
                $('a.downloadbatch', $(this)).hide();
                $('a.downloadbatcheachfile', $(this)).hide();
                $('.batch_actions', $(this)).hide();
            }
            return false;
        });

        var $single_parent = $('tr.parent');
        if ($single_parent.length == 1) {
            var $parent = $single_parent.first();
            var $parent_row = $('tr.p_' + $parent.data('p'));
            var $single_parent_c = $('tr.parent_c', $parent_row);
            if ($parent.data('showed') != '1') {
                $parent.click();
            } else if ($single_parent_c.length == 1 && $single_parent_c.data('showed') != '1') {
                $single_parent_c.click();
            }
        }


    })
</script>