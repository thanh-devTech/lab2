<?php

require_once  'restful_api.php';


if (!function_exists('tr_billing_get_cpt_code')) {
	include_once(WP_CONTENT_DIR . '/plugins/ci-billing-indep/inc/init.php');
}


class CI_Data_restapi extends restful_api
{

	function __construct($pre_endpoint)
	{
		parent::__construct($pre_endpoint);
	}


	public function checkKey($key)
	{
		global $wpdb;
		if (empty($key)) {
			return false;
		}

		return $wpdb->get_var("select wsdl_ID from ci_wsdl_keys where wsdl_key = '{$key}'");
	}

	/**
	 * @param string $key
	 * @param string $date
	 * @param string $ssn
	 * @param string $patient
	 * @param string $accession
	 * @return array json
	 * ex: /restapi/billing/?key=abc&date=2020-10-25
	 * ex: /restapi/billing/?key=abc&patient=firstname lastname
	 * ex: /restapi/billing/?key=abc&date=2020-10-25&ssn=123456
	 * ex: /restapi/billing/?key=abc&accession=ABC123
	 */

	public function request_billing()
	{
		global $wpdb;
		$data = array('count' => 0, 'results' => array());

		$start_time = microtime(true);

		if (!$this->checkKey($this->request_api_key)) {
			$data['error'] = 'Wrong key';
		} else if ($this->method == 'GET') {

			$settings       = tr_billing_get_settings();
			$show_billing_codetoo = isset($settings['api_show_all']) && $settings['api_show_all'];
			$races = array(
				'' => '',
				'1002-5' => 'American Indian or Alaska  Native',
				'2028-9' => 'Asian',
				'2054-5' => 'Black / African American',
				'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
				'2106-3' => 'White',
				'2131-1' => 'Other'
			);
			$Ethnicitis = array(
				''  => '',
				'H' => 'Hispanic or Latino',
				'N' => 'Not Hispanic or Latino',
				'U' => 'Unknown'
			);
			$all_groups  	= array();
			$rs = $wpdb->get_results("select tg_ID, tg_name, tg_parent,
                            tg_notestcheckbox, tg_only_group, tg_is_covid
                            from ci_test_groups 
                            where tg_type in( 'pcr', 'blood','tox')
                            ");
			foreach ($rs as $r) {
				$all_groups[$r->tg_ID] = $r;
				if ($r->tg_notestcheckbox || $r->tg_only_group || $r->tg_is_covid == 1) {
					$groups_notestcb[$r->tg_ID] = array();
				}
				if ($r->tg_is_covid == 1) {
					$covid_group_ids[] = $r->tg_ID;
				}
			}

			$rs = $wpdb->get_results("select te_ID, te_name, te_abrev, te_abrev2, te_group_id, te_type
										from ci_test_panels 
										where te_type in( 'pcr', 'blood','tox') and te_in_req = 1 and te_is_control = 0 ");
			$all_tests = array();
			foreach ($rs as $r) {

				if ($r->te_type == 'tox') {
					$all_tests['TOX_' . strtoupper($r->te_name)] = $r;
				} else {
					$all_tests[$r->te_ID] = $r;
				}


				if (isset($groups_notestcb[$r->te_group_id])) {
					$groups_notestcb[$r->te_group_id][] = $r->te_ID;
				}
			}


			$all_sales_reps = array();
			if ($settings['eapi_sales_rep']) {
				$rep_rs = $wpdb->get_results("select * from ci_lab_reps ");
				foreach ($rep_rs as $r) {
					$all_sales_reps[$r->rep_ID] = $r->rep_first . ' ' . $r->rep_last;
				}
			}


			$date 	= isset($this->params['date']) ? $this->params['date'] : $this->params[0];
			$ssn 	= isset($this->params['ssn']) ? $this->params['ssn'] : $this->params[1];
			$patient_name = isset($this->params['patient']) ? $this->params['patient'] : $this->params[2];
			$accession = isset($this->params['accession']) ? $this->params['accession'] : $this->params[3];

			$sql_child = array();

			$where = " and ( (re.id > 0 and re.estatus in ('C','F')) or (re.id is null and ba.result_pdf!='' ) ) ";


			if (!empty($ssn)) {
				$where .= " and pa.pa_ssn = '{$ssn}' ";
			}
			if (!empty($accession)) {
				$where .= " and ba.assen = '{$accession}' ";
			} else if (!empty($patient_name)) {
				list($first_name, $last_name) = explode(' ', $patient_name);
				$where .= " and ((pa.pa_first ='{$first_name}' and pa.pa_last = '{$last_name}' ) 
								or ba.patient_name like '%{$patient_name}%' )";
			} else {
				$where .= " and ((re.id > 0  and re.approve_date >= '{$date}' and re.approve_date <= '{$date} 23:59:59') 
                or (re.id is null and ba.result_pdf!='' and  ba.doc >= '{$date}' and ba.doc <= '{$date} 23:59:59' ))";
			}

			//check exclude lab
			$exclude_labids = $wpdb->get_col("select lab_ID from ci_labs where lab_send_billing = 0");
			if (count($exclude_labids) > 0) {
				$exclude_labids_str = implode(',', $exclude_labids);
				$where .= " and ba.ref_lab_ID not in ({$exclude_labids_str}) ";
			}



			foreach (tr_pdd_get_batch_types() as $t) {

				$batch_tbl = tr_pdd_get_table_by_btype($t);
				$report_tbl = tr_pdd_get_table_report_by_btype($t);

				$sql_child[] = "(select 
                        ba.dagnostic_codes1,
                        ba.assen,
                        ba.mid,
                        ba.requesting_practice,
                        ba.panel_tests_requested,
                        ba.doc,
                        ba.cli_ID,
						ba.insurance,
						ba.batch_created,

                        ins.ins_member_id,
						ins.ins_code,
                        ins.ins_group_id,
                        ins.ins_relation,
                        ins.ins_last,
                        ins.ins_first,
                        ins.ins_dob,
                        ins.ins_address,
                        ins.ins_city,
                        ins.ins_state,
                        ins.ins_zip,

                        pal.payer_mnemonic,

						ins2.ins_company ins_company2,
						ins2.ins_code ins_code2, 
						ins2.ins_member_id ins_member_id2,
						ins2.ins_group_id ins_group_id2,
						ins2.ins_relation ins_relation2,
						ins2.ins_last ins_last2,
						ins2.ins_first ins_first2,
						ins2.ins_dob ins_dob2,
						ins2.ins_address ins_address2,
						ins2.ins_city ins_city2,
						ins2.ins_state ins_state2,
						ins2.ins_zip ins_zip2,

                        pal2.payer_mnemonic as payer_mnemonic2,
                        
                        pa.*,
                        re.id,
                        re.approve_date,

                        cli.cli_facility_code,
						cli.cli_city,
						`cli_rep-ID`,

						lab.lab_ID,
						lab.lab_name,

                        doc.doc_last,
                        doc.doc_first,
                        doc.doc_npi,
                        doc.doc_phone,
                        doc.doc_provider_code,

                        '{$t}' as btype
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    join ci_clinic cli On cli.cID = ba.cli_ID
                    join ci_labs lab On lab.lab_ID = ba.lab_ID
                    left join ci_insurances ins ON ins.ins_ID = ba.insurance1
					left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
                    left join ci_payer_list pal ON pal.payer_name = ba.insurance
                    left join ci_payer_list pal2 ON pal2.payer_name = ins2.ins_company
                    where 1=1 {$where} 
					group by ba.mid 
					order by ba.mid desc)";
			}

			$sql_child = implode("\n UNION ALL \n", $sql_child);

			$sql = "select * from ( {$sql_child} ) as rs  ";

			$rs = $wpdb->get_results($sql);

			if (isset($_REQUEST['debug'])) {
				$data['sql_q']  = str_replace(array("\n", "\t"), ' ', $sql);
				$data['sqlerr'] = $wpdb->last_error;
			}

			$data['results'] = array();
			foreach ($rs as $i => $row) {
				$referring_provider_code = substr($row->doc_last, 0, 5) . substr($row->doc_first, 0, 1);
				$referring_provider_code = strtoupper($referring_provider_code);

				$cpt_code = tr_billing_get_cpt_code($row);
				if (empty($cpt_code) && !$show_billing_codetoo) continue;

				if (empty($row->approve_date)) {
					$row->approve_date = date('Y-m-d', strtotime('+1day', strtotime($row->doc)));
				}

				$parent_name = '';
				$parent_dob = '';
				if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
					$parent_name = $row->ins_last . ', ' . $row->ins_first;
					$parent_dob = $row->ins_dob;

					if (!empty($parent_dob)) {
						$parent_dob = date('m/d/Y', strtotime($parent_dob));
					}
				}


				$testes_resulted = explode('|', strtoupper($row->testes_resulted));

				if (in_array($row->btype, array('pcr', 'blood', 'tox'))) {
					$testids = explode(',', $row->panel_tests_requested);
					$panel = array();
					$groupids = array();
					foreach ($testids as $testid) {
						$testid = trim($testid);
						$testid = str_replace('+', '', $testid);
						if (stripos($testid, 'group_') !== false) {
							$group_id = str_ireplace('group_', '', $testid);
							if (!$settings['eapi_groups']) {
								if (!in_array($group_id, $groupids) && isset($all_groups[$group_id])) {
									$panel[] =  $all_groups[$group_id]->tg_name;
									$groupids[] = $group_id;
								}
							}
						} else if (isset($all_tests[$testid])) {
							if (
								in_array(strtoupper($all_tests[$testid]->te_name), $testes_resulted)
								|| in_array(strtoupper($all_tests[$testid]->te_abrev), $testes_resulted)
								|| in_array(strtoupper($all_tests[$testid]->te_abrev2), $testes_resulted)
							) {
								$group_id = $all_tests[$testid]->te_group_id;

								if ($settings['eapi_groups']) {
									$group_name = '';
									$subgroup_name = '';
									if ($all_groups[$group_id]->tg_parent == 0) {
										$group_name = $all_groups[$group_id]->tg_name;
									} else {
										$subgroup_name = $all_groups[$group_id]->tg_name;
										$group_name = $all_groups[$all_groups[$group_id]->tg_parent]->tg_name;
									}
									$panel[] = array(
										'Test' => $all_tests[$testid]->te_name,
										'Group' => $group_name,
										'Sub_Group' => $subgroup_name,
									);
								} else {
									if ($group_id > 0 && !in_array($group_id, $groupids) && isset($all_groups[$group_id])) {
										$panel[] = $all_groups[$group_id]->tg_name;
										$groupids[] = $group_id;

										if ($all_groups[$group_id]->tg_parent == 0) {
											$parent_groups[$group_id] = $all_groups[$group_id]->tg_name;
										} else {
											$child_groups[$group_id] = $all_groups[$group_id]->tg_name;
										}
									}
									$panel[] = $all_tests[$testid]->te_name;
								}
							}
						} else if ($row->btype == 'tox') {
							if (isset($all_tests['TOX_' . strtoupper($testid)])) {
								$testob 	= $all_tests['TOX_' . strtoupper($testid)];
								$group_id 	= $testob->te_group_id;
								$group_name = '';
								$subgroup_name = '';
								if ($all_groups[$group_id]->tg_parent == 0) {
									$group_name = $all_groups[$group_id]->tg_name;
								} else {
									$subgroup_name = $all_groups[$group_id]->tg_name;
									$group_name = $all_groups[$all_groups[$group_id]->tg_parent]->tg_name;
								}

								if ($settings['eapi_groups']) {
									$panel[] = array(
										'Test' => $testob->te_name,
										'Group' => $group_name,
										'Sub_Group' => $subgroup_name,
									);
								}
							}
							//get screening test
							$screenings = tr_pdd_get_result_validity(1, $row->batch_created, $row->mid);
							foreach ($screenings as $scr) {
								if ($settings['eapi_groups'] && $scr['checked'] == 1) {
									$panel[] = array(
										'Test' => $scr['u_name'],
										'Group' => 'Screening',
										'Sub_Group' => '',
									);
								}
							}
						}
					}

					if (!$settings['eapi_groups']) {
						if ($row->btype == 'tox') {
							$panel = $row->panel_tests_requested;
						} else {
							$panel = implode(', ', $panel);
						}
					}
				} else {
					if (!$settings['eapi_groups']) {
						$panel = $row->panel_tests_requested;
					} else {
						$panel = explode(', ', $row->panel_tests_requested);
					}
				}




				$rowdata = array(
					'order_number' => $row->assen,
					'ignite_lis' => $row->assen,
					'reqtype' => $row->btype,
					'facility' => $row->requesting_practice,
					'facility_code' => $row->cli_facility_code,
					'provider_code' => $row->doc_provider_code,
					'facility_city' => $row->cli_city,
					'clinic_city' => $row->cli_city,

					'dagnostic_codes' => $row->dagnostic_codes1,

					'paatient_full_name' => $row->pa_last . ',' . $row->pa_first,
					'patient_first_name' => $row->pa_first,
					'patient_last_name' => $row->pa_last,
					'patient_address' => $row->pa_address,
					'patient_city' => $row->pa_city,
					'patient_state' => $row->pa_state,
					'patient_zip' => $row->pa_zip,
					'patient_ssn' => $row->pa_ssn,
					'patient_dob' => date('m/d/Y', strtotime($row->pa_dob)),
					'patient_mrn' => $row->pa_mrn,
					'patient_gender' => $row->pa_gender,
					'patient_phone' => $row->pa_phone,
					'patient_code' => $row->pa_code,
					'patient_email' => $row->pa_email
				);

				if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {

					$rowdata['patient_race'] = isset($races[$row->pa_race]) ? $races[$row->pa_race] : '';
					$rowdata['patient_ethnicity'] = isset($Ethnicitis[$row->pa_ethnicity]) ? $Ethnicitis[$row->pa_ethnicity] : '';
					$rowdata['patient_sexual_oriention'] = $row->pa_sexual_oriention;
					$rowdata['patient_gender_identity'] = $row->pa_gender_identity;
				}
				$rowdata['referring_provider_code'] = $referring_provider_code;
				$rowdata['parent_spouse_name'] = $parent_name;
				$rowdata['parent_dob'] = $parent_dob;
				$rowdata['doctor_first'] = $row->doc_first;
				$rowdata['doctor_last'] = $row->doc_last;
				$rowdata['doctor_npi'] = $row->doc_npi;
				$rowdata['doctor_phone'] = $row->doc_phone;

				$rowdata['cpt_code'] = $cpt_code;
				$rowdata['dos'] = date('m/d/Y', strtotime($row->doc));
				$rowdata['collection'] = date('m/d/Y', strtotime($row->doc));
				$rowdata['received'] = $row->spec_received;
				$rowdata['result_date'] = date('m/d/Y', strtotime($row->approve_date));
				$rowdata['panels'] = $panel;

				if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
					$rowdata['insurance_code'] = $row->ins_code;
				}
				$rowdata['insurance'] = $row->insurance;
				$rowdata['carrier_code'] = $row->payer_mnemonic;
				$rowdata['member_id'] = str_replace('-', '', $row->ins_member_id);

