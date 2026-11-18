<?php
global $wpdb,$current_role;
global $ci_all_btypes;
global $access_btypes;

include_once(TRCPDD_PATH.'pages/formdata.php');


$user_id        = get_current_user_id();
$result_dir     = tr_get_result_upload_dir();


$tabs = array();
foreach($access_btypes as $btype)
{
	$filepath = trpdd_get_file_path($btype,'patient_chart');
	if(file_exists($filepath) && $filepath != __FILE__)
	{
		$tabs[$btype] = tr_pdd_get_typename($btype).' Chart';
	}

}

$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab])){
    $tab = key($tabs);
}

if(!isset($_REQUEST['trajax'])) {
	?>
    <div class="wrap">
    <h2 class="nav-tab-wrapper">
		<?php

		foreach ( $tabs as $k => $txt ) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link( array(
				'tab'      => $k,
				'act'      => '',
				'id'       => '',
				'patient_id' => '',
				'view'     => '',
				's'        => '',
				'search'   => '',
				'paged'    => '',
				'from'     => '',
				'to'       => '',
				'mrn'      => '',
				'orderby'  => '',
				'order'    => '',
				'assen'    => '',
				'ssn'      => '',
				'dob'      => ''
			), false );
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
		}
		?>
    </h2>


    <h2>Patient Chart</h2>
	<?php Tr_Session_Class::show() ?>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab'] : '' ?>">

        <label>Search by Name</label>
        <input type="text" class="patient_auto" name="s" value="<?php echo isset($_REQUEST['s'])? $_REQUEST['s']: '' ?>"/>

        <label>SSN</label>
        <input type="text" name="ssn" value="<?php echo isset($_REQUEST['ssn'])? $_REQUEST['ssn']: '' ?>"/>

        <label>MRN</label>
        <input type="text" name="mrn" value="<?php echo isset($_REQUEST['mrn'])? $_REQUEST['mrn'] : '' ?>"/>

        <label>Birthday</label>
        <input type="text" class="hasdate" data-write="1" name="dob" value="<?php echo isset($_REQUEST['dob'])? $_REQUEST['dob'] : '' ?>"/>

        <input type="submit" class="button bsty6" value="Search"/>

        <input type="hidden" name="patient_id" id="patient_id" value="<?php echo isset($patient_id)? $patient_id : '' ?>"/>
    </form>
    </div>
	<?php
}


$filepath = trpdd_get_file_path($tab,'patient_chart');
if(file_exists($filepath) && $filepath != __FILE__)
{
	$btype = $tab;
	include_once($filepath);
}
?>

<script>

    jQuery(function($){

        $('.patient_auto').autocomplete({
            source: ajaxurl+'?action=ajax&autocomplete=patient_auto&btype=<?php echo $btype?>',
            minLength: 1,
            select: function( event, ui ) {
                if(ui.item)
                {
                    $('input#patient_id').val(ui.item.id).data('name',ui.item.value);
                    $('input[name="ssn"]').val('');
                    $('input[name="mrn"]').val('');
                }
            }
        }).change(function () {
            if($(this).val()!=$('input#patient_id').data('name')){
                $('input#patient_id').val('');
            }
        }).on('keypress',function () {
            $('[name="patient_mrn"]').val('');
        });
        $('[name="patient_mrn"]').on('keypress',function(){
            $('input#patient_id').val('');
            $('[name="patient"]').val('');
        })

    })
</script>
