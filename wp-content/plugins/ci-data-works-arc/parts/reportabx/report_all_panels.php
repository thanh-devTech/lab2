<script>
    var total_samples_chart1 = 0;
    var verticalLinePlugin = {
        id: 'verticalLine',
        afterDraw(chart) {
            if (chart.tooltip?._active?.length) {
                const ctx = chart.ctx;
                const activePoint = chart.tooltip._active[0];
                const x = activePoint.element.x;
                const topY = chart.scales.y.top;
                const bottomY = chart.scales.y.bottom;

                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, topY);
                ctx.lineTo(x, bottomY);
                ctx.lineWidth = 1;
                ctx.strokeStyle = '#aaa';
                ctx.stroke();
                ctx.restore();
            }
        }
    };

    function handleSingleSeriesLegendClick(e, legendItem) {
        var chart = this.chart;
        var datasetIndex = legendItem.datasetIndex;
        var visibleCount = 0;

        if (!chart || !chart.data || !Array.isArray(chart.data.datasets)) {
            return;
        }

        chart.data.datasets.forEach(function(dataset, index) {
            if (chart.isDatasetVisible(index)) {
                visibleCount++;
            }
        });

        if (visibleCount === 1 && chart.isDatasetVisible(datasetIndex)) {
            chart.data.datasets.forEach(function(dataset, index) {
                chart.getDatasetMeta(index).hidden = null;
            });
        } else {
            chart.data.datasets.forEach(function(dataset, index) {
                chart.getDatasetMeta(index).hidden = index === datasetIndex ? null : true;
            });
        }

        chart.update();
    }
