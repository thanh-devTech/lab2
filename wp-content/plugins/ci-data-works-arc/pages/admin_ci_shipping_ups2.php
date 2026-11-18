<?php

if(isset($_POST['option'])){
    $options = $_POST['option'];
    update_option('tr_shipping_ups',$options);

    Tr_Session_Class::add('Updated Settings!');
}


$options = get_option('tr_shipping_ups',array());
?>

<div class="wrap">
    <h2>UPS</h2>
    <?php Tr_Session_Class::show()?>
    <form enctype="multipart/form-data" action="" id="mainform" method="post">
        <table class="form-table">
            <tbody><tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_enabled">Realtime Rates</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Realtime Rates</span></legend>
                        <label for="woocommerce_wf_shipping_ups_enabled">
                            <input type="checkbox" <?php checked($options['enabled'])?> value="1" style="" id="woocommerce_wf_shipping_ups_enabled" name="option[enabled]" class=""> Enable</label><br>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_title">UPS Method Title</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS Method Title</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['title']?>" style="" id="woocommerce_wf_shipping_ups_title" name="option[title]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_availability">Method Availability</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Method Availability</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_availability" name="option[availability]" class="select availability">
                            <?php
                            $ctr = array(
                                'all' => 'All Countries',
                                'specific' => 'Specific Countries'
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['availability']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top" id="select_ups_country" style="display: none;">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_countries">Specific Countries</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Specific Countries</span></legend>
                        <select multiple="multiple" class="multiselect chosen_select" name="option[countries][]" id="woocommerce_wf_shipping_ups_countries" style="width: 450px;"  >
                            <?php
                            $ctr = array (
                                'AX' => 'Åland Islands',
                                'AF' => 'Afghanistan',
                                'AL' => 'Albania',
                                'DZ' => 'Algeria',
                                'AS' => 'American Samoa',
                                'AD' => 'Andorra',
                                'AO' => 'Angola',
                                'AI' => 'Anguilla',
                                'AQ' => 'Antarctica',
                                'AG' => 'Antigua and Barbuda',
                                'AR' => 'Argentina',
                                'AM' => 'Armenia',
                                'AW' => 'Aruba',
                                'AU' => 'Australia',
                                'AT' => 'Austria',
                                'AZ' => 'Azerbaijan',
                                'BS' => 'Bahamas',
                                'BH' => 'Bahrain',
                                'BD' => 'Bangladesh',
                                'BB' => 'Barbados',
                                'BY' => 'Belarus',
                                'PW' => 'Belau',
                                'BE' => 'Belgium',
                                'BZ' => 'Belize',
                                'BJ' => 'Benin',
                                'BM' => 'Bermuda',
                                'BT' => 'Bhutan',
                                'BO' => 'Bolivia',
                                'BQ' => 'Bonaire, Saint Eustatius and Saba',
                                'BA' => 'Bosnia and Herzegovina',
                                'BW' => 'Botswana',
                                'BV' => 'Bouvet Island',
                                'BR' => 'Brazil',
                                'IO' => 'British Indian Ocean Territory',
                                'VG' => 'British Virgin Islands',
                                'BN' => 'Brunei',
                                'BG' => 'Bulgaria',
                                'BF' => 'Burkina Faso',
                                'BI' => 'Burundi',
                                'KH' => 'Cambodia',
                                'CM' => 'Cameroon',
                                'CA' => 'Canada',
                                'CV' => 'Cape Verde',
                                'KY' => 'Cayman Islands',
                                'CF' => 'Central African Republic',
                                'TD' => 'Chad',
                                'CL' => 'Chile',
                                'CN' => 'China',
                                'CX' => 'Christmas Island',
                                'CC' => 'Cocos (Keeling) Islands',
                                'CO' => 'Colombia',
                                'KM' => 'Comoros',
                                'CG' => 'Congo (Brazzaville)',
                                'CD' => 'Congo (Kinshasa)',
                                'CK' => 'Cook Islands',
                                'CR' => 'Costa Rica',
                                'HR' => 'Croatia',
                                'CU' => 'Cuba',
                                'CW' => 'Curaçao',
                                'CY' => 'Cyprus',
                                'CZ' => 'Czech Republic',
                                'DK' => 'Denmark',
                                'DJ' => 'Djibouti',
                                'DM' => 'Dominica',
                                'DO' => 'Dominican Republic',
                                'EC' => 'Ecuador',
                                'EG' => 'Egypt',
                                'SV' => 'El Salvador',
                                'GQ' => 'Equatorial Guinea',
                                'ER' => 'Eritrea',
                                'EE' => 'Estonia',
                                'ET' => 'Ethiopia',
                                'FK' => 'Falkland Islands',
                                'FO' => 'Faroe Islands',
                                'FJ' => 'Fiji',
                                'FI' => 'Finland',
                                'FR' => 'France',
                                'GF' => 'French Guiana',
                                'PF' => 'French Polynesia',
                                'TF' => 'French Southern Territories',
                                'GA' => 'Gabon',
                                'GM' => 'Gambia',
                                'GE' => 'Georgia',
                                'DE' => 'Germany',
                                'GH' => 'Ghana',
                                'GI' => 'Gibraltar',
                                'GR' => 'Greece',
                                'GL' => 'Greenland',
                                'GD' => 'Grenada',
                                'GP' => 'Guadeloupe',
                                'GU' => 'Guam',
                                'GT' => 'Guatemala',
                                'GG' => 'Guernsey',
                                'GN' => 'Guinea',
                                'GW' => 'Guinea-Bissau',
                                'GY' => 'Guyana',
                                'HT' => 'Haiti',
                                'HM' => 'Heard Island and McDonald Islands',
                                'HN' => 'Honduras',
                                'HK' => 'Hong Kong',
                                'HU' => 'Hungary',
                                'IS' => 'Iceland',
                                'IN' => 'India',
                                'ID' => 'Indonesia',
                                'IR' => 'Iran',
                                'IQ' => 'Iraq',
                                'IM' => 'Isle of Man',
                                'IL' => 'Israel',
                                'IT' => 'Italy',
                                'CI' => 'Ivory Coast',
                                'JM' => 'Jamaica',
                                'JP' => 'Japan',
                                'JE' => 'Jersey',
                                'JO' => 'Jordan',
                                'KZ' => 'Kazakhstan',
                                'KE' => 'Kenya',
                                'KI' => 'Kiribati',
                                'KW' => 'Kuwait',
                                'KG' => 'Kyrgyzstan',
                                'LA' => 'Laos',
                                'LV' => 'Latvia',
                                'LB' => 'Lebanon',
                                'LS' => 'Lesotho',
                                'LR' => 'Liberia',
                                'LY' => 'Libya',
                                'LI' => 'Liechtenstein',
                                'LT' => 'Lithuania',
                                'LU' => 'Luxembourg',
                                'MO' => 'Macao S.A.R., China',
                                'MK' => 'Macedonia',
                                'MG' => 'Madagascar',
                                'MW' => 'Malawi',
                                'MY' => 'Malaysia',
                                'MV' => 'Maldives',
                                'ML' => 'Mali',
                                'MT' => 'Malta',
                                'MH' => 'Marshall Islands',
                                'MQ' => 'Martinique',
                                'MR' => 'Mauritania',
                                'MU' => 'Mauritius',
                                'YT' => 'Mayotte',
                                'MX' => 'Mexico',
                                'FM' => 'Micronesia',
                                'MD' => 'Moldova',
                                'MC' => 'Monaco',
                                'MN' => 'Mongolia',
                                'ME' => 'Montenegro',
                                'MS' => 'Montserrat',
                                'MA' => 'Morocco',
                                'MZ' => 'Mozambique',
                                'MM' => 'Myanmar',
                                'NA' => 'Namibia',
                                'NR' => 'Nauru',
                                'NP' => 'Nepal',
                                'NL' => 'Netherlands',
                                'AN' => 'Netherlands Antilles',
                                'NC' => 'New Caledonia',
                                'NZ' => 'New Zealand',
                                'NI' => 'Nicaragua',
                                'NE' => 'Niger',
                                'NG' => 'Nigeria',
                                'NU' => 'Niue',
                                'NF' => 'Norfolk Island',
                                'KP' => 'North Korea',
                                'MP' => 'Northern Mariana Islands',
                                'NO' => 'Norway',
                                'OM' => 'Oman',
                                'PK' => 'Pakistan',
                                'PS' => 'Palestinian Territory',
                                'PA' => 'Panama',
                                'PG' => 'Papua New Guinea',
                                'PY' => 'Paraguay',
                                'PE' => 'Peru',
                                'PH' => 'Philippines',
                                'PN' => 'Pitcairn',
                                'PL' => 'Poland',
                                'PT' => 'Portugal',
                                'PR' => 'Puert Rico',
                                'QA' => 'Qatar',
                                'IE' => 'Republic of Ireland',
                                'RE' => 'Reunion',
                                'RO' => 'Romania',
                                'RU' => 'Russia',
                                'RW' => 'Rwanda',
                                'ST' => 'São Tomé and Príncipe',
                                'BL' => 'Saint Barthélemy',
                                'SH' => 'Saint Helena',
                                'KN' => 'Saint Kitts and Nevis',
                                'LC' => 'Saint Lucia',
                                'SX' => 'Saint Martin (Dutch part)',
                                'MF' => 'Saint Martin (French part)',
                                'PM' => 'Saint Pierre and Miquelon',
                                'VC' => 'Saint Vincent and the Grenadines',
                                'WS' => 'Samoa',
                                'SM' => 'San Marino',
                                'SA' => 'Saudi Arabia',
                                'SN' => 'Senegal',
                                'RS' => 'Serbia',
                                'SC' => 'Seychelles',
                                'SL' => 'Sierra Leone',
                                'SG' => 'Singapore',
                                'SK' => 'Slovakia',
                                'SI' => 'Slovenia',
                                'SB' => 'Solomon Islands',
                                'SO' => 'Somalia',
                                'ZA' => 'South Africa',
                                'GS' => 'South Georgia/Sandwich Islands',
                                'KR' => 'South Korea',
                                'SS' => 'South Sudan',
                                'ES' => 'Spain',
                                'LK' => 'Sri Lanka',
                                'SD' => 'Sudan',
                                'SR' => 'Suriname',
                                'SJ' => 'Svalbard and Jan Mayen',
                                'SZ' => 'Swaziland',
                                'SE' => 'Sweden',
                                'CH' => 'Switzerland',
                                'SY' => 'Syria',
                                'TW' => 'Taiwan',
                                'TJ' => 'Tajikistan',
                                'TZ' => 'Tanzania',
                                'TH' => 'Thailand',
                                'TL' => 'Timor-Leste',
                                'TG' => 'Togo',
                                'TK' => 'Tokelau',
                                'TO' => 'Tonga',
                                'TT' => 'Trinidad and Tobago',
                                'TN' => 'Tunisia',
                                'TR' => 'Turkey',
                                'TM' => 'Turkmenistan',
                                'TC' => 'Turks and Caicos Islands',
                                'TV' => 'Tuvalu',
                                'UG' => 'Uganda',
                                'UA' => 'Ukraine',
                                'AE' => 'United Arab Emirates',
                                'GB' => 'United Kingdom (UK)',
                                'US' => 'United States (US)',
                                'UM' => 'United States (US) Minor Outlying Islands',
                                'VI' => 'United States (US) Virgin Islands',
                                'UY' => 'Uruguay',
                                'UZ' => 'Uzbekistan',
                                'VU' => 'Vanuatu',
                                'VA' => 'Vatican',
                                'VE' => 'Venezuela',
                                'VN' => 'Vietnam',
                                'WF' => 'Wallis and Futuna',
                                'EH' => 'Western Sahara',
                                'YE' => 'Yemen',
                                'ZM' => 'Zambia',
                                'ZW' => 'Zimbabwe',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = in_array($c,$options['countries'])? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_debug">Debug Mode</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Debug Mode</span></legend>
                        <label for="woocommerce_wf_shipping_ups_debug">
                            <input type="checkbox" <?php checked($options['debug'])?> value="1" style="" id="woocommerce_wf_shipping_ups_debug" name="option[debug]" class=""> Enable</label><br>
                    </fieldset>
                </td>
            </tr>
            </tbody></table>
        <h3 id="woocommerce_wf_shipping_ups_api" class="wc-settings-sub-title ">Generic API Settings</h3>
        <p>Obtain UPS account credentials by registering on UPS website.</p>
        <table class="form-table">
            <tbody><tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_api_mode">API Mode</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>API Mode</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_api_mode" name="option[api_mode]" class="select ">
                            <?php
                            $ctr = array(
                                'Live' => 'Live',
                                'Test' => 'Test'
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['api_mode']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_ups_user_name">Your Name</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Your Name</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['ups_user_name']?>" style="" id="woocommerce_wf_shipping_ups_ups_user_name" name="option[ups_user_name]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_ups_display_name">Attention Name</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Attention Name</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['ups_display_name']?>" style="" id="woocommerce_wf_shipping_ups_ups_display_name" name="option[ups_display_name]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_user_id">UPS User ID</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS User ID</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['user_id']?>" style="" id="woocommerce_wf_shipping_ups_user_id" name="option[user_id]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_password">UPS Password</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS Password</span></legend>
                        <input type="password" placeholder="" value="<?php echo $options['password']?>"  id="woocommerce_wf_shipping_ups_password" name="option[password]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_access_key">UPS Access Key</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS Access Key</span></legend>
                        <input type="password" placeholder="" value="<?php echo $options['user_id']?>"  id="woocommerce_wf_shipping_ups_access_key" name="option[access_key]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_shipper_number">UPS Account Number</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS Account Number</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['shipper_number']?>" style="" id="woocommerce_wf_shipping_ups_shipper_number" name="option[shipper_number]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_units">Weight/Dimension Units</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Weight/Dimension Units</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_units" name="option[units]" class="select ">
                            <?php
                            $ctr = array(
                                'imperial' => 'LB / IN',
                                'metric' => 'KG / CM'
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['units']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_negotiated">Negotiated Rates</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Negotiated Rates</span></legend>
                        <label for="woocommerce_wf_shipping_ups_negotiated">
                            <input type="checkbox" <?php checked($options['negotiated'])?> value="1" style="" id="woocommerce_wf_shipping_ups_negotiated" name="option[negotiated]" class=""> Enable</label><br>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_insuredvalue">Insurance Option</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Insurance Option</span></legend>
                        <label for="woocommerce_wf_shipping_ups_insuredvalue">
                            <input type="checkbox"  <?php checked($options['insuredvalue'])?> value="1" style="" id="woocommerce_wf_shipping_ups_insuredvalue" name="option[insuredvalue]" class=""> Enable</label><br>
                    </fieldset>
                </td>
            </tr>
            </tbody></table>
        <h3 id="woocommerce_wf_shipping_ups_pickup_destination" class="wc-settings-sub-title ">Pickup and Destination</h3>
        <table class="form-table">
            <tbody><tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_pickup">Pickup</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Pickup</span></legend>
                        <select class="select chosen_select" name="option[pickup]" id="woocommerce_wf_shipping_ups_pickup" style="width: 250px;"  >

                            <?php
                            $ctr = array(
                                '01' => 'Daily Pickup',
                                '03' => 'Customer Counter',
                                '06' => 'One Time Pickup',
                                '07' => 'On Call Air',
                                '19' => 'Letter Center',
                                '20' => 'Air Service Center'
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['pickup']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_customer_classification">Customer Classification</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Customer Classification</span></legend>
                        <select class="select chosen_select" name="option[classification]" id="woocommerce_wf_shipping_ups_customer_classification" style="width: 250px;"  >
                            <?php
                            $ctr = array(
                                'NA' => 'Default',
                                '00' => 'Rates Associated with Shipper Number',
                                '01' => 'Daily Rates',
                                '04' => 'Retail Rates',
                                '53' => 'Standard List Rates',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['classification']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_residential">Residential</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Residential</span></legend>
                        <label for="woocommerce_wf_shipping_ups_residential">
                            <input type="checkbox" <?php checked($options['residential'])?> value="1" style="" id="woocommerce_wf_shipping_ups_residential" name="option[residential]" class=""> Ship to address is Residential.</label><br>
                    </fieldset>
                </td>
            </tr>
            </tbody></table>
        <h3 id="woocommerce_wf_shipping_ups_label-settings" class="wc-settings-sub-title ">Label Printing API Settings</h3>
        <table class="form-table">
            <tbody><tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_disble_ups_print_label">Label Printing</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Label Printing</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_disble_ups_print_label" name="option[disble_ups_print_label]" class="select ">
                            <option selected="selected" value="no">Enable</option>
                            <option value="yes">Disable</option>
                            <?php
                            $ctr = array(
                                'no' => 'Enable',
                                'yes' => 'Disable',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['disble_ups_print_label']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_print_label_type">Print Label Type</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Print Label Type</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_print_label_type" name="option[print_label_type]" class="select ">

                            <?php
                            $ctr = array(
                                'gif' => 'GIF',
                                'png' => 'PNG',
                                'zpl' => 'ZPL',
                                'epl' => 'EPL',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['print_label_type']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_show_label_in_browser">Display Label in Browser</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Display Label in Browser</span></legend>
                        <label for="woocommerce_wf_shipping_ups_show_label_in_browser">
                            <input type="checkbox" <?php checked($options['show_label_in_browser'])?> value="1" style="" id="woocommerce_wf_shipping_ups_show_label_in_browser" name="option[show_label_in_browser]" class=""> Enable</label><br>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_disble_shipment_tracking">Shipment Tracking</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Shipment Tracking</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_disble_shipment_tracking" name="option[disble_shipment_tracking]" class="select ">
                            <?php
                            $ctr = array(
                                'TrueForCustomer' => 'Disable for Customer',
                                'False' => 'Enable',
                                'True' => 'Disable',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['disble_shipment_tracking']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_ship_from_address">Ship From Address Preference</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Ship From Address Preference</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_ship_from_address" name="option[ship_from_address]" class="select ">
                            <option value="origin_address">Origin Address</option>
                            <option selected="selected" value="billing_address">Billing Address</option>
                            <?php
                            $ctr = array(
                                'origin_address' => 'Origin Address',
                                'billing_address' => 'Billing Address',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['ship_from_address']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_origin_addressline">Origin Address</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Origin Address</span></legend>
                        <input type="text" placeholder="" value="Apple Inc." style="" id="woocommerce_wf_shipping_ups_origin_addressline" name="option[origin_addressline]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_origin_city">Origin City</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Origin City</span></legend>
                        <input type="text" placeholder="" value="manasas" style="" id="woocommerce_wf_shipping_ups_origin_city" name="option[origin_city]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="origin_country">Origin Country</label>
                </th>
                <td class="forminp"><select name="option[origin_country_state]" id="woocommerce_ups_origin_country_state" style="width: 250px;" data-placeholder="Choose a country&hellip;" title="Country" class="chosen_select">
                        <?php
                        $opts = array (
                            'AX' => 'Åland Islands',
                            'AF' => 'Afghanistan',
                            'AL' => 'Albania',
                            'DZ' => 'Algeria',
                            'AS' => 'American Samoa',
                            'AD' => 'Andorra',
                            'AO' => 'Angola',
                            'AI' => 'Anguilla',
                            'AQ' => 'Antarctica',
                            'AG' => 'Antigua and Barbuda',
                            '' => '',
                            'AR:C' => 'Argentina — Ciudad Autónoma de Buenos Aires',
                            'AR:B' => 'Argentina — Buenos Aires',
                            'AR:K' => 'Argentina — Catamarca',
                            'AR:H' => 'Argentina — Chaco',
                            'AR:U' => 'Argentina — Chubut',
                            'AR:X' => 'Argentina — Córdoba',
                            'AR:W' => 'Argentina — Corrientes',
                            'AR:E' => 'Argentina — Entre Ríos',
                            'AR:P' => 'Argentina — Formosa',
                            'AR:Y' => 'Argentina — Jujuy',
                            'AR:L' => 'Argentina — La Pampa',
                            'AR:F' => 'Argentina — La Rioja',
                            'AR:M' => 'Argentina — Mendoza',
                            'AR:N' => 'Argentina — Misiones',
                            'AR:Q' => 'Argentina — Neuquén',
                            'AR:R' => 'Argentina — Río Negro',
                            'AR:A' => 'Argentina — Salta',
                            'AR:J' => 'Argentina — San Juan',
                            'AR:D' => 'Argentina — San Luis',
                            'AR:Z' => 'Argentina — Santa Cruz',
                            'AR:S' => 'Argentina — Santa Fe',
                            'AR:G' => 'Argentina — Santiago del Estero',
                            'AR:V' => 'Argentina — Tierra del Fuego',
                            'AR:T' => 'Argentina — Tucumán',
                            'AM' => 'Armenia',
                            'AW' => 'Aruba',
                            'AU:ACT' => 'Australia — Australian Capital Territory',
                            'AU:NSW' => 'Australia — New South Wales',
                            'AU:NT' => 'Australia — Northern Territory',
                            'AU:QLD' => 'Australia — Queensland',
                            'AU:SA' => 'Australia — South Australia',
                            'AU:TAS' => 'Australia — Tasmania',
                            'AU:VIC' => 'Australia — Victoria',
                            'AU:WA' => 'Australia — Western Australia',
                            'AT' => 'Austria',
                            'AZ' => 'Azerbaijan',
                            'BS' => 'Bahamas',
                            'BH' => 'Bahrain',
                            'BD:BAG' => 'Bangladesh — Bagerhat',
                            'BD:BAN' => 'Bangladesh — Bandarban',
                            'BD:BAR' => 'Bangladesh — Barguna',
                            'BD:BARI' => 'Bangladesh — Barisal',
                            'BD:BHO' => 'Bangladesh — Bhola',
                            'BD:BOG' => 'Bangladesh — Bogra',
                            'BD:BRA' => 'Bangladesh — Brahmanbaria',
                            'BD:CHA' => 'Bangladesh — Chandpur',
                            'BD:CHI' => 'Bangladesh — Chittagong',
                            'BD:CHU' => 'Bangladesh — Chuadanga',
                            'BD:COM' => 'Bangladesh — Comilla',
                            'BD:COX' => 'Bangladesh — Cox\'s Bazar',
                            'BD:DHA' => 'Bangladesh — Dhaka',
                            'BD:DIN' => 'Bangladesh — Dinajpur',
                            'BD:FAR' => 'Bangladesh — Faridpur',
                            'BD:FEN' => 'Bangladesh — Feni',
                            'BD:GAI' => 'Bangladesh — Gaibandha',
                            'BD:GAZI' => 'Bangladesh — Gazipur',
                            'BD:GOP' => 'Bangladesh — Gopalganj',
                            'BD:HAB' => 'Bangladesh — Habiganj',
                            'BD:JAM' => 'Bangladesh — Jamalpur',
                            'BD:JES' => 'Bangladesh — Jessore',
                            'BD:JHA' => 'Bangladesh — Jhalokati',
                            'BD:JHE' => 'Bangladesh — Jhenaidah',
                            'BD:JOY' => 'Bangladesh — Joypurhat',
                            'BD:KHA' => 'Bangladesh — Khagrachhari',
                            'BD:KHU' => 'Bangladesh — Khulna',
                            'BD:KIS' => 'Bangladesh — Kishoreganj',
                            'BD:KUR' => 'Bangladesh — Kurigram',
                            'BD:KUS' => 'Bangladesh — Kushtia',
                            'BD:LAK' => 'Bangladesh — Lakshmipur',
                            'BD:LAL' => 'Bangladesh — Lalmonirhat',
                            'BD:MAD' => 'Bangladesh — Madaripur',
                            'BD:MAG' => 'Bangladesh — Magura',
                            'BD:MAN' => 'Bangladesh — Manikganj',
                            'BD:MEH' => 'Bangladesh — Meherpur',
                            'BD:MOU' => 'Bangladesh — Moulvibazar',
                            'BD:MUN' => 'Bangladesh — Munshiganj',
                            'BD:MYM' => 'Bangladesh — Mymensingh',
                            'BD:NAO' => 'Bangladesh — Naogaon',
                            'BD:NAR' => 'Bangladesh — Narail',
                            'BD:NARG' => 'Bangladesh — Narayanganj',
                            'BD:NARD' => 'Bangladesh — Narsingdi',
                            'BD:NAT' => 'Bangladesh — Natore',
                            'BD:NAW' => 'Bangladesh — Nawabganj',
                            'BD:NET' => 'Bangladesh — Netrakona',
                            'BD:NIL' => 'Bangladesh — Nilphamari',
                            'BD:NOA' => 'Bangladesh — Noakhali',
                            'BD:PAB' => 'Bangladesh — Pabna',
                            'BD:PAN' => 'Bangladesh — Panchagarh',
                            'BD:PAT' => 'Bangladesh — Patuakhali',
                            'BD:PIR' => 'Bangladesh — Pirojpur',
                            'BD:RAJB' => 'Bangladesh — Rajbari',
                            'BD:RAJ' => 'Bangladesh — Rajshahi',
                            'BD:RAN' => 'Bangladesh — Rangamati',
                            'BD:RANP' => 'Bangladesh — Rangpur',
                            'BD:SAT' => 'Bangladesh — Satkhira',
                            'BD:SHA' => 'Bangladesh — Shariatpur',
                            'BD:SHE' => 'Bangladesh — Sherpur',
                            'BD:SIR' => 'Bangladesh — Sirajganj',
                            'BD:SUN' => 'Bangladesh — Sunamganj',
                            'BD:SYL' => 'Bangladesh — Sylhet',
                            'BD:TAN' => 'Bangladesh — Tangail',
                            'BD:THA' => 'Bangladesh — Thakurgaon',
                            'BB' => 'Barbados',
                            'BY' => 'Belarus',
                            'PW' => 'Belau',
                            'BE' => 'Belgium',
                            'BZ' => 'Belize',
                            'BJ' => 'Benin',
                            'BM' => 'Bermuda',
                            'BT' => 'Bhutan',
                            'BO' => 'Bolivia',
                            'BQ' => 'Bonaire, Saint Eustatius and Saba',
                            'BA' => 'Bosnia and Herzegovina',
                            'BW' => 'Botswana',
                            'BV' => 'Bouvet Island',
                            'BR:AC' => 'Brazil — Acre',
                            'BR:AL' => 'Brazil — Alagoas',
                            'BR:AP' => 'Brazil — Amapá',
                            'BR:AM' => 'Brazil — Amazonas',
                            'BR:BA' => 'Brazil — Bahia',
                            'BR:CE' => 'Brazil — Ceará',
                            'BR:DF' => 'Brazil — Distrito Federal',
                            'BR:ES' => 'Brazil — Espírito Santo',
                            'BR:GO' => 'Brazil — Goiás',
                            'BR:MA' => 'Brazil — Maranhão',
                            'BR:MT' => 'Brazil — Mato Grosso',
                            'BR:MS' => 'Brazil — Mato Grosso do Sul',
                            'BR:MG' => 'Brazil — Minas Gerais',
                            'BR:PA' => 'Brazil — Pará',
                            'BR:PB' => 'Brazil — Paraíba',
                            'BR:PR' => 'Brazil — Paraná',
                            'BR:PE' => 'Brazil — Pernambuco',
                            'BR:PI' => 'Brazil — Piauí',
                            'BR:RJ' => 'Brazil — Rio de Janeiro',
                            'BR:RN' => 'Brazil — Rio Grande do Norte',
                            'BR:RS' => 'Brazil — Rio Grande do Sul',
                            'BR:RO' => 'Brazil — Rondônia',
                            'BR:RR' => 'Brazil — Roraima',
                            'BR:SC' => 'Brazil — Santa Catarina',
                            'BR:SP' => 'Brazil — São Paulo',
                            'BR:SE' => 'Brazil — Sergipe',
                            'BR:TO' => 'Brazil — Tocantins',
                            'IO' => 'British Indian Ocean Territory',
                            'VG' => 'British Virgin Islands',
                            'BN' => 'Brunei',
                            'BG:BG-01' => 'Bulgaria — Blagoevgrad',
                            'BG:BG-02' => 'Bulgaria — Burgas',
                            'BG:BG-08' => 'Bulgaria — Dobrich',
                            'BG:BG-07' => 'Bulgaria — Gabrovo',
                            'BG:BG-26' => 'Bulgaria — Haskovo',
                            'BG:BG-09' => 'Bulgaria — Kardzhali',
                            'BG:BG-10' => 'Bulgaria — Kyustendil',
                            'BG:BG-11' => 'Bulgaria — Lovech',
                            'BG:BG-12' => 'Bulgaria — Montana',
                            'BG:BG-13' => 'Bulgaria — Pazardzhik',
                            'BG:BG-14' => 'Bulgaria — Pernik',
                            'BG:BG-15' => 'Bulgaria — Pleven',
                            'BG:BG-16' => 'Bulgaria — Plovdiv',
                            'BG:BG-17' => 'Bulgaria — Razgrad',
                            'BG:BG-18' => 'Bulgaria — Ruse',
                            'BG:BG-27' => 'Bulgaria — Shumen',
                            'BG:BG-19' => 'Bulgaria — Silistra',
                            'BG:BG-20' => 'Bulgaria — Sliven',
                            'BG:BG-21' => 'Bulgaria — Smolyan',
                            'BG:BG-23' => 'Bulgaria — Sofia',
                            'BG:BG-22' => 'Bulgaria — Sofia-Grad',
                            'BG:BG-24' => 'Bulgaria — Stara Zagora',
                            'BG:BG-25' => 'Bulgaria — Targovishte',
                            'BG:BG-03' => 'Bulgaria — Varna',
                            'BG:BG-04' => 'Bulgaria — Veliko Tarnovo',
                            'BG:BG-05' => 'Bulgaria — Vidin',
                            'BG:BG-06' => 'Bulgaria — Vratsa',
                            'BG:BG-28' => 'Bulgaria — Yambol',
                            'BF' => 'Burkina Faso',
                            'BI' => 'Burundi',
                            'KH' => 'Cambodia',
                            'CM' => 'Cameroon',
                            'CA:AB' => 'Canada — Alberta',
                            'CA:BC' => 'Canada — British Columbia',
                            'CA:MB' => 'Canada — Manitoba',
                            'CA:NB' => 'Canada — New Brunswick',
                            'CA:NL' => 'Canada — Newfoundland and Labrador',
                            'CA:NT' => 'Canada — Northwest Territories',
                            'CA:NS' => 'Canada — Nova Scotia',
                            'CA:NU' => 'Canada — Nunavut',
                            'CA:ON' => 'Canada — Ontario',
                            'CA:PE' => 'Canada — Prince Edward Island',
                            'CA:QC' => 'Canada — Quebec',
                            'CA:SK' => 'Canada — Saskatchewan',
                            'CA:YT' => 'Canada — Yukon Territory',
                            'CV' => 'Cape Verde',
                            'KY' => 'Cayman Islands',
                            'CF' => 'Central African Republic',
                            'TD' => 'Chad',
                            'CL' => 'Chile',
                            'CN:CN1' => 'China — Yunnan / 云南',
                            'CN:CN2' => 'China — Beijing / 北京',
                            'CN:CN3' => 'China — Tianjin / 天津',
                            'CN:CN4' => 'China — Hebei / 河北',
                            'CN:CN5' => 'China — Shanxi / 山西',
                            'CN:CN6' => 'China — Inner Mongolia / 內蒙古',
                            'CN:CN7' => 'China — Liaoning / 辽宁',
                            'CN:CN8' => 'China — Jilin / 吉林',
                            'CN:CN9' => 'China — Heilongjiang / 黑龙江',
                            'CN:CN10' => 'China — Shanghai / 上海',
                            'CN:CN11' => 'China — Jiangsu / 江苏',
                            'CN:CN12' => 'China — Zhejiang / 浙江',
                            'CN:CN13' => 'China — Anhui / 安徽',
                            'CN:CN14' => 'China — Fujian / 福建',
                            'CN:CN15' => 'China — Jiangxi / 江西',
                            'CN:CN16' => 'China — Shandong / 山东',
                            'CN:CN17' => 'China — Henan / 河南',
                            'CN:CN18' => 'China — Hubei / 湖北',
                            'CN:CN19' => 'China — Hunan / 湖南',
                            'CN:CN20' => 'China — Guangdong / 广东',
                            'CN:CN21' => 'China — Guangxi Zhuang / 广西壮族',
                            'CN:CN22' => 'China — Hainan / 海南',
                            'CN:CN23' => 'China — Chongqing / 重庆',
                            'CN:CN24' => 'China — Sichuan / 四川',
                            'CN:CN25' => 'China — Guizhou / 贵州',
                            'CN:CN26' => 'China — Shaanxi / 陕西',
                            'CN:CN27' => 'China — Gansu / 甘肃',
                            'CN:CN28' => 'China — Qinghai / 青海',
                            'CN:CN29' => 'China — Ningxia Hui / 宁夏',
                            'CN:CN30' => 'China — Macau / 澳门',
                            'CN:CN31' => 'China — Tibet / 西藏',
                            'CN:CN32' => 'China — Xinjiang / 新疆',
                            'CX' => 'Christmas Island',
                            'CC' => 'Cocos (Keeling) Islands',
                            'CO' => 'Colombia',
                            'KM' => 'Comoros',
                            'CG' => 'Congo (Brazzaville)',
                            'CD' => 'Congo (Kinshasa)',
                            'CK' => 'Cook Islands',
                            'CR' => 'Costa Rica',
                            'HR' => 'Croatia',
                            'CU' => 'Cuba',
                            'CW' => 'Curaçao',
                            'CY' => 'Cyprus',
                            'CZ' => 'Czech Republic',
                            'DK' => 'Denmark',
                            'DJ' => 'Djibouti',
                            'DM' => 'Dominica',
                            'DO' => 'Dominican Republic',
                            'EC' => 'Ecuador',
                            'EG' => 'Egypt',
                            'SV' => 'El Salvador',
                            'GQ' => 'Equatorial Guinea',
                            'ER' => 'Eritrea',
                            'EE' => 'Estonia',
                            'ET' => 'Ethiopia',
                            'FK' => 'Falkland Islands',
                            'FO' => 'Faroe Islands',
                            'FJ' => 'Fiji',
                            'FI' => 'Finland',
                            'FR' => 'France',
                            'GF' => 'French Guiana',
                            'PF' => 'French Polynesia',
                            'TF' => 'French Southern Territories',
                            'GA' => 'Gabon',
                            'GM' => 'Gambia',
                            'GE' => 'Georgia',
                            'DE' => 'Germany',
                            'GH' => 'Ghana',
                            'GI' => 'Gibraltar',
                            'GR:I' => 'Greece — Αττική',
                            'GR:A' => 'Greece — Ανατολική Μακεδονία και Θράκη',
                            'GR:B' => 'Greece — Κεντρική Μακεδονία',
                            'GR:C' => 'Greece — Δυτική Μακεδονία',
                            'GR:D' => 'Greece — Ήπειρος',
                            'GR:E' => 'Greece — Θεσσαλία',
                            'GR:F' => 'Greece — Ιόνιοι Νήσοι',
                            'GR:G' => 'Greece — Δυτική Ελλάδα',
                            'GR:H' => 'Greece — Στερεά Ελλάδα',
                            'GR:J' => 'Greece — Πελοπόννησος',
                            'GR:K' => 'Greece — Βόρειο Αιγαίο',
                            'GR:L' => 'Greece — Νότιο Αιγαίο',
                            'GR:M' => 'Greece — Κρήτη',
                            'GL' => 'Greenland',
                            'GD' => 'Grenada',
                            'GP' => 'Guadeloupe',
                            'GU' => 'Guam',
                            'GT' => 'Guatemala',
                            'GG' => 'Guernsey',
                            'GN' => 'Guinea',
                            'GW' => 'Guinea-Bissau',
                            'GY' => 'Guyana',
                            'HT' => 'Haiti',
                            'HM' => 'Heard Island and McDonald Islands',
                            'HN' => 'Honduras',
                            'HK:HONG KONG' => 'Hong Kong — Hong Kong Island',
                            'HK:KOWLOON' => 'Hong Kong — Kowloon',
                            'HK:NEW TERRITORIES' => 'Hong Kong — New Territories',
                            'HU:BK' => 'Hungary — Bács-Kiskun',
                            'HU:BE' => 'Hungary — Békés',
                            'HU:BA' => 'Hungary — Baranya',
                            'HU:BZ' => 'Hungary — Borsod-Abaúj-Zemplén',
                            'HU:BU' => 'Hungary — Budapest',
                            'HU:CS' => 'Hungary — Csongrád',
                            'HU:FE' => 'Hungary — Fejér',
                            'HU:GS' => 'Hungary — Győr-Moson-Sopron',
                            'HU:HB' => 'Hungary — Hajdú-Bihar',
                            'HU:HE' => 'Hungary — Heves',
                            'HU:JN' => 'Hungary — Jász-Nagykun-Szolnok',
                            'HU:KE' => 'Hungary — Komárom-Esztergom',
                            'HU:NO' => 'Hungary — Nógrád',
                            'HU:PE' => 'Hungary — Pest',
                            'HU:SO' => 'Hungary — Somogy',
                            'HU:SZ' => 'Hungary — Szabolcs-Szatmár-Bereg',
                            'HU:TO' => 'Hungary — Tolna',
                            'HU:VA' => 'Hungary — Vas',
                            'HU:VE' => 'Hungary — Veszprém',
                            'HU:ZA' => 'Hungary — Zala',
                            'IS' => 'Iceland',
                            'IN:AP' => 'India — Andhra Pradesh',
                            'IN:AR' => 'India — Arunachal Pradesh',
                            'IN:AS' => 'India — Assam',
                            'IN:BR' => 'India — Bihar',
                            'IN:CT' => 'India — Chhattisgarh',
                            'IN:GA' => 'India — Goa',
                            'IN:GJ' => 'India — Gujarat',
                            'IN:HR' => 'India — Haryana',
                            'IN:HP' => 'India — Himachal Pradesh',
                            'IN:JK' => 'India — Jammu and Kashmir',
                            'IN:JH' => 'India — Jharkhand',
                            'IN:KA' => 'India — Karnataka',
                            'IN:KL' => 'India — Kerala',
                            'IN:MP' => 'India — Madhya Pradesh',
                            'IN:MH' => 'India — Maharashtra',
                            'IN:MN' => 'India — Manipur',
                            'IN:ML' => 'India — Meghalaya',
                            'IN:MZ' => 'India — Mizoram',
                            'IN:NL' => 'India — Nagaland',
                            'IN:OR' => 'India — Orissa',
                            'IN:PB' => 'India — Punjab',
                            'IN:RJ' => 'India — Rajasthan',
                            'IN:SK' => 'India — Sikkim',
                            'IN:TN' => 'India — Tamil Nadu',
                            'IN:TS' => 'India — Telangana',
                            'IN:TR' => 'India — Tripura',
                            'IN:UK' => 'India — Uttarakhand',
                            'IN:UP' => 'India — Uttar Pradesh',
                            'IN:WB' => 'India — West Bengal',
                            'IN:AN' => 'India — Andaman and Nicobar Islands',
                            'IN:CH' => 'India — Chandigarh',
                            'IN:DN' => 'India — Dadar and Nagar Haveli',
                            'IN:DD' => 'India — Daman and Diu',
                            'IN:DL' => 'India — Delhi',
                            'IN:LD' => 'India — Lakshadeep',
                            'IN:PY' => 'India — Pondicherry (Puducherry)',
                            'ID:AC' => 'Indonesia — Daerah Istimewa Aceh',
                            'ID:SU' => 'Indonesia — Sumatera Utara',
                            'ID:SB' => 'Indonesia — Sumatera Barat',
                            'ID:RI' => 'Indonesia — Riau',
                            'ID:KR' => 'Indonesia — Kepulauan Riau',
                            'ID:JA' => 'Indonesia — Jambi',
                            'ID:SS' => 'Indonesia — Sumatera Selatan',
                            'ID:BB' => 'Indonesia — Bangka Belitung',
                            'ID:BE' => 'Indonesia — Bengkulu',
                            'ID:LA' => 'Indonesia — Lampung',
                            'ID:JK' => 'Indonesia — DKI Jakarta',
                            'ID:JB' => 'Indonesia — Jawa Barat',
                            'ID:BT' => 'Indonesia — Banten',
                            'ID:JT' => 'Indonesia — Jawa Tengah',
                            'ID:JI' => 'Indonesia — Jawa Timur',
                            'ID:YO' => 'Indonesia — Daerah Istimewa Yogyakarta',
                            'ID:BA' => 'Indonesia — Bali',
                            'ID:NB' => 'Indonesia — Nusa Tenggara Barat',
                            'ID:NT' => 'Indonesia — Nusa Tenggara Timur',
                            'ID:KB' => 'Indonesia — Kalimantan Barat',
                            'ID:KT' => 'Indonesia — Kalimantan Tengah',
                            'ID:KI' => 'Indonesia — Kalimantan Timur',
                            'ID:KS' => 'Indonesia — Kalimantan Selatan',
                            'ID:KU' => 'Indonesia — Kalimantan Utara',
                            'ID:SA' => 'Indonesia — Sulawesi Utara',
                            'ID:ST' => 'Indonesia — Sulawesi Tengah',
                            'ID:SG' => 'Indonesia — Sulawesi Tenggara',
                            'ID:SR' => 'Indonesia — Sulawesi Barat',
                            'ID:SN' => 'Indonesia — Sulawesi Selatan',
                            'ID:GO' => 'Indonesia — Gorontalo',
                            'ID:MA' => 'Indonesia — Maluku',
                            'ID:MU' => 'Indonesia — Maluku Utara',
                            'ID:PA' => 'Indonesia — Papua',
                            'ID:PB' => 'Indonesia — Papua Barat',
                            'IR:KHZ' => 'Iran — Khuzestan  (خوزستان)',
                            'IR:THR' => 'Iran — Tehran  (تهران)',
                            'IR:ILM' => 'Iran — Ilaam (ایلام)',
                            'IR:BHR' => 'Iran — Bushehr (بوشهر)',
                            'IR:ADL' => 'Iran — Ardabil (اردبیل)',
                            'IR:ESF' => 'Iran — Isfahan (اصفهان)',
                            'IR:YZD' => 'Iran — Yazd (یزد)',
                            'IR:KRH' => 'Iran — Kermanshah (کرمانشاه)',
                            'IR:KRN' => 'Iran — Kerman (کرمان)',
                            'IR:HDN' => 'Iran — Hamadan (همدان)',
                            'IR:GZN' => 'Iran — Ghazvin (قزوین)',
                            'IR:ZJN' => 'Iran — Zanjan (زنجان)',
                            'IR:LRS' => 'Iran — Luristan (لرستان)',
                            'IR:ABZ' => 'Iran — Alborz (البرز)',
                            'IR:EAZ' => 'Iran — East Azarbaijan (آذربایجان شرقی)',
                            'IR:WAZ' => 'Iran — West Azarbaijan (آذربایجان غربی)',
                            'IR:CHB' => 'Iran — Chaharmahal and Bakhtiari (چهارمحال و بختیاری)',
                            'IR:SKH' => 'Iran — South Khorasan (خراسان جنوبی)',
                            'IR:RKH' => 'Iran — Razavi Khorasan (خراسان رضوی)',
                            'IR:NKH' => 'Iran — North Khorasan (خراسان جنوبی)',
                            'IR:SMN' => 'Iran — Semnan (سمنان)',
                            'IR:FRS' => 'Iran — Fars (فارس)',
                            'IR:QHM' => 'Iran — Qom (قم)',
                            'IR:KRD' => 'Iran — Kurdistan / کردستان)',
                            'IR:KBD' => 'Iran — Kohgiluyeh and BoyerAhmad (کهگیلوییه و بویراحمد)',
                            'IR:GLS' => 'Iran — Golestan (گلستان)',
                            'IR:GIL' => 'Iran — Gilan (گیلان)',
                            'IR:MZN' => 'Iran — Mazandaran (مازندران)',
                            'IR:MKZ' => 'Iran — Markazi (مرکزی)',
                            'IR:HRZ' => 'Iran — Hormozgan (هرمزگان)',
                            'IR:SBN' => 'Iran — Sistan and Baluchestan (سیستان و بلوچستان)',
                            'IQ' => 'Iraq',
                            'IM' => 'Isle of Man',
                            'IL' => 'Israel',
                            'IT:AG' => 'Italy — Agrigento',
                            'IT:AL' => 'Italy — Alessandria',
                            'IT:AN' => 'Italy — Ancona',
                            'IT:AO' => 'Italy — Aosta',
                            'IT:AR' => 'Italy — Arezzo',
                            'IT:AP' => 'Italy — Ascoli Piceno',
                            'IT:AT' => 'Italy — Asti',
                            'IT:AV' => 'Italy — Avellino',
                            'IT:BA' => 'Italy — Bari',
                            'IT:BT' => 'Italy — Barletta-Andria-Trani',
                            'IT:BL' => 'Italy — Belluno',
                            'IT:BN' => 'Italy — Benevento',
                            'IT:BG' => 'Italy — Bergamo',
                            'IT:BI' => 'Italy — Biella',
                            'IT:BO' => 'Italy — Bologna',
                            'IT:BZ' => 'Italy — Bolzano',
                            'IT:BS' => 'Italy — Brescia',
                            'IT:BR' => 'Italy — Brindisi',
                            'IT:CA' => 'Italy — Cagliari',
                            'IT:CL' => 'Italy — Caltanissetta',
                            'IT:CB' => 'Italy — Campobasso',
                            'IT:CI' => 'Italy — Carbonia-Iglesias',
                            'IT:CE' => 'Italy — Caserta',
                            'IT:CT' => 'Italy — Catania',
                            'IT:CZ' => 'Italy — Catanzaro',
                            'IT:CH' => 'Italy — Chieti',
                            'IT:CO' => 'Italy — Como',
                            'IT:CS' => 'Italy — Cosenza',
                            'IT:CR' => 'Italy — Cremona',
                            'IT:KR' => 'Italy — Crotone',
                            'IT:CN' => 'Italy — Cuneo',
                            'IT:EN' => 'Italy — Enna',
                            'IT:FM' => 'Italy — Fermo',
                            'IT:FE' => 'Italy — Ferrara',
                            'IT:FI' => 'Italy — Firenze',
                            'IT:FG' => 'Italy — Foggia',
                            'IT:FC' => 'Italy — Forlì-Cesena',
                            'IT:FR' => 'Italy — Frosinone',
                            'IT:GE' => 'Italy — Genova',
                            'IT:GO' => 'Italy — Gorizia',
                            'IT:GR' => 'Italy — Grosseto',
                            'IT:IM' => 'Italy — Imperia',
                            'IT:IS' => 'Italy — Isernia',
                            'IT:SP' => 'Italy — La Spezia',
                            'IT:AQ' => 'Italy — L\'Aquila',
                            'IT:LT' => 'Italy — Latina',
                            'IT:LE' => 'Italy — Lecce',
                            'IT:LC' => 'Italy — Lecco',
                            'IT:LI' => 'Italy — Livorno',
                            'IT:LO' => 'Italy — Lodi',
                            'IT:LU' => 'Italy — Lucca',
                            'IT:MC' => 'Italy — Macerata',
                            'IT:MN' => 'Italy — Mantova',
                            'IT:MS' => 'Italy — Massa-Carrara',
                            'IT:MT' => 'Italy — Matera',
                            'IT:ME' => 'Italy — Messina',
                            'IT:MI' => 'Italy — Milano',
                            'IT:MO' => 'Italy — Modena',
                            'IT:MB' => 'Italy — Monza e della Brianza',
                            'IT:NA' => 'Italy — Napoli',
                            'IT:NO' => 'Italy — Novara',
                            'IT:NU' => 'Italy — Nuoro',
                            'IT:OT' => 'Italy — Olbia-Tempio',
                            'IT:OR' => 'Italy — Oristano',
                            'IT:PD' => 'Italy — Padova',
                            'IT:PA' => 'Italy — Palermo',
                            'IT:PR' => 'Italy — Parma',
                            'IT:PV' => 'Italy — Pavia',
                            'IT:PG' => 'Italy — Perugia',
                            'IT:PU' => 'Italy — Pesaro e Urbino',
                            'IT:PE' => 'Italy — Pescara',
                            'IT:PC' => 'Italy — Piacenza',
                            'IT:PI' => 'Italy — Pisa',
                            'IT:PT' => 'Italy — Pistoia',
                            'IT:PN' => 'Italy — Pordenone',
                            'IT:PZ' => 'Italy — Potenza',
                            'IT:PO' => 'Italy — Prato',
                            'IT:RG' => 'Italy — Ragusa',
                            'IT:RA' => 'Italy — Ravenna',
                            'IT:RC' => 'Italy — Reggio Calabria',
                            'IT:RE' => 'Italy — Reggio Emilia',
                            'IT:RI' => 'Italy — Rieti',
                            'IT:RN' => 'Italy — Rimini',
                            'IT:RM' => 'Italy — Roma',
                            'IT:RO' => 'Italy — Rovigo',
                            'IT:SA' => 'Italy — Salerno',
                            'IT:VS' => 'Italy — Medio Campidano',
                            'IT:SS' => 'Italy — Sassari',
                            'IT:SV' => 'Italy — Savona',
                            'IT:SI' => 'Italy — Siena',
                            'IT:SR' => 'Italy — Siracusa',
                            'IT:SO' => 'Italy — Sondrio',
                            'IT:TA' => 'Italy — Taranto',
                            'IT:TE' => 'Italy — Teramo',
                            'IT:TR' => 'Italy — Terni',
                            'IT:TO' => 'Italy — Torino',
                            'IT:OG' => 'Italy — Ogliastra',
                            'IT:TP' => 'Italy — Trapani',
                            'IT:TN' => 'Italy — Trento',
                            'IT:TV' => 'Italy — Treviso',
                            'IT:TS' => 'Italy — Trieste',
                            'IT:UD' => 'Italy — Udine',
                            'IT:VA' => 'Italy — Varese',
                            'IT:VE' => 'Italy — Venezia',
                            'IT:VB' => 'Italy — Verbano-Cusio-Ossola',
                            'IT:VC' => 'Italy — Vercelli',
                            'IT:VR' => 'Italy — Verona',
                            'IT:VV' => 'Italy — Vibo Valentia',
                            'IT:VI' => 'Italy — Vicenza',
                            'IT:VT' => 'Italy — Viterbo',
                            'CI' => 'Ivory Coast',
                            'JM' => 'Jamaica',
                            'JP:JP01' => 'Japan — Hokkaido',
                            'JP:JP02' => 'Japan — Aomori',
                            'JP:JP03' => 'Japan — Iwate',
                            'JP:JP04' => 'Japan — Miyagi',
                            'JP:JP05' => 'Japan — Akita',
                            'JP:JP06' => 'Japan — Yamagata',
                            'JP:JP07' => 'Japan — Fukushima',
                            'JP:JP08' => 'Japan — Ibaraki',
                            'JP:JP09' => 'Japan — Tochigi',
                            'JP:JP10' => 'Japan — Gunma',
                            'JP:JP11' => 'Japan — Saitama',
                            'JP:JP12' => 'Japan — Chiba',
                            'JP:JP13' => 'Japan — Tokyo',
                            'JP:JP14' => 'Japan — Kanagawa',
                            'JP:JP15' => 'Japan — Niigata',
                            'JP:JP16' => 'Japan — Toyama',
                            'JP:JP17' => 'Japan — Ishikawa',
                            'JP:JP18' => 'Japan — Fukui',
                            'JP:JP19' => 'Japan — Yamanashi',
                            'JP:JP20' => 'Japan — Nagano',
                            'JP:JP21' => 'Japan — Gifu',
                            'JP:JP22' => 'Japan — Shizuoka',
                            'JP:JP23' => 'Japan — Aichi',
                            'JP:JP24' => 'Japan — Mie',
                            'JP:JP25' => 'Japan — Shiga',
                            'JP:JP26' => 'Japan — Kyoto',
                            'JP:JP27' => 'Japan — Osaka',
                            'JP:JP28' => 'Japan — Hyogo',
                            'JP:JP29' => 'Japan — Nara',
                            'JP:JP30' => 'Japan — Wakayama',
                            'JP:JP31' => 'Japan — Tottori',
                            'JP:JP32' => 'Japan — Shimane',
                            'JP:JP33' => 'Japan — Okayama',
                            'JP:JP34' => 'Japan — Hiroshima',
                            'JP:JP35' => 'Japan — Yamaguchi',
                            'JP:JP36' => 'Japan — Tokushima',
                            'JP:JP37' => 'Japan — Kagawa',
                            'JP:JP38' => 'Japan — Ehime',
                            'JP:JP39' => 'Japan — Kochi',
                            'JP:JP40' => 'Japan — Fukuoka',
                            'JP:JP41' => 'Japan — Saga',
                            'JP:JP42' => 'Japan — Nagasaki',
                            'JP:JP43' => 'Japan — Kumamoto',
                            'JP:JP44' => 'Japan — Oita',
                            'JP:JP45' => 'Japan — Miyazaki',
                            'JP:JP46' => 'Japan — Kagoshima',
                            'JP:JP47' => 'Japan — Okinawa',
                            'JE' => 'Jersey',
                            'JO' => 'Jordan',
                            'KZ' => 'Kazakhstan',
                            'KE' => 'Kenya',
                            'KI' => 'Kiribati',
                            'KW' => 'Kuwait',
                            'KG' => 'Kyrgyzstan',
                            'LA' => 'Laos',
                            'LV' => 'Latvia',
                            'LB' => 'Lebanon',
                            'LS' => 'Lesotho',
                            'LR' => 'Liberia',
                            'LY' => 'Libya',
                            'LI' => 'Liechtenstein',
                            'LT' => 'Lithuania',
                            'LU' => 'Luxembourg',
                            'MO' => 'Macao S.A.R., China',
                            'MK' => 'Macedonia',
                            'MG' => 'Madagascar',
                            'MW' => 'Malawi',
                            'MY:JHR' => 'Malaysia — Johor',
                            'MY:KDH' => 'Malaysia — Kedah',
                            'MY:KTN' => 'Malaysia — Kelantan',
                            'MY:LBN' => 'Malaysia — Labuan',
                            'MY:MLK' => 'Malaysia — Malacca (Melaka)',
                            'MY:NSN' => 'Malaysia — Negeri Sembilan',
                            'MY:PHG' => 'Malaysia — Pahang',
                            'MY:PNG' => 'Malaysia — Penang (Pulau Pinang)',
                            'MY:PRK' => 'Malaysia — Perak',
                            'MY:PLS' => 'Malaysia — Perlis',
                            'MY:SBH' => 'Malaysia — Sabah',
                            'MY:SWK' => 'Malaysia — Sarawak',
                            'MY:SGR' => 'Malaysia — Selangor',
                            'MY:TRG' => 'Malaysia — Terengganu',
                            'MY:PJY' => 'Malaysia — Putrajaya',
                            'MY:KUL' => 'Malaysia — Kuala Lumpur',
                            'MV' => 'Maldives',
                            'ML' => 'Mali',
                            'MT' => 'Malta',
                            'MH' => 'Marshall Islands',
                            'MQ' => 'Martinique',
                            'MR' => 'Mauritania',
                            'MU' => 'Mauritius',
                            'YT' => 'Mayotte',
                            'MX:Distrito Federal' => 'Mexico — Distrito Federal',
                            'MX:Jalisco' => 'Mexico — Jalisco',
                            'MX:Nuevo Leon' => 'Mexico — Nuevo León',
                            'MX:Aguascalientes' => 'Mexico — Aguascalientes',
                            'MX:Baja California' => 'Mexico — Baja California',
                            'MX:Baja California Sur' => 'Mexico — Baja California Sur',
                            'MX:Campeche' => 'Mexico — Campeche',
                            'MX:Chiapas' => 'Mexico — Chiapas',
                            'MX:Chihuahua' => 'Mexico — Chihuahua',
                            'MX:Coahuila' => 'Mexico — Coahuila',
                            'MX:Colima' => 'Mexico — Colima',
                            'MX:Durango' => 'Mexico — Durango',
                            'MX:Guanajuato' => 'Mexico — Guanajuato',
                            'MX:Guerrero' => 'Mexico — Guerrero',
                            'MX:Hidalgo' => 'Mexico — Hidalgo',
                            'MX:Estado de Mexico' => 'Mexico — Edo. de México',
                            'MX:Michoacan' => 'Mexico — Michoacán',
                            'MX:Morelos' => 'Mexico — Morelos',
                            'MX:Nayarit' => 'Mexico — Nayarit',
                            'MX:Oaxaca' => 'Mexico — Oaxaca',
                            'MX:Puebla' => 'Mexico — Puebla',
                            'MX:Queretaro' => 'Mexico — Querétaro',
                            'MX:Quintana Roo' => 'Mexico — Quintana Roo',
                            'MX:San Luis Potosi' => 'Mexico — San Luis Potosí',
                            'MX:Sinaloa' => 'Mexico — Sinaloa',
                            'MX:Sonora' => 'Mexico — Sonora',
                            'MX:Tabasco' => 'Mexico — Tabasco',
                            'MX:Tamaulipas' => 'Mexico — Tamaulipas',
                            'MX:Tlaxcala' => 'Mexico — Tlaxcala',
                            'MX:Veracruz' => 'Mexico — Veracruz',
                            'MX:Yucatan' => 'Mexico — Yucatán',
                            'MX:Zacatecas' => 'Mexico — Zacatecas',
                            'FM' => 'Micronesia',
                            'MD' => 'Moldova',
                            'MC' => 'Monaco',
                            'MN' => 'Mongolia',
                            'ME' => 'Montenegro',
                            'MS' => 'Montserrat',
                            'MA' => 'Morocco',
                            'MZ' => 'Mozambique',
                            'MM' => 'Myanmar',
                            'NA' => 'Namibia',
                            'NR' => 'Nauru',
                            'NP:BAG' => 'Nepal — Bagmati',
                            'NP:BHE' => 'Nepal — Bheri',
                            'NP:DHA' => 'Nepal — Dhawalagiri',
                            'NP:GAN' => 'Nepal — Gandaki',
                            'NP:JAN' => 'Nepal — Janakpur',
                            'NP:KAR' => 'Nepal — Karnali',
                            'NP:KOS' => 'Nepal — Koshi',
                            'NP:LUM' => 'Nepal — Lumbini',
                            'NP:MAH' => 'Nepal — Mahakali',
                            'NP:MEC' => 'Nepal — Mechi',
                            'NP:NAR' => 'Nepal — Narayani',
                            'NP:RAP' => 'Nepal — Rapti',
                            'NP:SAG' => 'Nepal — Sagarmatha',
                            'NP:SET' => 'Nepal — Seti',
                            'NL' => 'Netherlands',
                            'AN' => 'Netherlands Antilles',
                            'NC' => 'New Caledonia',
                            'NZ:NL' => 'New Zealand — Northland',
                            'NZ:AK' => 'New Zealand — Auckland',
                            'NZ:WA' => 'New Zealand — Waikato',
                            'NZ:BP' => 'New Zealand — Bay of Plenty',
                            'NZ:TK' => 'New Zealand — Taranaki',
                            'NZ:GI' => 'New Zealand — Gisborne',
                            'NZ:HB' => 'New Zealand — Hawke’s Bay',
                            'NZ:MW' => 'New Zealand — Manawatu-Wanganui',
                            'NZ:WE' => 'New Zealand — Wellington',
                            'NZ:NS' => 'New Zealand — Nelson',
                            'NZ:MB' => 'New Zealand — Marlborough',
                            'NZ:TM' => 'New Zealand — Tasman',
                            'NZ:WC' => 'New Zealand — West Coast',
                            'NZ:CT' => 'New Zealand — Canterbury',
                            'NZ:OT' => 'New Zealand — Otago',
                            'NZ:SL' => 'New Zealand — Southland',
                            'NI' => 'Nicaragua',
                            'NE' => 'Niger',
                            'NG' => 'Nigeria',
                            'NU' => 'Niue',
                            'NF' => 'Norfolk Island',
                            'KP' => 'North Korea',
                            'MP' => 'Northern Mariana Islands',
                            'NO' => 'Norway',
                            'OM' => 'Oman',
                            'PK' => 'Pakistan',
                            'PS' => 'Palestinian Territory',
                            'PA' => 'Panama',
                            'PG' => 'Papua New Guinea',
                            'PY' => 'Paraguay',
                            'PE:CAL' => 'Peru — El Callao',
                            'PE:LMA' => 'Peru — Municipalidad Metropolitana de Lima',
                            'PE:AMA' => 'Peru — Amazonas',
                            'PE:ANC' => 'Peru — Ancash',
                            'PE:APU' => 'Peru — Apurímac',
                            'PE:ARE' => 'Peru — Arequipa',
                            'PE:AYA' => 'Peru — Ayacucho',
                            'PE:CAJ' => 'Peru — Cajamarca',
                            'PE:CUS' => 'Peru — Cusco',
                            'PE:HUV' => 'Peru — Huancavelica',
                            'PE:HUC' => 'Peru — Huánuco',
                            'PE:ICA' => 'Peru — Ica',
                            'PE:JUN' => 'Peru — Junín',
                            'PE:LAL' => 'Peru — La Libertad',
                            'PE:LAM' => 'Peru — Lambayeque',
                            'PE:LIM' => 'Peru — Lima',
                            'PE:LOR' => 'Peru — Loreto',
                            'PE:MDD' => 'Peru — Madre de Dios',
                            'PE:MOQ' => 'Peru — Moquegua',
                            'PE:PAS' => 'Peru — Pasco',
                            'PE:PIU' => 'Peru — Piura',
                            'PE:PUN' => 'Peru — Puno',
                            'PE:SAM' => 'Peru — San Martín',
                            'PE:TAC' => 'Peru — Tacna',
                            'PE:TUM' => 'Peru — Tumbes',
                            'PE:UCA' => 'Peru — Ucayali',
                            'PH:ABR' => 'Philippines — Abra',
                            'PH:AGN' => 'Philippines — Agusan del Norte',
                            'PH:AGS' => 'Philippines — Agusan del Sur',
                            'PH:AKL' => 'Philippines — Aklan',
                            'PH:ALB' => 'Philippines — Albay',
                            'PH:ANT' => 'Philippines — Antique',
                            'PH:APA' => 'Philippines — Apayao',
                            'PH:AUR' => 'Philippines — Aurora',
                            'PH:BAS' => 'Philippines — Basilan',
                            'PH:BAN' => 'Philippines — Bataan',
                            'PH:BTN' => 'Philippines — Batanes',
                            'PH:BTG' => 'Philippines — Batangas',
                            'PH:BEN' => 'Philippines — Benguet',
                            'PH:BIL' => 'Philippines — Biliran',
                            'PH:BOH' => 'Philippines — Bohol',
                            'PH:BUK' => 'Philippines — Bukidnon',
                            'PH:BUL' => 'Philippines — Bulacan',
                            'PH:CAG' => 'Philippines — Cagayan',
                            'PH:CAN' => 'Philippines — Camarines Norte',
                            'PH:CAS' => 'Philippines — Camarines Sur',
                            'PH:CAM' => 'Philippines — Camiguin',
                            'PH:CAP' => 'Philippines — Capiz',
                            'PH:CAT' => 'Philippines — Catanduanes',
                            'PH:CAV' => 'Philippines — Cavite',
                            'PH:CEB' => 'Philippines — Cebu',
                            'PH:COM' => 'Philippines — Compostela Valley',
                            'PH:NCO' => 'Philippines — Cotabato',
                            'PH:DAV' => 'Philippines — Davao del Norte',
                            'PH:DAS' => 'Philippines — Davao del Sur',
                            'PH:DAC' => 'Philippines — Davao Occidental',
                            'PH:DAO' => 'Philippines — Davao Oriental',
                            'PH:DIN' => 'Philippines — Dinagat Islands',
                            'PH:EAS' => 'Philippines — Eastern Samar',
                            'PH:GUI' => 'Philippines — Guimaras',
                            'PH:IFU' => 'Philippines — Ifugao',
                            'PH:ILN' => 'Philippines — Ilocos Norte',
                            'PH:ILS' => 'Philippines — Ilocos Sur',
                            'PH:ILI' => 'Philippines — Iloilo',
                            'PH:ISA' => 'Philippines — Isabela',
                            'PH:KAL' => 'Philippines — Kalinga',
                            'PH:LUN' => 'Philippines — La Union',
                            'PH:LAG' => 'Philippines — Laguna',
                            'PH:LAN' => 'Philippines — Lanao del Norte',
                            'PH:LAS' => 'Philippines — Lanao del Sur',
                            'PH:LEY' => 'Philippines — Leyte',
                            'PH:MAG' => 'Philippines — Maguindanao',
                            'PH:MAD' => 'Philippines — Marinduque',
                            'PH:MAS' => 'Philippines — Masbate',
                            'PH:MSC' => 'Philippines — Misamis Occidental',
                            'PH:MSR' => 'Philippines — Misamis Oriental',
                            'PH:MOU' => 'Philippines — Mountain Province',
                            'PH:NEC' => 'Philippines — Negros Occidental',
                            'PH:NER' => 'Philippines — Negros Oriental',
                            'PH:NSA' => 'Philippines — Northern Samar',
                            'PH:NUE' => 'Philippines — Nueva Ecija',
                            'PH:NUV' => 'Philippines — Nueva Vizcaya',
                            'PH:MDC' => 'Philippines — Occidental Mindoro',
                            'PH:MDR' => 'Philippines — Oriental Mindoro',
                            'PH:PLW' => 'Philippines — Palawan',
                            'PH:PAM' => 'Philippines — Pampanga',
                            'PH:PAN' => 'Philippines — Pangasinan',
                            'PH:QUE' => 'Philippines — Quezon',
                            'PH:QUI' => 'Philippines — Quirino',
                            'PH:RIZ' => 'Philippines — Rizal',
                            'PH:ROM' => 'Philippines — Romblon',
                            'PH:WSA' => 'Philippines — Samar',
                            'PH:SAR' => 'Philippines — Sarangani',
                            'PH:SIQ' => 'Philippines — Siquijor',
                            'PH:SOR' => 'Philippines — Sorsogon',
                            'PH:SCO' => 'Philippines — South Cotabato',
                            'PH:SLE' => 'Philippines — Southern Leyte',
                            'PH:SUK' => 'Philippines — Sultan Kudarat',
                            'PH:SLU' => 'Philippines — Sulu',
                            'PH:SUN' => 'Philippines — Surigao del Norte',
                            'PH:SUR' => 'Philippines — Surigao del Sur',
                            'PH:TAR' => 'Philippines — Tarlac',
                            'PH:TAW' => 'Philippines — Tawi-Tawi',
                            'PH:ZMB' => 'Philippines — Zambales',
                            'PH:ZAN' => 'Philippines — Zamboanga del Norte',
                            'PH:ZAS' => 'Philippines — Zamboanga del Sur',
                            'PH:ZSI' => 'Philippines — Zamboanga Sibugay',
                            'PH:00' => 'Philippines — Metro Manila',
                            'PN' => 'Pitcairn',
                            'PL' => 'Poland',
                            'PT' => 'Portugal',
                            'PR' => 'Puert Rico',
                            'QA' => 'Qatar',
                            'IE' => 'Republic of Ireland',
                            'RE' => 'Reunion',
                            'RO' => 'Romania',
                            'RU' => 'Russia',
                            'RW' => 'Rwanda',
                            'ST' => 'São Tomé and Príncipe',
                            'BL' => 'Saint Barthélemy',
                            'SH' => 'Saint Helena',
                            'KN' => 'Saint Kitts and Nevis',
                            'LC' => 'Saint Lucia',
                            'SX' => 'Saint Martin (Dutch part)',
                            'MF' => 'Saint Martin (French part)',
                            'PM' => 'Saint Pierre and Miquelon',
                            'VC' => 'Saint Vincent and the Grenadines',
                            'WS' => 'Samoa',
                            'SM' => 'San Marino',
                            'SA' => 'Saudi Arabia',
                            'SN' => 'Senegal',
                            'RS' => 'Serbia',
                            'SC' => 'Seychelles',
                            'SL' => 'Sierra Leone',
                            'SG' => 'Singapore',
                            'SK' => 'Slovakia',
                            'SI' => 'Slovenia',
                            'SB' => 'Solomon Islands',
                            'SO' => 'Somalia',
                            'ZA:EC' => 'South Africa — Eastern Cape',
                            'ZA:FS' => 'South Africa — Free State',
                            'ZA:GP' => 'South Africa — Gauteng',
                            'ZA:KZN' => 'South Africa — KwaZulu-Natal',
                            'ZA:LP' => 'South Africa — Limpopo',
                            'ZA:MP' => 'South Africa — Mpumalanga',
                            'ZA:NC' => 'South Africa — Northern Cape',
                            'ZA:NW' => 'South Africa — North West',
                            'ZA:WC' => 'South Africa — Western Cape',
                            'GS' => 'South Georgia/Sandwich Islands',
                            'KR' => 'South Korea',
                            'SS' => 'South Sudan',
                            'ES:C' => 'Spain — A Coruña',
                            'ES:VI' => 'Spain — Araba/Álava',
                            'ES:AB' => 'Spain — Albacete',
                            'ES:A' => 'Spain — Alicante',
                            'ES:AL' => 'Spain — Almería',
                            'ES:O' => 'Spain — Asturias',
                            'ES:AV' => 'Spain — Ávila',
                            'ES:BA' => 'Spain — Badajoz',
                            'ES:PM' => 'Spain — Baleares',
                            'ES:B' => 'Spain — Barcelona',
                            'ES:BU' => 'Spain — Burgos',
                            'ES:CC' => 'Spain — Cáceres',
                            'ES:CA' => 'Spain — Cádiz',
                            'ES:S' => 'Spain — Cantabria',
                            'ES:CS' => 'Spain — Castellón',
                            'ES:CE' => 'Spain — Ceuta',
                            'ES:CR' => 'Spain — Ciudad Real',
                            'ES:CO' => 'Spain — Córdoba',
                            'ES:CU' => 'Spain — Cuenca',
                            'ES:GI' => 'Spain — Girona',
                            'ES:GR' => 'Spain — Granada',
                            'ES:GU' => 'Spain — Guadalajara',
                            'ES:SS' => 'Spain — Gipuzkoa',
                            'ES:H' => 'Spain — Huelva',
                            'ES:HU' => 'Spain — Huesca',
                            'ES:J' => 'Spain — Jaén',
                            'ES:LO' => 'Spain — La Rioja',
                            'ES:GC' => 'Spain — Las Palmas',
                            'ES:LE' => 'Spain — León',
                            'ES:L' => 'Spain — Lleida',
                            'ES:LU' => 'Spain — Lugo',
                            'ES:M' => 'Spain — Madrid',
                            'ES:MA' => 'Spain — Málaga',
                            'ES:ML' => 'Spain — Melilla',
                            'ES:MU' => 'Spain — Murcia',
                            'ES:NA' => 'Spain — Navarra',
                            'ES:OR' => 'Spain — Ourense',
                            'ES:P' => 'Spain — Palencia',
                            'ES:PO' => 'Spain — Pontevedra',
                            'ES:SA' => 'Spain — Salamanca',
                            'ES:TF' => 'Spain — Santa Cruz de Tenerife',
                            'ES:SG' => 'Spain — Segovia',
                            'ES:SE' => 'Spain — Sevilla',
                            'ES:SO' => 'Spain — Soria',
                            'ES:T' => 'Spain — Tarragona',
                            'ES:TE' => 'Spain — Teruel',
                            'ES:TO' => 'Spain — Toledo',
                            'ES:V' => 'Spain — Valencia',
                            'ES:VA' => 'Spain — Valladolid',
                            'ES:BI' => 'Spain — Bizkaia',
                            'ES:ZA' => 'Spain — Zamora',
                            'ES:Z' => 'Spain — Zaragoza',
                            'LK' => 'Sri Lanka',
                            'SD' => 'Sudan',
                            'SR' => 'Suriname',
                            'SJ' => 'Svalbard and Jan Mayen',
                            'SZ' => 'Swaziland',
                            'SE' => 'Sweden',
                            'CH' => 'Switzerland',
                            'SY' => 'Syria',
                            'TW' => 'Taiwan',
                            'TJ' => 'Tajikistan',
                            'TZ' => 'Tanzania',
                            'TH:TH-37' => 'Thailand — Amnat Charoen (อำนาจเจริญ)',
                            'TH:TH-15' => 'Thailand — Ang Thong (อ่างทอง)',
                            'TH:TH-14' => 'Thailand — Ayutthaya (พระนครศรีอยุธยา)',
                            'TH:TH-10' => 'Thailand — Bangkok (กรุงเทพมหานคร)',
                            'TH:TH-38' => 'Thailand — Bueng Kan (บึงกาฬ)',
                            'TH:TH-31' => 'Thailand — Buri Ram (บุรีรัมย์)',
                            'TH:TH-24' => 'Thailand — Chachoengsao (ฉะเชิงเทรา)',
                            'TH:TH-18' => 'Thailand — Chai Nat (ชัยนาท)',
                            'TH:TH-36' => 'Thailand — Chaiyaphum (ชัยภูมิ)',
                            'TH:TH-22' => 'Thailand — Chanthaburi (จันทบุรี)',
                            'TH:TH-50' => 'Thailand — Chiang Mai (เชียงใหม่)',
                            'TH:TH-57' => 'Thailand — Chiang Rai (เชียงราย)',
                            'TH:TH-20' => 'Thailand — Chonburi (ชลบุรี)',
                            'TH:TH-86' => 'Thailand — Chumphon (ชุมพร)',
                            'TH:TH-46' => 'Thailand — Kalasin (กาฬสินธุ์)',
                            'TH:TH-62' => 'Thailand — Kamphaeng Phet (กำแพงเพชร)',
                            'TH:TH-71' => 'Thailand — Kanchanaburi (กาญจนบุรี)',
                            'TH:TH-40' => 'Thailand — Khon Kaen (ขอนแก่น)',
                            'TH:TH-81' => 'Thailand — Krabi (กระบี่)',
                            'TH:TH-52' => 'Thailand — Lampang (ลำปาง)',
                            'TH:TH-51' => 'Thailand — Lamphun (ลำพูน)',
                            'TH:TH-42' => 'Thailand — Loei (เลย)',
                            'TH:TH-16' => 'Thailand — Lopburi (ลพบุรี)',
                            'TH:TH-58' => 'Thailand — Mae Hong Son (แม่ฮ่องสอน)',
                            'TH:TH-44' => 'Thailand — Maha Sarakham (มหาสารคาม)',
                            'TH:TH-49' => 'Thailand — Mukdahan (มุกดาหาร)',
                            'TH:TH-26' => 'Thailand — Nakhon Nayok (นครนายก)',
                            'TH:TH-73' => 'Thailand — Nakhon Pathom (นครปฐม)',
                            'TH:TH-48' => 'Thailand — Nakhon Phanom (นครพนม)',
                            'TH:TH-30' => 'Thailand — Nakhon Ratchasima (นครราชสีมา)',
                            'TH:TH-60' => 'Thailand — Nakhon Sawan (นครสวรรค์)',
                            'TH:TH-80' => 'Thailand — Nakhon Si Thammarat (นครศรีธรรมราช)',
                            'TH:TH-55' => 'Thailand — Nan (น่าน)',
                            'TH:TH-96' => 'Thailand — Narathiwat (นราธิวาส)',
                            'TH:TH-39' => 'Thailand — Nong Bua Lam Phu (หนองบัวลำภู)',
                            'TH:TH-43' => 'Thailand — Nong Khai (หนองคาย)',
                            'TH:TH-12' => 'Thailand — Nonthaburi (นนทบุรี)',
                            'TH:TH-13' => 'Thailand — Pathum Thani (ปทุมธานี)',
                            'TH:TH-94' => 'Thailand — Pattani (ปัตตานี)',
                            'TH:TH-82' => 'Thailand — Phang Nga (พังงา)',
                            'TH:TH-93' => 'Thailand — Phatthalung (พัทลุง)',
                            'TH:TH-56' => 'Thailand — Phayao (พะเยา)',
                            'TH:TH-67' => 'Thailand — Phetchabun (เพชรบูรณ์)',
                            'TH:TH-76' => 'Thailand — Phetchaburi (เพชรบุรี)',
                            'TH:TH-66' => 'Thailand — Phichit (พิจิตร)',
                            'TH:TH-65' => 'Thailand — Phitsanulok (พิษณุโลก)',
                            'TH:TH-54' => 'Thailand — Phrae (แพร่)',
                            'TH:TH-83' => 'Thailand — Phuket (ภูเก็ต)',
                            'TH:TH-25' => 'Thailand — Prachin Buri (ปราจีนบุรี)',
                            'TH:TH-77' => 'Thailand — Prachuap Khiri Khan (ประจวบคีรีขันธ์)',
                            'TH:TH-85' => 'Thailand — Ranong (ระนอง)',
                            'TH:TH-70' => 'Thailand — Ratchaburi (ราชบุรี)',
                            'TH:TH-21' => 'Thailand — Rayong (ระยอง)',
                            'TH:TH-45' => 'Thailand — Roi Et (ร้อยเอ็ด)',
                            'TH:TH-27' => 'Thailand — Sa Kaeo (สระแก้ว)',
                            'TH:TH-47' => 'Thailand — Sakon Nakhon (สกลนคร)',
                            'TH:TH-11' => 'Thailand — Samut Prakan (สมุทรปราการ)',
                            'TH:TH-74' => 'Thailand — Samut Sakhon (สมุทรสาคร)',
                            'TH:TH-75' => 'Thailand — Samut Songkhram (สมุทรสงคราม)',
                            'TH:TH-19' => 'Thailand — Saraburi (สระบุรี)',
                            'TH:TH-91' => 'Thailand — Satun (สตูล)',
                            'TH:TH-17' => 'Thailand — Sing Buri (สิงห์บุรี)',
                            'TH:TH-33' => 'Thailand — Sisaket (ศรีสะเกษ)',
                            'TH:TH-90' => 'Thailand — Songkhla (สงขลา)',
                            'TH:TH-64' => 'Thailand — Sukhothai (สุโขทัย)',
                            'TH:TH-72' => 'Thailand — Suphan Buri (สุพรรณบุรี)',
                            'TH:TH-84' => 'Thailand — Surat Thani (สุราษฎร์ธานี)',
                            'TH:TH-32' => 'Thailand — Surin (สุรินทร์)',
                            'TH:TH-63' => 'Thailand — Tak (ตาก)',
                            'TH:TH-92' => 'Thailand — Trang (ตรัง)',
                            'TH:TH-23' => 'Thailand — Trat (ตราด)',
                            'TH:TH-34' => 'Thailand — Ubon Ratchathani (อุบลราชธานี)',
                            'TH:TH-41' => 'Thailand — Udon Thani (อุดรธานี)',
                            'TH:TH-61' => 'Thailand — Uthai Thani (อุทัยธานี)',
                            'TH:TH-53' => 'Thailand — Uttaradit (อุตรดิตถ์)',
                            'TH:TH-95' => 'Thailand — Yala (ยะลา)',
                            'TH:TH-35' => 'Thailand — Yasothon (ยโสธร)',
                            'TL' => 'Timor-Leste',
                            'TG' => 'Togo',
                            'TK' => 'Tokelau',
                            'TO' => 'Tonga',
                            'TT' => 'Trinidad and Tobago',
                            'TN' => 'Tunisia',
                            'TR:TR01' => 'Turkey — Adana',
                            'TR:TR02' => 'Turkey — Adıyaman',
                            'TR:TR03' => 'Turkey — Afyon',
                            'TR:TR04' => 'Turkey — Ağrı',
                            'TR:TR05' => 'Turkey — Amasya',
                            'TR:TR06' => 'Turkey — Ankara',
                            'TR:TR07' => 'Turkey — Antalya',
                            'TR:TR08' => 'Turkey — Artvin',
                            'TR:TR09' => 'Turkey — Aydın',
                            'TR:TR10' => 'Turkey — Balıkesir',
                            'TR:TR11' => 'Turkey — Bilecik',
                            'TR:TR12' => 'Turkey — Bingöl',
                            'TR:TR13' => 'Turkey — Bitlis',
                            'TR:TR14' => 'Turkey — Bolu',
                            'TR:TR15' => 'Turkey — Burdur',
                            'TR:TR16' => 'Turkey — Bursa',
                            'TR:TR17' => 'Turkey — Çanakkale',
                            'TR:TR18' => 'Turkey — Çankırı',
                            'TR:TR19' => 'Turkey — Çorum',
                            'TR:TR20' => 'Turkey — Denizli',
                            'TR:TR21' => 'Turkey — Diyarbakır',
                            'TR:TR22' => 'Turkey — Edirne',
                            'TR:TR23' => 'Turkey — Elazığ',
                            'TR:TR24' => 'Turkey — Erzincan',
                            'TR:TR25' => 'Turkey — Erzurum',
                            'TR:TR26' => 'Turkey — Eskişehir',
                            'TR:TR27' => 'Turkey — Gaziantep',
                            'TR:TR28' => 'Turkey — Giresun',
                            'TR:TR29' => 'Turkey — Gümüşhane',
                            'TR:TR30' => 'Turkey — Hakkari',
                            'TR:TR31' => 'Turkey — Hatay',
                            'TR:TR32' => 'Turkey — Isparta',
                            'TR:TR33' => 'Turkey — İçel',
                            'TR:TR34' => 'Turkey — İstanbul',
                            'TR:TR35' => 'Turkey — İzmir',
                            'TR:TR36' => 'Turkey — Kars',
                            'TR:TR37' => 'Turkey — Kastamonu',
                            'TR:TR38' => 'Turkey — Kayseri',
                            'TR:TR39' => 'Turkey — Kırklareli',
                            'TR:TR40' => 'Turkey — Kırşehir',
                            'TR:TR41' => 'Turkey — Kocaeli',
                            'TR:TR42' => 'Turkey — Konya',
                            'TR:TR43' => 'Turkey — Kütahya',
                            'TR:TR44' => 'Turkey — Malatya',
                            'TR:TR45' => 'Turkey — Manisa',
                            'TR:TR46' => 'Turkey — Kahramanmaraş',
                            'TR:TR47' => 'Turkey — Mardin',
                            'TR:TR48' => 'Turkey — Muğla',
                            'TR:TR49' => 'Turkey — Muş',
                            'TR:TR50' => 'Turkey — Nevşehir',
                            'TR:TR51' => 'Turkey — Niğde',
                            'TR:TR52' => 'Turkey — Ordu',
                            'TR:TR53' => 'Turkey — Rize',
                            'TR:TR54' => 'Turkey — Sakarya',
                            'TR:TR55' => 'Turkey — Samsun',
                            'TR:TR56' => 'Turkey — Siirt',
                            'TR:TR57' => 'Turkey — Sinop',
                            'TR:TR58' => 'Turkey — Sivas',
                            'TR:TR59' => 'Turkey — Tekirdağ',
                            'TR:TR60' => 'Turkey — Tokat',
                            'TR:TR61' => 'Turkey — Trabzon',
                            'TR:TR62' => 'Turkey — Tunceli',
                            'TR:TR63' => 'Turkey — Şanlıurfa',
                            'TR:TR64' => 'Turkey — Uşak',
                            'TR:TR65' => 'Turkey — Van',
                            'TR:TR66' => 'Turkey — Yozgat',
                            'TR:TR67' => 'Turkey — Zonguldak',
                            'TR:TR68' => 'Turkey — Aksaray',
                            'TR:TR69' => 'Turkey — Bayburt',
                            'TR:TR70' => 'Turkey — Karaman',
                            'TR:TR71' => 'Turkey — Kırıkkale',
                            'TR:TR72' => 'Turkey — Batman',
                            'TR:TR73' => 'Turkey — Şırnak',
                            'TR:TR74' => 'Turkey — Bartın',
                            'TR:TR75' => 'Turkey — Ardahan',
                            'TR:TR76' => 'Turkey — Iğdır',
                            'TR:TR77' => 'Turkey — Yalova',
                            'TR:TR78' => 'Turkey — Karabük',
                            'TR:TR79' => 'Turkey — Kilis',
                            'TR:TR80' => 'Turkey — Osmaniye',
                            'TR:TR81' => 'Turkey — Düzce',
                            'TM' => 'Turkmenistan',
                            'TC' => 'Turks and Caicos Islands',
                            'TV' => 'Tuvalu',
                            'UG' => 'Uganda',
                            'UA' => 'Ukraine',
                            'AE' => 'United Arab Emirates',
                            'GB' => 'United Kingdom (UK)',
                            'US:AL' => 'United States (US) — Alabama',
                            'US:AK' => 'United States (US) — Alaska',
                            'US:AZ' => 'United States (US) — Arizona',
                            'US:AR' => 'United States (US) — Arkansas',
                            'US:CA' => 'United States (US) — California',
                            'US:CO' => 'United States (US) — Colorado',
                            'US:CT' => 'United States (US) — Connecticut',
                            'US:DE' => 'United States (US) — Delaware',
                            'US:DC' => 'United States (US) — District Of Columbia',
                            'US:FL' => 'United States (US) — Florida',
                            'US:GA' => 'United States (US) — Georgia',
                            'US:HI' => 'United States (US) — Hawaii',
                            'US:ID' => 'United States (US) — Idaho',
                            'US:IL' => 'United States (US) — Illinois',
                            'US:IN' => 'United States (US) — Indiana',
                            'US:IA' => 'United States (US) — Iowa',
                            'US:KS' => 'United States (US) — Kansas',
                            'US:KY' => 'United States (US) — Kentucky',
                            'US:LA' => 'United States (US) — Louisiana',
                            'US:ME' => 'United States (US) — Maine',
                            'US:MD' => 'United States (US) — Maryland',
                            'US:MA' => 'United States (US) — Massachusetts',
                            'US:MI' => 'United States (US) — Michigan',
                            'US:MN' => 'United States (US) — Minnesota',
                            'US:MS' => 'United States (US) — Mississippi',
                            'US:MO' => 'United States (US) — Missouri',
                            'US:MT' => 'United States (US) — Montana',
                            'US:NE' => 'United States (US) — Nebraska',
                            'US:NV' => 'United States (US) — Nevada',
                            'US:NH' => 'United States (US) — New Hampshire',
                            'US:NJ' => 'United States (US) — New Jersey',
                            'US:NM' => 'United States (US) — New Mexico',
                            'US:NY' => 'United States (US) — New York',
                            'US:NC' => 'United States (US) — North Carolina',
                            'US:ND' => 'United States (US) — North Dakota',
                            'US:OH' => 'United States (US) — Ohio',
                            'US:OK' => 'United States (US) — Oklahoma',
                            'US:OR' => 'United States (US) — Oregon',
                            'US:PA' => 'United States (US) — Pennsylvania',
                            'US:RI' => 'United States (US) — Rhode Island',
                            'US:SC' => 'United States (US) — South Carolina',
                            'US:SD' => 'United States (US) — South Dakota',
                            'US:TN' => 'United States (US) — Tennessee',
                            'US:TX' => 'United States (US) — Texas',
                            'US:UT' => 'United States (US) — Utah',
                            'US:VT' => 'United States (US) — Vermont',
                            'US:VA' => 'United States (US) — Virginia',
                            'US:WA' => 'United States (US) — Washington',
                            'US:WV' => 'United States (US) — West Virginia',
                            'US:WI' => 'United States (US) — Wisconsin',
                            'US:WY' => 'United States (US) — Wyoming',
                            'US:AA' => 'United States (US) — Armed Forces (AA)',
                            'US:AE' => 'United States (US) — Armed Forces (AE)',
                            'US:AP' => 'United States (US) — Armed Forces (AP)',
                            'UM' => 'United States (US) Minor Outlying Islands',
                            'VI' => 'United States (US) Virgin Islands',
                            'UY' => 'Uruguay',
                            'UZ' => 'Uzbekistan',
                            'VU' => 'Vanuatu',
                            'VA' => 'Vatican',
                            'VE' => 'Venezuela',
                            'VN' => 'Vietnam',
                            'WF' => 'Wallis and Futuna',
                            'EH' => 'Western Sahara',
                            'YE' => 'Yemen',
                            'ZM' => 'Zambia',
                            'ZW' => 'Zimbabwe',
                        );
                        foreach($opts as $c =>$vl)
                        {
                            $selected = $c==$options['origin_country_state']? 'selected':'';
                            echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                        }
                        ?>
                    </select>

                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_origin_custom_state">Origin State Code</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Origin State Code</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['origin_custom_state']?>" style="" id="woocommerce_wf_shipping_ups_origin_custom_state" name="option[origin_custom_state]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_origin_postcode">Origin Postcode</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Origin Postcode</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['origin_postcode']?>" style="" id="woocommerce_wf_shipping_ups_origin_postcode" name="option[origin_postcode]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_phone_number">Your Phone Number</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Your Phone Number</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['phone_number']?>" style="" id="woocommerce_wf_shipping_ups_phone_number" name="option[phone_number]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            </tbody></table>
        <h3 id="woocommerce_wf_shipping_ups_services_packaging" class="wc-settings-sub-title ">Services and Packaging</h3>
        <table class="form-table">
            <tbody><tr valign="top" id="service_options">
                <td style="padding-left:0px" colspan="2" class="forminp">
                    <table class="ups_services widefat">
                        <thead>
                        <tr><th class="sort">&nbsp;</th>
                            <th>Service Code</th>
                            <th>Name</th>
                            <th>Enabled</th>
                            <th>Price Adjustment ($)</th>
                            <th>Price Adjustment (%)</th>
                        </tr></thead>
                        <tfoot>
                        </tfoot>
                        <tbody class="ui-sortable">
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[12][order]" class="order"></td>
                            <td><strong>12</strong></td>
                            <td><input type="text" size="50" value="" placeholder="3 Day Select (UPS)" name="ups_service[12][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[12][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[12][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[12][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[03][order]" class="order"></td>
                            <td><strong>03</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Ground (UPS)" name="ups_service[03][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[03][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[03][adjustment]"></td>
                            <td><input type="text" size="4" value="20" placeholder="N/A" name="ups_service[03][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[02][order]" class="order"></td>
                            <td><strong>02</strong></td>
                            <td><input type="text" size="50" value="" placeholder="2nd Day Air (UPS)" name="ups_service[02][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[02][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[02][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[02][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[59][order]" class="order"></td>
                            <td><strong>59</strong></td>
                            <td><input type="text" size="50" value="" placeholder="2nd Day Air AM (UPS)" name="ups_service[59][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[59][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[59][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[59][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[01][order]" class="order"></td>
                            <td><strong>01</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Next Day Air (UPS)" name="ups_service[01][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[01][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[01][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[01][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[13][order]" class="order"></td>
                            <td><strong>13</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Next Day Air Saver (UPS)" name="ups_service[13][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[13][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[13][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[13][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[14][order]" class="order"></td>
                            <td><strong>14</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Next Day Air Early AM (UPS)" name="ups_service[14][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[14][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[14][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[14][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[92][order]" class="order"></td>
                            <td><strong>92</strong></td>
                            <td><input type="text" size="50" value="" placeholder="SurePost Less than 1 lb (UPS)" name="ups_service[92][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[92][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[92][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[92][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="8" name="ups_service[11][order]" class="order"></td>
                            <td><strong>11</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Standard (UPS)" name="ups_service[11][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[11][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[11][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[11][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="9" name="ups_service[07][order]" class="order"></td>
                            <td><strong>07</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Worldwide Express (UPS)" name="ups_service[07][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[07][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[07][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[07][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="10" name="ups_service[54][order]" class="order"></td>
                            <td><strong>54</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Worldwide Express Plus (UPS)" name="ups_service[54][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[54][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[54][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[54][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="11" name="ups_service[08][order]" class="order"></td>
                            <td><strong>08</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Worldwide Expedited (UPS)" name="ups_service[08][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[08][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[08][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[08][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="12" name="ups_service[65][order]" class="order"></td>
                            <td><strong>65</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Saver (UPS)" name="ups_service[65][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[65][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[65][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[65][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[93][order]" class="order"></td>
                            <td><strong>93</strong></td>
                            <td><input type="text" size="50" value="" placeholder="SurePost 1 lb or Greater (UPS)" name="ups_service[93][name]"></td>
                            <td><input type="checkbox" name="ups_service[93][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[93][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[93][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[94][order]" class="order"></td>
                            <td><strong>94</strong></td>
                            <td><input type="text" size="50" value="" placeholder="SurePost BPM (UPS)" name="ups_service[94][name]"></td>
                            <td><input type="checkbox" name="ups_service[94][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[94][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[94][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[95][order]" class="order"></td>
                            <td><strong>95</strong></td>
                            <td><input type="text" size="50" value="" placeholder="SurePost Media (UPS)" name="ups_service[95][name]"></td>
                            <td><input type="checkbox" name="ups_service[95][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[95][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[95][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[M2][order]" class="order"></td>
                            <td><strong>M2</strong></td>
                            <td><input type="text" size="50" value="" placeholder="First Class Mail (UPS)" name="ups_service[M2][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[M2][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M2][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M2][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[M3][order]" class="order"></td>
                            <td><strong>M3</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Priority Mail (UPS)" name="ups_service[M3][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[M3][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M3][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M3][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[M4][order]" class="order"></td>
                            <td><strong>M4</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Expedited Mail Innovations  (UPS)" name="ups_service[M4][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[M4][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M4][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M4][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[M5][order]" class="order"></td>
                            <td><strong>M5</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Priority Mail Innovations  (UPS)" name="ups_service[M5][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[M5][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M5][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M5][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[M6][order]" class="order"></td>
                            <td><strong>M6</strong></td>
                            <td><input type="text" size="50" value="" placeholder="EconomyMail Innovations  (UPS)" name="ups_service[M6][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[M6][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M6][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[M6][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[70][order]" class="order"></td>
                            <td><strong>70</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Access Point Economy  (UPS)" name="ups_service[70][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[70][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[70][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[70][adjustment_percent]"></td>
                        </tr>
                        <tr>
                            <td class="sort ui-sortable-handle"><input type="hidden" value="" name="ups_service[96][order]" class="order"></td>
                            <td><strong>96</strong></td>
                            <td><input type="text" size="50" value="" placeholder="Worldwide Express Freight (UPS)" name="ups_service[96][name]"></td>
                            <td><input type="checkbox" checked="checked" name="ups_service[96][enabled]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[96][adjustment]"></td>
                            <td><input type="text" size="4" value="" placeholder="N/A" name="ups_service[96][adjustment_percent]"></td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_offer_rates">Offer Rates</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Offer Rates</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_offer_rates" name="option[offer_rates]" class="select ">

                            <?php
                            $ctr = array (
                                'all' => 'Offer the customer all returned rates',
                                'cheapest' => 'Offer the customer the cheapest rate only',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['offer_rates']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_fallback">Fallback</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Fallback</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['fallback']?>" style="" id="woocommerce_wf_shipping_ups_fallback" name="option[fallback]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_conversion_rate">Conversion Rate.</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Conversion Rate.</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['conversion_rate']?>" style="" id="woocommerce_wf_shipping_ups_conversion_rate" name="option[conversion_rate]" class="input-text regular-input ">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_packing_method">Parcel Packing</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Parcel Packing</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_packing_method" name="option[packing_method]" class="select packing_method">


                            <?php
                            $ctr = array (
                                'per_item' => 'Default: Pack items individually',
                                'box_packing' => 'Recommended: Pack into boxes with weights and dimensions',
                                'weight_based' => 'Weight based: Calculate shipping on the basis of order total weight',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['packing_method']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top" style="display: none;">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_box_max_weight">Box Maximum Weight</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Box Maximum Weight</span></legend>
                        <input type="text" placeholder="" value="<?php echo $options['box_max_weight']?>" style="" id="woocommerce_wf_shipping_ups_box_max_weight" name="option[box_max_weight]" class="input-text regular-input weight_based_option">
                    </fieldset>
                </td>
            </tr>
            <tr valign="top" style="display: none;">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_weight_packing_process">Packing Process</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Packing Process</span></legend>
                        <select style="" id="woocommerce_wf_shipping_ups_weight_packing_process" name="option[weight_packing_process]" class="select weight_based_option">
                            <?php
                            $ctr = array (
                                'pack_descending' => 'Pack heavier items first',
                                'pack_ascending' => 'Pack lighter items first.',
                                'pack_simple' => 'Pack purely divided by weight.',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = $c==$options['weight_packing_process']? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th class="titledesc" scope="row">
                    <label for="woocommerce_wf_shipping_ups_ups_packaging">UPS Packaging</label>
                    <span class="woocommerce-help-tip"></span>			</th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>UPS Packaging</span></legend>
                        <select multiple="multiple" class="multiselect ups_packaging chosen_select" name="option[ups_packaging][]" id="woocommerce_wf_shipping_ups_ups_packaging" style="width: 450px;"  >

                            <?php
                            $ctr = array (
                                '01' => 'UPS Letter',
                                '03' => 'Tube',
                                '04' => 'PAK',
                                '24' => '25KG Box',
                                '25' => '10KG Box',
                                '2a' => 'Small Express Box',
                                '2b' => 'Medium Express Box',
                                '2c' => 'Large Express Box',
                            );
                            foreach($ctr as $c =>$vl)
                            {
                                $selected = in_array($c,$options['ups_packaging'])? 'selected':'';
                                echo '<option value="'.$c.'" '.$selected.'>'.$vl.'</option>';
                            }
                            ?>
                        </select>
                    </fieldset>
                </td>
            </tr>
            <tr valign="top" id="packing_options" style="display: none;">
                <td style="padding-left:0px" colspan="2" class="forminp">
                    <style type="text/css">
                        .ups_boxes td, .ups_services td {
                            vertical-align: middle;
                            padding: 4px 7px;
                        }
                        .ups_boxes th, .ups_services th {
                            padding: 9px 7px;
                        }
                        .ups_boxes td input {
                            margin-right: 4px;
                        }
                        .ups_boxes .check-column {
                            vertical-align: middle;
                            text-align: left;
                            padding: 0 7px;
                        }
                        .ups_services th.sort {
                            width: 16px;
                            padding: 0 16px;
                        }
                        .ups_services td.sort {
                            cursor: move;
                            width: 16px;
                            padding: 0 16px;
                            cursor: move;
                            background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center;					}
                    </style>
                    <strong>Custom Box Dimensions</strong><br>
                    <table class="ups_boxes widefat">
                        <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox"></th>
                            <th>Outer Length</th>
                            <th>Outer Width</th>
                            <th>Outer Height</th>
                            <th>Inner Length</th>
                            <th>Inner Width</th>
                            <th>Inner Height</th>
                            <th>Box Weight</th>
                            <th>Max Weight</th>
                        </tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="3">
                                <a class="button plus insert" href="#">Add Box</a>
                                <a class="button minus remove" href="#">Remove selected box(es)</a>
                            </th>
                            <th colspan="6">
                                <small class="description">Items will be packed into these boxes depending based on item dimensions and volume. Outer dimensions will be passed to UPS, whereas inner dimensions will be used for packing. Items not fitting into boxes will be packed individually.</small>
                            </th>
                        </tr>
                        </tfoot>
                        <tbody id="rates">
                        </tbody>
                    </table>
                    <script type="text/javascript">

                        jQuery(window).on('load',function(){

                            jQuery('.ups_boxes .insert').click( function() {
                                var $tbody = jQuery('.ups_boxes').find('tbody');
                                var size = $tbody.find('tr').size();
                                var code = '<tr class="new">\
									<td class="check-column"><input type="checkbox" /></td>\
									<td><input type="text" size="5" name="boxes_outer_length[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_outer_width[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_outer_height[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_inner_length[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_inner_width[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_inner_height[' + size + ']" />IN</td>\
									<td><input type="text" size="5" name="boxes_box_weight[' + size + ']" />LBS</td>\
									<td><input type="text" size="5" name="boxes_max_weight[' + size + ']" />LBS</td>\
								</tr>';

                                $tbody.append( code );

                                return false;
                            } );

                            jQuery('.ups_boxes .remove').click(function() {
                                var $tbody = jQuery('.ups_boxes').find('tbody');

                                $tbody.find('.check-column input:checked').each(function() {
                                    jQuery(this).closest('tr').hide().find('input').val('');
                                });

                                return false;
                            });

                            // Ordering
                            jQuery('.ups_services tbody').sortable({
                                items:'tr',
                                cursor:'move',
                                axis:'y',
                                handle: '.sort',
                                scrollSensitivity:40,
                                forcePlaceholderSize: true,
                                helper: 'clone',
                                opacity: 0.65,
                                placeholder: 'wc-metabox-sortable-placeholder',
                                start:function(event,ui){
                                    ui.item.css('baclbsround-color','#f6f6f6');
                                },
                                stop:function(event,ui){
                                    ui.item.removeAttr('style');
                                    ups_services_row_indexes();
                                }
                            });

                            function ups_services_row_indexes() {
                                jQuery('.ups_services tbody tr').each(function(index, el){
                                    jQuery('input.order', el).val( parseInt( jQuery(el).index('.ups_services tr') ) );
                                });
                            };

                        });

                    </script>
                </td>
            </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" value="Save changes" class="button-primary woocommerce-save-button" name="save">
        </p>
    </form>
</div>
<style>
    .woocommerce table.form-table input.regular-input {
        width: 25em;
    }
</style>
<script>
    jQuery(function($){
        $('#woocommerce_wf_shipping_ups_availability').change(function(){
            if($(this).val()=='all'){
                $('#select_ups_country').hide();
            }else{
                $('#select_ups_country').show();
            }
        }).change();
    })
</script>