<?php
if (empty($patient['pa_email']) && is_user_logged_in()) {
    $user = wp_get_current_user();
    $patient['pa_email'] = $user->user_email;
}

if (isset($_GET['debugpatient'])) {
    $patient = $wpdb->get_row("select * from ci_patients where pa_first = 'patient' or pa_last = 'patient' limit 1", ARRAY_A);
}

$show_patient_passport      = isset($form_options['show_patient_passport']) && $form_options['show_patient_passport'];
$required_patient_passport  = isset($form_options['required_patient_passport']) && $form_options['required_patient_passport'];
$show_patient_sport         = isset($form_options['show_patient_sport']) && $form_options['show_patient_sport'];
$show_quesion_interested    = isset($form_options['show_quesion_interested']) && $form_options['show_quesion_interested'];

$hide_ssn           = isset($form_options['hide_patient_passport']) && $form_options['hide_patient_passport'];
$allow_empty_ssn    = isset($form_options['allow_empty_ssn']) && $form_options['allow_empty_ssn'];
$show_patient_race  = isset($form_options['show_patient_race']) && $form_options['show_patient_race'];
$show_patient_eth   = isset($form_options['show_patient_eth']) && $form_options['show_patient_eth'];
$phone_not_required = isset($form_options['phone_not_required']) && $form_options['phone_not_required'];
$show_patient_fvaccine = isset($form_options['show_patient_fvaccine']) && $form_options['show_patient_fvaccine'];
$show_marital_status = isset($form_options['show_marital_status']) && $form_options['show_marital_status'];



if (isset($_REQUEST['location'])) {
    $show_patient_passport = false;
    $show_quesion_interested = false;
} else {
    $show_patient_sport = false;
}
$sports = array(
    'Soccer',
    'Football',
    'Baseball',
    'Hockey',
    'Basketball',
    'Other'
);

if (!in_array($current_loc_key_char, array('S', 'W'))) {
    $show_patient_sport = false;
}

$count_adding_row_f = 0;

if (isset($form_options['text_apatient']) && !empty($form_options['text_apatient'])) {
    echo '<p>' . nl2br($form_options['text_apatient']) . '</p>';
}
?>
<div id="pre_patient" style="display: none;">
    <div class="frow blabel">
        <div class="col4">
            <label>First Name</label>
            <input type="text" class="cfname drequired" id="pre_first_name" />
        </div>
        <div class="col4">
            <label>Last Name</label>
            <input type="text" class="cfname drequired" id="pre_last_name" />
        </div>

        <div class="col4">
            <label>DOB</label>
            <input type="text" class="hasdate birthday drequired" placeholder="yyyy-mm-dd" id="pre_pa_dob" />
        </div>
        <div class="col4">
            <label>Cell Phone Number</label>
            <input type="text" class="has_phone drequired" id="pre_pa_phone" placeholder="(xxx) xxx-xxxx" maxlength="18" />
        </div>
    </div>
    <div class="frow aright">
        <a class="button qbutton small" id="btn_pre_patient_next" href="#nextstep">Next</a>
    </div>
</div>
<div id="show_confirm" class="center">
    <label>Are you a previous patient?</label>
    <div class="frow ">
        <a class="button qbutton button_second small" id="btn_patient_no" href="#nextstep">No</a>

        <a class="button qbutton small" id="btn_patient_yes" href="#nextstep">Yes</a>
    </div>
</div>

