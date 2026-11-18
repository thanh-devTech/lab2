<?php
global $wpdb;
$user = wp_get_current_user();
$settings       = tr_billing_get_settings();

if (isset($_GET['reset_billing_codes'])) {
    $wpdb->query("truncate table ci_bill_drug_classes");
    $wpdb->query("truncate table ci_bill_drug_classes_test");
    $wpdb->query("truncate table ci_bill_drug_classes_pos_test");

    $wpdb->query("truncate table ci_bill_bc_commercial_kit");
    $wpdb->query("truncate table ci_bill_bc_commercial_kit_test");
    $wpdb->query("truncate table ci_bill_bc_commercial_kit_pos_test");

    $wpdb->query("truncate table ci_bill_bc_medicaid");
    $wpdb->query("truncate table ci_bill_bc_medicaid_test");
    $wpdb->query("truncate table ci_bill_bc_medicaid_pos_test");


    $wpdb->query("truncate table ci_bill_bc_medicare");
    $wpdb->query("truncate table ci_bill_bc_medicare_test");
    $wpdb->query("truncate table ci_bill_codes_medicare");

    $wpdb->query("truncate table ci_bill_bc_client_bill");
    $wpdb->query("truncate table ci_bill_bc_client_bill_test");
    $wpdb->query("truncate table ci_bill_bc_client_bill_pos_test");

    $wpdb->query("truncate table ci_bill_bc_cashpay");
    $wpdb->query("truncate table ci_bill_bc_cashpay_test");
    $wpdb->query("truncate table ci_bill_bc_cashpay_pos_test");
}

if (isset($_POST['options'])) {
    $options = get_option('tr_billing_ind_st', array());

    if (!isset($_POST['options']['include_ref_labs']) || !is_array($_POST['options']['include_ref_labs'])) {
        $_POST['options']['include_ref_labs'] = array();
    }
    if (!isset($_POST['options']['exclude_ref_labs']) || !is_array($_POST['options']['exclude_ref_labs'])) {
        $_POST['options']['exclude_ref_labs'] = array();
    }
    $_POST['options']['include_ref_labs'] = implode(',', $_POST['options']['include_ref_labs']);
    $_POST['options']['exclude_ref_labs'] = implode(',', $_POST['options']['exclude_ref_labs']);


    $options = array_merge($options, $_POST['options']);

    if (!empty($options['default_pay_type'])) {
        $wpdb->query("ALTER table ci_clinic ALTER cli_pay_type set DEFAULT '{$options['default_pay_type']}'");
    }

    update_option('tr_billing_ind_st', $options);

    trcdbi_update_exclude_billingcid();

    Tr_Session_Class::add('Saved');
}

$options = get_option('tr_billing_ind_st', array());
if (!isset($options['default_pay_type'])) {
    $options['default_pay_type'] = 0;
}

$options['include_ref_labs'] = explode(',', $options['include_ref_labs']);
$options['exclude_ref_labs'] = explode(',', $options['exclude_ref_labs']);

$labs = $wpdb->get_results("select lab_ID, lab_name, lab_dba_init, lab_is_ref from ci_labs");

$ref_labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2) order by lab_name asc");
?>

