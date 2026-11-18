<?php
class CI_Import{

    var $limit = 500;
    var $from = 0;
    var $data = false;
    var $count = 0;

    public function __construct()
    {
        if($_REQUEST['limit']>0)
        {
            $this->limit = $_REQUEST['limit'];
        }
        $this->get_file_data();
    }

    public function get_form()
    {
        ?>
        <form method="post" enctype="multipart/form-data">
            <label>json file</label>
            <input type="file" name="file"><br>url:
            <input type="url" name="fileurl2" style="width:90%"/><br>
            <label><input type="checkbox" name="onlyinsert" value="1"/> Only insert </label><br>
            <input type="submit" value="Upload"/>
        </form>
        <?php
        if($this->count==$this->limit && !empty($_REQUEST['fileurl2']))
        {
            $url = Tr_Base_Class_V6::link(array('from'=>$this->from+1,'fileurl2'=>$_REQUEST['fileurl2'],'onlyinsert'=>$_REQUEST['onlyinsert'],'limit'=>$this->limit),false);
            ?>
            <meta content="3;url=<?php echo $url?>" http-equiv="refresh" >
            <?php
        }
        exit;
    }

    public function get_file_data()
    {
        $file = false;

        if($_FILES['file']['name']) {
            $file = $_FILES['file']['tmp_name'];
        }else if(!empty($_REQUEST['fileurl2'])){
            $this->from = intval($_REQUEST['from']);
            $file = $_REQUEST['fileurl2'];
            $file.= '&from='.($this->from * $this->limit);
            $file.= '&limit='.$this->limit;
            echo 'url:'.$file.'<br>';
        }

        if($file)
        {
            $this->data = json_decode(file_get_contents($file), ARRAY_A);

            $this->count = count($this->data);
            if($this->count==0)
            {
                $this->data = false;
            }
        }
        echo 'count: '.$this->count.'<br>';
        return $this->data;
    }
    public  function  admin_import_reps()
    {
        global $wpdb;

        if($this->data)
        {
            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_lab_reps add column rep_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_lab_reps");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['rep_ID'];

                unset($row['rep_ID']);

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }
                $row['rep_import_ID'] = $import_id;
                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                $sql3 = $wpdb->prepare("select rep_ID from ci_lab_reps where rep_import_ID = %s ",$import_id);
                $rep_ID = $wpdb->get_var($sql3);
                if($rep_ID>0)
                {
                    $exists++;

                    $wpdb->update('ci_lab_reps',$row,array('rep_ID'=>$rep_ID));

                }else{
                    $rs = $wpdb->insert('ci_lab_reps',$row);
                    $rep_ID = $wpdb->insert_id;
                    $inserted ++;
                }

                if($rep_ID>0)
                {

                }else{
                    $fail++;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';
            exit;
        }
        $this->get_form();
    }

