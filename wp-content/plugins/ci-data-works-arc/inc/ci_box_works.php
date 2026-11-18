<?php
class CI_Box_Works extends CI_Box_Content_Api
{
    public $options;
    public $use_current_blog = false;
    public $option_name = 'ci_box_settings';

    public function __construct($refresh=true, $use_current_blog=false,$option_name='')
    {
        if(!empty($option_name))
        {
            $this->option_name = $option_name;
        }
        $this->use_current_blog = $use_current_blog;


        $this->get_options();
        parent::__construct($this->options['client_id'],$this->options['client_secret']);
        $this->setToken($this->options['access_token'],$this->options['refresh_token']);

        if($refresh && empty($this->options['access_token']))
        {
            if($this->debug)
            {
                echo 'try refresh in create<br>';
            }
            try{
                $this->refresh();
            }catch (Exception $e)
            {
                $this->exception($e->getMessage());
            }

        }
    }

    public function get_options()
    {
        if(function_exists('get_blog_option') && !$this->use_current_blog){
            $this->options = get_blog_option(1,$this->option_name,array());
        }else{
            $this->options = get_option($this->option_name,array());
        }

	    if(!isset($this->options['client_id']))
	    {
		    $this->options['client_id'] = '';
	    }
	    if(!isset($this->options['client_secret']))
	    {
		    $this->options['client_secret'] = '';
	    }
	    if(!isset($this->options['access_token']))
	    {
		    $this->options['access_token'] = '';
	    }
	    if(!isset($this->options['refresh_token']))
	    {
		    $this->options['refresh_token'] = '';
	    }
    }

    public function update_options($options=null)
    {
        if($options!=null)
        {
            $this->options = wp_parse_args($options,$this->options);
        }
        if(function_exists('get_blog_option') && !$this->use_current_blog){
            update_blog_option(1,$this->option_name,$this->options);
        }else{
            update_option($this->option_name,$this->options);
        }
    }

    /**
     * overrive
     */
    public function authorize($code, $refresh_token=''){
        if(!empty($code) && $this->options['code']==$code)
        {
            return -1;
        }
        $rs = parent::authorize($code,$refresh_token);
        if($rs->headers->code==200)
        {
            $this->options['access_token'] = $rs->response['access_token'];
            $this->options['token_type'] = $rs->response['token_type'];
            $this->options['expires_in'] = $rs->response['expires_in'];
            $this->options['refresh_token'] = $rs->response['refresh_token'];
            $this->options['time'] = time();
            if(!empty($code))
            {
                $this->options['code'] = $code;
            }

            $this->update_options();
        }
        return $rs;
    }
    /**
     * overrive
     * @param $log
     */
    function log($log)
    {
        $filename = WP_CONTENT_DIR .'/log_box_api.txt';
        $string = date("Y-m-d H:i:s").": \n";
        if(is_object($log) || is_array($log))
        {
            ob_start();
            var_export($log);
            $string.=ob_get_clean();
        }else{
            $string .= $log;
        }

        $string .= "\n\n";
        $f = fopen($filename, 'a+');
        fwrite($f, $string);
        fclose($f);
    }

    public function delete_path($path)
    {
        global $wpdb;
        $sql = "delete  ci_box_folder where path = '{$path}'";
        if(!empty($this->options['account_id'])){
            $sql .= " and account_id = {$this->options['account_id']}";
        }
        return $wpdb->query($sql);
    }

    public function check_folder($path)
    {
        global $wpdb;

        $dir = '';
        $folders = explode('/',$path);
        foreach($folders as $f)
        {
            if(empty($f))continue;
            $dir.= '/'.$f;
            //find in db
            $sql = "select * from ci_box_folder where path = '{$dir}'";
            if(!empty($this->options['account_id'])){
                $sql .= " and account_id = {$this->options['account_id']}";
            }
            $folder_db = $wpdb->get_row($sql);
            if($folder_db && $folder_db->folder_id>0)
            {
                $rs = false;
                try{
                    $rs = $this->get_folder_info($folder_db->folder_id);
                }
                catch(Exception $e){

                }
                if(!$rs)
                {
                    $wpdb->query("delete from ci_box_folder where id = {$folder_db->id}");
                }
            }
        }
    }

