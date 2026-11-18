<?php
global $wpdb,$blog_id,$current_role,$blog_id;

include_once(TRCPDD_PATH.'pages/formdata.php');


$user_id    = get_current_user_id();
$join_rep   = '';

$act = isset($_REQUEST['act'])? $_REQUEST['act']:'';

$where_rep = '';
if($current_role == 'distributor')
{
	$dis_ID =$wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`= $user_id ");
	$join_rep .= " left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID ";
	$where_rep.= " and (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ";
}


if(isset($_POST['save_group'])){
	$data = $_POST['data'];
	$data['clig_princial'] = (is_array($data['clig_princial']))? json_encode($data['clig_princial']):'';
	$clig_ID = $data['clig_ID'];

	if($clig_ID>0)
	{
		$rs = $wpdb->update('ci_clinician_groups',$data,array('clig_ID'=>$clig_ID));
		if($rs===false)
		{
			Tr_Session_Class::add_error('Error: '.$wpdb->last_error);
		}else{
			Tr_Session_Class::add('Updated group');
		}

	}else{
		unset($data['clig_ID']);
		$rs = $wpdb->insert('ci_clinician_groups',$data);
		$clig_ID = $wpdb->insert_id;


		if($rs)
		{
			Tr_Session_Class::add('Added group  - <a class="button" href="'.Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $clig_ID),false) .'">Edit</a>
                 - <a  class="button" href="'.Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $clig_ID),false) .'">Add Members</a>');

			//create new rep leader if contact info not empty
			$email = $data['clig_email'];
			if(!empty($email) && is_email($email))
			{
				$pass = wp_generate_password( 12, false );
				$userarr = array(
					'user_login' => $email,
					'user_email' => $email,
					'user_pass' => $pass,
					'first_name' => $data['clig_contact_name'],
					'repplay_name' => $data['clig_contact_name'],
					'role' => 'clinic'
				);
				$user_id = wp_insert_user($userarr);
				if(!is_wp_error($user_id))
				{
					$rs = trpdd_send_mail_create_user($user_id,$pass);

					Tr_Session_Class::add('Created User as leader: '.$email);
					if($rs)
					{
						Tr_Session_Class::add('Sent email to '.$email);
					}else{
						Tr_Session_Class::add_error('Error while send email to '.$email);
					}
					update_user_meta($user_id,'_clinic_group',$clig_ID);

					$wpdb->update('ci_clinician_groups',array('clig_admins'=>$user_id),array('gr_ID'=>$clig_ID));

				}else{
					Tr_Session_Class::add_error($user_id->get_error_message());
				}
			}


			$data = array();
		}else{
			Tr_Session_Class::add_error('Error. Please try again. '.$wpdb->last_error);
		}
	}
}
else if(isset($_POST['save_group_members'])){
	$data = $_POST['data'];
	$clig_ID = $data['clig_ID'];
	if($clig_ID>0)
	{
		if(!is_array($data['clig_admins'])) $data['clig_admins'] = array();
		if(!is_array($data['clig_members'])) $data['clig_members'] = array();

		if(count($data['clig_admins'])>0)
		{
			foreach($data['clig_admins'] as $uid)
			{
				$wpdb->update($wpdb->usermeta,array('meta_value'=>''),array('meta_key'=>'_clinic_group','meta_value'=>$clig_ID));
				update_user_meta($uid,'_clinic_group',$clig_ID);
			}

		}

		//update members
		$wpdb->update('ci_clinic',array('cli_group_ID'=>''),array('cli_group_ID'=>$clig_ID));
		foreach($data['clig_members'] as $cli_id)
		{
			$wpdb->update('ci_clinic',array('cli_group_ID'=>$clig_ID),array('cID'=>$cli_id));
		}
		unset($data['clig_members']);
		$data['clig_admins'] = implode(',',$data['clig_admins']);
		$wpdb->update('ci_clinician_groups',$data,array('clig_ID'=>$clig_ID));
		Tr_Session_Class::add('Updated group');
	}
}
else if($act=='delete'){
	$wpdb->query("UPDATE `ci_clinician_groups` SET `clig_inactive`='1' WHERE  `clig_ID`='".$_REQUEST['id']."'");
	Tr_Session_Class::add('InActive group');
}
else if($act=='active'){
	$wpdb->query("UPDATE `ci_clinician_groups` SET `clig_inactive`='0' WHERE  `clig_ID`='".$_REQUEST['id']."'");
	Tr_Session_Class::add('Active group');
}

if($act=='add' || $act=='edit')
{

	if($act=='edit'){
		$id = $_REQUEST['id'];
		$data = $wpdb->get_row("select * from ci_clinician_groups where clig_ID = {$id} and clig_site_id='{$blog_id}' ", ARRAY_A);
		$clig_princial = @json_decode($data['clig_princial'],true);
	}else{
		$data['clig_site_id'] = $blog_id;
	}


	$reps = array(''=>'Select Rep');
	$labs = array(''=>'Select Lab');

	if($current_role=='lab_rep' || $current_role=='lab_rep_w2')
	{
		$rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
		$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

	}else if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms'))) {
		$lab_id = CI_Base_Lab::get_current_lab_id();
		if ($lab_id > 0) {
			$rep['lab_ID'] = $lab_id;
			$where_rep .= " and lab_ID = {$lab_id}";
		}

		$las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) ");
		foreach ($las as $l) {
			$labs[$l->lab_ID] = $l->lab_dba;
		}
	}

	$rep_rs = $wpdb->get_results("SELECT rep.* FROM ci_lab_reps rep
                            {$join_rep}
                             where 1 {$where_rep}");
	if(isset($rep_rs) && count($rep_rs)>0)
	{
		foreach($rep_rs as $r)
		{
			$reps[$r->rep_ID] = $r->rep_first.' '.$r->rep_last;
		}
	}

	?>
    <div class="wrap">
        <h2>Clinician Group
			<?php if($act=='edit'):?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $id)) ?>" class="add-new-h2">Add members</a>
			<?php endif;?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a></h2>

		<?php Tr_Session_Class::show()?>

        <form method="post" class="need_validate">
            <input type="hidden" name="data[clig_ID]" value="<?php echo isset($data['clig_ID'])? $data['clig_ID'] : ''?>"/>
            <input type="hidden" name="data[clig_site_id]" value="<?php echo isset($data['clig_site_id'])? $data['clig_site_id'] : ''?>"/>


            <div class="frow">
                <h4>Info</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Name</label>
                    <input type="text" name="data[clig_name]" value="<?php echo isset($data['clig_name'])? $data['clig_name'] : ''?>" maxlength="150"  required/>
                </div>
				<?php if($current_role=='lab_rep' || $current_role=='lab_rep_w2'):?>
                    <div class="col4">
                        <label>SALES REP LAST NAME</label>
                        <input type="text" name="rep[rep_last]" value="<?php echo $rep['rep_last']?>" required=""/>
                        <input type="hidden" name="data[clig_rep_ID]" value="<?php echo $rep['rep_ID']?>"/>
                    </div>
                    <div class="col4">
                        <label>SALES REP FIRST NAME</label>
                        <input type="text" name="rep[rep_first]" value="<?php echo $rep['rep_first']?>" required=""/>
                    </div>
                    <div class="col4">
                        <label>MIDDLE INITIAL</label>
                        <input type="text" name="rep[rep_middle]" value="<?php echo $rep['rep_middle']?>" />
                    </div>
				<?php else:?>
                    <div class="col4">
                        <label>SALES REP NAME</label>
						<?php tr_select_option('data[clig_rep_ID]',$reps,isset($data['clig_rep_ID'])? $data['clig_rep_ID'] : '',array('attr'=>'required'));?>
                    </div>
                    <div class="col4"></div>
                    <div class="col4"></div>


				<?php endif;?>
            </div>
            <div class="frow">
                <label>Address</label>
                <input type="text" name="data[clig_address]" value="<?php echo isset($data['clig_address'])? $data['clig_address'] : ''?>" maxlength="255" />

            </div>
            <div class="frow">
                <div class="col3">
                    <label>City</label>
                    <input type="text" name="data[clig_city]" value="<?php echo isset($data['clig_city'])? $data['clig_city'] : ''?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>State</label>
					<?php tr_select_option('data[clig_state]',tr_get_states(), isset($data['clig_state'])? $data['clig_state'] : '')?>
                </div>
                <div class="col3">
                    <label>Zip</label>
                    <input type="text" name="data[clig_zip]" value="<?php echo isset($data['clig_zip'])? $data['clig_zip'] : ''?>" maxlength="100"/>
                </div>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Telephone</label>
                    <input type="text" name="data[clig_phone]" value="<?php echo isset($data['clig_phone'])? $data['clig_phone'] : ''?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>Fax</label>
                    <input type="text" name="data[clig_fax]" value="<?php echo isset($data['clig_fax'])? $data['clig_fax'] : ''?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>Website</label>
                    <input type="text" name="data[clig_website]" value="<?php echo isset($data['clig_website'])? $data['clig_website'] : ''?>" maxlength="255"/>
                </div>
            </div>
            <div class="frow">
                <div class="col5">
                    <label>Company Type</label>
                </div>
                <div class="col2 linline lcheckboxes">
                    <label><input type="radio" name="data[clig_company_type]" value="LLC" maxlength="100" <?php checked('LLC', isset($data['clig_company_type'])? $data['clig_company_type'] : '')?>/> LLC</label>
                    <label><input type="radio" name="data[clig_company_type]" value="C Corp" maxlength="100" <?php checked('C Corp',isset($data['clig_company_type'])? $data['clig_company_type'] : '')?>/> C Corp</label>
                    <label><input type="radio" name="data[clig_company_type]" value="S Corp" maxlength="100" <?php checked('S Corp',isset($data['clig_company_type'])? $data['clig_company_type'] : '')?>/> S Corp</label>
                    <label><input type="radio" name="data[clig_company_type]" value="Other" maxlength="100" <?php checked('Other',isset($data['clig_company_type'])? $data['clig_company_type'] : '')?>/> Other</label>
                    <input type="text" name="data[clig_company_type_other]" value="<?php echo isset($data['clig_website'])? $data['clig_website'] : ''?>" maxlength="255" style="width:260px"/>
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>State of Incorporation</label>
					<?php tr_select_option('data[clig_state_inc]',tr_get_states(), isset($data['clig_state_inc'])? $data['clig_state_inc'] : '' )?>
                </div>
                <div class="col3">
                    <label>EIN</label>
                    <input type="text" name="data[clig_state_inc_ein]" value="<?php echo isset($data['clig_state_inc_ein'])? $data['clig_state_inc_ein'] : ''?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>W9</label><br>
                    <a href="https://www.irs.gov/pub/irs-pdf/fw9.pdf" target="_blank">(click for form)</a>
                </div>
            </div>

            <div class="frow">
                <h4>Contact Info</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Contact Name</label>
                    <input type="text" name="data[clig_contact_name]" value="<?php echo isset($data['clig_contact_name'])? $data['clig_contact_name'] : ''?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Telephone</label>
                    <input type="text" name="data[clig_contact_phone]" value="<?php echo isset($data['clig_contact_phone'])? $data['clig_contact_phone'] : ''?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Fax</label>
                    <input type="text" name="data[clig_contact_fax]" value="<?php echo isset($data['clig_contact_fax'])? $data['clig_contact_fax'] : ''?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>Email</label>
                    <input type="text" name="data[clig_email]" value="<?php echo isset($data['clig_email'])? $data['clig_email'] : ''?>" maxlength="255" />
                </div>
            </div>

            <div class="frow">
                <h4>Principal</h4>
            </div>

			<?php for($i=1;$i<=3;$i++):?>

                <div class="frow">
                    <div class="col4">
                        <label>Principal Name</label>
                        <input type="text" name="data[clig_princial][name<?php echo $i?>]" value="<?php echo isset($clig_princial['name'.$i])? $clig_princial['name'.$i] : ''?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Ownership %</label>
                        <input type="text" name="data[clig_princial][ownership<?php echo $i?>]" value="<?php echo isset($clig_princial['ownership'.$i])? $clig_princial['ownership'.$i] : '' ?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Cellphone</label>
                        <input type="text" name="data[clig_princial][phone<?php echo $i?>]" value="<?php echo isset($clig_princial['phone'.$i])? $clig_princial['phone'.$i] : ''?>" maxlength="255" />
                    </div>
                    <div class="col4">
                        <label>Email</label>
                        <input type="text" name="data[clig_princial][email<?php echo $i?>]" value="<?php echo isset($clig_princial['email'.$i])? $clig_princial['email'.$i] : ''?>" maxlength="255" />
                    </div>
                </div>

			<?php endfor?>


            <div class="frow">
                <h4>Payment Preference</h4>
            </div>
            <div class="frow linline lcheckboxes">
                <label><input type="radio" name="data[clig_payment_type]" class="payment_check" value="Check" <?php checked('Check', isset($data['clig_payment_type'])? $data['clig_payment_type'] : '' )?> /> Check</label>
                <label><input type="radio" name="data[clig_payment_type]" value="ACH" <?php checked('ACH', isset($data['clig_payment_type'])? $data['clig_payment_type'] : '')?> /> ACH</label>
            </div>
            <div class="frow payment_info">
                <div class="col4">
                    <label>Bank Name</label>
                    <input type="text" name="data[clig_payment_bank_name]" value="<?php echo isset($data['clig_payment_bank_name'])? $data['clig_payment_bank_name'] : ''?>" maxlength="255"/>
                </div>
                <div class="col4">
                    <label>ABA</label>
                    <input type="text" name="data[clig_payment_aba]" value="<?php echo isset($data['clig_payment_aba'])? $data['clig_payment_aba'] : ''?>" maxlength="100"/>
                </div>
                <div class="col4">
                    <label>Account Number</label>
                    <input type="text" name="data[clig_payment_account_number]" value="<?php echo isset($data['clig_payment_account_number'])? $data['clig_payment_account_number'] : ''?>" maxlength="255"/>
                </div>
                <div class="col4">
                    <label>Account Name</label>
                    <input type="text" name="data[clig_payment_account_name]" value="<?php echo isset($data['clig_payment_account_name'])? $data['clig_payment_account_name'] : ''?>" maxlength="255"/>
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Commission</label>
                    <input type="text" name="data[clig_comission]" value="<?php echo isset($data['clig_comission'])? $data['clig_comission'] : ''?>"/>
                </div>
                <div class="col3">
                    <label>Start Date</label>
                    <input type="text" name="data[clig_sdate]"  class="hasdate" value="<?php echo isset($data['clig_sdate'])? $data['clig_sdate'] : ''?>"/>
                </div>
                <div class="col3">
                    <label>End date</label>
                    <input type="text" name="data[clig_edate]" class="hasdate" value="<?php echo isset($data['clig_edate'])? $data['clig_edate'] : ''?>"/>
                </div>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Tox</label>
                    <input type="hidden" name="data[clig_tox]" value="0"/>
                    <input type="checkbox" name="data[clig_tox]" value="1" <?php checked(isset($data['clig_tox'])? $data['clig_tox'] : '')?>"/>
                </div>
                <div class="col3">
                    <label>Blood</label>
                    <input type="hidden" name="data[clig_blood]" value="0"/>
                    <input type="checkbox" name="data[clig_blood]"  value="1" <?php checked(isset($data['clig_blood'])? $data['clig_blood'] : '')?>"/>
                </div>
                <div class="col3">
                    <label>DNA</label>
                    <input type="hidden" name="data[clig_dna]" value="0"/>
                    <input type="checkbox" name="data[clig_dna]" value="1" <?php checked(isset($data['clig_dna'])? $data['clig_dna'] : '')?>"/>
                </div>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_group" value="1"/>
            </div>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            $('[name="data[clig_payment_type]"]').change(function(){
                if($('.payment_check').is(':checked')){
                    $('.payment_info').hide();
                }else{
                    $('.payment_info').show();
                }
            }).change();
        })
    </script>

	<?php
}
else if($act=='add_members')
{
	$id     = $_REQUEST['id'];
	$data   = $wpdb->get_row("select * from ci_clinician_groups 
	                    where clig_ID = {$id} and clig_site_id='{$blog_id}' ", ARRAY_A);

	?>
    <div class="wrap">
        <h2>Clinician Group
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id)) ?>" class="add-new-h2">Edit</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a></h2>

		<?php Tr_Session_Class::show()?>

        <form method="post" class="need_validate">
            <div class="frow">
                <label>Name</label>
                <input type="text" name="data[clig_name]" value="<?php echo isset($data['clig_name'])? $data['clig_name'] : ''?>" maxlength="150" style="width:90%" required/>
                <input type="hidden" name="data[clig_ID]" value="<?php echo isset($data['clig_ID'])? $data['clig_ID'] : ''?>"/>
                <input type="hidden" name="data[clig_site_id]" value="<?php echo isset($data['clig_site_id'])? $data['clig_site_id'] : ''?>"/>
            </div>



            <div class="frow">
                <h4>Manager</h4>
                <ul id="clig_admins" class="list_user_members">
					<?php
					if(!empty($data['clig_admins']))
					{
						$users = $wpdb->get_results("select ID,
                        concat(u.user_login ,' (', fname.meta_value ,' ',lname.meta_value ,')') as user_login
                        from {$wpdb->users} u
                        {$joins}
                        left join {$wpdb->usermeta} fname ON fname.user_id = u.ID and fname.meta_key='first_name'
                        left join {$wpdb->usermeta} lname ON lname.user_id = u.ID and lname.meta_key='last_name'
                        Where u.ID in ({$data['clig_admins']})");
						foreach($users as $user)
						{
							?>
                            <li><input type="hidden" name="data[clig_admins][]" value="<?php echo $user->ID?>"/> <?php echo $user->user_login?><a class="remove left"></a></li>
							<?php
						}
					}
					?>
                </ul>
                <input type="text" class="user_auto" rel="clig_admins" placeholder="Search User" style="width:200px"/>
            </div>


            <div class="frow">
                <h4>Members</h4>
                <ul id="clig_members" class="list_user_members">
					<?php
					$clis = $wpdb->get_results("select * from ci_clinic where cli_group_ID = {$id}");
					foreach($clis as $cli)
					{
						?>
                        <li><input type="hidden" name="data[clig_members][]" value="<?php echo $cli->cID?>"/> <?php echo $cli->cli_clinic?><a class="remove left"></a></li>
						<?php
					}
					?>
                </ul>
                <input type="text" class="requesting_practice_auto" rel="clig_members" placeholder="Search Clinic" style="width:200px"/>
                <a class="button" href="admin.php?page=ci_data_forms&clig=<?php echo $id?>">Add New Clinic</a>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_group_members" value="1"/>
            </div>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            $('.user_auto').autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=user_auto&target='+$(this).attr('rel'),
                minLength: 1,
                select: function( event, ui ) {
                    if(ui.item)
                    {
                        var $target = $(this).parent().find('.list_user_members');
                        var $user_id = ui.item.id;
                        var $user_name = ui.item.value;
                        if($user_id>0)
                        {
                            var $added = false;
                            $('#clig_admins input').each(function(){
                                if($(this).val()==$user_id)
                                {
                                    $added = true;
                                }
                            });
                            if($added)
                            {
                                alert('Added this user');
                            }else{
                                var $new = $('<li><input type="hidden" name="data['+$target.attr('id')+'][]" value="'+$user_id+'"/> '+$user_name+'<a class="remove left"></a></li>');
                                $target.append($new);
                            }

                        }
                        $(this).val('');
                        return false;
                    }
                }
            });

            $('.requesting_practice_auto').autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=requesting_practice_auto&target='+$(this).attr('rel'),
                minLength: 1,
                select: function( event, ui ) {
                    if(ui.item)
                    {
                        var $target = $(this).parent().find('.list_user_members');
                        var $user_id = ui.item.id;
                        var $user_name = ui.item.value;
                        if($user_id>0)
                        {
                            var $added = false;
                            $('#clig_members input').each(function(){
                                if($(this).val()==$user_id)
                                {
                                    $added = true;
                                }
                            });
                            if($added)
                            {
                                alert('Added this user');
                            }else{
                                var $new = $('<li><input type="hidden" name="data['+$target.attr('id')+'][]" value="'+$user_id+'"/> '+$user_name+'<a class="remove left"></a></li>');
                                $target.append($new);
                            }

                        }
                        $(this).val('');
                        return false;
                    }
                }
            });
            $('.list_user_members').on('click','.remove',function(){
                $(this).parents('li').remove();
            })
        })
    </script>
	<?php
}
else
{

	$sql = "select cg.*,
                (select count(cli.cID) from ci_clinic cli where cli.cli_group_ID = cg.clig_ID ) as count_member
                from ci_clinician_groups cg
                left join ci_lab_reps rep ON cg.clig_rep_ID= rep.rep_ID
                {$join_rep}
                where cg.clig_site_id = '{$blog_id}' {$where_rep}";
	$results = $wpdb->get_results($sql);
	if(isset($_REQUEST['debug']))
	{
		echo $wpdb->last_error;
		var_dump($sql);
	}
	?>
    <div class="wrap">
        <h2>Clinician Groups <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>

		<?php Tr_Session_Class::show()?>

        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Group Name</th>
                    <th>Members</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ($results as $i => $data):


					$clig_inactive=$data->clig_inactive;
					if($clig_inactive=='0'){
						$txt_action='delete';
						$txt_action_show='Inactivate';
					}else{
						$txt_action='active';
						$txt_action_show='Activate';
					}
					?>
                    <tr <?php if($active=='1'){ echo 'class="active"';}?> >
                        <td><?php echo $i + 1 ?></td>
                        <td><?php echo $data->clig_name ?></td>
                        <td><?php echo $data->count_member ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $data->clig_ID)) ?>">Add Members</a> -
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $data->clig_ID)) ?>">Edit</a> -
                            <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => $txt_action, 'id' => $data->clig_ID)) ?>"><?php echo $txt_action_show; ?></a>
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        jQuery(function ($) {
            $('a.delete').click(function () {
                if (confirm('Do you want inActive/Active?') == false)return false;
            });
        })
    </script>
	<?php
}
?>
