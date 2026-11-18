<div class="wrap">
    <?php
    
    include_once(TRCPDD_PATH.'pages/formdata.php');

    //wp_enqueue_script('MonthPicker',TRCPDD_URL. 'js/jquery.monthpicker.min.js',array(),TRCPDD_VS );


    if(isset($_POST['tblci_ap_clms'])){
        $ap = $_POST['tblci_ap_clms'];
        $ap_ID = $ap['ap_ID'];
        
        if($ap_ID > 0)
        {
            $rs = $wpdb->update('ci_ap_clms',$ap,array('ap_ID' => $ap_ID));
            if($rs===false)
            {
                Tr_Session_Class::add_error('Error. update A/P igniteLIS wrong to db!');
            }
            else{
                Tr_Session_Class::add('Updated A/P igniteLIS Successfully!');
            }
        }else{
            $wpdb->insert('ci_ap_clms',$ap);
            $ap_ID = $wpdb->insert_id;
            if($ap_ID>0)
            {
                Tr_Session_Class::add('Created New A/P igniteLIS Successfully!');
            }
            else{
                Tr_Session_Class::add_error('Error. create A/P igniteLIS wrong to db!');
            }
        }

    }
    if($act=='edit' || $act=='add'){

        if($_REQUEST['id']>0)
        {
            $id = $_REQUEST['id'];
            $data = $wpdb->get_row("select * from ci_ap_clms where ap_ID = {$id}",ARRAY_A);
        }

        $ls = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) ");
        $labs = array(''=>'Select Lab');
        foreach($ls as $l)
        {
            $labs[$l->lab_ID] = $l->lab_dba;
        }

        ?>
        <h2><?php echo ($act=='add')? 'Addd New': 'Edit'?> A/P igniteLIS <a href="<?php Tr_Base_Class_V6::link(array('act'=>'','id'=>''))?>" class="add-new-h2">All A/P to igniteLIS</a> </h2>
        <?php Tr_Session_Class::show()?>

        <?php $this->form(array('data'=>$data,'enctype'=>'multipart/form-data'),'tblci_ap_clms','div');?>

        <div class="frow blabel">
            <div class="col2">
                <?php
                $this->input('ap_ID',array('type'=>'hidden'));
                $this->input('ap_date',array('label'=>'Date','required'=>true));
                $this->input('ap_Service_fees',array('label'=>'Service fees'));
                $this->input('ap_Equipment_Lease',array('label'=>'Equipment lease'));
                $this->input('ap_Rent',array('label'=>'Rent'));
                $this->input('ap_BOP_Insurance',array('label'=>'BOP insurance'));
                $this->input('ap_Accreditation',array('label'=>'Accreditation'));
                ?>
            </div>
            <div class="col2">
                <?php
                $this->select('ap_Lab_ID',$labs,array('label'=>'Lab','required'=>true));
                $this->input('ap_Employee_Leasing',array('label'=>'Employee leasing'));
                $this->input('ap_Credentialing',array('label'=>'Credentialing'));
                $this->input('ap_Lab_Director',array('label'=>'Lab director'));
                $this->input('ap_Printing',array('label'=>'Printing'));
                $this->input('ap_Shipping',array('label'=>'Shipping'));
                ?>
            </div>
        </div>

    <?php $this->endform();?>
        <style>
            .ui-datepicker-calendar {
                display: none;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                //$('[name="tblci_ap_clms[ap_date]"]').monthpicker();
                $('[name="tblci_ap_clms[ap_date]"]').datepicker( {
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    dateFormat: 'yy-mm-01',
                    onClose: function(dateText, inst) {
                        var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
                        var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
                        $(this).datepicker('setDate', new Date(year, month, 1));
                    },
                    beforeShow : function(input, inst) {
                        var datestr;
                        if ((datestr = $(this).val()).length > 0) {
                            year = datestr.substring(datestr.length-4, datestr.length);
                            month = jQuery.inArray(datestr.substring(0, datestr.length-5), $(this).datepicker('option', 'monthNamesShort'));
                            $(this).datepicker('option', 'defaultDate', new Date(year, month, 1));
                            $(this).datepicker('setDate', new Date(year, month, 1));
                        }
                    }
                });
            });
        </script>
    <?php
    }
    else {
    if($act=='delete'){
        $ap_ID = $_REQUEST['id'];
        $wpdb->delete('ci_ap_clms',array('ap_ID'=>$ap_ID));
        Tr_Session_Class::add('Deleted A/P igniteLIS #'.$ap_ID);
    }

    $where = '';
    $row_per_page = 100;
    $pagination = new Tr_Pagination_class($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_ap_clms {$where}"));
    $offset = $pagination->offset();

    $sql = "select ap.*,lab.lab_dba_init from ci_ap_clms ap
            Left Join ci_labs lab ON ap.ap_Lab_ID = lab.lab_ID
            {$where}
            order by ap.ap_date desc limit {$offset},{$row_per_page}";

    $aps = $wpdb->get_results($sql);
    ?>
        <h2>A/P to igniteLIS <a href="<?php Tr_Base_Class_V6::link(array('act'=>'add','id'=>''))?>" class="add-new-h2">Add New</a></h2>
    <?php Tr_Session_Class::show()?>
    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Date</th>
                <th>Lab</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($aps as $ap): ?>
                <tr>
                    <td><?php echo $ap->ap_ID ?></td>
                    <td><?php echo $ap->ap_date ?></td>
                    <td><?php echo $ap->lab_dba_init?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $ap->ap_ID)) ?>">Edit</a> -
                        <a class="delete"
                           href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $ap->ap_ID)) ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
        <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
        <script>
            jQuery(function($){
                $('a.delete').click(function(){
                    if(confirm('Do you want delete?')==false)return false;
                })
            })
        </script>
        <?php
    }
    ?>
</div>
