<?php
global $wpdb,$current_role;
$user_id = get_current_user_id();

$enable_pcr_panel   = tr_get_option('ci_pdf_data','e_ful_panels');
$all_types          = array();

foreach(tr_pdd_get_batch_types() as $btype)
{
	if($btype=='tox'){
		$all_types['tox_oral'] = 'Oral';
		$all_types['tox_urine'] = 'Urine';
	}else if($btype=='pcr' && $enable_pcr_panel) {
		$all_types['pcr_uri'] = 'URI';
		$all_types['pcr_uti'] = 'UTI';
		$all_types['pcr_wound'] = 'Wound';
		$all_types['pcr_covid'] = 'Covid-19';
	}
    else {
		    $all_types[$btype] = tr_pdd_get_typename($btype);

    }

}


include_once(TRCPDD_PATH.'pages/formdata.php');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );

$search = isset($_REQUEST['s'])? $_REQUEST['s']: '';
$date_from = isset($_REQUEST['date_from'])? $_REQUEST['date_from']: '';
$date_to   = isset($_REQUEST['date_to'])? $_REQUEST['date_to'] :'';

//check empty doctor or clinic
$orders = $wpdb->get_results("select * from wp_wp_eStore_customer_tbl where doc_ID = 0 or cli_ID = 0 group by txn_id");
foreach($orders as $o)
{
	$update = array();
	if($o->cli_ID ==0)
	{
		$cid = $wpdb->get_var("select cID from ci_clinic where cli_clinic = '{$o->facility_name}'");
		if($cid==0)
		{
			$wpdb->insert('ci_clinic',array(
                'cli_clinic'=>$o->facility_name,
                'cli_status'=>0,
                'cli_lab-ID'=> CI_Base_Lab::get_current_lab_id(),
                'cli_street'=>$o->facility_address
            ));
			$cid = $wpdb->insert_id;
		}
		$update['cli_ID'] = $cid;
		$o->cli_ID = $cid;
	}

	if($o->doc_ID == 0){
		$did = $wpdb->get_var("select doc_ID from ci_doc where doc_first = '{$o->first_name}' and doc_last='{$o->last_name}'");
		if($did==0)
		{
			$wpdb->insert('ci_doc',array('doc_first'=>$o->first_name,'doc_last'=>$o->last_name,'doc_email'=>$o->email_address,'doc_phone'=>$o->phone,'cli_ID'=>$o->cli_ID));
			$did = $wpdb->insert_id;

			$wpdb->insert('ci_clinic_doc',array('doc_ID'=>$did,'cli_ID'=>$o->cli_ID));
		}
		$update['doc_ID'] = $did;
		$o->doc_ID = $did;
	}
	if(count($update)>0)
	{
		$wpdb->update('wp_wp_eStore_customer_tbl',$update,array('txn_id'=>$o->txn_id));
	}
}

$joins = '';
$where = '';

if(!empty($search))
{
	//search clinic , doctor
	$where.= " and ( cli.cli_clinic like '%{$search}%' or doc.doc_first like '%{$search}%' or doc.doc_last like '%{$search}%') ";
}


if(!empty($search) || !empty($date_from) || !empty($date_to))
{
	$joins.= " JOIN ci_doc doc
    JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID 
    JOIN ci_clinic  cli ON cd.cli_ID = cli.cID and cli.`cli_lab-ID` = lab.lab_ID ";
}

if(!empty($date_from) || !empty($date_to))
{
	//$joins.= " left JOIN ci_customer_orders co ON doc.doc_ID = co.doc_ID ";
	//$joins.= " left join wp_wp_eStore_customer_tbl od ON od.doc_ID = doc.doc_ID ";
	if(!empty($date_from))
	{
		//$where.= " and (co.ship_date >= '{$date_from}' or od.date >= '{$date_from}') ";
	}

	if(!empty($date_to))
	{
		//$where.= " and (co.ship_date <= '{$date_to} 23:59:59' or od.date <= '{$date_to} 23:59:59') ";
	}
}

if(CI_Base_Lab::has_user_labs())
{
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
	$where.= " and lab.lab_id  in ({$lab_str}) ";
}

