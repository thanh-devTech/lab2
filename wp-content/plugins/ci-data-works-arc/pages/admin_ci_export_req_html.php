<?php
global $wpdb;
global $current_role;


$table_batch        = 'ci_batches';
$current_medications = tr_pdd_get_current_medications();


$action_form    = '';
$id             = $_REQUEST['id'];
$bat            = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ",ARRAY_A);
$cli['cID']     = $bat['cli_ID'];
$cli_id         = $cli['cID'];
$cli            = $wpdb->get_row("select * from ci_clinic where `cID`= '{$cli_id}' ", ARRAY_A);
$pa             = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ",ARRAY_A);
if(!$pa)
{
    $pa = array();
    list($pa['pa_last'],$pa['pa_first'])= explode(',',@$bat['patient_name']);
    $pa['pa_dob'] = $bat['dob'];
}
$ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ",ARRAY_A);
$ins2 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance2']}' ",ARRAY_A);

$collector = $wpdb->get_row("select * from ci_collector where col_ID = '{$bat['col_ID']}' ", ARRAY_A);
$current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = '{$bat['lab_id']}' ", ARRAY_A);

$bat['current_medication'] = explode(',',@$bat['current_medication']);
$bat['current_medication_other'] = explode(',',@$bat['current_medication_other']);
if(count($bat['current_medication_other'])==1 && trim($bat['current_medication_other'][0])==''){
    $bat['current_medication_other'] = array();
}
foreach($bat['current_medication'] as $m)
{
    if(!in_array($m,$current_medications) && !empty($m) && $m!='None Listed')
    {
        $bat['current_medication_other'][] = $m;
    }
}

$bat['panel_tests_requested'] = explode(',',@$bat['panel_tests_requested']);
$bat['medication'] = maybe_unserialize(@$bat['medication']);


$bat['current_medication'] = array_map('trim',$bat['current_medication']);
$bat['panel_tests_requested'] = array_map('trim',$bat['panel_tests_requested']);
$bat['current_medication_other'] = array_map('trim',$bat['current_medication_other']);
//var_dump(@$bat);
$batch_key = $bat['assen'];


