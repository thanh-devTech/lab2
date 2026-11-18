<?php
global $wpdb;
$return = array('status'=>'ok');

include_once(TRCPDD_PATH.'inc/ups/ci_shipping_ups.php');
$ups = new CI_Shipping_UPS();

$txn_id = $_REQUEST['txn_id'];

$order = $wpdb->get_row("select * from wp_wp_eStore_customer_tbl od 
                              JOIN ci_customer_orders co ON co.co_txn_id = od.txn_id
                              where od.txn_id = '{$txn_id}' ", ARRAY_A);

if(empty($order['address_street']))
{
    $location = Tr_Base_Class_V6::get_Lat_LNG($order['facility_address']);
    if($location)
    {
        unset($location['lat']);
        unset($location['lng']);
        $order = array_merge($order,$location);
        $update = $location;
        $wpdb->update('wp_wp_eStore_customer_tbl',$update,array('txn_id'=>$txn_id));
    }
}

$order['ship_length'] = $_POST['length']? $_POST['length'] : $order['ship_length'];
$order['ship_width'] = $_POST['width']? $_POST['width'] : $order['ship_width'];
$order['ship_height'] = $_POST['height']? $_POST['height'] : $order['ship_height'];
$order['ship_weight'] = $_POST['weight']? $_POST['weight'] : $order['ship_weight'];

$package = $ups->create_package($order);


$ups->calculate_shipping($package);
$rates = $ups->rates;
$ship_rates = '';
if(is_array($rates) && count($rates)>0)
{
    foreach($rates as $k  => $rate)
    {
        $rate = (array) $rate;
        $ship_rates.= '<option value="'.$k.'" data-cost="'.$rate['cost'].'">'.$rate['label'].'</option>';
    }
}else{
    $return['alert']='Not found';
    $return['status']='not_found';
}


ob_start();
Tr_Session_Class::show();
$return['msg'] = ob_get_clean();
$return['rates'] = json_encode($rates);
$return['ship_rates'] = $ship_rates;

wp_send_json($return);

