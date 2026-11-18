<?php

include_once(TRCPDD_PATH.'pages/formdata.php');
include_once(TRCPDD_PATH.'inc/ups/ci_shipping_ups.php');

$ups = new CI_Shipping_UPS();

if ( ! empty( $_POST ) ) {
    $ups->process_admin_options();
    $ups = new CI_Shipping_UPS();
}


wp_enqueue_script( 'jquery-ui-sortable' );
wp_enqueue_script( 'common-script2', TRCPDD_URL. 'js/wf_common.js', array( 'jquery' ) ,TRCPDD_VS);
?>

<div class="wrap">
    <h2>UPS</h2>

    <div class="wrap woocommerce">
        <form method="<?php echo esc_attr( apply_filters( 'woocommerce_settings_form_method_tab_' . $current_tab, 'post' ) ); ?>" id="mainform" action="" enctype="multipart/form-data">
            <table class="form-table">
                <?php

                $ups->generate_settings_html();
                ?>
            </table>
            <p class="submit">
                <input name="save" class="button-primary woocommerce-save-button" type="submit" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>" />

                <?php wp_nonce_field( 'woocommerce-settings' ); ?>
            </p>
        </form>
    </div>

</div>