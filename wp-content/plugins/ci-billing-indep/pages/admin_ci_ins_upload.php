<?php
global $wpdb;
$log_dir = WP_CONTENT_DIR.'/log/ins_upload/';
if(!is_dir($log_dir))
{
	wp_mkdir_p($log_dir);
}
$user_id = get_current_user_id();

if(isset($_REQUEST['add_logs'])){
    $files = scandir($log_dir);
    foreach($files as $file)
    {
        if(!in_array($file,array('.','..'))){

        }
    }
}

if(!empty($_FILES['import_file']['name'])) {
	$filename = $_FILES['import_file']['name'];
	$filetmp  = $_FILES['import_file']['tmp_name'];
	$info     = pathinfo( $filename );
	$ext      = $info['extension'];

	if ( in_array( $ext, array( 'csv', 'xls', 'xlsx' ) ) ) {
		$filepath = $log_dir . $filename;
		if ( move_uploaded_file( $filetmp, $filepath ) ) {
			$data = trpdd_get_data( $filepath, $ext, array(
				array( 'insurance Name','insurance company','insurance' ),
				array('peg id','ref. accession')
			), true );


			if ( empty( $data['header'] ) ) {
				if ( isset( $data['missing'] ) ) {
					$missing = array_map( 'ucwords', $data['missing'] );
					$missing = implode( ', ', $missing );
					Tr_Session_Class::add_error( "Required Columns: " . $missing );
				} else {
					Tr_Session_Class::add_error( "Required Columns: Insurance Name" );
				}

			} else {
				$total = 0;
				$updated = 0;
				$not_exist = 0;
				$req_not_exist = array();
				//import here
				foreach($data['data'] as $row)
				{
					$oldreq = trpdd_get_string_exist($row,array('peg id','ref. accession'));
					$insurance_name = trpdd_get_string_exist($row,array( 'insurance name','insurance company','insurance' ));

					if(empty($oldreq) || empty($insurance_name))continue;
					$total ++;

					$insurance_id = trpdd_get_string_exist($row,array( 'insurance id' ));
					$relationship = trpdd_get_string_exist($row,array( 'relationship to insured' ));
					$holder_name = trpdd_get_string_exist($row,array( 'name of policy holder (if not self)' ,'name of policy holder'));
					$patient_name = trpdd_get_string_exist($row,array( 'name','patient name'));

					if(stripos($relationship,'parent')!==false)
					{
						$relationship = 'Parent';
					}
					else if(stripos($relationship,'spouse')!==false || stripos($relationship,'spuse')!==false)
					{
						$relationship = 'Spouse';
					}else{
						$relationship = 'Self';
					}
					//find
					$exist_b = false;
					foreach(trbilling_req_types() as $btype)
					{
						if($btype=='study')continue;

						$batch_tbl = tr_pdd_get_table_by_btype($btype);
						$batch = $wpdb->get_row("select mid,
												insurance1,
												patient_name
						                        from {$batch_tbl} where oldreq='{$oldreq}'");
						if(!$batch)
                        {
	                        $batch = $wpdb->get_row("select mid,
												insurance1,
												patient_name
						                        from {$batch_tbl} where notes='{$oldreq}'");
                        }

						if($batch)
						{
							if(empty($patient_name))
							{
								$patient_name = $batch->patient_name;
							}
							if($relationship=='Self'){
								list($ins_last, $ins_first) = explode(',', $patient_name);
							}else{
								list($ins_last, $ins_first) = explode(',', $holder_name);
							}
							$ins_last = trim($ins_last);
							$ins_first = trim($ins_first);


							$ins_id = $batch->insurance1;
							$ins_data = array(
								'ins_first' => $ins_first,
								'ins_last' => $ins_last,
								'ins_relation' => $relationship,
								'ins_company' => $insurance_name,
								'ins_member_id' => $insurance_id,
							);
							if($ins_id==0)
							{
								$ins_data['ins_created'] = date_i18n('Y-m-d H:i:s');
								$ins_data['ins_created_by'] = $user_id;
								$wpdb->insert('ci_insurances',$ins_data);
							}else{
								$ins_data['ins_modified'] = date_i18n('Y-m-d H:i:s');
								$wpdb->update('ci_insurances',$ins_data,array('ins_ID'=>$ins_id));
							}
							$wpdb->update($batch_tbl,array('insurance'=>$insurance_name),array('mid'=>$batch->mid));

							//add log
							$wpdb->insert('ci_billing_ins_upload_log',array(
								'mid' => $batch->mid,
								'btype' => $btype,
								'created' => date_i18n('Y-m-d H:i:s'),
								'createdby' => $user_id
							));
							$updated ++;
							$exist_b = true;
							break;
						}
					}

					if(!$exist_b)
					{
						$not_exist++;
						$req_not_exist[] = $oldreq;
					}
				}


				//done
				Tr_Session_Class::add("Uploaded file Successfully!");
				Tr_Session_Class::add("Total Row: {$total}");
				Tr_Session_Class::add("Updated Row: {$updated}");
				if($not_exist>0)
				{
					$req_not_exist = implode(', ',$req_not_exist);
					Tr_Session_Class::add_error("Not Exist Row: {$not_exist}");
					Tr_Session_Class::add_error(" {$req_not_exist}");
				}



			}
		}else{
			Tr_Session_Class::add_error("Error while upload file!");
		}
	}
	else{
		Tr_Session_Class::add_error("Not allow this File Extension!");
	}
}

?>
<div class="wrap">
    <h2 class="center">Insurance Upload</h2>
	<?php Tr_Session_Class::show()?>
    <div class="form-wrap center">
        <form enctype="multipart/form-data" method="post">
            <h3>Upload File Data</h3>
            <input type="file" id="upload-files" name="import_file"
                   accept="text/csv,text/txt, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
            <input type="submit" value="Upload" class="button-primary"/>

        </form>
    </div>
</div>