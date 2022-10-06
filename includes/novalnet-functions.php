<?php
/**
 * Handling Novalnet validation / process functions
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @package     edd-novalnet-gateway
 * @Author      Novalnet AG
 * @located     at  /includes/
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
    // Get novalnet server request.
    add_action( 'edd_api_valid_query_modes', 'novalnet_add_api_mode', 10 );
    add_action( 'edd_api_public_query_modes', 'novalnet_add_api_mode', 10 );
    add_action( 'edd_api_output_data', 'novalnet_log_api_data', 10, 2 );
    add_action( 'edd_api_output_before', 'novalnet_handle_api', 10, 1 );

    // Update subscription order.
    add_filter( 'edd_get_success_page_uri', 'novalnet_subscription_url' );
    add_filter( 'edd_recurring_pre_record_signup_args', 'novalnet_signup_args', 10, 2 );

    // Global configuration validation.
    add_action( 'edd_settings_tab_bottom_gateways_novalnet_global_config', 'novalnet_admin_global_config_error' );

    // For subscription product restrict unwanted payments from Novalnet.
    add_filter( 'edd_enabled_payment_gateways', 'remove_novalnet_payment' );

    // Get comments from post values.
    add_action( 'edd_payment_receipt_before', 'novalnet_transaction_detail_checkout' );

    // To show Novalnet Transaction comments in admin mail.
    add_filter( 'edd_sale_notification', 'novalnet_sale_notification', 10, 2 );

    // To add Novalnet transaction detail in mail content for purchase receipt.
    add_filter( 'edd_gateway_checkout_label', 'novalnet_transaction_detail_email', 10, 2 );

    // To display back-end comments in proper alignment.
    add_filter( 'the_comments', 'novalnet_backend_comments' );

    // Get default gateway.
    add_filter( 'edd_default_gateway', 'novalnet_set_default_gateway' );

    // Add renewal periods in the shop hook as it is not included in shop structure.
    add_filter( 'edd_subscription_renewal_expiration', 'novalnet_add_period', 10, 3 );

    // Plugin update action.
    add_action( 'admin_init', 'novalnet_update_action' );
    add_action( 'edd_payment_receipt_after_table', 'novalnet_barzahlen_scripts' );

    // Adapt refund checkbox and refund transaction
    add_action( 'edd_after_submit_refund_table', 'edd_novalnet_refund_checkbox' );
    add_filter( 'edd_refund_order', 'edd_novalnet_refund', 10, 3 );
/**
 * Actions to initialize Server request
 *
 * @since 1.1.1
 * @param array $data Get request and form $data.
 * @param array $endpoint End point will show taken path time.
 * @return array
 */
function novalnet_log_api_data( $data, $endpoint ) {
    if ( in_array( $endpoint, array( 'novalnet_redirect_response', 'products', 'novalnet_callback', 'novalnet_update_payment_method' ), true ) ) {
        $data = wp_unslash( $_REQUEST ); // Input var okay.
    }
    return $data;
}

/**
 * Handling for subscription process
 *
 * @param array  $args  Get the subscription order details.
 * @param object $item  Get the subscription product details.
 * return @array
 */
function novalnet_signup_args( $args, $item ) {

    global $edd_options;

    $params = EDD()->session->get( $item->purchase_data['gateway'] . '_subscription' );

    if ( ! empty( $params ) && novalnet_check_string( $item->purchase_data['gateway'] ) ) {

		$novalnet_cc_do_redirect = EDD()->session->get( 'novalnet_cc_do_redirect' );

        EDD()->session->set( $item->purchase_data['gateway'] . '_subscription', null );
        EDD()->session->set( 'novalnet_cc_do_redirect' , null );
        if ( ! empty( $args['parent_payment_id'] ) ) {
            $params['order_no'] = $args['parent_payment_id'];
        }

        if ( ( 'novalnet_paypal' === $item->purchase_data['gateway'] ) ||  ('novalnet_cc' === $item->purchase_data['gateway'] && 1 == $novalnet_cc_do_redirect ) ) {
            EDD()->session->set( 'novalnet_redirect_params', $params );
        } else {
            $parsed_response = novalnet_submit_request( $params );
            EDD()->session->set( 'novalnet_response', $parsed_response );
        }
        EDD()->session->set( 'novalnet_subscription_process', true );
    }
    if ( 'pending' === $args['status'] ) {
        $args['status'] = 'active';
    }

    return $args;
}

/**
 * Actions to initialize Vendor script
 *
 * @since 1.1.1
 * @param array $accepted Get request and form $data.
 * @return array
 */
function novalnet_add_api_mode( $accepted ) {
    $accepted [] = 'novalnet_callback';
    $accepted [] = 'novalnet_redirect_response';
    $accepted [] = 'novalnet_update_payment_method';
    return $accepted;
}

/**
 * Actions to settings configuration in admin plugin
 *
 * @since 1.0.1
 * @param  string $links    Gets to Novalnet admin portal.
 * @return string  $links    Login to see merchant credentials.
 */
function novalnet_action_links( $links ) {

    // Add configuration link in plugin page.
    $links [] = '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=novalnet_global_config' ) . '">' . __( 'Configuration', 'edd-novalnet' ) . '</a>';
    return $links;
}

/**
 * Actions to initialize api handler
 *
 * @since 1.0.1
 * @param array $data Get request and form $data.
 */
function novalnet_handle_api( $data ) {
    switch ( $data['edd-api'] ) {
        case 'products':
        case 'novalnet_redirect_response':
        case 'novalnet_update_payment_method':
            if ( in_array( $data['edd-api'], array( 'novalnet_redirect_response', 'products' ), true) ) {
                $order_data = EDD()->session->get( 'edd_purchase' );
                $return_url = edd_get_checkout_uri();
            } elseif ( 'novalnet_update_payment_method' === $data['edd-api'] ) {
                $order_data = EDD()->session->get( 'novalnet_update_payment_method' );
                $return_url = $data['return_url'];
            }
            if ( empty( $order_data ) ) {
                header_remove( 'Set-Cookie' );
                $redirect  = array();
                $redirect['edd-api']  = $data['edd-api'];
                $redirect  = array_merge( $redirect, $data );
                $redirect_url  = add_query_arg( $redirect, $return_url );
                header( 'Location: ' . $redirect_url );
                die();
            } else {
                if ( in_array( $data['edd-api'], array( 'novalnet_redirect_response', 'products' ), true) )  {
                    novalnet_get_redirect_response();
                } elseif ( 'novalnet_update_payment_method' === $data['edd-api'] ) {
                    novalnet_get_redirect_response( true );
                }
            }
            break;
        case 'novalnet_callback':
            // Include the callback script files.
            include_once NOVALNET_PLUGIN_DIR . '/includes/api/class-novalnet-callback-api.php';
            // Initiate the callback function.
            novalnet_callback_api_process();
            break;
    }
}

/**
 * Update status message
 *
 * @since 1.1.3
 * @param array $request Get all post params.
 */
function get_status_desc( $request ) {
    if ( ! empty( $request['status_text'] ) ) {
        return $request['status_text'];
    } elseif ( ! empty( $request['status_desc'] ) ) {
        return $request['status_desc'];
    } elseif ( ! empty( $request['status_message'] ) ) {
        return $request['status_message'];
    } else {
        return __( 'Payment was not successful. An error occurred.', 'edd-novalnet' );
    }
}

/**
 * Actions to show the barzahlen overlay
 *
 * @since 1.1.3
 * @param object $post  Get the order object.
 */
function novalnet_barzahlen_scripts( $post ) {
    global $wpdb;
    if ( 'novalnet_cashpayment' === novalnet_get_order_meta( $post->ID, '_edd_payment_gateway' ) ) {
        $transaction_id = novalnet_get_order_meta( $post->ID, '_nn_order_tid' );
        $result         = $wpdb->get_row( $wpdb->prepare( "SELECT test_mode FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid=%s ORDER BY id DESC", $transaction_id ), ARRAY_A );
        $url            = ( '1' === $result['test_mode'] ) ? 'https://cdn.barzahlen.de/js/v2/checkout-sandbox.js' : 'https://cdn.barzahlen.de/js/v2/checkout.js';
        $token          = EDD()->session->get( 'cp_checkout_token' );
        echo "<script src='$url' data-token='$token' class='bz-checkout'></script><style>#bz-checkout-modal { position: fixed !important; }</style><button id='barzahlen_button' class='bz-checkout-btn'>" . esc_html( __( 'Pay now with Barzahlen/viacash', 'edd-novalnet' ) ) . '</button>';
    }
}

/**
 * Actions to perform once on Plug-in deactivation
 *
 * @since 1.0.1
 */
function novalnet_deactivate_plugin() {

    global $wpdb;
    $config_settings             = get_option( 'edd_settings' );
    $config_settings['gateways'] = isset( $config_settings['gateways'] ) ? $config_settings['gateways'] : array();
    $config_tmp                  = array_merge( $config_settings, $config_settings['gateways'] );

    foreach ( $config_tmp as $key => $value ) {
        if ( novalnet_check_string( $key ) ) {
            if ( isset( $config_settings[ $key ] ) ) {
                unset( $config_settings[ $key ] );
            } else {
                unset( $config_settings['gateways'][ $key ] );
            }
        }
    }
    $wpdb->query( "delete from {$wpdb->options} where option_name like '%novalnet%'" ); // db call ok; no-cache ok.
    update_option( 'edd_settings', $config_settings );
}

/**
 * When reloading the page after install.
 *
 * @since 1.1.1
 */
function novalnet_update_action() {

    $current_db_version = get_option( 'novalnet_db_version' );
    $show_update_page   = get_option( 'novalnet_version_update' );
    if ( ! empty( $current_db_version ) && version_compare( $current_db_version, NOVALNET_VERSION, '!=' ) ) {
        // Redirect to updated information page.
        novalnet_activation_process();
    } elseif ( $show_update_page ) {
        delete_option( 'novalnet_version_update' );
        wp_safe_redirect( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=novalnet_global_config' ) );
        exit();
    }
}

/**
 * To get remote/server Ip address
 *
 * @param   string $type get the ipaddress type.
 * @return mixed
 */
function novalnet_server_addr( $type = 'REMOTE_ADDR' ) {
    // Check to determine the IP address type.
    if ( 'SERVER_ADDR' === $type ) {
        if ( empty( $_SERVER['SERVER_ADDR'] ) ) {
            // Handled for IIS server.
            $ip_address  = gethostbyname( $_SERVER['SERVER_NAME'] );
        } else {
            $ip_address  = $_SERVER['SERVER_ADDR'];
        }
    } else {
        // For remote address.
        $ip_address = get_remote_address();
        return $ip_address;
    }
    return $ip_address;
}

/**
 * To get remote IP address
 *
 * @return mixed
 */
function get_remote_address() {
    foreach ( array( 'HTTP_X_FORWARDED_HOST', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
            foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                return trim( $ip );
            }
        }
    }
}

