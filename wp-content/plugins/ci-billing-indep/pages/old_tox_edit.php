<?php
global $wpdb;
global $current_role;
global $current_lab_id;
$time_start  = microtime(true);

$user_id = get_current_user_id();


$act = isset($_REQUEST['act'])? $_REQUEST['act']: @$act;
$btype = $_REQUEST['btype']? $_REQUEST['btype'] : 'tox';

if($btype!='tox'){
	return;
}


$current_medications = tr_pdd_get_current_medications();
$Medications = tr_pdd_get_medications();



$cli = array();
$collector = array();
$current_lab = array();
$clinics = array(''=>'Please Select');
$docs = array(''=>'Please Select');
$where_clis = " and cli.cli_clinic !='' and cli.cli_clinic !='0' ";
$cli_id = 0;



if($current_role=='lab_rep')
{
	$rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
	$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

	$where_clis .= " and cli.`cli_rep-ID`={$rep_id} ";

}else if($current_role=='collector')
{
	$col_id = get_user_meta(get_current_user_id(),'_collector_id',true);
	$collector = $wpdb->get_row("select * from ci_collector where col_ID ={$col_id}", ARRAY_A);
	$rep_id = $collector['rep_ID'];
	//$where_clis = " and cli.`cli_rep-ID`={$rep_id} ";
	$where_clis.= " and cc.col_ID = {$col_id} ";
}
else if($current_role == 'distributor'){
	$dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

	$arr_marketing_groups=$wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
	$g_ids = array(-1);
	foreach($arr_marketing_groups as $g)
	{
		$g_ids[] = $g->gr_ID;
	}
	$g_ids = implode(',',$g_ids);

	$where_rep.= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

	$where_clis.=" and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
	$where_group.= " and gr_distributor_ID = {$dis_id} ";
}





$action_form = 'ci_save_form_requisition';
$id = $_REQUEST['id'];
$batch_table = 'ci_batches';
$page_title= 'THERAPEUTIC DRUG TEST REQUISITION FORM';
$btype = isset($_REQUEST['btype'])? $_REQUEST['btype']:'tox';
if($btype=='blood'){
	$batch_table = 'ci_batches_blood';
	$action_form = 'ci_save_blood';
}else if($btype=='dna'){
	$batch_table = 'ci_batches_dna';
	$action_form = 'ci_save_dna';
}else if($btype=='rpp_gi'){
	$batch_table = 'ci_batches_rpp_gi';
	$action_form = 'ci_save_form_rpp_gi';
	$page_title = 'RPP or GI Pathogen Testing Requisition Form';
}
if($act=='edit'){

	$mid = $id;
}else{
	$action_form = '';
}

$bat = $wpdb->get_row("select * from {$batch_table} where mid='{$id}' ",ARRAY_A);

$where_exist = " assen = '{$bat['assen']}' ";

if(!empty($bat['claim_id']) && tr_billing_get_settings('enable_claim_no')){
	$where_exist .= " or claim_no = '{$bat['claim_id']}' ";
}

$bb = $wpdb->get_row("select * from ci_batch_billings where {$where_exist} ",ARRAY_A);


$bat['btype'] = $btype;
$cli['cID'] = $bat['cli_ID'];
$cli_id = $cli['cID'];
$cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
$pa = $wpdb->get_row("select pa.*,med.med_number from ci_patients pa
                           Left JOIN ci_medical_numbers med ON med.med_pa_ID = pa.pa_ID and med.med_cli_ID = '{$cli_id}'
                           where pa.pa_ID = '{$bat['patient_id']}' ",ARRAY_A);
if(!$pa)
{
	$pa = array();
	list($pa['pa_last'],$pa['pa_first'])= explode(',',@$bat['patient_name']);
	$pa['pa_dob'] = $bat['dob'];

	$pa['pa_first'] = trim($pa['pa_first']);
	$pa['pa_last'] = trim($pa['pa_last']);
}
$ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ",ARRAY_A);
$ins2 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance2']}' ",ARRAY_A);

$collector = $wpdb->get_row("select * from ci_collector where col_ID ={$bat['col_ID']}", ARRAY_A);

$bat['current_medication'] = explode(',',@$bat['current_medication']);
$bat['current_medication_other'] = explode(',',@$bat['current_medication_other']);
if(count($bat['current_medication_other'])==1 && trim($bat['current_medication_other'][0])==''){
	$bat['current_medication_other'] = array();
}
foreach($bat['current_medication'] as $m)
{
	if(!in_array($m,$current_medications) && !empty($m) && $m!='None Listed')
	{
		$bat['current_medication_other'][] = $m;
	}
}
$bat['medication'] = maybe_unserialize(@$bat['medication']);

$bat['current_medication'] = array_map('trim',$bat['current_medication']);
$bat['current_medication_other'] = array_map('trim',$bat['current_medication_other']);

//var_dump(@$bat);
$batch_key = $bat['assen'];

if($bat['lab_id']>0 && !defined('TRPDD_SAME_LAB'))
{
	$where_clis= " and cli.`cli_lab-ID` = {$bat['lab_id']}";
}




if(isset($_POST['doc_id']))
{
	$doc_id = $_POST['doc_id'];
	$doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}");
	$cli_id = $doc->cli_ID;
	$bat['physician_phone'] = $doc->doc_phone;
	$bat['doc_ID'] = $doc_id;
}
else if(isset($_POST['clinic_id']))
{
	$cli_id = $_POST['clinic_id'];
	$cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}


