<?php
global $wp_roles, $is_ci_admin;

$trajax     = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$keyword_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$role_search = isset($_GET['r']) ? sanitize_text_field($_GET['r']) : 'all';
$per_page = 20;

if ($trajax == 'delete_users') {
    $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];

    if (empty($ids)) {
        wp_send_json_error('No users selected');
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';

    $deleted = 0;

    foreach ($ids as $user_id) {
        $user_id = intval($user_id);

        if ($user_id <= 0) continue;
        if ($user_id === get_current_user_id()) continue;
        if (!current_user_can('delete_user', $user_id)) continue;

        if (wp_delete_user($user_id)) {
            $deleted++;
        }
    }

    wp_send_json_success("Deleted {$deleted} user(s) successfully");
    exit;
}

if ($trajax == 'delete_one_user') {
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';

    require_once ABSPATH . 'wp-admin/includes/user.php';

    $user_id = intval($user_id);

    if ($user_id === get_current_user_id()) wp_send_json_error("You cannot delete yourself!");
    if (!current_user_can('delete_user', $user_id)) wp_send_json_error("You do not have permission to delete!");

    if (wp_delete_user($user_id)) {
        wp_send_json_success("Deleted successfully!");
    } else {
        wp_send_json_error("Failed to delete user!");
    }
    exit;
}

if ($trajax == 'send_password_reset') {

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error("Invalid user ID!");
    }

    if (!current_user_can('edit_user', $user_id)) {
        wp_send_json_error("You do not have permission to reset this user's password!");
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error("User not found!");
    }

    $result = retrieve_password($user->user_login);

    if ($result === true) {
        wp_send_json_success("Password reset email has been sent!");
    } else {
        wp_send_json_error("Failed to send password reset email!");
    }
    exit;
}

if ($trajax === 'bulk_send_password_reset') {

    if (!current_user_can('edit_users')) {
        wp_send_json_error('Permission denied');
    }

    $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];

    if (empty($ids)) {
        wp_send_json_error('No users selected');
    }

    $sent   = 0;
    $failed = [];

    foreach ($ids as $user_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0) continue;

        if ($user_id === get_current_user_id()) continue;

        if (!current_user_can('edit_user', $user_id)) {
            $failed[] = $user_id;
            continue;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            $failed[] = $user_id;
            continue;
        }

        $result = retrieve_password($user->user_login);

        if ($result === true) {
            $sent++;
        } else {
            $failed[] = $user_id;
        }
    }

    wp_send_json_success("Password reset email sent for {$sent} user(s)");
    exit;
}


if ($trajax === 'bulk_resend_welcome') {

    if (!current_user_can('create_users')) {
        wp_send_json_error('Permission denied');
    }

    $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];

    if (empty($ids)) {
        wp_send_json_error('No users selected');
    }

    $sent   = 0;
    $failed = [];

    foreach ($ids as $user_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0) continue;

        $user = get_user_by('id', $user_id);
        if (!$user) {
            $failed[] = $user_id;
            continue;
        }

        if (function_exists('wp_new_user_notification')) {
            wp_new_user_notification($user_id, null, 'user');
            $sent++;
        } else {
            $failed[] = $user_id;
        }
    }

    wp_send_json_success("Welcome email resent for {$sent} user(s)");
    exit;
}



if ($trajax === 'bulk_change_role') {

    /*
    if (!current_user_can('promote_users')) {
        wp_send_json_error('Permission denied');
    }*/

    $ids  = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
    $role = sanitize_text_field($_POST['role'] ?? '');

    if (empty($ids) || empty($role)) {
        wp_send_json_error('Invalid data');
    }

    global $wp_roles;
    $is_no_role = ($role === 'no_role');

    if (!$is_no_role) {
        if (!$wp_roles->is_role($role)) {
            wp_send_json_error('Invalid role');
        }
        if ($role === 'data_export' && !is_super_admin()) {
            wp_send_json_error('Only Super Admin can assign the Data Export role.');
        }
        if (trpdd_is_role_disabled($role)) {
            wp_send_json_error('This role is disabled for this site');
        }
    }

    $changed = 0;

    foreach ($ids as $user_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0) continue;
        if ($user_id === get_current_user_id()) continue;
        $user = get_user_by('id', $user_id);
        if (!$user) continue;

        $old_role = $user->roles[0] ?? '';
        if ($is_no_role) {
            $user->set_role('');
        } else {
            $user->set_role($role);
        }
        $changed++;
    }

    wp_send_json_success("Changed role for {$changed} user(s)");
    exit;
}

