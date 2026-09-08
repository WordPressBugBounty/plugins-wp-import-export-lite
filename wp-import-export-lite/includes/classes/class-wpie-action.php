<?php
/**
 * AJAX Action Router.
 *
 * Routes incoming WPIE AJAX requests to the corresponding module controller:
 * Export, Import, Extensions, or Common Actions.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

defined( 'ABSPATH' ) || exit;

use WpieApp\Core\Helpers\Param;

// Ensure Param helper is loaded if invoked outside standard loader.
if ( ! class_exists( '\WpieApp\Core\Helpers\Param' ) ) {
	if ( defined( 'WPIE_HELPERS_DIR' ) && file_exists( WPIE_HELPERS_DIR . '/Param.php' ) ) {
		require_once WPIE_HELPERS_DIR . '/Param.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/helpers/Param.php' ) ) {
		require_once dirname( __FILE__ ) . '/helpers/Param.php';
	}
}

$wpie_action = class_exists( '\WpieApp\Core\Helpers\Param' )
	? Param::requestSanitized( 'action', 'key', '' )
	: ( isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( '' !== $wpie_action && strpos( $wpie_action, 'wpie_export' ) === 0 ) {
	if ( defined( 'WPIE_EXPORT_CLASSES_DIR' ) && file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-actions.php' ) ) {
		require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-actions.php';
		if ( class_exists( '\wpie\export\actions\WPIE_Export_Actions' ) ) {
			$wpie_action_instance = new \wpie\export\actions\WPIE_Export_Actions();
			unset( $wpie_action_instance );
		}
	}
} elseif ( '' !== $wpie_action && strpos( $wpie_action, 'wpie_import' ) === 0 ) {
	if ( defined( 'WPIE_IMPORT_CLASSES_DIR' ) && file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import-actions.php' ) ) {
		require_once WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import-actions.php';
		if ( class_exists( '\wpie\import\WPIE_Import_Actions' ) ) {
			$wpie_action_instance = new \wpie\import\WPIE_Import_Actions();
			unset( $wpie_action_instance );
		}
	}
} elseif ( '' !== $wpie_action && strpos( $wpie_action, 'wpie_ext' ) === 0 ) {
	if ( defined( 'WPIE_CLASSES_DIR' ) && file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
		require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';
		if ( class_exists( '\wpie\addons\WPIE_Extension' ) ) {
			$wpie_ext_instance = new \wpie\addons\WPIE_Extension();
			unset( $wpie_ext_instance );
		}
	}
} else {
	if ( defined( 'WPIE_CLASSES_DIR' ) && file_exists( WPIE_CLASSES_DIR . '/class-wpie-common-action.php' ) ) {
		require_once WPIE_CLASSES_DIR . '/class-wpie-common-action.php';
		if ( class_exists( 'WPIE_Common_Actions' ) ) {
			$wpie_action_instance = new WPIE_Common_Actions();
			unset( $wpie_action_instance );
		}
	}
}

unset( $wpie_action );
