<?php
class CI_ABX_V1
{
    public $style           = 1;
    public $version         = '3.0.0';
    public $type            = 'uti';
    public $subtype         = 'uti';
    public $antibiograms    = array();
    public $antibiogroups   = array();
    public $rm_results_tbl;
    public $abx_tbl;

    public $all_bacterias   = array();
    public $all_markers     = array();
    public $all_antibiotics = array();
    public $ts_scores       = array();
    public $lt_scores       = array();
    public $rmbiograms      = array();

    public $selectedBacterias = array();

    public $selectedMarkers = array();
    public $selectedState   = '';
    public $selectedClinicID = 0;
    public $gender          = '';
    public $age             = 0;
    public $group_markers   = array();

    public $commbacids      = array();
    public $scores          = array();
    public $result_abx      = array();
    public $cache_lr_scores = array();
    public $cache_ls_scores = array();

    public $pair_results    = array();

    public $max_count_match = 0;
    public $count_match_all = 0;
    public $count_match_all_before_pair = 0;
    public $Quantitative     = false; //low/medium/high=true
    public $needcheckrisk0  = false;

    public $has_Orphan      = false;

    public $debug           = false;
    public $debug_ls_by     = array();

    public $gram_map_LS     = null;
    public $count_bac_available = 0;
    public $count_bac_hide  = 0;
    public $alway_exclude_Risk0 = false;
    public $count_errors        = 0;
    public $hasTestMakers       = false;



    public function __construct($type = 'uti')
    {
        $this->type = $type;
        $this->subtype = $type;
        $this->rm_results_tbl   = 'ci_abx_rm_scores_' . $type;
        $this->abx_tbl          = 'ci_abx_results_scores_' . $type;
    }

    public function excludeRisk0($bool)
    {
        $this->alway_exclude_Risk0 = $bool;
    }


    public function setReport($report)
    {
        $this->selectedClinicID = isset($report['cli_ID']) ? $report['cli_ID'] : 0;
        $this->gender = isset($report['pa_gender']) ? $report['pa_gender'] : '';
        if (isset($report['age']) && $report['age'] > 0) {
            $this->age = $report['age'];
        } else {
            $this->age = $this->dob2ages($report['pa_dob'], $report['doc']);
        }

        if ($this->type == 'uti' && ($this->gender == 'M' || ($this->gender == 'F' && $this->age >= 50))) {
            $this->subtype = 'cuti';
            $this->rm_results_tbl = str_replace('_uti', '_cuti', $this->rm_results_tbl);
            $this->abx_tbl = str_replace('_uti', '_cuti', $this->abx_tbl);
        }
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

        return $years;
    }

    public function setSelectedState($state)
    {
        $this->selectedState = strtoupper(trim($state));
    }

    public function setQuantitative($quantitative)
    {
        $this->Quantitative = $quantitative ? true : false;
    }

