<?php
global $wpdb, $is_ci_current_user;
$act        = isset($_REQUEST['act']) ? $_REQUEST['act'] : 'list';
$trajax     = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$slug_main  = $_GET['page'];
$is_ci_current_user = !empty($is_ci_current_user) || (bool) get_user_meta(get_current_user_id(), '_is_ci_user', true);
$can_delete_main_lab = $is_ci_current_user && isset($_REQUEST['debug']);

$keys_operation = [
    'lab_mon' => 'Monday',
    'lab_tue' => 'Tuesday',
    'lab_wed' => 'Wednesday',
    'lab_thu' => 'Thursday',
    'lab_fri' => 'Friday',
    'lab_sat' => 'Saturday',
    'lab_sun' => 'Sunday',
];

if ($trajax != '') {
    include "edit_lab_ajax.php";
    exit;
}

if ($act == 'delete') {
    $lab_id = isset($_REQUEST['lab_id']) ? intval($_REQUEST['lab_id']) : 0;
    $lab = $wpdb->get_row($wpdb->prepare("select lab_ID, lab_is_ref from ci_labs where lab_ID = %d", $lab_id));

    if (!$lab) {
        Tr_Session_Class::add_error('Lab not found.');
    } else if (intval($lab->lab_is_ref) === 1 && !$is_ci_current_user) {
        Tr_Session_Class::add_error('You cannot delete this ref lab.');
    } else if (intval($lab->lab_is_ref) !== 1 && !$can_delete_main_lab) {
        Tr_Session_Class::add_error('You cannot delete this lab.');
    } else {
        $wpdb->delete('ci_labs', array('lab_ID' => $lab_id));
        Tr_Session_Class::add('Deleted Lab #' . $lab_id);
    }

    $act = 'list';
}

