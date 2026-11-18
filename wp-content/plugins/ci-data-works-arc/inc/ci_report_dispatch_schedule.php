<?php
class CI_Report_Dispatch_Schedule
{
    public $enable = false;
    public $option_key = 'ci_user_dispatch_schedules';
    public $feature_key = 'report_dispatch_schedule';
    public $report_type = 'not_received';
    public $report_label = 'Not Received';

    public function __construct($report_type = 'not_received', $report_label = 'Not Received', $feature_key = 'report_dispatch_schedule')
    {
        $this->report_type = $report_type;
        $this->report_label = $report_label;
        $this->feature_key = $feature_key;
        $this->enable = tr_get_option('ci_pdf_data', $this->feature_key);
    }

    public function get_title()
    {
        return 'Dispatch Alert - ' . $this->report_label;
    }

    public function get_mail_body($summary_rows = array())
    {
        $summary_html = '';

        if (!empty($summary_rows)) {
            $summary_html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0 24px 0;">';
            $summary_html .= '<thead>';
            $summary_html .= '<tr>';
            $summary_html .= '<th style="border:1px solid #d6d6d6;padding:12px 14px;text-align:left;background:#f7f7f7;font-size:14px;">Clinic</th>';
            $summary_html .= '<th style="border:1px solid #d6d6d6;padding:12px 14px;text-align:left;background:#f7f7f7;font-size:14px;">Not Received Samples</th>';
            $summary_html .= '<th style="border:1px solid #d6d6d6;padding:12px 14px;text-align:left;background:#f7f7f7;font-size:14px;">Oldest sample Age</th>';
            $summary_html .= '</tr>';
            $summary_html .= '</thead>';
            $summary_html .= '<tbody>';

            foreach ($summary_rows as $row) {
                $summary_html .= '<tr>';
                $summary_html .= '<td style="border:1px solid #d6d6d6;padding:12px 14px;font-size:14px;vertical-align:top;">' . esc_html($row['clinic']) . '</td>';
                $summary_html .= '<td style="border:1px solid #d6d6d6;padding:12px 14px;font-size:14px;vertical-align:top;">' . esc_html($row['not_received_samples']) . '</td>';
                $summary_html .= '<td style="border:1px solid #d6d6d6;padding:12px 14px;font-size:14px;vertical-align:top;">' . esc_html($row['oldest_sample_age']) . '</td>';
                $summary_html .= '</tr>';
            }

            $summary_html .= '</tbody>';
            $summary_html .= '</table>';
        }

        return '
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#222;">
                <p style="margin:0 0 16px 0;">Attached is a list of specimens pending pickup.</p>
                ' . $summary_html . '
                <p style="margin:0;">
                    This email was automatically generated from the "' . esc_html($this->report_label) . '" report in igniteLIS.
                    To unsubscribe or update recipients, please log into igniteLIS or contact your lab administrator.
                </p>
            </div>
        ';
    }

    public function get_frequency_options()
    {
        return array(
            'hourly' => 'Hourly',
            '30min' => 'Every 30 min',
            '15min' => 'Every 15 min',
        );
    }

    public function get_frequency_label($frequency)
    {
        $options = $this->get_frequency_options();
        return $options[$frequency] ?? ucfirst($frequency);
    }

    protected function get_wp_timezone()
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $tz_string = get_option('timezone_string');
        if (!empty($tz_string)) {
            return new DateTimeZone($tz_string);
        }

