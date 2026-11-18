<?php
global $wpdb, $current_role;
include_once(TRCPDD_PATH . 'pages/formdata.php');

require_once(TRCPDD_PATH . 'api/ci_sample_manifest.php');

$show_no_results_tab = tr_get_option('ci_pdf_data', 'sample_manifest_no_results_tab');
$manifest_tab   = isset($_REQUEST['tab']) ? sanitize_text_field($_REQUEST['tab']) : '';
if ($manifest_tab == 'no_results' && !$show_no_results_tab) {
	$manifest_tab = '';
}
$is_no_results_tab = $manifest_tab == 'no_results';
$default_from_date = $is_no_results_tab ? date_i18n('Y-m-d', strtotime('-30 days', current_time('timestamp'))) : date_i18n('Y-m-d');
$from_date      = !empty($_REQUEST['fromdate']) ? sanitize_text_field($_REQUEST['fromdate']) : $default_from_date;
$to_date        = !empty($_REQUEST['todate']) ? sanitize_text_field($_REQUEST['todate']) : ($is_no_results_tab ? date_i18n('Y-m-d') : $from_date);
$cli_ID         = isset($_REQUEST['cli_ID']) ? (int) $_REQUEST['cli_ID'] : 0;
$reflabid       = isset($_REQUEST['reflab']) ? (int) $_REQUEST['reflab'] : 0;
$user_id        = get_current_user_id();
$no_results_hours = 100;

$server_api_ids = $wpdb->get_col("select wsdl_ID from ci_wsdl_keys where wsdl_url = '' or wsdl_url is null");
$client_api_ids = $wpdb->get_col("select wsdl_ID from ci_wsdl_keys where wsdl_url != '' and wsdl_url is not null");


$conditions = trpdd_get_batch_conditions(0);
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where      = ' is_saved = 0 ' . $conditions['where'];
$where_cli  = $conditions['where_clinic'];

$ref_assen_title = tr_get_option('ci_pdf_data', 'title_pid_field', 'PID');
$show_ref_assen = tr_get_option('ci_pdf_data', 'show_pid_field');
$show_pa_mrn 	= tr_get_option('ci_pdf_data', 'show_pa_mrn_todayb');
$show_athena_id = tr_get_option('ci_pdf_data', 'report_show_athena_id');
$show_panel = tr_get_option('ci_pdf_data', 'report_show_panel');


$blood_settings = get_option('ci_req_settings_blood', array());
$Phlebotomist = isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist'];
$Phlebotomist_clinics = array();
if ($Phlebotomist) {
	$Phlebotomist_clinics = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_phlebotomist = 1 ");
}


$sql_clinic = "select cli.* from ci_clinic cli
                    where {$where_cli} 
					group by cli.cID 
					order by cli.cli_clinic";


$clinics 	= $wpdb->get_results($sql_clinic);
$reflabs 	= CI_Base_Lab::get_ref_labs();


$select_fields = '';
$joins = '';

if ($reflabid > 0 || $is_no_results_tab) {
	$select_fields .= ", wb.bdate as sent_at, TIMESTAMPDIFF(HOUR, wb.bdate, now()) as sent_hours ";
	//where sent by client api
	$joins .= " JOIN ci_wsdl_batch wb 
					ON wb.btype='[btype]'and wb.b_mid = ba.mid and wb.b_status >0 
					and wb.b_wsdl_ID in (" . implode(',', $client_api_ids) . ") ";
}

if ($reflabid > 0) {
	$where .= " and ba.ref_lab_ID = {$reflabid} ";



	if (!empty($from_date)) {
		$where .= " and wb.bdate >= '{$from_date}' ";
	}
	if (!empty($to_date)) {
		$where .= " and wb.bdate <= '{$to_date} 23:59:59' ";
	}
} else {
	if (!empty($from_date)) {
		$where .= " and ba.doc >= '{$from_date}' ";
	}
	if (!empty($to_date)) {
		$where .= " and ba.doc <= '{$to_date} 23:59:59' ";
	}
}

