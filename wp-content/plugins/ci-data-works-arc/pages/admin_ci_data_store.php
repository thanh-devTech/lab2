

<link type="text/css" rel="stylesheet" href="<?php echo WP_ESTORE_URL?>/wp_eStore_style.css?ver=7.1.9" />
<link type="text/css" rel="stylesheet" href="<?php echo WP_ESTORE_URL?>/wp_eStore_custom_style.css" />
<link type="text/css" rel="stylesheet" href="/wp-content/plugins/eStore-extra-shortcodes/eStore_extra_shortcode_css.css?ver=4.9.9" />
<style>
    table{
        width:100%;
    }
</style>
<h2>Supply Store</h2>

<div class="wrap">
<?php
$need_show_items = true;
if(isset($_REQUEST['manual_checkout'])){

	$checkout = wp_eStore_on_page_manual_gateway_form_handler();

	if(!stripos($checkout,'is empty'))
    {
	    $need_show_items = false;
    }

    echo $checkout;
}


if($need_show_items) {
    $html = tr_get_option('ci_pdf_data','store_html');
    if(empty($html))
    {
	    $html = '<table border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="width: 74%;">
<div class="eStore_cart_fancy1_header">PCR/MOLECULAR SUPPLIES</div><br>
[wp_eStore_category_products_fancy id=2 style=13 show_price=0 description=0]
<br><br>
<div class="eStore_cart_fancy1_header">Shipping Supplies</div><br>
[wp_eStore_category_products_fancy id=1 style=13 show_price=0 description=0]
</td>
<td valign="top">[wp_eStore_cart_fancy1 comments="1"]</td>
</tr>
</tbody>
</table>';
    }

	echo do_shortcode( stripslashes($html) );
}

return;
?>
</div>