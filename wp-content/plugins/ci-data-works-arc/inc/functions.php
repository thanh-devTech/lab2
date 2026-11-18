<?php

function tr_get_new_assen($type = 'tox', $mainsite = false, $barcode_key = '', $lab = null)
{
	global $wpdb;

	if (isset($_GET['debugkey'])) {
		var_dump($lab);
	}

	if (is_array($lab) && empty($barcode_key)) {
		if (!empty($lab['lab_dba_init'])) {
			$barcode_key = $lab['lab_dba_init'];
		} else if (isset($lab['lab_ID']) && $lab['lab_ID'] > 0) {
			$keylab = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$lab['lab_ID']}");
			if (strlen($keylab) >= 3) {
				$barcode_key = $keylab;
			}
		}
	} else if ($lab > 0 && $lab != CI_Base_Lab::get_current_lab_id() && empty($barcode_key)) {
		$labid = CI_Base_Lab::get_current_lab_id();
		$keylab = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID = {$labid}");
		if (strlen($keylab) >= 3) {
			$barcode_key = $keylab;
		}
	}

	if ($mainsite && function_exists('get_blog_option') && defined('BLOG_ID_CURRENT_SITE') && BLOG_ID_CURRENT_SITE > 0) {
		$settings = get_blog_option(BLOG_ID_CURRENT_SITE, 'ci_pdf_data', array());
	} else {
		$settings = get_option('ci_pdf_data', array());
	}

	$indexkey = 'randoum_create_r_index';
	if (!empty($barcode_key) && $barcode_key != $settings['barcode_key']) {
		if ($type == 'study') {
			$indexkey = 'randoum_create_r_index_study';
		} else {
			$indexkey = 'randoum_create_r_index_' . $barcode_key;
		}
	} else {
		$barcode_key = $settings['barcode_key'];
	}

	if ($mainsite && function_exists('get_blog_option') && defined('BLOG_ID_CURRENT_SITE') && BLOG_ID_CURRENT_SITE > 0) {
		$r_index  = get_blog_option(BLOG_ID_CURRENT_SITE, $indexkey, 1);
	} else {
		$r_index  = get_option($indexkey, 1);
	}

	if (empty($barcode_key)) {
		return '';
	}


	$randnum = '';
	$num_length = $settings['barcode_length'] > 5 ? $settings['barcode_length'] : 8;
	$start_number = intval($settings['barcode_start']);
	if ($r_index < $start_number) $r_index = $start_number;

	for ($i = 0; $i < 1500; $i++) {
		$randnum = $barcode_key . sprintf('%0' . $num_length . 's', $r_index);
		$exits   = false;
		foreach (tr_pdd_get_batch_types() as $btype) {
			$table = tr_pdd_get_table_by_btype($btype);
			$exits = $wpdb->get_var("select mid from {$table} where assen = '{$randnum}' ");
			if ($exits) {
				break;
			}
		}

		if (!$exits) {
			break;
		} else {
			$r_index++;
			$randnum = '';
		}
	}

	if ($mainsite && function_exists('get_blog_option')) {
		update_blog_option(BLOG_ID_CURRENT_SITE, $indexkey, intval($r_index) + 1, 'no');
	} else {
		update_option($indexkey, intval($r_index) + 1, 'no');
	}



	return $randnum;
}

function tr_pdd_get_blogid($lab_id)
{
	global $wpdb;
	if ($lab_id == 0) return 0;
	$blogid = $wpdb->get_var("select lab_site_id from ci_labs where lab_ID = {$lab_id} ");
	return $blogid;
}

function tr_yes_no($fname, $value, $atts = array())
{
	if (!isset($atts['attr'])) $atts['attr'] = '';
	echo '<select name="' . $fname . '" ' . $atts['attr'] . '>';
	foreach (array('1' => 'Yes', '0' => 'No') as $k => $vl) {
		$selected = ($k == $value) ? 'selected' : '';
		echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
	}
	echo '</select>';
}

function tr_yes_no_radio($fname, $value, $atts = array())
{
	$values = array('1' => 'Yes', '0' => 'No');
	foreach ($values as $k => $vl) {
		echo '<label><input type="radio" name="' . $fname . '" value="' . $k . '" ' . checked($value, $k, false) . ' ' . $atts['attr'] . '/> ' . $vl . '</label>';
	}
}

function tr_select_option($fname, $options, $value = '', $atts = array())
{
	if (!isset($atts['valuekey'])) $atts['valuekey'] = false;
	if (!is_array($options)) {
		$option_str = $options;
		$options = array('' => 'Select');
		foreach (explode('|', $option_str) as $line) {
			$arr = explode(',', $line);
			if (count($arr) == 2) {
				$options[trim($arr[0])] = trim($arr[1]);
			}
		}
	}

	if (!isset($atts['id']) || empty($atts['id'])) {
		$atts['id'] = sanitize_title($fname);
	}
	if (isset($options['']) && isset($_REQUEST['act']) && $_REQUEST['act'] == 'print') {
		$options[''] = '';
	}
	if (!isset($atts['attr'])) {
		$atts['attr'] = '';
	}
	if (!isset($atts['class'])) {
		$atts['class'] = '';
	}
	$disabled_options = isset($atts['disabled_options']) ? (array)$atts['disabled_options'] : [];

	echo '<select name="' . $fname . '" id="' . $atts['id'] . '" ' . $atts['attr'] . ' class="js_select2 ' . $atts['class'] . '">';
	if (isset($atts['addselect']) && $atts['addselect']) {
		echo '<option value="">Select</option>';
	}
	foreach ($options as $k => $vl) {
		if (is_array($vl)) {
			echo ' <optgroup label="' . $k . '">';
			foreach ($vl as $k2 => $vl2) {
				if ($atts['valuekey']) {
					$k2 = $vl2;
				}
				$selected = ($k2 == $value) ? 'selected' : '';
				echo '<option value="' . $k2 . '" ' . $selected . '>' . stripslashes($vl2) . '</option>';
			}
			echo '</optgroup>';
		} else {
			if ($atts['valuekey']) {
				$k = $vl;
			}

			$selected = ($k == $value) ? 'selected' : '';
			if (stripos($fname, 'state') !== false && isset($_REQUEST['act']) && $_REQUEST['act'] == 'print') {
				$vl = $k;
			}
			$is_disabled = in_array($k, $disabled_options);
			$disabled_attr = $is_disabled && $value !== 'hold' ? 'disabled' : '';
			echo '<option value="' . $k . '" ' . $selected . ' ' . $disabled_attr . '>' . stripslashes($vl) . '</option>';
		}
	}
	echo '</select>';
}

function tr_radio($fname, $options, $value = '', $atts = array())
{
	if (!isset($atts['attr'])) $atts['attr'] = '';
	if (!is_array($options)) $options = array();
	echo '<span class="group_radio">';
	foreach ($options as $k => $vl) {
		echo '<label><input type="radio" name="' . $fname . '" value="' . $k . '" ' . checked($value, $k, false) . ' ' . $atts['attr'] . '/> ' . $vl . '</label>';
	}
	echo '</span>';
}

function tr_signature($args, $url = '')
{

	// loading signature javascript
	wp_enqueue_script('signature-pad', TRCPDD_URL . 'js/signature_pad.min.js', array(), TRCPDD_VS, false);

	$width = 300;
	$height = 130;
	$clear = true;
	$name = '';
	extract(wp_parse_args($args, array(
		'width' => '300',
		'height' => '130',
		'required' => false,
		'value' => '',
		'validation_error' => false,
		'name' => '',
		'url' => '',
		'clear' => true,
	)));
	$atts = array();
	$atts['class'] = 'signature_div hide_sign';
	$atts['aria-invalid'] = isset($validation_error) && $validation_error ? 'true' : 'false';
	$atts['value'] = isset($value) ? $value : '';
	$atts['type'] = 'hidden';
	$atts['name'] = isset($name) ? $name : '';

	$classes = '';
	if (isset($required) && $required) {
		$classes .= ' required';
		$atts['required'] = 'required';
	}

	$tt = '';
	foreach ($atts as $k => $vl) {
		$tt .= ' ' . $k . '="' . $vl . '" ';
	}
	$atts = $tt;

	$sigid = str_replace("-", "_", sanitize_html_class($name));
	$clear_btn = $clear ? '<input class="button clear_btn" type="button" value="Clear"/>' : '';


	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s"><input %2$s id="wpcf7_%4$s_input"/>%3$s
		<canvas id="wpcf7_%4$s_signature" class="%4$s" width="%5$s" height="%6$s"></canvas>' . $clear_btn . '</span>',
		$sigid . ' signature_canvas ' . $classes,
		$atts,
		$validation_error,
		$sigid,
		$width,
		$height
	);

	// script needs to be added for each signature field
	ob_start();
?>
	<script type="text/javascript">
		jQuery(function($) {
			var $swrap = $('.<?php echo $sigid ?>.signature_canvas');
			var $input = $("input.hide_sign", $swrap);
			var $cavas = document.querySelector("#wpcf7_<?php echo $sigid ?>_signature");
			if (window.devicePixelRatio) {
				var hidefContext = $cavas.getContext('2d');
				var hidefCanvasWidth = $($cavas).attr('width');
				var hidefCanvasHeight = $($cavas).attr('height');

				$($cavas).attr('width', hidefCanvasWidth * window.devicePixelRatio);
				$($cavas).attr('height', hidefCanvasHeight * window.devicePixelRatio);
				$($cavas).css('width', hidefCanvasWidth);
				$($cavas).css('height', hidefCanvasHeight);
				hidefContext.scale(window.devicePixelRatio, window.devicePixelRatio);
			}

			var signaturePad = new SignaturePad($cavas, {
				onEnd: function() {
					$input.val(signaturePad.toDataURL());
				}
			});
			$swrap.data('signaturepad', signaturePad);
			<?php if (!empty($url)) echo 'signaturePad.fromDataURL("' . $url . '?t=' . time() . '");'; ?>

			$(".clear_btn", $swrap).click(function() {
				signaturePad.clear();
			});

			$($swrap).parents("form").submit(function(evt) {
				if (!signaturePad.isEmpty()) {
					$input.val(signaturePad.toDataURL());
				} else {
					$input.val("");
					var attr = $input.attr("required")
					if (typeof attr !== typeof undefined && attr !== false) {
						alert("Signature is required");
						evt.preventDefault();
						return false;
					}
				}
			})
		});
	</script>
<?php
	$html .= ob_get_clean();

	return $html;
}


function tr_get_next_batch_id($default_batch_id = false)
{
	global $wpdb;
	$data = $wpdb->get_row("select batch_id,doc, count(batch_id) as batch_count from ci_batches group by batch_id order by batch_id desc limit 1");

	$batch_id = $data->batch_id;
	if ($default_batch_id !== false) {
		$batch_id = $default_batch_id;
	}

	return compact('batch_id');
}

function tr_get_lab_id_by_clinic($cli_ID)
{
	global $wpdb;
	return $wpdb->get_var("select `cli_lab-ID` from ci_clinic where cID = {$cli_ID}");
}

function tr_get_lab_short($lab_ID)
{
	global $wpdb;
	$row = $wpdb->get_row("select lab_dba_init,lab_dba,lab_name from ci_labs where lab_ID = {$lab_ID}");
	$lab = '';
	if ($row) {
		$lab = !empty($row->lab_dba_init) ? $row->lab_dba_init : $row->lab_dba;
		if (empty($lab)) {
			$lab = $row->lab_name;
		}
	}
	return $lab;
}

function tr_pdd_checkDrug($report_id, $d, $has_pending = false, $is_qc = false, $qc_number = 0)
{
	global  $cutoffs, $report_these_drugs;

	$d['ComponentName'] = trim($d['ComponentName']);

	if (empty($d['ComponentName'])) {
		$d['ComponentName'] = trim($d['AnalyteName']);
	}
	$expl = explode(" ", $d['ComponentName']);
	$num_end = end($expl);
	array_pop($expl);

	if ($is_qc && $num_end == '2') {
		$drug = trim(strtoupper($d['ComponentName']));
	} else {
		$drug = $d['ComponentName'];
		if ($num_end === '1') {
		}
		$drug = preg_replace("/\s?\-?[0-9]$/", '', $drug);
		$drug = preg_replace("/\s?\-?[A-Z][0-9]$/", '', $drug);
		$drug = preg_replace("/\s?\-[A-Z][0-9]?$/", '', $drug);
		$drug = trim(strtoupper($drug));
	}

	$drug = str_replace(' COOH', '-COOH', $drug);

	if (empty($drug)) {
		return;
	}
	if ($is_qc && $d['CalculatedConcentration'] === '') {
		return;
	}

	if ($is_qc && $qc_number > 1) {
		$drug .= '-' . $qc_number;
	}

	if (!in_array($drug, $report_these_drugs)) {
		$report_these_drugs[] = $drug;
		$skipDrug = false;
	} else {
		return;
	}

	$matrix = strtolower(isset($d['Matrix']) ? $d['Matrix'] : '');
	$drug_cut = strtoupper($drug . $d['instrument']);
	if (!isset($cutoffs[$drug_cut])) {
		$drug_cut = strtoupper($drug . 'MS1');
	}
	$cutoff_vl = isset($cutoffs[$drug_cut]['cutoff']) ? $cutoffs[$drug_cut]['cutoff'] : '';

	if (substr($matrix, 0, 4) == "oral") $matrix = "oral";

	$isnan = stripos($d['CalculatedConcentration'], 'N/A') !== false;
	if (!$isnan && stripos($d['CalculatedConcentration'], 'NA') !== false) {
		$isnan = true;
	}
	if (!$isnan && stripos($d['CalculatedConcentration'], 'N.A') !== false) {
		$isnan = true;
	}
	if ($isnan) {
		$arr = array(
			"drug" => $drug,
			"conc" => "",
			"pos-neg" => "NEG",
			"cutoff" => $cutoff_vl,
			"comments" => ""
		);

		if (isset($d['instrument']) && !empty($d['instrument'])) {
			$arr['instrument'] = $d['instrument'];
		}

		if (!$skipDrug) tr_pdd_insertDrug($report_id, $arr, $has_pending);
	} else {
		//$max_cutoff = ((float)$cutoffs[$drug_cut]['cutoff']);
		if ($is_qc || stripos($d['SampleName'], 'QC') > 0) {
			$skipDrug = false;
		}

		$calc_conc = $d['CalculatedConcentration'];
		$calc_conc = str_replace(',', '', $calc_conc);
		$calc_conc = floatval($calc_conc);
		if (!empty($calc_conc)) {
			$calc_conc = round($calc_conc, 3);
		}



		$pos_neg = '';
		if ($cutoff_vl !== '') {
			$pos_neg = ((float)$d['CalculatedConcentration'] < $cutoffs[$drug_cut]['cutoff']) ? "NEG" : "POS";
		}


		$comments = ($pos_neg == "NEG") ? "Detected below cutoff" : "";
		$arr = array(
			"drug" => $drug,
			"conc" => $calc_conc,
			"pos-neg" => $pos_neg,
			"cutoff" => $cutoff_vl,
			"comments" => $comments,
		);

		if (isset($d['instrument']) && !empty($d['instrument'])) {
			$arr['instrument'] = $d['instrument'];
		}
		if (!$skipDrug) tr_pdd_insertDrug($report_id, $arr, $has_pending);
	}
}

function tr_pdd_insertDrug($report_id, $arr, $has_pending = false)
{
	global $wpdb;
	$arr['report_id'] = $report_id;

	if (empty($arr['conc'])) {
		$arr['conc'] = '';
	}

	if (empty($arr['cutoff'])) {
		$arr['cutoff'] = '';
	}
	if (empty($arr['pos-neg'])) {
		$arr['pos-neg'] = 'NEG';
	}


	$rs_id = $wpdb->get_var("select id from ci_results where report_id = {$report_id} and drug='{$arr['drug']}' ");


	if ($rs_id > 0) {
		$where = array(
			'id' => $rs_id,
		);
		if ($has_pending) {
			$where['pos-neg'] = 'PEN';
		}
		$rs = $wpdb->update('ci_results', $arr, $where);
	} else {
		$rs = $wpdb->insert('ci_results', $arr);
		$rs_id = $wpdb->insert_id;
	}
	return ($rs_id > 0) ? true : false;
}



function tr_pdd_parseComments($comment, $get)
{
	if (strpos($comment, "Sample Comment") !== false) {
		$comment = str_replace("Sample Comment", strtoupper($get['medication']), $comment);
	}

	return $comment;
}

function tr_pdd_get_drug_class_comments($arr, $medications, $matrix, $in_panel_tests_requested = null, $ci_cutoffs = null)
{
	global $wpdb, $report, $tr_cache_queries;
	if (!is_array($tr_cache_queries)) {
		$tr_cache_queries = array();
	}
	if (!isset($arr['pos-neg'])) {
		$arr['pos-neg']  = '';
	}
	$arr['old_pos-neg'] = isset($arr['pos-neg']) ? $arr['pos-neg'] : '';
	$has_conc = isset($arr['conc']);

	if (!isset($arr['conc']) || $arr['conc'] === 0) {
		$arr['conc'] = '';
	} else {
		$arr['conc'] = str_replace(',', '', $arr['conc']);
	}

	$d  = $drug_strtoupper = strtoupper($arr['drug']);
	$d_cutoff = $d . $report['instrument'];

	if (!empty($arr['instrument'])) {
		$d_cutoff = $d . $arr['instrument'];
	}

	$d_cutoff = strtoupper($d_cutoff);

	if (!isset($ci_cutoffs[$d_cutoff])) {
		$d_cutoff = $d . 'MS1';
	}


	//always use cutoff in panels
	if (isset($ci_cutoffs[$d_cutoff]['cutoff']) && $ci_cutoffs[$d_cutoff]['cutoff'] > 0) {
		$arr['cutoff'] = $ci_cutoffs[$d_cutoff]['cutoff'];
	}

	$arr['limit'] = $ci_cutoffs[$d_cutoff]['limit'];
	$arr['conc_number'] = str_replace(array('>', ','), '', $arr['conc']);



	$max_cutoff = ((float)$ci_cutoffs[$d_cutoff]['limit']);
	if (!empty($arr['conc']) && strpos($arr['conc'], '>') === false && $max_cutoff > 0) {
		$arr['conc'] = ((float)$arr['conc_number'] > $max_cutoff) ? ">" . $max_cutoff : $arr['conc'];
	}
	if (empty($arr['conc'])) {
		$arr['conc'] = $arr['conc_number'];
	}
	if (($arr['pos-neg'] == '' || $arr['pos-neg'] == 'PEN') && $arr['conc_number'] == '') {
		$arr['conc'] = '';
	} else {
		$arr['pos-neg'] = ((float)$arr['conc_number'] < $arr['cutoff'] || empty($arr['conc_number'])) ? "NEG" : "POS";
		if ($arr['conc_number'] == $arr['cutoff'] && strpos($arr['conc'], '<') !== false) {
			$arr['pos-neg'] = "NEG";
		}
	}


	//end


	$is_blue = false;
	foreach ($medications['meds'] as $med) {
		if ($drug_strtoupper == strtoupper($med)) {
			$is_blue = true;
			break;
		}
	}

	if (!$is_blue && $drug_strtoupper == 'NORHYDROCODONE' && in_array('HYDROCODONE', $medications['meds'])) {
		$is_blue = true;
	}

	$med = $drug_strtoupper;
	if (isset($medications['inc'][$drug_strtoupper]) && !empty($medications['inc'][$drug_strtoupper])) {
		$med = strtoupper($medications['inc'][$drug_strtoupper]);
	}

	if ($is_blue) {
		if ($drug_strtoupper == 'NORDIAZEPAM' || $drug_strtoupper == 'TEMAZEPAM') {
			if (isset($medications['inc'][$drug_strtoupper]) && $med == 'OXAZEPAM') {
				$is_blue = false;
			}
		}
		$arr['is_blue'] = $is_blue;
	}

	$where_drug = " and anticipated='{$drug_strtoupper}' ";

	//get pos comment
	$arr['posc'] = '';
	$arr['med'] = $med;
	$sql = "select * from `ci_drug_logic` where medication='{$med}' {$where_drug}  and concent like '%>%' and detected!='Not Detected' ";

	if (isset($tr_cache_queries[$sql])) {
		$rs = $tr_cache_queries[$sql];
	} else {
		$rs = $wpdb->get_row($sql, ARRAY_A);
		$tr_cache_queries[$sql] = $rs;
	}

	if (!$rs) {
		$sql = "select * from `ci_drug_logic` where anticipated='{$drug_strtoupper}'  and concent like '%{$drug_strtoupper}%>%' and detected!='Not Detected' ";

		if (isset($tr_cache_queries[$sql])) {
			$rs = $tr_cache_queries[$sql];
		} else {
			$rs = $wpdb->get_row($sql, ARRAY_A);
			$tr_cache_queries[$sql] = $rs;
		}
	}
	if (!$rs && !in_array($drug_strtoupper, array('NORDIAZEPAM', 'OXAZEPAM', 'TEMAZEPAM'))) {
		$sql = "select * from `ci_drug_logic` where medication='{$med}'  and concent like '%>%' and detected!='Not Detected' ";
		if (isset($tr_cache_queries[$sql])) {
			$rs = $tr_cache_queries[$sql];
		} else {
			$rs = $wpdb->get_row($sql, ARRAY_A);
			$tr_cache_queries[$sql] = $rs;
		}
	}

	if ($rs && !empty($rs['comment'])) {
		$arr['posc'] = tr_pdd_parseComments($rs['comment'], $rs);
	}
	//end get pos comment

	if (!isset($arr['is_blue'])) {
		$arr['is_blue'] = false;
	}

	//parse comments
	if (empty($arr['comments']) && ((!empty($arr['conc']) && strpos($arr['conc'], '>') !== false) || $arr['pos-neg'] == 'POS' || $arr['is_blue'])) {
		if (!empty($arr['posc'])) {
			$arr['comments'] = $arr['posc'];
		} else if (!$rs) {
			$arr['is_blue'] = false;
		}
	}

	if (strtoupper($arr['pos-neg']) == 'NEG' &&  !$arr['is_blue']) {
		//$arr['comments'] = '';
	}

	if (isset($arr['group']) && stripos($arr['group'], 'Illicits') !== false && strtoupper($arr['pos-neg']) == 'POS') {
		$arr['is_red'] = true;
		$arr['class'] .= ' bg_red ';
	} else if (stripos($arr['comments'], "ILLICIT") !== false) {
		//$arr['is_red'] = true;
		//$arr['class'] .= ' bg_red ';
	}


	//get detection
	$concent = strtoupper($arr['pos-neg']) == 'NEG' ? '<' : '>';

	$sql = "select detected,`detected_oral` from `ci_drug_logic`
                                            where medication='{$med}'
                                            {$where_drug}
                                            and detected !='Not Detected'
                                            and UPPER(concent) LIKE '%{$concent}%' limit 1";

	if (isset($tr_cache_queries[$sql])) {
		$Detection = $tr_cache_queries[$sql];
	} else {
		$Detection = $wpdb->get_row($sql, ARRAY_A);
		$tr_cache_queries[$sql] = $Detection;
	}

	if (!$Detection) {

		$sql = "select detected,`detected_oral` from `ci_drug_logic`
                                            where 1
                                            {$where_drug}
                                            and detected !='Not Detected'
                                            and UPPER(concent) LIKE '%{$concent}%' limit 1";

		if (isset($tr_cache_queries[$sql])) {
			$Detection = $tr_cache_queries[$sql];
		} else {
			$Detection = $wpdb->get_row($sql, ARRAY_A);
			$tr_cache_queries[$sql] = $Detection;
		}
	}

	if (isset($_GET['debug3'])) {
		$arr['tt'] .= $sql;
	}


	$arr['detection'] = ($matrix == 'oral') ? $Detection['detected_oral'] : $Detection['detected'];

	//check comments
	if ($arr['comments'] == 'Detected' || $arr['comments'] == 'Detected below cutoff') {
		$arr['comments'] = '';
	}


	//check exclude
	$arr['exclude'] = false;
	if (!is_array($in_panel_tests_requested)) {
		$in_panel_tests_requested = array();
	}

	$drug_strtoupper = trim($drug_strtoupper);
	$drug_strtoupper2 = !empty($arr['te_abrev']) ? strtoupper($arr['te_abrev']) : $drug_strtoupper;
	$drug_strtoupper3 = !empty($arr['te_abrev2']) ? strtoupper($arr['te_abrev2']) : $drug_strtoupper;
	if (strpos($drug_strtoupper, '(') > 0) {
		$drug_strtoupper3 = substr($drug_strtoupper, 0, strpos($drug_strtoupper, '('));
		$drug_strtoupper3 = trim($drug_strtoupper3);
	}

	if (
		stripos($arr['drug'], 'Ethanol*') === false
		&& count($in_panel_tests_requested) > 0
		&& !in_array($drug_strtoupper, $in_panel_tests_requested)
		&& !in_array($drug_strtoupper2, $in_panel_tests_requested)
		&& !in_array($drug_strtoupper3, $in_panel_tests_requested)
	) {
		if (isset($_GET['debu2g'])) {
			var_dump($in_panel_tests_requested);
			echo  "Not in panel: {$drug_strtoupper}, {$drug_strtoupper2}, {$drug_strtoupper3}";
		}
		$arr['exclude'] = true;
	}

	//check #9 Amphetamine,Methamphetamine
	if ($drug_strtoupper == 'METHAMPHETAMINE') {
		if (in_array('METHAMPHETAMINE D/L ISOMERS', $in_panel_tests_requested)) {
			$arr['exclude'] = false;
		}
		if (in_array('AMPHETAMINE', $in_panel_tests_requested)) {
			$arr['is_blue'] = false;
		}
	} else if ($drug_strtoupper == 'AMPHETAMINE') {
		if (in_array('METHAMPHETAMINE', $in_panel_tests_requested) || in_array('METHAMPHETAMINE D/L ISOMERS', $in_panel_tests_requested)) {
			$arr['exclude'] = false;
		}
	} else if ($drug_strtoupper == 'MORPHINE') {
		if (strtoupper($medications['inc']['MORPHINE']) == 'HYDROMORPHONE') {
			$arr['exclude'] = true;
		}
	} else if ($drug_strtoupper == 'AMITRIPTYLINE' && in_array('NORTRIPTYLINE', $in_panel_tests_requested)) {
		//$arr['exclude'] = true;
	}

	if (isset($_GET['debugshowall'])) {
		$arr['exclude'] = false;
	}

	//fix $arr['conc']
	if (
		!empty($arr['cutoff'])
		&& ($arr['conc'] === '' || strpos($arr['conc'], '<') !== false || $arr['conc'] < $arr['cutoff'])
		&& strtoupper($arr['pos-neg']) == 'NEG'
	) {
		$arr['conc'] = '<' . $arr['cutoff'];
	}

	if ($arr['pos-neg'] == '' && $has_conc) {
		$arr['detection'] = '';
		if (empty($arr['comments']))
			$arr['comments'] = 'Unable to Report';
	} else if ($arr['pos-neg'] == 'POS') {
		$arr['red_color'] = true;
	}

	if ($arr['old_pos-neg'] == 'CAN') {
		$arr['pos-neg'] = $arr['old_pos-neg'];
	} else if (!empty($arr['pos-neg']) && $arr['old_pos-neg'] != $arr['pos-neg']) {
		$wpdb->update('ci_results', array('pos-neg' => $arr['pos-neg']), array('id' => $arr['id']));
	}


	return $arr;
}