$user_count = count_users();
$total_users = $user_count['total_users'];
$avail_roles = $user_count['avail_roles'];

$inactive_users = $wpdb->get_var("select count(*) from {$wpdb->signups} where active = 0") ?? 0;
$all_roles = $wp_roles->roles;
$roles = get_editable_roles();

$args = [
    'number' => $per_page,
    'paged'  => $paged,
    'orderby' => 'ID',
    'order'  => 'DESC'
];

if ($keyword_search !== '') {
    $args['search'] = '*' . $keyword_search . '*';
    $args['search_columns'] = [
        'user_login',
        'user_email',
        'display_name',
    ];
}

if ($role_search === 'no_role') {
    $args['role__not_in'] = array_keys((array) $wp_roles->roles);
} elseif ($role_search != 'all') {
    $args['role'] = $role_search;
}

$total_users_count = $total_users + $inactive_users;

$user_query = new WP_User_Query($args);
$users = $user_query->get_results();
$total_pages = ceil($total_users / $per_page);

$prev_page = max(1, $paged - 1);
$next_page = min($total_pages, $paged + 1);

$prev_url = ($paged > 1) ? add_query_arg('paged', $prev_page) : '';
$next_url = ($paged < $total_pages) ? add_query_arg('paged', $next_page) : '';

