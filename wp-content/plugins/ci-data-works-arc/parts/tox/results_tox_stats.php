<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();
$batch_statuses = tr_pdd_get_batch_statuses(false, false, false, 'tox');


wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$is_etg = isset($_GET['etg']);
$search = isset($_GET['s']) ? $_GET['s'] : '';
$joins = '';
$where = " and ba.batch_id !='' and ba.batch_id!='0' ";
$select_fields = '';

$conditions     = trpdd_get_batch_conditions(0);
$where .= $conditions['where'];


//where missing
$vdxs = array();
if ($is_etg) {
    $select_fields .= ", re.id ";
    $tests_alcohols = $wpdb->get_col("select te_name from ci_test_panels t JOIN ci_test_groups g ON g.tg_ID = t.te_group_id and g.tg_name = 'Alcohol'");
    $tests_alcohols_str = implode("','", $tests_alcohols);

    $joins .= " JOIN ci_reports re ON re.patient_id = ba.assen ";
    $where .= " and re.id in (select report_id from ci_results 
	                                    where drug in ('{$tests_alcohols_str}')
	                                    and `pos-neg` = 'POS' )";
} else {
    $where .= " and ba.has_vdx = 2 ";
    $vdxs  = tr_pdd_get_result_validity(2);
}

if (!empty($search)) {
    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(
            " and (ba.patient_name like %s 
                    or ba.requesting_practice like %s 
                    or ba.requesting_physician like %s )",
            $explodeString,
            $search . '%',
            $search . '%'
        );
    } else {
        $where .= $wpdb->prepare(
            " and (ba.assen = %s 
                    or ba.patient_name like %s 
                    or ba.requesting_practice like %s 
                    or ba.requesting_physician like %s )",
            $search,
            $search . '%',
            $search . '%',
            $search . '%'
        );
    }
}

$sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches ba {$joins} where 1 {$where}";

if (class_exists('Tr_Cache_Query')) {
    $cache_time = 86400;
    $cache = new Tr_Cache_Query();
    $total = $cache->get_var($sql_total, 0, 0, $cache_time);
} else {
    $total = $wpdb->get_var($sql_total);
}

$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$pagination->setTotal($total);
$offset = $pagination->offset();

$order = @$_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
$order_by = !empty($_REQUEST['orderby']) ? 'ba.' . $_REQUEST['orderby'] : 'ba.batch_id';


$sql = "select distinct(ba.batch_id) as batch_id
        from ci_batches ba
        {$joins}
        where 1 {$where}
        order by ba.batch_id desc
        limit {$offset},{$row_per_page}";

$batch_ids = $wpdb->get_col($sql);
$batch_ids = implode("','", $batch_ids);
$where .= " and ba.batch_id in ('$batch_ids') ";


if (!$is_etg) {
    foreach ($vdxs as $arr) {
        $select_fields .= ", (
					select tp.conc from ci_result_test_speciments tp 
					where tp.mid=ba.mid and tp.testid = {$arr['u_ID']} 
					) as v_{$arr['u_ID']} ";
    }
}



$sql2 = "select ba.mid,
				ba.batch_id,
				ba.assen,
				ba.requesting_practice,
				ba.requesting_physician
				{$select_fields}
        from ci_batches ba 
        {$joins}
            where 1 {$where}
            order by ba.batch_id desc,{$order_by} {$order}";
$results = $wpdb->get_results($sql2);

$batches = array();
foreach ($results as $row) {
    $batches[$row->batch_id][] = $row;
}

if (isset($_GET['debug'])) {
    var_dump($sql);
    echo '<br><br><br>';
    var_dump($sql2);
}
?>

