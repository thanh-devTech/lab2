<?php
$tabs = array(
        'welcome' => 'Send Welcome'
);

$addings = array(
        'new_user' => array(
            'name' => 'New Users',
            'file' => TRLCN_PATH.'pages/admin_new_user_multi.php'
        ),
        'pending' => array(
            'name' => 'Pending Users',
            'file' => TRLCN_PATH.'pages/admin_users_pending.php'
        ),
);
foreach($addings as $k => $arr)
{
    if(file_exists($arr['file'])){
        $tabs[$k] = $arr['name'];
    }
}
$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab])){
	$tab = key($tabs);
}
if(!isset($_REQUEST['trajax']) && count($tabs)>1) {
	?>
    <h2 class="nav-tab-wrapper">
		<?php

		foreach ( $tabs as $k => $txt ) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link( array( 'tab'     => $k,
			                                        'act'     => '',
			                                        'id'      => '',
			), false );
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
    </h2>
	<?php
}

if(isset($addings[$tab])){
    include($addings[$tab]['file']);
}
else {

	if ( isset( $_POST['action'] ) ) {

		global $wpdb;
		$_POST = tr_pdd_stripslashes_deep( $_POST );

		if ( isset( $_REQUEST['debug'] ) ) {
			global $debug_email;
			$debug_email = 'pntrinh29@gmail.com';
		}
		$email = $_POST['email_or_user'];
		$user  = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user = get_user_by( 'login', $email );
		}


		$old_data = get_user_meta( $user->ID, '_send_mail_create_user', true );
		$pass     = '';
		if ( ! empty( $old_data['plaintext'] ) ) {
			$pass = $old_data['plaintext'];
		}

		if ( isset( $_POST['create_new_pass'] ) || empty( $old_data['plaintext'] ) ) {
			$pass = wp_generate_password( 12, false );

		}

		$rs = trpdd_send_mail_create_user( $user->ID, $pass );
		if ( $rs ) {
			if ( isset( $_POST['create_new_pass'] ) || empty( $old_data['plaintext'] ) ) {
				$pass_hash = wp_hash_password( $pass );
				$wpdb->update( $wpdb->users, array( 'user_pass' => $pass_hash ), array( 'ID' => $user->ID ) );
			}

			Tr_Session_Class::add( 'Sent to ' . $email );
		} else {
			Tr_Session_Class::add( 'Error. Can\'t send to ' . $email );
		}

	}
	?>

    <div class="wrap">
        <h2>Send Welcome</h2>
		<?php Tr_Session_Class::show() ?>
        <form method="post">
            <div class="frow">
                <label>Email or Username:</label><br>
                <input type="text" name="email_or_user" value="" style="width:400px;"/>
            </div>
            <br>
            <div class="frow">
                <label><input type="checkbox" name="create_new_pass" value="1"/> Create new Password</label>
            </div>
            <br><br>
            <div class="frow">
                <input type="submit" class="button-primary" value="Send"/>
                <input type="hidden" name="action" value="send">
            </div>
        </form>
    </div>
	<?php
}