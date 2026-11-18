<?php
$selDay = $_REQUEST['selDay'] ?? 'today';
?>
<div id="box_current_queue_pane" data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card gap-0">
    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-0">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <div class="p-1.5 bg-[#EAB308]/10 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hourglass h-4 w-4 text-[#EAB308]">
                            <path d="M5 22h14"></path>
                            <path d="M5 2h14"></path>
                            <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
                            <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
                        </svg>
                    </div>Current Queue by Panel
                </h4>
                <div id="current_queue_sample_filter_wrap" style="display:none;">
                    <select id="current_queue_sample_filter"
                        class="h-8 rounded-md border border-slate-300 px-2 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="__all__">All Batch Types</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-3">

                <button id="btn_current_queue_live" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 h-8 transition-colors text-muted-foreground hover:text-primary hover:bg-transparent">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity h-3.5 w-3.5 mr-1.5">
                        <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"></path>
                    </svg>Live Report
                </button>

                <div style="position:relative;display:inline-block;">

                    <button id="current_queue_export" data-slot="dropdown-menu-trigger" class="cursor-pointer btn-export inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent h-8" type="button" id="radix-:rf:" aria-haspopup="menu" aria-expanded="false" data-state="closed">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-3.5 w-3.5 mr-1.5">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" x2="12" y1="15" y2="3"></line>
                        </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>

                    <div id="menu_current_queue_export" class="menu-export" data-radix-popper-content-wrapper=""
                        dir="ltr"
                        style="display: none;position: absolute;left: -33px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                        <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                            data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                            aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                            class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                            tabindex="-1" data-orientation="vertical"
                            style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                            <div export_func="export_report" export_type="pdf" role="menuitem"
                                data-slot="dropdown-menu-item" data-variant="default"
                                class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                    </path>
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                    <path d="M10 9H8"></path>
                                    <path d="M16 13H8"></path>
                                    <path d="M16 17H8"></path>
                                </svg>Export as PDF</div>
                            <div export_func="export_report" export_type="csv" role="menuitem"
                                data-slot="dropdown-menu-item" data-variant="default"
                                class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                    </path>
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

    <div id="current_queue_live" style="display: none;" class="mx-6 mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
        <h4 class="font-semibold text-base mb-4 text-slate-800" style="margin-bottom:10px">Live Current Queue</h4>
        <div class="bg-white rounded-lg border overflow-hidden">
            <table class="w-full text-sm" id="table_current_queue_live">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium border-b">Panel</th>
                        <th class="text-center p-3 font-medium border-b">Inbound</th>
                        <th class="text-center p-3 font-medium border-b">%</th>
                        <th class="text-center p-3 font-medium border-b">Received</th>
                        <th class="text-center p-3 font-medium border-b">%</th>
                        <th class="text-center p-3 font-medium border-b">Processing</th>
                        <th class="text-center p-3 font-medium border-b">%</th>
                        <th class="text-center p-3 font-medium border-b">Completed</th>
                        <th class="text-center p-3 font-medium border-b">%</th>
                    </tr>
                </thead>
                <tbody id="current_queue_live_body"></tbody>
                <tbody>
                    <tr id="current_queue_live_totals" class="bg-gray-50 font-bold"></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-xs text-slate-600 bg-slate-100 p-2 rounded">💡 This live report reflects real-time queue status across all active panels.</div>
    </div>

    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6 pt-3 pb-5">
        <div class="space-y-6">

            <div class="flex flex-col gap-0">
                <div class="flex items-center gap-3"><span class="text-sm font-medium text-slate-700">Current Inbound</span>
                    <div class="flex items-baseline gap-1"><span id="current_inbound_total" class="text-lg font-bold text-slate-900">--</span><span class="text-xs text-muted-foreground">samples</span></div>
                </div>
                <div class="h-10 w-full min-w-0">
                    <div class="recharts-responsive-container" style="width: 100%; height: 64px; min-width: 0px; min-height: 0px;">
                        <div id="current_inbound_chart_tooltip" class="recharts-tooltip-wrapper"></div>
                        <canvas id="current_inbound_chart" style="width:100%;height:100%;display:block;"></canvas>
                    </div>
                </div>
            </div>

            <div id="current_received_box" class="flex flex-col gap-0" style="margin-top: 52px;">
                <div class="flex items-center gap-3"><span class="text-sm font-medium text-slate-700">Received</span>
                    <div class="flex items-baseline gap-1"><span id="current_received_total" class="text-lg font-bold text-slate-900">--</span><span class="text-xs text-muted-foreground">samples</span></div>
                </div>

                <div class="h-20 w-full min-w-0 relative">
                    <div class="recharts-responsive-container" style="width: 100%; height: 80px; min-width: 0px; min-height: 0px;">
                        <div id="current_received_chart_tooltip" class="recharts-tooltip-wrapper"></div>
                        <canvas id="current_received_chart" style="width:100%;height:100%;display:block;"></canvas>
                    </div>
                </div>
            </div>
            <div class="pt-4 border-t border-gray-100 mt-4" style="margin-top: 50px;">
                <div id="current_queue_panel_legend" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
    var current_inbound_chart_tooltip = document.getElementById('current_inbound_chart_tooltip');
    var current_received_chart_tooltip = document.getElementById('current_received_chart_tooltip');
    var selDay = '<?php echo $selDay; ?>';
    const RECEIVED_PENDING_LABEL = 'Not Completed';
    const RECEIVED_PENDING_SUFFIX = ` (${RECEIVED_PENDING_LABEL})`;
    const RECEIVED_COMPLETED_SUFFIX = ' (Completed)';

    showLoading('box_current_queue_pane');

    function applyRoundedBar(chart) {
        const roundedRadiusPx = 8;
        const roundedCapMinLengthPx = 8;
        const ds = chart.data.datasets;
        const count = ds.length;
        const dataCount = chart.data.labels.length;
        const isReceived = chart?.canvas?.id === 'current_received_chart';

        for (let i = 0; i < count; i++) {
            ds[i].borderRadius = Array(dataCount).fill(0);
            ds[i].borderSkipped = false;
            ds[i].minBarLength = 0;
        }

        let totalInProcess = 0;
        let totalCompleted = 0;
        let firstInProcessIndex = -1;
        if (isReceived) {
            ds.forEach((d, idx) => {
                const val = parseFloat(Array.isArray(d.data) ? d.data[0] : d.data) || 0;
                if ((d.label || '').includes(RECEIVED_PENDING_SUFFIX)) {
                    totalInProcess += val;
                    if (val > 0 && firstInProcessIndex === -1) firstInProcessIndex = idx;
                }
                if ((d.label || '').includes(RECEIVED_COMPLETED_SUFFIX)) totalCompleted += val;
            });
        }

        for (let col = 0; col < dataCount; col++) {
            const isInProcess = i => (ds[i].label || '').includes(RECEIVED_PENDING_SUFFIX);
            const isCompleted = i => (ds[i].label || '').includes(RECEIVED_COMPLETED_SUFFIX);

            const leftGroupCheck = (i) => {
                if (!isReceived) return true;
                if (totalInProcess === 0) return false;
                if (totalCompleted === 0) {
                    // only In Process: round on the first In Process block
                    return isInProcess(i) && i === firstInProcessIndex;
                }
                return isInProcess(i);
            };
            const rightGroupCheck = (i) => {
                if (!isReceived) return true;
                if (totalCompleted === 0) {
                    // only In Process: no right rounding
                    return false;
                }
                return isCompleted(i);
            };

            for (let i = 0; i < count; i++) {
                if (ds[i].data[col] > 0 && leftGroupCheck(i)) {
                    const currentRadius = (typeof ds[i].borderRadius[col] === 'object' && ds[i].borderRadius[col] !== null) ?
                        ds[i].borderRadius[col] : {};
                    ds[i].borderRadius[col] = {
                        ...currentRadius,
                        topLeft: roundedRadiusPx,
                        bottomLeft: roundedRadiusPx
                    };
                    ds[i].minBarLength = Math.max(ds[i].minBarLength || 0, roundedCapMinLengthPx);
                    break;
                }
            }
            for (let i = count - 1; i >= 0; i--) {
                if (ds[i].data[col] > 0 && rightGroupCheck(i)) {
                    const currentRadius = (typeof ds[i].borderRadius[col] === 'object' && ds[i].borderRadius[col] !== null) ?
                        ds[i].borderRadius[col] : {};
                    ds[i].borderRadius[col] = {
                        ...currentRadius,
                        topRight: roundedRadiusPx,
                        bottomRight: roundedRadiusPx
                    };
                    ds[i].minBarLength = Math.max(ds[i].minBarLength || 0, roundedCapMinLengthPx);
                    break;
                }
            }
        }
    }
    let current_inbound_chart = null;
    let current_received_chart = null;
    let inboundBaseColors = [];
    let receivedBaseColors = [];
    let receivedSplitTotals = {
        inProcess: 0,
        completed: 0
    };
    const ctx = document.getElementById('current_inbound_chart').getContext('2d');
    const ctx2 = document.getElementById('current_received_chart').getContext('2d');

    var noDataPlugin = {
        id: 'noData',
        beforeDraw(chart, args, options) {
            const {
                ctx,
                data,
                chartArea
            } = chart;
            if (!chartArea) return;

            const hasData = data.datasets.some(ds => ds.data && ds.data.length > 0);

            if (!hasData) {
                const {
                    top,
                    left,
                    width,
                    height
                } = chartArea;

                ctx.save();
                ctx.clearRect(left, top, width, height);

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '16px sans-serif';
                ctx.fillStyle = '#999';
                ctx.fillText('No Data Available', left + width / 2, top + height / 2);
                ctx.restore();
            }
        }
    };

    function lightenColor(hex, amount) {
        let c = hex.replace('#', '');
        if (c.length === 3) {
            c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
        }
        const num = parseInt(c, 16);
        let r = (num >> 16) & 255;
        let g = (num >> 8) & 255;
        let b = num & 255;
        r = Math.min(255, Math.round(r + (255 - r) * amount));
        g = Math.min(255, Math.round(g + (255 - g) * amount));
        b = Math.min(255, Math.round(b + (255 - b) * amount));
        return `rgb(${r}, ${g}, ${b})`;
    }

    function colorWithAlpha(color, alpha) {
        if (!color) return color;
        const c = color.toString().trim();
        if (c.startsWith('rgba(')) {
            return c.replace(/rgba\(([^,]+),([^,]+),([^,]+),([^)]+)\)/, `rgba($1,$2,$3,${alpha})`);
        }
        if (c.startsWith('rgb(')) {
            return c.replace(/rgb\(([^,]+),([^,]+),([^)]+)\)/, `rgba($1,$2,$3,${alpha})`);
        }
        if (c.startsWith('#')) {
            let hex = c.slice(1);
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            if (hex.length === 6) {
                const r = parseInt(hex.slice(0, 2), 16);
                const g = parseInt(hex.slice(2, 4), 16);
                const b = parseInt(hex.slice(4, 6), 16);
                return `rgba(${r},${g},${b},${alpha})`;
            }
        }
        return c;
    }

    function getReadableTextColor(color) {
        const value = (color || '').toString().trim();
        let channels = null;

        if (value.startsWith('#')) {
            let hex = value.slice(1);
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            if (hex.length === 6) {
                channels = [
                    parseInt(hex.slice(0, 2), 16),
                    parseInt(hex.slice(2, 4), 16),
                    parseInt(hex.slice(4, 6), 16)
                ];
            }
        } else {
            const match = value.match(/rgba?\(([^,]+),([^,]+),([^,\)]+)/i);
            if (match) {
                channels = [
                    parseFloat(match[1]),
                    parseFloat(match[2]),
                    parseFloat(match[3])
                ];
            }
        }

        if (!channels) {
            return '#111827';
        }

        const [r, g, b] = channels;
        const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return brightness > 170 ? '#111827' : '#ffffff';
    }

    function renderCountBadge(value, color, variant = 'solid') {
        const safeValue = value ?? 0;
        const badgeStyle = variant === 'solid' ?
            `background:${color};color:${getReadableTextColor(color)};` :
            `background:${colorWithAlpha(color, 0.14)};color:${color};border:1px solid ${colorWithAlpha(color, 0.24)};`;

        return `<span style="padding:2px 6px;margin:1px;border-radius:999px;font-size:10px;line-height:1;display:inline-flex;align-items:center;justify-content:center;font-weight:600;${badgeStyle}">${safeValue}</span>`;
    }

    function stripReceivedStatus(label) {
        return (label || '')
            .replace(RECEIVED_PENDING_SUFFIX, '')
            .replace(RECEIVED_COMPLETED_SUFFIX, '');
    }

    const receivedSplitPlugin = {
        id: 'receivedSplitPlugin',
        afterDatasetsDraw(chart) {
            const total = receivedSplitTotals.inProcess + receivedSplitTotals.completed;
            if (!total) return;

            const {
                ctx,
                chartArea
            } = chart;
            const xScale = chart.scales.x;
            const splitX = xScale.getPixelForValue(receivedSplitTotals.inProcess);

            ctx.save();
            ctx.strokeStyle = '#9CA3AF';
            ctx.lineWidth = 3;
            ctx.setLineDash([6, 4]);
            const lineTop = Math.max(chartArea.top, 2);
            const lineBottom = Math.min(chartArea.bottom, chart.height - 2);
            ctx.beginPath();
            ctx.moveTo(splitX, lineTop);
            ctx.lineTo(splitX, lineBottom);
            ctx.stroke();
            ctx.setLineDash([]);

            ctx.fillStyle = '#D83D00';
            ctx.strokeStyle = '#FFFFFF';
            ctx.lineWidth = 3;
            ctx.shadowColor = 'rgba(216, 61, 0, 0.35)';
            ctx.shadowBlur = 6;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 2;
            ctx.beginPath();
            ctx.arc(splitX, lineTop + 5, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;

            ctx.fillStyle = '#8f97a8';
            ctx.font = '10px sans-serif';
            ctx.textBaseline = 'top';
            const labelY = Math.min(chartArea.bottom + 6, chart.height - 10);
            const leftText = RECEIVED_PENDING_LABEL;
            const rightText = 'Completed';
            ctx.textAlign = 'right';
            ctx.fillText(leftText, splitX - 6, labelY);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#292b30';
            ctx.fillText(rightText, splitX + 6, labelY);
            ctx.restore();
        }
    };

    function positionChartTooltip(tooltipEl, canvasEl, tooltipModel, offsetY) {
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
            tooltipEl.style.left = '0px';
            tooltipEl.style.right = '0px';
            tooltipEl.style.width = '100%';
            tooltipEl.style.maxWidth = '';
            tooltipEl.style.top = (tooltipModel.caretY + offsetY) + 'px';
            return;
        }

        const container = canvasEl.closest('.recharts-responsive-container') || canvasEl.parentElement;
        if (container && getComputedStyle(container).position === 'static') {
            container.style.position = 'relative';
        }

        const containerRect = container?.getBoundingClientRect();
        const caretX = tooltipModel.caretX ?? 0;
        const caretY = tooltipModel.caretY ?? 0;

        // Ensure tooltip is measurable before clamping.
        tooltipEl.style.right = '';
        tooltipEl.style.width = '';
        tooltipEl.style.maxWidth = containerRect ? `${Math.max(220, containerRect.width - 12)}px` : '';

        const tooltipRect = tooltipEl.getBoundingClientRect();
        const containerWidth = containerRect ? containerRect.width : window.innerWidth;

        let left = caretX + 12;
        if (caretX > containerWidth / 2) {
            left = caretX - tooltipRect.width - 12;
        }
        const clampedLeft = Math.max(6, Math.min(left, containerWidth - tooltipRect.width - 6));
        tooltipEl.style.left = clampedLeft + 'px';
        tooltipEl.style.top = (caretY + offsetY) + 'px';
    }

    function buildInboundChart(panelStats, sharedMaxX, inboundScaleFactor) {
        if (current_inbound_chart) {
            current_inbound_chart.destroy();
        }
        inboundBaseColors = panelStats.map(p => p.bgcolor || '#999999');
        current_inbound_chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Inbound'],
                datasets: panelStats.map(p => ({
                    label: (p.panel_display_name || p.panel_name || ''),
                    data: [(parseFloat(p.inbound) || 0) * inboundScaleFactor],
                    rawData: [parseFloat(p.inbound) || 0],
                    backgroundColor: p.bgcolor || '#999999',
                    clip: false
                }))
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                datasets: {
                    bar: {
                        barThickness: 38,
                        maxBarThickness: 38
                    }
                },
                plugins: {
                    legend: {
                        display: false,
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: "nearest",
                        intersect: false,
                        enabled: false,
                        external: function(context) {
                            let elementTag = document.getElementById('current_inbound_chart');
                            let tooltipModel = context.tooltip;

                            if (tooltipModel.opacity === 0) {
                                current_inbound_chart_tooltip.classList.remove("active");
                                return;
                            } else {
                                current_inbound_chart_tooltip.classList.add("active");
                            }
                            const dataIndex = tooltipModel.dataPoints?.[0]?.dataIndex ?? 0;

                            const dp = tooltipModel.dataPoints?.[0];
                            const datasetIndex = dp?.datasetIndex;

                            const dataPoints = context.chart.data.datasets.map((ds, datasetIndex) => ({
                                dataset: ds,
                                datasetIndex,
                                raw: Array.isArray(ds.rawData) ? ds.rawData[dataIndex] : (Array.isArray(ds.data) ? ds.data[dataIndex] : ds.data)
                            })).filter((dp) => {
                                const label = (dp.dataset?.label || '').toString().trim().toLowerCase();
                                return label !== 'unknown/no panel';
                            });

                            let innerHtml = `<div class="font-medium mb-2" style="border-bottom:solid 1px #d1d1d1;padding-bottom:6px;font-weight: 600;">Inbound BreakDown</div>`;
                            const isMobile = window.innerWidth < 768;
                            innerHtml += `<div style="display:grid;grid-template-columns:${isMobile ? '1fr' : '1fr auto'};gap:2px 10px;white-space:normal;line-height:1.2;">`;

                            dataPoints.forEach((dp) => {
                                let color = dp.dataset.backgroundColor;
                                const isActive = dp.datasetIndex === datasetIndex;
                                const itemBg = isActive ? 'rgba(59, 130, 246, 0.12)' : 'transparent';
                                innerHtml += `
                                    <div class="text-sm flex items-center justify-between" style="margin:0px !important;background:${itemBg};border-radius:6px;padding:1px 6px;">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${color}"></span>
                                            <div class="text-xs text-gray-700">${dp.dataset.label}</div>
                                        </div>
                                        <div class="flex items-center gap-2"><span class="bg-gray-100" style="padding:2px 4px;margin:1px;border-radius:3px;font-size:10px;line-height:1;">${dp.raw}</span></div>
                                    </div>
                                 `;
                            });
                            innerHtml += `</div>`;

                            current_inbound_chart_tooltip.innerHTML = innerHtml;
                            current_inbound_chart_tooltip.style.display = 'block';
                            positionChartTooltip(current_inbound_chart_tooltip, elementTag, tooltipModel, 21);
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        display: false,
                        max: sharedMaxX
                    },
                    y: {
                        stacked: true,
                        display: false
                    }
                }
            },
            plugins: [noDataPlugin]
        });
        applyRoundedBar(current_inbound_chart);
        current_inbound_chart.update();
    }

    function buildReceivedChart(panelStats, totals, sharedMaxX, receivedScaleFactor) {
        if (current_received_chart) {
            current_received_chart.destroy();
        }
        receivedBaseColors = [
            ...panelStats.map(p => p.bgcolor || '#999999'),
            ...panelStats.map(p => lightenColor(p.bgcolor || '#999999', 0.7))
        ];
        receivedSplitTotals = {
            inProcess: ((parseFloat(totals?.received) || 0) + (parseFloat(totals?.in_processing) || 0)) * receivedScaleFactor,
            completed: (parseFloat(totals?.completed) || 0) * receivedScaleFactor
        };
        current_received_chart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Total'],
                datasets: [
                    ...panelStats.map(p => ({
                        label: `${(p.panel_display_name || p.panel_name || '')}${RECEIVED_PENDING_SUFFIX}`,
                        data: [((parseFloat(p.received) || 0) + (parseFloat(p.in_processing) || 0)) * receivedScaleFactor],
                        rawData: [(parseFloat(p.received) || 0) + (parseFloat(p.in_processing) || 0)],
                        backgroundColor: p.bgcolor || '#999999',
                        clip: false,
                        borderRadius: {
                            topLeft: 38,
                            bottomLeft: 38
                        }
                    })),
                    ...panelStats.map(p => ({
                        label: `${(p.panel_display_name || p.panel_name || '')}${RECEIVED_COMPLETED_SUFFIX}`,
                        data: [(parseFloat(p.completed) || 0) * receivedScaleFactor],
                        rawData: [parseFloat(p.completed) || 0],
                        backgroundColor: lightenColor(p.bgcolor || '#999999', 0.7),
                        clip: false
                    }))
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                datasets: {
                    bar: {
                        barThickness: 38,
                        maxBarThickness: 38
                    }
                },
                plugins: {
                    legend: {
                        display: false,
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: "nearest",
                        intersect: false,
                        enabled: false,
                        external: function(context) {
                            let elementTag = document.getElementById('current_received_chart');
                            let tooltipModel = context.tooltip;

                            if (tooltipModel.opacity === 0) {
                                current_received_chart_tooltip.classList.remove("active");
                                return;
                            } else {
                                current_received_chart_tooltip.classList.add("active");
                            }
                            const dataIndex = tooltipModel.dataPoints?.[0]?.dataIndex ?? 0;
                            const activeDatasetIndex = tooltipModel.dataPoints?.[0]?.datasetIndex;
                            const allPoints = context.chart.data.datasets.map((ds, datasetIndex) => ({
                                label: ds.label,
                                value: Array.isArray(ds.rawData) ? ds.rawData[dataIndex] : (Array.isArray(ds.data) ? ds.data[dataIndex] : ds.data),
                                color: ds.backgroundColor,
                                datasetIndex
                            }));

                            const inProcess = allPoints.filter(p => p.label.includes(RECEIVED_PENDING_SUFFIX));
                            const completed = allPoints.filter(p => p.label.includes(RECEIVED_COMPLETED_SUFFIX));
                            const activeLabel = tooltipModel.dataPoints?.[0]?.dataset?.label || '';
                            const showInProcess = activeLabel.includes(RECEIVED_PENDING_SUFFIX);
                            const showCompleted = activeLabel.includes(RECEIVED_COMPLETED_SUFFIX);

                            const totalInProcess = inProcess.reduce((sum, p) => sum + (parseFloat(p.value) || 0), 0);
                            const totalCompleted = completed.reduce((sum, p) => sum + (parseFloat(p.value) || 0), 0);
                            const totalsByPanel = allPoints.reduce((acc, p) => {
                                const name = stripReceivedStatus(p.label);
                                acc[name] = (acc[name] || 0) + (parseFloat(p.value) || 0);
                                return acc;
                            }, {});

                            let innerHtml = `<div class="font-medium mb-1" style="font-weight: 600;">Received Breakdown</div>`;
                            if (showInProcess) {
                                innerHtml += `<div style="border-bottom:solid 1px #d1d1d1;padding-bottom:6px;" class="text-xs text-gray-600 mb-2 flex items-center justify-between"><span class="font-semibold text-gray-900">${RECEIVED_PENDING_LABEL}</span>${renderCountBadge(totalInProcess, '#6B7280', 'soft')}</div>`;
                            } else if (showCompleted) {
                                innerHtml += `<div style="border-bottom:solid 1px #d1d1d1;padding-bottom:6px;" class="text-xs text-gray-600 mb-2 flex items-center justify-between"><span class="font-semibold text-gray-900">Completed</span>${renderCountBadge(totalCompleted, '#292b30', 'soft')}</div>`;
                            } else {
                                innerHtml += `<div style="border-bottom:solid 1px #d1d1d1;padding-bottom:6px;" class="text-xs text-gray-600 mb-2 flex items-center justify-between gap-2"><span class="font-semibold text-gray-900">Totals</span><span class="flex items-center gap-1">${renderCountBadge(totalInProcess, '#6B7280', 'soft')}${renderCountBadge(totalCompleted, '#292b30', 'soft')}</span></div>`;
                            }

                            const isMobile = window.innerWidth < 768;
                            const renderSection = (items) => {
                                if (!items.length) return '';
                                let html = `<div style="display:grid;grid-template-columns:${isMobile ? '1fr' : '1fr auto'};gap:2px 12px;margin-bottom:4px;white-space:normal;line-height:1.2;">`;
                                items.forEach((p) => {
                                    const name = stripReceivedStatus(p.label);
                                    const total = totalsByPanel[name] ?? 0;
                                    const isActive = p.datasetIndex === activeDatasetIndex;
                                    const itemBg = isActive ? 'rgba(59, 130, 246, 0.12)' : 'transparent';
                                    html += `
                                        <div class="text-sm flex items-center justify-between gap-2" style="background:${itemBg};border-radius:6px;padding:1px 6px;">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${p.color}"></span>
                                                <div class="text-xs text-gray-700">${name}</div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                ${renderCountBadge(p.value, p.color)}
                                                ${renderCountBadge(total, p.color, 'soft')}
                                            </div>
                                        </div>
                                    `;
                                });
                                html += `</div>`;
                                return html;
                            };

                            if (showInProcess) {
                                innerHtml += renderSection(inProcess);
                            } else if (showCompleted) {
                                innerHtml += renderSection(completed);
                            } else {
                                innerHtml += renderSection(inProcess);
                            }

                            current_received_chart_tooltip.innerHTML = innerHtml;
                            current_received_chart_tooltip.style.display = 'block';
                            positionChartTooltip(current_received_chart_tooltip, elementTag, tooltipModel, 21);
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        display: false,
                        max: sharedMaxX
                    },
                    y: {
                        stacked: true,
                        display: false
                    }
                }
            },
            plugins: [receivedSplitPlugin, noDataPlugin]
        });
        applyRoundedBar(current_received_chart);
        current_received_chart.update();
    }

    jQuery(function($) {
        const CURRENT_QUEUE_FILTER_ALL = '__all__';
        let currentQueueSampleType = CURRENT_QUEUE_FILTER_ALL;
        let cachedBtypeOptions = [];

        function toNumber(value) {
            return parseFloat(value) || 0;
        }

        function escapeHtml(text) {
            return (text || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getBtypeOptions(resp) {
            const optionRows = Array.isArray(resp?.btype_options) ? resp.btype_options : [];
            if (optionRows.length > 0) {
                return optionRows
                    .map((row) => {
                        const value = (row?.value || '').toString().trim();
                        const label = (row?.label || value).toString().trim();
                        return {
                            value,
                            label
                        };
                    })
                    .filter((row) => row.value);
            }

            const rawBtypes = resp?.btypes;
            const btypes = Array.isArray(rawBtypes) ?
                rawBtypes :
                (rawBtypes && typeof rawBtypes === 'object' ? Object.values(rawBtypes) : []);
            return btypes
                .map(v => (v || '').toString().trim())
                .filter(Boolean)
                .map(v => ({
                    value: v,
                    label: v.toUpperCase()
                }));
        }

        function updateSampleTypeFilter(resp) {
            const incomingOptions = getBtypeOptions(resp);
            if (incomingOptions.length > 1 || currentQueueSampleType === CURRENT_QUEUE_FILTER_ALL) {
                cachedBtypeOptions = incomingOptions;
            }
            const options = incomingOptions.length > 1 ?
                incomingOptions :
                (cachedBtypeOptions.length ? cachedBtypeOptions : incomingOptions);
            const $wrap = $("#current_queue_sample_filter_wrap");
            const $select = $("#current_queue_sample_filter");
            const ensureCustomFilterSelect = (forceRebuild = false) => {
                const $wrapper = $select.next(".custom-select");
                if (forceRebuild && $wrapper.length) {
                    $wrapper.remove();
                    $select.removeData("customized");
                }

                if (typeof window.initCustomSelect === "function" && !$select.next(".custom-select").length) {
                    window.initCustomSelect("#current_queue_sample_filter");
                }
                if (typeof window.refreshCustomSelect === "function") {
                    window.refreshCustomSelect("current_queue_sample_filter");
                }
            };

            if (options.length > 1) {
                const optionHtml = [`<option value="${CURRENT_QUEUE_FILTER_ALL}">All Types</option>`]
                    .concat(options.map(opt => `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`));
                $select.html(optionHtml.join(''));

                const stillExists = options.some(opt => opt.value === currentQueueSampleType);
                if (!stillExists) currentQueueSampleType = CURRENT_QUEUE_FILTER_ALL;

                $select.val(currentQueueSampleType);
                ensureCustomFilterSelect(true);
                $wrap.show();
                return;
            }

            currentQueueSampleType = CURRENT_QUEUE_FILTER_ALL;
            $select.html(`<option value="${CURRENT_QUEUE_FILTER_ALL}">All Types</option>`);
            ensureCustomFilterSelect(true);
            $wrap.hide();
        }

        function renderCurrentQueueByPanel(resp) {
            if (!resp || !resp.panel_stats) return;

            const allPanelStats = Object.values(resp.panel_stats || {});
            updateSampleTypeFilter(resp);

            const panelStats = allPanelStats;
            const totals = (resp.system_totals || {});

            const inboundTotal = toNumber(totals.inbound);
            $("#current_inbound_total").text(inboundTotal || 0);
            const receivedTotal = toNumber(totals.received_total);
            $("#current_received_total").text(receivedTotal || 0);
            const $receivedBox = $("#current_received_box");
            const processingTotal = toNumber(totals.in_processing);
            const completedTotal = toNumber(totals.completed);

            if (receivedTotal == 0) {
                $receivedBox.removeClass("flex").hide();
            } else {
                $receivedBox.addClass("flex").show();
            }

            const $liveBody = $("#current_queue_live_body");
            const $liveTotals = $("#current_queue_live_totals");
            if ($liveBody.length) {
                $liveBody.empty();
                panelStats.forEach(p => {
                    const panelDisplayName = p.panel_display_name || p.panel_name || '';
                    const receivedDisplay = toNumber(p.received_total);
                    const inboundPct = inboundTotal > 0 ? Math.round((toNumber(p.inbound) / inboundTotal) * 100) : 0;
                    const receivedPct = receivedTotal > 0 ? Math.round((receivedDisplay / receivedTotal) * 100) : 0;
                    const processingPct = processingTotal > 0 ? Math.round((toNumber(p.in_processing) / processingTotal) * 100) : 0;
                    const completedPct = completedTotal > 0 ? Math.round((toNumber(p.completed) / completedTotal) * 100) : 0;
                    $liveBody.append(`
                        <tr class="border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" style="background-color: ${p.bgcolor || '#999999'};"></div>
                                    <span class="font-medium text-gray-700 tooltip-trigger" data-tooltip="${p.panel_name}">${panelDisplayName}</span>
                                </div>
                            </td>
                            <td class="p-3 text-center text-slate-900 font-bold" style="font-weight:600">${p.inbound || 0}</td>
                            <td class="p-3 text-center text-xs font-bold text-slate-500" style="font-weight:600">${inboundPct}%</td>
                            <td class="p-3 text-center text-slate-900 font-bold" style="font-weight:600">${receivedDisplay}</td>
                            <td class="p-3 text-center text-xs font-bold text-slate-500" style="font-weight:600">${receivedPct}%</td>
                            <td class="p-3 text-center text-slate-900 font-bold" style="font-weight:600">${p.in_processing || 0}</td>
                            <td class="p-3 text-center text-xs font-bold text-slate-500" style="font-weight:600">${processingPct}%</td>
                            <td class="p-3 text-center text-slate-900 font-bold"  style="font-weight:600">${p.completed || 0}</td>
                            <td class="p-3 text-center text-xs font-bold text-slate-500" style="font-weight:600">${completedPct}%</td>
                        </tr>
                    `);
                });
                $('#table_current_queue_live').sort_table();
            }

            if ($liveTotals.length) {
                const systemInboundPct = inboundTotal > 0 ? 100 : 0;
                const systemReceivedPct = receivedTotal > 0 ? 100 : 0;
                const systemProcessingPct = processingTotal > 0 ? 100 : 0;
                const systemCompletedPct = completedTotal > 0 ? 100 : 0;
                $liveTotals.html(`
                    <td class="p-3">Total</td>
                    <td class="p-3 text-center text-slate-900">${totals.inbound || 0}</td>
                    <td class="p-3 text-center text-xs font-bold text-slate-500">${systemInboundPct}%</td>
                    <td class="p-3 text-center text-slate-900">${totals.received_total || 0}</td>
                    <td class="p-3 text-center text-xs font-bold text-slate-500">${systemReceivedPct}%</td>
                    <td class="p-3 text-center text-slate-900 font-bold">${totals.in_processing || 0}</td>
                    <td class="p-3 text-center text-xs font-bold text-slate-500">${systemProcessingPct}%</td>
                    <td class="p-3 text-center text-slate-900 font-bold">${totals.completed || 0}</td>
                    <td class="p-3 text-center text-xs font-bold text-slate-500">${systemCompletedPct}%</td>
                `);
            }

            const $legend = $("#current_queue_panel_legend");
            if ($legend.length) {
                $legend.empty();
                panelStats.forEach(p => {
                    const panelDisplayName = p.panel_display_name || p.panel_name || '';
                    $legend.append(`
                        <div class="current-queue-legend-item flex items-center gap-2 tooltip-trigger" data-panel-name="${panelDisplayName}" data-tooltip="${p.panel_name}" style="padding:2px 4px;border-radius:6px;transition:background-color .15s ease;">
                            <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${p.bgcolor || '#999999'};"></div>
                            <span class="text-xs text-gray-600 truncate">${panelDisplayName}</span>
                        </div>
                    `);
                });
            }

            const inboundPanelTotalRaw = panelStats.reduce((sum, p) => sum + toNumber(p.inbound), 0);
            const receivedPanelTotalRaw = panelStats.reduce((sum, p) => {
                return sum + toNumber(p.received) + toNumber(p.in_processing) + toNumber(p.completed);
            }, 0);
            const inboundTargetTotal = inboundTotal;
            const receivedTargetTotal = receivedTotal;
            const sharedMaxX = Math.max(inboundTargetTotal, receivedTargetTotal, 1);
            const sharedMaxXWithPadding = sharedMaxX * 1.02;
            // Keep both charts on the exact same linear scale (no visual compression).
            const inboundScaleFactor = inboundPanelTotalRaw > 0 ? (inboundTargetTotal / inboundPanelTotalRaw) : 1;
            const receivedScaleFactor = receivedPanelTotalRaw > 0 ? (receivedTargetTotal / receivedPanelTotalRaw) : 1;

            buildInboundChart(panelStats, sharedMaxXWithPadding, inboundScaleFactor);
            buildReceivedChart(panelStats, totals, sharedMaxXWithPadding, receivedScaleFactor);
        }

        function normalizePanelName(name) {
            return (name || '').toString().trim();
        }

        function setChartHoverHighlight(panelName) {
            const target = normalizePanelName(panelName);
            if (current_inbound_chart) {
                current_inbound_chart.data.datasets.forEach((ds, idx) => {
                    const isMatch = normalizePanelName(ds.label) === target;
                    ds.backgroundColor = colorWithAlpha(inboundBaseColors[idx], isMatch ? 1 : 0.38);
                });
                current_inbound_chart.update('none');
            }
            if (current_received_chart) {
                current_received_chart.data.datasets.forEach((ds, idx) => {
                    const name = normalizePanelName(stripReceivedStatus(ds.label));
                    const isMatch = name === target;
                    ds.backgroundColor = colorWithAlpha(receivedBaseColors[idx], isMatch ? 1 : 0.38);
                });
                current_received_chart.update('none');
            }
        }

        function resetChartHoverHighlight() {
            if (current_inbound_chart) {
                current_inbound_chart.data.datasets.forEach((ds, idx) => {
                    ds.backgroundColor = inboundBaseColors[idx] || ds.backgroundColor;
                });
                current_inbound_chart.update('none');
            }
            if (current_received_chart) {
                current_received_chart.data.datasets.forEach((ds, idx) => {
                    ds.backgroundColor = receivedBaseColors[idx] || ds.backgroundColor;
                });
                current_received_chart.update('none');
            }
        }

        function loadCurrentQueueByPanel() {
            const data = {
                trajax: "current_queue_by_panel_ajax",
                export_func: "load_data",
                selDay: selDay
            };
            if (currentQueueSampleType !== CURRENT_QUEUE_FILTER_ALL) {
                data.btype = currentQueueSampleType;
            }
            const fromVal = $('[name="from"]').val();
            const toVal = $('[name="to"]').val();
            if (fromVal) data.from = fromVal;
            if (toVal) data.to = toVal;
            $.ajax({
                url: location.href,
                data: data,
                dataType: "json",
                success: function(resp) {
                    hideLoading('box_current_queue_pane');
                    renderCurrentQueueByPanel(resp);
                }
            });
        }

        loadCurrentQueueByPanel();

        $("#current_queue_sample_filter").on("change", function() {
            currentQueueSampleType = $(this).val() || CURRENT_QUEUE_FILTER_ALL;
            showLoading('box_current_queue_pane');
            loadCurrentQueueByPanel();
        });

        $("#btn_current_queue_live").click(function() {
            $("#current_queue_live").toggle();
        });

        $(".btn-export").on("click", function(e) {
            e.stopPropagation();
            var id_btn = $(this).attr("id");
            $("#menu_" + id_btn).toggle();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest(".custom-select").length) {
                $(".custom-select .options").hide();
            }

            if (!$(e.target).closest(".menu-export").length) {
                $(".menu-export").hide();
            }
        });

        $(".export_item").click(async function() {
            var export_func = $(this).attr("export_func");
            var export_type = $(this).attr("export_type");
            var $url = location.href + '&trajax=current_queue_by_panel_ajax&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&selDay=' + selDay;
            if (currentQueueSampleType !== CURRENT_QUEUE_FILTER_ALL) {
                $url += '&btype=' + encodeURIComponent(currentQueueSampleType);
            }
            var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');
            try {
                const response = await fetch($url);
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

                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
        });

        $(document).on("mouseenter", ".current-queue-legend-item", function() {
            const panelName = $(this).data("panel-name");
            $(this).css("background-color", "rgba(59, 130, 246, 0.08)");
            setChartHoverHighlight(panelName);
        });

        $(document).on("mouseleave", ".current-queue-legend-item", function() {
            $(this).css("background-color", "transparent");
            resetChartHoverHighlight();
        });
    });
</script>