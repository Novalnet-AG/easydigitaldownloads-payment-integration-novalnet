<?php
/**
 * Novalnet Applepay Payment
 *
 * This gateway is used for real time processing of Applepay Payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Applepay
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
 * Applepay payment processed
 */
class Novalnet_Applepay {

    /**
     * Get all required action and filter to process Applepay
     */
    public function __construct() {

        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_novalnet_applepay' ), 1, 1 );
        add_action( 'edd_novalnet_applepay_cc_form', array( $this, 'display_form' ) );
        add_action( 'edd_gateway_novalnet_applepay', array( $this, 'novalnet_applepay_process_payment' ) );
        add_filter('edd_enabled_payment_gateways', function($gateways) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isAppleSafari = preg_match('/(iPhone|iPad|iPod|iPhone Simulator|iPad Simulator|iPod Simulator|Macintosh|Mac OS X)/i', $userAgent) && preg_match('/Safari/i', $userAgent) &&
                       !preg_match('/Chrome|CriOS|Edg|OPR|Firefox|FxiOS/i', $userAgent);
        if (!$isAppleSafari) {
            unset($gateways['novalnet_applepay']);
        }
         return $gateways;
        });
        // Plugin script actions.
        add_action( 'wp_enqueue_scripts', array( $this, 'novalnet_applepay_script_functions' ) );
        add_action( 'wp_ajax_novalnet_refresh_applepay',array($this, 'novalnet_refresh_applepay'));
        add_action('wp_ajax_nopriv_novalnet_refresh_applepay',array($this, 'novalnet_refresh_applepay'));
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_applepay_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_applepay_settings' ), 1, 1 );
        }
    }

    /**
     * Add Novalnet function scripts in front-end
     *
     * @since 1.1.0
     */
   public function novalnet_applepay_script_functions() {

    global $edd_options;

    // Novalnet Payment SDK.
    wp_enqueue_script(
        'edd-novalnet-payment-script',
        'https://cdn.novalnet.de/js/v3/payment-1.1.2.js',
        array( 'jquery' ),
        NOVALNET_VERSION,
        true
    );

    // Google Pay JS.
    wp_enqueue_script(
        'edd-novalnet-applepay-script',
        NOVALNET_PLUGIN_URL . 'assets/js/novalnet-applepay.min.js',
        array(
            'jquery',
            'edd-novalnet-payment-script',
        ),
        NOVALNET_VERSION,
        true
    );
    $integrity_hash = 'sha384-atA3i+le7+YbutIO8EnS4fCE+xye9oYFNILkTuqYTmUgLtrTO3fG6ydtf4JEpn7V';
        add_filter('script_loader_tag', function ($tag, $handle) use ($integrity_hash) {
            if ('edd-novalnet-applepay-script' === $handle) {
                return str_replace(
                    ' src=',
                    ' integrity="' . esc_attr($integrity_hash) . '" crossorigin="anonymous" src=',
                    $tag
                );
            }
            return $tag;
        }, 10, 2);

 $applepay_settings = get_wallet_configurations( 'applepay', $edd_options );

wp_localize_script(
    'edd-novalnet-applepay-script',
    'novalnet_applepay',
    $applepay_settings
);
}

