<?php

global $wpdb, $is_ci_admin;

if (!isset($tes_type)) {
    $tes_type   = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? $_REQUEST['type'] : $tab;
}

if (isset($_POST['trajax'])) {
    $action = $_POST['trajax'];

    // Export Cutoffs CSV (Export Cutoffs button)
    if ($action === 'export_cutoffs') {
        $group_id       = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $today          = date_i18n('Y-m-d');
        $result_settings = get_option('ci_result_settings_' . $tes_type, array());

        // Build header theo type
        $header = array('Panel', 'Test Name');
        if ($tes_type == 'blood') {
            $header[] = 'Critical Low';
            $header[] = 'Low';
            $header[] = 'Medium';
            $header[] = 'High';
            $header[] = 'Critical High';
            $header[] = 'Instrument';
            $header[] = 'Gender';
            $header[] = 'Age';
        } else if ($tes_type == 'pcr') {
            if (!empty($result_settings['enable_verylh'])) {
                $header[] = 'Very Low';
            } else {
                $header[] = 'Repeat';
            }
            $header[] = 'Low';
            $header[] = 'Medium';
            $header[] = 'High';
            if (!empty($result_settings['enable_verylh'])) {
                $header[] = 'Very High';
            }
            $header[] = 'Instrument';
        } else {
            $header[] = 'Cutoff';
            $header[] = 'Upper Limit';
            $header[] = 'Low';
            $header[] = 'High';
            $header[] = 'Instrument';
        }
        $header[] = 'Start Date';
        $header[] = 'End Date';

        // Build WHERE cho group filter
        $where_group = '';
        if ($group_id > 0) {
            $where_group = "AND (g.tg_ID = {$group_id} OR g.tg_parent = {$group_id})";
        }

        $tests = $wpdb->get_results("
            SELECT te.*, g.tg_name, tgp.tg_name AS group_parent
            FROM ci_test_panels te
            JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
            LEFT JOIN ci_test_groups tgp ON tgp.tg_ID = g.tg_parent
            WHERE te.te_type = '{$tes_type}'
            AND te.te_in_req = 1
            AND (te.te_end = '' OR te.te_end IS NULL OR te.te_end < '{$today}')
            AND (g.tg_show_req = 1 OR tgp.tg_show_req = 1)
            {$where_group}
            ORDER BY group_parent ASC, g.tg_name ASC, te.te_name ASC
        ", ARRAY_A);

        // Build CSV rows
        $csvLines = array();
        $csvLines[] = implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $header));

        foreach ($tests as $t) {
            $cuts = $wpdb->get_results(
                "SELECT * FROM ci_test_panels_cutoffs WHERE cu_te_ID = {$t['te_ID']}",
                ARRAY_A
            );

            $panel_name = !empty($t['group_parent']) ? $t['group_parent'] : $t['tg_name'];
            if (empty($cuts)) {
                $row = array($panel_name, $t['te_name']);

                if ($tes_type == 'blood') {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '*'; // Instrument
                    $row[] = '';
                    $row[] = '';
                } else if ($tes_type == 'pcr') {
                    if (!empty($result_settings['enable_verylh'])) {
                        $row[] = '';
                    } else {
                        $row[] = '';
                    }
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    if (!empty($result_settings['enable_verylh'])) {
                        $row[] = '';
                    }
                    $row[] = '*'; // Instrument
                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    $row[] = '*'; // Instrument
                }
                $row[] = '';
                $row[] = '';

                $csvLines[] = implode(',', array_map(function ($v) {
                    return '"' . str_replace('"', '""', (string)$v) . '"';
                }, $row));
            } else {
                foreach ($cuts as $cut) {
                    $row = array($panel_name, $t['te_name']);

                    if ($tes_type == 'blood') {
                        $row[] = $cut['cu_critical_low'];
                        $row[] = $cut['cu_cutoff'];
                        $row[] = $cut['cu_medium'];
                        $row[] = $cut['cu_upper_limit'];
                        $row[] = $cut['cu_critical_high'];
                        $row[] = $cut['cu_ms'];
                        $row[] = $cut['cu_gender'];
                        $row[] = $cut['cu_age'];
                    } else if ($tes_type == 'pcr') {
                        if (!empty($result_settings['enable_verylh'])) {
                            $row[] = $cut['cu_critical_low'];
                        } else {
                            $row[] = $cut['cu_critical_low'];  // Repeat
                        }
                        $row[] = $cut['cu_cutoff'];
                        $row[] = $cut['cu_medium'];
                        $row[] = $cut['cu_upper_limit']; // High
                        if (!empty($result_settings['enable_verylh'])) {
                            $row[] = $cut['cu_critical_high']; // Very High
                        }
                        $row[] = $cut['cu_ms'];
                    } else {
                        $row[] = $cut['cu_cutoff'];
                        $row[] = $cut['cu_upper_limit'];
                        $row[] = $cut['cu_lqc'];
                        $row[] = $cut['cu_hqc'];
                        $row[] = $cut['cu_ms'];
                    }

                    $row[] = $cut['cu_start'];
                    $row[] = $cut['cu_end'];

                    $csvLines[] = implode(',', array_map(function ($v) {
                        return '"' . str_replace('"', '""', (string)$v) . '"';
                    }, $row));
                }
            }
        }

        $csv = implode("\n", $csvLines);

        $lab_init = CI_Base_Lab::get_current_lab_init();
        $suffix   = $group_id > 0
            ? sanitize_file_name($wpdb->get_var("SELECT tg_name FROM ci_test_groups WHERE tg_ID = {$group_id}"))
            : $tes_type;
        $filename = $lab_init . '_cutoffs_' . $suffix . '_' . $today . '.csv';

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'csv'      => base64_encode($csv),
                'filename' => $filename,
            )
        ));
        exit;
    }

    // Export Group Data CSV (Export Group Data button)
    if ($action === 'export_group_data') {
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        $where_group = $group_id > 0
            ? "AND (g.tg_ID = {$group_id} OR g.tg_parent = {$group_id})"
            : '';

        $groups = $wpdb->get_results("
            SELECT * FROM ci_test_groups
            WHERE tg_type = '{$tes_type}'
              AND tg_show_req = 1
              AND tg_parent = 0
            ORDER BY tg_position ASC
        ", ARRAY_A);

        $tests = $wpdb->get_results("
            SELECT te.*,
                   g.tg_name,
                   (SELECT tg_name FROM ci_test_groups gp WHERE gp.tg_ID = g.tg_parent) AS group_parent
            FROM ci_test_panels te
            JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
            WHERE te.te_type = '{$tes_type}'
              AND te.te_in_req = 1
              AND te.te_hide_pdf = 0
              AND (
                  g.tg_show_req = 1
                  OR g.tg_parent IN (SELECT tg_ID FROM ci_test_groups WHERE tg_show_req = 1)
              )
              {$where_group}
            ORDER BY group_parent ASC, g.tg_name ASC
        ", ARRAY_A);

        $testbygroups = [];
        foreach ($tests as $test) {
            $test['main_group'] = $test['tg_name'];
            $test['subgroup']   = '';
            if (!empty($test['group_parent'])) {
                $test['subgroup']   = $test['tg_name'];
                $test['main_group'] = $test['group_parent'];
            }

            $testarr = [
                $test['te_name'],
                $test['te_loinc_code'],
                '',
                $test['te_abrev'],
                $test['main_group'],
                $test['subgroup'],
            ];

            if ($tes_type == 'blood') {
                $testarr[] = $test['te_units'];
            }
            $testbygroups[$test['te_group_id']][] = $testarr;
        }

        $csvLines = [];
        $headers = ['Test Name', 'LOINC', 'Order Code', 'Abbreviation', 'Group', 'Sub Group'];
        if ($tes_type == 'blood') {
            $headers[] =  'Units';
        }
        $csvLines[] = implode(',', $headers);


        foreach ($groups as $group) {
            // Skip groups not matching filter
            if ($group_id > 0 && $group['tg_ID'] != $group_id) {
                continue;
            }

            $child_groups = $wpdb->get_results("
                SELECT * FROM ci_test_groups
                WHERE tg_type = '{$tes_type}'
                  AND tg_parent = {$group['tg_ID']}
                ORDER BY tg_position ASC
            ", ARRAY_A);

            $linearr = array_map(function ($v) {
                return '"' . str_replace('"', '""', (string)$v) . '"';
            }, [$group['tg_name'], $group['tg_short_name'], $group['tg_cpt_code']]);


            $csvLines[] = implode(',', $linearr);

            if (!empty($testbygroups[$group['tg_ID']])) {
                foreach ($testbygroups[$group['tg_ID']] as $row) {
                    $csvLines[] = implode(',', array_map(function ($v) {
                        return '"' . str_replace('"', '""', (string)$v) . '"';
                    }, $row));
                }
            }

            foreach ($child_groups as $childgroup) {
                $csvLines[] = implode(',', array_map(function ($v) {
                    return '"' . str_replace('"', '""', (string)$v) . '"';
                }, [$childgroup['tg_name'], $childgroup['tg_short_name']]));

                if (!empty($testbygroups[$childgroup['tg_ID']])) {
                    foreach ($testbygroups[$childgroup['tg_ID']] as $row) {
                        $csvLines[] = implode(',', array_map(function ($v) {
                            return '"' . str_replace('"', '""', (string)$v) . '"';
                        }, $row));
                    }
                }
            }
        }

        $csv = implode("\n", $csvLines);

        $lab_id  = CI_Base_Lab::get_current_lab_id();
        $labinit = $wpdb->get_var("SELECT lab_dba_init FROM ci_labs WHERE lab_ID = {$lab_id}");
        $suffix  = $group_id > 0
            ? $wpdb->get_var("SELECT tg_name FROM ci_test_groups WHERE tg_ID = {$group_id}")
            : 'All';
        $filename = strtoupper($tes_type) . '_Panels_' . sanitize_file_name($suffix) . '_' . $labinit . '_' . date_i18n('Y-m-d') . '.csv';

        wp_send_json([
            'success'  => true,
            'data'     => [
                'csv'      => base64_encode($csv),
                'filename' => $filename,
            ]
        ]);
        exit;
    }

    // Parse uploaded CSV → return preview rows + column headers
    if ($action === 'parse_csv') {
        if (empty($_FILES['csvfile']['tmp_name'])) {
            wp_send_json(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }

        $separater = ',';
        $datafile  = file_get_contents($_FILES['csvfile']['tmp_name']);
        $filename = $_FILES['csvfile']['name'];
        if (stripos($filename, '.json') !== false || (isset($datafile[0]) && $datafile[0] === '{')) {
            $json = json_decode($datafile, true);
            if (!$json || !isset($json['groups'], $json['tests'], $json['cutoffs'])) {
                wp_send_json(['success' => false, 'message' => 'Invalid JSON format. Expected keys: groups, tests, cutoffs.']);
                exit;
            }

            $onlycutoff    = !empty($_POST['onlycutoff']);
            $test_cols     = $wpdb->get_col("SHOW COLUMNS FROM `ci_test_panels`");
            $group_cols    = $wpdb->get_col("SHOW COLUMNS FROM `ci_test_groups`");
            $cutoff_cols   = $wpdb->get_col("SHOW COLUMNS FROM `ci_test_panels_cutoffs`");
            $specimen_cols = $wpdb->get_col("SHOW COLUMNS FROM `ci_test_specimens`");
            $filefrom      = $json['from'] ?? '';
            $errors        = [];
            $group_ids     = []; // old_id => new_id

            // Import groups
            foreach ($json['groups'] as $g) {
                $parent = 0;
                if ($g['tg_parent'] > 0) {
                    $parent = $group_ids[$g['tg_parent']] ?? 0;
                    if (!$parent) {
                        $errors[] = 'Missing parent for group: ' . $g['tg_name'];
                        continue;
                    }
                }
                $gid = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_type='{$tes_type}' AND tg_parent='{$parent}' AND tg_name='{$g['tg_name']}'");
                if (!$gid) {
                    $gdata = $g;
                    unset($gdata['tg_ID']);
                    $gdata['tg_type'] = $tes_type;
                    $gdata['tg_parent'] = $parent;
                    foreach (array_keys($gdata) as $k) {
                        if (!in_array($k, $group_cols)) unset($gdata[$k]);
                    }
                    $wpdb->insert('ci_test_groups', $gdata);
                    $gid = $wpdb->insert_id;
                }
                $group_ids[$g['tg_ID']] = $gid;
            }

            // Import tests + metas
            $test_ids = [];
            foreach ($json['tests'] as $t) {
                $metas = $t['metas'] ?? [];
                $gid   = $group_ids[$t['te_group_id']] ?? 0;
                $tid   = $wpdb->get_var("SELECT te_ID FROM ci_test_panels WHERE te_type='{$tes_type}' AND te_name='{$t['te_name']}' AND te_group_id='{$gid}'");

                $tdata = $t;
                foreach (array_keys($tdata) as $k) {
                    if (!in_array($k, $test_cols)) unset($tdata[$k]);
                }
                unset($tdata['te_centaur_code'], $tdata['te_mf_ranges'], $tdata['te_is_hlm_detected'], $tdata['te_ID'], $tdata['import_ID']);
                $tdata['te_type'] = $tes_type;
                $tdata['te_group_id'] = intval($gid);

                if ($filefrom === 'ark') {
                    $ark = [
                        4 => 5,
                        3 => ($tes_type === 'blood' ? 1 : $tdata['te_is_detected']),
                        2 => ($tes_type === 'blood' ? 3 : $tdata['te_is_detected']),
                        1 => ($tes_type === 'blood' ? 2 : $tdata['te_is_detected'])
                    ];
                    $tdata['te_is_detected'] = $ark[$tdata['te_is_detected']] ?? $tdata['te_is_detected'];
                }

                if (!$tid) {
                    $wpdb->insert('ci_test_panels', $tdata);
                    $tid = $wpdb->insert_id;
                } elseif (!$onlycutoff) {
                    $wpdb->update('ci_test_panels', $tdata, ['te_ID' => $tid]);
                }
                $test_ids[$t['te_ID']] = $tid;

                if (!$onlycutoff) {
                    foreach ($metas as $mk => $mv) {
                        $eid = $wpdb->get_var("SELECT id FROM ci_test_meta WHERE test_id={$tid} AND meta_key='{$mk}'");
                        $ma  = ['test_id' => $tid, 'meta_key' => $mk, 'meta_value' => $mv];
                        $eid > 0 ? $wpdb->update('ci_test_meta', $ma, ['id' => $eid]) : $wpdb->insert('ci_test_meta', $ma);
                    }
                }
            }

            // Import cutoffs
            $count_cutoffs = 0;
            foreach ($json['cutoffs'] as $cut) {
                $tid = $test_ids[$cut['cu_te_ID']] ?? 0;
                if (!$tid) continue;
                $existing = $wpdb->get_var("SELECT cu_te_ID FROM ci_test_panels_cutoffs WHERE cu_te_ID={$tid} AND cu_btype='{$tes_type}' AND cu_ms='{$cut['cu_ms']}'");
                $cut['cu_btype'] = $tes_type;
                $cut['cu_te_ID'] = $tid;
                foreach (array_keys($cut) as $k) {
                    if (!in_array($k, $cutoff_cols) || is_null($cut[$k])) unset($cut[$k]);
                }
                $existing
                    ? $wpdb->update('ci_test_panels_cutoffs', $cut, ['cu_te_ID' => $tid, 'cu_btype' => $tes_type, 'cu_ms' => $cut['cu_ms']])
                    : $wpdb->insert('ci_test_panels_cutoffs', $cut);
                $count_cutoffs++;
                if (!empty($cut['cu_ms'])) {
                    foreach (explode(',', $cut['cu_ms']) as $m) {
                        $m = trim($m);
                        if ($m && $m !== '*') $wpdb->query("INSERT IGNORE INTO ci_instruments (instrument,btype) VALUES ('{$m}','{$tes_type}')");
                    }
                }
            }

            // Import specimens
            if (!$onlycutoff && isset($json['specimens'])) {
                foreach ($json['specimens'] as $sp) {
                    $gid = $group_ids[$sp['ts_group_id']] ?? 0;
                    $sid = $wpdb->get_var("SELECT ts_ID FROM ci_test_specimens WHERE ts_type='{$tes_type}' AND ts_name='{$sp['ts_name']}' AND ts_group_id='{$gid}'");
                    $sd  = $sp;
                    unset($sd['ts_ID']);
                    foreach (array_keys($sd) as $k) {
                        if (!in_array($k, $specimen_cols)) unset($sd[$k]);
                    }
                    $sd['ts_type'] = $tes_type;
                    $sd['ts_group_id'] = intval($gid);
                    $sid ? $wpdb->update('ci_test_specimens', $sd, ['ts_ID' => $sid]) : $wpdb->insert('ci_test_specimens', $sd);
                }
            }

            wp_send_json([
                'success' => true,
                'type' => 'json',
                'data'    => [
                    'count_groups'  => count($json['groups']),
                    'count_tests'   => count($json['tests']),
                    'count_cutoffs' => $count_cutoffs,
                    'errors'        => $errors,
                ],
            ]);
            exit;
        }
        if (substr_count($datafile, ';') > substr_count($datafile, ',')) {
            $separater = ';';
        }

        $data = [];
        $f    = fopen($_FILES['csvfile']['tmp_name'], 'r');
        while ($row = fgetcsv($f, null, $separater)) {
            $data[] = $row;
        }
        fclose($f);

        // Store in session for subsequent import
        $_SESSION['import_panels_data'] = $data;

        // Return headers (first row) and preview rows (up to 5)
        $headers = !empty($data[0]) ? $data[0] : [];
        $preview = array_slice($data, 0, 6); // include header row

        wp_send_json([
            'success' => true,
            'data'    => [
                'headers'     => $headers,
                'preview'     => $preview,
                'total_rows'  => count($data) - 1, // exclude header
            ]
        ]);
        exit;
    }

    // Execute import from session data
    if ($action === 'do_import') {
        $group_id    = isset($_POST['group_id'])   ? intval($_POST['group_id'])           : 0;
        $ms          = isset($_POST['ms'])         && !empty($_POST['ms']) ? $_POST['ms'] : '*';
        $msarr       = strpos($ms, ',') > 0 ? explode(',', $ms) : [$ms];
        $maps        = isset($_POST['maps'])       ? $_POST['maps']                       : [];
        $replace     = isset($_POST['replace'])    && $_POST['replace'];
        $dontnewtest = isset($_POST['dontnewtest']) && $_POST['dontnewtest'];

        // Defaults
        $default_vlow    = isset($_POST['vlow'])    ? $_POST['vlow']    : '';
        $default_low    = isset($_POST['low'])    ? $_POST['low']    : '';
        $default_medium = isset($_POST['medium']) ? $_POST['medium'] : '';
        $default_high   = isset($_POST['high'])   ? $_POST['high']   : '';
        $default_vhigh   = isset($_POST['vhigh'])   ? $_POST['vhigh']   : '';

        $pcr_settings   = get_option('ci_result_settings_pcr', []);
        $data           = isset($_SESSION['import_panels_data']) ? $_SESSION['import_panels_data'] : [];

        if (empty($data) || empty($maps)) {
            wp_send_json(['success' => false, 'message' => 'No data or mapping found']);
            exit;
        }

        $group        = $group_id > 0 ? $wpdb->get_row("SELECT * FROM ci_test_groups WHERE tg_ID = {$group_id}") : null;
        $groupsbynames = [];
        $count_test    = 0;
        $errors        = [];
        $position      = 0;

        foreach ($data as $ri => $line) {
            if ($ri === 0) continue;

            $count_test++;
            $position += 2;
            $row_group_id = $group_id;
            $rowdata      = [];
            $group_name   = '';
            $subgroup_name = '';

            foreach ($maps as $i => $mkey) {
                if (!empty($mkey) && isset($line[$i]) && $line[$i] !== '') {
                    $colvl = trim($line[$i]);
                    if ($mkey === 'group') {
                        $group_name = $colvl;
                        continue;
                    } elseif ($mkey === 'subgroup') {
                        $subgroup_name = $colvl;
                        $gramname = str_replace(['-', '  '], [' '], $subgroup_name);
                        if (stripos($gramname, 'gram positive') !== false) $subgroup_name = 'Gram Positive';
                        elseif (stripos($gramname, 'gram negative') !== false) $subgroup_name = 'Gram Negative';
                        continue;
                    }
                    $rowdata[$mkey] = $colvl;
                }
            }

            // Resolve group
            if (!empty($group_name)) {
                $key = strtolower($group_name);
                if (!isset($groupsbynames[$key])) {
                    $gid = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_type = '{$tes_type}' AND tg_name = '{$group_name}' AND tg_parent = 0");
                    if ($gid > 0) {
                        $groupsbynames[$key] = $gid;
                    } elseif (!$dontnewtest) {
                        $wpdb->insert('ci_test_groups', ['tg_type' => $tes_type, 'tg_name' => $group_name]);
                        $groupsbynames[$key] = $wpdb->insert_id;
                    }
                }
                $row_group_id = $groupsbynames[$key] ?? $row_group_id;
            }

            // Resolve subgroup
            if (!empty($subgroup_name)) {
                $subkey = strtolower($subgroup_name) . '_sub';
                if (!isset($groupsbynames[$subkey])) {
                    $gid = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_type = '{$tes_type}' AND tg_name = '{$subgroup_name}' AND tg_parent = {$row_group_id}");
                    if ($gid > 0) {
                        $groupsbynames[$subkey] = $gid;
                    } elseif (!$dontnewtest) {
                        $wpdb->insert('ci_test_groups', ['tg_type' => $tes_type, 'tg_name' => $subgroup_name, 'tg_parent' => $row_group_id, 'tg_show_req' => 0]);
                        $groupsbynames[$subkey] = $wpdb->insert_id;
                    }
                }
                $row_group_id = $groupsbynames[$subkey] ?? $row_group_id;
            }

            $testname = isset($rowdata['te_name']) ? $rowdata['te_name'] : '';
            $abrev    = isset($rowdata['te_abrev']) ? $rowdata['te_abrev'] : '';

            if (empty($testname) && !empty($abrev)) {
                $sql_test = "SELECT te_name FROM ci_test_panels WHERE te_type='{$tes_type}' AND te_abrev = '{$abrev}'";
                if ($row_group_id > 0) {
                    $sql_test .= " AND (te_group_id = {$row_group_id} OR te_group_id IN (SELECT tg_ID FROM ci_test_groups WHERE tg_parent = {$row_group_id}))";
                }
                $testname = $wpdb->get_var($sql_test);

                if (!$testname && $group && $group->tg_parent > 0) {
                    $testname = $wpdb->get_var("SELECT te_name FROM ci_test_panels WHERE te_type='{$tes_type}' AND te_abrev = '{$abrev}' AND te_group_id = {$group->tg_parent}");
                }
            }

            if (empty($testname)) continue;

            $test_data = [
                'te_name'       => $testname,
                'te_type'       => $tes_type,
                'te_group_id'   => $row_group_id,
                'te_in_req'     => '1',
                'te_is_detected' => '1',
                'te_position'   => $position,
            ];

            if (stripos($subgroup_name, 'control') !== false) {
                $test_data['te_in_req']     = 0;
                $test_data['te_is_control'] = 1;
            }
            if (!empty($abrev)) $test_data['te_abrev'] = $abrev;

            // Build cutoff array
            $cutoff = [];
            if (!empty($default_vlow))    $cutoff['cu_critical_low']      = $default_vlow;
            if (!empty($default_low))    $cutoff['cu_cutoff']      = $default_low;
            if (!empty($default_medium)) $cutoff['cu_medium']       = $default_medium;
            if (!empty($default_high))   $cutoff['cu_upper_limit']  = $default_high;
            if (!empty($default_vhigh))   $cutoff['cu_critical_high']  = $default_vhigh;

            foreach ($rowdata as $k => $vl) {
                if (substr($k, 0, 3) === 'te_') {
                    $test_data[$k] = $vl;
                } elseif (substr($k, 0, 3) === 'cu_') {
                    $vl = str_replace(',', '.', $vl);
                    $vl = trpdd_replace_symbol_db($vl);

                    if (strpos($vl, '>') === false && strpos($vl, '<') === false && strpos($vl, '=') === false) {
                        if (!empty($pcr_settings['enable_rs_ct_amp_cq']) && in_array($k, ['cu_critical_high', 'cu_hqc'])) {
                            $vl = stripos($vl, '>') === false && stripos($vl, '-') === false ? '>=' . $vl : $vl;
                        } elseif (in_array($k, ['cu_upper_limit', 'cu_cutoff', 'cu_medium', 'cu_critical_high', 'cu_critical_low'])) {
                            if (stripos($vl, '-') > 0) {
                                [$num1, $num2] = explode('-', $vl);
                                if ($num1 > $num2) {
                                    [$num1, $num2] = [$num2, $num1];
                                }
                                if (in_array($k, ['cu_upper_limit', 'cu_medium']) && stripos($num2, '<') === false) {
                                    $num2 = '<' . $num2;
                                }
                                $vl = $num1 . '-' . $num2;
                            } else {
                                $vl = stripos($vl, '<') === false ? '<=' . $vl : $vl;
                            }
                        }
                    }
                    $cutoff[$k] = trpdd_replace_symbol_db($vl);
                }
            }

            // Find or create test
            $sql_test = "SELECT te_ID FROM ci_test_panels WHERE te_type='{$tes_type}' AND te_name = '{$testname}'";
            if ($row_group_id > 0) {
                $sql_test .= " AND (te_group_id = {$row_group_id} OR te_group_id IN (SELECT tg_ID FROM ci_test_groups WHERE tg_parent = {$row_group_id}))";
            }
            $test_id = $wpdb->get_var($sql_test);

            if (!$test_id && $group && $group->tg_parent > 0) {
                $test_id = $wpdb->get_var("SELECT te_ID FROM ci_test_panels WHERE te_type='{$tes_type}' AND te_name = '{$testname}' AND te_group_id = {$group->tg_parent}");
            }

            if (($row_group_id == 0 || $dontnewtest) && !$test_id) {
                $errors[] = "{$group_name}: {$testname} — not found";
                continue;
            }

            if (!$test_id && !$dontnewtest) {
                $wpdb->insert('ci_test_panels', $test_data);
                $test_id = $wpdb->insert_id;
            } elseif ($test_id > 0) {
                $update_data = $test_data;
                foreach (['te_name', 'te_type', 'te_group_id', 'te_in_req', 'te_is_detected', 'te_position'] as $uk) {
                    unset($update_data[$uk]);
                }
                $wpdb->update('ci_test_panels', $update_data, ['te_ID' => $test_id]);
            }

            // Upsert cutoffs
            if (isset($cutoff['cu_start']) && !empty($cutoff['cu_start'])) {
                $cutoff['cu_start'] = strtotime($cutoff['cu_start']) > 0 ? date('Y-m-d', strtotime($cutoff['cu_start'])) : '';
            }
            if (isset($cutoff['cu_end']) && !empty($cutoff['cu_end'])) {
                $cutoff['cu_end'] = strtotime($cutoff['cu_end']) > 0 ? date('Y-m-d', strtotime($cutoff['cu_end'])) : '';
            }
            //check has data
            $has_cutoff = false;
            foreach ($cutoff as $k => $v) {
                if (!empty($v) && !in_array($k, ['cu_start', 'cu_end', 'cu_ms'])) {
                    $has_cutoff = true;
                    break;
                }
            }
            if (!$has_cutoff) {
                $cutoff = array();
            }
            if ($test_id > 0 && count($cutoff) > 0) {

                $ms_arr2 = $msarr;
                if (!empty($cutoff['cu_ms'])) {
                    $ms_arr2 = array($cutoff['cu_ms']);
                }

                foreach ($ms_arr2 as $msi) {

                    $cutoff['cu_te_ID']  = $test_id;
                    $cutoff['cu_ms']     = $msi;
                    $cutoff['cu_btype']  = $tes_type;

                    $where_cut = empty($msi) || $msi === '*'
                        ? " AND (cu_ms = '{$msi}' OR cu_ms IS NULL OR cu_ms = '')"
                        : " AND cu_ms = '{$msi}'";

                    if (!empty($cutoff['cu_start'])) {
                        $where_cut .= " AND cu_start = '{$cutoff['cu_start']}'";
                    }


                    $cut_db = $wpdb->get_row("SELECT * FROM ci_test_panels_cutoffs WHERE cu_te_ID = {$test_id} AND cu_btype = '{$tes_type}' {$where_cut}", ARRAY_A);


                    if (!$cut_db || empty($cut_db['cu_te_ID'])) {
                        //check previous cutoff with same ms and change end date to prev of new start date
                        if (!empty($cutoff['cu_ms']) && !empty($cutoff['cu_start'])) {
                            $where_existing = "cu_te_ID = {$test_id} AND cu_btype = '{$tes_type}' AND cu_ms = '{$cutoff['cu_ms']}' ";

                            $existing_cutoff = $wpdb->get_row("SELECT * FROM ci_test_panels_cutoffs WHERE {$where_existing} order by cu_start desc", ARRAY_A);
                            if ($existing_cutoff) {
                                $end_date = date('Y-m-d', strtotime($cutoff['cu_start']) - 86400);
                                $wpdb->query("UPDATE ci_test_panels_cutoffs SET cu_end = '{$end_date}' WHERE {$where_existing}");
                            }
                        }
                        $wpdb->insert('ci_test_panels_cutoffs', $cutoff);
                    } else {
                        if (!$replace) {
                            foreach ($cutoff as $k => $vl) {
                                if (!empty($cut_db[$k])) unset($cutoff[$k]);
                            }
                        }

                        if (!empty($cutoff)) {

                            //check previous cutoff with same ms and change end date to prev of new start date
                            if (!empty($cutoff['cu_ms']) && !empty($cutoff['cu_start'])) {
                                $where_existing = "cu_te_ID = {$test_id} AND cu_btype = '{$tes_type}' AND cu_ms = '{$cutoff['cu_ms']}' AND cu_start != '{$cut_db['cu_start']}'";
                                $existing_cutoff = $wpdb->get_row("SELECT * FROM ci_test_panels_cutoffs WHERE {$where_existing} order by cu_start desc", ARRAY_A);
                                if ($existing_cutoff) {
                                    $end_date = date('Y-m-d', strtotime($cutoff['cu_start']) - 86400);
                                    $wpdb->query("UPDATE ci_test_panels_cutoffs SET cu_end = '{$end_date}' WHERE {$where_existing}");
                                }
                            }
                            $where = [
                                'cu_te_ID' => $test_id,
                                'cu_btype' => $tes_type,
                                'cu_ms' => $cut_db['cu_ms']
                            ];
                            if (!empty($cutoff['cu_start'])) {
                                $where['cu_start'] = $cut_db['cu_start'];
                            }
                            $wpdb->update('ci_test_panels_cutoffs', $cutoff, $where);
                        }
                    }
                }
            }
        }

        wp_send_json([
            'success' => true,
            'data'    => [
                'count'  => $count_test,
                'errors' => $errors,
            ]
        ]);
        exit;
    }
}

?>
<div class="wrap">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl" style="color: rgb(60, 61, 89); font-weight: 600;">Import &amp; Export</h1>
        </div>
        <?php
        if ($is_ci_admin) { ?>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="text-base" style="color: rgb(60, 61, 89); margin: 0;">Admin Exports</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&:last-child]:pb-6">
                    <div class="grid grid-cols-4 gap-3">
                        <a style="display:inline-flex;" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'compendium_export', 'trajax' => 1)) ?>">
                            <button data-slot="button" style="width: 100%;" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 px-4 py-2 has-[>svg]:px-3 h-12">
                                Compendium Export
                            </button>
                        </a>
                        <a style="display:inline-flex;" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'export_json', 'trajax' => 1)) ?>">
                            <button data-slot="button" style="width: 100%;" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 px-4 py-2 has-[>svg]:px-3 h-12">
                                Export Json
                            </button>
                        </a>
                        <a style="display:inline-flex;" class="thickbox" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'backup_json', 'trajax' => 1)) ?>">
                            <button data-slot="button" style="width: 100%;" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 px-4 py-2 has-[>svg]:px-3 h-12">
                                Backup Json
                            </button>
                        </a>
                        <a style="display:inline-flex;" class="thickbox" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'act' => 'backups', 'trajax' => 1)) ?>">
                            <button data-slot="button" style="width: 100%;" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 px-4 py-2 has-[>svg]:px-3 h-12">
                                Backups
                            </button>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="text-base" style="color: rgb(60, 61, 89); margin: 0;">Export Group Data</h4>
            </div>
            <div data-slot="card-content" class="px-6 [&:last-child]:pb-6 space-y-4">
                <div class="space-y-2">
                    <label data-slot="label" class="flex items-center gap-2 font-medium text-gray-700 select-none text-sm">Select Group to Export</label>
                    <select id="export-group-select"
                        class="border-input text-gray-600 flex w-full items-center justify-between gap-2 rounded-md border bg-input-background px-3 py-2 text-sm whitespace-nowrap transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-9">
                        <option value="0">All Groups</option>
                        <?php
                        $export_groups = $wpdb->get_results("
                            SELECT tg_ID, tg_name
                            FROM ci_test_groups
                            WHERE tg_type = '{$tes_type}' AND tg_show_req = 1 AND tg_parent = 0
                            ORDER BY tg_position ASC
                        ");
                        foreach ($export_groups as $eg) {
                            echo '<option value="' . esc_attr($eg->tg_ID) . '">' . esc_html($eg->tg_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button id="btn-export-group-data" data-slot="button"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-primary-foreground px-4 py-2 has-[>svg]:px-3 flex-1 h-12 bg-primary hover:bg-primary/90">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" x2="12" y1="15" y2="3"></line>
                        </svg>Export Group Data
                    </button>
                    <button id="btn-export-cutoffs" data-slot="button"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-primary-foreground px-4 py-2 has-[>svg]:px-3 flex-1 h-12 bg-primary hover:bg-primary/90">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-down h-4 w-4 mr-2">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                            <path d="M12 18v-6"></path>
                            <path d="m9 15 3 3 3-3"></path>
                        </svg>Export Cutoffs
                    </button>
                </div>
                <!-- Export status message -->
                <div id="export-status" class="hidden text-sm text-gray-500 text-center py-1"></div>
            </div>
        </div>

        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="text-base" style="color: rgb(60, 61, 89);margin: 0;">Import Group Data</h4>
            </div>
            <div data-slot="card-content" class="px-6 [&:last-child]:pb-6 space-y-6">

                <div id="import-dropzone"
                    class="border-2 border-dashed border-gray-300 rounded-lg p-8 flex flex-col items-center justify-center gap-3 cursor-pointer hover:border-primary transition-colors"
                    onclick="document.getElementById('import-csv-file').click()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-12 w-12 mx-auto mb-4 text-gray-400">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" x2="12" y1="3" y2="15"></line>
                    </svg>
                    <p class="text-sm mb-2">Drag and drop your CSV file here, or</p>
                    <label for="import-csv-file">
                        <button data-slot="button" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3" type="button">
                            Choose File</button>
                    </label>
                    <input id="import-csv-file" type="file" accept=".csv,.json" class="hidden" />
                    <p class="text-xs text-muted-foreground mt-2">Supports: .csv and .json files</p>
                </div>

                <div id="import-file-info" class="hidden border-2 border-gray-300 rounded-lg p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                            <path d="M10 9H8"></path>
                            <path d="M16 13H8"></path>
                            <path d="M16 17H8"></path>
                        </svg>
                        <div>
                            <p id="import-file-name" class="text-sm font-medium"></p>
                            <p id="import-file-size" class="text-xs text-muted-foreground"></p>
                        </div>
                    </div>
                    <button id="btn-clear-file" data-slot="button"
                        class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all hover:bg-accent hover:text-accent-foreground h-8 rounded-md gap-1.5 px-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="import-json-options" class="hidden space-y-4 pt-2 border border-blue-100 rounded-lg p-4 bg-blue-50/50">
                    <h4 class="text-sm font-medium" style="color: rgb(60, 61, 89);margin-top: 0;">JSON Import Options</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" id="json-onlycutoff" style="margin:0;" class="custom-checkbox size-4 shrink-0 rounded-[4px] border mt-0.5 accent-primary" />
                            <div class="flex-1">
                                <label for="json-onlycutoff" class="text-sm font-medium cursor-pointer block">Only Update Cutoffs</label>
                                <p class="text-xs text-muted-foreground mt-1">Skip updating test and group data; only upsert cutoff values.</p>
                            </div>
                        </div>
                    </div>
                    <button id="btn-do-json-import" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all text-primary-foreground px-4 py-2 w-full h-12 bg-primary hover:bg-primary/90">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="17 8 12 3 7 8" />
                            <line x1="12" x2="12" y1="3" y2="15" />
                        </svg>
                        Import JSON
                    </button>
                </div>
                <div id="json-import-result" class="hidden text-sm rounded-lg p-3"></div>

                <div id="import-config" class="hidden space-y-6 pt-4">

                    <!-- Preview Table -->
                    <div>
                        <h4 class="text-sm font-medium mb-3" style="color: rgb(60, 61, 89);margin:0;">Import Preview</h4>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table id="import-preview-table" class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b" style="background-color: rgb(248, 249, 250);"></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 pt-4">

                        <div>
                            <h4 class="text-sm font-medium mb-3" style="color: rgb(60, 61, 89);margin:0;">Field Mapping</h4>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="relative w-full overflow-x-auto">
                                    <table class="w-full caption-bottom text-sm">
                                        <thead class="[&_tr]:border-b">
                                            <tr class="hover:bg-muted/50 border-b transition-colors bg-gray-50">
                                                <th class="h-10 px-2 text-left align-middle font-semibold text-gray-900" style="width: 50%;">CSV Column</th>
                                                <th class="h-10 px-2 text-left align-middle font-semibold text-gray-900" style="width: 50%;">Maps To</th>
                                            </tr>
                                        </thead>
                                        <tbody id="import-mapping-body" class="[&_tr:last-child]:border-0"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <h4 class="text-sm font-medium mb-3" style="color: rgb(60, 61, 89); margin: 0;">Select Group</h4>
                                <select id="import-group-select"
                                    class="border-input text-gray-600 flex w-full items-center justify-between gap-2 rounded-md border bg-input-background px-3 py-2 text-sm whitespace-nowrap transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-9">
                                    <option value="0">Global (multiple groups)</option>
                                    <?php
                                    foreach ($export_groups as $eg) {
                                        echo '<option value="' . esc_attr($eg->tg_ID) . '">' . esc_html($eg->tg_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Default CT Values -->
                            <div class="space-y-3">
                                <h4 class="text-sm font-medium" style="color: rgb(60, 61, 89);margin: 0 0 10px 0;">Default CT Values</h4>
                                <div class="grid gap-2" style="grid-template-columns: repeat(6,1fr);">
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">Instr.</label>
                                        <input id="import-ms" type="text" placeholder="*"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">V. Low</label>
                                        <input id="import-vlow" type="text"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">Low</label>
                                        <input id="import-low" type="text"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">Medium</label>
                                        <input id="import-medium" type="text"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">High</label>
                                        <input id="import-high" type="text"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 font-medium text-gray-700 text-xs">V. High</label>
                                        <input id="import-vhigh" type="text"
                                            class="input-import-df file:text-foreground placeholder:text-muted-foreground border-input flex w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9" />
                                    </div>
                                </div>
                            </div>

                            <!-- Import Options -->
                            <div class="space-y-3">
                                <label class="flex items-center gap-2 text-gray-700 text-sm font-medium">Import Options</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-start gap-2">
                                        <input type="checkbox" id="import-replace" checked style="margin: 0;" class="custom-checkbox size-4 shrink-0 rounded-[4px] border mt-0.5 accent-primary" />
                                        <div class="flex-1">
                                            <label for="import-replace" class="text-sm font-medium cursor-pointer block">Replace cutoffs</label>
                                            <p class="text-xs text-muted-foreground mt-1">If a test already exists in the panel, its cutoffs will be updated.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <input type="checkbox" id="import-dontnewtest" style="margin: 0;"
                                            class="custom-checkbox size-4 shrink-0 rounded-[4px] border mt-0.5 accent-primary" />
                                        <div class="flex-1">
                                            <label for="import-dontnewtest" class="text-sm font-medium cursor-pointer block">Only update existing tests</label>
                                            <p class="text-xs text-muted-foreground mt-1">Tests in the CSV that are not already in the panel will not be created.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Import Summary -->
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-gray-700 text-sm font-medium">Import Summary</label>
                                <div class="border rounded-lg p-4 bg-gray-50 space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Tests to import:</span>
                                        <span id="summary-count" class="font-medium">—</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Target group:</span>
                                        <span id="summary-group" class="font-medium">Global</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Mode:</span>
                                        <span id="summary-mode" class="font-medium">Create &amp; Update</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Import button -->
                            <button id="btn-do-import" data-slot="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] text-primary-foreground px-4 py-2 has-[>svg]:px-3 w-full h-12 bg-primary hover:bg-primary/90">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" x2="12" y1="3" y2="15"></line>
                                </svg>Import Data
                            </button>
                        </div>
                    </div>
                </div>
                <div id="import-result" class="hidden text-sm rounded-lg p-3"></div>
            </div>
        </div>

        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card" style="background-color: rgb(248, 249, 250);">
            <div data-slot="card-content" class="px-6 [&:last-child]:pb-6 text-sm text-gray-600 space-y-2 pt-6">
                <p style="margin-bottom: 7px;"><strong>Export:</strong> Download panel configuration and cutoff values as CSV files.</p>
                <p style="margin-bottom: 7px;"><strong>Import:</strong> Upload a CSV file to add or update tests and cutoffs. Map CSV columns to system fields.</p>
                <p style="margin-bottom: 7px;"><strong>Field Mapping:</strong> Shows CSV columns (left) and their mapped system fields (right).</p>
                <p style="margin-bottom: 7px;"><strong>Import Options:</strong></p>
                <ul style="margin-left: 20px; list-style: disc;">
                    <li><strong>Replace cutoffs:</strong> Updates cutoffs and settings for tests already in the group</li>
                    <li><strong>Only update existing tests:</strong> Prevents creation of new tests; updates existing only</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<style>
    input.input-import-df {
        border-radius: 8px !important;
        background-color: var(--input-background);
        outline: none;
        box-shadow: none !important;
        border: none;
    }

    .select2-container--default .select2-selection--single {
        height: 2.25rem !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        line-height: 1.25rem !important;
        color: #4b5563 !important;
        background-color: #f8f9fb !important;
        border: none;
        border-radius: 8px !important;
        outline: none !important;
        transition: all 0.15s ease-in-out !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #4b5563 !important;
        line-height: 1.25rem !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 2.25rem !important;
        right: 0.75rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #6b7280 transparent transparent transparent !important;
        opacity: 0.5 !important;
    }

    .select2-container--default .select2-selection--single:hover {
        background-color: #f9fafb !important;
    }

    .select2-container--default .select2-results__option {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #f3f4f6 !important;
        color: #374151 !important;
    }

    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #fef2f2 !important;
        color: #D83D00 !important;
        font-weight: 500 !important;
    }

    .select2-container--default .select2-results__option[aria-selected="true"]::after {
        content: '✓';
        float: right;
        font-weight: bold;
        margin-left: 8px;
    }

    .select2-dropdown {
        border: 1px solid #d1d5db !important;
        border-radius: 0.375rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }

    .select2-container--default.select2-container--disabled .select2-selection--single {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }

    .custom-checkbox {
        appearance: none;
        width: 16px !important;
        height: 16px !important;
        border: 1px solid #ccc;
        border-radius: 4px;
        cursor: pointer;
        position: relative;
    }

    input.custom-checkbox:focus {
        border-color: #D83D00;
        box-shadow: none;
    }

    .custom-checkbox:checked {
        background: #D83D00;
        border-color: #D83D00;
    }

    input.custom-checkbox:checked::before {
        content: none;
    }

    .custom-checkbox:checked::after {
        content: "";
        position: absolute;
        left: 4px;
        top: 1px;
        width: 4px;
        height: 8px;
        border: solid #fff;
        border-width: 0 1px 1px 0;
        transform: rotate(45deg);
    }

    @media screen and (max-width: 782px) {
        .grid-cols-2 {
            grid-template-columns: 1fr;
        }

        .grid-cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- jQuery Logic    -->
<script>
    jQuery(function($) {
        $('#export-group-select').select2({
            minimumResultsForSearch: Infinity,
            width: '100%'
        });
        $('#import-group-select').select2({
            minimumResultsForSearch: Infinity,
            width: '100%'
        });


        // Mapping options
        var MAPS = {
            'te_name': 'Name',
            'te_abrev': 'Abrev',
            'te_abrev2': 'Abrev 2',
            'te_snomed_code': 'Snomed Code',
            'te_loinc_code': 'Loinc Code',
            'te_label_barcode': 'Label Barcode',
            'te_units': 'Units',
            'te_range': 'Range',
            'te_conc_decimal': 'Decimal',
            'cu_critical_high': 'Very High/Amp Score',
            'cu_upper_limit': 'Ct / High',
            'cu_medium': 'Medium',
            'cu_cutoff': 'Low',
            'cu_critical_low': 'Very Low',
            'cu_invalid': 'Invalid',
            'cu_hqc': 'Cq',
            'cu_ms': 'Instrument',
            'cu_start': 'Start Date',
            'cu_end': 'End Date',
            'group': 'Group',
            'subgroup': 'Sub Group',
        };

        // Auto-detect map key from column header label
        function autoDetectMapKey(col) {
            var c = col.trim().toLowerCase();
            if (c === 'test' || c === 'name' || c === 'test name' || c === 'all') return 'te_name';
            if (c.indexOf('abrev2') === 0) return 'te_abrev2';
            if (c.indexOf('abrev') === 0) return 'te_abrev';
            if (c.indexOf('test code') === 0 || c === 'loinc') return 'te_loinc_code';
            if (c === 'group' || c === 'panel') return 'group';
            if (c === 'sub group') return 'subgroup';
            if (c === 'very low') return 'cu_critical_low';
            if (c === 'low') return 'cu_cutoff';
            if (c === 'medium') return 'cu_medium';
            if (c === 'high') return 'cu_upper_limit';
            if (c === 'very high') return 'cu_critical_high';
            if (c === 'instrument') return 'cu_ms';
            if (c === 'snomed') return 'te_snomed_code';
            if (c === 'loinc') return 'te_loinc_code';
            if (c === 'barcode') return 'te_label_barcode';
            if (c === 'units' || c === 'unit') return 'te_units';
            if (c === 'start date') return 'cu_start';
            if (c === 'end date') return 'cu_end';
            return '';
        }

        function buildMapSelect(idx, selectedKey) {
            var html = '<select name="maps[' + idx + ']" class="map-select border-input text-gray-600 flex w-full rounded-md border bg-input-background px-2 py-1 text-sm outline-none focus-visible:ring-[3px] h-8">';
            html += '<option value="">— ignore —</option>';
            $.each(MAPS, function(k, label) {
                html += '<option value="' + k + '"' + (k === selectedKey ? ' selected' : '') + '>' + label + '</option>';
            });
            html += '</select>';
            return html;
        }

        function downloadCsv(base64, filename) {
            var a = document.createElement('a');
            a.href = 'data:text/csv;base64,' + base64;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function exportStatus(msg, cls) {
            var $el = $('#export-status');
            $el.removeClass('hidden text-red-500 text-green-600 text-gray-500')
                .addClass(cls || 'text-gray-500')
                .text(msg)
                .removeClass('hidden');
            if (!msg) $el.addClass('hidden');
        }

        // EXPORT GROUP DATA
        $('#btn-export-group-data').on('click', function() {
            var groupId = $('#export-group-select').val();
            exportStatus('Exporting…');
            $(this).prop('disabled', true);
            var $btn = $(this);

            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    trajax: 'export_group_data',
                    group_id: groupId
                },
                success: function(res) {
                    if (res.success && res.data) {
                        downloadCsv(res.data.csv, res.data.filename);
                        exportStatus('Downloaded: ' + res.data.filename, 'text-green-600');
                    } else {
                        exportStatus('Export failed.', 'text-red-500');
                    }
                },
                error: function() {
                    exportStatus('Request error.', 'text-red-500');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    setTimeout(function() {
                        exportStatus('');
                    }, 4000);
                }
            });
        });

        // EXPORT CUTOFFS
        $('#btn-export-cutoffs').on('click', function() {
            var groupId = $('#export-group-select').val();
            exportStatus('Exporting cutoffs…');
            $(this).prop('disabled', true);
            var $btn = $(this);

            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    trajax: 'export_cutoffs',
                    group_id: groupId
                },
                success: function(res) {
                    if (res.success && res.data) {
                        downloadCsv(res.data.csv, res.data.filename);
                        exportStatus('Downloaded: ' + res.data.filename, 'text-green-600');
                    } else {
                        exportStatus('Export failed.', 'text-red-500');
                    }
                },
                error: function() {
                    exportStatus('Request error.', 'text-red-500');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    setTimeout(function() {
                        exportStatus('');
                    }, 4000);
                }
            });
        });

        // FILE INPUT – pick CSV file=
        var parsedHeaders = [];
        var parsedTotalRows = 0;
        var currentFile = null;
        var isJsonFile = false;

        $('#import-csv-file').on('change', function() {
            var file = this.files[0];
            if (!file) return;
            handleFileSelected(file);
        });

        // Drag & drop support
        var $dz = $('#import-dropzone');
        $dz.on('dragover', function(e) {
            e.preventDefault();
            $dz.addClass('border-primary');
        });
        $dz.on('dragleave', function() {
            $dz.removeClass('border-primary');
        });
        $dz.on('drop', function(e) {
            e.preventDefault();
            $dz.removeClass('border-primary');
            var file = e.originalEvent.dataTransfer.files[0];
            if (file) handleFileSelected(file);
        });

        function handleFileSelected(file) {
            currentFile = file;
            isJsonFile = /\.json$/i.test(file.name);

            $('#import-file-name').text(file.name);
            $('#import-file-size').text((file.size / 1024).toFixed(2) + ' KB');
            $('#import-dropzone').addClass('hidden');
            $('#import-file-info').removeClass('hidden');
            $('#import-config, #import-json-options').addClass('hidden');
            $('#json-import-result, #import-result').addClass('hidden');

            if (isJsonFile) {
                $('#import-json-options').removeClass('hidden');
            } else {
                var formData = new FormData();
                formData.append('trajax', 'parse_csv');
                formData.append('csvfile', file);
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success && res.data) {
                            parsedHeaders = res.data.headers;
                            parsedTotalRows = res.data.total_rows;
                            renderPreview(res.data.preview);
                            renderMapping(parsedHeaders);
                            updateSummary();
                            $('#import-config').removeClass('hidden');
                        }
                    }
                });
            }
        }

        // Clear file
        $('#btn-clear-file').on('click', function() {
            $('#import-csv-file').val('');
            $('#import-file-info, #import-config, #import-json-options').addClass('hidden');
            $('#import-dropzone').removeClass('hidden');
            $('#import-result, #json-import-result').addClass('hidden');
            parsedHeaders = [];
            parsedTotalRows = 0;
            currentFile = null;
            isJsonFile = false;
        });

        // Render preview table
        function renderPreview(rows) {
            var $thead = $('#import-preview-table thead tr').empty();
            var $tbody = $('#import-preview-table tbody').empty();

            if (!rows || rows.length === 0) return;

            // Header row
            $.each(rows[0], function(_, col) {
                $thead.append('<th class="text-left py-2 px-3 font-medium">' + $('<span>').text(col).html() + '</th>');
            });

            // Data rows (skip header, show up to 5)
            $.each(rows.slice(1), function(_, row) {
                var $tr = $('<tr class="border-b hover:bg-gray-100">');
                $.each(row, function(_, cell) {
                    $tr.append('<td class="py-2 px-3">' + $('<span>').text(cell).html() + '</td>');
                });
                $tbody.append($tr);
            });
        }

        // Render field mapping rows
        function renderMapping(headers) {
            var $tbody = $('#import-mapping-body').empty();
            $.each(headers, function(i, col) {
                var autoKey = autoDetectMapKey(col);
                var $tr = $('<tr class="data-[state=selected]:bg-muted transition-colors border-0 hover:bg-gray-100">');
                $tr.append('<td class="p-2 align-middle py-1.5 w-1/2">' + $('<span>').text(col).html() + '</td>');
                $tr.append('<td class="p-2 align-middle py-1.5 w-1/2">' + buildMapSelect(i, autoKey) + '</td>');
                $tbody.append($tr);
            });

            $('.map-select').select2({
                minimumResultsForSearch: Infinity,
                width: '100%'
            });

        }

        // Update import summary
        function updateSummary() {
            $('#summary-count').text(parsedTotalRows > 0 ? parsedTotalRows : '—');

            var $sel = $('#import-group-select');
            var grpTxt = $sel.find('option:selected').text() || 'Global';
            $('#summary-group').text(grpTxt);

            var replace = $('#import-replace').is(':checked');
            var dontNew = $('#import-dontnewtest').is(':checked');
            var mode = dontNew ? 'Update Only' : (replace ? 'Create & Update' : 'Create & Merge');
            $('#summary-mode').text(mode);
        }

        $('#import-group-select, #import-replace, #import-dontnewtest').on('change', updateSummary);

        $('#btn-do-json-import').on('click', function() {
            if (!currentFile) return;
            var $btn = $(this);
            var originalHtml = $btn.data('original-html') || $btn.html();
            var loadingHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" class="animate-spin" aria-hidden="true">' +
                '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"></circle>' +
                '<path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>' +
                '</svg>Loading...';
            $btn.data('original-html', originalHtml);
            $btn.prop('disabled', true).html(loadingHtml);
            var formData = new FormData();
            formData.append('trajax', 'parse_csv');
            formData.append('csvfile', currentFile);
            formData.append('onlycutoff', $('#json-onlycutoff').is(':checked') ? '1' : '0');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    var $el = $('#json-import-result').removeClass('hidden bg-red-50 bg-green-50 text-red-700 text-green-700');
                    if (res.success && res.type === 'json') {
                        var d = res.data;
                        var msg = '<strong>JSON import successful!</strong><br>' +
                            'Groups: ' + d.count_groups + ' &nbsp;|&nbsp; ' +
                            'Tests: ' + d.count_tests + ' &nbsp;|&nbsp; ' +
                            'Cutoffs: ' + d.count_cutoffs;
                        if (d.errors && d.errors.length)
                            msg += '<br><em class="text-red-600">' + d.errors.join('<br>') + '</em>';
                        $('#import-file-info, #import-json-options').addClass('hidden');
                        $('#import-dropzone').removeClass('hidden');
                        $('#import-csv-file').val('');
                        currentFile = null;
                        isJsonFile = false;
                        $el.addClass('bg-green-50 text-green-700').html(msg);
                    } else {
                        $el.addClass('bg-red-50 text-red-700').text(res.message || 'Import failed.');
                    }
                },
                error: function() {
                    $('#json-import-result').removeClass('hidden').addClass('bg-red-50 text-red-700').text('Request error.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
        // DO IMPORT
        $('#btn-do-import').on('click', function() {
            var $btn = $(this);
            var originalHtml = $btn.data('original-html') || $btn.html();
            var loadingHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" class="animate-spin" aria-hidden="true">' +
                '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"></circle>' +
                '<path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>' +
                '</svg>Loading...';
            $btn.data('original-html', originalHtml);
            $btn.prop('disabled', true).html(loadingHtml);

            // Collect maps
            var maps = {};
            $('#import-mapping-body select').each(function() {
                var name = $(this).attr('name'); // maps[i]
                var idx = name.match(/\[(\d+)\]/)[1];
                maps[idx] = $(this).val();
            });

            var payload = {
                trajax: 'do_import',
                group_id: $('#import-group-select').val(),
                ms: $('#import-ms').val() || '*',
                low: $('#import-vlow').val(),
                low: $('#import-low').val(),
                medium: $('#import-medium').val(),
                high: $('#import-high').val(),
                high: $('#import-vhigh').val(),
                replace: $('#import-replace').is(':checked') ? 1 : 0,
                dontnewtest: $('#import-dontnewtest').is(':checked') ? 1 : 0,
                maps: maps,
            };

            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: payload,
                success: function(res) {
                    var $result = $('#import-result').removeClass('hidden bg-red-50 bg-green-50 text-red-700 text-green-700');
                    if (res.success) {
                        $('#import-file-info, #import-config').addClass('hidden');
                        $('#import-dropzone').removeClass('hidden');
                        $('#import-csv-file').val('');
                        parsedHeaders = [];
                        parsedTotalRows = 0;
                        currentFile = null;
                        isJsonFile = false;
                        $result.addClass('bg-green-50 text-green-700')
                            .html('<strong>Import successful!</strong> ' + res.data.count + ' test(s) processed.' +
                                (res.data.errors.length ? '<br><em>' + res.data.errors.join('<br>') + '</em>' : ''));
                    } else {
                        $result.addClass('bg-red-50 text-red-700').text(res.message || 'Import failed.');
                    }
                },
                error: function() {
                    $('#import-result').removeClass('hidden').addClass('bg-red-50 text-red-700').text('Request error.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

    });
</script>