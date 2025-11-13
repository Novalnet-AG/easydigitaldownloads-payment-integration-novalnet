<?php

/**
 * Novalnet Subscription Actions
 *
 * This file is used for handling the subscription actions under
 * edd recurring plugins.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Subscriptions
 * @package     edd-novalnet-gateway
 * @Author      Novalnet AG
 * @located at  /includes
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Get Recurring order to function.
 */
class Novalnet_Subscriptions
{

    /**
     *  Get request params in query params.
     *
     * @var array $query_params Create subscription order.
     */
    public $query_params;

    /**
     *  Request values are processed using action and filter.
     */
    public function __construct()
    {
        $this->query_params = wp_unslash($_REQUEST); // Input var okay.

        // transction id and profile id are processed in back-end subscription order details.
        add_action('edd_recurring_post_create_payment_profiles', array($this, 'novalnet_create_payment_profiles'));

        // cancel of a subscription product and sends request to server.
        add_action('edd_cancel_subscription', array($this, 'novalnet_process_cancellation'));

        // status when not cancelled to be appear.
        add_filter('edd_subscription_can_cancel', array($this, 'novalnet_can_cancel'), 10, 2);

        // Update payment method.
        add_filter('edd_subscription_can_update', array($this, 'can_update'), 10, 2);
        add_action('edd_recurring_update_payment_form', array($this, 'update_payment_method_form'), 10, 2);
        add_action('edd_recurring_update_subscription_payment_method', array($this, 'process_novalnet_payment_method_update'), 10, 3);

        // adds custom url to both front-end and back-end.
        add_filter('edd_subscription_cancel_url', array($this, 'novalnet_cancel_url'), 10, 2);

        // Subscription script access to cancel order and hide details in subscription order back-end.
        add_action('wp_enqueue_scripts', array($this, 'novalnet_subscription_enqueue_scripts'), 10);
        add_action('admin_enqueue_scripts', array($this, 'novalnet_subscription_enqueue_scripts'), 10);

        // Error notice to be displayed in back-end.
        add_action('admin_notices', array($this, 'novalnet_subscription_notices'));
    }

    /**
     * Update payment method for subscription in Novalnet payments
     *
     * @param boolean $ret           Show update payment option.
     * @param object  $subscription  The subscription object.
     * @return bool
     */
    public function can_update($ret, $subscription)
    {

        global $wpdb;

        $order_data = $wpdb->get_row($wpdb->prepare("SELECT payment_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s", $subscription->parent_payment_id), ARRAY_A); // db call ok; no-cache ok.

        if (! $ret && novalnet_check_string($subscription->gateway) && edd_is_gateway_active($subscription->gateway) && ! empty($subscription->profile_id) && in_array($subscription->status, array('active', 'trialling'), true) && (isset($order_data['payment_id']) && ! in_array($order_data['payment_id'], array('41', '40'), true))) {
            return true;
        }
        return $ret;
    }

    /**
     * Display the payment form for update payment method
     *
     * @param  object $subscription The subscription object.
     * @return void
     */
    public function update_payment_method_form($subscription)
    {

        global $edd_options;

        if (novalnet_check_string($subscription->gateway)) {
            if (in_array($subscription->gateway, array('novalnet_invoice', 'novalnet_sepa', 'novalnet_prepayment', 'novalnet_cc', 'novalnet_paypal'), true)) {
                $class_name  = ucwords($subscription->gateway, '_');
                $payment_obj = new $class_name();
                $payment_obj->display_form(true);
            }
            EDD()->session->set('novalnet_update_payment_method', NULL);
        }
    }

