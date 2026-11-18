<?php
global $wpdb;

include_once(TRCPDD_PATH.'pages/formdata.php');
$dir = WP_CONTENT_DIR.'/cache/';
$user_id = get_current_user_id();

if(isset($_POST['senddata'])){
    $senddata = tr_pdd_stripslashes_deep($_POST['senddata']);
    if(empty($senddata['message']))
    {
        $senddata['message'] = 'Reference Loadlist attached!';
    }
    $email = $senddata['email'];
    $subject = $senddata['subject'];
    $message = $senddata['message'];

    $batch_id = date('Ymd',strtotime($senddata['date']));
    $cIDs = $senddata['cli_IDs'];


    update_option('ci_loadlist_send',$senddata);
    update_user_meta($user_id,'_loadlist_email',$email);



    //$headers = 'From: ' .$from_name.' <'. $from_email.'>' . "\r\n";
    //$headers .= 'Reply-To: ' . $from_email. "\r\n";
    $headers .= "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $filepath = $dir . 'report_'.$date.'.csv';


    $order_by = 'ba.position asc, ba.mid asc';
    $cIDs_str = implode(',',$cIDs);

    $where = " cli_ID in ({$cIDs_str}) ";
    $where.= " and batch_id = '{$batch_id}'";


    $sql = "select assen,
                doc,
                dob,
                current_medication
               
            from ci_batches ba
            where {$where} order by {$order_by} ";


    $batches = $wpdb->get_results($sql);

    if(count($batches)>0)
    {
        $i = 1;
        $keys = array(
            'Barcode',
            'DOC',
            'DOB',
            'Medication',
        );
        $f = fopen($filepath,'w');
        fputcsv($f,$keys);

        foreach($batches as $row)
        {
            $i++;
            $row_data = array(
                $row->assen,
                $row->doc,
                $row->dob,
                $row->current_medication
            );


            fputcsv($f,$row_data);
        }
        @fclose($f);

        $atts = array(
            $filepath
        );
        $message = nl2br($message);
        $rs = wp_mail($email,$subject,$message,$headers,$atts);
        unlink($filepath);
        if($rs)
        {
            Tr_Session_Class::add('Sent Successfully');
        }else{
            Tr_Session_Class::add_error('Error. please try again');
        }
    }
    else{
        Tr_Session_Class::add_error('No Results found');
    }

}

$where_clis = " and cli.cli_clinic !='' and cli.cli_clinic !='0' ";
$sql_clinic = "select cli.* from ci_clinic cli
                    where 1 {$where_clis} 
                    order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);

$senddata = get_option('ci_loadlist_send',array());
$email = get_user_meta($user_id,'_loadlist_email',true);
if(empty($senddata['subject']))
{
    $senddata['subject'] = 'Loadlist';
}
if(empty($senddata['message']))
{
    $senddata['message'] = 'Reference Loadlist attached!';
}
?>
<div class="wrap">
    <h2>Loadlist Send</h2>
    <?php Tr_Session_Class::show()?>

    <form method="post" class="need_validate">
        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <label>Subject</label>
                    <input type="text"  name="senddata[subject]" value="<?php echo $senddata['subject']?>" required/>
                </div>
                
                <div class="frow">
                    <label>Email</label>
                    <input type="email"  name="senddata[email]" value="<?php echo $email?>" required/>
                </div>

                <div class="frow">
                    <label>Date</label>
                    <input type="date" class="hasdate" name="senddata[date]" required/>
                </div>


            </div>
            <div class="col2">
                <div class="frow">
                    <label>Clinics</label>
                    <select name="senddata[cli_IDs][]" class="js_select2" multiple required>
                        <?php
                        foreach($clinics as $clinic)
                        {
                            $selected = isset($senddata['cli_IDs']) && in_array($clinic['cID'],(array)$senddata['cli_IDs'])? 'selected':'';
                            echo '<option value="'.$clinic['cID'].'" '.$selected.'>'.$clinic['cli_clinic'].'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Send Loadlist"/>
        </div>
    </form>
</div>