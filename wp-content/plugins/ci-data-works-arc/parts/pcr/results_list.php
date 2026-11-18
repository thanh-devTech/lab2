<?php
global $wpdb, $current_role;

if ($act == 'data_qc_plate384') {
    include(trpdd_get_file_path('pcr', 'data_qc_plate384'));
    exit;
} else if ($act == 'data_qc_plate98') {
    include(trpdd_get_file_path('pcr', 'data_qc_plate98'));
    exit;
}



add_thickbox();
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

wp_enqueue_script('admin_result', TRCPDD_URL . 'js/admin_result.js', array(), TRCPDD_VS . time());
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

wp_enqueue_script('jquery-ui-slider');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('at-timepicker', TRCPDD_URL . 'admin/js/time-and-date/jquery-ui-timepicker-addon.js', array('jquery'), null, true);

$is_test_results    = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';

$user_id            = get_current_user_id();
$box_options        = get_option('ci_box_settings', array());
$result_settings    = get_option('ci_result_settings_pcr', array());
$can_fax            = true;
$can_hold           = true;
$can_delete         = !tr_get_option('ci_pdf_data', 'req_ddelete', false);

$use_upload         = isset($use_upload) ? $use_upload : false;
$can_upload_box     = !empty($box_options['access_token']);

$can_view_csv       = false;
$has_filter         = true;
$where              = ' and ba.batch_id > 0 and ba.is_saved = 0';
$where .= " and ba.cli_ID > 0 ";
$where .= " and ba.batch_status != 'archived' ";

$archive_date       = tr_get_option('ci_pdf_data', 'archive_date', '');
$show_archive       = isset($_GET['showarchive']);
if (!empty($archive_date) && !$show_archive) {
    $where .= " and ba.batch_created > '{$archive_date}' ";
}

$orderby            = ' order by ba.batch_id desc,re.lab_id asc';

$joins              = " left JOIN ci_reports_pcr re ON ba.assen = re.patient_id ";

if (isset($result_settings['sort_df_assen']) && $result_settings['sort_df_assen']) {
    if (!isset($_REQUEST['orderby']) || empty($_REQUEST['orderby'])) {
        $_REQUEST['orderby'] = 'assen';
    }
}


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
    } else if (stripos($search, 'reflab:') !== false) {
        $reflabname = str_replace('reflab:', '', $search);
        $ref_lab_id = CI_Base_Lab::getLabIDByKey($reflabname);
        $where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
    } else if (stripos($search, 'panel:') !== false) {
        $pn = str_replace('panel:', '', $search);
        $pn = trim($pn);
        foreach (explode(',', $pn) as $pn1) {
            $group_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_name like '%{$pn1}%' ");
            $wheregroups = array();
            foreach ($group_ids as $gid) {
                $wheregroups[] = "  find_in_set('GROUP_{$gid}', ba.panel_tests_requested  ) ";
            }
            $wheregroups = implode(' or ', $wheregroups);

            $where .= " and ({$wheregroups})";
        }
    } else if (stripos($search, 'test:') !== false) {
        $pn = str_replace('test:', '', $search);
        $pn = trim($pn);
        foreach (explode(',', $pn) as $pn1) {
            $tests = $wpdb->get_results("select te_ID, tg_ID, tg_parent
				from ci_test_panels te
				JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
				where te_name like '%{$pn1}%' ");
            $where_tests = array();
            foreach ($tests as $test) {
                if ($test->tg_parent > 0) {
                    $where_tests[] = "  find_in_set('GROUP_{$test->tg_parent}', ba.panel_tests_requested  ) ";
                }
                $where_tests[] = "  find_in_set('{$test->te_ID}', ba.panel_tests_requested  ) ";
            }
            $where_tests = implode(' or ', $where_tests);

            $where .= " and ({$where_tests})";
        }
    } else if (stripos($search, 'labstate:') !== false) {
        $pn = str_replace('labstate:', '', $search);
        $pn = trim($pn);
        $where .= " and ba.patient_id in(select pa_ID from ci_patients where pa_state='{$pn}') ";
        $options     = get_option('ci_send_hub_tests', array());
        $where_tests = array();
        foreach ($options as $oarr) {
            if (strtoupper($oarr['state']) == strtoupper($pn)) {
                $where_tests[] = "  find_in_set('{$oarr['tid']}', ba.panel_tests_requested  ) ";
            }
        }
        $where_tests = implode(' or ', $where_tests);

        $where .= " and ({$where_tests})";
        $where .= " and re.has_pos = 1 ";
    } else if (stripos($search, 'state:') !== false) {
        $pn = str_replace('state:', '', $search);
        $pn = trim($pn);
        $where .= " and ba.patient_id in(select pa_ID from ci_patients where pa_state='{$pn}') ";
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
            $pa_ids = $wpdb->get_col("select pa_ID from ci_patients where pa_mrn = '{$search}'  or pa_ssn = '{$search}' ");
            if (count($pa_ids) > 0) {
                $pa_ids_str = implode(', ', $pa_ids);
                $where .= " and ba.patient_id in ( {$pa_ids_str} )";
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
    }
    $has_filter = true;
}


$sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches_pcr ba
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) from ci_batches_pcr ba
    {$joins}
        where 1 {$where}
        {$orderby}  limit {$offset},{$row_per_page}";


