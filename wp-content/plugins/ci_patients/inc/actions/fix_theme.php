<?php
$bridge_dir = WP_CONTENT_DIR.'/themes/bridge';
$files = array(
	'woocommerce/cart/cart-empty.php',
	'woocommerce/cart/cart.php',
	'woocommerce/cart/shipping-calculator.php',
	'woocommerce/cart/cart-shipping.php',
	'woocommerce/checkout/form-coupon.php',
	'woocommerce/checkout/thankyou.php',
	'woocommerce/content-product.php',
	'woocommerce/global/form-login.php',
	'woocommerce/global/quantity-input.php',
	'woocommerce/loop/add-to-cart.php',
	'woocommerce/loop/pagination.php',
	'woocommerce/loop/result-count.php',
	'woocommerce/myaccount/form-login.php',
	'woocommerce/myaccount/form-lost-password.php',
	'woocommerce/myaccount/my-address.php',
	'woocommerce/single-product/add-to-cart/external.php',
	'woocommerce/single-product/add-to-cart/grouped.php',
	'woocommerce/single-product/add-to-cart/simple.php',
	'woocommerce/single-product/add-to-cart/variable.php',
);

foreach($files as $f)
{
	$mfile = $bridge_dir.'/'.$f;
	$tmfile = $bridge_dir.'/'.$f.'_';
	
	if(file_exists($mfile))
	{
		rename($mfile,$tmfile);
		echo 'renamed: '.$mfile.' => '.$tmfile.'<br>';
	}else{
		echo $mfile.': file not exist<br>';
	}
}
echo 'ok';