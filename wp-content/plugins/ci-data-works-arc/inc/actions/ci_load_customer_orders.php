<?php
global $wpdb;
$doc_id = $_REQUEST['doc_id'];
$cli_id = $_REQUEST['cli_id'];
$search = isset($_REQUEST['s'])? $_REQUEST['s']: '';
$date_from = isset($_REQUEST['date_from'])? $_REQUEST['date_from']: '';
$date_to   = isset($_REQUEST['date_to'])? $_REQUEST['date_to'] :'';

$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_id} ");

$where = "WHERE od.doc_ID = {$doc_id} and od.cli_ID = {$cli_id} ";
if(!empty($date_from))
{
	$where.= " and (co.ship_date >= '{$date_from}' or od.date >= '{$date_from}') ";
}

if(!empty($date_to))
{
	$where.= " and (co.ship_date <= '{$date_to}' or od.date <= '{$date_to}') ";
}
$orders = $wpdb->get_results("select od.*,co.*, sa.time
                                from wp_wp_eStore_customer_tbl od 
                                LEFT JOIN ci_customer_orders co ON co.co_txn_id = od.txn_id
                                left join wp_wp_eStore_sales_tbl sa ON sa.cust_email = od.email_address 
                                                and sa.date = od.date 
                                                and sa.item_id = od.purchased_product_id 
                                 {$where}
                                GROUP BY od.txn_id 
                                ORDER BY od.date desc");


if(!empty($wpdb->last_error))
{
	echo '<br>';
	echo $wpdb->last_error;
	echo '<br>';
}

if(count($orders)>0)
{
	$ids = array();
	$sender_email = $orders[0]->email_address;
	?>
    <form method="post" class="form_orders">
        <div class="left">
			<?php
			echo 'Clinic Address: ';
			echo $clinic->cli_address.' '.$clinic->cli_city.', '.$clinic->cli_state.' '.$clinic->cli_zip;
			?>
        </div>
        <div class=" right">
			<?php echo $sender_email?>
            <input type="submit" class="button-primary" value="Save"/>
            <input type="button" class="button close_orders" value="Cancel"/>
        </div>
        <br>

        <table class="report_tbl">
			<?php
			foreach ($orders as $order)
			{
				if(empty($order->order_notes))
				{
					$order->order_notes = $order->notes;
				}

				if(!empty($order->time))
				{
					$order->date .= ' '.$order->time;
				}
				if($sender_email!=$order->email_address)
				{
					?>
                    <tr>
                        <td colspan="2" style="text-align: right"><?php echo $order->email_address?></td>
                    </tr>
					<?php
				}
				?>
                <tr>
                    <td style="width:45%;font-weight: 600;background:#dfdfdf!important;"><label>Order</label>
                        <span style="margin-right:10px;"><?php echo tr_pdd_display_date($order->date,'m/d/Y h:i A',true)?></span>
                        <span>#<?php echo $order->id ?></span>
                    </td>
                    <td style="width:55%;background:#dfdfdf!important;">
                        <label>Shipped</label>
                        <input type="text" class="hasdate" name="order[<?php echo $order->txn_id ?>][ship_date]"
                               value="<?php echo $order->ship_date ?>" placeholder="0000-00-00"/>
                        <input type="text" name="order[<?php echo $order->txn_id ?>][ups]" style="width:40%"
                               value="<?php echo esc_attr(stripslashes($order->ups)) ?>" placeholder="UPS0000000"/>
                    </td>
                </tr>
				<?php
				$childs = $wpdb->get_results("select * from wp_wp_eStore_customer_tbl where txn_id = '{$order->txn_id}' ");
				foreach ($childs as $child)
				{
					$ids[] = $child->id;
					?>
                    <tr>
                        <td>
                            <label><?php echo $child->product_name?></label>
                            <span class="order_qty"><?php echo $child->purchase_qty?></span>
                        </td>
                        <td>
                            <label><?php echo $child->product_name?></label>
                            <input type="number" name="ship[<?php echo $child->id?>]" value="<?php echo $child->ship_qty ?>">
                        </td>
                    </tr>
					<?php
				}
				?>

                <tr class="border_bottom">
                    <td>
                        <label>Notes</label>
                        <textarea name="order[<?php echo $order->txn_id ?>][order_notes]"
                                  style="width:70%"><?php echo stripslashes($order->order_notes) ?></textarea>
                    </td>
                    <td>
                        <label>Notes</label>
                        <textarea name="order[<?php echo $order->txn_id ?>][ship_notes]"
                                  style="width:70%"><?php echo stripslashes($order->ship_notes) ?></textarea>
                    </td>
                </tr>

			<?php }

			$ids = implode(',',$ids);
			//$wpdb->query("update wp_wp_eStore_customer_tbl set viewed = 1 where id in ({$ids})");

			?>

        </table>
        <div class="frow right">
            <input type="submit" class="button-primary" value="Save"/>
            <input type="button" class="button close_orders" value="Cancel"/>
        </div>


        <input type="hidden" name="tr_action" value="ci_save_customer_orders"/>
        <input type="hidden" name="doc_id" value="<?php echo $doc_id ?>"/>
        <input type="hidden" name="cli_id" value="<?php echo $cli_id ?>"/>
    </form>
	<?php
}else{
	echo '<div>No orders found</div>';
}
?>