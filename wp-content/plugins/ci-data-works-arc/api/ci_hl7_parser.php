<?php
include_once(__DIR__ . '/ci_hl7.php');

class CI_HL7_Parser extends CI_Hl7
{
	protected function render_segment_value($value): string
	{
		if (is_array($value)) {
			$value = array_map(array($this, 'render_segment_value'), $value);
			return implode('^', $value);
		}

		return htmlspecialchars((string) $value);
	}

	public function parse_hl7(string $msg): array
	{
		$msg = str_replace("\r\n", "\n", $msg);
		$msg = str_replace("\n", "\r", $msg);
		$segs = explode("\r", $msg);
		$out = array();

		if (empty($segs[0]) || substr($segs[0], 0, 3) != 'MSH') {
			$out['ERROR'][0] = 'Invalid HL7 Message.';
			$out['ERROR'][1] = 'Must start with MSH';
			return $out;
		}

		$delbarpos = strpos($segs[0], '|', 4);
		$delchar = substr($segs[0], 4, ($delbarpos - 4));
		$out['FLD_SEP'] = substr($delchar, 0, 1);
		$out['SFLD_SEP'] = substr($delchar, 1, 1);
		$out['REP_SEP'] = substr($delchar, 2, 1);
		$out['ESC_CHAR'] = substr($delchar, 3, 1);

		$current_num = 0;
		foreach ($segs as $fseg) {
			$fseg = trim($fseg);
			if (empty($fseg)) {
				continue;
			}

			$current_num++;
			$segments = explode('|', $fseg);
			$segname = $segments[0];
			$i = 0;

			foreach ($segments as $k => $seg) {
				$j = 1;
				if (strpos($seg, $out['SFLD_SEP']) > 0) {
					$sf = explode($out['SFLD_SEP'], $seg);
					foreach ($sf as $f) {
						$out[$current_num][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = explode($out['FLD_SEP'], $f);
						$j++;
					}
				} else {
					$sf = explode($out['FLD_SEP'], $seg);
					foreach ($sf as $f) {
						$out[$current_num][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
						$j++;
					}
				}

				$i++;
				if ($k == 0 && $segname == 'MSH') {
					$out[$current_num][$segname . '.' . $i][$segname . '.' . $i . '.1'] = '|';
					$i++;
				}
			}
		}

		return $out;
	}

	public function render_html(array $parsed): string
	{
		$accordion_id = uniqid('hl7-accordion-');
		$wrapper_id = uniqid('hl7-wrapper-');
		$button_id = uniqid('hl7-toggle-');
		$label_id = uniqid('hl7-label-');
		$view_label = 'Parsed HL7 Viewer';
		$html = '<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">';
		$html .= '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
		$html .= '<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>';
		$html .= '<div class="trpdd-hl7-parsed-block">';
		$html .= '<div class="trpdd-hl7-parsed-header">';
		$html .= '<button type="button" id="' . $label_id . '" class="button-link trpdd-hl7-toggle-label" aria-expanded="false" aria-controls="' . $wrapper_id . '">' . htmlspecialchars($view_label) . '</button>';
		$html .= '<button type="button" id="' . $button_id . '" class="button trpdd-hl7-toggle-btn" aria-expanded="false" aria-controls="' . $wrapper_id . '" aria-label="' . htmlspecialchars($view_label) . '" title="' . htmlspecialchars($view_label) . '"><span class="trpdd-hl7-toggle-icon" aria-hidden="true"></span></button>';
		$html .= '</div>';
		$html .= '<div id="' . $wrapper_id . '" class="trpdd-hl7-toggle-panel" style="display:none;">';
		$html .= '<div id="' . $accordion_id . '">';

		foreach ($parsed as $segment => $data) {
			if (in_array($segment, array('SFLD_SEP', 'FLD_SEP', 'REP_SEP', 'ESC_CHAR'))) {
				continue;
			}

			$segment = is_numeric($segment) ? str_replace('.0', '', array_key_first($data)) : $segment;

			$shorttext = '';
			foreach ($data as $value) {
				$value = !is_array($value) ? array($value) : $value;
				$value = array_map(function ($v) {
					return is_array($v) ? implode('&', array_map('strval', $v)) : (string) $v;
				}, $value);
				$shorttext .= htmlspecialchars(implode('^', $value)) . '|';
			}

			$shorttext = '<span style="font-size:12px;color:#666;margin-left:10px;">' . substr($shorttext, 0, 200) . '</span>';
			$html .= '<h3>' . htmlspecialchars($segment) . $shorttext . '</h3>';
			$html .= '<div><pre>';

			foreach ($data as $key => $value) {
				$value = !is_array($value) ? array($value) : $value;

				$html .= '<div class="item"><strong>' . htmlspecialchars($key) . ':</strong> ';
				$html .= $this->render_segment_value($value);
				if (count($value) > 1) {
					foreach ($value as $k2 => $vl) {
						$html .= '<div class="item"><strong>' . htmlspecialchars((string) $k2) . ': </strong>' . $this->render_segment_value($vl) . '</div>';
					}
				}

				$html .= '</div>';
			}

			$html .= '</pre></div>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<style>
		.trpdd-hl7-parsed-block{
			margin:12px 0 0;
			border:1px solid #dcdcde;
			border-radius:6px;
			background:#fff;
			overflow:hidden;
		}
		.trpdd-hl7-parsed-header{
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:8px;
			padding:5px 10px;
			background:#f6f7f7;
			border-bottom:1px solid #dcdcde;
		}
		.trpdd-hl7-toggle-label{
			font-size:13px;
			font-weight:600;
			color:#1d2327;
			text-decoration:none;
			padding:0;
			border:0;
			background:transparent;
			cursor:pointer;
		}
		.trpdd-hl7-toggle-label:hover,
		.trpdd-hl7-toggle-label:focus{
			color:#135e96;
			text-decoration:none;
			box-shadow:none;
		}
		.trpdd-hl7-toggle-btn{
			min-width:22px;
			width:22px;
			padding:0;
			line-height:20px;
			height:20px;
			min-height:20px;
			font-weight:600;
			color:#1d2327;
			border:0;
			background:transparent;
			box-shadow:none;
		}
		.trpdd-hl7-toggle-btn:hover,
		.trpdd-hl7-toggle-btn:focus{
			color:#135e96;
			background:rgba(19, 94, 150, 0.08);
		}
		.trpdd-hl7-toggle-icon{
			display:inline-block;
			width:8px;
			height:8px;
			border-right:2px solid currentColor;
			border-bottom:2px solid currentColor;
			transform:rotate(45deg);
			transition:transform 0.15s ease;
			margin-top:-1px;
		}
		.trpdd-hl7-toggle-btn.is-open .trpdd-hl7-toggle-icon{
			transform:rotate(-135deg);
			margin-top:1px;
		}
		.trpdd-hl7-toggle-panel{
			padding:10px;
			background:#fff;
		}
		.ui-accordion .ui-accordion-header{
			font-size:13px;
			padding:0;
		}
		.ui-accordion .item{
			border-bottom:1px solid #eee;
			padding-left:20px;
		}
		</style>
		<script>
		$(function() {
			$("#' . $accordion_id . '").accordion({
				collapsible: true,
				active: false,
				heightStyle: "content"
			});
			var $button = $("#' . $button_id . '");
			var $label = $("#' . $label_id . '");
			var $wrapper = $("#' . $wrapper_id . '");
			var setExpandedState = function(isVisible) {
				$button.attr("aria-expanded", isVisible ? "true" : "false");
				$button.toggleClass("is-open", isVisible);
				$label.attr("aria-expanded", isVisible ? "true" : "false");
			};
			var togglePanel = function() {
				var $wrapper = $("#' . $wrapper_id . '");
				$wrapper.toggle();
				setExpandedState($wrapper.is(":visible"));
			};
			$button.on("click", togglePanel);
			$label.on("click", togglePanel);
			setExpandedState($wrapper.is(":visible"));
		});
		</script>';

		return $html;
	}
}