/**
 * Checks for the given string in given text
 *
 * @since 1.1.0
 * @param  string $string Check given string contains name as novalnet.
 * @param  string $data   Get payment method name to avoid other payment's.
 * @return boolean
 */
function novalnet_check_string( $string, $data = 'novalnet' ) {
    return ( false !== strpos( $string, $data ) );
}

/**
 * Throws admin side empty value in global configuration of Novalnet payment
 *
 * @since 1.0.1
 */
function novalnet_admin_global_config_error() {
    global $edd_options;

    // Check for Global configuration.
    if ( empty( $edd_options['novalnet_merchant_id'] ) || ! novalnet_digits_check( $edd_options['novalnet_merchant_id'] ) || empty( $edd_options['novalnet_product_id'] ) || ! novalnet_digits_check( $edd_options['novalnet_product_id'] ) || empty( $edd_options['novalnet_tariff_id'] ) || ! novalnet_digits_check( $edd_options['novalnet_tariff_id'] ) || empty( $edd_options['novalnet_auth_code'] ) || empty( $edd_options['novalnet_access_key'] ) || ( isset( $edd_options['novalnet_subs_enable_option'] ) && ( empty( $edd_options['novalnet_subs_tariff_id'] ) || ! novalnet_digits_check( $edd_options['novalnet_subs_tariff_id'] ) ) ) ) {
        echo '<div id="notice" class="error"><p><strong>' . esc_html( __( 'Please fill in all the mandatory fields', 'edd-novalnet' ) ) . '</strong></p></div>';
    }
}

/**
 * Get Merchant details and payment details
 *
 * @param  string $payment_method  payment method name.
 * @return array.
 */
function get_merchant_details( $payment_method ) {

    global $edd_options;

    $payment_key                 = get_payment_key( $payment_method );
    $config_data['key']          = $payment_key['key'];
    $config_data['payment_type'] = $payment_key['payment_type'];
    $config_data['vendor']       = trim( $edd_options['novalnet_merchant_id'] );
    $config_data['auth_code']    = trim( $edd_options['novalnet_auth_code'] );
    $config_data['product']      = trim( $edd_options['novalnet_product_id'] );
    $config_data['tariff']       = trim( $edd_options['novalnet_tariff_id'] );
    $config_data['notify_url']   = ! empty( $edd_options['novalnet_merchant_notify_url'] ) ? trim( $edd_options['novalnet_merchant_notify_url'] ) : '';

    return $config_data;
}

/**
 * Form Novalnet gateway configuration data
 *
 * @since 1.0.1
 * @param  array  $purchase_data  Get customer detail.
 * @param  string $payment_method Allow payment to process.
 * @return array.
 */
function novalnet_get_merchant_data( $purchase_data, $payment_method ) {

    global $edd_options;

    $config_data  = get_merchant_details($payment_method);
    $config_data['amount']  = sprintf( '%0.2f', $purchase_data['price'] ) * 100; // convert amount euro to cents.
    $config_data['test_mode']  = ( ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options[ $payment_method . '_test_mode' ] ) ? 1 : 0 ) );
    EDD()->session->set( 'edd_purchase_key', $config_data['key'] );

    if ( preg_match( '/[^\d\.]/', $config_data['amount'] ) ) {
        edd_set_error( 'amount_validation', __( 'The amount is invalid', 'edd-novalnet' ) );
        edd_send_back_to_checkout( '?payment-mode=' . $payment_method );
    }

    $recuring_product = 0;
    foreach ( $purchase_data['downloads'] as $value ) {
        if ( ! empty( $value ['options']['recurring'] ) ) {
            $recuring_product++;
        }
    }
    if ( $recuring_product > 1 ) {
        edd_set_error( 'basic_validation', __( 'Multiple subscription can not be purchased at the same time ', 'edd-novalnet' ) );
        edd_send_back_to_checkout( '?payment-mode=' . $payment_method );
    }

    if ( class_exists( 'EDD_Recurring' ) && ( ! empty( $edd_options['novalnet_subs_tariff_id'] ) && novalnet_digits_check( $edd_options['novalnet_subs_tariff_id'] ) ) && ( isset( $edd_options['novalnet_subs_enable_option'] ) && ! empty( $edd_options['novalnet_subs_tariff_id'] ) ) && ( isset( $purchase_data['downloads'][0]['options']['recurring'] ) || isset( $purchase_data['downloads'][1]['options']['recurring'] ) || isset( $purchase_data['downloads'][2]['options']['recurring'] ) ) ) {
      if ( in_array( $payment_method, array( 'novalnet_cc', 'novalnet_sepa', 'novalnet_paypal', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_invoice_guarantee', 'novalnet_sepa_guarantee' ), true ) ) {
            $config_data['tariff'] = trim( $edd_options['novalnet_subs_tariff_id'] );
            $tariff_period         = false;
            $trial_period          = '';

            foreach ( $purchase_data['downloads'] as $purchase_key => $purchase_val ) {
                if ( ! empty( $purchase_val['options']['recurring'] ) && ( empty( $purchase_val['options']['recurring']['trial_period'] ) && 1 == $purchase_val['options']['recurring']['times'] ) ) {
                    $config_data['tariff'] = $edd_options['novalnet_tariff_id'];

                } else {

                    if ( ! empty( $purchase_val['options']['recurring'] ) ) {
                        $subs_product_id = $purchase_key;
                        $recurring       = $purchase_val['options']['recurring'];
                        $tariff_times       = ( ( 'quarter' === $recurring['period'] ) ? 3 : ( ( 'semi-year' === $recurring['period'] ) ? 6 : 1 ) );

                        if ( ! empty( $recurring['trial_period'] ) ) {
                            $trial_period = substr( $recurring['trial_period']['unit'], 0, 1 );
                            if ( 'q' === $trial_period || 's' === $trial_period ) {
                                $trial_period = 'm';
                            }
                            $config_data['amount'] = 0;
                            if ( isset($recurring['signup_fee']) && !empty($recurring['signup_fee']) ) {
								$config_data['amount'] = $config_data['amount'] + ($recurring['signup_fee']);
							}
							if ( !empty($edd_options['prices_include_tax']) && 'yes' === $edd_options['prices_include_tax'] ) {
								$config_data['amount'] = $config_data['amount'];
							} else {
								$config_data['amount'] = ( ! empty( $purchase_data['tax_rate'] ) && ! empty( $purchase_data['cart_details'][ $subs_product_id ]['tax'] ) ) ? ( $config_data['amount'] + ( round( $config_data['amount'] * $purchase_data['tax_rate'], 2 ) ) ): $config_data['amount'] ;
							}
							$config_data['amount'] = $config_data['amount'] * 100;
                        }
                        $tariff_period = substr( $recurring['period'], 0, 1 );
                        if ( 'q' === $tariff_period || 's' === $tariff_period ) {
                            $tariff_period = 'm';
                        }
                    }
                }
            }
            if ( $tariff_period ) {

                $subs_product_amt = $purchase_data['cart_details'][ $subs_product_id ]['price'] * 100;
                if ( 'w' === $tariff_period ) {
                    $tariff_period = 'd';
                    $tariff_times  = 1 * 7;
                }
                if ( 'w' === $trial_period ) {
                    $trial_period                          = 'd';
                    $recurring['trial_period']['quantity'] = $recurring['trial_period']['quantity'] * 7;
                }
                $config_data['tariff_period']  = ( ! empty( $trial_period ) ) ? $recurring['trial_period']['quantity'] . $trial_period : $tariff_times . $tariff_period;
                $config_data['tariff_period2'] = $tariff_times . $tariff_period;

                $recurring_one_time_discounts = edd_get_option( 'recurring_one_time_discounts' ) ? true : false;
                if ( ! empty( $recurring['trial_period']['unit'] ) && ! empty( $recurring['trial_period']['quantity'] ) ) {
                    $recurring_one_time_discounts = false;
                }

                if ( $purchase_data['cart_details'][ $subs_product_id ]['discount'] ) {
                    if ( $recurring_one_time_discounts ) {
                        if ( !empty($edd_options['prices_include_tax']) && 'yes' === $edd_options['prices_include_tax'] ) {
                            $config_data['tariff_period2_amount'] = $purchase_data['cart_details'][ $subs_product_id ]['subtotal'] * 100 + $purchase_data['tax'] * 100;
                        } else {
                            $config_data['tariff_period2_amount'] = ( ! empty( $purchase_data['tax_rate'] ) && ! empty( $purchase_data['cart_details'][ $subs_product_id ]['tax'] ) ) ? ( $purchase_data['cart_details'][ $subs_product_id ]['subtotal'] + ( round( $purchase_data['cart_details'][ $subs_product_id ]['subtotal'] * $purchase_data['tax_rate'], 2 ) ) ) * 100 : $purchase_data['cart_details'][ $subs_product_id ]['subtotal'] * 100;
                        }
                    } else {
                        $config_data['tariff_period2_amount'] = $subs_product_amt;
                    }
                } else {
                    $config_data['tariff_period2_amount'] = $subs_product_amt;
                }
            }
        } else {
              $config_data['tariff'] = $edd_options['novalnet_tariff_id'];
        }// End if.
    }// End if.

    return $config_data;
}

/**
 * Get payment details
 *
 * @param  string $payment_type Allow payment to process.
 * @return array.
 */
function get_payment_key( $payment_type ) {
    $payment_key = array(
        'novalnet_cc'                => array(
            'key'          => 6,
            'payment_type' => 'CREDITCARD',
        ),
        'novalnet_sepa'              => array(
            'key'          => 37,
            'payment_type' => 'DIRECT_DEBIT_SEPA',
        ),
        'novalnet_invoice'           => array(
            'key'          => 27,
            'payment_type' => 'INVOICE_START',
        ),
        'novalnet_prepayment'        => array(
            'key'          => 27,
            'payment_type' => 'PREPAYMENT',
        ),
        'novalnet_instantbank'       => array(
            'key'          => 33,
            'payment_type' => 'ONLINE_TRANSFER',
        ),
        'novalnet_onlinebanktransfer'       => array(
            'key'          => 113,
            'payment_type' => 'ONLINE_BANK_TRANSFER',
        ),
        'novalnet_paypal'            => array(
            'key'          => 34,
            'payment_type' => 'PAYPAL',
        ),
        'novalnet_ideal'             => array(
            'key'          => 49,
            'payment_type' => 'IDEAL',
        ),
        'novalnet_eps'               => array(
            'key'          => 50,
            'payment_type' => 'EPS',
        ),
        'novalnet_giropay'           => array(
            'key'          => 69,
            'payment_type' => 'GIROPAY',
        ),
        'novalnet_przelewy24'        => array(
            'key'          => 78,
            'payment_type' => 'PRZELEWY24',
        ),
        'novalnet_cashpayment'       => array(
            'key'          => 59,
            'payment_type' => 'CASHPAYMENT',
        ),
        'novalnet_sepa_guarantee'    => array(
            'key'          => 40,
            'payment_type' => 'GUARANTEED_DIRECT_DEBIT_SEPA',
        ),
        'novalnet_invoice_guarantee' => array(
            'key'          => 41,
            'payment_type' => 'GUARANTEED_INVOICE',
        ),
    );
    return $payment_key[ $payment_type ];
}

/**
 * Get order number
 *
 * @param  array $purchase_data Get purchase data from shop.
 * @return string
 */
function get_novalnet_transaction_order( $purchase_data ) {

    global $edd_options;

    $payment_data = array(
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => edd_get_currency(),
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'gateway'      => $purchase_data['gateway'],
    );

    $nn_order_no = edd_insert_payment( $payment_data );
    return $nn_order_no;
}

/**
 * Get customer details
 *
 * @since 1.0.1
 * @param  array $purchase_data Get purchase data from shop.
 * @return array
 */
function novalnet_get_customer_data( $purchase_data ) {

	global $edd_options;

    $name = novalnet_retrieve_name(
        array(
            $purchase_data['user_info']['first_name'],
            $purchase_data['user_info']['last_name'],
        )
    );
    // Returns customer details.
    $customer_data = array_map( 'trim',
        array(
            'gender'           => 'u',
            'customer_no'      => $purchase_data['user_info']['id'] > 0 ? trim( $edd_options['novalnet_product_id'] ) . '-' . $purchase_data['user_info']['id'] : 'guest',
            'first_name'       => $name['0'],
            'last_name'        => $name['1'],
            'email'            => isset( $purchase_data['user_email'] ) ? $purchase_data['user_email'] : $purchase_data['user_info']['email'],
            'street'           => ( ! empty( $purchase_data['user_info']['address']['line1'] ) ) ? $purchase_data['user_info']['address']['line1'] . ', ' . $purchase_data['user_info']['address']['line2'] : $purchase_data['user_info']['address']['line1'],
            'search_in_street' => 1,
            'city'             => $purchase_data['user_info']['address']['city'],
            'zip'              => $purchase_data['user_info']['address']['zip'],
            'country_code'     => $purchase_data['user_info']['address']['country'],
            'country'          => $purchase_data['user_info']['address']['country'],


        )
    );
    if ( ! empty( $purchase_data['user_info']['company'] ) ) {
        $customer_data['company'] =  $purchase_data['user_info']['company'];
    }

    if ( empty( $customer_data['first_name'] ) || empty( $customer_data['last_name'] ) || empty( $customer_data['email'] ) ) {
        edd_set_error( 'customer_validation', __( 'Customer name/email fields are not valid', 'edd-novalnet' ) );
        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['gateway'] );
    } elseif ( empty( $customer_data['city'] ) || empty( $customer_data['zip'] ) || empty( $customer_data['country'] ) || empty( $customer_data['street'] ) ) {
        edd_set_error( 'customer_validation', __( 'Please fill in all the mandatory fields', 'edd-novalnet' ) );
        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['gateway'] );
    }
    return $customer_data;
}

