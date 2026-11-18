<?php
function sendPushNotification($deviceToken, $title, $body) {
    $url = "https://fcm.googleapis.com/fcm/send";
    $serverKey = "YOUR_FIREBASE_SERVER_KEY";

    $data = [
        "to" => $deviceToken,
        "notification" => [
            "title" => $title,
            "body" => $body,
            "sound" => "default"
        ],
        "data" => [
            "extraData" => "optional"
        ]
    ];

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

$token = 'EXPO_PUSH_TOKEN_HERE';

 if (isset($_POST['firebase_save_settings'])) {
    $server_key   = sanitize_text_field($_POST['firebase_server_key'] ?? '');
    $client_token = sanitize_text_field($_POST['firebase_client_token'] ?? '');

    update_option('firebase_server_key', $server_key);
    update_option('firebase_client_token', $client_token);

    echo '<div class="updated"><p>Save setting success!</p></div>';
}

if(isset($_POST['firebase_test_notification'])){
    //sendPushNotification($token, 'Notification WP', 'Content test'); 
    echo '<div class="notice"><p>Send notification success!</p></div>';
}

$server_key   = get_option('firebase_server_key', '');
$client_token = get_option('firebase_client_token', '');
?>
<div class="wrap">
    <h1>Firebase Notifications</h1>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="firebase_server_key">Server Key</label></th>
                <td><input type="text" id="firebase_server_key" name="firebase_server_key" value="<?php echo esc_attr($server_key); ?>" style="width:400px;"></td>
            </tr>
            <tr>
                <th scope="row"><label for="firebase_client_token">Client Token</label></th>
                <td><input type="text" id="firebase_client_token" name="firebase_client_token" value="<?php echo esc_attr($client_token); ?>" style="width:400px;"></td>
            </tr>
        </table>
        <?php submit_button('Save Setting', 'primary', 'firebase_save_settings'); ?>
        <?php submit_button('Test Send Device', 'success', 'firebase_test_notification'); ?>
    </form>

    <div style="font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px;">
    <h2>🔔 Push Notification Setup Guide</h2>

    <h3>1. How Push Notifications Work</h3>
    <p>
        Push notifications allow your app or website to send real-time messages directly to users’ devices,
        even when they are not actively using the app.
    </p>
    <ol>
        <li>Your app requests permission from the user to receive notifications.</li>
        <li>Firebase Cloud Messaging (FCM) generates a unique <strong>device token</strong> for each user.</li>
        <li>Your server sends a notification request to Firebase, including:
        <ul>
            <li>The <strong>Server key</strong> (project authentication key).</li>
            <li>The <strong>Client token</strong> (the unique device token of the user).</li>
        </ul>
        </li>
        <li>Firebase delivers the message to the user’s device.</li>
    </ol>

    <h3>2. Getting the Firebase Keys</h3>

    <h4>A. Server Key</h4>
    <ol>
        <li>Go to <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a>.</li>
        <li>Open your project → <strong>Project Settings</strong>.</li>
        <li>Navigate to the <strong>Cloud Messaging</strong> tab.</li>
        <li>Under <em>Project credentials</em>, copy the <strong>Server key (Legacy key)</strong>.</li>
        <li>Save this as your <code>firebase_server_key</code>.</li>
    </ol>

    <h4>B. Client Token (Device Token)</h4>
    <ol>
        <li>Your app (Android, iOS, or Web) requests a device token from Firebase using the SDK.</li>
        <li>When the user opens the app, Firebase generates a <strong>unique device token</strong>.</li>
        <li>Store this token in your backend or WordPress database as <code>firebase_client_token</code>.</li>
    </ol>

    <h3>3. Saving Keys in the Settings Page</h3>
    <p>
        In this settings page, you will find two input fields:
    </p>
    <ul>
        <li><strong>Firebase Server Key</strong> → paste the server key here.</li>
        <li><strong>Firebase Client Token</strong> → paste the device token here.</li>
    </ul>
    <p>
        These values will be used whenever your backend sends push notifications.
    </p>

    <h3>4. Sending a Test Notification</h3>
    <ol>
        <li>Save the keys above in this page.</li>
        <li>Use your backend (PHP / WordPress) to send a test notification with
        <code>firebase_server_key</code> + <code>firebase_client_token</code>.
        </li>
        <li>If successful, you should receive the notification on your device.</li>
    </ol>
    </div>

</div>