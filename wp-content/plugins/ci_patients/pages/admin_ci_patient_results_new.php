<?php
global $wpdb;
global $is_ci_admin;

$btypes_str = '';
$user_id = get_current_user_id();
$user = wp_get_current_user();
$can_search_patient_results = $is_ci_admin || in_array('administrator', (array) $user->roles, true);
$today_time = strtotime(date_i18n('Y-m-d H:i:s'));
$check_duplicatep = true;
$byassen = isset($_GET['byassen']) ? $_GET['byassen'] : '';
$byphone = isset($_GET['byphone']) ? $_GET['byphone'] : '';
$result_sort = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'doc';
$result_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'asc' : 'desc';
$result_sort_map = array(
    'assen' => 'tt.assen',
    'collect_date' => 'tt.doc',
    'result_date' => 'tt.approve_date',
    'status' => 'tt.has_result',
);
if (!isset($result_sort_map[$result_sort])) {
    $result_sort = 'doc';
}

if (isset($_REQUEST['reset_patient_id'])) {
    update_user_meta($user_id, '_patient_code', '');
    $wpdb->update($wpdb->users, array('patient_id' => ''), array('ID' => $user_id));
    $wpdb->update('ci_patients', array('pa_email' => ''), array('pa_email' => $user->user_email));
}
wp_dequeue_style('Admin_Page_Class');


include_once(TRCPDD_PATH . 'pages/formdata.php');
wp_enqueue_script('patients', TRCPPR_URL . 'js/admin.js', array(), TRCPPR_V);
wp_enqueue_script('datedropdown', TRCPPR_URL . 'js/jquery.date-dropdowns.min.js', array(), TRCPPR_V);



$options = get_option('trpatient_settings', array());
$patient_id = tr_patient_get_id_patient($user_id);


if ($can_search_patient_results) {
    if (!empty($byassen)) {
        foreach (tr_pdd_get_batch_types() as $btt) {
            $btable = tr_pdd_get_table_by_btype($btt);
            $pid = $wpdb->get_var("select patient_id from {$btable} where assen = '{$byassen}' ");
            if ($pid > 0) {
                $patient_id = $pid;
                break;
            }
        }
    } else if (isset($_GET['patient_id']) && $_GET['patient_id'] > 0) {
        $patient_id = $_GET['patient_id'];
    }

    if (!empty($byphone)) {
        $patient_id = $wpdb->get_var("select pa_ID from ci_patients where pa_phone like '%{$byphone}%'");
    }
}

if (isset($_GET['debugphone'])) {
    $phone = trim($_GET['debugphone']);
    $patient_id = $wpdb->get_var("select pa_ID from ci_patients where pa_phone ='{$phone}'");
}

if (isset($_GET['dd2'])) {
    var_dump($user_id);
    var_dump($patient_id);
    exit;
}

$dob = get_user_meta($user_id, '_dob', true);

//fix old error
if (is_object($patient_id)) {
    $patient_id = $patient_id->pa_ID;
    $wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
}


if ($patient_id == 0 && !$can_search_patient_results) {
    include('admin_ci_setup_patient.php');
    return;
}


if ($options['enable_check_dob'] && empty($dob)) {

    //include('admin_ci_update_dob.php');
    //return;
}

$kids = tr_patient_get_kids($patient_id, $user_id);

if (isset($_GET['update_patient'])) {
    trcppr_cron_sync_patient();
}
$lab_url = '';

if (defined('TRPDD_ALLOW_FRONT_PDF') && TRPDD_ALLOW_FRONT_PDF) {
    $lab_url = get_site_url(1);
    if (empty($lab_url) && defined('DOMAIN_CURRENT_SITE')) {
        $lab_url = 'https://' . DOMAIN_CURRENT_SITE;
    }
}


if ($act == 'reset') {
    delete_user_meta($user_id, '_patient_id');
    $wpdb->update($wpdb->users, array('patient_id' => 0), array('ID' => $user_id));
    update_user_meta($user_id, '_patient_kids', array());
?>
    <h4>Reloading....</h4>
    <script>
        location.reload();
    </script>
<?php
    exit;
} else if ($act == 'addkid') {
    include('admin_ci_add_kid.php');
} else if ($act == 'removekid') {
    $kidid = intval($_REQUEST['id']);
    unset($kids[$kidid]);
    update_user_meta($user_id, '_patient_kids', $kids);

    wp_redirect(Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'id' => '', 'tab' => ''), false));
}
$tabs = array(
    'main' => 'Search Results',
);


