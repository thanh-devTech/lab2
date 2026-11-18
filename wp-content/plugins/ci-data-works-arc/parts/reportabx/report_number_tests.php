<div class="mb-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div id="box_test_results" data-slot="card"
            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header"
                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-activity h-5 w-5 text-primary"
                                aria-hidden="true">
                                <path
                                    d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                                </path>
                            </svg>Test Results</h4>
                        <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Positive and negative
                            results distribution</p>
                    </div>

                    <select id="month_range_test_result" name="month_range_test_result" class="sel_date_all_panel">
                        <?php foreach ($filter_date_select as $item): ?>
                            <option value="<?php echo esc_attr($item['value']); ?>">
                                <?php echo esc_html($item['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="space-y-4">
                    <div class="relative">
                        <div class="recharts-responsive-container" style="width: 100%; height: 280px;">
                            <div style="width: 100%; height: 0px; overflow: visible;">
                                <div class="recharts-wrapper"
                                    style="position: relative; cursor: default; width: 100%; height: 280px;">
                                    <div
                                        style="display:flex; justify-content:center; align-items:center; margin-top:0px;width:260px; height: 260px;max-width:100%;margin:0 auto">
                                        <div id="tooltipTabsTestResult" class="recharts-tooltip-wrapper"></div>
                                        <canvas id="chart_test_results_tabs" width="100%" height="260"
                                            style="display: block; box-sizing: border-box; height: 260px; width: 100%;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="box_volume_trend_6_months" data-slot="card"
            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header"
                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-trending-up h-5 w-5 text-primary"
                                aria-hidden="true">
                                <path d="M16 7h6v6"></path>
                                <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                            </svg>Volume Trend 6 Months</h4>
                        <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Monthly volume with
                            positive/negative</p>
                    </div>

                    <div style="position:relative;display:inline-block;">
                        <button data-slot="dropdown-menu-trigger"
                            class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                            type="button" id="export_tabs_number_test" aria-haspopup="menu" aria-expanded="false"
                            data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                <path d="M12 15V3"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>

                        <div id="menu_export_tabs_number_test" class="menu-export" data-radix-popper-content-wrapper=""
                            dir="ltr"
                            style="position:absolute;left:-38px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                            <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                tabindex="-1" data-orientation="vertical"
                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                <div export_func="all_panels_positivity_rate_trends" export_type="pdf" role="menuitem"
                                    data-slot="dropdown-menu-item" data-variant="default"
                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                    tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2"
                                        aria-hidden="true">
                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                        <path d="M10 9H8"></path>
                                        <path d="M16 13H8"></path>
                                        <path d="M16 17H8"></path>
                                    </svg>Export as PDF</div>
                                <div export_func="all_panels_positivity_rate_trends" export_type="csv" role="menuitem"
                                    data-slot="dropdown-menu-item" data-variant="default"
                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                    tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2"
                                        aria-hidden="true">
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
                <div class="recharts-responsive-container" style="width: 100%; height: 240px">
                    <div style="width: 100%; height: 0px; overflow: visible;">
                        <div class="recharts-wrapper"
                            style="position: relative; cursor: default; width: 100%; height: 240px;">
                            <div
                                style="width:100%;  justify-content:center; align-items:center; margin-top:0px; height: 260px; width:100%">
                                <div id="tooltipChartVolumeTabs" class="recharts-tooltip-wrapper"></div>
                                <canvas id="chart_volume_tabs" width="100%" height="100%"
                                    style="display: block; box-sizing: border-box; height: 260px; width: 100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    //Chart 1
    var tooltipTabsTestResult = document.getElementById('tooltipTabsTestResult');
    var ctx = document.getElementById('chart_test_results_tabs');
    var chart_test_results_tabs = new Chart(ctx, {
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
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: "nearest",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        var elementTag = document.getElementById('chart_test_results_tabs');
                        var tooltipModel = context.tooltip;

                        if (tooltipModel.opacity === 0) {
                            tooltipTabsTestResult.classList.remove("active");
                            return;
                        } else {
                            tooltipTabsTestResult.classList.add("active");
                        }
                        var dataPoints = tooltipModel.dataPoints;

                        var total = 0;
                        dataPoints.forEach(dp => total += dp.raw || 0);
                        var formattedTotal = parseFloat(total.toFixed(2));


                        var label_tag = dataPoints[0].label;
                        var parts = label_tag.split(" ");
                        var pc_positive_rate = parts[0];
                        var pc_name = parts[1];

                        var innerHtml = `
                                                    <div class="font-medium" style="font-weight:bold; margin-bottom:4px;">
                                                    ${pc_name}
                                                    </div>
                                                `;

                        dataPoints.forEach(dp => {
                            innerHtml += `
                                                    <div class="text-sm text-muted-foreground">
                                                        <span style="font-size:12px;"> ${pc_positive_rate} (${parseFloat((dp.raw || 0).toFixed(2))} Sample)</span>
                                                    </div>
                                                    `;
                        });

                        tooltipTabsTestResult.innerHTML = innerHtml;
                        tooltipTabsTestResult.style.display = 'block';
                        tooltipTabsTestResult.style.left = tooltipModel.caretX + 'px';
                        tooltipTabsTestResult.style.top = tooltipModel.caretY + 'px';
                    }
                }
            },
            cutout: '60%',
            layout: {
                padding: 0
            },
        },
        plugins: [{
            id: 'centerText',
            beforeDraw(chart) {
                const {
                    width
                } = chart;
                const {
                    height
                } = chart;
                const ctx = chart.ctx;
                ctx.restore();

                const dataset = chart.data.datasets[0].data;
                const total = dataset.reduce((a, b) => a + b, 0);

                ctx.font = fontCenterText_Pie;
                ctx.fillStyle = "#000";
                ctx.textAlign = "center";
                ctx.textBaseline = "middle";
                ctx.fillText(total, width / 2, height / 2 - 10);

                ctx.font = fontCenterTextTip_Pie;
                ctx.fillStyle = "#666";
                ctx.fillText("Total Samples", width / 2, height / 2 + 15);

                ctx.save();
            }
        }, noDataPlugin]
    });

    //Chart 2
    var tooltipChartVolumeTabs = document.getElementById('tooltipChartVolumeTabs');
    var ctx2 = document.getElementById('chart_volume_tabs').getContext('2d');
    var chart_volume_tabs = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: [],
            datasets: []
        },
        plugins: [noDataPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            layout: {
                padding: 33
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        autoSkip: true,
                        maxRotation: 0,
                        maxTicksLimit: 15
                    },
                    grid: grid_x_y
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: grid_x_y,
                    ticks: {
                        font: {
                            family: 'Inter',
                            size: 13
                        },
                        color: '#555'
                    },
                    title: {
                        display: true,
                        text: 'Sample Count',
                        color: '#666',
                        font: {
                            size: 13
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: legend_labels,
                },
                title: {
                    display: false
                },
                tooltip: {
                    mode: "index",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        let elementTag = document.getElementById('chart_volume_tabs');
                        let y = elementTag.getBoundingClientRect().top + window.scrollY;
                        let tooltipModel = context.tooltip;

                        if (tooltipModel.opacity === 0) {
                            tooltipChartVolumeTabs.classList.remove("active");
                            return;
                        } else {
                            tooltipChartVolumeTabs.classList.add("active");
                        }
                        let dataPoints = tooltipModel.dataPoints;

                        let total = 0;
                        dataPoints.forEach(dp => total += dp.raw || 0);
                        let formattedTotal = parseFloat(total.toFixed(2));

                        let innerHtml = `
                                                    <div class="font-medium" style="font-weight:bold; margin-bottom:4px;">
                                                    ${dataPoints[0].label}
                                                    </div>
                                                `;

                        dataPoints.forEach(dp => {
                            let color = dp.element?.options?.backgroundColor || dp.dataset
                                .backgroundColor;
                            innerHtml += `
                                                    <div class="text-sm">
                                                        <span style="color:${color}; font-size:12px;"> ${dp.dataset.label} : ${parseFloat((dp.raw || 0).toFixed(2))}</span>
                                                    </div>
                                                    `;
                        });

                        tooltipChartVolumeTabs.innerHTML = innerHtml;
                        tooltipChartVolumeTabs.style.display = 'block';

                        tooltipChartVolumeTabs.style.left = tooltipModel.caretX + 'px';
                        //tooltipDaily.style.top = (y + tooltipModel.caretY) + 'px';
                    }
                }
            }
        }
    });
</script>