/**
 * Get system details
 *
 * @since 1.0.1
 * @return array
 */
function novalnet_get_system_data() {

    $system_data = array(
        'currency'       => edd_get_currency(),
        'remote_ip'      => novalnet_server_addr(),
        'lang'           => novalnet_shop_language(),
        'system_ip'      => novalnet_server_addr( 'SERVER_ADDR' ),
        'system_name'    => 'wordpress-easydigitaldownloads',
        'system_version' => get_bloginfo( 'version' ) . '-' . EDD_VERSION . '-NN-' . NOVALNET_VERSION,
        'system_url'     => site_url(),
    );
    return $system_data;
}

/**
 * Validates the given input data is numeric or not
 *
 * @since 1.0.1
 * @param  integer $input Check to allow only numbers.
 * @return boolean
 */
function novalnet_digits_check( $input ) {
    return ( preg_match( '/^[0-9]+$/', trim( $input ) ) );
}

/**
 * Redirect to Novalnet paygate for re-direction payments
 *
 * @since 1.0.1
 * @param  string $paygate_url Redirect URL is passed in shop.
 * @param  array  $params Formed parameter with redirection param is passed.
 */
function novalnet_get_redirect( $paygate_url, $params ) {
    // Re-directs to third party.
    $form = '<form name="frmnovalnet_payment" method="post" action="' . $paygate_url . '">';
    foreach ( $params as $k => $v ) {
        $form .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
    }
    $form .= html_entity_decode( __( 'You are now redirected automatically', 'edd-novalnet' ) ) . '<br> <input style="display:none;" type="submit" name="enter" value=' . __( 'Please wait...', 'edd-novalnet' ) . '></form>';
    $form .= '<script>
                document.forms.frmnovalnet_payment.submit();
              </script>';
    echo wp_unslash( $form );
    exit();
}

/**
 * Check Novalnet response
 *
 * @since 1.0.1
 * @param  array $response         Get response from server.
 * @param  array $update_payment   check if it is update payment response.
 */
function novalnet_check_response( $response, $update_payment = false, $trial_product = '0') {

    if ( isset( $response['status'] ) && '100' === $response['status'] ) {
        if ( ! empty( $response['subs_id'] ) ) {
            // To restrict empty mail sending from edd-recurring.
            add_action( 'edd_pre_complete_purchase', 'novalnet_restrict_mail', 10, 1 );
        }
        novalnet_success( $response, $update_payment, $trial_product );
    } else {
        novalnet_transaction_failure( $response, false, $update_payment );
    }
}

/**
 * Restrict subscription mail which is sent at first due to process checkout function in edd-recurring
 *
 * @since 1.1.0
 * @param  boolean $return Contains mail process.
 * @return boolean
 */
function novalnet_restrict_mail( $return ) {
    if ( ( ( ! empty( $_REQUEST['payment-mode'] ) && novalnet_check_string( $_REQUEST['payment-mode'] ) ) && ( ! empty( $_REQUEST['edd-gateway'] ) && novalnet_check_string( $_REQUEST['edd-gateway'] ) ) ) || ( ! empty( $_REQUEST['tid'] ) && ( ! empty( $_REQUEST['payment_method'] ) && in_array( $_REQUEST['payment_method'], array( 'novalnet_paypal', 'novalnet_cc' ), true ) ) ) ) { // Input var okay.
        remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );
        return false;
    }
    return $return;
}

/**
 * Pass session values in url
 *
 * @since 1.1.0
 * @param  string $url Subscription URL.
 * @return string
 */
function novalnet_subscription_url( $url ) {

    // Here Getting the payment transaction details in order confirmation page.
    if ( EDD()->session->get( 'novalnet_subscription_process' ) ) {

        $redirect_param = EDD()->session->get( 'novalnet_redirect_params' );

        EDD()->session->set( 'novalnet_subscription_process', null );
        EDD()->session->set( 'novalnet_redirect_params', null );

        if ( ! empty( $redirect_param ) && 'PAYPAL' === $redirect_param['payment_type'] ) {
            novalnet_get_redirect( 'https://payport.novalnet.de/paypal_payport', $redirect_param );
        } elseif ( ! empty( $redirect_param ) && 'CREDITCARD' === $redirect_param['payment_type'] ) {
            novalnet_get_redirect( 'https://payport.novalnet.de/pci_payport', $redirect_param );
        }
        novalnet_check_response( EDD()->session->get( 'novalnet_response' ) );
    }
    return $url;
}

/**
 * Update and insert Novalnet Transaction details in database and payment note for Payment success
 *
 * @since 1.0.1
 * @param array $response         Server response.
 * @param array $update_payment   Check response for update payment.
 */
