<?php
/**
 * Novalnet Credit/Debit Cards Payment
 *
 * This gateway is used for real time processing of Credit/Debit Cards Payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Cc
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
 * CC payment processed
 */
class Novalnet_Cc {

    /**
     * Get all required action and filter to process CC
     */
    public function __construct() {

        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_cc' ), 1, 1 );
        add_action( 'edd_novalnet_cc_cc_form', array( $this, 'display_form' ) );
        add_action( 'edd_gateway_novalnet_cc', array( $this, 'novalnet_cc_process_payment' ) );

        if ( edd_is_gateway_active( 'novalnet_cc' ) ) {
            // Plugin script actions.
            add_action( 'wp_enqueue_scripts', array( $this, 'novalnet_cc_script_functions' ) );
        }
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_cc_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_cc_settings' ), 1, 1 );
        }
    }

    /**
     * Add Novalnet function scripts in front-end
     *
     * @since 1.1.0
     */
    public function novalnet_cc_script_functions() {
        global $edd_options;
        
        $user_data           = get_user_meta( get_current_user_id() );
        
        $billing_details = ( isset( $user_data['_edd_user_address'] ) && !empty( $user_data['_edd_user_address'] ) ) ? unserialize( $user_data['_edd_user_address'][0] ) : array();
        
        $billing_address = array();
        if ( !empty( $billing_details ) ) {
			$billing_address = array(
				'street'			 => $billing_details['line1'],
				'city'				 => $billing_details['city'],
				'zip'				 => $billing_details['zip'],
				'country_code'		 => $billing_details['country']
			);
		}
       
		// Enqueue style.
		wp_enqueue_style( 'edd-novalnet-cc-style', NOVALNET_PLUGIN_URL . 'assets/css/novalnet-checkout.css' );
		
        // Enqueue script.
        wp_enqueue_script( 'edd-novalnetutility-cc-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery' ), NOVALNET_VERSION, true );
        wp_enqueue_script( 'edd-novalnet-cc-script', NOVALNET_PLUGIN_URL . 'assets/js/novalnet-cc.js', array( 'jquery' ), NOVALNET_VERSION, true );
        wp_localize_script(
            'edd-novalnet-cc-script',
            'novalnet_cc',
            array_merge($billing_address,
				array(
					'card_holder_label'  => __( 'Card holder name', 'edd-novalnet' ),
					'card_holder_input'  => __( 'Name on card', 'edd-novalnet' ),
					'card_number_label'  => __( 'Card number', 'edd-novalnet' ),
					'card_number_input'  => __( 'XXXX XXXX XXXX XXXX', 'edd-novalnet' ),
					'card_expiry_label'  => __( 'Expiry date', 'edd-novalnet' ),
					'card_cvc_label'     => __( 'CVC/CVV/CID', 'edd-novalnet' ),
					'card_cvc_input'     => __( 'XXX', 'edd-novalnet' ),
					'card_error_text'    => __( 'Your credit card details are invalid', 'edd-novalnet' ),
					'common_label_style' => ! empty( $edd_options['novalnet_common_label_style'] ) ? trim( $edd_options['novalnet_common_label_style'] ) : '',
					'common_field_style' => ! empty( $edd_options['novalnet_common_field_style'] ) ? trim( $edd_options['novalnet_common_field_style'] ) : '',
					'common_style_text'  => ! empty( $edd_options['novalnet_common_style_text'] ) ? trim( $edd_options['novalnet_common_style_text'] ) : '',
					'client_key' 		 => isset( $edd_options['novalnet_client_key'] ) ? $edd_options['novalnet_client_key'] : '',
					'test_mode' 		 => (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_cc_test_mode'] ) ),
					'first_name' 		 => !empty($user_data) ? $user_data['first_name'][0] : '',
					'last_name' 		 => !empty($user_data) ? $user_data['last_name'][0] : '',
					'amount'			 => edd_get_cart_total() * 100,
					'currency'			 => edd_get_currency(),
					'lang'				 => novalnet_shop_language(),
					'enforce_3d'	 => ( isset($edd_options['novalnet_cc_enforced_3d']) && $edd_options['novalnet_cc_enforced_3d'] == 'yes' ) ? 1 : 0,
				) 
			)
        );
    }

    /**
     * Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update creditcard configuraion fields.
        update_option( self::register_novalnet_cc_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @access public
     * @param  array $gateway_sections Get CC payment to append in default gateways.
     * @return array $gateway_sections Returns CC in along with gateway payments.
     */
    public function register_novalnet_cc_gateway( $gateway_sections ) {
        if ( edd_is_gateway_active( 'novalnet_cc' ) ) {
            $gateway_sections['novalnet_cc'] = __( 'Novalnet Credit/Debit Cards', 'edd-novalnet' );
        }
        return $gateway_sections;
    }

    /**
     * Register the gateway payment for cc
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show CC payment in front-end and back-end.
     */
    public function register_novalnet_cc( $gateways ) {

        $novalnet_cc = array(
            'novalnet_cc' => array(
                'admin_label'    => __( 'Novalnet Credit/Debit Cards', 'edd-novalnet' ),
                'checkout_label' => __( 'Credit/Debit Cards', 'edd-novalnet' ),
                'supports'       => array(),
            ),
        );
        $novalnet_cc = apply_filters( 'edd_register_novalnet_cc_gateway', $novalnet_cc );
        $gateways    = array_merge( $gateways, $novalnet_cc );
        return $gateways;
    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.1.0
     */
    public function display_form() {
        global $edd_options;

        $language            = get_bloginfo( 'language' );
        $language            = substr( $language, 0, 2 );
        $test_mode           = (int) ( edd_is_test_mode() || ! empty( $edd_options['novalnet_cc_test_mode'] ) );
        $information         = ! empty( $edd_options['novalnet_cc_information'] ) ? trim( $edd_options['novalnet_cc_information'] ) : '';
        $payment_method_name = __( 'Credit/Debit Cards', 'edd-novalnet' );
        ?>
        <fieldset>
            <div class="edd-payment-icons">
            <?php
            echo esc_html( $payment_method_name );
            novalnet_display_payment_logo( 'novalnet_cc', $payment_method_name );
            ?>
            </div>
            <p>
                <label for="novalnet_cc_payment_desc">
                    <span class="edd-description" style="font-size:90%;">
                        <?php echo esc_html( __( 'Your credit/debit card will be charged immediately after the order is completed', 'edd-novalnet' ) ); ?>
                    </span>
                </label>
            </p>
            <p>
                <label for="novalnet_cc_information">
                    <span class="edd-description"><?php echo strip_tags( $information ); ?>
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
        <iframe id="nnIframe" frameborder="0" scrolling="no" onload="novalnet_load_iframe()"></iframe>
		
        <input type="hidden" id="novalnet_cc_amount" name="novalnet_cc_amount" value="<?php echo edd_get_cart_total() * 100; ?>"/>
        <input type="hidden" id="novalnet_cc_hash" name="novalnet_cc_hash" value=""/>
        <input type="hidden" id="novalnet_cc_uniqueid" name="novalnet_cc_uniqueid" value=""/>
        <input type="hidden" id="novalnet_cc_do_redirect" name="novalnet_cc_do_redirect" value=""/>
        <input type="hidden" id="novalnet_cc_error" name="novalnet_cc_error" value=""/>
        </fieldset>
        <?php
        do_action( 'edd_after_cc_fields' );
    }

    /**
     * Register the action to initiate and process the payment
     *
     * @since 1.0.1
     * @param array $purchase_data Get customer details to payment.
     */
    public function novalnet_cc_process_payment( $purchase_data ) {
        global $edd_options;
        // Get configuration data.
        $payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_cc' );
        // Get customer data.
        $customer_data = novalnet_get_customer_data( $purchase_data );
        // Get card details.
        $card_data = $this->novalnet_get_credit_card_data();
        // Get system data.
        $system_data     = novalnet_get_system_data();
        $params          = array_merge( $payment_data, $card_data, $customer_data, $system_data );
        $params['nn_it'] = 'iframe';
        // Process the onhold product.
        if (isset($edd_options['novalnet_invoice_manual_limit']) && 'authorize' === $edd_options['novalnet_cc_manual_limit'] ) {
            if ( isset( $edd_options['novalnet_cc_manual_check'] ) ) {
                $payment_data['amount'] = ( novalnet_digits_check( $edd_options['novalnet_cc_manual_check'] ) ) ? $payment_data['amount'] : 0;
                if ( $payment_data['amount'] >= $edd_options['novalnet_cc_manual_check'] ) {
                    $params['on_hold'] = 1;
                }
            } else {
                $params['on_hold'] = 1;
            }
        }
        
		if ( isset($_REQUEST['novalnet_cc_do_redirect']) && $_REQUEST['novalnet_cc_do_redirect'] == 1 ) {
			EDD()->session->set( 'novalnet_cc_do_redirect', $_REQUEST['novalnet_cc_do_redirect'] );
			$paygate_url = 'https://payport.novalnet.de/pci_payport';
			if ( isset($edd_options['novalnet_cc_enforced_3d']) && $edd_options['novalnet_cc_enforced_3d'] == 'yes' ) {
				$params['enforce_3d'] = 1;
			}
			// Get redirect payment data.
			$redirect_param = novalnet_get_redirect_param( $purchase_data['gateway'], $payment_data );
			$params         = array_merge( $params, $redirect_param );
               
			// Create the subscription order for the subscription product.
		
			novalnet_check_subscription( $purchase_data, $params );
			// Redirect to the paygate url.
			novalnet_get_redirect( $paygate_url, $params );
        
        } else {
			
			// Create the subscription order for the subscription product
			novalnet_check_subscription( $purchase_data, $params );
			// Send the transaction request to the novalnet server			
			$parsed_response = novalnet_submit_request( $params );			 
			if ( '100' === $parsed_response['status'] ) {
				novalnet_check_response( $parsed_response  );
			} else {
				novalnet_transaction_failure( $parsed_response );
			}
		}
        
    }

    /** Get Credit Card values
     *
     * @since 1.0.1
     */
    public function novalnet_get_credit_card_data() {
        $request = $_REQUEST; // Input var okay.
        if ( ! empty( $request['novalnet_cc_hash'] ) && ! empty( $request['novalnet_cc_uniqueid'] ) ) {
            return array(
                'pan_hash'  => sanitize_key( $request['novalnet_cc_hash'] ),
                'unique_id' => sanitize_key( $request['novalnet_cc_uniqueid'] ),
            );
        } elseif ( ! empty( $request['novalnet_cc_error'] ) ) {
            edd_set_error( 'basic_validation', $request['novalnet_cc_error'] );
            edd_send_back_to_checkout( '?payment-mode=novalnet_cc' );
        } else {
            edd_set_error( 'basic_validation', __( 'Your credit card details are invalid', 'edd-novalnet' ) );
            edd_send_back_to_checkout( '?payment-mode=novalnet_cc' );
        }
    }

    /**
     * Adds the settings of the Novalnet Credit Card section
     *
     * @since 1.1.0
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public function register_novalnet_cc_settings( $gateway_settings ) {

        $novalnet_cc                     = array(
            array(
                'id'   => 'novalnet_cc_settings',
                'name' => '<strong><font color="#1874CD">' . __( 'Novalnet Credit/Debit Cards', 'edd-novalnet' ) . '</font> </strong>',
                'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_cc_test_mode',
                'name'          => __( 'Enable test mode', 'edd-novalnet' ),
                'type'          => 'checkbox',
                'tooltip_title' => __( 'Enable test mode', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'edd-novalnet' ),
            ),
            array(
                'id'      => 'novalnet_cc_manual_limit',
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
                'id'            => 'novalnet_cc_manual_check',
                'name'          => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'desc'          => __( '<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'edd-novalnet' ),
                'tooltip_title' => __( 'Minimum transaction limit for authorization', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_cc_enforced_3d',
                'name'          => __( 'Enforce 3D secure payment outside EU', 'edd-novalnet' ),
                'type'          => 'select',
                'tooltip_title' => __( 'Enforce 3D secure payment outside EU', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA', 'edd-novalnet' ),
                'options' => array(
                    'yes'   => __( 'Yes', 'edd-novalnet' ),
                    'no' => __( 'No', 'edd-novalnet' ),
                ),
                'std'     => 'no',
            ),
            array(
                'id'            => 'novalnet_cc_order_completion_status',
                'name'          => __( 'Completed order status', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'publish',
                'tooltip_title' => __( 'Completed order status', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for successful orders.', 'edd-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_cc_information',
                'name' => __( 'Notification for the buyer', 'edd-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __( 'The entered text will be displayed at the checkout page', 'edd-novalnet' ),
            ),
            array(
                'name' => '<strong><font color="#1874CD">' . __( 'Custom CSS settings', 'edd-novalnet' ) . '</font> </strong>',
                'type' => 'header',
                'id'   => 'novalnet_css_main_settings',
            ),
            array(
                'name' => '<strong><b><font size="2%" color="#000000">' . __( 'CSS settings for iframe form', 'edd-novalnet' ) . ' </font></b></strong>',
                'type' => 'header',
                'id'   => 'novalnet_css_settings',
            ),
            array(
                'id'   => 'novalnet_common_label_style',
                'name' => __( 'Label', 'edd-novalnet' ),
                'desc' => __( 'E.g: color:#999999; background-color:#FFFFFF;', 'edd-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'std'  => 'font-weight: 700;display: block;position: relative;line-height: 100%;font-size: 114%;font-family: NonBreakingSpaceOverride, "Hoefler Text", Garamond, "Times New Roman", serif;letter-spacing: normal;',
            ),
            array(
                'id'   => 'novalnet_common_field_style',
                'name' => __( 'Input', 'edd-novalnet' ),
                'desc' => __( 'E.g: color:#999999; background-color:#FFFFFF;', 'edd-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'std'  => '.input{padding: 4px 6px;display: inline-block;width: 100%;}',
            ),
            array(
                'id'          => 'novalnet_common_style_text',
                'name'        => __( 'CSS Text', 'edd-novalnet' ),
                'desc'        => __( 'E.g: #idselector{color:#999999;}.classSelector{color:#000000}', 'edd-novalnet' ),
                'type'        => 'textarea',
                'allow_blank' => false,
                'std'         => 'body{color: #141412; line-height: 1.5; margin: 0;} html, button, input, select, textarea{font-family: "Source Sans Pro",Helvetica,sans-serif;color: #000;}html{font-size: 100%;}',
            ),
        );
        $novalnet_cc                     = apply_filters( 'edd_novalnet_cc_settings', $novalnet_cc );
        $gateway_settings['novalnet_cc'] = $novalnet_cc;
        return $gateway_settings;
    }
}
new Novalnet_Cc();
?>
