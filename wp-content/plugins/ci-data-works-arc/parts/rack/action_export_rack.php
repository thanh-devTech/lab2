<?php

header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename=rack.csv');
header('Pragma: no-cache');

$re_ID = $_REQUEST['id'];
$rack = $wpdb->get_row("select * from ci_racks where re_ID = '{$re_ID}' ");

$arr = trrack_display_samples($re_ID);
//var_dump($arr['array']);


$f = fopen('php://output', 'w');
$headers = array('');
for ($i = 1; $i <= 12; $i++) {
	$headers[] = $i;
}
fputcsv($f, $headers);




for ($j = 1; $j <= 8; $j++) {
	$rowdata = array(chr($j + 64));
	for ($i = 1; $i <= 12; $i++) {
		$pos = ($j - 1) * 12 + $i;
		if ($rack_ABC) {
			$pos = ($i - 1) * 8 + $j;
		}
		$ewelld = tr_rack_well_by_position($pos, $rack->re_is_uti_panel);

		$rowdata[] = isset($arr['array'][$ewelld]) ? $arr['array'][$ewelld] : '';
	}
	fputcsv($f, $rowdata);
}

fclose($f);

exit;
