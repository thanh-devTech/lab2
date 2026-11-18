<?php
global $wpdb;
$id =$_GET['id'];


include_once(TRCPDD_PATH.'pages/formdata.php');

$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'].'/logos/';
$logo_url = $upload_dir['baseurl'].'/logos/';
$docx_dir = $upload_dir['basedir'].'/docx/';



$save_to_folder = "unassigned";

/* ===================================== */
/* ==  Get report info from database  == */
/* ===================================== */
$get_pairs_q = $wpdb->get_results("SELECT * FROM ci_drug_analyte_pairs", ARRAY_A);
foreach($get_pairs_q as $g) {
    $new_mb = array(); // for (MB) additions
    $drug_pairs[$g['drug_1']] = array($g['drug_2'],$g['drug_3'],$g['drug_4']);
    $new_mb['analytes'][] = $g['drug_1'];
    $drug_pairs[$g['drug_2']] = array($g['drug_1'],$g['drug_3'],$g['drug_4']);
    $new_mb['analytes'][] = $g['drug_2'];
    if(!empty($g['drug_3'])) {
        $drug_pairs[$g['drug_3']] = array($g['drug_1'],$g['drug_2'],$g['drug_4']);
        $new_mb['analytes'][] = $g['drug_3'];
    }
    if(!empty($g['drug_4'])) {
        $drug_pairs[$g['drug_4']] = array($g['drug_1'],$g['drug_2'],$g['drug_3']);
        $new_mb['analytes'][] = $g['drug_4'];
    }

    $new_mb['new_comment'] = $g['new_comment'];
    $new_mb['analyte_replace'] = json_decode($g['analyte_replace'], true);
    $mb_replace[] = $new_mb;
}
// codeine, morphine, hydrocodone override
$drug_pairs['CODEINE'] = array("MORPHINE","HYDROCODONE");
$drug_pairs['MORPHINE'] = array("HYDROMORPHONE");
$drug_pairs['6-MAM'] = array("MORPHINE","HYDROMORPHONE","CODEINE");
$drug_pairs['HYDROCODONE'] = array("HYDROMORPHONE");
//

$get_q = $wpdb->get_row("SELECT info FROM ci_reports WHERE id='". $_GET['id'] ."'", ARRAY_A);
$g = $get_q;
$info = json_decode($g['info'], true);

$collection_date = str_replace("/","-",$info['collection_date']);
$received_date = str_replace("/","-",$info['received_in_lab']);
$positives = array();

$all_results_q = $wpdb->get_results("SELECT * FROM ci_results WHERE report_id='". $_GET['id'] ."' ", ARRAY_A);
$all_total = count($all_results_q);
foreach($all_results_q as $a) {
    $arr = json_decode($a['val'], true);
    $all_res[$arr['drug']] = $arr;
}

$drug_groups = array("Natural and Semi-Synthetic Opioids" => array(),
    "Synthetic Opioids" => array(),
    "Benzodiazapines" => array(),
    "Stimulants" => array(),
    "Illicits" => array());
$groups_q = $wpdb->get_results("SELECT cmpd, `group` FROM ci_cutoffs WHERE `group`!='Other' AND active='1' ORDER BY cmpd ASC", ARRAY_A);
foreach($groups_q as $g) {
    $drug_groups[$g['group']][] = $g['cmpd'];
}

$matrix = (stripos($info['sample_type'],"oral")!==false) ? "oral" : strtolower($info['sample_type']);
$ci_cutoffs_q = $wpdb->get_results("SELECT * FROM ci_cutoffs WHERE matrix='". $matrix ."' AND active='1'", ARRAY_A);
foreach($ci_cutoffs_q as $c){
    $ci_cutoffs[$c['cmpd']] = (float)$c['cutoff'];
}


if(!empty($info['sublab'])) {
    $header = $wpdb->get_row("SELECT * FROM ci_labs WHERE lab_dba_init='" . $info['sublab'] . "' or lab_dba='" . $info['sublab'] . "' limit 1", ARRAY_A);
    if(!$header)
    {
        $header = $wpdb->get_row("SELECT * FROM ci_labs WHERE lab_dba_init like '" . $info['sublab'] . " lab%' or lab_dba like '" . $info['sublab'] . " lab%' limit 1", ARRAY_A);
    }
}else{
    //find lab from filename
    $info['sublab'] = end(explode(' ',$sublab));
    if(!empty($sublab))
    {
        $header = $wpdb->get_row("SELECT * FROM ci_labs WHERE lab_dba_init like '" . $info['sublab'] . " lab%' or lab_dba like '" . $info['sublab'] . " lab%' limit 1", ARRAY_A);
    }
}


