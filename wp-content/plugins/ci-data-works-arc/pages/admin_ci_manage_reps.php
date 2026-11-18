<?php
global $wpdb, $current_role, $blog_id;
global $ci_all_btypes;

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$can_change_status = true;


if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'ci_change_rep_inactive') {
    global $wpdb;
    $id = $_REQUEST['id'];
    $status = $_REQUEST['status'];
    $return = array('status' => 'ok');

    $update = array('rep_inactive' => $status);
    if ($status == '1') {
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$id} ");
        if (empty($rep->rep_edate)) {
            $update['rep_edate'] = date_i18n('Y-m-d');
        }
    } else {
        $update['rep_edate'] = '';
    }

    $rs = $wpdb->update('ci_lab_reps', $update, array('rep_ID' => $id));
    if ($rs === false) {
        $return['status'] = 'error';
        $return['msg'] = $wpdb->last_error;
    }

    wp_send_json($return);
    exit;
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

$tabs = array(
    '' => 'Marketing Representatives',
    'move_account' => 'Move Account to New Rep',
    'moving_all_acount_rep' => 'Move All Accts to New Rep',
    'ci_marketing_groups' => 'Marketing Groups'
);

$where_rep = '';

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_rep .= " and rep.lab_ID in ( {$lab_str} )";
}

