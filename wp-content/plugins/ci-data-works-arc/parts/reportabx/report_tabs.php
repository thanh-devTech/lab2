<?php $enable_genotypic_antibiogram = tr_get_option('ci_pdf_data', 'abx_genotypic_antibiogram'); ?>
<script>
    //Check Load data
    var isLoad_volume_trend_6_month = false;
    var isLoad_table_pathogens = false;
    var isLoad_table_marker = false;
    var enable_genotypic_antibiogram = <?php echo $enable_genotypic_antibiogram ? 'true' : 'false'; ?>;

    //Data Table
    var pathogens_table = null;
    var marker_table = null;

    var old_gid = null;
    var old_cid = null;
    var selected_bac_prevalence = "0";
    var selected_rmk_prevalence = "0";

    var all_chart_data = {
        chart1: null,
        chart2: null,
        chart3: null,
        chart3_d: null,
        chart4_d: null,
        chart4: null,
        chart5: null,
        chart6: null,
        table_pathogens: null,
        table_pathogens_d: null,
        table_marker_d: null,
        table_marker: null,
        genotypic_antibiogram: null,
        critical_findings: null,
        days_daily: null
    };
</script>
<div id="boxes_container">
    <?php
    include_once('report_number_tests.php');
    include_once('report_6_month_volume_trend.php');
    include_once('report_bacteria.php');
    include_once('report_marker.php');
    ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" style="margin-top: 28px;">
        <?php
        include_once('report_polymicrobial_infections.php');
        include_once('report_mdr_infections.php');
        ?>
    </div>
    <?php
    if ($enable_genotypic_antibiogram) {
        include_once('report_genotypic_antibiogram.php');
    }
    include_once('report_critical_findings.php');
    ?>
