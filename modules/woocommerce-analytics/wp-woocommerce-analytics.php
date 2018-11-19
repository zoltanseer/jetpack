<?php
/**
 * Jetpack_WooCommerce_Analytics is ported from the Jetpack_Google_Analytics code.
 *
 * @package Jetpack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_basename( 'classes/wp-woocommerce-analytics-universal.php' );
require_once( ABSPATH .'wp-admin/includes/plugin.php' );

/**
 * Class Jetpack_WooCommerce_Analytics
 * Instantiate WooCommerce Analytics
 */
class Jetpack_WooCommerce_Analytics {

	/**
	 * Instance of this class
	 *
	 * @var Jetpack_WooCommerce_Analytics - Static property to hold our singleton instance
	 */
	private static $instance = false;

	/**
	 * Instance of the Universal functions
	 *
	 * @var Static property to hold concrete analytics impl that does the work (universal or legacy)
	 */
	private static $analytics = false;

	/**
	 * WooCommerce Analytics is only available to Jetpack connected WooCommerce stores with both plugins set to active
	 * and WooCommerce version 3.0 or higher
	 *
	 * @return bool
	 */
	public static function shouldTrackStore() {
		/**
		 * Make sure WooCommerce is installed and active
		 *
		 * This action is documented in https://docs.woocommerce.com/document/create-a-plugin
		 */
		if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return false;
		}
		// Tracking only Site pages
		if ( is_admin() ) {
			return false;
		}
		// Don't track site admins
		if ( is_user_logged_in() && in_array( 'administrator', wp_get_current_user()->roles ) ) {
			return false;
		}
		// Make sure Jetpack is installed and active
		if ( ! Jetpack::is_active() ) {
			return false;
		}
		// Ensure the WooCommerce class exists and is a valid version
		$minimum_woocommerce_active = class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '3.0', '>=' );
		if ( ! $minimum_woocommerce_active ) {
			return false;
		}
		return true;
	}

	/**
	 * This is our constructor, which is private to force the use of get_instance()
	 *
	 * @return void
	 */
	private function __construct() {
		$analytics = new Jetpack_WooCommerce_Analytics_Universal();
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function get_instance() {
		if ( ! self::shouldTrackStore() ) {
			return;
		}
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

global $jetpack_woocommerce_analytics;
$jetpack_woocommerce_analytics = Jetpack_WooCommerce_Analytics::get_instance();
