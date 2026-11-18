<?php
global $wpdb, $current_role;

$user_id        = get_current_user_id();
$time_show_dl   = ('2016-05-16');
$box_options    = get_option('ci_box_settings', array());
$use_upload     = isset($use_upload) ? $use_upload : false;
$can_upload_box = !empty($box_options['access_token']);
$has_filter     = true;
$can_fax        = true;
$can_delete     = false;
$can_hold       = true;
$can_approve    = !tr_get_option('ci_result_settings_tox', 'hide_approve', false);


wp_enqueue_script('admin_result', TRCPDD_URL . 'js/admin_result.js', array(), TRCPDD_VS . time());
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

if ($_GET['page'] == 'ci_test_results') {
    $use_upload = false;
}

$tests_alcohols = $wpdb->get_col("select te_name from ci_test_panels t JOIN ci_test_groups g ON g.tg_ID = t.te_group_id and g.tg_name = 'Alcohol'");




$where      = '';
$orderby    = ' order by ba.batch_id desc';
$order      = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins      = " left JOIN ci_reports re ON ba.assen = re.patient_id ";


$conditions = trpdd_get_batch_conditions(0, 'tox');
$where      .=  $conditions['where'];
$where      .= " and ba.batch_status != 'archived' ";

if ($conditions['check_author']) {
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



//page
$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset     = $pagination->offset();

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $$wheresql .= " and ba.lab_id in ( {$lab_str} ) ";
}

$where .= " and (re.id > 0 or ( re.id is null and (" . tr_pdd_where_batch_rejected() . " or ba.batch_status = 'resulted' or ba.only_screening = 1) ) )  ";
$where .= " and ba.batch_id !='' and ba.batch_id!='0' ";
$where .= " and ba.cli_ID > 0 ";


$where_limit_smart = '';
$search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