function trpdd_only_number($value)
{
	if (empty($value)) {
		return false;
	}
	return str_replace(array('-', '>', '<', '='), '', $value) === $value;
}
/*
 * $report array: pa_gender, pa_dob, fasting, doc
 */


function tr_pdd_parse_medications($medications, $drug_pairs)
{
	global $wpdb, $tr_cache_ps_medis;

	if (!is_array($tr_cache_ps_medis)) {
		$tr_cache_ps_medis = array();
	}

	$medications_inc = array();
	if (!is_array($medications)) {
		$medications = str_replace(', ', ',', $medications);
		$medications = explode(',', $medications);
	}


	$meds = implode("','", $medications);
	$sql = "select anticipated,medication from ci_drug_logic where medication in ('{$meds}')";

	$md5 = md5($sql);
	if (isset($tr_cache_ps_medis[$md5])) {
		$anticipateds = $tr_cache_ps_medis[$md5];
	} else {
		$anticipateds = $wpdb->get_results($sql);
		$tr_cache_ps_medis[$md5] = $anticipateds;
	}

	foreach ($anticipateds as $row) {
		$med = strtoupper($row->medication);
		$drug = trim(strtoupper($row->anticipated));

		$medications[] = $drug;
		if (($drug == 'AMPHETAMINE' && $med != 'ADDERALL') || $drug == '') continue;


		if (isset($drug_pairs[$drug]) && is_array($drug_pairs[$drug])) {
			foreach ($drug_pairs[$drug] as $kd) {
				$kd = strtoupper($kd);

				if ($kd == 'AMITRIPTYLINE' && $med == 'NORTRIPTYLINE') {
					continue;
				}
				$medications[] = $kd;
				$medications_inc[$kd] = $med;
			}
		}

		$medications_inc[$drug] = $med;
	}
	$medications = array_map('trim', $medications);
	$medications = array_map('strtoupper', $medications);

	if (in_array('XANAX', $medications)) {
		$medications[] = 'ALAPROZALAM';
	}

	$medications = array_unique($medications);
	foreach ($medications as $k => $med) {
		if (empty($med)) {
			unset($medications[$k]);
		}
	}

	return array('meds' => $medications, 'inc' => $medications_inc);
}

function tr_pdd_get_test_outdate($date)
{
	global $wpdb;

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}

	$sql = "select te_name from ci_test_panels where te_start > '{$date}' or (te_end > '1999-01-01' and te_end < '{$date}') ";
	if (isset($_GET['debug'])) {
		echo '<br><br>tr_pdd_get_test_outdate: <br>';
		echo $sql;
		echo '<br><br>';
	}
	$tests = $wpdb->get_col($sql);
	if (!is_array($tests)) $tests = array();
	if (count($tests) > 0)
		$tests = array_map('strtoupper', $tests);
	return $tests;
}

function tr_pdd_parse_panel_tests_requested($tests, $drug_pairs = null, $exclude_tests = array())
{
	global $wpdb;
	$tests = strtoupper($tests);
	if (strpos($tests, '+') > 0) {
		$tests = explode('+', $tests);
		$tests = end($tests);
	}
	$tests = explode(',', $tests);
	$tests = array_map('trim', $tests);

	if (count($exclude_tests) > 0) {
		foreach ($tests as $i => $t) {
			if (in_array($t, $exclude_tests)) {
				unset($tests[$i]);
			}
		}
	}

	if (isset($drug_pairs['HYDROMORPHONE']) && in_array('MORPHINE', $drug_pairs['HYDROMORPHONE'])) {
		unset($drug_pairs['HYDROMORPHONE'][0]);
	}
	if (count($tests) > 0) {
		$news = array();
		foreach ($tests as $t) {
			$t = strtoupper($t);
			$told = $t;
			$t = trim($t, "\t\n\r\0\x0B\,");
			$t = trim($t);
			if (empty($t)) continue;

			$news[] = $t;


			if (isset($drug_pairs[$t]) && is_array($drug_pairs[$t])) {

				if (in_array('METHAMPHETAMINE', $drug_pairs[$t])) {
					$k = array_search('METHAMPHETAMINE', $drug_pairs[$t]);
					if ($k !== false) {
						unset($drug_pairs[$t][$k]);
					}
				}
				foreach ($drug_pairs[$t] as $dr) {
					if (!empty($dr)) {
						$news[] = $dr;
					}
				}
			}
		}
		$tests = array_unique($news);
	}

	if (count($tests) == 1 && $tests[0] == '') {
		$tests = array();
	}
	if (in_array('THC COOH', $tests)) {
		$tests[] = 'THC-COOH';
	}
	return $tests;
}

function tr_pdd_activity($txt, $error = false, $abort = false)
{
	echo "<li class='" . ($error ? 'error' : '') . "'>" . ((!$error) ? "<span class='green'>&#10004;</span>" : "<span class='red'>&#10008;</span>") . " (" . date("m/d/Y H:i:s") . ") " . $txt . "</li>";
	@ob_flush();
	if ($abort) die();
}

function tr_data_update_doc($doc, $signature = false)
{
	global $wpdb;
	$created = false;

	if (isset($doc['doc_first'])) {
		$doc['doc_first'] = ucfirst(strtolower(trim($doc['doc_first'])));
	}
	if (isset($doc['doc_last'])) {
		$doc['doc_last'] = ucfirst(strtolower(trim($doc['doc_last'])));
	}

	if (empty($doc['doc_first']) && empty($doc['doc_last'])) return $doc;

	if (strlen($doc['doc_npi']) > 6 && empty($doc['doc_ID'])) {
		//search doc by npi
		$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_npi='{$doc['doc_npi']}' limit 1");
		if ($doc_id > 0) {
			$doc['doc_ID'] = $doc_id;
		}
	}

	if ($doc['doc_ID'] > 0) {
		$old_doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc['doc_ID']}");
		if (
			strtolower($doc['doc_first']) != strtolower($old_doc->doc_first)
			&& strtolower($doc['doc_last']) != strtolower($old_doc->doc_last)
		) {
			$doc['doc_ID'] = 0;
		}
	}

	$upload_dir = tr_wp_upload_dir();
	$signature_dir = $upload_dir['basedir'] . "/signatures";


	if ($signature && !empty($signature['name'])) {
		$ext = pathinfo($signature['name'], PATHINFO_EXTENSION);
		$filename = 'doc_' . $doc['doc_first'] . '_' . $doc['doc_last'] . ($doc['doc_ID'] > 0 ? $doc['doc_ID'] : time());
		$filename = trim(sanitize_title($filename)) . '.' . $ext;

		$filepath = $signature_dir . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}

		if (move_uploaded_file($signature['tmp_name'], $filepath)) {
			$doc['doc_signature'] = $filename;
		}
	}

	if (isset($doc['signature'])) {
		if (!empty($doc['signature'])) {
			$doc['signature'] = tr_get_signature($doc['signature']);

			$filename = 'doc_' . $doc['doc_first'] . '_' . $doc['doc_last'] . ($doc['doc_ID'] > 0 ? $doc['doc_ID'] : time());
			$filename = $filename . '.png';
			$filepath = $signature_dir . '/' . $filename;
			$rs = file_put_contents($filepath, $doc['signature']);

			if ($rs) {
				$doc['doc_signature'] = $filename;
			}
		}
		unset($doc['signature']);
	}

	if ($doc['doc_ID'] > 0) {
		$wpdb->update('ci_doc', $doc, array('doc_ID' => $doc['doc_ID']));

		if ($doc['cli_ID'] > 0) {
			$cli_doc_exists = $wpdb->get_var("select doc_ID from ci_clinic_doc where doc_ID = {$doc['doc_ID']} and cli_ID = {$doc['cli_ID']}");
			if (!$cli_doc_exists) {
				$wpdb->insert('ci_clinic_doc', array('cli_ID' => $doc['cli_ID'], 'doc_ID' => $doc['doc_ID']));
			}
			//update batch
			$doc_name = $doc['doc_last'] . ', ' . $doc['doc_first'];
			$wpdb->update('ci_batches', array('requesting_physician' => $doc_name), array('doc_ID' => $doc['doc_ID']));
		}
	} else {
		$wpdb->insert('ci_doc', $doc);
		$doc['doc_ID'] = $wpdb->insert_id;
		if ($doc['doc_ID'] == 0) {
			Tr_Session_Class::add_error('Error add doctor db: ' . $doc['doc_first'] . ' ' . $doc['doc_last'] . '. ' . $wpdb->last_error);
		} else {
			$created = true;
			if ($doc['cli_ID'] > 0) {
				$wpdb->insert('ci_clinic_doc', array('cli_ID' => $doc['cli_ID'], 'doc_ID' => $doc['doc_ID']));
			}
		}
	}

	tr_check_and_create_doc_user($doc['doc_ID'], $doc['cli_ID'], $created);


	return $doc;
}


function tr_get_states($state = '')
{
	$states = array(
		''  => 'Select State',
		"AL" => "Alabama",
		"AK" => "Alaska",
		"AS" => "American Samoa",
		"AZ" => "Arizona",
		"AR" => "Arkansas",
		"CA" => "California",
		"CO" => "Colorado",
		"CT" => "Connecticut",
		"DE" => "Delaware",
		"DC" => "District Of Columbia",
		"FM" => "Federated States Of Micronesia",
		"FL" => "Florida",
		"GA" => "Georgia",
		"GU" => "Guam",
		"HI" => "Hawaii",
		"ID" => "Idaho",
		"IL" => "Illinois",
		"IN" => "Indiana",
		"IA" => "Iowa",
		"KS" => "Kansas",
		"KY" => "Kentucky",
		"LA" => "Louisiana",
		"ME" => "Maine",
		"MH" => "Marshall Islands",
		"MD" => "Maryland",
		"MA" => "Massachusetts",
		"MI" => "Michigan",
		"MN" => "Minnesota",
		"MS" => "Mississippi",
		"MO" => "Missouri",
		"MT" => "Montana",
		"NE" => "Nebraska",
		"NV" => "Nevada",
		"NH" => "New Hampshire",
		"NJ" => "New Jersey",
		"NM" => "New Mexico",
		"NY" => "New York",
		"NC" => "North Carolina",
		"ND" => "North Dakota",
		"MP" => "Northern Mariana Islands",
		"OH" => "Ohio",
		"OK" => "Oklahoma",
		"OR" => "Oregon",
		"PW" => "Palau",
		"PA" => "Pennsylvania",
		"PR" => "Puerto Rico",
		"RI" => "Rhode Island",
		"SC" => "South Carolina",
		"SD" => "South Dakota",
		"TN" => "Tennessee",
		"TX" => "Texas",
		"UT" => "Utah",
		"VT" => "Vermont",
		"VI" => "Virgin Islands",
		"VA" => "Virginia",
		"WA" => "Washington",
		"WV" => "West Virginia",
		"WI" => "Wisconsin",
		"WY" => "Wyoming",
		'00' => 'N/A'
	);
	$canada = array(
		'AB' => 'Canada - Alberta',
		'BC' => 'Canada - British Columbia',
		'MB' => 'Canada - Manitoba',
		'NB' => 'Canada - New Brunswick',
		'NL' => 'Canada - Newfoundland and Labrador',
		'NT' => 'Canada - Northwest Territories',
		'NS' => 'Canada - Nova Scotia',
		'NU' => 'Canada - Nunavut',
		'ON' => 'Canada - Ontario',
		'PE' => 'Canada - Prince Edward Island',
		'QC' => 'Canada - Quebec',
		'SK' => 'Canada - Saskatchewan',
		'YT' => 'Canada - Yukon Territory'
	);


	if (function_exists('get_blog_option')) {
		$settings = get_blog_option(defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1, 'ci_pdf_data');
	} else {
		$settings = get_option('ci_pdf_data');
	}

	if ($settings['req_canadian_provices']) {
		$states = array_merge($states, $canada);
	}
	if (!empty($state)) {
		return $states[$state];
	}

	return $states;
}

function trpdd_log_form($form, $data = null, $log_id = 0, $target_id = 0)
{

	global $wpdb, $blog_id, $form_user_log_id;

	if ($data == null || is_string($data)) {
		$data = is_array($_POST) ? $_POST : array();
	}

	$data['current_lab_id'] = CI_Base_Lab::get_current_lab_id();
	$data['ref'] = $_SERVER['REQUEST_URI'];

	if (stripos($data['ref'], 'doing_wp_cron') !== false) {
		return true;
	}

	$user_log_id = 0;

	if (in_array($form, ['savers_tox', 'savers_pcr', 'savers_blood', 'savers_allergy', 'savers_ngs', 'savers_dna', 'savers_study']) && !empty($form_user_log_id)) {
		$user_log_id = $form_user_log_id;
	}


	//end log user



	if (is_array($data) || is_object($data)) {
		unset($data['patient_signature']);
		unset($data['provider_signature']);
		$data = json_encode($data);
	}

	$formdata = array(
		'form'  => $form,
		'author_id' => get_current_user_id(),
		'created_date' => date_i18n('Y-m-d H:i:s'),
		'target_id' => $target_id,
		'blog_id' => $blog_id,
		'data' => $data,
		'user_log_id' => $user_log_id
	);
	if ($log_id > 0) {
		$rs = $wpdb->update('ci_log_forms', $formdata, array('id' => $log_id));
	} else {
		$rs = $wpdb->insert('ci_log_forms', $formdata);
	}

	return $rs;
}

function trpdd_get_lab_url($lab_site)
{
	global $wpdb;
	$lab_url = '';
	if (function_exists('get_sites') && $lab_site > 0) {
		$sites = get_sites();
		foreach ($sites as $site) {
			if ($site->blog_id == $lab_site) {
				$lab_url = $site->domain;
			}
		}
	}
	return $lab_url;
}

function trdebug_wp_mail_failed($debug)
{
	//var_dump($debug);
}

function tr_format_date_default($date, $format = 'Y-m-d')
{
	$date = @str_replace('/', '-', $date);
	if (@preg_match("/([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})/", $date, $matches) > 0) {
		$date = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
	}
	return $date;
}

function tr_parse_fl_name($name)
{
	$has_commo = false;
	if (strpos($name, ',') > 0) {
		list($last_name, $first_name) = explode(',', $name, 2);

		//check specialty
		$first_name = trim($first_name);
		$last_name = trim($last_name);
		$has_commo = true;
	} else {
		list($last_name, $first_name) = explode(' ', $name, 2);
		$first_name = trim($first_name);
		$last_name = trim($last_name);
	}
	$first_name = ucfirst($first_name);
	$last_name = ucfirst($last_name);

	return array($first_name, $last_name, $has_commo);
}

function tr_trim_name($name)
{
	$name = trim($name);
	$name = trim($name, "\t\n\r\0\x0B,");
	$name = trim($name);
	return $name;
}

function tr_tbl_sort($by, $title, $class = '', $style = '')
{
	$order = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
	$link = Tr_Base_Class_V6::link(array('orderby' => $by, 'order' => ($order == 'desc' ? 'asc' : 'desc')), false);
	if (isset($_REQUEST['orderby']) && $by == $_REQUEST['orderby']) {
		$class .= 'sorted';
	} else {
		$class .= 'nosort';
	}
	echo '<th class="manage-column sortable ' . $class . ' ' . ($order == 'desc' ? 'desc' : 'asc') . '" style="' . $style . '"><a href="' . $link . '"><span>' . $title . '</span><span class="sorting-indicator"></span></a></th>';
}

function trpdd_html_to_pdf($filepath, $savepath = '', $outfilename = '', $header = '', $footer = '')
{
	$out = false;
	$options = '';
	if (empty($savepath)) {
		$savepath = tempnam("/tmp", "export" . time()) . '.pdf';
		$out = true;
	}
	if (!empty($header)) {
		$options .= " --header-html {$header} ";
	}
	if (!empty($footer)) {
		$options .= " --footer-html {$footer} ";
	}
	try {
		$query = "wkhtmltopdf {$options} {$filepath} {$savepath}";
		$result = shell_exec($query);
		if ($out) {
			header('Content-Type: application/pdf');
			header('Content-Disposition: attachment; filename="' . $outfilename . '"');
			readfile($savepath);
		}
		return true;
	} catch (Exception $e) {
		var_dump($e);
	}
	return false;
}

function trpdd_get_path_result_pdf($report, $btype = 'tox', $type = '')
{
	$type = (isset($_REQUEST['sum'])) ? 'sum' : $type;

	if (empty($report['batch_id'])) {
		$report['batch_id'] = 'other';
	}

	$patient_name = str_replace(" ", "-", $report['patient_name']);


	$dir = $report['batch_id'];

	$full_path = WP_CONTENT_DIR . '/results/' . $dir;
	if (!file_exists($full_path)) {
		wp_mkdir_p($full_path);
	}

	if (tr_get_option('ci_pdf_data', 'pdf_filename', '') == 'mrn-date-name-panel') {
		$panelname = isset($report['panels_name']) ? $report['panels_name'] : '';
		$outfile = $report['pa_mrn'] . '_' . date('ymd', strtotime($report['approve_date'])) . '_' . sanitize_title($patient_name) . '-' . $panelname  . '.pdf';
	} else if (tr_get_option('ci_pdf_data', 'pdf_filename', '') == 'mrn-date-name-panelcode') {
		$panel_code = isset($report['panels_code']) ? $report['panels_code'] : '';
		$outfile = $report['pa_mrn'] . '_' . date('ymd', strtotime($report['approve_date'])) . '_' . sanitize_title($patient_name) . '_' . $panel_code  . '.pdf';
	} else {
		$addingname = '';
		if (tr_get_option('ci_pdf_data', 'pdf_with_refnumber') && !empty($report['oldreq'])) {
			$addingname .= '-' . $report['oldreq'];
		}
		if ($btype == 'rpp_gi') {
			$outfile = sanitize_title($patient_name . '_' . $report['assen']) . $addingname . '.pdf';
		} else if ($btype == 'blood') {
			$outfile = sanitize_title($patient_name . '_' . $report['assen']) . '' . $addingname . '-blood.pdf';
		} else if ($btype == 'tox') {
			if (empty($report['type_of_specimen'])) {
				$report['type_of_specimen']  = $report['sample_type'];
			}
			$matrix = (stripos($report['type_of_specimen'], "oral") !== false) ? "oral" : strtolower($report['type_of_specimen']);
			$oral = ($matrix == 'oral') ? 'ODT' : 'UDT';
			$outfile = $report['assen'] . "_" . sanitize_title($oral . "_" . $patient_name) . $addingname . '-' . $type . $btype . '.pdf';
		} else {
			$outfile = $report['assen'] . "_" . sanitize_title($patient_name) . $addingname . '-' . $type . $btype  . '.pdf';
		}
	}

	return array($dir, $outfile);
}

