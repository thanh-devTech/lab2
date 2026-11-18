<?php
/**
 * Plugin Name: E2E boundary stub (test-only)
 * Description: Shapes outbound HTTP that goes through wp_remote_* and points wp_mail at mailhog.
 *
 * THIS IS NOT A SECURITY BOUNDARY (D014/D015). It can only see calls that pass
 * through WP_Http::request(). 43 of the 68 integrations in 13-boundaries.md use
 * raw curl_init / SoapClient / ssh2_* / file_get_contents('http…') and are
 * invisible here. The container's `internal: true` network is the boundary; this
 * file is response *shaping* so tests get deterministic bodies instead of errors.
 *
 * Lives under tests/e2e/env/mu-plugins and is reached via WPMU_PLUGIN_DIR in the
 * test wp-config. Nothing under wp-content/plugins/ is touched.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('E2E_HTTP_LOG', '/tmp/e2e-http-intercepts.log');

/** Append one JSON line per wp_remote_* call the filter sees. */
function e2e_http_log(array $row)
{
	$row['t'] = microtime(true);
	@file_put_contents(E2E_HTTP_LOG, json_encode($row) . "\n", FILE_APPEND);
}

/**
 * host+path substring => [status, body] fixture.
 * Keep this list to what the smoke set actually reaches; anything else is
 * refused, loudly, rather than silently allowed through to the network.
 */
function e2e_http_fixtures()
{
	$dir = __DIR__ . '/../fixtures/';
	return array(
		'imt.ignitelabconnect.com/labs_api/get_ip' => array(200, @file_get_contents($dir . 'hub-get-ip.json')),
		'api.labelary.com/v1/printers'             => array(200, "\x89PNG\r\n\x1a\n"),
		'phat-reaction.com'                        => array(200, '[]'),
	);
}

add_filter('pre_http_request', function ($pre, $args, $url) {
	// A control switch, so the same build can demonstrate stubbed and unstubbed.
	if (getenv('E2E_STUB_HTTP') === '0') {
		e2e_http_log(array('url' => $url, 'method' => isset($args['method']) ? $args['method'] : 'GET', 'action' => 'observed_only'));
		return $pre;
	}

	foreach (e2e_http_fixtures() as $needle => $fixture) {
		if (strpos($url, $needle) !== false) {
			e2e_http_log(array('url' => $url, 'method' => isset($args['method']) ? $args['method'] : 'GET', 'action' => 'stubbed', 'match' => $needle));
			return array(
				'headers'  => array(),
				'body'     => $fixture[1],
				'response' => array('code' => $fixture[0], 'message' => 'OK'),
				'cookies'  => array(),
				'filename' => null,
			);
		}
	}

	e2e_http_log(array('url' => $url, 'method' => isset($args['method']) ? $args['method'] : 'GET', 'action' => 'refused'));
	return new WP_Error('e2e_unstubbed_egress', 'E2E: no fixture for ' . $url);
}, 1, 3);

/**
 * mailhog, part 1: unhook the hub mail relay.
 *
 * `ci-lab-client/inc/init.php:143` registers `check_smtp_configuration` on
 * `pre_wp_mail` at priority 1, which short-circuits EVERY wp_mail() into
 * CI_Lab_Client_Api::send_mail_to_hub() -- subject, body and base64 attachments
 * -- unless FluentSMTP / EasyWPSMTP / GMAIL_SMTP is present. Nothing reaches
 * PHPMailer in a stock install, so mailhog would stay empty forever.
 *
 * mu-plugins load before plugins, so the filter does not exist yet at this
 * point; remove it once plugins are up.
 */
add_action('plugins_loaded', function () {
	remove_filter('pre_wp_mail', 'check_smtp_configuration', 1);
}, 999);

/**
 * mailhog, part 2. The stack ships the container but no PHP SMTP transport was
 * wired, so wp_mail() had nowhere to go. PHPMailer is the only hook needed.
 */
add_action('phpmailer_init', function ($phpmailer) {
	$phpmailer->isSMTP();
	$phpmailer->Host       = 'mailhog';
	$phpmailer->Port       = 1025;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPAutoTLS = false;
	$phpmailer->SMTPSecure = '';
});

/**
 * Passthrough: WPMU_PLUGIN_DIR is exclusive, so the repo's own mu-plugins would
 * stop loading if this directory did not include them.
 */
foreach ((array) glob(WP_CONTENT_DIR . '/mu-plugins/*.php') as $e2e_repo_mu) {
	include_once $e2e_repo_mu;
}
