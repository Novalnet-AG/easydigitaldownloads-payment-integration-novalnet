<?php
/**
 * Novalnet Googlepay Payment
 *
 * This gateway is used for real time processing of Googlepay Payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Googlepay
 * @package     edd-novalnet-gateway
 * @author      Novalnet AG
 * @located     at  /includes/gateways
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Googlepay payment processed
 */
class Novalnet_Googlepay
{

    /**
     * Get all required action and filter to process Googlepay
     */
    public function __construct()
    {

        add_action('update_option_novalnet_settings', array($this, 'update_novalnet_settings'));
        add_filter('edd_payment_gateways', array($this, 'register_novalnet_googlepay'), 1, 1);
        add_action('edd_novalnet_googlepay_cc_form', array($this, 'display_form'));
        add_action('edd_gateway_novalnet_googlepay', array($this, 'novalnet_googlepay_process_payment'));
        add_action('wp_ajax_novalnet_refresh_googlepay', array($this, 'novalnet_refresh_googlepay'));
        add_action('wp_ajax_nopriv_novalnet_refresh_googlepay', array($this, 'novalnet_refresh_googlepay'));
        // Plugin script actions.
        add_action('wp_enqueue_scripts', array($this, 'novalnet_googlepay_script_functions'));
        if (is_admin()) {
            add_filter('edd_settings_sections_gateways', array($this, 'register_novalnet_googlepay_gateway'), 1, 1);
            add_filter('edd_settings_gateways', array($this, 'register_novalnet_googlepay_settings'), 1, 1);
        }
    }

    /**
     * Add Novalnet function scripts in front-end
     *
     * @since 1.1.0
     */
    public function novalnet_googlepay_script_functions()
    {

        global $edd_options;

        // Novalnet Payment SDK.
        wp_enqueue_script(
            'edd-novalnet-payment-script',
            'https://cdn.novalnet.de/js/v3/payment-1.1.2.js',
            array('jquery'),
            NOVALNET_VERSION,
            true
        );

        // Google Pay JS.
        wp_enqueue_script(
            'edd-novalnet-googlepay-script',
            NOVALNET_PLUGIN_URL . 'assets/js/novalnet-googlepay.min.js',
            array(
                'jquery',
                'edd-novalnet-payment-script',
            ),
            NOVALNET_VERSION,
            true
        );

        $integrity_hash = 'sha384-ta50yyJEyDu+Hd17TBTHClwz4Qm3POKoGrkpcW5WvjVK1eOw4J2FNx308W46/omd';
        add_filter('script_loader_tag', function ($tag, $handle) use ($integrity_hash) {
            if ('edd-novalnet-googlepay-script' === $handle) {
                return str_replace(
                    ' src=',
                    ' integrity="' . esc_attr($integrity_hash) . '" crossorigin="anonymous" src=',
                    $tag
                );
            }
            return $tag;
        }, 10, 2);

        $googlepay_settings = get_wallet_configurations('googlepay', $edd_options);

        wp_localize_script(
            'edd-novalnet-googlepay-script',
            'novalnet_googlepay',
            $googlepay_settings
        );
    }

