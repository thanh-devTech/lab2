<?php
global $wpdb;
global $cache_query_seconds;

$tabs = array(
    'uti' => array('title' => 'UTI', 'groupids' => array(), 'actived' => true),
    'wound' => array('title' => 'Wound', 'groupids' => array(), 'actived' => true),
    'r' => array('title' => 'Respiratory', 'groupids' => array(), 'actived' => true),
    'ei' => array('title' => 'ENT', 'groupids' => array(), 'actived' => true),
    'v' => array('title' => 'Vaginal', 'groupids' => array(), 'actived' => true),
    'sti' => array('title' => 'STI', 'groupids' => array(), 'actived' => true),
    'nail' => array('title' => 'Nail', 'groupids' => array(), 'actived' => true),
    'gi' => array('title' => 'GI', 'groupids' => array(), 'actived' => true),
);
$user_id = get_current_user_id();
$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true) ?? 0;

$trajax         = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$month_range    = isset($_REQUEST['month_range']) ? $_REQUEST['month_range'] : 'last_0_month';
$c_cid          = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : $current_clinic;
$g_id           = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;
$view_by           = isset($_REQUEST['view_by']) ? $_REQUEST['view_by'] : 'type';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';


$cache_query_seconds = 3600 * 2; //2 hours
if (isset($_GET['refreshcache']) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'refreshcache') !== false)) {
    $cache_query_seconds = 1;
}

$cur_name_panel = 'All Panels';
$cur_name_clinic = 'All Clinics';
$cur_name_filter_panel = 'All Panels';
$cur_name_month_range = 'Last 6 Months';

$class_tab_active = 'bg-gradient-to-r from-primary to-primary/90 text-white shadow-md';
$class_tab_nomal = 'bg-gray-50 text-gray-600';

