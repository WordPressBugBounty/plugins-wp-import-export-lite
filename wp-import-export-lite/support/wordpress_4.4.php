<?php
/**
 * Backward compatibility polyfill for WordPress 4.4.
 *
 * Provides fallback definition for `get_term_meta()` if running on WordPress versions prior to 4.4.0.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/support
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress core polyfill function.

if ( ! function_exists( 'get_term_meta' ) ) {
	/**
	 * Retrieves metadata for a specified term.
	 *
	 * Polyfill for `get_term_meta()` introduced in WordPress 4.4.0.
	 *
	 * @since 1.0.0
	 * @param int    $term_id Term ID.
	 * @param string $key     Optional. The meta key to retrieve. By default, returns data for all keys.
	 * @param bool   $single  Optional. Whether to return a single value. Default false.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
	 */
	function get_term_meta( $term_id = 0, $key = '', $single = false ) {
		return get_metadata( 'term', absint( $term_id ), (string) $key, (bool) $single );
	}
}