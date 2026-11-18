<?php
global $wpdb;

$gid        = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;
$btype      = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : $_REQUEST['type'];
$labid      = isset($_REQUEST['labid']) ? $_REQUEST['labid'] : 0;
$subact     = isset($_REQUEST['subact']) ? $_REQUEST['subact'] : '';
$btypename  = tr_pdd_get_typename($btype);
$user       = wp_get_current_user();
$can_delete = isset($_GET['debug'])  || $user->user_login == 'Administrat0r';

if (empty($labid)) {
    $labid = CI_Base_Lab::get_current_lab_id();
}
if ($labid > 0) {
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$labid}", ARRAY_A);
}


if ($subact == 'deletef') {

    $ID         = $_REQUEST['id'];
    $group_ID   = $_REQUEST['group_ID'];
    $labid      = $_REQUEST['labid'];

    if ($can_delete) {
        $where_delete = array('group_ID' => $group_ID, 'lab_ID' => $labid);
        if ($ID > 0) {
            $where_delete = array('ID' => $ID);
        }
        $wpdb->delete('ci_pdf_footer', $where_delete);
        Tr_Session_Class::add('Deleted Footer group #' . $group_ID);
    } else {
        Tr_Session_Class::add_error('You cant delete group #' . $group_ID);
    }

    $url = Tr_Base_Class_V6::link(array('subact' => '', 'id' => '', 'group_ID' => '', 'gid' => ''), false);
    echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
    exit;
} else if ($subact == 'deletef_by_id') {
    $ID         = $_REQUEST['id'];
    $gID        = $_REQUEST['gid'];
    $labid      = $_REQUEST['labid'];

    if ($can_delete) {
        $where_delete = array('ID' => $ID);
        $wpdb->delete('ci_pdf_footer', $where_delete);
        Tr_Session_Class::add('Deleted Footer #' . $ID);
    } else {
        Tr_Session_Class::add_error('You cant delete footer');
    }

    $url = Tr_Base_Class_V6::link(array('subact' => '', 'id' => '', 'gid' => $gID), false);
    echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
    exit;
}



$where  = " ft.btype='{$btype}' ";
if ($labid > 0) {
    $where .= " and ft.lab_ID = {$labid} ";
}

$wpdb->update('ci_pdf_footer', array('group_ID' => -1), array('group_ID' => 0));

$sql_footers = "select ft.*,
								g.tg_name as group_name,
								lab.lab_name,
								count(*) as count
                                from ci_pdf_footer ft
                                left join ci_test_groups g ON g.tg_ID = ft.group_ID
                                left join ci_labs lab ON lab.lab_ID = ft.lab_ID
                                where {$where}
                                group by group_ID, lab_ID
                                order by start_date desc";

$footers = $wpdb->get_results($sql_footers);


?>
<style>
    a {
        color: black
    }
</style>
<div class="wrap">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold"> <?php echo $btypename . ' Result Footer ' . ($labid > 0 ? 'for lab ' . $lab['lab_dba_init'] : '') ?></h3>
        <div class="flex gap-3">
            <button onclick="window.location='<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $labid, 'lab_id' => $labid)) ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
                    <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                </svg>
                Edit Lab
            </button>

            <button onclick="window.location='<?php Tr_Base_Class_V6::link(array('act' => 'footer_edit', 'subact' => 'addf', 'id' => '')) ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 border-[#3C3D59] text-[#3C3D59] hover:bg-[#3C3D59] hover:text-white"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4 mr-2" aria-hidden="true">
                    <path d="M5 12h14"></path>
                    <path d="M12 5v14"></path>
                </svg>Add New
            </button>

        </div>
    </div>

    <?php Tr_Session_Class::show() ?>

    <div class="bg-white rounded-lg border shadow-sm overflow-x-auto">
        <div data-slot="table-container" class="relative w-full overflow-x-auto">
            <table data-slot="table" class="w-full caption-bottom text-sm">
                <thead data-slot="table-header" class="[&amp;_tr]:border-b">
                    <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-gray-100">
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[100px]">#</th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[250px]">
                            <button data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 font-medium hover:bg-transparent">Lab
                                <?php echo icon_sort(); ?>
                            </button>
                        </th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[120px]">Group</th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[180px] bg-red-50/50">Last Edit</th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[120px] bg-red-50/50">Versions</th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[100px] bg-red-50/50">Actions</th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[50px] text-center"></th>
                        <th data-slot="table-head" class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] w-[50px] text-center"></th>
                    </tr>
                </thead>
                <tbody data-slot="table-body" class="[&_tr]:border-b">
                    <?php foreach ($footers as $i => $footer) {
                        if (empty($footer->group_name)) {
                            $footer->group_name = 'Global (default)';
                        }
                    ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo $footer->ID ?></td>
                            <td class="px-4 py-3 font-medium text-gray-900"><?php echo $footer->lab_name ?></td>
                            <td class="px-4 py-3"><?php echo $footer->group_name ?></td>
                            <td class="px-4 py-3"><?php echo $footer->start_date ?></td>
                            <td class="px-4 py-3"><?php echo $footer->count ?></td>

                            <td class="px-4 py-3 flex items-center">
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'footer_edit', 'subact' => 'editf', 'gid' => $footer->group_ID, 'labid'  => $footer->lab_ID, 'id' => $footer->ID)) ?>"
                                    class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
                                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                                    </svg>
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a href="<?php Tr_Base_Class_V6::link(array(
                                                    'act' => 'footers',
                                                    'subact' => 'deletef',
                                                    'id'  => $footer->ID,
                                                    'group_ID'  => $footer->group_ID,
                                                    'labid'  => $footer->lab_ID,
                                                )) ?>"
                                        class="delete cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true">
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </a>
                                <?php } ?>

                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        jQuery(function($) {
            $('a.delete').click(function() {
                if (confirm('Do you want delete?') == false) return false;
            })

            $('table th').click(function() {
                var table = $(this).parents('table').eq(0);
                var rows = table.find('tbody tr').toArray();
                var index = $(this).index();
                var asc = !$(this).data('asc');

                $('table th').find(".lucide-arrow-up-down").addClass("inline").show();
                $('table th').find(".lucide-arrow-up").removeClass("inline").hide();
                $('table th').find(".lucide-arrow-down").removeClass("inline").hide();
                $(this).find(".lucide-arrow-up-down").removeClass("inline").hide();


                rows.sort(function(a, b) {
                    var cellA = $(a).children('td').eq(index);
                    var cellB = $(b).children('td').eq(index);

                    var A = cellA.text().trim();
                    var B = cellB.text().trim();


                    return asc ?
                        B.localeCompare(A, undefined, {
                            sensitivity: 'base'
                        }) :
                        A.localeCompare(B, undefined, {
                            sensitivity: 'base'
                        });

                });

                $.each(rows, function(i, row) {
                    table.children('tbody').append(row);
                });

                if (asc) {
                    $(this).find(".lucide-arrow-down").addClass('inline').show();
                } else {
                    $(this).find(".lucide-arrow-up").addClass('inline').show();
                }

                $(this).data('asc', asc);
            });
        })
    </script>
</div>