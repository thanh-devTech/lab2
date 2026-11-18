<div id="box_historical_12m" data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card gap-3">
    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-0">
        <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
            <div class="p-1.5 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-4 w-4 text-red-600" aria-hidden="true">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                </svg></div>Specimens by Status & Time Bracket (12 Months)
        </h4>
    </div>

    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-3">
        <div class="space-y-4">
            <div>
                <div>
                    <div class="h-[400px] w-full">

                        <div style="width:100%;  justify-content:center; align-items:center; margin-top:0px; height: 400px; width:100%">
                            <div id="tooltipChart12m" class="recharts-tooltip-wrapper"></div>
                            <canvas id="stackedChart" height="120"></canvas>
                        </div>

                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-center gap-x-4 gap-y-2 flex-wrap">
                        <span class="text-xs font-medium text-gray-700">Not Received (Pre-Lab):</span>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'over_72', 'chart')); ?>;"></div><span class="text-xs text-gray-600">&gt;72h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '48_72', 'chart')); ?>;"></div><span class="text-xs text-gray-600">48-72h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'chart')); ?>;"></div><span class="text-xs text-gray-600">24-48h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '12_24', 'chart')); ?>;"></div><span class="text-xs text-gray-600">12-24h</span>
                        </div>
                        <div id="chart_legend_include_12_a" style="display:none" class="items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'under_12', 'chart')); ?>;"></div><span class="text-xs text-gray-600">&lt;12h</span>
                        </div>

                    </div>
                    <div class="flex items-center justify-center gap-x-4 gap-y-2 flex-wrap">
                        <span class="text-xs font-medium text-gray-700">Pending (In-Lab):</span>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'over_72', 'chart')); ?>;"></div><span class="text-xs text-gray-600">&gt;72h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '48_72', 'chart')); ?>;"></div><span class="text-xs text-gray-600">48-72h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '24_48', 'chart')); ?>;"></div><span class="text-xs text-gray-600">24-48h</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '12_24', 'chart')); ?>;"></div><span class="text-xs text-gray-600">12-24h</span>
                        </div>
                        <div id="chart_legend_include_12_b" style="display:none" class="items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'under_12', 'chart')); ?>;"></div><span class="text-xs text-gray-600">&lt;12h</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const bottomBorderPlugin = {
    id: 'bottomBorderPlugin',
    afterDatasetsDraw(chart) {
        const ctx = chart.ctx;

        // Chỉ áp dụng cho Pending >72h và sel_bucket/sel_status
        if (sel_bucket === "all" && sel_status === "all") {

            chart.data.datasets.forEach((dataset, i) => {
                if (dataset.label === 'Pending >72h') {
                    const meta = chart.getDatasetMeta(i);

                    meta.data.forEach((bar, index) => {
                        let total = 0;
                        chart.data.datasets.forEach(ds => {
                            total += ds.data[index] || 0;
                        });

                        if (total > 100) {
                            ctx.save();
                            ctx.beginPath();
                            ctx.strokeStyle = '#ffffff';
                            ctx.lineWidth = 2;

                            const x = bar.x - bar.width / 2;
                            const y = bar.y + bar.height;

                            ctx.moveTo(x, y);
                            ctx.lineTo(x + bar.width, y);
                            ctx.stroke();
                            ctx.restore();
                        }
                    });
                }
            });
        }
    }
};



    var tooltipChart12m = document.getElementById('tooltipChart12m');

    const ctx = document.getElementById('stackedChart').getContext('2d');

    function item_toolTip(dataPoints, status, bucket) {
        if (sel_bucket != 'all' && sel_bucket != bucket) {
            return '';
        }
        let num = -1;
        if (status === 'not_received') {
            if (bucket === 'over_72') num = 0;
            else if (bucket === '48_72') num = 1;
            else if (bucket === '24_48') num = 2;
            else if (bucket === '12_24') num = 3;
            else if (bucket === 'under_12' && is_include_m12) num = 4;
        } else if (status === 'pending') {
            if (bucket === 'over_72') num = is_include_m12 ? 5 : 4;
            else if (bucket === '48_72') num = is_include_m12 ? 6 : 5;
            else if (bucket === '24_48') num = is_include_m12 ? 7 : 6;
            else if (bucket === '12_24') num = is_include_m12 ? 8 : 7;
            else if (bucket === 'under_12' && is_include_m12) num = is_include_m12 ? 9 : 8;
        }
        if (num == -1) return '';
        let dp = dataPoints[num];

        let color = dp.element?.options?.backgroundColor || dp
            .dataset.backgroundColor;

        if (status === 'not_received') {
            total_not_rceived += parseInt(dp.raw || 0);
        } else if (status === 'pending') {
            total_pending += parseInt(dp.raw || 0);
        }
        return `
                    <div class="text-sm flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${color}"></span>
                            <div class="text-xs text-gray-500">${dp.dataset.label.replace(/Pending|Not Received/g, "").trim()}</div>
                        </div>
                        <div class="flex items-center gap-2 text-gray-500">
                            ${parseFloat((dp.raw || 0))}
                        </div>
                    </div>
                `;
    }

    var total_pending = 0;
    var total_not_rceived = 0;

    var chart_12_m = new Chart(ctx, {
        type: 'bar',
        plugins: [noDataPlugin, bottomBorderPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            layout: {
                padding: 33
            },
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
                        let elementTag3 = document.getElementById('stackedChart');
                        let y = elementTag3.getBoundingClientRect().top + window.scrollY;
                        let tooltipModel3 = context.tooltip;

                        if (tooltipModel3.opacity === 0) {
                            tooltipChart12m.classList.remove("active");
                            return;
                        } else {
                            tooltipChart12m.classList.add("active");
                        }
                        let dataPoints3 = tooltipModel3.dataPoints;
                        for (i = 0; i < dataPoints3.length; i++) {
                            console.log(i + "=>" + dataPoints3[i].dataset.label + ":" + dataPoints3[i].raw);
                        }



                        let innerHtml = `<div class="font-medium mb-2">
                                        ${dataPoints3[0].label} - Delay Breakdown
                                        </div>`;
                        if (sel_status == 'all' || sel_status == 'pending') {
                            total_pending = 0;
                            let pending_html = '';
                            pending_html += item_toolTip(dataPoints3, 'pending', 'over_72');
                            pending_html += item_toolTip(dataPoints3, 'pending', '48_72');
                            pending_html += item_toolTip(dataPoints3, 'pending', '24_48');
                            pending_html += item_toolTip(dataPoints3, 'pending', '12_24');
                            pending_html += item_toolTip(dataPoints3, 'pending', 'under_12');
                            innerHtml += `<div class="text-sm flex items-center justify-between">Pending<div>${total_pending}</div></div>` + pending_html;
                        }
                        if (sel_status == 'all' || sel_status == 'not_received') {
                            total_not_rceived = 0;
                            let not_received_html = '';
                            not_received_html += item_toolTip(dataPoints3, 'not_received', 'over_72');
                            not_received_html += item_toolTip(dataPoints3, 'not_received', '48_72');
                            not_received_html += item_toolTip(dataPoints3, 'not_received', '24_48');
                            not_received_html += item_toolTip(dataPoints3, 'not_received', '12_24');
                            not_received_html += item_toolTip(dataPoints3, 'not_received', 'under_12');
                            innerHtml += '<div style="border:solid 0.2px #c3c3c3" class="text-sm flex items-center justify-between"></div>';
                            innerHtml += `<div class="text-sm flex items-center justify-between">Not Received<div>${total_not_rceived}</div></div>` + not_received_html;
                        }


                        tooltipChart12m.innerHTML = innerHtml;
                        tooltipChart12m.style.display = 'block';

                        tooltipChart12m.style.left = (tooltipModel3.caretX + (tooltipModel3.caretX < 400 ? 50 : -180)) + 'px';
                        //tooltipChartPending.style.top = (y + tooltipModel2.caretY) + 'px';
                    }
                }
            },
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Samples',
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
