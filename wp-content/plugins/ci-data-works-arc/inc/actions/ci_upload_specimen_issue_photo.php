<?php
?>
<form method="post" enctype="multipart/form-data">
    <label>Photo:</label>
    <input type="file" name="filename" value=""/>
    <input type="submit" class="button-primary" value="Upload">
    <input type="hidden" name="tr_action" value="<?php echo $_REQUEST['tr_action']?>"/>
</form>
