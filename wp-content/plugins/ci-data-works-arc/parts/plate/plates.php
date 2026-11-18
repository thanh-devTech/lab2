<?php
global $wpdb;
global $rack_uti_type;
global $rack_max6_fun;

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.2');
wp_enqueue_style('admin_plate', TRCPDD_URL . 'css/admin_plate.css', array(), time());
wp_enqueue_script('jquery-ui-autocomplete');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('jquery-ui-dialog');

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$is_plate_view      = false;
$user_id            = get_current_user_id();
$rack_ABC           = tr_get_option('ci_pdf_data', 'rack_ABC');
$rack_16            = tr_get_option('ci_pdf_data', 'rack_16');
$rack_6controls     = tr_get_option('ci_pdf_data', 'rack_6controls');
$ntc_at_end         = tr_get_option('ci_pdf_data', 'rack_ntc_at_end');
$allow_rack4s       = tr_get_option('ci_pdf_data', 'rack_4s_well');
$allow_rack4s_posneg = tr_get_option('ci_pdf_data', 'rack_4s_well_posneg');
$rack_uti_type      = tr_get_option('ci_pdf_data', 'rack_uti_type');
$rack_max6_fun      = tr_get_option('ci_pdf_data', 'rack_max6_fun');
$pcrcovid_n1_rp     = tr_get_option('ci_pdf_data', 'pcrcovid_n1_rp');
$rack_exporttype    = tr_get_option('ci_pdf_data', 'rack_exporttype');
$plate_coating      = tr_get_option('ci_pdf_data', 'plate_coating');
$can_delete			= tr_get_option('ci_pdf_data', 'rack_plate_ddel') == false;
$newurl             = '';
$max_well           = 96;
$first_ntc          = 1;
$second_ntc_pos     = $rack_ABC ? 2 : 13;
$ntc3               = 0;
$ntc4               = 0;


if ($rack_16) {
	$max_well       = 16;
	$first_ntc      = 16;
	$second_ntc_pos = 15;
} else if ($allow_rack4s_posneg) {
	$first_ntc      = 95;
	$second_ntc_pos = 93;
	$ntc3_pos       = 94;
	$ntc4_pos       = 96;
} else if ($rack_6controls) {
	$rack_ABC       = false;
	$second_ntc_pos = 93;
	$first_ntc      = 95;
	$ntc3           = 94;
	$ntc4           = 96;
} else if ($ntc_at_end) {
	$first_ntc      = 95;
	$second_ntc_pos = 96;
}


$today              = date_i18n('Y-m-d');
$datetimenow 		= date_i18n('Y-m-d H:i:s');

if (isset($_REQUEST['today'])) {
	$today = $_REQUEST['today'];
}
if (isset($_REQUEST['date'])) {
	$today = $_REQUEST['date'];
} else {
	$newurl = Tr_Base_Class_V6::link(array('date' => $today), false);
}

include(trpdd_get_file_path('plate', 'actions'));

$where = " pl_today='{$today}' ";
if ($plate_coating) {
	$expiry_date = date('Y-m-d', strtotime($today) - 86400 * 7);
	$expiry_24h = date('Y-m-d H:i:s', strtotime($datetimenow) - 86400);
	$where .= " OR (
		((ra.re_priority >= '{$expiry_date}' and (ra.re_completed ='' or ra.re_completed is null))
		or (ra.re_completed >= '{$expiry_24h}' ))
		and ra.re_is_uti_panel in (21,22)
	 ) ";
	//show until expiration
	//and (select count(*) from ci_rack_batches rb where rb.re_ID = ra.re_ID) <3 )";
}
$sql = "select pl.*,
        ra.*
        from ci_plates pl 
        left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
        where {$where}
        order by pl_number asc";

$plates = $wpdb->get_results($sql, ARRAY_A);

