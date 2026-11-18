<?php
global $wpdb, $current_role, $ci_all_btypes;
global $is_ci_admin;

include(TRCPDD_PATH . 'inc/define_instruments.php');
include(TRCPDD_PATH . 'inc/define_apis.php');
if (defined('TRPDD_ARK') && TRPDD_ARK) {
    return include(trpdd_get_file_path('panel', 'edit_ark'));
}


$act        = isset($act) ? $act : '';
$tes_type   = isset($tes_type) ? $tes_type : 'tox';

$settings   = get_option('ci_req_settings_' . $tes_type, array());
$result_settings   = get_option('ci_result_settings_' . $tes_type, array());

$meta_type_array = array('reflex_groups'); //'bac_tests', 'mkr_tests',

if (isset($_POST['panel'])) {
    $test_ids       = array();
    $redirect_list  = false;
    $panel          = tr_pdd_stripslashes_deep($_POST['panel']);
    $panelmeta      = isset($_POST['panelmeta']) ? tr_pdd_stripslashes_deep($_POST['panelmeta']) : array();
    $te_id          = $panel['te_ID'];

    $columns        = $wpdb->get_col("explain ci_test_panels");

    $panel['te_name']       = (trim($panel['te_name']));
    $panel['te_basic']      = isset($panel['te_basic']) ? 1 : 0;
    $panel['te_standard']   = isset($panel['te_standard']) ? 1 : 0;
    $panel['te_highrisk']   = isset($panel['te_highrisk']) ? 1 : 0;
    $panel['te_comprehensive'] = isset($panel['te_comprehensive']) ? 1 : 0;
    $panel['te_in_req']     = isset($panel['te_in_req']) ? 1 : 0;
    $panel['te_nocheckbox'] = isset($panel['te_nocheckbox']) ? 1 : 0;
    $panel['te_reactive_result'] = isset($panel['te_reactive_result']) ? 1 : 0;
    $panel['te_is_detected'] = isset($panel['te_is_detected']) ? $panel['te_is_detected'] : 0;
    $panel['te_hide_pdf']   = isset($panel['te_hide_pdf']) ? 1 : 0;

    $panel['te_name']       = str_replace(',', ';', $panel['te_name']);


    if (isset($panel['te_range'])) {
        $panel['te_range'] = trpdd_replace_symbol_db($panel['te_range']);
    }
    if (isset($panel['te_desc'])) {
        $panel['te_desc'] = trpdd_replace_symbol_db($panel['te_desc']);
    }
    if (isset($panel['te_units'])) {
        $panel['te_units'] = trpdd_replace_symbol_db($panel['te_units']);
    }


    if ($tes_type == 'pcr' && $settings['enabel_test_control_by']) {
        $panel['te_control_bys'] = isset($panel['te_control_bys']) ? $panel['te_control_bys'] : array();
        if ($settings['hide_control_tests']) {
            foreach ($panel['te_control_bys'] as $ctid) {
                $wpdb->update('ci_test_panels', array('te_in_req' => 0), array('te_ID' => $ctid));
            }
        }
        $panel['te_control_bys'] = implode(',', $panel['te_control_bys']);
    }

    if (tr_get_option('ci_pdf_data', 'enable_reflex') || isset($panel['te_reflex'])) {
        if (!isset($panel['te_reflex_tests']) || !is_array($panel['te_reflex_tests'])) {
            $panel['te_reflex_tests'] = array();
        }
        if (!isset($panel['te_reflex_clinics']) || !is_array($panel['te_reflex_clinics'])) {
            $panel['te_reflex_clinics'] = array();
        }
        $panel['te_reflex_tests'] = implode(',', $panel['te_reflex_tests']);
        $panel['te_reflex_clinics'] = implode(',', $panel['te_reflex_clinics']);
        if (!$panel['te_reflex']) {
            $panel['te_reflex_tests'] = '';
            $panel['te_reflex_clinics'] = '';
        }
        unset($panel['te_reflex']);
    }


    $panel['te_relations'] = isset($panel['te_relations']) ? implode(',', $panel['te_relations']) : '';

    if ($tes_type == 'blood' && !empty($panel['te_calculation'])) {

        preg_match_all("/\[([^\]]+)\]/", $panel['te_calculation'], $matches);

        if (isset($matches[1]) && count($matches[1]) > 0) {

            $drugs_arr  = $matches[1];
            $relations = strlen($panel['te_relations']) > 0 ? explode(',', $panel['te_relations']) : array();

            foreach ($drugs_arr as $drug) {

                $testid = $wpdb->get_var("select te_ID 
                from ci_test_panels 
                where te_type='{$tes_type}'
                and te_abrev = '{$drug}'
                and te_in_req = 1
                and te_group_id = {$panel['te_group_id']} 
                ");

                if ($testid == 0) {
                    $testid = $wpdb->get_var("select te_ID from ci_test_panels 
                    where te_type ='{$tes_type}'
                        and te_abrev = '{$drug}'
                        and te_in_req = 1
                     ");
                }

                if ($testid > 0 && !in_array($testid, $relations)) {
                    $relations[] = $testid;
                }
            }

            $panel['te_relations'] = implode(',', $relations);
        }
    }

    if (isset($panel['te_conc_decimal']) && $panel['te_conc_decimal'] === '') {
        $panel['te_conc_decimal'] = '-1';
    }

    if (isset($panel['te_pdf_file_pos'])) {
        $result_pdf = tr_save_result_upload('covidpdf2pos', $_FILES['te_pdf_file_pos']);
        if ($result_pdf) {
            $panel['te_pdf_file_pos'] = $result_pdf;
        }
    }

    if (isset($panel['te_pdf_file_neg'])) {
        $result_pdf = tr_save_result_upload('covidpdf2neg', $_FILES['te_pdf_file_neg']);
        if ($result_pdf) {
            $panel['te_pdf_file_neg'] = $result_pdf;
        }
    }

    if (!empty($panel['new_group'])) {
        $g_exists = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = '{$panel['new_group']}'");
        if ($g_exists > 0) {
            $panel['te_group_id'] = $g_exists;
        } else {
            $wpdb->insert('ci_test_groups', array('tg_name' => $panel['new_group'], 'tg_type' => $tes_type));
            $panel['te_group_id'] = $wpdb->insert_id;
        }
    }
    unset($panel['new_group']);

    if (count($columns) > 0) {
        $has_col_notfound = false;
        foreach ($panel as $ck => $vl) {
            if (!in_array($ck, $columns) && !in_array($ck, array('te_names', 'new_group'))) {
                $has_col_notfound = true;
                break;
            }
        }
        if ($has_col_notfound) {
            include(TRCPDD_PATH . 'inc/install_options.php');
            foreach ($panel as $ck => $vl) {
                if (!in_array($ck, $columns) && !in_array($ck, array('te_names', 'new_group'))) {
                    unset($panel[$ck]);
                }
            }
        }
    }

    if ($te_id > 0) {
        $test_ids[] = $te_id;

        $old_panel = $wpdb->get_row("select * from ci_test_panels where te_ID = {$te_id} ", ARRAY_A);

        $rs = $wpdb->update('ci_test_panels', $panel, array('te_ID' => $te_id));

        if ($rs === false) {
            Tr_Session_Class::add_error('Error. update panel wrong to db!');
            echo $wpdb->last_error;
        } else {
            $changed_arr = array('id' => $te_id, 'name' => $panel['te_name']);
            foreach ($panel as $k => $vl) {
                $old_panel[$k] = str_replace('0000-00-00', '', $old_panel[$k]);

                if ($vl != $old_panel[$k] && !($old_panel[$k] == '0' && $vl === '')) {
                    if ($k == "te_color" && $vl == '#000000' && empty(($old_panel[$k]))) continue;
                    $changed_arr[$k] = array($old_panel[$k], $vl);
                    if ($k == 'te_abrev' && function_exists('tr_change_abrev')) {
                        tr_change_abrev($old_panel, $panel);
                    }
                }
            }

            if (count($changed_arr) > 2) {
                if (isset($changed_arr['te_color'])) {
                    trpdd_update_test_meta($te_id, 'color_changed', $panel['te_color']);
                    //check other test same name
                    $same_tests = $wpdb->get_results("select te_ID, te_color from ci_test_panels 
                    where te_name = '{$panel['te_name']}'  and te_type='{$tes_type}'
                    and te_ID != {$te_id} ");

                    foreach ($same_tests as $st) {
                        if ($st->te_color != $panel['te_color']) {
                            $wpdb->update('ci_test_panels', array('te_color' => $panel['te_color']), array('te_ID' => $st->te_ID));
                            trpdd_update_test_meta($st->te_ID, 'color_changed', $panel['te_color']);
                        }
                    }
                }
            }

            //fix result drug
            $result_tbl = tr_pdd_get_table_result_by_btype($tes_type);
            $drugs = array('te_name', 'te_abrev', 'te_abrev2');
            foreach ($drugs as $drugc) {
                if (isset($changed_arr[$drugc])) {
                    $newdrug = $changed_arr[$drugc][1];
                    $olddrug = $changed_arr[$drugc][0];
                    if (!empty($newdrug)) {
                        $wpdb->update(
                            $result_tbl,
                            array('drug' => $newdrug),
                            array('drug' => $olddrug, 'group_id' => $panel['te_group_id'])
                        );
                    }
                }
            }

            Tr_Session_Class::add('Updated panel Successfully!');
            if (isset($_POST['save_new'])) {
                $redirect_url = Tr_Base_Class_V6::link(array('act' => 'add', 'id' => ''), false);
            }
        }
    } else {

        $next_position = $wpdb->get_var("select max(te_position) 
	                        from ci_test_panels 
	                        where te_group_id = {$panel['te_group_id']}");

        if (!empty($panel['te_names'])) {
            $names = explode("\n", $panel['te_names']);
            if (isset($_GET['coma'])) {
                $names = explode(",", $panel['te_names']);
            }

            unset($panel['te_names']);
            $df_abrev = $panel['te_abrev'];

            foreach ($names as $name) {
                $name = trim($name);
                $abrev = '';

                if (empty($name)) continue;
                if (strpos($name, "\t") > 0) {
                    list($name, $abrev) = explode("\t", $name);
                } else if (strpos($name, '(') > 0) {
                    preg_match("/\(([^\)]+)\)/", $name, $matches);
                    if (isset($matches[1]) && !is_array($matches[1]) && strlen($matches[1]) > 2) {
                        $abrev = trim($matches[1]);
                    }
                }

                $panel['te_name'] = trim($name);
                $abrev = trim($abrev);
                $panel['te_abrev'] = !empty($abrev) ? $abrev : $df_abrev;
                $panel['te_position'] = ++$next_position;

                $wpdb->insert('ci_test_panels', $panel);
                $te_id = $wpdb->insert_id;
                $test_ids[] = $te_id;
            }
        } else {
            if (!isset($panel['te_position'])) {
                $next_position = $wpdb->get_var("select max(te_position) 
	                        from ci_test_panels 
	                        where te_group_id = {$panel['te_group_id']}");
                $panel['te_position'] = ++$next_position;
            }
            $wpdb->insert('ci_test_panels', $panel);
            $te_id = $wpdb->insert_id;
            $test_ids[] = $te_id;
        }

        if ($te_id > 0) {
            $changed_arr = array('id' => $te_id, 'name' => $panel['te_name']);

            Tr_Session_Class::add('Created New panel Successfully!');
            if (!isset($_POST['save_new']))
                $redirect_url = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);
        } else {
            Tr_Session_Class::add_error('Error.' . $wpdb->last_error);
        }
        $_POST['panel'] = array();
    }

    if (count($test_ids) > 0) {
        foreach ($test_ids as $te_id) {
            if (empty($te_id)) continue;

            //update meta
            if (!is_array($panelmeta)) {
                $panelmeta = array();
            }
            foreach ($meta_type_array as $mk) {
                if (!isset($panelmeta[$mk]) || !is_array($panelmeta[$mk])) {
                    $panelmeta[$mk] = array();
                }
            }

            if (is_array($panelmeta)) {
                foreach ($panelmeta as $mk => $mvl) {
                    if (is_array($mvl)) {
                        $mvl = implode(',', $mvl);
                    }
                    $existm = $wpdb->get_var("select id from ci_test_meta 
                    where test_id = {$te_id}
                    and meta_key='{$mk}' ");
                    $metaarr = array(
                        'test_id'    => $te_id,
                        'meta_key'   => $mk,
                        'meta_value' => $mvl
                    );
                    if ($existm > 0) {
                        $wpdb->update('ci_test_meta', $metaarr, array('id' => $existm));
                    } else {
                        $wpdb->insert('ci_test_meta', $metaarr);
                    }
                }
            }

            $old_cutoffs = $wpdb->get_results(
                "SELECT * FROM ci_test_panels_cutoffs WHERE cu_te_ID = {$te_id}",
                ARRAY_A
            );

            $wpdb->delete('ci_test_panels_cutoffs', array('cu_te_ID' => $te_id));

            $new_cutoffs = array();
            foreach ($_POST['cutoffs'] as $cut) {
                $cut['cu_te_ID'] = $te_id;
                $cut['cu_ms'] = str_replace(' ,', ',', $cut['cu_ms']);
                $cut['cu_ms'] = str_replace(', ', ',', $cut['cu_ms']);
                $cut['cu_ms'] = trim($cut['cu_ms']);

                if (isset($cut['cu_cutoff'])) {
                    $cut['cu_cutoff'] = trpdd_replace_symbol_db($cut['cu_cutoff']);
                }
                if (isset($cut['cu_medium'])) {
                    $cut['cu_medium'] = trpdd_replace_symbol_db($cut['cu_medium']);
                }
                if (isset($cut['cu_upper_limit'])) {
                    $cut['cu_upper_limit'] = trpdd_replace_symbol_db($cut['cu_upper_limit']);
                }
                if (isset($cut['cu_invalid'])) {
                    $cut['cu_invalid'] = trpdd_replace_symbol_db($cut['cu_invalid']);
                }
                if (isset($cut['cu_critical_low'])) {
                    $cut['cu_critical_low'] = trpdd_replace_symbol_db($cut['cu_critical_low']);
                }
                if (isset($cut['cu_critical_high'])) {
                    $cut['cu_critical_high'] = trpdd_replace_symbol_db($cut['cu_critical_high']);
                }
                if (isset($cut['cu_hqc'])) {
                    $cut['cu_hqc'] = trpdd_replace_symbol_db($cut['cu_hqc']);
                }

                if (isset($cut['cu_amp_score'])) {
                    $cut['cu_amp_score'] = trpdd_replace_symbol_db($cut['cu_amp_score']);
                }
                if (isset($cut['cu_cq'])) {
                    $cut['cu_cq'] = trpdd_replace_symbol_db($cut['cu_cq']);
                }

                if (empty($cut['cu_ms']) && empty($cut['cu_cutoff']) && empty($cut['cu_upper_limit']) && empty($cut['cu_critical_high']) && empty($cut['cu_medium'])) {
                    continue;
                }

                if (empty($cut['cu_ms'])) {
                    $cut['cu_ms'] = '*';
                }

                if (isset($cut['cu_age'])) {
                    $cut['cu_age'] = trim($cut['cu_age']);
                    $cut['cu_age'] = str_replace('  ', ' ', $cut['cu_age']);
                    $cut['cu_age'] = trpdd_replace_symbol_db($cut['cu_age']);
                }

                $cut['cu_btype']   = $tes_type;
                $cut['created']    = date_i18n('Y-m-d H:i:s');
                $cut['created_by'] = get_current_user_id();

                $rs = $wpdb->insert('ci_test_panels_cutoffs', $cut);
                if ($rs === false) {
                    var_dump($wpdb->last_error);
                }

                $new_cutoffs[] = array_filter(array(
                    'cu_ms'            => $cut['cu_ms']            ?? '',
                    'cu_cutoff'        => $cut['cu_cutoff']         ?? '',
                    'cu_upper_limit'   => $cut['cu_upper_limit']    ?? '',
                    'cu_medium'        => $cut['cu_medium']         ?? '',
                    'cu_critical_low'  => $cut['cu_critical_low']   ?? '',
                    'cu_critical_high' => $cut['cu_critical_high']  ?? '',
                    'cu_age'           => $cut['cu_age']            ?? '',
                    'cu_start'         => $cut['cu_start']          ?? '',
                    'cu_end'           => $cut['cu_end']            ?? '',
                ));

                //add instrument
                if (!empty($cut['cu_ms']) && $cut['cu_ms'] != '*' && $cut['cu_ms'] != 'all') {
                    $exist = $wpdb->get_var("select count(*) from ci_instruments where instrument = '{$cut['cu_ms']}' ");
                    if (!$exist) {
                        $wpdb->insert('ci_instruments', array('instrument' => $cut['cu_ms']));
                    }
                }
            }

            $old_cutoffs_clean = array_map(function ($c) {
                return array_filter(array(
                    'cu_ms'            => $c['cu_ms']            ?? '',
                    'cu_cutoff'        => $c['cu_cutoff']         ?? '',
                    'cu_upper_limit'   => $c['cu_upper_limit']    ?? '',
                    'cu_medium'        => $c['cu_medium']         ?? '',
                    'cu_critical_low'  => $c['cu_critical_low']   ?? '',
                    'cu_critical_high' => $c['cu_critical_high']  ?? '',
                    'cu_age'           => $c['cu_age']            ?? '',
                    'cu_start'         => $c['cu_start']          ?? '',
                    'cu_end'           => $c['cu_end']            ?? '',
                ));
            }, $old_cutoffs);

            if ($old_cutoffs_clean != $new_cutoffs) {
                $changed_arr['cutoffs'] = array($old_cutoffs_clean, $new_cutoffs);
            }

            $is_insert = !isset($old_panel);
            $log_action = $is_insert
                ? 'insert_test_' . $panel['te_type']
                : 'update_test_' . $panel['te_type'];

            if ($is_insert || count($changed_arr) > 2) {
                trpdd_log_user(get_current_user_id(), $log_action, $changed_arr, $te_id, '', '', '', $te_id);
            }
        }
    }


    if ($te_id > 0) {
        //drugs
        $drug_logics_post = isset($_POST['drug_logic']) ? $_POST['drug_logic'] : array();
        foreach ($drug_logics_post as $drug => $dl) {
            $dl['medication'] = $drug;
            $dl['anticipated'] = strtoupper($panel['te_name']);
            $dl['me_show_req'] = isset($dl['me_show_req']) ? 1 : 0;

            //check medication
            $me_ID = $wpdb->get_var("select me_ID from ci_medications where me_name ='{$drug}' ");

            if (!$me_ID) {
                $wpdb->insert('ci_medications', array('me_name' => $drug, 'me_show_req' => $dl['me_show_req']));
            } else {
                $wpdb->update('ci_medications', array('me_show_req' => $dl['me_show_req']), array('me_ID' => $me_ID));
            }
            unset($dl['me_show_req']);

            //check >
            $d_g = $dl['anticipated'] . ' > Cutoff';
            $exits = $wpdb->get_var("select id from ci_drug_logic WHERE medication='{$dl['medication']}' AND anticipated ='{$dl['anticipated']}' AND concent = '{$d_g}' ");
            if ($exits) {
                $wpdb->update('ci_drug_logic', $dl, array('id' => $exits));
            } else {
                $new = $dl;
                $new['concent'] = $d_g;
                $wpdb->insert('ci_drug_logic', $new);
            }

            //check <
            $d_g = $dl['anticipated'] . ' < Cutoff';
            $exits = $wpdb->get_var("select id from ci_drug_logic WHERE medication='{$dl['medication']}' AND anticipated ='{$dl['anticipated']}' AND concent = '{$d_g}' ");
            if ($exits) {
                $wpdb->update('ci_drug_logic', $dl, array('id' => $exits));
            } else {
                $new = $dl;
                $new['concent'] = $d_g;
                $wpdb->insert('ci_drug_logic', $new);
            }
        }

        if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'new_abx')) {
            //check abx type
            $groupp = $wpdb->get_row("select * from ci_test_groups 
                where tg_parent = 0 and tg_other_options like '%new_abx\":\"1%'
                and ( tg_ID = {$panel['te_group_id']} or tg_ID IN (select tg_parent from ci_test_groups where tg_ID = {$panel['te_group_id']})) ");
            if ($groupp) {
                //clear abxtbl cache
                $wpdb->query("update {$wpdb->options} set option_value = '0'  where option_name like '%ci_abx_results_scores_%' ");

                if (!isset($_POST['submitedit']) && !isset($_POST['submitnew'])) {
                    $redirect_url = Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'gr' => $groupp->tg_ID), false);
                }
            }
        }
    }


    $panel = array(
        'te_group_id' => $panel['te_group_id']
    );

    //add instrument to table
    wp_clear_scheduled_hook('ci_fix_db', array(0));
    wp_schedule_single_event(time() + 60, 'ci_fix_db', array(0));


    if (empty($redirect_url)) {
        if (isset($_REQUEST['submitnew'])) {
            $redirect_url = Tr_Base_Class_V6::link(array('act' => 'add', 'id' => ''), false);
        } else if (isset($_REQUEST['submitedit'])) {
            $redirect_url = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $te_id), false);
        }
    }


    if (isset($redirect_url) && $redirect_url) {
        echo '<meta http-equiv="refresh" content="0; url=' . $redirect_url . '">';
        Tr_Session_Class::show();
        exit;
    }
}

