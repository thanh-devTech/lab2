<?php
global $wpdb;
$errormsg = '';

$arr = explode('/qrsc/',$_SERVER['REQUEST_URI']);
$keystr = $arr[1];
if(strpos($keystr,'?')>0)
{
	list($keystr,$notkey) = explode('?',$keystr);
}
$arr = tr_patient_decode_id_btype($keystr);

$report_id = intval($arr['id']);
$btype = $arr['btype'];
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);


$report = $wpdb->get_row("
            select ba.mid,
                re.id,
                pa.pa_first,
                pa.pa_last
                from {$batch_tbl} ba
                jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                JOIN {$report_tbl} re ON re.patient_id = ba.assen
                WHERE re.id = {$report_id}
            ", ARRAY_A);


if(isset($_GET['debug'])){
    var_dump($report_id);
    var_dump($btype);exit;
}

$dob = isset($_POST['birthday'])? $_POST['birthday']:'';
$last_name = isset($_POST['last_name'])? $_POST['last_name']:'';
$lastcpassport = isset($_POST['lastcpassport'])? $_POST['lastcpassport']:'';

if($report_id>0 && !empty($_POST['birthday']))
{
	$passport = $wpdb->get_var(
	        "select pa.pa_passport 
	            from {$batch_tbl} ba
				join {$report_tbl} re ON ba.assen = re.patient_id
				join ci_patients pa ON pa.pa_ID = ba.patient_id
				where re.id = {$report_id} 
				and pa.pa_dob = '{$dob}' ");
	$passport = str_replace(array('-','.',' '),'',$passport);

	if($passport && substr($passport,-5)==$lastcpassport)
	{
		$lab_url = get_site_url(1);
		if(empty($lab_url) && defined('DOMAIN_CURRENT_SITE')){
			$lab_url = 'https://'.DOMAIN_CURRENT_SITE;
		}

		$user_id = time();
		$timeout = time() - 3500;//100s
		$file = $btype.'_'.trpdd_patient_encode($report_id).'_'.$user_id.'.pdf';
		$auth = base64_encode($timeout.'_'.$user_id.'_'.$report_id.'_'.($report_id+9382));
		$pdflink = $lab_url.'?tr_action=ci_results_pdf&auth='.$auth.'&file='.$file;
		header('location: '.$pdflink);exit;
	}else{
	    $errormsg = 'DOB or Passport number not match';
    }
}


$logo = $wpdb->get_var("select lab_logo from ci_labs where lab_status=1 and lab_is_ref=0 order by lab_ID asc limit 1");
if(!empty($logo))
{
	$upload_dir = tr_wp_upload_dir();
	$logo_path = $upload_dir['basedir'].'/logos/'.$logo;
	$logo = $upload_dir['baseurl'].'/logos/'.$logo;
}
?>
<html>
<head>
    <title>Report</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style>
		form{
			margin:20px auto;
			width:300px;
		}

        form .frow{
            margin-bottom:10px;
        }
		.frow label{
			display: block;
			margin-bottom:2px;
		}
		.login form .input, .login input[type=password], .login input[type=text], .login select {
			font-size: 20px;
			line-height: 1.33333333;
			width: 100%;
			padding: 0.1875rem 0.3125rem;
			margin: 0 6px 16px 0;
			min-height: 40px;
			max-height: none;
			box-shadow: 0 0 0 transparent;
			border-radius: 4px;
			border: 1px solid #7e8993;
			background-color: #fff;
			color: #32373c;
		}
		.wp-core-ui .button, .wp-core-ui .button-secondary {
			color: #0071a1;
			border-color: #0071a1;
			background: #f3f5f6;
			vertical-align: top;
		}
		.wp-core-ui .button, .wp-core-ui .button-primary, .wp-core-ui .button-secondary {
			display: block;
			text-decoration: none;
			font-size: 16px;
			line-height: 38px;
			min-height: 38px;
			margin: 0;
			padding: 0 10px;
			cursor: pointer;
			border-width: 1px;
			border-style: solid;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			box-sizing: border-box;
            width:100%;
		}
        div.error{
            color:red;
            text-align: center;
            padding:4px;
        }

        .dropd3 select{
            width:32% !important;
            margin-right:1% !important;
        }
        .dropd3 select:last-child{
            margin-right:0 !important;
        }
        .center{
            text-align: center;
        }

	</style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="<?php echo TRCPPR_URL . 'js/jquery.date-dropdowns.min.js' ?>"></script>
</head>
<body>
<form method="post" class="login wp-core-ui">
    <br>
    <div class="logo center">
        <img src="<?php echo $logo?>"/>
    </div>
    <?php if(!empty($errormsg))echo '<div class="error">'.$errormsg.'</div>'?>
    <br>
    <div class="frow">
        <label>First/Given Name: <span style="font-weight: bold;"><?php echo $report['pa_first']?></span></label>
    </div>
    <div class="frow">
        <label>Last/Surname: </label>
        <input type="text" name="last_name" value="<?php echo $last_name?>"/>
    </div>
	<div class="frow dropd3">
		<label>Birthday:</label>
		<input type="text" name="birthday" class="has_date" placeholder="yyyy-mm-dd" required="required" value="<?php echo $dob?>"/>
	</div>
	<div class="frow">
		<label>Last 5 digits of the Passport:</label>
		<input type="text" name="lastcpassport" maxlength="5" required value="<?php echo $lastcpassport?>"/>
	</div>
	<div class="frow" style="text-align: center">
		<input type="submit" class="button" value="Submit"/>
		<input type="hidden" name="reportid" value="<?php echo $report_id?>"/>
	</div>
</form>
<script>
    jQuery(function ($) {
        $('.has_date').dateDropdowns({displayFormat: 'mdy',minAge:0,daySuffixValues: ['', '', '', '']});
    })
</script>
</body>
</html>
<?php

exit;
