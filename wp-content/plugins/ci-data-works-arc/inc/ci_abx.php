<?php
class CI_ABX
{


    const VERY_LOW_SCORE    = 1;
    const LOW_SCORE         = 2;
    const MED_SCORE         = 3;
    const HIGH_SCORE        = 4;
    const VERY_HIGH_SCORE   = 5;



    public $style           = 1;
    public $version         = '3.0.1';
    public $type            = 'uti';
    public $subtype         = 'uti';
    public $antibiograms    = array();
    public $all_bv_types = array(
        1 => array('bv', 'BV'),
        2 => array('av', 'AV'),
        3 => array('ur', 'UR'),
        4 => array('sti', 'STI'),
        5 => array('yeast', 'Yeast'),
        6 => array('sm', 'Male STI'),
        7 => array('sr', 'Rectal STI'),
        8 => array('sp', 'Pharyngeal'),
        9 => array('sl', 'Skin Lesions'),
        10 => array('es', 'Eye STIs'),
        11 => array('wv', 'Wound Virus'),
    );
    public $antibiogroups   = array();
    public $rm_results_tbl;
    public $abx_tbl;
    public $report;

    public $patient_renal = '';

    public $all_bacterias   = array();
    public $bacterias_neg   = array();
    public $all_markers     = array();
    public $all_antibiotics = array();
    public $ts_scores       = array();
    public $lt_scores       = array();
    public $rmbiograms      = array();

    public $selectedBacterias = array();
    public $biofilm_yeasts = array();

    public $selectedMarkers = array();
    public $selectedState   = '';
    public $selectedClinicID = 0;
    public $gender = '';

    public $age = 0;
    public $age_months = 0;
    public $Pediatric_ranges    = false;
    public $hide_infection_complexity    = false;
    public $age_ranges          = '';

    public $group_markers   = array();

    public $commbacids      = array();
    public $scores          = array();
    public $result_abx      = array();
    public $cache_lr_scores = array();
    public $cache_ls_scores = array();
    public $limit_markers   = array();

    public $pair_results    = array();

    public $max_count_match = 0;
    public $count_match_all = 0;
    public $count_pill_match_all = 0;
    public $count_match_all_before_pair = 0;
    public $Quantitative     = false; //low/medium/high=true
    public $dismkquantitative = false;
    public $Quantitative5Levels = false;
    public $needcheckrisk0  = false;
    public $excludeNC0      = true; //enable by default

    public $enableConditional   = false;
    public $conditional_abxs    = array();
    public $bacteria_ids_by_panel = array();
    public $bacteria_hide_ids   = array();
    public $marker_ids_by_panel = array();
    public $exclude_antibiotic_IDs = array();

    public $RiskLMH_Score   = 0;
    public $NCLMH_Score     = 0;


    public $has_Orphan      = false;
    public $has_marker_Q    = false;

    public $debug           = false;
    public $log             = '';
    public $log_exclude     = '';
    public $lognols         = '';
    public $logrm           = '';
    public $logpair         = '';
    public $debug_ls_by     = array();

    public $gram_map_LS             = null;
    public $count_bac_available     = 0;
    public $count_bac_hide          = 0;
    public $alway_exclude_Risk0     = false;
    public $hasExcludeRiskLow       = false;
    public $hasExcludeDetectedLow   = false;
    public $ShowColOrphan           = false;
    public $PrioritizeOral          = true;
    public $PrioritizeActivity      = true;
    public $PrioritizeOrialB4Activity   = false;
    public $sortpair_onlypill       = false;
    public $show_percent_bacteria   = false;
    public $lab_dba_init            = '';
    public $NotTestAV               = false;
    public $base_abx                = false;

    public $enable_AMR              = false;
    public $show_pregnancy          = false;
    private $Biofilm                = false;
    public $Biofilm_core            = 0;
    public $Biofilm_Risk_infection  = null; //BRI
    public $Biofilm_Icon            = 0; //Bio Icon
    public $Biofilm_Text            = '';
    public $total_load              = 0;
    public $percent_quantitative    = false;
    public $percent_quantitative_new = false;


    private $markers_orphan     = array();

    private $passES_Score       = 60;

    public $count_errors        = 0;
    public $errors              = array();
    public $limit_abx           = 20;

    public $hasMatchOral        = false;
    public $NotLactobacillus    = false;
    public $pathogen_detected_notes = '';

    public $load_y_intercept    = 40.5;
    public $load_slope          = -3.3;
    public $load_delta          = 6.6;
    public $load_cutoff         = 100;
    public $load_ct1            = 0;
    public $load_ct2            = 0;
    public $load1               = 0;
    public $load2               = 0;
    public $load_formula        = '';
    public $load_high           = 100000;

    public $hasTestMakers       = false;
    public $enable_specimen_respiratory_algo = false;
    public $virus_detected_list      = array();
    public $marker_names        = array();

    public $NTR_Multiflier      = false;

    private $count_exclude_small = 0;
    public $Quantitative_Sum_score = false;
    public $pair_3_oral_90 = false;



    public function __construct($type = 'uti')
    {
        $this->type             = $type;
        $this->subtype          = $type;
        $this->rm_results_tbl   = 'ci_abx_rm_scores_' . $type;
        $this->abx_tbl          = 'ci_abx_results_scores_' . $type;
    }

    public function excludeRisk0($bool)
    {
        $this->alway_exclude_Risk0 = $bool;
    }

    public function bat_max_risk()
    {
        global $wpdb;
        $max = 11;
        $riskkey = $this->type == 'uti' ? 'risk' : 'risk_' . $this->type;
        $maxrisk = $wpdb->get_var("select max({$riskkey}) from ci_abx_bacterias");
        if ($maxrisk <= 6) $max = 6;
        return $max;
    }

    public function setReport($report)
    {
        $this->report = $report;
        $this->selectedClinicID = isset($report['cli_ID']) ? $report['cli_ID'] : 0;
        $this->gender = isset($report['pa_gender']) ? $report['pa_gender'] : '';

        $ages_arr       = $this->dob2ages($report['pa_dob'], $report['doc']);
        $this->age      = $ages_arr['years'];
        $this->age_months = $ages_arr['months'];

        if ($this->Pediatric_ranges && $this->age < 18) {
            if ($this->age == 0 && $this->age_months < 2) {
                $this->age_ranges = '';
            } else if ($this->age <= 1) {
                $this->age_ranges = '2–24mo';
            } else if ($this->age < 12) {
                $this->age_ranges = '2-12y';
            } else {
                $this->age_ranges = '12-18y';
            }

            if (in_array($this->type, array('uti', 'cuti', 'wound', 'r'))) {
                $this->rm_results_tbl   .= 'p';
                $this->abx_tbl          .= 'p';
            }
        }


        if ($this->type == 'uti' && ($this->gender == 'M' || ($this->gender == 'F' && ($this->age >= 50 || ($this->Pediatric_ranges && $this->age < 2))))) {
            $this->subtype = 'cuti';
            $this->rm_results_tbl = str_replace('_uti', '_cuti', $this->rm_results_tbl);
            $this->abx_tbl = str_replace('_uti', '_cuti', $this->abx_tbl);
        }


        if (isset($this->report['abx_cache_id']) && !empty($this->report['abx_cache_id'])) {
            $this->abx_tbl .= '_g' . $this->report['abx_cache_id'];
        }

        if ($this->debug) {
            $this->log .= "age: " . $this->age . '<br>';
            if ($this->Pediatric_ranges) {
                $this->log .= "age_ranges: " . $this->age_ranges . '<br>';
            } else {
                $this->log .= 'Pediatric: not enabled<br>';
            }

            $this->log .= "age months: " . $this->age_months . '<br>';
            $this->log .= "Score table: " . $this->abx_tbl . '<br>';
        }
    }

    public function get_typename()
    {
        $typename = $this->subtype;
        if ($typename == 'cuti') {
            $typename = 'cUTI';
        } else if ($typename == 'uti') {
            $typename = 'UTI';
        } else if ($typename == 'wound') {
            $typename = 'Wound';
        } else if ($typename == 'r') {
            $typename = 'RPP';
        } else if ($typename == 'gi') {
            $typename = 'GI';
        } else if ($typename == 'p') {
            $typename = 'P';
        } else if ($typename == 'v') {
            $typename = 'Vaginitis';
        }
        return $typename;
    }
    public function setAllMarkers($arrs)
    {
        global $wpdb;

        $this->limit_markers = array();

        foreach ($arrs as $arr) {
            $where = '';
            //get mkids
            $mkrids = '';
            if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
                $mkrids = $wpdb->get_var("select meta_value 
                from ci_test_meta 
                where test_id = '{$arr['te_ID']}' and meta_key='mkr_tests' ");
                if (!empty($wpdb->last_error)) {
                    $this->count_errors++;
                    $this->errors[$wpdb->last_query] = $wpdb->last_error;
                }
            }

            if (!empty($mkrids)) {
                $where .= " m.ID in ({$mkrids}) ";
            } else {

                $test_names = array($arr['te_name']);
                if (isset($arr['te_abrev']) && !empty($arr['te_abrev'])) {
                    $test_names[] = $arr['te_abrev'];
                }
                $test_names_str = implode("','", $test_names);
                $where = " m.resistance_marker in ('{$test_names_str}') ";
            }

            if ($this->debug) {

                $this->log .= $wpdb->last_error;
            }

            $sql = "select m.ID, m.resistance_marker from ci_abx_resistance_markers m where {$where}";
            $mkrs = $wpdb->get_results($sql);
            if (count($mkrs) > 0) {
                foreach ($mkrs as $mk) {
                    $this->limit_markers[] = $mk->ID;
                    $this->marker_names[$mk->ID] = $mk->resistance_marker;
                }
            }
        }

        if ($this->debug) {
            $this->log .= 'limit_markers: ';

            $this->log .=  json_encode($this->limit_markers);
            $this->log .= '<br><br>';
        }
    }

    public function setAllViruses($arrs)
    {
        $this->virus_detected_list = array();
        foreach ($arrs as $key => $arr) {
            $arr['lmh'] = 0;
            if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVL') {
                $arr['lmh'] = 1;
            } else  if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVH') {
                $arr['lmh'] = 5;
            } elseif (in_array($arr['pos-neg'], array('POL', 'PVL'))) {
                $arr['lmh'] = 2;
            } else if ($arr['pos-neg'] == 'POM') {
                $arr['lmh'] = 3;
            } else if (in_array($arr['pos-neg'], array('POH', 'PVH'))) {
                $arr['lmh'] = 4;
            }
            if ($arr['lmh'] > 0) {
                $arr['name'] .= ' <span class="smalltext1">(' . $this->lmh_num2text($arr['lmh']) . ')</span>';
            }

