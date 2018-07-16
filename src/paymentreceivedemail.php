<?php

namespace Ethereumico\Epg;

use \WC_Email;

/**
 * Notification that ETH payment has been received.
 */
class PaymentReceivedEmail extends WC_Email {

	/**
	 * The order payment has been received for.
	 *
	 * @var \WC_Order;
	 */
	private $order;

	/**
	 * Constructor.
	 *
	 * Set email attributes, and defaults.
	 */
	public function __construct() {
		$this->id             = 'epg_payment_completed';
		$this->title          = __( 'ETH/ERC20 payment completed', 'ether-and-erc20-tokens-woocommerce-payment-gateway' );
		$this->description    = __( 'Notification that payment by ETH/ERC20 has been received.', 'ether-and-erc20-tokens-woocommerce-payment-gateway' );
		$this->heading        = __( 'Payment received', 'ether-and-erc20-tokens-woocommerce-payment-gateway' );
		$this->subject        = __( 'Payment received', 'ether-and-erc20-tokens-woocommerce-payment-gateway' );
		$this->template_html  = 'emails/epg-payment-received.php';
		$this->template_plain = 'emails/plain/epg-payment-received.php';
		$this->template_base  = untrailingslashit( $GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->base_path ) . '/templates/';

		$this->customer_email = true;

		// Trigger this on transition from on hold to either pending, or order
		// complete.
		add_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed_notification', array( $this, 'trigger' ) );

		parent::__construct();
	}

	/**
	 * Trigger the email if required.
	 */
	public function trigger( $order_id ) {

		if ( ! $order_id || ! $this->is_enabled() ) {
			return;
		}
		$this->order = new \WC_Order( $order_id );
		$GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log( 'Trigger received for PaymentReceivedEmail' );
		$this->recipient    = is_callable( array( $this->order, 'get_billing_email' ) ) ? $this->order->get_billing_email() : $this->order->billing_email;
		if ( is_callable( array( $this->order, 'get_payment_method' ) ) ) {
			$payment_method = $this->order->get_payment_method();
		} else {
			$payment_method = $this->order->payment_method;
		}
		$GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
			sprintf( 'Payment method for order %d is %s', $order_id, $payment_method )
		);
		$GLOBALS['ether-and-erc20-tokens-woocommerce-payment-gateway']->log(
			sprintf( 'Recipient is %s', $this->recipient )
		);
		if ( 'ether-and-erc20-tokens-woocommerce-payment-gateway' !== $payment_method ) {
			return;
		}

		$this->find[] = '{order_date}';
        $order_date = $this->order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $this->order->get_date_created()->getOffsetTimestamp() ) : '';
		$this->replace[] = date_i18n( wc_date_format(), strtotime( $order_date ) );

		$this->find[] = '{order_number}';
		$this->replace[] = $this->order->get_order_number();

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
	}

	public function get_content_html() {
		ob_start();
		woocommerce_get_template(
			$this->template_html,
			array(
				'order'         => $this->order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'woocommerce',
			$this->template_base
		);
		return ob_get_clean();
	}

	public function get_content_plain() {
		ob_start();
		woocommerce_get_template(
			$this->template_plain,
			array(
				'order'         => $this->order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
		 	'woocommerce',
			$this->template_base
		);
		return ob_get_clean();
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email Heading', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'ether-and-erc20-tokens-woocommerce-payment-gateway' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'	    => __( 'Plain text', 'woocommerce' ),
					'html' 	    => __( 'HTML', 'woocommerce' ),
					'multipart' => __( 'Multipart', 'woocommerce' ),
				),
			),
		);
	}
}
