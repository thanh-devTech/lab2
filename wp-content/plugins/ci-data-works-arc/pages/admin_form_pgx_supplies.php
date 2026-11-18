<?php
if(isset($_POST['pgx'])){

    $pgx = $_POST['pgx'];
    $to = tr_get_option('ci_pdf_data','form_pgx_mail' );
    $subject = tr_get_option('ci_pdf_data', 'form_pgx_subject');

    $msg = "<strong>Distributor Name:</strong><br>
            {$pgx['distributor_name']}
            <br><br>
            <strong>Distributor GROUP:</strong><br>
            {$pgx['distributor_group']}
            <br><br>
            <strong>Distributor Email:</strong><br>
            {$pgx['distributor_email']}
            <br><br>
            <strong>Distributor Phone:</strong><br>
            {$pgx['distributor_phone']}
            <br><br>
            <strong>Physician/Clinic Name: </strong><br>
            {$pgx['physician_clinic_name']}
            <br><br>
            <strong>Shipping Contact Name:</strong><br>
            {$pgx['shipping_name']}
            <br><br>
            <strong>Shipping Address:</strong><br>
            {$pgx['shipping_address']}
            <br><br>
            <strong>Physician/Clinic Phone:</strong><br>
            {$pgx['physician_clinic_phone']}
            <br><br>
            <strong>[".($pgx['testing_kits_25']? 'X':' ')."] 25 Testing Kits</strong>
            <br><br>
            <strong>[".($pgx['testing_kits_50']? 'X':' ')."] 50 Testing Kits</strong>
            <br><br>
            <strong>[".($pgx['slim_jims_25']? 'X':' ')."] 25 Slim Jims</strong>
            <br><br>
            <strong>[".($pgx['drug_ref_guides_10']? 'X':' ')."] 10 Drug Reference Guides</strong>
            <br><br>
            <strong>Special Instructions:</strong>
            <br>{$pgx['special_intructions']}<br><br></div>";

    $result = apply_filters('tr-captcha-validate',$_POST['captcha_code']);
    if($result!==true)
    {
        echo  "<strong>".__('ERROR')."</strong>: {$result}";
    }else
    {
        if(empty($to))
        {
            $to = 'pntrinh29@gmail.com';
        }
        if(empty($subject))
        {
            $subject = 'PGX form from '.get_bloginfo('home_url');
        }
        $headers = 'From: '.get_bloginfo('admin_email') . "\r\n";
        $headers .= 'Reply-To: ' . get_bloginfo('admin_email') . "\r\n";
        $headers .= "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $rs = wp_mail($to, $subject,$msg,$headers );
        if($rs)
        {
            echo '<h2>Your Supplies Order has been sent Successfully!</h2><br><br>';
        }else{
            echo '<h2>Error while send mail. </h2><br><br>';
        }
        return;
    }
}
?>
<link  href="<?php echo TRCPDD_URL.'css/front.min.css?'.TRCPDD_VS?>" type='text/css' media='all' rel='stylesheet'/>
<div class="wrap">
    <?php if(!isset($_hide_title)):?>
        <h2>PGx Supplies</h2>
    <?php endif;?>

    <form method="post">
        <div class="frow">
            <label>Distributor Name: *</label>
            <input type="text" name="pgx[distributor_name]" value="<?php echo $pgx['distributor_name']?>" required>
        </div>
        <div class="frow">
            <label>Distributor Group: *</label>
            <input type="text" name="pgx[distributor_group]" value="<?php echo $pgx['distributor_group']?>" required>
        </div>
        <div class="frow">
            <label>Distributor Email: * </label>
            <input type="email" name="pgx[distributor_email]" value="<?php echo $pgx['distributor_email']?>" required>
        </div>
        <div class="frow">
            <label>Distributor Phone: * </label>
            <input type="tel" name="pgx[distributor_phone]" value="<?php echo $pgx['distributor_phone']?>" required>
        </div>
        <div class="frow">
            <label>Physician/Clinic Name: * </label>
            <input type="text" name="pgx[physician_clinic_name]" value="<?php echo $pgx['physician_clinic_name']?>" required>
        </div>
        <div class="frow">
            <label>Shipping Contact Name: *  </label>
            <input type="text" name="pgx[shipping_name]" value="<?php echo $pgx['shipping_name']?>" required>
        </div>
        <div class="frow">
            <label>Shipping Address: * </label>
            <input type="text" name="pgx[shipping_address]" value="<?php echo $pgx['shipping_address']?>" required>
        </div>
        <div class="frow">
            <label>Physician/Clinic Phone: * </label>
            <input type="text" name="pgx[physician_clinic_phone]" value="<?php echo $pgx['physician_clinic_phone']?>" required>
        </div>
        <div class="frow blabel">
            <label>
                <input type="checkbox" name="pgx[testing_kits_25]" value="25 Testing Kits" <?php if($pgx['testing_kits_25']=='25 Testing Kits')echo 'checked'?>> 25 Testing Kits
            </label>
            <label>
                <input type="checkbox" name="pgx[testing_kits_50]" value="50 Testing Kits" <?php if($pgx['testing_kits_50']=='50 Testing Kits')echo 'checked'?>> 50 Testing Kits
            </label>
            <label>
                <input type="checkbox" name="pgx[slim_jims_25]" value="25 Slim Jims" <?php if($pgx['slim_jims_25']=='25 Slim Jims')echo 'checked'?>> 25 Slim Jims
            </label>
            <label>
                <input type="checkbox" name="pgx[drug_ref_guides_10]" value="10 Drug Reference Guides" <?php if($pgx['drug_ref_guides_10']=='10 Drug Reference Guides')echo 'checked'?>> 10 Drug Reference Guides
            </label>
        </div>
        <div class="frow">
            <label>Special Instructions: </label>
            <textarea name="pgx[special_intructions]"><?php echo $pgx['special_intructions']?></textarea>
        </div>

        <?php do_action('tr-captcha',1);?>
        <div class="frow actions">
            <input type="submit" class="button btn qbutton" value="Send form">
        </div>
    </form>
</div>