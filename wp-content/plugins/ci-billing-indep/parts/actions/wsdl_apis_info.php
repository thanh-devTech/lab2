<?php
global $wsdl_apis_info;
$wsdl_apis_info = get_option('tr_wsdl_apis_info_sts', array());
if (isset($_POST['trajax']) && isset($_POST['datakey'])) {
	$return = array('status' => 'ok');
	$wsdl_apis_info[$_POST['datakey']] = $_POST['vl'];
	update_option('tr_wsdl_apis_info_sts', $wsdl_apis_info);
	wp_send_json($return);
	exit;
}


if (!isset($wsdl_apis_info['billing_webhook'])) $wsdl_apis_info['billing_webhook'] = '';
if (!isset($wsdl_apis_info['billing_webhook_header'])) $wsdl_apis_info['billing_webhook_header'] = '';

if(!empty($wsdl_apis_info['billing_webhook']))
{
	
	$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
	$time_mid_night = strtotime(date('Y-m-d', time() + $offset)) + 86400 -  $offset - 60;
  
  
	wp_clear_scheduled_hook('ci_cron_send_webhook');
	wp_schedule_event($time_mid_night - 300, 'daily', 'ci_cron_send_webhook');
}

?>
<p>
<h4>Billing API:</h4>
</p>
<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/restapi/info?key=viewinfo" onClick="this.select();" />

<p>
<h4>Billing Webhook</h4>
<input type="text" style="width:100%;border:0" class="wsdl_api_info_field" data-key="billing_webhook" value="<?php echo $wsdl_apis_info['billing_webhook'] ?>" />
<div>Header:</div>
<textarea style="width:100%;border:0" class="wsdl_api_info_field" data-key="billing_webhook_header"><?php echo $wsdl_apis_info['billing_webhook_header'] ?></textarea>
</p>
<br>
<script>
	jQuery(function($) {
		$('.wsdl_api_info_field').on('change', function() {
			var $key = $(this).data('key');
			var $vl = $(this).val();
			$.ajax({
				data: {
					trajax: 1,
					datakey: $key,
					vl: $vl
				},
				dataType: 'json',
				type: 'post',
				success: function(rs) {

				}
			});
		})
	})
</script>