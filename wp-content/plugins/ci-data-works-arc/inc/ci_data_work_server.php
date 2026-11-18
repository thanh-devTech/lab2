<?php


/**
 * This demo webservice shows you how to work with PhpWsdl
 *
 * @service DataWork
 */
class CI_Data_Work_Server extends  CI_Data_Work_Xml
{


	/**
	 * put Tox Order 
	 * xml : [demoxml url="/wsdl?demo=tox.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_req($xml, $key)
	{
		return $this->_put_req($xml, $key, 'tox');
	}


	/**
	 * put PGx Order 
	 * xml : [demoxml url="/wsdl?demo=pgx.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_pgx($xml, $key)
	{
		return $this->_put_req($xml, $key, 'dna');
	}

	/**
	 * put Blood Order 
	 *  xml : [demoxml url="/wsdl?demo=blood.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_blood($xml, $key)
	{
		return $this->_put_req($xml, $key, 'blood');
	}


	/**
	 * put Allergy Order 
	 * xml : [demoxml url="/wsdl?demo=allergy.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_allergy($xml, $key)
	{
		return $this->_put_req($xml, $key, 'allergy');
	}

	/**
	 * put RPP_GI 
	 * xml : [demoxml url="/wsdl?demo=rpp_gi.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_rpp_gi($xml, $key)
	{
		return $this->_put_req($xml, $key, 'rpp_gi');
	}

	/**
	 * put PCR 
	 * xml : [demoxml url="/wsdl?demo=pcr.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_pcr($xml, $key)
	{
		return $this->_put_req($xml, $key, 'pcr');
	}

	/**
	 * put Requisition 
	 * xml : [demoxml url="/wsdl?demo=requisition.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_requisition($xml, $key)
	{
		return $this->_put_req($xml, $key, 'req');
	}

	/**
	 * put genetics 
	 *  xml : [demoxml url="/wsdl?demo=genetics.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_genetics($xml, $key)
	{
		return $this->_put_req($xml, $key, 'gene');
	}

	/**
	 * put Women Health 
	 * xml : [demoxml url="/wsdl?demo=wom.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_women_health($xml, $key)
	{
		return $this->_put_req($xml, $key, 'wom');
	}


	/**
	 * put Tox Results 
	 * xml : [demoxml url="/wsdl?demo=result_tox.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_result_tox($xml, $key)
	{
		global $wpdb;

		$return = array(
			'status' => 'OK',
			'msg' => ''
		);
		$assen = '';

		//check key
		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong Key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'put_result_tox', 'tox'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$data = false;
			try {
				$xml = trim($xml);
				//file_put_contents(WP_CONTENT_DIR.'/put_result.xml',$xml);
				//trpdd_log_form('put_result_tox',$xml);

				$data = @simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = @simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}

			$assen 		= (string) $data->assen;
			$estatus 	= isset($data->estatus) ? (string) $data->estatus : '';
			$approve_date = isset($data->approve_date) ? (string) $data->approve_date : '';
			//check report
			if (empty($assen)) {
				$return['status'] = 'EMPTY_BARCODE';
				$return['msg'] = 'empty barcode';
			} else {
				$report = $wpdb->get_row("select * from ci_reports where patient_id = '{$assen}'", ARRAY_A);
				$batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}'", ARRAY_A);

				if ($batch) {
					$mid = $batch['mid'];
					$need_add_report = false;
					$pdf_files = array();

					//chemistry
					if (isset($data->chemistry) && count($data->chemistry->test) > 0) {
						$urine_test = $wpdb->get_results("select * from ci_urine_validity");
						$all_urine = array();
						foreach ($urine_test as $t) {
							if (!empty($t->u_abreviation)) {
								$t->u_abreviation = strtoupper($t->u_abreviation);
								$all_urine[$t->u_abreviation] = $t;
							}
							if (!empty($t->u_abreviation_mc6410)) {
								$t->u_abreviation_mc6410 = strtoupper($t->u_abreviation_mc6410);
								$all_urine[$t->u_abreviation_mc6410] = $t;
							}
							if (!empty($t->u_loinc_code)) {
								$t->u_loinc_code = strtoupper($t->u_loinc_code);
								$all_urine[$t->u_loinc_code] = $t;
							}
						}
						foreach ($data->chemistry->test as $t) {
							$name = (string)$t->name;
							$conc = (string)$t->value;
							$conc = str_replace(' ', '', $conc);
							$conc = preg_replace("/[^0-9-.,]/", '', $conc);
							// $comments = (string)$t->comments;


							$name = strtoupper($name);

							$tid = $all_urine[$name];
							if ($tid) {
								//api_type = 1 : wsdl type

								$wpdb->query("INSERT INTO ci_result_test_speciments (mid,testid,conc,checked,api_type) 
									values ('{$batch['mid']}','{$tid->u_ID}','{$conc}',1,1) 
									ON DUPLICATE KEY UPDATE conc = '{$conc}', api_type = 1 ");
							} else {
								$return['status'] = 'CHEMISTRY_NOTFOUND';
								$return['msg'] = 'Abreviation ' . $name . ' not exists';
							}
							$need_add_report = true;
						}
					}

					if (isset($data->screenings)) {

						foreach ($data->screenings->test as $scr_arr) {
							$scr_arr = (array)$scr_arr;

							$testname = (string) $scr_arr['name'];
							$testname = trim($testname);
							$abreviation = (string) $scr_arr['abbreviation'];
							$abreviation = trim($abreviation);
							$loinc_code = (string) $scr_arr['loinc_code'];
							$loinc_code = trim($loinc_code);

							$wherename = "u_name = '{$testname}' ";
							if (!empty($abreviation)) {
								$wherename .= " or u_abreviation = '{$abreviation}' ";
							}
							if (!empty($loinc_code)) {
								$wherename .= " or u_loinc_code = '{$loinc_code}' ";
							}

							$tid = $wpdb->get_var("select u_ID from ci_urine_validity where u_status = 1 and (" . $wherename . ")");

							if ($tid > 0) {
								$need_add_report = true;
								$exists = $wpdb->get_var("select mid from ci_result_test_speciments where mid = {$mid} and testid={$tid} ");

								$update = array(
									'mid' => $mid,
									'testid' => $tid,
									'checked' => 1,
									'api_type' => 1,
								);

								if (isset($scr_arr['conc'])) {
									$update['conc'] = $scr_arr['conc'];
								}
								if (isset($scr_arr['outcome'])) {
									$update['outcome'] = $scr_arr['outcome'];
								}
								if (isset($scr_arr['comments'])) {
									$update['comments'] = $scr_arr['comments'];
								}
								if ($exists) {
									$wpdb->update('ci_result_test_speciments', $update, array('mid' => $mid, 'testid' => $tid));
								} else if ((isset($update['conc']) && $update['conc'] !== '') || $update['checked'] || !empty($update['comments'])) {
									$wpdb->insert('ci_result_test_speciments', $update);
								}
							}
						}
					}

					if (isset($data->result_pdfs)) {

						$pdf_files = array();
						foreach ((array)$data->result_pdfs as $pdf_url) {
							$need_add_report = true;
							$pdf_url = (string)$pdf_url;
							$pdf_content = file_get_contents($pdf_url);
							if (strlen($pdf_content) > 200) {
								$pdf_name = strtolower(basename($pdf_url));
								$pdf_path = tr_get_result_upload_dir('path') . '/' . $pdf_name;
								file_put_contents($pdf_path, $pdf_content);
								$pdf_files[] = $pdf_name;
							}
						}

						if (count($pdf_files) > 0) {
							$oldfiles = !empty($batch['result_pdf']) ? explode(',', $batch['result_pdf']) : array();
							$pdf_files = array_merge($oldfiles, $pdf_files);
							$new_values = array();
							foreach ($pdf_files as $k => $f) {
								if (!empty($f) && !in_array(strtolower($f), $new_values)) {
									$new_values[] = $f;
								}
							}
							$wpdb->update('ci_batches', array('result_pdf' => implode(',', $new_values)), array('mid' => $batch['mid']));
						}
					}

					if ($need_add_report || isset($data->drugs)) {

						$import_id = isset($data->id) ? (int)$data->id : time();

						if ($keyobj['wsdl_dont_approve']) {
							$estatus = 'M';
						}
						$instrument = (string) $data->instrument;
						$report_data =  array(
							'patient_id' => $assen,
							'created' => date_i18n('Y-m-d H:i:s'),
							'created_date' => date_i18n('Y-m-d'),
							'upload_files' => 'api',
							'instrument' => $instrument,
							're_import_ID' => $import_id,
						);
						$approved = false;
						if (!$report['id']) {
							if ($estatus == 'C') {
								$report_data['estatus'] = 'C';
								$report_data['approve_date'] = $approve_date;
								$approved = true;
							} else {
								$report_data['estatus'] = '';
							}
							$wpdb->insert('ci_reports', $report_data);
							$report['id'] = $wpdb->insert_id;
						} else if ($report['estatus'] !== 'C' && $estatus === 'C') {
							$approved = true;
							$report_data = array(
								'estatus' => 'C',
								'approve_date' => $approve_date,
								're_import_ID' => $import_id,
							);

							$wpdb->update('ci_reports', $report_data, array('id' => $report['id']));
						} else if ($report['estatus'] == 'C' && empty($report['approve_date'])) {
							$report_data = array(
								'approve_date' => $approve_date,
								're_import_ID' => $import_id,
							);
							$wpdb->update('ci_reports', $report_data, array('id' => $report['id']));
						} else if ($report['estatus'] == 'C' && $keyobj['wsdl_dont_approve']) {
							$report_data = array(
								'estatus' => 'M',
								're_import_ID' => $import_id,
							);
							$wpdb->update('ci_reports', $report_data, array('id' => $report['id']));
						}

						if ($approved) {
							trpdd_log_user(-1, 'approve_rs', $batch['assen'], $batch['mid'], 'tox',  '', '', $batch['assen']);
						}

						$return['ID'] = $report['id'];
					}


					if (isset($data->drugs)) {

						if ($report['id'] > 0) {
							$all_tests = array();
							$tes = $wpdb->get_results("select te_ID, te_name, te_abrev, te_loinc_code 
										from ci_test_panels 
										where te_type ='tox' 
										and te_in_req = 1 ");
							foreach ($tes as $t) {
								$t->te_name = strtoupper($t->te_name);
								$all_tests[$t->te_name] = $t;
								if (!empty($t->te_abrev)) {
									$t->te_abrev = strtoupper($t->te_abrev);
									$all_tests[$t->te_abrev] = $t;
								}
								if (!empty($t->te_loinc_code)) {
									$t->te_loinc_code = strtoupper($t->te_loinc_code);
									$all_tests[$t->te_loinc_code] = $t;
								}
							}

							foreach ($data->drugs as $t) {
								$name 		= (string)$t->name;
								$abrev 		= isset($t->abrev) ? (string)$t->abrev : '';
								$value 		= (string)$t->value;
								$pos_neg 	= isset($t->pos_neg) ? (string)$t->pos_neg : '';
								if (isset($t->{'pos-neg'})) {
									$pos_neg = (string)$t->{'pos-neg'};
								}
								$comments 	= (string)$t->comments;
								$cutoff 	= isset($t->cutoff) ? (string)$t->cutoff : '';
								$cutoffs 	= isset($t->cutoffs) ? $t->cutoffs : new stdClass();

								$name = strtoupper($name);
								$testdb = null;
								if (isset($all_tests[$name])) {
									$testdb = $all_tests[$name];
									$name = $testdb->te_name;
								} else if (isset($all_tests[strtoupper($abrev)])) {
									$testdb = $all_tests[strtoupper($abrev)];
									$name = $testdb->te_name;
								}

								if (!empty($cutoffs) && isset($cutoffs->high) && !empty($instrument)) {

									$cutoffmap = array(
										'high' => 'cu_upper_limit',
										'low' => 'cu_cutoff',
										'medium' => 'cu_medium',
										'critical_high' => 'cu_critical_high',
										'critical_low' => 'cu_critical_low',
										'invalid' => 'cu_invalid',
										'lqc' => 'cu_lqc',
										'mqc' => 'cu_mqc',
										'hqc' => 'cu_hqc',
									);

									if ($testdb &&  $testdb->te_ID > 0) {
										$cutoffid = $wpdb->get_var("select cu_te_ID 
										from ci_test_panels_cutoffs where cu_te_ID = {$testdb->te_ID} 
										and cu_ms = '{$instrument}' ");

										if (!empty($wpdb->last_error)) {
											$return['sqlerror'][] = $wpdb->last_error;
										}
										$cutoffarr = array(
											'cu_te_ID' => $testdb->te_ID,
											'cu_ms' => $instrument,
										);
										foreach ($cutoffmap as $ck => $cdbk) {
											if (isset($cutoffs->{$ck})) {
												$vlc = (string) $cutoffs->{$ck};
												if (!empty($vlc)) {
													$cutoffarr[$cdbk] = $vlc;
												}
											}
										}
										if (empty($cutoffid)) {
											$wpdb->insert('ci_test_panels_cutoffs', $cutoffarr);
											if (!empty($wpdb->last_error)) {
												$return['sqlerror'][] = $wpdb->last_error;
											}

											$return['added_cutoff_id'][] = $testdb->te_ID . ' => ' . $wpdb->insert_id;
										} else {
											$wpdb->update('ci_test_panels_cutoffs', $cutoffarr, array('cu_te_ID' => $testdb->te_ID, 'cu_ms' => $instrument));
											if (!empty($wpdb->last_error)) {
												$return['sqlerror'][] = $wpdb->last_error;
											}

											$return['updated_cutoff_id'][] = $testdb->te_ID . ' => ' . $cutoffid;
										}
									} else {
										$return['errors'][] = 'no testid for - ' . $name;
									}
								}

								$rs_id = $wpdb->get_var("select id from ci_results where report_id = {$report['id']} and drug='{$name}' ");

								$rs_data = array(
									'report_id' => $report['id'],
									'drug' => $name,
									'conc' => $value,
									'pos-neg' => $pos_neg,
									'comments' => $comments
								);
								if (!empty($cutoff)) {
									$rs_data['cutoff'] = $cutoff;
								}
								if ($rs_id > 0) {
									$where = array(
										'id' => $rs_id,
									);
									$wpdb->update('ci_results', $rs_data, $where);
								} else {
									$wpdb->insert('ci_results', $rs_data);
								}
							}
						}
					}




					//$log['$urine_insert'] = $urine_insert;
					//file_put_contents(WP_CONTENT_DIR.'/log.txt',json_encode($log));


				} else {
					$return['status'] = 'NOT_EXISTS';
					$return['msg'] = 'The Order ' . $assen . ' not exists!';
				}
			}
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('put_result_tox', $key_id, $assen, $return['status'], $xml, $xmlout);
		return $xmlout;
	}

	/**
	 * put Blood Results 
	 * xml : [demoxml url="/wsdl?demo=result_blood.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_result_blood($xml, $key)
	{
		global $wpdb;

		$return = array(
			'status' => 'OK',
			'msg' => ''
		);
		$btype = 'blood';
		$assen = '';

		//check key
		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong Key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'put_result_blood', 'blood'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$data = false;
			try {
				$xml = trim($xml);
				$data = simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}

			$assen 		= (string) $data->assen;
			$instrument = (string) $data->instrument;
			$notes_report 	= (string) $data->notes_report;
			$batch_id 	= (string) $data->batch_id;
			$estatus 	= isset($data->estatus) ? (string) $data->estatus : '';
			$approve_date = isset($data->approve_date) ? (string) $data->approve_date : '';
			$final_results = isset($data->final) ? (string) $data->final : '';
			$created 	= isset($data->created) ? (string) $data->created : date_i18n('Y-m-d H:i:s');
			$created_date = isset($data->created_date) ? (string) $data->created_date : date_i18n('Y-m-d');

			if (empty($approve_date)) {
				$approve_date = date_i18n('Y-m-d H:i:s');
			}
			//check report
			if (empty($assen)) {
				$return['status'] = 'EMPTY_BARCODE';
				$return['msg'] = 'empty barcode';
			} else {
				$report = $wpdb->get_row("select * from ci_reports_blood where patient_id = '{$assen}'", ARRAY_A);
				$batch = $wpdb->get_row("select * from ci_batches_blood where assen = '{$assen}'", ARRAY_A);

				if ($batch) {
					$approved = false;
					$update_batch = array();
					if (!empty($batch_id) && empty($batch['batch_id'])) {
						$update_batch['batch_id'] = $batch_id;
					}

					$import_id = isset($data->id) ? (int)$data->id : time();

					if (!$report['id']) {


						$report_data = array(
							'patient_id' => $assen,
							'created' => $created,
							'created_date' => $created_date,
							'upload_files' => 'api',
							'instrument' => $instrument,
							're_import_ID' => $import_id,
						);

						if (!empty($final_results)) {
							$report_data['final_results'] = $final_results;
						}

						if ($estatus == 'C') {
							$report_data['estatus'] = 'C';
							$report_data['approve_date'] = $approve_date;
							$approved = true;
						}
						$wpdb->insert('ci_reports_blood', $report_data);

						$report['id'] = $wpdb->insert_id;
					} else if ($report['estatus'] !== 'C' && $estatus === 'C') {
						$approved = true;
						$report_data = array(
							'estatus' => 'C',
							'approve_date' => $approve_date,
							're_import_ID' => $import_id,
						);

						$wpdb->update('ci_reports_blood', $report_data, array('id' => $report['id']));
					} else if ($report['estatus'] == 'C' && empty($report['approve_date'])) {
						$report_data = array(
							'approve_date' => $approve_date,
							're_import_ID' => $import_id,
						);
						$wpdb->update('ci_reports_blood', $report_data, array('id' => $report['id']));
					}

					if ($approved) {
						trpdd_log_user(-1, 'approve_rs', $batch['assen'], $batch['mid'], 'blood', '', '', $batch['assen']);
					}

					if ($report['id'] > 0) {
						$list_names = array();
						foreach ($data->drugs as $t) {
							$name = (string)$t->name;
							$abrev = (string)$t->abrev;
							$value = (string)$t->value;
							$comments = (string)$t->comments;
							$instrument = (string) $t->instrument;
							$pos_neg = (string) $t->{'pos-neg'};

							if (empty($abrev)) {
								$abrev = $name;
							}

							$testdb = $wpdb->get_row("select te_name, te_abrev 
													from ci_test_panels 
													where te_type='blood'
													and te_in_req = 1
													and (te_name='{$name}' or te_abrev='{$abrev}')");

							if (!empty($wpdb->last_error)) {
								$return['errors'][] = $wpdb->last_error;
							}

							if (!$testdb) {
								$return['notfound'][] = $name;
							}

							$abrev = $testdb->te_abrev;
							$name = $testdb->te_name;

							$sql_exist = "select id from ci_results_blood 
											where report_id = {$report['id']} 
											and drug='{$name}' ";

							$rs_id = $wpdb->get_var($sql_exist);

							if (!$rs_id && !empty($abrev)) {
								$sql_exist = "select id from ci_results_blood 
											where report_id = {$report['id']} 
											and drug='{$abrev}' ";

								$rs_id = $wpdb->get_var($sql_exist);
							}

							$list_names[] = $name;

							if ($rs_id > 0) {
								$where = array(
									'id' => $rs_id,
								);
								$updatearr = array(
									'drug' => $name,
									'conc' => $value,
									'pos-neg' => $pos_neg,
								);
								if (!empty($instrument)) {
									$updatearr['instrument'] = $instrument;
								}
								$wpdb->update('ci_results_blood', $updatearr, $where);
							} else {
								$wpdb->insert('ci_results_blood', array(
									'report_id' => $report['id'],
									'drug' => $name,
									'conc' => $value,
									'pos-neg' => $pos_neg,
									'comments' => $comments,
									'instrument' => $instrument,
								));
							}
						}


						//$return['tests'] = $list_names;
					}


					if (isset($data->result_pdfs)) {

						$pdf_files = array();
						foreach ((array)$data->result_pdfs as $pdf_url) {
							$pdf_url = (string)$pdf_url;
							$pdf_content = file_get_contents($pdf_url);
							if (strlen($pdf_content) > 200) {
								$pdf_name = basename($pdf_url);
								$pdf_path = tr_get_result_upload_dir('path') . '/' . $pdf_name;
								file_put_contents($pdf_path, $pdf_content);
								$pdf_files[] = $pdf_name;
							}
						}

						if (count($pdf_files) > 0) {
							$oldfiles = !empty($batch['result_pdf']) ? explode(',', $batch['result_pdf']) : array();
							$pdf_files = array_merge($oldfiles, $pdf_files);
							$new_values = array();
							foreach ($pdf_files as $k => $f) {
								if (!empty($f) && !in_array(strtolower($f), $new_values)) {
									$new_values[] = $f;
								}
							}
							$update_batch['result_pdf'] = implode(',', $new_values);
						}
					}

					$return['ID'] = $report['id'];


					if (!empty($update_batch)) {
						$wpdb->update('ci_batches_blood', $update_batch, array('assen' => $assen));
						//$return['updated_bid']= $batch_id;
					} else {
						//$return['batch_id']= $batch_id;
					}

					//auto fax
					if ($keyobj['wsdl_fax_result'] && !$report['faxed']) {
						$fax_number = $wpdb->get_var("select `cli_om-fax` 
										from ci_clinic 
										where cID = {$batch['cli_ID']}
										and cli_faxres = '1' ");

						//$return['faxnumber'] = $fax_number;


						$fax_number = trim($fax_number);
						if (!empty($fax_number)) {
							$type_result = 'rs_efax' . $btype;

							$added_fax = $wpdb->get_var("select id 
										from ci_box_upload 
                                        where mid = {$batch['mid']} 
                                        and type='{$type_result}' 
                                        ");

							if (!$added_fax) {

								list($path, $file) = trpdd_get_path_result_pdf($report, $btype);

								$path_db = $path . '/' . $file;

								$wpdb->insert(
									'ci_box_upload',
									array(
										'path'       => $path_db,
										'type'       => $type_result,
										'status'     => '5',
										'mid'        => $batch['mid'],
										'account_id' => $batch['cli_ID'],
										'etag'       => $fax_number,
										'lab_ID'     => $batch['lab_id'],
										'batch_id'   => $batch['batch_id'],
										'btype'      => $btype,
										'uptime'      => time(),
										'sender'     => $batch['requesting_practice'],
										'created'    => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
										'created_by' => '-1'
									)
								);

								$next_cron = wp_next_scheduled('ci_check_upload_apis');
								if ($next_cron < time() || $next_cron > time() + 300) {
									wp_schedule_single_event(time() + 10, 'ci_check_upload_apis', array(1, 'single', $btype));
								}
							}
						}
					}
				} else {
					$return['status'] = 'NOT_EXISTS';
					$return['msg'] = 'The Order ' . $assen . ' not exists!';
				}
			}
		}



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('put_result_blood', $key_id, $assen, $return['status'], $xml, $xmlout);

		return $xmlout;
	}

	/**
	 * put PCR Results 
	 * xml : [demoxml url="/wsdl?demo=result_pcr.xml"]
	 *
	 * @param string $xml
	 * @param string $key
	 * @return string Response string XML
	 */
	public function put_result_pcr($xml, $key)
	{
		global $wpdb;

		$return = array(
			'status' => 'OK',
			'msg' => ''
		);
		$btype = 'pcr';
		$assen = '';

		//check key
		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong Key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'put_result_pcr', 'pcr'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$data = false;
			try {
				$xml = trim($xml);
				$data = simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}

			$assen 		= (string) $data->assen;
			$instrument = (string) $data->instrument;
			$$notes_report 	= (string) $data->notes_report;
			$batch_id 	= (string) $data->batch_id;
			$estatus 	= isset($data->estatus) ? (string) $data->estatus : '';
			$approve_date = isset($data->approve_date) ? (string) $data->approve_date : '';
			$final_results = isset($data->final) ? (string) $data->final : '';
			$created 	= isset($data->created) ? (string) $data->created : date_i18n('Y-m-d H:i:s');
			$created_date = isset($data->created_date) ? (string) $data->created_date : date_i18n('Y-m-d');

			if (empty($approve_date)) {
				$approve_date = date_i18n('Y-m-d H:i:s');
			}
			//check report
			if (empty($assen)) {
				$return['status'] = 'EMPTY_BARCODE';
				$return['msg'] = 'empty barcode';
			} else {
				$report = $wpdb->get_row("select * from ci_reports_pcr where patient_id = '{$assen}'", ARRAY_A);
				$batch = $wpdb->get_row("select * from ci_batches_pcr where assen = '{$assen}'", ARRAY_A);

				if ($batch) {
					$approved = false;
					$update_batch = array();
					if (!empty($batch_id) && empty($batch['batch_id'])) {
						$update_batch['batch_id'] = $batch_id;
					}
					$import_id = isset($data->id) ? (int)$data->id : time();

					if (!$report['id']) {

						$report_data = array(
							'patient_id' => $assen,
							'created' => $created,
							'created_date' => $created_date,
							'upload_files' => 'api',
							'instrument' => $instrument,
							're_import_ID' => $import_id,
						);

						if (isset($data->has_uti)) {
							$report_data['has_uti'] = (int) $data->has_uti;
						}

						if (!empty($final_results)) {
							$report_data['final_results'] = $final_results;
						}

						if ($estatus == 'C') {
							$report_data['estatus'] = 'C';
							$report_data['approve_date'] = $approve_date;
							$approved = true;
						}
						$wpdb->insert('ci_reports_pcr', $report_data);

						$report['id'] = $wpdb->insert_id;
					} else if ($report['estatus'] !== 'C' && $estatus === 'C') {
						$approved = true;
						$report_data = array(
							'estatus' => 'C',
							'approve_date' => $approve_date,
							'has_pos' => null,
							're_import_ID' => $import_id,
						);
						if (isset($data->has_uti)) {
							$report_data['has_uti'] = (int) $data->has_uti;
						}

						$wpdb->update('ci_reports_pcr', $report_data, array('id' => $report['id']));
					} else if ($report['estatus'] == 'C' && empty($report['approve_date'])) {
						$report_data = array(
							'approve_date' => $approve_date,
							'instrument' => $instrument,
							'has_pos' => null,
							're_import_ID' => $import_id,
						);
						if (isset($data->has_uti)) {
							$report_data['has_uti'] = (int) $data->has_uti;
						}
						$wpdb->update('ci_reports_pcr', $report_data, array('id' => $report['id']));
					} else {
						$report_data = array(
							'instrument' => $instrument,
							'has_pos' => null,
							're_import_ID' => $import_id,
						);
						if (isset($data->has_uti)) {
							$report_data['has_uti'] = (int) $data->has_uti;
						}
						$wpdb->update('ci_reports_pcr', $report_data, array('id' => $report['id']));
					}


					if ($approved) {
						trpdd_log_user(-1, 'approve_rs', $batch['assen'], $batch['mid'], 'pcr', '', '', $batch['assen']);
					}

					if ($report['id'] > 0) {

						if ($batch['ref_lab_ID'] > 0) {
							$wpdb->update('ci_reports_pcr', array('norerun_role' => 1), array('id' => $report['id']));
						}


						$list_names = array();
						$groupids = array();
						foreach (explode(',', $batch['panel_tests_requested']) as $trq) {
							if (stripos($trq, 'GROUP_') !== false) {
								$groupids[] = str_ireplace('GROUP_', '', $trq);
							}
						}
						foreach ($data->drugs as $t) {
							$name 		= (string)$t->name;
							$abrev 		= (string)$t->abrev;
							$value 		= (string)$t->value;
							$comments 	= (string)$t->comments;
							$instrument = (string) $t->instrument;
							$group 		= (string) $t->group;
							$pos_neg 	= (string) $t->{'pos-neg'};
							$cutoffs 	= isset($t->cutoffs) ? $t->cutoffs : new stdClass();

							if (empty($name)) {
								continue;
							}

							$testdb = null;
							if (!empty($group)) {
								if (!isset($groupids[$group])) {
									$gid = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = '{$group}' and tg_type = 'pcr' ");
									if ($gid > 0) {
										$groupids[$group] = $gid;
									}
								}
							}
							$groupidstr = implode(',', $groupids);
							$return['gids'] = $groupidstr;

							if (!empty($groupidstr)) {
								$testdb = $wpdb->get_row("select te_ID, te_name, te_abrev , te_group_id
									from ci_test_panels te
									JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
									where te_type='pcr'
									and (g.tg_ID in ({$groupidstr}) or g.tg_parent in ({$groupidstr}) )
									and (te_name='{$name}' or te_abrev='{$name}')");

								if (!empty($wpdb->last_error)) {
									$return['sqlerror'][] = $wpdb->last_error;
								}

								if (!$testdb && !empty($abrev)) {
									$testdb = $wpdb->get_row("select te_ID, te_name, te_abrev , te_in_req
														from ci_test_panels te
														JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
														where te_type='pcr'
														and (g.tg_ID in ({$groupidstr}) or g.tg_parent in ({$groupidstr}) )
														and te_abrev='{$abrev}' ");
									if (!empty($wpdb->last_error)) {
										$return['sqlerror'][] = $wpdb->last_error;
									}
									if ($testdb) {
										$name = $abrev;
									}
								}
							}

							if (empty($testdb)) {
								$testdb = $wpdb->get_row("select te_ID, te_name, te_abrev 
								from ci_test_panels 
								where te_type='pcr'
								and (te_name='{$name}' or te_abrev='{$name}')");
								if (!empty($wpdb->last_error)) {
									$return['sqlerror'][] = $wpdb->last_error;
								}

								if (!$testdb && !empty($abrev)) {
									$testdb = $wpdb->get_row("select te_ID, te_name, te_abrev 
														from ci_test_panels 
														where te_type='pcr'
														and te_abrev='{$abrev}' ");
									if (!empty($wpdb->last_error)) {
										$return['sqlerror'][] = $wpdb->last_error;
									}
									if ($testdb) {
										$name = $abrev;
									}
								}
							}

							$abrev = $testdb->te_abrev;



							//check instrument cutoff
							if (!empty($cutoffs) && isset($cutoffs->high) && !empty($instrument)) {

								$cutoffmap = array(
									'high' => 'cu_upper_limit',
									'low' => 'cu_cutoff',
									'medium' => 'cu_medium',
									'critical_high' => 'cu_critical_high',
									'critical_low' => 'cu_critical_low',
									'invalid' => 'cu_invalid',
								);
								$return['need_add_cutoff'][] = (object) array(
									'tid' => $testdb->te_ID,
									'name' => $testdb->te_name,
									'groupid' => $groupidstr,
								);

								$cutoffid = $wpdb->get_var("select cu_te_ID 
								from ci_test_panels_cutoffs where cu_te_ID = {$testdb->te_ID} 
								and cu_ms = '{$instrument}' ");
								if (empty($cutoffid)) {
									$cutoffarr = array(
										'cu_te_ID' => $testdb->te_ID,
										'cu_ms' => $instrument,
										'cu_upper_limit' => (string)$cutoffs->high,
									);
									foreach ($cutoffmap as $ck => $cdbk) {
										if (isset($cutoffs->{$ck})) {
											$vlc = (string) $cutoffs->{$ck};
											if (!empty($vlc)) {
												$cutoffarr[$cdbk] = $vlc;
											}
										}
									}

									$wpdb->insert('ci_test_panels_cutoffs', $cutoffarr);
									if (!empty($wpdb->last_error)) {
										$return['sqlerror'][] = $wpdb->last_error;
									}

									$return['added_cutoff_id'][$testdb->te_ID] = $wpdb->insert_id;
								}
							}

							$sql_exist = "select id from ci_results_pcr 
											where report_id = {$report['id']} 
											and drug='{$name}' ";

							$rs_id = $wpdb->get_var($sql_exist);

							if (!$rs_id && !empty($abrev)) {
								$sql_exist = "select id from ci_results_pcr 
											where report_id = {$report['id']} 
											and drug='{$abrev}' ";

								$rs_id = $wpdb->get_var($sql_exist);
							}

							$list_names[] = $name;

							//$return['test'.$aa++]= array($abrev,$name,$rs_id,$value,$pos_neg);

							if ($rs_id > 0) {
								$where = array(
									'id' => $rs_id,
								);
								$updatearr = array(
									'drug' => $name,
									'conc' => $value,
									'pos-neg' => $pos_neg,
								);
								if (!empty($instrument)) {
									$updatearr['instrument'] = $instrument;
								}
								$wpdb->update('ci_results_pcr', $updatearr, $where);
							} else {
								$wpdb->insert('ci_results_pcr', array(
									'report_id' => $report['id'],
									'drug' => $name,
									'conc' => $value,
									'pos-neg' => $pos_neg,
									'comments' => $comments,
									'instrument' => $instrument,
								));
							}
						}

						if (count($list_names) > 0) {
							$list_names_str = implode("','", $list_names);
							$wpdb->query("delete from ci_results_pcr where report_id = {$report['id']}
										and drug not in ('{$list_names_str}') ");
						}
					}

					$return['ID'] = $report['id'];

					if (isset($data->result_pdfs)) {
						$pdf_files = array();
						foreach ((array)$data->result_pdfs as $pdf_url) {
							$pdf_url = (string)$pdf_url;
							$pdf_content = file_get_contents($pdf_url);
							if (strlen($pdf_content) > 200) {
								$pdf_name = basename($pdf_url);
								$pdf_path = tr_get_result_upload_dir('path') . '/' . $pdf_name;
								file_put_contents($pdf_path, $pdf_content);
								$pdf_files[] = $pdf_name;
							}
						}

						if (count($pdf_files) > 0) {
							$oldfiles = !empty($batch['result_pdf']) ? explode(',', $batch['result_pdf']) : array();
							$pdf_files = array_merge($oldfiles, $pdf_files);
							$new_values = array();
							foreach ($pdf_files as $k => $f) {
								if (!empty($f) && !in_array(strtolower($f), $new_values)) {
									$new_values[] = $f;
								}
							}
							$update_batch['result_pdf'] = implode(',', $new_values);
						}
					}


					if (!empty($update_batch)) {
						$wpdb->update('ci_batches_pcr', $update_batch, array('assen' => $assen));
						//$return['updated_bid']= $batch_id;
					} else {
						//$return['batch_id']= $batch_id;
					}

					//auto fax
					if ($keyobj['wsdl_fax_result'] && !$report['faxed']) {
						$fax_number = $wpdb->get_var("select `cli_om-fax` 
										from ci_clinic 
										where cID = {$batch['cli_ID']}
										and cli_faxres = '1' ");

						//$return['faxnumber'] = $fax_number;


						$fax_number = trim($fax_number);
						if (!empty($fax_number)) {
							$type_result = 'rs_efax' . $btype;

							$added_fax = $wpdb->get_var("select id 
										from ci_box_upload 
                                        where mid = {$batch['mid']} 
                                        and type='{$type_result}' 
                                        ");

							if (!$added_fax) {

								list($path, $file) = trpdd_get_path_result_pdf($report, $btype);

								$path_db = $path . '/' . $file;

								$wpdb->insert(
									'ci_box_upload',
									array(
										'path'       => $path_db,
										'type'       => $type_result,
										'status'     => '5',
										'mid'        => $batch['mid'],
										'account_id' => $batch['cli_ID'],
										'etag'       => $fax_number,
										'lab_ID'     => $batch['lab_id'],
										'batch_id'   => $batch['batch_id'],
										'btype'      => $btype,
										'uptime'      => time(),
										'sender'     => $batch['requesting_practice'],
										'created'    => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
										'created_by' => '-1'
									)
								);

								$next_cron = wp_next_scheduled('ci_check_upload_apis');
								if ($next_cron < time() || $next_cron > time() + 300) {
									wp_schedule_single_event(time() + 10, 'ci_check_upload_apis', array(1, 'single', $btype));
								}
							}
						}
					}
				} else {
					$return['status'] = 'NOT_EXISTS';
					$return['msg'] = 'The Order ' . $assen . ' not exists!';
				}
			}
		}



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('put_result_pcr', $key_id, $assen, $return['status'], $xml, $xmlout);

		return $xmlout;
	}



	/**
	 * get Req data by Accession
	 * @param string $key
	 * @param string $accessions split by comma
	 * @return array
	 */

	public function get_req_by_assen($key, $accessions)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
			'msg' => '',
			'count' => 0,
			'reqs' => array()
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'getrba'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else if (!empty($accessions)) {
			$accessions_str = str_replace(array(',', ' '), array("','", ''), $accessions);
			$where          = " ba.assen in ('{$accessions_str}') ";

			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$sql = "
		            SELECT 
	                ba.mid
	                from {$batch_tbl} ba
	                where {$where}  ";
				$mids = $wpdb->get_col($sql);

				foreach ($mids as $mid) {
					$return['reqs'][] = $this->get_req_data($mid, $btype);
				}
			}

			$return['count'] = count($return['reqs']);
		} else {
			$return['status'] = 'NOT_Found';
			$return['msg'] = 'Not found';
		}
		//log
		$this->_log('get_req_by_assen', $key_id, $accessions, $return['status'], $accessions);


		//$xml = @$this->objToXML($return,'xml');
		return $return;
	}

	/**
	 * get Req Tox , page from 1
	 * @param int $page
	 * @param string $key
	 * @return string XML Req
	 */

	public function get_tox($page, $key)
	{
		global $wpdb;
		$post_per_page = 10;
		$page = intval($page);
		if ($page == 0) $page = 1;

		$return = array(
			'status' => 'OK',
			'msg' => ''
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_req'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$limit = ($page - 1) * $post_per_page;
			$limit = "{$limit}, {$post_per_page} ";

			$keyarray = $this->getRowKey($key);

			$where = " ba.batch_status !='archived' 
                        and ba.batch_id > 20160101 ";

			$cli_ids = $this->getClinicByKey($key);
			if (count($cli_ids) > 0 && !in_array(-1, $cli_ids)) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids}) ";
			}

			if (!empty($keyarray['wsdl_start']) && $keyarray['wsdl_start'] != '0000-00-00') {
				$where .= " and ba.doc >= '{$keyarray['wsdl_start']}' ";
			}

			$total = $wpdb->get_var("select count(*) from ci_batches ba where {$where}");
			$total_page = ceil($total / $post_per_page);

			$sql = "select ba.mid,
                            ba.assen,
                            ba.patient_name,
                            ba.panel_tests_requested
            from ci_batches ba
            where {$where} ";
			$sql .= " order by mid asc limit {$limit}";

			$rows = $wpdb->get_results($sql);
			$reqs = array();
			foreach ($rows as $row) {
				$data = new stdClass();
				$data->assen = $row->assen;
				$data->patient_name = $row->patient_name;

				//find urine value

				$chemistry = array();
				$screening = array();
				$vdxs = array();
				$validities = tr_pdd_get_result_validity(0, '', $row->mid);
				foreach ($validities as $arr) {
					if (empty($arr['u_abreviation'])) continue;

					$chemistry[] = $arr['u_abreviation'];
				}

				$scr = tr_pdd_get_result_validity(1, '', $row->mid);
				foreach ($scr as $u_ID => $vl) {
					if (empty($arr['u_abreviation'])) continue;
					$screening[] = $arr['u_abreviation'];
				}

				$vdx_rs = tr_pdd_get_result_validity(2, '', $row->mid);
				foreach ($vdx_rs as $u_ID => $vl) {
					if (empty($arr['u_abreviation'])) continue;
					$vdxs[] = $arr['u_abreviation'];
				}

				$data->chemistry = implode(', ', $chemistry);
				$data->screening = implode(', ', $screening);
				$data->vdx = implode(', ', $vdxs);
				$reqs[] = $data;
			}

			$result = new stdClass();
			$result->req = $reqs;
			$return['total_page'] = $total_page;
			$return['page'] = $page;
			$return['posts_per_page'] = $post_per_page;

			$return['count'] = count($rows);
			$return['reqs'] = count($reqs) > 0 ? $result : '';
			//$return['sql'] = $sql;


			//update status

		}




		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_req', $key_id, $page, $return['status'], $page, $xmlout);

		return $xmlout;
	}


	/**
	 * get results for Tox
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @return string XML results
	 */
	public function get_results($key, $date)
	{
		global $wpdb;

		$today = Tr_Base_Class_V4::date('Y-m-d');

		if (!empty($date)) {
			$today = $date;
		}
		//$today = str_replace('-','',$today);
		if (strpos($today, '-') === false) {
			$today = date('Y-m-d', strtotime($today));
		}

		$return = array(
			'status' => 'OK',
		);


		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_results'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$where = '';
			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);
			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where = " and ba.cli_ID in ({$cli_ids})  ";
			}

			if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}
			//$where.= " and ba.batch_id = '{$today}' ";
			$where .= " and re.approve_date >= '{$today}'";
			$where .= " and re.approve_date <= '{$today} 23:59:59'";


