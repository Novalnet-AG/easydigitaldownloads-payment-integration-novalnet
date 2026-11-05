<?php
/**
 * Novalnet Multibanco Payment
 *
 * This gateway is used for real time processing of Multibanco
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Multibanco
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
 * Multibanco payment processed
 */
class Novalnet_Multibanco {

    /**
     * Get all required action and filter to process invoice
     */
    public function __construct() {
        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_multibanco' ), 1, 1 );
        add_action( 'edd_novalnet_multibanco_cc_form', array( $this, 'display_form' ) );
        add_action( 'edd_gateway_novalnet_multibanco', array( $this, 'novalnet_multibanco_process_payment' ) );
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_multibanco_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_multibanco_settings' ), 1, 1 );
        }

    }

    /**
     * Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update multibanco configuraion fields.
        update_option( self::register_novalnet_multibanco_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @param  array $gateway_sections Get multibanco payment to append in default gateways.
     * @return array $gateway_sections Returns multibanco in along with gateway payments.
     */
    public function register_novalnet_multibanco_gateway( $gateway_sections ) {
        $gateway_sections['novalnet_multibanco'] = __( 'Novalnet Multibanco', 'easy-digital-downloads-payment-gateway-by-novalnet' );
        return $gateway_sections;
    }

    /**
     * Register the gateway payment for multibanco
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show multibanco payment in front-end and back-end.
     */
    public function register_novalnet_multibanco( $gateways ) {

        $novalnet_multibanco = array(
            'novalnet_multibanco' => array(
                'admin_label'    => __( 'Novalnet Multibanco', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'checkout_label' => __( 'Multibanco', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'supports'       => array(),
            ),
        );
        $novalnet_multibanco = apply_filters( 'edd_register_novalnet_multibanco_gateway', $novalnet_multibanco );
        $gateways            = array_merge( $gateways, $novalnet_multibanco );
        return $gateways;
    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.0.1
     */
    public function display_form() {
        global $edd_options;
        $test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_multibanco_test_mode'] ) );
        $information         = isset( $edd_options['novalnet_multibanco_information'] ) ? trim( $edd_options['novalnet_multibanco_information'] ) : '';
        $payment_method_name = __( 'Multibanco', 'easy-digital-downloads-payment-gateway-by-novalnet' );
        ?>
        <fieldset>
            <div class="edd-payment-icons">
                <?php
                    echo esc_html( $payment_method_name );
                    novalnet_display_payment_logo( 'novalnet_multibanco', $payment_method_name );
                ?>
            </div>
            <p>
                <label for="novalnet_multibanco_payment_desc" >
                    <span class="edd-description" style="font-size:90%;">
                        <?php echo esc_html( __( 'On successful checkout, you will receive a payment reference. Using this payment reference, you can either pay in the Multibanco ATM or through your online bank account.', 'easy-digital-downloads-payment-gateway-by-novalnet' ) ); ?>
                    </span>
                </label>
            </p>
            <p>
                <label for="novalnet_multibanco_information">
                    <span class="edd-description" style="font-size:90%;">
                        <?php echo esc_html( $information ); ?>
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
                            <?php echo esc_html( __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet' ) ); ?>
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
    public function novalnet_multibanco_process_payment( $purchase_data ) {
        global $edd_options;
       
        // Get configuration data.
        $payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_multibanco' );

        // Get customer data.
        $customer_data = novalnet_get_customer_data( $purchase_data );
        // Get system data.
        $system_data = novalnet_get_system_data();
        $params      = array_merge( $payment_data, $customer_data, $system_data );
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
     * Add the settings of the Novalnet Multibanco
     *
     * @since 1.0.1
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public static function register_novalnet_multibanco_settings( $gateway_settings ) {
        $novalnet_multibanco  = array(
            array(
                'id'   => 'novalnet_multibanco_settings',
                'name' => '<strong> <font color="#1874CD">' . __( 'Novalnet Multibanco', 'easy-digital-downloads-payment-gateway-by-novalnet' ) . '</font> </strong>',
                'desc' => __( 'Configure the gateway settings', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_multibanco_test_mode',
                'name'          => __( 'Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'checkbox',
                'tooltip_title' => __( 'Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            
            array(
                'id'            => 'novalnet_multibanco_order_completion_status',
                'name'          => __( 'Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'processing',
                'tooltip_title' => __( 'Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for successful orders.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_multibanco_order_callback_status',
                'name'          => __( 'Callback / Webhook order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'publish',
                'tooltip_title' => __( 'Callback / Webhook order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used when callback script is executed for payment received by Novalnet.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_multibanco_information',
                'name' => __( 'Notification for the buyer', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __( 'On successful checkout, you will receive a payment reference. Using this payment reference, you can either pay in the Multibanco ATM or through your online bank account', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
        );
        $novalnet_multibanco = apply_filters( 'edd_novalnet_multibanco_settings', $novalnet_multibanco );
        $gateway_settings['novalnet_multibanco'] = $novalnet_multibanco;
        return $gateway_settings;
    }
}
new Novalnet_Multibanco();
?>