$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();
$mid = $bat['mid'];
$doc_date = date('m-d-Y',strtotime($bat['doc']));
$patient_name = str_replace(" ","-",$bat['patient_name']);
$outfile = sanitize_title($current_lab['lab_dba_init'] ."_". $doc_date ."_". $bat['position'] ."_". $patient_name).'.pdf';
$outfile = str_replace(array(' ',',','__'),array('-','','_'),$outfile);
$outfile = strtolower($outfile);
$batch_modified = $bat['batch_modified'];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>PHPWord</title>
    <style>
        @page {
            margin: 1cm 1.5cm 2cm 1.5cm;
        }
        break {
            page-break-after: always;
            border: 0;
        }
        body {
            color: #444;
            font-family: "Open Sans",sans-serif;font-size: 10px;
        }
        a.NoteRef {text-decoration: none;}
        .checkbox{float:left;display:block;text-align:center;line-height:12px;width:13px;height:13px;margin-right:4px;border:1px solid #444}
        hr {height: 1px; padding: 0; margin: 1em 0; border: 0; border-top: 1px solid #CCC;}
        table{width:100%;border-spacing: 0px;page-break-inside: auto;border-collapse: collapse;}
        table th{text-align: left;padding:5px;vertical-align: top;font-size:10px;}
        table .head th, h4{background:#000;color:#fff;font-size:9px;padding:5px;}
        table .head th span{font-size:8px}
        table td{padding:5px;vertical-align: top;}
        table td ul{padding-left:10px;margin:0;}
        table.tdborder td,table.tdborder th {border: 1px solid black;}
        table.border2.border.tdborder{border-top:1px solid black;border-left:1px solid black;border-right:2px solid black;border-bottom:2px solid #000}
        table.noborder td,table.noborder th{border:0 !important}
        table td label{font-weight: 600;}
        .marginbottom{margin-bottom: 10px;}
        .fontNormal {font-family: 'Arial'; font-size: 9pt; color: #000000;}
        .fontNormalRed {font-family: 'Arial'; font-size: 9pt; color: #FF0000; font-weight: bold;}
        .fontSmall {font-family: 'Arial'; font-size: 9pt; color: #000000;}
        .fontBold {font-family: 'Arial'; font-size: 9pt; color: #000000; font-weight: bold;}
        .fontBoldSmall {font-family: 'Arial'; font-size: 9pt; color: #000000; font-weight: bold;}
        .fontBoldWhite {font-family: 'Arial'; font-size: 9pt; color: #FFFFFF; font-weight: bold;}
        .fontHeader {font-family: 'Arial'; font-size: 11pt; color: #000000; font-weight: bold;}
        .normal {margin-top: 2.5pt; margin-bottom: 2.5pt;}
        .table {margin-top: 0pt; margin-bottom: 0pt;}
        .tableCenter {text-align: center; margin-top: 0pt; margin-bottom: 0pt;}
        .header {margin-top: 10pt; margin-bottom: 10pt;}
        .center{text-align: center}
        #header,
        #footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #666;
            font-size: 0.9em;
        }

        #header {
            top: 0;
            border-bottom: 0.1pt solid #aaa;
        }

        #footer {
            bottom: 5px;
            border-top: 0.1pt solid #aaa;
        }
    </style>
</head>
<body>


<?php
$upload_dir = tr_wp_upload_dir();
$logo = $current_lab['lab_logo'];
$logo_path = $upload_dir['basedir'].'/logos/'.$logo;
$logo_url = $upload_dir['baseurl'].'/logos/'.$logo;
?>
<div style="text-align: center">
    <img src="<?php echo $logo_url?>" align="center" style="max-height: 90px;width:auto"/>
</div>


<h2 style="margin:0">DNA Testing Requisition Form</h2>

<div style="float:right;margin:10px 0;text-align: right;">
    <label>Accession Number: </label>
    <span><?php echo @$bat['assen']?></span>
</div>

<table class="tdborder marginbottom">
    <tr class="head">
        <th style="width:20%">PRACTICE INFORMATION</th>
        <th style="width:20%">ORDERING PHYSICIAN</th>
        <th style="width:20%">DIAGNOSTIC CODES</th>
        <th style="width:40%" colspan="2">SPECIMEN INFORMATION</th>
    </tr>
    <tr>
        <td><?php echo @$bat['requesting_practice']?></td>
        <td><?php echo @$bat['requesting_physician']?></td>
        <td>
            <ul id="list_dagnostic_codes">
                <?php
                $bat['dagnostic_codes'] = explode(',',@$bat['dagnostic_codes1']);
                if(is_array(@$bat['dagnostic_codes']) && count($bat['dagnostic_codes'])>0)
                {
                    foreach($bat['dagnostic_codes'] as $vl)
                    {
                        if(empty($vl))continue;
                        ?>
                        <li><?php echo $vl?></li>
                        <?php
                    }
                }
                ?>
            </ul>
        </td>
        <td style="width:15%">
            <label>Type of Specimen:</label>
            <?php echo @$bat['type_of_specimen']?>
        </td>
        <td style="width:25%">
            <label>Date of Collection</label>
            <div><?php echo @$bat['doc']?></div>
            <label>Date Received</label>
            <div><?php echo @$bat['spec_received']?></div>
            <hr>
            Temperature read within 4 minutes in
            the range of 91-99.6 oF
            <?php if(@$bat['temperature']):?>Yes<?php else:?>
                No, actual temp: <?php echo @$bat['actual_temperature']?>
            <?php endif;?>
        </td>
    </tr>
</table>

<table class="tdborder marginbottom">
    <tr class="head">
        <th colspan="4">PATIENT INFORMATION</th>
    </tr>
    <tr>
        <td>
            <label>Social Security Number</label>
        </td>
        <td>
            <label>Last Name</label>
        </td>
        <td>
            <label>First Name</label>
        </td>
        <td>
            <label>Middle Initial</label>
        </td>
    </tr>
    <tr>
        <td><?php echo @$pa['pa_ssn']?></td>
        <td><?php echo @$pa['pa_last']?></td>
        <td><?php echo @$pa['pa_first']?></td>
        <td><?php echo @$pa['pa_middle']?></td>
    </tr>

    <tr>
        <td>
            <label>Medical Record Number</label>
        </td>
        <td>
            <label>Street Address</label>
        </td>
        <td>
            <label>City</label>
        </td>
        <td>
            <label>State</label>
        </td>
    </tr>
    <tr>
        <td><?php echo @$pa['pa_mrn']?></td>
        <td><?php echo @$pa['pa_address']?></td>
        <td><?php echo @$pa['pa_city']?></td>
        <td><?php echo $pa['pa_state']?></td>
    </tr>

    <tr>
        <td>
            <label>Gender</label>
        </td>
        <td>
            <label>Zip</label>
        </td>
        <td>
            <label>DOB</label>
        </td>
        <td>
            <label>Phone Number</label>
        </td>
    </tr>
    <tr>
        <td><?php echo @$pa['pa_gender']?></td>
        <td><?php echo @$pa['pa_zip']?></td>
        <td><?php echo @$pa['pa_dob']?></td>
        <td><?php echo @$pa['pa_phone']?></td>
    </tr>
</table>

<table class="">
    <tr class="head">
        <th style="width:60%">
            PRIMARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span>
        </th>
        <th style="width:40%">
            WORKER'S COMP AND AUTO INFORMATION
        </th>
    </tr>
    <tr>
        <td style="padding: 0;">
            <table class="tdborder" style="width:98%">
                <tr>
                    <td style="width:33%"><label>Last Name</label></td>
                    <td style="width: 33%;"><label>First Name</label>
                    </td>
                    <td>
                        <label>Relation to Patient?</label>
                    </td>
                </tr>
                <tr>
                    <td><?php echo @$ins1['ins_last']?></td>
                    <td><?php echo @$ins1['ins_first']?></td>
                    <td><?php echo @$ins1['ins_relation']?></td>
                </tr>
                <tr>
                    <td>
                        <label style="font-size:9px">Insurance Company Name</label></td>
                    <td>
                        <label>Email</label>
                    </td>
                    <td>
                        <label><?php if($ins1['ins_relation']!='Self')echo 'Parent DOB';else echo '&nbsp;';?></label>
                    </td>
                </tr>
                <tr>
                    <td><?php echo @$ins1['ins_company']?></td>
                    <td><?php echo @$ins1['ins_email']?></td>
                    <td><?php if(@$ins1['ins_dob']!='0000-00-00')echo @$ins1['ins_dob'];?></td>
                </tr>
                <tr>
                    <td>
                        <label>Street Address</label>
                    </td>
                    <td>
                        <label>City</label>
                    </td>
                    <td>
                        <label>State</label>/<label>Zip</label>

                    </td>
                </tr>
                <tr>
                    <td><?php echo @$ins1['ins_address']?></td>
                    <td><?php echo @$ins1['ins_city']?></td>
                    <td><?php echo $ins1['ins_state']?> /
                        <?php echo @$ins1['ins_zip']?>
                    </td>
                </tr>
                <tr>
                    <td><label>Insurance Phone</label></td>
                    <td><label>Member ID</label></td>
                    <td><label>Group #</label></td>
                </tr>
                <tr>
                    <td><?php echo @$ins1['ins_phone']?></td>
                    <td><?php echo @$ins1['ins_member_id']?></td>
                    <td><?php echo @$ins1['ins_group_id']?></td>
                </tr>

                <tr>
                    <td><label>Pre-authorization</label></td>
                    <td><label>Authorization #</label></td>
                    <td><label>Authorization Date</label></td>
                </tr>
                <tr>
                    <td><?php echo $ins1['ins_authorization']?></td>
                    <td><?php echo @$ins1['ins_authorization_id']?></td>
                    <td><?php echo @$ins1['ins_authorization_date']?></td>
                </tr>
            </table>
        </td>
        <td style="padding:0">
            <table class="tdborder">
                <tr>
                    <td style="width:45%"><label>Subscriber Last Name</label></td>
                    <td style="width:55%"><?php echo @$bat['subscriber_last']?></td>
                </tr>
                <tr>
                    <td><label>Subscriber First Name</label></td>
                    <td><?php echo @$bat['subscriber_first']?></td>
                </tr>
                <tr>
                    <td><label>Date of Injury</label></td>
                    <td><?php echo @$bat['date_of_injury']?></td>
                </tr>

                <tr>
                    <td><label>Address of Claim</label></td>
                    <td><?php echo @$bat['address_of_claim']?></td>
                </tr>
                <tr>
                    <td><label>Zip Code</label></td>
                    <td><?php echo @$bat['subscriber_zip']?></td>
                </tr>

                <tr>
                    <td><label>Insurance</label></td>
                    <td><?php echo @$bat['subscriber_insurance']?></td>
                </tr>
                <tr>
                    <td><label>WC#</label></td>
                    <td><?php echo @$bat['subscriber_wc']?></td>
                </tr>

                <tr>
                    <td><label>Attorney</label></td>
                    <td><?php echo @$bat['attorney_name']?></td>
                </tr>
                <tr><td><label>Attorney Phone</label></td>
                    <td><?php echo @$bat['attorney_phone']?></td>
                </tr>

                <tr><td><label>Adjuster Name</label></td>
                    <td><?php echo @$bat['adjuster_name']?></td>
                </tr>

            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:0">
            <table class="tdborder" style="width:98%">
                <tr class="head" >
                    <th colspan="3">SECONDARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></th>
                </tr>
                <tr>
                    <td style="width:33%"><label>Last Name</label></td>
                    <td style="width: 33%;"><label>First Name</label></td>
                    <td><label>Relation to Patient?</label></td>
                </tr>

                <tr>
                    <td><?php echo @$ins2['ins_last']?></td>
                    <td><?php echo @$ins2['ins_first']?></td>
                    <td><?php echo @$ins2['ins_relation']?></td>
                </tr>
                <tr>
                    <td><label style="font-size:9px">Insurance Company Name</label></td>
                    <td><label>Email</label></td>
                    <td><label><?php if($ins2['ins_relation']!='Self')echo 'Parent DOB';else echo '&nbsp;';?></label></td>
                </tr>
                <tr>
                    <td><?php echo @$ins2['ins_company']?></td>
                    <td><?php echo @$ins2['ins_email']?></td>
                    <td><?php if(@$ins2['ins_dob']!='0000-00-00')echo @$ins2['ins_dob']?></td>
                </tr>
                <tr>
                    <td><label>Insurance Phone</label></td>
                    <td><label>Member ID</label></td>
                    <td><label>Group #</label></td>
                </tr>
                <tr>
                    <td><?php echo @$ins2['ins_phone']?></td>
                    <td><?php echo @$ins2['ins_member_id']?></td>
                    <td><?php echo @$ins2['ins_group_id']?></td>
                </tr>
            </table>

        </td>
        <td style="padding:0">
            <table class="tdborder">
                <tr>
                    <td style="width:45%"><label>Adjuster Phone Number</label></td>
                    <td><?php echo @$bat['adjuster_phone']?></td>
                </tr>
                <tr>
                    <td><label>Claim Number</label></td>
                    <td><?php echo @$bat['claim_number']?></td>
                </tr>
                <tr>
                    <td><label>Claim under litigation?</label></td>
                    <td><?php echo $bat['claim_under_litigation']?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<break/>


<table class="tdborder marginbottom">
    <tr class="head">
        <th style="width:60%">PATIENT’S CURRENT MEDICATIONS</th>
        <th>TESTING ORDERED</th>
    </tr>
    <tr>
        <td>
            <?php if(in_array('None Listed',@$bat['current_medication'])):?>
                No Prescribed Medication
            <?php else:?>
                <div>
                    <?php foreach($current_medications as $ii => $m):
                        $k = $m;
                        if($k=='Fioricet/Fiorinal')$k='Fioricet';
                        else if($k=='Lorcet/Lortab')$k='Lortab';
                        //if($ii%4==0 && $ii>0){
                        //echo '</tr><tr>';
                        //}
                        if(is_array($bat['current_medication'])&& in_array($k,@$bat['current_medication'])){
                            ?>
                            <div>
                                <?php echo $m?>
                            </div>
                        <?php }
                    endforeach;?>
                </div>


                <div class="frow" style="padding-top:10px">
                    <label>Other:</label>
                    <?php
                    if(!is_array(@$bat['current_medication_other'])) $bat['current_medication_other'] = array();
                    $count = count($bat['current_medication_other']);
                    for($i=0;$i<$count;$i++):?>
                        <div>
                            <?php echo @$bat['current_medication_other'][$i]?>
                        </div>
                    <?php endfor;?>
                </div>
            <?php endif;?>


        </td>
        <td>
            <?php
            $keys = array(
                'Comprehensive' => '<b>Gene (#SNPS)</b> CYP1A2 (4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), ANKK1/DRD2 (1), APOE (2),COMT (1), FACTOR II [F2] (1), FACTOR V [F5] (1), MTHFR (2),OPRM1 (1), SLCO1B1 (2), VKORC1 (1)',
                'Pain Management'=>'<b>Gene (#SNPS)</b> CYP1A2( 4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), OPRM1(1)',
                'Psychiatry' => '<b>Gene (#SNPS)</b> CYP1A2(4), CYP2B6(8), CYP2C9 (8), CYP2C19(8),CYP2D6(15), CYP3A4 (6), CYP3A5(5), ANKK1/DRD2 (1),COMT (1), MTHFR (2)',
                'Cardiology' => '<b>Gene (#SNPS)</b> CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6),CYP3A5 (5), APOE (2), FACTOR II [F2] (1), FACTOR V [F5] (1),MTHFR (2), SLCO1B1 (2), VKORC1 (1)',
                'Urology' => '<b>Gene (#SNPS)</b> CYP2D6 (15), CYP3A4 (6),CYP3A5 (5)'
            );
            $i = 0;
            foreach($keys as $k => $txt)
            {
                if(!in_array($k,$bat['panel_tests_requested']))continue;
                ?>
                <div class="frow blabel testing_ordered_box">
                    <label>
                        <strong><?php echo $k?> Panel
                    </label>

                </div>
                <?php
                $i++;
            }
            ?>
        </td>
    </tr>
</table>

<div class="frow">
    STATEMENT OF CONSENT AND RELEASE I hereby certify that the information provided is true and accurate. I consent to the collection of specimens from myself and any listed
    child for the purpose of DNA testing. <?php echo @$current_lab['lab_dba_init']?> reserves the right to re-collect samples for retesting and to store samples for future additional tests, if necessary. I understand that while
    DNA testing is highly accurate and widely accepted, as in all testing there is a possibility of delay or error. I understand that samples and the DNA they contain become the
    exclusive property of <?php echo @$current_lab['lab_dba_init']?>. I also understand that this testing is for informational purposes and prescription drug regimes should never be altered without consulting a physician.
    <?php echo @$current_lab['lab_dba_init']?> will not release any personal, identifiable information of any kind to a third party without my express written instructions. I also understand that <?php echo @$current_lab['lab_dba_init']?> reserves the right to
    provide deidentified information of a statistical nature to accrediting agencies and reserves the right to use such anonymous information
</div>
<table>
    <tr>
        <td width="50%">
            <div>Patient Signature:</div>
            <?php
            $fname = $batch_key.'_patient.png';

            if(file_exists($sign_dir['path'].'/'.$fname) && filesize($sign_dir['path'].'/'.$fname)>0):?>
                <img class="sign_img" src="<?php echo $sign_dir['url'].'/'.$fname?>" style="max-width:300px"/>
            <?php endif;?>

        </td>
        <td>
            <div><b>Authorized Healthcare</b></div>
            <div>Provider Signature:</div>
            <?php
            $fname = $batch_key.'_provider.png';

            if(file_exists($sign_dir['path'].'/'.$fname) && filesize($sign_dir['path'].'/'.$fname)>0):?>
                <img class="sign_img" src="<?php echo $sign_dir['url'].'/'.$fname?>" style="max-width:300px"/>
            <?php endif;?>
        </td>
    </tr>
</table>

<table class="frow">
    <tr class="head">
        <th>INSERT INSURANCE CARD AND PHOTO ID PICTURES</th>
    </tr>
</table>


<?php
$images =array();
$fname = !empty($bat['ins_card_front'])? $bat['ins_card_front']:$batch_key.'_ins.jpg';
if(file_exists($card_dir['path'].'/'.$fname) && getimagesize($card_dir['path'].'/'.$fname))
{
    $images[]= '<div><strong>Ins Card Front</strong></div>'
        . '<img src="'.$card_dir['url'].'/'.$fname.'" style="width:100%;min-width:300px">';
}

?>
<?php
$fname = !empty($bat['ins_card_back'])? $bat['ins_card_back']:$batch_key.'_ins_back.jpg';
if(file_exists($card_dir['path'].'/'.$fname) && getimagesize($card_dir['path'].'/'.$fname))
{
    $images[]=  '<div><strong>Ins Card Back</strong></div>'
        . '<img src="'.$card_dir['url'].'/'.$fname.'" style="width:100%;min-width:300px">';
}
?>

<?php
$fname = !empty($bat['patient_id_front'])? $bat['patient_id_front']:$batch_key.'_id.jpg';
if(file_exists($card_dir['path'].'/'.$fname) && getimagesize($card_dir['path'].'/'.$fname))
{
    $images[]=  '<div><strong>Patient ID Front</strong></div>'
        . '<img src="'.$card_dir['url'].'/'.$fname.'" style="width:100%;min-width:300px">';
}
?>

<?php
$fname = !empty($bat['patient_id_back'])? $bat['patient_id_back']:$batch_key.'_id_back.jpg';
if(file_exists($card_dir['path'].'/'.$fname) && getimagesize($card_dir['path'].'/'.$fname))
{
    $images[]=  '<div><strong>Patient ID Back</strong></div>'
        . '<img src="'.$card_dir['url'].'/'.$fname.'" style="width:100%;min-width:300px">';
}


?>
<table>
    <tr>
    <?php
    foreach($images as $iim=> $img)
    {
        if($iim%2==0 && $iim>0)
        {
            echo '</tr><tr>';
        }
      echo '<td style="width:50%">'.$img.'</td>';
    }
    ?>


    </tr>
</table>

<?php if(!empty($bat['notes'])):?>
    <div class="frow">
        <h4>NOTES</h4>
    </div>
    <div class="frow">
        <?php echo nl2br($bat['notes'])?>
    </div>
<?php endif;?>

<?php if(!empty($bat['billing_notes'])):?>
    <div class="frow">
        <h4>Billing Notes</h4>
    </div>
    <div class="frow">
        <?php echo nl2br($bat['billing_notes'])?>
    </div>
<?php endif;?>


</body>
</html>
