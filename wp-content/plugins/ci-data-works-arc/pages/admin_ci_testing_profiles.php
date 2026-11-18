<?php
include_once(TRCPDD_PATH.'pages/formdata.php');
global $wpdb;
$search = $_REQUEST['s'];

if(isset($_REQUEST['form_action'])){

	$cus = $_POST['cus'];
	if($cus['cus_ID']>0)
	{
		$data = array(
			'cus_injections' => $cus['cus_injections'],
			'cus_confirm_positive'=>$cus['cus_confirm_positive'],
			'cus_testing_profile' => $cus['cus_testing_profile'],
			'cus_individuals' => $cus['cus_individuals']
		);
		$cus_ID = $cus['cus_ID'];
		$wpdb->update('ci_custom_providers',$data,array('cus_ID'=>$cus_ID));
		Tr_Session_Class::add('Saved Testing Profile');
	}else{
		Tr_Session_Class::add_error('Error. Please enter doctor name');
	}

}
?>
<h2>Testing Profiles</h2>
<?php Tr_Session_Class::show() ?>

<form method="post">
    <div class="wraptbl">
        <table class="wp-list-table widefat striped pages blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:200px">Doctor</th>
                <th style="width:100px">Point of Care</th>
                <th>Profile</th>
                <th>CUSTOM</th>
                <th>Injection</th>
            </tr>
            </thead>
            <tbody>

            <tr>
                <td>
                    <input type="text" name="cus[name]" class="full custom_profile_auto" value="<?php echo tr_trim_name(@$cus->cus_last . ', ' . @$cus->cus_first) ?>" />
                    <input type="hidden" name="cus[cus_ID]" value="<?php echo @$cus->cus_ID?>"/>
                </td>
                <td>
                    <input type="hidden" name="cus[cus_confirm_positive]" value="0"/>
                    <input type="checkbox" name="cus[cus_confirm_positive]" value="1" <?php checked(@$cus->cus_confirm_positive) ?>/>
                </td>
                <td>
					<?php
					$panels = array(
						'Basic' => 'Low Risk',
						'Standard' => 'Moderate Risk',
						'Highrisk' => 'High Risk',
						'Comprehensive' => 'Comprehensive',
						'Psych' => 'Psych',
						'Custom' => 'Custom'
					);
					tr_select_option('cus[cus_testing_profile]',$panels);
					?>

                </td>
                <td>
                    <input type="text" name="cus[cus_individuals]" class="full" value="<?php echo @$cus->cus_individuals ?>"/>
                </td>
                <td>
                    <input type="text" name="cus[cus_injections]" class="full"value="<?php echo $cus->cus_injections ?>"/>
                </td>
            </tr>

            </tbody>
        </table>
    </div>

    <div class="frow">
        <input type="submit" value="Submit" class="button-primary"/>
        <input type="hidden" name="form_action" value="save"/>
    </div>
</form>
<script>
    jQuery(function($){
        $('.custom_profile_auto').each(function(){
            $(this).autocomplete({
                source: ajaxurl+'?action=ajax&autocomplete=custom_profile_auto&cli_id=-1',
                minLength: 2,
                select: function( event, ui ) {
                    if(ui.item)
                    {
                        $('[name="cus[cus_ID]"]').val(ui.item.id);
                        $('[name="cus[cus_ID]"]').data('name',ui.item.value);
                        $('[name="cus[cus_testing_profile]"]').val(ui.item.cus_testing_profile);
                        $('[name="cus[cus_individuals]"]').val(ui.item.cus_individuals);
                        $('[name="cus[cus_injections]"]').val(ui.item.cus_injections);
                        if(ui.item.cus_confirm_positive=='1'){
                            $('[name="cus[cus_confirm_positive]"]').prop('checked',true);
                        }else{
                            $('[name="cus[cus_confirm_positive]"]').removeAttr('checked');
                        }
                    }else{
                        $('[name="cus[cus_ID]"]').val(ui.item.id);
                        $('[name="cus[cus_ID]"]').data('name',ui.item.value);
                        $('[name="cus[cus_testing_profile]"]').val(ui.item.cus_testing_profile);
                        $('[name="cus[cus_individuals]"]').val(ui.item.cus_individuals);
                        $('[name="cus[cus_injections]"]').val(ui.item.cus_injections);
                        if(ui.item.cus_confirm_positive=='1'){
                            $('[name="cus[cus_confirm_positive]"]').prop('checked',true);
                        }else{
                            $('[name="cus[cus_confirm_positive]"]').removeAttr('checked');
                        }
                    }
                }
            });
        })
    })
</script>