if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
    $cache_time = 3600;
    $cache = new Tr_Cache_Query();
    $total = intval($cache->get_var($sql_total, 0, 0, $cache_time));
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
$last_login = get_user_meta($user_id, '_last_login', true);
if (empty($batch_ids)) {
    $batch_ids = '-1';
}

$adding_fields = '';
if ($is_test_results) {
    $adding_fields .= " ,sum(if(re.approve_date > '{$last_login}',1,0)) as count_new_result ";
}


if (tr_get_option('ci_pdf_data', 'rs_show_approvecountfinal')) {
    $adding_fields .= " , sum(if(re.estatus='C' and final_results='1' ,1,0)) as approved_count ";
} else {
    $adding_fields .= " , sum(if(re.estatus='C',1,0)) as approved_count ";
}

$where .= " and ba.batch_id in ('{$batch_ids}') ";

$sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        rl.lab_ID as ref_lab_ID,
        if(re.report_ref = 0, lab.lab_dba_init, rl.lab_dba_init) as lab_dba_init,
        re.report_ref,
        sum(if(re.estatus='C',1,0)) as count_result,
        sum(if(re.box_uploaded>0,1,0)) as count_uploaded,
        count(*) as total_items
        {$adding_fields}
    from ci_batches_pcr ba
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
    echo '$sql_total: ' . $sql_total, '<br><br>';
    echo 'qtotal: ' . $q_total, '<br><br>';
    echo $sql_limit, '<br><br>';
    echo 'qlimit: ' . $q_limit, '<br><br>';
    echo $sql, '<br><br>';
}
?>
<h2>PCR Results</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
    <form method="get" class="left">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <?php if (isset($_GET['view'])) { ?>
            <input type="hidden" name="view" value="1" />
        <?php } ?>
        <?php if (isset($_GET['shownewabx'])) { ?>
            <input type="hidden" name="shownewabx" value="1" />
        <?php } ?>
        <label>Accession/Patient/Clinic/Doctor:</label>
        <input type="text" name="s" value="<?php echo $search ?>" style="width:250px" />
        <?php if (!empty($archive_date)) {
            echo '<label><input type="checkbox" name="showarchive" onclick="this.form.submit()" value=1 ' . checked($show_archive, true, false) . '/> Show Archived</label>';
        }
        ?>
        <input type="submit" class="button bsty6 bsty6" value="Search" />

    </form>
    <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead class="sticky">
            <tr>
                <th style="width:40px">#</th>
                <?php tr_tbl_sort('assen', 'Accession', '', 'width:8%') ?>
                <?php if (!$is_test_results) { ?>
                    <?php tr_tbl_sort('position', 'Position', '', 'width:82px') ?>
                    <th style="width: 40px">Lab</th>
                <?php } ?>
                <th style="width: 13%;">Patient Name</th>
                <th style="width: 13%;">Clinic</th>
                <?php tr_tbl_sort('panel_tests_requested', 'Panel', '', 'width:12%') ?>
                <th style="width: 8%;">Result Type</th>
                <th style="width: 80px;">Date</th>
                <?php if ($is_test_results) { ?>
                    <th style="width:8%">Batch Status</th>
                <?php } ?>
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
                            'approved_count' => 0,
                            'not_approved_count' => 0,
                            'total' => 0
                        );
                    }

                    $batches[$row->batch_id]['date'] = $row->created_date;
                    //$batches[$row->batch_id]['qc'] += $row->total_qc;

                    $batches[$row->batch_id]['approved_count'] += $row->approved_count;
                    $batches[$row->batch_id]['not_approved_count'] += $row->total_items - $row->approved_count;
                    $batches[$row->batch_id]['total'] += $row->total_items;

                    $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->count_result && $row->count_uploaded > 0;
                    $batches[$row->batch_id]['count_uploaded'] = $row->count_uploaded;
                    $batches[$row->batch_id]['count_result'] = $row->count_result;
                    if ($is_test_results) {
                        $batches[$row->batch_id]['count_new_result'] = $row->count_new_result;
                    }

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
                    <tr class="parent parentacts <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-uped="<?php echo $d['count_uploaded'] ?>" data-count="<?php echo $d['count_result'] ?>">
                        <td><i></i></td>
                        <td colspan="2"><?php echo $batch_id ?></td>
                        <?php if (!$is_test_results) { ?>
                            <td><?php if ($can_view_csv) { ?>
                                    <a class="show_batch_result_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id ?>"></a>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td><span class="dashicons dashicons-yes <?php echo $qc_color ?>"></span></td>
                        <td><?php echo $d['approved_count'] . '/' . $d['total'] ?></td>
                        <?php if ($is_test_results && $d['count_new_result'] > 0) {
                        ?>
                            <td colspan="3" align="right"><span class="new_results"></span></td>
                        <?php }
                        $colspan = 5;
                        if ($is_test_results) {
                            if ($d['count_new_result'] > 0) {
                                $colspan = 2;
                            }
                        } ?>
                        <td align="right" colspan="<?php echo $colspan ?>" class="bactions">
                            <?php
                            if (count($d['labs']) == 1) {
                                echo '<div class="batch_actions">';
                                if ($use_upload) {
                            ?>
                                    <a class="btn_sell_all bsty1" sel="true">Select all</a>
                                    <a class="final_selected bsty2">Make Final</a>
                                    <a class="approve_selected bsty2">Approve All</a>
                                    <?php if ($can_fax) { ?>
                                        <a class="fax_pdf bsty3" data-batch_id="<?php echo $batch_id ?>">Fax PDF</a>
                                    <?php } ?>
                                    <?php if ($can_hold) { ?>
                                        <a class="hold_btn bsty4" data-batch_id="<?php echo $batch_id ?>">Hold</a>
                                        <a class="removehold_btn bsty5" data-batch_id="<?php echo $batch_id ?>">Remove Hold</a>
                                    <?php } ?>
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
                            <td colspan="<?php echo $is_test_results ? 10 : 11 ?>" class="nopadding">
                                <table class="report_tbl">
                                    <?php

                                    foreach ($d['labs'] as $lab_key => $row) {
                                        $lab_id = $row->lab_ID;
                                    ?>
                                        <tr class="parent_c parentacts" data-p="lab_<?php echo $lab_key . $batch_id ?>" data-lab_id="<?php echo $lab_key ?>" data-batch_id="<?php echo $batch_id ?>">
                                            <td style="width: 40px;"><i></i></td>
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
                        <tr class="child cbatch p_batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>">
                            <td colspan="<?php echo $is_test_results ? 10 : 11 ?>" class="nopadding"></td>
                        </tr>
                <?php
                    }
                }
            } else {
                ?>
                <tr>
                    <td colspan="<?php echo $is_test_results ? 10 : 11 ?>">No Results Found.</td>
                </tr>
            <?php
            }



            ?>
        </tbody>
    </table>
</div>


<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>

<script>
    var $bulkeditdateapprove = <?php echo isset($result_settings['bulk_approve_date']) && $result_settings['bulk_approve_date'] ? 1 : 0 ?>;
    PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
    jQuery(function($) {
        var $final_results = {
            0: 'None',
            2: 'Preliminary',
            1: 'Final',
            3: 'Amended Report'
        };


        function show_bulk_date_popup($btn) {
            $('body .loadingbody').remove();
            $('body #bulk_approve_box').remove();
            $('body').append('<div id="bulk_approve_box" style="display:none"><input type="hidden" autofocus="true" /><div class="frow"><label>Received Date: </label><input type="text" class="hasdatetime received_date" style="z-index:99"/></div><div class="frow"><label>Result Date: </label><input type="text" class="hasdatetime result_date"/></div><br>Leave empty if not change</div>');
            var $opts = {
                showButtonPanel: true,
                dateFormat: 'yy-mm-dd',
                timeFormat: 'hh:mm:ss',
                changeMonth: true,
                changeYear: true
            };
            $('.hasdatetime').datetimepicker($opts);
            $("#bulk_approve_box").dialog({
                modal: true,
                title: 'Bulk Approve',
                dialogClass: 'bulk_approve_box',
                width: '420px',
                buttons: [{
                        text: "Approve All",
                        class: "approveallcl",
                        click: function() {

                            $btn.data('can_approve', true);
                            $btn.data('received_date', $('input.received_date').val());
                            $btn.data('result_date', $('input.result_date').val());
                            $(this).dialog("close");
                            $btn.click();
                        }
                    },
                    {
                        text: "Cancel",
                        class: "cancelapprovecl",
                        click: function() {
                            $(this).dialog("close");
                        }
                    }
                ]
            });
            $('.approveallcl').focus();

        }

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
                    tr_action: 'ci_load_results_pcr',
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

                    var $scroll_top = $obj.offset().top - 100;
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
            if ($bulkeditdateapprove && !$(this).data('can_approve')) {
                show_bulk_date_popup($(this));
                return;
            }
            var $received_date = '';
            var $result_date = '';
            var $pr = $(this).parents('.parentacts');
            var $tbl = $('tr.p_' + $pr.data('p'));

            var $batch_id = $pr.data('batch_id');
            var $labid = $pr.data('lab_id');
            var $ref_lab_id = $pr.data('ref_lab_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })

            if ($bulkeditdateapprove) {
                $received_date = $(this).data('received_date');
                $result_date = $(this).data('result_date');
                $(this).data('can_approve', false)
            } else if ($ids == '') {
                if (!confirm('Do you want Approve all?')) {
                    return false;
                }
            } else if (!confirm('Do you want Approve Items Selected?')) {
                return false;
            }
            $('body').append('<div class="loading loadingbody"></div>');
            $.ajax({
                type: 'post',
                data: {
                    trajax: 2,
                    act: 'approve_selected',
                    batch_id: $batch_id,
                    lab_id: $labid,
                    ref_lab_id: $ref_lab_id,
                    ids: $ids,
                    received_date: $received_date,
                    result_date: $result_date
                },
                dataType: 'json',
                success: function(rs) {
                    $('body .loadingbody').remove();
                    if (rs.status == 'ok') {
                        var $ipcheck = $ids == '' ? 'input[type="checkbox"]' : 'input:checked';
                        var $done_ids = rs.done_ids ? rs.done_ids : [];
                        $($ipcheck, $tbl).each(function() {
                            var $id = $(this).val();
                            if ($done_ids && !$done_ids.includes($id)) {
                                return;
                            }
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
                            tralert(rs.msg);
                        }

                    } else {
                        tralert('Error. Please try again!');
                    }
                },
                error: function() {
                    tralert('Error. Please try again!');
                }
            })

            return false;
        })
        $('a.final_selected').click(function() {
            var $pr = $(this).parents('.parentacts');
            var $tbl = $('tr.p_' + $pr.data('p'));
            var $batch_id = $pr.data('batch_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })
            if ($ids == '') {
                tralert('Please Select least 1 item!');
            } else if (confirm('Do you want change to Final Items Selected?') == true) {
                $.ajax({
                    type: 'post',
                    data: {
                        trajax: 2,
                        act: 'final_selected',
                        batch_id: $batch_id,
                        ids: $ids
                    },
                    dataType: 'json',
                    success: function(rs) {
                        if (rs.status == 'ok') {
                            var $ipcheck = $ids == '' ? 'input[type="checkbox"]' : 'input:checked';
                            $($ipcheck, $tbl).each(function() {
                                var $id = $(this).val();
                                var $tr = $(this).parents('tr.childp');
                                $('td .finaltext', $tr).html('Final');

                            })
                        } else {
                            tralert('Error. Please try again!');
                        }
                    },
                    error: function() {
                        tralert('Error. Please try again!');
                    }
                })
            }
            return false;
        })


        $('a.show_batch_result_miss').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=pcr&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_back', function() {
                var $bid = $(this).data('batch_id');
                tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=pcr&width=800');
                return false;
            }).on('click', 'a.show_batch_result_miss_upload', function() {
                var $bid = $(this).data('batch_id');
                var $lab_id = $(this).data('lab_id');
                tb_show('miss upload', '?tr_action=ci_show_batch_result_miss_upload&batch_id=' + $bid + '&lab_id=' + $lab_id + '&type=pcr&width=800');
                return false;
            }).on('click', 'a.show_batch_result_files', function() {
                var $bid = $(this).data('batch_id');
                tb_show('Uploaded Files in Batch ' + $bid, '?tr_action=ci_show_batch_result_files&batch_id=' + $bid + '&type=pcr&width=800');
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
                    tralert('Not found');
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
                    tralert('Not found');
                    return false;
                }
                $ids = $ids.join(',');
                location.href = '?tr_action=ci_download_pdfs&downloaded=1&btype=pcr&ids=' + $ids;
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
                    tralert('Not found');
                    return false;
                }
                $ids = $ids.join(',');
                location.href = '?tr_action=ci_download_pdfs&downloaded=1&btype=pcr&ids=' + $ids;
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
            }).on('click', 'tr.parent', function(e) {
                if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;

                var $pr = $('tr.p_' + $(this).data('p'));
                $('tr.parent.active').removeClass('active');

                if ($pr.hasClass('cbatch')) {
                    $('tr.child_c').hide();
                    $('tr.child').hide();
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
            })
            .on('click', 'tr.parent_c', function(e) {
                if (e.target.tagName == 'A') return;
                $('tr.child_c').hide();
                if ($(this).data('showed') != '1') {
                    $('tr.p_' + $(this).data('p')).show();
                    $('tr.parent_c').data('showed', '0');
                    $(this).data('showed', '1');
                    $(this).addClass('active');
                    var $obj = $('tr.p_' + $(this).data('p'));
                    var $scroll_top = $obj.offset().top - 100;
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
            }).on('click', 'a.download_pdf', function() {
                var $obj = $(this);
                $obj.parent().find('.dashicons-yes').remove();
                $obj.parent().append('<span class="dashicons dashicons-yes"></span>');
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