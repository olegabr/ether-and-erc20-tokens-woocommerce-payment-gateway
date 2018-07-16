<?php

namespace Ethereumico\Epg;

use Ethereumico\Epg\PaymentReceivedEmail;
use \WC_Logger;

class Main {

	/**
	 * The base URL of the plugin.
	 *
	 * @var string
	 */
	public $base_url;

	/**
	 * The base path of the plugin files.
	 *
	 * @var string
	 */
	public $base_path;

    /**
     * The Gateway smart contract ABI
     * 
     * @var string The Gateway smart contract ABI
     * @see http://www.webtoolkitonline.com/json-minifier.html
     */
    public $gatewayContractABI = '[{"constant":false,"inputs":[{"name":"_tokenAddress","type":"address"},{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"},{"name":"_value","type":"uint256"}],"name":"payToken","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccount1","type":"address"}],"name":"setFeeAccount1","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccount2","type":"address"}],"name":"setFeeAccount2","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccountToken","type":"address"}],"name":"setFeeAccountToken","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feePercent","type":"uint256"}],"name":"setFeePercent","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[],"name":"transferFee","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"payable":true,"stateMutability":"payable","type":"fallback"},{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"constant":true,"inputs":[],"name":"balanceOfEthFee","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_tokenAddress","type":"address"},{"name":"_Address","type":"address"}],"name":"balanceOfToken","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feeAccount1","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feeAccount2","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feeAccountToken","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feePercent","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getBuyerAddressPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getCurrencyPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getSellerAddressPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getValuePayment","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"maxFee","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"},{"name":"","type":"uint256"}],"name":"payment","outputs":[{"name":"buyerAddress","type":"address"},{"name":"sellerAddress","type":"address"},{"name":"value","type":"uint256"},{"name":"currency","type":"address"}],"payable":false,"stateMutability":"view","type":"function"}]';
    
    /**
     * The Gateway smart contract ABI v1
     * 
     * @var string The Gateway smart contract ABI
     * @see http://www.webtoolkitonline.com/json-minifier.html
     */
    public $gatewayContractABI_v1 = '[{"constant":true,"inputs":[],"name":"maxFee","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getSellerAddressPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getBuyerAddressPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"balances","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccountToken","type":"address"}],"name":"setFeeAccountToken","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getValuePayment","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"balanceOfEthFee","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"refund","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"},{"name":"","type":"uint256"}],"name":"payment","outputs":[{"name":"buyerAddress","type":"address"},{"name":"sellerAddress","type":"address"},{"name":"value","type":"uint256"},{"name":"currency","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feeAccount2","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_feePercent","type":"uint256"}],"name":"setFeePercent","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"feePercent","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"},{"name":"_value","type":"uint256"}],"name":"payEth","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"}],"name":"getCurrencyPayment","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"getBalanceEth","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"feeAccount1","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[],"name":"transferFee","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccount1","type":"address"}],"name":"setFeeAccount1","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_feeAccount2","type":"address"}],"name":"setFeeAccount2","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"feeAccountToken","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"newOwner","type":"address"}],"name":"transferOwnership","outputs":[],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"_tokenAddress","type":"address"},{"name":"_Address","type":"address"}],"name":"balanceOfToken","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_tokenAddress","type":"address"},{"name":"_sellerAddress","type":"address"},{"name":"_orderId","type":"uint256"},{"name":"_value","type":"uint256"}],"name":"payToken","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"inputs":[],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"payable":true,"stateMutability":"payable","type":"fallback"}]';
    
    /**
     * The ERC20 smart contract ABI
     * 
     * @var string The ERC20 smart contract ABI
     * @see http://www.webtoolkitonline.com/json-minifier.html
     */
    public $erc20ContractABI = '[{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"supply","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"},{"name":"_spender","type":"address"}],"name":"allowance","outputs":[{"name":"remaining","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_owner","type":"address"},{"indexed":true,"name":"_spender","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Approval","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_from","type":"address"},{"indexed":true,"name":"_to","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Transfer","type":"event"},{"constant":false,"inputs":[{"name":"_spender","type":"address"},{"name":"_value","type":"uint256"}],"name":"approve","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transfer","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":false,"inputs":[{"name":"_from","type":"address"},{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transferFrom","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"}]';
    