			$sql = "SELECT
                ba.*,
                pa.pa_ssn,
                pa.pa_mrn as patient_mrn,
                doc.doc_npi,
                cp.cus_ID,
                rl.lab_additional as rl_lab_additional,
                re.id,
                re.estatus,
                re.patient_name as re_patient_name,
                re.patient_id,
                re.acquisition_date,
                re.collection_date,
                re.sublab,
                re.sample_type,
                re.validity,
                re.modified,
                re.approve_date,
                re.final_results
                
                FROM ci_reports re
                    JOIN ci_batches ba ON ba.assen = re.patient_id
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                   
                    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    WHERE  re.estatus='C'
                    {$where}
                    Group by ba.mid ";

			//return $sql;
			$reports = $wpdb->get_results($sql, ARRAY_A);

			$results = array();
			$report_ids = array();
			foreach ($reports as $report) {
				list($path, $file) = trpdd_get_path_result_pdf($report);
				$result_dir = WP_CONTENT_DIR . '/results/';
				$path_db = $path . '/' . $file;
				$file_path = $result_dir . $path_db;

				if (!file_exists($file_path) || filemtime($file_path) < $report['modified']) {
					trpdd_export_results_pdf($report['id']);
					// $return['createdfile'] = $report['assen'];
				}

				$data = new stdClass();

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->clinic = $report['requesting_practice'];
				$data->lab = $report['lab'];
				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['patient_mrn'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doc = $report['doc'];
				$data->dob = $report['dob'];

				$data->pdf_base64  = base64_encode(file_get_contents($file_path));


				$results[] = $data;
				$report_ids[] = $report['id'];
			}

			$result = new stdClass();
			$result->result = $results;
			//$return['sql'] = $sql;
			$return['total'] = count($results);
			$return['results'] = $result;
			//$return['demo_result_xml'] = TRCPDD_URL.'results.xml';


			if ($reports === false) {
				$return['status'] = "SQL_ERROR";
				$return['sql'] = $sql;
			}
		}
		//return $return;



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_results', $key_id, $today, $return['status'], $return['total'], $xmlout);

