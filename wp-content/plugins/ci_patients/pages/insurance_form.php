<?php
if(empty($ins1['ins_relation']))
{
	$ins1['ins_relation'] = 'Self';
}
?>
<div id="use_required_ins">

	<?php
	if($form_options['show_patient_multi'])
	{
		echo '<h4>Member #<span class="mpnum">1</span></h4>';
	}
	?>

    <div class="frow">
        <div class="col3">
            <label>Last Name</label>
            <input type="text" class="cfname has_required" name="ins1[ins_last]"
                   value="<?php echo isset($ins1['ins_last'])? $ins1['ins_last']:'' ?>" required=""/>
        </div>
        <div class="col3">
            <label>First Name</label>
            <input type="text" class="cfname has_required" name="ins1[ins_first]"
                   value="<?php echo isset($ins1['ins_first'])? $ins1['ins_first']:'' ?>" required=""/>
        </div>
        <div class="col3 ilabel">
            <label title="<?php echo $ins1['ins_relation']?>">Relation to Patient?</label><br>
			<?php tr_radio( 'ins1[ins_relation]', array( 'Self'   => 'Self',
			                                             'Parent' => 'Parent',
			                                             'Spouse' => 'Spouse'
			), isset($ins1['ins_relation'])?$ins1['ins_relation']:'', array( 'attr' => 'required' ) ) ?>

            <div  id="dob_ins1" <?php if ( @$ins1['ins_relation'] == 'Self' || empty($ins1['ins_relation']))
				echo 'style="display: none"' ?>>
                <label><span>Parent</span> DOB</label>
                <input type="text" class="hasdate birthday" name="ins1[ins_dob]"
                       value="<?php echo isset($ins1['ins_dob'])? $ins1['ins_dob']:'' ?>"/>
            </div>
        </div>
    </div>
    <div class="frow">
        <div class="col3 required">
            <label>Insurance Company Name</label>
            <?php if($form_options['ins_dropdown']){?>
                <select name="ins1[ins_company]" class="ins_js_select2" required>
                    <option value="">Select</option>
                    <option value="Other">Other</option>
		            <?php
		            $insurances = $wpdb->get_results("select * from ci_payer_list order by payer_name asc");
		            foreach($insurances as $ins)
		            {
			            $selected = $ins->payer_name == $ins1['ins_company']? 'selected':'';
			            echo '<option value="'.$ins->payer_name.'" '.$selected.'>'.$ins->payer_name.'</option>';
			            if(!empty($ins->payer_name_search))
			            {
				            echo '<option value="'.$ins->payer_name.'" '.$selected.'>'.$ins->payer_name_search.' ('.$ins->payer_name.')</option>';
			            }
		            }
		            ?>
                </select>
                <script src="<?php echo TRCPDD_URL.'/js/select2.min.js?'.TRCPDD_VS?>"></script>
                <link  href="<?php echo TRCPDD_URL.'/css/select2.min.css?'.TRCPDD_VS?>" type='text/css' media='all' rel='stylesheet'/>
                <script>
                    jQuery(function ($) {
                        $('.ins_js_select2').select2();
                    })
                </script>
            <?php }else{?>
            <input type="text" class="cfname ins_company_auto" name="ins1[ins_company]"
                   value="<?php echo isset($ins1['ins_company'])?$ins1['ins_company']:'' ?>" required=""/>
            <?php }?>
        </div>


        <div class="col3">
            <label>Member ID</label>
            <input type="text" name="ins1[ins_member_id]" value="<?php echo isset($ins1['ins_member_id'])?$ins1['ins_member_id']:'' ?>"
                   required=""/>
        </div>
        <div class="col3">
            <label>Group #</label>
            <input type="text" name="ins1[ins_group_id]"
                <?php if(isset($form_options['ins_group_required']) && $form_options['ins_group_required'])echo'required'?>
                   value="<?php echo isset($ins1['ins_group_id'])? $ins1['ins_group_id']:'' ?>"/>
        </div>
    </div>
	<?php if(isset($form_options['enable_insurance_photo']) && $form_options['enable_insurance_photo']) { ?>
        <div class="<?php if(isset($form_options['insu_cbtsyellow']) && $form_options['insu_cbtsyellow'])echo 'bgyellow'?>" style="margin-top:20px;">
            <div class="frow" >
                <h5>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h5>
            </div>
			<?php if(isset($form_options['insu_textac']) && !empty($form_options['insu_textac'])){
				echo '<div class="frow">'. $form_options['insu_textac'].'</div>';
			}else{
			    ?>
                <div class="frow"> (Optional: If not uploaded below, cards required at appointment.)</div>
                <div class="frow">
                    By clicking the buttons below, you will be prompted to insert a photo of both the patient current insurance card
                    and photo ID. The photos can be taken with your tablet or phone if you are using those to fill in this form.
                </div>
            <?php
            }
			?>
            <div class="frow blabel">
                <div class="col4">
                    <div class="card_file" data-id="ins_card_front">
                        <a class="button samebar" href="#">Insurance Card Front Photo</a>
                    </div>
                </div>
                <div class="col4">
                    <div class="card_file" data-id="ins_card_back">
                        <a class="button samebar" href="#">Insurance Card Back Photo</a>
                    </div>
                </div>

                <div class="col4">

                    <div class="card_file" data-id="patient_id_front">
                        <a class="button samebar" href="#">ID Card Front Photo</a>
                    </div>
                </div>
                <div class="col4">

                    <div class="card_file" data-id="patient_id_back">
                        <a class="button samebar" href="#"> ID Card Back Photo</a>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}
	?>

</div>

<div id="multi_insurance"></div>

<style>
    .uploadajax{
        display: none !important;
    }
</style>