    /**
     * Process the update payment form
     *
     * @param  int  $user_id            User ID.
     * @param  int  $subscription_id    Subscription ID.
     * @param  bool $verified           Sanity check that the request to update is coming from a verified source.
     * @return void
     */
    public function process_novalnet_payment_method_update($user_id, $subscription_id, $verified)
    {

        global $edd_options, $wpdb;

        $request      = wp_unslash($_POST);
        $subscription = new EDD_Subscription($subscription_id);
        $subscriber   = new EDD_Recurring_Subscriber($subscription->customer_id);

        if (1 !== $verified || empty($subscription->id) || empty($subscriber->id) || ($user_id != $subscriber->user_id)) {
            wp_die(esc_html(__('Invalid subscription id', 'edd-novalnet')));
        }
        if (novalnet_check_string($subscription->gateway)) {

            $customer_details   = get_user_meta($subscription->customer_id);
            $billing_details = isset($customer_details['_edd_user_address']) ? unserialize($customer_details['_edd_user_address'][0]) : '';

            // Update the customer details
            add_filter('edd_purchase_form_required_fields', array($this, 'purchase_form_required_fields'), 10, 1);
            $valid_data    = edd_purchase_form_validate_fields();
            $customer_data = edd_get_purchase_form_user($valid_data);

            $subs_id = $wpdb->get_var($wpdb->prepare("SELECT subs_id FROM {$wpdb->prefix}novalnet_subscription_details WHERE order_no=%s", $subscription->parent_payment_id));

            $customer_id = novalnet_get_order_meta($subscription->parent_payment_id, '_nn_customer_id');

            if (!empty($customer_id)) {
                $customer_id = $customer_id;
            } else {
                $customer_id = $subscription->customer_id;
            }

            $params        = array(
                'amount'             => 0,
                'gender'             => 'u',
                'customer_no'        => $customer_id,
                'first_name'         => $customer_data['user_first'],
                'last_name'          => $customer_data['user_last'],
                'email'              => $subscription->customer->email,
                'street'             => ! empty($customer_data['address']['line1']) ? $customer_data['address']['line1'] : $billing_details['line1'],
                'city'               => ! empty($customer_data['address']['city']) ? $customer_data['address']['city'] : $billing_details['city'],
                'zip'                => ! empty($customer_data['address']['zip']) ? $customer_data['address']['zip'] : $billing_details['zip'],
                'country_code'       => ! empty($customer_data['address']['country']) ? $customer_data['address']['country'] : $billing_details['country'],
                'country'            => ! empty($customer_data['address']['country']) ? $customer_data['address']['country'] : $billing_details['country'],
                'search_in_street'   => 1,
                'create_payment_ref' => 1,
                'subs_py_update'     => $subs_id,
                'order_no'           => $subscription->parent_payment_id,
                'test_mode'          => (int) (edd_is_test_mode() || ! empty($edd_options[$subscription->gateway . '_test_mode'])),
            );
            $params = array_merge(get_merchant_details($subscription->gateway), $params, novalnet_get_system_data());

            if (in_array($subscription->gateway, array('novalnet_invoice', 'novalnet_prepayment'), true)) {
                if ('novalnet_invoice' === $subscription->gateway) {
                    $params['invoice_type'] = 'INVOICE';
                } else {
                    $params['invoice_type'] = 'PREPAYMENT';
                }
                $params['invoice_ref']  = 'BNR-' . $params['product'] . '-' . $subscription->parent_payment_id;
            } elseif ('novalnet_sepa' === $subscription->gateway) {
                if (empty($request['novalnet_sepa_holder']) || empty($request['novalnet_sepa_iban'])) {
                    edd_set_error('edd_recurring_novalnet', __('Your account details are invalid', 'edd-novalnet'));
                    wp_safe_redirect($subscription->get_update_url());
                    die();
                }
                $params['bank_account_holder']  = $request['novalnet_sepa_holder'];
                $params['iban']                 = $request['novalnet_sepa_iban'];

                if (!empty($request['novalnet_sepa_bic'])) {
                    $params['bic']                 = $request['novalnet_sepa_bic'];
                }
            } elseif ('novalnet_cc' === $subscription->gateway) {
                if (empty($request['novalnet_cc_hash']) || empty($request['novalnet_cc_uniqueid'])) {
                    edd_set_error('edd_recurring_novalnet', $request['novalnet_cc_error']);
                    wp_safe_redirect($subscription->get_update_url());
                    die();
                }
                $params['pan_hash']  = $request['novalnet_cc_hash'];
                $params['unique_id'] = $request['novalnet_cc_uniqueid'];
            }

            if (('novalnet_cc' === $subscription->gateway && 1 == $request['novalnet_cc_do_redirect']) || 'novalnet_paypal' === $subscription->gateway) {
                $paygate_url   = '';
                if ('novalnet_cc' === $subscription->gateway) {
                    $paygate_url = 'https://payport.novalnet.de/pci_payport';
                    if (isset($edd_options['novalnet_cc_enforced_3d']) && $edd_options['novalnet_cc_enforced_3d'] == 'yes') {
                        $params['enforce_3d'] = 1;
                    }
                } elseif ('novalnet_paypal' === $subscription->gateway) {
                    $paygate_url    = 'https://payport.novalnet.de/paypal_payport';
                }

                $home_url    = wp_parse_url(home_url());
                $current_uri = "{$home_url['scheme']}://{$home_url['host']}" . add_query_arg(NULL, NULL);

                EDD()->session->set('novalnet_update_payment_method', true);
                $subs_update = array(
                    'update_payment' => true,
                    'return_url'     => $current_uri
                );
                $redirect_params = novalnet_get_redirect_param($subscription->gateway, $params, $subs_update);
                $params = array_merge($params, $redirect_params);

                // Redirect to the paygate url.
                novalnet_get_redirect($paygate_url, $params);
            }

            // Send the transaction request to the novalnet server.
            $response = novalnet_submit_request($params);

            // Update the novalnet response to the shop.
            novalnet_check_response($response, true);
        }
    }

