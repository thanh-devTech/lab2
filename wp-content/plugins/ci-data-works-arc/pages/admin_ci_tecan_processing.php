<?php
global $wpdb,$current_role,$blog_id;
$user_id = get_current_user_id();

if(isset($_REQUEST['trajax'])){
    if($_REQUEST['trajax']=='consumables')
    {
        include(trpdd_get_file_path('plate','consumables'));
        exit;
    }else if($_REQUEST['trajax']=='issue_exclude'){
        include(trpdd_get_file_path('plate','issue_exclude'));
        exit;
    }
}

include_once(TRCPDD_PATH.'inc/functions_rack_plate.php');

$colors = array(
	1 => '#F5C694',
	2 => '#98BCE3',
	3 => '#B1D09F',
	4 => '#CB9EB4',
	5 => '#67e050',
	6 => '#4BFBFE',
	7 => '#cb76f5',
	8 => '#f5db76',
	9 => '#f5ac9f',
	10 => '#8a77ea',
	11 => '#ea77e5',
	12 => '#d2ea77',
	13 => '#d0b190',
	14 => '#5ba2ef',
	15 => '#90d468',
	16 => '#19989a',
	17 => '#8d5da5',
	18 => '#e8bd12',
	19 => '#e43a1c',
	20 => '#d1cde2',
	21 => '#d8ede2',
	22 => '#d1c4f2',
	23 => '#2fcde2',
	24 => '#d1c122',
	25 => '#d1cd1f',
	26 => '#dfede2',
	27 => '#d155e2',
	28 => '#21c2e2',
	29 => '#d9c3e2',
	30 => '#d17dea',
	31 => '#dfc9e2',
	32 => '#d1adea',
	33 => '#d1c1ea',
	34 => '#d11d12',
	35 => '#dfcae2',
	36 => '#a1ad12',
	37 => '#d8cde2',
	38 => '#dacde2',
	39 => '#dfcde2',
	40 => '#d4cde2',
);

$ntc2_texts = array(
	'Covid-19 Positive IC',
	'Covid-19 Negative IC',
	'Patient Positive',
	'Patient Negative',
	'Plasmid Positive',
	'NTC'
);

$tabs = array(
    'plates' => 'Plates',
    'plate_view' => 'Plate View',
    //'processing' => 'Tox Processing Queue',
    //'pcr_processing' => 'PCR Processing Queue'
);

if(tr_get_option('ci_pdf_data','rack_plate_logtab')){
	$tabs['plate_log'] = 'Plate Log';
}

$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab])){
    $tab = key($tabs);
}
if(!isset($_REQUEST['trajax'])) {
	?>
    <h2 class="nav-tab-wrapper">
		<?php

		foreach ( $tabs as $k => $txt ) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link( array( 'tab'   => $k,
			                                        'act'   => '',
			                                        'id'    => '',
			                                        'date'  => '',
			                                        'bid'   => '',
			                                        'assen' => '',
			                                        'inst'  => ''
			), false );
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
    </h2>
	<?php
}

