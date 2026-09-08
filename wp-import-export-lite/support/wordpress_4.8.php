<?php
/**
 * Backward compatibility polyfill for WordPress 4.8.
 *
 * Provides fallback definition for `wp_doing_cron()` if running on WordPress versions prior to 4.8.0.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/support
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress core polyfill function.

if ( ! function_exists( 'wp_doing_cron' ) ) {
	/**
	 * Determines whether the current request is a WordPress cron request.
	 *
	 * Polyfill for `wp_doing_cron()` introduced in WordPress 4.8.0.
	 *
	 * @since 1.0.0
	 * @return bool True if WP-Cron request, false otherwise.
	 */
	function wp_doing_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}
