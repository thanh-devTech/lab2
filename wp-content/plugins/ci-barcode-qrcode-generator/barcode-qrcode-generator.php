<?php
/*
Plugin Name: CI Barcode QRcode Generator
Plugin URI:
Description: This plugin adds the functionality to output barcodes and qrcodes by use of the shortcodes.
Author:
Author URI:
Version: 1.3.8
Text Domain: barcode-qrcode-generator
Domain Path: /
*/


define('TRBQG_PATH', plugin_dir_path(__FILE__));
define('TRBQG_URL', plugins_url('/', __FILE__));

class barcode_qrcode_generator
{
	public function __construct()
	{
		add_filter('barcode', array(&$this, 'filter_barcode'), 10, 2);
		add_filter('barcode_img', array(&$this, 'filter_barcode_img'), 10, 2);
		add_shortcode('barcode', array(&$this, 'barcode_qrcode_generator_barcode_add_shortcode'));
		add_shortcode('qrcode', array(&$this, 'barcode_qrcode_generator_qrcode_add_shortcode'));
	}

	function filter_barcode_img($default = '', $attr = array())
	{
		$attr['return_image'] = true;
		return $this->filter_barcode($default, $attr);
	}

	function filter_barcode($default = '', $attr = array())
	{
		if (is_array($default)) {
			$attr = $default;
		}
		$attr = shortcode_atts(array(
			'text'     => '',
			'moretext' => '',
			'number' => '',
			'type'     => "ean13",
			'imgtype'  => 'png',
			'height'   => 60,
			'width'    => 1,
			'showtext' => 1,
			'rotation' => 0,
			'transparency' => 0,
			'remake'   => 0,
			'return_image' => false
		), $attr);

		if (empty($attr['text'])) {
			unset($attr['text']);
		}

		if (isset($attr['return_image']) && $attr['return_image']) {
			return include_once(TRBQG_PATH . 'image.php');
		}

		return add_query_arg($attr, TRBQG_URL . 'image.php');
	}

	function barcode_qrcode_generator_barcode_add_shortcode($attr)
	{
		$uploads = wp_upload_dir();

		extract(shortcode_atts(array(
			'text'     => '',
			'moretext' => '',
			'number' => '',
			'type'     => "ean13",
			'imgtype'  => 'png',
			'height'   => 60,
			'width'    => 1,
			'showtext' => 1,
			'rotation' => 0,
			'transparency' => 0,
			'remake'   => 0
		), $attr));

		if (empty($text)) return;

		if ($showtext === 'false') $showtext = false;
		$showtext = (bool) $showtext;
		if ($transparency === 'false') $transparency = false;
		$transparency = (bool) $transparency;

		$filename = sha1($text . $moretext . $type . $imgtype . $height . $width . $showtext . $rotation . $transparency) . '.' . $imgtype;

		//if ( file_exists($uploads['basedir'].'/'.$filename) && empty($remake) )
		//	return '<img src="'.esc_attr($uploads['baseurl'].'/'.$filename).'" height="'.esc_attr($height).'" alt="'.esc_attr($text).'" />';

		$inc =  ini_get('include_path');
		ini_set('include_path', $inc . ':' . dirname(__FILE__));

		require_once('Image/Barcode2.php');
		$code = new Image_Barcode2();
		$img = $code->draw($text, $type, $imgtype, false, $height - 20, $width, $showtext, $rotation, $moretext, $number);
		if (!empty($transpacenry)) imagecolortransparent($img, imagecolorallocate($img, 255, 255, 255));

		switch ($imgtype):
			case 'gif':
				imagegif($img, $uploads['basedir'] . '/' . $filename);
				break;
			case 'jpg':
				imagejpeg($img, $uploads['basedir'] . '/' . $filename);
				break;
			case 'png':
				imagepng($img, $uploads['basedir'] . '/' . $filename);
				break;
		endswitch;


		$txtheight = ($rotation == 90) ? 'width' : 'height';
		return '<img src="' . esc_attr($uploads['baseurl'] . '/' . $filename) . '" ' . $txtheight . '="' . esc_attr($height) . '" alt="' . esc_attr($text) . '" />';
	}

	function barcode_qrcode_generator_qrcode_add_shortcode($attr)
	{
		$uploads = wp_upload_dir();

		extract(shortcode_atts(array(
			'text'     => '',
			'eclevel'  => 3,
			'height'   => 60,
			'width'    => 60,
			'transparency' => 0,
			'remake'   => 0
		), $attr));

		if (empty($text)) return;

		$filename = sha1($text . $eclevel . $height . $width . $transparency) . '.png';

		if (file_exists($uploads['basedir'] . '/' . $filename) && empty($remake))
			return '<img src="' . esc_attr($uploads['baseurl'] . '/' . $filename) . '" height="' . esc_attr($height) . '" width="' . esc_attr($width) . '" alt="' . esc_attr($text) . '" />';

		require_once(__DIR__ . '/vendor/autoload.php'); // QRcode -> endroid/qr-code shim (qrcode-compat.php)

		QRcode::png($text, $uploads['basedir'] . '/' . $filename, 3, 3, 4, false, 0xFFFFFF, 0x000000, $height, $width, $transparency);

		return '<img src="' . esc_attr($uploads['baseurl'] . '/' . $filename) . '" height="' . esc_attr($height) . '" width="' . esc_attr($width) . '" alt="' . esc_attr($text) . '" />';
	}
}
$barcode_qrcode_generator = new barcode_qrcode_generator();