public function novalnet_refresh_applepay() {

    global $edd_options;

    $config = get_wallet_configurations(
        'applepay',
        $edd_options
    );

    $has_free_trial = false;
foreach ( edd_get_cart_contents() as $item ) {
    if ( ! empty( $item['options']['recurring']['trial_period'] ) ) {
        $has_free_trial = true;
        break;
    }
}
$amount = $has_free_trial ? 0 : $_POST['total'];
     if(isset($_POST['total'])) {

        $config['amount'] = $amount * 100;

    }

$currency = edd_currency_symbol();
$article_details = array();
 $cart_items = edd_get_cart_contents();

foreach ($cart_items as $item) {

    $download_id = $item['id'];
    $quantity    = $item['quantity'];

    $product_name = get_the_title($download_id);
    $price_id = $item['options']['price_id'] ?? false;
    if ($price_id !== false) {
        $price = edd_get_price_option_amount($download_id, $price_id);
    } else {
        $price = edd_get_download_price($download_id);
    }
    $amount = $quantity * $price;
    $product_details = $product_name . ' (' .
        $quantity . ' X ' .
        $currency . number_format($price, 2) . ')';

    $article_details[] = array(
        'label'  => $product_details,
        'amount' => edd_novalnet_amount_as_string($amount),
        'type'   => 'SUBTOTAL',
    );

       if (!empty($item['options']['recurring']['signup_fee'])) {

        $signup_fee = $item['options']['recurring']['signup_fee'];

        $article_details[] = array(
            'label'  => $product_name . ' Signup Fee',
            'amount' => edd_novalnet_amount_as_string($signup_fee),
            'type'   => 'SUBTOTAL',
        );
    }
}
 $tax = edd_get_cart_tax();

if ($tax > 0) {

    $article_details[] = array(
        'label'  => 'Tax',
        'amount' => edd_novalnet_amount_as_string($_POST['tax']),
        'type'   => 'TAX',
    );
}


  $discounts = edd_get_cart_discounts();

if (!empty($discounts)) {

        $article_details[] = array(
            'label'  => 'Coupon: ' . $_POST['coupon_code'],
            'amount' => edd_novalnet_amount_as_string($_POST['coupon_amount']),
            'type'   => 'DISCOUNT',
        ); 
}

  $config['article_details'] = wp_json_encode($article_details);
    wp_send_json($config);

}

    /**
     * Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update Applepay configuraion fields.
        update_option( self::register_novalnet_applepay_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @access public
     * @param  array $gateway_sections Get Applepay payment to append in default gateways.
     * @return array $gateway_sections Returns Applepay in along with gateway payments.
     */
    public function register_novalnet_applepay_gateway( $gateway_sections ) {
        $gateway_sections['novalnet_applepay'] = __( 'Novalnet Apple Pay', 'easy-digital-downloads-payment-gateway-by-novalnet' );
        return $gateway_sections;
    }

    /**
     * Register the gateway payment for Applepay
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show Applepay payment in front-end and back-end.
     */
    public function register_novalnet_applepay( $gateways ) {

        $novalnet_applepay = array(
            'novalnet_applepay' => array(
                'admin_label'    => __( 'Novalnet Apple Pay', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'checkout_label' => __( 'Apple Pay', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'supports'       => array(),
            ),
        );
        $novalnet_applepay = apply_filters( 'edd_register_novalnet_applepay_gateway', $novalnet_applepay );
        $gateways    = array_merge( $gateways, $novalnet_applepay );
        return $gateways;
    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.1.0
     */
    public function display_form( $is_update_payment_method = false ) {
        global $edd_options;

        $language            = get_bloginfo( 'language' );
        $language            = substr( $language, 0, 2 );
        $test_mode           = $test_mode = (int) (
    ( function_exists( 'edd_is_test_mode' ) && edd_is_test_mode() )
    || ! empty( $edd_options['novalnet_applepay_test_mode'] )
);
        $information         = ! empty( $edd_options['novalnet_applapay_information'] ) ? trim( $edd_options['novalnet_applepay_information'] ) : '';
        $payment_method_name = __( 'Apple Pay', 'easy-digital-downloads-payment-gateway-by-novalnet' );
        ?>
        <fieldset id="edd-novalnet-applepay-wrap">
    <div class="edd-payment-icons">
        <?php
        echo esc_html( $payment_method_name );
        novalnet_display_payment_logo( 'novalnet_applepay', $payment_method_name );
        ?>
    </div>

    <p>
        <label>
            <span class="edd-description">
                <?php echo esc_html( $information ); ?>
            </span>
        </label>
    </p>

    <?php if ( $test_mode ) : ?>
    <p>
            <label for="novalnet_test_mode_desc">
                <span class="edd-description" style="color:red; font-size:90%;">
                    <?php echo esc_html( __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet' ) ); ?>
                </span>
            </label>
     </p>
    <?php endif; ?>

    <div id="applepay_container"></div>

   <input 
            type="hidden" 
            id="novalnet_applepay_response"
            name="novalnet_applepay_response"
            value=""
        />

</fieldset>
        <?php
        do_action( 'edd_after_cc_fields' );
        if($is_update_payment_method){
            edd_terms_agreement();
            edd_privacy_agreement();
       };
        
    }

    /**
     * Register the action to initiate and process the payment
     *
     * @since 1.0.1
     * @param array $purchase_data Get customer details to payment.
     */
    public function novalnet_applepay_process_payment( $purchase_data ) {

    
        global $edd_options;
        // Get configuration data.

          $response = json_decode(
       stripslashes($_POST['novalnet_applepay_response']),
        true
     );

       $payment_data = novalnet_get_merchant_data( $purchase_data, 'novalnet_applepay' );
       $customer_data = novalnet_get_customer_data( $purchase_data );
       $card_data = $this->novalnet_get_applepay_data($response);
        $system_data     = novalnet_get_system_data();

         $params  = array_merge( $payment_data, $card_data, $customer_data, $system_data );

         
       
        // Process the onhold product.
        if (isset($edd_options['novalnet_applepay_manual_limit']) && 'authorize' === $edd_options['novalnet_applepay_manual_limit'] ) {
            if ( isset( $edd_options['novalnet_applepay_manual_check'] ) ) {
                  $payment_data['amount'] = ( novalnet_digits_check( $edd_options['novalnet_applepay_manual_check'] ) ) ? $payment_data['amount'] : 0;
                if ( $payment_data['amount'] >= $edd_options['novalnet_applepay_manual_check'] ) {
                    $params['on_hold'] = 1;
                }
            } else {
                $params['on_hold'] = 1;
            }
        }
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

     /** Get Applepay values
      *
      * @since 1.0.1
     */
    public function novalnet_get_applepay_data($response) {
     
        if ( ! empty( $response['transaction']['token'] ) ) {
            return array(
                "wallet_token" => $response['transaction']['token'],
            );
        } else {
            edd_set_error(
            'basic_validation',
            __( 'Your Applepay details are invalid', 'easy-digital-downloads-payment-gateway-by-novalnet' )
        );

        edd_send_back_to_checkout( '?payment-mode=novalnet_applepay' );

        }
    }

    /**
     * Adds the settings of the Novalnet Applepay section
     *
     * @since 1.1.0
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public static function register_novalnet_applepay_settings( $gateway_settings ) {

        $novalnet_applepay = array(
            array(
                'id'   => 'novalnet_applepay_settings',
                'name' => '<strong><font color="#1874CD">' . __( 'Novalnet Applepay', 'easy-digital-downloads-payment-gateway-by-novalnet' ) . '</font> </strong>',
                'desc' => __( 'Configure the gateway settings', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_applepay_test_mode',
                'name'          => __( 'Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'checkbox',
                'tooltip_title' => __( 'Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            array(
                'id'      => 'novalnet_applepay_manual_limit',
                'name'    => __( 'Payment action', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'    => 'select',
                'std'     => 'capture',
                'tooltip_title' => __( 'Payment action', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'options' => array(
                    'capture'   => __( 'Capture', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                    'authorize' => __( 'Authorize', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                ),
                'size'    => 'regular',
            ),
            array(
                'id'            => 'novalnet_applepay_manual_check',
                'name'          => __( 'Minimum transaction limit for authorization', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'desc'          => __( '<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_title' => __( 'Minimum transaction limit for authorization', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
    
            array(
                'id'   => 'novalnet_applepay_information',
                'name' => __( 'Notification for the buyer', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __( 'Amount will be booked from your card after successful authentication.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_applepay_order_completion_status',
                'name'          => __( 'Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'publish',
                'tooltip_title' => __( 'Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for successful orders.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),

             array(
                'id'   => 'novalnet_applepay_businessname',
                'name' => __( 'Business name', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type' => 'text',
                'size' => 'regular',
                'std'      => get_bloginfo('name'),
                'tooltip_title' => __( 'Business name', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'The business name is rendered in the Apple Pay payment sheet, and this text will appear as PAY "BUSINESS NAME" so that the customer knows where he is paying to.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),

            array(
                'name' => '<strong><font color="#1874CD">' . __( 'Button Design', 'easy-digital-downloads-payment-gateway-by-novalnet' ) . '</font> </strong>',
                'type' => 'header',
                'id'   => 'novalnet_css_main_settings',
            ),

            array(
                'id'            => 'novalnet_applepay_applepay_button_type',
                'name'          => __( 'Button Type', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'select',
                'options'       => get_wallet_button_types(),
                'std'           => 'publish',
            ),

             array(
                'id'          => 'novalnet_applepay_applepay_button_height',
                'name'        => __( 'Button Height ', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'type'          => 'number',
                'size'          => 'regular',
                'std'           => 40,
                'tooltip_title' => __( 'Button Height', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'tooltip_desc'  => __( 'Range from 30 to 64 pixels.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
                'autocomplete' => 'OFF',
                'min'          => 30,
                'max'          => 64,
            ),
           
        );
        $novalnet_applepay                     = apply_filters( 'edd_novalnet_applepay_settings', $novalnet_applepay );
        $gateway_settings['novalnet_applepay'] = $novalnet_applepay;
        return $gateway_settings;
    }
}
new Novalnet_Applepay();
?>
