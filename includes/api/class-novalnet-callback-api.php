<?php

/**
 * Novalnet Vendor script
 *
 * This script is used for processing the asynchronuous
 * parameters passed from Novalnet AG.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Callback_Api
 * @package     edd-novalnet-gateway
 * @Author      Novalnet AG
 * @Located     at /includes/api
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle the callback request triggered from Novalnet
 *
 * Calls from "edd_api_novalnet_callback"
 *
 * @since 1.1.0
 */
function novalnet_callback_api_process()
{
    global $wpdb, $order_reference, $callback_amount, $org_amount, $payment_level, $edd_options, $test_mode, $sum_amount, $payment_gateway;

    // Novalnet callback script starts.
    $request_param = array_map('trim', wp_unslash($_REQUEST)); // Input var okay.

    $test_mode = !empty($edd_options['novalnet_merchant_test_mode']) ? $edd_options['novalnet_merchant_test_mode'] : '';
    $vendor_script = new Novalnet_Callback_Api();

    // Get values passed in $_REQUEST.
    $request_param = $vendor_script->get_requested_params();

    // Order reference of given callback request.
    $order_reference = (array) $vendor_script->get_order_reference();

    if (!empty($order_reference['order_no'])) {
        list($payment_gateway, $callback_amount, $sum_amount, $org_amount, $payment_level) = $vendor_script->get_payment_data($request_param, $order_reference['order_no']);

        // Check for payment_type.
        if (!in_array($vendor_script->ary_request_params['payment_type'], $vendor_script->ary_payment_groups[$payment_gateway], true)) {
            $vendor_script->debug_error('Novalnet callback received. Payment type (' . $vendor_script->ary_request_params['payment_type'] . ') is not valid.');
        }

        $vendor_script->perform_subscription_stop_request($request_param);

        // Transaction cancellation process.
        $vendor_script->transaction_cancellation($request_param);

        // level 0 payments - Initial payments.
        $vendor_script->zero_level_process($request_param);

        // level 1 payments - Type of charge backs.
        $vendor_script->first_level_process($request_param);

        // level 2 payments - Type of payment.
        $vendor_script->second_level_process($request_param);

        $vendor_script->perform_subscription_reactivation_request($request_param);

        if ('100' !== $request_param['status'] || '100' !== $request_param['tid_status']) {
            $vendor_script->debug_error('Status ' . $request_param['status'] . ' is not valid: Only 100 is allowed');
        }
    } else {
        $nn_order_id = $request_param['order_no'];
        if (!empty($nn_order_id)) {
            $order = edd_get_order($nn_order_id);
            $tid_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s", $nn_order_id));
            if (empty($tid_details)) {
                $vendor_script->handle_communication_failure($order->gateway);
            }
        }
        /* Error section : Due to order reference not found from the shop database  */
        $vendor_script->debug_error('Novalnet callback received. Order Reference not exist!');
    } // End if.

    $vendor_script->debug_error('Callback script executed already. Refer order: ' . $order_reference['order_no']);
}

/**
 * Callback trigger for all payment
 */
class Novalnet_Callback_Api
{

    /**
     * Form request params.
     *
     * @var array
     */
    public $ary_request_params = array();