$panelmeta = array();
$cutoffs        = array();
$testsingroup   = array();
$is_rpp         = false;
if ($tes_type == 'pcr') {
    $is_rpp         = tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq');
}

if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {
    $id         = $_REQUEST['id'];
    $panel      = $wpdb->get_row("select te.*, g.* from ci_test_panels te
    JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
    where te_ID = {$id}", ARRAY_A);
    if (!empty($panel['te_type'])) {
        $wpdb->query($wpdb->prepare(
            "UPDATE ci_test_panels_cutoffs
            SET cu_btype = %s
            WHERE cu_te_ID = %d
            AND (cu_btype IS NULL OR cu_btype = '' OR cu_btype != %s)",
            $panel['te_type'],
            $id,
            $panel['te_type']
        ));
    }
    $cutoffs    = $wpdb->get_results(
        "select * ,
    CASE 
        WHEN POSITION('week' IN cu_age)>0 THEN CAST(REPLACE(cu_age,'>','99') as unsigned)
        WHEN POSITION('day' IN cu_age)>0 AND POSITION('month' IN cu_age)>0 THEN 200 +CAST(REPLACE(cu_age,'>','99') as unsigned) 
        WHEN POSITION('day' IN cu_age)>0 THEN 100 + CAST(REPLACE(cu_age,'>','99') as unsigned) 
        WHEN POSITION('month' IN cu_age)>0 AND POSITION('year' IN cu_age)>0 THEN 500 + CAST(REPLACE(cu_age,'>','99') as unsigned) 
        WHEN POSITION('month' IN cu_age)>0 THEN 400 + CAST(REPLACE(cu_age,'>','99') as unsigned) 
        WHEN POSITION('year' IN cu_age)>0 THEN 600 + CAST(REPLACE(cu_age,'>','99') as unsigned) 
        ELSE 800 + CAST(REPLACE(cu_age,'>','99') as unsigned) 
        END  as order_age
    from ci_test_panels_cutoffs 
    where cu_te_ID = {$id}
    order by cu_gender desc,order_age ASC , cu_start asc, created asc
    ",
        ARRAY_A
    );

    $main_group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$panel['te_group_id']} ");
    if ($main_group->tg_parent > 0) {
        $main_group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$main_group->tg_parent} ");
    }
    $tg_other_options = json_decode($main_group->tg_other_options, true);
    if (isset($tg_other_options['is_rpp']) && $tg_other_options['is_rpp'] == 2) {
        $is_rpp = false;
    } else if (isset($tg_other_options['is_rpp']) && $tg_other_options['is_rpp']) {
        $is_rpp = true;
    }

    $disable_rename = true;
    $g_parent = $wpdb->get_var("select tg_parent from ci_test_groups where tg_ID = {$panel['te_group_id']}");
    if ($g_parent == 0) $g_parent = $panel['te_group_id'];
    $testsingroup = $wpdb->get_results("select te_ID, te_name from ci_test_panels te
                                    JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
		                            where ( tg_ID = {$g_parent} or g.tg_parent = {$g_parent} )
		                            and te_ID != {$id}
                                    order by te_is_control desc, te_position asc");

    $metas = $wpdb->get_results("select * from ci_test_meta where test_id = {$id} ");
    foreach ($metas as $mt) {
        if (in_array($mt->meta_key, $meta_type_array)) {
            $mt->meta_value = explode(',', $mt->meta_value);
        }
        $panelmeta[$mt->meta_key] = $mt->meta_value;
    }

    foreach ($meta_type_array as $mk) {
        if (!isset($panelmeta[$mk])) {
            $panelmeta[$mk] = array();
        }
    }

    if ($act == 'duplicate') {
        $panel['te_group_id'] = 0;
        $id = 0;
    }
} else {
    $panel['te_in_req'] = 1;
    if (isset($_GET['gr'])) {
        $panel['te_group_id'] = $_GET['gr'];
    }
}
if (tr_current_role_in(array('administrator', 'AdminCIDW'))) {
    $disable_rename = false;
}
$has_gender = array('M' => false, 'F' => false);
$test_types_str = "'{$tes_type}'";
if (in_array($tes_type, array('blood', 'pcr')) && in_array('req', $ci_all_btypes)) {
    $test_types_str .= ",'req'";
    if (!in_array($tes_type, $ci_all_btypes)) {
        $test_types_str = " 'req' ";
    }
}
$groups = $wpdb->get_results("select *
                                from ci_test_groups t
                                where tg_type in ({$test_types_str}) and tg_parent = 0
                                order by tg_show_req desc, tg_name asc");

foreach ($groups as $i => $group) {
    $groups[$i]->childs = $wpdb->get_results(
        "select *
        from ci_test_groups where tg_parent = {$group->tg_ID} order by tg_position asc"
    );
}

$ref_labs = CI_Base_Lab::get_ref_labs(true);

?>
<div class="wrap">
    <h2>
        <?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Test <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Tests</a>
        <?php
        if ($act == 'edit') { ?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New Test</a>
        <?php
        }
        if (isset($panel['te_group_id']) && $panel['te_group_id'] > 0) {
        ?>
            <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'groups', 'act' => 'edit', 'id' => $panel['te_group_id'], 'type' => $panel['tg_type'], 'tab' => $panel['tg_type'])) ?>" class="add-new-h2">Edit Group</a>
        <?php
        }
        ?>
    </h2>

    <?php Tr_Session_Class::show() ?>

    <form method="post" id="drug_form" enctype="multipart/form-data">

        <?php
        if ($tes_type == 'dru') {
        ?>
            <div class="frow">
                <div class="col2">
                    <label>Name</label>
                    <?php if ($act == 'add') { ?>
                        <textarea name="panel[te_names]" required style="height: 200px;"><?php echo isset($panel['te_names']) ? $panel['te_names'] : '' ?></textarea>
                        <i>Add multi drugs by multi line</i>
                    <?php } else { ?>
                        <input type="text" maxlength="60" name="panel[te_name]" accept="" value="<?php echo isset($panel['te_name']) ? $panel['te_name'] : '' ?>" <?php if ($disable_rename) echo 'readonly' ?> required />
                    <?php } ?>
                </div>

            </div>
        <?php } else { ?>
            <div class="frow">
                <div class="col2">
                    <label>Name</label>
                    <?php if ($act == 'add') { ?>
                        <textarea name="panel[te_names]" required style="height: 200px;"><?php echo isset($panel['te_names']) ? $panel['te_names'] : '' ?></textarea>
                        <i>Add multi drugs by multi line</i>
                    <?php } else { ?>
                        <input type="text" name="panel[te_name]" value="<?php echo isset($panel['te_name']) ? $panel['te_name'] : '' ?>" <?php if ($disable_rename) echo 'readonly' ?> required />
                    <?php } ?>
                </div>
                <?php if (!in_array($tes_type, array('study'))) { ?>
                    <div class="col4">
                        <label>Group</label>
                        <select name="panel[te_group_id]" required>
                            <option value="">Select Group</option>
                            <?php
                            foreach ($groups as $group) {
                                $selected = isset($panel['te_group_id']) && $group->tg_ID == $panel['te_group_id'] ? 'selected="selected"' : '';
                                $ismarker = $group->tg_antibiotic || stripos($group->tg_name, 'antibiotic') !== false ? 1 : 0;
                                echo '<option value="' . $group->tg_ID . '" ' . $selected . ' data-ismarker="' . $ismarker . '">' . $group->tg_name . '</option>';

                                foreach ($group->childs as $child) {
                                    $selected = isset($panel['te_group_id']) && $child->tg_ID == $panel['te_group_id'] ? 'selected="selected"' : '';
                                    $ismarker = $child->tg_antibiotic || stripos($child->tg_name, 'antibiotic') !== false ? 1 : 0;
                                    echo '<option value="' . $child->tg_ID . '" ' . $selected . ' data-ismarker="' . $ismarker . '">- &nbsp;&nbsp;' . $child->tg_name . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>New Group</label>
                        <input type="text" name="panel[new_group]" value="<?php echo isset($panel['new_group']) ? $panel['new_group'] : '' ?>" />
                    </div>
                <?php } ?>

            </div>
            <div class="frow">
                <div class="col4">
                    <label>Abreviation</label>
                    <input type="text" name="panel[te_abrev]" value="<?php echo isset($panel['te_abrev']) ? $panel['te_abrev'] : '' ?>" maxlength="150">
                </div>
                <div class="col4">
                    <label>Abreviation 2</label>
                    <input type="text" name="panel[te_abrev2]" value="<?php echo isset($panel['te_abrev2']) ? $panel['te_abrev2'] : '' ?>" maxlength="150">
                </div>
                <div class="col4">
                    <label>SNOMED</label>
                    <input type="text" name="panel[te_snomed_code]" value="<?php echo isset($panel['te_snomed_code']) ? $panel['te_snomed_code'] : '' ?>" maxlength="100">
                </div>
                <div class="col4">
                    <label>Loinc Code</label>
                    <input type="text" name="panel[te_loinc_code]" value="<?php echo isset($panel['te_loinc_code']) ? $panel['te_loinc_code'] : '' ?>" maxlength="100">
                </div>

            </div>

            <div class="frow">

                <div class="col4">
                    <label>Label Barcode</label>
                    <input type="text" name="panel[te_label_barcode]" value="<?php echo isset($panel['te_label_barcode']) ? $panel['te_label_barcode'] : '' ?>" maxlength="10">
                </div>
                <div class="col4">
                    <label>Units</label>
                    <input type="text" name="panel[te_units]" value="<?php echo isset($panel['te_units']) ? trpdd_show_symbol($panel['te_units']) : '' ?>" maxlength="50">
                </div>
                <div class="col4">
                    <label>Range(only display)</label>
                    <input type="text" name="panel[te_range]" value="<?php echo isset($panel['te_range']) ? trpdd_show_symbol($panel['te_range']) : '' ?>" maxlength="50">
                </div>
                <div class="col4">
                    <label>Decimal</label>
                    <input type="number" name="panel[te_conc_decimal]" value="<?php
                                                                                if (isset($panel['te_conc_decimal']) && $panel['te_conc_decimal'] !== '-1') {
                                                                                    echo $panel['te_conc_decimal'];
                                                                                }
                                                                                ?>" maxlength="1">
                </div>

            </div>
            <div class="frow">
                <div class="col4">
                    <label>Well Number</label>
                    <input type="text" name="panel[te_well_numbers]" value="<?php echo isset($panel['te_well_numbers']) ? $panel['te_well_numbers'] : '' ?>">
                </div>
                <div class="col4">
                    <label>Only show for Ref Lab</label>
                    <?php tr_select_option('panel[te_ref_lab_ID]', $ref_labs, isset($panel['te_ref_lab_ID']) ? $panel['te_ref_lab_ID'] : '', array('addselect' => true)); ?>
                </div>
                <div class="col4">
                    <label>Not show for Ref Lab</label>
                    <?php tr_select_option('panel[te_not_ref_lab_ID]', $ref_labs, isset($panel['te_not_ref_lab_ID']) ? $panel['te_not_ref_lab_ID'] : '', array('addselect' => true)); ?>
                </div>
                <div class="col4">
                    <label>Color</label>
                    <input type="color" name="panel[te_color]" value="<?php echo isset($panel['te_color']) ? $panel['te_color'] : '' ?>">
                </div>
            </div>
            <?php
            if ($tes_type == 'pcr' && isset($result_settings['e_listby_plate']) && $result_settings['e_listby_plate']) {
            ?>
                <div class="frow">

                    <div class="col4">
                        <label>IDL slope</label>
                        <input type="text" name="panelmeta[idl_slope]" value="<?php echo isset($panelmeta['idl_slope']) ? $panelmeta['idl_slope'] : '' ?>" maxlength="10">
                    </div>
                    <div class="col4">
                        <label>IDL y-int</label>
                        <input type="text" name="panelmeta[idl_y_int]" value="<?php echo isset($panelmeta['idl_y_int']) ? $panelmeta['idl_y_int'] : '' ?>" maxlength="10">
                    </div>

                </div>
            <?php
            }

            if ($tes_type == 'pcr' && isset($testsingroup) && count($testsingroup) > 0) {
            ?>

                <div class="frow">
                    <div class="col4">
                        <label title="Control Negative => Test Invalid">Test Control Invalid:</label>
                        <select name="panel[te_control_by_ID]">
                            <option value="">Select Test</option>
                            <?php
                            foreach ($testsingroup as $teg) {
                                $selected = $teg->te_ID == $panel['te_control_by_ID'] ? 'selected' : '';
                                echo '<option value="' . $teg->te_ID . '" ' . $selected . '>' . $teg->te_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>Test Control INCONCLUSIVE:</label>
                        <select name="panel[te_inconclusive_ID]">
                            <option value="">Select Test</option>
                            <?php
                            $testsingroup_inc = $testsingroup;
                            $testsingroup_inc[] = (object)array('te_ID' => -1, 'te_name' => 'byself');
                            foreach ($testsingroup_inc as $teg) {
                                $selected = $teg->te_ID == $panel['te_inconclusive_ID'] ? 'selected' : '';
                                echo '<option value="' . $teg->te_ID . '" ' . $selected . '>' . $teg->te_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <?php

                    if (isset($settings['enabel_test_control_by']) && $settings['enabel_test_control_by']) {

                        $control_bys = explode(',', $panel['te_control_bys']);
                    ?>
                        <div class="col4">
                            <label>Control POS by Tests(any control POS then POS):</label>
                            <select name="panel[te_control_bys][]" multiple class="js_select2">
                                <?php
                                foreach ($testsingroup as $test) {
                                    $selected = in_array($test->te_ID, $control_bys) ? 'selected' : '';

                                    echo '<option value="' . $test->te_ID . '" ' . $selected . '>' . $test->te_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php
                    }

                    if (tr_get_option('ci_pdf_data', 'enable_tereflexgr')) {
                    ?>
                        <div class="col4">
                            <label>Reflex to Groups</label>
                            <select name="panelmeta[reflex_groups][]" class="js_select2" multiple>
                                <?php
                                foreach ($groups as $group) {

                                    $selected = in_array($group->tg_ID, $panelmeta['reflex_groups']) ? 'selected="selected"' : '';
                                    echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';

                                    foreach ($group->childs as $child) {
                                        $selected = in_array($child->tg_ID, $panelmeta['reflex_groups']) ? 'selected="selected"' : '';
                                        echo '<option value="' . $child->tg_ID . '" ' . $selected . '>- &nbsp;&nbsp;' . $child->tg_name . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    <?php
                    }
                    ?>

                </div>
            <?php
            }

            if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'new_abx') && in_array('bac_tests', $meta_type_array)) {
                $bac_options = $wpdb->get_results(
                    "select ID, 
                    bacteria_name as name 
                    from ci_abx_bacterias 
                    order by bacteria_name asc",
                    ARRAY_A
                );

                $mkr_options = $wpdb->get_results(
                    "select ID, 
                    resistance_marker as name 
                    from ci_abx_resistance_markers 
                    order by resistance_marker asc",
                    ARRAY_A
                );


            ?>
                <div class="frow tested_variants">
                    <label>Tested Variants</label>
                    <div class="bac_tests">
                        <input type="hidden" name="has_metas[bac_tests]" value="1" />
                        <select name="panelmeta[bac_tests][]" class="js_select2 " multiple>
                            <?php
                            if (!isset($panelmeta['bac_tests']) || !is_array($panelmeta['bac_tests'])) $panelmeta['bac_tests'] = array();
                            foreach ($bac_options as $abxarr) {
                                $selected = in_array($abxarr['ID'], $panelmeta['bac_tests']) ? 'selected' : '';
                                echo '<option value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mkr_tests">
                        <select name="panelmeta[mkr_tests][]" class="js_select2 " multiple>
                            <?php
                            if (!isset($panelmeta['mkr_tests']) || !is_array($panelmeta['mkr_tests'])) $panelmeta['mkr_tests'] = array();
                            foreach ($mkr_options as $abxarr) {
                                $selected = in_array($abxarr['ID'], $panelmeta['mkr_tests']) ? 'selected' : '';
                                echo '<option value="' . $abxarr['ID'] . '" ' . $selected . '>' . $abxarr['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <script>
                    jQuery(function($) {

                        $('body').on('change', '[name="panel[te_group_id]"], [name="panel[te_antibiotic]"]', function() {
                            var $isresi = $('[name="panel[te_antibiotic]"]').is(':checked');
                            var $group = $('[name="panel[te_group_id]"] option:selected');
                            var $gismarker = $group ? $group.data('ismarker') : false;
                            if ($isresi || $gismarker) {
                                $('.bac_tests').hide();
                                $('.bac_tests select').val('').change();
                                $('.mkr_tests').show();
                            } else {
                                $('.bac_tests').show();
                                $('.mkr_tests').hide();
                                $('.mkr_tests select').val('').change();
                            }
                        })
                        $('[name="panel[te_group_id]"]').change();

                    })
                </script>
                <?php
            }


            if ($tes_type == 'blood') {


                $has_style2 = tr_get_option('ci_pdf_data', 'blood_pdf_style') == 2;
                if (!$has_style2) {
                    $has_style2 = $wpdb->get_var("select count(*) from ci_test_groups where tg_type = 'blood' 
                    and tg_blood_style = 2");
                }

                if ($has_style2) {
                ?>
                    <div class="frow">
                        <div class="col4">
                            <label>Normal Population</label>
                            <input type="text" name="panel[te_cutoff]" value="<?php echo isset($panel['te_cutoff']) ? $panel['te_cutoff'] : '' ?>" maxlength="10">
                        </div>
                        <div class="col4">
                            <label>SS Steady State</label>
                            <input type="text" name="panel[te_upper_limit]" value="<?php echo isset($panel['te_upper_limit']) ? $panel['te_upper_limit'] : '' ?>" maxlength="10">
                        </div>
                        <div class="col4">
                            <label>Add Comparison Symbol</label>
                            <input type="text" name="panel[te_comparison_symbol]" value="<?php echo isset($panel['te_comparison_symbol']) ? $panel['te_comparison_symbol'] : '' ?>" maxlength="50">
                        </div>
                    </div>
                <?php } ?>

                <?php
                $i_instrpos = 0;
                $showed_keys = array();
                $define_instruments = array_merge($define_instruments, $define_apis);
                foreach ($define_instruments as $ins => $iarr) {
                    if (isset($iarr['add_fields']) && is_array($iarr['add_fields'])) {
                        foreach ($iarr['add_fields'] as $tf) {
                            $passbtype = is_array($iarr['btype']) ? in_array($tes_type, $iarr['btype']) : $tes_type == $iarr['btype'];
                            if ($passbtype && tr_get_option('ci_pdf_data', $ins) && strpos($tf, 'te_') === 0 && !in_array($tf, $showed_keys)) {

                                $showed_keys[] = $tf;
                                $i_instrpos++;
                                if ($i_instrpos % 4 == 1) {
                                    if ($i_instrpos == 1) {
                                        echo '<br><hr/>';
                                    } else {
                                        echo '</div>';
                                    }
                                    echo '<div class="frow">';
                                }


                ?>
                                <div class="col4">
                                    <label><?php echo $iarr['name'] ?></label>
                                    <input type="text" name="panel[<?php echo $tf ?>]" value="<?php echo isset($panel[$tf]) ? $panel[$tf] : '' ?>" maxlength="50">
                                </div>
                <?php
                            }
                        }
                    }
                }
                if ($i_instrpos > 0) {
                    echo '</div>';
                }



                $sptypes = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$tes_type}' order by ts_ID asc");
                if (empty($panel['te_type_id'])) {
                    //default to serum
                    foreach ($sptypes as $sptype) {
                        if ($sptype->ts_name == 'Serum') {
                            $panel['te_type_id'] = $sptype->ts_ID;
                            if (isset($panel['te_ID']) && $panel['te_ID'] > 0) {
                                //update test panel
                                $wpdb->update('ci_test_panels', array('te_type_id' => $panel['te_type_id']), array('te_ID' => $panel['te_ID']));
                            }
                            break;
                        }
                    }
                }
                ?>
                <div class="frow">
                    <div class="col4">
                        <label>Test Type</label>
                        <select name="panel[te_type_id]">
                            <option value="">Select Type</option>
                            <?php
                            $showed_types = array();
                            foreach ($sptypes as $sptype) {
                                if ($sptype->ts_name == 'Blood') {
                                    $sptype->ts_name = 'Whole Blood';
                                }
                                if (in_array($sptype->ts_name, $showed_types)) {
                                    continue;
                                } else {
                                    $showed_types[] = $sptype->ts_name;
                                }
                                $selected = $sptype->ts_ID == $panel['te_type_id'] ? 'selected="selected"' : '';
                                echo '<option value="' . $sptype->ts_ID . '" ' . $selected . '>' . $sptype->ts_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>Result Values</label>
                        <input type="text" name="panel[te_result_values]" value="<?php echo $panel['te_result_values'] ?>">
                    </div>
                    <?php
                    if (in_array('req', $ci_all_btypes)) {
                    ?>
                        <div class="col4">
                            <label>Optimal (score)</label>
                            <input type="text" name="panel[te_optimal_value]" value="<?php echo $panel['te_optimal_value'] ?>">
                        </div>
                        <div class="col4">
                            <label>ref nax(score)</label>
                            <input type="text" name="panel[te_ref_max]" value="<?php echo $panel['te_ref_max'] ?>">
                        </div>
                    <?php
                    }
                    if (tr_get_option('ci_pdf_data', 'enable_tereflexgr')) {
                    ?>
                        <div class="col4">
                            <label>Reflex to Groups</label>
                            <select name="panelmeta[reflex_groups][]" class="js_select2" multiple>
                                <?php
                                if (!is_array($panelmeta['reflex_groups'])) $panelmeta['reflex_groups'] = array();
                                foreach ($groups as $group) {

                                    $selected = in_array($group->tg_ID, $panelmeta['reflex_groups']) ? 'selected="selected"' : '';
                                    echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';

                                    foreach ($group->childs as $child) {
                                        $selected = in_array($child->tg_ID, $panelmeta['reflex_groups']) ? 'selected="selected"' : '';
                                        echo '<option value="' . $child->tg_ID . '" ' . $selected . '>- &nbsp;&nbsp;' . $child->tg_name . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    <?php
                    }
                    ?>
                </div>
                <?php
                $groups = $wpdb->get_results("select *
                from ci_test_groups t
                where tg_type='{$tes_type}' and tg_parent = 0
                order by tg_show_req desc, tg_name asc");
                $te_relations = explode(',', $panel['te_relations']);
                ?>
                <div class="frow">
                    <label>Additional Tests Req</label>
                    <select name="panel[te_relations][]" multiple class="js_select2">
                        <?php
                        foreach ($groups as $group) {
                            $all_tests = $wpdb->get_results("select te_ID, te_name, te_abrev from ci_test_panels
                                join ci_test_groups g on te_group_id = g.tg_ID
                                where te_type = '{$tes_type}' 
                                and (tg_ID = {$group->tg_ID} or tg_parent = {$group->tg_ID} )
                                and te_ID != " . (isset($panel['te_ID']) ? $panel['te_ID'] : 0) . "
                                and te_in_req = 1
                                order by te_name asc
                                ");

                            echo '<optgroup label="' . $group->tg_name . '">';
                            foreach ($all_tests as $test) {

                                $selected = in_array($test->te_ID, $te_relations) ? 'selected' : '';

                                echo '<option value="' . $test->te_ID . '" ' . $selected . '>' . $test->te_name . ' - [' . $test->te_abrev . '] (' . $group->tg_name . ')' . '</option>';
                            }
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                </div>
                <div class="frow">
                    <label>Calculation Value(Ratio)=</label>
                    <input type="text" name="panel[te_calculation]" value="<?php echo isset($panel['te_calculation']) ? htmlentities($panel['te_calculation']) : '' ?>" />

                    <div class="col3">
                        <div class="small">Use Abrev as short code ex: [BCG]</div>
                    </div>
                    <div class="col3">
                        <div class="small">Female: [female] ... [/female]</div>
                        <div class="small">Male: [male] ... [/male]</div>
                        <div class="small">Gender: [gender female="0.1" male="0.2"]</div>
                    </div>
                    <div class="col3">
                        <div class="small">Age: [age]</div>
                    </div>
                </div>
                <?php
            } //end blood

            else if ($tes_type == 'pcr') {
                $i_instrpos = 0;
                $showed_keys = array();
                $define_instruments = array_merge($define_instruments, $define_apis);
                foreach ($define_instruments as $ins => $iarr) {
                    if (isset($iarr['add_fields']) && is_array($iarr['add_fields'])) {
                        foreach ($iarr['add_fields'] as $tf) {
                            $passbtype = is_array($iarr['btype']) ? in_array($tes_type, $iarr['btype']) : $tes_type == $iarr['btype'];
                            if ($passbtype && tr_get_option('ci_pdf_data', $ins) && strpos($tf, 'te_') === 0 && !in_array($tf, $showed_keys)) {

                                $showed_keys[] = $tf;
                                $i_instrpos++;
                                if ($i_instrpos % 4 == 1) {
                                    if ($i_instrpos == 1) {
                                        echo '<br><hr/>';
                                    } else {
                                        echo '</div>';
                                    }
                                    echo '<div class="frow">';
                                }


                ?>
                                <div class="col4">
                                    <label><?php echo $iarr['name'] ?></label>
                                    <input type="text" name="panel[<?php echo $tf ?>]" value="<?php echo isset($panel[$tf]) ? $panel[$tf] : '' ?>" maxlength="50">
                                </div>
            <?php
                            }
                        }
                    }
                }
                if ($i_instrpos > 0) {
                    echo '</div>';
                }
            } //end pcr
            ?>

            <hr />

            <div class="frow">
                <label>Description</label>
                <textarea name="panel[te_desc]"><?php echo isset($panel['te_desc']) ? trpdd_show_symbol($panel['te_desc']) : '' ?></textarea>
            </div>

            <?php
            if (in_array($tes_type, array('pcr', 'blood'))) {
            ?>
                <hr />
                <div class="frow">
                    <div class="col3">
                        <label>Comment for Result POS</label>
                        <textarea name="panel[te_comments]"><?php echo isset($panel['te_comments']) ? $panel['te_comments'] : '' ?></textarea>
                    </div>
                    <div class="col3">
                        <label>Comment for Result Indeterminate</label>
                        <textarea name="panel[te_comments_ind]"><?php echo isset($panel['te_comments_ind']) ? $panel['te_comments_ind'] : '' ?></textarea>

                    </div>
                    <div class="col3">
                        <label>Comment for Result Neg</label>
                        <textarea name="panel[te_comments_neg]"><?php echo isset($panel['te_comments_neg']) ? $panel['te_comments_neg'] : '' ?></textarea>

                    </div>
                </div>
                <div class="frow">
                    <label>Reportable Note</label>
                    <textarea name="panel[te_reportable_note]"><?php echo isset($panel['te_reportable_note']) ? $panel['te_reportable_note'] : '' ?></textarea>
                </div>
                <div class="frow">
                    <div class="col2">
                        <label>Note for Result POS (end of pdf)</label>
                        <textarea name="panel[te_pdf_note_pos]"><?php echo isset($panel['te_pdf_note_pos']) ? $panel['te_pdf_note_pos'] : '' ?></textarea>
                    </div>
                    <div class="col2">
                        <label>Note for Result Neg (end of pdf)</label>
                        <textarea name="panel[te_pdf_note_neg]"><?php echo isset($panel['te_pdf_note_neg']) ? $panel['te_pdf_note_neg'] : '' ?></textarea>

                    </div>
                </div>
                <div class="frow">
                    <div class="col2">
                        <label>Add pdf file for Result POS</label>
                        <div class="card_file">
                            <a class="button selectfile" href="#">Upload PDF</a>

                            <input type="file" name="te_pdf_file_pos" style="display:none" />
                            <input type="hidden" name="panel[te_pdf_file_pos]" value="<?php echo isset($panel['te_pdf_file_pos']) ? $panel['te_pdf_file_pos'] : '' ?>" />
                            <?php
                            if (!empty($panel['te_pdf_file_pos'])) {
                                $result_dir = tr_get_result_upload_dir();
                                $te_pdf_file_pos = $result_dir['url'] . '/' . $panel['te_pdf_file_pos'];
                            ?>
                                <span><a href="<?php echo $te_pdf_file_pos ?>" class="te_pdf_file_pos_view" target="_blank">View PDF</a></span>
                                <input type="button" class="button remove_te_pdf_file_pos" value="Remove" />
                                <script>
                                    jQuery(function($) {
                                        $('.remove_te_pdf_file_pos').click(function() {
                                            $('[name="panel[te_pdf_file_pos]"]').val('');
                                            $('.te_pdf_file_pos_view').remove();
                                        })
                                    })
                                </script>
                            <?php
                            } else {
                                echo '<span></span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col2">
                        <label>Add pdf file for Result NEG</label>

                        <div class="card_file">
                            <a class="button selectfile" href="#">Upload PDF</a>

                            <input type="file" name="te_pdf_file_neg" style="display:none" />
                            <input type="hidden" name="panel[te_pdf_file_neg]" value="<?php echo isset($panel['te_pdf_file_neg']) ? $panel['te_pdf_file_neg'] : '' ?>" />
                            <?php
                            if (!empty($panel['te_pdf_file_neg'])) {
                                $result_dir = tr_get_result_upload_dir();
                                $te_pdf_file_neg = $result_dir['url'] . '/' . $panel['te_pdf_file_neg'];
                            ?>
                                <span><a href="<?php echo $te_pdf_file_neg ?>" class="te_pdf_file_neg_view" target="_blank">View PDF</a></span>
                                <input type="button" class="button remove_te_pdf_file_neg" value="Remove" />
                                <script>
                                    jQuery(function($) {
                                        $('.remove_te_pdf_file_neg').click(function() {
                                            $('[name="panel[te_pdf_file_neg]"]').val('');
                                            $('.te_pdf_file_neg_view').remove();
                                        })
                                    })
                                </script>
                            <?php
                            } else {
                                echo '<span></span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php
            }


            include('cutoffs.php');

            if ($tes_type == 'tox') {
                $drug_logics = array();
                $drugs_list  = $wpdb->get_col("select me_name from ci_medications order by me_name asc");
                if (isset($panel['te_ID']) && $panel['te_ID'] > 0) {
                    $drug_logics = $wpdb->get_results("
                        select dl.*,cu.cu_cutoff, me.me_show_req
                        from ci_drug_logic dl 
                        JOIN ci_test_panels te ON te.te_name = dl.anticipated
                        JOIN ci_test_panels_cutoffs cu ON cu.cu_te_ID = te.te_ID
                        left join ci_medications me ON me.me_name = dl.medication
                            WHERE te.te_ID = '{$panel['te_ID']}' 
                            GROUP BY dl.medication,dl.anticipated", ARRAY_A);
                }

            ?>

                <div class="frow blabel">
                    <div class="col6">
                        <h4>Drug</h4>
                    </div>
                    <div class="col6">
                        <h4>Detection Window - Urine</h4>
                    </div>
                    <div class="col6">
                        <h4>Detection Window - Oral </h4>
                    </div>
                    <div class="col3">
                        <h4>Comment</h4>
                    </div>
                </div>
                <div id="drug_detections" class="frow">
                    <?php
                    foreach ($drug_logics as $logic) {
                        include(trpdd_get_file_path('panel', 'row_test_logic'));
                    }
                    ?>
                </div>
                <div class="frow">
                    <input type="text" id="drug_text" style="width:300px" />
                    <a class="add_drug button">Add Drug</a>
                    <small>you can add multi by comma</small>
                </div>
                <br><br>
        <?php
            }
        } //end not dru
        ?>

        <?php


        if (!in_array($tes_type, array('study'))) {
            if (!isset($panel['te_is_detected']) && $tes_type == 'pcr') {
                $panel['te_is_detected'] = '1';
            }
        ?>
            <div class="frow">
                <h4>Other Options</h4>
            </div>
            <div class="col checkboxes" style="margin:10px 0">

                <select name="panel[te_is_detected]">
                    <?php
                    foreach (CI_Base_Panel::getPosNegType($tes_type) as $k => $txt) {
                        $selected = $panel['te_is_detected'] == $k ? 'selected' : '';
                        echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                    }
                    ?>
                </select>
                &nbsp;&nbsp;&nbsp;

                <label><input type="checkbox" name="panel[te_hide_pdf]" value="1" <?php checked(isset($panel['te_hide_pdf']) ? $panel['te_hide_pdf'] : '') ?> /> Hide in pdf</label>

                <?php
                if ($tes_type == 'pcr') {

                    if ($is_ci_admin) {
                ?>
                        <input type="hidden" name="panel[te_antibiotic]" value="0" />
                        <label><input type="checkbox" name="panel[te_antibiotic]" value="1" <?php checked(isset($panel['te_antibiotic']) ? $panel['te_antibiotic'] : '') ?> /> Antibiotic Resistance</label>
                    <?php } ?>

                    <input type="hidden" name="panel[te_is_control]" value="0" />
                    <label><input type="checkbox" name="panel[te_is_control]" value="1" <?php checked(isset($panel['te_is_control']) ? $panel['te_is_control'] : '') ?> /> Is control </label>

                    <?php
                    if ($user->user_login == 'Administrat0r') { ?>
                        <select name="panel[te_font_style]">
                            <?php
                            $fonts = array(0 => 'Normal', 1 => 'Italic', 2 => 'Bold');
                            foreach ($fonts as $fk => $ft) {
                                $selected = isset($panel['te_font_style']) && $fk == $panel['te_font_style'] ? 'selected' : '';
                                echo '<option value="' . $fk . '" ' . $selected . '>' . $ft . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>
                <?php
                }
                if ($tes_type == 'tox') {
                ?>
                    <label><input type="checkbox" name="panel[te_basic]" value="1" <?php checked(isset($panel['te_basic']) ? $panel['te_basic'] : 0) ?> /> Low Risk</label>
                    <label><input type="checkbox" name="panel[te_standard]" value="1" <?php checked(isset($panel['te_standard']) ? $panel['te_standard'] : 0) ?> /> Moderate Risk</label>
                    <label><input type="checkbox" name="panel[te_highrisk]" value="1" <?php checked(isset($panel['te_highrisk']) ? $panel['te_highrisk'] : 0) ?> /> High Risk</label>
                    <label><input type="checkbox" name="panel[te_comprehensive]" value="1" <?php checked(isset($panel['te_comprehensive']) ? $panel['te_comprehensive'] : 0) ?> /> Comprehensive</label>
                <?php
                }
                ?>
            </div>
        <?php
        } //not study
        ?>
        <div class="frow ilabel" style="background:#ddd;padding:2px 10px">
            <span>Start Date: </span>
            <input type="text" name="panel[te_start]" value="<?php if (isset($panel['te_start']) && $panel['te_start'] > '1000-01-01') echo $panel['te_start'] ?>" class="hasdate" autocomplete="off" data-write="1" style="width:120px" />
            &nbsp;&nbsp;
            <span>End Date: </span>
            <input type="text" <?php if (empty($panel['te_ID'])) echo 'disabled' ?> name="panel[te_end]" value="<?php if (isset($panel['te_end']) && $panel['te_end'] > '1000-01-01') echo $panel['te_end'] ?>" class="hasdate" autocomplete="off" data-write="1" style="width:120px" />
            &nbsp;&nbsp;
            <label style="float:none;width:auto;font-weight: bold"><input type="checkbox" name="panel[te_in_req]" value="1" <?php checked(isset($panel['te_in_req']) ? $panel['te_in_req'] : '') ?> /> Show In Req</label>
            &nbsp;&nbsp;
            <label style="float:none;width:auto;"><input type="checkbox" name="panel[te_nocheckbox]" value="1" <?php checked(isset($panel['te_nocheckbox']) ? $panel['te_nocheckbox'] : '') ?> /> No Checkbox</label>
        </div>


        <?php
        if (tr_get_option('ci_pdf_data', 'enable_reflex') || tr_get_option('ci_result_settings_' . $tes_type, 'enable_ref_postest')) {
            $panel['te_reflex_tests']   = explode(',', $panel['te_reflex_tests']);
            $panel['te_reflex_clinics'] = explode(',', $panel['te_reflex_clinics']);
            foreach ($panel['te_reflex_tests'] as $k => $vl) {
                if (empty($vl)) {
                    unset($panel['te_reflex_tests'][$k]);
                }
            }
            foreach ($panel['te_reflex_clinics'] as $k => $vl) {
                if (empty($vl)) {
                    unset($panel['te_reflex_clinics'][$k]);
                }
            }

            $panel['te_reflex'] = !empty($panel['te_reflex_tests']) || !empty($panel['te_reflex_clinics']);


            if ($panel['te_ID'] > 0) {
                $reflex_by_arr = array();
                $reflex_bys = $wpdb->get_results("select te_ID, te_name from ci_test_panels
					where find_in_set({$panel['te_ID']}, te_reflex_tests) 
					and te_type = '{$tes_type}' ");
                foreach ($reflex_bys as $refb) {
                    $reflex_by_arr[] = '[<a href="' . Tr_Base_Class_V6::link(array('id' => $refb->te_ID), false) . '">' . $refb->te_name . '</a>]';
                }
                if (count($reflex_by_arr) > 0) {
                    echo '<lable>Reflexed by: </label>' . implode(', ', $reflex_by_arr);
                    echo '<br><br>';
                }
            }

        ?>
            <div style="background:#fefefe;padding:5px">
                <div class="frow">
                    <input type="hidden" name="panel[te_reflex]" value="0" />
                    <label>
                        <input type="checkbox" id="te_reflex" name="panel[te_reflex]" value="1" <?php checked($panel['te_reflex']) ?> />Reflex</label>
                </div>

                <div class="frow" id="reflex_div" <?php if (!$panel['te_reflex']) echo 'style="display:none"' ?>>
                    <div class="col2">
                        <label>Reflex Tests</label><br>
                        <select class="js_select2" name="panel[te_reflex_tests][]" style="width:100%" multiple class="njs_select2">
                            <?php
                            $group_parentid = $wpdb->get_var("select tg_parent from ci_test_groups
								where tg_ID = {$panel['te_group_id']} ");
                            if ($group_parentid == 0) {
                                $group_parentid = $panel['te_group_id'];
                            }
                            $groups_refs = $wpdb->get_results("select tg_ID,tg_name from ci_test_groups
								where tg_ID = {$group_parentid} 
								or tg_parent = {$group_parentid} ");

                            foreach ($groups_refs as $groups_ref) {
                                $tests = $wpdb->get_results("select te_ID, te_name 
									from ci_test_panels 
									where te_group_id = {$groups_ref->tg_ID}
									and te_in_req = 1");

                                if (count($tests) == 0) continue;
                                echo '<optgroup label="' . $groups_ref->tg_name . '">';
                                foreach ($tests as $t) {
                                    $key = $t->te_ID;
                                    $selected = in_array($key, $panel['te_reflex_tests']) ? 'selected' : '';
                                    echo '<option value="' . $key . '" ' . $selected . '>' . $t->te_name . '</option>';
                                }
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col2">
                        <label>Reflex Clinics</label><br>
                        <select class="js_select2" name="panel[te_reflex_clinics][]" style="width:100%" multiple class="njs_select2">
                            <?php
                            $clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where cli_status=1 order by cli_clinic");

                            foreach ($clinics as $clinic) {
                                $selected = in_array($clinic->cID, $panel['te_reflex_clinics']) ? 'selected' : '';
                                echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <br>
        <?php
        }

        ?>

        <div class="frow" style="margin-top:20px">
            <input type="submit" class="button-primary" name="submit" value="Submit" />
            <input type="submit" class="button-primary" name="submitnew" value="Submit & Add New" />
            <input type="submit" class="button-primary" name="submitedit" value="Submit & Edit" />
            <input type="hidden" name="panel[te_ID]" value="<?php if ($act != 'duplicate' && isset($panel['te_ID'])) echo $panel['te_ID'] ?>" />
            <input type="hidden" name="panel[te_type]" value="<?php echo (!empty($panel['te_type'])) ? $panel['te_type'] : $tes_type ?>" />
        </div>

    </form>
</div>

<div id="add_multilines" style="display: none;">
    <textarea id="multilinebox" style="width:98%;height:200px" placeholder="AGE[Tab]Range"></textarea>
    <input type="button" class="button add_multiline_btn" value="Add" />
</div>
<style>
    li[data-gender="M"] {
        background: #ededae;
    }

    li[data-gender="F"] {
        background: #b7dcb3;
    }

    .checkboxes label {
        margin-right: 15px;
    }

    .pdoctorrow input[type="text"],
    .pdoctorrow input[type="search"] {
        max-width: 100%;
    }

    body .frow>[class*="col"] {
        margin-right: 0.2%;
    }

    .frow h4 {
        text-align: center;
        padding: 0;
    }
</style>
<script>
    jQuery(function($) {
        $('body').on('change', '[name="panel[new_group]"]', function() {
                if ($(this).val() != '') {
                    $('[name="panel[te_group_id]"]').removeAttr('required');
                } else {
                    $('[name="panel[te_group_id]"]').attr('required', 'required');
                }
            })
            .on('click', 'a.add_drug', function() {
                var $drugs = $('#drug_text').val();
                $('#drug_text').val('');
                $oldrugs = [];
                $('#drug_detections .drug_name').each(function() {
                    $oldrugs.push($(this).html());
                })
                if ($drugs != '') {
                    $this = $(this);
                    $this.html('Addding...');
                    $.ajax({
                        data: {
                            tr_action: 'ci_add_drugs_to_test',
                            drugs: $drugs,
                            olds: $oldrugs
                        },
                        type: 'post',
                        success: function(rs) {
                            $new = $(rs);
                            $('input.detected', $new).val($('input.detected:first').val());
                            $('input.detected_oral', $new).val($('input.detected_oral:first').val());
                            $('textarea.comments', $new).val($('textarea.comments:first').val());
                            $('#drug_detections').append($new);
                            $this.html('Add Drug');
                        }
                    })
                }
            })
            .on('change', 'input.detected', function() {
                $('input.detected').val($(this).val());
            })
            .on('change', 'input.detected_oral', function() {
                $('input.detected_oral').val($(this).val());
            })
            .on('click', 'a.duplicate_gender', function() {
                var $btnadd = $('a.add_doctor_row');
                var $gender = $(this).data('gender');
                $('#doctors_dv li').each(function() {
                    var $cr = parseInt($btnadd.data('id'));
                    var $new = '<li class="frow blabel pdoctorrow ">' + $(this).html() + '</li>';
                    $new = $new.replace(/\[\d*\]/g, '[' + $cr + ']');
                    $new = $($new);
                    $('#doctors_dv').append($new);
                    $('select[name*="cu_gender"]', $new).val($gender).change();
                    $('.hasdate', $new).removeClass('hasDatepicker').each($.tr_setup_datef);
                    $btnadd.data('id', $cr + 1);
                    $("#doctors_dv").trigger("new_row");
                })
                $(this).remove();
            })
            .on('change', '[name="panel[te_name]"]', function() {
                if ($(this).val().indexOf(',') > -1) {
                    $(this).addClass('error');
                    alert('Test Name not allow comma');
                } else {
                    $(this).removeClass('error');
                }
            })
            .on('submit', 'form#drug_form', function() {
                if ($('input.error,textarea.error', $(this)).length > 0) {
                    alert('Please check form');
                    return false;
                }
            })
            .on('change', 'select[name*="cu_gender"]', function() {
                var $pr = $(this).parents('li.pdoctorrow');
                $pr.data('gender', $(this).val()).attr('data-gender', $(this).val());
            })
            .on('click', 'a.make_all_male', function() {
                $('#doctors_dv li select[name*="cu_gender"]').each(function() {
                    if ($(this).val() == '') {
                        $(this).val('M').change();
                    }
                })
            })
            .on('keyup', '#doctors_dv input', function(e) {
                if (e.which == 40 || e.which == 38) {
                    var $tname = $(this).attr('name');
                    var $pr = $(this).parents('li');
                    var $next = e.which == 40 ? $pr.next('li') : $pr.prev('li');
                    console.log($next);
                    if ($next) {
                        $tname = $tname.replace(/.*\[\d*\]/g, '');
                        console.log($tname);
                        $('input[name*="' + $tname + '"]', $next).focus().select();
                    }
                }
            })
            .on('click', '.add_multiline_btn', function() {
                var $text = $('#multilinebox').val();
                $('#multilinebox').val('');
                var $lines = $text.split("\n");
                var $icount = 0;
                var $li = $('#doctors_dv li:last-child');
                var $gender = $('select[name*="cu_gender"]', $li).val();
                for (var i in $lines) {
                    var $line = $lines[i].trim();
                    $line = $line.replace(/\t\t/g, "\t");
                    if ($line == '') continue;

                    var $arr = $line.split("\t");
                    console.log($arr);
                    var $age = $arr[0];
                    var $range = $arr[1];
                    var $arr2 = $range.split('-');
                    var $low = '<' + $arr2[0].trim();
                    var $high = '>' + $arr2[1].trim();
                    if ($age && $range) {
                        $icount++;
                        if ($icount > 1) {
                            $('.add_doctor_row').click();
                        }
                        var $li = $('#doctors_dv li:last-child');
                        $('input[name*="cu_age"]', $li).val($age);
                        $('input[name*="cu_cutoff"]', $li).val($low);
                        $('input[name*="cu_upper_limit"]', $li).val($high);
                        if ($gender) {
                            $('select[name*="cu_gender"]', $li).val($gender);
                        }
                    }

                    console.log($age + "=> " + $range);

                }
                tb_remove();
            }).on('change', '#te_reflex', function() {
                if ($(this).is(':checked')) {
                    $('#reflex_div').show();
                } else {
                    $('#reflex_div').hide();
                }
            })


        $('#drug_text').autocomplete({
            source: <?php echo json_encode(isset($drugs_list) ? $drugs_list : array()) ?>
        });

    })
</script>