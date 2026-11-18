<?php
global $wpdb, $current_role;

$today_time         = strtotime(Tr_Base_Class_V4::date());
$current_month      = date('Y-m', $today_time);
$current_month_end  = date('Y-m-t', $today_time);

$user_id            = get_current_user_id();
$is_group_manager   = false;
$ref_labs           = array();


if (tr_get_option('ci_pdf_data', 'total_turnaroundave')) {
    $ref_labs = $wpdb->get_results("select lab_dba_init, lab_ID from ci_labs where lab_is_ref = 1");
}

if (empty($current_clinic)) {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
}
$show_export_btn = tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'lab', 'lab_tech', 'labb', 'lab_manager'));

switch ($current_role) {
    case 'lab_rep':
    case 'lab_rep_w2':
        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli where cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");
        break;
    case 'office_manager':
    case 'multi_site_manager':
    case 'clinic':
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
        $cids = implode(',', $cids);
        if (empty($cids)) {
            $cids = '-1';
        }
        $where_clinic = " cli.cID in ({$cids}) ";


        $clinic_group_id = $wpdb->get_var("select meta_value from {$wpdb->usermeta} WHERE meta_key='_clinic_group' AND user_id = {$user_id}");
        if ($clinic_group_id > 0) {
            $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_ID = {$clinic_group_id} and clig_inactive=0");
            $where_clinic = " or cli_group_ID = {$clinic_group_id} ";
            $is_group_manager = true;
        }
        $sql_clinic = "select * from ci_clinic cli WHERE cli_status = 1 and ( {$where_clinic} ) order by cli_clinic asc";

        $clinics = $wpdb->get_results($sql_clinic);

        if (count($clinics) > 0) {
            $is_group_manager = true;
        }
        break;

    case 'lab':

        $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_inactive=0");
        $clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic asc");
        break;

    case 'distributor':
        $sql = "select * from ci_clinician_groups cg
                                JOIN `ci_distributors` dis
                                JOIN ci_lab_reps lr ON lr.rep_ID = cg.clig_rep_ID
                                left join {$wpdb->usermeta} um ON um.meta_key='_rep_id' and um.meta_value = lr.rep_ID

                                JOIN ci_marketing_groups mg ON mg.gr_distributor_ID = dis.dis_ID
                                            and (lr.rep_marketing_group_ID = mg.gr_ID or mg.gr_admins = um.user_id)
                                 WHERE dis.`dis_user_ID`=$user_id and cg.clig_inactive=0";
        if (isset($_REQUEST['debug3'])) {
            echo $sql;
        }
        $clinic_groups = $wpdb->get_results($sql);

        $sql = "select * from ci_clinic cli
                                JOIN `ci_distributors` dis
                                JOIN ci_lab_reps lr ON  lr.rep_ID = cli.`cli_rep-ID`
                                JOIN ci_marketing_groups mg ON mg.gr_distributor_ID = dis.dis_ID
                                                            and lr.rep_marketing_group_ID = mg.gr_ID
                                 WHERE dis.`dis_user_ID`=$user_id and cli.cli_status = 1 ";
        if (isset($_REQUEST['debug4'])) {
            echo $sql;
        }
        $clinics = $wpdb->get_results($sql);
        break;

    default:
        $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_inactive=0");
        $clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic asc");
        break;
}



wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'admin/css/jquery-ui.css', array(), '1.1.9');

