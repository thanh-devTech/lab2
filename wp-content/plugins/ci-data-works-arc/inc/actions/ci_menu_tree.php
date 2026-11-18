<?php
global $wpdb, $blog_id, $current_role;
$user_id = get_current_user_id();
$tree = array();
$children = array();
$ci_marketing_groups = array();
$childer_mr = array();
$childer_cl = array();

$active = '0';
if (isset($_GET['active'])) {
    $active = $_GET['active'];
}

function trpdd_list_clinic_by_rep($rep_id, $active, $role)
{
    global $wpdb;

    $ci_clinic_arr = $wpdb->get_results("SELECT * FROM `ci_clinic` where `cli_rep-ID`='" . $rep_id . "'");
    $childer_cl = array();
    foreach ($ci_clinic_arr as $cl) {
        $doctor_resual = $wpdb->get_results("SELECT * FROM `ci_doc` a JOIN `ci_clinic_doc` b ON b.`cli_ID` = '" . $cl->cID . "' and a.`doc_ID`=b.`doc_ID`");


        $arr_doctor = array();

        foreach ($doctor_resual as $doct) {
            $doct = array(
                'name' => $doct->doc_first . ' ' . $doct->doc_last,
                'size' => 2047,
                'role' => 'doctor'
            );

            $arr_doctor[] = $doct;
        }

        $a = array(
            'name' => $cl->cli_clinic,
            'size' => 2047,
            'role' => 'clinic'
        );


        if (count($arr_doctor) > 0) {
            $a['children'] = $arr_doctor;
        }


        $childer_cl[] = $a;
    }
    return $childer_cl;
}

function trpdd_list_doc_by_group_clinic_id($clig_ID, $active)
{
    global $wpdb;

    $ci_clinic_list = $wpdb->get_results("SELECT * FROM `ci_clinician_groups` where `clig_ID`='" . $clig_ID . "'");
    $childer_cl = array();
    foreach ($ci_clinic_list as $cl) {

        $doctor_resual = $wpdb->get_results("SELECT * FROM `ci_doc` a JOIN `ci_clinic_doc` b ON b.`cli_ID` = '" . $cl->clig_members . "' and a.`doc_ID`=b.`doc_ID`");

        $arr_doctor = array();

        foreach ($doctor_resual as $doct) {
            $doct = array(
                'name' => $doct->doc_first . ' ' . $doct->doc_last,
                'size' => 2047,
                'role' => 'doctor'
            );

            $arr_doctor[] = $doct;
        }

        $a = array(
            'name' => $cl->clig_name,
            'size' => 2047,
            'role' => 'clinic_group'
        );
        if (count($arr_doctor) > 0) {
            $a['children'] = $arr_doctor;
        }
        $childer_cl[] = $a;
    }
    return $childer_cl;
}

function trpdd_list_clinic_by_dis($dis_id, $active, $role)
{

    global $wpdb;
    $ci_marketing_groups = array();


    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} " . ($active == '0' ? "and `gr_inactive`='0'" : ''));
    $reps_has = array();
    foreach ($arr_marketing_groups as $marketing_groups) {

        $sql_rep = "SELECT * FROM `ci_lab_reps` where `rep_marketing_group_ID`='" . $marketing_groups->gr_ID . "'";
        if (!empty($marketing_groups->gr_admins)) {
            $rep_admin_ids = array();
            foreach (explode(',', $marketing_groups->gr_admins) as $arid) {
                $rep_id = get_user_meta($arid, '_rep_id', true);
                if ($rep_id) {
                    $rep_admin_ids[] = $rep_id;
                }
            }
            if (count($rep_admin_ids) > 0) {
                $rep_admin_ids_str = implode(',', $rep_admin_ids);
                $sql_rep .= " or rep_ID IN ($rep_admin_ids_str)";
            }
        }
        $Marketing_Representative = $wpdb->get_results($sql_rep);

        $childer_mr = array();
        foreach ($Marketing_Representative as $m_r) {
            $childer_cl = trpdd_list_clinic_by_rep($m_r->rep_ID, $active, $role);
            $a = array(
                'name' => $m_r->rep_first . ' ' . $m_r->rep_last,
                'size' => 2047,
                'role' => 'lab_rep'
            );
            $reps_has[] = $m_r->rep_ID;
            if (count($childer_cl) > 0) {
                $a['children'] = $childer_cl;
            }
            $childer_mr[] = $a;
        }
        $a = array(
            'name' => $marketing_groups->gr_name,
            'size' => 2047,
            'active' => $marketing_groups->gr_inactive,
            'role' => 'marketing_group'
        );
        if (count($childer_mr) > 0) {
            $a['children'] = $childer_mr;
        }
        $ci_marketing_groups[] = $a;
    }

    $reps = $wpdb->get_results("SELECT * FROM `ci_lab_reps` where `rep_distributor_ID`={$dis_id} and `rep_marketing_group_ID`='0' " . ($active == '0' ? " and `rep_inactive`='0'" : ''));
    foreach ($reps as $m_r) {
        if (in_array($m_r->rep_ID, $reps_has)) continue;

        $childer_cl = trpdd_list_clinic_by_rep($m_r->rep_ID, $active, $role);
        $a = array(
            'name' => $m_r->rep_first . ' ' . $m_r->rep_last,
            'size' => 2047,
            'active' => $m_r->rep_inactive,
            'role' => 'lab_rep'
        );

        if (count($childer_cl) > 0) {
            $a['children'] = $childer_cl;
        }
        $ci_marketing_groups[] = $a;
    }

    return $ci_marketing_groups;
}


