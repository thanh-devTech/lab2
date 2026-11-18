<?php
global $wpdb, $blog_id;

$btypes         = tr_pdd_get_batch_types();
$all_status     = tr_pdd_get_batch_statuses(false, false, false, 'all');
$inb_show_pcode = tr_get_option('ci_pdf_data', 'inb_show_pcode');
$show_pa_mrn     = tr_get_option('ci_pdf_data', 'show_pa_mrn_todayb');
$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
$current_time   = time() + $current_offset;
$last_time      = $current_time - 86400;

$current_date   = date('Y-m-d', $current_time);
$current_year   = date('Y', $last_time);
$yesterday      = date('Y-m-d', $last_time);
$datem_from     = date('Y-m', $last_time) . '-01';
$datem_to       = date('Y-m', $last_time) . '-31';
$datey_from     = $current_year . '-01-01';
$datey_to       = $current_year . '-12-31';

$selected_lab_id    = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$from_date          = isset($_REQUEST['from']) ? $_REQUEST['from'] : '';
$to_date            = isset($_REQUEST['to']) ? $_REQUEST['to'] : '';
$query_date_by      = isset($_REQUEST['qby']) ? $_REQUEST['qby'] : 'batch_id';


if (empty($from_date)) {
    $from_date = Tr_Base_Class_V4::date();
}
if (empty($to_date)) {
    $to_date = Tr_Base_Class_V4::date();
}


$where      = '';
$where_study      = '';
$whereign   = '';
$wherehos   = '';
$where_lab  = '';
$where_lab_l  = '';

if (!empty($selected_lab_id)) {
    list($selected_lab_id, $labby) = explode('_', $selected_lab_id);
    if ($labby == 'r') {
        $where_lab .= " and ba.ref_lab_ID={$selected_lab_id} ";
    } else if ($labby == 'm') {
        $where_lab .= " and ba.lab_id={$selected_lab_id} ";
    } else {
        $where_lab .= " and (ba.lab_id = {$selected_lab_id} or ba.ref_lab_ID={$selected_lab_id}) ";
    }
    $where_lab_l .= " and lab_ID={$selected_lab_id} ";
}


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_lab .= " and ba.lab_id in ( {$lab_str} ) ";
    $where_lab_l .= " and lab_ID in ( {$lab_str} ) ";
}


$where .= $where_lab . " and ba.batch_id > 0 and is_saved = 0 ";



$batch_from     = str_replace('-', '', $from_date);
$batch_to       = str_replace('-', '', $to_date);

if ($query_date_by == 'doc') {
    $where         .= " and ba.doc>= '{$from_date}' ";
    $where         .= " and ba.doc<= '{$to_date} 23:59:59' ";
} else if ($query_date_by == 'spec_received') {
    $where         .= " and ba.spec_received>= '{$from_date}' ";
    $where         .= " and ba.spec_received<= '{$to_date} 23:59:59' ";
} else {
    $where         .= " and ba.batch_id>= {$batch_from} ";
    $where         .= " and ba.batch_id<= {$batch_to} ";
}

$where_study .= " and batch_created >= '{$from_date}' ";
$where_study .= " and batch_created <= '{$to_date} 23:59:59' ";

$whereign .= " and created>='{$from_date}' ";
$whereign .= " and created<='{$to_date} 23:59:59' ";

$wherehos .= " and created>='{$from_date}' ";;
$wherehos .= " and created<='{$to_date} 23:59:59' ";

$order = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';

if (isset($_REQUEST['orderby'])) {
    if ($_REQUEST['orderby'] == 'spec_received') {
        $order_by = 'ba.spec_received ' . $order;
    } else if (@$_REQUEST['orderby'] == 'doc') {
        $order_by = 'ba.doc ' . $order;
    } else if (@$_REQUEST['orderby'] == 'cli') {
        $order_by = 'ba.requesting_practice ' . $order;
    }
}


$labs = $wpdb->get_results("select * from ci_labs  order by lab_ID asc");
?>
<br />
<form method="get" style="float:left">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label><input type="checkbox" name="summary" value="1" <?php checked(isset($_REQUEST['summary']) ? $_REQUEST['summary'] : '', '1') ?> /> Summary </label>
    <span id="summaryfromdate">
        From Date:
        <input type="text" class="hasdate" name="from" value="<?php echo @$from_date ?>" />
    </span>
    <label id="summarytodate">To Date:</label>
    <input type="text" class="hasdate today" name="to" value="<?php echo @$to_date ?>" />
    <label>Query By:</label>
    <select name="qby">
        <?php
        foreach (array('batch_id' => 'Batch #', 'doc' => 'DOC', 'spec_received' => 'Received Date') as $k => $txt) {
            $selected = $k == $query_date_by ? 'selected' : '';
            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
        }
        ?>
    </select>
    <?php
    if (isset($labs) && count($labs) > 0) {
        echo '<select name="lab_id">';
        echo '<option value="">All Labs</option>';
        foreach ($labs as $lab) {
            $labkey = $lab->lab_ID . '_' . ($lab->lab_is_ref ? 'r' : 'm');
            $selected = $selected_lab_id == $labkey || $selected_lab_id == $lab->lab_ID ? 'selected' : '';
            echo '<option value="' . $labkey . '" ' . $selected . '>' . $lab->lab_name . '</option>';
        }
        echo '</select>';
    }
    ?>
    <input type="submit" class="button bsty6" value="Search" />
