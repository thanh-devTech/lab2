<?php
global $wpdb;

if (isset($_FILES['movefile'])) {
	$ref_labid = $_POST['ref_lab_id'];
	$data = trpdd_get_data($_FILES['movefile']['tmp_name'], 'csv', null, true, true);
	$count = 0;
	foreach ($data['data'] as $row) {
		$assen = trim($row[0]);
		if (!empty($assen)) {
			foreach(tr_pdd_get_batch_types() as $btype)
			{
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$assen}' ");
				if ($mid > 0) {
					Tr_Session_Class::add('Moved: ' . $assen);
					$wpdb->update($batch_tbl, array('ref_lab_ID' => $ref_labid), array('mid' => $mid));
					$count++;
					break;
				}
			}
			
		}
	}

	Tr_Session_Class::add("Moved ".$count.' sample(s)');
}

$ref_labs = $wpdb->get_results("select * from ci_labs 
	where (lab_is_ref=1 or lab_is_ref=2)
	 order by lab_name asc");

?>
<div class="wrap">
	<h2>Move Ref Lab</h2>
	<?php Tr_Session_Class::show(); ?>

	<form method="POST" enctype="multipart/form-data">
		<div class="frow">
			<div class="col2">
				<label>Ref Lab</label>
				<select name="ref_lab_id">
					<?php
					foreach ($ref_labs as $ref_lab) {
						echo '<option value="' . $ref_lab->lab_ID . '">' . $ref_lab->lab_name . '</option>';
					}
					?>
				</select>
			</div>
		</div>
		<div class="frow">
			<label>File:</label>
			<input type="file" name="movefile" required />
		</div>

		<div class="frow">
			<input type="submit" class="button" value="UPload" />
		</div>
	</form>
</div>