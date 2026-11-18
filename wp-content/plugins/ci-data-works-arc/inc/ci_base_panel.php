<?php
class CI_Base_Panel
{
	public static function list_active($btype)
	{
		global $wpdb;

		return $wpdb->get_results("select * from ci_test_groups where tg_type='{$btype}' and tg_show_req = 1
		order by tg_position asc");
	}

	/*
	atts: btype, value, name, class
	*/
	public static function panel_options($args = array())
	{
		$btype = $args['btype'];
		$value = $args['value'];
		$panels = self::list_active($btype);
		$atts = '';
		if (!empty($args['name'])) {
			$atts .= ' name="' . $args['name'] . '" ';
		}
		if (!empty($args['class'])) {
			$atts .= ' class="' . $args['class'] . '" ';
		}
		$blank_text = !empty($args['blank_text']) ? $args['blank_text'] : 'Select';

		echo '<select ' . $atts . '>';
		echo '<option value="">' . esc_html($blank_text) . '</option>';
		foreach ($panels as $panel) {
			$selected = $panel->tg_ID == $value ? 'selected' : '';
			echo '<option value="' . $panel->tg_ID . '" ' . $selected . '>' . esc_html($panel->tg_name) . '</option>';
		}
		echo '</select>';
	}

	public static function generateColorVariationHSL(string $base_color, int $index, int $total_groups): string
	{
		if ($index === 0 || $total_groups === 1) {
			return $base_color;
		}

		$hex = ltrim($base_color, '#');

		$r = hexdec(substr($hex, 0, 2)) / 255;
		$g = hexdec(substr($hex, 2, 2)) / 255;
		$b = hexdec(substr($hex, 4, 2)) / 255;

		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$l = ($max + $min) / 2;

		if ($max === $min) {
			$h = $s = 0;
		} else {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

			switch ($max) {
				case $r:
					$h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
					break;
				case $g:
					$h = (($b - $r) / $d + 2) / 6;
					break;
				case $b:
					$h = (($r - $g) / $d + 4) / 6;
					break;
			}
		}

		$lightness_increase = ($index * 0.08);
		$l = min(0.95, $l + $lightness_increase);

		$s = max(0.2, $s * (1 - $index * 0.1));

		if ($s === 0) {
			$r = $g = $b = $l;
		} else {
			$hue2rgb = function ($p, $q, $t) {
				if ($t < 0) $t += 1;
				if ($t > 1) $t -= 1;
				if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
				if ($t < 1 / 2) return $q;
				if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
				return $p;
			};

			$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
			$p = 2 * $l - $q;

			$r = $hue2rgb($p, $q, $h + 1 / 3);
			$g = $hue2rgb($p, $q, $h);
			$b = $hue2rgb($p, $q, $h - 1 / 3);
		}

		return sprintf(
			'#%02x%02x%02x',
			round($r * 255),
			round($g * 255),
			round($b * 255)
		);
	}

	public static function get_panel_types($value_key = 'name')
	{
		$panel_types = get_option('panel_types', [
			[
				'key'   => 'uti',
				'name'  => 'UTI',
				'color' => '#e5e67d',
			],
			[
				'key'   => 'wound',
				'name'  => 'Wound',
				'color' => '#f94947',
			],
			[
				'key'   => 'gi',
				'name'  => 'GI',
				'color' => '#b2d72c',
			],
			[
				'key'   => 'ei',
				'name'  => 'ENT',
				'color' => '#f9a825',
			],
			[
				'key'   => 'nail',
				'name'  => 'Nail',
				'color' => '#47e61f',
			],
			[
				'key'   => 'r',
				'name'  => 'Respiratory',
				'color' => '#70d4d6',
			],
			[
				'key'   => 'v',
				'name'  => 'Vaginitis',
				'color' => '#f495c3',
			],
			[
				'key'   => 'sti',
				'name'  => 'STI',
				'color' => '#7662a3',
			],
			[
				'key'   => 'other',
				'name'  => 'Other',
				'color' => '#cccccc',
			],
		]);

		return array_column($panel_types, $value_key, 'key');
	}

	public static function  getPosNegType($tes_type)
	{
		$posnegs = array(
			0 => 'Negative/Positive',
		);

		if ($tes_type == 'blood') {
			$posnegs[4] = 'Negative/Equivocal/Positive';
			$posnegs[1] = 'Low/Normal/High';
			$posnegs[2] = 'Detected/Not Detected';
			$posnegs[3] = 'Reactive/Not Reactive';
			$posnegs[5] = 'Normal/Abnormal';
			$posnegs[6] = 'Normal/At Risk/High';
			$posnegs[7] = '1+, 2+, 3+/None Seen';
			$posnegs[8] = '1+, 2+, 3+/Trace, Low, Small, Medium, Large, Negative';
		} else if ($tes_type == 'pcr') {
			$posnegs[1] = 'Detected/Not Detected';
			$posnegs[2] = 'Low/Normal/High';
			$posnegs[3] = 'Normal/Elevated';
			$posnegs[4] = 'Detected/Indeterminate/Not Detected';
		} else {
			$posnegs[1] = 'Detected/Not Detected';
			$posnegs[2] = 'Low/Normal/High';
			$posnegs[3] = 'Normal/Elevated';
		}
		return $posnegs;
	}
}