            $this->virus_detected_list[$key]  = $arr;
        }
    }

    public function setLimitMarkers($markerids)
    {
        $this->limit_markers = $markerids;
    }

    function dob2ages($dob, $today = '')
    {
        if (empty($today)) {
            $today = date("Y-m-d");
        } else {
            $today = date('Y-m-d', strtotime($today));
        }
        if (stripos($dob, '/') > 0) {
            $dob = date('Y-m-d', strtotime($dob));
        }
        $diff = date_diff(date_create($dob), date_create($today));
        $years = (int)$diff->format('%y');
        $months = (int)$diff->format('%m');

        return array(
            'years' => $years,
            'months' => $months
        );
    }

    public function setSelectedState($state)
    {
        $this->selectedState = strtoupper(trim($state));
    }

    public function setQuantitative($quantitative)
    {
        $this->Quantitative = $quantitative ? true : false;
    }

    public function set_Biofilm($biofilm)
    {
        if ($this->type == 'wound' && $this->style == 2) {
            $this->Biofilm = $biofilm ? true : false;
        }
    }

    public function set_Show_Percent_Quantitative($show)
    {
        $this->percent_quantitative = $this->Quantitative && $show ? true : false;
    }

    public function set_Show_Percent_Quantitative_New($show)
    {
        $this->percent_quantitative_new = $this->Quantitative && $show ? true : false;

        if ($this->percent_quantitative_new) {
            $this->percent_quantitative = true;
        }
    }

    public function set_Show_Pregnancy($show, $show_by = '')
    {
        //need after setReport
        $this->show_pregnancy = false;
        if (empty($show_by) || $show_by == 'age') {
            if ($this->gender == 'F' && $this->age >= 12 && $this->age <= 50) {
                $this->show_pregnancy = $show ? true : false;
            }
        } else if ($show_by == 'aoe') {
            $this->show_pregnancy = $show ? true : false;
        }

        if ($this->debug) {
            $this->log .= 'Show pregnancy: ' . ($this->show_pregnancy ? 'yes' : 'no') . ' by ' . $show_by . '<br>';
        }
    }

    public function checkQuantitative()
    {
        global $wpdb;
        $groupids = $wpdb->get_col("select tg_ID from ci_test_groups 
        where tg_other_options like '%new_abx\":\"1%'
        and tg_type ='pcr' ");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
            return;
        }
        $groupids_str = implode(',', $groupids);

        $this->Quantitative = $wpdb->get_var("select 
        count(*) 
        from ci_test_panels_cutoffs cu
        JOIN ci_test_panels t ON t.te_ID = cu.cu_te_ID
        JOIN ci_test_groups tg ON tg.tg_ID = t.te_group_id
        where tg_ID in ({$groupids_str}) or tg_parent in ({$groupids_str})
        and te_is_detected = 2
        and cu_cutoff !='' and cu_cutoff is not null
        and cu_upper_limit!='' and cu_upper_limit is not null
        ") > 10;

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
            return;
        }

        if ($this->debug) {
            $this->log .=  'Quantitative auto: ' . $this->Quantitative . '<br>';
        }

        update_option('ci_abx_Quantitative', $this->Quantitative);
    }

    public function addMarker($arr)
    {
        global $wpdb;
        $where = '';
        //get mkids
        $mkrids = '';
        $countmkids = 0;
        $abx_name = '';

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            $mkrids = $wpdb->get_var("select meta_value 
            from ci_test_meta 
            where test_id = '{$arr['te_ID']}' and meta_key='mkr_tests' ");
            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }

            $abx_name = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='abx_name' "
            );

            $countmkids = count(explode(',', $mkrids));
        }

        if (!empty($mkrids)) {
            $where .= " m.ID in ({$mkrids}) ";
        } else {

            $test_names = array($arr['te_name']);
            if (isset($arr['te_abrev']) && !empty($arr['te_abrev'])) {
                $test_names[] = $arr['te_abrev'];
            }
            $test_names_str = implode("','", $test_names);
            $where = " m.resistance_marker in ('{$test_names_str}') ";
        }


        if ($this->debug) {

            $this->log .= $wpdb->last_error;
        }

        $sql = "select 
        m.*,
        cl.class,
        cl.resistants
        from ci_abx_resistance_markers m
        left JOIN ci_abx_rm_class cl ON cl.ID = m.class_ID
        where {$where}";
        $rs = $wpdb->get_results($sql);

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        if ($this->debug) {
            $this->log .= $wpdb->last_error;

            if (count($rs) == 0) {
                $this->log .= 'not found marker: ' . $arr['te_name'] . '<br>';
                $this->log .= $sql;
                $this->log .= '<br><br>';
            } else {

                $this->log .= '<br>';
                $this->log .= 'Maker: ' . $arr['te_name'];
                foreach ($rs as $r) {
                    $this->log .=  '<br> - Found marker: ' . $r->resistance_marker . ' (Class: ' . $r->class . ')';
                }
                $this->log .= '<br><br>';
            }
        }

        foreach ($rs as $r) {

            $r->name    = $r->resistance_marker;
            $carr       = explode(' ', $r->class);
            $r->class   = $carr[0];
            if (stripos($r->class, 'class') !== false) {
                $r->class .= ' ' . $carr[1];
            }

            if (isset($arr['conc'])) {
                $r->conc = $arr['conc'];
            }
            $r->abrev       = !empty($r->abrev) ? $r->abrev : (!empty($arr['te_abrev']) ? $arr['te_abrev'] : $r->name);
            $r->group_name  =  $r->name;
            $r->display_name  =  $r->name;
            $r->display_group_name  =  $r->name;

            if (!empty($abx_name)) {
                $r->group_name = $abx_name;
                $r->display_group_name = $abx_name;
            } else if ($countmkids > 1) {
                $r->group_name = $r->abrev;
                $r->display_group_name  =  $r->name;
            }




            if ($this->dismkquantitative) {
            } else if ($this->Quantitative && isset($arr['pos-neg'])) {
                $r->lmh = '';
                if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVL') {
                    $r->lmh = 1;
                } else  if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVH') {
                    $r->lmh = 5;
                } elseif (in_array($arr['pos-neg'], array('POL', 'PVL'))) {
                    $r->lmh = 2;
                } else if ($arr['pos-neg'] == 'POM') {
                    $r->lmh = 3;
                } else if (in_array($arr['pos-neg'], array('POH', 'PVH'))) {
                    $r->lmh = 4;
                }
                if ($r->lmh > 0) {
                    $r->display_name .= ' <span class="smalltext1">(' . $this->lmh_num2text($r->lmh) . ')</span>';
                    $this->has_marker_Q = true;
                }
            } else {
                $r->lmh = '';
            }

            $r->count_anti = 0;
            $this->selectedMarkers[$r->ID] = $r;

            $this->marker_names[$r->ID] = $r->resistance_marker;
        }
    }

    public function lmh_num2text($num)
    {
        $lmh = '';
        if ($num == 1) {
            $lmh = 'V.Low';
        } else if ($num == 5) {
            $lmh = 'V.High';
        } else if ($num == 2) {
            $lmh = 'Low';
        } else if ($num == 3) {
            $lmh = 'Med';
        } else if ($num == 4) {
            $lmh = 'High';
        }
        return $lmh;
    }

    public function bac_text_level($short)
    {
        $lmh = '';
        if ($short == 'VL') {
            $lmh = 'Very Low';
        } else if ($short == 'VH') {
            $lmh = 'Very High';
        } else if ($short == 'L') {
            $lmh = 'Low';
        } else if ($short == 'M') {
            $lmh = 'Med';
        } else if ($short == 'H') {
            $lmh = 'High';
        } else if ($short == 'D') {
            $lmh = 'Detected';
        }
        return $lmh;
    }

    public function getBat_Biofilm_risk_text($bat)
    {
        if (!isset($bat->bro)) {
            return '';
        }
        $br = $bat->bro;

        $lmh = '';
        if ($br <= 2) {
            $lmh = 'Very Low';
        } else if ($br <= 4) {
            $lmh = 'Low';
        } else if ($br <= 6) {
            $lmh = 'Med';
        } else if ($br <= 8) {
            $lmh = 'High';
        } else if ($br > 8) {
            $lmh = 'Very High';
        }
        return $lmh;
    }

    public function getBat_Exclude_by_text($bat)
    {
        $br = $bat->exclude_by;

        $exby = '';
        if ($br == 1) {
            $exby = '% + Low BR';
        } else if ($br == 2) {
            $exby = 'Low Risk';
        } else if ($br == 3) {
            $exby = 'Contaminant';
        } else if ($br == 4) {
            $exby = 'Load';
        } else if ($br == 5) {
            $exby = 'Trace';
        } else if ($br == 6) {
            $exby = 'Low Priority';
        } else if ($br == 7) {
            $exby = 'Colonizer';
        } else if ($br == 10) {
            $exby = 'Commensal';
        } else if ($br == 8) {
            $exby = 'Low Load';
        } else if ($br == 9) {
            $exby = 'Low Burden';
        }
        return $exby;
    }

    public function addTestNeg($arr)
    {
        $this->bacterias_neg[] = $arr;
    }

    public function add_Biofilm_yeast($arr)
    {
        global $wpdb;
        $where = '';
        $baids = '';
        $abx_name = '';

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            $baids = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='bac_tests' "
            );
            $abx_name = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='abx_name' "
            );
            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }
        }

        if (!empty($baids)) {
            $where .= " ID in ({$baids}) ";
        } else {
            $test_names = array($arr['te_name']);
            if (isset($arr['te_abrev']) && !empty($arr['te_abrev'])) {
                $test_names[] =  $arr['te_abrev'];
            }
            $test_names_str = implode("','", $test_names);
            $where = " bacteria_name in ('{$test_names_str}') ";
        }

        $morefields     = '';

        if ($this->Biofilm) {
            $br_field = 'risk_br_' . $this->type;
            $morefields .= ", {$br_field} as br";
        }

        $sql1 = "select 
        ID,
        bacteria_name as name,
        bacteria_abrev as abrev,
        gram_type
        {$morefields}
        from ci_abx_bacterias 
        where {$where} ";

        $rs = $wpdb->get_results($sql1);

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }


        foreach ($rs as $r) {

            $r->conc        = isset($arr['conc']) ? $arr['conc'] : '';
            $r->ra          = $r->conc > 0 ? 2 ** (-1 * $r->conc) : 0;
            $r->load        = $this->cal_bat_load($arr, $r);
            $this->biofilm_yeasts[$r->ID] = $r;
        }
    }

    public function addBacteria($arr)
    {
        global $wpdb;
        $where = '';
        $baids = '';
        $countbgroup = 0;
        $abx_name = '';
        if ($this->debug) {
            $this->log .= "-bacteria: " . $arr['te_name'] . '<br>';
        }

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            $baids = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='bac_tests' "
            );
            $abx_name = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='abx_name' "
            );

            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }

            $countbgroup = count(explode(',', $baids));
        }

        if (!empty($baids)) {
            $where .= " ID in ({$baids}) ";
        } else {
            $test_names = array($arr['te_name']);
            if (isset($arr['te_abrev']) && !empty($arr['te_abrev'])) {
                $test_names[] =  $arr['te_abrev'];
            }
            $test_names_str = implode("','", $test_names);
            $where = " bacteria_name in ('{$test_names_str}') ";
        }

        $notes_field    = 'notes_' . $this->type;
        $nc_field       = 'nc_' . $this->type;
        $desc_field     = 'description2_' . $this->type;
        $risk_field     = $this->type != 'uti' ? 'risk_' . $this->type : 'risk';

        $morefields     = '';

        if ($this->excludeNC0) {
            if ($this->type == 'r' && $this->enable_specimen_respiratory_algo) {
                $nc_field2 = '';
                if (stripos($this->report['type_of_specimen'], 'nasopharyngeal swab') !== false) {
                    $nc_field2 = 'nc_r_np';
                } else if (stripos($this->report['type_of_specimen'], 'oropharyngeal swab') !== false) {
                    $nc_field2 = 'nc_r_op';
                } else if (stripos($this->report['type_of_specimen'], 'sputum') !== false) {
                    $nc_field2 = 'nc_r_s';
                }
                $nc_field = !empty($nc_field2) ? $nc_field2 : $nc_field;
            }
            $morefields .= ", {$nc_field} as nc";
        }

        if ($this->Biofilm) {
            $br_field = 'risk_br_' . $this->type;
            $morefields .= ", {$br_field} as br";
        }

        $sql1 = "select 
        ID,
        bacteria_name as name,
        bacteria_abrev as abrev,
        gram_type,
        {$risk_field} as risk,
        {$notes_field} as notes,
        {$desc_field} as description2
        {$morefields}
        from ci_abx_bacterias 
        where {$where} ";

        $rs = $wpdb->get_results($sql1);

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        $max_risk = 0;
        $spname = '';
        if (isset($_GET['debugnone2'])) {
            $countbgroup = 0;
        }

        if ($countbgroup >= 2) {


            foreach ($rs as $r) {

                $risk1 = $r->risk;
                if ($risk1 == $this->bat_max_risk()) $risk1 = 0;
                $max_risk = max($risk1, $max_risk);

                list($firstword) = explode(' ', $r->name);

                $spnamenew = $firstword . ' Spp.';

                if (!empty($spname) && $spname != $spnamenew) {
                    $spnamenew  = '-1';
                    $spname     = '-1';
                }
                if ($spnamenew != '-1' && $spname != '-1') {
                    $spname = $spnamenew;
                }
                if ($spname == '-1') {
                    $spname = '';
                    if (stripos($arr['te_abrev'], ' sp') > 0) {
                        list($firstword) = explode(' ', $arr['te_abrev']);
                        $spname = $firstword . '  Spp.';
                    }
                }
            }
        }

        if ($this->debug && count($rs) == 0) {
            $this->log .= $wpdb->last_error;
            $this->log .= 'Bacteria not found<br>';
        }

        $count_addedg   = 0;
        $main_bac_ID    = 0;
        $hide_ids       = array();
        if ($this->Quantitative && empty($this->load_formula)) {
            $this->load_formula = 'Q100';
        }


        foreach ($rs as $r) {
            $r->main_ID     = 0;
            $r->lmh         = '';
            if (isset($arr['pos-neg'])) {
                if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVL') {
                    $r->lmh = 'VL';
                } else  if ($this->Quantitative5Levels && $arr['pos-neg'] == 'PVH') {
                    $r->lmh = 'VH';
                } else if (in_array($arr['pos-neg'], array('POL', 'PVL'))) {
                    $r->lmh = 'L';
                } else if ($arr['pos-neg'] == 'POM') {
                    $r->lmh = 'M';
                } else if (in_array($arr['pos-neg'], array('POH', 'PVH'))) {
                    $r->lmh = 'H';
                } else if (in_array($arr['pos-neg'], array('POS')) && tr_get_option('ci_pdf_data', 'new_abx_quant_detected')) {
                    $r->lmh = 'D';
                }
            }

            if (empty($arr['conc'])) {
                //get conc from lmh
                if ($r->lmh == 'VL') {
                    $arr['conc'] =  $arr['vlow_1'];
                } else if ($r->lmh == 'L') {
                    $arr['conc'] = $arr['low_1'];
                } else if ($r->lmh == 'M') {
                    $arr['conc'] = $arr['med_1'];
                } else if ($r->lmh == 'H' || $r->lmh == 'D') {
                    $arr['conc'] = $arr['high_1'];
                } else if ($r->lmh == 'VH') {
                    $arr['conc'] = $arr['vhigh_1'];
                } else if (isset($arr['high_1']) && $arr['high_1'] > 0) {
                    $arr['conc'] = $arr['high_1'];
                } else if (isset($arr['te_range']) && !empty($arr['te_range'])) {
                    $ranges = explode('-', $arr['te_range']);
                    if (count($ranges) == 2) {
                        $arr['conc'] = (floatval($ranges[0]) + floatval($ranges[1])) / 2;
                    } else {
                        $arr['conc'] = $arr['te_range'];
                    }
                }

                $arr['conc'] = floatval(str_replace(array('<', '>', '='), '', $arr['conc']));

                if (isset($_GET['debug2222'])) {
                    var_dump($arr);
                    var_dump($arr['conc']);
                }
            }



            $r->no_RMrisk5  = true;
            $r->isexclude   = false;
            $r->is_risk0    = $r->risk == 0 || $r->risk == $this->bat_max_risk();
            $r->is_nc0      = $r->nc == 0;
            $r->conc        = isset($arr['conc']) ? $arr['conc'] : '';
            $r->ra          = $r->conc > 0 ? 2 ** (-1 * $r->conc) : 0;
            $r->load        = $this->cal_bat_load($arr, $r);


            $r->exclude_pair  = $this->alway_exclude_Risk0 && $r->is_risk0;
            $r->markers     = array();
            $r->is_hide     = false;




            if ($countbgroup >= 2) {
                if ($max_risk > 0) {
                    if ($max_risk > 0 && $r->risk < $max_risk) {
                        if ($this->debug) {
                            $this->log .= $r->name . " => hide by risk: " . $r->risk . "<br>";
                        }
                        $r->is_hide = true;
                    } else if ($count_addedg > 0 && $r->risk == $max_risk) {
                        if ($this->debug) {
                            $this->log .= $r->name . " => hide by risk: " . $r->risk . " same max risk<br><br>";
                        }
                        $r->is_hide = true;
                    } else if (!empty($spname)) {

                        $r->name = $spname;
                        $r->abrev = $spname;
                    }
                } else if ($max_risk == 0 && !empty($spname)) {
                    if ($count_addedg > 0) {
                        $r->is_hide = true;
                    } else {
                        $r->name = $spname;
                        $r->abrev = $spname;
                    }
                }
            }

            $r->pathogen_name = $r->name;
            $r->groupname   = $spname;

            if (!empty($abx_name)) {
                $r->pathogen_name = $abx_name;
                $r->groupname = $abx_name;
            }

            if ($this->Quantitative) {
                if ($r->lmh == 'VL') {
                    $r->pos_num = 5;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::VERY_LOW_SCORE ? $this->RiskLMH_Score : self::VERY_LOW_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::VERY_LOW_SCORE ? $this->NCLMH_Score : self::VERY_LOW_SCORE;
                    }
                    $r->pathogen_name .= '<span class="smalltext1"> (V.Low)</span>';
                } else  if ($r->lmh == 'VH') {
                    $r->pos_num = 1;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::VERY_HIGH_SCORE ? $this->RiskLMH_Score : self::VERY_HIGH_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::VERY_HIGH_SCORE ? $this->NCLMH_Score : self::VERY_HIGH_SCORE;
                    }
                    $r->pathogen_name .= '<span class="smalltext1"> (V.High)</span>';
                } else if ($r->lmh == 'L') {
                    $r->pos_num = 4;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::LOW_SCORE ? $this->RiskLMH_Score : self::LOW_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::LOW_SCORE ? $this->NCLMH_Score : self::LOW_SCORE;
                    }
                    $r->pathogen_name .= '<span class="smalltext1"> (Low)</span>';
                } else if ($r->lmh == 'M') {
                    $r->pos_num = 3;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::MED_SCORE ? $this->RiskLMH_Score : self::MED_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::MED_SCORE ? $this->NCLMH_Score : self::MED_SCORE;
                    }
                    $r->pathogen_name .= '<span class="smalltext1"> (Med)</span>';
                } else if ($r->lmh == 'H') {
                    $r->pos_num = 2;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::HIGH_SCORE ? $this->RiskLMH_Score : self::HIGH_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::HIGH_SCORE ? $this->NCLMH_Score : self::HIGH_SCORE;
                    }
                    $r->is_risk0 = false;

                    $r->pathogen_name .= '<span class="smalltext1"> (High)</span>';
                } else if ($r->lmh == 'D') { //detected
                    $r->pos_num = 1;
                    $r->load = 0;
                    if (!$r->is_risk0) {
                        $this->RiskLMH_Score = $this->RiskLMH_Score > self::LOW_SCORE ? $this->RiskLMH_Score : self::LOW_SCORE;
                    }
                    if (!$r->is_nc0) {
                        $this->NCLMH_Score = $this->NCLMH_Score > self::LOW_SCORE ? $this->NCLMH_Score : self::LOW_SCORE;
                    }

                    $r->pathogen_name .= '<span class="smalltext1"> (Detected)</span>';
                } else {
                    $r->is_risk0 = false;
                }
            } else {
                $r->pos_num = count($this->selectedBacterias) + 1;
            }

            if ($this->alway_exclude_Risk0 && $r->is_risk0) {
                $r->isexclude   = true;

                if ($this->debug) {
                    $this->log_exclude .= "exclude {$r->name} by risk = {$r->risk}<br>";
                }
            }

            $this->selectedBacterias[$r->ID] = $r;

            if (!$r->is_hide) {
                $this->total_load += $r->load;
                $count_addedg++;
                $main_bac_ID = $r->ID;
            } else {
                $hide_ids[] = $r->ID;
            }
        }

        //add main id
        if ($countbgroup >= 2) {
            foreach ($hide_ids as $hid) {
                $this->bacteria_hide_ids[$hid] = $main_bac_ID;
            }
        }
    }

    public function cal_bat_load($arr, $r)
    {

        $ct1    = $this->load_ct1;
        $ct2    = $this->load_ct2;

        $load1  = $this->load1;
        $load2  = $this->load2;

        $this->log .= '<br>' . $arr['te_name'] . ': ';

        if (in_array($this->load_formula, array('Q10', 'Q100'))) {
            if (empty($ct1)) {
                $arr['high']    = isset($arr['high_2']) && $arr['high_2'] > 0 ? $arr['high_2'] : $arr['high_1'];
                $arr['medium']  = isset($arr['medium_1']) ? (empty($arr['medium_2']) ? $arr['medium_1'] : $arr['medium_2']) : 0;
                $arr['low']     = !empty($arr['low_2']) ? $arr['low_1'] : $arr['medium'];

                $ct1 = floatval(str_replace(array('<', '=', '>'), '', $arr['high']));
                $ct2 = floatval(str_replace(array('<', '=', '>'), '', $arr['low']));
            }

            $this->log .= " <b>ct1:</b> " . $ct1;
            $this->log .= " | <b>ct2:</b> " . $ct2;
        } else  if (in_array($this->load_formula, array('QG'))) {
            if (empty($ct1)) {
                $arr['medium']  = !empty($arr['medium_1']) ? $arr['medium_1'] : 0;
                $arr['vlow']    = isset($arr['vlow_1']) ? $arr['vlow_1'] : 0;

                $ct1 = floatval(str_replace(array('<', '=', '>'), '', $arr['medium']));
                $ct2 = floatval(str_replace(array('<', '=', '>'), '', $arr['vlow']));
            }

            $this->log .= " <b>ct1:</b> " . $ct1;
            $this->log .= " | <b>ct2:</b> " . $ct2;
        } else  if (in_array($this->load_formula, array('QG1'))) {
            if (empty($ct1) || empty($ct2)) {
                $arr['vhigh']  = !empty($arr['vhigh_1']) ? $arr['vhigh_1'] : 0;
                $arr['medium']    = isset($arr['medium_1']) ? $arr['medium_1'] : 0;

                $ct1 = floatval(str_replace(array('<', '=', '>'), '', $arr['vhigh']));
                $ct2 = floatval(str_replace(array('<', '=', '>'), '', $arr['medium']));

                if (isset($arr['vhigh_concentration'])) {
                    $arr1 = explode('10^', $arr['vhigh_concentration']);
                    $load1_10 = substr($arr1[1], 0, 1);
                    if ($load1_10 > 1) {
                        $load1 = pow(10, intval($load1_10));
                    }

                    $this->log .= " | <b>vhigh_concentration:</b> " . $arr['vhigh_concentration'];
                    $this->log .= " | <b>load1_10:</b> " . $load1_10;
                    $this->log .= " | <b>load1:</b> " . $load1;
                }
                if (isset($arr['medium_concentration'])) {
                    $arr1 = explode('10^', $arr['medium_concentration']);
                    $load2_10 = substr($arr1[1], 0, 1);
                    if ($load2_10 > 1) {
                        $load2 = pow(10, intval($load2_10));
                    }
                    $this->log .= " | <b>medium_concentration:</b> " . $arr['medium_concentration'];
                    $this->log .= " | <b>load2_10:</b> " . $load2_10;
                    $this->log .= " | <b>load2:</b> " . $load2;
                }
            }


            $this->log .= " | <b>ct1:</b> " . $ct1;
            $this->log .= " | <b>ct2:</b> " . $ct2;
        } else  if (in_array($this->load_formula, array('QG_HL'))) {
            if (empty($ct1) || empty($ct2)) {
                $arr['high']  = !empty($arr['high_1']) ? $arr['high_1'] : 0;
                $arr['low']    = isset($arr['low_1']) ? $arr['low_1'] : 0;

                $ct1 = floatval(str_replace(array('<', '=', '>'), '', $arr['high']));
                $ct2 = floatval(str_replace(array('<', '=', '>'), '', $arr['low']));
            }

            $this->log .= " <b>ct1:</b> " . $ct1;
            $this->log .= " | <b>ct2:</b> " . $ct2;
        } else  if (in_array($this->load_formula, array('QG_HM'))) {
            if (empty($ct1) || empty($ct2)) {
                $arr['high']  = !empty($arr['high_1']) ? $arr['high_1'] : 0;
                $arr['medium']    = isset($arr['medium_1']) ? $arr['medium_1'] : 0;

                $ct1 = floatval(str_replace(array('<', '=', '>'), '', $arr['high']));
                $ct2 = floatval(str_replace(array('<', '=', '>'), '', $arr['medium']));
            }

            $this->log .= " <b>ct1:</b> " . $ct1;
            $this->log .= " | <b>ct2:</b> " . $ct2;
        }

        //NQC
        if ($this->load_formula == 'NQC' && !$this->Quantitative && $this->load_cutoff > 0) {
            //- Non-Quantitative panels
            //- When load cutoff known
            //- When cutoffs are different for different pathogens (otherwise use GL)
            $ct_cutoff   = floatval(str_replace(array('<', '=', '>'), '', $arr['high_1']));
            $load    = $r->conc > 0 ? $this->load_cutoff * pow(10, (($r->conc - $ct_cutoff) / $this->load_slope)) : 0;

            $this->log .= " | NQC: {$this->load_cutoff} * 10^( ({$r->conc} - {$ct_cutoff}) / {$this->load_slope})";
        }
        //NQY
        else if ($this->load_formula == 'NQY') {
            //- Non-Quantitative panel
            //- When load cutoff not known
            //- When the cutoffs are different for different pathogens (otherwise use GL)
            $ct_cutoff   = floatval(str_replace(array('<', '=', '>'), '', $arr['high_1']));
            $load     = $r->conc > 0 ? pow(10, (($r->conc - $ct_cutoff - $this->load_delta) / $this->load_slope)) : 0;

            $this->log .= " | NQY:  10^( ({$r->conc} - {$ct_cutoff} - {$this->load_delta}) / {$this->load_slope})";
        }
        //Q10
        else if ($this->load_formula == 'Q10' && $ct2 > 0 && $ct1 > 0) {
            $load     = $r->conc > 0 ? pow(10, 5 - (($r->conc - $ct1) / ($ct2 - $ct1))) : 0;

            $this->log .= " | Q10:  10^(5-(({$r->conc} - {$ct1})/({$ct2} - {$ct1})))";
        }
        //Q100
        else if ($this->load_formula == 'Q100' && $ct2 > 0 && $ct1 > 0) {
            $load     = $r->conc > 0 ? pow(10, 5 - (2 * ($r->conc - $ct1) / ($ct2 - $ct1))) : 0;

            $this->log .= " | Q100: 10^(5-(2*({$r->conc} - {$ct1})/({$ct2} - {$ct1})))";
        }
        //SY
        else if ($this->load_formula == 'SY' && !empty($arr['y_intercept']) && !empty($arr['slope'])) {
            $load    = $r->conc > 0 ? pow(10, (($r->conc - $arr['y_intercept']) / $arr['slope'])) : 0;

            $this->log .= " | SY: 10^( ({$r->conc} - {$arr['y_intercept']}) / {$arr['slope']})";
        }
        //QG , QG1, QG_HL, QG_HM
        else if (in_array($this->load_formula, array('QG', 'QG1', 'QG_HL', 'QG_HM')) && $ct2 > 0 && $ct1 > 0) {

            $log10load1 = log10($load1);
            $log10load  = log10($load1) - log10($load2);
            $load       = $r->conc > 0 ? pow(10, $log10load1 - ($log10load * ($r->conc - $ct1) / ($ct2 - $ct1))) : 0;

            $this->log .= " | {$this->load_formula}: 10^({$log10load1}-({$log10load}*({$r->conc} - {$ct1})/({$ct2} - {$ct1})))";
        }

        //GL
        else {
            $load    = $r->conc > 0 ? pow(10, (($r->conc - $this->load_y_intercept) / $this->load_slope)) : 0;

            $this->log .= " | GL: 10^( ({$r->conc} - {$this->load_y_intercept}) / {$this->load_slope})";
        }

        $this->log .= ' | <b>load:</b> ' . $load;
        $this->log .= '<br><br>';

        return $load;
    }

    public function getcountBacterias($include_hide = false)
    {
        if ($include_hide) {
            return count($this->selectedBacterias);
        }
        $count = 0;
        foreach ($this->getSelectedBacterias() as $bat) {
            if ($bat->is_hide) continue;
            $count++;
        }
        return $count;
    }

    public function getSelectedBacterias()
    {
        return $this->selectedBacterias;
    }

    public function pass_condition($condition, $value)
    {
        global $ci_debug_pass_log;

        $ci_debug_pass_log = array();

        $fvalue     = floatval($value);
        $pass       = false;
        $condition  = trim($condition);
        $value_no_condition  = str_replace(array('-', '>', '<', '='), '', $condition) === $condition;

        if ($condition !== '' && $value_no_condition) {

            $confloat = floatval($condition);
            if ($condition === '0' && ($value === '0' || $value === 0)) {
                $pass = true;
            } else if ($confloat > 0  && abs(($fvalue - $confloat) / $confloat) < 0.00001) {
                $pass = true;
            }
            if (isset($_GET['debugid'])) {
                $ci_debug_pass_log[] = array(
                    'condition' => $condition,
                    'value'     => $value,
                    'pass1'      => $pass
                );
            }
            return $pass;
        }

        if (strpos($condition, '-') !== false) {
            list($low, $high) = explode('-', $condition);
            $low = trim($low);
            $high = trim($high);

            if (strpos($low, '>') !== false && strpos($low, '>=') === false) {
                $low = floatval(trim(str_replace(array('>', '='), '', $low)));
                $pass_low = $fvalue > $low;
            } else {
                $low = floatval(trim(str_replace(array('>', '='), '', $low)));
                $pass_low = $fvalue >= $low;
            }

            if (strpos($high, '<') !== false && strpos($low, '<=') === false) {
                $high = floatval(trim(str_replace(array('<', '='), '', $high)));
                $pass_high = $fvalue < $high;
            } else {
                $pass_high = $fvalue <= $high;
            }

            $pass = $pass_low && $pass_high;
        } else {
            if (strpos($condition, '>=') !== false) {
                $limit = trim(str_replace(array('>=', ' '), '', $condition));
                $pass  = $fvalue >= $limit;
            } else if (strpos($condition, '<=') !== false) {
                $limit = trim(str_replace(array('<=', ' '), '', $condition));
                $pass  = $fvalue <= $limit;
            } else if (strpos($condition, '>') !== false) {
                $limit = trim(str_replace(array('>', ' '), '', $condition));
                $pass  = $fvalue > $limit;
            } else if (strpos($condition, '<') !== false) {
                $limit = trim(str_replace(array('<', ' '), '', $condition));
                $pass  = $fvalue < $limit;
            }
        }

        if (isset($_GET['debugid'])) {
            $ci_debug_pass_log[] = array(
                'condition' => $condition,
                'value'     => $value,
                'pass2'      => $pass
            );
        }
        return $pass;
    }

    public function getNotes($maintype, $subtype)
    {
        global $wpdb;
        $return = array(1 => '', 2 => '');
        $sql  = "select desc_1, desc_2 from ci_abx_texts where ctype = '{$maintype}' 
                and condition1='{$subtype}'  ";
        $row = $wpdb->get_row($sql);
        if ($row) {
            $return[1] = $row->desc_1;
            $return[2] = $row->desc_2;
        }
        return $return;
    }

    public function getEstimated_Inf_Comp_Text($risk10)
    {
        global $wpdb;
        $risk5 = ceil($risk10 / 2);

        $knums = array(
            1 => 'Low',
            2 => 'Medium',
            3 => 'Medium-High',
            4 => 'High',
            5 => 'Critical',
        );
        $key = $knums[$risk5];

        $bacteria_texts = array(
            'Low'       => 'Detected organisms may be low-risk or common commensals/contaminants. Pathogenicity is variable/unclear. In otherwise healthy patients, treat only if symptomatic.',
            'Medium'    => 'Detected infection is likely to respond well to targeted antibiotic therapy in otherwise healthy patients without complicating factors.',
            'Medium-High' => 'Detected infection may present some treatment challenges due to more difficult to treat organisms and/or antibiotic resistance. Targeted treatment may have a favorable outcome in otherwise healthy patients without complicating factors.',
            'High'      => 'Detected infection is likely to present significant treatment challenges, due to difficult to treat organisms and/or antibiotic resistance. Treatment may require dual therapy,  IV administration, or restricted antibiotics.',
            'Critical'  => 'Detected infection is likely to present few treatment options, due to difficult to treat organisms and/or extensive resistance. Therapeutical options may be restricted to IV administration of last-resort antibiotics or specialized antibiotic combinations. <span class="red">Infectious disease consultation is strongly recommended.</span>'
        );
        $eic_type   = 'eic_' . $this->type;
        $where2     = '';

        if ($this->Biofilm) { //only for wound
            $eic_type = 'eic_wbr';
            $where2 .= " and condition2 = '{$this->Biofilm_Text}' ";
        }

        $sql  = "select desc_1 from ci_abx_texts where ctype = '{$eic_type}' 
                and condition1='{$key}'  " . $where2;

        $text = $wpdb->get_var($sql);
        if (empty($text)) {
            $text = $bacteria_texts[$key];
        }

        $text = '<span class="mleveltext">' . $key . ':</span> ' . $text;

        return $text;
    }

    function get_Virus_comments()
    {
        global $wpdb;
        $text = '';
        if ($this->type == 'r' && $this->Quantitative && $this->enable_specimen_respiratory_algo) {

            $desc_field = '';
            if (stripos($this->report['type_of_specimen'], 'nasopharyngeal swab') !== false) {
                $desc_field = 'desc_1';
            } else if (stripos($this->report['type_of_specimen'], 'oropharyngeal swab') !== false) {
                $desc_field = 'desc_2';
            } else if (stripos($this->report['type_of_specimen'], 'sputum') !== false) {
                $desc_field = 'desc_3';
            }
            if (!empty($desc_field)) {
                $rex_yes_count = 0;
                $rex_no_count = 0;
                foreach ($this->selectedBacterias as $bat) {
                    if ($bat->is_hide) continue;
                    $load = 'none';
                    if ($bat->lmh == 'VL' || $bat->lmh == 'L') {
                        $load = 'low';
                    } else if ($bat->lmh == 'M') {
                        $load = 'med';
                    } else if ($bat->lmh == 'H' || $bat->lmh == 'VH') {
                        $load = 'high';
                    }
                    $virus = count($this->virus_detected_list) > 0 ? '1' : '0';


                    $sql  = "select {$desc_field} as rex_value
                    from ci_abx_texts where ctype = 'eic_rex' 
                    and condition1 = '{$bat->ID}'  
                    and condition2 = '{$load}' 
                    and condition3 = '{$virus}' ";

                    $rex_value = $wpdb->get_var($sql);
                    if ($rex_value !== null) {
                        if ($rex_value === '1') {
                            $rex_yes_count++;
                        } else if ($rex_value === '0') {
                            $rex_no_count++;
                        }

                        if ($this->debug) {
                            $this->log .= "<br>Rex check for bacteria ID {$bat->ID} ({$bat->name}) with load {$load} and virus {$virus}: rex_value={$rex_value}";
                        }
                    }
                }

                if ($rex_yes_count + $rex_no_count > 0) {

                    $default_texts = array(
                        '0' => array(
                            'no' => 'Bacterial findings appear clinically relevant. Antibiotic therapy may be appropriate. Correlate clinically.',
                            'yes' => 'Detected organisms are often respiratory colonizers. Correlate with symptoms to determine whether antibiotic treatment is necessary.',
                            'yes_no' => 'One or more bacterial findings appear clinically relevant. Antibiotic therapy may be appropriate. Correlate clinically.'
                        ),
                        '1' => array(
                            'no' => 'Mixed viral–bacterial infection is possible. Bacterial findings appear clinically relevant. Antibiotic therapy may be appropriate. Correlate clinically.',
                            'yes' => 'Viral detection likely explains the clinical picture. Bacterial findings are more consistent with colonization. Correlate with symptoms to determine whether antibiotic treatment is necessary.',
                            'yes_no' => 'Mixed viral–bacterial infection is possible. One or more bacterial findings appear clinically relevant. Antibiotic therapy may be appropriate. Correlate clinically.'
                        )
                    );
                    //get text
                    $textrow = $wpdb->get_row("select desc_1, desc_2, desc_3 from ci_abx_texts 
                                        where ctype = 'eic_rexc' 
                                        and condition1 = '{$virus}' ", ARRAY_A);


                    if ($textrow) {
                        $default_texts[$virus]['no']     = $textrow['desc_1'];
                        $default_texts[$virus]['yes']     = $textrow['desc_2'];
                        $default_texts[$virus]['yes_no']    = $textrow['desc_3'];
                    }

                    $typetext = 'yes_no';
                    if ($rex_no_count > 0 && $rex_yes_count == 0) {
                        $typetext = 'no';
                    } else if ($rex_yes_count > 0 && $rex_no_count == 0) {
                        $typetext = 'yes';
                    }

                    $text .= '<table class="noborder mgtb10 thawngabxtbl r_virus_comment"><tr><td class="thawngabx">!</td>';
                    $text .= '<td>' . $default_texts[$virus][$typetext] . '</td></tr></table>';

                    if ($this->debug) {
                        $this->log .= "<br>virus: {$virus} + type: {$typetext} (yes_count={$rex_yes_count}, no_count={$rex_no_count})";
                        $this->log .= $default_texts[$virus][$typetext];
                    }
                }
            }
        }
        return $text;
    }

    public function getTextbyCondition($ctype, $vl1 = null, $vl2 = null, $vl3 = null, $vl4 = null)
    {
        global $wpdb, $ci_debug_pass_log;

        if ($this->NotLactobacillus) {
            $ctype = 'bvm';
            $vl2 = null;
        }


        $this->log .= "<br>{$ctype}=> vl1=" . ($vl1 === null ? 'null' : $vl1) . " , vl2=" . ($vl2 === null ? 'null' : $vl2) . ", vl3=" . ($vl3 === null ? 'null' : $vl3) . ", vl4=" . ($vl4 === null ? 'null' : $vl4);

        $sql = "select * from ci_abx_texts 
        where ctype = '{$ctype}' 
        order by ID asc
        ";
        $rs     = $wpdb->get_results($sql, ARRAY_A);
        $arr    = array();
        $arr_empty = array();


        foreach ($rs as $r) {

            $pass       = true;
            $notpassby_logs = array();

            if ($vl1 === 'none' && $r['condition1'] !== '' && $r['condition1'] !== 'none') {
                $pass = false;
                $notpassby_logs['vl1'] = $ci_debug_pass_log;
            }

            if ($pass && $vl1 !== null && $r['condition1'] !== 'any' && $r['condition1'] !== '' && !$this->pass_condition($r['condition1'], $vl1)) {
                $pass = false;
                $notpassby_logs['vl1'] = $ci_debug_pass_log;
            }

            if ($pass && $vl2 !== null && $r['condition2'] !== 'any' && $r['condition2'] !== '' && !$this->pass_condition($r['condition2'], $vl2)) {
                $pass = false;
                $notpassby_logs['vl2'] = $ci_debug_pass_log;
            }

            if ($pass && $vl3 !== null && $r['condition3'] !== 'any' && $r['condition3'] !== '' && !$this->pass_condition($r['condition3'], $vl3)) {
                $pass = false;
                $notpassby_logs['vl3'] = $ci_debug_pass_log;
            }


            if ($pass && $vl4 !== null && $r['condition4'] !== 'any' && $r['condition4'] !== '') {

                if (is_string($vl4) && is_string($r['condition4']) && $vl4 === $r['condition4']) {
                    //both are string and equal
                } else if (($vl4 === 0 && stripos($r['condition4'], '<') === false && stripos($r['condition4'], '>') === false  && $r['condition4'] !== $vl4)) {
                    $pass = false;
                    $notpassby_logs['vl4'] = array($vl4, $r['condition4']);
                } else if (!$this->pass_condition($r['condition4'], $vl4)) {
                    $pass = false;
                    $notpassby_logs['vl4'] = $ci_debug_pass_log;
                }
            }

            if (isset($_GET['debugid']) && $_GET['debugid'] == $r['ID']) {
                var_dump($r);
                echo '<br><br>';
                var_dump($notpassby_logs);
                echo '<br><br>';
            }

            if ($pass) {
                $arr = $r;
                $this->log .= '=> ' . $r['ID'] . '<br>';
                if (isset($_GET['debugabx2'])) {
                    var_dump($r);
                }
                break;
            } else if (strlen(($vl1 !== null ? $r['condition1'] : '') . ($vl2 !== null ? $r['condition2'] : '') . ($vl3 !== null ? $r['condition3'] : '') . ($vl4 !== null ? $r['condition4'] : ''))) {
                $arr_empty = $r;
            }
        }

        if (empty($arr) && !empty($arr_empty)) {
            $arr = $arr_empty;
            $this->log .= '=>byempty: ' . $arr['ID'] . '<br>';
            if (isset($_GET['debugabx2'])) {
                var_dump($r);
            }
        }

        return $arr;
    }

    public function getMarkers()
    {
        $markers = $this->selectedMarkers;
        //checkgroup
        foreach ($this->group_markers as $group => $arr) {
            if (count($arr['markers']) > 1) {
                $markers[$arr['by']]->name = 'Group ' . $group;
                foreach ($arr['markers'] as $mkid) {
                    if ($mkid != $arr['by']) {
                        unset($markers[$mkid]);
                    }
                }
            }
        }
        return $markers;
    }

    public function sort_Markers()
    {
        uasort($this->selectedMarkers, function ($a, $b) {
            if (isset($a->conc) && $a->conc > 0) {
                return $a->conc < $b->conc ? -1 : 1;
            } else {
                return $a->lmh < $b->lmh ? -1 : 1;
            }
        });
    }

    public function getMarkerNames()
    {
        $markers = array();
        $this->markers_orphan = array();

        foreach ($this->selectedMarkers as $marker) {

            if (!empty($marker->group_name)) {
                $marker->name = $marker->group_name;
                $marker->display_name = $marker->group_name;
            }
            if (!isset($this->markers_orphan[$marker->name])) {
                $this->markers_orphan[$marker->name] = array(
                    'id' => $marker->ID,
                    'isorphan' => null,
                );
            }
            if ($marker->count_anti == 0 && $this->markers_orphan[$marker->name]['isorphan'] !== false) {

                $this->markers_orphan[$marker->name]['isorphan'] = true;
            } else {
                $this->markers_orphan[$marker->name]['isorphan'] = false;
            }

            if (!isset($markers[$marker->name])) {
                $markers[$marker->name] = array(
                    'name' => $marker->display_name,
                    'lmh' => $marker->lmh,
                );
            }
        }

        foreach ($markers as $k => $arr) {
            if ($this->markers_orphan[$k]['isorphan']) {
                $this->has_Orphan = true;
                $markers[$k]['name'] .=  '*';
            } else {
                unset($this->markers_orphan[$k]);
            }
        }

        if ($this->Quantitative) {
            uasort($markers, function ($a, $b) {
                return $a['lmh'] > $b['lmh'] ? -1 : 1;
            });
        }

        return $markers;
    }

    public function getOrphanMarkers()
    {
        $markers = array();
        foreach ($this->markers_orphan as $name => $orphan) {
            if (!$orphan['isorphan']) continue;
            $markers[$orphan['id']] = $this->selectedMarkers[$orphan['id']];
            $markers[$orphan['id']]->name = $name . '(o)';
        }
        return $markers;
    }

    public function getABXMarkerIcon($markerID, $antiID)
    {
        $score = 0;
        if (isset($this->rmbiograms[$markerID . '_g_' . $antiID])) {
            $score = $this->rmbiograms[$markerID . '_g_' . $antiID]->rm_score;
        } else if (isset($this->rmbiograms[$markerID . '_' . $antiID])) {
            $score = $this->rmbiograms[$markerID . '_' . $antiID]->rm_score;
        }
        $abx_icon = 'minus_grey';
        //++ = 3, + = 2,  +/- = 1, -/- = -1, - = -2
        // if -/- red dna, if "-"grey icon, +/- light green , + green, and ++ green checkmark.

        if ($score == -1) {
            $abx_icon = 'dna_red';
        } else if ($score == -2) {
            $abx_icon = 'minus_grey';
        } else if ($score == 3) {
            $abx_icon = 'checkmark_dark_green';
        } else if ($score == 2) {
            $abx_icon = 'plus_dark_green';
        } else if ($score == 1) {
            $abx_icon = 'plus_light_green';
        }

        return array($abx_icon, $score);
    }

    public function getAbxClasses()
    {
        $classes = array();
        $class_markers_orphan = array();

        foreach ($this->selectedMarkers as $mkid => $marker) {
            if (!empty($marker->resistants)) {
                $list = explode(',', $marker->resistants);
                $clpos = 0;
                foreach ($list as $cl) {
                    $cl = trim($cl);
                    if (empty($cl)) continue;
                    $clpos++;

                    if ($marker->count_anti == 0 && (!isset($class_markers_orphan[$cl]) || $class_markers_orphan[$cl] !== false)) {
                        $class_markers_orphan[$cl] = true;
                    } else {
                        $class_markers_orphan[$cl] = false;
                    }

                    if (!isset($classes[$cl])) {
                        $classes[$cl] = array(
                            'name' => $cl,
                            'lmh' => 0,
                            'position' => 0,
                            'isorphan' => 0,
                            'markers' => array(),
                        );
                    }

                    $classes[$cl]['position'] = $clpos;

                    $classes[$cl]['markers'][] = $marker->name;

                    if ($this->Quantitative) {
                        if ($marker->lmh !== '') {
                            $classes[$cl]['lmh'] = max($classes[$cl]['lmh'], $marker->lmh);
                        }
                    }
                }
            }
        }

        foreach ($classes as $k => $arr) {
            if (isset($class_markers_orphan[$k]) && $class_markers_orphan[$k]) {
                $this->has_Orphan = true;
                $classes[$k]['name'] .= '*';
                $classes[$k]['lmh'] = 0;
                $classes[$k]['isorphan'] = 1;
            }
        }

        if ($this->Quantitative) {
            uasort($classes, function ($a, $b) {
                if ($a['lmh'] == $b['lmh']) {
                    if ($a['isorphan'] < $b['isorphan']) {
                        return -1;
                    } else if ($a['isorphan'] > $b['isorphan']) {
                        return 1;
                    }
                    return $a['position'] < $b['position'] ? -1 : 1;
                }
                return $a['lmh'] > $b['lmh'] ? -1 : 1;
            });
        }

        return $classes;
    }

    public function resultAbxs()
    {
        global $wpdb;
        $abxs = array();
        $baseabxids = array();
        if ($this->base_abx) {
            $abxids = $wpdb->get_var("select desc_1 from ci_abx_texts where ctype = 'base_abx' and condition1 = '{$this->type}' ");
            if (!empty($abxids)) {
                $baseabxids = explode(',', $abxids);
            }
        }
        $count = 0;
        $limit1 = $this->limit_abx - count($baseabxids);
        foreach ($this->result_abx as $antiID => $arrs) {
            if ($count > $limit1) break;


            $count++;
            $abxs[$antiID] = $arrs;
        }
        foreach ($baseabxids as $abxid) {
            if (isset($this->result_abx[$abxid])) {
                $abxs[$abxid] = $this->result_abx[$abxid];
            }
        }
        return $abxs;
    }

    public function listABXMatches()
    {

        $return     = array();

        $inum       = 0;
        $limitall   = 5;

        $dosekey    = 'dose_' . $this->subtype;
        $dosekey2   = '';
        $notekey    = 'notes_' . $this->subtype;
        $pediatric_commentkey   = '';
        $pediatric_commentkey_2 = '';

        if (!empty($this->patient_renal)) {
            if ($this->debug) {
                $this->log .= "<br>Renal: " . $this->patient_renal . "<br>";
            }
            switch ($this->patient_renal) {
                case 'normal':
                    $dosekey2 = 'renal_crcla30_' . $this->subtype;
                    break;
                case 'moderate':
                    $dosekey2 = 'renal_crclb30_' . $this->subtype;
                    break;
                case 'severe':
                    $dosekey2 = 'renal_crclb10_' . $this->subtype;
                    break;
                case 'dialysis':
                    $dosekey2 = 'dialysis_' . $this->subtype;
                    break;
            }
        } else if ($this->Pediatric_ranges && $this->age < 18) {
            if ($this->age == 0 && $this->age_months < 2) {
                $dosekey = '';
            } else if ($this->age <= 1) {
                $dosekey = 'dose_1y_' . $this->subtype;
            } else if ($this->age < 12) {
                $dosekey = 'dose_2y_' . $this->subtype;
            } else {
                $dosekey = 'dose_12y_' . $this->subtype;
            }
            $pediatric_commentkey = 'pediatric_comment';
            $pediatric_commentkey_2 = 'pediatric_comment_' . $this->subtype;
        }




        $has_oral_top = false;
        if ($this->PrioritizeOral) {
            foreach ($this->result_abx as $antiID => $a) {

                if (!$a['exclude'] && (count($a['numpairs']) == 0 || (isset($a['match_all']) && $a['match_all']))) {
                    if ($a['iconcode'] == 'pill') {
                        $has_oral_top = true;
                        break;
                    }
                }
            }
        }


        foreach ($this->result_abx as $antiID => $a) {

            if (count($return) >= $limitall) break;

            if (!$a['exclude'] && (count($a['numpairs']) == 0 || (isset($a['match_all']) && $a['match_all']))) {


                $anti = $this->all_antibiotics[$antiID];


                $a1 = array(
                    'ID' => $antiID,
                    'name' => !empty($a['abrev']) ?  $a['abrev'] :  $a['name'],
                    'brand_name' => $anti->brand_name,
                    'dose' => '',
                    'dose1' => '',
                    'dose2' => '',
                    'notes' => '',
                    'pediatric_comment' => '',
                );
                if (isset($anti->trimester_1)) {
                    $a1['trimester_1'] = $anti->trimester_1;
                    $a1['trimester_2'] = $anti->trimester_2;
                    $a1['trimester_3'] = $anti->trimester_3;
                    $a1['trimester_l'] = $anti->trimester_l;
                    $a1['pregnancy_note'] = $anti->pregnancy_note;
                }
                if ($this->Biofilm) {
                    $a1['BA'] = $anti->ba;
                }

                if (isset($a['antid2']) && $a['antid2'] > 0) {
                    $anti2 = $this->all_antibiotics[$a['antid2']];
                    $a1['brand_name'] .= ' + ' . $anti2->brand_name;
                }

                if (isset($anti->{$dosekey2}) && !empty($anti->{$dosekey2})) {
                    $a1['dose'] = $anti->{$dosekey2};
                    $a1['dose1'] = $anti->{$dosekey2};
                    if ($this->debug) {
                        $this->log .= "<br>Dose by renal: " . $anti->abx . " => " . $anti->{$dosekey2} . "<br>";
                    }
                } else if (isset($anti->{$dosekey}) && !empty($anti->{$dosekey})) {
                    $a1['dose'] = $anti->{$dosekey};
                    $a1['dose1'] = $anti->{$dosekey};
                }

                if (isset($anti->{$pediatric_commentkey_2}) && !empty($anti->{$pediatric_commentkey_2})) {
                    $a1['pediatric_comment'] = $anti->{$pediatric_commentkey_2};
                } else if (isset($anti->{$pediatric_commentkey}) && !empty($anti->{$pediatric_commentkey})) {
                    $a1['pediatric_comment'] = $anti->{$pediatric_commentkey};
                }
                if (!empty($anti->{$notekey})) {
                    $a1['notes'] = $anti->{$notekey};
                }

                if ($this->count_match_all_before_pair > 3) {
                    //check AS=5
                    if ($a['diamond'] && $a['count_checkmark'] == 0) { //&& (!$this->PrioritizeOral || $a['iconcode'] != 'pill')

                        //check other oral
                        $exclude_diamond = true;
                        if ($this->PrioritizeOral && !$has_oral_top) {
                            $exclude_diamond = false;
                        }
                        if ($exclude_diamond) {
                            if ($this->debug) {
                                $this->log .= '-' . $a1['name'] . ' hide by S=5 and no checkmark<br>';
                            }
                            continue;
                        }
                    }
                }

                $inumabx = $inum;
                if ($this->PrioritizeOrialB4Activity && $a['iconcode'] != 'pill') {
                    $inumabx += 20;
                }

                $return[$inumabx] = $a1;

                $inum++;
            }
        }


        $countpair = 0;
        foreach ($this->pair_results as $anids_1_2) {
            if ($countpair > 3) break;

            $antiID1 = $anids_1_2[0];
            $antiID2 = $anids_1_2[1];

            $anti1 = $this->all_antibiotics[$antiID1];
            $anti2 = $this->all_antibiotics[$antiID2];

            $ab1 = $this->result_abx[$antiID1];
            $ab2 = $this->result_abx[$antiID2];
            $antiname1 = !empty($ab1['abrev']) ?  $ab1['abrev'] :  $ab1['name'];
            $antiname2 = !empty($ab2['abrev']) ?  $ab2['abrev'] :  $ab2['name'];

            $both_oral = false;
            if ($anti1->oral && $anti2->oral) {
                $both_oral = true;
            }
            $a1 = array(
                'ID' => $antiID1,
                'ID2' => $antiID2,
                'name' => $antiname1 . ' + ' . $antiname2,
                'brand_name' => $anti1->brand_name . ' + ' . $anti2->brand_name,
                'brand_name1' => $anti1->brand_name,
                'brand_name2' => $anti2->brand_name,
                'dose' => '',
                'dose1' => '',
                'dose2' => '',
                'is_pair' => true,
                'both_oral' => $both_oral,
            );

            if (isset($anti1->trimester_1)) {
                $a1['trimester_1'] = $anti1->trimester_1;
                $a1['trimester_2'] = $anti1->trimester_2;
                $a1['trimester_3'] = $anti1->trimester_3;
                $a1['trimester_l'] = $anti1->trimester_l;
                $a1['pregnancy_note'] = $anti1->pregnancy_note;
            }

            if ($this->Biofilm) {
                $a1['BA'] = $anti1->ba == 2 || $anti2->ba == 2 ? 2 : ($anti1->ba == 1 && $anti2->ba == 1 ? 1 : 0);

                if ($this->debug) {
                    $this->log .= $antiname1 . " ba= {$anti1->ba}, " . $antiname2 . " ba=" . $anti2->ba . ' => ' . $a1['BA'] . '<br><br>';
                }
            }

            if (isset($anti1->{$dosekey2}) && !empty($anti1->{$dosekey2})) {
                $a1['dose1'] = $anti1->{$dosekey2};
            } else if (isset($anti1->{$dosekey}) && !empty($anti1->{$dosekey})) {
                $a1['dose1'] = $anti1->{$dosekey};
            }


            if (isset($anti2->{$dosekey2}) && !empty($anti2->{$dosekey2})) {
                $a1['dose2'] = $anti2->{$dosekey2};
            } else if (isset($anti2->{$dosekey}) && !empty($anti2->{$dosekey})) {
                $a1['dose2'] = $anti2->{$dosekey};
            }


            $return[$inum] = $a1;
            $inum++;
            $countpair++;
        }

        ksort($return);

        $output = array();
        foreach ($return as $a) {
            if (count($output) >= $limitall) break;
            $output[] = $a;
        }

        return $output;
    }

    public function abx_name($antiID)
    {
        return $this->all_antibiotics[$antiID]->abx;
    }

    public function abx_class_ID($antiID)
    {
        return $this->all_antibiotics[$antiID]->class_ID;
    }

    public function abx_abrev($antiID)
    {
        return $this->all_antibiotics[$antiID]->abb;
    }

    public function abx_BA($antiID)
    {
        return intval($this->all_antibiotics[$antiID]->ba);
    }

    public function abx_R($antiID)
    {
        return $this->all_antibiotics[$antiID]->risk;
    }

    public function abx_C($antiID)
    {
        return $this->all_antibiotics[$antiID]->cost;
    }

    public function abx_S($antiID)
    {
        return $this->all_antibiotics[$antiID]->as;
    }
    public function abx_Risk($antiID)
    {
        return $this->all_antibiotics[$antiID]->risk;
    }


    public function abx_Icon($antiID)
    {
        if ($this->all_antibiotics[$antiID]->oral) {
            return 'pill'; //pill
        } else if ($this->all_antibiotics[$antiID]->inj || $this->all_antibiotics[$antiID]->iv) {
            return 'syringe'; //syringe
        }
        return '';
    }


    public function abx_LT($batID, $markerID, $antiID)
    {
        $LT    = isset($this->lt_scores[$batID . '_' . $markerID . '_' . $antiID]) ? $this->lt_scores[$batID . '_' . $markerID . '_' . $antiID] : 0;

        return $LT;
    }

    public function abx_A($antiID)
    {
        $anti = $this->all_antibiotics[$antiID];
        $A = 0;
        if ($anti->inj) {
            $A = 2;
        } else if ($anti->iv) {
            $A = 3;
        }
        return $A;
    }

    public function get_scores($bat_id, $marker_id)
    {
        if (!isset($this->scores[$bat_id])) {
            return array();
        }
        if (strtolower($marker_id) == 'nr') {
            return is_array($this->scores[$bat_id]['nr']) ? $this->scores[$bat_id]['nr'] : array();
        } else if (strtolower($marker_id) == 'common') {
            return is_array($this->scores[$bat_id]['common']) ? $this->scores[$bat_id]['common'] : array();
        } else {
            return isset($this->scores[$bat_id]['markers'][$marker_id]) && is_array($this->scores[$bat_id]['markers'][$marker_id]) ? $this->scores[$bat_id]['markers'][$marker_id] : array();
        }
    }

    public function get_bat_risk($batid, $type = '')
    {
        $risk = array(
            'number' => $this->scores[$batid]['brisk'] + $this->scores[$batid]['mrisk'],
            'mmaxrisk' => $this->scores[$batid]['mmaxrisk'],
            'color' => 'grey',
        );

        if ($risk['number'] > 10) $risk['number'] = 10;
        else if ($risk['number'] < 1) $risk['number'] = 1;


        $risk['number2'] = min($risk['number'], $risk['mmaxrisk'] > 0 ? $risk['mmaxrisk'] : $risk['number']);

        if ($type == 'pathogenicity') {
            $risk['number'] = $risk['number2'];
        }
        /*pathogenicity=1, if value ≤ 2
        pathogenicity=2, if 2<value≤4
        pathogenicity=3,  if 4<value≤6
        pathogenicity=4, if 6<value≤8
        pathogenicity=5, if 8<value≤10
        */


        if ($risk['number'] > 8) {
            $risk['color'] = 'red';
        } else if ($risk['number'] > 6) {
            $risk['color'] = 'orange';
        } else if ($risk['number'] > 4) {
            $risk['color'] = 'yellow_dask';
        } else if ($risk['number'] > 2) {
            $risk['color'] = 'yellow_light';
        } else if (!isset($this->scores[$batid])) {
            $risk['color'] = 'notexist';
        }
        return $risk;
    }

    public function getMaxRiskBacteria()
    {
        $max_risk = 0;
        foreach ($this->selectedBacterias as $bat) {
            if ($bat->is_hide || $bat->isexclude) continue;
            $risk = $this->get_bat_risk($bat->ID);
            $max_risk = max($max_risk, $risk['number']);
        }
        if ($this->debug) {
            $this->log .= 'Max risk: ' . $max_risk . '<br>';
        }

        if ($max_risk == 0) $max_risk = 1;
        if ($max_risk > 10) $max_risk = 10;
        return $max_risk;
    }


    public function get_gram_map_LS()
    {
        global $wpdb;
        $this->gram_map_LS = array();
        $rs = $wpdb->get_results("select ID, gram_type from ci_abx_bacterias 
        where bacteria_name like 'G-%' or bacteria_name like 'G+%' ");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        foreach ($rs as $r) {
            $this->gram_map_LS[$r->gram_type] = $r->ID;
        }
    }

    public function getLS($batid, $antiID, $AB, $marker_class_id = 0, $marker_ID = 0)
    {
        global $wpdb;

        $marker_class_id = intval($marker_class_id);


        $bat    = $this->selectedBacterias[$batid];
        $state  = $this->selectedState;
        if (empty($state)) {
            $state = 'US';
        }

        if (!is_array($this->gram_map_LS)) {
            $this->get_gram_map_LS();
        }

        if (empty($this->cache_ls_scores)) {
            $rs = $wpdb->get_results(
                "select 
                ls.ID,
                ls.bacteria_ID,
                ls.marker_group_N,
                ls.score,
                ls.score2,
                ls.score3,
                anti.ID as antibio_ID,
                re.states,
                re.name as re_name,
                ba.marker_classes_1,
                ba.marker_classes_2,
                ba.marker_classes_3,
                ba.marker_classes_4
                FROM ci_abx_local_resistance2 ls
                JOIN ci_abx_regions re ON re.ID = ls.region_ID
                JOIN ci_abx_antibiotics anti ON anti.ID = ls.antibio_ID
                JOIN ci_abx_bacterias ba ON ba.ID = ls.bacteria_ID
                WHERE FIND_IN_SET('{$state}' , re.states ) or re.name = 'USA' "
            );

            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }

            if ($this->debug) {
                $this->log .= $wpdb->last_error;
            }

            $foundstates = array();
            foreach ($rs as $r) {
                $statefound = $r->states == '' || $r->re_name == 'USA' ? 'US' : $state;
                $foundstates[$statefound] = $statefound;


                $gramid = array_search($r->bacteria_ID, $this->gram_map_LS);

                $bateids = array($r->bacteria_ID);

                if ($gramid > 0) {
                    $bateids[] = 'gram_' . $gramid;
                }

                foreach ($bateids as $rowbacID) {

                    if ($r->marker_group_N > 0) {
                        $batfield = 'marker_classes_' . $r->marker_group_N;
                        if (!empty($r->{$batfield})) {
                            foreach (explode(',', $r->{$batfield}) as $mkclid) {

                                if ($r->score3 !== '' && !is_null($r->score3)) {
                                    $k = strtolower($rowbacID . '-' . $mkclid . '-' . $r->antibio_ID . '-' . $statefound . '-score3');
                                    $this->cache_ls_scores[$k] = $r->score3;
                                }
                                if ($r->score2 !== '' && !is_null($r->score2)) {
                                    $k = strtolower($rowbacID . '-' . $mkclid . '-' . $r->antibio_ID . '-' . $statefound . '-score2');
                                    $this->cache_ls_scores[$k] = $r->score2;
                                }
                            }
                        }
                    } else {

                        if ($r->score3 !== '' && !is_null($r->score3)) {
                            $k = strtolower($rowbacID . '-0-' . $r->antibio_ID . '-' . $statefound . '-score3');
                            $this->cache_ls_scores[$k] = $r->score3;
                        }
                        if ($r->score2 !== '' && !is_null($r->score2)) {
                            $k = strtolower($rowbacID . '-0-' . $r->antibio_ID . '-' . $statefound . '-score2');
                            $this->cache_ls_scores[$k] = $r->score2;
                        }
                    }
                }
            }
        }




        $score = '';
        if ($AB == 1) $score = 70;
        else if ($AB == 2) $score = 90;
        else if ($AB == 3) $score = 100;


        $keys = array();

        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-' . $state . '-score3'); //#1
        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-' . $state . '-score2'); //#2
        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-US-score3'); //#3
        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-US-score2'); //#4

        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-' . $state . '-score3'); //#5
        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-' . $state . '-score2'); //#6
        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-US-score3'); //#7
        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-US-score2'); //#8

        // $keys[] = strtolower('gram_' . $bat->gram_type . '-0-' . $antiID . '-' . $state. '-score3');//9
        // $keys[] = strtolower('gram_' . $bat->gram_type . '-0-' . $antiID . '-' . $state. '-score2');//10
        // $keys[] = strtolower('gram_' . $bat->gram_type . '-0-' . $antiID . '-US-score3');//11
        // $keys[] = strtolower('gram_' . $bat->gram_type . '-0-' . $antiID . '-US-score2');//12

        $keys[] = strtolower($batid . '-0-' . $antiID . '-' . $state . '-score3'); //#13
        $keys[] = strtolower($batid . '-0-' . $antiID . '-' . $state . '-score2'); //#14
        $keys[] = strtolower($batid . '-0-' . $antiID . '-US-score3'); //#15
        $keys[] = strtolower($batid . '-0-' . $antiID . '-US-score2'); //#16

        $selected_key = '';

        foreach ($keys as $key) {
            if (isset($this->cache_ls_scores[$key])) {
                if ($AB == 0 && $this->cache_ls_scores[$key] !== 'NR') {
                    break;
                }
                $selected_key = $key;
                $score = floatval($this->cache_ls_scores[$key]);
                if ($score == 0 && $this->cache_ls_scores[$key] !== '0') {
                    $score = $this->cache_ls_scores[$key];
                }
                break;
            }
        }

        if (empty($selected_key) && empty($score) && $AB == 0) {
            $score = 'NR';
        }

        if ($this->debug) {
            foreach ($keys as $i => $key) {
                $selected = $key == $selected_key  ? $i + 1 : 0;
                $vl = $selected > 0 ? $this->cache_ls_scores[$key] : '-';
                $this->debug_ls_by[$batid . '_' . $marker_ID . '_' . $antiID][$key] = array($vl, $selected, $selected_key);
            }

            $scoresource1 = '';
            if ($AB == 1) $scoresource1 = 70;
            else if ($AB == 2) $scoresource1 = 90;
            else if ($AB == 3) $scoresource1 = 100;

            $this->debug_ls_by[$batid . '_' . $marker_ID . '_' . $antiID]['0'] = array($scoresource1, empty($selected_key) ? 1 : 0, $selected_key);
        }

        if ($AB == 0 && $score !== 'NR') $score = 0;

        return $score;
    }

    public function getNTR($batid, $antiID)
    {
        global $wpdb;

        $ntr        = 0;

        $abx_class_ID  = $this->all_antibiotics[$antiID]->class_ID;

        $marker_class_IDs = $wpdb->get_col(
            "select rm_class_ID from ci_abx_class_abx_rm_class
            where abx_class_ID = '{$abx_class_ID}' "
        );

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }


        //exclude maker detected
        $mknotin = array();
        foreach ($this->selectedMarkers as $mkid => $mkarr) {
            $mknotin[] = $mkid;
        }


        if (count($marker_class_IDs) > 0) {

            $marker_class_IDs_str = implode(',', $marker_class_IDs);

            $sql = "select sum(ntr.score) 
                from ci_abx_ntr ntr
                join ci_abx_resistance_markers rm ON rm.ID = marker_ID
                where ntr.bacteria_ID = {$batid} 
                and rm.class_ID in ({$marker_class_IDs_str}) ";

            if ($this->NTR_Multiflier) {
                $sql = "select sum(IF(carm.weight>0, ntr.score * carm.weight, ntr.score) ) 
                from ci_abx_ntr ntr
                join ci_abx_resistance_markers rm ON rm.ID = marker_ID
                JOIN ci_abx_class_abx_rm_class carm ON carm.rm_class_ID = rm.class_ID and carm.abx_class_ID = {$abx_class_ID}
                where ntr.bacteria_ID = {$batid} 
                and rm.class_ID in ({$marker_class_IDs_str}) ";
            }

            if (!empty($this->limit_markers)) {
                $limit_markers_str = implode(',', $this->limit_markers);
                $sql .= " and rm.ID in ({$limit_markers_str}) ";
            } else {
                if (is_array($this->marker_ids_by_panel) && count($this->marker_ids_by_panel) > 0) {
                    $mkid_str = implode(',', $this->marker_ids_by_panel);
                    $sql .= " and rm.ID in ({$mkid_str}) ";
                } else {
                    $sql .= " and rm.{$this->type}=1 ";
                }
            }

            if (count($mknotin) > 0) {
                $mknotin_str = implode(', ', $mknotin);
                $sql .= " and rm.ID not in ({$mknotin_str}) ";
            }

            $ntr = $wpdb->get_var($sql);


            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }

            if (isset($_GET['debugntr'])) {
                if ($this->NTR_Multiflier) {
                    $this->NTR_Multiflier = false;
                    $this->getNTR($batid, $antiID);
                    $this->log .= $this->all_antibiotics[$antiID]->abx;
                    $this->log .= ". New NTR= " . $ntr;
                    $this->log .= '<br><br>';
                    $this->NTR_Multiflier = true;
                } else {

                    $this->log .= $this->all_antibiotics[$antiID]->abx;
                    $this->log .= '.Old NTR=' . $ntr;
                    $this->log .= '<br><br>';
                }
            }
        }



        if ($ntr > 90) $ntr = 90; //NTR to 90 instead of 100 
        else if (empty($ntr)) $ntr = 0;
        return $ntr;
    }

    public function check_exclude($skip_quantitative_exclude = false)
    {
        global $wpdb;

        if ($this->debug) {
            $this->log_exclude .= '<h5>Check exclude bacteria</h5>';
        }
        $exclude_count = 0;

        $load_medium_num    = 100000;
        if (tr_get_option('ci_pdf_data', 'new_abx_piefactor10', false)) {
            $load_medium_num = $load_medium_num * 10;
        }
        foreach ($this->selectedBacterias as $bid => &$bat) {

            $bat->exclude_by = 0;
            $bat->load_percent = $this->total_load > 0 ? $bat->load / $this->total_load * 100 : 0;

            if ($this->debug) {
                $this->log_exclude .= $bat->name . ' %Load = ' . $bat->load_percent;
                $this->log_exclude .= '<br>';
            }


            if ($this->Quantitative) {
                if ($skip_quantitative_exclude) {
                    $bat->isexclude = false;
                    $bat->exclude_pair = false;
                    if ($this->debug) {
                        $this->log_exclude .= 'Skip Q3/Q5 quantitative exclude for %quant_new';
                        $this->log_exclude .= '<br><br>';
                    }
                    $this->count_bac_available++;
                    continue;
                }

                $bat->goexclude = false;
                if ($this->Quantitative5Levels) {
                    //5 levels
                    if ($bat->is_risk0) {
                        if (($bat->lmh == 'VL' && $this->RiskLMH_Score >= self::VERY_LOW_SCORE)
                            || ($bat->lmh == 'L' && $this->RiskLMH_Score >= self::LOW_SCORE)
                            || ($bat->lmh == 'M' && $this->RiskLMH_Score >= self::MED_SCORE)
                        ) {
                            $bat->goexclude = true;
                            $bat->exclude_by = 2; //Low Risk
                        }
                    }
                    if ($this->excludeNC0) {
                        if ($bat->is_nc0) {
                            if (($bat->lmh == 'VL' && $this->NCLMH_Score > self::VERY_LOW_SCORE)
                                || ($bat->lmh == 'L' && $this->NCLMH_Score > self::LOW_SCORE)
                            ) {
                                $bat->goexclude = true;
                                $bat->exclude_by = 3; //Contaminant
                            }
                        } else {
                            if (($bat->lmh == 'VL' && $this->NCLMH_Score >= self::MED_SCORE)) {
                                $bat->goexclude = true;
                                $bat->exclude_by = 4; //Load
                            }
                        }
                    }
                } else {
                    //3 levels
                    if ($bat->is_risk0) {

                        if (($bat->lmh == 'L' && $this->RiskLMH_Score >= self::LOW_SCORE)
                            || ($bat->lmh == 'M' && $this->RiskLMH_Score >= self::MED_SCORE)
                        ) {
                            $bat->goexclude = true;
                            $bat->exclude_by = 2; //Low Risk
                        }
                    }

                    if ($this->excludeNC0 && $bat->is_nc0) {
                        if ($bat->lmh == 'L' && $this->NCLMH_Score > self::LOW_SCORE) {
                            $bat->goexclude = true;
                            $bat->exclude_by = 3; //Contaminant
                        }
                    }
                }


                if ($bat->goexclude) {
                    $bat->isexclude = true;
                    $exclude_count++;
                    if ($this->debug) {
                        $this->log_exclude .= 'Excluded ' . $bat->name . ' by : LMH=' . $bat->lmh . ' & RiskLMH_Score=' . $this->RiskLMH_Score . ' & NCLMH_Score=' . $this->NCLMH_Score;
                        $this->log_exclude .= '<br><br>';
                    }
                } else {
                    $bat->isexclude = false;
                    $bat->exclude_pair = false;
                    $bat->is_risk0 = false;
                }
            }

            if ($bat->isexclude) {
                $this->hasExcludeRiskLow = true;
            } else {
                $this->count_bac_available++;
            }
        } //endforeach

        if ($this->debug) {
            if ($exclude_count > 0) {
                $this->log_exclude .= '<h5>Excluded bacteria count: ' . $exclude_count . '</h5>';
            } else {
                $this->log_exclude .= '<h5>No bacteria excluded</h5>';
            }
        }
    }

    public function check_exclude_biofilm()
    {
        if ($this->debug) {
            $this->log_exclude .= '<br><br><h3>Run Exclude Biofilm</h3>';
        }
        $has_excluded = false;
        foreach ($this->selectedBacterias as $bid => &$bat) {
            if ($this->debug) {
                $this->log_exclude .= '+ ' . $bat->name . ' : %load=' . $bat->load_percent . ' | Level: ' . $bat->lmh . ' | No RM >=5: ' . ($bat->no_RMrisk5 ? 'yes' : 'no') . ' | NC = ' . $bat->nc;
                $this->log_exclude .= '<br>';
            }

            if ($bat->isexclude) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> Already excluded, skip exclude Biofilm';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }
            //dont exclude nc = 2
            if ($bat->nc == 2) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> NC=2, skip exclude Biofilm';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }

            //check exclude if %<1 & load <=medium & br <=4
            if ($bat->load_percent < 1 && in_array($bat->lmh, array('VL', 'L', 'M'))  && $bat->br <= 4) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $has_excluded = true;
                $this->count_bac_available--;
                $bat->exclude_by = 1; //% + Low BR
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded ' . $bat->name . ' by : %load=' . $bat->load_percent . ' <1 & ' . $bat->lmh . ' <= Med & br=' . $bat->br . ' <=4';
                    $this->log_exclude .= '<br><br>';
                }
            }

            //https://lite.evernote.com/note/5b2cc367-4905-f0da-b644-2970d9cff1a9
            //rule1
            else if ($bat->load_percent < 0.1 && in_array($bat->lmh, array('VL', 'L', "M"))) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $has_excluded = true;
                $this->count_bac_available--;
                $bat->exclude_by = 5; //Trace
                if ($this->debug) {
                    $this->log_exclude .= ' -> Rule1: Excluded ' . $bat->name . ' by : %load=' . $bat->load_percent . ' < 0.1 & ' . $bat->lmh . ' <= Med ';
                    $this->log_exclude .= '<br><br>';
                }
            }

            //rule2
            else  if (
                $bat->load_percent < 1 && in_array($bat->lmh, array('VL', 'L', "M"))
                && $bat->bro <= 6 && $bat->no_RMrisk5 && !$bat->is_synergypair
            ) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $has_excluded = true;
                $this->count_bac_available--;
                $bat->exclude_by = 6; //empy
                if ($this->debug) {
                    $this->log_exclude .= ' -> Rule2: Excluded ' . $bat->name . ' by : %load=' . $bat->load_percent . ' < 1 & ' . $bat->lmh . ' <= Med & - no RM risk≥5 exists- synergy doesn\'t exists';
                    $this->log_exclude .= '<br><br>';
                }
            }

            //rule3
            else if (
                $bat->load_percent < 5 && in_array($bat->lmh, array('VL', 'L', "M"))
                && $bat->bro <= 4 && $bat->nc == 0
            ) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $has_excluded = true;
                $this->count_bac_available--;
                $bat->exclude_by = 7; //Colonizer
                if ($this->debug) {
                    $this->log_exclude .= ' -> Rule3: Excluded ' . $bat->name . ' by : %load=' . $bat->load_percent . ' < 5 & ' . $bat->lmh . ' <= Med & Bro< 4 & NC = 0';
                    $this->log_exclude .= '<br><br>';
                }
            } else {
                if ($this->debug) {
                    $this->log_exclude .= ' -> Not excluded ';
                    if ($bat->lmh == 'H') {
                        $this->log_exclude .= ' - Level=H';
                    }
                    $this->log_exclude .= '<br><br>';
                }
            }
        }

        //rebuild BRI if has excluded
        if ($has_excluded) {
            $this->check_biofilm();
        }
    }

    public function check_exlude_Percent_Quantitative()
    {

        if ($this->debug) {
            $this->log_exclude .= '<br><br><h3>Run Exclude %Quantitative</h3>';
        }
        if (count($this->selectedBacterias) == 1) {
            if ($this->debug) {
                $this->log_exclude .= ' - Only 1 bacteria, skip exclude %Quantitative';
                $this->log_exclude .= '<br><br>';
            }
            return;
        }
        //https://lite.evernote.com/note/3e3ac888-9c59-e530-4f97-b7b8af2b221e
        foreach ($this->selectedBacterias as $bid => &$bat) {
            if ($this->debug) {
                $this->log_exclude .= '+ ' . $bat->name . ' : %load=' . $bat->load_percent . ' | Level: ' . $bat->lmh . ' | No RM >=5: ' . ($bat->no_RMrisk5 ? 'yes' : 'no') . ' | NC = ' . $bat->nc;
                $this->log_exclude .= '<br>';
            }

            if ($bat->is_execlude) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> Already excluded, skip exclude %Quantitative';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }
            //dont exclude nc = 2
            if ($bat->nc == 2) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> NC=2, skip exclude %Quantitative';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }



            //rule1: check exclude if - % < 0.1% and - Load ≤ Medium
            if ($bat->load_percent < 0.1 && in_array($bat->lmh, array('VL', 'L', "M"))) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 5; //Trace
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule1: %load=' . $bat->load_percent . ' < 0.1 | Level: ' . $bat->lmh . ' <= Med ';
                    $this->log_exclude .= '<br><br>';
                }
            }

            //rule2
            else  if (
                $bat->load_percent < 1 && in_array($bat->lmh, array('VL', 'L', "M")) && $bat->no_RMrisk5
            ) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 8; //Low Load
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded  by Rule2: %load=' . $bat->load_percent . ' < 1 & ' . $bat->lmh . ' <= Med | No RM >=5: ' . ($bat->no_RMrisk5 ? 'yes' : 'no');
                    $this->log_exclude .= '<br><br>';
                }
            }

            //rule3
            else if (
                $bat->load_percent < 5 && in_array($bat->lmh, array('VL', 'L', "M")) && $bat->nc == 0
            ) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 7; //Colonizer
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule3: %load=' . $bat->load_percent . ' < 5 | Level ' . $bat->lmh . ' | No RM >=5: ' . ($bat->no_RMrisk5 ? 'yes' : 'no') . ' | NC = ' . $bat->nc;
                    $this->log_exclude .= '<br><br>';
                }
            } else  if ($this->debug) {
                $this->log_exclude .= ' Not Excluded';
                $this->log_exclude .= '<br><br>';
            }
        }

        if ($this->debug) {
            $this->log_exclude .= '<br><br>';
        }
    }

    public function check_exlude_Percent_Quantitative_New()
    {

        if ($this->debug) {
            $this->log_exclude .= '<br><br><h3>Run Exclude %Quant New</h3>';
        }

        foreach ($this->selectedBacterias as $bid => &$bat) {
            if ($this->debug) {
                $this->log_exclude .= '+ ' . $bat->name . ' : %load=' . $bat->load_percent . ' | Level: ' . $bat->lmh . ' | No RM >=5: ' . ($bat->no_RMrisk5 ? 'yes' : 'no') . ' | NC = ' . $bat->nc;
                $this->log_exclude .= '<br>';
            }

            if ($bat->isexclude) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> Already excluded, skip exclude %Quant New';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }

            if ($bat->risk >= 6) {
                if ($this->debug) {
                    $this->log_exclude .= ' -> Risk >= 6, skip exclude %Quant New';
                    $this->log_exclude .= '<br><br>';
                }
                continue;
            }

            if ($bat->load_percent < 0.1 && in_array($bat->lmh, array('VL', 'L', "M"))) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 5; //Trace
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule1 Trace: %load=' . $bat->load_percent . ' < 0.1 | Level: ' . $bat->lmh . ' <= Med';
                    $this->log_exclude .= '<br><br>';
                }
            } else if ($bat->load_percent < 1 && in_array($bat->lmh, array('VL', 'L'))) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 9; //Low Burden
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule2 Low Burden: %load=' . $bat->load_percent . ' < 1 | Level: ' . $bat->lmh . ' <= Low';
                    $this->log_exclude .= '<br><br>';
                }
            } else if ($bat->load_percent < 2 && in_array($bat->lmh, array('VL', 'L', "M")) && $bat->no_RMrisk5 && $bat->nc < 2) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 6; //Low Priority
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule3 Low Priority: %load=' . $bat->load_percent . ' < 2 | Level: ' . $bat->lmh . ' <= Med | No RM >=5: yes | NC < 2';
                    $this->log_exclude .= '<br><br>';
                }
            } else if ($bat->load_percent < 5 && $bat->lmh == 'M' && $bat->nc == 0) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 7; //Colonizer
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule4 Colonizer: %load=' . $bat->load_percent . ' < 5 | Level: M | NC = 0';
                    $this->log_exclude .= '<br><br>';
                }
            } else if ($bat->load_percent < 10 && in_array($bat->lmh, array('VL', 'L')) && $bat->nc == 0) {
                $bat->isexclude = true;
                $this->hasExcludeRiskLow = true;
                $this->count_bac_available--;
                $bat->exclude_by = 10; //Commensal
                if ($this->debug) {
                    $this->log_exclude .= ' -> Excluded by Rule5 Commensal: %load=' . $bat->load_percent . ' < 10 | Level: ' . $bat->lmh . ' <= Low | NC = 0';
                    $this->log_exclude .= '<br><br>';
                }
            } else  if ($this->debug) {
                $this->log_exclude .= ' Not Excluded';
                $this->log_exclude .= '<br><br>';
            }
        }

        if ($this->debug) {
            $this->log_exclude .= '<br><br>';
        }
    }

    public function check_Conditional()
    {
        global $wpdb;
        //Conditional

        if ($this->enableConditional) {
            $bids = array();
            foreach ($this->selectedBacterias as $bid => $bat) {
                if (!$bat->isexclude) {
                    $bids[] = $bid;
                }
            }
            $bids_str = implode(',', $bids);
            $mabxs = array();
            if (!empty($bids_str)) {
                $mabxs = $wpdb->get_results("select c.*,
                a.* 
                from ci_abx_conditionals c 
                JOIN ci_abx_antibiotics a ON a.ID = c.anti_ID
                where c.panel = '{$this->type}'
                and c.bacteria_ID in ({$bids_str}) ");
            }


            $abxs_not_exists = array();
            foreach ($mabxs as $mabx) {

                $this->conditional_abxs[$mabx->ID] = $mabx;

                if (!isset($this->all_antibiotics[$mabx->ID])) {
                    $abxs_not_exists[] = $mabx->ID;
                } else {
                    $dosekey = 'dose_' . $this->subtype;
                    $notekey = 'notes_' . $this->subtype;
                    if (!empty($this->conditional_abxs[$mabx->ID]->dose)) {
                        $this->all_antibiotics[$mabx->ID]->{$dosekey} = $this->conditional_abxs[$mabx->ID]->dose;
                    }
                    if (!empty($this->conditional_abxs[$mabx->ID]->note)) {
                        $this->all_antibiotics[$mabx->ID]->{$notekey} = $this->conditional_abxs[$mabx->ID]->note;
                    }
                }


                if ($this->debug) {

                    $this->log .= 'conditional: ' . $mabx->abx . ' by ' . $this->selectedBacterias[$mabx->bacteria_ID]->name . '<br>';
                }
            }

            if (count($abxs_not_exists) > 0) {

                $abxids_str = implode(',', $abxs_not_exists);
                $where = " ID in ({$abxids_str}) ";

                $anti_rs        = $wpdb->get_results("select * 
                                from ci_abx_antibiotics ant
                                where {$where}
                                order by ant.position asc");

                if (!empty($wpdb->last_error)) {
                    $this->count_errors++;
                    $this->errors[$wpdb->last_query] = $wpdb->last_error;
                }

                foreach ($anti_rs as $anti) {
                    $dosekey = 'dose_' . $this->subtype;
                    $notekey = 'notes_' . $this->subtype;
                    if (!empty($this->conditional_abxs[$anti->ID]->dose)) {
                        $anti->{$dosekey} = $this->conditional_abxs[$anti->ID]->dose;
                    }
                    if (!empty($this->conditional_abxs[$anti->ID]->note)) {
                        $anti->{$notekey} = $this->conditional_abxs[$anti->ID]->note;
                    }
                    $this->all_antibiotics[$anti->ID] = $anti;

                    if ($this->debug) {

                        $this->log .= 'conditional: ' . $anti->abx . ' =>dose ' . $anti->{$dosekey} . '<br>';
                    }
                }
            }
        }
    }

    public function buildscores()
    {
        global $wpdb;

        if ($this->debug) {
            echo 'Type: ' . $this->subtype . '<br>';
            echo 'Show Column Orphan: ' . ($this->ShowColOrphan ? 'yes' : 'no') . '<br>';
            echo 'Prioritize Oral: ' . ($this->PrioritizeOral ? 'yes' : 'no') . '<br>';
            echo 'Prioritize Activity: ' . ($this->PrioritizeActivity ? 'yes' : 'no') . '<br>';
            echo 'Oral before Activity: ' . ($this->PrioritizeOrialB4Activity ? 'yes' : 'no') . '<br>';
            echo 'Exclude Risk 0: ' . ($this->alway_exclude_Risk0 ? 'yes' : 'no') . '<br>';
            echo 'Quantitative: ' . ($this->Quantitative ? 'yes' : 'no') . '<br>';
            echo 'virus_detected: ' . (count($this->virus_detected_list) > 0 ? 'yes' : 'no') . '<br>';
            echo 'Quantitative5Levels: ' . ($this->Quantitative5Levels ? 'yes' : 'no') . '<br>';
        }

        $allbatids = array();
        $allmakids = array();
        foreach ($this->selectedBacterias as $bid => $bat) {
            $allbatids[] = $bat->ID;
        }

        foreach ($this->selectedMarkers as $mk) {
            $allmakids[] = $mk->ID;
        }

        $allbatids_str = implode("','", $allbatids);
        $allmakids_str = implode("','", $allmakids);

        $rs = $wpdb->get_results("select * 
        from {$this->abx_tbl} 
        where bacteria_ID in ('{$allbatids_str}')
        and marker_ID in ('-1','{$allmakids_str}') ");


        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        if (count($rs) == 0) {
            update_option('ci_abx_last_change', date('YmdHis'));
        }


        $this->check_exclude($this->percent_quantitative_new);

        //check Bacteria Risk5 exist
        if ($this->Biofilm || $this->percent_quantitative || $this->percent_quantitative_new) {
            foreach ($rs as $r) {
                if ($r->marker_ID == -1) {
                    $r->marker_ID = 'nr';
                }
                $bat    = $this->selectedBacterias[$r->bacteria_ID];
                $marker = isset($this->selectedMarkers[$r->marker_ID]) ? $this->selectedMarkers[$r->marker_ID] : null;

                if ($marker && $marker->risk >= 5) {
                    $bat->no_RMrisk5 = false;
                }
            }
        }
        //end check Bacteria Risk5 exist

        if ($this->Biofilm) {
            $this->check_biofilm();
            $this->check_exclude_biofilm();
        }

        if ($this->percent_quantitative_new) {
            $this->check_exlude_Percent_Quantitative_New();
        } else if ($this->percent_quantitative) {
            $this->check_exlude_Percent_Quantitative();
        }


        //dont exclude when all excluded
        if ($this->count_bac_available == 0) {
            $this->count_bac_available = count($this->selectedBacterias);
            foreach ($this->selectedBacterias as $bid => $r) {
                $this->selectedBacterias[$bid]->isexclude = false;
            }
            if ($this->debug) {
                $this->log .= '<br>need match before risk0<br>';
            }
        }
        //check conditional -must after exclude
        $this->check_Conditional();



        //parse abx
        foreach ($rs as $r) {

            if ($r->marker_ID == -1) {
                $r->marker_ID = 'nr';
            }
            $bat    = $this->selectedBacterias[$r->bacteria_ID];
            $marker = isset($this->selectedMarkers[$r->marker_ID]) ? $this->selectedMarkers[$r->marker_ID] : null;
            $marker_class_ID = $marker ? $marker->class_ID : 0;
            $marker_ID = $marker ? $marker->ID : 0;

            if ($marker) {
                $mkname = (!empty($marker->group_name)) ? $marker->group_name : $marker->name;
                $bat->markers[$mkname] = $mkname;
            }


            $r->antibiotics = json_decode($r->antibiotics, true);
            if (!is_array($r->antibiotics)) {
                $r->antibiotics  = array();
            } else if ($this->subtype == 'cuti') {
                foreach ($r->antibiotics as $antiID => $sct) {
                    if (!$this->all_antibiotics[$antiID]->cuti) {
                        unset($r->antibiotics[$antiID]);
                    }
                }
            }

            if (count($this->conditional_abxs) > 0) {
                foreach ($this->conditional_abxs as $a) {
                    if (!isset($r->antibiotics[$a->ID])) {
                        $antid      = $a->ID;
                        $bacan_key  = $bat->ID . '_' . $antid;
                        $maan_key   = ($marker_ID > 0 ? $marker_ID : 'nr') . '_' . $antid;
                        $maan_group_key = $marker_ID . '_g_' . $antid;

                        $antibiogram    = isset($this->antibiograms[$bacan_key]) ? $this->antibiograms[$bacan_key] : null;
                        $rmbiogram      = isset($this->rmbiograms[$maan_key]) ? $this->rmbiograms[$maan_key] : null;
                        if (isset($this->rmbiograms[$maan_group_key]) && !in_array($bat->ID, $this->rmbiograms[$maan_group_key]->exclude_Bacterias)) {
                            $rmbiogram = $this->rmbiograms[$maan_group_key];
                        }

                        $cAB    = $antibiogram ? $antibiogram->ab_score : 0;
                        $F      = $antibiogram ? $antibiogram->f : 0;
                        $cFS    = $antibiogram ? $antibiogram->fs_score : 0;

                        $cRM    = $rmbiogram ? $rmbiogram->rm_score : 0;

                        $cTS    = isset($this->ts_scores[$bat->ID . '_' . $marker_ID . '_' . $antid]) ? $this->ts_scores[$bat->ID . '_' . $marker_ID . '_' . $antid] : 0;

                        $r->antibiotics[$antid] = array('AB' => $cAB, 'RM' => $cRM, 'F' => $F, 'FS' => $cFS, 'TS' => $cTS);
                    }
                }
            }

            foreach ($r->antibiotics as $antiID => $sct) {

                $r->antibiotics[$antiID]['LS'] = $this->getLS($r->bacteria_ID, $antiID, $sct['AB'], $marker_class_ID, $marker_ID);
                $r->antibiotics[$antiID]['R'] = $this->abx_R($antiID);
                $r->antibiotics[$antiID]['C'] = $this->abx_C($antiID);
                $r->antibiotics[$antiID]['S'] = $this->abx_S($antiID);
                $r->antibiotics[$antiID]['A'] = $this->abx_A($antiID);
                $r->antibiotics[$antiID]['LT'] = $this->abx_LT($r->bacteria_ID, $r->marker_ID, $antiID);
            }


            if (!isset($this->scores[$r->bacteria_ID])) {

                $this->scores[$r->bacteria_ID] = array(
                    'markers'   => array(),
                    'abxs'      => array(),
                    'brisk'     => $bat->risk,
                    'gram_type' => $bat->gram_type,
                    'mrisk'     => $marker ? $marker->risk : 0,
                    'mmaxrisk'  => $marker ? $marker->max_risk : 0,
                    'nr'        => array(),
                    'common'    => array()
                );
            }


            if ($marker) {
                if ($marker->risk > $this->scores[$r->bacteria_ID]['mrisk']) {
                    $this->scores[$r->bacteria_ID]['mrisk'] = $marker->risk;
                }

                if ($marker->max_risk > $this->scores[$r->bacteria_ID]['mmaxrisk']) {
                    $this->scores[$r->bacteria_ID]['mmaxrisk'] = $marker->max_risk;
                }
            }

            if ($r->marker_ID == 'nr') {
                $this->scores[$r->bacteria_ID]['nr'] = $r->antibiotics;
            } else {
                if ($marker && !empty($marker->class)) {
                    if (!isset($this->group_markers[$marker->class])) {
                        $this->group_markers[$marker->class] = array(
                            'by' => $r->marker_ID,
                            'markers' => array(),
                        );
                        $this->group_markers[$marker->class]['markers'][$r->marker_ID] = $r->marker_ID;
                    } else {
                        $this->group_markers[$marker->class]['markers'][$r->marker_ID] = $r->marker_ID;
                        //continue;
                    }
                }


                $this->scores[$r->bacteria_ID]['markers'][$r->marker_ID] = $r->antibiotics;


                foreach ($r->antibiotics as $antiID => $sct) {
                    if (!isset($this->scores[$r->bacteria_ID]['abxs'][$antiID])) {
                        $this->scores[$r->bacteria_ID]['abxs'][$antiID] = 0;
                    }
                    $this->scores[$r->bacteria_ID]['abxs'][$antiID]++;
                }
            }
        }


        $this->commbacids = array();
        $max_abx_risk = 0;
        $removeantigroup = array();
        foreach ($this->scores as $batid => $bat_arr) {

            if (count($bat_arr['abxs']) == 0) {
                $bat_arr['abxs'] = is_array($bat_arr['nr']) ? $bat_arr['nr'] : array();
            }

            foreach ($bat_arr['abxs'] as $antiID => $countnmk) {

                $is_NR = is_array($countnmk);
                if (count($bat_arr['markers']) > 0 && !$is_NR && $countnmk < count($bat_arr['markers'])) continue;


                $F = 0;
                $A = $this->abx_A($antiID);
                $R = $this->abx_R($antiID);
                $C = $this->abx_C($antiID);
                $S = $this->abx_S($antiID);

                $NTR        = $this->getNTR($batid, $antiID);
                $iconcode   = $this->abx_Icon($antiID);
                $abx_risk   = $this->abx_Risk($antiID);

                $LT = 0;
                $LS = -1;
                $RM = 0;
                $AB = 0;

                $anti_group_name = '';

                if (count($bat_arr['markers']) > 0) {
                    foreach ($bat_arr['markers'] as $mkid => $arr) {
                        $scorearr   = $arr[$antiID];
                        $F          = $scorearr['F'];

                        if ($scorearr['LS'] !== '0' && $scorearr['LS'] !== 0 && floatval($scorearr['LS']) == 0) {
                            $LS = floatval($scorearr['LS']);
                        } else if (($scorearr['LS'] < $LS || $LS == -1) && $scorearr['LS'] !== '' && !is_null($scorearr['LS'])) {
                            $LS = floatval($scorearr['LS']);
                        }

                        if ($scorearr['RM'] == -1) {
                            $RM = -1;
                        }
                        $RM          = $RM < 0 || $RM == -1 ? $RM : $scorearr['RM'];

                        $AB          = $scorearr['AB'];
                        if ($scorearr['LT'] > $LT || $LT == 0) {
                            $LT          = $scorearr['LT'];
                        }

                        $this->selectedMarkers[$mkid]->count_anti++;

                        //check antigroups
                        foreach ($this->antibiogroups as $agid => $ag) {
                            if (in_array($batid, $ag->exclude_Bacterias)) {
                                if ($this->debug) {
                                    //echo 'exclude: ' . $batid;
                                }
                                continue;
                            }
                            if (($ag->antiID1 == $antiID || $ag->antiID2 == $antiID) && in_array($mkid, $ag->markers)) {
                                $anti_group_name = $this->abx_name($ag->antiID1) . ' + ' . $this->abx_name($ag->antiID2);
                                $aid = $ag->antiID1 == $antiID ? $ag->antiID1 : $ag->antiID2;
                                $this->scores[$batid]['markers'][$mkid][$aid]['name'] = $anti_group_name;
                                unset($this->scores[$batid]['markers'][$mkid][$ag->antiID2]);
                                $removeantigroup[$ag->antiID1 . '-' . $ag->antiID2 . '-' . $agid] = $anti_group_name;
                                break;
                            }
                        }
                    }

                    if (isset($bat_arr['nr'][$antiID]['LS']) && $LS > $bat_arr['nr'][$antiID]['LS'] && $bat_arr['nr'][$antiID]['LS'] > 0) {
                        $LS = floatval($bat_arr['nr'][$antiID]['LS']);
                    }
                } else if ($is_NR && is_array($countnmk)) {

                    //NR
                    $F           = $countnmk['F'];
                    $LS          = floatval($countnmk['LS']);
                    $RM          = $countnmk['RM'];
                    $AB          = $countnmk['AB'];
                    $LT          = $countnmk['LT'];
                } else {
                    continue;
                }


                $noLS = false;
                if ($LS == -1 || (floatval($LS) == 0 && $LS !== '0' && $LS !== 0)) {
                    if ($this->debug) {
                        $this->log .= 'NO LS for ' . $this->abx_name($antiID);
                        $this->log .= '<br>';
                    }
                    $noLS = true;
                }

                if ($bat_arr['gram_type'] == 16 || $bat_arr['gram_type'] == 17) {
                    $noLS = false;
                } else {
                    // if($AB==0 && count($allbatids)==1)continue;
                }

                if ($AB == 0 && $this->abx_class_ID($antiID) == 75) { //Anti Protozoa
                    if ($this->debug) {
                        $this->log .= 'Hide ' . $this->abx_name($antiID) . ' by Anti Protozoa';
                        $this->log .= '<br>';
                    }
                    continue;
                } else if ($this->subtype == 'ei' && $this->abx_class_ID($antiID) == 71) { //Antifungals
                    if ($this->debug) {
                        $this->log .= 'Hide ' . $this->abx_name($antiID) . ' by Antifungals';
                        $this->log .= '<br>';
                    }
                    continue;
                }

                $ES = $noLS && 100 + $LS != 0 ? 0 : ((100 - $LS) * $NTR / 100 + $LS);

                if ($RM <= 0 && !$is_NR) {
                    $ES = 0;
                }


                $RS = $F + $A + $R + $C + $S;

                $abx_score = (0.8 * $ES) + (20 - $RS) + $LT;

                //added 4 jul 2025
                if ($this->Quantitative) {
                    $load_percent = $this->selectedBacterias[$batid]->load_percent;
                    if ($this->selectedBacterias[$batid]->lmh === 'D') {
                        $load_percent = 100;
                    }

                    if ($load_percent < 0.01 && $load_percent > 0) {
                        $load_percent = 0.01; //to show in result but with low score
                    }
                    $abx_score = round($abx_score * $load_percent / 100, 2);
                }

                $abx_icon = 'minus_grey';


                //antibiotic group
                if (!empty($anti_group_name)) {
                    $ES = 90;
                    $abx_score = 90;
                    $LT = 3;
                }

                //$RM =-1 : -/- , $RM=-2: -
                if ($RM == -1) {
                    $abx_icon = 'dna_red';
                } else if ($ES < $this->passES_Score) {
                    $abx_icon = 'minus_grey';
                    if (count($bat_arr['markers']) == 0 && !$is_NR) {
                        if (isset($_GET['debugerror'])) {
                            echo 'nno matkers && !NR' . "\n<br>";
                        }
                        continue;
                    }
                } else if ($ES >= 90 && ($LT == 2 || $LT == 3)) {
                    $abx_icon = 'checkmark_dark_green';
                } else if ($ES >= 90) {
                    $abx_icon = 'plus_dark_green';
                } else if ($ES >= 80 && ($LT == 2 || $LT == 3)) {
                    $abx_icon = 'checkmark_light_green';
                } else if ($ES >= 80) {
                    $abx_icon = 'plus_light_green';
                } else if ($ES >= $this->passES_Score) {
                    $abx_icon = 'dna_orange';
                }

                //conditional

                if ($ES >= 80 && isset($this->conditional_abxs[$antiID]) && $this->conditional_abxs[$antiID]->bacteria_ID == $batid) {

                    $abx_icon = 'checkmark_dark_green';
                }



                $scorearr = array(
                    'ES'   => $ES,
                    'RS'   => $RS,
                    'RM'   => $RM,
                    'LS'   => $LS,
                    'NTR'   => $NTR,
                    'F'   =>  $F,
                    'A'    => $A,
                    'R'   => $R,
                    'C'    => $C,
                    'S'    => $S,
                    'AB'    => $AB,
                    'LT'    => $LT,
                    'icon'  => $abx_icon,
                    'abx_score' => $abx_score,
                );

                if ($RM > 0) {
                    if (!empty($anti_group_name)) {
                        $scorearr['name'] = $anti_group_name;
                    }
                    $this->scores[$batid]['common'][$antiID] = $scorearr;
                } else if (!empty($anti_group_name)) {
                    if (isset($_GET['debugerror'])) {
                        echo 'no abx name' . "\n<br>";
                    }
                    continue;
                }


                $this->commbacids[$batid] = $batid;

                if (!isset($this->result_abx[$antiID])) {
                    if ($abx_risk > $max_abx_risk) {
                        $max_abx_risk = $abx_risk;
                    }

                    $anti = $this->all_antibiotics[$antiID];
                    $abx_name = $anti->abx;
                    if (empty($abx_name)) {
                        if (isset($_GET['debugerror'])) {
                            echo 'no abx name' . "\n<br>";
                        }
                        continue;
                    }

                    if (!empty($this->patient_renal)) {

                        switch ($this->patient_renal) {
                            case 'normal':
                                $dosekey = 'renal_crcla30_' . $this->subtype;
                                break;
                            case 'moderate':
                                $dosekey = 'renal_crclb30_' . $this->subtype;
                                break;
                            case 'severe':
                                $dosekey = 'renal_crclb10_' . $this->subtype;
                                break;
                            case 'dialysis':
                                $dosekey = 'dialysis_' . $this->subtype;
                                break;
                        }
                    } else if ($this->Pediatric_ranges && $this->age < 18) {
                        $dosekey = '';
                        if ($this->age <= 1) {
                            $dosekey = 'dose_1y_' . $this->subtype;
                        } else if ($this->age < 12) {
                            $dosekey = 'dose_2y_' . $this->subtype;
                        } else {
                            $dosekey = 'dose_12y_' . $this->subtype;
                        }
                    } else {
                        $dosekey = 'dose_' . $this->subtype;
                    }

                    //changed 2025/8/3 from just Pediatric to all
                    if (isset($anti->{$dosekey}) && $anti->{$dosekey} === 'NA') {
                        if (isset($_GET['debugerror'])) {
                            echo 'no ' . $abx_name . ' by NA in dose' . "\n<br>";
                        }
                        continue;
                    }



                    $this->result_abx[$antiID] = array(
                        'name'      => $abx_name,
                        'abrev'     => $this->abx_abrev($antiID),
                        'scores'    => array(),
                        'abx_score' => 0,
                        'count_match' => 0,
                        'count_checkmark' => 0,
                        'iconcode'  => $iconcode,
                        'abx_risk'  => $abx_risk,
                        'risk_color' => 'grey',
                        'exclude'   => ($RM < 0 || $ES < $this->passES_Score) && !$is_NR ? true : false,
                        'numpairs'  => array(),
                        'maxscore'  => array(),
                        'S'         => $S,
                        'diamond'   => $S == 5,
                        'C'    => $C,
                        'A'    => $A,
                        'BA'    => $this->abx_BA($antiID),
                    );

                    if ($this->show_pregnancy) {
                        $this->result_abx[$antiID]['trimester_1'] = $anti->trimester_1;
                        $this->result_abx[$antiID]['trimester_2'] = $anti->trimester_2;
                        $this->result_abx[$antiID]['trimester_3'] = $anti->trimester_3;
                        $this->result_abx[$antiID]['trimester_l'] = $anti->trimester_l;
                        $this->result_abx[$antiID]['pregnancy_note'] = $anti->pregnancy_note;
                    }
                }
                $this->result_abx[$antiID]['scores'][$batid] = $scorearr;
            }


            if (is_array($this->scores[$batid]['common']) && count($this->scores[$batid]['common']) > 0) {
                if ($this->debug) {
                    $this->logpair .= '<br>sort common Bacteria#' . $batid . ' abx score<br>';
                }
                uasort($this->scores[$batid]['common'], array($this, 'sort_abx'));
            }
        }


        //check bacteria group
        if (count($this->bacteria_hide_ids) > 0) {
            foreach ($this->bacteria_hide_ids as $sbid => $mbid) {
                foreach ($this->result_abx as $antiID => $arrs) {
                    $arrmain = isset($arrs['scores'][$mbid]) ? $arrs['scores'][$mbid] : array();
                    $arrhide = isset($arrs['scores'][$sbid]) ? $arrs['scores'][$sbid] : array();

                    if (!empty($arrmain) && !empty($arrhide) && $arrmain['ES'] > $arrhide['ES']) {
                        $this->result_abx[$antiID]['scores'][$mbid] = $arrhide;
                    }
                }
            }
        }


        //cal abx
        foreach ($this->result_abx as $antiID => $arrs) {
            $tc_abx_score   = array();

            if (count($this->exclude_antibiotic_IDs) > 0) {

                if (in_array($antiID, $this->exclude_antibiotic_IDs)) {
                    unset($this->result_abx[$antiID]);
                    if ($this->debug) {
                        $abxname = $this->abx_name($antiID);
                        $this->log .= 'Exclude antibiotic by name: ' . $abxname;
                        $this->log .= '<br>';
                    }
                    continue;
                }
            }

            foreach ($this->commbacids as $batid) {

                if ($this->selectedBacterias[$batid]->isexclude) {
                    continue;
                }
                $arr = isset($arrs['scores'][$batid]) ? $arrs['scores'][$batid] : array();



                $tc_abx_score[] = $arr['abx_score'];
                $notpassES =  $arr['ES'] < $this->passES_Score;
                if ($arr['abx_score'] == 0 || $arr['RM'] < 0 || $notpassES) {
                    $this->result_abx[$antiID]['exclude'] = true;
                } else {

                    $this->result_abx[$antiID]['count_match']++;
                    if (in_array($arr['icon'], array('checkmark_dark_green', 'checkmark_light_green'))) {
                        $this->result_abx[$antiID]['count_checkmark']++;
                    }
                }

                if ($arr['ES'] >= 80) {
                    $this->result_abx[$antiID]['hasES80'] = true;
                }
            }

            //change 4 jul 2025,to sum for Quantitative
            if ($this->Quantitative && $this->Quantitative_Sum_score) {
                $abx_score = array_sum($tc_abx_score);
            } else {
                $abx_score = count($tc_abx_score) > 0 ? array_sum($tc_abx_score) / count($tc_abx_score) : 0;
            }
            $abx_score = round($abx_score, 2);

            $this->result_abx[$antiID]['abx_score'] = $abx_score;




            $risk_color = 'grey';
            if ($arrs['abx_risk'] == $max_abx_risk) {
                $risk_color = 'red';
            } else if ($arrs['abx_risk'] == $max_abx_risk - 1) {
                $risk_color = 'orange';
            } else if ($arrs['abx_risk'] == $max_abx_risk - 2) {
                $risk_color = 'yellow';
            } else if ($arrs['abx_risk'] == $max_abx_risk - 3) {
                $risk_color = 'yellow_light';
            }
            $this->result_abx[$antiID]['risk_color'] = $risk_color;

            if ($this->result_abx[$antiID]['count_match'] > $this->max_count_match) {
                $this->max_count_match = $this->result_abx[$antiID]['count_match'];
            }

            if ($this->result_abx[$antiID]['count_match'] >= $this->count_bac_available) {
                $this->count_match_all++;
                $this->result_abx[$antiID]['exclude'] = false;

                if ($arrs['iconcode'] == 'pill') {
                    $this->hasMatchOral = true;
                }
            }
        }



        if ($this->debug) {
            $this->log .= '<br>sort abx score<br>';
        }
        uasort($this->result_abx, array($this, 'sort_abx'));

        if ($this->debug) {
            $this->log .= '<br>';
            $this->log .= 'countmatches:' . $this->count_match_all;
            $this->log .= '<br>';
            $this->log .= 'count bac: ' . $this->count_bac_available;
            $this->log .= '<br>';
            $this->log .= '<br>';
        }

        if ($this->count_match_all < 3 && $this->count_bac_available > 1) {
            if ($this->debug) {
                $this->log_exclude .= '<h3>Run exclude Bacteria when small quantities:</h3>';
            }
            $this->newcal_risk_lowhigh_matches();
            if ($this->debug) {
                if ($this->count_exclude_small > 0) {
                    $this->log_exclude .= ' - Excluded: ' . $this->count_exclude_small;
                } else {
                    $this->log_exclude .= ' - No Exclude Bacteria ';
                }
            }
        }


        if (count($removeantigroup) > 0) {
            foreach ($removeantigroup as $antids => $antiname) {
                list($anti1, $anti2, $agid) = explode('-', $antids);


                $removeantid = 0;
                $antipass = 0;
                if (isset($this->result_abx[$anti1]) && !isset($this->result_abx[$anti2])) {
                    $antipass = $anti1;
                    $removeantid = $anti2;
                } else if (isset($this->result_abx[$anti2]) && !isset($this->result_abx[$anti1])) {
                    $antipass = $anti2;
                    $removeantid = $anti1;
                } else if ($this->result_abx[$anti1]['exclude'] && !$this->result_abx[$anti2]['exclude']) {
                    $antipass = $anti2;
                    $removeantid = $anti1;
                } else if ($this->result_abx[$anti2]['exclude'] && !$this->result_abx[$anti1]['exclude']) {
                    $antipass = $anti1;
                    $removeantid = $anti2;
                } else if ($this->result_abx[$anti2]['ES'] > $this->result_abx[$anti1]['ES']) {
                    $antipass = $anti2;
                    $removeantid = $anti1;
                } else {
                    $antipass = $anti1;
                    $removeantid = $anti2;
                }


                if ($removeantid > 0) {
                    $this->result_abx[$antipass]['name'] = $antiname;
                    $this->result_abx[$antipass]['abrev'] = $antiname;
                    $this->result_abx[$antipass]['combination'] = true;
                    $this->result_abx[$antipass]['combinationid'] = $agid;
                    $this->result_abx[$antipass]['antid2'] = $removeantid;
                    unset($this->result_abx[$removeantid]);
                    foreach ($this->scores as $batid => $arr) {
                        unset($this->scores[$batid]['common'][$removeantid]);
                    }
                }
            }
        }


        $this->count_match_all_before_pair = $this->count_match_all;
        if ($this->count_match_all < 3 && $this->count_bac_available >= 2) {

            $this->run_pair();
        } else if ($this->PrioritizeOrialB4Activity) {
            $this->count_pill_match_all = 0;
            foreach ($this->result_abx as $antiid => $arr) {
                if ($arr['exclude'] === false && $arr['iconcode'] == 'pill') {

                    if ($this->pair_3_oral_90) {
                        //new one
                        $has_score_90 = false;
                        foreach ($arr['scores'] as $sco1) {
                            if ($sco1['abx_score'] > 90) {
                                $has_score_90 = true;
                            }
                        }
                        if ($has_score_90) {
                            $this->count_pill_match_all++;
                        }
                    } else {
                        $this->count_pill_match_all++;
                    }
                }
            }

            if ($this->debug) {
                $this->log .= '<br>Count Oral match all: ' . $this->count_pill_match_all . '<br>';
            }

            if ($this->count_pill_match_all < 3) {
                $this->run_pair(true);
            }
        }

        //sort bacteria

        $this->sort_bacterias();
    }


    function check_biofilm()
    {
        global $wpdb;

        //reset
        $this->Biofilm_core = 0;
        $this->Biofilm_Icon = 0;
        $this->Biofilm_Text = '';

        $bids = array();
        $bids_synergy_pair = array();
        foreach ($this->selectedBacterias as $bid => $bat) {
            if (!$bat->isexclude) {
                $bids[] = $bid;
            }
        }

        foreach ($this->biofilm_yeasts as $bid => $bat) {
            $bids[] = $bid;
        }

        $bids_str = implode(',', $bids);
        if (empty($bids_str)) {
            $bids_str = 0;
        }
        $sql = "select * from ci_abx_synergy_pairs where bac_ID in ({$bids_str})
        and bac_ID_2 in ({$bids_str})";
        $list = $wpdb->get_results($sql);
        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        if (count($list) > 0) {
            $this->Biofilm_core = 2; //>=2 to show SYNERGY PAIRS row
            foreach ($list as $row) {
                if (!isset($bids_synergy_pair[$row->bac_ID])) {
                    $bids_synergy_pair[$row->bac_ID] = array();
                }
                if (!isset($bids_synergy_pair[$row->bac_ID_2])) {
                    $bids_synergy_pair[$row->bac_ID_2] = array();
                }
                $bids_synergy_pair[$row->bac_ID][] = $row->bac_ID_2;
                $bids_synergy_pair[$row->bac_ID_2][] = $row->bac_ID;
            }
        }

        if ($this->debug) {
            $this->log .= 'Biofilm: ' . $this->Biofilm_core;
            $this->log .= '<br>';
        }

        //step2 
        $all_bros = array();
        //var_dump($this->selectedBacterias);
        foreach ($this->selectedBacterias as $bid => &$bat) {

            $bat->is_synergypair = false;
            $bat->synergypar_name = '';
            if (isset($bids_synergy_pair[$bid])) {
                $bat->is_synergypair = true;
                $synergypar_names = array();
                foreach ($bids_synergy_pair[$bid] as $bid2) {
                    $synergypar_names[] = '+ ' . (isset($this->selectedBacterias[$bid2]) ? $this->selectedBacterias[$bid2]->abrev : $this->biofilm_yeasts[$bid2]->abrev);
                }
                $bat->synergypar_name = implode('<br>', $synergypar_names);
            }

            if ($this->Quantitative && $bat->lmh !== 'D') {
                $bat->bro = $bat->br * min(0.5 + ($bat->load / $this->load_high) * 0.5, 1);
                if ($this->debug) {
                    $this->log .= $bat->name . " BRO = {$bat->br} x min(0.5 + ({$bat->load} / {$this->load_high})x0.5,1) = " . $bat->bro;
                    $this->log .= '<br>';
                }
            } else {
                $bat->bro = 0.75 * $bat->br;
                if ($this->debug) {
                    $this->log .= $bat->name . " BRO = 0.75 x {$bat->br} = " . $bat->bro;
                    $this->log .= '<br>';
                }
            }

            if (!$bat->isexclude) {
                $all_bros[] = $bat->bro;
            }
        }



        foreach ($this->biofilm_yeasts as $bid => &$bat2) {
            $bat2->bro = $bat2->br * min(0.5 + ($bat2->load / $this->load_high) * 0.5, 1);

            $bat2->is_synergypair = false;
            $bat2->synergypar_name = '';
            if (isset($bids_synergy_pair[$bid])) {
                $bat2->is_synergypair = true;
                $synergypar_names = array();
                foreach ($bids_synergy_pair[$bid] as $bid2) {
                    $synergypar_names[] = '+ ' . (isset($this->selectedBacterias[$bid2]) ? $this->selectedBacterias[$bid2]->abrev : $this->biofilm_yeasts[$bid2]->abrev);
                }
                $bat2->synergypar_name = implode('<br>', $synergypar_names);
            }
            if ($this->debug) {
                $this->log .= $bat2->name . " BRO = {$bat2->br} x min(0.5 + ({$bat2->load} / {$this->load_high})x0.5,1) = " . $bat2->bro;
                $this->log .= '<br>';
            }
            $all_bros[] = $bat2->bro;
        }



        if (count($all_bros) == 0) {
            return;
        }

        //step 3 BRI=
        if (count($all_bros) > 0) {
            $this->Biofilm_Risk_infection = max($all_bros) + $this->Biofilm_core;
        }



        $this->Biofilm_Icon = 1;
        $this->Biofilm_Text = 'LOW';
        if ($this->Biofilm_Risk_infection > 4 && $this->Biofilm_Risk_infection <= 6) {
            $this->Biofilm_Icon = 2;
            $this->Biofilm_Text = 'POTENTIAL';
        } else if ($this->Biofilm_Risk_infection > 6 && $this->Biofilm_Risk_infection <= 8) {
            $this->Biofilm_Icon = 3;
            $this->Biofilm_Text = 'SUBSTANTIAL';
        } else if ($this->Biofilm_Risk_infection > 8 && $this->Biofilm_Risk_infection <= 12) {
            $this->Biofilm_Icon = 4;
            $this->Biofilm_Text = 'ELEVATED';
        }
        if ($this->debug) {
            $this->log .=  " BRI = max(" . implode(',', $all_bros) . ") + {$this->Biofilm_core} = " . $this->Biofilm_Risk_infection;
            $this->log .= '<br>';
        }
    }


    function newcal_risk_lowhigh_matches()
    {
        //check risk
        $count_risk0 = 0;
        $count_risknot0 = 0;
        foreach ($this->selectedBacterias as $bat) {
            if ($this->needcheckrisk0) {
                if ($bat->is_risk0) { //|| $bat->lmh=='L' 
                    $count_risk0++;
                    if ($bat->lmh == 'L') {
                        $this->hasExcludeDetectedLow = true;
                    }
                } else {
                    $count_risknot0++;
                }
            } else if ((!$this->Quantitative && $bat->is_risk0) || ($this->Quantitative && $bat->is_risk0 && $bat->lmh == 'L')) {
                $count_risk0++;
            } else {
                $count_risknot0++;
            }
        }

        if (!$this->needcheckrisk0 && $this->Quantitative && $count_risk0 == 0) {
            $this->needcheckrisk0 = true;
            // $this->Quantitative = false;
            $this->newcal_risk_lowhigh_matches();
            return;
        }

        if ($count_risk0 > 0 && $count_risknot0 > 0) {

            $new_scores = array();

            foreach ($this->result_abx as $antiID => $arrs) {

                if ($arrs['count_match'] >= $this->count_bac_available || $this->result_abx[$antiID]['exclude'] == false) {
                    continue;
                }

                $sum_abxscore = 0;
                $pass = true;
                foreach ($this->selectedBacterias as $bid => $bat) {


                    if ($this->needcheckrisk0) {
                        if ($bat->is_risk0) { //|| $bat->lmh=='L'
                            $this->selectedBacterias[$bid]->isexclude = true;
                            if ($this->debug) {
                                $this->log_exclude .= '-Exclude bacteria: ' . $bat->name . ' by risk0<br>';
                            }
                            if ($bat->lmh == 'L') {
                                $this->hasExcludeDetectedLow = true;
                            }
                            continue;
                        }
                    } else if ((!$this->Quantitative && $bat->is_risk0) || ($this->Quantitative && $bat->is_risk0 && $bat->lmh == 'L')) {
                        $this->selectedBacterias[$bid]->isexclude = true;
                        if ($this->debug) {
                            $this->log_exclude .= '-Exclude bacteria: ' . $bat->name . ' by risk0<br>';
                        }
                        if ($bat->lmh == 'L') {
                            $this->hasExcludeDetectedLow = true;
                        }
                        continue;
                    }

                    $arr = isset($arrs['scores'][$bat->ID]) ? $arrs['scores'][$bat->ID] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0);
                    if ($arr['abx_score'] == 0 || $arr['RM'] < 0 || $arr['ES'] < 70) {
                        $pass = false;
                    } else {
                        $sum_abxscore += $arr['abx_score'];
                    }
                }
                if ($pass) {
                    $new_scores[$antiID] = $sum_abxscore;
                }
            }

            foreach ($new_scores as $antiID => $sumsc) {
                $this->result_abx[$antiID]['exclude'] = false;
                if ($this->result_abx[$antiID]['iconcode'] == 'pill') {
                    $this->hasMatchOral = true;
                }
                $this->count_match_all++;
                if ($this->count_match_all >= 3) {
                    // break;
                }
            }
        } else {
            if ($this->debug) {
                $this->log_exclude .= '- No risk0 bacteria<br>';
            }
        }

        if (!$this->needcheckrisk0 && $this->Quantitative && $this->count_match_all < 3) {
            $this->needcheckrisk0 = true;
            $this->newcal_risk_lowhigh_matches();
            return;
        }

        if ($this->count_match_all >= 3) {
            if ($this->debug) {
                //$this->log .= '<br>sort abx score<br>';
            }
            uasort($this->result_abx, array($this, 'sort_abx'));
        }
    }

    function get_filter($antiIDs)
    {
        global $wpdb;
        $fitler = array();
        if (count($antiIDs) > 0) {

            $classes = [];
            $si_antiIds = [];

            foreach ($this->all_antibiotics as $aid => $anti) {
                $cid = $anti->class_ID;
                if (!isset($classes[$cid])) {
                    $classes[$cid] = array();
                }
                $classes[$cid][] = $aid;

                foreach ($classes[$cid] as $id) {
                    $si_antiIds[$id] = $classes[$cid];
                }
            }

            $rs = $wpdb->get_results("select antiID1, antiID2 from ci_abx_filter_antibios");

            if (!empty($wpdb->last_error)) {
                $this->count_errors++;
                $this->errors[$wpdb->last_query] = $wpdb->last_error;
            }

            foreach ($rs as $r) {
                $fitler[$r->antiID1 . '-' . $r->antiID2] = 1;

                if (isset($si_antiIds[$r->antiID1])) {
                    foreach ($si_antiIds[$r->antiID1] as $siaid1) {
                        if ($siaid1 != $r->antiID1) {
                            $fitler[$siaid1 . '-' . $r->antiID2] = 1;
                        }
                    }
                }
                if (isset($si_antiIds[$r->antiID2])) {
                    foreach ($si_antiIds[$r->antiID2] as $siaid2) {
                        if ($siaid2 != $r->antiID2) {
                            $fitler[$r->antiID1 . '-' . $siaid2] = 1;
                        }
                    }
                }
            }
        }

        return $fitler;
    }

    public function run_pair($onlypill = false)
    {
        if ($this->debug) {
            $this->logpair .= '<h2>Debug Pair</h2>';
            $this->logpair .= '<br>';
            $this->logpair .= 'countmatches:' . $this->count_match_all;
            $this->logpair .= '<br>';
        }
        $listscores = array();
        $keys       = array();
        $keys_filter = array();
        $batids     = array();

        if ($this->debug) {
            foreach ($this->selectedBacterias as $bat) {
                if ($bat->isexclude || $bat->exclude_pair) {
                    $this->logpair .=  'Exclude bacteria: ' . $bat->name . '<br>';
                }
            }
        }

        foreach ($this->result_abx as $antiID => $arr2) {


            if ($arr2['diamond']) {
                $this->result_abx[$antiID]['abx_score'] -= 100;
                if ($this->debug) {
                    // $this->logpair .= 'Pair2 Diamond: ' . $arr2['name'] . '<br>';;
                }
            }

            if ($arr2['exclude'] == false) {

                $this->result_abx[$antiID]['sum_score'] = $this->result_abx[$antiID]['abx_score'];
                $this->result_abx[$antiID]['match_all'] = true;

                if ($this->debug) {
                    $this->logpair .= 'exclude pair : ' . $arr2['name'] . ' (match all)<br>';
                }
                continue;
            }


            if ($onlypill && $arr2['iconcode'] != 'pill') {
                continue;
            }




            $listscores[$antiID] = array();
            $pass_run_pair = false;

            foreach ($this->selectedBacterias as $bat) {
                if ($bat->isexclude || $bat->exclude_pair) {
                    continue;
                }

                $arr = isset($this->result_abx[$antiID]['scores'][$bat->ID]) ? $this->result_abx[$antiID]['scores'][$bat->ID] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0);
                $abxsc = isset($arr['abx_score']) ? $arr['abx_score'] : 0;

                if (!isset($arr['RM']) || $arr['RM'] < 0) {
                    //$pass_run_pair = false;
                    // break;
                }

                if ($arr['ES'] >= $this->passES_Score) {
                    $pass_run_pair = true;
                } else {
                    $abxsc = 0;
                    if ($this->debug) {
                        //$this->logpair .= 'Not Pass ES<60 : ' . $arr2['name'] . '<br>';;
                    }
                }

                if ($arr['RM'] < 0) {
                    $abxsc = 0;
                    if ($this->debug) {
                        // $this->logpair .= 'RM <0 : ' . $arr2['name'] . '<br>';;
                    }
                }

                //check diamond 
                if ($arr2['diamond'] && stripos($arr['icon'], 'checkmark') === false) {
                    $abxsc -= 50;
                    if ($this->debug) {
                        // $this->logpair .= 'Pair Diamond: ' . $arr2['name'] . '<br>';;
                    }
                }

                $listscores[$antiID][] = $abxsc;

                $batids[$bat->ID] = $bat->ID;
            }



            if ($pass_run_pair) {
                $keys[] = $antiID;
                $keys_filter[$antiID] = isset($this->result_abx[$antiID]['combination']) && $this->result_abx[$antiID]['combination'] ? 0 - $this->result_abx[$antiID]['combinationid'] : $antiID;
            } else {
                unset($listscores[$antiID]);
                if ($this->debug) {
                    $this->logpair .= 'exclude pair : ' . $arr2['name'] . ' by ES= ' . $arr['ES'] . ' < ' . $this->passES_Score . '<br>';;
                }
            }
        }

        if (count($batids) < 2) {
            if ($this->debug) {
                $this->logpair .= '<br>not run pair by bacteria number<br>';
            }
            return;
        }

        if ($this->debug) {
            $this->logpair .= 'keys: ' . count($keys) . '<br>';
        }


        $countk     = count($keys);


        //get filter

        $filter = $this->get_filter($keys_filter);


        $listSum = array();
        $hasmap = array();
        $matchbaticons = array('checkmark_dark_green', 'checkmark_light_green', 'plus_dark_green', 'plus_light_green', 'dna_orange');
        $matchcheckmarkicons = array('checkmark_dark_green', 'checkmark_light_green');
        for ($i = 0; $i < $countk - 1; $i++) {
            $antiID1 = $keys[$i];
            for ($j = 0; $j < $countk; $j++) {
                $antiID2 = $keys[$j];

                if (in_array($antiID1 . '-' . $antiID2, $hasmap) || $antiID1 == $antiID2) {
                    continue;
                }

                $afid1 = $keys_filter[$antiID1] < 0 ? $keys_filter[$antiID1] : $antiID1;
                $afid2 = $keys_filter[$antiID2] < 0 ? $keys_filter[$antiID2] : $antiID2;

                if (!isset($filter[$afid1 . '-' . $afid2]) && !isset($filter[$afid2 . '-' . $afid1])) {
                    if ($this->debug) {
                        $this->logpair .= '-Exclude by filter: ' . $this->all_antibiotics[$afid1]->abx . ' (#' . $afid1 . ') + ' . $this->all_antibiotics[$afid2]->abx . '(#' . $afid2 . ') <br>';
                    }
                    $hasmap[]  = $antiID2 . '-' . $antiID1;
                    continue;
                }

                $sum = 0;
                $sum_arr = array();
                for ($col = 0; $col < count($listscores[$antiID1]); $col++) {
                    $maxvl = max($listscores[$antiID1][$col], $listscores[$antiID2][$col]);
                    $sum += $maxvl;
                    $sum_arr[] = $maxvl;
                }
                $csum = $sum;
                //check all oral & ES>=60
                $oraltextlog = '';
                if ($this->PrioritizeOral) {
                    $adding_score = $this->PrioritizeOrialB4Activity ? 40 : 10;

                    if ($this->result_abx[$antiID1]['iconcode'] == 'pill' && $this->result_abx[$antiID2]['iconcode'] == 'pill') {
                        $passES60 = true;
                        foreach ($batids as $batid) {
                            $arr1 = isset($this->result_abx[$antiID1]['scores'][$batid]) ? $this->result_abx[$antiID1]['scores'][$batid] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0);
                            $arr2 = isset($this->result_abx[$antiID2]['scores'][$batid]) ? $this->result_abx[$antiID2]['scores'][$batid] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0);

                            if ($arr1['ES'] < 60 && $arr2['ES'] < 60) {
                                $passES60 = false;
                                break;
                            }
                        }


                        if ($passES60) {
                            $sum += count($batids) * $adding_score;
                            $oraltextlog = 'Oral';
                        } else if ($onlypill) {
                            continue;
                        }
                    }
                } else if ($onlypill) {
                    continue;
                }
                //end check all oral & ES>=60

                //add checkmark to top
                $countcheckmarks = 0;
                $count_match_bac = 0;

                foreach ($batids as $batid) {
                    $arr1 = isset($this->result_abx[$antiID1]['scores'][$batid]) ? $this->result_abx[$antiID1]['scores'][$batid] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0, 'icon' => '');
                    $arr2 = isset($this->result_abx[$antiID2]['scores'][$batid]) ? $this->result_abx[$antiID2]['scores'][$batid] : array('abx_score' => 0, 'RM' => 0, 'ES' => 0, 'icon' => '');

                    if (in_array($arr1['icon'], $matchcheckmarkicons) || in_array($arr2['icon'], $matchcheckmarkicons)) {
                        $countcheckmarks++;
                    }

                    if (in_array($arr1['icon'], $matchbaticons) || in_array($arr2['icon'], $matchbaticons)) {
                        $count_match_bac++;
                    }
                }

                if ($this->PrioritizeActivity) {
                    if ($countcheckmarks > 0) {
                        $sum += $countcheckmarks * ($this->PrioritizeOrialB4Activity ? 10 : 20);
                    }

                    if ($this->PrioritizeOrialB4Activity && $count_match_bac >= count($batids) && $this->result_abx[$antiID1]['iconcode'] == 'pill' && $this->result_abx[$antiID2]['iconcode'] == 'pill') {
                        $sum += 10000; //move up to top
                    }
                }




                $listSum[] = array($antiID1, $antiID2, $sum, $csum, $countcheckmarks, $count_match_bac,  $oraltextlog, $sum_arr);
                $hasmap[]  = $antiID2 . '-' . $antiID1;


                if (!isset($this->result_abx[$antiID1]['sum_score'])) {
                    $this->result_abx[$antiID1]['sum_score'] = $sum;
                }
                if (!isset($this->result_abx[$antiID2]['sum_score'])) {
                    $this->result_abx[$antiID2]['sum_score'] = $sum;
                }


                if ($this->result_abx[$antiID1]['sum_score'] < $sum) {
                    $this->result_abx[$antiID1]['sum_score'] = $sum;
                }

                if ($this->result_abx[$antiID2]['sum_score'] < $sum) {
                    $this->result_abx[$antiID2]['sum_score'] = $sum;
                }
            }
        }

        if ($this->debug) {
            $this->logpair .= '<br>sort sum pair<br>';
        }
        uasort($listSum, array($this, 'sort_abx_sum_pair'));

        if ($this->debug) {

            if (count($listSum) == 0) {
                $this->logpair .= '<br>No results for Pair<br>';
            } else {
                $this->logpair .= '<div style="overflow:auto;max-height:200px"><table class="reportabx_tbl reportabx_tbl_debug tblborder">';
                foreach ($listSum as $aids) {
                    $this->logpair .= '<tr>';
                    $this->logpair .= '<td>' . $this->all_antibiotics[$aids[0]]->abx . '</td>'; //abx name1
                    $this->logpair .= '<td>' . $this->all_antibiotics[$aids[1]]->abx . '</td>'; //abx2 name2
                    $this->logpair .= '<td>' . $aids[3] . '(' . implode(' | ', $aids[7]) . ')</td>'; //$csum
                    $this->logpair .= '<td>' . $aids[4] . '</td>'; //$countcheckmarks
                    $this->logpair .= '<td>' . $aids[6] . '</td>'; //Oral
                    $this->logpair .= '</tr>';
                }
                $this->logpair .= '</table></div>';
            }
        }

        $numpair = 0;
        foreach ($listSum as $arr) {

            $antiID1        = $arr[0];
            $antiID2        = $arr[1];
            $count_match_bac = $arr[5];

            if (count($batids) > $count_match_bac) {
                continue;
            }


            if (($this->count_match_all < 3 || ($this->PrioritizeOrialB4Activity && $this->count_pill_match_all < 3))
                && ($this->result_abx[$antiID1]['exclude'] == true || $this->result_abx[$antiID2]['exclude'] == true)
            ) {

                $numpair++;

                $this->pair_results[] = $arr;

                $this->result_abx[$antiID1]['exclude'] = false;
                $this->result_abx[$antiID2]['exclude'] = false;
                $this->result_abx[$antiID1]['numpairs'][] = $numpair;
                $this->result_abx[$antiID2]['numpairs'][] = $numpair;

                $this->result_abx[$antiID1]['count_match'] = $numpair;
                $this->result_abx[$antiID2]['count_match'] = $numpair;
                $this->count_match_all++;

                if ($this->PrioritizeOrialB4Activity) {
                    $this->count_pill_match_all++;
                }


                if ($this->result_abx[$antiID1]['iconcode'] == 'pill' && $this->result_abx[$antiID2]['iconcode'] == 'pill') {
                    $this->hasMatchOral = true;
                }
            }
        }

        if (count($this->pair_results) > 0) {
            if ($this->debug) {
                $this->logpair .= '<br>sort abx by pair 2<br>';
            }
            $this->sortpair_onlypill = $onlypill;
            uasort($this->result_abx, array($this, 'sort_abx_pair'));
            $this->sortpair_onlypill = false;
        }
    }

    public function sort_bacterias()
    {
        /*
        $bacs = array();
        $bacsexclude = array();
        foreach ($this->selectedBacterias as $bid => $bac) {
            if ($bac->isexclude) {
                $bacsexclude[$bid] = $bac;
            } else {
                $bacs[$bid] = $bac;
            }
        }
        foreach ($bacsexclude as $bid => $bac) {
            $bacs[$bid] = $bac;
        }
        $this->selectedBacterias = $bacs;
        */

        uasort($this->selectedBacterias, function ($a, $b) {

            if ($a->isexclude && !$b->isexclude) {
                return 1; //b go first
            } else if (!$a->isexclude && $b->isexclude) {
                return -1; //a go first
            }

            if ($a->pos_num == $b->pos_num) {
                if ($a->load == $b->load) {
                    return 0;
                }
                return $a->load > $b->load ? -1 : 1;
            }
            return $a->pos_num < $b->pos_num ? -1 : 1;
        });
        return $this->selectedBacterias;
    }

    public function sort_abx($a, $b)
    {

        /*
        so this is the correct order:
        1. match-all checkmark
        2. match-all pill
        3. at least one green pill 
        4. the rest
        */

        if ($this->PrioritizeOrialB4Activity) {
            //match all & pill go to top
            if (isset($a['exclude']) && $a['exclude'] === false && $a['iconcode'] == 'pill' && $b['iconcode'] != 'pill') {
                return -1;
            } else if (isset($b['exclude']) && $b['exclude'] === false && $b['iconcode'] == 'pill' && $a['iconcode'] != 'pill') {
                return 1;
            }
        }

        if ($this->PrioritizeActivity) {
            if (isset($a['count_match'])) {
                if ($a['count_match'] == $a['count_checkmark']) {
                    $a['abx_score'] += $a['count_checkmark'] * 20;
                }
                if ($b['count_match'] == $b['count_checkmark']) {
                    $b['abx_score'] += $b['count_checkmark'] * 20;
                }
            }
        }


        if (isset($a['exclude']) || isset($b['exclude'])) {

            if (($a['exclude'] === false && $b['exclude'] === false) || ($a['exclude'] === true && $b['exclude'] === true)) {

                //checkmark to top first then oral
                if ($this->PrioritizeActivity) {
                    if ($a['count_checkmark'] > $b['count_checkmark']) {
                        return -1;
                    } else if ($b['count_checkmark'] > $a['count_checkmark']) {
                        return 1;
                    }
                }


                if ($this->PrioritizeOral) {
                    //match all & pill go to top
                    if ($a['exclude'] === false && $a['iconcode'] == 'pill' && $b['iconcode'] != 'pill') {
                        return -1;
                    } else if ($b['exclude'] === false && $b['iconcode'] == 'pill' && $a['iconcode'] != 'pill') {
                        return 1;
                    }
                    // end match all & pill go to top

                    //pill not match list
                    if ($a['exclude'] === true && $b['exclude'] === true) {
                        if ($a['iconcode'] == 'pill' && $b['iconcode'] != 'pill' && isset($a['hasES80']) && $a['hasES80']) {
                            return -1;
                        } else if ($b['iconcode'] == 'pill' && $a['iconcode'] != 'pill' && isset($b['hasES80']) && $b['hasES80']) {
                            return 1;
                        }
                    }
                }
            } else if ($a['exclude'] === false && $b['exclude'] === true) {
                return -1;
            } else if ($b['exclude'] === false && $a['exclude'] === true) {
                return 1;
            }
        }



        if ($a['abx_score'] == $b['abx_score']) return 0;
        return $a['abx_score'] > $b['abx_score'] ? -1 : 1;
    }

    public function sort_abx_sum_pair($a, $b)
    {
        if ($a[2] == $b[2]) return 0;
        return $a[2] > $b[2] ? -1 : 1;
    }

    public function sort_abx_pair($a, $b)
    {
        if (!isset($a['sum_score'])) $a['sum_score'] = 0;
        if (!isset($b['sum_score'])) $b['sum_score'] = 0;

        if (isset($a['numpairs']) && count($a['numpairs']) > 0) {
            $a['sum_score'] += count($a['numpairs']) + 5;
        }
        if (isset($b['numpairs']) && count($b['numpairs']) > 0) {
            $b['sum_score'] += count($b['numpairs']) + 5;
        }

        if ($this->PrioritizeOrialB4Activity) {
            if ($this->sortpair_onlypill) {
                //match all & pill go to top
                if (isset($a['exclude']) && $a['exclude'] === false && $a['iconcode'] == 'pill' && $b['iconcode'] != 'pill') {
                    return -1;
                } else if (isset($b['exclude']) && $b['exclude'] === false && $b['iconcode'] == 'pill' && $a['iconcode'] != 'pill') {
                    return 1;
                }
            }
            //match all & pill go to top
            // var_dump($a['name']);
            // var_dump($a['numpairs']);
            // echo '<br>';
            if (isset($a['numpairs']) && count($a['numpairs']) > 0 && $a['iconcode'] == 'pill' && $b['iconcode'] != 'pill') {
                return -1;
            } else if (isset($b['numpairs']) && count($b['numpairs']) > 0 && $b['iconcode'] == 'pill' && $a['iconcode'] != 'pill') {
                return 1;
            }
        }

        if (isset($a['match_all']) && !isset($b['match_all'])) {
            $a['sum_score']  = $b['sum_score'] + 1;
        } else if (isset($b['match_all']) && !isset($a['match_all'])) {
            $b['sum_score']  = $a['sum_score'] + 1;
        } else if (isset($a['combination']) && $a['combination'] && (!isset($b['numpairs']) || count($b['numpairs']) == 0)) {
            $a['sum_score'] = $b['sum_score'] + 1;
        } else if (isset($b['combination']) && $b['combination'] && (!isset($a['numpairs']) || count($a['numpairs']) == 0)) {
            $b['sum_score'] = $a['sum_score'] + 1;
        } else if (count($a['numpairs']) == 0 && count($b['numpairs']) == 0) {
            if ($this->PrioritizeActivity) {
                if ($a['count_checkmark'] > 0 && $a['count_checkmark'] > $b['count_checkmark']) {
                    return -1;
                } else if ($b['count_checkmark'] > 0 && $b['count_checkmark'] > $a['count_checkmark']) {
                    return 1;
                }
            }
        }



        if ($a['sum_score'] == $b['sum_score'] && $b['sum_score'] > 0) {
            if (count($b['numpairs']) > 0 && count($a['numpairs']) > 0) {

                if ($a['count_checkmark'] > 0 && $a['count_checkmark'] > $b['count_checkmark']) {
                    return -1;
                } else if ($b['count_checkmark'] > 0 && $b['count_checkmark'] > $a['count_checkmark']) {
                    return 1;
                } else if ($a['count_match'] > $b['count_match']) {
                    return 1;
                } else if ($a['count_match'] < $b['count_match']) {
                    return -1;
                }
            }
            return 0;
        }

        return $a['sum_score'] > $b['sum_score'] ? -1 : 1;
    }


    public function initData()
    {
        global $wpdb;

        $whereenable = " ba.{$this->type}=1 ";
        if (!empty($this->bacteria_ids_by_panel)) {
            $bacids_str = implode(',', $this->bacteria_ids_by_panel);
            $whereenable = " ba.ID in ({$bacids_str})";
        }

        $this->all_bacterias = $wpdb->get_results("select * 
        from ci_abx_bacterias ba
        where {$whereenable}
        order by ba.position asc");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        $all_bacteria_ids = array();
        foreach ($this->all_bacterias as $bat) {
            $all_bacteria_ids[] = $bat->ID;
        }
        $all_bacteria_ids_str = implode(',', $all_bacteria_ids);

        $mkrs = $wpdb->get_results("select *, 
        resistance_marker as name,
        GROUP_CONCAT(bacteria_ID) AS bacteria_IDs
        from ci_abx_resistance_markers  mk
        JOIN ci_abx_bacteria_markers bm ON bm.marker_ID = mk.ID
        where bm.bacteria_ID in ({$all_bacteria_ids_str})
        group by mk.ID
        order by mk.position asc");

        if (isset($_GET['debugabx1'])) {
            echo $wpdb->last_query;
            echo '<pre>';
            var_dump($mkrs);
            echo '</pre>';
        }


        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        $this->all_markers = array();
        foreach ($mkrs as $mkr) {
            if ($mkr->ID == -1) {
                $mkr->ID = 'nr';
            }
            $mkr->bacteria_IDs = explode(',', $mkr->bacteria_IDs);

            $this->all_markers[$mkr->ID] = $mkr;
        }
        unset($mkrs);



        $abx_rs = $wpdb->get_results("select 
                            bacteria_ID,
                            marker_ID,
                            antibiotics_1, 
                            antibiotics_2, 
                            antibiotics_3 
                            from {$this->rm_results_tbl}");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }



        foreach ($abx_rs as $r) {

            $anti_ids1 = json_decode($r->antibiotics_1, true);
            $anti_ids2 = json_decode($r->antibiotics_2, true);
            $anti_ids3 = json_decode($r->antibiotics_3, true);

            if ($r->marker_ID == -1) {
                $r->marker_ID = 'nr';
            }

            foreach ($anti_ids1 as $antiID) {
                //first line
                if ($antiID > 0) {

                    $antikey = $r->bacteria_ID . '_' . $r->marker_ID . '_' . $antiID;
                    $this->ts_scores[$antikey] = 2;
                    $this->lt_scores[$antikey] = 3;
                }
            }

            foreach ($anti_ids2 as $antiID) {
                //second line
                if ($antiID > 0) {
                    $antikey = $r->bacteria_ID . '_' . $r->marker_ID . '_' . $antiID;
                    $this->ts_scores[$antikey] = 2;
                    $this->lt_scores[$antikey] = 2;
                }
            }

            foreach ($anti_ids3 as $antiID) {
                //3rd line
                if ($antiID > 0) {
                    $antikey = $r->bacteria_ID . '_' . $r->marker_ID . '_' . $antiID;
                    $this->ts_scores[$antikey] = 0.5;
                    $this->lt_scores[$antikey] = -2;
                }
            }
        }
        unset($abx_rs);

        //where
        $where = " {$this->subtype} = 1 ";

        $anti_rs        = $wpdb->get_results("select * 
                                from ci_abx_antibiotics ant
                                where {$where}
                                order by ant.position asc");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        foreach ($anti_rs as $anti) {

            $this->all_antibiotics[$anti->ID] = $anti;
        }
        unset($anti_rs);


        $antibiograms_rs    = $wpdb->get_results("select * from ci_abx_antibiogram");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        foreach ($antibiograms_rs as $r) {
            $activity_col   = $this->type . '_activity';
            $fda_col        = $this->type . '_fda_status';

            //++ = 3, + = 2,  +/- = 1
            $r->ab_score    = $r->{$activity_col} > 0 && $r->{$activity_col} <= 3 ? $r->{$activity_col} : 0;

            //Approved = 2, Clinical data = 1.5, In vitro = 1, none=0
            $r->fs_score    = $r->{$fda_col} > 0 && $r->{$fda_col} <= 3 ? $r->{$fda_col} : 0;
            $r->off_label_use = $r->{$this->type . '_off_label_use'};

            $r->f = 2;

            if ($r->fs_score == 3) {
                $r->fs_score = 2; //Approved
                $r->f = 0;
            } else if ($r->fs_score == 2) {
                $r->fs_score = 1.5; //Clinical data
                $r->f = 1;
            } else if ($r->fs_score == 1) {
                $r->fs_score = 1; //In vitro
                $r->f = 1;
            }

            if ($r->off_label_use) {
                $r->f = 2;
            }

            if ($r->fs_score == 3) {
                $r->fs_score = 2; //Approved
            } else if ($r->fs_score == 2) {
                $r->fs_score = 1.5; //Clinical data
            } else if ($r->fs_score == 1) {
                $r->fs_score = 1; //In vitro
            }

            if ($r->ab_score == 0) {
                //continue;
            }

            $this->antibiograms[$r->bacteria_ID . '_' . $r->antibio_ID] = $r;
        }



        $this->antibiogroups = array();
        $antigroups_rs = $wpdb->get_results("select r.* ,
        (select GROUP_CONCAT(ID) from ci_abx_resistance_markers mk where mk.class_ID = r.markerClassID group by mk.class_ID) as markers
        from ci_abx_antibio_groups r
        ");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        foreach ($antigroups_rs as $r) {
            $r->markers = explode(',', $r->markers);
            $r->exclude_Bacterias = explode(',', $r->exclude_Bacterias);
            $this->antibiogroups[$r->ID] = $r;
        }



        $rmbiograms_rs = $wpdb->get_results("select * from ci_abx_rm_biogram");

        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
            $this->errors[$wpdb->last_query] = $wpdb->last_error;
        }

        foreach ($rmbiograms_rs as $r) {
            if ($r->marker_ID == -1) {
                $r->marker_ID = 'nr';
            }

            //++ = 3, + = 2,  +/- = 1, -/- = -1, - = -2
            $r->rm_score = !empty($r->value) ? $r->value : 0;

            if ($r->antibio_ID > 10000) {
                //it is group
                $gid = $r->antibio_ID - 10000;
                if (isset($this->antibiogroups[$gid]) && in_array($r->marker_ID, $this->antibiogroups[$gid]->markers)) {
                    $r->antibio_ID =  $this->antibiogroups[$gid]->antiID1;
                    $r->exclude_Bacterias = $this->antibiogroups[$gid]->exclude_Bacterias;
                    $this->rmbiograms[$r->marker_ID . '_g_' . $this->antibiogroups[$gid]->antiID1] = $r;
                    $this->rmbiograms[$r->marker_ID . '_g_' . $this->antibiogroups[$gid]->antiID2] = $r;
                }
            } else if (!isset($this->rmbiograms[$r->marker_ID . '_' . $r->antibio_ID])) {
                $this->rmbiograms[$r->marker_ID . '_' . $r->antibio_ID] = $r;
            }
        }
        unset($rmbiograms_rs);
    }

    public function cal_score1($batid, $markerid, $anti)
    {
        $antid      = $anti->ID;
        $bacan_key  = $batid . '_' . $antid;
        $maan_key   = $markerid . '_' . $antid;
        $maan_group_key = $markerid . '_g_' . $antid;

        if (!isset($this->rmbiograms[$maan_key]) || !isset($this->antibiograms[$bacan_key])) {
            return false;
        }
        $antibiogram    = isset($this->antibiograms[$bacan_key]) ? $this->antibiograms[$bacan_key] : null;
        $rmbiogram      = isset($this->rmbiograms[$maan_key]) ? $this->rmbiograms[$maan_key] : null;

        if (isset($this->rmbiograms[$maan_group_key]) && !in_array($batid, $this->rmbiograms[$maan_group_key]->exclude_Bacterias)) {
            $rmbiogram = $this->rmbiograms[$maan_group_key];
        }

        $cAB    = $antibiogram ? $antibiogram->ab_score : 0;
        $F      = $antibiogram ? $antibiogram->f : 0;
        $cFS    = $antibiogram ? $antibiogram->fs_score : 0;

        $cRM    = $rmbiogram ? $rmbiogram->rm_score : 0;

        $cTS    = isset($this->ts_scores[$batid . '_' . $markerid . '_' . $antid]) ? $this->ts_scores[$batid . '_' . $markerid . '_' . $antid] : 0;

        return array('AB' => $cAB, 'RM' => $cRM, 'F' => $F, 'FS' => $cFS, 'TS' => $cTS);
    }

    function resetbuild()
    {
        update_option('lastb_' . $this->abx_tbl, 0);
    }

    function build_all_scores()
    {
        global $wpdb;

        $last_change    = get_option('ci_abx_last_change', 0);
        $last_build     = get_option('lastb_' . $this->abx_tbl, 0) - 500;

        $updating_data  = get_option('ci_abx_updating_data', 0);

        if ($updating_data > time() - 100) {
            ob_get_clean();
            $this->log .= 'Syncing Data. Please try later!';


            echo 'Syncing Data. Please try later!';
            exit;
        }


        if ($this->type == 'v') {
            return;
        }



        $this->initData();



        if (empty($this->all_bacterias)) {
            echo 'db error: empty bacteria. Please try again later!';
            exit;
        }
        if (empty($this->all_markers)) {
            // echo 'db error: empty markers. Please try again later!';
            //   exit;
        }
        if ($this->count_errors > 0) {
            echo 'db error. Please try again later!';
            var_dump($this->errors);
            exit;
        }

        //check data exist
        $hasdata = $wpdb->get_var("select bacteria_ID from {$this->abx_tbl} limit 1") > 0;

        if ($hasdata && $last_build > $last_change && $last_change > 0 && !isset($_REQUEST['clearcacheabx'])) {
            if ($this->debug) {
                $this->log .= "<br> - loaded cache scores from {$this->abx_tbl} <br><br>\n";
            }
            return;
        }


        //check table exist
        $tableexist = $wpdb->get_col("SHOW TABLES LIKE '{$this->abx_tbl}';");
        if (count($tableexist) == 0) {
            $sql = "CREATE TABLE `{$this->abx_tbl}` (
                `bacteria_ID` int(11),
                `marker_ID` int(11),
                `antibiotics` text
            ) ENGINE=InnoDB";
            $wpdb->query($sql);
        }

        $wpdb->query("truncate table {$this->abx_tbl}");


        $hasscoredata = 0;
        foreach ($this->all_bacterias as &$bat) {
            $bat->scores = array();
            $bat->abxs = array();

            //score for nr
            $bat->scores['nr'] = array();

            foreach ($this->all_antibiotics as $anti) {

                if (!$anti->{$this->subtype}) continue;

                $score1 = $this->cal_score1($bat->ID, 'nr', $anti);
                if ($score1 !== false) {
                    $bat->scores['nr'][$anti->ID] = $score1;
                }
            }

            $this->insertScore2Db($bat->ID, '-1', $bat->scores['nr']);


            //score for markers
            foreach ($this->all_markers as $mkid => $marker) {

                if ($mkid == 'nr') continue;
                if (!in_array($bat->ID, $marker->bacteria_IDs)) {
                    continue;
                }
                $bat->scores[$marker->ID] = array();

                foreach ($this->all_antibiotics as $anti) {
                    if (!$anti->{$this->subtype}) continue;

                    $score1 = $this->cal_score1($bat->ID, $marker->ID, $anti);

                    if ($score1 !== false) {
                        $bat->scores[$marker->ID][$anti->ID] = $score1;
                    }
                }


                $this->insertScore2Db($bat->ID, $marker->ID, $bat->scores[$marker->ID]);
                $hasscoredata++;
            }
        }

        if ($hasscoredata > 10) {
            update_option('lastb_' . $this->abx_tbl, date_i18n('YmdHis'));
        }

        if ($this->debug) {
            $this->log .= "<br> - builded cache scores for {$this->abx_tbl}, count: {$hasscoredata} <br><br>\n";
        }
    }



    public function insertScore2Db($batid, $makerid, $scores)
    {
        global $wpdb;

        $table = $this->abx_tbl;

        $scores = json_encode($scores);


        $wpdb->insert($table, array(
            'bacteria_ID' => $batid,
            'marker_ID' => $makerid,
            'antibiotics' => $scores,
        ));
    }

    public function get_log()
    {
        return $this->log;
    }
}
