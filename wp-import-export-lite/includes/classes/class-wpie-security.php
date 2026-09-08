<?php
/**
 * Security Helper Class.
 *
 * Handles nonce verification and capability checks for incoming AJAX and admin requests.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

namespace wpie;

use WpieApp\Core\Helpers\Param;

defined( 'ABSPATH' ) || exit;

// Ensure Param helper is loaded if invoked independently.
if ( ! class_exists( '\WpieApp\Core\Helpers\Param' ) ) {
	if ( defined( 'WPIE_HELPERS_DIR' ) && file_exists( WPIE_HELPERS_DIR . '/Param.php' ) ) {
		require_once WPIE_HELPERS_DIR . '/Param.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/helpers/Param.php' ) ) {
		require_once dirname( __FILE__ ) . '/helpers/Param.php';
	}
}

/**
 * Security Class
 *
 * Provides static methods for request authorization and nonce verification.
 *
 * @since 1.0.0
 */
class Security {

	/**
	 * Verify the current request's nonce and user capability.
	 * Terminates execution with JSON error on failure.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $action Optional. Capability name required to perform the action. Default 'manage_options'.
	 *
	 * @return void
	 */
	public static function verify_request( $action = 'manage_options' ) {
		$error_data = array(
			'status' => 'error',
		);

		if ( ! self::verify_nonce() ) {
			$error_data['message'] = __( 'Session Expired. Please refresh page.', 'wp-import-export-lite' );
			wp_send_json( $error_data );
		}

		$cap = ( is_string( $action ) && '' !== trim( $action ) ) ? trim( $action ) : 'manage_options';

		if ( ! current_user_can( $cap ) ) {
			$error_data['message'] = __( 'Permission denied!', 'wp-import-export-lite' );
			wp_send_json( $error_data );
		}
	}

	/**
	 * Verify the security nonce from the request.
	 *
	 * Accepts nonces throughout their entire 24-hour lifetime (return 1 or 2 from wp_verify_nonce).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool True if valid nonce, false otherwise.
	 */
	public static function verify_nonce() {
		$nonce = class_exists( '\WpieApp\Core\Helpers\Param' )
			? Param::requestSanitized( 'wpieSecurity', 'text', '' )
			: ( isset( $_REQUEST['wpieSecurity'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpieSecurity'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! is_string( $nonce ) || '' === trim( $nonce ) ) {
			return false;
		}

		return false !== \wp_verify_nonce( trim( $nonce ), 'wpie-security' );
	}
}
