<?php
$location = array();
if ($user_id > 0) {
	$location = get_user_meta($user_id, '_location_fields', true);
}

if (!empty($form_options['text_loc'])) {
	echo '<div>' . stripslashes($form_options['text_loc']) . '</div>';
}
?>
<div id="location_form">
	<div class="frow blabel">

		<div class="col4">
			<label>Street Address</label>
			<input type="text" name="location_fields[address]" value="<?php echo @$location['address'] ?>"
				required maxlength="150" />
		</div>
		<div class="col4">
			<label>City</label>
			<input type="text" name="location_fields[city]" value="<?php echo @$location['city'] ?>" required
				maxlength="100" />
		</div>
		<div class="col4">
			<label>State</label>
			<select name="location_fields[state]" required>
				<?php
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

				if (isset($settings['req_canadian_provices']) && $settings['req_canadian_provices']) {
					$states = array_merge($states, $canada);
				}
				foreach ($states as $statecode => $statetxt) {
					$selected = isset($location['state']) && $location['state'] == $statecode ? 'selected' : '';
					echo '<option value="' . $statecode . '" ' . $selected . '>' . $statetxt . '</option>';
				}
				?>
			</select>

		</div>
		<div class="col4">
			<label>Zip</label>
			<input type="text" name="location_fields[zip]" value="<?php echo @$location['zip'] ?>"
				required maxlength="12" />
		</div>
	</div>
</div>