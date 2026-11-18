<?php
global $wpdb;
global $blog_id;

$labs = $wpdb->get_results("select dname from ci_batch_billings group by dname order by dname");

$last_batch_id = $wpdb->get_var("select max(batch_id) as m from ci_batches");
?>

<h2>Generate Report</h2>
<?php Tr_Session_Class::show()?>
<form method="post">
    <fieldset class="report_name">
        <legend>Report Name</legend>
        <div class="row audit">
            <label><input type="radio" name="report_type" value="audit" checked/> Audit Report</label>
            
            <div class="labs">
                <span>Lab </span>
                <select name="dname">
                    <option value="">All</option>
                    <?php
                    foreach($labs as $lab)
                    {
                        echo '<option value="'.$lab->dname.'">'.$lab->dname.'</option>';
                    }
                    ?>
                </select>
                <label>
                    <input type="checkbox" name="nolab" value="1"/> No Lab Listed
                </label>
            </div>
            
        </div>
        <div class="row dreamaudit">
            <label><input type="radio" name="report_type" value="dreamaudit" /> Dream Audit Report</label>
            
            <div class="labs">
                <span>Lab </span>
                <select name="dname2">
                    <option value="">All</option>
                    <?php
                    foreach($labs as $lab)
                    {
                        echo '<option value="'.$lab->dname.'">'.$lab->dname.'</option>';
                    }
                    ?>
                </select>
                <label>
                    <input type="checkbox" name="nolab2" value="1"/> No Lab Listed
                </label>
            </div>
           
        </div>
        <div class="row">
            <label class="block"><input type="radio" name="report_type" value="other"/> Comming soon</label>
        </div>
    </fieldset>

    <div class="row">
        <label>Batch From</label>
        <input type="number" name="batch_from" value="1">
    </div>
    <div class="row">
        <label>Batch To</label>
        <input type="number" name="batch_to" value="">
        <em>Leave this empty if only one batch</em>
        <br>
        <span><b>Last Batch Number</b>: <?php echo $last_batch_id?></span>
    </div>
    <input type="submit" class="button" value="Export"/>
    <input type="hidden" name="tr_action" value="ci_pdf_export_report"/>
</form>
<style>
    form .row, form .rowb{
        padding-top: 0;
    }
    fieldset {
        border: 1px solid #ddd;
        margin: 10px;
        padding: 20px;
    }
    fieldset .row label{
        width:200px;
    }
    form .row{
        min-height: 30px;
    }
    form .row .labs label{
        float: none;
    }
</style>
<script>
    jQuery(function($){
        $('input[name="report_type"]').change(function(){
            $('.labs').hide();
            $('.'+$(this).val()+' .labs').show();
        });
        $('input[value="audit"]').change();
    })
</script>
