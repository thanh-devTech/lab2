<div id="box_mdr" data-slot="card"
    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-dna h-5 w-5 text-primary" aria-hidden="true">
                        <path d="m10 16 1.5 1.5"></path>
                        <path d="m14 8-1.5-1.5"></path>
                        <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path>
                        <path d="m16.5 10.5 1 1"></path>
                        <path d="m17 6-2.891-2.891"></path>
                        <path d="M2 15c6.667-6 13.333 0 20-6"></path>
                        <path d="m20 9 .891.891"></path>
                        <path d="M3.109 14.109 4 15"></path>
                        <path d="m6.5 12.5 1 1"></path>
                        <path d="m7 18 2.891 2.891"></path>
                        <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path>
                    </svg>Resistance Marker Distribution</h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Resistance marker count per specimen.
                    <!-- <span id="mdr_date">--</span></p> -->
            </div>

            <select id="month_tabs_range_mdr" class="sel_date_all_panel">
                <?php foreach ($filter_date_select as $item): ?>
                    <option value="<?php echo esc_attr($item['value']); ?>">
                        <?php echo esc_html($item['text']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

        </div>
    </div>
    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
        <div class="flex items-center justify-center">
            <div class="relative w-full max-w-[320px]">
                <div class="recharts-responsive-container"
                    style="width: 100%; height: 260px; min-width: 0px;margin-top:38px;">
                    <canvas id="chart_mdr" width="100%" height="100%"
                        style="box-sizing: border-box; height: 260px; width: 100%;"></canvas>
                </div>
                <div id="tooltipPolyMdr" class="recharts-tooltip-wrapper"></div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2" id="list_attr_mdr"></div>
    </div>
</div>

<script>
    //Chart 5
    var tooltipMdr = document.getElementById('tooltipPolyMdr');
    var ctx_chart_mdr = document.getElementById('chart_mdr');
    var chartMdr = new Chart(ctx_chart_mdr, {
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
                    display: false,
                    position: 'bottom',
                    labels: legend_labels,
                },
                title: {
                    display: false,
                    text: ''
                },
                tooltip: {
                    enabled: true
                },
                tooltip: {
                    mode: "index",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        let elementTag = document.getElementById('chart_mdr');
                        let tooltipModel = context.tooltip;

                        if (tooltipModel.opacity === 0) {
                            tooltipMdr.classList.remove("active");
                            return;
                        } else {
                            tooltipMdr.classList.add("active");
                        }

                        let dataPoints = tooltipModel.dataPoints;
                        var index = dataPoints[0].dataIndex;
                        let total = 0;
                        dataPoints.forEach(dp => total += dp.raw || 0);
                        var pl_rate = 0;
                        if (window.mk_rates[index]) pl_rate = window.mk_rates[index];

                        tooltipMdr.innerHTML =
                            `<div><strong>${dataPoints[0].label}</strong><br/>${pl_rate}% (${total} Infections)</div>`;
                        tooltipMdr.style.display = 'block';

                        tooltipMdr.style.left = tooltipModel.caretX + 'px';
                        tooltipMdr.style.top = tooltipModel.caretY + 'px';
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

                const datasets = chart.data.datasets;
                const dataset = datasets && datasets.length ? datasets[0].data : null;
                if (!dataset || !dataset.length) return; // Không có data → thoát sớm
                const total = dataset.reduce((a, b) => a + b, 0);

                ctx.font = fontCenterText_Pie;
                ctx.fillStyle = "#000";
                ctx.textAlign = "center";
                ctx.textBaseline = "middle";
                ctx.fillText(total, width / 2, height / 2);
                ctx.save();
            }
        }, noDataPlugin]
    });
</script>