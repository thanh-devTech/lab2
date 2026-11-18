<?php
global $wpdb;

$act = isset($act) ? $act : '';
if (!isset($tes_type)) {
    $tes_type  = !empty($_REQUEST['type']) ? $_REQUEST['type'] : 'tox';
}
$today = date_i18n('Y-m-d');



if ($act == 'pdelete' && $can_delete) {
    $ID = $_REQUEST['idd'];
    $wpdb->delete('ci_study_tests', array('ID' => $ID));
    Tr_Session_Class::add('Deleted test #' . $ID);
}
else if($act=='add' || $act=='edit'){
    include(trpdd_get_file_path('panel','study_edit'));
    return;
}
else if($act=='import'){
    if(isset($_FILES['csvfile']['tmp_name'])){
        $data = trpdd_get_data($_FILES['csvfile']['tmp_name'],'csv',array('abbreviation'),true,false,true);
        $exit_cols = $wpdb->get_col("explain ci_study_tests");
        /*
        foreach($data['header'] as $k)
        {
            if(in_array($k, array('abbreviation','description'))){
                continue;
            }

            if (!in_array($k, $exit_cols)) {
				$datatype = 'varchar(100)';
				if (strlen($vl) > 100) {
					$datatype = 'varchar(150)';
				}

				if (stripos($k, 'num_') === 0) {
					$datatype = 'int(10)';
				} else if (in_array($k, array('conditions', 'time_points', 'sites'))) {
					$datatype = 'text';
				} else if (stripos($k, 'time') !== false) {
					$datatype = 'datetime';
				} else if (stripos($k, 'date') !== false) {
					$datatype = 'date';
				}
				$wpdb->query("alter table ci_study_tests add column `{$k}` {$datatype};");
			}
        }*/

        foreach($data['data'] as $i => $row)
        {
            $row['abrev'] = $row['abbreviation'];
            $row['desc'] = $row['description'];
            unset($row['']);
            unset($row['_2']);
            unset($row['abbreviation']);
            unset($row['description']);


            $exitid = $wpdb->get_var("select ID from ci_study_tets where abrev = '{$row['abrev']}' ");
            if($exitid)
            {
                $wpdb->update('ci_study_tests',$row,array('ID'=>$exitid));
            }else{
                $wpdb->insert('ci_study_tests',$row);
            }
        
        }
        Tr_Session_Class::add('Imported success!');

    }else{
    ?>
    <form method="post" enctype="multipart/form-data" style="margin:40px;">
        <label>File:</label>
        <input type="file" name="csvfile" accept="*/csv"/>
        <input type="submit" value="Import" class="button"/>
        <input type="hidden" name="act" value="import"/>
    </form>
    <?php
    exit;
    }
}

$where          = " 1 ";
$orderby        = 'te.iorder asc';

$sql = "select te.*
        from ci_study_tests te
        where {$where} 
        order by {$orderby}";
$tes = $wpdb->get_results($sql);
$max_length = 0;

?>
<div class="wrap">
    <h2 style="line-height: 40px;">
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2 bsty4">Add New Test</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'questions', 'type' => $tes_type)) ?>" class="add-new-h2">Questions</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'sitenames', 'type' => $tes_type)) ?>" class="add-new-h2">Site Names</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'irbs', 'type' => $tes_type)) ?>" class="add-new-h2">IRBs</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'inclusion', 'type' => $tes_type)) ?>" class="add-new-h2">Inclusion</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'exclusion', 'type' => $tes_type)) ?>" class="add-new-h2">Exclusion</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'batch_status', 'id' => '')) ?>" class="add-new-h2">Batch-Status</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'req_settings', 'id' => '')) ?>" class="add-new-h2">Req Settings</a>
        <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>1,'act' => 'import', 'type' => $tes_type)) ?>" class="add-new-h2 thickbox">Import</a>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead class="sticky">
                <tr>
                    <th style="width:30px">#</th>
                    <th style="width:180px">Abreviation</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($tes as $i => $te) {
                ?>
                    <tr data-id="<?php echo $te->ID ?>">
                        <td><?php echo $te->ID ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $te->ID)) ?>"><?php echo $te->abrev ?></a>
                            <div class="row-actions">
                                
                                <?php if ($can_delete) { ?>
                                    <a class="pdelete" href="#" title="there are results with this test" data-id="<?php echo  $te->ID ?>">Delete</a>
                                <?php } ?>
                            </div>
                        </td>
                        <td><?php echo $te->desc ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="total">
        Total: <?php echo count($tes); ?>
    </div>
</div>


<style>
    .notfortoxreq {
        background: #ccc !important;
    }

    td.editable:hover {
        background: yellow;
    }
</style>

<script>
    jQuery(function($) {
        $('td.editable').click(function() {
            var $this = $(this);
            if (!$this.data('editing')) {
                $this.data('value', $this.html());
                $this.html('<input type="text" value="' + $this.html() + '"/>');
                $this.data('editing', true);
                $('input', $this).focus();
            }
        })
        $('body').on('keyup', 'td.editable input', function(e) {
            var code = e.keyCode || e.which;
            var $this = $(this);
            var $pr = $(this).parent();
            var $tr = $pr.parent();

            if (code == 27) {
                $pr.data('editing', false);
                $pr.html($pr.data('value'));
            } else if (code == 13) {
                var $vl = $this.val();
                var $col = $pr.data('k');
                var $id = $tr.data('id');

                $pr.append('<div class="sloading"></div>');
                $.ajax({
                    data: {
                        save_test: 1,
                        trajax: 1,
                        col: $col,
                        id: $id,
                        val: $vl
                    },
                    type: 'post',
                    success: function(rs) {
                        var $html = $(rs);
                        if (rs.indexOf("updated success") > -1) {
                            $pr.data('editing', false);
                            $pr.html($vl);
                        } else {
                            alert($html.find('.msg').html());
                        }
                        $('.sloading', $pr).remove();
                    }
                });
                return false;
            }
        })
        $('a.delete').click(function() {
            if (confirm('Do you want delete?') == false) return false;
        })
        $('#cat_filter').change(function() {
            $(this).parents('form').submit();
        })
    })
</script>