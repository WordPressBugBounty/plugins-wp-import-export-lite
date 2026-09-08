<?php
/**
 * $_GET, $_POST, and $_SERVER Request Parameter Helper.
 *
 * Adapted from WP Import Export v5.0.0 for WP Import Export Lite.
 * Provides safe, typed, and unslashed access to request variables.
 * Self-contained with zero external dependencies, compatible with PHP 5.6 to 8.4+.
 *
 * @package WP_Import_Export_Lite
 * @since   5.0.0
 */

namespace WpieApp\Core\Helpers;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Helper class specifically designed to read and sanitize superglobals. Nonce verification is handled by callers.

// Ensure Sanitizer class is available.
if ( ! class_exists( 'WpieApp\Core\Helpers\Sanitizer' ) && file_exists( __DIR__ . '/Sanitizer.php' ) ) {
	require_once __DIR__ . '/Sanitizer.php';
}

/**
 * Param Helper class provides safe, typed access to request variables ($_GET, $_POST, $_REQUEST, and $_SERVER).
 *
 * @since 5.0.0
 */
class Param {

	/**
	 * Get raw unslashed field from $_GET.
	 *
	 * Supports nested paths like 'parent/child'.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field path (e.g. 'key' or 'parent/child'). Default empty string.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed The unslashed value or fallback default.
	 */
	public static function get( $option = '', $default = null ) {
		if ( '' === $option || is_null( $option ) ) {
			return ( isset( $_GET ) && is_array( $_GET ) ) ? \wp_unslash( $_GET ) : ( is_array( $default ) ? $default : array() );
		}

		if ( ! self::isNonEmptyStr( $option ) ) {
			return $default;
		}

		$parts = self::strToArr( $option, '/' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw access intended; callers sanitize appropriately.
		$data = isset( $_GET ) && is_array( $_GET ) ? $_GET : array();

		foreach ( $parts as $part ) {
			if ( is_array( $data ) && isset( $data[ $part ] ) ) {
				$data = $data[ $part ];
			} else {
				$data = null;
				break;
			}
		}

		return self::isEmpty( $data ) ? $default : \wp_unslash( $data );
	}

	/**
	 * Get raw unslashed field from $_POST.
	 *
	 * Supports nested paths like 'parent/child'.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field path (e.g. 'key' or 'parent/child'). Default empty string.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed The unslashed value or fallback default.
	 */
	public static function post( $option = '', $default = null ) {
		if ( '' === $option || is_null( $option ) ) {
			return ( isset( $_POST ) && is_array( $_POST ) ) ? \wp_unslash( $_POST ) : ( is_array( $default ) ? $default : array() );
		}

		if ( ! self::isNonEmptyStr( $option ) ) {
			return $default;
		}

		$parts = self::strToArr( $option, '/' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw access intended; callers sanitize appropriately.
		$data = isset( $_POST ) && is_array( $_POST ) ? $_POST : array();

		foreach ( $parts as $part ) {
			if ( is_array( $data ) && isset( $data[ $part ] ) ) {
				$data = $data[ $part ];
			} else {
				$data = null;
				break;
			}
		}

		return self::isEmpty( $data ) ? $default : \wp_unslash( $data );
	}

	/**
	 * Get raw unslashed field from $_REQUEST.
	 *
	 * Supports nested paths like 'parent/child'.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field path (e.g. 'key' or 'parent/child'). Default empty string.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed The unslashed value or fallback default.
	 */
	public static function request( $option = '', $default = null ) {
		if ( '' === $option || is_null( $option ) ) {
			return ( isset( $_REQUEST ) && is_array( $_REQUEST ) ) ? \wp_unslash( $_REQUEST ) : ( is_array( $default ) ? $default : array() );
		}

		if ( ! self::isNonEmptyStr( $option ) ) {
			return $default;
		}

		$parts = self::strToArr( $option, '/' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_REQUEST ) && is_array( $_REQUEST ) ? $_REQUEST : array();

		foreach ( $parts as $part ) {
			if ( is_array( $data ) && isset( $data[ $part ] ) ) {
				$data = $data[ $part ];
			} else {
				$data = null;
				break;
			}
		}

		return self::isEmpty( $data ) ? $default : \wp_unslash( $data );
	}

	/**
	 * Get sanitized field from $_SERVER.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field name in $_SERVER. Default empty string.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed Sanitized string value or fallback default.
	 */
	public static function server( $option = '', $default = null ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return ( isset( $_SERVER[ $option ] ) && is_scalar( $_SERVER[ $option ] ) )
			? Sanitizer::clean( \wp_unslash( $_SERVER[ $option ] ), 'text' )
			: $default;
	}

	/**
	 * Get sanitized field from $_POST.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field name in $_POST. Default empty string.
	 * @param string $type    Optional. Sanitization type: 'text', 'textarea', 'html', 'int', 'absint', 'float', 'bool', 'key', 'ikey', 'url', 'email', 'filename', etc. Default 'text'.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed Sanitized value.
	 */
	public static function postSanitized( $option = '', $type = 'text', $default = null ) {
		$value = self::post( $option, $default );

		return self::applySanitize( $value, $type, $default );
	}

	/**
	 * Get sanitized field from $_GET.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field name in $_GET. Default empty string.
	 * @param string $type    Optional. Sanitization type: 'text', 'textarea', 'html', 'int', 'absint', 'float', 'bool', 'key', 'ikey', 'url', 'email', 'filename', etc. Default 'text'.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed Sanitized value.
	 */
	public static function getSanitized( $option = '', $type = 'text', $default = null ) {
		$value = self::get( $option, $default );

		return self::applySanitize( $value, $type, $default );
	}

	/**
	 * Get sanitized field from $_REQUEST.
	 *
	 * @since 5.0.0
	 *
	 * @param string $option  Field name in $_REQUEST. Default empty string.
	 * @param string $type    Optional. Sanitization type: 'text', 'textarea', 'html', 'int', 'absint', 'float', 'bool', 'key', 'ikey', 'url', 'email', 'filename', etc. Default 'text'.
	 * @param mixed  $default Optional. Default value to return if field is not found. Default null.
	 *
	 * @return mixed Sanitized value.
	 */
	public static function requestSanitized( $option = '', $type = 'text', $default = null ) {
		$value = self::request( $option, $default );

		return self::applySanitize( $value, $type, $default );
	}

	/**
	 * Apply sanitization based on specified type.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed  $value   The value to sanitize.
	 * @param string $type    Optional. Sanitization type. Default 'text'.
	 * @param mixed  $default Optional. Fallback value if input is empty. Default null.
	 *
	 * @return mixed Sanitized value.
	 */
	private static function applySanitize( $value, $type = 'text', $default = null ) {
		if ( self::isEmpty( $value ) ) {
			return $default;
		}

		return Sanitizer::clean( $value, $type );
	}

	// -------------------------------------------------------------------------
	// Embedded self-contained helper methods
	// -------------------------------------------------------------------------

	/**
	 * Validates whether the passed variable is a non-empty string.
	 *
	 * @param mixed $str The variable to validate.
	 *
	 * @return bool True if non-empty string, false otherwise.
	 */
	private static function isNonEmptyStr( $str ) {
		return is_string( $str ) && '' !== trim( $str );
	}

	/**
	 * Convert delimited string to an array with trimmed elements.
	 *
	 * @param string $str String to split.
	 * @param string $sep Separator delimiter. Default '/'.
	 *
	 * @return array
	 */
	private static function strToArr( $str, $sep = '/' ) {
		if ( ! self::isNonEmptyStr( $str ) ) {
			return array();
		}

		$delimiter = self::isNonEmptyStr( $sep ) ? $sep : '/';
		$parts     = explode( $delimiter, trim( (string) $str ) );

		$trimmed = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' !== $part ) {
				$trimmed[] = $part;
			}
		}

		return $trimmed;
	}

	/**
	 * Checks if a value is empty, treating 0, 0.0, and false as non-empty values.
	 *
	 * @param mixed $var The variable to evaluate.
	 *
	 * @return bool
	 */
	private static function isEmpty( $var = null ) {
		if ( is_null( $var ) ) {
			return true;
		}

		$type = gettype( $var );

		if ( in_array( $type, array( 'boolean', 'integer', 'double' ), true ) ) {
			return false;
		}

		if ( 'string' === $type ) {
			return '' === trim( $var );
		}

		return empty( $var );
	}
}

if ( ! class_exists( 'wpie\helpers\Param' ) ) {
	class_alias( 'WpieApp\Core\Helpers\Param', 'wpie\helpers\Param' );
}