    public function path_folder($path,$check=false)
    {
        global $wpdb;
        //find in db
        $sql = "select folder_id from ci_box_folder where path = '{$path}'";
        if(!empty($this->options['account_id'])){
            $sql .= " and account_id = {$this->options['account_id']}";
        }
        $folder_id = $wpdb->get_var($sql);
        if($folder_id>0)
        {
            if($check)
            {
                try{
                    $folder = $this->get_folder_info($folder_id);
                }catch (Exception $e){
                    $folder = false;
                }

                if($folder!==false)
                {
                    return $folder_id;
                }else{
                    $wpdb->delete('ci_box_folder',array('path'=>$path));
                }
            }else{
                return $folder_id;
            }

        }

        $folder_id = 0;
        $dir = '';
        $folders = explode('/',$path);
        foreach($folders as $f)
        {
            if(empty($f))continue;
            $dir.= '/'.$f;
            $parent_id = $folder_id;
            //find in db
            $sql = "select folder_id from ci_box_folder where path = '{$dir}'";
            if(!empty($this->options['account_id'])){
                $sql .= " and account_id = {$this->options['account_id']}";
            }
            $folder_id = $wpdb->get_var($sql);
            if($folder_id==0)
            {
                $folder_id = $this->create_folder($f,$parent_id);

                if($this->debug)
                {
                    echo $dir,'=>'.$folder_id.'=>'.$parent_id,'<br>';
                }
                if($folder_id>0)
                {
                    $wpdb->insert('ci_box_folder',array('path'=>$dir,'folder_id'=>$folder_id,'parent_id'=>$parent_id,'account_id'=>$this->options['account_id']));
                }else{
                    break;
                }
            }else{
                if($check)
                {
                    try{
                        $folder = $this->get_folder_info($folder_id);
                    }catch (Exception $e){
                        $folder = false;
                    }

                    if($folder==false)
                    {
                        $wpdb->delete('ci_box_folder',array('path'=>$dir));
                    }
                }
            }
        }
        return $folder_id;
    }

    public function update_file_rs($path,$rs,$type='result')
    {
        global $wpdb;

        $status = ($rs)? 1 : 3;

        $data = array(
            'status'=>$status,
            'uptime'=>time(),
            'file_id'=>$rs->response['entries'][0]['id'],
            'etag'=>$rs->response['entries'][0]['etag'],
            'account_id' => $this->options['account_id']
        );
        if(!$rs)
        {
            unset($data['uptime']);
        }
        $wpdb->update('ci_box_upload', $data, array('path'=>$path,'account_id'=>$this->options['account_id'],'type'=>$type));
    }

    public function update_file_status($path,$status,$type='result')
    {
        global $wpdb;
        $wpdb->update('ci_box_upload', array('status'=>$status), array('path'=>$path,'account_id'=>$this->options['account_id'],'type'=>$type));
    }

    public function add_file($path,$mid,$type,$batch_id='',$lab_id='',$sender='',$btype='')
    {

        global $wpdb;
        $data = array(
            'path'=>$path,
            'mid'=>$mid,
            'type'=>$type,
            'account_id' => $this->options['account_id'],
            'batch_id' => $batch_id,
            'lab_ID' => $lab_id,
            'sender' => $sender,
            'btype' => $btype,
            'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
        );
        return $wpdb->insert('ci_box_upload',$data);
    }

    public function ready()
    {
        if(empty($this->options['access_token'])){
            return false;
        }
        $user = $this->get_user();
        if($user && $user->response['id']>0)
        {
            return true;
        }
        return false;
    }


}