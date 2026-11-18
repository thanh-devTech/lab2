<form method="post" id="form_move_assen" style="width:300px;margin:20px auto">
	<div class="frow">
		<label>New Batch#</label>
		<input type="text" id="new_batch_id" name="new_batch_id" value="<?php echo isset($_POST['new_batch_id']) ? $_POST['new_batch_id'] : '' ?>" required="" />
	</div>
	<div class="frow">
		<label>Accession Number</label>
		<input type="text" name="assen" id="batch_assen" value="" required="" />
	</div>
	<input type="submit" class="button" value="Move Sample" />
	<input type="hidden" name="tr_action" value="ci_move_assen_batch_id">
	</div>
</form>