        $offset = (float) get_option('gmt_offset');
        $hours = (int) $offset;
        $minutes = (int) round(abs($offset - $hours) * 60);
        $sign = $offset < 0 ? '-' : '+';

        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
    }

    protected function get_interval_minutes($frequency)
    {
        switch ($frequency) {
            case '15min':
                return 15;
            case '30min':
                return 30;
            case 'hourly':
            default:
                return 60;
        }
    }

    protected function is_valid_interval($start_time, $end_time)
    {
        if (empty($start_time) || empty($end_time)) {
            return false;
        }

        return strtotime($start_time) < strtotime($end_time);
    }

    public function style()
    {
        if (!$this->enable) {
            return;
        }
?>
        <style>
            .dispatch-setup-btn {
                background-color: #fff3ef;
                border: 1px solid #f4c9b8;
                color: #d83d00;
            }

            .dispatch-setup-btn:hover {
                background-color: #ffe6db;
                border-color: #eeaf97;
                color: #b63300;
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
        <button onClick="show_dispatch_schedule();return false;" data-slot="button" class="dispatch-setup-btn cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium h-9 px-4 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-ring h-4 w-4" aria-hidden="true">
                <path d="M10.268 21a2 2 0 0 0 3.464 0"></path>
                <path d="M22 8c0-2.3-.8-4.3-2-6"></path>
                <path d="M3.262 15.326A5 5 0 0 0 5 19h14a5 5 0 0 0 1.738-3.674"></path>
                <path d="M4 2C2.8 3.7 2 5.7 2 8"></path>
                <path d="M18 8a6 6 0 0 0-12 0v7"></path>
                <path d="M10 2h4"></path>
            </svg>
            Set Up Dispatch
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
            function loadListDispatchSchedule() {
                jQuery("#list_dispatch_schedule_book").html("Loading...");

                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'dispatch_schedule',
                    sub_ajax: 'load_schedule_list'
                }, function(response) {
                    jQuery("#list_dispatch_schedule_book").html(response);

                    jQuery(".btn_dispatch_del").off("click").on("click", function() {
                        var id = jQuery(this).data("id");
                        var user_id = jQuery(this).data("user_id");

                        Swal.fire({
                            title: 'Dispatch / Alerts',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => Swal.showLoading()
                        });

                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'dispatch_schedule',
                            sub_ajax: 'delete_schedule',
                            schedule_id: id,
                            user_id: user_id
                        }, function(res) {
                            Swal.close();
                            if (res.success) {
                                showToast('success', 'Dispatch', res.data, 4000, 'top_center');
                                loadListDispatchSchedule();
                            } else {
                                showToast('error', 'Dispatch', res.data, 4000, 'top_center');
                            }
                        });
                    });

                    jQuery(".btn_dispatch_pause").off("click").on("click", function() {
                        var id = jQuery(this).data("id");
                        var user_id = jQuery(this).data("user_id");
                        var status = parseInt(jQuery(this).data("status"), 10) === 1 ? 0 : 1;

                        Swal.fire({
                            title: 'Dispatch / Alerts',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => Swal.showLoading()
                        });

                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'dispatch_schedule',
                            sub_ajax: 'pause_schedule',
                            schedule_id: id,
                            status: status,
                            user_id: user_id
                        }, function(res) {
                            Swal.close();
                            if (res.success) {
                                showToast('success', 'Dispatch', res.data, 4000, 'top_center');
                                loadListDispatchSchedule();
                            } else {
                                showToast('error', 'Dispatch', res.data, 4000, 'top_center');
                            }
                        });
                    });
                });
            }

            function show_dispatch_schedule() {
                Swal.fire({
                    title: 'Dispatch / Alerts',
                    text: 'Loading...Please wait...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'dispatch_schedule',
                    sub_ajax: 'load_schedule'
                }, function(response) {
                    Swal.close();

                    var htmlBox = jQuery(response);
                    jQuery("body").append(htmlBox);
                    initCustomSelect("#dispatch_frequency");
                    refreshCustomSelect("dispatch_frequency");

                    htmlBox.find("#btn_close_dispatch_box, #btn_dispatch_cancel").on("click", function() {
                        htmlBox.remove();
                    });

                    htmlBox.find("#btn_dispatch_save").on("click", function() {
                        var schedule_email = jQuery("#dispatch_email").val();
                        var sel_frequency = jQuery("#dispatch_frequency").val();
                        var start_time = jQuery("#dispatch_start_time").val();
                        var end_time = jQuery("#dispatch_end_time").val();

                        if (!schedule_email) {
                            showToast('error', 'Dispatch', 'Please enter an email address', 4000, 'top_center');
                            return;
                        }

                        if (!start_time || !end_time) {
                            showToast('error', 'Dispatch', 'Please select a valid time interval', 4000, 'top_center');
                            return;
                        }

                        if (start_time >= end_time) {
                            showToast('error', 'Dispatch', 'End time must be later than start time', 4000, 'top_center');
                            return;
                        }

                        Swal.fire({
                            title: 'Dispatch / Alerts',
                            text: 'Saving...Please wait...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => Swal.showLoading()
                        });

                        jQuery.post("admin.php?page=" + url_main_page, {
                            trajax: 'dispatch_schedule',
                            sub_ajax: 'setup_dispatch_schedule',
                            schedule_email: schedule_email,
                            sel_frequency: sel_frequency,
                            dispatch_start_time: start_time,
                            dispatch_end_time: end_time
                        }, function(res) {
                            Swal.close();

                            if (res.success) {
                                showToast('success', 'Dispatch', res.data, 4000, 'top_center');
                                loadListDispatchSchedule();
                            } else {
                                showToast('error', 'Dispatch', res.data, 4000, 'top_center');
                            }
                        });
                    });

                    loadListDispatchSchedule();
                });
            }
        </script>
    <?php
    }

    public function ajax()
    {
        if (!$this->enable) {
            return;
        }

        $trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
        $sub_ajax = isset($_REQUEST['sub_ajax']) ? $_REQUEST['sub_ajax'] : '';

        if ($trajax !== 'dispatch_schedule') {
            return;
        }

        if ($sub_ajax == 'load_schedule') {
            $this->ajax_load_schedule();
        }
        if ($sub_ajax == 'load_schedule_list') {
            $this->ajax_load_schedule_list();
        }
        if ($sub_ajax == 'setup_dispatch_schedule') {
            $this->ajax_setup_dispatch_schedule();
        }
        if ($sub_ajax == 'pause_schedule') {
            $this->ajax_pause_schedule();
        }
        if ($sub_ajax == 'delete_schedule') {
            $this->ajax_delete_schedule();
        }

        exit;
    }

    protected function get_current_user_schedule($user_id)
    {
        $schedules = get_option($this->option_key, []);
        if (!empty($schedules[$user_id])) {
            $schedule_id = array_key_first($schedules[$user_id]);
            return $schedules[$user_id][$schedule_id];
        }

        return null;
    }

    public function ajax_load_schedule()
    {
        $current_user = wp_get_current_user();
        $current_schedule = $this->get_current_user_schedule($current_user->ID);
        $email = !empty($current_schedule['email']) ? $current_schedule['email'] : ($current_user && $current_user->exists() ? $current_user->user_email : '');
        $frequency = !empty($current_schedule['frequency']) ? $current_schedule['frequency'] : 'hourly';
        $start_time = !empty($current_schedule['start_time']) ? $current_schedule['start_time'] : '06:00';
        $end_time = !empty($current_schedule['end_time']) ? $current_schedule['end_time'] : '18:00';
    ?>
        <style>
            #emp_dispatch_frequency,
            #dispatch_email,
            #dispatch_start_time,
            #dispatch_end_time {
                border: solid 1px #cccccc !important;
                border-radius: 8px !important;
                background-color: #f8f9fb !important;
            }

            #emp_dispatch_frequency .selected {
                min-height: 36px;
                display: flex;
                align-items: center;
                padding: 0 12px;
            }

            #emp_dispatch_frequency .options {
                z-index: 10001;
            }
        </style>
        <div data-state="open" data-slot="dialog-overlay" class="fixed inset-0 z-50 bg-black/50" style="pointer-events: auto;" data-aria-hidden="true" aria-hidden="true"></div>

        <div role="dialog" data-state="open" data-slot="dialog-content"
            class="bg-background fixed top-[50%] left-[50%] z-50 grid w-full translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg max-w-2xl max-h-[90vh] overflow-y-auto"
            tabindex="-1" style="pointer-events: auto;">
            <div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-left">
                <h2 style="font-size:1.125rem;font-weight: 600 !important;" data-slot="dialog-title" class="text-lg leading-none font-semibold">Dispatch / Alerts</h2>
                <p data-slot="dialog-description" class="text-muted-foreground text-sm">Schedule auto email delivery of not yet received samples.</p>
            </div>

            <div class="space-y-6 pb-4 -mt-2">
                <div class="space-y-4 p-4 bg-gray-50 rounded-lg">
                    <h3 style="font-size:1rem;font-weight: 500 !important;margin-top:0 !important;margin-bottom:13px !important;" class="font-medium">Set Up Dispatch</h3>

                    <div class="space-y-2">
                        <label class="text-sm leading-none font-medium text-gray-700 flex items-center gap-2" for="dispatch_email">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail h-4 w-4" aria-hidden="true">
                                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            </svg>
                            Email Address
                        </label>
                        <input id="dispatch_email" type="email"
                            class="flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background outline-none md:text-sm border-gray-300"
                            placeholder="dispatch@example.com"
                            value="<?php echo esc_attr($email); ?>">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm leading-none font-medium text-gray-700 flex items-center gap-2" for="dispatch_frequency">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-4 w-4" aria-hidden="true">
                                <path d="M12 6v6l4 2"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>
                            Frequency
                        </label>
                        <select id="dispatch_frequency">
                            <?php foreach ($this->get_frequency_options() as $value => $label) { ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($frequency, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm leading-none font-medium text-gray-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-timer h-4 w-4" aria-hidden="true">
                                <path d="M10 2h4"></path>
                                <path d="M12 14v-4"></path>
                                <path d="M12 18h.01"></path>
                                <circle cx="12" cy="14" r="8"></circle>
                            </svg>
                            Time Interval
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs text-gray-500 mb-1">Start</div>
                                <input id="dispatch_start_time" type="time" class="flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background outline-none md:text-sm border-gray-300" value="<?php echo esc_attr($start_time); ?>">
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 mb-1">End</div>
                                <input id="dispatch_end_time" type="time" class="flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background outline-none md:text-sm border-gray-300" value="<?php echo esc_attr($end_time); ?>">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mb-0">Example: 6:00 AM to 6:00 PM. Dispatch emails run every day during this interval.</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button id="btn_dispatch_cancel" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium border bg-background text-foreground h-9 px-4 py-2">Cancel</button>
                        <button id="btn_dispatch_save" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium text-primary-foreground h-9 px-4 py-2 bg-[#D83D00] hover:bg-[#D83D00]/90">Save Dispatch</button>
                    </div>
                </div>
            </div>

            <div class="space-y-3" id="list_dispatch_schedule_book"></div>

            <button id="btn_close_dispatch_box" type="button" class="cursor-pointer absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
                <span class="sr-only">Close</span>
            </button>
        </div>
    <?php
        exit;
    }

    protected function render_schedule_item($id, $item)
    {
        $start_time = date('h:i A', strtotime($item['start_time']));
        $end_time = date('h:i A', strtotime($item['end_time']));
    ?>
        <div class="flex schedule_item items-center justify-between p-3 border rounded-lg bg-white">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail h-4 w-4 text-gray-400" aria-hidden="true">
                        <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    </svg>
                    <span><?php echo esc_html($item['email']); ?></span>
                </div>

                <div class="text-sm text-gray-500 mt-1 ml-6">
                    <?php echo esc_html($this->get_frequency_label($item['frequency'])); ?> between <?php echo esc_html($start_time); ?> and <?php echo esc_html($end_time); ?> · CSV
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button data-user_id="<?php echo esc_attr($item['user_id']); ?>" data-id="<?php echo esc_attr($id); ?>" data-status="<?php echo esc_attr($item['is_pause']); ?>" data-slot="button" class="btn_dispatch_pause cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium h-8 rounded-md gap-1.5 px-3">
                    <?php echo $item['is_pause'] ? 'Resume' : 'Pause'; ?>
                </button>

                <button data-user_id="<?php echo esc_attr($item['user_id']); ?>" data-id="<?php echo esc_attr($id); ?>" data-slot="button" class="btn_dispatch_del cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium h-8 rounded-md gap-1.5 px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 h-4 w-4 text-red-600" aria-hidden="true">
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

    public function ajax_load_schedule_list()
    {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $schedules = get_option($this->option_key, []);

        echo '<h3 class="font-medium">Active Dispatch Schedules</h3>';

        if (!empty($schedules[$user_id])) {
            foreach ($schedules[$user_id] as $id => $item) {
                $this->render_schedule_item($id, $item);
            }
        }

        exit;
    }

    public function ci_calc_schedule_timestamp($data)
    {
        $timezone = $this->get_wp_timezone();
        $now = new DateTime('now', $timezone);
        $today = $now->format('Y-m-d');

        $start = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $data['start_time'], $timezone);
        $end = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $data['end_time'], $timezone);

        if (!$start || !$end || $end <= $start) {
            return false;
        }

        $interval_minutes = $this->get_interval_minutes($data['frequency']);

        if ($now < $start) {
            $next = clone $start;
        } elseif ($now > $end) {
            $next = clone $start;
            $next->modify('+1 day');
        } else {
            $elapsed_seconds = $now->getTimestamp() - $start->getTimestamp();
            $steps = (int) floor($elapsed_seconds / ($interval_minutes * MINUTE_IN_SECONDS)) + 1;
            $next = clone $start;
            $next->modify('+' . ($steps * $interval_minutes) . ' minutes');

            if ($next > $end) {
                $next = clone $start;
                $next->modify('+1 day');
            }
        }

        return $next->getTimestamp();
    }

    public function ajax_setup_dispatch_schedule()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $email = sanitize_email($_REQUEST['schedule_email'] ?? '');
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
        }

        $frequency = sanitize_text_field($_REQUEST['sel_frequency'] ?? 'hourly');
        $frequency_options = $this->get_frequency_options();
        if (!isset($frequency_options[$frequency])) {
            wp_send_json_error('Invalid frequency');
        }

        $start_time = sanitize_text_field($_REQUEST['dispatch_start_time'] ?? '');
        $end_time = sanitize_text_field($_REQUEST['dispatch_end_time'] ?? '');
        if (!$this->is_valid_interval($start_time, $end_time)) {
            wp_send_json_error('End time must be later than start time');
        }

        $schedule_id = 'dispatch_' . $user_id . '_' . sanitize_key($this->report_type);
        $schedules = get_option($this->option_key, []);

        $data = array(
            'schedule_id' => $schedule_id,
            'email' => $email,
            'frequency' => $frequency,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_csv' => 1,
            'is_pause' => 0,
            'updated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'type_report' => 'report_dispatch',
            'report_type' => $this->report_type,
            'report_label' => $this->report_label,
        );

        $schedules[$user_id] = array(
            $schedule_id => $data,
        );
        update_option($this->option_key, $schedules);

        wp_clear_scheduled_hook(
            'cron_all_api',
            array('ci_cron_report_dispatch', '', array($user_id, $schedule_id))
        );

        $timestamp = $this->ci_calc_schedule_timestamp($data);
        if (!$timestamp) {
            wp_send_json_error('Invalid dispatch interval');
        }

        wp_schedule_event(
            $timestamp,
            $data['frequency'],
            'cron_all_api',
            array('ci_cron_report_dispatch', '', array($user_id, $schedule_id))
        );

        wp_send_json_success('Dispatch schedule saved successfully');
    }

    public function ajax_pause_schedule()
    {
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error('Not logged in');
        }

        $sid = sanitize_text_field($_POST['schedule_id'] ?? '');
        $status = (int) ($_POST['status'] ?? 0);
        $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $current_user_id;
        $schedules = get_option($this->option_key, []);

        if (
            $target_user_id !== $current_user_id &&
            !current_user_can('administrator') &&
            !current_user_can('admintision')
        ) {
            wp_send_json_error('Permission denied');
        }

        if (isset($schedules[$target_user_id][$sid])) {
            $schedules[$target_user_id][$sid]['is_pause'] = $status;
            update_option($this->option_key, $schedules);
            wp_send_json_success('Dispatch schedule updated');
        }

        wp_send_json_error('Dispatch schedule not found');
    }

    public function ajax_delete_schedule()
    {
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error('Not logged in');
        }

        $sid = sanitize_text_field($_POST['schedule_id'] ?? '');
        $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $current_user_id;
        $schedules = get_option($this->option_key, []);

        if (
            $target_user_id !== $current_user_id &&
            !current_user_can('administrator') &&
            !current_user_can('admintision')
        ) {
            wp_send_json_error('Permission denied');
        }

        if (isset($schedules[$target_user_id][$sid])) {
            wp_clear_scheduled_hook('cron_all_api', array('ci_cron_report_dispatch', '', array($target_user_id, $sid)));
            unset($schedules[$target_user_id][$sid]);
            if (empty($schedules[$target_user_id])) {
                unset($schedules[$target_user_id]);
            }
            update_option($this->option_key, $schedules);
            wp_send_json_success('Dispatch schedule deleted');
        }

        wp_send_json_error('Dispatch schedule not found');
    }
}