    /**
     * Validate required customer details
     *
     * @return array
     */
    public function purchase_form_required_fields($required_fields)
    {
        if (! empty($required_fields)) {
            unset($required_fields['edd_email'], $required_fields['edd_first']);
        }
        return $required_fields;
    }

    /**
     * Subscription error notice
     *
     * @since 1.1.0
     */
    public function novalnet_subscription_notices()
    {
        $get_error = wp_unslash($_GET);
        if ((isset($get_error['novalnet-message']) && ! empty($get_error['novalnet-message'])) || (isset($get_error['novalnet-error']) && ! empty($get_error['novalnet-error']))) { // Input var okay.
            $message = ! empty($get_error['novalnet-message']) ? $get_error['novalnet-message'] : $get_error['novalnet-error']; // Input var okay.
            $code    = ! empty($get_error['novalnet-message']) ? 'updated' : 'error'; // Input var okay.
            echo '<div class="' . esc_attr($code) . '"><p>' . esc_attr($message) . '</p></div>';
        }
    }

    /**
     * Adding subscription script
     *
     * @since 1.1.0
     */
    public function novalnet_subscription_enqueue_scripts()
    {

        // Enqueue style & script.
        wp_enqueue_script('edd-novalnet-subscription-script', NOVALNET_PLUGIN_URL . 'assets/js/novalnet-subscription.min.js', array('jquery'), NOVALNET_VERSION, true);
        wp_enqueue_style('edd-novalnet-subscription-style', NOVALNET_PLUGIN_URL . 'assets/css/novalnet-checkout.css', array(), NOVALNET_VERSION, true);

        $integrity_hash = 'sha384-H+4f08YULcAwQWBEjS8VCA5rWf1k/bG9LlzoLolkK5VezXnao8eq0cL71Z37yAbG';
        add_filter('script_loader_tag', function ($tag, $handle) use ($integrity_hash) {
            if ('edd-novalnet-subscription-script' === $handle) {
                return str_replace(
                    ' src=',
                    ' integrity="' . esc_attr($integrity_hash) . '" crossorigin="anonymous" src=',
                    $tag
                );
            }
            return $tag;
        }, 10, 2);

        $params = array(
            'reason_list'          => $this->novalnet_subscription_cancel_form(), // Display Subscription cancel reason.
            'admin'                => is_admin(),
            'error_message'        => __('Please select the reason for subscription cancellation', 'edd-novalnet'),
            'novalnet_subs_cancel' => __('The subscription has been already stopped or cancelled', 'edd-novalnet'),
        );
        if (is_admin() && (! empty($_REQUEST['page']) && 'edd-subscriptions' === $_REQUEST['page'] && ! empty($_REQUEST['id']))) { // Input var okay.
            $novalnet_subs                   = new EDD_Subscription(absint($_REQUEST['id'])); // Input var okay.
            $params['hide_backend_details'] = novalnet_check_string($novalnet_subs->gateway);
            $params['can_update']           = (novalnet_check_string($novalnet_subs->gateway) && in_array($novalnet_subs->status, array('cancelled', 'completed', 'expired', 'failing'))) ? 'true' : 'false';
        }
        wp_localize_script('edd-novalnet-subscription-script', 'novalnet_subscription', $params);
    }

