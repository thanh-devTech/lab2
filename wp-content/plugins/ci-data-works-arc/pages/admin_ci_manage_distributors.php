<?php
global $wpdb;

include_once(TRCPDD_PATH.'pages/formdata.php');

if(isset($_POST['save_action']))
{
	$data                   = $_POST['data'];
	$data['dis_princial']   = (is_array($data['dis_princial']))? json_encode($data['dis_princial']):'';
	$dis_ID                 = $data['dis_ID'];

	if($data['dis_ID']>0)
	{
		$rs = $wpdb->update('ci_distributors',$data,array('dis_ID'=>$dis_ID));
		if($rs===false)
		{
			Tr_Session_Class::add_error('Error. Please try again. '.$wpdb->last_error);
		}else{
			Tr_Session_Class::add('Updated Distributor');
			$data = array();
		}

	}else{
		$rs = $wpdb->insert('ci_distributors',$data);
		$dis_ID = $wpdb->insert_id;
		if($rs)
		{
			Tr_Session_Class::add('Added Distributor - <a href="'.Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $dis_ID),false) .'">Edit</a>');
			$data = array();
		}else{
			Tr_Session_Class::add_error('Error. Please try again. '.$wpdb->last_error);
		}
	}

	//check user
	$old_dis    = $wpdb->get_row("select * from ci_distributors where dis_ID = {$dis_ID}", ARRAY_A);

	$user_id    = $old_dis['dis_user_ID'];
	$email      = $old_dis['dis_email'];

	if($user_id > 0)
	{
		$user = get_user_by('id',$user_id);
		if(!$user)
		{
			$user_id = 0;
		}
	}

	if($user_id==0)
	{
		if(email_exists($email))
		{
			$user = get_user_by('email',$email);
			$user_id = $user->ID;
		}else{
			$pass = wp_generate_password( 12, false );
			$userarr = array(
				'user_login' => $email,
				'user_email' => $email,
				'user_pass' => $pass,
				'first_name' => $old_dis['dis_name'],
				'display_name' => $old_dis['dis_name'],
				'role' => 'distributor'
			);
			$user_id = wp_insert_user($userarr);

			if(!is_wp_error($user_id))
			{
				$rs = trpdd_send_mail_create_user($user_id,$pass);

				Tr_Session_Class::add('Created User: '.$email);
				if($rs)
				{
					Tr_Session_Class::add('Sent email to '.$email);
				}else{
					Tr_Session_Class::add_error('Error while send email to '.$email);
				}

			}else{
				Tr_Session_Class::add_error($user_id->get_error_message());
			}
		}

		$wpdb->update('ci_distributors',array('dis_user_ID'=>$user_id),array('dis_ID'=>$dis_ID));




	}else{
		//check user exist by id
	}
	update_user_meta($user_id,'_dna',$old_dis['dis_dna']);
	update_user_meta($user_id,'_allergy',$old_dis['dis_allergy']);
	update_user_meta($user_id,'_tox',$old_dis['dis_tox']);
	update_user_meta($user_id,'_blood',$old_dis['dis_blood']);
	update_user_meta($user_id,'_pcr',$old_dis['dis_pcr']);
}
else if(isset($_POST['save_action_members']))
{
	$data = $_POST['data'];
	if(!is_array($data['dis_sale_groups']))$data['dis_sale_groups'] = array();
	if(!is_array($data['dis_reps']))$data['dis_reps'] = array();
	$dis_ID = $data['dis_ID'];

	if($dis_ID>0)
	{
		//update reps
		$wpdb->update('ci_lab_reps',array('rep_distributor_ID'=>''),array('rep_distributor_ID'=>$dis_ID));
		foreach($data['dis_reps'] as $rep_id)
		{
			$wpdb->update('ci_lab_reps',array('rep_distributor_ID'=>$dis_ID),array('rep_ID'=>$rep_id));
		}

		//update rep group
		$wpdb->update('ci_marketing_groups',array('gr_distributor_ID'=>''),array('gr_distributor_ID'=>$dis_ID));
		foreach($data['dis_sale_groups'] as $gr_ID)
		{
			$wpdb->update('ci_marketing_groups',array('gr_distributor_ID'=>$dis_ID),array('gr_ID'=>$gr_ID));
		}
	}

}
else if($act=='delete'){
	global $wpdb;
	$wpdb->query("UPDATE `ci_distributors` SET `dis_activer`='1' WHERE  `dis_ID`='".$_REQUEST['id']."'");
	Tr_Session_Class::add('InActive Distributor');
	$user_id= $wpdb->get_results( "SELECT * FROM ci_distributors Where `dis_ID`='".$_REQUEST['id']."'" );
	$user=$user_id[0];
	$user_id=$user->dis_user_ID;
	update_user_meta($user_id,'in_active','0');
}
else if($act=='active'){
	global $wpdb;
	$wpdb->query("UPDATE `ci_distributors` SET `dis_activer`='0' WHERE  `dis_ID`='".$_REQUEST['id']."'");
	Tr_Session_Class::add('Active Distributor');
	$user_id= $wpdb->get_results( "SELECT * FROM ci_distributors Where `dis_ID`='".$_REQUEST['id']."'" );
	$user=$user_id[0];
	$user_id=$user->dis_user_ID;
	update_user_meta($user_id,'in_active','1');
}

