<?php
global $wpdb, $current_role;

wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('wp-color-picker');
wp_enqueue_style('wp-color-picker');


$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'notices';
$tabs = array(
    'notices' => 'Notices',
    'alerts' => 'Alerts',
);
$alertbgcolors = array(
    'critical' => array('color' => '#f4b1bb', 'label' => 'Critical'),
    'high' => array('color' => '#f9e2e7', 'label' => 'High'),
    'medium' => array('color' => '#fff4e5', 'label' => 'Medium'),
    'low' => array('color' => '#e7f4fa', 'label' => 'Low'),
);

$msg_type = $tab == 'alerts' ? 'a' : 'n'; // n-notice, a-alert
$msg_type_label = $tab == 'alerts' ? 'Alert' : 'Notice';

if (!in_array($current_role, array('administrator', 'IT'))) {
    unset($tabs['alerts']);
}

if (!isset($_GET['trajax'])) {
?>
    <h2 class="nav-tab-wrapper">
        <?php
        foreach ($tabs as $k => $v) {
            $class = 'nav-tab';
            if ($k == $tab) {
                $class .= ' nav-tab-active';
            }
        ?>
            <a class="<?php echo $class ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'send', 'tab' => $k, 'id' => '')) ?>"><?php echo $v ?></a>
        <?php
        }
        ?>
    </h2>
<?php
}

if ($act == 'add' || $act == 'edit') {

    include(trpdd_get_file_path('message', 'add'));
} else {
    include(trpdd_get_file_path('message', 'list'));
}