function icon_sort()
{
    $icon_down_up = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up-down inline h-3 w-3 ml-1 opacity-40" aria-hidden="true"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>';
    $icon_up = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
    $icon_down = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-down h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
    return $icon_down_up . $icon_up . $icon_down;
}
?>
<style>
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
        margin-bottom: 6px !important;
    }

    h1,
    h4 {
        margin: unset;
        font-size: var(--text-xl);
        line-height: var(--tw-leading, var(--text-xl--line-height));
    }

    h1 {
        margin-bottom: 2px;
    }

    h4 {
        margin-bottom: 5px;
    }

    h3 {
        --tw-font-weight: var(--font-weight-semibold) !important;
        font-weight: var(--font-weight-semibold) !important;
        font-size: var(--text-lg);
        line-height: var(--tw-leading, var(--text-lg--line-height));
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10px;
    }

    .text-primary {
        color: var(--primary);
    }

    ol {
        list-style-type: none;
        margin: 0px;
        margin-top: px;
        padding-top: 5px;
        padding-left: 5px;
    }

    ol li a {
        color: unset;
    }

    .hasdate.hasDatepicker {
        border: solid 1.3px #e0dede;
        border-radius: 5px !important;
        margin: 3px;
    }

    .swal2-container {
        z-index: 1000000 !important;
    }

    .text-2xl {
        font-size: var(--text-2xl);
        line-height: var(--tw-leading, var(--text-2xl--line-height));
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-6">
        <div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
            <nav aria-label="breadcrumb" data-slot="breadcrumb">
                <ol data-slot="breadcrumb-list" class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link" class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=dashboard_main">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
                                <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                <path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
                        </a>
                    </li>
                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true" class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>
                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link" class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=<?php echo $slug_main; ?>&act=list">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings h-3 w-3" aria-hidden="true">
                                <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">Settings</span>
                        </a>
                    </li>
                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true" class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg></li>
                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link" class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm" href="?page=<?php echo $slug_main; ?>&act=list">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings h-3 w-3" aria-hidden="true">
                                <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Laboratory Settings</span>
                        </a>
                    </li>

                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true" class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>

                    <?php if ($act == 'list') { ?>
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <span data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page" class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-3 w-3" aria-hidden="true">
                                    <path d="M10 12h4"></path>
                                    <path d="M10 8h4"></path>
                                    <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                    <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                                    <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                </svg>
                                <span class="truncate max-w-[120px] sm:max-w-none">Labs</span>
                            </span>
                        </li>
                    <?php } ?>

                    <?php if ($act == 'state_lab') { ?>
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page" class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-database h-3 w-3" aria-hidden="true">
                                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                    <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                    <path d="M3 12A9 3 0 0 0 21 12"></path>
                                </svg><span class="truncate max-w-[120px] sm:max-w-none">Report to State</span></span></li>
                    <?php } ?>

                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Laboratory Settings</h1>
                <p class="text-muted-foreground mt-1 text-xs sm:text-[13px]">Manage laboratory profiles, reporting, and reference labs</p>
            </div>
        </div>
        <div class="space-y-3">
            <div class="bg-gradient-to-r to-gray-100 rounded-2xl p-3 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=list'" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php if ($act == 'list') { ?>bg-gradient-to-r from-primary to-primary/90 text-white shadow-md <?php } else { ?>text-gray-600 hover:bg-white hover:shadow-sm<?php } ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-3.5 w-3.5" aria-hidden="true">
                            <path d="M10 12h4"></path>
                            <path d="M10 8h4"></path>
                            <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                            <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                            <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                        </svg><span>Labs</span>
                    </button>
                    <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=state_lab'" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php if ($act == 'state_lab') { ?>bg-gradient-to-r from-primary to-primary/90 text-white shadow-md <?php } else { ?>text-gray-600 hover:bg-white hover:shadow-sm<?php } ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-database h-3.5 w-3.5" aria-hidden="true">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                        </svg><span>Report to State</span>
                    </button>
                    <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=clinic_support'" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php if ($act == 'clinic_support') { ?>bg-gradient-to-r from-primary to-primary/90 text-white shadow-md <?php } else { ?>text-gray-600 hover:bg-white hover:shadow-sm<?php } ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone h-3.5 w-3.5">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg><span>Clinic Support</span>
                    </button>
                </div>
            </div>
        </div>
        <?php include trpdd_get_file_path('lab', $act); ?>
    </div>
</div>

<script>
    var $ = jQuery;
    jQuery(document).ready(function($) {
        $(".btn_list_clinic").click(function() {
            var lab_id = $(this).data("id-lab");
            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                trajax: 'list_ref_lab_clinic',
                lab_ID: lab_id
            }, function(response) {
                Swal.fire({
                    html: response,
                    showCloseButton: true,
                    showDenyButton: true,
                    confirmButtonText: '✔️ Save',
                    denyButtonText: '✖ Close',
                    didOpen: () => {
                        $(".js_select2").select2();
                    },
                }).then((result) => {

                    if (result.isConfirmed) {
                        jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                            trajax: 'update_list_ref_lab_clinic',
                            lab_ID: lab_id,
                            clinics: $("#list_clinics").val()
                        }, function(response) {
                            Swal.fire({
                                title: "Clinics",
                                text: "Save success!",
                                icon: "success"
                            });
                        });
                    }

                    if (result.isDenied) Swal.close();
                });
            });
        });

        $(".btn_list_fees").click(function() {
            var lab_id = $(this).data("id-lab");
            jQuery.post("admin.php?page=<?php echo $slug_main; ?>", {
                trajax: 'list_ref_lab_fees',
                lab_ID: lab_id
            }, function(response) {
                Swal.fire({
                    html: response,
                    showCloseButton: true,
                    showDenyButton: true,
                    confirmButtonText: '✔️ Save',
                    denyButtonText: '✖ Close',
                    didOpen: () => {

                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        var data = $('#frm_fees').serialize();

                        jQuery.post(
                            "admin.php?page=<?php echo $slug_main; ?>",
                            data + '&trajax=update_list_ref_lab_fees&lab_ID=' + lab_id,
                            function(response) {
                                Swal.fire({
                                    title: "Fees",
                                    text: "Save success!",
                                    icon: "success"
                                });
                            }
                        );
                    }

                    if (result.isDenied) Swal.close();
                });
            });
            return false;
        });
    });
</script>
