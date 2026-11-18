<?php
global $wpdb;

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'search_report') {
    $search  = trim($_REQUEST['term']);
    $sql = $wpdb->prepare("select assen, CONCAT(assen,' - ', patient_name)  as label
	 from ci_batches_pcr where assen = %s or patient_name like %s", $search, '%' . $search . '%');
    $rs = $wpdb->get_results($sql, ARRAY_A);

    wp_send_json($rs);
    exit;
} else if (isset($_POST['assen'])) {
    $assen         = stripslashes($_POST['assen']);
    $comment     = stripslashes($_POST['comment']);
    $addmailing = isset($_POST['addmailing']);
    $request_review = isset($_POST['request_review']);

    $user = wp_get_current_user();
    $rs = false;
    if (!empty($comment)) {
        $rs = tr_client_send_hub(array(
            'action'     => 'review',
            'assen'     => $assen,
            'comment'     => $comment,
            'addmailing' => $addmailing ? 1 : 0,
            'request_review' => $request_review ? 1 : 0,
            'email'     => $user->user_email,
            'fullname'    => $user->display_name,
        ));
    }

    if (is_array($rs) && $rs['status'] == 'ok') {
        $sent = true;
    } else {
        Tr_Session_Class::add_error("Error. Please try again!!");
    }
}

wp_enqueue_script('jquery-ui-autocomplete');
?>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-xl sm:text-2xl font-semibold">Release Notes</h1>
                <p class="text-muted-foreground mt-1 text-xs sm:text-[13px]">View version history and updates for Ignite
                    ABX</p>
            </div>
        </div>
        <div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <div class="flex items-center justify-between">
                                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-sparkles h-5 w-5 text-blue-500"
                                        aria-hidden="true">
                                        <path
                                            d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                                        </path>
                                        <path d="M20 2v4"></path>
                                        <path d="M22 4h-4"></path>
                                        <circle cx="4" cy="20" r="2"></circle>
                                    </svg>Version 1.2.5</h4><span class="text-sm text-muted-foreground">September 15,
                                    2025</span>
                            </div>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="prose prose-sm max-w-none">
                                <div class=" text-sm text-gray-700 leading-relaxed">
                                    <div class="font-semibold text-sm text-gray-900 mt-4 mb-2">Cumulative Antibiogram
                                        Generation Guide</div>
                                    <div class="text-sm text-gray-700 mb-3">This version introduces comprehensive
                                        antibiogram capabilities following CLSI M39 guidelines.</div>
                                    <div class="font-semibold text-sm text-gray-900 mt-4 mb-2">Creating CLSI-Compliant
                                        Antibiograms</div>
                                    <div class="text-sm text-gray-700 mb-3">Navigate to ABX Reports → Cumulative
                                        Antibiogram to access the new generator.</div>
                                    <div class="text-sm text-gray-700 mb-3">Step 1: Select your reporting period
                                        (typically 12 months)
                                        Step 2: Choose facility-wide or unit-specific data
                                        Step 3: Apply CLSI duplicate removal rules (automatically applied)
                                        Step 4: Set minimum isolate thresholds (default: 30 isolates per organism)
                                        Step 5: Generate report</div>
                                    <div class="text-sm text-gray-700 mb-3">The system automatically excludes duplicate
                                        isolates from the same patient within 30 days, following CLSI M39
                                        recommendations.</div>
                                    <div class="font-semibold text-sm text-gray-900 mt-4 mb-2">Using Advanced Filters
                                    </div>
                                    <div class="font-semibold text-sm text-gray-900 mt-4 mb-2">New filtering
                                        capabilities allow you to create targeted antibiograms:</div>
                                    <div class="text-sm text-gray-700 mb-3">By Specimen Source: Urine, blood,
                                        respiratory, wound, etc.
                                        By Patient Location: ICU, general ward, emergency department
                                        By Age Group: Pediatric, adult, geriatric populations
                                        By Infection Type: Community-acquired vs. healthcare-associated</div>
                                    <div class="text-sm text-gray-700 mb-3">Save filter presets for commonly generated
                                        reports to streamline your workflow.</div>
                                    <div class="font-semibold text-sm text-gray-900 mt-4 mb-2">Exporting Antibiograms
                                    </div>
                                    <div class="text-sm text-gray-700 mb-3">Excel Export: Includes formulas and
                                        conditional formatting for easy analysis
                                        PDF Export: Professional formatting suitable for distribution to clinicians
                                        CSV Export: Raw data with metadata for further statistical analysis</div>
                                    <div class="text-sm text-gray-700 mb-3">All exports include generation parameters
                                        and CLSI compliance notes.</div>
                                    <div class="text-sm text-gray-700 mb-3">For questions about antibiogram
                                        interpretation, contact our support team.</div>
                                </div>
                            </div>
                            <div class="pt-4 mt-6 border-t text-sm text-gray-600">
                                <p>For technical support or questions about this release, please contact our support
                                    team through the Contact Us page.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-1">
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <h4 data-slot="card-title" class="leading-none">Recent Versions</h4>
                            <p class="text-xs text-gray-600 mt-1">Click on any version to view release notes</p>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="space-y-3">
                                <div class="p-4 border rounded-lg transition-all border-gray-200 bg-white">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between gap-2"><span
                                                class="font-medium text-gray-900">v1.3.0</span><span data-slot="badge"
                                                class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-blue-500 text-white text-xs">Latest</span>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-900 mb-1">Enhanced Resistance Pattern Detection
                                            </p>
                                            <p class="text-xs text-gray-600">Advanced algorithms for identifying
                                                emerging resistance patterns and MDRO alerts.</p>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">October 1, 2025</div>
                                        <div class="flex gap-2"><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-file-text h-4 w-4 mr-1" aria-hidden="true">
                                                    <path
                                                        d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                    </path>
                                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                    <path d="M10 9H8"></path>
                                                    <path d="M16 13H8"></path>
                                                    <path d="M16 17H8"></path>
                                                </svg>Notes</button><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-book-open h-4 w-4 mr-1" aria-hidden="true">
                                                    <path d="M12 7v14"></path>
                                                    <path
                                                        d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z">
                                                    </path>
                                                </svg>Guide</button></div>
                                    </div>
                                </div>
                                <div class="p-4 border rounded-lg transition-all border-primary bg-primary/5 shadow-md">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between gap-2"><span
                                                class="font-medium text-gray-900">v1.2.5</span></div>
                                        <div>
                                            <p class="text-sm text-gray-900 mb-1">Improved Antibiogram Reports</p>
                                            <p class="text-xs text-gray-600">New cumulative antibiogram generation with
                                                customizable filters and export options.</p>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">September 15, 2025</div>
                                        <div class="flex gap-2"><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-file-text h-4 w-4 mr-1" aria-hidden="true">
                                                    <path
                                                        d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                    </path>
                                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                    <path d="M10 9H8"></path>
                                                    <path d="M16 13H8"></path>
                                                    <path d="M16 17H8"></path>
                                                </svg>Notes</button><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border text-foreground hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-gray-100 hover:bg-gray-200 border-gray-400 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-book-open h-4 w-4 mr-1" aria-hidden="true">
                                                    <path d="M12 7v14"></path>
                                                    <path
                                                        d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z">
                                                    </path>
                                                </svg>Guide</button></div>
                                    </div>
                                </div>
                                <div class="p-4 border rounded-lg transition-all border-gray-200 bg-white">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between gap-2"><span
                                                class="font-medium text-gray-900">v1.2.0</span></div>
                                        <div>
                                            <p class="text-sm text-gray-900 mb-1">Automated Stewardship Alerts</p>
                                            <p class="text-xs text-gray-600">Real-time alerts for antimicrobial
                                                stewardship interventions and recommendations.</p>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">August 28, 2025</div>
                                        <div class="flex gap-2"><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-file-text h-4 w-4 mr-1" aria-hidden="true">
                                                    <path
                                                        d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                    </path>
                                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                    <path d="M10 9H8"></path>
                                                    <path d="M16 13H8"></path>
                                                    <path d="M16 17H8"></path>
                                                </svg>Notes</button></div>
                                    </div>
                                </div>
                                <div class="p-4 border rounded-lg transition-all border-gray-200 bg-white">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between gap-2"><span
                                                class="font-medium text-gray-900">v1.1.8</span></div>
                                        <div>
                                            <p class="text-sm text-gray-900 mb-1">Performance &amp; Bug Fixes</p>
                                            <p class="text-xs text-gray-600">Optimized report generation and resolved
                                                data export issues.</p>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">August 10, 2025</div>
                                        <div class="flex gap-2"><button data-slot="button"
                                                class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 flex-1"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-file-text h-4 w-4 mr-1" aria-hidden="true">
                                                    <path
                                                        d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                    </path>
                                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                    <path d="M10 9H8"></path>
                                                    <path d="M16 13H8"></path>
                                                    <path d="M16 17H8"></path>
                                                </svg>Notes</button></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 pt-4 border-t">
                                <p class="text-xs text-gray-600 mb-3">Looking for older versions? View the complete
                                    version history in our documentation.</p><button data-slot="button"
                                    class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 w-full"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2"
                                        aria-hidden="true">
                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                        <path d="M10 9H8"></path>
                                        <path d="M16 13H8"></path>
                                        <path d="M16 17H8"></path>
                                    </svg>View All Versions</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 pt-4 pb-2 text-xs text-muted-foreground border-t">
                <p><strong>Note:</strong> Release notes are provided for informational purposes. All updates are
                    automatically applied to your Ignite ABX system. For questions about specific features or changes,
                    please contact our support team. Version numbers and release dates are examples for demonstration
                    purposes.</p>
            </div>
        </div>
    </div>
</div>