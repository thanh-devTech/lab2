<?php
global $wpdb, $current_role;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date  = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-d');
$to_date    = !empty($_GET['to']) ? $_GET['to'] : $from_date;
$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;

$conditions = trpdd_get_batch_conditions(0, 'blood');
$sql_cli 	= "select * from ci_clinic where {$conditions['where_clinic']}";
$clinics 	= $wpdb->get_results($sql_cli);
if(count($clinics)==1){
	$cli_ID = $clinics[0]->cID;
}

if (isset($_GET['trajax']) && stripos($_GET['trajax'], 'export') !== false) {
	//export pdf
	try {
		$filename = 'export.pdf';
		ob_start();
		include(TRCPDD_PATH . 'reports/health_wellness.php');
		$content = ob_get_clean();
		$content = trpdd_pdf_html($content, 'Export', false);
		if (isset($_GET['html'])) {
			echo $content;
			exit;
		}
		header('Content-Type: application/pdf');
		if (!isset($_GET['view'])) {
			header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
		}
		trpdd_build_pdf($content, array('size' => 'letter', 'filename' => $filename));
	} catch (Exception $e) {
		$ci_time_build_pdf = microtime(true) - $start_time;
		Tr_Session_Class::add_error("Error while export PDF file. " . $e->getMessage());
	}
	exit;
}
?>

<div class="wrap">
	<h2 class="center">Health Wellness</h2>

	<form method="get" style="width:600px;margin:20px auto" id="formsearch">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		From Date: <input type="text" class="hasdate" name="from" style="width:100px" value="<?php echo isset($from_date) ? $from_date : '' ?>" required />
		<label id="summarytodate">To:</label>
		<input type="text" class="hasdate today" name="to" style="width:100px" value="<?php echo isset($to_date) ? $to_date : '' ?>" required />
		<?php
		if (isset($clinics) && count($clinics)>1) {
		?>
			<select name="cli_ID" class="select2" style="max-width:350px" data-placeholder="Select Clinic" class="njs_select2">
				
				<?php
				foreach ($clinics as $cli) {
					$selected = $cli->cID == $cli_ID ? 'selected' : '';
					echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
				}
				?>
			</select>
		<?php
		}
		?>
		<input type="submit" onclick="jQuery('#formsearch').attr('target','')" class="button-primary" value="Report" />
		<input type="submit" onclick="jQuery('#formsearch').attr('target','_blank')" name="trajax" value="Export" class="button" />
	</form>

	<?php
	if (!empty($from_date) && $cli_ID > 0) {
		include(TRCPDD_PATH . 'reports/health_wellness.php');
	}
	?>
	<style>
		.wrappdf{
			padding:20px 0;
		}
	</style>
</div>