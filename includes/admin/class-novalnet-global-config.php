<?php
/**
 * Novalnet Global Configurations.
 *
 * This script is used for Novalnet global confgiuration of merchant details
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Global_Config
 * @package     edd-novalnet-gateway
 * @author      Novalnet AG
 * @located at  /includes/admin
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Global configuration settings
 */
class Novalnet_Global_Config {

    /**
     * Get all required action and filter to process Novalnet global configuration
     */
    public function __construct() {

        add_action( 'update_option_novalnet_settings', array( $this, 'update_novalnet_settings' ) );

        // Enqueue admin scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'global_admin_script' ) );
        if ( is_admin() ) {
            add_filter( 'edd_settings_sections_gateways', array( $this, 'register_novalnet_global_gateway' ), 1, 1 );
            add_filter( 'edd_settings_gateways', array( $this, 'register_novalnet_global_settings' ), 1, 1 );
            add_action( 'edd_subscription_card_top', array( $this, 'admin_novalnet_bill_times' ) );
            add_action( 'wp_ajax_get_novalnet_apiconfig', array( $this, 'novalnet_apiconfig' ) );
        }
    }

    /**
     * Admin subscription to check the bill time
     *
     * @since  1.1.0
     * @access public
     * @param array $sub Perform cancel process in admin based on recurring.
     */
    public function admin_novalnet_bill_times( $sub ) {
        if ( novalnet_check_string( $sub->gateway ) && ( $sub->get_total_payments() > $sub->bill_times && 0 != $sub->bill_times ) ) {
            novalnet_subs_cancel_perform( $sub );
        }
    }

    /**
     * Adding admin script
     *
     * @since 1.1.0
     */
    public static function global_admin_script() {

        // Enqueue script.
        wp_enqueue_script( 'novalnet-admin-script', NOVALNET_PLUGIN_URL . 'assets/js/novalnet-admin.js', '', NOVALNET_VERSION );

        wp_localize_script(
            'novalnet-admin-script',
            'novalnet_admin',
            array(
                'select_text' => __( '--Select--', 'edd-novalnet' ),
            )
        );
    }

    /**
     * To update and store the registered values given Novalnet Global Configuration
     *
     * @since  1.1.0
     * @access public
     */
    public static function update_novalnet_settings() {
        // Update Global configuraion fields.
        update_option( self::register_novalnet_global_settings() );
    }

    /**
     * Register the payment gateways setting section
     *
     * @since  1.1.0
     * @access public
     * @param  array $gateway_sections Array of sections for the gateways tab in payment gateways tab.
     * @return array $gateway_sections To add Novalnet Global Configuration into sub-sections of payment gateways tab.
     */
    public function register_novalnet_global_gateway( $gateway_sections ) {
        $gateway_sections['novalnet_global_config'] = __( 'Novalnet Global Configuration', 'edd-novalnet' );
        return $gateway_sections;
    }

