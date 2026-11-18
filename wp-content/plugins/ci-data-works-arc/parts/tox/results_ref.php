<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();
include_once(TRCPDD_PATH . 'pages/formdata.php');

$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

$tabs = array(
    'list' => 'Results',
    //'pending' => 'Pending List',
    'upload' => 'Upload Results'
);
if ($disable_upload_results) unset($tabs['upload']);
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

?>
<h2 class="nav-tab-wrapper">
    <?php

    foreach ($tabs as $k => $txt) {
        $class = $tab == $k ? 'nav-tab-active' : '';
        $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 'paged' => '', 's' => ''), false);
        echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
    }
    ?>
</h2>

<?php
if ($act == 'view' || $act == 'edit') {
    include(trpdd_get_file_path('tox', 'results_edit'));
    return;
}
if ($tab == 'pending') {
    include(trpdd_get_file_path('tox', 'results_pending'));
    return;
} else if ($tab == 'upload' && !$disable_upload_results) {
    include(trpdd_get_file_path('tox', 'results_upload'));
    return;
}

$orderby = ' order by ba.batch_id desc ';
$where = "where ba.result_pdf !=''  ";


$conditions     = trpdd_get_batch_conditions(0);
$where .= $conditions['where'];




if (!empty($_REQUEST['s'])) {

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
$sql_total = "select count(distinct(ba.batch_id)) from ci_batches ba  {$where}";
$row_per_page = 10;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var($sql_total));

$sql_limit = "select distinct(ba.batch_id) from ci_batches ba
        {$where}
        {$orderby}  limit {$offset},{$row_per_page}";


if (isset($_GET['debug'])) {
    var_dump($sql_limit);
}
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
                from ci_batches ba 
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
               {$where}
               group by ba.batch_id,lab.lab_dba_init
                {$orderby}");
?>
<div class="wrap">
    <h2>tox Results</h2>
    <?php Tr_Session_Class::show() ?>

    <div class="tablenav top">
        <form method="get" style="float:left;">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
            <label>Accession/Patient/Clinic/Doctor:</label>
            <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
            <input type="submit" class="button bsty6" value="Search" />

        </form>
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
            <thead class="sticky">
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
                    <th style="width:50px">Req</th>
                    <th class="aright">Result</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $batches = array();
                if (count($results) > 0) {
                    foreach ($results as $i => $row) {
                        if (empty($row->batch_id) && $row->batch_id != 0) {
                            //$row->batch_id = date('Ymd',strtotime($row->batch_created));
                        }

                        if (!is_array($batches[$row->batch_id])) {
                            $batches[$row->batch_id] = array();
                        }
                        //$batches[$row->batch_id]['date'] = $row->collection_date;
                        $batches[$row->batch_id]['date'] = date('Y-m-d', strtotime($row->doc));
                        $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->count_result;
                        $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
                    }
                ?>

                    <?php
                    foreach ($batches as $batch_id => $d) {
                    ?>
                        <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                            <td><i></i></td>
                            <td><?php echo $batch_id ?></td>
                            <td colspan="6"></td>
                            <td><?php echo $d['date'] ?></td>
                            <td colspan="2" style="text-align: right;">
                                <a class="fax_pdf" data-batch_id="<?php echo $batch_id ?>">Fax PDF</a>
                            </td>
                        </tr>
                        <tr class="child p_batch_<?php echo $batch_id ?>" data-batch_id="<?php echo $batch_id ?>">
                            <td colspan="11" class="nopadding"></td>
                        </tr>
                    <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="11">No Results Found.</td>
                    </tr>
                <?php
                }



                ?>
            </tbody>
        </table>
    </div>

    <div class="tablenav bottom"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

    <script>
        jQuery(function($) {
            function trcpp_load_results($batch_id) {
                var $obj = $('tr.p_batch_' + $batch_id + ' td');
                if ($obj.html() != '') {
                    return;
                } else {
                    $obj.html('<div class="loading center"></div>');
                }
                $.ajax({
                    data: {
                        tr_action: 'ci_load_results_upload',
                        type: 'tox',
                        batch_id: $batch_id
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
            });
            $('a.fax_pdf').click(function() {
                var $bid = $(this).data('batch_id');
                var $ids = '';
                var $url = '?tr_action=ci_upload_result_pdf_to_efax_upload&type=tox&batch_id=' + $bid + '&kt=<?php echo time() ?>';
                $('.p_batch_' + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })
                $url += '&ids=' + $ids;
                <?php if (isset($_GET['debug'])): ?>
                    $url += '&rsdb=1';
                <?php endif; ?>
                tb_show('Fax PDF', $url + '&TB_iframe=true&width=600&height=500');
                return false;
            });



            $('tr.parent').click(function() {
                $('tr.child').hide();
                var $obj = $('tr.p_' + $(this).data('p'));
                if ($(this).data('showed') != '1') {
                    $obj.show();
                    $('tr.parent').data('showed', '0');
                    $(this).data('showed', '1');
                    $(this).addClass('active');
                    setCookie('active_rs', $(this).data('p'));
                    $('body,html').scrollTop($obj.offset().top - 50);
                    var $batch_id = $obj.data('batch_id');
                    trcpp_load_results($batch_id);
                } else {
                    $obj.hide();
                    $(this).data('showed', '0');
                    $(this).removeClass('active');
                }
                return false;
            });

            $('.drugs').click(function() {
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
                    $('body,html').scrollTop($obj.offset().top - 150);
                    $batch_id = $obj.data('batch_id');
                    setTimeout(function() {
                        trcpp_load_results($batch_id);
                    }, 500);
                } catch (e) {}

            }

        })
    </script>


</div>