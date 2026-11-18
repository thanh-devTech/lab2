<div id="box_genotypic_antibiogram" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card"
    style="margin-top: 28px;">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-table2 lucide-table-2 h-5 w-5 text-primary" aria-hidden="true">
                        <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"></path>
                    </svg>Molecular Resistance Summary</h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Pathogen&ndash;Resistance Marker Co-Detection Rates</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <select id="month_tabs_range_genotypic" class="sel_date_all_panel">
                    <?php foreach ($filter_date_select as $item): ?>
                        <option value="<?= esc_attr($item['value']); ?>">
                            <?= esc_html($item['text']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="position:relative;display:inline-block;">
                    <button data-slot="dropdown-menu-trigger"
                        class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                        type="button" id="export_genotypic_antibiogram" aria-haspopup="menu" aria-expanded="false" data-state="closed"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                            <path d="M12 15V3"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="m7 10 5 5 5-5"></path>
                        </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>
                    <div id="menu_export_genotypic_antibiogram" class="menu-export" data-radix-popper-content-wrapper="" dir="ltr" style="position:absolute;right:0;top:100%;margin-top:4px;display:none;min-width:max-content;z-index:50;">
                        <div role="menu" data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground z-50 min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md">
                            <div export_func="genotypic_antibiogram" export_type="pdf" role="menuitem" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground relative flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>Export as PDF
                            </div>
                            <div export_func="genotypic_antibiogram" export_type="csv" role="menuitem" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground relative flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>Export as CSV
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="relative w-full overflow-x-auto">
                <table class="w-full border-collapse text-xs" id="table_genotypic_antibiogram_main">
                    <thead data-slot="table-header" class="[&amp;_tr]:border-b">
                        <tr data-slot="table-row"
                            class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-gray-50">
                            <th class="py-3 px-3 text-left font-semibold text-gray-900 sticky left-0 z-10 bg-gray-50">Pathogen</th>
                            <th class="py-3 px-3 text-center font-semibold text-gray-900 bg-gray-50">Samples</th>
                        </tr>
                    </thead>
                    <tbody id="table_genotypic_antibiogram" class="divide-y divide-gray-200 [&amp;_tr:last-child]:border-0"></tbody>
                </table>
            </div>
        </div>
        <div class="mt-4 pt-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4 text-xs">
                    <span class="font-medium text-gray-700">Resistance Legend:</span>
                    <div class="flex items-center gap-1">
                        <div class="w-4 h-4 rounded bg-green-100 border border-green-200"></div><span class="text-gray-600">&lt;10%</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-4 h-4 rounded bg-green-50 border border-green-200"></div><span class="text-gray-600">10-19%</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-4 h-4 rounded bg-orange-50 border border-orange-200"></div><span class="text-gray-600">20-39%</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-4 h-4 rounded bg-red-100 border border-red-200"></div><span class="text-gray-600">&ge;40%</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-4 h-4 rounded bg-gray-100 border border-gray-300"></div><span class="text-gray-600">No data</span>
                    </div>
                </div>
                <div class="text-xs text-gray-500">BL Inhibitors = Beta-Lactamase Inhibitors</div>
            </div>
            <div class="mt-3 text-[10px] leading-relaxed text-gray-500">This table provides a molecular, marker-based view of resistance and is not equivalent to a culture-based susceptibility assessment. It summarizes how often antibiotic resistance markers were detected alongside each pathogen, providing an estimate of potential resistance to a given antibiotic class. Values reflect overall trends and do not predict susceptibility for an individual case. Incomplete marker coverage may underestimate resistance rates, and resistance markers cannot always be assigned to a specific organism in polymicrobial infections. The presence of a marker does not always result in phenotypic resistance, and absence of a detected marker does not guarantee susceptibility.</div>
        </div>
    </div>
</div>