    /**
     * Array Type of payment available - Level : 0.
     *
     * @var array
     */
    public $ary_payments = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'PAYPAL', 'ONLINE_TRANSFER', 'ONLINE_BANK_TRANSFER', 'IDEAL', 'EPS', 'TWINT', 'GIROPAY', 'PRZELEWY24', 'CASHPAYMENT');

    /**
     * Array Type of Charge backs available - Level : 1.
     *
     * @var array
     */
    public $ary_chargebacks = array('GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK', 'RETURN_DEBIT_SEPA', 'REVERSAL', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'TWINT_REFUND', 'TWINT_CHARGEBACK', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'REFUND_BY_BANK_TRANSFER_EU', 'CASHPAYMENT_REFUND');

    /**
     * Array Type of CreditEntry payment and Collections available - Level : 2.
     *
     * @var array
     */
    public $ary_collection = array('INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'ONLINE_TRANSFER_CREDIT', 'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_SEPA', 'DEBT_COLLECTION_CREDITCARD', 'DEBT_COLLECTION_DE');

    /**
     * Form list of payment types as per payment method.
     *
     * @var array
     */
    public $ary_payment_groups = array(
        'novalnet_cc'                   => array('CREDITCARD', 'CREDITCARD_CHARGEBACK', 'CREDITCARD_BOOKBACK', 'CREDIT_ENTRY_CREDITCARD', 'DEBT_COLLECTION_CREDITCARD', 'SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE', 'TRANSACTION_CANCELLATION'),
        'novalnet_sepa'                 => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'GUARANTEED_SEPA_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE', 'TRANSACTION_CANCELLATION'),
        'novalnet_ideal'                => array('IDEAL', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_instantbank'          => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_onlinebanktransfer'   => array('ONLINE_BANK_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_paypal'               => array('PAYPAL', 'SUBSCRIPTION_STOP', 'PAYPAL_BOOKBACK', 'SUBSCRIPTION_REACTIVATE', 'TRANSACTION_CANCELLATION'),
        'novalnet_przelewy24'           => array('PRZELEWY24', 'PRZELEWY24_REFUND', 'TRANSACTION_CANCELLATION'),
        'novalnet_prepayment'           => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE', 'REFUND_BY_BANK_TRANSFER_EU'),
        'novalnet_invoice'              => array('INVOICE_START', 'GUARANTEED_INVOICE', 'GUARANTEED_INVOICE_BOOKBACK', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE', 'REFUND_BY_BANK_TRANSFER_EU', 'TRANSACTION_CANCELLATION', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_eps'                  => array('EPS', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_giropay'              => array('GIROPAY', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'),
        'novalnet_cashpayment'          => array('CASHPAYMENT', 'CASHPAYMENT_CREDIT', 'CASHPAYMENT_REFUND'),
        'novalnet_twint'                => array('TWINT', 'TWINT_REFUND', 'TWINT_CHARGEBACK'),
    );

    /**
     * Novalnet Transaction Cancellation catagory.
     *
     * @var array
     */

    public $cancellation = array(
        'TRANSACTION_CANCELLATION',
    );

    /**
     * Need check these params.
     *
     * @var array
     */
    public $params_required = array(
        'vendor_id' => '',
        'status' => '',
        'tid_status' => '',
        'payment_type' => '',
        'tid' => '',
    );

    /**
     * Subscription stop.
     *
     * @var array
     */
    public $ary_subscriptions = array('SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE', 'SUBSCRIPTION_PAUSE', 'SUBSCRIPTION_UPDATE');

    /**
     * Get all required action and filter to process callback script
     */
    public function __construct()
    {

        self::check_ip_address();

        $params = array_map('trim', $_REQUEST); // Input var okay.

        if (empty($params)) {
            self::debug_error('Novalnet callback received. No params passed over!');
        }

        if (!empty($params['subs_billing'])) {
            $this->params_required['signup_tid'] = '';
        } elseif (isset($params['payment_type']) && in_array($params['payment_type'], array_merge($this->ary_chargebacks, $this->ary_collection), true)) {
            $this->params_required['tid_payment'] = '';
        }

        $this->ary_request_params = self::validate_request_params($params);
    }

    /**
     * Validate the basic needed param
     *
     * @since 1.0.1
     * @access public
     * @param array $params Validate required param.
     * @return array $params Return validated param.
     */
    public function validate_request_params($params)
    {
        if (!empty($params)) {
            $value_nt_exist = array('reference', 'vendor_id', 'tid', 'status', 'tid_status', 'status_messge', 'payment_type', 'signup_tid');
            foreach ($value_nt_exist as $value) {
                if (!isset($params[$value])) {
                    $params[$value] = '';
                }
            }
            if (!$params['tid']) {
                $params['tid'] = $params['signup_tid'];
            }

            foreach ($this->params_required as $k => $v) {
                if (empty($params[$k])) {
                    self::debug_error('Required param ( ' . $k . '  ) missing!');
                } elseif (in_array($k, array('tid', 'tid_payment', 'signup_tid'), true) && !preg_match('/^\d{17}$/', $params[$k])) {
                    self::debug_error('Invalid TID [ ' . $params[$k] . ' ] for order: ' . $params['order_no']);
                }
            }

            // Validating payment_type.
            if (!in_array($params['payment_type'], array_merge($this->ary_payments, $this->ary_chargebacks, $this->ary_collection, $this->ary_subscriptions, $this->cancellation), true)) {
                self::debug_error('Payment type ( ' . $params['payment_type'] . ' ) is mismatched!');
            }

            if ('' !== $params['signup_tid'] && (isset($params['subs_billing']) && $params['subs_billing'] == 1)) {
                $params['shop_tid'] = $params['signup_tid'];
            } elseif (in_array($params['payment_type'], array_merge($this->ary_chargebacks, $this->ary_collection), true)) { // Invoice.
                $params['shop_tid'] = $params['tid_payment'];
            } elseif ('' !== $params['tid']) {
                $params['shop_tid'] = $params['tid'];
            }

            return $params;
        } // End if.
    }

    /**
     * Checks the client IP address
     *
     * @since 1.0.1
     * @access public
     */
    public function check_ip_address()
    {
        global $test_mode;
        $get_host_address = gethostbyname('pay-nn.de');
        if (empty($get_host_address)) {
            self::debug_error('Novalnet HOST IP missing');
        }

        $get_ip_address = self::get_remote_address_callback($get_host_address);
        if (($get_host_address !== $get_ip_address) && !$test_mode) {
            self::debug_error('Unauthorised access from the IP [' . $get_ip_address . ']');
        }
    }

    /**
     * Checks the client IP address
     *
     * @since 1.0.1
     * @access public
     */

    public static function get_remote_address_callback($get_host_address)
    {
        $ip_keys = array('HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                if (in_array($key, ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST'])) {
                    $forwardedIP = !empty(sanitize_text_field(wp_unslash($_SERVER[$key]))) ? explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) : [];
                    return in_array($get_host_address, array_map('trim', $forwardedIP)) ? $get_host_address : sanitize_text_field(wp_unslash($_SERVER[$key]));
                }
                return sanitize_text_field(wp_unslash($_SERVER[$key]));
            }
        }
    }

    /**
     * Mail notification
     *
     * @since 1.0.1
     * @access public
     * @param array $data  Send callback mail.
     */
    public function send_notify_mail($data)
    {
        global $edd_options;

        if (!empty($edd_options['novalnet_merchant_email'])) {
            $emails = new EDD_Emails();
            $email_to_addr = !empty($edd_options['novalnet_merchant_email_to']) ? trim($edd_options['novalnet_merchant_email_to']) : '';

            $email_subject = 'Novalnet Callback Script Access Report - ' . get_option('blogname');
            if ($data['comments'] && !empty($email_to_addr)) {
                $comments = $data['comments'];
                $message = html_entity_decode($comments);
                $emails->__set('heading', $email_subject);
                $emails->send($email_to_addr, $email_subject, $message);
            }
        }

        self::debug_error($data['comments']);
    }

    /**
     * Transaction cancellation.
     *
     * @param array $request_param  server response.
     */
    public function transaction_cancellation($request_param)
    {
        global $wpdb, $edd_options, $order_reference;

        if ('103' === $request_param['tid_status'] && 'TRANSACTION_CANCELLATION' === $request_param['payment_type'] && '103' !== $order_reference['gateway_status']) {
            /* translators: %1$s: date */
            $request_param['message'] = PHP_EOL . sprintf(__('The transaction has been canceled on %1$s.', 'edd-novalnet'), edd_novalnet_formatted_date());

            // Update order comments.
            $order_status = $edd_options['novalnet_onhold_cancel_status'];
            EDD()->session->set('novalnet_transaction_comments', $request_param['message']);
            edd_update_payment_status($order_reference['order_no'], $order_status);
            $this->update_comments($order_reference, $request_param['message']);
            add_concat_comments($request_param['order_no'], $request_param['message']);
            // Update gateway status.
            $wpdb->update(
                $wpdb->prefix . 'novalnet_transaction_detail',
                array(
                    'gateway_status' => $request_param['tid_status'],
                ),
                array(
                    'order_no' => $order_reference['order_no'],
                )
            ); // db call ok; no-cache ok.

            $this->send_notify_mail(array('comments' => $request_param['message']));
        }
    }

    /**
     * Get order details
     *
     * @since 1.0.1
     * @access public
     * @return array $order_ref Return order details to make callback success.
     */
    public function get_order_reference()
    {
        global $wpdb;

        $tid = isset($this->ary_request_params['shop_tid']) ? $this->ary_request_params['shop_tid'] : $this->ary_request_params['tid'];

        $order_id = isset($this->ary_request_params['order_no']) ? $this->ary_request_params['order_no'] : '';

        // Get recurring details.
        if (isset($this->ary_request_params['subs_billing']) && '1' === $this->ary_request_params['subs_billing'] || in_array($this->ary_request_params['payment_type'], array('SUBSCRIPTION_STOP', 'SUBSCRIPTION_REACTIVATE'), true)) {
            $recurring_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}novalnet_subscription_details WHERE recurring_tid=%s", $tid), ARRAY_A);
            $order_id = isset($recurring_details['order_no']) ? $recurring_details['order_no'] : '';
        }

        if (!empty($order_id)) {
            $order_ref = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid=%s OR order_no=%s", $tid, $order_id));
        } else {
            $order_ref = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid=%s", $tid));
        }
        return $order_ref;
    }

    /**
     * Handle communication failure transaction
     *
     * @param array $payment  payment name.
     */
    public function handle_communication_failure($payment)
    {

        global $edd_options, $wpdb;

        $test_mode = (int) (edd_is_test_mode() || !empty($edd_options[$payment . '_test_mode']) || !empty($this->ary_request_params['test_mode']));
        $key = !empty($this->ary_request_params['key']) ? $this->ary_request_params['key'] : $this->ary_request_params['payment_id'];

        $amount = str_replace('.', ',', sprintf('%.2f', $this->ary_request_params['amount'] / 100));

        if ('100' === $this->ary_request_params['status']) {

            novalnet_update_order_meta($this->ary_request_params['order_no'], '_edd_payment_gateway', $payment);
            novalnet_update_order_meta($this->ary_request_params['order_no'], '_nn_order_tid', $this->ary_request_params['tid']);

            $invoice_payments = array('novalnet_invoice', 'novalnet_prepayment');

            if (in_array($payment, $invoice_payments, true) || ('novalnet_paypal' === $payment && ('90' === $this->ary_request_params['tid_status'] || '85' === $this->ary_request_params['tid_status'])) || ('novalnet_przelewy24' === $payment && '86' === $this->ary_request_params['tid_status']) || ('novalnet_cashpayment' === $payment)) {
                novalnet_update_order_meta($this->ary_request_params['order_no'], '_nn_callback_amount', 0);
            } else {
                // Set the purchase to complete.
                novalnet_update_order_meta($this->ary_request_params['order_no'], '_nn_callback_amount', (int) novalnet_get_order_meta($this->ary_request_params['order_no'], '_edd_payment_total') * 100);
            }

            if (in_array($this->ary_request_params['tid_status'], array('91', '99', '98', '85'), true)) {
                $final_order_status = $edd_options['novalnet_onhold_success_status'];
            } elseif (in_array($this->ary_request_params['tid_status'], array('75', '90', '86'), true)) {
                $final_order_status = $edd_options[$payment . '_order_pending_status'];
            } else {
                $final_order_status = $edd_options[$payment . '_order_completion_status'];
                if ('41' === $key) {
                    $final_order_status = $edd_options[$payment . '_order_callback_status'];
                }
            }
            $novalnet_comments = '';
            $novalnet_comments .= novalnet_form_transaction_details($this->ary_request_params, $test_mode);
            if (in_array($payment, $invoice_payments, true)) {
                $novalnet_comments = novalnet_get_invoice_comments($this->ary_request_params, $amount, $novalnet_comments, true, true);
            }

            if ($payment === 'novalnet_cashpayment') {
                $novalnet_comments .= cashpayment_order_comments($this->ary_request_params);
            }

            $this->ary_request_params['amount'] = $amount;
            update_transaction_details($this->ary_request_params, $payment);

            $novalnet_comments = html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
            // Update Novalnet Transaction details into payment note.
            $post_comments = get_post($edd_options);
            $post_comments->post_excerpt .= $novalnet_comments;
            // Update Novalnet Transaction details into shop database.
            wp_update_post(
                array(
                    'ID' => $this->ary_request_params['order_no'],
                    'post_excerpt' => $novalnet_comments,
                )
            );
            EDD()->session->set('novalnet_transaction_comments', $novalnet_comments);
            edd_update_payment_status($this->ary_request_params['order_no'], $final_order_status);
            edd_insert_payment_note($this->ary_request_params['order_no'], $novalnet_comments);
            if ('publish' !== $final_order_status || !empty($this->ary_request_params['subs_id'])) {
                edd_trigger_purchase_receipt($this->ary_request_params['order_no']);
            }
        } else {

            novalnet_update_order_meta($this->ary_request_params['order_no'], '_edd_payment_gateway', $payment);
            novalnet_update_order_meta($this->ary_request_params['order_no'], '_nn_order_tid', $this->ary_request_params['tid']);

            $novalnet_comments = '';
            $novalnet_comments .= novalnet_form_transaction_details($this->ary_request_params, $test_mode);
            $novalnet_comments .= (isset($this->ary_request_params['status_text']) ? $this->ary_request_params['status_text'] : (isset($this->ary_request_params['status_desc']) ? $this->ary_request_params['status_desc'] : $this->ary_request_params['status_message']));

            $this->ary_request_params['amount'] = $amount;
            update_transaction_details($this->ary_request_params, $payment);

            $novalnet_comments = html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
            // Update Novalnet Transaction details into payment note.
            $post_comments = get_post($edd_options);
            $post_comments->post_excerpt .= $novalnet_comments;
            // Update Novalnet Transaction details into shop database.
            wp_update_post(
                array(
                    'ID' => $this->ary_request_params['order_no'],
                    'post_excerpt' => $novalnet_comments,
                )
            );
            EDD()->session->set('novalnet_transaction_comments', $novalnet_comments);
            edd_update_payment_status($this->ary_request_params['order_no'], 'abandoned');
            edd_insert_payment_note($this->ary_request_params['order_no'], $novalnet_comments);
        }

        edd_set_payment_transaction_id($this->ary_request_params['order_no'], $this->ary_request_params['tid']);
        $nn_comments = novalnet_get_order_meta($this->ary_request_params['order_no'], 'novalnet_transaction_comments');
        novalnet_update_order_meta($this->ary_request_params['order_no'], 'novalnet_transaction_comments', $nn_comments . PHP_EOL . $novalnet_comments);
        self::debug_error($novalnet_comments);
    }

    /**
     * Get given payment_type level for process
     *
     * @since 1.0.1
     * @access public
     */
    public function get_payment_type_level()
    {
        if (!empty($this->ary_request_params)) {
            if (in_array($this->ary_request_params['payment_type'], $this->ary_payments, true)) {
                return 0;
            } elseif (in_array($this->ary_request_params['payment_type'], $this->ary_chargebacks, true)) {
                return 1;
            } elseif (in_array($this->ary_request_params['payment_type'], $this->ary_collection, true)) {
                return 2;
            }
        }
        return false;
    }

    /**
     * Get request param from post
     *
     * @since 1.0.1
     * @access public
     */
    public function get_requested_params()
    {
        return $this->ary_request_params;
    }

    /**
     * Error message
     *
     * @since 1.0.1
     * @access public
     * @param string $message Print if the error persist while running.
     */
    public function debug_error($message = 'Authentication Failed!')
    {
        wp_send_json($message, 200);
    }

    /**
     * Creation for order for each recurring process
     *
     * @since 1.0.2
     * @access public
     * @param string $subscription_id Get subscription order.
     * @param array  $request_param Get post parent order.
     */
    public function recurring_order_creation($subscription_id, $request_param)
    {
        global $wpdb, $test_mode, $edd_options, $order_reference;
        $recurring_order = '';
        $subscription = new EDD_Subscription($subscription_id);
        $recurring_method = get_novalnet_payment($subscription->parent_payment_id);
        $trans_details = $wpdb->get_row($wpdb->prepare("SELECT termination_reason FROM {$wpdb->prefix}novalnet_subscription_details WHERE order_no=%s", $subscription->parent_payment_id), ARRAY_A); // db call ok; no-cache ok.
        $edd_recurring_version = preg_replace('/[^0-9.].*/', '', get_option('edd_recurring_version'));
        $get_times_billed = (version_compare($edd_recurring_version, '2.6', '<')) ? $subscription->get_total_payments() : $subscription->get_times_billed();

        if (((($get_times_billed < $subscription->bill_times) && 0 !== $subscription->bill_times) || '0' === $subscription->bill_times) && empty($trans_details['termination_reason'])) {

            // When a user makes a recurring payment.
            $subscription->add_payment(
                array(
                    'amount' => $subscription->recurring_amount,
                    'transaction_id' => $request_param['tid'],
                    'gateway' => $recurring_method,
                )
            );

            $subscription->renew();
            $recurring_order = $this->get_recurring_order($subscription);
            novalnet_update_order_meta($recurring_order, '_nn_order_tid', $request_param['tid']);
            novalnet_update_order_meta($recurring_order, '_edd_payment_total', $request_param['amount'] / 100);
            novalnet_update_order_meta($recurring_order, '_edd_payment_transaction_id', $recurring_order);
            $trans_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s", $subscription->parent_payment_id), ARRAY_A); // db call ok; no-cache ok.
            novalnet_update_order_meta($request_param['tid'], '_order_total', $request_param['amount'] / 100);
            novalnet_update_order_meta($subscription->parent_payment_id, '_nn_customer_id', $trans_details['product_id'] . '-' . $subscription->customer->user_id);

            $wpdb->insert(
                "{$wpdb->prefix}novalnet_transaction_detail",
                array(
                    'order_no' => $recurring_order,
                    'vendor_id' => $trans_details['vendor_id'],
                    'auth_code' => $trans_details['auth_code'],
                    'product_id' => $trans_details['product_id'],
                    'tariff_id' => $trans_details['tariff_id'],
                    'payment_id' => $trans_details['payment_id'],
                    'payment_type' => $recurring_method,
                    'tid' => $request_param['tid'],
                    'subs_id' => !empty($trans_details['subs_id']) ? $trans_details['subs_id'] : '',
                    'amount' => $request_param['amount'],
                    'callback_amount' => ('INVOICE_START' == $request_param['payment_type']) ? 0 : $request_param['amount'],
                    'currency' => $trans_details['currency'],
                    'gateway_status' => !empty($request_param['tid_status']) ? $request_param['tid_status'] : '',
                    'test_mode' => $request_param['test_mode'],
                    'customer_id' => $subscription->customer->user_id,
                    'customer_email' => $subscription->customer->email,
                    'date' => gmdate('Y-m-d H:i:s'),
                )
            ); // db call ok; no-cache ok.
            $novalnet_comments = novalnet_form_transaction_details($request_param, $request_param['test_mode']);

            if (in_array($request_param['payment_type'], array('INVOICE_START', 'GUARANTEED_INVOICE'), true)) {
                $request_param['order_no'] = $recurring_order;
                $novalnet_comments = novalnet_get_invoice_comments($request_param, sprintf('%0.2f', ($request_param['amount'] / 100)), $novalnet_comments, true, true);
                $novalnet_comments .= PHP_EOL . get_epc_qr($request_param);
                $ary_set_null_value = array('invoice_bankname', 'due_date', 'invoice_bankplace', 'invoice_iban', 'invoice_bic');
                foreach ($ary_set_null_value as $value) {
                    if (!isset($request_param[$value])) {
                        $request_param[$value] = '';
                    }
                }
            } // End if.

            $next_payment_date = '';
            $edd_recurring_version = preg_replace('/[^0-9.].*/', '', get_option('edd_recurring_version'));
            $get_times_billed = (version_compare($edd_recurring_version, '2.6', '<')) ? $subscription->get_total_payments() : $subscription->get_times_billed();
            if ((!empty($request_param['paid_until']) || !empty($request_param['next_subs_cycle'])) && $get_times_billed !== $subscription->bill_times) {
                $next_payment_date = __('Next charging date: ', 'edd-novalnet') . date_i18n(get_option('date_format'), strtotime(!empty($request_param['next_subs_cycle']) ? $request_param['next_subs_cycle'] : $request_param['paid_until']));
            }
            /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
            $callback_comments = PHP_EOL . sprintf(__('Subscription has been successfully renewed for the TID: %1$s with the amount %2$s on %3$s. The renewal TID is: %4$s', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), date_i18n(get_option('date_format'), strtotime(gmdate('Y-m-d'))), $request_param['tid']) . PHP_EOL;
            novalnet_update_order_meta($recurring_order, 'novalnet_transaction_comments', $novalnet_comments . PHP_EOL . $callback_comments . PHP_EOL . $next_payment_date);
            novalnet_update_order_meta($recurring_order, '_payment_method', $recurring_method);
            novalnet_update_order_meta($recurring_order, '_nn_version', NOVALNET_VERSION);
            edd_insert_payment_note($recurring_order, $novalnet_comments . $callback_comments . $next_payment_date);
            if (!empty($request_param['next_subs_cycle'])) {
                $wpdb->update(
                    $wpdb->prefix . 'novalnet_subscription_details',
                    array(
                        'next_payment_date' => gmdate('Y-m-d', strtotime($request_param['next_subs_cycle'])),
                    ),
                    array(
                        'order_no' => $order_reference['order_no'],
                    )
                );
            } // db call ok; no-cache ok.
            // Set the renewal order status.
            $final_order_status = 'edd_subscription';

            $exist_comments = $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM {$wpdb->posts} where ID =%s", $recurring_order)); // db call ok; no-cache ok.

            $add_comments = $exist_comments . PHP_EOL . $novalnet_comments . PHP_EOL;
            EDD()->session->set('novalnet_transaction_comments', $add_comments);
            $nn_order_notes = array(
                'ID' => $recurring_order,
                'post_excerpt' => $exist_comments . PHP_EOL . $novalnet_comments . PHP_EOL . $callback_comments . PHP_EOL . $next_payment_date,
            );
            $novalnet_comments = str_replace('\n', '<br / >', $nn_order_notes);
            wp_update_post($novalnet_comments);
            if ('publish' !== $final_order_status) {
                edd_trigger_purchase_receipt($recurring_order);
            }
            $edd_recurring_version = preg_replace('/[^0-9.].*/', '', get_option('edd_recurring_version'));
            $get_times_billed = (version_compare($edd_recurring_version, '2.6', '<')) ? $subscription->get_total_payments() : $subscription->get_times_billed();
            if ($get_times_billed == $subscription->bill_times && '0' != $subscription->bill_times) {
                // Cancel subscription in Novalnet server.
                $this->perform_subscription_cancel($request_param);
                $wpdb->update(
                    $wpdb->prefix . 'novalnet_subscription_details',
                    array(
                        'termination_reason' => 'Others',
                        'termination_at' => gmdate('Y-m-d H:i:s'),
                    ),
                    array(
                        'order_no' => $subscription->parent_payment_id,
                    )
                ); // db call ok; no-cache ok.
                $subscription->update(array(
                    'status' => 'expired'
                ));
            }
            EDD()->session->set('novalnet_transaction_comments', $nn_order_notes['post_excerpt']);

            edd_update_payment_status($recurring_order, $final_order_status);

            $this->send_notify_mail(array('comments' => $callback_comments . PHP_EOL . $next_payment_date));
        } else {
            self::debug_error(sprintf(__('The subscription has been already stopped or cancelled', 'edd-novalnet')));
        } // End if.
    }

    /**
     * Process Recurring subscription order id
     *
     * @since 1.0.2
     * @param array $subscription Get child payment count.
     */
    public function get_recurring_order($subscription)
    {
        foreach ($subscription->get_child_payments() as $value) {
            return $value->ID;
        }
    }

    /**
     * Process subscription cancel
     *
     * @since 1.0.2
     * @access public
     * @param array $request_param Get post params to be processed.
     */
    public function perform_subscription_cancel($request_param)
    {
        global $wpdb;
        $trans_details = $wpdb->get_row($wpdb->prepare("SELECT auth_code,product_id,tariff_id,payment_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid=%s", $request_param['shop_tid']), ARRAY_A); // db call ok; no-cache ok.
        $cancel_request = array(
            'vendor' => $request_param['vendor_id'],
            'auth_code' => $trans_details['auth_code'],
            'product' => $trans_details['product_id'],
            'tariff' => $trans_details['tariff_id'],
            'key' => $trans_details['payment_id'],
            'tid' => $request_param['shop_tid'],
            'cancel_sub' => 1,
            'cancel_reason' => __('Other', 'edd-novalnet'),
            'lang' => novalnet_shop_language(),
            'remote_ip' => novalnet_server_addr(),
        );
        novalnet_submit_request($cancel_request);
    }

    /**
     * Process subscription cancel
     *
     * @since 1.0.2
     * @access public
     * @param array $request_param Get post params to be processed.
     */
    public function perform_subscription_stop_request($request_param)
    {
        global $wpdb, $order_reference;
        $sub_billing = !empty($request_param['subs_billing']) ? $request_param['subs_billing'] : '0';
        $subs_billing_check = ('1' == $sub_billing || '0' == $sub_billing);

        if (($subs_billing_check && in_array($request_param['payment_type'], array('SUBSCRIPTION_STOP', 'TRANSACTION_CANCELLATION'), true)) || ($subs_billing_check && '100' !== $request_param['status'] && in_array($request_param['payment_type'], array('SUBSCRIPTION_STOP', 'TRANSACTION_CANCELLATION'), true))) {

            if ($request_param['payment_type'] == 'TRANSACTION_CANCELLATION') {
                /* translators: %1$s date */
                $request_param['callback_comments'] = PHP_EOL . sprintf(__('The transaction has been canceled on %1$s.', 'edd-novalnet'), date_i18n(get_option('date_format'), strtotime(gmdate('Y-m-d')))) . PHP_EOL;
                edd_update_payment_status($order_reference['order_no'], 'abandoned');
            } else {
                /* translators: %1$s: tid, %2$s: date */
                $request_param['callback_comments'] = PHP_EOL . sprintf(__('Subscription has been stopped for the TID: %1$s on %2$s.', 'edd-novalnet'), $request_param['signup_tid'], date_i18n(get_option('date_format'), strtotime(gmdate('Y-m-d')))) . PHP_EOL;
            }
            // subscription cancel reason
            $cancel_reason = '';
            if (!empty($request_param['termination_reason'])) {
                /* translators: %1$s: reason */
                $cancel_reason = PHP_EOL . sprintf(__('Subscription has been canceled due to: %1$s','edd-novalnet'), $request_param['termination_reason']) . PHP_EOL;
                $request_param['callback_comments'] .= $cancel_reason;
            }
            edd_insert_payment_note($order_reference['order_no'], $request_param['callback_comments']);
            add_concat_comments($request_param['order_no'], $request_param['callback_comments']);
            $exist_comments = $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM $wpdb->posts where ID =%s", $order_reference['order_no'])); // db call ok; no-cache ok.

            $nn_order_notes = array(
                'ID' => $order_reference['order_no'],
                'post_excerpt' => $exist_comments . $request_param['callback_comments'],
            );
            $wpdb->update(
                $wpdb->prefix . 'novalnet_subscription_details',
                array(
                    'termination_reason' => $cancel_reason,
                    'termination_at' => gmdate('Y-m-d H:i:s'),
                ),
                array(
                    'order_no' => $order_reference['order_no'],
                )
            ); // db call ok; no-cache ok.

            wp_update_post($nn_order_notes);
            $subscription_details = get_subscription_details($order_reference['order_no']);
            if (isset($subscription_details['subs_plugin_enabled'])) {
                $subscription = new EDD_Subscription($subscription_details['subs_id']);
                $subscription->cancel();
            }
            EDD()->session->set('novalnet_transaction_comments', $request_param['callback_comments']);
            // Cancel subscription in Novalnet server.
            $this->send_notify_mail(array('comments' => $request_param['callback_comments']));
            $this->debug_error($request_param['callback_comments']);
        }
    }

    /**
     * Process subscription cancel
     *
     * @since 1.1.3
     * @param array $request_param Get post params to be processed.
     */
    public function perform_subscription_reactivation_request($request_param)
    {

        global $wpdb, $order_reference, $edd_options, $payment_gateway;
        $subscription_details = get_subscription_details($order_reference['order_no']);
        if (isset($subscription_details['subs_plugin_enabled'])) {
            $subscription = new EDD_Subscription($subscription_details['subs_id']);
        }
        if ('SUBSCRIPTION_REACTIVATE' === $request_param['payment_type'] && ('100' === $request_param['status'] || ('100' !== $request_param['status'] && !empty($request_param['subs_billing'])))) {
            $wpdb->update(
                $wpdb->prefix . 'novalnet_subscription_details',
                array(
                    'termination_reason' => '',
                    'termination_at' => '',
                ),
                array(
                    'order_no' => $order_reference['order_no'],
                )
            );
            $next_payment_date = !empty($request_param['next_subs_cycle']) ? $request_param['next_subs_cycle'] : $request_param['paid_until'];
            /* translators: %1$s: tid, %2$s: date, %3$s: date  */
            $request_param['callback_comments'] = PHP_EOL . sprintf(__('Subscription has been reactivated for the TID: %1$s on %2$s.', 'edd-novalnet'), $request_param['signup_tid'], date_i18n(get_option('date_format'), strtotime(gmdate('Y-m-d')))) . PHP_EOL;
            $wpdb->update(
                $wpdb->prefix . 'edd_subscriptions',
                array(
                    'status' => 'active',
                    'expiration' => $next_payment_date,
                ),
                array(
                    'parent_payment_id' => $subscription->parent_payment_id,
                )
            ); // db call ok; no-cache ok.
            edd_insert_payment_note($order_reference['order_no'], $request_param['callback_comments']);
            add_concat_comments($request_param['order_no'], $request_param['callback_comments']);
            $exist_comments = $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM $wpdb->posts where ID =%s", $order_reference['order_no']));
            $nn_order_notes = array(
                'ID' => $order_reference['order_no'],
                'post_excerpt' => $exist_comments . $request_param['callback_comments'],
            );
            EDD()->session->set('novalnet_transaction_comments', $request_param['callback_comments']);
            // Reactive subscription in Novalnet server.
            edd_update_payment_status($order_reference['order_no'], $edd_options[$payment_gateway . '_order_completion_status']);
            wp_update_post($nn_order_notes);
            $this->send_notify_mail(array('comments' => $request_param['callback_comments']));
            $this->debug_error($request_param['callback_comments']);
        }
    }

    /**
     * Initial level process
     *
     * @since 1.0.2
     * @param array $request_param Get post params to be processed.
     */
    public function zero_level_process($request_param)
    {
        global $wpdb, $edd_options, $payment_gateway, $order_reference, $callback_amount, $org_amount, $payment_level;

        $test_mode = (int) (edd_is_test_mode() || !empty($edd_options[$payment_gateway . '_test_mode']) || !empty($this->ary_request_params['test_mode']));

        if (0 === $payment_level) {
            // Process recurring order for the parent order.
            if (!empty($request_param['subs_billing']) && '1' === $request_param['subs_billing']) {
                $subscription_details = get_subscription_details($order_reference['order_no']);
                if (isset($subscription_details['subs_plugin_enabled'])) {
                    $response = $this->recurring_order_creation($subscription_details['subs_id'], $request_param);
                    $this->debug_error($response);
                }
            } elseif (in_array($request_param['payment_type'], array('GUARANTEED_INVOICE', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'CREDITCARD', 'PAYPAL'), true) && in_array($request_param['tid_status'], array('100', '91', '99'), true) && '100' === $request_param['status'] && in_array($order_reference['gateway_status'], array('75', '91', '99', '85', '98'), true) && $request_param['tid_status'] !== $order_reference['gateway_status']) {
                /* translators: %1$s: date */
                $request_param['message'] = PHP_EOL . sprintf(__('The transaction has been confirmed on %1$s', 'edd-novalnet'), edd_novalnet_formatted_date()) . PHP_EOL;
                $order_status = ('GUARANTEED_INVOICE' === $request_param['payment_type']) ? $edd_options[$payment_gateway . '_order_callback_status'] : $edd_options[$payment_gateway . '_order_completion_status'];
                if ('75' === $order_reference['gateway_status'] && in_array($request_param['tid_status'], array('91', '99'), true)) {
                    $order_status = $edd_options['novalnet_onhold_success_status'];
                    /* translators: %1$s: tid, %2$s: date */
                    $request_param['message'] = PHP_EOL . sprintf(__('The transaction status has been changed from pending to on-hold for the TID: %1$s on %2$s.', 'edd-novalnet'), $request_param['tid'], edd_novalnet_formatted_date()) . PHP_EOL;
                }
                $request_param['message'] .= novalnet_form_transaction_details($request_param, $test_mode);
                if (in_array($request_param['payment_type'], array('INVOICE_START', 'GUARANTEED_INVOICE'), true) && in_array($order_reference['gateway_status'], array('75', '91'), true) && in_array($request_param['tid_status'], array('91', '100'))) {
                    $request_param['order_no'] = $order_reference['order_no'];
                    $request_param['message'] .= novalnet_get_invoice_comments($request_param, $org_amount, '', true, true);
                }
                $wpdb->update(
                    $wpdb->prefix . 'novalnet_transaction_detail',
                    array(
                        'gateway_status' => $request_param['tid_status'],
                    ),
                    array(
                        'order_no' => $order_reference['order_no'],
                    )
                ); // db call ok; no-cache ok.
                EDD()->session->set('novalnet_transaction_comments', $request_param['message']);
                edd_update_payment_status($order_reference['order_no'], $order_status);
                $this->update_comments($order_reference, $request_param['message']);
                if (in_array($request_param['payment_type'], array('INVOICE_START', 'GUARANTEED_INVOICE'), true) && in_array($order_reference['gateway_status'], array('75', '91'), true) && in_array($request_param['tid_status'], array('91', '100'))) {
                    $request_param['message'] .= PHP_EOL . get_epc_qr($request_param);
                }
                novalnet_update_order_meta($request_param['order_no'], 'novalnet_transaction_comments', $request_param['message']);
                $this->send_notify_mail(array('comments' => $request_param['message']));
            } else if (in_array($request_param['payment_type'], array('PAYPAL', 'PRZELEWY24'), true) && ('100' === $request_param['tid_status'])) {

                if ($callback_amount < $org_amount) {
                    novalnet_update_order_meta($order_reference['order_no'], '_nn_callback_amount', $org_amount);
                    /* translators: %1$s: tid, %2$s: amount, %3$s: date */
                    $request_param['message'] = PHP_EOL . sprintf(__('Transaction updated successfully for the TID: %1$s on %2$s.', 'edd-novalnet'), $request_param['shop_tid'], gmdate('Y-m-d H:i:s')) . PHP_EOL;
                    $wpdb->update(
                        $wpdb->prefix . 'novalnet_transaction_detail',
                        array(
                            'gateway_status' => '100',
                        ),
                        array(
                            'order_no' => $order_reference['order_no'],
                        )
                    ); // db call ok; no-cache ok.

                    $status = (('90' === $request_param['tid_status'] || '85' === $request_param['tid_status']) ? $edd_options[$payment_gateway . '_order_pending_status'] : (('100' === $request_param['tid_status']) ? $edd_options[$payment_gateway . '_order_completion_status'] : ''));
                    EDD()->session->set('novalnet_transaction_comments', $request_param['message']);
                    edd_update_payment_status($order_reference['order_no'], $status);
                    $this->update_comments($order_reference, $request_param['message']);
                    add_concat_comments($request_param['order_no'], $request_param['message']);
                    $this->send_notify_mail(array('comments' => $request_param['message']));
                } else {
                    $this->debug_error('Order already Paid.');
                }
            } elseif ('PRZELEWY24' === $request_param['payment_type'] && ('100' !== $request_param['tid_status'] || '100' !== $request_param['status'])) {
                // Przelewy24 cancel.
                if ('86' === $request_param['tid_status']) {
                    $this->debug_error('Payment type ( ' . $request_param['payment_type'] . ' ) is not applicable for this process!');
                }
                $cancellation_msg = get_status_desc($request_param);
                /* translators: %s: reason  */
                $request_param['message'] = PHP_EOL . sprintf(__('The transaction has been canceled due to: %s', 'edd-novalnet'), $cancellation_msg) . PHP_EOL;

                $wpdb->update(
                    $wpdb->prefix . 'novalnet_transaction_detail',
                    array(
                        'gateway_status' => $request_param['tid_status'],
                    ),
                    array(
                        'order_no' => $order_reference['order_no'],
                    )
                ); // db call ok; no-cache ok.
                EDD()->session->set('novalnet_transaction_comments', $request_param['message']);
                edd_update_payment_status($order_reference['order_no'], 'abandoned');
                $this->update_comments($order_reference, $request_param['message']);
                add_concat_comments($request_param['order_no'], $request_param['message']);
                $this->send_notify_mail(array('comments' => $request_param['message']));
            } else {
                $this->debug_error('Payment type ( ' . $request_param['payment_type'] . ' ) is not applicable for this process!');
            } // End if.
        } // End if.
    }

    /**
     * First level process
     *
     * @since 1.0.2
     * @param array $request_param Get post params to be processed.
     */
    public function first_level_process($request_param)
    {
        global $wpdb, $order_reference, $payment_level;
        if (1 === $payment_level && '100' === $request_param['status'] && '100' === $request_param['tid_status']) {
            $refund_order_status = "refunded";
            if (in_array($request_param['payment_type'], array('CREDITCARD_BOOKBACK', 'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK', 'PAYPAL_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND', 'TWINT_REFUND'), true)) {
                /* translators: %1$s: parent tid, %2$s: amount, %3$s: tid  */
                $request_param['message'] = PHP_EOL . sprintf(__('Refund has been initiated for the TID: %1$s with the amount %2$s. New TID: %3$s for the refunded amount.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), $request_param['tid']) . PHP_EOL;
                $old_value = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT refund_amount, amount FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid = %s",
                        $request_param['shop_tid']
                    ),
                    ARRAY_A
                );
                $original_amount = $old_value['amount'];
                $refund_amount = $old_value['refund_amount'] + $request_param['amount'];
                $wpdb->update(
                    $wpdb->prefix . 'novalnet_transaction_detail',
                    array(
                        'refund_amount' => $refund_amount,
                    ),
                    array(
                        'tid' => $request_param['shop_tid'],
                    )
                );
                if ($original_amount > $refund_amount) {
                    $refund_order_status = "partially_refunded";
                }
            } else if ('RETURN_DEBIT_SEPA' === $request_param['payment_type']) {
                /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                $request_param['message'] = PHP_EOL . sprintf(__('Chargeback executed for return debit of TID:%1$s with the amount %2$s on %3$s. The subsequent TID: %4$s.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;
            } else if ('REVERSAL' === $request_param['payment_type']) {
                /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                $request_param['message'] = PHP_EOL . sprintf(__('Chargeback executed for reversal of TID: %1$s with the amount %2$s on %3$s. The subsequent TID: %4$s.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;
            } else {
                /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                $request_param['message'] = PHP_EOL . sprintf(__('Chargeback executed successfully for the TID: %1$s amount: %2$s on %3$s. The subsequent TID: %4$s.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;
            }

            edd_update_payment_status($order_reference['order_no'], $refund_order_status);
            $this->update_comments($order_reference, $request_param['message']);
            add_concat_comments($request_param['order_no'], $request_param['message']);
            $this->send_notify_mail(array('comments' => $request_param['message']));
        }
    }

    /**
     * Second level process
     *
     * @since 1.0.2
     * @param array $request_param Get post params to be processed.
     */
    public function second_level_process($request_param)
    {
        global $wpdb, $order_reference, $payment_level, $callback_amount, $org_amount, $sum_amount, $payment_gateway, $edd_options;

        if (2 === $payment_level && '100' === $request_param['status'] && '100' === $request_param['tid_status']) {
            $novalnet_comments = '';
            if (in_array($request_param['payment_type'], array('INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'ONLINE_TRANSFER_CREDIT'))) {
                if ($callback_amount < $org_amount) {
                    /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                    $request_param['message'] = PHP_EOL . sprintf(__('Credit has been successfully received for the TID: %1$s with an amount of %2$s on %3$s. New TID: %4$s for the credit.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;

                    if ('ONLINE_TRANSFER_CREDIT' === $request_param['payment_type']) {
                        /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                        $request_param['message'] = PHP_EOL . sprintf(__('Credit has been successfully received for the TID: %1$s with an amount of %2$s on %3$s. New TID: %4$s for the credit.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;
                    }

                    novalnet_update_order_meta($order_reference['order_no'], '_nn_callback_amount', $sum_amount);
                    $wpdb->update(
                        $wpdb->prefix . 'novalnet_transaction_detail',
                        array(
                            'callback_amount' => $sum_amount,
                        ),
                        array(
                            'order_no' => $order_reference['order_no'],
                        )
                    ); // db call ok; no-cache ok.

                    $this->update_comments($order_reference, $request_param['message']);
                    add_concat_comments($request_param['order_no'], $request_param['message']);
                    if ($sum_amount >= (int) $org_amount) {
                        EDD()->session->set('novalnet_transaction_comments', $request_param['message']);
                        $payment_status = !empty($edd_options[$payment_gateway . '_order_callback_status']) ? $edd_options[$payment_gateway . '_order_callback_status'] : $edd_options[$payment_gateway . '_order_completion_status'];
                        edd_update_payment_status($order_reference['order_no'], $payment_status);
                    }
                    $this->send_notify_mail(array('comments' => $request_param['message']));
                } // End if.
                $this->debug_error('Callback script executed already. Refer order: ' . $order_reference['order_no']);
            } // End if.
            else {
                /* translators: %1$s: parent tid, %2$s: amount, %3$s: date, %4$s: tid  */
                $request_param['message'] = PHP_EOL . sprintf(__('Credit has been successfully received for the TID: %1$s with an amount of %2$s on %3$s. New TID: %4$s for the credit.', 'edd-novalnet'), $request_param['shop_tid'], edd_currency_filter(edd_format_amount($request_param['amount'] / 100)), gmdate('Y-m-d H:i:s'), $request_param['tid']) . PHP_EOL;
                // Update order comments.
                $this->update_comments($order_reference, $request_param['message']);
                add_concat_comments($request_param['order_no'], $request_param['message']);
                // Send notification mail to the configured E-mail.
                $this->send_notify_mail(array('comments' => $request_param['message']));
            }
            $this->debug_error('Payment type ( ' . $request_param['payment_type'] . ') is not applicable for this process!');
        } // End if.
    }

    /**
     * Update comments
     *
     * @since 1.0.2
     * @param array  $order_reference Get post id.
     * @param array  $message Get message form payment processed.
     * @param string $insert_payment_note Insert payment note.
     */
    public function update_comments($order_reference, $message, $insert_payment_note = true)
    {
        global $wpdb;
        if ($insert_payment_note) {
            edd_insert_payment_note($order_reference['order_no'], $message);
        }
        $comments = $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM {$wpdb->posts} where ID =%s", $order_reference['order_no'])); // db call ok; no-cache ok.

        $order_notes = array(
            'ID' => $order_reference['order_no'],
            'post_excerpt' => $comments . $message,
        );
        wp_update_post($order_notes);
    }

    /**
     * Update comments
     *
     * @since 1.0.2
     * @param array $request_param Get all post params.
     * @param array $order_reference Get post id.
     */
    public function get_payment_data(&$request_param, $order_reference)
    {

        $payment_gateway = get_novalnet_payment($order_reference);
        $request_param['currency'] = isset($request_param['currency']) ? edd_currency_symbol(strtoupper($request_param['currency'])) : edd_get_currency();
        $callback_amount = novalnet_get_order_meta($order_reference, '_nn_callback_amount');
        $sum_amount = (isset($request_param['amount']) && !empty($request_param['amount']) ? (int) $request_param['amount'] : '0') + (int) $callback_amount;
        $org_amount = round(edd_get_payment_amount($order_reference), 2) * 100;
        $payment_level = $this->get_payment_type_level();
        return array(
            $payment_gateway,
            $callback_amount,
            $sum_amount,
            $org_amount,
            $payment_level,
        );
    }
}
