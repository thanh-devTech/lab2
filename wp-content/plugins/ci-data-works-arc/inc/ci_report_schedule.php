<?php
class CI_Report_Schedule
{
    public $title = '';
    public $type_report = 'abx';
    public $type_tab = 'all'; // hoặc là tab nếu có

    public $path_include_ajax_download = ''; //Đường dẫn để xử lý request download

    public $enable = false;

    public function __construct($type = 'abx', $title = '', $path_ajax = '')
    {
        $this->enable = tr_get_option('ci_pdf_data', 'report_schedule');

        $this->type_report = $type;
        $this->title = $title;
        $this->path_include_ajax_download = $path_ajax;
    }

    public function get_title()
    {
        if ($this->title == '') {
            return "Scheduled Report";
        } else {
            return 'Scheduled Report - ' . $this->title;
        }
    }

    public function get_title_by_type($type, $tab = '', $tab_type = '')
    {
        switch ($type) {
            case 'abx':

                $tabs = array(
                    'uti' => array('title' => 'UTI', 'groupids' => array(), 'actived' => true),
                    'wound' => array('title' => 'Wound', 'groupids' => array(), 'actived' => true),
                    'r' => array('title' => 'Respiratory', 'groupids' => array(), 'actived' => true),
                    'ei' => array('title' => 'ENT', 'groupids' => array(), 'actived' => true),
                    'v' => array('title' => 'Vaginal', 'groupids' => array(), 'actived' => true),
                    'sti' => array('title' => 'STI', 'groupids' => array(), 'actived' => true),
                    'nail' => array('title' => 'Nail', 'groupids' => array(), 'actived' => true),
                    'gi' => array('title' => 'GI', 'groupids' => array(), 'actived' => true),
                );

                $tab_title = $tabs[$tab]['title'] ?? '';
                return 'igniteABX' . ($tab == 'all' ? ' - All Panels' . ($tab_type == 'type' ? ' (Type View)' : ' (Panel View)') : ' - ' . $tab_title . ' Panel');
            case 'delay':
                return 'Delay Trends';
            case 'current_delays':
                return 'Current Delays';
            case 'test_volumes':
                return 'Test Volumes Report';
            case 'tat_report':
                return 'TAT Report';
            case 'quality_metrics':
                return 'Quality Metrics';
            case 'providers':
                return 'Providers Report';
            case 'collectors':
                return 'Collectors Report';
            case 'processed_samples':
                return 'Processed Samples';
            case 'prevalence_rates':
                return 'Prevalence Rates';
            case 'positive_negative':
                return 'Positive/Negative Report';
            case 'audit':
                return 'Audit Report';
            case 'blood_result':
                return 'Blood Result Report';
            case 'clinic_panel_stats':
                return 'Clinic Panel Stats';
            case 'insurance_verification':
                return 'Insurance Verification';
            case 'rack':
                return 'Rack Report';
            case 'not_received':
                return 'Not Received';
            case 'state_report':
                return 'State Report';
            default:
                return 'Scheduled Report';
        }
    }

    public function get_mail_body()
    {
        $content    = file_get_contents(TRCPDD_PATH . 'parts/schedule/email_reports.htm');
        $image_path = TRCPDD_PATH . 'parts/schedule/images/ignitesignature.png';
        $image_url = TRCPDD_URL . 'parts/schedule/images/ignitesignature.png';
        // $image_url  = 'data:image/png;base64,' . base64_encode(file_get_contents($image_path));

        $content    = str_replace('{{company_logo_url}}', $image_url, $content);

        return $content;
    }

    public function style()
    {
        if (!$this->enable) {
            return;
        }
?>
        <style>
            .bg-mail {
                background-color: #fcece8;
                color: #fa7946 !important;
            }

            .bg-mail:hover {
                background-color: #ffcec2;
                color: #d83d00 !important;
            }

            .z-50 {
                z-index: 10000;
            }

            #toast-container-top_center {
                z-index: 10001;
            }

