<?php
/**
 * Sanitizer Helper.
 *
 * Adapted from WP Import Export v5.0.0 for WP Import Export Lite.
 * Provides unified, secure, typed data sanitization across the plugin.
 * Self-contained with zero external dependencies, compatible with PHP 5.6 to 8.4+.
 *
 * @package WP_Import_Export_Lite
 * @since   5.0.0
 */

namespace WpieApp\Core\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer class provides unified data sanitization across the plugin.
 *
 * @since 5.0.0
 */
class Sanitizer {

	/**
	 * Clean and sanitize data by type.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed  $value Data to sanitize (scalar or array). Default empty string.
	 * @param string $type  Optional. Sanitization type. Default 'text'.
	 * @param mixed  $extra Optional. Additional context (e.g. allowed tags array for html, extension override for filename, default for bool). Default null.
	 *
	 * @return mixed Sanitized data.
	 */
	public static function clean( $value = '', $type = 'text', $extra = null ) {
		$type = is_string( $type ) ? strtolower( trim( $type ) ) : 'text';

		if ( 'int_array' === $type ) {
			return self::intArray( $value );
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized[ $key ] = self::clean( $item, $type, $extra );
			}

			return $sanitized;
		}

		switch ( $type ) {
			case 'text':
				return self::text( $value );

			case 'textarea':
				return self::textarea( $value );

			case 'html':
				return self::html( $value, $extra );

			case 'filename':
				return self::filename( $value, $extra );

			case 'key':
				return self::key( $value );

			case 'ikey':
				return self::ikey( $value );

			case 'label_key':
				return self::labelKey( $value );

			case 'int':
				return self::int( $value );

			case 'absint':
				return self::absint( $value );

			case 'float':
				return self::float( $value );

			case 'bool':
				return self::bool( $value, is_bool( $extra ) ? $extra : false );

			case 'email':
				return self::email( $value );

			case 'url':
				return self::url( $value );

			case 'unserialize':
				return self::safeUnserialize( $value );

			default:
				return self::text( $value );
		}
	}

	/**
	 * Sanitize text field.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string|mixed Sanitized text string, or original value if not a string.
	 */
	public static function text( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( ! self::isNonEmptyStr( $value ) ) {
			return '';
		}

		return \sanitize_text_field( $value );
	}

	/**
	 * Sanitize multiline textarea field.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string|mixed Sanitized textarea string, or original value if not a string.
	 */
	public static function textarea( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( ! self::isNonEmptyStr( $value ) ) {
			return '';
		}

		return \sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize HTML content safely.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed        $value Input value.
	 * @param array|string $tags  Optional. Allowed HTML tags array. Default empty array.
	 *
	 * @return string Sanitized HTML string.
	 */
	public static function html( $value, $tags = array() ) {
		if ( ! self::isNonEmptyStr( $value ) ) {
			return '';
		}

		return self::sanitizeHtml( $value, is_array( $tags ) ? $tags : array() );
	}

	/**
	 * Sanitize filename.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed  $name Optional. Input filename. Default empty string.
	 * @param string $ext  Optional. Extension override. Default empty string.
	 *
	 * @return string Sanitized filename.
	 */
	public static function filename( $name = '', $ext = '' ) {
		if ( ! self::isNonEmptyStr( $name ) ) {
			return '';
		}

		if ( $name === self::ikey( $name ) || preg_match( '/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $name ) ) {
			$new_name = pathinfo( $name, PATHINFO_FILENAME );
		} else {
			$new_name = \sanitize_file_name( pathinfo( $name, PATHINFO_FILENAME ) );
		}

		if ( ! self::isNonEmptyStr( $ext ) ) {
			$ext = self::getExt( $name );
		}

		if ( self::isNonEmptyStr( $ext ) ) {
			$ext      = '.' . trim( self::key( $ext ), '.' );
			$new_name = self::rightTrim( $new_name, $ext ) . $ext;
		}

		return $new_name;
	}

	/**
	 * Sanitize key (lowercase alphanumeric, dashes, and underscores).
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $key Input key.
	 *
	 * @return string Sanitized key.
	 */
	public static function key( $key ) {
		if ( is_numeric( $key ) ) {
			return (string) $key;
		}

		if ( ! self::isNonEmptyStr( $key ) ) {
			return '';
		}

		return \sanitize_key( $key );
	}

	/**
	 * Sanitize case-sensitive key (preserves case, permits alphanumeric, dashes, and underscores).
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $key Input key.
	 *
	 * @return string Sanitized case-sensitive key.
	 */
	public static function ikey( $key ) {
		if ( ! self::isNonEmptyStr( $key ) ) {
			return '';
		}

		return preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key );
	}

	/**
	 * Sanitize and normalize a label string into a clean lookup key.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $label Input label.
	 *
	 * @return string Sanitized label key.
	 */
	public static function labelKey( $label ) {
		if ( ! self::isNonEmptyStr( $label ) ) {
			return '';
		}

		// If voku/portable-utf8 is present, utilize it.
		if ( class_exists( 'voku\helper\UTF8' ) ) {
			$string = \voku\helper\UTF8::to_utf8( $label );
			$string = \voku\helper\UTF8::cleanup( $string );
			$string = \voku\helper\UTF8::normalize_whitespace( $string );
			$cleaned = str_replace( array( ' ', '-', '.', '_' ), '', $string );

			if ( \voku\helper\UTF8::is_ascii( $cleaned ) ) {
				return self::key( \voku\helper\UTF8::strtolower( $cleaned ) );
			}

			return $cleaned;
		}

		// Self-contained fallback using WordPress UTF-8 capabilities.
		$string  = wp_strip_all_tags( (string) $label );
		$string  = preg_replace( '/\s+/', ' ', trim( $string ) );
		$cleaned = str_replace( array( ' ', '-', '.', '_' ), '', $string );

		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $cleaned, 'ASCII' ) ) {
			return self::key( function_exists( 'mb_strtolower' ) ? mb_strtolower( $cleaned, 'UTF-8' ) : strtolower( $cleaned ) );
		}

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $cleaned, 'UTF-8' ) : strtolower( $cleaned );
	}

	/**
	 * Convert and sanitize to integer.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int Sanitized integer.
	 */
	public static function int( $value ) {
		return (int) $value;
	}

	/**
	 * Convert and sanitize to non-negative integer.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int Non-negative integer.
	 */
	public static function absint( $value ) {
		return \absint( (int) $value );
	}

	/**
	 * Sanitize and filter array to retain only positive integers (> 0).
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $values Optional. Input array or scalar. Default empty array.
	 *
	 * @return int[] Array of positive integers.
	 */
	public static function intArray( $values = array() ) {
		if ( is_numeric( $values ) && \absint( $values ) > 0 ) {
			return array( \absint( $values ) );
		}

		if ( ! is_array( $values ) || empty( $values ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) && \absint( $value ) > 0 ) {
				$filtered[] = (int) $value;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Convert and sanitize to float.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return float Sanitized float.
	 */
	public static function float( $value ) {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		return (float) preg_replace( '/[^0-9.\-]/', '', (string) $value );
	}

	/**
	 * Convert and sanitize to boolean.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value   Optional. Input value. Default false.
	 * @param bool  $default Optional. Fallback boolean value. Default false.
	 *
	 * @return bool Sanitized boolean.
	 */
	public static function bool( $value = false, $default = false ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, array( 'true', '1', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $lower, array( 'false', '0', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		return (bool) $default;
	}

	/**
	 * Sanitize email.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $email Input email.
	 *
	 * @return string Sanitized email.
	 */
	public static function email( $email ) {
		if ( ! self::isNonEmptyStr( $email ) ) {
			return '';
		}

		return \sanitize_email( $email );
	}

	/**
	 * Sanitize URL.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $url Input URL.
	 *
	 * @return string Sanitized raw URL.
	 */
	public static function url( $url ) {
		if ( ! self::isNonEmptyStr( $url ) ) {
			return '';
		}

		return \esc_url_raw( $url );
	}

	/**
	 * Safe unserialize to prevent object injection vulnerabilities.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value Data to unserialize.
	 *
	 * @return mixed Unserialized data or original value.
	 */
	public static function safeUnserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( \is_serialized( $value ) ) {
			if ( PHP_VERSION_ID >= 70000 ) {
				return @unserialize( $value, array( 'allowed_classes' => false ) );
			}

			if ( preg_match( '/(^|;|{)\s*[OC]:\+?\d+:/i', $value ) ) {
				return false;
			}

			return @unserialize( $value );
		}

		return $value;
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
	 * Extract file extension.
	 *
	 * @param string $filename File name or path.
	 *
	 * @return string File extension.
	 */
	private static function getExt( $filename ) {
		if ( ! self::isNonEmptyStr( $filename ) ) {
			return '';
		}

		return (string) pathinfo( $filename, PATHINFO_EXTENSION );
	}

	/**
	 * Trim substring from the right of a string.
	 *
	 * @param string $str  Input string.
	 * @param string $trim Substring to trim.
	 *
	 * @return string
	 */
	private static function rightTrim( $str, $trim ) {
		if ( ! self::isNonEmptyStr( $str ) || ! self::isNonEmptyStr( $trim ) ) {
			return (string) $str;
		}

		$len = strlen( $trim );
		if ( substr( $str, -$len ) === $trim ) {
			return substr( $str, 0, -$len );
		}

		return $str;
	}

	/**
	 * Sanitize HTML with wp_kses and allowed tags.
	 *
	 * @param string $content Input HTML content.
	 * @param array  $tags    Optional additional tags.
	 *
	 * @return string Sanitized HTML.
	 */
	private static function sanitizeHtml( $content, $tags = array() ) {
		if ( ! function_exists( 'wp_kses' ) ) {
			if ( version_compare( PHP_VERSION, '7.4.0', '>=' ) ) {
				return strip_tags( (string) $content, array( 'a', 'img', 'strong' ) );
			}
			return strip_tags( (string) $content, '<a><img><strong>' );
		}

		$default_allowed = function_exists( 'wp_kses_allowed_html' ) ? wp_kses_allowed_html( 'post' ) : array();

		$allowed = array(
			'a'      => isset( $default_allowed['a'] ) && is_array( $default_allowed['a'] ) ? $default_allowed['a'] : array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'img'    => isset( $default_allowed['img'] ) && is_array( $default_allowed['img'] ) ? $default_allowed['img'] : array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
			),
			'strong' => isset( $default_allowed['strong'] ) && is_array( $default_allowed['strong'] ) ? $default_allowed['strong'] : array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'span'   => array( 'class' => true ),
			'p'      => array( 'class' => true ),
		);

		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$allowed = array_replace_recursive( $allowed, $tags );
		}

		return \wp_kses( $content, $allowed );
	}
}

if ( ! class_exists( 'wpie\helpers\Sanitizer' ) ) {
	class_alias( 'WpieApp\Core\Helpers\Sanitizer', 'wpie\helpers\Sanitizer' );
}