</script>
<div class="space-y-6" id="boxes_container">
    <div id="box_test_type" data-slot="card"
        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex-1">
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-activity h-5 w-5 text-primary" aria-hidden="true">
                            <path
                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                            </path>
                        </svg>Test Type &amp; Positivity

                        <svg id="filter_all_test_type_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18"
                            viewBox="0 0 64 18" aria-hidden="true" role="img" style="/* display: none; */">
                            <circle cx="9" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite"
                                    begin="0s"></animate>
                            </circle>
                            <circle cx="32" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite"
                                    begin="0.15s"></animate>
                            </circle>
                            <circle cx="55" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite"
                                    begin="0.3s"></animate>
                            </circle>
                        </svg>
                    </h4>
                    <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Totals, distribution, and positivity for the selected date range</p>
                </div>


                <div class="flex items-center gap-2">
                    <select id="month_range_test_type" class="sel_date_all_panel">
                        <?php foreach ($filter_date_select as $item): ?>
                            <option value="<?php echo esc_attr($item['value']); ?>">
                                <?php echo esc_html($item['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="position:relative;display:inline-block;">
                        <button data-slot="dropdown-menu-trigger" id="export_all_panels_test_type"
                            class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                            type="button" aria-haspopup="menu" aria-expanded="false" data-state="closed">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                <path d="M12 15V3"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>Export
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1"
                                aria-hidden="true">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>

                        <div id="menu_export_all_panels_test_type" class="menu-export"
                            data-radix-popper-content-wrapper="" dir="ltr"
                            style="position:absolute;left: -38px;;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                            <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                tabindex="-1" data-orientation="vertical"
                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                <div export_func="all_test_type" export_type="pdf" role="menuitem"
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
                                <div export_func="all_test_type" export_type="csv" role="menuitem"
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
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="relative">
                    <div class="relative">
                        <div class="recharts-responsive-container" style="width: 100%; height: 260px; min-width: 0px;">
                            <div class="recharts-wrapper"
                                style="position: relative; cursor: default; width: 100%; height: 260px;">
                                <div id="tooltipAllTestResult" class="recharts-tooltip-wrapper"></div>
                                <canvas id="chartAllTestResult" width="100%" height="320"></canvas>
                            </div>
                            <script>
                                var tooltipAllTestResult = document.getElementById('tooltipAllTestResult');
                                var ctx_chart_all_test_resull = document.getElementById('chartAllTestResult').getContext(
                                    '2d');
                                var chartAllTestResult = new Chart(ctx_chart_all_test_resull, {
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
                                                    var elementTag = document.getElementById(
                                                        'chartAllTestResult');
                                                    var tooltipModel = context.tooltip;

                                                    if (tooltipModel.opacity === 0) {
                                                        tooltipAllTestResult.classList.remove("active");
                                                        return;
                                                    } else {
                                                        tooltipAllTestResult.classList.add("active");
                                                    }
                                                    var dataPoints = tooltipModel.dataPoints;

                                                    var index = dataPoints[0].dataIndex;
                                                    var data_cur = table_data_chart1[index];
                                                    tooltipAllTestResult.innerHTML = `
                                                    <div><strong>${dataPoints[0].label}</strong></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Volume:</div> <span class="text-right font-medium">${data_cur.total} samples (${data_cur.total_pc}%)</span></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Positivity Rate:</div> <span class="text-right font-medium">${data_cur.positive_pc}%</span></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Positive Results:</div><span class="text-right font-medium">${data_cur.positive} samples</span></div>
                                                    `;

                                                    tooltipAllTestResult.style.display = 'block';

                                                    tooltipAllTestResult.style.left = tooltipModel.caretX +
                                                        'px';
                                                    tooltipAllTestResult.style.top = tooltipModel.caretY + 'px';
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
                                                width
                                            } = chart;
                                            const {
                                                height
                                            } = chart;
                                            const ctx = chart.ctx;
                                            ctx.restore();

                                            ctx.font = fontCenterText_Pie;
                                            ctx.fillStyle = "#000";
                                            ctx.textAlign = "center";
                                            ctx.textBaseline = "middle";
                                            ctx.fillText(total_samples_chart1, width / 2, height / 2 - 10);

                                            ctx.font = fontCenterTextTip_Pie;
                                            ctx.fillStyle = "#666";
                                            ctx.fillText("Total Samples", width / 2, height / 2 + 15);

                                            ctx.save();
                                        }
                                    }, noDataPlugin]
                                });
                            </script>
                        </div>
                    </div>
                </div>
                <div class="space-y-4 pb-4">
                    <div id="table_test_type">
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-6 pt-4 border-t">
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold" id="rp_all_total_samples">00</div>
                            <div class="text-xs text-gray-600 mt-1">Total Samples</div>
                        </div>
                        <div class="text-center p-3 bg-primary/10 rounded-lg">
                            <div class="text-2xl font-bold text-primary" id="rp_all_total_positive">00</div>
                            <div class="text-xs text-gray-600 mt-1">Positive Results</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div id="box_volume_trend_all_panels" data-slot="card"
        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
            <div>
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-trending-up h-5 w-5 text-primary" aria-hidden="true">
                        <path d="M16 7h6v6"></path>
                        <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                    </svg>Volume Trend<span class="show_sel_date text-sm text-muted-foreground ml-2">(----)</span></h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Daily sample volumes by
                    result type</p>
            </div>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
            <div class="recharts-responsive-container" style="width: 100%; height: 320px; min-width: 0px;">
                <div id="tooltipChartBar" class="recharts-tooltip-wrapper"></div>
                <canvas id="chartBar" style="width:100%;"></canvas>
                <script>
                    var tooltipChartBar = document.getElementById('tooltipChartBar');
                    var chartBar = new Chart(document.getElementById('chartBar'), {
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
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: legend_labels
                                },
                                tooltip: {
                                    mode: "index",
                                    intersect: false,
                                    enabled: false,
                                    external: function(context) {
                                        let elementTag = document.getElementById('chartBar');
                                        let y = elementTag.getBoundingClientRect().top + window.scrollY;
                                        let tooltipModel = context.tooltip;

                                        if (tooltipModel.opacity === 0) {
                                            tooltipChartBar.classList.remove("active");
                                            return;
                                        } else {
                                            tooltipChartBar.classList.add("active");
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
                                        innerHtml += `<div class="text-sm" style="font-weight:500;">Total: ${formattedTotal}</div>`;
                                        dataPoints.forEach(dp => {
                                            let color = dp.element?.options?.backgroundColor || dp
                                                .dataset.backgroundColor;
                                            innerHtml += `
                                                    <div class="text-sm">
                                                        <span style="color:${color}; font-size:12px;"> ${dp.dataset.label}: ${parseFloat((dp.raw || 0).toFixed(2))}</span>
                                                    </div>
                                                    `;
                                        });

                                        tooltipChartBar.innerHTML = innerHtml;
                                        tooltipChartBar.style.display = 'block';

                                        tooltipChartBar.style.left = tooltipModel.caretX + 'px';
                                        //tooltipDaily.style.top = (y + tooltipModel.caretY) + 'px';
                                    }
                                }
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
                            }
                        }
                    });
                </script>
            </div>
        </div>
    </div>
    <div id="box_positivity_rate_trends" data-slot="card"
        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-trending-up h-5 w-5 text-primary" aria-hidden="true">
                            <path d="M16 7h6v6"></path>
                            <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                        </svg>Positivity Rate Trends</h4>
                    <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Six-month positivity trends by selected view</p>
                </div>
                <div style="position:relative;display:inline-block;">
                    <button data-slot="dropdown-menu-trigger"
                        class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                        type="button" id="export_positivity" aria-haspopup="menu" aria-expanded="false"
                        data-state="closed">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                            <path d="M12 15V3"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="m7 10 5 5 5-5"></path>
                        </svg>Export
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>

                    <div id="menu_export_positivity" class="menu-export" data-radix-popper-content-wrapper="" dir="ltr"
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
                                class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground  focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
            <div class="recharts-responsive-container" style="width: 100%; height: 350px; min-width: 0px;">
                <div id="tooltipChartLine" class="recharts-tooltip-wrapper"></div>
                <canvas id="chartLine" style="width:100%"></canvas>
                <script>
                    var tooltipChartLine = document.getElementById('tooltipChartLine');
                    var ctxChartLine = document.getElementById('chartLine');
                    var chartLine = new Chart(ctxChartLine, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        plugins: [noDataPlugin, verticalLinePlugin],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            layout: {
                                padding: 33
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            stacked: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    onClick: handleSingleSeriesLegendClick,
                                    labels: {
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 8,
                                        boxHeight: 8,
                                        font: {
                                            size: 13,
                                        },
                                        padding: 20,
                                        color: '#5a5a5aff'
                                    }
                                },
                                tooltip: {
                                    mode: "index",
                                    intersect: false,
                                    enabled: false,
                                    external: function(context) {
                                        let elementTag = document.getElementById('chartLine');
                                        let y = elementTag.getBoundingClientRect().top + window.scrollY;
                                        let tooltipModel = context.tooltip;

                                        if (tooltipModel.opacity === 0) {
                                            tooltipChartLine.classList.remove("active");
                                            return;
                                        } else {
                                            tooltipChartLine.classList.add("active");
                                        }
                                        let dataPoints = tooltipModel.dataPoints;

                                        let total = 0;
                                        dataPoints.forEach(dp => total += dp.raw || 0);
                                        let formattedTotal = parseFloat(total.toFixed(2));

                                        let innerHtml = `
                                                    <div class="font-medium" style="font-weight:bold; margin-bottom:4px;">
                                                    Month : ${dataPoints[0].label}
                                                    </div>
                                                `;

                                        dataPoints.forEach(dp => {
                                            let color = dp.element?.options?.backgroundColor || dp
                                                .dataset.backgroundColor;
                                            innerHtml += `
                                                    <div class="text-sm">
                                                        <span style="color:${color}; font-size:12px;"> ${dp.dataset.label}: ${parseFloat((dp.raw || 0).toFixed(2))}</span>
                                                    </div>
                                                    `;
                                        });

                                        tooltipChartLine.innerHTML = innerHtml;
                                        tooltipChartLine.style.display = 'block';

                                        tooltipChartLine.style.left = tooltipModel.caretX + 'px';
                                        //tooltipDaily.style.top = (y + tooltipModel.caretY) + 'px';
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: grid_x_y,
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: grid_x_y,
                                    title: {
                                        display: true,
                                        text: 'Positivity Rate (%)'
                                    },
                                }
                            },
                            elements: {
                                point: {
                                    radius: 5,
                                    hoverRadius: 5,
                                    hitRadius: 6,
                                    z: 10
                                }
                            }
                        }
                    });
                </script>
            </div>
            <div class="grid grid-cols-5 gap-3 mt-6 pt-4 border-t" id="chart_summary_current_list"></div>
        </div>
    </div>
    <div id="box_all_panels_c_ao" data-slot="card"
        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex-1">
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-building2 lucide-building-2 h-5 w-5 text-primary" aria-hidden="true">
                            <path d="M10 12h4"></path>
                            <path d="M10 8h4"></path>
                            <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                            <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                            <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                        </svg>Clinic Activity Overview</h4>
                    <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Clinic testing volumes with overall positivity by selected view</p>
                </div>
                <div class="flex items-center gap-2">
                    <select id="month_range_clinic_activity" class="sel_date_all_panel">
                        <?php foreach ($filter_date_select as $item): ?>
                            <option value="<?= esc_attr($item['value']); ?>">
                                <?= esc_html($item['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="position:relative;display:inline-block;">
                        <button data-slot="dropdown-menu-trigger"
                            class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                            type="button" id="export_cinic_ao" aria-haspopup="menu" aria-expanded="false"
                            data-state="closed">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                <path d="M12 15V3"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>Export
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1"
                                aria-hidden="true">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>
                        <div id="menu_export_cinic_ao" class="menu-export" data-radix-popper-content-wrapper=""
                            dir="ltr"
                            style="position:absolute;left:-38px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                            <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                tabindex="-1" data-orientation="vertical"
                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                <div export_func="all_panels_clinic_activity" export_type="pdf" role="menuitem"
                                    data-slot="dropdown-menu-item" data-variant="default"
                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground  focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
                                <div export_func="all_panels_clinic_activity" export_type="csv" role="menuitem"
                                    data-slot="dropdown-menu-item" data-variant="default"
                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground  focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 clinic_tr_table_header">
                            <th class="text-left py-3 px-4 font-medium text-gray-700 text-base sortable cursor-pointer" data-sort="clinic">
                                Clinic
                                <span class="sort-icon inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                        <path d="m21 16-4 4-4-4"></path>
                                        <path d="M17 20V4"></path>
                                        <path d="m3 8 4-4 4 4"></path>
                                        <path d="M7 4v16"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                        <path d="m5 12 7-7 7 7"></path>
                                        <path d="M12 19V5"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                        <path d="M12 5v14"></path>
                                        <path d="m19 12-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </th>
                            <th class="text-center py-3 px-4 font-medium text-gray-700 text-base sortable cursor-pointer" data-sort="total_volume">
                                Total Volume
                                <span class="sort-icon inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                        <path d="m21 16-4 4-4-4"></path>
                                        <path d="M17 20V4"></path>
                                        <path d="m3 8 4-4 4 4"></path>
                                        <path d="M7 4v16"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                        <path d="m5 12 7-7 7 7"></path>
                                        <path d="M12 19V5"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                        <path d="M12 5v14"></path>
                                        <path d="m19 12-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </th>
                            <th class="text-center py-3 px-4 font-medium text-gray-700 text-base sortable cursor-pointer" data-sort="positivity_rate">
                                Positivity Rate
                                <span class="sort-icon inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                        <path d="m21 16-4 4-4-4"></path>
                                        <path d="M17 20V4"></path>
                                        <path d="m3 8 4-4 4 4"></path>
                                        <path d="M7 4v16"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                        <path d="m5 12 7-7 7 7"></path>
                                        <path d="M12 19V5"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                        <path d="M12 5v14"></path>
                                        <path d="m19 12-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </th>
                            <th class="text-center py-3 px-4 font-medium text-gray-700 text-base sortable cursor-pointer" data-sort="top_panel">
                                Top Panel
                                <span class="sort-icon inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                        <path d="m21 16-4 4-4-4"></path>
                                        <path d="M17 20V4"></path>
                                        <path d="m3 8 4-4 4 4"></path>
                                        <path d="M7 4v16"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                        <path d="m5 12 7-7 7 7"></path>
                                        <path d="M12 19V5"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                        <path d="M12 5v14"></path>
                                        <path d="m19 12-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="clinic_ao_table"></tbody>
                </table>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-6 pt-4 border-t">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div id="clinic_ao_active" class="text-2xl font-bold">0</div>
                    <div class="text-xs text-gray-600 mt-1">Active Clinics</div>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div id="clinic_ao_total_samples" class="text-2xl font-bold">0</div>
                    <div class="text-xs text-gray-600 mt-1">Total Samples</div>
                </div>
                <div class="text-center p-3 bg-primary/10 rounded-lg">
                    <div id="clinic_ao_avg_positivity" class="text-2xl font-bold text-primary">00.0%</div>
                    <div class="text-xs text-gray-600 mt-1">Avg Positivity</div>
                </div>
            </div>
        </div>
    </div>
    <div id="box_critical_findings" data-slot="card"
        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-critical-md">
        <div data-slot="card-header"
            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex-1">
                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-triangle-alert h-5 w-5 text-red-600" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>Critical Findings Summary
                        <span id="tt_cf" data-slot="badge"
                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-destructive text-white [a&amp;]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 ml-2">
                            0 Total</span>
                    </h4>
                    <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Detected High-Risk
                        Pathogens and Resistance Markers</p>
                </div>
                <div class="flex items-center gap-2">

                    <select id="month_range_critical" name="month_range_critical" class="sel_date_all_panel">
                        <?php foreach ($filter_date_select as $item): ?>
                            <option value="<?php echo esc_attr($item['value']); ?>">
                                <?php echo esc_html($item['text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="position:relative;display:inline-block;">
                        <button data-slot="dropdown-menu-trigger"
                            class="btn-export cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                            type="button" id="export_cirtical_findings" aria-haspopup="menu" aria-expanded="false"
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

                        <div id="menu_export_cirtical_findings" class="menu-export" data-radix-popper-content-wrapper=""
                            dir="ltr"
                            style="position:absolute;left:-38px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                            <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                tabindex="-1" data-orientation="vertical"
                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                <div export_func="all_panels_critical_findings" export_type="pdf" role="menuitem"
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
                                <div export_func="all_panels_critical_findings" export_type="csv" role="menuitem"
                                    data-slot="dropdown-menu-item" data-variant="default"
                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground  focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div data-slot="table-container" class="relative w-full overflow-x-auto">
                    <table data-slot="table" class="w-full caption-bottom text-sm" id="table_critical_findings_main">
                        <thead data-slot="table-header" class="[&amp;_tr]:border-b">
                            <tr data-slot="table-row"
                                class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-gray-50">
                                <th data-slot="table-head"
                                    class="h-10 px-2 text-left text-left align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-semibold text-gray-900">
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
                        <tbody id="table_critical_findings" data-slot="table-body"
                            class="[&amp;_tr:last-child]:border-0">
                        </tbody>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
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

</div>

<script>
    var table_data_chart1 = [];
    var tabs = <?php echo json_encode($tabs); ?>;
    var groups_mapping = <?php echo json_encode($groups_mapping); ?>;
    var tr_icon_up =
        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-4 w-4 flex-shrink-0 text-red-600" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>`;
    var tr_icon_down =
        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-4 w-4 flex-shrink-0 text-green-600" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>`;



    function get_tr_icon(status_compare) {
        if (status_compare.arrow == "up") {
            return tr_icon_up;
        } else if (status_compare.arrow == "equal") {
            return "";
        } else if (status_compare.arrow == "down") {
            return tr_icon_down;
        }
    }

    function get_tooltip_cf_text(assens_text) {
        if (!can_view_results) return '';
        let html = `<div class="space-y-1"><p class="font-semibold text-xs text-gray-700">Accession Numbers:</p>`;
        let arrs = assens_text.split(",");
        arrs.forEach(function(item) {
            item = item.trim();
            if (item.length > 0) {
                html += `<a href="?tr_action=ci_results_pdf&assen=${item}&btype=pcr&view=1" target=_blank class="block text-xs font-mono text-gray-600 hover:text-red-600 hover:underline">${item.trim()}</a>`;
            }
        });
        html += `</div>`;
        return html;
    }

    function get_count_compare(num_cp) {
        return '<span class="text-xs opacity-70">(' + num_cp + ')</span>';
    }

    function LoadChartAllTestResult(actDone = null) {
        $("#filter_all_test_type_loading").show();
        showLoading("box_test_type");
        showLoading("box_volume_trend_all_panels");

        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_all_panels',
            sub_ajax: 'AllTestResult',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            view_by: view_by
        }, function(response) {
            var res = JSON.parse(response);
            total_samples_chart1 = res.total_samples;
            chartAllTestResult.data.labels = res.chart.labels;
            chartAllTestResult.data.datasets = res.chart.datasets;
            styleDoughnutChart(chartAllTestResult);
            chartAllTestResult.update();

            table_data_chart1 = res.table;

            $("#table_test_type").empty();
            $.each(res.table, function(index, row) {
                var rowEmp = `
                    <div class="flex items-center justify-between gap-2 p-2 border-b border-gray-200 hover:bg-gray-50 transition-colors last:border-b-0">
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${row.color}"></div>
                            <span class="font-medium">${row.title}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm flex-shrink-0">
                            <span class="text-gray-600">${row.total} (${row.total_pc}%)</span>
                            <span class="font-medium text-red-700">${row.positive_pc}% positive</span>
                        </div>
                    </div>
                    `;
                $('#table_test_type').append(rowEmp);
            });
            $("#rp_all_total_samples").html(res.total_samples);
            $("#rp_all_total_positive").html(res.total_positive);

            chartBar.data = {
                labels: res.chartBar.labels,
                datasets: res.chartBar.datasets
            };
            applyRoundedTop(chartBar);
            chartBar.update();
            hideLoading('box_test_type');
            hideLoading("box_volume_trend_all_panels");
            $("#filter_all_test_type_loading").hide();
            $("#filter_all_loading").hide();
            actDone?.();
        });
    }

    function LoadChartVolumeTrend(actDone = null) {
        showLoading("box_positivity_rate_trends");
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_all_panels',
            sub_ajax: 'VolumeTrend',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            view_by: view_by
        }, function(response) {
            var res = JSON.parse(response);
            chartLine.data = {
                labels: res.chartLine.labels,
                datasets: res.chartLine.datasets,
            };
            chartLine.update();

            $("#chart_summary_current_list").empty();
            $.each(res.chartLine.datasets, function(index, row) {
                const rate = row.data[5] ?? 0;
                var rowEmp = `
                        <div class="text-center p-3 bg-red-50 rounded-lg" style="background-color:${row.bk_color}">
                            <div class="text-xl font-bold" style="color: ${row.borderColor}">${rate}%</div>
                            <div class="text-xs text-gray-600 mt-1">${row.label}(Current)</div>
                        </div>
                    `;
                $('#chart_summary_current_list').append(rowEmp);
            });
            hideLoading("box_positivity_rate_trends");
            actDone?.();
        });
    }

    function LoadActivityOverview(actDone = null) {
        showLoading("box_all_panels_c_ao");
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_all_panels',
            sub_ajax: 'ActivityOverview',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            view_by: view_by
        }, function(response) {
            var res = JSON.parse(response);
            var clinic_activity_overview = res.clinic_activity_overview;

            $('.clinic_tr_table_header').find('.th_clinic').remove();
            Object.entries(tabs).forEach(([key, value]) => {
                if (!["all", "uyr", "review", "contact"].includes(key)) {
                    if (view_by === 'type') {
                        $('.clinic_tr_table_header').append(`
                            <th class="text-center th_clinic py-3 px-4 font-medium text-gray-700 text-sm sortable cursor-pointer" data-sort="panel_${key}">
                                ${value.title}
                                <span class="sort-icon inline-flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                        <path d="m21 16-4 4-4-4"></path>
                                        <path d="M17 20V4"></path>
                                        <path d="m3 8 4-4 4 4"></path>
                                        <path d="M7 4v16"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                        <path d="m5 12 7-7 7 7"></path>
                                        <path d="M12 19V5"></path>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                        <path d="M12 5v14"></path>
                                        <path d="m19 12-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </th>
                        `);
                    } else if (value['groupids']) {
                        value['groupids'].forEach(function(groupid) {
                            const group_title = groups_mapping[groupid] ? groups_mapping[groupid]['short_name'] : groupid;
                            $('.clinic_tr_table_header').append(`
                                <th class="text-center th_clinic py-3 px-4 font-medium text-gray-700 text-sm sortable cursor-pointer" data-sort="panel_${key}_${groupid}">
                                    ${group_title}
                                    <span class="sort-icon inline-flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up-down active">
                                            <path d="m21 16-4 4-4-4"></path>
                                            <path d="M17 20V4"></path>
                                            <path d="m3 8 4-4 4 4"></path>
                                            <path d="M7 4v16"></path>
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-up">
                                            <path d="m5 12 7-7 7 7"></path>
                                            <path d="M12 19V5"></path>
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 lucide lucide-arrow-down">
                                            <path d="M12 5v14"></path>
                                            <path d="m19 12-7 7-7-7"></path>
                                        </svg>
                                    </span>
                                </th>
                            `);
                        });
                    }
                }
            });
            $('#clinic_ao_table').empty();
            $.each(clinic_activity_overview.clinic_summary, function(index, row) {
                if (row.total_volume == 0) return true;
                var cells = '';
                let tabkey = []
                if (view_by === 'type') {
                    tabkey = Object.keys(tabs).filter(k => !["all", "uyr", "review", "contact"].includes(k));
                } else {
                    Object.entries(tabs).forEach(([key, value]) => {
                        if (!["all", "uyr", "review", "contact"].includes(key)) {
                            if (value['groupids']) {
                                value['groupids'].forEach(function(groupid) {
                                    const groupKey = groups_mapping[groupid] ? groups_mapping[groupid]['short_name'] : groupid;
                                    tabkey.push(groupKey);
                                });
                            }
                        }
                    });
                }
                $.each(row.panels, function(i, val) {
                    cells += `<td class="text-center py-3 px-4 text-sm text-gray-600">${val}</td>`;
                });

                var rowEmp = `
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900">${row.clinic}</div>
                        </td>
                        <td class="text-center py-3 px-4">
                            <div class="font-medium">${row.total_volume}</div>
                        </td>
                        <td class="text-center py-3 px-4">
                            <span data-slot="badge"
                                class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden bg-primary/10 text-primary border-primary/20">
                                ${row.positivity_rate}%
                            </span>
                        </td>
                        <td class="text-center py-3 px-4">
                            <span data-slot="badge"
                                class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90">
                                ${row.top_panel}
                            </span>
                        </td>
                        ${cells}
                    </tr>
                `;

                $('#clinic_ao_table').append(rowEmp);
            });
            $("#clinic_ao_active").html(clinic_activity_overview.total_clinics);
            $("#clinic_ao_total_samples").html(clinic_activity_overview.total_all_samples);
            $("#clinic_ao_avg_positivity").html(clinic_activity_overview.avg_positivity + "%");
            hideLoading("box_all_panels_c_ao");
            actDone?.();
        });
    }

    function LoadCriticalFindings(actDone = null) {
        showLoading("box_critical_findings");
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_all_panels',
            sub_ajax: 'CriticalFindings',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid
        }, function(response) {
            var res = JSON.parse(response);
            $("#table_critical_findings").empty();
            var cfs = res.critical_findings_summary;


            $.each(cfs.table, function(index, itemd) {

                var row = itemd.data;

                var data_tr = itemd.trend;
                var data_cp = itemd.compare;
                var itemMRSA = (row.MRSA == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.MRSA)}</div>${get_tr_icon(data_tr.MRSA)}` :
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-red-100 text-red-800 border border-red-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.MRSA_assens}"><span class="font-semibold">${row.MRSA}</span>${get_count_compare(data_cp.MRSA)}</div>${get_tr_icon(data_tr.MRSA)}`;
                var itemVRE = (row.VRE == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.VRE)}</div>${get_tr_icon(data_tr.VRE)}` :
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-red-100 text-red-800 border border-red-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.VRE_assens}"><span class="font-semibold">${row.VRE}</span>${get_count_compare(data_cp.VRE)}</div>${get_tr_icon(data_tr.VRE)}`;
                var itemESBL = (row.ESBL == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.ESBL)}</div>${get_tr_icon(data_tr.ESBL)}` :
                    `<div class="inline-flex items-center justify-center min-w-[60px] px-3 py-1 rounded-md bg-orange-100 text-orange-800 border border-orange-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.ESBL_assens}"><span class="font-semibold">${row.ESBL}</span>${get_count_compare(data_cp.ESBL)}</div>${get_tr_icon(data_tr.ESBL)}`;
                var itemCRE = (row.CRE == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.CRE)}</div>${get_tr_icon(data_tr.CRE)}` :
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-red-100 text-red-800 border border-red-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.CRE_assens}"><span class="font-semibold">${row.CRE}</span>${get_count_compare(data_cp.CRE)}</div>${get_tr_icon(data_tr.CRE)}`;
                var itemMDR_AB = (row.MDR_AB == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.MDR_AB)}</div>${get_tr_icon(data_tr.MDR_AB)}` :
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-red-100 text-red-800 border border-red-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.MDR_AB_assens}"><span class="font-semibold">${row.MDR_AB}</span>${get_count_compare(data_cp.MDR_AB)}</div>${get_tr_icon(data_tr.MDR_AB)}`;
                var itemMDR_PA = (row.MDR_PA == 0) ?
                    `<div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-white text-gray-700 border border-gray-200"><span class="font-semibold">0</span>${get_count_compare(data_cp.MDR_PA)}</div>${get_tr_icon(data_tr.MDR_PA)}` :
                    `<div class="inline-flex items-center justify-center min-w-[60px] px-3 py-1 rounded-md bg-orange-100 text-orange-800 border border-orange-200 cursor-help tooltip-trigger" data-tooltip-callback="get_tooltip_cf_text" data-tooltip="${row.MDR_PA_assens}"><span class="font-semibold">${row.MDR_PA}</span>${get_count_compare(data_cp.MDR_PA)}</div>${get_tr_icon(data_tr.MDR_PA)}`;

                var rowEmp = `
                        <tr data-slot="table-row"
                                class="data-[state=selected]:bg-muted border-b transition-colors hover:bg-gray-50">
                                <td data-slot="table-cell"
                                    class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] font-medium">
                                    <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-building2 lucide-building-2 h-4 w-4 text-gray-500"
                                            aria-hidden="true">
                                            <path d="M10 12h4"></path>
                                            <path d="M10 8h4"></path>
                                            <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                            <path
                                                d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2">
                                            </path>
                                            <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                        </svg>${itemd.clinic}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.MRSA}" data-sortval2="${data_cp.MRSA}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                   <div class="inline-flex items-center gap-1.5">${itemMRSA}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.VRE}" data-sortval2="${data_cp.VRE}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">${itemVRE}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.ESBL}" data-sortval2="${data_cp.ESBL}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">${itemESBL}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.CRE}" data-sortval2="${data_cp.CRE}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">${itemCRE}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.MDR_AB}" data-sortval2="${data_cp.MDR_AB}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">${itemMDR_AB}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.MDR_PA}" data-sortval2="${data_cp.MDR_PA}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">${itemMDR_PA}</div>
                                </td>

                                <td data-slot="table-cell" data-sortval="${row.Total}" data-sortval2="${data_cp.Total}" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 min-w-[60px]">
                                        <span class="inline-flex items-center gap-1.5">${row.Total}</span>${get_count_compare(row.Total)}
                                        </span>
                                        ${get_tr_icon(data_tr.Total)}
                                    </div>
                                </td>
                            </tr>
                    `;
                $('#table_critical_findings').append(rowEmp);
            });

            $("#t_mrsa").html(res.critical_findings_summary.total_summary.MRSA);
            $("#t_vre").html(res.critical_findings_summary.total_summary.VRE);
            $("#t_esbl").html(res.critical_findings_summary.total_summary.ESBL);
            $("#t_cre").html(res.critical_findings_summary.total_summary.CRE);
            $("#t_mdr_ab").html(res.critical_findings_summary.total_summary.MDR_AB);
            $("#t_mdr_pa").html(res.critical_findings_summary.total_summary.MDR_PA);
            $("#tt_cf").html(cfs.total_all + " Total");
            hideLoading("box_critical_findings");
            $('#table_critical_findings_main').sort_table();
            actDone?.();
        });
    }

    async function report_all_range_date() {
        disableExportAndFilterDate()
        LoadChartAllTestResult();
        await delay(200);
        LoadChartVolumeTrend();
        await delay(200);
        LoadActivityOverview();
        await delay(200);
        LoadCriticalFindings();

        disableExportAndFilterDate(false);
        return false;
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function disableExportAndFilterDate(disable = true) {
        if (disable) {
            $(".btn-export").addClass("opacity-50 pointer-events-none");
            $("#emp_month_range_clinic_activity, #emp_month_range_critical, #emp_month_range_test_type").addClass("opacity-50 pointer-events-none");
        } else {
            $(".btn-export").removeClass("opacity-50 pointer-events-none");
            $("#emp_month_range_clinic_activity, #emp_month_range_critical, #emp_month_range_test_type").removeClass("opacity-50 pointer-events-none");
        }
    }

    jQuery(function($) {

        $(".export_item").click(async function() {
            var export_func = $(this).attr("export_func");
            var export_type = $(this).attr("export_type");
            $(this).closest(".menu-export").hide();

            var $url = location.href + '&trajax=report_all_panels&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&month_range=' + month_range;
            $url += '&date_from=' + date_from;
            $url += '&date_to=' + date_to;
            $url += '&cid=' + cid;
            $url += '&view_by=' + view_by;

            var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');
            try {
                const response = await fetch($url);

                // 🔥 Lấy filename từ header
                const disposition = response.headers.get("Content-Disposition");
                let filename = "download." + export_type;

                if (disposition && disposition.includes("filename=")) {
                    const match = disposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) filename = match[1];
                }

                const blob = await response.blob();

                const a = document.createElement("a");
                a.href = URL.createObjectURL(blob);
                a.download = filename; // giữ nguyên tên file
                a.click();

                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
            return false;
        });

        $(".sel_date_all_panel").change(function() {
            var val_date = $(this).val();
            month_range = val_date;
            report_all_range_date();
            update_all_select_report();
            if (val_date == "custom_range") {
                $("#daterange-btn-all-tab").addClass("flex").show();
                $("#daterange-btn-all-tab").data('daterangepicker').show();
            }
        });
        let clinicSort = {
            column: null,
            direction: null
        };

        function updateSortIcons(column, direction) {
            $('th.sortable').each(function() {
                const $iconContainer = $(this).find('.sort-icon');
                $iconContainer.find('svg').removeClass('active');
                $iconContainer.find('.lucide-arrow-up-down').addClass('active');
                $iconContainer.css('opacity', '0.4');
            });

            if (column && direction) {
                const $activeTh = $('th.sortable[data-sort="' + column + '"]');
                const $iconContainer = $activeTh.find('.sort-icon');

                $iconContainer.find('svg').removeClass('active');
                if (direction === 'asc') {
                    $iconContainer.find('.lucide-arrow-up').addClass('active');
                } else {
                    $iconContainer.find('.lucide-arrow-down').addClass('active');
                }

                $iconContainer.css('opacity', '1');
            }
        }

        function sortClinicTable(column, direction) {
            const $table = $('#clinic_ao_table');
            const $rows = $table.find('tr').toArray();

            $rows.sort(function(a, b) {
                let aVal = 0,
                    bVal = 0;

                if (column === 'clinic') {
                    aVal = $(a).find('td:eq(0)').text().trim().toLowerCase();
                    bVal = $(b).find('td:eq(0)').text().trim().toLowerCase();
                } else if (column === 'total_volume') {
                    aVal = parseInt($(a).find('td:eq(1)').text().replace(/[^0-9]/g, '')) || 0;
                    bVal = parseInt($(b).find('td:eq(1)').text().replace(/[^0-9]/g, '')) || 0;
                } else if (column === 'positivity_rate') {
                    aVal = parseFloat($(a).find('td:eq(2)').text().replace(/[^\d.]/g, '')) || 0;
                    bVal = parseFloat($(b).find('td:eq(2)').text().replace(/[^\d.]/g, '')) || 0;
                } else if (column === 'top_panel') {
                    aVal = $(a).find('td:eq(3)').text().trim().toLowerCase();
                    bVal = $(b).find('td:eq(3)').text().trim().toLowerCase();
                } else if (column.startsWith('panel_')) {
                    const $th = $('th[data-sort="' + column + '"]');
                    const panelIndex = $('th[data-sort^="panel_"]').index($th);
                    const cellIndex = 4 + panelIndex;

                    aVal = parseInt($(a).find('td:eq(' + cellIndex + ')').text().replace(/[^0-9]/g, '')) || 0;
                    bVal = parseInt($(b).find('td:eq(' + cellIndex + ')').text().replace(/[^0-9]/g, '')) || 0;
                }

                if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            $table.empty().append($rows);
            updateSortIcons(column, direction);
            clinicSort = {
                column,
                direction
            };
        }

        $(document).on('click', 'th.sortable', function() {
            const column = $(this).data('sort');
            let direction = 'asc';

            if (clinicSort.column === column && clinicSort.direction === 'asc') {
                direction = 'desc';
            }

            sortClinicTable(column, direction);
        });


        const originalLoadActivityOverview = LoadActivityOverview;
        LoadActivityOverview = function(actDone = null) {
            originalLoadActivityOverview(function() {
                updateSortIcons(null, null);
                if (actDone) actDone();
            });
        };
    });
</script>