    public function checkQuantitative()
    {
        global $wpdb;
        $groupids = $wpdb->get_col("select tg_ID from ci_test_groups 
        where tg_name like '%uti%' or tg_name like '%wound%' 
        and tg_type ='pcr' ");
        $groupids_str = implode(',', $groupids);
        $this->Quantitative = $wpdb->get_var("select 
        count(*) 
        from ci_test_panels_cutoffs cu
        JOIN ci_test_panels t ON t.te_ID = cu.cu_te_ID
        JOIN ci_test_groups tg ON tg.tg_ID = t.te_group_id
        where tg_ID in ({$groupids_str}) or tg_parent in ({$groupids_str})
        and cu_cutoff !='' and cu_cutoff is not null
        and cu_upper_limit!='' and cu_upper_limit is not null
        ") > 10;

        update_option('ci_abx_Quantitative', $this->Quantitative);
    }

    public function addMarker($arr)
    {
        global $wpdb;
        $where = '';
        //get mkids
        $mkrids = '';
        $countmkids = 0;

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            $mkrids = $wpdb->get_var("select meta_value 
            from ci_test_meta 
            where test_id = '{$arr['te_ID']}' and meta_key='mkr_tests' ");

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

            echo $wpdb->last_error;
        }

        $sql = "select 
        m.ID, 
        m.class_ID,
        m.resistance_marker as name,
        m.abrev,
        m.risk,
        cl.class,
        cl.resistants
        from ci_abx_resistance_markers m
        JOIN ci_abx_rm_class cl ON cl.ID = m.class_ID
        where {$where}";
        $rs = $wpdb->get_results($sql);

        if ($this->debug) {
            echo $wpdb->last_error;

            if (count($rs) == 0) {
                echo 'not found marker: ' . $arr['te_name'] . '<br>';
                echo $sql;
                echo '<br><br>';
            }
        }

        foreach ($rs as $r) {

            $carr = explode(' ', $r->class);
            $r->class = $carr[0];
            if (stripos($r->class, 'class') !== false) {
                $r->class .= ' ' . $carr[1];
            }

            $r->abrev = !empty($r->abrev) ? $r->abrev : $r->name;
            $r->group_name =  $r->name;
            if ($countmkids > 1) {
                $r->group_name = $r->abrev;
            }

            $r->count_anti = 0;
            $this->selectedMarkers[$r->ID] = $r;
        }
    }

    public function addBacteria($arr)
    {
        global $wpdb;
        $where = '';
        //get mkids
        $baids = '';
        $countbgroup = 0;

        if (isset($arr['te_ID']) && $arr['te_ID'] > 0) {
            $baids = $wpdb->get_var(
                "select meta_value 
                from ci_test_meta 
                where test_id = {$arr['te_ID']} and meta_key='bac_tests' "
            );

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

        $rs = $wpdb->get_results("select 
        ID,
        bacteria_name as name,
        bacteria_abrev as abrev,
        risk,
        gram_type
        from ci_abx_bacterias 
        where {$where} ");

        $max_risk = 0;
        if ($countbgroup >= 2) {
            foreach ($rs as $r) {
                $max_risk = max($r->risk, $max_risk);
            }
        }


        foreach ($rs as $r) {
            $r->low         = (isset($arr['pos-neg']) && $arr['pos-neg'] == 'POL');
            $r->isexclude   = false;

            if ($this->alway_exclude_Risk0 && $r->risk == 0) {
                $r->isexclude   = true;
            } else {
                $this->count_bac_available++;
            }

            $r->is_hide     = $countbgroup >= 2 && $max_risk > 0 && $r->risk < $max_risk ? true : false;
            $r->pathogen_name = $r->name;
            if ($this->Quantitative) {
                if (in_array($arr['pos-neg'], array('POL', 'PVL'))) {
                    $r->pathogen_name .= '<span class="smalltext1"> (Low)</span>';
                } else if ($arr['pos-neg'] == 'POM') {
                    $r->pathogen_name .= '<span class="smalltext1"> (Med)</span>';
                } else if (in_array($arr['pos-neg'], array('POH', 'PVH'))) {
                    $r->pathogen_name .= '<span class="smalltext1"> (High)</span>';
                }
            }
            $this->selectedBacterias[$r->ID] = $r;
        }
    }

    public function getcountBacterias()
    {
        return count($this->selectedBacterias);
    }

    public function getSelectedBacterias()
    {
        return $this->selectedBacterias;
    }

    public function setAllMarkers($arrs) {}

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

    public function getMarkerNames()
    {
        $markers = array();
        $markers_orphan = array();

        foreach ($this->selectedMarkers as $marker) {

            if (!empty($marker->group_name)) {
                $marker->name = $marker->group_name;
            }

            if ($marker->count_anti == 0 && !isset($markers_orphan[$marker->name]) && $markers_orphan[$marker->name] !== false) {
                $markers_orphan[$marker->name] = true;
            } else {
                $markers_orphan[$marker->name] = false;
            }

            if ($this->debug) {
                echo '<br>mkr:';
                var_dump($marker);
                echo '<br><br>';
            }

            if (!isset($markers[$marker->name])) {
                $markers[$marker->name] = array(
                    'name' =>  $marker->name
                );
            }
        }

        foreach ($markers as $k => $arr) {
            if (isset($markers_orphan[$k]) && $markers_orphan[$k]) {
                $this->has_Orphan = true;
                $markers[$k]['name'] .= '*';
            }
        }
        return $markers;
    }

    public function getAbxClasses()
    {
        $classes = array();
        foreach ($this->selectedMarkers as $mkid => $marker) {
            if (!empty($marker->resistants)) {
                $list = explode(',', $marker->resistants);
                foreach ($list as $cl) {
                    $cl = trim($cl);
                    $clk = $cl;
                    if ($marker->count_anti == 0) {
                        if (!isset($classes[$clk]) || strpos($classes[$clk], '*') > 0) {
                            $cl .= '*';
                            $this->has_Orphan = true;
                        }
                    }
                    $classes[$clk] = array(
                        'name' => $cl
                    );
                }
            }
        }

        return $classes;
    }

    public function resultAbxs()
    {
        return $this->result_abx;
    }

    public function listABXMatches()
    {
        $limit = 5;
        $return = array();

        foreach ($this->result_abx as $antiID => $a) {

            if (!$a['exclude'] && count($a['numpairs']) == 0) {

                $dosekey = 'dose_' . $this->subtype;
                $notekey = 'notes_' . $this->subtype;
                $anti = $this->all_antibiotics[$antiID];


                $a1 = array(
                    'name' => !empty($a['abrev']) ?  $a['abrev'] :  $a['name'],
                    'dose' => '',
                    'notes' => '',
                );
                if (!empty($anti->{$dosekey})) {
                    $a1['dose'] = $anti->{$dosekey};
                }

                if (!empty($anti->{$notekey})) {
                    $a1['notes'] = $anti->{$notekey};
                }

                if ($this->count_match_all_before_pair > 3) {
                    //check AS=5
                    if ($a['S'] == 5 && $a['count_checkmark'] == 0) {
                        continue;
                    }
                }
                $return[] = $a1;
            }

            if (count($return) >= $limit) break;
        }

        foreach ($this->pair_results as $anids_1_2) {
            if (count($return) > $limit) break;
            $antiID1 = $anids_1_2[0];
            $antiID2 = $anids_1_2[1];
            $a1 = $this->result_abx[$antiID1];
            $a2 = $this->result_abx[$antiID2];
            $antiname1 = !empty($a1['abrev']) ?  $a1['abrev'] :  $a1['name'];
            $antiname2 = !empty($a2['abrev']) ?  $a2['abrev'] :  $a2['name'];
            $a1 = array(
                'name' => $antiname1 . ' + ' . $antiname2,
                'dose' => '',
                'is_pair' => true,
            );
            $return[] = $a1;
        }
        return $return;
    }

    public function abx_name($antiID)
    {
        return $this->all_antibiotics[$antiID]->abx;
    }

    public function abx_abrev($antiID)
    {
        return $this->all_antibiotics[$antiID]->abb;
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
        if (strtolower($marker_id) == 'nr') {
            return is_array($this->scores[$bat_id]['nr']) ? $this->scores[$bat_id]['nr'] : array();
        } else if (strtolower($marker_id) == 'common') {
            return is_array($this->scores[$bat_id]['common']) ? $this->scores[$bat_id]['common'] : array();
        } else {
            return isset($this->scores[$bat_id]['markers'][$marker_id]) && is_array($this->scores[$bat_id]['markers'][$marker_id]) ? $this->scores[$bat_id]['markers'][$marker_id] : array();
        }
    }

    public function get_bat_risk($batid)
    {
        $risk = array(
            'number' => $this->scores[$batid]['brisk'] + $this->scores[$batid]['mrisk'],
            'color' => '',
        );
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


    public function get_gram_map_LS()
    {
        global $wpdb;
        $this->gram_map_LS = array();
        $rs = $wpdb->get_results("select ID, gram_type from ci_abx_bacterias 
        where bacteria_name like 'G-%' or bacteria_name like 'G+%' ");

        foreach ($rs as $r) {
            $this->gram_map_LS[$r->gram_type] = $r->ID;
        }
    }

    public function getLS($batid, $antiID, $AB, $marker_class_id = 0)
    {
        global $wpdb;

        $marker_class_id = intval($marker_class_id);


        $bat    = $this->selectedBacterias[$batid];
        $state  = $this->selectedState;
        if (empty($state)) {
            return '';
        }

        if (!is_array($this->gram_map_LS)) {
            $this->get_gram_map_LS();
        }

        if (empty($this->cache_ls_scores)) {
            $rs = $wpdb->get_results(
                "select 
                ls.bacteria_ID,
                ls.marker_group_N,
                ls.score,
                ls.score2,
                ls.score3,
                anti.ID as antibio_ID,
                re.states,
                ba.marker_classes_1,
                ba.marker_classes_2,
                ba.marker_classes_3
                FROM ci_abx_local_resistance2 ls
                JOIN ci_abx_regions re ON re.ID = ls.region_ID
                JOIN ci_abx_antibiotics anti ON anti.ID = ls.antibio_ID
                JOIN ci_abx_bacterias ba ON ba.ID = ls.bacteria_ID
                WHERE FIND_IN_SET('{$state}' , re.states ) or re.name = 'USA' "
            );

            $foundstates = array();
            foreach ($rs as $r) {
                $statefound = $r->states == '' ? 'US' : $state;
                $foundstates[$statefound] = $statefound;

                if ($r->score3 !== '' && !is_null($r->score3)) $r->score2 = $r->score3;

                $gramid = array_search($r->bacteria_ID, $this->gram_map_LS);

                if ($gramid > 0) {
                    $r->bacteria_ID = 'gram_' . $gramid;
                }

                if ($r->marker_group_N > 0) {
                    $batfield = 'marker_classes_' . $r->marker_group_N;
                    if (!empty($r->{$batfield})) {
                        foreach (explode(',', $r->{$batfield}) as $mkclid) {
                            $k = strtolower($r->bacteria_ID . '-' . $mkclid . '-' . $r->antibio_ID . '-' . $statefound);

                            $this->cache_ls_scores[$k] = $r->score2;
                        }
                    }
                } else {
                    $k = strtolower($r->bacteria_ID . '-0-' . $r->antibio_ID . '-' . $statefound);

                    $this->cache_ls_scores[$k] = $r->score2;
                }
            }
        }




        $score = '';
        if ($AB == 1) $score = 70;
        else if ($AB == 2) $score = 90;
        else if ($AB == 3) $score = 100;


        $keys = array();

        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-' . $state); //#1
        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-' . $state); //#2
        $keys[] = strtolower('gram_' . $bat->gram_type . '-0-' . $antiID . '-' . $state);
        $keys[] = strtolower($batid . '-0-' . $antiID . '-' . $state); //#3


        $keys[] = strtolower($batid . '-' . $marker_class_id . '-' . $antiID . '-US'); //#1
        $keys[] = strtolower('gram_' . $bat->gram_type . '-' . $marker_class_id . '-' . $antiID . '-US'); //#2
        $keys[] = strtolower($batid . '-0-' . $antiID . '-US'); //#3


        foreach ($keys as $key) {
            if (isset($this->cache_ls_scores[$key]) && $this->cache_ls_scores[$key] !== '' && !is_null($this->cache_ls_scores[$key])) {

                $score = floatval($this->cache_ls_scores[$key]);
                if ($score == 0 && $this->cache_ls_scores[$key] !== '0') {
                    $score = $this->cache_ls_scores[$key];
                }

                if ($this->debug) {
                    $this->debug_ls_by[$batid . '-' . $marker_class_id . '-' . $antiID] = array($key, $score);
                }

                break;
            }
        }

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

        if (isset($_GET['debugntr'])) {
            echo $abx_class_ID;
            echo '<br>';
            echo $this->all_antibiotics[$antiID]->abx;
            echo '<br>';
            var_dump($marker_class_IDs);
            echo '<br><br>';
        }


        if (count($marker_class_IDs) > 0) {

            $marker_class_IDs_str = implode(',', $marker_class_IDs);
            $sql = "select sum(ntr.score) 
            from ci_abx_ntr ntr
            join ci_abx_resistance_markers rm ON rm.ID = marker_ID
            where ntr.bacteria_ID = {$batid} 
            and rm.{$this->type}=1 
            and rm.class_ID in ({$marker_class_IDs_str}) ";
            $ntr = $wpdb->get_var($sql);


            if (isset($_GET['debugntr'])) {
                echo $sql;
                echo '<br><br>';
                var_dump($ntr);
                echo '<br>';
                echo $wpdb->get_var("select group_concat(resistance_marker) 
                from ci_abx_resistance_markers rm
                where rm.{$this->type}=1  and rm.class_ID in ({$marker_class_IDs_str}) ");
                echo '<br><br>';
                echo '<br><br>';
            }
        }



        if ($ntr > 100) $ntr = 100;
        else if (empty($ntr)) $ntr = 0;
        return $ntr;
    }

    public function buildscores()
    {
        global $wpdb;
        $allbatids = array();
        $allmakids = array();

        $count_exclude = 0;
        foreach ($this->selectedBacterias as $bat) {
            $allbatids[] = $bat->ID;

            if ($bat->isexclude) {
                $count_exclude++;
            }
        }

        if ($this->count_bac_available == 0) {
            $this->count_bac_available = count($this->selectedBacterias);
            foreach ($this->selectedBacterias as $bid => $r) {
                $this->selectedBacterias[$bid]->isexclude = false;
            }
            if ($this->debug) {
                echo '<br>need match before risk0<br>';
            }
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


        foreach ($rs as $r) {

            if ($r->marker_ID == -1) {
                $r->marker_ID = 'nr';
            }
            $bat    = $this->selectedBacterias[$r->bacteria_ID];
            $marker = isset($this->selectedMarkers[$r->marker_ID]) ? $this->selectedMarkers[$r->marker_ID] : null;
            $marker_class_ID = $marker ? $marker->class_ID : 0;

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

            foreach ($r->antibiotics as $antiID => $sct) {

                $r->antibiotics[$antiID]['LS'] = $this->getLS($r->bacteria_ID, $antiID, $sct['AB'], $marker_class_ID);
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
                    'mrisk'     => $marker ? $marker->risk : 0,
                    'nr'        => array(),
                    'common'    => array()
                );
            }


            if ($marker && $marker->risk > $this->scores[$r->bacteria_ID]['mrisk']) {
                $this->scores[$r->bacteria_ID]['mrisk'] = $marker->risk;
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

                $antiname = '';

                if (count($bat_arr['markers']) > 0) {
                    foreach ($bat_arr['markers'] as $mkid => $arr) {
                        $scorearr   = $arr[$antiID];
                        $F          = $scorearr['F'];

                        if ($scorearr['LS'] !== '0' && $scorearr['LS'] !== 0 && floatval($scorearr['LS']) == 0) {
                            $LS = $scorearr['LS'];
                        } else if (($scorearr['LS'] < $LS || $LS == -1) && $scorearr['LS'] !== '' && !is_null($scorearr['LS'])) {
                            $LS = $scorearr['LS'];
                        }


                        $RM          = $RM < 0 ? $RM : $scorearr['RM'];
                        $AB          = $scorearr['AB'];
                        if ($scorearr['LT'] > $LT || $LT == 0) {
                            $LT          = $scorearr['LT'];
                        }

                        $this->selectedMarkers[$mkid]->count_anti++;

                        //check antigroups
                        foreach ($this->antibiogroups as $ag) {
                            if (in_array($batid, $ag->exclude_Bacterias)) {
                                continue;
                            }
                            if (($ag->antiID1 == $antiID || $ag->antiID2 == $antiID) && in_array($mkid, $ag->markers)) {
                                $antiname = $this->abx_name($ag->antiID1) . ' + ' . $this->abx_name($ag->antiID2);
                                $aid = $ag->antiID1 == $antiID ? $ag->antiID1 : $ag->antiID2;
                                $this->scores[$batid]['markers'][$mkid][$aid]['name'] = $antiname;
                                unset($this->scores[$batid]['markers'][$mkid][$ag->antiID2]);
                                $removeantigroup[$ag->antiID1 . '-' . $ag->antiID2] = $antiname;
                                break;
                            }
                        }
                    }
                } else if ($is_NR && is_array($countnmk)) {

                    //NR
                    $F           = $countnmk['F'];
                    $LS          = $countnmk['LS'];
                    $RM          = $countnmk['RM'];
                    $AB          = $countnmk['AB'];
                    $LT          = $countnmk['LT'];
                } else {
                    continue;
                }

                $noLS = false;
                if ($LS == -1 || (floatval($LS) == 0 && $LS !== '0' && $LS !== 0)) {
                    if ($this->debug) {
                        // echo '<br>';
                        // echo 'NO LS for ' . $this->abx_name($antiID);
                        // echo '<br>';
                    }
                    $noLS = true;
                }



                $ES = $noLS ? 0 : ((100 - $LS) * $NTR / 100 + $LS);



                $RS = $F + $A + $R + $C + $S;

                $abx_score = (0.8 * $ES) + (20 - $RS) + $LT;

                $abx_icon = 'minus_grey';

                if ($RM <= 0 && !$is_NR) {
                    // $RS = 0;
                    $ES = 0;
                    $abx_score = (0.8 * $ES) + (20 - $RS) + $LT;
                }

                if (!empty($antiname)) {
                    $ES = 90;
                    $abx_score = 90;
                    $LT = 3;
                }


                if ($RM == -1) {

                    $abx_icon = 'dna_red';
                } else if ($ES < 70) {
                    $abx_icon = 'minus_grey';
                    if (count($bat_arr['markers']) == 0 && !$is_NR) {
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
                } else if ($ES >= 70) {
                    $abx_icon = 'dna_orange';
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
                    if (!empty($antiname)) {
                        $scorearr['name'] = $antiname;
                    }
                    $this->scores[$batid]['common'][$antiID] = $scorearr;
                } else if (!empty($antiname)) {
                    continue;
                }


                $this->commbacids[$batid] = $batid;

                if (!isset($this->result_abx[$antiID])) {
                    if ($abx_risk > $max_abx_risk) {
                        $max_abx_risk = $abx_risk;
                    }

                    $this->result_abx[$antiID] = array(
                        'name'      => $this->abx_name($antiID),
                        'abrev'     => $this->abx_abrev($antiID),
                        'scores'    => array(),
                        'abx_score' => 0,
                        'count_match' => 0,
                        'count_checkmark' => 0,
                        'iconcode'  => $iconcode,
                        'abx_risk'  => $abx_risk,
                        'risk_color' => 'grey',
                        'exclude'   => ($RM < 0 || $ES < 70) && !$is_NR ? true : false,
                        'numpairs'  => array(),
                        'maxscore'  => array(),
                        'S'         => $S
                    );
                }
                $this->result_abx[$antiID]['scores'][$batid] = $scorearr;
            }

            if (is_array($this->scores[$batid]['common']) && count($this->scores[$batid]['common']) > 0) {
                uasort($this->scores[$batid]['common'], array($this, 'sort_abx'));
            }
        }

        //cal abx
        foreach ($this->result_abx as $antiID => $arrs) {
            $tc_abx_score = array();

            foreach ($this->commbacids as $batid) {

                if ($this->selectedBacterias[$batid]->isexclude) {
                    continue;
                }
                $arr            = isset($arrs['scores'][$batid]) ? $arrs['scores'][$batid] : array();
                $tc_abx_score[] = $arr['abx_score'];
                $notpassES      =  $arr['ES'] < 70;
                if ($arr['abx_score'] == 0 || $arr['RM'] < 0 || $notpassES) {
                    $this->result_abx[$antiID]['exclude'] = true;
                } else {
                    $this->result_abx[$antiID]['count_match']++;
                    if (in_array($arr['icon'], array('checkmark_dark_green', 'checkmark_light_green'))) {
                        $this->result_abx[$antiID]['count_checkmark']++;
                    }
                }
            }

            $abx_score = array_sum($tc_abx_score) / count($tc_abx_score);
            $abx_score = round($abx_score, 2);


            $this->result_abx[$antiID]['abx_score'] = $abx_score;


            $risk_color = 'grey';
            if ($arrs['abx_risk'] == $max_abx_risk) {
                $risk_color = 'red';
            } else if ($arrs['abx_risk'] == $max_abx_risk - 1) {
                $risk_color = 'orange';
            } else if ($arrs['abx_risk'] == $max_abx_risk - 2) {
                $risk_color = 'yellow';
            }
            $this->result_abx[$antiID]['risk_color'] = $risk_color;

            if ($this->result_abx[$antiID]['count_match'] > $this->max_count_match) {
                $this->max_count_match = $this->result_abx[$antiID]['count_match'];
            }

            if ($this->result_abx[$antiID]['count_match'] >= $this->count_bac_available) {
                //match
                $this->count_match_all++;
            }
        }



        uasort($this->result_abx, array($this, 'sort_abx'));


        if ($this->count_match_all < 3 && $this->count_bac_available > 1) {
            if ($this->debug) {
                echo 'run exclude:';
                echo '<br>';
                echo 'countmatches:' . $this->count_match_all;
                echo '<br>';
                echo 'count bac: ' . $this->count_bac_available;
                echo '<br>';
                echo '<br>';
            }
            $this->newcal_risk_lowhigh_matches();
        }


        if (count($removeantigroup) > 0) {
            foreach ($removeantigroup as $antids => $antiname) {
                list($anti1, $anti2) = explode('-', $antids);


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
                    unset($this->result_abx[$removeantid]);
                    foreach ($this->scores as $batid => $arr) {
                        unset($this->scores[$batid]['common'][$removeantid]);
                    }
                }
            }
        }


        $this->count_match_all_before_pair = $this->count_match_all;
        if ($this->count_match_all < 3 && $this->count_bac_available >= 2) {
            if ($this->debug) {
                echo '<h2>Debug Pair</h2>';
                echo '<br>';
                echo 'countmatches:' . $this->count_match_all;
                echo '<br>';
            }
            $this->run_pair();
        }
    }


    function newcal_risk_lowhigh_matches()
    {
        //check risk
        $count_risk0 = 0;
        $count_risknot0 = 0;
        foreach ($this->selectedBacterias as $bat) {
            if ($this->needcheckrisk0) {
                if ($bat->risk == 0 || $bat->low) {
                    $count_risk0++;
                } else {
                    $count_risknot0++;
                }
            } else if ((!$this->Quantitative && $bat->risk == 0) || ($this->Quantitative && $bat->low)) {
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
                    $this->count_match_all++;
                    continue;
                }

                $sum_abxscore = 0;
                $pass = true;
                foreach ($this->selectedBacterias as $bid => $bat) {

                    if ($this->selectedBacterias[$bid]->isexclude) {
                        continue;
                    } else if ($this->needcheckrisk0) {
                        if ($bat->risk == 0 || $bat->low) {
                            $this->selectedBacterias[$bid]->isexclude = true;
                            continue;
                        }
                    } else if ((!$this->Quantitative && $bat->risk == 0) || ($this->Quantitative && $bat->low)) {
                        $this->selectedBacterias[$bid]->isexclude = true;
                        continue;
                    }

                    $arr = isset($arrs['scores'][$bat->ID]) ? $arrs['scores'][$bat->ID] : array();
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
                $this->count_match_all++;
                if ($this->count_match_all >= 3) {
                    // break;
                }
            }
        }

        if ($this->debug) {
            echo '$new_scores: ';
            var_dump($new_scores);
            echo '<br><br>';
        }

        if (!$this->needcheckrisk0 && $this->Quantitative && $this->count_match_all < 3) {
            $this->needcheckrisk0 = true;
            // $this->Quantitative = false;
            $this->newcal_risk_lowhigh_matches();
            return;
        }

        if ($this->debug) {
            //var_dump($count_risk0);
            //var_dump($count_risknot0);
            // var_dump($this->count_match_all);
        }
    }

    function get_filter($antiIDs)
    {
        global $wpdb;
        $fitler = array();
        if (count($antiIDs) > 0) {
            $antiIDs_str = implode(',', $antiIDs);

            $rs = $wpdb->get_results("select antiID1, antiID2 from ci_abx_filter_antibios
            where antiID1 in ({$antiIDs_str}) and antiID2 in ({$antiIDs_str})");

            foreach ($rs as $r) {
                $fitler[$r->antiID1 . '-' . $r->antiID2] = 1;
            }
        }

        return $fitler;
    }

    public function run_pair()
    {
        $listscores = array();
        $keys       = array();
        $batids     = array();

        foreach ($this->result_abx as $antiID => $arr2) {

            if ($this->result_abx[$antiID]['exclude'] == false) {
                //var_dump($this->result_abx[$antiID]);echo '<br><br>';
                $this->result_abx[$antiID]['abx_score2'] = $this->result_abx[$antiID]['abx_score'];
                $this->result_abx[$antiID]['match_all'] = true;

                if ($this->debug) {
                    // echo 'exclude pair for : ' . $arr2['name'] . '<br>';
                }
                // continue;
            }


            $listscores[$antiID] = array();
            $pass_run_pair = false;
            foreach ($this->selectedBacterias as $bat) {
                if ($bat->isexclude) {
                    continue;
                }

                $arr = isset($this->result_abx[$antiID]['scores'][$bat->ID]) ? $this->result_abx[$antiID]['scores'][$bat->ID] : array();
                $abxsc = $arr['abx_score'];

                if ($arr['RM'] < 0) {
                    //$pass_run_pair = false;
                    // break;
                }

                if ($arr['ES'] >= 70) {
                    $pass_run_pair = true;
                }

                if ($arr['LS'] < 70 || $arr['RM'] < 0) {
                    $abxsc = 0;
                }

                $listscores[$antiID][] = $abxsc;

                $batids[$bat->ID] = $bat->ID;
            }

            if ($pass_run_pair) {
                $keys[] = $antiID;
            } else {
                unset($listscores[$antiID]);
                if ($this->debug) {
                    echo 'exclude pair for : ' . $arr2['name'] . '<br>';;
                }
            }
        }

        if (count($batids) < 2) {
            if ($this->debug) {
                echo '<br>not run pair by bacteria number<br>';
            }
            return;
        }

        if ($this->debug) {
            echo 'keys: ' . count($keys);
        }


        $countk     = count($keys);


        //get filter
        $filter = $this->get_filter($keys);


        $listSum = array();
        $hasmap = array();
        for ($i = 0; $i < $countk - 1; $i++) {
            $antiID1 = $keys[$i];
            for ($j = 0; $j < $countk; $j++) {
                $antiID2 = $keys[$j];

                if (in_array($antiID1 . '-' . $antiID2, $hasmap)) {
                    continue;
                }

                if (!isset($filter[$antiID1 . '-' . $antiID2]) && !isset($filter[$antiID2 . '-' . $antiID1])) {
                    continue;
                }

                $sum = 0;
                for ($col = 0; $col < count($listscores[$antiID1]); $col++) {
                    $sum += max($listscores[$antiID1][$col], $listscores[$antiID2][$col]);
                }

                $sumk = $sum;
                for ($si = 0; $si <= 10; $si++) {
                    if (isset($listSum[$sumk])) {
                        $sumk += 0.1;
                    }
                }
                $listSum[$sumk] = array($antiID1, $antiID2);
                $hasmap[] = $antiID2 . '-' . $antiID1;



                if (!isset($this->result_abx[$antiID1]['abx_score2'])) {
                    $this->result_abx[$antiID1]['abx_score2'] = $sum;
                }
                if (!isset($this->result_abx[$antiID2]['abx_score2'])) {
                    $this->result_abx[$antiID2]['abx_score2'] = $sum;
                }


                if ($this->result_abx[$antiID1]['abx_score2'] < $sum) {
                    $this->result_abx[$antiID1]['abx_score2'] = $sum;
                }

                if ($this->result_abx[$antiID2]['abx_score2'] < $sum) {
                    $this->result_abx[$antiID2]['abx_score2'] = $sum;
                }
            }
        }

        krsort($listSum);

        if ($this->debug) {

            if (count($listSum) == 0) {
                echo '<br>No results for Pair<br>';
            } else {
                echo '<div style="overflow:auto;max-height:200px"><table class="reportabx_tbl tblborder">';
                foreach ($listSum as $sumk => $aids) {
                    echo '<tr>';
                    echo '<td>' . $this->all_antibiotics[$aids[0]]->abx . '</td>';
                    echo '<td>' . $this->all_antibiotics[$aids[1]]->abx . '</td>';
                    echo '<td>' . $sumk . '</td>';
                    echo '</tr>';
                }
                echo '</table></div>';
            }
        }

        $numpair = 0;
        foreach ($listSum as $i => $arr) {

            $antiID1 = $arr[0];
            $antiID2 = $arr[1];

            if ($this->count_match_all < 3 && ($this->result_abx[$arr[0]]['exclude'] == true || $this->result_abx[$arr[1]]['exclude'] == true)) {

                $numpair++;

                $this->pair_results[] = $arr;

                $this->result_abx[$antiID1]['exclude'] = false;
                $this->result_abx[$antiID2]['exclude'] = false;
                $this->result_abx[$antiID1]['numpairs'][] = $numpair;
                $this->result_abx[$antiID2]['numpairs'][] = $numpair;
                $this->count_match_all++;
            }
        }

        if (count($this->pair_results) > 0) {
            if ($this->debug) {
                echo '<br>sort by pair<br>';
            }
            uasort($this->result_abx, array($this, 'sort_abx_pair'));
        }
    }

    public function sort_abx($a, $b)
    {

        if (isset($a['count_match'])) {
            if ($a['count_match'] == $a['count_checkmark']) {
                $a['abx_score'] += $a['count_checkmark'] * 20;
            }
            if ($b['count_match'] == $b['count_checkmark']) {
                $b['abx_score'] += $b['count_checkmark'] * 20;
            }
        }

        if ($a['abx_score'] == $b['abx_score']) return 0;
        return $a['abx_score'] > $b['abx_score'] ? -1 : 1;
    }

    public function sort_abx_pair($a, $b)
    {
        if (isset($a['numpairs']) && count($a['numpairs']) > 0) {
            $a['abx_score2'] += count($a['numpairs']) + 5;
        }
        if (isset($b['numpairs']) && count($b['numpairs']) > 0) {
            $b['abx_score2'] += count($b['numpairs']) + 5;
        }

        if (isset($a['match_all']) && !isset($b['match_all'])) {
            $a['abx_score2']  = $b['abx_score2'] + 1;
        } else if (isset($b['match_all']) && !isset($a['match_all'])) {
            $b['abx_score2']  = $a['abx_score2'] + 1;
        } else if (isset($a['combination']) && $a['combination'] && (!isset($b['numpairs']) || count($b['numpairs']) == 0)) {
            $a['abx_score2'] += $b['abx_score2'];
        } else if (isset($b['combination']) && $b['combination'] && (!isset($a['numpairs']) || count($a['numpairs']) == 0)) {
            $b['abx_score2'] += $a['abx_score2'];
        } else if (count($a['numpairs']) == 0 && count($b['numpairs']) == 0) {
            if ($a['count_checkmark'] > 0 && $a['count_checkmark'] > $b['count_checkmark']) {

                return -1;
            } else if ($b['count_checkmark'] > 0 && $b['count_checkmark'] > $a['count_checkmark']) {

                return 1;
            }
        }



        if ($a['abx_score2'] == $b['abx_score2']) return 0;

        return $a['abx_score2'] > $b['abx_score2'] ? -1 : 1;
    }


    public function initData()
    {
        global $wpdb;

        $this->all_bacterias = $wpdb->get_results("select * 
        from ci_abx_bacterias ba
        where ba.{$this->type}=1  
        order by ba.position asc");
        if (!empty($wpdb->last_error)) {
            echo $wpdb->last_error;
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
        if (!empty($wpdb->last_error) || empty($mkrs)) {
            echo $wpdb->last_error;
            echo $wpdb->last_query;
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


        $anti_rs        = $wpdb->get_results("select * 
                                from ci_abx_antibiotics ant
                                where {$this->subtype} = 1
                                order by ant.position asc");

        foreach ($anti_rs as $anti) {
            $this->all_antibiotics[$anti->ID] = $anti;
        }
        unset($anti_rs);


        $antibiograms_rs    = $wpdb->get_results("select * from ci_abx_antibiogram");

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
        foreach ($antigroups_rs as $r) {
            $r->markers = explode(',', $r->markers);
            $r->exclude_Bacterias = explode(',', $r->exclude_Bacterias);
            $this->antibiogroups[$r->ID] = $r;
        }
        $rmbiograms_rs = $wpdb->get_results("select * from ci_abx_rm_biogram");

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

    function build_all_scores()
    {
        global $wpdb;

        $last_change    = get_option('ci_abx_last_change', 0);
        $last_build     = get_option('ci_abx_last_build_' . $this->subtype, 0);
        $updating_data  = get_option('ci_abx_updating_data', false);

        if ($updating_data) {
            ob_get_clean();
            echo 'Syncing Data. Please try later!';
            exit;
        }

        $this->initData();

        if (empty($this->all_bacterias)) {
            echo 'v1 db error:empty bacterias. Please try again later!';
            exit;
        }
        if (empty($this->all_markers)) {
            echo 'v1 db error: empty markers. Please try again later!';
            exit;
        }


        if ($last_build > $last_change) {
            return;
        }



        $wpdb->query("truncate table {$this->abx_tbl}");


        foreach ($this->all_bacterias as &$bat) {
            $bat->scores = array();
            $bat->abxs = array();

            //score for nr
            $bat->scores['nr'] = array();

            foreach ($this->all_antibiotics as $anti) {

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
                    $score1 = $this->cal_score1($bat->ID, $marker->ID, $anti);

                    if ($score1 !== false) {
                        $bat->scores[$marker->ID][$anti->ID] = $score1;
                    }
                }


                $this->insertScore2Db($bat->ID, $marker->ID, $bat->scores[$marker->ID]);
            }
        }


        update_option('ci_abx_last_build_' . $this->subtype, date_i18n('YmdHis'));
    }



    public function insertScore2Db($batid, $makerid, $scores)
    {
        global $wpdb;

        $table = 'ci_abx_results_scores_' . $this->subtype;

        $scores = json_encode($scores);


        $wpdb->insert($table, array(
            'bacteria_ID' => $batid,
            'marker_ID' => $makerid,
            'antibiotics' => $scores,
        ));
    }
}
