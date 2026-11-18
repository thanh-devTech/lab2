<?php
global $wpdb;

$results_sample_type=$wpdb->get_results("
    SELECT ts_ID, ts_name ,ts_type
    FROM ci_test_specimens
");
?>

<div class="flex-1 overflow-auto p-8">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Sample Reception</h1>
                <p class="text-muted-foreground mt-1 text-[13px]">Register new samples and manage incoming specimens</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3"><button data-slot="button"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-scan h-5 w-5 mr-2" aria-hidden="true">
                            <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                            <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                            <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                            <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                        </svg>Barcode Scanner</button><button data-slot="button"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-upload h-5 w-5 mr-2" aria-hidden="true">
                            <path d="M12 3v12"></path>
                            <path d="m17 8-5-5-5 5"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        </svg>Bulk Import</button></div>
            </div>
        </div>
        <div class="space-y-3">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-3 shadow-sm">
                <div class="flex flex-wrap gap-2"><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-primary text-primary-foreground shadow-lg shadow-primary/25"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-plus h-4 w-4" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="M12 5v14"></path>
                        </svg>Register New</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-upload h-4 w-4" aria-hidden="true">
                            <path d="M12 3v12"></path>
                            <path d="m17 8-5-5-5 5"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        </svg>Batch Processing</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-scan h-4 w-4" aria-hidden="true">
                            <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                            <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                            <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                            <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                        </svg>Barcode Scanner</button><button
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm hover:scale-105 hover:shadow-md transform bg-white text-gray-700 hover:bg-gray-50 shadow-sm border border-gray-200"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-clock h-4 w-4" aria-hidden="true">
                            <path d="M12 6v6l4 2"></path>
                            <circle cx="12" cy="12" r="10"></circle>
                        </svg>Reception History</button></div>
            </div>
        </div>
        <div class="space-y-4">
            <div class="reception_step bg-white border rounded-lg px-6 py-3 shadow-sm">
                <nav aria-label="breadcrumb" data-slot="breadcrumb">
                    <ol data-slot="breadcrumb-list"
                        class="text-muted-foreground flex flex-wrap items-center gap-1.5 text-sm break-words sm:gap-2.5">
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <a href="index.php?page=dashboard_main" data-slot="breadcrumb-link"
                                class="hover:text-foreground transition-colors flex items-center gap-1" href="#"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
                                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                    <path
                                        d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                                    </path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                            class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg></li>
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><a
                                data-slot="breadcrumb-link"
                                class="hover:text-foreground transition-colors flex items-center gap-1" href="#"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-test-tube h-3 w-3" aria-hidden="true">
                                    <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                    <path d="M8.5 2h7"></path>
                                    <path d="M14.5 16h-5"></path>
                                </svg>Samples</a></li>
                        <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                            class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg></li>
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
                                data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                                class="text-foreground font-normal flex items-center gap-1"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-upload h-3 w-3" aria-hidden="true">
                                    <path d="M12 3v12"></path>
                                    <path d="m17 8-5-5-5 5"></path>
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                </svg>Reception</span></li>
                    </ol>
                </nav>
            </div>
            <div class="reception_step grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border lg:col-span-2 shadow-card border-l-4 border-l-primary">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                            <div class="p-1.5 bg-primary/10 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                    width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-test-tube h-5 w-5 text-primary" aria-hidden="true">
                                    <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
                                    <path d="M8.5 2h7"></path>
                                    <path d="M14.5 16h-5"></path>
                                </svg></div>New Sample Registration
                        </h4>
                        <p data-slot="card-description" class="text-muted-foreground">Enter sample details to register a
                            new specimen</p>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div dir="ltr" data-orientation="horizontal" data-slot="tabs" class="flex flex-col gap-2">
                            <div role="tablist" aria-orientation="horizontal" data-slot="tabs-list"
                                class="bg-muted text-muted-foreground h-9 items-center justify-center rounded-xl p-[3px] grid w-full grid-cols-3 shadow-sm"
                                tabindex="0" data-orientation="horizontal" style="outline: none;">
                                <button id="tab_1" type="button" role="tab" aria-selected="true"
                                    aria-controls="radix-:r29:-content-step-1" data-state="active"
                                    data-slot="tabs-trigger"
                                    class="dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground"
                                    tabindex="0" data-orientation="horizontal" data-radix-collection-item="">
                                    <div class="p-1 bg-blue-50 rounded data-[state=active]:bg-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-user h-4 w-4 text-blue-600 data-[state=active]:text-white"
                                            aria-hidden="true">
                                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    Patient Info
                                </button>
                                <button id="tab_2" type="button" role="tab" aria-selected="true"
                                    aria-controls="radix-:r29:-content-step-2" data-state="inactive"
                                    data-slot="tabs-trigger"
                                    class="dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground"
                                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">
                                    <div class="p-1 bg-green-50 rounded data-[state=active]:bg-white/20"><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-test-tube h-4 w-4 text-green-600 data-[state=active]:text-white"
                                            aria-hidden="true">
                                            <path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2">
                                            </path>
                                            <path d="M8.5 2h7"></path>
                                            <path d="M14.5 16h-5"></path>
                                        </svg></div>Sample Details
                                </button>
                                <button id="tab_3" type="button" role="tab" aria-selected="false"
                                    aria-controls="radix-:r29:-content-step-3" data-state="inactive"
                                    id="radix-:r29:-trigger-step-3" data-slot="tabs-trigger"
                                    class="dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground h-[calc(100%-1px)] flex-1 justify-center rounded-xl border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 flex items-center gap-2 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground"
                                    tabindex="-1" data-orientation="horizontal" data-radix-collection-item="">
                                    <div class="p-1 bg-purple-50 rounded data-[state=active]:bg-white/20"><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-circle-check-big h-4 w-4 text-purple-600 data-[state=active]:text-white"
                                            aria-hidden="true">
                                            <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
                                            <path d="m9 11 3 3L22 4"></path>
                                        </svg></div>Review
                                </button>
                            </div>
                            <div id="panel_reception_step1" data-state="active" data-orientation="horizontal"
                                role="tabpanel" aria-labelledby="radix-:r29:-trigger-step-1"
                                id="radix-:r29:-content-step-1" tabindex="0" data-slot="tabs-content"
                                class="flex-1 outline-none space-y-6 mt-8" style="">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-3"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="patientId">Patient ID *</label>
                                        <div class="flex gap-2">
                                            <input data-slot="input"
                                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive shadow-sm"
                                                id="patientId" placeholder="Enter patient ID" value="">
                                                <button
                                                data-slot="button"
                                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 size-9 rounded-md shadow-sm">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-search h-4 w-4" aria-hidden="true">
                                                    <path d="m21 21-4.34-4.34"></path>
                                                    <circle cx="11" cy="11" r="8"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="space-y-3 sel_dropdown">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="priority">Priority Level *</label>

                                        <select id="sel_priority" class="none_border w-full">
                                            <option value="">Select priority</option>
                                            <option value="normal">Normal</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button id="btn_next_step1" data-slot="button"
                                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90"
                                        disabled="false">Next
                                    </button>
                                </div>
                            </div>
                            <div id="panel_reception_step2" data-state="inactive" data-orientation="horizontal"
                                role="tabpanel" aria-labelledby="radix-:r29:-trigger-step-2"
                                id="radix-:r29:-content-step-2" tabindex="0" data-slot="tabs-content"
                                class="flex-1 outline-none space-y-6 mt-8">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-3 sel_dopdown">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="sampleType">Sample Type *</label>
                                        <select id="sample_type" class="none_border w-full">
                                            <option value="">Select sample type</option>
                                            <?php
                                            foreach ($results_sample_type as $row) {
                                                echo '<option value="' . esc_attr($row->ts_ID) . '">' . esc_html($row->ts_ID) . ' - ' . esc_html($row->ts_name) . ' - ' . esc_html($row->ts_type) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="space-y-3">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="testType">Test Type *</label>

                                        <select id="test_type" class="none_border w-full">
                                            <option value="">Select test type</option>
                                            <?php
                                            foreach (tr_pdd_get_batch_types() as $btype_val) {
                                                echo '<option value="' . $btype_val . '">' . tr_pdd_get_typename($btype_val) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="space-y-3">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="collectionDate">Collection Date</label>
                                            <input type="date"
                                            data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive shadow-sm"
                                            id="collectionDate" value="">
                                    </div>
                                    <div class="space-y-3"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                            for="collectionTime">Collection Time</label><input type="time"
                                            data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive shadow-sm"
                                            id="collectionTime" value=""></div>
                                </div>
                                <div class="space-y-3">
                                    <label data-slot="label"
                                        class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold"
                                        for="notes">Additional Notes</label>
                                        <textarea data-slot="textarea"
                                        class="resize-none border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-input-background px-3 py-2 text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm shadow-sm"
                                        id="notes"
                                        placeholder="Enter any additional notes or special instructions"></textarea>
                                </div>
                                <div class="flex justify-between">
                                    <button 
                                        onclick="showTab(1)"
                                        data-slot="button"
                                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-sm">
                                        Back
                                    </button>
                                        
                                    <button
                                        id="btn_next_step2"
                                        data-slot="button"
                                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90"
                                        disabled="true"
                                        >
                                        Next
                                    </button>
                                </div>
                            </div>
                            <div id="panel_reception_step3" data-state="inactive" data-orientation="horizontal"
                                role="tabpanel" aria-labelledby="radix-:r29:-trigger-step-3"
                                id="radix-:r29:-content-step-3" tabindex="0" data-slot="tabs-content"
                                class="flex-1 outline-none space-y-6 mt-8">
                                <div class="space-y-6">
                                    <h3 class="text-lg font-semibold">Review Sample Information</h3>
                                    <div class="grid grid-cols-2 gap-6 p-6 bg-primary/5 rounded-xl border shadow-sm">
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Patient ID:</span>
                                            <p id="patient_id_review" class="font-semibold"></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Priority:</span>
                                            <p id="priority_level_review" class="font-semibold">Normal</p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Sample Type:</span>
                                            <p id="sample_type_review" class="font-semibold"></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Test Type:</span>
                                            <p id="test_type_review" class="font-semibold"></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Collection
                                                Date:</span>
                                            <p id="collection_date_review" class="font-semibold"></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-muted-foreground font-medium">Collection
                                                Time:</span>
                                            <p id="collection_time_review" class="font-semibold"></p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-sm text-muted-foreground font-medium">Notes:</span>
                                        <p id="notes_review" class="mt-2 p-4 bg-primary/5 rounded-lg border font-medium"></p>
                                    </div>
                                </div>
                                <div  class="flex justify-between">
                                    <button onclick="showTab(2)" data-slot="button"
                                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-sm">
                                        Back
                                    </button>
                                        
                                    <button
                                        id="btn_register_sample"
                                        data-slot="button"
                                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90">
                                        Register Sample
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                            <div class="p-1.5 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-clock h-4 w-4 text-green-600" aria-hidden="true">
                                    <path d="M12 6v6l4 2"></path>
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg></div>Recent Registrations
                        </h4>
                        <p data-slot="card-description" class="text-muted-foreground">Recently registered samples</p>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="space-y-4">
                            <?php
                            $table_batches="ci_batches";
                            $bacth_recent=$wpdb->get_results("SELECT * FROM {$table_batches} ORDER BY doc DESC LIMIT 10");
                            foreach ($bacth_recent as $row) {
                            ?>
                            <div
                                class="p-4 border rounded-xl space-y-3 hover:bg-gray-50/50 transition-colors shadow-sm">
                                <?php
                                    $time_formatted = date('h:i A', strtotime($row->doc));
                                ?>
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold"><?php echo $row->mid;?></span>
                                    <span class="text-xs text-muted-foreground"><?php echo $time_formatted; ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span 
                                        data-slot="badge"
                                        class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-gray-50 text-gray-700 border-gray-200 border font-medium">
                                        Normal
                                    </span>
                                    <?php
                                        //echo $row->batch_status;
                                        if($row->batch_status=="completed"){
                                    ?>
                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-green-50 text-green-700 border-green-200 border font-medium">Completed</span>
                                    <?php
                                        }else if($row->batch_status=="cold_storage"){ 
                                    ?>
                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-blue-50 text-blue-700 border-blue-200 border font-medium">Cold Storage</span>
                                    <?php
                                        }else{
                                    ?>
                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-yellow-50 text-yellow-700 border-yellow-200 border font-medium">Processing</span>
                                    <?php }?>
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    <?php
                                    $tests = explode(',', $row->panel_tests_requested);
                                    if (count($tests) > 3){
                                        $display_tests = implode(', ', array_slice($tests, 0, 3)) . '...';
                                    } else {
                                        $display_tests = implode(', ', $tests);
                                    }
                                    ?>
                                    <p class="font-medium" title="<?php echo $row->panel_tests_requested;?>">
                                        <?php echo esc_html($row->type_of_specimen); ?> - <?php echo esc_html($display_tests); ?>
                                    </p>
                                    <p>Patient: <?php echo $row->patient_id?></p>
                                </div>
                            </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var tabCurrent = 1;

var patient_id="";
var priority_level="";
var sample_type="";
var test_type="";
var collection_date="";
var collection_time="";
var sample_notes="";

jQuery(function($) {
    function resetTab() {
        $("#tab_1").attr("aria-selected", "false").attr("data-state", "inactive");
        $("#tab_2").attr("aria-selected", "false").attr("data-state", "inactive");
        $("#tab_3").attr("aria-selected", "false").attr("data-state", "inactive");
    }

    function resetPanel() {
        $("#panel_reception_step1").hide();
        $("#panel_reception_step2").hide();
        $("#panel_reception_step3").hide();
    }

    window.showTab = function(indexTab){
        tabCurrent = indexTab;
        resetTab();
        $("#tab_"+indexTab).attr("aria-selected", "true").attr("data-state", "active");
        resetPanel();
        $("#panel_reception_step"+indexTab).show();
    }

    resetTab();
    resetPanel();
    showTab(1);

    $("#tab_1").click(function() {
        showTab(1);
    });

    $("#tab_2").click(function() {
        showTab(2);
    });

    $("#tab_3").click(function() {
        showTab(3);
    });

    //Validate Step 1
    $('#patientId, #sel_priority').on('input change', function() {
        const patientId = $('#patientId').val().trim();
        const priority = $('#sel_priority').val().trim();
        if (patientId !== '' && priority !== '') {
            $('#btn_next_step1').prop('disabled', false);
        } else {
            $('#btn_next_step1').prop('disabled', true);
        }
    });

    //Validate Step 2
    $('#sample_type, #test_type, #collectionDate, #collectionTime').on('input change', function() {
        const sampleType = $('#sample_type').val().trim();
        const testType = $('#test_type').val().trim();
        const collectionDate = $('#collectionDate').val().trim();
        const collectionTime = $('#collectionTime').val().trim();
        if (sampleType !== '' && testType !== '' && collectionDate !== '' && collectionTime !== '') {
            $('#btn_next_step2').prop('disabled', false);
        } else {
            $('#btn_next_step2').prop('disabled', true);
        }
    }); 


    $('#btn_next_step1').click(function() {
        patient_id = $('#patientId').val().trim();
        priority_level = $('#sel_priority').val().trim();
        showTab(2);
    });

    $('#btn_next_step2').click(function() {
        sample_type=$("#sample_type").val();
        test_type=$("#test_type").val();
        collection_date=$("#collectionDate").val();
        collection_time=$("#collectionTime").val();
        sample_notes=$("#notes").val();
        showTab(3);
        $("#sample_type_review").text($("#sample_type option:selected").text());
        $("#test_type_review").text($("#test_type option:selected").text());
        $("#collection_date_review").text($("#collectionDate").val());
        $("#collection_time_review").text($("#collectionTime").val());
        $("#patient_id_review").text(patient_id);
        $("#priority_level_review").text(priority_level.charAt(0).toUpperCase() + priority_level.slice(1));
        $("#notes_review").text(sample_notes);
    });

    $("#btn_register_sample").click(function() {
        alert("Sample registered successfully!");
        register_sample();
    });

    $("#sample_type").change(function() {
        const sampleType = $(this).val();
    });

    function register_sample() {
        $.ajax({
            data: {
                trajax: "main_sample_reception_ajax",
                func: "register_sample",
                patient_id: patient_id,
                priority_level: priority_level,
                sample_type: sample_type,
                test_type: test_type,
                collection_date: collection_date,
                collection_time: collection_time,
                sample_notes: sample_notes
            },
            success: function(response) {
                let res = JSON.parse(response);
                alert(res.message);
            },
            error: function(xhr, status, error) {
                alert("An error occurred: " + error);
            }
        });
    }

    function get_tests_by_sample() {
        let sample_type = $("#sample_type").val();
        var sample_note=$("#notes").text();
        $("#test_type").html("<option value=''>Loading...</option>");
        $.ajax({
            data: {
                trajax: "main_sample_reception_ajax",
                btype: sample_type,
                func: "get_tests_by_sample"
            },
            success: function(response) {
                let res = JSON.parse(response);
                $("#test_type").html("<option value=''>Select Test Type</option>");
                $.each(res, function(i, item) {
                    $("#test_type").append(
                    $("<option>", {
                        value: item.tg_ID,
                        text: item.tg_name
                    })
                    );
                });
            },
            error: function(xhr, status, error) {
                alert("An error occurred: " + error);
            }
        });
    }
});
</script>