function trpdd_export_results_pdf($id, $type = '', $print = false, $btype = 'tox')
{
	global $wpdb, $report;
	global $pdf_file_is_exist;
	global $pdf_has_report, $ci_time_query_pdf;
	global $ci_time_build_pdf;
	global $ci_pdf_filename;
	global $db_time_log;

	$pdf_file_is_exist  = false;
	$pdf_has_report     = true;
	$report             = null;
	$mid 				= 0;

	if (empty($btype)) {
		$btype = 'tox';
	}


	if ($type == 'mid') {
		$type = '';
		$mid = $_REQUEST['mid'] = $id;
		$id = $_REQUEST['id'] = 0;
	} else if ($type != 'html') {
		$_REQUEST['id'] = $id;
		if (!empty($type)) {
			$type = '_' . $type;
		}
	}

	$report     = tr_pdd_get_report_by_id($id, $btype, $mid);
	//check history report
	$cache_html = '';

	if (
		$report['estatus'] == 'C'
		&& $report['final_results'] > 0
		&& $type != 'html'
		&& !isset($_GET['nocache'])
		&& !isset($_GET['debugabx'])
		&& !isset($_GET['shownewabx'])
		&& !isset($_GET['nocacheabx'])
		&& $report['batch_status'] != 'rejected'
		&& stripos($report['batch_status'], 're_') === false
	) {
		$cache_html = trpdd_get_last_result_history_html($report);
	}

	$start_time = microtime(true);
	if (empty($cache_html)) {
		if ($btype == 'tox') {
			$html_file = TRCPDD_PATH . 'reports/results_tox.php';
		} else {
			$html_file = TRCPDD_PATH . 'reports/results_' . $btype . '.php';
		}

		$has_report = true;

		ob_start();
		if (file_exists($html_file)) {
			require($html_file);
		} else {
			$has_report = false;
			if (isset($_GET['debug'])) {
				echo 'No file : ' . $html_file . '<br>';
			}
			if (isset($_GET['dbtime'])) {
				$db_time_log .= 'No file : ' . $html_file . '<br>';
			}
		}
		$content = ob_get_clean();

		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'build html from template: ' . $html_file . '<br>';
			$db_time_log .= 'content size: ' . strlen($content) . '<br>';
			$db_time_log .= 'include style: ' . $report['add_style'] . '<br>';
		}
	} else {
		$arr1 = explode('<!--split_page-->', $cache_html);
		$content = $arr1[0];
		$report['content2'] = isset($arr1[1]) ? $arr1[1] : '';
		if (!empty($report['content2'])) {
			$report['add_style'] = 3;
		}
		if (isset($_GET['dbtime'])) {
			global $ci_history_file_cache_path;
			echo '<br>Load cache history ' . $ci_history_file_cache_path . '<br>';
			echo '<br> history size: ' . strlen($content) . '<br>';
			echo 'content 2: ' . (!empty($report['content2']) ? 'yes' : 'no') . '<br>';
		}
		$has_report = true;
	}


	if (!$has_report && !isset($_GET['debugq']) && !isset($_GET['tr_action'])) {
		$content = '';
		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'no report found<br>';
		}
	}

	if ($type == 'html') {
		if (isset($report['add_style']) && $report['add_style'] > 0) {
			$file_html = 'results_' . $btype . '_' . $report['add_style'] . '.php';
			if (file_exists(TRCPDD_PATH . 'reports/' . $file_html)) {
				ob_start();
				require(TRCPDD_PATH . 'reports/' . $file_html);
				$contentabx = ob_get_clean();
				if ($has_style && strlen($contentabx) > 300) {
					$content .= '<!--split_page-->' . $contentabx;
				}
			}
		}
		return $content;
	}


	//$content = str_replace('https://','http://',$content);
	if (isset($_GET['html'])) {
		if (isset($_GET['debug'])) {
			echo microtime(true) - $start_time;
			echo '<br>';
			if (!$has_report) {
				echo 'file : ' . $html_file . '<br>';
			}
		}
		echo $content;
		if (isset($report['content2'])) {
			echo $report['content2'];
		}
		exit;
	}
	$ci_time_query_pdf = microtime(true) - $start_time;
	$only_pdf = false;

	$start_time = microtime(true);
	if (!$report) {
		echo 'no report';
		return false;
	}

	list($path, $outfile) = trpdd_get_path_result_pdf($report, $btype, $type);
	$ci_pdf_filename = $outfile;
	if (isset($_REQUEST['pos_results'])) {
		$outfile = 'pos_' . $outfile;
	} else if (isset($_REQUEST['new'])) {
		$outfile = 'new_' . $outfile;
	}
	if (isset($_GET['style'])) {
		$outfile = $_GET['style'] . '_' . $outfile;
	}

	//add patient guide for pcr abx
	if (isset($_GET['pst']) && $_GET['pst'] == '1' && $btype == 'pcr' && !empty($report['add_style'])) {
		$outfile = 'pst_' . $outfile;
		$report['patient_guide'] =  tr_get_option('ci_result_settings_pcr', 'abx_patient_guide_pdf', '');
	}
	if (isset($_GET['abx1']) || isset($_GET['abx2']) || isset($_GET['debug']) || isset($_GET['shownewabx']) || isset($_GET['nocacheabx'])) {
		$outfile = 'test_' . $outfile;
	}
	$outfile = WP_CONTENT_DIR . '/results/' . $path . '/' . $outfile;

	$tmp_time = @filectime($html_file);
	$filetime = @filectime($outfile);
	$approve_time = strtotime($report['approve_date']);

	if (file_exists($outfile) && $filetime > $tmp_time && $filetime > $approve_time && !isset($_REQUEST['pos_results'])) {
		if ($report['report_ref'] > 0 || $print || isset($_REQUEST['use_cache'])) {
			$pdf_file_is_exist = true;
			$ci_time_build_pdf = microtime(true) - $start_time;
			//return $outfile;
		}
	}
	if (isset($_GET['dbtime'])) {
		$db_time_log .= 'include style #2: ' . $report['add_style'] . '<br>';
	}

	if (isset($_GET['dbtime']) && isset($_GET['rmpdf'])) {
		$report['pdf2file'] = '';
	}
	if (isset($report['pdf2file']) && !empty($report['pdf2file'])) {
		$report['result_pdf'] = !empty($report['result_pdf']) ? $report['result_pdf'] . ',' . $report['pdf2file'] : $report['pdf2file'];
	}


	if (!empty($report['result_pdf'])) {
		$result_dir = tr_get_result_upload_dir();

		if ($report['id'] > 0 && !empty($report['instrument'])) {
			$report['content']  = $content;
		} else if ($report['id'] > 0 && stripos($content, 'DRUG SCREENING') > 0) {
			$report['content']  = $content;
		}

		if (strpos($content, 'No Custom Profile/No Tests Requested Otherwise') !== false) {
			$only_pdf = true;
		}


		$rs_pdfs = explode(',', $report['result_pdf']);


		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'uploaded result pdf: ' . $report['result_pdf'] . '<br>';
		}

		if (count($rs_pdfs) > 1) {
			$pdf_files = array();
			foreach ($rs_pdfs as $fpdf) {
				if (stripos($fpdf, '_mako.') > 0) {
					$only_pdf = true;
				}
				$pdf_files[] =  $result_dir['path'] . '/' . $fpdf;
			}
			if (isset($_GET['dbtime'])) {
				echo '<br>Merge PDF files:<br>';
				var_dump($pdf_files);
				echo '<br>';
				exit;
			}
			$pdf_file = trpdd_merge_pdf($pdf_files);

			if (isset($_GET['dbtime'])) {
				echo '<br>Merged to:<br>';
				var_dump($pdf_file);
				echo '<br>';
				exit;
			}

			if (isset($_GET['debugpdf2'])) {
				return $pdf_file;
			}
		} else {
			if (stripos($report['result_pdf'], '_mako.') > 0) {
				$only_pdf = true;
			}
			$pdf_file = $result_dir['path'] . '/' . $report['result_pdf'];

			if (isset($_GET['dbtime'])) {
				echo '<br>Merge uploaded pdf: ' . $report['result_pdf'] . '<br>';
			}
		}

		if (empty($content) || !$has_report) {
			$ci_time_build_pdf = microtime(true) - $start_time;
			if (isset($_GET['dbtime'])) {
				echo '<br>Only pdf file by no content<br>';
			}
			return $pdf_file;
		} else if ($only_pdf) {
			if (isset($_GET['dbtime'])) {
				echo '<br>Only pdf file<br>';
			}
			return $pdf_file;
		}


		$tmp_name = trpdd_add_result_pdf($report, $pdf_file, $btype, $outfile);

		if ($tmp_name != $outfile && (stripos($tmp_name, 'tmp_') === 0 || stripos($tmp_name, '/uploads/') === false)) {
			unlink($outfile);
			rename($tmp_name, $outfile);
		} else if (stripos($tmp_name, '/uploads/') !== false) {
			if (!file_exists($tmp_name) && file_exists($outfile)) {
				rename($outfile, $tmp_name);
			}
			$ci_time_build_pdf = microtime(true) - $start_time;
			return $tmp_name;
		}
		$ci_time_build_pdf = microtime(true) - $start_time;
		return $outfile;
	} else if (!empty($report['add_style'])) {

		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'trpdd_add_result_pdf <br>';
		}

		$report['content']  = $content;
		$tmp_name = trpdd_add_result_pdf($report, '', $btype, $outfile);

		if ($tmp_name != $outfile) {
			unlink($outfile);
			rename($tmp_name, $outfile);
		}
		$ci_time_build_pdf = microtime(true) - $start_time;
		return $outfile;
	}

	if (!$has_report && !isset($_GET['viewpdf'])) {
		$ci_time_build_pdf = microtime(true) - $start_time;
		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'no report found<br>';
		}
		return false;
	}

	$pdf_has_report = $has_report;


	if (isset($_GET['debugf'])) {
		var_dump($outfile);
		exit;
	}
	if (isset($_GET['html'])) {
		echo $content;
		exit;
	}

	try {
		$report['orientation'] = isset($report['orientation']) ? $report['orientation'] : '';
		trpdd_build_pdf($content, array('size' => 'letter', 'orientation' => $report['orientation'], 'filepath' => $outfile));
	} catch (Exception $e) {
		$ci_time_build_pdf = microtime(true) - $start_time;
		Tr_Session_Class::add_error("Error while export PDF file. " . $e->getMessage());
		if (isset($_GET['dbtime'])) {
			$db_time_log .= 'Error while export PDF file. ' . $e->getMessage() . '<br>';
		}
		return false;
	}
	$ci_time_build_pdf = microtime(true) - $start_time;
	return $outfile;
}

function trpdd_panel_tests_requested($batch, $doc_id, $cli_id)
{
	global $wpdb;
	$batch = (array) $batch;

	if (!is_array($batch['medication'])) {
		$batch['medication'] = @maybe_unserialize($batch['medication']);
	}

	//check last patient empty
	if ($batch['custom_profile'] == 'Previous Testing' && strlen($batch['panel_tests_requested']) < 20) {
		$batch['custom_profile'] = 'Custom High Risk';
	}

	//check POC
	if (stripos($batch['custom_profile'], 'custom') === 0) {
		$sql = "select * from `ci_custom_providers` where doc_ID = '{$doc_id}' and cli_ID= '{$cli_id}' order by cus_ID desc limit 1";
		$profile = $wpdb->get_row($sql);
		if (!$profile) {
			$sql = "select * from `ci_custom_providers` where doc_ID = '{$doc_id}' order by cus_ID desc limit 1";
			$profile = $wpdb->get_row($sql);
		}



		$poc = '';
		$poc_new = array();
		$test_new = array();
		if ($profile->cus_confirm_positive == 1 || $batch['poc_perform'] == 1) {
			//$poc .= '[+]POC confirm +,';
			if (is_array($batch['medication'])) {
				foreach ($batch['medication'] as $k => $vl) {
					if ($vl == '1') {
						$k = tr_pdd_get_poc_drugs($k);
						//$poc_new = array_merge($poc_new, explode(',', $k));
					}
				}
			}
		}


		if ($profile) {
			if ($batch['custom_profile'] == 'Custom High Risk') {
				$cus_test = $profile->cus_high_risk;
			} else if ($batch['custom_profile'] == 'Custom Mid Risk') {
				$cus_test = $profile->cus_med_risk;
			} else {
				$cus_test = $profile->cus_individuals; //Custom Low Risk
			}

			if (!empty($cus_test)) {
				$test_new = array_merge($test_new, explode(',', $cus_test));
			}
		}


		if (!empty($batch['panel_tests_requested'])) {
			$test_new = array_merge($test_new, explode(',', $batch['panel_tests_requested']));
		}

		//	$test_new = array_merge($test_new, $poc_new);
		$test_new = array_unique($test_new);
		$test_new = implode(',', $test_new);
		$batch['panel_tests_requested'] = $poc . $test_new;
	} else {

		if ($batch['poc_perform'] == 1 && is_array($batch['medication'])) {

			$poc_new = array();
			foreach ($batch['medication'] as $k => $vl) {
				if ($vl == '1') {
					$k = tr_pdd_get_poc_drugs($k);
					//$poc_new = array_merge($poc_new, explode(',', $k));
				}
			}
			//var_dump($poc_new);exit;
			//$test_new = array_merge($poc_new, explode(',', $batch['panel_tests_requested']));
			//$test_new = array_unique($test_new);
			//$batch['panel_tests_requested'] = '[+]POC confirm' . ' +,' . implode(',', $test_new);
		}
	}
	return trim($batch['panel_tests_requested'], '+,');
}



function tr_pdd_get_current_batch_id($today = '')
{
	$with_num = tr_get_option('ci_pdf_data', 'multi_batch_id');
	$last_batch_id = date_i18n("Ymd");
	if (!empty($today)) {
		$last_batch_id = date_i18n("Ymd", strtotime($today));
	}
	if ($with_num) {
		$arr = get_option('tr_batch_num_st', array());
		$num = $arr['num'] > 1 ? $arr['num'] : 1;
		if (!empty($arr['bid']) && $arr['bid'] != $last_batch_id) {
			$num = 1;
		}
		$arr['num'] = $num;
		$arr['bid'] = $last_batch_id;
		update_option('tr_batch_num_st', $arr);
		$last_batch_id .= '-' . $num;
	}
	return $last_batch_id;
}


function tr_pdd_standard_deviation($aValues, $bSample = false)
{
	$fMean = array_sum($aValues) / count($aValues);
	$fVariance = 0.0;
	foreach ($aValues as $i) {
		$fVariance += pow($i - $fMean, 2);
	}
	$fVariance /= ($bSample ? count($aValues) - 1 : count($aValues));
	return (float) sqrt($fVariance);
}

function tr_pdd_next_result($id, $batch_id, $lab_id = 0, $btype = 'tox')
{
	global $wpdb, $current_role;
	$user_id = get_current_user_id();

	$table = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);

	$joins = " JOIN {$table} ba ON ba.assen = re.patient_id
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id ";

	$orderby = "order by ba.position asc";
	if ($btype == 'tox') {
		$joins .= " LEFT JOIN {$result_tbl} cre ON cre.report_id = re.id 
                        and cre.drug  = 'Methamphetamine' and cre.`pos-neg`= 'POS'
                 ";
		$orderby = "order by cre.id desc, ba.position asc";
	}


	$where = " (re.estatus!='C' or re.estatus is null) ";

	if ($btype == 'study') {
		$where .= " and ba.project_ID='{$batch_id}' ";
	} else {
		$where .= " and ba.batch_id='{$batch_id}' ";
	}
	if ($lab_id > 0) {
		$where .= " and ba.lab_id ='{$lab_id}' ";
	}

	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and re.lab_id in ( {$lab_str} ) ";
	}

	if ($user_id > 0 && !tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'scientist', 'labmanager', 'lab_tech', 'lab'))) {
		$where .= " and re.author_id = {$user_id} ";
	}

	$sql = "select re.id from {$report_tbl} re
                        {$joins}
                        where {$where}
                        {$orderby}";

	$time_start  = microtime(true);
	$ids = $wpdb->get_col($sql);
	$has_id = false;
	$next = 0;
	foreach ($ids as $idn) {
		if ($idn == $id) {
			$has_id = true;
		} else if ($has_id) {
			$next = $idn;
			break;
		}
	}
	if (isset($_REQUEST['debug'])) {
		echo $sql;
		echo '<br>';
		echo microtime(true) - $time_start, '<br>';
	}

	return $next;
}

function tr_pdd_get_cutoffs($type = 'tox', $datereport = null)
{
	global $wpdb;
	global $ci_cache_get_cutoffs;
	$cache_key = $type . '_' . $datereport;
	if (isset($ci_cache_get_cutoffs[$cache_key])) {
		return $ci_cache_get_cutoffs[$cache_key];
	}
	$sql = "select te.*,cu.* 
			from ci_test_panels te 
			JOIN ci_test_panels_cutoffs cu ON cu.cu_te_ID = te.te_ID
            where cu.cu_cutoff!='' and te.te_in_req = 1
            ";

	if (!empty($datereport)) {
		$sql .= " 
            and (cu.cu_start is null or cu.cu_start ='' or cu.cu_start <= '{$datereport}' ) 
            and (cu.cu_end is null or cu.cu_end = '' or cu.cu_end > '{$datereport}') 
            ";
	}

	if (!empty($type)) {
		$sql .= " and te_type = '{$type}' ";
	}
	$cutoffs = array();
	$cutoffs_q = $wpdb->get_results($sql, ARRAY_A);
	foreach ($cutoffs_q as $c) {
		$k = strtoupper($c['te_name']);
		$c['cu_ms'] = trim($c['cu_ms']);
		$ms = array();
		if (!empty($c['cu_ms'])) {
			$ms = explode(',', $c['cu_ms']);
			$ms = array_map('trim', $ms);
		}

		if (count($ms) > 0) {
			foreach ($ms as $m) {
				if (empty($m)) continue;
				$m = strtoupper($m);
				$m = $k . $m;
				$cutoffs[$m] = array(
					'cutoff' => $c['cu_cutoff'],
					'limit' => $c['cu_upper_limit'],
					'lqc' => $c['cu_lqc'],
					'mqc' => $c['cu_mqc'] ?? '',
					'hqc' => $c['cu_hqc']
				);
			}
		}
		if (!isset($cutoffs[$k])) {
			$cutoffs[$k] = array(
				'cutoff' => $c['cu_cutoff'],
				'limit' => $c['cu_upper_limit'],
				'lqc' => $c['cu_lqc'],
				'mqc' => $c['cu_mqc'] ?? '',
				'hqc' => $c['cu_hqc']
			);
		}
	}
	$ci_cache_get_cutoffs[$cache_key] = $cutoffs;
	return $cutoffs;
}

function tr_pdd_get_drug_pairs()
{
	global $wpdb;

	$drug_pairs = array();
	$get_pairs_q = $wpdb->get_results("SELECT * FROM ci_drug_analyte_pairs", ARRAY_A);
	foreach ($get_pairs_q as $g) {
		$drug_pairs[$g['drug_1']] = array($g['drug_2'], $g['drug_3'], $g['drug_4']);
		//$drug_pairs[$g['drug_2']] = array($g['drug_1'],$g['drug_3'],$g['drug_4']);
		if (!empty($g['drug_3'])) {
			//  $drug_pairs[$g['drug_3']] = array($g['drug_1'],$g['drug_2'],$g['drug_4']);
		}
		if (!empty($g['drug_4'])) {
			//   $drug_pairs[$g['drug_4']] = array($g['drug_1'],$g['drug_2'],$g['drug_3']);
		}
	}
	return $drug_pairs;
}

function tr_pdd_get_drug_groups($btype = 'tox', $group_ids = array(), $get_sam_type = false)
{
	global $wpdb;
	global $ci_cache_get_drug_groups;
	if (empty($btype)) $btype = 'tox';
	$gkey = $btype . implode('_', $group_ids) . ($get_sam_type ? 1 : 0);
	if (isset($ci_cache_get_drug_groups[$gkey])) {
		return $ci_cache_get_drug_groups[$gkey];
	}

	$sql = "select 
            g.*,
            t.te_ID,
            t.te_name,
            t.te_abrev
        from ci_test_groups g
        left join ci_test_panels t ON t.te_group_id = g.tg_ID
    where g.tg_type = '{$btype}' ";



	if (count($group_ids) > 0) {
		$group_ids_str = implode(',', $group_ids);

		$sql .= " and (
				g.tg_ID in ({$group_ids_str}) 
				or g.tg_parent in ({$group_ids_str}) 
				or g.tg_ID in (select tg_parent from ci_test_groups where tg_ID in ({$group_ids_str}) ) 
				
				)";
	}
	$sql .= " order by tg_position asc, t.te_hide_pdf asc, t.te_position asc ";
	if (isset($_GET['debug1'])) {
		echo '<br><br>';
		var_dump($sql);
		echo '<br><br>';
	}
	$drugs = $wpdb->get_results($sql);
	$groups = array();
	foreach ($drugs as $d) {
		if (!isset($groups[$d->tg_ID])) {
			$types = array();
			if ($get_sam_type) {
				$rs = $wpdb->get_col("select ts_name from ci_test_specimens where ts_group_id={$d->tg_ID} and ts_type='{$btype}'");
				foreach ($rs as $r) {
					$types[strtolower($r)] = $r;
				}
			}
			$reflexids = !empty($d->tg_reflex_group_ID) ? explode(',', $d->tg_reflex_group_ID) : array();

			$groups[$d->tg_ID] = array(
				'id' 		=> $d->tg_ID,
				'position' 	=> $d->tg_position,
				'name' 		=> $d->tg_name,
				'short_name' => $d->tg_short_name,
				'code' 		=> $d->tg_cpt_code,
				'comments' 	=> $d->tg_comments,
				'is_covid' 	=> $d->tg_is_covid == 1,
				'parent' 	=> $d->tg_parent,
				'antibiotic' => $d->tg_antibiotic,
				'pdf_style' => $d->tg_blood_style,
				'drugs' 	=> array(),
				'te_IDs' 	=> array(),
				'types' 	=> $types,
				'show' 		=> $d->tg_show_req,
				'logo' 		=> $d->tg_logo,
				'manual_antibiotic' => $d->tg_manual_antibiotic,
				'manual_edit' => $d->tg_manual_edit,
				'pdf_title' => $d->tg_pdf_title,
				'hide_ct' 	=> $d->tg_hide_pdf_ct,
				'show_controls_pe' => $d->tg_show_controls_pe,
				'remove_controls' => $d->tg_remove_controls,
				'pos_2_top' => $d->tg_pos_2_top,
				'hide_phone_address' => $d->tg_hide_phone_address,
				'hide_units' => $d->tg_hide_pdf_units,
				'comment_next_row' => $d->tg_comment_next_row,
				'only_group' => $d->tg_only_group,
				'notestcheckbox' => $d->tg_notestcheckbox,
				'reflex_ID' => $reflexids,
				'other_options' => !empty($d->tg_other_options) ? json_decode($d->tg_other_options, true) : array(),
			);
		}

		if (!empty($d->te_name)) {
			$d->te_name = strtoupper($d->te_name);
			$d->te_abrev = strtoupper($d->te_abrev);

			$groups[$d->tg_ID]['drugs'][] = $d->te_name;
			$groups[$d->tg_ID]['te_IDs'][] = $d->te_ID;
			$groups[$d->tg_ID]['nametests'][$d->te_ID] = array(
				'te_name' => $d->te_name,
				'te_abrev' => $d->te_abrev,
			);
		}
	}
	$ci_cache_get_drug_groups[$gkey] = $groups;
	return $groups;
}


