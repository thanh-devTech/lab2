<?php
include_once (TRCPDD_PATH.'inc/pokitdok/PlatformClient.php');

if(isset($_POST['data'])){
    $data = $_POST['data'];
    update_option('_tr_pokitdok_settings',$data);

    Tr_Session_Class::add("Saved Settings Successfully");
}

$data = get_option('_tr_pokitdok_settings',array());


if(isset($_GET['test'])){
    try{
        $client = new PlatformClient($data['id'],$data['secret']);
        $rs = $client->eligibility(array(
            'member' => array(
                'id' => "W00000asdfsad0000",
                'birth_date' => "1987-01-01",
                'first_name' => "Jane2",
                'last_name' => "Doe2"
            ),
            'provider' => array(
                'npi' => "14675600032",
                'last_name' => "AYA-AY2",
                'first_name' => "JEROME2"
            ),
            'service_types' => array("health_benefit_plan_coverage"),
            'trading_partner_id' => "MOCKPAYER"
        ));
        var_dump($rs);
    }catch(Exception $e){
        var_dump($re);
    }
}
?>


<div class="wrap">
    <h2>Pokitdok Settings</h2>

    <?php Tr_Session_Class::show()?>

    <form method="post">
        <div class="frow blabel">
            <label>Active</label>
            <input type="checkbox" name="data[active]" value="1" <?php echo checked($data['active'])?>"/>
        </div>
        <div class="frow blabel">
            <label>Client ID</label>
            <input type="text" name="data[id]" value="<?php echo $data['id']?>" maxlength="50" required style="width:50%"/>
        </div>
        <div class="frow blabel">
            <label>Secret</label>
            <input type="text" name="data[secret]" value="<?php echo $data['secret']?>" maxlength="50" required style="width:50%"/>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
        </div>
    </form>
</div>