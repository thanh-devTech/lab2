<?php
global $wpdb,$current_role;
$user_id = get_current_user_id();
$settings = tr_billing_get_settings();

$where_doctor = " doc_first != '' ";
if ( $current_role == 'office_manager' ) {

	$current_clinic = get_user_meta($user_id,'_use_current_clinic_id',true);
	$where_cli = '1=2';
	if($current_clinic>0)
	{
		$where_cli = " cli_ID = {$current_clinic} ";
	}else{

		if(!tr_get_option('ci_pdf_data','off_see_all') || get_user_meta(get_current_user_id(),'_only_one_clinic',true) || $cid > 0) {
			$cid     = get_user_meta( $user_id, '_clinic_id', true );
			$cli_ids = $wpdb->get_col( "select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' " );
			$cli_ids = implode( ',', $cli_ids );


			$where_cli = " cli_ID in ( {$cli_ids} )";
		}
	}
	$where_doctor .= " and doc_ID in (select doc_ID from ci_clinic_doc where {$where_cli}) ";

}

if($act=='change_fedit')
{
	$return = array('status'=>'ok');
	$doc_ID = $_REQUEST['doc_ID'];
	$code = $_REQUEST['code'];
	$field = $_REQUEST['field'];

	$wpdb->update('ci_doc',array($field=>$code),array('doc_ID'=>$doc_ID));
	wp_send_json($return);
}



$row_per_page = 200;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("SELECT count(*) as count FROM `ci_doc` doc where  {$where_doctor}"));
$offset = $pagination->offset();
$sql = "select * from ci_doc where {$where_doctor} 
 ORDER BY `doc_first` asc limit {$offset},{$row_per_page}";
$doctors = $wpdb->get_results($sql);

if(isset($_GET['debug'])){
    var_dump($sql);
    var_dump($wpdb->last_error);
}
?>
<div class="wrap">
	<h2>Doctor</h2>
	<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
	<div class="wraptbl">

		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
			<tr>
				<th style="width:80px">#</th>
				<th>First Name</th>
				<th>Last Name</th>
				<th>NPI</th>
                <?php if($settings['enable_doctorkid']){?>
                <th>Key ID</th>
                <?php }?>
				<th>Provider Code</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach($doctors as $i => $doctor)
			{
				?>
				<tr data-id="<?php echo $doctor->doc_ID?>">
					<td><?php echo $i+1?></td>
					<td><?php echo $doctor->doc_first?></td>
					<td><?php echo $doctor->doc_last?></td>
					<td class="fedit" data-name="doc_npi">
                        <input type="text" value="<?php echo $doctor->doc_npi?>" maxlength="30"/>
                    </td>
					<?php if($settings['enable_doctorkid']){?>
                        <td class="fedit" data-name="doc_key_id">
                            <input type="text" value="<?php echo isset($doctor->doc_key_id)? $doctor->doc_key_id : ''?>" maxlength="50"/>
                        </td>
                    <?php }?>
					<td class="fedit" data-name="doc_provider_code">
                        <input type="text" value="<?php echo $doctor->doc_provider_code?>" maxlength="50"/>
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
        $('body').on('change','.fedit input[type="text"]',function () {
            var $pr = $(this).parents('tr');
            var $td = $(this).parent();
            var $doc_ID = $pr.data('id');
            var $code = $(this).val();
            var $field = $td.data('name');
            if($('.sloading',$td).length>0)return;
            $td.append('<span class="sloading"></span>');
            $.ajax({
                type:'post',
                data:{trajax:1,act:'change_fedit',field:$field,doc_ID:$doc_ID,code:$code},
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