$combines = $wpdb->get_results("select * 
from ci_plates_combine 
where pl_today = '{$today}' 
order by pl_number asc", ARRAY_A);

if (isset($_GET['debug'])) {
	$can_delete = true;
}

?>
<div class="wrap">
	<h2>Plates</h2>

	<form method="get" style="float:left">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		<label>Date:</label>
		<input type="search" class="hasdate submitchange" name="date" value="<?php echo $today ?>" />
		<input type="submit" class="button bsty6" value="Go" />
	</form>
	<div style="float:left;margin-left:30px;">
		<?php if (!tr_get_option('ci_pdf_data', 'rack_plate_remove_combine')) { ?>
			<select id="combinesort" class="njs_select2">
				<option value="1">Interleaved</option>
				<option value="2">90 Degree Interlaced</option>
				<option value="3">Straight</option>
			</select>

			<a class="button combine384btn bsty1"><?php echo $rack_16 ? 'Combine 384' : 'Combine' ?></a>
			<?php if ($rack_16) {
			?>
				<a class="button combine96btn bsty2">Combine 96</a>
			<?php
			} else {
			?>
				<a class="button combinefull96btn bsty2">Combine full 96</a>
			<?php
			} ?>

			<a class="button exportbtn bsty3">Export</a>
		<?php } ?>
		<?php if ($can_delete) { ?>
			<a class="button deletebtn bsty6">Delete</a>
		<?php } ?>
		<a class="button lockbtn bsty5">Lock</a>
		<a class="button unlockbtn bsty4">UnLock</a>
	</div>
	<div class="clear"></div>
	<?php
	if (count($plates) == 0) {
		echo '<p>Results Not Found</p>';
	} else {
		$plates_384 = array();
		$plates_96_blood = array();
		$plates_96_wide = array();
		$plates_extractions = array();


		echo '<div style="width:3000px">';
		foreach ($plates as $row) {

			if (!$can_delete && is_null($row['re_ID'])) {
				continue;
			}

			//check completed plate coating
			if ($plate_coating && in_array($row['re_is_uti_panel'], array(21, 22)) && empty($row['re_completed']) && !empty($row['re_barcode'])) {
				$countb = $wpdb->get_var("select count(*) from ci_rack_batches rb where rb.re_ID = {$row['re_ID']}");
				$countletters = strlen($wpdb->get_var("select letters from ci_plate_coating where barcode = '{$row['re_barcode']}' "));

				if ($countletters > 0 && $countb == $countletters) {
					$lastcompleted = $wpdb->get_var("select max(created) from ci_plates_batches where pl_ID = {$row['pl_ID']} ");

					$wpdb->update('ci_racks', array('re_completed' => $lastcompleted), array('re_ID' => $row['re_ID']));

					if (strtotime($expiry_24h) > strtotime($lastcompleted)) {
						continue;
					}
				}
			}


			$show_BioRad_export = true;
			$plate_name = 'ELUTION PLATE ' . $row['pl_number'];


			$config_plate = trpdd_plate_map_config($row['re_is_uti_panel']);

			if (!empty($config_plate)) {


				if ($config_plate['type'] == 96 && isset($config_plate['add96'])) {
					global $ci_lab_count_plate_batches;
					$ci_lab_count_plate_batches = 0;
					if ($row['re_is_uti_panel'] == 82) {
						$plate_name = 'HpaII Digestion Plate ' . $row['pl_number'];
					}

					$row['show_export_96'] = false;
					include(trpdd_get_file_path('plate', 'view_96_req'));
					$count = ceil($ci_lab_count_plate_batches / $config_plate['add96map']['max']);

					for ($i1 = 1; $i1 <= $count; $i1++) {
						$row['sub_number'] = $i1;
						if ($row['re_is_uti_panel'] == 82) {
							$plate_name = 'ddPCR Plate ' . $i1;
						}

						$row['subwellsplate'] = $config_plate['add96map']['max'];
						$row['show_export_96'] = true;
						include(trpdd_get_file_path('plate', 'view_96_req'));
					}
					echo '<div class="clear"></div>';
					continue;
				} else if ($config_plate['type'] == 384) {
					if ($config_plate['rack'] != 96 || (isset($config_plate['noplaterack']) && $config_plate['noplaterack'])) {
						$plates_384[] = $row;
						continue;
					} else if ($config_plate['subwellsplate'] > 0) {
						//get count subplates
						$countsp = $wpdb->get_var("select count(*) from ci_plates_batches where pl_ID = '{$row['pl_ID']}' ");
						$countsub = ceil($countsp / $config_plate['subwellsplate']);
						for ($isub = 1; $isub <= $countsub; $isub++) {
							$row['sub_number'] = $isub;
							$row['subwellsplate'] = $config_plate['subwellsplate'];
							$plates_384[] = $row;
						}
						unset($row['sub_number']);
						unset($row['subwellsplate']);
					} else {
						$plates_384[] = $row;
					}
				} else if (isset($config_plate['is_wide']) && $config_plate['is_wide']) {
					$plates_96_wide[] = $row;
					continue;
				}
			} else if ($rack_uti_type == 14 && $row['re_is_uti_panel'] == 1) {
				$plates_384[] = $row;
				continue;
			} else if (in_array($row['re_is_uti_panel'], array(-5, 10, 11, 12, 13, 15, 30, 31, 32, -300, 38, 43, 44, 50))) {
				$plates_384[] = $row;
				continue;
			} else if (in_array($row['re_is_uti_panel'], array('not now'))) {
				$plates_96_wide[] = $row;
				continue;
			} else if ($row['re_is_uti_panel'] == 21 || $row['re_is_uti_panel'] == 22) {
				//blood FA / MF 
				$plates_96_blood[] = $row;
				continue;
			} else if ($row['re_is_uti_panel'] == 17 && $row['pl_blank1_text'] == 'plateAcc') {
				$plates_384[] = $row;
				continue;
			} else if (in_array($row['re_is_uti_panel'], array(17, 18))) {
				//continue;
				$show_BioRad_export = false;
				$plate_name = 'ELUTION ' . str_replace('plate', 'Plate ', $row['pl_blank1_text']);

				if ($row['pl_blank1_text'] == 'plateEX') {
					if (isset($plates_extractions[$row['re_ID']]['plateA'])) {
						$row['plateA'] = $plates_extractions[$row['re_ID']]['plateA'];
					}
					if (isset($plates_extractions[$row['re_ID']]['plateB'])) {
						$row['plateB'] = $plates_extractions[$row['re_ID']]['plateB'];
					}
					if (isset($plates_extractions[$row['re_ID']]['plateAcc'])) {
						$row['plateAcc'] = $plates_extractions[$row['re_ID']]['plateAcc'];
					}
					$plates_extractions[$row['re_ID']] = $row;
				} else if ($row['pl_blank1_text'] == 'plateA') {
					$plates_extractions[$row['re_ID']]['plateA'] = $row;
				} else if ($row['pl_blank1_text'] == 'plateB') {
					$plates_extractions[$row['re_ID']]['plateB'] = $row;
				} else if ($row['pl_blank1_text'] == 'plateAcc') {
					$plates_extractions[$row['re_ID']]['plateAcc'] = $row;
				}

				continue;
			}

			include(trpdd_get_file_path('plate', 'view_96'));

			if ($row['re_is_uti_panel'] == 36) {
				include(trpdd_get_file_path('plate', 'view_final_tmp_36'));
				echo '<div class="clear"></div>';
			}
		}
		echo '</div>';


		if (count($plates_96_blood) > 0) {
			include(trpdd_get_file_path('plate', 'view_96_blood'));
		}
		if (count($plates_96_wide) > 0) {
			include(trpdd_get_file_path('plate', 'view_96_wide'));
		}
		if (count($plates_extractions) > 0) {
			include(trpdd_get_file_path('plate', 'view_extractions'));
		}

		if (count($plates_384) > 0) {
			include(trpdd_get_file_path('plate', 'view_384'));
		}


		$more_q_field = '';
		if ($allow_rack4s) {
			$more_q_field .= ', iparent ';
		}

		foreach ($combines as $row) {
			include(trpdd_get_file_path('plate', 'combine_384'));
		}
	}

	?>
</div>

<div style="display: none" id="dialog-confirm-ntc" title="Select NTC?">
	Select NTC to move:
</div>

<div style="display:none" id="dialog_pass_qc" title="Reason PASS?">
	<textarea id="reason_pass" style="width:100%;height:80px"></textarea>
</div>

<style>
	<?php
	foreach ($colors as $i => $color) {
		echo '.color_' . $i . ' .header, .combinedblock td.color_' . $i . '{background:' . $color . '}';
	}
	?>
</style>
<script>
	var $ntc2_texts = <?php echo json_encode($ntc2_texts) ?>;
	<?php if (!empty($newurl)) {
		echo 'window.history.pushState("", "", \'' . $newurl . '\');';
	} ?>
</script>
<script src="<?php echo TRCPDD_URL . 'js/admin_plate.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>