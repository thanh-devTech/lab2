<?php
global $wpdb, $current_role, $blog_id;

include_once(TRCPDD_PATH . 'pages/formdata.php');


wp_enqueue_script('admin_result', TRCPDD_URL . 'js/admin_result.min.js', array(), TRCPDD_VS);
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

$btype          = 'req';
$pending        = isset($_REQUEST['pending']);
$user_id        = get_current_user_id();
$box_options    = get_option('ci_box_settings', array());
$req_settings = get_option('ci_req_settings_req', array());
$req_spcode   = tr_get_option('ci_pdf_data', 'req_spcode');

$use_upload     = isset($use_upload) ? $use_upload : false;
$can_upload_box = !empty($box_options['access_token']);
$is_test_results = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';

if ($_GET['page'] == 'ci_test_results') {
    $use_upload = false;
}

$orderby        = ' order by ba.batch_id desc ';
$where          = "where ba.batch_id !='' and ba.batch_id!='0' and ba.is_saved = 0 ";
$where .= " and ba.batch_status != 'archived' ";

if ($pending) {
    $where .= " and (ba.result_pdf = '' or ba.result_pdf is null) and re.id is null and ba.batch_status not like 're\_%' and ba.batch_status !='rejected'  ";
} else {
    $where .= " and (ba.result_pdf != '' or re.id > 0 or ba.batch_status like 're\_%' or ba.batch_status ='rejected' )";
}


$conditions = trpdd_get_batch_conditions(0, 'req');
$where      .=  $conditions['where'];

if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}
$edit_time      = $conditions['edit_time'];
$can_edit       = $conditions['can_edit_result'];
$can_delete     = $conditions['can_delete_result'];
$can_fax        = $conditions['can_fax'];
$can_hold       = $conditions['can_hold_result'];

if (!tr_current_role_in(array('administrator', 'lab', 'lab_rep')) && current_user_can('only_rs_final')) {
    $where .= " and re.final_results = 1 ";
}