$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();


if($cli_id > 0)
{
	$ds = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID or doc.doc_ID = {$bat['doc_ID']}
                        where cd.cli_ID = {$cli_id} ");

	foreach($ds as $doc)
	{
		$docs[$doc->doc_ID] = $doc->doc_first.' '.$doc->doc_last;
	}
	$current_lab = $wpdb->get_row("select lab.* from ci_labs lab
                                    JOIN ci_clinic as cli ON cli.`cli_lab-ID` = lab.lab_ID
                                    Where cli.cID = {$cli_id}", ARRAY_A);

	if(!$collector)
	{
		$collector = $wpdb->get_row("select col.* from ci_collector col
                            JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                            where cc.cli_ID ={$cli_id} limit 1", ARRAY_A);
	}
}else if($current_lab_id>0){
	$current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$current_lab_id}", ARRAY_A);
}

$sql_clinic = "select cli.* from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID

                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    where 1=1 {$where_clis} group by cli.cID order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);

if(isset($_REQUEST['debug'])){
	var_dump($sql_clinic);
}

$sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            cp.cus_ID,
            rl.lab_additional as rl_lab_additional,
            re.id,
            re.estatus,
            re.patient_name as re_patient_name,
            re.patient_id,
            re.patient_num,
            re.patient_dob,
            re.acquisition_date,
            re.collection_date,
            re.medications,
            re.physician,
            re.sublab,
            re.result_table,
            re.sample_type,
            re.received_in_lab,
            re.validity,
            re.created_date
    FROM ci_reports re

    JOIN {$batch_table} ba ON  ba.assen = re.patient_id

    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID

    LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
    WHERE ba.mid='". $id ."' group by ba.mid limit 1";



$report = $wpdb->get_row($sql, ARRAY_A);

$q_row = microtime(true) - $time_start;

if(isset($_REQUEST['debug'])){
	echo '$q_row: '.$q_row,'<br>';
	echo '$sql: '.$sql,'<br><br><br>';
	var_dump($report);
	echo '<br><br><br>';
}


