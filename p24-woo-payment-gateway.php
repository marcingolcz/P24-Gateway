<?php
/**
 *
 * @author Marcin Gólcz
 *
 * Plugin Name: P24 Woo Payment Gateway
 * Description: Brama płatności z wykorzystaniem serwisu Przelewy24.pl do WooCommerce.
 * Author: Marcin Gólcz
 * Author URI: http://www.golczdesign.pl
 * Version: 1.0
 */

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'init_p24_gateway', 0 );
function init_p24_gateway() {
    //If parent class doesn't exists it means that WooCommerce is not installed
    //do nothing
    if(!class_exists('WC_Payment_Gateway')) return;

    //Include gateway class
    include_once('p24-woo-payment-gateway-class.php');

    //add gateway to woocommerce
    function p24_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Przelewy24';
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'p24_gateway' );
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'p24_action_links' );
function p24_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Ustawienia', 'p24_gateway' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
}
