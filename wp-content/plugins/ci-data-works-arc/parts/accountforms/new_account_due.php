<?php
global $wpdb, $current_role, $blog_id, $ci_all_btypes;

$user_id = get_current_user_id();
include_once(TRCPDD_PATH . 'pages/formdata.php');

$courier_types = array(
    'fedex' => 'FedEx',
    'ups' => 'UPS',
    'courier' => 'Courier',
);
$courier_types2 = array(
    'willcall' => 'Will Call',
    'dailypickup' => 'Daily Pickup',
);
$lab = array();

$cli = array();
$join_clinic = '';
$join_clinic = '';
$where_clinic = '';
$where_group = '';
$where_cli_group = '';
$where_rep = '';

$can_change_status = false;
$can_add_new = true;
$can_delete = true;
$can_edit = true;


if ($current_role == 'clinic') {
    $cli_id = get_user_meta(get_current_user_id(), '_clinic_id', true);
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
} else if (isset($_REQUEST['clinic_id'])) {
    $cli_id = $_REQUEST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

if (tr_current_role_in(array('office_manager', 'multi_site_manager', 'clinic'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and cID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where .= " and cID in ( {$cli_ids} )";
        }
    }
    $where_rep .= " and 1=0 ";

    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
} else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $where_clinic .= " and `cli_rep-ID`={$rep_id}";
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

    $where_rep .= " and rep_ID = {$rep_id} ";

    if ($rep['rep_inactive'] == 1) {
        $can_change_status = false;
        $can_add_new = false;
        $can_edit = false;
        $can_delete = false;
    }
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);


    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_clinic .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";

    //$can_change_status = true;
} else if ($current_role == 'administrator' || $current_role == 'administrator_p') {
    $can_change_status = true;
} else if ($current_role == 'lab_director') {
    $can_change_status = true;
} else if ($current_role == 'collector') {
    $can_change_status = false;
    $can_add_new = false;
    $can_delete = false;
    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $col_id = get_user_meta($user_id, '_collector_id', true);
    $cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id} ");
    $cids = implode(',', $cids);
    $where_rep .= '';
    $join_clinic .= "";
    $where_clinic .= " and cID in ({$cids})";
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clinic .= " and `cli_lab-ID` in ( {$lab_str} ) ";
}




