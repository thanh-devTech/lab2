<?php
wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );
echo '</p></td></tr>'; //this for profile picture inside
$sign_dir = tr_get_signature_dir();
?>
<tr>
    <th>Signature</th>
    <td>
        <p>

        <div class="card_file">
            <?php
            $signature_img = '';
            $signature = get_user_meta($profile_user->ID,'_signature',true);
            
            if (!empty($signature) && file_exists($sign_dir['path'] . '/' . $signature) && filesize($sign_dir['path'] . '/' . $signature) > 0) {
                $signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $signature . '?' . time() . '" style="width:300px"/>';
            }
            echo '<div class="signaturepad" style="' . (empty($signature_img) ? '' : 'display:none') . '">';
            echo tr_signature(array('name' => '_signature', 'required' => false, 'clear' => false));
            echo '</div>';
            ?>
            <a class="button selectfile" href="#">Choose Signature Image</a>
            <a class="button-primary remove" rel="_signature">Clear</a>
            <br>
            <span class="view"><?php echo $signature_img ?></span>
            <input type="file" name="file_signature" accept="image/*" />
            <input type="hidden" name="img_signature" value="<?php echo $signature ?>" />
        </div>

        </p>
    </td>
</tr>
<script>
    jQuery(function($){
        $('form#your-profile').attr('enctype','multipart/form-data');
    })
</script>
