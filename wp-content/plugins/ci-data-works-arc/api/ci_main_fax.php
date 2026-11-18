<?php
if(!class_exists('CI_Main_Fax')) {
	class CI_Main_Fax {

		public $api_slug = '';
		public $api;
		public $next_time = 0;
		public $error_message = '';
		public $last_message = '';
		public $last_phone = '';

		public function __construct() {
			$this->api_slug = tr_get_option( 'ci_pdf_data', 'fax_api' );
			if ( empty( $this->api_slug ) ) {
				$this->api_slug = 'CI_Fax_RingCentral';
			}


			$api_file = TRCPDD_PATH . 'api/' . strtolower( $this->api_slug ) . '.php';

			require_once( $api_file );

			$this->api = new $this->api_slug();

			$this->next_time = intval( isset($_SESSION['fax_next_time'])? $_SESSION['fax_next_time'] : 0 );

			if ( $this->next_time < time() ) {
				$this->next_time = time();
			}

		}

		public function is_expired() {
			if ( method_exists( $this->api, 'is_expired' ) ) {
				return $this->api->is_expired();
			} else {
				return false;
			}
		}

		public function refreshToken() {
			if ( method_exists( $this->api, 'refreshToken' ) ) {
				return $this->api->refreshToken();
			} else {
				return true;
			}
		}

		public function send( $number, $files, $sender ) {

			$rs = false;
			try {
				$this->next_time           += 5 * 60;
				$_SESSION['fax_next_time'] = $this->next_time;
				$sendTime                  = date( 'c', $this->next_time );

				$this->last_phone = $number;

				$rs = $this->api->fax( $number, $files, $sender, $sendTime );


			} catch ( Exception $e ) {

			}

			$this->last_message  = $this->api->last_message;
			$this->error_message = $this->api->error_message;

			$f = fopen( WP_CONTENT_DIR . '/log/log_fax.txt', 'a+' );
			fwrite( $f, date_i18n( 'Y-m-d H:i' ) . ' - to: ' . $number . "\n" );
			fwrite( $f, $this->last_message . "\n\n" );
			fwrite( $f, $this->error_message . "\n\n" );
			fclose( $f );

			return $rs;
		}

		public function send_debug( $number, $files, $sender ) {

			$to       = 'pntrinh29@gmail.com';
			$from     = get_bloginfo( 'admin_email' );
			$subject  = 'Fax';
			$msg      = $number;
			$reply_to = $from;
			$from     = $sender . ' <' . $from . ">";

			$headers = 'From: ' . $from . "\r\n";
			$headers .= 'Reply-To: ' . $reply_to . "\r\n";
			$headers .= "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

			return wp_mail( $to, $subject, $msg, $headers, $files );
		}
	}
}