function tr_pdd_get_chart_data($results, $test, $instrument, $type, &$batches, &$chart_data)
{
	global $wpdb;
	$startvl = $wpdb->get_row("select * from ci_results_start where rs_test='{$test}' and rs_instrument='{$instrument}'", ARRAY_A);
	if (!$startvl) {
		$startvl = array('rs_test' => $test, 'rs_instrument' => $instrument);
		$wpdb->insert('ci_results_start', $startvl);
		$startvl['rs_ID'] = $wpdb->insert_id;
	}

	$start_vals = array(
		'lot' => $startvl['rs_lot'],
		'expiry' => $startvl['rs_expiration_date']
	);
	$start_chart = array();
	foreach (array('low', 'high', 'mid') as $k) {
		$vl = $startvl['rs_' . $k];
		$start_vals['tests'][0][$k] = array('start_' . $k . '_' . $startvl['rs_ID'], $vl);

		$date = 'Start';
		if ($vl) {
			$start_chart[$k] = array($date, $vl);
		}
	}


	$batches = array(
		'Start' => $start_vals,
	);
	foreach ($results as $i => $row) {

		$row->sublab = preg_replace('/\s.*/', '', $row->sublab);

		$k = (stripos($row->patient_name, 'low') !== false || stripos($row->patient_name, 'lqc') !== false) ? 'low' : 'high';
		$k = (stripos($row->patient_name, 'mid') !== false || stripos($row->patient_name, 'mqc') !== false) ? 'mid' : $k;


		//$date = date('Y-m-d', $row->created + (get_option('gmt_offset') * HOUR_IN_SECONDS));
		$date = $row->collection_date;
		$expiry = $row->expiration_date;

		if (empty($row->conc)) $row->conc = 0;

		if (!is_array($batches[$row->batch_id])) {
			$batches[$row->batch_id] = array(
				'highcount' => 0,
				'lowcount' => 0,
				'midcount' => 0
			);
		}
		$lots = explode(' ', $row->patient_name);
		$lot = end($lots);
		$batches[$row->batch_id]['date'] = $date;
		$batches[$row->batch_id]['expiry'] = $expiry;
		if (!empty($lot)) {
			$batches[$row->batch_id]['lot'] = $lot;
		}




		if ($type == 1) {

			$batches[$row->batch_id]['labs'][$row->sublab][$k] = array($row->rs_id, $row->conc);
			$date = strtotime($date);
			$labs[$row->sublab][$k][$date] = $row->conc;
		} else {
			$count = $batches[$row->batch_id][$k . 'count']++;
			$batches[$row->batch_id]['tests'][$count][$k] = array($row->rs_id, $row->conc);

			//$batches[$row->batch_id][$k] = array($row->rs_id,$row->conc);
			$date = strtotime($date);
			$labs['Independent Laboratory Services'][$count][$k][$date] = $row->conc;
		}
	}

	$chart_data = array(
		'width' => '860',
		'height' => '500'
	);
	$chart_types = array(
		'low' => 'Low QC',
		'mid' => 'Mid QC',
		'high' => 'HIgh QC'
	);


	foreach ($labs as $lab => $rows) {
		foreach ($chart_types as $k => $txt) {
			$vls = array();
			$other = array();
			foreach ($rows as $ii => $row) {
				if (!isset($row[$k])) continue;
				ksort($row[$k]);
				if (isset($start_chart[$k])) {
					$vls[$start_chart[$k][0]] = $start_chart[$k][1];
					if ($ii > 0) {

						$other[$ii - 1][$start_chart[$k][0]] = $start_chart[$k][1];
					}
				}

				foreach ($row[$k] as $created => $vl) {

					if ($created == 'Start') {
						$date = $created;
					} else {
						$date = date('Y-m-d', $created);
					}
					if ($ii == 0) {
						$vls[$date] = $vl;
					} else {
						$other[$ii - 1][$date] = $vl;
					}
				}
			}


			if (count($vls) > 0) {
				$key = sanitize_title($k . $lab . $test);
				$chart_data['charts'][$key] = array(
					'name' => $lab . ' - ' . $txt . ' - ' . $test,
					'label' => $txt,
					'color' => 'blue',
					'color2' => 'yellowgreen',
					'value' => $vls,
					'value2' => $other
				);
			}
		}
	}
}

function tr_pdd_get_marketing_groups($dis_ID)
{
	global $wpdb;
	$groups = $wpdb->get_col("SELECT gr_ID FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_ID} ");
	return $groups;
}

function tr_pdd_get_report_by_id($id, $btype = 'tox', $mid = 0)
{
	global $wpdb;

	$table = tr_pdd_get_table_by_btype($btype);
	$table_reports = tr_pdd_get_table_report_by_btype($btype);

	$where = " re.id = {$id} ";
	if ($mid > 0) {
		$where = " ba.mid = {$mid} ";
	}

	$fields = '';
	$joins = '';
	if ($btype == 'tox') {
		$fields .= "
            cp.cus_ID,
            cp.cus_result_style,
            ";
		$joins .= " LEFT JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID ";
	} else if ($btype == 'pcr') {
		$fields .= 're.shownogrowth_note,';
		if (tr_get_option('ci_result_settings_pcr', 'cli_pha_guid')) {
			$fields .= ' re.clinical_pharmacy_guidance, ';
		}
	}

	if (tr_get_option('ci_pdf_data', 'pdf_approved_by')) {
		$fields .= ' re.approved_by_ID, ';
	}

	$sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            rl.lab_logo as ref_logo,
            rl.lab_results_logo as ref_results_logo,
            rl.lab_dba_init as ref_lab_dba_init,
            rl.lab_ref_only_logo,
            rl.lab_ref_only_address,
            rl.lab_address as ref_lab_address,
            rl.lab_city as ref_lab_city,
            rl.lab_state as ref_lab_state,
            rl.lab_zip as ref_lab_zip,
            rl.lab_name as ref_lab_name,
            rl.lab_phone as ref_lab_phone,
            rl.lab_clia as ref_lab_clia,
            rl.lab_director as ref_lab_director,
            rl.lab_fax as ref_lab_fax,
            rl.lab_second_line_hpdf as ref_lab_second_line_hpdf,
            rl.lab_color as ref_lab_color,
            re.id,
            re.estatus,
            re.patient_id,
            re.created_date,
            re.created,
            re.instrument,
            re.show_comment1,
            re.show_comment2,
            re.box_uploaded,
            re.modified,
            re.report_ref,
            re.final_results,
            re.approve_date,
            re.upload_files,
            
            cli.cli_street,
            cli.cli_city,
            cli.cli_state,
            cli.cli_zip,
            cli.cli_result_style,
            cli.cli_result_style_pcr,
            cli.cli_vurine_top,
            cli.cli_no_history,
            cli.`cli_om-phone`,
            doc.doc_specialty,
            doc.doc_npi,
            {$fields}
            pa.*
            
    FROM {$table} ba

    left JOIN {$table_reports} re ON ba.assen = re.patient_id
    
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
    left join ci_clinic cli ON cli.cID = ba.cli_ID
    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
    left join ci_patients pa ON pa.pa_ID = ba.patient_id
    {$joins}
    WHERE {$where} group by ba.mid limit 1";

	$report = $wpdb->get_row($sql, ARRAY_A);

	if (!$report) {
		return $report;
	}

	if (isset($_REQUEST['debugq'])) {
		echo $sql;
		var_dump($wpdb->last_error);
		echo '<br>';
	}

	if (empty($report['pa_code'])) {
		$report['pa_code'] = trpdd_patient_encode($report['pa_ID']);
		$wpdb->update('ci_patients', array('pa_code' => $report['pa_code']), array('pa_ID' => $report['pa_ID']));
	}


	if (!empty($report['current_medication'])) {
		$report['medications'] = $report['current_medication'];
	}

	$report['medications'] = str_replace(', ', ',', isset($report['medications']) ? $report['medications'] : '');

	if ($report['dob'] == '0000-00-00') $report['dob'] = '';
	if (in_array($report['lab_phone'], array('000', '000-000-000', '0', '00'))) {
		$report['lab_phone'] = '';
	}

	if ((empty($report['spec_received']) || $report['spec_received'] == '0000-00-00') && !empty($report['doc'])) {
		$report['spec_received'] = date('Y-m-d H:i:s', strtotime($report['doc']) + 3600);
	}

	if (tr_get_option('ci_pdf_data', 'receiving_offtime') && !empty($report['spec_received'])) {
		$report['spec_received'] = date('Y-m-d', strtotime($report['spec_received']));
	}

	if (empty($report['created'])) {
		$report['created'] = $report['batch_created'];
	}
	if (empty($report['created_date'])) {
		$report['created_date'] = $report['created'];
	}
	if (empty($report['created_date'])) {
		$report['created_date'] = $report['doc'];
	}

	if (empty($report['upload_files']) && empty($report['estatus'])) {
		$report['created_date'] = date_i18n('Y-m-d');
	}

	if (empty($report['acquisition_date']) || $report['acquisition_date'] == '0000-00-00') {
		$report['acquisition_date'] = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$1-$2-$3', $report['batch_id']);
	}

	if ($report['approve_date'] == '0000-00-00 00:00:00') {
		$report['approve_date'] = '';
	}
	if (empty($report['approve_date']) && $report['estatus'] == 'C') {
		if ($report['modified'] > 0) {
			$report['approve_date'] = date('Y-m-d H:i:s', $report['modified']);
		} else {
			$report['approve_date'] = $report['created'];
		}
	}

	$report['collection_date'] = $report['doc'];
	$report['received_in_lab'] = $report['spec_received'];
	$report['patient_dob'] = $report['dob'];

	if (empty($report['type_of_specimen']) && $btype == 'tox') {
		$report['type_of_specimen'] = 'Urine';
	}
	if (!isset($report['sample_type'])) {
		$report['sample_type'] = '';
	}
	if (!empty($report['type_of_specimen'])) {
		$report['report_sample_type'] = isset($report['sample_type']) ? $report['sample_type'] : '';
		$report['sample_type']      = $report['type_of_specimen'];
	}

	if (!empty($report['sample_type'])) {
		$sample_type_arr = explode(',', trim($report['sample_type']));
		$sample_type_arr = array_unique($sample_type_arr);

		$report['sample_type'] = implode(',', $sample_type_arr);
	}



	$report['matrix'] = (stripos($report['sample_type'] ?? '', "oral") !== false) ? "oral" : strtolower($report['sample_type'] ?? '');


	$report['sublab'] = $report['lab_dba_init'];
	$report['physician'] = $report['requesting_physician'];


	$repair = array(
		'physician' => 'requesting_physician',
		'patient_id' => 'assen',
		'sample_type' => 'type_of_specimen',
	);



	foreach ($repair as $k => $vl) {
		if (empty($report[$k]) || $report[$k] == '0000-00-00' || $report[$k] == '0000-00-00 00:00:00') {

			$report[$k] = $report[$vl];
		}
	}

	$report['sample_type'] = str_replace('	,', '', $report['sample_type'] ?? '');

	//check spec_received and doc
	$p_time = abs(strtotime($report['spec_received']) - strtotime($report['doc']));
	if ($p_time > 48 * 3600) {
		$report['show_comment1'] = 1;
	}
	if ($p_time > 72 * 3600) {
		$report['show_comment2'] = 1;
	}

	$report = CI_Base_Lab::apply_lab_field_history($report);

	if (!empty($report['lab_results_logo'])) {
		$report['lab_logo'] = $report['lab_results_logo'];
	}
	if (!empty($report['ref_results_logo'])) {
		$report['ref_logo'] = $report['ref_results_logo'];
	}

	if ($report['ref_lab_ID'] && $report['lab_ref_only_address']) {
		$report['header_lab_address'] = trpdd_format_address($report['ref_lab_address'], $report['ref_lab_city'], $report['ref_lab_state'], $report['ref_lab_zip'], true);
		$report['header_lab_name'] = $report['ref_lab_name'];
		$report['header_lab_phone'] = $report['ref_lab_phone'];
		$report['header_lab_clia'] = $report['ref_lab_clia'];
		$report['header_lab_director'] = $report['ref_lab_director'];
		$report['header_lab_second_line_hpdf'] = $report['ref_lab_second_line_hpdf'];
		$report['header_lab_fax'] = $report['ref_lab_fax'];
	} else {
		$report['header_lab_address'] = trpdd_format_address($report['lab_address'], $report['lab_city'], $report['lab_state'], $report['lab_zip'], true);
		$report['header_lab_name'] = $report['lab_name'];
		$report['header_lab_phone'] = $report['lab_phone'];
		$report['header_lab_clia'] = $report['lab_clia'];
		$report['header_lab_director'] = $report['lab_director'];
		$report['header_lab_second_line_hpdf'] = $report['lab_second_line_hpdf'];
		$report['header_lab_fax'] = $report['lab_fax'];
	}

	$report['color'] = tr_get_option('ci_pdf_data', 'pdf_main_color');
	if (isset($report['ref_lab_color']) && !empty($report['ref_lab_color'])) {
		$report['color'] = $report['ref_lab_color'];
	} else if (isset($report['lab_color']) && !empty($report['lab_color'])) {
		$report['color'] = $report['lab_color'];
	}

	if (empty($report['color']) || strlen($report['color']) < 3) {
		$report['color'] = '#059dab';
	}

	return $report;
}


function tr_pdd_stripslashes_deep($value)
{
	$value = is_array($value) ?
		array_map('tr_pdd_stripslashes_deep', $value) :
		trim(stripslashes($value));

	return $value;
}

function tr_pdd_display_date($date, $format = 'm/d/Y', $localtime = false, $removehourzero = false)
{

	if ($date == '' || $date == '0000-00-00' || $date == '00/00/0000') return '';

	$offset = 0;
	if ($localtime) {
		$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
	}

	if (strpos($date, '-') !== false) {
		$date = date($format, strtotime($date) + $offset);
	}
	if ($removehourzero) {
		$date = str_replace(array(' 00:00', ' 00:00:00'), '', $date);
	}
	return $date;
}

function tr_pdd_get_report_medis()
{
	$medis = array(
		'Opiates' => '80361',
		'Benzodiazepines' =>     '80346',
		'Cocaine' =>     '80353',
		'Cannabinoids' =>     '80349',
		'Buprenorphine' =>     '80348',
		'Barbiturates' =>     '80345',
		'Amphetamines' =>     '80324',
		'Antidepressants' =>     '80337',
		'Heroin metabolite' => '80356',
	);
	return $medis;
}

function trpdd_cal_commission($paid, $com, $ins_mix = '', $btype = '')
{
	$newcom = json_decode($com, true);
	if (is_array($newcom) && isset($newcom[$btype])) {
		$com = $newcom[$btype];
	}
	if (isset($_GET['debug1'])) {
		$paid = 3;
		var_dump($btype);
		var_dump($newcom);
		var_dump($com);
		var_dump($ins_mix);
	}
	if ($paid < 0.01) {
		return 0;
	}

	$return 	= 0;
	$percent 	= '';
	$add 		= '';
	if (!empty($com)) {
		if (strpos($com, ':') > 0 || strpos($com, '=') > 0) {
			//$ins_mix = 'C';
			if (empty($ins_mix)) {
				$ins_mix = 'ALL';
			}
			$cals = explode('&', $com);
			foreach ($cals as $cal) {
				$mxtype = stripos($cal, $ins_mix . ':') !== false ? $ins_mix . ':' : '';
				if (empty($mxtype)) {
					$mxtype = stripos($cal, $ins_mix . '=') !== false ? $ins_mix . '=' : '';
				}
				if (!empty($mxtype)) {
					$cal = trim(str_replace($mxtype, '', $cal));
					if (stripos($cal, '$Paid') === false) {
						$cal = str_replace('%', '*' . $paid . '/100', $cal);
					}
					$cal = str_replace(array('$Paid', '%'), array($paid, '/100'), $cal);

					$return = 0;
					try {
						$return = @eval("return {$cal};");
					} catch (Error $e) {
					}
					if ($return < 0) {
						$return = 0;
					} else {
						//var_dump($return);
						//echo $cal;
					}
				}
			}
		} else if (strpos($com, '+') > 0) {
			list($percent, $add) = explode('+', $com, 2);
		} else if (strpos($com, '%') > 0) {
			$percent = $com;
		} else {
			$add = $com;
		}

		if (!empty($percent)) {
			$percent = str_replace('%', '', $percent);
			$return += $paid * $percent / 100;
		}

		if (!empty($add)) {
			$return += $add;
		}
	}
	return $return;
}


function trpdd_get_tecan_letters()
{
	global $wpdb;
	$letter = 'C';
	$number = 1;
	$new_letter = $wpdb->get_var("select te_letter from ci_tecan order BY te_letter DESC limit 1");
	if (!empty($new_letter)) {
		$letter = $new_letter;
		$number = $wpdb->get_var("select te_number from ci_tecan WHERE te_letter = '{$letter}' order BY te_number DESC limit 1") + 1;
		if ($number >= 10000) {
			$letter = ++$letter;
			$number = 1;
		}
	}
	return array('letter' => $letter, 'number' => $number);
}

function trpdd_format_tecan_letters($letter, $number)
{
	return $letter . sprintf('%04d', $number);
}

function trpdd_get_tecan_batch_id_by_barcode($barcode)
{
	global $wpdb;
	preg_match("/([a-zA-Z]{1})([0-9]{4})/", $barcode, $matches);
	if ($matches) {
		$letter = $matches[1];
		$number = $matches[2];
		$batch_id = $wpdb->get_var("select ba.batch_id from ci_batches ba 
                    JOIN ci_tecan te ON te.te_assen = ba.assen 
                    WHERE  te.te_letter = '{$letter}' AND te.te_number = {$number} limit 1");

		if (!empty($batch_id)) {
			$barcode = $batch_id;
		}
	}
	return $barcode;
}

function tr_pdd_get_poc_drugs($poc)
{
	$pocs = array(
		'Amphetamine' => 'Amphetamine',
		'Barbiturate' => 'Amobarbital Pentobarbital,Butabarbital,Butalbital,Phenobarbital,Secobarbital',
		'Benzodiazepine' => 'Alprazolam,Diazepam,Flurazepam,Lorazepam,Temazepam,Oxazepam,Clonazepam,Nordiazepam,Midazolam,Flunitrazepam,Triazolam',
		'Buprenorphine' => 'Buprenorphine,Norbuprenorphine',
		'Cocaine' => 'Benzoylecgonine',
		'Methadone' => 'Methadone,EDDP',
		'Methamphetamine' => 'Methamphetamine,Amphetamine',
		'MDMA' => 'MDMA',
		'Opiates' => 'Hydrocodone,Hydromorphone,Oxycodone,Oxymorphone,Codeine,Morphine',
		'Oxycodone' => 'Oxycodone',
		'PCP' => 'PCP',
		'TCA' => 'Imipramine,Desipramine,Amitriptyline,Nortriptyline',
		'THC' => 'THC,THC-COOH',
		'6-MAM' => '6-MAM',
		'Fentanyl' => 'Fentanil',
		'Cannabinoids' => 'Cannabinoids,THC-COOH'
	);
	if (!isset($pocs[$poc])) {
		$pocs[$poc] = $poc;
	}
	return $pocs[$poc];
}


function tr_pdd_insert_hos($table, $outfile, $sub)
{
	global $wpdb;
	$insert = array(
		'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
		'user_id' => get_current_user_id()
	);

	if ($sub == 'Req') {
		list($doc, $clinic, $assen, $sample_type, $patient) = explode('-', str_ireplace('.pdf', '', $outfile), 5);
		if ($doc > 0) {
			preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/", $doc, $doc_str);
			if ($doc_str) {
				$cdatefile = $doc_str[1] . '-' . $doc_str[2] . '-' . $doc_str[3];
				$insert['doc'] = $cdatefile;
			}
		}
	} else if ($sub == 'Notes') {
		list($assen, $sample_type, $patient) = explode('-', str_ireplace('.pdf', '', $outfile), 3);
	} else {
		list($doctor, $assen, $sample_type, $patient) = explode('-', str_ireplace('.pdf', '', $outfile), 4);
	}

	$exists = $wpdb->get_var("select th_ID from {$table} where subfolder='{$sub}' and file_name='{$outfile}' ");
	if (!$exists) {
		$insert['assen'] = $assen;
		$insert['clinic'] = $clinic;
		$insert['doctor'] = $doctor;
		$insert['patient_name'] = $patient;
		$insert['subfolder'] = $sub;
		$insert['file_name'] = $outfile;
		$wpdb->insert($table, $insert);
	}
}


/**
 * @param int $type 0: urine | 1: screening | 2: VDX Synthetic Urine
 * @param null $date
 * @param int $mid
 *
 * @return array
 */
function tr_pdd_get_result_validity($type = 0, $date = null, $mid = 0, $saved_form = false)
{
	global $wpdb, $etg_to_urine;

	$data_tbl = $saved_form ? 'ci_result_test_speciments_saved' : 'ci_result_test_speciments';
	$where = '';
	if ($type > 1) {
		$where .= " and u.u_showinreq = {$type} ";
	} else if ($type == 1) {
		$where .= " and u.u_showinreq = 1 ";
		if ($etg_to_urine) {
			$where .= " and u.u_name != 'ETG' ";
		}
	} else {

		$where .= " and (u.u_showinreq = 0 ";
		if ($etg_to_urine) {
			$where .= " or u.u_name = 'ETG' ";
		}
		$where .= ")";
	}

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}



	$joins = '';
	$fields = '';
	if ($mid > 0) {
		$joins .= " left join {$data_tbl} t ON t.testid = u.u_ID and t.mid = {$mid} ";
		$fields .= ', t.* ';
	}

	$where .= " and ( u.u_start is null or u.u_start='' or u.u_start < '1999-01-01' or u.u_start <= '{$date}' ) ";
	$where .= " and ( u.u_end is null or u.u_end = '' or u.u_end < '1999-01-01' or u.u_end >= '{$date}' )";

	$_readonly_screening = trpdd_get_batch_meta($mid, 'tox', '_readonly_screening', false, false);
	if ($_readonly_screening) {
		$fields .= ', 1 as readonly ';
	} else {
		$where .= " and u.u_status = '1'  ";
	}

	$sql = "select u.* {$fields} from ci_urine_validity u 
            {$joins}
            where 1 {$where} 
            order by u.u_position asc, u.u_ID asc";

	$rs = $wpdb->get_results($sql, ARRAY_A);
	if (isset($_GET['debug'])) {
		echo $sql;
	}
	return $rs;
}


function tr_pdd_where_batch_rejected($return_array = false, $notin = false, $btype = '', $addings = array())
{
	$only_reject = true;
	if (in_array('cancelled', $addings) || in_array('archived', $addings)) {
		$only_reject = 2;
	}

	$batch_statuses = tr_pdd_get_batch_statuses(false, $only_reject, true, $btype);

	if ($return_array) {
		return $batch_statuses;
	}
	$statuses = is_array($addings) ? $addings : array();
	foreach ($batch_statuses as $k => $vl) {
		if (!in_array($k, $statuses)) {
			$statuses[] = $k;
		}
	}
	$statuses[] = 'rejected';
	$statuses = implode("','", $statuses);

	return "  (ba.batch_status " . ($notin ? 'not' : '') . " in ('{$statuses}') )"; // or (ba.position>=7000 and ba.position<8000) )";
}

function tr_pdd_where_batch_incomplete($return_array = false, $btype = '')
{
	$batch_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);

	$batch_statuses += array(
		'incomplete'    => 'Incomplete',
		'in_spe_nambir' => 'Incomplete:  Missing Specimen Identification: Name and Birthdate',
		'in_spe_pa_nam' => 'Incomplete:  Missing Specimen Identification: Missing Patient Name',
		'in_spe_bir' => 'Incomplete:  Missing Specimen Identification: Missing Birthdate',
		'in_spe_bar' => 'Incomplete:  Missing Specimen Identification: Missing Barcode',
		'in_req' => 'Incomplete:  Missing Req Form or Req Form Empty',
		'in_doc' => 'Incomplete:  Missing Doctor Name',
		'in_ins' => 'Incomplete:  Missing Patient Insurance Information',
		'in_bir' => 'Incomplete:  Missing Birthdate',
		'in_pa_sig' => 'Incomplete:  Missing Patient Signature',
	);
	if ($return_array) {
		return $batch_statuses;
	}
	$statuses = array();
	foreach ($batch_statuses as $k => $vl) {
		$statuses[] = $k;
	}
	$statuses = implode("','", $statuses);

	return "  ba.batch_status in ('{$statuses}') ";
}

