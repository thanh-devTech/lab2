<?php
global $wpdb;
$table = 'ci_excel_billing'.$wpdb->prefix;
if(!session_id())
{
	session_start();
}
require TRCPDD_PATH. 'inc/PHPExcel/IOFactory.php';


global $current_role,$current_lab_id;
if(!$current_lab_id)$current_lab_id = 0;
$user_id = get_current_user_id();
$created = time();

$wpdb->suppress_errors(true);

$date_cols = array(
	'servicedate' => 'servdate',
	'claimdate' => 'claim_date',
	'date' => 'date'
);
$use_cols = array(
	'referenceid' => 'assen',
	'specimennumber' => 'assen',
	'claimno' => 'claim_no',
	'claimnumber' => 'claim_no',
	'patientname' => 'patient',
	'patientname(last,first)' => 'patient',
	'patientfirstname' => 'patient_first',
	'patientlastname' => 'patient_last',

	'charges'       => 'billed_amount',
	
	'chargeamount'       => 'billed_amount',
	'paidamount'       => 'received_amount',


	//'currentbalance' => 'received_amount',
	'paymentpaid' => 'received_amount',
	'payments' => 'received_amount',

	'insurancepayment' => 'insurance_payment',
	'insurancebalance' => 'insurance_payment',
	'insurancepayments' => 'insurance_payment2',

	'allowedamount' => 'allowed_amount',
	'adjustments' => 'allowed_amount',
	'patientbalance' => 'patient_balance',
	'status'		=> 'bill_status'
);
$save_cols = array_merge($date_cols,$use_cols);

