<?php
if(isset($_POST['options']))
{
	$options = tr_pdd_stripslashes_deep($_POST['options']);
	update_option('trpatient_welcome_settings',$options);
}
$options = get_option('trpatient_welcome_settings',array());
?>
<div class="wrap">
	<h2>Welcome Page Settings</h2>
	<br><br>
	<form method="post">


		<div class="frow">
			<label>Welcome Page</label>
			<?php wp_editor( $options['welcome'], 'welcome', array( 'media_buttons' => true,'textarea_name'=>'options[welcome]' ) );?>
		</div>
		<br><br>
		<div class="frow">
			<input type="submit" class="button-primary" value="Save"/>
		</div>
	</form>
</div>


