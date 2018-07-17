<?php

/*
Plugin Name: Ether and ERC20 tokens WooCommerce Payment Gateway
Plugin URI: https://wordpress.org/plugins/ether-and-erc20-tokens-woocommerce-payment-gateway
Description: Ether and ERC20 tokens WooCommerce Payment Gateway enables customers to pay with Ether or any ERC20 or ERC223 token on your WooCommerce store.
Version: 2.3.1
WC requires at least: 2.6.0
WC tested up to: 3.4
Author: ethereumicoio
Author URI: https://ethereumico.io
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ether-and-erc20-tokens-woocommerce-payment-gateway
Domain Path: /languages
*/

function epg_plugin_deactivate() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    deactivate_plugins( plugin_basename( __FILE__ ) );
}

if ( version_compare( phpversion(), '7.0', '<' ) ) {
	add_action( 'admin_init', 'epg_plugin_deactivate' );
	add_action( 'admin_notices', 'epg_plugin_admin_notice' );
	function epg_plugin_admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="error"><p><strong>WooCommerce Ethereum ERC20 Payment Gateway</strong> requires PHP version 7.0 or above.</p></div>';
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
} else {
    /**
     * Check if WooCommerce is active
     * https://wordpress.stackexchange.com/a/193908/137915
     **/
    if ( 
      in_array( 
        'woocommerce/woocommerce.php', 
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
      ) 
    ) {
        // Add autoloaders, and load up the plugin.
        require_once( dirname( __FILE__ ) . '/autoload.php' );
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway'] = new \Ethereumico\Epg\Main( plugins_url( '', __FILE__ ), plugin_dir_path( __FILE__ ) );
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->run();
        
        require_once dirname( __FILE__ ) . '/vendor/prospress/action-scheduler/action-scheduler.php';
        
        // Place in Option List on Settings > Plugins page 
        function ether_and_erc20_tokens_woocommerce_payment_gateway_actlinks( $links, $file ) {
            // Static so we don't call plugin_basename on every plugin row.
            static $this_plugin;

            if ( ! $this_plugin ) {
                $this_plugin = plugin_basename( __FILE__ );
            }

            if ( $file == $this_plugin ) {
                $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=ether-and-erc20-tokens-woocommerce-payment-gateway">' . __( 'Settings', 'woocommerce' ) . '</a>';
                array_unshift( $links, $settings_link ); // before other links
            }

            return $links;
        }
        add_filter( 'plugin_action_links', 'ether_and_erc20_tokens_woocommerce_payment_gateway_actlinks', 10, 2 );

        function ether_and_erc20_tokens_woocommerce_payment_gateway_complete_order($order_id) {
            $payment_gateway = wc_get_payment_gateway_by_order( $order_id );
            if (!$payment_gateway) {
                $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("ether_and_erc20_tokens_woocommerce_payment_gateway_complete_order failed to get payment gateway for order: $order_id");
                return;
            }
            $payment_gateway->complete_order($order_id);
        }
        add_action("ether_and_erc20_tokens_woocommerce_payment_gateway_complete_order", 'ether_and_erc20_tokens_woocommerce_payment_gateway_complete_order', 0, 1);

    } else {
        add_action( 'admin_init', 'epg_plugin_deactivate' );
        add_action( 'admin_notices', 'epg_plugin_admin_notice_woocommerce' );
        function epg_plugin_admin_notice_woocommerce() {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }
            echo '<div class="error"><p><strong>WooCommerce Ethereum ERC20 Payment Gateway</strong> requires WooCommerce plugin to be installed and activated.</p></div>';
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_load_textdomain() {
    /**
     * Localise.
     */
    load_plugin_textdomain( 'ether-and-erc20-tokens-woocommerce-payment-gateway', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action('plugins_loaded', 'ether_and_erc20_tokens_woocommerce_payment_gateway_load_textdomain');