</div>
<script>
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

    function get_count_compare(num_cp) {
        return '<span class="text-xs opacity-70">(' + num_cp + ')</span>';
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

    function showLoadingAllBoxInTab() {
        showLoading("box_test_results");
        if (!isLoad_volume_trend_6_month) {
            showLoading("box_6_month_volume");
            showLoading("box_volume_trend_6_months");
        }
        if (!isLoad_table_pathogens) showLoading("box_pathogens");
        if (!isLoad_table_marker) showLoading("box_marker");
        showLoading("box_polymicrobial_infections");
        showLoading("box_mdr");
        if (enable_genotypic_antibiogram) {
            showLoading("box_genotypic_antibiogram");
        }
        showLoading("box_critical_findings");
    }

    showLoadingAllBoxInTab();



    function convertGoogleToChartJS(chartObj, indexData = 1) {
        if (!chartObj || !chartObj.data) return {
            labels: [],
            datasets: []
        };

        const type = chartObj.charttype || chartObj.config?.charttype || '';

        // === Dạng Doughnut / Pie ===
        if (type === 'doughnut' || type === 'pie') {
            const rows = chartObj.data.slice(1); // Bỏ hàng tiêu đề ["",""]
            return {
                labels: rows.map(r => r[0]),
                datasets: [{
                    label: chartObj.config?.title || '',
                    data: rows.map(r => r[indexData]),
                    backgroundColor: chartObj.config?.colors || [
                        '#2980b9', '#e74c3c', '#f1c40f', '#2ecc71', '#9b59b6',
                        '#16a085', '#d35400', '#7f8c8d', '#34495e', '#c0392b', '#9b59b6', , '#9b59b6',
                    ],
                    borderWidth: 1
                }]
            };
        }

        // === Dạng Column / Bar / Line ===
        if (type === 'column' || type === 'bar' || type === 'line') {
            const data = chartObj.data;
            const headers = data[0].slice(1); // bỏ cột đầu (label)
            const rows = data.slice(1);

            return {
                labels: rows.map(r => r[0]),
                datasets: headers.map((header, i) => ({
                    label: header,
                    data: rows.map(r => r[i + 1]),
                    backgroundColor: chartObj.config?.colors ?
                        chartObj.config.colors[i % chartObj.config.colors.length] : undefined,
                    borderWidth: 1
                }))
            };
        }

        return {
            labels: [],
            datasets: []
        };
    }

    function load_test_result(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'test_result',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            res.chart1 = Object.assign(res.chart1, convertGoogleToChartJS(res.chart1));
            all_chart_data.chart1 = res.chart1;

            chart_test_results_tabs.data = {
                labels: res.chart1.labels,
                datasets: res.chart1.datasets
            };
            styleDoughnutChart(chart_test_results_tabs);
            chart_test_results_tabs.update();
            hideLoading("box_test_results");
            actDone?.();
        });
    }

    function load_volume_trend_6_month(actDone = null) {
        if (isLoad_volume_trend_6_month) {
            hideLoading("box_volume_trend_6_months");
            actDone?.();
            return;
        }

        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'volume_trend_6_month',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);

            $("#table_6_month_volume").empty();

            var sum_samples = 0;
            var sum_p = 0;
            var sum_n = 0;
            var count_months = 0;

            $.each(res.chart2.monthdata, function(index, row) {

                // Nếu Total Sample = 0 nhưng tháng vẫn hiển thị, vẫn tính vào mẫu số
                count_months++;

                var empRow = `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-3"><strong>${row[0]}</strong></td>
                            <td class="text-center py-2 px-3">${row[5]}</td>
                            <td class="text-center py-2 px-3" style="color: #70d4d6; font-weight: bold;">${row[2]}</td>
                            <td class="text-center py-2 px-3" style="color: #ff5f43; font-weight: bold;">${row[1]}</td>
                            <td class="text-center py-2 px-3">${row[3]}%</td>
                        </tr>
                    `;

                $("#table_6_month_volume").append(empRow);

                sum_samples += row[5];
                sum_p += row[1];
                sum_n += row[2];
            });

            hideLoading("box_6_month_volume");

            var avg_samples = count_months > 0 ? Math.round(sum_samples / count_months) : 0;
            var avg_p = count_months > 0 ? Math.round(sum_p / count_months) : 0;
            var avg_n = count_months > 0 ? Math.round(sum_n / count_months) : 0;

            // Tính average positivity rate
            var avg_rate = avg_samples > 0 ?
                Math.round((avg_p / avg_samples) * 100 * 10) / 10 :
                0;

            var empRowTotal = `
                    <tr class="font-bold" style="background-color: rgb(229, 231, 235);">
                        <td class="py-2 px-3">6-Month Average</td>
                        <td class="text-center py-2 px-3">${avg_samples}</td>
                        <td class="text-center py-2 px-3" style="color: #70d4d6; font-weight: bold;">${avg_n}</td>
                        <td class="text-center py-2 px-3" style="color: #ff5f43; font-weight: bold;">${avg_p}</td>
                        <td class="text-center py-2 px-3">${avg_rate}%</td>
                    </tr>
                `;

            $("#table_6_month_volume").append(empRowTotal);

            res.chart2 = Object.assign(res.chart2, convertGoogleToChartJS(res.chart2));
            all_chart_data.chart2 = res.chart2;

            chart_volume_tabs.data = {
                labels: res.chart2.labels,
                datasets: res.chart2.datasets
            };
            applyRoundedTop(chart_volume_tabs);
            chart_volume_tabs.update();
            hideLoading("box_volume_trend_6_months");
            actDone?.();
        });
    }

    function check_hight_value(value_count) {
        if (value_count > 50) return 'bg-red-50';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [match];
        });
    }

    function formatGaPercent(value) {
        var rounded = Math.round((parseFloat(value) || 0) * 10) / 10;
        return Number.isInteger(rounded) ? rounded.toString() : rounded.toFixed(1);
    }

    function getGaCellClass(displayValue) {
        if (displayValue < 10) return "bg-green-100 text-green-800";
        if (displayValue < 20) return "bg-green-50 text-green-700";
        if (displayValue < 40) return "bg-orange-50 text-orange-800";
        return "bg-red-100 text-red-800";
    }

    function render_genotypic_antibiogram_table() {
        if (!all_chart_data.genotypic_antibiogram) return;

        var rows = Array.isArray(all_chart_data.genotypic_antibiogram.rows) ? all_chart_data.genotypic_antibiogram.rows : [];
        var columnKeys = Array.isArray(all_chart_data.genotypic_antibiogram.columns) ? all_chart_data.genotypic_antibiogram.columns : [];
        var $tbody = $("#table_genotypic_antibiogram");
        var $theader = $("#table_genotypic_antibiogram_main thead tr");
        $theader.html(`
            <th class="py-3 px-3 text-left font-semibold text-gray-900 sticky left-0 z-10 bg-gray-50">Pathogen</th>
            <th class="py-3 px-3 text-center font-semibold text-gray-900 bg-gray-50">Samples</th>
            ${columnKeys.map(columnKey => `
                <th class="py-3 px-3 text-center font-semibold text-gray-900 bg-gray-50">${escapeHtml(columnKey)}</th>
            `).join("")}
        `);

        $tbody.empty();
        if (rows.length === 0) {
            $tbody.append(`
                <tr class="border-b">
                    <td colspan="${columnKeys.length + 2}" class="py-6 px-3 text-center text-sm text-gray-500">
                        No molecular resistance summary data found for the selected filters.
                    </td>
                </tr>
            `);
            hideLoading("box_genotypic_antibiogram");
            return;
        }

        $.each(rows, function(index, row) {
            var safeFullName = escapeHtml(row.full_name || row.name || "Unknown");
            var sampleCount = parseInt(row.samples, 10) || 0;
            var cellsHtml = columnKeys.map(function(columnKey) {
                var cell = (row.values && row.values[columnKey]) ? row.values[columnKey] : null;
                if (!cell || !cell.applicable) {
                    return `
                        <td data-slot="table-cell" data-sortval="-1"
                            class="py-2 px-3 text-center font-medium bg-white text-gray-400">-</td>
                    `;
                }

                var displayValue = parseFloat(cell.resistant) || 0;
                var formattedValue = formatGaPercent(displayValue);
                var cellClass = getGaCellClass(displayValue);
                if (cell.link) {
                    formattedValue = `<a href="${cell.link}" target="_blank" class="underline">${formattedValue}</a>`;
                }

                return `
                    <td data-slot="table-cell" data-sortval="${displayValue}"
                        class="py-2 px-3 text-center font-medium ${cellClass}">${formattedValue}%</td>
                `;
            }).join("");

            $tbody.append(`
                <tr data-slot="table-row"
                    class="bg-white">
                    <td class="py-2 px-3 font-medium text-gray-900 sticky left-0 z-10 bg-white" title="${safeFullName}">
                        <div class="ga-pathogen-name relative flex items-center gap-1 max-w-[145px]">
                            <span class="block min-w-0 whitespace-nowrap overflow-hidden text-ellipsis cursor-help" data-state="closed" data-slot="tooltip-trigger">${safeFullName}</span>
                            <div class="tooltip_name" style="display:none">${safeFullName}</div>
                        </div>
                    </td>
                    <td data-sortval="${sampleCount}" class="py-2 px-3 text-center font-semibold text-gray-900 bg-gray-50">${sampleCount}</td>
                    ${cellsHtml}
                </tr>
            `);
        });

        $('#table_genotypic_antibiogram_main').sort_table();
        initTooltips(".ga-pathogen-name", true);
    }

    async function load_pathogens(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'pathogens',
            prevalence: selected_bac_prevalence,
            is_load_table: isLoad_table_pathogens,
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            all_chart_data.chart3 = res.pathogens_chart;

            if (res.pathogens_table != null) {
                pathogens_table = Object.fromEntries(res.pathogens_table.rows.map(item => [item.name, item.color]));;
                all_chart_data.table_pathogens = res.pathogens_table;
                if (Array.isArray(res.pathogens_table.rows) && res.pathogens_table.rows.length > 0) {
                    $("#box_pathogens").find(".item_nodata_grid").removeClass("grid").hide();
                    $("#box_pathogens").find(".item_data_grid").addClass("grid").show();
                } else {
                    $("#box_pathogens").find(".item_nodata_grid").addClass("grid").show();
                    $("#box_pathogens").find(".item_data_grid").removeClass("grid").hide();
                }
            }

            update_chart_pathogens()

            if (isLoad_table_pathogens) {
                hideLoading("box_pathogens");
                actDone?.();
                return;
            }
            render_pathogen_table();
            isLoad_table_pathogens = true;

            hideLoading("box_pathogens");
            actDone?.();
        });
    }

    function update_chart_pathogens() {
        const valueIndex = selected_bac_prevalence === "0" ? 1 : selected_bac_prevalence == "all" ? 3 : 4;
        all_chart_data.chart3 = Object.assign(all_chart_data.chart3, convertGoogleToChartJS(all_chart_data.chart3, valueIndex));

        chart_pathogens_tabs.data = {
            labels: all_chart_data.chart3.labels,
            datasets: all_chart_data.chart3.datasets
        };

        styleDoughnutChart(chart_pathogens_tabs);
        chart_pathogens_tabs.update();

    }

    function render_pathogen_table() {
        if (!all_chart_data.table_pathogens) return;
        $("#pathogens_table").empty();

        const months = all_chart_data.table_pathogens.columns;
        let headerHTML = `
                <span class="col-span-2 cursor-pointer flex items-center sortable pathogen-sortable" data-sort="name">
                    Pathogen
                    <span class="sort-icon-pathogen sort-icon inline-flex items-center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                    </span>
                </span>
                ${Object.values(months).map(m => `
                    <span class="flex cursor-pointer items-center justify-center sortable pathogen-sortable" data-sort="${m}">
                        ${m} %
                        <span class="sort-icon-pathogen sort-icon inline-flex items-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                        </span>
                    </span>
                `).join('')}

                <span class="flex cursor-pointer items-center justify-center sortable pathogen-sortable text-red-500 font-medium" data-sort="avg" style="color: rgb(216, 61, 0);">
                    Avg %
                    <span class="sort-icon-pathogen sort-icon inline-flex items-center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                    </span>
                </span>
            `;

        $("#pathogens_table_header").html(headerHTML);
        $.each(all_chart_data.table_pathogens.rows, function(index, row) {

            let totalVal = 0;
            let totalDenominator = 0;
            const monthKeys = Object.keys(months);

            monthKeys.forEach(m => {
                const cell = row[m] || {};
                const denominatorValue = selected_bac_prevalence === "0" ? cell.total : selected_bac_prevalence === "all" ? cell.total_sample : cell.total_positive;
                totalDenominator += parseFloat(denominatorValue) || 0;;
                totalVal += parseFloat(cell.count) || 0;
            });

            const avg = totalDenominator > 0 ? parseFloat(((totalVal / totalDenominator) * 100).toFixed(1)) : 0;

            const monthCells = monthKeys.map(m => {
                const cell = row[m] || {};
                const value = selected_bac_prevalence === "0" ? cell.value : selected_bac_prevalence == "all" ? cell.value_all : cell.value_pos;
                return `
                        <div class="text-center rounded px-1 ${value > avg ? 'bg-red-50' : ''}">
                            ${value || '0'}
                        </div>
                    `;
            }).join("");

            const rowEmp = `
                    <div class="grid grid-cols-${monthKeys.length + 3} gap-1 items-center py-2 px-1 border-b border-gray-200 hover:bg-gray-50 transition-colors last:border-b-0">

                        <!-- Cột trái (tên marker) -->
                        <div class="col-name-td col-span-2 flex items-center gap-2 text-left font-medium text-gray-700">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${row.color}"></div>
                            <span class="truncate cursor-help"
                                data-state="closed" data-slot="tooltip-trigger">${row.name}</span>
                            <div class="tooltip_name" style="display:none">${row.name}</div>
                        </div>

                        <!-- Các cột tháng -->
                        ${monthCells}

                        <div class="text-center font-semibold text-red-500" style="color: rgb(216, 61, 0);">${avg}</div>
                    </div>
                `;

            $('#pathogens_table').append(rowEmp);
        });
        initTooltips(".col-name-td");
    }

    async function load_resistance_markers(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'resistance_markers',
            prevalence: selected_rmk_prevalence,
            is_load_table: isLoad_table_marker,
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            all_chart_data.chart4 = res.marker_chart;


            if (res.marker_table != null) {
                marker_table = Object.fromEntries(res.marker_table.rows.map(item => [item.name, item.color]));
                all_chart_data.table_marker = res.marker_table;
                if (Array.isArray(res.marker_table.rows) && res.marker_table.rows.length > 0) {
                    $("#box_marker").find(".item_nodata_grid").removeClass("grid").hide();
                    $("#box_marker").find(".item_data_grid").addClass("grid").show();
                } else {
                    $("#box_marker").find(".item_nodata_grid").addClass("grid").show();
                    $("#box_marker").find(".item_data_grid").removeClass("grid").hide();
                }
            }
            update_chart_marker()

            if (isLoad_table_marker) {
                hideLoading("box_marker");
                actDone?.();
                return;
            }

            render_marker_table()


            hideLoading("box_marker");
            isLoad_table_marker = true;
            actDone?.();
        });
    }

    function update_chart_marker() {
        const valueIndex = selected_rmk_prevalence === "0" ? 1 : selected_rmk_prevalence == "all" ? 3 : 4;
        all_chart_data.chart4 = Object.assign(all_chart_data.chart4, convertGoogleToChartJS(all_chart_data.chart4, valueIndex));

        chart_marker.data = {
            labels: all_chart_data.chart4.labels,
            datasets: all_chart_data.chart4.datasets
        };

        styleDoughnutChart(chart_marker);
        chart_marker.update();
    }

    function render_marker_table() {
        if (!all_chart_data.table_marker) return;
        $("#marker_table").empty();
        const months = all_chart_data.table_marker.columns;
        const headerHTML = `
                <span class="col-span-2 cursor-pointer sortable flex items-center marker-sortable" data-sort="name">
                    Resistance Marker
                    <span class="sort-icon-marker sort-icon inline-flex items-center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                    </span>
                </span>
                ${Object.values(months).map(m => `
                    <span class="flex cursor-pointer sortable items-center justify-center marker-sortable" data-sort="${m}">
                        ${m} %
                        <span class="sort-icon-marker sort-icon inline-flex items-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                        </span>
                    </span>
                `).join('')}

                <span class="flex cursor-pointer items-center justify-center sortable marker-sortable text-red-500 font-medium" data-sort="avg" style="color: rgb(216, 61, 0);">
                    Avg %
                    <span class="sort-icon-marker sort-icon inline-flex items-center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up-down active"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-up"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide w-3 h-3 lucide-arrow-down"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>
                    </span>
                </span>
            `;
        $("#marker_table_header").html(headerHTML);
        $.each(all_chart_data.table_marker.rows, function(index, row) {

            const monthKeys = Object.keys(months);
            let totalVal = 0;
            let totalDenominator = 0;
            monthKeys.forEach(m => {
                const cell = row[m] || {};
                const denominatorValue = selected_rmk_prevalence === "0" ? cell.total : selected_rmk_prevalence === "all" ? cell.total_sample : cell.total_positive;
                totalDenominator += parseFloat(denominatorValue) || 0;;
                totalVal += parseFloat(cell.count) || 0;
            });

            const avg = totalDenominator > 0 ? parseFloat(((totalVal / totalDenominator) * 100).toFixed(1)) : 0;

            const monthCells = monthKeys.map(m => {
                const cell = row[m] || {};
                const value = selected_rmk_prevalence === "0" ? cell.value : selected_rmk_prevalence === "all" ? cell.value_all : cell.value_pos;
                return `
                    <div class="text-center rounded px-1 ${value > avg ? 'bg-red-50': ''}">
                        ${value || '0'}
                    </div>
                `;
            }).join("");

            var rowEmp = `
                    <div class="grid grid-cols-${monthKeys.length + 3} gap-1 items-center py-2 px-1 border-b border-gray-200 hover:bg-gray-50 transition-colors last:border-b-0">
                        <div class="col-name col-span-2 flex items-center gap-2 text-left font-medium text-gray-700">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${row.color}"></div>
                            <span class="truncate cursor-help"
                                data-state="closed" data-slot="tooltip-trigger">${row.name}</span>
                            <div class="tooltip_name" style="display:none">${row.name}</div>
                        </div>
                        ${monthCells}
                        <div class="text-center font-semibold text-red-500" style="color: rgb(216, 61, 0);">${avg}</div>
                    </div>
                `;

            $('#marker_table').append(rowEmp);
        });
        initTooltips()
    }

    function load_polymicrobial_infections(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'polymicrobial_infections',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {


            var res = JSON.parse(response);
            res.polymicrobial_chart = Object.assign(res.polymicrobial_chart, convertGoogleToChartJS(res
                .polymicrobial_chart));

            all_chart_data.chart5 = res.polymicrobial_chart;

            $("#polymicrobial_infections_date").html(res.polymicrobial_chart.date_sel);
            window.data_rate_poly_infe = res.polymicrobial_chart.data_rate;
            chart_polymicrobial_infections.data = {
                labels: res.polymicrobial_chart.labels,
                datasets: res.polymicrobial_chart.datasets
            };
            styleDoughnutChart(chart_polymicrobial_infections);
            chart_polymicrobial_infections.update();
            hideLoading("box_polymicrobial_infections");
            actDone?.();

            $("#list_attr_pi").empty();
            var bkColor = res.polymicrobial_chart.datasets[0].backgroundColor;
            $.each(res.polymicrobial_chart.data, function(i, k) {
                if (i == 0) return true;
                var rowEmp = `
                    <div class="flex items-center gap-1.5 text-sm">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color:${bkColor[i - 1]};"></div>
                        <span class="text-gray-700 text-xs">${k[0]}<span class="font-medium">(${k[1]})</span></span>
                    </div>
                `;
                $("#list_attr_pi").append(rowEmp);
            });
        });
    }

    function load_mdr(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'mdr',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            res.mdr_chart = Object.assign(res.mdr_chart, convertGoogleToChartJS(res.mdr_chart));
            window.mk_rates = res.mdr_chart.total_rate;

            all_chart_data.chart6 = res.mdr_chart;

            $("#mdr_date").html(res.mdr_chart.date_sel);
            chartMdr.data = {
                labels: res.mdr_chart.labels,
                datasets: res.mdr_chart.datasets
            };
            styleDoughnutChart(chartMdr);
            chartMdr.update();
            hideLoading("box_mdr");
            actDone?.();

            $("#list_attr_mdr").empty();
            var bkColor = res.mdr_chart.datasets[0].backgroundColor;
            $.each(res.mdr_chart.data, function(i, k) {
                if (i == 0) return true;
                var rowEmp = `
                    <div class="flex items-center gap-1.5 text-sm">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color:${bkColor[i - 1]};"></div>
                        <span class="text-gray-700 text-xs">${k[0]}<span class="font-medium">(${k[1]})</span></span>
                    </div>
                `;
                $("#list_attr_mdr").append(rowEmp);
            });
        });
    }

    function load_genotypic_antibiogram(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'genotypic_antibiogram',
            recachequery: 1,
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            all_chart_data.genotypic_antibiogram = res.genotypic_antibiogram || {
                columns: [],
                rows: []
            };
            render_genotypic_antibiogram_table();
            hideLoading("box_genotypic_antibiogram");
            actDone?.();
        });
    }

    function load_critical_findings(actDone = null) {
        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'report_tabs',
            sub_ajax: 'critical_findings',
            month_range: month_range,
            tab: '<?php echo $tab; ?>',
            date_from: date_from,
            date_to: date_to,
            cid: cid,
            gid: gid
        }, function(response) {
            var res = JSON.parse(response);
            $("#table_critical_findings").empty();

            var cfs = res.critical_findings_summary;

            all_chart_data.critical_findings = res.critical_findings_summary;

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
                                    <div class="inline-flex items-center gap-1.5">${itemVRE}
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
                                    <div class="inline-flex items-center justify-center gap-1 min-w-[60px] px-3 py-1 rounded-md bg-[#D83D00] text-white border border-transparent">
                                        <span class="font-semibold">${row.Total}</span>${get_count_compare(data_cp.Total)}</span>
                                    </div>
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

    function initTooltips(selector = ".col-name", placeFirstBelow = false) {
        const $tooltipItems = $(selector);
        $tooltipItems.off('mouseenter mouseleave').on('mouseenter', function() {
            const empTip = $(this).find(".tooltip_name");
            const isFirstItem = placeFirstBelow && $tooltipItems.filter(":visible").first()[0] === this;
            const triggerRect = this.getBoundingClientRect();

            empTip.css({
                "position": "fixed",
                "top": "0",
                "left": "0",
                "margin-top": "0",
                "transition": "none",
                "pointer-events": "none",
                "z-index": "9999"
            });
            empTip.show();

            const tooltipHeight = empTip.outerHeight() || 0;
            const tooltipWidth = empTip.outerWidth() || 0;
            const shouldPlaceBelow = isFirstItem || triggerRect.top < tooltipHeight + 12;
            const top = shouldPlaceBelow ? triggerRect.bottom + 6 : triggerRect.top - tooltipHeight - 6;
            const left = Math.min(triggerRect.left, window.innerWidth - tooltipWidth - 12);

            empTip.css({
                "top": Math.max(8, top) + "px",
                "left": Math.max(8, left) + "px"
            });
        }).on('mouseleave', function() {
            const empTip = $(this).find(".tooltip_name");
            empTip.hide();
        });
    }

    function update_sub_title(selector, prevalence) {
        let sub_title = "Relative share of total targets";
        if (prevalence === 'all') {
            sub_title = "Target % of tested specimens";
        } else if (prevalence === 'pos') {
            sub_title = "Target % of positive specimens";
        }
        $(selector).html(sub_title);

    }

    async function loadDataTabs() {
        if (old_cid === cid && old_gid === gid) {
            isLoad_volume_trend_6_month = true;
            isLoad_table_pathogens = true;
            isLoad_table_marker = true;
        } else {
            isLoad_volume_trend_6_month = false;
            isLoad_table_pathogens = false;
            isLoad_table_marker = false;
        }
        old_cid = cid;
        old_gid = gid;
        disableExportAndFilterDate();
        showLoadingAllBoxInTab();

        load_test_result();

        await delay(200);
        load_volume_trend_6_month();

        await load_pathogens();

        await load_resistance_markers();

        await delay(200);
        load_polymicrobial_infections();

        await delay(200);
        load_mdr();

        if (enable_genotypic_antibiogram) {
            await delay(200);
            load_genotypic_antibiogram();
        }

        await delay(200);
        load_critical_findings();

        $("#filter_all_loading").hide();
        disableExportAndFilterDate(false);
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function disableExportAndFilterDate(disable = true) {
        if (disable) {
            $(".btn-export").addClass("opacity-50 pointer-events-none");
            $("#emp_month_range_test_result, #emp_month_range_pathogens, #emp_month_range_mdr, #emp_month_range_pol_infections, #emp_month_tabs_range_mdr, #emp_month_tabs_range_citical, #month_tabs_range_genotypic").addClass("opacity-50 pointer-events-none");
        } else {
            $(".btn-export").removeClass("opacity-50 pointer-events-none");
            $("#emp_month_range_test_result, #emp_month_range_pathogens, #emp_month_range_mdr, #emp_month_range_pol_infections, #emp_month_tabs_range_mdr, #emp_month_tabs_range_citical, #month_tabs_range_genotypic").removeClass("opacity-50 pointer-events-none");
        }
    }

    async function download_export_file(empE) {
        var export_func = $(empE).attr("export_func");
        var export_type = $(empE).attr("export_type");

        var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');

        try {

            const formData = new URLSearchParams({
                trajax: 'report_tabs',
                export_type: export_type,
                export_func: export_func,
                month_range: month_range,
                date_from: date_from,
                date_to: date_to,
                cid: cid,
                gid: gid,
                tab: '<?php echo $tab; ?>',
                prevalence_pathogen: selected_bac_prevalence,
                prevalence_rmk: selected_rmk_prevalence,

                image_test_results: chart_test_results_tabs.toBase64Image(),
                image_pathogens: chart_pathogens_tabs.toBase64Image(),
                image_marker: chart_marker.toBase64Image(),

                chart1_data: JSON.stringify(all_chart_data.chart1),
                chart2_data: JSON.stringify(all_chart_data.chart2),
                chart3_data: JSON.stringify(all_chart_data.chart3),
                chart4_data: JSON.stringify(all_chart_data.chart4),
                chart5_data: JSON.stringify(all_chart_data.chart5),
                chart6_data: JSON.stringify(all_chart_data.chart6),

                table_pathogens: JSON.stringify(all_chart_data.table_pathogens),
                table_marker: JSON.stringify(all_chart_data.table_marker),
                genotypic_antibiogram: JSON.stringify(all_chart_data.genotypic_antibiogram),
                critical_findings: JSON.stringify(all_chart_data.critical_findings),
                days_daily_data: JSON.stringify(all_chart_data.days_daily)
            });

            const response = await fetch(location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });

            const disposition = response.headers.get("Content-Disposition");
            let filename = "download." + export_type;

            if (disposition && disposition.includes("filename=")) {
                const match = disposition.match(/filename="?([^"]+)"?/);
                if (match && match[1]) filename = match[1];
            }

            const blob = await response.blob();
            const a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            a.click();

            URL.revokeObjectURL(a.href);
            if (loadingToast) {
                loadingToast.classList.remove("show");
                setTimeout(() => loadingToast.remove(), 300);
            }

            showToast('success', 'Download ready!', 'Your file "' + filename + '" has been downloaded.', 4000, 'top_right');

        } catch (err) {
            if (loadingToast) {
                loadingToast.classList.remove("show");
                setTimeout(() => loadingToast.remove(), 300);
            }
            console.error('Export error:', err);
            showToast('error', 'Error', 'Download failed: ' + err.message, 4000, 'top_right');
        }
    }

    jQuery(function($) {
        $(".export_item").click(function() {
            download_export_file(this);
            $(this).closest(".menu-export").hide();
        });

        $(".sel_date_all_panel").change(function() {
            var val_date = $(this).val();
            month_range = val_date;
            loadDataTabs();
            update_all_select_report();
        });
        $("#bac_prevalence").change(function() {
            selected_bac_prevalence = $(this).val();
            update_sub_title('#box_pathogens .sub-title', selected_bac_prevalence)
            render_pathogen_table();
            update_chart_pathogens()
        });
        $("#rmk_prevalence").change(function() {
            selected_rmk_prevalence = $(this).val();
            update_sub_title('#box_marker .sub-title', selected_rmk_prevalence)
            render_marker_table();
            update_chart_marker();
        });
        let pathogenSort = {
            column: null,
            direction: 'asc'
        };
        let markerSort = {
            column: null,
            direction: 'asc'
        };

        function sortDynamicTable(tableId, sortColumn, direction, isMarker = false) {
            const $table = $(tableId);
            const $rows = $table.find('> div').toArray();
            const sortKey = isMarker ? markerSort : pathogenSort;

            $rows.sort(function(a, b) {
                let aVal, bVal;

                if (sortColumn === 'name') {
                    aVal = $(a).find('.col-name-td, .col-name').text().trim().toLowerCase();
                    bVal = $(b).find('.col-name-td, .col-name').text().trim().toLowerCase();
                } else {
                    const monthIndex = $(`${tableId.replace('_table', '_table_header')} span[data-sort="${sortColumn}"]`).index();
                    if (monthIndex === -1) return 0;
                    const cellIndex = monthIndex;
                    aVal = parseFloat($(a).children().eq(cellIndex).text()) || 0;
                    bVal = parseFloat($(b).children().eq(cellIndex).text()) || 0;
                }

                if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            $table.empty().append($rows);

            const iconClass = isMarker ? '.sort-icon-marker' : '.sort-icon-pathogen';
            const sortableClass = isMarker ? '.marker-sortable' : '.pathogen-sortable';

            $(sortableClass).find(iconClass).css('opacity', '0.4');
            $(sortableClass).find(iconClass + ' svg').removeClass('active');
            $(sortableClass).find(iconClass + ' .lucide-arrow-up-down').addClass('active');

            if (sortColumn) {
                const $activeHeader = $(sortableClass + `[data-sort="${sortColumn}"]`);
                const $icon = $activeHeader.find(iconClass);
                $icon.css('opacity', '1');
                $icon.find('svg').removeClass('active');
                if (direction === 'asc') {
                    $icon.find('.lucide-arrow-up').addClass('active');
                } else {
                    $icon.find('.lucide-arrow-down').addClass('active');
                }
            }
            if (['#marker_table', '#pathogens_table'].includes(tableId)) {
                initTooltips(tableId === '#pathogens_table' ? ".col-name-td" : ".col-name");
            }

            if (isMarker) markerSort = {
                column: sortColumn,
                direction
            };
            else pathogenSort = {
                column: sortColumn,
                direction
            };
        }

        $(document).on('click', '.pathogen-sortable', function() {
            const col = $(this).data('sort');
            let dir = 'asc';
            if (pathogenSort.column === col && pathogenSort.direction === 'asc') dir = 'desc';
            sortDynamicTable('#pathogens_table', col, dir, false);
        });

        $(document).on('click', '.marker-sortable', function() {
            const col = $(this).data('sort');
            let dir = 'asc';
            if (markerSort.column === col && markerSort.direction === 'asc') dir = 'desc';
            sortDynamicTable('#marker_table', col, dir, true);
        });

        function resetDynamicTableSort(tableType) {
            const iconClass = tableType === 'marker' ? '.sort-icon-marker' : '.sort-icon-pathogen';
            $(iconClass).css('opacity', '0.4');
            $(iconClass + ' svg').removeClass('active');
            $(iconClass + ' .lucide-arrow-up-down').addClass('active');
            if (tableType === 'marker') markerSort = {
                column: null,
                direction: 'asc'
            };
            else pathogenSort = {
                column: null,
                direction: 'asc'
            };
        }

        const oldLoadPathogens = load_pathogens;
        load_pathogens = function(actDone) {
            oldLoadPathogens(function() {
                resetDynamicTableSort('pathogen');
                if (actDone) actDone();
            });
        };

        const oldLoadMarkers = load_resistance_markers;
        load_resistance_markers = function(actDone) {
            oldLoadMarkers(function() {
                resetDynamicTableSort('marker');
                if (actDone) actDone();
            });
        };
    });
</script>