function novalnet_success( $response, $update_payment, $trial_product ) {

    global $edd_options, $wpdb;
    $trial_product = !empty(EDD()->session->get( 'trial_product')) ? EDD()->session->get( 'trial_product') : $trial_product;

    EDD()->session->set( 'trial_product', null);

    if ( $update_payment ) {
        $subs_details  = get_subscription_details( $response['order_no'] );
        $subscription  = new EDD_Subscription( $subs_details['subs_id'] );
        $payment_name  = get_novalnet_payment( $response['order_no'] );
        $test_mode  = (int) ( edd_is_test_mode() || ! empty( $edd_options[ $payment_name . '_test_mode' ] ) || ! empty( $response['test_mode'] ) );
        $comments  = novalnet_form_transaction_details( $response, $test_mode );
        /* translators: %s: Date */
        $comments .= sprintf( __( 'Successfully updated the payment and customer billing details for upcoming subscriptions on date: %s', 'edd-novalnet' ), date_i18n( get_option( 'date_format' ), strtotime( gmdate( 'Y-m-d H:i:s' ) ) ) ) . PHP_EOL;
        edd_insert_payment_note( $response['order_no'], $comments );
        $wpdb->update(
            $wpdb->prefix . 'novalnet_subscription_details',
            array(
                'recurring_tid' => $response['tid'],
            ),
            array(
                'order_no' => $subscription->parent_payment_id,
            )
        ); // db call ok; no-cache ok.
        $return_url = wp_parse_url( $subscription->get_update_url() );
        wp_safe_redirect( add_query_arg( array( 'updated' => true ), $return_url['path'] ) );
        exit();
    }
    $novalnet_comments = '';
    EDD()->session->set( 'novalnet_subscription_process', null );
    EDD()->session->set( 'novalnet_response', null );
    EDD()->session->set( 'novalnet_purchase_data', null );
    $payment_gateways = EDD()->session->get( 'edd_purchase' );
    novalnet_update_order_meta( $response['order_no'], '_edd_payment_gateway', $payment_gateways['gateway'] );
    novalnet_update_order_meta( $response['order_no'], '_nn_order_tid', $response['tid'] );
    $invoice_payments = array( 'novalnet_invoice', 'novalnet_prepayment' );
    $amount           = ( isset( $payment_gateways['gateway'] ) && in_array( $payment_gateways['gateway'], array( 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_sepa' ), true ) ) ? str_replace( ',', '', sprintf( '%0.2f', $response['amount'] ) ) * 100 : str_replace( ',', '', sprintf( '%0.2f', $response['amount'] ) );
    if ( in_array( $payment_gateways['gateway'], $invoice_payments, true ) || ( 'novalnet_paypal' === $payment_gateways['gateway'] && ( '90' === $response['tid_status'] || '85' === $response['tid_status'] ) ) || ( 'novalnet_przelewy24' === $payment_gateways['gateway'] && '86' === $response['tid_status'] ) || ( 'novalnet_cashpayment' === $payment_gateways['gateway'] ) ) {
        novalnet_update_order_meta( $response['order_no'], '_nn_callback_amount', 0 );
    } else {
		$order = edd_get_order( $response['order_no'] );
        // Set the purchase to complete.
        novalnet_update_order_meta( $response['order_no'], '_nn_callback_amount', $response['amount'] * 100 );
    }
    if ( in_array( $response['tid_status'], array( '91', '99', '98', '85' ), true ) ) {
        $final_order_status = $edd_options['novalnet_onhold_success_status'];
    } elseif ( in_array( $response['tid_status'], array( '75', '90', '86' ), true ) ) {
        $final_order_status = $edd_options[ $payment_gateways['gateway'] . '_order_pending_status' ];
    } else {
        $final_order_status = $edd_options[ $payment_gateways['gateway'] . '_order_completion_status' ];
        if ( '41' === $response['key'] ) {
            $final_order_status = $edd_options[ $payment_gateways['gateway'] . '_order_callback_status' ];
        }
    }
    $test_mode = (int) ( edd_is_test_mode() || ! empty( $edd_options[ $payment_gateways['gateway'] . '_test_mode' ] ) || ! empty( $response['test_mode'] ) );
    $novalnet_comments  = '';
    $novalnet_comments .= novalnet_form_transaction_details( $response, $test_mode );
    if ( in_array( $payment_gateways['gateway'], $invoice_payments, true ) &&  $trial_product == '0' ) {
        $novalnet_comments = novalnet_get_invoice_comments( $response, $payment_gateways, $amount, $novalnet_comments);
    }
    if ( 'novalnet_cashpayment' === $payment_gateways['gateway'] ) {
		$novalnet_comments .= cashpayment_order_comments( $response );
		EDD()->session->set( 'cp_checkout_token', $response['cp_checkout_token'] );
    }
    update_transaction_details( $response, $payment_gateways['gateway'] );
    $novalnet_comments = html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );
    EDD()->session->set( 'novalnet_transaction_comments', $novalnet_comments );
    edd_update_payment_status( $response['order_no'], $final_order_status );
    // Update Novalnet Transaction details into payment note.
    edd_insert_payment_note( $response['order_no'], $novalnet_comments );
    if ( 'publish' !== $final_order_status || ! empty( $response['subs_id'] ) ) {
        edd_trigger_purchase_receipt( $response['order_no'] );
    }

    // Empty the shopping cart.
    edd_empty_cart();

    // Go to the Success page.
    edd_send_to_success_page();

}

/**
 * Update the transaction details into the novalnet table
 *
 * @param array  $response          Server response.
 * @param string $payment_gateway   payment name.
 */
function update_transaction_details( $response, $payment_gateway ) {
    global $wpdb, $edd_options;

    $payment = get_novalnet_payment( $response['order_no'] );
    $payment = ! empty( $payment_gateway ) ? $payment_gateway : $payment;

    $subscription_details = get_subscription_details( $response['order_no'] );

    if ( ! empty( $response['tariff_period'] ) || ! empty( $response['subs_id'] ) || ! empty( $subscription_details['subs_id'] ) ) {
        $wpdb->update(
            $wpdb->prefix . 'edd_subscriptions',
            array(
                'transaction_id' => $response['tid'],
            ),
            array(
                'parent_payment_id' => $response['order_no'],
            )
        );
        $subscription = new EDD_Subscription( $subscription_details['subs_id'] );
        if ( '100' !== $response['status'] ) {
            $subscription->cancel();
        }
    }

    $invoice_payments = array( 'novalnet_invoice', 'novalnet_prepayment' );
    $test_mode        = (int) ( edd_is_test_mode() || ! empty( $payment . '_test_mode' ) || ! empty( $response['test_mode'] ) );
    $amount           = str_replace( ',', '', sprintf( '%0.2f', $response['amount'] ) ) * 100;

    $customer_id = edd_get_payment_user_id( $response['order_no'] );

    novalnet_update_order_meta( $response['order_no'], '_nn_customer_id', $edd_options['novalnet_product_id'] . '-' . $customer_id );

    $wpdb->insert(
        "{$wpdb->prefix}novalnet_transaction_detail",
        array(
            'order_no'        => $response['order_no'],
            'vendor_id'       => trim( $edd_options['novalnet_merchant_id'] ),
            'auth_code'       => trim( $edd_options['novalnet_auth_code'] ),
            'product_id'      => trim( $edd_options['novalnet_product_id'] ),
            'tariff_id'       => trim( $edd_options['novalnet_tariff_id'] ),
            'subs_id'         => ! empty( $response['subs_id'] ) ? $response['subs_id'] : '',
            'payment_id'      => !empty( $response['key'] ) ? $response['key'] : $response['payment_id'],
            'payment_type'    => $payment,
            'tid'             => $response['tid'],
            'gateway_status'  => ! empty( $response['tid_status'] ) ? $response['tid_status'] : '',
            'amount'          => $amount,
            'callback_amount' => ( ! in_array( $payment, $invoice_payments, true ) ) ? $amount : 0,
            'currency'        => edd_get_currency(),
            'test_mode'       => $test_mode,
            'customer_id'     => $customer_id,
            'customer_email'  => edd_get_payment_user_email( $response['order_no'] ),
            'date'            => gmdate( 'Y-m-d H:i:s' ),
        )
    ); // db call ok; no-cache ok.

    if ( ! empty( $response['subs_id'] ) ) {

        $total_length = $subscription->bill_times;

        $wpdb->insert(
            "{$wpdb->prefix}novalnet_subscription_details",
            array(
                'order_no'               => $response['order_no'],
                'payment_type'           => $payment,
                'recurring_payment_type' => $payment,
                'recurring_amount'       => $amount,
                'recurring_tid'          => $response['tid'],
                'tid'                    => $response['tid'],
                'signup_date'            => gmdate( 'Y-m-d H:i:s' ),
                'subs_id'                => $response['subs_id'],
                'next_payment_date'      => ! empty( $response['next_subs_cycle'] ) ? $response['next_subs_cycle'] : $response['paid_until'],
                'subscription_length'    => ! empty( $total_length ) ? $total_length : 0,
            )
        ); // db call ok; no-cache ok.
    }

}

/**
 * Update the failure transaction in shop backend
 *
 * @param array   $response  Server response.
 * @param boolean $hash      Check the hash error
 */
function novalnet_transaction_failure( $response, $hash = false, $update_payment = false ) {

	global $edd_options, $wpdb;
    if ( $update_payment ) {
        $subs_details  = get_subscription_details( $response['order_no'] );
        $subscription  = new EDD_Subscription( $subs_details['subs_id'] );
        $return_url = wp_parse_url( $subscription->get_update_url() );
        if ( $hash ) {
            edd_set_error( 'server_direct_validation', __( 'While redirecting some data has been changed. The hash check failed', 'edd-novalnet' ) );
            wp_safe_redirect( add_query_arg( array( 'action' => 'update',
                                            'subscription_id' => $subscription->id ), $return_url['path'] ) );
            die();
        }
        $error_msg = get_status_desc( $response );
        // Update failure comments to order note
        $comments = PHP_EOL . sprintf( __( 'Recurring change payment method has been failed due to %s', 'edd-novalnet' ), $error_msg );
        edd_insert_payment_note( $response['order_no'], $comments );
        edd_set_error( 'edd_recurring_novalnet', $error_msg );
        wp_safe_redirect( add_query_arg( array( 'action' => 'update', 'subscription_id' => $subscription->id ), $return_url['path'] ) );
        die();
    }
    EDD()->session->set( 'novalnet_subscription_process', null );
    EDD()->session->set( 'novalnet_response', null );
    EDD()->session->set( 'novalnet_purchase_data', null );
    $payment_gateways = EDD()->session->get( 'edd_purchase' );
    novalnet_update_order_meta( $response['order_no'], '_edd_payment_gateway', $payment_gateways['gateway'] );
    novalnet_update_order_meta( $response['order_no'], '_nn_order_tid', $response['tid'] );
    $test_mode          = (int) ( edd_is_test_mode() || ! empty( $edd_options[ $payment_gateways['gateway'] . '_test_mode' ] ) || ! empty( $response['test_mode'] ) );
    $novalnet_comments  = '';
    $novalnet_comments .= novalnet_form_transaction_details( $response, $test_mode );
    $novalnet_comments .= ( isset( $response['status_text'] ) ? $response['status_text'] : ( isset( $response['status_desc'] ) ? $response['status_desc'] : $response['status_message'] ) );
    update_transaction_details( $response, $payment_gateways['gateway'] );
    $novalnet_comments = html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );
    edd_update_payment_status( $response['order_no'], 'abandoned' );
    // Update Novalnet Transaction details into payment note.
    edd_insert_payment_note( $response['order_no'], $novalnet_comments );
    edd_set_error( 'server_direct_validation', get_status_desc( $response ) );
    if ( $hash ) {
        edd_set_error( 'server_direct_validation', __( 'While redirecting some data has been changed. The hash check failed', 'edd-novalnet' ) );
    }
    edd_send_back_to_checkout( '?payment-mode='. $payment_gateways['gateway'] );
}

/**
 * Process refund in Novalnet
 *
 * @since 2.2.0
 * @param object $order Get relevant order object.
 * @return mixed
 */
function edd_novalnet_refund( $order_id, $refund_id, $all_refunded ) {
	if ( ! current_user_can( 'edit_shop_payments', $order_id ) ) {
		return;
	}

	if ( empty( $_POST['data'] ) ) {
		return;
	}

	$order = edd_get_order( $order_id );
	// Return in gateway is empty or not the Novalnet payment
	if ( empty( $order->gateway ) || ! novalnet_check_string( $order->gateway ) ) {
		return;
	}

	// Get our data out of the serialized string.
	parse_str( $_POST['data'], $form_data );
	$subscription_id = null;
	// If subscription cancellation checkbox enabled
	if ( !empty( $form_data ) && !empty( $form_data['edd_recurring_cancel_subscription'] ) ) {
		foreach( $form_data['edd_recurring_cancel_subscription'] as $key => $value ) {
			$subscription_id = $value;
		}
	}
	// If subscription cancellation checkbox enabled, cancel the subscription in Novalnet
	if ( !is_null( $subscription_id ) ) {
		edd_novalnet_cancel_subscription( $order );
	}
	// Return if Novalnet checkbox is not enabled
	if ( empty( $form_data['edd-novalnet-refund'] ) ) {
		edd_add_note( array(
			'object_id'   => $order_id,
			'object_type' => 'order',
			'user_id'     => is_admin() ? get_current_user_id() : 0,
			'content'     => __( 'Transaction not refunded in Novalnet, as checkbox was not selected.', 'edd-novalnet' )
		) );

		return;
	}

	$refund = edd_get_order( $refund_id );
	// Return if refund amount is empty
	if ( empty( $refund->total ) ) {
		return;
	}
	if ( $order instanceof Order ) {
		$payment = edd_get_payment( $order->id );
	} elseif ( $order instanceof EDD_Payment ) {
		$order   = edd_get_order( $order->ID );
	} elseif ( is_numeric( $order ) ) {
		$order   = edd_get_order( $order );
		$payment = edd_get_payment( $order );
	}


	$params = $params = edd_novalnet_form_api_params( $order );
	$params['refund_request'] = 1;
	$params['refund_param'] = abs( $refund->total ) * 100;
	unset( $params['payment_type'], $params['notify_url'] );
	$response = novalnet_submit_request($params);
	if ($response['status'] == '100') { // Refund success
		$note_object_ids = array( $order->id );
		if ( $refund instanceof Order ) {
			$note_object_ids[] = $refund->id;
		}
		if ( !empty( $response['tid'] ) ) {
			$note_message = sprintf( __( 'Refund has been initiated for the TID: %1$s with the amount %2$s. New TID:%3$s for the refunded amount', 'edd-novalnet' ), $params['tid'], edd_currency_filter( edd_format_amount( $params['refund_param']/100 ) ),  $response['tid']);
		} else {
			$note_message = sprintf( __( 'Refund has been initiated for the TID:%1$s with the amount %2$s', 'edd-novalnet' ), $params['tid'], edd_currency_filter( edd_format_amount( $params['refund_param']/100 ) ) );
		}
		foreach ( $note_object_ids as $note_object_id ) {
			edd_add_note( array(
				'object_id'   => $note_object_id,
				'object_type' => 'order',
				'user_id'     => is_admin() ? get_current_user_id() : 0,
				'content'     => $note_message
			) );
		}
		$tid = !empty( $response['tid'] ) ? $response['tid'] : $params['tid'];
		// Add a negative transaction.
		if ( $refund instanceof Order ) {
			edd_add_order_transaction( array(
				'object_id'      => $refund->id,
				'object_type'    => 'order',
				'transaction_id' => sanitize_text_field( $tid ),
				'gateway'        => $order->gateway,
				'status'         => 'complete',
				'total'          => edd_negate_amount( $refund->total )
			) );
		}
	} else { // Refund failure
		edd_add_note( array(
			'object_id'   => $order->id,
			'object_type' => 'order',
			'user_id'     => is_admin() ? get_current_user_id() : 0,
			'content'     => sprintf( __( 'Payment refund failed for the order due to: %s', 'edd-novalnet' ), $response['status_desc'] )
		) );
	}
}

/**
 * Display refund checkbox
 *
 * @since 2.2.0
 * @param object $order Get relevant order object.
 * @return mixed
 */
function edd_novalnet_refund_checkbox( \EDD\Orders\Order $order ) {
	if ( ! novalnet_check_string( $order->gateway ) ) {
		return;
	}
	?>
	<div class="edd-form-group edd-novalnet-refund-transaction">
		<div class="edd-form-group__control">
			<input type="checkbox" id="edd-novalnet-refund" name="edd-novalnet-refund" class="edd-form-group__input" value="1">
			<label for="edd-novalnet-refund" class="edd-form-group__label">
				<?php esc_html_e( 'Refund transaction in Novalnet', 'edd-novalnet' ); ?>
			</label>
		</div>
	</div>
	<?php
}

/**
 * Process subscription cancellation
 *
 * @since 2.2.0
 * @param object $order  Get the order object.
 * return none
 */
function edd_novalnet_cancel_subscription( $order ) {
	$params = edd_novalnet_form_api_params( $order );
	$params['cancel_sub']    = 1;
	$params['cancel_reason'] = 'Others';
	$params['lang']          = novalnet_shop_language();
	$response = novalnet_submit_request( $params );
	if ( $response['status'] == '100' ) {
		edd_add_note( array(
			'object_id'   => $order->id,
			'object_type' => 'order',
			'user_id'     => is_admin() ? get_current_user_id() : 0,
			'content'     => sprintf( __( 'Subscription has been canceled for the TID: %s', 'edd-novalnet' ), $params['tid'] )
		) );
	} else {
		edd_add_note( array(
			'object_id'   => $order->id,
			'object_type' => 'order',
			'user_id'     => is_admin() ? get_current_user_id() : 0,
			'content'     => sprintf( __( 'Subscription cancelation failed for the order due to: %s', 'edd-novalnet' ), $response['status_desc'] )
		) );
	}
}

/**
 * Returns API params
 *
 * @since 2.2.0
 * @param object $order  Get the order object.
 * return @array
 */
function edd_novalnet_form_api_params( $order ) {
	$params = get_merchant_details( $order->gateway );
	$params['tid'] = novalnet_get_order_meta($order->id, '_nn_order_tid');
	unset( $params['payment_type'], $params['notify_url'] );
	return $params;
}

/**
 * Fetch subscription details
 *
 * @param integer $post_id Get relevant subscription post_id.
 * @return array $subs_details Subscription details are taken from core table.
 */
function get_subscription_details( $post_id ) {
    global $wpdb;

    $subs_details = array();

    if ( class_exists( 'EDD_Recurring' ) ) {
        $subs_details['subs_plugin_enabled'] = true;
        $subs_details['subs_id']             = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}edd_subscriptions WHERE parent_payment_id='%s'", $post_id ) ); // db call ok; no-cache ok.
    }
    return $subs_details;
}

 /**
  * Retrieves the Novalnet subscription cancel reasons
  *
  * @since 1.1.0
  * @return array
  */