if ($show_pa_mrn) {
	$select_fields = ', pa.pa_mrn ';
	$joins .= " LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id ";
}
if ($show_athena_id) {
	$select_fields .= ', cli.cli_athena_departmentid ';
	$joins .= " LEFT JOIN ci_clinic cli ON cli.cID = ba.cli_ID ";
}
$all_groups = [];
$all_tests = [];
if ($show_panel) {
	$select_fields .= ', ba.panel_tests_requested ';
	$rs_groups = $wpdb->get_results("select 
	tg_ID, 
	tg_name,
	tg_parent
	from ci_test_groups");
	foreach ($rs_groups as $g) {
		$all_groups[$g->tg_ID] = $g;
	}
	unset($rs_groups);
	$rs_tests = $wpdb->get_results("select 
	te_name,
	te_group_id
	from ci_test_panels");
	foreach ($rs_tests as $t) {
		$all_tests[$t->te_name] = $t->te_group_id;
	}
	unset($rs_tests);
}

if ($cli_ID > 0) {
	$where .= " and ba.cli_ID = {$cli_ID} ";
}

$act = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : (isset($_REQUEST['act']) ? $_REQUEST['act'] : '');

if ($act == 'settings') {

	if (isset($_POST['opt'])) {
		$opt = $_POST['opt'];
		update_option('tr_sample_manifest_st', $opt, false);
		$hassc = !empty($opt['email']) && !empty($opt['time']);
		if ($hassc) {
			$timezone_offset     = get_option('gmt_offset') * HOUR_IN_SECONDS;
			$runtime = strtotime(date('Y-m-d ' . $opt['time'] . ':00')) - $timezone_offset;
			wp_clear_scheduled_hook('cron_all_api', array('CI_Sample_Manifest'));
			wp_schedule_event($runtime, 'daily', 'cron_all_api', array('CI_Sample_Manifest'));
		}
		wp_send_json(array('status' => 'ok'));
	}
	$opt = get_option('tr_sample_manifest_st', array());
?>
	<form method="post" class="ajaxsaveform">
		<div class="frow">
			<label>Email:</label>
			<input type="text" name="opt[email]" value="<?php echo $opt['email'] ?>">
		</div>
		<div class="frow">
			<label>Time</label>
			<select name="opt[time]">
				<?php
				echo '<option value=""></option>';
				for ($i = 1; $i < 24; $i++) {
					for ($j = 0; $j <= 1; $j++) {
						$t = $j == 0 ? $i . ':00' : $i . ':30';
						$selected = $opt['time'] == $t ? 'selected' : '';
						echo '<option value="' . $t . '" ' . $selected . '>' . $t . '</option>';
					}
				}
				?>
			</select>
		</div>
		<div class="frow">
			<input type="submit" class="button-primary" value="Save" />
			<input type="hidden" name="trajax" value="settings" />
		</div>
	</form>
<?php
	exit;
} else if ($act == 'sendmail') {
	$opt = get_option('tr_sample_manifest_st', array());
?>
	<form method="post">
		<div class="frow">
			<label>Email:</label>
			<input type="text" name="opt[email]" value="<?php echo $opt['email'] ?>">
		</div>

		<div class="frow">
			<input type="submit" class="button-primary" value="Send" />
			<input type="hidden" name="act" value="dosendmail" />
		</div>
	</form>
<?php
	exit;
}

$results = array();
if (!empty($act)) {
	$child_qrs = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$wherebtype = $where;
		$joinsbtype = $joins;

		$joinsbtype = str_replace('[btype]', $btype, $joinsbtype);

		if ($is_no_results_tab) {
			$joinsbtype .= " LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen  ";
			$wherebtype .= " and (re.re_import_ID = 0 or re.re_import_ID is null)";
		}


		if ($Phlebotomist && count($Phlebotomist_clinics) > 0) {
			$wherebtype .= " and (
					ba.cli_ID  not in (" . implode(',', $Phlebotomist_clinics) . ") 
					or (ba.cli_ID in (" . implode(',', $Phlebotomist_clinics) . ") and ba.batch_status = 'so_collected')
				)";
		}

		$child_qrs[] =  "select
			ba.assen,
			ba.oldreq,
			ba.doc,
			ba.cli_ID,
			ba.requesting_practice,
			ba.requesting_physician,
			ba.patient_name,
			ba.insurance
			{$select_fields}
			from {$batch_tbl} ba
			{$joinsbtype}
			where {$wherebtype} ";
	}

	if (!empty($child_qrs)) {
		$sql = "select * from (
			" . implode(' UNION ALL ', $child_qrs) . "
		) as ba ";
		$sql .= $is_no_results_tab ? " order by sent_at asc, requesting_practice asc" : " order by requesting_practice asc, doc desc";
		$results = $wpdb->get_results($sql);
	}
}

