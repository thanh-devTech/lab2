<?php
global $wpdb;
global $is_ci_admin;


include_once(TRCPDD_PATH . 'pages/formdata.php');


function trrandom_color()
{
    global $trrandom_colors;
    if (!is_array($trrandom_colors)) {
        $trrandom_colors = array();
    }

    for ($i = 1; $i <= 100; $i++) {
        $color = 'rgba(' . mt_rand(30, 255) . ',' . mt_rand(30, 255) . ',' . mt_rand(30, 255) . ',0.4)';
        if (!in_array($color, $trrandom_colors)) {
            $trrandom_colors[] = $color;
            break;
        }
    }
    return $color;
}


$tabs = array(
    'clinic' => 'Clinic',
    'doctor' => 'Doctor',
    'collector' => 'Collector',
    'patient' => 'Patient'
);
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

if (!isset($tabs[$tab])) {
    $tab = key($tabs);
}
?>
<h2 class="nav-tab-wrapper">
    <?php

    foreach ($tabs as $k => $txt) {
        $class = $tab == $k ? 'nav-tab-active' : '';
        $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'date' => '', 's' => '', 'autodob' => '', 'dob' => '', 'first_name' => '', 'last_name' => '', 'npi' => ''), false);
        echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
    }
    ?>
</h2>

