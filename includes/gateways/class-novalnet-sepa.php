<?php
/**
 * Novalnet Direct Debit SEPA Payment
 *
 * This gateway is used for real time processing of Direct Debit SEPA Payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Sepa
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
 * SEPA payment processed
 */
class Novalnet_SEPA {

    /**
     * Get all required action and filter to process sepa
     */
    public function __construct() {

        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_sepa' ), 1, 1 );
        add_action( 'edd_gateway_novalnet_sepa', array( $this, 'novalnet_sepa_process_payment' ) );
        add_action( 'edd_novalnet_sepa_cc_form', array( $this, 'display_form' ) );
		// Plugin script actions.
		add_action( 'wp_enqueue_scripts', array( $this, 'novalnet_sepa_script_functions' ) );
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_sepa_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_sepa_settings' ), 1, 1 );
        }

    }

    /**
     * Add Novalnet function scripts in front-end
     *
     * @since 1.1.0
     */
    public function novalnet_sepa_script_functions() {

        global $edd_options;
        // Enqueue script.
        wp_enqueue_script( 'edd-novalnet-sepa-utility-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery' ), NOVALNET_VERSION, true );
        wp_enqueue_script( 'edd-novalnet-sepa-script', NOVALNET_PLUGIN_URL . 'assets/js/novalnet-sepa.js', array( 'jquery' ), NOVALNET_VERSION, true );
        wp_enqueue_script( 'edd-novalnet-sepa-script', NOVALNET_PLUGIN_URL . 'assets/js/novalnet.js', array( 'jquery' ), NOVALNET_VERSION, true );
    }

    /** Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update sepa configuraion fields.
        update_option( self::register_novalnet_sepa_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since 1.1.0
     * @access public
     * @param  array $gateway_sections Get sepa payment to append in default gateways.
     * @return array $gateway_sections Returns sepa in along with gateway payments.
     */
    public function register_novalnet_sepa_gateway( $gateway_sections ) {
		$gateway_sections['novalnet_sepa'] = __( 'Novalnet Direct Debit SEPA', 'edd-novalnet' );
        return $gateway_sections;
    }

    /**
     * Register the gateway for SEPA
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show sepa payment in front-end and back-end.
     */
    public function register_novalnet_sepa( $gateways ) {

        $novalnet_sepa = array(
            'novalnet_sepa' => array(
                'admin_label'    => __( 'Novalnet Direct Debit SEPA', 'edd-novalnet' ),
                'checkout_label' => __( 'Direct Debit SEPA', 'edd-novalnet' ),
                'supports'       => array(),
            ),
        );
        $novalnet_sepa = apply_filters( 'edd_register_novalnet_sepa_gateway', $novalnet_sepa );
        $gateways      = array_merge( $gateways, $novalnet_sepa );
        return $gateways;
    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.0.1
     */
    public function display_form( $is_update_payment_method = false ) {

        global $edd_options;
        
        $update_payment = $is_update_payment_method;
        
        $user_data           = get_user_meta( get_current_user_id() );
        $account_holder      = !empty($user_data) ? $user_data['first_name'][0] . ' ' . $user_data['last_name'][0] : '';
        $test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_sepa_test_mode'] ) );
        $information         = isset( $edd_options['novalnet_sepa_information'] ) ? trim( $edd_options['novalnet_sepa_information'] ) : '';
        $payment_method_name = __( 'Direct Debit SEPA', 'edd-novalnet' );
        $payment_name        = 'novalnet_sepa';

        include_once NOVALNET_PLUGIN_DIR . 'templates/sepa-template.php';

        do_action( 'edd_after_cc_fields' );
    }

    /**
     * Register the action to initiate and process the Payment
     *
     * @since 1.0.1
     * @param array $purchase_data Get customer details to payment.
     * @return array $params to acknowledge status call.
     */
    public function novalnet_sepa_process_payment( $purchase_data ) {

        global $edd_options;

        // Get payment data.
        $trial_product  = '0'; 
         if ( isset( $purchase_data['downloads'] ) && !empty( $purchase_data['downloads'] ) ) {
           foreach ( $purchase_data['downloads'] as $purchase_key => $purchase_val ) {
                    if ( ! empty( $purchase_val['options']['recurring']['trial_period'] ) ) {
                        $trial_product = '1';
                }
            }
        }
        $params       = $this->novalnet_get_sepa_bank_data($trial_product);
        $payment_name = 'novalnet_sepa';
         
        if ( isset( $edd_options['novalnet_sepa_guarantee_enable'] ) ) {
            novalnet_guarantee_payment_validation( $purchase_data, $payment_name,$trial_product );
        }

        if ( isset( $edd_options['novalnet_sepa_guarantee_enable'] ) && empty( EDD()->session->get( 'novalnet_sepa_guarantee_payment_error' ) ) && empty( EDD()->session->get( 'novalnet_sepa_guarantee_dob_payment_error' ) )  && $trial_product == '0' ) {
            $payment_name = 'novalnet_sepa_guarantee';
        }
        // Get configuration data.
        $payment_data = novalnet_get_merchant_data( $purchase_data, $payment_name );
        // Get customer data.
        $customer_data = novalnet_get_customer_data( $purchase_data );
        // Get system data.
        $system_data = novalnet_get_system_data();
        $params      = array_merge( $payment_data, $customer_data, $system_data, $params );
        // Process the onhold product.
        if ( isset($edd_options['novalnet_invoice_manual_limit']) &&'authorize' === $edd_options['novalnet_sepa_manual_limit'] ) {
            if ( isset( $edd_options['novalnet_sepa_manual_check'] ) ) {
                $payment_data['amount'] = ( novalnet_digits_check( $edd_options['novalnet_sepa_manual_check'] ) ) ? $payment_data['amount'] : 0;
                if ( $payment_data['amount'] >= $edd_options['novalnet_sepa_manual_check'] ) {
                    $params['on_hold'] = 1;
                }
            } else {
                $params['on_hold'] = 1;
            }
        }
        // Due date validations.
        $sepa_payment_duration = isset( $edd_options['novalnet_sepa_due_date'] ) ? trim( $edd_options['novalnet_sepa_due_date'] ) : '';
        if ( ! empty( $sepa_payment_duration ) )  {
            $params['sepa_due_date'] = gmdate( 'Y-m-d', strtotime( gmdate( 'y-m-d' ) . '+ ' . $sepa_payment_duration . ' days' ) );
        }
        // Create the subscription order for the subscription product.
        novalnet_check_subscription( $purchase_data, $params );
        // Send the transaction request to the novalnet server.       
        $parsed_response = novalnet_submit_request( $params );       
        if ( '100' === $parsed_response['status'] ) {
            novalnet_check_response( $parsed_response);
        } else {
            novalnet_transaction_failure( $parsed_response );
        }
    }

    /**
     * Get SEPA Account details
     *
     * @since 1.0.1
     * @param array $params Get required param to acknowledge Novalnet server to respond.
     */
    public function novalnet_get_sepa_bank_data($trial_product) {

        global $edd_options;
        $sepa_details = array_map( 'trim', $_POST ); // Input var okay.
        if ( preg_match( '/[#%\^<>@$=*!]+/i', $sepa_details['novalnet_sepa_holder'] ) || empty( $sepa_details['novalnet_sepa_holder'] ) || empty( $sepa_details['novalnet_sepa_iban'] ) ) {
            edd_set_error( 'account_validation', __( 'Your account details are invalid', 'edd-novalnet' ) );
            edd_send_back_to_checkout( '?payment-mode=novalnet_sepa' );
        }
        // Guarantee requirement validations
         if ( ( isset( $edd_options['novalnet_sepa_guarantee_enable'] ) && ! empty( EDD()->session->get( 'novalnet_sepa_guarantee_payment_error' ) ) && isset($edd_options['novalnet_sepa_force_normal_payment']) && $edd_options['novalnet_sepa_force_normal_payment'] == 'no' && $trial_product == '0' )) {
            $error     = EDD()->session->get( 'novalnet_sepa_guarantee_payment_error' );
            $error_msg = sprintf( __( 'The payment cannot be processed, because the basic requirements for the payment guarantee are not met ( %s )  ', 'edd-novalnet' ), $error );
            edd_set_error( 'guarantee_validation', __( $error_msg, 'edd-novalnet' ) );
            edd_send_back_to_checkout( '?payment-mode=novalnet_sepa' );
        }
        // Date validation for guarantee payment
        if ( isset( $edd_options['novalnet_sepa_guarantee_enable'] ) && $trial_product == '0' ) {
            $day   = isset( $sepa_details['novalnet_sepa_day'] ) ? $sepa_details['novalnet_sepa_day'] : '';
            $month = isset( $sepa_details['novalnet_sepa_month'] ) ? $sepa_details['novalnet_sepa_month'] : '';
            $year  = isset( $sepa_details['novalnet_sepa_year'] ) ? $sepa_details['novalnet_sepa_year'] : '';

            $error      = check_guarantee_payment( $day, $month, $year, 'novalnet_sepa' );
            $date_check = EDD()->session->get( 'novalnet_sepa_dob' );

            if ( ! empty( $error ) && isset($edd_options['novalnet_sepa_force_normal_payment']) && $edd_options['novalnet_sepa_force_normal_payment'] == 'no') {
                edd_set_error( 'guarantee_validation', __( $error, 'edd-novalnet' ) );
                edd_send_back_to_checkout( '?payment-mode=novalnet_sepa' );
            } elseif ( ! empty( $date_check ) ) {
                $params ['birth_date'] = gmdate( 'Y-m-d', strtotime( $date_check ) );
            }
        }

        // Payment data.
        $params ['bank_account_holder'] = $sepa_details['novalnet_sepa_holder'];
        $params ['iban']                = $sepa_details['novalnet_sepa_iban'];
        if ( ! empty( $sepa_details['novalnet_sepa_bic'] ) ) {
			$params ['bic']                = $sepa_details['novalnet_sepa_bic'];
		}

        return $params;
    }
    /**
     * Adds the settings of the Novalnet sepa payment
     *
     * @since 1.0.1
     * @access public
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public function register_novalnet_sepa_settings( $gateway_settings ) {

        $novalnet_sepa = array(
            array(
                'id'   => 'novalnet_sepa_settings',
                'name' => '<strong> <font color="#1874CD">' . __( 'Novalnet Direct Debit SEPA', 'edd-novalnet' ) . '</font> </strong>',
                'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_sepa_test_mode',
                'name'          => __( 'Enable test mode', 'edd-novalnet' ),
                'type'          => 'checkbox',
                'tooltip_title' => __( 'Enable test mode', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_sepa_due_date',
                'name'          => __( 'Payment due date (in days)', 'edd-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'min'           => 2,
                'max'           => 14,
                'placeholder'          => 'test',
                'tooltip_title' => __( 'Payment due date (in days)', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Number of days after which the payment is debited (must be between 2 and 14 days).', 'edd-novalnet' ),
            ),
            array(
                'id'      => 'novalnet_sepa_manual_limit',
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
                'id'            => 'novalnet_sepa_manual_check',
                'name'          => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'desc'          => __( '<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'edd-novalnet' ),
                'tooltip_title' => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_sepa_order_completion_status',
                'name'          => __( 'Completed order status', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'publish',
                'tooltip_title' => __( 'Completed order status', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for successful orders.', 'edd-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_sepa_information',
                'name' => __( 'Notification for the buyer', 'edd-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __( 'The entered text will be displayed at the checkout page', 'edd-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_guarantee_settings',
                'name' => '<strong> <font color="#1874CD">' . __( 'Payment guarantee configuration', 'edd-novalnet' ) . '</font></strong>',
                'type' => 'header',
            ),
            array(
                'id'   => 'novalnet_sepa_guarantee_settings',
                'name' => sprintf(
                    '<ul class="guarantee_requirements">
                    <li>%1$s</li>
                    <li>%2$s</li>
                    <li>%3$s</li>
                    <li>%4$s</li>
                    <li>%5$s</li>
                    <li>%6$s</li>
                    </ul>',
                    __( 'Payment guarantee requirements: ', 'edd-novalnet' ),
                    __( 'Allowed countries: DE, AT, CH', 'edd-novalnet' ),
                    __( 'Allowed currency: EUR', 'edd-novalnet' ),
                    __( 'Minimum order amount: 9,99 EUR or more', 'edd-novalnet' ),
                    __( 'Age limit: 18 years or more', 'edd-novalnet' ),
                    __( 'The billing address must be the same as the shipping address', 'edd-novalnet' )
                ),
                'type' => 'header',

            ),
            array(
                'id'   => 'novalnet_sepa_guarantee_enable',
                'name' => __( 'Enable payment guarantee', 'edd-novalnet' ),
                'type' => 'checkbox',
            ),
            array(
                'id'            => 'novalnet_sepa_order_pending_status',
                'name'          => __( 'Payment status for the pending payment', 'edd-novalnet' ),
                'type'          => 'select',
                'std'           => 'pending',
                'options'       => edd_get_payment_statuses(),
                'tooltip_title' => __( 'Payment status for the pending payment', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for pending transactions.', 'edd-novalnet' ),
            ),
            array(
                'id'                => 'novalnet_sepa_guarantee_minimum_order_amount',
                'name'              => __( 'Minimum order amount for payment guarantee', 'edd-novalnet' ),
                'type'              => 'number',
                'desc'          => __( '<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'edd-novalnet' ),
                'min'               => 999,
                'tooltip_title'     => __( 'Minimum order amount for payment guarantee', 'edd-novalnet' ),
                'tooltip_desc'      => __( 'Enter the minimum amount (in cents) for the transaction to be processed with payment guarantee. For example, enter 100 which is equal to 1,00. By default, the amount will be 9,99 EUR.', 'edd-novalnet' ),
            ),
           array(
                'id'            => 'novalnet_sepa_force_normal_payment',
                'name'          => __( 'Force Non-Guarantee payment', 'edd-novalnet' ),
                'type' => 'select',
				'options' => array(
				'no' => __( 'No', 'edd-novalnet' ),
				'yes' => __( 'Yes', 'edd-novalnet' ),
				),
				'std' => 'yes',
                'tooltip_title' => __( 'Force Non-Guarantee payment', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Even if payment guarantee is enabled, payments will still be processed as non-guarantee payments if the payment guarantee requirements are not met. Review the requirements under \'Enable Payment Guarantee\' in the Installation Guide. ', 'edd-novalnet' ),
            ), 
        );

        $novalnet_sepa                     = apply_filters( 'edd_novalnet_sepa_settings', $novalnet_sepa );
        $gateway_settings['novalnet_sepa'] = $novalnet_sepa;
        return $gateway_settings;
    }
}
new Novalnet_SEPA();

