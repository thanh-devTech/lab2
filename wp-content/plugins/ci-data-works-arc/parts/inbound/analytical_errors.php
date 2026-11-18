<?php
global $wpdb;


if(isset($_REQUEST['trajax']))
{
	if($_REQUEST['trajax']=='add_form'){
	    $data = array();
		$data['edate'] = date_i18n('Y-m-d');
		?>
        <form method="post" id="add_form">
            <div class="frow">
                <label>Title</label>
                <input type="text" name="data[title]" value="<?php echo $data['title']?>" maxlength="200" required/>
            </div>
            <div class="frow">
                <label>Accession</label>
                <input type="text" name="data[assen]"value="<?php echo $data['assen']?>" maxlength="25"/>
            </div>
            <div class="frow">
                <label>Date</label>
                <input type="text" class="hasdate" name="data[edate]" value="<?php echo $data['edate']?>" required/>
            </div>
            <div class="frow">
                <label>Description of Error</label>
                <textarea name="data[desc]" style="height:50px" required><?php echo $data['desc']?></textarea>
            </div>

            <div class="frow center">
                <input type="submit" class="button-primary" value="Add"/>
                <input type="button" class="button" onclick="tb_remove()" value="Close"/>
                <input type="hidden" name="trajax" value="do_add_new"/>
                <input type="hidden" name="data[ID]" value="<?php echo $data['ID']?>"/>
            </div>
        </form>
        <script>
            jQuery(function ($) {
                $('.hasdate').each($.tr_setup_datef );
                $('form#add_form').submit(function () {
                    var $form = $(this);
                    $.ajax({
                        data:$form.serialize(),
                        type:'post',
                        dataType:'json',
                        success:function (json) {
                            if(json.success)
                            {
                                location.reload();
                            }else{
                                alert('Error. Please try again!');
                            }
                        },
                        error:function () {
                            alert('Error. Please try again!');
                        }
                    });
                    return false;
                })
            })
        </script>
		<?php
        exit;
	}
	else if($_REQUEST['trajax']=='do_add_new'){
        $return = array('success'=>1);

        $data = $_POST['data'];
        if($data['ID']>0)
        {
            $wpdb->update('ci_analytical_errors',$data,array('ID'=>$data['ID']));
        }else{
	        $data['created'] = date_i18n('Y-m-d H:i:s');
	        $data['created_by'] = get_current_user_id();

            $wpdb->insert('ci_analytical_errors',$data);
        }

        wp_send_json($return);
    }

}
$where      = " and atype = '' ";
$from_date  = isset($_REQUEST['from'])? $_REQUEST['from']: '';
$to_date    = isset($_REQUEST['to'])? $_REQUEST['to'] : date_i18n('Y-m-d');


if(empty($from_date))
{
	$from_date = date_i18n('Y-m-d', strtotime('-30days'));
}
if($to_date < $from_date)
{
	$to_date = date_i18n('Y-m-d');
}
if(!empty($from_date))
{
	$where.= " and ba.edate >= '{$from_date}' ";
}
if(!empty($to_date))
{
	$where.= " and ba.edate <= '{$to_date} 23:59:59' ";
}

$order      = isset($_REQUEST['order']) && $_REQUEST['order']=='asc'?'asc':'desc';
$order_by   = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])? 'ba.'.$_REQUEST['orderby']:'ba.edate';
$child_queries = array();
$total      = $wpdb->get_var("select count(*) from ci_analytical_errors ba where 1 {$where}");

if(isset($_REQUEST['trajax']) && $_REQUEST['trajax']=='Export'){

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=Analytical_Errors.csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	$f = fopen('php://output','w');
	$headers = array('Title','Date','Error Type','Description of Error', 'Accession','User');
	fputcsv($f,$headers);

	$sql = "select ba.*,
            u.user_email
            from ci_analytical_errors ba
        left join {$wpdb->users} u ON u.ID = ba.created_by
        where 1 {$where}
        order by {$order_by} {$order}";
	$results = $wpdb->get_results($sql);
	foreach($results as $row)
	{
	    if(empty($row->assen))
        {
            $row->assen = 'No Accession Assigned';
        }
		$rowdata = array(
			$row->title,
			$row->edate,
			'Analytical',
			$row->desc,
			$row->assen,
			$row->user_email
		);
		fputcsv($f,$rowdata);
	}
	fclose($f);

	exit;
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($total);
$offset = $pagination->offset();


$sql = "select ba.*,
            u.user_email
            from ci_analytical_errors ba
        left join {$wpdb->users} u ON u.ID = ba.created_by
        where 1 {$where}
        order by {$order_by} {$order}
        limit {$offset},{$row_per_page}";
$results = $wpdb->get_results($sql);

trpdd_log_query_error();


if(isset($_GET['debug'])){
	var_dump($sql);
	echo '<br><br>';
}
?>
<div class="wrap">

	<div class="tablenav">
		<form method="get" class="left">
			<input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
			<input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
			<input type="hidden" name="subtab" value="<?php echo $_GET['subtab']?>"/>
			<label>From</label>
			<input type="text" class="hasdate" name="from" value="<?php echo @$from_date?>"/>
			<label>To</label>
			<input type="text" class="hasdate" name="to" value="<?php echo @$to_date?>"/>
			<input type="submit" class="button-primary" value="Filter"/>
			<input type="submit" class="button" name="trajax" value="Export"/>
			<a class="button thickbox" title="Add New" href="<?php Tr_Base_Class_V6::link(array('trajax'=>'add_form'))?>">Add New</a>
		</form>

		<?php if($pagination)echo $pagination->pagination() ?>
	</div>

	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
			<tr>
				<?php tr_tbl_sort('title','Title')?>
				<?php tr_tbl_sort('edate','Date')?>
                <th>Error Type</th>
				<th>Description of Error</th>
				<?php tr_tbl_sort('assen','Accession')?>
				<th>User</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($results as $i=> $row){
			    if(empty($row->assen))
                {
                    $row->assen = 'No Accession Assigned';
                }
			    ?>
				<?php
				?>
				<tr>
					<td><?php echo $row->title?></td>
					<td><?php echo $row->edate?></td>
					<td>Analytical</td>
					<td><?php echo $row->desc?></td>
					<td><?php echo $row->assen?></td>
					<td><?php echo $row->user_email; ?></td>
				</tr>
			<?php }?>
			</tbody>
		</table>
	</div>

</div>