<?php
$pagefile = trpdd_get_file_path('fix_duplicate',$tab);
if(file_exists($pagefile))
{
    include($pagefile);
}
else if ($tab == 'doctor') {
    if (isset($_POST['main_id']) && $_POST['main_id'] > 0 && isset($_POST['second_id'])) {
        $m_id = $_POST['main_id'];
        if (!is_array($_POST['second_id'])) {
            $_POST['second_id'] = array($_POST['second_id']);
        }

        $doc_main = $wpdb->get_row("select * from ci_doc where doc_ID = {$m_id}");
        if ($doc_main) {
            foreach ($_POST['second_id'] as $s_id) {
                if ($m_id == $s_id) {
                    continue;
                }
                $doc_dup = $wpdb->get_row("select * from ci_doc where doc_ID = {$s_id}");

                foreach (tr_pdd_get_batch_types() as $btype) {
                    $table = tr_pdd_get_table_by_btype($btype);

                    $wpdb->update($table, array('doc_ID' => $m_id), array('doc_ID' => $s_id));
                }


                $wpdb->update('ci_saved' . '_batches', array('doc_ID' => $m_id), array('doc_ID' => $s_id));
                $wpdb->update('ci_custom_providers', array('doc_ID' => $m_id), array('doc_ID' => $s_id));
                $wpdb->update('ci_clinic_doc', array('doc_ID' => $m_id), array('doc_ID' => $s_id));

                $has_clinic_doc = $wpdb->get_var("select doc_ID from ci_clinic_doc where doc_ID ={$m_id} and cli_ID = {$doc_dup->cli_ID}");
                if (!$has_clinic_doc) {
                    $wpdb->insert('ci_clinic_doc', array('doc_ID' => $m_id, 'cli_ID' => $doc_dup->cli_ID));
                }

                $wpdb->delete('ci_doc', array('doc_ID' => $s_id));

                if ($doc_dup->doc_import_ID > 0) {
                    $wpdb->insert('ci_fix_duplicate', array('ID' => $m_id, 'import_ID' => $doc_dup->doc_import_ID, 'ctype' => 'doctor', 'created' => date('Y-m-d H:i:s')));
                }
            }
            Tr_Session_Class::add("moved Doctor from: " . implode(', ', $_POST['second_id']) . ' to #' . $m_id);
        } else {
            Tr_Session_Class::add_error('Not found Main ID: #' . $m_id);
        }
    } else if ($act == 'autofix' && $is_ci_admin) {
        $clis = $wpdb->get_results("select * from ci_clinic ", ARRAY_A);
        foreach ($clis as $cli) {
            $docs = $wpdb->get_results("select doc.* from ci_doc doc
            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
            where cd.cli_ID = {$cli['cID']}
            order by doc_first asc, doc_npi desc", ARRAY_A);

            echo $cli['cli_clinic'];
            $exist_docs = array();
            foreach ($docs as $di => $doc) {
                $dockey = strtolower($doc['doc_first'] . '_' . $doc['doc_last']);

                if (isset($exist_docs[$dockey])) {
                    unset($docs[$di]);
                    $wpdb->delete('ci_clinic_doc', array('doc_ID' => $doc['doc_ID'], 'cli_ID' => $cli['cID']));
                } else {
                    $exist_docs[$dockey] = $doc['doc_ID'];
                }
            }
            echo '<br><br>';
        }

        //check empty
        $docs = $wpdb->get_results("select doc.* from ci_doc doc
        left JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
        where cd.cli_ID is null
        order by doc_first asc, doc_npi desc");

        foreach ($docs as $doc) {

            $m_id = $wpdb->get_var("select doc_ID from ci_doc where doc_first = '{$doc->doc_first}' 
            and doc_last = '{$doc->doc_last}' and doc_ID !={$doc->doc_ID}");
            if ($m_id > 0) {
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $table = tr_pdd_get_table_by_btype($btype);

                    $wpdb->update($table, array('doc_ID' => $m_id), array('doc_ID' => $doc->doc_ID));
                }

                $wpdb->delete('ci_doc', array('doc_ID' => $doc->doc_ID));
            }
        }
    } else if ($act == 'autofixsamename' && $is_ci_admin) {


        //check empty
        $docs = $wpdb->get_results("select doc.* from ci_doc doc
        where (doc.doc_npi is null or doc.doc_npi = '')
        ");

        foreach ($docs as $doc) {

            $m_id = $wpdb->get_var("select doc_ID from ci_doc where doc_first = '{$doc->doc_first}' 
            and doc_last = '{$doc->doc_last}' and ( doc_npi !='' and doc_npi is not null) and doc_ID !={$doc->doc_ID}");
            if ($m_id > 0) {
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $table = tr_pdd_get_table_by_btype($btype);

                    $wpdb->update($table, array('doc_ID' => $m_id), array('doc_ID' => $doc->doc_ID));
                }

                $wpdb->delete('ci_doc', array('doc_ID' => $doc->doc_ID));
                $wpdb->delete('ci_clinic_doc', array('doc_ID' => $doc->doc_ID));
            }
        }
    }
?>

    <h2>Duplicate Doctor</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">
        <div>
            <label>Main ID</label>
            <input type="text" name="main_id" required />

            <label>Second ID (delete)</label>
            <input type="text" name="second_id" required />

            <input type="submit" class="button-primary" name="fix_doctor" value="Move" />
        </div>
    </form>


    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <label>Last Name: </label>
        <input type="text" name="last_name" value="<?php echo isset($_REQUEST['last_name']) ? $_REQUEST['last_name'] : '' ?>" />
        <label>First Name:</label>
        <input type="text" name="first_name" value="<?php echo isset($_REQUEST['first_name']) ? $_REQUEST['first_name'] : '' ?>" />
        <label>NPI:</label>
        <input type="text" name="npi" value="<?php echo isset($_REQUEST['npi']) ? $_REQUEST['npi'] : '' ?>" />
        <input type="submit" class="button-primary  bsty6" value="Search" />
        <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autosearch', 'first_name' => '', 'last_name' => '', 'npi' => '')) ?>">Auto List</a>
        <?php if ($is_ci_admin) { ?>
            <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autofix', 'first_name' => '', 'last_name' => '', 'npi' => '')) ?>">Auto fix</a>
            <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autofixsamename', 'first_name' => '', 'last_name' => '', 'npi' => '')) ?>">Auto fix(same name and empty npi)</a>
        <?php } ?>

    </form>


    <?php
    if (!empty($_REQUEST['first_name']) || !empty($_REQUEST['last_name']) || !empty($_REQUEST['npi'])) {
    ?>
        <form method="post" id="move_items_form">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:30px">Main</th>
                        <th style="width:50px">Second</th>
                        <th style="width:10%">Last Name</th>
                        <th style="width:10%">First Name</th>
                        <th style="width:10%">NPI</th>
                        <th style="width:10%">Address</th>
                        <th style="width:8%">Phone</th>
                        <th>Clinic</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    $where = '';
                    if (!empty($_REQUEST['first_name'])) {
                        $where .= $wpdb->prepare(" and doc.doc_first like %s ", $_REQUEST['first_name'] . '%');
                    }
                    if (!empty($_REQUEST['last_name'])) {
                        $where .= $wpdb->prepare(" and doc.doc_last like %s ", $_REQUEST['last_name'] . '%');
                    }
                    if (!empty($_REQUEST['npi'])) {
                        $where .= $wpdb->prepare(" and doc.doc_npi like %s ", $_REQUEST['npi']);
                    }

                    $sql = "select 
                    doc.*,
                    group_concat(cli.cli_clinic,'|') as clinices
                from ci_doc doc
                left join ci_clinic_doc cd On doc.doc_ID = cd.doc_ID
                left join ci_clinic cli ON cli.cID = cd.cli_ID
                where 1 {$where} 
                group by doc.doc_ID
                order by doc.doc_npi desc, doc.doc_first asc, doc.doc_last asc
               ";
                    $results = $wpdb->get_results($sql);

                    if (isset($_GET['debug'])) {
                        echo $sql;
                    }

                    $doc_npis = array();
                    $doc_names = array();

                    foreach ($results as $i => $row) {
                        $results[$i]->key_name = strtolower($doc->doc_last . $doc->doc_first);
                        if (!is_array($doc_npis[$doc->doc_npi])) {
                            $doc_npis[$doc->doc_npi] = array();
                        }
                        $doc_npis[$doc->doc_npi]['count']++;
                        if ($doc_npis[$doc->doc_npi]['count'] > 1) {
                            $doc_npis[$doc->doc_npi]['color'] = trrandom_color();
                        }

                        if (!is_array($doc_names[$doc->key_name])) {
                            $doc_names[$doc->key_name] = array();
                        }

                        $doc_names[$doc->key_name]['count']++;
                        if ($doc_names[$doc->key_name]['count'] > 1) {
                            $doc_names[$doc->key_name]['color'] = trrandom_color();
                        }
                    }


                    foreach ($results as $row) {
                        $clinis = explode('|', $row->clinices);
                    ?>
                        <tr class="item_<?php echo $row->doc_ID ?>" data-id="<?php echo $row->doc_ID ?>">
                            <td><?php echo $row->doc_ID ?></td>
                            <td><input type="radio" class="main_id" name="main_id" value="<?php echo $row->doc_ID ?>" /> </td>
                            <td><input type="checkbox" class="second_id" name="second_id[]" value="<?php echo $row->doc_ID ?>" /> </td>
                            <td><?php echo $row->doc_last ?></td>
                            <td style="background:<?php echo @$doc_names[$row->key_name]['color'] ?>"><?php echo $row->doc_first ?></td>
                            <td class="editable" data-key="doc_npi" style="background:<?php echo @$doc_npis[$row->doc_npi]['color'] ?>"><?php echo $row->doc_npi ?></td>
                            <td><?php echo $row->doc_address . ' ' . $row->doc_city . ' ' . $row->doc_state . ' ' . $row->doc_zip ?></td>
                            <td class="editable" data-key="doc_phone"><?php echo $row->doc_phone ?></td>
                            <td>
                                <?php
                                foreach ($clinis as $cli) {
                                    if (empty($cli)) continue;
                                    echo '<li>' . $cli . '</li>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    }

                    ?>
                </tbody>
            </table>
            <div style="margin-top:10px;">
                <input type="hidden" name="act" value="move_duplicate" />
                <input type="hidden" name="first_name" value="<?php echo isset($_REQUEST['first_name']) ? $_REQUEST['first_name'] : '' ?>" />
                <input type="hidden" name="last_name" value="<?php echo isset($_REQUEST['last_name']) ? $_REQUEST['last_name'] : '' ?>" />
                <input type="hidden" name="npi" value="<?php echo isset($_REQUEST['npi']) ? $_REQUEST['npi'] : '' ?>" />

                <input type="submit" class="button-primary" value="Move" />
                <input type="button" class="button checkallsecond" value="Check all Second" />

                <span> <?php echo count($results) ?> items</span>
            </div>
        </form>
        <script>
            jQuery(function($) {
                function tr_save_doctor($obj) {
                    var $pr = $obj.parents('td.editable');
                    var $key = $pr.data('key');
                    $value = $obj.val();
                    $id = $pr.parent().data('id');
                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        type: 'POST',
                        data: {
                            tr_action: 'ci_save_doctor_ajax',
                            id: $id,
                            value: $value,
                            key: $key
                        },
                        dataType: 'json',
                        success: function(rs) {
                            if (rs.status == 'ok') {
                                $pr.html($value);
                                $pr.removeClass('editing');
                            } else {
                                alert('Error Please try again');
                            }
                            $pr.remove('.sloading');
                        },
                        error: function() {
                            alert('Error Please try again');
                            $pr.remove('.sloading');
                        }
                    })
                }
                $('.editable').click(function() {
                    if ($(this).hasClass('editing')) return;
                    $(this).addClass('editing');
                    var $vl = $(this).html();
                    $html = $('<input type="text" />');
                    $html.val($vl);

                    $(this).html($html);
                }).on('keypress', 'input', function(e) {
                    var code = e.keyCode || e.which;
                    if (code == 13) {
                        tr_save_doctor($(this));
                        return false;
                    }
                }).on('change', 'select', function() {
                    tr_save_doctor($(this));
                })

            })
        </script>

    <?php
    } else if ($act == 'autosearch') {

        $groupby = " group by doc_last,doc_first ";
        $sql = "select *,count(*) as c 
                        from ci_doc 
                        where doc_first !='' or doc_last!=''
                        {$groupby} 
                        order by c desc,doc_npi asc, doc_first 
                        ";
        $results = $wpdb->get_results($sql);
    ?>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">Duplicate</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>NPI</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($results as $doc) {
                    if ($doc->c == 1) continue;
                ?>
                    <tr>
                        <td><?php echo $doc->c ?></td>
                        <td><?php echo $doc->doc_last ?></td>
                        <td><?php echo $doc->doc_first ?></td>
                        <td><?php echo $doc->doc_npi ?></td>
                        <td><?php echo $doc->doc_address . ' ' . $doc->doc_city . ' ' . $doc->doc_state . ' ' . $doc->doc_zip ?></td>
                        <td><?php echo $doc->doc_phone ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'last_name' => $doc->doc_last, 'first_name' => $doc->doc_first)) ?>">List</a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    <?php
    }
    ?>

<?php
} else if ($tab == 'collector') {
    if (isset($_POST['main_id']) && $_POST['main_id'] > 0 && isset($_POST['second_id'])) {
        $m_id = $_POST['main_id'];
        if (!is_array($_POST['second_id'])) {
            $_POST['second_id'] = array($_POST['second_id']);
        }

        $col_main = $wpdb->get_row("select * from ci_collector where col_ID = {$m_id}");
        if ($col_main) {
            foreach ($_POST['second_id'] as $s_id) {
                if ($m_id == $s_id) {
                    continue;
                }
                $col_dup = $wpdb->get_row("select * from ci_collector where col_ID = {$s_id}");
                $cliids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$s_id}");

                foreach (tr_pdd_get_batch_types() as $btype) {
                    $table = tr_pdd_get_table_by_btype($btype);

                    $wpdb->update($table, array('col_ID' => $m_id), array('col_ID' => $s_id));
                }


                $wpdb->update('ci_saved' . '_batches', array('col_ID' => $m_id), array('col_ID' => $s_id));
                $wpdb->update('ci_clinic_collector', array('col_ID' => $m_id), array('col_ID' => $s_id));

                foreach ($cliids as $cliid) {
                    $has_clinic_collector = $wpdb->get_var("select col_ID from ci_clinic_collector where col_ID ={$m_id} and cli_ID = {$cliid}");
                    if (!$has_clinic_collector) {
                        $wpdb->insert('ci_clinic_collector', array('col_ID' => $m_id, 'cli_ID' => $cliid));
                    }
                }


                $wpdb->delete('ci_collector', array('col_ID' => $s_id));

                if ($col_dup->col_import_ID > 0) {
                    $wpdb->insert('ci_fix_duplicate', array('ID' => $m_id, 'import_ID' => $col_dup->col_import_ID, 'ctype' => 'collector', 'created' => date('Y-m-d H:i:s')));
                }
            }
            Tr_Session_Class::add("moved Collector from: " . implode(', ', $_POST['second_id']) . ' to #' . $m_id);
        } else {
            Tr_Session_Class::add_error('Not found Main ID: #' . $m_id);
        }
    }
?>

    <h2>Duplicate Collector</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">
        <div>
            <label>Main ID</label>
            <input type="text" name="main_id" required />

            <label>Second ID (delete)</label>
            <input type="text" name="second_id" required />

            <input type="submit" class="button-primary" name="fix_coltor" value="Move" />
        </div>
    </form>


    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <label>Last Name: </label>
        <input type="text" name="last_name" value="<?php echo isset($_REQUEST['last_name']) ? $_REQUEST['last_name'] : '' ?>" />
        <label>First Name:</label>
        <input type="text" name="first_name" value="<?php echo isset($_REQUEST['first_name']) ? $_REQUEST['first_name'] : '' ?>" />
        <input type="submit" class="button-primary  bsty6" value="Search" />
        <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autosearch', 'first_name' => '', 'last_name' => '')) ?>">Auto List</a>

    </form>


    <?php
    if (!empty($_REQUEST['first_name']) || !empty($_REQUEST['last_name'])) {
    ?>
        <form method="post" id="move_items_form">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:30px">Main</th>
                        <th style="width:50px">Second</th>
                        <th style="width:10%">Last Name</th>
                        <th style="width:10%">First Name</th>
                        <th style="width:10%">Address</th>
                        <th style="width:8%">Phone</th>
                        <th>Clinic</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    $where = '';
                    if (!empty($_REQUEST['first_name'])) {
                        $where .= $wpdb->prepare(" and col.col_first like %s ", $_REQUEST['first_name'] . '%');
                    }
                    if (!empty($_REQUEST['last_name'])) {
                        $where .= $wpdb->prepare(" and col.col_last like %s ", $_REQUEST['last_name'] . '%');
                    }


                    $sql = "select 
                    col.*,
                    group_concat(cli.cli_clinic,'|') as clinices
                from ci_collector col
                left join ci_clinic_collector cd On col.col_ID = cd.col_ID
                left join ci_clinic cli ON cli.cID = cd.cli_ID
                where 1 {$where} 
                group by col.col_ID
                order by col.col_first asc, col.col_last asc
               ";
                    $results = $wpdb->get_results($sql);

                    if (isset($_GET['debug'])) {
                        echo $sql;
                    }


                    $col_names = array();

                    foreach ($results as $i => $row) {
                        $results[$i]->key_name = strtolower($col->col_last . $col->col_first);


                        if (!is_array($col_names[$col->key_name])) {
                            $col_names[$col->key_name] = array();
                        }

                        $col_names[$col->key_name]['count']++;
                        if ($col_names[$col->key_name]['count'] > 1) {
                            $col_names[$col->key_name]['color'] = trrandom_color();
                        }
                    }


                    foreach ($results as $row) {
                        $clinis = explode('|', $row->clinices);
                    ?>
                        <tr class="item_<?php echo $row->col_ID ?>" data-id="<?php echo $row->col_ID ?>">
                            <td><?php echo $row->col_ID ?></td>
                            <td><input type="radio" class="main_id" name="main_id" value="<?php echo $row->col_ID ?>" /> </td>
                            <td><input type="checkbox" class="second_id" name="second_id[]" value="<?php echo $row->col_ID ?>" /> </td>
                            <td><?php echo $row->col_last ?></td>
                            <td style="background:<?php echo @$col_names[$row->key_name]['color'] ?>"><?php echo $row->col_first ?></td>

                            <td><?php echo $row->col_address . ' ' . $row->col_city . ' ' . $row->col_state . ' ' . $row->col_zip ?></td>
                            <td class="editable" data-key="col_phone"><?php echo $row->col_phone ?></td>
                            <td>
                                <?php
                                foreach ($clinis as $cli) {
                                    if (empty($cli)) continue;
                                    echo '<li>' . $cli . '</li>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    }

                    ?>
                </tbody>
            </table>
            <div style="margin-top:10px;">
                <input type="hidden" name="act" value="move_duplicate" />
                <input type="hidden" name="first_name" value="<?php echo isset($_REQUEST['first_name']) ? $_REQUEST['first_name'] : '' ?>" />
                <input type="hidden" name="last_name" value="<?php echo isset($_REQUEST['last_name']) ? $_REQUEST['last_name'] : '' ?>" />


                <input type="submit" class="button-primary" value="Move" />
                <input type="button" class="button checkallsecond" value="Check all Second" />

                <span> <?php echo count($results) ?> items</span>
            </div>
        </form>
        <script>
            jQuery(function($) {
                function tr_save_coltor($obj) {
                    var $pr = $obj.parents('td.editable');
                    var $key = $pr.data('key');
                    $value = $obj.val();
                    $id = $pr.parent().data('id');
                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        type: 'POST',
                        data: {
                            tr_action: 'ci_save_coltor_ajax',
                            id: $id,
                            value: $value,
                            key: $key
                        },
                        dataType: 'json',
                        success: function(rs) {
                            if (rs.status == 'ok') {
                                $pr.html($value);
                                $pr.removeClass('editing');
                            } else {
                                alert('Error Please try again');
                            }
                            $pr.remove('.sloading');
                        },
                        error: function() {
                            alert('Error Please try again');
                            $pr.remove('.sloading');
                        }
                    })
                }
                $('.editable').click(function() {
                    if ($(this).hasClass('editing')) return;
                    $(this).addClass('editing');
                    var $vl = $(this).html();
                    $html = $('<input type="text" />');
                    $html.val($vl);

                    $(this).html($html);
                }).on('keypress', 'input', function(e) {
                    var code = e.keyCode || e.which;
                    if (code == 13) {
                        tr_save_coltor($(this));
                        return false;
                    }
                }).on('change', 'select', function() {
                    tr_save_coltor($(this));
                })

            })
        </script>

    <?php
    } else if ($act == 'autosearch') {

        $groupby = " group by col_last,col_first ";
        $sql = "select *,count(*) as c 
                        from ci_collector 
                        where col_first !='' or col_last!=''
                        {$groupby} 
                        order by c desc,col_first 
                        ";
        $results = $wpdb->get_results($sql);
    ?>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">Duplicate</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($results as $col) {
                    if ($col->c == 1) continue;
                ?>
                    <tr>
                        <td><?php echo $col->c ?></td>
                        <td><?php echo $col->col_last ?></td>
                        <td><?php echo $col->col_first ?></td>
                        <td><?php echo $col->col_phone ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'last_name' => $col->col_last, 'first_name' => $col->col_first)) ?>">List</a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    <?php
    }
    ?>

