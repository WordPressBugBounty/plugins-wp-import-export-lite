<?php
/**
 * Compatibility handler for BBQ (Block Bad Queries) Firewall Plugin.
 *
 * Prevents false-positive blocks from BBQ Firewall during large or serialized WPIE AJAX requests.
 * Compatible with PHP 5.6 to 8.4+.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/support/plugins
 * @since      3.9.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BBQ_VERSION' ) ) {
	return;
}

// Hook early at priority -999 so WPIE actions are exempt from BBQ payload inspection.
add_action( 'plugins_loaded', 'wpie_remove_bbq_core', -999 );

if ( ! function_exists( 'wpie_remove_bbq_core' ) ) {
	/**
	 * Removes the BBQ Firewall core hook during legitimate WPIE AJAX and administrative actions.
	 *
	 * BBQ Firewall inspects query strings and request bodies for suspicious patterns, which can
	 * falsely flag complex import/export rules or XPath queries. This unhooks BBQ only when a valid
	 * WPIE action is being processed in an AJAX or admin context.
	 *
	 * @since 3.9.2
	 * @return void
	 */
	function wpie_remove_bbq_core() {
		$is_ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		if ( ! $is_ajax && ! is_admin() ) {
			return;
		}

		$action = '';
		if ( class_exists( '\WpieApp\Core\Helpers\Param' ) ) {
			$action = \WpieApp\Core\Helpers\Param::requestSanitized( 'action', 'key', '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( empty( $action ) || 0 !== strpos( $action, 'wpie' ) ) {
			return;
		}

		remove_action( 'plugins_loaded', 'bbq_core' );
	}
}
