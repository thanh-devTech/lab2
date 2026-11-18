<?php
global $wpdb;

$start_all_time = microtime(true);

$patient_id = isset($_GET['idPa']) ? $_GET['idPa'] : $patient_id;
$results = [];
$all_panels = array();
$group_tests = array();
$options = get_option('trpatient_settings', array());

$lab_url = '';

if (defined('TRPDD_ALLOW_FRONT_PDF') && TRPDD_ALLOW_FRONT_PDF) {
    $lab_url = get_site_url(1);
    if (empty($lab_url) && defined('DOMAIN_CURRENT_SITE')) {
        $lab_url = 'https://' . DOMAIN_CURRENT_SITE;
    }
}

$patient_ids = [];

if ($patient_id == 'all') {
    $kids = tr_patient_get_kids($patient_id, $user_id);
    foreach ($kids as $kidID => $item) {
        $patient_ids[] = $kidID;
    }
} else {
    $patient_ids[] = $patient_id;
}

$patient_ids = array_map('intval', $patient_ids);
$where_patient = " ba.patient_id IN (" . implode(',', $patient_ids) . ")";
$where_patient .= " and ba.batch_status !='archived' ";
/*
$test_type_str =   implode(',', array_map(function ($type) {
    return "'$type'";
}, tr_pdd_get_batch_types()));
*/
$start_time = microtime(true);
$rs_groups = $wpdb->get_results("
        SELECT tg_ID, tg_name, tg_other_options,tg_label_name,tg_notestcheckbox
        FROM ci_test_groups
        where tg_type IN ('" . implode("','", tr_pdd_get_batch_types()) . "')
        
    ");

foreach ($rs_groups as $i => $panel) {
    $all_panels[$panel->tg_ID] = $panel;
}

$rs_tests = $wpdb->get_results("select te.te_ID, 
            te.te_name,
            g.tg_ID,
            te.te_abrev,
            g.tg_other_options
            from ci_test_panels te
            JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
            where te.te_type IN ('" . implode("','", tr_pdd_get_batch_types()) . "')
            and te.te_in_req = 1 
            and te.te_is_control = 0
            order by te.te_name asc");

foreach ($rs_tests as $t) {
    $tkey = strtolower($t->te_name);
    if (isset($group_tests[$tkey])) {
        $group_tests[$tkey]->te_IDs[] = $t->te_ID;
        $group_tests[$tkey]->tg_IDs[] = $panel->tg_ID;
        $group_tests[$tkey]->tg_IDs[] = $t->tg_other_options;
    } else {
        $t->te_IDs = array($t->te_ID);
        $t->tg_IDs = array($panel->tg_ID);
        $group_tests[$tkey] = $t;
    }
}
$all_panels_time = microtime(true) - $start_time;


$orderby = 'ba.mid';
$order = isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
$where = ' 1=1 ';
$child_sqls = [];

foreach (tr_pdd_get_batch_types() as $bt) {
    $where_covid = '';
    if ($options['only_covid']) {
        if ($bt == 'pcr') {
            $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
            $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
        } else if ($bt == 'blood') {
            $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='blood' 
                                and tg_is_covid = 1");
            $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
        } else {
            continue;
        }
    }

    $batch_tbl  = tr_pdd_get_table_by_btype($bt);
    $report_tbl = tr_pdd_get_table_report_by_btype($bt);

    $child_sqls[] = "
        SELECT
                    ba.mid, 
                    ba.patient_name, 
                    ba.batch_id,
                    ba.assen,
                    ba.doc,
                    ba.spec_received,
                    ba.requesting_practice as clinic,
                    ba.requesting_physician as doctor,
                    ba.panel_tests_requested,
                    
                    pa.pa_last,
                    pa.pa_first,
                    pa.pa_dob,
                    pa.pa_passport,
                    
                    re.id,
                    re.estatus,
                    re.approve_date,
                    IF(re.estatus='C',1,0) as has_result,
                    '{$bt}' as btype
                    
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON re.patient_id = ba.assen
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id 
        WHERE {$where_patient} {$where_covid}
    ";
}

$sql_total = "
    SELECT COUNT(DISTINCT mid) 
    FROM (
        " . implode(' UNION ALL ', $child_sqls) . "
    ) tt
";

$start_time = microtime(true);
$total = (int) $wpdb->get_var($sql_total);
$total_time = microtime(true) - $start_time;

$row_per_page   = 20;
$pagination     = new Tr_Pagination_class2($row_per_page);
$offset         = $pagination->offset();
$pagination->setTotal($total);

$sql = implode(' UNION ALL ', $child_sqls);
$sql = "SELECT * FROM ({$sql}) tt 
        ORDER BY tt.doc DESC 
        LIMIT {$offset},{$row_per_page}";

$start_time = microtime(true);
$all_result = $wpdb->get_results($sql);
$all_result_time = microtime(true) - $start_time;

if (isset($_GET['debugtime'])) {
    echo 'Panel Time: ' . $all_panels_time . ' seconds<br/>';
    echo 'Total Time: ' . $total_time . ' seconds<br/>';
    echo 'Total Records: ' . $total . '<br/>';
    echo 'Query Time: ' . $all_result_time . ' seconds<br/>';
    echo 'All Time: ' . (microtime(true) - $start_all_time) . ' seconds<br/>';
}

$testids = [];
foreach ($all_result as $i => $row) {
    $row->panel_ids = [];
    $row->panels = [];
    $row->panels_about = [];
    foreach (explode(',', $row->panel_tests_requested) as $t) {
        if ($row->btype == 'pcr' || $row->btype == 'blood') {
            if (stripos($t, 'GROUP_') !== false) {
                $gid = str_ireplace('GROUP_', '', $t);
                $group = $all_panels[$gid] ?? null;

                if ($group) {
                    $row->panel_ids[] = $gid;
                    $row->panels[] = $group->tg_name;
                    $row->panels_about[] = $group->tg_other_options;
                }
            }
        } else {
            if (isset($group_tests[strtolower($t)])) {
                $g_ID = $group_tests[strtolower($t)]->tg_ID;
                $group = $all_panels[$g_ID] ?? null;
                if (!in_array($g_ID,  $row->panel_ids)) {
                    if ($group) {
                        $row->panel_ids[] = $g_ID;
                        $row->panels[] =  $group->tg_name;
                        $row->panels_about[] = $group->tg_other_options;
                    }
                }
            }
        }
    }
    $results[$i] = $row;
}

if (isset($_GET['debug'])) {
    echo 'Sql' . $sql;
    echo '<br/>';
    echo 'Pa Id:' . $patient_id;
}

function isValidDateTime($date)
{
    if (empty($date)) {
        return false;
    }

    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return false;
    }

    return strtotime($date) !== false;
}


function show_step($index_step)
{
    if ($index_step == 0) return '';
    $w_step = (($index_step - 1) * 25);
?>
    <div class="pt-4 border-t border-gray-200">
        <p class="text-xs font-medium text-gray-600 mb-4">Sample Status</p>
        <div class="w-full">
            <div class="flex items-center justify-between relative">
                <div class="absolute left-0 right-0 top-5 h-0.5 bg-gray-200" style="z-index: 0;"></div>
                <div class="absolute left-0 top-5 h-0.5 bg-[#D83D00] transition-all duration-500" style="width: <?php echo $w_step; ?>%; z-index: 1;"></div>
                <div class="flex flex-col items-center">
                    <div class="relative inline-block">
                        <?php if ($index_step == 1) { ?><div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-gray-200 animate-pulse"></div><?php } ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative z-10 <?php if ($index_step >= 1) {
                                                                                                                                            echo 'bg-[#D83D00] text-white';
                                                                                                                                        } else {
                                                                                                                                            echo ' bg-gray-200 text-gray-400';
                                                                                                                                        } ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard w-5 h-5" aria-hidden="true">
                                <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-xs font-medium hidden sm:block <?php echo $index_step >= 1 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Order Received</p>
                        <p class="text-xs font-medium sm:hidden <?php echo $index_step >= 1 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Order</p>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <div class="relative inline-block">
                        <?php if ($index_step == 2) { ?><div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-gray-200 animate-pulse"></div><?php } ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative z-10 <?php if ($index_step >= 2) {
                                                                                                                                            echo 'bg-[#D83D00] text-white';
                                                                                                                                        } else {
                                                                                                                                            echo ' bg-gray-200 text-gray-400';
                                                                                                                                        } ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-droplet w-5 h-5" aria-hidden="true">
                                <path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-xs font-medium hidden sm:block <?php echo $index_step >= 2 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Sample Collected</p>
                        <p class="text-xs font-medium sm:hidden <?php echo $index_step >= 2 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Collected</p>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <div class="relative inline-block">
                        <?php if ($index_step == 3) { ?><div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-gray-200 animate-pulse"></div><?php } ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative z-10 <?php if ($index_step >= 3) {
                                                                                                                                            echo 'bg-[#D83D00] text-white';
                                                                                                                                        } else {
                                                                                                                                            echo ' bg-gray-200 text-gray-400';
                                                                                                                                        } ?>"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package w-5 h-5" aria-hidden="true">
                                <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path>
                                <path d="M12 22V12"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <path d="m7.5 4.27 9 5.15"></path>
                            </svg></div>
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-xs font-medium hidden sm:block <?php echo $index_step >= 3 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Received by Lab</p>
                        <p class="text-xs font-medium sm:hidden <?php echo $index_step >= 3 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Received</p>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <div class="relative inline-block">
                        <?php if ($index_step == 4) { ?><div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-gray-200 animate-pulse"></div><?php } ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative z-10 <?php if ($index_step >= 4) {
                                                                                                                                            echo 'bg-[#D83D00] text-white';
                                                                                                                                        } else {
                                                                                                                                            echo ' bg-gray-200 text-gray-400';
                                                                                                                                        } ?>"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical w-5 h-5" aria-hidden="true">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg></div>
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-xs font-medium hidden sm:block <?php echo $index_step >= 4 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Being Tested</p>
                        <p class="text-xs font-medium sm:hidden <?php echo $index_step >= 4 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Testing</p>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <div class="relative inline-block">
                        <?php if ($index_step == 5) { ?><div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 rounded-full bg-gray-200 animate-pulse"></div><?php } ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative z-10 
                            <?php if ($index_step >= 5) {
                                echo 'bg-[#D83D00] text-white';
                            } else {
                                echo ' bg-gray-200 text-gray-400';
                            } ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big w-5 h-5" aria-hidden="true">
                                <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                                <path d="m9 11 3 3L22 4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-xs font-medium hidden sm:block <?php echo $index_step >= 5 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Results Available</p>
                        <p class="text-xs font-medium sm:hidden <?php echo $index_step >= 5 ? 'text-[#3C3D59]' : 'text-gray-400'; ?>">Complete</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}
?>

<main class="p-6 bg-[#f8f9fa] overflow-auto flex-1">
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-[#3C3D59] mb-2">Lab Results</h2>
        </div>
        <div class="space-y-4">

            <?php if (empty($results)) { ?>
                <div data-slot="card-content" class="[&:last-child]:pb-6 p-6 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 font-medium">The list is empty.</span>
                </div>
            <?php } ?>

            <?php if (!empty($results) && is_array($results) && $total > $row_per_page) { ?>
                <div class="tablenav topnav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')); ?></div>
            <?php } ?>

            <?php
            foreach ($results as $index => $item) {

                if (isset($_GET['debug']) && $index == 0)
                    var_dump($item);

                $item_step = 0;
                $txt_date_doc = 'Pending';
                $txt_date_collection = 'Pending';


                $item->pa_passport = str_replace(
                    ['-', '.', ' '],
                    '',
                    $item->pa_passport ?? ''
                );

                $has_passport = strlen($item->pa_passport) >= 8;

                if ($item->pa_passport !== '' && str_replace('0', '', $item->pa_passport) === '') {
                    $has_passport = false;
                }

                $pdflink = '';
                if ($item->id > 0 && $item->estatus == 'C') {
                    $file = $item->btype . '_' . trpdd_patient_encode($item->id) . '_' . $user_id . '.pdf';
                    $pdflink = Tr_Base_Class_V4::link(array('tr_action' => 'patient_result_pdf', 'file' => $file), false);

                    if (isset($options['mms_notify']) && $options['mms_notify'] && $has_passport) {
                        $pdflink .= '&hqr';
                    }
                    $pdflink .= '&pst=1';
                }

                $item_step = 2;

                if ($item->has_result != '1') {
                    if ($item->id > 0) {
                        $item_step = 4;
                    } else if ($item->spec_received >= $item->doc) { //isValidDateTime($item->received_in_lab)
                        $item_step = 3;
                    } else if (isValidDateTime($item->doc)) {
                        $item_step = 2;
                        $txt_date_collection = date_i18n("M j, Y", strtotime($item->doc));
                    } else if (isValidDateTime($item->approve_date)) {
                        $item_step = 1;
                    }
                } else {
                    $item_step = 5;
                    $txt_date_doc = date_i18n("M j, Y", strtotime($item->approve_date));
                    $txt_date_collection = date_i18n("M j, Y", strtotime($item->doc));

                    //Hide step after one week
                    $approve_ts = strtotime($item->approve_date);
                    $after_7_days = strtotime('+7 days', $approve_ts);

                    if (time() >= $after_7_days) {
                        $item_step = 0;
                    } else {
                        $item_step = 5;
                        $txt_date_doc = date_i18n("M j, Y", $approve_ts);
                        $txt_date_collection = isValidDateTime($item->doc)
                            ? date_i18n("M j, Y", strtotime($item->doc))
                            : '';
                    }
                }
            ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-200 bg-white">
                        <div class="flex sm:flex-row items-center justify-between">
                            <div class="w-full sm:w-auto">
                                <h3 class="text-lg font-semibold text-[#3C3D59]">
                                    <?php echo is_array($item->panels) ? implode(', ', $item->panels) : '' ?>
                                </h3>
                            </div>
                            <div class="flex items-center gap-2 sm:mt-0">
                                <?php
                                $is_about = false;
                                foreach ($item->panels as $index_p => $p) {
                                    $about = json_decode($item->panels_about[$index_p]  ?? '', true);
                                    if ($about) {
                                        if (!isset($about['pa_description']) || empty($about['pa_description'])) {
                                            $about['pa_description'] = $about['desc'];
                                        }
                                        if (isset($about) && isset($about['pa_description'])) {
                                            if ($about['pa_description'] != '') {
                                                $is_about = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                                ?>

                                <?php if ($is_about) { ?>
                                    <button data-slot="button" class="btn_about_test cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background hover:bg-accent dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-[#D83D00] hover:text-[#c03600]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark w-4 h-4 mr-1.5" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                            <path d="M12 17h.01"></path>
                                        </svg>About This Test
                                    </button>
                                <?php } ?>

                                <?php
                                if (empty($pdflink)) {
                                ?>
                                    <button data-slot="button" class="inline-flex cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-[#D83D00] hover:bg-[#c03600] text-white" disabled=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download w-4 h-4 mr-1.5" aria-hidden="true">
                                            <path d="M12 15V3"></path>
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <path d="m7 10 5 5 5-5"></path>
                                        </svg>Results PDF
                                    </button>
                                <?php } else {
                                ?>
                                    <button onclick="window.open('<?php echo $pdflink; ?>', '_blank'); return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-[#D83D00] hover:bg-[#c03600] text-white"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download w-4 h-4 mr-1.5" aria-hidden="true">
                                            <path d="M12 15V3"></path>
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <path d="m7 10 5 5 5-5"></path>
                                        </svg>Results PDF</button>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($is_about) { ?>
                            <div style="display: none;" class="-mb-6 -mx-6 px-6 bg-white item_about_content">
                                <div class="border-t border-gray-200 pt-4 pb-4">
                                    <?php
                                    foreach ($item->panels as $index_p => $p) {

                                        $desc = 'None';

                                        $about = json_decode($item->panels_about[$index_p] ?? '', true);
                                        if ($about) {
                                            if (!isset($about['pa_description']) || empty($about['pa_description'])) {
                                                $about['pa_description'] = $about['desc'];
                                            }
                                            $desc = $about['pa_description'] ?? '';
                                        }

                                        $guide_pdf = isset($about['guide_pdf']) ? '<p><strong>Learn more:</strong> <a class="text-[#D83D00]" target="_blank" href="' . $about['guide_pdf'] . '">Patient Test Guide (PDF)</a></p>' : '';

                                        if ($desc == 'None' || $desc == '') continue;

                                        if (count($item->panels) == 1)
                                            echo '<h4 class="font-semibold text-[#D83D00] mb-2">About This Test</h4>';
                                        else
                                            echo '<h4 class="font-semibold text-[#D83D00] mb-2">About Test (' . $p . ')</h4>';
                                    ?>

                                        <div class="space-y-2 text-sm text-gray-700">
                                            <p><strong>Description:</strong> <?php echo $desc; ?></p>
                                            <?php echo $guide_pdf; ?>
                                        </div>
                                        <?php if ($index_p != (count($item->panels) - 1)) { ?><div class="pt-4 border-t border-gray-200 mt-2"></div><?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                    </div>

                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Patient Name</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo $item->pa_first; ?> <?php echo $item->pa_last; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Accession #</p>
                                <p class="text-sm font-medium text-gray-900"><?php echo $item->assen; ?></p>
                            </div>
                            <?php if (!empty($item->clinic)) { ?>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Clinic Name</p>
                                    <p class="text-sm font-medium text-gray-900"><?php echo $item->clinic; ?></p>
                                </div>
                            <?php } ?>

                            <?php if (!empty($item->doctor)) { ?>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Doctor</p>
                                    <p class="text-sm font-medium text-gray-900"><?php echo $item->doctor; ?></p>
                                </div>
                            <?php } ?>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">Collection Date</p>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo $txt_date_collection; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Results Date</p>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo $txt_date_doc; ?>
                                </p>
                            </div>
                        </div>

                        <?php show_step($item_step); ?>

                    </div>
                </div>
            <?php
            }
            ?>

            <?php if (!empty($results) && is_array($results) && $total > $row_per_page) { ?>
                <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')); ?></div>
            <?php } ?>

        </div>
    </div>
</main>

<script>
    $(document).ready(function() {
        $(document).on('click', 'div[data-slot="card"] button.btn_about_test', function(e) {
            let emp_div = $(this).closest('div[data-slot="card"]');
            emp_div.find(".item_about_content").toggle();
        });
    });
</script>