    /**
     * Adds the settings of the Novalnet Global Configuration
     *
     * @since  1.1.0
     * @access public
     * @param array $gateway_settings List of global settings.
     */
    public function register_novalnet_global_settings( $gateway_settings ) {
		
		$edd_settings = get_option('edd_settings');
		if( !empty( $edd_settings['novalnet_subs_payments'] ) ) {
			$edd_subs_payment = $edd_settings['novalnet_subs_payments'];
		}
		if( !empty( $edd_subs_payment ) ) {
			update_option('temp_subs_payment', $edd_subs_payment );
			$standard_subs_payment = $edd_subs_payment;
		} else if( !empty( get_option('temp_subs_payment') ) ) {
			$standard_subs_payment = get_option('temp_subs_payment');
			$store_temp_subs_payment = array(
				'novalnet_subs_payments' => get_option('temp_subs_payment')
			);
			update_option('edd_settings', array_merge( $edd_settings, $store_temp_subs_payment ));
		} else {
			$standard_subs_payment = array(
				'novalnet_cc',
				'novalnet_sepa',
				'novalnet_invoice',
				'novalnet_prepayment',
				'novalnet_paypal',
			);
		}
		
        $admin_url       = 'https://admin.novalnet.de/';
        $url             = ( version_compare(EDD_VERSION, '2.5.17',  '<=') ) ? 'products' : 'novalnet_callback';
        $novalnet_global = array(
            array(
                'id'   => 'novalnet_settings',
                /* translators: %s: admin URL */
                'name' => '<strong><font color="#1874CD">' . __( 'Novalnet Global Configuration', 'edd-novalnet' ) . '</font> </strong> <p style="width:550%;">' . sprintf( __( 'Please read the Installation Guide before you start and login to the <a href="%s" target="blank">Novalnet Admin Portal</a> using your merchant account. To get a merchant account, mail to <a href="mailto:sales@novalnet.de">sales@novalnet.de</a> or call +49 (089) 923068320.', 'edd-novalnet' ), $admin_url) . '</p>',
                'desc' => __( 'Configure the gateway settings', 'edd-novalnet' ),
                'type' => 'header',
            ),
            array(
                'id'                => 'novalnet_public_key',
                'name'              => __( 'Product activation key', 'edd-novalnet' ) . '<span style="color:#ff0000"> *</span>',
                'type'              => 'text',
                'size'              => 'regular',
                'autocomplete'      => 'off',
                'tooltip_title' => __( 'Product activation key', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Enter the Novalnet Product activation key that is required for authentication and payment processing.', 'edd-novalnet' ),
                /* translators: %s: admin URL */
                'desc'              => '<br/>' . sprintf( __( 'You will find the Product activation key in the <a href="%s" target="blank">Novalnet Admin Portal</a> :  <strong>PROJECT</strong> > <strong>Choose your project</strong> > <strong>Shop Parameters</strong> > <strong>API Signature (Product activation key)</strong>. ', 'edd-novalnet' ), $admin_url ),
            ),
            array(
                'id'   => 'novalnet_merchant_id',
                'name' => __( 'Merchant ID ', 'edd-novalnet' ),
                'type' => 'text',
                'size' => 'regular',
            ),
            array(
                'id'   => 'novalnet_auth_code',
                'name' => __( 'Authentication code', 'edd-novalnet' ),
                'type' => 'text',
                'size' => 'regular',
            ),
            array(
                'id'   => 'novalnet_product_id',
                'name' => __( 'Project ID', 'edd-novalnet' ),
                'type' => 'text',
                'size' => 'regular',
            ),
            array(
                'id'            => 'novalnet_tariff_id',
                'name'          => __( 'Select Tariff ID', 'edd-novalnet' ) . '<span style="color:#ff0000"> * </span>',
                'tooltip_title' => __( 'Select Tariff ID', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project.', 'edd-novalnet' ),
                'type'          => 'text',
                'size'          => 'regular',
            ),
            array(
                'id'   => 'novalnet_access_key',
                'name' => __( 'Payment access key', 'edd-novalnet' ),
                'type' => 'text',
                'size' => 'regular',
            ),
            array(
                'id'                => 'novalnet_client_key',
                'name'              => __( 'Client Key', 'edd-novalnet' ) . '<span style="color:#ff0000"> *</span>',
                'type'              => 'text',
                'size'              => 'regular',
                'autocomplete'      => 'off',
            ),
            array(
                'id'            => 'novalnet_common_payment_logo',
                'name'          => __( 'Display payment logo', 'edd-novalnet' ),
                'tooltip_title' => __( 'Display payment logo', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'The payment method logo(s) will be displayed on the checkout page. ', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => array(
                    '0' => __( 'No', 'edd-novalnet' ),
                    '1' => __( 'Yes', 'edd-novalnet' ),
                ),
                'std'           => '1',
            ),
            array(
                'id'   => 'novalnet_global_settings_onhold_status',
                'name' => '<strong><font color="#1874CD">' . __( 'Order status management for on-hold transactions', 'edd-novalnet' ) . '</font> </strong>',
                'type' => 'header',
            ),
            array(
                'id'            => 'novalnet_onhold_success_status',
                'name'          => __( 'On-hold order status', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'pending',
                'tooltip_title' => __( 'On-hold order status', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used for on-hold orders until the transaction is confirmed or canceled.', 'edd-novalnet' ),
            ),
            array(
                'id'            => 'novalnet_onhold_cancel_status',
                'name'          => __( 'Canceled order status', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => edd_get_payment_statuses(),
                'std'           => 'abandoned',
                'tooltip_title' => __( 'Canceled order status', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Status to be used when order is canceled or fully refunded.', 'edd-novalnet' ),
            ),
            array(
                'id'   => 'novalnet_global_settings_subs_config',
                'name' => '<strong><font color="#1874CD">' . __( 'Dynamic subscription management', 'edd-novalnet' ) . '</font> </strong>',
                'type' => 'header',
            ),
            array(
                'id'      => 'novalnet_subs_enable_option',
                'name'    => __( 'Enable subscriptions', 'edd-novalnet' ),
                'type'    => 'select',
                'options' => array(
                    '0' => __( 'No', 'edd-novalnet' ),
                    '1' => __( 'Yes', 'edd-novalnet' ),
                ),
                'std'     => '0',
            ),
            array(
                'name'        => __( 'Subscription payments', 'edd-novalnet' ),
                'id'          => 'novalnet_subs_payments',
                'type'        => 'select',
                'class'       => 'novalnet_subs_config',
                'multiple'    => true,
                'chosen'      => true,
                'size'        => 'regular',
                'options'     => array(
                    'novalnet_cc'         => __( 'Novalnet Credit/Debit Cards', 'edd-novalnet' ),
                    'novalnet_sepa'       => __( 'Novalnet Direct Debit SEPA', 'edd-novalnet' ),
                    'novalnet_invoice'    => __( 'Novalnet Invoice', 'edd-novalnet' ),
                    'novalnet_prepayment' => __( 'Novalnet Prepayment', 'edd-novalnet' ),
                    'novalnet_paypal'     => __( 'Novalnet PayPal', 'edd-novalnet' ),
                ),
                'std'         => $standard_subs_payment,
            ),
            array(
                'id'            => 'novalnet_subs_tariff_id',
                'class'         => 'novalnet_subs_config',
                'name'          => __( 'Subscription Tariff ID', 'edd-novalnet' ) . '<span style="color:#ff0000"> * </span>',
                'tooltip_title' => __( 'Subscription Tariff ID', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Select the preferred Novalnet subscription tariff ID available for your project. For more information, please refer the Installation Guide.', 'edd-novalnet' ),
                'type'          => 'text',
                'size'          => 'regular',
            ),
            array(
                'name' => '<strong><font color="#1874CD">' . __( 'Notification / Webhook URL Setup', 'edd-novalnet' ) . '</font> </strong>',
                'type' => 'header',
                'id'   => 'novalnet_vendor_settings',
            ),
            array(
                'id'            => 'novalnet_merchant_test_mode',
                'name'          => __( 'Allow manual testing of the Notification / Webhook URL', 'edd-novalnet' ),
                'tooltip_title' => __( 'Allow manual testing of the Notification / Webhook URL', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Enable this to test the Novalnet Notification / Webhook URL manually. Disable this before setting your shop live to block unauthorized calls from external parties.', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => array(
                    '0' => __( 'No', 'edd-novalnet' ),
                    '1' => __( 'Yes', 'edd-novalnet' ),
                ),
                'std'           => '0',
            ),
            array(
                'id'            => 'novalnet_merchant_email',
                'name'          => __( 'Enable e-mail notification', 'edd-novalnet' ),
                'tooltip_title' => __( 'Enable e-mail notification', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Enable this option to notify the given e-mail address when the Notification / Webhook URL is executed successfully.', 'edd-novalnet' ),
                'type'          => 'select',
                'options'       => array(
                    '0' => __( 'No', 'edd-novalnet' ),
                    '1' => __( 'Yes', 'edd-novalnet' ),
                ),
                'std'           => '0',
            ),
            array(
                'id'            => 'novalnet_merchant_email_to',
                'name'          => __( 'Send e-mail to', 'edd-novalnet' ),
                'tooltip_title' => __( 'Send e-mail to', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Notification / Webhook URL execution messages will be sent to this e-mail.', 'edd-novalnet' ),
                'type'          => 'text',
                'size'          => 'regular',
            ),
            array(
                'id'            => 'novalnet_merchant_notify_url',
                'name'          => __( 'Notification / Webhook URL', 'edd-novalnet' ),
                'type'          => 'text',
                'size'          => 'regular',
                'std'           => add_query_arg(
                    array(
                        'edd-api' => $url,
                    ),
                    get_site_url() . '/'
                ),
                'tooltip_title' => __( 'Notification / Webhook URL', 'edd-novalnet' ),
                'tooltip_desc'  => __( 'Notification / Webhook URL is required to keep the merchant’s database/system synchronized with the Novalnet account (e.g. delivery status). Refer the Installation Guide for more information.', 'edd-novalnet' ),
                'allow_blank'   => false,
            ),
        );
        $gateway_settings['novalnet_global_config'] = apply_filters( 'edd_novalnet_global_settings', $novalnet_global );
        return $gateway_settings;
    }

    /**
     * Sent request to novalnet server for merchant configurations
     *
     * @since  2.0.0
     */
    public function novalnet_apiconfig() {
        $request = wp_unslash( $_POST );
        $error   = '';
        if ( ! empty( $request ['novalnet_api_key'] ) ) {
            $request  = array(
                'lang' => novalnet_shop_language(),
                'hash' => trim( $request ['novalnet_api_key'] ),
            );
            $response = novalnet_handle_communication( 'https://payport.novalnet.de/autoconfig', $request );
            $result   = json_decode( $response );

            if ( ! empty( $result->status ) && '100' === $result->status ) {
                    wp_send_json_success( $result );
            } else {

                if ( '106' === $result->status ) {
                    /* translators: %s: Server Address */
                    $error = sprintf( __( 'You need to configure your outgoing server IP address ( %s ) at Novalnet. Please configure it in Novalnet Admin Portal or contact technic@novalnet.de', 'edd-novalnet' ), novalnet_server_addr( 'SERVER_ADDR' ) );
                } else {
                    $error = $result->config_result;
                }
            }
        } else {
            $error = __( 'Please fill in all the mandatory fields', 'edd-novalnet' );
        }

        wp_send_json_error(
            array(
                'error' => $error,
            )
        );
    }

}
new Novalnet_Global_Config();
