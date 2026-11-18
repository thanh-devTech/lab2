<?php
class TR_Auto_Logout
{
    var $options = array();
    var $log = '';

    public function __construct()
    {
        $this->options['enable'] = true;
    }

    public function cron()
    {
        $db_version = get_option('TRSCSC_VS');
        if ($db_version != TRSCSC_VS) {
            include(TRSCSC_PATH . 'inc/install.php');
        }

        $this->auto_logout();
        $this->remove_last_activity_records();
    }

    function auto_logout()
    {
        global $wpdb;
        global $blog_id;


        $session_timeout = tr_get_option('tr_security', 'session_timeout', 0);
        $session_timeout = $session_timeout > 0 ? $session_timeout * 60 : 20 * 60;

        $this->log = "\nAuto logout process started at " . date('Y-m-d H:i:s') . "\n";

        $timeout = time() - $session_timeout;
        $sql = "SELECT *
                from ci_user_last_activity
                WHERE blog_id = {$blog_id} and last_activity < " . $timeout;
        $users = $wpdb->get_results($sql);

        $this->log .= $wpdb->last_error . "\n";
        $this->log .= "Auto logout users count: " . count($users) . "\n";

        foreach ($users as $user) {
            // Remove session token

            $blog_id = $user->blog_id;

            $this->log .= "\nUser ID {$user->user_ID}:";
            $user->session_tokens = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'session_tokens'",
                $user->user_ID
            ));
            if (empty($user->session_tokens)) {
                $this->log .= "=> has no session tokens.\n";
                $this->log .= $wpdb->last_error . "\n";
                continue;
            }
            $sessions = maybe_unserialize($user->session_tokens);
            if (is_array($sessions)) {
                $has_change_session = false;
                foreach ($sessions as $token => $session) {
                    if ($token == $user->token) {
                        unset($sessions[$token]);
                        $has_change_session = true;

                        break;
                    }
                }

                if ($has_change_session) {
                    $this->log .= "User ID {$user->user_ID} logged out. Session token: {$user->token}\n";
                    $outtime = time() - $user->last_activity;
                    if ($outtime > $session_timeout) {
                        $outtime = $session_timeout;
                    }
                    if (function_exists('trpdd_log_user')) {
                        $outtime = function_exists('trpdd_display_time_duration') ? trpdd_display_time_duration($outtime, false) : $outtime;
                        trpdd_log_user($user->user_ID, 'logout', 'auto-logout: ' . $outtime, '', '', '', '127.0.0.1');
                    }


                    $wpdb->update($wpdb->usermeta, array(
                        'meta_value' => maybe_serialize($sessions)
                    ), array(
                        'user_id' => $user->user_ID,
                        'meta_key' => 'session_tokens'
                    ));

                    // Remove last activity record

                    $wpdb->delete('ci_user_last_activity', array(
                        'user_ID' => $user->user_ID,
                        'token' => $user->token
                    ));
                } else {
                    $this->log .= "User ID {$user->user_ID} session token not found for token: {$user->token}\n";
                }
            } else {
                $this->log .= " has invalid session tokens data.\n";
            }
        }
    }

    function remove_last_activity_records()
    {
        global $wpdb;
        $timeout = time() - (2 * 86400); // 2 days
        $wpdb->query("DELETE FROM ci_user_last_activity WHERE last_activity < {$timeout}");
    }
}
