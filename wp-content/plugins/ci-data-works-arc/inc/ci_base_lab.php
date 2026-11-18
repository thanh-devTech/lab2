<?php
class CI_Base_Lab
{
    public static $has_user_labs = false;
    public static $base_roles = [
        'lab_tech',
        'ref_lab_tech',
        'reflab_manager',
        'lab',
        'labb',
        'lab_director',
        'lab_manager',
        'lab_rep',
        'lab_rep_w2',
        'labmanager',
        'doctor',
        'Phlebotomist',
        'collector',
        'office_manager',
        'multi_site_manager',
        'labsecman',
        'leadlabsecman',
        'lab_sec',
        'ResultsUpload',
        'RefManager',
        'sales_accounting',
        'scientist',
        'shipping',
        'Billing',
        'distributor',
        'results',
        'pcr_verify',
        'BloodLabManager',
        'administrator_p',
        'wp_admin',
        'user_management',
        'AdminCIDW',
        'IT',
        'Lab_Reporting',
        'ci_dev',
        'ci_support',
    ];

    public static function set_user_lab($user_id, $lab_id)
    {
        update_user_meta($user_id, '_lab_id', $lab_id);
        //add labs to user list
        $lab_ids = get_user_meta($user_id, '_lab_ids', true);
        if (!is_array($lab_ids)) {
            $lab_ids = !empty($lab_ids) ? explode(',', $lab_ids) : array();
        }
        if (!in_array($lab_id, $lab_ids)) {
            $lab_ids[] = $lab_id;
            $lab_ids_str = implode(',', $lab_ids);
            update_user_meta($user_id, '_lab_ids', $lab_ids_str);
        }
    }
    public static function get_user_lab_id($user_id = 0)
    {
        global $wpdb;
        $user = get_userdata($user_id);

        $user_role = ($user && isset($user->roles[0])) ? $user->roles[0] : '';
        if ($user && empty($user_role) && isset($user->caps)) {
            $user_role = array_keys($user->caps);
            $user_role = @$user_role[0];
        }
        $lab_id     = get_user_meta($user_id, '_lab_id', true);
        if (empty($lab_id)) {
            switch ($user_role) {
                case 'lab_rep':
                case 'lab_rep_w2':
                    $lab_id = $wpdb->get_var("select rep.lab_ID from ci_lab_reps rep
                                        join {$wpdb->usermeta} um ON um.meta_key='_rep_id' and um.meta_value=rep.rep_ID
                                        where um.user_id = {$user_id}");
                    break;
                case 'doctor':
                    $lab_id = $wpdb->get_var("select cli.`cli_lab-ID` from ci_clinic cli
                                    JOIN ci_clinic_doc cd ON cd.cli_ID = cli.cID
                                    JOIN ci_doc doc ON cd.doc_ID = doc.doc_ID
                                    join {$wpdb->usermeta} um ON um.meta_key='_doc_id' and um.meta_value=doc.doc_ID
                                    where um.user_id = {$user_id}");
                    break;

                case 'clinic':
                    $lab_id = $wpdb->get_var("select cli.`cli_lab-ID` from ci_clinic cli
                                    join {$wpdb->usermeta} um ON um.meta_key='_clinic_id' and um.meta_value=cli.cID
                                    where um.user_id = {$user_id}");
                    break;
                case 'collector':
                    $lab_id = $wpdb->get_var("select rep.lab_ID from ci_lab_reps rep
                                        JOIN ci_collector col ON col.rep_ID = rep.rep_ID
                                        join {$wpdb->usermeta} um ON um.meta_key='_collector_id' and um.meta_value=col.col_ID
                                        where um.user_id = {$user_id}");
                    break;
            }
        }
        if (empty($lab_id)) {
            $lab_id = self::get_current_lab_id();
        }

        return $lab_id;
    }

    public static function getCurrentLabName()
    {
        $labs = self::get_user_labs();

        return $labs[0]['lab_name'];
    }

    public static function has_user_labs()
    {
        self::get_user_lab_ids();

        return self::$has_user_labs;
    }

    public static function get_user_lab_ids_str()
    {
        return implode(',', self::get_user_lab_ids());
    }

    public static function get_user_role($user_id = 0)
    {
        if ($user_id > 0) {
            $user = get_userdata($user_id);
        } else {
            $user = wp_get_current_user();
        }

        $user_role = ($user && isset($user->roles[0])) ? $user->roles[0] : '';
        if ($user && empty($user_role) && isset($user->caps)) {
            $user_role = array_keys($user->caps);
            $user_role = @$user_role[0];
        }
        return $user_role;
    }

    public static function get_user_lab_ids()
    {
        global $wpdb, $user_lab_ids, $current_lab_id;

        if (!is_user_logged_in()) {
            return array();
        }

        if (!is_array($user_lab_ids) || empty($user_lab_ids)) {

            $user_lab_ids = get_user_meta(get_current_user_id(), '_lab_ids', true);
            if (!is_array($user_lab_ids)) {
                $user_lab_ids = !empty($user_lab_ids) ? explode(',', $user_lab_ids) : array();
            }

            if (!empty($user_lab_ids)) {
                $user_lab_ids_str = implode(',', $user_lab_ids);
                $user_lab_ids = $wpdb->get_col("select lab_ID from ci_labs 
                            where lab_status=1
                            and lab_ID in ({$user_lab_ids_str})
                            order by lab_ID asc");
                if (empty($user_lab_ids)) {
                    delete_user_meta(get_current_user_id(), '_lab_ids');
                }
            } else {

                if (tr_current_role_in(array('lab', 'labb', 'lab_tech', 'lab_manager'))) {
                    $user_lab_id = get_user_meta(get_current_user_id(), '_lab_id', true);
                    if ($user_lab_id > 0) {
                        $user_lab_ids[] = $user_lab_id;
                    }
                }
            }

            if (empty($user_lab_ids)) {
                $user_lab_ids = $wpdb->get_col("select lab_ID from ci_labs 
                            where lab_status=1
                            and (lab_is_ref = 0 or lab_is_ref = 2)
                            order by lab_ID asc");
            } else {
                self::$has_user_labs = true;
            }
        }

        $user_lab_id = get_user_meta(get_current_user_id(), '_lab_id', true);
        if ($user_lab_id == 0) {
            $user_lab_id = self::get_lab_id_by_blog_id();
        }

        if (count($user_lab_ids) > 0 && !in_array($user_lab_id, $user_lab_ids)) {
            $user_lab_id = $user_lab_ids[0];
        }

        $current_lab_id = $user_lab_id;



        return $user_lab_ids;
    }

    public static function get_user_labs()
    {
        global $wpdb;
        $lab_ids = self::get_user_lab_ids();
        $lab_id_str = implode(',', $lab_ids);
        if (empty($lab_id_str)) {
            $lab_id_str = self::get_current_lab_id();
        }
        return $wpdb->get_results("select * from ci_labs where lab_ID in ({$lab_id_str})", ARRAY_A);
    }



    public static function get_lab_id_by_blog_id()
    {
        global $wpdb, $blog_id;

        $lab_id = $wpdb->get_var("select lab_ID from ci_labs where lab_site={$blog_id} order by lab_ID asc limit 1");
        if ($lab_id == 0) {
            $lab_id = $wpdb->get_var("select lab_ID from ci_labs order by lab_ID asc limit 1");
        }
        return $lab_id;
    }

    public static function get_current_lab_id()
    {
        global $current_lab_id;

        //check lab from user first
        self::get_user_lab_ids();

        //get lab by blogid
        if (empty($current_lab_id)) {
            $current_lab_id = self::get_lab_id_by_blog_id();
        }

        return $current_lab_id;
    }

    public static function get_current_lab()
    {
        global $wpdb, $current_lab;

        if (empty($current_lab)) {
            $lab_id = self::get_current_lab_id();
            $current_lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
        }

        return $current_lab;
    }

    public static function get_current_lab_name()
    {
        global $wpdb, $current_lab;

        if (empty($current_lab)) {
            $lab_id = self::get_current_lab_id();
            $current_lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
        }

        return $current_lab['lab_name'];
    }
    public static function get_current_lab_init()
    {
        global $wpdb, $current_lab;

        if (empty($current_lab)) {
            $lab_id = self::get_current_lab_id();
            $current_lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
        }

        return $current_lab['lab_dba_init'];
    }

    public static function replace_assen_by_lab($assen, $oldbat, $newdata, $btype = 'tox')
    {
        global $wpdb;

        if ($newdata['lab_id'] > 0 && (($oldbat && $newdata['lab_id'] != $oldbat->lab_id) || (!$oldbat && $newdata['lab_id'] != self::get_current_lab_id()))) {
            $labinit = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$newdata['lab_id']}");
            if ($labinit) {
                $assen = $labinit . substr($assen, 3);
            }
        }

        return $assen;
    }

    public static function replace_assen_by_reflab($assen, $oldbat, &$newdata, $btype)
    {
        global $wpdb;

        $old_assen = $assen;

        $ref_lab_id = isset($newdata['ref_lab_ID']) ? $newdata['ref_lab_ID'] : ($oldbat ? $oldbat->ref_lab_ID : 0);

        if ($ref_lab_id > 0) {
            $reflabdb = $wpdb->get_row("select * from ci_labs where lab_ID = {$ref_lab_id}");
            if ($reflabdb) {
                if ($reflabdb->lab_ref_assen_init && !empty($reflabdb->lab_dba_init)) {
                    $subassen = preg_replace("/^[a-zA-Z]{3}/", '', $assen);
                    if (empty($subassen)) {
                        $subassen = substr($assen, 3);
                    }
                    $new_assen = $reflabdb->lab_dba_init . $subassen;
                    if ($new_assen != $assen) {
                        $assen = $new_assen;

                        Tr_Session_Class::add("Changed Acession by Reflab: from {$old_assen} to {$new_assen}");

                        //check & fix reports
                        $table_report   = tr_pdd_get_table_report_by_btype($btype);

                        $report_oldid = $wpdb->get_var("select id from {$table_report} where patient_id='{$old_assen}' limit 1");
                        $report_newid = $wpdb->get_var("select id from {$table_report} where patient_id='{$new_assen}' limit 1");
                        if ($report_oldid > 0 && $report_newid == 0) {
                            $wpdb->update($table_report, array('patient_id' => $new_assen), array('id' => $report_oldid));
                        }
                        //add oldreq
                        if ($oldbat && empty($oldbat->oldreq) && (!isset($newdata['oldreq']) || empty($newdata['oldreq']))) {
                            $newdata['oldreq'] = $old_assen;
                        }
                    }
                }
            }
        }

        return $assen;
    }



    public static function get_labs()
    {
        global $wpdb;
        return $wpdb->get_results("select * 
        from ci_labs 
        where lab_status=1
        and (lab_is_ref = 0 or lab_is_ref = 2)
        order by lab_dba_init asc");
    }

    public static function get_all_labs()
    {
        global $wpdb;
        return $wpdb->get_results("select * 
        from ci_labs 
        where lab_status=1
        order by lab_dba_init asc");
    }


    public static function get_ref_labs($for_select = false)
    {
        global $wpdb;
        $rs = $wpdb->get_results("select * 
        from ci_labs 
        where lab_status=1
        and (lab_is_ref = 1 or lab_is_ref = 2)
        order by lab_dba_init asc");
        if ($for_select) {
            $labs = array();
            foreach ($rs as $r) {
                $labs[$r->lab_ID] = $r->lab_name;
            }
            return $labs;
        }

        return $rs;
    }

    public static function getLabIDByKey($key)
    {
        global $wpdb;
        return $wpdb->get_var("select lab_ID from ci_labs where lab_dba_init = '{$key}' ");
    }

    public static function getLabByID($id)
    {
        global $wpdb;
        static $all_labs;
        if (empty($all_labs)) {
            $rs = $wpdb->get_results("select * from ci_labs");
            foreach ($rs as $r) {
                $all_labs[$r->lab_ID] = $r;
            }
        }
        return isset($all_labs[$id]) ? $all_labs[$id] : null;
    }
    public static function get_lab_operations()
    {
        global $wpdb;
        $days_of_operation_lab = [];
        $lab_operations = $wpdb->get_results("SELECT * FROM ci_lab_operation ORDER BY from_date ASC");
        if ($lab_operations) {
            foreach ($lab_operations as $lo) {
                if (!isset($days_of_operation_lab[$lo->lab_ID])) {
                    $days_of_operation_lab[$lo->lab_ID] = [];
                }
                $days_of_operation_lab[$lo->lab_ID][] = $lo;
            }
        }
        return $days_of_operation_lab;
    }
    public static function get_lab_operation_by_date($lab_id, $date, $days_of_operation_lab)
    {
        $items = $days_of_operation_lab[$lab_id] ?? [];
        if (empty($items) || !is_array($items)) {
            return null;
        }

        $date_ymd = date_i18n('Y-m-d', strtotime($date));
        $matched = null;
        foreach ($items as $item) {
            $from = !empty($item->from_date) ? $item->from_date : null;
            $to   = !empty($item->to_date) && $item->to_date !== '0000-00-00' ? $item->to_date : null;

            if ($from && $to && $date_ymd >= $from && $date_ymd <= $to) {
                $matched = $item;
            }

            if ($from && !$to && $date_ymd >= $from) {
                $matched = $item;
            }
        }

        return $matched;
    }
    public static function get_target_tat_hours($lab_id, $date, $days_of_operation_lab)
    {
        $operation = self::get_lab_operation_by_date($lab_id, $date, $days_of_operation_lab);
        if ($operation && !empty($operation->target_tat)) {
            return intval($operation->target_tat);
        }

        return 36;
    }
    public static function calculate_business_hours($received, $completed, $lab_id, $days_of_operation_lab)
    {
        if (empty($received) || empty($completed)) return 0;
        $received_ts  = strtotime($received);
        $completed_ts = strtotime($completed);

        if (!$received_ts || !$completed_ts || $completed_ts <= $received_ts) {
            return 0;
        }

        if (empty($days_of_operation_lab[$lab_id]) || !is_array($days_of_operation_lab[$lab_id])) {
            return ($completed_ts - $received_ts) / 3600;
        }

        $total_hours = 0;
        $received_ymd  = date_i18n('Y-m-d', $received_ts);
        $completed_ymd = date_i18n('Y-m-d', $completed_ts);

        $start_date_ts = strtotime(date_i18n('Y-m-d', $received_ts));
        $end_date_ts   = strtotime(date_i18n('Y-m-d', $completed_ts));
        $current_date = $start_date_ts;

        while ($current_date <= $end_date_ts) {

            $current_ymd = date_i18n('Y-m-d', $current_date);
            $day_start_ts = strtotime($current_ymd . ' 00:00:00');
            $day_end_ts   = $day_start_ts + 86400;

            $items = $days_of_operation_lab[$lab_id] ?? [];
            $operation_item = self::get_lab_operation_by_date($lab_id, $current_ymd, $days_of_operation_lab);

            if ($operation_item) {
                $day_of_week = date_i18n('N', $current_date);

                $day_map = [
                    1 => 'lab_mon',
                    2 => 'lab_tue',
                    3 => 'lab_wed',
                    4 => 'lab_thu',
                    5 => 'lab_fri',
                    6 => 'lab_sat',
                    7 => 'lab_sun',
                ];

                $field = $day_map[$day_of_week] ?? null;
                if ($field && intval($operation_item->$field) === 1) {

                    // Cùng ngày
                    if (
                        $received_ymd === $current_ymd &&
                        $completed_ymd === $current_ymd
                    ) {
                        $total_hours += ($completed_ts - $received_ts) / 3600;
                    } elseif ($received_ymd === $current_ymd) {
                        $total_hours += ($day_end_ts - $received_ts) / 3600;
                    } elseif ($completed_ymd === $current_ymd) {
                        $total_hours += ($completed_ts - $day_start_ts) / 3600;
                    } else {
                        $total_hours += 24;
                    }
                }
            } else {
                $first_from_ts = !empty($items[0]->from_date)
                    ? strtotime($items[0]->from_date . ' 00:00:00')
                    : null;

                if ($first_from_ts && $current_date < $first_from_ts) {
                    if ($received_ymd === $current_ymd && $completed_ymd === $current_ymd) {
                        $total_hours += ($completed_ts - $received_ts) / 3600;
                    } elseif ($received_ymd === $current_ymd) {
                        $total_hours += ($day_end_ts - $received_ts) / 3600;
                    } elseif ($completed_ymd === $current_ymd) {
                        $total_hours += ($completed_ts - $day_start_ts) / 3600;
                    } else {
                        $total_hours += 24;
                    }
                }
            }
            $current_date = strtotime('+1 day', $current_date);
        }

        return $total_hours;
    }

    public static function save_lab_field_history($lab_id, $old_lab, $new_lab, $effective_date = '', $created_by = 0)
    {
        global $wpdb;

        $lab_id = intval($lab_id);
        if ($lab_id <= 0) {
            return;
        }

        $is_full_reset = false;
        $effective_date_raw = trim((string) $effective_date);
        error_log($effective_date);

        if ($effective_date_raw !== '') {
            $sentinel_values = array('0000-00-00', '0001-01-01', '01/01/0001', '01/01/0000', '0000/00/00');
            if (in_array($effective_date_raw, $sentinel_values, true)) {
                $is_full_reset = true;
            } else {
                $ts = strtotime($effective_date_raw);
                if ($ts !== false && (int) date('Y', $ts) <= 1) {
                    $is_full_reset = true;
                }
            }
        }

        if (!$is_full_reset) {
            if (empty($effective_date_raw) || !strtotime($effective_date_raw)) {
                $effective_date = date_i18n('Y-m-d');
            } else {
                $effective_date = date('Y-m-d', strtotime($effective_date_raw));
            }
        }

        $field_groups = array(
            array('lab_address', 'lab_city', 'lab_state', 'lab_zip'),
            array('lab_phone'),
            array('lab_clia'),
            array('lab_cola'),
            array('lab_cap'),
            array('lab_director'),
            array('lab_director_signature'),
            array('lab_additional'),
            array('lab_results_footer'),
        );

        $created = date_i18n('Y-m-d H:i:s');

        foreach ($field_groups as $fields) {
            $is_changed = false;

            foreach ($fields as $field_key) {
                if ($field_key === 'lab_results_footer' && !array_key_exists('lab_results_footer', $new_lab)) {
                    continue;
                }

                $old_value = trim((string) (isset($old_lab[$field_key]) ? $old_lab[$field_key] : ''));
                $new_value = trim((string) (isset($new_lab[$field_key]) ? $new_lab[$field_key] : ''));

                if ($old_value !== $new_value) {
                    $is_changed = true;
                    break;
                }
            }

            if (!$is_changed) {
                continue;
            }

            foreach ($fields as $field_key) {
                if ($field_key === 'lab_results_footer' && !array_key_exists('lab_results_footer', $new_lab)) {
                    continue;
                }

                $new_value = isset($new_lab[$field_key]) ? $new_lab[$field_key] : '';

                if ($is_full_reset) {
                    $wpdb->delete(
                        'ci_lab_field_history',
                        array('lab_ID' => $lab_id, 'field_key' => $field_key),
                        array('%d', '%s')
                    );

                    $wpdb->insert(
                        'ci_lab_field_history',
                        array(
                            'lab_ID' => $lab_id,
                            'field_key' => $field_key,
                            'field_value' => $new_value,
                            'from_date' => null,
                            'created' => $created,
                            'created_by' => $created_by,
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%d')
                    );

                    continue;
                }

                $old_value = isset($old_lab[$field_key]) ? $old_lab[$field_key] : '';

                $history_count = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM ci_lab_field_history WHERE lab_ID = %d AND field_key = %s",
                    $lab_id,
                    $field_key
                )));

                if ($history_count === 0) {
                    $wpdb->insert(
                        'ci_lab_field_history',
                        array(
                            'lab_ID' => $lab_id,
                            'field_key' => $field_key,
                            'field_value' => $old_value,
                            'from_date' => null,
                            'created' => $created,
                            'created_by' => $created_by,
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%d')
                    );
                }

                $history_id = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM ci_lab_field_history
                    WHERE lab_ID = %d
                    AND field_key = %s
                    AND from_date = %s
                    ORDER BY ID DESC
                    LIMIT 1",
                    $lab_id,
                    $field_key,
                    $effective_date
                )));

                if ($history_id > 0) {
                    $wpdb->update(
                        'ci_lab_field_history',
                        array(
                            'field_value' => $new_value,
                            'created' => $created,
                            'created_by' => $created_by,
                        ),
                        array('ID' => $history_id),
                        array('%s', '%s', '%d'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        'ci_lab_field_history',
                        array(
                            'lab_ID' => $lab_id,
                            'field_key' => $field_key,
                            'field_value' => $new_value,
                            'from_date' => $effective_date,
                            'created' => $created,
                            'created_by' => $created_by,
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%d')
                    );
                }
            }
        }
    }

    public static function apply_lab_field_history($report)
    {
        global $wpdb;

        if (empty($report) || empty($report['lab_ID'])) {
            return $report;
        }

        $effective_date = !empty($report['created_date']) ? $report['created_date'] : '';

        if (
            empty($effective_date)
            || $effective_date == '0000-00-00'
            || $effective_date == '0000-00-00 00:00:00'
            || (!empty($report['doc']) && strtotime($effective_date) < strtotime($report['doc']))
        ) {
            $effective_date = !empty($report['doc']) ? $report['doc'] : $effective_date;
        }

        if (!empty($report['approve_date']) && $report['approve_date'] !== '0000-00-00' && $report['approve_date'] !== '0000-00-00 00:00:00') {
            $effective_date = $report['approve_date'];
        }

        if (empty($effective_date) || !strtotime($effective_date)) {
            $effective_date = date_i18n('Y-m-d');
        }

        $effective_date = date('Y-m-d', strtotime($effective_date));

        $field_sources = array(
            array('report_key' => 'lab_address', 'field_key' => 'lab_address', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_city', 'field_key' => 'lab_city', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_state', 'field_key' => 'lab_state', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_zip', 'field_key' => 'lab_zip', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_phone', 'field_key' => 'lab_phone', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_clia', 'field_key' => 'lab_clia', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_cola', 'field_key' => 'lab_cola', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_cap', 'field_key' => 'lab_cap', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_director', 'field_key' => 'lab_director', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_director_signature', 'field_key' => 'lab_director_signature', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_additional', 'field_key' => 'lab_additional', 'lab_id' => intval($report['lab_ID'])),
            array('report_key' => 'lab_results_footer', 'field_key' => 'lab_results_footer', 'lab_id' => intval($report['lab_ID'])),
        );

        if (!empty($report['ref_lab_ID'])) {
            $field_sources[] = array('report_key' => 'rl_lab_additional', 'field_key' => 'lab_additional', 'lab_id' => intval($report['ref_lab_ID']));
        }

        if (!empty($report['lab_ref_only_address']) && !empty($report['ref_lab_ID'])) {
            foreach ($field_sources as $source_index => $field_source) {
                if (in_array($field_source['field_key'], array('lab_address', 'lab_city', 'lab_state', 'lab_zip', 'lab_phone', 'lab_clia', 'lab_director', 'lab_director_signature'))) {
                    $field_sources[$source_index]['lab_id'] = intval($report['ref_lab_ID']);
                }
            }
        }

        foreach ($field_sources as $field_source) {
            if ($field_source['lab_id'] <= 0) {
                continue;
            }

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT field_value, from_date
                FROM ci_lab_field_history
                WHERE lab_ID = %d
                AND field_key = %s
                ORDER BY from_date ASC, ID ASC",
                $field_source['lab_id'],
                $field_source['field_key']
            ), ARRAY_A);

            if (empty($rows)) {
                continue;
            }

            $applied_value = null;

            foreach ($rows as $row) {
                if (empty($row['from_date']) || $row['from_date'] == '0000-00-00') {
                    $applied_value = $row['field_value'];
                    continue;
                }

                if ($row['from_date'] <= $effective_date) {
                    $applied_value = $row['field_value'];
                    continue;
                }

                break;
            }

            if ($applied_value !== null) {
                $report[$field_source['report_key']] = $applied_value;
            }
        }

        return $report;
    }
}
