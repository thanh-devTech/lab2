<?php
ini_set('display_errors', 'Off'); // Disables displaying errors on the screen
ini_set('log_errors', 'On');

include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

foreach (trpdd_list_plate_styles($tes_type) as $k => $txt) {
	if ($k <= 2) continue;
	if (isset($_GET['rebuild']) && $_GET['rebuild'] > 0) {
		if ($k != $_GET['rebuild']) {
			continue;
		}
	}
	if (isset($_GET['plate']) && $_GET['plate'] > 0) {
		if ($k != $_GET['plate']) {
			continue;
		}
	}

	$imgsrc 	=  TRCPDD_URL . '/plate_maps/view/' . $k . '.png';
	$imagepath 	= TRCPDD_PATH . '/plate_maps/view/' . $k . '.png';
	$htmlsrc 	=  TRCPDD_PATH . '/plate_maps/view/' . $k . '.html';
	$htmlsrc_auto =  TRCPDD_PATH . '/plate_maps/view/' . $k . '_auto.html';
	echo '<h2 class="map_' . $k . '">' . $txt . '</h2>';
	echo '<div class="center">';


	if (file_exists($htmlsrc) && filesize($htmlsrc) > 100 && !isset($_GET['rebuild'])) {
		echo file_get_contents($htmlsrc);
	} else if (file_exists($htmlsrc_auto)  && filesize($htmlsrc_auto) > 100 && !isset($_GET['rebuild'])) {
		echo file_get_contents($htmlsrc_auto);
	} else if (file_exists($imagepath)) {
		echo '<a href="' . $imgsrc . '" target=_blank>';
		echo '<img src="' . $imgsrc . '" style="width:100%;height:auto"><br><br>';
		echo '</a>';
	} else {
		//build 
		ob_start();
		include(trpdd_get_file_path('plate', 'action_build_plate_map'));
		$content = ob_get_clean();

		if (strlen($content) > 100 && (!file_exists($htmlsrc_auto) || isset($_GET['rebuild']))) {
			file_put_contents($htmlsrc_auto, $content);
		}
		echo $content;
	}
	echo '</div>';
}
?>

<link rel='stylesheet' id='select2-css' href='<?php echo TRCPDD_URL . 'css/admin_plate.min.css?' . time() ?>' media='all' />
<style>
	.outerplate .header,
	.outerplate .cfooter {
		display: none;
	}

	.combinedblock {
		float: none;
	}

	table.platetbl tr td {
		width: 8%;
		height: 40px;
		text-align: center;
		font-size: 13px;
		border: 0.5px solid #ddd !important;
	}

	.combinecol_24 table.platetbl tr td {
		width: 4%;
	}

	table.platetbl tr:nth-child(2n) td {
		background: #f2f2f2;
	}


	table.platetbl td.ntc1,
	table.platetbl td.negative {
		background: #ffc522 !important;
	}

	table.platetbl td.ntc2,
	table.platetbl td.positive {
		background: darkorange !important;
	}

	tr.ntc2 td,
	tr.ntc4 td,
	.combinedblock tr td.color_orange {
		background: darkorange !important;
	}

	.combinedblock tr td.color_yellow {
		background: yellow !important;
	}

	.combinedblock tr td.color_green {
		background-color: #0cae0cc9 !important;
	}

	.scircle {
		display: inline-block !important;
		width: 18px !important;
		height: 18px !important;
		line-height: 18px !important;
		-webkit-border-radius: 50%;
		-moz-border-radius: 50%;
		border-radius: 50%;
		color: #fff;
		background: #000;
		padding: 0 !important;
		text-align: center !important;
		font-size: 10px !important;
	}

	.scircle.greencircle {
		background: green;
		color: #fff;
	}

	.scircle.redcircle {
		background: red;
		color: #fff;
	}

	.assen .scircle,
	.sscircle {
		float: right;
		width: 16px !important;
		height: 16px !important;
		line-height: 16px !important;
	}

	body .combinedblock,
	body .combinedblock.combinecol_12 {
		width: 100%;
		border: none;
	}

	.combinedblock .ccontent,
	.combinedblock .header,
	.plateblock .ccontent,
	.plateblock .header {
		padding: 0;
	}
</style>
<?php
exit;