    public function admin_import_users()
    {
        global $wpdb;
        if($this->data)
        {

            $count=0;
            foreach($this->data as $user)
            {

                $role_meta = $user['meta_value'];
                $level_meta = $user['user_level'];
                $metas = $user['metas'];
                unset($user['user_id']);
                unset($user['umeta_id']);
                unset($user['meta_key']);
                unset($user['meta_value']);
                unset($user['user_level']);
                unset($user['metas']);
                unset($user['ID']);
                unset($user['spam']);
                unset($user['deleted']);

                $metas[] = array(
                    'meta_key'=> 'wp_capabilities',
                    'meta_value'=>$role_meta
                );
                $metas[]  = array(
                    'meta_key'=> 'wp_user_level',
                    'meta_value'=>$level_meta
                );

                $u = $wpdb->get_row("select * from {$wpdb->users} where user_email = '{$user['user_email']}' ");

                if(!$u)
                {
                    $rs= $wpdb->insert($wpdb->users,$user);
                    $user_id = $wpdb->insert_id;
                    if($rs==false)
                    {
                        var_dump($user);
                        var_dump($wpdb->last_error);
                        exit;
                    }


                    $count++;
                }else{
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $user_id = $u->ID;


                }

                $old_rep_id = $old_doc_id = $old_col_id = -1;
                foreach($metas as $m)
                {
                    $m['user_id'] = $user_id;
                    $rs =$wpdb->insert($wpdb->usermeta,$m);
                    if(!$rs)
                    {
                        var_dump($m);
                        var_dump($wpdb->last_error);
                        exit;
                    }

                    if($m['meta_key']=='_rep_id'){
                        $old_rep_id = $m['meta_value'];
                    }

                    if($m['meta_key']=='_doc_id'){
                        $old_doc_id = $m['meta_value'];
                    }

                    if($m['meta_key']=='_collector_id'){
                        $old_col_id = $m['meta_value'];
                    }
                }

                //update
                $rs = $wpdb->update($wpdb->usermeta,array('meta_value'=>$role_meta),array('user_id'=>$user_id,'meta_key'=>'wp_capabilities'));
                if($rs===false)
                {
                    $wpdb->insert($wpdb->usermeta,array('meta_value'=>$role_meta,'user_id'=>$user_id,'meta_key'=>'wp_capabilities'));
                }
                $wpdb->update($wpdb->usermeta,array('meta_value'=>$level_meta),array('user_id'=>$user_id,'meta_key'=>'wp_user_level'));
                if($rs===false)
                {
                    $wpdb->insert($wpdb->usermeta,array('meta_value'=>$level_meta,'user_id'=>$user_id,'meta_key'=>'wp_user_level'));
                }
                $wpdb->update($wpdb->users,array('user_pass'=>$user['user_pass']),array('ID'=>$user_id));

                //other meta
                if(stripos($role_meta,'lab_rep')>0){
                    $rep_id = $wpdb->get_var("select rep_ID from `ci_lab_reps` where rep_import_ID = '{$old_rep_id}' or  rep_email='{$user['user_email']}'");
                    if($rep_id>0)
                    {
                        update_user_meta($user_id,'_rep_id',$rep_id);
                    }
                }
                else if(stripos($role_meta,'doctor')>0){
                    $rep_id = $wpdb->get_var("select doc_ID from `ci_doc` where doc_import_ID = '{$old_doc_id}' or doc_email='{$user['user_email']}'");
                    if($rep_id>0)
                    {
                        update_user_meta($user_id,'_doc_id',$rep_id);
                    }
                }
                else if(stripos($role_meta,'collector')>0){
                    $rep_id = $wpdb->get_var("select col_ID from `ci_doc` where col_import_ID = '{$old_col_id}' or  col_email='{$user['user_email']}'");
                    if($rep_id>0)
                    {
                        update_user_meta($user_id,'_collector_id',$rep_id);
                    }
                }
            }

            echo 'inserted: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';

        }
        $this->get_form();
    }