if ($act == 'list') {
    if ($_REQUEST['act_save'] == 'delete' && $_GET['id'] > 0) {
        $clinic = $wpdb->get_row("select * from ci_clinic where cID = {$_GET['id']} ", ARRAY_A);

        $wpdb->delete('ci_clinic', array('cID' => $_GET['id']));
        Tr_Session_Class::add('Deleted Req record');

        $message = json_encode($clinic);

        trpdd_log_form('delete_clinic', $message);
        trpdd_log_user(get_current_user_id(), 'delete_cli', 'Delete Clinic', $_GET['id'], '', '', '', '');
    }

    if (!empty($_REQUEST['s'])) {
        $where_clinic .= $wpdb->prepare(" and cli_clinic like %s", '%' . $_REQUEST['s'] . '%');
    }

    if ($_REQUEST['rep'] > 0) {
        $where_clinic .= " and `cli_rep-ID` = {$_REQUEST['rep']} ";
    }

    $row_per_page = 100;
    $pagination = new Tr_Pagination_class($row_per_page);
    $pagination->setTotal($wpdb->get_var("SELECT count(*) as count FROM `ci_clinic` cli
            left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
            left join ci_clinic_manager ma on ma.ma_cli_ID = cli.cID
            {$join_clinic}
            where 1  {$where_clinic}"));
    $offset = $pagination->offset();


    $sql = "SELECT cli.*,lr.rep_first,lr.rep_last, ma.ma_email
            FROM `ci_clinic` cli
            left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
            left join ci_clinic_manager ma on ma.ma_cli_ID = cli.cID
            {$join_clinic}
            where 1  {$where_clinic}
            group by cli.cID
             ORDER BY `cli_clinic` asc limit {$offset},{$row_per_page}";

    if (isset($_REQUEST['debug'])) {
        echo $sql;
    }
    $results = $wpdb->get_results($sql);

?>
    <div class="wrap">
        <h2>All ACCOUNT DUE DILIGENCE
            <?php if ($can_add_new) : ?><a class="add-new-h2" href="admin.php?page=ci_data_forms&tab=new_account_due&paged=1">Add New</a><?php endif; ?></h2>
        <?php Tr_Session_Class::show() ?>

        <form method="get" style="float:left">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="act" value="<?php echo $_GET['act'] ?>" />
            <label>Name/Email</label>
            <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
            <?php if ($current_role != 'lab_rep' && $can_add_new) : ?>
                <select name="rep" class="njs_select2">
                    <option value="">Select Rep</option>
                    <?php
                    $reps = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} ORDER BY rep_first,rep_last ASC ");
                    foreach ($reps as $rep) {
                        $selected = $rep->rep_ID == $_REQUEST['rep'] ? 'selected' : '';
                        echo '<option value="' . $rep->rep_ID . '" ' . $selected . '>' . $rep->rep_first . ' ' . $rep->rep_last . '</option>';
                    }
                    ?>
                </select>
            <?php endif; ?>
            <input type="submit" class="button bsty6" value="Search" />

        </form>
        <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead class="sticky">
                    <tr>
                        <th style="width: 50px">#</th>
                        <th>Clinic Name</th>
                        <th style="width:90px">Phone</th>
                        <th style="width: 90px;">Fax</th>
                        <th>Email</th>
                        <th>Rep</th>
                        <th style="width:77px">Date</th>
                        <?php if ($can_change_status) : ?>
                            <th style="width:80px">Status</th>
                        <?php endif; ?>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($results as $row) : ?>
                        <tr data-id="<?php echo $row->cID; ?>">
                            <td><?php echo $row->cID ?></td>
                            <td><?php echo $row->cli_clinic; ?></td>
                            <td><?php echo $row->{'cli_om-phone'} ?></td>
                            <td><?php echo $row->{'cli_om-fax'} ?></td>
                            <td><?php echo $row->ma_email ?></td>
                            <td><?php echo $row->rep_first . ' ' . $row->rep_last ?></td>
                            <td><?php echo $row->cli_sdate; ?></td>
                            <?php if ($can_change_status) : ?>
                                <td class="cli_status" data-status="<?php echo $row->cli_status; ?>"></td>
                            <?php endif; ?>
                            <td>
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->cID)) ?>">Edit</a>
                                <?php if ($can_delete) : ?>
                                    - <a class="delete" data-assen="<?php echo $row->cli_clinic ?>" href="<?php Tr_Base_Class_V6::link(array('act_save' => 'delete', 'id' => $row->cID)) ?>">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <script>
        jQuery(function($) {

            var $statuses = {
                1: 'Active',
                0: 'Inactive'
            };
            $('.cli_status').each(function() {
                var $html = '<select>';
                for (k in $statuses) {
                    $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
                }
                $html += '</select>';
                $(this).html($html);
                if ($(this).data('status') == '0') {
                    $(this).parents('tr').addClass('row_inactive');
                }

            });
            $('.cli_status select').change(function() {
                var $this = $(this);
                var $pr = $(this).parent();
                $pr.find('.sloading').remove();
                $pr.append('<div class="sloading"></div>');
                var $id = $pr.parents('tr').data('id');
                var $vl = $(this).val();
                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    data: {
                        tr_action: 'ci_change_cli_status',
                        id: $id,
                        status: $vl
                    },
                    success: function(rs) {
                        $pr.find('.sloading').remove();
                        if (rs.status == 'ok') {
                            if ($vl == '0') {
                                $pr.parents('tr').addClass('row_inactive');
                            } else {
                                $pr.parents('tr').removeClass('row_inactive');
                            }
                        } else {
                            $this.change();
                        }
                    },
                    error: function() {
                        $this.change();
                    }
                })
            });

            $('a.delete').click(function() {
                if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
            })
        })
    </script>
<?php
    return;
} else if ($act == 'edit') {
    $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
    $cli = $wpdb->get_row("select * from ci_clinic where cID = {$id}", ARRAY_A);

    $lab_id = $cli['cli_lab-ID'];
    $rep_id = $cli['cli_rep-ID'];
    $cli_id = $cli['cID'];

    $cli['cli_payor-mix'] = explode('|', $cli['cli_payor-mix']);
}