		return $xmlout;
	}

	/**
	 * get PGx results
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @param int $limit (default: 100)
	 * @param int $page (default: 1)
	 * @return string XML results
	 */
	public function get_pgx_results($key, $date = '', $limit = 500, $page = 1)
	{
		return $this->_get_result($key, 'dna', $date, $limit, $page);
	}

	/**
	 * get Blood results
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @param int $limit (default: 100)
	 * @param int $page (default: 1)
	 * @return string XML results
	 */
	public function get_blood_results($key, $date = '', $limit = 500, $page = 1)
	{
		return $this->_get_result($key, 'blood', $date, $limit, $page);
	}

	/**
	 * get Allergy results
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @param int $limit (default: 100)
	 * @param int $page (default: 1)
	 * @return string XML results
	 */
	public function get_allergy_results($key, $date = '', $limit = 500, $page = 1)
	{
		return $this->_get_result($key, 'allergy', $date, $limit, $page);
	}

	/**
	 * get PCR results
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @param int $limit (default: 100)
	 * @param int $page (default: 1)
	 * @return string XML results
	 */
	public function get_pcr_results($key, $date = '', $limit = 500, $page = 1)
	{
		return $this->_get_result($key, 'pcr', $date, $limit, $page);
	}




	/**
	 * get PCR results data
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @param array $args 
	 * @return string XML results
	 */
	public function get_pcr_results_data($key, $date = '', $args = array())
	{
		return $this->_get_result_data($key, 'pcr', $date, $args);
	}


	/**
	 * get combined results 
	 * xml : [demoxml url="/wsdl?demo=get_combined_results.xml"]
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML 
	 * 
	 */
	public function get_combined_results($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		return $this->_get_result_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1, 'req');
	}

	/**
	 * get combined results by accession number
	 * xml: [demoxml url="/wsdl?demo=get_combined_results.xml"]
	 * @param string $key
	 * @param string $accession  (spit by comma. ex: assen1, assen2, assen3 )
	 * @return string XML 
	 * 
	 */
	public function get_combined_result_by_accession($key, $accession)
	{
		return $this->_get_result_combined($key, $accession, 'assen', 0, 0, 'req');
	}


	/**
	 * get results today pdf
	 * @param string $key
	 * @param string $date format YYYY-MM-dd
	 * @return string XML results
	 */
	public function get_results_today($key, $date = '')
	{
		global $wpdb;

		if (empty($date)) {
			$today = Tr_Base_Class_V4::date('Y-m-d');
		} else {
			$today = $date;
		}
		//$today = str_replace('-','',$today);
		if (strpos($today, '-') === false) {
			$today = date('Y-m-d', strtotime($today));
		}

		$return = array(
			'status' => 'OK',
		);


		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_results_today', 'pdf'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$where = '';
			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where = " and ba.cli_ID in ({$cli_ids})  ";
			}


			if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}
			//$where.= " and ba.batch_id = '{$today}' ";
			$where .= " and re.approve_date >= '{$today}'";
			$where .= " and re.approve_date <= '{$today} 23:59:59'";


			$sql = "SELECT
               lab.*,
                ba.*,
                doc.doc_npi,
                cp.cus_ID,
                pa.pa_ssn,
                pa.pa_mrn as patient_mrn,
                rl.lab_additional as rl_lab_additional,
                re.id,
                re.estatus,
                re.patient_name as re_patient_name,
                re.patient_id,
                re.acquisition_date,
                re.collection_date,
                re.sublab,
                re.sample_type,
                re.validity,
                re.modified,
                re.approve_date,
                re.final_results
                
                FROM ci_reports re
                    JOIN ci_batches ba ON ba.assen = re.patient_id
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    WHERE   re.estatus='C'
                    {$where}
                    Group by ba.mid ";

			//return $sql;
			$reports = $wpdb->get_results($sql, ARRAY_A);

			$results = array();
			$report_ids = array();
			foreach ($reports as $report) {
				list($path, $file) = trpdd_get_path_result_pdf($report);
				$result_dir = WP_CONTENT_DIR . '/results/';
				$path_db = $path . '/' . $file;
				$file_path = $result_dir . $path_db;

				if (!file_exists($file_path) || filemtime($file_path) < $report['modified']) {
					trpdd_export_results_pdf($report['id']);
					//$return['createdfile'] = $report['assen'];
				}

				$data = new stdClass();
				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->clinic = $report['requesting_practice'];
				$data->lab = $report['lab'];
				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['patient_mrn'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doc = $report['doc'];
				$data->dob = $report['dob'];

				$data->pdf_base64  = base64_encode(file_get_contents($file_path));


				$results[] = $data;
				$report_ids[] = $report['id'];
			}

			$result = new stdClass();
			$result->result = $results;
			$return['total'] = count($results);
			$return['results'] = $result;
			//$return['demo_result_xml'] = TRCPDD_URL.'results.xml';
		}
		//return $return;



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_results_today', $key_id, $today, $return['status'], $return['total'], $xmlout);

		return $xmlout;
	}

	/**
	 * get Tox results Data
	 * @param string $key
	 * @param int $limit (max: 100)
	 * @param string $datetime ( Datetime From. default null, format: Y-m-d H:i:s)
	 * @param string $datetime_to ( Datetime To. default null, format: Y-m-d H:i:s)
	 * @param int $page (default: 1)
	 * @return string XML results
	 */
	public function get_results_data($key, $limit = 0, $datetime = '', $datetime_to = '', $page = 1)
	{
		global $wpdb;

		$today = Tr_Base_Class_V4::date('Y-m-d');
		$start_time = microtime(true);
		if ($page == 0) $page = 1;
		if ($limit > 1000 || $limit == 0) $limit = 1000;
		$total_page = 0;
		$total = 0;

		$return = array(
			'status' => 'OK',
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_results_data'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$where = '';
			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);
			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where = " and ba.cli_ID in ({$cli_ids})  ";
			}


			if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}

			if (!empty($datetime)) {
				$datetime = date('Y-m-d H:i:s', strtotime($datetime));
				$today = $datetime;
				$where .= " and re.approve_date >= '{$datetime}'";

				if (!empty($datetime_to)) {
					if (strpos($datetime_to, ':') === false) {
						$datetime_to .= ' 23:59:59';
					}
					$datetime_to = date('Y-m-d H:i:s', strtotime($datetime_to));
					$today .= ' to ' . $datetime_to;
					$where .= " and re.approve_date <= '{$datetime_to}' ";
				}
			} else {
				$where .= " and date_format(re.approve_date,'%Y-%m-%d') = '{$today}' ";
			}

			$total = $wpdb->get_var("select count(ba.mid) 
                    FROM ci_reports re
                    JOIN ci_batches ba ON ba.assen = re.patient_id
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    WHERE re.estatus='C'
                    {$where}");

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;


			$sql = "SELECT
               lab.lab_dba,
               lab.lab_dba_init,
               lab.lab_logo,
               lab.lab_address,
               lab.lab_city,
               lab.lab_state,
               lab.lab_zip,
               lab.lab_phone,
               lab.lab_fax,
               lab.lab_clia,
               lab.lab_npi,
               lab.lab_results_footer,
               lab.lab_additional,
               rl.lab_additional as rl_lab_additional,
                ba.assen as accession_number,
                ba.batch_id,
                
                pa.pa_patient_id as patient_id,
                ba.patient_name,
                ba.dob as patient_dob,
                pa.pa_ssn as patient_ssn,
                pa.pa_mrn as patient_mrn,
                
                ba.requesting_physician	as doctor,
                doc.doc_npi as doctor_npi,
                ba.requesting_practice as clinic,
                ba.type_of_specimen as sample_type,
                ba.doc as collection_date,
                ba.current_medication as medications,
                ba.panel_tests_requested,
                re.id,
                re.final_results,
                re.approve_date,
                ba.mid


                FROM ci_reports re
                    JOIN ci_batches ba ON ba.assen = re.patient_id
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    
                    WHERE re.estatus='C'
                    {$where}
                    Group by ba.mid 
                    order by ba.mid asc ";


			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}

			//return $sql;
			$reports = $wpdb->get_results($sql, ARRAY_A);

			//$return['query_sql'] = $sql;

			$results = array();

			$upload_dir = tr_wp_upload_dir();
			$logo_dir = $upload_dir['basedir'] . '/logos/';
			$logo_url = $upload_dir['baseurl'] . '/logos/';
			$drug_pairs = tr_pdd_get_drug_pairs();

			foreach ($reports as $report) {

				$report['result_status'] = tr_pdd_get_result_status($report['final_results']);
				unset($report['final_results']);

				$report['lab_logo'] = (file_exists($logo_dir . $report['lab_logo'])) ? $logo_url . $report['lab_logo'] : '';
				$report['matrix'] = (stripos($report['sample_type'], "oral") !== false) ? "oral" : strtolower($report['sample_type']);

				//get validity tests



				$validity_tests = array();
				$validities = tr_pdd_get_result_validity(0, '', $report['mid']);
				foreach ($validities as $arr) {
					if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
					} else {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
					}
					$validity_test = array(
						'test' => $arr['u_name'],
						'value' => $arr['conc'],
						'outcome' => $arr['outcome'],
						'ref_range' => $arr['u_range'],
						'comments' => $arr['comments']
					);
					$validity_tests[] = (object)$validity_test;
				}

				//VDX
				$dvx = tr_pdd_get_screening_vdx($report['mid'], $report['batch_created'], $report['batch_id']);
				if ($dvx['checked'] && $dvx['resulted']) {
					$validity_test = array(
						'test' => 'VDX',
						'value' => '',
						'outcome' => $dvx['outcome'],
						'comments' => $dvx['comments']
					);
					$validity_tests[] = (object)$validity_test;
				}

				$vt = new stdClass();
				$vt->validity_test = $validity_tests;
				$report['validity_tests'] = $vt;
				//end validity tests

				//screening
				$screenings = array();
				$validities = tr_pdd_get_result_validity(1, '', $report['mid']);
				foreach ($validities as $arr) {
					$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

					if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
					} else {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
					}

					$scre = array(
						'test' => $arr['u_name'],
						//'abreviation' => $arr['u_abreviation'],
						'value' => $arr['conc'],
						'outcome' => $arr['outcome'],
						'ref_range' => $arr['u_range'],
						'comments' => $arr['comments']
					);
					$screenings[] = (object)$scre;
				}

				$vt = new stdClass();
				$vt->screening = $screenings;
				$report['screenings'] = $vt;


				//begin tests

				$methamphetamine = false; // $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $report['id'] . "' ", ARRAY_A);
				$has_Methamphetamine = $has_Methamphetamine_dl = stripos($report['panel_tests_requested'], 'Methamphetamine d/l Isomers') !== false;

				$drug_groups = $this->get_group_by_matrix($report['matrix']);
				$ci_cutoffs = $this->get_cutoff($report['matrix']);
				$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
				$medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);

				$all_tests = array();
				$ethanol = false;

				$all_results_q = $wpdb->get_results("SELECT * FROM ci_results WHERE report_id='" . $report['id'] . "' ", ARRAY_A);

				foreach ($all_results_q as $arr) {

					$arr['drug'] = strtoupper($arr['drug']);

					$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

					if ($arr['drug'] == 'METHAMPHETAMINE') {
						if ($arr['pos-neg'] == 'NEG') {
							$has_Methamphetamine = false;
						} else {
							$has_Methamphetamine = true;
						}
					}
					if (stripos($arr['drug'], 'Ethanol*') !== false) {
						$ethanol = $arr;
						continue;
					}
					if ($arr['exclude']) {
						continue;
					}
					$group = (isset($drug_groups[$arr['drug']])) ? $drug_groups[$arr['drug']] : 'other';
					$test = array(
						'drug' => $arr['drug'],
						'conc' => $arr['conc'],
						'pos-neg' => $arr['pos-neg'],
						'cutoff' => $arr['cutoff'],
						'detection' => $arr['detection'],
						'comments' => $arr['comments'],
						'group' => $group
					);
					$all_tests[] = (object)$test;
				}

				//ethanol

				if ($ethanol && $report['matrix'] != 'oral') {
					if (empty($ethanol['cutoff'])) {
						$ethanol['cutoff'] = '.010';
					}
					if (empty($ethanol['detection'])) {
						$ethanol['detection'] = '24 hours';
					}
					if (empty($ethanol['conc']) || $ethanol['conc'] == '<') {
						$ethanol['conc'] = '<' . $ethanol['cutoff'];
					}

					$ethanol = array(
						'drug' => $ethanol['drug'],
						'conc' => $ethanol['conc'],
						'pos-neg' => $ethanol['pos-neg'],
						'cutoff' => $ethanol['cutoff'],
						'detection' => $ethanol['detection'],
						'comments' => $ethanol['comments'],
						'group' => 'Alcohol'
					);
					$all_tests[] = (object)$ethanol;
				}

				//end ethanol

				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$report['tests'] = $ctests;
				//end tests



				//methamphetamine

				if (!$has_Methamphetamine_dl && empty($methamphetamine['d']) && empty($methamphetamine['l'])) {
					$has_Methamphetamine = false;
				}
				if ($has_Methamphetamine  && $report['matrix'] != 'oral') {
					$metha = $methamphetamine;
					$metha = array(
						'd_isomer' => $metha['d'],
						'l_isomer' => $metha['l'],
						'comments' => $metha['comments'],
					);
					$metha = (object)$metha;
					$report['methamphetamine'] = $metha;
				}

				//end methamphetamine

				//footer
				/*
				if(!empty($report['rl_lab_additional']))
				{
					$footer_text = $report['rl_lab_additional'];
				}
				else if(!empty($report['lab_additional'])){
					$footer_text = $report['lab_additional'];
					$footer_text = str_replace(array('[lab]','{original_footer}'),array($report['lab_dba_init'],$report['lab_results_footer']),$footer_text);

				}
				if(empty($footer_text)) $footer_text = $report['lab_results_footer'];
				$report['footer'] = strip_tags(stripslashes($footer_text));
				*/


				unset($report['id']);
				unset($report['mid']);
				unset($report['lab_results_footer']);
				unset($report['lab_additional']);
				unset($report['rl_lab_additional']);

				$report = (object) $report;
				$results[] = $report;
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;
			$return['total'] = $total;
			$return['rows_per_page'] = $limit;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count_results'] = $count_results;
			$return['query_datetime'] = $today;
			$return['query_time'] = microtime(true) - $start_time;
			$return['results'] = $result;
		}

		//log
		$log_xml = '<datetime>' . $datetime . '</datetime><datetime_to>' . $datetime_to . '</datetime_to><limit>' . $limit . '</limit><page>' . $page . '</page>';
		$log_xml .= "\n\nTotal:" . $total;
		$log_xml .= "\n rows_per_page:" . $limit;
		$log_xml .= "\n total_page:" . $total_page;
		$log_xml .= "\n paged:" . $page;
		$log_xml .= "\n count_results:" . $count_results;
		$log_xml .= "\n query_datetime:" . $today;



		$xmlout = @$this->objToXML($return, 'xml');

		$this->_log('get_results_data', $key_id, $today, $return['status'], $log_xml, $xmlout);
		return $xmlout;
	}

	/**
	 * get Tox Results data by assen
	 * @param string $key
	 * @param string $assen (spit by comma. ex: assen1, assen2, assen3 )
	 * @return string XML results
	 */
	public function get_results_data_by_assen($key, $assen)
	{
		global $wpdb;

		$start_time = microtime(true);

		$return = array(
			'status' => 'OK',
		);
		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		$log_xml = '<assen>' . $assen . '</assen>';

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_results_data_by_assen', 'pcr', 'blood', 'req', 'tox'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$arr = explode(',', $assen);
			$assen_query = array();
			foreach ($arr as $a) {
				$a = trim($a);
				$assen_query[] = "'{$a}'";
			}
			$assen_query = implode(',', $assen_query);

			$btypes = tr_pdd_get_batch_types();
			if ($keyobj['wsdl_type'] == 'pcr') {
				$btypes = array('pcr');
			}

			$results    = array();
			$drug_pairs = array();

			if (in_array('tox', $btypes)) {
				$drug_pairs = tr_pdd_get_drug_pairs();
			}

			$assen_found = array();


			$all_tests = array();
			$all_groups = array();
			$ts = $wpdb->get_results("select te_ID, 
                            te_name, 
                            te_label_barcode, 
                            te_group_id 
                            from ci_test_panels");
			foreach ($ts as $t) {
				$all_tests[$t->te_ID] = $t;
			}
			$ts = $wpdb->get_results("select tg_ID, 
                                tg_name, 
                                tg_label_name,
                                tg_notestcheckbox 
                                from ci_test_groups");
			foreach ($ts as $t) {
				$all_groups[$t->tg_ID] = $t;
			}

			foreach ($btypes as $btype) {
				$batch_tbl  = tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);
				$result_tbl = tr_pdd_get_table_result_by_btype($btype);

				$sql = "SELECT
		               lab.lab_dba,
		               lab.lab_dba_init,
		               lab.lab_logo,
		               lab.lab_address,
		               lab.lab_city,
		               lab.lab_state,
		               lab.lab_zip,
		               lab.lab_phone,
		               lab.lab_fax,
		               lab.lab_clia,
		               lab.lab_npi,
		               lab.lab_results_footer,
		               lab.lab_additional,
		               rl.lab_additional as rl_lab_additional,
		                ba.assen as accession_number,
		                ba.batch_id,
		                
		                pa.pa_patient_id as patient_id,
		                ba.patient_name,
		                ba.dob as patient_dob,
		                pa.pa_ssn as patient_ssn,
		                pa.pa_mrn as patient_mrn,
		                
		                ba.requesting_physician	as doctor,
		                doc.doc_npi as doctor_npi,
		                ba.requesting_practice as clinic,
		                ba.type_of_specimen as sample_type,
		                ba.doc as collection_date,
		                ba.current_medication as medications,
		                ba.panel_tests_requested,
		                re.id,
		                re.final_results,
		                re.approve_date,
		                ba.mid
		
		
		                FROM {$report_tbl} re
		                    JOIN {$batch_tbl} ba ON ba.assen = re.patient_id
		                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
		                    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
		                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
		                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
		                    WHERE re.estatus='C'
		                    and ba.assen in ( {$assen_query} ) 
		                    Group by ba.mid 
		                    order by ba.mid asc ";

				$reports = $wpdb->get_results($sql, ARRAY_A);

				foreach ($reports as $report) {
					$report['result_status'] = tr_pdd_get_result_status($report['final_results']);
					unset($report['final_results']);

					$assen_found[] = $report['accession_number'];

					$report['matrix'] = (stripos($report['sample_type'], "oral") !== false) ? "oral" : strtolower($report['sample_type']);

					$all_result_tests = array();
					//get validity tests
					if ($btype == 'tox') {
						$validity_tests = array();
						$validities = tr_pdd_get_result_validity(0, '', $report['mid']);
						foreach ($validities as $arr) {
							if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
								$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
							} else {
								$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
							}
							$validity_test = array(
								'test' => $arr['u_name'],
								'value' => $arr['conc'],
								'outcome' => $arr['outcome'],
								'ref_range' => $arr['u_range'],
								'comments' => $arr['comments']
							);
							$validity_tests[] = (object)$validity_test;
						}
						//VDX
						$dvx = tr_pdd_get_screening_vdx($report['mid'], $report['batch_created'], $report['batch_id']);
						if ($dvx['checked'] && $dvx['resulted']) {
							$validity_test = array(
								'test' => 'VDX',
								'value' => '',
								'outcome' => $dvx['outcome'],
								'comments' => $dvx['comments']
							);
							$validity_tests[] = (object)$validity_test;
						}

						$vt = new stdClass();
						$vt->validity_test = $validity_tests;
						$report['validity_tests'] = $vt;
						//end validity tests

						$screenings = array();
						$validities = tr_pdd_get_result_validity(1, '', $report['mid']);
						foreach ($validities as $arr) {

							$validity_test = array(
								'test' => $arr['u_name'],
								//'abreviation' => $arr['u_abreviation'],
								'value' => $arr['conc'],
								'outcome' => $arr['outcome'],
								'ref_range' => $arr['u_range'],
								'comments' => $arr['comments']
							);
							$screenings[] = (object)$validity_test;
						}
						$vt = new stdClass();
						$vt->screening = $screenings;
						$report['screenings'] = $vt;



						//begin tests

						$methamphetamine    = false; // $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $report['id'] . "' ", ARRAY_A);
						$has_Methamphetamine = $has_Methamphetamine_dl = stripos($report['panel_tests_requested'], 'Methamphetamine d/l Isomers') !== false;

						$drug_groups    = $this->get_group_by_matrix($report['matrix']);
						$ci_cutoffs     = $this->get_cutoff($report['matrix']);
						$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
						$medications    = tr_pdd_parse_medications($report['medications'], $drug_pairs);


						$ethanol        = false;

						$all_results_q = $wpdb->get_results("SELECT * FROM ci_results WHERE report_id='" . $report['id'] . "' ", ARRAY_A);

						foreach ($all_results_q as $arr) {

							$arr['drug'] = strtoupper($arr['drug']);

							$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

							if ($arr['drug'] == 'METHAMPHETAMINE') {
								if ($arr['pos-neg'] == 'NEG') {
									$has_Methamphetamine = false;
								} else {
									$has_Methamphetamine = true;
								}
							}
							if (stripos($arr['drug'], 'Ethanol*') !== false) {
								$ethanol = $arr;
								continue;
							}
							if ($arr['exclude']) {
								continue;
							}
							$group = (isset($drug_groups[$arr['drug']])) ? $drug_groups[$arr['drug']] : 'other';
							$test = array(
								'drug' => $arr['drug'],
								'conc' => $arr['conc'],
								'pos-neg' => $arr['pos-neg'],
								'cutoff' => $arr['cutoff'],
								'detection' => $arr['detection'],
								'comments' => $arr['comments'],
								'group' => $group
							);
							$all_result_tests[] = (object)$test;
						}

						//ethanol

						if ($ethanol && $report['matrix'] != 'oral') {
							if (empty($ethanol['cutoff'])) {
								$ethanol['cutoff'] = '.010';
							}
							if (empty($ethanol['detection'])) {
								$ethanol['detection'] = '24 hours';
							}
							if (empty($ethanol['conc']) || $ethanol['conc'] == '<') {
								$ethanol['conc'] = '<' . $ethanol['cutoff'];
							}

							$ethanol = array(
								'drug' => $ethanol['drug'],
								'conc' => $ethanol['conc'],
								'pos-neg' => $ethanol['pos-neg'],
								'cutoff' => $ethanol['cutoff'],
								'detection' => $ethanol['detection'],
								'comments' => $ethanol['comments'],
								'group' => 'Alcohol'
							);
							$all_result_tests[] = (object)$ethanol;
						}

						//end ethanol
						//methamphetamine

						if (!$has_Methamphetamine_dl && empty($methamphetamine['d']) && empty($methamphetamine['l'])) {
							$has_Methamphetamine = false;
						}
						if ($has_Methamphetamine  && $report['matrix'] != 'oral') {
							$metha = $methamphetamine;
							$metha = array(
								'd_isomer' => $metha['d'],
								'l_isomer' => $metha['l'],
								'comments' => $metha['comments'],
							);
							$metha = (object)$metha;
							$report['methamphetamine'] = $metha;
						}
					} else {

						$panel_tests_requested_str = array();
						$in_panel_tests_requested = array();
						$group_ids              = array();
						foreach (explode(',', $report['panel_tests_requested']) as $t) {
							if (stripos($t, 'group') === false) {
								if ($t > 0) {
									$in_panel_tests_requested[] = $t;
									if (isset($all_tests[$t])) {
										$panel_tests_requested_str[] = $all_tests[$t]->te_name;
									}
								}
							} else {
								$group_id = str_ireplace('group_', '', $t);
								$group_ids[] = $group_id;
								if (isset($all_groups[$group_id])) {
									$panel_tests_requested_str[] = $all_groups[$group_id]->tg_name;
								}
							}
						}

						$report['panel_tests_requested'] = implode(', ', $panel_tests_requested_str);

						$test_ids = implode(',', $in_panel_tests_requested);
						if (empty($test_ids)) {
							$test_ids = '0';
						}

						$group_ids_str = implode(',', $group_ids);
						if (empty($group_ids_str)) {
							$group_ids_str = '0';
						}
						$where_group = '';
						$more_query_test = '';
						if (count($group_ids) > 0) {
							$where_group .= " or ((tg_only_group = 1 or  tg_manual_edit=1) and tg_ID in ({$group_ids_str}) )";
							$more_query_test .= " or (tp.te_nocheckbox=1 and tp.te_group_id in ({$group_ids_str}))";
						}

						$drugkeys = array('te_abrev2', 'te_abrev', 'te_name');
						$all_res = array();
						$date_test = date('Y-m-d', strtotime($report['created']));
						$where_testdate = " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
						$where_testdate .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";


						foreach ($drugkeys as $drugkey) {
							$sql = "SELECT *
						        FROM {$result_tbl} rs 
						        join ci_test_panels tp ON tp.te_type='{$btype}' and  tp.{$drugkey} = rs.drug and rs.drug!=''
						        WHERE report_id='" . $report['id'] . "' 
						        and (tp.te_ID in ({$test_ids}) 
						            or tp.te_group_id in (select tg_ID from ci_test_groups 
						                                    where tg_type='{$btype}' 
						                                    and tg_aw_results = 1 {$where_group})
						             {$more_query_test}
						            )
						        {$where_testdate}
						        ";

							$all_results_q = $wpdb->get_results($sql, ARRAY_A);

							foreach ($all_results_q as $arr) {
								if (isset($all_res[$arr['te_ID']])) {
									continue;
								}
								$found_test_ids[] = $arr['te_ID'];

								$arr['dbdrug'] = $arr['drug'];
								$arr['drug'] = strtoupper($arr['te_name']);
								$all_res[$arr['te_ID']] = $arr;
								if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
									$in_panel_tests_requested[] = $arr['te_ID'];
								}

								if (!in_array($arr['te_group_id'], $group_ids)) {
									$group_ids[] = $arr['te_group_id'];
								}
							}
						}

						$drug_groups = tr_pdd_get_drug_groups($btype, $group_ids, true);

						foreach ($drug_groups as $groupid => $groupdata) {

							$te_IDs = is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

							foreach ($te_IDs as $te_ID) {
								if (!isset($all_res[$te_ID])) {
									continue;
								}
								$arr = $all_res[$te_ID];

								$arr['manual_antibiotic'] = $groupdata['manual_antibiotic'];
								if ($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) {

									$old_posneg = $arr['pos-neg'];
									$arr = trpdd_cal_ncovid19($arr, $report['id'], $report['instrument'], $report['created_date']);
									$arr['showed'] = true;
									if ($arr['pos-neg'] == 'PEN' && !empty($old_posneg)) {
										$arr['pos-neg'] = $old_posneg;
									}
									$test = array(
										'drug' => $arr['drug'],
										'conc' => $arr['conc'],
										'pos-neg' => $arr['pos-neg'],
										'comments' => $arr['comments'],
										'group' => $groupdata['name']
									);
									$all_result_tests[] = (object)$test;

									break;
								} else if (!empty($arr)) {
									if (!empty($arr['te_name'])) {
										$arr['drug'] = $arr['te_name'];
									}
									if ($btype == 'blood') {
										$arr = tr_pdd_parse_blood_test($arr, $report['instrument'], $groupdata['id'], $report);
									} else if ($btype == 'pcr') {
										$arr['type_of_specimen'] = $report['type_of_specimen'];
										$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
									}

									$test = array(
										'drug' => $arr['drug'],
										'conc' => $arr['conc'],
										'pos-neg' => $arr['pos-neg'],
										'comments' => $arr['comments'],
										'group' => $groupdata['name']
									);
									$all_result_tests[] = (object)$test;
								}
							}
						}
					}


					$ctests = new stdClass();
					$ctests->test = $all_result_tests;
					$report['tests'] = $ctests;
					//end tests

					unset($report['id']);
					unset($report['mid']);
					unset($report['lab_results_footer']);
					unset($report['lab_additional']);
					unset($report['rl_lab_additional']);

					$report = (object) $report;
					$results[] = $report;
				}
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;
			$return['count_results'] = $count_results;
			$return['found_assen'] = implode(',', $assen_found);
			$return['query_time'] = microtime(true) - $start_time;
			$return['results'] = $result;

			$log_xml .= "\n\nTotal:" . $count_results;
		}
		//log
		$log_xml .= "\n\nstatus: {$return['status']}";



		$xmlout = @$this->objToXML($return, 'xml');

		$this->_log('get_results_data_by_assen', $key_id, substr($assen, 0, 30), $return['status'], $log_xml, $xmlout);

		return $xmlout;
	}

	/**
	 * put ref results (only use for pdf data)
	 * @param string $xml
	 * @param string $key
	 * @return string status
	 */
	public function put_ref_result($xml, $key)
	{
		global $wpdb;
		$dir = WP_CONTENT_DIR . '/results/ref/';
		if (!file_exists($dir)) {
			wp_mkdir_p($dir);
		}

		$ref_key = 'KqzceZGimwNEM3lJEKGC1YvYAUIdNgCSuSH6iIBgsYkE3z6Phru9UDCFyIhx';
		$return = array(
			'status' => 'OK',
		);
		if ($ref_key != $key) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else {
			try {
				$xml = trim($xml);
				$data = simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}

			$assen = (string) $data->assen;
			$target_id = (int) $data->id;
			$pdf_name = (string) $data->pdf_name;
			$pdf = (string) $data->pdf;
			$pdf = base64_decode($pdf);

			if ($target_id == 0) $target_id = 1;
			//check report
			if (empty($assen)) {
				$return['status'] = 'EMPTY_BARCODE';
				$return['msg'] = 'empty barcode';
			} else {
				$report = $wpdb->get_row("select * from ci_reports where patient_id = '{$assen}'", ARRAY_A);
				$batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}'", ARRAY_A);
				if ($report && $report['report_ref'] == -1) {
					$return['status'] = 'REPORT_EXIST';
					$return['msg'] = 'report exist';
				} else if ($batch) {
					$insert = array(
						'batch_id' => $batch['batch_id'],
						'patient_id' => $assen,
						'info' => $pdf_name,
						'report_ref' => $target_id,
						'lab_id' => $batch['lab_id'],
						'estatus' => 'C'
					);
					if ($report) {
						$wpdb->update('ci_reports', $insert, array('id' => $report['id']));
						$return['msg'] = 'Updated';
					} else {
						$insert['created'] = date_i18n('Y-m-d H:i:s');
						$insert['created_date'] = date_i18n('Y-m-d');
						$wpdb->insert('ci_reports', $insert);
						$report['id'] = $wpdb->insert_id;
						$return['msg'] = 'Inserted';
					}
				}

				if (!empty($pdf)) {
					$result_dir = WP_CONTENT_DIR . '/results/';
					$report = tr_pdd_get_report_by_id($report['id'], 'tox');
					list($path, $file) = trpdd_get_path_result_pdf($report, 'tox');
					if (!file_exists($result_dir . $path)) {
						wp_mkdir_p($result_dir . $path);
					}
					$file_path = $result_dir . $path . '/' . $file;

					$w = file_put_contents($file_path, $pdf);
					$return['file_path'] = $path . '/' . $file;
					$return['write_file'] = $w ? 1 : 0;
				}


				$return['ID'] = $report['id'];
			}
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('put_ref_result', 0, $assen, $return['status'], $return['ID'], $xmlout);

		return $xmlout;
	}

	/**
	 * test key
	 * @param string $key
	 * @return string XML Status
	 */
	public function test_key($key)
	{
		$return = array(
			'status' => 'OK',
		);

		$cli_ids = $this->getClinicByKey($key);

		if (count($cli_ids) == 0) {
			if ($this->checkKey($key)) {
				if (!in_array(-1, $cli_ids)) {
					$return['status'] = 'EMPTY';
					$return['msg'] = 'No Clinic';
				}
			} else {
				$return['status'] = 'WRONG_KEY';
				$return['msg'] = 'Wrong key';
			}
		}
		//$return['ss'] = $cli_ids;
		$xml = @$this->objToXML($return, 'xml');
		return $xml;
	}

	/**
	 * add clinic
	 * @param string $key
	 * @param string $xml
	 * @return string XML Status
	 */
	public function add_clinic($key, $xml)
	{
		global $wpdb;

		$return = array(
			'status' => 'OK',
		);

		//check key
		$keyobj     = $this->getRowKey($key);
		$key_id     = $keyobj['wsdl_ID'];
		$lab_id     = $keyobj['wsdl_lab_ID'];
		$ref_lab_id     = $keyobj['wsdl_ref_lab_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg']    = 'Wrong key';
		} else if (!$keyobj['wsdl_sync_clinic']) {
			$return['status'] = 'DENY';
			$return['msg']    = 'Not allow';
		} else {
			$data = null;
			try {
				$xml = trim($xml);
				//file_put_contents(WP_CONTENT_DIR.'/putreq.xml',$xml);
				$data = simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml  = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg']    = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg']    = 'Wrong XML FORMAT';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg']    = 'Wrong XML FORMAT';
			}
		}

		if ($return['status'] == 'OK') {

			if (isset($data->clinic_detail)) {

				$clinic_cols = $wpdb->get_col("explain ci_clinic");
				$clinic_keys = array(
					'cID',
					'cli_lab-ID',
					'cli_rep-ID',
					'cli_group_ID',
					'cli_marketing_group_ID',
					'cli_distributor_ID',
					'cli_import_ID',
					'cli_ref_lab_ID',
					'office_managers',
					'doctors',
				);
				$clinic_data = array();
				foreach ((array) $data->clinic_detail as $k => $vl) {
					$vl = (string) $vl;
					$vl = trim($vl);
					if (!in_array($k, $clinic_keys) && !empty($vl) && in_array($k, $clinic_cols)) {
						$clinic_data[$k] = $vl;
					} else if ($k == 'ID') {
						$clinic_data['cli_import_id'] = $vl;
					}
				}

				$sql_cli = $wpdb->prepare("select cID from ci_clinic where cli_clinic= %s", $clinic_data['cli_clinic']);
				$cli_ID = $wpdb->get_var($sql_cli);

				$clinic_data['cli_lab-ID'] = $lab_id;

				if ($ref_lab_id > 0) {
					$clinic_data['cli_ref_lab_ID'] = $ref_lab_id;
				}

				$rsdb = false;
				if ($cli_ID > 0) {
					$rsdb = $wpdb->update('ci_clinic', $clinic_data, array('cID' => $cli_ID));
				} else {

					$rsdb = $wpdb->insert('ci_clinic', $clinic_data);
					$cli_ID = $wpdb->insert_id;
				}
			}

			$return['name'] = $clinic_data['cli_clinic'];
			//$return['data'] = json_encode($clinic_data);
			$return['rsdb'] = $rsdb;
			if (!empty($wpdb->last_error)) {
				$return['error'] = $wpdb->last_error;
			}

			$return['ID'] 	= $cli_ID;

			if ($cli_ID > 0 && isset($data->office_managers)) {

				foreach ($data->office_managers as $ma) {
					$ma_name = (string)$ma->name;
					$ma_email = (string)$ma->email;

					if (empty($ma_name) && empty($ma_email)) {
						continue;
					}

					$sql_manager = $wpdb->prepare(
						"select ma_ID from ci_clinic_manager 
										where ma_cli_ID = %s
										and ma_manager = %s
										and ma_email = %s ",
						$cli_ID,
						$ma_name,
						$ma_email
					);
					$exist = $wpdb->get_var($sql_manager);
					if (!$exist) {
						$wpdb->insert('ci_clinic_manager', array(
							'ma_cli_ID' => $cli_ID,
							'ma_manager' => $ma_name,
							'ma_email' => $ma_email,
						));
					}
				}
			}


			//doctors
			if ($cli_ID > 0 && isset($data->doctors)) {
				foreach ($data->doctors as $doc) {
					$doc_data = array();
					$doc_data['doc_last'] = trim((string) $doc->doc_last);
					$doc_data['doc_first'] = trim((string) $doc->doc_first);
					$doc_data['doc_phone'] = trim((string) $doc->doc_phone);
					$doc_data['doc_specialty'] = trim((string) $doc->doc_specialty);
					$doc_data['doc_npi'] = trim((string) $doc->doc_npi);
					$doc_data['doc_email'] = trim((string) $doc->doc_email);

					if (empty($doc_data['doc_last']) && empty($doc_data['doc_first'])) {
						continue;
					}

					$sql = $wpdb->prepare("select doc.* 
                                    from ci_doc doc 
                                    left join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID and cd.cli_ID = '{$cli_ID}'
                                    where doc.doc_first = %s 
                                    and doc.doc_last=%s", $doc_data['doc_first'], $doc_data['doc_last']);

					if (isset($doc_data['doc_npi']) && !empty($doc_data['doc_npi'])) {
						$sql .= " and doc.doc_npi = '{$doc_data['doc_npi']}' ";
					} else {
						$sql .= " and cd.cli_ID = '{$cli_ID}' ";
					}

					$doc_obj = $wpdb->get_row($sql);

					if (!$doc_obj) {
						$doc_data['cli_ID'] = $cli_ID;
						$rs_doc = $wpdb->insert('ci_doc', $doc_data);
						$doc_ID = $wpdb->insert_id;

						if ($doc_ID == 0) {
							$log['doc'] = $wpdb->last_error;
						} else {
							$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_ID, 'cli_ID' => $cli_ID));
						}
					} else {
						$wpdb->update('ci_doc', $doc_data, array('doc_ID' => $doc_obj->doc_ID));
					}
				}
			}

			//collectors
			if ($cli_ID > 0 && isset($data->collectors)) {
				$col_keys_ex = array('col_ID', 'doc_ID', 'lab_ID', 'rep_ID');

				foreach ($data->collectors as $ma) {
					$col_data = array();
					foreach ((array) $ma as $k => $vl) {
						$vl = (string) $vl;
						$vl = trim($vl);
						if (!in_array($k, $col_keys_ex) && !empty($vl)) {
							$col_data[$k] = $vl;
						}
					}
					if (empty($col_data['col_first']) && empty($col_data['col_last'])) {
						continue;
					}

					$sql = $wpdb->prepare("select col.* 
                                    from ci_collector col 
                                    left join ci_clinic_collector cl ON cl.col_ID = co.col_ID
                                    where  col.col_first = %s and col.col_last=%s", $col_data['col_first'], $col_data['col_last']);

					if (isset($col_data['col_email']) && !empty($col_data['col_email'])) {
						$sql .= " and col.col_email = '{$col_data['col_email']}' ";
					} else {
						$sql .= " cl.cli_ID = {$cli_ID}";
					}

					$col_obj = $wpdb->get_row($sql);

					if (!$col_obj) {
						$rs_doc = $wpdb->insert('ci_collector', $col_data);
						$col_ID = $wpdb->insert_id;

						if ($col_ID == 0) {
							$log['col'] = $wpdb->last_error;
						} else {
							$wpdb->insert('ci_clinic_collector', array('col_ID' => $col_ID, 'cli_ID' => $cli_ID));
						}
					} else {
						$wpdb->update('ci_collector', $col_data, array('col_ID' => $col_obj->col_ID));
					}
				}
			}
		}

		$xml = @$this->objToXML($return, 'xml');
		return $xml;
	}

	/**
	 * list clinics
	 * @param string $key
	 * @return string XML Status
	 */
	public function list_clinics($key)
	{
		global $wpdb;

		$return = array(
			'status' => 'OK',
		);

		$cli_ids = $this->getClinicByKey($key);

		if (count($cli_ids) == 0) {
			if ($this->checkKey($key)) {
				if (!in_array(-1, $cli_ids)) {
					$return['status'] = 'EMPTY';
					$return['msg'] = 'No Clinic';
				} else {

					$return['msg'] = 'All Clinic';
				}
			} else {
				$return['status'] = 'WRONG_KEY';
				$return['msg'] = 'Wrong key';
			}
		} else {
			$cli = new stdClass();
			$cli_ids_str = implode(',', $cli_ids);
			$cli->clinic = $wpdb->get_col("select cli_clinic from ci_clinic where cID in ({$cli_ids_str})");
			$return['total'] = count($cli_ids);
			$return['clinics'] = $cli;
		}


		$xml = @$this->objToXML($return, 'xml');
		return $xml;
	}

	/**
	 * get orders
	 * @param string $key
	 * @param string $from_ID
	 * @param string $limit
	 * @return string XML Status
	 */
	public function get_orders($key, $from_ID, $limit = 10)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
		);

		$lab_ID = $this->getLabByKey($key);
		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id || $lab_ID == 0) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_orders'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$customer_table_name =  "wp_wp_eStore_customer_tbl";
			$sql = "select * from {$customer_table_name} where lab_ID = {$lab_ID} and id>{$from_ID} order by id asc limit {$limit} ";
			$orders = $wpdb->get_results($sql);
			$o = new stdClass();
			$o->order = $orders;
			$return['total'] = count($orders);
			$return['orders'] = $o;
			//$return['sql']  = $sql;
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_orders', $key_id, $from_ID, $return['status'], $return, $xmlout);

		return $xmlout;
	}


	function _put_req($xml, $key, $btype = '')
	{

		global $wpdb;

		if (empty($btype)) {
			$btype = 'tox';
		} else if ($btype == 'pgx') {
			$btype = 'dna';
		}

		$return = array(
			'status' => 'OK',
			'msg' => '',
			'ID' => 0,
			'assen' => '',
		);

		if (strlen($xml) < strlen($key)) {
			$tmp_key = $xml;
			$xml = $key;
			$key = $tmp_key;
		}

		//check key
		$lab_id = $this->getLabByKey($key);
		$cli_ids = $this->getClinicByKey($key);



		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];
		$ref_lab_id = $keyobj['wsdl_ref_lab_ID'];

		$all_btypes = tr_pdd_get_batch_types();
		if (!in_array($btype, $all_btypes)) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'put_req', 'put_req_' . $btype, $btype))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {

			$data = null;
			try {
				$xml = trim($xml);
				//file_put_contents(WP_CONTENT_DIR.'/testputreq.xml',$xml);
				$data = @simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = @simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT.';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT..';
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT...';
			}


			if ($return['status'] == 'OK') {
				$return = $this->import_req(
					$data,
					array(
						'btype' 	=> $btype,
						'lab_id' 	=> $lab_id,
						'ref_lab_id' => $ref_lab_id,
						'cli_ids' 	=> $cli_ids,
						'return' 	=> $return,
						'paid' 		=> -57,
						'dont_reflex' => $keyobj['wsdl_dont_reflex'],
						'readonly_screening' => $keyobj['wsdl_dont_send_screening'],
					)
				);
			} else {
				//trpdd_log_form('data-servere',array('xml'=>$xml,'key'=>$key,'return'=>$return,'ip'=>$ip));
			}
		}



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$func = 'put_' . $btype;
		if ($btype == 'req') {
			$func = 'put_requisition';
		}
		$this->_log($func, $key_id > 0 ? $key_id : -1, $return['assen'], $return['status'], $xml, $xmlout);

		return $xmlout;
	}

	function _get_result($key, $btype = 'tox', $today = '', $limit = 500, $page = 1)
	{
		global $wpdb;

		if (empty($btype)) {
			$btype = 'tox';
		} else if ($btype == 'pgx') {
			$btype = 'dna';
		}

		if ($page == 0) $page = 1;
		if ($limit > 500 || $limit == 0) $limit = 500;
		$total_page = 0;
		$total = 0;

		$table = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		if (empty($today)) {
			$today = Tr_Base_Class_V4::date('Y-m-d');
		}
		//$today = str_replace('-','',$today);
		if (strpos($today, '-') === false) {
			$today = date('Y-m-d', strtotime($today));
		}



		$return = array(
			'status' => 'OK',
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (
			!in_array($keyobj['wsdl_type'], array('', 'get_result', 'get_result' . $btype, 'pdf', $btype))
			&& $btype != $keyobj['wsdl_type']
		) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {

			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			$where = " ( (re.id >0 and re.estatus='C') or (ba.result_pdf!='' and ba.result_pdf is not null)) ";


			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}



			//$where =" and ba.batch_id = '{$today}' ";
			$where .= " and re.approve_date >= '{$today}'";
			$where .= " and re.approve_date <= '{$today} 23:59:59'";

			$total = $wpdb->get_var("select count(ba.mid) 
                    FROM {$table} ba
                    LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    WHERE {$where}");

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;

			$sql = "SELECT
               lab.*,
                ba.*,
                doc.doc_npi,
                cp.cus_ID,
                rl.lab_additional as rl_lab_additional,
                re.id,
                re.modified,
                re.approve_date,
                re.final_results
                FROM {$table} ba 
                LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID

                    WHERE {$where}
                    Group by ba.mid 
                    ";
			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}



			$reports = $wpdb->get_results($sql, ARRAY_A);

			//$return['error'] = $wpdb->last_error;

			$results = array();
			$report_ids = array();
			foreach ($reports as $report) {
				$file_path = '';
				$path_db = '';
				if (!empty($report['result_pdf'])) {
					$path_db = 'uploads/' . $report['result_pdf'];
					$file_path = WP_CONTENT_DIR . '/results/uploads/' . $report['result_pdf'];
				} else if ($report['id'] > 0) {
					$report_new = tr_pdd_get_report_by_id($report['id'], $btype);
					list($path, $file) = trpdd_get_path_result_pdf($report_new, $btype);
					$path_db = $path . '/' . $file;
					$file_path = WP_CONTENT_DIR . '/results/' . $path_db;

					if (!file_exists($file_path) || filemtime($file_path) < $report['modified']) {
						trpdd_export_results_pdf($report['id'], '', false, $btype);
					}
				}

				$data = new stdClass();

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->ref_accession = $report['oldreq'];
				$data->clinic = $report['requesting_practice'];
				$data->lab = $report['lab'];
				$data->patient_name = $report['patient_name'];
				if (!in_array($btype, array('req'))) {

					$data->doctor = $report['requesting_physician'];
					$data->doctor_npi = $report['doc_npi'];
				}
				$data->doc = $report['doc'];
				$data->dob = $report['dob'];

				$data->pdf_url  = WP_CONTENT_URL . '/results/' . $path_db;
				$data->pdf_base64  = base64_encode(file_get_contents($file_path));


				$results[] = $data;
				$report_ids[] = $report['mid'];
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;
			$return['total'] = $total;
			$return['rows_per_page'] = $limit;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count_results'] = $count_results;
			$return['query_datetime'] = $today;
			$return['date'] = $today;
			$return['results'] = $result;
			//$return['demo_result_xml'] = TRCPDD_URL.'results.xml';


		}
		//return $return;



		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_' . $btype . '_results', $key_id, $today, $return['status'], $return['total'], $xmlout);

		return $xmlout;
	}

	function _get_result_data($key, $btype = 'tox', $today = '', $args = array())
	{
		global $wpdb;

		if (empty($btype)) {
			$btype = 'tox';
		} else if ($btype == 'pgx') {
			$btype = 'dna';
		}

		$table 		= tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);
		$date_to 	= $today;

		if (empty($today)) {
			$today = date_i18n('Y-m-d');
			$date_to 	= $today;
		} else if (strpos($today, '-') === false) {
			$today = date('Y-m-d', strtotime($today));
			$date_to 	= $today;
		} else if (strpos($today, '|') > 0) {
			list($today, $date_to) = explode('|', $today);
		}

		$return = array(
			'status' => 'OK',
			'total' => 0,
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (
			!in_array($keyobj['wsdl_type'], array('', 'get_result', 'get_result_data' . $btype))
			&& $btype != $keyobj['wsdl_type']
		) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {

			$drug_name = $btype == 'pcr' ? 'pathogen' : 'drug';

			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			$where = " re.id >0 and re.estatus='C' ";

			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}

			$where .= " and re.approve_date >= '{$today}'";
			$where .= " and re.approve_date <= '{$date_to} 23:59:59'";

			$has_flu_rsv = false;
			$only_testsids = array();
			$hub_conditions = array();
			$hub_tests = array();

			if (is_array($args) && isset($args['tests'])) {
				$hub_tests 	= get_option('ci_send_hub_tests', array());
				$where_tests = array();
				$args['tests'] = is_string($args['tests']) ? explode(',', $args['tests']) : $args['tests'];
				foreach ($args['tests'] as $tname) {
					if (stripos($tname, 'group_') !== false) {
						$tname = str_ireplace('group_', '', $tname);
						$groupids = $wpdb->get_col("select tg_ID from ci_test_groups 
						where tg_name = '{$tname}' 
						and tg_type = '{$btype}'
						");

						if (empty($groupids) && $tname == 'Flu-RSV') {
							$groupids = $wpdb->get_col("select tg_ID from ci_test_groups 
							where (((tg_name like '%covid%' or tg_name like '%cov2%' or tg_name like '%flu%') and tg_name like '%rsv%' )
								or tg_name = 'Respiratory Panel(new)' )
							and tg_type = '{$btype}'
							");
							$has_flu_rsv = true;
						}

						$child_groups = array();
						foreach ($groupids as $groupid) {
							$child_groups[] = " FIND_IN_SET('GROUP_{$groupid}',ba.panel_tests_requested) ";
						}

						if (empty($child_groups)) {
							$where_tests[] = ' 1 = 2';
						} else {
							$where_tests[] = '(' . implode(' OR ', $child_groups) . ' ) ';
						}
					} else if (stripos($tname, 'adding_') !== false) {

						$tk 		= str_ireplace('adding_', '', $tname);
						$tarr		= isset($hub_tests[$tk]) ? $hub_tests[$tk] : array();
						if (!isset($tarr['tid'])) {
							$tarr['tid'] = -111;
						} else {
							if (!in_array($tarr['tid'], $only_testsids)) {
								$only_testsids[] = $tarr['tid'];
							}

							$hubkey = $tarr['tid'] . '_' . strtoupper($tarr['state']);

							if (!isset($hub_conditions[$hubkey])) {
								$hub_conditions[$hubkey] = array();
							}
							$hub_conditions[$hubkey][] = $tarr;
						}
						$adding_state = !empty($tarr['state']) ? " and pa.pa_state = '{$tarr['state']}' " : '';
						$where_tests[] = " ( FIND_IN_SET('{$tarr['tid']}',ba.panel_tests_requested) {$adding_state} )";
					} else if (stripos($tname, 'test_') !== false) {
						$testid = str_ireplace('test_', '', $tname);
						$where_tests[] = " FIND_IN_SET('{$testid}',ba.panel_tests_requested) ";
						$only_testsids[] = $testid;
					} else {
						$testid = $wpdb->get_var("select te_ID from ci_test_panels 
						where te_name = '{$tname}' 
						and te_type = '{$btype}'
						");
						$where_tests[] = " FIND_IN_SET('{$testid}',ba.panel_tests_requested) ";
						$only_testsids[] = $testid;
					}
				}
				$where .= " and ( " . implode(' or ', $where_tests) . " ) ";
				$where .= " and ba.batch_status not in ('rejected','cancelled','hold') and ba.batch_status not like 're\_%' ";
			}
			//$return['where'] = $where;


			$sql = "SELECT
	                ba.assen,
	                ba.requesting_practice,
	                ba.lab,
	                ba.patient_name,
	                ba.dob,
	                ba.doc,
	                ba.requesting_physician,
	                ba.spec_received,
	                ba.panel_tests_requested,
	                ba.type_of_specimen,
	                
	                doc.doc_npi,
	                doc.doc_first,
	                doc.doc_last,
	                re.id,
	                re.modified,
	                re.created_date,
	                re.approve_date,
	                re.final_results,
	                re.instrument,

					pa.pa_ID,
					pa.pa_ssn,
					pa.pa_mrn,
					pa.pa_phone,
					pa.pa_gender,
					pa.pa_code,
					pa.pa_address,
					pa.pa_city,
					pa.pa_state,
					pa.pa_zip,
					pa.pa_race,
					pa.pa_ethnicity,
                    
                    cli.cli_street,
                    cli.cli_city,
                    cli.cli_state,
                    cli.cli_zip,
                    cli.`cli_om-phone`,
                    
                    lab.lab_address,
                    lab.lab_city,
                    lab.lab_state,
                    lab.lab_zip,
                    lab.lab_phone,
                    lab.lab_fax,
                    lab.lab_clia,
                    lab.lab_npi
                    
	                FROM {$table} ba 
	                LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    join ci_clinic cli ON cli.cID = ba.cli_ID
                    join ci_labs lab ON lab.lab_ID = ba.lab_ID

                    WHERE {$where}
                    Group by ba.mid 
                    ";



			$reports = $wpdb->get_results($sql, ARRAY_A);

			//$return['sq'] = $sql;
			if (!empty($only_testsids)) {
				$return['only_testsids'] = json_encode($only_testsids);
			}


			$results = array();
			$groups = array();

			$all_drugrs = $wpdb->get_results("select tg_ID, tg_name, tg_parent
				from ci_test_groups 
			 	where tg_type='{$btype}'
				order by tg_parent asc ");

			foreach ($all_drugrs as $r) {
				$groups[$r->tg_ID] = $r->tg_name;
				if ($r->tg_parent > 0 && isset($groups[$r->tg_parent])) {
					$groups[$r->tg_ID] = $groups[$r->tg_parent];
				}
			}

			$alltscodes = $wpdb->get_results("select ts_name, ts_code from ci_test_specimens where ts_type = '{$btype}' and ts_code !='' ");
			$specimen_codes = array();
			foreach ($alltscodes as $r) {
				$r->ts_name = trim($r->ts_name);
				if (!empty($r->ts_code) && !empty($r->ts_name)) {
					$specimen_codes[strtolower($r->ts_name)] = $r->ts_code;
				}
			}

			$covid_tests = array();
			$default_arr = array();
			$pos_neg = array(
				'' => '',
				'NEG' => 'Not Detected',
				'POS' => 'Detected',
				'PVL' => 'Detected-Very Low',
				'POL' => 'Detected-Low',
				'POM' => 'Detected-Medium',
				'POH' => 'Detected-High',
				'PVH' => 'Detected-Very High',
				'PEN' => 'Pending',
				'INC' => 'Inconclusive',
				'PRP' => 'Positive',
				'INV' => 'Invalid',
				'NGE' => 'Negative',
			);
			if ($btype == 'pcr') {
				$covid_testnames = trpdd_get_covid_tests('IC');
				$covid_tests = array(
					$covid_testnames[0] => array(),
					$covid_testnames[1] => array(),
				);
				$default_arr = $wpdb->get_row(
					"select te.* 
					 	from ci_test_panels te
					 	join ci_test_groups tg ON tg.tg_ID = te.te_group_id 
			            where tg.tg_type = 'pcr' and tg.tg_is_covid = 1 
			            and te_type='pcr' ",
					ARRAY_A
				);

				$default_arr['drug'] = $default_arr['te_name'];
			}
			foreach ($reports as $report) {
				$data = new stdClass();

				$sncodes = array();
				foreach (explode(',', $report['type_of_specimen']) as $spt) {
					$spt = strtolower(trim($spt));
					$sncodes[] = isset($specimen_codes[$spt]) ? $specimen_codes[$spt] : '';
				}

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen 		= $report['assen'];
				$data->type_of_specimen = $report['type_of_specimen'];
				$data->snomed_codes = implode(',', $sncodes);
				$data->clinic 		= $report['requesting_practice'];
				$data->clinic_name = $report['requesting_practice'];
				$data->clinic_street = $report['cli_street'];
				$data->clinic_city 	= $report['cli_city'];
				$data->clinic_state = $report['cli_state'];
				$data->clinic_zip 	= $report['cli_zip'];
				$data->clinic_phone = $report['cli_om-phone'];

				$data->lab 			= $report['lab'];
				$data->lab_address 	= $report['lab_address'];
				$data->lab_city 	= $report['lab_city'];
				$data->lab_state 	= $report['lab_state'];
				$data->lab_zip 		= $report['lab_zip'];
				$data->lab_phone 	= $report['lab_phone'];
				$data->lab_fax 		= $report['lab_fax'];
				$data->lab_clia 	= $report['lab_clia'];
				$data->lab_npi 		= $report['lab_npi'];


				$data->patient_name 	= $report['patient_name'];
				$data->patient_sex 		= $report['pa_gender'];
				$data->patient_dob 		= $report['dob'];
				$data->patient_ssn 		= $report['pa_ssn'];
				$data->patient_mrn 		= $report['pa_mrn'];
				$data->patient_code 	= $report['pa_code'];
				$data->patient_id 		= $report['pa_ID'];
				$data->patient_phone 	= $report['pa_phone'];
				$data->patient_address 	= $report['pa_address'];
				$data->patient_city 	= $report['pa_city'];
				$data->patient_state 	= $report['pa_state'];
				$data->patient_zip 		= $report['pa_zip'];
				$data->race 			= $report['pa_race'];
				$data->ethnicity 		= $report['pa_ethnicity'];

				$data->dob 				= $report['dob'];
				$data->doctor 			= $report['requesting_physician'];
				$data->doctor_npi 		= $report['doc_npi'];
				$data->doc 				= $report['doc'];
				$data->received_in_lab 	= $report['spec_received'];



				$where_rs = '';
				if (in_array($btype, array('pcr', 'blood'))) {
					$wheretests = array();
					foreach (explode(',', $report['panel_tests_requested']) as $t1) {
						if (stripos($t1, 'group_') !== false) {
							$gid = str_ireplace('GROUP_', '', $t1);
							$wheretests[] = " te.te_group_id = {$gid} ";
						} else if ($t1 > 0) {
							$wheretests[] = " te.te_ID = {$t1} ";
						}
					}
					if (count($wheretests) > 0) {
						$where_rs .= " and (" . implode(' or ', $wheretests) . ') ';
					}
				}



				$all_results_q = $wpdb->get_results("SELECT rs.*, te.*
								FROM {$result_tbl} rs
								join ci_test_panels te ON te.te_type='{$btype}' 
								and (te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug)
								WHERE report_id='" . $report['id'] . "' 
								and (conc is not null or `pos-neg` is not null)
								{$where_rs}
								group by rs.id", ARRAY_A);
				$all_tests = array();

				//$return['whrers'] = $where_rs;

				$covid_tests_arr = array();



				foreach ($all_results_q as $arr) {

					$arr['drug'] = strtoupper($arr['te_name']);

					$passflu = false;
					$hub_state = '';
					if (empty($arr['te_abrev'])) {
						$arr['te_abrev'] = $arr['drug'];
					}
					$group = (isset($groups[$arr['te_group_id']])) ? $groups[$arr['te_group_id']] : 'other';

					if ($btype == 'pcr') {
						$arr['type_of_specimen'] = $report['type_of_specimen'];
						$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $arr['te_group_id'], $report['created_date']);
					}

					if ($has_flu_rsv) {
						if (
							stripos($arr['drug'], 'INFLUENZA A') !== false
							|| stripos($arr['drug'], 'Inf A') !== false
							|| stripos($arr['drug'], 'FLU B') !== false
						) {
							$arr['drug'] = 'FLUA';
						} else if (
							stripos($arr['drug'], 'INFLUENZA B') !== false
							|| stripos($arr['drug'], 'Inf B') !== false
							|| stripos($arr['drug'], 'FLU B') !== false
						) {
							$arr['drug'] = 'FLUB';
						} else if (
							stripos($arr['drug'], 'RSV A/B') !== false
							|| stripos($arr['drug'], 'RSV AB') !== false
							|| $arr['drug'] == 'RSV'
						) {
							$arr['drug'] = 'RSVA';
						} else if (
							stripos($arr['drug'], 'SARS-COV-2') !== false
							|| stripos($arr['drug'], 'SC-2') !== false
							|| stripos($arr['drug'], 'CoV2 PCR') !== false
						) {
							$arr['drug'] = 'NCOV-2019';
						}

						if (in_array($arr['drug'], array('FLUA', 'FLUB', 'RSVA', 'RSV', 'RSVB', 'NCOV-2019'))) {
							$passflu = true;
						}
					}

					$debughub = false;
					$send_conditions = array();

					if (!empty($only_testsids) || $has_flu_rsv) {

						if ($passflu) {
							//nothing
						} else if (in_array($arr['te_ID'], $only_testsids)) {
							//check condition
							$hubkey = $arr['te_ID'] . '_' . strtoupper($report['pa_state']);
							if (!isset($hub_conditions[$hubkey])) {
								$hubkey = $arr['te_ID'] . '_';
							}

							$pass_condition = false;
							if (isset($hub_conditions[$hubkey])) {
								$pass_condition = false;


								foreach ($hub_conditions[$hubkey] as $condi) {
									if ($condi['cond_pos_neg'] == 1 && !in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
										if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>1, <br>';
										continue;
									} else if ($condi['cond_pos_neg'] == 2 && $arr['pos-neg'] != 'NEG') {
										if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>2, <br>';
										continue;
									} else if (isset($condi['cond_only_tids']) && count($condi['cond_only_tids']) > 0) {
										$haspasscond = false;
										$send_condition_childs = array();
										foreach ($condi['cond_only_tids'] as $condtid) {
											$arr2 = array();
											if (isset($all_tests[$condtid])) {
												$arr2 = (array)$all_tests[$condtid];
												$arr2['pos-neg'] = $arr2['flag'];
											} else {
												foreach ($all_results_q as $tmarr) {
													if ($tmarr['te_ID'] == $condtid) {
														$arr2 = $tmarr;

														break;
													}
												}

												if (empty($arr2['pos-neg']) && $btype == 'pcr') {
													$arr2['type_of_specimen'] = $report['type_of_specimen'];
													$arr2 = tr_pdd_parse_pcr_test($arr2, $report['instrument'], $arr2['te_group_id'], $report['created_date']);
												}
											}

											if ($condi['cond_only_pos_neg'] == 1 && !in_array($arr2['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
												if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>3, <br>';
												continue;
											} else if ($condi['cond_only_pos_neg'] == 2 && $arr2['pos-neg'] != 'NEG') {
												if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>4, <br>';
												continue;
											} else if (in_array($arr2['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH', 'NEG'))) {
												$haspasscond = true;
												if (isset($condi['send_cond_tests']) && $condi['send_cond_tests']) {
													$send_condition_childs[$arr2['te_ID']] = $arr2;
												}
											}
										}
										if (!$haspasscond) {
											if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>3, <br>';
											continue;
										} else {
											$send_conditions = array_merge($send_conditions, $send_condition_childs);
										}
									} else if (isset($condi['cond_only_tid']) && $condi['cond_only_tid'] > 0) {
										$arr2 = array();
										if (isset($all_tests[$condi['cond_only_tid']])) {
											$arr2 = (array)$all_tests[$condi['cond_only_tid']];
											$arr2['pos-neg'] = $arr2['flag'];
										} else {
											foreach ($all_results_q as $tmarr) {
												if ($tmarr['te_ID'] == $condi['cond_only_tid']) {
													$arr2 = $tmarr;

													break;
												}
											}

											if (empty($arr2['pos-neg']) && $btype == 'pcr') {
												$arr2['type_of_specimen'] = $report['type_of_specimen'];
												$arr2 = tr_pdd_parse_pcr_test($arr2, $report['instrument'], $arr2['te_group_id'], $report['created_date']);
											}
										}

										if ($condi['cond_only_pos_neg'] == 1 && !in_array($arr2['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) {
											if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>3, <br>';
											continue;
										} else if ($condi['cond_only_pos_neg'] == 2 && $arr2['pos-neg'] != 'NEG') {
											if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>4, <br>';
											continue;
										}
									}

									$pass_condition = true;

									$hub_state = $condi['state'];
									break;
								}

								if (!$pass_condition) {
									continue;
								}



								if (!empty($hub_state) && $hub_state != $report['pa_state']) {
									$return['continueby'] .= $arr['te_name'] . '=>5, <br>';
									continue;
								}
							} else if (count($hub_conditions) > 0) {
								continue;
							}
						} else {
							if ($debughub) $return['continueby'] .= $arr['te_name'] . '=>6, <br>';
							continue;
						}
					}


					if (isset($covid_tests[$arr['drug']]) && !in_array($arr['te_ID'], $only_testsids)) {
						$covid_tests_arr[] = $arr;
						//$return['continueby'].= '7, ';
						continue;
					}

					if ($keyobj['wsdl_type'] == 'covid') {
						//$return['continueby'].= '8, ';
						continue;
					}

					$test = array(
						$drug_name 	=> $arr['drug'],
						'tID' 		=> $arr['te_ID'],
						'abrev' 	=> $arr['te_abrev'],
						'loinc_code' => $arr['te_loinc_code'],
						'conc' 		=> $arr['conc'],
						'pos-neg' 	=> $pos_neg[$arr['pos-neg']],
						'flag' 		=> $arr['pos-neg'],
						//'cutoff' => $arr['cutoff'],
						'comments' 	=> $arr['comments'],
						'group' 	=> $group,
					);

					if (!empty($hub_state)) {
						$test['state'] = $hub_state;
					}
					$all_tests[$arr['te_ID']] = (object)$test;

					foreach ($send_conditions as $arr2) {
						$testc = array(
							$drug_name 	=> $arr2['drug'],
							'tID' 		=> $arr2['te_ID'],
							'abrev' 	=> $arr2['te_abrev'],
							'loinc_code' => $arr2['te_loinc_code'],
							'conc' 		=> $arr2['conc'],
							'pos-neg' 	=> $pos_neg[$arr2['pos-neg']],
							'flag' 		=> $arr2['pos-neg'],
							//'cutoff' => $arr['cutoff'],
							'comments' 	=> $arr2['comments'],
							'group' 	=> $group,
						);
						if (!empty($hub_state)) {
							$testc['state'] = $hub_state;
						}
						$all_tests[$arr2['te_ID']] = (object)$testc;
					}
				}

				if ($btype == 'pcr' && $covid_tests_arr) {
					if (empty($arr['te_abrev'])) {
						$arr['te_abrev'] = $arr['drug'];
					}
					$arr = trpdd_cal_ncovid19($default_arr, $report['id'], $report['instrument'], $report['created_date']);
					$test = array(
						'pathogen' => $arr['drug'],
						'abrev' => $arr['te_abrev'],
						'pos-neg' => $pos_neg[$arr['pos-neg']],
						'flag' => $arr['pos-neg'],
					);

					$all_tests[] = (object)$test;
				}

				if (empty($all_tests)) {
					continue;
				}



				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$data->tests = $ctests;
				//end tests


				$results[] = $data;
			}



			$result = new stdClass();
			$result->result = $results;
			$return['date'] = $today;
			$return['total'] = count($results);
			$return['results'] = $result;
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_' . $btype . '_results_data', $key_id, $today, $return['status'], $return['total'], $xmlout);


		return $xmlout;
	}

	/**
	 * get Results pdf
	 * @param string $key
	 * @param string $xml Format: <xml><client_id>your name</client_id></xml>
	 * @param string $page  default 1
	 * @param string $limit default 10
	 * @param string $btype default tox( types: tox, pcr, blood, pgx)
	 * @return string XML Results
	 */
	public function get_pdf_results($key, $xml, $page = 1, $limit = 10, $btype = 'tox')
	{
		global $wpdb;

		if ($limit <= 1) $limit = 10;
		if ($btype == '') $btype = 'tox';
		else if ($btype == 'pgx') $btype = 'dna';

		if ($page == 0) $page = 1;


		$client_id = '';
		$export_type = 'pdf_' . $btype;
		$joins = '';

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'get_pdf_results', 'pdf'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$return = array(
				'status' => 'OK',
				'btype'  => $btype
			);
			$table = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$where = " ( (re.id >0 and re.estatus='C') or (re.id is null and ba.result_pdf!=''))";
			$where .= " and ba.batch_id > 20150101 ";

			try {
				$data = $this->xmlToObj($xml);
				$client_id = substr($data->client_id, 0, 10);

				if (!empty($client_id)) {
					$joins .= " left join ci_exported_ids ex ON ba.mid = ex.id and ex.`type` = '{$export_type}' and  ex.client_id='{$client_id}' ";
					if (sizeof($data->last_ids) > 0) {
						foreach ($data->last_ids as $id) {
							$wpdb->query("insert IGNORE into ci_exported_ids set id={$id}, `type`='{$export_type}', client_id = '{$client_id}' ");
						}
					}
					if ($page != -1) {
						$where .= " and ex.id is null ";
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}



			if ($data->doctors && count($data->doctors) > 0) {
				$sql_doc = "select doc_ID from ci_doc where ";
				$i = 0;
				foreach ($data->doctors as $doctor) {
					$doctor->doc_last = trim($doctor->doc_last);
					$doctor->doc_first = trim($doctor->doc_first);
					$doctor->doc_npi = trim($doctor->doc_npi);
					$doctor->doc_phone = trim($doctor->doc_phone);

					if (strlen($doctor->doc_phone) < 6 || strpos($doctor->doc_phone, '0-00') !== false) {
						$doctor->doc_phone = 'nophone';
					}
					if (strlen($doctor->doc_npi) < 6 || strpos($doctor->doc_npi, '00') !== false) {
						$doctor->doc_npi = 'nodoc_npi';
					}
					if (strlen($doctor->doc_email) < 6 || !is_email($doctor->doc_email)) {
						$doctor->doc_email = 'nodoc_npi';
					}
					$sql_doc .= ($i > 0 ? ' or ' : '') .
						$wpdb->prepare(
							"  (doc_first='%s' and doc_last='%s') 
                                            or REPLACE(doc_npi,'-','') = '%s'  
                                            or REPLACE(doc_phone,'-','') = '%s'
                                            or doc_email = '%s' ",
							$doctor->doc_first,
							$doctor->doc_last,
							str_replace('-', '', $doctor->doc_npi),
							str_replace('-', '', $doctor->doc_phone),
							$doctor->doc_email
						);
					$i++;
				}
				$docids = $wpdb->get_col($sql_doc);
				$docids = implode(',', $docids);
				$where .= " and ba.doc_ID in ({$docids}) ";
			}


			$total = $wpdb->get_var("select count(*) from {$table} ba 
                        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                            where {$where}");
			$total_page = $limit > 0 ? ceil($total / $limit) : 1;


			$limit_str = (($page - 1) * $limit) . ',' . $limit;
			$sql = "SELECT
	                ba.*,
	                re.id,
	                re.created_date,
	                re.modified,
	                re.approve_date,
	                re.final_results,
	                doc.*,
                    pa.pa_gender
                    
	                FROM {$table} ba 
	                LEFT JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
	                LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
	                JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
	                {$joins}
	                WHERE {$where} 
	                Group by ba.mid 
	                order by ba.mid asc
	                ";
			if ($limit > 0) {
				$sql .= " limit {$limit_str} ";
			}
			$reports = $wpdb->get_results($sql, ARRAY_A);
			//  $return['sql_error'] = $wpdb->last_error.' .';
			$results = array();
			$report_ids = array();
			foreach ($reports as $report) {
				$path_db = 'uploads/' . $report['result_pdf'];
				if ($report['id'] > 0) {
					$report_new = tr_pdd_get_report_by_id($report['id'], $btype);
					list($path, $file) = trpdd_get_path_result_pdf($report_new, $btype);
					$path_db = $path . '/' . $file;
				}

				$file_path = WP_CONTENT_DIR . '/results/' . $path_db;

				if (!file_exists($file_path) || filemtime($file_path) < $report['modified']) {
					if ($report['id'] > 0) {
						trpdd_export_results_pdf($report['id'], '', false, $btype);
					}
				}

				$data = new stdClass();

				$data->mid = $report['mid'];
				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->batch_id = $report['batch_id'];
				$data->clinic = $report['requesting_practice'];
				$data->lab = $report['lab'];
				$data->patient_name = $report['patient_name'];
				$data->patient_sex = $report['pa_gender'];
				$data->dob = $report['dob'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doctor_first = $report['doc_first'];
				$data->doctor_last = $report['doc_last'];
				$data->doctor_phone = $report['doc_phone'];
				$data->doctor_email = $report['doc_email'];
				$data->doc = $report['doc'];

				$data->file_path = $path_db;
				$data->pdf_base64  = base64_encode(file_get_contents($file_path));
				$data->result_type = ($report['id'] > 0) ? 'report' : 'upload';
				$data->result_created_date = $report['created_date'];


				$results[] = $data;

				if (!empty($client_id))
					$wpdb->query("insert IGNORE into ci_exported_ids set id={$report['mid']}, `type`='{$export_type}', client_id = '{$client_id}' ");
			}

			$result = new stdClass();
			$return['page'] = $page;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count'] = count($results);
			$return['results'] = $result;
			$result->result = $results;
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_pdf_results', $key_id, $page, $return['status'], $return['total_page'], $xmlout);


		return $xmlout;
	}


	/**
	 * get covid results
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_covid_result($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
			'msg' => '',
		);

		if (empty($datetime_from)) {
			$datetime_from = date_i18n('Y-m-d');
		}
		if (empty($datetime_to)) {
			$datetime_to = date_i18n('Y-m-d 23:59:59');
		}

		if (strpos($datetime_from, '-') === false) {
			$datetime_from = date('Y-m-d H:i:s', strtotime($datetime_from));
		}
		if (strpos($datetime_to, '-') === false) {
			$datetime_to = date('Y-m-d H:i:s', strtotime($datetime_to));
		}

		$byhub = $page == -1 && $limit == 0;

		$include_M = ($page == -1);
		if ($page <= 0) $page = 1;

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'covid', 'pcr', 'acovid'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$site_url = get_bloginfo('url');
			$btype = 'pcr';

			$group_covid = $wpdb->get_row("select * from ci_test_groups 
										where tg_is_covid=1 
			                            and tg_type='pcr' 
										and tg_show_req = 1");
			if (!$group_covid) {
				$group_covid = $wpdb->get_row("select * from ci_test_groups 
				where tg_name like '%ncov%' 
				and tg_type='pcr' 
				and tg_show_req = 1");
			}

			if (!$group_covid) {
				$xmlout = @$this->objToXML($return, 'xml');
				return $xmlout;
			}



			$hub_condition = array();
			if ($byhub) {
				$hub_tests 	= get_option('ci_send_hub_tests', array());
				foreach ($hub_tests as $tarr) {
					if (isset($tarr['iscovid']) && $tarr['iscovid']) {
						$hub_condition = $tarr;
						break;
					}
				}
			}


			$covid_group_id = $group_covid->tg_ID;
			$covid_group_key = 'GROUP_' . $covid_group_id;


			$covid_single_tests = $wpdb->get_results("select * from ci_test_panels 
			where (te_name = 'SARS-CoV-2' or te_abrev='SARS-COV2' )
			and te_in_req = 1 ");


			$default_arr = $wpdb->get_row(
				"select te.* 
					 	from ci_test_panels te
					 	join ci_test_groups tg ON tg.tg_ID = te.te_group_id 
			            where tg.tg_type = 'pcr' and tg.tg_is_covid = 1 
			            and te_type='pcr' ",
				ARRAY_A
			);
			$default_arr['drug'] = $default_arr['te_name'];

			$alltscodes = $wpdb->get_results("select ts_name, ts_code from ci_test_specimens where ts_type = '{$btype}' and ts_code !='' ");
			$specimen_codes = array();
			foreach ($alltscodes as $r) {
				$r->ts_name = trim($r->ts_name);
				if (!empty($r->ts_code) && !empty($r->ts_name)) {
					$specimen_codes[strtolower($r->ts_name)] = $r->ts_code;
				}
			}


			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			$where = " (re.approve_date >= '{$datetime_from}' and re.approve_date <= '{$datetime_to}')";

			$rejected_status = tr_pdd_where_batch_rejected(false, true, 'pcr');
			$where .= " and {$rejected_status} ";

			if ($include_M) {
				$where .= " and ( re.id >0 and (re.estatus='C' or re.estatus='M')) ";
			} else {
				$where .= " and ( re.id >0 and re.estatus='C') ";
			}

			$where_tests_arr = array();
			if (!empty($covid_group_key)) {
				$where_tests_arr[] = " FIND_IN_SET('{$covid_group_key}',ba.panel_tests_requested) ";
			}
			foreach ($covid_single_tests as $test) {
				if (!empty($test->te_ID)) {
					$where_tests_arr[] = " FIND_IN_SET('{$test->te_ID}',ba.panel_tests_requested) ";
				}
			}


			if (empty($where_tests_arr)) {
				$where_tests_arr[] = ' 1 = 2';
			}

			$where_tests = implode(' or ', $where_tests_arr);

			$where .= " and ( {$where_tests} )  ";



			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}


			$covid_tests = trpdd_get_covid_tests('*');
			foreach ($covid_single_tests as $test) {
				$covid_tests[] = $test->te_name;
				if (!empty($test->te_abrev)) {
					$covid_tests[] = $test->te_abrev;
				}
			}

			$covid_tests_str = implode("','", $covid_tests);


			$sql_total = "select count(DISTINCT(ba.mid)) 
                    FROM ci_batches_pcr ba
                    JOIN ci_reports_pcr re ON re.patient_id = ba.assen
                    join ci_results_pcr rs ON rs.report_id = re.id and rs.drug in('{$covid_tests_str}')
                    WHERE {$where} ";

			$total = $wpdb->get_var($sql_total);

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;

			$sql = "SELECT
               ba.mid,
				ba.assen,
				ba.requesting_practice,
				ba.lab,
				ba.patient_name,
				ba.dob,
				ba.requesting_physician,
				ba.panel_tests_requested,
				ba.doc,
				ba.batch_created,
				ba.batch_status,
				ba.type_of_specimen,
				
				lab.lab_dba,
                lab.lab_dba_init,
                lab.lab_logo,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                lab.lab_phone,
                lab.lab_fax,
                lab.lab_clia,
                lab.lab_npi,
				
                doc.doc_npi,
                doc.doc_first,
                doc.doc_last,
                re.id,
                re.modified,
                re.approve_date,
                re.created_date,
                re.final_results,
                re.instrument,
                re.estatus,
                
                pa.pa_ID,
                pa.pa_ssn,
                pa.pa_mrn,
                pa.pa_phone,
                pa.pa_gender,
                pa.pa_code,
                pa.pa_address,
                pa.pa_city,
                pa.pa_state,
                pa.pa_zip,
                pa.pa_race,
                pa.pa_ethnicity,
                
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`
                
                FROM ci_batches_pcr ba 
                JOIN ci_reports_pcr re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    join ci_results_pcr rs ON rs.report_id = re.id and rs.drug in('{$covid_tests_str}')
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_clinic cli On cli.cID = ba.cli_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
					
                    WHERE  {$where}
                    Group by ba.mid 
                    order by ba.mid asc
                    ";

			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}


			$reports = $wpdb->get_results($sql, ARRAY_A);
			if (!empty($wpdb->last_error)) {
				$return['sqlerror'] = $wpdb->last_error;
			}

			$results = array();
			foreach ($reports as $report) {
				$data = new stdClass();

				if ($report['pa_ID'] == 0) {
					$return['miss_pa_ID'] = $report['assen'];
				}

				if (empty($report['doc']) || $report['doc'] === '0000-00-00 00:00:00') {
					$report['doc'] = $report['batch_created'];
				}

				$sncodes = array();
				foreach (explode(',', $report['type_of_specimen']) as $spt) {
					$spt = strtolower(trim($spt));
					$sncodes[] = isset($specimen_codes[$spt]) ? $specimen_codes[$spt] : '';
				}

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->accession_number = $report['assen'];
				$data->type_of_specimen = $report['type_of_specimen'];
				$data->snomed_codes = implode(',', $sncodes);
				$data->clinic = $report['requesting_practice'];
				$data->clinic_name = $report['requesting_practice'];
				$data->clinic_street = $report['cli_street'];
				$data->clinic_city = $report['cli_city'];
				$data->clinic_state = $report['cli_state'];
				$data->clinic_zip = $report['cli_zip'];
				$data->clinic_phone = $report['cli_om-phone'];

				$data->lab = $report['lab'];
				$data->lab_name = $report['lab'];
				$data->lab_dba = $report['lab_dba'];
				$data->lab_dba_init = $report['lab_dba_init'];
				$data->lab_logo = $site_url . '/wp-content/uploads/logos/' . $report['lab_logo'];
				$data->lab_address = $report['lab_address'];
				$data->lab_city = $report['lab_city'];
				$data->lab_state = $report['lab_state'];
				$data->lab_zip = $report['lab_zip'];
				$data->lab_phone = $report['lab_phone'];
				$data->lab_fax = $report['lab_fax'];
				$data->lab_clia = $report['lab_clia'];
				$data->lab_npi = $report['lab_npi'];

				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['pa_mrn'];
				$data->patient_code = $report['pa_code'];
				$data->patient_id = $report['pa_ID'];
				$data->patient_phone = $report['pa_phone'];
				$data->patient_sex = $report['pa_gender'];
				$data->patient_dob = $report['dob'];
				$data->patient_address = $report['pa_address'];
				$data->patient_city = $report['pa_city'];
				$data->patient_state = $report['pa_state'];
				$data->patient_zip = $report['pa_zip'];
				$data->race = $report['pa_race'];
				$data->ethnicity = $report['pa_ethnicity'];

				$data->dob = $report['dob'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_name = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doc = $report['doc'];
				$data->collection_date = $report['doc'];

				if (empty($data->doctor_name)) {
					$data->doctor = $report['doc_last'] . ',' . $report['doc_first'];
					$data->doctor_name = $report['doc_last'] . ',' . $report['doc_first'];
				}

				if (empty($data->patient_code)) {
					$data->patient_code = trpdd_patient_encode($report['pa_ID']);
				}

				if ($report['estatus'] === 'M') {
					$arr = array(
						'drug' => 'Covid-19',
						'pos-neg' => 'INV'
					);
				} else {
					$report_tests = explode(',', $report['panel_tests_requested']);
					if (!in_array($covid_group_key, $report_tests)) {
						$testid = 0;
						foreach ($covid_single_tests as $testobj) {
							if (in_array($testobj->te_ID, $report_tests)) {
								$testid = $testobj->te_ID;
								break;
							}
						}
						$arr = $wpdb->get_row("select * from ci_test_panels te
						JOIN ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
						where te_ID = {$testid} 
						and rs.report_id = {$report['id']} ", ARRAY_A);
						if (empty($arr)) {
							continue;
						}
						//var_dump($arr);exit;
						$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $arr['te_group_id'], $report['created_date']);
					} else {
						$arr = trpdd_cal_ncovid19($default_arr, $report['id'], $report['instrument'], $report['created_date']);
					}
				}


				if (!empty($hub_condition)) {
					if ($hub_condition['cond_pos_neg'] == 1 && !in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) {
						//$return['continueby'].= '1, ';
						continue;
					} else if ($hub_condition['cond_pos_neg'] == 2 && $arr['pos-neg'] != 'NEG') {
						//$return['continueby'].= '2, ';
						continue;
					}
					$hub_state = $hub_condition['state'];
					if (!empty($hub_state) && $hub_state != $report['pa_state']) {
						//$return['continueby'].= '5, ';
						continue;
					}
				}


				$test = array(
					'pathogen' => $arr['drug'],
					'pos-neg' => $arr['pos-neg'],
					'conc' => $arr['conc'],
					//'high' => $arr['high'],
					//'low' => $arr['low'],
					//'ckey' => $arr['ckey'],
				);
				$all_tests = array((object)$test);

				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$data->tests = $ctests;


				$results[] = $data;
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;

			$return['total'] = $total;
			$return['rows_per_page'] = $limit;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count_results'] = $count_results;
			$return['datetime_from'] = $datetime_from;
			$return['datetime_to'] = $datetime_to;
			$return['results'] = $result;
			$return['include_M'] = $include_M;
		}

		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_covid_result', $key_id, $datetime_from . ' to ' . $datetime_to, $return['status'], $return, $xmlout);


		return $xmlout;
	}

	/**
	 * get Antigen results
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_antigen_result($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
			'msg' => '',
		);

		if (empty($datetime_from)) {
			$datetime_from = date_i18n('Y-m-d');
		}
		if (empty($datetime_to)) {
			$datetime_to = date_i18n('Y-m-d 23:59:59');
		}

		if (strpos($datetime_from, '-') === false) {
			$datetime_from = date('Y-m-d H:i:s', strtotime($datetime_from));
		}
		if (strpos($datetime_to, '-') === false) {
			$datetime_to = date('Y-m-d H:i:s', strtotime($datetime_to));
		}

		$include_M = $page == -1;
		if ($page <= 0) $page = 1;
		//if($limit>2000 || $limit ==0)$limit=2000;

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'antigen', 'pcr', 'acovid'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$site_url = get_bloginfo('url');
			$btype = 'pcr';


			$group_covid = $wpdb->get_row("select * from ci_test_groups 
										where (tg_name='Antigen' or tg_short_name = 'Antigen')
			                            and tg_type='pcr' ");

			if (!$group_covid) {
				$group_covid = $wpdb->get_row("select * from ci_test_groups 
										where (tg_name='SARS-CoV-2 Rapid Antigen' or tg_short_name = 'SARS-CoV-2 Rapid Antigen')
			                            and tg_type='pcr' ");
			}

			if (!$group_covid) {
				$group_covid = $wpdb->get_row("select * from ci_test_groups 
										where tg_short_name like '%Antigen%' and tg_type='pcr' ");
			}

			if (!$group_covid) {
				$return['status'] = 'DENY';
				$return['msg'] = 'Not allow';
				$xml = @$this->objToXML($return, 'xml');
				return $xml;
			}

			$covid_group_id = $group_covid->tg_ID;
			$covid_group_key = 'GROUP_' . $covid_group_id;


			$default_arr = $wpdb->get_row(
				"select te.* 
					 	from ci_test_panels te
			            where te_group_id = {$covid_group_id}
			            and te_type='pcr' ",
				ARRAY_A
			);
			$default_arr['drug'] = $default_arr['te_name'];

			$alltscodes = $wpdb->get_results("select ts_name, ts_code from ci_test_specimens where ts_type = '{$btype}' and ts_code !='' ");
			$specimen_codes = array();
			foreach ($alltscodes as $r) {
				$r->ts_name = trim($r->ts_name);
				if (!empty($r->ts_code) && !empty($r->ts_name)) {
					$specimen_codes[strtolower($r->ts_name)] = $r->ts_code;
				}
			}


			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			$where = " (re.approve_date >= '{$datetime_from}' and re.approve_date <= '{$datetime_to}')";

			$rejected_status = tr_pdd_where_batch_rejected(false, true, 'pcr');
			$where .= " and {$rejected_status} ";

			if ($include_M) {
				$where .= " and ( re.id >0 and (re.estatus='C' or re.estatus='M')) ";
			} else {
				$where .= " and ( re.id >0 and re.estatus='C') ";
			}
			$where .= " and FIND_IN_SET('{$covid_group_key}',ba.panel_tests_requested)  ";

			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}

			$covid_tests_str = $default_arr['drug'];

			$total = $wpdb->get_var("select count(DISTINCT(ba.mid)) 
                    FROM ci_batches_pcr ba
                    JOIN ci_reports_pcr re ON re.patient_id = ba.assen
                    join ci_results_pcr rs ON rs.report_id = re.id and rs.drug in('{$covid_tests_str}')
                    WHERE {$where} ");

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;

			$sql = "SELECT
               ba.mid,
				ba.assen,
				ba.requesting_practice,
				ba.lab,
				ba.patient_name,
				ba.dob,
				ba.requesting_physician,
				ba.doc,
				ba.batch_created,
				ba.batch_status,
				ba.type_of_specimen,
				
				lab.lab_dba,
                lab.lab_dba_init,
                lab.lab_logo,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                lab.lab_phone,
                lab.lab_fax,
                lab.lab_clia,
                lab.lab_npi,
				
                doc.doc_npi,
                doc.doc_first,
                doc.doc_last,
                re.id,
                re.modified,
                re.approve_date,
                re.final_results,
                re.instrument,
                re.estatus,
                
                pa.pa_ID,
                pa.pa_ssn,
                pa.pa_mrn,
                pa.pa_phone,
                pa.pa_gender,
                pa.pa_code,
                pa.pa_address,
                pa.pa_city,
                pa.pa_state,
                pa.pa_zip,
                pa.pa_race,
                pa.pa_ethnicity,
                
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`,
                
                rs.conc,
                rs.`pos-neg`
                
                FROM ci_batches_pcr ba 
                JOIN ci_reports_pcr re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    join ci_results_pcr rs ON rs.report_id = re.id and rs.drug in('{$covid_tests_str}')
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_clinic cli On cli.cID = ba.cli_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
					
                    WHERE  {$where}
                    Group by ba.mid 
                    order by ba.mid asc
                    ";

			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}


			$reports = $wpdb->get_results($sql, ARRAY_A);
			if (!empty($wpdb->last_error)) {
				$return['sqlerror'] = $wpdb->last_error;
			}

			$results = array();
			foreach ($reports as $report) {
				$data = new stdClass();

				if ($report['pa_ID'] == 0) {
					$return['miss_pa_ID'] = $report['assen'];
				}

				if (empty($report['doc']) || $report['doc'] === '0000-00-00 00:00:00') {
					$report['doc'] = $report['batch_created'];
				}

				$sncodes = array();
				foreach (explode(',', $report['type_of_specimen']) as $spt) {
					$spt = strtolower(trim($spt));
					$sncodes[] = isset($specimen_codes[$spt]) ? $specimen_codes[$spt] : '';
				}

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->accession_number = $report['assen'];
				$data->type_of_specimen = $report['type_of_specimen'];
				$data->snomed_codes = implode(',', $sncodes);
				$data->clinic = $report['requesting_practice'];
				$data->clinic_name = $report['requesting_practice'];
				$data->clinic_street = $report['cli_street'];
				$data->clinic_city = $report['cli_city'];
				$data->clinic_state = $report['cli_state'];
				$data->clinic_zip = $report['cli_zip'];
				$data->clinic_phone = $report['cli_om-phone'];

				$data->lab = $report['lab'];
				$data->lab_name = $report['lab'];
				$data->lab_dba = $report['lab_dba'];
				$data->lab_dba_init = $report['lab_dba_init'];
				$data->lab_logo = $site_url . '/wp-content/uploads/logos/' . $report['lab_logo'];
				$data->lab_address = $report['lab_address'];
				$data->lab_city = $report['lab_city'];
				$data->lab_state = $report['lab_state'];
				$data->lab_zip = $report['lab_zip'];
				$data->lab_phone = $report['lab_phone'];
				$data->lab_fax = $report['lab_fax'];
				$data->lab_clia = $report['lab_clia'];
				$data->lab_npi = $report['lab_npi'];

				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['pa_mrn'];
				$data->patient_code = $report['pa_code'];
				$data->patient_id = $report['pa_ID'];
				$data->patient_phone = $report['pa_phone'];
				$data->patient_sex = $report['pa_gender'];
				$data->patient_dob = $report['dob'];
				$data->patient_address = $report['pa_address'];
				$data->patient_city = $report['pa_city'];
				$data->patient_state = $report['pa_state'];
				$data->patient_zip = $report['pa_zip'];
				$data->race = $report['pa_race'];
				$data->ethnicity = $report['pa_ethnicity'];

				$data->dob = $report['dob'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_name = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doc = $report['doc'];
				$data->collection_date = $report['doc'];

				if (empty($data->doctor_name)) {
					$data->doctor = $report['doc_last'] . ',' . $report['doc_first'];
					$data->doctor_name = $report['doc_last'] . ',' . $report['doc_first'];
				}

				if (empty($data->patient_code)) {
					$data->patient_code = trpdd_patient_encode($report['pa_ID']);
				}

				if ($report['estatus'] === 'M') {
					$arr = array(
						'drug' => $default_arr['drug'],
						'pos-neg' => 'INV'
					);
				} else {
					$default_arr['conc'] = $report['conc'];
					$default_arr['pos-neg'] = $report['pos-neg'];
					$default_arr['type_of_specimen'] = $report['type_of_specimen'];
					$arr = tr_pdd_parse_pcr_test($default_arr, $report['instrument'], $covid_group_id, $report['doc']);
				}

				$test = array(
					'pathogen' => $arr['drug'],
					'pos-neg' => $arr['pos-neg'],
				);
				$all_tests = array((object)$test);

				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$data->tests = $ctests;


				$results[] = $data;
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;

			$return['total'] = $total;
			$return['rows_per_page'] = $limit;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count_results'] = $count_results;
			$return['datetime_from'] = $datetime_from;
			$return['datetime_to'] = $datetime_to;
			$return['results'] = $result;
			$return['include_M'] = $include_M;
		}


		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_antigen_result', $key_id, $datetime_from . ' to ' . $datetime_to, $return['status'], $return, $xmlout);


		return $xmlout;
	}



	/**
	 * get blood covid results
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_blood_covid_result($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
			'msg' => '',
		);

		if (empty($datetime_from)) {
			$datetime_from = date_i18n('Y-m-d');
		}
		if (empty($datetime_to)) {
			$datetime_to = date_i18n('Y-m-d 23:59:59');
		}

		if (strpos($datetime_from, '-') === false) {
			$datetime_from = date('Y-m-d H:i:s', strtotime($datetime_from));
		}
		if (strpos($datetime_to, '-') === false) {
			$datetime_to = date('Y-m-d H:i:s', strtotime($datetime_to));
		}

		if ($page == 0) $page = 1;
		//if($limit>2000 || $limit ==0)$limit=2000;

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', 'blood_covid', 'blood'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {
			$site_url = get_bloginfo('url');
			$btype = 'blood';

			$pos_neg = array(
				'' => '',
				'NEG' => 'Negative',
				'POS' => 'Positive',
				'PEN' => 'Pending',
				'INC' => 'Inconclusive',
				'PRP' => 'Positive',
				'INV' => 'Invalid',
				'NGE' => 'Negative',
			);

			$blood_tests = array(680, 681, 683, 679);


			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);

			$where = " (re.approve_date >= '{$datetime_from}' and re.approve_date <= '{$datetime_to}')";
			$where .= " and ( re.id >0 and re.estatus='C') ";

			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}



			//get from blood
			$where_blood_tests = array();
			foreach ($blood_tests as $tid) {
				$where_blood_tests[] = " FIND_IN_SET('{$tid}',ba.panel_tests_requested) ";
			}
			$where_blood_tests = implode(' or ', $where_blood_tests);

			$alltscodes = $wpdb->get_results("select ts_name, ts_code from ci_test_specimens where ts_type = '{$btype}' and ts_code !='' ");
			$specimen_codes = array();
			foreach ($alltscodes as $r) {
				$r->ts_name = trim($r->ts_name);
				if (!empty($r->ts_code) && !empty($r->ts_name)) {
					$specimen_codes[strtolower($r->ts_name)] = $r->ts_code;
				}
			}


			$total = $wpdb->get_var("select count(ba.mid) 
                    FROM ci_batches_blood ba
                    JOIN ci_reports_blood re ON re.patient_id = ba.assen
                    WHERE {$where} 
                    and ({$where_blood_tests})");

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;


			$sql = "SELECT
				ba.mid,
				ba.assen,
				ba.requesting_practice,
				ba.lab,
				ba.patient_name,
				ba.dob,
				ba.requesting_physician,
				ba.doc,
				ba.batch_created,
				ba.type_of_specimen,
				
				lab.lab_dba,
                lab.lab_dba_init,
                lab.lab_logo,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                lab.lab_phone,
                lab.lab_fax,
                lab.lab_clia,
                lab.lab_npi,
				
                doc.doc_npi,
                doc.doc_first,
                doc.doc_last,
                re.id,
                re.modified,
                re.approve_date,
                re.final_results,
                
                pa.pa_ID,
                pa.pa_ssn,
                pa.pa_mrn,
                pa.pa_phone,
                pa.pa_gender,
                pa.pa_code,
                pa.pa_address,
                pa.pa_city,
                pa.pa_state,
                pa.pa_zip,
                pa.pa_race,
                pa.pa_ethnicity,
                
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`
                
                FROM ci_batches_blood ba 
                JOIN ci_reports_blood re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_clinic cli On cli.cID = ba.cli_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
					
                    WHERE {$where} 
                    and ({$where_blood_tests})
                    
                    Group by ba.mid 
                    order by ba.mid asc
                    ";

			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}

			$reports = $wpdb->get_results($sql, ARRAY_A);

			$results = array();
			foreach ($reports as $report) {
				if (empty($report['pa_code'])) {
					$report['pa_code'] = trpdd_patient_encode($report['pa_ID']);
					$wpdb->update('ci_patients', array('pa_code' => $report['pa_code']), array('pa_ID' => $report['pa_ID']));
				}

				$data = new stdClass();

				$sncodes = array();
				foreach (explode(',', $report['type_of_specimen']) as $spt) {
					$spt = strtolower(trim($spt));
					$sncodes[] = isset($specimen_codes[$spt]) ? $specimen_codes[$spt] : '';
				}

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->assen = $report['assen'];
				$data->accession_number = $report['assen'];
				$data->type_of_specimen = $report['type_of_specimen'];
				$data->snomed_codes = implode(',', $sncodes);
				$data->clinic = $report['requesting_practice'];
				$data->clinic_name = $report['requesting_practice'];
				$data->clinic_street = $report['cli_street'];
				$data->clinic_city = $report['cli_city'];
				$data->clinic_state = $report['cli_state'];
				$data->clinic_zip = $report['cli_zip'];
				$data->clinic_phone = $report['cli_om-phone'];

				$data->lab = $report['lab'];
				$data->lab_name = $report['lab'];
				$data->lab_dba = $report['lab_dba'];
				$data->lab_dba_init = $report['lab_dba_init'];
				$data->lab_logo = $site_url . '/wp-content/uploads/logos/' . $report['lab_logo'];
				$data->lab_address = $report['lab_address'];
				$data->lab_city = $report['lab_city'];
				$data->lab_state = $report['lab_state'];
				$data->lab_zip = $report['lab_zip'];
				$data->lab_phone = $report['lab_phone'];
				$data->lab_fax = $report['lab_fax'];
				$data->lab_clia = $report['lab_clia'];
				$data->lab_npi = $report['lab_npi'];

				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['pa_mrn'];
				$data->patient_code = $report['pa_code'];
				$data->patient_id = $report['pa_ID'];
				$data->patient_phone = $report['pa_phone'];
				$data->patient_sex = $report['pa_gender'];
				$data->patient_dob = $report['dob'];
				$data->patient_address = $report['pa_address'];
				$data->patient_city = $report['pa_city'];
				$data->patient_state = $report['pa_state'];
				$data->patient_zip = $report['pa_zip'];
				$data->race 			= $report['pa_race'];
				$data->ethnicity 		= $report['pa_ethnicity'];

				$data->dob = $report['dob'];
				$data->doctor = $report['requesting_physician'];
				$data->doctor_name = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->doc = $report['doc'];
				$data->collection_date = $report['doc'];

				if (empty($data->doctor_name)) {
					$data->doctor = $report['doc_last'] . ',' . $report['doc_first'];
					$data->doctor_name = $report['doc_last'] . ',' . $report['doc_first'];
				}

				if (empty($data->patient_code)) {
					$data->patient_code = trpdd_patient_encode($report['pa_ID']);
				}



				$all_tests = array();
				$arr = $wpdb->get_results("select * from ci_results_blood 
										where report_id = {$report['id']} and drug like '%cov%'", ARRAY_A);
				foreach ($arr as $a) {
					$t = new stdClass();
					$t->pathogen = $a['drug'];
					$t->conc = $a['conc'];
					$t->{'pos-neg'} = $pos_neg[$a['pos-neg']];
					$all_tests[] = $t;
				}
				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$data->tests = $ctests;


				$results[] = $data;
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;

			$return['total'] = $total;
			$return['rows_per_page'] = $limit;
			$return['total_page'] = $total_page;
			$return['paged'] = $page;
			$return['count_results'] = $count_results;
			$return['datetime_from'] = $datetime_from;
			$return['datetime_to'] = $datetime_to;
			$return['results'] = $result;
		}




		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log('get_blood_covid_result', $key_id, $datetime_from . ' to ' . $datetime_to, $return['status'], $return, $xmlout);


		return $xmlout;
	}

	/**
	 * get tox results combined
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_tox_results_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		return $this->_get_result_combined($key, $datetime_from, $datetime_to, $limit, $page, 'tox');
	}

	/**
	 * get blood results combined
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_blood_results_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		return $this->_get_result_combined($key, $datetime_from, $datetime_to, $limit, $page, 'blood');
	}

	/**
	 * get pcr results combined
	 * [demoxml url="/wsdl?demo=get_pcr_results_combined.xml"]
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML 
	 */
	public function get_pcr_results_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		return $this->_get_result_combined($key, $datetime_from, $datetime_to, $limit, $page, 'pcr');
	}


	/**
	 * get pgx results combined
	 * @param string $key
	 * @param string $datetime_from approved date
	 * @param string $datetime_to (default is current if empty)
	 * @param string $limit (default 200)
	 * @param string $page (default 1)
	 * @return string XML
	 */
	public function get_pgx_results_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1)
	{
		return $this->_get_result_combined($key, $datetime_from, $datetime_to, $limit, $page, 'dna');
	}

	function _get_result_combined($key, $datetime_from, $datetime_to, $limit = 200, $page = 1, $btype = 'tox')
	{
		global $wpdb;

		$show_base64 = tr_get_option('ci_pdf_data', 'sv_64combined');
		$return = array(
			'status' => 'OK',
			'msg' => '',
		);
		if (empty($btype)) {
			$btype = 'tox';
		} else if ($btype == 'pgx') {
			$btype = 'dna';
		}

		$assenes = array();
		if ($datetime_to == 'assen') {
			$assenes = explode(',', $datetime_from);
			$datetime_from = '';
			$datetime_to = '';
		} else {
			if (empty($datetime_from)) {
				$datetime_from = date_i18n('Y-m-d');
			}
			if (empty($datetime_to)) {
				$datetime_to = date_i18n('Y-m-d 23:59:59');
			}

			if (strpos($datetime_from, '-') === false) {
				$datetime_from = date('Y-m-d H:i:s', strtotime($datetime_from));
			}
			if (strpos($datetime_to, '-') === false && $datetime_to != 'assen') {
				$datetime_to = date('Y-m-d H:i:s', strtotime($datetime_to));
			}
		}



		if ($page == 0) $page = 1;
		//if($limit>2000 || $limit ==0)$limit=2000;

		$table = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);
		$drug_name = $btype == 'pcr' ? 'pathogen' : 'drug';
		$site_url = get_bloginfo('url');
		$pos_neg = array(
			'' => '',
			'NEG' => 'Negative',
			'POS' => 'Positive',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
		);

		$default_arr = array();
		if ($btype == 'pcr') {
			$covid_testnames = trpdd_get_covid_tests('IC');
			$covid_tests = array(
				$covid_testnames[0] => array(),
				$covid_testnames[1] => array(),
			);

			$default_arr = $wpdb->get_row(
				"select te.* 
					 	from ci_test_panels te
					 	join ci_test_groups tg ON tg.tg_ID = te.te_group_id 
			            where tg.tg_type = 'pcr' and tg.tg_is_covid = 1 
			            and te_type='pcr' ",
				ARRAY_A
			);

			$default_arr['drug'] = $default_arr['te_name'];
		}

		$drug_groups = array();
		$all_drugrs = $wpdb->get_results("select te_name,tg_name from ci_test_panels te
						join ci_test_groups tg ON tg.tg_ID = te.te_group_id
						where te.te_type='{$btype}' ");
		foreach ($all_drugrs as $r) {
			$r->te_name = strtoupper($r->te_name);
			$drug_groups[$r->te_name] = $r->tg_name;
		}

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else if (!in_array($keyobj['wsdl_type'], array('', $btype, 'tox_rs'))) {
			$return['status'] = 'DENY';
			$return['msg'] = 'Not allow';
		} else {

			$drug_pairs = array();
			if ($btype == 'tox') {
				$drug_pairs = tr_pdd_get_drug_pairs();
			}

			$alltscodes = $wpdb->get_results("select ts_name, ts_code from ci_test_specimens where ts_type = '{$btype}' and ts_code !='' ");
			$specimen_codes = array();
			foreach ($alltscodes as $r) {
				$r->ts_name = trim($r->ts_name);
				if (!empty($r->ts_code) && !empty($r->ts_name)) {
					$specimen_codes[strtolower($r->ts_name)] = $r->ts_code;
				}
			}

			$where = '';
			if (!empty($assenes)) {
				$assenes_str = implode("','", $assenes);
				$where = " ba.assen in ('{$assenes_str}') ";
			} else {
				$where = " (re.approve_date >= '{$datetime_from}' and re.approve_date <= '{$datetime_to}')";
			}

			$cli_ids = $this->getClinicByKey($key);
			$lab_id = $this->getLabByKey($key);
			if (!in_array(-1, $cli_ids) && count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$where .= " and ba.cli_ID in ({$cli_ids})  ";
			} else if ($lab_id > 0) {
				$where .= " and ba.lab_id = {$lab_id} ";
			}

			$where .= " and ( re.id >0 and re.estatus='C') ";

			$total = $wpdb->get_var("select count(ba.mid) 
                    FROM {$table} ba
                    JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    WHERE {$where}");

			$total_page = $limit > 0 ? ceil($total / $limit) : 1;
			$offset = ($page - 1) * $limit;

			$sql = "SELECT
				ba.mid,
				ba.assen,
				ba.batch_id,
				ba.requesting_practice,
				ba.lab,
				ba.patient_name,
				ba.dob,
				ba.requesting_physician,
				ba.doc,
				ba.batch_created,
				ba.type_of_specimen,
				ba.panel_tests_requested,
				
                lab.lab_dba,
                lab.lab_dba_init,
                lab.lab_logo,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                lab.lab_phone,
                lab.lab_fax,
                lab.lab_clia,
                lab.lab_npi,
               
                doc.doc_npi,
                doc.doc_first,
                doc.doc_last,
                re.id,
                re.modified,
                re.approve_date,
                re.created_date,
                re.final_results,
                re.instrument,
                
                pa.pa_ID,
                pa.pa_ssn,
                pa.pa_mrn,
                pa.pa_phone,
                pa.pa_gender,
                pa.pa_code,
                pa.pa_address,
                pa.pa_city,
                pa.pa_state,
                pa.pa_zip,
                pa.pa_race,
                pa.pa_ethnicity,
                
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`
                
                FROM {$table} ba 
                LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    left join ci_clinic cli On cli.cID = ba.cli_ID

                    WHERE  
                    {$where}
                    Group by ba.mid 
                    order by ba.mid asc
                    ";
			if ($limit > 0) {
				$sql .= " limit {$offset},{$limit} ";
			}

			$reports = $wpdb->get_results($sql, ARRAY_A);


			$results = array();
			foreach ($reports as $report) {
				$data = new stdClass();

				$sncodes = array();
				foreach (explode(',', $report['type_of_specimen']) as $spt) {
					$spt = strtolower(trim($spt));
					$sncodes[] = isset($specimen_codes[$spt]) ? $specimen_codes[$spt] : '';
				}

				$data->result_status = tr_pdd_get_result_status($report['final_results']);
				$data->approve_date = $report['approve_date'];
				$data->accession_number = $report['assen'];
				$data->type_of_specimen = $report['type_of_specimen'];
				$data->snomed_codes = implode(',', $sncodes);
				$data->clinic_name = $report['requesting_practice'];
				$data->clinic_street = $report['cli_street'];
				$data->clinic_city = $report['cli_city'];
				$data->clinic_state = $report['cli_state'];
				$data->clinic_zip = $report['cli_zip'];
				$data->clinic_phone = $report['cli_om-phone'];

				$data->lab_name = $report['lab'];
				$data->lab_dba = $report['lab_dba'];
				$data->lab_dba_init = $report['lab_dba_init'];
				$data->lab_logo = $site_url . '/wp-content/uploads/logos/' . $report['lab_logo'];
				$data->lab_address = $report['lab_address'];
				$data->lab_city = $report['lab_city'];
				$data->lab_state = $report['lab_state'];
				$data->lab_zip = $report['lab_zip'];
				$data->lab_phone = $report['lab_phone'];
				$data->lab_fax = $report['lab_fax'];
				$data->lab_clia = $report['lab_clia'];
				$data->lab_npi = $report['lab_npi'];

				$data->patient_name = $report['patient_name'];
				$data->patient_ssn = $report['pa_ssn'];
				$data->patient_mrn = $report['pa_mrn'];
				$data->patient_code = $report['pa_code'];
				$data->patient_id = $report['pa_ID'];
				$data->patient_phone = $report['pa_phone'];
				$data->patient_sex = $report['pa_gender'];
				$data->patient_dob = $report['dob'];
				$data->race 			= $report['pa_race'];
				$data->ethnicity 		= $report['pa_ethnicity'];

				$data->patient_address = $report['pa_address'];
				$data->patient_city = $report['pa_city'];
				$data->patient_state = $report['pa_state'];
				$data->patient_zip = $report['pa_zip'];

				$data->doctor_name = $report['requesting_physician'];
				$data->doctor_npi = $report['doc_npi'];
				$data->collection_date = $report['doc'];

				if (empty($data->doctor_name)) {
					$data->doctor_name = $report['doc_last'] . ',' . $report['doc_first'];
				}

				if ($btype == 'tox') {
					$data->panel_tests_requested = $report['panel_tests_requested'];
				}

				if (empty($data->patient_code)) {
					$data->patient_code = trpdd_patient_encode($report['pa_ID']);
				}

				$filekey = substr(md5($btype . $report['id']), 2, 7);
				$detail_info = $report['id'] . '/' . $filekey;
				$data->pdf_url  = $site_url . '/pdfresult/' . $btype . '/' . $detail_info . '/' . $report['assen'] . '.pdf';
				$data->pdf_base64  = '';
				if ($show_base64) {
					$file_path = '';
					if ($report['id'] > 0) {
						$report_new = tr_pdd_get_report_by_id($report['id'], $btype);
						list($path, $file) = trpdd_get_path_result_pdf($report_new, $btype);
						$path_db = $path . '/' . $file;
						$file_path = WP_CONTENT_DIR . '/results/' . $path_db;

						if (!file_exists($file_path) || filemtime($file_path) < $report['modified']) {
							trpdd_export_results_pdf($report['id'], '', false, $btype);
							//$data->debug_created_file = 1;
						}
					}
					if (file_exists($file_path)) {
						$data->pdf_base64  = base64_encode(file_get_contents($file_path));
					} else {
						$data->pdf_base64  = '';
					}
				}




				$all_results_q = $wpdb->get_results("SELECT * FROM {$result_tbl} 
								WHERE report_id='" . $report['id'] . "' ", ARRAY_A);
				$all_tests = array();
				$covid_tests_arr = array();
				foreach ($all_results_q as $arr) {

					$arr['drug'] = strtoupper($arr['drug']);
					if (isset($covid_tests[$arr['drug']])) {
						$covid_tests_arr[] = $arr;
						continue;
					}

					if ($keyobj['wsdl_type'] == 'covid') {
						continue;
					} else if ($btype == 'tox') {
						if (!$arr['has_order']) {
							continue;
						}
						$report['matrix'] = (stripos($report['type_of_specimen'], "oral") !== false) ? "oral" : strtolower($report['type_of_specimen']);

						$medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);
						$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix']);
					}

					$group = (isset($drug_groups[$arr['drug']])) ? $drug_groups[$arr['drug']] : 'other';


					$test = array(
						$drug_name => $arr['drug'],
						'conc' => $arr['conc'],
						'pos-neg' => $pos_neg[$arr['pos-neg']],
						'comments' => $arr['comments'],
						'group' => $group
					);
					if ($btype == 'tox') {
						$test['cutoff'] = $arr['cutoff'];
						$test['detection'] = $arr['detection'];
					}
					$all_tests[] = (object)$test;
				}

				if ($btype == 'pcr' && $covid_tests_arr) {

					$arr = trpdd_cal_ncovid19($default_arr, $report['id'], $report['instrument'], $report['created_date']);
					$test = array(
						'pathogen' => $arr['drug'],
						'pos-neg' => $pos_neg[$arr['pos-neg']]
					);
					$all_tests[] = (object)$test;
				} else if ($btype == 'tox') {
					$validity_tests = array();
					$validities = tr_pdd_get_result_validity(0, '', $report['mid']);
					foreach ($validities as $arr) {
						if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
							$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
						} else {
							$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
						}
						$validity_test = array(
							'test' => $arr['u_name'],
							'value' => $arr['conc'],
							'outcome' => $arr['outcome'],
							'ref_range' => $arr['u_range'],
							'comments' => $arr['comments']
						);
						$validity_tests[] = (object)$validity_test;
					}

					//VDX
					$dvx = tr_pdd_get_screening_vdx($report['mid'], $report['batch_created'], $report['batch_id']);
					if ($dvx['checked'] && $dvx['resulted']) {
						$validity_test = array(
							'test' => 'VDX',
							'value' => '',
							'outcome' => $dvx['outcome'],
							'comments' => $dvx['comments']
						);
						$validity_tests[] = (object)$validity_test;
					}

					$vt = new stdClass();
					$vt->validity_test = $validity_tests;
					$data->validity_tests = $vt;
					//end validity tests

					//screening
					$screenings = array();
					$validities = tr_pdd_get_result_validity(1, '', $report['mid']);
					foreach ($validities as $arr) {
						if (!$arr['checked']) {
							continue;
						}

						$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

						if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
							$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
						} else {
							$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
						}


						$scre = array(
							'test' => $arr['u_name'],
							'value' => $arr['conc'],
							'outcome' => $arr['outcome'],
							'ref_range' => $arr['u_range'],
							'comments' => $arr['comments']
						);
						$screenings[] = (object)$scre;
					}


					$vt = new stdClass();
					$vt->screening_test = $screenings;
					$data->screening_tests = $vt;
				}


				$ctests = new stdClass();
				$ctests->test = $all_tests;
				$data->panel_tests = $ctests;
				//end tests


				$results[] = $data;
			}

			$count_results = count($results);

			$result = new stdClass();
			$result->result = $results;
			$return['total'] = $total;
			if ($limit > 0) {
				$return['rows_per_page'] = $limit;
				$return['total_page'] = $total_page;
				$return['paged'] = $page;
			}


			$return['count_results'] = $count_results;
			if (!empty($datetime_from)) {
				$return['datetime_from'] = $datetime_from;
				$return['datetime_to'] = $datetime_to;
			}

			$return['results'] = $result;
		}



		if (isset($_REQUEST['debug'])) {
			return $return;
		}
		$xmlout = @$this->objToXML($return, 'xml');

		//log
		$this->_log($btype . '_result_combined', $key_id, $datetime_from . ' to ' . $datetime_to, $return['status'], $return, $xmlout);


		return $xmlout;
	}

	/**
	 * @param string $key
	 * @param string $xml
	 * @return string XML
	 */
	function sync_hub($key, $xml)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
			'msg' => '',
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else {
			$data = null;
			try {
				$xml = trim($xml);
				$data = simplexml_load_string($xml);

				if (!$data) {
					//try again
					if (stripos($xml, 'encoding="utf-16"') > 0) {
						$xml = preg_replace("/\<\?xml[^\>]*\>/", '', $xml);
						$data = simplexml_load_string($xml);
						if (!$data) {
							$return['status'] = 'WRONG_XML';
							$return['msg'] = 'Wrong XML FORMAT';
						}
					} else {
						$return['status'] = 'WRONG_XML';
						$return['msg'] = 'Wrong XML FORMAT';
					}
				}

				if ($data) {
					if (isset($data->state_counts)) {

						foreach ($data->state_counts as $report) {
							$month = (string)$report->month;
							$year = (string)$report->year;
							$state = (string)$report->state;
							$count = (int)$report->count;

							if (!empty($month) && !empty($year) && !empty($state) && $count > 0) {
								$exist = $wpdb->get_var("select ID from ci_log_hubs 
												where log_month='{$month}' 
												and log_year = '{$year}' and log_state='{$state}'");
								if ($exist > 0) {
									$wpdb->update('ci_log_hubs', array('log_count' => $count), array('ID' => $exist));
								} else {
									$wpdb->insert('ci_log_hubs', array(
										'log_month' => $month,
										'log_year' => $year,
										'log_state' => $state,
										'log_count' => $count
									));
								}
								if (!empty($wpdb->last_error)) {
									$return['error'] = $wpdb->last_error;
								}
							}
						}
					}
				}
			} catch (Exception $e) {
				$return['status'] = 'WRONG_XML';
				$return['msg'] = 'Wrong XML FORMAT';
			}
		}

		$xml = @$this->objToXML($return, 'xml');
		return $xml;
	}
	/**
	 * @param string $key
	 * @param string $accession
	 * @return string XML
	 */
	public function sample_status($key, $accession)
	{
		global $wpdb;
		$return = array(
			'status' => 'OK',
		);

		$keyobj = $this->getRowKey($key);
		$key_id = $keyobj['wsdl_ID'];

		if (!$key_id) {
			$return['status'] = 'WRONG_KEY';
			$return['msg'] = 'Wrong key';
		} else {
			include_once(TRCPDD_PATH . 'inc/functions_custody_chain.php');

			foreach (tr_pdd_get_batch_types() as $btype) {

				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$adding_fields = '';
				if ($btype == 'study') {
					$adding_fields .= ', ba.receivingID';
				}
				$sql = "select 
							ba.mid,
							ba.batch_id,
							ba.position,
							ba.assen,
							ba.doc,
							ba.batch_created,
							ba.batch_type,
							ba.batch_created,
							ba.batch_status,
							ba.author_ID,
							ba.cli_ID,
							ba.billing_notes,
							ba.is_saved,
							pa.pa_state
							{$adding_fields}
						from {$batch_tbl} ba
						LEFT join ci_patients pa ON pa.pa_ID = ba.patient_id
						where (assen = '{$accession}' or oldreq='{$accession}') {$where_ba}";
				$bat = $wpdb->get_row($sql);
				if ($bat) {
					break;
				}
			}

			if ($bat) {
				$return['data'] = trcustody_chain_detail($bat, $btype, true);
			} else {
				$return['status'] = 'NOT_FOUND';
			}


			$return['accession'] = $accession;
		}

		//$return = @$this->objToXML($return, 'xml');
		return $return;
	}
}

//end file