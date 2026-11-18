<?php
class CI_ABX_BV extends CI_ABX
{

    public $style           = 1;
    public $version         = '1.0.0-vh';
    public $yeast_version   = 1;
    public $yeast_uti_wound = ''; //empty/uti/wound/ei/nail
    public $yeast_uti_wound_abx_groups = array();
    public $RelativeLoad    = true;
    public $use_new_calc_risk = false;
    public $show_bv_desc = false;
    public $bvavur_notes2 = array();
    public $va_amrs = array();
    public $positie_mk_ids = array();
    public $negative_mk_ids = array();


    //BV
    public $bv_bac_Ids    = array(
        'bv' => array(),
        'bvo' => array(),
        'lb' => array(),
        'lbi' => array(),
        'ur' => array(),
        'av' => array(),
        'sti' => array(),
        'yeast' => array(),
        'sm' => array(),
        'sr' => array(),
        'sp' => array(),
        'sl' => array(),
        'es' => array(),
        'wv' => array(),
        'opportunistic' => array(),
    );
    public $bv_sum_ra    = array(
        'bv' => 0,
        'lb' => 0,
        'lbi' => 0,
        'ur' => 0,
        'av' => 0,
        'sti' => 0,
        'yeast' => 0,
        'sm' => 0,
        'sr' => 0,
        'sp' => 0,
        'sl' => 0,
        'es' => 0,
        'opportunistic' => 0,
    );
    public $vm_scores        = array(
        'bv' => 0,
        'av' => 0,
        'ur' => 0,
        'lb' => 0,
        'all' => 0,
    );

    public $score_highest = array(
        'bv' => 0,
        'av' => 0,
        'ur' => 0,
        'highest' => '',
    );

    public $total_loadbytypes = array(
        'bv' => 0,
        'av' => 0,
        'ur' => 0,
        'opportunistic' => 0,
    );

    public $panel_risks = array(
        'bv' => 0,
        'av' => 0,
        'ur' => 0,
        'yeast' => 0,
        'opportunistic' => 0,
    );

    public $highest_panel       = '';
    protected $highest_risk     = 0;
    protected $bv_abx_scores    = array();
    protected $excludedPanels   = array();

    protected $bv_abxs          = array();
    protected $bv_abx_colors    = array();
    protected $bv_abx_types     = array();

    public $bv_total_load       = 0;
    public $yeast_total_load    = 0;
    public $yeast_max_risk      = 0;
    public $bv_risk             = 0;
    public $lb_total_load       = 0;
    public $lb_percent          = 0;
    public $abxByBAVSCore       = true;
    public $before_panel_riskey = '';





    protected $bvav_Types       = array();
    public $bvavTypeKey         = 'bv';
    public $bvavSubTypeKey      = '';
    public $countBVType         = 0;
    public $parent_type         = 'bv';

    public $separate_opportunistic = false;


    protected $log_load         = '';

    public function __construct()
    {
        $type = 'v';
        $this->type             = $type;
        $this->subtype          = $type;
        $this->rm_results_tbl   = 'ci_abx_rm_scores_' . $type;
        $this->abx_tbl          = 'ci_abx_results_scores_' . $type;
    }


    public function get_typename()
    {
        return 'Vaginitis';
    }


