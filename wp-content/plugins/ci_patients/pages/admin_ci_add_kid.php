<?php
$pa_options = get_option('trpatient_settings',array());
if(!isset($pa_options['disable_18kid']))
{
	$pa_options['disable_18kid'] = false;
}

$user_id = get_current_user_id();
if(isset($_POST['kid'])){
    $return = array('status'=>'ok','alert'=>'');

    $kid = $_POST['kid'];

    //check age < 18
    $age = date('Y') - date('Y',strtotime($kid['birthday']));
    if($age<=19 || $pa_options['disable_18kid'])
    {
	    $patient_id = trpa_patient_id_from_code($kid['patient_code'],$kid['first_name'],$kid['last_name'],$kid['birthday']);
	    if(empty($patient_id))
        {
	        $patient_id = trpa_patient_id_from_code($kid['patient_code'],$kid['last_name'],$kid['first_name'],$kid['birthday']);
	        if($patient_id>0)
            {
	            $lastname = $kid['last_name'];
                $kid['last_name'] = $kid['first_name'];
                $kid['first_name'] = $lastname;
            }
        }
	    if($patient_id>0)
	    {
		    $kids = get_user_meta($user_id,'_patient_kids',true);
		    if(!is_array($kids))$kids = array();
		    $kids[$patient_id] = $kid;
		    update_user_meta($user_id,'_patient_kids',$kids);
		    $return['alert'] = '<div class="success">Added Successfully!</div>';
		    $return['redirect'] = Tr_Base_Class_V6::link(array('tab'=>'patient_'.$patient_id,'act'=>'','trajax'=>''),false);
	    }else{
		    $return['status']='error';
		    $return['alert'] = '<div class="error"><b>Error</b>: Please correct your name, dob or patient code!</div>';
	    }
    }
    else{
	    $return['status']='error';
	    $return['alert'] = '<div class="error"><b>Error</b>: Only allow kid\'s Age under 18!</div>';
    }


    if(isset($_REQUEST['trajax'])){
        wp_send_json($return);
    }else{
        if($return['status']=='ok'){
            Tr_Session_Class::add($return['alert']);
            wp_redirect($return['redirect']);
        }else{
            Tr_Session_Class::add_error($return['alert']);
        }
        return;
    }
}
?>
<div class="wrap">
	<form method="post" id="add_new_kid_form">
        <div class="alert"></div>
        <div class="frow">
            <label>Last Name</label>
            <input type="text" name="kid[last_name]" value="" required/>
        </div>
		<div class="frow">
			<label>First Name</label>
			<input type="text" name="kid[first_name]" value="" required/>
		</div>

        <div class="frow dropd3">
            <label>Birthday</label>
            <input type="text" class="has_date" name="kid[birthday]" value="" required/>
        </div>
        <?php if(!$pa_options['disable_fmas']){?>
		<div class="frow">
			<label><?php echo ($pa_options['show_kid_sample_text'])?'Sample ID':'Patient Code or Accession Code'?></label>
			<input type="text" name="kid[patient_code]" value="" <?php if(!$pa_options['disable_fmas'])echo 'required'?>/>
		</div>
        <?php }?>
		<div class="frow submit">
			<input type="submit" class="button-primary" value="Submit"/>
		</div>

		<input type="hidden" name="act" value="addkid"/>
	</form>
    <script>
        jQuery(function ($) {
            $('.has_date').dateDropdowns({displayFormat:'mdy',minAge:0,daySuffixValues: ['', '', '', '']});
        })
    </script>
</div>

<?php
exit;