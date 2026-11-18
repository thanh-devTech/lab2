<div id="box_not_received_rate" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pt-3 pb-3 border-b-0">
        <div class="flex items-start justify-between">
            <div>
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <div class="p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-4 w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'chart')); ?>;">
                            <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                            <path d="m7.5 4.27 9 5.15"></path>
                            <polyline points="3.29 7 12 12 20.71 7"></polyline>
                            <line x1="12" x2="12" y1="22" y2="12"></line>
                            <path d="m17 13 5 5m-5 0 5-5"></path>
                        </svg></div>Pre-Lab Delay Rates
                </h4>
                <p class="text-sm text-muted-foreground mt-0">Not Received (% by time bracket)</p>
            </div>

            <div class="flex items-center gap-1.5"><span class="text-sm text-gray-600">View:</span>
                <div class="inline-flex items-center p-0.5 bg-gray-100 rounded-full border border-gray-200">
                    <button id="btn_view_line_not_recv" onclick="swith_type_view_chart('not_recv','line');return false;" class="cursor-pointer px-3 py-1 rounded-full text-sm font-medium transition-all text-gray-600 hover:text-gray-900">Line</button>
                    <button id="btn_view_stack_not_recv" onclick="swith_type_view_chart('not_recv','stack');return false;" class="cursor-pointer px-3 py-1 rounded-full text-sm font-medium transition-all bg-white text-gray-900 shadow-sm">Stack</button>
                </div>
            </div>

        </div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0">
        <div class="recharts-responsive-container" style="width: 100%; height: 300px; min-width: 0px;">
            <div style="width:100%;  justify-content:center; align-items:center; margin-top:0px; height: 300px; width:100%">
                <div id="tooltipChartLine" class="recharts-tooltip-wrapper"></div>
                <canvas id="notReceivedChart_12m" style="height:100%; width:100%;"></canvas>
            </div>
        </div>
        <div class="flex justify-center gap-3 flex-wrap text-xs">
            <div class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'over_72', 'chart')); ?>;"></div><span class="text-gray-600">&gt;72h</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '48_72', 'chart')); ?>;"></div><span class="text-gray-600">48-72h</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'chart')); ?>;"></div><span class="text-gray-600">24-48h</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '12_24', 'chart')); ?>;"></div><span class="text-gray-600">12-24h</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'under_12', 'chart')); ?>;"></div><span class="text-gray-600">&lt;12h</span>
            </div>
        </div>
    </div>
</div>
<script>
    var tooltipChartLine = document.getElementById('tooltipChartLine');
    const ctx_notReceivedChart_12m = document.getElementById('notReceivedChart_12m').getContext('2d');
    var chart_notReceived_rate = new Chart(ctx_notReceivedChart_12m, {
        type: 'line',
        plugins: [noDataPlugin, verticalLinePlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            layout: {padding: 15},
            plugins: {
                legend: {
                    display: false,
                    position: 'bottom'
                },
                tooltip: {
                    mode: "index",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        let elementTag = document.getElementById('notReceivedChart_12m');
                        let x = elementTag.getBoundingClientRect().left;
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
                                                    <div class="font-medium mb-2">
                                                    ${dataPoints[0].label} - Not Received Delay Rates
                                                    </div>
                                                `;

                        dataPoints.forEach((dp, index) => {

                            if (sel_bucket != 'all' && dp.dataset.bucket != sel_bucket) {
                                return;
                            }

                            let color = dp.element?.options?.backgroundColor || dp
                                .dataset.backgroundColor;
                            innerHtml += `
                                                <div class="text-sm flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${color}"></span>
                                                        <div class="text-xs text-gray-500">${dp.dataset.label.replace(/Pending|Not Received/g, "").trim()}</div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        ${parseFloat((dp.raw || 0).toFixed(2))}%
                                                    </div>
                                                </div>
                                                    `;
                        });

                        tooltipChartLine.innerHTML = innerHtml;
                        tooltipChartLine.style.display = 'block';

                        tooltipChartLine.style.left = (tooltipModel.caretX + ((x + tooltipModel.caretX) < 400 ? 50 : -220)) + 'px';
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: false,
                        text: 'Month'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Delay Rate (%)'
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 5,
                    hitRadius: 6,
                    z: 10
                }
            }
        }
    });
</script>
