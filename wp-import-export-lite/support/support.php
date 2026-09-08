<?php
/**
 * Backward Compatibility and Third-Party Plugin Support Loader.
 *
 * Conditionally loads polyfills and patches for legacy WordPress versions and
 * integrations with third-party security plugins (such as BBQ Firewall).
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/support
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SORT_FLAG_CASE' ) ) {
	define( 'SORT_FLAG_CASE', 5 );
}

$wpie_support_versions = array( '4.4', '4.5', '4.6', '4.7', '4.8', '4.9', '5.0', '5.1' );

global $wp_version;

foreach ( $wpie_support_versions as $wpie_ver ) {
	if ( ! empty( $wp_version ) && version_compare( strval( $wp_version ), $wpie_ver, '<' ) ) {
		$wpie_compat_file = dirname( __FILE__ ) . '/wordpress_' . $wpie_ver . '.php';
		if ( file_exists( $wpie_compat_file ) ) {
			require_once $wpie_compat_file;
		}
	}
}
unset( $wpie_support_versions, $wpie_ver, $wpie_compat_file );

$wpie_compat_plugins = array( 'bbq_firewall' );

foreach ( $wpie_compat_plugins as $wpie_plugin ) {
	$wpie_plugin_file = dirname( __FILE__ ) . '/plugins/' . $wpie_plugin . '.php';
	if ( file_exists( $wpie_plugin_file ) ) {
		require_once $wpie_plugin_file;
	}
}
unset( $wpie_compat_plugins, $wpie_plugin, $wpie_plugin_file );
