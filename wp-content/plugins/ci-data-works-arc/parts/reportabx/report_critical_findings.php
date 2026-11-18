<div id="box_critical_findings" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card"
    style="margin-top: 28px;">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-triangle-alert h-5 w-5 text-primary" aria-hidden="true">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>Critical Findings
                    <span id="tt_cf" class="inline-flex items-center justify-center px-3 py-1 rounded-md bg-[#D83D00] text-white text-sm font-semibold ml-2">--</span>
                </h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Detected High-Risk Pathogens and Resistance Markers</p>
            </div>

            <select id="month_tabs_range_citical" class="sel_date_all_panel">
                <?php foreach ($filter_date_select as $item): ?>
                    <option value="<?= esc_attr($item['value']); ?>">
                        <?= esc_html($item['text']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="position:relative;display:inline-block;">
                <button data-slot="dropdown-menu-trigger"
                    class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                    type="button" id="export_critical_findings" aria-haspopup="menu" aria-expanded="false" data-state="closed"><svg
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

                <div id="menu_export_critical_findings" class="menu-export" data-radix-popper-content-wrapper="" dir="ltr" style="position:absolute;left:-38px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                    <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical" data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:" aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md" tabindex="-1" data-orientation="vertical" style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                        <div export_func="all_panels_critical_findings" export_type="pdf" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>Export as PDF
                        </div>
                        <div export_func="all_panels_critical_findings" export_type="csv" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>Export as CSV</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div data-slot="table-container" class="relative w-full overflow-x-auto">
                <table data-slot="table" class="w-full caption-bottom text-sm" id="table_critical_findings_main">
                    <thead data-slot="table-header" class="[&amp;_tr]:border-b">
                        <tr data-slot="table-row"
                            class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-gray-50">
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                Clinic</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                MRSA</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                VRE</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                ESBL</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                CRE</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                MDR AB</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                MDR PA</th>
                            <th data-slot="table-head"
                                class="h-10 px-2 text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
                                Total</th>
                        </tr>
                    </thead>
                    <tbody id="table_critical_findings" data-slot="table-body" class="[&amp;_tr:last-child]:border-0"></tbody>
                </table>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mt-6 pt-4 border-t">
            <div class="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                <div id="t_mrsa" class="text-2xl font-bold text-red-700">--</div>
                <div class="text-xs text-gray-600 mt-1">MRSA Cases</div>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                <div id="t_vre" class="text-2xl font-bold text-red-700">--</div>
                <div class="text-xs text-gray-600 mt-1">VRE Cases</div>
            </div>
            <div class="text-center p-3 bg-orange-50 rounded-lg border border-orange-200">
                <div id="t_esbl" class="text-2xl font-bold text-orange-700">--</div>
                <div class="text-xs text-gray-600 mt-1">ESBL Cases</div>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                <div id="t_cre" class="text-2xl font-bold text-red-700">--</div>
                <div class="text-xs text-gray-600 mt-1">CRE Cases</div>
            </div>

            <div class="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                <div id="t_mdr_ab" class="text-2xl font-bold text-red-700">--</div>
                <div class="text-xs text-gray-600 mt-1">MDR AB</div>
            </div>
            <div class="text-center p-3 bg-orange-50 rounded-lg border border-orange-200">
                <div id="t_mdr_pa" class="text-2xl font-bold text-orange-700">--</div>
                <div class="text-xs text-gray-600 mt-1">MDR PA</div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div>
                    <h5 class="font-medium text-gray-900 mb-2">Resistance Markers</h5>
                    <ul class="space-y-1 text-gray-600">
                        <li><strong>MRSA:</strong> Methicillin-Resistant Staphylococcus aureus</li>
                        <li><strong>VRE:</strong> Vancomycin-Resistant Enterococci</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-medium text-gray-900 mb-2">Extended Spectrum</h5>
                    <ul class="space-y-1 text-gray-600">
                        <li><strong>ESBL:</strong> Extended-Spectrum Beta-Lactamase</li>
                        <li><strong>CRE:</strong> Carbapenem-resistant Enterobacterales</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-medium text-gray-900 mb-2">MDR Organisms</h5>
                    <ul class="space-y-1 text-gray-600">
                        <li><strong>MDR AB:</strong> Multi-Drug Resistant Acinetobacter baumannii</li>
                        <li><strong>MDR PA:</strong> Multi-Drug Resistant Pseudomonas aeruginosa</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>