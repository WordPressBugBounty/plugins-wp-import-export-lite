<?php
/**
 * Plugin Name:       WP Import Export Lite
 * Plugin URI:        https://www.vjinfotech.com/
 * Description:       The Advanced and powerful solution for importing and exporting data to WordPress. Import and Export to Posts, Pages, and Custom Post Types. Ability to update existing data, and much more.
 * Version:           3.9.33
 * Requires at least: 4.4
 * Tested up to:      7.1
 * Requires PHP:      5.6
 * Author:            VJInfotech
 * Author URI:        https://www.vjinfotech.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-import-export-lite
 * Domain Path:       /languages/
 *
 * @package WP_Import_Export_Lite
 */

defined( 'ABSPATH' ) || exit;

// Auto deactivation of conflicting Pro/Lite plugins.
if ( file_exists( dirname( __FILE__ ) . '/deactivate-plugins.php' ) ) {
	require_once dirname( __FILE__ ) . '/deactivate-plugins.php';
	add_action( 'admin_init', 'wpie_auto_deactivate_pro_plugins' );
}

// Plugin version.
if ( ! defined( 'WPIE_PLUGIN_VERSION' ) ) {
	define( 'WPIE_PLUGIN_VERSION', '3.9.33' );
}

// Database schema version.
if ( ! defined( 'WPIE_DB_VERSION' ) ) {
	define( 'WPIE_DB_VERSION', '1.0.0' );
}

// Plugin main file path.
if ( ! defined( 'WPIE_PLUGIN_FILE' ) ) {
	define( 'WPIE_PLUGIN_FILE', __FILE__ );
}

// Plugin directory path with normalized slashes.
if ( ! defined( 'WPIE_PLUGIN_DIR' ) ) {
	define( 'WPIE_PLUGIN_DIR', wp_normalize_path( plugin_dir_path( WPIE_PLUGIN_FILE ) ) );
}

// Plugin URL with correct scheme.
if ( ! defined( 'WPIE_PLUGIN_URL' ) ) {
	$wpie_plugin_url = untrailingslashit( plugin_dir_url( WPIE_PLUGIN_FILE ) );
	if ( is_ssl() ) {
		$wpie_plugin_url = set_url_scheme( $wpie_plugin_url, 'https' );
	}
	define( 'WPIE_PLUGIN_URL', $wpie_plugin_url );
	unset( $wpie_plugin_url );
}

// External resource URLs (using HTTPS).
if ( ! defined( 'WPIE_PLUGIN_SITE' ) ) {
	define( 'WPIE_PLUGIN_SITE', 'https://www.vjinfotech.com' );
}
if ( ! defined( 'WPIE_PLUGIN_API' ) ) {
	define( 'WPIE_PLUGIN_API', 'https://api.vjinfotech.com/' );
}
if ( ! defined( 'WPIE_DOC_URL' ) ) {
	define( 'WPIE_DOC_URL', 'https://plugins.vjinfotech.com/wordpress-import-export/documentation/' );
}
if ( ! defined( 'WPIE_SUPPORT_URL' ) ) {
	define( 'WPIE_SUPPORT_URL', 'https://www.vjinfotech.com/support/' );
}

// Upload directory paths and URLs.
$wpie_upload_dir = wp_upload_dir();

if ( ! defined( 'WPIE_SITE_UPLOAD_DIR' ) ) {
	define( 'WPIE_SITE_UPLOAD_DIR', ! empty( $wpie_upload_dir['basedir'] ) ? wp_normalize_path( $wpie_upload_dir['basedir'] ) : '' );
}

if ( ! defined( 'WPIE_UPLOAD_DIR' ) ) {
	define( 'WPIE_UPLOAD_DIR', WPIE_SITE_UPLOAD_DIR . '/wp-import-export-lite' );
}

