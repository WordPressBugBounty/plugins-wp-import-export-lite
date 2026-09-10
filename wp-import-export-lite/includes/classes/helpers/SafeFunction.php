<?php
/**
 * Safe Function Checker and Executor Helper.
 *
 * Provides unified, secure validation and execution of custom user functions across Import and Export.
 * Self-contained with zero external dependencies, compatible with PHP 5.6 to 8.4+.
 *
 * @package WP_Import_Export_Lite
 * @since   3.9.33
 */

namespace WpieApp\Core\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * SafeFunction class validates and applies callable functions safely.
 *
 * @since 3.9.33
 */
class SafeFunction {

	/**
	 * Check if a callable function is safe to execute during import or export.
	 *
	 * @since 3.9.33
	 *
	 * @param string $function The function name to validate. Default empty string.
	 * @param string $type     The context type ('import' or 'export'). Default 'import'.
	 *
	 * @return bool True if safe, false otherwise.
	 */
	public static function check( $function = '', $type = 'import' ) {
		if ( ! is_string( $function ) ) {
			return false;
		}

		$function = trim( $function );

		if ( strpos( $function, '\\' ) !== false || strpos( $function, '::' ) !== false ) {
			return false;
		}

		if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $function ) ) {
			return false;
		}

		$functionLower = strtolower( $function );

		// Strict non-bypassable blacklist of dangerous functions, command execution, filesystem, and core operations.
		$blacklist = array(
			// System / Command execution
			'exec',
			'passthru',
			'system',
			'shell_exec',
			'popen',
			'proc_open',
			'pcntl_exec',
			'dl',

			// Code evaluation & dynamic callback dispatch
			'eval',
			'assert',
			'create_function',
			'call_user_func',
			'call_user_func_array',
			'forward_static_call',
			'forward_static_call_array',
			'array_map',
			'array_filter',
			'array_reduce',
			'array_walk',
			'array_walk_recursive',
			'usort',
			'uasort',
			'uksort',

			// Filesystem write / delete / modify
			'file_put_contents',
			'unlink',
			'rmdir',
			'mkdir',
			'rename',
			'copy',
			'chmod',
			'chown',
			'chgrp',
			'touch',
			'symlink',
			'link',
			'fopen',
			'fwrite',
			'fputs',
			'truncate',
			'ftruncate',

			// Filesystem read / inclusion
			'file_get_contents',
			'readfile',
			'file',
			'highlight_file',
			'show_source',
			'include',
			'include_once',
			'require',
			'require_once',
			'virtual',

			// Process / Environment / Configuration
			'putenv',
			'ini_set',
			'ini_alter',
			'ini_restore',
			'set_time_limit',
			'apache_setenv',

			// Serialization / Injection
			'unserialize',

			// Mail / Network
			'mail',
			'mb_send_mail',
			'fsockopen',
			'pfsockopen',

			// WordPress critical user / post / authentication operations
			'wp_delete_user',
			'wp_delete_post',
			'wp_delete_attachment',
			'wp_delete_comment',
			'wp_set_current_user',
			'wp_set_auth_cookie',
			'wp_clear_auth_cookie',
			'wp_create_user',
			'wp_insert_user',
			'wp_update_user',
			'update_option',
			'delete_option',
			'add_option',
			'update_site_option',
			'delete_site_option',
			'add_site_option',
			'wp_install',
			'wp_uninstall',
			'wp_upgrade',
		);

		// Any blacklisted function is immediately blocked and cannot be overridden by filters.
		if ( in_array( $functionLower, $blacklist, true ) ) {
			return false;
		}

		$safePHPBuiltins = [
			// String functions
			'strtolower',
			'strtoupper',
			'ucfirst',
			'lcfirst',
			'ucwords',
			'trim',
			'ltrim',
			'rtrim',
			'str_pad',
			'str_repeat',
			'str_replace',
			'str_ireplace',
			'str_word_count',
			'substr',
			'substr_count',
			'substr_replace',
			'strlen',
			'mb_strlen',
			'mb_strtolower',
			'mb_strtoupper',
			'mb_substr',
			'mb_convert_encoding',
			'mb_detect_encoding',
			'strrev',
			'str_shuffle',
			'str_split',
			'wordwrap',
			'nl2br',
			'number_format',
			'addslashes',
			'stripslashes',
			'quotemeta',
			'htmlspecialchars',
			'htmlspecialchars_decode',
			'htmlentities',
			'html_entity_decode',
			'strip_tags',
			'urlencode',
			'urldecode',
			'rawurlencode',
			'rawurldecode',
			'base64_encode',
			'base64_decode',
			'json_encode',
			'json_decode',
			'md5',
			'sha1',
			'crc32',
			'implode',
			'explode',
			'join',
			'chunk_split',
			'str_contains',
			'str_starts_with',
			'str_ends_with',

			// Number functions
			'abs',
			'ceil',
			'floor',
			'round',
			'max',
			'min',
			'intval',
			'floatval',
			'strval',
			'is_numeric',
			'is_int',
			'is_float',
			'is_string',

			// Array functions (non-callback, value-returning)
			'array_values',
			'array_keys',
			'array_unique',
			'array_reverse',
			'array_flip',
			'array_merge',
			'array_slice',
			'array_combine',
			'array_chunk',
			'array_pad',
			'array_sum',
			'array_product',
			'array_count_values',
			'array_column',
			'array_key_exists',
			'in_array',
			'array_search',
			'count',
			'sizeof',
			'range',

			// Date/time functions
			'date',
			'gmdate',
			'strtotime',
			'mktime',
			'time',
			'microtime',
			'date_create',
			'date_format',
			'date_diff',
			'wp_date',
			'mysql2date',
			'human_time_diff',
			'get_date_from_gmt',
			'get_gmt_from_date',

			// WordPress sanitization / escaping
			'sanitize_text_field',
			'sanitize_title',
			'sanitize_email',
			'sanitize_file_name',
			'sanitize_key',
			'sanitize_user',
			'sanitize_html_class',
			'sanitize_mime_type',
			'esc_html',
			'esc_attr',
			'esc_url',
			'esc_textarea',
			'wp_kses',
			'wp_kses_post',
			'wp_strip_all_tags',
			'absint',
			'wp_slash',
			'wp_unslash',
			'maybe_serialize',
			'is_email',
			'is_serialized',

			// Type casting / checks
			'gettype',
			'boolval',
		];

		if ( in_array( $functionLower, $safePHPBuiltins, true ) ) {
			return true;
		}

		// Block ALL PHP built-in and WordPress core functions not in the safe list above.
		if ( self::isCoreFunction( $function ) ) {
			return false;
		}

		// User-defined functions must be explicitly declared in the allowlist filter.
		$filter = 'wpie_' . $type . '_custom_function_allow_list';

		/**
		 * Filter the list of allowed custom PHP functions for import or export.
		 *
		 * @since 3.9.33
		 *
		 * @param string[] $allowList Array of allowed custom function names.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is prefixed with wpie_.
		$allowList = function_exists( 'apply_filters' ) ? \apply_filters( $filter, array() ) : array();

		if ( is_array( $allowList ) && ! empty( $allowList ) ) {
			foreach ( $allowList as $allowedFunction ) {
				if ( is_string( $allowedFunction ) && $functionLower === strtolower( trim( $allowedFunction ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a function is a PHP built-in or WordPress core function.
	 *
	 * PHP built-in functions are detected via ReflectionFunction::isInternal().
	 * WordPress core functions are detected by checking if the function's
	 * source file is within ABSPATH (wp-includes/ or wp-admin/).
	 *
	 * Any function that is NOT user-defined (i.e. not from a theme or plugin)
	 * is considered a core function and will be blocked unless it appears
	 * in the safe allowlist.
	 *
	 * @since 3.9.33
	 *
	 * @param string $function The function name.
	 *
	 * @return bool True if it's a core PHP/WordPress function, false if user-defined.
	 */
	private static function isCoreFunction( $function ) {
		if ( ! function_exists( $function ) ) {
			return true; // Unknown functions are blocked.
		}

		try {
			$ref = new \ReflectionFunction( $function );
		} catch ( \ReflectionException $e ) {
			return true; // If we can't reflect it, block it.
		} catch ( \Throwable $e ) {
			return true;
		}

		// PHP built-in functions (from C extensions like string, array, date, etc.)
		if ( $ref->isInternal() ) {
			return true;
		}

		// Check if the function is defined within WordPress core (not a plugin/theme).
		$file = $ref->getFileName();

		if ( ! is_string( $file ) || $file === '' ) {
			return true; // No source file — treat as core / block.
		}

		$file    = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $file ) : str_replace( '\\', '/', $file );
		$abspath = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( ABSPATH ) : ( defined( 'ABSPATH' ) ? str_replace( '\\', '/', ABSPATH ) : '' );

		// Functions inside wp-includes/ or wp-admin/ are WordPress core.
		if (
			! empty( $abspath ) &&
			( strpos( $file, $abspath . 'wp-includes/' ) === 0 ||
			  strpos( $file, $abspath . 'wp-admin/' ) === 0 )
		) {
			return true;
		}

		// Function is user-defined (theme, plugin, or mu-plugin).
		return false;
	}

	/**
	 * Apply safe PHP function to single data value (used in export and field-level transforms).
	 *
	 * @since 3.9.33
	 *
	 * @param mixed       $data       The input data value.
	 * @param bool|string $is_enabled Whether the user function is enabled, or function name if 2-arg signature used.
	 * @param string      $php_fun    The function name.
	 * @param string      $type       Context type ('export' or 'import'). Default 'export'.
	 *
	 * @return mixed Processed data, or original data if function is invalid/blocked/fails.
	 */
	public static function apply( $data = '', $is_enabled = false, $php_fun = '', $type = 'export' ) {
		// Support alternative signature: apply( $data, $php_fun, $is_enabled, $type )
		if ( is_string( $is_enabled ) && ! empty( $is_enabled ) && ( empty( $php_fun ) || is_bool( $php_fun ) ) ) {
			$temp_fun   = $is_enabled;
			$is_enabled = is_bool( $php_fun ) ? $php_fun : true;
			$php_fun    = $temp_fun;
		}

		if ( ! $is_enabled || ! is_string( $php_fun ) || trim( $php_fun ) === '' ) {
			return $data;
		}

		$php_fun = trim( $php_fun );

		if ( ! self::check( $php_fun, $type ) ) {
			return $data;
		}

		$funcName = ltrim( $php_fun, '\\' );

		if ( ! function_exists( $funcName ) ) {
			return $data;
		}

		try {
			$data = call_user_func( '\\' . $funcName, $data );
		} catch ( \Exception $ex ) {
			// Fail safely
		} catch ( \Throwable $ex ) {
			// Fail safely for PHP 7+ errors
		}

		return $data;
	}

	/**
	 * Backward compatibility alias for apply().
	 *
	 * @since 3.9.33
	 *
	 * @param mixed       $data       The input data value.
	 * @param bool|string $is_enabled Whether user function is enabled.
	 * @param string      $php_fun    The function name.
	 * @param string      $type       Context type ('export' or 'import'). Default 'export'.
	 *
	 * @return mixed
	 */
	public static function apply_user_function( $data = '', $is_enabled = false, $php_fun = '', $type = 'export' ) {
		return self::apply( $data, $is_enabled, $php_fun, $type );
	}

	/**
	 * Safely execute a custom function with arguments and content (used in import shortcodes).
	 *
	 * @since 3.9.33
	 *
	 * @param string $function The function name.
	 * @param array  $attr     Parsed shortcode attributes / arguments.
	 * @param string $content  Inner content.
	 * @param string $type     Context type ('import' or 'export'). Default 'import'.
	 *
	 * @return string Result of execution or empty string on failure.
	 */
	public static function execute( $function = '', $attr = [], $content = '', $type = 'import' ) {
		if ( ! is_string( $function ) || trim( $function ) === '' ) {
			return '';
		}

		$function = trim( $function );

		if ( ! self::check( $function, $type ) ) {
			return '';
		}

		$funcName = ltrim( $function, '\\' );

		if ( ! function_exists( $funcName ) ) {
			return '';
		}

		try {
			$result = call_user_func( '\\' . $funcName, $attr, $content );
			if ( is_scalar( $result ) || ( is_object( $result ) && method_exists( $result, '__toString' ) ) ) {
				return (string) $result;
			}
			return '';
		} catch ( \Exception $ex ) {
			return '';
		} catch ( \Throwable $ex ) {
			return '';
		}
	}
}

if ( ! class_exists( 'wpie\helpers\SafeFunction' ) ) {
	class_alias( 'WpieApp\Core\Helpers\SafeFunction', 'wpie\helpers\SafeFunction' );
}