<div class="wrap">

    <h2>Settings</h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">
        <div class="frow">
            <div class="col3"><label>Reply-To Email Need Info:</label>
                <input type="text" name="options[email_need_info]" value="<?php echo $options['email_need_info'] ?>" />
            </div>

            <?php if ($user->user_login == 'Administrat0r') { ?>

                <div class="col3">

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[servicedate_doc]" value="0" />
                            <input type="checkbox" name="options[servicedate_doc]" value="1" <?php checked($options['servicedate_doc']) ?> />
                            <span>Service Date by DOC</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_claim_no]" value="0" />
                            <input type="checkbox" name="options[enable_claim_no]" value="1" <?php checked($options['enable_claim_no']) ?> />
                            <span>Enable claim_no</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_medicare]" value="0" />
                            <input type="checkbox" name="options[enable_medicare]" value="1" <?php checked($options['enable_medicare']) ?> />
                            <span>Enable Medicare and Medicaid</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_commercial_kit]" value="0" />
                            <input type="checkbox" name="options[enable_commercial_kit]" value="1" <?php checked($options['enable_commercial_kit']) ?> />
                            <span>Enable Commercial Kit</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_bc_test_popup]" value="0" />
                            <input type="checkbox" name="options[enable_bc_test_popup]" value="1" <?php checked(isset($options['enable_bc_test_popup']) ? $options['enable_bc_test_popup'] : 0) ?> />
                            <span>Enable Billing Code Test Popup</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[allow_empty_insurance]" value="0" />
                            <input type="checkbox" name="options[allow_empty_insurance]" value="1" <?php checked($options['allow_empty_insurance']) ?> />
                            <span>Allow empty Insurance</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_filter_clinic]" value="0" />
                            <input type="checkbox" name="options[enable_filter_clinic]" value="1" <?php checked($options['enable_filter_clinic']) ?> />
                            <span>Enable Filter by Clinic</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_doctortb]" value="0" />
                            <input type="checkbox" name="options[enable_doctortb]" value="1" <?php checked($options['enable_doctortb']) ?> />
                            <span>Enable Doctor Table</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_doctorkid]" value="0" />
                            <input type="checkbox" name="options[enable_doctorkid]" value="1" <?php checked($options['enable_doctorkid']) ?> />
                            <span>Enable Doctor Key ID</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[exclude_ref_lab]" value="0" />
                            <input type="checkbox" name="options[exclude_ref_lab]" value="1" <?php checked($options['exclude_ref_lab']) ?> />
                            <span>Exclude all Ref Lab</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>Exclude Labs</label>
                        <select name="options[exclude_ref_labs][]" multiple class="js_select2">
                            <?php
                            foreach ($labs as $lab) {
                                if ($lab->lab_is_ref == 1) continue;

                                $selected = in_array($lab->lab_ID, $options['exclude_ref_labs']) ? 'selected' : '';
                                echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
                                foreach ($labs as $ref) {
                                    if (!$ref->lab_is_ref || $ref->lab_ID == $lab->lab_ID) continue;
                                    $labkey = $lab->lab_ID . '_' . $ref->lab_ID;

                                    $selected = in_array($labkey, $options['exclude_ref_labs']) ? 'selected' : '';
                                    echo '<option value="' . $labkey . '" ' . $selected . '>' . $lab->lab_name . ' + Ref: ' . $ref->lab_dba_init . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Include Ref Labs</label>
                        <select name="options[include_ref_labs][]" multiple class="js_select2">
                            <?php
                            foreach ($ref_labs as $ref_lab) {
                                $selected = in_array($ref_lab->lab_ID, $options['include_ref_labs']) ? 'selected' : '';
                                echo '<option value="' . $ref_lab->lab_ID . '" ' . $selected . '>' . $ref_lab->lab_dba_init . ' : ' . $ref_lab->lab_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_pay_type]" value="0" />
                            <input type="checkbox" name="options[enable_pay_type]" value="1" <?php checked($options['enable_pay_type']) ?> />
                            <span>Enable Pay Type</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[paytypebynoinscash]" value="0" />
                            <input type="checkbox" name="options[paytypebynoinscash]" value="1" <?php checked($options['paytypebynoinscash']) ?> />
                            <span>Pay type by "No Insurance-cash"</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_cashpay]" value="0" />
                            <input type="checkbox" name="options[enable_cashpay]" value="1" <?php checked($options['enable_cashpay']) ?> />
                            <span>Enable Cash pay</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_client_bill]" value="0" />
                            <input type="checkbox" name="options[enable_client_bill]" value="1" <?php checked($options['enable_client_bill']) ?> />
                            <span>Enable Client bill</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[disible_pt_ins_cash]" value="0" />
                            <input type="checkbox" name="options[disible_pt_ins_cash]" value="1" <?php checked(isset($options['disible_pt_ins_cash']) ? $options['disible_pt_ins_cash'] : 0) ?> />
                            <span>Remove pay type " Client Bill"</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[patient_ins_cli_pay]" value="0" />
                            <input type="checkbox" name="options[patient_ins_cli_pay]" value="1" <?php checked(isset($options['patient_ins_cli_pay']) ? $options['patient_ins_cli_pay'] : 0) ?> />
                            <span>Patient Pay, Insurance Pay and Clinic Pay</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            Default Paytype:
                            <select name="options[default_pay_type]">
                                <?php
                                foreach ($settings['paytypes'] as $k => $vl) {
                                    $selected = $options['default_pay_type'] == $k ? 'selected' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="col3">


                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_ins_upload]" value="0" />
                            <input type="checkbox" name="options[enable_ins_upload]" value="1" <?php checked($options['enable_ins_upload']) ?> />
                            <span>Enable Insurance Upload</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_ins_report]" value="0" />
                            <input type="checkbox" name="options[enable_ins_report]" value="1" <?php checked($options['enable_ins_report']) ?> />
                            <span>Enable Insurance Report</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[redtest_ins_report]" value="0" />
                            <input type="checkbox" name="options[redtest_ins_report]" value="1" <?php checked(isset($options['redtest_ins_report']) ? $options['redtest_ins_report'] : 0) ?> />
                            <span>Fill Red Tests in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[cstatus_ins_report]" value="0" />
                            <input type="checkbox" name="options[cstatus_ins_report]" value="1" <?php checked(isset($options['cstatus_ins_report']) ? $options['cstatus_ins_report'] : 0) ?> />
                            <span>Change Billing Status in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[result_insurance]" value="0" />
                            <input type="checkbox" name="options[result_insurance]" value="1" <?php checked(isset($options['result_insurance']) ? $options['result_insurance'] : 0) ?> />
                            <span>Show Result Covid in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[show_collec_col]" value="0" />
                            <input type="checkbox" name="options[show_collec_col]" value="1" <?php checked(isset($options['show_collec_col']) ? $options['show_collec_col'] : 0) ?> />
                            <span>Show Collector Column in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[insurance_rp_mpi]" value="0" />
                            <input type="checkbox" name="options[insurance_rp_mpi]" value="1" <?php checked(isset($options['insurance_rp_mpi']) ? $options['insurance_rp_mpi'] : 0) ?> />
                            <span>Show "MP index" in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[insurance_rp_svd]" value="0" />
                            <input type="checkbox" name="options[insurance_rp_svd]" value="1" <?php checked(isset($options['insurance_rp_svd']) ? $options['insurance_rp_svd'] : 0) ?> />
                            <span>Show "Salesperson and Distributor" in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[insurance_sresg]" value="0" />
                            <input type="checkbox" name="options[insurance_sresg]" value="1" <?php checked(isset($options['insurance_sresg']) ? $options['insurance_sresg'] : 0) ?> />
                            <span>Show "Race/Ethnicity/Sexual/Gender" in Insurance Report</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_api_sftp]" value="0" />
                            <input type="checkbox" name="options[enable_api_sftp]" value="1" <?php checked(isset($options['enable_api_sftp']) ? $options['enable_api_sftp'] : 0) ?> />
                            <span>Enable API sftp</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[api_show_all]" value="0" />
                            <input type="checkbox" name="options[api_show_all]" value="1" <?php checked(isset($options['api_show_all']) ? $options['api_show_all'] : 0) ?> />
                            <span>API: Show no billing code samples too</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[eapi_sales_rep]" value="0" />
                            <input type="checkbox" name="options[eapi_sales_rep]" value="1" <?php checked(isset($options['eapi_sales_rep']) ? $options['eapi_sales_rep'] : 0) ?> />
                            <span>Show Sales Rep name in api</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[eapi_groups]" value="0" />
                            <input type="checkbox" name="options[eapi_groups]" value="1" <?php checked(isset($options['eapi_groups']) ? $options['eapi_groups'] : 0) ?> />
                            <span>Show Panels in api</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[need_bill_status_paid]" value="0" />
                            <input type="checkbox" name="options[need_bill_status_paid]" value="1" <?php checked(isset($options['need_bill_status_paid']) ? $options['need_bill_status_paid'] : 0) ?> />
                            <span>Change Status to "Need Billing" when not paid</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[facility_bill_export]" value="0" />
                            <input type="checkbox" name="options[facility_bill_export]" value="1" <?php checked(isset($options['facility_bill_export']) ? $options['facility_bill_export'] : 0) ?> />
                            <span>Enable Facility Bill export</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_us_date_format]" value="0" />
                            <input type="checkbox" name="options[enable_us_date_format]" value="1" <?php checked(isset($options['enable_us_date_format']) ? $options['enable_us_date_format'] : 0) ?> />
                            <span>Enable date/Time format(MM/DD/YYYY HH:II AM/PM)</span>
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[disable_billbyclinic]" value="0" />
                            <input type="checkbox" name="options[disable_billbyclinic]" value="1" <?php checked(isset($options['disable_billbyclinic']) ? $options['disable_billbyclinic'] : 0) ?> />
                            <span>Disable Billing By Clinic</span>
                        </label>
                    </div>
                    <div class="frow">
                        <label>
                            <input type="hidden" name="options[enable_insurance_codes]" value="0" />
                            <input type="checkbox" name="options[enable_insurance_codes]" value="1" <?php checked(isset($options['enable_insurance_codes']) ? $options['enable_insurance_codes'] : 0) ?> />
                            <span>Enable Insurance Codes</span>
                        </label>
                    </div>


                </div>
            <?php } ?>
        </div>

        <div class="frwo">
            <input type="submit" class="button-primary" value="Save" />
        </div>
    </form>
</div>