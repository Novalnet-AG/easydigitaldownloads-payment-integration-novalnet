<?php
/**
 * Novalnet Plugin installation process.
 *
 * This file is used for creating tables while installing the plugins.
 *
 * @version  	2.3.0
 * @package  	Novalnet-gateway
 * @category 	Class
 * @author   	Novalnet AG
 * @copyright   Novalnet (https://www.novalnet.de)
 * @license     https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Novalnet tables if not exist while activating the plugins
 * Calls from the hook "register_activation_hook"
 */
function novalnet_activation_process() {
	global $wpdb;
	$charset_collate       = $wpdb->get_charset_collate();
	$edd_recurring_version = preg_replace( '/[^0-9.].*/', '', get_option( 'edd_recurring_version' ) );
	if ( version_compare($edd_recurring_version, '2.7',  '>=') ) {
		$sub_details = $wpdb->get_results( "SELECT id, parent_payment_id FROM {$wpdb->prefix}edd_subscriptions" );
		foreach ( $sub_details as $row ) {
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} where post_parent = '%d' AND post_type='edd_payment'", array( $row->parent_payment_id ) ) );
			foreach ( $result as $post_id ) {
				novalnet_update_order_meta( $post_id->ID, 'subscription_id', $row->id );
			}
		}
	}
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	if ( ! get_option( 'novalnet_db_version' ) || NOVALNET_VERSION !== get_option( 'novalnet_db_version' ) ) {
		dbDelta(
			"
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_transaction_detail (
		id int(11) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
		order_no bigint(20) unsigned NOT NULL COMMENT 'Post ID for the order in shop',
		vendor_id int(8) unsigned NOT NULL COMMENT 'Novalnet Vendor ID',
		auth_code varchar(30) NOT NULL COMMENT 'Novalnet Authentication code',
		product_id int(8) unsigned NOT NULL COMMENT 'Novalnet Project ID',
		tariff_id int(8) unsigned NOT NULL COMMENT 'Novalnet Tariff ID',
		payment_id int(8) unsigned NOT NULL COMMENT 'Payment ID',
		payment_type varchar(50) NOT NULL COMMENT 'Executed Payment type of this order',
		tid bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
		subs_id int(8) unsigned DEFAULT NULL COMMENT 'Subscription Status',
		amount int(11) NOT NULL COMMENT 'Transaction amount in cents',
		callback_amount int(11) DEFAULT '0' COMMENT 'Transaction paid amount in cents',
		currency varchar(5) NOT NULL COMMENT 'Transaction currency',
		gateway_status int(11) DEFAULT NULL COMMENT 'Novalnet transaction status',
		test_mode tinyint(1) unsigned DEFAULT NULL COMMENT 'Transaction test mode status',
		customer_id int(11) unsigned DEFAULT NULL COMMENT 'Customer ID from shop',
		customer_email varchar(50) DEFAULT NULL COMMENT 'Customer ID from shop',
		date datetime NOT NULL COMMENT 'Transaction Date for reference',
		PRIMARY KEY (id),
		INDEX tid (tid),
		INDEX customer_id (customer_id),
		INDEX order_no (order_no)
		) $charset_collate COMMENT='Novalnet Transaction History';"
		);

		dbDelta(
			"
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_subscription_details (
			id int(11) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			order_no bigint(20) NOT NULL COMMENT 'Post ID for the order in shop',
			payment_type varchar(50) NOT NULL COMMENT 'Payment Type',
			recurring_payment_type varchar(50) NOT NULL COMMENT 'Recurring Payment Type',
			recurring_amount int(11) DEFAULT NULL COMMENT 'Amount in cents',
			tid bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
			recurring_tid bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
			subs_id int(8) unsigned DEFAULT NULL COMMENT 'Subscription Status',
			signup_date datetime DEFAULT NULL COMMENT 'Subscription signup date',
			next_payment_date datetime DEFAULT NULL COMMENT 'Subscription next cycle date',
			termination_reason varchar(255) DEFAULT NULL COMMENT 'Subscription termination reason by merchant',
			termination_at datetime DEFAULT NULL COMMENT 'Subscription terminated date',
			subscription_length int(8) NOT NULL DEFAULT 0 COMMENT 'Length of Subscription',
			PRIMARY KEY (id),
			INDEX order_no (order_no),
			INDEX tid (tid)
			) $charset_collate COMMENT='Novalnet Subscription Payment Details';"
		);

		update_option( 'novalnet_version_update', true );
		if ( ! get_option( 'novalnet_db_version' ) ) {
			add_option( 'novalnet_db_version', NOVALNET_VERSION );
		} elseif ( NOVALNET_VERSION !== get_option( 'novalnet_db_version' ) ) {

			if ( version_compare(get_option( 'novalnet_db_version' ), '2.3.0',  '<=') ) {

				$edd_settings = get_option('edd_settings');

				$store_temp_subs_payment = array();

				if( isset($edd_settings['novalnet_subs_payments']) && !empty( $edd_settings['novalnet_subs_payments'] ) ) {
						$store_temp_subs_payment = array(
						'novalnet_subs_payments' => array_keys($edd_settings['novalnet_subs_payments'])
					);
				}

				update_option('edd_settings', array_merge( $edd_settings, $store_temp_subs_payment ));
			}
			update_option( 'novalnet_db_version', NOVALNET_VERSION );
		}

	}// End if().
}