function novalnet_subscription_cancel_list() {
    return array(
        __( 'Product is costly', 'edd-novalnet' ),
        __( 'Cheating', 'edd-novalnet' ),
        __( 'Partner interfered', 'edd-novalnet' ),
        __( 'Financial problem', 'edd-novalnet' ),
        __( 'Content does not match my likes', 'edd-novalnet' ),
        __( 'Content is not enough', 'edd-novalnet' ),
        __( 'Interested only for a trial', 'edd-novalnet' ),
        __( 'Page is very slow', 'edd-novalnet' ),
        __( 'Satisfied customer', 'edd-novalnet' ),
        __( 'Logging in problems', 'edd-novalnet' ),
        __( 'Other', 'edd-novalnet' ),
    );
}

 /**
  * Novalnet subscription cancel action performs
  *
  * @since 1.1.0
  * @param  object $subscription The Subscription object.
  * @return array
  */
function novalnet_subs_cancel_perform( $subscription ) {
    global $wpdb;
    $result_set         = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id, payment_id,tid FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s ORDER BY id DESC", $subscription->parent_payment_id ), ARRAY_A ); // db call ok; no-cache ok.
    $termination_reason = $wpdb->get_row( $wpdb->prepare( "SELECT termination_reason FROM {$wpdb->prefix}novalnet_subscription_details WHERE order_no=%s ORDER BY id DESC", $subscription->parent_payment_id ), ARRAY_A ); // db call ok; no-cache ok.
    $language           = novalnet_shop_language();
    if ( empty( $termination_reason['termination_reason'] ) ) {
        $cancel_request = array(
            'vendor'        => $result_set['vendor_id'],
            'auth_code'     => $result_set['auth_code'],
            'product'       => $result_set['product_id'],
            'tariff'        => $result_set['tariff_id'],
            'tid'           => $result_set['tid'],
            'key'           => $result_set['payment_id'],
            'cancel_sub'    => 1,
            'cancel_reason' => 'Other',
            'lang'          => $language,
        );
        $wpdb->update(
            $wpdb->prefix . 'novalnet_subscription_details',
            array(
                'termination_reason' => isset( $cancel_request['cancel_reason'] ) ? $cancel_request['cancel_reason'] : 'Other',
                'termination_at'     => gmdate( 'Y-m-d H:i:s' ),
            ),
            array(
                'order_no' => $subscription->parent_payment_id,
            )
        ); // db call ok; no-cache ok.
        return novalnet_submit_request( $cancel_request );
    } else {
        echo '<div id="notice" class="error"><p>The subscription has been already stopped or cancelled. </p></div>';
    }
    return array();
}

/**
 * Get transaction details in order success page
 *
 * @since 1.0.1
 * @param boolean $post_comments Get DB post comments.
 */
