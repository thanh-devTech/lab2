<?php

if (isset($_POST['option'])) {
	$option  = sanitize_text_field($_POST['option']);
	$current = get_option($option, 'yes');
	$new     = ($current === 'yes') ? 'no' : 'yes';

	$updated = update_option($option, $new);

	if ($updated) {
		wp_send_json_success([
			'status' => 'ok',
			'option' => $option,
			'value'  => $new
		]);
	} else {
		wp_send_json_error([
			'status'  => 'fail',
			'option'  => $option,
			'current' => $current,
			'expected' => $new
		]);
	}
	exit;
}

$available_gateways = WC()->payment_gateways->payment_gateways();
?>
<div class="wrap">
	<table class="form-pay-woo">
		<tbody>
			<tr>
				<th>Payment Method</th>
				<th>App</th>
				<th>Web</th>
			</tr>
			<?php foreach ($available_gateways as $key => $gateway) :

				if (isset($gateway->settings['enabled']) && $gateway->settings['enabled'] == 'no') {
					continue;
				}
				$option_name_app = 'ci_patients_' . $key . '_app';
				$enabled_app = get_option($option_name_app, 'yes') === 'yes';

				$option_name_web = 'ci_patients_' . $key . '_web';
				$enabled_web = get_option($option_name_web, 'yes') === 'yes';
			?>
				<tr>
					<th scope="row" class="title_row"><?php echo $key; ?></th>
					<td>
						<button
							type="button"
							class="cip-toggle-btn <?php echo $enabled_app ? 'on' : 'off'; ?>"
							data-option="<?php echo esc_attr($option_name_app); ?>"><?php if ($enabled_app) : ?>On<?php else : ?>Off<?php endif; ?>
						</button>
					</td>
					<td>
						<button
							type="button"
							class="cip-toggle-btn <?php echo $enabled_web ? 'on' : 'off'; ?>"
							data-option="<?php echo esc_attr($option_name_web); ?>"><?php if ($enabled_web) : ?>On<?php else : ?>Off<?php endif; ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<script>
	jQuery(document).ready(function($) {
		$('.cip-toggle-btn').on('click', function() {
			let btn = $(this);
			let option = btn.data('option');

			btn.prop('disabled', true);

			$.post(location.href, {
				trajax: '1',
				option: option
			}, function(response) {
				btn.prop('disabled', false);


				if (response.success && response.data.status === "ok") {
					if (response.data.value === 'yes') {
						btn.removeClass('off').addClass('on').text('On');
					} else {
						btn.removeClass('on').addClass('off').text('Off');
					}
				} else {
					alert('Error: ' + (response.data && response.data.message ? response.data.message : 'not update'));
				}
			}).fail(function(xhr, status, error) {
				btn.prop('disabled', false);
				console.error("AJAX error:", status, error);
				console.error("Response:", xhr.responseText);
				alert("AJAX Error: " + error);
			});
		});
	});
</script>