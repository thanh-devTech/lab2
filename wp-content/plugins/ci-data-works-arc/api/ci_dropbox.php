<?php
include_once('DropboxClient.php');

class CI_Dropbox extends DropboxClient
{

    public $options = array();
    public $log = '';

    function __construct()
    {
        $this->options = get_option('tr_dropbox_st', array());

        if (empty($redirect_uri)) {
            $redirect_uri = $this->getRedirectUri();
        }

        parent::__construct(array(
            'app_key' => $this->options['app_key'],
            'app_secret' => $this->options['app_secret'],
            'app_full_access' => true,
            'redirect_uri' => $redirect_uri
        ));


        $access_token = self::load_token("access");


        if (!empty($access_token['t'])) {
            $this->SetBearerToken($access_token);
            $this->options['enable'] = true;
        }
    }

    function cron()
    {
        $access_token = self::load_token("access");
        if (!empty($access_token['t'])) {
            //check refresh
            $current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
            $this->log .= "expiry: " . date('Y-m-d H:i:s', $access_token['expires_in']+$current_offset) ;
            if ($access_token['expires_in'] < time() + 3600) {
                $newaccess_token = $this->do_Refresh();
                $this->log .= " => new expiry: " . date('Y-m-d H:i:s', $newaccess_token['expires_in']+$current_offset) . "\n";
            } else {
                $this->log .= " => token not expiry\n";
            }
        } else {
            $this->log .= "token empty\n";
        }
    }


    public function storeTokenFromCode($code)
    {
        try {
            $res = $this->GetBearerToken($code, $this->getRedirectUri());

            if (!empty($res['t'])) {
                $this->store_token($res, 'access');
            }
        } catch (Exception $e) {
        }
        return $res;
    }

    public function do_Refresh()
    {
        $res = array();
        try {
            $res = $this->Refresh();
            if (!empty($res['t'])) {
                $this->store_token($res, 'access');
            }
        } catch (Exception $e) {
            var_dump($e);
        }
        return $res;
    }

    public function ready()
    {
        return $this->IsAuthorized();
    }

    public function getRedirectUri()
    {
        $url = get_bloginfo('url') . '?auth=dropbox';
        if (stripos($url, 'http://') !== false && stripos($url, 'localhost') === false) {
            $url = str_replace('http://', 'https://', $url);
        }
        return $url;
    }

    public static function load_token($name)
    {
        $token = get_option('tr_api_dropbox_' . $name);
        return $token;
    }
    public static function store_token($token, $name)
    {
        if (function_exists('update_option'))
            update_option('tr_api_dropbox_' . $name, $token);
    }
    public static function delete_token($name)
    {
        if (function_exists('update_option'))
            update_option('tr_api_dropbox_' . $name, '');
    }
}
