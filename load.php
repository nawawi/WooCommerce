<?php

/* Plugin Name: SecurePay
 * Plugin URI:  https://github.com/securepay/WooCommerce
 * Description: Plugin for SecurePay payment integration with WooCommerce
 * Version:     1.0.0
 * Author:      SecurePay Sdn Bhd
 * Author URI:  https://www.securepay.my/
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'securepay_settings_link' );
    function securepay_settings_link( $links ) {
        $url = get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=securepay";
        $settings_link = '<a href="' . $url . '">' . __('Settings', 'textdomain') . '</a>';
          $links[] = $settings_link;
        return $links;
      }


    if (!defined('SECUREPAY_DIR')) {
        define('SECUREPAY_DIR', plugin_dir_path(__FILE__));
    }

    if (!defined('SECUREPAY_URL')) {
        define('SECUREPAY_URL', plugin_dir_url(__FILE__));
    }

    add_filter('woocommerce_payment_gateways', 'add_securepay_gateway');

    function add_securepay_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_securepay';
        return $gateways;
    }

    add_action('plugins_loaded', 'init_securepay_payment_gateway');

    function init_securepay_payment_gateway()
    {
        require 'classes/class-woocommerce-securepay.php';
        
        add_filter( 'woocommerce_get_sections_checkout', function($sections){return $sections;}, 500 );
    }


    function woocommerce_securepay_actions()
    {
        if (isset($_GET['wc-api']) && !empty($_GET['wc-api'])) {
            WC()->payment_gateways();
            switch ($_GET['wc-api'])
            {
                case 'wc_gateway_securepay_process_response':
                    do_action('woocommerce_wc_gateway_securepay_process_response');
                    break;

                case 'wc_gateway_securepay_responseOnline':
                    do_action('woocommerce_wc_gateway_securepay_responseOnline');
                    break;
            }
        }
    }
    
    add_action('init', 'woocommerce_securepay_actions', 500);
}