    /**
     * Subscription cancellation reason form
     *
     * @since 1.1.0
     * @return string
     */
    public function novalnet_subscription_cancel_form()
    {
        $form = '<div class="novalnet_loader" style="display:none"></div> <form method="POST" id="novalnet_subscription_cancel" style="float:right"><select id="novalnet_subscription_cancel_reason" name="novalnet_subscription_cancel_reason" style="width:125px;height:40px;">';

        // Append subscription cancel reasons.
        $form .= '<option value="">' . __('--Select--', 'edd-novalnet') . '</option>';
        foreach (novalnet_subscription_cancel_list() as $reason) {
            $form .= '<option value="' . $reason . '">' . $reason . '</option>';
        }
        $form .= '</select><br/><input type="submit" class="button novalnet_cancel" style="background:#0085ba;border-color:#0073aa #006799 #006799;color:#fff;" onclick="return novalnet_hide_button(this);" id="novalnet_cancel" value=' . __('Cancel', 'edd-novalnet') . '></form>';
        return $form;
    }

    /**
     * Add novalnet values to subscription params
     *
     * @since 1.1.0
     * @param array $subscriptions Creates subscription details in core ID's.
     */
    public function novalnet_create_payment_profiles($subscriptions)
    {
        // Gateways loop through each download and creates a payment profile and then sets the profile ID.
        $purchase_key = $subscriptions->purchase_data['purchase_key'];
        $user_id      = $subscriptions->purchase_data['user_info']['id'];
        if (novalnet_check_string($subscriptions->purchase_data['gateway'])) {
            $subscriptions->offsite = true;
            foreach ($subscriptions->subscriptions as $key => $val) {
                $subscriptions->subscriptions[$key]['profile_id'] = md5($purchase_key . $user_id);
            }
        }
    }