if ($current_role == 'distributor') {
    $dis = $wpdb->get_row("SELECT * FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
    $dis_id = $dis->dis_ID;
    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);
    $where_rep .= " and (rep.`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    unset($tabs['move_account']);
    unset($tabs['moving_all_acount_rep']);
}
?>
<div class="wrap">
    <?php
    $tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
    if (!isset($tabs[$tab])) {
        $tab = key($tabs);
    }
    ?>
    <h2 class="nav-tab-wrapper">
        <?php

        foreach ($tabs as $k => $txt) {
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'date' => ''), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>
    <?php

    if ($tab == 'move_account') {
        include(TRCPDD_PATH . 'pages/admin_ci_move_account_rep.php');
        return;
    } else if ($tab == 'moving_all_acount_rep') {
        include(TRCPDD_PATH . 'pages/admin_ci_moving_all_acount_rep.php');
        return;
    } else if ($tab == 'ci_marketing_groups') {
        include(TRCPDD_PATH . 'pages/admin_ci_marketing_groups.php');
        return;
    }


    if (isset($_POST['save_action'])) {
        $rep    = $_POST['rep'];
        $rep_id = $rep['rep_ID'];
        $rep['rep_email'] = trim($rep['rep_email']);
        $email  = $rep['rep_email'];

        if (is_array($rep['rep_comission'])) {
            $rep['rep_comission'] = json_encode($rep['rep_comission']);
        }

        //check email
        $exists = $wpdb->get_var("select rep_ID from ci_lab_reps where rep_email='{$email}'");
        if ($exists > 0 && $exists != $rep_id) {
            Tr_Session_Class::add_error('Email is exists');
        } else {
            unset($rep['rep_ID']);
            $rep['rep_princial'] = (is_array($rep['rep_princial'])) ? json_encode($rep['rep_princial']) : '';

            $rep['lab_ID'] = CI_Base_Lab::get_current_lab_id();

            if ($rep_id > 0) {
                $rs = $wpdb->update('ci_lab_reps', $rep, array('rep_ID' => $rep_id));
                if ($rs === false) {
                    Tr_Session_Class::add_error('Error. update Rep wrong to db!' . $wpdb->last_error);
                } else {
                    Tr_Session_Class::add('Updated Rep Successfully!');
                }
            } else {

                if ($current_role == 'distributor') {
                    $rep['rep_distributor_ID'] = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");
                }
                $wpdb->insert('ci_lab_reps', $rep);
                $rep_id = $wpdb->insert_id;
                if ($rep_id > 0) {
                    Tr_Session_Class::add('Created New Rep Successfully!');
                } else {
                    Tr_Session_Class::add_error('Error. create Rep wrong to db!' . $wpdb->last_error);
                }
                $_POST = array();
            }

            if ($rep_id > 0) {
                //check lab_rep user role
                if (!empty($email) && is_email($email)) {
                    $u = get_user_by('email', $email);
                    if ($u) {
                        $user_id = $u->ID;
                        $fname = get_user_meta($user_id, 'first_name', true);
                        $lname = get_user_meta($user_id, 'last_name', true);

                        if (empty($fname)) {
                            update_user_meta($user_id, 'first_name', $rep['rep_first']);
                            $userarr = array(
                                'ID' => $user_id,
                                'first_name' => @$rep['rep_first'],
                                'last_name' => @$rep['rep_last'],
                                'repplay_name' => trim($rep['rep_first'] . ' ' . @$rep['rep_last']),
                            );
                            wp_update_user($userarr);
                        }
                        if (empty($lname)) {
                            update_user_meta($user_id, 'last_name', $rep['rep_last']);
                        }
                    } else {
                        //need create new
                        $pass = wp_generate_password(12, false);
                        $userarr = array(
                            'user_login' => $email,
                            'user_email' => $email,
                            'user_pass' => $pass,
                            'first_name' => $rep['rep_first'],
                            'last_name' => $rep['rep_last'],
                            'repplay_name' => $rep['rep_first'] . ' ' . $rep['rep_last'],
                            'role' => 'lab_rep'
                        );
                        $user_id = wp_insert_user($userarr);
                        if (!is_wp_error($user_id)) {
                            $rs = trpdd_send_mail_create_user($user_id, $pass);

                            Tr_Session_Class::add('Created User: ' . $email);
                            if ($rs) {
                                Tr_Session_Class::add('Sent email to ' . $email);
                            } else {
                                Tr_Session_Class::add_error('Error while send email to ' . $email);
                            }
                        } else {
                            Tr_Session_Class::add_error($user_id->get_error_message());
                        }
                    }
                } else if (!empty($email) && !is_email($email)) {
                    Tr_Session_Class::add_error("Please Check Email");
                }


                if (!is_wp_error($user_id) && $user_id > 0) {
                    update_user_meta($user_id, '_rep_id', $rep_id);
                    foreach (tr_pdd_get_batch_types() as $btype) {
                        update_user_meta($user_id, '_' . $btype, $rep['rep_' . $btype]);
                    }
                    do_action('tr_change_role', $user_id, 'lab_rep');
                }

                //end check
            }
        }
    }
    if ($act == 'edit' || $act == 'add') {

        if ($_REQUEST['id'] > 0) {
            $id = $_REQUEST['id'];
            $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$id}", ARRAY_A);
            $rep_princial = json_decode($rep['rep_princial'], true);
        } else {
            $rep['rep_tox'] = 1;
            $rep['rep_sdate'] = date_i18n('Y-m-d');
        }
    ?>
        <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Marketing Representatives</a> </h2>

        <?php Tr_Session_Class::show() ?>

        <form method="post" class="need_validate">
            <div class="frow">
                <h4>Info</h4>
            </div>

            <div class="frow">
                <div class="col2">
                    <label>First Name</label>
                    <input type="text" name="rep[rep_first]" value="<?php echo @$rep['rep_first'] ?>" maxlength="100" required />
                </div>
                <div class="col2">
                    <label>Last Name</label>
                    <input type="text" name="rep[rep_last]" value="<?php echo @$rep['rep_last'] ?>" maxlength="100" required />
                </div>
            </div>

            <div class="frow">
                <?php if ($current_role == 'lab' || $current_role == 'administrator' || $current_role == 'administrator_p') { ?>
                    <div class="col2">
                        <label>Distributor</label>
                        <select name="rep[rep_distributor_ID]" class="njs_select2">
                            <option value="">Select Distritutor</option>
                            <?php
                            $distributors = $wpdb->get_results("select * from ci_distributors");
                            foreach ($distributors as $dis) {
                                $selected = $rep['rep_distributor_ID'] ==  $dis->dis_ID ? 'selected="selected"' : '';
                                echo '<option value="' . $dis->dis_ID . '" ' . $selected . '>' . $dis->dis_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                <?php } ?>

                <?php
                if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor'))) {
                ?>
                    <div class="col2">
                        <label>Marketing Group</label>
                        <?php
                        $groups_q = $wpdb->get_results("select * from ci_marketing_groups where `gr_inactive`='0'");
                        $groups = array('' => 'Select Marketing Group');
                        foreach ($groups_q as $g) {
                            $groups[$g->gr_ID] = $g->gr_name;
                        }
                        if (@$_GET['mg'] > 0 && $act == 'add') {
                            $rep['rep_marketing_group_ID'] = $_GET['mg'];
                        }
                        tr_select_option('rep[rep_marketing_group_ID]', $groups, @$rep['rep_marketing_group_ID']);
                        ?>
                    </div>
                <?php
                }
                ?>
            </div>
            <div class="frow">
                <label>Address</label>
                <input type="text" name="rep[rep_address]" value="<?php echo @$rep['rep_address'] ?>" maxlength="150" />

            </div>
            <div class="frow">
                <div class="col3">
                    <label>City</label>
                    <input type="text" name="rep[rep_city]" value="<?php echo @$rep['rep_city'] ?>" maxlength="100" />
                </div>
                <div class="col3">
                    <label>State</label>
                    <?php tr_select_option('rep[rep_state]', tr_get_states(), @$rep['rep_state']) ?>
                </div>
                <div class="col3">
                    <label>Zip</label>
                    <input type="text" name="rep[rep_zip]" value="<?php echo @$rep['rep_zip'] ?>" maxlength="12" />
                </div>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Telephone</label>
                    <input type="text" name="rep[rep_phone]" value="<?php echo @$rep['rep_phone'] ?>" maxlength="18" />
                </div>
                <div class="col3">
                    <label>Fax</label>
                    <input type="text" name="rep[rep_fax]" value="<?php echo @$rep['rep_fax'] ?>" maxlength="18" />
                </div>
                <div class="col3">
                    <label>Website</label>
                    <input type="text" name="rep[rep_website]" value="<?php echo @$rep['rep_website'] ?>" maxlength="255" />
                </div>
            </div>
            <div class="frow">
                <div class="col5">
                    <label>Company Type</label>
                </div>
                <div class="col2 linline lcheckboxes">
                    <label><input type="radio" name="rep[rep_company_type]" value="LLC" maxlength="100" <?php checked('LLC', @$rep['rep_company_type']) ?> /> LLC</label>
                    <label><input type="radio" name="rep[rep_company_type]" value="C Corp" maxlength="100" <?php checked('C Corp', @$rep['rep_company_type']) ?> /> C Corp</label>
                    <label><input type="radio" name="rep[rep_company_type]" value="S Corp" maxlength="100" <?php checked('S Corp', @$rep['rep_company_type']) ?> /> S Corp</label>
                    <label><input type="radio" name="rep[rep_company_type]" value="Other" maxlength="100" <?php checked('Other', @$rep['rep_company_type']) ?> /> Other</label>
                    <input type="text" name="rep[rep_company_type_other]" value="<?php echo @$rep['rep_website'] ?>" maxlength="25" style="width:60px" />
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>State of Incorporation</label>
                    <?php tr_select_option('rep[rep_state_inc]', tr_get_states(), @$rep['rep_state_inc']) ?>
                </div>
                <div class="col3">
                    <label>EIN</label>
                    <input type="text" name="rep[rep_state_inc_ein]" value="<?php echo @$rep['rep_state_inc_ein'] ?>" maxlength="150" />
                </div>
                <div class="col3">
                    <label>W9</label><br>
                    <a href="https://www.irs.gov/pub/irs-pdf/fw9.pdf" target="_blank">(click for form)</a>
                </div>
            </div>

            <div class="frow">
                <h4>Contact Info</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Contact Name</label>
                    <input type="text" name="rep[rep_contact_name]" value="<?php echo @$rep['rep_contact_name'] ?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Telephone</label>
                    <input type="text" name="rep[rep_contact_phone]" value="<?php echo @$rep['rep_contact_phone'] ?>" maxlength="18" />
                </div>
                <div class="col4">
                    <label>Fax</label>
                    <input type="text" name="rep[rep_contact_fax]" value="<?php echo @$rep['rep_contact_fax'] ?>" maxlength="18" />
                </div>
                <div class="col4">
                    <label>Email</label>
                    <input type="text" name="rep[rep_email]" value="<?php echo @$rep['rep_email'] ?>" maxlength="250" required />
                </div>
            </div>

            <div class="frow">
                <h4>Principal</h4>
            </div>

            <?php for ($i = 1; $i <= 3; $i++) : ?>

                <div class="frow">
                    <div class="col4">
                        <label>Principal Name</label>
                        <input type="text" name="rep[rep_princial][name<?php echo $i ?>]" value="<?php echo @$rep_princial['name' . $i] ?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Ownership %</label>
                        <input type="text" name="rep[rep_princial][ownership<?php echo $i ?>]" value="<?php echo @$rep_princial['ownership' . $i] ?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Cellphone</label>
                        <input type="text" name="rep[rep_princial][phone<?php echo $i ?>]" value="<?php echo @$rep_princial['phone' . $i] ?>" maxlength="255" />
                    </div>
                    <div class="col4">
                        <label>Email</label>
                        <input type="text" name="rep[rep_princial][email<?php echo $i ?>]" value="<?php echo @$rep_princial['email' . $i] ?>" maxlength="255" />
                    </div>
                </div>

            <?php endfor ?>


            <div class="frow">
                <h4>Payment Preference</h4>
            </div>
            <div class="frow linline lcheckboxes">
                <label><input type="radio" name="rep[rep_payment_type]" class="payment_check" value="Check" <?php checked('Check', @$rep['rep_payment_type']) ?> /> Check</label>
                <label><input type="radio" name="rep[rep_payment_type]" value="ACH" <?php checked('ACH', @$rep['rep_payment_type']) ?> /> ACH</label>
            </div>
            <div class="frow payment_info">
                <div class="col4">
                    <label>Bank Name</label>
                    <input type="text" name="rep[rep_payment_bank_name]" value="<?php echo @$rep['rep_payment_bank_name'] ?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>ABA</label>
                    <input type="text" name="rep[rep_payment_aba]" value="<?php echo @$rep['rep_payment_aba'] ?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Account Number</label>
                    <input type="text" name="rep[rep_payment_account_number]" value="<?php echo @$rep['rep_payment_account_number'] ?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>Account Name</label>
                    <input type="text" name="rep[rep_payment_account_name]" value="<?php echo @$rep['rep_payment_account_name'] ?>" maxlength="255" />
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <?php
                    $commission = json_decode($rep['rep_comission'], true);

                    foreach ($ci_all_btypes as $btype) {
                        $cmm = isset($commission[$btype]) ? $commission[$btype] : '';
                        if (!isset($commission[$btype]) && empty($commission)) {
                            $cmm = $rep['rep_comission'];
                        }
                        echo '<label>Commission.' . strtoupper($btype) . '</label>';
                        echo '<input type="text" name="rep[rep_comission][' . $btype . ']" value="' . $cmm . '" />';
                    }
                    ?>
                    <small>C - Commercial, A - medicAid. M -Medicare, W-Workmen, O -Other</small><br>

                </div>
                <div class="col3"><br>
                    <label>Hide Commission Amt</label>
                    <input type="hidden" name="rep[rep_hide_commission]" value="0" />
                    <input type="checkbox" name="rep[rep_hide_commission]" value="1" <?php checked($rep['rep_hide_commission']) ?>" />
                </div>
                <div class="col6">
                    <label>Start Date</label>
                    <input type="text" name="rep[rep_sdate]" class="hasdate" value="<?php echo $rep['rep_sdate'] ?>" maxlength="15" />
                </div>
                <div class="col6">
                    <label>End date</label>
                    <input type="text" name="rep[rep_edate]" class="hasdate" value="<?php echo $rep['rep_edate'] ?>" maxlength="15" />
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow">
                <?php
                foreach ($ci_all_btypes as $btype) {
                    $typename = tr_pdd_get_typename($btype);
                    $key = 'rep_' . $btype;
                ?>
                    <div class="col5">
                        <label><?php echo $typename ?></label>
                        <input type="hidden" name="rep[<?php echo $key ?>]" value="0" />
                        <input type="checkbox" name="rep[<?php echo $key ?>]" value="1" <?php checked($rep[$key]) ?>" />
                    </div>
                <?php
                }
                ?>

            </div>


            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_action" value="1" />
                <input type="hidden" name="rep[rep_ID]" value="<?php echo @$rep['rep_ID'] ?>" />
            </div>
        </form>
        <script>
            jQuery(function($) {
                $('[name="rep[rep_payment_type]"]').change(function() {
                    if ($('.payment_check').is(':checked')) {
                        $('.payment_info').hide();
                    } else {
                        $('.payment_info').show();
                    }
                }).change();
            })
        </script>
    <?php
    } else {
        if ($act == 'delete') {
            $wpdb->delete('ci_lab_reps', array('rep_ID' => $_REQUEST['id']));
            $user_id = $wpdb->get_var("select user_id from {$wpdb->usermeta} where meta_key = '_rep_id' and meta_value='{$_REQUEST['id']}' ");
            if ($user_id > 0) {
                wp_delete_user($user_id);
            }
            Tr_Session_Class::add('Deleted Marketing Representatives');
        }


        if (isset($_GET['orderby'])) {
            $short = $_GET['orderby'];
        } else {
            $short = 'rep.rep_inactive asc, rep.rep_first';
        }

        $order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
        $orderby = "order by $short $order";

        $sql = "select rep.*,lab.lab_dba_init from ci_lab_reps rep
                        Left Join ci_labs lab ON rep.lab_ID = lab.lab_ID

            where 1 {$where_rep} {$orderby} ";


        $reps = $wpdb->get_results($sql);

        if (isset($_REQUEST['debug'])) {
            var_dump($sql);
        }
    ?>
        <h2>Marketing Representatives <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>
        <?php Tr_Session_Class::show() ?>

        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead class="sticky">
                <tr>
                    <th style="width:4%">#</th>
                    <?php echo tr_tbl_sort('rep.rep_first', 'Name') ?>
                    <?php echo tr_tbl_sort('rep.rep_state', 'State') ?>
                    <th>Start date</th>
                    <th>End date</th>
                    <th style="width:25%">Email</th>

                    <th>Lab</th>

                    <?php if ($can_change_status) : ?>
                        <th>Status</th>
                    <?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reps as $rep) {
                ?>
                    <tr data-id="<?php echo $rep->rep_ID; ?>">
                        <td><?php echo $rep->rep_ID ?></td>
                        <td><?php echo $rep->rep_first . ' ' . $rep->rep_last ?></td>
                        <td><?php echo $rep->rep_state ?></td>
                        <td><?php echo $rep->rep_sdate ?></td>
                        <td><?php echo $rep->rep_edate ?></td>
                        <td><?php echo $rep->rep_email ?></td>

                        <td><?php echo $rep->lab_dba_init ?></td>

                        <?php if ($can_change_status) : ?>
                            <td class="rep_inactive" data-status="<?php echo $rep->rep_inactive; ?>"></td>
                        <?php endif; ?>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $rep->rep_ID)) ?>">Edit</a>

                            <?php if ($user->user_login == 'Administrat0r') {
                            ?>
                                - <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $rep->rep_ID)) ?>">delete</a>
                            <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <script>
            jQuery(function($) {
                var $statuses = {
                    0: 'Active',
                    1: 'Inactive'
                };
                $('.rep_inactive').each(function() {
                    var $html = '<select class="njs_select2">';
                    for (k in $statuses) {
                        $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
                    }
                    $html += '</select>';
                    $(this).html($html);
                    if ($(this).data('status') == '1') {
                        $(this).parents('tr').addClass('row_inactive');
                    }

                });
                $('.rep_inactive select').change(function() {
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
                            act: 'ci_change_rep_inactive',
                            id: $id,
                            status: $vl,
                            trajax: 1
                        },
                        success: function(rs) {
                            $pr.find('.sloading').remove();
                            if (rs.status == 'ok') {
                                if ($vl == '1') {
                                    $pr.parents('tr').addClass('row_inactive');
                                } else {
                                    $pr.parents('tr').removeClass('row_inactive');
                                }
                            } else {
                                $this.change();
                            }
                        },
                        error: function() {
                            alert("Error. Please try again!");

                        }
                    })
                });

                $('a.delete').click(function() {
                    if (confirm('Do you want Delete?') == false) return false;
                })
            })
        </script>
    <?php
    }
    ?>
</div>