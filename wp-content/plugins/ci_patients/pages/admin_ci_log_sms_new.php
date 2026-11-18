<?php
global $wpdb, $tr_sms_gateway;

if (isset($_REQUEST['sms_result'])) {
    trcppr_cron_notify_sms();
}

if ($act == 'resend') {
    $return = array('success' => 0);
    $slugkey = $_REQUEST['slugkey'];
    list($phone, $created, $sendtype) = explode('_', $slugkey);
    $sql = "select * from tr_log_sms 
            where phone = '{$phone}'
                and created ='{$created}'
                and sendtype = '{$sendtype}' ";
    $row = $wpdb->get_row($sql);

    if ($row) {
        $btype = '';
        $report_id = 0;

        foreach (tr_pdd_get_batch_types() as $btype) {
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);
            $report_id = $wpdb->get_var(
                "select re.id from {$report_tbl} re
                                where re.patient_id = '{$row->assen}'
                                "
            );
            if ($report_id > 0) {
                break;
            }
        }

        $loaded = $tr_sms_gateway->load_library();

        if ($loaded) {
            $init = $tr_sms_gateway->init_api();
        }

        if ($row->sendtype == 'nt') {
            $sent = $tr_sms_gateway->send_sms($row->phone, $row->message, $row->patient_name, $row->assen, $row->sendtype, $row->patient_id);
        } else if ($row->sendtype == 'ms' && $report_id > 0) {
            $url = tr_patient_qr_url_report($report_id, $btype);
            $sent = $tr_sms_gateway->send_sms($row->phone, $row->message, $row->patient_name, $row->assen, $row->sendtype, $row->patient_id, array($url));
        }

        $return['sendtype'] = $row->sendtype;

        if ($sent) {
            $return['success'] = 1;
        }
    }

    wp_send_json($return);
    exit;
}

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui-datepicker', TRCPPR_URL . 'css/jquery-ui-datepicker.css', array(), '1.8');

$settingsmain = get_blog_option(BLOG_ID_CURRENT_SITE, 'ci_pdf_data', array());
$keyassen = $settingsmain['barcode_key'];

$stypes = array(
    'n' => 'Welcome',
    'v' => 'Verify Code',
    'nt' => 'Results',
    'ms' => 'MMS',
    'f' => 'Failed',
);

$where = '';
$join_total = '';
if (!empty($_REQUEST['patient'])) {
    $where .= " and patient_name like '%{$_REQUEST['patient']}%' ";
}

if (!empty($_REQUEST['assen'])) {
    $where .= " and (assen like '%{$_REQUEST['assen']}%' 
	                
	                 )";
    //or (assen = '' and sendtype in ('nt','ms') and message like '%{$_REQUEST['assen']}%')
}
if (!empty($_REQUEST['phone'])) {
    $phone = trim($_REQUEST['phone']);
    $phone = str_replace(array('+1', '-', ' ', '.', '(', ')'), '', $phone);
    $phones = array($phone);
    $phones[] = '+1' . $phone;
    $phones = implode("','", $phones);
    $where .= " and phone in ('{$phones}') ";
}


if (!empty($_GET['datefrom'])) {
    $datefrom = date('Y-m-d', strtotime($_GET['datefrom']));
    $where .= " and sms.created >= '{$datefrom}' ";
    if (empty($_GET['dateto'])) {
        $_GET['dateto'] = $_GET['datefrom'];
    }
}
if (!empty($_GET['dateto'])) {
    $dateto = date('Y-m-d', strtotime($_GET['dateto']));
    $where .= " and sms.created <= '{$dateto} 23:59:59' ";
}

if (isset($_GET['cid']) && $_GET['cid'] > 0) {
    $cid = $_GET['cid'];
    $where .= " and pa.pa_clinic_ID = {$cid} ";

    $join_total .= "left join {$wpdb->usermeta} ump ON ump.meta_key='phone' 
                                        and ump.meta_value=sms.phone
	 left join {$wpdb->users} u ON u.ID = ump.user_id
	 left join ci_patients pa ON pa.pa_ID = u.patient_id or pa.pa_ID = sms.patient_id
	             ";
}




