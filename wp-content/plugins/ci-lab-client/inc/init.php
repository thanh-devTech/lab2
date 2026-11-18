<?php
if (strpos($_SERVER['REQUEST_URI'], '/lab_api/') === 0) {
	include(TRLCN_PATH . '/api/ci_lab_client.php');
	define('LAB_API', true);
	$api = new CI_Lab_Client_Api();
	exit;
}
//connect to hub
include(TRLCN_PATH . 'inc/ci_auth.php');

//cron multisites
if (defined('DOING_CRON') && DOING_CRON) {
	include(TRLCN_PATH . '/inc/cron_multisites.php');
}

add_action('cron_lab_client', 'trlcn_cron_lab_client');
function trlcn_cron_lab_client()
{
	include(TRLCN_PATH . '/inc/crons/lab_client.php');
}

add_action('cron_lab_client_clean', 'trlcn_cron_lab_client_clean');
function trlcn_cron_lab_client_clean()
{
	include(TRLCN_PATH . '/inc/crons/clean.php');
}

add_action('cron_lab_client_mid_month_statistics', 'trlcn_cron_lab_client_monthly_statistics');
add_action('cron_lab_client_end_month_statistics', 'trlcn_cron_lab_client_monthly_statistics');
function trlcn_cron_lab_client_monthly_statistics()
{
	include(TRLCN_PATH . '/inc/crons/monthly_statistics.php');
}

add_action('cron_client_sync_whatnew', 'trlcn_cron_lab_client_sync_whatnew');
function trlcn_cron_lab_client_sync_whatnew()
{
	include(TRLCN_PATH . '/inc/crons/sync_whatnew.php');
}

add_action('cron_update_abx', 'trlcn_cron_update_abx');
function trlcn_cron_update_abx()
{
	include(TRLCN_PATH . '/inc/crons/update_abx.php');
}
add_action('cron_run_query', 'trlcn_cron_run_query');
function trlcn_cron_run_query()
{
	include(TRLCN_PATH . '/inc/crons/cron_run_query.php');
}

add_filter('wp_redirect', 'trlcn_wp_redirect_filter', 10, 2);
function trlcn_wp_redirect_filter($location, $status = '')
{
	if (headers_sent()) {
		echo '<meta http-equiv="refresh" content="1;url=' . $location . '"/>';
	}
	return $location;
}

function trlnc_convert_key($fcontent)
{
	$pubcontent = '';
	$ppkcontent = '';
	require_once TRLCN_PATH . 'vendor/autoload.php';

	if (stripos($fcontent, 'PuTTY') !== false) {

		$pubcontent = '';
		//convert
		$lines = explode("\n", $fcontent);

		$start_pub = false;
		foreach ($lines as $line) {
			$line = trim($line);
			if (stripos($line, 'Public-Lines') !== false) {
				$start_pub = true;
				continue;
			}
			if (stripos($line, 'Private-Lines') !== false) {
				$start_pub = false;
				continue;
			}
			if ($start_pub) {
				$pubcontent .= $line;
			}
		}
		if (!empty($pubcontent)) {
			$pubcontent = "ssh-rsa " . $pubcontent;
			$pubcontent .= ' rsa-key-20230728';
		}

		// phpseclib 3.x: load the PuTTY .ppk and re-export the private key as OpenSSH.
		// Replaces phpseclib 1.x: new Crypt_RSA()->loadKey()->getPrivateKey(CRYPT_RSA_PUBLIC_FORMAT_OPENSSH).
		// $fcontent is an uploaded key file (trust boundary) — a malformed key must not fatal.
		try {
			$ppkcontent = \phpseclib3\Crypt\PublicKeyLoader::load($fcontent)->toString('OpenSSH');
		} catch (\Throwable $e) {
			$ppkcontent = '';
		}
	}

	return array('pub' => $pubcontent, 'ppk' => $ppkcontent);
}

function tr_client_send_hub($args)
{
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$rs = $client->send_to_hub($args);
	if (isset($args['both_server'])) {
		unset($args['both_server']);
		$args['old_server'] = true;
		$rs2 = $client->send_to_hub($args);
		if (isset($_GET['debug2'])) {
			var_dump($rs2);
		}
	}
	return $rs;
}
function tr_client_check_auth_hub($args)
{
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$rs = $client->check_auth_hub($args);
	return $rs;
}
function tr_client_forgot_password_hub($args)
{
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$rs = $client->forgot_password_hub($args);
	return $rs;
}
function tr_client_send_user_feedback($args)
{
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$rs = $client->send_user_feedback($args);
	return $rs;
}

add_filter('pre_wp_mail', 'check_smtp_configuration', 1, 2);
function check_smtp_configuration($return, $args)
{
	if (
		defined('FLUENTMAIL_PLUGIN_FILE')
		|| defined('EasyWPSMTP_PLUGIN_VERSION')
		|| class_exists('GMAIL_SMTP')
	) {
		return $return;
	}

	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	try {

		if (isset($args['attachments'])) {
			$attachments = is_array($args['attachments']) ? $args['attachments'] : array($args['attachments']);
			$args['attachments'] = array();

			foreach ($attachments as $att) {
				if (file_exists($att)) {
					$file_content = file_get_contents($att);
					$base64_content = base64_encode($file_content);

					$file_name = basename($att);

					$args['attachments'][] = array(
						'name' => $file_name,
						'content' => $base64_content
					);
				}
			}
		}
		return $client->send_mail_to_hub(['data' => $args]);
	} catch (\Throwable $th) {
	}
	return $return;
}

add_action('do_faviconico', function () {
	wp_redirect(TRLCN_URL . 'images/favicon.ico');
	exit;
});