$icons_type = array();
$icons_type['all'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dna h-3.5 w-3.5" aria-hidden="true"><path d="m10 16 1.5 1.5"></path><path d="m14 8-1.5-1.5"></path><path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path><path d="m16.5 10.5 1 1"></path><path d="m17 6-2.891-2.891"></path><path d="M2 15c6.667-6 13.333 0 20-6"></path><path d="m20 9 .891.891"></path><path d="M3.109 14.109 4 15"></path><path d="m6.5 12.5 1 1"></path><path d="m7 18 2.891 2.891"></path><path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path></svg>';
$icons_type['uti'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-droplet h-3.5 w-3.5" aria-hidden="true"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"></path></svg>';
$icons_type['sm'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity h-3.5 w-3.5" aria-hidden="true"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"></path></svg>';
$icons_type['v'] = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><circle cx="12" cy="8" r="5"></circle><line x1="12" y1="13" x2="12" y2="21"></line><line x1="9" y1="18" x2="15" y2="18"></line></svg>';
$icons_type['wound'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bandage h-3.5 w-3.5" aria-hidden="true"><path d="M10 10.01h.01"></path><path d="M10 14.01h.01"></path><path d="M14 10.01h.01"></path><path d="M14 14.01h.01"></path><path d="M18 6v11.5"></path><path d="M6 6v12"></path><rect x="2" y="6" width="20" height="12" rx="2"></rect></svg>';
$icons_type['r'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wind h-3.5 w-3.5" aria-hidden="true"><path d="M12.8 19.6A2 2 0 1 0 14 16H2"></path><path d="M17.5 8a2.5 2.5 0 1 1 2 4H2"></path><path d="M9.8 4.4A2 2 0 1 1 11 8H2"></path></svg>';
$icons_type['sti'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-round h-3 w-3" aria-hidden="true"><path d="M18 21a8 8 0 0 0-16 0"></path><circle cx="10" cy="8" r="5"></circle><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"></path></svg>';
$icons_type['gi'] = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-waves h-3 w-3" aria-hidden="true"><path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"></path><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"></path><path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"></path></svg>';

$colors_type = CI_Base_Panel::get_panel_types('color');

//== Filter Date ==
$filter_date_select = [];

// Tháng hiện tại

// last 3 months
$ftimethismonth = strtotime(date_i18n('Y-m-01')); //first day of current month
for ($i = 0; $i <= 2; $i++) {
    $filter_date_select[] = [
        'value' => 'last_' . $i . '_month',
        'text' => date_i18n('F Y', strtotime("first day of -$i month", $ftimethismonth))
    ];
}

$filter_date_select[] = [
    'value' => 'last_6_month',
    'text' => 'Last 6 Months'
];
$filter_date_select[] = [
    'value' => 'custom_range',
    'text' => 'Custom Range'
];

// Lấy tên tháng hiện tại theo lựa chọn
foreach ($filter_date_select as $value) {
    if ($value['value'] == $month_range) {
        $cur_name_month_range = $value['text'];
    }
}

//=== Create Data ===//
$monthnow = date_i18n('m');
$yearnow = date_i18n('Y');
$month6 = $monthnow - 5;
if ($month6 <= 0) {
    $yearnow -= 1;
    $month6 += 12;
}
$date_from_6months = $yearnow . '-' . $month6 . '-01';

$date_from = date_i18n('Y-m-01');
$date_to = date_i18n('Y-m-t');

switch ($month_range) {
    case 'last_0_month':
        $date_from = date_i18n('Y-m-01');
        $date_to = date_i18n('Y-m-t');
        break;

    case 'last_1_month':
    case 'last_2_month':
        $offset = intval(str_replace(['last_', '_month'], '', $month_range));
        $date_from = date_i18n('Y-m-01', strtotime("first day of -$offset month", $ftimethismonth));
        $date_to = date_i18n('Y-m-t', strtotime("last day of -$offset month", $ftimethismonth));
        break;

    case 'last_6_month':
    case 'last_6_months':
        $date_from = date_i18n('Y-m-01', strtotime("first day of -5 months", $ftimethismonth));
        $date_to = date_i18n('Y-m-t', strtotime("last day of this month", $ftimethismonth));
        break;

    case 'custom_range':
    default:
        $date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : date_i18n('Y-m-01');
        $date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : date_i18n('Y-m-t');
        break;
}

$can_view_results = current_user_can('ci_resutls_pcr');

$main_abx_group_gids = array();
$groups_mapping = array();
$sql_groups = "
select tg_ID, tg_name, tg_other_options, tg_cpt_code, tg_label_name, tg_short_name, tg_bgcolor
from ci_test_groups
where tg_parent = 0
and tg_type = 'pcr'

and tg_other_options like '%new_abx\":\"1%'
and tg_antibiotic = 0 
order by tg_position asc
";
if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
    $cache = new Tr_Cache_Query('reportabx');
    $main_groups = $cache->get_results($sql_groups, OBJECT, 600);
} else {
    $main_groups = $wpdb->get_results($sql_groups);
}



foreach ($main_groups as $g) {
    $tabkey = '';
    $other_options = json_decode($g->tg_other_options, true);

    if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
        $tabkey = $other_options['abx_type'];
    } else if (stripos($g->tg_name, 'wound') !== false) {
        $tabkey = 'wound';
    } else if (stripos($g->tg_name, 'gi') !== false) {
        $tabkey = 'gi';
    } else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
        $tabkey = 'r';
    }

    if (in_array($tabkey, array('sm', 'sr', 'sp', 'sl', 'es'))) {
        $tabkey = 'sti';
    }

    if (empty($tabkey)) {
        continue;
    }

    if (isset($other_options['new_abx_date']) && $other_options['new_abx_date'] > date_i18n('Y-m-d')) {
        // $tabs[$tabkey]['actived'] = false;
    }

    $tabs[$tabkey]['groupids'][] = $g->tg_ID;
    $groups_mapping[$g->tg_ID] =
        [
            'name' => str_replace('(new)', '', $g->tg_name),
            'short_name' => $g->tg_cpt_code
                ?: $g->tg_label_name
                ?: $g->tg_short_name
                ?: $g->tg_name,
            'group_color' => $g->tg_bgcolor
        ];
}


foreach ($tabs as $tabkey => $arr) {
    // Xóa tab nếu không có groupids
    if (empty($arr['groupids'])) {
        unset($tabs[$tabkey]);
        continue;
    }

    // --- Bổ sung icon ngẫu nhiên nếu chưa có ---
    if (!isset($icons_type[$tabkey])) {
        $icon_keys = ['uti', 'sm', 'wound', 'r', 'gi'];
        $rand_icon_val = $icon_keys[array_rand($icon_keys)];
        $icons_type[$tabkey] = $icons_type[$rand_icon_val];
    }

    // --- Bổ sung màu ngẫu nhiên nếu chưa có ---
    if (!isset($colors_type[$tabkey])) {
        $colors_type[$tabkey] = sprintf('#%06X', mt_rand(0, 0xFFFFFF)); // màu ngẫu nhiên
    }
}
unset($arr);

if (count($tabs) >= 1) {
    $tabs = array('all' => array('title' => 'All Panels')) + $tabs;
}


// Tự gom toàn bộ groupids cho tab ALL
if (isset($tabs['all'])) {
    $all_ids = [];
    foreach ($tabs as $k => $v) {
        if ($k === 'all') continue;
        if (!empty($v['groupids'])) {
            $all_ids = array_merge($all_ids, $v['groupids']);
        }
    }
    $tabs['all']['groupids'] = $all_ids;
}

$tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (!array_key_exists($tab, $tabs)) {
    $tab = key($tabs);
}



$email_title    = 'ABX - ';
if ($tab == 'all') {
    $email_title .= 'All Panels (' . ($view_by == 'type' ? 'Type' : 'Panel') . ' View)';
} else {
    $email_title .= $tabs[$tab]['title'] . ' Panel';
}
$schedule_report = new CI_Report_Schedule('abx', $email_title);





// Cuối cùng gán groupids cho tab hiện tại
if (count($main_groups) > 0 && isset($tabs[$tab]['groupids'])) {
    $main_abx_group_gids = $tabs[$tab]['groupids'];
}

$tab_title = isset($tabs[$tab]['title']) ? $tabs[$tab]['title'] : 'UTI';
$cur_name_panel = isset($tabs[$tab]['title']) ? $tabs[$tab]['title'] : 'All Panels';


if (isset($_GET['debug'])) {
    var_dump($main_groups);
    echo "<br><br>";
    var_dump($tabs);
}

$show_clinic = false;
$report_object = '';
$conditions = trpdd_get_batch_conditions(0, 'pcr');

if (isset($_GET['debug1'])) {
    var_dump($conditions);
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'create_pdf') {
    include('create_pdf.php');
    exit;
}


if (strlen($conditions['where_clinic']) > 10) {
    $sql = "SELECT * FROM ci_clinic WHERE {$conditions['where_clinic']} ORDER BY cli_clinic ASC";
    $clinics = $wpdb->get_results($sql);
} else {

    $report_object = CI_Base_Lab::getCurrentLabName();

    $cli_where_group = array();
    foreach ($main_abx_group_gids as $gid) {
        $cli_where_group[] = "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
    }
    $cli_where_group_str = implode(' OR ', $cli_where_group);
    $thismonthstamp = strtotime(date_i18n('Y-m-01'));
    $last_6month = date_i18n('Ymd', strtotime('-6 months', $thismonthstamp));
    $sql_clinic = "
            SELECT c.*, 
                (
                    SELECT COUNT(*) 
                    FROM ci_batches_pcr ba
                    WHERE ba.cli_ID = c.cID
                    AND ({$cli_where_group_str})
                    AND ba.is_saved = 0
                    AND ba.batch_id >= '{$last_6month}'
                ) AS count  
            FROM ci_clinic c
            WHERE c.cli_status = 1
            ORDER BY c.cli_clinic ASC
        ";


    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
        $cache = new Tr_Cache_Query('reportabx');
        $clinics = $cache->get_results($sql_clinic, OBJECT, 600);
    } else {
        $clinics = $wpdb->get_results($sql_clinic);
    }

    usort($clinics, function ($a, $b) {
        return $b->count - $a->count;
    });
}

if (count($clinics) > 1) {
    $show_clinic = true;

    if ($c_cid > 0) {
        foreach ($clinics as $clinic) {
            if ($clinic->cID == $c_cid) {
                $report_object = $clinic->cli_clinic;
                $cur_name_clinic = $clinic->cli_clinic;
                break;
            }
        }
    } elseif (empty($report_object)) {
        $report_object = 'All Offices';
    }
} else {
    foreach ($clinics as $clinic) {
        $report_object = $clinic->cli_clinic;
        break;
    }
}
if (!empty($main_abx_group_gids) && $g_id > 0) {
    if ($g_id > 0) {
        $cur_name_filter_panel = isset($groups_mapping[$g_id]) ? $groups_mapping[$g_id]['name'] : '';
    }
}


if ($trajax == 'month_range') {
    $obj_result = [];
    $obj_result['clinics'] = $clinics;
    echo json_encode($obj_result);
    exit;
}

if ($trajax == 'report_all_panels') {
    include('report_all_panels_ajax.php');
    if (defined('RECACHEQUERIES') || defined('DO_EMAIL_SCHEDULE_REPORTS')) {
        return;
    }
    exit;
}

if ($trajax == 'report_tabs') {
    include('report_tabs_ajax.php');
    if (defined('RECACHEQUERIES') || defined('DO_EMAIL_SCHEDULE_REPORTS')) {
        return;
    }
    exit;
}

$schedule_report->ajax();

wp_enqueue_script('jquery-ui-tooltip');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
        margin-bottom: 6px !important;
    }

    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 2px;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10px;
    }

    .text-primary {
        color: var(--primary);
    }

    :where(.space-y-2>:not(:last-child)) {
        --tw-space-y-reverse: 0;
        margin-block-start: calc(calc(var(--spacing)*2)*var(--tw-space-y-reverse)) !important;
        margin-block-end: calc(calc(var(--spacing)*2)*calc(1 - var(--tw-space-y-reverse))) !important;
    }

    .bg-white {
        background-color: white;
    }

    .rep_tap:hover {
        background-color: white;
    }

    #nav_tab {
        --tw-gradient-to: oklch(0.99 0 0 / 0) !important;
        --tw-gradient-stops: var(--tw-gradient-via-stops) !important;
    }

    .rep_tap {
        background-color: #ffffff1f;
    }
