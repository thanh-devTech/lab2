<?php
global $wpdb,$current_role;
global $access_btypes;

include_once(TRCPDD_PATH.'pages/formdata.php');

$tabs = array(
	'' => 'Consumables',
	'vendor' => 'Vendors',
);

if(tr_current_role_in(array('AdminCIDW','administrator','administrator_p','clms')) )
{
	$tabs['admin'] = 'Admin';
}

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$act = isset($_REQUEST['act'])? $_REQUEST['act'] : '';
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);


if($act=='edit_col_consum'){
	$id = $_REQUEST['id'];
	$col = $_REQUEST['col'];
	$vl = $_REQUEST['val'];
	$return = array('status'=>'ok');

	$update = array(
		$col => $vl,
		'co_sent_notify' => 0
	);

	$rs = $wpdb->update('ci_consumable_inv',$update,array('id'=>$id));

	if($rs===false)
	{
		$return['status'] ='error';
	}
	$return['id'] = $id;
	$return['col'] = $col;
	$return['val'] = $vl;
	$return['error'] = $wpdb->last_error;
	wp_schedule_single_event(time(),'ci_check_pdf_card_single_cron');
	wp_send_json($return);
	exit;
}
else if($act=='submit_consumable'){

	$consumed = $_POST['consumed'];
	$row = $wpdb->get_row("select * from ci_consumable_inv where id = {$id} ");
	$newsize = $consumed < $row->co_qty? $row->co_qty - $consumed : 0;

	$wpdb->update('ci_consumable_inv',array('co_qty'=>$newsize), array('id'=>$id));
	$return = array(
		'status' => 'ok',
		'newsize' => $newsize
	);
	wp_schedule_single_event(time(),'ci_check_pdf_card_single_cron');
	wp_send_json($return);
	exit;
}
?>
    <h2 class="nav-tab-wrapper">
		<?php

		foreach ($tabs as $k => $txt) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 's' => ''), false);
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
    </h2>
<?php