if (isset($_GET['stype']) && !empty($_GET['stype'])) {
    $stype = $_GET['stype'];
    if ($stype == 'n') {
        $stype = "n','no";
    } else if ($stype == 'nt') {
        $stype = "nt','ms";
    }
    $where .= " and sms.sendtype in ('{$stype}') ";
} else {
    $where .= " and sms.sendtype not in ('f') ";
}

$sms_sort = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'created';
$sms_order = isset($_REQUEST['order']) && strtolower($_REQUEST['order']) == 'asc' ? 'asc' : 'desc';
$sms_sort_map = array(
    'created' => 'sms.created',
    'patient_name' => 'sms.patient_name',
    'phone' => 'sms.phone',
    'assen' => 'sms.assen',
    'sendtype' => 'sms.sendtype',
);
if (!isset($sms_sort_map[$sms_sort])) {
    $sms_sort = 'created';
}
$order_by = "order by {$sms_sort_map[$sms_sort]} {$sms_order}";


$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$total = $wpdb->get_var("
                    select count(*) 
                    from tr_log_sms sms 
                    {$join_total}
                    where 1 {$where}
                    ");

$pagination->setTotal($total);
$sql = "
	select 
	 sms.*,
	 pa.pa_ID,
	 pa.pa_first,
	 pa.pa_last
	 from tr_log_sms sms
	 left join ci_patients pa ON  pa.pa_ID = sms.patient_id     
	 where 1 {$where}
	{$order_by}";

//left join {$wpdb->usermeta} ump ON ump.meta_key='phone' 
//and ump.meta_value=sms.phone
//left join {$wpdb->users} u ON u.ID = ump.user_id
//pa.pa_ID = u.patient_id or

if (empty($act) || $act != 'export') {
    $sql .= " limit {$offset},{$row_per_page} ";
}

//var_dump($sql);exit;

$rows = $wpdb->get_results($sql);

if ($act == 'export') {
    $filename = 'export.csv';
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/csv");

    $f = fopen('php://output', 'w');
    $headers = array('Date/Time', 'Patient Name', 'Phone Number', 'Accession', 'Scheduled', 'Message');
    fputcsv($f, $headers);

    $duplicates = array();
    foreach ($rows as $row) {
        if (empty($row->pa_last)) {
            $phones = implode("','", tr_patient_get_phone_arr($row->phone));

            $patient = $wpdb->get_row("select pa_first,pa_last from ci_patients 
					            where pa_phone in ('{$phones}')");
            if ($patient) {
                $row->pa_last = $patient->pa_last;
                $row->pa_first = $patient->pa_first;
            }
        }



        preg_match_all("/([a-zA-Z]{3} [0-9]+ @ [0-9]+:[0-9]+[A|P|M]+)/", $row->message, $matches_scheduled);

        if (empty($row->assen)) {
            preg_match_all("/({$keyassen}[0-9]+)/", $row->message, $matches);
            $row->assen = implode(', ', $matches[1]);
        }

        if (empty($row->patient_name)) {
            $row->patient_name = $row->pa_last . ', ' . $row->pa_first;
            $wpdb->update('tr_log_sms', array(
                'patient_name' => $row->patient_name,
                'assen' => $row->assen
            ), array(
                'phone' => $row->phone,
                'created' => $row->created
            ));
        }
        $key_duplicate = $row->phone . '_' . $row->created . '_' . $row->sendtype . '_' . $row->assen;
        if (in_array($key_duplicate, $duplicates)) {
            continue;
        }
        $duplicates[] = $key_duplicate;

        $data = array(
            $row->created,
            $row->patient_name,
            $row->phone,
            $row->assen,
            implode(', ', $matches_scheduled[1]),
            $row->message,
        );
        fputcsv($f, $data);
    }
    exit;
}
if (isset($_GET['debug'])) {
    var_dump($sql);
}

$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where cli_status = 1 order by cli_clinic asc");

if (!function_exists('trcppr_sms_log_icon')) {
    function trcppr_sms_log_icon($name, $classes = 'w-4 h-4')
    {
        $icons = array(
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line>',
            'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            'hash' => '<line x1="4" x2="20" y1="9" y2="9"></line><line x1="4" x2="20" y1="15" y2="15"></line><line x1="10" x2="8" y1="3" y2="21"></line><line x1="16" x2="14" y1="3" y2="21"></line>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
            'calendar' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path>',
            'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
        );
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . esc_attr($classes) . '">' . ($icons[$name] ?? '') . '</svg>';
    }
}
if (!function_exists('trcppr_sms_log_sort_link')) {
    function trcppr_sms_log_sort_link($key, $label, $current_sort, $current_order)
    {
        $next_order = ($current_sort == $key && $current_order == 'asc') ? 'desc' : 'asc';
        $url = add_query_arg(array('orderby' => $key, 'order' => $next_order));
        return '<a class="text-gray-700" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
}
?>

<div class="wrap" style="display:none">
    <?php Tr_Session_Class::show() ?>

    <form method="get" class="formsearch">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <label>Patient: </label>
        <input type="search" name="patient" value="<?php echo $_GET['patient'] ?>" />
        <label>Accession: </label>
        <input type="search" name="assen" value="<?php echo $_GET['assen'] ?>" />
        <label>Phone: </label>
        <input type="search" name="phone" value="<?php echo $_GET['phone'] ?>" />
        <label>Type:</label>
        <select name="stype">
            <option value="">All Type</option>
            <?php
            foreach ($stypes as $k => $vl) {
                $selected = $k == $_GET['stype'] ? 'selected' : '';
                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
            }
            ?>
        </select>
        <br>
        <label>Date From: </label>
        <input type="search" class="hasdate" name="datefrom" value="<?php echo $_GET['datefrom'] ?>" />
        <label>Date To: </label>
        <input type="search" class="hasdate" name="dateto" value="<?php echo $_GET['dateto'] ?>" />
        <label>Clinic:</label>
        <select name="cid">
            <option value="">Select</option>
            <?php
            foreach ($clinics as $clinic) {
                $selected = $clinic->cID == $_GET['cid'] ? 'selected' : '';
                echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
            }
            ?>
        </select>

        <span style="display: inline-block;padding-left:70px">
            <input type="submit" class="button-primary" value="Search" />
            <input type="button" class="button export_btn" value="Export" />
        </span>
    </form>
    <div class="tablenav">
        <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
    </div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
            <thead>
                <tr>
                    <?php tr_tbl_sort('created', 'Date/Time') ?>
                    <?php tr_tbl_sort('patient_name', 'Patient Name') ?>
                    <?php tr_tbl_sort('phone', 'Phone Number') ?>
                    <th style="width: 100px">Accession</th>
                    <th style="width: 100px">scheduled</th>
                    <th>Text</th>
                    <td style="width:80px"></td>
                </tr>
            </thead>

            <tbody>
                <?php
                $duplicates = array();
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                        if (empty($row->pa_last)) {
                            $phones = implode("','", tr_patient_get_phone_arr($row->phone));
                            $patient = null;
                            if (!empty($phones)) {
                                $patient = $wpdb->get_row("select pa_first,pa_last from ci_patients 
                            where pa_phone in ('{$phones}')");
                            }


                            if ($patient) {
                                $row->pa_last = $patient->pa_last;
                                $row->pa_first = $patient->pa_first;
                            }
                        }



                        preg_match_all("/([a-zA-Z]{3} [0-9]+ @ [0-9]+:[0-9]+[A|P|M]+)/", $row->message, $matches_scheduled);

                        if (empty($row->assen)) {
                            preg_match_all("/({$keyassen}[0-9]+)/", $row->message, $matches);
                            $row->assen = implode(', ', $matches[1]);
                        }

                        if (empty($row->patient_name)) {
                            $row->patient_name = $row->pa_last . ', ' . $row->pa_first;
                            $wpdb->update('tr_log_sms', array(
                                'patient_name' => $row->patient_name,
                                'assen' => $row->assen
                            ), array(
                                'phone' => $row->phone,
                                'created' => $row->created
                            ));
                        }
                        $key_duplicate = $row->phone . '_' . $row->created . '_' . $row->sendtype . '_' . $row->assen;
                        if (in_array($key_duplicate, $duplicates)) {
                            continue;
                        }
                        $duplicates[] = $key_duplicate;
                ?>
                        <tr class="childp">
                            <td><?php echo $row->created ?></td>
                            <td title="<?php echo $row->pa_ID ?>"><?php echo $row->patient_name ?></td>
                            <td><?php echo $row->phone ?></td>
                            <td><?php echo $row->assen ?></td>
                            <td><?php echo implode(', ', $matches_scheduled[1]) ?></td>
                            <td><?php echo $row->message ?></td>
                            <td>
                                <?php if (in_array($row->sendtype, array('nt', 'ms', 'n', 'no'))) {
                                ?>
                                    <a class="resend button" data-slugkey="<?php echo $key_duplicate ?>">Resend</a>
                                <?php
                                } ?>
                            </td>
                        </tr>
                    <?php
                    }
                } else {
                    ?>
                    <tr class="childp">
                        <td colspan="6">Not found</td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
</div>
<style>
    .tr-sms-log-admin{box-sizing:border-box;margin:-10px 0 0 -20px;min-height:calc(100vh - 32px);color:#111827;font-size:14px}.tr-sms-log-admin *,.tr-sms-log-admin *:before,.tr-sms-log-admin *:after{box-sizing:border-box}.tr-sms-log-admin h1,.tr-sms-log-admin h4,.tr-sms-log-admin p{margin:0}.tr-sms-log-admin a{text-decoration:none}.tr-sms-log-admin button,.tr-sms-log-admin input,.tr-sms-log-admin select{font:inherit;letter-spacing:inherit}.tr-sms-log-admin svg{display:block}.tr-sms-log-admin .flex{display:flex}.tr-sms-log-admin .inline-flex{display:inline-flex}.tr-sms-log-admin .grid{display:grid}.tr-sms-log-admin .relative{position:relative}.tr-sms-log-admin .absolute{position:absolute}.tr-sms-log-admin .flex-1{flex:1 1 0%}.tr-sms-log-admin .flex-col{flex-direction:column}.tr-sms-log-admin .flex-wrap{flex-wrap:wrap}.tr-sms-log-admin .items-center{align-items:center}.tr-sms-log-admin .items-end{align-items:flex-end}.tr-sms-log-admin .justify-between{justify-content:space-between}.tr-sms-log-admin .justify-center{justify-content:center}.tr-sms-log-admin .gap-1\.5{gap:6px}.tr-sms-log-admin .gap-2{gap:8px}.tr-sms-log-admin .gap-4{gap:16px}.tr-sms-log-admin .gap-6{gap:24px}.tr-sms-log-admin .space-y-1\.5>:not(:last-child){margin-bottom:6px}.tr-sms-log-admin .space-y-4>:not(:last-child){margin-bottom:16px}.tr-sms-log-admin .space-y-6>:not(:last-child){margin-bottom:24px}.tr-sms-log-admin .overflow-auto{overflow:auto}.tr-sms-log-admin .overflow-hidden{overflow:hidden}.tr-sms-log-admin .overflow-x-auto{overflow-x:auto}.tr-sms-log-admin .w-4{width:16px}.tr-sms-log-admin .w-5{width:20px}.tr-sms-log-admin .w-full{width:100%}.tr-sms-log-admin .h-4{height:16px}.tr-sms-log-admin .h-5{height:20px}.tr-sms-log-admin .h-9{height:36px}.tr-sms-log-admin .min-w-0{min-width:0}.tr-sms-log-admin .max-w-\[260px\]{max-width:260px}.tr-sms-log-admin .p-4{padding:16px}.tr-sms-log-admin .px-2{padding-left:8px;padding-right:8px}.tr-sms-log-admin .px-3{padding-left:12px;padding-right:12px}.tr-sms-log-admin .px-4{padding-left:16px;padding-right:16px}.tr-sms-log-admin .px-6{padding-left:24px;padding-right:24px}.tr-sms-log-admin .py-0\.5{padding-top:2px;padding-bottom:2px}.tr-sms-log-admin .py-1{padding-top:4px;padding-bottom:4px}.tr-sms-log-admin .py-2{padding-top:8px;padding-bottom:8px}.tr-sms-log-admin .pt-1{padding-top:4px}.tr-sms-log-admin .pt-6{padding-top:24px}.tr-sms-log-admin .pb-6{padding-bottom:24px}.tr-sms-log-admin .pb-16{padding-bottom:64px}.tr-sms-log-admin .pl-9{padding-left:36px}.tr-sms-log-admin .ml-1{margin-left:4px}.tr-sms-log-admin .left-3{left:12px}.tr-sms-log-admin .top-1\/2{top:50%}.tr-sms-log-admin .-translate-y-1\/2{transform:translateY(-50%)}.tr-sms-log-admin .rounded-md{border-radius:6px}.tr-sms-log-admin .rounded-lg{border-radius:8px}.tr-sms-log-admin .rounded-xl{border-radius:12px}.tr-sms-log-admin .rounded-full{border-radius:999px}.tr-sms-log-admin .border{border:1px solid #e5e7eb}.tr-sms-log-admin .border-b{border-bottom:1px solid #e5e7eb}.tr-sms-log-admin .border-t{border-top:1px solid #e5e7eb}.tr-sms-log-admin .border-gray-200{border-color:#e5e7eb}.tr-sms-log-admin .border-gray-300{border-color:#d1d5db}.tr-sms-log-admin .border-\[\#D83D00\]{border-color:#D83D00}.tr-sms-log-admin .bg-\[\#f8f9fa\]{background:#f8f9fa}.tr-sms-log-admin .bg-\[\#D83D00\]{background:#D83D00}.tr-sms-log-admin .bg-card,.tr-sms-log-admin .bg-white,.tr-sms-log-admin .bg-background{background:#fff}.tr-sms-log-admin .bg-gray-50{background:#f9fafb}.tr-sms-log-admin .bg-blue-100{background:#dbeafe}.tr-sms-log-admin .bg-input-background{background:#f8f9fb}.tr-sms-log-admin .text-left{text-align:left}.tr-sms-log-admin .text-xs{font-size:12px!important;line-height:16px!important}.tr-sms-log-admin .text-sm{font-size:14px;line-height:20px}.tr-sms-log-admin .text-2xl{font-size:24px;line-height:32px}.tr-sms-log-admin .font-medium{font-weight:500}.tr-sms-log-admin .font-semibold{font-weight:600}.tr-sms-log-admin .font-normal{font-weight:400}.tr-sms-log-admin .font-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}.tr-sms-log-admin .leading-none{line-height:1}.tr-sms-log-admin .text-\[\#3C3D59\]{color:#3C3D59}.tr-sms-log-admin .text-\[\#D83D00\]{color:#D83D00}.tr-sms-log-admin .text-white{color:#fff}.tr-sms-log-admin .text-foreground{color:#1a1a1a}.tr-sms-log-admin .text-gray-400{color:#9ca3af}.tr-sms-log-admin .text-gray-500{color:#6b7280}.tr-sms-log-admin .text-gray-600{color:#4b5563}.tr-sms-log-admin .text-gray-700{color:#374151}.tr-sms-log-admin .text-gray-800{color:#1f2937}.tr-sms-log-admin .text-blue-700{color:#1d4ed8}.tr-sms-log-admin .whitespace-nowrap{white-space:nowrap}.tr-sms-log-admin .block{display:block}.tr-sms-log-admin .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tr-sms-log-admin .shadow-card{box-shadow:0 1px 2px rgba(0,0,0,.04)}.tr-sms-log-admin .transition-all,.tr-sms-log-admin .transition-colors{transition-duration:.15s;transition-timing-function:cubic-bezier(.4,0,.2,1)}.tr-sms-log-admin .divide-y>:not(:last-child){border-bottom:1px solid #f3f4f6}.tr-sms-log-admin .hover\:bg-\[\#c03600\]:hover{background:#c03600}.tr-sms-log-admin .hover\:bg-gray-50:hover{background:#f9fafb}.tr-sms-log-admin .hover\:bg-accent:hover{background:#fef3f0}.tr-sms-log-admin .hover\:text-accent-foreground:hover{color:#D83D00}.tr-sms-log-admin .hover\:text-gray-700:hover{color:#374151}.tr-sms-log-admin .hover\:border-\[\#D83D00\]:hover{border-color:#D83D00}.tr-sms-log-admin table{border-collapse:collapse}.tr-sms-log-admin table.w-full{width:100%;font-size:12px;line-height:16px}.tr-sms-log-admin table.w-full th{font-size:12px!important;line-height:16px!important;font-weight:600!important}.tr-sms-log-admin table.w-full td{font-size:12px!important;line-height:16px!important}.tr-sms-log-admin input,.tr-sms-log-admin select{box-shadow:none!important;margin:0}.tr-sms-log-admin input::placeholder{color:#6b7280}.tr-sms-log-admin .tr-sms-input svg{pointer-events:none;position:absolute;top:50%;transform:translateY(-50%)}.tr-sms-log-admin .tr-sms-input input{height:36px;line-height:20px;padding-top:7px;padding-bottom:7px;padding-left:36px}@media (min-width:640px){.tr-sms-log-admin .sm\:p-6{padding:24px}}@media (min-width:768px){.tr-sms-log-admin .md\:grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.tr-sms-log-admin .md\:col-span-2{grid-column:span 2/span 2}}@media (min-width:1024px){.tr-sms-log-admin .lg\:p-8{padding:32px}}
</style>
<div class="tr-sms-log-admin">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6 pb-16">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-[#3C3D59]">SMS Log</h1>
                <button type="button" class="export_btn justify-center whitespace-nowrap rounded-md font-medium transition-all border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2 flex items-center gap-2 text-sm"><?php echo trcppr_sms_log_icon('download'); ?>Export</button>
            </div>
            <?php Tr_Session_Class::show() ?>
            <form method="get" class="tr-sms-search">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="grid items-start gap-1.5 px-6 pt-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><?php echo trcppr_sms_log_icon('search', 'w-5 h-5 text-[#D83D00]'); ?>Filter</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 pb-6 space-y-4">
                        <div class="grid md:grid-cols-4 gap-4">
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Patient</label><div class="tr-sms-input relative"><?php echo trcppr_sms_log_icon('user', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="border flex w-full min-w-0 rounded-md px-3 py-1 bg-input-background pl-9 h-9 text-sm" name="patient" placeholder="Last, First" value="<?php echo esc_attr($_GET['patient'] ?? ''); ?>"></div></div>
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Accession</label><div class="tr-sms-input relative"><?php echo trcppr_sms_log_icon('hash', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="border flex w-full min-w-0 rounded-md px-3 py-1 bg-input-background pl-9 h-9 text-sm" name="assen" placeholder="Accession #" value="<?php echo esc_attr($_GET['assen'] ?? ''); ?>"></div></div>
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Phone</label><div class="tr-sms-input relative"><?php echo trcppr_sms_log_icon('phone', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="border flex w-full min-w-0 rounded-md px-3 py-1 bg-input-background pl-9 h-9 text-sm" name="phone" placeholder="+1..." value="<?php echo esc_attr($_GET['phone'] ?? ''); ?>"></div></div>
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Clinic</label><select name="cid" class="w-full h-9 px-3 text-sm border border-gray-300 rounded-lg bg-white"><option value="">All Clinics</option><?php foreach ($clinics as $clinic) { ?><option value="<?php echo esc_attr($clinic->cID); ?>" <?php selected($clinic->cID, $_GET['cid'] ?? ''); ?>><?php echo esc_html($clinic->cli_clinic); ?></option><?php } ?></select></div>
                        </div>
                        <div class="grid md:grid-cols-4 gap-4 items-end">
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Date From</label><div class="tr-sms-input relative"><?php echo trcppr_sms_log_icon('calendar', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="hasdate border flex w-full min-w-0 rounded-md px-3 py-1 bg-input-background pl-9 h-9 text-sm" name="datefrom" value="<?php echo esc_attr($_GET['datefrom'] ?? ''); ?>"></div></div>
                            <div class="space-y-1.5"><label class="flex items-center gap-2 font-medium text-xs text-gray-600">Date To</label><div class="tr-sms-input relative"><?php echo trcppr_sms_log_icon('calendar', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?><input class="hasdate border flex w-full min-w-0 rounded-md px-3 py-1 bg-input-background pl-9 h-9 text-sm" name="dateto" value="<?php echo esc_attr($_GET['dateto'] ?? ''); ?>"></div></div>
                            <div class="md:col-span-2 flex items-end gap-2"><button class="justify-center whitespace-nowrap rounded-md text-sm font-medium transition-all px-4 py-2 bg-[#D83D00] hover:bg-[#c03600] text-white flex items-center gap-2 h-9" type="submit"><?php echo trcppr_sms_log_icon('search'); ?>Search</button><a href="<?php echo esc_url(admin_url('admin.php?page=ci_log_sms')); ?>" class="text-sm text-gray-500 hover:text-gray-700 transition-colors px-2 h-9 inline-flex items-center">Reset</a></div>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="submit" name="stype" value="" class="px-3 py-1 text-xs font-medium rounded-full border transition-all <?php echo empty($_GET['stype']) ? 'bg-[#D83D00] text-white border-[#D83D00]' : 'bg-white text-gray-600 border-gray-300 hover:border-[#D83D00]'; ?>">All</button>
                            <?php foreach ($stypes as $k => $vl) { ?><button type="submit" name="stype" value="<?php echo esc_attr($k); ?>" class="px-3 py-1 text-xs font-medium rounded-full border transition-all <?php echo (($_GET['stype'] ?? '') == $k) ? 'bg-[#D83D00] text-white border-[#D83D00]' : 'bg-white text-gray-600 border-gray-300 hover:border-[#D83D00]'; ?>"><?php echo esc_html($vl); ?></button><?php } ?>
                        </div>
                    </div>
                </div>
            </form>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden" style="padding-bottom: 50px;">
                <div data-slot="card-header" class="grid items-start gap-1.5 px-6 pt-6">
                    <div class="flex items-center justify-between">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><?php echo trcppr_sms_log_icon('message', 'w-5 h-5 text-[#D83D00]'); ?>Log<span class="text-sm font-normal text-gray-500 ml-1">(<?php echo esc_html((int) $total); ?> entries)</span></h4>
                    </div>
                </div>
                <?php if (isset($pagination) && $total > $row_per_page) { ?><div class="tablenav px-4"><?php echo $pagination->pagination(array('m')); ?></div><?php } ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead><tr class="border-b border-t border-gray-200 bg-gray-50"><th class="px-4 py-2 text-left font-semibold text-gray-700 whitespace-nowrap"><?php echo trcppr_sms_log_sort_link('created', 'Date / Time', $sms_sort, $sms_order); ?></th><th class="px-4 py-2 text-left font-semibold text-gray-700 whitespace-nowrap"><?php echo trcppr_sms_log_sort_link('patient_name', 'Patient', $sms_sort, $sms_order); ?></th><th class="px-4 py-2 text-left font-semibold text-gray-700 whitespace-nowrap"><?php echo trcppr_sms_log_sort_link('phone', 'Phone', $sms_sort, $sms_order); ?></th><th class="px-4 py-2 text-left font-semibold text-gray-700"><?php echo trcppr_sms_log_sort_link('assen', 'Accession', $sms_sort, $sms_order); ?></th><th class="px-4 py-2 text-left font-semibold text-gray-700"><?php echo trcppr_sms_log_sort_link('sendtype', 'Type', $sms_sort, $sms_order); ?></th><th class="px-4 py-2 text-left font-semibold text-gray-700">Scheduled</th><th class="px-4 py-2 text-left font-semibold text-gray-700">Message</th><th class="px-4 py-2"></th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                        <?php
                        $duplicates = array();
                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                if (empty($row->pa_last)) {
                                    $phones = implode("','", tr_patient_get_phone_arr($row->phone));
                                    $patient = !empty($phones) ? $wpdb->get_row("select pa_first,pa_last from ci_patients where pa_phone in ('{$phones}')") : null;
                                    if ($patient) {
                                        $row->pa_last = $patient->pa_last;
                                        $row->pa_first = $patient->pa_first;
                                    }
                                }
                                preg_match_all("/([a-zA-Z]{3} [0-9]+ @ [0-9]+:[0-9]+[A|P|M]+)/", $row->message, $matches_scheduled);
                                if (empty($row->assen)) {
                                    preg_match_all("/({$keyassen}[0-9]+)/", $row->message, $matches);
                                    $row->assen = implode(', ', $matches[1]);
                                }
                                if (empty($row->patient_name)) {
                                    $row->patient_name = trim($row->pa_last . ', ' . $row->pa_first, ', ');
                                }
                                $key_duplicate = $row->phone . '_' . $row->created . '_' . $row->sendtype . '_' . $row->assen;
                                if (in_array($key_duplicate, $duplicates)) {
                                    continue;
                                }
                                $duplicates[] = $key_duplicate;
                                $stype_label = $stypes[$row->sendtype] ?? strtoupper($row->sendtype);
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors"><td class="px-4 py-2 text-gray-500 whitespace-nowrap font-mono"><?php echo esc_html($row->created); ?></td><td class="px-4 py-2 text-gray-800 whitespace-nowrap font-medium"><?php echo esc_html($row->patient_name); ?></td><td class="px-4 py-2 text-gray-600 whitespace-nowrap font-mono"><?php echo esc_html($row->phone); ?></td><td class="px-4 py-2 text-gray-600 font-mono"><?php echo esc_html($row->assen); ?></td><td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700"><?php echo esc_html($stype_label); ?></span></td><td class="px-4 py-2"><?php echo !empty($matches_scheduled[1]) ? esc_html(implode(', ', $matches_scheduled[1])) : '<span class="text-gray-400">&mdash;</span>'; ?></td><td class="px-4 py-2 text-gray-600 max-w-[260px]"><span class="block truncate" title="<?php echo esc_attr($row->message); ?>"><?php echo esc_html($row->message); ?></span></td><td class="px-4 py-2"><?php if (in_array($row->sendtype, array('nt', 'ms', 'n', 'no'))) { ?><a class="resend inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-white border border-gray-200 text-[#D83D00]" data-slugkey="<?php echo esc_attr($key_duplicate); ?>">Resend</a><?php } ?></td></tr>
                        <?php
                            }
                        } else {
                        ?>
                            <tr><td colspan="8" class="px-4 py-2 text-gray-500">Not found</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-4 py-2 text-xs text-gray-400"><?php echo esc_html((int) $total); ?> entries found</div>
                <?php if (isset($pagination) && $total > $row_per_page) { ?><div class="tablenav px-4"><?php echo $pagination->pagination(array('m')); ?></div><?php } ?>
            </div>
        </div>
    </div>
</div>
<style>
    .formsearch label {
        display: inline-block;
        text-align: right;
        padding-right: 5px;
        width: 60px;
    }

    .formsearch input[type="search"],
    .formsearch select {
        width: 200px;
    }
    .tr-sms-log-admin .tr-sms-input svg {
        top: 26px !important;
    }
</style>

<script>
    jQuery(document).ready(function($) {

        $('body').on('click', '.export_btn', function() {
            var $url = 'admin.php?act=export&trajax=1&' + $('form.tr-sms-search').serialize();
            $('body iframe.export').remove();
            $('body').append('<iframe class="export" style="position: absolute;left:-1110px;width:1px;height: 1px;" src="' + $url + '"></iframe>');
        }).on('click', 'a.resend', function() {
            var $this = $(this);
            if ($this.html() == 'Sending...') {
                return false;
            }
            if (confirm('Do you want resend?')) {
                $this.data('oldtext', $this.html());
                $this.html('Sending...');
                var $slugkey = $this.data('slugkey');
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'resend',
                        slugkey: $slugkey
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        $this.html($this.data('oldtext'));
                        if (rs.success) {
                            alert('Sent Successfully!');
                            $this.hide();
                        } else {
                            alert('Error. Please try again!');
                        }
                    },
                    error: function() {
                        $this.html($this.data('oldtext'));
                        alert('Error. Please try again!');
                    }
                });
            }
            return false;
        })

        $('.hasdate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    })
</script>