if (@$_POST['lab_id'] > 0) {
    $lab_id = @$_POST['lab_id'];
    $cli['cli_lab-ID'] = $lab_id;
}


$reps = array('' => 'Select Rep');
$labs = array('' => 'Select Lab');
if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
} else if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms'))) {

    if (empty($lab_id)) {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }

    $sql = "select * from ci_lab_reps where rep_inactive = 0 ";


    if ($lab_id > 0 && !defined('TRPDD_SAME_LAB')) {
        $rep['lab_ID'] = $lab_id;
        $sql .= " and lab_ID = {$lab_id}";
    }

    $rep_rs = $wpdb->get_results($sql . ' order by rep_first ASC ');
    $las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) ");
    foreach ($las as $l) {
        $labs[$l->lab_ID] = $l->lab_dba_init;
    }
} else {
    if (empty($lab_id)) {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }

    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where_rep .= " and lab_ID in ( {$lab_str} ) ";
    }

    $rep_rs = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} order by rep_first ASC ");
}

if (isset($rep_rs) && count($rep_rs) > 0) {
    foreach ($rep_rs as $r) {
        $reps[$r->rep_ID] = $r->rep_first . ' ' . $r->rep_last . ' (' . $r->rep_email . ')';
    }
}

if ($lab_id > 0) {
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
}

if (isset($cli['cID']) && $cli['cID'] > 0) {
    $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                where cd.cli_ID = {$cli['cID']}", ARRAY_A);

    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$cli['cli_rep-ID']}", ARRAY_A);
    $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$cli['cID']}", ARRAY_A);
    if ($rep && !isset($reps[$rep['rep_ID']])) {
        $reps[$rep['rep_ID']] = $rep['rep_first'] . ' ' . $rep['rep_last'] . ' (' . $rep['rep_email'] . ')';
    }
    if (!$managers) {
        $managers = array();
        $managers[] = array(
            'ma_manager' => '',
            'ma_email' => $cli['cli_om-email'],
        );
    }
} else {
    $cli['cli_status'] = 1;
    $cli['cli_sdate'] = date_i18n('Y-m-d');
}

