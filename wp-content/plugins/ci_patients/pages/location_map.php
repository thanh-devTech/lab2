<?php
global $wpdb;


$onlylocationkeys   = array();
$pmottcli           = isset($atts['pmottcli']) ? $atts['pmottcli'] : 0;


if (isset($atts['locationkeys'])) {
	foreach (explode(',', $atts['locationkeys']) as $lkey) {
		$lkey = strtoupper(trim($lkey));
		if (!empty($lkey)) {
			$onlylocationkeys[$lkey] = $lkey;
		}
	}
}

if ($pmottcli > 0) {
	//search clinic where office in suboffice
	$sql_cli = "select c.cID 
	        from ci_clinic c 
	        JOIN ci_clinic_manager cm ON cm.ma_cli_ID = c.cID and cm.ma_suboffice = 0
	        JOIN ci_clinic_manager subm ON cm.ma_user_ID = subm.ma_user_ID and subm.ma_suboffice = 1
	        where subm.ma_cli_ID = {$pmottcli} ";
	$cli_ids = $wpdb->get_col($sql_cli);
}

$showed_locations   = true;
$current_location   = false;
$key                = $pa_options['gg_map_key'];

if (empty($key)) {
	$key = 'AIzaSyDbkxGUVm1UqzzYWBGTG-Iil3p8h5hotxA';
}

if (!empty($form_options['text_loc'])) {
	echo '<div>' . stripslashes($form_options['text_loc']) . '</div>';
}

if ($pmottcli > 0) {
	echo '<input type="hidden" name="ref_cli_ID" value="' . $pmottcli . '"/>';
}
$has_location_text = strlen($form_options['location_text']) > 5;
$mapconfig = array(
	'location_text_selected' => nl2br($form_options['location_text_selected']),
	'location_text_color' => $form_options['location_text_color'],
	'no_location_text' => $form_options['no_location_text']
);

?>
<div id="no_location_text"></div>
<script src="https://maps.google.com/maps/api/js?libraries=places&key=<?php echo $key ?>"></script>
<div data-role="page" id="map_result" class="<?php echo $has_location_text ? 'has_location_text' : '' ?>">
	<div data-role="content" style="padding:0;">
		<div id="list_location">
			<ol data-count="<?php echo count($locations) ?>">
				<?php
				$locations_map = array();
				$center = array();
				$i = 0;
				foreach ($locations as $location) {
					if (!empty($onlylocationkeys)) {
						$fkey = strtoupper(substr($location['key'], 0, 1));
						if (!in_array($fkey, $onlylocationkeys)) {
							continue;
						}
						if ($fkey == 'P' && count($cli_ids) > 0 && !in_array($location['cli_ID'], $cli_ids)) {
							continue;
						}
					}

					if (empty($location['lat'])) {
						$location['lat'] = 0;
					}
					if (empty($location['lng'])) {
						$location['lng'] = 0;
					}


					$class = '';
					if (!empty($_REQUEST['location']) && strtolower($location['url']) == strtolower($_REQUEST['location'])) {
						$current_location = $location;
						$class = 'active';
					}

					$pos = $i + 1;
					$location_name = '<b>' . $location['name'] . '</b><br>' . $location['address'];

					$pids = '';
					if ($form_options['enable_pbyloc']) {
						if (!isset($location['pids']) || !is_array($location['pids'])) {
							$location['pids'] = array();
						}
						$pids = implode(',', $location['pids']);
					}
					echo '<li data-num="'.$i.'" data-key="' . $location['key'] . '" data-state="'.$location['state'].'" data-lat="'.$location['lat'].'" data-pids="' . $pids . '" class="location_' . $i . ' ' . $class . '" >';
					echo $location['name'];
					if (!empty($location['desc'])) {
						echo '<div class="loc_desc">' . $location['desc'] . '</div>';
						$location_name .= '<br>' . $location['desc'];
					}
					echo '</li>';
					

					$locations_map[] = array(
						$location_name,
						$location['lat'],
						$location['lng'],
						$pos,
						$location['key'],
						$location['state'],
						$location['address'],
						$location['phone'],
					);

					if ($location['lat'] !== 0 && $location['lng'] !== 0) {
						$center = $location;
					}



					$i++;
				}
				?>
			</ol>
			<?php
			if ($has_location_text) {
				echo '<div class="location_text">';
				echo $form_options['location_text'];
				echo '</div>';
			}
			?>
		</div>
		<div id="map" data-zoom="9" data-lat="<?php echo isset($center['lat']) ? $center['lat'] : '' ?>" data-lng="<?php echo isset($center['lng']) ? $center['lng'] : '' ?>"></div>

	</div>
	<div id="text_below_map"></div>
</div>
<input type="hidden" name="location" required />
<input type="hidden" name="location_url" value="<?php echo isset($_GET['location']) ? $_GET['location'] : '' ?>" />
<div class="clear"></div>
<script>
	// Locations: [title, lat, long, number]
	var locations = <?php echo json_encode($locations_map) ?>;
	var $mapconfig = <?php echo json_encode($mapconfig) ?>;
</script>