function tr_pdd_get_batch_statuses($key = false, $only_reject = false, $withoutempty = false, $btype = '')
{
	global $wpdb;
	if (empty($btype)) {
		$btype = 'all';
	}
	$sql = "select s_type,s_key,s_text 
            from ci_batches_status 
            where istatus = 1 ";
	if ($btype != 'all') {
		$sql .= " and btype='{$btype}' ";
	}
	if ($only_reject === 2) {
		$sql .= " and s_type in ('re','ca') ";
	} else if ($only_reject) {
		$sql .= " and s_type='re' ";
	}
	$sql .= " group by s_type,s_text ";
	$sql .= " order by iorder asc ";

	$rs = $wpdb->get_results($sql);
	$batch_statuses = array();
	foreach ($rs as $r) {
		if (in_array($r->s_key, array('rejected', 'incomplete'))) $r->s_type = '';
		$keytype = !empty($r->s_type) ? $r->s_type . '_' . $r->s_key : $r->s_key;
		$batch_statuses[$keytype] = $r->s_text;
	}



	if (empty($batch_statuses)) {
		$reject_status = array(
			'rejected' => 'Rejected',
			're_srrir' => 'Rejected - Sample Recollection required. Indeterminate results.',
			're_spilled' => 'Rejected - Spilled Contents',
			're_noreci' => 'Rejected - Not Received',
			're_no_spe' => 'Rejected - No Specimen Barcode or Req Form in Bag',
			're_no_req' => 'Rejected - No Requisition Form Found',
			're_inoreq' => 'Rejected - Insufficient Information/No Req Form',
			're_bluecr' => 'Rejected - Blue Cross Blue Shield Medicaid',
			're_sp_exp' => 'Rejected - Specimen Expired',
			're_sp_ctt' => 'Rejected - Specimen contents too thick',
			're_sp_vfail' => 'Rejected - Specimen Validity Fail',
			're_mtios' => 'Rejected - Missing Two Identifiers on Specimen',
			're_barcode' => 'Rejected - Barcode Not Found',
			're_wrongst' => 'Rejected - Wrong Specimen Type',
			're_qnotsuf' => 'Rejected - Quantity Not Sufficient',
			're_noswab' => 'Rejected - No swab in tube',
			're_danbl' => 'Rejected - Duplicate accession number/barcode label',
			're_yscbp' => 'Insufficient Sample - Your specimen cannot be processed by the lab, please return for a recollection as soon as possible',
			're_inco' => 'Inconclusive',

		);

		if ($btype == 'blood') {
			$reject_status['re_notmffa'] = 'Rejected - The sample has clots, therefore it is not possible to process for MF and FA. As a result, this sample was rejected.';
			$reject_status['re_48hr'] = 'Rejected - Note: The sample arrived in the laboratory 48 hours after the time of collection. Thus, the sample was rejected and no results are shown.';
		}
		$reject_status['re_prabr'] = 'PRELIMINARY - ANALYSIS BEING REPEATED';

		$batch_statuses = array(
			'not_received' => 'Not Received',
			'received' => 'Received',
			'plated'   => 'Plated',
			'archived' => 'Archived',
			'processing' => 'Processing',
			'completed' => 'Completed',
			'cold_storage'  => 'Cold Storage',
			'need_quantification' => 'Need Quantification',
			'need_review'   => 'Needs Review',
			'need_result'   => 'Needs Resulted',
			'hold'          => 'On Hold',
			'refused'       => 'Refused',
			'resulted'      => 'Resulted',


			'incomplete'    => 'Incomplete',
			'in_spe_nambir' => 'Incomplete:  Missing Specimen Identification: Name and Birthdate',
			'in_spe_pa_nam' => 'Incomplete:  Missing Specimen Identification: Missing Patient Name',
			'in_spe_bir'    => 'Incomplete:  Missing Specimen Identification: Missing Birthdate',
			'in_spe_bar'    => 'Incomplete:  Missing Specimen Identification: Missing Barcode',
			'in_req'        => 'Incomplete:  Missing Req Form or Req Form Empty',
			'in_doc'        => 'Incomplete:  Missing Doctor Name',
			'in_ins'        => 'Incomplete:  Missing Patient Insurance Information',
			'in_bir'        => 'Incomplete:  Missing Birthdate',
			'in_pa_sig'     => 'Incomplete:  Missing Patient Signature',
			// 'Tecan' => 'Tecan',
		);
		$batch_statuses += $reject_status;

		if ($only_reject) {
			$batch_statuses = $reject_status;
		}
	}

	if ($key !== false) {
		return isset($batch_statuses[$key]) ? $batch_statuses[$key] : '';
	}


	if (!$withoutempty) {
		$batch_statuses = array('' => 'None') + $batch_statuses;
	}


	return $batch_statuses;
}
function tr_pdd_get_batch_statuses_disabled_keys($btype = '', $show_in = '', $include_status = '')
{
	global $wpdb;
	if (!tr_get_option('ci_pdf_data', 'new_batch_status') || empty($show_in)) {
		return [];
	}
	if (empty($btype)) $btype = 'all';

	$sql = "select s_type, s_key 
            from ci_batches_status 
            where istatus = 1 ";
	if ($btype != 'all') {
		$sql .= " and btype='{$btype}' ";
	}
	$sql .= " and {$show_in} = 0 ";
	if (!empty($include_status)) {
		$sql .= " and CONCAT(s_type, '_', s_key) != '{$include_status}' ";
	}
	$sql .= " group by s_type, s_key ";

	$rs = $wpdb->get_results($sql);
	$disabled_keys = [];
	foreach ($rs as $r) {
		if (in_array($r->s_key, ['rejected', 'incomplete'])) $r->s_type = '';
		$disabled_keys[] = !empty($r->s_type) ? $r->s_type . '_' . $r->s_key : $r->s_key;
	}
	return $disabled_keys;
}

function tr_pdd_get_batch_statuses_on_hold($statues, $batch_status, $mid, $table_batch, $btype)
{
	global $wpdb;
	if ($batch_status == 'hold' && tr_get_option('ci_pdf_data', 'new_batch_status')) {
		$hold_status = $wpdb->get_var("select meta_value from {$table_batch}_meta where mid={$mid} and meta_key = 'hold_batch_status'") ?: '';
		if ($hold_status) {
			if (!isset($statues[$hold_status])) {
				$sql = "select s_text from ci_batches_status where btype='{$btype}' and CONCAT(s_type, '_', s_key) = '{$hold_status}'";
				$statues[$hold_status] = $wpdb->get_var($sql) ?: $hold_status;
			}
			$statues = [
				$hold_status => $statues[$hold_status],
				'hold' => 'On Hold'
			];
		}
	}
	return $statues;
}


function tr_pdd_update_result_ordered($re_id)
{
	global $wpdb;

	$bat = $wpdb->get_row("select ba.panel_tests_requested from ci_batches ba
                        join ci_reports re ON re.patient_id = ba.assen
                        where re.id = {$re_id}", ARRAY_A);
	if (!$bat) return;
	$drug_pairs = tr_pdd_get_drug_pairs();
	$tests = tr_pdd_parse_panel_tests_requested($bat['panel_tests_requested'], $drug_pairs);
	$tests = array_map('trim', $tests);
	$tests = implode("','", $tests);

	$wpdb->query("update ci_results set has_order = 1 where report_id = {$re_id}  and drug!='' ");
	$rs =  $wpdb->query("update ci_results set has_order = 0 where  report_id = {$re_id} and drug not in ('{$tests}') ");
	$wpdb->query("update ci_reports set 
                                count_drugs = (select count(*) from ci_results where report_id = {$re_id} and has_order=1) 
                                where id = {$re_id} ");
	return $rs;
}

function trpdd_form_unique_id($name = 'form_unique_id')
{
	echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . md5(microtime(true)) . '" />';
}

function trpdd_check_form_unique_id($name = 'form_unique_id')
{
	$key = $_POST[$name];
	if (empty($key)) {
		return false;
	}
	$exists = isset($_SESSION[$name]) && is_array($_SESSION[$name]) ? $_SESSION[$name] : array();
	if (isset($exists[$key])) {
		return true;
	}
	//remove expiry
	foreach ($exists as $k => $ti) {
		if ($ti < time() - 60) {
			unset($exists[$k]);
		}
	}
	$exists[$key] = time();
	$_SESSION[$name] = $exists;
	return false;
}


function tr_pdd_get_table_by_btype($btype = 'tox')
{
	$table = 'ci_batches';
	if (!empty($btype) && $btype != 'tox') {
		$table .= '_' . $btype;
	}
	return $table;
}

function tr_pdd_get_table_report_by_btype($btype = 'tox')
{
	$table = 'ci_reports';
	if (!empty($btype) && $btype != 'tox' && $btype != 'dna' && $btype != 'rpp_gi') {
		$table .= '_' . $btype;
	}
	return $table;
}

function tr_pdd_get_table_result_by_btype($btype = 'tox')
{
	$table = 'ci_results';
	if (!empty($btype) && $btype != 'tox' && $btype != 'dna' && $btype != 'rpp_gi') {
		$table .= '_' . $btype;
	}
	return $table;
}



function tr_pdd_get_numtype($btype = 'tox')
{
	$num = array_search($btype, tr_pdd_get_batch_types());
	return $num;
}

function tr_pdd_get_table_mid_by_id($id)
{
	$table = 'ci_batches';
	$btype = 'tox';
	$mid = $id;
	$subtype = '';
	$arr = explode('_', $id);
	if (count($arr) >= 2) {
		$btype = $arr[0];
		$mid = $arr[1];
		$subtype = isset($arr[2]) ? $arr[2] : $subtype;
	}

	if (!empty($btype) && $btype != 'tox') {
		$table .= '_' . $btype;
	}

	return array($table, $mid, $btype, $subtype);
}


function tr_pdd_check_header_result($rowdt, $required_cols)
{
	global $ci_found_columns;

	if (!is_array($ci_found_columns)) {
		$ci_found_columns = array();
	}

	if (!is_array($rowdt)) {
		//echo 'by not array';
		return false;
	}

	foreach ($rowdt as $kr => $rvl) {
		if (is_null($rvl)) {
			unset($rowdt[$kr]);
		} else {
			$rowdt[$kr] = trim(str_replace(array(' ', "\t"), '', $rvl));
		}
	}


	if (count($required_cols) > count($rowdt)) {
		//echo 'by count';
		return false;
	}

	if (is_array($required_cols) && count($required_cols) > 0) {
		$pass_child = true;

		foreach ($required_cols as $rc) {
			if (is_array($rc)) {
				$pass = false;
				foreach ($rc as $f) {
					$rck = trim(str_replace(array(' ', "\t"), '', $f));
					if (in_array($rck, $rowdt)) {
						$pass = true;
						break;
					}
				}

				if ($pass) {
					$ci_found_columns[] = $rc[0];
				} else {
					$pass_child = false;
				}
			} else {
				$rck = trim(str_replace(array(' ', "\t"), '', $rc));
				if (!is_array($rowdt) || !in_array($rck, $rowdt)) {
					$pass_child = false;
				} else {
					$ci_found_columns[] = $rc;
				}
			}

			if (!$pass_child) {
				//echo 'by not $pass_child';
				$rowdt = false;
				break;
			}
		}
	}

	return $rowdt;
}


function inputTxt($id, $name, $label, $placeholder = "")
{
	echo "<label for='" . $id . "'>" . $label . "</label>
			  <div class='input-wrap'>
				<input type='text' id='" . $id . "' name='" . $name . "' placeholder='" . $placeholder . "' />
			  </div>";
}

function trpdd_genders($key = false)
{
	$genders = array('' => 'Select', 'F' => 'Female', 'M' => 'Male');
	if (tr_get_option('ci_pdf_data', 'enable_patientug')) {
		$genders['U'] = 'Unknown';
	}
	if (tr_get_option('ci_pdf_data', 'enable_patientgdts')) {
		$genders['D'] = 'Decline To Specify';
	}

	if (tr_get_option('ci_pdf_data', 'enable_patientug')) {
		$genders['U'] = 'Unknown';
	}
	if (tr_get_option('ci_pdf_data', 'enable_patientgo')) {
		$genders['O'] = 'Other';
	}
	if ($key !== false) {
		if (!empty($key)) {
			return $genders[$key];
		}
		return '';
	}
	return $genders;
}

function trdd_dob2ages($dob, $today = '', $return_array = false)
{
	if (empty($today)) {
		$today = date("Y-m-d");
	} else {
		$today = date('Y-m-d', strtotime($today));
	}
	if (stripos($dob, '/') > 0) {
		$dob = date('Y-m-d', strtotime($dob));
	}
	$diff = date_diff(date_create($dob), date_create($today));
	$years = (int)$diff->format('%y');

	if ($return_array) {
		$days = (int)$diff->format('%a');
		$weeks = $days / 7;
		$months = (($diff->y) * 12) + ($diff->m);

		return array(
			'day' => $days,
			'week' => $weeks,
			'month' => $months,
			'year' => $years,
		);
	}
	return $years;
}


function trpdd_replace_symbol_db($text)
{
	$text = str_replace("≥", '>=', $text);
	$text = str_replace("≤", '<=', $text);
	$text = trim($text);
	return $text;
}

function trpdd_show_symbol($text, $showhtml = false)
{
	$text = trim($text);
	$text = str_replace(array('>=', '<='), array("≥", "≤"), $text);
	if ($showhtml) {
		$text = str_replace(array('>', '<'), array("&gt;", "&lt;"), $text);
	}
	return $text;
}


function trpdd_get_html_report($args)
{
	global $wpdb, $current_role;

	$report_type = '';
	extract($args);

	if (empty($report_type)) return;

	$orientation = 'portrait';
	$hasreport = false;
	$filename = 'report_' . $report_type . '.pdf';
	$report_file = $report_type . '.php';
	ob_start();
	require(TRCPDD_PATH . 'reports/' . $report_file);

	if (empty($orientation)) {
		$orientation = 'portrait';
	}
	return array(
		'html' => ob_get_clean(),
		'orientation' => $orientation,
		'filename' => $filename,
		'hasreport' => $hasreport
	);
}

function trpdd_pdf_html($content, $title = '', $pagination = true)
{
	ob_start();
	require(TRCPDD_PATH . 'reports/pdf.php');
	return ob_get_clean();
}

function trpdd_port_opened($port)
{
	$socket = @stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);

	if ($socket) {
		@stream_socket_shutdown($socket, STREAM_SHUT_WR);
		return false;
	} else {
		return true;
	}
}

function trpdd_try_connect($address, $port)
{
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if (socket_connect($socket, $address, $port)) {
		return true;
	} else {
		return false;
	}
}

function tr_pdd_capitalized($name)
{

	if (stripos($name, ', ') === false) {
		$name = str_replace(',', ', ', $name);
	}
	$name = str_replace('(', '( ', $name);
	$name = strtolower($name);
	$name = ucwords($name);
	$name = str_replace('( ', '(', $name);
	$name = trim($name);
	return $name;
}

function tr_pdd_get_result_status($int, $color = false)
{
	$status = '';

	switch ($int) {
		case '2':
			$status = 'Preliminary';
			break;
		case '1':
			$status = 'Final';
			break;
		case '3':
			$status = 'Amended';
			break;
	}

	if ($color && !empty($status)) {
		$status = '<span class="' . ($int === '1' ? 'blue' : 'red') . '">' . $status . '</span>';
	}

	return $status;
}

function tr_pdd_get_patient_duplicate($pa, $bat = null)
{
	global $wpdb;
	$mid = 0;

	$patient = false;
	if ($pa['pa_ID'] > 0) {
		$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pa['pa_ID']} ");
	}

	$need_check = true;
	if ($bat && $patient) {
		$mid = $bat->mid;
		if (
			$bat->patient_id == $pa['pa_ID']
			&& (strtolower($patient->pa_first) == strtolower($pa['pa_first'])
				&& strtolower($patient->pa_last) == strtolower($pa['pa_last'])
				&& $patient->pa_dob == $pa['pa_dob']
			)
		) {
			$need_check = false;
		}
	}
	//check patient exist
	if (
		$need_check && $patient &&
		(strtolower($patient->pa_first) != strtolower($pa['pa_first'])
			|| strtolower($patient->pa_last) != strtolower($pa['pa_last']))
	) {
		$count_b_patient = 0;
		foreach (tr_pdd_get_batch_types() as $btype) {
			$table_batch = tr_pdd_get_table_by_btype($btype);
			$count_b_patient += $wpdb->get_var("select count(mid) 
			                from {$table_batch} 
			                where patient_id = {$patient->pa_ID} 
			                and mid != '{$mid}'");
		}

		if ($count_b_patient > 0) {
			$patient = false;
		}
	}
	return $patient;
}

function trpdd_use_screening_vdx($mid, $date)
{
	$settings = get_option('ci_urine_validity_settings', array());
	$tests = tr_pdd_get_result_validity(2, $date, $mid);

	return ($settings['vdx'] && count($tests) > 0);
}

function tr_pdd_get_screening_vdx($mid, $date, $batch_id)
{
	global $wpdb;

	if (empty($date)) {
		$date = date_i18n('Y-m-d');
	} else {
		$date = date('Y-m-d', strtotime($date));
	}


	$return = array(
		'checked' => false,
		'comments' => '',
		'outcome' => '',
		'conditions' => array(),
		'condition_match' => 0,
		'resulted' => false,
	);
	$settings = get_option('ci_urine_validity_settings', array());
	$tests = tr_pdd_get_result_validity(2, $date, $mid);
	$return['tests'] = $tests;
	if (!isset($settings['vdx'])) $settings['vdx'] = false;
	if (!isset($settings['debugvdx'])) $settings['debugvdx'] = false;

	if (($settings['vdx'] || isset($_GET['debugvdx'])) && count($tests) > 0) {
		$return['checked'] = true; //$wpdb->get_var("select has_vdx from ci_batches where mid = {$mid} ");
		if ($return['checked']) {
			$ds = array();
			$has_value = true;
			$has_acong = false;
			foreach ($tests as $arr) {
				$arr['u_name'] = strtoupper($arr['u_name']);

				if (strpos($arr['conc'], '@') !== false) {
					if (!$has_acong && $arr['u_name'] != 'OXVDX') {
						$return['has_acong-5'] = true;
					}

					$has_acong = true;
					$arr['has_acong'] = true;
					$return['has_acong'] = true;
				}

				$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

				$arr['u_low'] = trim($arr['u_low']);
				$arr['u_high'] = trim($arr['u_high']);
				if ($arr['conc'] === '' && $arr['u_low'] != '' && $arr['u_high'] != '') {
					$has_value = false;
				}

				$ds[$arr['u_name']] = $arr;
			}

			if ($has_value) {
				if ($batch_id >= 20200814) {
					$return = trpdd_cal_vdx_new($return, $ds);
				} else {
					$return = trpdd_cal_vdx($return, $ds);
				}
			}


			$vdx_pos = $return['outcome'] == 'ABNORMAL' ? 2 : 1;
			$return['has_vdx'] = $vdx_pos;

			$wpdb->update('ci_batches', array('has_vdx' => $vdx_pos), array('mid' => $mid));


			if (isset($_GET['debug'])) {
				var_dump($tests);
			}
		}
	}

	return $return;
}

function trpdd_cal_vdx($return, $ds)
{
	if (!is_array($return)) {
		$return = array();
	}

	if (($ds['PHVDX']['conc'] != '' && $ds['PHVDX']['conc'] <= $ds['PHVDX']['u_low'])
		|| $ds['PHVDX']['conc'] > $ds['PHVDX']['u_high']
	) {
		$return['conditions'][1] = 'pH = <3.0 or >10.5';
	}

	if ((($ds['OXVDX']['conc'] != '' && $ds['OXVDX']['conc'] < $ds['OXVDX']['u_low'])
			|| ($ds['TUVDX']['conc'] != '' && $ds['TUVDX']['conc'] < $ds['TUVDX']['u_low']))
		&& ($ds['SGVDX']['conc'] >= $ds['SGVDX']['u_low'] && $ds['SGVDX']['conc'] <= $ds['SGVDX']['u_high'])
		&& ($ds['CREVDX']['conc'] >= $ds['CREVDX']['u_low'] && $ds['CREVDX']['conc'] <= $ds['CREVDX']['u_high'])
	) {
		$return['conditions'][2] = 'OXH < 10 &/or TRUULD < 10 with “normal” SGI and UCREA ';
	}

	if ((($ds['OXVDX']['conc'] !== '' && $ds['OXVDX']['conc'] < $ds['OXVDX']['u_low']) || ($ds['TUVDX']['conc'] !== '' && $ds['TUVDX']['conc'] < $ds['TUVDX']['u_low']))
		&& (($ds['SGVDX']['conc'] !== '' && $ds['SGVDX']['conc'] < $ds['SGVDX']['u_low']) || ($ds['CREVDX']['conc'] < $ds['CREVDX']['u_low']))
	) {
		$return['conditions'][3] = 'OXH < 10 &/or TRUULD < 10 and SGI <1.003 &/or UCREA <20';
	}


	if ($ds['SGVDX']['conc'] < $ds['SGVDX']['u_low'] || $ds['CREVDX']['conc'] < $ds['CREVDX']['u_low']) {
		$return['conditions'][4] = 'SGI <1.003 &/or UCREA <20';
	}

	if (
		$ds['SGVDX']['conc'] > $ds['SGVDX']['u_high']
		&& ($ds['CREVDX']['conc'] >= $ds['CREVDX']['u_low']
			&& $ds['CREVDX']['conc'] <= $ds['CREVDX']['u_high'])
	) {
		$return['conditions'][6] = 'SGI > 1.0500 and “normal” UCREA Consistent with salting';
	}

	if ($ds['TUVDX']['conc'] > $ds['TUVDX']['u_high']) {
		$return['conditions'][7] = 'TRUULD > 2500';
	}

	if ($ds['CREVDX']['conc'] > 20 && $ds['CREVDX']['conc'] < 40 && $ds['SGVDX']['conc'] < $ds['SGVDX']['u_low']) {
		$return['conditions'][8] = 'UCREA >20 and <40 and SGI <1.003';
	}

	if ($ds['SGVDX']['conc'] > $ds['SGVDX']['u_high'] || $ds['CREVDX']['conc'] > $ds['CREVDX']['u_high']) {
		$return['conditions'][9] = 'SGI >1.0500 &/or UCREA >250';
	}


	$c_sg = $ds['CREVDX']['conc'] / (($ds['SGVDX']['conc'] - 1) * 6667);
	$return['cre_sg'] = $c_sg;

	if ($c_sg > 5) {
		$return['conditions'][10] = 'SGI/UCREA Ratio > 5.0';
	}

	if ($return['has_acong'] && ($ds['OXVDX']['conc'] < $ds['OXVDX']['u_low'])) {
		$return['conditions'][5] = '@ and OxVdx < ' . $ds['OXVDX']['u_low'];
	} else if ($return['has_acong-5']) {
		$return['conditions'][5] = 'AU platform “@” error code';
	}

	$return['condition_match'] = count($return['conditions']);


	if ($return['condition_match'] >= 2) {
		$return['outcome']  = 'ABNORMAL';
		$return['comments'] = 'Validity testing performed on the submitted sample is inconsistent with normal human urine. The specimen may have been impacted by dilution, oxidation, clearing substances or may be a synthetic urine.';

		if (isset($return['conditions'][4]) && isset($return['conditions'][9]) && isset($return['conditions'][10])) {
			$return['comments'] = "Validity testing performed on the submitted sample is inconsistent with normal human urine. The specimen is consistent with possible enzyme inhibitor from alcohol abuse, detergent or adulteration by mercury, zinc or copper.";
		} else if (isset($return['conditions'][4]) && isset($return['conditions'][8])) {
			$return['comments'] = "Validity testing performed on the submitted sample is inconsistent with normal human urine. The specimen is consistent with possible in vivio water/crea/protein loading.";
		}
	} else {
		$return['outcome'] = 'NORMAL';
	}

	$return['resulted'] = true;

	return $return;
}

function trpdd_cal_vdx_new($return, $ds)
{
	if (!is_array($return)) {
		$return = array(
			'conditions' => array(),
			'comments' => array()
		);
	} else {
		$return['conditions'] = array();
		$return['comments'] = array();
	}


	$c_sg = ($ds['SGVDX']['conc'] - 1) > 0 ? $ds['CREVDX']['conc'] / (($ds['SGVDX']['conc'] - 1) * 6667) : 0;
	$return['cre_sg'] = $c_sg;

	if (($ds['PHVDX']['conc'] != '' && $ds['PHVDX']['conc'] <= $ds['PHVDX']['u_low'])
		|| $ds['PHVDX']['conc'] > $ds['PHVDX']['u_high']
	) {
		//pH is abnormally high or low
		$return['conditions'][] = 'pH = <3.0 or >10.5';
		$return['comments'][] = 'Consistent with an adulterated (with an acid or base) urine sample.';
	}

	if (
		$ds['SGVDX']['conc'] > $ds['SGVDX']['u_high']
		&& ($ds['CREVDX']['conc'] >= $ds['CREVDX']['u_low']
			&& $ds['CREVDX']['conc'] <= $ds['CREVDX']['u_high'])
	) {
		//Specific Gravity is greater than 1.05 and normal Creatinine
		$return['conditions'][] = 'SGI > 1.05 and “normal” UCREA Consistent with salting';
		$return['comments'][] = 'Consistent with salting.';
	}

	if ($ds['TUVDX']['conc'] > $ds['TUVDX']['u_high']) {
		//TRUULD is greater than 2500
		$return['conditions'][] = 'TRUULD > 2500';
		$return['comments'][] = 'Consistent with an adulterated urine sample or bacterial growth';
	}

	if ($c_sg > 5) {
		//SGI/UCREA Ratio is greater than 5
		$return['conditions'][] = 'SGI/UCREA Ratio > 5.0';
		//$return['comments'][] = 'Enzyme inhibitor from alcohol abuse, detergent or adulteration by mercury, zinc or copper.';

	}


	if ((($ds['OXVDX']['conc'] !== '' && $ds['OXVDX']['conc'] < $ds['OXVDX']['u_low']) || ($ds['TUVDX']['conc'] !== '' && $ds['TUVDX']['conc'] < $ds['TUVDX']['u_low']))
		&& (($ds['SGVDX']['conc'] !== '' && $ds['SGVDX']['conc'] < $ds['SGVDX']['u_low']) || ($ds['CREVDX']['conc'] < $ds['CREVDX']['u_low']))
	) {
		//Oxidant is less than 10 and / or TRUULD is less than 10 AND SGI less than 1.003 and / or UCREA is less than 20
		$return['conditions'][] = 'OXH < 10 &/or TRUULD < 10 and SGI <1.003 &/or UCREA <20';
		$return['comments'][] = 'Consistent with a diluted urine sample';
	}


	if ($ds['SGVDX']['conc'] < $ds['SGVDX']['u_low'] && $ds['CREVDX']['conc'] > $ds['CREVDX']['u_high']) {
		$return['conditions'][] = 'SGI < ' . $ds['SGVDX']['u_low'] . ' and UCREA > ' . $ds['CREVDX']['u_high'];
		//SGI is less than 1.003 and UCREA is greater than 250
		$return['comments'][] = 'Enzyme inhibitor from alcohol abuse, detergent or adulteration by mercury, zinc or copper';
	}

	if ((($ds['OXVDX']['conc'] != '' && $ds['OXVDX']['conc'] < $ds['OXVDX']['u_low'])
			|| ($ds['TUVDX']['conc'] != '' && $ds['TUVDX']['conc'] < $ds['TUVDX']['u_low']))
		&& ($ds['SGVDX']['conc'] >= $ds['SGVDX']['u_low'] && $ds['SGVDX']['conc'] <= $ds['SGVDX']['u_high'])
		&& ($ds['CREVDX']['conc'] >= $ds['CREVDX']['u_low'] && $ds['CREVDX']['conc'] <= $ds['CREVDX']['u_high'])
	) {
		//Oxidant is less than 10 and / or TRUULD is less than 10 WITH "normal" (in range) SGI and UCREA
		$return['conditions'][] = 'OXH < ' . $ds['OXVDX']['u_low'] . ' &/or TRUULD < ' . $ds['TUVDX']['u_low'] . ' with “normal” SGI and UCREA';
		$return['comments'][] = 'Consistent with an adulterated urine sample or synthetic urine';
	}


	if ($ds['SGVDX']['conc'] > $ds['SGVDX']['u_high'] || $ds['CREVDX']['conc'] > $ds['CREVDX']['u_high']) {
		//SGI is greater than 1.05 and / or UCREA is greater than 250
		$return['conditions'][] = 'SGI > 1.05 &/or UCREA >250';
		$return['comments'][] = 'Consistent with a concentrated urine sample';
	}

	$return['comments'] = array_unique($return['comments']);
	$return['comments'] = implode("\n", $return['comments']);
	if ($return['has_acong']) {
		$return['conditions'][] = 'AU platform “@” error code';
	}

	$return['condition_match'] = count($return['conditions']);


	if ($return['condition_match'] >= 1) {
		$return['outcome']  = 'ABNORMAL';
	} else {
		$return['outcome'] = 'NORMAL';
	}

	$return['resulted'] = true;

	return $return;
}

function dec2hex62($number)
{
	$hexvalues = array(
		'0',
		'1',
		'2',
		'3',
		'4',
		'5',
		'6',
		'7',
		'8',
		'9',
		'A',
		'B',
		'C',
		'D',
		'E',
		'F',
		'G',
		'H',
		'I',
		'J',
		'K',
		'L',
		'M',
		'N',
		'O',
		'P',
		'Q',
		'R',
		'S',
		'T',
		'U',
		'V',
		'W',
		'X',
		'Y',
		'Z',
		'a',
		'b',
		'c',
		'd',
		'e',
		'f',
		'g',
		'h',
		'i',
		'j',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'q',
		'r',
		's',
		't',
		'u',
		'v',
		'w',
		'x',
		'y',
		'z'
	);

	$hexval = '';
	if (function_exists('bcmod')) {
		while ($number != '0') {
			$hexval = $hexvalues[bcmod($number, '62')] . $hexval;
			$number = bcdiv($number, '62', 0);
		}
	} else {
		$hexval = base64_encode($number);
	}
	return $hexval;
}

function hex2dec62($number)
{
	$decvalues = array(
		'0' => '0',
		'1' => '1',
		'2' => '2',
		'3' => '3',
		'4' => '4',
		'5' => '5',
		'6' => '6',
		'7' => '7',
		'8' => '8',
		'9' => '9',
		'A' => '10',
		'B' => '11',
		'C' => '12',
		'D' => '13',
		'E' => '14',
		'F' => '15',
		'G' => '16',
		'H' => '17',
		'I' => '18',
		'J' => '19',
		'K' => '20',
		'L' => '21',
		'M' => '22',
		'N' => '23',
		'O' => '24',
		'P' => '25',
		'Q' => '26',
		'R' => '27',
		'S' => '28',
		'T' => '29',
		'U' => '30',
		'V' => '31',
		'W' => '32',
		'X' => '33',
		'Y' => '34',
		'Z' => '35',
		'a' => '36',
		'b' => '37',
		'c' => '38',
		'd' => '39',
		'e' => '40',
		'f' => '41',
		'g' => '42',
		'h' => '43',
		'i' => '44',
		'j' => '45',
		'k' => '46',
		'l' => '47',
		'm' => '48',
		'n' => '49',
		'o' => '50',
		'p' => '51',
		'q' => '52',
		'r' => '53',
		's' => '54',
		't' => '55',
		'u' => '56',
		'v' => '57',
		'w' => '58',
		'x' => '59',
		'y' => '60',
		'z' => '61'
	);
	if (function_exists('bcmod')) {
		$decval = '0';
		$number = strrev($number);
		for ($i = 0; $i < strlen($number); $i++) {
			$decval = bcadd(bcmul(bcpow('62', $i, 0), $decvalues[$number[$i]]), $decval);
		}
	} else {
		$decval = base64_decode($number);
	}
	return $decval;
}

function trpdd_patient_encode($patient_id)
{
	if (empty($patient_id)) {
		return '';
	}
	$patient_id = intval($patient_id) + 10000000;
	$hex = strtoupper(trpdd_dec2hex36($patient_id));
	$maxlen = 6;

	//$hex = str_repeat('0',$maxlen-strlen($hex)).$hex;

	return $hex;
}

function trpdd_patient_decode($code)
{
	$code = strtolower($code);
	$patient_id = trpdd_hex2dec36($code) - 10000000;
	return intval($patient_id);
}

function trpdd_get_tests_reflex_by_screening($mid, $cli_ID, $created_date, $medications = '', $only_screening = false)
{
	global $wpdb;

	$dont_reflex = trpdd_get_batch_meta($mid, 'tox', '_dont_reflex', false, false);

	if ($dont_reflex) {
		return array();
	}

	if (!is_array($medications)) {
		$medications = str_replace('|', ',', $medications);
		$medications = explode(',', $medications);
	}
	$medications = array_map('trim', $medications);
	$medications = array_map('strtoupper', $medications);

	$screenings = tr_pdd_get_result_validity(1, $created_date, $mid);
	$reflex_tests = array();
	foreach ($screenings as $arr) {
		if ($arr['checked']) {
			$in_medication 	= false;
			$is_pos 		= false;

			if (is_array($medications) && count($medications) > 0) {
				$related_medications = array();
				if (!empty($arr['u_related_medications'])) {
					$related_medications = explode(',', $arr['u_related_medications']);
				}
				$related_medications[] = $arr['u_name'];
				if (!empty($arr['u_abreviation'])) {
					$related_medications[] = $arr['u_abreviation'];
				}
				$related_medications = array_map('trim', $related_medications);
				$related_medications = array_map('strtoupper', $related_medications);
				$related_medications = array_filter($related_medications);

				if (count(array_intersect($related_medications, $medications)) > 0) {
					$in_medication = true;
				}
			}

			$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);
			if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
				$is_pos = true;
			}

			$can_reflex = $is_pos;

			if (isset($arr['u_reflex_pos_not_medication']) && $arr['u_reflex_pos_not_medication'] == '1' && $is_pos && $in_medication) {
				//dont reflex if pos but in medication
				$can_reflex = false;
			}

			if (isset($arr['u_reflex_neg_and_medication']) && $arr['u_reflex_neg_and_medication'] == '1' && !$is_pos && $in_medication) {
				//reflex if neg but in medication
				$can_reflex = true;
			}


			if ($can_reflex && !empty($arr['u_reflex_tests'])) {
				$clinics = !empty($arr['u_reflex_clinics']) ? explode(',', $arr['u_reflex_clinics']) : array();

				if (is_array($clinics) && count($clinics) > 0 && !in_array($cli_ID, $clinics)) {
					continue;
				}

				$tests = explode(',', $arr['u_reflex_tests']);
				//check tests active
				$tests_str = implode("','", $tests);
				$newtests = $wpdb->get_col("select te_name from ci_test_panels 
				where te_type = 'tox' and te_in_req = 1
				and te_name in ('{$tests_str}')
				");

				$newtests = array_map('strtoupper', $newtests);

				$reflex_tests = array_merge($reflex_tests, $newtests);
			}
		}
	}

	if (count($reflex_tests) > 0) {
		$reflex_tests = array_unique($reflex_tests);
	}
	return $reflex_tests;
}

