<?php
include(TRCPDD_PATH.'api/ci_fax_faxage.php');

$act        = isset($_GET['trajax'])? $_GET['trajax']:$act;
$offset     = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$options    = get_option('ci_faxage_st',array());

$faxage     = new CI_Fax_Faxage();

if(!empty($_POST['faxto'])){
	$number = $_POST['faxto'];
	$files = array(
		TRCPDD_PATH.'show.pdf'
	);

	$rs = $faxage->fax($number,$files);
	var_dump($rs);
	var_dump($faxage->error_message);
}

if($act=='test')
{

	?>
    <form method="post">
        <label>Fax to: </label>
        <input type="text" value="" name="faxto"/>
        <input type="submit" class="button" value="Send"/>
    </form>
	<?php

	exit;
}
else if($act=='log')
{
	$where = " type like '%fax%'  ";

	if(!empty($_REQUEST['s'])){
		$search = $_REQUEST['s'];
	    $mid = 0;
		foreach(tr_pdd_get_batch_types() as $btype)
        {
	        $batch_tbl = tr_pdd_get_table_by_btype($btype);
	        $mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$search}' ");
	        if($mid>0)
            {
                break;
            }
        }
        $where.= " and mid = {$mid} and btype = '{$btype}' ";
	}
	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) from ci_box_upload where {$where}"));
	$offset = $pagination->offset();
	$sql = "select * from ci_box_upload 
	                where {$where} 
	                order by id desc
                    limit {$offset},{$row_per_page}";
	$rows = $wpdb->get_results($sql);
	ob_start();
	?>
    <div>
        <input type="search" id="assen_search">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s'=>''))?>">Search</a>
    </div>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
        <tr>
            <th style="width:40px">#</th>
            <th style="width:120px">Accession</th>
            <th style="width:50px">Type</th>
            <th>Patient</th>
            <th>Clinic</th>
            <th>Doctor</th>
            <th>DOC</th>
            <th style="width:60px">Status</th>
            <th>Date/time</th>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach($rows as $row)
		{
		    $batch_tbl = tr_pdd_get_table_by_btype($row->btype);
		    $batch = $wpdb->get_row("select assen, 
		            patient_name,
		            requesting_practice,
		            requesting_physician,
		            doc
		     from {$batch_tbl} where mid = {$row->mid}");

        ?>
            <tr>
                <td><?php echo $row->mid?></td>
                <td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->btype.'_'.$row->mid?>&short=1"><?php echo $batch->assen?></a></td>
                <td><?php echo $row->btype?></td>
                <td><?php echo $batch->patient_name?></td>
                <td><?php echo $batch->requesting_practice?></td>
                <td><?php echo $batch->requesting_physician?></td>
                <td><?php echo $batch->doc?></td>
                <td title="<?php echo $row->status?>"><?php echo $row->status==1? 'sent':''?></td>
                <td title="<?php echo $row->created_by?>"><?php echo $row->created?></td>
            </tr>
        <?php
		}
		?>
        </tbody>
    </table>

	<?php
	$content = ob_get_clean();
	if(isset($_GET['trajax'])){
		$content = str_replace('class="','class="show_popup ',$content);
		$content = str_replace('class=\'','class=\'show_popup ',$content);
	}
	echo $content;
	exit;
}
else if($act=='docron')
{
	include(TRCPDD_PATH.'inc/crons/check_upload_apis.php');
	exit;
}


if(isset($_POST['options'])){
	$old = get_option('ci_faxage_st',array());
	$new = $_POST['options'];

	if($new['password']=='here'){
		$new['password'] = $old['password'];
	}

    $old_options = get_option('ci_faxage_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $new
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_ci_faxage_st', $bkdata);

	update_option('ci_faxage_st',$new);

	Tr_Session_Class::add('Saved Settings Successfully');
}

$options = get_option('ci_faxage_st',array());

?>
<div class="wrap">

    <h2>Faxage Connect</h2>
	<?php Tr_Session_Class::show()?>

    <form method="post">

        <div class="row">
            <label>Username</label>
            <input type="text" name="options[username]" value="<?php echo $options['username']?>"/>
        </div>
        <div class="row">
            <label>Company</label>
            <input type="text" name="options[company]" value="<?php echo $options['company']?>"/>
        </div>
        <div class="row">
            <label>Password</label>
            <input type="password" name="options[password]" value="here"/>
        </div>
        <div class="row">
            <label>Recipname</label>
            <input type="text" name="options[recipname]" value="<?php echo $options['recipname']?>"/>
        </div>

        <div class="row">
            <input type="submit" value="Save" class="button-primary"/>
            <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'test'))?>" class="thickbox button">Test</a>
            <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'log','width'=>'1200'))?>" class="thickbox button">Log</a>
            <a href="<?php Tr_Base_Class_V6::link(array('trajax'=>'docron'))?>" class="thickbox button">Run Cron</a>

        </div>
    </form>
</div>

<script>
    jQuery(function ($) {
        $('body').on('click','a.show_popup',function () {
            if($(this).hasClass('seach_assen'))return;
            var $url = $(this).attr('href');
            tb_remove();
            setTimeout(function () {
                tb_show('',$url);
            },500);
            return false;
        }).on('click','a.seach_assen',function () {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function () {
                tb_show('',$url+'&s='+$s);
            },300);
            return false;
        })
    })
</script>