if (is_array($kids)) {
    foreach ($kids as $pid => $kid) {
        $tabkey = trpdd_patient_encode($pid);
        $tabs['patient_' . $tabkey] = $kid['last_name'] . ', ' . $kid['first_name'];
    }
}

$is_kid = false;
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (stripos($tab, 'patient_') !== false) {
    $main_patient_id = $patient_id;
    list($temp, $patient_id) = explode('_', $tab);
    $patient_id = trpdd_patient_decode($patient_id);
    if (isset($kids[$patient_id])) {
        $is_kid = true;
    } else {
        $patient_id = $main_patient_id;
        $tab = key($tabs);
    }
}


$all_groups = array();
$ts = $wpdb->get_results("select tg_ID, 
                                tg_name, 
                                tg_label_name,
                                tg_notestcheckbox 
                                from ci_test_groups");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;
}

$patientname = '';
if ($patient_id > 0) {
    $patientname = $wpdb->get_var("select concat(pa_first, ' ', pa_last, ' (', pa_dob,')') from ci_patients where pa_ID = {$patient_id} ");
}

if (!function_exists('trcppr_search_results_icon')) {
    function trcppr_search_results_icon($name, $classes = 'w-4 h-4')
    {
        $icons = array(
            'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            'hash' => '<line x1="4" x2="20" y1="9" y2="9"></line><line x1="4" x2="20" y1="15" y2="15"></line><line x1="10" x2="8" y1="3" y2="21"></line><line x1="16" x2="14" y1="3" y2="21"></line>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
            'x' => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
            'flask' => '<path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path><path d="M6.453 15h11.094"></path><path d="M8.5 2h7"></path>',
            'up' => '<path d="m18 15-6-6-6 6"></path>',
            'down' => '<path d="m6 9 6 6 6-6"></path>',
            'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle>',
        );
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . esc_attr($classes) . '">' . ($icons[$name] ?? '') . '</svg>';
    }
}
if (!function_exists('trcppr_search_results_sort_link')) {
    function trcppr_search_results_sort_link($key, $label, $current_sort, $current_order)
    {
        $next_order = ($current_sort == $key && $current_order == 'asc') ? 'desc' : 'asc';
        $url = add_query_arg(array('orderby' => $key, 'order' => $next_order));
        $up_class = $current_sort == $key && $current_order == 'asc' ? 'w-3 h-3 -mb-1 opacity-100 text-[#D83D00]' : 'w-3 h-3 -mb-1';
        $down_class = $current_sort == $key && $current_order == 'desc' ? 'w-3 h-3 opacity-100 text-[#D83D00]' : 'w-3 h-3';
        return '<a class="inline-flex items-center text-gray-700" href="' . esc_url($url) . '">' . esc_html($label) . '<span class="inline-flex flex-col ml-1 opacity-40">' . trcppr_search_results_icon('up', $up_class) . trcppr_search_results_icon('down', $down_class) . '</span></a>';
    }
}
?>
<style>
    .tr-search-results-admin{--spacing:.25rem;box-sizing:border-box;margin:-10px 0 0 -20px;min-height:calc(100vh - 32px);color:#111827;font-size:14px}.tr-search-results-admin *,.tr-search-results-admin *:before,.tr-search-results-admin *:after{box-sizing:border-box}.tr-search-results-admin h1,.tr-search-results-admin h4,.tr-search-results-admin p{margin:0}.tr-search-results-admin a{text-decoration:none}.tr-search-results-admin button,.tr-search-results-admin input{font:inherit;letter-spacing:inherit}.tr-search-results-admin svg{display:block}.tr-search-results-admin .flex{display:flex}.tr-search-results-admin .inline-flex{display:inline-flex}.tr-search-results-admin .grid{display:grid}.tr-search-results-admin .relative{position:relative}.tr-search-results-admin .absolute{position:absolute}.tr-search-results-admin .flex-1{flex:1 1 0%}.tr-search-results-admin .flex-col{flex-direction:column}.tr-search-results-admin .items-center{align-items:center}.tr-search-results-admin .items-start{align-items:flex-start}.tr-search-results-admin .justify-between{justify-content:space-between}.tr-search-results-admin .justify-center{justify-content:center}.tr-search-results-admin .gap-1\.5{gap:6px}.tr-search-results-admin .gap-2{gap:8px}.tr-search-results-admin .gap-3{gap:12px}.tr-search-results-admin .gap-5{gap:20px}.tr-search-results-admin .gap-6{gap:24px}.tr-search-results-admin .space-y-1\.5>:not(:last-child){margin-bottom:6px}.tr-search-results-admin .space-y-6>:not(:last-child){margin-bottom:24px}.tr-search-results-admin .overflow-auto{overflow:auto}.tr-search-results-admin .overflow-hidden{overflow:hidden}.tr-search-results-admin .overflow-x-auto{overflow-x:auto}.tr-search-results-admin .w-3{width:12px}.tr-search-results-admin .w-4{width:16px}.tr-search-results-admin .w-5{width:20px}.tr-search-results-admin .w-8{width:32px}.tr-search-results-admin .w-full{width:100%}.tr-search-results-admin .h-3{height:12px}.tr-search-results-admin .h-4{height:16px}.tr-search-results-admin .h-5{height:20px}.tr-search-results-admin .h-9{height:36px}.tr-search-results-admin .min-w-0{min-width:0}.tr-search-results-admin .max-w-\[180px\]{max-width:180px}.tr-search-results-admin .p-4{padding:16px}.tr-search-results-admin .px-2\.5{padding-left:10px;padding-right:10px}.tr-search-results-admin .px-3{padding-left:12px;padding-right:12px}.tr-search-results-admin .px-4{padding-left:16px;padding-right:16px}.tr-search-results-admin .px-6{padding-left:24px;padding-right:24px}.tr-search-results-admin .py-1{padding-top:4px;padding-bottom:4px}.tr-search-results-admin .py-1\.5{padding-top:6px;padding-bottom:6px}.tr-search-results-admin .py-2{padding-top:8px;padding-bottom:8px}.tr-search-results-admin .py-3{padding-top:12px;padding-bottom:12px}.tr-search-results-admin .pt-6{padding-top:24px}.tr-search-results-admin .pb-16{padding-bottom:64px}.tr-search-results-admin .pl-9{padding-left:36px}.tr-search-results-admin .mt-5{margin-top:20px}.tr-search-results-admin .ml-1{margin-left:4px}.tr-search-results-admin .mb-1{margin-bottom:4px}.tr-search-results-admin .-mb-1{margin-bottom:-4px}.tr-search-results-admin .left-3{left:12px}.tr-search-results-admin .top-1\/2{top:50%}.tr-search-results-admin .-translate-y-1\/2{transform:translateY(-50%)}.tr-search-results-admin .rounded-md{border-radius:6px}.tr-search-results-admin .rounded-lg{border-radius:8px}.tr-search-results-admin .rounded-xl{border-radius:12px}.tr-search-results-admin .rounded-full{border-radius:999px}.tr-search-results-admin .border{border:1px solid #e5e7eb}.tr-search-results-admin .border-b{border-bottom:1px solid #e5e7eb}.tr-search-results-admin .border-t{border-top:1px solid #f3f4f6}.tr-search-results-admin .border-gray-100{border-color:#f3f4f6}.tr-search-results-admin .border-gray-200{border-color:#e5e7eb}.tr-search-results-admin .bg-\[\#f8f9fa\]{background:#f8f9fa}.tr-search-results-admin .bg-\[\#D83D00\]{background:#D83D00}.tr-search-results-admin .bg-card,.tr-search-results-admin .bg-white{background:#fff}.tr-search-results-admin .bg-gray-50{background:#f9fafb}.tr-search-results-admin .bg-green-100{background:#dcfce7}.tr-search-results-admin .bg-amber-100{background:#fef3c7}.tr-search-results-admin .bg-input-background{background:#f8f9fb}.tr-search-results-admin .text-left{text-align:left}.tr-search-results-admin .text-xs{font-size:12px;line-height:16px}.tr-search-results-admin .text-sm{font-size:14px;line-height:20px}.tr-search-results-admin .text-base{font-size:16px;line-height:24px}.tr-search-results-admin .text-2xl{font-size:24px;line-height:32px}.tr-search-results-admin .font-medium{font-weight:500}.tr-search-results-admin .font-semibold{font-weight:600}.tr-search-results-admin .font-normal{font-weight:400}.tr-search-results-admin .font-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}.tr-search-results-admin .leading-none{line-height:1}.tr-search-results-admin .text-\[\#3C3D59\]{color:#3C3D59}.tr-search-results-admin .text-\[\#D83D00\]{color:#D83D00}.tr-search-results-admin .text-white{color:#fff}.tr-search-results-admin .text-gray-400{color:#9ca3af}.tr-search-results-admin .text-gray-500{color:#6b7280}.tr-search-results-admin .text-gray-600{color:#4b5563}.tr-search-results-admin .text-gray-700{color:#374151}.tr-search-results-admin .text-gray-800{color:#1f2937}.tr-search-results-admin .text-green-700{color:#15803d}.tr-search-results-admin .text-amber-700{color:#b45309}.tr-search-results-admin .whitespace-nowrap{white-space:nowrap}.tr-search-results-admin .select-none{user-select:none}.tr-search-results-admin .cursor-pointer{cursor:pointer}.tr-search-results-admin .shadow-card{box-shadow:0 1px 2px rgba(0,0,0,.04)}.tr-search-results-admin .transition-all,.tr-search-results-admin .transition-colors{transition-duration:.15s;transition-timing-function:cubic-bezier(.4,0,.2,1)}.tr-search-results-admin .transition-colors{transition-property:color,background-color,border-color}.tr-search-results-admin .opacity-40{opacity:.4}.tr-search-results-admin .opacity-100{opacity:1}.tr-search-results-admin .divide-y>:not(:last-child){border-bottom:1px solid #f3f4f6}.tr-search-results-admin .hover\:bg-\[\#c03600\]:hover{background:#c03600}.tr-search-results-admin .hover\:bg-gray-50:hover{background:#f9fafb}.tr-search-results-admin .hover\:bg-green-200:hover{background:#bbf7d0}.tr-search-results-admin .hover\:text-gray-700:hover{color:#374151}.tr-search-results-admin .focus-visible\:ring-\[3px\]:focus-visible{box-shadow:0 0 0 3px rgba(216,61,0,.25);outline:none}.tr-search-results-admin table{border-collapse:collapse}.tr-search-results-admin input{border-width:1px;box-shadow:none;margin:0}.tr-search-results-admin input::placeholder{color:#6b7280}.tr-search-results-admin .tr-sr-input svg{pointer-events:none}.tr-search-results-admin .tr-sr-input input{height:36px;line-height:20px;padding-top:7px;padding-bottom:7px;padding-left:36px}@media (min-width:640px){.tr-search-results-admin .sm\:p-6{padding:24px}}@media (min-width:768px){.tr-search-results-admin .md\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.tr-search-results-admin .md\:text-sm{font-size:14px;line-height:20px}}@media (min-width:1024px){.tr-search-results-admin .lg\:p-8{padding:32px}}
    .tr-search-results-admin .block{display:block}.tr-search-results-admin .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tr-search-results-admin .text-card-foreground{color:#1a1a1a}.tr-search-results-admin [data-slot="card-header"]{padding-top:24px}.tr-search-results-admin [data-slot="card-title"]{font-size:16px;font-weight:500;color:#1a1a1a}.tr-search-results-admin table.w-full{width:100%;font-size:13px;line-height:18px}.tr-search-results-admin table.w-full th{font-size:13px!important;line-height:18px!important;font-weight:600!important;color:#374151!important}.tr-search-results-admin table.w-full td{font-size:13px!important;line-height:18px!important}.tr-search-results-admin table.w-full .text-xs{font-size:12px!important;line-height:16px!important}.tr-search-results-admin .tr-sr-input{height:36px}.tr-search-results-admin .tr-sr-input svg{position:absolute;top: 26px !important;transform:translateY(-50%)!important}.tr-search-results-admin .tr-viewing-pill{min-height:32px;max-width:50%;white-space:normal}.tr-search-results-admin .tr-viewing-pill span{font-size:13px;line-height:18px}
</style>
<div class="tr-search-results-admin">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6 pb-16">
            <h1 class="text-2xl font-semibold text-[#3C3D59]">Search Results</h1>
            <?php if ($can_search_patient_results) { ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="grid items-start gap-1.5 px-6 pt-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><?php echo trcppr_search_results_icon('search', 'w-5 h-5 text-[#D83D00]'); ?>Search Patient Records</h4>
                    </div>
                    <div data-slot="card-content" class="px-6" style="padding-bottom:24px">
                        <form method="get" id="searchform">
                            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                            <input type="hidden" name="patient_id" id="patient_id" />
                            <div class="grid md:grid-cols-3 gap-5">
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600" for="search-name">Patient Name</label>
                                    <div class="tr-sr-input relative"><?php echo trcppr_search_results_icon('user', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="patient_auto border flex h-9 w-full min-w-0 rounded-md px-3 py-1 text-base bg-input-background outline-none md:text-sm pl-9" id="search-name" placeholder="Last, First" value="<?php echo esc_attr($patientname); ?>" /></div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600" for="search-accession">Accession Number</label>
                                    <div class="tr-sr-input relative"><?php echo trcppr_search_results_icon('hash', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="border flex h-9 w-full min-w-0 rounded-md px-3 py-1 text-base bg-input-background outline-none md:text-sm pl-9" name="byassen" id="byassen" placeholder="e.g. ILS12934632" value="<?php echo esc_attr($byassen); ?>" /></div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600" for="search-phone">Phone Number</label>
                                    <div class="tr-sr-input relative"><?php echo trcppr_search_results_icon('phone', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="border flex h-9 w-full min-w-0 rounded-md px-3 py-1 text-base bg-input-background outline-none md:text-sm pl-9" name="byphone" id="byphone" placeholder="e.g. 3136223133" value="<?php echo esc_attr($byphone); ?>" /></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 mt-5">
                                <button type="submit" class="justify-center whitespace-nowrap rounded-md text-sm font-medium transition-all h-9 px-4 py-2 bg-[#D83D00] hover:bg-[#c03600] text-white flex items-center gap-2"><?php echo trcppr_search_results_icon('search'); ?>Search</button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ci_patient_results')); ?>" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"><?php echo trcppr_search_results_icon('x'); ?>Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    var $cur_year = '<?php echo date_i18n('Y') ?>';
                    jQuery(function($) {
                        $('.patient_auto').autocomplete({
                            source: ajaxurl + '?action=ajax&autocomplete=patient_auto&btype=all',
                            minLength: 1,
                            select: function(event, ui) {
                                if (ui.item) {
                                    $('input#patient_id').val(ui.item.id).data('name', ui.item.value);
                                    $('#searchform').submit();
                                }
                            }
                        });
                    });
                </script>
            <?php } ?>

    <?php Tr_Session_Class::show() ?>
    <?php
    $options['below_tabs'] = isset($options['below_tabs']) ? $options['below_tabs'] : '';
    echo '<div class="text_below_tabs" style="display:none">';
    echo apply_filters('the_content', stripslashes($options['below_tabs']));
    echo '</div>';

    $patient_ids = array($patient_id);
    //find duplicate by phone
    $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id}");

    if ($patient) {
        $phones = tr_patient_get_phone_arr($patient->pa_phone);
        $phones = implode("','", $phones);
    } else {
        $phones = '';
    }


    if ($check_duplicatep && $patient && !empty($patient->pa_phone) && strlen($patient->pa_phone) > 9 && !empty($phones)) {
        $dubpa = $wpdb->get_col($wpdb->prepare(
            "select pa_ID from ci_patients
                    where 
                    (
                        (pa_first= %s and pa_last = %s)
                        OR (pa_first= %s and pa_last = %s)
                    )
                     and pa_dob = %s
                     and pa_phone in ('{$phones}') 
                     and pa_ID != {$patient_id} ",
            $patient->pa_first,
            $patient->pa_last,
            $patient->pa_last,
            $patient->pa_first,
            $patient->pa_dob
        ));


        if (count($dubpa) > 0) {
            $patient_ids = array_merge($patient_ids, $dubpa);
        }
    }
    $patient_ids = implode(',', $patient_ids);

    $where_patient = " ba.patient_id in ({$patient_ids}) ";

    if (isset($options['patient_m5china']) && $options['patient_m5china']) {
        $pachinas = $wpdb->get_col("select pa_ID from ci_patients
                    where pa_phone in ('{$phones}') 
                     and pa_ID not in ( {$patient_ids} ) ");

        if (count($pachinas) >= 4) {
            $pachinas = implode(',', $pachinas);
            $where_patient .= " or (ba.patient_id in ({$pachinas}) and ba.patient_portal_type = 'China') ";
        }
    }

    $where = " ( {$where_patient} ) ";

    $where .= " and ba.batch_status !='archived' ";

    $sql_total = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $table_batch = tr_pdd_get_table_by_btype($btype);
        $table_report = tr_pdd_get_table_report_by_btype($btype);

        $sql_total[] = "(select count(*) as count from {$table_batch} ba 
	                left join {$table_report} re ON re.patient_id = ba.assen
	                where {$where})";
    }
    $sql_total = implode(' UNION ALL ', $sql_total);
    $sql_total = "select sum(tt.count) from ({$sql_total}) tt";


    $row_per_page = 20;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();
    $total = $wpdb->get_var($sql_total);
    //var_dump($wpdb->last_error);
    $pagination->setTotal($total);


    $sql = array();
    $sql_no_result = array();
    $btypes = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $where_covid = '';
        if ($options['only_covid']) {
            if ($btype == 'pcr') {
                $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
                $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
            } else if ($btype == 'blood') {
                $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='blood' 
                                and tg_is_covid = 1");
                $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
            } else {
                continue;
            }
        }
        $table_batch = tr_pdd_get_table_by_btype($btype);
        $table_report = tr_pdd_get_table_report_by_btype($btype);

        $sql[] = "(select 
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
                    '{$btype}' as btype
                    
                    from {$table_batch} ba
                    left join {$table_report} re On re.patient_id = ba.assen
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    where {$where} {$where_covid}
                 )";
    }
    $sql = implode(' UNION ALL ', $sql);


    $sql_order_by = isset($result_sort_map[$result_sort]) ? $result_sort_map[$result_sort] : 'tt.doc';
    $sql = "select * from ({$sql}) tt 
	    order by {$sql_order_by} {$result_order}
        limit {$offset},{$row_per_page}
        ";

    $rows = $wpdb->get_results($sql);

    //get last result date
    $last_result_date = '';
    $no_results = array();
    $has_result = false;
    foreach ($rows as $row) {
        if ($row->has_result) {
            if ($row->doc > $last_result_date) {
                $last_result_date = $row->doc;
            }
            $has_result = true;
        } else {
            $no_results[$row->assen] = $row;
        }
    }

    if ($has_result) {
        $no_results = false;
    }


    if (isset($_GET['debug'])) {
        var_dump($patient_ids);
        echo '<br><br>';

        var_dump($wpdb->last_error);
        echo '<br>';
        var_dump($wpdb->last_query);
    }

    $steps = array();
    $steps[1] = array('title' => 'Testing Order Received', 'desc' => 'Test Requisition has been received!');
    $steps[2] = array('title' => 'Sample Collected', 'desc' => 'Your sample it\'s on its way to the lab for processing...');
    $steps[3] = array('title' => 'Sample Received', 'desc' => 'Your sample has been received by the lab...');
    $steps[4] = array('title' => $btypes_str . ' Testing', 'desc' => 'Your sample is now being tested...');
    $steps[5] = array('title' => 'Testing Complete', 'desc' => 'Your Results are now available!');

    foreach ($steps as $i => $arr) {
        if (isset($options['bubble_t_' . $i]) && !empty($options['bubble_t_' . $i])) {
            $steps[$i]['title'] = $options['bubble_t_' . $i];
        }
        if (isset($options['bubble_sub_' . $i]) && !empty($options['bubble_sub_' . $i])) {
            $steps[$i]['desc'] = $options['bubble_sub_' . $i];
        }

        $steps[$i]['title'] = str_replace('#types#', $btypes_str, $steps[$i]['title']);
    }


    if ($no_results) {
        $btype_names = array();
        $received = false;
        $assens_no_results = array();
        foreach ($no_results as $r) {
            $btype_names[$r->btype] = tr_pdd_get_typename($r->btype);
            if (!$received) {
                $received = $wpdb->get_var("select ID from ci_receiving_batches 
	                    where mid = {$r->mid} and btype='{$r->btype}'");
            }
            $assens_no_results[] = $r->assen;
        }

        $step = 2;

        if ($received) {
            $step = 4;
        }
        $btypes_str = implode(', ', $btype_names);


        echo '<div class="text_above_bubbles">';
        echo apply_filters('the_content', stripslashes($options['above_bubbles']));
        echo '</div>';
    ?>
        <div class="bubbles">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                $class = '';
                if ($step >= $i) {
                    $class .= 'active';
                }
                $title_one = '';
                if ($i == 1) {
                    $title_one = implode(', ', $assens_no_results);
                }
            ?>
                <div class="bubble <?php echo $class ?>">
                    <div class="bwrap">
                        <div class="circle" title="<?php echo $title_one ?>"><span class="step"><?php echo $i ?></span></div>
                        <div class="tt"><?php echo $steps[$i]['title'] ?></div>
                        <?php if ($options['hide_desc'] != '1'): ?>
                            <div class="desc"><?php echo $steps[$i]['desc'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            }
            ?>
        </div>
    <?php
        echo '<div class="text_below_bubbles">';
        echo apply_filters('the_content', stripslashes($options['below_bubbles']));
        echo '</div>';
    }


    if (isset($_GET['archive']) && $_GET['archive']) {
    ?>
        <div style="text-align: right;"><a class="button" href="<?php Tr_Base_Class_V6::link(array('archive' => '')) ?>">New Results</a></div>
    <?php
    } else if ($no_results && $options['archive_result'] && count($rows) > 0) {
        $rows = array();
    ?>
        <div style="text-align: right;margin:5px 0"><a class="button" href="<?php Tr_Base_Class_V6::link(array('archive' => 1)) ?>">Archive Results</a></div>
    <?php
    }

    ?>
    <div id="main_results" class="<?php if (count($rows) == 0) echo 'nodatars' ?>">
        <?php if (isset($pagination) && $total > $row_per_page) { ?><div class="tablenav topnav"><?php echo $pagination->pagination(array('m')) ?></div><?php } ?>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
            <div data-slot="card-header" class="grid items-start gap-1.5 px-6 pt-6">
                <div class="flex items-center justify-between gap-3">
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><?php echo trcppr_search_results_icon('flask', 'w-5 h-5 text-[#D83D00]'); ?>Results<span class="ml-1 text-sm font-normal text-gray-500">(<?php echo esc_html((int) $total); ?> records)</span></h4>
                    <?php if (!empty($patientname)) { ?>
                        <div class="tr-viewing-pill flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                            <?php echo trcppr_search_results_icon('user', 'w-4 h-4 text-[#D83D00]'); ?><span>Viewing: <span class="font-medium text-[#3C3D59]"><?php echo esc_html($patientname); ?></span></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 w-8">#</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none whitespace-nowrap"><?php echo trcppr_search_results_sort_link('assen', 'Accession', $result_sort, $result_order); ?></th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Patient Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">DOB</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Clinic</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Doctor</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Test Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none whitespace-nowrap"><?php echo trcppr_search_results_sort_link('collect_date', 'Collect Date', $result_sort, $result_order); ?></th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none whitespace-nowrap"><?php echo trcppr_search_results_sort_link('result_date', 'Result Date', $result_sort, $result_order); ?></th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none"><?php echo trcppr_search_results_sort_link('status', 'Status', $result_sort, $result_order); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        if (count($rows) > 0) {
                            foreach ($rows as $i => $row) {
                            $row->pa_passport = str_replace(array('-', '.', ' '), '', $row->pa_passport);
                            $has_passport = strlen($row->pa_passport) >= 8;
                            if (str_replace('0', '', $row->pa_passport) === '') {
                                $has_passport = false;
                            }

                            $pdflink = '';
                            if ($row->id > 0 && $row->estatus == 'C') {
                                $file = $row->btype . '_' . trpdd_patient_encode($row->id) . '_' . $user_id . '.pdf';
                                $pdflink = Tr_Base_Class_V4::link(array('tr_action' => 'patient_result_pdf', 'file' => $file), false);

                                if (isset($options['mms_notify']) && $options['mms_notify'] && $has_passport) {
                                    $pdflink .= '&hqr';
                                }
                            }




                            $panel = array();
                            foreach (explode(',', $row->panel_tests_requested) as $t) {
                                if (stripos($t, 'group') === false) {
                                } else {
                                    $group_id = str_ireplace('group_', '', $t);

                                    if (isset($all_groups[$group_id])) {
                                        $panel[] = $all_groups[$group_id]->tg_name;
                                    }
                                }
                            }

                            if (empty($panel) && $row->btype == 'tox') {
                                $panel[] = 'Tox';
                            }

                            $class = '';
                            $result_time = strtotime($row->approve_date);
                            if (!empty($options['ew_1week']) && $today_time - $result_time > 86400 * 7) {
                                $class .= ' warning_1week ';
                            }

                    ?>
                            <tr class="childp hover:bg-gray-50 transition-colors <?php echo esc_attr($class); ?>">
                                <td class="px-4 py-3 text-gray-400 text-xs" data-colname="ID"><?php echo esc_html($total - ($offset) - $i); ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-[#3C3D59] font-medium whitespace-nowrap" data-colname="Accession"><?php echo esc_html($row->assen); ?></td>
                                <td class="px-4 py-3 text-gray-800 whitespace-nowrap" data-colname="Patient Name"><?php echo esc_html($row->pa_last . ', ' . $row->pa_first); ?></td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs" data-colname="Patient DOB"><?php echo esc_html($row->pa_dob); ?></td>
                                <td class="px-4 py-3 text-gray-700" data-colname="Clinic"><?php echo esc_html($row->clinic); ?></td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap" data-colname="Doctor"><?php echo esc_html($row->doctor); ?></td>
                                <td class="px-4 py-3 text-gray-700 max-w-[180px]" data-colname="Type"><span class="block truncate" title="<?php echo esc_attr(implode(', ', $panel)); ?>"><?php echo esc_html(implode(', ', $panel)); ?></span></td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs" data-colname="Collect Date"><?php echo esc_html(tr_pdd_display_date($row->doc, 'M d, Y')); ?></td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs" data-colname="Result Date"><?php echo $row->approve_date ? esc_html(tr_pdd_display_date($row->approve_date, 'M d, Y')) : '&mdash;'; ?></td>
                                <td class="px-4 py-3 viewlink">
                                    <?php if (!empty($pdflink)) { ?>
                                        <a class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 transition-colors" target="_blank" href="<?php echo esc_url($pdflink); ?>"><?php echo trcppr_search_results_icon('eye', 'w-3 h-3'); ?>View Result</a>
                                    <?php } else {
                                        $step = 2;
                                        if ($row->estatus == 'C') {
                                            $step = 5;
                                        } else if ($row->id > 0) {
                                            $step = 4;
                                        } else if ($row->spec_received >= $row->doc) {
                                            $step = 3;
                                        }
                                        $arr = $steps[$step];
                                        echo '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">' . esc_html($arr['title']) . '</span>';
                                    } ?>
                                </td>
                            </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr class="childp">
                            <td colspan="10" class="px-4 py-3 text-gray-500 text-sm">No Results have been Released yet!</td>
                        </tr>
                    <?php
                        }
                    ?>
                </tbody>
            </table>
        </div>
            <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-400"><?php echo esc_html((int) $total); ?> records found</div>
        </div>
        <?php if (isset($pagination) && $total > $row_per_page) { ?><div class="tablenav"><?php echo $pagination->pagination(array('m')) ?></div><?php } ?>
    </div>

    <?php
    if ($is_kid) {
    ?>
        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'removekid', 'id' => $patient_id)) ?>">Remove this Member</a>
    <?php
    }
    ?>
</div>
</div>
</div>

<?php
if ($options['ew_1week']) {

    wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


    wp_enqueue_script('jquery-ui');
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');
?>
    <div id="dialog_warning_empty" style="display: none;">
        <div style="color:red">IMPORTANT: The results you are about to access are more than 1 week old,
            please ensure you accessing your most recent result as referenced by the Date column.</div>
    </div>

    <script>
        jQuery(function($) {
            $('body').on('click', 'tr.warning_1week .viewlink a', function() {
                var $this = $(this);
                $("#dialog_warning_empty").dialog({
                    modal: true,
                    title: 'Warning',
                    width: 400,
                    dialogClass: 'receivingwarning',
                    buttons: {
                        "I Understand!": function() {
                            location.href = $this.attr('href');
                            $(this).dialog("close");
                        }
                    }
                });
                return false;
            });
        })
    </script>
<?php
}