<div class="wrap2">
    <h2><?php echo $is_etg ? 'ETG Stats' : 'VDX Stats' ?></h2>

    <div class="tablenav">
        <div class="left">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
                <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
                <label>Accession/Patient/Clinic/Doctor:</label>
                <input type="search" name="s" value="<?php echo $_GET['s'] ?>" />
                <input type="submit" class="button bsty6" value="Search" />


                <label style="margin-left:15px"><input type="checkbox" name="etg" value="1" <?php checked($is_etg) ?> /> ETG/ETS Stats</label>
            </form>

        </div>
        <?php if ($pagination) echo $pagination->pagination() ?>
    </div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th style="width:60px">Batch #</th>
                    <?php tr_tbl_sort('assen', 'Accession', '', 'width:120px') ?>
                    <?php tr_tbl_sort('requesting_practice', 'Clinic', '', 'width:19%') ?>
                    <?php tr_tbl_sort('requesting_physician', 'Doctor', '', 'width:12%') ?>
                    <?php
                    if ($is_etg) {
                        echo '<th style="width:90px">ETG</th>';
                        echo '<th style="width:90px">ETS</th>';
                        echo '<th></th>';
                    } else {
                        foreach ($vdxs as $arr) {
                            echo '<th style="width:55px">' . $arr['u_name'] . '</th>';
                        }
                        echo '<th>SGI/UCREA</th>';
                        echo '<th>Rules</th>';
                    }
                    ?>

                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch_id => $results) { ?>
                    <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                        <td><i></i></td>
                        <td><?php echo $batch_id ?></td>
                        <td colspan="10" style="text-align: right">
                            <?php echo count($results) ?>
                        </td>
                    </tr>
                    <tr class="child p_batch_<?php echo $batch_id ?>">
                        <td colspan="12" class="nopadding">
                            <table class="report_tbl">
                                <?php foreach ($results as $i => $row) {
                                ?>
                                    <tr class="childp">
                                        <td style="width:30px;"></td>
                                        <td style="width:60px"></td>
                                        <td style="width:110px"><?php echo $row->assen ?></td>
                                        <td style="width:19%"><?php echo $row->requesting_practice ?></td>
                                        <td style="width:12%"><?php echo $row->requesting_physician ?></td>
                                        <?php
                                        if ($is_etg) {
                                            $etg = $wpdb->get_var("select conc from ci_results where report_id = {$row->id} and drug='ETG'");
                                            $ets = $wpdb->get_var("select conc from ci_results where report_id = {$row->id} and drug='ETS'");
                                            echo '<td style="width:90px">' . $etg . '</td>';
                                            echo '<td style="width:90px">' . $ets . '</td>';
                                            echo '<td></td>';
                                        } else {
                                            $return    = array();
                                            $ds        = array();
                                            $has_acong = false;
                                            foreach ($vdxs as $arr) {
                                                $arr['conc'] = $row->{'v_' . $arr['u_ID']};
                                                echo '<td style="width:55px">' . $arr['conc'] . '</td>';

                                                $arr['u_name']        = strtoupper($arr['u_name']);
                                                if (strpos($arr['conc'], '@') !== false) {
                                                    if (! $has_acong  && $arr['u_name'] != 'OXVDX') {
                                                        $return['has_acong-5'] = true;
                                                    }

                                                    $has_acong        = true;
                                                    $arr['has_acong'] = true;
                                                    $return['has_acong'] = true;
                                                }

                                                $arr['conc']          = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

                                                $arr['u_low']         = trim($arr['u_low']);
                                                $arr['u_high']        = trim($arr['u_high']);
                                                $ds[$arr['u_name']] = $arr;
                                            }

                                            $return = trpdd_cal_vdx($return, $ds);
                                            $vdx_pos = ($return['outcome'] == 'ABNORMAL') ? 2 : 1;
                                            $return['has_vdx'] = $vdx_pos;

                                        ?>
                                            <td><?php echo ($return['cre_sg'] > 5) ? number_format($return['cre_sg'], 3) : '' ?></td>
                                            <td class="rules">
                                                <?php

                                                if ($vdx_pos === 1) {
                                                    $wpdb->update('ci_batches', array('has_vdx' => $vdx_pos), array('mid' => $row->mid));
                                                    if (is_object($cache) && method_exists($cache, 'clear_var')) {
                                                        $cache->clear_var($sql_total);
                                                    }
                                                }
                                                foreach ($return['conditions'] as $t) {
                                                    echo '<li>' . $t . '</li>';
                                                }
                                                ?>
                                            </td>
                                        <?php
                                        } ?>
                                    </tr>
                                <?php } ?>
                            </table>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    td.rules li {
        font-size: 11px;
        margin-left: 0;
        padding-left: 0;
    }
</style>
<script>
    jQuery(function($) {
        $('tr.parent').click(function() {
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_icom', $(this).data('p'));
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('[name="etg"]').click(function() {
            var $url = '<?php echo add_query_arg(array('etg' => false)) ?>';
            location = $url + ($(this).is(':checked') ? '&etg=1' : '');
        })
    })
</script>