?>
<div class="wrap-log">
    <p><em>Processing...</em></p>
    <ul class="report-log">
		<?php
		if($step == 2){

			$files = implode(',',$_SESSION['billing_import_files']);
			$files = $wpdb->get_results("select * from ci_autoimportqc where id in ({$files})", ARRAY_A);

			$break_data = false;
			$start_time = time();
			$limit_time = $start_time + 60;

			foreach($files as $file_id => $row_dt)
			{
				if($break_data)
				{
					break;
				}

				$datameta = @json_decode($row_dt['data'],true);
				$only_update_claim_no = @$datameta['only_update_claim_no'];

				$file = $row_dt['filename'];
				$start_row = $row_dt['imported'];
				$count_file_row = intval($row_dt['total_rows']);
				$count_run_per = intval($row_dt['per_run']);

				$filename = basename($file);

				$has_header = false;
				$headers = array();
				$row_data = array();
				$count_row_get = 0;
				$count_row = 0;

				if($count_file_row > 0 && $start_row >= $count_file_row)
				{
					continue;
				}

				if(isset($_REQUEST['debug'])){
					echo 'Run per: '.$count_run_per,'<br>';
					echo 'start at: '.$start_row,'<br>';
				}



				echo ("<li>Gathering Text file data...".$filename.'</li>');

				$start_time = time();

				if(stripos($file,'.xls')!==false || stripos($file,'.csv')!==false)
				{
					$filetype = PHPExcel_IOFactory::identify($file);

					$objReader = PHPExcel_IOFactory::createReader($filetype);

					if (stripos($file, '.csv') > 0) {
						//check semicolon
						$datafile           = file_get_contents($file);
						$count_semicolon    = substr_count($datafile, ';');
						$count_comma        = substr_count($datafile, ',');
						$datafile           = '';
		
						if ($count_semicolon > $count_comma) {
							$objReader->setDelimiter(';');
						}
					}

					$objReader->setReadDataOnly( true );
					$objPHPExcel = $objReader->load($file);

					$sheet = $objPHPExcel->getSheet(0);
					$highestRow = $sheet->getHighestRow();
					$highestColumn = $sheet->getHighestColumn();


					$count_file_row = $highestRow;
					$row = 0;
					for($row = 1; $row <= $highestRow; $row++) {


						if($row == 1 || !$has_header) {
							$rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
							$headers = array_map('trim',$rowdt[0]);
							if(!empty($headers[0]) && !empty($headers[1])){
								$has_header = true;
							}
						}else if($row<$start_row)
						{
							$row = $start_row;
							continue;
						}
						else if($count_row_get >$count_run_per && $count_run_per>10)
						{
							$break_data = true;
							break;
						}
						else {

							$rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
							$count_row_get++;
							if($count_row_get>10000)
							{
								$break_data = true;
								break;
							}
							$row_data[$row] = $rowdt[0];
						}
					}


				}
				else{
					$dt = file_get_contents($file);
					$rows = explode("\n",$dt);
					foreach($rows as $row => $rowdt)
					{
						$rowdt = trim($rowdt);
						if(empty($rowdt))continue;
						$rowdt = str_replace(array("\t\"","\"\t"),"\t",$rowdt);
						$rowdt = explode("\t",$rowdt);

						if($row==0 || empty($headers))
						{
							$headers = array_map('trim',$rowdt);
						}
						else if($row<$start_row)
						{
							continue;
						}else{
							$count_row_get++;
							if($count_row_get<10000)
							{
								$row_data[$row] = $rowdt;
								break;
							}

						}
					}
					if($count_file_row==0)
					{
						$count_file_row = $row;
					}
				}

				$parse_row_time = time() - $start_time;

				//repair headers
                $has_main_key = false;
				foreach($headers as $pos =>$vl)
				{
					$vl = trim($vl);
					$vl = preg_replace("/[^a-zA-Z0-9]/",'',$vl);
					$vl = preg_replace('/\([^\)]*\)/','',$vl);
					$vl = trim($vl);
					$vl = strtolower($vl);
					$headers[$pos] = $vl;

					if($save_cols[$vl]=='assen' || $save_cols[$vl]=='claim_no'){
					    $has_main_key = true;
                    }
				}

				//check headers
				if(count($row_data)==0 || count($headers)==0 || !$has_main_key)
				{
					tr_pdd_activity("Format Error!",true);
					var_dump($headers);
					continue;
				}


				$count_run = 0;

				if(isset($_REQUEST['debug']))
				{
					echo 'Time: '.$parse_row_time,'<br>';
				}

				foreach($row_data as $rowid => $row)
				{

					if(time() > $limit_time && $limit_time>0)
					{
						$break_data = true;
						break;
					}

					$files[$file_id]['imported'] = $rowid;

					$insert = array();
					$count_run++;

					foreach($row as $c => $r)
					{
						$col_name = $headers[$c];
						//  var_dump($col_name);echo '<br>';

						if(isset($save_cols[$col_name]))
						{
							$r = preg_replace("/[^a-zA-Z0-9\s\.\,\-\_\/]/",'',$r);

							if(isset($date_cols[$col_name]))
							{
								if(preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})/",$r,$matches))
								{
									$r = (strlen($matches[3])==2? '20':'').$matches[3].'-'.$matches[1].'-'.$matches[2];
								}else{
									$t = PHPExcel_Shared_Date::ExcelToPHP($r);
									if($t>0)
									{
										$r = gmdate('Y-m-d',PHPExcel_Shared_Date::ExcelToPHP($r));
									}
								}
							}


							$key = $save_cols[$col_name];

							$insert[$key] = trim($r.'');//fix null
						}
					}
					if(empty($insert['patient']) && !empty($insert['patient_first']))
					{
						$insert['patient'] = $insert['patient_last'].', '.$insert['patient_first'];
					}

					if(empty($insert['insurance_payment'])){
					    $insert['insurance_payment'] = $insert['insurance_payment2'];
                    }
                    if(empty($insert['insurance_payment']))
                    {
                        unset($insert['insurance_payment']);
                    }


					unset($insert['patient_first']);
					unset($insert['patient_last']);
                    unset($insert['insurance_payment2']);

                    if(isset($insert['billed_amount'])){
						if(stripos($insert['billed_amount'],'US')!==false)
						{
							$insert['billed_amount'] = str_replace('US','',$insert['billed_amount']);
							$insert['billed_amount'] = str_replace(',','.',$insert['billed_amount']);
						}
                        $insert['billed_amount'] = str_replace(',','',$insert['billed_amount']);
                    }
					if(isset($insert['received_amount'])){
						if(stripos($insert['received_amount'],'US')!==false)
						{
							$insert['received_amount'] = str_replace('US','',$insert['received_amount']);
							$insert['received_amount'] = str_replace(',','.',$insert['received_amount']);
						}
						$insert['received_amount'] = str_replace(',','',$insert['received_amount']);
					}
					if(isset($insert['insurance_payment'])){
						$insert['insurance_payment'] = str_replace(',','',$insert['insurance_payment']);
					}

					if(isset($insert['allowed_amount'])){
						$insert['allowed_amount'] = str_replace(',','',$insert['allowed_amount']);
					}

					if(isset($insert['patient_balance'])){
						$insert['patient_balance'] = str_replace(',','',$insert['patient_balance']);
					}

					if(empty($insert['claim_no']) && empty($insert['assen']))
					{
						continue;
					}
					if(isset($_GET['debug']))
					{
						var_dump($headers);echo '<br><br>';
						var_dump($row);echo '<br><br>';
						var_dump($insert);exit;
					}
					$count_row ++;

					$where_exist = " claim_no = '{$insert['claim_no']}' ";
					if(!empty($insert['assen'])){
						$where_exist = " assen = '{$insert['assen']}' ";
                    }


					$exist_id = $wpdb->get_var("select id from ci_batch_billings where {$where_exist}");


					if(isset($only_update_claim_no) && $only_update_claim_no===true)
					{
					    if($exist_id)
                        {
	                        $update1 = array(
		                        'claim_date' => $insert['claim_date'],
		                        'servdate' => $insert['servdate'],
		                        'date' => $insert['date']
	                        );
	                        $wpdb->update('ci_batch_billings',$update1,array('id'=>$exist_id));
	                        $count_row_updated++;
                        }

						continue;
					}

					if($exist_id>0)
                    {
	                    if(isset($_GET['noupdate'])){
		                    continue;
	                    }
	                    if(empty($insert['claim_no'])){
	                        unset($insert['claim_no']);
                        }
	                    if(empty($insert['assen'])){
		                    unset($insert['assen']);
	                    }
	                    $rs = $wpdb->update('ci_batch_billings',$insert,array('id'=>$exist_id));
                    }else{


						if($insert['received_amount']>0 || $insert['billed_amount']>0)
						{
							$insert['bill_status'] = 'Billed';
						}

						$rs = $wpdb->insert('ci_batch_billings',$insert);
                    }

					if($rs===false)
					{
						tr_pdd_activity("Error: ".$wpdb->last_error,true);
					}else{
						$count_row_inserted ++;
					}

				}

				if($count_file_row > 0)
				{
					$files[$file_id]['total_rows'] = $count_file_row;
				}

				if($count_run_per<10 && $count_run>10)
				{
					$count_run_per = $count_run;
					$files[$file_id]['per_run'] = $count_run_per;
				}


				$file_update = $files[$file_id];
				$file_update['end_time'] = date('Y-m-d H:i:s');
				unset($file_update['id']);
				unset($file_update['data']);

				//update file
				$wpdb->update('ci_autoimportqc',$file_update,array('id'=>$row_dt['id']));

			}



			$runned = 0;
			$count_total = 0;
			foreach($files as $row_dt)
			{
				$runned+= $row_dt['imported'];
				$count_total+= $row_dt['total_rows'];
			}

			if($break_data || $count_total > $runned)
			{
				?>
                <li><br></li>
                <li>Total: <?php echo $count_total?></li>
                <li title="<?php echo $count_run_per?>">Left: <?php echo $count_total - $runned?></li>
                <li title="p: <?php echo $parse_row_time?>">Importing...Don't close this page!</li>
                <meta http-equiv="refresh" content="1; url=<?php Tr_Base_Class_V4::link(array('step'=>'2'))?>">
				<?php
			}else{
				?>
                <li>Total: <?php echo $count_total?></li>
                <li>Done.</li>
				<?php
			}

		}
		else{

			$save_folder = WP_CONTENT_DIR.'/log/billing';
			if(!is_dir($save_folder))
			{
				wp_mkdir_p($save_folder);
			}
			$files = array();
			if(!is_array($_FILES['files']['tmp_name'])) {
				$files[] = $_FILES['files'];
			} else {
				$count = 0;
				foreach($_FILES['files']['tmp_name'] as $t) {
					foreach($_FILES['files'] as $key => $f) {
						$new_file[$key] = $f[$count];
					}
					$files[] = $new_file;
					$count++;
				}
			}

			if(isset($_GET['logfile'])){
				$files[] = $save_folder.'/'.$_GET['logfile'];
			}

			$_SESSION['billing_import_files'] = array();
			foreach($files as $f)
			{
				if(empty($f))continue;

				$row = array();
				$row['need_update_batch'] = true;
				$row['only_update_claim_no'] = false;

				if(!is_array($f))
				{
					$filename = basename($f);
					$file = $f;
					$row['need_update_batch'] = false;
					$row['only_update_claim_no'] = true;

				}else{
					$filename = $f['name'];
					$file = $save_folder.'/'. $filename;


					if(move_uploaded_file($f['tmp_name'], $file)) {
						tr_pdd_activity("File uploading: <em>". $f['name'] ."</em>");
					} else
					{
						tr_pdd_activity("Error when upload: <em>". $f['name'] ."</em>",true);
						continue;
					}
				}

				$data = array(
					'filename' => $file,
					'ctype'    => 'billing',
					'data'     => @json_encode($row),
					'total_rows' => 0,
					'per_run' => 0,
					'imported'=> 0,
					'start_time' => date('Y-m-d H:i:s')
				);

				$wpdb->insert('ci_autoimportqc',$data);
				$_SESSION['billing_import_files'][] = $wpdb->insert_id;
			}

			//redirect
			if(count($_SESSION['billing_import_files'])>0)
			{
				echo '<meta http-equiv="refresh" content="1; url='.Tr_Base_Class_V4::link(array('step'=>'2'),false).'">';
			}
		}

		?>

    </ul>
</div>