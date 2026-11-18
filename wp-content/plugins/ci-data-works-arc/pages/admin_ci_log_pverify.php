<div class="wrap">
    <?php
    global $wpdb;
    include_once(TRCPDD_PATH.'pages/formdata.php');
    $time_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

    if($_REQUEST['act']=='del'){
        $wpdb->delete('ci_log_forms',array('id'=>$_REQUEST['id']));
    }

    $where = " and data not like '%assen\":\"\"%' ";
    if(!empty($_REQUEST['s'])){
        $assen = $_REQUEST['s'];
        $where .= " and data like '%\"$assen\"%' ";
    }

    $row_per_page = 200;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_log_forms WHERE form='pverify' {$where}"));
    $offset = $pagination->offset();

    $sql = "select * from ci_log_forms WHERE form='pverify' {$where} ORDER BY created_date desc limit {$offset},{$row_per_page}";
    $logs = $wpdb->get_results($sql);

    $labs_db = $wpdb->get_results("select * from ci_labs");
    $labs = array();
    foreach($labs_db as $l)
    {
        $labs[$l->lab_ID] = $l->lab_dba_init;
    }

    if(isset($_REQUEST['debug'])){
        echo $sql;
        echo '<br>';
        echo $wpdb->last_error;
    }
    ?>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']?>">
        <input type="hidden" name="pagetab" value="<?php echo $_REQUEST['pagetab']?>">
        search:
        <input type="text" name="s" value="<?php echo $_REQUEST['s']?>">
        <input type="submit" class="button bsty6" value="Search"/>
    </form>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>

    <div class="wraptbl">
    <table class="wp-list-table widefat striped pages blue_tbl hover_tbl">
        <thead>
        <tr>
            <th>id</th>
            <th>Accession</th>
            <th>Log</th>
            <th>Time</th>
            <th>Batch Status</th>
            <th>Ref</th>
            <th style="width:150px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach($logs as $log)
        {
            $data = json_decode($log->data,true);
            $assen = $data['assen'];
            $mid = $data['mid'];
            if($mid==0)
            {
                $mid = $log->target_id;
            }

            ?>
            <tr>
                <td><?php echo $mid?></td>
                <td><?php echo $assen?></td>
                <td>
                    <a href="?tr_action=ci_xml_pverify_log&id=<?php echo $log->id?>&type=Request">Request XML</a> -
                    <?php
                    if(!empty($data['rsuid'])){
                        ?>
                        <a href="?tr_action=ci_xml_pverify_log&id=<?php echo $log->id?>&type=VerifyEligibility">VerifyEligibility XML</a>
                        <?php
                    }
                    //var_export($log->data['result']);
                    if(empty($data['result']) || is_string($data['result'])){
                        echo $data['result'];
                    }else{
                        ?>

                         - <a href="?tr_action=ci_xml_pverify_log&id=<?php echo $log->id?>">GetEligibilitySummaryFULL XML</a>
                    <?php
                        if(stripos($log->data,'No data available.')!==false)
                        {
                            echo ' => No data available.';
                        }
                    }
                    ?>
                </td>
                <td title="<?php echo $log->id?>"><?php echo $log->created_date ?></td>
                <td><?php echo $data['status']?></td>
                <td><?php echo $labs[$data['ref_lab_ID']]?></td>
                <td>
                    <a class="thickbox" href="?tr_action=ci_xml_pverify_run&mid=<?php echo $mid?>&logid=<?php echo $log->id?>&TB_iframe=true">test</a>
                    -  <a class="thickbox" href="?tr_action=ci_xml_pverify_run&need_update=1&mid=<?php echo $mid?>&logid=<?php echo $log->id?>&TB_iframe=true">Re run</a>
                    <?php if(isset($_REQUEST['debug'])):?>

                        -  <a  href="<?php Tr_Base_Class_V6::link(array('act'=>'del','id'=>$log->id))?>">del</a>
            <?php endif;?>
                </td>
            </tr>
            <?php
        }
        if(count($logs)==0)
        {
            ?>
            <tr>
                <td colspan="3">Not found</td>
            </tr>
        <?php
        }
        ?>
        </tbody>
    </table>
    </div>

    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
</div>