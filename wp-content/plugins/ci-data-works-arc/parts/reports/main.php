<?php
global $access_btypes;
?>
<style>
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

    ol {
        list-style-type: none;
        margin: 0px;
        margin-top: px;
        padding-top: 5px;
        padding-left: 5px;
    }

    ol li a {
        color: unset;
    }

    .font-bold {
        --tw-font-weight: var(--font-weight-bold) !important;
        font-weight: var(--font-weight-bold) !important;
    }

    .text-2xl {
        font-size: var(--text-2xl);
        line-height: var(--tw-leading, var(--text-2xl--line-height));
    }

    td {
        --tw-font-weight: var(--font-weight-medium) !important;
        font-weight: var(--font-weight-medium) !important;
        line-height: unset !important;
    }
</style>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6">
            <div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
                <nav aria-label="breadcrumb" data-slot="breadcrumb">
                    <ol data-slot="breadcrumb-list"
                        class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <a onclick="window.location.href='admin.php?page=dashboard_main'"
                                data-slot="breadcrumb-link"
                                class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                                href="#"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house h-3 w-3"
                                    aria-hidden="true">
                                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                    <path
                                        d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                                    </path>
                                </svg><span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span></a>
                        </li>
                        <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                            class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg></li>
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
                                data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                                class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
                                    <path
                                        d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
                                    </path>
                                    <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                    <path d="M10 9H8"></path>
                                    <path d="M16 13H8"></path>
                                    <path d="M16 17H8"></path>
                                </svg><span class="truncate max-w-[120px] sm:max-w-none">Reports</span></span></li>
                    </ol>
                </nav>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                        Reports</h1>
                    <p class="text-muted-foreground mt-2">Access comprehensive laboratory reporting
                        and analytics</p>
                </div>
            </div>
            <div>
                <div class="space-y-8">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-1 h-6 rounded bg-rose-500"></div>
                            <h2 class="text-xl">Clinical Reports</h2>
                        </div>

                        <?php if (in_array('pcr', $access_btypes)) { ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div onclick="window.location.href='admin.php?page=abx_filter'" data-slot="card"
                                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                    <div data-slot="card-header"
                                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-triangle-alert h-5 w-5 text-primary"
                                                        aria-hidden="true">
                                                        <path
                                                            d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
                                                        </path>
                                                        <path d="M12 9v4"></path>
                                                        <path d="M12 17h.01"></path>
                                                    </svg></div>
                                                <div>
                                                    <h4 data-slot="card-title" class="text-base">Clinical Filters</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                        <p data-slot="card-description" class="text-muted-foreground text-sm">Apply
                                            pregnancy,
                                            lactation, and allergy filters to igniteABX recommendations</p>
                                    </div>
                                </div>

                                <div onclick="window.location.href='admin.php?page=ci_reports_pcr_summary'" data-slot="card"
                                    class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                    <div data-slot="card-header"
                                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-dna h-5 w-5 text-primary" aria-hidden="true">
                                                        <path d="m10 16 1.5 1.5"></path>
                                                        <path d="m14 8-1.5-1.5"></path>
                                                        <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path>
                                                        <path d="m16.5 10.5 1 1"></path>
                                                        <path d="m17 6-2.891-2.891"></path>
                                                        <path d="M2 15c6.667-6 13.333 0 20-6"></path>
                                                        <path d="m20 9 .891.891"></path>
                                                        <path d="M3.109 14.109 4 15"></path>
                                                        <path d="m6.5 12.5 1 1"></path>
                                                        <path d="m7 18 2.891 2.891"></path>
                                                        <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path>
                                                    </svg></div>
                                                <div>
                                                    <h4 data-slot="card-title" class="text-base">PCR Summary Report</h4>
                                                </div>
                                            </div><span data-slot="badge"
                                                class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                                Soon</span>
                                        </div>
                                    </div>
                                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                        <p data-slot="card-description" class="text-muted-foreground text-sm">Comprehensive
                                            PCR
                                            testing results and antibiotic interpretation analytics</p>
                                    </div>
                                </div>

                            <?php } ?>

                            <div onclick="window.location.href='admin.php?page=ci_reports_clinic_panel_stats'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-chart-column h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                                    <path d="M18 17V9"></path>
                                                    <path d="M13 17V5"></path>
                                                    <path d="M8 17v-3"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Clinic Panel Stats</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Panel-specific
                                        statistics by clinic including rejection rates and turnaround times</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_positive_negative_report'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all">

                                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-target h-5 w-5 text-primary" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <circle cx="12" cy="12" r="6"></circle>
                                                    <circle cx="12" cy="12" r="2"></circle>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Positive / Negative Report</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Summary of
                                        positive and negative test results by panel and timeframe</p>
                                </div>
                            </div>
                            </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-1 h-6 rounded bg-blue-500"></div>
                            <h2 class="text-xl">Operations Reports</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div onclick="window.location.href='admin.php?page=ci_reports_test_volumes'"
                                data-slot="card"
                                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-trending-up h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path d="M16 7h6v6"></path>
                                                    <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Test Volume Report</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Daily sample
                                        volume metrics with live updates and historical trends</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_tat_report'" data-slot="card"
                                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-clock h-5 w-5 text-primary" aria-hidden="true">
                                                    <path d="M12 6v6l4 2"></path>
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">TAT Report</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Turnaround
                                        time
                                        analysis to identify workflow bottlenecks</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_lab_flow'" data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-flask-conical h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path
                                                        d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2">
                                                    </path>
                                                    <path d="M6.453 15h11.094"></path>
                                                    <path d="M8.5 2h7"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Lab Flow</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Sample flow
                                        through laboratory departments and processing stages</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_sample_manifest'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-clipboard-list h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                                                    <path
                                                        d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2">
                                                    </path>
                                                    <path d="M12 11h4"></path>
                                                    <path d="M12 16h4"></path>
                                                    <path d="M8 11h.01"></path>
                                                    <path d="M8 16h.01"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Sample Manifest</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Complete
                                        sample
                                        tracking and chain of custody documentation</p>
                                </div>
                            </div>

                            <div 
                                onclick="window.location.href='admin.php?page=ci_reports_not_received'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" class="lucide lucide-clipboard-list h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                                    <path d="M12 11h4"></path>
                                                    <path d="M12 16h4"></path>
                                                    <path d="M8 11h.01"></path>
                                                    <path d="M8 16h.01"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Not Received</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Samples collected but not yet received for
                                        a selected date of collection.</p>
                                </div>
                            </div>

                            <div onclick="window.location.href='admin.php?page=ci_reports_pending_and_outstanding'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-package h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path
                                                        d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                                                    </path>
                                                    <path d="M12 22V12"></path>
                                                    <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                                    <path d="m7.5 4.27 9 5.15"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Current Delays</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Track samples not received and samples pending results with time buckets</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_processed_samples'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-beaker h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path d="M4.5 3h15"></path>
                                                    <path d="M6 3v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3"></path>
                                                    <path d="M6 14h12"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Processed Samples</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">View all
                                        processed
                                        samples with processing details and results status</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-1 h-6 rounded bg-green-500"></div>
                            <h2 class="text-xl">Quality Reports</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div onclick="window.location.href='admin.php?page=ci_reports_quality_metrics'"
                                data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-chart-column h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                                    <path d="M18 17V9"></path>
                                                    <path d="M13 17V5"></path>
                                                    <path d="M8 17v-3"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Quality Metrics</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Monitor QC
                                        metrics, contamination rates, and performance indicators</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_plating'" data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-microscope h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path d="M6 18h8"></path>
                                                    <path d="M3 22h18"></path>
                                                    <path d="M14 22a7 7 0 1 0 0-14h-1"></path>
                                                    <path d="M9 14h2"></path>
                                                    <path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"></path>
                                                    <path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Plating Report</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Culture
                                        plating
                                        statistics, media usage, and growth analysis</p>
                                </div>
                            </div>
                            <div onclick="window.location.href='admin.php?page=ci_reports_sapphire'" data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-database h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                                    <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                                    <path d="M3 12A9 3 0 0 0 21 12"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Rack Report</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Sample rack
                                        locations, storage tracking, and inventory management</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-1 h-6 rounded bg-purple-500"></div>
                            <h2 class="text-xl">Laboratory Management</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div onclick="window.location.href='admin.php?page=ci_reports_providers'" data-slot="card"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all hover:shadow-lg cursor-pointer hover:border-primary">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-users h-5 w-5 text-primary" aria-hidden="true">
                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                    <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Provider Report</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Test volume
                                        and
                                        performance metrics by healthcare provider</p>
                                </div>
                            </div>

                            <div data-slot="card" onclick="window.location.href='admin.php?page=ci_reports_collectors'"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-users h-5 w-5 text-primary" aria-hidden="true">
                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                    <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Collectors Report</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Sample
                                        collection
                                        statistics by collector and location</p>
                                </div>
                            </div>
                            <div data-slot="card"
                                onclick="window.location.href='admin.php?page=ci_reports_lost_providers'"
                                class="bg-card cursor-pointer text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card transition-all opacity-60">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-primary/10 rounded-lg"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-triangle-alert h-5 w-5 text-primary"
                                                    aria-hidden="true">
                                                    <path
                                                        d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
                                                    </path>
                                                    <path d="M12 9v4"></path>
                                                    <path d="M12 17h.01"></path>
                                                </svg></div>
                                            <div>
                                                <h4 data-slot="card-title" class="text-base">Lost Providers</h4>
                                            </div>
                                        </div><span data-slot="badge"
                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">Coming
                                            Soon</span>
                                    </div>
                                </div>
                                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                    <p data-slot="card-description" class="text-muted-foreground text-sm">Track
                                        providers
                                        who have reduced or stopped ordering tests</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>