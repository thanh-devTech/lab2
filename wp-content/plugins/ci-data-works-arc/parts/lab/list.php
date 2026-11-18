<?php
global $wpdb, $is_ci_current_user;
$is_ci_current_user = !empty($is_ci_current_user) || (bool) get_user_meta(get_current_user_id(), '_is_ci_user', true);
$can_delete_main_lab = $is_ci_current_user && isset($_GET['debug']);
//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
    wp_schedule_single_event(time(), 'ci_check_upload_apis');
    trpdd_plugin_activate();
}


$labs = $wpdb->get_results("select 
        lab.*,
		(select count(*) from ci_clinic cli where cli.`cli_lab-ID` = lab.lab_ID) as count_accounts,
        (select count(*) from {$wpdb->prefix}usermeta um where um.meta_key='_lab_id' and um.`meta_value` = lab.lab_ID) as count_users
		from ci_labs lab 
        order by lab.lab_ID asc");
?>
<style>
    .select2-container {
        z-index: 100000000 !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: white;
        color: #6b7280;
        border-color: #0000001f;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #d83d00;
    }
</style>
<div>
    <div class="space-y-8 pb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Manage Laboratories</h3>
            <div class="flex gap-3">
                <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=edit&mode=add_primary_lab'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4 mr-2" aria-hidden="true">
                        <path d="M5 12h14"></path>
                        <path d="M12 5v14"></path>
                    </svg>Add Primary Lab
                </button>

                <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=edit&mode=add_ref_lab'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 border-[#3C3D59] text-[#3C3D59] hover:bg-[#3C3D59] hover:text-white"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4 mr-2" aria-hidden="true">
                        <path d="M5 12h14"></path>
                        <path d="M12 5v14"></path>
                    </svg>Add Reference Lab
                </button>

            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php
            foreach ($labs as $i => $lab) {
            ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-all duration-200">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-xl flex items-center justify-center">
                            <?php
                            if ($lab->lab_is_ref != '1') {
                            ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-8 w-8 text-gray-600" aria-hidden="true">
                                    <path d="M10 12h4"></path>
                                    <path d="M10 8h4"></path>
                                    <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                    <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                                    <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                                </svg>
                            <?php
                            } else {
                            ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-8 w-8 text-gray-600" aria-hidden="true">
                                    <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                    <path d="M6.453 15h11.094"></path>
                                    <path d="M8.5 2h7"></path>
                                </svg>
                            <?php } ?>

                        </div>
                        <h4 data-slot="card-title" class="text-xl"><?php echo $lab->lab_name ?></h4>
                        <p class="text-sm text-muted-foreground p-0" style="margin: 0px;">
                            <?php
                            $location = array_filter([
                                $lab->lab_address ?? '',
                                $lab->lab_city ?? '',
                                $lab->lab_state ?? ''
                            ]);

                            echo implode(', ', $location);
                            ?>
                        </p>
                        <div class="mt-1">
                            <?php if ($lab->lab_is_ref != '1') { ?>
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-primary text-white text-xs uppercase tracking-wide">Primary</span>
                            <?php } else { ?>
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-[#3C3D59] text-white text-xs uppercase tracking-wide">Reference</span>
                            <?php } ?>
                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hash h-4 w-4 text-muted-foreground" aria-hidden="true">
                                        <line x1="4" x2="20" y1="9" y2="9"></line>
                                        <line x1="4" x2="20" y1="15" y2="15"></line>
                                        <line x1="10" x2="8" y1="3" y2="21"></line>
                                        <line x1="16" x2="14" y1="3" y2="21"></line>
                                    </svg>
                                    <span class="font-medium text-sm">Lab ID</span>
                                </div>
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">#<?php echo $lab->lab_ID; ?></span>
                            </div>


                            <div class="flex items-center justify-between py-2 border-b">
                                <div class="flex items-center gap-2">
                                    <span data-slot="badge" class="border font-medium whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 h-4 w-4 rounded-full flex items-center justify-center p-0 text-[10px]">i</span>
                                    <span class="font-medium text-sm">Init</span>
                                </div>
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs"><?php echo $lab->lab_dba_init ?></span>
                            </div>



                            <div class="flex items-center justify-between py-2 border-b">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 text-muted-foreground" aria-hidden="true">
                                        <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"></path>
                                        <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                        <path d="M10 9H8"></path>
                                        <path d="M16 13H8"></path>
                                        <path d="M16 17H8"></path>
                                    </svg>
                                    <span class="font-medium text-sm">CLIA</span>
                                </div>
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">
                                    <?php echo $lab->lab_clia; ?>
                                </span>
                            </div>


                            <div class="flex items-center justify-between py-2 border-b">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hospital h-4 w-4 text-muted-foreground" aria-hidden="true">
                                        <path d="M12 7v4"></path>
                                        <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                        <path d="M14 9h-4"></path>
                                        <path d="M18 11h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"></path>
                                        <path d="M18 21V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                    <span class="font-medium text-sm">Accounts</span>
                                </div><span class="text-sm"><?php echo $lab->count_accounts; ?></span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users h-4 w-4 text-muted-foreground" aria-hidden="true">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg><span class="font-medium text-sm">Users</span>
                                </div>
                                <span class="text-sm"><?php echo $lab->count_users; ?></span>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button onclick="window.location='?page=<?php echo $slug_main; ?>&act=edit&lab_id=<?php echo $lab->lab_ID; ?><?php echo $can_delete_main_lab ? '&debug=1' : ''; ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 flex-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
                                    <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                                </svg>Edit Laboratory
                            </button>
                            <?php if (($is_ci_current_user && intval($lab->lab_is_ref) === 1) || ($can_delete_main_lab && intval($lab->lab_is_ref) !== 1)) { ?>
                                <button type="button" data-url="?page=<?php echo $slug_main; ?>&act=delete&lab_id=<?php echo $lab->lab_ID; ?><?php echo $can_delete_main_lab ? '&debug=1' : ''; ?>" data-slot="button" class="btn_delete_ref_lab cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border border-red-200 bg-white text-red-600 hover:bg-red-50 h-9 px-3 py-2 has-[&gt;svg]:px-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 h-4 w-4" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                </button>
                            <?php } ?>
                        </div>

                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
    jQuery(function($) {
        $('.btn_delete_ref_lab').click(function() {
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
    });
</script>
