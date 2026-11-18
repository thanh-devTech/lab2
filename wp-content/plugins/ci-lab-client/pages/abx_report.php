<?php
global $wpdb;

wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');

$fromdate = isset($_REQUEST['fdate']) ? $_REQUEST['fdate'] : '';
$todate = isset($_REQUEST['tdate']) ? $_REQUEST['tdate'] : '';
?>
<div class="wrap">

	<form method="get">
		<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		<label>Test</label>
		<input type="text" name="t" value="<?php echo isset($_GET['t']) ? $_GET['t'] : '' ?>" />
		<label>From</label>
		<input type="search" name="fdate" id="from" class="hasdate" value="<?php echo $fromdate ?>" />
		<label>To</label>
		<input type="search" name="tdate" id="to" class="hasdate" value="<?php echo $todate ?>" />
		<script>
			jQuery(function($) {
				$(".hasdate").datepicker({
					changeMonth: true,
					changeYear: true,
					numberOfMonths: 1,
					dateFormat: 'yy-mm-dd'
				});
			})
		</script>
		<input type="submit" class="button" value="Search" />
	</form>
	<?php
	$test = isset($_GET['t']) && !empty($_GET['t'])? trim($_GET['t']) : '';
	if (!empty($test)) {

		$dir = WP_CONTENT_DIR . '/results/html/';
		$fdir = opendir($dir);
		$tfrom = strtotime($fromdate);
		$tto = strtotime($todate);
		if ($fdir) {
			$count = 0;
			echo 'checking...';
			while ($f = readdir($fdir)) {
				if (in_array($f, array('.', '..')) || stripos($f, 'pcr_3') === false) continue;

				$filepath = $dir . $f;
				$fmtime = filemtime($filepath);
				if ($fmtime < $tfrom || $fmtime > $tto) continue;
				$content = file_get_contents($filepath);
				preg_match("/<td>[^\>]*iconpill[^\<]*(" . $test . ")[^<]*\<\/td\>/i", $content, $matches);
				if (isset($matches[1])) {
					$count++;
				}
			}
			echo 'count: ' . $count;
		}
	}
	?>
</div>