function novalnet_transaction_detail_checkout( $post_comments ) {
    EDD()->session->set( 'novalnet', null );
    $nn_post_id = get_post( $post_comments );
	$payment_note = edd_get_payment_notes($nn_post_id->ID, 'novalnet');
    $payment_name = get_novalnet_payment( $nn_post_id->ID );
    if ( novalnet_check_string( $payment_name ) ) {
        echo wpautop( edd_get_gateway_checkout_label( $payment_name ) . '<br>'. $payment_note[0]->content );
    }
}

/**
 * Receive Novalnet response for redirection payments
 *
 * @since 1.0.1
 * @param boolean $update_payment Check for update payment.
 */
function novalnet_get_redirect_response( $update_payment = false ) {

    global $wpdb;
    $redirect_response = wp_unslash( $_REQUEST ); // Input var okay.
    $order_ref = array();
	if ( isset($redirect_response['order_no']) && !empty($redirect_response['order_no']) ) {
		$order_ref = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%s", $redirect_response['order_no'] ) );
	}

	if ( empty($order_ref) || ( ! empty($order_ref) && $update_payment ) ) {

		// Decode the redirect response.
		$redirect_response = decode_paygate_response( $redirect_response );

		if ( isset( $redirect_response['tid_status'] ) && ( in_array( $redirect_response['tid_status'], array( '98', '85', '86', '90', '100' ), true ) ) && isset( $redirect_response['tid'] ) ) {
			if ( ! novalnet_check_hash( $redirect_response ) ) {
				novalnet_transaction_failure( $redirect_response, true, $update_payment );
			} else {
				novalnet_check_response( $redirect_response, $update_payment );
			}
		} elseif ( ! empty( $redirect_response['status'] ) && '100' !== $redirect_response['status'] ) {
			novalnet_transaction_failure( $redirect_response, false, $update_payment );
		}
	} else {
		edd_send_back_to_checkout();
	}
}

/**
 * Perform the decoding paygate response process for redirection payment methods
 *
 * @param array $datas This is array of transaction data.
 *
 * @return string
 */
function decode_paygate_response( $datas ) {
    $result = array();

    $data['auth_code'] = $datas['auth_code'];
    $data['tariff']    = $datas['tariff'];
    $data['product']   = $datas['product'];
    $data['amount']    = $datas['amount'];
    $data['test_mode'] = $datas['test_mode'];
    $data['uniqid']    = $datas['uniqid'];

    foreach ( $data as $key => $value ) {
        $result[ $key ] = generate_decode( $value, $data['uniqid'] ); // Decode process.
    }
    return array_merge( $datas, $result );
}

/**
 * Perform the decoding process for redirection payment methods
 *
 * @param $data   array  The transaction data.
 * @param $uniqid string The string value.
 * @return string
 */
function generate_decode( $data, $uniqid ) {
    global $edd_options;
    try {
        $data = openssl_decrypt( base64_decode( $data ), 'aes-256-cbc', $edd_options['novalnet_access_key'], true, $uniqid );
    } catch ( Exception $e ) { // Error log for the exception.
        echo esc_html( 'Error: ' . $e );
    }
    return $data;
}

/**
 * Set default payment as chosen payment
 *
 * @since 1.0.1
 * @return string $set_default_payment Default payment is checked.
 */
function novalnet_set_default_gateway() {
    global $edd_options;
    $set_default_payment = '';
    $payment_gateways    = EDD()->session->get( 'edd_purchase' );
    $current_payment     = isset( $payment_gateways['gateway'] ) ? $payment_gateways['gateway'] : '';
    $invalid_pin_count   = EDD()->session->get( $current_payment . '_pin_invalid_count' );
    if ( isset( $edd_options['default_gateway'] ) && edd_is_gateway_active( $edd_options['default_gateway'] ) ) {
        $set_default_payment = $edd_options['default_gateway'];
    } elseif ( isset( $current_payment ) && ! empty( $current_payment ) && ! $invalid_pin_count ) {
        $set_default_payment = $current_payment;
    }
    return $set_default_payment;
}

/**
 * Returns the gateway icon for payment logo.
 *
 * @since 1.0.1
 * @param string $payment_method      Get payment name.
 * @param string $payment_method_name Get payment method name.
 */
function novalnet_display_payment_logo( $payment_method, $payment_method_name ) {
    global $edd_options;
    $icon_html = isset( $edd_options['novalnet_common_payment_logo'] ) ? $edd_options['novalnet_common_payment_logo'] : '';
    if ( $icon_html == 'yes') {
        if ( 'novalnet_cc' !== $payment_method ) { ?>
            <img class="payment-icon" title="<?php echo esc_html( $payment_method_name ); ?>" alt="<?php echo esc_html( $payment_method_name ); ?>" style="float:right;" src="<?php echo esc_html( NOVALNET_PLUGIN_URL . 'assets/images/' . $payment_method . '.png' ); ?>">
            <?php
        }
        if ( 'novalnet_cc' === $payment_method ) {
            $cc_logos  = novalnet_get_cc_logos();
            $icon_html = '';
            foreach ( $cc_logos as $value ) {
                    ?>
                    <img class="payment-icon" title="<?php echo esc_html( $payment_method_name ); ?>" alt="<?php echo esc_html( $payment_method_name ); ?>" style="float: right;" src="<?php echo esc_html( NOVALNET_PLUGIN_URL ) . 'assets/images/' . esc_html( $value ) . '.png'; ?>">
                    <?php
            }
        }
    }
}

/**
 * Returns the credit card logo.
 *
 * @since 1.0.1
 * @return $cc_logos
 */
function novalnet_get_cc_logos() {
    global $edd_options;

    $cc_logos = array(
		'novalnet_cc_visa',
		'novalnet_cc_mastercard',
		'novalnet_cc_amex',
		'novalnet_cc_maestro',
		'novalnet_cc_cartasi',
		'novalnet_cc_unionpay',
		'novalnet_cc_discover',
		'novalnet_cc_diners',
		'novalnet_cc_jcb',
		'novalnet_cc_carte-bleue',
    );

    return $cc_logos;
}

/**
 * Set curl request function
 *
 * @since 1.0.1
 * @param  string $nn_url    Get URL to be processed.
 * @param  array  $urlparam  Get params to handle request.
 * @return array  $data      Get response from server.
 */
function novalnet_handle_communication( $nn_url, $urlparam ) {

    global $edd_options;

    // Post the values to the paygate URL.
    $response = wp_remote_post(
        $nn_url,
        array(
            'method'  => 'POST',
            'timeout' => 240,
            'body'    => $urlparam,
        )
    );

    // Check for error.
    if ( is_wp_error( $response ) ) {
        return 'tid=&status=' . $response->get_error_code() . '&status_message=' . $response->get_error_message();
    }

    // Return the response.
    return $response['body'];
}

/**
 * Remove the payment which is not for recurring process
 *
 * @since 1.0.1
 * @param  array $gateway_list Get enabled payment.
 * @return array $gateway_list payment are displayed in checkout.
 */
function remove_novalnet_payment( $gateway_list ) {
    global $edd_options;

	if ( ! is_admin() && ( empty( $edd_options['novalnet_public_key'] ) || empty( $edd_options['novalnet_tariff_id'] ) ) ) {
		foreach ( array_keys( $gateway_list ) as $value ) {
			if ( novalnet_check_string( $value ) ) {
				unset( $gateway_list [ $value ] );
			}
		}
	}
	if ( ! is_admin() && !isset( $edd_options['novalnet_client_key'] ) ) {
		foreach ( array_keys( $gateway_list ) as $value ) {
			if ( novalnet_check_string( $value ) ) {
				if ( 'novalnet_cc' === $value ) {
					unset( $gateway_list [ $value ] );
				}
			}
		}
	}

	if ( ! is_admin() && ! empty ( EDD()->cart->contents ) ) {
		$recurring    = false;
		$cart_details = edd_get_cart_contents();
		if( ! empty ( $cart_details) ) {
			foreach ( (array) $cart_details as $cart_val ) {
				if ( ! empty( $cart_val['options']['recurring'] ) ) {
					$recurring = true;
					break;
				}
			}
		}

		if ( $recurring && isset( $edd_options['novalnet_subs_payments'] ) ) {
			foreach ( array_keys( $gateway_list ) as $value ) {
				if ( ( novalnet_check_string( $value ) && ! in_array( $value, $edd_options['novalnet_subs_payments'], true ) && ( !empty($edd_options['novalnet_subs_enable_option']) && '1' === $edd_options['novalnet_subs_enable_option'] ) ) || ( novalnet_check_string( $value ) && empty( $edd_options['novalnet_subs_enable_option'] ) ) ) {
					unset( $gateway_list [ $value ] );
				}
			}
			if ( novalnet_check_string( $edd_options['default_gateway'] ) && isset( $edd_options['default_gateway'] ) && ! in_array( $edd_options['default_gateway'], $edd_options['novalnet_subs_payments'], true ) &&  !empty($edd_options['novalnet_subs_enable_option']) && '1' === $edd_options['novalnet_subs_enable_option'] )  {
				$subs_payments = $edd_options['novalnet_subs_payments'];
				for ( $i = 0; $i < count( $subs_payments ); $i++ ) {
					$edd_options['default_gateway'] = $subs_payments[ $i ];
				}
			} else if( novalnet_check_string( $edd_options['default_gateway'] ) && !empty($gateway_list) && ! in_array( $edd_options['default_gateway'], array_keys( $gateway_list ), true ) ){
				$available_gateways = array_keys( $gateway_list );
				$edd_options['default_gateway'] = $available_gateways[0];
			}
		}
	}
    return $gateway_list;
}

/**
 * Check the response hash is equal to request hash
 *
 * @since 1.0.1
 * @param array $request Get hash value and check.
 * @return string
 */
function novalnet_check_hash( $request ) {
    return ( $request['hash2'] !== generate_md5_value( $request ) );
}

 /**
  * Submit the given request to the given url
  *
  * @since 1.1.0
  * @param array  $request Get request data.
  * @param string $url     URL to proceed to Novalnet.
  * @return object
  */
function novalnet_submit_request( $request, $url = 'https://payport.novalnet.de/paygate.jsp' ) {
    $request      = http_build_query( $request );
    $data = novalnet_handle_communication( $url, $request );
    wp_parse_str( $data, $response );
    return $response;
}