?>
<div class="wrap_form wrap">

	<?php if($act=='print'):?>
		<input type="button" class="button print_button" onclick="window.print()" value="Print"/>
		<?php
		$upload_dir = tr_wp_upload_dir();
		$logo = $current_lab['lab_logo'];
		$logo_path = $upload_dir['basedir'].'/logos/'.$logo;
		$logo_url = $upload_dir['baseurl'].'/logos/'.$logo;
		?>
		<div style="text-align: center">
			<img src="<?php echo $logo_url?>" align="center" style="max-width:400px"/>
		</div>
	<?php endif;?>

	<form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">
		<h2 ><?php echo $page_title?></h2>
		<?php Tr_Session_Class::show()?>


		<div class="right foutrow " style="padding-right:20px;">
			<label>Accession Number: </label>
			<b><?php echo @$bat['assen']?></b>
		</div>


		<div class="frow blabel hlabel" >
			<div class="col5">
				<?php if($current_lab_id==0){
					$las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba");
					$labs = array(''=>'Select Lab');
					foreach($las as $l)
					{
						$labs[$l->lab_ID] = $l->lab_dba;
					}
					?>
					<label>Lab</label>
					<?php tr_select_option('bat[lab_id]',$labs,$bat['lab_id'],array('attr'=>'required'))?>
					<?php
				}?>
				<label>PRACTICE INFORMATION</label>
				<?php
				if(in_array($current_role,array('lab_tech','lab_sec'))){
					?>
					<input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo @$bat['requesting_practice']?>" required/>
					<input type="hidden" name="bat[cli_ID]" value="<?php echo @$bat['cli_ID']?>">
					<?php
				} else{?>
					<select name="bat[cli_ID]" required id="cli_change">
						<option value="">Select Clinic</option>
						<?php
						foreach($clinics as $clinic)
						{
							$selected = $cli['cID'] == $clinic['cID']? 'selected':'';
							echo '<option value="'.$clinic['cID'].'" '.$selected.'>'.$clinic['cli_clinic'].'</option>';
						}
						?>
					</select>
				<?php }?>
			</div>
			<div class="col5" id="cli_change_div">
				<label>ORDERING PHYSICIAN</label>
				<?php if(in_array($current_role,array('lab_tech','lab_sec'))){
					?>
					<input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo @$bat['requesting_physician']?>" required/>
					<input type="hidden" name="bat[doc_ID]" value="<?php echo @$bat['doc_ID']?>">
					<?php
				}else{
					tr_select_option('bat[doc_ID]',$docs,@$bat['doc_ID'],array('id'=>'doc_change','attr'=>'required'));
				}
				?>
				<label>Collector Name</label>
				<input type="text" class="col_auto" name="col[col_name]" value="<?php echo tr_trim_name(@$collector['col_first'].' '.@$collector['col_last'])?>" />
				<input type="hidden" name="col[col_ID]" value="<?php echo @$collector['col_ID']?>"/>
				<label>Collector Phone Number</label>
				<input type="text" class="has_phone" name="col[col_phone]" value="<?php echo @$collector['col_phone']?>" <?php if(!empty($collector['col_phone']))echo 'readonly'?>/>
			</div>
			<div class="col5">
				<label>DIAGNOSTIC CODES</label>

				<div class="dagnostic_code" data-name="bat[dagnostic_codes][]" <?php if(defined('CI_WORK_DAGNOSTIC_REQUIRED'))echo 'data-required="true"'?>>
					<ul id="list_dagnostic_codes">
						<?php
						$bat['dagnostic_codes'] = explode(',',@$bat['dagnostic_codes1']);
						if(empty($bat['mid']))
						{
							$bat['dagnostic_codes'] = array();
						}
						if(is_array(@$bat['dagnostic_codes']) && count($bat['dagnostic_codes'])>0)
						{
							foreach($bat['dagnostic_codes'] as $vl)
							{
								if(empty($vl))continue;
								?>
								<li><input type="hidden" name="bat[dagnostic_codes][]" value="<?php echo $vl?>"/> <?php echo $vl?> <span></span></li>
								<?php
							}
						}
						?>
					</ul>
					<?php if($act!='print' && $act!='view'):?>
						<div class="dagnostic_edit">
							<?php tr_select_option('dagnostic_code_select',tr_pdd_get_dagnostic_codes($btype),null)?>
							<a class="itip thickbox" href="<?php echo TRCPDD_URL?>images/ICD10Crosswalk.png?"></a>
							<input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes"/>
						</div>
					<?php endif;?>
				</div>

			</div>
			<div class="col25">

				<label>SPECIMEN INFORMATION</label>
				<div class="frow">
					<div class="col2 blabel ">
						<label>Type of Specimen:</label>
						<div><?php tr_radio('bat[type_of_specimen]',tr_pdd_get_type_of_specimens($btype),@$bat['type_of_specimen'],array('attr'=>'required'))?></div>
					</div>
					<div class="col2 small">
						<label>Date of Collection</label>
						<input type="text"  class="hasdatetime today" <?php if(in_array($current_role,array('administrator','lab','labsecman','lab_manager','lab_sec','lab_tech')))echo 'data-write="true"'?> name="bat[doc]" value="<?php echo @$bat['doc']?>" <?php if(!empty($bat['doc']))echo 'readonly'?> required=""/>

					</div>
				</div>
			</div>
		</div>

		<div class="frow">
			<h4>PATIENT INFORMATION</h4>
		</div>

		<div class="frow blabel">
			<div class="col4">
				<label>Social Security Number</label>
				<input type="text" name="pa[pa_ssn]" id="pa_ssn" value="<?php echo @$pa['pa_ssn']?>" <?php if($act!='print')echo 'style="width:60%"'?> />
				<input type="hidden" name="pa[pa_ID]" value="<?php echo @$pa['pa_ID']?>"/>

				<?php if($act!='print' && $act!='view'):?>
					<a class="button find_patient" href="#" data-type="ssn" >find</a>
				<?php endif;?>
			</div>
			<div class="col4">
				<label>Last Name</label>
				<input type="text" class="cfname requesting_physician_last_auto" name="pa[pa_last]" value="<?php echo @$pa['pa_last']?>" required=""/>
			</div>
			<div class="col4">
				<label>First Name</label>
				<input type="text" class="cfname" name="pa[pa_first]" value="<?php echo @$pa['pa_first']?>" required=""/>
			</div>
			<div class="col4">
				<label>Middle Initial</label>
				<input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo @$pa['pa_middle']?>"/>
			</div>
		</div>

		<div class="frow blabel">
			<div class="col4">
				<label>Medical Record Number</label>
				<input type="text" id="med_number" name="pa[med_number]" value="<?php echo @$pa['med_number']?>" maxlength="15" <?php if($act!='print')echo 'style="width:60%"'?> />
				<?php if($act!='print' && $act!='view'):?>
					<a class="button find_patient" href="#" data-type="med_number" >find</a>
				<?php endif;?>
			</div>
			<div class="col4">
				<label>Street Address</label>
				<input type="text" name="pa[pa_address]" value="<?php echo @$pa['pa_address']?>" required=""/>
			</div>
			<div class="col4">
				<label>City</label>
				<input type="text" name="pa[pa_city]" value="<?php echo @$pa['pa_city']?>" required=""/>
			</div>
			<div class="col4">
				<div class="frow blabel">
					<div class="col2">
						<label>State</label>
						<?php tr_select_option('pa[pa_state]',tr_get_states(),@$pa['pa_state'],array('attr'=>'required'))?>
					</div>
					<div class="col2">
						<label>Zip</label>
						<input type="text" name="pa[pa_zip]" value="<?php echo @$pa['pa_zip']?>" required=""/>
					</div>
				</div>
			</div>
		</div>
		<div class="frow">
			<div class="col4 label2d">
				<label>Gender</label><br>
				<?php tr_select_option('pa[pa_gender]',array(''=>'Select','F'=>'Female','M'=>'Male'),@$pa['pa_gender'],array('attr'=>'required="" '))?>
			</div>
			<div class="col4">
				<label>DOB</label>
				<input type="text" class="hasdate birthday" name="pa[pa_dob]" value="<?php echo @$pa['pa_dob']?>" required=""/>
			</div>
			<div class="col4">
				<label>Phone Number</label>
				<input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo @$pa['pa_phone']?>" required=""/>
			</div>

		</div>

		<?php
		if($btype=='rpp_gi')
		{
			?>
			<div class="pdf_table">
				<div class="frow">
					<div class="col23"><h4>BILLING INFORMATION</h4></div>
					<div class="col13"><h4>PATIENT’S CLINICAL HISTORY</h4></div>
				</div>
			</div>
			<?php
		}else{
			?>
			<div class="frow">
				<div class="col23"><h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins',@$bat['ins_comp'])?>/> PRIMARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></label></h4></div>
				<div class="col13"><h4><label><input type="radio" name="bat[ins_comp]" value="comp" <?php checked('comp',@$bat['ins_comp'])?>/> WORKER'S COMP AND AUTO INFORMATION</label></h4></div>
			</div>
			<?php
		}
		?>


		<div class="frow blabel">
			<div class="col23" id="use_required_ins">

				<div class="frow" >
					<input type="hidden" name="no_insurance" value=""/>
					<label><input type="checkbox" name="no_insurance" value="No Insurance - Cash" <?php checked(@$bat['insurance'],'No Insurance - Cash')?>/> No Insurance - Cash</label>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Last Name</label>
						<input type="text" class="cfname has_required" name="ins1[ins_last]" value="<?php echo @$ins1['ins_last']?>" required=""/>
						<input type="hidden" name="ins1[ins_ID]" value="<?php echo @$ins1['ins_ID']?>"/>
					</div>
					<div class="col3">
						<label>First Name</label>
						<input type="text" class="cfname has_required" name="ins1[ins_first]" value="<?php echo @$ins1['ins_first']?>" required=""/>
					</div>
					<div class="col3 ilabel">
						<label>Relation to Patient?</label><br>
						<?php tr_radio('ins1[ins_relation]',array('Self'=>'Self','Parent'=>'Parent','Spouse'=>'Spouse'),@$ins1['ins_relation'],array('attr'=>'required'))?>
					</div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Insurance Company Name</label>
						<input type="text" class="cfname ins_company_auto" name="ins1[ins_company]" value="<?php echo @$ins1['ins_company']?>" required=""/>
					</div>
					<div class="col3">
						<label>Email</label>
						<input type="email" name="ins1[ins_email]" value="<?php echo @$ins1['ins_email']?>"/>
					</div>
					<div class="col3" id="dob_ins1" <?php if(@$ins1['ins_relation']=='Self')echo 'style="display: none"'?>>
						<label><span>Parent</span> DOB</label>
						<input type="text" class="hasdate birthday" name="ins1[ins_dob]" value="<?php echo @$ins1['ins_dob']?>"/>
					</div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Street Address</label>
						<input type="text" name="ins1[ins_address]" value="<?php echo @$ins1['ins_address']?>" required=""/>
					</div>
					<div class="col3">
						<label>City</label>
						<input type="text" name="ins1[ins_city]" value="<?php echo @$ins1['ins_city']?>" required=""/>
					</div>
					<div class="col3">
						<div class="frow">
							<div class="col2">
								<label>State</label>
								<?php tr_select_option('ins1[ins_state]',tr_get_states(),@$ins1['ins_state'],array('attr'=>'required'))?>
							</div>
							<div class="col2">
								<label>Zip</label>
								<input type="text" name="ins1[ins_zip]" value="<?php echo @$ins1['ins_zip']?>" required=""/>
							</div>
						</div>
					</div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Insurance Phone</label>
						<input type="text" class="has_phone" name="ins1[ins_phone]" value="<?php echo @$ins1['ins_phone']?>"/>
					</div>
					<div class="col3">
						<label>Member ID</label>
						<input type="text" name="ins1[ins_member_id]" value="<?php echo @$ins1['ins_member_id']?>" required=""/>
					</div>
					<div class="col3">
						<label>Group #</label>
						<input type="text" name="ins1[ins_group_id]" value="<?php echo @$ins1['ins_group_id']?>"/>
					</div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Pre-authorization</label>
						<?php tr_yes_no('ins1[ins_authorization]',@$ins1['ins_authorization'],array('attr'=>'required'))?>
					</div>
					<div class="col3">
						<label>Authorization #</label>
						<input type="text" name="ins1[ins_authorization_id]" value="<?php echo @$ins1['ins_authorization_id']?>"/>
					</div>

				</div>

				<h4 >SECONDARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></h4>
				<div class="frow">
					<div class="col3">
						<label>Last Name</label>
						<input type="text" class="cfname" name="ins2[ins_last]" value="<?php echo @$ins2['ins_last']?>" />
						<input type="hidden" name="ins2[ins_ID]" value="<?php echo @$ins2['ins_ID']?>"/>
					</div>
					<div class="col3">
						<label>First Name</label>
						<input type="text" class="cfname" name="ins2[ins_first]" value="<?php echo @$ins2['ins_first']?>" />
					</div>
					<div class="col3 ilabel">
						<label>Relation to Patient?</label><br>
						<?php tr_radio('ins2[ins_relation]',array('Self'=>'Self','Parent'=>'Parent','Spouse'=>'Spouse'),@$ins2['ins_relation'])?>
					</div>
					<div class="col3"></div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Insurance Company Name</label>
						<input type="text" class="cfname ins_company_auto" name="ins2[ins_company]" value="<?php echo @$ins2['ins_company']?>"/>
					</div>
					<div class="col3">
						<label>Email</label>
						<input type="email" name="ins2[ins_email]" value="<?php echo @$ins2['ins_email']?>"/>
					</div>
					<div class="col3" id="dob_ins2" <?php if(@$ins2['ins_relation']=='Self')echo 'style="display: none"'?>>
						<label><span>Parent</span> DOB</label>
						<input type="text" class="hasdate birthday" name="ins2[ins_dob]" value="<?php echo @$ins2['ins_dob']?>"/>
					</div>
				</div>
				<div class="frow">
					<div class="col3">
						<label>Insurance Phone</label>
						<input type="text" class="has_phone" name="ins2[ins_phone]" value="<?php echo @$ins2['ins_phone']?>"/>
					</div>
					<div class="col3">
						<label>Member ID</label>
						<input type="text" name="ins2[ins_member_id]" value="<?php echo @$ins2['ins_member_id']?>"/>
					</div>
					<div class="col3">
						<label>Group #</label>
						<input type="text" name="ins2[ins_group_id]" value="<?php echo @$ins2['ins_group_id']?>"/>
					</div>
				</div>

			</div>
			<div class="col13" id="use_required_com">
				<?php
				if($btype=='rpp_gi')
				{
					?>
					<textarea name="bat[clinic_history]" style="height:400px"><?php echo $bat['clinic_history']?></textarea>
					<?php
				}else{
					?>
					<div class="frow">
						<div class="col2">
							<label>Subscriber Last Name</label>
							<input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo @$bat['subscriber_last']?>" required=""/>
						</div>
						<div class="col2">
							<label>Subscriber First Name</label>
							<input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo @$bat['subscriber_first']?>" required=""/>
						</div>
					</div>

					<div class="frow">
						<div class="col23">
							<label>Address of Claim</label>
							<input type="text"  name="bat[address_of_claim]" value="<?php echo @$bat['address_of_claim']?>"/>
						</div>
						<div class="col13">
							<label>Zip Code</label>
							<input type="text"  name="bat[subscriber_zip]" value="<?php echo @$bat['subscriber_zip']?>"/>
						</div>
					</div>
					<div class="frow">
						<div class="col23">
							<label>Insurance</label>
							<input type="text" class="cfname"  name="bat[subscriber_insurance]" value="<?php echo @$bat['subscriber_insurance']?>" required=""/>
						</div>
						<div class="col13">
							<label>WC#</label>
							<input type="text"  name="bat[subscriber_wc]" value="<?php echo @$bat['subscriber_wc']?>"/>
						</div>
					</div>
					<div class="frow">
						<div class="col23">
							<label>Attorney</label>
							<input type="text"  name="bat[attorney_name]" value="<?php echo @$bat['attorney_name']?>" />
						</div>
						<div class="col13">
							<label>Attorney Phone</label>
							<input type="text" class="has_phone"  name="bat[attorney_phone]" value="<?php echo @$bat['attorney_phone']?>"/>
						</div>
					</div>
					<div class="frow">
						<label>Adjuster Name</label>
						<input type="text" class="cfname" name="bat[adjuster_name]" value="<?php echo @$bat['adjuster_name']?>"/>
					</div>
					<div class="frow">
						<label>Adjuster Phone Number</label>
						<input type="text" class="has_phone" name="bat[adjuster_phone]" value="<?php echo @$bat['adjuster_phone']?>"/>
					</div>
					<div class="frow">
						<label>Claim Number</label>
						<input type="text" name="bat[claim_number]" value="<?php echo @$bat['claim_number']?>" required=""/>
					</div>
					<div class="frow">
						<label>Claim under litigation?</label>
						<?php tr_yes_no('bat[claim_under_litigation]',@$bat['claim_under_litigation']);?>
					</div>
					<?php
				}
				?>

			</div>
		</div>

		<div class="frow blabel">
			<div class="col2">
				<br>
				<label>Patient Signature:</label>
				<?php
				$fname = '';
				if(@$bat['mid']>0) {

					$fname = !empty($bat['patient_signature'])? $bat['patient_signature']:$batch_key . '_patient.png';

					if (file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
						?>
						<img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time(); ?>" style="width:300px"/>

						<?php
					}elseif(@$_REQUEST['page']=='ci_short_list' && $act!='print' && $act!='view'){
						?>
						<div class="card_file">
							<a class="button">Choose Signature Image</a>
							<span></span>
							<input type="file" name="patient_signature" id="patient_signature_image"  accept="image/*"/>
						</div>
						<?php
					}else if($act=='edit'){
						echo tr_signature(array('name'=>'patient_signature','required'=>true));
						$fname = '';
					}
				}else{
					echo tr_signature(array('name'=>'patient_signature','required'=>true));
				}?>

				<?php
				if(@$_REQUEST['page']!='ci_short_list' && $act!='print' && $act!='view'){
					?>
					<div class="card_file">
						<a class="button">Choose Signature Image</a>
						<span></span>
						<input type="file" name="patient_signature" id="patient_signature_image"  accept="image/*"/>

						<?php
						$pdf_file = str_replace('.jpg','.pdf',$fname);
						if (stripos($pdf_file,'.pdf')!==false && file_exists($sign_dir['path'] . '/' . $pdf_file))
						{
							?>
							<a class="button" href="<?php echo $sign_dir['url'] . '/' . $pdf_file  ?>">PDF Req</a>
							<?php
						}
						?>

					</div>
					<?php
				}

				?>

				<input type="hidden" name="img_patient_signature" value="<?php echo $fname?>" />
			</div>
			<div class="col2">
				<label><b>Authorized Healthcare</b></label>
				<label>Provider Signature:</label>
				<?php
				$fname = '';
				if(@$bat['mid']>0) {
					$fname = !empty($bat['provider_signature'])? $bat['provider_signature']:$batch_key.'_provider.png';
					if (file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0){
						?>
						<img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname.'?'.time(); ?>"
						     style="width:300px"/>
						<?php
					}
					else if($act!='print' && $act!='view'){
						?>
						<div class="card_file">
							<a class="button">Choose Signature Image</a>
							<span></span>
							<input type="file" name="provider_signature" accept="image/*"/>
						</div>
						<?php
					}

				}else{
					echo tr_signature(array('name'=>'provider_signature'));
				}?>
				<input type="hidden" name="img_provider_signature" value="<?php echo $fname?>" />
			</div>
		</div>



		<div class="frow">
			<h4>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h4>
		</div>
		<div class="frow">
			By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
			can be obtained directly from the computer or a scanner.
		</div>
		<div class="frow blabel">
			<?php
			$col_num = ($action_form!='ci_save_form_requisition')?'col2':'col4';
			?>
			<div class="<?php echo $col_num?>">
				<?php
				$fname = !empty($bat['ins_card_front'])? $bat['ins_card_front']:$batch_key.'_ins.jpg';
				$rq1 = false;
				if(!empty($action_form))
				{
					?>
					<div class="card_file">
						<a class="button <?php if($rq1)echo 'required'?>">Choose Ins Card Front File</a>
						<span>
                            <?php
                            if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
                            {
	                            echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
                            }else{
	                            $fname = '';
                            }
                            ?>
                        </span>
						<input type="file" <?php if($rq1)echo 'data-required="1" required '?> name="ins_card_front"  accept="image/*"/>
						<input type="hidden" name="img_ins_card_front" value="<?php echo $fname?>"/>
					</div>
					<?php
				}
				else
				{
					echo '<label>Ins Card Front</label>';

					if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
					{
						echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
					}
				}
				?>

			</div>
			<div class="<?php echo $col_num?>">
				<?php
				$fname = !empty($bat['ins_card_back'])? $bat['ins_card_back']:$batch_key.'_ins_back.jpg';
				if(!empty($action_form))
				{
					?>
					<div class="card_file">
						<a class="button">Choose Ins Card Back File</a>
						<span>
                            <?php
                            if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
                            {
	                            echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
                            }else{
	                            $fname = '';
                            }
                            ?>
                        </span>
						<input type="file" name="ins_card_back"/>
						<input type="hidden" name="img_ins_card_back" value="<?php echo $fname?>"/>
					</div>
					<?php
				}
				else
				{
					echo '<label>Ins Card Back</label>';

					if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
					{
						echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
					}
				}
				?>

			</div>

			<div class="<?php echo $col_num?>">
				<?php
				$fname = !empty($bat['patient_id_front'])? $bat['patient_id_front']:$batch_key.'_id.jpg';
				if(!empty($action_form))
				{
					?>
					<div class="card_file">
						<a class="button">Choose Patient ID Front File</a>
						<span>
                                <?php
                                if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
                                {
	                                echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
                                }else{
	                                $fname = '';
                                }
                                ?>
                            </span>
						<input type="file" name="patient_id_front"/>
						<input type="hidden" name="img_patient_id_front" value="<?php echo $fname?>"/>
					</div>
					<?php
				}
				else
				{
					echo '<label>Patient ID Front</label>';

					if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
					{
						echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
					}
				}
				?>

			</div>
			<div class="<?php echo $col_num?>">
				<?php
				$fname = !empty($bat['patient_id_back'])? $bat['patient_id_back']:$batch_key.'_id_back.jpg';
				if(!empty($action_form))
				{
					?>
					<div class="card_file">
						<a class="button">Choose Patient ID Back File</a>
						<span>
                            <?php
                            if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
                            {
	                            echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
                            }else{
	                            $fname = '';
                            }
                            ?>
                        </span>
						<input type="file" name="patient_id_back"/>
						<input type="hidden" name="img_patient_id_back" value="<?php echo $fname?>"/>
					</div>
					<?php
				}
				else{
					echo '<label>Patient ID Back</label>';

					if(file_exists($card_dir['path'].'/'.$fname) && filesize($card_dir['path'].'/'.$fname)>0)
					{
						echo '<img src="'.$card_dir['url'].'/'.$fname.'?r='.time().'" style="width:100%">';
					}
				}
				?>

			</div>
		</div>

		<div class="frow">
			<h4>NOTES</h4>
		</div>

		<div class="frow">
			<textarea name="bat[billing_notes]" maxlength="200" <?php if($act!='print')echo 'placeholder="Please enter any Billing Notes"'?> ><?php if(isset($bat['billing_notes']))echo @$bat['billing_notes']?></textarea>
		</div>

		<div class="frow">
			<div class="col3">
				<label>Claim ID</label>
				<input type="text" name="bb[claim_no]" value="<?php echo !empty($bb['claim_no']) ? $bb['claim_no'] : $bat['claim_id']?>"/>
			</div>
			<div class="col3">
				<label>Claim Date</label>
				<input type="text" class="hasdate today" data-write="1" name="bb[claim_date]" value="<?php echo $bb['claim_date']?>"/>
			</div>

		</div>
		<div class="frow">
			<div class="col3">
				<label>Status</label>
				<?php
				echo tr_select_option('bb[bill_status]',$bill_statuses,$bb['bill_status'])?>
			</div>
			<div class="col3">
				<label>Billed Amount</label>
				<input type="text" name="bb[billed_amount]" value="<?php echo $bb['billed_amount']?>"/>
			</div>
		</div>


		<div id="billing_codes_div">
			<div class="frow show_classes">
				<h4>Billing Codes</h4>
			</div>
			<?php

			if(isset($_REQUEST['debug'])){
				var_dump($bat['insurance']);
				var_dump($ins1['ins_company']);
			}

			tr_bill_get_billing_code($ins1['ins_company'],$report['id'],$bat);
			?>
		</div>
		<?php if(!empty($action_form) && $act!='print'):
			$date = $_REQUEST['date'];
			$next = tr_bill_get_next_link($report,$date);
			?>

			<div>
				<input type="submit" class="button-primary" value="Submit">
				<?php if($next):?>
					<input type="submit" name="submit_next" class="button" value="Submit & Next"/>
					<a href="<?php echo $next?>" class="button">Next</a>
				<?php endif;?>
				<input type="hidden" name="next_link" value="<?php echo $next?>"/>
				<input type="hidden" name="tr_action" value="<?php echo $action_form?>"/>
				<?php if(@$mid > 0):?>
					<input type="hidden" name="bat[mid]" value="<?php echo $mid?>"/>
				<?php endif;?>
			</div>

		<?php endif;?>

	</form>