if ( ! defined( 'WPIE_UPLOAD_URL' ) ) {
	$wpie_base_url = ! empty( $wpie_upload_dir['baseurl'] ) ? $wpie_upload_dir['baseurl'] . '/wp-import-export-lite' : '';
	if ( is_ssl() && ! empty( $wpie_base_url ) ) {
		$wpie_base_url = set_url_scheme( $wpie_base_url, 'https' );
	}
	define( 'WPIE_UPLOAD_URL', $wpie_base_url );
	unset( $wpie_base_url );
}
unset( $wpie_upload_dir );

if ( ! defined( 'WPIE_ASSETS_URL' ) ) {
	define( 'WPIE_ASSETS_URL', WPIE_PLUGIN_URL . '/assets' );
}

if ( ! defined( 'WPIE_UPLOAD_EXPORT_DIR' ) ) {
	define( 'WPIE_UPLOAD_EXPORT_DIR', WPIE_UPLOAD_DIR . '/export' );
}

if ( ! defined( 'WPIE_UPLOAD_IMPORT_DIR' ) ) {
	define( 'WPIE_UPLOAD_IMPORT_DIR', WPIE_UPLOAD_DIR . '/import' );
}

if ( ! defined( 'WPIE_UPLOAD_TEMP_DIR' ) ) {
	define( 'WPIE_UPLOAD_TEMP_DIR', WPIE_UPLOAD_DIR . '/temp' );
}

if ( ! defined( 'WPIE_UPLOAD_MAIN_DIR' ) ) {
	define( 'WPIE_UPLOAD_MAIN_DIR', WPIE_UPLOAD_DIR . '/upload' );
}