    /**
     * Update the registered payment settings while saving
     *
     * @since 1.1.0
     * @access public
     */
    public static function update_novalnet_settings()
    {
        // Update googlepay configuraion fields.
        update_option(self::register_novalnet_googlepay_settings());
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @access public
     * @param  array $gateway_sections Get Googlepay payment to append in default gateways.
     * @return array $gateway_sections Returns Googlepay in along with gateway payments.
     */
    public function register_novalnet_googlepay_gateway($gateway_sections)
    {
        $gateway_sections['novalnet_googlepay'] = __('Novalnet Google Pay', 'easy-digital-downloads-payment-gateway-by-novalnet');
        return $gateway_sections;
    }

    /**
     * Register the gateway payment for Googlepay
     *
     * @since 1.0.1
     * @param array $gateways Allows payment details.
     * @return array  $gateways Show Googlepay payment in front-end and back-end.
     */
    public function register_novalnet_googlepay($gateways)
    {

        $novalnet_googlepay = array(
            'novalnet_googlepay' => array(
                'admin_label' => __('Novalnet Google Pay', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'checkout_label' => __('Google Pay', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'supports' => array(),
            ),
        );
        $novalnet_googlepay = apply_filters('edd_register_novalnet_googlepay_gateway', $novalnet_googlepay);
        $gateways = array_merge($gateways, $novalnet_googlepay);
        return $gateways;
    }

    public function novalnet_refresh_googlepay()
    {

        global $edd_options;

        $config = get_wallet_configurations(
            'googlepay',
            $edd_options
        );

        $has_free_trial = false;
        foreach (edd_get_cart_contents() as $item) {
            if (!empty($item['options']['recurring']['trial_period'])) {
                $has_free_trial = true;
                break;
            }
        }
        $amount = $has_free_trial ? 0 : $_POST['total'];
        if (isset($_POST['total'])) {

            $config['amount'] = $amount * 100;

        }

        $currency = edd_currency_symbol();
        $article_details = array();
        $cart_items = edd_get_cart_contents();

        foreach ($cart_items as $item) {

            $download_id = $item['id'];
            $quantity = $item['quantity'];

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
                'label' => $product_details,
                'amount' => edd_novalnet_amount_as_string($amount),
                'type' => 'SUBTOTAL',
            );

            if (!empty($item['options']['recurring']['signup_fee'])) {

                $signup_fee = $item['options']['recurring']['signup_fee'];

                $article_details[] = array(
                    'label' => $product_name . ' Signup Fee',
                    'amount' => edd_novalnet_amount_as_string($signup_fee),
                    'type' => 'SUBTOTAL',
                );
            }
        }
        $tax = edd_get_cart_tax();

        if ($tax > 0) {

            $article_details[] = array(
                'label' => 'Tax',
                'amount' => edd_novalnet_amount_as_string($_POST['tax']),
                'type' => 'TAX',
            );
        }


        $discounts = edd_get_cart_discounts();

        if (!empty($discounts)) {

            $article_details[] = array(
                'label' => 'Coupon: ' . $_POST['coupon_code'],
                'amount' => edd_novalnet_amount_as_string($_POST['coupon_amount']),
                'type' => 'DISCOUNT',
            );
        }

        $config['article_details'] = wp_json_encode($article_details);
        wp_send_json($config);

    }

    /**
     * Register the action to display the payment form
     *
     * @since 1.1.0
     */
    public function display_form($is_update_payment_method = false)
    {
        global $edd_options;

        $language = get_bloginfo('language');
        $language = substr($language, 0, 2);
        $test_mode = $test_mode = (int) (
            (function_exists('edd_is_test_mode') && edd_is_test_mode())
            || !empty($edd_options['novalnet_googlepay_test_mode'])
        );
        $information = !empty($edd_options['novalnet_googlepay_information']) ? trim($edd_options['novalnet_googlepay_information']) : '';
        $payment_method_name = __('Google Pay', 'easy-digital-downloads-payment-gateway-by-novalnet');
        ?>
        <fieldset id="edd-novalnet-googlepay-wrap">
            <div class="edd-payment-icons">
                <?php
                echo esc_html($payment_method_name);
                novalnet_display_payment_logo('novalnet_googlepay', $payment_method_name);
                ?>
            </div>

            <p>
                <label>
                    <span class="edd-description">
                        <?php echo esc_html($information); ?>
                    </span>
                </label>
            </p>

            <?php if ($test_mode): ?>
                <p>
                    <label for="novalnet_test_mode_desc">
                        <span class="edd-description" style="color:red; font-size:90%;">
                            <?php echo esc_html(__('The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet')); ?>
                        </span>
                    </label>
                </p>
            <?php endif; ?>



            <div id="googlepay_container"></div>

            <input type="hidden" id="novalnet_googlepay_response" name="novalnet_googlepay_response" value="" />

        </fieldset>
        <?php
        do_action('edd_after_cc_fields');
        if ($is_update_payment_method) {
            edd_terms_agreement();
            edd_privacy_agreement();
        }
        ;

    }

    /**
     * Register the action to initiate and process the payment
     *
     * @since 1.0.1
     * @param array $purchase_data Get customer details to payment.
     */
    public function novalnet_googlepay_process_payment($purchase_data)
    {


        global $edd_options;
        // Get configuration data.

        $response = json_decode(
            stripslashes($_POST['novalnet_googlepay_response']),
            true
        );


        $payment_data = novalnet_get_merchant_data($purchase_data, 'novalnet_googlepay');
        $customer_data = novalnet_get_customer_data($purchase_data);
        $card_data = $this->novalnet_get_googlepay_data($response);
        $system_data = novalnet_get_system_data();

        $params = array_merge($payment_data, $card_data, $customer_data, $system_data);



        // Process the onhold product.
        if (isset($edd_options['novalnet_googlepay_manual_limit']) && 'authorize' === $edd_options['novalnet_googlepay_manual_limit']) {
            if (isset($edd_options['novalnet_googlepay_manual_check'])) {
                $payment_data['amount'] = (novalnet_digits_check($edd_options['novalnet_googlepay_manual_check'])) ? $payment_data['amount'] : 0;
                if ($payment_data['amount'] >= $edd_options['novalnet_googlepay_manual_check']) {
                    $params['on_hold'] = 1;
                }
            } else {
                $params['on_hold'] = 1;
            }
        }

        if (isset($response['transaction']['doRedirect']) && (sanitize_text_field(wp_unslash($response['transaction']['doRedirect'])) == 1)) {
            EDD()->session->set('novalnet_googlepay_do_redirect', sanitize_text_field(wp_unslash($response['transaction']['doRedirect'])));
            $paygate_url = 'https://payport.novalnet.de/pci_payport';
            // Get redirect payment data.
            $redirect_param = novalnet_get_redirect_param($purchase_data['gateway'], $payment_data);
            $params = array_merge($params, $redirect_param);

            // Create the subscription order for the subscription product.

            novalnet_check_subscription($purchase_data, $params);

            // Redirect to the paygate url.
            novalnet_get_redirect($paygate_url, $params);

        } else {

            // Create the subscription order for the subscription product
            novalnet_check_subscription($purchase_data, $params);

            // Send the transaction request to the novalnet server
            $parsed_response = novalnet_submit_request($params);

            if ('100' === $parsed_response['status']) {
                novalnet_check_response($parsed_response);
            } else {
                novalnet_transaction_failure($parsed_response);
            }
        }

    }

    /** Get Googlepay values
     *
     * @since 1.0.1
     */
    public function novalnet_get_googlepay_data($response)
    {

        if (!empty($response['transaction']['token'])) {
            return array(
                "wallet_token" => $response['transaction']['token'],
            );
        } else {
            edd_set_error(
                'basic_validation',
                __('Your Google Pay details are invalid', 'easy-digital-downloads-payment-gateway-by-novalnet')
            );

            edd_send_back_to_checkout('?payment-mode=novalnet_googlepay');

        }
    }

    /**
     * Adds the settings of the Novalnet Googlepay section
     *
     * @since 1.1.0
     * @param array $gateway_settings Back-end configuration list.
     * @return array $gateway_settings Save back-end configuration's.
     */
    public static function register_novalnet_googlepay_settings($gateway_settings)
    {

        $novalnet_googlepay = array(
            array(
                'id' => 'novalnet_googlepay_settings',
                'name' => '<strong><font color="#1874CD">' . __('Novalnet Googlepay', 'easy-digital-downloads-payment-gateway-by-novalnet') . '</font> </strong>',
                'desc' => __('Configure the gateway settings', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'header',
            ),
            array(
                'id' => 'novalnet_googlepay_test_mode',
                'name' => __('Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'checkbox',
                'tooltip_title' => __('Enable test mode', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'easy-digital-downloads-payment-gateway-by-novalnet'),
            ),
            array(
                'id' => 'novalnet_googlepay_manual_limit',
                'name' => __('Payment action', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'select',
                'std' => 'capture',
                'tooltip_title' => __('Payment action', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'options' => array(
                    'capture' => __('Capture', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                    'authorize' => __('Authorize', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                ),
                'size' => 'regular',
            ),
            array(
                'id' => 'novalnet_googlepay_manual_check',
                'name' => __('Minimum transaction limit for authorization', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'number',
                'size' => 'regular',
                'desc' => __('<p>(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_title' => __('Minimum transaction limit for authorization', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
            ),

            array(
                'id' => 'novalnet_googlepay_information',
                'name' => __('Notification for the buyer', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'textarea',
                'size' => 'regular',
                'desc' => __('Amount will be booked from your card after successful authentication.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
            ),
            array(
                'id' => 'novalnet_googlepay_order_completion_status',
                'name' => __('Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'select',
                'options' => edd_get_payment_statuses(),
                'std' => 'publish',
                'tooltip_title' => __('Completed order status', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('Status to be used for successful orders.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
            ),
            array(
                'id' => 'novalnet_googlepay_merchantid',
                'name' => __('Google Merchant ID', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'text',
                'size' => 'regular',
                'desc' => __( 'Please note that Googles merchant identifier is required for processing the payment method in the live environment. Googles merchant identifier is issued after registration with the <a href="https://pay.google.com/business/console/">Google Pay and Wallet Console</a>. See <a href="https://developers.google.com/pay/api/web/guides/test-and-deploy/request-prod-access">Request production access</a> for more information about the approval process and obtaining a Google merchant identifier. The registration also involves submitting the integration with sufficient screen-shots, so collect this information by enabling the payment method in test mode. To suppress the validation of this field while saving the configuration, use this test identifier BCR2DN4XXXTN7FSI for testing and submission of your integration to Google.', 'easy-digital-downloads-payment-gateway-by-novalnet' ),
            ),
            array(
                'id' => 'novalnet_googlepay_businessname',
                'name' => __('Business name', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'text',
                'size' => 'regular',
                'std' => get_bloginfo('name'),
                'tooltip_title' => __('Business name', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('The business name is rendered in the Google Pay payment sheet, and this text will appear as PAY "BUSINESS NAME" so that the customer knows where he is paying to.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
            ),

            array(
                'name' => '<strong><font color="#1874CD">' . __('Button Design', 'easy-digital-downloads-payment-gateway-by-novalnet') . '</font> </strong>',
                'type' => 'header',
                'id' => 'novalnet_css_main_settings',
            ),

            array(
                'id' => 'novalnet_googlepay_google_pay_button_type',
                'name' => __('Button Type', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'select',
                'options' => get_wallet_button_types(),
                'std' => 'publish',
            ),

            array(
                'id' => 'novalnet_googlepay_google_pay_button_height',
                'name' => __('Button Height ', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'type' => 'number',
                'size' => 'regular',
                'std' => 40,
                'tooltip_title' => __('Button Height', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'tooltip_desc' => __('Range from 40 to 100 pixels.', 'easy-digital-downloads-payment-gateway-by-novalnet'),
                'autocomplete' => 'OFF',
                'min' => 40,
                'max' => 100,
            ),

        );
        $novalnet_googlepay = apply_filters('edd_novalnet_googlepay_settings', $novalnet_googlepay);
        $gateway_settings['novalnet_googlepay'] = $novalnet_googlepay;
        return $gateway_settings;
    }
}
new Novalnet_Googlepay();
?>
