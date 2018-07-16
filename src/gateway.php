<?php

namespace Ethereumico\Epg;

require $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_path . '/vendor/autoload.php';

use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Contract;
use Web3\Utils;
use WC_Logger;
use WC_Order;
use WC_Payment_Gateway;
use WC_Admin_Settings;

function ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress($blockchainNetwork) {
    switch ($blockchainNetwork) {
        case 'mainnet' :
            return '0x3E0371bcb61283c036A48274AbDe0Ab3DA107a50';
        case 'ropsten' :
            return '0x4028d9F65B65517eAf4c541CfBd3d0D5E4b411cC';
    }
    return __('Unknown network name in configuration settings', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress_v1($blockchainNetwork) {
    switch ($blockchainNetwork) {
        case 'mainnet' :
            return '0xCF19CfEE14E161d339a084993B2cb18b7eAEc773';
        case 'ropsten' :
            return '0x77E4c6fC30862F066642206697c085b5D2E24CBb';
    }
    return __('Unknown network name in configuration settings', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_getEthValueByOrderId($order_id) {
    $order = new WC_Order($order_id);
    if (is_callable(array($order, 'get_meta'))) {
        return $order->get_meta('_epg_eth_value');
    }
    return get_post_meta($order_id, '_epg_eth_value', true);
}

function _ether_and_erc20_tokens_woocommerce_payment_gateway_double_int_multiply($dval, $ival) {
    $dval = doubleval($dval);
    $ival = intval($ival);
    $dv1 = floor($dval);
    $ret = new \phpseclib\Math\BigInteger(intval($dv1));
    $ret = $ret->multiply(new \phpseclib\Math\BigInteger($ival));
    if ($dv1 === $dval) {
        return $ret;
    }
    $dv2 = $dval - $dv1;
    $iv1 = intval($dv2 * $ival);
    $ret = $ret->add(new \phpseclib\Math\BigInteger($iv1));
    return $ret;
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_getEthValueWithDustByOrderId($eth_value, $order_id) {
    $eth_value_wei0 = _ether_and_erc20_tokens_woocommerce_payment_gateway_double_int_multiply($eth_value, pow(10, 18));
    $a10000000000 = new \phpseclib\Math\BigInteger(10000000000);
    list($eth_value_wei1, $_) = $eth_value_wei0->divide($a10000000000);
    $eth_value_wei = $eth_value_wei1->multiply($a10000000000);
    $diff = $eth_value_wei0->subtract($eth_value_wei);
    $order_id_wei = new \phpseclib\Math\BigInteger(intval($order_id));
    if ($order_id_wei->compare($diff) < 0) {
        // compensate replacement of these weis with order_id
        $eth_value_wei = $eth_value_wei->add($a10000000000);
    }
    // add order_id as a dust
    $eth_value_wei = $eth_value_wei->add($order_id_wei);
    list($eth_value_wei_1, $eth_value_wei_2) = $eth_value_wei->divide(new \phpseclib\Math\BigInteger(pow(10, 18)));
    return array($eth_value_wei->toString(), $eth_value_wei_1->toString() . '.' . sprintf("%'.018d", intval($eth_value_wei_2->toString())));
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_getTokenRate($tokens_supported, $tokenAddress) {
    $tokensArr = explode(",", $tokens_supported);
    if (!$tokensArr) {
        return null;
    }
    foreach ($tokensArr as $tokenStr) {
        $tokenPartsArr = explode(":", $tokenStr);
        if (count($tokenPartsArr) != 3) {
            continue;
        }
        $address = $tokenPartsArr[1];
        if (strtolower($tokenAddress) != strtolower($address)) {
            continue;
        }
        $rate = $tokenPartsArr[2];
        return $rate;
    }
    return null;
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method($method, $order_id, $providerUrl, $blockchainNetwork, $marketAddress) {
    $abi = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->gatewayContractABI;
    $contract = new Contract(new HttpProvider(new HttpRequestManager($providerUrl, 10/* seconds */)), $abi);

    $contractAddress = ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress($blockchainNetwork);
    $ret = null;
    $callback = function($error, $result) use(&$ret) {
        if ($error !== null) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log($error);
            return;
        }
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("RESULT: " . print_r($result, true));
        foreach ($result as $key => $res) {
            $ret = $res;
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("key: " . $key . "; ret: " . $ret);
            break;
        }
    };
    // call contract function
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
        sprintf(
            __('call contract %s method %s for market %s for order %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $contractAddress, $method, $marketAddress, $order_id
        )
    );
    $contract->at($contractAddress)->call($method, $marketAddress, $order_id, $callback);
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("ret2: " . $ret);
    return $ret;
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method_v1($method, $order_id, $providerUrl, $blockchainNetwork, $marketAddress) {
    $abi = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->gatewayContractABI_v1;
    $contract = new Contract(new HttpProvider(new HttpRequestManager($providerUrl, 10/* seconds */)), $abi);

    $contractAddress = ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress_v1($blockchainNetwork);
    $ret = null;
    $callback = function($error, $result) use(&$ret) {
        if ($error !== null) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log($error);
            return;
        }
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("RESULT: " . print_r($result, true));
        foreach ($result as $key => $res) {
            $ret = $res;
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("key: " . $key . "; ret: " . $ret);
            break;
        }
    };
    // call contract function
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
        sprintf(
            __('call contract %s method %s for market %s for order %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $contractAddress, $method, $marketAddress, $order_id
        )
    );
    $contract->at($contractAddress)->call($method, $marketAddress, $order_id, $callback);
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("ret2: " . $ret);
    return $ret;
}

function woo_eth_erc20_get_token_decimals($tokenAddress, $providerUrl) {
    $abi = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->erc20ContractABI;
    $contract = new Contract(new HttpProvider(new HttpRequestManager($providerUrl, 10/* seconds */)), $abi);

    $ret = null;
    $callback = function($error, $result) use(&$ret) {
        if ($error !== null) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log($error);
            return;
        }
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("RESULT: " . print_r($result, true));
        foreach ($result as $key => $res) {
            $ret = $res;
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("key: " . $key . "; ret: " . $ret);
            break;
        }
    };
    // call contract function
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
        sprintf(
            __('call contract %s method decimals', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $tokenAddress
        )
    );
    $contract->at($tokenAddress)->call("decimals", $callback);
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log("ret2: " . $ret);
    return $ret;
}

function ether_and_erc20_tokens_woocommerce_payment_gateway_getPaymentInfo($order_id, $providerUrl, $blockchainNetwork, $marketAddress) {
    $currencyPayment = ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method("getCurrencyPayment", $order_id, $providerUrl, $blockchainNetwork, $marketAddress);
    if (!($currencyPayment === "0x0000000000000000000000000000000000000000" ||
        $currencyPayment === "0x")
    ) {
        $valuePayment = ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method("getValuePayment", $order_id, $providerUrl, $blockchainNetwork, $marketAddress);
        if (null !== $valuePayment) {
            $valuePayment_f = doubleval($valuePayment->toString());
            return [$currencyPayment => $valuePayment_f];
        }
    }

    // Backwards compatibility with first plugin version
    $currencyPayment = ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method_v1("getCurrencyPayment", $order_id, $providerUrl, $blockchainNetwork, $marketAddress);
    if (!($currencyPayment === "0x0000000000000000000000000000000000000000" ||
        $currencyPayment === "0x")
    ) {
        $valuePayment = ether_and_erc20_tokens_woocommerce_payment_gateway_call_gateway_method_v1("getValuePayment", $order_id, $providerUrl, $blockchainNetwork, $marketAddress);
        if (null !== $valuePayment) {
            $valuePayment_f = doubleval($valuePayment->toString());
            return [$currencyPayment => $valuePayment_f];
        }
    }
    return null;
}

function wc_erc20_pg_check_tx_status_impl(
$order_id, $tokens_supported, $orderExpiredTimeout, $event_timeout_sec
, $providerUrl, $blockchainNetwork, $marketAddress, $standalone = false
) {
    $order = new WC_Order($order_id);
    if (!$order->needs_payment()) {
        if ($standalone) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                sprintf(
                    __('Order do not need payment, tx check stopped: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
                )
            );
        }
        return true;
    }
    // WC_DateTime|NULL object if the date is set or null if there is no date.
    $created = $order->get_date_created();
    if ($created) {
        $diff = time() - $created->getTimestamp();
        if ($diff > $orderExpiredTimeout) {
            if ($standalone) {
                $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                    sprintf(
                        __('Order expired: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
                    )
                );
                $order->add_order_note(
                    __('Order was expired.', 'ether-and-erc20-tokens-woocommerce-payment-gateway')
                );
            }
            return false;
        }
    }

    $paymentInfo = ether_and_erc20_tokens_woocommerce_payment_gateway_getPaymentInfo($order_id, $providerUrl, $blockchainNetwork, $marketAddress);
    if (!$paymentInfo) {
        // no payment yet
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
            sprintf(
                __('No payment found for order: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
            )
        );
        return false;
    }

    $paymentSuccess = false;
    $eth_value = ether_and_erc20_tokens_woocommerce_payment_gateway_getEthValueByOrderId($order_id);
    $decimals_eth = 1000000000000000000;
    $eth_value_wei = doubleval($eth_value) * $decimals_eth;
    // payment recieved
    foreach ($paymentInfo as $currencyPayment => $valuePayment) {
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
            sprintf(
                __('PaymentInfo recieved for order_id=%s. %s: %s. eth_value=%s, eth_value_wei=%s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id, $currencyPayment, $valuePayment, $eth_value, $eth_value_wei
            )
        );
        // ETH is encoded as address 0x0000000000000000000000000000000000000001
        if ('0x0000000000000000000000000000000000000001' == $currencyPayment) {
            $paymentSuccess = ($valuePayment >= $eth_value_wei);
        } else {
            // $valuePayment is in some ERC20 token
            $tokens_supported = $tokens_supported;
            $decimals_token = intval(woo_eth_erc20_get_token_decimals($currencyPayment, $providerUrl)->toString());
            $rate = ether_and_erc20_tokens_woocommerce_payment_gateway_getTokenRate($tokens_supported, $currencyPayment);
            $value = ($valuePayment / pow(10, $decimals_token)) * doubleval($rate);
            $paymentSuccess = ($value >= doubleval($eth_value));
            if (!$paymentSuccess) {
                $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                    sprintf(
                        'Payment failure for order_id=%s. tokens: %s. token decimals: %s. rate=%s. value=%s'
                        , $order_id, $tokens_supported, $decimals_token, $rate, $value
                    )
                );
            }
        }
        break;
    }

    if ($paymentSuccess) {
        // Trigger the emails to be registered and hooked.
        WC()->mailer()->init_transactional_emails();

        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
            __('Successful payment notification received. Order updated to pending.', 'ether-and-erc20-tokens-woocommerce-payment-gateway')
        );
        $order->add_order_note(
            __('Successful payment notification received. Order updated to pending.', 'ether-and-erc20-tokens-woocommerce-payment-gateway')
        );
        $order->payment_complete();
    } else {
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
            sprintf(
                __('Non-successful payment notification received. Order updated to failed: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
            )
        );
        $order->add_order_note(
            __('Non-successful payment notification received. Order updated to failed.', 'ether-and-erc20-tokens-woocommerce-payment-gateway')
        );
        $order->update_status('failed', __('Non-successful payment notification.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'));
    }
    return $paymentSuccess;
}

function wc_erc20_pg_check_tx_status_handler(
$order_id, $tokens_supported, $orderExpiredTimeout, $event_timeout_sec
, $providerUrl, $blockchainNetwork, $marketAddress
) {
    error_log("wc_erc20_pg_check_tx_status_handler called");
    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
        sprintf(
            __('tx check for order: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
        )
    );
    $order = new WC_Order($order_id);
    if (!$order->needs_payment()) {
        $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
            sprintf(
                __('Order do not need payment, tx check stopped: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
            )
        );
        return;
    }
    // WC_DateTime|NULL object if the date is set or null if there is no date.
    $created = $order->get_date_created();
    if ($created) {
        $diff = time() - $created->getTimestamp();
        if ($diff > $orderExpiredTimeout) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                sprintf(
                    __('Order expired: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $order_id
                )
            );
            $order->add_order_note(
                __('Order was expired.', 'ether-and-erc20-tokens-woocommerce-payment-gateway')
            );
            return;
        }
    }

    // schedule next check
    $event_key = 'wc_erc20_pg_check_tx_status_handler';
    $event_at = time() + $event_timeout_sec;
    $params = array($order_id, $tokens_supported, $orderExpiredTimeout, $event_timeout_sec
        , $providerUrl, $blockchainNetwork, $marketAddress);
    wp_schedule_single_event($event_at, $event_key, $params);

    $paymentSuccess = wc_erc20_pg_check_tx_status_impl(
        $order_id, $tokens_supported, $orderExpiredTimeout, $event_timeout_sec
        , $providerUrl, $blockchainNetwork, $marketAddress);

    if ($paymentSuccess) {
        // clear this event schedule
        wp_unschedule_event($event_at, $event_key, $params);
    }
}

error_log("wc_erc20_pg_check_tx_status_handler registered 1");
add_action('wc_erc20_pg_check_tx_status_handler', 'wc_erc20_pg_check_tx_status_handler', 10, 7);

/**
 * WooCommerce gateway class implementation.
 */
class Gateway extends WC_Payment_Gateway {

    /**
     * Constructor, set variables etc. and add hooks/filters
     */
    function __construct() {
        $this->id = 'ether-and-erc20-tokens-woocommerce-payment-gateway';
        $this->method_title = __('Pay with Ether or ERC20 token', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $this->has_fields = true;
        $this->supports = array(
            'products',
        );
        $this->view_transaction_url = 'https://etherscan.io/tx/%s';

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Set the public facing title according to the user's setting.
        $this->title = $this->settings['title'];
        $this->description = $this->settings['short_description'];

        // Save options from admin forms.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'verify_api_connection'));

        // Show gateway icon.
//        add_filter('woocommerce_gateway_icon', array($this, 'show_icons'), 10, 2);

        // add on-hold status to a list of statuses that needs payment
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'valid_order_statuses_for_payment'), 10, 2);

        // Show payment instructions on thank you page.
        add_action('woocommerce_thankyou_ether-and-erc20-tokens-woocommerce-payment-gateway', array($this, 'thank_you_page'));

//        add_filter('cron_schedules', array($this, 'cron_schedules'));
//        error_log("wc_erc20_pg_check_tx_status_handler registered 2");
//        add_action( 'wc_erc20_pg_check_tx_status_handler', 'wc_erc20_pg_check_tx_status_handler', 10, 7 );
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
    }

    public function valid_order_statuses_for_payment($statuses = array()) {
        if (!$statuses) {
            $statuses = array('pending', 'failed');
        }
        array_push($statuses, 'on-hold');
        return $statuses;
    }

//    /**
//     * Output the logo.
//     *
//     * @param  string $icon    The default WC-generated icon.
//     * @param  string $gateway The gateway the icons are for.
//     *
//     * @return string          The HTML for the selected iconsm or empty string if none
//     */
//    public function show_icons($icon, $gateway) {
//        if ($this->id !== $gateway) {
//            return $icon;
//        }
//        $img_url = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_url . '/img/etherium-icon.png';
//        return '<img src="' . esc_attr($img_url) . '" width="25" height="25">';
//    }

    /**
     * Get gateway icon.
     * @return string
     */
    public function get_icon() {
        $img_url = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_url . '/img/etherium-icon.png';
        $icon_html ='<img src="' . esc_attr($img_url) . '" width="25" height="25">';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Tell the user how much their order will cost if they pay by ETH.
     */
    public function payment_fields() {
        $total = WC()->cart->total;
        $currency = get_woocommerce_currency();
        try {
            $convertor = new CurrencyConvertor($currency, 'ETH');
            $eth_value = $convertor->convert($total);
            $eth_value = $this->apply_markup($eth_value);
            // Set the value in the session so we can log it against the order.
            WC()->session->set(
                'epg_calculated_value', array(
                'eth_value' => $eth_value,
                'timestamp' => time(),
                )
            );
            echo '<p class="epg-eth-pricing-note"><strong>';
            printf(__('Payment of %s ETH or an equvalent in ERC20 supported tokens will be due.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $eth_value);
            echo '</strong></p>';
        } catch (\Exception $e) {
            $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                sprintf(
                    __('Problem performing currency conversion: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $e->getMessage()
                )
            );
            echo '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">';
            echo '<ul class="woocommerce-error">';
            echo '<li>';
            _e(
                'Unable to provide an order value in ETH at this time. Please contact support.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'
            );
            echo '</li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Checks that not too much time has passed since we quoted them a price.
     */
    public function validate_fields() {
        $price_info = WC()->session->get('epg_calculated_value');
        // Prices quoted at checkout must be re-calculated if more than 15
        // minutes have passed.
        $validity_period = apply_filters('epg_checkout_validity_time', 900);
        if ($price_info['timestamp'] + $validity_period < time()) {
            wc_add_notice(__('ETH price quote has been updated, please check and confirm before proceeding.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), 'error');
            return false;
        }
        return true;
    }

    /**
     * Mark up a price by the configured amount.
     *
     * @param  float $price  The price to be marked up.
     *
     * @return float         The marked up price.
     */
    private function apply_markup($price) {
        $markup_percent = doubleval($this->settings['markup_percent']);
        $multiplier = ( $markup_percent / 100 ) + 1;
        return round($price * $multiplier, 5, PHP_ROUND_HALF_UP);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / disable', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'label' => __('Enable payment with Ether or ERC20 tokens', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
        );
        $this->form_fields += array(
            'basic_settings' => array(
                'title' => __('Basic settings', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => '',
            ),
            'debug' => array(
                'title' => __('Enable debug mode', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'label' => __('Enable only if you are diagnosing problems.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'description' => sprintf(__('Log interactions inside <code>%s</code>', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), wc_get_log_file_path($this->id)),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the name of the payment option that the user sees during checkout.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => __('Pay with ETH or ERC20 token', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
            ),
            'short_description' => array(
                'title' => __('Short description', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description of the payment option that the user sees during checkout.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => 'Pay with your Ether (ETH) or some ERC20 token.',
            ),
        );
        $this->form_fields += array(
            'api_credentials' => array(
                'title' => __('API credentials', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => '',
            ),
            'infura_api_key' => array(
                'title' => __('Infura API Key', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => sprintf(__('<p>The API key for the <a target="_blank" href="%s">%s</a>. You need to register on this site to obtain it.</p>', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), 'https://infura.io/register.html', 'https://infura.io/'),
                'default' => '',
            ),
            'blockchain_network' => array(
                'title' => __('Blockchain', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('<p>The blockchain used: mainnet or ropsten. Use mainnet in production, and ropsten in test mode.</p>', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => 'mainnet',
            ),
        );
        $this->form_fields += array(
            'payment_details' => array(
                'title' => __('Payment details', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => '',
            ),
            'payment_address' => array(
                'title' => __('Your ethereum address', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'text',
                'description' => __('The ethereum address payment should be sent to. Make sure to use one address per one online store. Do not use one address for two or more stores! Also, make sure to use a ERC20 tokens compatible wallet if you are planning to accept it!', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => '',
            ),
            'tokens_supported' => array(
                'title' => __('Supported ERC20 tokens list', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Provide a list of tokens you want to support. It should be in a format like this: Token_symbol1:token_eth_address1:token_price_eth1,Token_symbol2:token_eth_address2:token_price_eth2. For example: TSX:0xe762Da33bf2b2412477c65b01f46D923A7Ef5794:0.001,EOS:0x86Fa049857E0209aa7D9e616F7eb3b3B78ECfdb0:0.01054550', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => '',
            ),
            'disable_ether' => array(
                'title' => __('Disable Ether', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'label' => __('Disallow customer to pay with Ether', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'checkbox',
                'description' => __('This option is useful to accept only some token. It is an advanced option. Use with care.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => 'no',
            ),
            'gas_limit' => array(
                'title' => __('Gas limit', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'number',
                'description' => __('The gas limit for transaction.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => '200000',
            ),
            'gas_price' => array(
                'title' => __('Gas price, Gwei', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'number',
                'description' => __('The gas price for transaction.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => '21',
            ),
            'markup_percent' => array(
                'title' => __('Mark ETH or ERC20 token price up by %', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'description' => __('To help cover currency fluctuations the plugin can automatically mark up converted rates for you. These are applied as percentage markup, so a 1ETH value with a 1.00% markup will be presented to the customer as 1.01ETH.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'default' => '2.0',
                'type' => 'number',
                'css' => 'width:100px;',
                'custom_attributes' => array(
                    'min' => -100,
                    'max' => 100,
                    'step' => 0.1,
                ),
            ),
        );

        $this->form_fields += array(
            'ads1' => array(
                'title' => __('Need help to configure this plugin?', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => sprintf(
                    __('Feel free to %1$shire me!%2$s', 'cryptocurrency-product-for-woocommerce')
                    , '<a target="_blank" href="https://www.upwork.com/freelancers/~0134e80b874bd1fa5f">'
                    , '</a>'
                ),
            ),
        );
        $this->form_fields += array(
            'ads2' => array(
                'title' => __('Need help to develop a ERC20 token for your ICO?', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => sprintf(
                    __('Feel free to %1$shire me!%2$s', 'cryptocurrency-product-for-woocommerce')
                    , '<a target="_blank" href="https://www.upwork.com/freelancers/~0134e80b874bd1fa5f">'
                    , '</a>'
                ),
            ),
        );
        $this->form_fields += array(
            'ads3' => array(
                'title' => __('Want to sell your ERC20/ERC223 ICO token from your ICO site?', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => sprintf(
                    __('Install the %1$sThe EthereumICO Wordpress plugin%2$s!', 'ethereum-wallet')
                    , '<a target="_blank" href="https://ethereumico.io/product/ethereum-ico-wordpress-plugin/">'
                    , '</a>'
                ),
            ),
        );
        $this->form_fields += array(
            'ads4' => array(
                'title' => __('Want to sell ERC20 token for fiat and/or Bitcoin?', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'type' => 'title',
                'description' => sprintf(
                    __('Install the %1$sCryptocurrency Product for WooCommerce plugin%2$s!', 'ethereum-wallet')
                    , '<a target="_blank" href="https://ethereumico.io/product/cryptocurrency-product-for-woocommerce-standard-license/">'
                    , '</a>'
                ),
            ),
        );
    }

    /**
     * Do not allow enabling of the gateway without providing a payment address.
     */
    public function validate_enabled_field($key, $value) {
        $post_data = $this->get_post_data();
        if ($value) {
            if (empty($post_data['woocommerce_ether-and-erc20-tokens-woocommerce-payment-gateway_payment_address'])) {
                WC_Admin_Settings::add_error('You must provide an Ethereum address before enabling the gateway');
                return 'no';
            } else {
                return 'yes';
            }
        }
        return 'no';
    }

    /**
     * Output the gateway settings page.
     */
    public function admin_options() {
        ?>
        <h3><?php _e('Pay with Ether or ERC20 token', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></h3>
        <p><?php echo sprintf(__('Your customers will be given instructions about where, and how much to pay. Your orders will be marked as on-hold when they are placed. And as complete when payment is recieved. You can update your orders on the <a href="%s">WooCommerce Orders</a> page.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), admin_url('edit.php?post_type=shop_order')); ?></p>
        <table class="form-table">
        <?php $this->generate_settings_html(); ?>
        </table><!--/.form-table-->
        <?php
    }

    /**
     * See if the site can be connected to the auto-verification service.
     */
    public function verify_api_connection() {
        
    }

    /**
     * Process the payment.
     *
     * @param int $order_id  The order ID to update.
     */
    function process_payment($order_id) {

        // Load the order.
        $order = new WC_Order($order_id);

        // Retrieve the ETH value.
        $stored_info = WC()->session->get('epg_calculated_value');

        // Add order note.
        $order->add_order_note(sprintf(
                __('Order submitted, and payment with %s requested.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), 'ETH' // TODO
        ));

        // Store the ETH amount required against the order.
        $eth_value = $stored_info['eth_value'];

        update_post_meta($order_id, '_epg_eth_value', $eth_value);
        $order->add_order_note(sprintf(
                __('Order value calculated as %f %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $eth_value, 'ETH' // TODO
        ));

        // Place the order on hold.
        $order->update_status('on-hold', __('Awaiting payment.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'));

        // Reduce stock levels.
        if (is_callable('wc_reduce_stock_levels')) {
            wc_reduce_stock_levels($order->get_id());
        } else {
            $order->reduce_order_stock();
        }

        // Remove cart.
        WC()->cart->empty_cart();
        // Redirect the user to the confirmation page.
        if (method_exists($order, 'get_checkout_order_received_url')) {
            $redirect = $order->get_checkout_order_received_url();
        } else {
            if (is_callable(array($order, 'get_id')) &&
                is_callable(array($order, 'get_order_key'))) {
                $redirect = add_query_arg('key', $order->get_order_key(), add_query_arg('order', $order->get_id(), get_permalink(get_option('woocommerce_thanks_page_id'))));
            } else {
                $redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(get_option('woocommerce_thanks_page_id'))));
            }
        }

        // Return thank you page redirect.
        return array(
            'result' => 'success',
            'redirect' => $redirect,
        );
    }
    
    /**
     * Output the payment information onto the thank you page.
     *
     * @param  int $order_id  The order ID.
     */
    public function thank_you_page($order_id) {
        // set task to check tx state and complete order if needed
        $tokens_supported = esc_attr($this->settings['tokens_supported']);
        $providerUrl = $this->getWeb3Endpoint();
        $blockchainNetwork = esc_attr($this->settings['blockchain_network']);
        $marketAddress = $this->getMarketAddress();
        $event_timeout_sec = $this->getEventTimeoutSec();
        $params = array(
            $order_id,
            $tokens_supported,
            $this->getOrderExpiredTimeout(),
            $event_timeout_sec,
            $providerUrl,
            $blockchainNetwork,
            $marketAddress
        );
        $paymentSuccess = wc_erc20_pg_check_tx_status_impl(
            $order_id, $tokens_supported, $this->getOrderExpiredTimeout(), $event_timeout_sec, $providerUrl, $blockchainNetwork, $marketAddress, true
        );
        if (!$paymentSuccess) {
            $event_key = 'wc_erc20_pg_check_tx_status_handler';
            $timestamp = wp_next_scheduled($event_key, $params);
            // check if not already scheduled
            if (false === $timestamp) {
                if (false === wp_schedule_single_event(time() + $event_timeout_sec, $event_key, $params)) {
                    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                        sprintf(
                            __('%s failed to schedule for order: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $event_key, $order_id
                        )
                    );
                } else {
                    $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
                        sprintf(
                            __('%s scheduled for order: %s', 'ether-and-erc20-tokens-woocommerce-payment-gateway'), $event_key, $order_id
                        )
                    );
                }
            }
        }

        $eth_value = ether_and_erc20_tokens_woocommerce_payment_gateway_getEthValueByOrderId($order_id);
        list($eth_value_with_dust, $eth_value_with_dust_str) = ether_and_erc20_tokens_woocommerce_payment_gateway_getEthValueWithDustByOrderId($eth_value, $order_id);
        $payment_summary_token_title = __("The ERC20 token payment consists of two steps.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_summary_token_1 = __("Deposit funds to the payment gateway smart contract in the Ethereum blockchain, and", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_summary_token_2 = __("Use this deposit to pay for your order.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_summary_token_3 = __("You have to send two transactions to the Ethereum blockchain: first for deposit and second for the real payment.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_description_title = __("Step 2: Release deposit for payment.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_description = __("Send a command to the payment gateway smart contract to do the actual payment for you. The payment gateway smart contract can not spend your tokens without your command. And nobody except you can send this command to the gateway contract. But note, that if you cancel the order on this step and then decide to pay for this or any other order with the same token again, you'll be asked to approve 0 (zero) amount of tokens first, and only after that you'll be able to approve the desired token amount. It is true even for purchases on other stores that use the same payment gateway. It is due to the ERC20 token internal limitations.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $deposit_description_title = __('Step 1: Deposit funds to the payment gateway smart contract.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $deposit_description = __('This step calls the approve method on the token smart contract to allow the payment gateway contract to spend this amount of tokens on behalf of you. The Amount field is the amount of tokens you are required to pay for this order. The Value field is always 0 (zero) for token payments.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $success_message = __('Payment succeeded!', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $success_message_no_metamask = __('Payment succeeded! Reload page if it was not auto reloaded.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_incomplete_message = __('Payment status: not complete.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $payment_deposit_made_message = __('Payment status: deposit made.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $str_unlock_metamask_account = __("This wizard requires the MetaMask browser extension to be installed and your MetaMask account to be unlocked. If you do not want to use MetaMask, just copy and paste Value, Address, and Data fields in your favorite wallet software. Ensure these values are quoted exactly, otherwise we won't be able to reconcile your payment.", 'ether-and-erc20-tokens-woocommerce-payment-gateway');
        $str_metamask_network_mismatch = __('MetaMask network mismatch. Choose another network or ask site administrator.', 'ether-and-erc20-tokens-woocommerce-payment-gateway');

        // Output everything.
        ?>
<section class="twbs epg-payment-instructions">
    <h2><?php echo $this->settings['title']; ?></h2>
    <div id="epg-unlock-metamask-message-wrapper" class="alert alert-warning hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
        <div><?php esc_html_e($str_unlock_metamask_account) ?></div>
    </div>
    <div id="epg-metamask-network-mismatch-message-wrapper" class="alert alert-warning hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
        <div><?php esc_html_e($str_metamask_network_mismatch) ?></div>
    </div>
    <div id="epg-payment-incomplete-message-wrapper" class="alert alert-warning hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
        <div><?php esc_html_e($payment_incomplete_message) ?></div>
    </div>
    <div id="epg-payment-deposit-made-message-wrapper" class="alert alert-warning hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
        <div><?php esc_html_e($payment_deposit_made_message) ?></div>
    </div>
    <div id="epg-payment-success-message-wrapper" class="alert alert-success hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-check-circle" aria-hidden="true"></div>
        <div><?php esc_html_e($success_message) ?></div>
    </div>
    <div id="epg-payment-success-no-metamask-message-wrapper" class="alert alert-success hidden" hidden role="alert">
        <!--http://jsfiddle.net/0vzmmn0v/1/-->
        <div class="fa fa-check-circle" aria-hidden="true"></div>
        <div><?php esc_html_e($success_message_no_metamask) ?></div>
    </div>
    <?php
    $strDisplayStyle = '';
    if (empty(trim($tokens_supported))) {
        $strDisplayStyle = 'display:none;';
    }
    ?>
    <div class="container-fluid" id="epg-token-wrapper" style="padding-left: 0; padding-right: 0;<?php echo $strDisplayStyle; ?>">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label" for="epg-token"><?php _e('Choose Ether or ERC20 token', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                    <select name="epg-token" id="epg-token"
                            class="form-control">
<?php
        $disable_ether = $this->settings['disable_ether'];
        if ('yes' != $disable_ether) {
?>
                        <option value="ETH" selected>ETH</option>
<?php
        }
        $tokensArr = explode(",", $tokens_supported);
        if ($tokensArr) {
            foreach ($tokensArr as $tokenStr) {
                $tokenPartsArr = explode(":", $tokenStr);
                if (count($tokenPartsArr) != 3) {
                    continue;
                }
                $symbol = $tokenPartsArr[0];
                ?>
                        <option value="<?php echo $symbol ?>"><?php echo $symbol ?></option>
                <?php
            }
        }
?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid" style="padding-left: 0; padding-right: 0;" id="epg-ether-payment">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label" for="epg-ether-amount"><?php _e('Amount', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                    <input style="cursor: text;" type="text" disabled="disabled" 
                           value="<?php esc_html_e($eth_value_with_dust_str); ?>" 
                           id="epg-ether-amount" 
                           class="form-control">
                </div>
                <p>
                    <a class="pull-left" id="epg-ether-advanced-details-button" data-toggle="collapse" href="#epg-ether-advanced-details" role="button" aria-expanded="false" aria-controls="epg-ether-advanced-details"><?php _e('Advanced', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                </p>
                <div class="collapse" id="epg-ether-advanced-details">
                    <div class="form-group">
                        <label class="control-label" for="epg-ether-value"><?php _e('Value', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                        <div class="input-group" style="margin-top: 8px">
                            <input style="cursor: text;" type="text" disabled="disabled" 
                                   value="<?php esc_html_e($eth_value_with_dust_str); ?>" 
                                   data-clipboard-action="copy" 
                                   id="epg-ether-value" 
                                   class="form-control">
                            <span class="input-group-btn">
                                <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-ether-qr1" role="button" aria-expanded="false" aria-controls="epg-ether-qr1" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                <button class="button btn btn-default epg-copy-button" type="button"
                                        data-input-id="epg-ether-value"
                                        title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                    <i class="fa fa-clipboard" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                        <div class="col-12 collapse" id="epg-ether-qr1">
                            <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr1"></div>
                            </div>
                        </div>
                        <div class="col-12 d-block d-md-none">
                            <div class="col-12 d-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="epg-ether-gateway-address"><?php _e('Address', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                        <div class="input-group" style="margin-top: 8px">
                            <input style="cursor: text;" type="text" disabled="disabled" 
                                   value="<?php echo $this->getGatewayContractAddress(); ?>" 
                                   data-clipboard-action="copy" 
                                   id="epg-ether-gateway-address" 
                                   class="form-control">
                            <span class="input-group-btn">
                                <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-ether-qr2" role="button" aria-expanded="false" aria-controls="epg-ether-qr2" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                <button class="button btn btn-default epg-copy-button" type="button"
                                        data-input-id="epg-ether-gateway-address"
                                        title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                    <i class="fa fa-clipboard" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                        <div class="col-12 collapse" id="epg-ether-qr2">
                            <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr2"></div>
                            </div>
                        </div>
                        <div class="col-12 d-block d-md-none">
                            <div class="col-12 d-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr2"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="epg-ether-data-value"><?php _e('Data', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                        <div class="input-group" style="margin-top: 8px">
                            <input style="cursor: text;" type="text" disabled="disabled" 
                                   value="<?php echo $this->getMarketAddress(); ?>" 
                                   data-clipboard-action="copy" 
                                   id="epg-ether-data-value" 
                                   class="form-control">
                            <span class="input-group-btn">
                                <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-ether-qr3" role="button" aria-expanded="false" aria-controls="epg-ether-qr3" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                <button class="button btn btn-default epg-copy-button" type="button"
                                        data-input-id="epg-ether-data-value"
                                        title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                    <i class="fa fa-clipboard" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                        <div class="col-12 collapse" id="epg-ether-qr3">
                            <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr3"></div>
                            </div>
                        </div>
                        <div class="col-12 d-block d-md-none">
                            <div class="col-12 d-block mx-auto col-md-4 float-none">
                                <div class="epg-ether-canvas-qr3"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="epg-ether-alert" class="form-group hidden" hidden>
                    <div class="alert alert-warning" role="alert">
                        <!--http://jsfiddle.net/0vzmmn0v/1/-->
                        <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
                        <div><?php _e('Do not close or reload this page until payment confirmation complete.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <button id="epg-ether-mm-pay" class="button btn btn-default float-right col-12 col-md-4"><?php _e('Pay with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></button>
                    <button id="epg-ether-download-metamask-button" class="button btn btn-default float-right hidden col-12 col-md-4" hidden><?php _e('Download MetaMask!', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></button>
                    <div id="epg-ether-spinner" class="spinner float-right"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="rootwizard-help-info" class="container-fluid" style="padding-left: 0; padding-right: 0;">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info" role="info">
                    <!--http://jsfiddle.net/0vzmmn0v/1/-->
                    <div class="fa fa-info-circle" aria-hidden="true"></div>
                    <div>
                        <span>
                            <?php esc_html_e($payment_summary_token_title) ?>
                            <a data-toggle="collapse" href="#epg-payment-token-help-info" role="button" aria-expanded="false" aria-controls="epg-payment-token-help-info"><?php _e('More...', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                        </span>
                        <div class="collapse" id="epg-payment-token-help-info">
                            <ol>
                                <li><?php esc_html_e($payment_summary_token_1) ?></li>
                                <li><?php esc_html_e($payment_summary_token_2) ?></li>
                            </ol>
                            <?php esc_html_e($payment_summary_token_3) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="rootwizard" class="hidden" hidden>
        <div class="container-fluid" style="padding-left: 0; padding-right: 0;">
            <div class="row">
                <div class="col-md-12">
                    <ul class="nav nav-pills nav-justified">
                        <li class="nav-item"><a class="nav-link" href="#epg-payment-step1" data-toggle="tab"><?php _e('1. Deposit funds', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="#epg-payment-step2" data-toggle="tab"><?php _e('2. Make payment', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="epg-payment-step1">
                            <div class="alert alert-info" role="info" style="margin-top:10px">
                                <!--http://jsfiddle.net/0vzmmn0v/1/-->
                                <div class="fa fa-info-circle" aria-hidden="true"></div>
                                <div>
                                    <span>
                                        <?php esc_html_e($deposit_description_title) ?>
                                        <a data-toggle="collapse" href="#epg-payment-step1-help-info" role="button" aria-expanded="false" aria-controls="epg-payment-step1-help-info"><?php _e('More...', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                                    </span>
                                    <div class="collapse" id="epg-payment-step1-help-info">
                                        <?php esc_html_e($deposit_description) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="epg-amount"><?php _e('Amount', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                <input style="cursor: text;" type="text" disabled="disabled" 
                                       value="<?php esc_html_e($eth_value); ?>" 
                                       id="epg-amount" 
                                       class="form-control">
                            </div>
                            <div id="epg-balance-group" class="form-group hidden" hidden 
                                >
                                <label class="control-label" for="epg-balance"><?php _e('Balance', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                <input style="cursor: text;" type="text" disabled="disabled" 
                                       value="0" 
                                       id="epg-balance" 
                                       class="form-control">
                            </div>
                            <p>
                                <a class="pull-left" id="epg-token-advanced-details-step1-button" data-toggle="collapse" href="#epg-token-advanced-details-step1" role="button" aria-expanded="false" aria-controls="epg-token-advanced-details-step1"><?php _e('Advanced', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                            </p>
                            <div class="collapse" id="epg-token-advanced-details-step1">
                                <div class="form-group">
                                    <label class="control-label" for="epg-value"><?php _e('Value', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <input style="cursor: text;" type="text" disabled="disabled" 
                                               value="0" 
                                               data-clipboard-action="copy" 
                                               id="epg-value" 
                                               class="form-control">
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step1-qr1" role="button" aria-expanded="false" aria-controls="epg-token-step1-qr1" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-value"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step1-qr1">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr1"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr1"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="epg-gateway-address"><?php _e('Address', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <input style="cursor: text;" type="text" disabled="disabled" 
                                               value="<?php echo $this->getGatewayContractAddress(); ?>" 
                                               data-clipboard-action="copy" 
                                               id="epg-gateway-address" 
                                               class="form-control">
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step1-qr2" role="button" aria-expanded="false" aria-controls="epg-token-step1-qr2" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-gateway-address"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step1-qr2">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr2"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr2"></div>
                                        </div>
                                    </div>
                                </div>
                                <div id="epg-data-value-group" class="form-group hidden" hidden 
                                    >
                                    <label class="control-label" for="epg-data-value"><?php _e('Data', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <textarea style="cursor: text;" disabled="disabled" 
                                                  data-clipboard-action="copy" 
                                                  id="epg-data-value" 
                                                  class="form-control"></textarea>
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step1-qr3" role="button" aria-expanded="false" aria-controls="epg-token-step1-qr3" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-data-value"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step1-qr3">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr3"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step1-canvas-qr3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="epg-payment-step2">
                            <div class="alert alert-info" role="info" style="margin-top:10px">
                                <!--http://jsfiddle.net/0vzmmn0v/1/-->
                                <div class="fa fa-info-circle" aria-hidden="true"></div>
                                <div>
                                    <span>
                                        <?php esc_html_e($payment_description_title) ?>
                                        <a data-toggle="collapse" href="#epg-payment-step2-help-info" role="button" aria-expanded="false" aria-controls="epg-payment-step2-help-info"><?php _e('More...', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                                    </span>
                                    <div class="collapse" id="epg-payment-step2-help-info">
                                        <?php esc_html_e($payment_description) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="epg-amount2"><?php _e('Amount', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                <input style="cursor: text;" type="text" disabled="disabled" 
                                       value="<?php esc_html_e($eth_value); ?>" 
                                       id="epg-amount2" 
                                       class="form-control">
                            </div>
                            <p>
                                <a class="pull-left" id="epg-token-advanced-details-step2-button" data-toggle="collapse" href="#epg-token-advanced-details-step2" role="button" aria-expanded="false" aria-controls="epg-token-advanced-details-step2"><?php _e('Advanced', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></a>
                            </p>
                            <div class="collapse" id="epg-token-advanced-details-step2">
                                <div class="form-group">
                                    <label class="control-label" for="epg-value"><?php _e('Value', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <input style="cursor: text;" type="text" disabled="disabled" 
                                               value="0" 
                                               data-clipboard-action="copy" 
                                               id="epg-value-step2" 
                                               class="form-control">
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step2-qr1" role="button" aria-expanded="false" aria-controls="epg-token-step2-qr1" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-value-step2"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step2-qr1">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr1"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr1"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="epg-gateway-address"><?php _e('Address', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <input style="cursor: text;" type="text" disabled="disabled" 
                                               value="<?php echo $this->getGatewayContractAddress(); ?>" 
                                               data-clipboard-action="copy" 
                                               id="epg-gateway-address-step2" 
                                               class="form-control">
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step2-qr2" role="button" aria-expanded="false" aria-controls="epg-token-step2-qr2" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-gateway-address-step2"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step2-qr2">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr2"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr2"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="epg-data-value"><?php _e('Data', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></label>
                                    <div class="input-group" style="margin-top: 8px">
                                        <textarea style="cursor: text;" disabled="disabled" 
                                                  data-clipboard-action="copy" 
                                                  id="epg-data-value-step2" 
                                                  class="form-control"></textarea>
                                        <span class="input-group-btn">
                                            <button class="button btn btn-default d-none d-md-inline" type="button" data-toggle="collapse" href="#epg-token-step2-qr3" role="button" aria-expanded="false" aria-controls="epg-token-step2-qr3" title="<?php _e('QR', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i></button>
                                            <button class="button btn btn-default epg-copy-button" type="button"
                                                    data-input-id="epg-data-value-step2"
                                                    title="<?php _e('Copy', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?>">
                                                <i class="fa fa-clipboard" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="col-12 collapse" id="epg-token-step2-qr3">
                                        <div class="col-12 d-none d-md-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr3"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-block d-md-none">
                                        <div class="col-12 d-block mx-auto col-md-4 float-none">
                                            <div class="epg-token-step2-canvas-qr3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="epg-alert" class="form-group hidden" hidden>
                            <div class="alert alert-warning" role="alert">
                                <!--http://jsfiddle.net/0vzmmn0v/1/-->
                                <div class="fa fa-exclamation-triangle" aria-hidden="true"></div>
                                <div><?php _e('Do not close or reload this page until payment confirmation complete.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></div>
                            </div>
                        </div>
                        <div class="container-fluid" style="padding-left: 0; padding-right: 0;">
                            <div class="row" style="width:100%; margin-left: 0; margin-right: 0;">
                                <button id="epg-button-previous" class="button previous hidden col-12 col-md-2" hidden><?php _e('Previous', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></button>
                                <div id="epg-wizard-buttons-group" class="col-12 offset-md-2 col-md-10" style="padding-left: 0px;padding-right: 0px;">
                                    <button id="epg-button-next" class="button float-right col-md-4 col-sm-12"><?php _e('Next', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></button>
                                    <button id="epg-download-metamask-button" class="button hidden float-right col-md-4 col-sm-12" hidden><?php _e('Download MetaMask!', 'ether-and-erc20-tokens-woocommerce-payment-gateway'); ?></button>
                                </div>
                                <div id="epg-spinner" class="spinner float-right"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
        <?php
            $base_url = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_url;
            $min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
            wp_enqueue_script(
                'wooetherc20paymentgateway', 
                $base_url . "/js/ether-and-erc20-tokens-woocommerce-payment-gateway{$min}.js", array('jquery', 'bootstrap.wizard', 'web3', 'jquery.qrcode'), '2.2.2'
            );
            wp_enqueue_style(
                'wooetherc20paymentgateway', 
                $base_url . "/css/ether-and-erc20-tokens-woocommerce-payment-gateway.css", array('bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway'), '2.2.2'
            );

            $web3Endpoint = $this->getWeb3Endpoint();
            wp_localize_script(
                'wooetherc20paymentgateway', 'epg', [
                // variables
                'gas_limit' => esc_html(intval(isset($this->settings['gas_limit']) ? $this->settings['gas_limit'] : '200000')),
                'gas_price' => esc_html(floatval(isset($this->settings['gas_price']) ? $this->settings['gas_price'] : '21')),
                'payment_address' => esc_html(isset($this->settings['payment_address']) ? $this->settings['payment_address'] : ''),
                'tokens_supported' => esc_html(isset($this->settings['tokens_supported']) ? $this->settings['tokens_supported'] : ''),
                'gateway_address_v1' => $this->getGatewayContractAddress_v1(),
                'gateway_abi_v1' => $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->gatewayContractABI_v1,
                'gateway_address' => $this->getGatewayContractAddress(),
                'gateway_abi' => $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->gatewayContractABI,
                'erc20_abi' => $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->erc20ContractABI,
                'eth_value' => esc_html($eth_value),
                'eth_value_with_dust' => esc_html($eth_value_with_dust),
                'order_id' => $order_id,
                'web3Endpoint' => esc_html($web3Endpoint),
                // translations
                'str_page_unload_text' => __('Do not close or reload this page until payment confirmation complete.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_make_deposit_button_text' => __('Deposit with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_button_text' => __('Pay with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_download_metamask' => __('Download MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_download_metamask_or_input_address' => __('Download MetaMask or input your ethereum address', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_unlock_metamask_account' => __('Unlock your MetaMask account please.', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_eth_failure' => __('Failed to pay ETH with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_eth_success' => __('Pay ETH with MetaMask succeeded', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_eth_failure' => __('Failed to deposit ETH with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_eth_success' => __('Deposit ETH with MetaMask succeeded', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_token_failure' => __('Failed to pay ERC20 token with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_token_failure_insufficient_balance' => __('Failed to pay ERC20 token: insufficient balance', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_token_success' => __('Pay ERC20 token with MetaMask succeeded', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_eth_rejected' => __('Failed to pay ETH with MetaMask - rejected', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_pay_token_rejected' => __('Failed to pay ERC20 token with MetaMask - rejected', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_token_failure' => __('Failed to deposit ERC20 token with MetaMask', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_token_failure_insufficient_balance' => __('Failed to deposit ERC20 token: insufficient balance', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_token_success' => __('Deposit ERC20 token with MetaMask succeeded', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_eth_rejected' => __('Failed to deposit ETH with MetaMask - rejected', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_deposit_token_rejected' => __('Failed to deposit ERC20 token with MetaMask - rejected', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_copied_msg' => __('Copied to clipboard', 'ether-and-erc20-tokens-woocommerce-payment-gateway'),
                'str_payment_complete' => esc_html($success_message),
                'str_payment_complete_no_metamask' => esc_html($success_message_no_metamask),
                'str_payment_incomplete' => esc_html($payment_incomplete_message),
                'str_metamask_network_mismatch' => esc_html($str_metamask_network_mismatch),
                ]
            );
        }

        public function register_plugin_styles() {
            $base_url = $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_url;
            $min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
            wp_dequeue_script('web3');
            wp_deregister_script('web3');
            wp_register_script(
                'web3', $base_url . "/js/web3.min.js", array('jquery'), '0.19.0'
            );

            if( ( ! wp_script_is( 'bootstrap', 'queue' ) ) && ( ! wp_script_is( 'bootstrap', 'done' ) ) ) {
                wp_dequeue_script('bootstrap');
                wp_deregister_script('bootstrap');
                wp_register_script(
                    'bootstrap', 
                    $base_url . "/js/bootstrap{$min}.js", array('jquery'), '4.0.0'
                );
            }

            if( ( ! wp_script_is( 'qrcode', 'queue' ) ) && ( ! wp_script_is( 'qrcode', 'done' ) ) ) {
                wp_dequeue_script('qrcode');
                wp_deregister_script('qrcode');
                wp_register_script(
                    'qrcode', 
                    $base_url . "/js/qrcode{$min}.js", array(), '2009'
                );
            }

            if( ( ! wp_script_is( 'jquery.qrcode', 'queue' ) ) && ( ! wp_script_is( 'jquery.qrcode', 'done' ) ) ) {
                wp_dequeue_script('jquery.qrcode');
                wp_deregister_script('jquery.qrcode');
                wp_register_script(
                    'jquery.qrcode', 
                    $base_url . "/js/jquery.qrcode{$min}.js", array('jquery', 'qrcode'), '1.0'
                );
            }

            if( ( ! wp_script_is( 'bootstrap.wizard', 'queue' ) ) && ( ! wp_script_is( 'bootstrap.wizard', 'done' ) ) ) {
                wp_dequeue_script('bootstrap.wizard');
                wp_deregister_script('bootstrap.wizard');
                wp_register_script(
                    'bootstrap.wizard', 
                    $base_url . "/js/jquery.bootstrap.wizard{$min}.js", array('jquery', 'bootstrap'), '1.4.2'
                );
            }

            if( ( ! wp_style_is( 'font-awesome', 'queue' ) ) && ( ! wp_style_is( 'font-awesome', 'done' ) ) ) {
                wp_dequeue_style('font-awesome');
                wp_deregister_style('font-awesome');
                wp_register_style(
                    'font-awesome', 
                    $base_url . "/css/font-awesome{$min}.css", array(), '4.7.0'
                );
            }

            if( ( ! wp_style_is( 'bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway', 'queue' ) ) && ( ! wp_style_is( 'bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway', 'done' ) ) ) {
                wp_dequeue_style('bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway');
                wp_deregister_style('bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway');
                wp_register_style(
                    'bootstrap-ether-and-erc20-tokens-woocommerce-payment-gateway', 
                    $base_url . "/css/bootstrap-ns{$min}.css", array('font-awesome'), '4.0.0'
                );
            }
        }
        
        public function getTokenRate($tokenSymbol) {
            $tokens_supported = esc_attr($this->settings['tokens_supported']);
            return ether_and_erc20_tokens_woocommerce_payment_gateway_getTokenRate($tokens_supported, $tokenSymbol);
        }

        protected function getGatewayContractAddress() {
            $blockchainNetwork = esc_attr($this->settings['blockchain_network']);
            return ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress($blockchainNetwork);
        }

        protected function getGatewayContractAddress_v1() {
            $blockchainNetwork = esc_attr($this->settings['blockchain_network']);
            return ether_and_erc20_tokens_woocommerce_payment_gateway_getGatewayContractAddress_v1($blockchainNetwork);
        }

        public function getMarketAddress() {
            return esc_attr($this->settings['payment_address']);
        }

        public function getEventTimeoutSec() {
            // TODO: use tx_check_period
            return 60;
//        return $this->settings['tx_check_period'];
        }

        public function getOrderExpiredTimeout() {
            // TODO: use order_expire_timeout
            return 30 * 24 * 3600; // one month
//        return $this->settings['order_expire_timeout'];
        }

        protected function getWeb3Endpoint() {
            $infuraApiKey = esc_attr($this->settings['infura_api_key']);
            $blockchainNetwork = esc_attr($this->settings['blockchain_network']);
            if (empty($blockchainNetwork)) {
                $blockchainNetwork = 'mainnet';
            }
            $web3Endpoint = "https://" . esc_attr($blockchainNetwork) . ".infura.io/" . esc_attr($infuraApiKey);
            return $web3Endpoint;
        }

    }
    