if (tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p'))) {

    $tree_child = array();
    //list distributor
    $ci_distributors = $wpdb->get_results("SELECT * FROM `ci_distributors` " . ($active == '0' ? "WHERE `dis_activer`='0'" : ''));
    foreach ($ci_distributors as $ci) {
        $children_sl = trpdd_list_clinic_by_dis($ci->dis_ID, $active, 'all');
        $a = array(
            'name' => $ci->dis_name,
            'active' => $ci->dis_activer,
            'role' => 'distributor'
        );
        if (count($children_sl) > 0) {
            $a['children'] = $children_sl;
        }
        $tree_child[] = $a;
    }

    $child2 = trpdd_list_clinic_by_dis(0, $active, 'all');
    if (count($child2) > 0) {
        foreach ($child2 as $k => $v) {
            $tree_child[] = $v;
        }
    }



    //return tree

    $tree = array(
        'name' => 'Lab Management',
        'active' => $ci_distributors->dis_activer,
        'role' => 'owner'
    );
    if (count($tree_child) > 0) {
        $tree['children']  = $tree_child;
    }
} else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta($user_id, '_rep_id', true);

    $check_admin = $wpdb->get_row("SELECT * FROM `ci_user_groups` where `gr_admins`='$rep_id'");

    if ($check_admin) {

        $members = $check_admin->gr_members;
        $members = explode($members, ',');
        $member_cil = array();
        foreach ($member as $mr) {
            $cil_child = $wpdb->get_row("select * from `ci_lab_reps` where rep_ID = {$mr}");
            $children = trpdd_list_clinic_by_rep($mr, $active, 'lab_rep');
            $a = array(
                'name' => $rep_child->rep_first . ' ' . $rep_child->rep_last,
                'active' => '0',
                'role' => 'lab_rep'
            );
            if (count($children) > 0) {
                $a['children'] = $children;
            }
            $member_cil[] = $a;
        }

        $tree = array(
            'name' => 'Owner',
            'active' => '0',
            'role' => 'lab_rep'
        );

        if (count($member_cil) > 0) {
            $tree['children']  = $member_cil;
        }
    } else {
        $show_all_clinic_to_members = tr_get_option('ci_pdf_data', 'show_group_clinics_to_all_members');
        if ($show_all_clinic_to_members) {
            $children = array();
            $marketing_group_ID = $wpdb->get_var($wpdb->prepare(
                "SELECT rep_marketing_group_ID FROM ci_lab_reps WHERE rep_ID = %d",
                $rep_id
            ));

            $rep_ids = [$rep_id];

            if (!empty($marketing_group_ID)) {
                $members = $wpdb->get_var($wpdb->prepare(
                    "SELECT gr_members FROM ci_marketing_groups WHERE gr_ID = %d",
                    $marketing_group_ID
                ));

                if (!empty($members)) {
                    $rep_ids = explode(',', $members);
                }
            }

            $rep_ids = array_filter(array_map('intval', $rep_ids));
            foreach ($rep_ids as $r_id) {
                $items = trpdd_list_clinic_by_rep($r_id, $active, 'lab_rep');
                $children = array_merge($children, $items);
            }
        } else {
            $children = trpdd_list_clinic_by_rep($rep_id, $active, 'lab_rep');
        }
        $tree = array(
            'name' => 'Owner',
            'active' => '0',
            'role' => 'lab_rep'
        );

        if (count($children) > 0) {
            $tree['children']  = $children;
        }
    }
} else if ($current_role == 'distributor') {
    $dis = $wpdb->get_row("SELECT * FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");

    $children = trpdd_list_clinic_by_dis($dis->dis_ID, $active, 'distributor');
    $tree = array(
        'name' => $dis->dis_name,
        'active' => isset($dis->dis_activer) ? $dis->dis_activer : '0',
        'role' => 'distributor'
    );

    if (count($children) > 0) {
        $tree['children']  = $children;
    }
} else if ($current_role == 'clinic') {

    $cicl_g = $wpdb->get_row("SELECT * FROM `ci_clinician_groups` Where `clig_admins`='" . $user_id . "'");

    if ($cicl_g->num_rows > 0) {
        $members = $cicl_g->clig_members;
        $members = explode($members, ',');
        $member_rep = array();
        foreach ($member as $mr) {
            $cil_child = $wpdb->get_row("select * from `ci_distributors` where `cID` = {$mr}");
            $children = trpdd_list_doc_by_group_clinic_id($mr, $active);
            $a = array(
                'name' => $cil_child->cli_clinic,
                'active' => '0',
                'role' => 'clinic'
            );
            if (count($children) > 0) {
                $a['children']  = $children;
            }
            $member_rep[] = $a;
        }
        $tree = array(
            'name' => $cicl_g->clig_name,
            'active' => '0',
            'role' => 'clinic_group'
        );
        if (count($children) > 0) {
            $tree['children']  = $children;
        }
    } else {
        $children = trpdd_list_doc_by_group_clinic_id($user_id, $active);
        $tree = array(
            'name' => 'Owner',
            'active' => '0',
            'role' => 'clinic'
        );
        if (count($children) > 0) {
            $tree['children']  = $children;
        }
    }
}




wp_send_json($tree);