	/**
	 * Constructor.
	 *
	 * Store variables for use later.
	 *
	 * @param string $base_url  The base URL of the plugin.
	 */
	function __construct( $base_url, $base_path ) {
		$this->base_url = $base_url;
		$this->base_path = $base_path;
	}

	/**
	 * Trigger the plugin to run.
	 */
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'init', array( $this, 'on_init' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'register_eth_payment_completed_email' ) );
		add_action( 'woocommerce_email_order_details', array( $this, 'email_content' ), 1, 4 );
	}

	/**
	 * Designed to run actions required at WordPress' init hook.
	 *
	 * Triggers localisation of the plugin.
	 */
	public function on_init() {
	}

	/**
	 * Designed to run actions required at WordPress' plugins_loaded hook.
	 *
	 * - Register our gateway with WooCommerce.
	 */
	public function on_plugins_loaded() {
	    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
	    add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Register our payment completed email.
	 */
	public function register_eth_payment_completed_email( $email_classes ) {
		$email_classes['PWE_Payment_Completed'] = new PaymentReceivedEmail();
		return $email_classes;
	}

	/**
	 * Add the Gateway to WooCommerce.
	 *
	 * @param array $gateways  The current list of gateways.
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = 'Ethereumico\Epg\Gateway';
		return $gateways;
	}

	/**
	 * Add payment instructions to the "order on hold" email.
	 */
	public function email_content( $order, $sent_to_admin, $plain_text, $email ) {
		// We only interfere in the order on hold email.
		if ( ! ( $email instanceof \WC_Email_Customer_On_Hold_Order ) ) {
			return;
		}
		// Check that the order was paid with this gateway.
		if ( is_callable( array( $order, 'get_payment_method' ) ) ) {
			$payment_method = $order->get_payment_method();
		} else {
			$payment_method = $order->payment_method;
		}
		if ( 'ether-and-erc20-tokens-woocommerce-payment-gateway' !== $payment_method ) {
			return;
		}
		// Retrieve the info we need.
		$settings  = get_option( 'woocommerce_ether-and-erc20-tokens-woocommerce-payment-gateway_settings', false );
		if ( is_callable( array( $order, 'get_id' ) ) ) {
			$order_id = $order->get_id();
		} else {
			$order_id = $order->id;
		}
		$eth_value = get_post_meta( $order_id, '_epg_eth_value', true );
		if ( false === $settings || false === $eth_value ) {
			return;
		}
		?>
		<h2>
		<?php
		esc_html_e( __('Payment details', 'ether-and-erc20-tokens-woocommerce-payment-gateway') );
		?>
		</h2>
		<?php
		esc_html_e( $settings['payment_summary'] );
		?>
		<ul>
			<li><?php _e( 'Amount', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ); ?>: <strong><?php esc_html_e( $eth_value ); ?></strong> ETH</li>
			<li><?php _e( 'Address', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ); ?>: <strong><?php esc_html_e( $settings['payment_address'] ); ?></strong></li>
		</ul>
		<?php
	}

	/**
	 * Log information using the WC_Logger class.
	 *
	 * Will do nothing unless debug is enabled.
	 *
	 * @param string $msg   The message to be logged.
	 */
	public function log( $msg ) {
		static $logger = false;
		$settings  = get_option( 'woocommerce_ether-and-erc20-tokens-woocommerce-payment-gateway_settings', false );
		// Bail if debug isn't on.
		if ( 'yes' !== $settings['debug'] ) {
			return;
		}
		// Create a logger instance if we don't already have one.
		if ( false === $logger ) {
			$logger = new WC_Logger();
		}
		$logger->add( 'ether-and-erc20-tokens-woocommerce-payment-gateway', $msg );
	}


}