    public function admin_import_clinices()
    {
        global $wpdb;
        if($this->data)
        {

            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_clinic add column cli_import_ID int(11) ");
            $wpdb->query("alter table ci_doc add column doc_import_ID int(11) ");

            $fields = $wpdb->get_col("DESCRIBE ci_clinic");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['cID'];
                $doctors = $row['doctors'];

                unset($row['cID']);
                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }

                if($row['cli_rep-ID']>0)
                {
                    $row['cli_rep-ID'] = $wpdb->get_var("select rep_ID from ci_lab_reps where rep_import_ID = {$row['cli_rep-ID']}");
                }



                $sql3 = $wpdb->prepare("select cID from ci_clinic where (cli_clinic = %s ) 
                            or cli_import_ID = %s",$row['cli_clinic'],$import_id);
                $cid = $wpdb->get_var($sql3);
                if($cid>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    foreach($not_in as $k)
                    {
                        unset($row[$k]);
                    }
                    $row['cli_import_ID'] = $import_id;
                    $wpdb->update('ci_clinic',$row,array('cID'=>$cid));

                }else{
                    foreach($not_in as $k)
                    {
                        unset($row[$k]);
                    }
                    $row['cli_import_ID'] = $import_id;
                    $wpdb->insert('ci_clinic',$row);
                    $cid = $wpdb->insert_id;
                    $inserted ++;
                }

                if($cid>0)
                {

                    foreach($doctors as $doc)
                    {
                        $doc['doc_import_ID'] = $doc['doc_ID'];
                        unset($doc['doc_ID']);

                        $sql = $wpdb->prepare("select doc_ID from ci_doc where (doc_first=%s and doc_last = %s and doc_npi=%s)
                                    or doc_import_ID = %s",$doc['doc_first'],$doc['doc_last'],$doc['doc_npi'],$import_id);
                        $doc_id = $wpdb->get_var($sql);
                        if($doc_id>0)
                        {
                            $wpdb->update('ci_doc',$doc,array('doc_ID'=>$doc_id));
                        }else{
                            $wpdb->insert('ci_doc',$doc);
                            $doc_id = $wpdb->insert_id;
                        }

                        if($doc_id>0)
                        {
                            $wpdb->insert('ci_clinic_doc',array('doc_ID'=>$doc_id,'cli_ID'=>$cid));
                        }else{
                            var_dump($wpdb->last_error);
                            var_dump($doc);exit;
                        }
                    }


                }else{
                    $fail++;
                    //var_dump($wpdb->last_error);
                   // var_dump($row);exit;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo '$fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public function admin_import_collectors()
    {
        global $wpdb;
        if($this->data)
        {
            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_collector add column col_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_collector");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['col_ID'];
                $cIDs = $row['cIDs'];

                unset($row['col_ID']);
                unset($row['cIDs']);

                $row['col_import_ID'] = $import_id;

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }

                if($row['rep_ID']>0)
                {
                    $row['rep_ID'] = $wpdb->get_var("select rep_ID from ci_lab_reps where rep_import_ID = {$row['rep_ID']}");
                }


                $sql3 = $wpdb->prepare("select col_ID from ci_collector where (col_first=%s and col_last=%s and col_phone=%s) 
                or col_import_ID = %s",$row['col_first'],$row['col_last'],$row['col_phone'],$import_id);
                $col_ID = $wpdb->get_var($sql3);
                if($col_ID>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    foreach($not_in as $k)
                    {
                        unset($row[$k]);
                    }

                    $wpdb->update('ci_collector',$row,array('col_ID'=>$col_ID));

                }else{
                    foreach($not_in as $k)
                    {
                        unset($row[$k]);
                    }

                    $rs = $wpdb->insert('ci_collector',$row);
                    $col_ID = $wpdb->insert_id;
                    $inserted ++;
                }

                if($col_ID>0)
                {

                    foreach($cIDs as $cli_ID)
                    {
                        $new_cli_ID = $wpdb->get_var("select cID from ci_clinic where cli_import_ID = {$cli_ID}");
                        if($new_cli_ID>0)
                        {
                            $cli_ID = $new_cli_ID;
                        }
                        $wpdb->insert('ci_clinic_collector',array('cli_ID'=>$cli_ID,'col_ID'=>$col_ID));
                    }

                    //insert rep relationship
                    if($row['rep_ID']>0)
                    {
                        $wpdb->insert('ci_rep_collector',array('rep_ID'=>$row['rep_ID'],'col_ID'=>$col_ID));
                    }
                }else{
                    $fail++;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public  function  admin_import_profiles()
    {
        global $wpdb;
        $sign_dir = tr_get_signature_dir();

        if($this->data)
        {
            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_custom_providers add column cus_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_custom_providers");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['cus_ID'];

                unset($row['cus_ID']);
                $image = !empty($row['img'])? $row['img'] : false;

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }

                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                if($row['cli_ID']>0)
                {
                    $row['cli_ID'] = $wpdb->get_var("select cID from ci_clinic where cli_import_ID = {$row['cli_ID']}");
                }

                if($row['doc_ID']>0)
                {
                    $row['doc_ID'] = $wpdb->get_var("select doc_ID from ci_doc where doc_import_ID = {$row['doc_ID']}");
                }
                if(empty($row['doc_ID'])){
                    continue;
                }

                $row['cus_import_ID'] = $import_id;

                $sql3 = $wpdb->prepare("select cus_ID from ci_custom_providers where cus_import_ID = %s order by cus_ID asc",$import_id);
                $cus_ID = $wpdb->get_var($sql3);
                if($cus_ID>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $wpdb->update('ci_custom_providers',$row,array('cus_ID'=>$cus_ID));

                }else{
                    $rs = $wpdb->insert('ci_custom_providers',$row);
                    $cus_ID = $wpdb->insert_id;
                    $inserted ++;
                }

                if($cus_ID>0)
                {

                    if($image)
                    {
                        $fname =  'provider_'.$cus_ID.'.png';
                        $file_exists = file_exists($sign_dir['path'] . '/' . $fname) && (filesize($sign_dir['path'] . '/' . $fname) > 0);
                        if(!$file_exists)
                        {
                            $fdata = file_get_contents($image);
                            if($fdata)
                            {
                                file_put_contents($sign_dir['path'] . '/' . $fname, $fdata);
                                $image_added++;
                            }

                        }
                    }


                }else{
                    $fail++;
                    //var_dump($wpdb->last_error);
                    //var_dump($row);exit;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'images: '.$image_added.'<br>';
            echo 'fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public  function  admin_import_patients()
    {
        global $wpdb;
        if($this->data)
        {
            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_patients add column pa_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_patients");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['pa_ID'];

                unset($row['pa_ID']);

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }
                $row['pa_import_ID'] = $import_id;
                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                $sql3 = $wpdb->prepare("select pa_ID from ci_patients where pa_import_ID = %s",$import_id);
                $pa_ID = $wpdb->get_var($sql3);
                if($pa_ID>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $wpdb->update('ci_patients',$row,array('pa_ID'=>$pa_ID));

                }else{
                    $rs = $wpdb->insert('ci_patients',$row);
                    $pa_ID = $wpdb->insert_id;
                    $inserted ++;
                }

                if($pa_ID>0)
                {

                }else{
                    $fail++;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public  function  admin_import_insurance()
    {
        global $wpdb;
        if($this->data)
        {

            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_insurances add column ins_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_insurances");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['ins_ID'];

                unset($row['ins_ID']);

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }
                $row['ins_import_ID'] = $import_id;
                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                if($row['pa_ID']>0)
                {
                    $row['pa_ID'] = $wpdb->get_var("select pa_ID from ci_patients where pa_import_ID = {$row['pa_ID']} ");
                }

                $sql3 = $wpdb->prepare("select ins_ID from ci_insurances where ins_import_ID = %s",$import_id);
                $ins_ID = $wpdb->get_var($sql3);
                if($ins_ID>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $wpdb->update('ci_insurances',$row,array('ins_ID'=>$ins_ID));

                }else{
                    $rs = $wpdb->insert('ci_insurances',$row);
                    $ins_ID = $wpdb->insert_id;
                    $inserted ++;
                }

                if($ins_ID==0)
                {
                    $fail++;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public  function  admin_import_reqs()
    {
        global $wpdb;
        if($this->data)
        {

            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_batches add column bat_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_batches");
            $not_in = false;
            $patients = array();
            $clis = array();
            $docs = array();
            $cols = array();
            $inses = array();

            foreach($this->data as $row)
            {
                $import_id = $row['mid'];

                unset($row['mid']);

                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }
                $row['bat_import_ID'] = $import_id;
                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                if($row['patient_id']>0)
                {
                    $old_id = $row['patient_id'];
                    if(!isset($patients[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select pa_ID from ci_patients where pa_import_ID = {$old_id} "));
                        $patients[$old_id] = $new_id;
                    }else{
                        $new_id = $patients[$old_id];
                    }
                    $row['patient_id'] = $new_id;
                }

                if($row['cli_ID']>0)
                {
                    $old_id = $row['cli_ID'];
                    if(!isset($clis[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select cID from ci_clinic where cli_import_ID = {$old_id}"));
                        $clis[$old_id] = $new_id;
                    }else{
                        $new_id = $clis[$old_id];
                    }
                    $row['cli_ID'] = $new_id;
                }

                if($row['doc_ID']>0)
                {
                    $old_id = $row['doc_ID'];
                    if(!isset($docs[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select doc_ID from ci_doc where doc_import_ID = {$old_id}"));
                        $docs[$old_id] = $new_id;
                    }else{
                        $new_id = $docs[$old_id];
                    }
                    $row['doc_ID'] = $new_id;
                }

                if($row['col_ID']>0)
                {
                    $old_id = $row['col_ID'];
                    if(!isset($cols[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select col_ID from ci_collector where col_import_ID = {$old_id}"));
                        $cols[$old_id] = $new_id;
                    }else{
                        $new_id = $cols[$old_id];
                    }
                    $row['col_ID'] = $new_id;
                }

                if($row['insurance1']>0)
                {
                    $old_id = $row['insurance1'];
                    if(!isset($inses[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select ins_ID from ci_insurances where ins_import_ID = {$old_id}"));
                        $inses[$old_id] = $new_id;
                    }else{
                        $new_id = $inses[$old_id];
                    }
                    $row['insurance1'] = $new_id;
                }

                if($row['insurance2']>0)
                {
                    $old_id = $row['insurance2'];
                    if(!isset($inses[$old_id]))
                    {
                        $new_id = intval($wpdb->get_var("select ins_ID from ci_insurances where ins_import_ID = {$old_id}"));
                        $inses[$old_id] = $new_id;
                    }else{
                        $new_id = $inses[$old_id];
                    }
                    $row['insurance2'] = $new_id;
                }

                $sql3 = $wpdb->prepare("select mid from ci_batches where bat_import_ID = %s or assen=%s",$import_id,$row['assen']);
                $mid = $wpdb->get_var($sql3);
                if($mid>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $rs = $wpdb->update('ci_batches',$row,array('mid'=>$mid));

                }else{
                    $rs = $wpdb->insert('ci_batches',$row);
                    $mid = $wpdb->insert_id;
                    $inserted ++;
                }

                if($mid==0)
                {
                    $fail++;
                    var_dump($row);exit;
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';
        }
        $this->get_form();
    }

    public  function  admin_import_results()
    {
        global $wpdb;
        if($this->data)
        {
            $count=0;
            $inserted = 0;
            $wpdb->query("alter table ci_reports add column re_import_ID int(11) ");
            $fields = $wpdb->get_col("DESCRIBE ci_reports");
            $not_in = false;
            foreach($this->data as $row)
            {
                $import_id = $row['id'];
                $results = $row['rs'];
                unset($row['id']);
                unset($row['rs']);

                if($row['created']>0 && is_numeric($row['created']))
                {
                    $row['created_date'] = date('Y-m-d',$row['created']);
                }


                if($not_in===false)
                {
                    $not_in = array();
                    foreach($row as $k =>$vl)
                    {
                        if(!in_array($k,$fields))
                        {
                            $not_in[] = $k;
                        }
                    }
                }
                $row['re_import_ID'] = $import_id;
                foreach($not_in as $k)
                {
                    unset($row[$k]);
                }

                $sql3 = $wpdb->prepare("select id from ci_reports where re_import_ID = %s or (patient_id = %s and batch_id = %s)",
                                $import_id,$row['patient_id'],$row['batch_id']);

                $re_ID = $wpdb->get_var($sql3);

                $is_insert = false;
                if($re_ID>0)
                {
                    $exists++;
                    if($_REQUEST['onlyinsert'])continue;
                    $wpdb->update('ci_reports',$row,array('id'=>$re_ID));

                }else{
                    $rs = $wpdb->insert('ci_reports',$row);
                    $re_ID = $wpdb->insert_id;
                    $inserted ++;
                    $is_insert = true;
                }

                if($re_ID==0)
                {
                    $fail++;
                    var_dump($row);
                    var_dump($wpdb->last_error);
                    exit;
                }else
                {
                    foreach($results as $r)
                    {
                        unset($r['id']);
                        $r['report_id'] = $re_ID;
                        if($is_insert)
                        {
                            $wpdb->insert('ci_results',$r);
                        }else{
                            $rsid = $wpdb->get_var("select id from ci_results where report_id = {$re_ID} and drug='{$r['drug']}' ");
                            if($rsid>0)
                            {
                                $wpdb->update('ci_results',$r,array('id'=>$rsid));
                            }else{
                                $wpdb->insert('ci_results',$r);
                            }
                        }
                    }
                }
                $count++;
            }

            echo 'Count: '.$count.'<br>';
            echo 'exist: '.$exists.'<br>';
            echo 'Inserted: '.$inserted.'<br>';
            echo 'fail: '.$fail.'<br>';

        }
        $this->get_form();
    }

    public  function  admin_import_req_images()
    {
        global $wpdb;

        if($this->data)
        {
            $card_dir = tr_get_ins_card_dir();
            $sign_dir = tr_get_signature_dir();

            foreach($this->data['sign'] as $row)
            {
                $fname = basename($row);
                $img = $sign_dir['path'] . '/' . $fname;
                if(!file_exists($img))
                {
                    $fdata = file_get_contents($row);
                    file_put_contents($img,$fdata);
                    $sign++;
                }
            }

            foreach($this->data['card'] as $row)
            {
                $fname = basename($row);
                $img = $sign_dir['path'] . '/' . $fname;
                if(!file_exists($img))
                {
                    $fdata = file_get_contents($row);
                    file_put_contents($img,$fdata);
                    $card++;
                }
            }

            echo 'Sign: '.$sign.'<br>';
            echo 'Card: '.$card.'<br>';
            exit;
        }
        $this->get_form();
    }
}