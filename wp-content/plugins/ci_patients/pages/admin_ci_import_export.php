<?php
global $wpdb;
$keys = array(
	'main' => 'trpatient_settings',
	'form' => 'trpatient_product_settings',
	'concierge' => 'trpatient_concierge_settings',
	'meijer' => 'trpatient_meijer_settings',
	'canada' => 'trpatient_canada_settings',
);
if($act=='import'){
	$data = file_get_contents($_FILES['importfile']['tmp_name']);
	$json = json_decode($data,true);
	foreach($keys as $k => $key)
	{
		if(isset($json[$k]) && is_array($json[$k])){
			update_option($key, $json[$k]);
		}
	}
	Tr_Session_Class::add('Imported Success');
}
else if($act=='export'){
	$data = array();
	foreach($keys as $k => $key)
	{
		if($k=='main'){
			if(!$_REQUEST['import_main']){
				continue;
			}
		}else{
			if(!$_REQUEST['import_form']){
				continue;
			}
		}
		$data[$k] = get_option($key);
		if(!is_array($data[$k]))
		{
			unset($data[$k]);
		}
	}
	$filename = 'patients_settings.json';
	header("Content-disposition: attachment; filename={$filename}");
	header("Content-Type: text/json");
	wp_send_json($data);
	exit;
}
?>
<h2>Import/Export </h2>
<?php Tr_Session_Class::show()?>
<div class="frow" style="width:400px;border:1px dashed #ddd;padding:20px;margin:20px auto">
	<a class="button" href="<?php Tr_Base_Class_V6::link(array('act'=>'export','trajax'=>1))?>">Export</a>
</div>
<div class="frow" style="width:400px;border:1px dashed #ddd;padding:20px;margin:20px auto">
	<h2>Import</h2>
	<form method="post" enctype="multipart/form-data">

		<div class="frow">
			<label>Import File</label>
			<input type="file" name="importfile"/>
		</div>
		<div class="frow">
			<label><input type="checkbox" value="1" name="import_main"/> Main data </label><br>
			<label><input type="checkbox" value="1" name="import_form"/> Form data </label>
		</div>
		<div class="frow">
			<input type="submit" class="button" value="Import"/>
			<input type="hidden" name="act" value="import"/>
		</div>
	</form>

</div>