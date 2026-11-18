<div class="wrap">
    <?php
    global $wpdb, $blog_id;
    global $ci_all_btypes;
    $user_id = get_current_user_id();

    include_once(TRCPDD_PATH . 'pages/formdata.php');
    $act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

    $where_group = '';

    if ($current_role == 'distributor') {
        $dis = $wpdb->get_row("SELECT * FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
        $dis_id = $dis->dis_ID;
        $where_group .= " and gr_distributor_ID = {$dis_id} ";
    }


    if (isset($_POST['save_group'])) {
        $data = $_POST['data'];
        $data['gr_princial'] = (is_array($data['gr_princial'])) ? json_encode($data['gr_princial']) : '';

        if ($data['gr_ID'] > 0) {
            $rs = $wpdb->update('ci_marketing_groups', $data, array('gr_ID' => $data['gr_ID']));
            if ($rs === false) {
                Tr_Session_Class::add_error('Error. Please try again. ' . $wpdb->last_error);
            } else {

                Tr_Session_Class::add('Updated group');
            }
        } else {
            unset($data['gr_ID']);
            if ($current_role == 'distributor') {
                $data['gr_distributor_ID'] = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");
            }
            $rs = $wpdb->insert('ci_marketing_groups', $data);
            $group_id = $wpdb->insert_id;
            if ($rs) {
                Tr_Session_Class::add('Added group - <a href="' . Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group_id), false) . '">Edit</a>
                 - <a href="' . Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $group_id), false) . '">Add Members</a>');


                //create new rep leader if contact info not empty
                $email = $data['gr_email'];
                if (!empty($email) && is_email($email)) {
                    $pass = wp_generate_password(12, false);
                    $userarr = array(
                        'user_login' => $email,
                        'user_email' => $email,
                        'user_pass' => $pass,
                        'first_name' => $data['gr_contact_name'],
                        'repplay_name' => $data['gr_contact_name'],
                        'role' => 'lab_rep'
                    );
                    $user_id = wp_insert_user($userarr);
                    if (!is_wp_error($user_id)) {
                        $rs = trpdd_send_mail_create_user($user_id, $pass);

                        Tr_Session_Class::add('Created User as leader: ' . $email);
                        if ($rs) {
                            Tr_Session_Class::add('Sent email to ' . $email);
                        } else {
                            Tr_Session_Class::add_error('Error while send email to ' . $email);
                        }
                        update_user_meta($user_id, '_marketing_group', $group_id);

                        $wpdb->update('ci_marketing_groups', array('gr_admins' => $user_id), array('gr_ID' => $group_id));
                    } else {
                        Tr_Session_Class::add_error($user_id->get_error_message());
                    }
                }
                $data = array();
            } else {
                Tr_Session_Class::add_error('Error. Please try again. ' . $wpdb->last_error);
            }
        }
    } else if (isset($_POST['save_group_members'])) {
        $data = $_POST['data'];
        if (!is_array($data['gr_admins'])) $data['gr_admins'] = array();
        if (!is_array($data['gr_members'])) $data['gr_members'] = array();
        if ($data['gr_ID'] > 0) {
            //update reps
            $wpdb->update('ci_lab_reps', array('rep_marketing_group_ID' => ''), array('rep_marketing_group_ID' => $data['gr_ID']));
            foreach ($data['gr_members'] as $rep_id) {
                $wpdb->update('ci_lab_reps', array('rep_marketing_group_ID' => $data['gr_ID']), array('rep_ID' => $rep_id));
            }

            $data['gr_admins'] = @implode(',', @$data['gr_admins']);
            $data['gr_members'] = @implode(',', @$data['gr_members']);
            $wpdb->update('ci_marketing_groups', $data, array('gr_ID' => $data['gr_ID']));

            Tr_Session_Class::add('Updated group');
            $data = array();
        }
    } else if ($act == 'delete') {
        global $wpdb;
        $wpdb->query("UPDATE `ci_marketing_groups` SET `gr_inactive`='1' WHERE  `gr_ID`='" . $_REQUEST['id'] . "'");
        Tr_Session_Class::add('InActive group');
    } else if ($act == 'active') {
        global $wpdb;
        $wpdb->query("UPDATE `ci_marketing_groups` SET `gr_inactive`='0' WHERE  `gr_ID`='" . $_REQUEST['id'] . "'");
        Tr_Session_Class::add('Active group');
    }


    if ($act == 'add' || $act == 'edit') {
        if ($act == 'edit') {
            $id = $_REQUEST['id'];
            $data = $wpdb->get_row("select * from ci_marketing_groups where gr_ID = {$id} and gr_site_id='{$blog_id}' ", ARRAY_A);
            $gr_princial = json_decode($data['gr_princial'], true);
        } else {
            $data['gr_site_id'] = $blog_id;
        }
    ?>
        <h2>Group
            <?php if ($act == 'edit'): ?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $id)) ?>" class="add-new-h2">Add members</a>
            <?php endif; ?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a>
        </h2>
        <?php Tr_Session_Class::show() ?>
        <form method="post" class="need_validate">
            <input type="hidden" name="data[gr_ID]" value="<?php echo @$data['gr_ID'] ?>" />
            <input type="hidden" name="data[gr_site_id]" value="<?php echo @$data['gr_site_id'] ?>" />


            <div class="frow">
                <h4>Info</h4>
            </div>
            <div class="frow">
                <div class="col2">
                    <label>Name</label>
                    <input type="text" name="data[gr_name]" value="<?php echo @$data['gr_name'] ?>" maxlength="150" required />
                </div>
                <div class="col2">
                    <?php if ($current_role == 'lab' || $current_role == 'administrator' || $current_role == 'administrator_p') { ?>
                        <label>Distributor</label>
                        <select name="data[gr_distributor_ID]">
                            <option value="">Select Distritutor</option>
                            <?php
                            $distributors = $wpdb->get_results("select * from ci_distributors");
                            foreach ($distributors as $dis) {
                                $selected = $data['gr_distributor_ID'] ==  $dis->dis_ID ? 'selected="selected"' : '';
                                echo '<option value="' . $dis->dis_ID . '" ' . $selected . '>' . $dis->dis_name . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>
                </div>
            </div>
            <div class="frow">
                <label>Address</label>
                <input type="text" name="data[gr_address]" value="<?php echo @$data['gr_address'] ?>" maxlength="255" />

            </div>
            <div class="frow">
                <div class="col3">
                    <label>City</label>
                    <input type="text" name="data[gr_city]" value="<?php echo @$data['gr_city'] ?>" maxlength="100" />
                </div>
                <div class="col3">
                    <label>State</label>
                    <?php tr_select_option('data[gr_state]', tr_get_states(), @$data['gr_state']) ?>
                </div>
                <div class="col3">
                    <label>Zip</label>
                    <input type="text" name="data[gr_zip]" value="<?php echo @$data['gr_zip'] ?>" maxlength="100" />
                </div>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Telephone</label>
                    <input type="text" name="data[gr_phone]" value="<?php echo @$data['gr_phone'] ?>" maxlength="100" />
                </div>
                <div class="col3">
                    <label>Fax</label>
                    <input type="text" name="data[gr_fax]" value="<?php echo @$data['gr_fax'] ?>" maxlength="100" />
                </div>
                <div class="col3">
                    <label>Website</label>
                    <input type="text" name="data[gr_website]" value="<?php echo @$data['gr_website'] ?>" maxlength="255" />
                </div>
            </div>
            <div class="frow">
                <div class="col5">
                    <label>Company Type</label>
                </div>
                <div class="col2 linline lcheckboxes">
                    <label><input type="radio" name="data[gr_company_type]" value="LLC" maxlength="100" <?php checked('LLC', @$data['gr_company_type']) ?> /> LLC</label>
                    <label><input type="radio" name="data[gr_company_type]" value="C Corp" maxlength="100" <?php checked('C Corp', @$data['gr_company_type']) ?> /> C Corp</label>
                    <label><input type="radio" name="data[gr_company_type]" value="S Corp" maxlength="100" <?php checked('S Corp', @$data['gr_company_type']) ?> /> S Corp</label>
                    <label><input type="radio" name="data[gr_company_type]" value="Other" maxlength="100" <?php checked('Other', @$data['gr_company_type']) ?> /> Other</label>
                    <input type="text" name="data[gr_company_type_other]" value="<?php echo @$data['gr_website'] ?>" maxlength="255" style="width:60px" />
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>State of Incorporation</label>
                    <?php tr_select_option('data[gr_state_inc]', tr_get_states(), @$data['gr_state_inc']) ?>
                </div>
                <div class="col3">
                    <label>EIN</label>
                    <input type="text" name="data[gr_state_inc_ein]" value="<?php echo @$data['gr_state_inc_ein'] ?>" maxlength="100" />
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
                    <input type="text" name="data[gr_contact_name]" value="<?php echo @$data['gr_contact_name'] ?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Telephone</label>
                    <input type="text" name="data[gr_contact_phone]" value="<?php echo @$data['gr_contact_phone'] ?>" maxlength="100" />
                </div>
                <div class="col4">
                    <label>Fax</label>
                    <input type="text" name="data[gr_contact_fax]" value="<?php echo @$data['gr_contact_fax'] ?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>Email</label>
                    <input type="text" name="data[gr_email]" value="<?php echo @$data['gr_email'] ?>" maxlength="255" />
                </div>
            </div>

            <div class="frow">
                <h4>Principal</h4>
            </div>

            <?php for ($i = 1; $i <= 3; $i++): ?>

                <div class="frow">
                    <div class="col4">
                        <label>Principal Name</label>
                        <input type="text" name="data[gr_princial][name<?php echo $i ?>]" value="<?php echo @$gr_princial['name' . $i] ?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Ownership %</label>
                        <input type="text" name="data[gr_princial][ownership<?php echo $i ?>]" value="<?php echo @$gr_princial['ownership' . $i] ?>" maxlength="100" />
                    </div>
                    <div class="col4">
                        <label>Cellphone</label>
                        <input type="text" name="data[gr_princial][phone<?php echo $i ?>]" value="<?php echo @$gr_princial['phone' . $i] ?>" maxlength="255" />
                    </div>
                    <div class="col4">
                        <label>Email</label>
                        <input type="text" name="data[gr_princial][email<?php echo $i ?>]" value="<?php echo @$gr_princial['email' . $i] ?>" maxlength="255" />
                    </div>
                </div>

            <?php endfor ?>


            <div class="frow">
                <h4>Payment Preference</h4>
            </div>
            <div class="frow linline lcheckboxes">
                <label><input type="radio" name="data[gr_payment_type]" class="payment_check" value="Check" <?php checked('Check', @$data['gr_payment_type']) ?> /> Check</label>
                <label><input type="radio" name="data[gr_payment_type]" value="ACH" <?php checked('ACH', @$data['gr_payment_type']) ?> /> ACH</label>
            </div>
            <div class="frow payment_info">
                <div class="col4">
                    <label>Bank Name</label>
                    <input type="text" name="data[gr_payment_bank_name]" value="<?php echo @$data['gr_payment_bank_name'] ?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>ABA</label>
                    <input type="text" name="data[gr_payment_aba]" value="<?php echo @$data['gr_payment_aba'] ?>" maxlength="100" />
                </div>

                <div class="col4">
                    <label>Account Number</label>
                    <input type="text" name="data[gr_payment_account_number]" value="<?php echo @$data['gr_payment_account_number'] ?>" maxlength="255" />
                </div>
                <div class="col4">
                    <label>Account Name</label>
                    <input type="text" name="data[gr_payment_account_name]" value="<?php echo @$data['gr_payment_account_name'] ?>" maxlength="255" />
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <label>Commission</label>
                    <input type="text" name="data[gr_comission]" value="<?php echo $data['gr_comission'] ?>" />
                </div>
                <div class="col3">
                    <label>Start Date</label>
                    <input type="text" name="data[gr_sdate]" class="hasdate" value="<?php echo $data['gr_sdate'] ?>" />
                </div>
                <div class="col3">
                    <label>End date</label>
                    <input type="text" name="data[gr_edate]" class="hasdate" value="<?php echo $data['gr_edate'] ?>" />
                </div>
            </div>

            <div class="frow">
                <?php
                foreach ($ci_all_btypes as $btype) {
                    $typename = tr_pdd_get_typename($btype);
                    $key = 'gr_' . $btype;
                    $checked = isset($data[$key]) ? $data[$key] : '';
                    if (count($ci_all_btypes) == 1) {
                        $checked = true;
                    }
                ?>
                    <div class="col8">
                        <label>
                            <input type="hidden" name="data[<?php echo $key ?>]" value="0" />
                            <input type="checkbox" name="data[<?php echo $key ?>]" value="1" <?php checked($checked) ?> />
                            <?php echo $typename ?>
                        </label>
                    </div>
                <?php
                }
                ?>
            </div>



            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_group" value="1" />
            </div>
        </form>
        <script>
            jQuery(function($) {
                $('[name="data[gr_payment_type]"]').change(function() {
                    if ($('.payment_check').is(':checked')) {
                        $('.payment_info').hide();
                    } else {
                        $('.payment_info').show();
                    }
                }).change();
            })
        </script>

    <?php

    } else if ($act == 'add_members') {
        $id = $_REQUEST['id'];
        $data = $wpdb->get_row("select * from ci_marketing_groups where gr_ID = {$id} and gr_site_id='{$blog_id}' ", ARRAY_A);
        $joins = '';
        if ($blog_id > 1) {
            $joins .= " join {$wpdb->usermeta} cap ON u.ID = cap.user_id and cap.meta_key like '%{$blog_id}_capabilities' and cap.meta_value !='a:0:{}'";
        }
    ?>
        <h2>Group <?php echo @$data['gr_name'] ?>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id)) ?>" class="add-new-h2">Edit</a>

            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a>
        </h2>
        <?php Tr_Session_Class::show() ?>
        <form method="post" class="need_validate">
            <input type="hidden" name="data[gr_ID]" value="<?php echo @$data['gr_ID'] ?>" />
            <input type="hidden" name="data[gr_site_id]" value="<?php echo @$data['gr_site_id'] ?>" />

            <div class="frow">
                <h4>Sales Regional Manager</h4>
                <ul id="gr_admins" class="list_user_members">
                    <?php
                    if (!empty($data['gr_admins'])) {
                        $users = $wpdb->get_results("select ID,
                        concat(u.user_login ,' (', fname.meta_value ,' ',lname.meta_value ,')') as user_login
                        from {$wpdb->users} u
                        {$joins}
                        left join {$wpdb->usermeta} fname ON fname.user_id = u.ID and fname.meta_key='first_name'
                        left join {$wpdb->usermeta} lname ON lname.user_id = u.ID and lname.meta_key='last_name'
                        Where u.ID in ({$data['gr_admins']})");
                        foreach ($users as $user) {
                    ?>
                            <li><input type="hidden" name="data[gr_admins][]" value="<?php echo $user->ID ?>" /> <?php echo $user->user_login ?><a class="remove left"></a></li>
                    <?php
                        }
                    }
                    ?>
                </ul>
                <input type="text" class="user_auto" rel="gr_admins" placeholder="Search User" style="width:200px" />
            </div>

            <div class="frow">
                <h4>Members</h4>
                <ul id="gr_members" class="list_user_members">
                    <?php
                    $reps = $wpdb->get_results("select * from ci_lab_reps where rep_marketing_group_ID = {$id}");
                    foreach ($reps as $rep) {
                    ?>
                        <li><input type="hidden" name="data[gr_members][]" value="<?php echo $rep->rep_ID ?>" /> <?php echo $rep->rep_email ?> (<?php echo $rep->rep_first . ' ' . $rep->rep_last ?>)<a class="remove left"></a></li>
                    <?php
                    }
                    ?>
                </ul>
                <input type="text" class="lab_rep_auto" rel="gr_members" placeholder="Search Marketing Representative" style="width:200px" />
                <a class="button" href="admin.php?page=ci_manage_reps&act=add&mg=<?php echo $id ?>">Add new Marketing Representative</a>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_group_members" value="1" />
            </div>
        </form>
        <script>
            jQuery(function($) {
                $('.user_auto').each(function() {
                    $('.user_auto').autocomplete({
                        source: ajaxurl + '?action=ajax&autocomplete=user_auto&target=' + $(this).attr('rel'),
                        minLength: 1,
                        select: function(event, ui) {
                            if (ui.item) {
                                var $target = $(this).parent().find('.list_user_members');
                                var $user_id = ui.item.id;
                                var $user_name = ui.item.value;
                                if ($user_id > 0) {
                                    var $added = false;
                                    $('.list_user_members input').each(function() {
                                        if ($(this).val() == $user_id) {
                                            $added = true;
                                        }
                                    });
                                    if ($added) {
                                        alert('Added this user');
                                    } else {
                                        var $new = $('<li><input type="hidden" name="data[' + $target.attr('id') + '][]" value="' + $user_id + '"/> ' + $user_name + '<a class="remove left"></a></li>');
                                        $target.append($new);
                                    }

                                }
                                $(this).val('');
                                return false;
                            }
                        }
                    });
                });
                $('.lab_rep_auto').autocomplete({
                    source: ajaxurl + '?action=ajax&autocomplete=lab_rep_auto&target=' + $(this).attr('rel'),
                    minLength: 1,
                    select: function(event, ui) {
                        if (ui.item) {
                            var $target = $(this).parent().find('.list_user_members');
                            var $user_id = ui.item.id;
                            var $user_name = ui.item.value;
                            if ($user_id > 0) {
                                var $added = false;
                                $('#dis_reps input').each(function() {
                                    if ($(this).val() == $user_id) {
                                        $added = true;
                                    }
                                });
                                if ($added) {
                                    alert('Added this user');
                                } else {
                                    var $new = $('<li><input type="hidden" name="data[' + $target.attr('id') + '][]" value="' + $user_id + '"/> ' + $user_name + '<a class="remove left"></a></li>');
                                    $target.append($new);
                                }

                            }
                            $(this).val('');
                            return false;
                        }
                    }
                });
                $('.list_user_members').on('click', '.remove', function() {
                    $(this).parents('li').remove();
                })
            })
        </script>
    <?php
    } else {
    ?>
        <h2>Marketing Groups <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>

        <?php Tr_Session_Class::show() ?>

        <?php
        $results = $wpdb->get_results("select * from ci_marketing_groups where gr_site_id = '{$blog_id}' {$where_group} order by gr_name asc ");
        ?>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Group Name</th>
                    <th>Sales Regional Manager</th>
                    <th>Members</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($results as $i => $group):
                    $admins = !empty($group->gr_admins) ? explode(',', $group->gr_admins) : array();
                    $members = !empty($group->gr_members) ? explode(',', $group->gr_members) : array();
                    $group->count_admin = count($admins);
                    $group->count_member = count($members);
                    $active = $group->gr_inactive;
                    if ($active == '0') {
                        $txt_action = 'delete';
                        $txt_action_show = 'Inactivate';
                    } else {
                        $txt_action = 'active';
                        $txt_action_show = 'Activate';
                    }
                ?>
                    <tr <?php if ($active == '1') {
                            echo 'class="active"';
                        } ?>>
                        <td><?php echo $group->gr_ID ?></td>
                        <td><?php echo $group->gr_name ?></td>
                        <td><?php echo $group->count_admin ?></td>
                        <td><?php echo $group->count_member ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add_members', 'id' => $group->gr_ID)) ?>">Add Members</a> -
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $group->gr_ID)) ?>">Edit</a> -
                            <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => $txt_action, 'id' => $group->gr_ID)) ?>"><?php echo $txt_action_show; ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
            jQuery(function($) {
                $('a.delete').click(function() {
                    if (confirm('Do you want InActive/Active?') == false) return false;
                });
            })
        </script>
    <?php
    }
    ?>
</div>