            .swal2-container {
                z-index: 1000000 !important;
            }
        </style>
    <?php
    }

    public function btn_schedule()
    {
        if (!$this->enable) {
            return;
        }
    ?>
        <button onClick="show_schedule();return false;" data-slot="button" class="inline-flex bg-mail cursor-pointer items-center justify-center gap-2 whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 size-9 rounded-md h-8 w-8 bg-[#D83D00]/10 hover:bg-[#D83D00]/20 text-[#D83D00]" title="Schedule Report"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail h-4 w-4" aria-hidden="true">
                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
            </svg>
        </button>
    <?php
    }

    public function js()
    {
        if (!$this->enable) {
            return;
        }
    ?>
        <script>
            function updateCheckboxUI($el) {
                const isChecked = $el.attr("data-state") === "checked";

                $el.attr({
                    "aria-checked": isChecked ? "true" : "false",
                    "value": isChecked ? "on" : "off"
                });

                $el.find("span")
                    .toggleClass("flex", isChecked)
                    .toggleClass("hidden", !isChecked);
            }

            function loadListSchedule() {
                $("#list_schedule_book").html("Loading...");
                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'schedule',
                    sub_ajax: 'load_schedule_list',
                    tab: (typeof tab !== 'undefined' ? tab : 'all'),
                    type_report: '<?php echo $this->type_report; ?>'
                }, function(response) {
                    $("#list_schedule_book").html(response);

                    if ($(".schedule_item").length > 0)
                        $("#schedule_list_title").show();
                    else
                        $("#schedule_list_title").hide();

                    $(".btn_schedule_del").click(function() {
                        var id = $(this).data("id");
                        var user_id = $(this).data("user_id");
                        Swal.fire({
                            title: 'Schedule Report',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'schedule',
                            sub_ajax: 'delete_schedule',
                            schedule_id: id,
                            user_id
                        }, function(res) {
                            Swal.close();
                            if (res.success) {
                                showToast('success', 'Delete', res.data, 4000, 'top_center');
                                loadListSchedule();
                            } else {
                                showToast('error', 'Delete', res.data, 4000, 'top_center');
                            }
                        });
                    });

                    $(".btn_schedule_pause").click(function() {
                        var id = $(this).data("id");
                        var user_id = $(this).data("user_id");
                        var status = $(this).data("status");
                        if (status == 0)
                            status = 1;
                        else
                            status = 0;

                        Swal.fire({
                            title: 'Schedule Report',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'schedule',
                            sub_ajax: 'pause_schedule',
                            schedule_id: id,
                            status,
                            user_id
                        }, function(res) {
                            Swal.close();
                            if (res.success) {
                                showToast('success', 'Schedule Status', res.data, 4000, 'top_center');
                                loadListSchedule();
                            } else {
                                showToast('error', 'Schedule Status', res.data, 4000, 'top_center');
                            }
                        });

                    });
                });
            }

            function check_ui_frequency(v) {
                $("#box_day_of_week").hide();
                $("#box_day_of_month").hide();

                if (v == 'weekly') $("#box_day_of_week").show();
                if (v == 'monthly') $("#box_day_of_month").show();
            }

            function show_schedule() {
                var from_date_schedule = '<?php echo date_i18n('Y-m-d H:i:s'); ?>';
                Swal.fire({
                    title: 'Schedule Report',
                    text: 'Loading...Please wait...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'schedule',
                    sub_ajax: 'load_schedule',
                    view_by: (typeof view_by !== 'undefined' ? view_by : 'all'),
                    tab: (typeof tab !== 'undefined' ? tab : 'all'),
                    type_report: '<?php echo $this->type_report; ?>'
                }, function(response) {
                    Swal.close();
                    var htmlBox = $(response);
                    $("body").append(htmlBox);
                    initCustomSelect("#sel_frequency");
                    initCustomSelect("#sel_day_of_week");
                    initCustomSelect("#sel_day_of_month");
                    $(htmlBox).find("#btn_close_box").click(function() {
                        $(htmlBox).remove();
                    });

                    $(htmlBox).find("#btn_box_cancel").click(function() {
                        $(htmlBox).remove();
                    });

                    $(document).off('click', 'button[role="checkbox"]').on('click', 'button[role="checkbox"]', function() {
                        const $btn = $(this);
                        const name = $btn.attr("name");
                        const newState = $btn.attr("data-state") === "unchecked" ? "checked" : "unchecked";

                        $btn.attr("data-state", newState);
                        updateCheckboxUI($btn);
                    });

                    check_ui_frequency($("#sel_frequency").val());
                    $("#sel_frequency").change(function() {
                        var v = $(this).val();
                        check_ui_frequency(v);
                    });

                    $(htmlBox).find("#btn_box_send").click(function() {
                        var schedule_email = $("#schedule_email").val();
                        var sel_frequency = $("#sel_frequency").val();
                        var sel_day_of_week = $("#sel_day_of_week").val();
                        var sel_day_of_month = $("#sel_day_of_month").val();
                        var schedule_time = $("#schedule_time").val();
                        var is_pdf = 1;
                        var is_csv = 1;
                        var msg = '';

                        if ($("#schedule_pdf").attr("value") == "on") is_pdf = 1;
                        else is_pdf = 0;
                        if ($("#schedule_csv").attr("value") == "on") is_csv = 1;
                        else is_csv = 0;

                        if (is_csv == 0 && is_pdf == 0) {
                            showToast('error', 'Error', 'Please select at least one export format', 4000, 'top_center');
                            return;
                        } else if (is_csv == 1 && is_pdf == 1) {
                            msg = 'PDF & CSV ';
                        } else if (is_csv == 1) {
                            msg = 'CSV ';
                        } else if (is_pdf == 1) {
                            msg = 'PDF ';
                        }

                        if (sel_frequency == 'daily') {
                            msg = msg + ' will be sent to ' + schedule_email + ' ' + sel_frequency + ' at ' + schedule_time;
                        }

                        if (sel_frequency == 'weekly') {
                            msg = msg + ' will be sent to ' + schedule_email + ' ' + sel_frequency + ' on ' + sel_day_of_week + ' at ' + schedule_time;
                        }

                        if (sel_frequency == 'monthly') {
                            msg = msg + ' will be sent to ' + schedule_email + ' ' + sel_frequency + ' on ' + sel_day_of_month + ' at ' + schedule_time;
                        }

                        Swal.fire({
                            title: 'Schedule Report',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'schedule',
                            sub_ajax: 'setup_book_schedule',
                            schedule_email,
                            schedule_time,
                            is_csv,
                            is_pdf,
                            sel_frequency,
                            sel_day_of_week,
                            sel_day_of_month,
                            start_date: $("#start_date").val(),
                            tab: (typeof tab !== 'undefined' ? tab : 'all'),
                            cid: (typeof cid !== 'undefined' ? cid : 0),
                            gid: (typeof gid !== 'undefined' ? gid : 0),
                            view_by: (typeof view_by !== 'undefined' ? view_by : 'all'),
                            month_range: (typeof month_range !== 'undefined' ? month_range : 'last_0_month'),
                            type_report: '<?php echo $this->type_report; ?>'
                        }, function(res) {
                            Swal.close();
                            if (res.success) {
                                showToast('success', 'Schedule igniteABX Reports Report', msg, 4000, 'top_center');
                                loadListSchedule();
                            } else {
                                showToast('error', 'Error', res.data, 4000, 'top_center');
                            }
                        });
                    });

                    $("#btn_date_schedule").daterangepicker({
                            opens: 'right',
                            singleDatePicker: true,
                            showDropdowns: true,
                            autoApply: true,
                            startDate: moment($("#start_date").val()),
                        },
                        function(start, end, label) {
                            let label_date_range = start.format('MMM D, YYYY');
                            $("#txt_show_date_schedule").html(label_date_range);
                            from_date_schedule = start.format('YYYY-MM-DD');
                            $("#start_date").val(from_date_schedule);
                        }
                    );
                    loadListSchedule();

                    $("#btn_test_pdf").click(function() {
                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'schedule',
                            sub_ajax: 'pdf',
                            schedule_email: $("#schedule_email").val(),
                            tab: (typeof tab !== 'undefined' ? tab : 'all'),
                            view_by: (typeof view_by !== 'undefined' ? view_by : 'all'),
                            type_report: '<?php echo $this->type_report; ?>'
                        }, function(response) {
                            alert(response);
                            console.log(response);

                        });
                    });

                });
            }
        </script>
    <?php
    }

    function ajax()
    {
        if (!$this->enable) {
            return;
        }
        $trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
        $sub_ajax = isset($_REQUEST['sub_ajax']) ? $_REQUEST['sub_ajax'] : '';
        if ($trajax == 'schedule') {
            if ($sub_ajax == 'load_schedule') $this->ajax_load_schedule();
            if ($sub_ajax == 'load_schedule_list') $this->ajax_load_schedule_list();
            if ($sub_ajax == 'setup_book_schedule') $this->ajax_setup_book_schedule();
            if ($sub_ajax == 'pause_schedule') $this->ajax_pause_schedule();
            if ($sub_ajax == 'delete_schedule') $this->ajax_delete_schedule();
            if ($sub_ajax == 'pdf') $this->ajax_test();
            exit;
        }
    }

    function ajax_load_schedule()
    {
        if (!$this->enable) {
            return;
        }
        $type_report = isset($_REQUEST['type_report']) ? $_REQUEST['type_report'] : 'abx';
        $view_by = isset($_REQUEST['view_by']) ? $_REQUEST['view_by'] : 'type';
        $tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'all';
        $this->type_tab = $tab;
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $is_admin = in_array('administrator', $current_user->roles);

        $schedules = get_option('ci_user_schedules', []);

        $current_schedule = null;
        $title_box = 'Schedule Email Report - ';
        if ($type_report == 'delay') {
            $title_box .= 'Specimen Delay Trends';
        } else if ($type_report == 'current_delays') {
            $title_box .= 'Current Delays';
        } else {
            if ($this->title == '') {
                if ($tab == 'all') {
                    $title_box .= 'All Panels';
                    if ($view_by == 'type') {
                        $title_box .= ' (Type View)';
                    } else {
                        $title_box .= ' (Panel View)';
                    }
                } else {
                    $title_box .= strtoupper($tab) . ' Panel';
                }
            } else {
                $title_box .= $this->title;
            }
        }

        if (!empty($schedules[$user_id])) {
            // mỗi user chỉ 1 schedule
            $schedule_id = array_key_first($schedules[$user_id]);
            $current_schedule = $schedules[$user_id][$schedule_id];
        }
    ?>
        <style>
            #emp_sel_frequency,
            #schedule_email,
            #emp_sel_day_of_week,
            #emp_sel_day_of_month,
            #schedule_time {
                border: solid 1px #cccccc !important;
                border-radius: 8px !important;
                background-color: #f8f9fb !important;
            }

            .daterangepicker {
                z-index: 10000;
            }
        </style>
        <div data-state="open" data-slot="dialog-overlay" class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/50" style="pointer-events: auto;" data-aria-hidden="true" aria-hidden="true"></div>

        <div role="dialog" id="radix-:r4n:" aria-describedby="radix-:r4p:" aria-labelledby="radix-:r4o:" data-state="open"
            data-slot="dialog-content"
            class="bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg max-w-2xl max-h-[90vh] overflow-y-auto"
            tabindex="-1" style="pointer-events: auto;">
            <div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-left">
                <h2 style="font-size:1.125rem;font-weight: 600 !important;" id="radix-:r4o:" data-slot="dialog-title" class="text-lg leading-none font-semibold">
                    <?php echo $title_box; ?>
                </h2>
                <p id="radix-:r4p:" data-slot="dialog-description" class="text-muted-foreground text-sm">Schedule a report to be sent automatically to the specified email address.</p>
            </div>
            <div class="space-y-6 pb-4 -mt-2">
                <div class="space-y-4 p-4 bg-gray-50 rounded-lg">
                    <h3 style="font-size:1rem;font-weight: 500 !important;--tw-font-weight:500 !important;margin-top:0px !important;margin-bottom:13px !important;" class="font-medium">Create New Schedule</h3>
                    <div class="space-y-2">
                        <?php
                        $email = !empty($current_schedule['email']) ? $current_schedule['email'] : ($current_user && $current_user->exists() ? $current_user->user_email : ''); ?>
                        <label data-slot="label"
                            class="text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 flex items-center gap-2"
                            for="email">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-mail h-4 w-4" aria-hidden="true">
                                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            </svg>
                            Email Address</label>
                        <input id="schedule_email"
                            type="email" data-slot="input"
                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border-gray-300"
                            id="email"
                            placeholder="recipient@example.com"
                            value="<?php echo $email; ?>">
                    </div>

                    <div class="space-y-2">
                        <label data-slot="label"
                            class="text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 flex items-center gap-2"
                            for="frequency">

                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-clock h-4 w-4" aria-hidden="true">
                                <path d="M12 6v6l4 2"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>Frequency
                        </label>

                        <?php
                        $frequency = 'daily';
                        if (!empty($current_schedule['frequency'])) $frequency = $current_schedule['frequency'];
                        ?>
                        <select id="sel_frequency">
                            <option value="daily" <?php if ($frequency == 'daily') { ?>selected="true" <?php } ?>>Daily</option>
                            <option value="weekly" <?php if ($frequency == 'weekly') { ?>selected="true" <?php } ?>>Weekly</option>
                            <option value="monthly" <?php if ($frequency == 'monthly') { ?>selected="true" <?php } ?>>Monthly</option>
                            <option value="one-time" <?php if ($frequency == 'one-time') { ?>selected="true" <?php } ?>>One-time</option>
                        </select>
                    </div>

                    <div id="box_day_of_week" class="space-y-2" style="display:none">
                        <label data-slot="label"
                            class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            for="sel_day_of_week">Day of Week</label>
                        <?php
                        $sel_day_of_week = 0;
                        if (!empty($current_schedule['sel_day_of_week'])) $sel_day_of_week = intval($current_schedule['sel_day_of_week']);
                        ?>
                        <select id="sel_day_of_week">
                            <option value="0" <?php if ($sel_day_of_week == 0) { ?>selected="true" <?php } ?>>Sunday</option>
                            <option value="1" <?php if ($sel_day_of_week == 1) { ?>selected="true" <?php } ?>>Monday</option>
                            <option value="2" <?php if ($sel_day_of_week == 2) { ?>selected="true" <?php } ?>>Tuesday</option>
                            <option value="3" <?php if ($sel_day_of_week == 3) { ?>selected="true" <?php } ?>>Wednesday</option>
                            <option value="4" <?php if ($sel_day_of_week == 4) { ?>selected="true" <?php } ?>>Thursday</option>
                            <option value="5" <?php if ($sel_day_of_week == 5) { ?>selected="true" <?php } ?>>Friday</option>
                            <option value="6" <?php if ($sel_day_of_week == 6) { ?>selected="true" <?php } ?>>Saturday</option>
                        </select>

                    </div>

                    <div id="box_day_of_month" class="space-y-2" style="display:none">
                        <label data-slot="label"
                            class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            for="day-of-month">Day of Month</label>
                        <?php
                        $sel_day_of_month = 1;
                        if (!empty($current_schedule['sel_day_of_month'])) $sel_day_of_month = intval($current_schedule['sel_day_of_month']);
                        ?>
                        <select id="sel_day_of_month">
                            <?php for ($i = 1; $i < 29; $i++) { ?>
                                <option value="<?php echo $i; ?>" <?php if ($sel_day_of_month == $i) { ?>selected="true" <?php } ?>><?php echo $i; ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label data-slot="label"
                            class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            for="time">Time</label>
                        <input
                            id="schedule_time"
                            type="time" data-slot="input"
                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border-gray-300"
                            value="<?php if (!empty($current_schedule['time'])) {
                                        echo $current_schedule['time'];
                                    } else {
                                        echo '08:00';
                                    }; ?>">
                    </div>

                    <div class="space-y-2">
                        <label data-slot="label"
                            class="text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 flex items-center gap-2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-calendar h-4 w-4" aria-hidden="true">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>Start Date</label>

                        <button id="btn_date_schedule" data-slot="popover-trigger"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 w-full justify-start text-left border-gray-300"
                            type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="radix-:rfd:"
                            data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-calendar h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>
                            <span id="txt_show_date_schedule">
                                <?php if (isset($current_schedule['start_date'])) { ?>
                                    <?php echo date('M j, Y', strtotime($current_schedule['start_date'])); ?>
                                <?php } else { ?>
                                    <?php echo date_i18n('M j, Y'); ?>
                                <?php } ?>
                            </span>
                        </button>

                        <?php
                        $start_date = date_i18n('M j, Y');
                        if (isset($current_schedule['start_date'])) $start_date = date('M j, Y', strtotime($current_schedule['start_date']));
                        ?>
                        <input
                            id="start_date" type="hidden"
                            value="<?php echo $start_date; ?>" />
                    </div>

                    <div class="space-y-2">
                        <label data-slot="label"
                            class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50">Export
                            Format</label>
                        <div class="flex gap-4">
                            <?php
                            $is_pdf = 1;
                            if (isset($current_schedule['is_pdf'])) $is_pdf = $current_schedule['is_pdf'];
                            ?>
                            <div class="flex items-center space-x-2">
                                <button id="schedule_pdf"
                                    type="button"
                                    role="checkbox"
                                    <?php if ($is_pdf == 1) { ?>
                                    aria-checked="true" data-state="checked" value="on"
                                    <?php } else { ?>
                                    aria-checked="false" data-state="unchecked" value="off"
                                    <?php } ?>
                                    data-slot="checkbox"
                                    class="peer cursor-pointer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                                    <span data-state="checked" data-slot="checkbox-indicator"
                                        class="<?php if ($is_pdf == 1) {
                                                    echo 'flex;';
                                                } else {
                                                    echo 'hidden';
                                                } ?> items-center justify-center text-current transition-none"
                                        style="pointer-events: none;"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                    </span>
                                </button>
                                <label for="schedule_pdf" class="cursor-pointer">PDF</label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <?php
                                $is_csv = 1;
                                if (isset($current_schedule['is_csv'])) $is_csv = $current_schedule['is_csv'];
                                ?>
                                <button id="schedule_csv"
                                    type="button"
                                    role="checkbox"
                                    <?php if ($is_csv == 1) { ?>
                                    aria-checked="true" data-state="checked" value="on"
                                    <?php } else { ?>
                                    aria-checked="false" data-state="unchecked" value="off"
                                    <?php } ?>
                                    data-slot="checkbox"
                                    class="peer cursor-pointer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                                    <span data-state="checked" data-slot="checkbox-indicator"
                                        class="<?php if ($is_csv == 1) {
                                                    echo 'flex;';
                                                } else {
                                                    echo 'hidden';
                                                } ?>  items-center justify-center text-current transition-none"
                                        style="pointer-events: none;"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg></span>
                                </button>
                                <label for="schedule_csv" class="cursor-pointer">CSV</label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            id="btn_box_cancel"
                            data-slot="button"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                            Cancel
                        </button>

                        <button
                            id="btn_box_send"
                            data-slot="button"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#D83D00]/90">Schedule
                            Report
                        </button>

                        <button
                            style="display: none" ;
                            id="btn_test_pdf"
                            data-slot="button"
                            class="cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#D83D00]/90">
                            Test
                        </button>

                    </div>
                </div>
            </div>

            <div class="space-y-3" id="list_schedule_book"></div>

            <button id="btn_close_box" type="button"
                class="cursor-pointer ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4">
                <svg
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-x">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
                <span class="sr-only">Close</span>
            </button>
        </div>
    <?php
        exit;
    }

    function render_schedule_item($id, $item, $owner_email = '')
    {
        $item['time'] = date('h:i A', strtotime($item['time']));
    ?>
        <div class="flex schedule_item items-center justify-between p-3 border rounded-lg bg-white">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail h-4 w-4 text-gray-400" aria-hidden="true">
                        <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    </svg>
                    <span class=""><?php echo esc_html($item['email']); ?></span>
                </div>

                <div class="text-sm text-gray-500 mt-1 ml-6">
                    <?php echo ucfirst($item['frequency']); ?> at <?php echo $item['time']; ?>
                    <?php if ($item['is_pdf']) echo ' · PDF'; ?>
                    <?php if ($item['is_csv']) echo ' · CSV'; ?>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button data-user_id="<?php echo $item['user_id']; ?>" data-id="<?php echo $id; ?>" data-status="<?php echo $item['is_pause']; ?>" data-slot="button" class="btn_schedule_pause cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                    <?php echo $item['is_pause'] ? 'Resume' : 'Pause'; ?>
                </button>

                <button data-user_id="<?php echo $item['user_id']; ?>" data-id="<?php echo $id; ?>" data-slot="button" class="btn_schedule_del cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4 text-red-600" aria-hidden="true">
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M3 6h18"></path>
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
        </div>
<?php
    }

    function ajax_load_schedule_list()
    {
        if (!$this->enable) {
            return;
        }
        $tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'all';
        $this->type_tab = $tab;
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $is_admin = false; //in_array('administrator', $current_user->roles);
        $schedules = get_option('ci_user_schedules', []);

        //var_dump($schedules);
        echo '<h3 id="schedule_list_title" class="font-medium">Active Schedules</h3>';
        if ($is_admin) {
            // ADMIN: xem tất cả user
            foreach ($schedules as $uid => $user_schedules) {

                $user_info = get_userdata($uid);
                if (!$user_info) continue;

                foreach ($user_schedules as $id => $item) {
                    if ($item['type_report'] == $this->type_report && $item['tab'] == $this->type_tab)
                        $this->render_schedule_item($id, $item, $user_info->user_email);
                }
            }
        } else {

            // USER THƯỜNG: chỉ xem của mình
            if (!empty($schedules[$user_id])) {
                foreach ($schedules[$user_id] as $id => $item) {
                    //if ($item['is_pause'] == 1) continue; // chỉ active
                    if ($item['type_report'] == $this->type_report && $item['tab'] == $this->type_tab)
                        $this->render_schedule_item($id, $item);
                }
            }
        }

        exit;
    }

    function ci_calc_schedule_timestamp($data)
    {
        if (!$this->enable) {
            return;
        }

        $time = $data['time']; // HH:mm
        switch ($data['frequency']) {

            case 'daily':
                return strtotime($data['start_date'] . ' ' . $data['time']) - (get_option('gmt_offset') * HOUR_IN_SECONDS);

            case 'weekly':
                // sel_day_of_week: 1 (Mon) → 7 (Sun)
                $dow = (int) $data['sel_day_of_week'];
                return strtotime("next " . jddayofweek($dow % 7, 1) . " $time");

            case 'monthly':
                // sel_day_of_month: 1 → 28/29/30
                $dom = (int) $data['sel_day_of_month'];
                $dom = min($dom, date_i18n('t'));
                return strtotime(date_i18n("Y-m-$dom $time"));

            case 'one-time':
                return strtotime($data['start_date'] . ' ' . $data['time']) - (get_option('gmt_offset') * HOUR_IN_SECONDS);
        }

        return false;
    }

    function ajax_setup_book_schedule()
    {
        if (!$this->enable) {
            return;
        }
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('Not logged in');

        if (empty($_REQUEST['schedule_email'])) {
            wp_send_json_error('Please enter an email address');
        }

        $type_report = $_REQUEST['type_report'];
        $tab_report = $_REQUEST['tab'];

        $schedule_id = 'sch_' . $user_id . '_' . $type_report . '_' . $tab_report;


        $schedules = get_option('ci_user_schedules', []);

        $found_schedule_id = null;

        foreach ($schedules as $sid => $item) {
            if (!empty($item['type_report']) && $item['type_report'] === $type_report && $item['tab'] === $tab_report) {
                $found_schedule_id = $sid;
                break;
            }
        }

        if ($found_schedule_id)  $schedule_id = $found_schedule_id;

        $data = [
            'schedule_id' => $schedule_id,
            'email' => sanitize_email($_REQUEST['schedule_email']),
            'time' => sanitize_text_field($_REQUEST['schedule_time']),
            'frequency' => sanitize_text_field($_REQUEST['sel_frequency']),
            'is_csv' => (int) $_REQUEST['is_csv'],
            'is_pdf' => (int) $_REQUEST['is_pdf'],
            'is_pause' => 0,
            'sel_day_of_week' => (int) ($_REQUEST['sel_day_of_week'] ?? 1),
            'sel_day_of_month' => (int) ($_REQUEST['sel_day_of_month'] ?? 1),
            'start_date' => sanitize_text_field($_REQUEST['start_date']),
            'updated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'tab' => $tab_report,
            'view_by' => $_REQUEST['view_by'],
            'month_range' => $_REQUEST['month_range'],
            'type_report' => $type_report
        ];
        $schedules[$user_id][$schedule_id] = $data;
        update_option('ci_user_schedules', $schedules);

        wp_clear_scheduled_hook(
            'cron_all_api',
            ['ci_cron_reports', '', [$user_id, $schedule_id]]
        );

        $timestamp = $this->ci_calc_schedule_timestamp($data);
        if (!$timestamp) {
            wp_send_json_error('Invalid schedule time');
        }

        if ($data['frequency'] === 'one-time') {

            wp_schedule_single_event(
                $timestamp,
                'cron_all_api',
                ['ci_cron_reports', '', [$user_id, $schedule_id]]
            );
        } else {

            wp_schedule_event(
                $timestamp,
                $data['frequency'],
                'cron_all_api',
                ['ci_cron_reports', '', [$user_id, $schedule_id]]
            );
        }

        wp_send_json_success('Schedule saved successfully');
    }

    function ajax_pause_schedule()
    {
        if (!$this->enable) {
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) wp_send_json_error('Not logged in');

        $sid    = $_POST['schedule_id'];
        $status = (int) $_POST['status'];

        // user bị tác động (admin có thể truyền user khác)
        $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $current_user_id;

        $schedules = get_option('ci_user_schedules', []);

        // nếu không phải admin thì chỉ được tác động chính mình
        if (
            $target_user_id !== $current_user_id &&
            !current_user_can('administrator') &&
            !current_user_can('admintision')
        ) {
            wp_send_json_error('Permission denied');
        }

        if (isset($schedules[$target_user_id][$sid])) {

            $schedules[$target_user_id][$sid]['is_pause'] = $status;
            update_option('ci_user_schedules', $schedules);

            wp_send_json_success('Scheduled report updated');
        }

        wp_send_json_error('No booking found');
    }

    function ajax_delete_schedule()
    {
        if (!$this->enable) {
            return;
        }
        $current_user_id = get_current_user_id();
        if (!$current_user_id) wp_send_json_error('Not logged in');

        $sid = $_POST['schedule_id'];

        $target_user_id = isset($_POST['user_id'])
            ? intval($_POST['user_id'])
            : $current_user_id;

        $schedules = get_option('ci_user_schedules', []);

        if (
            $target_user_id !== $current_user_id &&
            !current_user_can('administrator') &&
            !current_user_can('admintision')
        ) {
            wp_send_json_error('Permission denied');
        }

        if (isset($schedules[$target_user_id][$sid])) {

            wp_clear_scheduled_hook('cron_all_api', ['ci_cron_reports', '', [$target_user_id, $sid]]);
            unset($schedules[$target_user_id][$sid]);
            update_option('ci_user_schedules', $schedules);

            wp_send_json_success('Scheduled report deleted');
        }

        wp_send_json_error('No booking found');
    }

    function ajax_test()
    {
        if (!$this->enable) {
            return;
        }
        $dir = WP_CONTENT_DIR . '/cache/abx';
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $filename_pdf = 'report_' . date_i18n('Ymd_His') . '.pdf';
        $filepath_psd = $dir . '/' . $filename_pdf;
        $filename_csv = 'report_' . date_i18n('Ymd_His') . '.csv';
        $filepath_csv = $dir . '/' . $filename_csv;
        $attachments = [];
        $attachments[] = $filepath_psd;
        $attachments[] = $filepath_csv;

        $tab = $_REQUEST['tab'];
        $trajax = 'report_all_panels';
        if ($tab != 'all') $trajax = 'report_tabs';
        $_REQUEST = array_merge($_REQUEST, [
            'page'        => 'ci_reportabx_main',
            'tab'         => $tab,
            'trajax'      => $trajax,
            'export_type' => 'csv',
            'export_func' => 'all',
            'month_range' => 'last_6_month',
            'from'        => date_i18n('Y-m-01'),
            'to'          => date_i18n('Y-m-t'),
            'cid'         => 0,
            'view_by'     => $_REQUEST['view_by'],
            'filepath_save_pdf'    => $filepath_psd,
            'filepath_save_csv'    => $filepath_csv,
            'is_pdf' => 1,
            'is_csv' => 1
        ]);

        $sc = array();
        $sc['email'] = $_REQUEST['schedule_email'];

        if ($_REQUEST['type_report'] == 'delay') {
            include(TRCPDD_PATH . 'parts/reports/sample_delay_trends.php');
        } else if ($_REQUEST['type_report'] == 'current_delays') {
            include(TRCPDD_PATH . 'parts/reports/pending_and_outstanding.php');
        } else if ($_REQUEST['type_report'] == 'test_volumes') {
            include(TRCPDD_PATH . 'parts/reports/test_volumes.php');
        } else if ($_REQUEST['type_report'] == 'tat_report') {
            include(TRCPDD_PATH . 'parts/reports/tat_report.php');
        } else if ($_REQUEST['type_report'] == 'quality_metrics') {
            include(TRCPDD_PATH . 'parts/reports/quality_metrics.php');
        } else if ($_REQUEST['type_report'] == 'providers') {
            include(TRCPDD_PATH . 'parts/reports/providers.php');
        } else if ($_REQUEST['type_report'] == 'collectors') {
            include(TRCPDD_PATH . 'parts/reports/collectors.php');
        } else if ($_REQUEST['type_report'] == 'processed_samples') {
            include(TRCPDD_PATH . 'parts/reports/processed_samples.php');
        } else if ($_REQUEST['type_report'] == 'prevalence_rates') {
            include(TRCPDD_PATH . 'parts/reports/prevalence_rates.php');
        } else if ($_REQUEST['type_report'] == 'positive_negative') {
            include(TRCPDD_PATH . 'parts/reports/positive_negative_report.php');
        } else if ($_REQUEST['type_report'] == 'audit') {
            include(TRCPDD_PATH . 'parts/reports/audit.php');
        } else if ($_REQUEST['type_report'] == 'blood_result') {
            include(TRCPDD_PATH . 'parts/reports/blood_result.php');
        } else if ($_REQUEST['type_report'] == 'clinic_panel_stats') {
            include(TRCPDD_PATH . 'parts/reports/clinic_panel_stats.php');
        } else if ($_REQUEST['type_report'] == 'insurance_verification') {
            include(TRCPDD_PATH . 'parts/reports/insurance_verification.php');
        } else if ($_REQUEST['type_report'] == 'rack') {
            include(TRCPDD_PATH . 'parts/reports/sapphire.php');
        } else if ($_REQUEST['type_report'] == 'not_received') {
            include(TRCPDD_PATH . 'parts/reports/not_received.php');
        } else if ($_REQUEST['type_report'] == 'state_report') {
            include(TRCPDD_PATH . 'parts/reports/state_report.php');
        } else {
            if ($this->path_include_ajax_download != '')
                include(TRCPDD_PATH . $this->path_include_ajax_download);
            else
                include(TRCPDD_PATH . 'parts/reportabx/main.php');
        }
        echo 'Send File success!!!!!!';
    }
}