</form>

<?php
if (isset($_REQUEST['summary']) && $_REQUEST['summary']) {

    $enabled_abxs = array();

    $main_groups = $wpdb->get_results("
    select tg_ID, tg_name, tg_other_options
    from ci_test_groups
    where tg_parent = 0
    and tg_type = 'pcr'
    and tg_show_req = 1
    and tg_other_options like '%new_abx\":\"1%'
    and tg_antibiotic = 0 
    order by tg_position asc
    ");

    foreach ($main_groups as $g) {
        $tabtext = 'UTI';
        $tabkey = 'uti';
        $other_options = json_decode($g->tg_other_options, true);

        $g->tg_name = str_replace('(new)', '', $g->tg_name);
        $g->tg_name = str_replace(' Panel', '', $g->tg_name);
        if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
            $tabkey = $other_options['abx_type'];
            if (in_array($tabkey, array('sm', 'sr', 'sp', 'sl', 'es'))) {
                $tabkey = 'sti';
            }
            $tabtext = strlen($g->tg_name) < 7 || strlen($tabkey) <= 2 ? $g->tg_name : strtoupper($tabkey);
            if ($tabkey == 'v') {
                $tabtext = 'Vaginitis';
            }
        } else if (stripos($g->tg_name, 'wound') !== false) {
            $tabkey = 'wound';
            $tabtext = 'Wound';
        } else if (stripos($g->tg_name, 'gi') !== false) {
            $tabkey = 'gi';
            $tabtext = 'GI';
        } else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
            $tabkey = 'r';
            $tabtext = 'RPP';
        }

        if (!isset($enabled_abxs[$tabkey])) {
            $enabled_abxs[$tabkey] = array(
                'title' => $tabtext,
                'groupids' => array()
            );
        }
        $enabled_abxs[$tabkey]['groupids'][] = $g->tg_ID;
    }

    //var_dump($enabled_abxs);

    echo '<div class="clear"></div>';

    $sql = "select 
            lab.lab_ID,
            lab.lab_dba as lab,
            lab.lab_dba_init,
            lab.lab_dna_fee,
            lab.lab_specimen_fee
            from ci_labs lab 
            where (lab_is_ref = 0 or lab_is_ref = 2)
            ";

    $sql .= $where_lab_l;


    $lab_rows = $wpdb->get_results($sql);
    if (count($lab_rows) == 0) {
        //echo '<div>Not found</div>';
    }

    $labs = array();
    $lab_refs = array();


    foreach ($lab_rows as $i => $b) {
        $rows = array();
        foreach ($btypes as $btype) {
            $table = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);
            $adding_query = '';
            $adding_where = '';
            $count_query = 'COUNT(DISTINCT ba.assen) as count';
            $count_result_query = "SUM(if(re.estatus='C' , 1, 0)) as count_result";
            if ($btype == 'tox') {
                $count_query = "SUM(IF(panel_tests_requested!='', 1, 0)) as count";
                $count_result_query = "SUM(if(panel_tests_requested!='' and (re.estatus='C' ), 1, 0)) as count_result";

                $adding_query .= " SUM(IF((select count(*) from ci_result_test_speciments ts where ts.mid = ba.mid and ba.panel_tests_requested='' and ts.checked = 1 limit 1)>0,1,0)) as screening, ";
                $adding_query .= " SUM(IF((select count(*) from ci_result_test_speciments ts where ts.mid = ba.mid and ba.panel_tests_requested='' and ts.checked = 1 and conc!='' and conc is not null limit 1)>0,1,0)) as screening_result, ";
                //$adding_where.= " and panel_tests_requested != '' ";
            } else if ($btype == 'pcr') {
                foreach ($enabled_abxs as $abxtype => $abxarr) {
                    $abxarr['num'] = 99;
                    switch ($abxtype) {
                        case 'uti':
                            $abxarr['num'] = 1;
                            break;
                        case 'wound':
                            $abxarr['num'] = 2;
                            break;
                        case 'gi':
                            $abxarr['num'] = 3;
                            break;
                        case 'r':
                            $abxarr['num'] = 4;
                            break;
                        case 'v':
                            $abxarr['num'] = 5;
                            break;
                        case 'ei':
                            $abxarr['num'] = 6;
                            break;
                        case 'sti':
                            $abxarr['num'] = 7;
                            break;
                        case 'nail':
                            $abxarr['num'] = 8;
                            break;
                    }


                    $fieldpositive = '(re.has_uti > 0 and re.has_uti <9)';
                    /*
                    //only count abx generated after 2026-01-01

                    if ($to_date < '2026-01-01') {
                        $fieldpositive = 're.has_uti > 0';
                        if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
                            $fieldpositive .= ' or re.has_pos > 0';
                        }
                    }*/

                    $q_group = '';
                    if (isset($abxarr['groupids']) && is_array($abxarr['groupids']) && count($abxarr['groupids']) > 0) {
                        $q_group = ' ( ';
                        foreach ($abxarr['groupids'] as $gid) {
                            if ($q_group != ' ( ') {
                                $q_group .= ' or ';
                            }
                            $q_group .= " FIND_IN_SET( 'GROUP_{$gid}' , ba.panel_tests_requested ) ";
                        }
                        $q_group .= ' ) ';
                    } else {
                        $q_group = " re.has_uti = '{$abxarr['num']}' ";
                    }
                    $adding_query .= " sum(if(({$fieldpositive} ) and {$q_group} and re.estatus='C' ,1,0)) as count_{$abxtype}, ";
                }
            }
            if ($btype != 'tox') {
                $where .= " and ba.panel_tests_requested != '' and ba.panel_tests_requested is not null";
            }


            if ($btype == 'study') {
                $sql = " select 
                {$count_query},
                0 as lab_ref,
                SUM(if(ba.batch_status like 're\_%' or ba.batch_status='rejected', 1, 0)) as count_Rejected,
                {$count_result_query},
                {$adding_query}
                ba.ref_lab_ID,
                ba.lab_id,
                0 as specimen_fee
                    from {$table}  ba 
                left join {$report_tbl} re On re.patient_id = ba.assen
                    where 1 {$where_study} 
                    group by lab_ref";

                if (isset($_GET['debug'])) {
                    echo '<br><br>';
                    echo $sql;
                    echo '<br><br>';
                }
            } else {
                $sql = " select 
                {$count_query},
                IF(ba.type_of_specimen='Oral',concat(lr.lab_dba_init,'-','Oral'),lr.lab_dba_init) as lab_ref,
                SUM(if(ba.batch_status like 're\_%' or ba.batch_status='rejected', 1, 0)) as count_Rejected,
                {$count_result_query},
                {$adding_query}
                ba.ref_lab_ID,
                ba.lab_id,
                lr.lab_ref_specimen_fee as specimen_fee
                    from {$table}  ba 
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                left join {$report_tbl} re On re.patient_id = ba.assen
                    where ba.lab_id = {$b->lab_ID} {$where} {$adding_where}
                    group by lab_ref";
            }


            $rs = $wpdb->get_results($sql);
            if (isset($_GET['debug'])) {
                echo '<br><br>';
                echo 'Query Btype : ' . $btype;
                echo '<br><br>';
                var_dump($sql);
                echo '<br><br>';
            }
            foreach ($rs as $r) {
                $key = !empty($r->lab_ref) ? $r->lab_ref : '0';
                if (!is_array($rows[$key])) {
                    $rows[$key] = array();
                }
                $rows[$key]['count_' . $btype] = $r->count;
                $rows[$key]['lab_ref'] = $r->lab_ref;
                $rows[$key]['count_Rejected_' . $btype] = $r->count_Rejected;
                $rows[$key]['count_result_' . $btype] = $r->count_result;
                if ($btype == 'pcr') {
                    $rows[$key]['totalabx'] = 0;
                    foreach ($enabled_abxs as $abxtype => $abxarr) {
                        $rows[$key]['count_' . $abxtype] = isset($r->{'count_' . $abxtype}) ? $r->{'count_' . $abxtype} : 0;
                        $rows[$key]['totalabx'] += isset($r->{'count_' . $abxtype}) ? $r->{'count_' . $abxtype} : 0;
                    }
                } else  if ($btype == 'tox') {
                    $rows[$key]['screening'] = $r->screening;
                    $rows[$key]['screening_result'] = $r->screening_result;
                }


                $rows[$key]['specimen_fee'] = $r->specimen_fee;
                $rows[$key]['labr_specimen_fee'] = $wpdb->get_var("select lab_specimen_fee from ci_lab_ref_fees
                                                                where lab_ID = {$r->lab_id} 
                                                                and ref_lab_ID = {$r->ref_lab_ID} ");
            }
        }

        if (count($rows) == 0) continue;


?>
        <div style="width: 95%;margin-right:3%;padding-bottom:10px;float:left;<?php if ($i % 2 == 0) echo 'clear:both' ?>">
            <h4 style="margin-bottom:0"><?php echo $b->lab ?></h4>
            <table class="wp-list-table fixed widefat striped pages blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th>Samples</th>
                        <th>Management</th>
                        <th style="width:150px">Referenced</th>

                        <?php
                        foreach ($btypes as $btype) {
                            echo '<th>' . tr_pdd_get_typename($btype) . '</th>';
                            if ($btype == 'pcr') {
                                echo '<th>Total ABX</th>';
                                foreach ($enabled_abxs as $abxtype => $abxarr) {
                                    echo '<th data-key="' . $abxtype . '">' . $abxarr['title'] . ' ABX</th>';
                                }
                            } else if ($btype == 'tox') {
                                echo '<th>Screening</th>';
                            }
                        }
                        ?>
                        <th>Fees</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count_total = array();
                    $count_total_results = array();

                    foreach ($rows as $row) {

                        $show_rejected = true;
                        $management = '';
                        $fee = 0;
                        $td_rejected_types = '';
                        $td_types = '';
                        $row['count'] = 0;
                        foreach ($btypes as $btype) {
                            $count_b = $row['count_' . $btype];
                            if ($row['count_Rejected_' . $btype] > 0) {
                                $row['count_' . $btype] -= $row['count_Rejected_' . $btype];
                            }

                            $row['count_Rejected'] += $row['count_Rejected_' . $btype];
                            $td_rejected_types .= '<td>' . intval($row['count_Rejected_' . $btype]) . '</td>';
                            $td_types .= '<td>' . intval($row['count_' . $btype]) . '</td>';
                            if ($btype == 'pcr') {
                                $td_types_abx = '';
                                $td_rejected_types .= '<td></td>';
                                foreach ($enabled_abxs as $abxtype => $abxarr) {
                                    $td_types_abx .= '<td>' . intval($row['count_' . $abxtype]) . '</td>';
                                    $td_rejected_types .= '<td></td>';
                                    $count_total['count_' . $abxtype] += $row['count_' . $abxtype];
                                    $count_total_results['count_' . $abxtype] += $row['count_' . $abxtype];

                                    $count_total['totalabx'] += $row['count_' . $abxtype];
                                    $count_total_results['totalabx'] += $row['count_' . $abxtype];

                                    if (isset($_GET['debug'])) {
                                        echo '<br>';
                                        echo 'ABX Type : ' . $abxtype;
                                        echo '<br>';
                                        var_dump($row['count_' . $abxtype]);
                                        echo '<br>';
                                    }
                                }
                                if (isset($_GET['debug'])) {
                                    var_dump($row);
                                }
                                $td_types .= '<td>' . intval($row['totalabx']) . '</td>';
                                $td_types .= $td_types_abx;
                            } else if ($btype == 'tox') {
                                $td_types .= '<td>' . $row['screening'] . '</td>';
                            }

                            $count_total[$btype] += $count_b;
                            $count_total_results[$btype] += $row['count_result_' . $btype];

                            $row['count'] += $count_b;
                        }



                        if (empty($row['lab_ref'])) {
                            $management = 'igniteLIS';
                            $count_total['man'] += $row['count'];
                            //$count_total['man']+=$row['count_Rejected'];
                            $fee = floatval($b->lab_specimen_fee) * $row['count'];
                            $show_rejected = true;
                        }

                        if ($management == '') {
                            if ($row['labr_specimen_fee'] > 0) {
                                $fee = $row['labr_specimen_fee'] * $row['count'];
                            } else if ($row['specimen_fee'] > 0) {
                                $fee = $row['specimen_fee'] * $row['count'];
                            }

                            if (!empty($row['lab_ref'])) {
                                $count_total['ref'] += $row['count'];
                                // $count_total['ref'] +=$row['count_Rejected'];
                            }
                        }



                        $count_total['total'] += $row['count'];
                        //$count_total['total']+=$row['count_Rejected'];
                        $count_total['fee'] += $fee;
                        $count_total['screening'] += $row['screening'];
                        $count_total_results['screening'] += $row['screening_result'];


                    ?>
                        <tr>
                            <td><?php echo $row['count'] ?></td>
                            <td><?php echo $management ?></td>
                            <td><?php echo $row['lab_ref'] ?></td>
                            <?php echo $td_types ?>
                            <td>
                                <?php
                                if ($fee > 0) {
                                    echo '$' . number_format($fee, 2);
                                }
                                ?>
                            </td>
                        </tr>

                        <?php if ($show_rejected && $row['count_Rejected'] > 0) { ?>
                            <tr>
                                <td><?php echo $row['count_Rejected'] ?></td>
                                <td></td>
                                <td><?php echo 'Rejected ' . $row['lab_ref'] ?></td>
                                <?php echo $td_rejected_types ?>
                                <td></td>
                            </tr>
                        <?php } ?>
                    <?php
                    }

                    if (isset($_GET['debug'])) {
                        var_dump($count_total);
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="totalrow2">
                        <td><?php echo $count_total['total'] ?></td>
                        <td><?php echo $count_total['man'] ?></td>
                        <td><?php echo $count_total['ref'] ?></td>

                        <?php
                        foreach ($btypes as $btype) {
                            echo '<td>' . $count_total[$btype] . '</td>';
                            if ($btype == 'pcr') {
                                echo '<td>' . $count_total['totalabx'] . '</td>';
                                foreach ($enabled_abxs as $abxtype => $abxarr) {
                                    echo '<td>' . $count_total['count_' . $abxtype] . '</td>';
                                }
                            } else if ($btype == 'tox') {
                                echo '<td>' . $count_total['screening'] . '</td>';
                            }
                        }
                        ?>
                        <td>$<?php echo number_format($count_total['fee'], 2) ?></td>
                    </tr>
                    <tr class="totalrow2">
                        <td colspan="2"></td>
                        <td>Results</td>
                        <?php
                        foreach ($btypes as $btype) {
                            echo '<td>' . $count_total_results[$btype] . '</td>';
                            if ($btype == 'pcr') {
                                echo '<td>' . $count_total_results['totalabx'] . '</td>';
                                foreach ($enabled_abxs as $abxtype => $abxarr) {
                                    echo '<td>' . $count_total_results['count_' . $abxtype] . '</td>';
                                }
                            } else if ($btype == 'tox') {
                                echo '<td>' . $count_total_results['screening'] . '</td>';
                            }
                        }
                        ?>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php
        if (!empty($b->lab)) {
            $lab = array(
                'name' => $b->lab,
                'igniteLIS' => $count_total['total'],
                'ref' => $count_total['ref'],
                'fee' => $count_total['fee'],
                'screening' => $count_total['screening'],
            );
            foreach ($btypes as $btype) {
                $lab[$btype] = $count_total[$btype];
            }
            $labs[] = $lab;
        }
    }
    ?>
    <div>

        <?php

        if ($blog_id == 1) {
            $total = 0;
            $total_ref = 0;
            $total_screening = 0;
            $fees  = 0;
            $type_total = array();
        ?>
            <div style="width:95%;padding-top:15px;clear:both;">
                <table class="wp-list-table fixed widefat striped pages blue_tbl hover_tbl">
                    <thead>
                        <tr>
                            <th>Lab Name</th>
                            <th>igniteLIS</th>
                            <th>Referenced</th>

                            <?php
                            foreach ($btypes as $btype) {
                                echo '<th>' . tr_pdd_get_typename($btype) . '</th>';
                                if ($btype == 'tox') {
                                    echo '<th>Screening</th>';
                                }
                            }
                            ?>
                            <th>Fee Totals</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($labs as $lab) {
                            $total += $lab['igniteLIS'];
                            $total_ref += $lab['ref'];
                            $total_screening += $lab['screening'];
                            $fees += $lab['fee'];

                        ?>
                            <tr>
                                <td><?php echo $lab['name'] ?></td>
                                <td><?php echo $lab['igniteLIS'] ?></td>
                                <td><?php echo $lab['ref'] ?></td>
                                <?php
                                foreach ($btypes as $btype) {
                                    echo '<td>' . $lab[$btype] . '</td>';
                                    if ($btype == 'tox') {
                                        echo '<td>' . $lab['screening'] . '</td>';
                                    }
                                    $type_total[$btype] += $lab[$btype];
                                }
                                ?>
                                <td>$<?php echo number_format($lab['fee'], 2) ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="totalrow">
                            <td></td>
                            <td><?php echo $total ?></td>
                            <td><?php echo $total_ref ?></td>
                            <?php
                            foreach ($btypes as $btype) {
                                echo '<td>' . $type_total[$btype] . '</td>';
                                if ($btype == 'tox') {
                                    echo '<td>' . $total_screening . '</td>';
                                }
                            }
                            ?>
                            <td>$<?php echo number_format($fees, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php
        }

        $sql_total_today = array();
        $sql_total_month = array();
        $sql_total_year = array();
        $sql_fee_today = array();

        foreach ($btypes as $btype) {
            $table = tr_pdd_get_table_by_btype($btype);

            $sql_total_today[] = "select count(ba.mid) as bcount
                                        from {$table} ba where ba.batch_id!=0 and ba.spec_received >= '{$yesterday}' 
                                        and ba.spec_received <= '{$yesterday} 23:59:59' {$where_lab} ";

            $sql_total_month[] = "select count(ba.mid) as bcount
                                        from {$table} ba where ba.batch_id!=0 and ba.spec_received >= '{$datem_from}' 
                                        and ba.spec_received <= '{$datem_to} 23:59:59' {$where_lab} ";

            $sql_total_year[] = "select count(ba.mid) as bcount
                                        from {$table} ba where ba.batch_id!=0 and ba.spec_received >= '{$datey_from}' 
                                        and ba.spec_received <= '{$datey_to} 23:59:59' {$where_lab} ";

            if ($blog_id == 1) {
                $sql_fee_today[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lr.lab_ref_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$yesterday}' and ba.spec_received <= '{$yesterday} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";

                $sql_fee_month[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lr.lab_ref_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$datem_from}' and ba.spec_received <= '{$datem_from} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";

                $sql_fee_year[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lr.lab_ref_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$datey_from}' and ba.spec_received <= '{$datey_from} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";
            } else {
                $sql_fee_today[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lrf.lab_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_lab_ref_fees lrf ON lrf.lab_ID = ba.lab_id and lrf.ref_lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$yesterday}' and ba.spec_received <= '{$yesterday} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";

                $sql_fee_month[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lrf.lab_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_lab_ref_fees lrf ON lrf.lab_ID = ba.lab_id and lrf.ref_lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$datem_from}' and ba.spec_received <= '{$datem_to} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";

                $sql_fee_year[] = "select (count(ba.mid) * if(ba.ref_lab_ID>0,lrf.lab_specimen_fee,lab.lab_specimen_fee) ) as fee
                from {$table} ba
                join ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_lab_ref_fees lrf ON lrf.lab_ID = ba.lab_id and lrf.ref_lab_ID = ba.ref_lab_ID
                where ba.batch_id>0 and ba.spec_received >= '{$datey_from}' and ba.spec_received <= '{$datey_to} 23:59:59' {$where_lab}
                group by ba.ref_lab_ID,ba.lab_id";
            }
        }

        $sql_total_today = implode(" UNION ALL ", $sql_total_today);
        $total_specimens_today = $wpdb->get_var("select sum(bcount) from ({$sql_total_today}) as rs");


        $sql_total_month = implode(" UNION ALL ", $sql_total_month);
        $total_specimens_month = $wpdb->get_var("select sum(bcount) from ({$sql_total_month}) as rs");

        $sql_total_year = implode(" UNION ALL ", $sql_total_year);
        $total_specimens_year = $wpdb->get_var("select sum(bcount) from ({$sql_total_year}) as rs");


        //fee
        $sql_fee_today = implode(" UNION ALL ", $sql_fee_today);
        $fee_specimens_today = $wpdb->get_var("select sum(fee) from ({$sql_fee_today}) as rs");

        $sql_fee_month = implode(" UNION ALL ", $sql_fee_month);
        $fee_specimens_month = $wpdb->get_var("select sum(fee) from ({$sql_fee_month}) as rs");

        $sql_fee_year = implode(" UNION ALL ", $sql_fee_year);
        $fee_specimens_year = $wpdb->get_var("select sum(fee) from ({$sql_fee_year}) as rs");



        ?>
        <div style="width:95%;margin-top:15px">
            <table class="wp-list-table fixed widefat striped pages blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th></th>
                        <th title="<?php echo $yesterday ?>">Yesterday</th>
                        <th>MTD</th>
                        <th>YTD</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Specimens</td>
                        <td><?php echo $total_specimens_today ?></td>
                        <td><?php echo $total_specimens_month ?></td>
                        <td><?php echo $total_specimens_year ?></td>
                    </tr>
                    <tr>
                        <td>Total Service Fees</td>
                        <td>$<?php echo number_format($fee_specimens_today, 2) ?></td>
                        <td>$<?php echo number_format($fee_specimens_month, 2) ?></td>
                        <td>$<?php echo number_format($fee_specimens_year, 2) ?></td>
                    </tr>

                </tbody>
            </table>
        </div>
        <br><br>
        <?php
        $has_sms = $wpdb->get_var("show tables like  'tr_log_sms'");
        if ($has_sms) {

            $config = get_option('ci_sms_notification_st', array());



            $inrange = $wpdb->get_var("select count(*) from tr_log_sms 
		                            where created >= '{$from_date}'
		                                and created <= '{$to_date} 23:59:59'
		                                and sendtype != 'cl' ");



            echo '<div class="center">Patient\'s SMS in Range: ' . $inrange . ' messages';

            if ($config['enable']) {

                $clinic_inrange = $wpdb->get_var("select count(*) 
		                            from tr_log_sms 
		                            where created >= '{$datestartm}'
		                            and created <= '{$dateendm} 23:59:59'
		                            and sendtype = 'cl' ");

                echo '<div class="center">Clinic\'s SMS in Range: ' . $clinic_inrange . ' messages';
                $total_inrange_sms = $inrange + $clinic_inrange;
                echo '<div class="center">Total  in Range: ' . $total_inrange_sms . ' messages</div>';
            }


            /*


            $datestartm = date_i18n('Y-m-01', strtotime($from_date));
            $dateendm = date_i18n('Y-m-t', strtotime($from_date));

            $thismonth = $wpdb->get_var("select count(*) from tr_log_sms 
		                            where created >= '{$datestartm}'
		                                and created <= '{$dateendm} 23:59:59'
		                                and sendtype != 'cl' ");

            echo '<br><br><div class="center">Patient\'s SMS ' . date('M Y', strtotime($datestartm)) . ': ' . $thismonth . ' messages';

            if ($config['enable']) {
                $clinic_thismonth = $wpdb->get_var("select count(*) 
		                            from tr_log_sms 
		                            where created >= '{$datestartm}'
		                            and created <= '{$dateendm} 23:59:59'
		                            and sendtype = 'cl' ");

                echo '<div class="center">Clinic\'s SMS ' . date('M Y', strtotime($datestartm)) . ': ' . $clinic_thismonth . ' messages';
                $total_sms = $thismonth + $clinic_thismonth;
                echo '<div class="center">Total ' . date('M Y', strtotime($datestartm)) . ': ' . $total_sms . ' messages</div>';
            }*/
        }

        $log_month = date('m', strtotime($from_date));
        $log_year = date('Y', strtotime($from_date));
        $log_hubs = $wpdb->get_results("select * 
	                    from ci_log_hubs 
	                    where log_month='{$log_month}' 
	                    and log_year='{$log_year}'");

        if ($log_hubs) {
        ?>
            <table style="border:none;margin:10px auto">
                <tr>
                    <td colspan="2">igniteLab Connect <?php echo date('M Y', strtotime($from_date)) ?></td>
                </tr>
                <?php
                $total = 0;
                foreach ($log_hubs as $log_hub) {
                    $total += $log_hub->log_count;
                ?>
                    <tr>
                        <td><?php echo $log_hub->log_count ?></td>
                        <td><?php echo $log_hub->log_state ?></td>
                    </tr>
                <?php
                }
                ?>
                <tr>
                    <td style="border-top:1px solid #333"><?php echo $total ?></td>
                    <td></td>
                </tr>
            </table>
        <?php
        }
    } else {

        $rs = $wpdb->get_results("select * from ci_test_panels where te_type !='tox' ");
        $all_tests = array();
        foreach ($rs as $r) {
            $all_tests[$r->te_ID] = $r;
        }

        $all_groups = array();
        $ts = $wpdb->get_results("select * from ci_test_groups");
        foreach ($ts as $t) {
            $all_groups[$t->tg_ID] = $t;
        }

        $sql_child = array();
        foreach ($btypes as $btype) {
            $table = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);
            $sql_child[] = "
        select ba.mid,ba.position,ba.assen,ba.oldreq,ba.batch_id,ba.dob,ba.patient_name, ba.spec_received,ba.doc,
                ba.current_medication_other,ba.current_medication,ba.insurance,ba.subscriber_insurance, 
                ba.panel_tests_requested,ba.custom_profile,ba.requesting_practice,ba.type_of_specimen,ba.batch_status,ba.notes,
                ba.requesting_physician,
                cli.cli_city,
                doc.doc_phone,
                doc.doc_npi,
                lab.lab_dba_init as lab,
                lr.lab_dba_init as lab_ref,
                IF(re.id is not null or ba.result_pdf is not null, 1 , 0) as resulted,
                re.approve_date,
                re.estatus,
                pa.pa_address,pa.pa_city,pa.pa_state,pa.pa_zip,pa_phone,pa.pa_mrn,
                '{$btype}' as btype
                from {$table} ba
                left join {$report_tbl} re on re.patient_id = ba.assen
                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                left JOIN ci_clinic cli ON cli.cID = ba.cli_ID or (ba.cli_ID<1 && cli.cli_clinic = ba.requesting_practice)
                left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                left join ci_patients pa On pa.pa_ID = ba.patient_id
                where 1  {$where} 
        ";
        }

        $sql_child = implode(" UNION ALL ", $sql_child);

        $sql = " select * from ({$sql_child}) ba
                order by {$order_by}
             ";


        if (isset($_GET['debug'])) {
            echo $sql;
        }
        $batches = $wpdb->get_results($sql);

        if (count($batches) == 0) {
        ?>
            <div class="clear"></div>
            <p>Not found</p>
        <?php
        } else {

        ?>
            <div class="clear" style="padding-top:10px;">
                Order by
                <label><input type="radio" name="orderby" <?php checked(isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '', 'doc') ?> onclick="location='<?php echo Tr_Base_Class_V6::link(array('orderby' => 'doc', 'order' => 'desc')) ?>'" /> DOC</label>
                <label><input type="radio" name="orderby" <?php checked(isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '', 'spec_received') ?> onclick="location='<?php echo Tr_Base_Class_V6::link(array('orderby' => 'spec_received', 'order' => 'desc')) ?>'" /> Received</label>

                <a class="button" href="<?php echo Tr_Base_Class_V6::link(array('tr_action' => 'ci_export_today_batches', 'from' => $from_date, 'to' => $to_date)) ?>">Export</a>
            </div>
            <br><br>

            <table class="wp-list-table  widefat striped pages blue_tbl hover_tbl" style="width:3000px">
                <thead>
                    <tr>
                        <th style="width:20px">#</th>
                        <th style="width:20px">Resulted</th>
                        <th style="width:8%">Accession Number</th>
                        <th>Order #</th>
                        <th style="min-width:150px">Patient Name</th>
                        <?php if ($show_pa_mrn) {
                            echo '<th style="width:100px">PatientID</th>';
                        } ?>
                        <th style="min-width:150px">Patient Address</th>
                        <?php if (tr_get_option('ci_pdf_data', 'inb_tbpphone')) { ?>
                            <th>Patient Phone</th>
                        <?php } ?>
                        <th style="width: 8%;">Medication</th>
                        <th>DOB</th>
                        <?php tr_tbl_sort('cli', 'Facility') ?>
                        <th>Clinic City</th>
                        <?php tr_tbl_sort('requesting_physician', 'Provider', '', 'min-width:100px') ?>
                        <?php if (tr_get_option('ci_pdf_data', 'inb_show_doc_npi')) { ?>
                            <th>Dr NPI</th>
                        <?php } ?>
                        <th>Dr Phone</th>
                        <th>Insurance</th>
                        <th>Collection</th>
                        <th>Received</th>
                        <th style="width:3%">Lab</th>
                        <th>Referenced</th>
                        <th>Panels</th>
                        <th>Test Requested</th>
                        <th>Insurance</th>
                        <th>Type of Specimen</th>
                        <th>Notes</th>
                        <?php if (tr_get_option('ci_pdf_data', 'inb_tdb_rsd')) {
                            echo '<th style="width:140px">Result Date/time</th>';
                        } ?>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($batches as $row) {
                        //format medication
                        $row->current_medication = str_replace('|', ',', $row->current_medication);
                        $medications = explode(',', trim($row->current_medication));
                        if (!empty($row->current_medication_other)) {
                            $row->current_medication_other = str_replace('|', ',', $row->current_medication_other);
                            $others = explode(',', trim($row->current_medication_other));
                            $medications = array_merge($medications, $others);
                        }
                        foreach ($medications as $j => &$med) {
                            $med = trim($med);
                            $med = ucfirst(strtolower($med));
                            if (empty($med)) {
                                unset($medications[$j]);
                            }
                        }
                        $medications = implode(',', $medications);
                        $medications = str_replace(',', ', ', $medications);

                        //check insurance_name
                        if (empty($row->insurance)) {
                            $row->insurance = $row->subscriber_insurance;
                        }

                        //get panel
                        $testnames = '';
                        $panels = '';

                        if ($row->btype == 'tox') {
                            if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
                                $testnames = $row->panel_tests_requested;
                            } else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
                                $testnames = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
                            } else {
                                $testnames = $row->panel_tests_requested;
                            }
                        } else {
                            $testids = explode(',', $row->panel_tests_requested);
                            $testnames_arr = array();
                            $panels_arr = array();
                            foreach ($testids as $testid) {
                                $testid = trim($testid);
                                if (stripos($testid, 'group') !== false) {
                                    $testid = str_ireplace('GROUP_', '', $testid);
                                    $panels_arr[] = $inb_show_pcode ? $all_groups[$testid]->tg_cpt_code : $all_groups[$testid]->tg_name;
                                } else if (isset($all_tests[$testid])) {
                                    $testnames_arr[] = $inb_show_pcode ? $all_tests[$testid]->te_loinc_code : $all_tests[$testid]->te_name;
                                }
                            }
                            $testnames = implode(',', $testnames_arr);
                            $panels = implode(',', $panels_arr);
                        }
                        $testnames = trim(str_replace(',', "\n", $testnames));
                        $panels = trim(str_replace(',', "\n", $panels));

                        if (!empty($row->pa_city)) {
                            $row->pa_address .= ', ' . $row->pa_city;
                        }

                        if (!empty($row->pa_state)) {
                            $row->pa_address .= ', ' . $row->pa_state;
                        }

                        if (!empty($row->pa_zip)) {
                            $row->pa_address .= ' ' . $row->pa_zip;
                        }
                        $batch_status = $all_status[$row->batch_status];
                        if ($row->estatus != 'C') {
                            $row->approve_date = '';
                            if ($row->estatus == 'M' && empty($row->batch_status)) {
                                $batch_status = 'Reversed';
                            }
                        }
                    ?>
                        <tr data-mid="<?php echo $row->mid ?>" class="btype_<?php echo $row->btype ?>">
                            <td title="<?php echo $row->batch_id ?>" class="col-position"><?php echo $row->position; ?></td>
                            <td><?php if ($row->resulted) echo '<span class="dashicons dashicons-yes"></span>' ?></td>
                            <td><?php echo $row->assen ?></td>
                            <td><?php echo $row->oldreq ?></td>
                            <td><?php echo $row->patient_name ?></td>
                            <?php if ($show_pa_mrn) echo '<td>' . $row->pa_mrn . '</td>' ?>
                            <td><?php echo $row->pa_address ?></td>
                            <?php if (tr_get_option('ci_pdf_data', 'inb_tbpphone')) { ?>
                                <td><?php echo $row->pa_phone ?></td>
                            <?php } ?>
                            <td><?php echo $medications ?></td>
                            <td class="r-date"><?php echo $row->dob ?></td>
                            <td><?php echo $row->requesting_practice ?></td>
                            <td><?php echo $row->cli_city ?></td>
                            <td><?php echo $row->requesting_physician ?></td>
                            <?php if (tr_get_option('ci_pdf_data', 'inb_show_doc_npi')) { ?>
                                <td><?php echo $row->doc_npi ?></td>
                            <?php } ?>
                            <td><?php echo $row->doc_phone ?></td>
                            <td><?php if ($row->batch_type == 'E') echo $row->insurance ?></td>
                            <td class="r-date"><?php echo $row->doc ?></td>
                            <td class="r-date"><?php echo $row->spec_received ?></td>
                            <td><?php echo $row->lab ?></td>
                            <td><?php echo $row->lab_ref ?></td>
                            <td><?php if (!empty($panels)) echo '<textarea readonly>' . $panels . '</textarea>' ?></td>
                            <td><?php if (!empty($testnames)) echo '<textarea readonly>' . $testnames . '</textarea>' ?></td>
                            <td><?php echo $row->insurance ?></td>
                            <td><?php echo $row->type_of_specimen ?></td>
                            <td><?php echo nl2br($row->notes) ?></td>
                            <?php if (tr_get_option('ci_pdf_data', 'inb_tdb_rsd')) {
                                echo '<td>' . $row->approve_date . '</td>';
                            }
                            ?>
                            <td><?php echo $batch_status ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <div>Total: <?php echo count($batches) ?></div>

            <style>
                table .dashicons-yes {
                    color: #00a700 !important;
                }

                table textarea {
                    border: none;
                    background: rgba(255, 255, 255, 0.5);
                }

                table textarea.showfull {
                    height: 500px;
                }

                .btype_blood td.col-position {
                    color: red;
                }

                .btype_dna td.col-position {
                    color: blue;
                }

                .btype_ngs td.col-position {
                    color: green;
                }

                .btype_pcr td.col-position {
                    color: pink;
                }
            </style>
            <script>
                jQuery(function($) {
                    $('table textarea').on('focus', function() {
                        $(this).addClass('showfull');
                    }).on('focusout', function() {
                        $(this).removeClass('showfull');
                    })
                })
            </script>

    <?php
        }
    }