$today          = Tr_Base_Class_V4::date('Y-m-d');
$past_30days    = date('Y-m-d', strtotime('today - 30 days'));
$panels = $wpdb->get_results("select tg_ID, tg_name, tg_type 
from ci_test_groups 
where tg_parent=0 
and tg_show_req =1
order by tg_position asc");
?>
<div class="turnaround" style="padding:5px;">
    <input type="text" id="turnaround_from" value="<?php echo $past_30days ?>" style="width:108px;" />
    To: <input type="text" id="turnaround_to" value="<?php echo $today ?>" style="width:108px" />
    <select id="turnaround_type" class="njs_select2">
        <option value="doc">DOC</option>
        <option value="rec">Received</option>
    </select>
    <select id="turnaround_btype">
        <?php
        $move_opt_bottom = '';
        foreach (tr_pdd_get_batch_types() as $btype) {
            $batch_tbl  = tr_pdd_get_table_by_btype($btype);
            $count      = $wpdb->get_var("select count(*) from {$batch_tbl}");

            $opttxt =  '<option value="' . $btype . '">' . tr_pdd_get_typename($btype) . '</option>';
            if ($count > 100) {
                echo $opttxt;
            } else {
                $move_opt_bottom .= $opttxt;
            }
        }
        echo $move_opt_bottom;
        ?>
    </select>
    <?php


    echo '<select id="panel_id">';
    echo '<option value="">All Panels</option>';
    echo '</select>';

    ?>
    <?php if (count($clinics) > 0) { ?>
        <select id="turnaround_clinic">
            <?php if (count($clinics) > 1) : ?>
                <option value="">All Clinics</option>
            <?php endif; ?>
            <?php
            foreach ($clinics as $c) {
                $selected = $current_clinic == $c->cID ? 'selected' : '';
                echo '<option value="' . $c->cID . '" ' . $selected . '>' . $c->cli_clinic . '</option>';
            }
            ?>
        </select>
    <?php }

    if (count($ref_labs) > 0) { ?>
        <select id="turnaround_reflab">
            <option value="all">Overall</option>
            <option value="main">Main lab</option>
            <?php
            foreach ($ref_labs as $c) {
                echo '<option value="' . $c->lab_ID . '">' . $c->lab_dba_init . '</option>';
            }
            ?>
        </select>
    <?php }




    ?>
    <a class="run_turnaround_report button">Report</a>
    <?php if ($show_export_btn) { ?>
        <a class="export_turnaround_report button">Export</a>
    <?php } ?>
    <div id="turaround_report" style="min-height:100px"></div>
</div>

<style>
    #dashboard_average_turnaround_time {
        width: 100% !important;
        clear: both;
        float: none !important;
    }

    .turnaroundbox {
        background: #e1e0e0;
        margin-top: 5px;
        padding: 4px 10px;
    }

    .boxitem {
        margin-right: 10px;
        display: inline-block;
    }
</style>
<script>
    var $panels = <?php echo json_encode($panels); ?>;
    jQuery(function($) {


        function tr_load_turnaround() {
            var $from = $('#turnaround_from').val();
            var $to = $('#turnaround_to').val();
            var $type = $('#turnaround_type').val();
            var $btype = $('#turnaround_btype').val();
            var $cid = $('#turnaround_clinic').val();
            var $lid = $('#turnaround_reflab').val();
            var $panel_id = $('#panel_id').val();
            if ($from == '' || $to == '') {
                return false;
            }
            $('#turaround_report').html('<div class="loading">Loading...</div>');
            $.ajax({
                data: {
                    tr_action: 'ci_get_report_turnaround',
                    from: $from,
                    to: $to,
                    type: $type,
                    btype: $btype,
                    cid: $cid,
                    lid: $lid,
                    panel_id: $panel_id
                },
                success: function(rs) {
                    $('#turaround_report').html(rs);
                }
            })
        }

        $('body').on('change', 'select#turnaround_btype', function() {
            var $vl = $(this).val();
            $('#panel_id').val('');
            if ($vl != 'tox') {
                $('select#panel_id').show();
                $html = '<option value="">All Panels</option>';
                for (i in $panels) {
                    if ($panels[i].tg_type == $vl) {
                        $html += '<option value="' + $panels[i].tg_ID + '">' + $panels[i].tg_name + '</option>';
                    }

                }

                $('#panel_id').html($html);
            } else {
                $('select#panel_id').hide();
            }
        }).on('click', '.run_turnaround_report', function() {
            tr_load_turnaround();
        }).on('click', '.export_turnaround_report', function() {
            var $url = '?tr_action=ci_get_report_turnaround&export=true';
            $url += '&from=' + $('#turnaround_from').val();
            $url += '&to=' + $('#turnaround_to').val();
            $url += '&type=' + $('#turnaround_type').val();
            $url += '&btype=' + $('#turnaround_btype').val();
            $url += '&cid=' + $('#turnaround_clinic').val();
            $url += '&lid=' + $('#turnaround_reflab').val();
            $url += '&panel_id=' + $('#panel_id').val();

            location.href = $url;
            return
        })

        $("#turnaround_from").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#turnaround_to").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#turnaround_to").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#turnaround_from").datepicker("option", "maxDate", selectedDate);
            }
        });
        $('select#turnaround_btype').change();
        setTimeout(function() {
            tr_load_turnaround();
        }, 1500);
    })
</script>