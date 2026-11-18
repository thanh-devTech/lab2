<?php
global $ci_all_btypes, $wpdb, $is_ci_admin, $is_ci_current_user;
include_once(TRCPDD_PATH . 'pages/formdata.php');
$sub_act = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$is_ci_current_user = !empty($is_ci_current_user) || (bool) get_user_meta(get_current_user_id(), '_is_ci_user', true);
$can_delete_main_lab = $is_ci_current_user && isset($_GET['debug']);

$insurance  = '[""]';
$states     = '[""]';
$exclusions = '[""]';
$auto_rej_insurance = '[""]';

$sign_dir = tr_get_signature_dir();
$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$lab_operation_columns = $wpdb->get_col("SHOW COLUMNS FROM ci_lab_operation");
if (!in_array('target_tat', $lab_operation_columns)) {
    $wpdb->query("ALTER TABLE ci_lab_operation ADD target_tat tinyint(3) default 36");
}

$lab = $wpdb->get_row(
    $wpdb->prepare(
        "select l.*, clo.lab_mon, clo.lab_tue, clo.lab_wed, clo.lab_thu, clo.lab_fri, clo.lab_sat, clo.lab_sun, clo.target_tat 
						from ci_labs l
						LEFT JOIN ci_lab_operation clo
						ON clo.id = (
							SELECT id
							FROM ci_lab_operation
							WHERE lab_ID = l.lab_ID
							ORDER BY ID DESC
							LIMIT 1
						)
						where l.lab_ID = %d",
        $lab_id
    ),
    ARRAY_A
);

$insurance  = !empty($lab['lab_insurance']) ? $lab['lab_insurance'] : '[""]';
$states     = !empty($lab['lab_states']) ? $lab['lab_states'] : '[""]';
$exclusions = !empty($lab['lab_exclusions']) ? $lab['lab_exclusions'] : '[""]';
$auto_rej_insurance = !empty($lab['lab_auto_rej_insurance']) ? $lab['lab_auto_rej_insurance'] : '[""]';

if (empty($lab['lab_start_date'])) {
    $lab['lab_start_date'] = date_i18n("Y-m-d");
}
if (empty($lab['target_tat'])) {
    $lab['target_tat'] = 36;
}

$is_ref = ($sub_act === '') ? ($lab['lab_is_ref'] == 1) : ($sub_act !== 'add_primary_lab');

if ($lab_id > 0) {
    $wpdb->query("delete from ci_lab_operation 
            where lab_ID = " . intval($lab['lab_ID']) . " 
            and lab_mon =0
            and lab_tue =0
            and lab_wed =0
            and lab_thu =0
            and lab_fri =0
            and lab_sat =0
            and lab_sun =0 ");
}
$editable_lab_init = ($lab_id > 0 && isset($_GET['debug'])) || ($lab_id == 0); //only allow edit lab_dba_init when create new lab or admin user
?>
<style>
    input[type=button],
    input[type=email],
    input[type=search],
    input[type=date],
    input[type=submit],
    input[type=tel],
    input[type=text],
    input[type=number],
    input[type=color],
    select,
    textarea,
    .selected {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: oklch(0.98 0 0) !important;
        border: none;
    }

    .wp-die-message {
        display: none;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: white;
        color: #6b7280;
        border-color: #0000001f;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #d83d00;
    }

    .js .tmce-active .wp-editor-area {
        color: #000000 !important;
    }

    label {
        margin-bottom: 5px;
    }

    .select2-container--default .select2-selection--multiple {
        border: 0px none;
    }

    .select2-container--default .select2-selection--multiple {
        background-color: oklch(0.98 0 0) !important;
        padding: 6px;
        padding-bottom: 0px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: white;
        color: #6b7280;
        border-color: #0000001f;
        margin-bottom: 0px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #d83d00;
    }

    input::placeholder {
        font-size: 12px;
    }

    .select2-container .select2-search--inline .select2-search__field {
        padding: 0 !important;
        margin-top: 0px;
        text-align: center;
    }

    body .select2-container .select2-selection--single,
    body .select2-container--default .select2-selection--single .select2-selection__arrow {
        border: none;
    }

    body .select2-container--default .select2-selection--single .select2-selection__rendered {
        background-color: #f9f9f9;
        border-radius: 10px;
        color: #4a5565;
        padding-right: 30px
    }

    .select2-container--default .select2-results__option[role=group] {
        font-size: 12px;
    }

    .select2-dropdown {
        border-top: solid 1px #3838384a !important;
        border-top-right-radius: 4px !important;
        border-top-left-radius: 4px !important;
        padding: 3px;
        box-shadow: 0px 1px 7px 0px #7878784a;
    }

    body .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: #f7c0c0ff;
        color: #d83d00;
    }

    .select2-results__options::-webkit-scrollbar {
        width: 6px;
    }

    .select2-results__options::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }

    .select2-results__options::-webkit-scrollbar-track {
        background: transparent;
    }

    .select2-results__option--highlighted:not(.select2-results__option--selected) {
        background-color: #f7c0c0ff !important;
        color: #d83d00 !important;
    }

    .select2-container--default .select2-selection--single {
        background-color: transparent !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        background-image: url("<?php echo TRCPDD_URL; ?>/images/down_arrow.png") !important;
        background-repeat: no-repeat;
        background-size: 100% 100%;
        height: 16px;
        left: 50%;
        margin-left: -14px;
        margin-top: -8px;
        position: absolute;
        top: 50%;
        width: 16px;
        border: none;
    }
</style>