if($tab=='admin')
{
	if(isset($_POST['config'])){

		$config = stripslashes_deep($_POST['config']);
		$config['emails'] = trim($config['emails']);
		update_option('consumable_inv_settings',$config);

		Tr_Session_Class::add("Updated Settings Successfully!");
	}
	$config = get_option("consumable_inv_settings",array());
	?>
    <div class="wrap">
        <h2>Settings</h2>
		<?php Tr_Session_Class::show()?>
        <form method="post">
            <div class="frow blable">
                <label>Notify Emails (split by lines)</label><br>
                <textarea name="config[emails]" style="width:90%;height:300px" required><?php echo isset($config['emails'])? $config['emails'] : ''?></textarea>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Save"/>
            </div>
        </form>
    </div>
	<?php
}
else if($tab=='vendor')
{
	if ($act == 'add' || $act == 'edit') {

		$data = array();
		if(isset($_POST['data'])){
			$data = tr_pdd_stripslashes_deep($_POST['data']);
			if($data['id']>0)
			{
				$rs = $wpdb->update('ci_vendors',$data,array('id'=>$data['id']));
			}else{
				$rs = $wpdb->insert('ci_vendors',$data);
			}

			if($rs===false)
			{
				Tr_Session_Class::add_error('Error: '.$wpdb->last_error);
			}else{
				Tr_Session_Class::add("Saved Vendor successfully!");
				$data = array();
			}
		}

		if ($act == 'edit') {
			$data = $wpdb->get_row("select * from ci_vendors where id = {$id} ", ARRAY_A);
		}
		?>
        <div class="wrap">
            <h2>Edit Vendor <a class="add-new-h2"
                               href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>">List
                    all</a></h2>
			<?php Tr_Session_Class::show()?>
            <form method="post">
                <div class="frow">
                    <label>Name</label>
                    <input type="text" name="data[ve_name]" value="<?php echo $data['ve_name'] ?>" maxlength="150" required/>
                </div>
                <div class="frow">
                    <label>Sales Person</label>
                    <input type="text" name="data[ve_salesperson]" value="<?php echo $data['ve_salesperson'] ?>" maxlength="150"/>
                </div>
                <div class="frow">
                    <label>Site</label>
                    <input type="text" name="data[ve_site]" value="<?php echo $data['ve_site'] ?>" maxlength="150"/>
                </div>
                <div class="frow">
                    <label>Email</label>
                    <input type="text" name="data[ve_email]" value="<?php echo $data['ve_email'] ?>"/>
                </div>
                <div class="frow">
                    <label>Phone</label>
                    <input type="text" name="data[ve_phone]" value="<?php echo $data['ve_phone'] ?>"/>
                </div>

                <div class="frow">
                    <label>Notes</label>
                    <textarea name="data[ve_notes]"><?php echo $data['ve_notes']?></textarea>
                </div>

                <div class="frow">
                    <input type="submit" class="button-primary" value="Save"/>
                    <input type="hidden" name="data[id]" value="<?php echo $data['id'] ?>"/>
                </div>
            </form>
        </div>
		<?php
	} else {

		if(isset($_POST['act']) && $_POST['act']=='pdelete')
		{
			$id = $_POST['idd'];
			$wpdb->delete('ci_vendors',array('id'=>$id));
			Tr_Session_Class::add("Deleted Vendor Successfully!");
		}
		$where = '';

		$row_per_page = 100;
		$pagination = new Tr_Pagination_class($row_per_page);
		$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_vendors {$where}"));
		$offset = $pagination->offset();

		$sql = "select * from ci_vendors
        {$where} 
        order by id desc 
        limit {$offset},{$row_per_page}";

		if (isset($_REQUEST['debug'])) {
			echo $sql;
		}
		$results = $wpdb->get_results($sql);
		?>

        <div class="wrap">
            <h2>Vendors <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'add')) ?>">Add
                    New</a></h2>
			<?php Tr_Session_Class::show()?>

            <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>

            <div class="wraptbl">
                <table class="wp-list-table widefat striped posts blue_tbl">
                    <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width: 23%;">Name</th>
                        <th style="width: 23%;">Sales Person</th>
                        <th style="width:10%">Site</th>
                        <th style="width:10%">Email</th>
                        <th style="width:15%">Phone</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php
					if (count($results) > 0) {
						foreach ($results as $row) {
							?>
                            <tr>
                                <td><?php echo $row->id ?></td>
                                <td><?php echo $row->ve_name ?></td>
                                <td><?php echo $row->ve_salesperson ?></td>
                                <td><?php echo $row->ve_site ?></td>
                                <td><?php echo $row->ve_email ?></td>
                                <td><?php echo $row->ve_phone ?></td>
                                <td>
                                    <a href="<?php Tr_Base_Class_V6::link(array('act'=>'edit','id'=>$row->id))?>">Edit</a> -
                                    <a class="pdelete" href="#" data-id="<?php echo $row->id?>">Delete</a>
                                </td>
                            </tr>
							<?php
						}
					} else {
						?>
                        <tr>
                            <td colspan="8">No Results Found.</td>
                        </tr>
						<?php
					}


					?>
                    </tbody>
                </table>
            </div>

            <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
        </div>
		<?php
	}
}
else {
	if ($act == 'add' || $act == 'edit') {

		$data = array();
		if(isset($_POST['data'])){
			$data = tr_pdd_stripslashes_deep($_POST['data']);
			$data['co_sent_notify'] = 0;
			if($data['id']>0)
			{
				$rs = $wpdb->update('ci_consumable_inv',$data,array('id'=>$data['id']));
			}else{
				$rs = $wpdb->insert('ci_consumable_inv',$data);
			}

			if($rs===false)
			{
				Tr_Session_Class::add_error('Error: '.$wpdb->last_error);
			}else{
				Tr_Session_Class::add("Saved Consumable Inc successfully!");
				$data = array();
			}
		}
		if ($act == 'edit') {
			$data = $wpdb->get_row("select * from ci_consumable_inv where id = {$id} ", ARRAY_A);
		}
		$vendors = $wpdb->get_results("select * from ci_vendors order by ve_salesperson asc");
		?>
        <div class="wrap">
            <h2>Edit Consumable Inventory <a class="add-new-h2"
                                             href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>">List
                    all</a></h2>
			<?php Tr_Session_Class::show()?>
            <form method="post">
                <div class="frow">
                    <label>Name</label>
                    <input type="text" name="data[co_name]" value="<?php echo $data['co_name'] ?>" maxlength="150" required/>
                </div>

                <div class="frow">
                    <label>Size</label>
                    <input type="number" step="1" name="data[co_size]" value="<?php echo $data['co_size'] ?>" maxlength="11" required/>
                </div>
                <div class="frow">
                    <label>Order Date</label>
                    <input type="text" class="hasdate" name="data[co_order_date]"
                           value="<?php echo $data['co_order_date'] ?>" required/>
                </div>

                <div class="frow">
                    <label>Duration</label>
                    <input type="number" step="1" name="data[co_duration]" value="<?php echo $data['co_duration'] ?>" maxlength="11" required/>
                    <small>Days need to reorder</small>
                </div>

                <div class="frow">
                    <label>Qty Left</label>
                    <input type="number" step="1" name="data[co_qty]" value="<?php echo $data['co_qty'] ?>" maxlength="11" required/>
                </div>

                <div class="frow">
                    <label>ReOrder at</label>
                    <input type="number" step="1" name="data[co_reorder_at]" value="<?php echo $data['co_reorder_at'] ?>" maxlength="11" required/>
                </div>

                <div class="frow">
                    <label>ReOrder Qty</label>
                    <input type="number" step="1" name="data[co_reorder_qty]" value="<?php echo $data['co_reorder_qty'] ?>" maxlength="11" required/>
                </div>

                <div class="frow" required="">
                    <label>Vendor</label><br>
                    <select name="data[co_vendor_id]" required class="njs_select2">
                        <option value="">Select</option>
						<?php
						foreach ($vendors as $vendor)
						{
							$selected = $vendor->id == $data['co_vendor_id']? 'selected':'';
							echo '<option value="'.$vendor->id.'" '.$selected.'>'.$vendor->ve_name.' ('.$vendor->ve_site.')</option>';
						}
						?>
                    </select>
                </div>
                <div class="frow" required="">
                    <label>Testing Type</label><br>
                    <select name="data[co_btype]" required class="njs_select2">
                        <option value="">Select</option>
						<?php
						foreach ($access_btypes as $bt)
						{
							$selected = $bt == $data['co_btype']? 'selected':'';
							echo '<option value="'.$bt.'" '.$selected.'>'.tr_pdd_get_typename($bt).'</option>';
						}
						?>
                    </select>
                </div>
                <div class="frow">
                    <label>Notes</label>
                    <textarea name="data[co_notes]"><?php echo $data['co_notes']?></textarea>
                </div>
                <div class="frow">
                    <label>Notify Email</label>
                    <input type="email" name="data[co_email]" value="<?php echo $data['co_email'] ?>" maxlength="200" />
                </div>
                <div class="frow">
                    <input type="submit" class="button-primary" value="Save"/>
                    <input type="hidden" name="data[id]" value="<?php echo $data['id'] ?>"/>
                </div>
            </form>
        </div>
		<?php
	} else {


		if(isset($_POST['act']) && $_POST['act']=='pdelete')
		{
			$id = $_POST['idd'];
			$wpdb->delete('ci_consumable_inv',array('id'=>$id));
			Tr_Session_Class::add("Deleted Consumable Inv Successfully!");
		}

		$btype_selected = isset($_GET['btype'])? $_GET['btype'] : '';
		$where          = '';

		if(!empty($btype_selected))
		{
			$where.= " and co_btype = '{$btype_selected}' ";
		}else{
			$btypes = implode("','", $access_btypes);
			$where.= " and co_btype in( '{$btypes}' )";
        }

		$row_per_page = 100;
		$pagination = new Tr_Pagination_class($row_per_page);
		$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_consumable_inv where 1 {$where}"));
		$offset = $pagination->offset();

		$sql = "select inv.*,
                    ven.ve_name,
                    ven.ve_site
                from ci_consumable_inv inv
                left join ci_vendors ven ON ven.id = inv.co_vendor_id
                where 1 {$where} 
                order by inv.id desc 
                limit {$offset},{$row_per_page}";

		if (isset($_REQUEST['debug'])) {
			echo $sql;
		}
		$results = $wpdb->get_results($sql);
		?>

        <div class="wrap">
            <h2>Consumables Inventory <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'add')) ?>">Add
                    New</a></h2>
			<?php Tr_Session_Class::show()?>

            <div class="tablenav">
                <form method="get" class="left">
                    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
                    <input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab'] : ''?>"/>
                    <label>Testing Type</label>
                    <select name="btype" class="njs_select2">
                        <option value="">All Types</option>
						<?php
						foreach ($access_btypes as $bt)
						{
							$selected = $bt == $btype_selected? 'selected':'';
							echo '<option value="'.$bt.'" '.$selected.'>'.tr_pdd_get_typename($bt).'</option>';
						}
						?>
                    </select>
                    <input type="submit" class="button bsty6" value="Filter"/>
                </form>
				<?php if($pagination)echo $pagination->pagination() ?>
            </div>

            <div class="wraptbl">
                <table class="wp-list-table widefat striped posts blue_tbl">
                    <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width: 25%;">Name</th>
                        <th style="width:15%">Vendor</th>
                        <th style="width:10%">Testing Type</th>
                        <th style="width:5%">Size</th>
                        <th style="width:10%">Order Date</th>
                        <th style="width:5%">Duration</th>
                        <th style="width:8%">Qty Left</th>
                        <th style="width:8%">ReOrder At</th>
                        <th style="width:9%">ReOrder Qty</th>
                        <th>Consumed</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php
					if (count($results) > 0) {
						foreach ($results as $row) {
							$class= '';
							if($row->co_sent_notify==1)
							{
								$class= 'sent_notify';
							}
							else if($row->co_sent_notify==-1)
							{
								$class= 'error_notify';
							}
							?>
                            <tr class="drow <?php echo $class?>" data-id="<?php echo $row->id?>">
                                <td class="rid" title="<?php if($row->co_sent_notify==1)echo 'Sent notify'?>"><?php echo $row->id ?></td>
                                <td>
                                    <span class="rname"><?php echo $row->co_name ?></span>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php Tr_Base_Class_V6::link(array('act'=>'edit','id'=>$row->id))?>">Edit</a></span> |
                                        <span class="delete"><a class="pdelete " href="#" data-id="<?php echo $row->id?>">Delete</a></span>
                                    </div>
                                </td>
                                <td><?php echo $row->ve_name ?></td>
                                <td><?php echo tr_pdd_get_typename($row->co_btype) ?></td>
                                <td><?php echo $row->co_size ?></td>
                                <td><?php echo $row->co_order_date ?></td>
                                <td><?php echo $row->co_duration ?></td>
                                <td class="rqty editable" title="click to edit" data-k="co_qty"><?php echo $row->co_qty ?></td>
                                <td><?php echo $row->co_reorder_at ?></td>
                                <td><?php echo $row->co_reorder_qty ?></td>
                                <td>
                                    <a class="consumed button" href="#">Consumed</a>
                                </td>
                            </tr>
							<?php
						}
					} else {
						?>
                        <tr>
                            <td colspan="8">No Results Found.</td>
                        </tr>
						<?php
					}


					?>
                    </tbody>
                </table>
            </div>
            <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
        </div>


        <div id="consumed_div" style="display: none;">
            <form method="post" id="form_consumed">
                <div class="frow">
                    <label>Qty</label>
                    <input type="number" step="1" name="consumed" value="1" required/>
                </div>
                <div class="frow center">
                    <input type="submit" class="button-primary" value="Submit"/>
                    <input type="hidden" name="id" value=""/>
                </div>
            </form>
        </div>

        <style>
            .sent_notify .rid{
                background:yellow;
            }
            .error_notify .rid{
                background:red;
            }
            td.rqty{
                background:lightgreen;
            }
            td.editable input{
                width:98%;
            }
        </style>

        <script>
            var $tr = null;
            jQuery(function ($) {
                $('a.consumed').click(function () {
                    $tr = $(this).parents('tr.drow');
                    var $rid = $('td.rid',$tr).html();
                    var $rname = $('.rname',$tr).html();
                    var $qty = $('td.rqty',$tr).html();
                    if($('td.rqty input',$tr).length>0)
                    {
                        $qty = $('td.rqty input',$tr).val();
                    }
                    tb_show($rname,'#TB_inline?inlineId=consumed_div&width=300&height=150');
                    $('#form_consumed input[name="id"]').val($rid);
                    $('#form_consumed input[name="consumed"]').attr('max',$qty).val('').focus();
                    return false;
                })
                $('body').on('submit','form#form_consumed',function () {
                    var $btn = $(this).find('input[type="submit"]');
                    $btn.val('Sending...');
                    $.ajax({
                        url:'?page=ci_consumable_inv&act=submit_consumable&trajax=true',
                        data:$('#form_consumed').serialize(),
                        type:'post',
                        dataType:'json',
                        success:function (rs) {
                            $btn.val('Save');
                            if(rs.status=='ok'){
                                $('td.rqty',$tr).html(rs.newsize);
                                tb_remove();
                            }else{
                                alert('Error. please try again');
                            }
                        }
                    })
                    return false;
                })
                $('td.editable').click(function () {
                    var $this = $(this);
                    if(!$this.data('editing')){
                        $this.data('value',$this.html());
                        $this.html('<input type="text" value="'+$this.html()+'"/>');
                        $this.data('editing',true);
                        $('input',$this).focus();
                    }
                })
                $('body').on('keyup','td.editable input',function (e) {
                    var code = e.keyCode || e.which;
                    var $this = $(this);
                    var $pr = $(this).parent();
                    var $tr = $pr.parent();

                    if(code==27)
                    {
                        $pr.data('editing',false);
                        $pr.html($pr.data('value'));
                    }
                    else if(code==13)
                    {
                        var $vl = $this.val();
                        var $col = $pr.data('k');
                        var $id = $tr.data('id');

                        if($('.sloading',$pr).length>0)
                        {
                            return false;
                        }

                        $pr.append('<div class="sloading"></div>');
                        $.ajax({
                            data:{act:'edit_col_consum',trajax:1,col:$col,id:$id,val:$vl},
                            type:'post',
                            dataType:'json',
                            success:function (rs) {

                                if(rs.status =='ok'){
                                    $pr.data('editing',false);
                                    $pr.html($vl);
                                }else{
                                    alert("Error");
                                }
                                $('.sloading',$pr).remove();
                            }
                        });
                        return false;
                    }
                })
            })
        </script>
		<?php
	}
}