if($tab=='plates')
{
    include(trpdd_get_file_path('plate','plates'));
}
else if($tab=='plate_view')
{
    include(trpdd_get_file_path('plate','plate_view'));
}
else if($tab=='plate_log'){
	include(trpdd_get_file_path('plate','plate_log'));
}
else {

    include_once(TRCPDD_PATH.'pages/formdata.php');

    if ($_POST['act'] == 'change_status' && !empty($_POST['barcode'])) {
        $batch_id = trpdd_get_tecan_batch_id_by_barcode($_POST['barcode']);
        $ms = $_POST['ms'];
        $bats = $wpdb->get_results("select ba.mid,ba.batch_status from ci_tecan te 
                    JOIN ci_batches ba ON ba.assen = te.te_assen
                    WHERE ba.batch_id = '{$batch_id}'  ");
        if ($bats) {
            $notes = $wpdb->get_var("select bn_bid from ci_batches_notes WHERE bn_bid='{$batch_id}'");
            if ($notes) {
                $wpdb->update('ci_batches_notes', array(
                    'bn_user' => $user_id,
                    'bn_status_changed' => Tr_Base_Class_V4::date('Y-m-d H:i:s')
                ), array('bn_bid' => $batch_id));
            } else {
                $wpdb->insert('ci_batches_notes', array(
                        'bn_bid' => $batch_id,
                        'bn_status_changed' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                        'bn_created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                        'bn_user' => $user_id)
                );
            }


            $update = array('ms' => $ms, 'batch_status' => 'processing');
            if ($bats[0]->batch_status == 'processing') {
                $update['batch_status'] = 'need_quantification';
                Tr_Session_Class::add("Changed Barcode: " . $_POST['barcode'] . ' to Need Quantification');
            } else {
                Tr_Session_Class::add("Changed Barcode: " . $_POST['barcode'] . ' to Running');
            }


            foreach ($bats as $ba) {
                $wpdb->update('ci_batches', $update, array('mid' => $ba->mid));
            }

        } else {
            Tr_Session_Class::add_error('Not found Barcode: ' . $_POST['barcode']);
        }
    }


    $where = '';
    $orderby = ' order by ba.batch_id desc';
    $order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';

    $joins = '';
//page
    $row_per_page = 10;
    $pagination = new Tr_Pagination_class($row_per_page);
    $offset = $pagination->offset();


    if ($current_role == 'office_manager' || $current_role == 'clinic') {

        $cid = get_user_meta($user_id, '_clinic_id', true);
        $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
        $clinics = implode(',', $clinics);
        $where .= " and ba.cli_ID in ({$clinics}) ";

    }


    $joins .= " JOIN ci_batches ba ON ba.assen = te.te_assen
    JOIN ci_labs lab  ON lab.lab_ID = ba.lab_id
     LEFT JOIN ci_reports re ON  ba.assen = re.patient_id ";
    $where .= " and ba.batch_status in( 'processing' , 'need_quantification','quan_stared','quantified') ";

    $sql_total = "select count(distinct(ba.batch_id)) as c from ci_tecan te
    {$joins}
        where 1 {$where}";

    $sql_limit = "select distinct(ba.batch_id) from ci_tecan te
    {$joins}
        where 1 {$where}
        order by ba.batch_id desc limit {$offset},{$row_per_page}";


    $time_start = microtime(true);
    $pagination->setTotal($wpdb->get_var($sql_total));
    $q_total = microtime(true) - $time_start;

    $time_start = microtime(true);
    $batch_ids = $wpdb->get_col($sql_limit);
    $q_limit = microtime(true) - $time_start;

    $batch_ids = implode("','", $batch_ids);
    $where .= " and ba.batch_id in('{$batch_ids}') ";


    $sql = "select ba.batch_id,
        ba.doc,ba.ms,
        ba.batch_status,
        te.te_letter,
        te.te_number,
        te.te_created,
        lab.lab_ID,
        lab.lab_dba_init,
        bn.bn_user,
        bn.bn_status_changed,
        re.id AS Resulted
    from ci_tecan te
    {$joins}
    LEFT  JOIN  ci_batches_notes bn on bn.bn_bid=ba.batch_id
    where 1 {$where} group by ba.mid {$orderby}";


    $time_start = microtime(true);
    $tecans = $wpdb->get_results($sql);

    $count_rows = count($tecans);


    if (isset($_REQUEST['debug'])) {
        echo microtime(true) - $time_start, '<br>';
        echo 'qtotal: ' . $q_total, '<br>';
        echo '$sql_limit: ' . $sql_limit, "<br><br>";
        echo 'qlimit: ' . $q_limit, '<br><br>';
        echo $sql, '<br><br>';
    }


    ?>
    <h2>Processing Queue</h2>
    <?php Tr_Session_Class::show() ?>
    <form method="get" style="float:left;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>"/>
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>"/>
        <label>Patient</label>
        <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>"/>
        <input type="submit" class="button bsty6" value="Search"/>


    </form>
    <form method="post" style="float: right;margin-right:20px;">
        Barcode: <input type="text" name="barcode" autofocus/>
        <select name="ms" class="njs_select2">
            <?php
            $ms_list = array('MS1', 'MS2', 'MS3', 'MS4', 'MS15');
            foreach ($ms_list as $ms) {
                $selected = $ms == $_POST['ms'] ? 'selected' : '';
                echo '<option value="' . $ms . '" ' . $selected . '>' . $ms . '</option>';
            }
            ?>
        </select>
        <input type="hidden" name="act" value="change_status"/>
        <input type="submit" value="Submit" class="button"/>
    </form>

    <div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl"
           data-count="<?php echo @$count_rows ?>">
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
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $batches = array();
        if (count($tecans) > 0) {
            foreach ($tecans as $i => $row) {
                if (!is_array($batches[$row->batch_id])) {
                    $batches[$row->batch_id] = array();
                }
                //$batches[$row->batch_id]['date'] = $row->collection_date;
                $timechanged = strtotime($row->bn_status_changed);
                if ($timechanged <= 0) {
                    $timechanged = strtotime($row->te_created);
                }
                $batches[$row->batch_id]['date'] = date('m/d/Y H:i', $timechanged);
                $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
                $batches[$row->batch_id]['ms'] = $row->ms;
                $batches[$row->batch_id]['bn_user'] = $row->bn_user;
                $batches[$row->batch_id]['batch_status'] = $row->batch_status;
                $batches[$row->batch_id]['Resulted'] = $row->Resulted;
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
                <tr class="parent" data-p="batch_<?php echo $batch_id ?>" data-id="<?php echo $batch_id ?>">
                    <td><i></i></td>
                    <td title="<?php echo $batch_id ?>"><?php echo empty($cnum) ? $batch_id : $cnum ?></td>
                    <td><?php echo $d['ms'] ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td colspan="2" style="text-align: right;"><?php echo $d['date'] ?></td>
                    <td></td>
                    <?php if ($d['Resulted']): ?>
                        <td>Resulted</td>
                    <?php else: ?>
                        <td class="group_batch_status" data-status="<?php echo $d['batch_status'] ?>"></td>
                    <?php endif; ?>
                    <td>
                        <a title="Notes" class="thickbox dashicons dashicons-welcome-write-blog"
                           href="?tr_action=ci_batch_notes&bid=<?php echo $batch_id ?>&<?php if ($d['Resulted']) echo 'noedit' ?>"></a>
                        <?php
                        if ($d['bn_user'] != '') {
                            $data_user = get_userdata($d['bn_user']);
                            ?>
                            <div data-show-batch="<?php echo $data_user->display_name . '(' . $data_user->user_email . ')'; ?>"
                                 class="dashicons dashicons-admin-users"></div>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <tr class="child p_batch_<?php echo $batch_id ?>">
                    <td colspan="11" class="nopadding">
                        <table class="report_tbl">
                            <?php
                            foreach ($d['labs'] as $lab_id => $lab_name) {
                                ?>
                                <tr class="parent_c" data-p="lab_<?php echo $lab_id . $batch_id ?>"
                                    data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">
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
                                <tr class="child_c p_lab_<?php echo $lab_id . $batch_id ?>"
                                    data-batch_id="<?php echo $batch_id ?>" data-lab_id="<?php echo $lab_id ?>">
                                    <td colspan="11" class="nopadding"></td>
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
        jQuery(function ($) {
            $(document).tooltip({
                items: "[data-show-batch]",
                content: function () {
                    var element = $(this);
                    if (element.is("[data-show-batch]")) {
                        var text = element.data('show-batch');
                        return text;
                    }
                }
            });


            var $statuses = {
                'processing': 'Running',
                'need_quantification': 'Needs Quantitated',
                'need_review': 'Needs Review',
                'need_result': 'Needs Resulted'
            };

            function trpdd_setup_status() {
                $('.group_batch_status').each(function () {
                    var $html = '<select>';
                    for (k in $statuses) {
                        $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
                    }
                    $html += '</select>';
                    $(this).html($html);

                });
                $('.group_batch_status select').change(function () {
                    var $this = $(this);
                    var $pr = $(this).parent();
                    $pr.find('.sloading').remove();
                    $pr.append('<div class="sloading"></div>');
                    var $id = $pr.parents('tr').data('id');
                    var $vl = $(this).val();
                    $.ajax({
                        type: 'post',
                        dataType: 'json',
                        data: {tr_action: 'ci_change_group_batch_status', batch_id: $id, status: $vl},
                        success: function (rs) {
                            $pr.find('.sloading').remove();
                            if (rs.status == 'ok') {
                            } else {
                                $this.change();
                            }
                        },
                        error: function () {
                            $this.change();
                        }
                    })
                });
            }

            trpdd_setup_status();


            function trcpp_load_tecans($batch_id, $lab_id) {
                var $obj = $('tr.p_lab_' + $lab_id + $batch_id + ' td');
                if ($obj.html() != '') {
                    return;
                } else {
                    $obj.html('<div class="loading center"></div>');
                }
                $.ajax({
                    data: {tr_action: 'ci_load_tecan_processing', batch_id: $batch_id, lab_id: $lab_id},
                    type: 'post',
                    success: function (rs) {
                        var $html = $(rs);
                        $obj.html($html);
                    }
                })
            }


            $('tr.parent').click(function (e) {
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
            $('tr.parent_c').click(function (e) {
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
            $('.drugs').click(function () {
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
                    $batch_id = $obj.data('batch_id');
                    $lab_id = $obj.data('lab_id');
                    setTimeout(function () {
                        trcpp_load_tecans($batch_id, $lab_id);
                    }, 300);
                } catch (e) {
                }

            }


        })


    </script>
    <?php
}