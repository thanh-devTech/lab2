<div class="wrap">
    <?php
    global $wpdb,$blog_id;

    include_once(TRCPDD_PATH.'pages/formdata.php');
    $act = isset($_REQUEST['act'])? $_REQUEST['act']:'';


    if(isset($_POST['save_group'])){
        $data = $_POST['group'];
        $data['gr_admins'] = @implode(',',@$data['gr_admins']);
        $data['gr_members'] = @implode(',',@$data['gr_members']);

        if($data['gr_ID']>0)
        {
            $wpdb->update('ci_user_groups',$data,array('gr_ID'=>$data['gr_ID']));
            Tr_Session_Class::add('Updated group');
        }else{
            unset($data['gr_ID']);
            $rs = $wpdb->insert('ci_user_groups',$data);
            if($rs)
            {
                Tr_Session_Class::add('Added group');
            }else{
                Tr_Session_Class::add_error('Error. Please try again. '.$wpdb->last_error);
            }

        }
    }else if($act=='delete'){
        $wpdb->delete('ci_user_groups',array('gr_ID'=>$_REQUEST['id']));
        Tr_Session_Class::add('Deleted group');
    }


    if($act=='add' || $act=='edit'){
        if($act=='edit'){
            $id = $_REQUEST['id'];
            $row = $wpdb->get_row("select * from ci_user_groups where gr_ID = {$id} and gr_site_id='{$blog_id}' ", ARRAY_A);
        }else{
            $row['gr_site_id'] = $blog_id;
        }

        $joins = '';
        if($blog_id>1)
        {
            $joins.= " join {$wpdb->usermeta} cap ON u.ID = cap.user_id and cap.meta_key like '%{$blog_id}_capabilities' and cap.meta_value !='a:0:{}'";
        }

        ?>
        <h2>Group <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Groups</a></h2>
        <?php Tr_Session_Class::show()?>
        <form method="post" class="need_validate">
            <div class="frow">
                <label>Name</label>
                <input type="text" name="group[gr_name]" value="<?php echo @$row['gr_name']?>" maxlength="150" style="width:90%" required/>
                <input type="hidden" name="group[gr_ID]" value="<?php echo @$row['gr_ID']?>"/>
                <input type="hidden" name="group[gr_site_id]" value="<?php echo @$row['gr_site_id']?>"/>
            </div>
            <div class="frow">
                <h4>Sales Regional Manager</h4>
                <ul id="gr_admins" class="list_user_members">
                    <?php
                    if(!empty($row['gr_admins']))
                    {
                        $users = $wpdb->get_results("select ID,
                        concat(u.user_login ,' (', fname.meta_value ,' ',lname.meta_value ,')') as user_login
                        from {$wpdb->users} u
                        {$joins}
                        left join {$wpdb->usermeta} fname ON fname.user_id = u.ID and fname.meta_key='first_name'
                        left join {$wpdb->usermeta} lname ON lname.user_id = u.ID and lname.meta_key='last_name'
                        Where u.ID in ({$row['gr_admins']})");
                        foreach($users as $user)
                        {
                            ?>
                            <li><input type="hidden" name="group[gr_admins][]" value="<?php echo $user->ID?>"/> <?php echo $user->user_login?><a class="remove left"></a></li>
                            <?php
                        }
                    }
                    ?>
                </ul>
                <input type="text" class="user_auto" rel="gr_admins" placeholder="Search User" style="width:200px"/>
            </div>

            <div class="frow">
                <h4>Members</h4>
                <ul id="gr_members" class="list_user_members">
                    <?php
                    if(!empty($row['gr_members']))
                    {
                        $users = $wpdb->get_results("select ID,
                        concat(u.user_login ,' (', fname.meta_value ,' ',lname.meta_value ,')') as user_login
                        from {$wpdb->users} u
                        {$joins}
                        left join {$wpdb->usermeta} fname ON fname.user_id = u.ID and fname.meta_key='first_name'
                        left join {$wpdb->usermeta} lname ON lname.user_id = u.ID and lname.meta_key='last_name'
                        where u.ID in ({$row['gr_members']})");
                        foreach($users as $user)
                        {
                            $u = new WP_User( $user->ID );
                            $user_role = $u->roles[0];
                            ?>
                            <li title="<?php echo $user_role?>"><input type="hidden" name="group[gr_members][]" value="<?php echo $user->ID?>"/> <?php echo $user->user_login?><a class="remove left"></a></li>
                            <?php
                        }
                    }
                    ?>
                </ul>
                <input type="text" class="user_auto" rel="gr_members" placeholder="Search User" style="width:200px"/>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_group" value="1"/>
            </div>
        </form>
        <script>
            jQuery(function ($) {
                $('.user_auto').each(function(){
                    $('.user_auto').autocomplete({
                        source: ajaxurl+'?action=ajax&autocomplete=user_auto&target='+$(this).attr('rel'),
                        minLength: 1,
                        select: function( event, ui ) {
                            if(ui.item)
                            {
                                var $target = $(this).parent().find('.list_user_members');
                                var $user_id = ui.item.id;
                                var $user_name = ui.item.value;
                                if($user_id>0)
                                {
                                    var $added = false;
                                    $('.list_user_members input').each(function(){
                                       if($(this).val()==$user_id)
                                       {
                                           $added = true;
                                       }
                                    });
                                    if($added)
                                    {
                                        alert('Added this user');
                                    }else{
                                        var $new = $('<li><input type="hidden" name="group['+$target.attr('id')+'][]" value="'+$user_id+'"/> '+$user_name+'<a class="remove left"></a></li>');
                                        $target.append($new);
                                    }

                                }
                                $(this).val('');
                                return false;
                            }
                        }
                    });
                });
                $('.list_user_members').on('click','.remove',function(){
                    $(this).parents('li').remove();
                })
            })
        </script>

    <?php

    }else{
    ?>
        <h2>User Groups <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>

    <?php Tr_Session_Class::show()?>

    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Group Name</th>
                <th>Sales Regional Manager</th>
                <th>Members</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $results = $wpdb->get_results("select * from ci_user_groups where gr_site_id = '{$blog_id}' ");
            foreach ($results as $i => $row):
                $admins = !empty($row->gr_admins) ? explode(',',$row->gr_admins): array();
                $members = !empty($row->gr_members) ? explode(',',$row->gr_members): array();
                $row->count_admin = count($admins);
                $row->count_member = count($members);
                ?>
                <tr>
                    <td><?php echo $i + 1 ?></td>
                    <td><?php echo $row->gr_name ?></td>
                    <td><?php echo $row->count_admin ?></td>
                    <td><?php echo $row->count_member ?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->gr_ID)) ?>">Edit</a> -
                        <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->gr_ID)) ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

        <script>
            jQuery(function ($) {
                $('a.delete').click(function () {
                    if (confirm('Do you want delete?') == false)return false;
                });
            })
        </script>
        <?php
    }
    ?>
</div>