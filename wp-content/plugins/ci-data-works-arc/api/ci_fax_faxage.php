<?php
if(!class_exists('CI_Fax_Faxage')) {
	class CI_Fax_Faxage {

		var $url = 'https://api.faxage.com/httpsfax.php';
		var $options = array();
		var $last_phone = '';
		var $error_message = '';
		var $last_message = '';

		public function __construct() {
			$this->options = get_option( 'ci_faxage_st', array() );
		}

		public function fax( $to, $files, $sender = '', $sendTime = '' ) {
			$faxfilenames = array();
			$faxfiledatas = array();
			foreach ( $files as $file ) {
				$faxfilenames[] = basename( $file );
				$faxfiledatas[] = base64_encode( file_get_contents( $file ) );
			}

			$body = array(
				'username'     => $this->options['username'],
				'company'      => $this->options['company'],
				'password'     => $this->options['password'],
				'recipname'    => $this->options['recipname'],
				'faxno'        => $to,
				'operation'    => 'sendfax',
				'faxfilenames' => $faxfilenames,
				'faxfiledata'  => $faxfiledatas,
			);


			$rs = wp_remote_post( $this->url, array(
				'body' => $body
			) );

			if ( stripos( $rs['body'], 'JOBID' ) !== false ) {
				$this->last_message  = $rs['body'];
				$this->error_message = '';

				return true;
			} else {
				$this->last_message  = '';
				$this->error_message = $rs['body'];
			}

			return false;
		}

		public function send_url( $to, $file_url ) {
			$body = array(
				'username'  => $this->options['username'],
				'company'   => $this->options['company'],
				'password'  => $this->options['password'],
				'recipname' => $this->options['recipname'],
				'faxno'     => $to,
				'operation' => 'sendfax',
				'faxurl'    => $file_url,
			);

			$rs = wp_remote_post( $this->url, array(
				'body' => $body
			) );

			return $rs;
		}
	}
}