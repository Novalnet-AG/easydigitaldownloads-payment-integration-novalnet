<?php
/**
 * Novalnet Prepayment Payment
 *
 * This gateway is used for real time processing of Prepayment
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Prepayment
 * @package     edd-novalnet-gateway
 * @author      Novalnet AG
 * @located     at  /includes/gateways
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepayment payment processed
 */
class Novalnet_Prepayment {

	/**
	 * Get all required action and filter to process invoice
	 */
	public function __construct() {
		add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
		add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_prepayment' ), 1, 1 );
		add_action( 'edd_novalnet_prepayment_cc_form', array( $this, 'display_form' ) );
		add_action( 'edd_gateway_novalnet_prepayment', array( $this, 'novalnet_prepayment_process_payment' ) );
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_prepayment_gateway' ), 1, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_prepayment_settings' ), 1, 1 );
		}

	}

	/**
	 * Update the registered payment settings while saving
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public static function update_novalnet_settings() {
		// Update prepayment configuraion fields.
		update_option( self::register_novalnet_prepayment_settings() );
	}

	/**
	 * Register the payment gateways setting section
	 *
	 * @since  1.1.0
	 * @param  array $gateway_sections Get prepayment payment to append in default gateways.
	 * @return array $gateway_sections Returns prepayment in along with gateway payments.
	 */
	public function register_novalnet_prepayment_gateway( $gateway_sections ) {
		if ( edd_is_gateway_active( 'novalnet_prepayment' ) ) {
			$gateway_sections['novalnet_prepayment'] = __( 'Novalnet Prepayment', 'edd-novalnet' );
		}
		return $gateway_sections;
	}

	/**
	 * Register the gateway payment for prepayment
	 *
	 * @since 1.0.1
	 * @param array $gateways Allows payment details.
	 * @return array  $gateways Show prepayment payment in front-end and back-end.
	 */
	public function register_novalnet_prepayment( $gateways ) {

		$novalnet_prepayment = array(
			'novalnet_prepayment' => array(
				'admin_label'    => __( 'Novalnet Prepayment', 'edd-novalnet' ),
				'checkout_label' => __( 'Prepayment', 'edd-novalnet' ),
				'supports'       => array(),
			),
		);
		$novalnet_prepayment = apply_filters( 'edd_register_novalnet_prepayment_gateway', $novalnet_prepayment );
		$gateways            = array_merge( $gateways, $novalnet_prepayment );
		return $gateways;
	}

	/**
	 * Register the action to display the payment form
	 *
	 * @since 1.0.1
	 */
	public function display_form() {
		global $edd_options;
		$test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_prepayment_test_mode'] ) );
		$information         = isset( $edd_options['novalnet_prepayment_information'] ) ? trim( $edd_options['novalnet_prepayment_information'] ) : '';
		$payment_method_name = __( 'Prepayment', 'edd-novalnet' );
		?>
		<fieldset>
			<div class="edd-payment-icons">
				<?php
					echo esc_html( $payment_method_name );
					novalnet_display_payment_logo( 'novalnet_prepayment', $payment_method_name );
				?>
			</div>
			<p>
				<label for="novalnet_prepayment_payment_desc" >
					<span class="edd-description" style="font-size:90%;">
						<?php echo esc_html( __( 'You will receive an e-mail with the Novalnet account details to complete the payment.', 'edd-novalnet' ) ); ?>
					</span>
				</label>
			</p>
			<p>
				<label for="novalnet_prepayment_information">
					<span class="edd-description" style="font-size:90%;">
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
						<span class="edd-description" style="color:red;font-size:90%;">
							<?php echo esc_html( __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ) ); ?>
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
	 * @since 1.0.1
	 * @param array $purchase_data Get customer details to payment.
	 */
	public function novalnet_prepayment_process_payment( $purchase_data ) {
		global $edd_options;
		 $trial_product  = '0';              
         if ( isset( $purchase_data['downloads'] ) && !empty( $purchase_data['downloads'] ) ) {
           foreach ( $purchase_data['downloads'] as $purchase_key => $purchase_val ) {
                    if ( ! empty( $purchase_val['options']['recurring']['trial_period'] ) ) {
                        $trial_product = '1';
                }
            }
        }     
		// Get configuration data.
		$payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_prepayment' );
		$payment_data['invoice_type'] = 'PREPAYMENT';
		
		$payment_duration = isset( $edd_options['novalnet_prepayment_due_date'] ) ? trim( $edd_options['novalnet_prepayment_due_date'] ) : '';
        $payment_data['due_date'] = ( ! empty( $payment_duration ) ) ? gmdate( 'Y-m-d', strtotime( gmdate( 'y-m-d' ) . '+ ' . $payment_duration . ' days' ) ) : gmdate( 'Y-m-d', strtotime( gmdate( 'y-m-d' ) . '+ 14 days' ) );
		// Get customer data.
		$customer_data = novalnet_get_customer_data( $purchase_data );
		// Get system data.
		$system_data = novalnet_get_system_data();
		$params      = array_merge( $payment_data, $customer_data, $system_data );
        EDD()->session->set( 'trial_product', $trial_product);
		// Create the subscription order for the subscription product.
		novalnet_check_subscription( $purchase_data, $params );

		// Send the transaction request to the novalnet server.		 
		$parsed_response = novalnet_submit_request( $params );		
		if ( '100' === $parsed_response['status'] ) {
			novalnet_check_response( $parsed_response );
		} else {
			novalnet_transaction_failure( $parsed_response );
		}
	}

	/**
	 * Add the settings of the Novalnet Prepayment
	 *
	 * @since 1.0.1
	 * @param array $gateway_settings Back-end configuration list.
	 * @return array $gateway_settings Save back-end configuration's.
	 */
	public function register_novalnet_prepayment_settings( $gateway_settings ) {
		$novalnet_prepayment                     = array(
			array(
				'id'   => 'novalnet_prepayment_settings',
				'name' => '<strong> <font color="#1874CD">' . __( 'Novalnet Prepayment', 'edd-novalnet' ) . '</font> </strong>',
				'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
				'type' => 'header',
			),
			array(
				'id'            => 'novalnet_prepayment_test_mode',
				'name'          => __( 'Enable test mode', 'edd-novalnet' ),
				'type'          => 'checkbox',
				'tooltip_title' => __( 'Enable test mode', 'edd-novalnet' ),
				'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ),
			),
			array(
                'id'            => 'novalnet_prepayment_due_date',
                'name'          => __( 'Payment due date (in days)', 'edd-novalnet' ),
                'type'          => 'number',
                'min'           => 7,
                'max'           => 28,
                'size'          => 'regular',
                'tooltip_title' => __( 'Payment due date (in days)', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). If this field is left blank, 14 days will be set as due date by default. ', 'edd-novalnet' ),
            ),
			array(
				'id'      		=> 'novalnet_prepayment_order_completion_status',
				'name'    		=> __( 'Completed order status', 'edd-novalnet' ),
				'type'    		=> 'select',
				'options' 		=> edd_get_payment_statuses(),
				'std'     		=> 'processing',
				'tooltip_title' => __( 'Completed order status', 'edd-novalnet' ),
				'tooltip_desc'  => __( 'Status to be used for successful orders.', 'edd-novalnet' ),
			),
			array(
				'id'      		=> 'novalnet_prepayment_order_callback_status',
				'name'    		=> __( 'Callback / Webhook order status', 'edd-novalnet' ),
				'type'    		=> 'select',
				'options' 		=> edd_get_payment_statuses(),
				'std'     		=> 'publish',
				'tooltip_title' => __( 'Callback / Webhook order status', 'edd-novalnet' ),
				'tooltip_desc'  => __( 'Status to be used when callback script is executed for payment received by Novalnet.', 'edd-novalnet' ),
			),
			array(
				'id'   => 'novalnet_prepayment_information',
				'name' => __( 'Notification for the buyer', 'edd-novalnet' ),
				'type' => 'textarea',
				'size' => 'regular',
				'desc' => __( 'The entered text will be displayed at the checkout page', 'edd-novalnet' ),
			),
		);
		$novalnet_prepayment = apply_filters( 'edd_novalnet_prepayment_settings', $novalnet_prepayment );
		$gateway_settings['novalnet_prepayment'] = $novalnet_prepayment;
		return $gateway_settings;
	}
}
new Novalnet_Prepayment();
?>
