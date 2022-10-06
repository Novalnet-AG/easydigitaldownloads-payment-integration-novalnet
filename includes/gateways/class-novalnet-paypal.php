<?php
/**
 * Novalnet Paypal Payment
 *
 * This gateway is used for real time processing of Paypal Payment
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Paypal
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
 * Paypal payment processed
 */
class Novalnet_Paypal {

    /**
     * Get all required action and filter to process invoice
     */
    public function __construct() {

        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_paypal' ), 1, 1 );
        add_action( 'edd_novalnet_paypal_cc_form', array( $this, 'display_form' ) );
        add_action( 'edd_gateway_novalnet_paypal', array( $this, 'novalnet_paypal_process_payment' ) );
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_paypal_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_paypal_settings' ), 1, 1 );
        }

    }

    /**
     * Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update paypal configuraion fields.
        update_option( self::register_novalnet_paypal_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @access public
     * @param  array $gateway_sections Get paypal payment to append in default gateways.
     * @return array $gateway_sections Returns paypal in along with gateway payments.
     */
    public function register_novalnet_paypal_gateway( $gateway_sections ) {
		$gateway_sections['novalnet_paypal'] = __( 'Novalnet PayPal', 'edd-novalnet' );
        return $gateway_sections;
    }

    /**
     * Register the gateway for Paypal
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show paypal payment in front-end and back-end.
     */
    public function register_novalnet_paypal( $gateways ) {

        $novalnet_paypal = array(
            'novalnet_paypal' => array(
                'admin_label'    => __( 'Novalnet PayPal', 'edd-novalnet' ),
                'checkout_label' => __( 'PayPal', 'edd-novalnet' ),
                'supports'       => array(),
            ),
        );
        $novalnet_paypal = apply_filters( 'edd_register_novalnet_paypal_gateway', $novalnet_paypal );
        $gateways        = array_merge( $gateways, $novalnet_paypal );
        return $gateways;
    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.0.1
     */
    public function display_form() {
        global $edd_options;
        $test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_paypal_test_mode'] ) );
        $information         = isset( $edd_options['novalnet_paypal_information'] ) ? trim( $edd_options['novalnet_paypal_information'] ) : '';
        $payment_method_name = __( 'PayPal', 'edd-novalnet' );
        ?>
        <fieldset>
            <div class="edd-payment-icons">
                <?php
                    echo esc_html( $payment_method_name );
                    novalnet_display_payment_logo( 'novalnet_paypal', $payment_method_name );
                ?>
            </div>
            <p>
                <label for="novalnet_paypal_payment_desc" >
                    <span class="edd-description" style="font-size:90%;">
                        <?php echo esc_html( __( 'You will be redirected to PayPal. Please don’t close or refresh the browser until the payment is completed', 'edd-novalnet' ) ); ?>
                    </span>
                </label>
            </p>
            <p>
                <label for="novalnet_paypal_information">
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
    public function novalnet_paypal_process_payment( $purchase_data ) {
        global $edd_options;

        $paygate_url = 'https://payport.novalnet.de/paypal_payport';
        // Get configuration data.
        $payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_paypal' );
        // Get customer data.
        $customer_data = novalnet_get_customer_data( $purchase_data );
        // Get system data.
        $system_data = novalnet_get_system_data();
        // Get redirect payment data.
        $redirect_param = novalnet_get_redirect_param( $purchase_data['gateway'], $payment_data );
        $params = array_merge( $payment_data, $customer_data, $system_data, $redirect_param );
        
        if ( isset($edd_options['novalnet_invoice_manual_limit']) && 'authorize' === $edd_options['novalnet_paypal_manual_limit'] ) {
            if ( isset( $edd_options['novalnet_paypal_manual_check'] ) ) {
                $payment_data['amount'] = ( novalnet_digits_check( $edd_options['novalnet_paypal_manual_check'] ) ) ? $payment_data['amount'] : 0;
                if ( $payment_data['amount'] >= $edd_options['novalnet_paypal_manual_check'] ) {
                    $params['on_hold'] = 1;
                }
            } else {
                $params['on_hold'] = 1;
            }
        }      
         
        // Create the subscription order for the subscription product.
        novalnet_check_subscription( $purchase_data, $params );
        // Redirect to the paygate url.
        novalnet_get_redirect( $paygate_url, $params );
    }

    /**
     * Add the settings of the Novalnet PayPal
     *
     * @since 1.0.1
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public function register_novalnet_paypal_settings( $gateway_settings ) {
        $admin_url       = 'https://admin.novalnet.de/';
        $novalnet_paypal = array(
            array(
                'id'   => 'novalnet_paypal_settings',
                /* translators: %s: admin URL */
                'name' => '<strong> <font color="#1874CD">' . __( 'Novalnet PayPal', 'edd-novalnet' ) . '</font> </strong><p style="width:550%;">' . sprintf( __( 'To accept PayPal transactions, configure your PayPal API info in the <a href="%s" target="_new">Novalnet Admin Portal</a>: > <strong>PROJECT</strong> > <strong>Choose your project</strong> > <strong>Payment Methods</strong> > <strong>Paypal</strong> > <strong>Configure</strong>.', 'edd-novalnet' ), $admin_url ) . '</p>',
                'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_paypal_test_mode',
                'name'          => __( 'Enable test mode', 'edd-novalnet' ),
                'type'          => 'checkbox',
                'tooltip_title' => __( 'Enable test mode', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ),
            ),
            array(
                'id'      => 'novalnet_paypal_manual_limit',
                'name'    => __( 'Payment action', 'edd-novalnet' ),
                'type'    => 'select',
                'std'     => 'capture',
                'tooltip_title' => __( 'Payment action', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'edd-novalnet' ),
                'options' => array(
                    'capture'   => __( 'Capture', 'edd-novalnet' ),
                    'authorize' => __( 'Authorize', 'edd-novalnet' ),
                ),
                'size'    => 'regular',
            ),
            array(
                'id'            => 'novalnet_paypal_manual_check',
                'name'          => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'desc'          => __( '<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'edd-novalnet' ),
                'tooltip_title' => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_paypal_order_pending_status',
                'name'          => __( 'Payment status for the pending payment', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'pending',
                'tooltip_title' => __( 'Payment status for the pending payment', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for pending transactions.', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_paypal_order_completion_status',
                'name'          => __( 'Completed order status', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'publish',
                'tooltip_title' => __( 'Completed order status', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for successful orders.', 'edd-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_paypal_information',
                'name' => __( 'Notification for the buyer', 'edd-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __( 'The entered text will be displayed at the checkout page', 'edd-novalnet' ),
            ),
        );
        $novalnet_paypal                     = apply_filters( 'edd_novalnet_paypal_settings', $novalnet_paypal );
        $gateway_settings['novalnet_paypal'] = $novalnet_paypal;
        return $gateway_settings;
    }
}
new Novalnet_Paypal();
?>