/**
 * Get transaction details in mail content
 *
 * @since 1.0.1
 * @param string $label Append in transaction detail.
 * @param array  $gateway Check for Novalnet payments.
 * @return $label
 */
function novalnet_transaction_detail_email( $label, $gateway ) {
    if ( novalnet_check_string( $gateway ) ) {
        $label .= EDD()->session->get( 'novalnet_transaction_comments' );
    }
    return $label;
}

/**
 * Get transaction details in proper alignment for back-end each order history
 *
 * @since 1.0.1
 * @param string $comments Post comments in DB.
 * @return string $comments Retrieve comments in DB.
 */
function novalnet_backend_comments( $comments ) {
    foreach ( $comments as $value ) {
        if ( novalnet_check_string( novalnet_get_order_meta( $value->comment_post_ID, '_edd_payment_gateway' ) ) ) {
            $value->comment_content = nl2br( $value->comment_content );
        }
    }
    return $comments;
}

/**
 * Get transaction details in proper alignment for back-end each order history
 *
 * @since 1.0.1
 * @param array $mail_body    Get list of mail content.
 * @param array $payment_id   Get payment order no.
 * @return string
 */
function novalnet_sale_notification( $mail_body, $payment_id ) {
    if ( ! is_admin() && novalnet_check_string( novalnet_get_order_meta( $payment_id, '_edd_payment_gateway' ) ) ) {
        EDD()->session->set( 'novalnet_transaction_comments', null );
    }
    return $mail_body;
}

/**
 * Add renewal periods in the shop hook as it is not included in shop structure.
 *
 * @since 1.0.1
 * @param date    $expiration Get expire date.
 * @param integer $id      Get order id to process.
 * @param object  $subs_db  Get subscription details.
 * @return array
 */
function novalnet_add_period( $expiration, $id, $subs_db ) {
    if ( novalnet_check_string( $subs_db->gateway ) && in_array( $subs_db->period, array( 'quarter', 'semi-year' ), true ) ) {
        $expires = $subs_db->get_expiration_time();
        // Determine what date to use as the start for the new expiration calculation.
        if ( $expires > current_time( 'timestamp' ) && $subs_db->is_active() ) {
            $base_date = $expires;
        } else {
            $base_date = current_time( 'timestamp' );
        }
        if ( 'quarter' === $subs_db->period ) {
            $length = '+3';
            $period = 'month';
        } else {
            $length = '+6';
            $period = 'month';
        }
        $expiration = gmdate( 'Y-m-d H:i:s', strtotime( $length . $period . ' 23:59:59', $base_date ) );
    }
    return $expiration;
}

/**
 * Perform serialize data.
 *
 * @since 1.1.1
 * @param array   $response          Get server response.
 * @param array   $payment_gateways  List the payment gateways.
 * @param integer $amount            Order placed amount to be shown in invoice.
 * @param array   $novalnet_comments Store the comments.
 * @param boolean $cond The condition check.
 * @param boolean $callback The callback via comments.
 * @return array
 */
function novalnet_get_invoice_comments( $response, $payment_gateways, $amount, $novalnet_comments, $cond = true, $callback = false ) {

    global $edd_options, $wpdb;

    $payment_gateways        = isset( $payment_gateways['gateway'] ) ? $payment_gateways['gateway'] : $payment_gateways;
    $product                 = isset( $edd_options['novalnet_product_id'] ) ? $edd_options['novalnet_product_id'] : ( isset( $response['product_id'] ) ? $response['product_id'] : $edd_options['novalnet_product_id'] );
    $invoice_referece        = 'BNR-' . $product . '-' . $response['order_no'];
    $response['invoice_ref'] = $invoice_ref = ( isset( $response['invoice_ref'] ) && ! empty( $response['invoice_ref'] ) ) ? $response['invoice_ref'] : $invoice_referece;
    $novalnet_comments      .= PHP_EOL;
    if ( isset( $response['tid_status'] ) && '75' === $response['tid_status'] && '41' === $response['key'] ) {
        $novalnet_comments .= __( 'Your order is being verified. Once confirmed, we will send you our bank details to which the order amount should be transferred. Please note that this may take up to 24 hours.', 'edd-novalnet' );
    } elseif ( '100' === $response['tid_status'] || '91' === $response['tid_status'] ) {
        $novalnet_comments .= __( 'Please transfer the amount to the below mentioned account.', 'edd-novalnet' ) . PHP_EOL;
        if ( '100' === $response['tid_status'] ) {
			$due_date = !empty($response['due_date']) ? date_i18n( get_option( 'date_format' ), strtotime( $response['due_date'] ) ) : '';
            $novalnet_comments .= __( 'Due date: ', 'edd-novalnet' ) .$due_date. PHP_EOL;
        }
        $novalnet_comments .= __( 'Account holder: ', 'edd-novalnet' ) . $response['invoice_account_holder'] . PHP_EOL;
        $novalnet_comments .= ' IBAN: ' . $response['invoice_iban'] . PHP_EOL;
        $novalnet_comments .= ' BIC: ' . $response['invoice_bic'] . PHP_EOL;
        $amount = edd_format_amount( $response['amount'] );
        if ( $callback ) {
            $amount = edd_format_amount( $response['amount'] / 100 );
        }
        $novalnet_comments .= ' Bank: ' . $response['invoice_bankname'] . ' ' . trim( isset( $response['invoice_bankplace'] ) ? $response['invoice_bankplace'] : '' ) . PHP_EOL;
        $novalnet_comments .= __( 'Amount: ', 'edd-novalnet' ) . edd_currency_filter( $amount ) . PHP_EOL;
        $increment          = 1;
        if ( $cond ) {
            $novalnet_comments .= PHP_EOL . __( 'Please use the following payment reference for your money transfer, as only through this way your payment is matched and assigned to the order:', 'edd-novalnet' );
            $novalnet_comments .= PHP_EOL;
        }
        $payment_ref = array(
            'TID ' . $response['tid'] . PHP_EOL,
            $invoice_ref . PHP_EOL,
        );
        foreach ( $payment_ref as $key ) {
            $novalnet_comments .= sprintf( __( 'Payment Reference %s: ', 'edd-novalnet' ), $increment++ );
            $novalnet_comments .= $key;
        }
    }
    return $novalnet_comments;
}

/**
 * To get the order comments for cashpayment
 *
 * @param  string $response get the payment response.
 * @return string
 */
function cashpayment_order_comments( $response ) {
    $store_count = 1;
    foreach ( $response as $key => $value ) {
        if ( strpos( $key, 'nearest_store_title' ) !== false ) {
            $store_count++;
        }
    }
    $comments = PHP_EOL;
    if ( $response['cp_due_date'] ) {
        $comments .= __( 'Slip expiry date', 'edd-novalnet' ) . ': ' . $response['cp_due_date'];
    }
    $comments .= PHP_EOL . PHP_EOL;
    $comments .= __( 'Store(s) near you', 'edd-novalnet' ) . PHP_EOL . PHP_EOL;
    for ( $i = 1; $i < $store_count; $i++ ) {
        $comments .= $response[ 'nearest_store_title_' . $i ] . PHP_EOL;
        $comments .= $response[ 'nearest_store_street_' . $i ] . PHP_EOL;
        $comments .= $response[ 'nearest_store_city_' . $i ] . PHP_EOL;
        $comments .= $response[ 'nearest_store_zipcode_' . $i ] . PHP_EOL;
        foreach ( edd_get_country_list() as $country_code => $country ) {
            if ( $country_code === $response[ 'nearest_store_country_' . $i ] ) {
                $comments .= $country . PHP_EOL . PHP_EOL;
            }
        }
    }
    return $comments;
}

/**
 * Perform serialize data.
 *
 * @since 1.1.1
 * @param array $data The resourse data.
 *
 * @return string
 */
function novalnet_serialize_data( $data ) {
    return ! empty( $data ) ? wp_json_encode( $data ) : '';
}

/**
 * Returns Wordpress-blog language.
 *
 * @since  2.0.0
 * @return string
 */
function novalnet_shop_language() {
    return strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
}

/**
 * Formating the date as per the
 * shop structure.
 *
 * @since 2.0.0
 * @param date $date The date value.
 *
 * @return string
 */
function edd_novalnet_formatted_date( $date = '' ) {
    return date_i18n( get_option( 'date_format' ), strtotime( '' === $date ? gmdate( 'Y-m-d H:i:s' ) : $date ) );
}

/**
 * Returns redirect param
 *
 * @since  1.1.1
 * @param  string $payment_name   Get the payment name.
 * @param  array  $payment_data   Get the payment data.
 * @return array
 */
function novalnet_get_redirect_param( $payment_name, $payment_data = array(), $subs_update = array() ) {
    global $edd_options;

    $encode_array = array();

    if ( ! empty( $payment_data ['tariff_period'] ) && ! empty( $payment_data ['tariff_period2'] ) && ! empty( $payment_data ['tariff_period2_amount'] ) ) {
        // Form subscription params to encode.
        $encode_array['tariff_period']         = $payment_data ['tariff_period'];
        $encode_array['tariff_period2']        = $payment_data ['tariff_period2'];
        $encode_array['tariff_period2_amount'] = $payment_data ['tariff_period2_amount'];
    }
    $encode_array['auth_code'] = $payment_data['auth_code'];
    $encode_array['product']   = $payment_data['product'];
    $encode_array['tariff']    = $payment_data['tariff'];
    $encode_array['test_mode'] = $payment_data['test_mode'];
    $encode_array['amount']    = $payment_data ['amount'];
    $encode_array['uniqid']    = unique_string();

    $config_data = generate_hash_value( $encode_array );

    if ( isset($subs_update['update_payment']) && $subs_update['update_payment'] ) {

        $return_url  = isset($subs_update['return_url']) ? $subs_update['return_url'] : '';
        $redirect_url  = add_query_arg( array(  'edd-api' => 'novalnet_update_payment_method'), $return_url);
    } else {
        $redirect_url = add_query_arg(
            array(
                'edd-api' => 'novalnet_redirect_response',
            ),
            edd_get_checkout_uri()
        );
        if ( version_compare(EDD_VERSION, '2.5.17',  '<=') ) {
            $redirect_url = add_query_arg(
                array(
                    'edd-api' => 'products',
                ),
                edd_get_checkout_uri()
            );
        }
    }

    // Form redirect parameters.
    $config_data['return_url']          = $redirect_url;
    $config_data['return_method']       = 'POST';
    $config_data['error_return_url']    = $redirect_url;
    $config_data['error_return_method'] = 'POST';
    $config_data['implementation']      = 'ENC';

    return $config_data;
}