$sql = "select lab.* from ci_labs  lab
        {$joins}
        where (lab.lab_is_ref = 0 or lab.lab_is_ref = 2)
         and lab.lab_status = 1 {$where} 
        GROUP BY lab.lab_id
        ORDER BY lab.lab_dba_init ASC ";

$results = $wpdb->get_results($sql);

if(isset($_REQUEST['debug'])){
	echo $sql;
}

?>
<h2>Fulfillment</h2>

<?php Tr_Session_Class::show()?>

<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <label>Clinic/Doctor: </label>
    <input type="search" name="s" id="s" value="<?php echo isset($_REQUEST['s'])? $_REQUEST['s'] : ''?>"/>
    <label>From: </label>
    <input type="search" name="date_from" id="from" value="<?php echo $date_from?>" autocomplete="off"/>
    <label>To: </label>
    <input type="search" name="date_to" id="to" value="<?php echo $date_to?>" autocomplete="off"/>
    <input type="submit" value="Search" class="button bsty6"/>
</form>
<br>
<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
        <thead class="sticky">
        <tr>
            <th style="width:30px"></th>
            <th style="width:40px">#</th>
            <th style="width: 15%;">Lab/Clinic/Doctor</th>
            <th style="width:15%">Office phone</th>
			<?php
            foreach($all_types as $btype => $txt)
			{
				echo '<th style="width:6%">'.$txt.'</th>';
			}
			?>
            <th style="width:12%">Rep</th>
            <th style="width:12%">Rep Phone</th>
            <th>Orders</th>
        </tr>
        </thead>
        <tbody>
		<?php
		if(count($results)>0)
		{
			foreach($results as $row)
			{
				?>
                <tr class="parent" data-p="<?php echo $row->lab_ID?>">
                    <td><i></i> </td>
                    <td></td>
                    <td colspan="<?php echo 4+count($all_types)?>"><?php echo $row->lab_dba_init?></td>
                    <td></td>
                </tr>
                <tr class="child p_<?php echo $row->lab_ID?>">
                    <td colspan="<?php echo 7+count($all_types)?>" class="nopadding"></td>
                </tr>
				<?php
			}
		}else{
			?>
            <tr>
                <td colspan="<?php echo 7+count($all_types)?>">No Results Found.</td>
            </tr>
			<?php
		}



		?>
        </tbody>
    </table>
</div>

<style>
    .child_orders label{width:30%}
    .report_tbl .dashicons-dismiss, .is_red{
        color:red !important;
    }
    .report_tbl .dashicons-yes{
        color: green;
    }
    .report_tbl .dashicons.grey{
        color: #464646;
    }
    .report_tbl .dashicons.yellow{
        color: yellow;
    }
