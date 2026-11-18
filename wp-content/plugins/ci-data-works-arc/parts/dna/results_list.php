<?php

$orderby = ' order by ba.batch_id desc ';
$where  = " where ba.is_dna=1 and result_pdf !='' "; //dna
$where .= " and ba.batch_status != 'archived' ";
$is_test_results = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) ) ";
}


if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'clinic'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid     = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where .= " and ba.cli_ID in ( {$cli_ids} )";
        }
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    if (!tr_get_option('ci_pdf_data', 'doctor_see_all')) {
        $where .= " and ba.doc_ID = {$doc_id} ";
    }
} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
    $show_doctor = true;
    $rep_id = get_user_meta($user_id, '_rep_id', true);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` = {$rep_id}");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in  ({$clinics}) ";
} else if ($current_role == 'distributor') {
    $dis_ID = $wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
    $reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ");


    $reps = implode(',', $reps);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
} else if ($current_role == 'collector') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }

    $col_id = get_user_meta($user_id, '_collector_id', true);
    if (!tr_get_option('ci_pdf_data', 'collector_see_all')) {
        $where .= " and ba.col_ID = {$col_id} ";
    }
    $check_author = false;
}



if (!empty($_REQUEST['s'])) {
    $search = trim($_REQUEST['s']);
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


$sql_total = "select count(distinct(ba.batch_id)) from ci_batches_dna ba  {$where}";
$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var($sql_total));

$sql_limit = "select distinct(ba.batch_id) from ci_batches_dna ba
        {$where}
        {$orderby}  limit {$offset},{$row_per_page}";


$batch_ids = $wpdb->get_col($sql_limit);
$batch_ids = implode(',', $batch_ids);
if (empty($batch_ids)) {
    $batch_ids = '-1';
}
$where .= " and ba.batch_id in({$batch_ids}) ";

$results = $wpdb->get_results("select 
                ba.*,
                lab.*,
                count(*) as count_result,
                sum(if(ba.box_uploaded>0,1,0)) as count_uploaded
                from ci_batches_dna ba 
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                {$where}
                group by ba.batch_id,lab.lab_dba_init
                {$orderby}");
?>
<div class="wrap">
    <h2>PGx Results</h2>
    <?php Tr_Session_Class::show() ?>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Patient/Clinic/Doctor:</label>
        <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
        <input type="submit" class="button bsty6" value="Search" />

    </form>
    <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
            <thead>
                <tr>
                    <th style="width:3%">Pos</th>
                    <th style="width: 5%">Batch</th>
                    <th style="width: 6%;">Accession</th>
                    <th style="width: 12%;">Patient Name</th>
                    <th style="width: 80px;">Birthday</th>
                    <th style="width: 12%;">Doctor</th>
                    <th style="width: 18%;">Clinic</th>
                    <th style="width: 11%;">Insurance</th>
                    <th style="width: 80px;">Doc</th>
                    <?php if ($is_test_results) { ?>
                        <th style="width:10%">Batch Status</th>
                    <?php } ?>
                    <th style="width:50px">Req</th>
                    <th class="aright">Result</th>
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
                        //$batches[$row->batch_id]['date'] = $row->collection_date;
                        $batches[$row->batch_id]['date'] = date('Y-m-d', strtotime($row->batch_created));
                        $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->count_result;
                        $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
                    }
                ?>

                    <?php
                    foreach ($batches as $batch_id => $d) {
                    ?>
                        <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                            <td><i></i></td>
                            <td colspan="7"><?php echo $batch_id ?></td>
                            <td><?php echo $d['date'] ?></td>
                            <td align="right" colspan="2">
                                <?php /*
                        <a class="fax_pdf" data-batch_id="<?php echo $batch_id?>">Fax PDF</a> -
                        <a class="uploadpdf" data-batch_id="<?php echo $batch_id?>">Upload PDF</a>
                            */ ?>
                                <?php if ($d['uploaded']): ?>
                                    <span class="checkmark checked"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="child p_batch_<?php echo $batch_id ?>">
                            <td colspan="<?php echo $is_test_results ? 12 : 11 ?>" class="nopadding">
                                <table class="report_tbl">
                                    <?php

                                    foreach ($d['labs'] as $lab_id => $lab_name) {
                                    ?>
                                        <tr class="parent_c" data-p="lab_<?php echo $lab_id . $batch_id ?>">
                                            <td style="width: 3%"><i></i></td>
                                            <td colspan="4" style="width:12%"><?php echo $lab_name ?></td>
                                            <td colspan="7" align="right">
                                                <a class="btn_sell_all" sel="true">Select all</a> -
                                                <a class="fax_pdf" data-batch_id="<?php echo $batch_id ?>"
                                                    data-lab_id="<?php echo $lab_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>">Fax PDF</a> -
                                                <a class="uploadpdf" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">Upload PDF</a>
                                            </td>
                                        </tr>
                                        <tr class="child_c p_lab_<?php echo $lab_id . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">
                                            <td colspan="<?php echo $is_test_results ? 12 : 11 ?>" class="nopadding"></td>
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
                        <td colspan="<?php echo $is_test_results ? 12 : 11 ?>">No Results Found.</td>
                    </tr>
                <?php
                }



                ?>
            </tbody>
        </table>
    </div>

</div>
<script>
    jQuery(function($) {
        function trcpp_load_results($batch_id, $lab_id) {
            var $obj = $('tr.p_lab_' + $lab_id + $batch_id + ' td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_results_upload',
                    type: 'dna',
                    batch_id: $batch_id,
                    lab_id: $lab_id
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('<input type="checkbox" value="' + $(this).attr('title') + '"/>');
                    })
                },
                error: function(c) {
                    trcpp_load_results($batch_id, $lab_id);
                }
            })
        }

        $('a.btn_sell_all').click(function() {
            var sleat = $(this).attr("sel");
            $pid = $(this).parents('.parent_c').data('p');

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

        $('a.uploadpdf').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_box_upload&type=dna&batch_id=' + $bid + '&lab_id=' + $labid + '&kt=<?php echo time() ?>&ids=' + $ids;
            <?php if (isset($_GET['debug'])): ?>
                $url += '&rsdb=1';
            <?php endif; ?>
            tb_show('Upload to Box', $url + '&TB_iframe=true&width=400&height=300');
            return false;
        });
        $('a.fax_pdf').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_efax_upload&type=dna&batch_id=' + $bid + '&lab_id=' + $labid + '&kt=<?php echo time() ?>&ids=' + $ids;
            <?php if (isset($_GET['debug'])): ?>
                $url += '&rsdb=1';
            <?php endif; ?>
            tb_show('Fax PDF', $url + '&TB_iframe=true&width=600&height=500');
            return false;
        });

        $('tr.parent').click(function() {
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
        $('tr.parent_c').click(function() {
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
                trcpp_load_results($batch_id, $lab_id);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });

        var $old_active = getCookie('active_rs');
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
                setTimeout(function() {
                    trcpp_load_results($batch_id, $lab_id);
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