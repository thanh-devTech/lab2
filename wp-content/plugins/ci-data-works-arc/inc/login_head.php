<?php

global $wpdb;

$lab_id     = CI_Base_Lab::get_lab_id_by_blog_id();
$logo       = tr_get_option('ci_pdf_data', 'logo');
$logo       = @$logo['src'];

if (isset($_REQUEST['id'])) {
    $logo1_id = $_REQUEST['id'];
    $logo1 = $wpdb->get_var("select lab_logo from ci_labs where lab_ID = {$logo1_id}");
    if (!empty($logo1)) {
        $upload_dir = tr_wp_upload_dir();
        $logo_path = $upload_dir['basedir'] . '/logos/' . $logo1;
        $logo1 = $upload_dir['baseurl'] . '/logos/' . $logo1;
?>
        <script>
            jQuery(function($) {
                var $new = '<img src="<?php echo $logo1 ?>" style="width:99.5%;background:#fff;padding:2px;"/>';
                $new += '<div>Powered by</div>';
                $('#login').prepend($($new));
                $('form').append('<input type="hidden" name="id" value="<?php echo $logo1_id ?>"/>');
            })
        </script>
    <?php
    }
}


if (empty($logo) && $lab_id > 0) {
    $logo = $wpdb->get_var("select lab_logo from ci_labs where lab_ID = {$lab_id}");
    if (!empty($logo)) {
        $upload_dir = tr_wp_upload_dir();
        $logo_path = $upload_dir['basedir'] . '/logos/' . $logo;
        $logo = $upload_dir['baseurl'] . '/logos/' . $logo;
    }
} else if (!empty($logo)) {
    $logo_f = explode('/wp-content', $logo, 2);
    if (count($logo_f) == 2) {
        $logo_path = WP_CONTENT_DIR . $logo_f[1];
    }
}

if (empty($logo)) {
    $logo_path = WP_CONTENT_DIR . '/plugins/ci-data-works-arc/images/logo.png';
    $logo = '/wp-content/plugins/ci-data-works-arc/images/logo.png';
}


if (!empty($logo)) {
    if (isset($logo_path) && file_exists($logo_path)) {
        list($width, $height, $type, $attr) = @getimagesize($logo_path);
        $new_height = 320 * $height / $width;
        if ($new_height > 200) {
            $new_height = 200;
        }
    }
    ?>
    <style>
        body.login h1 a {
            background: url(<?php echo $logo . '?' . filemtime($logo_path) ?>) no-repeat center center !important;
            background-size: contain !important;
            -webkit-background-size: contain !important;
            padding: 0 !important;
            width: 320px !important;
            <?php if (isset($new_height)) echo 'height: ' . $new_height . 'px !important;' ?>
        }

        body.login.interim-login h1 a {
            max-height: 40px !important;
        }

        .forgetmenot {
            display: none;
        }
    </style>
<?php
}

if (isset($_REQUEST['show_ip'])) {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
?>
    <style>
        p.message,
        p#backtoblog {
            display: none !important
        }
    </style>
    <script>
        jQuery(function($) {
            $('form').html('Your IP: <?php echo $ip ?>');
        })
    </script>
<?php
} else {
    $more_text = '<br><a href="' . add_query_arg(array('action' => 'lostpassword', 'show_ip' => '1'), wp_login_url()) . '">What is my external IP address?</a>';
    $more_text = apply_filters('tr_below_login_form', $more_text);
?>
    <style>
        p#backtoblog {
            display: none !important
        }

        p#nav a[rel="nofollow"] {
            display: none;
        }
    </style>
    <script>
        jQuery(function($) {
            $('#nav').append('<?php echo ($more_text) ?>');
        })
    </script>
<?php
}