</style>
<?php
$schedule_report->style();
?>
<script>
    var can_view_results = <?php echo $can_view_results ? 1 : 0 ?>;
    var padding_char = 10;
    var fontCenterText_Pie =
        'bold 20px ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
    var fontCenterTextTip_Pie =
        '12px ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
    var noDataPlugin = {
        id: 'noData',
        beforeDraw(chart, args, options) {
            const {
                ctx,
                data,
                chartArea
            } = chart;
            if (!chartArea) return;

            const hasData = data.datasets.some(ds => ds.data && ds.data.length > 0);

            if (!hasData) {
                const {
                    top,
                    left,
                    width,
                    height
                } = chartArea;

                ctx.save();
                ctx.clearRect(left, top, width, height);

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '16px sans-serif';
                ctx.fillStyle = '#999';
                ctx.fillText('No Data Available', left + width / 2, top + height / 2);
                ctx.restore();
            }
        }
    };

    var legend_labels = {
        usePointStyle: true,
        pointStyle: 'circle',
        boxWidth: 8,
        boxHeight: 8,
        font: {
            size: 13,
        },
        padding: 18,
        color: '#696969ff'
    };

    var grid_x_y = {
        color: 'rgba(126, 126, 126, 0.05)',
        borderDash: [6, 3],
        borderDashOffset: 0,
        drawTicks: false,
        lineWidth: 1
    };

    function applyRoundedTop(chart) {
        const ds = chart.data.datasets;
        const count = ds.length;
        const dataCount = chart.data.labels.length;

        for (let i = 0; i < count; i++) {
            const dset = ds[i];
            dset.borderRadius = Array(dataCount).fill(0);
            // thêm đường viền mảnh giữa các stack
            dset.borderWidth = -1;
        }

        // xác định dataset nằm trên cùng của từng cột và bo góc trên
        for (let col = 0; col < dataCount; col++) {
            for (let i = count - 1; i >= 0; i--) {
                if (ds[i].data[col] > 0) {
                    ds[i].borderRadius[col] = {
                        topLeft: 4,
                        topRight: 4
                    };
                    break;
                }
            }
        }
    }

    function styleDoughnutChart(chartObj, cutout = '59%', radius = '100%') {
        if (!chartObj || !chartObj.data || !chartObj.data.datasets) return;

        chartObj.data.datasets.forEach(ds => {
            ds.cutout = cutout;
            ds.radius = radius;
            ds.borderWidth = 1;
        });
    }