if (!empty($search) || (isset($_REQUEST['drug']) && !empty($_REQUEST['drug']))) {

    if (isset($_REQUEST['drug']) && $_REQUEST['drug'] == 'Alcohol') {
        $where_al = array();
        foreach ($tests_alcohols as $t) {
            $where_al[] = " ba.panel_tests_requested like '%{$t}%' ";
        }
        $where_al = implode(' or ', $where_al);
        $where .= " and ({$where_al}) ";
    } else if (isset($_REQUEST['drug']) && $_REQUEST['drug'] == 'Methamphetamine DL') {
        $where .= " and (ba.type_of_specimen not like 'oral%' or ( ba.type_of_specimen like 'oral%' and re.created_date > '{$time_show_dl}' )) and rs.id > 0 ";
        $joins .= "  join ci_results rs ON rs.report_id = re.id and rs.drug='METHAMPHETAMINE' and rs.`pos-neg`='POS' ";
    }


    if (stripos($search, 'medication:') !== false) {
        $pn = str_replace('medication:', '', $search);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($search, 'position:') !== false) {
        $pn = str_replace('position:', '', $search);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($search, 'panel:') !== false) {
        $pn = str_replace('panel:', '', $search);
        $pn = trim($pn);
        if ($pn == 'screening only') {
            $where .= " and ba.only_screening = '1' ";
        } else  if ($pn == 'screening') {
            $where .= " and ba.has_screening = '1' ";
        } else {
            $testnames = $wpdb->get_col("select te_name from ci_test_panels t
            JOIn ci_test_groups g ON g.tg_ID = t.te_group_id
            where g.tg_name like '%{$pn}%'");
            $wherechilds = array();
            foreach ($testnames as $tname) {
                $wherechilds[] = " find_in_set('{$tname}' , ba.panel_tests_requested ) ";
            }
            $wherechilds = implode(" or ", $wherechilds);
            if (!empty($wherechilds)) {
                $wherechilds = ' or ' . $wherechilds;
            }

            $where .= $wpdb->prepare(" and (ba.custom_profile like %s  {$wherechilds})", '%' . $pn . '%');
        }
    } else if ($search == 'ethanol*') {

        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $search . '%');
    } else if ($search == 'rejected') {
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    } else {
        $search_like = '%' . $wpdb->esc_like($search) . '%';


        if (strpos($search, ',') > 0) {
            list($firstname, $lastname) = explode(',', $search);
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


$sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches ba
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) 
                from ci_batches ba
                {$joins}
            where 1 {$where_limit_smart} {$where} 
            {$orderby}  
            limit {$offset},{$row_per_page}";

$total = 0;
if (class_exists('Tr_Cache_Query')) {
    $cache_time = 86400;
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
if (tr_get_option('ci_pdf_data', 'rs_show_approvecountfinal')) {
    $adding_fields = " , sum(if(re.estatus='C' and final_results='1' ,1,0)) as approved_count ";
} else {
    $adding_fields = " , sum(if(re.estatus='C',1,0)) as approved_count ";
}

$sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        rl.lab_ID as ref_lab_ID,
        if(re.report_ref = 0 or re.report_ref is null, lab.lab_dba_init, rl.lab_dba_init) as lab_dba_init,
        re.report_ref,
        sum(if(re.estatus='C',1,0)) as count_result,
        sum(if(re.box_uploaded>0,1,0)) as count_uploaded,
        count(*) as total_items
        {$adding_fields}
    from (select * from ci_batches where batch_id in('{$batch_ids}') ) ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID and re.report_ref >0
    where 1 {$where} 
    group by ba.batch_id,lab.lab_dba_init, rl.lab_dba_init
    {$orderby} ";


$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
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
<h2>Results</h2>
<?php Tr_Session_Class::show() ?>
<div class="tablenav">
    <form method="get" class="left">
        <?php if (isset($_GET['view'])) echo '<input type="hidden" name="view" value="1"/>' ?>
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <label>Patient/Clinic/Doctor:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <input type="submit" class="button bsty6" value="Search" />
        <?php if (!tr_get_option('ci_pdf_data', 'disable_dl')) : ?>
            <label><input type="checkbox" class="drugs" name="drug" value="Methamphetamine DL" <?php checked(isset($_REQUEST['drug']) ? $_REQUEST['drug'] : '', 'Methamphetamine DL') ?> /> Methamphetamine DL</label>
        <?php endif; ?>
        <?php if (!tr_get_option('ci_pdf_data', 'disable_Alcohol')) : ?>
            <label><input type="checkbox" class="drugs" name="drug" value="Alcohol" <?php checked(isset($_REQUEST['drug']) ? $_REQUEST['drug'] : '', 'Alcohol') ?> /> Alcohol</label>
        <?php endif; ?>

    </form>
    <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div class="wraptbl <?php if (!$can_approve) echo 'notallowapprove' ?>">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead class="sticky">
            <tr>
                <th style="width:7%">#</th>
                <th style="width: 7%;">Batch</th>
                <th style="width: 54px;">Position</th>
                <th style="width: 6%;">Lab</th>
                <th style="width: 10%;">Patient Name</th>
                <th style="width: 10%;">Clinic</th>
                <th style="width: 9%;">Doctor</th>
                <th style="width: 16%;">Medications</th>
                <th style="width: 80px;">Date</th>
                <th style="width:72px;" colspan="2">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            if (count($results) > 0) {
                foreach ($results as $i => $row) {

                    if (!isset($batches[$row->batch_id]) || !is_array($batches[$row->batch_id])) {
                        $batches[$row->batch_id] = array(
                            'approved_count' => 0,
                            'total' => 0,
                            'date' => '',
                            'uploaded' => '',
                            'count_uploaded' => '',
                            'count_result' => '',
                        );
                    }

                    $batches[$row->batch_id]['approved_count'] += isset($row->approved_count) ? $row->approved_count : 0;
                    $batches[$row->batch_id]['total'] += isset($row->total_items) ? $row->total_items : 0;

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

                    $qc_color = 'yellow';
                    if ($d['approved_count'] == $d['total']) {
                        $qc_color = 'blue';
                    }
                ?>
                    <tr class="parent <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>" data-uped="<?php echo $d['count_uploaded'] ?>" data-count="<?php echo $d['count_result'] ?>">
                        <td><i></i></td>
                        <td><a class="show_batch_result_miss" data-batch_id="<?php echo $batch_id ?>"><?php echo $batch_id ?></a></td>
                        <td></td>
                        <td><a class="show_batch_result_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id ?>"></a></td>
                        <td></td>
                        <td><span class="dashicons dashicons-yes <?php echo $qc_color ?>"></span></td>
                        <td><?php echo $d['approved_count'] . '/' . $d['total'] ?></td>
                        <td></td>
                        <td><?php echo $d['date'] ?></td>
                        <td></td>
                        <td colspan="2" class="cactions">
                            <a class="bsty1" href="?tr_action=ci_batch_pdf&batch_id=<?php echo $batch_id ?>">PDF</a>
                            <?php if ($can_fax) { ?>
                                <a class="fax_pdf bsty2" data-batch_id="<?php echo $batch_id ?>">Fax PDF</a>
                            <?php } ?>
                            <?php if ($can_upload_box) { ?>
                                <a class="uploadpdf bsty3" data-batch_id="<?php echo $batch_id ?>">Upload PDF</a>
                            <?php } ?>

                            <?php if ($d['uploaded']) : ?>
                                <span class="checkmark checked"></span>
                            <?php endif; ?>
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
                                        <td colspan="8" align="right" class="bactions">
                                            <a class="btn_sell_all bsty1" sel="true">Select all</a>
                                            <?php if ($can_hold) { ?>
                                                <a class="hold_btn bsty4" data-lab_id="<?php echo $lab_id ?>" data-batch_id="<?php echo $batch_id ?>">Hold</a>
                                                <a class="removehold_btn bsty5" data-lab_id="<?php echo $lab_id ?>" data-batch_id="<?php echo $batch_id ?>">Remove Hold</a>
                                            <?php } ?>
                                            <?php if ($can_approve) { ?>
                                                <a class="approve_all bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_approve_all', 'lab_id' => $lab_id, 'ref_lab_id' => $row->ref_lab_ID, 'batch_id' => $batch_id)) ?>">Approve
                                                    All</a>
                                            <?php } ?>
                                            <?php if ($can_fax) { ?>
                                                <a class="fax_pdf bsty3" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Fax PDF</a>
                                            <?php } ?>
                                            <?php if ($can_upload_box) { ?>
                                                <a class="uploadpdf bsty4" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Upload PDF</a>
                                            <?php } ?>
                                            <?php if ($row->report_ref == 0 && $can_delete) {
                                            ?>
                                                <a class="delete_selected bsty6" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">Delete</a> &nbsp;&nbsp;&nbsp;
                                            <?php
                                            }
                                            ?>
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
    .notallowapprove a[text="Approve"] {
        display: none !important;
    }
</style>

<script>
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
                    tr_action: 'ci_load_results',
                    batch_id: $batch_id,
                    lab_id: $lab_id,
                    ref_lab_id: $ref_lab_id
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
        $('body').on('click', 'a.delete', function() {
            if (!confirm('Do you want delete?')) {
                return false;
            }
        })
        $('a.approve_all').click(function() {
            if (confirm('Do you want Approve all?')) {
                location = $(this).attr('href');
            }
            return false;
        })


        $('a.delete_selected').click(function() {
            if (!confirm('Do You want delete selected?')) return false;
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ids = '';
            var $childpr = $('.p_lab_' + $labid + $bid);
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
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_back', function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_upload', function() {
            var $bid = $(this).data('batch_id');
            var $lab_id = $(this).data('lab_id');
            tb_show('miss upload', '?tr_action=ci_show_batch_result_miss_upload&batch_id=' + $bid + '&lab_id=' + $lab_id + '&width=800');
            return false;
        })
        $('a.show_batch_result_files').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Uploaded Files in Batch ' + $bid, '?tr_action=ci_show_batch_result_files&batch_id=' + $bid + '&width=800');
            return false;
        })

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
                var $scroll_top = $obj.offset().top - 50;
                if ($('#header_bar').length) {
                    $scroll_top -= $('#header_bar').outerHeight();
                }
                $('body,html').scrollTop($scroll_top);
                if ($('.parent_c', $pr).length == 1 && $('.parent_c', $pr).data('showed') != '1') {
                    $('.parent_c', $pr).click();
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
                setCookie('active_rs', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                var $scroll_top = $obj.offset().top - 150;
                if ($('#header_bar').length) {
                    $scroll_top -= $('#header_bar').outerHeight();
                }
                $('body,html').scrollTop($scroll_top);
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
        $('.drugs').click(function() {
            var $vl = $(this).val();
            $('.drugs').each(function() {
                if ($(this).val() != $vl) {
                    $(this).removeAttr('checked');
                }
            })
            $(this).parents('form').submit();
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
                var $scroll_top = $obj.offset().top - 150;
                if ($('#header_bar').length) {
                    $scroll_top -= $('#header_bar').outerHeight();
                }
                $('body,html').scrollTop($scroll_top);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                $ref_lab_id = $obj.data('ref_lab_id');
                setTimeout(function() {
                    trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
                }, 500);
            } catch (e) {}

        }

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