function trpdd_get_user_office_html($user_id, $data_csv = false)
{
    global $wpdb;

    $user = get_user_by('id', $user_id);
    if (!$user) return '';

    $user_role = $user->roles[0] ?? '';
    $rs = [];

    switch ($user_role) {

        case 'doctor':
            $doc_id = get_user_meta($user_id, '_doc_id', true);
            if ($doc_id > 0) {
                $rs = $wpdb->get_results("
                    SELECT cli.cID, cli.cli_clinic 
                    FROM ci_clinic cli
                    JOIN ci_clinic_doc cd ON cd.cli_ID = cli.cID
                    JOIN ci_doc doc ON doc.doc_ID = cd.doc_ID
                    WHERE doc.doc_ID = {$doc_id}
                ");
            }
            break;

        case 'lab_rep':
        case 'lab_rep_w2':
            $rep_id = get_user_meta($user_id, '_rep_id', true);
            if ($rep_id > 0) {
                $rs = $wpdb->get_results("
                    SELECT cli.cID, cli.cli_clinic 
                    FROM ci_clinic cli
                    WHERE cli.`cli_rep-ID` = {$rep_id}
                ");
            }
            break;

        case 'clinic':
        case 'office_manager':
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $rs = $wpdb->get_results("
                SELECT cli.cID, cli.cli_clinic 
                FROM ci_clinic cli
                LEFT JOIN ci_clinic_manager clim ON clim.ma_cli_ID = cli.cID
                WHERE clim.ma_user_ID={$user_id} OR cli.cID='{$cid}'
                GROUP BY cli.cID
            ");
            break;

        case 'multi_site_manager':
            $ids = get_user_meta($user_id, '_ms_clinic_ids', true);
            if (!empty($ids) && is_array($ids)) {
                $where = 'cli.cID IN (' . implode(',', array_map('intval', $ids)) . ')';
            } else {
                $where = '1=0';
            }
            $rs = $wpdb->get_results("
                SELECT cli.cID, cli.cli_clinic 
                FROM ci_clinic cli
                WHERE {$where}
                GROUP BY cli.cID
            ");
            break;

        case 'collector':
            $col_id = get_user_meta($user_id, '_collector_id', true);
            if ($col_id > 0) {
                $rs = $wpdb->get_results("
                    SELECT cli.cID, cli.cli_clinic 
                    FROM ci_clinic cli
                    JOIN ci_clinic_collector cc ON cc.cli_ID = cli.cID
                    WHERE cc.col_ID = {$col_id}
                ");
            }
            break;

        case 'distributor':
            $rs = $wpdb->get_results("
                SELECT cli.cID, cli.cli_clinic 
                FROM ci_clinic cli
                LEFT JOIN ci_distributors d ON d.dis_ID = cli.cli_distributor_ID
                WHERE d.dis_user_ID={$user_id}
                GROUP BY cli.cID
            ");
            break;
    }

    if (empty($rs)) return '';

    if ($data_csv) {
        return implode(',', array_map(fn($r) => $r->cli_clinic, $rs));
    }

    $id    = $user_id;
    $total = count($rs);
    $limit = 2;
    $out   = '';

    $out .= '<ul style="margin:0;padding:0;list-style:none">';
    foreach (array_slice($rs, 0, $limit) as $r)
        $out .= '<li>' . esc_html($r->cli_clinic) . '</li>';
    $out .= '</ul>';

    if ($total > $limit) {
        $all_html = implode('', array_map(
            function ($r) {
                return '<li style="padding:3px 0;border-bottom:1px solid #eee">' . esc_html($r->cli_clinic) . '</li>';
            },
            $rs
        ));
        $pid  = 'cp-' . $id;
        $out .= '<span class="cp-more" data-pid="' . $pid . '" style="cursor:pointer;color:#2271b1;font-size:11px">+' . ($total - $limit) . ' more&hellip;</span>';
        $out .= '<div id="' . $pid . '" style="display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #ccc;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.2);padding:10px 14px;min-width:180px;max-width:300px;max-height:260px;overflow-y:auto;font-size:13px">
                    <b style="display:block;margin-bottom:5px">All Clinics (' . $total . ')</b>
                    <ul style="margin:0;padding:0;list-style:none">' . $all_html . '</ul>
                </div>';
    }

    static $js_done = false;
    if (!$js_done) {
        $js_done = true;
        $out .= '<script>
        (function(){
            var active=null;
            function close(){if(active)active.style.display="none";active=null;}
            document.addEventListener("click",function(e){
                var t=e.target.closest(".cp-more");
                if(t){
                    var p=document.getElementById(t.dataset.pid);
                    if(!p)return;
                    if(active===p){close();return;}
                    close();
                    var r=t.getBoundingClientRect();
                    p.style.left=Math.min(r.left,window.innerWidth-310)+"px";
                    p.style.top=(r.bottom+4)+"px";
                    p.style.display="block";
                    active=p;
                } else if(!e.target.closest("[id^=\'cp-\']")){close();}
            });
        })();
        </script>';
    }

    return $out;
}

if ($trajax == 'export') {
    $outfile = "Users.csv";
    $f = fopen('php://output', 'w');
    tr_pdd_header('csv', $outfile);
    fputcsv($f, ["Username", "Name", "Email", "Role", "Office"]);
    foreach ($users as $u) {
        $role = $u->roles[0] ?? '';
        $data_role = isset($all_roles[$role]) ? $all_roles[$role] : array('name' => $role);
        $office = trpdd_get_user_office_html($u->ID, true);
        fputcsv($f, [$u->user_login, $u->display_name, $u->user_email, $data_role['name'], $office]);
    }
    fclose($f);
    exit;
}
?>
<style>
    .btn_yes {
        background-color: rgb(216, 61, 0) !important;
    }

    .btn_cancel {
        background-color: rgb(80 79 103) !important;
    }

    .btn_success {
        background-color: rgb(56 161 91) !important;
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Users</h1>
                </div>
                <div class="flex gap-2">
                    <button id="btn_export_all" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2" aria-hidden="true">
                            <path d="M12 15V3"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="m7 10 5 5 5-5"></path>
                        </svg>Export
                    </button>
                    <button onclick="window.location='?page=ci_user_add'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 has-[&gt;svg]:px-3" style="background-color: rgb(216, 61, 0);">Add New User</button>
                </div>
            </div>

            <div class="grid grid-cols-2 <?php echo $is_ci_admin ? 'md:grid-cols-4' : 'md:grid-cols-3'; ?> gap-4">
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold" style="color: rgb(216, 61, 0);"><?php echo $total_users_count; ?></div>
                            <div class="text-sm text-gray-500 mt-1">Total Users</div>
                        </div>
                    </div>
                </div>
                <?php if ($is_ci_admin): ?>
                    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-800"><?php echo $avail_roles['administrator']; ?></div>
                                <div class="text-sm text-gray-500 mt-1">Administrators</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-800"><?php echo $total_users; ?></div>
                            <div class="text-sm text-gray-500 mt-1">Active Users</div>
                        </div>
                    </div>
                </div>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-800"><?php echo $inactive_users; ?></div>
                            <div class="text-sm text-gray-500 mt-1">Pending</div>
                        </div>
                    </div>
                </div>
            </div>


            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex flex-wrap gap-2">
                        <span data-id="all" data-slot="badge" class="btn_role <?php if ($role_search == 'all') echo 'bg-red-50 text-orange-600';
                                                                                else echo 'border'; ?>  inline-flex items-center justify-center rounded-md px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden text-foreground [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground cursor-pointer transition-all text-sm hover:bg-accent hover:text-accent-foreground">
                            All <span class="text-gray-500">(<?php echo $total_users; ?>)</span>
                        </span>

                        <?php
                        foreach ($user_count['avail_roles'] as $role => $count) {
                            $data_role = isset($roles[$role]) ? $roles[$role] : null;
                            if (empty($data_role) || trpdd_is_role_disabled($role)) continue;
                        ?>
                            <span data-id='<?php echo $role; ?>' data-slot="badge" class="btn_role <?php if ($role_search == $role) echo 'bg-red-50 text-orange-600';
                                                                                                    else echo 'border'; ?> inline-flex items-center justify-center rounded-md px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden text-foreground [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground cursor-pointer transition-all text-sm hover:bg-accent hover:text-accent-foreground">
                                <?php echo $data_role['name']; ?>
                                <span class="text-gray-500">(<?php echo $count; ?>)</span>
                            </span>
                        <?php }
                        $no_role_count = $wpdb->get_var("
                            SELECT COUNT(*)
                            FROM {$wpdb->users} u
                            INNER JOIN {$wpdb->usermeta} um
                                ON u.ID = um.user_id
                            WHERE um.meta_key = 'wp_capabilities'
                            AND um.meta_value = 'a:0:{}'
                        ");
                        ?>
                        <span data-id="no_role" data-slot="badge" class="btn_role <?php echo ($role_search == 'no_role') ? 'bg-red-50 text-orange-600' : 'border'; ?> inline-flex items-center justify-center rounded-md px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive overflow-hidden text-foreground [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground  cursor-pointer transition-all text-sm hover:bg-accent hover:text-accent-foreground">
                            No Role <span class="text-gray-500">(<?php echo $no_role_count; ?>)</span>
                        </span>
                    </div>
                </div>
            </div>


            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <select id="sel_bulk_action">
                                <option value="bulk_action">Bulk actions</option>
                                <option value="delete">Remove</option>
                                <option value="password_reset">Send password reset</option>
                                <option value="resend_welcome">Resend Welcome</option>
                            </select>

                            <button id="btn_action_apply" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                                Apply
                            </button>

                            <select id="sel_change_role">
                                <option value="change_role_to">Change role to...</option>
                                <?php foreach ($roles as $key => $r) {
                                ?>
                                    <option value="<?php echo $key; ?>" <?php if (isset($r['disabled']) && !empty($r['disabled'])) echo 'disabled'; ?>><?php echo $r['name']; ?></option>
                                <?php
                                }
                                ?>
                                <option value="no_role">— No role for this site —</option>
                            </select>

                            <button id="btn_change_roles" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">Change</button>
                        </div>
                        <div class="flex items-center gap-2 w-full lg:w-auto">
                            <div class="relative flex-1 lg:flex-initial">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" aria-hidden="true">
                                    <path d="m21 21-4.34-4.34"></path>
                                    <circle cx="11" cy="11" r="8"></circle>
                                </svg>
                                <input
                                    id="inp_search"
                                    data-slot="input"
                                    class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9 w-full lg:w-64"
                                    placeholder="Search Users"
                                    value="<?php echo $keyword_search ?? ''; ?>">
                            </div>
                            <button id="btn_search_user" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 has-[&gt;svg]:px-3" style="background-color: rgb(216, 61, 0);">Search</button>
                        </div>
                    </div>
                </div>
            </div>


            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <button id="btn_sel_all" type="button" role="checkbox" aria-checked="false" data-state="unchecked" value="on" data-slot="checkbox" class="cursor-pointer peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                                            <span data-state="unchecked" data-slot="checkbox-indicator" class="hidden items-center justify-center text-current transition-none" style="pointer-events: none;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                    <path d="M20 6 9 17l-5-5"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Username</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Name</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Role</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Office</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                foreach ($users as $u) {
                                    $role = $u->roles[0] ?? '';
                                    $data_role = isset($roles[$role]) ? $roles[$role] : array('name' => $role);
                                    $is_role_disabled = trpdd_is_role_disabled($role);
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <button data-user-id="<?php echo $u->ID; ?>" type="button" role="checkbox" aria-checked="false" data-state="unchecked" value="on" data-slot="checkbox" class="cursor-pointer peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                                                <span data-state="unchecked" data-slot="checkbox-indicator" class="hidden items-center justify-center text-current transition-none" style="pointer-events: none;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                                        <path d="M20 6 9 17l-5-5"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 td_menu_user">
                                            <div>
                                                <button onclick="window.location='?page=ci_user_edit&user_id=<?php echo $u->ID; ?>'" class="cursor-pointer text-sm font-medium hover:underline block" style="color: rgb(216, 61, 0);">
                                                    <?php echo $u->user_login; ?>
                                                </button>
                                            </div>
                                            <div class="sub_menu mt-0.5" style="display:none;font-size: 11px; line-height: 1.2;">
                                                <button onclick="window.location='?page=ci_user_edit&user_id=<?php echo $u->ID; ?>'" class="cursor-pointer hover:underline" style="color: rgb(216, 61, 0); font-size: 11px;">Edit</button>
                                                <span class="mx-1" style="color: rgb(156, 163, 175); font-size: 11px;">|</span>
                                                <button data-id="<?php echo $u->ID; ?>'" class="btn_del_one cursor-pointer hover:underline" style="color: rgb(220, 38, 38); font-size: 11px;">Remove</button>
                                                <span class="mx-1" style="color: rgb(156, 163, 175); font-size: 11px;">|</span>
                                                <!-- <button onclick="window.location='?page=ci_user_edit&user_id=<-?php echo $u->ID; ?>&mode=view'" class="cursor-pointer hover:underline" style="color: rgb(216, 61, 0); font-size: 11px;">View</button>
                                                <span class="mx-1" style="color: rgb(156, 163, 175); font-size: 11px;">|</span> -->
                                                <button data-id="<?php echo $u->ID; ?>'" class="btn_reset_password cursor-pointer hover:underline" style="color: rgb(216, 61, 0); font-size: 11px;">Send password reset</button>
                                                <span class="mx-1" style="color: rgb(156, 163, 175); font-size: 11px;">|</span>
                                                <button onclick="window.location='?page=ci_user_logs&user_view=<?php echo $u->ID; ?>'" class="cursor-pointer hover:underline" style="color: rgb(216, 61, 0); font-size: 11px;">Log</button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <?php echo $u->display_name; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="mailto:<?php echo $u->user_email; ?>" class="text-sm hover:underline" style="color: rgb(216, 61, 0);"><?php echo $u->user_email; ?></a>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($is_role_disabled) { ?>
                                                <span class="text-xs text-gray-400"><?php echo esc_html($data_role['name']); ?></span>
                                            <?php } else { ?>
                                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden text-foreground [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground"><?php echo esc_html($data_role['name']); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <?php echo trpdd_get_user_office_html($u->ID); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4 border-t">
                        <div class="text-sm text-gray-600"><?php echo $user_query->get_total(); ?> items</div>
                        <div class="flex items-center gap-2">

                            <a href="<?php echo esc_url($prev_url); ?>"
                                class="inline-flex items-center justify-center h-8 rounded-md px-3 gap-1.5 border
                                <?php echo ($paged <= 1) ? 'pointer-events-none opacity-50' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m15 18-6-6 6-6"></path>
                                </svg>
                            </a>

                            <span class="text-sm text-gray-600">
                                <?php echo esc_html($paged); ?> of <?php echo esc_html($total_pages); ?>
                            </span>

                            <a href="<?php echo esc_url($next_url); ?>"
                                class="inline-flex items-center justify-center h-8 rounded-md px-3 gap-1.5 border
                                <?php echo ($paged >= $total_pages) ? 'pointer-events-none opacity-50' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m9 18 6-6-6-6"></path>
                                </svg>
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var url_main_page = 'ci_user_users';
    var paged = '<?php echo $paged; ?>';
    var keyword_search = '<?php echo $keyword_search; ?>';
    var role_search = '<?php echo $role_search; ?>';

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

    function delete_users() {
        let ids = [];

        $('button[role="checkbox"][aria-checked="true"]').each(function() {
            let uid = $(this).data("user-id");
            if (uid) ids.push(uid);
        });

        if (ids.length === 0) {
            Swal.fire('Warning', 'Please select at least one user.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Delete User',
            text: 'Are you sure you want to delete the selected users?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            customClass: {
                confirmButton: 'btn_yes',
                cancelButton: 'btn_cancel'
            }
        }).then((result) => {

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'delete_users',
                ids: ids
            }, function(response) {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.data,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn_success'
                        }
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.data, 'error');
                }
            });

        });
    }

    function password_reset_users() {
        let ids = [];

        $('button[role="checkbox"][aria-checked="true"]').each(function() {
            let uid = $(this).data("user-id");
            if (uid) ids.push(uid);
        });

        if (ids.length === 0) {
            Swal.fire('Warning', 'Please select at least one user.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Send password reset',
            text: 'Send password reset to multiple users?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            customClass: {
                confirmButton: 'btn_yes',
                cancelButton: 'btn_cancel'
            }
        }).then((result) => {

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Sending...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'bulk_send_password_reset',
                ids: ids
            }, function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.data,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn_success'
                        }
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.data, 'error');
                }
            });

        });
    }


    function resend_welcome_users() {
        let ids = [];

        $('button[role="checkbox"][aria-checked="true"]').each(function() {
            let uid = $(this).data("user-id");
            if (uid) ids.push(uid);
        });

        if (ids.length === 0) {
            Swal.fire('Warning', 'Please select at least one user.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Resend Welcome',
            text: 'Confirm sending the welcome message again to all users?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            customClass: {
                confirmButton: 'btn_yes',
                cancelButton: 'btn_cancel'
            }
        }).then((result) => {

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Sending...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'bulk_resend_welcome',
                ids: ids
            }, function(response) {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.data,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn_success'
                        }
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.data, 'error');
                }
            });

        });
    }


    function change_role_users(role) {
        let ids = [];

        $('button[role="checkbox"][aria-checked="true"]').each(function() {
            let uid = $(this).data("user-id");
            if (uid) ids.push(uid);
        });

        if (ids.length === 0) {
            Swal.fire('Warning', 'Please select at least one user.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Change Role',
            text: 'Apply new role to selected users?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Apply',
            customClass: {
                confirmButton: 'btn_yes',
                cancelButton: 'btn_cancel'
            }
        }).then((result) => {

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'bulk_change_role',
                ids: ids,
                role: role
            }, function(response) {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.data,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn_success'
                        }
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.data, 'error');
                }
            });

        });
    }

    jQuery(document).ready(function() {
        initCustomSelect("select");
        $(".td_menu_user").hover(
            function() {
                $(this).find(".sub_menu").stop(true, true).fadeIn(150);
            },
            function() {
                $(this).find(".sub_menu").stop(true, true).fadeOut(150);
            }
        );

        $("#btn_search_user").click(function() {
            var inp_search = $("#inp_search").val();
            window.location = "?page=" + url_main_page + "&s=" + inp_search;
        });

        $(document).on('click', 'button[role="checkbox"]', function() {
            const $btn = $(this);
            const name = $btn.attr("name");
            const newState = $btn.attr("data-state") === "unchecked" ? "checked" : "unchecked";

            $btn.attr("data-state", newState);
            updateCheckboxUI($btn);
        });

        $("#btn_action_apply").click(function() {
            var sel_bulk_action = $("#sel_bulk_action").val();
            if (sel_bulk_action == "delete") {
                delete_users();
                return false;
            }

            if (sel_bulk_action == "password_reset") {
                password_reset_users();
                return false;
            }

            if (sel_bulk_action == "resend_welcome") {
                resend_welcome_users();
                return false;
            }
        });

        $("#btn_sel_all").click(function() {
            let emp_check_all = $(this);
            let state = $(this).data("state");
            if (state == 'unchecked') state = 'checked';
            else state = 'unchecked';
            $(this).data("state", state);
            updateCheckboxUI(emp_check_all);
            setTimeout(function() {
                $('button[role="checkbox"]').each(function() {
                    $(this).attr({
                        'data-state': state
                    });
                    updateCheckboxUI($(this));
                });
            }, 300);
        });

        $("#btn_change_roles").click(function() {
            let val_role = $("#sel_change_role").val();
            if (val_role != "change_role_to") {
                change_role_users(val_role);
            } else {
                Swal.fire('Warning', 'Please select the type of permissions for the user!Please select at least one user.', 'warning');
                return;
            }
        });

        $(".btn_role").click(function() {
            var id_role = $(this).data("id");
            window.location = "?page=" + url_main_page + "&r=" + id_role;
        });

        $(".btn_del_one").click(function() {
            var id_user = $(this).data("id");
            Swal.fire({
                title: 'Delete User',
                text: 'Are you sure you want to delete user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                customClass: {
                    confirmButton: 'btn_yes',
                    cancelButton: 'btn_cancel'
                }
            }).then((result) => {

                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'Deleting...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'delete_one_user',
                    user_id: id_user
                }, function(response) {
                    Swal.close();

                    if (response.success) {
                        Swal.fire({
                            title: 'Success',
                            text: response.data,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn_success'
                            }
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.data, 'error');
                    }
                });

            });
        });

        $(".btn_reset_password").click(function() {
            var id_user = $(this).data("id");
            Swal.fire({
                title: 'Send password reset',
                text: 'Start sending a password reset request to this user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, sending!',
                customClass: {
                    confirmButton: 'btn_yes',
                    cancelButton: 'btn_cancel'
                }
            }).then((result) => {

                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'Processing...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                jQuery.post("admin.php?page=" + url_main_page, {
                    trajax: 'send_password_reset',
                    user_id: id_user
                }, function(response) {
                    Swal.close();

                    if (response.success) {
                        Swal.fire({
                            title: 'Success',
                            text: response.data,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn_success'
                            }
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.data, 'error');
                    }
                });

            });
        });

        $("#btn_export_all").click(async function() {
            var export_type = "csv";
            var $url = location.href + '&trajax=export';
            $url += '&paged=' + paged;
            $url += '&s=' + keyword_search;
            $url += '&r=' + role_search;

            var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');
            try {
                const response = await fetch($url);
                const disposition = response.headers.get("Content-Disposition");
                let filename = "download." + export_type;

                if (disposition && disposition.includes("filename=")) {
                    const match = disposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) filename = match[1];
                }

                const blob = await response.blob();

                const a = document.createElement("a");
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                a.click();

                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
            return false;
        });
    });
</script>