</script>

<div id="root" class="wrap">
    <div class="flex-1 overflow-auto p-8">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-semibold">Reports & Analytics</h1>
                    <p class="text-muted-foreground mt-1 text-[13px]" style="margin-bottom: 4px;">Aggregated testing
                        data for igniteABX-enabled panels.</p>
                </div>
            </div>
            <div>
                <div id="nav_tab" class="bg-gradient-to-r from-gray-50 to-gray-50 rounded-2xl p-3 shadow-sm mb-6">
                    <div class="flex flex-wrap gap-2">
                        <?php
                        foreach ($tabs as $k => $arr) {
                            $class = $tab == $k ? $class_tab_active : $class_tab_nomal;
                            $link = Tr_Base_Class_V6::link(array(
                                'tab' => $k,
                                'cid' => '',
                                'm' => '',
                            ), false);
                        ?>
                            <button data-type="<?php echo $k; ?>" data-link="<?php echo $link; ?>"
                                class="rep_tap cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php echo $class; ?> hover:bg-white hover:shadow-sm">
                                <?php if (isset($icons_type[$k]))
                                    echo $icons_type[$k]; ?> <?php echo $arr['title']; ?>
                            </button>
                        <?php
                        }
                        ?>
                    </div>
                </div>
                <div class="main-content">
                    <?php
                    if ($tab != "contact" && $tab != "review" && $tab != 'uyr'):
                    ?>
                        <div id="box_filter_all_tab" data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card mb-6">
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                                <div class="flex flex-wrap items-center gap-4 justify-between">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="lucide lucide-calendar h-4 w-4 text-muted-foreground" aria-hidden="true">
                                                <path d="M8 2v4"></path>
                                                <path d="M16 2v4"></path>
                                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                                <path d="M3 10h18"></path>
                                            </svg>
                                            <select id="month_range" name="month_range">
                                                <?php foreach ($filter_date_select as $item): ?>
                                                    <option value="<?php echo esc_attr($item['value']); ?>">
                                                        <?php echo esc_html($item['text']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button id="daterange-btn-all-tab" style="width:auto" type=button role=combobox
                                                aria-controls=radix-:rf:aria-expanded=false aria-autocomplete=none dir=ltr
                                                data-state=closed data-slot=select-trigger data-size=default
                                                class="border-input data-[placeholder]:text-muted-foreground [&amp;_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex items-center justify-between gap-2 rounded-md px-3 py-2 whitespace-nowrap outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 w-32 h-8 text-sm border-0 bg-muted/50 hover:bg-muted transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                                    role="img">
                                                    <rect x="3" y="4" width="18" height="17" rx="2"></rect>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="7" y1="14" x2="7" y2="14"></line>
                                                    <line x1="12" y1="14" x2="12" y2="14"></line>
                                                    <line x1="17" y1="14" x2="17" y2="14"></line>
                                                    <line x1="7" y1="18" x2="7" y2="18"></line>
                                                    <line x1="12" y1="18" x2="12" y2="18"></line>
                                                    <line x1="17" y1="18" x2="17" y2="18"></line>
                                                </svg>
                                                <span id="txt_show_customer_day" data-slot="select-value"
                                                    style="pointer-events:none"><?php echo date_i18n('M j', strtotime($date_from)) . " - " . date_i18n('M j', strtotime($date_to)); ?></span>
                                            </button>
                                        </div>
                                        <?php if (count($clinics) > 1): ?>
                                            <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-building2 lucide-building-2 h-4 w-4 text-muted-foreground"
                                                    aria-hidden="true">
                                                    <path d="M10 12h4"></path>
                                                    <path d="M10 8h4"></path>
                                                    <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                                    <path
                                                        d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2">
                                                    </path>
                                                    <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                                </svg>
                                            </div>

                                            <select id="report_clinic" class="none_border">
                                                <option value="0">All Clinics</option>
                                                <?php foreach ($clinics as $item) { ?>
                                                    <option value="<?php echo $item->cID; ?>" <?php if ($c_cid == $item->cID) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                        <?php echo $item->cli_clinic; ?>
                                                    </option>
                                                <?php }; ?>
                                            </select>
                                        <?php endif; ?>

                                        <?php if (!empty($main_abx_group_gids) && count($main_abx_group_gids) > 1 && $tab != 'all') { ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-test-tube h-4 w-4 text-muted-foreground" aria-hidden="true">
                                                <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                                <path d="M8.5 2h7"></path>
                                                <path d="M14.5 16h-5"></path>
                                            </svg>
                                            <select id="report_panel" class="none_border">
                                                <option value="0">All Panels</option>
                                                <?php foreach ($main_abx_group_gids as $id) { ?>
                                                    <option value="<?php echo $id; ?>"
                                                        <?php if ($g_id == $id) {
                                                            echo 'selected';
                                                        } ?>>
                                                        <?php echo $groups_mapping[$id]['name']; ?>
                                                    </option>
                                                <?php }; ?>
                                            </select>
                                        <?php } ?>

                                        <svg id="filter_all_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18"
                                            viewBox="0 0 64 18" aria-hidden="true" role="img" style="/* display: none; */">
                                            <circle cx="9" cy="9" r="6" fill="#6B7280">
                                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                    repeatCount="indefinite" begin="0s"></animate>
                                            </circle>
                                            <circle cx="32" cy="9" r="6" fill="#6B7280">
                                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                    repeatCount="indefinite" begin="0.15s"></animate>
                                            </circle>
                                            <circle cx="55" cy="9" r="6" fill="#6B7280">
                                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                    repeatCount="indefinite" begin="0.3s"></animate>
                                            </circle>
                                        </svg>

                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div style="position:relative;display:inline-block;">
                                            <div class="flex items-center gap-2">
                                                <button id="export_report_all" data-slot="dropdown-menu-trigger"
                                                    class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
                                                    type="button" id="radix-:r2h:" aria-haspopup="menu" aria-expanded="false"
                                                    data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-download h-4 w-4 mr-2" aria-hidden="true">
                                                        <path d="M12 15V3"></path>
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <path d="m7 10 5 5 5-5"></path>
                                                    </svg>Export Report<svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                                        <path d="m6 9 6 6 6-6"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <div id="menu_export_report_all" class="menu-export"
                                                data-radix-popper-content-wrapper="" dir="ltr"
                                                style="position:absolute;left:18px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                                                <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                                    data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                                    aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                                    class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                                    tabindex="-1" data-orientation="vertical"
                                                    style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                                    <div export_func="all" export_type="pdf" role="menuitem"
                                                        data-slot="dropdown-menu-item" data-variant="default"
                                                        class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                                        tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                            </path>
                                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                            <path d="M10 9H8"></path>
                                                            <path d="M16 13H8"></path>
                                                            <path d="M16 17H8"></path>
                                                        </svg>Export as PDF</div>
                                                    <div export_func="all" export_type="csv" role="menuitem"
                                                        data-slot="dropdown-menu-item" data-variant="default"
                                                        class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                                        tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                            </path>
                                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                            <path d="M10 9H8"></path>
                                                            <path d="M16 13H8"></path>
                                                            <path d="M16 17H8"></path>
                                                        </svg>Export as CSV</div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php $schedule_report->btn_schedule(); ?>
                                    </div>

                                </div>
                                <div class="mt-4 pt-4 border-t flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <div class="flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg"
                                                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-funnel h-3.5 w-3.5 text-primary" aria-hidden="true">
                                                <path
                                                    d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z">
                                                </path>
                                            </svg><span class="text-sm text-gray-700">Active Filters:</span>
                                        </div>
                                        <span id="badge_month_all" data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground bg-primary/5 text-primary border-primary/20 font-medium px-3 py-1">----</span>
                                        <span id="badge_filter_clinics" data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground bg-gray-50 text-primary border-gray-200 font-medium px-3 py-1">----</span>
                                        <span id="badge_filter_panel" data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground bg-gray-50 text-primary border-gray-200 font-medium px-3 py-1">----</span>
                                    </div>
                                    <?php if ($tab == 'all'):
                                    ?>
                                        <div class="flex items-center gap-1.5"><span class="text-sm text-gray-600">View:</span>
                                            <div class="inline-flex items-center p-0.5 bg-gray-100 rounded-full border border-gray-200">
                                                <button data-view="type" class="view_by_btn cursor-pointer px-3 py-1 rounded-full text-sm font-medium transition-all <?php echo $view_by == 'type' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">By Type</button>
                                                <button data-view="panel" class="view_by_btn cursor-pointer px-3 py-1 rounded-full text-sm font-medium transition-all <?php echo $view_by != 'type' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'; ?>">By Panel</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php
                    endif;

                    if ($tab == "all") {
                        include_once('report_all_panels.php');
                    } else {
                        include_once('report_tabs.php');
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="text-base flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-triangle-alert h-4 w-4 text-primary" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>Clinical Interpretation</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 text-sm text-gray-600 space-y-2">
                    <p>Critical findings are provided for informational purposes. Detection may indicate colonization or
                        infection and should be evaluated in the context of clinical presentation, specimen type, and
                        patient history.</p>
                    <p>Follow-up may include infection control review, ID consultation, or public health reporting per
                        local policy.</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="text-base flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-activity h-4 w-4 text-primary" aria-hidden="true">
                            <path
                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                            </path>
                        </svg>Data Methodology</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 text-sm text-gray-600 space-y-2">
                    <p>Analysis is based only on finalized PCR results that use the igniteABX interpretive report.
                        Testing not using the igniteABX interpretive report is not included in these metrics. See LIS
                        dashboard for full data.</p>
                    <p>Positivity rates are calculated as positive samples divided by total samples tested, with trends
                        based on rolling averages.</p>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header"
                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="text-base flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-users h-4 w-4 text-primary" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>Questions &amp; Support</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 text-sm text-gray-600 space-y-2">
                    <p>For general questions about testing or interpretation, use the Contact Form or email <a
                            href="mailto:support@igniteABX.com"
                            class="text-primary hover:underline">support@igniteABX.com</a>.</p>
                    <p>For report-specific inquiries, use Request ABX Review from the dashboard, where you can select
                        the report you'd like reviewed for accurate follow-up.</p>
                    <p class="text-xs text-gray-500 mt-3 pt-3 border-t">Report generated:
                        <?php echo date_i18n('F j, Y \a\t h:i A') ?>
                    </p>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px;" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs text-gray-600 text-center"><strong>Note:</strong> This report is intended for use by
                authorized healthcare professionals only. It provides aggregated data to support laboratory and clinic
                insight into testing volumes, utilization, and performance, and may also assist with compliance or
                public health reporting where applicable.</p>
        </div>

    </div>
</div>
</div>


<script>
    var month_range = '<?php echo $month_range; ?>';
    var date_from = '<?php echo $date_from; ?>';
    var date_to = '<?php echo $date_to; ?>';
    var tab = '<?php echo $tab; ?>';
    var cid = '<?php echo $c_cid; ?>';
    var gid = '<?php echo $g_id; ?>';
    var view_by = '<?php echo $view_by; ?>';
    var label_date_range = '';
    var url_main_page = 'ci_reportabx_main';

    showLoading("box_filter_all_tab");

    function update_all_select_report() {
        $("#month_range").val(month_range);
        refreshCustomSelect('month_range');
        if (tab == 'all') {
            $("#month_range_test_type").val(month_range);
            refreshCustomSelect('month_range_test_type');

            $("#month_range_clinic_activity").val(month_range);
            refreshCustomSelect('month_range_clinic_activity');

            $("#month_range_critical").val(month_range);
            refreshCustomSelect('month_range_critical');
        } else {
            $("#month_range_test_result").val(month_range);
            refreshCustomSelect('month_range_test_result');

            $("#month_range_pathogens").val(month_range);
            refreshCustomSelect('month_range_pathogens');

            $("#month_tabs_range_mdr").val(month_range);
            refreshCustomSelect('month_tabs_range_mdr');

            $("#month_range_pol_infections").val(month_range);
            refreshCustomSelect("month_range_pol_infections");

            $("#month_tabs_range_citical").val(month_range);
            refreshCustomSelect("month_tabs_range_citical");

            $("#month_tabs_range_genotypic").val(month_range);
            refreshCustomSelect("month_tabs_range_genotypic");

            $("#month_range_mdr").val(month_range);
            refreshCustomSelect("month_range_mdr");
        }
        update_ui_abx();
    }
    window.update_ui_abx = function() {
        if (month_range == "custom_range") {
            label_date_range = $('#txt_show_customer_day').html();
            $(".custom-select")
                .not("#emp_use_current_clinic_id, #emp_report_clinic, #emp_report_panel, #emp_rmk_prevalence, #emp_bac_prevalence")
                .find(".selected")
                .html("Custom Range");
            $("#daterange-btn-all-tab").addClass("flex").show();
            $("#daterange-btn-all-tab").data('daterangepicker').show();
            $('html, body').animate({
                scrollTop: 0
            }, 500);
        } else {
            label_date_range = $('option:selected', '#month_range').text();
            $(".custom-select")
                .not("#emp_use_current_clinic_id, #emp_report_clinic, #emp_report_panel, #emp_rmk_prevalence, #emp_bac_prevalence")
                .find(".selected")
                .html(label_date_range);
            $("#daterange-btn-all-tab").removeClass("flex").hide();
        }
        if (label_date_range == undefined) label_date_range = "Custom Range";
        $("#badge_month_all").html(label_date_range);
        $(".show_sel_date").html("(" + label_date_range + ")");

    }

    window.load_data_all_report = function() {
        $("#filter_all_loading").show();
        if (tab == 'all') {
            report_all_range_date();
        } else {
            loadDataTabs();
        }
        hideLoading("box_filter_all_tab");
    }

    jQuery(function($) {
        function initializeUI() {
            $("#badge_filter_clinics").removeClass("inline-flex").hide();
            $("#badge_filter_panel").removeClass("inline-flex").hide();
            $("#daterange-btn-all-tab").removeClass("flex").hide();
        }

        function initAllEvents() {
            initSelectEvents();
            initExportEvents();
            initDateRangePicker();
            initializeUI();
        }

        function initSelectEvents() {
            $('#month_range').off('change').on('change', function() {
                var val_sel = $(this).val();
                label_date_range = $('option:selected', '#month_range').text();
                month_range = val_sel;
                if (val_sel != "custom_range") {
                    load_data_all_report();
                    $("#daterange-btn-all-tab").removeClass("flex").hide();
                } else {
                    $("#daterange-btn-all-tab").addClass("flex").show();
                    $("#daterange-btn-all-tab").data('daterangepicker').show();
                }
                update_all_select_report();
            });

            $('#report_clinic').off('change').on('change', function() {
                var sel_val = $(this).val();
                var text_clinic = $('option:selected', this).text();
                cid = sel_val;
                if (sel_val == "0") {
                    $("#badge_filter_clinics").removeClass("inline-flex").hide();
                    $("#badge_filter_clinics").html("----");
                } else {
                    $("#badge_filter_clinics").addClass("inline-flex");
                    $("#badge_filter_clinics").html(text_clinic);
                }
                $("#filter_all_loading").show();
                if (tab == "all")
                    report_all_range_date();
                else {
                    loadDataTabs();
                }
            });

            $('#report_panel').off('change').on('change', function() {
                const val = $(this).val();
                const groupName = $('option:selected', this).text();
                if (val === gid) return;
                gid = val;
                if (val == "0") {
                    $("#badge_filter_panel").removeClass("inline-flex").hide();
                    $("#badge_filter_panel").html("----");
                } else {
                    $("#badge_filter_panel").addClass("inline-flex");
                    $("#badge_filter_panel").html(groupName);
                }
                $("#filter_all_loading").show();
                if (tab == "all")
                    report_all_range_date();
                else {
                    loadDataTabs();
                }
            });
            $('.view_by_btn').on('click', function() {
                const selectedViewBy = $(this).data('view');
                if (view_by === selectedViewBy) return;

                view_by = selectedViewBy;

                if (history.pushState) {
                    var newUrl = new URL(window.location);
                    newUrl.searchParams.set('view_by', view_by);
                    window.history.pushState({
                        path: newUrl.href
                    }, '', newUrl.href);
                }

                $('.view_by_btn').each(function() {
                    const isActive = $(this).data('view') === view_by;
                    $(this).toggleClass('bg-white text-gray-900 shadow-sm', isActive);
                    $(this).toggleClass('text-gray-600 hover:text-gray-900', !isActive);
                });
                report_all_range_date();
            });
        }

        function initExportEvents() {
            $(".btn-export").off('click').on("click", function(e) {
                e.stopPropagation();
                var id_btn = $(this).attr("id");
                $("#menu_" + id_btn).toggle();
            });
        }

        function initDateRangePicker() {
            if ($('#daterange-btn-all-tab').data('daterangepicker')) {
                $('#daterange-btn-all-tab').data('daterangepicker').remove();
            }

            $('#daterange-btn-all-tab').daterangepicker({
                    opens: 'right'
                },
                function(start, end, label) {
                    label_date_range = start.format('MMM D') + ' - ' + end.format('MMM D');
                    $('#badge_month_all').html(label_date_range);
                    $("#txt_show_customer_day").html(label_date_range);
                    date_from = start.format('YYYY-MM-DD');
                    date_to = end.format('YYYY-MM-DD');
                    month_range = 'custom_range';
                    load_data_all_report();
                    update_all_select_report();
                }
            );
        }

        $(document).on('click', '.rep_tap', function(e) {
            e.preventDefault();

            const type = $(this).data("type");
            const link = $(this).data("link");

            if (type === tab) return;

            $(".rep_tap").removeClass("bg-gradient-to-r from-primary to-primary/90 text-white shadow-md")
                .addClass("bg-gray-50 text-gray-600");
            $(this).removeClass("bg-gray-50 text-gray-600")
                .addClass("bg-gradient-to-r from-primary to-primary/90 text-white shadow-md");

            tab = type;
            view_by = 'type';

            if (history.pushState) {
                var newUrl = new URL(window.location);
                newUrl.searchParams.set('tab', type);
                newUrl.searchParams.delete('gid');
                newUrl.searchParams.delete('view_by');
                window.history.pushState({
                    path: newUrl.href
                }, '', newUrl.href);
            }
            month_range = 'last_0_month';
            date_from = '<?php echo $date_from; ?>';
            date_to = '<?php echo $date_to; ?>';

            cid = '<?php echo $current_clinic; ?>';
            gid = 0;
            showLoading("box_filter_all_tab");

            showLoading("boxes_container", true);
            $.ajax({
                url: link + '&trajax=1',
                type: 'GET',
                dataType: 'html',
                success: function(data) {
                    var $newData = $(`<div>${data}</div>`);
                    var newContent = $newData.find("#root .main-content").html();

                    $("#root .main-content").html(newContent);

                    if (typeof window.trpddRefreshUserGuide === 'function') {
                        window.trpddRefreshUserGuide($newData);
                    }

                    initAllEvents();
                    update_ui_select();
                    update_all_select_report();
                    load_data_all_report();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    hideLoading("box_filter_all_tab");
                }
            });
        });

        window.addEventListener('popstate', function(event) {
            location.reload();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest(".custom-select").length) {
                $(".custom-select .options").hide();
            }

            if (!$(e.target).closest(".menu-export").length) {
                $(".menu-export").hide();
            }
        });

        initializeUI();
        initAllEvents();
        update_ui_select();
        update_all_select_report();
        load_data_all_report();
    });
</script>

<?php if ($trajax == '1') { ?>
    <div style="display:none;" id="trpddUserGuideAjax">
        <?php
        $trpdd_skip_user_guide_script = true;
        include TRCPDD_PATH . 'parts/reports/user_guide.php';
        ?>
    </div>
<?php } ?>

<?php
$schedule_report->js();
?>