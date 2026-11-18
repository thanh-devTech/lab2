<?php

if ($act == 'add_ntc') {
    $return     = array('status' => 'ok', 'msg' => '');
    $ntc        = ($_REQUEST['ntc'] > 1) ? $_REQUEST['ntc'] : 1;
    $ntc_pos    = $_REQUEST['ntc_pos'];
    $pl_ID      = $_REQUEST['pl_ID'];
    $newpos     = $_REQUEST['newpos'];
    $fieldkey   = 'pl_ntc' . ($ntc > 1 ? $ntc : '') . '_position';

    $plate_controls = array(
        'neg' => 'Covid-19 Negative IC',
        'pos' => 'Covid-19 Positive IC'
    );


    if ($pl_ID > 0 && $ntc_pos > 0 && $newpos > 0) {
        $max_pos = $wpdb->get_var("select max(position) from ci_plates_batches where pl_ID = {$pl_ID}");
        $plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID}");
        if ($plate->re_is_uti_panel == 1) {
            //uti, wound
            $plate_controls = array(
                'neg' => 'NC',
                'pos' => 'PC'
            );
        }
        $ntc2_pos = $plate->pl_ntc2_position;

        if ($ntc_pos == $ntc2_pos) {
            $return['status'] = 'error';
        } else if ($newpos > 0 && $newpos <= $max_well) {
            $wpdb->update('ci_plates', array($fieldkey => $ntc_pos), array('pl_ID' => $pl_ID));
            $wpdb->update('ci_plates_batches', array('position' => $newpos), array('position' => $ntc_pos, 'pl_ID' => $pl_ID));
        } else {
            $return['status'] = 'error';
        }
        $return['ntcname'] = !empty($plate->pl_ntc_text) ? $plate->pl_ntc_text : $plate_controls['neg'];
        if ($ntc == 2) {
            $return['ntcname'] = !empty($plate->pl_ntc2_text) ? $plate->pl_ntc2_text : $plate_controls['pos'];
        }
    } else {
        $return['status'] = 'error';
    }

    wp_send_json($return);
} else if ($act == 'combine384') {
    $return = array('status' => 'ok', 'msg' => '');
    $pl_IDs = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    $combinesort = $_REQUEST['combinesort'] > 1 ? $_REQUEST['combinesort'] : 1;

    if (count($pl_IDs) > 0) {
        $pl_IDs_str = implode(',', $pl_IDs);

        $plate_date = $wpdb->get_var("select pl_today from ci_plates where pl_ID in ({$pl_IDs_str}) limit 1");

        $number = $wpdb->get_var("select count(*) from ci_plates_combine where pl_today = '{$plate_date}'") + 1;
        //create
        $wpdb->insert('ci_plates_combine', array(
            'pl_today' => $plate_date,
            'pl_number' => $number,
            'combinesort' => $combinesort,
            'created' => date_i18n('Y-m-d H:i:s'),
            'created_by' => $user_id
        ));
        $combine_ID = $wpdb->insert_id;

        if ($combine_ID > 0) {

            $wpdb->query("update ci_plates set pl_combine_ID = {$combine_ID}
                        where pl_ID in ({$pl_IDs_str})
						and (pl_combine_ID = 0 or pl_combine_ID={$combine_ID}) ");
        } else {
            $return['msg'] = $wpdb->last_error;
        }
        //check count
        $plates = $wpdb->get_results("select p.*,
							ra.re_is_uti_panel
							from ci_plates p
							LEFT JOIN ci_racks ra ON p.pl_rack_ID = ra.re_ID
		                    where pl_combine_ID = {$combine_ID}
		                    order by pl_number asc", ARRAY_A);


        //check plate
        $main_type = 0;
        foreach ($plates as $i => $plate) {
            if ($main_type == 0 && in_array($plate['re_is_uti_panel'], array(33, 34, 35))) {
                $main_type = $plate['re_is_uti_panel'];
                $combinesort = $main_type;
                break;
            }
        }


        if ($main_type != 0) {
            foreach ($plates as $i => $plate) {
                if ($main_type != $plate['re_is_uti_panel']) {
                    unset($plates[$i]);
                    $wpdb->query("update ci_plates set pl_combine_ID = 0
							where pl_ID = {$plate['pl_ID']}
							and pl_combine_ID={$combine_ID} ");
                }
            }

            $wpdb->update('ci_plates_combine', array('pl_type' => $main_type), array('ID' => $combine_ID));
        }

        if (count($plates) == 0) {
            $wpdb->delete('ci_plates_combine', array('ID' => $combine_ID));
            $return['status'] = 'error';
        } else {
            //update combine position

            $last_pos = 0;
            foreach ($plates as $i => $plate) {
                $child_sql = array();
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $batch_tbl = tr_pdd_get_table_by_btype($btype);

                    $child_sql[] = "(select ba.mid,ba.assen,ba.patient_name,ba.dob,
                                    rb.position, rb.ID, rb.btype,rb.created
                                    from {$batch_tbl} ba
                                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                        where rb.pl_ID = '{$plate['pl_ID']}' ) ";
                }

                $child_sql = implode(' UNION ALL ', $child_sql);

                $sql = "select * from ({$child_sql}) as ball
                                order by ball.position asc";
                $batches = $wpdb->get_results($sql);


                switch ($combinesort) {
                    case 33:
                        $start_plate_pos = ($i * 6);
                        break;
                    case 34:
                        $start_plate_pos = $i <= 1 ? ($i * 12) : ($i * 12) + 168;
                        break;
                    case 35:
                        $start_plate_pos = $last_pos + $i * 2;
                        break;
                    case 3:
                        $start_plate_pos = $i * 96;
                        break;
                    case 2:
                        $start_plate_pos = $i < 2 ? $i : $i + 190;
                        break;
                    default:
                        $start_plate_pos = $i * 12;
                        break;
                }


                $ntc1_pos = $plate['pl_ntc_position'];
                $ntc2_pos = $plate['pl_ntc2_position'] > 0 ? $plate['pl_ntc2_position'] : 1;
                $ntc3_pos = $plate['pl_ntc3_position'];
                $ntc4_pos = $plate['pl_ntc4_position'];
                $blank1_pos = $plate['pl_blank1_position'];
                $blank2_pos = $plate['pl_blank2_position'];
                $update = array();
                $update['pl_ntc_combine_position'] = 0;
                $update['pl_ntc2_combine_position'] = 0;

                if ($plate['re_is_uti_panel'] == 35) {
                    $ntc2_pos = 0;
                    //$ntc1_pos = 0;
                }
                if ($ntc1_pos > 0) {
                    $update['pl_ntc_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $ntc1_pos);
                }
                if ($ntc2_pos > 0) {
                    $update['pl_ntc2_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $ntc2_pos);
                }
                if ($ntc3_pos > 0) {
                    $update['pl_ntc3_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $ntc3_pos);
                }
                if ($ntc4_pos > 0) {
                    $update['pl_ntc4_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $ntc4_pos);
                }

                if ($blank1_pos > 0) {
                    $update['pl_blank1_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $blank1_pos);
                }

                if ($blank2_pos > 0) {
                    $update['pl_blank2_combine_position'] = trpdd_s_get_384_position($combinesort, $start_plate_pos, $blank2_pos);
                }
                if (in_array($main_type, array(33, 34))) {
                    $update = array();
                }
                if (count($update) > 0) {
                    $wpdb->update('ci_plates', $update, array('pl_ID' => $plate['pl_ID']));
                }


                foreach ($batches as $batch) {
                    $current_pos = trpdd_s_get_384_position($combinesort, $start_plate_pos, $batch->position);

                    $wpdb->update('ci_plates_batches', array(
                        'combine_position' => $current_pos
                    ), array('ID' => $batch->ID));

                    $last_pos = $batch->position;
                }
            }
        }
    } else {
        $return['status'] = 'error';
        $return['msg'] = 'empty ids';
    }

    wp_send_json($return);
} else if ($act == 'combine96') {
    $return     = array('status' => 'ok', 'msg' => '');
    $pl_IDs     = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    $combinesort = $_REQUEST['combinesort'] > 1 ? $_REQUEST['combinesort'] : 1;

    if (count($pl_IDs) > 0) {
        $pl_IDs_str = implode(',', $pl_IDs);

        $plate_date = $wpdb->get_var("select pl_today from ci_plates where pl_ID in ({$pl_IDs_str}) limit 1");

        $number = $wpdb->get_var("select count(*) from ci_plates_combine where pl_today = '{$plate_date}'") + 1;
        //create
        $wpdb->insert('ci_plates_combine', array(
            'pl_today' => $plate_date,
            'pl_number' => $number,
            'combinesort' => $combinesort,
            'created' => date_i18n('Y-m-d H:i:s'),
            'created_by' => $user_id,
            'combine96' => 1,
        ));
        $combine_ID = $wpdb->insert_id;
        if ($combine_ID > 0) {
            $wpdb->query("update ci_plates set pl_combine_ID = {$combine_ID}
                        where pl_ID in ({$pl_IDs_str})");
        } else {
            $return['msg'] = $wpdb->last_error;
        }
        //check count
        $plates = $wpdb->get_results("select * from ci_plates 
		                    where pl_combine_ID = {$combine_ID}
		                    order by pl_number asc", ARRAY_A);
        if (count($plates) == 0) {
            $wpdb->delete('ci_plates_combine', array('ID' => $combine_ID));
            $return['status'] = 'error';
        } else {
            //update combine position

            foreach ($plates as $i => $plate) {
                $child_sql = array();
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $batch_tbl = tr_pdd_get_table_by_btype($btype);

                    $child_sql[] = "(select ba.mid,ba.assen,ba.patient_name,ba.dob,
                                    rb.position, rb.ID, rb.btype,rb.created
                                    from {$batch_tbl} ba
                                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                        where rb.pl_ID = '{$plate['pl_ID']}' ) ";
                }

                $child_sql = implode(' UNION ALL ', $child_sql);

                $sql = "select * from ({$child_sql}) as ball
                                order by ball.position asc";
                $batches = $wpdb->get_results($sql);


                $start_plate_pos = $i * 16;

                $ntc1_pos = $plate['pl_ntc_position'];
                $ntc2_pos = $plate['pl_ntc2_position'] > 0 ? $plate['pl_ntc2_position'] : 1;

                $update = array();
                $update['pl_ntc_combine_position'] = 0;
                $update['pl_ntc2_combine_position'] = 0;
                if ($ntc1_pos > 0) {
                    $update['pl_ntc_combine_position'] = $start_plate_pos + 15;
                }
                if ($ntc2_pos > 0) {
                    $update['pl_ntc2_combine_position'] = $start_plate_pos + 16;
                }


                $wpdb->update('ci_plates', $update, array('pl_ID' => $plate['pl_ID']));

                foreach ($batches as $batch) {
                    $current_pos = $start_plate_pos + $batch->position;
                    $wpdb->update('ci_plates_batches', array(
                        'combine_position' => $current_pos
                    ), array('ID' => $batch->ID));
                }
            }
        }
    } else {
        $return['status'] = 'error';
        $return['msg'] = 'empty ids';
    }

    wp_send_json($return);
} else if ($act == 'combinefull96') {
    $return = array('status' => 'ok');
    $pl_IDs = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    $rack_max6_fun      = tr_get_option('ci_pdf_data', 'rack_max6_fun');
    if (count($pl_IDs) > 0) {
        foreach ($pl_IDs as $i => $pl_ID) {
            $plate = $wpdb->get_row("select * 
                            from ci_plates pl
                            left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
                            where pl_ID = {$pl_ID} ", ARRAY_A);

            if ($plate['re_is_uti_panel'] == -1) {
                //Respiratory Light
                continue;
            } else if ($rack_max6_fun && $plate['re_is_uti_panel'] == -3) {
                //Fungal
                continue;
            } else if ($rack_max6_fun && $plate['re_is_uti_panel'] == -5) {
                //GI
                continue;
            } else if ($rack_uti_type == 3 && $plate['re_is_uti_panel'] == 1) {
                continue;
            } else if ($rack_uti_type == 14 && $plate['re_is_uti_panel'] == 1) {
                continue;
            } else if ($rack_uti_type == 30 && $plate['re_is_uti_panel'] == 1) {
                continue;
            }

            $count = $wpdb->get_var("select count(*) from ci_plates_batches where pl_ID = {$pl_ID}");
            $need = 94 - $count;
            if ($need > 0) {
                //fix old
                $olds = $wpdb->get_results("select * from ci_plates_batches 
                                        where pl_ID = {$pl_ID}
                                         order by position asc");
                $current_pos = $plate->pl_ntc_position == 1 ? 3 : 2;
                if ($current_pos == $plate->pl_ntc2_position) {
                    $current_pos++;
                }
                foreach ($olds as $old) {
                    if ($current_pos != $old->position) {
                        $wpdb->update('ci_plates_batches', array(
                            'position' => $current_pos
                        ), array('ID' => $old->ID));
                    }


                    $current_pos++;
                    if ($current_pos == $plate->pl_ntc_position || $current_pos == $plate->pl_ntc2_position) {
                        $current_pos++;
                    }
                }

                if ($i + 1 == count($pl_IDs)) {
                    break;
                }
                for ($j = $i + 1; $j < count($pl_IDs); $j++) {
                    $next_pl_ID = isset($pl_IDs[$j]) ? $pl_IDs[$j] : 0;
                    if ($next_pl_ID == 0) break;

                    if ($need > 0) {
                        $news = $wpdb->get_results("select * from ci_plates_batches 
                                        where pl_ID = {$next_pl_ID}
                                         order by position asc
                                        limit {$need}");
                        foreach ($news as $new) {
                            $wpdb->update('ci_plates_batches', array(
                                'pl_ID' => $pl_ID,
                                'position' => $current_pos
                            ), array('ID' => $new->ID));
                            $current_pos++;
                            $need--;
                        }
                    } else {
                        break;
                    }
                }
            }
        }

        foreach ($pl_IDs  as $pl_ID) {
            $count = $wpdb->get_var("select count(*) from ci_plates_batches where pl_ID = {$pl_ID}");
            if ($count == 0) {
                $wpdb->delete('ci_plates', array('pl_ID' => $pl_ID));
            }
        }
    } else {
        $return['status'] = 'error';
        $return['error'] = 'empty ids';
    }

    wp_send_json($return);
} else if ($act == 'expand') {
    $return = array('status' => 'ok');
    $id = $_REQUEST['ID'];

    $wpdb->query("update ci_plates_batches set combine_position = 0 
	            where pl_ID in (select pl_ID from ci_plates where pl_combine_ID = {$id} )");

    $wpdb->delete('ci_plates_combine', array('ID' => $id));
    $wpdb->update('ci_plates', array(
        'pl_combine_ID' => 0,
        'pl_ntc_combine_position' => 0,
        'pl_ntc2_combine_position' => 0,
    ), array('pl_combine_ID' => $id));

    $wpdb->update('ci_plates', array(
        'pl_ntc3_combine_position' => 0,
        'pl_ntc4_combine_position' => 0,
    ), array('pl_combine_ID' => $id));

    wp_send_json($return);
} else if ($act == 'delete_plates') {
    $return = array('status' => 'ok');
    $pl_IDs = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    foreach ($pl_IDs as $pl_ID) {
        $wpdb->delete('ci_plates', array('pl_ID' => $pl_ID));
        $wpdb->delete('ci_plates_batches', array('pl_ID' => $pl_ID));
    }
    wp_send_json($return);
} else if ($act == 'export') {
    $pl_IDs = explode(',', $_REQUEST['pl_IDs']);
    $com_IDs = explode(',', $_REQUEST['com_IDs']);
    $filename = trpdd_get_file_export($pl_IDs, $com_IDs, '');
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/csv");
    $output = fopen("php://output", 'w');

    $pl_ID = $pl_IDs[0];
    $com_ID = $com_IDs[0];

    if (!empty($pl_ID)) {
        $plate = $wpdb->get_row("select * 
                            from ci_plates pl
                            left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
                            where pl_ID = {$pl_ID} ", ARRAY_A);
        $assens = trpdd_get_assens_plate($plate);

        $headers = array('ELUTION PLATE ' . $plate['pl_number']);
        fputcsv($output, $headers);

        for ($j = 1; $j <= $max_well; $j++) {
            $arr = isset($assens[$j]) ? $assens[$j] : array('assen' => '');
            $assen = $arr['assen'];

            if ($rack_ABC) {
                $num12 = floor(($j - 1) / 8) + 1;
                $welld = chr(floor(($j - 1) % 8) + 65) . $num12;
            } else {
                $num12 = ($j - 1) % 12 + 1;
                $welld = chr(floor(($j - 1) / 12) + 65) . $num12;
            }

            $rowdata = array($j, $assen, $welld);
            fputcsv($output, $rowdata);
        }
    } else if (!empty($com_ID)) {
        $plate_combine = $wpdb->get_row("select * from ci_plates_combine where ID = {$com_ID} ", ARRAY_A);
        $plates = $wpdb->get_results("select * from ci_plates where pl_combine_ID = {$com_ID}", ARRAY_A);

        $assens = array();
        foreach ($plates as $i => $plate) {
            if ($plate_combine['combine96']) {
                $plate['num'] = $i + 1;
            }
            $assens = trpdd_get_assens_plate($plate, true, $assens);
        }

        $com_max_col = 24;
        $com_max_row = 16;

        if ($plate_combine['combine96']) {
            $com_max_col = 12;
            $com_max_row = 8;
            $assens[81] = array(
                'assen' => 'Positive IC'
            );
            $assens[82] = array(
                'assen' => 'Negative IC'
            );
            $assens[83] = array(
                'assen' => 'NTC'
            );
        }

        $headers = array('Final PLATE ' . $plate_combine['pl_number']);
        fputcsv($output, $headers);
        $headers = array('');
        for ($c = 1; $c <= $com_max_col; $c++) {
            $headers[] = $c;
        }
        fputcsv($output, $headers);

        $t_pos = 0;
        for ($r = 1; $r <= $com_max_row; $r++) {
            $char = chr($r + 64);
            $rowdata = array($char);
            for ($c = 1; $c <= $com_max_col; $c++) {
                $t_pos++;
                $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                $rowdata[] = $arr['assen'];
            }
            fputcsv($output, $rowdata);
        }
    }

    exit;
} else if ($act == 'biorad_export' || $act == 'studio_export' || $act == 'qtower_export') {
    $pl_IDs     = isset($_REQUEST['pl_IDs']) ? explode(',', $_REQUEST['pl_IDs']) : array();
    $com_IDs     = isset($_REQUEST['com_IDs']) ? explode(',', $_REQUEST['com_IDs']) : array();
    $tem         = isset($_REQUEST['tem']) ? $_REQUEST['tem'] : '';
    $blockid     = isset($_REQUEST['blockid']) ? $_REQUEST['blockid'] : '';
    $nameslug     = $act == 'biorad_export' ? 'BioRad' : 'Quant Studio';
    $export_type = '';
    if ($act == 'qtower_export') {
        $nameslug = 'qTower';
    }


    if (stripos($blockid, 'abc_') === 0) {
        $tem         = 'ABC';
        $blockid     = str_replace('abc_', '', $blockid);
        $blockid    = intval($blockid);
        $nameslug     = 'Final Plate ' . chr($blockid + 64) . '-' . $nameslug;
    }





    $pl_ID = isset($pl_IDs[0]) ? $pl_IDs[0] : 0;
    $com_ID = isset($com_IDs[0]) ? $com_IDs[0] : 0;


    if (!empty($pl_ID)) {
        $plate      = $wpdb->get_row("select * 
		                        from ci_plates pl    
                            left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
                            where pl_ID = {$pl_ID} ", ARRAY_A);

        $config_plate = trpdd_plate_map_config($plate['re_is_uti_panel']);

        if (!empty($config_plate)) {
            if (!empty($blockid) && strpos($blockid, 'num_') === 0) {
                $blockid = $plate['sub_number'] = str_replace('num_', '', $blockid);
                if ($config_plate['subwellsplate'] > 0) {
                    $plate['subwellsplate'] = $config_plate['subwellsplate'];
                    $nameslug     = 'Final Plate ' . chr($blockid + 64) . '-' . $nameslug;
                }

                if ($config_plate['add96'] > 0) {
                    $plate['subwellsplate'] = $config_plate['add96map']['max'];
                    $nameslug     = 'Final Plate ' . $plate['sub_number'] . '-' . $nameslug;
                }
            }


            if ($config_plate['type'] == 96) {

                if (isset($config_plate['add384']) && $config_plate['add384']) {
                    $plate['is_add384'] = 1;
                    $plate['is_96'] = true;
                    $nameslug = 'Final Plate ' . $nameslug;
                } else {
                    $plate['is_wide'] = true;
                    $plate['is_96'] = true;
                }
            }

            if (isset($config_plate['export_type'])) {
                $export_type = $config_plate['export_type'];
            }
        }



        $assens     = trpdd_get_assens_plate($plate);

        $filename = trpdd_get_file_export($pl_IDs, $com_IDs, $nameslug);
        $filename = str_replace(',', '_', $filename);
        header('Content-disposition: attachment; filename="' . $filename . '"');
        header("Content-Type: text/csv");
        $output = fopen("php://output", 'w');

        $headers    = array('Row', 'Column', '*Target Name', '*Sample Name');

        if ($export_type == 'ddPCR') {
            fputs($output, 'ddplate - DO NOT MODIFY THIS LINE,Version=1,ApplicationName=QX Manager Standard Edition,ApplicationVersion=2.0.0.665,ApplicationEdition=ResearchEmbedded,User=QLDR-1T30\Admin,CreatedDate=2/11/2025 12:45:58 PM,,,,,,,,,,,' . "\n");
            fputs($output, ',,,,,,,,,,,,,,,,,' . "\n");
            fputs($output, 'PlateSize=GCR96,,,,,,,,,,,,,,,,,' . "\n");
            fputs($output, 'PlateNotes=,Plate Notes Here,Plate Notes Here 2,,,,,,,,,,,,,,,' . "\n");
            $headers    = array(
                'Well',
                'Perform Droplet Reading',
                'ExperimentType',
                'Sample description 1',
                'Sample description 2',
                'Sample description 3',
                'Sample description 4',
                'SampleType',
                'SupermixName',
                'AssayType',
                'TargetName',
                'TargetType',
                'Signal Ch1',
                'Signal Ch2',
                'Reference Copies',
                'Well Notes',
                'Plot?',
                'RdqConversionFactor'
            );
        }
        $panel_name = 'Covid-19';
        $target_names = array();
        $target_by_well = false;
        $export_384 = false;

        if ($rack_exporttype == 'rpp') {
            $headers = array('Well', 'Sample');
        } else if (isset($plate['re_is_uti_panel'])) {
            if ($plate['re_is_uti_panel'] < 0) {
                $panel_name = '';
            }


            if (!empty($config_plate)) {
                $panel_name = '';
                if ($config_plate['type'] == 384) {
                    $max_well = 384;
                } else {
                    $max_well = 96;
                    if (isset($config_plate['add384']) && $config_plate['add384']) {
                        $max_well = 384;
                    }
                }
                if (isset($config_plate['target']) && $config_plate['target']) {
                    $target_by_well = true;
                }
            } else if ($plate['re_is_uti_panel'] == -2 && $act == 'studio_export') {
                //RPP
                $headers = array('Well', 'Sample');
                $rack_exporttype = 'rpp';
            } else if ($plate['re_is_uti_panel'] == -1) {
                //Respiratory Light
                $panel_name = '';
                $max_well = 0;
                $headers = array('');
                for ($i = 1; $i <= 12; $i++) {
                    $headers[] = 'Patient ' . $i;
                }
                fputcsv($output, $headers);
                $headers = array('', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12');
                fputcsv($output, $headers);

                for ($row = 1; $row <= 8; $row++) {
                    $row_char = chr($row + 64);

                    $rowdata = array($row_char);
                    for ($i = 1; $i <= 12; $i++) {
                        $t_pos = $rack_ABC ? (($i - 1) * 8) + 1 : $i;
                        $assen = isset($assens[$t_pos]) ? $assens[$t_pos]['assen'] : '';
                        if ($row < 5) {
                            $rowdata[] = $assen;
                        } else if ($row == 6) {
                            $rowdata[] = 'RT Control';
                        } else if ($row == 7) {
                            $rowdata[] = 'Positive Control';
                        } else if ($row == 8) {
                            $rowdata[] = 'Negative Control';
                        }
                    }

                    fputcsv($output, $rowdata);
                }
            } else if ($rack_max6_fun && $plate['re_is_uti_panel'] == -3) {
                //Fungal
                $panel_name = '';
                $max_well = 0;
                $headers = array('', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12');
                fputcsv($output, $headers);

                for ($row = 1; $row <= 8; $row++) {
                    $row_char = chr($row + 64);

                    $rowdata = array($row_char);
                    for ($i = 1; $i <= 12; $i++) {
                        $t_pos = $rack_ABC ? (($i - 1) * 8) + $row : $i;
                        $assen = isset($assens[$t_pos]) ? $assens[$t_pos]['assen'] : '';
                        $rowdata[] = $assen;
                    }

                    fputcsv($output, $rowdata);
                }
            } else if ($rack_uti_type == 14 && $plate['re_is_uti_panel'] == 1) {
                $max_well = 16;
                $rack_exporttype = '';
                $target_by_well = true;
            } else if ($plate['re_is_uti_panel'] == 1) {
                $panel_name = '';
            } else if ($plate['re_is_uti_panel'] == -4) {
                //abx
                $panel_name = '';
            } else if (in_array($plate['re_is_uti_panel'], array(-5))) {
                //GI
                $panel_name = '';
                $rack_exporttype = '';
                $max_well = 384;
            } else if (in_array($plate['re_is_uti_panel'], array(10, 11, 12, 13, 15, 38, 43, 44, 50))) {
                //style ...
                $panel_name         = '';
                $rack_exporttype    = '';
                $max_well           = 384;
            } else if (in_array($plate['re_is_uti_panel'], array(14, 16, 17, 18, 42))) {
                $panel_name         = '';
                $target_by_well     = true;
            } else if ($act == 'qtower_export') {
                $panel_name         = '';
                $rack_exporttype    = '';
                $max_well           = 384;
                $target_by_well     = true;
                $qtower_type         = 2;
                if (in_array($plate['re_is_uti_panel'], array(33, 34))) {
                    $max_well = 96;
                    $qtower_type = 1;
                }

                if ($qtower_type == 1) {
                    $headers            = array('Well', '*Target Name', 'Sample Name');
                } else {
                    $export_384 = true;
                }
            } else if (in_array($plate['re_is_uti_panel'], array(36))) {
                $panel_name = '';
                $max_well = 96;

                $groups = array();

                foreach ($assens as $pos => $arr) {
                    $nblock = ceil($pos / 8);

                    if (!isset($groups[$nblock])) {
                        $groups[$nblock] = array(0 => array('assen' => ''));
                    }

                    for ($i1 = 1; $i1 <= 12; $i1++) {
                        $groups[$nblock][] = $arr;
                    }
                }
                $assens = $groups[$blockid];
            } else if (in_array($plate['re_is_uti_panel'], array(45, 46, 47, 48, 49))) {
                $panel_name = '';
                $max_well = 96;
                $target_by_well     = true;
            } else if ($plate['re_is_uti_panel'] > 36) {
                $panel_name = '';
                $max_well = 96;
            }




            if ($target_by_well) {

                $panel_id = $wpdb->get_var("select re_panel_id from ci_racks 
	                                            where re_ID = '{$plate['pl_rack_ID']}' ");
                if ($panel_id > 0) {
                    $childs_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_parent = '{$panel_id}'");
                    $panel_ids = $childs_ids;
                    $panel_ids[] = $panel_id;
                    $panel_ids = implode(',', $panel_ids);

                    $rstgs = $wpdb->get_results("select te_name,te_abrev, te_abrev2 , te_well_numbers
												from ci_test_panels 
												where te_group_id in( {$panel_ids}) 
												and (te_abrev2 like '%well%'
													or (te_well_numbers !='' and te_well_numbers is not null ) ) ");
                    foreach ($rstgs as $rstg) {
                        $wells = !empty($rstg->te_well_numbers) ? $rstg->te_well_numbers : $rstg->te_abrev2;
                        $abrvs = explode(',', $wells);
                        foreach ($abrvs as $abrv) {
                            $abrv = trim($abrv);
                            $targetn = $rstg->te_name;
                            if (strpos($abrv, '|') > 0) {
                                list($abrv, $targetn) = explode('|', $abrv);
                            } else if (strpos($abrv, ':') > 0) {
                                list($abrv, $targetn) = explode(':', $abrv);
                            } else if (stripos($abrv, '_') === false && !empty($rstg->te_abrev)) {
                                $targetn = $rstg->te_abrev;
                            }

                            if (stripos($abrv, 'well') === false) {
                                $abrv = 'well_' . $abrv;
                            }

                            $abrv = strtolower($abrv);
                            $target_names[$abrv] = isset($target_names[$abrv]) ? $target_names[$abrv] . '/' . $targetn : $targetn;
                        }
                    }
                }

                if (empty($target_names) && !empty($config_plate) && isset($config_plate['targets'])) {
                    foreach ($config_plate['targets'] as $inum => $tgn) {
                        $target_names['well' . ($inum + 1)] = $tgn;
                    }
                }
            }
        }

        if ($export_384) {
            $headers = array('');
            for ($i = 1; $i <= 24; $i++) {
                $headers[] = $i;
            }

            fputcsv($output, $headers);

            for ($row = 1; $row <= 16; $row++) {
                $row_char = chr($row + 64);

                $rowdata = array($row_char);

                for ($i = 1; $i <= 24; $i++) {
                    $t_pos = ($row - 1) * 24 + $i;
                    $assen = isset($assens[$t_pos]) ? $assens[$t_pos]['assen'] : '';

                    if (!empty($assen)) {
                        $layout_target_index = trpdd_plate_layout_target_index($config_plate, $t_pos);
                        if ($layout_target_index > 0) {
                            $numt = $layout_target_index;
                        } else if ($plate['re_is_uti_panel'] == 30) {
                            $numt = floor(($t_pos - 1) / 24) + 1;
                            if ($t_pos % 2 == 0) $numt += 16;
                        } else if ($plate['re_is_uti_panel'] == 31) {
                            $t_pos2 = $t_pos < 193 ? $t_pos : $t_pos - 192;
                            $numt = floor(($t_pos2 - 1) / 24) + 1;
                            if ($t_pos2 % 3 == 2) $numt += 8;
                            if ($t_pos2 % 3 == 0) $numt += 16;
                        } else if (isset($config_plate['add384']) && $config_plate['add384']) {
                            $numt = $assens_numbers[$assen];
                        } else if (isset($config_plate['wellperpatient']) && $config_plate['wellperpatient'] > 1) {
                            $numt = $assens_numbers[$assen];
                        }
                        $wellname = 'well_' . ($numt < 10 ? '0' . $numt : $numt);
                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well_' . $numt;
                        }

                        $assen = isset($target_names[$wellname]) ? $target_names[$wellname] : '';
                    }

                    $rowdata[] = $assen;
                }

                fputcsv($output, $rowdata);
            }
            exit;
        } else if ($max_well > 0) {
            fputcsv($output, $headers);

            $ntc_empty_target = isset($config_plate['ntctarget']) && !$config_plate['ntctarget'];

            $t_pos = 0;
            $assens_numbers = array();
            for ($j = 1; $j <= $max_well; $j++) {

                if ($tem == 'ABC') {
                    $t_pos = $j;
                } elseif ($rack_uti_type == 14 && $plate['re_is_uti_panel'] == 1) {
                    $row_char = chr(($j - 1) + 65);

                    for ($i = 1; $i <= 24; $i++) {
                        $t_pos++;

                        $arr    = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

                        $assen  = $arr['assen'];
                        $assen  = str_replace(' RPP', '', $assen);

                        if (strpos($assen, ',') > 0) {
                            $assen = $row_char . $i . '-' . $plate['pl_number'] . '#' . $plate['pl_today'];
                        }

                        $wellname = 'well_' . ($i < 10 ? '0' . $i : $i);

                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well_' . $i;
                        }

                        $target_name = isset($target_names[$wellname]) && !empty($assen) ? $target_names[$wellname] : '';

                        if ($j >= 15) {
                            $target_name = $assen;
                        }

                        $rowdata = array(
                            $row_char,
                            $i,
                            $target_name,
                            $assen,
                        );

                        fputcsv($output, $rowdata);
                    }
                    continue;
                } else if (!empty($config_plate) && $config_plate['type'] == 96) {
                    $t_pos = $j;
                } else if ($rack_ABC) {
                    $t_pos = floor(($j - 1) / 12) + 1 + (($j - 1) % 12) * 8;
                } else {
                    $t_pos = $j;
                }


                if ($max_well == 384) {
                    $t_pos = $j;
                    $rownum = floor(($j - 1) / 24) + 1;
                    $row_char = chr($rownum + 64);
                    $column = ($j - 1) % 24 + 1;
                } else {
                    $rownum = floor(($j - 1) / 12) + 1;
                    $row_char = chr($rownum + 64);
                    $column = ($j - 1) % 12 + 1;
                }

                $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                $assen = $arr['assen'];
                if (strpos($assen, ',') > 0) {
                    $assen = $row_char . $column . '-' . $plate['pl_number'] . '#' . $plate['pl_today'];
                }
                $panel_name_row = $panel_name;
                if (empty($assen)) {
                    $panel_name_row = '';
                }

                if ($act == 'qtower_export') {

                    if ($plate['re_is_uti_panel'] == 32) {
                        $target_name = 'SARS-COV-2';
                    } else {
                        if ($plate['re_is_uti_panel'] == 30) {
                            $numt = floor(($t_pos - 1) / 24) + 1;
                            if ($t_pos % 2 == 0) $numt += 16;
                        } else if ($plate['re_is_uti_panel'] == 31) {
                            $t_pos2 = $t_pos < 193 ? $t_pos : $t_pos - 192;
                            $numt = floor(($t_pos2 - 1) / 24) + 1;
                            if ($t_pos2 % 3 == 2) $numt += 8;
                            if ($t_pos2 % 3 == 0) $numt += 16;
                        } else if ($plate['re_is_uti_panel'] == 33) {
                            $numt = ($t_pos - 1) % 32 + 1;
                        } else if ($plate['re_is_uti_panel'] == 34) {
                            $numt = ($t_pos - 1) % 24 + 1;
                        }
                        $wellname = 'well_' . ($numt < 10 ? '0' . $numt : $numt);
                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well' . ($numt < 10 ? '0' . $numt : $numt);
                        }
                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well_' . $numt;
                        }
                        $target_name = isset($target_names[$wellname]) && !empty($assen) ? $target_names[$wellname] : '';

                        if ($ntc_empty_target && isset($arr['ntc'])) {
                            $target_name = '';
                        }
                    }

                    $rowdata = array(
                        $row_char . $column,
                        $target_name,
                        $assen,
                    );
                } else if ($rack_exporttype == 'rpp') {
                    $rowdata = array(
                        $row_char . $column,
                        $assen,
                    );
                } else {
                    if (!isset($assens_numbers[$assen])) {
                        $assens_numbers[$assen] = 0;
                    }
                    $assens_numbers[$assen]++;

                    if (empty($panel_name_row)) {
                        $numt = $column;
                        $layout_target_index = trpdd_plate_layout_target_index($config_plate, $t_pos);
                        if ($layout_target_index > 0) {
                            $numt = $layout_target_index;
                        } else if (in_array($plate['re_is_uti_panel'], array(14, 42))) {
                            $numt = (($rownum - 1) * 4) + (($column - 1) % 4) + 1;
                        } else if (in_array($plate['re_is_uti_panel'], array(16))) {
                            $numt = (($rownum - 1) * 2) + (($column - 1) % 2) + 1;
                        } else if (isset($config_plate['wellperpatient']) && $max_well == 384 && $config_plate['wellperpatient'] > 0) {
                            if ((isset($config_plate['add384']) && $config_plate['add384']) || $config_plate['rack'] == 384) {
                                $numt = $assens_numbers[$assen];
                            } else if (isset($config_plate['targets']) && count($config_plate['targets']) > 0) {
                                $numt = $assens_numbers[$assen];
                            } else if ($numt > $config_plate['wellperpatient']) {
                                $numt = $numt - $config_plate['wellperpatient'];
                            }
                        } else if (isset($config_plate['add384']) && $config_plate['add384']) {
                            $numt = $assens_numbers[$assen];
                        } else if (isset($config_plate['wellperpatient']) && $config_plate['wellperpatient'] >= 1) {
                            $numt = $assens_numbers[$assen];
                        }


                        $wellname = 'well_' . ($numt < 10 ? '0' . $numt : $numt);
                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well' . ($numt < 10 ? '0' . $numt : $numt);
                            if (!isset($target_names[$wellname])) {
                                $wellname = 'well' . $numt;
                            }
                        }
                        if (!isset($target_names[$wellname])) {
                            $wellname = 'well_' . $numt;
                        }

                        $panel_name_row = isset($target_names[$wellname]) && !empty($assen) ? $target_names[$wellname] : '';

                        if ($ntc_empty_target && isset($arr['ntc'])) {
                            $panel_name_row = '';
                        }

                        // $panel_name_row = $wellname;//only debug

                        if ($plate['re_is_uti_panel'] == 46) //rpplite
                        {
                            if ($column == 1 || $column == 4) {
                                $panel_name_row = 'FLUA';
                            } else if ($column == 2 || $column == 5) {
                                $panel_name_row = 'EERTC';
                            } else if ($column == 3 || $column == 6) {
                                $panel_name_row = 'RNASEP2';
                            }
                        } else if ($plate['re_is_uti_panel'] == 49) {
                            $panel_name_row = 'NEIGON';
                        }
                    }

                    $rowdata = array(
                        $row_char,
                        $column,
                        $panel_name_row,
                        $assen,
                    );

                    if ($export_type == 'ddPCR') {

                        if ($column < 10) $column = '0' . $column;

                        $SampleType = 'Unknown';
                        $desc2 = 'MSRE+';
                        if (isset($had_ntc) && $had_ntc) $desc2 = 'MSRE-';
                        if ($assen == 'PC') {
                            $SampleType = 'PosCtrl';
                        }
                        if ($assen == 'NC' || $assen == 'NTC') {
                            $SampleType = 'NTC';
                            $desc2 = '';
                            $had_ntc = true;
                        }
                        $rowdata = array(
                            $row_char . $column,
                            'Yes',
                            'Direct Quantification (DQ)',
                            $assen,
                            $desc2,
                            '',
                            '',
                            $SampleType,
                            'ddPCR Multiplex Supermix',
                            'Single Target per Channel',
                            '1',
                            'Unknown',
                            'FAM',
                            'None',
                            '',
                            '',
                            'FALSE',
                            ''
                        );
                        fputcsv($output, $rowdata);
                        $rowdata[10] = 2;
                        $rowdata[12] = 'None';
                        $rowdata[13] = 'HEX';
                    }

                    if ($pcrcovid_n1_rp) {
                        if (!empty($assen)) {
                            $rowdata[2] = 'N1';
                        }

                        fputcsv($output, $rowdata);
                        if (!empty($assen)) {
                            $rowdata[2] = 'RP';
                        }
                    }
                }



                fputcsv($output, $rowdata);
            }
        }
    } else if (!empty($com_ID)) {
        $target_by_well = false;
        $target_names = array();

        $plate_combine = $wpdb->get_row("select * from ci_plates_combine where ID = {$com_ID} ", ARRAY_A);
        $plates = $wpdb->get_results("select p.*,
		ra.re_panel_id,
		ra.re_is_uti_panel
		from ci_plates p
		JOIN ci_racks ra ON ra.re_ID = p.pl_rack_ID
		where p.pl_combine_ID = {$com_ID}", ARRAY_A);
        $assens = array();
        $panel_id = 0;



        foreach ($plates as $i => $plate) {
            if ($plate_combine['combine96']) {
                $plate['num'] = $i + 1;
            }
            if ($plate['re_panel_id'] > 0) {
                $panel_id = $plate['re_panel_id'];
            }

            $assens = trpdd_get_assens_plate($plate, true, $assens);
        }

        $filename = trpdd_get_file_export($pl_IDs, $com_IDs, $nameslug);
        $filename = str_replace(',', '_', $filename);
        header('Content-disposition: attachment; filename="' . $filename . '"');
        header("Content-Type: text/csv");
        $output = fopen("php://output", 'w');


        $com_max_col = 24;
        $com_max_row = 16;

        if ($plate_combine['combine96']) {
            $com_max_col = 12;
            $com_max_row = 8;
            $assens[81] = array(
                'assen' => 'Positive IC'
            );
            $assens[82] = array(
                'assen' => 'Negative IC'
            );
            $assens[83] = array(
                'assen' => 'NTC'
            );
        }

        if ($act == 'qtower_export') {
            $target_by_well = true;
            $headers = array('', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24);
        } else if ($tem == '96full') {
            $headers = array('');
            for ($ic = 1; $ic <= 12; $ic++) {
                $headers[] = $ic;
            }
        } else if ($tem == '96') {
            $headers = array('Well', 'Sample Name');
        } else {
            $headers = array('Row', 'Column', '*Target Name', '*Sample Name');
        }

        if ($target_by_well) {

            if ($panel_id > 0) {
                $childs_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_parent = '{$panel_id}'");
                $panel_ids = $childs_ids;
                $panel_ids[] = $panel_id;
                $panel_ids = implode(',', $panel_ids);
                $rstgs = $wpdb->get_results("select te_name, te_abrev2 
										from ci_test_panels 
										where te_group_id in( {$panel_ids}) 
										and te_abrev2 like '%well%' ");
                foreach ($rstgs as $rstg) {
                    $abrvs = explode(',', $rstg->te_abrev2);
                    foreach ($abrvs as $abrv) {
                        $target_names[strtolower($abrv)] = $rstg->te_name;
                    }
                }
            }
        }

        fputcsv($output, $headers);

        if ($act == 'qtower_export') {
            for ($r = 1; $r <= $com_max_row; $r++) {
                $char = chr($r + 64);
                $rowdata = array($char);
                for ($c = 1; $c <= $com_max_col; $c++) {
                    $t_pos++;
                    $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

                    if ($plate['re_is_uti_panel'] == 33) {
                        $numt = floor(($t_pos - 1) / 24) + 1;
                        if ($t_pos % 2 == 0) $numt += 16;
                    } else if ($plate['re_is_uti_panel'] == 34) {
                        $t_pos2 = $t_pos < 193 ? $t_pos : $t_pos - 192;
                        $numt = floor(($t_pos2 - 1) / 24) + 1;
                        if ($t_pos2 % 3 == 2) $numt += 8;
                        if ($t_pos2 % 3 == 0) $numt += 16;
                    }
                    $wellname = 'well_' . ($numt < 10 ? '0' . $numt : $numt);
                    if (!isset($target_names[$wellname])) {
                        $wellname = 'well_' . $numt;
                    }
                    $arr['assen'] = isset($target_names[$wellname]) && !empty($arr['assen']) ? $target_names[$wellname] : '';

                    $rowdata[] = $arr['assen'];
                }
                fputcsv($output, $rowdata);
            }
        } else if ($tem == '96full') {
            $t_pos = 0;
            for ($r = 1; $r <= $com_max_row; $r++) {
                $char = chr($r + 64);
                $rowdata = array($char);
                for ($c = 1; $c <= $com_max_col; $c++) {
                    $t_pos++;
                    $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

                    $rowdata[] = $arr['assen'];
                }
                fputcsv($output, $rowdata);
            }
        } else if ($tem == '96') {
            $t_pos = 0;
            for ($r = 1; $r <= $com_max_row; $r++) {
                $char = chr($r + 64);
                for ($c = 1; $c <= $com_max_col; $c++) {
                    $t_pos++;
                    $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

                    $rowdata = array(
                        $t_pos,
                        $arr['assen'],
                    );

                    fputcsv($output, $rowdata);
                }
            }
        } else {
            $t_pos = 0;

            for ($r = 1; $r <= $com_max_row; $r++) {
                $char = chr($r + 64);
                $rowdata = array();
                for ($c = 1; $c <= $com_max_col; $c++) {
                    $t_pos++;
                    $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');

                    $rowdata = array(
                        $char,
                        $c,
                        'Covid-19',
                        $arr['assen'],
                    );

                    fputcsv($output, $rowdata);
                }
            }
        }
    }

    exit;
} else if ($act == 'ntc2_change_text') {
    $return = array('status' => 'ok');
    $pl_ID = $_REQUEST['pl_ID'];
    $text = $_REQUEST['text'];
    if (!empty($text)) {
        $wpdb->update('ci_plates', array('pl_ntc2_text' => $text), array('pl_ID' => $pl_ID));
    }
    wp_send_json($return);
} else if ($act == 'lock_plates') {
    $return = array('status' => 'ok');
    $pl_IDs = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    foreach ($pl_IDs as $pl_ID) {
        $wpdb->update('ci_plates', array('pl_lock' => 1), array('pl_ID' => $pl_ID));
    }
    wp_send_json($return);
} else if ($act == 'unlock_plates') {
    $return = array('status' => 'ok');
    $pl_IDs = is_array($_REQUEST['pl_IDs']) ? $_REQUEST['pl_IDs'] : array();
    foreach ($pl_IDs as $pl_ID) {
        $wpdb->update('ci_plates', array('pl_lock' => 0), array('pl_ID' => $pl_ID));
    }
    wp_send_json($return);
} else if ($act == 'upload_dx11') {

    $return     = array('status' => 'ok', 'list' => array());
    $pl_ID      = $_REQUEST['pl_ID'];
    $file       = $_FILES['file'];
    $logdir     = WP_CONTENT_DIR . '/log/results_req/';
    if (!file_exists($logdir)) {
        wp_mkdir_p($logdir);
    }
    $filename = $file['name'];
    $file_path = $logdir . $filename;
    file_put_contents($file_path, file_get_contents($file['tmp_name']));
    $data = trpdd_get_data($file_path, 'csv', array(), true, false, true);


    $plate      = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);
    $rack       = $wpdb->get_row("select * from ci_racks where re_ID = {$plate['pl_rack_ID']} ", ARRAY_A);
    $otherdata  = json_decode($plate['other_data'], true);
    $otherdata['dx11_file'] = $filename;
    $lqc        = 0.1;
    $testid     = $wpdb->get_var("select te_ID from ci_test_panels 
                    where te_type = 'pcr' 
                    and te_name = 'Beta Score' 
                    and te_in_req = 1");

    $cut        = $wpdb->get_row("select * from ci_test_panels_cutoffs where cu_te_ID = {$testid} ");

    if ($cut && $cut->cu_lqc > 0) {
        $lqc = $cut->cu_lqc;
    }

    $fail_list = array();
    foreach ($data['data'] as $row) {
        $assen = trpdd_get_string_exist($row, array('samplename', 'name'));
        $conc = trpdd_get_string_exist($row, array('samplestockconcentration', 'conc'));

        if (empty($assen)) {
            continue;
        }
        $posneg = $conc > $lqc ? 'POS' : 'NEG';
        if ($posneg == 'NEG') {
            $fail_list[] = $assen;
        }

        $return['list'][$assen] = $posneg;
        $report_id = isset($reports[$assen]) ? $reports[$assen] : 0;
        if (empty($report_id)) {
            $report_id = $wpdb->get_var("select id from ci_reports_req where patient_id = '{$assen}'  ");
            if ($report_id > 0) {
                $reports[$assen] = $report_id;
            } else {
                $data = array(
                    'patient_id' => $assen,
                    'lab_id' => CI_Base_Lab::get_current_lab_id(),
                    'author_id' => get_current_user_id(),
                    'created_date' => date_i18n('Y-m-d'),
                    'created' => date_i18n('Y-m-d H:i:s'),
                    'instrument' => '',
                );
                $wpdb->insert('ci_reports_req', $data);
                $report_id = $wpdb->insert_id;
                $reports[$assen] = $report_id;
            }
        }
        if ($report_id > 0) {

            $drugs = array('_dx11', '_dx11_' . $pl_ID);
            foreach ($drugs as $drug) {
                $rsid = $wpdb->get_var("select id from ci_results_req where report_id = {$report_id} and drug = '{$drug}' ");


                if ($rsid > 0) {
                    $wpdb->update('ci_results_req', array(
                        'conc' => $conc,
                        'pos-neg' => $posneg,
                    ), array('id' => $rsid));
                } else {
                    $wpdb->insert('ci_results_req', array(
                        'report_id' => $report_id,
                        'drug' => $drug,
                        'conc' => $conc,
                        'pos-neg' => $posneg,
                    ));
                }
            }
        }
    }

    if (count($fail_list) > 0) {

        $mids = $wpdb->get_col("select mid from ci_batches_req where assen in ('" . implode("','", $fail_list) . "') ");

        //check exist in other racks
        $existrerun = $wpdb->get_var("select count(*) as c from ci_racks ra 
        JOIN ci_rack_batches rb ON rb.re_ID = ra.re_ID
        where rb.mid in (" . implode(',', $mids) . ") and rb.btype = 'req'
        and ra.re_ID > {$rack['re_ID']} 
        and ra.re_is_uti_panel = {$rack['re_is_uti_panel']} ");
        if (!$existrerun) {
            $new_rack = $rack;
            unset($new_rack['re_ID']);
            $new_rack['re_subtitle'] = 'Re-extraction Rack #' . $rack['re_number'];
            $new_rack['re_number'] = $rack['re_number'] + 1;
            $new_rack['re_created'] = date_i18n('Y-m-d H:i:s');
            $wpdb->insert('ci_racks', $new_rack);

            $new_rack_id = $wpdb->insert_id;
            if ($new_rack_id > 0) {
                foreach ($mids as $mid) {
                    $wpdb->insert('ci_rack_batches', array(
                        're_ID' => $new_rack_id,
                        'mid' => $mid,
                        'btype' => 'req',
                        'is_rerun' => 2,
                        'reason_rerun' => 'qc1'
                    ));
                }
            }
        }
    }

    $wpdb->update('ci_plates', array('other_data' => json_encode($otherdata)), array('pl_ID' => $pl_ID));

    wp_send_json($return);
} else if ($act == 'upload_qx200') {

    include_once(trpdd_get_file_path('req', 'functions_req_qc'));

    $return     = array('status' => 'ok', 'list' => array());
    $pl_ID      = $_REQUEST['pl_ID'];
    $file       = $_FILES['file'];
    $logdir     = WP_CONTENT_DIR . '/log/results_req/';
    if (!file_exists($logdir)) {
        wp_mkdir_p($logdir);
    }
    $filename = $file['name'];
    $file_path = $logdir . $filename;
    file_put_contents($file_path, file_get_contents($file['tmp_name']));
    $data = trpdd_get_data($file_path, 'csv', array(), true, false, true);


    $lqc            = 10000;
    $fail_list      = array();
    $passqc2_bywell     = array();
    $passqc3_bywell     = array();

    $all_qc2_pass       = true;
    $all_qc3_pass       = true;
    $all_qc4_pass       = true;
    $all_qc5_pass       = true;
    $pcdata             = array();


    foreach ($data['data'] as $row) {
        $well   = trpdd_get_string_exist($row, array('well', 'wellname'));
        $assen  = trpdd_get_string_exist($row, array('samplename', 'sampledescription1'));
        $assen2 = trpdd_get_string_exist($row, array('samplename2', 'sampledescription2'));
        $target = trpdd_get_string_exist($row, array('target', 'targetname'));
        $accepted_droplets   = trpdd_get_string_exist($row, array('accepteddroplets', 'conc'));
        $conc  = trpdd_get_string_exist($row, array('conccopies/µl', 'concntc'));
        $positives = trpdd_get_string_exist($row, array('positives'));
        $negatives = trpdd_get_string_exist($row, array('negatives'));

        $assen2 = str_replace(' ', '', strtoupper($assen2));

        $pass_qc2       = $accepted_droplets > $lqc ? 1 : 0;
        $well           = trim($well);
        if (empty($assen) || empty($well)) {
            continue;
        }

        //remove zero in well number
        $wellchar   = strtoupper(substr($well, 0, 1));
        $wellnum    = substr($well, 1);
        $well       = $wellchar . intval($wellnum);

        $pctype = $wellchar == 'M' ? 'M' : 'P';


        $existid = $wpdb->get_var("select ID from ci_req_qx200 
            where well='{$well}' 
            and desc1 = '{$assen}'
            and desc2 = '{$assen2}'
            and target = {$target}
            and pctype = '{$pctype}'
            and plate_ID = {$pl_ID} ");

        $qx200data = array(
            'plate_ID' => $pl_ID,
            'desc1' => $assen,
            'desc2' => $assen2,
            'well' => $well,
            'pctype' => $pctype,
            'target' => $target,
            'conc' => substr($conc, 0, 15),
            'accepted_droplets' => substr($accepted_droplets, 0, 15),
            'positives' => substr($positives, 0, 15),
            'negatives' => substr($negatives, 0, 15),
            'pass_qc2' => $pass_qc2,
        );

        //check QC3
        if ($assen == 'NTC') {
            $pass_qc3 = $conc < 4 ? 1 : 0;
            if (!$pass_qc3) {
                $all_qc3_pass = true;
            }
            $qx200data['pass_qc3'] = $pass_qc3;
        }


        if ($existid == 0) {
            $wpdb->insert('ci_req_qx200', $qx200data);
        } else {
            $wpdb->update('ci_req_qx200', $qx200data, array('ID' => $existid));
        }
    }

    tr_pdd_qc_check_all($pl_ID);


    wp_send_json($return);
} else if ($act == 'pass_qc') {
    $return     = array('status' => 'ok');
    $pl_ID      = $_REQUEST['pl_ID'];
    $assen      = $_REQUEST['assen'];
    $reason      = $_REQUEST['reason'];
    $qc         = $_REQUEST['qc'];
    $well         = $_REQUEST['well'];
    $user       = wp_get_current_user();
    $email      = $user->user_email;
    $comments = "Passed QC{$qc} by {$email} at " . date('m/d/Y H:i:s');
    if (!empty($reason)) {
        $comments .= ' (' . $reason . ')';
    }

    $plate = $wpdb->get_row("select * from ci_plates where pl_ID = {$pl_ID} ", ARRAY_A);


    if ($qc == 1) {
        $report_id = $wpdb->get_var("select id from ci_reports_req where patient_id = '{$assen}'  ");
        if ($report_id > 0) {
            $drug = '_dx11_pass_' . $pl_ID;
            $rsid = $wpdb->get_var("select id from ci_results_req where report_id = {$report_id} and drug = '{$drug}' ");
            $posneg = 'POS';
            if ($rsid > 0) {
                $wpdb->update('ci_results_req', array(
                    'pos-neg' => $posneg,
                    'comments' => $comments,
                ), array('id' => $rsid));
            } else {
                $wpdb->insert('ci_results_req', array(
                    'report_id' => $report_id,
                    'drug' => $drug,
                    'pos-neg' => $posneg,
                    'comments' => $comments,
                ));
            }
        }
    } else if ($qc == 4 || $qc == 5) {

        $otherdata      = json_decode($plate['other_data'], true);
        $otherdata['pass_qc' . $qc . '_hand'] = 1;
        $otherdata['comment_pass_qc' . $qc] = $comments;
        $wpdb->update('ci_plates', array('other_data' => json_encode($otherdata)), array('pl_ID' => $pl_ID));
        $pass_all_qc = tr_pdd_qc_check_all($pl_ID);
    } else {
        $existid = $wpdb->get_var("select ID from ci_req_qx200 
            where well='{$well}' 
            and pass_qc{$qc} = 0
            and plate_ID = {$pl_ID} ");

        $qx200data = array(
            'pass_qc' . $qc . '_hand' => 1,
            'comments_qc' . $qc => $comments,
        );
        $wpdb->update('ci_req_qx200', $qx200data, array('ID' => $existid));

        $pass_all_qc = tr_pdd_qc_check_all($pl_ID);
    }


    wp_send_json($return);
}