</style>
<script>
    jQuery(function($) {
        $( "#from" ).datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function( selectedDate ) {
                $( "#to" ).datepicker( "option", "minDate", selectedDate );
            }
        });
        $( "#to" ).datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function( selectedDate ) {
                $( "#from" ).datepicker( "option", "maxDate", selectedDate );
            }
        });

        function trcpp_lab_results($lab_id,$offset)
        {
            var $obj = $('tr.p_'+$lab_id+' > td');
            if($obj.html()!='' && $offset==0)
            {
                return;
            }else{
                $obj.append('<div class="loading center"></div>');
            }
            $.ajax({
                data:{tr_action:'ci_load_customer_by_lab',lab_id:$lab_id,'offset':$offset},
                type:'post',
                success:function(rs)
                {
                    $('.loadmorediv',$obj).remove();
                    $('.loading',$obj).remove();
                    var $html = $(rs);
                    $obj.append($html);
                    $.tr_setup_the_form();
                },
                error:function(){
                    setTimeout(function () {
                        $('.loading',$obj).remove();
                        trcpp_lab_results($lab_id,$offset);
                    },1000);
                }
            })
        }

        function trcpp_customer_orders($cli_id,$doc_id) {
            var $obj = $('tr.order_'+$cli_id+'_'+$doc_id+' td');
            if($obj.html()!='')
            {
                return;
            }else{
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data:{tr_action:'ci_load_customer_orders',cli_id:$cli_id,doc_id:$doc_id},
                type:'post',
                success:function(rs)
                {
                    var $html = $(rs);
                    $obj.html($html);
                    $('textarea',$html).change();
                    $.tr_setup_the_form();
                },
                error:function(){
                    setTimeout(function () {
                        trcpp_customer_orders($cli_id,$doc_id);
                    },1000);
                }
            })
        }
        $('body').on('click','a.loadmore',function () {
            if($(this).html()=='Loading...')return false;
            var $offset = $(this).data('off');
            var $lab_id = $(this).data('lab_id');
            $(this).html('Loading...');
            trcpp_lab_results($lab_id,$offset);
        }).on('click','tr.parent',function(){
            $('tr.child').hide();
            if($(this).data('showed')!='1')
            {
                var $lab_id = $(this).data('p');
                $('tr.p_'+$lab_id).show();
                $('tr.parent').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                setCookie('customer_rs',$(this).data('p'));
                var $obj = $('tr.p_'+$lab_id);
                $('body,html').scrollTop($obj.offset().top-80);
                trcpp_lab_results($lab_id,0);
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        }).on('click','tr.parent_c',function(){
            $('tr.child_c').hide();
            if($(this).data('showed')!='1')
            {
                $('tr.p_'+$(this).data('p')).show();
                $('tr.parent_c').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                //setCookie('customer_rs',$(this).data('p'));
                var $obj = $('tr.p_'+$(this).data('p'));
                $('body,html').scrollTop($obj.offset().top-150);
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        }).on('click','a.doc_order',function () {
            var $doc_id = $(this).data('doc_id');
            var $cli_id = $(this).data('cli_id');
            $('tr.child_orders').hide();
            $('tr.order_'+$cli_id+'_'+$doc_id).show();
            trcpp_customer_orders($cli_id,$doc_id);
            return false;
        }).on('click','.close_orders',function () {
            $('tr.child_orders').hide();
        }).on('click','a.generate_ups_ship',function () {
            var $this = $(this);
            var $pr = $(this).parents('.order_box');
            var $txn_id = $pr.data('id');
            var $se = $pr.find('.ship_rates');
            var $weight = $('.ship_weight',$pr).val();
            var $length = $('.ship_length',$pr).val();
            var $width = $('.ship_width',$pr).val();
            var $height = $('.ship_height',$pr).val();
            if($weight=='' || $length==''|| $width=='' || $weight==''){
                alert('Please enter box size and weight');
                return false;
            }
            $url = '?tr_action=ci_generate_ups_ship&txn_id='+$txn_id+'&ship_weight='+$weight+'&ship_length='+$length+'&ship_width='+$width+'&ship_height='+$height;
            tb_show('UPS',$url);
            return false;

        }).on('submit','form.form_orders',function () {
            var $this = $(this);
            var $btn = $this.find('[type="submit"]');
            $btn.val('Saving...');
            $.ajax({
                data: $this.serialize(),
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    if(rs.status=='ok'){

                    }else{
                        alert('Error. Please try again');
                    }
                    $btn.val('Save');
                },
                error:function(){
                    alert('Error. Please try again');
                    $btn.val('Save');
                }
            })
            return false;
        }).on('change keyup','textarea',function () {
            if(this.scrollHeight>60)
            {
                $(this).css('height',this.scrollHeight+'px');
            }

        })
        $old_active = getCookie('customer_rs');
        if($old_active && $old_active!=null)
        {
            var $obj = $('tr.p_'+$old_active);
            try
            {
                $obj.show();
                $('[data-p="'+$old_active+'"]').data('showed','1');
                if($old_active.indexOf('c_')>-1)
                {
                    $obj.parents('.child').show();
                    $old_active = $obj.data('labid');
                }
                $('body,html').scrollTop($obj.offset().top-150);
                trcpp_lab_results($old_active,0);
            }catch(e){}

        }else if($('tr.parent').length<2){
            $obj = $('tr.child');
            $obj.show();
            $('tr.parent').data('showed','1');
            $('body,html').scrollTop($obj.offset().top-150);
            setTimeout(function () {
                trcpp_lab_results($('tr.parent').data('p'),0);
            },500);
        }
    });

</script>