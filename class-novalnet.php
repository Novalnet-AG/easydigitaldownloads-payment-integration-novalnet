<?php
/**
 * Plugin Name: Novalnet payment plugin - Easy Digital Downloads
 * Plugin URI:  https://www.novalnet.de/modul/easy-digital-downloads
 * Description: PCI compliant payment solution, covering a full scope of payment services and seamless integration for easy adaptability
 * Author:      Novalnet AG
 * Author URI:  https://www.novalnet.de
 * Version:     2.3.0
 * Text Domain: edd-novalnet
 * Domain Path: languages
 * License:     GPLv2
 *
 * @package Novalnet payment plugin
 */

ob_start();

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Exit if class not found.
if ( ! class_exists( 'Novalnet' ) ) :

	/**
	 * Main Novalnet Class
	 *
	 * @class Novalnet
	 */
	final class Novalnet {

		/**
		 * Main Novalnet Class
		 *
		 * @var Novalnet The single instance of the class
		 * @since 1.1.0
		 */
		private static $instance;

		/**
		 * Main Novalnet Instance
		 *
		 * Ensures only one instance of Novalnet is loaded.
		 *
		 * @since 1.1.0
		 * @static
		 * @see novalnet_payment()
		 * @return Novalnet - Main instance
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Novalnet ) ) {
				self::$instance = new Novalnet();
				
				// If class is not found throw error message.
				add_action('plugins_loaded', function() {
					if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
						add_action( 'admin_notices', array( self::$instance, 'novalnet_admin_notices' ) );
						return;
					}
				});

				self::$instance->setup_constants();
				self::$instance->includes();
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'novalnet_action_links' );
				add_action( 'init', array( self::$instance, 'novalnet_initialize_payments' ) );

				// Registration hooks.
				register_activation_hook( NOVALNET_PLUGIN_FILE, 'novalnet_activation_process' );
				register_deactivation_hook( NOVALNET_PLUGIN_FILE, 'novalnet_deactivate_plugin' );

				// Include payment gateway files.
				foreach ( glob( NOVALNET_PLUGIN_DIR . 'includes/gateways/*.php' ) as $filename ) {
					include_once $filename;
				}
			}
			return self::$instance;
		}

		/**
		 * Setup plugin constants.
		 *
		 * @access private
		 * @since 1.0.1
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'NOVALNET_VERSION' ) ) {
				define( 'NOVALNET_VERSION', '2.3.0' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'NOVALNET_PLUGIN_DIR' ) ) {
				define( 'NOVALNET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'NOVALNET_PLUGIN_URL' ) ) {
				define( 'NOVALNET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'NOVALNET_PLUGIN_FILE' ) ) {
				define( 'NOVALNET_PLUGIN_FILE', __FILE__ );
			}

		}

		/**
		 * Include required files.
		 *
		 * @access private
		 * @since 1.1.0
		 */
		private function includes() {
			include_once NOVALNET_PLUGIN_DIR . 'includes/novalnet-functions.php';
			include_once NOVALNET_PLUGIN_DIR . '/novalnet-install.php';
			include_once NOVALNET_PLUGIN_DIR . 'includes/admin/class-novalnet-global-config.php';
			include_once NOVALNET_PLUGIN_DIR . 'includes/class-novalnet-subscriptions.php';
		}

		/**
		 * Actions to initialize Novalnet Payments to EDD
		 *
		 * Display's Payment in admin
		 *
		 * @since 1.0.1
		 */
		public function novalnet_initialize_payments() {
			/* loads the Novalnet language translation strings */
			load_plugin_textdomain( 'edd-novalnet', false, dirname( plugin_basename( NOVALNET_PLUGIN_FILE ) ) . '/languages/' );
		}

		/**
		 * Display admin notice at WordPress admin during Plug-in activation
		 *
		 * Activate Easy Digital Downloads plugin before you activate the Novalnet payments
		 *
		 * @since 1.0.1
		 */
		public function novalnet_admin_notices() {

			add_settings_error( 'edd-notices', 'edd-novalnet-admin-error', ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ? __( '<b>Easy Digital Downloads Payment Gateway by Novalnet</b>add-on requires <a href="https://easydigitaldownloads.com" target="_new"> Easy Digital Downloads</a> plugin. Please install and activate it.', 'edd-novalnet' ) : ( ! extension_loaded( 'curl' ) ? ( __( '<b>Easy Digital Downloads Payment Gateway by Novalnet</b>requires PHP CURL. You need to activate the CURL function on your server. Please contact your hosting provider.', 'edd-novalnet' ) ) : '' ) ), 'error' );
			settings_errors( 'edd-notices' );
		}
	}
endif; // End if class_exists check.

/**
 * To include all instance object
 *
 * @since 1.0.1
 */
function novalnet_payment() {
	return Novalnet::instance();
}

// Get Novalnet Running.
novalnet_payment();