</div>
<input type="hidden" id="profile_link" value="admin.php?page=ci_data_forms&tab=custom_provider"/>

<?php if($act=='print'):?>
	<script>
        window.print();
        //parent.close_popup();
	</script>
<?php endif;?>
<?php if($current_lab_id!=11):?>
	<script>
        jQuery(function($){


            $('.requesting_physician_last_auto').autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=requesting_patients_last_auto',
                minLength: 1,
                select: function( event, ui ) {
                    if(ui.item)
                    {
                        $('[name="pa[pa_ID]"]').val(ui.item.id);
                        for(k in ui.item.pa)
                        {
                            $.tr_fill_value('[name="pa['+k+']"]',ui.item.pa[k]);
                        }
                        if(ui.item.ins1)
                        {
                            for(k in ui.item.ins1)
                            {
                                $.tr_fill_value('[name="ins1['+k+']"]',ui.item.ins1[k]);
                            }
                        }
                        if(ui.item.ins2)
                        {
                            for(k in ui.item.ins2)
                            {
                                $.tr_fill_value('[name="ins2['+k+']"]',ui.item.ins2[k]);
                            }
                        }
                        if(ui.item.images)
                        {
                            for(k in ui.item.images)
                            {
                                var vl = ui.item.images[k];
                                if(vl!==''){
                                    var $obj = $('[name="img_'+k+'"]');
                                    var $pr = $obj.parents('.card_file');
                                    $obj.val(vl);
                                    $('span',$pr).html('<div><img src="'+ui.item.card_url+'/'+vl+'"></div>');
                                }
                            }
                        }
                    }else{
                        $('[name="pa[pa_ID]').val('');
                    }
                }
            });
        })
	</script>