function trpdd_filter_tests_reflex_by_screening($test_requested, $report)
{
	global $wpdb;
	$reflex_tests = array();
	$has_reflex = null;
	if (!empty($report)) {
		if (is_object($report)) {
			$report = (array)$report;
		}

		if (isset($report['estatus']) && in_array($report['estatus'], array('M', 'C'))) {
			//dont run reflex if report is amended or cancelled
			return array();
		}

		$reflex_tests = trpdd_get_tests_reflex_by_screening($report['mid'], $report['cli_ID'], $report['batch_created'], $report['current_medication']);
		if (isset($_GET['check_reflex'])) {
			var_dump($reflex_tests);
		}

		if (count($reflex_tests) > 0) {
			//check for test ordered

			if ($report['only_screening']) {
				$test_requested = array();
			}


			$adding_reflex_tests = trpdd_get_batch_meta($report['mid'], 'tox', '_reflex_tests', false);
			$adding_reflex_tests = !empty($adding_reflex_tests) ? explode(',', $adding_reflex_tests) : array();

			$has_miss_test = count($adding_reflex_tests) != count($reflex_tests);


			foreach ($reflex_tests as $test) {
				if (!in_array(strtoupper($test), $test_requested) && !empty($test)) {
					$test_requested[] = $test;
					$adding_reflex_tests[] = $test;
					$has_miss_test = true;
				}
			}

			if ($has_miss_test) {

				$reflex_tests = array_unique($reflex_tests);
				trpdd_update_batch_meta($report['mid'], 'tox', '_reflex_tests', implode(',', $reflex_tests));

				$panel_tests_requested = implode(',', $test_requested);

				$wpdb->update('ci_batches', array('panel_tests_requested' => $panel_tests_requested), array('mid' => $report['mid']));
			}

			$reflex_tests_str = implode("','", $reflex_tests);
			$has_reflex = $wpdb->get_var("select count(*) from ci_results 
	                                where drug in ('{$reflex_tests_str}')
	                                and report_id = {$report['id']} ");
			$has_reflex = $has_reflex > 0 ? 1 : 0;
		} else {
			trpdd_delete_batch_meta($report['mid'], 'tox', '_reflex_tests');
		}
	}



	return array(
		'test_requested' => $test_requested,
		'reflex_tests' 	=> $reflex_tests,
		'has_reflex' 	=> $has_reflex
	);
}

function trpdd_format_address($address, $city, $state, $zip, $fieldbr = true)
{
	if (!empty($city)) {
		$address .= ($fieldbr == 'city' ? '<br>' : ', ') . $city;
	}
	$state_zip = trim($state . ' ' . $zip);
	if (!empty($state_zip)) {
		$address .= ', ' . $state_zip;
	}

	return $address;
}

function trpdd_stringval($string)
{
	return trim($string) . '';
}


function trpdd_get_string_exist($row, $keys)
{
	$value = '';
	foreach ($keys as $key) {
		if (isset($row[$key])) {
			$value = trim($row[$key]) . '';
			if (!empty($value)) {
				break;
			}
		}
	}
	return $value;
}


function countRecursive($array)
{
	if (!is_array($array)) {
		return 1;
	}

	$count = 0;
	foreach ($array as $sub_array) {
		$count += countRecursive($sub_array);
	}

	return $count;
}

function trpdd_parse_address($address)
{
	$full_address 	= trpdd_stringval($address);
	$address_arr 	= explode(',', $full_address, 4);
	$full_address = str_replace("\r", "\n", $full_address);

	if (count($address_arr) == 2 && strpos($full_address, "\n") > 0) {
		$full_address = str_replace("\n", ',', $full_address);
		$address_arr 	= explode(',', $full_address, 4);
	}
	$address_arr 	= array_map('trim', $address_arr);
	$address 		= $address_arr[0];
	$city 			= isset($address_arr[1]) ? $address_arr[1] : '';
	$state 			= '';
	$zip 			= '';

	if (count($address_arr) == 4) {
		$address .= ', ' . $address_arr[1];
		$city 			= $address_arr[2];
		list($state, $zip) = explode(' ', $address_arr[3]);
	} else if (count($address_arr) == 3) {
		list($state, $zip) = explode(' ', $address_arr[2]);
	} else if (count($address_arr) == 2) {
		list($city, $state, $zip) = explode(' ', $address_arr[1]);
	}
	return array($address, $city, $state, $zip);
}

function trpdd_update_patient($pa, $patient_id)
{
	global $wpdb;
	$changes = array();
	$oldpatient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id} ", ARRAY_A);
	$has_change = false;
	foreach ($pa as $k => $vl) {
		$oldvl = $oldpatient[$k];
		if ($oldvl != $vl && !in_array($k, array('pa_clinic_ID', 'pa_modified', 'pa_notes', 'pa_import_ID', 'pa_patient_type'))) {
			$has_change = true;
			$changes[$k] = $oldvl . ' (to) ' . $vl;
			break;
		}
	}

	if ($has_change) {
		$oldpatient['pa_modified'] = date_i18n('Y-m-d H:i:s');
		unset($oldpatient['pa_msh_mrn']);
		unset($oldpatient['pa_african_american']);
		unset($oldpatient['pa_sport']);
		$wpdb->insert('ci_patients_history', $oldpatient);
	}

	$pa['pa_modified'] = date_i18n('Y-m-d H:i:s');
	$wpdb->update('ci_patients', $pa, array('pa_ID' => $patient_id));

	return $changes;
}

function trpdd_update_insurance($ins, $ins_id)
{
	global $wpdb;

	$oldins = $wpdb->get_row("select * from ci_insurances where ins_ID = {$ins_id} ", ARRAY_A);
	$has_change = false;
	foreach ($ins as $k => $vl) {
		$oldvl = $oldins[$k];
		if ($oldvl != $vl) {
			$has_change = true;
			break;
		}
	}

	if ($has_change) {
		$oldins['ins_modified'] = date_i18n('Y-m-d H:i:s');
		$wpdb->insert('ci_insurances_history', $oldins);
	}

	$ins['ins_modified'] = date_i18n('Y-m-d H:i:s');
	$wpdb->update('ci_insurances', $ins, array('ins_ID' => $ins_id));
}


function trpdd_pick_hex($color1, $color2, $weight)
{
	if (is_string($color1)) {
		$color1 = sscanf($color1, "#%02x%02x%02x");
	}
	if (is_string($color2)) {
		$color2 = sscanf($color2, "#%02x%02x%02x");
	}

	$p = $weight;
	$w = $p * 2 - 1;
	$w1 = ($w / 1 + 1) / 2;
	$w2 = 1 - $w1;
	$rgb = array(
		round($color1[0] * $w1 + $color2[0] * $w2),
		round($color1[1] * $w1 + $color2[1] * $w2),
		round($color1[2] * $w1 + $color2[2] * $w2)
	);

	return sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
}


function trpdd_delete_batch($id, $btype)
{
	global $wpdb;
	$table = tr_pdd_get_table_by_btype($btype);
	$bat = $wpdb->get_row("select ba.*,lab.lab_email 
	                from {$table} ba 
	                left JOIN ci_labs lab On lab.lab_ID = ba.lab_id 
	                where ba.mid = {$id}");
	if (!$bat) {
		return true;
	}

	if ($bat->is_saved > 0) {
		$wpdb->query("delete from {$table} where mid = {$id}");
		$wpdb->query("delete from {$table}_meta where mid = {$id}");
		trpdd_log_form('delete_assen', array('bat' => (array)$bat, 'btype' => $btype));
	} else {
		$wpdb->update($table, array('is_saved' => 2), array('mid' => $id));
		trpdd_log_user(get_current_user_id(), 'delete_req', '', $id, $btype,  '', '', $bat->assen);
	}


	//need send mail
	$nl = '<br>';

	$user = wp_get_current_user();

	$subject = "Deleted Batch(" . $id . '") Accession: ' . $bat->assen;
	$message = 'Patient Name: ' . $bat->patient_name . $nl;
	$message .= 'Accession: ' . $bat->assen   . $nl;
	$message .= 'Position: ' . $bat->position . $nl;
	$message .= 'Lab: ' . $bat->lab . $nl;
	$message .= 'Clinic: ' . $bat->requesting_practice . $nl;
	$message .= 'Doctor: ' . $bat->requesting_physician . $nl;
	$message .= 'Deleted By: ' . $user->user_email . $nl;

	$blogname = $bat->lab;
	if (empty($blogname)) {
		$blogname = get_option('blogname');
	}
	$blogname = wp_specialchars_decode($blogname, ENT_QUOTES);
	$to = $bat->lab_email;
	if (!is_email($to)) {
		$to = get_bloginfo('admin_email');
	}

	$headers = 'From: ' . $blogname . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
	$headers .= 'Reply-To: ' . get_bloginfo('admin_email') . "\r\n";

	$headers .= "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

	wp_mail($to, $subject, $message, $headers);

	return $bat;
}

function trpdd_format_phone_number($number)
{
	$number = str_replace(array('-', ' ', '(', ')', '+1'), '', $number);

	if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $number,  $matches)) {
		$number = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
	}
	return $number;
}

function trpdd_pos_to_well($pos, $pl_ID = 0, $pl_combine_ID = 0)
{
	global $pl_combine_list, $wpdb;

	if (empty($pos)) return '';
	$rack_ABC = tr_get_option('ci_pdf_data', 'rack_ABC');

	if ($pl_combine_ID > 0) {
		if (!isset($pl_combine_list[$pl_combine_ID])) {
			$pl_combine_list[$pl_combine_ID] = $wpdb->get_row("select * from ci_plates_combine where ID = {$pl_combine_ID}");
		}
		$cp = $pl_combine_list[$pl_combine_ID];

		$welld = '';

		if ($cp->combine96) {
			$r = floor($pos / 12) + 1;
			$char = chr($r + 64);
			$num = $pos % 12;
		} else {
			$r = floor($pos / 24) + 1;
			$char = chr($r + 64);
			$num = $pos % 24;
		}
		$welld = $char . $num;
	} else if ($rack_ABC) {
		$num12 = floor(($pos - 1) / 8) + 1;
		$welld = chr(floor(($pos - 1) % 8) + 65) . $num12;
	} else {
		$num12 = ($pos - 1) % 12 + 1;
		$welld = chr(floor(($pos - 1) / 12) + 65) . $num12;
	}
	return $welld;
}

function trpdd_well_to_pos($well)
{
	if (empty($well)) return '';

	$rack_ABC = tr_get_option('ci_pdf_data', 'rack_ABC');
	$char = strtoupper(substr($well, 0, 1));
	$charnum = ord($char);
	$num12 = intval(substr($well, 1));
	if ($rack_ABC) {
		$pos = ($num12 - 1) * 8 + ($charnum - 65) + 1;
	} else {
		$pos = ($charnum - 65) * 12 + ($num12 - 1);
	}
	return $pos;
}


function trpdd_getContrastColor($hexcolor)
{
	$r = hexdec(substr($hexcolor, 1, 2));
	$g = hexdec(substr($hexcolor, 3, 2));
	$b = hexdec(substr($hexcolor, 5, 2));
	$yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
	return ($yiq >= 128) ? 'black' : 'white';
}

function trpdd_get_qr_code($url, $size = 100, $errorCorrectionLevel = 'H', $matrixPointSize = 15, $margin = 1)
{
	include_once(TRCPDD_PATH . 'inc/phpqrcode/qrlib.php');
	if (!defined('QR_CACHE_DIR')) {
		define('QR_CACHE_DIR', WP_CONTENT_DIR . '/cache/qrcache/');
	}

	if (!file_exists(QR_CACHE_DIR)) {
		wp_mkdir_p(QR_CACHE_DIR);
	}
	$filename = tempnam('/tmp', 'qrcode');

	QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, $margin);

	$QR = imagecreatefrompng($filename);
	unlink($filename);

	$QR = imagescale($QR, $size, $size);

	$logo = imagecreatefromjpeg(TRCPDD_PATH . 'images/logo_qr.jpg');

	$logo_width = imagesx($logo);
	$logo_height = imagesy($logo);

	$QR_width = imagesx($QR);
	$QR_height = imagesy($QR);

	// Scale logo to fit in the QR Code
	$logo_qr_width = ($QR_width - 10) / 3;
	$scale = $logo_width / $logo_qr_width;
	$logo_qr_height = $logo_height / $scale;

	imagecopyresampled($QR, $logo, $QR_width / 3, $QR_height / 3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

	return $QR;
}

function trpdd_add_cron_after_approve($btype = 'pcr', $need_fax = false)
{
	$nextcron = wp_next_scheduled('ci_cron_clean_results');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 60, 'ci_cron_clean_results', array(0));
	}

	if ($need_fax) {
		$next_cron = wp_next_scheduled('ci_check_upload_apis');
		if ($next_cron < time() || $next_cron > time() + 300) {
			wp_schedule_single_event(time() + 70, 'ci_check_upload_apis', array(1, 'single', $btype));
		}
	}
	//add cron create pdf
	$next_cron = wp_next_scheduled('cron_create_result_pdf');
	if ($next_cron < time() || $next_cron > time() + 400) {
		wp_schedule_single_event(time() + 120, 'cron_create_result_pdf', array($btype));
	}

	$next_cron = wp_next_scheduled('ci_cron_sync_wsdl_client_result');
	if ($next_cron < time() || $next_cron > time() + 500) {
		wp_schedule_single_event(time() + 200, 'ci_cron_sync_wsdl_client_result', array($btype));
	}

	//add sms notify cron
	wp_clear_scheduled_hook('cron_sms_notify');
	wp_schedule_single_event(time() + (20 * 60), 'cron_sms_notify'); //20mins

}

