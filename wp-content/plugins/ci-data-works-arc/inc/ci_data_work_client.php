<?php
class CI_Data_Work_Client extends CI_Data_Work_Xml
{

    var $wsdl = '';
    var $key = '';
    var $client;
    var $debug = false;
    var $all_tests = array();
    var $all_groups = array();
    public $ref_lab_id = 0;
    public $fix_ref_test = false;
    var $wsdl_data = array();

    public function __construct($wsdl_data)
    {
        $this->wsdl_data = $wsdl_data;
        if (!is_array($this->wsdl_data)) {
            $this->wsdl_data = (array)$this->wsdl_data;
        }
        if (!empty($this->wsdl_data['wsdl_url'])) {
            $this->wsdl = $this->wsdl_data['wsdl_url'];
        }
        if (!empty($this->wsdl_data['wsdl_key'])) {
            $this->key = $this->wsdl_data['wsdl_key'];
        }

        if (empty($this->wsdl) || empty($this->key)) {
            return false;
        }

        if ($this->wsdl_data['wsdl_api_type'] == 1) {
            $this->isjsonwebhook = true;
            include_once(TRCPDD_PATH . 'api/ci_json_client.php');
            $this->client = new CI_Json_Client($this->wsdl);
        } else {
            $this->client = new SoapClient($this->wsdl, array(

                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'exceptions' => 1,
                'trace' => 1,
                'soap_version' => SOAP_1_2,
                'cache_wsdl' => WSDL_CACHE_NONE
            ));
        }
    }

