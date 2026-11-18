<?php
global $wpdb;

// thời điểm hiện tại (datetime) và 7 ngày trước
$end = date_i18n('Y-m-d H:i:s');                     // now with time
$start = date('Y-m-d H:i:s', strtotime($end) - 7*24*3600); // 7 days ago

// dùng $start/$end cho where
$where_last7 = " AND ba.doc >= '{$start}' AND ba.doc <= '{$end}'";

$report_tracking = [
  'in_processing' => 0,
  'resulted'      => 0,
  'rejected'      => 0,
  'complete'      => 0,
  'on_hold'       => 0,
  'total'         => 0,
  'pending_24h'   => 0,
];

foreach (tr_pdd_get_batch_types() as $btype) {
    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    // Tổng sample trong 7 ngày
    $report_tracking['total'] += (int) $wpdb->get_var("
        SELECT COUNT(*) FROM {$batch_tbl} ba
        WHERE 1 {$where_last7}
    ");

    // In processing (plated)
    $report_tracking['in_processing'] += (int) $wpdb->get_var("
        SELECT COUNT(DISTINCT ba.mid)
        FROM {$batch_tbl} ba
        JOIN ci_plates_batches pl ON pl.mid = ba.mid AND pl.btype = '{$btype}'
        WHERE ba.batch_status = 'plated'
        {$where_last7}
    ");

    // Completed (estatus = 'C')
    $report_tracking['complete'] += (int) $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON ba.assen = re.patient_id
        WHERE re.estatus = 'C'
        {$where_last7}
    ");

    // On hold (estatus = 'H')
    $report_tracking['on_hold'] += (int) $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON ba.assen = re.patient_id
        WHERE re.estatus = 'H'
        {$where_last7}
    ");

    // Resulted (distinct mid với estatus = 'C')
    $report_tracking['resulted'] += (int) $wpdb->get_var("
        SELECT COUNT(DISTINCT ba.mid)
        FROM {$batch_tbl} ba
        JOIN {$report_tbl} re ON ba.assen = re.patient_id
        WHERE re.estatus = 'C'
        {$where_last7}
    ");

    // Rejected (dùng helper của bạn)
    $report_tracking['rejected'] += (int) $wpdb->get_var("
        SELECT COUNT(*) FROM {$batch_tbl} ba
        WHERE " . tr_pdd_where_batch_rejected(false, false) . " {$where_last7}
    ");
}

// *** Lấy samples (ví dụ btype = 'blood') trong 7 ngày để render table
$batch_tbl = tr_pdd_get_table_by_btype("blood");
$report_tbl = tr_pdd_get_table_report_by_btype("blood");

$sql = "
    SELECT 
        ba.assen AS sample_id,
        ba.patient_id,
        ba.patient_name,
        ba.type_of_specimen AS specimen_type,
        ba.panel_tests_requested AS test_requested,
        ba.priority_hours as priority_hours,
        ba.batch_type,
        ba.batch_status,
        ba.batch_id as batch_id,
        ba.lab_id as lab_id,
        re.estatus,
        re.created_date,
        re.approve_date
    FROM {$batch_tbl} ba
    LEFT JOIN {$report_tbl} re 
        ON ba.assen = re.patient_id 
        AND ba.batch_id = re.batch_id
        AND ba.lab_id  = re.lab_id
    WHERE ba.doc >= %s AND ba.doc <= %s
    ORDER BY ba.doc DESC
";

$samples = $wpdb->get_results($wpdb->prepare($sql, $start, $end));
?>
<div class="flex-1 overflow-auto p-8">
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold">Sample Tracking</h1>
                <p class="text-muted-foreground mt-1">Monitor and manage samples through the testing workflow</p>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth border-l-4 border-l-primary">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-activity h-5 w-5 text-primary" aria-hidden="true">
                                <path
                                    d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                                </path>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['total'];?></p>
                            <p class="text-xs text-muted-foreground font-medium">Total Samples</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-yellow-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-clock h-5 w-5 text-yellow-600" aria-hidden="true">
                                <path d="M12 6v6l4 2"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['pending_24h'];?></p>
                            <p class="text-xs text-muted-foreground font-medium">Pending</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-play h-5 w-5 text-blue-600" aria-hidden="true">
                                <path
                                    d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z">
                                </path>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['in_processing'];?></p>
                            <p class="text-xs text-muted-foreground font-medium">Processing</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-eye h-5 w-5 text-purple-600" aria-hidden="true">
                                <path
                                    d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0">
                                </path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['in_processing'];?></p>
                            <p class="text-xs text-muted-foreground font-medium">QC Review</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-circle-check-big h-5 w-5 text-green-600" aria-hidden="true">
                                <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                                <path d="m9 11 3 3L22 4"></path>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['complete']; ?></p>
                            <p class="text-xs text-muted-foreground font-medium">Completed</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-triangle-alert h-5 w-5 text-red-600" aria-hidden="true">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
                                </path>
                                <path d="M12 9v4"></path>
                                <path d="M12 17h.01"></path>
                            </svg></div>
                        <div>
                            <p class="text-2xl font-bold"><?php echo $report_tracking['on_hold'];?></p>
                            <p class="text-xs text-muted-foreground font-medium">On Hold</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header"
                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <div class="p-1.5 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                            height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-activity h-4 w-4 text-primary" aria-hidden="true">
                            <path
                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                            </path>
                        </svg></div>Sample Management
                </h4>
                <p data-slot="card-description" class="text-muted-foreground">Search, filter, and manage sample
                    processing status</p>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="flex flex-col md:flex-row gap-4 mb-8 tracking-bar">
                    <div class="flex-1">
                        <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-search absolute left-3 top-3 h-4 w-4 text-muted-foreground"
                                aria-hidden="true">
                                <path d="m21 21-4.34-4.34"></path>
                                <circle cx="11" cy="11" r="8"></circle>
                            </svg>
                            <input id="seach_tracking" data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-10 shadow-sm"
                                placeholder="Search by Sample ID or Patient ID" value=""></div>
                    </div>
                    <?php
                    $batches_status="ci_batches_status";
                    $status=$wpdb->get_results("
                        SELECT s_key, s_text
                        FROM {$batches_status}
                        WHERE istatus = 1
                        ORDER BY iorder ASC
                    ");
                    ?>
                    <select id="tracking_status_sel" class="none_border" style="min-width: 100px;max-width:157px;">
                        <option value="all">All Status</option>
                        <?php foreach ($status as $st) {?>
                            <option value="<?php echo esc_html($st->s_key);?>"><?php echo esc_html($st->s_text);?></option>
                        <?php }?>
                    </select>
                        
                    <select id="priority_sel" class="none_border" style="min-width: 100px;">
                        <option value="all">All Priority</option>
                        <option value="high">High</option>
                        <option value="normal">Normal</option>
                    </select>

                </div>
                <div class="rounded-xl border shadow-sm overflow-hidden">
                    <div data-slot="table-container" class="relative w-full overflow-x-auto">
                        <table data-slot="table" class="w-full caption-bottom text-sm">
                            <thead data-slot="table-header" class="[&amp;_tr]:border-b">
                                <tr data-slot="table-row"
                                    class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-primary/5">
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Sample ID</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Patient</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Type</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Test</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Priority</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Status</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Stage</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Assigned To</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Progress</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        ETA</th>
                                    <th data-slot="table-head"
                                        class="text-foreground h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody data-slot="table-body" class="[&amp;_tr:last-child]:border-0">
                            <?php foreach ($samples as $row): ?>
                                <tr data-slot="table-row"
                                    class="data-[state=selected]:bg-muted border-b hover:bg-primary/5 transition-colors">

                                    <!-- Sample ID -->
                                    <td class="p-2 font-semibold"><?= esc_html($row->sample_id) ?></td>

                                    <!-- Patient -->
                                    <td class="p-2 font-medium"><?= esc_html($row->patient_id) ?></td>

                                    <!-- Type -->
                                    <td class="p-2"><?= esc_html($row->specimen_type ?: 'N/A') ?></td>

                                    <!-- Test -->
                                    <td class="p-2" title="<?php echo $row->test_requested;?>"><?= esc_html(mb_strimwidth($row->test_requested ?: 'N/A', 0, 10, '...')) ?></td>

                                    <!-- Priority -->
                                    <td class="p-2">
                                        <span class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs border font-medium
                                            <?= ($row->priority_hours > 0 && $row->priority_hours <= 1) ? 'bg-red-50 text-red-700 border-red-200' :
                                            (($row->priority_hours > 1 && $row->priority_hours <= 4) ? 'bg-orange-50 text-orange-700 border-orange-200' :
                                                'bg-gray-50 text-gray-700 border-gray-200') ?>">
                                            <?= $row->priority_hours > 0 ? 'High' : 'Normal' ?>
                                        </span>
                                        <?php echo $row->priority_hours.'h'; ?>
                                    </td>

                                    <!-- Status -->
                                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
                                        <?php if ($row->batch_status=== 'completed'): ?>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big h-4 w-4 text-green-600" aria-hidden="true"><path d="M21.801 10A10 10 0 1 1 17 3.335"></path><path d="m9 11 3 3L22 4"></path></svg><span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-green-50 text-green-700 border-green-200 border font-medium">Completed</span>
                                            </div>
                                        <?php elseif ($row->batch_status === 'received'): ?>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pause h-4 w-4 text-red-600" aria-hidden="true"><rect x="14" y="3" width="5" height="18" rx="1"></rect><rect x="5" y="3" width="5" height="18" rx="1"></rect></svg>
                                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-red-50 text-red-700 border-red-200 border font-medium">Received</span>
                                            </div>
                                        <?php elseif ($row->batch_status === 'not_received'): ?>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-4 w-4 text-yellow-600" aria-hidden="true"><path d="M12 6v6l4 2"></path><circle cx="12" cy="12" r="10"></circle></svg>
                                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-yellow-50 text-yellow-700 border-yellow-200 border font-medium">Pending</span>
                                            </div>
                                        <?php elseif ($row->batch_status === ''): ?>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye h-4 w-4 text-purple-600" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg><span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-purple-50 text-purple-700 border-purple-200 border font-medium">QC Review</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play h-4 w-4 text-blue-600" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path></svg>
                                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-blue-50 text-blue-700 border-blue-200 border font-medium">
                                                <?php echo ucfirst($row->batch_status); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Stage -->
                                    <td class="p-2 text-sm font-medium">
                                        <?= esc_html($row->batch_status ?: 'DNA Extraction') ?>
                                        <?php echo $row->estatus;?>
                                    </td>

                                    <!-- Assigned To -->
                                    <td class="p-2 font-medium"><?= esc_html($row->patient_name ?: 'Unassigned') ?></td>

                                    <!-- Progress -->
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 bg-gray-200 rounded-full h-2.5">
                                                <?php $progress = ($row->estatus === 'C') ? 100 : (($row->estatus === 'H') ? 25 : 60); ?>
                                                <div class="bg-primary h-2.5 rounded-full transition-all" style="width: <?= $progress ?>%;"></div>
                                            </div>
                                            <span class="text-xs text-muted-foreground font-medium"><?= $progress ?>%</span>
                                        </div>
                                    </td>

                                    <!-- ETA -->
                                    <td class="p-2 text-sm font-medium">
                                        <?= $row->priority_hours ? $row->priority_hours . ' hours' : 'TBD' ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="p-2">
                                        <button data-slot="dropdown-menu-trigger" class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 hover:shadow-sm transition-shadow-smooth" type="button" id="radix-:r2b:" aria-haspopup="menu" aria-expanded="false" data-state="closed">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis h-4 w-4" aria-hidden="true"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($) {
    function filterTable() {
        var query   = $('#seach_tracking').val().toLowerCase();
        var status  = $('#tracking_status_sel').val();
        var priority = $('#priority_sel').val();

        $('table tbody tr').each(function() {
            var sampleId   = $(this).find('td:nth-child(1)').text().toLowerCase();
            var patientId  = $(this).find('td:nth-child(2)').text().toLowerCase();
            var rowStatus  = $(this).find('td[data-slot="table-cell"]').text().toLowerCase();
            var rowPriority = $(this).find('td:nth-child(5) span').text().toLowerCase(); 
            // cột Priority là cột thứ 5 trong table

            var matchSearch   = (sampleId.includes(query) || patientId.includes(query));
            var matchStatus   = (status === 'all' || rowStatus.includes(status.toLowerCase()));
            var matchPriority = (priority === 'all' || rowPriority.includes(priority));

            if (matchSearch && matchStatus && matchPriority) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    $('#seach_tracking').on('input', filterTable);
    $('#tracking_status_sel').on('change', filterTable);
    $('#priority_sel').on('change', filterTable);
});
</script>