function trpdd_get_main_options($key, $default = '')
{
	if (function_exists('get_blog_option')) {
		$mainid = defined('BLOG_ID_CURRENT_SITE') && BLOG_ID_CURRENT_SITE > 0 ? BLOG_ID_CURRENT_SITE : 1;
		$settings = get_blog_option($mainid, 'ci_pdf_data', array());
	} else {
		$settings = get_option('ci_pdf_data', array());
	}
	return isset($settings[$key]) ? $settings[$key] : $default;
}

function trpdd_get_name_by_key($key)
{
	if (substr($key, 0, 3) == 'pa_') {
		$key = ucfirst(substr($key, 3));
	}
	return $key;
}

function trpdd_dec2hex36($number)
{
	$hexvalues = array(
		'0',
		'1',
		'2',
		'3',
		'4',
		'5',
		'6',
		'7',
		'8',
		'9',
		'a',
		'b',
		'c',
		'd',
		'e',
		'f',
		'g',
		'h',
		'i',
		'j',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'q',
		'r',
		's',
		't',
		'u',
		'v',
		'w',
		'x',
		'y',
		'z'
	);

	$hexval = '';
	if (function_exists('bcmod')) {
		while ($number != '0') {
			$hexval = $hexvalues[bcmod($number, '36')] . $hexval;
			$number = bcdiv($number, '36', 0);
		}
	} else {
		$hexval = base64_encode($number);
	}
	return $hexval;
}

function trpdd_hex2dec36($number)
{
	$decvalues = array(
		'0' => '0',
		'1' => '1',
		'2' => '2',
		'3' => '3',
		'4' => '4',
		'5' => '5',
		'6' => '6',
		'7' => '7',
		'8' => '8',
		'9' => '9',
		'a' => '10',
		'b' => '11',
		'c' => '12',
		'd' => '13',
		'e' => '14',
		'f' => '15',
		'g' => '16',
		'h' => '17',
		'i' => '18',
		'j' => '19',
		'k' => '20',
		'l' => '21',
		'm' => '22',
		'n' => '23',
		'o' => '24',
		'p' => '25',
		'q' => '26',
		'r' => '27',
		's' => '28',
		't' => '29',
		'u' => '30',
		'v' => '31',
		'w' => '32',
		'x' => '33',
		'y' => '34',
		'z' => '35',

	);
	if (function_exists('bcmod')) {
		$decval = '0';
		$number = strrev($number);
		for ($i = 0; $i < strlen($number); $i++) {
			$decval = bcadd(bcmul(bcpow('36', $i, 0), $decvalues[$number[$i]]), $decval);
		}
	} else {
		$decval = base64_decode($number);
	}
	return $decval;
}

function trpdd_encode_id_btype($id, $btype)
{
	$keyid      = trpdd_dec2hex36($id);
	$lengthbtype = strlen($btype);
	$lengthcode = 34 - strlen($keyid . $btype);
	$code       = md5($keyid . $id . $btype);
	$code       = substr($code, 0, $lengthcode);

	return $code . $keyid . $btype . trpdd_dec2hex36($lengthcode) . trpdd_dec2hex36($lengthbtype);
}

function trpdd_decode_id_btype($keystr)
{
	$arr            = array('id' => 0, 'btype' => '');
	$lengthbtype    = trpdd_hex2dec36(substr($keystr, -1, 1));
	$lengthcode     = trpdd_hex2dec36(substr($keystr, -2, 1));
	$lengthkey      = strlen($keystr) - $lengthcode - $lengthbtype - 2;

	$code           = substr($keystr, 0, $lengthcode);
	$keyid          = substr($keystr, $lengthcode, $lengthkey);
	$btype          = substr($keystr, $lengthcode + $lengthkey, $lengthbtype);
	$id             = trpdd_hex2dec36($keyid);

	$codenew        = md5($keyid . $id . $btype);
	$codenew        = substr($codenew, 0, $lengthcode);

	if ($codenew == $code) {
		$arr['btype'] = $btype;
		$arr['id'] = $id;
	}
	return $arr;
}


function trpdd_get_patient_site_url()
{
	global $patient_site_url, $wpdb;
	if (empty($patient_site_url)) {
		$patient_site_url = get_option('ci_patient_site_url', '');
		if (empty($patient_site_url)) {
			$patient_site_url = $wpdb->get_var("select domain from {$wpdb->blogs} where blog_id>1 order by blog_id asc limit 1");
			if (!empty($patient_site_url)) {
				$patient_site_url = 'https://' . $patient_site_url;
			} else {
				$patient_site_url = get_bloginfo('url');
			}
		}
	}
	return $patient_site_url;
}

function trpdd_qr_by_report($id, $btype, $width = 300, $border = 0)
{
	$key = trpdd_encode_id_btype($id, $btype);
	$url = trpdd_get_patient_site_url() . '/qrsc/' . $key;
	$qr = trpdd_get_qr_code($url, $width, 'H', 15, $border);
	return $qr;
}


function trpdd_url_qr_by_report($id, $btype, $localpath = false, $width = 300, $border = 2)
{
	$key = trpdd_encode_id_btype($id, $btype);
	if ($localpath) {
		$dir = WP_CONTENT_DIR . '/qrs';
		if (!file_exists($dir)) {
			wp_mkdir_p($dir);
		}
		$filename = 'p_' . $id . '_' . $btype . '.png';
		$filepath = $dir . '/' . $filename;
		$QR = trpdd_qr_by_report($id, $btype, $width, $border);
		if (imagepng($QR, $filepath)) {
			return $filepath;
		}
	} else {
		return trpdd_get_patient_site_url() . '/qr/' . $key;
	}
}

function trpdd_log_query_error()
{
	global $wpdb;
	if (!empty($wpdb->last_error)) {
		if (stripos($wpdb->last_error, 'Unknown') !== false) {
			ob_start();
			include(TRCPDD_PATH . 'inc/install.php');
			ob_clean();
		}
	}
}


function trpdd_get_new_footer_text($report, $btype)
{
	global $wpdb, $pdf_header_text;

	$footer_text = '';
	$sql_footer = "select * from ci_pdf_footer 
		            where btype='{$btype}'
		            and group_ID = {$report['group_id']} 
		            and lab_ID = {$report['lab_ID']}
		            order by start_date desc, ID desc";

	$footers = $wpdb->get_results($sql_footer);
	if (count($footers) == 0) {
		if ($report['group_id'] > 0) {
			$report['group_id'] = -1;
			$footer_text = trpdd_get_new_footer_text($report, $btype);
		}
	} else {
		$report_time = strtotime($report['created_date']);

		if (isset($_GET['debug_footer'])) {
			echo 'reportdate: ' . $report['created_date'];
			echo '$report_time: ' . $report_time . '<br>';
		}

		foreach ($footers as $footer) {
			if ($footer->start_date == '0000-00-00') {
				$footer->start_date = '';
			}
			if ($footer->end_date == '0000-00-00') {
				$footer->end_date = '';
			}
			$start_time = !empty($footer->start_date) ? strtotime($footer->start_date) : 0;
			$end_time = !empty($footer->end_date) ? strtotime($footer->end_date) : 0;

			if ($end_time == 0) {
				if ($start_time == 0 || ($start_time > 0 && $start_time <= $report_time)) {
					if (isset($_GET['debug_footer'])) {
						echo 'by enddate = 0';
						var_dump($footer);
					}
					$pdf_header_text = $footer->header;
					return $footer->text;
				}
			} else {
				if ($report_time < $end_time && $report_time >= $start_time) {
					if (isset($_GET['debug_footer'])) {
						echo 'by enddate >0. $end_time=' . $end_time . '(' . $footer->end_date . ')';
						echo ' & $start_time=' . $start_time . '(' . $footer->start_date . ')<br>';
						var_dump($footer);
					}
					$pdf_header_text = $footer->header;
					return $footer->text;
				}
			}
		}
	}
	return $footer_text;
}

function trpdd_get_footer_text($report, $btype)
{

	global $pdf_header_text;
	$pdf_header_text = '';
	$report = CI_Base_Lab::apply_lab_field_history($report);
	if (empty($report['created_date']) || $report['created_date'] == '0000-00-00' || (strtotime($report['created_date']) < strtotime($report['doc']))) {
		$report['created_date'] = date('Y-m-d', strtotime($report['doc']));
	}
	if (!empty($report['approve_date']) && $report['approve_date'] !== '0000-00-00') {
		$report['created_date'] = $report['approve_date'];
	}

	if (empty($report['group_id'])) {
		$report['group_id'] = -1;
	}

	$footer_text = stripslashes($report['rl_lab_additional']);
	if (isset($report['no_ref_lab_footer']) && $report['no_ref_lab_footer'] == 1) {
		$footer_text = '';
	}

	if (empty($footer_text)) {
		//new footer
		$footer_text = trpdd_get_new_footer_text($report, $btype);
		if (isset($_GET['debug_footer'])) {
			$footer_text = "footer text => " . $footer_text;
			var_dump($footer_text);
		}
	}

	if (empty($footer_text)) {

		$footer_fname = '';

		if (isset($report['footer_key']) && !empty($report['footer_key'])) {
			$footer_fname = $report['footer_key'];
		}

		$footer_text = isset($report[$footer_fname]) ? stripslashes($report[$footer_fname]) : '';
	}

	$lab_address = $report['lab_address'] . ' ' . $report['lab_city'];
	$lab_address .= ', ' . $report['lab_state'] . ' ' . $report['lab_zip'];
	$report['signature'] = '';

	if (!empty($report['lab_director_signature'])) {
		$sign_dir = tr_get_signature_dir();
		$director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
		$report['signature'] = '<img src="' . $director_signature_url . '" style="height:30px">';
	}


	$searchs = array(
		'[lab]',
		'[lab_name]',
		'{original_footer}',
		'[assen]',
		'[patient_name]',
		'[lab_address]',
		'[lab_director]',
		'[lab_phone]',
		'[lab_clia]',
		'[lab_cap]',
		'[lab_cola]',
		'[cap]',
		'[cola]',
		'[signature]'
	);

	$replaces = array(
		$report['lab_dba_init'],
		$report['lab_name'],
		$report['lab_additional'],
		$report['assen'],
		$report['patient_name'],
		$lab_address,
		$report['lab_director'],
		$report['lab_phone'],
		$report['lab_clia'],
		$report['lab_cap'],
		$report['lab_cola'],
		$report['lab_cap'],
		$report['lab_cola'],
		$report['signature'] ?? ''
	);

	$footer_text = str_replace($searchs, $replaces, $footer_text);


	if (!isset($report['has_covid']) || !$report['has_covid']) {
		//remove tag <only_covid>
		$footer_text = preg_replace("#\<only\_covid\>.*\<\/only\_covid\>#is", '', $footer_text);
	} else {
		$footer_text = preg_replace("#\<not\_covid\>.*\<\/not\_covid\>#is", '', $footer_text);
	}

	if (stripos($footer_text, '/wp-content/') > 0) {
		$fsearch =  get_bloginfo('url') . '/wp-content';

		$footer_text = str_ireplace($fsearch, WP_CONTENT_DIR, $footer_text);
	}
	$footer_text = stripslashes($footer_text);

	$has_passport = isset($_REQUEST['hqr']);
	if (!$has_passport) {
		$report['pa_passport2'] = str_replace(array('-', '.', ' '), '', $report['pa_passport']);
		$has_passport = strlen($report['pa_passport2']) >= 8;
		if (str_replace('0', '', $report['pa_passport2']) === '') {
			$has_passport = false;
		}
	}

	if ($has_passport) {
		$newqrurl = trpdd_url_qr_by_report($report['id'], 'pcr', true);

		if (!empty($newqrurl)) {
			if (strpos($footer_text, 'class="qr"') > 0) {
				$footer_text = preg_replace("/<img([^>]*)class=\"qr\"([^>]*)src=\"([^\"]+)\"/", '<img $1 $2 src="' . $newqrurl . '"', $footer_text);
			} else {
				$footer_text = str_replace("[qr]", '<img src="' . $newqrurl . '" style="width:80px;height:80px"/>', $footer_text);
			}
		}
	}

	if (isset($_GET['debugqr'])) {
		echo 'has class qr:';
		var_dump(strpos($footer_text, 'class=\"qr\"'));
		var_dump($has_passport);
		echo "<br>";
		var_dump($newqrurl);
		echo "<br>";
	}


	$footer_text = apply_filters('the_content', $footer_text);

	$footer_text = str_replace('<p></p>', '', $footer_text);
	$footer_text = str_replace('[qr]', '', $footer_text);

	return $footer_text;
}

function trpdd_get_pdf_note($report, $btype, $reflab = false)
{
	global $wpdb, $pdf_file_adding;

	if (is_object($report)) {
		$report = (array)$report;
	}

	$lab_ID = isset($report['lab_id']) ? $report['lab_id'] : $report['lab_ID'];
	if (empty($lab_ID)) {
		$lab_ID = CI_Base_Lab::get_current_lab_id();
	}
	if ($reflab) {
		$lab_ID = $report['ref_lab_ID'];
	}
	if (isset($_GET['debug_note'])) {
		echo 'lab_ID: ' . $lab_ID . '<br>';
	}
	if (empty($lab_ID)) {
		return '';
	}
	if (empty($report['group_id'])) {
		$report['group_id'] = -1;
	}

	$pdf_file_adding = '';
	$note_text = '';
	$where_notes = " btype='{$btype}'
		            and (group_ID = {$report['group_id']} or group_ID = -1 or group_ID = 0) ";
	if ($reflab) {
		$where_notes .= " and ( lab_ID = {$lab_ID} ) ";
	} else {
		$where_notes .= " and (lab_ID = 0 or lab_ID = {$lab_ID} ) ";
	}

	$sql_notes = "select * from ci_pdf_notes 
		            where  {$where_notes}
		            order by is_abx asc,group_ID asc, lab_ID desc, start_date desc";
	if (isset($_GET['debug_note'])) {
		echo $sql_notes;
		echo '<br><br';
	}
	$notes = $wpdb->get_results($sql_notes);

	if (count($notes) == 0) {
		if ($report['group_id'] > 0) {
			$report['group_id'] = -1;
			$note_text = trpdd_get_pdf_note($report, $btype);
		}
	} else {
		$report_time = !empty($report['created_date']) ? strtotime($report['created_date']) : strtotime(date_i18n('Y-m-d H:i:s'));

		foreach ($notes as $note) {
			if (($note->group_ID == -1 || $note->group_ID == 0) && !empty($note->exclude_panels)) {
				$exclude = array_map('intval', explode(',', $note->exclude_panels));
				if (!empty($exclude) && in_array((int)$report['group_id'], $exclude)) {
					continue;
				}
			}

			//check is_abx
			if ($note->is_abx > 0 && (!isset($report['add_style']) || $report['add_style'] != 3)) {
				continue;
			}
			if ($note->start_date == '0000-00-00') {
				$note->start_date = '';
			}
			if ($note->end_date == '0000-00-00') {
				$note->end_date = '';
			}
			$start_time = !empty($note->start_date) ? strtotime($note->start_date) : 0;
			$end_time = !empty($note->end_date) ? strtotime($note->end_date) : 0;

			if (isset($_GET['debug_note'])) {
				var_dump($note);
				echo '<br>';
				echo '$start_time';
				var_dump($start_time);
				echo '<br>';
				echo '$end_time';
				var_dump($end_time);
				echo '<br>';
			}

			if ($end_time == 0) {
				if ($start_time == 0 || ($start_time > 0 && $start_time <= $report_time)) {
					$pdf_file_adding = $note->pdf_file;
					$note_text .= (!empty($note_text) ? '<br class="sepnotes" style="margin-bottom:5px">' : '') . '<div class="note-text">' . $note->text . '</div>';
				}
			} else {
				if ($report_time < $end_time && $report_time >= $start_time) {
					$pdf_file_adding = $note->pdf_file;
					$note_text .= (!empty($note_text) ? '<br class="sepnotes" style="margin-bottom:5px">' : '') . '<div class="note-text">' . $note->text . '</div>';
				}
			}
		}
	}

	//replace notes
	$searchs = array(
		'[lab]',
		'[lab_name]',
		'[assen]',
		'[patient_name]',
		'[director_name]',
	);
	$replaces = array(
		$report['lab_dba_init'],
		$report['lab_name'],
		$report['assen'],
		$report['patient_name'],
		'<span class="director_name">' . $report['lab_director'] . '</span>',
	);
	$note_text = str_replace($searchs, $replaces, $note_text);
	if (isset($_GET['debug_note'])) {
		var_dump($wpdb->last_error);
		var_dump($note_text);
	}
	return $note_text;
}

function trpdd_get_req_pdf($mid, $btype)
{
	$outfile = '';
	try {

		ob_start();
		$_GET['id']         = $_REQUEST['id'] = $mid;
		$_REQUEST['act']    = $_GET['act'] = $act = 'print';
		$hide_tabs          = true;
		$_REQUEST['trajax'] = true;
		$bat                = array();
		$req_form_file = tr_pdd_get_req_file($btype);
		require($req_form_file);
		$content = ob_get_clean();

		if (!$bat || empty($bat['assen'])) {
			return false;
		}

		$dir = WP_CONTENT_DIR . '/cache/reqs/';
		if (!file_exists($dir)) {
			wp_mkdir_p($dir);
		}
		$outfile = $dir . $bat['assen'] . '.pdf';

		if (isset($_GET['nocache']) || isset($_GET['html'])) {
			//nothing
		} else if (
			file_exists($outfile)
			&& filemtime($outfile) > strtotime($bat['batch_modified'])
			&& filemtime($outfile) > filemtime($req_form_file)
			&& filemtime($outfile) < time() - 60
		) {
			return $outfile;
		}


		if ($bat && !empty($bat['original_req'])) {
			$dir = tr_get_result_upload_dir();
			$pdf_file = $dir['path'] . $bat['original_req'];

			if (file_exists($pdf_file)) {
				return $pdf_file;
			}
		}


		$content = trpdd_pdf_html($content, 'Order ' . $bat['assen']);

		$content = preg_replace("#\<option[^\>]*selected[^\>]*\>([^\<]*)\<\/option\>#is", '$1', $content);
		$content = preg_replace("#\<option([^\>]*)\>([^\<]*)\<\/option\>#is", '', $content);
		$content = preg_replace("#\<input[^\>]*type=\"hidden\"[^\>]*>#is", '', $content);
		$content = preg_replace("#\<select[^\>]*\>([^\<]*)\<\/select\>#is", '<span class="text">$1</span>', $content);

		$content = str_ireplace(array('<span class="text">Select State</span>', '<span class="text">Select</span>'), '<span class="text"> </span>', $content);
		$content = str_ireplace(array('type="email"', 'type="search"'), 'type="text"', $content);
		$content = str_replace('<textarea', '<div class="textarea text" ', $content);
		$content = str_replace('</textarea>', '</div>', $content);

		if (strpos($content, 'print.min.css') === false && strpos($content, 'print.css') === false) {
			$print_css = '<link href="' . TRCPDD_URL . 'css/print.css?' . time() . '" media="all" type="text/css" rel=\'stylesheet\'/>';
			if (stripos($content, '</head>') !== false) {
				$content = str_ireplace('</head>', $print_css . '</head>', $content);
			} else {
				$content .= $print_css;
			}
		}
		if (isset($_GET['html'])) {
			var_dump($content);
			exit;
		}

		trpdd_build_pdf($content, array('filepath' => $outfile));
	} catch (Error $e) {
		$outfile  = '';
	}
	return $outfile;
}