<?php
}else {

    if (isset($_POST['main_id'])  && $_POST['main_id'] > 0 && isset($_POST['second_id'])) {
        $m_id = intval($_POST['main_id']);
        if (!is_array($_POST['second_id'])) {
            $_POST['second_id'] = array($_POST['second_id']);
        }

        $cli_name = $wpdb->get_var("select cli_clinic from ci_clinic WHERE cID = {$m_id}");
        if ($cli_name) {
            foreach ($_POST['second_id'] as $s_id) {
                if ($m_id == $s_id) {
                    continue;
                }
                $s_id = intval($s_id);

                foreach (tr_pdd_get_batch_types() as $btype) {
                    $table = tr_pdd_get_table_by_btype($btype);
                    $wpdb->update($table, array('cli_ID' => $m_id, 'requesting_practice' => $cli_name), array('cli_ID' => $s_id));
                }


                $wpdb->update('ci_saved' . '_batches', array('cli_ID' => $m_id, 'requesting_practice' => $cli_name), array('cli_ID' => $s_id));
                $wpdb->update('ci_doc', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_clinic_doc', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_clinic_collector', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_clinic_manager', array('ma_cli_ID' => $m_id), array('ma_cli_ID' => $s_id));

                $wpdb->update('ci_custom_providers', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_igniting_qss', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_wsdl_key_clinics', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('wp_wp_eStore_customer_tbl', array('cli_ID' => $m_id), array('cli_ID' => $s_id));
                $wpdb->update('ci_customer_orders', array('cli_ID' => $m_id), array('cli_ID' => $s_id));

                $cli_import_ID = $wpdb->get_var("select cli_import_ID from ci_clinic where cID = {$s_id} ");
                if ($cli_import_ID > 0) {
                    $wpdb->insert('ci_fix_duplicate', array('ID' => $m_id, 'import_ID' => $cli_import_ID, 'ctype' => 'clinic', 'created' => date('Y-m-d H:i:s')));
                }
                $wpdb->delete('ci_clinic', array('cID' => $s_id));
            }

            Tr_Session_Class::add("moved Clinic from: " . implode(', ', $_POST['second_id']) . ' to #' . $m_id);
        } else {
            Tr_Session_Class::add_error('Not found Main ID: #' . $m_id);
        }
    }
    ?>

    <h2>Duplicate Clinic</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">
        <div>
            <label>Main ID</label>
            <input type="text" name="main_id" required />

            <label>Second ID (delete)</label>
            <input type="text" name="second_id" required />

            <input type="submit" class="button-primary" name="fix_clinic" value="Move" />
        </div>
    </form>


    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <label>Clinic: </label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <input type="submit" class="button-primary bsty6" value="Search" />
        <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autosearch', 's' => '')) ?>">Auto List</a>

    </form>

    <?php
    if (!empty($_REQUEST['s']) || !empty($_REQUEST['email'])) {
    ?>
        <form method="post" id="move_items_form">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width:30px">Main</th>
                        <th style="width:50px">Second</th>
                        <th>Name</th>
                        <th style="width:10%">Address</th>
                        <th style="width:8%">Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    $where = '';
                    if (!empty($_REQUEST['s'])) {
                        $where .= $wpdb->prepare(" and cli.cli_clinic like %s ", $_REQUEST['s'] . '%');
                    }

                    $sql = "select 
                    cli.*
                from ci_clinic cli
                left join ci_clinic_doc cd On cli.cID = cd.cli_ID
                left join ci_doc doc ON doc.doc_ID = cd.doc_ID
                where 1 {$where} 
                group by cli.cID
                order by cli.cli_clinic asc
                ";
                    $results = $wpdb->get_results($sql);

                    if (isset($_GET['debug'])) {
                        echo $sql;
                    }


                    foreach ($results as $row) {
                    ?>
                        <tr class="item_<?php echo $row->cID ?>" data-id="<?php echo $row->cID ?>">
                            <td><?php echo $row->cID ?></td>
                            <td><input type="radio" class="main_id" name="main_id" value="<?php echo $row->cID ?>" /> </td>
                            <td><input type="checkbox" class="second_id" name="second_id[]" value="<?php echo $row->cID ?>" /> </td>
                            <td><?php echo $row->cli_clinic ?></td>
                            <td><?php echo $row->cli_address . ' ' . $row->cli_city . ' ' . $row->cli_state . ' ' . $row->cli_zip ?></td>
                            <td class="editable" data-key="cli_phone"><?php echo $row->cli_phone ?></td>
                            <td></td>
                        </tr>
                    <?php
                    }

                    ?>
                </tbody>
            </table>
            <div style="margin-top:10px;">
                <input type="hidden" name="act" value="move_duplicate" />
                <input type="hidden" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />

                <input type="submit" class="button-primary" value="Move" />
                <input type="button" class="button checkallsecond" value="Check all Second" />

                <span> <?php echo count($results) ?> items</span>
            </div>
        </form>
        <script>
            jQuery(function($) {
                function tr_save_clinic($obj) {
                    var $pr = $obj.parents('td.editable');
                    var $key = $pr.data('key');
                    $value = $obj.val();
                    $id = $pr.parent().data('id');
                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        type: 'POST',
                        data: {
                            tr_action: 'ci_save_clinic_ajax',
                            id: $id,
                            value: $value,
                            key: $key
                        },
                        dataType: 'json',
                        success: function(rs) {
                            if (rs.status == 'ok') {
                                $pr.html($value);
                                $pr.removeClass('editing');
                            } else {
                                alert('Error Please try again');
                            }
                            $pr.remove('.sloading');
                        },
                        error: function() {
                            alert('Error Please try again');
                            $pr.remove('.sloading');
                        }
                    })
                }
                $('.editable').click(function() {
                    if ($(this).hasClass('editing')) return;
                    $(this).addClass('editing');
                    var $vl = $(this).html();
                    $html = $('<input type="text" />');
                    $html.val($vl);

                    $(this).html($html);
                }).on('keypress', 'input', function(e) {
                    var code = e.keyCode || e.which;
                    if (code == 13) {
                        tr_save_clinic($(this));
                        return false;
                    }
                }).on('change', 'select', function() {
                    tr_save_clinic($(this));
                })

            })
        </script>

    <?php
    } else if ($act == 'autosearch') {

        $groupby = " group by cli_clinic ";
        $sql = "select *,count(*) as c 
                        from ci_clinic 
                        where cli_clinic !='' 
                        {$groupby} 
                        order by c desc,cli_clinic asc
                        ";
        $results = $wpdb->get_results($sql);
    ?>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">Duplicate</th>
                    <th>Clinic</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($results as $row) {
                    if ($row->c == 1) continue;
                ?>
                    <tr>
                        <td><?php echo $row->c ?></td>
                        <td><?php echo $row->cli_clinic ?></td>
                        <td><?php echo $row->cli_address . ' ' . $row->cli_city . ' ' . $row->cli_state . ' ' . $row->cli_zip ?></td>
                        <td><?php echo $row->cli_phone ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 's' => $row->cli_clinic)) ?>">List</a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    <?php
    }
    ?>

<?php
}
?>

<style>
    table.blue_tbl td,
    table.blue_tbl th,
    table.blue_tbl input[type="text"] {
        font-size: 10px;
    }
</style>


<script>
    jQuery(function($) {
        $('#move_items_form').submit(function() {
            if ($('input.main_id:checked').length == 0) {
                alert('Please select Main');
                return false;
            }
            if ($('input.second_id:checked').length == 0) {
                alert('Please select Second');
                return false;
            }

            if (confirm("Do you want move these?") == false) {
                return false;
            }

        })
        $('input.main_id').click(function() {
            $('input.second_id').removeAttr('disabled');
            var $vl = $(this).val();
            $('.item_' + $vl + ' input.second_id').attr('disabled', 'disabled');
            $('.item_' + $vl + ' input.second_id').removeAttr('checked');
        })
        $('.checkallsecond').click(function() {
            $('input.second_id:not([disabled])').prop('checked', true);
        })
    })
</script>