<form id="reg_form" method="post" enctype="multipart/form-data">
    <div class="space-y-6">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="leading-none">Basic Information</h4>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="lab-name">Lab Name</label>
                            <input name="tblci_labs[lab_name]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="lab-name" placeholder="Enter lab name" value="<?php echo $lab['lab_name']; ?>" required>
                            <input name="tblci_labs[lab_ID]" type="hidden" value="<?php echo $lab['lab_ID']; ?>" />
                            <?php if ($sub_act == '') { ?>
                                <input name="tblci_labs[lab_is_ref]" type="hidden" value="<?php echo $lab['lab_is_ref']; ?>" />
                            <?php } else { ?>
                                <?php if ($sub_act == 'add_primary_lab') { ?>
                                    <input name="tblci_labs[lab_is_ref]" type="hidden" value="0" />
                                <?php } else { ?>
                                    <input name="tblci_labs[lab_is_ref]" type="hidden" value="1" />
                            <?php }
                            } ?>
                        </div>

                        <?php if (!$is_ref) { ?>
                            <div class="space-y-1.5">
                                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="lab-dba">Lab DBA</label>
                                <input name="tblci_labs[lab_dba]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="lab-dba" placeholder="Enter DBA name" value="<?php echo $lab['lab_dba']; ?>">
                            </div>
                        <?php } ?>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="dba-init">DBA INIT</label>
                            <input name="tblci_labs[lab_dba_init]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="dba-init" placeholder="Enter initials" <?php echo $editable_lab_init ? '' : 'readonly'; ?> value="<?php echo $lab['lab_dba_init']; ?>">
                        </div>

                        <?php if ($is_ref) { ?>
                            <div class="space-y-3 mt-6">
                                <div class="flex items-center space-x-2">
                                    <button name="tblci_labs[lab_ref_only_logo]" type="button" role="checkbox" <?php if ($lab['lab_ref_only_logo']) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="lab_ref_only_logo">
                                        <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($lab['lab_ref_only_logo']) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer" for="lab_ref_only_logo">Show only Ref Lab Logo</label>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <button name="tblci_labs[lab_ref_only_address]" type="button" role="checkbox" <?php if ($lab['lab_ref_only_address']) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="lab_ref_only_address">
                                        <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($lab['lab_ref_only_address']) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer" for="lab_ref_only_address">Show only Ref Lab Address</label>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <button name="tblci_labs[lab_ref_assen_init]" type="button" role="checkbox" <?php if ($lab['lab_ref_assen_init']) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="lab_ref_assen_init">
                                        <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($lab['lab_ref_assen_init']) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer" for="lab_ref_assen_init">Use Lab INIT For Accession</label>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if (!$is_ref) { ?>
                            <div class="space-y-1.5">
                                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="lab-site">Website</label>
                                <select id="lab_site_select" style="width: 100%;">
                                    <option value="">Select Site</option>
                                </select>

                                <input type="hidden" name="tblci_labs[lab_site]" id="lab_site" value="<?php echo $lab['lab_site']; ?>">
                            </div>
                        <?php } ?>

                    </div>

                    <div class="space-y-3">

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="ref-main-color">Main Color</label>
                            <div class="flex items-center gap-3">
                                <input id="picker_color_lab" type="color" name="tblci_labs[lab_color]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-20 h-10 cursor-pointer" id="ref-main-color" value="<?php echo $lab['lab_color']; ?>">
                                <input id="main_color_lab_inp" type="text" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" placeholder="#D83D00" value="<?php echo $lab['lab_color']; ?>">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="logo">Logo</label>
                            <div class="flex gap-2">
                                <input id="field_lab_logo" name="lab_logo" type="file" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" id="logo" accept="image/*">
                                <button onclick="$('#field_lab_logo').click();return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-4 w-4" aria-hidden="true">
                                        <path d="M12 3v12"></path>
                                        <path d="m17 8-5-5-5 5"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div id="area_logo_lab" onclick="$('#field_lab_logo').click();return false;" class="cursor-pointer relative flex justify-center items-center border-2 border-dashed rounded-lg p-4 bg-gray-50">

                            <?php
                            $logo_path = $logo_dir . '/' . $lab['lab_logo'];
                            if (!empty($lab['lab_logo']) && file_exists($logo_path) && is_file($logo_path)) {
                            ?>
                                <img id="img_logo_lab" src="<?php echo $logo_url . '/' . $lab['lab_logo']; ?>" alt="Laboratory Logo" class="h-32 w-auto object-contain">
                                <button id="btn_del_logo_lab" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute top-2 right-2 h-8 w-8 rounded bg-white/90 hover:bg-white text-red-600 hover:text-red-700 shadow-sm p-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true">
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            <?php } else { ?>
                                <input id="field_lab_logo" name="lab_logo" type="file" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" id="logo" accept="image/*">
                            <?php } ?>
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="results-logo">Results Logo</label>
                            <div class="flex gap-2">
                                <input id="field_lab_results_logo" name="lab_results_logo" type="file" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" accept="image/*">
                                <button onclick="$('#field_lab_results_logo').click();return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-4 w-4" aria-hidden="true">
                                        <path d="M12 3v12"></path>
                                        <path d="m17 8-5-5-5 5"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div id="area_results_logo_lab" onclick="$('#field_lab_results_logo').click();return false;" class="cursor-pointer relative flex justify-center items-center border-2 border-dashed rounded-lg p-4 bg-gray-50">
                            <?php
                            $results_logo_file = isset($lab['lab_results_logo']) ? $lab['lab_results_logo'] : '';
                            $results_logo_path = $logo_dir . '/' . $results_logo_file;
                            if (!empty($results_logo_file) && file_exists($results_logo_path) && is_file($results_logo_path)) {
                            ?>
                                <img id="img_results_logo_lab" src="<?php echo $logo_url . '/' . $results_logo_file; ?>" alt="Results Logo" class="h-32 w-auto object-contain">
                                <button id="btn_del_results_logo_lab" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute top-2 right-2 h-8 w-8 rounded bg-white/90 hover:bg-white text-red-600 hover:text-red-700 shadow-sm p-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true">
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            <?php } ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="leading-none">Contact Information</h4>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3 md:col-span-2">
                        <div class="space-y-1.5"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="address">Address</label>
                            <input name="tblci_labs[lab_address]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="address" placeholder="Street address" value="<?php echo $lab['lab_address']; ?>">
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="city">City</label>
                            <input name="tblci_labs[lab_city]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="city" placeholder="City" value="<?php echo $lab['lab_city']; ?>">
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="state">State</label>
                            <input name="tblci_labs[lab_state]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="state" placeholder="State" value="<?php echo $lab['lab_state']; ?>">
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="zip">Zip</label>
                            <input name="tblci_labs[lab_zip]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="zip" placeholder="Zip code" value="<?php echo $lab['lab_zip']; ?>">
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="phone">Phone</label>
                            <input name="tblci_labs[lab_phone]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="phone" placeholder="Phone number" value="<?php echo $lab['lab_phone']; ?>">
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="fax">Fax</label>
                            <input name="tblci_labs[lab_fax]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="fax" placeholder="Fax number" value="<?php echo $lab['lab_fax']; ?>">
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="email">Email</label>
                            <input name="tblci_labs[lab_email]" type="email" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="email" placeholder="email@example.com" value="<?php echo $lab['lab_email']; ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <h4 data-slot="card-title" class="leading-none">Certifications &amp; IDs</h4>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="tax-id">Tax ID</label>
                        <input name="tblci_labs[lab_tax_id]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="tax-id" placeholder="Tax ID number" value="<?php echo $lab['lab_tax_id']; ?>">
                    </div>

                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="npi">NPI</label>
                        <input name="tblci_labs[lab_npi]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="npi" placeholder="NPI number" value="<?php echo $lab['lab_npi']; ?>">
                    </div>

                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="test-in-pdf">PDF text above CLIA</label>
                        <input name="tblci_labs[lab_second_line_hpdf]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="test-in-pdf" placeholder="Text information for PDF" value="<?php echo $lab['lab_second_line_hpdf']; ?>">
                    </div>
                </div>

                <div class="space-y-3">

                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="clia">CLIA</label>
                        <input name="tblci_labs[lab_clia]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="clia" placeholder="CLIA number" value="<?php echo $lab['lab_clia']; ?>">
                    </div>

                    <?php if ($is_ref) { ?>
                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cap">Director</label>
                            <input name="tblci_labs[lab_director]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="director" placeholder="Director name" value="<?php echo $lab['lab_director']; ?>">
                        </div>

                    <?php } ?>
                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cola">COLA</label>
                        <input name="tblci_labs[lab_cola]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="cola" placeholder="COLA number" value="<?php echo $lab['lab_cola']; ?>">
                    </div>

                    <div class="space-y-1.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cap">CAP</label>
                        <input name="tblci_labs[lab_cap]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="cap" placeholder="CAP number" value="<?php echo $lab['lab_cap']; ?>">
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_ref) { ?>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Financial Settings</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="billing-account">Billing Account</label>
                                <input name="tblci_labs[lab_billing_account]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="billing-account" placeholder="Billing account number" value="<?php echo $lab['lab_billing_account']; ?>">
                            </div>

                            <?php if (in_array('tox', $ci_all_btypes)) { ?>
                                <div class="space-y-1.5">
                                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="specimen-fee">Specimen Fee</label>
                                    <input name="tblci_labs[lab_specimen_fee]" type="number" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="specimen-fee" step="0.01" placeholder="0.00" value="<?php echo $lab['lab_specimen_fee']; ?>">
                                </div>

                            <?php } ?>

                            <div class="space-y-1.5">
                                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="test_types">Lab Test Type</label>
                                <select class="js_select2" id="test_types" name="tblci_labs[lab_ref_test_type][]" multiple placeholder="Select test type" style="width:100%">
                                    <?php
                                    $test_types = [];
                                    foreach ($ci_all_btypes as $type) {
                                        if ($type === 'tox') {
                                            $test_types['screen'] = 'Tox_Screening';
                                            $test_types['tox'] = 'Tox_Confirmation';
                                            $test_types['meth_dl'] = 'Tox_Methamphetamine D/L';
                                            $test_types['urine'] = 'Tox_Urine Validity';
                                        } else {
                                            $test_types[$type] = tr_pdd_get_typename($type);
                                        }
                                    }
                                    $lab['lab_ref_test_type'] = explode(',', $lab['lab_ref_test_type'] ?? '');
                                    foreach ($test_types as $type => $label) {
                                        $selected = in_array($type, $lab['lab_ref_test_type']) ? 'selected' : '';
                                        echo '<option value="' . $type . '" ' . $selected . '>' . $label . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <?php if (in_array('dna', $ci_all_btypes)) { ?>
                                <div class="space-y-1.5">
                                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="dna-fee">DNA Fee</label>
                                    <input name="tblci_labs[lab_dna_cost]" type="number" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="dna-fee" step="0.01" placeholder="0.00" value="<?php echo $lab['lab_dna_cost']; ?>">
                                </div>
                            <?php } ?>
                        </div>

                        <div class="space-y-3">
                            <?php if (in_array('tox', $ci_all_btypes)) { ?>
                                <div class="space-y-1.5">
                                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="tox-cost">Tox Cost</label>
                                    <input name="tblci_labs[lab_tox_cost]" type="number" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="tox-cost" step="0.01" placeholder="0.00" value="<?php echo $lab['lab_tox_cost']; ?>">
                                </div>

                            <?php } ?>

                            <?php if (in_array('pcr', $ci_all_btypes)) { ?>
                                <div class="space-y-1.5">
                                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="pcr-cost">PCR Cost</label>
                                    <input type="number" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="pcr-cost" step="0.01" placeholder="0.00" value="">
                                </div>
                            <?php } ?>
                            <?php if (in_array('blood', $ci_all_btypes)) { ?>
                                <div class="space-y-1.5">
                                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="blood-cost">Blood Cost</label>
                                    <input name="tblci_labs[lab_blood_cost]" type="number" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="blood-cost" step="0.01" placeholder="0.00" value="<?php echo $lab['lab_blood_cost']; ?>">
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if (!$is_ref) { ?>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Operations</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="start-date">Start Date</label>
                            <input name="tblci_labs[lab_start_date]" value="<?php echo date('Y-m-d', strtotime($lab['lab_start_date'])); ?>"
                                type="date"
                                data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                id="start-date" />
                        </div>

                        <div class="space-y-1.5">
                            <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="target-tat">Target TAT</label>
                            <select name="tblci_labs[target_tat]" id="target-tat" style="width: 100%;">
                                <?php foreach (array(12, 24, 36, 48, 60, 72) as $target_tat) { ?>
                                    <option value="<?php echo $target_tat; ?>" <?php selected(intval($lab['target_tat']), $target_tat); ?>><?php echo $target_tat; ?>h</option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600">Days of Operation</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <?php
                            $has_operation = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT COUNT(*) FROM ci_lab_operation WHERE lab_ID = %d",
                                    $lab_id
                                )
                            );

                            foreach ($keys_operation as $key => $item) {
                                $is_show = true;
                                if ($has_operation == 0) {
                                    $is_show = true;
                                } else {
                                    $is_show = !empty($lab[$key]);
                                }
                            ?>
                                <div class="flex items-center space-x-2">
                                    <button name="tblci_labs[<?php echo $key; ?>]" type="button" role="checkbox" <?php if ($is_show) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="day-<?php echo $key; ?>">
                                        <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($is_show) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <label data-slot="label" class="flex items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer text-sm" for="day-<?php echo $key; ?>"><?php echo $item; ?></label>
                                </div>
                            <?php } ?>

                        </div>
                    </div>
                    <?php if ($lab_id > 0) { ?>
                        <div class="flex items-center space-x-2">
                            <button id="btn_operation_logs" style="color: #5D6657;" data-slot="button" class="cursor-pointer inline-flex items-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 text-blue-600 hover:bg-primary/5 justify-start">
                                <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.07868 5.06891C8.87402 1.27893 15.0437 1.31923 18.8622 5.13778C22.6824 8.95797 22.7211 15.1313 18.9262 18.9262C15.1312 22.7211 8.95793 22.6824 5.13774 18.8622C2.87389 16.5984 1.93904 13.5099 2.34047 10.5812C2.39672 10.1708 2.775 9.88377 3.18537 9.94002C3.59575 9.99627 3.88282 10.3745 3.82658 10.7849C3.4866 13.2652 4.27782 15.881 6.1984 17.8016C9.44288 21.0461 14.6664 21.0646 17.8655 17.8655C21.0646 14.6664 21.046 9.44292 17.8015 6.19844C14.5587 2.95561 9.33889 2.93539 6.13935 6.12957L6.88705 6.13333C7.30126 6.13541 7.63535 6.47288 7.63327 6.88709C7.63119 7.3013 7.29372 7.63539 6.87951 7.63331L4.33396 7.62052C3.92269 7.61845 3.58981 7.28556 3.58774 6.8743L3.57495 4.32874C3.57286 3.91454 3.90696 3.57707 4.32117 3.57498C4.73538 3.5729 5.07285 3.907 5.07493 4.32121L5.07868 5.06891Z" fill="#707070"></path>
                                        <path opacity="0.5" d="M12 7.25C12.4142 7.25 12.75 7.58579 12.75 8V11.6893L15.0303 13.9697C15.3232 14.2626 15.3232 14.7374 15.0303 15.0303C14.7374 15.3232 14.2626 15.3232 13.9697 15.0303L11.5429 12.6036C11.3554 12.416 11.25 12.1617 11.25 11.8964V8C11.25 7.58579 11.5858 7.25 12 7.25Z" fill="#707070"></path>
                                    </g>
                                </svg>
                                Lab operation logs
                            </button>
                        </div>
                    <?php } ?>

                </div>
            </div>


            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Director Information</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="space-y-1.5"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="director">Director</label><input name="tblci_labs[lab_director]" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="director" placeholder="Director name" value="<?php echo $lab['lab_director']; ?>"></div>
                        </div>
                        <div class="space-y-3">

                            <div class="space-y-1.5">
                                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="director-signature">Director Signature</label>
                                <div class="flex gap-2">
                                    <input id="field_upload_file_signature" name="lab_director_signature" type="file" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" id="director-signature" accept="image/*">
                                    <button onclick="$('#field_upload_file_signature').click();return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-4 w-4" aria-hidden="true">
                                            <path d="M12 3v12"></path>
                                            <path d="m17 8-5-5-5 5"></path>
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <div class="card_file">
                                    <input type="hidden" name="tblci_labs[lab_director_signature]" value="<?php echo $lab['lab_director_signature'] ?>" />

                                    <div class="relative flex justify-center items-center border-2 border-dashed rounded-lg p-4 bg-gray-50">
                                        <span class="view">
                                            <?php

                                            echo '<div class="signaturepad" style="' . (empty($lab['lab_director_signature']) ? '' : 'display:none') . '">';
                                            echo tr_signature(array('name' => 'lab_director_signature', 'required' => false, 'clear' => false));
                                            echo '</div>';

                                            if (!empty($lab['lab_director_signature'])) {
                                                echo '<img src="' . $sign_dir['url'] . '/' . $lab['lab_director_signature'] . '?' . time() . '" style="max-height:100px;background:#fff" alt="Director Signature" class="h-24 w-auto object-contain">';
                                            }
                                            ?>
                                        </span>

                                        <button data-slot="button" class="remove cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute top-2 right-2 h-8 w-8 rounded bg-white/90 hover:bg-white text-red-600 hover:text-red-700 shadow-sm p-0"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true">
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg></button>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($lab_id > 0) { ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Result Footers</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                            <?php
                            foreach ($ci_all_btypes as $index => $btype) {
                                $textColor = tr_pdd_get_typecolor($btype);
                            ?>
                                <button style="color: <?php echo $textColor; ?>;" onclick="window.location='?page=<?php echo $slug_main; ?>&act=footers&btype=<?php echo $btype; ?>&labid=<?php echo $lab['lab_ID']; ?>';return false;" data-slot="button" class="cursor-pointer inline-flex items-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 text-blue-600 hover:bg-primary/5 justify-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
                                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                                    </svg><?php echo strtoupper($btype); ?> Result Footer
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>

        <?php if ($is_ref) { ?>

            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Clinics</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="space-y-1.5"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="ref-clinics">Select Clinics</label>
                        <div class="relative">
                            <?php
                            $selected_clinics = [];
                            $clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic");
                            if (!empty($lab_id)) {
                                $selected_clinics = $wpdb->get_col(
                                    $wpdb->prepare(
                                        "SELECT cID FROM ci_clinic WHERE cli_ref_lab_ID = %d",
                                        $lab_id
                                    )
                                );
                            }
                            ?>
                            <select id="list_clinics" name="clinics[]" multiple placeholder="Select clinics..." style="width:100%">
                                <?php
                                foreach ($clinics as $clinic) {
                                    $selected = in_array($clinic->cID, $selected_clinics) ? 'selected' : '';
                                    echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (in_array('pcr', $ci_all_btypes)) {
                $data['lab_pcr_panels'] = explode(',', $lab['lab_pcr_panels']);
                $panels = $wpdb->get_results("select tg_ID, tg_name 
                from ci_test_groups where tg_type = 'pcr' and tg_show_req = 1 
                order by tg_position asc");
            ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">PCR Panels <span class="text-sm text-gray-500">(restrict ordering to selected panels)</span></h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="ref-pcr-panels">Select PCR Panels</label>
                        <div class="frow">
                            <select id="lab_pcr_panels" name="tblci_labs[lab_pcr_panels][]" multiple="true">
                                <?php
                                foreach ($panels as $panel) {
                                    $selected = in_array($panel->tg_ID, $data['lab_pcr_panels']) ? 'selected' : '';
                                    echo '<option value="' . $panel->tg_ID . '" ' . $selected . '>' . $panel->tg_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Additional Footer</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="ref-additional-footer">Additional Footer Text</label>
                    <?php

                    wp_editor(
                        $lab['lab_additional'],
                        'lab_additional',
                        array(
                            'textarea_name' => 'lab_additional',
                            'media_buttons' => true,
                            'textarea_rows' => 8,
                            'teeny' => false
                        )
                    );
                    ?>

                </div>
            </div>

            <?php
            $insurance  = !empty($lab['lab_insurance']) ? $lab['lab_insurance'] : '[""]';
            $states     = !empty($lab['lab_states']) ? $lab['lab_states'] : '[""]';
            $exclusions = !empty($lab['lab_exclusions']) ? $lab['lab_exclusions'] : '[""]';
            ?>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Properties</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                    <div class="frow list_properties grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="w-100">
                            <label>Insurance</label>
                            <ul id="list_insurance"></ul>
                            <button class="addnew cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[>svg]:px-2.5 w-6 h-6 p-0 border-0 bg-gray-100 hover:bg-gray-200 text-gray-600" data-rel="insurance">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3 w-3" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5v14"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="w-100">
                            <label>States</label>
                            <ul id="list_states"></ul>
                            <button class="addnew cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[>svg]:px-2.5 w-6 h-6 p-0 border-0 bg-gray-100 hover:bg-gray-200 text-gray-600" data-rel="states">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3 w-3" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5v14"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="w-100">
                            <label>Exclusions</label>
                            <ul id="list_exclusions"></ul>
                            <button class="addnew cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[>svg]:px-2.5 w-6 h-6 p-0 border-0 bg-gray-100 hover:bg-gray-200 text-gray-600" data-rel="exclusions">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3 w-3" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5v14"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (!$is_ref) { ?>
            <?php if (in_array('tox', $ci_all_btypes)) { ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Tox Settings</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="flex items-center space-x-2">
                                    <button name="tblci_labs[lab_not_specimen]" type="button" role="checkbox" <?php if ($lab['lab_not_specimen']) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="disable-specimen-validity">
                                        <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($lab['lab_not_specimen']) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer" for="disable-specimen-validity">Disable SPECIMEN VALIDITY TEST</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($is_ci_admin) { ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Admin Settings</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
                        <div class="flex items-center space-x-2">
                            <button name="tblci_labs[lab_status]" type="button" role="checkbox" <?php if ($lab['lab_status']) { ?>aria-checked="true" data-state="checked" value="1" <?php } else { ?>aria-checked="false" data-state="unchecked" value="0" <?php } ?> data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="enable-system">
                                <span data-state="unchecked" data-slot="checkbox-indicator" class="<?php if ($lab['lab_status']) { ?>flex<?php } else { ?>hidden<?php } ?> items-center justify-center text-current transition-none" style="pointer-events: none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                        <path d="M20 6 9 17l-5-5"></path>
                                    </svg>
                                </span>
                            </button>
                            <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 cursor-pointer" for="enable-system">Enable Lab</label>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (tr_get_option('ci_pdf_data', 'req_auto_rej_ins')) { ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Auto Reject List</h4>
                        <p class="text-sm text-muted-foreground mt-1">Insurance names in this list will be auto-rejected for this lab.</p>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="space-y-2">
                            <label class="text-xs text-gray-600 font-medium">Insurance</label>
                            <ul id="list_auto_rej_insurance" class="space-y-2"></ul>
                            <button class="addnew cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 w-8 h-8 p-0 border-0 bg-gray-100 hover:bg-gray-200 text-gray-600" data-rel="auto_rej_insurance">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5v14"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>

        <div class="flex justify-end gap-3 pb-8">
            <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=list';return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">Cancel</button>
            <?php if ($is_ci_admin && $lab_id > 0) { ?>
                <button id="btn_lab_history" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history h-4 w-4" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                        <path d="M12 7v5l4 2"></path>
                    </svg>
                    History
                </button>
            <?php } ?>
            <?php if ($is_ref) { ?>
                <button data-id-lab="<?php echo $lab_id; ?>" data-slot="button" class="btn_list_fees cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 w-50">
                    <svg fill="#737373" width="64px" height="64px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" stroke="#545454">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                            <title>list</title>
                            <path d="M0 26.016v-20q0-2.496 1.76-4.256t4.256-1.76h20q2.464 0 4.224 1.76t1.76 4.256v20q0 2.496-1.76 4.224t-4.224 1.76h-20q-2.496 0-4.256-1.76t-1.76-4.224zM4 26.016q0 0.832 0.576 1.408t1.44 0.576h20q0.8 0 1.408-0.576t0.576-1.408v-20q0-0.832-0.576-1.408t-1.408-0.608h-20q-0.832 0-1.44 0.608t-0.576 1.408v20zM8 24v-4h4v4h-4zM8 18.016v-4h4v4h-4zM8 12v-4h4v4h-4zM14.016 24v-4h9.984v4h-9.984zM14.016 18.016v-4h9.984v4h-9.984zM14.016 12v-4h9.984v4h-9.984z"></path>
                        </g>
                    </svg>
                    Fees
                </button>
            <?php } ?>
            <?php if ($lab_id > 0 && (($is_ci_current_user && $is_ref) || ($can_delete_main_lab && !$is_ref))) { ?>
                <button type="button" data-url="?page=<?php echo $slug_main; ?>&act=delete&lab_id=<?php echo $lab_id; ?><?php echo $can_delete_main_lab ? '&debug=1' : ''; ?>" data-slot="button" class="btn_delete_ref_lab_detail cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border border-red-200 bg-white text-red-600 hover:bg-red-50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 h-4 w-4" aria-hidden="true">
                        <path d="M3 6h18"></path>
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                    </svg>
                    Delete
                </button>
            <?php } ?>

            <button id="reg_lab_submit" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2" aria-hidden="true">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                </svg>
                Save Laboratory Profile
            </button>
        </div>
    </div>
</form>

<?php
$lab_sites = [];

if (function_exists('get_sites') && $GLOBALS['blog_id'] <= 1) {
    $sites = get_sites();
    foreach ($sites as $site) {
        if (stripos($site->domain, 'patients.') !== false) continue;
        if (stripos($site->domain, 'results.') !== false) continue;

        $lab_sites[] = [
            'label' => '(#' . $site->blog_id . ') ' . $site->domain,
            'value' => $site->domain,
            'id'    => $site->blog_id
        ];
    }
}

?>

<script>
    const LAB_SITES = <?php echo json_encode($lab_sites); ?>;
    var $ = jQuery;

    var $insurance = <?php echo $insurance ?>;
    var $states = <?php echo $states ?>;
    var $exclusions = <?php echo $exclusions ?>;
    var $auto_rej_insurance = <?php echo $auto_rej_insurance ?>;
    var ciLabMaxlengths = {
        lab_name: 75,
        lab_dba: 75,
        lab_dba_init: 15,
        lab_city: 100,
        lab_state: 10,
        lab_zip: 12,
        lab_npi: 30,
        lab_tax_id: 30,
        lab_clia: 30,
        lab_color: 8
    };

    $.each(ciLabMaxlengths, function(field, length) {
        $('[name="tblci_labs[' + field + ']"]').attr('maxlength', length);
    });

    function updateCheckboxUI($el) {
        const isChecked = $el.attr("data-state") === "checked";

        $el.attr({
            "aria-checked": isChecked ? "true" : "false",
            "value": isChecked ? "1" : "0"
        });

        $el.find("span")
            .toggleClass("flex", isChecked)
            .toggleClass("hidden", !isChecked);
    }

    $(document).on('click', 'button[role="checkbox"]', function() {
        const $btn = $(this);
        const name = $btn.attr("name");
        const newState = $btn.attr("data-state") === "unchecked" ? "checked" : "unchecked";

        $btn.attr("data-state", newState);
        updateCheckboxUI($btn);

        // xử lý riêng cho edit_date_opera
        //if (name === 'edit_date_opera') {
        //const isChecked = newState === "checked";
        //$("#edit_date_opera_area").toggleClass("flex", isChecked).toggleClass("hidden", !isChecked);
        //}
    });

    function dataURLtoBlob(dataurl) {
        let arr = dataurl.split(',');
        let mime = arr[0].match(/:(.*?);/)[1];
        let bstr = atob(arr[1]);
        let n = bstr.length;
        let u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], {
            type: mime
        });
    }

    function isCanvasBlank(canvas) {
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        return canvas.toDataURL() === blank.toDataURL();
    }

    function updateUiUpload() {
        $("#btn_del_logo_lab").click(function() {
            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                trajax: 'remove_logo',
                lab_ID: '<?php echo $lab['lab_ID']; ?>'
            }, function(response) {
                Swal.fire({
                    title: "Remove Logo",
                    text: response,
                    icon: "success"
                });
            });
            $("#area_logo_lab").empty();
            return false;
        });

        $("#btn_del_results_logo_lab").click(function() {
            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                trajax: 'remove_results_logo',
                lab_ID: '<?php echo $lab['lab_ID']; ?>'
            }, function(response) {
                Swal.fire({
                    title: "Remove Results Logo",
                    text: response,
                    icon: "success"
                });
            });
            $("#area_results_logo_lab").empty();
            return false;
        });
    }

	    const trackedLabFieldGroups = {
        address: {
            label: 'Address',
            fields: ['lab_address', 'lab_city', 'lab_state', 'lab_zip']
        },
        phone: {
            label: 'Phone',
            fields: ['lab_phone']
        },
        clia: {
            label: 'CLIA',
            fields: ['lab_clia']
        },
        cola: {
            label: 'COLA',
            fields: ['lab_cola']
        },
        cap: {
            label: 'CAP',
            fields: ['lab_cap']
        },
        medical_director: {
            label: 'Medical Director',
            fields: ['lab_director']
        },
        director_signature: {
            label: 'Director Signature',
            fields: ['lab_director_signature']
        },
        additional_footer: {
            label: 'Additional Footer Text',
            fields: ['lab_additional']
        },
        target_tat: {
            label: 'Target TAT',
            fields: ['target_tat']
	        }
	    };
	    const trackedLabFieldLabels = {
	        lab_address: 'Address',
	        lab_city: 'City',
	        lab_state: 'State',
	        lab_zip: 'Zip',
	        lab_phone: 'Phone',
	        lab_clia: 'CLIA',
	        lab_cola: 'COLA',
	        lab_cap: 'CAP',
	        lab_director: 'Medical Director',
	        lab_director_signature: 'Director Signature',
	        lab_additional: 'Additional Footer Text',
	        target_tat: 'Target TAT'
	    };
    let originalTrackedLabValues = <?php echo wp_json_encode(array(
                                        'lab_address' => isset($lab['lab_address']) ? $lab['lab_address'] : '',
                                        'lab_city' => isset($lab['lab_city']) ? $lab['lab_city'] : '',
                                        'lab_state' => isset($lab['lab_state']) ? $lab['lab_state'] : '',
                                        'lab_zip' => isset($lab['lab_zip']) ? $lab['lab_zip'] : '',
                                        'lab_phone' => isset($lab['lab_phone']) ? $lab['lab_phone'] : '',
                                        'lab_clia' => isset($lab['lab_clia']) ? $lab['lab_clia'] : '',
                                        'lab_cola' => isset($lab['lab_cola']) ? $lab['lab_cola'] : '',
                                        'lab_cap' => isset($lab['lab_cap']) ? $lab['lab_cap'] : '',
                                        'lab_director' => isset($lab['lab_director']) ? $lab['lab_director'] : '',
                                        'lab_director_signature' => isset($lab['lab_director_signature']) ? $lab['lab_director_signature'] : '',
                                        'lab_additional' => isset($lab['lab_additional']) ? $lab['lab_additional'] : '',
                                        'target_tat' => isset($lab['target_tat']) ? $lab['target_tat'] : 36,
                                    )); ?>;

    function normalizeTrackedLabValue(value) {
        return $.trim(value === undefined || value === null ? '' : String(value));
    }

    function getTrackedLabValues() {
        let values = {};
        $.each(trackedLabFieldGroups, function(groupKey, group) {
            $.each(group.fields, function(index, fieldKey) {
                if (fieldKey === 'lab_additional') {
                    const editor = typeof tinymce !== 'undefined' ? tinymce.get('lab_additional') : null;
                    values[fieldKey] = normalizeTrackedLabValue(editor ? editor.getContent() : $('[name="lab_additional"]').val());
                } else {
                    values[fieldKey] = normalizeTrackedLabValue($('[name="tblci_labs[' + fieldKey + ']"]').val());
                }
            });
        });

        let fileSig = document.querySelector('input[type=file][name=lab_director_signature]');
        let canvas = document.querySelector('.signaturepad canvas');
        if (fileSig && fileSig.files.length) {
            values.lab_director_signature = '__pending_upload__';
        } else if (canvas && !isCanvasBlank(canvas)) {
            values.lab_director_signature = '__pending_draw__';
        }

        return values;
    }

	    function getChangedTrackedLabGroups() {
	        const currentValues = getTrackedLabValues();
	        const changedGroups = [];
	        const changedFields = [];

	        $.each(trackedLabFieldGroups, function(groupKey, group) {
	            let isChanged = false;

	            $.each(group.fields, function(index, fieldKey) {
	                if (normalizeTrackedLabValue(originalTrackedLabValues[fieldKey]) !== currentValues[fieldKey]) {
	                    isChanged = true;
	                    changedFields.push({
	                        label: trackedLabFieldLabels[fieldKey] || fieldKey,
	                        oldValue: normalizeTrackedLabValue(originalTrackedLabValues[fieldKey]),
	                        newValue: currentValues[fieldKey]
	                    });
	                }
	            });

	            if (isChanged) {
                changedGroups.push(group.label);
            }
        });

	        return {
	            labels: changedGroups,
	            fields: changedFields,
	            values: currentValues
	        };
	    }

	    function escapeLabChangeHtml(value) {
	        return $('<div>').text(value === undefined || value === null || value === '' ? 'empty' : value).html();
	    }

	    function buildTrackedLabChangesHtml(changedFields) {
	        let rows = '';

	        $.each(changedFields, function(index, field) {
	            rows += '<tr>' +
	                '<th style="text-align:left;padding:6px;border-top:1px solid #eee;width:30%;">' + escapeLabChangeHtml(field.label) + '</th>' +
	                '<td style="text-align:left;padding:6px;border-top:1px solid #eee;">' + escapeLabChangeHtml(field.oldValue) + '</td>' +
	                '<td style="text-align:left;padding:6px;border-top:1px solid #eee;">' + escapeLabChangeHtml(field.newValue) + '</td>' +
	                '</tr>';
	        });

	        return '<div style="font-size:14px;line-height:1.5;text-align:left;">' +
	            '<div style="margin-bottom:8px;">Please choose the effective date for these changed fields:</div>' +
	            '<table style="width:100%;border-collapse:collapse;font-size:12px;">' +
	            '<thead><tr><th style="text-align:left;padding:6px;">Field</th><th style="text-align:left;padding:6px;">Old</th><th style="text-align:left;padding:6px;">New</th></tr></thead>' +
	            '<tbody>' + rows + '</tbody></table></div>';
	    }

	    async function askTrackedLabEffectiveDate(trackedChanges) {
	        const defaultDate = new Date().toISOString().split('T')[0];
	        const result = await Swal.fire({
	            title: 'Effective date required',
	            html: buildTrackedLabChangesHtml(trackedChanges.fields),
	            input: 'date',
	            inputValue: defaultDate,
	            inputAttributes: {
	                required: 'required'
	            },
	            showCancelButton: true,
	            confirmButtonText: 'Continue',
	            preConfirm: (value) => {
                if (!value) {
                    Swal.showValidationMessage('Please choose an effective date.');
                }
                return value;
            }
        });

        if (!result.isConfirmed) {
            return '';
        }

        return result.value;
    }

    jQuery(document).ready(function($) {

        $(".js_select2").select2();

        $(".btn_delete_ref_lab_detail").click(function() {
            var url = $(this).data('url');
            Swal.fire({
                title: 'Delete lab?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location = url;
                }
            });
        });

        $('#reg_lab_submit').on('click', async function(e) {
            e.preventDefault();

            const trackedChanges = getChangedTrackedLabGroups();
	            let effectiveDate = '';

	            if (trackedChanges.labels.length) {
	                effectiveDate = await askTrackedLabEffectiveDate(trackedChanges);

                if (!effectiveDate) {
                    return;
                }
            }

            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }

            let form = document.getElementById('reg_form');
            let formData = new FormData(form);

            formData.append('trajax', 'reg_lab');

            if (effectiveDate) {
                formData.append('lab_effective_date', effectiveDate);
            }

            //if (typeof tinymce !== 'undefined' && tinymce.get('lab_additional')) {
            //var lab_additional = tinymce.get('lab_additional').getContent();
            // formData.set('tblci_labs[lab_additional]', lab_additional);
            //}

            <?php if (!$is_ref) { ?>
                let fileSig = form.querySelector('input[type=file][name=lab_director_signature]');
                if (fileSig.files.length) {
                    formData.set('lab_director_signature', fileSig.files[0]);
                } else {
                    let canvas = document.querySelector('.signaturepad canvas');
                    if (canvas && !isCanvasBlank(canvas)) {
                        let sigDataUrl = canvas.toDataURL('image/png');
                        let sigBlob = dataURLtoBlob(sigDataUrl);
                        formData.set('lab_director_signature', sigBlob, 'signature.png');
                    }
                }
            <?php } ?>

            let fileLogo = form.querySelector('input[type=file][name=lab_logo]');
            if (fileLogo.files.length) {
                formData.set('lab_logo', fileLogo.files[0]);
            }

            let fileResultsLogo = form.querySelector('input[type=file][name=lab_results_logo]');
            if (fileResultsLogo.files.length) {
                formData.set('lab_results_logo', fileResultsLogo.files[0]);
            }

            //CheckBox
            $('button[role="checkbox"]').each(function() {
                let $btn = $(this);
                let name = $btn.attr('name');
                let value = $btn.attr('value');
                if (name != "" && name != undefined) formData.append(name, value);
            });

            Swal.fire({
                title: 'Laboratory Profile',
                text: 'Saving...Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'admin.php?page=<?php echo $slug_main; ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success(response) {
                    if (response.success) {
                        if (response.data_update && Object.prototype.hasOwnProperty.call(response.data_update, 'signature')) {
                            const hiddenSignature = $('input[type="hidden"][name="tblci_labs[lab_director_signature]"]');
                            const signaturePad = $('.card_file .signaturepad');
                            const signatureView = $('.card_file .view');
                            const fileSignature = $('#field_upload_file_signature');

                            hiddenSignature.val(response.data_update.signature);
                            signaturePad.hide();
                            signatureView.find('img').remove();

                            if (response.data_update.signature) {
                                signatureView.append('<img src="<?php echo $sign_dir['url']; ?>/' + response.data_update.signature + '?' + Date.now() + '" style="max-height:100px;background:#fff" alt="Director Signature" class="h-24 w-auto object-contain">');
                            }

                            if (fileSignature.length) {
                                fileSignature.val('');
                            }

                            signaturePad.find('canvas').each(function() {
                                try {
                                    $(this).parent().jSignature('reset');
                                } catch (err) {}
                            });
                        }

                        originalTrackedLabValues = getTrackedLabValues();

                        if (response.is_add) {
                            Swal.fire({
                                title: "Add Lab",
                                text: response.message,
                                icon: "success"
                            });
                            setTimeout(() => {
                                window.location = "?page=<?php echo $slug_main; ?>&act=list";
                            }, 1000);
                        } else {
                            Swal.fire({
                                title: "Update Lab",
                                text: response.message,
                                icon: "success"
                            });
                        }
                    } else {
                        Swal.fire({
                            title: "Error",
                            text: response.message,
                            icon: "error"
                        });
                    }

                    if (response.data_update && response.data_update.logo) {
                        $("#area_logo_lab").empty();
                        var empLogo = `
                                    <img id="img_logo_lab" src="${response.data_update.logo}" alt="Laboratory Logo" class="h-32 w-auto object-contain">
                                    <button id="btn_del_logo_lab" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute -top-2 -right-2 h-6 w-6 rounded-full bg-red-500 hover:bg-red-600 text-white p-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-4 w-4" aria-hidden="true">
                                            <path d="M18 6 6 18"></path>
                                            <path d="m6 6 12 12"></path>
                                        </svg>
                                    </button>
                    `;
                        $("#area_logo_lab").append(empLogo);
                        updateUiUpload();
                    }

                    if (response.data_update && response.data_update.results_logo) {
                        $("#area_results_logo_lab").empty();
                        var empResultsLogo = `
                                    <img id="img_results_logo_lab" src="${response.data_update.results_logo}" alt="Results Logo" class="h-32 w-auto object-contain">
                                    <button id="btn_del_results_logo_lab" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute -top-2 -right-2 h-6 w-6 rounded-full bg-red-500 hover:bg-red-600 text-white p-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-4 w-4" aria-hidden="true">
                                            <path d="M18 6 6 18"></path>
                                            <path d="m6 6 12 12"></path>
                                        </svg>
                                    </button>
                    `;
                        $("#area_results_logo_lab").append(empResultsLogo);
                        updateUiUpload();
                    }
                },
                error() {
                    Swal.fire({
                        title: "Error",
                        text: "Server error!",
                        icon: "error"
                    });
                }
            });
        });

        updateUiUpload();

	        $("#btn_operation_logs").click(function() {
            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                trajax: 'list_operation_logs',
                lab_ID: '<?php echo $lab['lab_ID']; ?>'
            }, function(response) {
                Swal.fire({
                    title: 'Lab operation',
                    html: response,
                    width: '95vw',
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    didOpen: () => {
                        $('.update-lab-operation').off('click').on('click', function() {
                            const $row = $(this).closest('tr');
                            const labId = $row.data('id');

                            const labData = {
                                ID: labId,
                                lab_mon: $row.find('input[name="lab_mon"]').is(':checked') ? 1 : 0,
                                lab_tue: $row.find('input[name="lab_tue"]').is(':checked') ? 1 : 0,
                                lab_wed: $row.find('input[name="lab_wed"]').is(':checked') ? 1 : 0,
                                lab_thu: $row.find('input[name="lab_thu"]').is(':checked') ? 1 : 0,
                                lab_fri: $row.find('input[name="lab_fri"]').is(':checked') ? 1 : 0,
                                lab_sat: $row.find('input[name="lab_sat"]').is(':checked') ? 1 : 0,
                                lab_sun: $row.find('input[name="lab_sun"]').is(':checked') ? 1 : 0,
                                target_tat: $row.find('select[name="target_tat"]').val(),
                                from_date: $row.find('input[name="from_date"]').val(),
                                to_date: $row.find('input[name="to_date"]').val()
                            };

                            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                                trajax: 'update_operation_logs',
                                lab_operation: labData
                            }, function(response) {
                                if (response.success == true) {
                                    <?php
                                    foreach ($keys_operation as $key => $item) {
                                    ?>
                                        var emp_check_<?php echo $key; ?> = $('button[name="tblci_labs[<?php echo $key; ?>]"]');
                                        if (labData['<?php echo $key; ?>'] == 1)
                                            emp_check_<?php echo $key; ?>.attr("data-state", "checked");
                                        else
                                            emp_check_<?php echo $key; ?>.attr("data-state", "unchecked");
                                        updateCheckboxUI(emp_check_<?php echo $key; ?>);
                                    <?php } ?>
                                    if (labData.target_tat) {
                                        $('select[name="tblci_labs[target_tat]"]').val(labData.target_tat).trigger('change.select2');
                                    }
                                    Swal.fire({
                                        title: "Updated",
                                        text: response.data || "Update success",
                                        icon: "success"
                                    });
                                } else {
                                    Swal.fire({
                                        title: "Error",
                                        text: response.data || "Update failed",
                                        icon: "error"
                                    });
                                }
                            });
                        });

                        $('.delete-lab-operation').click(function() {
                            let emp_btn = $(this);
                            var id_l = $(this).data('id_log');
                            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                                trajax: 'delete_operation_logs',
                                id_log: id_l
                            }, function(response) {
                                if (response.success == true) {
                                    $(emp_btn).parent().parent().remove();
                                }
                            });
                        });
                    }
                });
            });
	            return false;
	        });

	        $("#btn_lab_history").click(function() {
	            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
	                trajax: 'list_lab_history',
	                lab_ID: '<?php echo $lab['lab_ID']; ?>'
	            }, function(response) {
	                Swal.fire({
	                    title: 'Lab history',
	                    html: response,
	                    width: '90vw',
	                    showCloseButton: true,
	                    confirmButtonText: 'Close',
	                    didOpen: () => {
	                        $('.lab-history-date').off('click').on('click', function() {
	                            $(this).toggleClass('is-collapsed');
	                            $(this).closest('.lab-history-group').toggleClass('is-collapsed');
	                        });
	                    }
	                });
	            });
	            return false;
	        });

	        function trpdd_setup_auto_complete() {
            $('.auto_insurance').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
            $('.auto_states').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=us_states_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
            $('.auto_exclusions').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_practice_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
            $('.auto_auto_rej_insurance').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
        }

        function trpdd_add_new_list($vl, $key) {
            let $val_placehoder = '';
            if ($key == 'insurance') $val_placehoder = 'placeholder="Insurance information"';
            if ($key == 'exclusions') $val_placehoder = 'placeholder="Exclusions"';
            if ($key == 'states') $val_placehoder = 'placeholder="State codes"';
            if ($key == 'auto_rej_insurance') $val_placehoder = 'placeholder="Insurance name"';
            $vl = $('<li><input type="text" ' + $val_placehoder + ' name="lab_' + $key + '[]" value="' + $vl + '" class="auto_' + $key + '"/></li>');
            $('#list_' + $key).append($vl);
            trpdd_setup_auto_complete();
        }

        let count_insurance = 0;
        for (i in $insurance) {
            if ($insurance[i] != "") {
                count_insurance++;
                trpdd_add_new_list($insurance[i], 'insurance');
            }
        }
        if (count_insurance == 0) trpdd_add_new_list("", 'insurance');

        let count_states = 0;
        for (i in $states) {
            if ($states[i] != "") {
                count_states++;
                trpdd_add_new_list($states[i], 'states');
            }
        }
        if (count_states == 0) trpdd_add_new_list("", 'states');

        let count_exclusions = 0;
        for (i in $exclusions) {
            if ($exclusions[i] != "") {
                count_exclusions++;
                trpdd_add_new_list($exclusions[i], 'exclusions');
            }
        }
        if (count_exclusions == 0) trpdd_add_new_list("", 'exclusions');

        let count_auto_rej_insurance = 0;
        for (i in $auto_rej_insurance) {
            if ($auto_rej_insurance[i] != "") {
                count_auto_rej_insurance++;
                trpdd_add_new_list($auto_rej_insurance[i], 'auto_rej_insurance');
            }
        }
        if ($('#list_auto_rej_insurance').length && count_auto_rej_insurance == 0) trpdd_add_new_list("", 'auto_rej_insurance');

        $('button.addnew[data-rel]').click(function() {
            trpdd_add_new_list('', $(this).data('rel'));
            return false;
        });

        $("#picker_color_lab").change(function() {
            var val_color = $(this).val();
            $("#main_color_lab_inp").val(val_color);
        });

        $("#main_color_lab_inp").on("input", function() {
            const val = $(this).val();
            const reg = /^#([0-9A-F]{3}){1,2}$/i;
            if (reg.test(val)) {
                $("#picker_color_lab").val(val);
            }
        });

        const $select = $('#lab_site_select');

        LAB_SITES.forEach(site => {
            $select.append(new Option(site.label, site.id, false, false));
        });

        $select.select2({
            placeholder: 'Select Site',
            allowClear: true,
            width: 'resolve'
        });

        $('#target-tat').select2({
            minimumResultsForSearch: Infinity,
            width: 'resolve'
        });

        $select.on('change', function() {
            $('#lab_site').val($(this).val() || '');
        });

        <?php if (!empty($lab['lab_site'])): ?>
            const currentId = <?php echo (int)$lab['lab_site']; ?>;
            $select.val(currentId).trigger('change.select2');
            $('#lab_site').val(currentId);
        <?php endif; ?>

        $("#list_clinics").select2({
            placeholder: 'Select clinics...',
            allowClear: true
        });

        $("#lab_pcr_panels").select2({
            placeholder: 'Select PCR panels...',
            allowClear: true
        });

        $(document).on('click', '.card_file .remove', function(e) {
            e.preventDefault();

            const $card = $(this).closest('.card_file');

            // 1. Xoá ảnh chữ ký
            $card.find('img').remove();

            // 2. Hiện lại signature pad
            $card.find('.signaturepad').show();

            // 3. Clear hidden field (CỰC KỲ QUAN TRỌNG)
            $card.find('input[type="hidden"][name="tblci_labs[lab_director_signature]"]').val('');

            // 4. Reset canvas (jSignature)
            try {
                $card.find('.signaturepad').find('canvas').each(function() {
                    $(this).parent().jSignature('reset');
                });
            } catch (err) {}

            return false;
        });

    });
</script>
