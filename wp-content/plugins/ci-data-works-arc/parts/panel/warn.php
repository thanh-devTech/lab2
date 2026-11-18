<?php
global $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$keydata = 'tr_warn_popup_' . $tes_type;

if (isset($_POST['data'])) {

    update_option($keydata, $_POST['data']);
    Tr_Session_Class::add("Updated Successfully!");
}
$data = get_option($keydata);
if (empty($data['msg'])) {
    $data['msg'] = 'This result should be verified with another lab before reporting! Did you call Alan about this result?';
}
$data['tests'] = isset($data['tests']) ? $data['tests'] : array();

$tests = $wpdb->get_results("select * from ci_test_panels where te_type='{$tes_type}' group by te_name order by te_name asc");
?>
<div class="wrap">
    <h2>Warn Popup Settings
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">Tests</a>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">
        <div class="frow">
            <label>Tests</label>
            <select name="data[tests][]" class="js_select2" multiple>
                <?php
                foreach ($tests as $test) {
                    $selected = in_array($test->te_ID, $data['tests']) ? 'selected' : '';
                    echo '<option value="' . $test->te_ID . '" ' . $selected . '>' . $test->te_name . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="frow">
            <label>Message</label>
            <textarea name="data[msg]"><?php echo $data['msg'] ?></textarea>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Save" />
        </div>
    </form>
    <p>Also You can put Popup Message when Approve In Group edit</p>
</div>