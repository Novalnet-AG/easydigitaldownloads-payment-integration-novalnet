<?php
/**
 * Novalnet Giropay Payment
 *
 * This gateway is used for real time processing of Giropay Payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Giropay
 * @package     edd-novalnet-gateway
 * @author      Novalnet AG
 * @located     at /includes/gateways
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Giropay payment processed
 */
class Novalnet_Giropay {

	/**
	 * Get all required action and filter to process giropay
	 */
	public function __construct() {

		add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
		add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_giropay' ), 1, 1 );
		add_action( 'edd_novalnet_giropay_cc_form', array( $this, 'display_form' ) );
		add_action( 'edd_gateway_novalnet_giropay', array( $this, 'novalnet_giropay_process_payment' ) );
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_giropay_gateway' ), 1, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_giropay_settings' ), 1, 1 );
		}
	}

	/**
	 * Update the registered payment settings while saving
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public static function update_novalnet_settings() {
		// Update giropay configuraion fields.
		update_option( self::register_novalnet_giropay_settings() );

	}

	/**
	 * Register the payment gateways setting section
	 *
	 * @since  1.1.0
	 * @access public
	 * @param  array $gateway_sections Get giropay payment to append in default gateways.
	 * @return array $gateway_sections Returns giropay in along with gateway payments.
	 */
	public function register_novalnet_giropay_gateway( $gateway_sections ) {
		if ( edd_is_gateway_active( 'novalnet_giropay' ) ) {
			$gateway_sections['novalnet_giropay'] = __( 'Novalnet giropay', 'edd-novalnet' );
		}
		return $gateway_sections;
	}

	/**
	 * Register the gateway for giropay
	 *
	 * @since  1.0.1
	 * @access public
	 * @param array $gateways Allows payment details.
	 * @return array  $gateways Show CC payment in front-end and back-end.
	 */
	public function register_novalnet_giropay( $gateways ) {
		$novalnet_giropay = array(
			'novalnet_giropay' => array(
				'admin_label'    => __( 'Novalnet giropay', 'edd-novalnet' ),
				'checkout_label' => __( 'giropay', 'edd-novalnet' ),
				'supports'       => array(),
			),
		);
		$novalnet_giropay = apply_filters( 'edd_register_novalnet_giropay_gateway', $novalnet_giropay );
		$gateways         = array_merge( $gateways, $novalnet_giropay );
		return $gateways;
	}

	/**
	 * Register the action to display the payment form
	 *
	 * @since  1.0.1
	 */
	public function display_form() {
		global $edd_options;
		$test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_giropay_test_mode'] ) );
		$information         = isset( $edd_options['novalnet_giropay_information'] ) ? trim( $edd_options['novalnet_giropay_information'] ) : '';
		$payment_method_name = __( 'giropay', 'edd-novalnet' );
		?>
		<fieldset>
			<div class="edd-payment-icons">
				<?php
					echo esc_html( $payment_method_name );
					novalnet_display_payment_logo( 'novalnet_giropay', $payment_method_name );
				?>
			</div>
			<p>
				<label for="novalnet_giropay_payment_desc" >
					<span class="edd-description" style="font-size:90%;">
						<?php echo esc_html( __( 'You will be redirected to giropay. Please don’t close or refresh the browser until the payment is completed', 'edd-novalnet' ) ); ?>
					</span>
				</label>
			</p>
			<p>
				<label for="novalnet_giropay_information">
					<span class="edd-description">
						<?php echo strip_tags( $information ); ?>
					</span>
				</label>
			</p>
			<?php
			if ( $test_mode ) {
				// display test mode description.
				?>
					<p>
						<label for="novalnet_test_mode_desc">
							<span class="edd-description" style="color:red;font-size:90%;"><?php echo esc_html( __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ) ); ?>
							</span>
						</label>
					</p>
				<?php
			}
			?>
		</fieldset>
		<?php
		do_action( 'edd_after_cc_fields' );
	}

	/**
	 * Register the action to initiate and processes the payment
	 *
	 * @since  1.0.1
	 * @param array $purchase_data Get customer details to payment.
	 */
	public function novalnet_giropay_process_payment( $purchase_data ) {

		$paygate_url = 'https://payport.novalnet.de/giropay';
		// Get configuration data.
		$payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_giropay' );
		// Get customer data.
		$customer_data = novalnet_get_customer_data( $purchase_data );
		// Get system data.
		$system_data = novalnet_get_system_data();
		// Get redirect payment data.
		$redirect_param = novalnet_get_redirect_param( $purchase_data['gateway'], $payment_data );
		$params         = array_merge( $payment_data, $customer_data, $system_data, $redirect_param );
		// Get transaction order number.
		$edd_order          = get_novalnet_transaction_order( $purchase_data );
		$params['order_no'] = $edd_order;
		// Redirect to the paygate url.		
		novalnet_get_redirect( $paygate_url, $params );
	}

	/**
	 * Add the settings of the Novalnet Giropay
	 *
	 * @since  1.0.1
	 * @param array $gateway_settings Back-end configuration list.
	 * @return array $gateway_settings Save back-end configuration's.
	 */
	public function register_novalnet_giropay_settings( $gateway_settings ) {
		$novalnet_giropay                     = array(
			array(
				'id'   => 'novalnet_giropay_settings',
				'name' => '<strong> <font color="#1874CD">' . __( 'Novalnet giropay', 'edd-novalnet' ) . '</font> </strong>',
				'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
				'type' => 'header',
			),
			array(
				'id'            => 'novalnet_giropay_test_mode',
				'name'          => __( 'Enable test mode', 'edd-novalnet' ),
				'type'          => 'checkbox',
				'tooltip_title' => __( 'Enable test mode', 'edd-novalnet' ),
				'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ),
			),
			array(
				'id'            => 'novalnet_giropay_order_completion_status',
				'name'          => __( 'Completed order status', 'edd-novalnet' ),
				'type'          => 'select',
				'options'       => edd_get_payment_statuses(),
				'std'           => 'publish',
				'tooltip_title' => __( 'Completed order status', 'edd-novalnet' ),
				'tooltip_desc'  => __( 'Status to be used for successful orders.', 'edd-novalnet' ),
			),
			array(
				'id'   => 'novalnet_giropay_information',
				'name' => __( 'Notification for the buyer', 'edd-novalnet' ),
				'type' => 'textarea',
				'size' => 'regular',
				'desc' => __( 'The entered text will be displayed at the checkout page', 'edd-novalnet' ),
			),
		);
		$novalnet_giropay                     = apply_filters( 'edd_novalnet_giropay_settings', $novalnet_giropay );
		$gateway_settings['novalnet_giropay'] = $novalnet_giropay;
		return $gateway_settings;
	}
}
new Novalnet_Giropay();
?>