if($act=='add' || $act=='edit')
{

	if($act=='edit'){
		$id = $_REQUEST['id'];
		$data = $wpdb->get_row("select * from ci_distributors where dis_ID = {$id}  ", ARRAY_A);
		$dis_princial = @json_decode($data['dis_princial'],true);
	}else{
		$data['dis_tox'] = 1;
	}

	?>
    <div class="wrap">
        <h2>Distributor
			<?php if($act=='edit'):?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $id)) ?>" class="add-new-h2">Add members</a>
			<?php endif;?>
            <a href="<?php Tr_Base_Class_V6::link(array('act'=>'','id'=>''))?>" class="add-new-h2">All Distributors</a></h2>
		<?php Tr_Session_Class::show()?>

        <form method="post" class="need_validate">
            <div class="frow">
                <h4>Info</h4>
            </div>
            <div class="frow">
                <div class="col2">
                    <label>Name</label>
                    <input type="text" name="data[dis_name]" value="<?php echo @$data['dis_name']?>" maxlength="255"  required/>
                </div>
            </div>
            <div class="frow">
                <label>Address</label>
                <input type="text" name="data[dis_address]" value="<?php echo @$data['dis_address']?>" maxlength="255" />

            </div>
            <div class="frow">
                <div class="col3">
                    <label>City</label>
                    <input type="text" name="data[dis_city]" value="<?php echo @$data['dis_city']?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>State</label>
					<?php tr_select_option('data[dis_state]',tr_get_states(),@$data['dis_state'])?>
                </div>
                <div class="col3">
                    <label>Zip</label>
                    <input type="text" name="data[dis_zip]" value="<?php echo @$data['dis_zip']?>" maxlength="100"/>
                </div>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Telephone</label>
                    <input type="text" name="data[dis_phone]" value="<?php echo @$data['dis_phone']?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>Fax</label>
                    <input type="text" name="data[dis_fax]" value="<?php echo @$data['dis_fax']?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>Website</label>
                    <input type="text" name="data[dis_website]" value="<?php echo @$data['dis_website']?>" maxlength="255"/>
                </div>
            </div>
            <div class="frow">
                <div class="col5">
                    <label>Company Type</label>
                </div>
                <div class="col2 linline lcheckboxes">
                    <label><input type="radio" name="data[dis_company_type]" value="LLC" maxlength="100" <?php checked('LLC',@$data['dis_company_type'])?>/> LLC</label>
                    <label><input type="radio" name="data[dis_company_type]" value="C Corp" maxlength="100" <?php checked('C Corp',@$data['dis_company_type'])?>/> C Corp</label>
                    <label><input type="radio" name="data[dis_company_type]" value="S Corp" maxlength="100" <?php checked('S Corp',@$data['dis_company_type'])?>/> S Corp</label>
                    <label><input type="radio" name="data[dis_company_type]" value="Other" maxlength="100" <?php checked('Other',@$data['dis_company_type'])?>/> Other</label>
                    <input type="text" name="data[dis_company_type_other]" value="<?php echo @$data['dis_website']?>" maxlength="255" style="width:60px"/>
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>State of Incorporation</label>
					<?php tr_select_option('data[dis_state_inc]',tr_get_states(),@$data['dis_state_inc'])?>
                </div>
                <div class="col3">
                    <label>EIN</label>
                    <input type="text" name="data[dis_state_inc_ein]" value="<?php echo @$data['dis_state_inc_ein']?>" maxlength="100"/>
                </div>
                <div class="col3">
                    <label>W9</label><br/>
                    <a href="https://www.irs.gov/pub/irs-pdf/fw9.pdf" target="_blank">(click for form)</a>
                </div>
            </div>

            <div class="frow">
                <h4>Contact Info</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Contact Name</label>
                    <input type="text" name="data[dis_contact_name]" value="<?php echo @$data['dis_contact_name']?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Telephone</label>
                    <input type="text" name="data[dis_contact_phone]" value="<?php echo @$data['dis_contact_phone']?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Fax</label>
                    <input type="text" name="data[dis_contact_fax]" value="<?php echo @$data['dis_contact_fax']?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>Email</label>
                    <input type="text" name="data[dis_email]" value="<?php echo @$data['dis_email']?>" maxlength="255"  required/>
                </div>
            </div>

            <div class="frow">
                <h4>Principal</h4>
            </div>

			<?php for($i=1;$i<=3;$i++):?>

                <div class="frow">
                    <div class="col4">
                        <label>Principal Name</label>
                        <input type="text" name="data[dis_princial][name<?php echo $i?>]" value="<?php echo @$dis_princial['name'.$i]?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Ownership %</label>
                        <input type="text" name="data[dis_princial][ownership<?php echo $i?>]" value="<?php echo @$dis_princial['ownership'.$i]?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Cellphone</label>
                        <input type="text" name="data[dis_princial][phone<?php echo $i?>]" value="<?php echo @$dis_princial['phone'.$i]?>" maxlength="255" />
                    </div>
                    <div class="col4">
                        <label>Email</label>
                        <input type="text" name="data[dis_princial][email<?php echo $i?>]" value="<?php echo @$dis_princial['email'.$i]?>" maxlength="255" />
                    </div>
                </div>

			<?php endfor?>


            <div class="frow">
                <h4>Payment Preference</h4>
            </div>
            <div class="frow linline lcheckboxes">
                <label><input type="radio" name="data[dis_payment_type]" class="payment_check" value="Check" <?php checked('Check',@$data['dis_payment_type'])?> /> Check</label>
                <label><input type="radio" name="data[dis_payment_type]" value="ACH" <?php checked('ACH',@$data['dis_payment_type'])?> /> ACH</label>
            </div>
            <div class="frow payment_info">
                <div class="col4">
                    <label>Bank Name</label>
                    <input type="text" name="data[dis_payment_bank_name]" value="<?php echo @$data['dis_payment_bank_name']?>" maxlength="255"/>
                </div>
                <div class="col4">
                    <label>ABA</label>
                    <input type="text" name="data[dis_payment_aba]" value="<?php echo @$data['dis_payment_aba']?>" maxlength="100"/>
                </div>
                <div class="col4">
                    <label>Account Number</label>
                    <input type="text" name="data[dis_payment_account_number]" value="<?php echo @$data['dis_payment_account_number']?>" maxlength="255"/>
                </div>
                <div class="col4">
                    <label>Account Name</label>
                    <input type="text" name="data[dis_payment_account_name]" value="<?php echo @$data['dis_payment_account_name']?>" maxlength="255"/>
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Commission</label>
                    <input type="text" name="data[dis_comission]" value="<?php echo $data['dis_comission']?>"/>
                    <small>C - Commercial, A - medicAid. M -Medicare, W-Workmen, O -Other</small>
                </div>
                <div class="col3">
                    <label>Start Date</label>
                    <input type="text" name="data[dis_sdate]"  class="hasdate" value="<?php echo $data['dis_sdate']?>"/>
                </div>
                <div class="col3">
                    <label>End date</label>
                    <input type="text" name="data[dis_edate]" class="hasdate" value="<?php echo $data['dis_edate']?>"/>
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Tox</label>
                    <input type="hidden" name="data[dis_tox]" value="0"/>
                    <input type="checkbox" name="data[dis_tox]" value="1" <?php checked($data['dis_tox'])?>"/>
                </div>
                <div class="col4">
                    <label>Blood</label>
                    <input type="hidden" name="data[dis_blood]" value="0"/>
                    <input type="checkbox" name="data[dis_blood]"  value="1" <?php checked($data['dis_blood'])?>"/>
                </div>
                <div class="col4">
                    <label>DNA</label>
                    <input type="hidden" name="data[dis_dna]" value="0"/>
                    <input type="checkbox" name="data[dis_dna]" value="1" <?php checked($data['dis_dna'])?>"/>
                </div>
                <div class="col4">
                    <label>Allergy</label>
                    <input type="hidden" name="data[dis_allergy]" value="0"/>
                    <input type="checkbox" name="data[dis_allergy]" value="1" <?php checked($data['dis_allergy'])?>"/>
                </div>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_action" value="1"/>
                <input type="hidden" name="data[dis_ID]" value="<?php echo @$data['dis_ID']?>"/>
            </div>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            $('[name="data[dis_payment_type]"]').change(function(){
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
	$id = $_REQUEST['id'];
	$data = $wpdb->get_row("select * from ci_distributors where dis_ID = {$id}  ", ARRAY_A);
	?>
    <div class="wrap">
        <h2>Distributor <?php echo @$data['dis_name']?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id)) ?>" class="add-new-h2">Edit</a>

            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Distributors</a></h2>
		<?php Tr_Session_Class::show()?>
        <form method="post" class="need_validate">
            <div class="frow">
                <h4>Marketing Groups</h4>
                <ul id="dis_sale_groups" class="list_user_members">
					<?php
					$groups = $wpdb->get_results("select * from ci_marketing_groups where gr_distributor_ID = {$id}");
					foreach($groups as $gr)
					{
						?>
                        <li><input type="hidden" name="data[dis_sale_groups][]" value="<?php echo $gr->gr_ID?>"/> <?php echo $gr->gr_name?><a class="remove left"></a></li>
						<?php
					}
					?>
                </ul>
                <input type="text" class="marketing_groups_auto" rel="dis_sale_groups" placeholder="Search Marketing Group" style="width:250px"/>
            </div>

            <div class="frow">
                <h4>Marketing Representative</h4>
                <ul id="dis_reps" class="list_user_members">
					<?php
					$reps = $wpdb->get_results("select * from ci_lab_reps where rep_distributor_ID = {$id}");
					foreach($reps as $rep)
					{
						?>
                        <li><input type="hidden" name="data[dis_reps][]" value="<?php echo $rep->rep_ID?>"/> <?php echo $rep->rep_first.' '.$rep->rep_last?><a class="remove left"></a></li>
						<?php
					}
					?>
                </ul>
                <input type="text" class="lab_rep_auto" rel="dis_reps" placeholder="Search Marketing Representative" style="width:250px"/>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_action_members" value="1"/>
                <input type="hidden" name="data[dis_ID]" value="<?php echo @$data['dis_ID']?>"/>
            </div>
        </form>
    </div>
    <script>
        jQuery(function ($) {
            $('.marketing_groups_auto').autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=marketing_groups_auto&target='+$(this).attr('rel'),
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
                            $('.list_user_members input').each(function(){
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
            $('.lab_rep_auto').autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=lab_rep_auto&target='+$(this).attr('rel'),
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
                            $('#dis_reps input').each(function(){
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
            });
        })
    </script>
	<?php
}else{
	?>
    <div class="wrap">
        <h2>Distributors
            <a href="<?php Tr_Base_Class_V6::link(array('act'=>'add','id'=>''))?>" class="add-new-h2">Add New</a></h2>
		<?php Tr_Session_Class::show()?>

		<?php
		if(isset($_GET['orderby'])){
			$short=$_GET['orderby'];
		}else{
			$short='dis_ID';
		}

		$order = isset($_REQUEST['order']) && $_REQUEST['order']=='desc'?'desc':'asc';
		$orderby = "order by $short $order";
		$distributors = $wpdb->get_results("select * from ci_distributors  {$orderby}");
		?>

        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                <tr>
                    <th style="width:50px">#</th>
					<?php tr_tbl_sort('dis_name','Name')?>
                    <th>Email</th>
					<?php tr_tbl_sort('dis_state','State')?>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ($distributors as $i=> $data):
					$active=$data->dis_activer;
					if($active=='0'){
						$txt_action='delete';
						$txt_action_show='Inactivate';
					}else{
						$txt_action='active';
						$txt_action_show='Activate';
					}
					?>
                    <tr <?php if($active=='1'){ echo 'class="active"';}?> >
                        <td><?php echo $data->dis_ID ?></td>
                        <td><?php echo $data->dis_name ?></td>
                        <td><?php echo $data->dis_email ?></td>
                        <td><?php echo $data->dis_state ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $data->dis_ID)) ?>">Add Members</a> -
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $data->dis_ID)) ?>">Edit</a> -
                            <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => $txt_action, 'id' => $data->dis_ID)) ?>"><?php echo $txt_action_show;?></a>
                        </td>
                    </tr>
				<?php endforeach;?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        jQuery(function($){
            $('a.delete').click(function(){
                if(confirm('Do you want inActive/Active?')==false)return false;
            })
        })
    </script>
	<?php
}
?>

