<?php
global $wpdb;

include_once(TRCPDD_PATH.'inc/ups/ci_shipping_ups.php');
$ups = new CI_Shipping_UPS();

$txn_id = $_REQUEST['txn_id'];

$update = array();
$update['ship_length'] = $_REQUEST['ship_length'];
$update['ship_width'] = $_REQUEST['ship_width'];
$update['ship_height'] = $_REQUEST['ship_height'];
$update['ship_weight'] = $_REQUEST['ship_weight'];

$co_ID = $wpdb->get_var("select co_ID from ci_customer_orders where co_txn_id = '{$txn_id}'");
if($co_ID>0)
{
    $wpdb->update('ci_customer_orders',$update,array('co_txn_id'=>$txn_id));
}
else{
    $update['co_txn_id'] = $txn_id;
    $update['author_ID'] = get_current_user_id();
    $od = $wpdb->get_row("select doc_ID,cli_ID from wp_wp_eStore_customer_tbl where txn_id = '{$txn_id}' limit 1");
    $update['doc_ID'] = $od->doc_ID;
    $update['cli_ID'] = $od->cli_ID;
    $wpdb->insert('ci_customer_orders',$update);
}

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
        $wpdb->update('wp_wp_eStore_customer_tbl',$location,array('txn_id'=>$txn_id));
    }
}


$package = $ups->create_package($order);
$ups->calculate_shipping($package);

?>
<?php Tr_Session_Class::show()?>

<form method="post" id="ship_ups_methods">
    <table style="width: 100%;">
        <tr>
            <td style="text-align: right;">Ship Type: </td>
            <td>
                <select name="ship[ship_type]" class="ship_rates">
                    <?php
                    foreach($ups->rates as $k  => $rate)
                    {
                        $rate = (array) $rate;
                        echo '<option value="'.$k.'" data-cost="'.$rate['cost'].'">'.$rate['label'].' ($ '.$rate['cost'].')'.'</option>';
                    }
                    ?>
                </select>
                <input type="hidden" name="ship[rates]" value="<?php echo esc_attr(json_encode($ups->rates))?>"/>
            </td>
        </tr>
        <tr>
            <td style="text-align: right;">Cost($): </td>
            <td>
                <input type="text" readonly class="ship_cost" name="ship[ship_cost]" style="border:0">
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <div>
                    <?php if($ups->rates):?>
                        <input type="submit" class="button-primary" value="Confirm Shipment"/>
                        <input type="hidden" name="tr_action" value="ci_confirm_shipment_ups"/>
                        <input type="hidden" name="txn_id" value="<?php echo $txn_id?>"/>
                    <?php endif;?>
                </div>
            </td>
        </tr>
    </table>


</form>

<script>
    jQuery(function($){
        $('#ship_ups_methods').submit(function () {
            var $this = $(this);
            var $pr = $('.order_box[data-id="<?php echo $txn_id?>"]');
            $.ajax({
                data:$(this).serialize(),
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    $('.ups_ship_id',$pr).val(rs.ship_id);
                    $('.ship_ups_label',$pr).html(rs.ship_type);
                    $('.generate_ups_ship',$pr).hide();
                    $('.print_label',$pr).show();
                    $this.html(rs.html);
                },
                error:function(){
                    alert('Error. Please try again');
                }
            })
            return false;
        })
        $('select.ship_rates').change(function () {
            var $cost = $(':selected',$(this)) && $(':selected',$(this)).data('cost')? $(':selected',$(this)).data('cost'):'';
            $('.ship_cost').val($cost);
        }).change();
    })
</script>
