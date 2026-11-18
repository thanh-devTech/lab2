<li class="frow blabel">
    <div class="col6 bold">
        <span class="drug_name"><?php echo $logic['medication']?></span>&nbsp;
    </div>
    <div class="col6">
        <input type="text" class="detected" name="drug_logic[<?php echo $logic['medication']?>][detected]" value="<?php echo $logic['detected']?>" />
    </div>
    <div class="col6">
        <input type="text" class="detected_oral" name="drug_logic[<?php echo $logic['medication']?>][detected_oral]" value="<?php echo $logic['detected_oral']?>" />
    </div>
    <div class="col3">
        <textarea class="comments" name="drug_logic[<?php echo $logic['medication']?>][comment]" ><?php echo $logic['comment']?></textarea>
    </div>
    <div class="col6">
        <label><input type="checkbox" name="drug_logic[<?php echo $logic['medication']?>][me_show_req]" value="1" <?php checked($logic['me_show_req'])?> />
        Show in Forms?</label>
    </div>
</li>