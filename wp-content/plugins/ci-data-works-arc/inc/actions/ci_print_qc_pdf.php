<?php
global $wpdb;
$orientation = 'landscape'; //portrait

$images = $_POST['images'];
$titles = $_POST['titles'];
$lab_id = $_POST['lab_id'];
if (empty($lab_id)) {
    $user_id = get_current_user_id();
    $lab_id = CI_Base_Lab::get_user_lab_id($user_id);
}
$image_size = 0;
$instrument = $_REQUEST['instrument'];
if (!empty($instrument)) $instrument = '-' . $instrument;
$date = date('F Y', strtotime($_GET['month'] . '-01'));
$report_title = 'Levey-Jennings QC Graphs ' . $instrument . ' - ' . $date;

$lab = $wpdb->get_row("select * from ci_labs WHERE lab_ID = {$lab_id}", ARRAY_A);
$html = '<html class="' . $orientation . '">
            <head>
            <style>
                @page{margin:30px}
                body{font-family: Arial, Helvetica, sans-serif; font-size: 12px;}
                .landscape body{margin:0;}
                table{ width:100%;}
                td.width50{width:50%}
                td.width100{width:100%;text-align:center;}
                td.width50 img.chartimg{width:300px; }
                td.width100 img.chartimg{ width:780px;background:#fff}
                td.pos_left img.chartimg{margin-bottom:40px;}
                .break {page-break-after: always;border: 0; }
                .title{font-size:12px;margin:0;font-weight: normal;}
                .legend{font-size:10px;text-align:center;margin-bottom:5px;}
            </style>
            </head>
            <body>';



$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos/';
$logo_url = $upload_dir['baseurl'] . '/logos/';
$logo_path = $logo_dir . $lab['lab_logo'];
if (file_exists($logo_path)) {
    $size = getimagesize($logo_path);
    $html .= '<div style="text-align:center"><img border="0" style="width:300px; height: auto;margin-top:50px" src="' . $logo_url . $lab['lab_logo'] . '"/></div>';
    $html .= '<div style="text-align:center;margin-top:20px"><h2>' . $report_title . '</h2></div>';
    $html .= '<div class="break"></div>';
}

$countbr = 0;

$tmpfname = tempnam("/tmp", "image.jpg");

foreach ($images as $k => $img) {
    $title = $titles[$k];
    $image_size += strlen($img);

    if ($orientation == 'portrait') {
        $img = tr_get_image_from_base64($img);
        file_put_contents($tmpfname, $img);


        list($width, $height, $type, $attr) = getimagesize($tmpfname);
        $img_c = imagecreatefromjpeg($tmpfname);
        $max_h = $orientation == 'portrait' ? 300 : 600;
        $max_w = $max_h * $width / $height;
        $text_height = 44;
        $imgnew = imagecreatetruecolor($max_w, $max_h + $text_height);

        $white = imagecolorallocate($imgnew, 255, 255, 255);
        $black = imagecolorallocate($imgnew, 0, 0, 0);
        $font = TRCPDD_PATH . '/fonts/arial.ttf';

        imagefill($imgnew, 0, 0, $white);

        imagecopyresampled($imgnew, $img_c, 0, $text_height, 0, 0, $max_w, $max_h, $width, $height);



        //write title
        list($left,, $right) = imageftbbox(18, 0, $font, $title);
        $dwidth = $right - $left;
        $rs = imagettftext($imgnew, 18, 0, $max_w / 2 - $dwidth / 2, 18, $black, $font, $title);


        //write desc
        $desc = 'o Point     ... 2 Sigma    --- 1 Sigma     ---  -1 Sigma     ... -2 Sigma';
        list($left,, $right) = imageftbbox(10, 0, $font, $desc);
        $dwidth = $right - $left;
        imagettftext($imgnew, 10, 0, $max_w / 2 - $dwidth / 2, 40, $black, $font, $desc);


        if ($orientation == 'portrait') {
            $imgnew = imagerotate($imgnew, 90, 0);
        }


        ob_start();

        imagejpeg($imgnew, null, 90);
        imagedestroy($imgnew);
        imagedestroy($img_c);

        $img = ob_get_clean();
    } else {

        $img = tr_get_image_from_base64($img);
        $source_image = imagecreatefromstring($img);
        ob_start();
        imagejpeg($source_image, null, 80);
        $img = ob_get_clean();
        // 5. Free up memory by destroying the image resource
        imagedestroy($source_image);
    }



    if ($countbr % 2 == 0) {
        if ($countbr > 0) $html .= '</tr></table>' . "\n";
        $html .= '<table style="page-break-after: always" ><tr>';
        $align = 'left';
    } else {
        $align = 'right';
    }
    if ($orientation == 'portrait') {
        $html .= '<td class="width50" style="vertical-align: middle;text-align: ' . $align . ';height: 900px">';
        $html .= '<img class="chartimg" src="' . 'data:image/jpeg;base64,' . base64_encode($img) . '"/>';
        $html .= '</td>';
    } else {
        $html .= '<td class="width100 pos_' . $align . '">';
        $html .= '<h3 class="title">' . $title . '</h3>';
        $html .= '<div class="legend">o Point     ... 2 Sigma    --- 1 Sigma     ---  -1 Sigma     ... -2 Sigma</div>';
        $html .= '<img class="chartimg" src="' . 'data:image/jpeg;base64,' . base64_encode($img) . '"/>';
        $html .= '</td></tr>';
    }

    $countbr++;

    // if ($countbr > 5) break;
}

$html .= '</tr></table></body></html>';

//echo $html;exit;




$date = date_i18n('M-y', strtotime($_GET['month'] . '-01'));
$filename = 'Levey-Jennings-QC' . $instrument . '-' . $date . '.pdf';
$download = isset($_GET['dbtime']) ? 0 : 1;
$config = array(
    'orientation' => $orientation,
    'filename' => $filename,
    'download' => $download,
    'alwaysdownload' => $download,
);

if (isset($_GET['dbtime'])) {
    $filepath = '/tmp/' . $filename;
    unset($config['filename']);
    $config['filepath'] = $filepath;
}
$start_time = microtime(true);
trpdd_build_pdf($html, $config);

if (isset($_GET['dbtime'])) {
    unlink($filepath);
    echo 'PDF generated in ' . round(microtime(true) - $start_time, 2) . ' seconds. ' . "\n";
    echo 'size: ' . round($image_size / 1024, 2) . ' KB';
}

exit;
