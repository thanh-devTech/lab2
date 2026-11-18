<?php if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

define('OTP_AUTH_PAGE', true);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
setcookie('wp-postpass_login', '0', time() + 5);

$pa_options = get_option('trpatient_settings', array());
$show_dob = !empty($pa_options['enable_check_dob']) || !empty($pa_options['enable_acheck_dob']);
$show_name = !empty($pa_options['enable_acheck_name']);

$url_logo_header = '';

if (!empty($pa_options['logo_login'])) {
	$url_logo_header = wp_get_attachment_image_url($pa_options['logo_login'], 'large');
} else {
	$current_lab = CI_Base_Lab::get_current_lab();
	if ($current_lab) {
		$upload_dir = tr_wp_upload_dir();
		$logo       = $current_lab['lab_logo'];
		$url_logo_header   = $upload_dir['baseurl'] . '/logos/' . $logo;
	} else {
		if (!empty($otp_logo_url))
			$url_logo_header = $otp_logo_url;
		else
			$url_logo_header = TRCPPR_URL . 'images/logo.png';
	}
}

?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
	<title>Login</title>
	<meta name="format-detection" content="telephone=no">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<?php //wp_head();
	wp_print_head_scripts();
	wp_print_styles();
	?>
</head>

<body class="otpa-<?php echo esc_attr(str_replace('_', '-', $otp_form_type)); ?> otpa-page">

	<div class="otpa-inner">
		<div class="otpa-wrapper">
			<div id="otpa_logo" class="otpa-logo" data-otp_logo_url="<?php echo esc_url($url_logo_header); ?>"></div>
			<div id="otpa_otp_form" data-otp_form_type="<?php echo esc_attr($otp_form_type); ?>" data-handler="verify_otp_code" data-dob-enabled="<?php echo $show_dob ? '1' : '0'; ?>" data-patient-name-enabled="<?php echo $show_name ? '1' : '0'; ?>" class="otpa-form">
				<h2 class="text-xl font-semibold text-[#3C3D59] text-center"><?php echo esc_html($otp_form_title); ?></h2>
				<?php do_action('otpa_before_otp_form', $otp_form_type); ?>
				<form>
					<?php wp_nonce_field('otpa_nonce', 'otpa_nonce'); ?>
					<?php do_action('otpa_before_otp_widget', $otp_form_type); ?>
					<div class='otpa-widget'>
						<?php echo $otp_widget; // @codingStandardsIgnoreLine 
						?>
					</div>
					<?php do_action('otpa_after_otp_widget', $otp_form_type); ?>
					<button disabled="disabled" id="otpa_submit" class="submit"><?php esc_html_e('Sign In', 'otpa'); ?></button>
					<div id="adding_html"></div>
					<p id="otpa_result" class="message result"></p>
					<span id="otpa_unknown_result" class="hidden"><?php esc_html_e('An undefined error occured.', 'otpa'); ?><br /><?php esc_html_e('Make sure you have a working internet connection and try again. If the problem persists, please contact an administrator.', 'otpa'); ?></span>
					<?php do_action('otpa_before_otp_submit_button', $otp_form_type); ?>

					<?php do_action('otpa_after_otp_submit_button', $otp_form_type); ?>
					<input type="hidden" id="otpa_id">
					<input type="hidden" id="otpa_code">
					<p class="message footer">
						<?php do_action('otpa_before_otp_footer_message', $otp_form_type); ?>
						<?php echo $otp_footer_message; // @codingStandardsIgnoreLine 
						?>
						<?php do_action('otpa_after_otp_footer_message', $otp_form_type); ?>
					</p>
				</form>
				<?php do_action('otpa_after_otp_form', $otp_form_type); ?>
			</div>
			<div class="flex items-center justify-center gap-1 text-xs otp_nav_mm">
				<button onclick="show_help();return false;" style="font-weight: 700;" type="button" class="cursor-pointer text-[#D83D00] hover:text-[#c03600] hover:underline transition-colors font-bold">Help</button>
				<span class="text-gray-700 text-base">•</span>
				<button onclick="show_my_ip();return false;" style="font-weight: 700;" type="button" class="cursor-pointer text-[#D83D00] hover:text-[#c03600] hover:underline transition-colors font-bold">What is my IP?</button>
			</div>
			<div class="form_footer">
				<div>
					<a class="link_frm" href="<?php $login_url = wp_login_url();
												echo $login_url . (strpos($login_url, '?') === false ? '?' : '&') . 'login_pass=1'; ?>">Login with email</a>
				</div>
				<div class="copyright">© 2026 igniteLIS</div>
			</div>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>

</html>

<script>
	var boxHelp = null;
	jQuery(function($) {
		$(".otpa-hide").hide();
		$(".otpa-dob-field").hide();
		$(".otpa-first_name-field").hide();
		$(".otpa-last_name-field").hide();
		$("#otpa_result").hide();
		$("#otpa_send_code").on("click", function(e) {
			$("#otpa_submit").show();
			$(".otpa-code-field").show();
			$("#otpa_result").show();
		});
		$("#otpa_otp_form .message").html("Access your lab results securely");
	});

	function show_help() {
		boxHelp = jQuery(`
        <div id='bg_white_login' onclick="close_help();return false">
            <div id="box_info">
                <div class="box_info_header">
                    <h3>Need Help Logging In?</h3>
                    <svg class="icon_close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-6 h-6" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </div>
                <div class="box_info_body">
                    <p>
                        If you’re having trouble accessing your account, try the tips below.
                    </p>
                    <div class="panel">
                        <strong class="panel_title">Login tips</strong>
						<ul>
						<li>• Use the same mobile number you gave your clinic</li>
						<li>• Don’t see your code? Check recent texts - it may come from an unfamiliar number.</li>
						<li>• Enter your date of birth exactly (MM/DD/YYYY)</li>
						</ul>
                    </div>

                    <div class="panel">
                        <strong class="panel_title">Still Need Help?</strong>
                        Contact our Patient Support Team:
                        <br/><br/>
                        <span class="text-h">Phone: 1-800-IGNITE-1</span><br/>
                        <span class="text-h">Email: support@ignitelis.com</span><br/>
                        <span class="text-h">Hours: Mon-Fri 8AM-6PM EST</span><br/>
                    </div>

					<button class="cursor-pointer w-full bg-[#D83D00] hover:bg-[#c03600] text-white py-2.5 rounded-lg transition-all">Close</button>
                </div>
            </div>
        </div>
    `);

		jQuery("body").append(boxHelp);
	}

	function close_help() {
		boxHelp.remove();
	}

	function show_my_ip() {
		<?php
		$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		?>
		alert("Your IP address is: <?php echo $ip; ?>");
	}
</script>