<?php
global $wpdb;
$barcode_length = tr_get_option('ci_pdf_data', 'barcode_length');
if (empty($barcode_length)) $barcode_length = 8;

$id         = $_REQUEST['id'];
$studyba    = $wpdb->get_row("select * from {$re_batch_tbl} where ID = {$id}", ARRAY_A);
$irbs       = $wpdb->get_results("select * from ci_study_irbs order by iorder asc");
$sites      = $wpdb->get_results('select * from ci_study_sites order by `desc`');

$ncolumns = 4;

if ($re_type === 1) {
    $ncolumns = 6;
}
$max_slides = 0;
$studies    = $wpdb->get_results("select st.*,ba.assen 
from ci_study st 
LEFT JOIN ci_batches_study ba ON ba.project_ID = st.ID and oldreq = '{$studyba['code']}'
where st.istatus=1 
order by st.project_name asc");
$ci_all_studies = array();
foreach ($studies as $r) {
    $r->time_points = json_decode($r->time_points, true);
    $ci_all_studies[$r->ID] = $r;

    $max_slides += $r->max_slides;
}


if ($_REQUEST['trajax'] == 'save') {
    $return = array('status' => 'ok', 'errors' => array());

    $exit_cols = $wpdb->get_col("explain {$re_batch_tbl}");



    $data = $_POST['formdata'];



    $data['study_assigned'] = is_array($_POST['study_assigned']) ? implode(',', $_POST['study_assigned']) : '';
    $data['timepoint']      = is_array($_POST['timepoint']) ? implode(',', $_POST['timepoint']) : '';
    $tmp_conditions      = is_array($_POST['condition']) ? $_POST['condition'] : array();
    $data['condition'] = array();
    //var_dump($_POST['condition']);
    foreach ($_POST['timepoint'] as $num => $tp) {
        $arr = isset($tmp_conditions[$num]) ? $tmp_conditions[$num] : '';
        $data['condition'][$num] = is_array($arr) ? implode(';', $arr) : '';
    }

    //var_dump($data['condition']);exit;
    $data['condition'] = implode(',', $data['condition']);





    $data['doc'] = $data['doc1'] . ' ' . $data['doc2'];
    unset($data['doc1']);
    unset($data['doc2']);

    $data['slides'] = array();

    foreach ($_POST['slides']['assen'] as $i => $slideassen) {
        $studycb = isset($_POST['slides']['study'][$i]) ? $_POST['slides']['study'][$i] : 0;
        $rejectedcb = isset($_POST['slides']['rejected'][$i]) ? $_POST['slides']['rejected'][$i] : 0;
        $slide = array(
            'assen' => $slideassen,
            'doc' => $_POST['slides']['doc1'][$i] . ' ' . $_POST['slides']['doc2'][$i],
            'slideid' => $_POST['slides']['slideid'][$i],
            'study' => $studycb,
            'rejected' => $rejectedcb,
        );
        $data['slides'][] = $slide;
    }

    $data['slides'] = json_encode($data['slides']);

    foreach ($data as $k => $vl) {
        if (!in_array($k, $exit_cols)) {
            $datatype = 'text';
            if ($k == 'timepoint') {
                $datatype = 'text';
            } else if (stripos($k, 'num_') === 0) {
                $datatype = 'int(10)';
            } else if (stripos($k, 'time') !== false || $k == 'doc') {
                $datatype = 'datetime';
            } else if (stripos($k, 'date') !== false) {
                $datatype = 'date';
            }
            $wpdb->query("alter table {$re_batch_tbl} add column `{$k}` {$datatype};");
        }
    }

    $number_assen = $studyba['number_assen'];
    if (empty($number_assen)) {
        $number_assen = get_option("index_number_assen_study", 0) + 1;
        update_option('index_number_assen_study', $number_assen);
    }

    $number_assen =  sprintf('%0' . $barcode_length . 's', $number_assen);

    $return['number_assen'] = $number_assen;
    $data['number_assen'] = $number_assen;



    $wpdb->update($re_batch_tbl, $data, array('ID' => $id));
    if (!empty($wpdb->last_error)) {
        $return['errors'][] = $wpdb->last_error;
    }

    $assigned_studies = !empty($data['study_assigned']) ?  explode(',', $data['study_assigned']) : array();
    $assigned_timepoints = !empty($data['timepoint']) ?  explode(',', $data['timepoint']) : array();
    $assigned_conditions = !empty($data['condition']) ?  explode(',', $data['condition']) : array();




    $data['study_names'] = array();
    $data['study_edta'] = array();
    $data['study_citrate'] = array();
    $data['study_sst_tube'] = array();



    foreach ($assigned_studies as $i => $sid) {

        $timepoint_selected = $assigned_timepoints[$i];
        $condition_selecteds =  explode(';', $assigned_conditions[$i]);
        $return['$condition_selecteds'][] = $condition_selecteds;

        if (isset($ci_all_studies[$sid])) {
            $study = $ci_all_studies[$sid];

            $data['study_names'][]  = $study->project_name;
            $data['study_edta'][]   = $study->num_ml_edta;
            $data['study_citrate'][] = $study->num_ml_citrate;
            $data['study_sst_tube'][] = $study->num_sst_tube;



            //check & create req
            $old_data = $wpdb->get_row("select * from ci_batches_study where oldreq = '{$studyba['code']}' 
            and project_ID = {$sid} ", ARRAY_A);
            if (!empty($wpdb->last_error)) {
                $return['errors'][] = $wpdb->last_error;
            }

            $mid = $old_data && $old_data['mid'] > 0 ? $old_data['mid'] : 0;



            if (empty($study->short_name)) {
                $study->short_name = substr($study->project_name, 0, 3);
            }



            $batch_data = array(
                'project_ID' => $sid,
                'doc' => $data['doc'],
                'batch_id' => (int) date('Ymd', strtotime($data['doc'])),
                'oldreq' => $studyba['code'],
                'assen' => $study->short_name . $number_assen,
                'phase' => $study->phase,
                'timeframe' => $timepoint_selected,
                'receivingID' => $id,
                'conditions' => implode(';', $condition_selecteds),
            );

            if (!empty($studyba['patientid'])) {
                $batch_data['sequenceno'] = $studyba['patientid'];
            }

            if (empty($mid)) {
                $batch_data['spec_received'] = date_i18n('Y-m-d H:i:s');
            } else if (empty($old_data['spec_received'])) {
                $batch_data['spec_received'] = $studyba['created'];
            }

            $tests = $mid > 0 && !empty($old_data['panel_tests_requested']) ? explode(',', $old_data['panel_tests_requested']) : array();

            $time_points = $study->time_points;

            foreach ($time_points as $timepoint) {
                $timepoint['name'] = trim($timepoint['name']);

                if (strtolower($timepoint['name']) == strtolower($timepoint_selected)) {
                    foreach ($timepoint['conditions'] as $condition) {
                        if (in_array($condition['name'], $condition_selecteds)) {

                            foreach ($condition['tests'] as $t) {
                                if (!in_array($t, $tests)) {
                                    $tests[] = $t;
                                }
                            }
                        }
                    }
                    break;
                }
            }

            $batch_data['panel_tests_requested'] = implode(',', $tests);

            $return['$batch_data'] = $batch_data;
            $return['mid'] = $mid;
            if ($mid > 0) {
                $wpdb->update('ci_batches_study', $batch_data, array('mid' => $mid));

                if (!empty($wpdb->last_error)) {
                    $return['errors'][] = $wpdb->last_error;
                }
            } else {
                //insert new
                $batch_data['batch_created'] = date_i18n('Y-m-d H:i:s');

                $batch_data['author_ID'] = get_current_user_id();

                $wpdb->insert('ci_batches_study', $batch_data);

                if (!empty($wpdb->last_error)) {
                    $return['errors'][] = $wpdb->last_error;

                    if (stripos($wpdb->last_error, 'Duplicate') !== false) {
                        $number_assen = get_option("index_number_assen_study", 0) + 1;
                        update_option('index_number_assen_study', $number_assen);
                        $number_assen =  sprintf('%0' . $barcode_length . 's', $number_assen);
                        $wpdb->update($re_batch_tbl, array('number_assen' => $number_assen), array('ID' => $id));

                        $batch_data['assen'] = $study->short_name . $number_assen;
                        $wpdb->insert('ci_batches_study', $batch_data);
                    }
                }
            }
        }
    }
    $data['study_names'] = implode('<br>', $data['study_names']);
    $data['study_edta'] = implode('<br>', $data['study_edta']);
    $data['study_citrate'] = implode('<br>', $data['study_citrate']);
    $data['study_sst_tube'] = implode('<br>', $data['study_sst_tube']);

    $return['data'] = $data;
    wp_send_json($return);
}

$data   = $wpdb->get_row("select * from {$re_batch_tbl} where ID = {$id} ", ARRAY_A);

if (empty($data['doc']) || stripos($data['doc'], '0000') !== false) {
    $data['doc'] = date_i18n('Y-m-d');
}

$data['doc1']           = date('Y-m-d', strtotime($data['doc']));
$data['doc2']           = date('H:i', strtotime($data['doc']));
$assigned_studies       = !empty($data['study_assigned']) ?  explode(',', $data['study_assigned']) : array();
$assigned_timepoints    = !empty($data['timepoint']) ?  explode(',', $data['timepoint']) : array();
$assigned_conditions    = array();
if (!empty($data['condition'])) {
    foreach (explode(',', $data['condition']) as $cons) {
        $assigned_conditions[] = !empty($cons) ? explode(';', $cons) : array();
    }
}

$slides = json_decode($data['slides'], true);
if (empty($slides)) {
    $slides = array(array());
}

?>
<form method="post" class="receiving_req_form width1200" style="margin:20px auto;">
    <div class="frow">
        <div class="col2">
            <label>IRB</label>
            <select name="formdata[irb]">
                <?php
                foreach ($irbs as $irb) {
                    $selected = $irb->ID == $data['irb'] ? 'selected' : '';
                    echo '<option value="' . $irb->ID . '" ' . $selected . '>' . $irb->desc . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>Patient ID</label>
            <input type="text" name="formdata[patientid]" value="<?php echo $data['patientid'] ?>" />
        </div>

    </div>

    <div class="frow">
        <div class="col2">
            <label>Inclusion/Exclusion</label>
            <input type="text" name="formdata[inclusion]" value="<?php echo $data['inclusion'] ?>" />
        </div>
    </div>

    <div class="frow">
        <div class="col2">
            <label>Volume Of EDTA(tubes)</label>
            <select name="formdata[edta]" class="rebuild_assign">
                <?php
                for ($i = 0; $i <= 4; $i++) {
                    $selected = $i == $data['edta'] ? 'selected' : '';
                    echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>&nbsp;&nbsp;&nbsp;&nbsp;</label><br>
            <input type="text" name="formdata[edta_value]" class="rebuild_assign" value="<?php echo $data['edta_value'] ?>" style="width:50%" />
            <small class="normal">(ml per tube)</small>
        </div>
        <div class="col4">
            <label>EDTA Sample ID</label>
            <input type="text" name="formdata[edta_id]" value="<?php echo $data['edta_id'] ?>" />
        </div>
    </div>

    <div class="frow">
        <div class="col2">
            <label>Volume Of Citrate(tubes)</label>
            <select name="formdata[citrate]" class="rebuild_assign">
                <?php
                for ($i = 0; $i <= 4; $i++) {
                    $selected = $i == $data['citrate'] ? 'selected' : '';
                    echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>&nbsp;&nbsp;&nbsp;&nbsp;</label><br>
            <input type="text" name="formdata[citrate_value]" class="rebuild_assign" value="<?php echo $data['citrate_value'] ?>" style="width:50%" />
            <small>(ml per tube)</small>
        </div>
        <div class="col4">
            <label>Citrate Sample ID</label>
            <input type="text" name="formdata[citrate_id]" value="<?php echo $data['citrate_id'] ?>" />
        </div>
    </div>

    <div class="frow">
        <div class="col2">
            <label>Volume Of SST Tube</label>
            <select name="formdata[sst_tube]" class="rebuild_assign">
                <?php
                for ($i = 0; $i <= 4; $i++) {
                    $selected = $i == $data['sst_tube'] ? 'selected' : '';
                    echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>&nbsp;&nbsp;&nbsp;&nbsp;</label><br>
            <input type="text" name="formdata[sst_tube_value]" class="rebuild_assign" value="<?php echo $data['sst_tube_value'] ?>" style="width:50%" />
            <small>(ml per tube)</small>
        </div>
        <div class="col4">
            <label>SST Sample ID</label>
            <input type="text" name="formdata[sst_tube_id]" value="<?php echo $data['sst_tube_id'] ?>" />
        </div>
    </div>
    <div class="frow">
        <div class="col2">
            <label>Freezer Code</label>
            <input type="text" name="formdata[freezer_code]" value="<?php echo $data['freezer_code'] ?>" />
        </div>
    </div>

    <div class="frow">
        <div class="<?php echo 'col' . $ncolumns ?>">Slide accessions</div>
        <div class="<?php echo 'col' . $ncolumns ?>">Collection Date</div>
        <div class="<?php echo 'col' . $ncolumns ?>">Collection Time</div>
        <?php
        if ($re_type === 1) {
            echo '<div class="col' . $ncolumns . '">Study</div>';
        }
        ?>
        <div class="<?php echo 'col' . $ncolumns ?>">Slide ID</div>
        <?php
        if ($re_type === 1) {
            echo '<div class="col' . $ncolumns . '">Rejected</div>';
        }
        ?>
    </div>
    <div class="frow" id="listslides">
        <?php
        $slide_num = 0;
        foreach ($slides as $slide) {
            $slide_num++;
            if (empty($slide['assen'])) {
                $slide['assen'] = $studyba['code'] . '-' . ($slide_num < 10 ? '0' . $slide_num : $slide_num);
            }

            list($slide['doc1'], $slide['doc2']) = explode(' ', $slide['doc']);
        ?>
            <div class="item">
                <div class="<?php echo 'col' . $ncolumns ?>"><input type="text" class="assen" name="slides[assen][]" value="<?php echo $slide['assen'] ?>" readonly /></div>
                <div class="<?php echo 'col' . $ncolumns ?>"><input type="text" class="has_date" name="slides[doc1][]" value="<?php echo $slide['doc1'] ?>" /></div>
                <div class="<?php echo 'col' . $ncolumns ?>"><input type="text" class="has_time" name="slides[doc2][]" value="<?php echo $slide['doc2'] ?>" /></div>
                <?php
                if ($re_type === 1) {
                    echo '<div class="col' . $ncolumns . '">';
                    echo '<select name="slides[study][]" style="display:none">';
                    for ($i1 = 0; $i1 <= 1; $i1++) {
                        echo '<option value="' . $i1 . '" ' . ($slide['study'] == $i1 ? 'selected' : '') . '>' . $i1 . '</option>';
                    }
                    echo '</select>';
                    echo '<input type="checkbox" class="targetselect" value="1" ' . checked($slide['study'], 1, false) . '/>';
                    echo '</div>';
                }
                ?>
                <div class="<?php echo 'col' . $ncolumns ?>"><input type="text" name="slides[slideid][]" value="<?php echo $slide['slideid'] ?>" /></div>
                <?php
                if ($re_type === 1) {
                    echo '<div class="col' . $ncolumns . '">';
                    echo '<select name="slides[rejected][]" style="display:none">';
                    for ($i1 = 0; $i1 <= 1; $i1++) {
                        echo '<option value="' . $i1 . '" ' . ($slide['rejected'] == $i1 ? 'selected' : '') . '>' . $i1 . '</option>';
                    }
                    echo '</select>';
                    echo '<input type="checkbox" class="targetselect" value="1" ' . checked($slide['rejected'], 1, false) . '/></div>';
                }
                ?>
            </div>
        <?php
        }
        ?>
    </div>
    <a class="addslide" data-code="<?php echo $studyba['code'] ?>" data-num="<?php echo $slide_num ?>" data-max="<?php echo $max_slides ?>">+</a>

    <div class="frow">
        <div class="col3">
            <label>Collection Date</label>
            <input type="text" class="has_date" name="formdata[doc1]" value="<?php echo $data['doc1'] ?>" />
        </div>
        <div class="col3">
            <label>Collection Time</label>
            <input type="text" class="has_time" name="formdata[doc2]" value="<?php echo $data['doc2'] ?>" />
        </div>
        <div class="col3">
            <label>Collection Site</label>
            <select name="formdata[siteid]">
                <?php
                echo '<option value=""></option>';
                foreach ($sites as $site) {
                    $selected = $site->ID == $data['siteid'] ? 'selected' : '';
                    echo '<option value="' . $site->ID . '" ' . $selected . '>' . $site->desc . '</option>';
                }
                ?>
            </select>
        </div>
    </div>

    <div class="frow">
        <div class="col3">
            <div class="frow">
                <label>Signature</label>
                <?php
                //created_by
                $createdbyuser = get_user_by('id', $data['created_by']);
                $signature = get_user_meta($data['created_by'], '_signature', true);
                if (!empty($signature)) {
                    $dir = tr_get_signature_dir();
                    $signature_url = $dir['url'] . '/' . $signature;
                    echo '<br><img src="' . $signature_url . '" style="max-height:60px;width:auto"/> ';
                }
                echo '<br><a href="mailto:' . $createdbyuser->user_email . '">' . $createdbyuser->first_name . ' ' . $createdbyuser->last_name . '</a>';
                ?>
            </div>

            <div class="frow">
                <label>Assign to</label>
                <select class="list_study_assign" data-list="<?php echo esc_attr(json_encode($ci_all_studies)) ?>"></select>
            </div>
        </div>
        <div class="col23">
            <div class="study_assigned" data-num="0" data-assigned="<?php echo esc_attr(json_encode($assigned_studies)) ?>" data-assigned_timepoints="<?php echo esc_attr(json_encode($assigned_timepoints)) ?>" data-assigned_conditions="<?php echo esc_attr(json_encode($assigned_conditions)) ?>"></div>
        </div>

    </div>

    <div class="frow">
        <input type="submit" class="button-primary" value="Save" />
        <a href="#" class="button reloadf">Reload</a>
        <a href="#" class="button closechildcontent">close</a>
        <input type="hidden" name="trajax" value="save" />
        <input type="hidden" name="act" value="receiving_req_form" />
        <input type="hidden" name="id" value="<?php echo $id ?>" />
    </div>
</form>