<?php

$questions = $form_options['questions'];

$last_col_num = 0;
$last_type = '';
$group_adding = '';
$cnum = 0;
$fieldnum = 0;

echo '<div class="frow">';
foreach ($questions as $question) {
	$col_num = 3;
	$has_open_column = false;
	if (in_array($question['type'], array('texta', 'cond2', 'cond4', 'cond8', 'mcheckbox', 'mtext'))) {
		$col_num = 1;
	}
	if ($question['cols'] > 0) {
		$col_num = $question['cols'];
	}
	$question_key = $question['key'];
	if (empty($question_key)) {
		$question_key = substr(preg_replace("/[^0-9a-z]/", '', strtolower($question['question'])),0,30);
	}
	$value = '';
	$name = 'batch_meta[' . $question_key . ']';

	if ($question['type'] == 'mtext') {
		$col_num = 1;
		$cnum = 0;
	} else if ($col_num == 1) {
		echo '</div><div class="frow full ' . $question['type'] . '">' . "\n";
		$cnum = 0;
	} else {
		if ($last_col_num > 0 && ($cnum % $col_num == 0 || ($last_col_num > 0 && $last_col_num != $col_num))) {
			echo '</div><div class="frow">' . "\n";
			$cnum = 0;
		} else if ($question['type'] == 'cb2' && $last_type != '' && $last_type != $question['type']) {
			echo '</div><div class="frow cb2">' . "\n";
			$cnum = 0;
		}

		echo '<div class="col' . $col_num . ' ' . $question['type'] . '">' . "\n";
		$has_open_column = true;

		$cnum++;
	}

	if (!in_array($question['type'], array('checkbox', 'mtext'))) {
		echo '<label>' . $question['question'] . '</label><br>' . "\n";
	}


	$last_col_num   = $col_num;
	$last_type      = $question['type'];

	switch ($question['type']) {
		case 'select':
		case 'radio':
			$type = 1;
			if (strpos($question['options'], "\r") > 0) {
				$options = explode("\r", $question['options']);
				$type = 2;
			} else {
				$options = explode('|', $question['options']);
			}

			echo '<select name="' . $name . '">';
			echo '<option value="">Select</option>';
			foreach ($options as $vl) {
				if (strpos($vl, ',') > 0) {
					list($k, $txt) = explode(',', $vl);
				} else {
					$k = $txt = $vl;
				}


				$k = trim($k);
				$selected = $value == $k ? 'selected' : '';
				echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
			}
			echo '</select>' . "\n";
			break;

		case 'mselect':
			$type = 1;
			if (strpos($question['options'], "\r") > 0) {
				$options = explode("\r", $question['options']);
				$type = 2;
			} else {
				$options = explode(',', $question['options']);
			}

			$adding_value = '';

			echo '<select name="' . $name . '[]" multiple class="js_select2 ">';
			$values = explode('|', $value);
			foreach ($options as $txt) {
				$txt = trim($txt);
				$selected = in_array($txt, $values) ? 'selected' : '';
				echo '<option value="' . $txt . '" ' . $selected . '>' . $txt . '</option>';
			}
			echo '</select>' . "\n";

			break;

		case 'checkbox':
			echo '<input type="hidden" name="' . $name . '" value="0"/>';
			echo '<label> <input type="checkbox" name="' . $name . '" value="1" ' . checked($value, '1', false) . '/> ';
			echo $question['question'];
			echo '</label>' . "\n";
			break;

		case 'mcheckbox':
			$type = 1;
			if (strpos($question['options'], "\r") > 0) {
				$options = explode("\r", $question['options']);
				$type = 2;
			} else {
				$options = explode(',', $question['options']);
			}
			$adding_value = '';


			$value = maybe_unserialize($value);
			if(!is_array($value))$value = array();
			foreach ($options as $txt) {
				$txt = trim($txt);
				$checked 	= in_array($txt, $value);
				$fieldnum++;
				$has_other = strtolower($txt)=='other:' || strtolower($txt)=='other';
				echo '<label class="mcbox"> <input type="checkbox" class="'.($has_other? 'hasother':'').'" data-id="'.$fieldnum.'" name="' . $name . '[]" value="' . $txt . '" ' . ($checked?'checked':'') . '/> ';
				echo ucfirst($txt);
				echo '</label>' . "\n";

				if($has_other){
					echo '<label class="forother mcbox hide fieldnum_'.$fieldnum.'">';
					echo '<input type="text" name="' . $name . '[other]" placeholder="'.$txt.'" />';
					echo '</label>';
				}
			}
			break;


		case 'date':
			echo '<input type="text" class="hasdate" name="' . $name . '" value="' . $value . '"/>' . "\n";
			break;

		case 'texta':
			echo '<textarea name="' . $name . '">' . $value . '</textarea>' . "\n";
			break;

		case 'textu':
			$values = explode('|', $value);
			if (empty($values[1])) {
				$values[1] = $question['options'];
			}
			echo '<input type="text" name="' . $name . '[0]" value="' . $values[0] . '" style="width:60%"/>';
			echo '<input type="text" name="' . $name . '[1]" value="' . $values[1] . '" style="width:35%"/>';
			break;

		case 'mtext':
			$options = explode('|', $question['question'].'|'. $question['options']);
			
			echo '<div class="mtext_row">';

			echo '<div class="frow">';
			foreach ($options as $txt) {
				$col_num1 = count($options);
				echo '<div class="col' . $col_num1 . '">';
				echo '<label>' . $txt . '</label><br>' . "\n";
				echo '</div>';
			}
			echo '</div>';

			echo '<div class="frow main_row">';
			foreach ($options as $i2 =>$txt) {
				$col_num1 = count($options);
				$name = 'batch_meta[' . $question['key']  . ']['.$i2.'][]';

				echo '<div class="col' . $col_num1 . '">';
				echo '<input type="text" name="' . $name . '" value="' . $value . '"/>';
				echo '</div>';
			}
			echo '</div>';
			echo '<a class="addnew_mtext">+</a>';

			echo '</div>';
			$col_num = 1;
			break;

		default:
			echo '<input type="text" name="' . $name . '" value="' . $value . '"/>';
			break;
	}

	if ($has_open_column) {
		echo '</div>' . "\n"; //end col
	}
}
echo '</div>' . "\n"; //end row
?>

<script>
	jQuery(function($) {
		$('body').on('click', '.addnew_mtext', function() {
			var $pr = $(this).parents('.mtext_row');
			var $html = '<div class="frow">' + $('.frow.main_row', $pr).html() + '</div>';
			var $newrow = $($html);
			$('input', $newrow).val('');
			$newrow.insertBefore($(this));
		})
	})
</script>