if (isset($_GET['debug'])) {
	echo '<pre>';
	print_r($sql);
	echo '</pre>';
}


if ($act === 'Export') {

	header("Content-disposition: attachment; filename=" . ($is_no_results_tab ? "sample_manifest_no_results.csv" : "sample_manifest.csv"));
	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');
	$headers = array();
	if ($show_ref_assen) {
		$headers[] = $ref_assen_title;
	}
	$headers[] = 'Accession';
	$headers[] = 'Practice';
	$headers[] = 'Ordering Physician';
	$headers[] = 'Patient';
	if ($show_athena_id) {
		$headers[] = "Athena ID";
	}
	if ($show_panel) {
		$headers[] = "Panel";
	}
	$headers[] = 'Insurance';
	if ($is_no_results_tab) {
		$headers[] = 'Sent At';
		$headers[] = 'Hours Since Sent';
	}
	$headers[] = 'Date';
	$headers[] = 'Sample Present?';

	fputcsv($output, $headers);
	$clinicarr = array();
	foreach ($results as $i => $r) {
		if (!in_array($r->requesting_practice, $clinicarr)) {
			if (count($clinicarr) > 0) {
				fputcsv($output, array(''));
				fputcsv($output, array(''));
			}
			$row = array(
				'',
				'',
				'',
				$r->requesting_practice,
			);

			fputcsv($output, $row);
			//fputcsv($output, array(''));

			$clinicarr[] = $r->requesting_practice;
		}
		$row = array();
		if ($show_ref_assen) {
			$row[] = $r->oldreq . "\0";
		}
		$row[] = $r->assen . "\0";
		$row[] = $r->requesting_practice;
		$row[] = $r->requesting_physician;
		$row[] = $r->patient_name;
		if ($show_athena_id) {
			$row[] = $r->cli_athena_departmentid;
		}
		if ($show_panel) {
			$panels = [];
			foreach (explode(',', $r->panel_tests_requested) as $t) {
				if (stripos($t, 'group') !== false) {
					$group_id = str_ireplace('group_', '', $t);
					if (isset($all_groups[$group_id])) {
						$panels[] = $all_groups[$group_id]->tg_name;
					}
				} else {
					if (isset($all_tests[$t]) && isset($all_groups[$all_tests[$t]])) {
						$panels[] = $all_groups[$all_tests[$t]]->tg_name;
					}
				}
			}

			$row[] = implode(', ', array_unique($panels));
		}
		$row[] = $r->insurance;
		if ($is_no_results_tab) {
			$row[] = $r->sent_at;
			$row[] = $r->sent_hours;
		}
		$row[] = $r->doc;
		$row[] = '';


		fputcsv($output, $row);
	}
	@fclose($output);
	$data_log = [
		'type' => $is_no_results_tab ? 'Sample Manifest No Results Report' : 'Sample Manifest Report',
		'file_name' => $is_no_results_tab ? 'sample_manifest_no_results.csv' : 'sample_manifest.csv',
		'total_records' => count($results),
		'from_date' => $from_date,
		'to_date' => $to_date,
	];
	trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));

	exit;
} else if ($act === 'PDF') {
	ob_start();
	include('sample_manifest_html.php');
	$html = ob_get_clean();
	$html = trpdd_pdf_html($html);
	$filename = 'sample_manifest.pdf';
	header("Pragma: public");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"$filename\";");
	trpdd_build_pdf($html, array('filename' => $filename));
	exit;
} else if ($act === 'print') {
	ob_start();
	include('sample_manifest_html.php');
	$html = ob_get_clean();
	$html = trpdd_pdf_html($html);
	echo $html;
	echo '<script>window.print()</script>';
	exit;
} else if ($act == 'dosendmail') {

	$client = new CI_Sample_Manifest();
	$emails = explode(',', $_POST['opt']['email']);
	$rs = false;
	foreach ($emails as $email) {
		$email = trim($email);
		if (!empty($email)) {
			$rs = $client->send($email);
		}
	}


	if ($rs) {
		Tr_Session_Class::add('Sent!');
	} else {
		Tr_Session_Class::add_error('Empty');
	}
}

