<?php
global $wpdb, $current_role;

$user_id            = get_current_user_id();


wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'admin/css/jquery-ui.css', array(), '1.1.9');

?>

<div id="box_tat_chart" data-slot=card class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
    <div data-slot=card-header
        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]">
        <div class="flex items-center justify-between">
            <div>
                <h4 data-slot=card-title class="leading-none flex items-center gap-2">
                    <div class="p-1.5 bg-purple-50 rounded-lg">
                        <svg xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
                            stroke-linejoin=round class="lucide lucide-clock h-4 w-4 text-purple-600">
                            <circle cx=12 cy=12 r=10></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>TAT

                    <svg id="tat_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img">
                        <circle cx="9" cy="9" r="6" fill="#6B7280">
                            <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0s" />
                        </circle>
                        <circle cx="32" cy="9" r="6" fill="#6B7280">
                            <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.15s" />
                        </circle>
                        <circle cx="55" cy="9" r="6" fill="#6B7280">
                            <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.3s" />
                        </circle>
                    </svg>
                </h4>
            </div>
            <div class="flex items-center gap-3 tat-bar">
                <div class="flex items-center gap-2">
                    <button data-slot="button"
                        class="run_turnaround_report inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-7 px-2 text-muted-foreground hover:text-primary hover:bg-transparent transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-activity h-3 w-3 mr-1" aria-hidden="true">
                            <path
                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                            </path>
                        </svg>
                        <textshow>Live Report</textshow>
                    </button>
                    <div style="position:relative;display:inline-block;">
                        <button id="btn_tat_export" data-slot=button
                            class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-7 px-2 text-muted-foreground hover:text-primary hover:bg-transparent transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M12 15V3"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>
                            Export
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chevron-down h-4 w-4 ml-2"
                                aria-hidden="true">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>

                        <div id="menu_taxt_export" data-radix-popper-content-wrapper="" dir="ltr"
                            style="position:absolute;left:-17px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                            <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                tabindex="-1" data-orientation="vertical"
                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                <div export_type="pdf" role="menuitem" data-slot="dropdown-menu-item"
                                    data-variant="default"
                                    class="export_turnaround_report focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
                                <div export_type="csv" role="menuitem" data-slot="dropdown-menu-item"
                                    data-variant="default"
                                    class="export_turnaround_report focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
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
    </div>

    <div data-slot=card-content class="px-6 [&amp;:last-child]:pb-0 pt-0 pb-0 mt-0">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <div class="p-1.5 bg-gray-50 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-funnel h-4 w-4 text-gray-600" aria-hidden="true">
                        <path
                            d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z">
                        </path>
                    </svg>

                </div>
            </div>

            <div class="flex items-center gap-2 tat-bar">
                <select id="tat_sel_days" class="none_border">
                    <option value="14_days">14 Days</option>
                    <option value="30_days">30 Days</option>
                    <option value="90_days">90 Days</option>
                    <option value="customer_range">Custom Range</option>
                </select>

                <button id="btn_day_customer_tat" data-slot="popover-trigger"
                    class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm"
                    title="Select custom date range" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-calendar h-3 w-3 mr-1" aria-hidden="true">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                    </svg>
                    <span class="txt_show_customer_day" data-slot=select-value style=pointer-events:none>-- - --</span>
                </button>

                <div class="flex items-center gap-2">
                    <select id="tat">
                        <option value="tat">TAT</option>
                        <option value="c2r">C2R</option>
                        <option value="r2r">R2R</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <select id="turnaround_btype" class="none_border select-btype">
                        <?php
                        foreach (tr_pdd_get_batch_types() as $btype) {
                            echo '<option value="' . $btype . '">' . tr_pdd_get_typename($btype) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <select id="panel_id" class="none_border">
                        <option value="0">All Panels</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (count($clinics) > 0) { ?>
                        <select id="turnaround_clinic" class="none_border">
                            <?php if (count($clinics) > 1) : ?>
                                <option value="0">All Clinics</option>
                            <?php endif; ?>
                            <?php
                            foreach ($clinics as $c) {
                                $selected = isset($current_clinic) && $current_clinic == $c->cID ? 'selected' : '';
                                echo '<option value="' . $c->cID . '" ' . $selected . '>' . $c->cli_clinic . '</option>';
                            }
                            ?>
                        </select>
                    <?php }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div id="turaround_report" data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3"></div>
</div>



<script>
    var isShowReport = false;
    var tat_day_from = 0;
    var tat_day_to = 0;
    showLoading("box_tat_chart");
    jQuery(function($) {
        $("#btn_day_customer_tat").removeClass("inline-flex").hide();
        window.tr_load_turnaround = function() {
            var $tat = $('#tat').val();
            var $sel_day_tat = $("#tat_sel_days").val();
            var $btype = $('#turnaround_btype').val();
            var $cid = $('#turnaround_clinic').val();
            var $cid_label = $("#turnaround_clinic option:selected").text();
            var $panel_id = $('#panel_id').val();
            $("#tat_loading").show();
            $.ajax({
                data: {
                    trajax: 'tat_ajax',
                    sel_day_tat: $sel_day_tat,
                    from: tat_day_from,
                    to: tat_day_to,
                    tat: $tat,
                    btype: $btype,
                    cid: $cid,
                    cid_label: $cid_label,
                    panel_id: $panel_id,
                },
                success: function(rs) {
                    hideLoading('box_tat_chart');
                    $('#turaround_report').html(rs);
                    $("#tat_loading").hide();
                }
            })
        }

        $('body').on('change', 'select#turnaround_btype', function() {
            var $vl = $(this).val();
            $('#panel_id').val('');
            $('#emp_panel_id').show();
            $html = '<option value="0">All Panels</option>';
            for (i in $panels) {
                if ($panels[i].tg_type == $vl) {
                    $html += '<option value="' + $panels[i].tg_ID + '">' + $panels[i].tg_name +
                        '</option>';
                }
            }

            $('#panel_id').html($html);
            refreshCustomSelect("panel_id");
            tr_load_turnaround();
        }).on('click', '.run_turnaround_report', function() {
            var valHtml = $(this).find("textshow").html();
            if (valHtml.trim() == "Live Report") {
                $(this).find("textshow").html("Hide Live Report");
                $("#report_analysis").show();
                isShowReport = true;
            } else {
                $(this).find("textshow").html("Live Report");
                $("#report_analysis").hide();
                isShowReport = false;
            }
        }).on('click', '.export_turnaround_report', function() {
            var export_type = $(this).attr("export_type");
            showToast('info', 'Preparing your download...', 'Your ' + export_type.toUpperCase() + ' export is being generated. This may take a few moments.', 4000);
            var $url = location.href + '&trajax=tat_ajax&export=true&export_type=' + export_type;
            $url += '&sel_day_tat=' + $("#tat_sel_days").val();
            $url += '&from=' + tat_day_from;
            $url += '&to=' + tat_day_to;
            $url += '&tat=' + $('#tat').val();
            $url += '&btype=' + $('#turnaround_btype').val();
            $url += '&cid=' + $('#turnaround_clinic').val();
            $url += '&panel_id=' + $('#panel_id').val();
            $url += '&panel_name=' + $('#panel_id option:selected').text();
            $url += '&cid_label=' + $("#turnaround_clinic option:selected").text();
            window.open($url, '_blank');
            setTimeout(() => {
                showToast('success', 'Download ready!', 'Your sample report has been downloaded successfully.', 4000);
            }, 5000);
            return false;
        })

        $('#panel_id').on('change', function() {
            tr_load_turnaround();
        });

        $('#turnaround_clinic').on('change', function() {
            tr_load_turnaround();
        });

        $('#tat_sel_days').on('change', function() {
            var sel_day = $(this).val();
            if (sel_day == 'customer_range') {
                $("#btn_day_customer_tat").addClass("inline-flex").show();
                $('#btn_day_customer_tat').data('daterangepicker').show();
            } else {
                $("#btn_day_customer_tat").removeClass("inline-flex").hide();
                tr_load_turnaround();
            }
        });

        $("#btn_tat_export").on("click", function(e) {
            e.stopPropagation();
            $("#menu_taxt_export").toggle();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest("#btn_tat_export, #menu_taxt_export").length) {
                $("#menu_taxt_export").hide();
            }
        });

        $('#btn_day_customer_tat').daterangepicker({
                opens: 'right'
            },
            function(start, end, label) {
                $('#btn_day_customer_tat span').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
                tat_day_from = start.format('YYYY-MM-DD');
                tat_day_to = end.format('YYYY-MM-DD');
                tr_load_turnaround();
            }
        );

        $('select#turnaround_btype').change();
        setTimeout(() => {
            $("#emp_tat").addClass("tat_btn");
        }, 600);
    })
</script>