if ( ! function_exists( 'wpie_setup_upload_dirs' ) ) {
	/**
	 * Ensure plugin upload and working directories exist with security index files.
	 *
	 * Creates the base, import, export, temp, and upload directories if they do not exist,
	 * and writes an index.php / .htaccess to prevent directory indexing and unauthorized direct access.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function wpie_setup_upload_dirs() {
		$upload_dirs = array(
			WPIE_UPLOAD_DIR,
			WPIE_UPLOAD_EXPORT_DIR,
			WPIE_UPLOAD_IMPORT_DIR,
			WPIE_UPLOAD_TEMP_DIR,
			WPIE_UPLOAD_MAIN_DIR,
		);

		foreach ( $upload_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			if ( is_dir( $dir ) && wp_is_writable( $dir ) ) {
				$index_file = $dir . '/index.php';
				if ( ! file_exists( $index_file ) ) {
					@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
				}
			}
		}

		// Protect export and temp folders on Apache/LiteSpeed.
		$protected_dirs = array( WPIE_UPLOAD_EXPORT_DIR, WPIE_UPLOAD_TEMP_DIR );
		foreach ( $protected_dirs as $pdir ) {
			if ( is_dir( $pdir ) && wp_is_writable( $pdir ) ) {
				$htaccess_file = $pdir . '/.htaccess';
				if ( ! file_exists( $htaccess_file ) ) {
					@file_put_contents( $htaccess_file, "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" );
				}
			}
		}
	}
}

// Ensure upload directories are initialized on activation or lazily if missing.
register_activation_hook( WPIE_PLUGIN_FILE, 'wpie_setup_upload_dirs' );
if ( ! is_dir( WPIE_UPLOAD_DIR ) ) {
	wpie_setup_upload_dirs();
}

// Addon and asset URLs.
if ( ! defined( 'WPIE_IMPORT_ADDON_URL' ) ) {
	define( 'WPIE_IMPORT_ADDON_URL', WPIE_PLUGIN_URL . '/includes/classes/import/extensions' );
}
if ( ! defined( 'WPIE_EXPORT_ADDON_URL' ) ) {
	define( 'WPIE_EXPORT_ADDON_URL', WPIE_PLUGIN_URL . '/includes/classes/export/extensions' );
}

if ( ! defined( 'WPIE_CSS_URL' ) ) {
	define( 'WPIE_CSS_URL', WPIE_ASSETS_URL . '/css' );
}

if ( ! defined( 'WPIE_JS_URL' ) ) {
	define( 'WPIE_JS_URL', WPIE_ASSETS_URL . '/js' );
}

if ( ! defined( 'WPIE_IMAGES_URL' ) ) {
	define( 'WPIE_IMAGES_URL', WPIE_ASSETS_URL . '/images' );
}

// Internal directories.
if ( ! defined( 'WPIE_INCLUDES_DIR' ) ) {
	define( 'WPIE_INCLUDES_DIR', WPIE_PLUGIN_DIR . 'includes' );
}

if ( ! defined( 'WPIE_LIBRARIES_DIR' ) ) {
	define( 'WPIE_LIBRARIES_DIR', WPIE_PLUGIN_DIR . 'libraries' );
}
if ( ! defined( 'WPIE_CLASSES_DIR' ) ) {
	define( 'WPIE_CLASSES_DIR', WPIE_INCLUDES_DIR . '/classes' );
}

if ( ! defined( 'WPIE_HELPERS_DIR' ) ) {
	define( 'WPIE_HELPERS_DIR', WPIE_CLASSES_DIR . '/helpers' );
}

// Helper dependencies.
if ( file_exists( WPIE_HELPERS_DIR . '/Sanitizer.php' ) ) {
	require_once WPIE_HELPERS_DIR . '/Sanitizer.php';
}
if ( file_exists( WPIE_HELPERS_DIR . '/Param.php' ) ) {
	require_once WPIE_HELPERS_DIR . '/Param.php';
}
if ( file_exists( WPIE_HELPERS_DIR . '/SafeFunction.php' ) ) {
	require_once WPIE_HELPERS_DIR . '/SafeFunction.php';
}

if ( ! defined( 'WPIE_IMPORT_CLASSES_DIR' ) ) {
	define( 'WPIE_IMPORT_CLASSES_DIR', WPIE_CLASSES_DIR . '/import' );
}

if ( ! defined( 'WPIE_EXPORT_CLASSES_DIR' ) ) {
	define( 'WPIE_EXPORT_CLASSES_DIR', WPIE_CLASSES_DIR . '/export' );
}

if ( ! defined( 'WPIE_VIEW_DIR' ) ) {
	define( 'WPIE_VIEW_DIR', WPIE_INCLUDES_DIR . '/views' );
}

// Schedule class.
if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-schedule.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-schedule.php';
	new \wpie\WPIE_Schedule();
}

// Compatibility support.
if ( file_exists( WPIE_PLUGIN_DIR . 'support/support.php' ) ) {
	require_once WPIE_PLUGIN_DIR . 'support/support.php';
}

// Helper functions.
if ( file_exists( WPIE_CLASSES_DIR . '/function.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/function.php';
}

add_action( 'init', 'wpie_init_addons' );

if ( ! function_exists( 'wpie_init_addons' ) ) {
	/**
	 * Initialize plugin extensions and add-ons.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function wpie_init_addons() {
		if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
			require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';

			$wpie_ext = new \wpie\addons\WPIE_Extension();
			$wpie_ext->wpie_init_extensions();
			unset( $wpie_ext );
		}
	}
}

// Plugin updates handler.
if ( file_exists( WPIE_CLASSES_DIR . '/class-updates.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-updates.php';
	new \wpie\Updates();
}

// Safely retrieve request action with class check fallback.
$wpie_request_action = '';
if ( class_exists( '\WpieApp\Core\Helpers\Param' ) ) {
	$wpie_request_action = \WpieApp\Core\Helpers\Param::requestSanitized( 'action', 'key', '' );
} elseif ( isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$wpie_request_action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$wpie_is_doing_ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );

if ( is_admin() && $wpie_is_doing_ajax && '' !== $wpie_request_action && strpos( $wpie_request_action, 'wpie' ) === 0 ) {
	if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-action.php' ) ) {
		require_once WPIE_CLASSES_DIR . '/class-wpie-action.php';
	}
} elseif ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-general.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-general.php';
	new \wpie\core\WPIE_General();
}