    public function addBacteria($arr)
    {
        global $wpdb, $ci_cache_queries;
        $where = '';
        //get mkids
        $baids = '';

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            global $ci_cache_cachebids;
            if (!is_array($ci_cache_cachebids)) $ci_cache_cachebids = array();
            if (!isset($ci_cache_cachebids[$arr['te_ID']])) {
                $baids = $wpdb->get_var(
                    "select meta_value 
                    from ci_test_meta 
                    where test_id = {$arr['te_ID']} and meta_key='bac_tests' "
                );
                $ci_cache_cachebids[$arr['te_ID']] = $baids;
                if (!empty($wpdb->last_error)) {
                    $this->count_errors++;
                }
            } else {
                $baids = $ci_cache_cachebids[$arr['te_ID']];
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

        $notes_field    = 'notes_' . $this->type;
        $nc_field    = 'nc_' . $this->type;
        $desc_field     = 'description2_' . $this->type;
        if (isset($arr['bv_type']) && in_array($arr['bv_type'], array('sm', 'sr', 'sp', 'sl', 'es', 'wv'))) {
            $desc_field     = 'description_' . $arr['bv_type'];
        }

        $risk_field     = $this->type != 'uti' ? 'risk_' . $this->type : 'risk';
        if ($this->yeast_uti_wound == 'uti') {
            $risk_field = 'risk';
        } else if ($this->yeast_uti_wound == 'wound') {
            $risk_field = 'risk_wound';
        } else if ($this->yeast_uti_wound == 'ei') {
            $risk_field = 'risk_ei';
        } else if ($this->yeast_uti_wound == 'nail') {
            $risk_field = 'risk_nail';
        }

        $morefields     = '';

        if ($this->excludeNC0) {
            $morefields .= ", {$nc_field} as nc";
        }
        if (in_array($this->yeast_uti_wound, array('uti', 'wound', 'ei', 'nail'))) {
            $morefields .= ", description2_{$this->yeast_uti_wound} as description2_yeast";
        }

        $sql1 = "select 
        ID,
        bacteria_name as name,
        bacteria_abrev as abrev,
        gram_type,
        v_type,
        {$risk_field} as risk,
        {$notes_field} as notes,
        {$desc_field} as description2
        {$morefields}
        from ci_abx_bacterias 
        where {$where} ";

        $qkey = md5($sql1);
        if (!isset($ci_cache_queries[$qkey])) {
            $rs = $wpdb->get_results($sql1);
            $ci_cache_queries[$qkey] = $rs;
        } else {
            $rs = $ci_cache_queries[$qkey];
        }


        if (!empty($wpdb->last_error)) {
            $this->count_errors++;
        }

        if ($this->debug && count($rs) == 0) {
            $this->log .= $wpdb->last_error;
            $this->log .= 'Bacteria not found<br>';
            $this->log .= implode(', ', $test_names);
            $this->log .= '<br><br>';
        }


        foreach ($rs as $r) {

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
                }
            }


            if (isset($arr['bv_type']) && !empty($arr['bv_type'])) {
                $r->bv_type = $arr['bv_type'];
            } else {
                $bvtypes        = explode(',', $r->v_type);
                foreach ($bvtypes as $bvtype) {
                    if (isset($this->all_bv_types[$bvtype])) {
                        $r->bv_type = $this->all_bv_types[$bvtype][0];
                        if (!in_array($r->bv_type, array('sm', 'sp', 'sr', 'sl', 'es'))) {
                            break;
                        }
                    }
                }
            }


            if ($this->separate_opportunistic && $r->name == 'Ureaplasma urealyticum') {
                $r->bv_type = 'opportunistic';
            }



            $r->color       = isset($arr['te_color']) ? $arr['te_color'] : '';
            $r->conc        = isset($arr['conc']) && $arr['conc'] > 0 ? $arr['conc'] : 27;
            $r->ra          = in_array($r->bv_type, array('av', 'bv', 'ur', 'yeast')) ? 2 ** (-1 * $r->conc) : 0;
            $r->load        = $this->cal_bat_load($arr, $r);

            // $r->load        = pow(10, (($r->conc - $this->load_y_intercept) / $this->load_slope ));

            $r->bvtypenum   = 1;
            if ($r->bv_type == 'av') {
                $r->bvtypenum = 2;
                $this->NotTestAV = 0;
            } else if ($r->bv_type == 'ur') {
                $r->bvtypenum = 3;
            }


            if (in_array($r->bv_type, array('av', 'bv', 'ur'))) {

                if (stripos($r->name, 'Lactobacillus iners') === 0) {
                    $this->lb_total_load += $r->load;
                    $this->bv_bac_Ids['lbi'][] = $r->ID;
                    $r->lb_weight = 0.8;
                } else if (stripos($r->name, 'Lactobacillus') === 0) {
                    $this->lb_total_load += $r->load;
                    $this->bv_bac_Ids['lb'][] = $r->ID;
                    $r->lb_weight = 1;
                    if (stripos($r->name, 'Jensenii') > 0) {
                        $r->lb_weight = 0.95;
                    } else if (stripos($r->name, 'Gasseri') > 0) {
                        $r->lb_weight = 0.9;
                    }
                } else {
                    $this->bv_bac_Ids[$r->bv_type . 'o'][] = $r->ID;

                    if (!isset($this->total_loadbytypes[$r->bv_type])) {
                        $this->total_loadbytypes[$r->bv_type] = 0;
                    }
                    $this->total_loadbytypes[$r->bv_type] += $r->load;
                }
            } else if ($r->bv_type == 'yeast' && $this->yeast_version == 2) {
                if (!isset($this->total_loadbytypes[$r->bv_type])) {
                    $this->total_loadbytypes[$r->bv_type] = 0;
                }
                $this->total_loadbytypes[$r->bv_type] += $r->load;
                $this->yeast_max_risk = max($this->yeast_max_risk, $r->risk);
            }

            $this->bv_bac_Ids[$r->bv_type][] = $r->ID;
            $this->bv_sum_ra[$r->bv_type] += $r->ra;

            $r->abxs = $this->get_bv_bacteria_abxs($r->ID, $r->bv_type);

            $this->selectedBacterias[$r->ID] = $r;
        }
    }


    function checkNotTestAV()
    {
        global $wpdb, $ci_cache_cachebids, $ci_cache_queries;

        if ($this->NotTestAV === false && is_array($this->bacterias_neg)) {
            $has_av = false;
            foreach ($this->bacterias_neg as $arr) {
                $where = ' FIND_IN_SET(2,v_type) '; //av
                //get mkids
                $baids = '';

                if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
                    if (!is_array($ci_cache_cachebids)) $ci_cache_cachebids = array();
                    if (!isset($ci_cache_cachebids[$arr['te_ID']])) {
                        $baids = $wpdb->get_var(
                            "select meta_value 
                            from ci_test_meta 
                            where test_id = {$arr['te_ID']} and meta_key='bac_tests' "
                        );
                        $ci_cache_cachebids[$arr['te_ID']] = $baids;
                        if (!empty($wpdb->last_error)) {
                            $this->count_errors++;
                        }
                    } else {
                        $baids = $ci_cache_cachebids[$arr['te_ID']];
                    }
                }

                if (!empty($baids)) {
                    $where .= " and ID in ({$baids}) ";
                } else {
                    $test_names = array($arr['te_name']);
                    if (isset($arr['te_abrev']) && !empty($arr['te_abrev'])) {
                        $test_names[] =  $arr['te_abrev'];
                    }
                    $test_names_str = implode("','", $test_names);
                    $where .= " and bacteria_name in ('{$test_names_str}') ";
                }
                $sql1 = "select count(*) 
                from ci_abx_bacterias 
                where {$where} ";
                $qkey = md5($sql1);
                if (!isset($ci_cache_queries[$qkey])) {
                    $has_av = $wpdb->get_var($sql1);
                    $ci_cache_queries[$qkey] = $has_av;
                } else {
                    $has_av = $ci_cache_queries[$qkey];
                }

                if ($has_av) {
                    break;
                }
            }

            if (!$has_av) {
                $this->NotTestAV = true;
            }
        }
    }

    public function getBV_None()
    {
        $batname = 'None';
        if ($this->RelativeLoad) {
            $batname .= '<span class="batrelatives">';
            for ($i = 1; $i <= 5; $i++) {
                $batname .= '<span class="relateitem"></span>';
            }
            $batname .= '</span>';
        }
        return $batname;
    }

    public function getBV_Bacterias($type, $onlyName = false)
    {
        $return = array();
        $bids = isset($this->bv_bac_Ids[$type]) ? $this->bv_bac_Ids[$type] : array();
        foreach ($bids as $bid) {
            $bat = $this->selectedBacterias[$bid];
            $batname = '<span class="bacname ' . ($this->RelativeLoad ? 'bacrelativeload' : '') . '">' . $bat->name . '</span>';

            if ($this->RelativeLoad) {
                $batname .= '<span class="batrelatives">';
                for ($i = 1; $i <= 5; $i++) {
                    $bstyle = '';
                    if (isset($_GET['debuglnum'])) {
                        $bat->l_num = $_GET['debuglnum'];
                    }

                    if (isset($bat->l_num) && $i == $bat->l_num) {
                        $bgcolor = '117, 191, 66'; //75bf42
                        if ($type == 'bvo') {
                            $bgcolor = '240, 78, 19'; //f04e13
                        } else if ($type == 'lbi') {
                            $bgcolor = '187, 214, 63'; //bbd63f
                        } else if ($type == 'ur') {
                            $bgcolor = '255, 207, 41'; //ffcf29
                        } else if ($type == 'av') {
                            $bgcolor = '236, 139, 26'; //ec8b1a
                        }
                        $bgtrpr = 1 - (5 - $i) * 0.175;
                        $bgcolor = 'rgba(' . $bgcolor . ', ' . $bgtrpr . ')';
                        $bstyle = ' style="background:' . $bgcolor . '"';
                    }
                    $batname .= '<span class="relateitem" ' . $bstyle . '></span>';
                }
                $batname .= '</span>';
            } else if ($this->show_percent_bacteria) {
                $batname .= '<span class="batpercent">' . number_format($bat->ra_percent, 2) . '%</span>';
            }

            $return[$bid] = $onlyName ? $batname : $bat;
        }

        return $return;
    }

    public function getbvtypes()
    {
        if ($this->style == 'v') {
            uasort($this->panel_risks, function ($a, $b) {
                return $a > $b ? -1 : 1;
            });
            $oldtypes = $this->bvav_Types;
            $this->bvav_Types = array();
            foreach ($this->panel_risks as $t => $sc) {
                if (isset($oldtypes[$t])) {
                    $this->bvav_Types[$t] = $oldtypes[$t];
                }
            }
        }
        return $this->bvav_Types;
    }

    public function cappedPathogenScore($score, $cap)
    {
        return min($score, $cap); // Cap the pathogen score at the specified custom value
    }

    public function totalRiskScoreWithCustomCaps($scores, $caps)
    {
        $productOfComplements = 1;

        // Loop through each pathogen score and apply the custom caps
        foreach ($scores as $index => $score) {
            $cappedScore = $this->cappedPathogenScore($score, $caps[$index]); // Apply the cap for each pathogen
            $complement = (100 - $cappedScore) / 100; // Calculate complement (100 - Capped Score_i) / 100
            $productOfComplements *= $complement; // Multiply the complements
        }

        // Calculate the total risk score
        $totalRiskScore = (1 - $productOfComplements) * 100; // Final risk score

        return $totalRiskScore;
    }

    public function hasLactobacillus()
    {
        return count($this->bv_bac_Ids['lb']) + count($this->bv_bac_Ids['lbi']) > 0;
    }

    public function getBV_Lactobacillus_Percent()
    {

        return $this->lb_percent;
    }

    public function getBV_Risk()
    {
        return $this->bv_risk;
    }

    public function getPanel_Risk($type)
    {
        return $this->panel_risks[$type];
    }

    function _cal_LB_Percent()
    {
        $sumlb    = 0;
        $sumlbi = 0;

        foreach ($this->bv_bac_Ids['lb'] as $bid) {
            $bat = $this->selectedBacterias[$bid];
            $sumlb += $bat->ra_percent;
        }
        if ($sumlb > 100) $sumlb = 100;
        foreach ($this->bv_bac_Ids['lbi'] as $bid) {
            $bat    = $this->selectedBacterias[$bid];
            $sumlbi += $bat->ra_percent;
        }

        if ($sumlbi > 100) $sumlbi = 100;

        $this->lb_percent = $sumlb + ($sumlbi); //($sumlbi /2)

        if ($this->debug) {
            $this->log .= "%LB = {$this->lb_percent} % (sum RA)<br>";
        }
    }

    public function getBatLNumbyScore($bat)
    {
        $lnum = 0;
        $score = $bat->score;
        if ($score < 20) {
            $lnum = 1;
        } else if ($score < 40) {
            $lnum = 2;
        } else if ($score < 60) {
            $lnum = 3;
        } else if ($score < 80) {
            $lnum = 4;
        } else {
            $lnum = 5;
        }
        return $lnum;
    }

    public function _cal_BV_Risk()
    {
        global $wpdb, $ci_cache_queries;

        //calc panels risks
        $countpanel = 0;
        $has_bauv   = false;
        $single_panel_risk_panel = null;
        foreach ($this->bvav_Types as $t => $hastype) {
            if ($hastype && isset($this->bv_abxs[$t])) {
                $countpanel++;
            }
        }

        $sql = "select * from ci_bvm_algo";
        if (!empty($this->lab_dba_init)) {
            //only for labconnect
            $colexist = $wpdb->get_var("select t_min_{$this->lab_dba_init} from ci_bvm_algo");

            if (!is_null($colexist)) {
                $sql = "select ID, 
                bac_ID, 
                IF(individual_cap_{$this->lab_dba_init}>0, individual_cap_{$this->lab_dba_init}, individual_cap) as individual_cap,
                IF(t_min_{$this->lab_dba_init}>0, t_min_{$this->lab_dba_init}, t_min) as t_min,
                IF(t_max_{$this->lab_dba_init}>0, t_max_{$this->lab_dba_init}, t_max) as t_max
                from ci_bvm_algo";
            }
        }
        $qkey = md5($sql);
        if (!isset($ci_cache_queries[$qkey])) {
            $bvm_rs = $wpdb->get_results($sql);
            $ci_cache_queries[$qkey] = $bvm_rs;
        } else {
            $bvm_rs = $ci_cache_queries[$qkey];
        }

        $lbm_tmin = 1000000;

        $bvms = array();
        foreach ($bvm_rs as $r) {
            if ($r->bac_ID == -1) {
                if ($r->t_min > 0) {
                    $lbm_tmin = $r->t_min;
                }
            } else {
                $bvms[$r->bac_ID] = $r;
            }
        }


        $count_bv_risk5 = 0;

        if (count($this->bv_bac_Ids['bvo']) + count($this->bv_bac_Ids['av']) + count($this->bv_bac_Ids['ur']) > 0) {
            $has_bauv   = true;
            $all_scores = array();
            $all_caps   = array();
            if (count($this->bv_bac_Ids['bvo']) > 0) {
                $scores = array();
                $caps = array();
                foreach ($this->bv_bac_Ids['bvo'] as $bid) {
                    $bat = &$this->selectedBacterias[$bid];
                    $bat->l_num = 0;
                    if (isset($bvms[$bid])) {
                        $bvm = $bvms[$bid];
                        $bat->t_min = $bvm->t_min;
                        $bat->t_max = $bvm->t_max;
                        $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
                        if ($bat->score < 0) $bat->score = 0;
                        $scores[]       = $bat->score;
                        $all_scores[]   = $bat->score;
                        $caps[]         = $bvm->individual_cap;
                        $all_caps[]     = $bvm->individual_cap;

                        if ($bat->score >  $this->score_highest['bv']) {
                            $this->score_highest['bv'] = $bat->score;
                        }

                        //fill l num
                        $bat->l_num = $this->getBatLNumbyScore($bat);
                    }

                    if ($bat->risk == $this->bat_max_risk() - 1) { //5 / 10
                        $count_bv_risk5++;
                    }
                }

                $this->vm_scores['bv'] = $this->totalRiskScoreWithCustomCaps($scores, $caps);
                if ($this->debug) {
                    $this->log .= "<br>BVM Score= {$this->vm_scores['bv']}<br>";
                    $this->log .= "<br>Count BVrisk5= {$count_bv_risk5}<br>";
                }
            }

            if (count($this->bv_bac_Ids['ur']) > 0) {
                $scores = array();
                $caps = array();
                foreach ($this->bv_bac_Ids['ur'] as $bid) {
                    $bat = &$this->selectedBacterias[$bid];
                    if (isset($bvms[$bid])) {
                        $bvm = $bvms[$bid];
                        $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
                        if ($bat->score < 0) $bat->score = 0;
                        $scores[]       = $bat->score;
                        $all_scores[]   = $bat->score;
                        $caps[]         = $bvm->individual_cap;
                        $all_caps[]     = $bvm->individual_cap;

                        if ($bat->score >  $this->score_highest['ur']) {
                            $this->score_highest['ur'] = $bat->score;
                        }
                        $bat->l_num = $this->getBatLNumbyScore($bat);
                    }
                }


                $this->vm_scores['ur'] = $this->totalRiskScoreWithCustomCaps($scores, $caps);


                if ($this->debug) {
                    $this->log .= "<br>URM Score= {$this->vm_scores['ur']}<br>";
                }
            }

            if (count($this->bv_bac_Ids['av']) > 0) {
                $scores = array();
                $caps = array();
                foreach ($this->bv_bac_Ids['av'] as $bid) {
                    $bat = &$this->selectedBacterias[$bid];
                    if (isset($bvms[$bid])) {
                        $bvm = $bvms[$bid];
                        $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
                        if ($bat->score < 0) $bat->score = 0;
                        $scores[]       = $bat->score;
                        $all_scores[]   = $bat->score;
                        $caps[]         = $bvm->individual_cap;
                        $all_caps[]     = $bvm->individual_cap;

                        if ($bat->score >  $this->score_highest['av']) {
                            $this->score_highest['av'] = $bat->score;
                        }
                        $bat->l_num = $this->getBatLNumbyScore($bat);
                    }
                }

                $this->vm_scores['av'] = $this->totalRiskScoreWithCustomCaps($scores, $caps);
                if ($this->debug) {
                    $this->log .= "<br>AVM Score= {$this->vm_scores['av']}<br>";
                }
            }

            $this->score_highest['score']   = max($this->score_highest);

            $this->score_highest['highest'] = array_search($this->score_highest['score'], $this->score_highest);

            $this->vm_scores['all'] = $this->totalRiskScoreWithCustomCaps($all_scores, $all_caps);
            if ($this->debug) {
                // var_dump($all_scores);
                //  var_dump($all_caps);
                $this->log .= "<br>VM Score = {$this->vm_scores['all']} <br>";
            }

            if ($this->NotLactobacillus) {
                //return 100 - ($this->vm_scores['bv'] + $this->vm_scores['ur'] + $this->vm_scores['av']);
            }
        }




        //get LB score
        $this->vm_scores['lb'] = 100;
        foreach ($this->bv_bac_Ids['lb'] as $bid) {
            $bat = &$this->selectedBacterias[$bid];
            $bvm = $bvms[$bid];
            $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
            if ($bat->score < 0) $bat->score = 0;
            $bat->lb_score = $bat->lb_weight * $bat->ra_percent;
            $this->vm_scores['lb'] -= $bat->lb_score;

            $bat->l_num = $this->getBatLNumbyScore($bat);
        }

        foreach ($this->bv_bac_Ids['lbi'] as $bid) {
            $bat = &$this->selectedBacterias[$bid];
            $bvm = $bvms[$bid];
            $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
            if ($bat->score < 0) $bat->score = 0;
            $bat->lb_score = $bat->lb_weight * $bat->ra_percent;
            $this->vm_scores['lb'] -= $bat->lb_score;

            $bat->l_num = $this->getBatLNumbyScore($bat);
        }

        if ($this->debug) {
            $this->log .= "LB score: " . $this->vm_scores['lb'] . "<br>";
        }

        //LBM 2
        $this->vm_scores['lbm'] = min(100 * $this->lb_total_load / $lbm_tmin, 100);


        //LBR = 100 - [(%crispatus×1)+(%jensenii×0.95)+(%gasseri×0.9)+(%iners×0.8) x LBM/100 ]
        $this->vm_scores['lbr'] = 100 - (100 - $this->vm_scores['lb']) * $this->vm_scores['lbm'] / 100;
        $this->vm_scores['lbry'] = 100 - $this->vm_scores['lbr'];

        if ($this->debug) {
            $this->log .= "LBM = " . $this->vm_scores['lbm'] . '<br>';
            $this->log .= "LBR = " . $this->vm_scores['lbr'] . '<br>';
            $this->log .= "LBRY = " . $this->vm_scores['lbry'] . '<br>';
        }



        if ($this->yeast_version == 2 && count($this->bv_bac_Ids['yeast']) > 0) {

            $scores = array();
            $caps = array();
            foreach ($this->bv_bac_Ids['yeast'] as $bid) {
                $bat = &$this->selectedBacterias[$bid];
                if (isset($bvms[$bid])) {
                    $bvm = $bvms[$bid];
                    $bat->t_min = $bvm->t_min;
                    $bat->t_max = $bvm->t_max;
                    $bat->score =  100 * ($bat->load - $bvm->t_min) / ($bvm->t_max - $bvm->t_min);
                    if ($bat->score < 0) $bat->score = 0;
                    $scores[]       = $bat->score;
                    $all_scores[]   = $bat->score;
                    $caps[]         = $bvm->individual_cap;
                    $all_caps[]     = $bvm->individual_cap;
                }
            }

            $this->vm_scores['yeast'] = $this->totalRiskScoreWithCustomCaps($scores, $caps);
            if ($this->debug) {
                $this->log .= "<br>CVM Score= {$this->vm_scores['yeast']}<br>";
            }

            $this->panel_risks['yeast'] = $this->vm_scores['yeast'];
            if ($countpanel > 1) {
                // $this->panel_risks['yeast'] = $this->vm_scores['lb'] * 0.66 * ($this->vm_scores['yeast'] / 100) + $this->vm_scores['yeast'] * 0.34;
            } else {
                //$this->panel_risks['yeast'] = $this->vm_scores['lb'] * 0.5 + $this->vm_scores['yeast'] * 0.5;
            }

            if ($this->debug) {
                $this->log .= 'Yeast risk = ' . $this->panel_risks['yeast'] . '<br>';
            }
        }

        //build panel risk
        foreach ($this->bvav_Types as $t => $hastype) {
            if ($hastype) {

                if ($t == 'ur') {
                    //change what we have now to UR risk=URM. so it will be based just on load and not have LB part of the formula
                    $this->panel_risks['ur'] = $this->vm_scores['ur'];
                } else if ($countpanel > 1) {
                    //changed 14/10/24
                    $this->panel_risks[$t] = $this->vm_scores['lb'] * 0.66 * ($this->vm_scores[$t] / 100) + $this->vm_scores[$t] * 0.34;
                } else {
                    $this->panel_risks[$t] = $this->vm_scores['lb'] * 0.66 + $this->vm_scores[$t] * 0.34;
                }

                //new formula BVR=BVM x (1-%LB/100)
                //NEW: BVR=BVM x %BV
                $this->vm_scores[$t . 'r'] = $this->vm_scores[$t] * $this->vm_scores[$t . '_percent'] / 100;
                //SF (scaling factor)=1−e −BVR/25
                $this->vm_scores[$t . '_sf'] = 1 - exp(-$this->vm_scores[$t . 'r'] / 10);

                //new formula BV risk 2=LBR x BVR/100
                //NEW: BV risk =LBR x 0.5 x SF + BVR x 0.5

                if ($t == 'ur') {
                    //change what we have now to UR risk=URM. so it will be based just on load and not have LB part of the formula
                    $this->panel_risks['urnew'] = $this->vm_scores['ur'];
                } else {
                    $this->panel_risks[$t . 'new'] = $this->vm_scores['lbr'] * 0.5 * $this->vm_scores[$t . '_sf'] + $this->vm_scores[$t . 'r'] * 0.5;
                }


                if ($this->panel_risks[$t] > 0) {
                    if ($single_panel_risk_panel === null) {
                        $single_panel_risk_panel = $t;
                    } else {
                        $single_panel_risk_panel = '';
                    }
                }



                if ($this->debug) {
                    $this->log .= '<br>';
                    $this->log .= strtoupper($t) . 'R = ' . $this->vm_scores[$t . 'r'] . '<br>';
                    $this->log .= strtoupper($t) . '% = ' . $this->vm_scores[$t . '_percent'] . '<br>';
                    $this->log .= strtoupper($t) . ' FS = ' . $this->vm_scores[$t . '_sf'] . '<br>';
                    $this->log .= strtoupper($t) . ' risknew = ' . $this->panel_risks[$t . 'new'] . '<br>';
                    $this->log .= strtoupper($t) . ' risk = ' . $this->panel_risks[$t] . '<br><br>';
                }

                if ($this->use_new_calc_risk) {
                    $this->panel_risks[$t]  = $this->panel_risks[$t . 'new'];
                    $this->log .= strtoupper($t) . ' =>>risk = ' . $this->panel_risks[$t] . '<br><br>';
                }



                if ($t == 'bv' && $count_bv_risk5 > 0) {
                    ////AF=1-e-N/2
                    $AF = 1 - exp(-$count_bv_risk5 / 2);
                    //DF=e-BVR/2
                    $DF = exp(-$this->vm_scores[$t . 'r'] / 30);
                    $this->panel_risks[$t . 'plus'] = $this->panel_risks[$t . 'new'] * (1 + $AF * $DF);

                    if ($this->use_new_calc_risk) {
                        $this->panel_risks[$t] = $this->panel_risks[$t . 'plus'];
                    }


                    if ($this->debug) {
                        $this->log .= '<br>';
                        $this->log .= strtoupper($t) . ' AF = ' . $AF . '<br>';
                        $this->log .= strtoupper($t) . ' DF = ' . $DF . '<br>';
                        $this->log .= strtoupper($t) . ' riskplus = ' . $this->panel_risks[$t . 'plus'] . '<br>';
                    }
                }
            }
        }

        if ($this->style == 'v') { //version 1
            $this->_runExcludePanelbyRatio();
        }



        if ($this->bvavTypeKey == 'bav' && count($this->bv_bac_Ids['bvo']) > 0 && count($this->bv_bac_Ids['av']) == 0) {
            $highest_risk = 0;
            $this->bvavSubTypeKey = '';

            /*
            disable this by new rule 18/6/2025 below
            foreach ($this->getbvtypes() as $bvty => $hasbvt) {
                if (!$hasbvt || $bvty == 'av') continue;
                if ($this->panel_risks[$bvty] > $highest_risk) {

                    $hasabx = $this->bv_get_abxs($bvty);
                    if (!empty($hasabx) ) {
                        $highest_risk = $this->panel_risks[$bvty];
                        $this->bv_risk = $this->panel_risks[$bvty];
                        $this->bvavTypeKey = $bvty;
                        $this->bvavSubTypeKey = $bvty;
                    }
                }
            }*/
        }


        //new rule https://lite.evernote.com/note/44cf231e-2bde-7f30-5972-c45364c43dae

        $ns_conditions = [];
        $this->bvavur_notes2 = [];

        if ($this->bvav_Types['av'] && $this->hasTreatmentO('av')) $ns_conditions[] = 'av';
        if ($this->bvav_Types['bv'] && $this->hasTreatmentO('bv')) $ns_conditions[] = 'bv';
        if ($this->bvav_Types['ur'] && $this->hasTreatmentO('ur')) $ns_conditions[] = 'ur';


        if (count($ns_conditions) === 1) {
            // Case: Only 1 NS condition
            $this->bvavTypeKey = $ns_conditions[0];

            // Add Note 1 for any suppressed-but-detected condition
            if ($this->bvav_Types['av'] && !$this->hasTreatmentO('av')) $this->bvavur_notes2[] = 'av1';
            if ($this->bvav_Types['bv'] && !$this->hasTreatmentO('bv')) $this->bvavur_notes2[] = 'bv1';
            if ($this->bvav_Types['ur'] && !$this->hasTreatmentO('ur')) $this->bvavur_notes2[] = 'ur1';
        } elseif (count($ns_conditions) > 1) {
            // Case: More than 1 NS condition
            // Ignore UR even if NS
            $hasAV = $this->bvav_Types['av'] && $this->hasTreatmentO('av');
            $hasBV = $this->bvav_Types['bv'] && $this->hasTreatmentO('bv');

            if ($hasAV && $hasBV) {
                $this->bvavTypeKey = 'bav';
            } elseif ($hasAV) {
                $this->bvavTypeKey = 'av';
            } elseif ($hasBV) {
                $this->bvavTypeKey = 'bv';
            }


            // Notes logic
            if ($this->bvav_Types['ur'] && !$this->hasTreatmentO('ur')) $this->bvavur_notes2[] = 'ur1';
            if ($this->bvav_Types['ur'] && $this->hasTreatmentO('ur')) $this->bvavur_notes2[] = 'ur2';
            if ($this->bvav_Types['bv'] && !$this->hasTreatmentO('bv')) $this->bvavur_notes2[] = 'bv1';
            if ($this->bvav_Types['av'] && !$this->hasTreatmentO('av')) $this->bvavur_notes2[] = 'av1';
        } else {
            if ($this->bvav_Types['bv'] && $this->bvav_Types['ur'] && empty($this->bvavur_notes2)) {
                $bothrisk = $this->panel_risks['bv'] + $this->panel_risks['ur'];
                if ($bothrisk == 0) {
                    $this->bvavur_notes2[] = 'bv1';
                    $this->bvavur_notes2[] = 'ur1';
                    $this->bvavTypeKey = 'bv';
                }
            }
        }


        //get total
        if (!empty($this->bvavSubTypeKey)) {
            //nothing
        } else if (!empty($single_panel_risk_panel)) {
            if ($this->bvavTypeKey != 'bav' && !$this->use_new_calc_risk) {
                $this->panel_risks[$single_panel_risk_panel] = $this->vm_scores['lb'] * 0.66 + $this->vm_scores[$single_panel_risk_panel] * 0.34;
            }

            $this->bv_risk = $this->panel_risks[$single_panel_risk_panel];
            if ($this->debug) {
                $this->log .= "<br>Risk.= {$this->bv_risk}<br>";
            }
        } else if ($this->bvavTypeKey == 'av') {
            $this->bv_risk = $this->panel_risks['av'];
            if ($this->debug) {
                $this->log .= "<br>Risk = AV risk ={$this->bv_risk}<br>";
            }
        } else if ($this->bvavTypeKey == 'bv') {
            $this->bv_risk = $this->panel_risks['bv'];
            if ($this->debug) {
                $this->log .= "<br>Risk= BV risk= {$this->bv_risk}<br>";
            }
        } else if ($this->bvavTypeKey == 'ur') {
            $this->bv_risk = $this->panel_risks['ur'];
            if ($this->debug) {
                $this->log .= "<br>Risk= UR risk= {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['bvo']) > 0 && count($this->bv_bac_Ids['av']) > 0 && count($this->bv_bac_Ids['ur']) > 0) {
            //$bv_riskold = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['bv'] + $this->vm_scores['av'] + $this->vm_scores['ur']) * 0.113;

            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['all']) * 0.34;

            if ($this->debug) {
                // $this->log .= "<br>Risk(old)=LB x 0.66 + (BVM+AVM+URM)x0.113 = {$bv_riskold}<br>";
                $this->log .= "<br>Risk=LB x 0.66 + VM x 0.34 = {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['bvo']) > 0 && count($this->bv_bac_Ids['av']) > 0) {
            //$bv_riskold = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['bv'] + $this->vm_scores['av']) * 0.17;
            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['all']) * 0.34;
            if ($this->debug) {
                // $this->log .= "<br>Risk(old)=LB x 0.66 + (BVM+AVM)x0.17 = {$bv_riskold}<br>";
                $this->log .= "<br>Risk=LB x 0.66 + VM x 0.34 = {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['bvo']) > 0 && count($this->bv_bac_Ids['ur']) > 0) {
            //$bv_riskold = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['bv'] + $this->vm_scores['ur']) * 0.17;
            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['all']) * 0.34;
            if ($this->debug) {
                // $this->log .= "<br>Risk(old)=LB x 0.66 + (BVM+URM)x0.17 = {$bv_riskold}<br>";
                $this->log .= "<br>Risk=LB x 0.66 + VM x 0.34 = {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['av']) > 0 && count($this->bv_bac_Ids['ur']) > 0) {
            //$bv_riskold = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['av'] + $this->vm_scores['ur']) * 0.17;
            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + ($this->vm_scores['all']) * 0.34;
            if ($this->debug) {
                // $this->log .= "<br>Risk(old)=LB x 0.66 + (AVM+URM)x0.17 = {$bv_riskold}<br>";
                $this->log .= "<br>Risk=LB x 0.66 + VM x 0.34 = {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['ur']) > 0 && $this->bvavTypeKey == 'ur') {
            $this->bv_risk = $this->vm_scores['lb'] * 0.50 + $this->vm_scores['ur'] * 0.50;
            if ($this->debug) {
                $this->log .= "<br>Risk=LB x 0.50 + URM x 0.50 = {$this->bv_risk}<br>";
            }
        } else if (count($this->bv_bac_Ids['av']) > 0 && $this->bvavTypeKey == 'av') {
            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + $this->vm_scores['av'] * 0.34;
            if ($this->debug && $has_bauv) {
                $this->log .= "<br>Risk=LB x 0.66 + AVM x 0.34 = {$this->bv_risk}<br>";
            }
        } else {
            $this->bv_risk = $this->vm_scores['lb'] * 0.66 + $this->vm_scores['bv'] * 0.34;
            if ($this->debug && $has_bauv) {
                $this->log .= "<br>Risk=LB x 0.66 + BVMx0.34 = {$this->bv_risk}<br>";
            }
        }




        return $this->bv_risk;
    }

    function _runExcludePanelbyRatio()
    {
        if ($this->countBVType <= 1) {
            return;
        }
        $this->log .= "<br><b>run exclude panel by Ratio: </b><br>";


        $count_excluded = 0;

        foreach ($this->bvav_Types as $t => $hastype) {
            if ($hastype) {
                $risk = $this->panel_risks[$t];
                if ($this->highest_risk < $risk) {
                    $this->highest_panel = $t;
                    $this->highest_risk = $risk;
                }
                /*disable 1/11/2024
                if ($risk < 5) {
                    //exclude <5
                    $this->excludedPanels[$t] = $t;
                    $count_excluded++;
                    $this->log .= "excluded < 5: {$t} <br>";
                }*/

                if ($risk == 0) {
                    $this->excludedPanels[$t] = $t;
                    $count_excluded++;
                    $this->log .= "excluded =0: {$t} <br>";
                }
            }
        }

        if ($this->debug) {
            $this->log .= "Highest: " . $this->highest_panel . ' = ' . $this->highest_risk . '<br>';
        }

        if ($count_excluded == $this->countBVType) {
            //dont exclude highest
            unset($this->excludedPanels[$this->highest_panel]);

            $count_excluded--;
        }

        if ($this->countBVType > 1) {
            $ratios = array();
            foreach ($this->bvav_Types as $t => $hastype) {
                if ($hastype && $t != $this->highest_panel) {
                    $ratios[$t] = $this->panel_risks[$t] > 0 ? $this->highest_risk / $this->panel_risks[$t] : 0;

                    $this->log .= "Ratio {$this->highest_panel}/{$t} = {$ratios[$t]} <br>";
                }
            }

            foreach ($ratios as $t => $ratio) {
                if ($ratio >= 5 || ($ratio >= 3 && $ratio < 5 && $this->panel_risks[$t] < 20)) {
                    $this->excludedPanels[$t] = $t;
                    $count_excluded++;
                    $this->log .= "excluded ratio >= 5: {$t} <br>";
                }
            }
        }

        foreach ($this->excludedPanels as $t) {
            unset($this->bv_abxs[$t]);
        }
    }

    public function BVhasOther($type)
    {
        return count($this->bv_bac_Ids[$type . 'o']) > 0 ? 1 : 0;
    }

    public function get_BV_Sum($type)
    {
        $sum = 0;
        if ($type == 'bv') {
            $type = 'bvo';
        }
        foreach ($this->bv_bac_Ids[$type] as $bid) {
            $bat = $this->selectedBacterias[$bid];
            $sum += $bat->ra;
        }
        return $sum;
    }

    public function get_BV_total_load()
    {
        return $this->bv_total_load;
    }

    public function get_LB_total_load()
    {
        return $this->lb_total_load;
    }


    public function bv_get_abxs($type)
    {
        if (isset($this->excludedPanels[$type])) {
            return array();
        }
        if ($type == 'bv' && count($this->bv_bac_Ids['bvo']) == 0) {
            return array();
        }
        return $this->bv_abxs[$type];
    }

    public function get_Maker_Notes()
    {
        $notes = '';
        if ($this->enable_AMR && count($this->selectedMarkers) > 0) {
            global $asset_url;
            $mkrnames = array();
            foreach ($this->selectedMarkers as $mkid => $mkr) {
                $mkrnames[] = $mkr->resistance_marker;
            }
            $notes .= '<table class="noborder mgtb10 thawngabxtbl"><tr><td class="thawngabx red">!</td>';
            $notes .= '<td>Resistance markers detected: ' . implode(', ', $mkrnames) . '. Their impact on treatment choices has been factored into this report.</td></tr></table>';
        }
        return $notes;
    }

    public function get_Type_Notes()
    {
        $notes = '';


        foreach ($this->bvavur_notes2 as $nk) {
            $bt = substr($nk, 0, 2);
            $btnum = substr($nk, 2, 1);
            $notearr = $this->getNotes('bau', $bt);
            if (!empty($notearr[$btnum])) {
                $notes .= '<table class="urnotes"><tr><td class="urnoteicon"><span class="abxroundcic abxround_' . $bt . '"></span></td>';
                $notes .= '<td><span class="bold bold2">Note:</span> ' . $notearr[$btnum] . '</td></tr></table>';
            }
        }

        $notes .= $this->get_Maker_Notes();

        return $notes;
    }

    public function hasTreatmentO($type)
    {
        if (isset($this->excludedPanels[$type])) {
            return false;
        }

        return $this->bvav_Types[$type] && isset($this->bv_abxs[$type]) && !empty($this->bv_abxs[$type]);
    }

    public function isExcludedPanel($type)
    {
        return isset($this->excludedPanels[$type]);
    }

    public function getTextbyCondition($ctype, $vl1 = null, $vl2 = null, $vl3 = null, $vl4 = null)
    {
        if (!$this->NotTestAV) {
            if ($ctype == 'bv') $ctype = 'bv2';
            if ($ctype == 'ur') $ctype = 'ur2';
        }
        if ($ctype == 'yeast') {
            $vl1 = null;
            $vl4 = null;
            //only 2:yeast , 3:risk
            if ($this->yeast_uti_wound == 'uti') {
                $ctype = 'utiyeast';
            } else if ($this->yeast_uti_wound == 'wound') {
                $ctype = 'woundyeast';
            } else if ($this->yeast_uti_wound == 'ei') {
                $ctype = 'eiyeast';
            } else if ($this->yeast_uti_wound == 'nail') {
                $ctype = 'nailyeast';
            } else {
                $vl1 = $this->vm_scores['lbry']; //LBRY
                $vl4 = $this->vm_scores['yeast']; //CVM
            }
        } else if ($ctype == 'bvm') {
            //only 1: Score
            $vl2 = null;
            $vl3 = null;
            $vl4 = null;
        } else if ($ctype == 'bav') {
            //only 1: lac , 3:risk, 4:bload
            $vl2 = null;
        }

        $arr = parent::getTextbyCondition($ctype, $vl1, $vl2, $vl3, $vl4);


        return $arr;
    }

    public function get_analysis_Summary()
    {
        $arrs = array();
        $pregnants = array(
            'av' => array('Clindamycin', 'Clindamycin'),
            'bv' => array('Clindamycin', 'Clindamycin'),
            'ur' => array('Doxycycline', 'Azythromycin')
        );

        $allvmless10 = true;
        foreach ($this->bvav_Types as $t => $hastype) {
            if ($hastype && $this->vm_scores[$t] >= 10) {
                $allvmless10 = false;
            }
        }

        foreach ($this->bvav_Types as $t => $hastype) {

            if ($hastype && isset($this->bv_abxs[$t]) && !isset($this->excludedPanels[$t])) {
                $risk       = $this->panel_risks[$t];

                $risktextshort  = 'LOW'; //10-30
                if ($risk < 10) {
                    $risktextshort = 'VERY LOW';
                } else if ($risk > 30 && $risk < 70) {
                    $risktextshort      = 'MED';
                    if ($allvmless10) {
                        $this->before_panel_riskey = 'MED';
                        $risktextshort   = 'LOW';
                    }
                } else if ($risk >= 70) {
                    $risktextshort      = 'HIGH';
                    if ($allvmless10) {
                        $this->before_panel_riskey = 'HIGH';
                        $risktextshort   = 'MED';
                    }
                }


                $risktext           = 'Low Risk'; //low
                $comment            = 'Assess/Monitor'; //low

                if ($risktextshort == 'MED') {
                    $highest    = $this->style == 'v' ? $this->highest_panel == $t : $this->score_highest['highest'] == $t;
                    $risktext   = 'Med Risk';
                    $comment    = $highest ? 'Prioritize Treatment' : 'Correlate clinically';
                } else if ($risktextshort == 'HIGH') {
                    $highest    = $this->style == 'v' ? $this->highest_panel == $t : $this->score_highest['highest'] == $t;
                    $risktext   = 'High Risk';
                    $comment    = $highest ? 'Prioritize Treatment' : 'Consider treatment';
                } else if ($risktextshort == 'VERY LOW') {
                    $risktext   = 'Very Low Risk';
                    $comment    = 'Assess/Monitor';
                }


                $arrs[$t] = array(
                    'risk'          => $risk,
                    'risktextshort' => $risktextshort,
                    'risktext'      => $risktext,
                    'score_highest' => $this->score_highest[$t],
                    'comment'       => $comment,
                    'non-pregnant'  => $pregnants[$t][0],
                    'pregnant'      => $pregnants[$t][1],
                );
            }
        }
        uasort($arrs, function ($a, $b) {
            if ($a['risk'] == $b['risk']) {
                return $a['score_highest'] > $b['score_highest'] ? -1 : 1;
            }
            return $a['risk'] > $b['risk'] ? -1 : 1;
        });
        return $arrs;
    }

    public function get_bv_bacteria_abxs($bid = 0, $subpanel = '')
    {
        global $wpdb, $ci_cache_queries;
        if (!is_array($ci_cache_queries)) $ci_cache_queries = array();

        if ($bid === 0) $bid = 'bv';

        $where  = " FIND_IN_SET('{$bid}',bacteriaIDs) ";
        if ($bid == 'bv' && $this->abxByBAVSCore) {
            foreach ($this->bv_bac_Ids['bvo'] as $bvbid) {
                $where  .= " OR FIND_IN_SET('{$bvbid}',bacteriaIDs) ";
            }
        }

        if ($subpanel == 'yeast' && in_array($this->yeast_uti_wound, array('uti', 'wound', 'ei', 'nail'))) {
            $where .= " and bv_panel_type = '{$this->yeast_uti_wound}' ";
        } else if (empty($subpanel) || in_array($subpanel, array('bv', 'av', 'ur', 'yeast', 'sti', 'opportunistic'))) {
            $where .= " and (bv_panel_type = 'bv' or bv_panel_type = '' )";
        } else {
            $where .= " and bv_panel_type = '{$subpanel}' ";
        }

        $sql    = "select * 
        from ci_abx_bv_abx 
        where {$where}
        order by ID asc";
        if (isset($_GET['debug'])) {
            echo $sql . '<br>';
        }
        $sqlkey = md5($sql);
        if (isset($ci_cache_queries[$sqlkey])) {
            return $ci_cache_queries[$sqlkey];
        }
        $abxs   =  $wpdb->get_results($sql);
        $ci_cache_queries[$sqlkey] = $abxs;
        return $abxs;
    }

    function _buildbavAbxs()
    {
        $this->bv_abxs['bav'] = array();
        $inarr = array();
        $inum = 0;
        foreach ($this->bvav_Types as $t => $hastype) {

            if ($hastype && isset($this->bv_abxs[$t]) && !isset($this->excludedPanels[$t])) {
                $subabxs = &$this->bv_abxs[$t];

                foreach ($subabxs as &$abx1) {
                    $abxkey = strtolower(trim($abx1->abx));

                    if (!isset($this->bv_abx_types[$abxkey])) {
                        $this->bv_abx_types[$abxkey] = array();
                    }
                    if (!in_array($t, $this->bv_abx_types[$abxkey])) {
                        $this->bv_abx_types[$abxkey][] = $t;
                    }

                    if (!isset($abx1->types) || !is_array($abx1->types)) $abx1->types = array();
                    if (!isset($inarr[$abxkey])) {
                        $abx1->types    = array($t);
                        $this->bv_abxs['bav'][$inum]    = $abx1;
                        $inarr[$abxkey] = $inum;
                        $inum++;
                    } else {
                        if (!in_array($t, $abx1->types)) {
                            $abx1->types[] = $t;
                        }
                        if (!in_array($t, $this->bv_abxs['bav'][$inarr[$abxkey]]->types)) {
                            $this->bv_abxs['bav'][$inarr[$abxkey]]->types[] = $t;
                        }
                    }
                }
            }
        }

        usort($this->bv_abxs['bav'], function ($a, $b) {
            $numa = count($a->types);
            $numb = count($b->types);
            if ($numa == $numb) return 0;
            return  $numa > $numb ? -1 : 1;
        });
    }

    function _loadVAMR()
    {
        global $wpdb;
        $this->log .= "<br><b>Loading V-AMR data...</b><br>";

        $sql = "select amr.*,
        abx.abx
        from ci_abx_v_amr amr
        JOIN ci_abx_bv_abx abx ON amr.anti_ID = abx.ID
        ";
        $rs = $wpdb->get_results($sql);
        $this->log .= $wpdb->last_error;
        foreach ($rs as $r) {
            $bacids = explode(',', $r->bacteriaIDs);
            foreach ($bacids as $bid) {
                if (empty($bid)) continue;
                $abxkey = strtolower(trim($r->abx));
                $rowkey = $r->marker_ID . '_' . $bid . '_' . $abxkey;

                $this->va_amrs[$rowkey] = $r->ntr;
            }
        }
        $this->positie_mk_ids = array();
        $this->negative_mk_ids = array();
        foreach ($this->selectedMarkers as $mkid => $mkarr) {
            $this->positie_mk_ids[$mkid] = $mkid;
        }
        foreach ($this->limit_markers as $mkid) {
            if (!isset($this->positie_mk_ids[$mkid])) {
                $this->negative_mk_ids[$mkid] = $mkid;
            }
        }

        $this->log .= "V-AMR entries loaded: " . count($this->va_amrs) . "<br>";
        if (isset($_GET['debug2'])) {

            var_dump($this->va_amrs);
            echo '<br>';
        }
    }

    function _loadBvABXAntibiogram()
    {
        global $wpdb, $ci_cache_queries;
        $bvtypes = array('bv' => 'bv', 'av' => 'av', 'ur' => 'ur');
        $abxcountpanels = array();

        /*
        //remove 3/11/2024
        if ($this->vm_scores['bv'] > $this->vm_scores['ur']) {
            unset($this->bv_abxs['ur']);
        }*/

        foreach ($bvtypes as $t => $bv1) {
            if (!isset($this->bv_abxs[$t])) {
                unset($bvtypes[$t]);
            }
        }

        $sql = "select * from ci_abx_bvav_antibiogram";
        $qkey = md5($sql);

        if (!isset($ci_cache_queries[$qkey])) {
            $rs = $wpdb->get_results($sql);
            $ci_cache_queries[$qkey] = $rs;
        } else {
            $rs = $ci_cache_queries[$qkey];
        }


        foreach ($rs as $r) {
            if (!isset($this->selectedBacterias[$r->bacteria_ID])) {
                continue;
            }
            $bat = $this->selectedBacterias[$r->bacteria_ID];

            if (!isset($bvtypes[$bat->bv_type])) {
                continue;
            }

            $r->abxkey = strtolower(trim($r->abx));
            if (!isset($this->bv_abx_scores[$r->bacteria_ID])) {
                $this->bv_abx_scores[$r->bacteria_ID] = array();
            }

            //V-AMR adjustment
            if ($this->enable_AMR) {


                foreach ($this->negative_mk_ids as $mkid) {
                    $rowkey = $mkid . '_' . $r->bacteria_ID . '_' . $r->abxkey;
                    if (isset($_GET['debug2'])) {
                        echo '<br>';
                        var_dump($rowkey);
                    }

                    if (isset($this->va_amrs[$rowkey]) && $this->va_amrs[$rowkey] > 0) {
                        $r->oldscore = $r->score;
                        $r->score  += (100 - $r->score) * $this->va_amrs[$rowkey] / 100;
                        $this->log .= "V-AMR applied: Bacteria {$bat->name}, Marker {$this->marker_names[$mkid]}, ABX {$r->abx} => {$r->oldscore} => {$r->score}<br>";
                    }
                }

                foreach ($this->positie_mk_ids as $mkid) {
                    $rowkey = $mkid . '_' . $r->bacteria_ID . '_' . $r->abxkey;
                    if (isset($_GET['debug2'])) {
                        echo '<br>';
                        var_dump($rowkey);
                    }

                    if (isset($this->va_amrs[$rowkey])) {
                        $r->score  = 0;
                        $this->log .= "V-AMR applied: Bacteria {$bat->name}, Marker {$this->marker_names[$mkid]}, ABX {$r->abx} => 0<br>";
                    }
                }
            }

            $x = $r->score * $bat->percent_panel / 100;
            $this->bv_abx_scores[$r->bacteria_ID][$r->abxkey] = array(
                'name'  => $r->abx,
                'score' => $r->score,
                'x'     => $x,
            );

            if (!isset($this->bv_abx_scores[$bat->bv_type])) {
                $this->bv_abx_scores[$bat->bv_type] = array();
            }
            if (!isset($this->bv_abx_scores[$bat->bv_type][$r->abxkey])) {
                $this->bv_abx_scores[$bat->bv_type][$r->abxkey] = array(
                    'name' => $r->abx,
                    'x' => 0,
                );
            }
            $this->bv_abx_scores[$bat->bv_type][$r->abxkey]['x'] += $x;

            if (!isset($abxcountpanels[$r->abxkey])) {
                $abxcountpanels[$r->abxkey] = array();
            }
            if ($this->bv_abx_scores[$bat->bv_type][$r->abxkey]['x'] >= 50) {
                $abxcountpanels[$r->abxkey][$bat->bv_type] = $this->bv_abx_scores[$bat->bv_type][$r->abxkey]['x'];
            }
        }


        if ($this->yeast_version == 2) {
            $bvtypes['yeast'] = 'yeast';

            $yang_tbl = !empty($this->yeast_uti_wound) ? 'ci_abx_yeast_' . $this->yeast_uti_wound . '_antibiogram' : 'ci_abx_yeast_antibiogram';
            $rs = $wpdb->get_results("select * from {$yang_tbl}");

            foreach ($rs as $r) {
                if (!isset($this->selectedBacterias[$r->bacteria_ID])) {
                    continue;
                }

                $bat = $this->selectedBacterias[$r->bacteria_ID];

                if ($bat->bv_type !== 'yeast' || !isset($bvtypes['yeast'])) {
                    continue;
                }

                $r->abxkey = strtolower(trim($r->abx));
                if (!isset($this->bv_abx_scores[$r->bacteria_ID])) {
                    $this->bv_abx_scores[$r->bacteria_ID] = array();
                }

                //V-AMR adjustment
                if ($this->enable_AMR) {

                    foreach ($this->negative_mk_ids as $mkid) {
                        $rowkey = $mkid . '_' . $r->bacteria_ID . '_' . $r->abxkey;
                        if (isset($_GET['debug2'])) {
                            echo '<br>';
                            var_dump($rowkey);
                        }

                        if (isset($this->va_amrs[$rowkey]) && $this->va_amrs[$rowkey] > 0) {
                            $r->oldscore = $r->score;
                            $r->score  += (100 - $r->score) * $this->va_amrs[$rowkey] / 100;
                            $this->log .= "V-AMR applied: Bacteria {$bat->name}, Marker {$this->marker_names[$mkid]}, ABX {$r->abx} => {$r->oldscore} => {$r->score}<br>";
                        }
                    }
                    foreach ($this->positie_mk_ids as $mkid) {
                        $rowkey = $mkid . '_' . $r->bacteria_ID . '_' . $r->abxkey;
                        if (isset($_GET['debug2'])) {
                            echo '<br>';
                            var_dump($rowkey);
                        }

                        if (isset($this->va_amrs[$rowkey])) {
                            $r->score  = 0;
                            $this->log .= "V-AMR applied: Bacteria {$bat->name}, Marker {$this->marker_names[$mkid]}, ABX {$r->abx} => 0<br>";
                        }
                    }
                }

                $x = $r->score * $bat->percent_panel / 100;

                $abx_min_risk = isset($this->bv_abxs['yeast_minrisk'][$r->abxkey]) ? $this->bv_abxs['yeast_minrisk'][$r->abxkey] : 0;



                $this->bv_abx_scores[$r->bacteria_ID][$r->abxkey] = array(
                    'name'  => $r->abx,
                    'score' => $r->score,
                    'x'     => $x,
                );

                if (!isset($this->bv_abx_scores['yeast'])) {
                    $this->bv_abx_scores['yeast'] = array();
                }

                if (!isset($this->bv_abx_scores['yeast'][$r->abxkey])) {
                    $this->bv_abx_scores['yeast'][$r->abxkey] = array(
                        'name' => $r->abx,
                        'x' => 0,
                        'lowrisk' => 0,
                        'passrisk' => 0,
                    );
                }

                if ($abx_min_risk > 0 && $bat->risk < $abx_min_risk) {
                    $this->bv_abx_scores['yeast'][$r->abxkey]['lowrisk']++;
                } else {
                    $this->bv_abx_scores['yeast'][$r->abxkey]['passrisk']++;
                }


                $this->bv_abx_scores['yeast'][$r->abxkey]['x'] += $x;

                if (!isset($abxcountpanels[$r->abxkey])) {
                    $abxcountpanels[$r->abxkey] = array();
                }

                if ($this->bv_abx_scores['yeast'][$r->abxkey]['x'] >= 50) {
                    $abxcountpanels[$r->abxkey]['yeast'] = $this->bv_abx_scores['yeast'][$r->abxkey]['x'];
                }
            }

            //check exclude
            if (isset($this->bv_abx_scores['yeast']) && is_array($this->bv_abx_scores['yeast'])) {
                foreach ($this->bv_abx_scores['yeast'] as $abxkey => $arr) {

                    if ($arr['lowrisk'] > 0 && $arr['passrisk'] == 0) {
                        $this->bv_abx_scores['yeast'][$abxkey]['x'] = 0;
                    }
                }
            }
        }

        if (isset($_GET['debug'])) {
            var_dump($abxcountpanels);
        }


        //put abxs
        $this->bv_abxs['bv_gram'] = array();
        $this->bv_abxs['bv_gram_ex'] = array();
        $this->bv_abxs['av_gram'] = array();
        $this->bv_abxs['ur_gram'] = array();
        $this->bv_abxs['yeast_gram'] = array();


        foreach ($bvtypes as $t) {
            if (isset($this->bv_abx_scores[$t])) {
                foreach ($this->bv_abx_scores[$t] as $abxkey => $arr) {
                    $passrule = false;
                    if ($arr['x'] >= 80) { //rule 2
                        $passrule = true;
                    } else if (($arr['x'] >= 50  && count($abxcountpanels[$abxkey]) >= 2)) {
                        //rule 3
                        $pass80 = false;
                        foreach ($abxcountpanels[$abxkey] as $x) {
                            if ($x >= 80) {
                                $pass80 = true;
                                break;
                            }
                        }

                        if ($pass80) {
                            $passrule = true;
                        }
                    }

                    if ($passrule) {
                        $this->bv_abxs[$t . '_gram'][$abxkey] = $arr['name'];
                    } else if ($t == 'bv') {
                        $this->bv_abxs[$t . '_gram_ex'][$abxkey] = $arr['name'];
                    }
                }

                //check empty
                if (count($this->bv_abxs[$t . '_gram']) == 0 && isset($this->bv_abxs[$t . '_gram_ex']) && is_array($this->bv_abxs[$t . '_gram_ex'])) {
                    foreach ($this->bv_abxs[$t . '_gram_ex'] as $abxkey => $nameabx) {
                        $arr = $this->bv_abx_scores[$t][$abxkey];
                        if ($arr['x'] >= 60) {
                            unset($this->bv_abxs[$t . '_gram_ex'][$abxkey]);
                            $this->bv_abxs[$t . '_gram'][$abxkey] = $arr['name'];
                        }
                    }
                }


                if ($t == 'yeast') {
                    //tr_get_option('ci_pdf_data', 'new_abx_yeast_separate_groups') || defined('TRHUB_FILE')
                    if (false) {
                        $sepgroups = empty($this->bv_abxs[$t]);
                        if (!$sepgroups && isset($this->bv_bac_Ids['yeast']) && is_array($this->bv_bac_Ids['yeast']) && count($this->bv_bac_Ids['yeast']) > 1) {
                            $dif_risks = array();
                            foreach ($this->bv_bac_Ids['yeast'] as $bid) {
                                $bat = $this->selectedBacterias[$bid];
                                if (!in_array($bat->risk, $dif_risks)) {
                                    $dif_risks[] = $bat->risk;
                                }
                            }
                            if (count($dif_risks) > 1) {
                                $sepgroups = true;
                            }
                        }
                        if ($sepgroups) {
                            $this->bv_abxs[$t . '_gram'] = array();
                        } else if ($t == 'yeast' && empty($this->bv_abxs[$t])) {
                            $this->bv_abxs[$t . '_gram'] = array();
                        }
                    } else {
                        if ($t == 'yeast' && empty($this->bv_abxs[$t])) {
                            $this->bv_abxs[$t . '_gram'] = array();
                        }
                    }
                }

                if ($this->yeast_uti_wound == 'ei' && $t == 'yeast') {
                    $this->bv_abxs[$t . '_gram'] = array();
                }


                if (in_array($this->yeast_uti_wound, array('uti', 'wound', 'ei', 'nail')) && count($this->bv_abxs[$t . '_gram']) == 0) {
                    $this->yeast_uti_wound_abx_groups = array();
                    $bids = isset($this->bv_bac_Ids['yeast']) ? $this->bv_bac_Ids['yeast'] : array();
                    $bac_groups = array(
                        // 'Candida albicans' => 'C. albicans',
                        'Epidermophyton floccosum' => 'E. floccosum, T. rubrum',
                        'Trichophyton rubrum' => 'E. floccosum, T. rubrum',
                    );
                    $group_names = array();


                    foreach ($bids as $bid) {
                        $bat = $this->selectedBacterias[$bid];
                        if (stripos($bat->name, 'candida') !== false) {
                            $groupkey = 'Candida';
                        } else {
                            $groupkey = $bat->risk;
                            if (!isset($group_names[$groupkey]) || !is_array($group_names[$groupkey])) {
                                $group_names[$groupkey] = array();
                            }
                            $group_names[$groupkey][] = $bat->abrev;
                        }


                        if (!isset($this->yeast_uti_wound_abx_groups[$groupkey])) {
                            $this->yeast_uti_wound_abx_groups[$groupkey] = array(
                                'bacids'    => array(),
                                'name'      => $groupkey,
                                'abxs'      => array(),
                                'max_risk'  => 0,
                            );

                            foreach ($bat->abxs as $abxobj) {

                                $abxkey = strtolower(trim($abxobj->abx));
                                $abxobj->x = $this->bv_abx_scores['yeast'][$abxkey]['x'];
                                $abx_min_risk = $abxobj->min_risk;

                                if ($abx_min_risk > 0 && $abx_min_risk > $bat->risk) continue;
                                if (!isset($this->bv_abxs['yeast_gram_ex'][$abxkey])) {
                                    $this->yeast_uti_wound_abx_groups[$groupkey]['abxs'][] = $abxobj;
                                }
                            }
                        } else {
                            $preabxs = $this->yeast_uti_wound_abx_groups[$groupkey]['abxs'];
                            $this->yeast_uti_wound_abx_groups[$groupkey]['abxs'] = array();
                            foreach ($preabxs as $abxobj) {
                                $hascommon = false;
                                foreach ($bat->abxs as $abxobj2) {
                                    if (strtolower($abxobj->abx) == strtolower($abxobj2->abx)) {
                                        $hascommon = true;
                                        break;
                                    }
                                }
                                if ($hascommon) {
                                    $this->yeast_uti_wound_abx_groups[$groupkey]['abxs'][] = $abxobj;
                                }
                            }
                        }

                        $this->yeast_uti_wound_abx_groups[$groupkey]['bacids'][] = $bid;
                        $this->yeast_uti_wound_abx_groups[$groupkey]['max_risk'] = max($this->yeast_uti_wound_abx_groups[$groupkey]['max_risk'], $bat->risk);
                    }

                    foreach ($group_names as $groupkey => $abs) {
                        $this->yeast_uti_wound_abx_groups[$groupkey]['name'] = implode(', ', $abs);
                    }


                    //sort group abx
                    foreach ($this->yeast_uti_wound_abx_groups as $groupkey => $abs) {
                        if (
                            is_array($this->yeast_uti_wound_abx_groups[$groupkey]['abxs'])
                            && count($this->yeast_uti_wound_abx_groups[$groupkey]['abxs']) > 0
                        ) {
                            uasort($this->yeast_uti_wound_abx_groups[$groupkey]['abxs'], function ($a, $b) {

                                // echo $a->abx . ' = ' . $a->x . ' => ' . $b->abx . ' = ' . $b->x . '<br>';
                                if ($a->x == $b->x) return 0;
                                return $a->x > $b->x ? -1 : 1;
                            });
                        }
                    }

                    uasort($this->yeast_uti_wound_abx_groups, function ($a, $b) {
                        if ($a['max_risk'] == $b['max_risk']) return 0;
                        return $a['max_risk'] > $b['max_risk'] ? -1 : 1;
                    });
                }
                //filter abxes
                $oldabxs = $this->bv_abxs[$t];

                $this->bv_abxs[$t] = array();

                foreach ($oldabxs as $a) {
                    $abxkey = strtolower(trim($a->abx));

                    $a->x = isset($this->bv_abx_scores[$t][$abxkey]['x']) ? $this->bv_abx_scores[$t][$abxkey]['x'] : 0;

                    if (isset($this->bv_abxs[$t . '_gram'][$abxkey])) {
                        $this->bv_abxs[$t][] = $a;
                    } else if ($t == 'bv' && stripos($a->fda, $t) !== false && isset($this->bv_abxs[$t . '_gram_ex'][$abxkey])) {
                        $a->excluded = true;
                        $this->bv_abxs[$t][] = $a;
                    }
                }
            }

            if (count($this->bv_abxs[$t]) > 0) {
                usort($this->bv_abxs[$t], function ($a, $b) {
                    if (!isset($a->x)) $a->x = 0;
                    if (!isset($b->x)) $b->x = 0;
                    //   echo $a->abx.' = '.$a->x.' => '.$b->abx.' = '.$b->x.'<br>';
                    if ($a->x == $b->x) return 0;
                    return $a->x > $b->x ? -1 : 1;
                });
            }
        }
    }

    public function getAbxtypes($abx)
    {
        $abxkey = strtolower(trim($abx));
        return isset($this->bv_abx_types[$abxkey]) ? $this->bv_abx_types[$abxkey] : array();
    }

    public function build_colors()
    {
        static $abx_color_num;

        if (empty($abx_color_num)) {
            $abx_color_num = 1;
        }

        $types = array('bav', 'yeast');

        $bids = isset($this->bv_bac_Ids['sti']) ? $this->bv_bac_Ids['sti'] : array();
        foreach ($bids as $bid) {
            $types[]  = $this->selectedBacterias[$bid];
        }

        foreach ($types as $t) {
            $abxs = is_object($t) ? $t->abxs : $this->bv_abxs[$t];

            foreach ($abxs as $r) {
                $abxkey = strtolower(trim($r->abx));

                if (!isset($this->bv_abx_colors[$abxkey])) {
                    $this->bv_abx_colors[$abxkey] = array(
                        'count' => 0,
                        'color' => 0,
                    );
                }
                $this->bv_abx_colors[$abxkey]['count']++;

                if ($this->bv_abx_colors[$abxkey]['count'] >= 2) {
                    if ($this->bv_abx_colors[$abxkey]['color'] == 0) {
                        $this->bv_abx_colors[$abxkey]['color'] = $abx_color_num;
                        $abx_color_num++;
                    }
                }
            }
        }
    }

    public function get_bv_color($abx)
    {
        $abxkey = strtolower(trim($abx));
        $colornum = 0;

        if (isset($this->bv_abx_colors[$abxkey])) {
            $colornum = $this->bv_abx_colors[$abxkey]['color'];
        }
        return $colornum;
    }



    public function buildscores()
    {
        $this->checkNotTestAV();
        //sort Bacteria by CT
        foreach (array('av', 'bv', 'bvo', 'ur', 'lb', 'lbi') as $t) {
            if (count($this->bv_bac_Ids[$t]) > 1) {
                //var_dump($this->bv_bac_Ids[$t]);echo '<br>';
                uasort($this->bv_bac_Ids[$t], function ($bid1, $bid2) {
                    $bat1 = $this->selectedBacterias[$bid1];
                    $bat2 = $this->selectedBacterias[$bid2];

                    if ($bat1->conc == $bat2->conc) return 0;
                    return $bat1->conc < $bat2->conc ? -1 : 1;
                });
                //var_dump($this->bv_bac_Ids[$t]);echo '<br><br>';
            }
        }


        $this->bv_abxs['bv'] = $this->get_bv_bacteria_abxs('bv');
        $this->bv_abxs['ur'] = array();
        $this->bv_abxs['av'] = array();
        $this->bv_abxs['yeast'] = array();

        $sumra = $this->bv_sum_ra['bv'] + $this->bv_sum_ra['ur'] + $this->bv_sum_ra['av'];

        //load BV
        $this->vm_scores['bv_percent'] = 0;
        foreach ($this->bv_bac_Ids['bv'] as $bid) {
            $bat = $this->selectedBacterias[$bid];
            $bat->ra_percent = number_format($bat->ra / $sumra  * 100, 2);
            if (in_array($bid, $this->bv_bac_Ids['bvo'])) {
                $this->vm_scores['bv_percent'] += $bat->ra_percent;
            }

            if (!in_array($bid, $this->bv_bac_Ids['lb'] + $this->bv_bac_Ids['lbi'])) {
                $bat->percent_panel = $this->total_loadbytypes['bv'] > 0 ? ($bat->load / $this->total_loadbytypes['bv']) * 100 : 0;
            }


            $this->bv_total_load += $bat->load;
        }

        //load UR
        $this->vm_scores['ur_percent'] = 0;
        if (count($this->bv_bac_Ids['ur']) > 0) {
            $abxs_tmp = array();
            foreach ($this->bv_bac_Ids['ur'] as $bid) {
                $bat = $this->selectedBacterias[$bid];
                $bat->ra_percent = number_format($bat->ra / $sumra  * 100, 2);
                $this->vm_scores['ur_percent'] += $bat->ra_percent;
                $bat->percent_panel = ($bat->load / $this->total_loadbytypes['ur']) * 100;

                $this->bv_total_load += $bat->load;

                //abx
                foreach ($bat->abxs as $r) {
                    $abxkey = strtolower(trim($r->abx));
                    if (!isset($abxs_tmp[$abxkey])) {
                        $r->count = 0;
                        $abxs_tmp[$abxkey] = $r;
                    }
                    $abxs_tmp[$abxkey]->count++;
                }
            }

            foreach ($abxs_tmp as $r) {
                if ($this->abxByBAVSCore) {
                    $this->bv_abxs['ur'][] = $r;
                } else {
                    if ($r->count >= count($this->bv_bac_Ids['ur'])) {
                        $this->bv_abxs['ur'][] = $r;
                    }
                }
            }
        }

        //load AV
        $this->vm_scores['av_percent'] = 0;
        if (count($this->bv_bac_Ids['av']) > 0) {
            $abxs_tmp = array();
            foreach ($this->bv_bac_Ids['av'] as $bid) {
                $bat = $this->selectedBacterias[$bid];
                $bat->ra_percent = number_format($bat->ra / $sumra  * 100, 2);
                $this->vm_scores['av_percent'] += $bat->ra_percent;
                $bat->percent_panel = ($bat->load / $this->total_loadbytypes['av']) * 100;
                $this->bv_total_load += $bat->load;

                //abx
                foreach ($bat->abxs as $r) {
                    $abxkey = strtolower(trim($r->abx));
                    if (!isset($abxs_tmp[$abxkey])) {
                        $r->count = 0;
                        $abxs_tmp[$abxkey] = $r;
                    }
                    $abxs_tmp[$abxkey]->count++;
                }
            }

            foreach ($abxs_tmp as $r) {

                if ($this->abxByBAVSCore) {
                    $this->bv_abxs['av'][] = $r;
                } else {
                    if ($r->count >= count($this->bv_bac_Ids['av'])) {
                        $this->bv_abxs['av'][] = $r;
                    }
                }
            }
        }

        //load yeast
        if (count($this->bv_bac_Ids['yeast']) > 0) {
            $abxs_tmp = array();
            foreach ($this->bv_bac_Ids['yeast'] as $bid) {
                $bat = $this->selectedBacterias[$bid];
                $bat->ra_percent = $this->bv_sum_ra['yeast'] > 0 ? number_format($bat->ra / $this->bv_sum_ra['yeast']  * 100, 2) : 0;
                $bat->percent_panel = $this->total_loadbytypes['yeast'] > 0 ? ($bat->load / $this->total_loadbytypes['yeast']) * 100 : 0;
                $this->yeast_total_load += $bat->load;
                foreach ($bat->abxs as $r) {
                    $abxkey = strtolower(trim($r->abx));
                    if (!isset($abxs_tmp[$abxkey])) {
                        $r->count = 0;
                        $abxs_tmp[$abxkey] = $r;
                    } else {
                        $oldabx = $abxs_tmp[$abxkey];
                        if ($r->min_risk > $abxs_tmp[$abxkey]->min_risk) {
                            $r->count = $abxs_tmp[$abxkey]->count;
                            $abxs_tmp[$abxkey] = $r;
                        }
                        $abxs_tmp[$abxkey]->dose1 = !empty($r->dose1) ? $r->dose1 : $oldabx->dose1;
                        $abxs_tmp[$abxkey]->dose2 = !empty($r->dose2) ? $r->dose2 : $oldabx->dose2;
                        $abxs_tmp[$abxkey]->dose3 = !empty($r->dose3) ? $r->dose3 : $oldabx->dose3;
                    }
                    $abxs_tmp[$abxkey]->count++;
                }
            }

            foreach ($this->bv_bac_Ids['yeast'] as $bid) {
                $bat = $this->selectedBacterias[$bid];
                $bat->load_percent = $this->yeast_total_load > 0 ? $bat->load / $this->yeast_total_load * 100 : 0;

                if ($this->debug) {
                    $this->log .= $bat->name . ': load=' . $bat->load . ' =>  %Load = ' . $bat->load_percent;
                    $this->log .= '<br>';
                }
            }

            foreach ($abxs_tmp as $r) {
                if ($r->count >= count($this->bv_bac_Ids['yeast'])) {
                    $r->abxkey = strtolower(trim($r->abx));
                    $this->bv_abxs['yeast'][] = $r;
                    $this->bv_abxs['yeast_minrisk'][$r->abxkey] = $r->min_risk;
                }
            }
        }

        $this->bvav_Types = array();

        $this->bvav_Types['bv'] = is_array($this->bv_bac_Ids['bvo']) && count($this->bv_bac_Ids['bvo']) > 0;
        $this->bvav_Types['ur'] = is_array($this->bv_bac_Ids['ur']) && count($this->bv_bac_Ids['ur']) > 0;
        $this->bvav_Types['av'] = is_array($this->bv_bac_Ids['av']) && count($this->bv_bac_Ids['av']) > 0;

        if (!$this->bvav_Types['bv'] && !$this->bvav_Types['av'] && !$this->bvav_Types['ur'] && !empty($this->bv_bac_Ids['lbi'])) {
            $this->bvav_Types['bv'] = true;
        }

        $this->countBVType = 0;
        if ($this->bvav_Types['bv']) {
            $this->countBVType++;
        }
        if ($this->bvav_Types['ur']) {
            $this->countBVType++;
        }
        if ($this->bvav_Types['av']) {
            $this->countBVType++;
        }


        $this->bvavTypeKey            = 'bv';
        if ($this->bvav_Types['av'] && !$this->bvav_Types['bv']) {
            $this->bvavTypeKey = 'av';
        } else if ($this->bvav_Types['ur'] && !$this->bvav_Types['bv']) {
            $this->bvavTypeKey = 'ur';
        }

        if ($this->bvav_Types['bv'] && $this->bvav_Types['av']) {
            $this->bvavTypeKey = 'bav';
        } else if ($this->bvav_Types['bv'] && $this->bvav_Types['ur']) {
            $this->bvavTypeKey = 'bav';
        } else if ($this->bvav_Types['ur'] && $this->bvav_Types['av']) {
            $this->bvavTypeKey = 'bav';
        }


        $this->_cal_LB_Percent();


        $this->_cal_BV_Risk();



        if ($this->abxByBAVSCore) {
            if ($this->enable_AMR) {
                $this->_loadVAMR();
            }
            $this->_loadBvABXAntibiogram();
        }



        $this->_buildbavAbxs();

        $this->build_colors();
    }

    function build_all_scores()
    {

        $updating_data  = get_option('ci_abx_updating_data', false);

        if ($updating_data) {
            ob_get_clean();
            $this->log .= 'Syncing Data. Please try later!';
            exit;
        }
    }

    public function get_log()
    {
        include(dirname(__FILE__) . '/../reports/abx/abx_debug_bv_table.php');
        return $this->log;
    }
}
