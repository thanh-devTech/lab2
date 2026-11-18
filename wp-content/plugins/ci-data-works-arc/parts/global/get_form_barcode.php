<?php

$moretext = '';
if (isset($bat['patient_name'])) {
    $moretext .= $bat['patient_name'];
}

if (isset($bat['dob'])) {
    $moretext .= " " . date('m/d/Y', strtotime($bat['dob']));
}

$arr = array(
    'text'     => isset($bat['assen']) ? $bat['assen'] : '',
    'moretext' => $moretext,
    'type'     => "code128b",
    'imgtype'  => 'png',
    'height'   => 60,
    'width'    => 200,
    'w'        => 200,
    'return_image' => true,
);
$url_img = '';
$img = apply_filters('barcode_img', '', $arr);

if ($img && !is_string($img)) {
    ob_start();
    imagepng($img);
    $image_data = ob_get_clean();
    $url_img = 'data:image/png;base64,' . base64_encode($image_data);
} else if (is_string($img) && !empty($img)) {
    $url_img = $img;
}

if (!empty($url_img)) {
    if (isset($barcode_at_top) && $barcode_at_top) {
        echo '<div style="float:right;margin-top:-65px">';
    } else {
        echo '<div style="float:right;">';
    }
    echo '<img src="' . $url_img . '" alt="' . $bat['assen'] . '" />';
    echo '</div>';
}
