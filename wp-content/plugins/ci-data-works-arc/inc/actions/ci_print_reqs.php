<html>
<body style="margin:0;padding:0;">

<?php
global $wpdb;
$print_mids = trim($_REQUEST['mids'],',');

$mids = explode(',',$print_mids);
$_REQUEST['act'] = 'print';

foreach($mids as $id)
{
    $_GET['id'] = $_REQUEST['id'] = $id;
    include( tr_pdd_get_req_file('tox') );
    echo '<div style="page-break-after: always;"><hr></div>';
}

if(isset($_REQUEST['print'])){
    ?>
    <script>
        setTimeout(function(){
            window.print();
        },700);
    </script>
    <?php
}
?>
</body>
</html>