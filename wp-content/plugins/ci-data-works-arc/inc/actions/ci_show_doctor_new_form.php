<?php
global $wpdb;
$cli_id = $_REQUEST['cli_id'];

$sql = "select doc.* from ci_doc doc
        join ci_custom_providers cp ON cp.doc_ID = doc.doc_ID
        JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
        where cd.cli_ID = {$cli_id} group by doc.doc_ID";
$docs = $wpdb->get_results($sql);
?>

<div class="wrap">
    <a class="button use_new_doctor">Are you sure you want to add a new Provider to this Clinic</a>
    <div>or pick one from below?</div>
</div>
<hr>
<ol class="select_doctor_list">
    <?php foreach($docs as $doc):?>
    <li><a data-id="<?php echo $doc->doc_ID?>"><?php echo $doc->doc_last.', '.$doc->doc_first?></a></li>
    <?php endforeach;?>
</ol>
<script>
    jQuery(function($){
        $('.select_doctor_list a').click(function(){
            $('[name="bat[requesting_physician]"]').val($(this).html());
            $('[name="bat[doc_ID]"]').val($(this).data('id'));
            tb_remove();
        })
    })
</script>