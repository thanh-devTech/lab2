<?php
global $wpdb;
add_thickbox();

$user_id = get_current_user_id();
$user = wp_get_current_user();

if(isset($_POST['dob']))
{
	update_user_meta($user_id,'_dob',$_POST['dob']);
}

$dob = get_user_meta($user_id,'_dob',true);
?>
<div class="frow" style="width:400px;max-width: 100%;margin:20px auto">
	<form method="post">
		<p>Please enter your Birthday: </p>
		<div class="frow">

			<br>
			<input type="text" class="has_date" name="dob" value="" style="width:200px" required/>

			<input type="submit" class="button-primary" value="Update"/>
		</div>


	</form>
</div>

<script>
    jQuery(function ($) {
        $('.has_date').dateDropdowns({displayFormat:'mdy',minAge:0,daySuffixValues: ['', '', '', '']});
    })
</script>
