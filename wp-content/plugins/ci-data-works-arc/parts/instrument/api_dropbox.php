<?php
global $wpdb;
include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . '/api/ci_dropbox.php');
$api_key = 'api_dropbox';

if (!isset($define_apis[$api_key])) {
	echo 'not enable';
	exit;
}

if (isset($_POST['options'])) {
    $options=$_POST['options'];
	$key_api=$define_apis[$api_key]['st'];
    $old_options = get_option($key_api, array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_'.$key_api, $bkdata);
	Tr_Session_Class::add('Saved options successfully.');

	$options = $_POST['options'];

	update_option($define_apis[$api_key]['st'], $options);
}
$options = get_option($define_apis[$api_key]['st'], array());



if (isset($_POST['ci_drbox_unlink_account'])) {
	CI_Dropbox::delete_token('access');
	wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
}
try {
	$dropbox = new CI_Dropbox();


	if (!empty($_GET['code'])) // are we coming from dropbox's auth page?
	{
		$res = $dropbox->storeTokenFromCode($_GET['code']);
	
		if (!empty($res['t'])) {
			Tr_Session_Class::add('Authorized');
		} else {
			Tr_Session_Class::add_error("Error when Authorize  ");
		}

		
		wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));


		wp_redirect(Tr_Base_Class_V6::link(array('code' => '', 'trajax' => ''), false));
	} else if (isset($_POST['refresh_token'])) {
		$res = $dropbox->do_Refresh();
		if (!empty($res['t'])) {
			Tr_Session_Class::add('Refreshed Token');
		} else {
			Tr_Session_Class::add_error("Error when refresh token ");
		}
	} else if (isset($_POST['createfolder'])) {
		if (!empty($options['main_folder'])) {
			$root_dir = '/' . $options['main_folder'];
			$rs = $dropbox->CreateFolder($root_dir);
		}


		if ($rs) {
			Tr_Session_Class::add("Created folder: " . $root_dir);
		} else {
			Tr_Session_Class::add_error("Error when create folder: " . $root_dir);
		}
	}
} catch (Exception $e) {
	var_dump($e);
}



if (empty($options['main_folder'])) {
	$options['main_folder'] = 'results';
}

?>
<div class="wrap">
	<?php Tr_Session_Class::show() ?>
	<div id="poststuff">

		<div class="meta-box-sortables">
			<div id="uploader" class="postbox">


				<div class="inside">

					<h3>Dropbox Settings</h3>
					<form method="post">
						<div class="row">
							<label>App Key</label>
							<input type="text" name="options[app_key]" value="<?php echo @$options['app_key'] ?>" />
						</div>
						<div class="row">
							<label>App Secret</label>
							<input type="text" name="options[app_secret]" value="<?php echo @$options['app_secret'] ?>" />
						</div>
						<div class="row">
							<label>Main Folder</label>
							<input type="text" name="options[main_folder]" value="<?php echo @$options['main_folder'] ?>" />
						</div>

						<div class="submit">
							<input type="submit" class="button button-primary" value="Save" />
							<?php
							if ($dropbox->IsAuthorized()) {
							?>
								<input type="submit" class="button" name="createfolder" value="Create Folders" />
								<input type="submit" class="button" name="refresh_token" value="Refresh Token" />
							<?php
							}
							?>
						</div>
					</form>
				</div>
			</div>
		</div>
		<div class=""> Redirect Uri:
			<?php echo $dropbox->getRedirectUri(); ?>
		</div>


		<?php
		if ($dropbox && $dropbox->IsAuthorized()) {
			try {
				$account_info = $dropbox->GetAccountInfo();

				$space = $dropbox->GetSpaceUsage();

				$files = $dropbox->list_folder('');
				if ($files === false) {
					echo '<div>Cant access to list folder</div>';
				}

				$hascron = wp_get_schedule($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
				if (empty($hascron)) {
					wp_schedule_event(time() + 1, 'hourly', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
				}

				//var_dump(CI_Dropbox::load_token('access'));
				//var_dump($account_info);
			} catch (DropboxException $err) {
				var_dump($err);
				$err = $err->getMessage();
				if (stripos($err, 'expired_access_token') !== false) {
					CI_Dropbox::delete_token('access');
				}
			}
		}
		if (empty($options['app_key']) || empty($options['app_secret'])) {
		} else if (!$dropbox->IsAuthorized()) {
			// redirect user to dropbox auth page
			$auth_url = $dropbox->BuildAuthorizeUrl($dropbox->getRedirectUri());
		?>
			<div class="meta-box-sortables">
				<div id="uploader" class="postbox">
					<div class="inside">
						<h3>Authorize</h3>
						<p><a class="button button-primary" href="<?php echo $auth_url ?>">Authorize</a></p>

					</div>
				</div>
			</div>
			<?php
		} else {

			$used = round($space->used / 1073741824, 1);
			$quota = round($space->allocation->allocated / 1073741824, 1);

			if ($quota > 0) {
			?>



				<div class="meta-box-sortables">
					<div id="uploader" class="postbox">
						<div class="inside">
							<h3>Dropbox Account Details</h3>

							<p><?php echo $account_info->name->display_name ?>, you have <?php echo $used ?>GB of <?php echo $quota ?>GB (<?php echo round(($used / $quota) * 100, 0) ?>%) free </p>
							<form method="post">
								<input type="submit" class="button" name="ci_drbox_unlink_account" value="Unlink Account" />
							</form>
						</div>
					</div>
				</div>

		<?php
			}
		}
		?>
	</div>
</div>