    /**
     * Cancel process of a subscription
     *
     * @since 1.1.0
     * @param array $data Allows subscription params to form object.
     */
    public function novalnet_process_cancellation($data)
    {
        global $wpdb;

        $subscription = new EDD_Subscription(absint($data['sub_id']));
        if ('cancelled' !== $subscription->status) {
            $reason_id       = isset($this->query_params['novalnet_subscription_cancel_reason']) ? $this->query_params['novalnet_subscription_cancel_reason'] : 'Other';
            $result_set      = $wpdb->get_row($wpdb->prepare("SELECT vendor_id, auth_code, product_id, tariff_id, payment_id,tid FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s ORDER BY id DESC", $subscription->parent_payment_id), ARRAY_A); // db call ok; no-cache ok.
            $cancel_request  = array(
                'vendor'        => $result_set['vendor_id'],
                'auth_code'     => $result_set['auth_code'],
                'product'       => $result_set['product_id'],
                'tariff'        => $result_set['tariff_id'],
                'tid'           => $result_set['tid'],
                'key'           => $result_set['payment_id'],
                'cancel_sub'    => 1,
                'cancel_reason' => $reason_id,
                'lang'          => novalnet_shop_language(),
            );
            $cancel_response = novalnet_submit_request($cancel_request);
            if ('100' === $cancel_response['status']) {
                /* translators: %s: Cancel reason */
                $cancel_msg = "\n" . sprintf(__('Subscription has been canceled due to: %s', 'edd-novalnet'), $cancel_request['cancel_reason']);
                $wpdb->update(
                    $wpdb->prefix . 'novalnet_subscription_details',
                    array(
                        'termination_reason' => $cancel_request['cancel_reason'],
                        'termination_at'     => gmdate('Y-m-d H:i:s'),
                    ),
                    array(
                        'order_no' => $subscription->parent_payment_id,
                    )
                ); // db call ok; no-cache ok.
                $subscription->cancel();
                if (is_admin()) {
                    $url = add_query_arg(
                        array(
                            'novalnet-message' => $cancel_request['cancel_reason'],
                            'id'               => $subscription->id,
                        ),
                        admin_url('edit.php?post_type=download&page=edd-subscriptions')
                    );
                }
            } else {
                $cancel_msg = (isset($cancel_response['status_text']) ? $cancel_response['status_text'] : (isset($cancel_response['status_desc']) ? $cancel_response['status_desc'] : ''));
                if (is_admin()) {
                    $url = add_query_arg(
                        array(
                            'novalnet-error' => $cancel_msg,
                            'id'             => $subscription->id,
                        ),
                        admin_url('edit.php?post_type=download&page=edd-subscriptions')
                    );
                }
            }
            edd_insert_payment_note($subscription->parent_payment_id, $cancel_msg);
            if (isset($cancel_msg) && is_admin()) {
                echo '<div id="notice" class="updated"><p>' . esc_html($cancel_msg) . '</p></div>';
            }
        } // End if().
        if (is_admin()) {
            wp_safe_redirect($url);
            exit;
        } else {
            wp_safe_redirect(
                remove_query_arg(
                    array('_wpnonce', 'edd_action', 'sub_id'),
                    add_query_arg(
                        array(
                            'edd-message' => 'cancelled',
                        )
                    )
                )
            );
            exit;
        }
    }

    /**
     * Cancel of a subscription based on status and Novalnet payments
     *
     * @since 1.1.0
     * @param string $can_cancel Show cancel option other than cancel status.
     * @param array  $subscriptions  Params to cancel a subscription.
     */
    public function novalnet_can_cancel($can_cancel, $subscriptions)
    {
        $edd_recurring_version = preg_replace('/[^0-9.].*/', '', get_option('edd_recurring_version'));
        $get_times_billed      = (version_compare($edd_recurring_version, '2.6',  '<')) ? $subscriptions->get_total_payments() : $subscriptions->get_times_billed();
        // Back-end cancel subscription show based on status for Novalnet Payment.
        return (novalnet_check_string($subscriptions->gateway) && (! in_array($subscriptions->status, array('cancelled', 'completed', 'expired', 'failing'), true) && $get_times_billed !== $subscriptions->bill_times)) ? true : $can_cancel;
    }

    /**
     * Append custom value to both front-end and back-end of a cancel process
     *
     * @since 1.1.0
     * @param string $url               Gets subscription url.
     * @param array  $subscriptions     Params to cancel a subscription.
     * @return $url
     */
    public function novalnet_cancel_url($url, $subscriptions)
    {

        // Back-end cancel subscription show based on status for Novalnet Payment.
        if (novalnet_check_string($subscriptions->gateway)) {
            if (false !== strpos($url, 'edit.php')) {
                $url_array = explode('edit.php?', $url);
                $url       = $url_array['0'] . 'edit.php?novalnet_subscription=true&' . $url_array['1'];
            } else {
                $url = $url . '&novalnet_subscription=true';
            }
        }
        return $url;
    }
}
new Novalnet_Subscriptions();