if($header)
{
    $header['lab_url'] = trpdd_get_lab_url($header['lab_site']);

    $sublab = preg_replace("/[^a-zA-Z]/","",strtolower($info['sublab']));
    if(!empty($sublab)) $save_to_folder = $sublab;

    $doc = date('Y-m-d',strtotime($info['collection_date']));
    $sql = "select lab.* from ci_labs lab
                            JOIN ci_batches ba ON ba.ref_lab_ID = lab.lab_ID
                            WHERE ba.doc='{$doc}'
                            and ba.patient_name = '{$info['patient_name']}'
                            and ba.lab_id = {$header['lab_ID']} limit 1";

    $ref_lab = $wpdb->get_row($sql);
}

?>
<h2>Edit</h2>

<form method="post">
    <div class="frow">
        <div class="col2">
            <label>Address</label>
            <input type="text" name="lab[lab_address]" value="<?php echo $header['lab_address']?>">
        </div>
    </div>
    <div class="frow">
        <div class="col4">
            <label>Phone</label>
            <input type="text" name="lab[lab_phone]" value="<?php echo $header['lab_phone']?>">
        </div>
        <div class="col4">
            <label>CLIA #</label>
            <input type="text" name="lab[lab_clia]" value="<?php echo $header['lab_clia']?>">
        </div>

        <div class="col4">
            <label>Fax</label>
            <input type="text" name="lab[lab_fax]" value="<?php echo $header['lab_fax']?>">
        </div>
        <div class="col4">
            <label>NPI # </label>
            <input type="text" name="lab[lab_npi]" value="<?php echo $header['lab_npi']?>">
        </div>
    </div>

    <div class="frow">
        <h4>&nbsp;</h4>
    </div>

    <div class="frow">
        <div class="col4">
            <label>Patient Name</label>
            <input type="text" name="info[patient_name]" value="<?php echo $info['patient_name']?>">
        </div>
        <div class="col4">
            <label>Physician</label>
            <input type="text" name="info[physician]" value="<?php echo $info['physician']?>">
        </div>

        <div class="col4">
            <label>Patient ID</label>
            <input type="text" name="info[patient_id]" value="<?php echo $info['patient_id']?>">
        </div>
        <div class="col4">
            <label>Sublab</label>
            <input type="text" name="info[sublab]" value="<?php echo $info['sublab']?>">
        </div>
    </div>

    <div class="frow">
        <div class="col4">
            <label>Patient DOB</label>
            <input type="text" name="info[patient_dob]" value="<?php echo $info['patient_dob']?>">
        </div>

        <div class="col4">
            <label>Acquisition Date</label>
            <input type="text" name="info[acquisition_date]" value="<?php echo $info['acquisition_date']?>">
        </div>
        <div class="col4">
            <label>Sample Type</label>
            <input type="text" name="info[sample_type]" value="<?php echo $info['sample_type']?>">
        </div>
    </div>

    <div class="frow">
        <div class="col4">
            <label>Collection Date</label>
            <input type="text" name="info[collection_date]" value="<?php echo $info['collection_date']?>">
        </div>
        <div class="col4">
            <label>Received in Lab</label>
            <input type="text" name="info[received_in_lab]" value="<?php echo $info['received_in_lab']?>">
        </div>
        <div class="col2">
            <label>Medications</label>
            <input type="text" name="info[medications]" value="<?php echo $info['medications']?>">
        </div>
    </div>





    <p>Summary Report:
        <?php if(empty($info['medications']) || strtolower($info['medications'])=="none listed") {
            echo (!empty($res_inconsis2)) ? ' When no medications are listed, detected drugs are reported as expected negatives' : 'No drugs from this panel detected';
        } elseif(empty($res_consistent) && empty($res_inconsis1) && empty($res_inconsis2)) {
            echo 'No drugs from this panel detected','fontNormal','normal';
        }
        ?>
    </p>
    <div class="frow"><h4>LCMS Confirmation TEST</h4></div>

    <div class="frow"><h4>Consistent Results-Reported Medication Detected</h4></div>

    <div class="frow">
        <div class="col5">
            <label>Prescription</label>
        </div>
        <div class="col5">
            <label>Anticipated</label>
        </div>

        <div class="col5">
            <label>Detected (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Cutoff (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Comments</label>
        </div>
    </div>
    <div>

        <?php
        if(is_array($res_consistent)) {
            foreach($res_consistent as $drug => &$arr) {
                // oxycodone/hydrocodone rules
                if($arr['prescrip']=="OXYCODONE" && $arr['detected']=="OXYMORPHONE" && !array_key_exists("OXYCODONE",$res_consistent)) {
                    if(empty($res_inconsis1["OXYMORPHONE"])) {
                        $res_inconsis1['OXYMORPHONE'] = array("prescrip" => strtoupper($drug),
                            "anticip" => $arr['anticip'],
                            "detected" => "NEG",
                            "cutoff" => $ci_cutoffs[$drug],
                            "comments" => $arr['Detection Window 2-4 days']);
                    }
                    continue;
                }
                if($arr['prescrip']=="HYDROCODONE" && $arr['detected']=="HYDROMORPHONE" && !array_key_exists("HYDROCODONE",$res_consistent)) {
                    if(empty($res_inconsis1["HYDROMORPHONE"])) {
                        $res_inconsis1['HYDROMORPHONE'] = array("prescrip" => strtoupper($drug),
                            "anticip" => $arr['anticip'],
                            "detected" => "NEG",
                            "cutoff" => $ci_cutoffs[$drug],
                            "comments" => $arr['Detection Window 2-4 days']);
                    }
                    continue;
                }

                $arr['prescrip'] = array($arr['prescrip']);
                $arr['anticip'] = array($arr['anticip']);
                $arr['detected'] = array($arr['detected']);
                $arr['cutoff'] = array($arr['cutoff']);

                // check if more than one medication has the same analyte
                $anticip_arr = array();
                $meds_to_combine = array();
                foreach($res_by_med as $rbm) {
                    $meds_to_combine[$rbm['anticip']][] = $rbm['prescrip'];
                }

                // hydromorphone override
                //$drug_pairs['HYDROMORPHONE'] = ($arr['prescrip'] == "NORCO") ? array("") : array("MORPHINE","HYDROCODONE","6-MAM","CODEINE");
                $drug_pairs['HYDROMORPHONE'] = array("MORPHINE"); // should only ever be the second array for heroin, which is not prescribed
                //

                foreach($meds_to_combine as $key => $mtc) {
                    if(count($mtc) > 1 && ($key == $drug || @in_array(strtoupper($key), $drug_pairs[strtoupper($drug)]))) {
                        $has_combined_med = 0; // starts at 1
                        $new_prescrip = array();
                        foreach($mtc as $m) {
                            $new_prescrip[] = $m;
                            $has_combined_med++;
                            $consis_total--;
                        }

                        if(strcasecmp(str_replace(" ","",$arr['comments']),"RX-". $arr['prescrip'][0])==0) {
                            $arr['comments'] = "RX-";
                            foreach($new_prescrip as $new) {
                                $arr['comments'] .= $new;
                                if($new != end($new_prescrip)) $arr['comments'] .= ", ";
                            }
                        }
                        $arr['prescrip'] = $new_prescrip;
                    }
                }

                // check if its pair is also in the results, and if so combine rows
                $has_combined = 0;
                $pair = array();
                $line_anticip = $line_detected = $line_cutoff = array();
                if(isset($drug_pairs[$drug]) && (!empty($res_consistent[$drug_pairs[$drug][0]]) || !empty($res_consistent[$drug_pairs[$drug][1]])
                        || !empty($res_consistent[$drug_pairs[$drug][2]]) || !empty($res_consistent[$drug_pairs[$drug][3]]))) {

                    if(!isset($res_consistent[$drug])) continue; // skip over iterations that have been unset by pairing code
                    $pair[] = $arr['anticip'][0];
                    for($i=0;$i<4;$i++) {
                        if(!empty($res_consistent[$drug_pairs[$drug][$i]])) $pair[] = $res_consistent[$drug_pairs[$drug][$i]]['anticip'];
                    }

                    foreach($pair as &$p) {
                        if(is_array($p)) $p = $p[0];
                        if(array_key_exists($p,$res_consistent)) {
                            $line_anticip[] = (is_array($res_consistent[$p]['anticip'])) ? $res_consistent[$p]['anticip'][0] : $res_consistent[$p]['anticip'];
                            $line_detected[] = (is_array($res_consistent[$p]['detected'])) ? $res_consistent[$p]['detected'][0] : $res_consistent[$p]['detected'];
                            $line_cutoff[] = (is_array($res_consistent[$p]['detected'])) ? $res_consistent[$p]['cutoff'][0] : $res_consistent[$p]['cutoff'];
                            $has_combined++;
                            $consis_total--;
                            unset($res_consistent[$p]);
                        }
                    }

                    $arr['anticip'] = $line_anticip;
                    $arr['detected'] = $line_detected;
                    $arr['cutoff'] = $line_cutoff;
                }

                // check for below-cutoff logic
                include "below_logic.php";
                //

                // add (MB)s
                if(is_array($arr['anticip'])) {
                    if( (isset($drug_pairs[$drug]) || $use_below) &&  // $use_below equals true from below_logic.php
                        (in_array($drug_pairs[$drug][0],$arr['anticip']) || in_array($drug_pairs[$drug][1],$arr['anticip'])
                            || in_array($drug_pairs[$drug][2],$arr['anticip']) || in_array($drug_pairs[$drug][3],$arr['anticip']))
                    ) {
                        // (MB) override)
                        if($use_below && is_array($arr['anticip'])) { // rebuild updated drug pairing, if below_logic.php was used
                            $pair = $arr['anticip'];
                        }
                        foreach($mb_replace as $mb) {
                            if(count($mb['analytes']) != count($pair) && !$use_below) continue;
                            $skip = false;

                            foreach($pair as $p) {
                                if(!in_array($p,$mb['analytes'])) $skip = true;
                            }
                            if($skip) continue;

                            if(is_array($mb['analyte_replace'])) : foreach($mb['analyte_replace'] as $orig => $repl) :
                                if(in_array($orig, $arr['anticip'])) {
                                    $key = array_search($orig, $arr['anticip']);
                                    $arr['anticip'][$key] = $repl;
                                }
                            endforeach; endif;
                            break;
                        }
                        // manual (MB) overrides for groups that contain other groups as subgroups (i.e. Nor, Ox, Temazepam overwriting Ox and Tempazpam)
                        $manual_mb = array(
                            array(array("TEMAZEPAM","OXAZEPAM"),array("TEMAZEPAM","OXAZEPAM (MB)")),
                            array(array("HYDROCODONE","HYDROMORPHONE"),array("HYDROCODONE","HYDROMORPHONE (MB)")),
                            array(array("CODEINE","MORPHINE","HYDROCODONE"),array("CODEINE","MORPHINE (MB)","HYDROCODONE (MB)")),
                            array(array("CODEINE","MORPHINE"),array("CODEINE","MORPHINE (MB)")),
                            array(array("MORPHINE","HYDROMORPHONE"),array("MORPHINE","HYDROMORPHONE (MB)")),
                            array(array("NORDIAZEPAM","TEMAZEPAM"),array("NORDIAZEPAM (MB)","TEMAZEPAM (MB)")),
                        );
                        foreach($manual_mb as $group) {
                            if(count($arr['anticip'])==count($group[0])) {
                                $in_array = true;
                                foreach($group[0] as $g) {
                                    if(!in_array($g,$arr['anticip'])) $in_array = false;
                                }
                                if(!$in_array) continue;

                                foreach($group[1] as $g) {
                                    $key = array_search(str_replace(" (MB)","",$g),$arr['anticip']);
                                    if($key !== false) {
                                        $arr['anticip'][$key] = $g;
                                    }
                                }
                            }
                        }

                        // Norco override
                        if(($arr['prescrip']=="NORCO" || @in_array("NORCO",$arr['prescrip'])) && @in_array("HYDROCODONE",$arr['anticip']) && @in_array("HYDROMORPHONE (MB)",$arr['anticip'])) {
                            $arr['comments'] = "RX - NORCO";
                        }
                        //
                        // Xanax override
                        if(($arr['prescrip']=="XANAX" || @in_array("XANAX",$arr['prescrip'])) && @in_array("ALPRAZOLAM",$arr['anticip']) && @in_array("HYDROXYALPRAZOLAM (MB)",$arr['anticip'])) {
                            $arr['comments'] = "RX – Xanax";
                        }
                        //
                        // 7 Amino override
                        if($arr['anticip']=="7 AMINOCLONAZEPAM" || @in_array("7 AMINOCLONAZEPAM",$arr['anticip'])) {
                            $key = @array_search("7 AMINOCLONAZEPAM",$arr['anticip']);
                            $arr['anticip'][$key] = "7 AMINOCLONAZEPAM (MB)";
                        }
                        //
                        // end manual (MB) overrides
                    }
                    if(in_array("OXYMORPHONE",$arr['anticip'])) {
                        $key = array_search("OXYMORPHONE",$arr['anticip']);
                        $arr['anticip'][$key] = "OXYMORPHONE (MB)";
                    }
                    if(in_array("HYDROMORPHONE",$arr['anticip'])) {
                        $key = array_search("HYDROMORPHONE",$arr['anticip']);
                        $arr['anticip'][$key] = "HYDROMORPHONE (MB)";
                    }
                }

                if(count($arr['anticip']) == count($arr['detected'])) {
                    $curr_index = 0;
                    $new_anticip = $new_detected = $new_cutoff = $end_anticip = $end_detected = $end_cutoff = array();
                    foreach($arr['anticip'] as $a) {
                        if(strpos($a,"(MB)")===false) {
                            $new_anticip[] = $a;
                            $new_detected[] = $arr['detected'][$curr_index];
                            $new_cutoff[] = $arr['cutoff'][$curr_index];
                        } else {
                            $end_anticip[] = $a;
                            $end_detected[] = $arr['detected'][$curr_index];
                            $end_cutoff[] = $arr['cutoff'][$curr_index];
                        }
                        $curr_index++;
                    }
                    $arr['anticip'] = array_merge($new_anticip,$end_anticip);
                    $arr['detected'] = array_merge($new_detected,$end_detected);
                    $arr['cutoff'] = array_merge($new_cutoff,$end_cutoff);
                }
                //





                ?>
                <div class="frow">
                    <div class="col5">
                        <textarea name="res_co[<?php echo $drug?>][prescrip]"><?php echo (is_array($arr['prescrip']))? implode("\n",$arr['prescrip']):$arr['prescrip']?></textarea>
                    </div>
                    <div class="col5">
                        <textarea name="res_co[<?php echo $drug?>][anticip]"><?php echo (is_array($arr['anticip']))? implode("\n",$arr['anticip']):$arr['anticip']?></textarea>

                    </div>
                    <div class="col5">
                        <textarea name="res_co[<?php echo $drug?>][detected]"><?php echo (is_array($arr['detected']))? implode("\n",$arr['detected']):$arr['detected']?></textarea>
                    </div>
                    <div class="col5">
                        <textarea name="res_co[<?php echo $drug?>][cutoff]"><?php echo (is_array($arr['cutoff']))? implode("\n",$arr['cutoff']):$arr['cutoff']?></textarea>
                    </div>
                    <div class="col5">
                        <textarea name="res_co[<?php echo $drug?>][comments]"><?php echo (is_array($arr['comments']))? implode("\n",$arr['comments']):$arr['comments']?></textarea>
                    </div>
                </div>
            <?php }
        }

        ?>
    </div>

    <div class="frow"><h4>Inconsistent Results-Expected Positives for Reported Medication</h4></div>
    <div class="frow">
        <div class="col5">
            <label>Prescription</label>
        </div>
        <div class="col5">
            <label>Anticipated</label>
        </div>

        <div class="col5">
            <label>Detected (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Cutoff (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Comments</label>
        </div>
    </div>

    <div>
        <?php
        if(is_array($res_inconsis1)) {
            foreach($res_inconsis1 as $drug => $arr) {
                $fontColor = ($arr['detected']=="NEG") ? 'fontNormalRed' : 'fontNormal';

                ?>
                <div class="frow">
                    <div class="col5">
                        <input type="text" name="res_inc1[prescrip][]" value="<?php echo $arr['prescrip']?>">
                    </div>
                    <div class="col5">
                        <input type="text" name="res_inc1[anticip][]" value="<?php echo $arr['anticip']?>">
                    </div>
                    <div class="col5">
                        <input type="text" name="res_inc1[detected][]" value="<?php echo $arr['detected']?>">
                    </div>
                    <div class="col5">
                        <input type="text" name="res_inc1[cutoff][]" value="<?php echo $arr['cutoff']?>">
                    </div>
                    <div class="col5">
                        <input type="text" name="res_inc1[comments][]" value="<?php echo $arr['comments']?>">
                    </div>
                </div>
                <?php
            }
        }
        ?>

    </div>

    <div class="frow"><h4>Inconsistent Results-Expected Negatives for Drugs that Test Positive</h4></div>
    <div class="frow">
        <div class="col5">
            <label>Analyte</label>
        </div>
        <div class="col5">
            <label>Detected (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Cutoff (ng/mL)</label>
        </div>
        <div class="col5">
            <label>Typical detection window</label>
        </div>
        <div class="col5">
            <label>Comments</label>
        </div>
    </div>

    <div>
        <?php
        if(is_array($res_inconsis2)) {
            foreach($res_inconsis2 as $drug => &$arr) {
                // check if its pair is also in the results, and if so combine rows
                $has_combined = 0;
                $pair = array();
                $line_analyte = $line_detected = $line_cutoff = array();
                // heroin override
                $drug_pairs['HYDROMORPHONE'] = array("MORPHINE","HYDROCODONE","6-MAM","CODEINE");
                //
                if(isset($drug_pairs[$drug]) && (!empty($res_inconsis2[$drug_pairs[$drug][0]]) || !empty($res_inconsis2[$drug_pairs[$drug][1]])
                        || !empty($res_inconsis2[$drug_pairs[$drug][2]]) || !empty($res_inconsis2[$drug_pairs[$drug][3]]))) {
                    if(!isset($res_inconsis2[$drug])) continue; // skip over iterations that have been unset by pairing code
                    $pair[] = $arr['analyte'];
                    for($i=0;$i<4;$i++) {
                        if(!empty($res_inconsis2[$drug_pairs[$drug][$i]])) $pair[] = $res_inconsis2[$drug_pairs[$drug][$i]]['analyte'];
                    }

                    foreach($pair as $p) {
                        if(array_key_exists($p,$res_inconsis2)) {
                            $line_analyte[] = $res_inconsis2[$p]['analyte'];
                            $line_detected[] = $res_inconsis2[$p]['detected'];
                            $line_cutoff[] = $res_inconsis2[$p]['cutoff'];
                            $has_combined++;
                            $inconsis2_total--;
                            unset($res_inconsis2[$p]);
                        }
                    }
                    // (MB) override)
                    foreach($mb_replace as $mb) {
                        if(count($mb['analytes']) != count($pair)) continue;
                        $skip = false;
                        foreach($pair as $p) {
                            if(!in_array($p,$mb['analytes'])) $skip = true;
                        }
                        if($skip) continue;

                        if(!empty($mb['new_comment'])) $arr['comments'] = $mb['new_comment'];
                        if(is_array($mb['analyte_replace'])) : foreach($mb['analyte_replace'] as $orig => $repl) :
                            if(in_array($orig, $line_analyte)) {
                                $key = @array_search($orig, $line_analyte);
                                $line_analyte[$key] = $repl;
                            }
                        endforeach; endif;
                        break;
                    }
                    // manual (MB) overrides for groups that contain other groups as subgroups (i.e. Nor, Ox, Temazepam overwriting Ox and Tempazpam)
                    $manual_mb = array(
                        array(array("TEMAZEPAM","OXAZEPAM"),array("TEMAZEPAM","OXAZEPAM (MB)"),"NOT IN RX - TEMAZEPAM"),
                        array(array("HYDROCODONE","HYDROMORPHONE"),array("HYDROCODONE","HYDROMORPHONE (MB)"),"NOT IN RX - HYDROCODONE"),
                        array(array("CODEINE","MORPHINE","HYDROCODONE"),array("CODEINE","MORPHINE (MB)","HYDROCODONE (MB)"),"NOT IN RX - CODEINE"),
                        array(array("CODEINE","MORPHINE"),array("CODEINE","MORPHINE (MB)"),"NOT IN RX - CODEINE"),
                        array(array("HYDROMORPHONE","MORPHINE"),array("HYDROMORPHONE (MB)","MORPHINE"),"NOT IN RX - MORPHINE"),
                        array(array("6-MAM","MORPHINE","HYDROMORPHONE","CODEINE"),array("6-MAM","MORPHINE (MB)","HYDROMORPHONE (MB)","CODEINE (CON)"),"ILLICIT - HEROIN"),
                        array(array("NORDIAZEPAM","TEMAZEPAM"),array("NORDIAZEPAM (MB)","TEMAZEPAM (MB)"),"NOT IN RX - DIAZEPAM (Parent Drug)"),
                    );
                    foreach($manual_mb as $group) {
                        if(count($line_analyte)==count($group[0])) {
                            $in_array = true;
                            foreach($group[0] as $g) {
                                if(!in_array($g,$line_analyte)) $in_array = false;
                            }
                            if(!$in_array) continue;

                            foreach($group[1] as $g) {
                                $key = @array_search(str_replace(" (MB)","",$g),$line_analyte);
                                if($key !== false) {
                                    $line_analyte[$key] = $g;
                                }
                            }
                            $arr['comments'] = $group[2];
                        }
                    }
                    // end manual (MB) overrides

                    $arr['analyte'] = $line_analyte;
                    $arr['detected'] = $line_detected;
                    $arr['cutoff'] = $line_cutoff;

                    if(count($arr['analyte']) == count($arr['detected'])) {
                        $curr_index = 0;
                        $new_anticip = $new_detected = $new_cutoff = $end_anticip = $end_detected = $end_cutoff = array();
                        foreach($arr['analyte'] as $a) {
                            if(strpos($a,"(MB)")===false) {
                                $new_anticip[] = $a;
                                $new_detected[] = $arr['detected'][$curr_index];
                                $new_cutoff[] = $arr['cutoff'][$curr_index];
                            } else {
                                $end_anticip[] = $a;
                                $end_detected[] = $arr['detected'][$curr_index];
                                $end_cutoff[] = $arr['cutoff'][$curr_index];
                            }
                            $curr_index++;
                        }
                        $arr['analyte'] = array_merge($new_anticip,$end_anticip);
                        $arr['detected'] = array_merge($new_detected,$end_detected);
                        $arr['cutoff'] = array_merge($new_cutoff,$end_cutoff);
                    }
                }

                $fontColor = (substr($arr['comments'],0,7)=="ILLICIT") ? 'fontNormalRed' : 'fontNormal';



                ?>
                <div class="frow">
                    <div class="col5">
                        <textarea name="res_inc2[analyte]"><?php echo (is_array($arr['analyte']))? implode("\n",$arr['analyte']):$arr['analyte']?></textarea>
                    </div>
                    <div class="col5">
                        <textarea name="res_inc2[detected]"><?php echo (is_array($arr['detected']))? implode("\n",$arr['detected']):$arr['detected']?></textarea>

                    </div>
                    <div class="col5">
                        <textarea name="res_inc2[cutoff]"><?php echo (is_array($arr['cutoff']))? implode("\n",$arr['cutoff']):$arr['cutoff']?></textarea>
                    </div>
                    <div class="col5">
                        <textarea name="res_inc2[detect_win]"><?php echo (is_array($arr['detect_win']))? implode("\n",$arr['detect_win']):$arr['detect_win']?></textarea>
                    </div>
                    <td>
                        <textarea name="res_inc2[comments]"><?php echo (is_array($arr['comments']))? implode("\n",$arr['comments']):$arr['comments']?></textarea>
                    </td>
                </div>

                <?php
            }
        }

        ?>

    </div>

    <?php
    if($matrix!="oral") {
        ?>
        <div class="frow"><h4>SPECIMEN VALIDITY TEST</h4></div>
        <div class="frow">
            <div class="col4">
                <label>Test</label>
            </div>
            <div class="col4">
                <label>Test Outcome</label>
            </div>
            <div class="col4">
                <label>Reference Range</label>
            </div>
            <div class="col4">
                <label>Comments</label>
            </div>
        </div>
        <div class="frow">
            <?php
            $validity = array(" Creatinine" => " >20 mg/dL",
                " Specific Gravity" => " 1.003-1.025",
                " Nitrite" => " < 20 mg/dL",
                " Glutaraldehyde" => " NEG",
                " pH" => " 4.0-9.0",
                " Oxidants/PCC" => " NEG");

            foreach($validity as $key => $val) {
                ?>
                <div class="frow">
                    <div class="col4">
                        <?php echo htmlspecialchars($key)?>
                    </div>
                    <div class="col4">
                        NORMAL
                    </div>
                    <div class="col4">
                        <?php echo htmlspecialchars($val)?>
                    </div>
                    <div class="col4">

                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <?php
    }
    ?>


    <div class="frow">
        <?php
        $comments = "Comments";
        foreach($drug_groups as $group => $drugs) {
            $found = 0;
            foreach($drugs as $d) {
                if(isset($all_res[$d])) $found++;
            }
            if(empty($found)) continue;

            ?>
            <div class="frow">
                <div class="col5"><?php echo htmlspecialchars($group)?></div>
                <div class="col5">Conc  (ng/mL)</div>
                <div class="col5">Pos/Neg</div>
                <div class="col5">Cutoff (ng/mL)</div>
                <div class="col5"><?php echo $comments?></div>
            </div>
            <?php
            //$comments = "";

            foreach($drugs as $d) {
                $arr = $all_res[$d];
                if(!empty($arr)) {
                    unset($all_res[$d]);

                    $max_cutoff = ((float)$ci_cutoffs[$d] * 10);
                    if(!empty($arr['conc'])) $arr['conc'] = ((float)$arr['conc'] > $max_cutoff) ? ">". $max_cutoff : round($arr['conc']);

                    ?>
                    <div class="frow">
                        <div class="col5">
                            <input type="text" name="drug[<?php echo $group?>][drug][]" value="<?php echo $arr['drug']?>"/>
                        </div>
                        <div class="col5">
                            <input type="text" name="drug[<?php echo $group?>][conc][]" value="<?php echo $arr['conc']?>"/>
                        </div>
                        <div class="col5">
                            <input type="text" name="drug[<?php echo $group?>][pos-neg][]" value="<?php echo $arr['pos-neg']?>"/>
                        </div>
                        <div class="col5">
                            <input type="text" name="drug[<?php echo $group?>][cutoff][]" value="<?php echo $arr['cutoff']?>"/>
                        </div>
                        <div class="col5">
                            <input type="text" name="drug[<?php echo $group?>][comments][]" value="<?php echo $arr['comments']?>"/>
                        </div>
                    </div>
                    <?php
                }
            }
        }


        if(is_array($all_res)) {
            ?>
            <div class="frow">
                <div class="col5">
                    Other
                </div>
                <div class="col5">
                    Conc  (ng/mL)
                </div>
                <div class="col5">
                    Pos/Neg
                </div>
                <div class="col5">
                    Cutoff (ng/mL)
                </div>
                <div class="col5">
                    <?php echo $comments?>
                </div>
            </div>

            <?php

            ksort($all_res);
            foreach($all_res as $arr) {
                $max_cutoff = ((float)$ci_cutoffs[$arr['drug']] * 10);
                if(!empty($arr['conc'])) $arr['conc'] = ((float)$arr['conc'] > $max_cutoff) ? ">". $max_cutoff : round($arr['conc']);

                ?>
                <div class="frow">
                    <div class="col5">
                        <input type="text" name="all_res[drug][]" value="<?php echo $arr['drug']?>"/>
                    </div>
                    <div class="col5">
                        <input type="text" name="all_res[conc][]" value="<?php echo $arr['conc']?>"/>
                    </div>
                    <div class="col5">
                        <input type="text" name="all_res[pos-neg][]" value="<?php echo $arr['pos-neg']?>"/>
                    </div>
                    <div class="col5">
                        <input type="text" name="all_res[cutoff][]" value="<?php echo $arr['cutoff']?>"/>
                    </div>
                    <div class="col5">
                        <input type="text" name="all_res[comments][]" value="<?php echo $arr['comments']?>"/>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
    <div class="frow">
        <input type="submit" name="submit" value="Submit" class="button-primary"/>
        <input type="hidden" name="form_action" value="save_form"/>
    </div>
</form>