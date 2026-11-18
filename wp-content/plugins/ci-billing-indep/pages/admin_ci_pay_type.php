<?php
global $wpdb,$current_role;
$user_id = get_current_user_id();

$where_clinic = " cli_status = 1 ";

if ( $current_role == 'office_manager' ) {

	$current_clinic = get_user_meta($user_id,'_use_current_clinic_id',true);
	if($current_clinic>0)
	{
		$where_clinic .= " and cID = {$current_clinic} ";
	}else{

		if(!tr_get_option('ci_pdf_data','off_see_all') || get_user_meta(get_current_user_id(),'_only_one_clinic',true) || $cid > 0) {
			$cid     = get_user_meta( $user_id, '_clinic_id', true );
			$cli_ids = $wpdb->get_col( "select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' " );
			$cli_ids = implode( ',', $cli_ids );


			$where_clinic .= " and cID in ( {$cli_ids} )";
		}
	}

}


if($act=='change_pay_type'){

	$return = array('status'=>'ok');
	$cid = $_REQUEST['cid'];
	$paytype = $_REQUEST['paytype'];

	$wpdb->update('ci_clinic',array('cli_pay_type'=>$paytype),array('cID'=>$cid));
	wp_send_json($return);
}
else if($act=='change_fedit')
{
	$return = array('status'=>'ok');
	$cid = $_REQUEST['cid'];
	$code = $_REQUEST['code'];
	$field = $_REQUEST['field'];

	$wpdb->update('ci_clinic',array($field=>$code),array('cID'=>$cid));
	wp_send_json($return);
}



$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("SELECT count(*) as count FROM `ci_clinic` cli where  {$where_clinic}"));
$offset = $pagination->offset();
$sql = "select * from ci_clinic where {$where_clinic} 
 ORDER BY `cli_clinic` asc limit {$offset},{$row_per_page}";
$clinics = $wpdb->get_results($sql);

if(isset($_GET['debug'])){
    var_dump($sql);
}
?>
<div class="wrap">
	<h2>Pay Type</h2>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<div class="wraptbl">

		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
			<tr>
				<th style="width:80px">#</th>
				<th>Clinic</th>
				<th>Facility Code</th>
				<th>Swab Code</th>
				<th style="width:32%"></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach($clinics as $i => $clinic)
			{
				?>
				<tr data-id="<?php echo $clinic->cID?>">
					<td><?php echo $i+1?></td>
					<td><?php echo $clinic->cli_clinic?></td>
					<td class="fedit" data-name="cli_facility_code"><input type="text" value="<?php echo $clinic->cli_facility_code?>" maxlength="20"/></td>
					<td class="fedit" data-name="cli_swab_code"><input type="text" value="<?php echo $clinic->cli_swab_code?>" maxlength="20"/></td>
					<td class="statuses">
						<?php
						foreach($settings['paytypes'] as $k=>$vl)
						{
							$checked = $k==$clinic->cli_pay_type? 'checked':'';
							echo '<label>
							<input type="radio" name="cli['.$clinic->cID.']" value="'.$k.'" '.$checked.'/> 
							'.$vl.'</label> ';
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>

	</div>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
</div>
<style>
	.statuses label{
		margin-right:10px;
	}
</style>

<script>
    jQuery(function ($) {
        $('body').on('click','.statuses input[type="radio"]',function () {
			var $pr = $(this).parents('tr');
			var $cid = $pr.data('id');
			var $paytype = $(this).val();
			$('.statuses',$pr).append('<span class="sloading"></span>');
            $.ajax({
                type:'post',
                data:{trajax:1,act:'change_pay_type',cid:$cid,paytype:$paytype},
                dataType:'json',
                success:function (json) {
					$('.statuses .sloading',$pr).remove();
                }
            })

        }).on('change','.fedit input[type="text"]',function () {
            var $pr = $(this).parents('tr');
            var $td = $(this).parent();
            var $cid = $pr.data('id');
            var $code = $(this).val();
            var $field = $td.data('name');
            if($('.sloading',$td).length>0)return;
            $td.append('<span class="sloading"></span>');
            $.ajax({
                type:'post',
                data:{trajax:1,act:'change_fedit',field:$field,cid:$cid,code:$code},
                dataType:'json',
                success:function (json) {
                    $('.sloading',$td).remove();
                }
            })

        }).on('keypress','.fedit input[type="text"]',function (e) {
            var code = e.keyCode || e.which;
            if(code==13)
            {
                $(this).change();
            }
        })
    })
</script>