    public function load_tests_groups($btype)
    {
        global $wpdb;

        if (!isset($this->all_tests[$btype])) {
            $this->all_tests[$btype] = array();

            $rs = $wpdb->get_results("select te_ID,te_name, te_abrev, te_abrev2
		                from ci_test_panels
						where te_type='{$btype}' ");
            foreach ($rs as $r) {
                $this->all_tests[$btype][$r->te_ID] = $r;
            }
        }
        if (!isset($this->all_groups[$btype])) {
            $rs = $wpdb->get_results("select tg_ID,
		                    tg_name,
		                    tg_short_name,
                            tg_cpt_code
		                from ci_test_groups
						where tg_type='{$btype}' ");
            foreach ($rs as $r) {
                if (!empty($r->tg_short_name) && empty($r->tg_cpt_code)) {
                    $r->tg_cpt_code = $r->tg_short_name;
                }
                $this->all_groups[$btype][$r->tg_ID] = $r;
            }
        }
    }

    public function put_req_base($mid, $btype, $debug = false)
    {
        global $wpdb;
        if (!$debug) {
            $key = $this->key;
            if (empty($key) || empty($this->wsdl)) return false;
        }

        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $data = $wpdb->get_row("select
                            ba.assen,
                            ba.patient_name,
                            ba.panel_tests_requested
                            
                            from {$batch_tbl} ba
                            where mid = {$mid} 
                            ");
        try {

            $xml = @$this->objToXML($data, 'req');
            if ($debug) {
                return $xml;
            }

            file_put_contents(WP_CONTENT_DIR . '/demo_base.xml', $xml);

            $rs = $this->client->put_req($xml, $key);
            $rs = (object)$this->xmlToObj($rs);
        } catch (Exception $e) {
            return $e;
        }
        return $rs;
    }

    public function put_clinic($cli_ID, $remote_ID = 0, $debug = false)
    {
        global $wpdb;

        if (!$debug) {
            $key = $this->key;
            if (empty($key) || empty($this->wsdl)) return false;
        }

        //get clinic
        $clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_ID}");

        $clinic_keys_ex = array(
            'cID',
            'cli_lab-ID',
            'cli_rep-ID',
            'cli_group_ID',
            'cli_marketing_group_ID',
            'cli_distributor_ID',
            'cli_import_ID',
            'cli_ref_lab_ID'
        );
        $clinic->ID = $clinic->cID;
        foreach ($clinic_keys_ex as $ck) {
            unset($clinic->{$ck});
        }

        $data = new stdClass();

        $data->clinic_detail = $clinic;

        $data->office_managers = array();

        $managers = $wpdb->get_results("select ma_manager, ma_email from ci_clinic_manager where ma_cli_ID = {$cli_ID}");
        foreach ($managers as $ma) {
            $manager = new stdClass();
            $manager->name = $ma->ma_manager;
            $manager->email = $ma->ma_email;

            $data->office_managers[] = $manager;
        }


        //get doctor
        $data->doctors = array();

        $doctor_keys_ex = array('doc_ID', 'cli_ID', 'doc_import_ID');

        $doctors = $wpdb->get_results("select doc.* from ci_doc doc 
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID 
	                        where cd.cli_ID = '{$cli_ID}' ");

        foreach ($doctors as $doctor) {
            foreach ($doctor_keys_ex as $ke) {
                unset($doctor->{$ke});
            }
            $data->doctors[] = $doctor;
        }

        //collectors
        $data->collectors = array();
        $collectors = $wpdb->get_results("select co.* from ci_collector co 
	                            JOIN ci_clinic_collector cl ON cl.col_ID = co.col_ID 
	                            where cl.cli_ID = {$cli_ID} ");

        $col_keys_ex = array('col_ID', 'doc_ID', 'lab_ID', 'rep_ID');

        foreach ($collectors as $collector) {
            foreach ($col_keys_ex as $ke) {
                unset($collector->{$ke});
            }
            $data->collectors[] = $collector;
        }


        try {

            $xml = @$this->objToXML($data, 'clinic');
            if ($debug) {
                return $xml;
            }
            if ($this->debug) {
                file_put_contents(WP_CONTENT_DIR . '/demo_clinic.xml', $xml);
            }

            $rs = $this->client->add_clinic($key, $xml);
            $rs = (object)$this->xmlToObj($rs);
        } catch (Exception $e) {
            return $e;
        }
        return $rs;
    }

    public function put_req($mid, $btype, $remote_ID = 0, $debug = false, $is_resend = false)
    {
        global $wpdb;

        $key = $this->key;

        if (!$debug) {

            if (empty($key) || empty($this->wsdl)) return false;
        }

        $result_dir = tr_get_result_upload_dir();

        $this->load_tests_groups($btype);

        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $data = $wpdb->get_row("select
                            ba.mid as id,
                            ba.assen as accession_number,
                            ba.oldreq as ref_accession,
                            ba.assen,
                            ba.position,
                            ba.batch_id,
                            ba.lab,
                            ba.dob,
                            ba.spec_received,
                            cli.cli_clinic as clinic,
                            ba.cli_ID,
                            ba.doc_ID,
                            concat(doc.doc_last,',',doc.doc_first) as doctor,
                            doc.doc_npi,
                            ba.physician_phone as doctor_phone,
                            concat(col.col_last,',',col.col_first) as collector,
                            col.col_phone,
                            ba.dagnostic_codes1 as primary_dagnostic_code,
                            ba.dagnostic_codes2 as second_dagnostic_code,
                            ba.type_of_specimen,
                            ba.doc as date_of_collection,
                            ba.temperature,
                            ba.actual_temperature,
                            ba.current_medication,
                            ba.current_medication_other,
                            ba.poc_perform,
                            ba.medication as medication_or_drug,
                            ba.custom_profile,
                            ba.panel_tests_requested,
                            ba.patient_id,
                            ba.notes,
                            ba.insurance,
                            ba.result_pdf,
                            ba.original_req,
                            ba.insurance1 as ins_id1,
                            ba.insurance2 as ins_id2,
                            ba.ref_lab_ID,
                            lab.lab_dba_init as ref_lab

                            from {$batch_tbl} ba
                            left JOIN ci_clinic cli on ba.cli_ID = cli.cID
                            left JOIN ci_doc doc ON ba.doc_ID = doc.doc_ID
                            left JOIN ci_clinic_collector ccl ON cli.cID = ccl.cli_ID
                            left JOIN ci_collector col ON col.col_ID = ccl.col_ID
                            left JOIN ci_labs lab ON lab.lab_ID = ba.lab_ID

                            where mid ={$mid} group by mid");


        //trim name
        $data->doctor = tr_trim_name($data->doctor);
        $data->collector = tr_trim_name($data->collector);
        $data->remote_ID = $remote_ID;
        $data->is_resend = $is_resend ? 1 : 0;

        if (!empty($this->wsdl_data['wsdl_clinicname'])) {
            $data->clinic = $this->wsdl_data['wsdl_clinicname'];
        }

        if (!empty($this->wsdl_data['wsdl_doctorname'])) {
            $data->doctor = $this->wsdl_data['wsdl_doctorname'];
        }

        //format medication
        if (in_array($btype, array('tox', 'gene'))) {
            $data->current_medication = str_replace('|', ',', $data->current_medication);
            $medications = explode(',', trim($data->current_medication));
            if (!empty($data->current_medication_other)) {
                $data->current_medication_other = str_replace('|', ',', $data->current_medication_other);
                $others = explode(',', trim($data->current_medication_other));
                $medications = array_merge($medications, $others);
            }
            foreach ($medications as $j => &$med) {
                $med = trim($med);
                $med = ucfirst(strtolower($med));
                if (empty($med)) {
                    unset($medications[$j]);
                }
            }
            $medications = implode(',', $medications);
            $medications = str_replace(',', ', ', $medications);
            $data->medications = $medications;


            //format medication_or_drug
            $medication_or_drug = maybe_unserialize($data->medication_or_drug);
            $data->medication_or_drug = array();
            foreach ($medication_or_drug as $k => $vl) {
                $data->medication_or_drug[] = ($vl == 1 ? '+' : '-') . $k;
            }
            $data->medication_or_drug = implode(', ', $data->medication_or_drug);
        } else {
            unset($data->medication_or_drug);
            unset($data->temperature);
            unset($data->actual_temperature);
            unset($data->poc_perform);
            unset($data->custom_profile);
            unset($data->medications);
        }




        if ($data->panel_tests_requested == null) {
            $data->panel_tests_requested = '';
        } else {
            $data->panel_tests_requested = trim($data->panel_tests_requested, ',');
        }

        if (in_array($btype, array('blood', 'pcr', 'gene'))) {
            $data->abrevs = '';
            $abrevs = array();
            $tests = array();
            foreach (explode(',', $data->panel_tests_requested) as $p) {
                if (stripos($p, 'group_') !== false) {
                    $gid = str_ireplace('group_', '', $p);

                    if (isset($this->all_groups[$btype][$gid])) {
                        $tests[] = $this->all_groups[$btype][$gid]->tg_name;
                        $abrevs[] = $this->all_groups[$btype][$gid]->tg_cpt_code;
                    }
                } else {
                    $tid = $p;
                    if (isset($this->all_tests[$btype][$tid])) {
                        $tests[] = $this->all_tests[$btype][$tid]->te_name;
                        $abrevs[] = $this->all_tests[$btype][$tid]->te_abrev;
                    }
                }
            }

            $data->panel_tests_requested = implode(',', $tests);
            $data->abrevs = implode(',', $abrevs);
        }


        $data->patient_signature = tr_get_signature_by($data->assen, 'patient');
        $data->provider_signature = tr_get_signature_by($data->assen, 'provider');

        $data->ins_card_front = tr_get_card_by($data->assen, 'ins', $data->ins_card_front);
        $data->ins_card_back = tr_get_card_by($data->assen, 'ins_back', $data->ins_card_back);
        $data->patient_id_front = tr_get_card_by($data->assen, 'id', $data->patient_id_front);
        $data->patient_id_back = tr_get_card_by($data->assen, 'id_back', $data->patient_id_back);

        //get patient
        $patient = $wpdb->get_row("select 
                                pa.pa_ID as pa_patient_id,
                                pa.pa_first,
                                pa.pa_last,
                                pa.pa_middle,
                                pa.pa_dob,
                                pa.pa_phone,
                                pa.pa_gender,
                                pa.pa_address,
                                pa.pa_city,
                                pa.pa_state,
                                pa.pa_zip,
                                pa.pa_ssn,
                                pa.pa_mrn, 
                                pa.pa_apt,
                                pa.pa_race,
                                pa.pa_ethnicity,
                                pa.pa_email
                            from ci_patients pa
                            where pa.pa_ID = '{$data->patient_id}'");


        $data->patient = $patient;


        //get insurance
        if ($data->ins_id1 > 0) {
            $insurance = $wpdb->get_row("select 
            ins_first,
            ins_last,
            ins_company,
            ins_relation,
            ins_dob,
            ins_email,
            ins_address,
            ins_city,
            ins_state,
            ins_zip,
            ins_phone,
            ins_member_id,
            ins_group_id
             from ci_insurances where ins_ID = '{$data->ins_id1}' ");
            if ($insurance) {
                unset($insurance->ins_ID);

                if (empty($insurance->ins_first)) {
                    $insurance->ins_first = $patient->pa_first;
                    $insurance->ins_last = $patient->pa_last;
                }
                if (empty($insurance->ins_relation)) {
                    $insurance->ins_relation = 'Self';
                }
                if (!isset($insurance->ins_fin_mnemonic) || empty($insurance->ins_fin_mnemonic)) {
                    $ins_fin_mnemonic = $wpdb->get_var($wpdb->prepare(
                        "select payer_fin_mnemonic 
			    	                from ci_payer_list where payer_name = %s",
                        $insurance->ins_company
                    ));
                    if (!empty($ins_fin_mnemonic)) {
                        $insurance->ins_fin_mnemonic = $ins_fin_mnemonic;
                    }
                }
            }

            $data->primary_insurance = $insurance;
        }

        if (!empty($data->result_pdf)) {
            $data->result_pdfs = array();
            foreach (explode(',', $data->result_pdf) as $fpdf) {
                $data->result_pdfs[] = $result_dir['url'] . '/' . $fpdf;
            }
        }

        if (!empty($data->original_req)) {
            $data->original_req = $result_dir['url'] . '/' . $data->original_req;
        }



        //get second insurance
        if ($data->ins_id2 > 0) {
            $insurance = $wpdb->get_row("select 
             ins_first,
            ins_last,
            ins_company,
            ins_relation,
            ins_dob,
            ins_email,
            ins_address,
            ins_city,
            ins_state,
            ins_zip,
            ins_phone,
            ins_member_id,
            ins_group_id
             from ci_insurances where ins_ID = '{$data->ins_id2}' ");
            $data->second_insurance = $insurance;
        }

        if ($btype == 'tox') {
            $screening_rs = tr_pdd_get_result_validity(1, '', $mid);
            $screenings = array();
            foreach ($screening_rs as $arr) {
                if (isset($arr['checked']) && $arr['checked']) {

                    if (isset($arr['u_notshow_for_ref_lab_ID']) && !empty($arr['u_notshow_for_ref_lab_ID']) && $data->ref_lab_ID > 0 && $data->ref_lab_ID == $arr['u_notshow_for_ref_lab_ID']) {
                        $wpdb->update('ci_result_test_speciments', array('checked' => 0), array('mid' => $arr['mid'], 'testid' => $arr['testid']));
                        continue;
                    }
                    $obj = new stdClass();
                    $obj->ID = $arr['u_ID'];
                    $obj->name = $arr['u_name'];
                    $obj->abreviation = $arr['u_abreviation'];
                    $obj->loinc_code = $arr['u_loinc_code'];
                    $obj->range = $arr['u_range'];
                    $obj->conc = $arr['conc'];
                    $obj->outcome = $arr['outcome'];
                    $obj->comments = $arr['comments'];
                    $screenings[] = $obj;
                }
            }
            $data->screenings = new stdClass();
            $data->screenings->test = $screenings;
        }

        unset($data->current_medication);
        unset($data->current_medication_other);
        unset($data->patient_id);
        unset($data->cli_ID);
        unset($data->doc_ID);
        unset($data->ins_id1);
        unset($data->ins_id2);
        unset($data->result_pdf);
        unset($data->ref_lab_ID);

        if ($this->wsdl_data['wsdl_dont_send_bid']) {
            $data->batch_id = 0;
        }


        if ($this->debug) {
            $data->save_batch = true;
        }
        $data->type = $btype;


        try {
            if ($this->wsdl_data['wsdl_api_type'] == 2) {
                $rs = $this->client->put_req($data, $key);
            } else {
                if ($this->isjsonwebhook) {
                    $data = (array)$data;
                }
                $xml = @$this->objToXML($data, 'req');
                //  var_dump($data);
                //var_dump($xml);
                if ($debug) {
                    return $xml;
                }
                if ($this->debug) {
                    file_put_contents(WP_CONTENT_DIR . '/demo.xml', $xml);
                }

                $func = 'put_' . ($btype == 'tox' ? 'req' : $btype);
                if ($this->isjsonwebhook) {
                    $func = 'put_req';
                }
                $rs = $this->client->{$func}($xml, $key);
            }
            $rs = (object)$this->xmlToObj($rs);
        } catch (Exception $e) {
            return $e;
        }
        return $rs;
    }

    public function put_result($mid, $btype, $remote_ID = 0, $default = false)
    {
        global $wpdb;

        $key = $this->key;

        if (empty($key) || empty($this->wsdl)) return false;

        $send_result_file = false;

        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $report_tbl = tr_pdd_get_table_report_by_btype($btype);
        $result_tbl = tr_pdd_get_table_result_by_btype($btype);

        $adding_fields = '';
        if ($btype == 'pcr') {
            $adding_fields .= "re.has_uti,";
        }

        $report = $wpdb->get_row(
            "SELECT
        	        re.id,
                    re.approve_date,
                    re.instrument,
                    re.estatus,
                    re.created,
                    re.created_date,
                    re.final_results,
                    {$adding_fields}
                    ba.assen,
                    ba.batch_id,
                    ba.panel_tests_requested,
                    ba.notes_report,
                    ba.ref_lab_ID,
                    ba.result_pdf
                    
                    FROM {$report_tbl} re
                    JOIN {$batch_tbl} ba ON ba.assen = re.patient_id
                    WHERE ba.mid ='" . $mid . "'",
            ARRAY_A
        );

        echo 'sync: ' . $report['assen'] . '<br>';;


        $data = new stdClass();

        $data->id = $report['id'];
        $data->assen = $report['assen'];
        $data->approve_date = $report['approve_date'];
        $data->instrument = $report['instrument'];
        $data->notes_report = $report['notes_report'];
        $data->batch_id = $report['batch_id'];



        if (!$this->wsdl_data['wsdl_dont_approve']) {
            $data->estatus = $report['estatus'];
        }
        $instrumentkeys = array();
        if (!empty($data->instrument)) {
            $instrumentkeys[] = $data->instrument;
        }
        if (!empty($this->wsdl_data['wsdl_send_instrument'])) {
            $instrumentkeys[] = $this->wsdl_data['wsdl_send_instrument'];
        }

        $data->instrument = implode('_', array_unique($instrumentkeys));

        $data->created = $report['created'];
        $data->created_date = $report['created_date'];
        $data->final = $report['final_results'];
        if ($btype == 'pcr') {
            $data->has_uti = $report['has_uti'];
        }


        $in_panel_tests_requested = array();
        $group_ids = array();
        foreach (explode(',', $report['panel_tests_requested']) as $t) {
            if (stripos($t, 'group') === false) {
                if ($t > 0 && !in_array($t, $in_panel_tests_requested)) {
                    $in_panel_tests_requested[] = $t;
                }
            } else {
                $group_ids[] = str_ireplace('group_', '', $t);
            }
        }
        if (empty($group_ids)) {
            $group_ids[] = -1;
        } else {
            $groupids_str = implode(',', $group_ids);
            $group_ids = $wpdb->get_col("select tg_ID from ci_test_groups
            where tg_ID in ({$groupids_str}) or tg_parent in ({$groupids_str}) ");
        }
        $testsids_str = implode(',', $in_panel_tests_requested);
        $groupids_str = implode(',', $group_ids);

        $where_tests = "  and ( te.te_ID in ({$testsids_str}) or te.te_group_id in ({$groupids_str}) )";
        if ($btype == 'tox') {
            $in_panel_tests_requested_str = implode("','", $in_panel_tests_requested);
            $where_tests = "  and (
                             te.te_name in ('{$in_panel_tests_requested_str}') 
                             or te.te_abrev in ('{$in_panel_tests_requested_str}') 
                             or te.te_group_id in ({$groupids_str}) )";
        }

        if ($report['ref_lab_ID'] > 0) {
            $group_not_ins = $wpdb->get_col("select tg_ID from ci_test_groups 
                where tg_btype='{$btype}' and FIND_IN_SET({$report['ref_lab_ID']}, tg_notshow_for_ref_lab_ID)");
            if (count($group_not_ins) > 0) {
                $group_not_ins_str = implode(',', $group_not_ins);
                $where_tests .= " and te.te_group_id not in ({$group_not_ins_str}) ";
            }
        }


        $all_res        = array();
        $drugkeys       = array('te_abrev2', 'te_abrev', 'te_name');
        foreach ($drugkeys as $drugkey) {


            $sql_rs = "select rs.*,
            te.*
            from {$result_tbl} rs
            JOIN ci_test_panels te ON te.{$drugkey} = rs.drug 
            where report_id = {$report['id']}
            and rs.drug !='' and rs.conc is not null
            and te.te_type='{$btype}'
            {$where_tests}
            group by te.te_ID
            order by rs.id desc";
            $results = $wpdb->get_results($sql_rs, ARRAY_A);

            // echo $wpdb->last_query . '<br>';
            echo $wpdb->last_error;

            foreach ($results as $arr) {
                if (empty($arr['drug'])) {
                    continue;
                }
                if (isset($all_res[$arr['te_ID']])  && strlen($arr['conc']) == 0 && empty($arr['pos-neg'])) {
                    continue;
                }

                $arr['dbdrug'] = $arr['drug'];
                $arr['drug'] = strtoupper($arr['te_name']);
                $all_res[$arr['te_ID']] = $arr;
            }
        }

        echo '<br>count tests: ' . count($all_res) . '<br>';


        $tests = array();
        foreach ($all_res as $arr) {

            //parse 
            $arr['cutoffs'] = array();
            if ($btype == 'pcr') {
                $arr['type_of_specimen'] = $report['type_of_specimen'];
                $arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $arr['te_group_id'], $report['created_date']);
            }

            if (empty($arr['instrument'])) {
                $arr['instrument'] = $report['instrument'];
            }


            $test           = new stdClass();
            $test->name     = !empty($arr['te_name']) ? $arr['te_name'] : $arr['drug'];
            $test->abrev    = !empty($arr['te_abrev']) ? $arr['te_abrev'] : '';
            $test->abrev2   = !empty($arr['te_abrev2']) ? $arr['te_abrev2'] : '';
            $test->value    = $arr['conc'];
            $test->tid      = $arr['te_ID'];
            $test->rsid      = $arr['id'];
            $test->{'pos-neg'} =  $arr['pos-neg'];
            $test->cutoff   = isset($arr['cutoff']) ? $arr['cutoff'] : '';
            $test->comments = $arr['comments'];
            $test->instrument = $arr['instrument'];
            $test->group     = isset($arr['group']) ? $arr['group'] : '';

            if (!empty($arr['cutoffs'])) {
                $test->cutoffs  = new stdClass();
                $test->cutoffs->high = $arr['cutoffs']['te_upper_limit'];
                $test->cutoffs->low = $arr['cutoffs']['te_cutoff'];
                $test->cutoffs->medium = $arr['cutoffs']['te_medium'];

                $test->cutoffs->lqc = isset($arr['cutoffs']['te_lqc']) ? $arr['cutoffs']['te_lqc'] : '';
                $test->cutoffs->mqc = isset($arr['cutoffs']['te_mqc']) ? $arr['cutoffs']['te_mqc'] : '';
                $test->cutoffs->hqc = isset($arr['cutoffs']['te_hqc']) ? $arr['cutoffs']['te_hqc'] : '';

                $test->cutoffs->critical_high = $arr['cutoffs']['te_critical_high'];
                $test->cutoffs->critical_low = $arr['cutoffs']['te_critical_low'];
                $test->cutoffs->invalid = $arr['cutoffs']['te_invalid'];
            } else if ($btype == 'tox') {
                $ci_cutoffs = tr_pdd_get_cutoffs($btype, $report['created_date']);
                $drug       = strtoupper($arr['drug']);
                $d_cutoff   = $drug . $report['instrument'];

                if (!empty($arr['instrument'])) {
                    $d_cutoff = $drug . $arr['instrument'];
                }
                $d_cutoff = strtoupper($d_cutoff);
                if (!isset($ci_cutoffs[$d_cutoff])) {
                    $d_cutoff = $drug . 'MS1';
                }


                $cutoff = isset($ci_cutoffs[$d_cutoff]) ? $ci_cutoffs[$d_cutoff] : array();
                $test->cutoffs  = new stdClass();
                if (!empty($cutoff)) {
                    $test->cutoffs->high = $cutoff['limit'];
                    $test->cutoffs->low = $cutoff['cutoff'];

                    $test->cutoffs->lqc = $cutoff['lqc'];
                    $test->cutoffs->mqc = $cutoff['mqc'];
                    $test->cutoffs->hqc = $cutoff['hqc'];
                }
            }



            if (!empty($this->wsdl_data['wsdl_send_instrument'])) {
                $test->instrument = $this->wsdl_data['wsdl_send_instrument'];
            }

            if (empty($test->name)) {
                continue;
            }

            $tests[] = $test;
        }

        if ($btype == 'tox' && !$this->wsdl_data['wsdl_dont_send_screening']) {
            $screening_rs = tr_pdd_get_result_validity(1, '', $mid);
            $screenings = array();
            foreach ($screening_rs as $arr) {
                if (isset($arr['checked']) && $arr['checked']) {
                    $obj = new stdClass();
                    $obj->ID = $arr['u_ID'];
                    $obj->name = $arr['u_name'];
                    $obj->abreviation = $arr['u_abreviation'];
                    $obj->loinc_code = $arr['u_loinc_code'];
                    $obj->range = $arr['u_range'];
                    $obj->conc = $arr['conc'];
                    $obj->outcome = $arr['outcome'];
                    $obj->comments = $arr['comments'];
                    $screenings[] = $obj;
                }
            }
            $data->screenings = new stdClass();
            $data->screenings->test = $screenings;
        }


        if (count($tests) == 0 && empty($report['result_pdf'])) {
            echo $sql_rs;
            return false;
        }

        $data->drugs = $tests;

        if ($send_result_file) {
            list($path, $file) = trpdd_get_path_result_pdf($report);
            $result_dir = WP_CONTENT_DIR . '/results/';
            $path_db = $path . '/' . $file;
            $file_path = $result_dir . $path_db;
            $data->pdf  = base64_encode(file_get_contents($file_path));
        }

        if (!empty($report['result_pdf'])) {
            //need send pdf uploaded
            $data->result_pdfs = array();
            foreach (explode(',', $report['result_pdf']) as $fpdf) {
                $filepath = tr_get_result_upload_dir('path') . '/' . $fpdf;
                if (file_exists($filepath)) {
                    $data->result_pdfs[] = tr_get_result_upload_dir('url') . '/' . $fpdf;
                } else {
                    echo 'file not exists: ' . $filepath . '<br>';
                }
            }
        }


        try {
            $xml = @$this->objToXML($data, 'result');
            if ($this->debug) {
                file_put_contents(WP_CONTENT_DIR . '/result_put.xml', $xml);
            }

            if (isset($_GET['do_cron'])) {
                echo '<textarea style="width:100%;height:300px">';
                var_dump($data);
                echo '</textarea>';
            }
            $func = 'put_result_' . $btype;
            if ($this->isjsonwebhook) {
                $func = 'put_result';
            }
            $rs = $this->client->{$func}($xml, $key);
            $rs = (object)$this->xmlToObj($rs);
        } catch (Exception $e) {
            var_dump($e);
        }
        return $rs;
    }

    public function get_results()
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_results($key);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function get_results_today($date)
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_results_today($key, $date);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function list_clinics()
    {
        $key = $this->key;

        try {
            $rs = $this->client->list_clinics($key);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function test_key($key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }


        try {
            $rs = $this->client->test_key($key);
            $rs = $this->xmlToObj($rs);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function get_results_data()
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_results_data($key);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function get_orders($from_ID, $limit = 10)
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_orders($key, $from_ID, $limit);
            $rs = $this->xmlToObj($rs);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function get_pdf_results($doctors)
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_pdf_results($doctors);
        } catch (Exception $e) {
        }
        return $rs;
    }

    public function get_req_by_assen($assen)
    {
        $key = $this->key;

        try {
            $rs = $this->client->get_req_by_assen($key, $assen);
            return $rs;
        } catch (Exception $e) {
        }
        return false;
    }

    public function sync_req($assen, $btype)
    {
        $rs = $this->get_req_by_assen($assen);
        $return = array();

        if (is_array($rs) && $rs['status'] == 'OK') {
            foreach ($rs['reqs'] as $data) {
                $return[] = $this->import_req($data, array('btype' => $btype, 'ref_lab_id' => $this->ref_lab_id));
            }
        }
        return $return;
    }
}
