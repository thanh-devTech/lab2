<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card" id="months_volume_trend">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <div class="p-1.5 bg-primary/10 rounded-lg"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-trending-up h-5 w-5 text-primary" aria-hidden="true">
                            <path d="M16 7h6v6"></path>
                            <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                        </svg>
                    </div>
                    12-Month Volume Trend
                </h4>
            </div>
            <div style="position:relative;display:inline-block;">
                <button id="export_volume_trend" class="btn-export inline-flex items-center justify-center whitespace-nowrap text-sm 
                    border bg-background text-foreground hover:bg-accent hover:text-accent-foreground
                    rounded-md gap-1.5 h-8 px-3 transition"
                    data="export_volume_trend"
                    type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-download h-4 w-4 mr-1">
                        <path d="M12 15V3" />
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <path d="m7 10 5 5 5-5" />
                    </svg>
                    Export
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-chevron-down h-3 w-3 ml-1">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
                <div id="menu_export_volume_trend" class="menu-export" style="display:none; position:absolute; right:0; top:100%;
                    margin-top:4px; z-index:50; min-width:160px;
                    background:white; border:1px solid #e5e7eb; border-radius:6px;
                    box-shadow:0 4px 12px rgba(0,0,0,.1); padding:4px;">
                    <div class="export_item cursor-pointer flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100"
                        export_func="export_volume_trend" export_type="pdf">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                            <path d="M10 9H8" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg>
                        Export as PDF
                    </div>
                    <div class="export_item cursor-pointer flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100"
                        export_func="export_volume_trend" export_type="csv">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                            <path d="M10 9H8" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg>
                        Export as CSV
                    </div>
                </div>
            </div>
        </div>
        <div class="text-sm text-gray-600" id="months_12_text">---</div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <div class="p-3 bg-gray-50 rounded-lg border">
                <div class="text-xs text-gray-600 mb-1">12-Month Avg Volume</div>
                <div class="text-xl font-bold text-gray-600" id="avg_volume">---</div>
                <div class="text-xs text-gray-600 mt-1">samples per month</div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg border">
                <div class="text-xs text-gray-600 mb-1">Avg Rejection Rate</div>
                <div class="text-xl font-bold text-orange-600" id="avg_rejected">---</div>
                <div class="text-xs text-orange-600 mt-1">of processed samples</div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg border">
                <div class="text-xs text-gray-600 mb-1">Growth Rate</div>
                <div class="text-xl font-bold text-green-700" id="r2">---</div>
                <div class="text-xs text-gray-600 mt-1">trend-based (linear regression)</div>
            </div>
        </div>
        <div id="custom-tooltip-volume" class="recharts-tooltip-wrapper"></div>
        <div id="volume_chart_contain" style="height:300px; width:100%; margin:3px 3px">
            <canvas id="sample_volume_chart" height="300">Loading</canvas>
        </div>
        <script>
            var tooltipVolumeChart = document.getElementById('custom-tooltip-volume');
            var ctx_volume_chart = document.getElementById("sample_volume_chart");
            var volume_chart = new Chart(ctx_volume_chart, {
                type: "bar",
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            mode: "index",
                            intersect: false,
                            enabled: false,
                            external: function(context) {
                                let elementTag = document.getElementById('daily_sample_volume_chart');
                                let tooltipModel = context.tooltip;

                                if (tooltipModel.opacity === 0) {
                                    tooltipVolumeChart.classList.remove("active");
                                    return;
                                } else {
                                    tooltipVolumeChart.classList.add("active");
                                }
                                let dataPoints = tooltipModel.dataPoints;

                                let total = dataPoints
                                    .filter(dp => dp.dataset.label !== 'Rejected')
                                    .reduce((s, dp) => s + (dp.raw || 0), 0);

                                let innerHtml = `<div style="font-weight:bold;margin-bottom:4px;">${dataPoints[0].label}</div>
                                <div style="font-size:12px;margin-bottom:3px;">Total: <strong>${total}</strong></div>`;

                                dataPoints.forEach(dp => {
                                    let color = dp.element?.options?.backgroundColor || dp.dataset.backgroundColor;
                                    innerHtml += `<div style="color:${color};font-size:12px;">${dp.dataset.label}: ${dp.raw || 0}</div>`;
                                });

                                tooltipVolumeChart.innerHTML = innerHtml;
                                tooltipVolumeChart.style.display = 'block';

                                let index = tooltipModel.dataPoints[0].dataIndex;
                                let offsetX = 0;
                                if (index > 9) {
                                    offsetX = -130;
                                } else {
                                    offsetX = 60;
                                }

                                let x = tooltipModel.caretX + offsetX;

                                tooltipVolumeChart.style.left = x + 'px';
                            }
                        },
                        legend: {
                            position: 'bottom',
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
                            },
                            onClick: function(e, legendItem, legend) {
                                const chart = legend.chart;
                                const clickedIndex = legendItem.datasetIndex;
                                const meta = chart.getDatasetMeta(clickedIndex);
                                let onlyOneVisible = true;
                                chart.data.datasets.forEach((ds, i) => {
                                    if (i !== clickedIndex && chart.isDatasetVisible(i)) {
                                        onlyOneVisible = false;
                                    }
                                });
                                if (!onlyOneVisible) {
                                    chart.data.datasets.forEach((ds, i) => {
                                        chart.setDatasetVisibility(i, i === clickedIndex);
                                    });
                                } else {
                                    chart.data.datasets.forEach((ds, i) => {
                                        chart.setDatasetVisibility(i, true);
                                    });
                                }
                                chart.update();
                            }
                        },
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            suggestedMax: 14,
                            title: {
                                display: true,
                                text: 'Sample count'
                            },
                        }
                    }
                }
            });
        </script>
    </div>