?>
<div class="wrap ">
	<h2>Sample Manifest</h2>
	<?php if ($show_no_results_tab) { ?>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab <?php echo !$is_no_results_tab ? 'nav-tab-active' : '' ?>" href="<?php echo esc_url(add_query_arg(array('page' => isset($_GET['page']) ? $_GET['page'] : '', 'tab' => ''))) ?>">Manifest</a>
			<a class="nav-tab <?php echo $is_no_results_tab ? 'nav-tab-active' : '' ?>" href="<?php echo esc_url(add_query_arg(array('page' => isset($_GET['page']) ? $_GET['page'] : '', 'tab' => 'no_results', 'fromdate' => date_i18n('Y-m-d', strtotime('-30 days', current_time('timestamp'))), 'todate' => date_i18n('Y-m-d')))) ?>">No Results</a>
		</h2>
	<?php } ?>
	<?php Tr_Session_Class::show() ?>
	<br><br>
	<form method="get" target="_blank" style="margin-bottom:20px" id="formsearch">
		<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr($manifest_tab) ?>" />
		From: <input type="text" class="hasdate" name="fromdate" style="width:100px" value="<?php echo @$from_date ?>" required />
		<label id="summarytodate">To:</label>
		<input type="text" class="hasdate today" name="todate" style="width:100px" value="<?php echo @$to_date ?>" required />
		<select name="cli_ID" class="js_select2" style="max-width:270px">
			<option value="">All Clinics</option>
			<?php
			foreach ($clinics as $cli) {
				$selected = $cli->cID == $cli_ID ? 'selected' : '';
				echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
			}
			?>
		</select>

		<?php
		if (count($reflabs) > 0) {
		?>
			<select name="reflab">
				<?php
				echo '<option value="">Select</option>';
				foreach ($reflabs as $rlab) {
					$selected = $rlab->lab_ID == $reflabid ? 'selected' : '';
					echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
				}
				?>
			</select>
		<?php
		}
		?>

		<input type="submit" name="act" value="View" onclick="jQuery('#formsearch').removeAttr('target')" class="button" />
		<?php if (!$is_no_results_tab) { ?>
			<input type="submit" name="trajax" value="Export" class="button" />
			<input type="button" name="print" value="Print" class="button printbtn" />
			<input type="submit" name="trajax" value="PDF" class="button" />
			<?php
			if (tr_current_role_in(array('administrator', 'lab', 'labb'))) {
			?>
				<a title="Send Mail" class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'sendmail')) ?>">Send Mail</a>
				<a title="Auto Send Manifest" class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'settings')) ?>">Settings</a>
			<?php
			}
			?>
		<?php } ?>
	</form>
	<?php if ($act == 'View') {
		echo '<div class="viewhtml">';
		include('sample_manifest_html.php');
		echo '</div>';
	} ?>
</div>

<style>
	.viewhtml table {
		border-collapse: collapse;
		width: 100%;
	}

	.viewhtml table th {
		background: #ddd;
	}

	.viewhtml table td,
	.viewhtml table th {
		border: 1px solid #ddd;
	}

	.viewhtml table tr.no-result-warning td {
		background: #f8d7da;
		color: #721c24;
	}
</style>
<script>
	jQuery(function($) {
		$('body').on('click', '.printbtn', function() {
			$('.frameprint').remove();
			var $url = '?' + $('#formsearch').serialize() + '&trajax=print';
			$('body').append('<iframe class="frameprint" style="position:absolute;left:-99999px" src="' + $url + '"></iframe>');
			return false;
		}).on('submit', 'form.ajaxsaveform', function() {
			var $btn = $('input[type="submit"]', $(this));
			$btn.val('Saving...');
			$.ajax({
				data: $(this).serialize(),
				dataType: 'json',
				type: 'post',
				success: function(rs) {
					$btn.val('Save');
					if (rs.status == 'ok') {

					} else {
						alert('Error. Please try again!');
					}
				}
			});
			return false;
		})
	})
</script>