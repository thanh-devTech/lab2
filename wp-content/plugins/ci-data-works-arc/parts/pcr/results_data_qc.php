<?php
global $wpdb, $current_role;


$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

if ($act == 'data_qc_plate384') {
    include(trpdd_get_file_path('pcr', 'data_qc_plate384'));
    exit;
} else if ($act == 'data_qc_plate98') {
    include(trpdd_get_file_path('pcr', 'data_qc_plate98'));
    exit;
}


$child_tabs = array(
    'n1n2' => 'N1,N2 & RNAsep',
    'n1n2rp' => 'N1,N2 & RP',
    'n1rp' => 'N1 & RP',
    'ms2' => 'ORF1ab,N Gene & MS2',
    'famvic' => 'FAM & VIC',
    'ic' => 'COVID-19 & IC',
);

$hascovidgroup = $wpdb->get_var("select count(*) from ci_test_groups where tg_type = 'pcr' 
and tg_is_covid = 1 and tg_show_req = 1");
if(!$hascovidgroup)
{
    $child_tabs = array();
}
//check groups
$groups = $wpdb->get_results("select tg_ID, tg_name
 from ci_test_groups where tg_type = 'pcr' 
and tg_other_options like '%show_qc\":\"1%' ");
foreach ($groups as $g) {
    $child_tabs['g_' . $g->tg_ID] = $g->tg_name;
}


if (!tr_get_option('ci_pdf_data', 'pcrcovid_famvic')) {
    unset($child_tabs['famvic']);
} else {
    unset($child_tabs['ic']);
}

if (!tr_get_option('ci_pdf_data', 'pcrcovid_n12rp')) {
    unset($child_tabs['n1n2']);
}

if (!tr_get_option('ci_pdf_data', 'pcrcovid_n12_r_p')) {
    unset($child_tabs['n1n2rp']);
}

if (!tr_get_option('ci_pdf_data', 'pcrcovid_ms2rp') && !tr_get_option('ci_pdf_data', 'pcrcovid_ms2rpsgen')) {
    unset($child_tabs['ms2']);
}

if (!tr_get_option('ci_pdf_data', 'pcrcovid_n1_rp')) {
    unset($child_tabs['n1rp']);
}

$ctab = !empty($_REQUEST['ctab']) && isset($child_tabs[$_REQUEST['ctab']]) ? $_REQUEST['ctab'] : key($child_tabs);
$instrument = 'IC';
if ($ctab == 'n1n2') {
    $instrument = 'N1N2';
} else if ($ctab == 'n1n2rp') {
    $instrument = 'N1N2RP';
} else if ($ctab == 'ms2') {
    $instrument = 'MS2';
} else if ($ctab == 'n1rp') {
    $instrument = 'N1RP';
} else if ($ctab == 'famvic') {
    $instrument = 'FAMVIC';
}
$covid_tests = trpdd_get_covid_tests($instrument);

$log_url = WP_CONTENT_URL . '/log/results_pcr/';
add_thickbox();

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$is_test_results = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';
$user_id = get_current_user_id();
$box_options = get_option('ci_box_settings', array());
$offset_time = get_option('gmt_offset') * HOUR_IN_SECONDS;

$use_upload = isset($use_upload) ? $use_upload : false;
$can_upload_box = !empty($box_options['access_token']);

if ($_GET['page'] == 'ci_test_results') {
    $use_upload = false;
}


$group_covid = $wpdb->get_row("select * from ci_test_groups where (tg_name ='nCoV-2019' or tg_is_covid = 1) and tg_type='pcr' ");

$covid_group_id = $group_covid->tg_ID;

$where      = '';
$where_rf   = '';
$bygroup    = false;
$join_bygroup = '';
if (stripos($ctab, 'g_') !== false) {
    $bygroup = true;
    $covid_group_id = str_replace('g_', '', $ctab);
    $covid_tests = array();
    $where_rf   .= " and rf.rf_covid_type = '' ";
    $where      .= " and FIND_IN_SET('GROUP_{$covid_group_id}', ba.panel_tests_requested) ";
    $join_bygroup = " JOIN ci_batches_pcr ba ON FIND_IN_SET(ba.assen, rf.rf_assen_list)  " . $where;
} else if (count($child_tabs) > 1) {
    $where_rf .= " and rf.rf_covid_type = '{$ctab}' ";
}
//page
$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();



$check_author = false;
switch ($current_role) {
    case 'administrator_p':
    case 'administrator':
    case 'AdminCIDW':
    case 'pcr_verify':
    case 'results':
    case 'labmanager':

        break;
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

        $cls_id = array();
        $clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id}", ARRAY_A);
        foreach ($clis as $row) {
            $cls_id[] = $row['cID'];
        }
        $cls_id = implode(', ', $cls_id);
        if (!empty($cls_id)) {
            $where .= " and ba.cli_ID in  ({$cls_id})";
        } else {
            $check_author = true;
        }
        break;

    case 'lab':
    case 'labb':

        break;

    case 'lab_tech':

        $edit_time -= 86400 * 1000;
        break;

    case 'ref_lab_tech':
    case 'reflab_manager':

        $ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
        $where .= " and ba.ref_lab_ID = '{$ref_lab_id}' ";
        break;

    case 'collector';
        $check_author = true;
        break;

    case 'office_manager':
    case 'multi_site_manager':
    case 'clinic':
        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        } else {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where .= " and ba.cli_ID in ( {$cli_ids} )";
        }
        break;
    case 'doctor':
        $doc_id = get_user_meta($user_id, '_doc_id', true);
        $where .= " and ba.doc_ID  = {$doc_id} ";
        //$check_author = true;
        break;
    case 'lab_director':
        //$where .= " and ba.cli_ID >0";
        // $check_author = true;
        break;
    case 'lab_manager':
        $reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_admins`= {$user_id})
                             ");


        $reps = implode(',', $reps);
        $cls_id = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
        $cls_id = implode(',', $cls_id);

        if (!empty($cls_id)) {
            $where .= " and ba.cli_ID in  ({$cls_id})";
            $where_clinic .= " and cID in  ({$cls_id})";
        } else {
            $check_author = true;
        }
        break;
    case 'lab_sec':
        $edit_time -= 86400 * 4;
        break;
    case 'leadlabsecman':
    case 'labsecman':
        $edit_time -= 86400 * 365;
        break;
    case 'pcrLabManager':
        $edit_time += 86400 * 9999;
        break;
    default:
        $check_author = true;
        $edit_time -= 86400 * 4;
        break;
}
$where_author = '';
if ($check_author) {
    $user_ids = tr_dpp_get_users_in_group($user_id);
    $user_ids = implode(',', $user_ids);
    $where_author .= " and ba.author_ID in ( {$user_ids} ) ";
    $where .= $where_author;
}


$archive_date   = tr_get_option('ci_pdf_data', 'archive_date', '');
if (!empty($archive_date)) {
    $archive_bid = str_replace('-', '', $archive_date);
    $where .= " and ba.batch_id > {$archive_bid} ";
    $where_rf .= " and rf_upload_date > '{$archive_date}' ";
}


$search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';
if (!empty($search)) {

    $where .= " and ba.assen like '{$search}%' ";
}

$where .= $where_rf;

$sql_total = "select 
        count(distinct(rf.rf_upload_date)) as c 
        from ci_reports_files rf 
        {$join_bygroup}
        where rf_btype = 'pcr' {$where_rf}";

$total  = $wpdb->get_var($sql_total);
$pagination->setTotal($total);


$sql_limit = "select distinct(rf.rf_upload_date)
    from ci_reports_files rf
    {$join_bygroup}
    where rf.rf_btype = 'pcr' {$where_rf}
    order by rf.rf_upload_date desc
     limit {$offset},{$row_per_page}";


if (!empty($search)) {
    $sql_limit = "select distinct(rf_upload_date)
    from ci_reports_files rf
    JOIN ci_batches_pcr ba ON FIND_IN_SET(ba.assen, rf.rf_assen_list) 
    where rf_btype = 'pcr' {$where}
    order by rf_upload_date desc
     limit {$offset},{$row_per_page}";
}

$upload_dates = $wpdb->get_col($sql_limit);
$upload_dates = implode("','", $upload_dates);
if (empty($upload_dates)) {
    $upload_dates = '-1';
}


$sql = "select *
    from ci_reports_files rf
    {$join_bygroup}
    where rf_btype = 'pcr' 
        and rf_upload_date in('{$upload_dates}')
    order by rf_upload_date desc";

if (!empty($search)) {
    $sql = "select *
    from ci_reports_files rf
    JOIN ci_batches_pcr ba ON FIND_IN_SET(ba.assen, rf.rf_assen_list) 
    where rf_btype = 'pcr' 
            and rf_upload_date in('{$upload_dates}')
        {$where}
    order by rf_upload_date desc";
}
$results = $wpdb->get_results($sql);
$count_rows = count($results);


if (isset($_REQUEST['debug'])) {

    echo $sql_total, '<br>';
    echo 'qtotal: ' . $q_total, '<br>';
    echo $sql_limit, '<br>';
    echo 'qlimit: ' . $q_limit, '<br>';
    echo $sql, '<br>';
}
?>
<h2>Data QC</h2>
<?php
if (count($child_tabs) > 1) {
?>
    <div class="wrap" style="margin-bottom:10px">
        <h2 class="nav-tab-wrapper">
            <?php
            foreach ($child_tabs as $k => $txt) {
                $class = $ctab == $k ? 'nav-tab-active' : '';
                $link = Tr_Base_Class_V6::link(array('ctab' => $k, 'act' => '', 'id' => '', 'orderby' => '', 'order' => '', 's' => '', 'paged' => ''), false);
                echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
            }
            ?>
        </h2>
    </div>
<?php
}
?>
<?php Tr_Session_Class::show() ?>
<div class="tablenav">
    <form method="get" class="left">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <input type="hidden" name="ctab" value="<?php echo isset($_GET['ctab']) ? $_GET['ctab'] : '' ?>" />
        <?php if (isset($_GET['view'])) { ?>
            <input type="hidden" name="view" value="1" />
        <?php } ?>
        <label>Accession:</label>
        <input type="search" name="s" value="<?php echo $search ?>" />
        <input type="submit" class="button bsty6" value="Search" />


    </form>
    <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead class="sticky">
            <tr>
                <th style="width:42px">#</th>
                <?php
                if (tr_get_option('ci_pdf_data', 'pcr_qcshowplate')) { ?>
                    <th style="width: 120px;">Plate</th>
                    <th style="width: 80px">Well</th>
                <?php
                } else {
                ?>
                    <th style="width: 120px;">Date</th>
                    <th style="width: 80px">Position</th>
                <?php
                }
                ?>
                <th style="width: 18%;">Panel</th>
                <th style="width: 100px;">Result Type</th>
                <?php
                $covid_test_w = 160 - count($covid_tests) * 5;
                foreach ($covid_tests as $t) {
                ?>
                    <th class="center" style="width: <?php echo $covid_test_w ?>px"><?php echo $t ?></th>
                <?php
                }
                ?>
                <th style="width:40px"></th>
                <th style="width:60px">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            if (count($results) > 0) {
                foreach ($results as $i => $row) {

                    $fdate = $row->rf_upload_date;
                    $mfile = $row->rf_file;

                    if (!isset($batches[$fdate]) || !is_array($batches[$fdate])) {
                        $batches[$fdate] = array(
                            'files' => array(),
                        );
                    }

                    if (!isset($batches[$fdate]['files'][$mfile])) {
                        $batches[$fdate]['files'][$mfile] = $row;
                    }
                }
            ?>

                <?php
                foreach ($batches as $batch_id => $d) {
                    if (!isset($d['files'])) {
                        continue;
                    }
                    $classes = '';
                    $parent_total = 0;
                    $parent_approved = 0;
                    $parent_onhold = 0;

                    ob_start();
                ?>
                    <table class="report_tbl">
                        <?php
                        foreach ($d['files'] as $filename => $arr) {

                            $mkey = md5($filename);
                            $tab_key = $mkey . $batch_id;

                            $assens = explode(',', $arr->rf_assen_list);
                            $assens_str = implode("','", $assens);

                            if ($bygroup) {
                                $sql_counting = "select count(*) as total,
                                SUM(IF(re.estatus='C',1,0)) as approved,
                                SUM(IF(ba.batch_status='hold',1,0)) as onhold
                                from ci_reports_pcr re
                                join ci_batches_pcr ba ON ba.assen = re.patient_id
                                 where re.patient_id in ('{$assens_str}')";
                            } else {
                                $sql_counting = "select count(*) as total,
                                SUM(IF(re.estatus='C',1,0)) as approved,
                                SUM(IF(ba.batch_status='hold',1,0)) as onhold
                                from ci_reports_pcr re
                                join ci_batches_pcr ba ON ba.assen = re.patient_id
                                join ci_results_pcr rs On rs.report_id = re.id and rs.drug = '{$covid_tests[0]}'
                                 where re.patient_id in ('{$assens_str}')";
                            }




                            $counting = $wpdb->get_row($sql_counting);

                            $total = $counting->total;
                            $approved = $counting->approved;
                            $onhold = $counting->onhold;
                            $unapprove = $total - $approved;

                            if ($total == 0) {
                                continue;
                            }
                            $parent_total += $total;
                            $parent_approved += $approved;
                            $parent_onhold += $onhold;


                        ?>
                            <tr class="parent_c" data-p="<?php echo $tab_key ?>" data-batch_id="<?php echo $batch_id ?>">
                                <td style="width: 42px"><i></i></td>
                                <td style="width:45%">
                                    <a href="<?php echo $log_url . $filename ?>" target="_blank"><?php echo $filename ?></a>
                                </td>
                                <td style="width:120px">
                                    <div class="ci-wrap-counter tooltip">
                                        <span class="white"><?php echo $total; ?> </span> /
                                        <span class="green"> <?php echo $approved; ?> </span> /
                                        <span class="yellow"> <?php echo $unapprove; ?> </span> /
                                        <span class="holdcount"> <?php echo $onhold; ?> </span>
                                        <span class="tooltiptext">Total: <?php echo $total; ?>
                                            <br> Approved: <?php echo $approved; ?>
                                            <br> Unapproved: <?php echo $unapprove; ?>
                                            <br> Hold: <?php echo $onhold; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="bactions">
                                    <div class="batch_actions">

                                        <a class="plate_heatmap bsty1">Plate HeatMap</a>

                                        <?php if ($use_upload) { ?>
                                            <a class="btn_sell_all bsty2" sel="true">Select all</a>
                                            <a class="fax_selected bsty3">Fax Selected</a>
                                            <a class="final_selected bsty4">Make Final</a>
                                            <a class="approve_selected bsty6">Approve Selected</a>
                                        <?php } ?>
                                    </div>
                                </td>

                            </tr>
                            <tr class="child_c <?php echo 'p_' . $tab_key ?>" data-pc="<?php echo $tab_key ?>" data-filename="<?php echo $filename ?>" data-batch_id="<?php echo $batch_id ?>">
                                <td colspan="4" class="nopadding"></td>
                            </tr>
                        <?php
                        }

                        ?>
                    </table>
                    <?php
                    $child_table = ob_get_clean();

                    $parent_unapproved = $parent_total -  $parent_approved;


                    if ($parent_total == 0) {
                        if (isset($_GET['debug'])) {
                            var_dump($parent_total);
                            var_dump($sql_counting);
                            echo '<br><br>';
                        }
                        continue;
                    }
                    ?>
                    <tr class="parent <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>">
                        <td><i></i></td>
                        <td><?php echo $batch_id ?></td>
                        <td colspan="4">
                            <div class="ci-wrap-counter tooltip" style="margin-right:20px">
                                <span class="white"> <?php echo $parent_total; ?> </span> /
                                <span class="green"> <?php echo $parent_approved; ?> </span> /
                                <span class="yellow"> <?php echo $parent_unapproved; ?> </span> /
                                <span class="holdcount"> <?php echo $parent_onhold; ?> </span>
                                <span class="tooltiptext">
                                    Total: <?php echo $parent_total; ?>
                                    <br> Approved: <?php echo $parent_approved; ?>
                                    <br> Unapproved: <?php echo $parent_unapproved; ?>
                                    <br> Hold: <?php echo $parent_onhold; ?>
                                </span>
                            </div>
                        </td>
                        <td colspan="<?php echo count($covid_tests) + 2 ?>"></td>
                    </tr>
                    <tr class="child p_batch_<?php echo $batch_id ?>">
                        <td colspan="<?php echo count($covid_tests) + 8 ?>" class="nopadding">
                            <?php echo $child_table ?>
                        </td>
                    </tr>
                <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="<?php echo count($covid_tests) + 8 ?>">No Results Found.</td>
                </tr>
            <?php
            }

            ?>
        </tbody>
    </table>
</div>

<style>
    tr.bs_hold .cid input {
        display: none;
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>
<script>
    PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
    jQuery(function($) {
        var $cvtype = '<?php echo $ctab ?>';
        var $heatmap_filename = '';
        var $final_results = {
            0: 'None',
            2: 'Preliminary',
            1: 'Final',
            3: 'Amended Report'
        };

        function trcpp_load_results($obj) {
            $('td', $obj).html('<div class="loading center"></div>');
            var $batch_id = $obj.data('batch_id');
            var $filename = $obj.data('filename');
            $.ajax({
                data: {
                    tr_action: 'ci_load_results_pcr',
                    batch_id: $batch_id,
                    data_qc: '1',
                    filename: $filename,
                    cvtype: $cvtype
                },
                type: 'post',
                success: function(rs) {
                    if (rs.indexOf('need_reload') > -1) {
                        $obj.html('');
                        trcpp_load_results($obj, $trpr, $batch_id, $lab_id, $ref_lab_id);

                    } else {
                        var $html = $(rs);
                        $('td', $obj).html($html);
                        $('.cid', $html).each(function() {
                            if ($(this).parent().hasClass('disabled')) {
                                $(this).html('');
                                return;
                            }
                            var $tr = $(this).parents('.childp');
                            if (!$tr.hasClass('bs_hold') && !$tr.hasClass('no_value') && !$tr.hasClass('no_batch')) {
                                $(this).html('<input type="checkbox" value="' + $(this).html() + '"/>');
                            } else {
                                $(this).html('');
                            }

                        })
                        $('.final_results', $html).each(function() {
                            var $html = '<select>';
                            for (k in $final_results) {
                                $html += '<option value="' + k + '" ' + (k == $(this).data('val') ? 'selected' : '') + '>' + $final_results[k] + '</option>';
                            }
                            $html += '</select>';
                            $(this).html($html);
                        })
                    }

                },
                error: function() {
                    trcpp_load_results($obj);
                }
            })
        }
        $('body').on('change', 'td.final_results select', function() {
                var $this = $(this);
                var $td = $(this).parent();
                var $tr = $td.parent();
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
                        if (rs.status == 'ok') {} else {
                            $this.change();
                        }
                    },
                    error: function() {
                        $this.change();
                    }
                })
            })
            .on('change', 'select.test_filter', function() {
                var $pr = $(this).parents('.data_qc_plate');
                var $vl = $(this).val();
                $('.platetblsmall td', $pr).each(function() {
                    var $colors = $(this).data('colors');
                    if ($colors && $colors[$vl]) {
                        $(this).attr('style', 'background:' + $colors[$vl] + '!important');
                    }

                })
            })
            .on('click', 'a.plate_heatmap', function() {
                tb_show('Plate Heatmap', '#TB_inline');
                var $tr = $(this).parents('tr.parent_c');
                var $obj = $('tr.p_' + $tr.data('p'));
                var $batch_id = $obj.data('batch_id');
                var $filename = $obj.data('filename');
                $heatmap_filename = $filename;
                $('#TB_ajaxContent').html('<div class="loading center"></div>');
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'data_qc_plate384',
                        filename: $filename
                    },
                    type: 'post',
                    success: function(rs) {
                        $('#TB_ajaxContent').html(rs);

                    },
                    error: function() {

                    }
                })
                return false;
            })
            .on('click', 'table.platetbl384 tr td', function() {
                $('#TB_ajaxContent').html('<div class="loading center"></div>');
                var $pl_id = $(this).data('pl_id');
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'data_qc_plate98',
                        filename: $heatmap_filename,
                        pl_id: $pl_id
                    },
                    type: 'post',
                    success: function(rs) {
                        $('#TB_ajaxContent').html(rs);

                    },
                    error: function() {

                    }
                })
            });

        $('a.approve_selected').click(function() {
            var $pr = $(this).parents('.parent_c');
            var $tbl = $('tr.p_' + $pr.data('p'));
            var $batch_id = $pr.data('batch_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })
            if ($ids == '') {
                alert('Please Select least 1 item!');
            } else if (confirm('Do you want Approve Items Selected?') == true) {
                $.ajax({
                    type: 'post',
                    data: {
                        trajax: 2,
                        act: 'approve_selected',
                        batch_id: $batch_id,
                        ids: $ids
                    },
                    dataType: 'json',
                    success: function(rs) {
                        if (rs.status == 'ok') {
                            $('input:checked', $tbl).each(function() {

                                var $tr = $(this).parents('tr.childp');
                                $('.approvebtn', $tr).html('Reverse');

                                $('.editlink', $tr).hide();
                                $('.pdflink', $tr).show();
                                $('td.statuscol', $tr).html('C').addClass('result_C').removeClass('result_M');
                                $(this).removeAttr('checked');
                            })
                            $('a.btn_sell_all').html('Select All');
                        } else {
                            alert('Error. Please try again!');
                        }
                    },
                    error: function() {
                        alert('Error. Please try again!');
                    }
                })
            }
            return false;
        })
        $('a.final_selected').click(function() {
            var $pr = $(this).parents('.parent_c');
            var $tbl = $('tr.p_' + $pr.data('p'));
            var $batch_id = $pr.data('batch_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })
            if ($ids == '') {
                alert('Please Select least 1 item!');
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
                            $('input:checked', $tbl).each(function() {
                                var $tr = $(this).parents('tr.childp');
                                $('.final_results', $tr).data('val', '1');
                                $('.final_results select', $tr).val('1');
                                //$(this).removeAttr('checked');
                            })
                        } else {
                            alert('Error. Please try again!');
                        }
                    },
                    error: function() {
                        alert('Error. Please try again!');
                    }
                })
            }
            return false;
        })


        $('a.fax_selected').click(function() {
            var $pr = $(this).parents('.parent_c');
            var $tbl = $('tr.p_' + $pr.data('p'));
            var $batch_id = $pr.data('batch_id');
            var $ids = '';
            $('input:checked', $tbl).each(function() {
                $ids += $(this).val() + ',';
            })
            if ($ids == '') {
                alert('Please Select least 1 item!');
            } else if (confirm('Do you want Fax Items Selected?') == true) {
                var $url = '?tr_action=ci_upload_result_pdf_to_efax&type=pcr&batch_id=' + $batch_id + '&kt=<?php echo time() ?>&ids=' + $ids;
                <?php if (isset($_GET['debug'])): ?>
                    $url += '&rsdb=1';
                <?php endif; ?>
                tb_show('Fax PDF', $url + '&TB_iframe=true&width=600&height=500');
            }

            return false;
        });

        $('a.btn_sell_all').click(function() {
            var sleat = $(this).attr("sel");
            var $pid = $(this).parents('.parent_c').data('p');

            if (sleat == "true") {
                $(".p_" + $pid + " input:checkbox").prop('checked', true).change();
                $(this).attr("sel", false);
                $(this).html("Unselect All")
            } else {
                $(".p_" + $pid + " input:checkbox").prop('checked', false).change();
                $(this).attr("sel", true);
                $(this).html("Select All")
            }
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
                $('body,html').scrollTop($obj.offset().top - 150);
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
        $('tr.parent_c').click(function(e) {
            if (e.target.tagName == 'A') return;
            $('tr.child_c').hide();
            $('tr.parent_c').removeClass('active');
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);

                if ($('td', $obj).html() == '') {
                    trcpp_load_results($obj);
                }

            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });

    })
</script>