</div>
<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card" id="panels_volume_trend">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <div class="p-1.5 bg-primary/10 rounded-lg"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-trending-up h-5 w-5 text-primary" aria-hidden="true">
                            <path d="M16 7h6v6"></path>
                            <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                        </svg>
                    </div>
                    Panel Volume Trends
                </h4>
            </div>
            <div style="position:relative;display:inline-block;">
                <button id="export_panel_trend" class="btn-export inline-flex items-center justify-center whitespace-nowrap text-sm
                    border bg-background text-foreground hover:bg-accent hover:text-accent-foreground
                    rounded-md gap-1.5 h-8 px-3 transition"
                    data-section="export_volume_trend"
                    type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-download h-4 w-4 mr-1">
                        <path d="M12 15V3" />
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <path d="m7 10 5 5 5-5" />
                    </svg>
                    Export
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-chevron-down h-3 w-3 ml-1">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
                <div id="menu_export_panel_trend" class="menu-export" style="display:none; position:absolute; right:0; top:100%;
                    margin-top:4px; z-index:50; min-width:160px;
                    background:white; border:1px solid #e5e7eb; border-radius:6px;
                    box-shadow:0 4px 12px rgba(0,0,0,.1); padding:4px;">
                    <div class="export_item cursor-pointer flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100"
                        export_func="export_panel_trend" export_type="pdf">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                            <path d="M10 9H8" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg>
                        Export as PDF
                    </div>
                    <div class="export_item cursor-pointer flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-gray-100"
                        export_func="export_panel_trend" export_type="csv">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                            <path d="M10 9H8" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg>
                        Export as CSV
                    </div>
                </div>
            </div>
        </div>
        <div class="text-sm text-gray-600">Monthly volume by panel (last 12 months)</div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
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
                    plugins: [noDataPlugin, verticalLinePlugin, {
                        id: 'legendTooltip',
                        afterEvent(chart, args) {
                            const e = args.event;
                            if (e.type !== 'mousemove') return;
                            const legendItems = chart.legend?.legendItems ?? [];
                            const legendEl = chart.legend;
                            if (!legendEl) return;
                            let found = false;
                            legendItems.forEach((item, i) => {
                                const box = legendEl.legendHitBoxes?.[i];
                                if (!box) return;
                                const x = e.x,
                                    y = e.y;
                                if (x >= box.left && x <= box.left + box.width &&
                                    y >= box.top && y <= box.top + box.height) {
                                    chart.canvas.title = item.fullName || item.text;
                                    found = true;
                                }
                            });
                            if (!found) chart.canvas.title = '';
                        }
                    }],
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
                                display: false,
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
																<span style="color:${color}; font-size:12px;"> ${dp.dataset.label}: ${dp.raw || 0}</span>
															</div>
															`;
                                    });

                                    tooltipChartLine.innerHTML = innerHtml;
                                    tooltipChartLine.style.display = 'block';

                                    let index = tooltipModel.dataPoints[0].dataIndex;
                                    let offsetX = 0;
                                    if (index > 9) {
                                        offsetX = -130;
                                    } else {
                                        offsetX = 60;
                                    }

                                    let x = tooltipModel.caretX + offsetX;

                                    tooltipChartLine.style.left = x + 'px';
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
                                    text: 'Sample count'
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
        <div id="chartLine_legend" class="flex flex-wrap gap-4 mt-4 px-1 items-center justify-center"></div>
        <div class="grid grid-cols-5 gap-3 mt-6 pt-4 border-t" id="chart_summary_current_list"></div>
    </div>
</div>