if (!empty($_REQUEST['s'])) {

    $search = trim($_REQUEST['s']);
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    if (stripos($search, 'test:') !== false) {
        $pn = str_replace('test:', '', $search);
        $pn = trim($pn);
        $test_ids = $wpdb->get_col("select te_ID from ci_test_panels where te_name like '%{$pn}%' ");
        $wheretests = array();
        foreach ($test_ids as $tid) {
            $wheretests[] = "  find_in_set('{$tid}', ba.panel_tests_requested  ) ";
        }
        $wheretests = implode(' or ', $wheretests);
        $where .= " and ({$wheretests})";
    } else if (stripos($search, 'panel:') !== false) {
        $pn = str_replace('panel:', '', $search);
        $pn = trim($pn);
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
    } else if (strpos($search, ',') > 0) {
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
$sql_total = "select count(distinct(ba.batch_id)) from ci_batches_req ba 
 left join ci_reports_req re ON re.patient_id = ba.assen 
 {$where}";

$row_per_page   = 10;
$pagination     = new Tr_Pagination_class2($row_per_page);
$offset         = $pagination->offset();
$pagination->setTotal($wpdb->get_var($sql_total));

$sql_limit = "select distinct(ba.batch_id) from ci_batches_req ba
                left join ci_reports_req re ON re.patient_id = ba.assen 
        {$where}
        {$orderby}  
        limit {$offset},{$row_per_page}";


$batch_ids = $wpdb->get_col($sql_limit);

if (count($batch_ids) > 0) {
    $batch_ids = implode(',', $batch_ids);
    $where .= " and ba.batch_id in({$batch_ids}) ";

    $sql = "select 
                ba.*,
                lab.*,
                count(*) as total_items,
                sum(if(ba.box_uploaded>0,1,0)) as count_uploaded ,
                sum(if(re.estatus='C',1,0)) as approved_count
                from ci_batches_req ba 
                left join ci_reports_req re ON re.patient_id = ba.assen
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
               {$where}
               group by ba.batch_id,lab.lab_dba_init
                {$orderby}";
    $results = $wpdb->get_results($sql);
} else {
    $results = array();
}

if (isset($_GET['debug'])) {

    var_dump($wpdb->last_query);
}
?>
<div class="wrap">
    <h2>Results</h2>
    <?php Tr_Session_Class::show() ?>

    <div class="tablenav top">
        <form method="get" style="float: left;">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
            <label>Accession/Patient/Clinic/Doctor:</label>
            <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
            <input type="submit" class="button bsty6" value="Search" />

        </form>
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo isset($count_rows) ? $count_rows : 0 ?>">
            <thead>
                <tr>
                    <th style="width:3%">Pos</th>
                    <th style="width: 4%">Batch</th>
                    <th style="width: 8%;">Accession</th>
                    <th style="width: 16%;">Patient Name</th>
                    <th style="width: 18%;">Clinic</th>
                    <th style="width: 80px;">Doc</th>
                    <th style="width:72px">Result Type</th>
                    <th style="width:72px">Status</th>
                    <th class="center">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $batches = array();
                if (count($results) > 0) {
                    foreach ($results as $i => $row) {
                        if (empty($row->batch_id)) {
                            $row->batch_id = date('Ymd', strtotime($row->batch_created));
                        }

                        if (!isset($batches[$row->batch_id]) || !is_array($batches[$row->batch_id])) {
                            $batches[$row->batch_id] = array(
                                'qc' => 0,
                                'total' => 0,
                            );
                        }
                        $batches[$row->batch_id]['approved_count'] += $row->approved_count;
                        $batches[$row->batch_id]['not_approved_count'] += $row->total_items - $row->approved_count;
                        $batches[$row->batch_id]['total'] += $row->total_items;
                        //$batches[$row->batch_id]['date'] = $row->collection_date;
                        $batches[$row->batch_id]['date'] = date('Y-m-d', strtotime($row->doc));
                        $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->total_items;
                        $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
                    }
                ?>

                    <?php
                    foreach ($batches as $batch_id => $d) {
                    ?>
                        <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                            <td><i></i></td>
                            <td colspan="3"><?php echo $batch_id ?></td>
                            <td title="Approved/Not Approved"><?php echo $d['approved_count'] . '/' . $d['not_approved_count'] ?></td>
                            <td><?php echo $d['date'] ?></td>
                            <td colspan="3" style="text-align: right;" class="bactions">
                                <div class="batch_actions">
                                    <?php if (!$is_test_results) { ?>
                                        <?php if ($can_fax) { ?>
                                            <a class="fax_pdf bsty1" data-batch_id="<?php echo $batch_id ?>">Fax PDF</a>
                                        <?php } ?>
                                        <?php if ($can_hold) { ?>
                                            <a class="hold_btn bsty2" data-batch_id="<?php echo $batch_id ?>">Hold</a>
                                            <a class="removehold_btn bsty5" data-batch_id="<?php echo $batch_id ?>">Remove Hold</a>
                                        <?php } ?>
                                        <?php if ($can_upload_box) { ?>
                                            <a class="uploadpdf bsty4" data-batch_id="<?php echo $batch_id ?>">Upload PDF</a>
                                        <?php } ?>
                                    <?php
                                    } else {
                                    ?>
                                        <a class="printbatch bsty1">Print All</a>
                                        <a class="downloadbatcheachfile bsty7">Download Ind</a>
                                        <a class="downloadbatch bsty4">Download All</a>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="child p_batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>">
                            <td colspan="<?php echo 9 ?>" class="nopadding"></td>
                        </tr>
                    <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="<?php echo 9 ?>">No Results Found.</td>
                    </tr>
                <?php
                }



                ?>
            </tbody>
        </table>
    </div>

    <div class="tablenav bottom"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

    <script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>

    <script>
        PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
        jQuery(function($) {
            function trcpp_load_results($batch_id) {
                var $obj = $('tr.p_batch_' + $batch_id + ' td');
                var $trpr = $('tr[data-p="batch_' + $batch_id + '"]');
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
                        act: 'load_results',
                        trajax: 1,
                        batch_id: $batch_id
                    },
                    type: 'post',
                    success: function(rs) {
                        var $html = $(rs);
                        $obj.html($html);
                        $('.cid', $html).each(function() {
                            $(this).prepend('<input type="checkbox" value="' + $(this).data('id') + '"/>');
                        })
                        $('a.printbatch', $trpr).show();
                        $('a.downloadbatch', $trpr).show();
                        $('a.downloadbatcheachfile', $trpr).show();
                        $('.batch_actions', $trpr).show();
                    },
                    error: function(c) {
                        trcpp_load_results($batch_id);
                    }
                })
            }
            $('body').on('click', 'a.delete', function() {
                var $pr = $(this).parents('tr.childp');
                var $assen = $pr.data('assen');
                var $id = $pr.data('id');
                if (confirm('Are you sure you want to delete the uploaded results for {' + $assen + '}?') == true) {
                    location.href = '<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_delete')) ?>&id=' + $id;
                }
                return false;
            })




            $('body').on('click', 'a.print_pdf', function() {
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
            }).on('click', 'a.printbatch', function(e) {
                e.preventDefault();
                var $urls = [];
                var $trpr = $(this).parents('tr.parent');
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
            }).on('download_error', 'a.downloadbatch', function() {
                $(this).html('Download');
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
                location.href = '?tr_action=ci_download_pdfs&btype=req&ids=' + $ids;
                return false;
            }).on('click', 'a.downloadbatcheachfile', function(e) {
                var $ids = [];
                var $trpr = $(this).parents('tr.parent_c');
                if ($trpr.length == 0) {
                    $trpr = $(this).parents('tr.parent');
                }
                var $trchild = $('tr.p_' + $trpr.data('p'));
                $('tr.childp', $trchild).each(function() {
                    var $id = $(this).data('id');
                    $ids.push($id);
                    window.open('?tr_action=ci_results_pdf&btype=req&id=' + $id);
                })
                if ($ids.length == 0) {
                    alert('Not found');
                    return false;
                }


                return false;
            })

            $('tr.parent').click(function(e) {
                if (e.target.tagName == 'A') return;
                $('tr.child').hide();
                $('tr.parent.active').removeClass('active');
                var $obj = $('tr.p_' + $(this).data('p'));
                if ($(this).data('showed') != '1') {
                    $obj.show();
                    $('tr.parent').data('showed', '0');
                    $(this).data('showed', '1');
                    $(this).addClass('active');
                    setCookie('active_rs', $(this).data('p'));
                    var $scroll_top = $obj.offset().top - 150;
                    if ($('#header_bar').length) {
                        $scroll_top -= $('#header_bar').outerHeight();
                    }
                    $('body,html').scrollTop($scroll_top);
                    var $batch_id = $obj.data('batch_id');
                    trcpp_load_results($batch_id);
                } else {
                    $obj.hide();
                    $(this).data('showed', '0');
                    $(this).removeClass('active');
                    $('a.printbatch', $(this)).hide();
                }
                return false;
            });

            $('.drugs').click(function() {
                $(this).parents('form').submit();
            })
            var $old_active = getCookie('active_rs');
            if ($old_active && $old_active != null) {
                var $obj = $('tr.p_' + $old_active);
                try {
                    $obj.show();
                    $('[data-p="' + $old_active + '"]').data('showed', '1').addClass('active');
                    if ($old_active.indexOf('lab_') > -1) {
                        $obj.parents('.child').show();
                    }
                    //$('body,html').scrollTop($obj.offset().top-150);
                    $batch_id = $obj.data('batch_id');
                    setTimeout(function() {
                        trcpp_load_results($batch_id);
                    }, 500);
                } catch (e) {}

            }

            if (!$('tr.parent.active').length && $('tr.parent').length == 1) {
                $('tr.parent').click();
            }

        })
    </script>

    <?php

    //update batchid
    //$wpdb->query("update ci_batches_req set batch_id = DATE_FORMAT(SUBDATE(batch_created,INTERVAL 1 DAY),'%Y%m%d')  where batch_id = 0");
    ?>
</div>