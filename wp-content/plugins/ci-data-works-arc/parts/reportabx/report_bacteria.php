<div id="box_pathogens" data-slot="card"
    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary">
                        <rect x="3" y="4" width="7" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="15" cy="5.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <circle cx="18.5" cy="5.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <rect x="4" y="10" width="8" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="6" cy="17.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <circle cx="9.5" cy="17.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <rect x="14" y="16" width="7" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="17" cy="11" r="2" fill="currentColor" opacity="0.85"></circle>
                    </svg>
                    Pathogens
                </h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1 sub-title">Relative share of total targets</p>
            </div>
            <select id="bac_prevalence" class="none_border">
                <option value="0" selected>Distribution</option>
                <option value="all">Prevalence (All)</option>
                <option value="pos">Prevalence (Pos)</option>
            </select>

            <select id="month_range_pathogens" name="month_range_pathogens" class="sel_date_all_panel">
                <?php foreach ($filter_date_select as $item): ?>
                    <option value="<?php echo esc_attr($item['value']); ?>">
                        <?php echo esc_html($item['text']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="position:relative;display:inline-block;">
                <button data-slot="dropdown-menu-trigger"
                    class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                    type="button" id="export_pathogens" aria-haspopup="menu" aria-expanded="false" data-state="closed"><svg
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

                <div id="menu_export_pathogens" class="menu-export" data-radix-popper-content-wrapper="" dir="ltr" style="position:absolute;left:-38px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                    <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical" data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:" aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md" tabindex="-1" data-orientation="vertical" style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                        <div export_func="all_panels_pathogens" export_type="pdf" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>Export as PDF
                        </div>
                        <div export_func="all_panels_pathogens" export_type="csv" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
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
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
        <div style="display:none;height: 260px;" class="item_nodata_grid  items-center justify-center">
            <div class="relative w-full text-muted-foreground text-xl">No Data Available</div>
        </div>
        <div class="item_data_grid grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-5 flex items-center justify-center">
                <div class="relative w-full max-w-[320px]">
                    <div class="recharts-responsive-container" style="width: 260px; height: 260px;max-width:100%;margin:0 auto; min-width: 0px;">
                        <div id="tooltipChartPathogens" class="recharts-tooltip-wrapper"></div>
                        <canvas id="chart_pathogens_tabs" width="100%" height="260"
                            style="display: block; box-sizing: border-box; height: 260px; width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 space-y-3">
                <div id="pathogens_table_header" class="grid grid-cols-9 gap-1 text-xs font-medium text-gray-600 px-1">
                </div>
                <div id="pathogens_table" style="position: relative;" class="text-xs max-h-[320px] overflow-y-auto">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    //Chart 3
    var ctx_pathogens = document.getElementById('chart_pathogens_tabs');
    var tooltipChartPathogens = document.getElementById('tooltipChartPathogens');
    var chart_pathogens_tabs = new Chart(ctx_pathogens, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: []
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: "nearest",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        var elementTag = document.getElementById('chart_pathogens_tabs');
                        var tooltipModel = context.tooltip;

                        if (tooltipModel.opacity === 0) {
                            tooltipChartPathogens.classList.remove("active");
                            return;
                        } else {
                            tooltipChartPathogens.classList.add("active");
                        }
                        const dataPoints = tooltipModel.dataPoints;
                        const index = dataPoints[0].dataIndex;
                        const el_data = all_chart_data.chart3 ? all_chart_data.chart3.data[index + 1] : [];

                        const pt_name = el_data[0] || '';
                        const pt_value = el_data[1] || '';
                        let pt_ps = el_data[2] || '';
                        if (selected_bac_prevalence == 'all') {
                            pt_ps = el_data[3] || '';
                        } else if (selected_bac_prevalence == 'pos') {
                            pt_ps = el_data[4] || '';
                        }
                        const type = selected_bac_prevalence == '0' ? "detected" : "specimens";
                        tooltipChartPathogens.innerHTML = `<div><strong>${pt_name}</strong><br/>${pt_ps}% (${pt_value} ${type})</div>`;
                        tooltipChartPathogens.style.display = 'block';

                        tooltipChartPathogens.style.left = tooltipModel.caretX + 'px';
                        tooltipChartPathogens.style.top = tooltipModel.caretY + 'px';
                    }
                }
            },
            cutout: '60%',
            layout: {
                padding: padding_char
            },
        },
        plugins: [{
            id: 'centerText',
            beforeDraw(chart) {
                const {
                    width,
                    height,
                    ctx
                } = chart;
                ctx.restore();
                let total = 0;
                if (all_chart_data.chart3 && all_chart_data.chart3.data[1]) {
                    const indexTotal = selected_bac_prevalence == '0' ? 5 : selected_bac_prevalence == 'all' ? 6 : 7;
                    total = all_chart_data.chart3.data[1][indexTotal] || 0;
                }

                ctx.font = fontCenterText_Pie;
                ctx.fillStyle = "#000";
                ctx.textAlign = "center";
                ctx.textBaseline = "middle";
                ctx.fillText(Math.round(total), width / 2, height / 2 - 10);

                ctx.font = fontCenterTextTip_Pie;
                ctx.fillStyle = "#666";
                const total_text = selected_bac_prevalence == '0' ? "Detected" : selected_bac_prevalence == 'all' ? "Specimens" : "Positives";
                ctx.fillText(total_text, width / 2, height / 2 + 15);

                ctx.save();
            }
        }, noDataPlugin]
    });
</script>