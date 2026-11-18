<div id="box_polymicrobial_infections" data-slot="card"
    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
    <div data-slot="card-header"
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary">
                        <rect x="3" y="4" width="7" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="15" cy="5.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <circle cx="18.5" cy="5.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <rect x="4" y="10" width="8" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="6" cy="17.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <circle cx="9.5" cy="17.5" r="2" fill="currentColor" opacity="0.85"></circle>
                        <rect x="14" y="16" width="7" height="3" rx="1.5" fill="currentColor" opacity="0.9"></rect>
                        <circle cx="17" cy="11" r="2" fill="currentColor" opacity="0.85"></circle>
                    </svg>
                    Polymicrobial Detections</h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm mt-1">Pathogen count per specimen.
                    <!-- <span id="polymicrobial_infections_date">--</span> -->
                </p>
            </div>

            <select id="month_range_pol_infections" name="month_range_pol_infections" class="sel_date_all_panel">
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
                    style="width: 100%; height: 260px; min-width: 0px;margin-top:38px">
                    <div class="recharts-wrapper"
                        style="position: relative; cursor: default; width: 100%; height: 260px;">
                        <canvas id="chart_polymicrobial_infections" width="100%" height="100%"
                            style="box-sizing: border-box; height: 100%; width: 100%;"></canvas>
                        <div id="tooltipPolymicrobial" class="recharts-tooltip-wrapper"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2" id="list_attr_pi"></div>

    </div>
</div>

<script>
    //Chart 4
    var tooltipPolymicrobial = document.getElementById('tooltipPolymicrobial');
    var ctx_chart_pi = document.getElementById('chart_polymicrobial_infections');
    var chart_polymicrobial_infections = new Chart(ctx_chart_pi, {
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
                    labels: legend_labels
                },
                title: {
                    display: false,
                    text: ''
                },
                tooltip: {
                    enabled: true
                },
                tooltip: {
                    mode: "nearest",
                    intersect: false,
                    enabled: false,
                    external: function(context) {
                        let elementTag = document.getElementById('chart_polymicrobial_infections');
                        let y = elementTag.getBoundingClientRect().top + window.scrollY;
                        let tooltipModel = context.tooltip;

                        if (tooltipModel.opacity === 0) {
                            tooltipPolymicrobial.classList.remove("active");
                            return;
                        } else {
                            tooltipPolymicrobial.classList.add("active");
                        }
                        let dataPoints = tooltipModel.dataPoints;
                        var index = dataPoints[0].dataIndex;
                        let total = 0;
                        dataPoints.forEach(dp => total += dp.raw || 0);
                        var pl_rate = 0;
                        if (window.data_rate_poly_infe[index]) pl_rate = window.data_rate_poly_infe[index];

                        tooltipPolymicrobial.innerHTML =
                            `<div><strong>${dataPoints[0].label}</strong><br/>${pl_rate}% (${total} Infections)</div>`;
                        tooltipPolymicrobial.style.display = 'block';

                        tooltipPolymicrobial.style.left = tooltipModel.caretX + 'px';
                        tooltipPolymicrobial.style.top = tooltipModel.caretY + 'px';
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