add_filter('rest_authentication_errors', function ($result) {
	if (!is_user_logged_in()) {
		return new WP_Error('rest_disabled', 'REST API disabled.', array('status' => 403));
	}
	return $result;
});

// send mail panels change
include(TRLCN_PATH . 'inc/crons/send_mail_panels_change.php');

function trlcn_update_abx()
{
	include TRLCN_PATH . 'inc/crons/update_abx_template.php';
}

function trpdd_run_check_unsent_state_report()
{
	include(TRLCN_PATH . '/inc/crons/check_unsent_state_report.php');
}

add_action('cron_check_unsent_state_report', 'trpdd_run_check_unsent_state_report');

function trpdd_parse_multi_user_line($line)
{
	$line = trim($line);
	if ($line === '') {
		return null;
	}

	$tokens = preg_split('/\s+/', $line);

	// Find token is email
	$email = '';
	$email_index = -1;
	foreach ($tokens as $i => $token) {
		$clean = trim($token, ",;|");
		if (is_email($clean)) {
			$email = $clean;
			$email_index = $i;
			break;
		}
	}

	if ($email_index === -1) {
		return array('error' => $line . ' - Not found valid email');
	}

	$before = array_slice($tokens, 0, $email_index);
	$after  = array_slice($tokens, $email_index + 1);

	$first_name = isset($before[0]) ? $before[0] : '';
	$last_name  = count($before) > 1 ? implode(' ', array_slice($before, 1)) : '';

	$password   = '';
	$role_label = '';

	if (!empty($after)) {
		if (preg_match('/^password:?(.+)$/i', $after[0], $m)) {
			// format: password:xxxxx 
			$password = $m[1];
		} elseif (strtolower($after[0]) === 'password' && isset($after[1])) {
			// format: password xxxxx 
			$password = $after[1];
		} else {
			// role, VD "lab manager"
			$role_label = implode(' ', $after);
		}
	}

	return array(
		'first_name' => $first_name,
		'last_name'  => $last_name,
		'email'      => $email,
		'password'   => $password,
		'role_label' => $role_label,
	);
}

function trpdd_match_role_by_label($role_label, $available_roles)
{
	$role_label = trim($role_label);
	if (empty($role_label)) {
		return '';
	}
	if (isset($available_roles[$role_label])) {
		return $role_label;
	}
	$lower = strtolower($role_label);
	if (isset($available_roles[$lower])) {
		return $lower;
	}

	$slug = str_replace(' ', '_', $lower);
	if (isset($available_roles[$slug])) {
		return $slug;
	}
	return '';
}

/**
 * Return array $results, item have status: success | exists | error
 */
function trpdd_process_multi_user_add($emails_raw, $default_role, $send_mail)
{
	global $wp_roles;
	$available_roles = $wp_roles->roles;

	$lines = preg_split('/\r\n|\r|\n/', trim($emails_raw));
	$lines = array_filter(array_map('trim', $lines));

	if (empty($lines)) {
		return array('error' => 'Please enter the list of users!');
	}

	$results = array();

	foreach ($lines as $line) {
		$parsed = trpdd_parse_multi_user_line($line);
		if (empty($parsed)) {
			continue;
		}
		if (isset($parsed['error'])) {
			$results[] = array('status' => 'error', 'message' => $parsed['error']);
			continue;
		}

		$user_email = $parsed['email'];
		$role_key   = trpdd_match_role_by_label($parsed['role_label'], $available_roles);
		$role       = !empty($role_key) ? $role_key : $default_role;

		if ($role === 'data_export' && !is_super_admin()) {
			$results[] = array('status' => 'error', 'message' => 'Only Super Admin can assign the Data Export role. (' . $user_email . ')');
			continue;
		}

		if (email_exists($user_email)) {
			$user_details = get_user_by('email', $user_email);
			if (empty($user_details->roles)) {
				wp_update_user(array('role' => $role, 'ID' => $user_details->ID));
			}
			$results[] = array('status' => 'exists', 'email' => $user_email, 'message' => $user_email . ' exists');
			continue;
		}

		$password = !empty($parsed['password']) ? $parsed['password'] : wp_generate_password();

		$userdata = array(
			'user_login' => $user_email,
			'user_email' => $user_email,
			'role'       => $role,
			'user_pass'  => $password,
		);
		if (!empty($parsed['first_name'])) {
			$userdata['first_name'] = $parsed['first_name'];
			$userdata['last_name']  = $parsed['last_name'];
		}

		$user_id = wp_insert_user($userdata);

		if (is_wp_error($user_id)) {
			$results[] = array('status' => 'error', 'message' => $user_email . ' error: ' . implode(', ', $user_id->get_error_messages()));
			continue;
		}

		if ($send_mail != '0') {
			trpdd_send_new_user_email($user_email, $password);
		}

		$results[] = array(
			'status'     => 'success',
			'first_name' => $parsed['first_name'],
			'last_name'  => $parsed['last_name'],
			'email'      => $user_email,
			'password'   => $password,
			'role_key'   => $role,
			'role_name'  => isset($available_roles[$role]) ? $available_roles[$role]['name'] : $role,
		);
	}

	return array('results' => $results);
}

function trpdd_send_new_user_email($user_email, $password)
{
	$message = __(
		'Hi,

		You\'ve been invited to join \'%1$s\' at
		%2$s with the email: %3$s.
		Your password: %4$s

		Please click the following link to Login:
		%5$s'
	);
	$subject = sprintf(__('[%s] Joining to'), wp_specialchars_decode(get_option('blogname')));
	$body = sprintf($message, get_option('blogname'), home_url(), $user_email, $password, home_url('/wp-login.php'));
	wp_mail($user_email, $subject, $body, '');
}
