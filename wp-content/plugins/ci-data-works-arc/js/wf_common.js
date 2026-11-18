jQuery(document).ready(function($){
	wf_fed_ex_load_packing_method_options();
	jQuery('.packing_method').change(function(){
		wf_fed_ex_load_packing_method_options();
	});
	$('.multiselect.chosen_select').select2();
    $('#woocommerce_wf_shipping_ups_availability').change(function () {
        var $nex = $(this).parents('tr').next('tr');
        if ($(this).val()=='all'){
            $nex.hide();
        }else{
            $nex.show();
        }
    }).change();

});

function wf_fed_ex_load_packing_method_options(){
	pack_method	=	jQuery('.packing_method').val();
	jQuery('#packing_options').hide();
	jQuery('.weight_based_option').closest('tr').hide();
	switch(pack_method){
		case 'per_item':
		default:
			break;
			
		case 'box_packing':
			jQuery('#packing_options').show();
			break;
			
		case 'weight_based':
			jQuery('.weight_based_option').closest('tr').show();
			break;
	}
}