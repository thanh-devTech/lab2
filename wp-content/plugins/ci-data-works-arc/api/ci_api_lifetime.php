<?php

class CI_Api_Lifetime{

	protected $options = array();
	public $debug = false;
	public $log='';
	public $api_id = -21;
	public $api_name = 'Lifetime';

	public $order_dir='';
	public $result_dir = '';
	public $archive_dir = '';

	public $log_file = WP_CONTENT_DIR.'/log/log_api_lifetime_main.txt';

	public function __construct($options) {
		global $wpdb;

		$this->options = $options;

		$dir = dirname(ABSPATH).'/lifetime/';

		$this->order_dir = $dir.'orders/';
		$this->result_dir = $dir.'results/';
		$this->archive_dir = $dir.'archive/';

	}

	public function log($message)
	{
		$f_log = fopen($this->log_file,'a+');
		if($f_log)
		{
			fwrite($f_log, date_i18n('Y-m-d H:i:s').' => '.$message."\n");
			fclose($f_log);
		}

		$this->log.=$message."\n";
	}

	public function build_csv_req($date, $btype)
	{
		global $wpdb;

		$rs = $wpdb->get_results("select te_ID,te_name,te_abrev, te_abrev2, te_group_id
                            from ci_test_panels 
                            where te_type='{$btype}' ");
		$all_tests = array();
		foreach($rs as $r)
		{

			$all_tests[$r->te_ID] = $r;
		}

		$groups = array();
		$rs = $wpdb->get_results("select tg_ID, 
						tg_name, 
						tg_short_name,
						tg_only_group,
						tg_notestcheckbox
                            from ci_test_groups 
                            where tg_type='{$btype}'
                            ");
		foreach($rs as $r)
		{
			if(!$r->tg_only_group && !$r->tg_notestcheckbox)continue;

			$groups[$r->tg_ID] = !empty($r->tg_short_name)? $r->tg_short_name : $r->tg_name;
		}


		$where = "";
		$where .= " and ba.batch_created >= '{$date}' ";
		$where .= " and ba.batch_created <= '{$date} 23:59:59' ";


		$where .= " and ba.patient_id >0  ";

		if($this->options['lab_id']>0)
		{
			$where.= " and ba.lab_id = {$this->options['lab_id']} ";
		}



		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);


		$sql = "SELECT 
                    ba.mid,
                    ba.lab,
                    ba.doc,
                    ba.spec_received,
                    ba.assen,
                    ba.patient_name,
                    ba.requesting_practice,
                    ba.requesting_physician,
                    ba.pverify_status,
                    ba.claim_id,
                    ba.result_pdf,
                    ba.insurance,
                    ba.paid,
                    ba.billing_notes,
                    ba.priority_hours,
                    ba.panel_tests_requested,
                    ba.dagnostic_codes1 as dagnostic_codes,
                    ba.type_of_specimen,
                    ba.fasting,
                    
                 
                    cli.cID,
                    cli.cli_Telcor_ID,
                    
                    doc.doc_ID,
                    doc.doc_npi,
                    doc.doc_pecos,
                    doc.doc_first,
                    doc.doc_last,
                    
                    pa.*
                    
                  
                from {$batch_tbl} ba
                join ci_patients pa ON pa.pa_ID = ba.patient_id
                left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                left join ci_clinic cli ON cli.cID = ba.cli_ID
                where 1=1 {$where} 
                group by ba.mid 
                order by ba.mid desc";


		$rows = $wpdb->get_results($sql);


		if(!empty($wpdb->last_error))
		{
			$this->log($wpdb->last_error);
			echo $wpdb->last_error."\n";
		}else{
			//var_dump($rows);
			$this->log( 'Count '.$date.' '.$btype.': '.count($rows)."\n");
		}


		$filename = $date.($btype!='blood'? '_'.$btype:'').'.csv';
		$local_file = $this->order_dir.'/'.$filename;

		$file = fopen($local_file,'w');

		if(isset($_REQUEST['testcron'])){
			echo 'file: '.$filename.'<br>';
		}

		$headers = array(
			'assen'         => 'Accession',
			'doc'           => 'date_of_collection',
			'spec_received' => 'spec_received',
			'requesting_practice'=>'clinic',
			'requesting_physician' => 'doctor',
			'doc_npi' => 'doctor npi',
			'dagnostic_codes' => 'primary_dagnostic_code',
			'type_of_specimen' => 'type_of_specimen',
			'fasting' => 'fasting',
			'panel_tests_requested' => 'panel_tests_requested',
			'notes' => 'notes',
			'billing_notes' => 'billing_notes',
			'pa_first' => 'pa_first',
			'pa_last' => 'pa_last',
			'pa_middle' => 'pa_middle',
			'pa_dob' => 'pa_dob',
			'pa_phone' => 'pa_phone',
			'pa_gender' => 'pa_gender',
			'pa_address' => 'pa_address',
			'pa_city' => 'pa_city',
			'pa_state' => 'pa_state',
			'pa_zip' => 'pa_zip',
			'pa_ssn' => 'pa_ssn',
			'pa_mrn' => 'pa_mrn',
		);
		fputcsv($file,$headers);

		$this->mids = array();
		foreach($rows as $row)
		{
			$this->mids[] = $row->mid;

			$panel = '';
			if(in_array($btype, array('pcr','blood'))){
				$testids = explode(',',$row->panel_tests_requested);
				$panel = array();
				$groupids = array();
				foreach($testids as $testid)
				{
					$testid = trim($testid);
					$testid = str_replace('+','',$testid);
					if(stripos($testid,'group_')!==false)
					{
						$group_id = str_ireplace('group_','',$testid);
						if(!in_array($group_id,$groupids) && isset($groups[$group_id]))
						{
							$panel[] = $groups[$group_id];
							$groupids[] = $group_id;
						}

					}
					else if(isset($all_tests[$testid])){
						$group_id = $all_tests[$testid]->te_group_id;
						if($group_id>0 && !in_array($group_id,$groupids) && isset($groups[$group_id]))
						{
							$panel[] = $groups[$group_id];
							$groupids[] = $group_id;
						}
						$test_drug = !empty($all_tests[$testid]->te_abrev)? $all_tests[$testid]->te_abrev: $all_tests[$testid]->te_name;
						$panel[] = $test_drug;

					}
				}
				$row->panel_tests_requested = implode(', ',$panel);
			}
			else if(strpos($row->panel_tests_requested,'+')===false){
				$row->panel_tests_requested = trim(ucfirst($row->custom_profile).' +'.$row->panel_tests_requested,'+');
			}


			if(strtoupper($row->pa_address)=='N/A' || $row->pa_address == '0'){
				$row->pa_address = '';
			}
			if(strtoupper($row->pa_city)=='N/A' || $row->pa_city=='0'){
				$row->pa_city = '';
			}
			if(strtoupper($row->pa_state)=='N/A' || $row->pa_state == '0'){
				$row->pa_state = '';
			}
			if(strtoupper($row->pa_zip)=='N/A' || $row->pa_zip == '0'){
				$row->pa_zip = '';
			}
			if($row->pa_phone=='000-00-0000' || $row->pa_phone=='0000-0000' || $row->pa_phone=='0'){
				$row->pa_phone = '';
			}

			//fix date
			$row->pa_dob        = !empty($row->pa_dob) && $row->pa_dob!='0000-00-00' ? date('Ymd',strtotime($row->pa_dob)):'';

			if(empty($row->spec_received))
			{
				$row->spec_received = date('Y-m-d 23:59:00', strtotime($row->doc));
			}
			$rowdata = array();
			foreach($headers as $hk => $ht)
			{
				$rowdata[] = $row->{$hk};
			}


			fputcsv($file,$rowdata);
		}
		fclose($file);

		return $local_file;
	}

	public function sync_reqs()
	{
		global $wpdb;

		$this->current_date = date_i18n('Y-m-d');
		$hour = date_i18n('H');
		if($hour<=17)
		{
			$this->current_date = date('Y-m-d', strtotime($this->current_date)-86400);
		}
		if(isset($_REQUEST['date'])){
			$this->current_date = $_REQUEST['date'];

			$this->current_date = date('Y-m-d', strtotime($this->current_date)-86400*3);
		}

		$btypes = array('blood');

		$files = array();
		$count = 0;
		foreach($btypes as $btype)
		{
			$this->mids = array();

			$this->build_csv_req($this->current_date,$btype);
			$count += count($this->mids);
		}

		return $count;
	}

	public function sync_reports()
	{
		global $wpdb;

		$btype = 'blood';
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		if($this->debug )
		{
			//$this->log(" list file:");
		}

		$files = scandir($this->result_dir);
		$count_files = 0;
		$need_add_fax = false;

		foreach($files as $filename)
		{
			$file_path = $this->result_dir.'/'.$filename;
			if(stripos($filename,'.pdf')>0)
			{
				$filesize = filesize($file_path);
				$filetime = filemtime($file_path);

				if($filesize > 10)
				{
					$assen = str_ireplace('.pdf','',$filename);
					$is_reject = false;
					if(stripos($assen,'-r')>0)
					{
						$is_reject = true;
						list($assen) = explode('-',$assen);
					}else if(strpos($assen,'-')>0)
					{
						list($assen) = explode('-',$assen);
					}

					$bat = $wpdb->get_row("select mid,
											batch_id,
											doc,
											batch_status,
											result_pdf
											from {$batch_tbl} 
											where assen = '{$assen}' ");

					$report = $wpdb->get_row("select id,
											estatus,
											approve_date,
											upload_files
											from {$report_tbl} 
											where patient_id = '{$assen}' ");

					if($bat && $bat->mid > 0)
					{
						$mid = $bat->mid;
						$filename = strtolower($filename);

						$this->log('assen: '.$assen.' # '.$mid.' => '.$filename);

						if(!$report)
						{
							$rsdata = array(
								'patient_id' => $assen,
								'estatus' => $is_reject? 'M':'C',
								'author_id' => 0,
								'instrument' => $this->api_name,
								'created_date' => date_i18n('Y-m-d'),
								'created' => date_i18n('Y-m-d H:i:s'),
								'upload_files' => $filename,
							);
							if(!$is_reject)
							{
								$rsdata['approve_date'] = date_i18n('Y-m-d H:i:s');
							}
							$wpdb->insert($report_tbl,$rsdata);
							$report_id = $wpdb->insert_id;
						}else{
							$report_id = $report->id;

							$update_report = array();

							$upload_files = array();
							if(!empty($report->upload_files))
							{
								$upload_files = explode(',',$report->upload_files);
							}
							if(!in_array($filename,$upload_files))
							{
								$upload_files[] = $filename;

								$update_report['upload_files'] = implode(',',$upload_files);
							}

							if(($report->estatus!='C' || empty($report->approve_date)) && !$is_reject){
								$update_report['estatus'] = 'C';
								$update_report['approve_date'] = date_i18n('Y-m-d H:i:s');
								$need_add_fax = true;
							}

							if(count($update_report)>0)
							{
								$wpdb->update($report_tbl,$update_report,array('id'=>$report_id));
							}

						}

						if($report_id>0)
						{
							$newfilename = $filename;
							$result_path = WP_CONTENT_DIR.'/results/uploads/'.$newfilename;
							$ii = 0;
							while(file_exists($result_path))
							{
								//unlink($result_path);
								$ii++;
								$newfilename = str_ireplace('.pdf','-'.$ii.'.pdf',$filename);
								$result_path = WP_CONTENT_DIR.'/results/uploads/'.$newfilename;
								
							}
							if(rename($file_path,$result_path))
							{
								$update_bat = array();
								$result_pdfs = array();
								if(!empty($bat->result_pdf))
								{
									$result_pdfs = explode(',',$bat->result_pdf);
								}
								if(!in_array($newfilename,$result_pdfs))
								{
									$result_pdfs[] = $newfilename;

									usort($result_pdfs,function($a,$b){
										if(strpos($a,'-')===false)
										{
											return false;
										}else if(strpos($b,'-')===false)
										{
											return true;
										}
										return strcmp($a,$b);
									});

									$update_bat['result_pdf'] = implode(',',$result_pdfs);
								}
								if(empty($bat->batch_id))
								{
									$update_bat['batch_id'] = date('Ymd',strtotime($bat->doc));
								}
								if(count($update_bat)>0)
								{
									$wpdb->update($batch_tbl,$update_bat,array('mid'=>$bat->mid));
								}

								$moobj = $wpdb->get_row("select mo_ID,mo_peg_ID 
												from ci_sync_api_orders
													where mo_file_id={$this->api_id}
													and mo_mid = {$mid}
													and mo_btype= 'blood'
													and mo_control_ID = '{$newfilename}'");

								if($moobj && $moobj->mo_ID > 0)
								{
									$wpdb->update('ci_sync_api_orders',array(
										'mo_peg_ID' => $moobj->mo_peg_ID+1,
										'mo_test_ID'    => date_i18n('Y-m-d H:i:s'),
									),array('mo_ID'=>$moobj->mo_ID));
								}
								else{
									$wpdb->insert('ci_sync_api_orders',array(
										'mo_file_id' => $this->api_id,
										'mo_mid'        => $mid,
										'mo_btype'      => 'blood',
										'mo_control_ID' => $newfilename,
										'mo_test_ID'    => date_i18n('Y-m-d H:i:s'),
									));
								}

							}
						}


						$count_files++;
					}
					else{
						$this->log('assen: '.$assen.' not found '.$filename);
						$filetime = filemtime($file_path);
						touch($file_path,$filetime-86400);
						if($filetime > 1000 && $filetime < time() - 86400*15)
						{
							unlink($file_path);
						}
					}

				}

			}
		}

		if($need_add_fax)
		{
			$nextcron = wp_next_scheduled('ci_auto_fax_results');
			if($nextcron==0 || $nextcron > time()+300)
			{
				wp_schedule_single_event(time()+100, 'ci_auto_fax_results',array('single'));
			}
		}

		if($this->debug )
		{
			$this->log( "count: ".$count_files);
		}

	}




}