<?php endif;?>

<style>
	.drug_classes{
		padding-bottom:5px;
	}
	.drug_classes:hover{
		background: rgba(255, 255, 153, 0.5)
	}
	.drug_classes.col2:nth-child(2n+1){
		border-right:1px solid #aaa;
		width:47.5%;
		margin-right:2%;
	}
</style>
<script>
    jQuery(function($){
        $('#batcustom_profile').change(function(){
            var $vl = $(this).val();
            $('#default_requests input').removeAttr('checked');
            $('#default_requests input.te_'+$vl).prop('checked',true);
        })
        $('#box_create_rand').click(function(){
            var $ptient = encodeURI($('[name="pa[pa_last]"]').val()+'|'+$('[name="pa[pa_first]"]').val());
            var $dob = encodeURI($('[name="pa[pa_dob]"]').val())
            tb_show('','<?php echo get_home_url();?>/wp-admin/admin.php?&tr_action=qrcode&width=400&height=400&text='+$(this).data('a')+'&pa='+$ptient+'&dob='+$dob);
            return false;
        });
        $('#requisition_form').submit(function(){
            if($('#confirm_assen').val()!=$('[name="bat[assen]"]').val()){
                alert('Please Confirm Accession Number');
                $('#confirm_assen').focus();
                return false;
            }
            if($('[name="bb[bill_status]"]').val()=='Need Info')
            {
                $('form [required]').removeAttr('required');
            }
        });
        $('#confirm_assen,[name="bat[assen]"]').keyup(function(){
            if($('#confirm_assen').val()!='' && $('#confirm_assen').val()==$('[name="bat[assen]"]').val()){
                $('.confirm_status').addClass('confirmed');
                $('.confirm_status').removeClass('error');
            }else{
                $('.confirm_status').removeClass('confirmed');
                $('.confirm_status').addClass('error');
            }
        })
        $('body').on('click','.only_save_form',function(){
            $('form [required]').removeAttr('required');
        })
        function tr_bill_get($ins,$re_id,$btype)
        {
            $.ajax(
                {
                    data:{tr_action:'ci_get_change_billing_codes',ins:$ins,report_id:$re_id,btype:$btype},
                    success:function(rs){
                        $('#billing_codes_div').html(rs);
                    },
                    error:function(){
                        tr_bill_get($ins,$re_id,$btype);
                    }
                }

            )
        }
        $('body').on('change','[name="ins1[ins_company]"]',function(){
            var $ins = $(this).val();
            var $re_id = <?php echo $report['id']?>;
            var $btype = <?php echo $report['btype']?>;
            $('[name="no_insurance"]').removeAttr('checked').change();
            tr_bill_get($ins,$re_id,$btype);
        })
    })
</script>