<div id="patient_form">
    <?php
    if (isset($form_options['show_patient_multi']) && $form_options['show_patient_multi']) {
        echo '<h4 class="membernumh4">Member #<span class="mpnum">1</span></h4>';
    }
    ?>
    <div class="frow blabel">
        <div class="col4">
            <label>First Name</label>
            <input type="text" class="cfname" name="pa[pa_first]" value="<?php echo isset($patient['pa_first']) ? $patient['pa_first'] : '' ?>" required />
        </div>
        <div class="col4">
            <label>Last Name</label>
            <input type="text" class="cfname" name="pa[pa_last]" value="<?php echo isset($patient['pa_last']) ? $patient['pa_last'] : '' ?>" required />
        </div>

        <div class="col4">
            <label>Middle Initial</label>
            <input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo isset($patient['pa_middle']) ? $patient['pa_middle'] : '' ?>" <?php if ($form_options['required_patient_mid']) echo 'required' ?> />
        </div>
        <?php if (!$hide_ssn) { ?>
            <div class="col4">
                <label>Social Security Number</label>
                <input type="text" name="pa[pa_ssn]" id="pa_ssn" minlength="11" maxlength="11" placeholder="xxx-xx-xxx" data-format="xxx-xx-xxx" value="<?php echo isset($patient['pa_ssn']) ? $patient['pa_ssn'] : '' ?>" data-allow_empty_ssn="<?php echo $allow_empty_ssn ? 1 : 0 ?>" />

            </div>
        <?php } else if ($show_patient_passport) { ?>
            <div class="col4">
                <label>Passport Number</label>
                <input type="text" name="pa[pa_passport]" value="<?php echo isset($patient['pa_passport']) ? $patient['pa_passport'] : '' ?>" <?php echo $required_patient_passport ? 'required' : '' ?> maxlength="100" />
            </div>
        <?php } ?>
    </div>

    <div class="frow blabel">

        <div class="col4">
            <label>Street Address</label>
            <input type="text" name="pa[pa_address]" value="<?php echo isset($patient['pa_address']) ? $patient['pa_address'] : '' ?>" required maxlength="150" class="f_address" />
        </div>
        <div class="col4">
            <label>City</label>
            <input type="text" name="pa[pa_city]" value="<?php echo isset($patient['pa_city']) ? $patient['pa_city'] : '' ?>" required maxlength="100" class="f_address" />
        </div>
        <div class="col4">
            <label>State</label>
            <select name="pa[pa_state]" required class="f_address">
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
                    $selected = isset($patient['pa_state']) && $patient['pa_state'] == $statecode ? 'selected' : '';
                    echo '<option value="' . $statecode . '" ' . $selected . '>' . $statetxt . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>Zip</label>
            <input type="text" name="pa[pa_zip]" value="<?php echo isset($patient['pa_zip']) ? $patient['pa_zip'] : '' ?>" required maxlength="12" class="f_address" />
        </div>
    </div>
    <div class="frow">
        <div class="col4">
            <label>Gender at Birth</label><br>
            <select name="pa[pa_gender]" required>
                <?php
                foreach (
                    array(
                        ''  => 'Select',
                        'F' => 'Female',
                        'M' => 'Male'
                    ) as $optkey => $opttxt
                ) {
                    $selected = isset($patient['pa_gender']) && $patient['pa_gender'] == $optkey ? 'selected' : '';
                    echo '<option value="' . $optkey . '">' . $opttxt . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col4">
            <label>DOB</label>
            <input type="text" class="hasdate birthday" name="pa[pa_dob]" placeholder="yyyy-mm-dd" value="<?php echo isset($patient['pa_dob']) ? $patient['pa_dob'] : '' ?>" required />
        </div>
        <div class="col4">
            <label>Cell Phone Number</label>
            <input type="text" class="has_phone" name="pa[pa_phone]" placeholder="(xxx) xxx-xxxx" value="<?php echo isset($patient['pa_phone']) ? $patient['pa_phone'] : '' ?>" <?php if (!$phone_not_required) echo 'required' ?> maxlength="18" />
        </div>
        <div class="col4">
            <label>Email</label>
            <input type="email" name="pa[pa_email]" value="<?php echo isset($patient['pa_email']) ? $patient['pa_email'] : '' ?>" required />
        </div>
    </div>
    <div class="frow">

        <?php
        $adding_fields = array();
        if ($show_patient_passport && !$hide_ssn) {
            ob_start();
        ?>
            <div class="col4">
                <label>Passport Number</label>
                <input type="text" name="pa[pa_passport]" value="<?php echo isset($patient['pa_passport']) ? $patient['pa_passport'] : '' ?>" <?php echo $required_patient_passport ? 'required' : '' ?> maxlength="100" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }
        if ($show_patient_sport) {
            ob_start();
        ?>
            <div class="col4">
                <label>Sport</label>
                <select name="pa[pa_sport]">
                    <option value="">Select</option>
                    <?php
                    foreach ($sports as $sport) {
                        echo '<option value="' . $sport . '">' . $sport . '</option>';
                    }
                    ?>
                </select>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_patient_race) {
            ob_start();
        ?>
            <div class="col4">
                <label>Race</label>
                <select name="pa[pa_race]" required>
                    <?php
                    $races = array(
                        ''       => 'Select',
                        '1002-5' => 'American Indian or Alaska  Native',
                        '2028-9' => 'Asian',
                        '2054-5' => 'Black / African American',
                        '2076-8' => 'Native Hawaiian or Other  Pacific Islander',
                        '2106-3' => 'White',
                        '2131-1' => 'Other'
                    );
                    foreach ($races as $k => $vl) {
                        echo '<option value="' . $k . '">' . $vl . '</option>';
                    }
                    ?>
                </select>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_patient_eth) {
            ob_start();
        ?>
            <div class="col4">
                <label>Ethnicity</label>
                <select name="pa[pa_ethnicity]" required>
                    <?php
                    $Ethnicitis = array(
                        '' => 'Select',
                        'H' => 'Hispanic or Latino',
                        'N' => 'Not Hispanic or Latino',
                        'U' => 'Unknown'
                    );
                    foreach ($Ethnicitis as $k => $vl) {
                        echo '<option value="' . $k . '">' . $vl . '</option>';
                    }
                    ?>
                </select>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_marital_status) {
            ob_start();
        ?>
            <div class="col4">
                <label>Marital Status</label>
                <select name="pa[pa_marital_status]">
                    <?php
                    $marital_status = array(
                        '' => 'Select',
                        's' => 'Single',
                        'm' => 'Married',
                        'd' => 'Decline to respond',
                        'o' => 'Other'
                    );
                    foreach ($marital_status as $k => $vl) {
                        echo '<option value="' . $k . '">' . $vl . '</option>';
                    }
                    ?>
                </select>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_marital_status) {
            ob_start();
        ?>
            <div class="col4">
                <label>Height</label>
                <input type="text" name="pa[pa_height]" value="<?php echo isset($patient['pa_height']) ? $patient['pa_height'] : '' ?>" placeholder="inches" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_marital_status) {
            ob_start();
        ?>
            <div class="col4">
                <label>Weight</label>
                <input type="text" name="pa[pa_weight]" value="<?php echo isset($patient['pa_weight']) ? $patient['pa_weight'] : '' ?>" placeholder="LBs" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_marital_status) {
            ob_start();
        ?>
            <div class="col4">
                <label>Sleep</label>
                <input type="text" name="pa[pa_sleep]" value="<?php echo isset($patient['pa_sleep']) ? $patient['pa_sleep'] : '' ?>" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_marital_status) {
            ob_start();
        ?>
            <div class="col4">
                <label>Exercise</label>
                <input type="text" name="pa[pa_exercise]" value="<?php echo isset($patient['pa_exercise']) ? $patient['pa_exercise'] : '' ?>" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }


        if (isset($form_options['show_patient_sexgen']) && $form_options['show_patient_sexgen']) {
            ob_start();
        ?>
            <div class="col4">
                <label>Sexual Orientation</label>
                <input type="text" name="pa[pa_sexual_oriention]" value="<?php echo isset($patient['pa_sexual_oriention']) ? $patient['pa_sexual_oriention'] : '' ?>" />
            </div>
            <?php
            $count_adding_row_f++;
            if ($count_adding_row_f >= 4) {
                echo '</div><div class="frow">';
            }
            ?>
            <div class="col4">
                <label>Gender Identity</label>
                <input type="text" name="pa[pa_gender_identity]" value="<?php echo isset($patient['pa_gender_identity']) ? $patient['pa_gender_identity'] : '' ?>" />
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }


        if ($show_patient_fvaccine) {
            ob_start();
        ?>
            <div class="col4">
                <label>Are you fully Vaccinated?</label>
                <select name="pa[pa_fully_vaccinated]" required>
                    <?php
                    foreach (
                        array(
                            '' => 'Select',
                            'yes' => 'Yes',
                            'no' => 'No',
                        ) as $k => $vl
                    ) {
                        echo '<option value="' . $k . '">' . $vl . '</option>';
                    }
                    ?>
                </select>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        if ($show_quesion_interested) {
            ob_start();
        ?>
            <div class="col41">
                <label>Are you interested in being contacted regarding a COVID research study?</label>
                <select name="pa[pa_interested_research]" required style="width:100px">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_interested_research']) && $patient['pa_interested_research'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '">' . $opttxt . '</option>';
                    }
                    ?>
                </select>
                <p style="margin:0">(Your contact information will be shared with the SRI research team if you qualify.)</p>
            </div>
        <?php
            $adding_fields[] = ob_get_clean();
        }

        foreach ($adding_fields as $inum => $fhtml) {
            if ($inum > 0  && $inum % 4 == 0) {
                echo '</div><div class="frow">';
            }
            echo $fhtml;
        }

        ?>
    </div>

    <?php if ($form_options['show_patient_county_pregnant']) { ?>
        <div class="frow">
            <div class="col4">
                <label>Pt County</label>
                <input type="text" name="pa[pa_pt_county]" value="<?php echo $patient['pa_pt_county'] ?>" />
            </div>
            <div class="col4">
                <label>Employed in Healthcare?</label>
                <select name="pa[pa_employed_in_healthcar]">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_employed_in_healthcar']) && $patient['pa_employed_in_healthcar'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '" ' . $selected . '>' . $opttxt . '</option>';
                    }
                    ?>
                </select>

            </div>
            <div class="col4">
                <label>Symptomatic per cdc?</label>
                <select name="pa[pa_symptomatic]">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_symptomatic']) && $patient['pa_symptomatic'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '" ' . $selected . '>' . $opttxt . '</option>';
                    }
                    ?>
                </select>

            </div>
            <div class="col4">
                <label>Date of Symptom Onset?</label>
                <input type="text" class="hasdate" name="pa[pa_date_of_symptom]" placeholder="yyyy-mm-dd" value="<?php echo $patient['pa_date_of_symptom'] != '0000-00-00' ? $patient['pa_date_of_symptom'] : '' ?>" />
            </div>
        </div>
        <div class="frow">
            <div class="col4">
                <label>Hospitalized in ICU?</label>
                <select name="pa[pa_hospitalized_in_icu]">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_hospitalized_in_icu']) && $patient['pa_hospitalized_in_icu'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '" ' . $selected . '>' . $opttxt . '</option>';
                    }
                    ?>
                </select>

            </div>
            <div class="col4">
                <label>Resident in Congregate Care</label>
                <select name="pa[pa_resident_in_congregate_care]">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_resident_in_congregate_care']) && $patient['pa_resident_in_congregate_care'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '" ' . $selected . '>' . $opttxt . '</option>';
                    }
                    ?>
                </select>

            </div>
            <div class="col4">
                <label>Pregnant?</label>
                <select name="pa[pa_pregnant]">
                    <?php
                    foreach (
                        array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ) as $optkey => $opttxt
                    ) {
                        $selected = isset($patient['pa_pregnant']) && $patient['pa_pregnant'] == $optkey ? 'selected' : '';
                        echo '<option value="' . $optkey . '" ' . $selected . '>' . $opttxt . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    <?php } ?>

    <?php if (function_exists('trpdd_get_main_options') && trpdd_get_main_options('show_patient_r3snh')) { ?>
        <div class="frow">
            <?php
            $patient_types = array(
                '' => 'None',
                'SC' => 'Symptomatic',
                'NH' => 'Nursing Home',
                'HW' => 'Healthcare Worker'
            );
            echo '<span class="group_radio">';
            foreach ($patient_types as $k => $vl) {
                $value = isset($patient['pa_patient_type']) ? $patient['pa_patient_type'] : '';
                echo '<label><input type="radio" name="pa[pa_patient_type]" value="' . $k . '" ' . checked($value, $k, false) . '/> ' . $vl . '</label>';
            }
            echo '</span>';
            ?>
        </div>
    <?php } ?>



    <?php if (isset($has_company_section) && $has_company_section) { ?>
        <div class="frow" id="company_section" <?php if (!$form_options['disable_select_tests']) echo 'style="display: none;"' ?>>
            <div class="col2">
                <label>Name of Company/Group</label>
                <input type="text" name="bat[company]" />
            </div>
        </div>
    <?php } ?>

    <?php

    if (isset($form_options['text_bpatient']) && !empty($form_options['text_bpatient'])) {
        echo '<p>' . nl2br($form_options['text_bpatient']) . '</p>';
    }
    ?>

</div>