/**
 * Perform HASH Generation process for redirection payment methods
 *
 * @param  array $datas  Get the redirect params.
 * @return string
 */
function generate_hash_value( $datas ) {
    // Form params to encode.
    $encode_array = array( 'auth_code', 'product', 'tariff', 'amount', 'test_mode' );
    if ( ! empty( $datas ['tariff_period'] ) && ! empty( $datas ['tariff_period2'] ) && ! empty( $datas ['tariff_period2_amount'] ) ) {
        // Form subscription params to encode.
        $encode_array = array_merge( $encode_array, array( 'tariff_period', 'tariff_period2', 'tariff_period2_amount' ) );
    }
    foreach ( $encode_array as $key ) {
        $datas[ $key ] = generate_encode( $datas[ $key ], $datas['uniqid'] ); // Encoding process.
    }
    $datas['hash'] = generate_md5_value( $datas ); // Generate hash value.
    return $datas;
}

/*
 * Perform the encoding process for redirection payment methods
 *
 * @param  array   $data    Get the encode data.
 * @param  string  $uniqid  Get the unique id.
 *
 * @return string
 */
function generate_encode( $data, $uniqid ) {
    global $edd_options;
    try {
        $data = htmlentities( base64_encode( openssl_encrypt( $data, 'aes-256-cbc', $edd_options['novalnet_access_key'], true, $uniqid ) ) );
    } catch ( Exception $e ) { // Error log for the exception
        echo esc_html( 'Error: ' . $e );
    }
    return $data;
}

/**
 * Get hash value
 *
 * @param  string $datas  Get the encode data.
 * @return string
 */
function generate_md5_value( $datas ) {
    global $edd_options;
    return hash( 'sha256', ( $datas['auth_code'] . $datas['product'] . $datas['tariff'] . $datas['amount'] . $datas['test_mode'] . $datas['uniqid'] . strrev( $edd_options['novalnet_access_key'] ) ) );
}

/**
 * Generate 30 digit unique string
 *
 * return string
 */
function unique_string() {
    $uniqid = explode( ',', '8,7,6,5,4,3,2,1,9,0,9,7,6,1,2,3,4,5,6,7,8,9,0' );
    shuffle( $uniqid );
    return substr( implode( '', $uniqid ), 0, 16 );
}

/**
 * Form transaction detail.
 *
 * @since 1.1.1
 * @param array   $response  Get response from server.
 * @param string  $test_mode Form testmode value.
 * @param boolean $callback  Check response from callback.
 * @return array
 */
function novalnet_form_transaction_details( $response, $test_mode, $callback = false ) {

    $tid               = ( $callback && !empty( $response['tid_payment'] ) ) ? $response['tid_payment'] : $response['tid'];
    $novalnet_comments = '';

    $payment_name = get_novalnet_payment( $response['order_no'] );

    if ( '100' === $response['status'] && isset( $response['key'] ) && ( '40' === $response['key'] || '41' === $response['key'] ) ) {
        $novalnet_comments .= PHP_EOL . __( 'This is processed as a guarantee payment', 'edd-novalnet' );
    }
    $novalnet_comments .= PHP_EOL . sprintf( __( 'Novalnet transaction ID: %s', 'edd-novalnet' ), ! empty( $response ) ? $tid : $response['shop_tid'] ) . PHP_EOL;

    $novalnet_comments .= ( '1' == $test_mode ) ? __( 'Test order', 'edd-novalnet' ) . PHP_EOL : '';
    if ( isset( $response['tid_status'] ) && '75' === $response['tid_status'] && 'novalnet_sepa' === $payment_name ) {
        $novalnet_comments .= PHP_EOL . __( 'Your order is under verification and we will soon update you with the order status. Please note that this may take upto 24 hours.', 'edd-novalnet' );
    }
    return $novalnet_comments;
}

/**
 * Retrieve the name of the end user.
 *
 * @since 1.1.1
 * @param string $name The customer name value.
 * @return array
 */
function novalnet_retrieve_name( $name ) {
    // Retrieve first name and last name from order objects.
    if ( empty( $name['0'] ) ) {
        $name['0'] = $name['1'];
    }
    if ( empty( $name['1'] ) ) {
        $name['1'] = $name['0'];
    }
    return $name;
}

/**
 * Basic requirements validation for guarantee payment.
 *
 * @since 2.0.0
 * @param  array  $user_data      Get users details.
 * @param  string $payment_name  Get payment name.
 */
function novalnet_guarantee_payment_validation( $user_data, $payment_name,$trial_product = '0' ) {

        global $edd_options;

        $order_amount = ( isset( $user_data['price'] ) && !empty( $user_data['price'] ) ) ? sprintf( '%0.2f', $user_data['price'] ) * 100 : sprintf( '%0.2f', edd_get_cart_total() ) * 100;
        // Billing address.
        $billing_details = isset( $user_data['_edd_user_address'] ) ? unserialize( $user_data['_edd_user_address'][0] ) : $user_data['user_info']['address'];

        $min_amount     = isset( $edd_options[ $payment_name . '_guarantee_minimum_order_amount' ] ) ? $edd_options[ $payment_name . '_guarantee_minimum_order_amount' ] : '';
        $minimum_amount = (trim( $min_amount ) > 999 && novalnet_digits_check( $min_amount ) ) ? $min_amount : 999;
        $error_message  = '';
        // Payment guarantee process.
        if ( '1' === $edd_options[ $payment_name . '_guarantee_enable' ]  && $trial_product == '0' ){

		// Show error on payment field/ checkout.
        if ( ! in_array( $billing_details['country'], array( 'AT', 'DE', 'CH' ), true ) ) {
            $error_message .= '<br>' . __( 'Only Germany, Austria or Switzerland are allowed', 'edd-novalnet' );
        }
        if ( 'EUR' !== edd_get_currency() ) {
             $error_message .= '<br>' . __( 'Only EUR currency allowed', 'edd-novalnet' );
        }
        if ( $order_amount < $minimum_amount  ) {
               /* translators: %s: amount */
               $error_message .= '<br>' . sprintf( __( 'Minimum order amount must be %s', 'edd-novalnet' ), edd_currency_filter( edd_format_amount( $minimum_amount / 100 ) ) );
        }
        EDD()->session->set( $payment_name . '_guarantee_payment_error', trim( str_replace( '<br>', ', ', $error_message ), ',' ) );

        if ( ! isset( $edd_options[ $payment_name . '_force_normal_payment' ] ) ) {
            return $error_message;
        }
    } else {
        // Process as normal payment.
        EDD()->session->set( $payment_name . '_guarantee_payment_error', null );
    }
}

/**
 * Validations for date of birth
 *
 * @param  string $day           Get customer birth day.
 * @param  string $month         Get customer birth month.
 * @param  string $year          Get customer birth year.
 * @param  string $payment_name  Get payment name.
 * @return string
 */
function check_guarantee_payment( $day, $month, $year, $payment_name ) {

    $message    = '';
    $date_check = $day . '.' . $month . '.' . $year;
    $total_days = 0;
    EDD()->session->set( $payment_name . '_dob', null );
    if ( ! empty( novalnet_digits_check( $month ) ) && ! empty( novalnet_digits_check( $year ) ) ) {
        $total_days = cal_days_in_month( CAL_GREGORIAN, $month, $year );
    }

    if ( empty( $day ) && empty( $month ) && empty( $year ) ) {
        $message = __( 'Please enter your date of birth', 'edd-novalnet' );
    } elseif ( ( $day > $total_days ) || ( empty( $day ) || empty( $month ) || empty( $year ) ) || ! preg_match( '/^(0[1-9]|[1-2][0-9]|3[0-1]).(0[1-9]|1[0-2]).([0-9]{4})$/', $date_check ) ) {
        $message = __( 'The date format is invalid', 'edd-novalnet' );
    } elseif ( time() < strtotime( '+18 years', strtotime( $date_check ) ) ) {
        $message = __( 'You need to be at least 18 years old', 'edd-novalnet' );
    } else {
        EDD()->session->set( $payment_name . '_dob', $date_check );
    }
        EDD()->session->set( $payment_name . '_guarantee_dob_payment_error', $message );
        return $message;
}

/**
 * Get order number for transaction
 *
 * @param  array $purchase_data  Get customer detail.
 * @param  array $params         Transaction parameters.
 * @return void
 */
function novalnet_check_subscription( $purchase_data, &$params ) {

    if ( ! empty( $params ['tariff_period'] ) && ! empty( $params ['tariff_period2'] ) && ! empty( $params ['tariff_period2_amount'] ) ) {
        EDD()->session->set( $purchase_data['gateway'] . '_subscription', $params );
        EDD()->session->set( 'novalnet_purchase_data', $purchase_data );
        $subscription = new EDD_Recurring_Gateway();
        $subscription->process_checkout( $purchase_data );
    } else {
        // Get transaction order number.
        $edd_order          = get_novalnet_transaction_order( $purchase_data );
        $params['order_no'] = $edd_order;
    }
}

/**
 * Get payment name
 *
 * @since 2.0.1
 * @param string $payment_id  Get pament name for that id.
 */
function get_novalnet_payment( $payment_id ) {
    $payment_name = novalnet_get_order_meta( $payment_id, '_edd_payment_gateway');
    return $payment_name;
}

/**
 * Get order meta
 *
 * @since 2.2.0
 * @param integer $order_no  Order no
 * @param string $key  Meta key
 */
function novalnet_get_order_meta($order_no, $key) {
	if ( version_compare( EDD_VERSION, '3.0', '<' ) ) {
		return get_post_meta($order_no, $key, true);
	} else {
		return edd_get_order_meta($order_no, $key, true);
	}
}

/**
 * Get order meta
 *
 * @since 2.2.0
 * @param integer $order_no  Order no
 * @param string $key  Meta key
 * @param string $value  Meta value
 */
function novalnet_update_order_meta($order_no, $key, $value) {
	if ( version_compare( EDD_VERSION, '3.0', '<' ) ) {
		return update_post_meta($order_no, $key, $value);
	} else {
		return edd_update_order_meta($order_no, $key, $value);
	}
}
