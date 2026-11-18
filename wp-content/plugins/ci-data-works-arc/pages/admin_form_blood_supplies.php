<?php

$today = Tr_Base_Class_V4::date('m/d/Y');
$listing = array(
    'Serum Separator Tube' => '25',
    'Lavender Top EDTA' => '25',
    'Blue Top (Sodium Citrate)' => 25,
    'Red Top (Plain)' => 25,
    'Grey Top (Sodium Fluoride/Pottasium)' => 25,
    'Grey Top, Glass( For Drug Screen)' => 25,
    'Yellow Top (ACD)' => 25,
    'Dark Blue' => 25,
    'Green Top (Lithium Heparin)' => 25,
    'Green Top (Soduim Heparin )' => 25,

    ' BLOOD COLLECTION NEEDLES' => false,
    'Butterfly Needle 23 G'=> 50,
    'Straight Needle 21G 1.25"' => 48,
    'Straight Needle 22G 1.25"' => 48,
    'Vacutainer' => 250,


    ' TRANSPORT MATERIAL' => false,
    'Transfer tubes and Caps' => 'EA',
    'Frozen Transport Tubes' => 'EA',
    'Specimen Bags' => 100,

    'MISCELLANEOUS SUPPLIES' => false,
    'Alcohol Pads' => 200,
    'Tourniqurt' => 'EA',
    'Bandages ' => 100,
    'Paper Tape ' => 'EA',
    'UPS Medium Kits' => 25,
    'UPS Medical Packs' => 25,
    'UPSshipping labels' => 20
);



if(isset($_POST['pgx'])){

    $pgx = $_POST['pgx'];
    $to = tr_get_option('ci_pdf_data','form_blood_mail' );
    $subject = tr_get_option('ci_pdf_data', 'form_blood_subject');

    $listing_post = '
<style>table, th, td {
    border: 1px solid black;
}</style>
<table style="width:100%;border:1px solid;	border-collapse: collapse;">
<tr>
                <th> Blood Specimen Supplies </th>
                <th>Order By (Pack Size)</th>
                <th>QTY</th>
            </tr>';
    foreach($listing as $text =>$vl)
    {
        $k = sanitize_title($text);
        if($vl===false)$text = '<strong>'.$text.'</strong>';
        $listing_post.= '<tr>
                        <td>'.$text.'</td>
                        <td>'.$vl.'</td>
                        <td>'.$pgx['listing'][$k].'</td>
                        </tr>';
    }
    $listing_post .= '</table>';

    $msg = "<strong>Today’s Date:</strong><br>
            ".$today."
            <br><br>
            <strong>Practice Name:</strong><br>
            {$pgx['practice_name']}
            <br><br>
            <strong>Ordered By:</strong><br>
            {$pgx['ordered_by']}
            <br><br>
            <strong>Ship To Address:</strong><br>
            {$pgx['ship_address']}
            <br><br>
            <strong>City:</strong><br>
            {$pgx['city']}
            <br><br>
            <strong>State:</strong><br>
            {$pgx['state']}
            <br><br>
            <strong>Zip:</strong><br>
            {$pgx['zip']}
            <br><br>
            <strong>phone:</strong><br>
            {$pgx['phone']}
            <br><br>
            <strong>Fax:</strong><br>
            {$pgx['fax']}
            <br><br>
            <strong>Need Delivery By:</strong><br>
            {$pgx['need_delivery']}
            <br><br>
            
         
            {$listing_post}
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
            $subject = 'Blood Supplies form from '.get_bloginfo('name');
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
        <h2>Blood Supplies</h2>
    <?php endif;?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <label>Today’s Date</label>
                <input type="text"  value="<?php echo $today?>" readonly>
            </div>
            <div class="col2">
                <label>Practice Name:</label>
                <input type="text" name="pgx[practice_name]" value="<?php echo $pgx['practice_name']?>" required>
            </div>
        </div>
        <div class="frow">
            <div class="col2">
                <label>Ordered By:</label>
                <input type="text" name="pgx[ordered_by]" value="<?php echo $pgx['ordered_by']?>" required>
            </div>
            <div class="col2">
                <label>Ship To Address:</label>
                <input type="tel" name="pgx[ship_address]" value="<?php echo $pgx['ship_address']?>" required>
            </div>
        </div>
        <div class="frow">
            <div class="col2">
                <label>City:</label>
                <input type="text" name="pgx[city]" value="<?php echo $pgx['city']?>" required>
            </div>
            <div class="col2">
                <label>State:</label>
                <input type="text" name="pgx[state]" value="<?php echo $pgx['state']?>" required>
            </div>

        </div>
        <div class="frow">
            <div class="col2">
                <label>Zip:</label>
                <input type="text" name="pgx[zip]" value="<?php echo $pgx['zip']?>" required>
            </div>
            <div class="col2">
                <label>Phone:</label>
                <input type="text" name="pgx[phone]" value="<?php echo $pgx['phone']?>" required>
            </div>

        </div>
        <div class="frow">
            <div class="col2">
                <label>Fax:</label>
                <input type="text" name="pgx[fax]" value="<?php echo $pgx['fax']?>" required>
            </div>
            <div class="col2">
                <label>Need Delivery By:</label>
                <input type="text" name="pgx[need_delivery]" value="<?php echo $pgx['need_delivery']?>" required>
            </div>
        </div>


        <br><br>


        <h3 class="center">Order Listing</h3>
        <table class="order_listing">
            <thead>
            <tr>
                <th> Blood Specimen Supplies </th>
                <th>Order By (Pack Size)</th>
                <th>QTY</th>
            </tr>
            </thead>
            <tbody>
            <?php


            foreach($listing as $text =>$vl)
            {
                $k = sanitize_title($text);
                if($vl===false)$text = '<strong>'.$text.'</strong>';
                ?>
                <tr>
                    <td <?php if($vl===false)echo 'class="bold"'?>><?php echo $text?></td>
                    <td><?php echo $vl?></td>
                    <td>
                        <?php if($vl!==false):?>
                            <input type="text" name="pgx[listing][<?php echo $k?>]" value="<?php echo $pgx['listing'][$k]?>"/>
                        <?php endif;?>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>


        <div class="frow">
            <label>Please list special requests: </label>
            <textarea name="pgx[special_intructions]"><?php echo $pgx['special_intructions']?></textarea>
        </div>

        <?php do_action('tr-captcha',1);?>

        <div class="frow actions">
            <input type="submit" class="button btn qbutton" value="Send form">
        </div>
    </form>
</div>