?>
<div class="wrap">
    <h2>NEW ACCOUNT DUE DILIGENCE <a class="add-new-h2" href="<?php echo Tr_Base_Class_V6::link(array('act' => 'list', 'id' => '')) ?>">Saved form</a> </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post" class="need_validate main_form">

        <div class="frow">
            <h4>PRIMARY FACILITY ACCOUNT INFORMATION</h4>
        </div>

        <div class="frow blabel">
            <div class="col2">
                <div class="frow">
                    <label>PRACTICE/CLINIC NAME</label>
                    <input type="text" name="cli[cli_clinic]" value="<?php echo @$cli['cli_clinic'] ?>" required="" />
                    <input type="hidden" name="cli[cID]" value="<?php echo @$cli['cID'] ?>" />


                </div>
                <div class="frow">
                    <?php if ($can_change_status) { ?>
                        <div class="col2">
                            <label>Status</label>
                            <select name="cli[cli_status]" required class="njs_select2">
                                <?php
                                foreach (array(1 => 'Active', 0 => 'Inactive') as $k => $vl) {
                                    $selected = $k == @$cli['cli_status'] ? 'selected' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php } ?>
                    <div class="col2">
                        <label>START DATE</label>
                        <input type="text" name="cli[cli_sdate]" <?php echo (@$cli['cID'] > 0) ? 'readonly' : 'class="hasdate"' ?> value="<?php echo @$cli['cli_sdate'] ?>" required="" />
                    </div>
                </div>
            </div>

            <div class="col2">
                <label>HOURS OF OPERATION</label>
                <div class="frow ilabel">
                    <div class="col7">
                        <label>Mon</label>
                        <input type="text" name="cli[cli_mon]" value="<?php echo @$cli['cli_mon'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Tue</label>
                        <input type="text" name="cli[cli_tue]" value="<?php echo @$cli['cli_tue'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Wed</label>
                        <input type="text" name="cli[cli_wed]" value="<?php echo @$cli['cli_wed'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Thu</label>
                        <input type="text" name="cli[cli_thu]" value="<?php echo @$cli['cli_thu'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Fri</label>
                        <input type="text" name="cli[cli_fri]" value="<?php echo @$cli['cli_fri'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Sat</label>
                        <input type="text" name="cli[cli_sat]" value="<?php echo @$cli['cli_sat'] ?>" />
                    </div>
                    <div class="col7">
                        <label>Sun</label>
                        <input type="text" name="cli[cli_sun]" value="<?php echo @$cli['cli_sun'] ?>" />
                    </div>
                </div>
                <div class="frow ilabel">
                    <label style="width:80px">Courier Type</label>
                    <select name="cli[cli_courier_type]" style="width:100px;" class="njs_select2">
                        <?php
                        foreach ($courier_types as $tkey => $tname) {
                            $selected = $tkey == $cli['cli_courier_type'] ? 'selected' : '';
                            echo '<option value="' . $tkey . '" ' . $selected . '>' . $tname . '</option>';
                        }
                        ?>
                    </select>
                    <select name="cli[cli_courier_type2]" style="width:100px;" class="njs_select2">
                        <option value="">Select</option>
                        <?php
                        foreach ($courier_types2 as $tkey => $tname) {
                            $selected = $tkey == $cli['cli_courier_type2'] ? 'selected' : '';
                            echo '<option value="' . $tkey . '" ' . $selected . '>' . $tname . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>PRACTICE/CLINIC ADDRESS</label>
                <input type="text" name="cli[cli_street]" value="<?php echo @$cli['cli_street'] ?>" required="" />
            </div>
            <div class="col4">
                <label>CITY</label>
                <input type="text" name="cli[cli_city]" value="<?php echo @$cli['cli_city'] ?>" required="" />
            </div>
            <div class="col4">
                <label>STATE</label>
                <?php tr_select_option('cli[cli_state]', tr_get_states(), @$cli['cli_state'], array('attr' => 'required')) ?>
            </div>
            <div class="col4">
                <label>ZIP CODE</label>
                <input type="text" name="cli[cli_zip]" value="<?php echo @$cli['cli_zip'] ?>" required="" />
            </div>
        </div>


        <div class="frow blabel">
            <div class="col2">
                <label>PHONE NO.</label>
                <input type="text" class="has_phone" name="cli[cli_om-phone]" value="<?php echo @$cli['cli_om-phone'] ?>" required="" />
            </div>
            <div class="col2">
                <label>FAX NO.</label>
                <input type="text" class="has_phone" name="cli[cli_om-fax]" value="<?php echo @$cli['cli_om-fax'] ?>" required="" />
            </div>
        </div>

        <div class="frow">
            <h4>Office Manager</h4>
        </div>

        <div class="frow blabel">
            <div class="col2">
                <label>Name</label>
            </div>
            <div class="col2">
                <label>Email Address</label>
            </div>
        </div>

        <ol id="managers_dv" class="more multirows" style="overflow: auto;max-height:300px">
            <?php
            $offic_required = tr_get_option('ci_pdf_data', 'forms_droffic') ? '' : 'required';
            $max_j = isset($managers) &&  count($managers) > 0 ? count($managers) : ($act == 'print' && $id == 0 ? 5 : 1);
            for ($j = 0; $j < $max_j; $j++) : ?>
                <li class="frow blabel pmanagerrow <?php echo ($j == 0) ? 'default' : 'secondrow' ?> ">
                    <div class="col2">
                        <input type="text" name="manager[<?php echo $j ?>][ma_manager]" value="<?php echo isset($managers[$j]['ma_manager']) ? $managers[$j]['ma_manager'] : '' ?>" <?php echo $offic_required ?> />
                        <input type="hidden" name="manager[<?php echo $j ?>][ma_ID]" value="<?php echo isset($managers[$j]['ma_ID']) ? $managers[$j]['ma_ID'] : '' ?>" />
                    </div>
                    <div class="col2">
                        <input type="email" name="manager[<?php echo $j ?>][ma_email]" value="<?php echo isset($managers[$j]['ma_email']) ? $managers[$j]['ma_email'] : '' ?>" <?php echo $offic_required ?> />
                        <?php if ($act != 'print') : ?>
                            <a class="button remove_multi_row">-</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ($act != 'print') : ?>
            <a class="button add_multi_row add_managerbtn" data-key="manager" data-id="<?php echo $j ?>">+</a>
        <?php endif; ?>

        <div class="frow">
            <h4>SALES REP INFORMATION (required)</h4>
        </div>

        <div class="frow blabel" id="cli_change_div">
            <?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms'))) : ?>
                <div class="col4">
                    <label>Lab</label>
                    <?php if ($blog_id == 1) : ?>
                        <?php tr_select_option('lab[lab_ID]', $labs, $lab_id, array('attr' => 'required')) ?>
                    <?php else : ?>
                        <input type="text" name="lab[lab_dba_init]" value="<?php echo $lab['lab_dba_init'] ?>" readonly required="" />
                        <input type="hidden" name="lab[lab_ID]" value="<?php echo $lab['lab_ID'] ?>">
                    <?php endif; ?>
                </div>
                <div class="col4">
                    <label>SALES REP NAME</label>
                    <?php tr_select_option('rep[rep_ID]', $reps, @$rep['rep_ID'], array('attr' => 'required')) ?>
                </div>
                <div class="col4">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo @$cli['cli_account_manager'] ?>" maxlength="50" />
                </div>
            <?php elseif ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') : ?>
                <div class="col5">
                    <label>SALES REP LAST NAME</label>
                    <input type="text" name="rep[rep_last]" value="<?php echo @$rep['rep_last'] ?>" required="" />
                    <input type="hidden" name="rep[rep_ID]" value="<?php echo @$rep['rep_ID'] ?>" />
                </div>
                <div class="col5">
                    <label>SALES REP FIRST NAME</label>
                    <input type="text" name="rep[rep_first]" value="<?php echo @$rep['rep_first'] ?>" required="" />
                </div>
                <div class="col5">
                    <label>MIDDLE INITIAL</label>
                    <input type="text" name="rep[rep_middle]" value="<?php echo @$rep['rep_middle'] ?>" />
                </div>
                <div class="col5">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo @$cli['cli_account_manager'] ?>" maxlength="50" />
                </div>
            <?php else : ?>
                <div class="col4">
                    <label>SALES REP NAME</label>
                    <?php tr_select_option('rep[rep_ID]', $reps, @$rep['rep_ID'], array('attr' => 'required')); ?>
                </div>
                <div class="col4">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo @$cli['cli_account_manager'] ?>" maxlength="50" />
                </div>
                <div class="col4"></div>


            <?php endif; ?>
            <div class="col4">
                <label>DATE</label>
                <input type="text" class="hasdate today" name="rep[rep_sdate]" value="<?php echo @$rep['rep_sdate'] ?>" required="" />
            </div>
        </div>
        <div class="frow">
            <?php if (tr_get_option('ci_pdf_data', 'enable_clinic_group')) { ?>
                <div class="col4">
                    <label>Clinician Group</label>
                    <?php
                    $groups = $wpdb->get_results("select * from ci_clinician_groups  where 1  {$where_cli_group}");

                    if (isset($_GET['clig'])) {
                        $cli['cli_group_ID'] = $_GET['clig'];
                    }
                    ?>
                    <select name="cli[cli_group_ID]" class="njs_select2">
                        <option value="">Select Clinician Group</option>
                        <?php
                        foreach ($groups as $g) {
                            $selected = $g->clig_ID == @$cli['cli_group_ID'] ? 'selected' : '';
                            echo '<option value="' . $g->clig_ID . '" ' . $selected . ' data-reps="|' . $g->clig_rep_ID . '|">' . $g->clig_name . '</option>';
                        }
                        ?>
                    </select>
                    <a class="add_new_group">Add New Clinician Group</a>
                    <div class="new_group" style="display: none;">
                        <label>New Group</label>
                        <input type="text" name="cli_new_group" value="" />
                    </div>
                </div>
            <?php } ?>
            <div class="col4">
                <label>Marketing Group</label>
                <?php
                if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor'))) {
                    $groups = $wpdb->get_results("select g.*,group_concat('|',rep.rep_ID,'|') as reps from ci_marketing_groups g
                    left JOIN ci_lab_reps rep ON rep.rep_marketing_group_ID = g.gr_ID
                    where 1  {$where_group}
                    Group by g.gr_ID ");

                ?>
                    <select name="cli[cli_marketing_group_ID]" class="njs_select2">
                        <option value="">Select Marketing Group</option>
                        <?php
                        foreach ($groups as $g) {
                            $selected = $g->gr_ID == @$cli['cli_marketing_group_ID'] ? 'selected' : '';
                            echo '<option value="' . $g->gr_ID . '" ' . $selected . ' data-reps="' . $g->reps . '">' . $g->gr_name . '</option>';
                        }
                        ?>
                    </select>
                    <?php
                } else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
                    $group_leader = tr_dpp_get_marketing_group_leader($user_id);
                    if ($group_leader) {
                    ?>
                        <input type="text" value="<?php echo $group_leader->gr_name ?>" readonly />
                        <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group_leader->gr_ID ?>" />
                    <?php
                    } else {
                        $group = tr_dpp_get_marketing_group($user_id);
                    ?>
                        <input type="text" value="<?php echo $group->gr_name ?>" readonly />
                        <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group->gr_ID ?>" />
                <?php
                    }
                }

                ?>
            </div>
            <div class="col4">
                <label>Distributor</label>
                <?php
                if ($current_role == 'distributor') {
                    $distributor = $wpdb->get_var("select dis_name from ci_distributors where dis_user_ID = {$user_id}");
                ?>
                    <input type="text" value="<?php echo $distributor ?>" readonly />
                <?php
                } else {
                    $groups = $wpdb->get_results("select dis.*,group_concat('|',rep.rep_ID,'|') as reps from ci_distributors dis
                    left JOIN ci_lab_reps rep ON rep.rep_distributor_ID = dis.dis_ID
                    Group by dis.dis_ID ");
                ?>
                    <select name="cli[cli_distributor_ID]" class="njs_select2">
                        <option value="">Select Distributor</option>
                        <?php
                        foreach ($groups as $dis) {
                            $selected = $dis->dis_ID == @$cli['cli_distributor_ID'] ? 'selected' : '';
                            if (count($groups) == 1) $selected = 'selected';
                            echo '<option value="' . $dis->dis_ID . '" ' . $selected . ' data-reps="' . $dis->reps . '">' . $dis->dis_name . ' ' . $dis->dis_last . '</option>';
                        }
                        ?>
                    </select>
                <?php
                }
                ?>
            </div>
        </div>

        <div class="frow">
            <h4>PRIMARY FACILITY PHYSICIAN INFORMATION - Please fill out a separate forms for separate locations</h4>
        </div>


        <div class="frow blabel">
            <div class="col6"><label>PHYSICIAN FIRST NAME</label></div>
            <div class="col6"><label>LAST NAME</label></div>
            <div class="col6"><label>SPECIALTY/CREDENTIAL</label></div>
            <div class="col6"><label>NPI</label></div>
            <div class="col6"><label>PHONE NO.</label></div>
            <div class="col6"><label>EMAIL ADDRESS</label></div>
        </div>
        <ol id="doctors_dv">

            <?php
            $max_j = is_array($docs) && count($docs) > 0 ? count($docs) : 1;
            for ($j = 0; $j < $max_j; $j++) : ?>
                <li class="frow blabel pdoctorrow <?php if ($j == 0) echo 'default' ?>">
                    <div class="col6">
                        <input type="text" name="doc[<?php echo $j ?>][doc_first]" value="<?php echo $docs[$j]['doc_first'] ?>" required="" />

                        <input type="hidden" name="doc[<?php echo $j ?>][doc_ID]" value="<?php echo $docs[$j]['doc_ID'] ?>" />
                    </div>
                    <div class="col6">
                        <input type="text" name="doc[<?php echo $j ?>][doc_last]" value="<?php echo $docs[$j]['doc_last'] ?>" required="" />
                    </div>
                    <div class="col6">
                        <input type="text" name="doc[<?php echo $j ?>][doc_specialty]" value="<?php echo $docs[$j]['doc_specialty'] ?>" required="" />
                    </div>
                    <div class="col6">
                        <input type="text" name="doc[<?php echo $j ?>][doc_npi]" value="<?php echo $docs[$j]['doc_npi'] ?>" required="" />
                    </div>
                    <div class="col6">
                        <input type="text" class="has_phone" name="doc[<?php echo $j ?>][doc_phone]" value="<?php echo $docs[$j]['doc_phone'] ?>" />
                    </div>
                    <div class="col6">
                        <input type="email" name="doc[<?php echo $j ?>][doc_email]" value="<?php echo $docs[$j]['doc_email'] ?>" />
                        <?php if ($act != 'print') : ?>
                            <a class="button rm_doctor_row">-</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ($act != 'print') : ?>
            <a class="button add_doctor_row" data-id="<?php echo $j ?>">+</a>
            <div class="note">Note: You can search PHYSICIAN by NPI</div>
        <?php endif; ?>


        <div class="frow">
            <h4>INSURANCE NETWORK PARTICIPATION - Please list</h4>
        </div>

        <div id="insurance_network">
            <?php
            $net_list = explode('|', @$cli['cli_in-net-list']);
            if (count($net_list) == 0) $net_list = array('');
            foreach ($net_list as $j => $net) : ?>
                <div class="frow">
                    <input type="text" name="cli[cli_in-net-list][]" value="<?php echo $net ?>" required="" />
                </div>
            <?php endforeach; ?>
        </div>
        <a class="button add_insurance_network" data-id="<?php echo $j + 1 ?>">+</a>


        <div class="frow percent">
            <h4>PAYOR MIX- Must equal 100% - TOTAL <input type="text" class="payor_total" value="<?php echo $fvalue['payor_total'] ?>" /> %</h4>
        </div>

        <div class="frow ilabel percent">
            <div class="col5">
                <label>PMedicare</label>
                <input type="text" name="cli[cli_payor-mix][0]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][0] ?>" required="" />%
            </div>
            <div class="col5">
                <label>Auto</label>
                <input type="text" name="cli[cli_payor-mix][1]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][1] ?>" required="" />%
            </div>
            <div class="col5">
                <label>Medicaid</label>
                <input type="text" name="cli[cli_payor-mix][2]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][2] ?>" required="" />%
            </div>
            <div class="col5">
                <label>PPO in network</label>
                <input type="text" name="cli[cli_payor-mix][3]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][3] ?>" required="" />%
            </div>
            <div class="col5">
                <label>PPO out of network</label>
                <input type="text" name="cli[cli_payor-mix][4]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][4] ?>" required="" />%
            </div>
        </div>

        <div class="frow ilabel percent">
            <div class="col5">
                <label>BCBS</label>
                <input type="text" name="cli[cli_payor-mix][5]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][5] ?>" required="" />%
            </div>
            <div class="col5">
                <label>Self Pay</label>
                <input type="text" name="cli[cli_payor-mix][6]" class="payor_part" value="<?php echo @$cli['cli_payor-mix'][6] ?>" required="" />%
            </div>

        </div>

        <div class="frow">
            <h4>TEST RESULTS</h4>
        </div>

        <div class="frow iblabel">
            <?php /*
        <label>
            CUSTOM PROVIDER ACKNOWLEDGEMENT FORM COMPLETED
            <input type="hidden" name="cli[cli_poc-test]" value="0"/>
            <input type="checkbox" name="cli[cli_poc-test]" value="1" <?php checked($cli['cli_poc-test'])?>/>
        </label>*/ ?>
            <label>
                FAX RESULTS
                <input type="hidden" name="cli[cli_faxres]" value="0" />
                <input type="checkbox" id="cli_faxes_result" name="cli[cli_faxres]" value="1" <?php checked($cli['cli_faxres']) ?> />
            </label>
            <label>
                WEB PORTAL ACCESS
                <input type="hidden" name="cli[cli_portal]" value="0" />
                <input type="checkbox" name="cli[cli_portal]" value="1" <?php checked($cli['cli_portal']) ?> />
            </label>
            <label>
                EMR/EHR ACCESS
                <input type="hidden" name="cli[cli_emr-ehr]" value="0" />
                <input type="checkbox" name="cli[cli_emr-ehr]" value="1" <?php checked($cli['cli_emr-ehr']) ?> />
            </label>
        </div>


        <div class="frow">
            <h4>&nbspAccess to</h4>
        </div>
        <div class="frow">
            <?php
            foreach ($ci_all_btypes as $btype) {
                $typename = tr_pdd_get_typename($btype);
                $key = 'cli_' . $btype;
                $checked = $cli[$key];
                if (count($ci_all_btypes) == 1) {
                    $checked = true;
                }
            ?>
                <div class="col5">
                    <label><?php echo $typename ?></label>
                    <input type="hidden" name="cli[<?php echo $key ?>]" value="0" />
                    <input type="checkbox" name="cli[<?php echo $key ?>]" value="1" <?php checked($checked) ?>" />
                </div>
            <?php
            }
            ?>

        </div>

        <div class="frow">
            <h4>Current Lab</h4>
        </div>

        <div class="frow ilabel">
            <div class="col4">
                <input type="text" name="cli[cli_current_laboratory]" value="<?php echo @$cli['cli_current_laboratory'] ?>" />
            </div>
            <div class="col4"></div>
            <div class="col4"></div>
        </div>
        <?php if (($can_add_new && $cli['cID'] == 0) || ($can_edit && $cli['cID'] > 0)) { ?>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="cli[cID]" value="<?php echo @$cli['cID'] ?>">
                <input type="hidden" name="tr_action" value="ci_save_form_new_account_due">
            </div>
        <?php } ?>

    </form>
</div>
<script>
    jQuery(function($) {
        $('[name="cli[cli_urine]"], [name="cli[cli_oral]"]').change(function() {
            var $urine = parseInt(0 + $('[name="cli[cli_urine]"]').val());
            var $oral = parseInt(0 + $('[name="cli[cli_oral]"]').val());
            var $total = $urine + $oral;
            $('#total_volumn').val($total);
        }).change();

        function setup_physician_npi_auto() {
            $('[name*="[doc_npi]"]').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_physician_npi_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {
                        var $pr = $(this).parents('.pdoctorrow');
                        $('[name*="[doc_ID]"]', $pr).val(ui.item.id);
                        $('[name*="[doc_first]"]', $pr).val(ui.item.doc_first);
                        $('[name*="[doc_last]"]', $pr).val(ui.item.doc_last);
                        $('[name*="[doc_specialty]"]', $pr).val(ui.item.doc_specialty);
                        $('[name*="[doc_phone]"]', $pr).val(ui.item.doc_phone);
                        $('[name*="[doc_email]"]', $pr).val(ui.item.doc_email);
                    }
                }
            });

        };
        $("#doctors_dv").on("new_row", function() {
            setup_physician_npi_auto();
        });
        setup_physician_npi_auto();
        $('body').on('click', 'a.add_new_group', function() {
            $(this).hide();
            $('.new_group').show();
            $('#clicli_group_id').val('');
        })
        $('[name="cli[cli_group_ID]"] option').hide();
        $('[name="rep[rep_ID]"]').change(function() {
            $vl = $(this).val();

            var $mg = false;
            $('[name="cli[cli_marketing_group_ID]"] option').each(function() {
                $('[name="cli[cli_marketing_group_ID]"]').val('');
                var $reps = $(this).data('reps') + '';
                if ($(this).val() == '' || $reps.indexOf('|' + $vl + '|') > -1) {
                    $mg = $(this).val();
                }
            })
            if ($mg) {
                $('[name="cli[cli_marketing_group_ID]"]').val($mg);
            }

            var $dis = false;
            $('[name="cli[cli_distributor_ID]"] option').each(function() {
                $('[name="cli[cli_distributor_ID]"]').val('');
                var $reps = $(this).data('reps') + '';
                if ($(this).val() == '' || $reps.indexOf('|' + $vl + '|') > -1) {
                    $dis = $(this).val();
                }
            })
            if ($dis) {
                $('[name="cli[cli_distributor_ID]"]').val($dis);
            }

            var $cg = false;
            $('[name="cli[cli_group_ID]"] option').each(function() {
                $('[name="cli[cli_group_ID]"]').val('');
                var $reps = $(this).data('reps') + '';
                if ($(this).val() == '' || $reps.indexOf('|' + $vl + '|') > -1) {
                    $(this).show();
                    $cg = $(this).val();
                } else {
                    $(this).hide();
                }
            })
            if ($cg) {
                $('[name="cli[cli_group_ID]"]').val($cg);
            }

        }).change();
    })
</script>