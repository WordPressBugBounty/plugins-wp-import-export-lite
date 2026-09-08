<?php
/**
 * Global Utility Functions.
 *
 * Provides standalone sanitization, draft cleanup, encoding, and autoload helpers.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

defined( 'ABSPATH' ) || exit;

use WpieApp\Core\Helpers\Sanitizer;

// Ensure Sanitizer helper is loaded if needed.
if ( ! class_exists( '\WpieApp\Core\Helpers\Sanitizer' ) ) {
	if ( defined( 'WPIE_HELPERS_DIR' ) && file_exists( WPIE_HELPERS_DIR . '/Sanitizer.php' ) ) {
		require_once WPIE_HELPERS_DIR . '/Sanitizer.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/helpers/Sanitizer.php' ) ) {
		require_once dirname( __FILE__ ) . '/helpers/Sanitizer.php';
	}
}

if ( ! function_exists( 'wpie_sanitize_field' ) ) {

	/**
	 * Sanitize text field input.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $var Input variable to sanitize.
	 *
	 * @return string|mixed Sanitized text string or array.
	 */
	function wpie_sanitize_field( $var ) {
		if ( class_exists( '\WpieApp\Core\Helpers\Sanitizer' ) ) {
			return Sanitizer::clean( $var, 'text' );
		}

		return is_array( $var ) ? array_map( 'sanitize_text_field', $var ) : sanitize_text_field( $var );
	}
}

if ( ! function_exists( 'wpie_sanitize_textarea' ) ) {

	/**
	 * Sanitize multiline textarea input.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $var Input variable to sanitize.
	 *
	 * @return string|mixed Sanitized textarea string or array.
	 */
	function wpie_sanitize_textarea( $var ) {
		if ( class_exists( '\WpieApp\Core\Helpers\Sanitizer' ) ) {
			return Sanitizer::clean( $var, 'textarea' );
		}

		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return is_array( $var ) ? array_map( 'sanitize_textarea_field', $var ) : sanitize_textarea_field( $var );
		}

		return is_array( $var ) ? array_map( 'sanitize_text_field', $var ) : sanitize_text_field( $var );
	}
}

add_action( 'init', 'wpie_remove_draft_entries' );

if ( ! function_exists( 'wpie_remove_draft_entries' ) ) {

	/**
	 * Clean up stale import draft records older than 1 hour.
	 *
	 * Throttled via a transient to run at most once per hour, avoiding
	 * repetitive database DELETE queries on every HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function wpie_remove_draft_entries() {
		// Only run in admin or cron context, and throttle once per hour.
		$is_cron = function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : ( defined( 'DOING_CRON' ) && DOING_CRON );
		if ( ! is_admin() && ! $is_cron ) {
			return;
		}

		if ( false !== get_transient( 'wpie_cleanup_draft_entries' ) ) {
			return;
		}

		set_transient( 'wpie_cleanup_draft_entries', 1, HOUR_IN_SECONDS );

		global $wpdb;

		$cutoff_time = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpie_template WHERE opration = 'import-draft' AND last_update_date < %s",
				$cutoff_time
			)
		);
	}
}

if ( ! function_exists( 'wpie_get_export_id' ) ) {

	/**
	 * Retrieve current export ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int Positive export ID, or 0 if not set.
	 */
	function wpie_get_export_id() {
		global $wpie_export_id;

		return empty( $wpie_export_id ) ? 0 : absint( $wpie_export_id );
	}
}

if ( ! function_exists( 'wpie_load_vendor_autoloader' ) ) {

	/**
	 * Loads the appropriate Composer autoloader based on current PHP version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function wpie_load_vendor_autoloader() {
		$file = version_compare( PHP_VERSION, '7.3', '<' )
			? 'vendor_deprecated/autoload.php'
			: 'vendor/autoload.php';

		$path = wp_normalize_path( WPIE_PLUGIN_DIR . '/' . $file );

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

if ( ! function_exists( 'wpie_utf8_encode' ) ) {

	/**
	 * Safely encode string to UTF-8 without using deprecated/removed utf8_encode() in PHP 8.2+.
	 *
	 * @since 3.9.33
	 *
	 * @param mixed $data Data to encode. Default empty string.
	 *
	 * @return mixed UTF-8 encoded string or original data.
	 */
	function wpie_utf8_encode( $data = '' ) {
		if ( empty( $data ) || ! is_scalar( $data ) ) {
			return $data;
		}

		$string_val = strval( $data );

		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $string_val, 'UTF-8', 'ISO-8859-1' );
		}

		if ( function_exists( 'iconv' ) ) {
			$encoded = @iconv( 'ISO-8859-1', 'UTF-8//IGNORE', $string_val );
			if ( false !== $encoded ) {
				return $encoded;
			}
		}

		if ( function_exists( 'utf8_encode' ) ) {
			return @utf8_encode( $string_val );
		}

		return $string_val;
	}
}