				if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
					$rowdata['insurance_code_2'] = $row->ins_code2;
				}
				$rowdata['insurance_2'] = $row->ins_company2;
				$rowdata['carrier_code2'] = $row->payer_mnemonic2;
				$rowdata['insurance_member_id_2'] = str_replace('-', '', $row->ins_member_id2);

				//ion
				$rowdata['Primary_Insured_Relationship_Code'] = $row->ins_relation;
				$rowdata['Primary_Insured_Relationship_Desc'] = '';
				$rowdata['Primary_Insured_Last_Name'] = $row->ins_last;
				$rowdata['Primary_Insured_First_Name'] = $row->ins_first;
				$rowdata['Primary_Insured_Middle_Name'] = '';
				$rowdata['Primary_Insured_Gender'] = $row->pa_gender;
				$rowdata['Primary_Insured_DOB'] = date('m/d/Y', strtotime($row->pa_dob));
				$rowdata['Primary_Insured_Address1'] = $row->ins_address;
				$rowdata['Primary_Insured_Address2'] = '';
				$rowdata['Primary_Insured_City'] = $row->ins_city;
				$rowdata['Primary_Insured_State'] = $row->ins_state;
				$rowdata['Primary_Insured_Zip'] = $row->ins_zip;

				//ion
				$rowdata['Secondary_Insured_Relationship_Code'] = $row->ins_relation2;
				$rowdata['Secondary_Insured_Relationship_Desc'] = '';
				$rowdata['Secondary_Insured_Last_Name'] = $row->ins_last2;
				$rowdata['Secondary_Insured_First_Name'] = $row->ins_first2;
				$rowdata['Secondary_Insured_Middle_Name'] = '';
				$rowdata['Secondary_Insured_Gender'] = !empty($row->ins_last2) ? $row->pa_gender : '';
				$rowdata['Secondary_Insured_DOB'] = !empty($row->ins_last2) ? date('m/d/Y', strtotime($row->pa_dob)) : '';
				$rowdata['Secondary_Insured_Address1'] = $row->ins_address2;
				$rowdata['Secondary_Insured_Address2'] = '';
				$rowdata['Secondary_Insured_City'] = $row->ins_city2;
				$rowdata['Secondary_Insured_State'] = $row->ins_state2;
				$rowdata['Secondary_Insured_Zip'] = $row->ins_zip2;

				$rowdata['Performing_Facility_ID'] = $row->lab_ID;
				$rowdata['Performing_Facility_Name'] = $row->lab_name;


				if ($settings['eapi_sales_rep']) {
					$rowdata['Sales_Rep_Name'] = $all_sales_reps[$row->{'cli_rep-ID'}];
				}


				$data['results'][] = $rowdata;
			}
			//$data['error'] = $wpdb->last_error;
			$data['count'] = count($data['results']);
			//$data['runtime'] = microtime(true) - $start_time;


			//log
			/*
			if(!file_exists(WP_CONTENT_DIR.'/log/resapi/'))
			{
				mkdir(WP_CONTENT_DIR.'/log/resapi/');
			}
			file_put_contents(WP_CONTENT_DIR.'/log/resapi/'.date_i18n('Ymd-H:i').'-'.$date.'.json',json_encode($data));
			*/
		} else {
			$data = false;
		}


		$this->response(200, $data);
	}

	/**
	 * @param string $key
	 * @param string $datefrom
	 * @param string $dateto
	 * @param string $ssn
	 * @param string $patient
	 * @param string $accession
	 * @return array json
	 * ex: /restapi/billing2/?key=abc&datefrom=2020-10-25&dateto=2020-11-01
	 */

	public function request_billing2()
	{
		global $wpdb;
		$data = array('count' => 0, 'results' => array());

		if (!$this->checkKey($this->request_api_key)) {
			$data['error'] = 'Wrong key';
		} else if ($this->method == 'GET') {

			$settings       = tr_billing_get_settings();
			$races = array(
				'' => '',
				'1002-5' => 'American Indian or Alaska  Native',
				'2028-9' => 'Asian',
				'2054-5' => 'Black / African American',
				'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
				'2106-3' => 'White',
				'2131-1' => 'Other'
			);
			$Ethnicitis = array(
				''  => '',
				'H' => 'Hispanic or Latino',
				'N' => 'Not Hispanic or Latino',
				'U' => 'Unknown'
			);

			$all_groups  	= array();
			$rs = $wpdb->get_results("select tg_ID, tg_name, tg_parent,
                            tg_notestcheckbox, tg_only_group, tg_is_covid
                            from ci_test_groups 
                            where tg_type in( 'pcr', 'blood')
                            ");
			foreach ($rs as $r) {
				$all_groups[$r->tg_ID] = $r;
				if ($r->tg_notestcheckbox || $r->tg_only_group || $r->tg_is_covid == 1) {
					$groups_notestcb[$r->tg_ID] = array();
				}
				if ($r->tg_is_covid == 1) {
					$covid_group_ids[] = $r->tg_ID;
				}
			}

			$datefrom 	= isset($this->params['datefrom']) ? $this->params['datefrom'] : $this->params[0];
			$dateto 	= isset($this->params['dateto']) ? $this->params['dateto'] : $this->params[1];
			$ssn 		= isset($this->params['ssn']) ? $this->params['ssn'] : $this->params[2];
			$patient_name = isset($this->params['patient']) ? $this->params['patient'] : $this->params[3];
			$accession 	= isset($this->params['accession']) ? $this->params['accession'] : $this->params[4];

			$sql_child = array();

			$where = " and ( (re.id > 0 and re.estatus in ('C','F')) or (re.id is null and ba.result_pdf!='' ) ) ";


			if (!empty($ssn)) {
				$where .= " and pa.pa_ssn = '{$ssn}' ";
			}
			if (!empty($accession)) {
				$where .= " and ba.assen = '{$accession}' ";
			} else if (!empty($patient_name)) {
				list($first_name, $last_name) = explode(' ', $patient_name);
				$where .= " and ((pa.pa_first ='{$first_name}' and pa.pa_last = '{$last_name}' ) 
								or ba.patient_name like '%{$patient_name}%' )";
			}

			$where .= " and ((re.id > 0  and re.approve_date >= '{$datefrom}' and re.approve_date <= '{$dateto} 23:59:59') 
                or (re.id is null and ba.result_pdf!='' and  ba.doc >= '{$datefrom}' and ba.doc <= '{$dateto} 23:59:59' ))";


			//check exclude lab
			$exclude_labids = $wpdb->get_col("select lab_ID from ci_labs where lab_send_billing = 0");
			if (count($exclude_labids) > 0) {
				$exclude_labids_str = implode(',', $exclude_labids);
				$where .= " and ba.ref_lab_ID not in ({$exclude_labids_str}) ";
			}



			foreach (tr_pdd_get_batch_types() as $t) {

				$batch_tbl = tr_pdd_get_table_by_btype($t);
				$report_tbl = tr_pdd_get_table_report_by_btype($t);

				$sql_child[] = "(select 
                        ba.dagnostic_codes1,
                        ba.assen,
                        ba.mid,
                        ba.requesting_practice,
                        ba.panel_tests_requested,
                        ba.doc,
                        ba.insurance,

                        pal.payer_mnemonic,

                        ins.ins_member_id,
						ins.ins_code,
                        ins.ins_group_id,
                        ins.ins_relation,
                        ins.ins_last,
                        ins.ins_first,
                        ins.ins_dob,

						ins2.ins_company ins_company2,
						ins2.ins_code ins_code2, 
						ins2.ins_member_id ins_member_id2,
						ins2.ins_group_id ins_group_id2,
						ins2.ins_relation ins_relation2,
						ins2.ins_last ins_last2,
						ins2.ins_first ins_first2,
						ins2.ins_dob ins_dob2,
						pal2.payer_mnemonic as payer_mnemonic2,
                        
                        pa.*,
                        
                        pah.pa_first as orig_first,
                        pah.pa_last as orig_last,
                        pah.pa_address as orig_address,
                        pah.pa_city as orig_city,
                        pah.pa_state as orig_state,
                        pah.pa_zip as orig_zip,
                        pah.pa_ssn as orig_ssn,
                        pah.pa_dob as orig_dob,
                        
                        re.id,
                        re.approve_date,

						cli.cli_clinic,
						cli.cli_facility_code,
						cli.cli_city,

                        doc.doc_last,
                        doc.doc_first,
                        doc.doc_npi,
                        doc.doc_phone,
                        doc.doc_provider_code,

                        '{$t}' as btype
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    left join ci_patients_history pah On pah.pa_ID = pa.pa_ID 
                                                and pah.pa_modified >= '{$datefrom}'
                                                and pah.pa_modified <= '{$dateto}'
                    join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    join ci_clinic cli On cli.cID = ba.cli_ID
                    left join ci_insurances ins ON ins.ins_ID = ba.insurance1
					left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
                    left join ci_payer_list pal ON pal.payer_name = ba.insurance
					left join ci_payer_list pal2 ON pal2.payer_name = ins2.ins_company
                    where 1=1 {$where} group by ba.mid order by ba.mid desc)";
			}

			$sql_child = implode("\n UNION ALL \n", $sql_child);

			$sql = "select * from ( {$sql_child} ) as rs  ";

			$rs = $wpdb->get_results($sql);

			if (isset($_GET['debug'])) {
				$data['sql_q'] = str_replace(array("\n", "\t"), ' ', $sql);
				$data['sqlerr'] = $wpdb->last_error;
			}

			$data['results'] = array();
			foreach ($rs as $i => $row) {

				$referring_provider_code = substr($row->doc_last, 0, 5) . substr($row->doc_first, 0, 1);
				$referring_provider_code = strtoupper($referring_provider_code);

				$cpt_code = tr_billing_get_cpt_code($row);
				//if (empty($cpt_code)) continue;

				if (empty($row->approve_date)) {
					$row->approve_date = date('Y-m-d', strtotime('+1day', strtotime($row->doc)));
				}

				$parent_name = '';
				$parent_dob = '';
				if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
					$parent_name = $row->ins_last . ', ' . $row->ins_first;
					$parent_dob = $row->ins_dob;

					if (!empty($parent_dob)) {
						$parent_dob = date('m/d/Y', strtotime($parent_dob));
					}
				}



				$testes_resulted = explode('|', strtoupper($row->testes_resulted));
				if (in_array($row->btype, array('pcr', 'blood'))) {
					$testids = explode(',', $row->panel_tests_requested);
					$panel = array();
					$groupids = array();
					foreach ($testids as $testid) {
						$testid = trim($testid);
						$testid = str_replace('+', '', $testid);
						if (stripos($testid, 'group_') !== false) {
							$group_id = str_ireplace('group_', '', $testid);
							if (!in_array($group_id, $groupids) && isset($all_groups[$group_id])) {
								$panel[] =  $all_groups[$group_id]->tg_name;
								$groupids[] = $group_id;
							}
						} else if (isset($all_tests[$testid])) {
							if (
								in_array(strtoupper($all_tests[$testid]->te_name), $testes_resulted)
								|| in_array(strtoupper($all_tests[$testid]->te_abrev), $testes_resulted)
								|| in_array(strtoupper($all_tests[$testid]->te_abrev2), $testes_resulted)
							) {
								$group_id = $all_tests[$testid]->te_group_id;
								if ($group_id > 0 && !in_array($group_id, $groupids) && isset($all_groups[$group_id])) {
									$panel[] = $all_groups[$group_id]->tg_name;
									$groupids[] = $group_id;
								}
								$panel[] = $all_tests[$testid]->te_name;

								if (stripos($all_tests[$testid]->te_name, 'cov-') !== false) {
									$has_covid = true;
								}
							}
						}
					}
					$panel = implode(', ', $panel);
				} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
					$panel = $row->panel_tests_requested;
				} else if (strpos($row->panel_tests_requested, '+') === false) {
					$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
				} else {
					$panel = $row->panel_tests_requested;
				}

				$rowdata = array(
					'order_number' => $row->assen,
					'ignite_lis' => $row->assen,
					'dagnostic_codes' => $row->dagnostic_codes1,
					'carrier_code' => $row->payer_mnemonic,
					'facility' => $row->requesting_practice,
					'facility_code' => $row->cli_facility_code,
					'provider_code' => $row->doc_provider_code,
					'facility_city' => $row->cli_city,
					'clinic_city' => $row->cli_city,

					'patient_full_name' => $row->pa_last . ',' . $row->pa_first,
					'patient_first_name' => $row->pa_first,
					'patient_last_name' => $row->pa_last,
					'patient_address' => $row->pa_address,
					'patient_city' => $row->pa_city,
					'patient_state' => $row->pa_state,
					'patient_zip' => $row->pa_zip,
					'patient_ssn' => $row->pa_ssn,
					'patient_dob' => date('m/d/Y', strtotime($row->pa_dob)),
					'patient_mrn' => $row->pa_mrn,
					'patient_gender' => $row->pa_gender,
					'patient_phone' => $row->pa_phone,
					'patient_email' => $row->pa_email
				);

				$rowdata['patient_mrn'] = $row->pa_mrn;

				if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {

					$rowdata['patient_race'] = isset($races[$row->pa_race]) ? $races[$row->pa_race] : '';
					$rowdata['patient_ethnicity'] = isset($Ethnicitis[$row->pa_ethnicity]) ? $Ethnicitis[$row->pa_ethnicity] : '';
					$rowdata['patient_sexual_oriention'] = $row->pa_sexual_oriention;
					$rowdata['patient_gender_identity'] = $row->pa_gender_identity;
				}
				$rowdata['orig_patient_first_name'] = $row->orig_first;
				$rowdata['orig_patient_last_name'] =  $row->orig_last;
				$rowdata['orig_patient_dob'] = !empty($row->orig_dob) ? date('m/d/Y', strtotime($row->orig_dob)) : '';

				$rowdata['referring_provider_code'] = $referring_provider_code;
				$rowdata['parent_spouse_name'] = $parent_name;
				$rowdata['parent_dob'] = $parent_dob;
				$rowdata['doctor_first'] = $row->doc_first;
				$rowdata['doctor_last'] = $row->doc_last;
				$rowdata['doctor_npi'] = $row->doc_npi;
				$rowdata['doctor_phone'] = $row->doc_phone;

				$rowdata['cpt_code'] = $cpt_code;
				$rowdata['dos'] = date('m/d/Y', strtotime($row->doc));
				$rowdata['collection'] = date('m/d/Y', strtotime($row->doc));
				$rowdata['received'] = $row->spec_received;
				$rowdata['result_date'] = date('m/d/Y', strtotime($row->approve_date));
				$rowdata['panels'] = $panel;
				if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
					$rowdata['insurance_code'] = $row->ins_code;
				}
				$rowdata['insurance'] = $row->insurance;
				$rowdata['member_id'] = str_replace('-', '', $row->ins_member_id);

				if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
					$rowdata['insurance_code_2'] = $row->ins_code2;
				}
				$rowdata['insurance_2'] = $row->ins_company2;
				$rowdata['carrier_code2'] = $row->payer_mnemonic2;
				$rowdata['insurance_member_id_2'] = str_replace('-', '', $row->ins_member_id2);


				$data['results'][] = $rowdata;
			}
			//$data['error'] = $wpdb->last_error;
			$data['count'] = count($data['results']);
		} else {
			$data = false;
		}

		$this->response(200, $data);
	}
}