function trpdd_add_auto_fax($batch_arr, $btype)
{
	global $wpdb;

	$type_result = 'rs_efax' . $btype;

	$table_report = tr_pdd_get_table_report_by_btype($btype);

	$fax_number = '';
	$mid = $batch_arr['mid'];

	if (isset($batch_arr['fax'])) {
		$fax_number = $batch_arr['fax'];
	} else {
		$fax_number = $wpdb->get_var("select `cli_om-fax` from ci_clinic 
	                where cID = '{$batch_arr['cli_ID']}'
	                and cli_faxres = '1' ");
	}

	if (!isset($batch_arr['id']) || !isset($batch_arr['faxed'])) {
		$report = $wpdb->get_row("select  re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.instrument,
	                re.final_results 
	                from {$table_report}  re
	                where re.patient_id = '{$batch_arr['assen']}' ", ARRAY_A);
		if (!$report) {
			return false;
		}
		$batch_arr = array_merge($batch_arr, $report);
	}


	if (!$batch_arr['faxed'] && !empty($fax_number)) {
		$added_fax = $wpdb->get_var("select id from ci_box_upload 
                                        where mid = {$mid} 
                                        and type='{$type_result}' 
                                        ");
		if (!$added_fax) {

			list($path, $file) = trpdd_get_path_result_pdf($batch_arr, $btype);
			$path_db = $path . '/' . $file;

			$wpdb->insert(
				'ci_box_upload',
				array(
					'path'       => $path_db,
					'type'       => $type_result,
					'status'     => '5',
					'mid'        => $batch_arr['mid'],
					'account_id' => $batch_arr['cli_ID'],
					'etag'       => trim($fax_number),
					'lab_ID'     => $batch_arr['lab_id'],
					'batch_id'   => $batch_arr['batch_id'],
					'btype'      => $btype,
					'uptime'      => time(),
					'sender'     => $batch_arr['requesting_practice'],
					'created'    => date_i18n('Y-m-d H:i:s'),
					'created_by' => '-1'
				)
			);
		} else {
			$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
		}
	}
}

function trpdd_get_bacteria_table($groupk)
{
	$table = 'ci_bacteria';
	if ($groupk != 'uti') {
		$table = 'ci_bacteria_' . $groupk;
	}
	return $table;
}

function trpdd_get_abx_table($groupk)
{

	$table = 'ci_abx';
	if ($groupk != 'uti' && !is_null($groupk)) {
		$table = 'ci_abx_' . $groupk;
	}
	return $table;
}

function trpdd_get_batch_conditions($user_id = 0, $btype = '', $args = array())
{
	global $wpdb, $current_role;

	$return = array(
		'where' 			=> '',
		'where_clinic' 		=> isset($args['clinic_inactive']) && $args['clinic_inactive'] ? ' 1 ' : ' cli_status = 1 ',
		'where_doctor' 		=> ' ',
		'edit_time' 		=> 0,
		'can_edit' 			=> true,
		'can_delete' 		=> false,
		'check_author' 		=> false,
		'only_view_result' 	=> false,
		'can_edit_result' 	=> true,
		'can_delete_result' => true,
		'can_view_csv' 		=> false,
		'can_hold_result' 	=> true,
		'can_approve_result' => true,
		'can_view_req_result' => true,

		'can_fax' 			=> true,
		'can_hold_req' 		=> true,
		'can_remove_hold_req' => false,
		'is_admin' 			=> false,
	);
	if ($user_id == 0) {
		$user_id = get_current_user_id();
	}



	$return['edit_time']  = strtotime(date_i18n('Y-m-d H:i:s'));


	if (tr_current_role_in(array('administrator_p', 'administrator', 'pcr_verify', 'labmanager', 'AdminCIDW'))) {

		$return['edit_time'] = 0;
		$return['can_edit_result'] = true;
		$return['can_edit'] = true;
		$return['is_admin'] = true;
		$return['can_delete'] = true;
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
		$return['can_remove_hold_req'] = true;
	} else if (tr_current_role_in(array('Billing'))) {

		$return['can_edit_result'] = false;
		$return['can_edit'] = true;
		$return['edit_time'] = 0;
		$return['only_view_result'] = true;
	} else if (tr_current_role_in(array('lab_rep', 'lab_rep_w2'))) {
		$rep_id = get_user_meta($user_id, '_rep_id', true);
		$show_all_clinic_to_members = tr_get_option('ci_pdf_data', 'show_group_clinics_to_all_members');
		if ($show_all_clinic_to_members) {
			$marketing_group_ID = $wpdb->get_var($wpdb->prepare(
				"SELECT rep_marketing_group_ID FROM ci_lab_reps WHERE rep_ID = %d",
				$rep_id
			));

			$rep_ids = [$rep_id];

			if (!empty($marketing_group_ID)) {
				$members = $wpdb->get_var($wpdb->prepare(
					"SELECT gr_members FROM ci_marketing_groups WHERE gr_ID = %d",
					$marketing_group_ID
				));

				if (!empty($members)) {
					$rep_ids = explode(',', $members);
				}
			}

			$rep_ids = array_filter(array_map('intval', $rep_ids));

			if (empty($rep_ids)) {
				$cls_ids = [];
			} else {

				$placeholders = implode(',', array_fill(0, count($rep_ids), '%d'));

				$sql = $wpdb->prepare(
					"SELECT cID FROM ci_clinic WHERE `cli_rep-ID` IN ($placeholders)",
					$rep_ids
				);

				$cls_ids = $wpdb->get_col($sql);
			}
		} else {
			$cls_ids = $wpdb->get_col("select cID from ci_clinic where `cli_rep-ID`={$rep_id}");
		}

		if (!empty($cls_ids)) {
			$cls_ids = implode(', ', $cls_ids);
			$return['where'] .= " and ba.cli_ID in  ({$cls_ids})";
			$return['where_clinic'] .= " and cID in  ({$cls_ids})";
		} else {
			$return['check_author'] = true;
		}

		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('lab', 'labb'))) {

		$return['edit_time'] = 0;
		$return['can_edit'] = true;
		$return['can_edit_result'] = true;
		$return['can_delete'] = true;
		$return['can_remove_hold_req'] = true;

		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('lab_tech', 'req_billing'))) {


		$return['edit_time'] -= 86400 * 1000;
		$return['can_delete'] = true;
		$return['can_remove_hold_req'] = true;

		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('ref_lab_tech'))) {

		$ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);

		$return['where'] .= " and ba.ref_lab_ID = '{$ref_lab_id}' ";
		$return['where_clinic'] .= " and cli_ref_lab_ID = {$ref_lab_id} ";

		$return['edit_time'] -=  86400 * 1000;
		$return['can_delete'] = true;
		$return['can_remove_hold_req'] = true;

		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('reflab_manager'))) {

		$ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);

		$return['where'] .= " and ba.ref_lab_ID = '{$ref_lab_id}' ";
		$return['where_clinic'] .= " and cli_ref_lab_ID = {$ref_lab_id} ";

		$return['edit_time'] -= 86400 * 1000;
		$return['can_delete_result'] = false;
		$return['can_view_csv'] = false;
		$return['can_approve_result'] = false;
		$return['can_view_req_result'] = false;
		$return['can_remove_hold_req'] = true;
	} else if (tr_current_role_in(array('collector'))) {

		$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
		if ($current_clinic > 0) {
			$return['where'] .= " and ba.cli_ID = {$current_clinic} ";
			$return['where_clinic'] .= " and cID = {$current_clinic} ";
		}

		$col_id = get_user_meta($user_id, '_collector_id', true);
		if ($col_id == 0) {
			$col_id = -1;
		}
		if (tr_get_option('ci_pdf_data', 'coloshisreqs')) {
			$return['where'] .= " and ba.col_ID = {$col_id}  ";
			$cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
			$cli_ids = implode(',', $cli_ids);
			if (empty($cli_ids)) $cli_ids = '-1';
			$return['where_clinic'] .= " and cID in ({$cli_ids})  ";
		} else if (!tr_get_option('ci_pdf_data', 'collector_see_all')) {

			$cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
			$cli_ids = implode(',', $cli_ids);
			if (empty($cli_ids)) $cli_ids = '-1';
			$return['where'] .= " and (ba.cli_ID in ({$cli_ids}) or ba.col_ID = {$col_id} ) ";
			$return['where_clinic'] .= " and cID in ({$cli_ids})  ";
		}

		$return['edit_time'] -=  86400 * 4; //4days
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('doctor'))) {

		$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
		if ($current_clinic > 0) {
			$return['where'] .= " and ba.cli_ID = {$current_clinic} ";
			$return['where_clinic'] .= " and cID = {$current_clinic} ";
		}

		$doc_id = get_user_meta($user_id, '_doc_id', true);
		if (!tr_get_option('ci_pdf_data', 'doctor_see_all')) {
			$return['where'] .= " and ba.doc_ID = {$doc_id} ";
			$return['where_clinic'] .= " and cID in  (select cli_ID from ci_clinic_doc where doc_ID = {$doc_id})";
		}
		$return['where_doctor'] .= " and doc.doc_ID = {$doc_id} ";

		$return['edit_time'] -=  86400 * 4; //4days
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('lab_director'))) {

		$return['where'] .= " and ba.cli_ID >0";

		$return['can_remove_hold_req'] = true;
		$return['edit_time'] -= 86400 * 1000;
	} else if (tr_current_role_in(array('lab_manager'))) { //Sales regional manager
		//find group
		$groups = $wpdb->get_results("select gr_ID, gr_members from ci_marketing_groups where find_in_set({$user_id}, gr_admins)  ");
		$all_member_ids = array();
		foreach ($groups as $group) {
			$member_ids = explode(',', $group->gr_members);
			$all_member_ids = array_merge($all_member_ids, $member_ids);
		}
		$all_member_ids = array_unique($all_member_ids);
		$all_member_ids = array_filter($all_member_ids, function ($val) use ($user_id) {
			return $val != $user_id;
		});
		if (!empty($all_member_ids)) {
			$placeholders = implode(',', array_fill(0, count($all_member_ids), '%d'));
			$sql = $wpdb->prepare(
				"SELECT cID FROM ci_clinic WHERE `cli_rep-ID` IN ($placeholders)",
				$all_member_ids
			);
			$cls_ids = $wpdb->get_col($sql);
			if (!empty($cls_ids)) {
				$cls_ids_str = implode(', ', $cls_ids);
				$return['where'] .= " and ba.cli_ID in  ({$cls_ids_str})";
				$return['where_clinic'] .= " and cID in  ({$cls_ids_str})";
			} else {
				$return['check_author'] = true;
			}
		} else {
			$return['check_author'] = true;
		}
		$return['where'] .= " and ba.cli_ID >0";
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('clinic'))) {

		$return['check_author'] = true;
		$return['edit_time'] -= 86400 * 4; //4days
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('office_manager'))) {
		$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
		if ($current_clinic > 0) {
			$return['where'] .= " and ba.cli_ID = {$current_clinic} ";
			$return['where_clinic'] .= " and cID = {$current_clinic} ";
		} else {
			$cid     = get_user_meta($user_id, '_clinic_id', true);
			if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true) || $cid > 0) {

				$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
				$cli_ids = implode(',', $cli_ids);

				$return['where'] .= " and ba.cli_ID in ( {$cli_ids} )";
				$return['where_clinic'] .= " and cID in ( {$cli_ids} )";
			}
		}
		if (tr_get_option('ci_pdf_data', 'aoffice_delete')) {
			$return['can_delete'] = true;
		}
		$return['edit_time'] -= 86400 * 4; //4days
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('multi_site_manager'))) {
		$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
		if ($current_clinic > 0) {
			$return['where'] .= " and ba.cli_ID = {$current_clinic} ";
			$return['where_clinic'] .= " and cID = {$current_clinic} ";
		} else if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
			//can see all clinics
		} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
			$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
			if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
				$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
			}
			$ms_cli_ids_str = implode(',', $ms_cli_ids);
			$return['where'] .= " and ba.cli_ID in ( {$ms_cli_ids_str} ) ";
			$return['where_clinic'] .= " and cID in ( {$ms_cli_ids_str} ) ";
		}

		if (tr_get_option('ci_pdf_data', 'aoffice_delete')) {
			$return['can_delete'] = true;
		}
		$return['edit_time'] = 0;
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;

		$return['can_edit_result'] = true;
		$return['can_edit'] = true;
	} else if (tr_current_role_in(array('lab_sec'))) {

		$return['edit_time'] = 0;
		$return['can_remove_hold_req'] = true;
	} else if (tr_current_role_in(array('results'))) {
		$return['edit_time'] = 0;
		$return['can_edit'] = false;
		$return['can_edit_result'] = false;
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('leadlabsecman', 'labsecman', 'Data Entry', 'Front Administrator'))) {

		$return['edit_time'] -= 86400 * 365;
		$return['can_remove_hold_req'] = true;
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('pcrLabManager'))) {

		$return['can_edit'] = true;
		$return['can_edit_result'] = false;
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
	} else if (tr_current_role_in(array('distributor'))) {
		$return['can_delete_result'] = true;
		$return['can_view_csv'] = true;
		$distributor_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID={$user_id} ");

		$ep_marketing_group_IDs = $wpdb->get_col("select gr_ID FROM `ci_marketing_groups` where `gr_distributor_ID`= {$distributor_id}");
		if (empty($ep_marketing_group_IDs)) {
			$ep_marketing_group_IDs[] = -1;
		}
		$ep_marketing_group_IDs_str = implode(',', $ep_marketing_group_IDs);

		$rep_ids = $wpdb->get_col("select rep_ID from ci_lab_reps 
			where rep_distributor_ID = {$distributor_id} 
			or rep_marketing_group_ID in ({$ep_marketing_group_IDs_str})");

		$wheredis = " cli.cli_distributor_ID = {$distributor_id} ";
		if (count($rep_ids) > 0) {
			$rep_ids_str = implode(',', $rep_ids);
			$wheredis .= " or `cli_rep-ID` in ({$rep_ids_str}) ";
		}


		$cliids = $wpdb->get_col("select cli.cID from ci_clinic cli 
					where {$wheredis}
					group by cli.cID");


		if (empty($cliids)) {
			$cliids = array(-1);
		}
		$cliids_str = implode(',', $cliids);



		$return['where_clinic'] .= " and cID in ({$cliids_str}) ";
		$return['where'] .= " and ba.cli_ID in ( {$cliids_str} ) ";
	} else {
		$return['check_author'] = true;
		$return['edit_time'] -= 86400 * 4; //4days
	}

	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		if (!in_array($current_role, array('office_2manager'))) {
			$return['where'] .= " and ba.lab_id in ( {$lab_str} ) ";
		}

		$return['where_clinic'] .= " and `cli_lab-ID` in  ({$lab_str})";
	}

	if ($return['check_author']) {
		$user_ids = tr_dpp_get_users_in_group($user_id);
		$user_ids = implode(',', $user_ids);
		$return['where_author'] = " and ba.author_ID in ( {$user_ids} ) ";
	}

	if (!tr_get_option('ci_pdf_data', 'req_show_hold')) {
		$return['can_hold_req'] = false;
	}

	if (tr_get_option('ci_pdf_data', 'private_lab_clinic')) {
		$lab_id = CI_Base_Lab::get_current_lab_id();
		//$return['where_clinic'].= " and cID in (select cli_ID from ci_clinic_lab where lab_ID='{$lab_id}')";
	}

	if (tr_get_option('ci_pdf_data', 'req_ddelete', false)) {
		$user = wp_get_current_user();
		if ($user->user_login != 'Administrat0r') {
			$return['can_delete'] = false;
			$return['can_edit_result'] = false;
		}
	}

	if (isset($args['hold']) && $args['hold']) {
		$return['where'] .= " and ba.batch_status = 'hold' ";
		$return['can_hold_req'] = false;
	}

	if (current_user_can('dont_allow_delete') && !$return['is_admin']) {
		$return['can_delete'] = false;
		$return['can_delete_result'] = false;
	}

	if (tr_get_option('ci_pdf_data', 'req_always_editable')) {
		$return['edit_time'] = 0;
	}

	return $return;
}

function trpdd_get_batch_meta($mid, $btype, $key, $is_saved = false, $default = null)
{
	global $wpdb;
	if (empty($mid) || empty($key)) return '';
	if ($is_saved) {
		$key .= '_saved';
	}
	$meta_tbl = tr_pdd_get_table_by_btype($btype) . '_meta';
	$meta = $wpdb->get_var("select meta_value from {$meta_tbl}
	where mid = {$mid}
	and meta_key = '{$key}' ");
	if ($meta) {
		$sr = maybe_unserialize($meta);
	} else if ($default !== null) {
		return $default;
	}

	return isset($sr) && is_array($sr) ? $sr : $meta;
}

function trpdd_update_batch_meta($mid, $btype, $key, $value, $is_saved = false)
{
	global $wpdb;
	if (empty($mid) || empty($key)) return '';

	$raw_value = $value;
	if (is_array($value)) {
		$value = maybe_serialize($value);
	}
	if ($is_saved) {
		$key .= '_saved';
	}
	$meta_tbl = tr_pdd_get_table_by_btype($btype) . '_meta';
	$exist = $wpdb->get_var("select count(*) from {$meta_tbl} 
	where mid = {$mid}
	and meta_key = '{$key}'");
	$data = array(
		'mid' => $mid,
		'meta_key' => $key,
		'meta_value' => $value,
	);
	if ($exist) {
		$wpdb->update($meta_tbl, $data, array('mid' => $mid, 'meta_key' => $key));
	} else if (!empty($raw_value)) {
		$wpdb->insert($meta_tbl, $data);
	}
}

function trpdd_delete_batch_meta($mid, $btype, $key, $is_saved = false)
{
	global $wpdb;
	if (empty($mid) || empty($key)) return false;
	if ($is_saved) {
		$key .= '_saved';
	}
	$meta_tbl = tr_pdd_get_table_by_btype($btype) . '_meta';
	return $wpdb->delete($meta_tbl, array('mid' => $mid, 'meta_key' => $key));
}


function trpdd_get_test_meta($tid, $key, $default = null)
{
	global $wpdb;
	if (empty($tid) || empty($key)) return $default;

	$meta = $wpdb->get_var("select meta_value from ci_test_meta
	where test_id = {$tid}
	and meta_key = '{$key}' ");
	if ($meta) {
		$sr = maybe_unserialize($meta);
	} else if ($default !== null) {
		return $default;
	}

	return isset($sr) && is_array($sr) ? $sr : $meta;
}

function trpdd_update_test_meta($tid, $key, $value)
{
	global $wpdb;

	if (empty($tid) || empty($key)) return false;

	$raw_value = $value;
	if (is_array($value)) {
		$value = maybe_serialize($value);
	}

	$exist = $wpdb->get_var("select count(*) from ci_test_meta
	where test_id = {$tid} and meta_key = '{$key}'");
	$data = array(
		'test_id' => $tid,
		'meta_key' => $key,
		'meta_value' => $value,
	);
	if ($exist) {
		return $wpdb->update('ci_test_meta', $data, array('test_id' => $tid, 'meta_key' => $key));
	} else if (!empty($raw_value)) {
		return $wpdb->insert('ci_test_meta', $data);
	}
}

function tr_parse_address($address)
{
	$address = trim($address, "\t\n\r\0\x0B, ");
	$address = str_replace('  ', ' ', $address);
	$arr = array();
	$arr2 = explode(',', $address);
	$arr['address'] = trim($arr2[0]);
	$arr['city'] = trim($arr2[1]);
	$arr2[2] = trim($arr2[2], "\t\n\r\0\x0B, ");
	$arr3 = explode(' ', $arr2[2]);
	$arr['state'] = trim($arr3[0]);

	$arr['zip'] = isset($arr2[3]) && !empty($arr2[3]) ? $arr2[3] : trim($arr3[1]);

	return $arr;
}

function trpdd_get_current_btype()
{

	$type = 'tox';
	if (isset($_REQUEST['btype']) && !empty($_REQUEST['btype'])) {
		$type = $_REQUEST['btype'];
	} else if (isset($_REQUEST['type']) && !empty($_REQUEST['type'])) {
		$type = $_REQUEST['type'];
	} else {
		global $ci_all_btypes;
		foreach ($ci_all_btypes as $bt) {
			if (stripos($_SERVER['HTTP_REFERER'], $bt)) {
				$type = $bt;
				break;
			}
		}
	}


	return $type;
}


function trpdd_is_role_disabled($role_key)
{
	$role_key = sanitize_key($role_key);
	if (empty($role_key)) {
		return false;
	}

	$role = get_role($role_key);
	if (!$role) {
		return false;
	}
	return isset($role->capabilities['disable_role']) && $role->capabilities['disable_role'];
}

function trpdd_user_has_disabled_role($user = null)
{
	if (!$user) {
		$user = wp_get_current_user();
	}

	if (!$user || is_wp_error($user) || empty($user->roles) || !is_array($user->roles)) {
		return false;
	}

	foreach ($user->roles as $role) {
		if (trpdd_is_role_disabled($role)) {
			return true;
		}
	}

	return false;
}

function tr_current_role_in($roles = array())
{
	global $current_role, $ci_base_roledf;

	$in = in_array($current_role,  $roles);
	if (!$in && !empty($ci_base_roledf)) {
		$in = in_array($ci_base_roledf, $roles);
	}
	return $in;
}

function trpdd_display_time_duration($seconds, $displaysecond = false)
{

	$days = floor($seconds / 86400);
	$seconds -= $days * 86400;
	$hours = floor($seconds / 3600);
	$seconds -= $hours * 3600;
	$minutes = floor($seconds / 60);
	$seconds -= $minutes * 60;
	$out = '';
	if ($days > 0) {
		$out .= $days . 'd,';
	}
	if ($hours > 0) {
		$out .= $hours . 'h ';
	}
	if ($minutes > 0) {
		$out .= $minutes . 'm ';
	}
	if ($displaysecond && $seconds > 0) {
		$out .= $seconds . 's ';
	}

	return $out;
}

function trpdd_can_view_header_clinic_dropdown()
{
	global $is_ci_admin;
	if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'doctor', 'collector')) || $is_ci_admin) {
		return true;
	}
	return false;
}


function trp_pdd_get_hours($datetime)
{
	$now = date_i18n('Y-m-d H:i:s');
	$seconds = strtotime($now) - strtotime($datetime);
	$hours = floor($seconds / 3600);
	$mins = floor($seconds % 3600 / 60);
	return $hours . ':' . $mins;
}

function tr_pdd_build_batch_id_where($from, $to, $alias = 'ba')
{
	$batch_id_from = date('Ymd', strtotime($from));
	$batch_id_to   = date('Ymd', strtotime($to));

	if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
		$all_bids = [];
		$period_tmp = new DatePeriod(
			new DateTime($from),
			new DateInterval('P1D'),
			(new DateTime($to))->modify('+1 day')
		);
		foreach ($period_tmp as $d) {
			$bid = $d->format('Ymd');
			$all_bids[] = $bid;
			for ($i = 1; $i <= 10; $i++) {
				$all_bids[] = $bid . '-' . $i;
			}
		}
		$all_bids_str = implode("','", $all_bids);
		return " {$alias}.batch_id in ('{$all_bids_str}') ";
	}

	return " {$alias}.batch_id >= '{$batch_id_from}' AND {$alias}.batch_id <= '{$batch_id_to}' ";
}

function trpdd_abx_query_date_range($from, $to)
{
	$where = '';
	if (tr_get_option('ci_pdf_data', 'abx_db_base_batch_id')) {
		$where .= tr_pdd_build_batch_id_where($from, $to);
	} else {
		if (strpos($to, ' ') === false) {
			$to .= ' 23:59:59';
		}
		$where .= " ( ba.doc between '{$from}' and '{$to}' ) ";
	}
	return $where;
}

function trpdd_abx_query_field_date($format)
{
	$field = 'ba.doc';
	if (tr_get_option('ci_pdf_data', 'abx_db_base_batch_id')) {
		//20251109
		$field = "STR_TO_DATE(ba.batch_id, '%Y%m%d')";
	}
	$field = "DATE_FORMAT({$field}, '{$format}')";
	return $field;
}

function trpdd_filter_comments_in_pdf($comments)
{
	if (!empty($comments)) {
		$comments = stripslashes($comments);
		$comments = str_replace(array('<=', '&lt;=', '>=', '&gt;='), array("≤", "≤", "≥", "≥"), $comments);
		$comments = preg_replace('/\<([\s0-9]+)/', '&lt;$1', $comments);
		$comments = preg_replace('/([\s0-9]+)\>/', '$1&gt;', $comments);
	}
	return nl2br($comments);
}

function trpdd_rand_color()
{
	return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

function trpdd_is_ci_page($url)
{
	if (
		stripos($url, 'ci_') !== false
		|| stripos($url, '_results') !== false
		|| stripos($url, 'req_form') !== false
		|| stripos($url, 'receiving') !== false
	) {
		return true;
	}
	return false;
}
function generate_report_filename(
	string $report_type = "",
	string $name_report = "",
	string $from_date = "",
	string $to_date = "",
	string $extension = "pdf"
): string {
	$lab_name = CI_Base_Lab::get_current_lab_init();
	$formatted_from_date = $from_date ? date('Ymd', strtotime($from_date)) : "";
	$formatted_to_date = $to_date ? date('Ymd', strtotime($to_date)) : "";
	if ($formatted_from_date == $formatted_to_date) {
		$formatted_to_date = "";
	}

	$parts = [
		substr($lab_name, 0, 3),
		$report_type,
		$name_report,
		$formatted_from_date,
		$formatted_to_date
	];

	return implode('-', array_filter($parts)) . '.' . $extension;
}

function trpdd_get_standing_order_statuses($btypes)
{
	global $wpdb;


	$all_status = array('' => 'None');
	$btypes_str = implode("','", $btypes);
	$statusrs = $wpdb->get_results("select s_key, s_text from ci_batches_status where s_type = 'so' and btype in ('{$btypes_str}')");
	foreach ($statusrs as $sr) {
		$all_status['so_' . $sr->s_key] = $sr->s_text;
	}

	if (empty($all_status)) {
		include_once(TRCPDD_PATH . 'parts/scripts/add_status_standing_orders.php');
		$all_status = array(
			'' => 'None',
			'so_assigned' => 'Assigned',
			'so_collected' => 'Collected',
			'so_not_collected' => 'Not Collected',
			'so_refused' => 'Refused',
			'so_stopped' => 'Stopped'
		);
		if (tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) {
			$all_status     = array(
				'' => 'None',
				'so_assigned' => 'Checked-In',
				'so_collected' => 'Collected',
			);
		}
	}
	return $all_status;
}
function tr_pdd_get_batch_changes(array $batch, array $bat_snapshot): array
{
	$changes = [];
	foreach ($batch as $field => $new_val) {
		if (!isset($bat_snapshot[$field]) || !isset($new_val) || in_array($field, ['req_form_time', 'batch_modified'])) {
			continue;
		}
		if ((string) $bat_snapshot[$field] !== (string) $new_val && !($bat_snapshot[$field] == '0' && $new_val === '') && !($bat_snapshot[$field] == '0000-00-00' && $new_val === '')) {
			$changes[$field] = [
				'old' => $bat_snapshot[$field],
				'new' => $new_val,
			];
		}
	}
	return $changes;
}

function tr_pdd_build_diff(array $old, array $new): array
{
	$changes = [];
	foreach ($old as $field => $new_val) {
		if (!isset($new[$field]) || !isset($new_val) || in_array($field, ['req_form_time', 'batch_modified'])) {
			continue;
		}
		if ((string) $new[$field] !== (string) $new_val && !($new[$field] == '0' && $new_val === '') && !($new[$field] == '0000-00-00' && $new_val === '')) {
			$changes[$field] = [
				'old' => $new[$field],
				'new' => $new_val,
			];
		}
	}
	return $changes;
}

function trpdd_has_screening($report)
{
	$screenings = tr_pdd_get_result_validity(1, $report['batch_created'], $report['mid']);
	$has_screeing = false;
	if ($report['matrix'] != "oral") {
		foreach ($screenings as $arr) {
			if (!$arr['checked']) {
				continue;
			}
			if (!empty($arr['conc']) || !empty($arr['outcome'])) {
				$has_screeing = true;
				break;
			}
		}
	}

	return $has_screeing;
}
//end file
